"""Guarded release for the neighborhood and homepage construction teaser only."""
import argparse
import json
from pathlib import Path
import re
import runpy
import subprocess
import uuid

from lxml import html

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
B = runpy.run_path(str(HERE / '2026-10-29-deploy-search-budget.py'))
H, Q, N = B['H'], B['Q'], B['N']
require, sha, norm = B['require'], B['sha'], B['norm']
BASE = '6d60891'
MU = H.LIVE + '/wp-content/mu-plugins/'
MODULES = ('zz-harmat-neighborhood-practical.php', 'zz-harmat-construction-updates.php')
ASSETS = 'harmat-neighborhood-practical/r2'
ROUTE = '/harmat-lakopark-kornyeke/'
ROUTES = (ROUTE, *B['ROUTES'])
PREFIX = H.BACKUPS + '/site-maintenance-20261122-'
LOCK = H.BACKUPS + '/.site-maintenance-20261122.lock'
POSTER = '2026-10-30-harmat-site.jpg'


def candidate():
    source = ROOT / 'wp-mu-plugins'
    old = {name: subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + name], cwd=ROOT) for name in MODULES}
    new = {name: norm((source / name).read_bytes()) for name in MODULES}
    expected = old[MODULES[1]].replace(b'Version: 1.0.2', b'Version: 1.0.3').replace(b", 'journal_only' => true", b'')
    require(new[MODULES[1]] == expected and new[MODULES[1]] != old[MODULES[1]], 'Unexpected construction changes')
    manifest = norm((source / ASSETS / 'manifest.json').read_bytes())
    pins = json.loads(manifest)
    require(tuple(pins) == ('body.html', 'neighborhood.css', 'neighborhood.js'), 'Unexpected bundle files')
    assets = {name: norm((source / ASSETS / name).read_bytes()) for name in pins}
    require(all(sha(assets[name]) == pin for name, pin in pins.items()), 'Neighborhood pin mismatch')
    require(sha(manifest).encode() in new[MODULES[0]], 'Controller does not pin the revision')
    assets['manifest.json'] = manifest
    return old, new, assets


def public_page(route):
    blob, mime = Q.get('https://harmat22.hu' + route)
    require('text/html' in mime and not re.search(rb'Fatal error:|Parse error:|critical error on this website', blob), 'Public page failed: ' + route)
    return blob


def proof(before, after, active, assets):
    for route in ROUTES:
        a, b = (N.D.Page(page.decode()) for page in (before[route], after[route]))
        require(a.form and a.form == b.form and a.seo == b.seo and a.canonical == b.canonical, 'Native form or SEO changed: ' + route)
        x, y = (html.fromstring(page.decode()) for page in (before[route], after[route]))
        for ident in ('masthead', 'colophon', 'h22-offer-modal', 'harmat-internal-language'):
            left, right = (doc.xpath('//*[@id=$id]', id=ident) for doc in (x, y))
            require(len(left) == len(right) == 1 and B['clean'](left[0]) == B['clean'](right[0]), 'Native shell changed: ' + route + ' ' + ident)
        owned = {ROUTE: 'harmat-neighborhood-practical', '/': 'hhf-epitesi-naplo'}.get(route)
        if owned:
            left, right = (doc.xpath('//*[@id=$id]', id=owned) for doc in (x, y))
            require(len(left) == len(right) == 1, 'Owned section missing: ' + route)
            if route == ROUTE and active:
                expected = html.fromstring(assets['body.html'].decode())
                for selector in ('.//article[@class="place-row"]', './/*[@id="hn-facility-data"]'):
                    require([B['clean'](n) for n in right[0].xpath(selector)] ==
                            [B['clean'](n) for n in expected.xpath(selector)], 'Neighborhood markup mismatch: ' + selector)
                photos = [doc.xpath('.//img[@class="hero-photo"]') for doc in (left[0], right[0], expected)]
                require(all(len(group) == 1 for group in photos) and
                        photos[0][0].attrib == photos[1][0].attrib == photos[2][0].attrib,
                        'Original park image attributes changed')
                require(after[route].count((ASSETS + '/neighborhood.css').encode()) == 1, 'New stylesheet not loaded')
            elif route == '/' and active:
                require(right[0].xpath('.//time/@datetime') == ['2026-10-30']
                        and right[0].xpath('.//*[@data-hcu-poster]/@data-hcu-poster')[0].endswith(POSTER)
                        and right[0].xpath('.//*[@data-hcu-video]/@data-hcu-video')[0].endswith(POSTER.replace('.jpg', '.mp4')),
                        'Homepage poster, video or capture date not updated')
                require(not right[0].xpath('.//video'), 'Construction video must still be click-to-play')
            else:
                require(B['clean'](left[0]) == B['clean'](right[0]), 'Restored section differs: ' + route)
            for node in (left[0], right[0]):
                node.drop_tree()
        ident = 'content' if route.startswith('/property/') else 'main'
        left, right = (doc.xpath('//*[@id=$id]', id=ident) for doc in (x, y))
        require(len(left) == len(right) == 1 and B['clean'](left[0]) == B['clean'](right[0]), 'Non-owned content changed: ' + route)
    return {'routes': len(ROUTES), 'native_forms_and_other_content_unchanged': True, 'submitted': False}


