"""Explicit loopback HTTP fixtures, never a Meta test or production bootstrap."""

import argparse
from contextlib import closing, contextmanager
import hashlib
import hmac
import http.client
import json
from pathlib import Path
import socket
import sqlite3
import sys
import tempfile
import threading
import time
from urllib.parse import urlencode

RUNTIME = Path(__file__).resolve().parents[1] / "messenger-prototype/nogakft"
sys.path.insert(0, str(RUNTIME))
from asgi_app import ASGIApp
from ingress import Config
from metadata_store import MetadataStore

PAGE, PEER = "100000000000001", "100000000000002"
CONFIG = Config(receive_enabled=True, data_processing_approved=True, verify_enabled=True,
                page_id=PAGE, app_secret="synthetic-http-fixture-not-a-real-secret",
                identity_key="synthetic-http-identity-key-not-for-production",
                verify_token="synthetic-http-verification-token-not-for-production")


@contextmanager
def server(app):
    import uvicorn
    if uvicorn.__version__ != "0.54.0":
        raise RuntimeError("Use the reviewed isolated uvicorn 0.54.0 test environment")
    sock = socket.socket()
    sock.bind(("127.0.0.1", 0))
    sock.listen(8)
    port = sock.getsockname()[1]
    engine = uvicorn.Server(uvicorn.Config(app, host="127.0.0.1", port=port,
        http="h11", ws="none", lifespan="off", proxy_headers=False,
        log_config=None, log_level="critical", access_log=False,
        timeout_keep_alive=1, timeout_graceful_shutdown=3, limit_concurrency=8))
    errors = []

    def run():
        try:
            engine.run(sockets=[sock])
        except BaseException as error:
            errors.append(type(error).__name__)

    thread = threading.Thread(target=run, name="synthetic-loopback-http", daemon=False)
    thread.start()
    try:
        deadline = time.monotonic() + 5
        while not engine.started and thread.is_alive() and time.monotonic() < deadline:
            time.sleep(0.01)
        if not engine.started or errors:
            raise RuntimeError("Isolated HTTP engine did not start")
        yield port
    finally:
        engine.should_exit = True
        thread.join(5)
        sock.close()
        if thread.is_alive() or errors:
            raise RuntimeError("Isolated HTTP engine did not stop cleanly")


def request(port, method="GET", path="/healthz", body=b"", headers=()):
    with closing(http.client.HTTPConnection("127.0.0.1", port, timeout=4)) as client:
        client.putrequest(method, path, skip_accept_encoding=True)
        for name, value in headers:
            client.putheader(name, value)
        if not any(k.lower() == "content-length" for k, _ in headers):
            client.putheader("Content-Length", str(len(body)))
        client.endheaders(body)
        response = client.getresponse()
        return response.status, response.read()


