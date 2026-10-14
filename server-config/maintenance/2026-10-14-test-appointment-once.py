"""Explicitly approved one TEST lead. No browser/ad pixels, no repeat or manual mail."""
import argparse
import importlib.util
import json
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'outputs/2026-10-14-appointment-qa/approved-once'
STATE = OUT / 'state.json'
PAGE = 'https://harmat22.hu/property/a1-1-l2/'
NAME = 'TESZT - Harmat rendszerellen\u0151rz\u00e9s'
EMAIL = 'ertekesites@harmat22.hu'


def save(state):
    temporary = OUT / ('state-' + uuid.uuid4().hex + '.json')
    with temporary.open('x', encoding='utf-8') as handle:
        json.dump(state, handle, indent=2, ensure_ascii=True)
    temporary.replace(STATE)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--send-once', action='store_true')
    args = parser.parse_args()
    if not args.send_once:
        raise SystemExit('No send without --send-once and explicit user approval')
    OUT.mkdir(parents=True, exist_ok=True)
    if STATE.exists():
        raise SystemExit('Existing test intent/result preserved. Never automatically send again.')
    spec = importlib.util.spec_from_file_location('probe', Path(__file__).with_name('2026-10-14-probe-appointment.py'))
    probe = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(probe)
    headers = {'User-Agent': 'HarmatAppointmentAudit/1.0', 'Origin': 'https://harmat22.hu', 'Referer': PAGE}
    with urllib.request.urlopen(urllib.request.Request(PAGE, headers=headers), timeout=30) as response:
        form = probe.PublicFormParser()
        form.feed(response.read().decode('utf-8'))
    if len(form.nonces) != 1 or not form.nonces[0]:
        raise RuntimeError('Current form nonce not unambiguous; no submission')
    marker = 'HARMAT-QA-20261014-' + uuid.uuid4().hex[:8]
    params = {
        'your-name': NAME, 'your-email': EMAIL, 'your-phone': '+36300733375',
        'your-date': '2026-10-20', 'your-time': '09:00-12:00',
        'your-message': 'TESZT - ne h\u00edvj\u00e1k vissza! Nem val\u00f3di \u00fcgyf\u00e9l vagy foglal\u00e1s. K\u00e9rj\u00fck, ne foglaljanak le lak\u00e1st \u00e9s ne m\u00f3dos\u00edtsanak \u00fcgyf\u00e9ladatokat. ' + marker,
        'selected-apartment': 'A1-1-L2', 'selected-url': PAGE, 'privacy-acceptance': '1',
        'lead_source': 'Google keres\u00e9s', 'source_url': PAGE, 'landing_page': PAGE,
        'utm_source': 'maintenance_test', 'utm_medium': 'qa_no_ads',
        '_harmat_offer_nonce': form.nonces[0],
    }
    if len(params['your-message']) > 200:
        raise RuntimeError('TEST marker must not be truncated')
    state = {'status': 'SEND_INTENT_NO_RETRY', 'marker': marker,
             'public_test_contact': {'name': NAME, 'email': EMAIL, 'phone': params['your-phone']},
             'date': params['your-date'], 'time': params['your-time'], 'apartment': 'A1-1-L2',
             'source': params['lead_source'], 'scope': 'ONE_APPROVED_TEST_NO_AD_PIXEL_NO_PROPERTY_LOCK'}
    with STATE.open('x', encoding='utf-8') as handle:
        json.dump(state, handle, indent=2)
    request = urllib.request.Request(probe.ENDPOINT, data=urllib.parse.urlencode(params).encode(),
                  headers={**headers, 'Content-Type': 'application/x-www-form-urlencoded'}, method='POST')
    try:
        with urllib.request.urlopen(request, timeout=45) as response:
            payload, status = json.loads(response.read()), response.status
    except urllib.error.HTTPError as error:
        state.update(status='HTTP_REJECTED_NO_RETRY', http_status=error.code,
                     result=json.loads(error.read()))
        save(state)
        raise
    state.update(status='HTTP_RESULT_RECEIVED_NO_RETRY', http_status=status, result=payload)
    save(state)
    if status != 200 or not payload.get('success') or not isinstance(payload.get('id'), int) or not payload.get('crm'):
        raise RuntimeError('Unexpected result; preserve state and investigate, never retry')
    print(json.dumps(state, indent=2, ensure_ascii=True))


if __name__ == '__main__':
    main()
