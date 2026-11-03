"""One bounded OpenAI test run; only fixed synthetic questions, no website calls."""
import argparse
import datetime
import getpass
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import time
import urllib.error
import urllib.request
import warnings

HERE = Path(__file__).resolve().parent
MODEL = 'gpt-5.4-mini'
URL = 'https://api.openai.com/v1/responses'


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def php(*args, data=None):
    return json.loads(subprocess.check_output(['php', str(HERE / 'synthetic-cases.php'), *args], input=data))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--dry-run', action='store_true')
    args = parser.parse_args()
    cases = php(MODEL)
    if len(cases) != 12 or any(case['request']['store'] is not False for case in cases):
        raise SystemExit('Unexpected synthetic test suite')
    if args.dry_run:
        print('PASS: 12 fixed synthetic requests; no network or key used.')
        return 0
    key = os.environ.get('OPENAI_API_KEY', '').strip()
    if not key:
        with warnings.catch_warnings():
            warnings.simplefilter('error', getpass.GetPassWarning)
            try:
                key = getpass.getpass('Paste company test API key (hidden; not saved): ').strip()
            except (EOFError, getpass.GetPassWarning):
                raise SystemExit('Use an interactive terminal with hidden input.') from None
    if not re.fullmatch(r'sk-[A-Za-z0-9_-]{16,512}', key):
        raise SystemExit('Invalid key format; no API request made.')
    opener = urllib.request.build_opener(NoRedirect())
    results = []
    error = None
    for case in cases:
        start = time.monotonic()
        try:
            req = urllib.request.Request(URL, data=json.dumps(case['request']).encode(), headers={
                'Authorization': 'Bearer ' + key, 'Content-Type': 'application/json'}, method='POST')
            with opener.open(req, timeout=12) as response:
                raw = response.read(128 * 1024 + 1)
            if len(raw) > 128 * 1024:
                error = 'oversized_response'
                break
            actual = php('--parse', data=raw)
            usage = json.loads(raw).get('usage') or {}
        except urllib.error.HTTPError as exc:
            error = f'http_{exc.code}'
            exc.close()
            break
        except (OSError, ValueError, subprocess.SubprocessError):
            error = 'transport_or_response_error'
            break
        result = {'lang': case['lang'], 'expected': case['expected'], 'actual': actual,
                  'pass': actual == case['expected'], 'ms': round((time.monotonic() - start) * 1000),
                  'input_tokens': usage.get('input_tokens'), 'output_tokens': usage.get('output_tokens')}
        results.append(result)
        print(json.dumps(result), flush=True)
    passed = len(results) == len(cases) and all(row['pass'] for row in results)
    receipt = {'model': MODEL, 'checked_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
               'passed': passed, 'error': error, 'results': results}
    out = HERE.parents[1] / 'outputs' / 'assistant-openai-synthetic'
    out.mkdir(parents=True, exist_ok=True)
    (out / 'latest.json').write_text(json.dumps(receipt, indent=2) + '\n', encoding='utf-8')
    print(f'Result: {sum(row["pass"] for row in results)}/{len(cases)}; error={error}; receipt={out / "latest.json"}')
    return 0 if passed else 1


if __name__ == '__main__':
    sys.exit(main())