def message(mid, *, echo=False):
    item = {"mid": mid, "text": "Synthetic fixture question; no real customer data"}
    if echo:
        item["is_echo"] = True
    value = {"object": "page", "entry": [{"id": PAGE, "messaging": [{
        "sender": {"id": PAGE if echo else PEER},
        "recipient": {"id": PEER if echo else PAGE},
        "timestamp": time.time_ns() // 1_000_000, "message": item}]}]}
    return json.dumps(value, separators=(",", ":")).encode()


def signed(body):
    signature = hmac.new(CONFIG.app_secret.encode(), body, hashlib.sha256).hexdigest()
    return [("Content-Type", "application/json"), ("X-Hub-Signature-256", "sha256=" + signature)]


def state(path):
    with closing(sqlite3.connect(path)) as db:
        return json.loads(db.execute("SELECT state FROM metadata").fetchone()[0])


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--run-loopback", action="store_true")
    if not parser.parse_args().run_loopback:
        print(json.dumps({"scope": "OFFLINE_ONLY", "listener_started": False}))
        return
    checks = []

    def check(name, condition):
        if not condition:
            raise RuntimeError("Synthetic HTTP check failed: " + name)
        checks.append(name)

    with server(ASGIApp()) as port:
        code, body = request(port)
        check("default_health_not_production_ready", code == 200 and b"production-ready=false" in body)
        check("default_readiness_503", request(port, path="/readyz")[0] == 503)
        check("default_post_disabled", request(port, "POST", "/webhook", b"{}")[0] == 503)
        check("default_verification_disabled", request(port, path="/webhook")[0] == 503)
        check("no_admin_route", request(port, path="/admin")[0] == 404)
    with tempfile.TemporaryDirectory(prefix="harmat-synthetic-http-") as directory:
        path = Path(directory) / "fixture.sqlite"
        store = MetadataStore.create(path)
        app = ASGIApp(CONFIG, store, metadata_only_test=True)
        with server(app) as port:
            query = urlencode({"hub.mode": "subscribe", "hub.verify_token": CONFIG.verify_token,
                               "hub.challenge": "synthetic_challenge"})
            check("synthetic_challenge_http", request(port, path="/webhook?" + query) == (200, b"synthetic_challenge"))
            check("duplicate_query_denied", request(port, path="/webhook?" + query + "&hub.challenge=again")[0] == 403)
            body = message("http-fixture-one")
            check("signed_request_committed", request(port, "POST", "/webhook", body, signed(body))[0] == 200
                  and len(state(path)["events"]) == 1)
            check("duplicate_deduplication", request(port, "POST", "/webhook", body, signed(body))[0] == 200
                  and len(state(path)["events"]) == 1)
            check("killed_remains_true", state(path)["killed"] is True)
            bad = signed(body)
            bad[-1] = (bad[-1][0], "sha256=" + "0" * 64)
            check("bad_signature_rejected", request(port, "POST", "/webhook", body, bad)[0] == 403)
            check("duplicate_signature_rejected", request(port, "POST", "/webhook", body, signed(body) + signed(body)[-1:])[0] == 403)
            malformed = b"{invalid json"
            check("signed_invalid_json_rejected", request(port, "POST", "/webhook", malformed, signed(malformed))[0] == 400)
            check("oversize_header_rejected", request(port, "POST", "/webhook", b"", signed(b"") + [("Content-Length", "65537")])[0] == 413)
            check("framing_errors_do_not_commit", len(state(path)["events"]) == 1)
            staff = message("http-fixture-staff", echo=True)
            check("staff_echo_persisted_pause", request(port, "POST", "/webhook", staff, signed(staff))[0] == 200
                  and len(state(path)["pauses"]) == 1)
            check("test_receiver_not_reply_ready", request(port, path="/readyz")[0] == 503)
            with closing(socket.create_connection(("127.0.0.1", port), timeout=4)) as client:
                client.sendall(b"POST /webhook HTTP/1.1\r\nHost: localhost\r\nContent-Length: 10\r\n\r\n{")
                started = time.monotonic()
                response = client.recv(4096)
                elapsed = time.monotonic() - started
                check("actual_socket_read_deadline", response.startswith(b"HTTP/1.1 408 ") and 1.8 <= elapsed < 3.5)
            check("partial_body_never_committed", len(state(path)["events"]) == 2)
        data = path.read_bytes()
        check("no_plaintext_question_or_ids_in_sqlite", all(x not in data for x in (
            PAGE.encode(), PEER.encode(), b"Synthetic fixture question")))
    print(json.dumps({"scope": "LOCAL_LOOPBACK_SYNTHETIC_HTTP_NOT_META_OR_MODEL", "checks": len(checks),
        "passed": checks, "uvicorn": "0.54.0", "listeners_stopped": True,
        "temporary_databases_removed": True, "website_or_customer_requests": 0,
        "meta_delivery_or_ai_reply_verified": False}, indent=2))


if __name__ == "__main__":
    main()
