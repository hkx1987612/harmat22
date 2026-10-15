"""Bounded ASGI receive-only staging adapter; no queue, model, sender or listener.

Trusted bootstrap may construct ASGIApp with an existing Config/MetadataStore.
POST additionally requires metadata_only_test=True; this is never reply readiness.
No environment, secret loading, database creation or background ACK is performed.
"""

import asyncio
from dataclasses import dataclass, field
import re
import time
from urllib.parse import parse_qsl

import ingress
from metadata_store import MetadataStore

READ_SECONDS = 2.0
MAX_BODY_EVENTS = 1024
_TOKEN = re.compile(r"[!#$%&'*+.^_`|~0-9A-Za-z-]+", re.ASCII)
_LENGTH = re.compile(r"[0-9]{1,10}", re.ASCII)
_BAD_ESCAPE = re.compile(r"%(?![0-9a-fA-F]{2})", re.ASCII)
_HEALTH = b"receive-only staging; model=false; sender=false; production-ready=false"
_READY = b"Not ready: full queue and sender absent; model=false; production-ready=false"


class _Rejected(Exception):
    def __init__(self, status=400):
        self.status = status


class _Disconnected(Exception):
    pass


def _reply(status, body=b"Request rejected"):
    return ingress.Response(status, body)


def _request(scope):
    method, path = scope.get("method"), scope.get("path")
    if (type(method) is not str or not _TOKEN.fullmatch(method)
            or type(path) is not str or len(path) > 256 or not path.isascii()
            or any(ord(c) < 33 or ord(c) > 126 for c in path)):
        raise _Rejected()
    raw_path = scope.get("raw_path", path.encode("ascii"))
    if type(raw_path) is not bytes or raw_path != path.encode("ascii"):
        raise _Rejected()
    return method, path


def _headers(scope):
    raw = scope.get("headers", [])
    if type(raw) not in (list, tuple) or len(raw) > 100:
        raise _Rejected()
    pairs = []
    for pair in raw:
        if type(pair) not in (list, tuple) or len(pair) != 2:
            raise _Rejected()
        name, value = pair
        if (type(name) is not bytes or type(value) is not bytes
                or not 1 <= len(name) <= 256 or len(value) > 4096):
            raise _Rejected()
        name, value = name.decode("ascii"), value.decode("ascii")
        if not _TOKEN.fullmatch(name) or any(ord(c) < 32 or ord(c) > 126 for c in value):
            raise _Rejected()
        pairs.append((name.lower(), value))
    return pairs


def _query(scope):
    raw = scope.get("query_string", b"")
    if type(raw) is not bytes or len(raw) > 8192:
        raise _Rejected()
    query = raw.decode("ascii")
    if _BAD_ESCAPE.search(query):
        raise _Rejected()
    return parse_qsl(query, keep_blank_values=True, strict_parsing=True,
                     encoding="ascii", errors="strict", max_num_fields=100)


def _length(headers):
    lengths = [v for k, v in headers if k == "content-length"]
    if len(lengths) > 1:
        raise _Rejected(403)
    if any(k == "transfer-encoding" for k, _ in headers):
        raise _Rejected()
    if not lengths:
        return None
    if not _LENGTH.fullmatch(lengths[0]) or int(lengths[0]) > ingress.MAX_BODY_BYTES:
        raise _Rejected(413)
    return int(lengths[0])


async def _body(receive, length, deadline):
    chunks, size = [], 0
    loop = asyncio.get_running_loop()
    for _ in range(MAX_BODY_EVENTS):
        if loop.time() >= deadline:
            raise _Rejected(408)
        event = await receive()
        if loop.time() >= deadline:
            raise _Rejected(408)
        if type(event) is not dict:
            raise _Rejected()
        if event.get("type") == "http.disconnect":
            raise _Disconnected()
        if event.get("type") != "http.request":
            raise _Rejected()
        chunk, more = event.get("body", b""), event.get("more_body", False)
        if type(chunk) is not bytes or type(more) is not bool:
            raise _Rejected()
        size += len(chunk)
        if size > ingress.MAX_BODY_BYTES or (length is not None and size > length):
            raise _Rejected(413)
        chunks.append(chunk)
        if not more:
            if length is not None and size != length:
                raise _Rejected(413)
            return b"".join(chunks)
    raise _Rejected()


@dataclass(frozen=True, slots=True)
class ASGIApp:
    config: ingress.Config = field(default_factory=ingress.Config, repr=False)
    store: MetadataStore | None = field(default=None, repr=False)
    metadata_only_test: bool = False

    def __post_init__(self):
        if (type(self.config) is not ingress.Config
                or (self.store is not None and type(self.store) is not MetadataStore)
                or type(self.metadata_only_test) is not bool):
            raise ValueError("Invalid bootstrap configuration")

    async def _http(self, scope, receive, deadline):
        method, path = _request(scope)
        if path not in ("/healthz", "/readyz", "/webhook"):
            return _reply(404, b"Not found")
        if method not in (("GET", "POST") if path == "/webhook" else ("GET",)):
            return _reply(405, b"Method not allowed")
        headers = _headers(scope)
        if path == "/healthz":
            return _reply(200, _HEALTH)
        if path == "/readyz":
            return _reply(503, _READY)
        if method == "POST" and not self.metadata_only_test:
            return _reply(503, b"Metadata-only test receiver disabled")
        query = _query(scope)
        length = _length(headers)
        if method == "GET" and len({k for k, _ in headers}) != len(headers):
            raise _Rejected(403)
        remaining = deadline - asyncio.get_running_loop().time()
        if remaining <= 0:
            raise _Rejected(408)
        try:
            body = await asyncio.wait_for(_body(receive, length, deadline), remaining)
        except TimeoutError:
            raise _Rejected(408) from None
        if method == "GET" and body:
            raise _Rejected()
        # Synchronous ingress returns only after the store's FULL-sync commit.
        return ingress.handle(method, "/webhook", query, headers, body,
                              self.config, self.store, time.time_ns() // 1_000_000)

    async def __call__(self, scope, receive, send):
        kind = scope.get("type")
        if kind == "lifespan":
            while True:
                event = await receive()
                if event["type"] == "lifespan.startup":
                    await send({"type": "lifespan.startup.complete"})
                elif event["type"] == "lifespan.shutdown":
                    await send({"type": "lifespan.shutdown.complete"})
                    return
                else:
                    return
        elif kind == "websocket":
            await send({"type": "websocket.close", "code": 1008})
        elif kind == "http":
            deadline = asyncio.get_running_loop().time() + READ_SECONDS
            try:
                response = await self._http(scope, receive, deadline)
            except _Disconnected:
                return
            except _Rejected as error:
                response = _reply(error.status)
            except (ValueError, TypeError, UnicodeError):
                response = _reply(400)
            except Exception:
                response = _reply(503, b"Temporarily unavailable")
            headers = [(k.encode("ascii"), v.encode("ascii")) for k, v in response.headers]
            headers.append((b"content-length", str(len(response.body)).encode("ascii")))
            try:
                await send({"type": "http.response.start", "status": response.status,
                            "headers": headers})
                await send({"type": "http.response.body", "body": response.body,
                            "more_body": False})
            except OSError:
                return


app = ASGIApp()
