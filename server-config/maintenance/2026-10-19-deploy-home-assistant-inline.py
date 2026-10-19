"""Homepage-only inline assistant entry. Offline by default; no chat or form sends."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import uuid

HERE=Path(__file__).resolve().parent
ROOT=HERE.parents[1]
spec=importlib.util.spec_from_file_location('inline_previous_transport',HERE/'2026-10-19-update-home-scroll-play.py')
S=importlib.util.module_from_spec(spec);spec.loader.exec_module(S)
D,H,Q=S.D,S.H,S.Q
require,sha=S.require,S.sha
MAIN='zz-harmat-home-assistant-inline.php'
TARGET=D.DIR+'/'+MAIN
TEST='2026-10-19-test-home-assistant-inline.php'
PREFIX=H.BACKUPS+'/home-assistant-inline-'
MARKERS=('id="harmat-home-assistant-inline-style"','id="harmat-home-assistant-inline-script"')


def release():
    source=S.read_local(MAIN)
    require(len(source)<20000 and all(m.encode() in source for m in MARKERS),'Bounded inline presentation required')
    require(not re.search(rb'\b(?:wp_mail|update_option|update_post_meta|wp_insert_post|register_rest_route|wp_remote_\w+)\s*\(',source),'Presentation-only source required')
    edits,_,protected,_,_=S.release()
    protected={**protected,**{path[len(D.DIR)+1:]:new for path,(old,new) in edits.items()}}
    for name in ('zz-harmat-assistant-presentation.php','harmat-assistant-presentation/assistant-presentation.css',
                 'harmat-assistant-presentation/assistant-presentation.js','harmat-assistant-presentation/LICENSE-lucide.txt'):
        protected[name]=S.read_local(name)
    helpers={name:(HERE/name).read_bytes().replace(b'\r\n',b'\n') for name in (TEST,D.CHECKER)}
    descriptor={'source':sha(source),'protected':{p:sha(b) for p,b in protected.items()},'helpers':{p:sha(b) for p,b in helpers.items()}}
    return source,protected,helpers,descriptor,sha(Q.P.encoded(descriptor))


def state(r,protected):
    for path,blob in protected.items():require(r.read(D.DIR+'/'+path)==blob,'Protected asset changed: '+path)
    value=Q.snapshot(r)
    value['sources'].pop(TARGET,None)
    for path in (Q.TARGET_A,Q.TARGET_N):value['sources'][path]=sha(r.read(path))
    return value


def public(r,baseline,protected,active):
    hashes={p:sha(b) for p,b in protected.items()}
    result=D.public_proof(r,tuple(protected),hashes,baseline.decode('utf-8'))
    body,mime=Q.get(D.URL);text=body.decode('utf-8')
    require('text/html' in mime and all(text.count(m)==int(active) for m in MARKERS),'Homepage inline resource state mismatch')
    require('/home-family-scroll-play.js?ver=063358c160bcdbdf' in text,'Current playback runtime lost')
    require(all(text.count(m)==1 for m in S.V.MARKERS),'Fullscreen resource state changed')
    S.T.V.policy(text)
    for route in ('/lakaskereso/','/property/a1-f-l1/','/elerhetosegeink/','/virtualis-lakasvalaszto-a1-epulet/'):
        data,mime=Q.get(Q.P.ORIGIN+route)
        require('text/html' in mime and not any(m.encode() in data for m in MARKERS),'Homepage assistant addon leaked')
    result['assistant_entry']='homepage_inline_resources_only' if active else 'original_floating_resources'
    return result


def restore(r,source):
    if not Q.P.absent(r,TARGET):
        require(r.read(TARGET)==source,'Foreign addon retained')
        r.remove_owned(TARGET,source)
    r.purge()


def operate(bundle,backup=None,reverse=False):
    source,protected,helpers,descriptor,digest=bundle
    fresh=backup is None;backup=backup or PREFIX+uuid.uuid4().hex
    require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact private backup required')
    r=H.Remote();locked=attempted=False
    try:
        r.directory(H.BACKUPS);r.directory(D.DIR);r.mkdir(D.LOCK);locked=True
        if fresh:
            require(Q.P.absent(r,TARGET),'Inline addon path already exists')
            meta=Q.metadata(r,S.HOME,protected[D.MAIN])
            before=state(r,protected)
            baseline,mime=Q.get(D.URL)
            require('text/html' in mime and not any(m.encode() in baseline for m in MARKERS),'Unexpected baseline')
            r.mkdir(backup);r.write_new(backup+'/'+MAIN,source);r.lint(backup+'/'+MAIN)
            for name,blob in helpers.items():r.write_new(backup+'/'+name,blob);r.lint(backup+'/'+name)
            r.write_new(backup+'/baseline.html',baseline)
            saved={'descriptor':descriptor,'digest':digest,'metadata':meta,'before':before,'baseline':sha(baseline),'original_absent':True}
            D.write_json(r,backup+'/manifest.json',saved)
            fixture=json.loads(r.run(H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/'+MAIN)))
            require(fixture.get('status')=='PASS' and fixture.get('checks',0)>=79,'Staged scope fixture failed')
            filters=D.validate_native(r,backup)
            require(state(r,protected)==before and Q.P.absent(r,TARGET),'Protected state changed before activation')
            D.write_json(r,backup+'/preflight.json',{'scope':fixture,'filters':filters})
            print(json.dumps({'status':'PRIVATE_BACKUP_READY','backup':backup,'aggregate':digest}),flush=True)
            attempted=True;Q.replace(r,TARGET,None,source,meta);r.purge()
        else:
            r.directory(backup,private=True)
            saved=json.loads(r.read(backup+'/manifest.json',private=True))
            require(saved['descriptor']==descriptor and saved['digest']==digest and saved['original_absent'] is True,'Saved release differs')
            meta,before=saved['metadata'],saved['before']
            baseline=r.read(backup+'/baseline.html',private=True)
            require(sha(baseline)==saved['baseline'] and r.read(backup+'/'+MAIN,private=True)==source,'Saved backup changed')
            for name,blob in helpers.items():require(r.read(backup+'/'+name,private=True)==blob,'Saved checker changed')
            require(Q.metadata(r,TARGET,source)==tuple(meta),'Released source ownership changed')
            if reverse:restore(r,source)
        require(Q.P.absent(r,TARGET) if reverse else Q.metadata(r,TARGET,source)==tuple(meta),'Final source state differs')
        proof=public(r,baseline,protected,not reverse);filters=D.validate_native(r,backup)
        require(state(r,protected)==before,'Protected source/business drift; no data recovery')
        if fresh:D.write_json(r,backup+'/release.json',{'public':proof,'filters':filters})
        print(json.dumps({'status':'RELEASED' if fresh else 'ROLLED_BACK_SOURCE_ONLY' if reverse else 'VERIFIED','backup':backup,'aggregate':digest,'proof':proof,'filters':filters}),flush=True)
    except BaseException:
        if attempted:
            restore(r,source)
            print(json.dumps({'status':'OWNED_ADDON_ROLLED_BACK','backup':backup}),flush=True)
        raise
    finally:
        try:
            if locked:r.sftp.rmdir(D.LOCK)
        finally:r.close()


def main():
    p=argparse.ArgumentParser(description=__doc__);mode=p.add_mutually_exclusive_group()
    mode.add_argument('--deploy',action='store_true');mode.add_argument('--verify');mode.add_argument('--rollback')
    p.add_argument('--expect');args=p.parse_args();bundle=release()
    if not any((args.deploy,args.verify,args.rollback)):
        print(json.dumps({'mode':'OFFLINE_NO_NETWORK','aggregate':bundle[4],'descriptor':bundle[3]},indent=2));return
    require(args.expect==bundle[4],'Exact reviewed aggregate required before connecting')
    operate(bundle,args.verify or args.rollback,bool(args.rollback))


if __name__=='__main__':main()
