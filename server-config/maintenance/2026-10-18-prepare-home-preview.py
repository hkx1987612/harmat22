"""Build local-only homepage preview assets from public/previously approved media."""
import argparse
import base64
import hashlib
from html import escape
from html.parser import HTMLParser
import io
import json
import mimetypes
from pathlib import Path
import shutil
import tarfile
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'outputs/2026-10-18-home-family'
ASSETS = OUT / 'assets'
SITE = 'https://harmat22.hu'
CODES = ('A1-1-L6', 'A2-4-L5', 'A1-1-L5')
MEDIA = {
    'hero-aerial.jpg': 'wp-mu-plugins/harmat-project-family/project-aerial.jpg',
    'project-courtyard.jpg': 'wp-mu-plugins/harmat-project-family/project-courtyard.jpg',
    'project-living-room.jpg': 'wp-mu-plugins/harmat-project-family/project-living-room.jpg',
    'construction-site.webp': 'wp-mu-plugins/harmat-project-family/construction-site.webp',
    'showroom-lounge.jpg': 'wp-mu-plugins/harmat-contact-family/showroom-lounge.jpg',
    'showroom-model.jpg': 'wp-mu-plugins/harmat-contact-family/showroom-model.jpg',
    'project-location.jpg': 'wp-mu-plugins/harmat-contact-family/project-location.jpg',
    'park.jpg': 'outputs/construction-nearby-park/harmat-kornyek-kutyapark-2026-09.jpg',
    'hero-video.mp4': 'outputs/home-native-video/harmat-home-1080p-v2.mp4',
}


def require(value, message):
    if not value:
        raise RuntimeError(message)


def fetch(url, limit=2 * 1024 * 1024):
    req = urllib.request.Request(url, headers={'User-Agent': 'HarmatLocalHomepagePreview/1.0'})
    with urllib.request.urlopen(req, timeout=30) as response:
        data = response.read(limit + 1)
        require(response.status == 200 and len(data) <= limit, 'Public asset status/size')
        return data, response.headers.get('Content-Type', '')


class Images(HTMLParser):
    def __init__(self, text):
        super().__init__(convert_charrefs=True)
        self.images = []
        self.feed(text)

    def handle_starttag(self, tag, attrs):
        if tag == 'img':
            self.images.append(dict(attrs))


def public_media(url, target):
    url = urllib.parse.urljoin(SITE, url)
    parsed = urllib.parse.urlsplit(url)
    require(parsed.scheme == 'https' and parsed.netloc == 'harmat22.hu'
        and parsed.path.startswith('/wp-content/'), 'Only official public image assets')
    data, mime = fetch(url, 10 * 1024 * 1024)
    require(mime.startswith('image/'), 'Image MIME boundary')
    target.write_bytes(data)
    return {'url': url, 'sha256': hashlib.sha256(data).hexdigest(), 'bytes': len(data)}


def stage_panorama():
    ASSETS.mkdir(parents=True, exist_ok=True)
    source = SITE + '/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_f.jpg'
    proof = public_media(source, ASSETS / 'project-panorama.jpg')
    (OUT / 'panorama-manifest.json').write_text(json.dumps(proof, indent=2), encoding='utf-8')
    print(json.dumps({'status': 'PUBLIC_PANORAMA_POSTER_STAGED', **proof}))


