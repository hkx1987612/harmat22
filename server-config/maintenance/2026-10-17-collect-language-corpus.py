"""Read-only bounded public sitemap/text snapshot for internal translation QA."""
import hashlib
import json
from pathlib import Path
import re
import sys
import urllib.request
import xml.etree.ElementTree as ET
from urllib.parse import urlsplit
from lxml import html

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'outputs/2026-10-17-internal-language'
NS = {'s': 'http://www.sitemaps.org/schemas/sitemap/0.9'}
BLOCK = {'script', 'style', 'noscript', 'textarea', 'input', 'svg', 'code', 'pre'}

def fetch(url):
    p = urlsplit(url)
    if p.scheme != 'https' or p.netloc != 'harmat22.hu' or p.query or p.fragment:
        raise ValueError('Public same-origin URLs only')
    req = urllib.request.Request(url, headers={'User-Agent': 'Harmat-readonly-language-QA/1.0'})
    with urllib.request.urlopen(req, timeout=30) as r:
        data = r.read(2*1024*1024 + 1)
        if r.status != 200 or r.geturl() != url or len(data) > 2*1024*1024:
            raise ValueError('HTTP status/identity/size')
        return data

def candidate(value):
    value = value.strip()
    if not value or not re.search('[A-Za-zÀ-ž]', value): return False
    if re.fullmatch(r'[\d\s.,/%²³€+\-–:()]+(?:m|m²|Ft|HUF|kWh|W|db)?', value): return False
    if re.fullmatch(r'A[1-4](?:[-\s/][A-Za-z0-9]+)*', value): return False
    if re.fullmatch(r'(https?://|www\.)\S+|[^\s@]+@[^\s@]+', value): return False
    if value in {'Harmat Lakópark','Cooperation Power Kft.','Harmat utca 22.','Harmat u. 22.','Budapest','1105 Budapest,','1105 Budapest, Harmat utca 22.','m²','Ft','HUF','m2','A1','A2','A3','A4'}: return False
    return True

def strings(data):
    tree = html.fromstring(data.decode('utf-8'))
    result = set()
    for node in tree.iter():
        if not isinstance(node.tag, str): continue
        if node.tag not in BLOCK and not any(a.tag in BLOCK for a in node.iterancestors()):
            if node.text and candidate(node.text): result.add(node.text.strip())
            for attr in ('alt','title','aria-label','placeholder'):
                value=node.get(attr)
                if value and candidate(value): result.add(value.strip())
        if node.tail and not any(a.tag in BLOCK for a in node.iterancestors()) and candidate(node.tail):
            result.add(node.tail.strip())
    return sorted(result)

def main():
    OUT.mkdir(parents=True,exist_ok=True)
    urls=[]
    for loc in ET.fromstring(fetch('https://harmat22.hu/sitemap_index.xml')).findall('s:sitemap/s:loc',NS):
        if not loc.text.endswith(('page-sitemap.xml','property-sitemap.xml')): continue
        urls += [e.text for e in ET.fromstring(fetch(loc.text)).findall('s:url/s:loc',NS)]
    urls=sorted(set(urls)|{'https://harmat22.hu/'})
    if not 120 <= len(urls) <= 200: raise ValueError('Unexpected public URL count')
    corpus={}; manifest=[]
    for index,url in enumerate(urls):
        if re.match(r'^/(sales|agent|client|lawyer|wp-admin|wp-json|wp-login)',urlsplit(url).path):
            raise ValueError('Private route in public sitemap')
        data=fetch(url); filename=hashlib.sha256(url.encode()).hexdigest()[:16]+'.html'
        (OUT/filename).write_bytes(data)
        text=strings(data)
        for value in text: corpus.setdefault(value,[]).append(urlsplit(url).path)
        manifest.append({'url':url,'file':filename,'sha256':hashlib.sha256(data).hexdigest(),'strings':len(text)})
        if index%25==0: print('read',index+1,'/',len(urls),flush=True)
    (OUT/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf-8')
    (OUT/'corpus.json').write_text(json.dumps(corpus,ensure_ascii=False,indent=2),encoding='utf-8')
    marketing={}; legal={}; properties={}
    for text,paths in corpus.items():
        if any('tajekoztato' in p or 'impresszum' in p or 'feltetel' in p for p in paths) and len(paths)<10:
            legal[text]=paths
        elif all(p.startswith('/property/') for p in paths): properties[text]=paths
        else: marketing[text]=paths
    for name,value in [('marketing',marketing),('legal',legal),('property',properties)]:
        (OUT/(name+'-source.json')).write_text(json.dumps(value,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps({'urls':len(urls),'unique':len(corpus),'marketing':len(marketing),'legal':len(legal),'property':len(properties)}))

def partial_index():
    corpus={}
    for path in OUT.glob('*.html'):
        data=path.read_bytes(); tree=html.fromstring(data.decode('utf-8'))
        links=tree.xpath('//link[@rel="canonical"]/@href')
        if len(links)!=1: raise ValueError('One canonical required')
        route=urlsplit(links[0]).path
        for text in strings(data): corpus.setdefault(text,[]).append(route)
    marketing={}; legal={}; properties={}
    for text,paths in corpus.items():
        if any('tajekoztato' in p or 'impresszum' in p or 'feltetel' in p for p in paths) and len(paths)<10:
            legal[text]=paths
        elif all(p.startswith('/property/') for p in paths): properties[text]=paths
        else: marketing[text]=paths
    for name,value in [('marketing',marketing),('legal',legal),('property',properties)]:
        (OUT/('partial-'+name+'-source.json')).write_text(json.dumps(value,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps({name:len(value) for name,value in [('marketing',marketing),('legal',legal),('property',properties)]}))

def inventory_index():
    manifest=json.loads((OUT/'manifest.json').read_text(encoding='utf-8'))
    result={}
    for row in manifest:
        tree=html.fromstring((OUT/row['file']).read_bytes())
        scripts=tree.xpath('//script[@id="harmat-sales-manager-front-js-before"]/text()')
        data={'items':{}}
        if scripts:
            prefix='window.harmatSalesFront='
            source=scripts[0].strip()
            if not source.startswith(prefix):raise ValueError('Unexpected front data prefix')
            data,end=json.JSONDecoder().raw_decode(source[len(prefix):])
            if not source[len(prefix)+end:].lstrip().startswith(';'):raise ValueError('Unexpected front data suffix')
        result[urlsplit(row['url']).path]=data
    (OUT/'front-data.json').write_text(json.dumps(result,ensure_ascii=False),encoding='utf-8')
    print('Parsed public front data without executing any website script:',len(result))

if __name__=='__main__':
    if sys.argv[1:]==['--index-saved']: partial_index()
    elif sys.argv[1:]==['--inventory-only']: inventory_index()
    elif not sys.argv[1:]: main()
    else: raise SystemExit('No arguments or --index-saved only')
