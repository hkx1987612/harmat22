"""Synthetic stdlib fixtures only. Every test is guarded against real networking."""
from contextlib import contextmanager
from copy import deepcopy
from datetime import datetime, timedelta, timezone
import http.client
import io
import json
import os
import socket
import ssl
import unittest
from unittest.mock import MagicMock, patch

import inventory_reader as reader


NOW = datetime(2026, 10, 15, 12, 0, 0, tzinfo=timezone.utc)


def apartment(code='A1-F-L1', price=12_345_678):
    return dict(code=code, url='https://harmat22.hu/property/' + code.lower() + '/',
                building=code[:2], floor=code.split('-')[1], rooms=2,
                sales_area_m2=47.83, outdoor_area_m2=0, status='current', price_huf=price)


def snapshot(items=None):
    return dict(schema_version=1, generated_at='2026-10-15T12:00:00Z',
                source=reader.SOURCE_URL, currency='HUF',
                items=[apartment()] if items is None else items)


def encode(data):
    return json.dumps(data, separators=(',', ':')).encode('utf-8')


class Response(io.BytesIO):
    def __init__(self, payload, status=200, headers=None):
        super().__init__(payload)
        self.status = status
        self.headers = {'Content-Type': 'application/json'} if headers is None else headers
        self.read_sizes = []

    def getheader(self, name, default=None):
        return self.headers.get(name, default)

    def read(self, size=-1):
        self.read_sizes.append(size)
        return super().read(size)


@contextmanager
def transport(response):
    with response:
        yield response


class OfflineTests(unittest.TestCase):
    def setUp(self):
        for target in ('socket.create_connection', 'socket.socket.connect',
                       'socket.socket.connect_ex', 'socket.getaddrinfo'):
            guard = patch(target, side_effect=AssertionError('network forbidden'))
            guard.start()
            self.addCleanup(guard.stop)

    def validate(self, data, now=NOW):
        return reader.validate_inventory(encode(data), now=now)

    def invalid(self, data):
        with self.assertRaisesRegex(reader.InventoryUnavailable, '^inventory_unavailable$'):
            self.validate(data)

    def read(self, response, clock=lambda: NOW):
        return reader.read_inventory(transport=lambda: transport(response), clock=clock)


