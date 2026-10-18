"""Sequential public GET-only payment-copy audit. No forms, REST writes or SSH."""
import argparse
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import re
import time
import unicodedata
from urllib.parse import urljoin, urlsplit, urlunsplit
import urllib.request
import uuid
import xml.etree.ElementTree as ET
from lxml import html

ROOT = Path(__file__).resolve().parents[2]
ORIGIN = 'https://harmat22.hu'
NS = {'s': 'http://www.sitemaps.org/schemas/sitemap/0.9'}
PRIVATE = re.compile(r'^/(sales|sales-admin|agent|client|customer|ugyfel|lawyer|wp-admin|wp-json|wp-login)(/|\.|$)', re.I)
DATE = re.compile(r'202[67]\s*(?:[./-]\s*\d{1,2}\s*[./-]\s*\d{1,2}|年\s*\d{1,2}\s*月\s*\d{1,2}|\.?(?:\s+)(?:december|január|február|március|április|május|június|julius|július|augusztus|szeptember|október|november)\s+\d{1,2})', re.I)
TERMS = re.compile(r'fizet|részlet|reszlet|kedvezm|engedm|foglaló|előleg|payment|installment|discount|付款|优惠|折扣|首付|定金|分期', re.I)
PERCENT = re.compile(r'(?<!\d)(?:4|8|10|15|20|25|50|75)\s*%')


def public_url(url):
    p = urlsplit(url)
    if p.scheme != 'https' or p.netloc != 'harmat22.hu' or p.query or PRIVATE.match(p.path):
        raise ValueError('Refuse noncanonical/private URL')
    return urlunsplit((p.scheme, p.netloc, p.path or '/', '', ''))


def get(url, limit=2 * 1024 * 1024):
    url = public_url(url)
    request = urllib.request.Request(url, headers={'User-Agent': 'Harmat-readonly-payment-audit/1.0', 'Cache-Control': 'no-cache'})
    with urllib.request.urlopen(request, timeout=30) as response:
        data = response.read(limit + 1)
        if response.status != 200 or public_url(response.geturl()) != url or len(data) > limit:
            raise ValueError('Public status/identity/size failed')
        return data, response.headers.get('Content-Type', '')


def normalize(text):
    return re.sub(r'\s+', ' ', unicodedata.normalize('NFKC', text)).strip()


def snippets(text, pattern, radius=150):
    found = []
    for match in pattern.finditer(text):
        value = text[max(0, match.start() - radius):match.end() + radius]
        if value not in found:
            found.append(value)
    return found


def inspect(data, url):
    doc = html.fromstring(data.decode('utf-8'))
    scripts, assets, pdfs, inline = [], set(), {}, []
    for node in doc.xpath('//script'):
        source = node.get('src')
        if source:
            p = urlsplit(urljoin(url, source))
            if p.netloc == 'harmat22.hu' and p.path.endswith('.js') and ('/mu-plugins/' in p.path or '/plugins/harmat' in p.path):
                assets.add(urlunsplit((p.scheme, p.netloc, p.path, '', '')))
        else:
            text = node.text or ''
            text = re.sub(r'\\u([a-fA-F0-9]{4})', lambda m: chr(int(m[1], 16)), text)
            if DATE.search(text) or (TERMS.search(text) and PERCENT.search(text)):
                inline.append({'id': node.get('id', ''), 'dates': snippets(normalize(text), DATE),
                               'payment_percentages': [s for s in snippets(normalize(text), PERCENT) if TERMS.search(s)]})
    for node in doc.xpath('//a[@href]'):
        p = urlsplit(urljoin(url, node.get('href')))
        if p.netloc == 'harmat22.hu' and p.path.lower().endswith(('.pdf', '.doc', '.docx', '.xlsx')):
            link = urlunsplit((p.scheme, p.netloc, p.path, '', ''))
            pdfs[link] = normalize(' '.join(node.itertext()))
    canonical = doc.xpath('//link[@rel="canonical"]/@href')
    forms = len(doc.xpath('//*[@id="h22-offer-form"]'))
    for node in doc.xpath('//script|//style|//noscript|//svg|//template'):
        node.drop_tree()
    body = doc.find('body')
    text = normalize(' '.join((body if body is not None else doc).itertext()))
    return {'url': url, 'canonical': canonical, 'native_form_count': forms, 'sha256': hashlib.sha256(data).hexdigest(),
            'dates': snippets(text, DATE), 'payment': snippets(text, TERMS),
            'payment_percentages': [s for s in snippets(text, PERCENT) if TERMS.search(s)],
            'inline': inline}, assets, pdfs


def run():
    out = ROOT / 'outputs/2026-10-18-payment-audit' / uuid.uuid4().hex
    out.mkdir(parents=True, exist_ok=False)
    index, _ = get(ORIGIN + '/sitemap_index.xml')
    urls = {ORIGIN + '/'}
    sitemaps = [n.text for n in ET.fromstring(index).findall('s:sitemap/s:loc', NS)]
    for sitemap in sitemaps:
        data, _ = get(sitemap)
        urls.update(n.text for n in ET.fromstring(data).findall('s:url/s:loc', NS))
    urls = sorted(public_url(u) for u in urls)
    if not 120 <= len(urls) <= 220:
        raise ValueError('Unexpected public crawl size')
    rows, failures, assets, documents = [], [], set(), {}
    for i, url in enumerate(urls, 1):
        try:
            data, mime = get(url)
            if 'text/html' not in mime:
                raise ValueError('Expected HTML')
            row, js, pdfs = inspect(data, url)
            rows.append(row)
            assets.update(js)
            for link, label in pdfs.items():
                documents.setdefault(link, []).append({'page': url, 'label': label})
            (out / (hashlib.sha256(url.encode()).hexdigest()[:16] + '.html')).write_bytes(data)
        except Exception as error:
            failures.append({'url': url, 'error': str(error)})
        if i % 25 == 0 or i == len(urls):
            print(json.dumps({'public_pages': i, 'total': len(urls), 'failures': len(failures)}), flush=True)
        time.sleep(.15)
    external = []
    if len(assets) > 60:
        raise ValueError('Too many first-party script assets')
    for url in sorted(assets):
        try:
            data, _ = get(url)
            text = data.decode('utf-8')
            text = re.sub(r'\\u([a-fA-F0-9]{4})', lambda m: chr(int(m[1], 16)), text)
            external.append({'url': url, 'sha256': hashlib.sha256(data).hexdigest(), 'dates': snippets(normalize(text), DATE),
                             'payment_percentages': [s for s in snippets(normalize(text), PERCENT) if TERMS.search(s)]})
        except Exception as error:
            failures.append({'url': url, 'error': str(error)})
        time.sleep(.15)
    result = {'time_utc': datetime.now(timezone.utc).isoformat(), 'scope': 'PUBLIC_GET_ONLY_NO_SUBMISSION_NO_SERVER_WRITE',
              'pages': rows, 'assets': external, 'documents': documents, 'failures': failures}
    (out / 'audit.json').write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'output': str(out), 'pages': len(rows), 'assets': len(external), 'documents': len(documents),
                      'failures': len(failures), 'payment_pages': [r['url'] for r in rows if r['payment_percentages']]}), flush=True)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--run', action='store_true')
    if parser.parse_args().run:
        run()
    else:
        print('OFFLINE_NO_NETWORK: use --run for sequential same-origin public GET audit')
