"""New Contact-page MU only. Default is offline; live write needs exact bundle pin."""
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

ROOT = Path(__file__).resolve().parents[2]
MAINT = ROOT/'server-config/maintenance'

def module(name, filename):
    spec=importlib.util.spec_from_file_location(name,MAINT/filename)
    result=importlib.util.module_from_spec(spec); spec.loader.exec_module(result)
    return result

H=module('contact_transport','2026-10-14-deploy-appointment-source.py')
I=module('contact_inventory','2026-10-15-deploy-public-inventory.py')
T=module('contact_protected','2026-10-16-deploy-public-trust.py')
DIR=H.LIVE+'/wp-content/mu-plugins'
PREFIX=H.BACKUPS+'/contact-family-'
LOCK=H.BACKUPS+'/.contact-family.lock'
ASSET='harmat-contact-family'
MAIN='zz-harmat-contact-family.php'
FILES=(ASSET+'/body.php',ASSET+'/contact-family.css',ASSET+'/project-garden.jpg',ASSET+'/showroom-model.jpg',ASSET+'/showroom-lounge.jpg',ASSET+'/project-location.jpg',MAIN)
TEST='2026-10-17-test-contact-family.php'
NATIVE='2026-10-17-native-contact-family.php'
URL='https://harmat22.hu/elerhetosegeink/'
EXTRA=('harmat-unified-offer-modal.php','hm-legal-cookie-compliance.php','harmat-seo-structure-cleanup.php','harmat-performance-guard.php')
SELECTOR=('viewer.js','viewer.css','lakaspark-360.php')

def require(value,message):
    if not value: raise RuntimeError(message)

def sha(data): return hashlib.sha256(data).hexdigest()

def bundle():
    blobs={name:(ROOT/'wp-mu-plugins'/name).read_bytes() for name in FILES}
    for name in FILES:
        if name.endswith(('.php','.css')): blobs[name]=blobs[name].replace(b'\r\n',b'\n')
    blobs[TEST]=(MAINT/TEST).read_bytes().replace(b'\r\n',b'\n')
    blobs[NATIVE]=(MAINT/NATIVE).read_bytes().replace(b'\r\n',b'\n')
    pins={name:sha(data) for name,data in blobs.items()}
    digest=sha(json.dumps(pins,sort_keys=True,separators=(',',':')).encode())
    return blobs,pins,digest

def absent(r,path):
    try: r.info(path); return False
    except FileNotFoundError: return True

def snapshot(r):
    result=T.snapshot(r)
    result['extra']={name:sha(r.read(DIR+'/'+name)) for name in EXTRA}
    result['about']={name:sha(r.read(DIR+'/'+name)) for name in ('zz-harmat-about-family.php','harmat-about-family/body.php','harmat-about-family/about-family.css','harmat-about-family/harmat-courtyard.jpg','harmat-about-family/harmat-living-room.jpg')}
    result['selector']={name:sha(r.read(H.LIVE+'/wp-content/plugins/360/'+name)) for name in SELECTOR}
    result['reserved_view']=sha(r.read(DIR+'/zz-harmat-reserved-virtual-view.php'))
    result['htaccess']=sha(r.read(H.LIVE+'/.htaccess'))
    return result

class Page(HTMLParser):
    def __init__(self,text):
        super().__init__(convert_charrefs=True); self.h1=[]; self.current=None; self.ids=[]; self.images=[]; self.meta={}; self.canonicals=[]; self.feed(text)
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if 'id' in a: self.ids.append(a['id'])
        if tag=='h1': self.current=[]
        if tag=='br' and self.current is not None: self.current.append(' ')
        if tag=='img': self.images.append(a.get('src',''))
        if tag=='link' and a.get('rel')=='canonical':
            self.meta['canonical']=a.get('href'); self.canonicals.append(a.get('href'))
    def handle_endtag(self,tag):
        if tag=='h1' and self.current is not None: self.h1.append(' '.join(''.join(self.current).split())); self.current=None
    def handle_data(self,text):
        if self.current is not None: self.current.append(text)

