"""Add a removable homepage-only six-card rotation; default offline."""
import importlib.util
import json
from pathlib import Path
import re
import shlex

HERE=Path(__file__).resolve().parent
ROOT=HERE.parents[1]
spec=importlib.util.spec_from_file_location('rotation_prior',HERE/'2026-10-20-update-home-hero-assistant.py')
M=importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
V=M.U.V
D,H,Q=V.D,V.H,V.Q
require,sha=M.require,M.sha
MAIN='zz-harmat-home-apartment-rotation.php'
TARGET=D.DIR+'/'+MAIN
TEST='2026-10-20-test-home-apartment-rotation.php'
PREFIX=H.BACKUPS+'/home-apartment-rotation-'
NEW='a505545d3c548206f04db2360847e56e48b44102e707c846a100795fd66bc957'
MARKERS=tuple('id="harmat-home-rotation-'+part+'"' for part in ('data','style','script'))
HERO_MAIN=M.U.V.MAIN
BASE=M.release()


def release():
    source=M.R.S.read_local(MAIN)
    require(sha(source)==NEW,'Exact reviewed rotation source required')
    require(not re.search(rb'\b(?:wp_mail|update_option|update_post_meta|wp_insert_post|register_rest_route|wp_remote_\w+)\s*\(',source),
            'Read-only presentation source required')
    _,hero,base_protected,_,_,_=BASE
    protected=dict(base_protected)
    protected[HERO_MAIN]=hero
    helpers={name:(HERE/name).read_bytes().replace(b'\r\n',b'\n') for name in (TEST,D.CHECKER)}
    descriptor={'source':sha(source),'protected':{p:sha(b) for p,b in protected.items()},
                'helpers':{p:sha(b) for p,b in helpers.items()},'snapshot_policy':M.R.M.A.CACHE_PREDICATE}
    return source,protected,helpers,descriptor,sha(Q.P.encoded(descriptor))


def state(remote,protected):
    value=M.state(remote,protected)
    value['sources'].pop(TARGET,None)
    return value


def blocks(text):
    result=[]
    for tag in ('style','script'):
        found=re.findall('<'+tag+' id="harmat-home-rotation-'+tag+'">([\\s\\S]*?)</'+tag+'>',text)
        require(len(found)==1,'One rotation '+tag+' required')
        result.append(found[0].replace('\r\n','\n').strip())
    return result


def public(remote,baseline,protected,active):
    body,kind=Q.get(D.URL)
    text,previous=body.decode('utf-8'),baseline.decode('utf-8')
    require('text/html' in kind,'Homepage MIME')
    proof=D.assert_home(text,previous)
    require(M.R.M.main_html(text)==M.R.M.main_html(previous),'Original six-card main must remain byte-exact')
    require(all(text.count(marker)==int(active) for marker in MARKERS),'Rotation resource state mismatch')
    require(M.asset_blocks(text)==M.asset_blocks(previous),'Hero assistant assets changed')
    if active:
        require(blocks(text)==blocks(release()[0].decode('utf-8')),'Published rotation assets differ')
        found=re.findall('<script type="application/json" id="harmat-home-rotation-data">(.*?)</script>',text,re.S)
        require(len(found)==1,'Single public inventory payload required')
        rows=json.loads(found[0])
        expected=json.loads(remote.run(H.WP+' eval '+shlex.quote('echo json_encode(harmat_har_inventory());')))
        require(rows==expected and 7<=len(rows)<=500,'Public rotation data differs from unified source')
        require(all(set(row)=={'code','url','image','rooms','area','price'} for row in rows),'Public allowlist changed')
        proof['rotation_pool']=len(rows)
    for route in ('/lakaskereso/','/elerhetosegeink/','/property/a1-f-l1/',
                  '/virtualis-lakasvalaszto/','/virtualis-lakasvalaszto-elso-utem/','/virtualis-lakasvalaszto-a1-epulet/'):
        data,mime=Q.get('https://harmat22.hu'+route)
        require('text/html' in mime and not any(marker.encode() in data for marker in MARKERS),
                'Unrelated route health or rotation leakage')
    proof.update({'health_routes':7,'main_unchanged':True,'rotation':active,'submission':False})
    return proof


def configure():
    V.release,V.state,V.public=release,state,public
    V.MAIN,V.TARGET,V.TEST,V.PREFIX,V.MARKERS=MAIN,TARGET,TEST,PREFIX,MARKERS
    V.__doc__=__doc__
    return V


if __name__=='__main__': configure().main()
