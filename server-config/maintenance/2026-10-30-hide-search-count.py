"""Hide the search result-count row only. Default offline; private backup/recovery."""
import argparse
import importlib.util
import json
from pathlib import Path
import shlex
from lxml import html

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('hide_count_budget_transport',HERE/'2026-10-29-deploy-search-budget.py')
B=importlib.util.module_from_spec(spec);spec.loader.exec_module(B)
B.BASE='071caf48a220b8d70c0367779ccc8f5e1bbda615'
B.PATHS={'search':B.PATHS['search']}
B.PREFIX=B.H.BACKUPS+'/search-count-hidden-'
B.LOCK=B.H.BACKUPS+'/.search-count-hidden.lock'
B.OUT=B.ROOT/'outputs/2026-10-30-hide-search-count'

def validate():
    old,new=B.originals(),B.local()
    expected=old['search']
    edits=[(b'1.2.5',b'1.2.6',3),
        (b"return 'harmat_lakas_redesign_markup_v15';",b"return 'harmat_lakas_redesign_markup_v16';",1),
        (b'delete_transient(harmat_lakas_redesign_cache_key());',b"delete_transient(harmat_lakas_redesign_cache_key());\n    delete_transient('harmat_lakas_redesign_markup_v15');",1),
        (b'<div class="hm-lakas-resultbar">',b'<div class="hm-lakas-resultbar" hidden aria-hidden="true">',1)]
    for before,after,count in edits:
        B.require(expected.count(before)==count,'Exact count-visibility delta required')
        expected=expected.replace(before,after)
    line=b'    .hm-lakas-resultbar{display:flex;align-items:center;gap:8px;margin:0 0 20px;color:#687078;font-size:14px}\n'
    B.require(expected.count(line)==1,'Exact result-row CSS required')
    expected=expected.replace(line,line+b'    body.harmat-lakas-redesign-page .hm-lakas-resultbar[hidden]{display:none}\n',1)
    B.require(new['search']==expected,'Only version/cache and result-row visibility changes allowed')
    return old,new

def proof(before,after,active):
    for route in B.ROUTES:
        aa,bb=B.N.D.Page(before[route].decode()),B.N.D.Page(after[route].decode())
        B.require(aa.form==bb.form and aa.form and aa.seo==bb.seo and aa.canonical==bb.canonical,'Native form/SEO changed '+route)
        x,y=html.fromstring(before[route]),html.fromstring(after[route])
        for ident in ('masthead','colophon','h22-offer-modal','harmat-internal-language'):
            a,b=x.xpath('//*[@id=$id]',id=ident),y.xpath('//*[@id=$id]',id=ident)
            B.require(len(a)==len(b)==1 and B.clean(a[0])==B.clean(b[0]),'Native shell changed '+ident)
        ident='content' if route.startswith('/property/') else 'main'
        a,b=x.xpath('//*[@id=$id]',id=ident),y.xpath('//*[@id=$id]',id=ident)
        B.require(len(a)==len(b)==1,'Native main missing')
        if route=='/lakaskereso/':
            rows=y.xpath('//*[@class="hm-lakas-resultbar"]')
            B.require(len(rows)==1 and ('hidden' in rows[0].attrib)==active,'Wrong count visibility')
            B.require(rows[0].get('aria-hidden')==('true' if active else None),'Count accessibility changed')
            B.require(len(rows[0].xpath('.//*[@data-count]'))==1,'Internal counter removed')
            B.require(len(B.cards(before[route]))==124 and B.cards(before[route])==B.cards(after[route]),'Apartment facts changed')
            for tree in (x,y):
                for row in tree.xpath('//*[@class="hm-lakas-resultbar"]'):
                    row.attrib.pop('hidden',None);row.attrib.pop('aria-hidden',None)
        B.require(B.clean(a[0])==B.clean(b[0]),'Unexpected main-content change '+route)
    return {'routes':len(B.ROUTES),'cards_unchanged':124,'only_result_row_visibility':True,'native_form_seo_shell_unchanged':True,'submission':False}

def purge(r):
    code='foreach(array(15,16) as $v)delete_transient("harmat_lakas_redesign_markup_v".$v);'
    code+='if(function_exists("wp_cache_clear_cache"))wp_cache_clear_cache();wp_cache_flush();echo "COUNT_CACHE_CLEARED";'
    B.require(r.run(B.H.WP+' eval '+shlex.quote(code))=='COUNT_CACHE_CLEARED','Presentation cache purge failed')

B.validate_local=validate
B.proof=proof
B.purge=purge

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);g=p.add_mutually_exclusive_group()
    g.add_argument('--preflight',action='store_true');g.add_argument('--deploy');g.add_argument('--verify');g.add_argument('--rollback')
    a=p.parse_args()
    if a.preflight:B.preflight()
    elif a.deploy:B.operate(a.deploy,deploy=True)
    elif a.verify:B.operate(a.verify)
    elif a.rollback:B.operate(a.rollback,rollback=True)
    else:
        _,new=validate();print(json.dumps({'status':'OFFLINE_EXACT_VISIBILITY_DELTA','source':B.sha(new['search'])}))
