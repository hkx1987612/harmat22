"""Two bounded search-presentation edits. Offline by default; no lead submissions."""
import argparse
import difflib
import importlib.util
import json
from pathlib import Path
import re
import shlex
import subprocess
import time
import uuid
from lxml import html

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
BASE = 'e0037186b8d8eb775b1b80a5a634d40961222232'
spec = importlib.util.spec_from_file_location('budget_prior', HERE/'2026-10-27-deploy-neighborhood.py')
N = importlib.util.module_from_spec(spec); spec.loader.exec_module(N)
H, Q = N.H, N.Q
require, sha = N.require, N.sha
PATHS = {
    'search': ('wp-plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php', H.LIVE+'/wp-content/plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php'),
    'smart': ('wp-mu-plugins/harmat-performance-guard.php', H.LIVE+'/wp-content/mu-plugins/harmat-performance-guard.php'),
}
ROUTES = ('/lakaskereso/', '/', '/property/a1-1-l6/', '/virtualis-lakasvalaszto/',
          '/virtualis-lakasvalaszto-elso-utem/', '/virtualis-lakasvalaszto-a1-epulet/', '/elerhetosegeink/', '/epitesi-naplo/')
OUT = ROOT/'outputs/2026-10-29-search-budget'
PREFIX = H.BACKUPS+'/search-budget-'
LOCK = H.BACKUPS+'/.search-budget.lock'

def norm(b): return b.replace(b'\r\n', b'\n')
def encoded(x): return json.dumps(x, ensure_ascii=True, sort_keys=True, indent=2).encode()
def originals(): return {k: subprocess.check_output(['git', 'show', BASE+':'+p], cwd=ROOT) for k,(p,_) in PATHS.items()}
def local(): return {k: norm((ROOT/p).read_bytes()) for k,(p,_) in PATHS.items()}

def validate_local():
    old, new = originals(), local()
    prefix = b'function harmat_perf_lakaskereso_search_patch()'
    end = b"add_action('wp_footer', 'harmat_perf_lakaskereso_search_patch'"
    a, b = old['smart'], new['smart']
    require(a[:a.index(prefix)] == b[:b.index(prefix)] and a[a.index(end):] == b[b.index(end):], 'Only legacy smart-search function may change')
    require(b'budgetBound' in b and b'budgetMin' in b and b'budgetMax' in b, 'Budget smart-search contract')
    # PHP data/card/related/offer renderers must remain byte-exact.
    for first, last in [('function harmat_lakas_redesign_items()', 'function harmat_lakas_redesign_render()')]:
        x,y = first.encode(),last.encode()
        require(old['search'][old['search'].index(x):old['search'].index(y)] == new['search'][new['search'].index(x):new['search'].index(y)], 'Inventory/card/related/offer functions changed')
    require(b'hm-lakas-construction-link" href=' not in new['search'] and b'<fieldset class="hm-lakas-budget"' in new['search'], 'Budget/link contract')
    return old,new

def raw_patch(raw, old, new):
    require(norm(raw) == old, 'Live source differs from reviewed Git baseline')
    lines, before, after = raw.splitlines(keepends=True), old.splitlines(keepends=True), new.splitlines(keepends=True)
    result = []
    for kind,a,b,c,d in difflib.SequenceMatcher(a=before,b=after,autojunk=False).get_opcodes():
        if kind == 'equal': result.extend(lines[a:b]); continue
        nearby = lines[a:b] or lines[max(0,a-1):a+1]
        ending = b'\r\n' if sum(line.endswith(b'\r\n') for line in nearby) > len(nearby)/2 else b'\n'
        result.extend(line.replace(b'\n', ending) for line in after[c:d])
    candidate = b''.join(result)
    require(norm(candidate) == new, 'Raw line-ending preservation failed')
    return candidate

def state(r):
    result = N.P.state(r,{})
    for _,target in PATHS.values(): result['sources'].pop(target,None)
    # Explicitly retain the current homepage controller (historically excluded).
    for name in ('zz-harmat-home-family.php','zz-harmat-neighborhood-practical.php','harmat-neighborhood-practical/manifest.json'):
        p = H.LIVE+'/wp-content/mu-plugins/'+name
        result['sources'][p] = sha(r.read(p))
    return result

def pages():
    result = {}
    for route in ROUTES:
        time.sleep(1.5)
        body, mime = Q.get('https://harmat22.hu'+route)
        require('text/html' in mime and not re.search(rb'Fatal error:|Parse error:|critical error on this website',body), 'Public page failed '+route)
        result[route] = body
    return result

