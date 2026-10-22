"""Approved native homepage copy/photo release. Offline by default; no form dispatch."""
import importlib.util
import json
from pathlib import Path
import re
import time
import urllib.request
import uuid

from lxml import html

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]


def module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    item = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(item)
    return item


B = module('practical_build', HERE / '2026-10-23-prepare-home-practical.py')
M = module('practical_prior_transport', HERE / '2026-10-20-deploy-home-loop.py')
S, P, D, H, Q = M.S, M.P, M.D, M.H, M.Q
require, sha = M.require, M.sha
ASSET, HOME = M.ASSET, M.HOME
PHP_REPLACE = Q.replace
PINS = {
    'body-practical.php': '868b2b5b750a2f05bc9be753510342df6421e291797d083e15f803640172b4d9',
    'home-family-practical.js': '7c42df417edc93fbd5fcc390640fd71a2b5f97add611e067e79f01c1f945c77b',
    'manifest-practical.json': '42a73d9100bb7422a1e85a94e079741a436466aa76ae14b6d935f10d962822b4',
    'ohegy-park-2024-original.jpg': 'fc603a46552558b2acfdae68ab3bad57d569e7d19d11bb02b86afbc727c9c266',
    'zz-harmat-home-family.php': 'b116dc413a0aea1c367c955b98669987368ee05b0c74b46cf2199bb013ba86df',
}


def replace_asset_or_php(remote, path, old, new, metadata):
    if path.endswith('.php'):
        return PHP_REPLACE(remote, path, old, new, metadata)
    allowed = {ASSET + '/' + name: PINS[name] for name in (B.PHOTO, B.JS, B.MANIFEST)}
    require(path in allowed and old is None and sha(new) == allowed[path], 'Only exact new static release assets allowed')
    require(Q.P.absent(remote, path), 'New static asset collision')
    stage = path.rsplit('/', 1)[0] + '/.' + Path(path).name + '.' + uuid.uuid4().hex + '.tmp'
    try:
        remote.write_new(stage, new)
        remote.stage_metadata(stage, metadata)
        require(remote.read(stage) == new and Q.P.absent(remote, path), 'Static stage identity or absence changed')
        remote.sftp.rename(stage, path)
        require(Q.metadata(remote, path, new) == tuple(metadata), 'Final static asset identity changed')
    finally:
        if not Q.P.absent(remote, stage):
            actual = remote.read(stage)
            require(new.startswith(actual), 'Foreign static stage retained')
            remote.remove_owned(stage, actual)


def release():
    old = B.original(D.MAIN)
    require(sha(old) == B.OLD_HOME_SHA, 'Exact stable original controller required')
    new = S.read_local(D.MAIN)
    assets = {n: S.read_local(D.ASSET + '/' + n) for n in (B.BODY, B.JS, B.MANIFEST)}
    assets[B.PHOTO] = (B.OUT / B.PHOTO).read_bytes()
    require({D.MAIN: sha(new), **{n: sha(b) for n, b in assets.items()}} == PINS, 'Reviewed practical source/image pins required')
    require(new == B.controller(assets[B.MANIFEST]), 'Controller selection and scoped style delta changed')
    require(assets[B.BODY] == B.rewrite(B.original(D.ASSET + '/body-six.php').decode(), '__HHF_ASSET_BASE__' + B.PHOTO).encode(), 'Body exceeds reviewed copy/photo consolidation')
    require(assets[B.JS] == B.runtime(), 'Runtime exceeds approved dictionary changes')
    previous = S.read_local(D.ASSET + '/manifest-loop.json')
    require(sha(previous) == '0ea9f878ae7eacf6602af0ae0bda89e899ebea73ec6ca968c59d29fc07f5d998', 'Retained loop manifest changed')
    pins = json.loads(previous)
    require(json.loads(assets[B.MANIFEST]) == {**pins, **{n: sha(assets[n]) for n in (B.BODY, B.JS, B.PHOTO)}}, 'Prior immutable asset pins must remain')
    protected = {D.ASSET + '/' + n: S.read_local(D.ASSET + '/' + n) for n in pins}
    require(all(sha(protected[D.ASSET + '/' + n]) == pin for n, pin in pins.items()), 'Retained asset drift')
    for name in ('manifest.json', 'manifest-scroll-play.json', 'manifest-six.json', 'manifest-six-r2.json', 'manifest-loop.json'):
        protected[D.ASSET + '/' + name] = S.read_local(D.ASSET + '/' + name)
    for name, pin in {
        'zz-harmat-home-assistant-inline.php': '12f6ea88ed68b548aae279496b57402ed5d96c123788d9f1b176e346edd701ca',
        'zz-harmat-home-apartment-rotation.php': 'a505545d3c548206f04db2360847e56e48b44102e707c846a100795fd66bc957',
        'zz-harmat-home-video-fullscreen.php': '38383bbaacb876e0b3de4b63d22fc23c6f29679784e3d4cf99e56d9ddd9d4580',
        'zz-harmat-construction-updates.php': '4a92047c91b1eabd846910914ed1f27332d7218a5a06028dfcc4d053f2db654f',
    }.items():
        protected[name] = S.read_local(name)
        require(sha(protected[name]) == pin, 'Retained UI/journal owner changed: ' + name)
    footer = S.read_local('harmat-migrated-snippets.php')
    raw = b''.join(line if i in (4, 175, 176, 177) else line.replace(b'\n', b'\r\n')
                   for i, line in enumerate(footer.splitlines(keepends=True)))
    require(sha(raw) == 'f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077', 'Exact retained raw footer required')
    protected['harmat-migrated-snippets.php'] = raw
    # The selected image and immutable dependencies arrive before the controller switches.
    edits = {M.ASSET + '/' + n: (None, assets[n]) for n in (B.PHOTO, B.BODY, B.JS, B.MANIFEST)}
    edits[M.HOME] = (old, new)
    helpers = {n: (HERE / n).read_bytes().replace(b'\r\n', b'\n') for n in (D.TEST, D.CHECKER)}
    recorded = {p: {'old': None if a is None else sha(a), 'new': sha(b)} for p, (a, b) in edits.items()}
    digest = sha(Q.P.encoded({'sources': recorded, 'protected': {p: sha(b) for p, b in protected.items()},
                             'static_asset_policy': 'EXACT_NEW_ONLY_HASH_METADATA_RENAME_NO_PHP_LINT',
                             'helpers': {p: sha(b) for p, b in helpers.items()}, 'snapshot_policy': P.CACHE_PREDICATE}))
    return edits, helpers, protected, recorded, digest


