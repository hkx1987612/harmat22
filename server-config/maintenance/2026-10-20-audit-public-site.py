"""Sequential public GET/HEAD audit; no logins, submissions or private records."""
import argparse
from collections import defaultdict
import gzip
import json
from pathlib import Path
import re
import time
from urllib.parse import urljoin, urlsplit
import urllib.request
import urllib.error
import xml.etree.ElementTree as ET

from lxml import html

ORIGIN = 'https://harmat22.hu'
ROOT = Path(__file__).resolve().parents[2]
UA = 'HarmatPublicReadOnlyAudit/2026-10-20'
LIMIT = 2 * 1024 * 1024


def safe(url):
    p = urlsplit(url)
    if (p.scheme != 'https' or p.netloc != 'harmat22.hu' or p.query or p.fragment
            or any(x in p.path.lower() for x in ('wp-admin', 'wp-login', '/crm', '/ugyfel', '/agent'))):
        raise ValueError('Non-public URL refused')
    return url


def get(url):
    req = urllib.request.Request(safe(url), headers={'User-Agent': UA, 'Accept-Encoding': 'gzip'})
    start = time.monotonic()
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            ttfb = time.monotonic() - start
            data = r.read(LIMIT + 1)
            if len(data) > LIMIT:
                raise ValueError('Oversized response')
            wire = len(data)
            if r.headers.get('Content-Encoding') == 'gzip':
                data = gzip.decompress(data)
            if len(data) > LIMIT:
                raise ValueError('Oversized decoded response')
            return data, {'status': r.status, 'final': r.geturl(), 'ttfb_s': round(ttfb, 3),
                          'total_s': round(time.monotonic() - start, 3), 'wire_bytes': wire,
                          'decoded_bytes': len(data), 'cache': r.headers.get('Cache-Control')}
    except urllib.error.HTTPError as e:
        return b'', {'status': e.code, 'final': e.geturl()}


def sitemap(url, seen=None):
    seen = set() if seen is None else seen
    if url in seen or len(seen) > 10:
        raise ValueError('Unexpected sitemap fanout')
    seen.add(url)
    body, status = get(url)
    if status['status'] != 200:
        raise ValueError('Sitemap failed')
    tree = ET.fromstring(body)
    ns = {'s': 'http://www.sitemaps.org/schemas/sitemap/0.9'}
    locs = [n.text for n in tree.findall('s:sitemap/s:loc', ns)]
    if locs:
        return [page for child in locs for page in sitemap(safe(child), seen)]
    return [safe(n.text) for n in tree.findall('s:url/s:loc', ns)]


def compact(nodes):
    return [' '.join(n.text_content().split()) for n in nodes]


def page(url):
    body, info = get(url)
    result = {'url': url, **info}
    if info['status'] != 200:
        return result
    tree = html.fromstring(body)
    result.update(title=compact(tree.xpath('//title')), h1=compact(tree.xpath('//h1')),
                  description=tree.xpath('//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]/@content'),
                  canonical=tree.xpath('//link[@rel="canonical"]/@href'),
                  robots=tree.xpath('//meta[@name="robots"]/@content'),
                  hreflang=tree.xpath('//link[@hreflang]/@hreflang'), lang=tree.get('lang'))
    schemas, errors = [], []
    for node in tree.xpath('//script[@type="application/ld+json"]'):
        try:
            value = json.loads(node.text or '')
            def walk(v):
                if isinstance(v, dict):
                    if '@type' in v:
                        schemas.append(v['@type'])
                    for x in v.values():
                        walk(x)
                elif isinstance(v, list):
                    for x in v:
                        walk(x)
            walk(value)
        except (ValueError, TypeError):
            errors.append('Invalid JSON-LD')
    result['schema_types'], result['schema_errors'] = schemas, errors
    main = tree.xpath('//main|//*[@id="harmat-home-family"]|//*[@id="content"]')
    text = ' '.join(compact(main[:1] or [tree]))
    result['placeholder_hits'] = [x for x in ('Gipsz Jakab', '012-888-2222', 'agent.name@example.com', 'Lorem ipsum') if x.lower() in text.lower()]
    result['phones'] = sorted(set(tree.xpath('//a[starts-with(@href,"tel:")]/@href')))
    result['emails'] = sorted(set(tree.xpath('//a[starts-with(@href,"mailto:")]/@href')))
    imgs = tree.xpath('//img')
    result['images'] = {'count': len(imgs), 'missing_alt': sum('alt' not in n.attrib for n in imgs),
                        'missing_dimensions': sum(not n.get('width') or not n.get('height') for n in imgs)}
    result['scripts'] = len(tree.xpath('//script[@src]'))
    result['stylesheets'] = len(tree.xpath('//link[@rel="stylesheet"]'))
    result['http_resources'] = sorted(set(tree.xpath('//img[starts-with(@src,"http:")]/@src|//script[starts-with(@src,"http:")]/@src|//link[@rel="stylesheet" and starts-with(@href,"http:")]/@href')))
    if url == ORIGIN + '/':
        result['hero_sources'] = tree.xpath('//video/@src|//video/@data-src|//video/source/@src')
        result['iframe_sources'] = tree.xpath('//iframe/@src|//iframe/@data-src')
    return result


