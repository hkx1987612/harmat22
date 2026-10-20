"""Six homepage cards; versioned dependencies first, exact controller last."""
import importlib.util
import json
from pathlib import Path
import shlex
import subprocess

from lxml import html

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('six_prior_transport', HERE/'2026-10-19-update-home-scroll-play.py')
S = importlib.util.module_from_spec(spec)
spec.loader.exec_module(S)
D, H, Q = S.D, S.H, S.Q
require, sha = S.require, S.sha
HOME, ASSET = S.HOME, S.ASSET
BODY, JS, MANIFEST = 'body-six.php', 'home-family-six.js', 'manifest-six.json'
TEST = '2026-10-20-test-home-six.php'
CODES = ('A1-1-L6', 'A2-4-L5', 'A1-1-L5', 'A3-F-L3', 'A4-F-L1', 'A3-2-L2')
OLD_SHA = 'af6f0206b31a430d7acde7a3eac14e304d39220256a1a97083e858eaee59c787'
# Frozen after independent source/fixture review; a changed candidate cannot deploy.
NEW_PINS = {
    'body-six.php':'0585ca56e2afa2a558932d9ee9ad85868641a199683d19caddeafd9a010d61d2',
    'home-family-six.js':'a2222a2e7cd882a128a146c1c35f6c316c63b3558073d5fcbe73d4dd59abca05',
    'manifest-six.json':'3b6c8c5248dab6e6eb78d15b3f8cca5203498095980a9f3875fb32a2caafefc1',
    'zz-harmat-home-family.php':'2c0fdc06a013d9b74f82f24dd14c3ed71d92f0fa828809103558c55d76873f85',
}
native_validation = D.validate_native
prior_state = S.state
CACHE_PREDICATE = "m.meta_key NOT IN ('_elementor_element_cache','_elementor_css','_elementor_page_assets')"


def business_snapshot_code():
    original = D.I.SNAPSHOT
    marker = "m.meta_key<>'_elementor_element_cache'"
    require(original.count(marker) == 1, 'Protected SQL ownership changed')
    # Native public property requests regenerate these three presentation caches.
    # Keep every other property/lead field, row, and CRM option in the comparison.
    return original.replace(marker, CACHE_PREDICATE, 1)


def state(r, protected):
    value = prior_state(r, protected)
    value['protected'] = json.loads(r.run(H.WP+' eval '+shlex.quote(business_snapshot_code())))
    return value


def release():
    old = subprocess.check_output(['git', 'show', '29c906b:wp-mu-plugins/'+D.MAIN], cwd=ROOT)
    require(sha(old) == OLD_SHA, 'Exact current controller required')
    new = S.read_local(D.MAIN)
    if sha(new) == '7d823dc258bd7e64ede2586455ce222000632d2f47aa5523df779542d0bf3dab':
        # Allow the exact later local release to reconstruct r1 for chained recovery.
        new = new.replace(b' * Version: 1.0.15',b' * Version: 1.0.14',1)
        new = new.replace(b"'/manifest-six-r2.json'",b"'/manifest-six.json'",1)
        new = new.replace(b'b7ad673660278889725b832cf748656c5b4e59fc92220519e75117eb99f25280',
                          NEW_PINS['manifest-six.json'].encode(),1)
        new = new.replace(b",$pins['home-family-six-r2.js']",b'',1)
        new = new.replace(b"array('pannellum.js','home-family-six-r2.js')",b"array('pannellum.js','home-family-six.js')",1)
    names = (BODY, JS, MANIFEST)
    assets = {name: S.read_local(D.ASSET+'/'+name) for name in names}
    require(NEW_PINS and {D.MAIN:sha(new), **{n:sha(b) for n,b in assets.items()}} == NEW_PINS,
            'Exact reviewed four-source bundle required')
    prior = S.read_local(D.ASSET+'/manifest-scroll-play.json')
    require(sha(prior) == '4916615afd43d01394b22b362e5e4878f9eca02ee086791cbb0081b52bbf7b81',
            'Prior seven-asset manifest changed')
    pins = json.loads(prior)
    require(json.loads(assets[MANIFEST]) == {**pins, BODY:sha(assets[BODY]), JS:sha(assets[JS])},
            'Original pins must be retained')
    require(sha(assets[MANIFEST]).encode() in new, 'Controller manifest pin mismatch')
    protected = {D.ASSET+'/'+n:S.read_local(D.ASSET+'/'+n) for n in pins}
    require(all(sha(protected[D.ASSET+'/'+n]) == v for n,v in pins.items()), 'Prior asset drift')
    protected[D.ASSET+'/manifest-scroll-play.json'] = prior
    protected[D.ASSET+'/manifest.json'] = S.read_local(D.ASSET+'/manifest.json')
    for name, pin in {
        'zz-harmat-home-video-fullscreen.php':'38383bbaacb876e0b3de4b63d22fc23c6f29679784e3d4cf99e56d9ddd9d4580',
        'zz-harmat-home-assistant-inline.php':'3bce85ddad7b4b59948293536b2fbd193bb21b820b6384c9bdbb937a529125bd',
    }.items():
        protected[name] = S.read_local(name)
        require(sha(protected[name]) == pin, 'Retained UI owner changed')
    edits = {ASSET+'/'+n:(None, assets[n]) for n in names}
    edits[HOME] = (old, new)
    helpers = {n:(HERE/n).read_bytes().replace(b'\r\n',b'\n') for n in (D.TEST, D.CHECKER, TEST)}
    recorded = {p:{'old':None if a is None else sha(a), 'new':sha(b)} for p,(a,b) in edits.items()}
    digest = sha(Q.P.encoded({'sources':recorded, 'protected':{p:sha(b) for p,b in protected.items()},
                             'native_presentation_cache_predicate':CACHE_PREDICATE,
                             'helpers':{p:sha(b) for p,b in helpers.items()}}))
    return edits, helpers, protected, recorded, digest