def stage():
    ASSETS.mkdir(parents=True, exist_ok=True)
    proof = {}
    for filename, source in MEDIA.items():
        origin = ROOT / source
        require(origin.is_file(), 'Missing approved local media ' + source)
        target = ASSETS / filename
        shutil.copyfile(origin, target)
        proof[filename] = {'local_source': source, 'sha256': hashlib.sha256(target.read_bytes()).hexdigest(), 'bytes': target.stat().st_size}

    # Prior self-contained reviewed Project preview already carries the real logo.
    previous = ROOT / 'outputs/2026-10-17-project-family/harmat-project-family-preview-v1.html'
    images = Images(previous.read_text(encoding='utf-8')).images
    logo = next((row['src'] for row in images if row.get('alt') == 'Harmat Lakópark'
        and row.get('src', '').startswith('data:image/png;base64,')), None)
    require(logo is not None, 'Exact approved PNG logo missing')
    logo_data = base64.b64decode(logo.split(',', 1)[1], validate=True)
    require(logo_data.startswith(b'\x89PNG\r\n\x1a\n'), 'Original PNG logo signature')
    (ASSETS / 'harmat-logo.png').write_bytes(logo_data)

    data, mime = fetch(SITE + '/wp-json/harmat-public/v1/apartments')
    require('json' in mime, 'Public inventory MIME')
    items = json.loads(data)['items']
    facts = []
    for code in CODES:
        matches = [row for row in items if row['code'] == code]
        require(len(matches) == 1 and matches[0]['status'] == 'current', 'Exact available public apartment ' + code)
        row = matches[0]
        facts.append(row)
        page_url = row['url']
        require(page_url == SITE + '/property/' + code.lower() + '/', 'Official property URL')
        html, page_mime = fetch(page_url)
        require('text/html' in page_mime, 'Public property MIME')
        candidates = [image for image in Images(html.decode('utf-8')).images
            if code.lower() in image.get('src', '').lower() and '-display.' in image.get('src', '').lower()]
        require(len({image['src'] for image in candidates}) == 1, 'Unique official cropped floorplan ' + code)
        name = 'apartment-' + code.lower() + '.jpg'
        proof[name] = public_media(candidates[0]['src'], ASSETS / name)

    # Vendor a pinned official npm Lucide release for file:// operation, with license.
    metadata, _ = fetch('https://registry.npmjs.org/lucide/0.468.0')
    distribution = json.loads(metadata)['dist']
    require(distribution['tarball'] == 'https://registry.npmjs.org/lucide/-/lucide-0.468.0.tgz', 'Pinned package origin')
    archive, _ = fetch(distribution['tarball'], 10 * 1024 * 1024)
    integrity = 'sha512-' + base64.b64encode(hashlib.sha512(archive).digest()).decode()
    require(distribution['integrity'] == integrity, 'Package integrity mismatch')
    with tarfile.open(fileobj=io.BytesIO(archive), mode='r:gz') as package:
        for source, filename in (('package/dist/umd/lucide.min.js', 'lucide.min.js'), ('package/LICENSE', 'lucide.LICENSE')):
            member = package.getmember(source)
            require(member.isfile() and member.size < 2 * 1024 * 1024, 'Exact package member')
            (ASSETS / filename).write_bytes(package.extractfile(member).read())
    proof['lucide.min.js'] = {'package': 'lucide@0.468.0', 'integrity': integrity}
    (OUT / 'facts.json').write_text(json.dumps(facts, ensure_ascii=False, indent=2), encoding='utf-8')
    (OUT / 'asset-manifest.json').write_text(json.dumps(proof, ensure_ascii=True, indent=2), encoding='utf-8')
    stage_panorama()
    print(json.dumps({'status': 'LOCAL_ASSETS_STAGED', 'directory': str(OUT), 'apartments': facts}, ensure_ascii=False))


def copy_source():
    source = ROOT / 'server-config/previews/home-family'
    for filename in ('index.html', 'home-family.css', 'home-family.js'):
        origin = source / filename
        require(origin.is_file(), 'Missing preview code ' + filename)
        OUT.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(origin, OUT / filename)
    print(json.dumps({'status': 'LOCAL_PREVIEW_CODE_COPIED', 'html': str(OUT / 'index.html')}))