def clean(node):
    node = html.fromstring(html.tostring(node))
    for item in node.xpath('//*[@name="_harmat_offer_nonce"]'): item.attrib.pop('value',None)
    return html.tostring(node)

def cards(blob):
    # Public HTML is minified/lazy-image wrapped; compare every public fact and
    # destination against the raw native render, not whitespace or placeholders.
    result=[]
    for node in html.fromstring(blob.decode('utf-8')).xpath('//*[@data-hm-lakas-page]//*[@data-card]'):
        images=node.xpath('.//img[not(ancestor::noscript)]')
        result.append({'attributes':dict(node.attrib),
            'links':[(a.get('href'),' '.join(a.text_content().split())) for a in node.xpath('.//a')],
            'facts':[' '.join(n.text_content().split()) for n in node.xpath('.//*[@class="hm-lakas-facts"]/div')],
            'price':' '.join(node.xpath('.//*[@class="hm-lakas-price"]')[0].text_content().split()),
            'images':[(n.get('data-src') or n.get('src'),n.get('alt')) for n in images]})
    return result

def proof(before, after, active):
    for route in ROUTES:
        a,b = N.D.Page(before[route].decode()),N.D.Page(after[route].decode())
        require(a.form == b.form and a.form and a.seo == b.seo and a.canonical == b.canonical, 'Native form/SEO changed '+route)
        x,y = html.fromstring(before[route]),html.fromstring(after[route])
        for ident in ('masthead','colophon','h22-offer-modal','harmat-internal-language'):
            aa,bb = x.xpath('//*[@id=$id]',id=ident),y.xpath('//*[@id=$id]',id=ident)
            require(len(aa)==len(bb)==1 and clean(aa[0]) == clean(bb[0]), 'Native shell changed '+ident+' '+route)
        if route == '/lakaskereso/':
            require(len(cards(before[route])) == 124 and cards(before[route]) == cards(after[route]), 'Card facts/price/status/offer links changed')
            require(len(y.xpath('//*[@class="hm-lakas-construction-link"]')) == int(not active), 'Wrong journal-link state')
            require(len(y.xpath('//*[@data-filter="budgetMin"]')) == int(active) and len(y.xpath('//*[@data-filter="budgetMax"]')) == int(active), 'Budget inputs missing/duplicated')
        else:
            ident = 'content' if route.startswith('/property/') else 'main'
            aa,bb=x.xpath('//*[@id=$id]',id=ident),y.xpath('//*[@id=$id]',id=ident)
            require(len(aa)==len(bb)==1 and clean(aa[0]) == clean(bb[0]), 'Unrelated page changed '+route)
    return {'routes':len(ROUTES), 'cards_unchanged':124, 'native_form_seo_shell_unchanged':True, 'other_main_unchanged':True, 'submission':False}

def purge(r):
    code = 'foreach(array(14,15) as $v)delete_transient("harmat_lakas_redesign_markup_v".$v);'
    code += 'if(function_exists("wp_cache_clear_cache"))wp_cache_clear_cache();wp_cache_flush();echo "BUDGET_CACHE_CLEARED";'
    require(r.run(H.WP+' eval '+shlex.quote(code))=='BUDGET_CACHE_CLEARED','Presentation cache purge failed')

def native_filters(r, backup): return N.D.validate_native(r,backup)

def preflight():
    old,new = validate_local(); r=H.Remote(); locked=False
    try:
        r.directory(H.BACKUPS); r.mkdir(LOCK); locked=True
        before=state(r); baseline=pages(); backup=PREFIX+uuid.uuid4().hex
        r.mkdir(backup)
        print(json.dumps({'status':'PRIVATE_BACKUP_STARTED','backup':backup}),flush=True)
        meta, pins = {}, {}
        for key,(_,target) in PATHS.items():
            raw=r.read(target); candidate=raw_patch(raw,old[key],new[key])
            meta[key]=Q.metadata(r,target,raw)
            for label,blob in [('old',raw),('new',candidate)]:
                r.write_new(backup+'/'+key+'-'+label+'.php',blob); r.lint(backup+'/'+key+'-'+label+'.php')
            pins[key]={'old':sha(raw),'new':sha(candidate),'local':sha(new[key])}
        for i,route in enumerate(ROUTES): r.write_new(backup+'/page-'+str(i)+'.html',baseline[route])
        for name in (N.D.CHECKER,): r.write_new(backup+'/'+name,norm((HERE/name).read_bytes()))
        # Use the real public inventory with the candidate renderer, isolated from the active plugin.
        code='require '+json.dumps(backup+'/search-new.php')+'; echo base64_encode(harmat_lakas_redesign_render());'
        import base64
        rendered=base64.b64decode(r.run(H.WP+' --skip-plugins=harmat-lakaskereso-redesign eval '+shlex.quote(code)),validate=True)
        r.write_new(backup+'/native-search-render.html',rendered)
        require(cards(baseline['/lakaskereso/']) == cards(rendered) and len(cards(rendered)) == 124, 'Native candidate changes public cards')
        native=native_filters(r,backup)
        require(state(r)==before,'Protected state changed before activation; do not restore business data')
        saved={'backup':backup,'base':BASE,'pins':pins,'meta':meta,'before':before,'native':native}
        r.write_new(backup+'/manifest.json',encoded(saved))
        OUT.mkdir(parents=True,exist_ok=True); (OUT/'preflight.json').write_bytes(encoded(saved))
        print(json.dumps({'status':'PREFLIGHT_BACKUP_READY','backup':backup,'pins':pins,'native':native}),flush=True)
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()

