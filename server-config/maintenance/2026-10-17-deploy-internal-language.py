"""Fail-closed new-MU administrator preview. Does not enable public Chinese."""
import argparse
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import shlex
import uuid

ROOT=Path(__file__).resolve().parents[2]
MAINT=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('language_previous_release',MAINT/'2026-10-17-deploy-project-family.py')
D=importlib.util.module_from_spec(spec);spec.loader.exec_module(D)
H=D.H
DIR=H.LIVE+'/wp-content/mu-plugins'
ASSET='harmat-internal-language'
MAIN='zz-harmat-internal-language.php'
FILES=tuple(ASSET+'/'+n for n in ('runtime.js','runtime.css','bootstrap.js','patterns.js','zh-Hans.json'))+(MAIN,)
TEST='2026-10-17-test-internal-language-scope.php'
PREFIX=H.BACKUPS+'/internal-language-preview-'
LOCK=H.BACKUPS+'/.internal-language-preview.lock'

def require(v,message):
    if not v: raise RuntimeError(message)

def sha(data):return hashlib.sha256(data).hexdigest()

def bundle():
    blobs={n:(ROOT/'wp-mu-plugins'/n).read_bytes().replace(b'\r\n',b'\n') for n in FILES}
    require(b'function harmat_internal_language_public_enabled() { return false; }' in blobs[MAIN], 'Preview must not enable public language')
    for n in FILES[:-1]:
        require(sha(blobs[n]).encode() in blobs[MAIN],'Unpinned asset '+n)
    blobs[TEST]=(MAINT/TEST).read_bytes().replace(b'\r\n',b'\n')
    pins={n:sha(v) for n,v in blobs.items()}
    return blobs,pins,sha(json.dumps(pins,sort_keys=True,separators=(',',':')).encode())

def snapshot(r):
    result=D.snapshot(r)
    result['project_family']={n:sha(r.read(DIR+'/'+n)) for n in D.FILES}
    return result

def health(r):
    out=[]
    for p in ('/','/harmat-lakopark/','/magunkrol/','/lakaskereso/','/property/a1-f-l1/','/virtualis-lakasvalaszto/','/virtualis-lakasvalaszto-elso-utem/','/virtualis-lakasvalaszto-a2-epulet/','/galeria/','/elerhetosegeink/','/epitesi-naplo/'):
        data,mime=D.http('https://harmat22.hu'+p)
        require('text/html' in mime and b'h22-offer-modal' in data and b'Fatal error:' not in data,'Native health '+p)
        require(b'id="harmat-internal-language"' not in data and b'/harmat-internal-language/' not in data,'Preview publicly exposed '+p)
        out.append(p)
    # Anonymous query must never grant preview permission.
    data,_=D.http('https://harmat22.hu/?h22_language_test=1')
    require(b'id="harmat-internal-language"' not in data,'Anonymous preview exposed')
    with D.T.bounded_shared_requests(): inventory=D.I.public_check(r)
    return {'unchanged_public_routes':out,'anonymous_preview_absent':True,'inventory':inventory}

