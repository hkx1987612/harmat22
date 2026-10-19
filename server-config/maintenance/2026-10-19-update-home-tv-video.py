"""Natural full-width desktop video and a removable fullscreen addon; no form writes."""
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
spec = importlib.util.spec_from_file_location('tv_home_transport', HERE / '2026-10-19-update-home-video-complete.py')
C = importlib.util.module_from_spec(spec)
spec.loader.exec_module(C)
T, U = C.T, C.U
Q, D, H = T.Q, U.D, U.H
require, sha = U.require, U.sha
BASE = 'a42ba47'
ADDON = 'zz-harmat-home-video-fullscreen.php'
TARGET = D.DIR + '/' + ADDON
PREFIX = H.BACKUPS + '/home-tv-video-'
MARKERS = ('id="harmat-home-video-fullscreen-css"', 'id="harmat-home-video-fullscreen-js"')
ADDON_TEST = '2026-10-19-test-home-video-fullscreen.php'


def release():
    old = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + D.MAIN], cwd=ROOT)
    require(sha(old) == 'e641ee934d125b07bfd05a9680ef438125bb00caf99f8c9257a99a58dabdc4a1', 'Exact current home required')
    new = (ROOT/'wp-mu-plugins'/D.MAIN).read_bytes().replace(b'\r\n', b'\n')
    rule = C.RULE.replace(b'max-height:calc(100svh - 108px)', b'max-height:none')
    require(new.count(rule) == 1 and new.replace(rule, C.RULE, 1).replace(b' * Version: 1.0.12', b' * Version: 1.0.11', 1) == old,
            'Only desktop height cap and version may change')
    addon = (ROOT/'wp-mu-plugins'/ADDON).read_bytes().replace(b'\r\n', b'\n')
    require(len(addon) < 20000 and all(m.encode() in addon for m in MARKERS), 'Reviewed bounded addon required')
    files, blobs, hashes, _ = D.bundle()
    edits = {U.TARGET:(old,new), TARGET:(None,addon)}
    helpers = {D.CHECKER:blobs[D.CHECKER], ADDON_TEST:(HERE/ADDON_TEST).read_bytes().replace(b'\r\n',b'\n')}
    pins = {p:{'old':None if a is None else sha(a),'new':sha(b)} for p,(a,b) in edits.items()}
    digest = sha(Q.P.encoded({'sources':pins,'helpers':{p:sha(b) for p,b in helpers.items()}}))
    return edits,helpers,pins,digest,files,hashes


def public(r, baseline, files, hashes, active):
    proof = D.public_proof(r, files, hashes, baseline.decode('utf-8'))
    body,mime = Q.get(D.URL)
    text = body.decode('utf-8')
    require('text/html' in mime and all(text.count(m) == int(active) for m in MARKERS), 'Fullscreen resource state differs')
    expected = C.RULE.replace(b'max-height:calc(100svh - 108px)', b'max-height:none') if active else C.RULE
    # Existing inline style is one literal; no additional native header/form owner.
    require(expected.decode().strip()[3:-1] in text, 'Natural-ratio CSS not active')
    T.V.policy(text)
    for route in ('/lakaskereso/','/property/a1-f-l1/','/elerhetosegeink/'):
        data,mime = Q.get(Q.P.ORIGIN+route)
        require('text/html' in mime and not any(m.encode() in data for m in MARKERS), 'Fullscreen addon leaked to another route')
    proof['desktop_video'] = 'full_width_natural_ratio_and_fullscreen' if active else 'prior_viewport_height_cap'
    return proof


def restore(r, edits, metadata):
    errors = []
    for path in reversed(tuple(edits)):
        old,new = edits[path]
        try:
            if Q.P.absent(r,path):
                require(old is None,'Existing original missing');continue
            current = r.read(path)
            require(current in (old,new),'Foreign source retained')
            if current == old:continue
            if old is None:r.remove_owned(path,new)
            else:Q.replace(r,path,new,old,metadata[path])
        except Exception as error:errors.append(str(error))
    r.purge()
    require(not errors,'Owned-source recovery incomplete: '+'; '.join(errors))


