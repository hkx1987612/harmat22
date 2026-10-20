"""Preview first, then exact bounded footer update. No quote/appointment writes."""
import argparse
import difflib
import importlib.util
import json
from pathlib import Path
import re
import shlex
import subprocess
import uuid

from lxml import html

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('footer_prior',HERE/'2026-10-20-deploy-home-six-r2.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
D,H,Q = M.D,M.H,M.Q
require,sha = M.require,M.sha
NAME = 'harmat-migrated-snippets.php'
TARGET = D.DIR+'/'+NAME
TEST = '2026-10-20-test-footer.php'
FIXTURE_CHECKS = 477
PREFIX = H.BACKUPS+'/footer-family-'
PREVIEW_ROOT = H.LIVE+'/wp-content/uploads'
OLD_LF = '588e2740b09e02aff24f6d5ed3d89d34a76d3c142518c1a3c38e5e81646db2aa'
OLD_RAW = '76dcc12c8caf5aeae64f05ac54e0385f1ac1267470497c003e77ee45d48d287e'
NEW_LF = '32868104d0da9d2a85ba0b779db408fe1688e8ec1ea3da4fda3615b3e8f92b19'
ROUTES = ('/','/lakaskereso/','/property/a1-f-l1/','/virtualis-lakasvalaszto/',
          '/virtualis-lakasvalaszto-elso-utem/','/virtualis-lakasvalaszto-a2-epulet/',
          '/galeria/','/elerhetosegeink/','/epitesi-naplo/','/finanszirozas/')


def layout(blob):
    match = re.search(rb'<style id="harmat-migrated-public-layout-css">([\s\S]*?)</style>',blob)
    require(match is not None,'Unique public layout style required')
    return match[0]


def saved_metadata(value):
    require(isinstance(value,list) and len(value) == 3 and all(type(n) is int for n in value),
            'Exact numeric metadata required')
    return tuple(value)


def release():
    old = subprocess.check_output(['git','show','d608550:wp-mu-plugins/'+NAME],cwd=ROOT)
    new = (ROOT/'wp-mu-plugins'/NAME).read_bytes().replace(b'\r\n',b'\n')
    require(sha(old) == OLD_LF and NEW_LF and sha(new) == NEW_LF,'Exact reviewed footer source required')
    original = b''.join(line if i in (4,175,176,177) else line.replace(b'\n',b'\r\n')
                        for i,line in enumerate(old.splitlines(keepends=True)))
    require(sha(original) == OLD_RAW,'Exact mixed-EOL baseline required')
    raw_lines = original.splitlines(keepends=True)
    old_lines,new_lines = old.splitlines(keepends=True),new.splitlines(keepends=True)
    parts = []
    for tag,a,b,c,d in difflib.SequenceMatcher(None,old_lines,new_lines,autojunk=False).get_opcodes():
        if tag == 'equal':
            parts.extend(raw_lines[a:b])
        else:
            for offset,line in enumerate(new_lines[c:d]):
                use_lf = b-a == d-c and a+offset in (4,175,176,177)
                parts.append(line if use_lf else line.replace(b'\n',b'\r\n'))
    candidate = b''.join(parts)
    require(candidate.replace(b'\r\n',b'\n') == new,'Raw reconstruction failed')
    helpers = {n:(HERE/n).read_bytes().replace(b'\r\n',b'\n') for n in (TEST,D.CHECKER)}
    helpers['footer-dictionary.json'] = (ROOT/'wp-mu-plugins/harmat-internal-language/zh-Hans.json').read_bytes().replace(b'\r\n',b'\n')
    edits,_,protected,_,_ = M.release()
    protected = {**protected,**{p.removeprefix(D.DIR+'/'):b for p,(_,b) in edits.items()}}
    pins = {'old':sha(original),'new':sha(candidate),'normalized_new':sha(new),
            'protected':{p:sha(b) for p,b in protected.items()},'helpers':{p:sha(b) for p,b in helpers.items()}}
    return original,candidate,helpers,protected,pins,sha(Q.P.encoded(pins))


def state(r,protected):
    for name,blob in protected.items():
        require(r.read(D.DIR+'/'+name) == blob,'Protected home owner changed: '+name)
    value = Q.snapshot(r)
    value['sources'].pop(TARGET,None)
    value['protected'] = json.loads(r.run(H.WP+' eval '+shlex.quote(M.business_snapshot_code())))
    return value


def render(r,backup):
    value = json.loads(r.run('HARMAT_FOOTER_DICTIONARY='+shlex.quote(backup+'/footer-dictionary.json')+' '+H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/candidate.php')+' --render'))
    require(set(value) == {'html','css'} and value['html'].count('id="colophon"') == 1,'Bad fixture rendering')
    require(not re.search(r'<(?:form|input|textarea|iframe|script)\b',value['html'],re.I),'Active preview content refused')
    return value


def fixture(r,backup):
    output = r.run('HARMAT_FOOTER_DICTIONARY='+shlex.quote(backup+'/footer-dictionary.json')+' '+H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/candidate.php'))
    match = re.fullmatch(r'PASS footer: (\d+) assertions; pure extraction, no WordPress/network\.\s*',output)
    require(match is not None and int(match[1]) == FIXTURE_CHECKS,'Staged footer fixture failed')
    return {'status':'PASS','checks':int(match[1])}


def preview_document(home,value):
    tree = html.fromstring(home)
    resources = []
    for node in tree.xpath('//head/link[@rel="stylesheet"]|//head/style'):
        if node.tag == 'style' and node.get('id') == 'harmat-migrated-public-layout-css':
            continue
        resources.append(html.tostring(node,encoding='unicode'))
    body = tree.xpath('//body')[0]
    classes = body.get('class','')
    # Only reviewed public markup/styles, no copied forms, nonces or native scripts.
    control = '<div id="harmat-internal-language" role="group" aria-label="Language / 语言" translate="no"><button data-hil-language="hu" type="button" lang="hu" aria-pressed="true">Magyar</button><button data-hil-language="zh-Hans" type="button" lang="zh-Hans" aria-pressed="false">中文</button><span data-hil-status role="status" class="screen-reader-text"></span></div>'
    base = 'https://harmat22.hu/wp-content/mu-plugins/harmat-internal-language/'
    config = '<script type="application/json" id="harmat-internal-language-config">'+json.dumps({'dictionary':base+'zh-Hans.json?v=7651d634166af48b7'})+'</script>'
    scripts = ''.join('<script defer src="'+base+n+'"></script>' for n in ('runtime.js','patterns.js','bootstrap.js'))
    top = '<header class="hff-preview-head"><a href="https://harmat22.hu/">Harmat Lakópark</a>'+control+'</header>'
    css = '<style>'+value['css']+'</style><style>html,body{margin:0;padding:0;min-width:0;background:#f3f4f1}.hff-preview-head{min-height:88px;display:flex;gap:18px;align-items:center;justify-content:space-between;max-width:1180px;margin:auto;padding:20px 24px}.hff-preview-head>a{font-size:24px;color:#244b3c}.hff-preview-head #harmat-internal-language{position:static!important;margin:0;flex-shrink:0}.hff-preview-context{height:110px;background:#f3f4f1;border-top:1px solid #e0e5df}@media(max-width:620px){.hff-preview-head{padding:16px 20px;min-height:76px}.hff-preview-head>a{font-size:20px}.hff-preview-context{height:54px}}</style>'
    result = '<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Harmat Lakópark</title>'+''.join(resources)+css+'</head><body class="'+classes+'">'+top+'<div class="hff-preview-context"></div>'+value['html']+config+scripts+'</body></html>'
    parsed = html.fromstring(result)
    require(not parsed.xpath('//form|//input|//textarea|//iframe') and '_nonce' not in result,'Preview contains non-public active fields')
    return result.encode('utf-8')


def public(r,baseline,active):
    result = {}
    for route,old in baseline.items():
        data,mime = Q.get('https://harmat22.hu'+route)
        require('text/html' in mime,'Public HTML unavailable')
        new,prior = D.Page(data.decode('utf-8')),D.Page(old.decode('utf-8'))
        require(new.form == prior.form and new.form and new.seo == prior.seo and new.canonical == prior.canonical,
                'Native form/SEO changed: '+route)
        tree = html.fromstring(data)
        footer = tree.xpath('//footer[@id="colophon"]')
        require(len(footer) == 1 and not footer[0].xpath('.//form|.//input|.//textarea'),'Footer identity/form changed')
        require(len(tree.xpath('//*[@id="h22-offer-modal"]')) == 1,'Native modal missing')
        require(len(footer[0].xpath('.//nav[@aria-label="Jogi dokumentumok"]/a')) == 4,'Legal links changed')
        for tag in ('img','video','source','iframe'):
            attrs = lambda root: [(n.get('src'),n.get('srcset'),n.get('poster'),n.get('data-src')) for n in root.xpath('//'+tag)]
            require(attrs(tree) == attrs(html.fromstring(old)),'Public media changed: '+route)
        if active:
            require(len(footer[0].xpath('.//a[@href="https://harmat22.hu/epitesi-naplo/"]')) == 1,'Journal footer link missing')
            require(len(footer[0].xpath('.//a[@href="tel:+36300733375"]')) == 1,'Sales phone changed')
            require(len(footer[0].xpath('.//a[@href="mailto:ertekesites@harmat22.hu"]')) == 1,'Sales email changed')
            require('#193e34' in data.decode('utf-8').lower(),'Footer palette missing')
        result[route] = {'status':200,'native_form_unchanged':True,'native_seo_unchanged':True,'footer':True}
    with D.T.bounded_shared_requests():
        inventory = D.I.public_check(r)
    return {'routes':result,'inventory':inventory}


def prepare(bundle):
    old,new,helpers,protected,pins,digest = bundle
    backup = PREFIX+uuid.uuid4().hex
    preview_path = PREVIEW_ROOT+'/harmat-footer-preview-'+uuid.uuid4().hex+'.html'
    r = H.Remote();written = False;preview = None
    try:
        meta = Q.metadata(r,TARGET,old)
        before = state(r,protected)
        r.mkdir(backup)
        for name,data in {'original.php':old,'candidate.php':new,**helpers}.items():
            r.write_new(backup+'/'+name,data)
            if name.endswith('.php'):r.lint(backup+'/'+name)
        test = fixture(r,backup)
        baseline = {route:Q.get('https://harmat22.hu'+route)[0] for route in ROUTES}
        for i,(route,data) in enumerate(baseline.items()):
            r.write_new(backup+'/baseline-'+str(i)+'.html',data)
        preview = preview_document(baseline['/'].decode('utf-8'),render(r,backup))
        require(state(r,protected) == before,'Protected state drift in preparation')
        require(Q.P.absent(r,preview_path),'Preview collision')
        r.write_new(preview_path,preview);written = True;r.stage_metadata(preview_path,meta)
        url = 'https://harmat22.hu/wp-content/uploads/'+Path(preview_path).name
        served,mime = Q.get(url)
        require(served == preview and 'text/html' in mime,'Public preview mismatch')
        D.write_json(r,backup+'/manifest.json',{'pins':pins,'digest':digest,'metadata':meta,'preview_path':preview_path,
                     'preview_sha':sha(preview),'preview_url':url,'routes':list(baseline),'baseline':{p:sha(b) for p,b in baseline.items()}})
        print(json.dumps({'status':'PREVIEW_READY_FORMAL_SITE_UNCHANGED','backup':backup,'aggregate':digest,'url':url,'fixture':test}),flush=True)
    except BaseException:
        if written:
            current = r.read(preview_path)
            require(current == preview,'Foreign preview retained; manual review required')
            r.remove_owned(preview_path,current)
        raise
    finally:
        r.close()


def operate(bundle,backup,mode):
    old,new,helpers,protected,pins,digest = bundle
    require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup),'Exact backup required')
    r = H.Remote();locked=attempted=False
    try:
        saved = json.loads(r.read(backup+'/manifest.json',private=True))
        require(saved['pins'] == pins and saved['digest'] == digest,'Saved release differs')
        saved['metadata'] = saved_metadata(saved['metadata'])
        require(re.fullmatch(re.escape(PREVIEW_ROOT+'/harmat-footer-preview-')+r'[a-f0-9]{32}\.html',saved['preview_path']),
                'Exact owned public preview path required')
        require(r.read(backup+'/original.php',private=True) == old and r.read(backup+'/candidate.php',private=True) == new,'Private source changed')
        for name,data in helpers.items():require(r.read(backup+'/'+name,private=True) == data,'Saved checker changed')
        baseline = {p:r.read(backup+'/baseline-'+str(i)+'.html',private=True) for i,p in enumerate(saved['routes'])}
        require({p:sha(b) for p,b in baseline.items()} == saved['baseline'],'Saved public baseline changed')
        if mode == 'cleanup':
            if not Q.P.absent(r,saved['preview_path']):
                current = r.read(saved['preview_path'])
                require(sha(current) == saved['preview_sha'],'Foreign preview retained')
                r.remove_owned(saved['preview_path'],current)
            print(json.dumps({'status':'PREVIEW_REMOVED'}));return
        r.mkdir(D.LOCK);locked=True
        expected = old if mode == 'deploy' else new
        require(Q.metadata(r,TARGET,expected) == tuple(saved['metadata']),'Formal source ownership changed')
        before = state(r,protected)
        test = fixture(r,backup)
        filters = D.validate_native(r,backup)
        require(state(r,protected) == before,'Protected state drift in preflight')
        if mode in ('deploy','rollback'):
            if mode == 'deploy':
                require(Q.P.absent(r,saved['preview_path']),'Remove reviewed preview before activation')
                D.write_json(r,backup+'/activation-before-'+uuid.uuid4().hex+'.json',before)
            attempted=True
            Q.replace(r,TARGET,expected,new if mode == 'deploy' else old,saved['metadata'])
            r.purge()
        result = public(r,baseline,mode != 'rollback')
        filters = D.validate_native(r,backup)
        require(state(r,protected) == before,'Protected state changed during release/check; never restore customer data')
        if mode == 'deploy':D.write_json(r,backup+'/release.json',{'public':result,'fixture':test,'filters':filters})
        print(json.dumps({'status':{'deploy':'RELEASED','verify':'VERIFIED','rollback':'ROLLED_BACK_SOURCE_ONLY'}[mode],
                          'backup':backup,'aggregate':digest,'proof':result,'fixture':test,'filters':filters}),flush=True)
    except BaseException:
        if attempted and mode == 'deploy':
            current = r.read(TARGET)
            require(current in (old,new),'Foreign source retained; manual recovery required')
            if current == new:Q.replace(r,TARGET,new,old,saved['metadata']);r.purge()
            print(json.dumps({'status':'OWNED_SOURCE_ROLLED_BACK','backup':backup}),flush=True)
        raise
    finally:
        try:
            if locked:r.sftp.rmdir(D.LOCK)
        finally:r.close()


def main():
    p = argparse.ArgumentParser(description=__doc__)
    g = p.add_mutually_exclusive_group()
    g.add_argument('--prepare',action='store_true');g.add_argument('--deploy');g.add_argument('--verify');g.add_argument('--rollback');g.add_argument('--cleanup')
    p.add_argument('--expect');p.add_argument('--preview-reviewed',action='store_true')
    args = p.parse_args();bundle = release()
    if not any((args.prepare,args.deploy,args.verify,args.rollback,args.cleanup)):
        print(json.dumps({'mode':'OFFLINE_NO_NETWORK','aggregate':bundle[5],'pins':bundle[4]},indent=2));return
    require(args.expect == bundle[5],'Exact reviewed aggregate required before connecting')
    if args.prepare:prepare(bundle);return
    if args.deploy:require(args.preview_reviewed,'Actual desktop/mobile preview gate required')
    for mode in ('deploy','verify','rollback','cleanup'):
        if getattr(args,mode):operate(bundle,getattr(args,mode),mode);return


if __name__ == '__main__':main()
