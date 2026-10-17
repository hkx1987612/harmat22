"""Bounded language-control presentation refresh; no business or form writes."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import uuid

MAINT=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('language_header_public',MAINT/'2026-10-17-publish-internal-language.py')
P=importlib.util.module_from_spec(spec);spec.loader.exec_module(P)
D=P.D
PREFIX=D.H.BACKUPS+'/internal-language-header-'
PREVIOUS=D.H.BACKUPS+'/internal-language-public-d9e6c64ddc5c49669abc98efef4cb8af'
LOCK=D.H.BACKUPS+'/.internal-language-header.lock'
CHANGED=(D.ASSET+'/runtime.css',D.ASSET+'/bootstrap.js',D.MAIN)
OLD_VERSION=b'Version: 1.0.0'
NEW_VERSION=b'Version: 1.0.1'
PRIOR={
    D.ASSET+'/runtime.css':'0b5c3f67bc915ae0e1216ca540e3ee675874b568fb483959c49840f5488659b3',
    D.ASSET+'/bootstrap.js':'4d1734173954a4b7bcdc67049d1e7fbb440f0a8da24f0d8732c4b38bf495ad7d',
    D.MAIN:'8306610a1cf5f589397b8c9d208b95abbc25c92daab3e2863766193273a23e04',
}

def bundle():
    blobs=P.files();pins={n:D.sha(data) for n,data in blobs.items()}
    D.require(b'function harmat_internal_language_public_enabled() { return true; }' in blobs[D.MAIN],'Public flag changed')
    for n in D.FILES[:-1]:D.require(pins[n].encode() in blobs[D.MAIN],'Missing controller asset pin '+n)
    digest=D.sha(json.dumps(pins,sort_keys=True,separators=(',',':')).encode())
    return blobs,pins,digest

def match_previous(r,blobs,pins):
    original=json.loads(r.read(PREVIOUS+'/manifest.json',private=True))
    old={n:r.read(D.DIR+'/'+n) for n in D.FILES}
    for n in D.FILES:
        D.require(D.sha(old[n])==original['pins'][n],'Prior release drift '+n)
        if n not in CHANGED:D.require(old[n]==blobs[n],'Unexpected translation/runtime/data change '+n)
    for n,pin in PRIOR.items():D.require(D.sha(old[n])==pin,'Unexpected prior presentation '+n)
    expected=old[D.MAIN]
    for n in CHANGED[:-1]:expected=expected.replace(PRIOR[n].encode(),pins[n].encode(),1)
    expected=expected.replace(OLD_VERSION,NEW_VERSION,1)
    D.require(blobs[D.MAIN]==expected,'Only two pins and version may change in controller')
    return old

def pure_native(r,backup):
    body=(MAINT/'2026-10-16-native-offer-readonly.php').read_bytes().replace(b'\r\n',b'\n')
    target=backup+'/native-offer-readonly.php.txt'
    r.write_new(target,body);r.lint(target)
    offer=json.loads(r.run(D.H.WP+' eval-file '+shlex.quote(target)))
    spam=json.loads(r.run(D.H.WP+' eval '+shlex.quote(D.D.I.HEALTH.RUNTIME_CHECK)))
    D.require(offer.get('status')=='PASS' and offer.get('checks')==35,'Pure native offer regression')
    D.require(spam.get('status')=='PASS' and spam.get('checks')==17,'Pure anti-spam regression')
    return {'offer':offer,'antispam':spam}

def replace(r,n,data,expected):
    target=D.DIR+'/'+n;tmp=target+'.'+uuid.uuid4().hex+'.tmp'
    r.write_new(tmp,data)
    try:
        if n.endswith('.php'):r.lint(tmp)
        D.require(D.sha(r.read(tmp))==D.sha(data) and r.read(target)==expected,'Stage/preimage identity '+n)
        r.sftp.chmod(tmp,0o644);r.sftp.posix_rename(tmp,target)
    finally:
        if not D.D.absent(r,tmp):r.remove_owned(tmp,data)

def deploy(blobs,pins,digest):
    r=D.H.Remote();backup=PREFIX+uuid.uuid4().hex;locked=False;updated=[];old=None
    try:
        r.directory(D.H.BACKUPS);r.mkdir(LOCK);locked=True
        old=match_previous(r,blobs,pins);before=D.snapshot(r)
        r.mkdir(backup);r.mkdir(backup+'/original');r.mkdir(backup+'/original/'+D.ASSET);r.mkdir(backup+'/'+D.ASSET)
        for n in CHANGED:r.write_new(backup+'/original/'+n,old[n])
        # Scope fixture validates all pinned siblings in the private candidate.
        for n,data in blobs.items():r.write_new(backup+'/'+n,data)
        fixture=(MAINT/D.TEST).read_bytes().replace(b'\r\n',b'\n')
        r.write_new(backup+'/'+D.TEST,fixture);r.lint(backup+'/'+D.TEST);r.lint(backup+'/'+D.MAIN)
        scope=r.run(D.H.PHP+' '+shlex.quote(backup+'/'+D.TEST)+' '+shlex.quote(backup+'/'+D.MAIN)+' public')
        D.require('PUBLIC TOTAL: 306 checks, 52 cases, 0 failures' in scope,'Public scope regression')
        r.write_new(backup+'/public-scope-proof.txt',scope.encode())
        native=pure_native(r,backup)
        D.require(D.snapshot(r)==before,'Protected drift before presentation refresh')
        D.D.write_json(r,backup+'/manifest.json',{'pins':pins,'before':before,'bundle':digest,'prior_pins':{n:D.sha(old[n]) for n in D.FILES},'previous':PREVIOUS})
        for n in CHANGED:
            replace(r,n,blobs[n],old[n]);updated.append(n)
            if n.endswith('.php'):r.lint(D.DIR+'/'+n)
        r.purge();proof=P.public_health(r,pins)
        D.require(D.snapshot(r)==before,'Protected drift after refresh; never restore business tables')
        D.D.write_json(r,backup+'/release-proof.json',{'public':proof,'native':native})
        print(json.dumps({'status':'HEADER_REFRESH_RELEASED','backup':backup,'bundle':digest,'proof':proof,'native':native}),flush=True)
    except BaseException:
        for n in reversed(updated):
            replace(r,n,old[n],blobs[n])
            if n.endswith('.php'):r.lint(D.DIR+'/'+n)
        if updated:r.purge()
        print(json.dumps({'status':'OWNED_PRESENTATION_ROLLED_BACK' if updated else 'STOPPED_BEFORE_ACTIVATION','backup':backup}),flush=True)
        raise
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()

def verify(backup):
    D.require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact header backup required')
    r=D.H.Remote()
    try:
        saved=json.loads(r.read(backup+'/manifest.json',private=True))
        for n,pin in saved['pins'].items():D.require(D.sha(r.read(D.DIR+'/'+n))==pin,'Formal hash drift '+n)
        r.lint(D.DIR+'/'+D.MAIN);proof=P.public_health(r,saved['pins'])
        D.require(D.snapshot(r)==saved['before'],'Protected state drift')
        print(json.dumps({'status':'VERIFIED_HEADER_REFRESH','proof':proof}),flush=True)
    finally:r.close()

def rollback(backup):
    D.require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact header backup required')
    r=D.H.Remote();locked=False
    try:
        r.mkdir(LOCK);locked=True
        saved=json.loads(r.read(backup+'/manifest.json',private=True))
        for n,pin in saved['pins'].items():D.require(D.sha(r.read(D.DIR+'/'+n))==pin,'Refuse foreign rollback '+n)
        before=D.snapshot(r)
        original={n:r.read(backup+'/original/'+n,private=True) for n in CHANGED}
        for n in CHANGED:D.require(D.sha(original[n])==saved['prior_pins'][n],'Original backup identity '+n)
        for n in reversed(CHANGED):
            replace(r,n,original[n],r.read(D.DIR+'/'+n))
            if n.endswith('.php'):r.lint(D.DIR+'/'+n)
        r.purge();proof=P.public_health(r,saved['prior_pins'])
        D.require(D.snapshot(r)==before,'Protected drift during source-only rollback')
        print(json.dumps({'status':'ROLLED_BACK_PRESENTATION_ONLY','proof':proof}),flush=True)
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()

def main():
    p=argparse.ArgumentParser(description=__doc__);action=p.add_mutually_exclusive_group();action.add_argument('--deploy',action='store_true');action.add_argument('--verify');action.add_argument('--rollback');p.add_argument('--expect');a=p.parse_args()
    if a.verify:verify(a.verify)
    elif a.rollback:rollback(a.rollback)
    else:
        blobs,pins,digest=bundle()
        if a.deploy:D.require(a.expect==digest,'Exact reviewed bundle required');deploy(blobs,pins,digest)
        else:print(json.dumps({'mode':'OFFLINE_NO_WRITES','bundle':digest,'pins':pins},indent=2))

if __name__=='__main__':main()
