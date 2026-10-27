"""New route-only presentation. Default offline; private preflight before activation."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import stat
import time
import uuid
from lxml import html

HERE=Path(__file__).resolve().parent
ROOT=HERE.parents[1]
spec=importlib.util.spec_from_file_location('neighborhood_transport',HERE/'2026-10-23-home-showroom.py')
M=importlib.util.module_from_spec(spec);spec.loader.exec_module(M)
H,Q,D,P=M.H,M.Q,M.D,M.P
require,sha=M.require,M.sha
DIR=H.LIVE+'/wp-content/mu-plugins'
ASSET='harmat-neighborhood-practical'
MAIN='zz-harmat-neighborhood-practical.php'
TEST='2026-10-27-native-neighborhood.php'
PREFIX=H.BACKUPS+'/neighborhood-practical-'
LOCK=H.BACKUPS+'/.neighborhood-practical.lock'
ROUTE='/harmat-lakopark-kornyeke/'
ROUTES=(ROUTE,'/','/harmat-lakopark/','/magunkrol/','/lakaskereso/','/property/a1-f-l1/',
        '/virtualis-lakasvalaszto/','/virtualis-lakasvalaszto-elso-utem/',
        '/virtualis-lakasvalaszto-a1-epulet/','/elerhetosegeink/')
OUT=ROOT/'outputs/2026-10-27-neighborhood-release'

def local(name):return (ROOT/'wp-mu-plugins'/name).read_bytes().replace(b'\r\n',b'\n')
def encoded(value):return json.dumps(value,sort_keys=True,separators=(',',':')).encode()
def absent(r,path):return D.absent(r,path)

def bundle():
    manifest=local(ASSET+'/manifest.json');pins=json.loads(manifest)
    require(list(pins)==['body.html','neighborhood.css','neighborhood.js','lucide.js','LICENSE-lucide.txt'],'Exact asset set')
    blobs={ASSET+'/'+name:local(ASSET+'/'+name) for name in (*pins,'manifest.json')}
    require(all(sha(blobs[ASSET+'/'+n])==pin for n,pin in pins.items()),'Asset pin drift')
    blobs[MAIN]=local(MAIN)
    require(sha(manifest).encode() in blobs[MAIN],'Manifest controller identity')
    require(b'get_queried_object_id() !== 3959' in blobs[MAIN] and b"'GET','HEAD'" in blobs[MAIN],'Route/method guard')
    require(not re.search(rb'\b(?:wp_mail|register_rest_route|update_option|update_post_meta|wp_insert_post|rest_do_request)\s*\(',blobs[MAIN]),'Presentation only')
    helpers={TEST:(HERE/TEST).read_bytes().replace(b'\r\n',b'\n'),D.CHECKER:(HERE/D.CHECKER).read_bytes().replace(b'\r\n',b'\n')}
    descriptor={'files':{n:sha(b) for n,b in blobs.items()},'helpers':{n:sha(b) for n,b in helpers.items()},'route':ROUTE,'policy':'NEW_ONLY_CONTROLLER_LAST_SOURCE_ONLY_RECOVERY'}
    return blobs,helpers,descriptor,sha(encoded(descriptor))

def sources(r):
    paths=[DIR+'/'+n for n in ('harmat-performance-guard.php','zz-harmat-home-family.php',
      'zz-harmat-internal-language.php','harmat-home-family/manifest-showroom.json',
      'harmat-home-family/pannellum.js','harmat-unified-offer-modal.php')]
    paths += [H.LIVE+'/wp-content/plugins/harmat22-map-redesign/'+n for n in ('harmat22-map-redesign.php','assets/map-redesign.js')]
    return {p:sha(r.read(p)) for p in paths}

def state(r):
    value=P.state(r,{})
    value['retained_neighborhood']=sources(r)
    # The inherited snapshot excludes the homepage controller for older releases.
    value['retained_neighborhood'][DIR+'/zz-harmat-home-family.php']=sha(r.read(DIR+'/zz-harmat-home-family.php'))
    return value

def pages():
    result={}
    for route in ROUTES:
        time.sleep(1.5)
        blob,kind=Q.get('https://harmat22.hu'+route)
        require('text/html' in kind,'Expected public HTML '+route)
        result[route]=blob
    return result

def normalized(node):
    node=html.fromstring(html.tostring(node))
    for n in node.xpath('//*[@name="_harmat_offer_nonce"]'):n.attrib.pop('value',None)
    return html.tostring(node)

def prove(before,after,active):
    for route in ROUTES:
        a,b=D.Page(before[route].decode()),D.Page(after[route].decode())
        require(a.form==b.form and a.form and a.seo==b.seo and a.canonical==b.canonical,'Native form/SEO changed '+route)
        x,y=html.fromstring(before[route]),html.fromstring(after[route])
        for ident in ('masthead','colophon','h22-offer-modal','harmat-internal-language'):
            aa,bb=x.xpath('//*[@id=$id]',id=ident),y.xpath('//*[@id=$id]',id=ident)
            require(len(aa)==len(bb)==1 and normalized(aa[0])==normalized(bb[0]),'Native shell changed '+ident+' '+route)
        body=after[route].decode()
        if route!=ROUTE:
            ident='content' if route.startswith('/property/') else 'main'
            aa,bb=x.xpath('//*[@id=$id]',id=ident),y.xpath('//*[@id=$id]',id=ident)
            require(len(aa)==len(bb)==1 and normalized(aa[0])==normalized(bb[0]),'Other page content changed '+route)
            require(ASSET not in body,'New module leaked outside route')
        else:
            root=y.xpath('//*[@id="harmat-neighborhood-practical"]')
            require(len(root)==int(active),'Wrong neighborhood activation state')
            if active:
                require(len(root[0].xpath('.//article[@class="place-row"]'))==17,'Public POI loss')
                require(len(y.xpath('//h1'))==1,'Heading duplication')
                require('harmat22-map-redesign-js' not in body and 'harmat-neighborhood-page-20260531' not in body,'Legacy route module still active')
                require(not root[0].xpath('.//form|.//header|.//footer|.//iframe'),'Preview shell/form leak')
                require('Offline előnézet' not in body and '/media/' not in html.tostring(root[0]).decode(),'Preview URL leak')
                expected=json.loads((ROOT/'server-config/previews/neighborhood-practical/facilities.json').read_text(encoding='utf-8'))
                require(root[0].xpath('.//h4[@class="place-name"]/text()')==[p['name'] for p in expected['places']],'Public place names changed')
                require(root[0].xpath('.//span[@class="place-distance"]/text()')==[p['distance'] for p in expected['places']],'Public distances changed')
    return {'routes':len(ROUTES),'native_form_seo_shell_unchanged':True,'other_main_unchanged':True,'places':17,'submission':False}

def filters(r,backup):return D.validate_native(r,backup)

def backup_read(r,path):return json.loads(r.read(path,private=True))
def write_json(r,path,value):r.write_new(path,json.dumps(value,ensure_ascii=True,indent=2).encode())

def preflight(blobs,helpers,descriptor,digest):
    r=H.Remote();backup=PREFIX+uuid.uuid4().hex;locked=False
    try:
        r.directory(H.BACKUPS);r.directory(DIR);r.mkdir(LOCK);locked=True
        require(absent(r,DIR+'/'+MAIN) and absent(r,DIR+'/'+ASSET),'New paths already exist')
        before=state(r);baseline=pages()
        require(ASSET.encode() not in baseline[ROUTE],'Unexpected prior activation')
        r.mkdir(backup);r.mkdir(backup+'/'+ASSET)
        print(json.dumps({'status':'PRIVATE_BACKUP_STARTED','backup':backup}),flush=True)
        for i,(route,blob) in enumerate(baseline.items()):r.write_new(backup+'/page-'+str(i)+'.html',blob)
        original=r.run(H.WP+' eval '+shlex.quote('$p=get_post(3959);echo wp_json_encode(array("post"=>(array)$p,"meta"=>get_post_meta(3959)));')).encode()
        r.write_new(backup+'/original-page.json',original)
        for path in before['retained_neighborhood']:
            r.write_new(backup+'/original-'+sha(path.encode())[:16]+'.bin',r.read(path))
        for name,blob in {**blobs,**helpers}.items():
            r.write_new(backup+'/'+name,blob)
            if name.endswith('.php'):r.lint(backup+'/'+name)
        saved={'descriptor':descriptor,'digest':digest,'before':before,'baseline_routes':list(baseline),'original_absent':list(blobs)}
        write_json(r,backup+'/manifest.json',saved)
        native=json.loads(r.run(H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/'+MAIN)))
        require(native.get('status')=='PASS','Private native preflight failed')
        shadow={**baseline,ROUTE:r.read(backup+'/native-render.html',private=True)}
        proof=prove(baseline,shadow,True)
        pure=filters(r,backup)
        require(state(r)==before,'Protected state drift before activation; no business restore')
        write_json(r,backup+'/preflight-proof.json',{'native':native,'public_contract':proof,'pure_filters':pure})
        print(json.dumps({'status':'PREFLIGHT_READY_NO_LIVE_ACTIVATION','backup':backup,'bundle':digest,'native':native,'proof':proof,'filters':pure}),flush=True)
        return backup
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()

def validate_backup(r,backup,descriptor,digest):
    require(re.fullmatch(re.escape(PREFIX)+'[a-f0-9]{32}',backup),'Exact private backup path required')
    saved=backup_read(r,backup+'/manifest.json')
    require(saved['descriptor']==descriptor and saved['digest']==digest,'Backup bundle differs')
    return saved,{route:r.read(backup+'/page-'+str(i)+'.html',private=True) for i,route in enumerate(saved['baseline_routes'])}

def owned_remove(r,blobs):
    # Deactivate first. Never delete foreign files or restore customer/database data.
    if not absent(r,DIR+'/'+MAIN):r.remove_owned(DIR+'/'+MAIN,blobs[MAIN])
    for name,blob in reversed(tuple(blobs.items())):
        if name==MAIN:continue
        if not absent(r,DIR+'/'+name):r.remove_owned(DIR+'/'+name,blob)
    if not absent(r,DIR+'/'+ASSET):r.sftp.rmdir(DIR+'/'+ASSET)
    r.purge()

def operate(backup,blobs,descriptor,digest,activate=False,reverse=False):
    r=H.Remote();locked=False;started=False;stages={}
    try:
        r.directory(H.BACKUPS);r.directory(DIR)
        if activate or reverse:r.mkdir(LOCK);locked=True
        saved,baseline=validate_backup(r,backup,descriptor,digest)
        if reverse:
            owned_remove(r,blobs)
            proof=prove(baseline,pages(),False)
            print(json.dumps({'status':'ROLLED_BACK_SOURCE_ONLY','backup':backup,'proof':proof}),flush=True);return
        if activate:
            require(absent(r,DIR+'/'+MAIN) and absent(r,DIR+'/'+ASSET),'Activation paths not absent')
            require(backup_read(r,backup+'/preflight-proof.json')['native']['status']=='PASS','Missing private preflight')
            require(state(r)==saved['before'],'Protected state changed since preflight; stop without restoring data')
            r.sftp.mkdir(DIR+'/'+ASSET,0o755);r.sftp.chmod(DIR+'/'+ASSET,0o755);started=True
            for name,blob in blobs.items():
                target=DIR+'/'+name;stage=target+'.'+uuid.uuid4().hex+'.tmp'
                require(absent(r,target),'Activation collision')
                stages[stage]=blob;r.write_new(stage,blob)
                if name.endswith('.php'):r.lint(stage)
                r.sftp.chmod(stage,0o644)
                info=r.info(stage)
                require(info.st_uid==1608 and info.st_gid==1611 and stat.S_IMODE(info.st_mode)==0o644 and r.read(stage)==blob,'Staging identity')
                require(absent(r,target),'Target appeared during staging')
                r.sftp.rename(stage,target);stages.pop(stage)
            r.purge()
        for name,blob in blobs.items():require(r.read(DIR+'/'+name)==blob,'Release source changed '+name)
        r.lint(DIR+'/'+MAIN)
        after=pages();proof=prove(baseline,after,True);pure=filters(r,backup)
        for name in ('neighborhood.css','neighborhood.js','lucide.js'):
            body,kind=Q.get('https://harmat22.hu/wp-content/mu-plugins/'+ASSET+'/'+name)
            require(body==blobs[ASSET+'/'+name],'Public asset mismatch '+name)
        require(state(r)==saved['before'],'Protected state drift after release; never rewind customers')
        event='RELEASED' if activate else 'VERIFIED'
        if activate:write_json(r,backup+'/release-proof.json',{'public':proof,'pure_filters':pure,'sources':descriptor['files']})
        OUT.mkdir(parents=True,exist_ok=True)
        (OUT/('released.html' if activate else 'verified.html')).write_bytes(after[ROUTE])
        print(json.dumps({'status':event,'backup':backup,'bundle':digest,'proof':proof,'filters':pure}),flush=True)
    except BaseException:
        for path,blob in stages.items():
            if not absent(r,path):
                actual=r.read(path)
                if blob.startswith(actual):r.remove_owned(path,actual)
        if started:
            owned_remove(r,blobs)
            print(json.dumps({'status':'OWNED_RELEASE_ROLLED_BACK','backup':backup}),flush=True)
        raise
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()

def main():
    parser=argparse.ArgumentParser(description=__doc__);group=parser.add_mutually_exclusive_group()
    group.add_argument('--prepare',action='store_true');group.add_argument('--activate');group.add_argument('--verify');group.add_argument('--rollback')
    parser.add_argument('--expect');args=parser.parse_args()
    blobs,helpers,descriptor,digest=bundle()
    if not any((args.prepare,args.activate,args.verify,args.rollback)):
        print(json.dumps({'status':'OFFLINE_NO_WRITES','bundle':digest,'descriptor':descriptor},indent=2));return
    require(args.expect==digest,'Exact reviewed bundle required')
    if args.prepare:preflight(blobs,helpers,descriptor,digest)
    else:operate(args.activate or args.verify or args.rollback,blobs,descriptor,digest,bool(args.activate),bool(args.rollback))
if __name__=='__main__':main()