def assert_page(text):
    page=Page(text)
    require(page.ids.count('harmat-contact-family')==1 and page.h1==['Harmat Lakópark'],'Contact root/visible H1 mismatch')
    require(page.canonicals==[URL],'Contact canonical changed or duplicated')
    for marker in ('h22-offer-modal','h22-offer-form','hm-cookie-settings-button','masthead','colophon'):
        require(page.ids.count(marker)==1,'Native shell/form/cookie marker missing or duplicated: '+marker)
    require('class="harmat-contact-scene"' not in text and 'Your Name' not in text,'Old Contact content remains')
    require('src="assets/' not in text,'Unresolved preview image path')
    for image in ('project-garden.jpg','showroom-model.jpg','showroom-lounge.jpg','project-location.jpg'):
        require('https://harmat22.hu/wp-content/mu-plugins/'+ASSET+'/'+image in page.images,'Owned public image missing')
    for marker in ('tel:+36300733375','mailto:ertekesites@harmat22.hu','#opal-contactform-popup','Látványterv','09:00','17:00'):
        require(marker in text,'Contact detail or native trigger missing: '+marker)
    require('<iframe' not in text.split('id="harmat-contact-family"',1)[1].split('id="colophon"',1)[0],'Unexpected Contact iframe')
    return {'root':1,'h1':page.h1,'native_form':True,'canonical':URL}

def http(url):
    request=urllib.request.Request(url,headers={'User-Agent':'HarmatOfferSafety/1.0'})
    with urllib.request.urlopen(request,timeout=30) as response:
        data=response.read(2*1024*1024+1)
        require(response.status==200 and response.geturl()==url and len(data)<=2*1024*1024,'HTTP identity/size/status failed')
        return data,response.headers.get('Content-Type','')

def verify_files(r,pins):
    for name in FILES:
        require(sha(r.read(DIR+'/'+name))==pins[name],'Released file changed: '+name)
        if name.endswith('.php'): r.lint(DIR+'/'+name)

def public_proof(r,pins):
    data,mime=http(URL); require('text/html' in mime,'Contact not HTML')
    result=assert_page(data.decode('utf-8'))
    for name in FILES[:-1]:
        if name.endswith('.php'): continue
        data,mime=http('https://harmat22.hu/wp-content/mu-plugins/'+name)
        require(sha(data)==pins[name],'Public asset bytes mismatch')
        require(('text/css' if name.endswith('.css') else 'image/jpeg') in mime,'Public asset MIME mismatch')
    with T.bounded_shared_requests():
        result['health']=I.HEALTH.smoke()
        result['inventory']=I.public_check(r)
    return result

def write_json(r,path,value): r.write_new(path,json.dumps(value,sort_keys=True,ensure_ascii=True,indent=2).encode())

def rollback(r,backup,manifest,blobs):
    for name in reversed(FILES):
        path=DIR+'/'+name
        if not absent(r,path):
            require(sha(r.read(path))==manifest['pins'][name],'Owned rollback refused: changed file')
            r.remove_owned(path,blobs[name])
    if not absent(r,DIR+'/'+ASSET): r.sftp.rmdir(DIR+'/'+ASSET)
    r.purge()

