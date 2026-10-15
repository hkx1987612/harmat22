"""In-process ASGI fixtures only: synthetic bytes and temporary SQLite stores."""

import asyncio
from contextlib import closing, redirect_stderr, redirect_stdout
from dataclasses import replace
import io
import json
from pathlib import Path
import sqlite3
import tempfile
import unittest
from unittest.mock import Mock, patch
from urllib.parse import urlencode

import asgi_app
from asgi_app import ASGIApp
from ingress import Config, MAX_BODY_BYTES
from metadata_store import MetadataStore
from test_ingress import CONFIG, NOW, PAGE, PEER, event, payload, sign, wire


def request(body=b"", more=False):
    return {"type": "http.request", "body": body, "more_body": more}


def scope(method="POST", path="/webhook", body=None, **changes):
    body = wire(payload()) if body is None else body
    value = dict(type="http", asgi={"version": "3.0"}, http_version="1.1",
                 method=method, path=path, raw_path=path.encode("utf-8"), query_string=b"",
                 headers=[(b"content-type", b"application/json"),
                          (b"x-hub-signature-256", sign(body).encode("ascii"))])
    value.update(changes)
    return value


class ASGITests(unittest.IsolatedAsyncioTestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.path = Path(self.tmp.name) / "synthetic.sqlite"
        self.store = MetadataStore.create(self.path)
        self.app = ASGIApp(CONFIG, self.store, metadata_only_test=True)

    def state(self):
        with closing(sqlite3.connect(self.path)) as db:
            return json.loads(db.execute("SELECT state FROM metadata").fetchone()[0])

    async def call(self, *, app=None, request_scope=None, events=None, receive=None, on_send=None):
        sent = []
        events = iter([request(wire(payload()))] if events is None else events)

        async def default_receive():
            try:
                return next(events)
            except StopIteration:
                await asyncio.Future()

        async def send(message):
            if on_send:
                on_send(message)
            sent.append(message)

        with patch("asgi_app.time.time_ns", return_value=NOW * 1_000_000):
            await (self.app if app is None else app)(
                scope() if request_scope is None else request_scope,
                default_receive if receive is None else receive, send)
        return sent

    def status(self, sent):
        self.assertEqual([e["type"] for e in sent], ["http.response.start", "http.response.body"])
        self.assertEqual(sent[1]["more_body"], False)
        headers = dict(sent[0]["headers"])
        self.assertEqual(headers[b"content-length"], str(len(sent[1]["body"])).encode())
        self.assertEqual(headers[b"cache-control"], b"no-store")
        return sent[0]["status"]

    async def test_default_off_and_scope_cannot_supply_bootstrap(self):
        self.assertEqual(asgi_app.app.config, Config())
        self.assertFalse(asgi_app.app.metadata_only_test)
        self.assertIsNone(asgi_app.app.store)
        injected = scope(config=CONFIG, store=self.store, metadata_only_test=True,
                         app_secret=CONFIG.app_secret, receive_enabled=True)
        with patch.object(self.store, "accept") as accept:
            self.assertEqual(self.status(await self.call(app=asgi_app.app, request_scope=injected)), 503)
            self.assertEqual(self.status(await self.call(app=ASGIApp(CONFIG, self.store))), 503)
            accept.assert_not_called()

    async def test_all_config_gates_remain_required(self):
        for config in (Config(), replace(CONFIG, receive_enabled=False),
                       replace(CONFIG, data_processing_approved=False), replace(CONFIG, app_secret="")):
            self.assertEqual(self.status(await self.call(app=ASGIApp(config, self.store, True))), 503)
        self.assertEqual(self.status(await self.call(app=ASGIApp(CONFIG, None, True))), 503)

    async def test_bootstrap_exact_types_no_db_creation_or_secret_loading(self):
        for args in (({}, self.store, True), (CONFIG, str(self.path), True), (CONFIG, self.store, 1)):
            with self.assertRaisesRegex(ValueError, "Invalid bootstrap configuration"):
                ASGIApp(*args)
        missing = Path(self.tmp.name) / "missing.sqlite"
        with patch.dict("os.environ", {"APP_SECRET": "synthetic-do-not-load"}), patch.object(
                MetadataStore, "create", side_effect=AssertionError("no creation")):
            app = ASGIApp(CONFIG, MetadataStore(missing), True)
            self.assertEqual(self.status(await self.call(app=app)), 503)
        self.assertFalse(missing.exists())

    async def test_health_and_readiness_static_without_credentials_or_reads(self):
        async def forbidden():
            raise AssertionError("must not read")

        for app in (ASGIApp(), self.app):
            for path, expected in (("/healthz", 200), ("/readyz", 503)):
                result = await self.call(app=app, request_scope=scope("GET", path), receive=forbidden)
                self.assertEqual(self.status(result), expected)
                for marker in (b"model=false", b"production-ready=false"):
                    self.assertIn(marker, result[1]["body"])
                if path == "/healthz":
                    self.assertIn(b"receive-only staging", result[1]["body"])
                    self.assertIn(b"sender=false", result[1]["body"])
                for secret in (PAGE, CONFIG.app_secret, CONFIG.identity_key, CONFIG.verify_token):
                    self.assertNotIn(secret.encode(), result[1]["body"])

    async def test_only_public_routes_and_supported_methods(self):
        for path in ("/admin", "/resume", "/mutate", "/webhook/", "/", "/healthz/secret"):
            self.assertEqual(self.status(await self.call(request_scope=scope("GET", path))), 404)
        for method, path in (("PUT", "/webhook"), ("HEAD", "/webhook"),
                             ("POST", "/healthz"), ("OPTIONS", "/readyz")):
            self.assertEqual(self.status(await self.call(request_scope=scope(method, path))), 405)

    async def test_challenge_only_preexisting_config_token(self):
        query = urlencode({"hub.mode": "subscribe", "hub.verify_token": CONFIG.verify_token,
                           "hub.challenge": "synthetic_challenge"}).encode()
        req = scope("GET", query_string=query)
        result = await self.call(request_scope=req, events=[request()])
        self.assertEqual((self.status(result), result[1]["body"]), (200, b"synthetic_challenge"))
        for app in (ASGIApp(), ASGIApp(replace(CONFIG, verify_enabled=False))):
            self.assertEqual(self.status(await self.call(app=app, request_scope=req, events=[request()])), 503)
        req["query_string"] = query.replace(CONFIG.verify_token.encode(), b"synthetic-wrong-token-" * 2)
        self.assertEqual(self.status(await self.call(request_scope=req, events=[request()])), 403)

    async def test_query_duplicate_names_preserved_including_encoded_alias(self):
        query = urlencode({"hub.mode": "subscribe", "hub.verify_token": CONFIG.verify_token,
                           "hub.challenge": "ok"}).encode() + b"&hub.%63hallenge=other"
        with patch("asgi_app.ingress.handle", wraps=asgi_app.ingress.handle) as handle:
            result = await self.call(request_scope=scope("GET", query_string=query), events=[request()])
        self.assertEqual(self.status(result), 403)
        self.assertEqual(handle.call_args.args[2][-2:], [("hub.challenge", "ok"), ("hub.challenge", "other")])
        self.assertEqual(self.status(await self.call(request_scope=scope(query_string=b"x=1&x=2"))), 403)

    async def test_header_duplicates_preserved_for_ingress(self):
        req = scope()
        req["headers"].append((b"X-Hub-Signature-256", sign(wire(payload())).encode()))
        with patch("asgi_app.ingress.handle", wraps=asgi_app.ingress.handle) as handle:
            result = await self.call(request_scope=req)
        self.assertEqual(self.status(result), 403)
        self.assertEqual([k for k, _ in handle.call_args.args[3]].count("x-hub-signature-256"), 2)

    async def test_strict_ascii_headers_path_and_query(self):
        changes = [dict(path="/webh\u00f3ok"), dict(path="/webhook\n"), dict(raw_path=b"/%77ebhook"),
                   dict(raw_path="/webhook"), dict(method="POST\n"), dict(query_string=b"x=%FF"),
                   dict(query_string=b"x=%GG"), dict(query_string=b"x=\xff"),
                   dict(query_string=b"x"), dict(query_string=b"x=" + b"a" * 8192),
                   dict(query_string=b"&".join([b"x=1"] * 101)),
                   dict(headers=[(b"x", b"\xff")]), dict(headers=[(b"bad name", b"x")]),
                   dict(headers=[(b"x", b"a\r\nb")]), dict(headers=[("x", b"y")]),
                   dict(headers=[(b"x",)]), dict(headers=[(b"x", b"y")] * 101)]
        for change in changes:
            with self.subTest(change=change):
                self.assertEqual(self.status(await self.call(request_scope=scope(**change))), 400)

    async def test_actual_multifragment_multievent_signed_body(self):
        body = wire(payload([event("one"), event("two"), event("staff", echo=True)]))
        chunks = [request(body[:5], True), request(b"", True), request(body[5:101], True), request(body[101:])]
        with patch("asgi_app.ingress.handle", wraps=asgi_app.ingress.handle) as handle:
            result = await self.call(request_scope=scope(body=body), events=chunks)
        self.assertEqual(self.status(result), 200)
        self.assertEqual(handle.call_args.args[4], body)
        state = self.state()
        self.assertEqual(len(state["events"]), 3)
        self.assertEqual(len(state["pauses"]), 1)
        self.assertTrue(state["killed"])
        for secret in (b"Synthetic private question", PAGE.encode(), PEER.encode()):
            self.assertNotIn(secret, self.path.read_bytes())

    async def test_health_headers_also_require_ascii(self):
        for path in ("/healthz", "/readyz"):
            req = scope("GET", path, headers=[(b"x", b"\xff")])
            self.assertEqual(self.status(await self.call(request_scope=req)), 400)

    async def test_duplicate_content_type_and_bad_encoding_cannot_commit(self):
        for headers, status in (([(b"content-type", b"application/json")], 403),
                                ([(b"content-encoding", b"gzip")], 415)):
            req = scope()
            req["headers"].extend(headers)
            with patch.object(self.store, "accept") as accept:
                self.assertEqual(self.status(await self.call(request_scope=req)), status)
                accept.assert_not_called()

    async def test_bad_signature_does_not_decode_body(self):
        body = b"\xff invalid json"
        req = scope(body=body)
        req["headers"][1] = (b"x-hub-signature-256", b"sha256=" + b"0" * 64)
        with patch("ingress.json.loads", side_effect=AssertionError("no decoding")) as parse:
            self.assertEqual(self.status(await self.call(request_scope=req, events=[request(body)])), 403)
            parse.assert_not_called()

    async def test_size_limit_inclusive_and_fragmented_overflow(self):
        base = wire(payload([]))
        body = base + b" " * (MAX_BODY_BYTES - len(base))
        self.assertEqual(self.status(await self.call(request_scope=scope(body=body), events=[request(body)])), 200)
        with patch.object(self.store, "accept") as accept:
            self.assertEqual(self.status(await self.call(events=[request(body, True), request(b" ")])), 413)
            self.assertEqual(self.status(await self.call(events=[request(b"x" * (MAX_BODY_BYTES + 1))])), 413)
            accept.assert_not_called()

    async def test_content_length_framing_and_trailing_fragment(self):
        body = wire(payload())
        req = scope()
        req["headers"].append((b"content-length", str(len(body)).encode()))
        self.assertEqual(self.status(await self.call(request_scope=req)), 200)
        cases = [(b"-1", 413), (b"", 413), (b"9" * 11, 413), (b"65537", 413),
                 (b"1,1", 413), (b"0", 413), (str(len(body) + 1).encode(), 413)]
        for length, status in cases:
            changed = scope()
            changed["headers"].append((b"content-length", length))
            self.assertEqual(self.status(await self.call(request_scope=changed)), status)
        self.assertEqual(self.status(await self.call(request_scope=req,
                         events=[request(body, True), request(b"trailing")])), 413)
        req["headers"].append((b"Content-Length", str(len(body)).encode()))
        self.assertEqual(self.status(await self.call(request_scope=req)), 403)
        req = scope()
        req["headers"].append((b"transfer-encoding", b"chunked"))
        self.assertEqual(self.status(await self.call(request_scope=req)), 400)

    async def test_signed_json_trailing_bytes_rejected(self):
        body = wire(payload()) + b"{}"
        self.assertEqual(self.status(await self.call(request_scope=scope(body=body), events=[request(body)])), 400)

    async def test_total_real_two_second_deadline_not_per_fragment(self):
        started = asyncio.get_running_loop().time()
        reads = 0

        async def slow():
            nonlocal reads
            reads += 1
            await asyncio.sleep(0.8)
            return request(b" ", True)

        with patch.object(self.store, "accept") as accept:
            result = await self.call(receive=slow)
            accept.assert_not_called()
        elapsed = asyncio.get_running_loop().time() - started
        self.assertEqual(self.status(result), 408)
        self.assertEqual(reads, 3)
        self.assertGreaterEqual(elapsed, 1.9)
        self.assertLess(elapsed, 2.7)

    async def test_complete_length_but_missing_terminal_fragment_times_out(self):
        body = wire(payload())
        req = scope()
        req["headers"].append((b"content-length", str(len(body)).encode()))
        with patch("asgi_app.READ_SECONDS", 0.03), patch.object(self.store, "accept") as accept:
            self.assertEqual(self.status(await self.call(request_scope=req, events=[request(body, True)])), 408)
            accept.assert_not_called()

    async def test_disconnect_never_sends_or_commits(self):
        for events in ([{"type": "http.disconnect"}],
                       [request(b"{", True), {"type": "http.disconnect"}]):
            with patch.object(self.store, "accept") as accept:
                self.assertEqual(await self.call(events=events), [])
                accept.assert_not_called()

    async def test_malformed_asgi_events_and_fragment_count_bounded(self):
        for value in (None, {}, {"type": "websocket.receive"}, request("{}"), request(bytearray()),
                      request(b"", 1)):
            self.assertEqual(self.status(await self.call(events=[value])), 400)
        self.assertEqual(self.status(await self.call(events=[request(b"", True)] * 1024)), 400)

    async def test_get_body_rejected(self):
        self.assertEqual(self.status(await self.call(request_scope=scope("GET"), events=[request(b"x")])), 400)

    async def test_no_response_until_real_commit_visible(self):
        committed = []
        real_accept = self.store.accept

        def commit(events, now):
            result = real_accept(events, now)
            self.assertEqual(len(self.state()["events"]), 1)
            committed.append(True)
            return result

        def on_send(_message):
            self.assertEqual(committed, [True])
            self.assertEqual(len(self.state()["events"]), 1)

        with patch.object(self.store, "accept", side_effect=commit):
            self.assertEqual(self.status(await self.call(on_send=on_send)), 200)

    async def test_actual_commit_failure_rollback_no_ack_and_successful_retry(self):
        connect = sqlite3.connect
        before = self.state()

        def broken_connect(*args, **kwargs):
            db = connect(*args, **kwargs)
            proxy = Mock(wraps=db)

            def execute(sql, *params):
                if sql == "COMMIT":
                    raise sqlite3.OperationalError("SYNTHETIC_PRIVATE_COMMIT_ERROR")
                return db.execute(sql, *params)

            proxy.execute.side_effect = execute
            return proxy

        with patch("metadata_store.sqlite3.connect", side_effect=broken_connect):
            result = await self.call()
        self.assertEqual(self.status(result), 503)
        self.assertNotEqual(result[1]["body"], b"EVENT_RECEIVED")
        self.assertEqual(self.state(), before)
        self.assertEqual(self.status(await self.call()), 200)

    async def test_empty_batch_still_requires_commit(self):
        body = wire(payload([]))
        with patch.object(self.store, "accept", side_effect=RuntimeError("private")) as accept:
            result = await self.call(request_scope=scope(body=body), events=[request(body)])
        accept.assert_called_once_with([], NOW)
        self.assertEqual(self.status(result), 503)

    async def test_sanitized_errors_no_output_or_request_data(self):
        out, err = io.StringIO(), io.StringIO()

        async def broken():
            raise RuntimeError(CONFIG.app_secret)

        with redirect_stdout(out), redirect_stderr(err):
            result = await self.call(receive=broken)
        self.assertEqual((self.status(result), result[1]["body"]), (503, b"Temporarily unavailable"))
        self.assertEqual(out.getvalue() + err.getvalue(), "")

    async def test_cancel_propagates_without_ack(self):
        async def cancelled():
            raise asyncio.CancelledError()

        with patch.object(self.store, "accept") as accept:
            with self.assertRaises(asyncio.CancelledError):
                await self.call(receive=cancelled)
            accept.assert_not_called()

    async def test_disconnected_send_is_not_retried(self):
        sends = []

        async def receive():
            return request(wire(payload()))

        async def send(message):
            sends.append(message)
            raise OSError("SYNTHETIC_PRIVATE_DISCONNECT")

        await self.app(scope(), receive, send)
        self.assertEqual(len(sends), 1)
        self.assertEqual(sends[0]["type"], "http.response.start")

    async def test_no_socket_no_inventory_no_model_sender_or_queue(self):
        with patch("socket.socket", side_effect=AssertionError("no sockets")) as sockets:
            self.assertEqual(self.status(await self.call()), 200)
            sockets.assert_not_called()

    async def test_lifespan_has_no_resources_and_websocket_closed(self):
        result = await self.call(request_scope={"type": "lifespan"}, events=[
            {"type": "lifespan.startup"}, {"type": "lifespan.shutdown"}])
        self.assertEqual(result, [{"type": "lifespan.startup.complete"},
                                  {"type": "lifespan.shutdown.complete"}])
        self.assertEqual(await self.call(request_scope={"type": "websocket"}),
                         [{"type": "websocket.close", "code": 1008}])


if __name__ == "__main__":
    unittest.main()
