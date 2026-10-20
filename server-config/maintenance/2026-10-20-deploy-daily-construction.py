"""Exact registry-only daily media release. Offline by default; never restore a DB."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import stat
import subprocess
import time
import urllib.request
import uuid

HERE=Path(__file__).resolve().parent
ROOT=HERE.parents[1]


def load(name,filename):
    spec=importlib.util.spec_from_file_location(name,HERE/filename)
    value=importlib.util.module_from_spec(spec);spec.loader.exec_module(value);return value


L=load('daily_current_home','2026-10-20-deploy-home-loop.py')
C=load('daily_owned_html','2026-10-18-deploy-construction-updates.py')
H,D,Q=L.H,L.D,L.Q
require,sha=L.require,L.sha
NAME='zz-harmat-construction-updates.php'
TARGET=D.DIR+'/'+NAME
OLD='a6d1388ba8e6750d452906f8b4aeb3f312538f62b05fe41e92e889d2cc2813b9'
NEW='4a92047c91b1eabd846910914ed1f27332d7218a5a06028dfcc4d053f2db654f'
PREFIX=H.BACKUPS+'/daily-construction-'
MEDIA=H.LIVE+'/wp-content/uploads/2026/10/construction-updates'
LOCAL=ROOT/'outputs/2026-10-20-site-media/final-v1'
URL='https://harmat22.hu/wp-content/uploads/2026/10/construction-updates/'
DATES=('2026-10-19','2026-10-20')
NAMES=tuple(d+'-compilation-hu-subtitled'+suffix for d in DATES for suffix in ('.mp4','.jpg'))
TEST='2026-10-20-test-daily-construction.php'
HELPERS=(TEST,'2026-10-18-test-construction-updates.php',D.TEST,D.CHECKER)
MAP_PINS={
    'assets/map-redesign.js':'a9d07a0f3c823d40fcd39b4ad0011ed74545f4c99ef28d3c8d534bcb039870f2',
    'harmat22-map-redesign.php':'fb3bdf09c766880a847c6bd55b150ff229c45374b16f9054aaac73bb18e61330',
}


def file_hash(path):
    import hashlib
    with path.open('rb') as f:return hashlib.file_digest(f,'sha256').hexdigest()


def local_media(name,item,directory=LOCAL):
    require(name in NAMES,'Only reviewed public media filenames allowed')
    path=directory/name
    require(path.is_file() and not path.is_symlink() and path.resolve().parent==directory.resolve(),'Unsafe local media')
    require(set(item)=={'sha256','bytes'} and isinstance(item['sha256'],str)
            and re.fullmatch('[a-f0-9]{64}',item['sha256'])
            and type(item['bytes']) is int and 0<item['bytes']<=512*1024*1024,'Bounded reviewed HD media required')
    require(path.stat().st_size==item['bytes'] and file_hash(path)==item['sha256'],'Final local media drift')


def page(route):
    time.sleep(1.5)
    print('BOUNDED_PAGE_CHECK '+route,flush=True)
    return Q.get('https://harmat22.hu'+route)


def bundle():
    old=subprocess.check_output(['git','show','fb8e536:wp-mu-plugins/'+NAME],cwd=ROOT)
    new=(ROOT/'wp-mu-plugins'/NAME).read_bytes().replace(b'\r\n',b'\n')
    require(sha(old)==OLD and sha(new)==NEW,'Reviewed registry pair required')
    marker=b'    $records[] = array(\n        \'id\' => \'2026-10-19\''
    start=new.index(marker);end=new.index(b'    return $records;',start)
    require(new[:start]+new[end:]==old.replace(b'Version: 1.0.0',b'Version: 1.0.1',1),
            'Only version and two registry rows may change')
    require(new[start:end].count(b"'id' =>")==2,'Exactly two new records required')
    edits,_,protected,_,_=L.release()
    protected=dict(protected)
    for path,(_,current) in edits.items():protected[path.removeprefix(D.DIR+'/')]=current
    protected['zz-harmat-construction-progress-video.php']=(ROOT/'wp-mu-plugins/zz-harmat-construction-progress-video.php').read_bytes().replace(b'\r\n',b'\n')
    helpers={name:(HERE/name).read_bytes().replace(b'\r\n',b'\n') for name in HELPERS}
    qa=json.loads((LOCAL/'qa.json').read_text())
    require(qa.get('status')=='PASS_FINAL_LOCAL_TECHNICAL_QA_NOT_PUBLISHED','Final media QA required')
    media={name:qa['files'][name] for name in NAMES}
    for name,item in media.items():
        local_media(name,item)
    pins={'old':OLD,'new':NEW,'protected':{n:sha(b) for n,b in protected.items()},
          'helpers':{n:sha(b) for n,b in helpers.items()},'media':media,'qa':sha((LOCAL/'qa.json').read_bytes()),
          'snapshot_policy':L.P.CACHE_PREDICATE,'retained_neighborhood_sources':MAP_PINS}
    return old,new,protected,helpers,media,sha(Q.P.encoded(pins))


def state(r,protected):
    value=L.P.state(r,protected)
    value['sources'].pop(TARGET,None)
    base=H.LIVE+'/wp-content/plugins/harmat22-map-redesign'
    r.directory(base);r.directory(base+'/assets')
    value['retained_neighborhood_sources']={name:sha(r.read(base+'/'+name)) for name in MAP_PINS}
    require(value['retained_neighborhood_sources']==MAP_PINS,'Retained neighborhood repair changed')
    return value


def media_check(r,path,entry,mode=0o644):
    before=r.info(path)
    require(stat.S_ISREG(before.st_mode) and stat.S_IMODE(before.st_mode)==mode
            and before.st_size==entry['bytes'],'Media identity/size/mode drift')
    require(r.run('sha256sum -- '+shlex.quote(path))==entry['sha256']+'  '+path,'Remote media hash differs')
    require(H.Remote.identity(before)==H.Remote.identity(r.info(path)),'Media changed while hashing')


def upload(r,name,entry):
    require(name in NAMES and D.absent(r,MEDIA+'/'+name),'New-only media path required')
    stage=MEDIA+'/.'+name+'.'+uuid.uuid4().hex+'.tmp'
    try:
        r.directory(MEDIA);r.write_new(stage,b'')
        r.sftp.put(str(LOCAL/name),stage,confirm=True)
        media_check(r,stage,entry,0o600)
        require(file_hash(LOCAL/name)==entry['sha256'],'Local upload source changed')
        r.sftp.chmod(stage,0o644);media_check(r,stage,entry)
        require(D.absent(r,MEDIA+'/'+name),'New path appeared during upload')
        r.sftp.rename(stage,MEDIA+'/'+name)
        media_check(r,MEDIA+'/'+name,entry)
    finally:
        if not D.absent(r,stage):
            # Only a fully verified stage is safe for automatic cleanup.
            media_check(r,stage,entry,stat.S_IMODE(r.info(stage).st_mode));r.sftp.remove(stage)


def remove_media(r,media):
    for name,item in media.items():
        require(name in NAMES,'Never remove nonallowlisted media')
        path=MEDIA+'/'+name
        if not D.absent(r,path):media_check(r,path,item);r.sftp.remove(path)


def main_html(text):
    return C.owned(text,'id','main')


def proof(baselines,active,media):
    result={}
    for route,baseline in baselines.items():
        body,mime=page(route)
        require('text/html' in mime,'Page unavailable')
        text=body.decode('utf-8');old=baseline.decode('utf-8')
        current_page,previous=D.Page(text),D.Page(old)
        require(current_page.form==previous.form and current_page.form,'Original native offer form changed')
        require(current_page.canonical==previous.canonical,'Canonical changed')
        if route=='/':
            D.assert_home(text,old)
            current_root=C.owned(text,'data-hcu-home','1');old_root=C.owned(old,'data-hcu-home','1')
            require(main_html(text).replace(current_root,old_root,1)==main_html(old),'Homepage delta outside latest construction')
            require(('2026-10-20-compilation-hu-subtitled.mp4' in current_root)==active,'Wrong latest homepage record')
        else:
            current_root=C.owned(text,'id','harmat-construction-updates');old_root=C.owned(old,'id','harmat-construction-updates')
            reduced=current_root
            if active:
                for date in reversed(DATES):
                    fragment=C.owned(reduced,'id','construction-'+date)
                    require(fragment.count('data-hcu-player data-hcu-video=')==1,'Daily record not unique')
                    reduced=reduced.replace(fragment,'',1)
                require('2026. október 20.' in text,'Latest metadata not updated')
            require(reduced==old_root,'Existing construction records changed')
            require(main_html(text).replace(current_root,old_root,1)==main_html(old),'Journal delta outside construction registry')
            require(current_root.count('data-hcu-player data-hcu-video=')==(7 if active else 5),'Wrong lazy player count')
        require(not re.search(r'<(?:video|source)[^>]+2026-10-(?:19|20)-compilation',text),'Unexpected eager daily MP4')
        result[route]={'native_form_unchanged':True,'owned_delta_only':True,'canonical_unchanged':True}
    for route in ('/lakaskereso/','/property/a1-f-l1/','/elerhetosegeink/',
                  '/virtualis-lakasvalaszto/','/virtualis-lakasvalaszto-elso-utem/','/virtualis-lakasvalaszto-a1-epulet/'):
        data,mime=page(route)
        require('text/html' in mime and not re.search(rb'Fatal error|Parse error|critical error',data,re.I),'Health route failed')
    if active:
        for name,item in media.items():
            with urllib.request.urlopen(urllib.request.Request(URL+name,method='HEAD'),timeout=30) as response:
                require(response.status==200 and response.geturl()==URL+name
                        and int(response.headers['Content-Length'])==item['bytes'],'Media HEAD failed')
                require(('video/mp4' if name.endswith('.mp4') else 'image/jpeg') in response.headers.get('Content-Type',''),'Media MIME failed')
            if name.endswith('.mp4'):
                with urllib.request.urlopen(urllib.request.Request(URL+name,headers={'Range':'bytes=0-0'}),timeout=30) as response:
                    require(response.status==206 and response.geturl()==URL+name
                            and response.headers.get('Content-Range')=='bytes 0-0/'+str(item['bytes'])
                            and len(response.read(2))==1,'Bounded byte-range failed')
    result['health_routes']=8;result['submission']=False
    return result


def operate(parts,backup=None,reverse=False):
    old,new,protected,helpers,media,digest=parts
    fresh=backup is None;backup=backup or PREFIX+uuid.uuid4().hex
    require(re.fullmatch(re.escape(PREFIX)+'[a-f0-9]{32}',backup),'Exact private backup path required')
    r=H.Remote();locked=activated=False;uploaded={}
    try:
        for path in (H.BACKUPS,D.DIR,MEDIA):r.directory(path)
        for suffix in ('','/wp-content','/wp-content/uploads','/wp-content/uploads/2026','/wp-content/uploads/2026/10'):
            r.directory(H.LIVE+suffix)
        r.mkdir(D.LOCK);locked=True
        before=state(r,protected)
        if fresh:
            meta=Q.metadata(r,TARGET,old)
            require(all(D.absent(r,MEDIA+'/'+n) for n in NAMES),'Existing daily media retained; inspect duplicates')
            baselines={route:page(route)[0] for route in ('/','/epitesi-naplo/')}
            r.mkdir(backup);r.mkdir(backup+'/'+D.ASSET)
            r.write_new(backup+'/original-module.php',old);r.write_new(backup+'/candidate-module.php',new)
            r.lint(backup+'/original-module.php');r.lint(backup+'/candidate-module.php')
            for name,blob in protected.items():
                if name.startswith(D.ASSET+'/'):r.write_new(backup+'/'+name,blob)
            for name,blob in helpers.items():r.write_new(backup+'/'+name,blob);r.lint(backup+'/'+name)
            r.write_new(backup+'/current-home.php',protected[D.MAIN])
            for i,(route,body) in enumerate(baselines.items()):r.write_new(backup+f'/baseline-{i}.html',body)
            D.write_json(r,backup+'/manifest.json',{'digest':digest,'metadata':meta,'before':before,
                'baselines':{route:sha(body) for route,body in baselines.items()},'media':media})
            fixture=json.loads(r.run(H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/candidate-module.php')))
            require(fixture=={'status':'PASS','checks':59,'records':7},'Private candidate fixture failed')
            home=json.loads(r.run(H.PHP+' '+shlex.quote(backup+'/'+D.TEST)+' '+shlex.quote(backup+'/current-home.php')))
            require(home.get('status')=='PASS' and home.get('checks')==76,'Retained homepage fixture failed')
            filters=D.validate_native(r,backup)
            require(state(r,protected)==before and Q.metadata(r,TARGET,old)==meta,'Protected drift before media stage')
            print(json.dumps({'status':'PRIVATE_BACKUP_READY','backup':backup,'aggregate':digest,'fixture':fixture}),flush=True)
            for name,item in media.items():
                upload(r,name,item);uploaded[name]=item
                print(json.dumps({'uploaded_media':name,'bytes':item['bytes']}),flush=True)
            require(state(r,protected)==before and Q.metadata(r,TARGET,old)==meta,'Protected drift before registry activation')
            activated=True;Q.replace(r,TARGET,old,new,meta);r.purge()
        else:
            r.directory(backup,private=True)
            saved=json.loads(r.read(backup+'/manifest.json',private=True))
            require(saved['digest']==digest and saved['media']==media,'Saved release mismatch')
            require(r.read(backup+'/original-module.php',private=True)==old and r.read(backup+'/candidate-module.php',private=True)==new,'Saved source drift')
            require(not D.absent(r,backup+'/release.json'),'Successful release evidence required')
            baselines={route:r.read(backup+f'/baseline-{i}.html',private=True) for i,route in enumerate(('/','/epitesi-naplo/'))}
            require({k:sha(v) for k,v in baselines.items()}==saved['baselines'],'Baseline evidence drift')
            meta=tuple(saved['metadata']);require(Q.metadata(r,TARGET,new)==meta,'Released source drift')
            for name,item in media.items():media_check(r,MEDIA+'/'+name,item)
            if reverse:
                Q.replace(r,TARGET,new,old,meta);r.purge();remove_media(r,media)
        expected=old if reverse else new
        require(Q.metadata(r,TARGET,expected)==tuple(meta),'Final source identity mismatch')
        public=proof(baselines,not reverse,media)
        filters=D.validate_native(r,backup)
        require(state(r,protected)==before,'Protected state changed; never restore customer data')
        report={'status':'RELEASED' if fresh else 'ROLLED_BACK_SOURCE_ONLY' if reverse else 'VERIFIED_CURRENT_STATE',
                'backup':backup,'aggregate':digest,'proof':public,'filters':filters,'database_restored_or_written':False}
        if fresh:D.write_json(r,backup+'/release.json',report)
        print(json.dumps(report),flush=True)
    except BaseException:
        if fresh:
            # Revert only our module before removing only our new media; never rewind business data.
            current=r.read(TARGET)
            require(current in (old,new),'Foreign module retained; dependency cleanup stopped')
            if activated and current==new:Q.replace(r,TARGET,new,old,meta);r.purge()
            remove_media(r,uploaded)
            print(json.dumps({'status':'OWNED_RELEASE_NOT_ACTIVE','backup':backup}),flush=True)
        raise
    finally:
        try:
            if locked:r.sftp.rmdir(D.LOCK)
        finally:r.close()


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    modes=parser.add_mutually_exclusive_group();modes.add_argument('--deploy',action='store_true');modes.add_argument('--verify');modes.add_argument('--rollback')
    parser.add_argument('--expect');args=parser.parse_args();parts=bundle()
    if not any((args.deploy,args.verify,args.rollback)):
        print(json.dumps({'status':'OFFLINE_NO_NETWORK','aggregate':parts[-1],'old':OLD,'new':NEW,'media':parts[-2]}))
    else:
        require(args.expect==parts[-1],'Exact reviewed aggregate required before connection')
        operate(parts,args.verify or args.rollback,bool(args.rollback))
