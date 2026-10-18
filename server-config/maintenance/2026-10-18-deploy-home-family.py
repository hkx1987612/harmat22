"""Homepage-only new MU release. Default is offline; exact reviewed bundle required."""
import argparse
import hashlib
from html.parser import HTMLParser
import importlib.util
import json
from pathlib import Path
import re
import shlex
import urllib.request
import uuid

ROOT=Path(__file__).resolve().parents[2]
HERE=Path(__file__).resolve().parent
def module(name,filename):
    spec=importlib.util.spec_from_file_location(name,HERE/filename)
    item=importlib.util.module_from_spec(spec);spec.loader.exec_module(item);return item
P=module('home_previous_transport','2026-10-17-deploy-project-family.py')
H=P.H;T=P.T;I=P.I
DIR=H.LIVE+'/wp-content/mu-plugins'
ASSET='harmat-home-family'
MAIN='zz-harmat-home-family.php'
PREFIX=H.BACKUPS+'/home-family-'
LOCK=H.BACKUPS+'/.home-family.lock'
TEST='2026-10-18-test-home-family-controller.php'
NATIVE='2026-10-18-native-home-family.php'
CHECKER='2026-10-16-native-offer-readonly.php'
URL='https://harmat22.hu/'
def require(value,message):
    if not value:raise RuntimeError(message)
def sha(data):return hashlib.sha256(data).hexdigest()
def bundle():
    pins=json.loads((ROOT/'wp-mu-plugins'/ASSET/'manifest.json').read_text())
    files=tuple(ASSET+'/'+name for name in pins)+(ASSET+'/manifest.json',MAIN)
    require(all(re.fullmatch(r'[a-zA-Z0-9][a-zA-Z0-9._-]*',name) for name in pins),'Invalid release filename')
    blobs={name:(ROOT/'wp-mu-plugins'/name).read_bytes().replace(b'\r\n',b'\n') for name in files}
    for name in (TEST,NATIVE,CHECKER):blobs[name]=(HERE/name).read_bytes().replace(b'\r\n',b'\n')
    for name,pin in pins.items():require(sha(blobs[ASSET+'/'+name])==pin,'Asset manifest drift '+name)
    require(sha(blobs[ASSET+'/manifest.json']).encode() in blobs[MAIN],'Controller manifest pin mismatch')
    hashes={name:sha(data) for name,data in blobs.items()}
    digest=sha(json.dumps(hashes,sort_keys=True,separators=(',',':')).encode())
    return files,blobs,hashes,digest
def absent(r,path):return P.absent(r,path)
def write_json(r,path,data):r.write_new(path,json.dumps(data,sort_keys=True,ensure_ascii=True,indent=2).encode())
def snapshot(r):
    value=P.snapshot(r)
    value['language']={name:sha(r.read(DIR+'/'+name)) for name in ('zz-harmat-internal-language.php','harmat-internal-language/bootstrap.js','harmat-internal-language/runtime.js','harmat-internal-language/runtime.css','harmat-internal-language/zh-Hans.json','harmat-internal-language/patterns.js')}
    value['disclaimer']={name:sha(r.read(DIR+'/'+name)) for name in ('zz-harmat-property-disclaimer.php','zz-harmat-public-offer-antispam.php','zz-harmat-public-offer-integrity.php')}
    return value
def get(url):
    request=urllib.request.Request(url,headers={'User-Agent':'HarmatOfferSafety/1.0'})
    with urllib.request.urlopen(request,timeout=30) as response:
        data=response.read(2*1024*1024+1)
        require(response.status==200 and response.geturl()==url and len(data)<=2*1024*1024,'HTTP identity/status/size failed')
        return data,response.headers.get('Content-Type','')
class Page(HTMLParser):
    def __init__(self,text):
        super().__init__(convert_charrefs=True);self.ids=[];self.form=[];self.in_form=False;self.canonical=[];self.seo=[];self.feed(text)
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if 'id' in a:self.ids.append(a['id'])
        if tag=='link' and a.get('rel')=='canonical':self.canonical.append(a.get('href'))
        if tag=='meta' and (a.get('name') in ('description','robots') or a.get('property') in ('og:title','og:description','og:url')):self.seo.append((tag,attrs))
        if tag=='form' and a.get('id')=='h22-offer-form':self.in_form=True
        if self.in_form:
            if a.get('name')=='_harmat_offer_nonce':a.pop('value',None)
            self.form.append((tag,sorted(a.items())))
    def handle_endtag(self,tag):
        if self.in_form:
            self.form.append(('/'+tag,[]))
            if tag=='form':self.in_form=False
