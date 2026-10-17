"""Fresh sequential public snapshots for Chinese QA; no JS or form submission."""
import hashlib
from html.parser import HTMLParser
import importlib.util
import json
from pathlib import Path
import xml.etree.ElementTree as ET
from urllib.parse import urlsplit

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('chinese_audit_corpus',HERE/'2026-10-17-collect-language-corpus.py')
C=importlib.util.module_from_spec(spec);spec.loader.exec_module(C)
OUT=C.ROOT/'outputs/2026-10-17-chinese-audit/fresh'

class Metadata(HTMLParser):
    def __init__(self):super().__init__();self.canonical=[];self.lang=None;self.titles=[];self.in_title=False
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='html':self.lang=a.get('lang')
        if tag=='link' and a.get('rel')=='canonical':self.canonical.append(a.get('href'))
        if tag=='title':self.in_title=True
    def handle_endtag(self,tag):
        if tag=='title':self.in_title=False
    def handle_data(self,value):
        if self.in_title:self.titles.append(value)

def main():
    if (OUT/'manifest.json').exists():raise RuntimeError('Refuse overwriting completed fresh audit; choose a new owned run directory')
    OUT.mkdir(parents=True,exist_ok=True)
    urls=[]
    for loc in ET.fromstring(C.fetch('https://harmat22.hu/sitemap_index.xml')).findall('s:sitemap/s:loc',C.NS):
        if loc.text.endswith(('page-sitemap.xml','property-sitemap.xml')):
            urls.extend(e.text for e in ET.fromstring(C.fetch(loc.text)).findall('s:url/s:loc',C.NS))
    urls=sorted(set(urls)|{'https://harmat22.hu/'})
    if not 120<=len(urls)<=200:raise RuntimeError('Unexpected public page count')
    rows=[]
    for url in urls:
        p=urlsplit(url)
        if any(p.path.lower().startswith('/'+s) for s in ('sales','agent','client','customer','ugyfel','lawyer','wp-admin','wp-json','wp-login')):
            raise RuntimeError('Private page in public sitemap; do not fetch')
        data=C.fetch(url);meta=Metadata();meta.feed(data.decode('utf-8'))
        if meta.canonical!=[url] or meta.lang!='hu':raise RuntimeError('Canonical/default-language mismatch: '+p.path)
        if data.count(b'id="h22-offer-form"')!=1 or data.count(b'id="harmat-internal-language"')!=1 or b'Fatal error:' in data:
            raise RuntimeError('Native form/language boundary: '+p.path)
        filename=hashlib.sha256(url.encode()).hexdigest()[:16]+'.html'
        (OUT/filename).write_bytes(data)
        rows.append({'url':url,'file':filename,'sha256':hashlib.sha256(data).hexdigest(),'status':200,'default_hu':True,'canonical':url,'native_form':True,'one_switch':True})
        if len(rows)%25==0:print('fresh public pages',len(rows),'/',len(urls),flush=True)
    (OUT/'manifest.json').write_text(json.dumps(rows,ensure_ascii=True,indent=2),encoding='utf-8')
    print(json.dumps({'status':'PASS','pages':len(rows),'scope':'READONLY_PUBLIC_HTTP_NO_JS_NO_SUBMIT','manifest':str(OUT/'manifest.json')}),flush=True)

if __name__=='__main__':main()