def redirects():
    result = []
    for url in ('http://harmat22.hu/', 'http://harmat22.hu/adatvedelmi-tajekoztato/', 'https://www.harmat22.hu/'):
        req = urllib.request.Request(url, method='HEAD', headers={'User-Agent': UA})
        try:
            with urllib.request.urlopen(req, timeout=20) as r:
                result.append({'url': url, 'status': r.status, 'final': r.geturl()})
        except Exception as e:
            result.append({'url': url, 'error': type(e).__name__})
    return result


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--out', required=True)
    p.add_argument('--areas', action='store_true')
    args = p.parse_args()
    out = ROOT / args.out
    out.mkdir(parents=True, exist_ok=True)
    target = out / 'report.json'
    if target.exists():
        raise ValueError('Keep old audit evidence')
    if args.areas:
        raw, info = get(ORIGIN+'/wp-json/harmat-public/v1/apartments')
        if info['status'] != 200:
            raise ValueError('Public feed unavailable')
        rows = json.loads(raw)['items']
        if len(rows) != 124:
            raise ValueError('Unexpected inventory count')
        result = []
        for i, row in enumerate(rows):
            raw, info = get(safe(row['url']))
            tree = html.fromstring(raw)
            values = tree.xpath('//section[@aria-label="Lakás összefoglaló"]//div[dt[normalize-space()="Eladási terület"]]/dd')
            text = ' '.join(compact(values))
            match = re.fullmatch(r'([\d\s\xa0]+(?:,\d+)?)\s*m²',text)
            area = float(match[1].replace('\xa0','').replace(' ','').replace(',','.')) if match else None
            result.append({'code':row['code'],'url':row['url'],'detail_sales_area':area,
                           'authority_sales_area':row['sales_area_m2'],
                           'mismatch':area is not None and abs(area-row['sales_area_m2']) > 0.005})
            if i % 25 == 0:
                print(json.dumps({'area_done':i+1,'total':len(rows)}),flush=True)
        report = {'scope':'PUBLIC_DISPLAY_COMPARISON_NOT_DATA_CORRECTION','items':result,
                  'mismatches':[r for r in result if r['mismatch']],
                  'unparsed':[r['code'] for r in result if r['detail_sales_area'] is None]}
        target.write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
        print(json.dumps({'count':len(rows),'mismatches':report['mismatches'],'unparsed':report['unparsed']}),flush=True)
        return
    urls = sorted(set(sitemap(ORIGIN + '/sitemap_index.xml')))
    if not 20 <= len(urls) <= 200:
        raise ValueError('Unexpected crawl size')
    rows = []
    for i, url in enumerate(urls):
        try:
            row = page(url)
        except Exception as e:
            row = {'url': url, 'error': type(e).__name__}
        rows.append(row)
        if i % 20 == 0:
            print(json.dumps({'done': i + 1, 'total': len(urls)}), flush=True)
    dup = {}
    for field in ('title', 'description'):
        groups = defaultdict(list)
        for row in rows:
            groups[tuple(row.get(field, []))].append(row['url'])
        dup[field] = [{'value': k, 'urls': v} for k, v in groups.items() if len(v) > 1]
    report = {'scope': 'PUBLIC_HTTP_NOT_BROWSER_CWV_OR_SEARCH_CONSOLE', 'pages': rows,
              'count': len(rows), 'duplicates': dup, 'redirects': redirects()}
    target.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'count': len(rows), 'failed': [r for r in rows if r.get('status') != 200],
                      'redirects': report['redirects'], 'report': str(target)}, ensure_ascii=True), flush=True)


if __name__ == '__main__':
    main()