class ValidationTests(OfflineTests):
    def test_valid_schema_preserved(self):
        data = snapshot()
        self.assertEqual(self.validate(data), data)
        self.assertEqual(set(data), reader.ENVELOPE_FIELDS)
        self.assertEqual(set(data['items'][0]), reader.ITEM_FIELDS)

    def test_empty_inventory_valid_without_fallback(self):
        self.assertEqual(self.validate(snapshot([]))['items'], [])

    def test_null_price_preserved_for_every_status(self):
        for status in ('current', 'reserved', 'sold'):
            data = snapshot([apartment(price=None)])
            data['items'][0]['status'] = status
            with self.subTest(status=status):
                self.assertIsNone(self.validate(data)['items'][0]['price_huf'])

    def test_max_500_items_no_fixed_production_count(self):
        items = [apartment('A1-F-L' + str(n)) for n in range(500)]
        self.assertEqual(len(self.validate(snapshot(items))['items']), 500)
        self.invalid(snapshot(items + [apartment('A1-F-L500')]))

    def test_all_buildings_and_code_floors(self):
        for building in range(1, 5):
            for floor in ('F', '1', '9'):
                code = 'A' + str(building) + '-' + floor + '-L12'
                with self.subTest(code=code):
                    self.assertEqual(self.validate(snapshot([apartment(code)]))['items'][0]['code'], code)

    def test_exact_envelope_and_item_allowlists(self):
        for item_level in (False, True):
            original = snapshot()
            target = original['items'][0] if item_level else original
            for field in list(target):
                data = deepcopy(original)
                del (data['items'][0] if item_level else data)[field]
                with self.subTest(item_level=item_level, missing=field):
                    self.invalid(data)
            for key in ('customer', 'hide_price', 'price', 'fallback', '__proto__'):
                data = deepcopy(original)
                (data['items'][0] if item_level else data)[key] = 'forbidden'
                with self.subTest(item_level=item_level, extra=key):
                    self.invalid(data)

    def test_envelope_types_and_values(self):
        cases = {
            'schema_version': [True, 1.0, '1', 0, 2, None],
            'currency': ['EUR', 'huf', None, 1, [], {}],
            'source': ['http://harmat22.hu/lakaskereso/', reader.SOURCE_URL + '?x=1',
                       'https://elsewhere.test/lakaskereso/', None, [], {}],
            'items': [None, {}, '', 0, True],
        }
        for field, values in cases.items():
            for value in values:
                data = snapshot()
                data[field] = value
                with self.subTest(field=field, value=value):
                    self.invalid(data)
        for value in ([], None, True, 1, 'inventory'):
            self.invalid(value)
        for value in (None, [], True, 1):
            self.invalid(snapshot([value]))

    def test_strict_code_and_derived_fields(self):
        cases = {
            'code': ['a1-f-l1', 'A5-F-L1', 'A1-0-L1', 'A1-10-L1', 'A1-F-L',
                     'A1-F-L1\n', ' A1-F-L1', 'A1-F-L\u0661', 'A1-F-L' + '1' * 65,
                     None, 1, [], {}],
            'building': ['A2', 'a1', None, 1, [], {}],
            'floor': ['1', 'f', 0, True, None, [], {}],
            'status': ['available', 'CURRENT', '', 1, None, [], {}],
        }
        for field, values in cases.items():
            for value in values:
                data = snapshot()
                data['items'][0][field] = value
                with self.subTest(field=field, value=value):
                    self.invalid(data)

    def test_exact_canonical_property_url(self):
        urls = [
            'http://harmat22.hu/property/a1-f-l1/',
            'https://elsewhere.test/property/a1-f-l1/',
            'https://harmat22.hu.evil.test/property/a1-f-l1/',
            'https://user:pass@harmat22.hu/property/a1-f-l1/',
            'https://harmat22.hu:443/property/a1-f-l1/',
            'https://harmat22.hu/property/a1-f-l2/',
            'https://harmat22.hu/property/a1-f-l1/?customer=x',
            'https://harmat22.hu/property/a1-f-l1/#x',
            'https://harmat22.hu/property/%611-f-l1/',
            'https://harmat22.hu/property/../client/',
            'https://harmat22.hu/CLIENT/', '//harmat22.hu/property/a1-f-l1/',
            'file:///inventory', 'https://harmat22.hu/property/a1-f-l1', None, [], {},
        ]
        for url in urls:
            data = snapshot()
            data['items'][0]['url'] = url
            with self.subTest(url=url):
                self.invalid(data)

    def test_numeric_types_finiteness_and_bounds(self):
        limits = {'rooms': reader.MAX_ROOMS, 'sales_area_m2': reader.MAX_SALES_AREA_M2,
                  'outdoor_area_m2': reader.MAX_OUTDOOR_AREA_M2}
        for field, maximum in limits.items():
            for value in (maximum, 1.5):
                data = snapshot()
                data['items'][0][field] = value
                self.validate(data)
            bad = [True, False, None, '2', [], {}, -1, maximum + 1,
                   float('nan'), float('inf'), -float('inf'), 10**1000]
            if field != 'outdoor_area_m2':
                bad.append(0)
            for value in bad:
                data = snapshot()
                data['items'][0][field] = value
                with self.subTest(field=field, value=value):
                    self.invalid(data)

    def test_price_is_null_or_bounded_positive_integer(self):
        for value in (1, reader.MAX_PRICE_HUF, None):
            self.validate(snapshot([apartment(price=value)]))
        for value in (True, False, 0, -1, 1.0, '123', [], {}, float('nan'),
                      float('inf'), reader.MAX_PRICE_HUF + 1):
            with self.subTest(value=value):
                self.invalid(snapshot([apartment(price=value)]))

    def test_duplicate_code_or_url_rejects_entire_snapshot(self):
        item = apartment()
        self.invalid(snapshot([item, deepcopy(item)]))
        second = apartment('A2-1-L2')
        second['url'] = item['url']
        self.invalid(snapshot([item, second]))

    def test_freshness_inclusive_boundaries(self):
        for seconds in (-60, 0, 180):
            with self.subTest(age=seconds):
                self.validate(snapshot(), now=NOW + timedelta(seconds=seconds))
        for seconds in (-60.001, 180.001):
            with self.subTest(age=seconds), self.assertRaises(reader.InventoryUnavailable):
                self.validate(snapshot(), now=NOW + timedelta(seconds=seconds))

    def test_strict_generated_utc_timestamp(self):
        for value in (None, 1, [], {}, '2026-10-15T12:00:00+00:00',
                      '2026-10-15T12:00:00.000Z', '2026-10-15t12:00:00z',
                      '2026-02-30T12:00:00Z', '2026-10-15T12:00:60Z',
                      '2026-10-15T24:00:00Z', '2026-10-15T12:00:00Z\n',
                      '2026-1-15T12:00:00Z', '2026-10-15T12:00:00'):
            data = snapshot()
            data['generated_at'] = value
            with self.subTest(value=value):
                self.invalid(data)

    def test_clock_requires_aware_datetime(self):
        for now in (None, NOW.replace(tzinfo=None), 123, 'now', float('nan')):
            with self.subTest(now=now), self.assertRaises(reader.InventoryUnavailable):
                self.validate(snapshot(), now=now)
        self.validate(snapshot(), now=NOW.astimezone(timezone(timedelta(hours=2))))

    def test_duplicate_json_keys_including_escaped_keys(self):
        valid = encode(snapshot())
        for raw in (
            valid.replace(b'"schema_version":1', b'"schema_version":2,"schema_version":1'),
            valid.replace(b'"price_huf":12345678', b'"price_huf":null,"price_huf":12345678'),
            valid.replace(b'"code":"A1-F-L1"', b'"code":"x","co\\u0064e":"A1-F-L1"'),
        ):
            with self.subTest(raw=raw), self.assertRaises(reader.InventoryUnavailable):
                reader.validate_inventory(raw, now=NOW)

    def test_malformed_json_encoding_and_resource_bounds(self):
        valid = encode(snapshot())
        for raw in (b'', b'{', b'\xff', b'\xef\xbb\xbf' + valid, valid + b'{}',
                    b'[' * 2000 + b']' * 2000, b'{"x":' + b'9' * 5000 + b'}',
                    valid.replace(b'47.83', b'1e999'), valid.decode(), bytearray(valid)):
            with self.subTest(kind=type(raw)), self.assertRaises(reader.InventoryUnavailable):
                reader.validate_inventory(raw, now=NOW)
        exact = valid + b' ' * (reader.MAX_RESPONSE_BYTES - len(valid))
        self.assertEqual(reader.validate_inventory(exact, now=NOW), snapshot())
        with self.assertRaises(reader.InventoryUnavailable):
            reader.validate_inventory(exact + b' ', now=NOW)


