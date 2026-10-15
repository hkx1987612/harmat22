"""Public inventory only; no cache, fallback, credentials or service integration.

Transport and clock injection are trusted application/test dependencies, never
customer inputs. Returned dictionaries contain only the validated public schema.
"""
from contextlib import contextmanager
from datetime import datetime, timezone
import http.client
import json
import math
import re
import ssl


INVENTORY_URL = 'https://harmat22.hu/wp-json/harmat-public/v1/apartments'
SOURCE_URL = 'https://harmat22.hu/lakaskereso/'
MAX_RESPONSE_BYTES = 128 * 1024
TIMEOUT_SECONDS = 3
MAX_ITEMS = 500
MAX_AGE_SECONDS = 180
MAX_FUTURE_SECONDS = 60
# Defensive ceilings, not project room/area counts or an approved price table.
MAX_ROOMS = 20
MAX_SALES_AREA_M2 = 10_000
MAX_OUTDOOR_AREA_M2 = 100_000
MAX_PRICE_HUF = 2**53 - 1
ENVELOPE_FIELDS = frozenset(('schema_version', 'generated_at', 'source', 'currency', 'items'))
ITEM_FIELDS = frozenset(('code', 'url', 'building', 'floor', 'rooms',
                         'sales_area_m2', 'outdoor_area_m2', 'status', 'price_huf'))
CODE = re.compile(r'A[1-4]-(F|[1-9])-L[0-9]+', re.ASCII)
UTC_STAMP = re.compile(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z', re.ASCII)


class InventoryUnavailable(RuntimeError):
    """Fixed input-free failure: callers must not substitute stale/default data."""


def _require(condition):
    if not condition:
        raise InventoryUnavailable('inventory_unavailable')


def _utc_now():
    return datetime.now(timezone.utc)


def _unique_object(pairs):
    obj = {}
    for key, value in pairs:
        _require(key not in obj)
        obj[key] = value
    return obj


def _reject_constant(value):
    raise InventoryUnavailable('inventory_unavailable')


def _number(value, maximum, *, zero=False):
    return (type(value) in (int, float) and 0 <= value <= maximum
            and (zero or value > 0) and math.isfinite(value))


def _validate(payload, now):
    _require(type(payload) is bytes and len(payload) <= MAX_RESPONSE_BYTES)
    data = json.loads(payload.decode('utf-8'), object_pairs_hook=_unique_object,
                      parse_constant=_reject_constant)
    _require(type(data) is dict and data.keys() == ENVELOPE_FIELDS)
    _require(type(data['schema_version']) is int and data['schema_version'] == 1)
    _require(data['source'] == SOURCE_URL and data['currency'] == 'HUF')
    stamp = data['generated_at']
    _require(type(stamp) is str and UTC_STAMP.fullmatch(stamp) is not None)
    generated = datetime.strptime(stamp, '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=timezone.utc)
    _require(type(now) is datetime and now.utcoffset() is not None)
    age = (now - generated).total_seconds()
    _require(-MAX_FUTURE_SECONDS <= age <= MAX_AGE_SECONDS)
    items = data['items']
    _require(type(items) is list and len(items) <= MAX_ITEMS)
    codes, urls = set(), set()
    for item in items:
        _require(type(item) is dict and item.keys() == ITEM_FIELDS)
        code = item['code']
        _require(type(code) is str and len(code) <= 64)
        match = CODE.fullmatch(code)
        _require(match is not None)
        _require(item['building'] == code[:2] and item['floor'] == match[1])
        url = 'https://harmat22.hu/property/' + code.lower() + '/'
        _require(item['url'] == url)
        _require(code not in codes and url not in urls)
        codes.add(code)
        urls.add(url)
        _require(_number(item['rooms'], MAX_ROOMS))
        _require(_number(item['sales_area_m2'], MAX_SALES_AREA_M2))
        _require(_number(item['outdoor_area_m2'], MAX_OUTDOOR_AREA_M2, zero=True))
        _require(type(item['status']) is str and item['status'] in ('current', 'reserved', 'sold'))
        price = item['price_huf']
        _require(price is None or (type(price) is int and 0 < price <= MAX_PRICE_HUF))
    return data


def validate_inventory(payload, *, now):
    """Validate bounded UTF-8 JSON bytes, preserving null price without inference.

    Reject the whole snapshot on any failure, including duplicate JSON keys.
    Freshness is checked at validation time, not guaranteed for later reuse.
    """
    try:
        return _validate(payload, now)
    except Exception:
        raise InventoryUnavailable('inventory_unavailable') from None


@contextmanager
def _public_transport():
    # Direct HTTPSConnection ignores proxy environments and never follows redirects.
    # The endpoint is deliberately not a function argument or environment setting.
    # PROTOCOL_TLS_CLIENT validates certificates/hostnames by default without
    # create_default_context's optional SSLKEYLOGFILE environment side effect.
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
    context.load_default_certs()
    connection = http.client.HTTPSConnection(
        'harmat22.hu', timeout=TIMEOUT_SECONDS, context=context)
    try:
        connection.request('GET', '/wp-json/harmat-public/v1/apartments', headers={
            'Accept': 'application/json', 'Accept-Encoding': 'identity',
        })
        with connection.getresponse() as response:
            yield response
    finally:
        connection.close()


def read_inventory(*, transport=None, clock=None):
    """One fixed anonymous GET; no retries, redirects, persistence or fallback.

    The 3-second timeout is the socket-operation timeout, not a hard total/DNS
    deadline. Trusted transport() yields an HTTPResponse-compatible context;
    trusted clock() returns an aware datetime, sampled after the response read.
    """
    try:
        with (_public_transport if transport is None else transport)() as response:
            _require(type(response.status) is int and response.status == 200)
            content_type = response.getheader('Content-Type', '')
            _require(content_type.split(';', 1)[0].strip().lower() == 'application/json')
            _require(response.getheader('Content-Encoding', 'identity').lower() == 'identity')
            length = response.getheader('Content-Length')
            if length is not None:
                _require(re.fullmatch(r'[0-9]{1,9}', length, re.ASCII) is not None)
                _require(int(length) <= MAX_RESPONSE_BYTES)
            payload = response.read(MAX_RESPONSE_BYTES + 1)
            _require(type(payload) is bytes and len(payload) <= MAX_RESPONSE_BYTES)
            _require(length is None or len(payload) == int(length))
        return validate_inventory(payload, now=(_utc_now if clock is None else clock)())
    except Exception:
        raise InventoryUnavailable('inventory_unavailable') from None
