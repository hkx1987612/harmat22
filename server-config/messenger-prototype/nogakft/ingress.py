"""Receive-only transport boundary; no listener, sender, model or live integration.

The trusted host must read at most 65,536 raw bytes under a total read deadline
(the JS reference uses two seconds), before calling handle. Do not decompress,
decode or reserialize the signed body. Pass decoded query-name/value pairs and
header pairs without collapsing duplicates. This module performs no reads.
Use one existing MetadataStore per configured Page/identity-key namespace;
paths and now_ms are trusted host inputs, never webhook-supplied controls.
Only the bounded verification challenge is reflected; metadata is never returned.
Malformed message candidates reject the whole batch; non-message notifications
and valid foreign-Page entries are ignored. This is stricter than the JS filter.
"""

from dataclasses import dataclass, field
import hashlib
import hmac
import json
import math
import re

from metadata_store import MetadataStore, integer, unique_object

MAX_BODY_BYTES = 65_536
MAX_ENTRIES = 20
MAX_EVENTS = 50
_ID = re.compile(r"[0-9]{5,32}", re.ASCII)
_MID = re.compile(r"[A-Za-z0-9_:.=\-]{1,256}", re.ASCII)
_SIGNATURE = re.compile(r"sha256=[0-9a-f]{64}", re.ASCII)
_CONTENT_TYPE = re.compile(r"application/json(?:\s*;\s*charset=utf-8)?", re.ASCII | re.I)
_CHALLENGE = re.compile(r"[A-Za-z0-9_-]{1,128}", re.ASCII)
_HEADERS = (("content-type", "text/plain; charset=utf-8"),
            ("cache-control", "no-store"), ("x-content-type-options", "nosniff"))


def _matches(pattern, value):
    return type(value) is str and pattern.fullmatch(value) is not None


def _secret(value, minimum):
    if type(value) is not str or not minimum <= len(value) <= 256:
        return False
    try:
        value.encode("utf-8", errors="strict")
    except UnicodeError:
        return False
    return True


@dataclass(frozen=True, slots=True)
class Config:
    receive_enabled: bool = False
    data_processing_approved: bool = False
    verify_enabled: bool = False
    page_id: str = field(default="", repr=False)
    app_secret: str = field(default="", repr=False)
    identity_key: str = field(default="", repr=False)
    verify_token: str = field(default="", repr=False)

    def __post_init__(self):
        if not _config_valid(self):
            raise ValueError("Invalid configuration")


def _config_valid(config):
    if type(config) is not Config:
        return False
    if any(not hasattr(config, name) for name in Config.__dataclass_fields__):
        return False
    if any(type(flag) is not bool for flag in
           (config.receive_enabled, config.data_processing_approved, config.verify_enabled)):
        return False
    if type(config.page_id) is not str or (config.page_id and not _matches(_ID, config.page_id)):
        return False
    for value, minimum in ((config.app_secret, 16), (config.identity_key, 32),
                           (config.verify_token, 32)):
        if type(value) is not str or (value and not _secret(value, minimum)):
            return False
    return True


@dataclass(frozen=True, slots=True)
class Response:
    status: int
    body: bytes
    headers: tuple = _HEADERS


def _reply(status, text):
    return Response(status, text.encode("ascii"))


def _pairs(values, *, headers=False):
    # Lists/tuples preserve duplicate query names and duplicate auth headers.
    if type(values) not in (list, tuple) or len(values) > 100:
        raise ValueError()
    result = {}
    for pair in values:
        if type(pair) not in (list, tuple) or len(pair) != 2:
            raise ValueError()
        name, value = pair
        if type(name) is not str or type(value) is not str:
            raise ValueError()
        if not name or len(name) > 256 or len(value) > 4096:
            raise ValueError()
        if headers:
            if not re.fullmatch(r"[!#$%&'*+.^_`|~0-9A-Za-z-]+", name, re.ASCII):
                raise ValueError()
            if any(ord(c) < 32 or ord(c) > 126 for c in value):
                raise ValueError()
            name = name.lower()
        if name in result:
            raise ValueError()
        result[name] = value
    return result


def verify_signature(app_secret, body, signature):
    if (not _secret(app_secret, 16) or type(body) is not bytes
            or len(body) > MAX_BODY_BYTES or not _matches(_SIGNATURE, signature)):
        return False
    expected = hmac.digest(app_secret.encode("utf-8"), body, "sha256")
    return hmac.compare_digest(expected, bytes.fromhex(signature[7:]))


def _pseudonym(identity_key, scope, *parts):
    # Compact JSON arrays exactly match JSON.stringify for validated ASCII IDs/mids.
    wire = json.dumps([scope, *parts], separators=(",", ":")).encode("utf-8")
    return hmac.new(identity_key.encode("utf-8"), wire, hashlib.sha256).hexdigest()