def deploy(blobs,pins,digest):
    r=H.Remote(); locked=False; backup=PREFIX+uuid.uuid4().hex; activated=False; manifest=None; stages={}
    try:
        r.directory(DIR); r.directory(H.BACKUPS)
        r.mkdir(LOCK); locked=True
        require(absent(r,DIR+'/'+MAIN) and absent(r,DIR+'/'+ASSET),'New release paths already exist')
        r.mkdir(backup); r.mkdir(backup+'/'+ASSET)
        before=snapshot(r)
        manifest={'bundle':digest,'pins':pins,'before':before,'original_absent':list(FILES)}
        write_json(r,backup+'/manifest.json',manifest)
        page_backup=r.run(H.WP+' eval '+shlex.quote("$p=get_post(26); if (!$p || $p->post_name!=='elerhetosegeink') throw new RuntimeException('Wrong page'); echo wp_json_encode(array('post'=>(array)$p,'meta'=>get_post_meta(26)));"))
        r.write_new(backup+'/original-page.json',page_backup.encode())
        for name in list(I.CRITICAL)+list(T.OTHER_SOURCES.values())+[DIR+'/'+n for n in EXTRA]+[H.LIVE+'/.htaccess']:
            filename='original-'+sha(name.encode())[:12]+'.bin'
            r.write_new(backup+'/'+filename,r.read(name))
        for name,data in blobs.items():
            r.write_new(backup+'/'+name,data)
            if name.endswith('.php'): r.lint(backup+'/'+name)
        fixture=r.run(H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/'+MAIN))
        r.write_new(backup+'/fixture-result.txt',fixture.encode())
        require('PASS' in fixture,'Isolated PHP fixture did not pass')
        native=r.run(H.PHP+' '+shlex.quote(backup+'/'+NATIVE)+' '+shlex.quote(backup+'/'+MAIN))
        native_info=json.loads(native); require(native_info['native'] is True,'No native preflight')
        assert_page(r.read(backup+'/native-render.html',private=True).decode('utf-8'))
        require(snapshot(r)==before,'Protected state changed before activation')
        r.sftp.mkdir(DIR+'/'+ASSET,0o755); r.sftp.chmod(DIR+'/'+ASSET,0o755)
        for name in FILES:
            target=DIR+'/'+name; stage=target+'.'+uuid.uuid4().hex+'.tmp'
            require(absent(r,target),'New target collision')
            stages[stage]=blobs[name]
            r.write_new(stage,blobs[name])
            if name.endswith('.php'): r.lint(stage)
            require(sha(r.read(stage))==pins[name],'Stage hash mismatch')
            r.sftp.chmod(stage,0o644)
            r.sftp.posix_rename(stage,target)
            stages.pop(stage)
            if name==MAIN: activated=True
            if name.endswith('.php'): r.lint(target)
        r.purge(); verify_files(r,pins)
        proof=public_proof(r,pins)
        require(snapshot(r)==before,'Protected state changed after activation; never restore business data')
        write_json(r,backup+'/release-proof.json',{'native':native_info,'proof':proof,'pins':pins})
        print(json.dumps({'status':'RELEASED','backup':backup,'bundle':digest,'proof':proof},ensure_ascii=True))
    except BaseException:
        for stage,data in stages.items():
            if not absent(r,stage): r.remove_owned(stage,data)
        if manifest is not None:
            # Remove only this exact originally absent release, including partial asset upload.
            rollback(r,backup,manifest,blobs)
            print(json.dumps({'status':'OWNED_NEW_FILES_ROLLED_BACK','backup':backup,'was_activated':activated}),flush=True)
        raise
    finally:
        if locked: r.sftp.rmdir(LOCK)
        r.close()

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--deploy',action='store_true'); p.add_argument('--expect'); p.add_argument('--verify')
    args=p.parse_args(); blobs,pins,digest=bundle()
    if args.verify:
        require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',args.verify),'Exact backup required')
        r=H.Remote()
        try:
            manifest=json.loads(r.read(args.verify+'/manifest.json',private=True))
            require(manifest['pins']==pins,'Local release differs from saved release')
            verify_files(r,pins); proof=public_proof(r,pins)
            require(snapshot(r)==manifest['before'],'Protected state drift; do not restore business data')
            print(json.dumps({'status':'VERIFIED','backup':args.verify,'proof':proof}))
        finally: r.close()
    elif args.deploy:
        require(args.expect==digest,'Exact reviewed bundle pin required')
        deploy(blobs,pins,digest)
    else: print(json.dumps({'mode':'OFFLINE_NO_WRITES','bundle':digest,'pins':pins},indent=2))

if __name__=='__main__': main()
