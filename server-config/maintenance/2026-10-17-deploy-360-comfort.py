"""Guarded viewer-only update. Default offline; exact source pin needed for writes."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import subprocess
import uuid

ROOT=Path(__file__).resolve().parents[2]
MAINT=ROOT/'server-config/maintenance'
spec=importlib.util.spec_from_file_location('viewer_safety',MAINT/'2026-10-16-deploy-about-family.py')
D=importlib.util.module_from_spec(spec);spec.loader.exec_module(D)
H=D.H
DIR=H.LIVE+'/wp-content/plugins/360'
PREFIX=H.BACKUPS+'/360-comfort-'
LOCK=H.BACKUPS+'/.360-comfort.lock'
FILES=('viewer.js','lakaspark-360.php')
OLD={'viewer.js':'ae9566aee3f979bc3cd83c441ea97bb7377d9e59490b2aa790563b03a754dce0',
     'lakaspark-360.php':'b65b471c5ec8a8b6d002e5dff94320a840b97e3e7fbe3ef9d2e2e8df705a9205'}
ROUTES=('virtualis-lakasvalaszto','virtualis-lakasvalaszto-elso-utem',
        'virtualis-lakasvalaszto-a1-epulet','virtualis-lakasvalaszto-a2-epulet',
        'virtualis-lakasvalaszto-a3-epulet','virtualis-lakasvalaszto-a4-epulet')
TESTS=('2026-09-06-test-360-frames.mjs','2026-10-17-test-360-comfort.mjs')

def bundle():
    blobs={n:(ROOT/'wp-plugins/360'/n).read_bytes().replace(b'\r\n',b'\n') for n in FILES}
    pins={n:D.sha(b) for n,b in blobs.items()}
    tests={n:D.sha((MAINT/n).read_bytes().replace(b'\r\n',b'\n')) for n in TESTS}
    digest=D.sha(json.dumps({'files':pins,'tests':tests},sort_keys=True,separators=(',',':')).encode())
    return blobs,pins,digest

def snapshot(r):
    result=D.snapshot(r)
    result['retained']={n:D.sha(r.read(H.LIVE+'/wp-content/mu-plugins/'+n)) for n in (
        'zz-harmat-about-family.php','harmat-about-family/body.php','harmat-about-family/about-family.css',
        'harmat-about-family/harmat-courtyard.jpg','harmat-about-family/harmat-living-room.jpg',
        'zz-harmat-reserved-virtual-view.php')}
    result['viewer_css']=D.sha(r.read(DIR+'/viewer.css'))
    return result

def php_only_version(old,new):
    needle=b"'viewer.js', array(), '6.4', true)"
    replacement=b"'viewer.js', array(), '6.5', true)"
    D.require(old.count(needle)==1,'Wrong original viewer version')
    expected=old.replace(needle,replacement)
    D.require(expected.replace(b'\r\n',b'\n')==new,'PHP changed beyond one script version')
    return expected

def public_proof(r,pins):
    results={}
    for slug in ROUTES:
        raw,mime=D.http('https://harmat22.hu/'+slug+'/')
        text=raw.decode('utf-8')
        D.require('text/html' in mime and 'viewer.js?ver=6.5' in text,'Wrong viewer page/version')
        for marker in ('id="buildingViewer"','id="hitboxLayer"','LakasparkData','id="h22-offer-modal"','id="hm-cookie-settings-button"'):
            D.require(marker in text,'Missing viewer/native shell marker')
        D.require(not any(x in text for x in ('Fatal error:','Parse error:','critical error on this website')),'Public fatal')
        results[slug]=200
    data,mime=D.http('https://harmat22.hu/wp-content/plugins/360/viewer.js?ver=6.5')
    D.require(D.sha(data)==pins['viewer.js'] and ('javascript' in mime or 'text/plain' in mime),'Public viewer hash/MIME mismatch')
    with D.T.bounded_shared_requests():
        health=D.I.HEALTH.smoke()
        inventory=D.I.public_check(r)
    return {'selectors':results,'health':health,'inventory':inventory}

def native_checks(r,backup):
    target=backup+'/offer-readonly.php.txt'
    data=(MAINT/'2026-10-16-native-offer-readonly.php').read_bytes().replace(b'\r\n',b'\n')
    if D.absent(r,target):r.write_new(target,data)
    D.require(r.read(target,private=True)==data,'Private pure checker changed')
    r.lint(target)
    offer=json.loads(r.run(H.WP+' eval-file '+shlex.quote(target)))
    spam=json.loads(r.run(H.WP+' eval '+shlex.quote(D.I.HEALTH.RUNTIME_CHECK)))
    D.require(offer['status']=='PASS' and offer['checks']==35,'Native pure offer failure')
    D.require(spam['status']=='PASS' and spam['checks']==17,'Native pure spam failure')
    return {'offer':offer,'antispam':spam}

def verify_files(r,pins):
    for n in FILES:D.require(D.sha(r.read(DIR+'/'+n))==pins[n],'Viewer file drift')
    r.lint(DIR+'/lakaspark-360.php')

def rollback(r,installed,originals,pins):
    for n in reversed(installed):
        target=DIR+'/'+n
        D.require(D.sha(r.read(target))==pins[n],'Rollback refuses later editor changes')
        stage=target+'.'+uuid.uuid4().hex+'.rollback'
        r.write_new(stage,originals[n])
        if n.endswith('.php'):r.lint(stage)
        r.sftp.chmod(stage,0o644);r.sftp.posix_rename(stage,target)
        if n.endswith('.php'):r.lint(target)
        D.require(r.read(target)==originals[n],'Rollback readback failure')
    if installed:r.purge()

def deploy(blobs,pins,digest):
    subprocess.run(['node','--check',str(ROOT/'wp-plugins/360/viewer.js')],check=True,cwd=ROOT)
    for n in TESTS:subprocess.run(['node','--test',str(MAINT/n)],check=True,cwd=ROOT)
    r=H.Remote();locked=False;stages={};installed=[];originals={};backup=PREFIX+uuid.uuid4().hex
    try:
        r.directory(DIR);r.directory(H.BACKUPS);r.mkdir(LOCK);locked=True
        for n in FILES:
            originals[n]=r.read(DIR+'/'+n)
            D.require(D.sha(originals[n])==OLD[n],'Reviewed original viewer differs')
        blobs['lakaspark-360.php']=php_only_version(originals['lakaspark-360.php'],blobs['lakaspark-360.php'])
        pins={n:D.sha(b) for n,b in blobs.items()}
        before=snapshot(r)
        r.mkdir(backup)
        D.write_json(r,backup+'/manifest.json',{'before':before,'old':OLD,'pins':pins,'bundle':digest})
        for n in FILES:
            r.write_new(backup+'/original-'+n,originals[n])
            r.write_new(backup+'/candidate-'+n,blobs[n])
            D.require(r.read(backup+'/original-'+n,private=True)==originals[n],'Backup mismatch')
            if n.endswith('.php'):r.lint(backup+'/candidate-'+n)
        code="$out=array();foreach(array(8244,8533,8538,8543,8548,8553) as $id){$p=get_post($id);if(!$p||$p->post_status!=='publish')throw new RuntimeException('Wrong selector');$out[]=array('id'=>$id,'post'=>(array)$p,'meta'=>get_post_meta($id));}echo wp_json_encode($out);"
        r.write_new(backup+'/original-selector-pages.json',r.run(H.WP+' eval '+shlex.quote(code)).encode())
        pure=native_checks(r,backup)
        D.require(snapshot(r)==before,'Protected state drift before activation')
        for n in FILES:
            stage=DIR+'/'+n+'.'+uuid.uuid4().hex+'.tmp';stages[stage]=blobs[n]
            r.write_new(stage,blobs[n])
            if n.endswith('.php'):r.lint(stage)
            D.require(D.sha(r.read(stage))==pins[n],'Staged hash mismatch');r.sftp.chmod(stage,0o644)
        for n,stage in zip(FILES,tuple(stages)):
            D.require(r.read(DIR+'/'+n)==originals[n],'Concurrent live viewer change')
            r.sftp.posix_rename(stage,DIR+'/'+n);stages.pop(stage);installed.append(n)
            if n.endswith('.php'):r.lint(DIR+'/'+n)
        r.purge();verify_files(r,pins)
        proof=public_proof(r,pins)
        D.require(snapshot(r)==before,'Protected state changed; do not restore data')
        D.write_json(r,backup+'/release-proof.json',{'proof':proof,'pure':pure,'protected_unchanged':True})
        print(json.dumps({'status':'RELEASED','backup':backup,'pins':pins,'proof':proof,'pure':pure}),flush=True)
    except BaseException:
        for stage,data in stages.items():
            if not D.absent(r,stage):r.remove_owned(stage,data)
        rollback(r,installed,originals,pins)
        print(json.dumps({'status':'NO_ACTIVATION_OR_OWNED_FILES_RESTORED','backup':backup}),flush=True)
        raise
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()

def verify(backup):
    D.require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact private backup required')
    r=H.Remote()
    try:
        manifest=json.loads(r.read(backup+'/manifest.json',private=True))
        blobs,pins,digest=bundle()
        D.require(digest==manifest['bundle'],'Local release changed')
        for n in FILES:D.require(D.sha(r.read(backup+'/original-'+n,private=True))==OLD[n],'Original backup changed')
        verify_files(r,manifest['pins']);proof=public_proof(r,manifest['pins']);pure=native_checks(r,backup)
        D.require(snapshot(r)==manifest['before'],'Protected drift')
        print(json.dumps({'status':'VERIFIED','backup':backup,'proof':proof,'pure':pure,'protected_unchanged':True}))
    finally:r.close()

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--deploy',action='store_true');p.add_argument('--expect');p.add_argument('--verify')
    args=p.parse_args()
    if args.verify:verify(args.verify)
    else:
        blobs,pins,digest=bundle()
        if args.deploy:
            D.require(args.expect==digest,'Exact reviewed bundle required');deploy(blobs,pins,digest)
        else:print(json.dumps({'mode':'OFFLINE_NO_WRITES','bundle':digest,'pins':pins},indent=2))
