"""Reproduce approved copy/image as immutable native-home assets; no network."""
from copy import deepcopy
from hashlib import sha256
from html import escape
import importlib.util
import json
from pathlib import Path
import re
import subprocess

from lxml import etree, html
from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
HERE = Path(__file__).resolve().parent
ASSET = ROOT / 'wp-mu-plugins/harmat-home-family'
OUT = ROOT / 'outputs/2026-10-23-home-practical-release'
BASE = '261ae44'
BODY = 'body-practical.php'
JS = 'home-family-practical.js'
PHOTO = 'ohegy-park-2024-original.jpg'
MANIFEST = 'manifest-practical.json'
spec = importlib.util.spec_from_file_location('approved_practical', ROOT / 'server-config/previews/home-practical/build.py')
PREVIEW = importlib.util.module_from_spec(spec)
spec.loader.exec_module(PREVIEW)
COPY, PARK = PREVIEW.COPY, PREVIEW.PARK
OLD_HOME_SHA = '36a2f2ff3cba033228dd5d12cd70833c6803ac9895d502501900347b3c34d29a'
PUBLIC_PHOTO = 'https://harmat22.hu/wp-content/mu-plugins/harmat-home-family/' + PHOTO
CSS = (
    'body.home.page-id-164 #harmat-home-family .hhf-showroom-copy>a+a{margin-left:24px}'
    'body.home.page-id-164 #harmat-home-family img[data-hhf-ohegy]{aspect-ratio:4/3!important;height:auto!important;object-fit:contain!important}'
    'body.home.page-id-164 #harmat-home-family .hhf-photo-credit{display:block;margin-top:6px;font-size:11px;line-height:1.6;color:var(--muted);overflow-wrap:anywhere}'
    '@media(max-width:767px){body.home.page-id-164 #harmat-home-family .hhf-showroom-copy>a+a{margin-left:0;display:flex;width:fit-content}}'
)


def require(ok, message):
    if not ok:
        raise RuntimeError(message)


def sha(data):
    return sha256(data).hexdigest()


def original(name):
    return subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + name], cwd=ROOT)


def one_replace(text, old, new):
    require(text.count(old) == 1, 'Unique owned fragment required')
    return text.replace(old, new, 1)


def text_rewrite(text, key, value):
    pattern = re.compile(r'(<[^>]+\bdata-i18n="' + re.escape(key) + r'"[^>]*>)([^<]*)(</[a-z0-9]+>)')
    matches = list(pattern.finditer(text))
    require(len(matches) == 1, 'Unique copy key required: ' + key)
    node = html.fragment_fromstring(matches[0].group())
    require(node.get('data-i18n') == key and len(node) == 0, 'Text-only copy owner required')
    return pattern.sub(lambda m: m[1] + escape(value, quote=False) + m[3], text, count=1)


def photo_figure(url):
    figure = etree.Element('figure')
    etree.SubElement(figure, 'img', {
        'src': url, 'width': '4096', 'height': '3072', 'alt': PARK['alt'][0],
        'data-i18n-alt': 'parkAlt', 'data-hhf-ohegy': '', 'loading': 'lazy', 'decoding': 'async',
    })
    etree.SubElement(figure, 'figcaption', {'data-i18n': 'parkCaption'}).text = PARK['caption'][0]
    credit = etree.SubElement(figure, 'span', {'class': 'hhf-photo-credit'})
    a = etree.SubElement(credit, 'a', {'href': PARK['source'], 'target': '_blank', 'rel': 'noopener noreferrer'})
    a.text = PARK['author'] + ' / Wikimedia Commons'
    a.tail = ' · '
    etree.SubElement(credit, 'a', {'href': PARK['license'], 'target': '_blank', 'rel': 'noopener noreferrer'}).text = PARK['license_name']
    return etree.tostring(figure, encoding='unicode', method='html')


def rewrite(text, photo_url):
    for key, (hu, _) in COPY.items():
        text = text_rewrite(text, key, hu)
    team = re.findall(r'    <section class="hhf-team hhf-section"[\s\S]*?</section>', text)
    require(len(team) == 1, 'One standalone team band required')
    links = re.findall(r'<a class="hhf-text-link" href="https://harmat22.hu/magunkrol/">[\s\S]*?</a>', team[0])
    require(len(links) == 1, 'Original about link required')
    showroom = re.findall(r'<div class="hhf-showroom-copy">[\s\S]*?</div>', text)
    require(len(showroom) == 1, 'One showroom copy owner required')
    text = one_replace(text, showroom[0], showroom[0][:-6] + links[0] + '</div>')
    text = one_replace(text, team[0], '')
    parks = re.findall(r'<figure><img [^>]*\bdata-i18n-alt="parkAlt"[\s\S]*?</figure>', text)
    require(len(parks) == 1, 'One original park figure required')
    return one_replace(text, parks[0], photo_figure(photo_url))