def operate(old, new, assets, rollback=None):
    remote = H.Remote()
    backup = rollback or PREFIX + uuid.uuid4().hex
    locked = False
    written = []
    try:
        remote.directory(H.BACKUPS)
        remote.mkdir(LOCK)
        locked = True
        if rollback:
            require(re.fullmatch(re.escape(PREFIX) + '[a-f0-9]{32}', rollback), 'Invalid private backup path')
            remote.directory(backup, private=True)
        current = {name: remote.read(MU + name) for name in MODULES}
        original = {name: remote.read(backup + '/old-' + name, private=True) if rollback else current[name] for name in MODULES}
        updated = {name: B['raw_patch'](original[name], old[name], new[name]) for name in MODULES}
        want, have = (original, updated) if rollback else (updated, original)
        require(current == have, 'Live sources differ from expected state')
        metadata = {name: Q.metadata(remote, MU + name, current[name]) for name in MODULES}
        if rollback:
            before = {route: remote.read(backup + '/page-' + str(i) + '.html', private=True) for i, route in enumerate(ROUTES)}
        else:
            before = {route: public_page(route) for route in ROUTES}
            remote.mkdir(backup)
            for i, page in enumerate(before.values()):
                remote.write_new(backup + '/page-' + str(i) + '.html', page)
            for name in MODULES:
                for prefix, payload in (('old-', original), ('new-', updated)):
                    private = backup + '/' + prefix + name
                    remote.write_new(private, payload[name])
                    remote.lint(private)
            print(json.dumps({'status': 'BACKED_UP', 'backup': backup}), flush=True)
            if N.absent(remote, MU + ASSETS):
                remote.sftp.mkdir(MU + ASSETS, 0o755)
                remote.sftp.chmod(MU + ASSETS, 0o755)
                for name, data in assets.items():
                    target = MU + ASSETS + '/' + name
                    remote.write_new(target, data)
                    remote.sftp.chmod(target, 0o644)
            remote.directory(MU + ASSETS)
            require(set(remote.sftp.listdir(MU + ASSETS)) == set(assets), 'Unexpected staged files')
            for name, data in assets.items():
                require(remote.read(MU + ASSETS + '/' + name) == data, 'Staged asset differs: ' + name)
        for name in MODULES:
            written.append(name)
            Q.replace(remote, MU + name, have[name], want[name], metadata[name])
        remote.purge()
        for name in MODULES:
            require(remote.read(MU + name) == want[name], 'Live source differs: ' + name)
        results = proof(before, {route: public_page(route) for route in ROUTES}, not rollback, assets)
        if not rollback:
            for name, marker in (('neighborhood.css', 'text/css'), ('neighborhood.js', 'javascript')):
                body, mime = Q.get('https://harmat22.hu/wp-content/mu-plugins/' + ASSETS + '/' + name)
                require(body == assets[name] and marker in mime, 'Public asset differs: ' + name)
        print(json.dumps({'status': 'ROLLED_BACK' if rollback else 'RELEASED', 'backup': backup, 'checks': results}), flush=True)
    except BaseException:
        for name in reversed(written):
            current = remote.read(MU + name)
            if current == want[name]:
                Q.replace(remote, MU + name, current, have[name], metadata[name])
            else:
                require(current == have[name], 'Foreign edits preserved; inspect ' + name)
        if written:
            remote.purge()
            print(json.dumps({'status': 'MODULES_RESTORED', 'backup': backup}), flush=True)
        raise
    finally:
        try:
            if locked:
                remote.sftp.rmdir(LOCK)
        finally:
            remote.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument('--deploy', action='store_true')
    modes.add_argument('--rollback')
    args = parser.parse_args()
    bundle = candidate()
    if args.deploy or args.rollback:
        operate(*bundle, rollback=args.rollback)
    else:
        print(json.dumps({'status': 'OFFLINE_PASS', 'modules': {name: sha(data) for name, data in bundle[1].items()}}))