class TransportTests(OfflineTests):
    def test_read_success_bound_and_cleanup(self):
        body = encode(snapshot())
        response = Response(body, headers={'Content-Type': 'application/json; charset=UTF-8',
                                           'Content-Length': str(len(body))})
        self.assertEqual(self.read(response), snapshot())
        self.assertEqual(response.read_sizes, [reader.MAX_RESPONSE_BYTES + 1])
        self.assertTrue(response.closed)

    def test_statuses_including_dependency_failure_and_all_redirects(self):
        for status in (*range(300, 400), 201, 204, 400, 401, 403, 404, 429, 500, 503, True, '200'):
            response = Response(b'PRIVATE ERROR CONTENT', status=status,
                                headers={'Location': 'https://elsewhere.test/customer/'})
            with self.subTest(status=status), self.assertRaisesRegex(
                    reader.InventoryUnavailable, '^inventory_unavailable$'):
                self.read(response)
            self.assertTrue(response.closed)
            self.assertEqual(response.read_sizes, [])

    def test_http_200_error_payload_not_inventory(self):
        self.invalid({'code': 'harmat_inventory_unavailable', 'message': 'synthetic dependency failure'})
        with self.assertRaises(reader.InventoryUnavailable):
            self.read(Response(b'{"code":"harmat_inventory_unavailable"}'))

    def test_oversize_and_bad_content_length_fail_closed(self):
        for length in (str(reader.MAX_RESPONSE_BYTES + 1), '-1', 'abc', '2,2', ' 2',
                       '+2', '9999999999', '0', '1'):
            response = Response(encode(snapshot()), headers={
                'Content-Type': 'application/json', 'Content-Length': length})
            with self.subTest(length=length), self.assertRaises(reader.InventoryUnavailable):
                self.read(response)
            self.assertTrue(response.closed)
        response = Response(b'x' * (reader.MAX_RESPONSE_BYTES + 100))
        with self.assertRaises(reader.InventoryUnavailable):
            self.read(response)
        self.assertEqual(response.read_sizes, [reader.MAX_RESPONSE_BYTES + 1])
        self.assertTrue(response.closed)

    def test_wrong_media_type_and_compression(self):
        for headers in ({}, {'Content-Type': 'text/html'},
                        {'Content-Type': 'application/json', 'Content-Encoding': 'gzip'},
                        {'Content-Type': 'application/json', 'Content-Encoding': 'br'}):
            response = Response(encode(snapshot()), headers=headers)
            with self.subTest(headers=headers), self.assertRaises(reader.InventoryUnavailable):
                self.read(response)
            self.assertEqual(response.read_sizes, [])

    def test_transport_failures_are_sanitized_no_retry_or_fallback(self):
        for error in (TimeoutError('PRIVATE TIMEOUT'), socket.timeout('PRIVATE SOCKET'),
                      OSError('PRIVATE DNS'), ssl.SSLError('PRIVATE TLS'),
                      http.client.HTTPException('PRIVATE HTTP'), RuntimeError('PRIVATE DEPENDENCY')):
            mock = MagicMock(side_effect=error)
            with self.subTest(kind=type(error)), self.assertRaisesRegex(
                    reader.InventoryUnavailable, '^inventory_unavailable$'):
                reader.read_inventory(transport=mock, clock=lambda: NOW)
            mock.assert_called_once_with()

    def test_read_timeout_and_truncation_cleanup(self):
        for error in (TimeoutError('PRIVATE BODY'), http.client.IncompleteRead(b'PRIVATE PARTIAL')):
            response = Response(b'')
            with patch.object(response, 'read', side_effect=error), self.assertRaisesRegex(
                    reader.InventoryUnavailable, '^inventory_unavailable$'):
                self.read(response)
            self.assertTrue(response.closed)

    def test_clock_sampled_after_read_no_stale_success_cache(self):
        self.read(Response(encode(snapshot())))
        response = Response(encode(snapshot()))

        def late_clock():
            self.assertTrue(response.closed)
            return NOW + timedelta(seconds=181)

        with self.assertRaises(reader.InventoryUnavailable):
            self.read(response, clock=late_clock)
        with self.assertRaises(reader.InventoryUnavailable):
            self.read(Response(b'broken'))
        with self.assertRaises(reader.InventoryUnavailable):
            self.read(Response(b'unavailable', status=503))

    def test_invalid_clock_never_uses_default_time(self):
        for now in (None, NOW.replace(tzinfo=None), 'now'):
            with self.subTest(now=now), patch.object(
                    reader, '_utc_now', side_effect=AssertionError('clock fallback forbidden')):
                with self.assertRaises(reader.InventoryUnavailable):
                    self.read(Response(encode(snapshot())), clock=lambda: now)

    def test_invalid_dependencies_never_use_default_transport(self):
        with patch.object(reader, '_public_transport') as default:
            with self.assertRaises(reader.InventoryUnavailable):
                reader.read_inventory(transport=False, clock=lambda: NOW)
            default.assert_not_called()

    def test_no_url_configuration_parameter(self):
        with self.assertRaises(TypeError):
            reader.read_inventory(url='https://customer.test/')

    def test_default_transport_fixed_get_tls_and_ignores_proxy_environment(self):
        response = Response(encode(snapshot()))
        with patch.dict(os.environ, {
                'HTTP_PROXY': 'http://customer.test/', 'HTTPS_PROXY': 'http://customer.test/',
                'ALL_PROXY': 'http://customer.test/', 'https_proxy': 'http://customer.test/',
                'INVENTORY_URL': 'https://customer.test/'}), patch.object(
                    reader.http.client, 'HTTPSConnection') as factory, patch.object(
                    reader.ssl, 'create_default_context',
                    side_effect=AssertionError('implicit TLS key logging forbidden')):
            connection = factory.return_value
            connection.getresponse.return_value = response
            self.assertEqual(reader.read_inventory(clock=lambda: NOW), snapshot())
            factory.assert_called_once()
            self.assertEqual(factory.call_args.args, ('harmat22.hu',))
            self.assertEqual(factory.call_args.kwargs['timeout'], 3)
            context = factory.call_args.kwargs['context']
            self.assertIsInstance(context, ssl.SSLContext)
            self.assertTrue(context.check_hostname)
            self.assertEqual(context.verify_mode, ssl.CERT_REQUIRED)
            self.assertIsNone(context.keylog_filename)
            connection.request.assert_called_once_with(
                'GET', '/wp-json/harmat-public/v1/apartments', headers={
                    'Accept': 'application/json', 'Accept-Encoding': 'identity'})
            connection.close.assert_called_once_with()
            self.assertTrue(response.closed)

    def test_default_transport_redirect_never_followed(self):
        with patch.object(reader.http.client, 'HTTPSConnection') as factory:
            connection = factory.return_value
            connection.getresponse.return_value = Response(b'', status=302,
                headers={'Location': 'https://harmat22.hu/client/'})
            with self.assertRaises(reader.InventoryUnavailable):
                reader.read_inventory(clock=lambda: NOW)
            factory.assert_called_once()
            connection.request.assert_called_once()
            connection.close.assert_called_once_with()

    def test_default_transport_closes_on_request_failure(self):
        with patch.object(reader.http.client, 'HTTPSConnection') as factory:
            connection = factory.return_value
            connection.request.side_effect = TimeoutError('PRIVATE')
            with self.assertRaisesRegex(reader.InventoryUnavailable, '^inventory_unavailable$'):
                reader.read_inventory(clock=lambda: NOW)
            connection.close.assert_called_once_with()


if __name__ == '__main__':
    unittest.main()
