"""Synthetic, offline transport fixtures; never sends a message or opens a listener."""

from contextlib import closing, redirect_stderr, redirect_stdout
from dataclasses import FrozenInstanceError, replace
import hashlib
import hmac
import io
import json
from pathlib import Path
import sqlite3
import tempfile
import unittest
from unittest.mock import Mock, patch

from ingress import Config, MAX_BODY_BYTES, handle, verify_signature
from metadata_store import MAX_INT, MetadataStore, WINDOW

PAGE = "100000000000001"
PEER = "200000000000001"
OTHER_PAGE = "300000000000001"
NOW = 1_792_065_600_000
CONFIG = Config(receive_enabled=True, data_processing_approved=True, verify_enabled=True,
                page_id=PAGE, app_secret="synthetic-app-secret-not-live-12345",
                identity_key="synthetic-identity-key-not-live-12345",
                verify_token="synthetic-verification-token-not-live-12345")
# Node createHmac over JSON.stringify arrays, using the JS reference fixtures.
EVENT_KEY = "4e094fb9bc0852d83637910b71eb3ca44405d48d66747860c92befcc100a778a"
THREAD_KEY = "b3c1cc7cd30a9586d14c1282ed86ecbecad636ca960e5773e0305183ecbf0cd7"


def event(mid="synthetic-mid-1", timestamp=NOW, echo=False):
    return dict(sender=dict(id=PAGE if echo else PEER),
                recipient=dict(id=PEER if echo else PAGE), timestamp=timestamp,
                message=dict(mid=mid, is_echo=echo, text="Synthetic private question"))


def payload(events=None, page=PAGE):
    return dict(object="page", entry=[dict(id=page, messaging=[event()] if events is None else events)])


def wire(value):
    return json.dumps(value, separators=(",", ":")).encode("utf-8")


def sign(body, config=CONFIG):
    return "sha256=" + hmac.new(config.app_secret.encode("utf-8"), body, hashlib.sha256).hexdigest()


class IngressTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.path = Path(self.tmp.name) / "synthetic.sqlite"
        self.store = MetadataStore.create(self.path)

    def state(self):
        with closing(sqlite3.connect(self.path)) as db:
            return json.loads(db.execute("SELECT state FROM metadata WHERE id=1").fetchone()[0])

    def receive(self, body=None, *, config=CONFIG, store=None, now=NOW, headers=None,
                signature=None, query=()):
        body = wire(payload()) if body is None else body
        pairs = [("content-type", "application/json"),
                 ("x-hub-signature-256", sign(body) if signature is None else signature)]
        if headers is not None:
            pairs = headers
        return handle("POST", "/webhook", query, pairs, body, config,
                      self.store if store is None else store, now)

    def verify(self, query=..., config=CONFIG):
        if query is ...:
            query = [("hub.mode", "subscribe"), ("hub.verify_token", config.verify_token),
                     ("hub.challenge", "synthetic_challenge")]
        return handle("GET", "/webhook", query, (), b"", config)

    def assert_rejected(self, body, status=400):
        before = self.state()
        with patch.object(self.store, "accept", side_effect=AssertionError("must not store")) as accept:
            response = self.receive(body)
        self.assertEqual(response.status, status)
        accept.assert_not_called()
        self.assertEqual(self.state(), before)
        self.assertNotEqual(response.body, b"EVENT_RECEIVED")

    def test_default_gates_are_all_off(self):
        self.assertEqual(Config(), Config(False, False, False))
        self.assertEqual(self.receive(config=Config()).status, 503)
        self.assertEqual(self.verify(config=Config()).status, 503)
        self.assertEqual(self.receive(config=None).status, 503)
        for name in ("receive_enabled", "data_processing_approved"):
            with self.subTest(name=name), patch.object(self.store, "accept") as accept:
                self.assertEqual(self.receive(config=replace(CONFIG, **{name: False})).status, 503)
                accept.assert_not_called()

    def test_config_is_frozen_and_hides_credentials(self):
        with self.assertRaises(FrozenInstanceError):
            CONFIG.receive_enabled = False
        with self.assertRaises((AttributeError, TypeError)):
            Config(send_enabled=True)
        for value in (PAGE, CONFIG.app_secret, CONFIG.identity_key, CONFIG.verify_token):
            self.assertNotIn(value, repr(CONFIG))

    def test_config_requires_exact_booleans_even_when_disabled(self):
        for field in ("receive_enabled", "data_processing_approved", "verify_enabled"):
            for value in (0, 1, "true", "false", None, [], {}):
                with self.subTest(field=field, value=value), self.assertRaisesRegex(
                        ValueError, "^Invalid configuration$"):
                    replace(CONFIG, **{field: value})

    def test_config_rejects_bad_ids_secrets_types_and_surrogates(self):
        for field, value in (("page_id", "page-name"), ("page_id", 12345),
                             ("page_id", "12345\n"), ("app_secret", "short"),
                             ("identity_key", "x" * 257), ("verify_token", None),
                             ("app_secret", "x" * 20 + "\ud800")):
            with self.subTest(field=field), self.assertRaisesRegex(ValueError, "^Invalid configuration$"):
                replace(CONFIG, **{field: value})

    def test_exact_config_type_and_revalidation_prevent_gate_evasion(self):
        class DerivedConfig(Config):
            pass

        with self.assertRaisesRegex(ValueError, "^Invalid configuration$"):
            DerivedConfig()
        for config in ({**{name: True for name in ("receive_enabled", "data_processing_approved")}},
                       Mock(), object.__new__(DerivedConfig), object.__new__(Config)):
            self.assertEqual(self.receive(config=config).status, 503)
        damaged = replace(CONFIG)
        object.__setattr__(damaged, "receive_enabled", "false")
        self.assertEqual(self.receive(config=damaged).status, 503)

    def test_missing_required_configuration_and_dependency_fail_closed(self):
        for field in ("page_id", "app_secret", "identity_key"):
            self.assertEqual(self.receive(config=replace(CONFIG, **{field: ""})).status, 503)
        for store in (object(), Mock(), lambda _events, _now: None):
            self.assertEqual(self.receive(store=store).status, 503)
        self.assertEqual(handle("POST", "/webhook", (), (), b"", CONFIG, None, NOW).status, 503)
        for now in (None, True, -1, MAX_INT + 1, "123", 1.0):
            self.assertEqual(self.receive(now=now).status, 503)

    def test_health_is_static_and_never_uses_configuration_or_store(self):
        response = handle("GET", "/healthz", None, None, None, Mock(), Mock(), None)
        self.assertEqual(response.status, 200)
        self.assertEqual(response.body,
                         b"receive-only staging; sending=false; model=false; production-ready=false")
        self.assertIn(("cache-control", "no-store"), response.headers)
        self.assertIn(("x-content-type-options", "nosniff"), response.headers)
        self.assertNotIn("access-control-allow-origin", dict(response.headers))

    def test_exact_paths_and_methods_no_control_routes(self):
        for path in ("/send", "/admin", "/resume", "/reserve", "/webhook/", "/WEBHOOK",
                     "/webhook?receive_enabled=true", "/healthz/"):
            self.assertEqual(handle("GET", path, (), (), b"", CONFIG).status, 404)
        for method in ("PUT", "DELETE", "OPTIONS", "HEAD", "post"):
            self.assertEqual(handle(method, "/webhook", (), (), b"", CONFIG).status, 405)
        self.assertEqual(handle(None, "/webhook", (), (), b"").status, 400)

    def test_verification_only_returns_bounded_challenge(self):
        response = self.verify()
        self.assertEqual((response.status, response.body), (200, b"synthetic_challenge"))
        for value in (PAGE, CONFIG.verify_token, CONFIG.app_secret):
            self.assertNotIn(value.encode(), response.body)
        self.assertEqual(self.verify(config=replace(CONFIG, verify_enabled=False)).status, 503)
        self.assertEqual(self.verify(config=replace(CONFIG, verify_token="")).status, 503)

    def test_verification_rejects_duplicates_unknowns_missing_and_wrong_auth(self):
        valid = [("hub.mode", "subscribe"), ("hub.verify_token", CONFIG.verify_token),
                 ("hub.challenge", "synthetic_challenge")]
        invalid = [valid + [pair] for pair in valid]
        invalid += [valid[:-1], valid + [("receive_enabled", "true")], dict(valid), None,
                    valid + [("other", "1"), ("other", "2")]]
        for index, value in ((0, "unsubscribe"), (1, "wrong" * 10), (2, "<script>"),
                             (2, "x" * 129), (2, "123\n"), (2, ""), (2, "\ud800")):
            changed = valid.copy()
            changed[index] = (changed[index][0], value)
            invalid.append(changed)
        for query in invalid:
            with self.subTest(query=query):
                response = self.verify(query)
                self.assertEqual(response.status, 403)
                self.assertEqual(response.body, b"Forbidden")

    def test_challenge_cannot_reflect_configured_ids_or_secrets(self):
        for value in (PAGE, CONFIG.app_secret, CONFIG.identity_key, CONFIG.verify_token):
            query = [("hub.mode", "subscribe"), ("hub.verify_token", CONFIG.verify_token),
                     ("hub.challenge", "prefix_" + value)]
            response = self.verify(query)
            self.assertEqual((response.status, response.body), (403, b"Forbidden"))

    def test_raw_signature_precedes_utf8_and_json(self):
        for body in (b"not json", b"\xff", wire(payload())):
            with patch("ingress.json.loads", side_effect=AssertionError("must not parse")) as parse:
                self.assertEqual(self.receive(body, signature="sha256=" + "0" * 64).status, 403)
                parse.assert_not_called()

    def test_signature_wire_escaping_and_exact_header_format(self):
        escaped = b'{"question":"\\u00e1r"}'
        decoded = json.dumps(json.loads(escaped), ensure_ascii=False).encode("utf-8")
        self.assertTrue(verify_signature(CONFIG.app_secret, escaped, sign(escaped)))
        self.assertFalse(verify_signature(CONFIG.app_secret, decoded, sign(escaped)))
        for signature in (None, "", "sha1=" + "a" * 64, "sha256=" + "A" * 64,
                          sign(escaped) + ",extra", sign(escaped) + "\n"):
            self.assertFalse(verify_signature(CONFIG.app_secret, escaped, signature))
        self.assertFalse(verify_signature("short", escaped, sign(escaped)))

    def test_bad_body_types_are_not_coerced(self):
        for body in (None, "{}", bytearray(b"{}"), memoryview(b"{}"), {}):
            response = handle("POST", "/webhook", (), [("content-type", "application/json")],
                              body, CONFIG, self.store, NOW)
            self.assertEqual(response.status, 400)

    def test_size_limit_inclusive_and_empty_body_rejected(self):
        base = wire(dict(object="page", entry=[]))
        body = base + b" " * (MAX_BODY_BYTES - len(base))
        self.assertEqual(self.receive(body).status, 200)
        self.assert_rejected(body + b" ", 413)
        self.assert_rejected(b"")

    def test_content_length_is_bounded_numeric_and_matches_actual_bytes(self):
        body = wire(payload())
        headers = [("content-type", "application/json"), ("x-hub-signature-256", sign(body))]
        self.assertEqual(self.receive(body, headers=headers + [("content-length", str(len(body)))]).status, 200)
        for value in ("65537", "-1", "x", "0", "1.0", "1,1", "9" * 100, ""):
            self.assertEqual(self.receive(body, headers=headers + [("content-length", value)]).status, 413)

    def test_only_identity_encoded_json_is_accepted(self):
        body = wire(payload())
        for content in ("text/plain", "application/json; charset=latin-1", "application/json; x=1", ""):
            pairs = [("content-type", content), ("x-hub-signature-256", sign(body))]
            self.assertEqual(self.receive(body, headers=pairs).status, 415)
        for encoding in ("gzip", "br", "Identity", "identity,gzip"):
            pairs = [("content-type", "application/json"), ("content-encoding", encoding)]
            self.assertEqual(self.receive(body, headers=pairs).status, 415)
        pairs = [("Content-Type", "APPLICATION/JSON; charset=UTF-8"),
                 ("X-Hub-Signature-256", sign(body)), ("Content-Encoding", "identity")]
        self.assertEqual(self.receive(body, headers=pairs).status, 200)

    def test_duplicate_auth_or_framing_headers_are_rejected(self):
        body = wire(payload())
        base = [("content-type", "application/json"), ("x-hub-signature-256", sign(body))]
        for pair in (("X-Hub-Signature-256", sign(body)), ("Content-Type", "application/json")):
            self.assertEqual(self.receive(body, headers=base + [pair]).status, 403)
        self.assertEqual(self.receive(body, headers=base + [("Content-Length", str(len(body))),
                                                          ("content-length", str(len(body)))]).status, 403)
        self.assertEqual(self.receive(body, headers=base + [("Transfer-Encoding", "chunked")]).status, 400)

    def test_bounded_exact_pairs_and_post_query_rejection(self):
        for headers in (None, {}, [("bad\nname", "x")], [("content-type", "x\r\ny")],
                        [("x", "y" * 4097)], [("x", None)], [("x",)], [("x", "y")] * 101):
            self.assertEqual(handle("POST", "/webhook", (), headers, b"{}", CONFIG,
                                    self.store, NOW).status, 403)
        for query in ([("receive_enabled", "true")], [("x", "1"), ("x", "2")], {}, None):
            self.assertEqual(self.receive(query=query).status, 403)

    def test_signed_invalid_utf8_and_bom_rejected(self):
        for body in (b'"\xc3("', b"\xff", b"\xef\xbb\xbf" + wire(payload())):
            self.assert_rejected(body)

    def test_duplicate_json_keys_rejected_at_every_depth(self):
        good = wire(payload())
        for body in (b'{"object":"page","object":"page","entry":[]}',
                     good.replace(b'"id":"' + PAGE.encode() + b'"',
                                  b'"id":"' + PAGE.encode() + b'","id":"' + PAGE.encode() + b'"', 1),
                     good.replace(b'"mid":"synthetic-mid-1"',
                                  b'"mid":"synthetic-mid-1","\\u006did":"other"'),
                     good.replace(b'"is_echo":false', b'"is_echo":true,"is_echo":false')):
            self.assert_rejected(body)

    def test_nonstandard_json_numbers_and_deep_json_rejected(self):
        for value in (b"NaN", b"Infinity", b"-Infinity", b"1e999"):
            self.assert_rejected(b'{"object":"page","entry":[],"ignored":' + value + b"}")
        self.assert_rejected(b"[" * 2000 + b"0" + b"]" * 2000)

    def test_invalid_envelopes_and_batch_types_rejected(self):
        for value in (None, {}, [], dict(object="instagram", entry=[]),
                      dict(object="page", entry={}), dict(object="page", entry=[None]),
                      dict(object="page", entry=[dict(id=PAGE, messaging={})])):
            self.assert_rejected(wire(value))

    def test_entry_limit_inclusive(self):
        self.assertEqual(self.receive(wire(dict(object="page", entry=[dict(id=PAGE)] * 20))).status, 200)
        self.assert_rejected(wire(dict(object="page", entry=[dict(id=PAGE)] * 21)))

    def test_event_limit_inclusive_and_total_across_entries_even_foreign(self):
        self.assertEqual(self.receive(wire(payload([event(str(i)) for i in range(50)]))).status, 200)
        self.assertEqual(len(self.state()["events"]), 50)
        self.assert_rejected(wire(payload([event()] * 51)))
        entries = [dict(id=PAGE, messaging=[event()] * 25),
                   dict(id=OTHER_PAGE, messaging=[event()] * 26)]
        self.assert_rejected(wire(dict(object="page", entry=entries)))

    def test_exact_page_only_and_public_standby_notifications_ignored(self):
        values = [payload(page=OTHER_PAGE), payload([dict(read={}), dict(delivery={})]),
                  dict(object="page", entry=[dict(id=PAGE, changes=[dict(text="private")],
                                                  standby=[event()])])]
        for value in values:
            self.assertEqual(self.receive(wire(value)).status, 200)
            self.assertEqual(self.state()["events"], {})

    def test_invalid_entry_and_message_ids_rejected(self):
        for value in ("abcde", "1234", "1" * 33, 12345, True, "12345\n", "１２３４５", None):
            self.assert_rejected(wire(payload(page=value)))
            for field in ("sender", "recipient"):
                candidate = event()
                candidate[field]["id"] = value
                self.assert_rejected(wire(payload([candidate])))
        for value in ("", "x" * 257, "mid\n", "a/b", 123, None):
            candidate = event()
            candidate["message"]["mid"] = value
            self.assert_rejected(wire(payload([candidate])))

    def test_safe_integer_timestamps_and_strict_echo_booleans(self):
        for timestamp in (True, False, -1, MAX_INT + 1, str(NOW), float(NOW), None):
            self.assert_rejected(wire(payload([event(timestamp=timestamp)])))
        for echo in (0, 1, "false", "true", None, []):
            candidate = event()
            candidate["message"]["is_echo"] = echo
            self.assert_rejected(wire(payload([candidate])))
        for timestamp in (0, MAX_INT):
            self.assertEqual(self.receive(wire(payload([event(timestamp=timestamp)]))).status, 200)

    def test_malformed_message_and_reversed_identities_reject_entire_batch(self):
        candidates = [None, [], dict(message=None), dict(message={})]
        for field, value in (("sender", None), ("recipient", []), ("sender", dict(id=PAGE)),
                             ("recipient", dict(id=PEER))):
            candidate = event()
            candidate[field] = value
            candidates.append(candidate)
        candidate = event(echo=True)
        candidate["sender"]["id"] = PEER
        candidates.append(candidate)
        for candidate in candidates:
            self.assert_rejected(wire(payload([event(echo=True), candidate])))

    def test_js_compatible_pseudonyms_and_metadata_only_persistence(self):
        candidate = event()
        candidate["message"].update(text="SYNTHETIC_PRIVATE_TEXT synthetic@example.invalid",
                                    attachments=[dict(payload=dict(url="https://private.invalid/file"))])
        body = wire(payload([candidate]))
        response = self.receive(body)
        self.assertEqual(response.body, b"EVENT_RECEIVED")
        state = self.state()
        self.assertEqual(set(state["events"]), {EVENT_KEY})
        self.assertEqual(state["events"][EVENT_KEY], dict(type="message", threadKey=THREAD_KEY,
                                                       timestamp=NOW, seenAt=NOW))
        with patch.object(self.store, "accept", wraps=self.store.accept) as accept:
            self.receive(body)
        self.assertEqual(set(accept.call_args.args[0][0]), {"type", "timestamp", "eventKey", "threadKey"})
        contents = self.path.read_bytes()
        for value in (PAGE, PEER, "synthetic-mid-1", "SYNTHETIC_PRIVATE_TEXT", "private.invalid",
                      "synthetic@example.invalid", CONFIG.app_secret, CONFIG.identity_key):
            self.assertNotIn(value.encode(), contents)
            self.assertNotIn(value.encode(), response.body)

    def test_page_key_event_and_thread_namespaces(self):
        captures = []
        with patch.object(self.store, "accept", side_effect=lambda batch, _now: captures.append(batch)):
            self.receive()
            self.receive(wire(payload([event(mid="synthetic-mid-2")])))
            self.receive(config=replace(CONFIG, identity_key=CONFIG.identity_key + "2"))
            candidate = event()
            candidate["recipient"]["id"] = OTHER_PAGE
            self.receive(wire(payload([candidate], page=OTHER_PAGE)), config=replace(CONFIG, page_id=OTHER_PAGE))
        one, two, key_changed, page_changed = [batch[0] for batch in captures]
        self.assertNotEqual(one["eventKey"], two["eventKey"])
        self.assertEqual(one["threadKey"], two["threadKey"])
        self.assertNotEqual(one["eventKey"], one["threadKey"])
        for changed in (key_changed, page_changed):
            self.assertNotEqual(one["eventKey"], changed["eventKey"])
            self.assertNotEqual(one["threadKey"], changed["threadKey"])

    def test_killed_default_receipts_do_not_use_budget_or_enable_sender(self):
        self.assertEqual(self.receive().status, 200)
        state = self.state()
        self.assertTrue(state["killed"])
        self.assertEqual(state["page"], 0)
        self.assertEqual(len(state["events"]), 1)
        self.store.set_killed(False)
        self.assertEqual(self.receive().status, 200)
        self.assertEqual(self.state()["page"], 0)

    def test_replay_restart_deduplicates_using_existing_store(self):
        self.store.set_killed(False)
        self.assertEqual(self.receive().status, 200)
        self.store = MetadataStore(self.path)
        self.assertEqual(self.receive().status, 200)
        self.assertEqual(self.state()["page"], 1)
        self.assertEqual(len(self.state()["events"]), 1)

    def test_every_echo_pauses_staff_first_regardless_of_app_metadata(self):
        self.store.set_killed(False)
        for index, source in enumerate(({}, dict(app_id=PAGE), dict(metadata="synthetic-own-bot"))):
            echo = event(mid="echo-" + str(index), timestamp=NOW + 1, echo=True)
            echo["message"].update(source)
            with patch.object(self.store, "accept", wraps=self.store.accept) as accept:
                response = self.receive(wire(payload([event(), echo])))
            self.assertEqual(response.status, 200)
            self.assertEqual([e["type"] for e in accept.call_args.args[0]], ["staff", "message"])
            self.assertTrue(self.state()["pauses"][THREAD_KEY])
            self.assertEqual(self.state()["page"], 0)

    def test_cross_entry_staff_conflicting_mid_wins_before_message(self):
        self.store.set_killed(False)
        incoming = event()
        del incoming["message"]["is_echo"]
        body = dict(object="page", entry=[dict(id=PAGE, messaging=[incoming]),
                                          dict(id=PAGE, messaging=[event(echo=True, timestamp=NOW + 1)])])
        self.assertEqual(self.receive(wire(body)).status, 200)
        state = self.state()
        self.assertTrue(state["pauses"][THREAD_KEY])
        self.assertEqual(state["events"][EVENT_KEY]["type"], "staff")
        self.assertEqual(state["page"], 0)

    def test_stale_future_and_duplicate_echoes_reassert_pause(self):
        self.store.set_killed(False)
        for stamp in (NOW - WINDOW, NOW + 60_001, NOW):
            echo = event(mid="echo", timestamp=stamp, echo=True)
            self.store.set_paused(THREAD_KEY, False)
            self.assertEqual(self.receive(wire(payload([echo]))).status, 200)
            self.assertIn(THREAD_KEY, self.state()["pauses"])
        self.store.set_paused(THREAD_KEY, False)
        self.assertEqual(self.receive(wire(payload([event(mid="echo", echo=True)]))).status, 200)
        self.assertIn(THREAD_KEY, self.state()["pauses"])

    def test_empty_batch_also_requires_dependency_commit_before_ack(self):
        with patch.object(self.store, "accept", wraps=self.store.accept) as accept:
            self.assertEqual(self.receive(wire(payload([]))).status, 200)
            accept.assert_called_once_with([], NOW)
        with patch.object(self.store, "accept", side_effect=RuntimeError("synthetic-private-error")):
            response = self.receive(wire(payload([])))
        self.assertEqual((response.status, response.body), (503, b"Temporarily unavailable"))

    def test_ack_observes_committed_state_and_discards_internal_decisions(self):
        real_accept = self.store.accept
        observed = []

        def commit(events, now):
            result = real_accept(events, now)
            self.assertIn(EVENT_KEY, self.state()["events"])
            observed.append("committed")
            return result

        with patch.object(self.store, "accept", side_effect=commit):
            response = self.receive()
        self.assertEqual(observed, ["committed"])
        self.assertEqual((response.status, response.body), (200, b"EVENT_RECEIVED"))
        self.assertNotIn(b"kill_switch", response.body)

    def test_commit_failure_rolls_back_and_retry_is_not_deduplicated(self):
        self.store.set_killed(False)
        before = self.state()
        connect = sqlite3.connect

        def fail_commit(*args, **kwargs):
            db = connect(*args, **kwargs)
            proxy = Mock(wraps=db)

            def execute(sql, *params):
                if sql == "COMMIT":
                    raise sqlite3.OperationalError("synthetic-private-error")
                return db.execute(sql, *params)

            proxy.execute.side_effect = execute
            return proxy

        with patch("metadata_store.sqlite3.connect", side_effect=fail_commit):
            response = self.receive()
        self.assertEqual((response.status, response.body), (503, b"Temporarily unavailable"))
        self.assertEqual(self.state(), before)
        self.assertEqual(self.receive().status, 200)
        self.assertEqual(self.state()["page"], 1)

    def test_missing_corrupt_or_busy_store_returns_503_without_reset(self):
        missing = Path(self.tmp.name) / "missing.sqlite"
        self.assertEqual(self.receive(store=MetadataStore(missing)).status, 503)
        self.assertFalse(missing.exists())
        with closing(sqlite3.connect(self.path, isolation_level=None)) as db:
            db.execute("BEGIN IMMEDIATE")
            self.assertEqual(self.receive().status, 503)
            db.execute("ROLLBACK")
        self.path.write_bytes(b"synthetic corrupt database")
        before = self.path.read_bytes()
        self.assertEqual(self.receive().status, 503)
        self.assertEqual(self.path.read_bytes(), before)

    def test_dependency_error_never_logs_or_echoes_payload_or_exception(self):
        output, errors = io.StringIO(), io.StringIO()
        with redirect_stdout(output), redirect_stderr(errors), patch.object(
                self.store, "accept", side_effect=RuntimeError(PAGE + " synthetic private error")):
            response = self.receive()
        self.assertEqual(response.status, 503)
        self.assertEqual(response.body, b"Temporarily unavailable")
        self.assertEqual(output.getvalue() + errors.getvalue(), "")

    def test_handle_does_not_open_network_or_listener(self):
        with patch("socket.socket", side_effect=AssertionError("no sockets")) as socket:
            self.assertEqual(self.receive().status, 200)
            self.assertEqual(self.verify().status, 200)
            socket.assert_not_called()


if __name__ == "__main__":
    unittest.main()