def runtime():
    text = original('harmat-home-family/home-family-loop.js').decode('utf-8')
    values = {**COPY, 'parkAlt': PARK['alt'], 'parkCaption': PARK['caption']}
    for key, (_, zh) in values.items():
        pattern = re.compile(r'^(\s*' + re.escape(key) + r': )[^\n]+$', re.M)
        text, count = pattern.subn(lambda m: m[1] + json.dumps(zh, ensure_ascii=False) + ',', text, count=1)
        require(count == 1, 'One Chinese dictionary entry required: ' + key)
    approved = ROOT / 'outputs/2026-10-22-home-practical/v1/runtime-after.js'
    require(text == approved.read_text(encoding='utf-8'), 'Runtime differs from reviewed preview')
    return text.encode()


def image():
    source = ROOT / 'outputs/2026-10-22-home-practical/source-images/ohegy-park-2024-11-09-original.jpg'
    require(sha(source.read_bytes()) == PARK['sha256'], 'Approved original photo changed')
    target = OUT / PHOTO
    with Image.open(source) as original_image:
        pixels = original_image.convert('RGB')
        require(pixels.size == (4096, 3072), 'Approved full-resolution image required')
        target.write_bytes(source.read_bytes())
        require(target.read_bytes() == source.read_bytes(), 'Original image must be byte exact')
        proof = {'original_sha256': PARK['sha256'], 'original_bytes': source.stat().st_size,
                 'file': PHOTO, 'bytes': target.stat().st_size, 'sha256': sha(target.read_bytes()),
                 'decoded_rgb_sha256': sha(pixels.tobytes()), 'size': list(pixels.size),
                 'pixel_exact': True, 'resized': False, 'cropped': False}
    return target.read_bytes(), proof


def controller(manifest_bytes):
    old = original('zz-harmat-home-family.php')
    require(sha(old) == OLD_HOME_SHA, 'Stable original controller required')
    text = old.decode()
    text = one_replace(text, ' * Version: 1.0.19', ' * Version: 1.0.20')
    text = one_replace(text, "'/manifest-loop.json'", "'/" + MANIFEST + "'")
    text = one_replace(text, '0ea9f878ae7eacf6602af0ae0bda89e899ebea73ec6ca968c59d29fc07f5d998', sha(manifest_bytes))
    text = one_replace(text, "$pins['home-family-loop.js']))", "$pins['home-family-loop.js'],$pins['" + BODY + "'],$pins['" + JS + "'],$pins['" + PHOTO + "']))")
    text = one_replace(text, "/harmat-home-family/body-six.php'", "/harmat-home-family/" + BODY + "'")
    text = one_replace(text, "array('pannellum.js','home-family-loop.js')", "array('pannellum.js','" + JS + "')")
    text = one_replace(text, "            . '</style>';", "            . '" + CSS + "'\n            . '</style>';")
    return text.encode()


def prepare():
    OUT.mkdir(parents=True, exist_ok=True)
    old_pins = json.loads(original('harmat-home-family/manifest-loop.json'))
    for name, pin in old_pins.items():
        require(sha(original('harmat-home-family/' + name)) == pin, 'Retained immutable asset changed')
    body = rewrite(original('harmat-home-family/body-six.php').decode(), '__HHF_ASSET_BASE__' + PHOTO).encode()
    js = runtime()
    photo, picture_proof = image()
    new_pins = {**old_pins, BODY: sha(body), JS: sha(js), PHOTO: sha(photo)}
    manifest = (json.dumps(new_pins, indent=2) + '\n').encode()
    generated = {BODY: body, JS: js, MANIFEST: manifest}
    for name, blob in generated.items():
        (ASSET / name).write_bytes(blob)
    candidate = controller(manifest)
    (ROOT / 'wp-mu-plugins/zz-harmat-home-family.php').write_bytes(candidate)
    fixture = OUT / 'fixture'
    (fixture / 'harmat-home-family').mkdir(parents=True, exist_ok=True)
    for name in old_pins:
        (fixture / 'harmat-home-family' / name).write_bytes(original('harmat-home-family/' + name))
    (fixture / 'harmat-home-family/manifest-loop.json').write_bytes(original('harmat-home-family/manifest-loop.json'))
    for name, blob in {**generated, PHOTO: photo}.items():
        (fixture / 'harmat-home-family' / name).write_bytes(blob)
    (fixture / 'candidate-home.php').write_bytes(candidate)
    (fixture / 'original-home.php').write_bytes(original('zz-harmat-home-family.php'))
    (OUT / 'image-proof.json').write_text(json.dumps(picture_proof, indent=2), encoding='utf-8')
    print(json.dumps({'status': 'OFFLINE_PREPARED', 'controller': sha(candidate),
                      'assets': {n: sha(b) for n, b in generated.items()}, 'image': picture_proof}))


if __name__ == '__main__':
    prepare()
