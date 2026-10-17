"""Bounded no-JS/no-submit postrelease crawl of the observed public sitemap URLs."""
import hashlib
from html.parser import HTMLParser
import json
from pathlib import Path
import urllib.request

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'outputs/2026-10-17-internal-language'

class Metadata(HTMLParser):
    def __init__(self):super().__init__();self.canonical=[];self.description=[];self.title=[];self.in_title=False;self.lang=None
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='html':self.lang=a.get('lang')
        if tag=='link' and a.get('rel')=='canonical':self.canonical.append(a.get('href'))
        if tag=='meta' and a.get('name')=='description':self.description.append(a.get('content'))
        if tag=='title':self.in_title=True
    def handle_endtag(self,tag):
        if tag=='title':self.in_title=False
    def handle_data(self,data):
        if self.in_title:self.title.append(data)
    def signature(self):return [self.lang,self.canonical,self.description,self.title]

def main():
    manifest=json.loads((OUT/'manifest.json').read_text());proof=[]
    for row in manifest:
        req=urllib.request.Request(row['url'],headers={'User-Agent':'Harmat-readonly-language-postrelease/1.0'})
        with urllib.request.urlopen(req,timeout=30) as r:
            data=r.read(2*1024*1024+1)
            if r.status!=200 or r.geturl()!=row['url']or len(data)>2*1024*1024:raise RuntimeError('Public HTTP/identity/size mismatch')
        if data.count(b'id="harmat-internal-language"')!=1 or data.count(b'id="h22-offer-form"')!=1 or b'Fatal error:' in data:raise RuntimeError('Public native/switch boundary')
        before=Metadata();before.feed((OUT/row['file']).read_text(encoding='utf-8'))
        after=Metadata();after.feed(data.decode('utf-8'))
        if before.signature()!=after.signature():raise RuntimeError('Default original SEO/locale changed: '+row['url'])
        proof.append({'url':row['url'],'status':200,'original_seo_unchanged':True,'one_switch':True,'native_form':True,'sha256':hashlib.sha256(data).hexdigest()})
        if len(proof)%25==0:print('verified',len(proof),'/',len(manifest),flush=True)
    (OUT/'postrelease-audit.json').write_text(json.dumps({'status':'PASS','pages':proof,'scope':'HTTP_SOURCE_METADATA_NO_JAVASCRIPT_NO_FORM_SUBMISSION'},indent=2),encoding='utf-8')
    print(json.dumps({'status':'PASS','pages':len(proof),'seo_unchanged':True,'native_form':True,'one_switch':True}))

if __name__=='__main__':main()