def deploy(blobs,pins,digest):
    r=H.Remote(); backup=PREFIX+uuid.uuid4().hex; locked=False; created=[]; stages={}; before=None
    try:
        r.directory(DIR);r.directory(H.BACKUPS);r.mkdir(LOCK);locked=True
        require(all(D.absent(r,DIR+'/'+n) for n in FILES) and D.absent(r,DIR+'/'+ASSET),'New-path collision')
        r.mkdir(backup);r.mkdir(backup+'/'+ASSET)
        before=snapshot(r)
        manifest={'status':'ADMINISTRATOR_PREVIEW_ONLY','bundle':digest,'pins':pins,'before':before,'original_absent':list(FILES)}
        D.write_json(r,backup+'/manifest.json',manifest)
        for n in D.FILES:
            r.write_new(backup+'/prior-project-'+sha(n.encode())[:12]+'.bin',r.read(DIR+'/'+n))
        for name,data in blobs.items():
            r.write_new(backup+'/'+name,data)
            if name.endswith('.php'):r.lint(backup+'/'+name)
        fixture=r.run(H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/'+MAIN))
        require('TOTAL: 2167 checks, 372 cases, 0 failures;' in fixture,'Preview PHP scope fixture failed')
        r.write_new(backup+'/scope-proof.txt',fixture.encode())
        require(snapshot(r)==before,'Protected state drift before activation')
        r.sftp.mkdir(DIR+'/'+ASSET,0o755);r.sftp.chmod(DIR+'/'+ASSET,0o755)
        for n in FILES:
            target=DIR+'/'+n; stage=target+'.'+uuid.uuid4().hex+'.tmp';stages[stage]=blobs[n]
            r.write_new(stage,blobs[n])
            if n.endswith('.php'):r.lint(stage)
            require(sha(r.read(stage))==pins[n] and D.absent(r,target),'Stage/collision mismatch')
            r.sftp.chmod(stage,0o644);r.sftp.posix_rename(stage,target);stages.pop(stage);created.append(n)
            if n.endswith('.php'):r.lint(target)
        r.purge()
        for n in FILES:require(sha(r.read(DIR+'/'+n))==pins[n],'Formal hash drift')
        proof=health(r)
        require(snapshot(r)==before,'Protected state drift; do not restore customer data')
        D.write_json(r,backup+'/release-proof.json',proof)
        print(json.dumps({'status':'ADMIN_PREVIEW_STAGED','backup':backup,'bundle':digest,'proof':proof}),flush=True)
    except BaseException:
        for path,data in stages.items():
            if not D.absent(r,path):r.remove_owned(path,data)
        for n in reversed(created):
            require(sha(r.read(DIR+'/'+n))==pins[n],'Rollback refuses foreign edit')
            r.remove_owned(DIR+'/'+n,blobs[n])
        if not D.absent(r,DIR+'/'+ASSET):r.sftp.rmdir(DIR+'/'+ASSET)
        r.purge()
        print(json.dumps({'status':'OWNED_PREVIEW_ROLLED_BACK','backup':backup}),flush=True)
        raise
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()

def verify(backup,pins):
    require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact backup required')
    r=H.Remote()
    try:
        saved=json.loads(r.read(backup+'/manifest.json',private=True))
        require(saved['pins']==pins,'Candidate changed')
        for n in FILES:
            require(sha(r.read(DIR+'/'+n))==pins[n],'Preview file drift')
            if n.endswith('.php'):r.lint(DIR+'/'+n)
        require(snapshot(r)==saved['before'],'Protected state drift')
        print(json.dumps({'status':'VERIFIED_ADMIN_PREVIEW','proof':health(r)}))
    finally:r.close()

def refresh(previous,blobs,pins,digest):
    require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',previous),'Exact prior preview backup required')
    r=H.Remote();backup=PREFIX+uuid.uuid4().hex;locked=False;updated=[];stage=None;stage_data=None
    old=None
    try:
        r.directory(DIR);r.directory(H.BACKUPS);r.mkdir(LOCK);locked=True
        old=json.loads(r.read(previous+'/manifest.json',private=True))
        for n in FILES:require(sha(r.read(DIR+'/'+n))==old['pins'][n],'Prior preview drift '+n)
        before=snapshot(r);r.mkdir(backup);r.mkdir(backup+'/'+ASSET)
        prior={n:r.read(DIR+'/'+n) for n in FILES}
        r.mkdir(backup+'/original');r.mkdir(backup+'/original/'+ASSET)
        for n,data in prior.items():r.write_new(backup+'/original/'+n,data)
        manifest={'status':'ADMINISTRATOR_PREVIEW_ONLY','bundle':digest,'pins':pins,'before':before,'previous':previous,'prior_pins':old['pins']}
        D.write_json(r,backup+'/manifest.json',manifest)
        for n,data in blobs.items():
            r.write_new(backup+'/'+n,data)
            if n.endswith('.php'):r.lint(backup+'/'+n)
        fixture=r.run(H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/'+MAIN))
        require('TOTAL: 2167 checks, 372 cases, 0 failures;' in fixture,'Private refreshed scope fixture')
        r.write_new(backup+'/scope-proof.txt',fixture.encode())
        require(snapshot(r)==before,'Protected drift before preview refresh')
        for n in FILES:
            if prior[n]==blobs[n]:continue
            target=DIR+'/'+n;stage=target+'.'+uuid.uuid4().hex+'.tmp'
            stage_data=blobs[n];r.write_new(stage,stage_data);
            if n.endswith('.php'):r.lint(stage)
            require(sha(r.read(target))==sha(prior[n]),'Foreign preview edit')
            r.sftp.chmod(stage,0o644);r.sftp.posix_rename(stage,target);stage=None;stage_data=None;updated.append(n)
            if n.endswith('.php'):r.lint(target)
        r.purge();proof=health(r)
        require(snapshot(r)==before,'Protected drift after preview refresh')
        D.write_json(r,backup+'/release-proof.json',proof)
        print(json.dumps({'status':'ADMIN_PREVIEW_REFRESHED','backup':backup,'bundle':digest,'proof':proof}),flush=True)
    except BaseException:
        if stage and not D.absent(r,stage):r.remove_owned(stage,stage_data)
        for n in reversed(updated):
            require(sha(r.read(DIR+'/'+n))==pins[n],'Refusing foreign rollback')
            target=DIR+'/'+n;tmp=target+'.'+uuid.uuid4().hex+'.tmp'
            r.write_new(tmp,prior[n]);
            if n.endswith('.php'):r.lint(tmp)
            r.sftp.chmod(tmp,0o644);r.sftp.posix_rename(tmp,target)
            if n.endswith('.php'):r.lint(target)
        if updated:r.purge()
        raise
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--stage',action='store_true');p.add_argument('--expect');p.add_argument('--verify');p.add_argument('--refresh')
    args=p.parse_args();blobs,pins,digest=bundle()
    if args.stage:
        require(args.expect==digest,'Exact reviewed bundle required');deploy(blobs,pins,digest)
    elif args.refresh:
        require(args.expect==digest,'Exact reviewed refreshed bundle required');refresh(args.refresh,blobs,pins,digest)
    elif args.verify:verify(args.verify,pins)
    else: print(json.dumps({'mode':'OFFLINE_NO_WRITES','bundle':digest,'pins':pins},indent=2))