class Bundle(HTMLParser):
    """Inline local presentation assets, keeping only the local video as a sibling."""
    def __init__(self, source):
        super().__init__(convert_charrefs=False)
        self.parts = []
        self.scripts = []
        self.skip_script = False
        self.feed(source)
        self.close()
        require(not self.scripts and not self.skip_script, 'Script bundle requires complete body')

    @staticmethod
    def asset(value):
        target = (OUT / value).resolve()
        require(target.is_relative_to(OUT.resolve()) and target.is_file(), 'Owned local resource only')
        return target

    def image(self, value):
        target = self.asset(value)
        require(target.suffix in ('.png', '.jpg', '.jpeg', '.webp'), 'Expected raster image')
        mime = mimetypes.guess_type(target.name)[0]
        return 'data:' + mime + ';base64,' + base64.b64encode(target.read_bytes()).decode()

    def handle_decl(self, decl):
        self.parts.append('<!' + decl + '>')

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'link' and a.get('rel') == 'stylesheet':
            require(a.get('href') == 'home-family.css', 'Exact stylesheet only')
            css = self.asset(a['href']).read_text(encoding='utf-8')
            require('</style' not in css.lower() and 'url(' not in css.lower(), 'Unexpected style resource/terminator')
            self.parts.append('<style>' + css + '</style>')
            return
        if tag == 'script' and 'src' in a:
            require(a['src'] in ('home-family.js', 'assets/lucide.min.js'), 'Exact local scripts only')
            script = self.asset(a['src']).read_text(encoding='utf-8')
            require('</script' not in script.lower(), 'Unexpected script terminator')
            self.scripts.append(script)
            self.skip_script = True
            return
        if tag == 'meta' and a.get('http-equiv', '').lower() == 'content-security-policy':
            a['content'] = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; script-src 'unsafe-inline'; media-src 'self' file:; connect-src 'none'; form-action 'none'; base-uri 'none'; frame-src 'none'; object-src 'none'"
        if tag == 'img':
            require('src' in a and 'srcset' not in a, 'One explicit local image source')
            a['src'] = self.image(a['src'])
        if tag == 'video' and a.get('poster'):
            a['poster'] = self.image(a['poster'])
        self.parts.append('<' + tag + ''.join(' ' + k + ('="' + escape(v, quote=True) + '"' if v is not None else '') for k, v in a.items()) + '>')

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)

    def handle_endtag(self, tag):
        if tag == 'script' and self.skip_script:
            self.skip_script = False
            return
        if tag == 'body':
            self.parts.extend('<script>' + script + '</script>' for script in self.scripts)
            self.scripts.clear()
        self.parts.append('</' + tag + '>')

    def handle_data(self, data):
        if not self.skip_script:
            self.parts.append(data)

    def handle_entityref(self, name):
        self.parts.append('&' + name + ';')

    def handle_charref(self, name):
        self.parts.append('&#' + name + ';')

    def handle_comment(self, text):
        self.parts.append('<!--' + text + '-->')


def build_bundle():
    target = OUT / 'harmat-home-family-preview-v1.html'
    record = OUT / 'bundle-proof.json'
    if target.exists():
        require(record.is_file() and json.loads(record.read_text())['sha256'] == hashlib.sha256(target.read_bytes()).hexdigest(), 'Refuse unrelated edited preview overwrite')
    parser = Bundle((OUT / 'index.html').read_text(encoding='utf-8'))
    data = ''.join(parser.parts).encode('utf-8')
    target.write_bytes(data)
    require(target.read_bytes() == data, 'Bundle readback')
    proof = {'sha256': hashlib.sha256(data).hexdigest(), 'bytes': len(data),
        'images_css_js_embedded': True, 'video': 'assets/hero-video.mp4', 'no_server_writes': True}
    record.write_text(json.dumps(proof, indent=2), encoding='utf-8')
    print(json.dumps({'status': 'LOCAL_IMAGE_EMBEDDED_PREVIEW_READY', 'file': str(target), **proof}))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--assets', action='store_true')
    parser.add_argument('--panorama', action='store_true', help='Stage only the original official panorama poster')
    parser.add_argument('--copy-source', action='store_true')
    parser.add_argument('--bundle', action='store_true')
    args = parser.parse_args()
    if args.assets:
        stage()
    elif args.panorama:
        stage_panorama()
    if args.copy_source:
        copy_source()
    if args.bundle:
        build_bundle()
    if not args.assets and not args.panorama and not args.copy_source and not args.bundle:
        print('OFFLINE_NO_WRITES: use --assets, --panorama, --copy-source and/or --bundle; no server writes in any mode')
