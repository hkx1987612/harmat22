"""Final flag-only release after native/browser gates and one owned TEST."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import uuid

MAINT=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('language_publish',MAINT/'2026-10-17-deploy-internal-language.py')
D=importlib.util.module_from_spec(spec);spec.loader.exec_module(D)
PREFIX=D.H.BACKUPS+'/internal-language-public-'

def files():
    return {n:(D.ROOT/'wp-mu-plugins'/n).read_bytes().replace(b'\r\n',b'\n') for n in D.FILES}

def public_health(r,pins):
    routes=[]
    for p in ('/','/harmat-lakopark/','/magunkrol/','/lakaskereso/','/property/a1-f-l1/','/property/a1-1-l2/','/virtualis-lakasvalaszto/','/virtualis-lakasvalaszto-elso-utem/','/virtualis-lakasvalaszto-a2-epulet/','/galeria/','/elerhetosegeink/','/epitesi-naplo/','/adatvedelmi-tajekoztato/'):
        data,mime=D.D.http('https://harmat22.hu'+p)
        D.require('text/html' in mime and b'Fatal error:' not in data and b'h22-offer-modal' in data,'Public route failed '+p)
        D.require(data.count(b'id="harmat-internal-language"')==1 and b'aria-pressed="true">Magyar' in data,'Guest/default-HU boundary '+p)
        routes.append(p)
    for n in D.FILES[:-1]:
        data,mime=D.D.http('https://harmat22.hu/wp-content/mu-plugins/'+n)
        D.require(D.sha(data)==pins[n],'Public asset identity '+n)
        expected='application/json' if n.endswith('.json') else 'text/css' if n.endswith('.css') else 'javascript'
        D.require(expected in mime,'Unexpected asset MIME '+n)
    with D.D.T.bounded_shared_requests(): inventory=D.D.I.public_check(r)
    return {'routes':routes,'default_hu':True,'one_switch':True,'assets_match':True,'inventory':inventory}

def publish(previous,expect,qa_path):
    D.require(re.fullmatch(re.escape(D.PREFIX)+r'[a-f0-9]{32}',previous),'Exact final preview backup')
    blobs=files();pins={n:D.sha(b) for n,b in blobs.items()};D.require(pins[D.MAIN]==expect,'Exact reviewed public controller hash')
    qa=json.loads(Path(qa_path).read_text(encoding='utf-8'))
    D.require(qa['dictionary_sha256']==pins[D.ASSET+'/zh-Hans.json'],'Browser dictionary version mismatch')
    for k in ('desktop','mobile390','mobile320','quote_controls','appointment_controls','selector','same_tab_language'):
        D.require(qa.get(k) is True,'Missing actual browser gate '+k)
    state=json.loads((D.ROOT/'outputs/2026-10-17-internal-language/approved-once/state.json').read_text())
    proof=state.get('verification',{})
    D.require(proof.get('owned_test') and proof.get('wp_mail_accepted_both') and proof.get('non_test_protected_state_unchanged'),'Owned one-shot TEST not verified')
    r=D.H.Remote();backup=PREFIX+uuid.uuid4().hex;locked=False;stage=None;active=False;old=None
    try:
        r.directory(D.DIR);r.directory(D.H.BACKUPS);r.mkdir(D.LOCK);locked=True
        manifest=json.loads(r.read(previous+'/manifest.json',private=True))
        for n in D.FILES:D.require(D.sha(r.read(D.DIR+'/'+n))==manifest['pins'][n],'Preview/source drift '+n)
        old=r.read(D.DIR+'/'+D.MAIN)
        old_flag=b'function harmat_internal_language_public_enabled() { return false; }'
        new_flag=b'function harmat_internal_language_public_enabled() { return true; }'
        D.require(old.count(old_flag)==1 and old.count(b'Version: 0.1.0-preview')==1,'Unexpected preview preimage')
        expected=old.replace(old_flag,new_flag,1).replace(b'Version: 0.1.0-preview',b'Version: 1.0.0',1)
        D.require(blobs[D.MAIN]==expected,'Only approved public flag/version may change')
        for n in D.FILES[:-1]:D.require(pins[n]==manifest['pins'][n],'Untested assets changed')
        before=D.snapshot(r);r.mkdir(backup);r.mkdir(backup+'/'+D.ASSET)
        r.write_new(backup+'/original-controller.php.txt',old)
        for n,data in blobs.items():r.write_new(backup+'/'+n,data)
        fixture=(MAINT/D.TEST).read_bytes().replace(b'\r\n',b'\n')
        r.write_new(backup+'/'+D.TEST,fixture);r.lint(backup+'/'+D.TEST);r.lint(backup+'/'+D.MAIN)
        scope=r.run(D.H.PHP+' '+shlex.quote(backup+'/'+D.TEST)+' '+shlex.quote(backup+'/'+D.MAIN)+' public')
        D.require(re.search(r'PUBLIC TOTAL: [0-9]+ checks, [0-9]+ cases, 0 failures',scope),'Public scope fixture failed')
        r.write_new(backup+'/public-scope-proof.txt',scope.encode())
        D.D.write_json(r,backup+'/manifest.json',{'pins':pins,'before':before,'preview':previous,'original_controller_sha256':D.sha(old)})
        D.D.write_json(r,backup+'/browser-gates.json',qa)
        D.require(D.snapshot(r)==before,'Protected state drift before public activation')
        target=D.DIR+'/'+D.MAIN;stage=target+'.'+uuid.uuid4().hex+'.tmp'
        r.write_new(stage,blobs[D.MAIN]);r.lint(stage)
        D.require(r.read(target)==old and D.sha(r.read(stage))==expect,'Public stage/preimage drift')
        r.sftp.chmod(stage,0o644);r.sftp.posix_rename(stage,target);stage=None;active=True;r.lint(target);r.purge()
        released=public_health(r,pins)
        D.require(D.snapshot(r)==before,'Non-test state drift; never restore business data')
        D.D.write_json(r,backup+'/release-proof.json',released)
        print(json.dumps({'status':'PUBLIC_RELEASED','backup':backup,'controller_sha256':expect,'proof':released}),flush=True)
    except BaseException:
        if stage and not D.D.absent(r,stage):r.remove_owned(stage,blobs[D.MAIN])
        if active:
            target=D.DIR+'/'+D.MAIN;D.require(D.sha(r.read(target))==expect,'Refuse foreign rollback')
            tmp=target+'.'+uuid.uuid4().hex+'.tmp';r.write_new(tmp,old);r.lint(tmp)
            r.sftp.chmod(tmp,0o644);r.sftp.posix_rename(tmp,target);r.lint(target);r.purge()
            print(json.dumps({'status':'PUBLIC_FLAG_ROLLED_BACK_TO_ADMIN_PREVIEW','backup':backup}),flush=True)
        raise
    finally:
        if locked:r.sftp.rmdir(D.LOCK)
        r.close()

def verify(backup):
    D.require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact public backup')
    r=D.H.Remote()
    try:
        manifest=json.loads(r.read(backup+'/manifest.json',private=True))
        for n,pin in manifest['pins'].items():D.require(D.sha(r.read(D.DIR+'/'+n))==pin,'Public file drift '+n)
        r.lint(D.DIR+'/'+D.MAIN);proof=public_health(r,manifest['pins'])
        D.require(D.snapshot(r)==manifest['before'],'Protected public baseline drift')
        print(json.dumps({'status':'VERIFIED_PUBLIC','proof':proof}))
    finally:r.close()

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--publish',action='store_true');p.add_argument('--preview-backup');p.add_argument('--expect');p.add_argument('--browser-qa');p.add_argument('--verify');args=p.parse_args()
    if args.publish and not args.verify:publish(args.preview_backup,args.expect,args.browser_qa)
    elif args.verify and not args.publish:verify(args.verify)
    else: print('OFFLINE_NO_WRITES: explicit publication gates or verification required')
