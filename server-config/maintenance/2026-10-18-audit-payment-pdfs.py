"""Read only three observed public PDFs; no customer documents or writes to site."""
import argparse
import hashlib
import io
import json
from pathlib import Path
import re
import time
import urllib.request

from pypdf import PdfReader

URLS = (
    'https://harmat22.hu/wp-content/uploads/2026/06/harmat-lakopark-muszaki-leiras.pdf',
    'https://harmat22.hu/wp-content/uploads/2026/05/a1-1-l2-cn-floorplan-web.pdf',
    'https://harmat22.hu/wp-content/uploads/2026/05/a1-f-l1-cn-floorplan-web.pdf',
)
OUT = Path(__file__).resolve().parents[2] / 'outputs/2026-10-18-payment-audit/pdf-sample.json'
PATTERN = re.compile(r'202[67][.\-/ ]\s*\d{1,2}[.\-/ ]\s*\d{1,2}|fizet|kedvezm|r.szlet|payment|discount|付款|优惠', re.I)


def run():
    rows = []
    for url in URLS:
        try:
            request = urllib.request.Request(url, headers={'User-Agent': 'Harmat-readonly-payment-audit/1.0'})
            with urllib.request.urlopen(request, timeout=40) as response:
                data = response.read(25 * 1024 * 1024 + 1)
                if response.status != 200 or response.geturl() != url or len(data) > 25 * 1024 * 1024 or not data.startswith(b'%PDF'):
                    raise ValueError('Unexpected public PDF identity or size')
            pdf = PdfReader(io.BytesIO(data))
            texts = [' '.join((p.extract_text() or '').split()) for p in pdf.pages]
            matches = [{'page': i, 'text': t[max(0, m.start()-100):m.end()+150]}
                       for i, t in enumerate(texts, 1) for m in PATTERN.finditer(t)]
            rows.append({'url': url, 'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest(),
                         'pages': len(texts), 'characters': [len(t) for t in texts], 'matches': matches})
        except Exception as error:
            rows.append({'url': url, 'error': str(error)})
        print(json.dumps(rows[-1], ensure_ascii=True), flush=True)
        time.sleep(.3)
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(rows, indent=2, ensure_ascii=True), encoding='utf-8')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--run', action='store_true')
    if parser.parse_args().run:
        run()
    else:
        print('OFFLINE_NO_NETWORK: use --run for the three explicit public PDFs')
