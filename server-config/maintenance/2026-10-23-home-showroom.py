"""Two approved homepage figure changes; offline preparation and guarded release."""
import importlib.util
import json
from pathlib import Path
import re
import subprocess
import time
from lxml import etree, html
from PIL import Image

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
BASE = '297ebe0'
OUT = ROOT / 'outputs/2026-10-23-home-showroom'
SOURCE = Path('C:/Users/Administrator/AppData/Local/Temp/codex-clipboard-818ad2d3-3104-4701-ab6c-e69010962eef.jpg')
PHOTO = 'showroom-exterior-harmat.jpg'
PHOTO_SHA = 'ac4ad2578c1b44af479f5a0bcf9c07bb49e6f5a0245e24a65fc9a0b8e60eb1dc'
BODY, JS, MANIFEST = 'body-showroom.php', 'home-family-showroom.js', 'manifest-showroom.json'
ASSET = 'harmat-home-family'
PUBLIC_PHOTO = 'https://harmat22.hu/wp-content/mu-plugins/' + ASSET + '/' + PHOTO
OLD_HOME_SHA = 'b116dc413a0aea1c367c955b98669987368ee05b0c74b46cf2199bb013ba86df'


def module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    value = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(value)
    return value


M = module('showroom_prior', HERE / '2026-10-23-deploy-home-practical.py')
S, D, H, Q, P = M.S, M.D, M.H, M.Q, M.P
require, sha = M.require, M.sha
PHP_REPLACE = M.PHP_REPLACE


def original(name):
    return subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + name], cwd=ROOT)


def one(text, old, new):
    require(text.count(old) == 1, 'One exact owned fragment required')
    return text.replace(old, new, 1)


def rewrite(text, photo_url):
    park = re.findall(r'<figure><img [^>]*data-hhf-ohegy=""[\s\S]*?</figure>', text)
    require(len(park) == 1, 'One original park figure required')
    node = html.fragment_fromstring(park[0])
    require([child.tag for child in node] == ['img', 'figcaption', 'span']
            and node[1].get('data-i18n') == 'parkCaption'
            and node[2].get('class') == 'hhf-photo-credit', 'Only reviewed park note/credit removable')
    image = re.match(r'<figure>(<img [^>]+>)', park[0]).group(1)
    text = one(text, park[0], '<figure>' + image + '</figure>')
    showroom = re.findall(r'<figure><img [^>]*data-i18n-alt="showroomAlt"[\s\S]*?</figure>', text)
    require(len(showroom) == 1, 'One showroom image required')
    node = html.fragment_fromstring(showroom[0])
    require(node[0].get('src').endswith('/harmat-contact-family/showroom-lounge.jpg')
            and len(node) == 2 and node[1].get('data-i18n') == 'showroomCaption', 'Exact prior showroom required')
    replacement = ('<figure><img src="' + photo_url + '" width="1706" height="1280" '
                   'alt="A Harmat Lakópark értékesítési irodájának homlokzata a HARMAT felirattal" '
                   'data-i18n-alt="showroomAlt" data-hhf-showroom-exterior="" loading="lazy" decoding="async">'
                   + etree.tostring(node[1], encoding='unicode', method='html') + '</figure>')
    return one(text, showroom[0], replacement)


def runtime():
    text = original(ASSET + '/home-family-practical.js').decode()
    pattern = re.compile(r'^(\s*showroomAlt: )[^\n]+$', re.M)
    text, count = pattern.subn(lambda m: m[1] + json.dumps('Harmat Lakópark 售楼部外观与 HARMAT 标识实景', ensure_ascii=False) + ',', text)
    require(count == 1, 'One Chinese showroom alt key required')
    return text.encode()


CSS = ('body.home.page-id-164 #harmat-home-family img[data-hhf-showroom-exterior]'
       '{aspect-ratio:1706/1280!important;height:auto!important;object-fit:contain!important}')


