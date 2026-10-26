"""Read-only image/log diagnosis. Print aggregates only, never raw access logs."""
from collections import Counter
from datetime import datetime
import gzip
import importlib.util
import io
import json
from pathlib import Path
import re
from urllib.parse import unquote, urlsplit
from urllib.request import Request, urlopen

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('showroom_transport', HERE / '2026-10-23-home-showroom.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
PATH = '/wp-content/mu-plugins/harmat-home-family/showroom-exterior-harmat.jpg'
ORIGIN = 'https://harmat22.hu'
EXPECTED = 'ac4ad2578c1b44af479f5a0bcf9c07bb49e6f5a0245e24a65fc9a0b8e60eb1dc'
RECORD = re.compile(rb'\[([^\]]+)\]\s+"(GET|HEAD) ([^ ]+) HTTP/[^\"]+"\s+(\d{3})\s+')


def public():
    request = Request(ORIGIN + PATH, headers={'User-Agent': 'HarmatOfferSafety/1.0', 'Referer': ORIGIN + '/'})
    with urlopen(request, timeout=30) as response:
        data = response.read(1024 * 1024 + 1)
        M.require(len(data) <= 1024 * 1024 and response.geturl() == ORIGIN + PATH, 'Unexpected image size/redirect')
        result = {'status': response.status, 'mime': response.headers.get_content_type(),
                  'bytes': len(data), 'matches_original': M.sha(data) == EXPECTED}
        M.require(result == {'status': 200, 'mime': 'image/jpeg', 'bytes': 237920, 'matches_original': True}, 'Public image mismatch')
        return result


def audit(remote, name):
    path = '/home/harmath2/logs/' + name
    M.require(remote.info(path).st_size <= 8 * 1024 * 1024, 'Compressed log exceeds bounded audit')
    packed = remote.read(path)
    counts = Counter()
    methods = Counter()
    times = []
    first = last = None
    scanned = 0
    with gzip.GzipFile(fileobj=io.BytesIO(packed)) as handle:
        for line in handle:
            scanned += len(line)
            M.require(scanned <= 128 * 1024 * 1024, 'Expanded log exceeds bounded audit')
            match = RECORD.search(line)
            if match is None:
                continue
            try:
                timestamp = datetime.strptime(match[1].decode('ascii'), '%d/%b/%Y:%H:%M:%S %z')
            except ValueError:
                continue
            first = timestamp if first is None or timestamp < first else first
            last = timestamp if last is None or timestamp > last else last
            target = urlsplit(match[3].decode('ascii', 'replace'))
            if unquote(target.path) != PATH:
                continue
            counts[(timestamp.date().isoformat(), match[4].decode())] += 1
            methods[match[2].decode()] += 1
            times.append(timestamp.isoformat())
    return {'archive': name, 'range': [first.isoformat() if first else None, last.isoformat() if last else None],
            'image_statuses': [{'date': day, 'status': status, 'count': count}
                               for (day, status), count in sorted(counts.items())],
            'image_methods': dict(methods), 'first_image': min(times) if times else None,
            'last_image': max(times) if times else None}


if __name__ == '__main__':
    remote = M.H.Remote()
    try:
        source = remote.read(M.D.DIR + '/harmat-home-family/showroom-exterior-harmat.jpg')
        M.require(M.sha(source) == EXPECTED, 'Server original mismatch')
        result = {'mode': 'READ_ONLY_NO_SITE_OR_CUSTOMER_WRITES', 'server_original_matches': True,
                  'public': public(), 'logs': [audit(remote, name) for name in
                  ('harmat22.hu-Oct-2026.gz', 'harmat22.hu-ssl_log-Oct-2026.gz')]}
        out = HERE.parents[1] / 'outputs/2026-10-26-showroom-check'
        out.mkdir(parents=True, exist_ok=True)
        (out / 'image-audit.json').write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
        print(json.dumps(result, indent=2))
    finally:
        remote.close()
