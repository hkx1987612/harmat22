"""Add-only landing release, with private backup, native parity and source-only recovery."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import runpy
import shlex
import stat
import subprocess
import uuid
from lxml import html

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
A = runpy.run_path(str(HERE / '2026-11-07-deploy-offer-attribution.py'))
B, H = A['B'], A['H']
require, sha = H.require, H.sha
OUT = ROOT / 'outputs/2026-11-09-v4-landing-release'
DIR = H.LIVE + '/wp-content/mu-plugins'
MAIN = 'zz-harmat-v4-landing.php'
ASSET = 'harmat-v4-landing'
MEDIA = H.LIVE + '/wp-content/uploads/' + ASSET
PREFIX = H.BACKUPS + '/v4-landing-'
LOCK = H.BACKUPS + '/.v4-landing.lock'
ROUTE = 'https://harmat22.hu/uj-otthonok/'
PREVIEW = ROOT / 'outputs/2026-11-09-v4-lead-preview'
VIDEO = ROOT / 'outputs/2026-11-01-main-ad/v4/landscape/harmat-60s-v4-hu.mp4'
VIDEO_SHA = 'c4ec5b7e0987d84dd319faebe1cec34fc340f82e2a1cc82deea25933b4f95f8b'


def encoded(value):
    return json.dumps(value, ensure_ascii=True, sort_keys=True, indent=2).encode()


def absent(remote, path):
    try:
        remote.info(path)
    except FileNotFoundError:
        return True
    return False


def bundle():
    pins = json.loads((ROOT / 'wp-mu-plugins' / ASSET / 'manifest.json').read_text())
    blobs = {ASSET + '/' + name: (ROOT / 'wp-mu-plugins' / ASSET / name).read_bytes()
             for name in (*pins, 'manifest.json')}
    require(all(sha(blobs[ASSET + '/' + n]) == p for n, p in pins.items()), 'Asset pins changed')
    blobs[MAIN] = (ROOT / 'wp-mu-plugins' / MAIN).read_bytes()
    for name, blob in blobs.items():
        if name.endswith('.php'):
            require(not re.search(rb'\b(?:wp_mail|update_option|update_post_meta|wp_insert_post|register_rest_route|rest_do_request)\s*\(', blob), 'Presentation must not mutate or dispatch')
    return blobs


def protected(remote):
    result = A['state'](remote)
    result['sources'][DIR + '/harmat-unified-offer-modal.php'] = sha(remote.read(DIR + '/harmat-unified-offer-modal.php'))
    for path in list(result['sources']):
        if path == DIR + '/' + MAIN or path.startswith(DIR + '/' + ASSET + '/'):
            result['sources'].pop(path)
    return result


def same_protected(before, after):
    # Existing public GETs and real visitors advance this analytics counter.
    # Retain its raw hash in evidence, but never roll it back or suppress visits.
    def business(snapshot):
        copy = json.loads(json.dumps(snapshot))
        copy['options'].pop('harmat_sales_visit_stats', None)
        return copy
    return business(before) == business(after)


def prove_unchanged(before, after):
    return A['proof'](before, after, True)


def check_landing(blob, baseline):
    root = html.fromstring(blob.decode())
    old = html.fromstring(baseline.decode())
    for ident in ('h22-offer-modal', 'harmat-unified-offer-gate', 'harmat-unified-offer-modal-css'):
        a, b = root.xpath('//*[@id=$id]', id=ident), old.xpath('//*[@id=$id]', id=ident)
        def native_tree(node):
            values = []
            for child in node.iter():
                attrs = dict(child.attrib)
                if attrs.get('name') == '_harmat_offer_nonce': attrs.pop('value', None)
                text = (child.text or '').strip() if child.tag in ('script', 'style') else ' '.join((child.text or '').split())
                values.append((child.tag, sorted(attrs.items()), text))
            return values
        require(len(a) == len(b) == 1 and native_tree(a[0]) == native_tree(b[0]), 'Original modal differs: ' + ident)
    require(len(root.xpath('//*[@class="hvl-page"]//form')) == 0, 'Landing must not add a form')
    require(len(root.xpath('//*[@class="hvl-page"]')) == 1, 'Missing isolated landing')
    require(len(root.xpath('//*[contains(concat(" ",normalize-space(@class)," ")," unit-card ")]')) == 3, 'Refresh selected availability before release')
    require('{{' not in blob.decode() and '仅预览' not in blob.decode(), 'Preview placeholder remains')
    require(b'form-action' not in blob and b'harmatOfferAttributionV1' in blob, 'Production consent/attribution missing')
    return {'native_modal_exact': True, 'native_form_count': len(root.xpath('//form')), 'sample_available_units': 3, 'no_submission': True}


def save_local(name, value):
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / name).write_bytes(encoded(value))


def preflight():
    blobs = bundle()
    remote = H.Remote()
    locked = False
    try:
        remote.directory(H.BACKUPS)
        remote.mkdir(LOCK); locked = True
        for path in (DIR + '/' + MAIN, DIR + '/' + ASSET, MEDIA):
            require(absent(remote, path), 'New target already exists; never overwrite')
        require(remote.run(H.WP + ' --skip-plugins --skip-themes eval ' + shlex.quote("echo get_page_by_path('uj-otthonok') ? 'EXISTS' : 'ABSENT';")) == 'ABSENT', 'Existing page owns route')
        pages = A['pages']()
        before = protected(remote)
        folder = PREFIX + uuid.uuid4().hex
        remote.mkdir(folder)
        remote.mkdir(folder + '/bundle')
        remote.mkdir(folder + '/bundle/' + ASSET)
        for name, blob in blobs.items():
            remote.write_new(folder + '/bundle/' + name, blob)
            if name.endswith('.php'): remote.lint(folder + '/bundle/' + name)
        originals = {}
        for name in ('harmat-unified-offer-modal.php', 'zz-harmat-messenger-public-inventory.php', 'hm-legal-cookie-compliance.php', 'zz-harmat-confirmed-lead-tracking.php'):
            blob = remote.read(DIR + '/' + name)
            remote.write_new(folder + '/' + name, blob)
            originals[name] = sha(blob)
        for i, (route, blob) in enumerate(pages.items()):
            remote.write_new(folder + '/page-' + str(i) + '.html', blob)
        remote.write_new(folder + '/' + A['N'].D.CHECKER, (HERE / A['N'].D.CHECKER).read_bytes())
        native = A['N'].D.validate_native(remote, folder)
        code = "$_SERVER['HTTPS']='on';$_SERVER['HTTP_HOST']='harmat22.hu';$_SERVER['SERVER_PORT']=443;$_SERVER['REQUEST_URI']='/uj-otthonok/';$_SERVER['REQUEST_METHOD']='GET';register_post_type('property',array('public'=>true,'rewrite'=>array('slug'=>'property')));require " + json.dumps(folder + '/bundle/' + ASSET + '/template.php') + ';'
        rendered = remote.run(H.WP + ' --skip-plugins --skip-themes eval ' + shlex.quote(code)).encode()
        OUT.mkdir(parents=True, exist_ok=True)
        (OUT / 'candidate-render.html').write_bytes(rendered)
        (OUT / 'native-reference.html').write_bytes(pages['/lakaskereso/'])
        # The preview reuses exact native UI code; network submission is blocked by CSP locally.
        contract = check_landing(rendered, pages['/lakaskereso/'])
        remote.write_new(folder + '/candidate-render.html', rendered)
        after = protected(remote)
        save_local('preflight-before.json', before)
        save_local('preflight-after.json', after)
        require(same_protected(before, after), 'Protected state changed during preflight')
        manifest = {'backup': folder, 'files': {n: sha(b) for n, b in blobs.items()},
                    'before': before, 'originals': originals, 'native': native, 'contract': contract,
                    'routes': list(pages), 'pages': {k: sha(v) for k, v in pages.items()}, 'render_sha256': sha(rendered)}
        remote.write_new(folder + '/manifest.json', encoded(manifest))
        save_local('backup.json', manifest)
        (OUT / 'candidate-render.html').write_bytes(rendered)
        print(json.dumps({'status': 'PRIVATE_BACKUP_PREFLIGHT_PASS', 'backup': folder, 'native': native, 'contract': contract}), flush=True)
    finally:
        if locked: remote.sftp.rmdir(LOCK)
        remote.close()


def saved(remote, folder):
    require(re.fullmatch(re.escape(PREFIX) + '[a-f0-9]{32}', folder), 'Exact backup path required')
    remote.directory(folder, private=True)
    manifest = json.loads(remote.read(folder + '/manifest.json', private=True))
    require(manifest['backup'] == folder, 'Backup identity changed')
    pages = {route: remote.read(folder + '/page-' + str(i) + '.html', private=True) for i, route in enumerate(manifest['routes'])}
    require({k: sha(v) for k, v in pages.items()} == manifest['pages'], 'Page baseline changed')
    return manifest, pages


def remove_owned(remote, manifest):
    target = DIR + '/' + MAIN
    if not absent(remote, target):
        require(sha(remote.read(target)) == manifest['files'][MAIN], 'Foreign controller retained')
        remote.sftp.remove(target)
    # Disable the entry first; keep additive media/assets for safe resumability.
    remote.purge()


def operate(folder, mode):
    blobs = bundle()
    remote = H.Remote(); locked = False; activated = False
    try:
        manifest, baseline = saved(remote, folder)
        require(manifest['files'] == {n: sha(b) for n, b in blobs.items()}, 'Candidate drift since tested preflight')
        if mode != 'verify': remote.mkdir(LOCK); locked = True
        before = protected(remote)
        if mode == 'rollback':
            remove_owned(remote, manifest)
        elif mode == 'deploy':
            require(same_protected(before, manifest['before']), 'Protected data changed; refresh backup before deployment')
            for path in (DIR + '/' + MAIN, DIR + '/' + ASSET, MEDIA):
                require(absent(remote, path), 'New target appeared; do not overwrite')
            require(sha(VIDEO.read_bytes()) == VIDEO_SHA, 'Original V4 changed')
            for path in (DIR + '/' + ASSET, MEDIA): remote.mkdir(path)
            for name, blob in blobs.items():
                if name == MAIN: continue
                target = DIR + '/' + name
                remote.write_new(target, blob)
                if name.endswith('.php'): remote.lint(target)
                remote.sftp.chmod(target, 0o644)
            for name in ('harmat-logo.png', 'project-courtyard.jpg', 'project-living-room.jpg', '20261030-a1.jpg'):
                remote.write_new(MEDIA + '/' + name, (PREVIEW / 'assets' / name).read_bytes())
                remote.sftp.chmod(MEDIA + '/' + name, 0o644)
            remote.write_new(MEDIA + '/harmat-60s-v4-hu.mp4', VIDEO.read_bytes())
            remote.sftp.chmod(MEDIA + '/harmat-60s-v4-hu.mp4', 0o644)
            remote.sftp.chmod(MEDIA, 0o755); remote.sftp.chmod(DIR + '/' + ASSET, 0o755)
            stage = DIR + '/.' + MAIN + '.' + uuid.uuid4().hex
            remote.write_new(stage, blobs[MAIN]); remote.lint(stage)
            remote.sftp.chmod(stage, 0o644)
            require(absent(remote, DIR + '/' + MAIN), 'Concurrent controller appeared')
            remote.sftp.rename(stage, DIR + '/' + MAIN); activated = True
            remote.purge()
        pages = A['pages']()
        public = prove_unchanged(baseline, pages)
        if mode != 'rollback':
            landing, mime = A['Q'].get(ROUTE)
            require('text/html' in mime, 'Landing HTTP failed')
            contract = check_landing(landing, pages['/lakaskereso/'])
            for name, blob in blobs.items(): require(sha(remote.read(DIR + '/' + name)) == sha(blob), 'Live asset mismatch')
            media_hash = remote.run(H.PHP + ' -r ' + shlex.quote("echo hash_file('sha256',$argv[1]);") + ' -- ' + shlex.quote(MEDIA + '/harmat-60s-v4-hu.mp4'))
            require(media_hash == VIDEO_SHA, 'Original V4 media hash differs')
            (OUT / 'live.html').write_bytes(landing)
        else: contract = {'entry_removed': True}
        native = A['N'].D.validate_native(remote, folder)
        after = protected(remote)
        require(same_protected(before, after), 'Protected source/data changed; never restore business data')
        result = {'status': mode.upper() + '_VERIFIED', 'backup': folder, 'public': public,
                  'landing': contract, 'native': native, 'budgets_changed': False, 'protected_data_unchanged': True,
                  'normal_visit_counter_excluded_only': 'harmat_sales_visit_stats',
                  'original_video_sha256': VIDEO_SHA if mode != 'rollback' else None,
                  'visit_counter_hash_before': before['options'].get('harmat_sales_visit_stats'),
                  'visit_counter_hash_after': after['options'].get('harmat_sales_visit_stats')}
        save_local(mode + '.json', result)
        remote.write_new(folder + '/' + mode + '-' + uuid.uuid4().hex + '.json', encoded(result))
        print(json.dumps(result), flush=True)
    except Exception:
        if activated:
            remove_owned(remote, manifest)
            print(json.dumps({'status': 'OWN_LANDING_ENTRY_DISABLED', 'backup': folder}), flush=True)
        raise
    finally:
        if locked: remote.sftp.rmdir(LOCK)
        remote.close()


def canonical_fix(folder):
    blobs = bundle(); remote = H.Remote(); locked = False; replaced = []
    try:
        manifest, _ = saved(remote, folder)
        remote.mkdir(LOCK); locked = True
        before = protected(remote)
        changed = {n for n in blobs if sha(blobs[n]) != manifest['files'][n]}
        allowed = (ASSET + '/template.php', ASSET + '/landing.css', ASSET + '/manifest.json')
        require(changed and changed.issubset(set(allowed)) and ASSET + '/manifest.json' in changed, 'Only own canonical/heading presentation manifest may change')
        names = [n for n in allowed if n in changed]
        checkpoint = folder + '/canonical-' + uuid.uuid4().hex
        remote.mkdir(checkpoint)
        for name in names:
            raw = remote.read(DIR + '/' + name)
            require(sha(raw) == manifest['files'][name], 'Concurrent landing edit retained')
            remote.write_new(checkpoint + '/' + name.split('/')[-1], raw)
            if name.endswith('.php'):
                expected = raw.replace(b'<link rel="canonical"', b"<?php if (!defined('WPSEO_VERSION')) : ?>\n<link rel=\"canonical\"", 1)
                expected = expected.replace(b"home_url('/uj-otthonok/')); ?>\">", b"home_url('/uj-otthonok/')); ?>\">\n<?php endif; ?>", 1)
                require(blobs[name] == expected, 'Canonical-only delta required')
            if name.endswith('landing.css'):
                delta = b'.hvl-page h1,.hvl-page h2,.hvl-page h3{text-transform:none;font-style:normal;color:var(--ink);letter-spacing:0}.hvl-page .hero-inner h1{color:#fff}.hvl-page .dialog-heading h2{font-family:Arial,sans-serif}\n'
                header_delta = b'.hvl-page .landing-header .header-offer{width:auto;min-width:0;flex:0 0 auto}\n'
                font_delta = b'.hvl-page h3{font-family:Arial,sans-serif}\n'
                require(blobs[name] in (raw + delta, raw + header_delta, raw + font_delta), 'Scoped heading/header-only delta required')
            meta = A['Q'].metadata(remote, DIR + '/' + name, raw)
            remote.write_new(checkpoint + '/candidate-' + name.split('/')[-1], blobs[name])
            remote.lint(checkpoint + '/candidate-' + name.split('/')[-1])
            A['Q'].replace(remote, DIR + '/' + name, raw, blobs[name], meta)
            replaced.append((name, raw, meta))
        remote.purge()
        body, _ = A['Q'].get(ROUTE)
        page = html.fromstring(body)
        require(page.xpath('//link[@rel="canonical"]/@href') == [ROUTE], 'Exactly one correct canonical required')
        require(same_protected(before, protected(remote)), 'Protected state changed')
        old_manifest = remote.read(folder + '/manifest.json', private=True)
        remote.write_new(checkpoint + '/release-manifest.json', old_manifest)
        manifest['files'] = {n: sha(b) for n,b in blobs.items()}
        manifest['canonical_checkpoint'] = checkpoint
        stage = folder + '/manifest-' + uuid.uuid4().hex + '.json'
        remote.write_new(stage, encoded(manifest))
        remote.sftp.posix_rename(stage, folder + '/manifest.json')
        save_local('backup.json', manifest)
        (OUT / 'live.html').write_bytes(body)
        print(json.dumps({'status': 'OWN_SCOPED_REFINEMENT_VERIFIED', 'checkpoint': checkpoint}), flush=True)
    except Exception:
        for name, raw, meta in reversed(replaced):
            A['Q'].replace(remote, DIR + '/' + name, blobs[name], raw, meta)
        if replaced: remote.purge()
        raise
    finally:
        if locked: remote.sftp.rmdir(LOCK)
        remote.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group(required=True)
    modes.add_argument('--preflight', action='store_true')
    modes.add_argument('--canonical-fix')
    for mode in ('deploy', 'verify', 'rollback'): modes.add_argument('--' + mode)
    args = parser.parse_args()
    if args.preflight: preflight()
    elif args.canonical_fix: canonical_fix(args.canonical_fix)
    else:
        mode = next(m for m in ('deploy', 'verify', 'rollback') if getattr(args, m))
        operate(getattr(args, mode), mode)
