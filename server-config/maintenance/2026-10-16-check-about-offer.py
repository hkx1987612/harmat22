"""Explicit read-only postrelease form checks; no REST dispatch or mail/lead writes."""
import argparse
import importlib.util
import json
import re
import shlex
from pathlib import Path

spec=importlib.util.spec_from_file_location('about_release',Path(__file__).with_name('2026-10-16-deploy-about-family.py'))
D=importlib.util.module_from_spec(spec); spec.loader.exec_module(D)

def check(backup):
    D.require(re.fullmatch(re.escape(D.PREFIX)+r'[a-f0-9]{32}',backup), 'Exact private release backup required')
    r=D.H.Remote()
    try:
        manifest=json.loads(r.read(backup+'/manifest.json',private=True))
        D.require(D.snapshot(r)==manifest['before'],'Protected baseline drift')
        path=backup+'/offer-readonly.php.txt'
        data=Path(__file__).with_name('2026-10-16-native-offer-readonly.php').read_bytes().replace(b'\r\n',b'\n')
        if D.absent(r,path): r.write_new(path,data)
        D.require(r.read(path,private=True)==data,'Read-only checker identity mismatch'); r.lint(path)
        offer=json.loads(r.run(D.H.WP+' eval-file '+shlex.quote(path)))
        D.require(offer['status']=='PASS' and offer['checks']==35,'Pure offer validation failed')
        antispam=json.loads(r.run(D.H.WP+' eval '+shlex.quote(D.I.HEALTH.RUNTIME_CHECK)))
        D.require(antispam['status']=='PASS' and antispam['checks']==17,'Pure anti-spam check failed')
        pages={}
        for page in ('/property/a1-f-l1/','/virtualis-lakasvalaszto-a2-epulet/'):
            data,mime=D.http('https://harmat22.hu'+page)
            D.require('text/html' in mime and b'id="harmat-about-redesign"' not in data,'About presentation leaked to another page')
            D.require(b'harmat-about-family/about-family.css' not in data,'About CSS leaked to another page')
            pages[page]={'status':200,'about_root_or_css':False}
        D.require(D.snapshot(r)==manifest['before'],'Protected state changed; do not restore business data')
        result={'offer':offer,'antispam':antispam,'other_pages':pages,'protected_unchanged':True}
        target=backup+'/offer-readonly-proof.json'
        if D.absent(r,target): D.write_json(r,target,result)
        print(json.dumps(result))
    finally:
        r.close()

if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__); parser.add_argument('--backup')
    args=parser.parse_args()
    if args.backup: check(args.backup)
    else: print('OFFLINE_NO_WRITES: supply the exact successful release backup to run read-only checks')