def controller(manifest):
    old = original(D.MAIN)
    require(sha(old) == OLD_HOME_SHA, 'Exact stable controller required')
    text = old.decode()
    for a, b in [(' * Version: 1.0.20', ' * Version: 1.0.21'),
                 ("'/manifest-practical.json'", "'/" + MANIFEST + "'"),
                 ('42a73d9100bb7422a1e85a94e079741a436466aa76ae14b6d935f10d962822b4', sha(manifest)),
                 ("$pins['ohegy-park-2024-original.jpg']))", "$pins['ohegy-park-2024-original.jpg'],$pins['" + BODY + "'],$pins['" + JS + "'],$pins['" + PHOTO + "']))"),
                 ("/harmat-home-family/body-practical.php'", "/harmat-home-family/" + BODY + "'"),
                 ("array('pannellum.js','home-family-practical.js')", "array('pannellum.js','" + JS + "')"),
                 ("            . '</style>';", "            . '" + CSS + "'\n            . '</style>';" )]:
        text = one(text, a, b)
    return text.encode()


def prepared():
    photo = (OUT / PHOTO).read_bytes()
    require(sha(photo) == PHOTO_SHA and len(photo) == 237920, 'Exact user-supplied photograph required')
    with Image.open(OUT / PHOTO) as image:
        require(image.size == (1706, 1280) and image.format == 'JPEG', 'Original image dimensions/format required')
    body = rewrite(original(ASSET + '/body-practical.php').decode(), '__HHF_ASSET_BASE__' + PHOTO).encode()
    js = runtime()
    pins = json.loads(original(ASSET + '/manifest-practical.json'))
    assets = {BODY: body, JS: js, PHOTO: photo}
    manifest = (json.dumps({**pins, **{n: sha(b) for n, b in assets.items()}}, indent=2) + '\n').encode()
    return {**assets, MANIFEST: manifest}, controller(manifest)


def prepare():
    OUT.mkdir(parents=True, exist_ok=True)
    if not (OUT / PHOTO).exists():
        require(sha(SOURCE.read_bytes()) == PHOTO_SHA, 'Uploaded original changed')
        (OUT / PHOTO).write_bytes(SOURCE.read_bytes())
    assets, home = prepared()
    for name in (BODY, JS, MANIFEST):
        (ROOT / 'wp-mu-plugins' / ASSET / name).write_bytes(assets[name])
    (ROOT / 'wp-mu-plugins' / D.MAIN).write_bytes(home)
    fixture = OUT / 'fixture'
    (fixture / ASSET).mkdir(parents=True, exist_ok=True)
    for name, blob in protected().items():
        if name.startswith(ASSET + '/'):
            (fixture / name).write_bytes(blob)
    for name, blob in assets.items():
        (fixture / ASSET / name).write_bytes(blob)
    (fixture / 'candidate-home.php').write_bytes(home)
    print(json.dumps({'status': 'OFFLINE_PREPARED', 'controller': sha(home), 'assets': {n: sha(b) for n, b in assets.items()}}))


def protected():
    pins = json.loads(original(ASSET + '/manifest-practical.json'))
    values = {}
    for name, pin in pins.items():
        blob = ((ROOT / 'outputs/2026-10-23-home-practical-release' / name).read_bytes()
                if name == 'ohegy-park-2024-original.jpg' else original(ASSET + '/' + name))
        require(sha(blob) == pin, 'Retained pinned dependency changed')
        values[ASSET + '/' + name] = blob
    for name in ('manifest.json', 'manifest-scroll-play.json', 'manifest-six.json', 'manifest-six-r2.json', 'manifest-loop.json', 'manifest-practical.json'):
        values[ASSET + '/' + name] = original(ASSET + '/' + name)
    for name in ('zz-harmat-home-assistant-inline.php', 'zz-harmat-home-apartment-rotation.php', 'zz-harmat-home-video-fullscreen.php', 'zz-harmat-construction-updates.php'):
        values[name] = original(name)
    footer = original('harmat-migrated-snippets.php')
    raw = b''.join(line if i in (4, 175, 176, 177) else line.replace(b'\n', b'\r\n') for i, line in enumerate(footer.splitlines(keepends=True)))
    require(sha(raw) == 'f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077', 'Retained raw footer identity')
    values['harmat-migrated-snippets.php'] = raw
    return values