def assert_home(text,baseline):
    p=Page(text);b=Page(baseline)
    require(p.ids.count('harmat-home-family')==1,'New homepage not active')
    for marker in ('main','masthead','colophon','h22-offer-modal','h22-offer-form','hm-cookie-settings-button','harmat-internal-language'):
        require(p.ids.count(marker)==1,'Native marker missing or duplicate '+marker)
    require(p.canonical==b.canonical==[URL] and p.seo==b.seo,'Native SEO metadata changed')
    require(p.form==b.form and p.form,'Native form structure changed')
    require(len(re.findall(r'<h1\b',text))==1 and 'id="hero-title"' in text,'Heading contract failed')
    require('src="assets/' not in text and '__HHF_' not in text,'Unresolved preview path')
    require('http-equiv="Content-Security-Policy"' not in text and 'noindex, nofollow' not in text,'Offline policy leaked')
    for marker in ('data-language-protect','panorama-viewer','tel:+36300733375','mailto:ertekesites@harmat22.hu'):
        require(marker in text,'Homepage marker missing '+marker)
    return {'homepage':True,'native_form_unchanged':True,'native_seo_unchanged':True}
def validate_native(r,backup):
    a=json.loads(r.run(H.WP+' eval-file '+shlex.quote(backup+'/'+CHECKER)))
    b=json.loads(r.run(H.WP+' eval '+shlex.quote(I.HEALTH.RUNTIME_CHECK)))
    require(a.get('status')=='PASS' and a.get('checks')==35,'Pure native offer filters failed')
    require(b.get('status')=='PASS' and b.get('checks')==17,'Pure anti-spam filters failed')
    return {'offer_checks':35,'antispam_checks':17,'scope':'NO_REST_DISPATCH_NO_SUBMISSION_NO_MAIL'}
def public_proof(r,files,hashes,baseline):
    data,mime=get(URL);require('text/html' in mime,'Homepage MIME failed')
    result=assert_home(data.decode('utf-8'),baseline)
    for name in files:
        if not name.endswith(('.css','.js')):continue
        data,mime=get('https://harmat22.hu/wp-content/mu-plugins/'+name)
        require(sha(data)==hashes[name],'Public source mismatch '+name)
        require(('css' if name.endswith('.css') else 'javascript') in mime,'Asset MIME mismatch '+name)
    with T.bounded_shared_requests():
        result['routes']=I.HEALTH.smoke()
        result['inventory']=I.public_check(r)
    return result
def rollback(r,files,blobs):
    # Remove only exact originally absent files, controller first; never restore DB.
    if not absent(r,DIR+'/'+MAIN):
        require(r.read(DIR+'/'+MAIN)==blobs[MAIN],'foreign bytes in controller; retain its dependent assets')
        r.remove_owned(DIR+'/'+MAIN,blobs[MAIN])
    failures=[]
    for name in reversed(files):
        if name==MAIN:continue
        try:
            if not absent(r,DIR+'/'+name):r.remove_owned(DIR+'/'+name,blobs[name])
        except Exception:failures.append(name)
    if not absent(r,DIR+'/'+ASSET):
        try:r.sftp.rmdir(DIR+'/'+ASSET)
        except Exception:failures.append(ASSET)
    r.purge()
    require(not failures,'foreign bytes or changed release files retained; native presentation deactivated where owned')
