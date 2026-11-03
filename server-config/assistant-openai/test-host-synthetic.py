"""Check OpenAI reachability from the WordPress host without installing a key."""
import json
from pathlib import Path
import re
import runpy
import shlex
import sys

HERE = Path(__file__).resolve().parent
test = runpy.run_path(str(HERE / 'test-live-synthetic.py'))
REMOTE = r'''
import json, sys, urllib.error, urllib.request
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None
payload = json.load(sys.stdin)
opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
for body in payload['requests']:
    try:
        req = urllib.request.Request('https://api.openai.com/v1/responses',
            data=json.dumps(body).encode(), headers={'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + payload['key']}, method='POST')
        with opener.open(req, timeout=10) as res:
            raw = res.read(131073)
        if len(raw) > 131072:
            raise ValueError('response limit')
        print(json.dumps({'response': json.loads(raw)}), flush=True)
    except urllib.error.HTTPError as exc:
        print(json.dumps({'error': 'http_' + str(exc.code)}), flush=True)
        break
    except (OSError, ValueError):
        print(json.dumps({'error': 'transport_or_response_error'}), flush=True)
        break
'''


def main():
    key = sys.stdin.readline().strip()
    if not re.fullmatch(r'sk-[A-Za-z0-9_-]{16,512}', key):
        raise SystemExit('Missing test key; no connection attempted.')
    cases = test['php'](test['MODEL'])
    if len(cases) != 12:
        raise SystemExit('Unexpected test suite.')
    transport = runpy.run_path(str(HERE.parent / 'maintenance' / '2026-10-14-deploy-appointment-source.py'))
    r = transport['Remote']()
    try:
        stdin, stdout, stderr = r.client.exec_command('python3 -c ' + shlex.quote(REMOTE), timeout=145)
        stdin.write(json.dumps({'key': key, 'requests': [c['request'] for c in cases]}))
        stdin.flush()
        stdin.channel.shutdown_write()
        rows = stdout.read(2 * 1024 * 1024)
        status = stdout.channel.recv_exit_status()
        if status:
            raise SystemExit('Host test runtime failed; no credential or remote stderr logged.')
    finally:
        r.close()
    count = 0
    for case, line in zip(cases, rows.splitlines()):
        row = json.loads(line)
        if 'error' in row:
            print('Host API failure: ' + row['error'])
            break
        actual = test['php']('--parse', data=json.dumps(row['response']).encode())
        passed = actual == case['expected']
        count += passed
        print(f"{case['lang']} {case['expected']}: {'PASS' if passed else 'FAIL'}")
    print(f'Host synthetic result: {count}/{len(cases)}; no key installed or visitor traffic used.')
    return 0 if count == len(cases) else 1


if __name__ == '__main__':
    sys.exit(main())
