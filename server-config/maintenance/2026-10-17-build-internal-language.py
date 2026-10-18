"""Deterministic reviewed local dictionary merge; no server or external translator."""
import argparse
import hashlib
import json
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[2]
DIR=ROOT/'wp-mu-plugins/harmat-internal-language'
COVERAGE=DIR/'coverage-fixes.zh-Hans.json'
PARTS=[DIR/'marketing.zh-Hans.json',DIR/'legal.zh-Hans.json',DIR/'property.zh-Hans.json',DIR/'dynamic.zh-Hans.json',ROOT/'server-config/previews/chinese-switch/zh-Hans.json',COVERAGE]

def unique(pairs):
    result={}
    for key,value in pairs:
        if key in result:raise ValueError('Duplicate exact key: '+key)
        result[key]=value
    return result

def build():
    result={};conflicts=[]
    for part in PARTS:
        data=json.loads(part.read_text(encoding='utf-8'),object_pairs_hook=unique)
        for key,value in data.items():
            if not isinstance(key,str) or not isinstance(value,str) or key!=key.strip() or not key or not value:
                raise ValueError('Nonempty exact trimmed string dictionary required')
            if re.match(r'^Az A[1-4]-(F|[1-4])-L[0-9]+ a Harmat Lakópark ',key):
                raise ValueError('Live numeric paragraph must use current-data parser, not dictionary')
            if key in result and result[key]!=value:conflicts.append(key)
            if part==COVERAGE and key in result:continue
            result[key]=value
    blob=(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode('utf-8')
    return blob,conflicts

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--write',action='store_true');args=p.parse_args()
    blob,conflicts=build()
    if args.write:(DIR/'zh-Hans.json').write_bytes(blob)
    print(json.dumps({'entries':len(json.loads(blob)),'bytes':len(blob),'sha256':hashlib.sha256(blob).hexdigest(),'review_conflicts':conflicts},ensure_ascii=True))