def deploy(files,blobs,hashes,digest,prepare):
    r=H.Remote();backup=PREFIX+uuid.uuid4().hex;locked=False;started=False;stages={}
    try:
        r.directory(DIR);r.directory(H.BACKUPS);r.mkdir(LOCK);locked=True
        require(absent(r,DIR+'/'+MAIN) and absent(r,DIR+'/'+ASSET),'New release paths already exist')
        before=snapshot(r)
        baseline,mime=get(URL);require('text/html' in mime,'Baseline MIME')
        r.mkdir(backup);r.mkdir(backup+'/'+ASSET)
        print(json.dumps({'status':'PRIVATE_BACKUP_STARTED','backup':backup}),flush=True)
        manifest={'bundle':digest,'pins':hashes,'files':files,'before':before,'original_absent':files}
        write_json(r,backup+'/manifest.json',manifest)
        r.write_new(backup+'/public-original.html',baseline)
        code="$p=get_post(164);if(!$p||$p->post_name!=='fooldal')throw new RuntimeException('Wrong home');echo wp_json_encode(array('post'=>(array)$p,'meta'=>get_post_meta(164)));"
        r.write_new(backup+'/original-page.json',r.run(H.WP+' eval '+shlex.quote(code)).encode())
        for path in list(I.CRITICAL)+list(T.OTHER_SOURCES.values())+[DIR+'/harmat-unified-offer-modal.php',DIR+'/zz-harmat-internal-language.php',H.LIVE+'/.htaccess']:
            r.write_new(backup+'/original-'+sha(path.encode())[:12]+'.bin',r.read(path))
        for name,data in blobs.items():
            r.write_new(backup+'/'+name,data)
            if name.endswith('.php'):r.lint(backup+'/'+name)
        pure=json.loads(r.run(H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/'+MAIN)))
        require(pure.get('status')=='PASS','Isolated PHP fixture failed')
        native=json.loads(r.run(H.PHP+' '+shlex.quote(backup+'/'+NATIVE)+' '+shlex.quote(backup+'/'+MAIN)))
        require(native.get('native') and native.get('outside_main_preserved'),'Native pipeline preflight failed')
        shadow=r.read(backup+'/native-render.html',private=True).decode('utf-8')
        assert_home(shadow,r.read(backup+'/home-baseline.html',private=True).decode('utf-8'))
        filters=validate_native(r,backup)
        require(snapshot(r)==before,'Protected state drift before activation')
        write_json(r,backup+'/preflight-proof.json',{'pure':pure,'native':native,'filters':filters})
        if prepare:
            print(json.dumps({'status':'PREFLIGHT_ONLY_NO_ACTIVATION','backup':backup,'bundle':digest,'pure':pure,'native':native,'filters':filters}),flush=True);return
        r.sftp.mkdir(DIR+'/'+ASSET,0o755);started=True;r.sftp.chmod(DIR+'/'+ASSET,0o755)
        for name in files:
            target=DIR+'/'+name;stage=target+'.'+uuid.uuid4().hex+'.tmp'
            require(absent(r,target),'Target collision')
            stages[stage]=blobs[name];r.write_new(stage,blobs[name])
            if name.endswith('.php'):r.lint(stage)
            require(sha(r.read(stage))==hashes[name],'Staged hash mismatch')
            r.sftp.chmod(stage,0o644)
            require(absent(r,target),'Target appeared during staging')
            # Standard SFTP rename refuses an existing destination, unlike posix-rename.
            r.sftp.rename(stage,target);stages.pop(stage)
            if name.endswith('.php'):r.lint(target)
        r.purge()
        proof=public_proof(r,files,hashes,baseline.decode('utf-8'))
        filters=validate_native(r,backup)
        require(snapshot(r)==before,'Protected state drift after activation; never rewind customers')
        write_json(r,backup+'/release-proof.json',{'public':proof,'filters':filters,'native':native})
        print(json.dumps({'status':'RELEASED','backup':backup,'bundle':digest,'proof':proof,'filters':filters}),flush=True)
    except BaseException:
        for path,bytes_ in stages.items():
            try:
                if not absent(r,path):
                    actual=r.read(path)
                    if bytes_.startswith(actual):r.remove_owned(path,actual)
            except Exception:
                pass  # Retain foreign temporary bytes; still deactivate owned controller.
        if started:
            rollback(r,files,blobs)
            print(json.dumps({'status':'OWNED_RELEASE_ROLLED_BACK','backup':backup}),flush=True)
        raise
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()
def verify(backup,reverse=False):
    require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact backup required')
    files,blobs,hashes,digest=bundle();r=H.Remote();locked=False
    try:
        saved=json.loads(r.read(backup+'/manifest.json',private=True));require(saved['pins']==hashes,'Saved release differs')
        require(tuple(saved.get('files',()))==files and tuple(saved.get('original_absent',()))==files,'Original absence evidence missing')
        if reverse:
            r.mkdir(LOCK);locked=True;rollback(r,files,blobs)
            data,_=get(URL);require('id="harmat-home-family"' not in data.decode(),'Rollback did not remove presentation')
            print(json.dumps({'status':'ROLLED_BACK_SOURCE_ONLY','backup':backup}));return
        for name in files:
            require(sha(r.read(DIR+'/'+name))==hashes[name],'Released source drift '+name)
            if name.endswith('.php'):r.lint(DIR+'/'+name)
        proof=public_proof(r,files,hashes,r.read(backup+'/public-original.html',private=True).decode('utf-8'))
        filters=validate_native(r,backup);require(snapshot(r)==saved['before'],'Protected source/data drift')
        print(json.dumps({'status':'VERIFIED_HOME_FAMILY','backup':backup,'proof':proof,'filters':filters}),flush=True)
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()
def main():
    parser=argparse.ArgumentParser(description=__doc__);group=parser.add_mutually_exclusive_group()
    group.add_argument('--prepare',action='store_true');group.add_argument('--deploy',action='store_true');group.add_argument('--verify');group.add_argument('--rollback')
    parser.add_argument('--expect');args=parser.parse_args()
    if args.verify or args.rollback:verify(args.verify or args.rollback,bool(args.rollback));return
    files,blobs,hashes,digest=bundle()
    if args.prepare or args.deploy:
        require(args.expect==digest,'Exact reviewed bundle required');deploy(files,blobs,hashes,digest,args.prepare)
    else:print(json.dumps({'mode':'OFFLINE_NO_WRITES','bundle':digest,'pins':hashes},indent=2))
if __name__=='__main__':main()
