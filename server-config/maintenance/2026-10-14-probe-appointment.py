"""Safe live rejection probes: required name/email/phone always empty; no lead/mail."""
import argparse
import hashlib
import json
from html.parser import HTMLParser
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ENDPOINT = 'https://harmat22.hu/wp-json/harmat-sales-manager/v1/offer'


class PublicFormParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.nonces = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == 'input' and attributes.get('name') == '_harmat_offer_nonce':
            self.nonces.append(attributes.get('value', ''))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True)
    parser.add_argument('--smoke', action='store_true', help='Public GET health only; no POST or client JavaScript')
    args = parser.parse_args()
    output = Path(args.output).resolve()
    if not output.is_relative_to((ROOT / 'outputs/2026-10-14-appointment-qa').resolve()):
        raise RuntimeError('Only ignored owned evidence path is allowed')
    if output.exists():
        raise RuntimeError('Prior probe evidence preserved')
    if args.smoke:
        results = []
        for path in ('/', '/lakaskereso/', '/property/a1-1-l2/', '/virtualis-lakasvalaszto/',
                     '/virtualis-lakasvalaszto-elso-utem/', '/galeria/', '/elerhetosegeink/',
                     '/epitesi-naplo/', '/koszonjuk/'):
            request = urllib.request.Request('https://harmat22.hu' + path,
                        headers={'User-Agent': 'HarmatAppointmentAudit/1.0'})
            with urllib.request.urlopen(request, timeout=30) as response:
                body, status, content_type = response.read(), response.status, response.headers.get('Content-Type', '')
            fatal = any(marker in body.lower() for marker in (
                b'<b>fatal error</b>', b'<b>parse error</b>', b'there has been a critical error on this website'))
            passed = status == 200 and 'text/html' in content_type and len(body) > 2048 and not fatal
            results.append({'path': path, 'status': status, 'html_bytes': len(body), 'fatal_marker': fatal, 'pass': passed})
        output.parent.mkdir(parents=True, exist_ok=True)
        report = {'scope': 'PUBLIC_GET_HEALTH_ONLY_NO_JS_OR_SUBMISSION', 'results': results,
                  'failures': [result for result in results if not result['pass']]}
        with output.open('x', encoding='utf-8') as handle:
            json.dump(report, handle, indent=2)
        print(json.dumps(report, indent=2))
        raise SystemExit(1 if report['failures'] else 0)
    cases = [
        ('empty', {}, 'harmat_offer_required'),
        ('outdoor', {'lead_source': 'K\u00fclt\u00e9ri hirdet\u00e9s'}, 'harmat_offer_required'),
        ('google', {'lead_source': 'Google keres\u00e9s'}, 'harmat_offer_required'),
        ('ingatlan', {'lead_source': 'ingatlan.com'}, 'harmat_offer_required'),
        ('social', {'lead_source': 'K\u00f6z\u00f6ss\u00e9gi m\u00e9dia'}, 'harmat_offer_required'),
        ('other', {'lead_source': 'Egy\u00e9b'}, 'harmat_offer_required'),
        ('facebook_social_path', {'lead_source': 'K\u00f6z\u00f6ss\u00e9gi m\u00e9dia',
                                  'utm_source': 'facebook', 'utm_medium': 'paid_social',
                                  'source_url': 'https://harmat22.hu/?utm_source=facebook&utm_medium=paid_social'}, 'harmat_offer_required'),
        ('past_date', {'your-date': '2026-10-13'}, 'harmat_offer_integrity_bad_date'),
        ('malformed_date', {'your-date': 'not-a-date'}, 'harmat_offer_integrity_bad_date'),
        ('invalid_time', {'your-time': '03:00-05:00'}, 'harmat_offer_integrity_bad_time'),
    ]
    nonce_pages = []
    for path in ('/', '/elerhetosegeink/', '/property/a1-1-l2/'):
        page_url = 'https://harmat22.hu' + path
        request = urllib.request.Request(page_url, headers={'User-Agent': 'HarmatAppointmentAudit/1.0'})
        with urllib.request.urlopen(request, timeout=30) as response:
            parser = PublicFormParser()
            parser.feed(response.read().decode('utf-8'))
        if len(parser.nonces) != 1 or not parser.nonces[0]:
            raise RuntimeError('Missing/ambiguous public form nonce on ' + path)
        nonce_pages.append({'page': path, 'nonce_sha256': hashlib.sha256(parser.nonces[0].encode()).hexdigest()})
        cases.append(('page_nonce_' + path, {'_harmat_offer_nonce': parser.nonces[0], 'source_url': page_url}, 'harmat_offer_required'))
    results = []
    for name, extra, expected in cases:
        params = {'your-name': '', 'your-email': '', 'your-phone': '', 'privacy-acceptance': '',
                  'source_url': 'https://harmat22.hu/', **extra}
        if any(params[key] for key in ('your-name', 'your-email', 'your-phone', 'privacy-acceptance')):
            raise RuntimeError('No positive live submission is allowed')
        request = urllib.request.Request(ENDPOINT, data=urllib.parse.urlencode(params).encode(),
                    headers={'Origin': 'https://harmat22.hu', 'Referer': 'https://harmat22.hu/',
                             'User-Agent': 'HarmatAppointmentAudit/1.0',
                             'Content-Type': 'application/x-www-form-urlencoded'}, method='POST')
        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                status, raw = response.status, response.read()
        except urllib.error.HTTPError as error:
            status, raw = error.code, error.read()
        payload = json.loads(raw)
        results.append({'case': name, 'status': status, 'expected': expected,
                        'actual': payload.get('code'), 'message': payload.get('message'),
                        'pass': status == 400 and payload.get('code') == expected})
        if status < 400 or payload.get('success'):
            raise RuntimeError('Unexpected success; stopped all probes, do not retry')
    report = {'scope': 'SAFE_LIVE_NEGATIVE_ONLY_REQUIRED_FIELDS_EMPTY', 'page_nonces': nonce_pages, 'results': results,
              'failures': [result for result in results if not result['pass']],
              'limits': ['No valid submission or email delivered', 'No success/CRM write/delivery claim']}
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open('x', encoding='utf-8') as handle:
        json.dump(report, handle, indent=2, ensure_ascii=True)
    print(json.dumps(report, indent=2, ensure_ascii=True))
    raise SystemExit(1 if report['failures'] else 0)


if __name__ == '__main__':
    main()