def operate(backup, deploy=False, rollback=False):
    require(re.fullmatch(re.escape(PREFIX)+'[a-f0-9]{32}',backup),'Exact private backup path required')
    _,new=validate_local(); r=H.Remote(); changed=[];locked=False
    try:
        if deploy or rollback:r.mkdir(LOCK);locked=True
        saved=json.loads(r.read(backup+'/manifest.json',private=True))
        require(saved['base']==BASE and saved['backup']==backup,'Backup identity mismatch')
        baseline={route:r.read(backup+'/page-'+str(i)+'.html',private=True) for i,route in enumerate(ROUTES)}
        blobs={}
        for key,(_,target) in PATHS.items():
            pair=tuple(r.read(backup+'/'+key+'-'+tag+'.php',private=True) for tag in ('old','new'))
            require([sha(x) for x in pair]==[saved['pins'][key]['old'],saved['pins'][key]['new']] and norm(pair[1])==new[key], 'Source pin drift')
            blobs[key]=pair
        before=state(r)
        if deploy:
            require(before==saved['before'],'Protected state changed since backup; stop, do not restore data')
            # The smart matcher supports old markup too; activate it before the new controls.
            for key in sorted(PATHS, key=lambda key: key == 'search'):
                old,candidate=blobs[key];target=PATHS[key][1]
                changed.append(key); Q.replace(r,target,old,candidate,tuple(saved['meta'][key]))
            purge(r)
        elif rollback:
            for key in reversed(sorted(PATHS, key=lambda key: key == 'search')):
                old,candidate=blobs[key];target=PATHS[key][1]
                Q.replace(r,target,candidate,old,tuple(saved['meta'][key]))
            purge(r)
        for key,(_,target) in PATHS.items():
            expect=blobs[key][0 if rollback else 1]
            require(r.read(target)==expect,'Final file mismatch');r.lint(target)
        public=proof(baseline,pages(),not rollback); native=native_filters(r,backup)
        require(state(r)==before,'Protected state changed during release/verification; no business restore')
        result={'status':'ROLLED_BACK' if rollback else ('RELEASED' if deploy else 'VERIFIED'), 'backup':backup,'proof':public,'native':native}
        r.write_new(backup+'/'+result['status'].lower()+'-'+uuid.uuid4().hex+'.json',encoded(result))
        OUT.mkdir(parents=True,exist_ok=True);(OUT/(result['status'].lower()+'.json')).write_bytes(encoded(result))
        print(json.dumps(result),flush=True)
    except Exception:
        if changed:
            for key in reversed(changed):
                old,candidate=blobs[key];target=PATHS[key][1];actual=r.read(target)
                require(actual in (old,candidate),'Foreign concurrent source retained')
                if actual==candidate:Q.replace(r,target,candidate,old,tuple(saved['meta'][key]))
            purge(r)
            print(json.dumps({'status':'OWN_SOURCE_ROLLBACK','backup':backup}),flush=True)
        raise
    finally:
        if locked:r.sftp.rmdir(LOCK)
        r.close()

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);g=p.add_mutually_exclusive_group()
    g.add_argument('--preflight',action='store_true');g.add_argument('--deploy');g.add_argument('--verify');g.add_argument('--rollback')
    a=p.parse_args()
    if a.preflight:preflight()
    elif a.deploy:operate(a.deploy,deploy=True)
    elif a.verify:operate(a.verify)
    elif a.rollback:operate(a.rollback,rollback=True)
    else:
        old,new=validate_local();print(json.dumps({'status':'OFFLINE_SOURCE_SCOPE_OK','pins':{k:sha(v) for k,v in new.items()}}))
