"""Lower desktop hero copy64px only. Default offline; source-only recovery."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import subprocess
import uuid

HERE=Path(__file__).resolve().parent
ROOT=HERE.parents[1]
spec=importlib.util.spec_from_file_location('hero_lower_previous',HERE/'2026-10-19-deploy-home-assistant-inline.py')
V=importlib.util.module_from_spec(spec);spec.loader.exec_module(V)
D,H,Q=V.D,V.H,V.Q
require,sha=V.require,V.sha
TARGET=V.TARGET
PREFIX=H.BACKUPS+'/home-hero-lower-'
RULE=b'#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-copy{position:relative;top:64px}'
ANCHOR=b'#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-bottom{left:48px;right:48px}'


def release():
    old=subprocess.check_output(['git','show','c43260f:wp-mu-plugins/'+V.MAIN],cwd=ROOT)
    require(sha(old)=='449ffd8868549b5eba50a15e84efa9883517b828e678b96b987ca9ebcc1a9f71','Exact current placement module required')
    new=old.replace(b' * Version: 1.0.0',b' * Version: 1.0.1',1).replace(ANCHOR,RULE+ANCHOR,1)
    source,protected,helpers,_,_=V.release()
    require(source==new and new.count(RULE)==1,'Only desktop copy offset and version may change')
    descriptor={'old':sha(old),'new':sha(new),'protected':{p:sha(b) for p,b in protected.items()},'helpers':{p:sha(b) for p,b in helpers.items()}}
    return old,new,protected,helpers,descriptor,sha(Q.P.encoded(descriptor))


def public(r,baseline,protected,active):
    proof=V.public(r,baseline,protected,True)
    body,mime=Q.get(D.URL)
    require('text/html' in mime and body.count(RULE)==int(active),'Desktop copy offset state mismatch')
    proof['hero_copy']='64px_below_center' if active else 'centered'
    return proof


def restore(r,old,new,meta):
    current=r.read(TARGET)
    require(current in (old,new),'Foreign source retained')
    if current==new:Q.replace(r,TARGET,new,old,meta)
    r.purge()


def operate(bundle,backup=None,reverse=False):
    old,new,protected,helpers,descriptor,digest=bundle
    fresh=backup is None;backup=backup or PREFIX+uuid.uuid4().hex
    require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact private backup required')
    r=H.Remote();locked=attempted=False
    try:
        r.directory(H.BACKUPS);r.directory(D.DIR);r.mkdir(D.LOCK);locked=True
        if fresh:
            meta=Q.metadata(r,TARGET,old);before=V.state(r,protected)
            baseline,mime=Q.get(D.URL)
            require('text/html' in mime and RULE not in baseline,'Unexpected baseline')
            r.mkdir(backup)
            for label,blob in (('original.php',old),('candidate.php',new)):
                r.write_new(backup+'/'+label,blob);r.lint(backup+'/'+label)
            for name,blob in helpers.items():r.write_new(backup+'/'+name,blob);r.lint(backup+'/'+name)
            r.write_new(backup+'/baseline.html',baseline)
            D.write_json(r,backup+'/manifest.json',{'descriptor':descriptor,'digest':digest,'metadata':meta,'before':before,'baseline':sha(baseline)})
            fixture=json.loads(r.run(H.PHP+' '+shlex.quote(backup+'/'+V.TEST)+' '+shlex.quote(backup+'/candidate.php')))
            require(fixture.get('status')=='PASS' and fixture.get('checks',0)>=79,'Staged scope fixture failed')
            filters=D.validate_native(r,backup)
            require(V.state(r,protected)==before,'Protected state changed before activation')
            D.write_json(r,backup+'/preflight.json',{'scope':fixture,'filters':filters})
            print(json.dumps({'status':'PRIVATE_BACKUP_READY','backup':backup,'aggregate':digest}),flush=True)
            attempted=True;Q.replace(r,TARGET,old,new,meta);r.purge()
        else:
            r.directory(backup,private=True)
            saved=json.loads(r.read(backup+'/manifest.json',private=True))
            require(saved['descriptor']==descriptor and saved['digest']==digest,'Saved release differs')
            meta,before=saved['metadata'],saved['before'];baseline=r.read(backup+'/baseline.html',private=True)
            require(sha(baseline)==saved['baseline'],'Saved baseline changed')
            for label,blob in (('original.php',old),('candidate.php',new)):
                require(r.read(backup+'/'+label,private=True)==blob,'Saved source changed')
            for name,blob in helpers.items():require(r.read(backup+'/'+name,private=True)==blob,'Saved checker changed')
            require(Q.metadata(r,TARGET,new)==tuple(meta),'Released source ownership changed')
            if reverse:restore(r,old,new,meta)
        require(Q.metadata(r,TARGET,old if reverse else new)==tuple(meta),'Final source differs')
        proof=public(r,baseline,protected,not reverse);filters=D.validate_native(r,backup)
        require(V.state(r,protected)==before,'Protected source/business drift; no customer data recovery')
        if fresh:D.write_json(r,backup+'/release.json',{'public':proof,'filters':filters})
        print(json.dumps({'status':'RELEASED' if fresh else 'ROLLED_BACK_SOURCE_ONLY' if reverse else 'VERIFIED','backup':backup,'aggregate':digest,'proof':proof,'filters':filters}),flush=True)
    except BaseException:
        if attempted:
            restore(r,old,new,meta)
            print(json.dumps({'status':'OWNED_SOURCE_ROLLED_BACK','backup':backup}),flush=True)
        raise
    finally:
        try:
            if locked:r.sftp.rmdir(D.LOCK)
        finally:r.close()


def main():
    p=argparse.ArgumentParser(description=__doc__);m=p.add_mutually_exclusive_group()
    m.add_argument('--deploy',action='store_true');m.add_argument('--verify');m.add_argument('--rollback')
    p.add_argument('--expect');a=p.parse_args();b=release()
    if not any((a.deploy,a.verify,a.rollback)):
        print(json.dumps({'mode':'OFFLINE_NO_NETWORK','aggregate':b[5],'sources':{'old':sha(b[0]),'new':sha(b[1])}},indent=2));return
    require(a.expect==b[5],'Exact reviewed aggregate required before connecting')
    operate(b,a.verify or a.rollback,bool(a.rollback))


if __name__=='__main__':main()
