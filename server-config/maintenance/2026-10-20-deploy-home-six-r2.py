"""Keep the mobile collapse control visible; retain all r1 assets for cached pages."""
import importlib.util
import json
from pathlib import Path
import shlex

from lxml import html

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('six_r1_transport',HERE/'2026-10-20-deploy-home-six.py')
A = importlib.util.module_from_spec(spec)
spec.loader.exec_module(A)
S,D,H,Q = A.S,A.D,A.H,A.Q
require,sha = A.require,A.sha
HOME,ASSET,BODY = A.HOME,A.ASSET,A.BODY
JS,MANIFEST = 'home-family-six-r2.js','manifest-six-r2.json'
NEW_PINS = {
    'home-family-six-r2.js':'514ac9461c3751804b001513c831c0993573b781b701b213286fab507138e766',
    'manifest-six-r2.json':'b7ad673660278889725b832cf748656c5b4e59fc92220519e75117eb99f25280',
    'zz-harmat-home-family.php':'7d823dc258bd7e64ede2586455ce222000632d2f47aa5523df779542d0bf3dab',
}
OLD_SHA = '2c0fdc06a013d9b74f82f24dd14c3ed71d92f0fa828809103558c55d76873f85'
business_snapshot_code = A.business_snapshot_code
TEST = '2026-10-20-test-home-six-r2.php'


def validate_native(r,backup):
    r.lint(backup+'/'+D.ASSET+'/'+BODY)
    result = r.run(H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/candidate-home.php'))
    require(result.strip() == 'PASS 141 presentation PHP assertions','Staged r2 six-card fixture failed')
    proof = A.native_validation(r,backup)
    proof['six_card_checks'] = 141
    return proof


def release():
    new = S.read_local(D.MAIN)
    js,manifest = (S.read_local(D.ASSET+'/'+n) for n in (JS,MANIFEST))
    require(NEW_PINS and {D.MAIN:sha(new),JS:sha(js),MANIFEST:sha(manifest)} == NEW_PINS,
            'Exact reviewed r2 three-source bundle required')
    old_manifest = S.read_local(D.ASSET+'/manifest-six.json')
    require(sha(old_manifest) == A.NEW_PINS['manifest-six.json'],'Retained r1 manifest changed')
    old = new.replace(b' * Version: 1.0.15',b' * Version: 1.0.14',1)
    old = old.replace(b"'/manifest-six-r2.json'",b"'/manifest-six.json'",1)
    old = old.replace(sha(manifest).encode(),sha(old_manifest).encode(),1)
    old = old.replace(b",$pins['home-family-six-r2.js']",b'',1)
    old = old.replace(b"array('pannellum.js','home-family-six-r2.js')",b"array('pannellum.js','home-family-six.js')",1)
    require(sha(old) == OLD_SHA,'Only reviewed r2 controller selection/version edits allowed')
    prior = json.loads(old_manifest)
    require(json.loads(manifest) == {**prior,JS:sha(js)},'All prior nine pins must remain')
    protected = {D.ASSET+'/'+n:S.read_local(D.ASSET+'/'+n) for n in prior}
    require(all(sha(protected[D.ASSET+'/'+n]) == pin for n,pin in prior.items()),'Retained asset drift')
    for n in ('manifest.json','manifest-scroll-play.json','manifest-six.json'):
        protected[D.ASSET+'/'+n] = S.read_local(D.ASSET+'/'+n)
    for n,pin in {
        'zz-harmat-home-video-fullscreen.php':'38383bbaacb876e0b3de4b63d22fc23c6f29679784e3d4cf99e56d9ddd9d4580',
        'zz-harmat-home-assistant-inline.php':'3bce85ddad7b4b59948293536b2fbd193bb21b820b6384c9bdbb937a529125bd',
    }.items():
        protected[n] = S.read_local(n)
        require(sha(protected[n]) == pin,'Retained UI owner changed')
    edits = {ASSET+'/'+JS:(None,js),ASSET+'/'+MANIFEST:(None,manifest),HOME:(old,new)}
    helpers = {n:(HERE/n).read_bytes().replace(b'\r\n',b'\n') for n in (D.TEST,D.CHECKER,TEST)}
    pins = {p:{'old':None if a is None else sha(a),'new':sha(b)} for p,(a,b) in edits.items()}
    digest = sha(Q.P.encoded({'sources':pins,'protected':{p:sha(b) for p,b in protected.items()},
                             'native_presentation_cache_predicate':A.CACHE_PREDICATE,
                             'helpers':{p:sha(b) for p,b in helpers.items()}}))
    return edits,helpers,protected,pins,digest


def public(r,baseline,protected,edits,active):
    files = tuple(protected)+((D.ASSET+'/'+JS,) if active else ())
    hashes = {n:sha(b) for n,b in protected.items()}
    hashes[D.ASSET+'/'+JS] = sha(edits[ASSET+'/'+JS][1])
    proof = D.public_proof(r,files,hashes,baseline.decode('utf-8'))
    data,mime = Q.get(D.URL)
    text = data.decode('utf-8')
    expected,other = (JS,A.JS) if active else (A.JS,JS)
    require('text/html' in mime and text.count('/'+expected+'?ver=') == 1 and '/'+other+'?ver=' not in text,
            'Wrong six-card runtime')
    cards = html.fromstring(data).xpath('//*[@id="hhf-lakasok"]//*[@class="hhf-apartment"]')
    require([n.get('data-code') for n in cards] == list(A.CODES),'Six cards changed')
    require(all(text.count(m) == 1 for m in S.V.MARKERS),'Fullscreen owner changed')
    require(text.count('id="harmat-home-assistant-inline-script"') == 1,'Inline assistant owner missing')
    S.T.V.policy(text)
    proof['cards'] = len(cards)
    proof['mobile_collapse'] = 'nearest_visible_control' if active else 'r1_scroll_position'
    return proof


def configure():
    S.JS,S.MANIFEST = JS,MANIFEST
    S.PREFIX = H.BACKUPS+'/home-six-r2-'
    S.release,S.public,S.state = release,public,A.state
    D.validate_native = validate_native
    S.__doc__ = __doc__
    return S


if __name__ == '__main__':
    configure().main()