def release():
    assets, home = prepared()
    require(S.read_local(D.MAIN) == home, 'Exact scoped controller delta required')
    require(all(S.read_local(ASSET + '/' + n) == assets[n] for n in (BODY, JS, MANIFEST)), 'Exact generated assets required')
    edits = {M.ASSET + '/' + n: (None, assets[n]) for n in (PHOTO, BODY, JS, MANIFEST)}
    edits[M.HOME] = (original(D.MAIN), home)
    retained = protected()
    helpers = {n: (HERE / n).read_bytes().replace(b'\r\n', b'\n') for n in (D.TEST, D.CHECKER)}
    recorded = {p: {'old': None if a is None else sha(a), 'new': sha(b)} for p, (a, b) in edits.items()}
    digest = sha(Q.P.encoded({'sources': recorded, 'protected': {p: sha(b) for p, b in retained.items()},
                             'helpers': {p: sha(b) for p, b in helpers.items()}, 'snapshot_policy': P.CACHE_PREDICATE,
                             'static_asset_policy': 'EXACT_NEW_ONLY_HASH_METADATA_RENAME_NO_PHP_LINT'}))
    return edits, helpers, retained, recorded, digest


def replace(remote, path, old, new, metadata):
    if path.endswith('.php'):
        return PHP_REPLACE(remote, path, old, new, metadata)
    assets, _ = prepared()
    M.B, M.PINS = type('AssetNames', (), {'PHOTO': PHOTO, 'JS': JS, 'MANIFEST': MANIFEST}), {n: sha(assets[n]) for n in (PHOTO, JS, MANIFEST)}
    return M.replace_asset_or_php(remote, path, old, new, metadata)


def public(remote, baseline, retained, edits, active):
    data, kind = Q.get(D.URL)
    text, before = data.decode(), baseline.decode()
    require('text/html' in kind, 'Homepage MIME')
    proof = D.assert_home(text, before)
    previous = M.main_html(before)
    expected = rewrite(previous, PUBLIC_PHOTO) if active else previous
    require(M.main_html(text) == expected, 'Only the two approved figure changes allowed')
    for selector in ('//*[@id="masthead"]', '//*[@id="colophon"]'):
        a, b = html.fromstring(before).xpath(selector)[0], html.fromstring(text).xpath(selector)[0]
        require(html.tostring(a) == html.tostring(b), 'Native header/footer changed')
    selected = JS if active else 'home-family-practical.js'
    blob, kind = Q.get('https://harmat22.hu/wp-content/mu-plugins/' + ASSET + '/' + selected)
    wanted = edits[M.ASSET + '/' + JS][1] if active else retained[ASSET + '/home-family-practical.js']
    require(sha(blob) == sha(wanted) and 'javascript' in kind and text.count('/' + selected + '?ver=') == 1, 'Selected runtime identity')
    if active:
        blob, kind = Q.get(PUBLIC_PHOTO)
        require(kind.startswith('image/jpeg') and sha(blob) == PHOTO_SHA, 'Public user photograph identity')
        require('data-i18n="parkCaption"' not in M.main_html(text) and 'class="hhf-photo-credit"' not in M.main_html(text), 'Removed park note remains')
    for route in ('/lakaskereso/', '/elerhetosegeink/', '/property/a1-f-l1/', '/virtualis-lakasvalaszto/', '/virtualis-lakasvalaszto-elso-utem/', '/virtualis-lakasvalaszto-a1-epulet/'):
        time.sleep(.5)
        blob, kind = Q.get('https://harmat22.hu' + route)
        require('text/html' in kind and ('/' + JS + '?ver=').encode() not in blob, 'Homepage change leaked outside homepage')
    proof.update({'health_routes': 7, 'only_two_approved_figures': True, 'original_user_photo_uncropped': active, 'native_header_footer_unchanged': True, 'submission': False})
    return proof


def configure():
    S.JS, S.MANIFEST = JS, MANIFEST
    S.PREFIX = H.BACKUPS + '/home-showroom-'
    S.release, S.public, S.state = release, public, P.state
    Q.replace = replace
    S.__doc__ = __doc__
    return S


if __name__ == '__main__':
    if '--prepare' in __import__('sys').argv:
        require(__import__('sys').argv[1:] == ['--prepare'], 'Prepare is offline only')
        prepare()
    else:
        configure().main()