def main_html(text):
    return M.main_html(text)


def public(remote, baseline, protected, edits, active):
    data, kind = Q.get(D.URL)
    require('text/html' in kind, 'Homepage MIME')
    text, previous = data.decode(), baseline.decode()
    proof = D.assert_home(text, previous)
    expected = B.rewrite(main_html(previous), B.PUBLIC_PHOTO) if active else main_html(previous)
    require(main_html(text) == expected, 'Homepage delta differs from approved copy/photo; cards, journal and media must stay exact')
    before, after = html.fromstring(previous), html.fromstring(text)
    for selector in ('//*[@id="masthead"]', '//*[@id="colophon"]'):
        require(html.tostring(before.xpath(selector)[0]) == html.tostring(after.xpath(selector)[0]), 'Native menu/footer changed')
    current_js = B.JS if active else 'home-family-loop.js'
    require(text.count('/' + current_js + '?ver=') == 1, 'One selected original-behavior runtime required')
    body, kind = Q.get('https://harmat22.hu/wp-content/mu-plugins/' + D.ASSET + '/' + current_js)
    wanted = edits[M.ASSET + '/' + B.JS][1] if active else protected[D.ASSET + '/home-family-loop.js']
    require(sha(body) == sha(wanted) and 'javascript' in kind, 'Public runtime mismatch')
    if active:
        request = urllib.request.Request(B.PUBLIC_PHOTO, method='HEAD', headers={'User-Agent': 'HarmatOfferSafety/1.0'})
        with urllib.request.urlopen(request, timeout=30) as response:
            require(response.status == 200 and response.geturl() == B.PUBLIC_PHOTO
                    and response.headers.get('Content-Type', '').startswith('image/jpeg')
                    and int(response.headers.get('Content-Length', '-1')) == 8154459, 'Original public image identity/size/MIME changed')
    for marker in ('harmat-home-video-fullscreen-js', 'harmat-home-assistant-inline-script', 'harmat-home-rotation-script'):
        require(text.count('id="' + marker + '"') == 1, 'Retained feature owner missing: ' + marker)
    for route in ('/lakaskereso/', '/elerhetosegeink/', '/property/a1-f-l1/',
                  '/virtualis-lakasvalaszto/', '/virtualis-lakasvalaszto-elso-utem/', '/virtualis-lakasvalaszto-a1-epulet/'):
        time.sleep(0.5)
        body, kind = Q.get('https://harmat22.hu' + route)
        require('text/html' in kind and ('/' + B.JS + '?ver=').encode() not in body, 'Homepage change leaked outside homepage')
    proof.update({'health_routes': 7, 'approved_copy_and_photo_only': True, 'native_header_footer_unchanged': True,
                  'dynamic_cards_journal_media_unchanged': True, 'photo': '2024-11-09_original_uncropped', 'submission': False})
    return proof


def configure():
    S.JS, S.MANIFEST = B.JS, B.MANIFEST
    S.PREFIX = H.BACKUPS + '/home-practical-'
    S.release, S.public, S.state = release, public, P.state
    Q.replace = replace_asset_or_php
    S.__doc__ = __doc__
    return S


if __name__ == '__main__':
    configure().main()