def _metadata_events(body, config):
    if (type(body) is not dict or body.get("object") != "page"
            or type(body.get("entry")) is not list or len(body["entry"]) > MAX_ENTRIES):
        raise ValueError()
    count, events = 0, []
    for entry in body["entry"]:
        if type(entry) is not dict or not _matches(_ID, entry.get("id")):
            raise ValueError()
        if "messaging" not in entry:
            continue
        batch = entry["messaging"]
        if type(batch) is not list:
            raise ValueError()
        count += len(batch)
        if count > MAX_EVENTS:
            raise ValueError()
        if entry["id"] != config.page_id:
            continue
        for event in batch:
            if type(event) is not dict:
                raise ValueError()
            if "message" not in event:
                continue
            message = event["message"]
            if (type(message) is not dict or not _matches(_MID, message.get("mid"))
                    or not integer(event.get("timestamp"))
                    or ("is_echo" in message and type(message["is_echo"]) is not bool)):
                raise ValueError()
            sender, recipient = event.get("sender"), event.get("recipient")
            if (type(sender) is not dict or type(recipient) is not dict
                    or not _matches(_ID, sender.get("id"))
                    or not _matches(_ID, recipient.get("id"))):
                raise ValueError()
            echo = message.get("is_echo", False)
            if echo:
                if sender["id"] != config.page_id or recipient["id"] == config.page_id:
                    raise ValueError()
                peer = recipient["id"]
            else:
                if recipient["id"] != config.page_id or sender["id"] == config.page_id:
                    raise ValueError()
                peer = sender["id"]
            events.append(dict(
                type="staff" if echo else "message", timestamp=event["timestamp"],
                eventKey=_pseudonym(config.identity_key, "event-v1", config.page_id, message["mid"]),
                threadKey=_pseudonym(config.identity_key, "thread-v1", config.page_id, peer)))
    return sorted(events, key=lambda e: (e["type"] != "staff", e["timestamp"]))


def _reject_constant(_value):
    raise ValueError()


def _finite_float(value):
    result = float(value)
    if not math.isfinite(result):
        raise ValueError()
    return result


def handle(method, path, query_pairs, headers, body, config=None, store=None, now_ms=None):
    """Return a fixed Response; successful receipt means commit, never send permission.

    Config is exact-typed/frozen, with actual bool gates (not environment strings).
    Query/header inputs must be bounded lists/tuples of string pairs; the host
    must reject duplicates before this boundary if its framework collapses them.
    Already-bounded raw body must have exact type bytes. No state is auto-created.
    """
    if type(method) is not str or type(path) is not str:
        return _reply(400, "Request rejected")
    if path == "/healthz" and method == "GET":
        return _reply(200, "receive-only staging; sending=false; model=false; production-ready=false")
    if path != "/webhook":
        return _reply(404, "Not found")
    if method not in ("GET", "POST"):
        return _reply(405, "Method not allowed")
    if not _config_valid(config):
        return _reply(503, "Receiver disabled")
    if method == "GET":
        if not config.verify_enabled or not _secret(config.verify_token, 32):
            return _reply(503, "Verification disabled")
        try:
            query = _pairs(query_pairs)
            if (set(query) != {"hub.mode", "hub.verify_token", "hub.challenge"}
                    or query["hub.mode"] != "subscribe"
                    or not _matches(_CHALLENGE, query["hub.challenge"])
                    or not _secret(query["hub.verify_token"], 32)):
                raise ValueError()
            expected = hashlib.sha256(config.verify_token.encode("utf-8")).digest()
            supplied = hashlib.sha256(query["hub.verify_token"].encode("utf-8")).digest()
            if not hmac.compare_digest(expected, supplied):
                raise ValueError()
            if any(value and value in query["hub.challenge"] for value in
                   (config.page_id, config.app_secret, config.identity_key, config.verify_token)):
                raise ValueError()
        except (ValueError, UnicodeError):
            return _reply(403, "Forbidden")
        return _reply(200, query["hub.challenge"])
    if (not config.receive_enabled or not config.data_processing_approved
            or not _matches(_ID, config.page_id) or not _secret(config.app_secret, 16)
            or not _secret(config.identity_key, 32) or type(store) is not MetadataStore
            or not integer(now_ms)):
        return _reply(503, "Receiver disabled")
    try:
        if _pairs(query_pairs):
            raise ValueError()
        request_headers = _pairs(headers, headers=True)
    except ValueError:
        return _reply(403, "Forbidden")
    if (not _matches(_CONTENT_TYPE, request_headers.get("content-type"))
            or request_headers.get("content-encoding", "") not in ("", "identity")):
        return _reply(415, "Unsupported content type")
    if type(body) is not bytes:
        return _reply(400, "Body rejected")
    if len(body) > MAX_BODY_BYTES:
        return _reply(413, "Body rejected")
    length = request_headers.get("content-length")
    if length is not None and (not re.fullmatch(r"[0-9]{1,10}", length, re.ASCII)
                               or int(length) != len(body)):
        return _reply(413, "Body rejected")
    if "transfer-encoding" in request_headers:
        return _reply(400, "Request rejected")
    if not verify_signature(config.app_secret, body, request_headers.get("x-hub-signature-256")):
        return _reply(403, "Forbidden")
    try:
        payload = json.loads(body.decode("utf-8", errors="strict"),
                             object_pairs_hook=unique_object, parse_constant=_reject_constant,
                             parse_float=_finite_float)
        events = _metadata_events(payload, config)
    except (ValueError, TypeError, RecursionError, OverflowError):
        return _reply(400, "Payload rejected")
    try:
        # accept returns only after its atomic FULL-sync commit, even for [].
        store.accept(events, now_ms)
    except Exception:
        return _reply(503, "Temporarily unavailable")
    return _reply(200, "EVENT_RECEIVED")
