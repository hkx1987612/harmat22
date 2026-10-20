"""Preserve detailed facilities before the old 3D viewer. Offline by default."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import subprocess
import time
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('neighborhood_current', HERE/'2026-10-20-deploy-home-loop.py')
L = importlib.util.module_from_spec(spec)
spec.loader.exec_module(L)
H, D, Q = L.H, L.D, L.Q
C = D.module('neighborhood_exact_markup', '2026-10-18-deploy-construction-updates.py')
require, sha = L.require, L.sha
DIRECTORY = H.LIVE + '/wp-content/plugins/harmat22-map-redesign'
JS, PHP = 'assets/map-redesign.js', 'harmat22-map-redesign.php'
PREFIX = H.BACKUPS + '/neighborhood-preserve-'
URL = 'https://harmat22.hu/harmat-lakopark-kornyeke/'
OLD_JS = 'ed225cd27cda3498b1329e84e788c53efe00435df019e4ccabf8c4a58eb81aa7'
OLD_PHP = '7ba09d026b7ab16c493c78302cbd3b8a373080efa5e7505ebe024f377a09f521'
NEW_PHP = 'fb3bdf09c766880a847c6bd55b150ff229c45374b16f9054aaac73bb18e61330'
FIXTURE = '2026-10-20-test-neighborhood-preservation.cjs'


def bundle():
    old = {n: subprocess.check_output(['git', 'show', 'fb8e536:wp-plugins/harmat22-map-redesign/'+n], cwd=ROOT) for n in (JS, PHP)}
    new = {n: (ROOT/'wp-plugins/harmat22-map-redesign'/n).read_bytes().replace(b'\r', b'') for n in old}
    expected = old[JS].replace(b'    var target = findTargetSection();',
        b'    var neighborhood = document.querySelector("main .harmat-neighborhood-page");\n    var target = neighborhood || findTargetSection();', 1)
    expected = expected.replace(b'    target.replaceWith(replacement);',
        b'    if (target === neighborhood) {\n      target.insertAdjacentElement("afterend", replacement);\n    } else {\n      target.replaceWith(replacement);\n    }', 1)
    require(sha(old[JS]) == OLD_JS and new[JS] == expected, 'Only detailed-section preservation may change')
    require(new[PHP] == old[PHP].replace(b' * Version: 2.2', b' * Version: 2.2.1', 1)
        .replace(b"const VERSION = '2.2';", b"const VERSION = '2.2.1';", 1), 'Only plugin cache version may change')
    edits, _, protected, _, _ = L.release()
    protected = dict(protected)
    for path, (_, current) in edits.items(): protected[path.removeprefix(D.DIR+'/')] = current
    protected['zz-harmat-construction-updates.php'] = subprocess.check_output(
        ['git', 'show', 'fb8e536:wp-mu-plugins/zz-harmat-construction-updates.php'], cwd=ROOT)
    helpers = {D.CHECKER: (HERE/D.CHECKER).read_bytes().replace(b'\r\n', b'\n')}
    pins = {'old_js': OLD_JS, 'new_js': sha(new[JS]), 'old_php': OLD_PHP, 'new_php': NEW_PHP,
        'protected': {n: sha(b) for n, b in protected.items()}, 'helpers': {n: sha(b) for n, b in helpers.items()},
        'fixture': sha((HERE/FIXTURE).read_bytes().replace(b'\r\n', b'\n')), 'snapshot_policy': L.P.CACHE_PREDICATE}
    return old, new, protected, helpers, sha(Q.P.encoded(pins))


def state(r, protected):
    return L.P.state(r, protected)


def page(route):
    time.sleep(1.5)
    print('BOUNDED_PAGE_CHECK '+route, flush=True)
    return Q.get('https://harmat22.hu'+route)


def content_fragment(text, route):
    # The native property template has #content, not the page template's #main.
    identity = 'content' if route == '/property/a1-f-l1/' else 'main'
    return C.owned(text, 'id', identity)


def atomic(r, path, old, new, meta):
    stage = path.rsplit('/', 1)[0] + '/.' + Path(path).name + '.' + uuid.uuid4().hex + '.tmp'
    try:
        r.directory(path.rsplit('/', 1)[0])
        r.write_new(stage, new)
        if path.endswith('.php'): r.lint(stage)
        r.stage_metadata(stage, meta)
        require(r.read(stage) == new and Q.metadata(r, path, old) == tuple(meta), 'Activation ownership changed')
        r.sftp.posix_rename(stage, path)
        Q.metadata(r, path, new)
        if path.endswith('.php'): r.lint(path)
    finally:
        if not D.absent(r, stage): r.remove_owned(stage, new)


def public(baselines, candidate):
    for route, baseline in baselines.items():
        body, mime = page(route)
        require('text/html' in mime, 'Page MIME changed')
        text, before = body.decode(), baseline.decode()
        a, b = D.Page(text), D.Page(before)
        require(a.form == b.form and a.canonical == b.canonical and a.seo == b.seo, 'Native form/SEO changed')
        if route == '/': D.assert_home(text, before)
        require(content_fragment(text, route) == content_fragment(before, route), 'Server-rendered page content changed: '+route)
        if route == '/harmat-lakopark-kornyeke/':
            require('id="harmat-neighborhood-title"' in text and text.count('class="harmat-neighborhood-distance-card') == 6,
                    'Detailed facilities missing from server')
            require('/map-redesign.js?ver=2.2.1' in text, 'Browser cache version not advanced')
    data, mime = Q.get('https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/'+JS+'?ver=2.2.1')
    require(data == candidate[JS] and 'javascript' in mime, 'Public runtime differs')
    return {'routes': len(baselines), 'server_main_exact': True, 'native_form_and_seo_exact': True,
            'facility_cards': 6, 'runtime_exact': True, 'submission': False}


def operate(data, backup=None, reverse=False):
    canonical, local, protected, helpers, digest = data
    fresh = backup is None
    backup = backup or PREFIX + uuid.uuid4().hex
    require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}', backup), 'Exact private backup required')
    r = H.Remote(); locked = attempted = False
    try:
        r.directory(H.BACKUPS); r.directory(DIRECTORY); r.directory(DIRECTORY+'/assets')
        r.mkdir(D.LOCK); locked = True
        if fresh:
            originals = {n: r.read(DIRECTORY+'/'+n) for n in (JS, PHP)}
            require(sha(originals[JS]) == OLD_JS and sha(originals[PHP]) == OLD_PHP, 'Current sources drifted')
            require(all(originals[n].replace(b'\r', b'') == canonical[n] for n in originals), 'Canonical baseline differs')
            candidate = {JS: local[JS], PHP: originals[PHP].replace(b' * Version: 2.2', b' * Version: 2.2.1', 1)
                .replace(b"const VERSION = '2.2';", b"const VERSION = '2.2.1';", 1)}
            require(sha(candidate[PHP]) == NEW_PHP, 'Raw PHP candidate differs')
            meta = {n: Q.metadata(r, DIRECTORY+'/'+n, originals[n]) for n in originals}
            before = state(r, protected)
            routes = ('/', '/harmat-lakopark-kornyeke/', '/elerhetosegeink/', '/lakaskereso/',
                '/property/a1-f-l1/', '/virtualis-lakasvalaszto/', '/virtualis-lakasvalaszto-a1-epulet/')
            baselines = {route: page(route)[0] for route in routes}
            for route, blob in baselines.items(): content_fragment(blob.decode(), route)
            r.mkdir(backup)
            for n in originals:
                r.write_new(backup+'/old-'+Path(n).name, originals[n])
                r.write_new(backup+'/new-'+Path(n).name, candidate[n])
            r.lint(backup+'/old-'+PHP); r.lint(backup+'/new-'+PHP)
            for n, blob in helpers.items(): r.write_new(backup+'/'+n, blob); r.lint(backup+'/'+n)
            for i, (route, blob) in enumerate(baselines.items()): r.write_new(backup+'/page-'+str(i)+'.html', blob)
            receipt = {'aggregate': digest, 'metadata': meta, 'before': before,
                'pages': {route: {'file': 'page-'+str(i)+'.html', 'sha256': sha(blob)} for i, (route, blob) in enumerate(baselines.items())}}
            D.write_json(r, backup+'/manifest.json', receipt)
            native = D.validate_native(r, backup)
            require(state(r, protected) == before, 'Protected state changed before activation')
            print(json.dumps({'status': 'PRIVATE_BACKUP_READY', 'backup': backup, 'aggregate': digest, 'native': native}), flush=True)
            attempted = True
            for n in (JS, PHP): atomic(r, DIRECTORY+'/'+n, originals[n], candidate[n], meta[n])
            r.purge()
        else:
            r.directory(backup, private=True)
            receipt = json.loads(r.read(backup+'/manifest.json', private=True))
            require(receipt['aggregate'] == digest, 'Saved bundle differs')
            originals = {n: r.read(backup+'/old-'+Path(n).name, private=True) for n in (JS, PHP)}
            candidate = {n: r.read(backup+'/new-'+Path(n).name, private=True) for n in (JS, PHP)}
            require(sha(originals[JS]) == OLD_JS and sha(originals[PHP]) == OLD_PHP
                and candidate[JS] == local[JS] and sha(candidate[PHP]) == NEW_PHP, 'Backup identity differs')
            meta = receipt['metadata']
            baselines = {route: r.read(backup+'/'+entry['file'], private=True) for route, entry in receipt['pages'].items()}
            require(all(sha(baselines[route]) == entry['sha256'] for route, entry in receipt['pages'].items()), 'Baseline evidence differs')
            require(D.absent(r, backup+'/release.json') is False, 'Successful original release required')
            for n in candidate: require(Q.metadata(r, DIRECTORY+'/'+n, candidate[n]) == tuple(meta[n]), 'Current source differs')
            before = state(r, protected)
            if reverse:
                attempted = True
                for n in (PHP, JS): atomic(r, DIRECTORY+'/'+n, candidate[n], originals[n], meta[n])
                r.purge()
                require(state(r, protected) == before, 'Protected state changed during source-only recovery')
                print(json.dumps({'status': 'ROLLED_BACK', 'backup': backup, 'database_restore': False}), flush=True)
                return
        proof = public(baselines, candidate)
        proof['native'] = D.validate_native(r, backup)
        require(state(r, protected) == before, 'Protected business/source state changed')
        proof.update({'status': 'RELEASED' if fresh else 'VERIFIED_CURRENT_STATE', 'backup': backup, 'aggregate': digest})
        if fresh: D.write_json(r, backup+'/release.json', proof)
        print(json.dumps(proof), flush=True)
    except Exception:
        if attempted and fresh:
            for n in (PHP, JS):
                current = r.read(DIRECTORY+'/'+n)
                require(current in (originals[n], candidate[n]), 'Foreign source retained; no blind rollback')
                if current == candidate[n]: atomic(r, DIRECTORY+'/'+n, candidate[n], originals[n], meta[n])
            r.purge()
            print('OWNED_SOURCES_RESTORED_NO_DATABASE_ROLLBACK', flush=True)
        raise
    finally:
        if locked: r.sftp.rmdir(D.LOCK)
        r.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--deploy', action='store_true')
    group.add_argument('--verify')
    group.add_argument('--rollback')
    parser.add_argument('--expect')
    args = parser.parse_args(); data = bundle()
    if args.deploy or args.verify or args.rollback:
        require(args.expect == data[-1], 'Explicit reviewed aggregate required')
        operate(data, args.verify or args.rollback, bool(args.rollback))
    else: print(json.dumps({'status': 'OFFLINE_REVIEW_ONLY', 'aggregate': data[-1]}))