def operate(bundle, backup=None, reverse=False):
    edits,helpers,pins,digest,files,hashes = bundle
    fresh = backup is None
    backup = backup or PREFIX+uuid.uuid4().hex
    require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact private backup required')
    r = H.Remote();locked=attempted=False
    try:
        r.directory(H.BACKUPS);r.directory(D.DIR);r.mkdir(D.LOCK);locked=True
        if fresh:
            meta_home = Q.metadata(r,U.TARGET,edits[U.TARGET][0])
            require(Q.P.absent(r,TARGET),'Fullscreen addon path already exists')
            meta = {U.TARGET:meta_home,TARGET:meta_home}
            before = T.state(r,files,hashes)
            baseline,mime = Q.get(D.URL)
            require('text/html' in mime and not any(m.encode() in baseline for m in MARKERS),'Unexpected baseline')
            r.mkdir(backup)
            for path,label in ((U.TARGET,'home'),(TARGET,'addon')):
                for state,blob in zip(('original','candidate'),edits[path]):
                    if blob is not None:r.write_new(backup+'/'+state+'-'+label+'.php',blob);r.lint(backup+'/'+state+'-'+label+'.php')
            for name,blob in helpers.items():r.write_new(backup+'/'+name,blob);r.lint(backup+'/'+name)
            r.write_new(backup+'/baseline.html',baseline)
            saved={'pins':pins,'digest':digest,'metadata':meta,'before':before,'baseline':sha(baseline)}
            D.write_json(r,backup+'/manifest.json',saved)
            addon_test = json.loads(r.run(H.PHP+' '+shlex.quote(backup+'/'+ADDON_TEST)+' '+shlex.quote(backup+'/candidate-addon.php')))
            require(addon_test.get('status')=='PASS' and addon_test.get('checks',0)>=42,'Staged fullscreen scope tests failed')
            filters = D.validate_native(r,backup)
            require(T.state(r,files,hashes)==before,'Protected state changed before activation')
            require(Q.P.absent(r,TARGET) and Q.metadata(r,U.TARGET,edits[U.TARGET][0])==meta_home,'Original ownership changed')
            D.write_json(r,backup+'/preflight.json',{'addon_scope':addon_test,'filters':filters})
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
            for path,label in ((U.TARGET,'home'),(TARGET,'addon')):
                for state,blob in zip(('original','candidate'),edits[path]):
                    if blob is not None:require(r.read(backup+'/'+state+'-'+label+'.php',private=True)==blob,'Saved source differs')
            for name,blob in helpers.items():require(r.read(backup+'/'+name,private=True)==blob,'Saved checker changed')
            for path,(_,new) in edits.items():require(Q.metadata(r,path,new)==tuple(meta[path]),'Owned released source changed')
            if reverse:restore(r,edits,meta)
        for path,(old,new) in edits.items():
            expected=old if reverse else new
            require(Q.P.absent(r,path) if expected is None else Q.metadata(r,path,expected)==tuple(meta[path]),'Final ownership differs')
        proof=public(r,baseline,files,hashes,not reverse)
        filters=D.validate_native(r,backup)
        require(T.state(r,files,hashes)==before,'Protected data/source drift; never restore business data')
        if fresh:D.write_json(r,backup+'/release.json',{'public':proof,'filters':filters})
        print(json.dumps({'status':'RELEASED' if fresh else 'ROLLED_BACK_SOURCE_ONLY' if reverse else 'VERIFIED',
                          'backup':backup,'aggregate':digest,'proof':proof,'filters':filters}),flush=True)
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
    parser=argparse.ArgumentParser(description=__doc__);m=parser.add_mutually_exclusive_group()
    m.add_argument('--deploy',action='store_true');m.add_argument('--verify');m.add_argument('--rollback')
    parser.add_argument('--expect');args=parser.parse_args();bundle=release()
    if not any((args.deploy,args.verify,args.rollback)):
        print(json.dumps({'mode':'OFFLINE_NO_NETWORK','aggregate':bundle[3],'pins':bundle[2]},indent=2));return
    require(args.expect==bundle[3],'Exact reviewed aggregate required before connecting')
    operate(bundle,args.verify or args.rollback,bool(args.rollback))


if __name__=='__main__':main()