def validate_native(r, backup):
    r.lint(backup+'/'+D.ASSET+'/'+BODY)
    test = r.run(H.PHP+' '+shlex.quote(backup+'/'+TEST)+' '+shlex.quote(backup+'/candidate-home.php'))
    require(test.strip() == 'PASS 136 presentation PHP assertions', 'Staged six-card fixture failed')
    result = native_validation(r, backup)
    result['six_card_checks'] = 136
    return result


def public(r, baseline, protected, edits, active):
    files = tuple(protected) + ((D.ASSET+'/'+JS,) if active else ())
    hashes = {n:sha(b) for n,b in protected.items()}
    hashes[D.ASSET+'/'+JS] = sha(edits[ASSET+'/'+JS][1])
    proof = D.public_proof(r, files, hashes, baseline.decode('utf-8'))
    data, mime = Q.get(D.URL)
    text = data.decode('utf-8')
    expected = JS if active else 'home-family-scroll-play.js'
    require('text/html' in mime and text.count('/'+expected+'?ver=') == 1, 'Wrong active runtime')
    require('/'+('home-family-scroll-play.js' if active else JS)+'?ver=' not in text, 'Double runtime')
    tree = html.fromstring(data)
    cards = tree.xpath('//*[@id="hhf-lakasok"]//*[@class="hhf-apartment"]')
    codes = [n.xpath('.//h3/text()')[0].strip() for n in cards]
    require(codes == list(CODES if active else CODES[:3]), 'Wrong live apartment set/order')
    require(len(tree.xpath('//button[contains(concat(" ",@class," ")," hhf-apartment-toggle ")]')) == int(active), 'Wrong toggle count')
    for card, code in zip(cards, codes):
        require(card.xpath('.//a/@href') == ['https://harmat22.hu/property/'+code.lower()+'/']*2,
                'Wrong apartment detail link')
    require(all(text.count(marker) == 1 for marker in S.V.MARKERS), 'Fullscreen resource changed')
    require(text.count('id="harmat-home-assistant-inline-script"') == 1, 'Inline assistant owner missing')
    S.T.V.policy(text)
    for route in ('/lakaskereso/', '/property/a1-f-l1/', '/elerhetosegeink/', '/virtualis-lakasvalaszto-a1-epulet/'):
        body, mime = Q.get(Q.P.ORIGIN+route)
        require('text/html' in mime and ('/'+JS+'?ver=').encode() not in body, 'Six-card runtime leaked')
    proof['cards'] = len(cards)
    proof['mobile_policy'] = 'first_three_then_native_toggle' if active else 'prior_three'
    return proof


def configure():
    S.JS, S.MANIFEST = JS, MANIFEST
    S.PREFIX = H.BACKUPS+'/home-six-'
    S.release, S.public = release, public
    S.state = state
    D.validate_native = validate_native
    S.__doc__ = __doc__
    return S


if __name__ == '__main__':
    configure().main()
