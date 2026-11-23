"""Release only the full-width neighborhood hero; keep r2 for rollback."""
import argparse
import json
from pathlib import Path
import runpy
import subprocess
import uuid

from lxml import html

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
P = runpy.run_path(str(HERE / '2026-11-22-deploy-site-maintenance.py'))
H, Q, N = P['H'], P['Q'], P['N']
require, sha, norm = P['require'], P['sha'], P['norm']
clean, raw_patch = P['B']['clean'], P['B']['raw_patch']
MODULE = P['MODULES'][0]
MU = P['MU']
TARGET = MU + MODULE
BASE_ASSETS = P['ASSETS']
ASSETS = BASE_ASSETS.removesuffix('/r2') + '/r3'
ROUTE, ROUTES = P['ROUTE'], P['ROUTES']
PREFIX = H.BACKUPS + '/neighborhood-hero-20261123-'
LOCK = H.BACKUPS + '/.neighborhood-hero-20261123.lock'


def element(doc, selector):
    nodes = doc.xpath(selector)
    require(len(nodes) == 1, 'Expected one element: ' + selector)
    return nodes[0]


def markup(node):
    return (node.tag, dict(node.attrib), (node.text or '').strip(),
            [(markup(child), (child.tail or '').strip()) for child in node])


def hero_proof(old, new, expected):
    selector = './/section[@class="park-hero"]'
    heroes = [element(doc, selector) for doc in (old, new, expected)]
    previous_title = element(heroes[0], './/*[@id="hn-page-title"]')
    expected_title = element(heroes[2], './/*[@id="hn-page-title"]')
    if previous_title.tag == 'h2' and previous_title.get('class') == 'harmat-demoted-h1':
        expected_title.tag = 'h2'
        expected_title.set('class', 'harmat-demoted-h1')
    require(markup(heroes[1]) == markup(heroes[2]), 'Unexpected hero markup')
    images = [element(hero, './img[@class="hero-photo"]') for hero in heroes]
    require(images[0].attrib == images[1].attrib, 'Original image changed')
    require(element(heroes[1], './a[@class="photo-label"]').get('href') == images[1].get('src'), 'Original link not preserved')
    for hero in heroes[:2]:
        hero.drop_tree()


def candidate():
    source = ROOT / 'wp-mu-plugins'
    old = subprocess.check_output(['git', 'show', 'a0e2846:wp-mu-plugins/' + MODULE], cwd=ROOT)
    new = norm((source / MODULE).read_bytes())
    baseline, manifest = (norm((source / revision / 'manifest.json').read_bytes()) for revision in (BASE_ASSETS, ASSETS))
    pins = json.loads(manifest)
    require(tuple(pins) == ('body.html', 'neighborhood.css', 'neighborhood.js'), 'Unexpected bundle files')
    assets = {name: norm((source / ASSETS / name).read_bytes()) for name in pins}
    require(all(sha(assets[name]) == pin for name, pin in pins.items()), 'Bundle checksum mismatch')
    require(new == old.replace(b'Version: 1.0.1', b'Version: 1.0.2').replace(b'/r2', b'/r3').replace(b"'r2/", b"'r3/")
            .replace(sha(baseline).encode(), sha(manifest).encode()), 'Unexpected controller changes')
    require(pins['neighborhood.js'] == json.loads(baseline)['neighborhood.js'], 'Runtime changed')
    before = html.fromstring((source / BASE_ASSETS / 'body.html').read_text(encoding='utf8'))
    after = html.fromstring(assets['body.html'].decode())
    hero_proof(before, after, html.fromstring(assets['body.html'].decode()))
    require(clean(before) == clean(after), 'Non-hero candidate differs')
    return old, new, {**assets, 'manifest.json': manifest}


def proof(before, after, assets):
    for route in ROUTES:
        a, b = (N.D.Page(page.decode()) for page in (before[route], after[route]))
        require(a.form and a.form == b.form and a.seo == b.seo and a.canonical == b.canonical, 'Form or SEO changed: ' + route)
        x, y = (html.fromstring(page.decode()) for page in (before[route], after[route]))
        for ident in ('masthead', 'colophon', 'h22-offer-modal', 'harmat-internal-language'):
            selector = '//*[@id="' + ident + '"]'
            require(clean(element(x, selector)) == clean(element(y, selector)), 'Native shell changed: ' + route + ' ' + ident)
        if route == ROUTE:
            selector = '//*[@id="harmat-neighborhood-practical"]'
            hero_proof(element(x, selector), element(y, selector), html.fromstring(assets['body.html'].decode()))
            require((BASE_ASSETS + '/neighborhood.css').encode() in before[route] and
                    (ASSETS + '/neighborhood.css').encode() in after[route], 'Active hero stylesheet not verified')
        selector = '//*[@id="content"]' if route.startswith('/property/') else '//*[@id="main"]'
        require(clean(element(x, selector)) == clean(element(y, selector)), 'Non-hero content changed: ' + route)


def deploy(old, new, assets):
    r = H.Remote()
    locked = touched = False
    staged = MU + ASSETS
    backup = PREFIX + uuid.uuid4().hex
    try:
        r.directory(H.BACKUPS)
        r.mkdir(LOCK)
        locked = True
        original = r.read(TARGET)
        updated = raw_patch(original, old, new)
        metadata = Q.metadata(r, TARGET, original)
        before = {route: P['public_page'](route) for route in ROUTES}
        r.mkdir(backup)
        for name, blob in (('controller-before.php', original), ('controller-after.php', updated)):
            r.write_new(backup + '/' + name, blob)
            r.lint(backup + '/' + name)
        print(json.dumps({'status': 'BACKED_UP', 'backup': backup}), flush=True)
        if N.absent(r, staged):
            r.sftp.mkdir(staged, 0o755)
            r.sftp.chmod(staged, 0o755)
            for name, blob in assets.items():
                r.write_new(staged + '/' + name, blob)
                r.sftp.chmod(staged + '/' + name, 0o644)
        r.directory(staged)
        require(set(r.sftp.listdir(staged)) == set(assets), 'Unexpected staged files')
        for name, blob in assets.items():
            require(r.read(staged + '/' + name) == blob, 'Unexpected staged content: ' + name)
        touched = True
        Q.replace(r, TARGET, original, updated, metadata)
        r.purge()
        require(r.read(TARGET) == updated, 'Controller readback mismatch')
        proof(before, {route: P['public_page'](route) for route in ROUTES}, assets)
        css, mime = Q.get('https://harmat22.hu/wp-content/mu-plugins/' + ASSETS + '/neighborhood.css')
        require(css == assets['neighborhood.css'] and 'text/css' in mime, 'Public CSS mismatch')
        print(json.dumps({'status': 'RELEASED', 'backup': backup, 'routes': len(ROUTES), 'submitted': False}), flush=True)
    except BaseException:
        if touched:
            current = r.read(TARGET)
            if current == updated:
                Q.replace(r, TARGET, updated, original, metadata)
                r.purge()
                print(json.dumps({'status': 'RESTORED', 'backup': backup}), flush=True)
            else:
                require(current == original, 'Controller was changed externally; preserved')
        raise
    finally:
        try:
            if locked:
                r.sftp.rmdir(LOCK)
        finally:
            r.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--deploy', action='store_true')
    args = parser.parse_args()
    bundle = candidate()
    if args.deploy:
        deploy(*bundle)
    else:
        print(json.dumps({'status': 'OFFLINE_PASS', 'manifest': sha(bundle[2]['manifest.json'])}))
