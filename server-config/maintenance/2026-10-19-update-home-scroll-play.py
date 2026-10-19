"""Keep homepage video playing while scrolling. Default offline; exact source gate."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import subprocess
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('scroll_prior_transport', HERE/'2026-10-19-update-home-tv-video.py')
V = importlib.util.module_from_spec(spec)
spec.loader.exec_module(V)
D, H, Q, T = V.D, V.H, V.Q, V.T
require, sha = V.require, V.sha
BASE = '5813e37'
JS = 'home-family-scroll-play.js'
MANIFEST = 'manifest-scroll-play.json'
HOME = D.DIR+'/'+D.MAIN
ASSET = D.DIR+'/'+D.ASSET
PREFIX = H.BACKUPS+'/home-scroll-play-'


def read_local(name):
    return (ROOT/'wp-mu-plugins'/name).read_bytes().replace(b'\r\n', b'\n')


def release():
    old = subprocess.check_output(['git','show',BASE+':wp-mu-plugins/'+D.MAIN],cwd=ROOT)
    require(sha(old)=='b1aab40a51dab20d042a3a966e426fe6ff8cfe10bfe992e852acbd551da82d8b','Exact current home required')
    old_manifest = read_local(D.ASSET+'/manifest.json')
    require(sha(old_manifest)=='21e6ebec9bf0bfcbc273c42b5dc33f07ce96547fa04ccdd0b53faf780d3587b3','Original manifest changed')
    pins = json.loads(old_manifest)
    protected = {D.ASSET+'/'+name:read_local(D.ASSET+'/'+name) for name in pins}
    require(all(sha(protected[D.ASSET+'/'+name])==value for name,value in pins.items()),'Original asset changed')
    protected[D.ASSET+'/manifest.json']=old_manifest
    protected['zz-harmat-home-video-fullscreen.php']=read_local('zz-harmat-home-video-fullscreen.php')
    require(sha(protected['zz-harmat-home-video-fullscreen.php'])=='38383bbaacb876e0b3de4b63d22fc23c6f29679784e3d4cf99e56d9ddd9d4580','Fullscreen source changed')
    js = read_local(D.ASSET+'/'+JS)
    # The JS worker fixture checks the exact source allowlist and real playback behavior.
    require(b'heroInViewport' not in js and b'heroWithinViewport' not in js,'Hero viewport gate retained')
    next_pins = {**pins,JS:sha(js)}
    manifest = (json.dumps(next_pins,indent=2)+'\n').encode()
    require(read_local(D.ASSET+'/'+MANIFEST)==manifest,'New manifest must retain all original pins')
    candidate = old.replace(b' * Version: 1.0.12',b' * Version: 1.0.13',1)
    candidate = candidate.replace(b"$directory.'/manifest.json'",("$directory.'/"+MANIFEST+"'").encode(),1)
    candidate = candidate.replace(sha(old_manifest).encode(),sha(manifest).encode(),1)
    candidate = candidate.replace(b"$pins['home-family.js']))",("$pins['home-family.js'],$pins['"+JS+"']))").encode(),1)
    candidate = candidate.replace(b"array('pannellum.js','home-family.js')",("array('pannellum.js','"+JS+"')").encode(),1)
    require(read_local(D.MAIN)==candidate,'Only exact controller version/manifest/script selection edits allowed')
    edits = {ASSET+'/'+JS:(None,js),ASSET+'/'+MANIFEST:(None,manifest),HOME:(old,candidate)}
    helpers = {name:(HERE/name).read_bytes().replace(b'\r\n',b'\n') for name in (D.TEST,D.CHECKER)}
    recorded = {p:{'old':None if a is None else sha(a),'new':sha(b)} for p,(a,b) in edits.items()}
    digest = sha(Q.P.encoded({'sources':recorded,'protected':{p:sha(b) for p,b in protected.items()},'helpers':{p:sha(b) for p,b in helpers.items()}}))
    return edits,helpers,protected,recorded,digest


def state(r, protected):
    for name,blob in protected.items():
        require(r.read(D.DIR+'/'+name)==blob,'Protected homepage asset changed: '+name)
    value = Q.snapshot(r)
    value['sources'].pop(HOME,None)
    for path in (Q.TARGET_A,Q.TARGET_N):value['sources'][path]=sha(r.read(path))
    return value


def public(r, baseline, protected, edits, active):
    files = tuple(protected)+(D.ASSET+'/'+JS,) if active else tuple(protected)
    hashes = {name:sha(blob) for name,blob in protected.items()}
    hashes[D.ASSET+'/'+JS]=sha(edits[ASSET+'/'+JS][1])
    result = D.public_proof(r,files,hashes,baseline.decode('utf-8'))
    body,mime = Q.get(D.URL)
    text = body.decode('utf-8')
    expected = JS if active else 'home-family.js'
    other = 'home-family.js' if active else JS
    require('text/html' in mime and text.count('/'+expected+'?ver=')==1 and '/'+other+'?ver=' not in text,'Wrong playback runtime selected')
    require(all(text.count(marker)==1 for marker in V.MARKERS),'Fullscreen resource changed')
    T.V.policy(text)
    for route in ('/lakaskereso/','/property/a1-f-l1/','/elerhetosegeink/','/virtualis-lakasvalaszto-a1-epulet/'):
        body,mime=Q.get(Q.P.ORIGIN+route)
        require('text/html' in mime and ('/'+JS+'?ver=').encode() not in body,'Playback script leaked outside homepage')
    result['scroll_playback']='continues_in_visible_document' if active else 'prior_viewport_pause'
    return result


def restore(r, edits, meta):
    # Restore the controller first; retained original assets make this immediately safe.
    for path in reversed(tuple(edits)):
        old,new=edits[path]
        if Q.P.absent(r,path):require(old is None,'Existing original missing');continue
        current=r.read(path)
        require(current in (old,new),'Foreign source retained; dependencies not removed')
        if current==old:continue
        if old is None:r.remove_owned(path,new)
        else:Q.replace(r,path,new,old,meta[path])
    r.purge()


def operate(bundle, backup=None, reverse=False):
    edits,helpers,protected,pins,digest=bundle
    fresh=backup is None
    backup=backup or PREFIX+uuid.uuid4().hex
    require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact private backup required')
    r=H.Remote();locked=attempted=False
    try:
        r.directory(H.BACKUPS);r.directory(D.DIR);r.directory(ASSET);r.mkdir(D.LOCK);locked=True
        if fresh:
            home_meta=Q.metadata(r,HOME,edits[HOME][0]);meta={p:home_meta for p in edits}
            require(all(Q.P.absent(r,p) for p in edits if p!=HOME),'Versioned asset path already exists')
            before=state(r,protected)
            baseline,mime=Q.get(D.URL)
            require('text/html' in mime and ('/'+JS+'?ver=').encode() not in baseline,'Unexpected baseline')
            r.mkdir(backup);r.mkdir(backup+'/'+D.ASSET)
            for name,blob in protected.items():
                if name.startswith(D.ASSET+'/'):r.write_new(backup+'/'+name,blob)
            for path,(old,new) in edits.items():
                if path==HOME:
                    r.write_new(backup+'/original-home.php',old);r.lint(backup+'/original-home.php')
                    r.write_new(backup+'/candidate-home.php',new);r.lint(backup+'/candidate-home.php')
                else:r.write_new(backup+'/'+D.ASSET+'/'+Path(path).name,new)
            for name,blob in helpers.items():r.write_new(backup+'/'+name,blob);r.lint(backup+'/'+name)
            r.write_new(backup+'/baseline.html',baseline)
            saved={'pins':pins,'digest':digest,'metadata':meta,'before':before,'baseline':sha(baseline)}
            D.write_json(r,backup+'/manifest.json',saved)
            fixture=json.loads(r.run(H.PHP+' '+shlex.quote(backup+'/'+D.TEST)+' '+shlex.quote(backup+'/candidate-home.php')))
            require(fixture.get('status')=='PASS' and fixture.get('checks',0)>=76,'Staged homepage fixture failed')
            filters=D.validate_native(r,backup)
            require(state(r,protected)==before,'Protected state changed before activation')
            require(Q.metadata(r,HOME,edits[HOME][0])==home_meta,'Controller changed before activation')
            D.write_json(r,backup+'/preflight.json',{'controller':fixture,'filters':filters})
            print(json.dumps({'status':'PRIVATE_BACKUP_READY','backup':backup,'aggregate':digest}),flush=True)
            attempted=True
            for path,(old,new) in edits.items():Q.replace(r,path,old,new,meta[path])
            r.purge()
        else:
            r.directory(backup,private=True)
            saved=json.loads(r.read(backup+'/manifest.json',private=True))
            require(saved['pins']==pins and saved['digest']==digest,'Saved release differs')
            meta,before=saved['metadata'],saved['before']
            baseline=r.read(backup+'/baseline.html',private=True)
            require(sha(baseline)==saved['baseline'],'Saved baseline changed')
            require(r.read(backup+'/original-home.php',private=True)==edits[HOME][0],'Saved original changed')
            require(r.read(backup+'/candidate-home.php',private=True)==edits[HOME][1],'Saved candidate changed')
            for name,blob in helpers.items():require(r.read(backup+'/'+name,private=True)==blob,'Saved checker changed')
            for path,(_,new) in edits.items():require(Q.metadata(r,path,new)==tuple(meta[path]),'Released source ownership changed')
            if reverse:restore(r,edits,meta)
        for path,(old,new) in edits.items():
            expected=old if reverse else new
            require(Q.P.absent(r,path) if expected is None else Q.metadata(r,path,expected)==tuple(meta[path]),'Final source state differs')
        proof=public(r,baseline,protected,edits,not reverse)
        filters=D.validate_native(r,backup)
        require(state(r,protected)==before,'Protected state drift; no customer data recovery permitted')
        if fresh:D.write_json(r,backup+'/release.json',{'public':proof,'filters':filters})
        print(json.dumps({'status':'RELEASED' if fresh else 'ROLLED_BACK_SOURCE_ONLY' if reverse else 'VERIFIED','backup':backup,'aggregate':digest,'proof':proof,'filters':filters}),flush=True)
    except BaseException:
        if attempted:
            restore(r,edits,meta)
            print(json.dumps({'status':'OWNED_SOURCES_ROLLED_BACK','backup':backup}),flush=True)
        raise
    finally:
        try:
            if locked:r.sftp.rmdir(D.LOCK)
        finally:r.close()


def main():
    parser=argparse.ArgumentParser(description=__doc__);mode=parser.add_mutually_exclusive_group()
    mode.add_argument('--deploy',action='store_true');mode.add_argument('--verify');mode.add_argument('--rollback')
    parser.add_argument('--expect');args=parser.parse_args();bundle=release()
    if not any((args.deploy,args.verify,args.rollback)):
        print(json.dumps({'mode':'OFFLINE_NO_NETWORK','aggregate':bundle[4],'pins':bundle[3]},indent=2));return
    require(args.expect==bundle[4],'Exact reviewed aggregate required before connecting')
    operate(bundle,args.verify or args.rollback,bool(args.rollback))


if __name__=='__main__':main()
