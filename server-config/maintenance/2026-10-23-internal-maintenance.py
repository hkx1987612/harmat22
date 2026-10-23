"""Two legal-copy rows and fixed-path Chinese dictionary; no business writes."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import subprocess
import uuid

from lxml import html

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
BASE = 'bbfd68e'
spec = importlib.util.spec_from_file_location('maintenance_transport', HERE / '2026-10-23-home-showroom.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
H, Q, D = M.H, M.Q, M.D
require, sha = M.require, M.sha
LEGAL = 'hm-legal-cookie-compliance.php'
LANG = 'zz-harmat-internal-language.php'
DICT = 'harmat-internal-language/zh-Hans.json'
OLD_DICT = 'harmat-internal-language/zh-Hans.json'
PREFIX = H.BACKUPS + '/internal-maintenance-fixed-path-'
OLD_LEGAL = 'e193f911b025ce82176a80a7155b2f8ee01145a55a1f660a459d7bb9d3742e91'
OLD_LANG = 'a34c8461f8a65c5b8b9e9b45aa9e73ba92d2d76fd46e2362ae3ef294d8b29c45'
ROUTES = ('/', '/adatvedelmi-tajekoztato/', '/impresszum/', '/cookie-tajekoztato/',
          '/elerhetosegeink/', '/lakaskereso/', '/property/a1-f-l1/',
          '/virtualis-lakasvalaszto/', '/virtualis-lakasvalaszto-a1-epulet/')
PAIRS = (
    ('<tr><td>Weboldal-fejlesztő és karbantartó, például Szabados Attila EV. / 21stcenturywebsites.hu</td><td>Hibajavítás, biztonsági és működési támogatás</td><td>Csak a feladat elvégzéséhez szükséges adatok</td></tr>',
     '<tr><td>A Cooperation Power Kft. belső, feljogosított munkatársai</td><td>Weboldal-karbantartás, hibajavítás, biztonsági és működési támogatás</td><td>Csak a karbantartási feladat elvégzéséhez szükséges adatok</td></tr>'),
    ('<tr><th>Weboldal-karbantartás</th><td>Weboldal-fejlesztő és karbantartó szolgáltató, például Szabados Attila EV. / 21stcenturywebsites.hu - hibajavítás, biztonsági és működési támogatás.</td></tr>',
     '<tr><th>Weboldal-karbantartás</th><td>A weboldal karbantartását a Cooperation Power Kft. belső, feljogosított munkatársai végzik; a kapcsolódó adatokhoz csak a karbantartási feladat elvégzéséhez szükséges mértékben férhetnek hozzá.</td></tr>'),
)
OLD_KEYS = (
    'Weboldal-fejlesztő és karbantartó, például Szabados Attila EV. / 21stcenturywebsites.hu',
    'Weboldal-fejlesztő és karbantartó szolgáltató, például Szabados Attila EV. / 21stcenturywebsites.hu - hibajavítás, biztonsági és működési támogatás.',
)
NEW_KEYS = (
    'A Cooperation Power Kft. belső, feljogosított munkatársai',
    'Weboldal-karbantartás, hibajavítás, biztonsági és működési támogatás',
    'Csak a karbantartási feladat elvégzéséhez szükséges adatok',
    'A weboldal karbantartását a Cooperation Power Kft. belső, feljogosított munkatársai végzik; a kapcsolódó adatokhoz csak a karbantartási feladat elvégzéséhez szükséges mértékben férhetnek hozzá.',
)


def original(name):
    return subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + name], cwd=ROOT)


def local(name):
    return (ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')


def rewrite(blob):
    for old, new in PAIRS:
        require(blob.count(old.encode()) == 1, 'One exact original maintenance row required')
        blob = blob.replace(old.encode(), new.encode(), 1)
    return blob


def dictionary():
    data = json.loads(original(OLD_DICT))
    legal = json.loads(local('harmat-internal-language/legal.zh-Hans.json'))
    for key in OLD_KEYS:
        require(key in data and key not in legal, 'Old maintenance dictionary ownership changed')
        del data[key]
    require(all(key not in data and key in legal for key in NEW_KEYS), 'New exact translations required')
    data.update({key: legal[key] for key in NEW_KEYS})
    return (json.dumps(data, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()


def language(blob):
    return (blob.replace(b'Version: 1.1.0', b'Version: 1.1.1', 1)
            .replace(sha(original(OLD_DICT)).encode(), sha(dictionary()).encode(), 1))


def bundle():
    require(local(LEGAL) == rewrite(original(LEGAL)), 'Legal edits exceed two approved rows')
    require(local(LANG) == language(original(LANG)), 'Language code changes exceed dictionary selection')
    require(local(DICT) == dictionary(), 'Fixed-path dictionary differs')
    bootstrap = local('harmat-internal-language/bootstrap.js')
    require(bootstrap == original('harmat-internal-language/bootstrap.js')
            and b"url.pathname.endsWith('/harmat-internal-language/zh-Hans.json')" in bootstrap,
            'Existing exact-path bootstrap contract must remain unchanged')
    old_legal = json.loads(original('harmat-internal-language/legal.zh-Hans.json'))
    next_legal = json.loads(local('harmat-internal-language/legal.zh-Hans.json'))
    for key in OLD_KEYS:
        del old_legal[key]
    old_legal.update({key: next_legal[key] for key in NEW_KEYS})
    require(old_legal == next_legal, 'Legal source dictionary has unrelated changes')
    pins = {LEGAL: {'old': OLD_LEGAL, 'new_lf': sha(local(LEGAL))},
            LANG: {'old': OLD_LANG, 'new': sha(local(LANG))},
            DICT: {'old': sha(original(OLD_DICT)), 'new': sha(dictionary())}}
    return pins, sha(Q.P.encoded(pins))


def state(remote):
    value = M.P.state(remote, {})
    for name in (LEGAL, LANG, DICT):
        value['sources'].pop(D.DIR + '/' + name, None)
    for path in (Q.TARGET_A, Q.TARGET_N):
        value['sources'][path] = sha(remote.read(path))
    return value


def pages():
    result = {}
    for route in ROUTES:
        blob, kind = Q.get('https://harmat22.hu' + route)
        require('text/html' in kind, 'Public HTML required')
        result[route] = blob
    return result


def prove(before, after, active):
    for route in ROUTES:
        old, new = before[route].decode(), after[route].decode()
        a, b = D.Page(old), D.Page(new)
        require(a.form == b.form and a.form and a.seo == b.seo and a.canonical == b.canonical,
                'Native offer form/SEO/canonical changed: ' + route)
        for label, source in (('runtime.js', 'runtime.js'), ('patterns.js', 'patterns.js'), ('bootstrap.js', 'bootstrap.js')):
            pattern = r'[^"\s]*harmat-internal-language/' + re.escape(source) + r'\?[^"\s]*'
            require(re.findall(pattern, old) == re.findall(pattern, new), 'Translation runtime changed: ' + label)
        selected = sha(dictionary() if active else original(OLD_DICT))[:16]
        require(('zh-Hans.json?v=' + selected) in new and 'zh-Hans-maintenance.json' not in new,
                'Selected fixed-path dictionary hash is not active')
        if route in ('/adatvedelmi-tajekoztato/', '/impresszum/'):
            old_main = html.fromstring(old).xpath('//main')
            new_main = html.fromstring(new).xpath('//main')
            require(len(old_main) == len(new_main) == 1, 'One legal main required')
            expected = html.tostring(old_main[0], encoding='unicode')
            if active:
                for x, y in PAIRS:
                    expected = expected.replace(x, y)
            require(expected == html.tostring(new_main[0], encoding='unicode'), 'Legal content exceeds approved rows')
            require(('21stcenturywebsites.hu' in new_main[0].text_content()) != active, 'Wrong maintenance party')
    return {'routes': len(ROUTES), 'native_form_seo_unchanged': True,
            'legal_delta_two_rows_only': True, 'unchanged_language_runtime': True,
            'submission': False}


def replace(remote, path, old, new, meta):
    if path.endswith('.php'):
        return Q.replace(remote, path, old, new, meta)
    require(path == D.DIR + '/' + DICT and {sha(old), sha(new)} == {sha(original(OLD_DICT)), sha(dictionary())},
            'Only exact owned dictionary update or recovery allowed')
    require(Q.metadata(remote, path, old) == tuple(meta), 'Original dictionary ownership changed')
    stage = path + '.' + uuid.uuid4().hex + '.tmp'
    try:
        remote.write_new(stage, new)
        require(json.loads(remote.read(stage)) == json.loads(new), 'Staged JSON invalid')
        remote.stage_metadata(stage, meta)
        require(Q.metadata(remote, path, old) == tuple(meta), 'Dictionary changed before rename')
        remote.sftp.posix_rename(stage, path)
        require(Q.metadata(remote, path, new) == tuple(meta), 'JSON identity changed')
    finally:
        if not Q.P.absent(remote, stage):
            data = remote.read(stage)
            require(new.startswith(data), 'Foreign stage retained')
            remote.remove_owned(stage, data)


def restore(remote, edits, metadata):
    # Keep the original filename contract; never restore business/database data.
    for name in (LEGAL, LANG, DICT):
        old, new = edits[name]
        path = D.DIR + '/' + name
        if Q.P.absent(remote, path):
            require(old is None, 'Existing source missing')
            continue
        current = remote.read(path)
        require(current in (old, new), 'Foreign source retained')
        if current == old:
            continue
        if old is None:
            remote.remove_owned(path, new)
        else:
            replace(remote, path, new, old, metadata[name])
    remote.purge()


def operate(backup=None, reverse=False):
    pins, digest = bundle()
    fresh = backup is None
    backup = backup or PREFIX + uuid.uuid4().hex
    require(re.fullmatch(re.escape(PREFIX) + '[a-f0-9]{32}', backup), 'Exact backup location required')
    remote = H.Remote()
    locked = attempted = False
    try:
        remote.directory(H.BACKUPS)
        remote.directory(D.DIR)
        remote.directory(D.DIR + '/harmat-internal-language')
        remote.mkdir(D.LOCK)
        locked = True
        if fresh:
            raw = remote.read(D.DIR + '/' + LEGAL)
            require(sha(raw) == OLD_LEGAL and raw.replace(b'\r\n', b'\n') == original(LEGAL), 'Original legal source changed')
            require(sha(remote.read(D.DIR + '/' + LANG)) == OLD_LANG, 'Original language source changed')
            require(sha(remote.read(D.DIR + '/' + OLD_DICT)) == pins_for_old_dictionary(), 'Retained dictionary changed')
            edits = {DICT: (original(OLD_DICT), dictionary()), LANG: (original(LANG), local(LANG)), LEGAL: (raw, rewrite(raw))}
            metadata = {n: Q.metadata(remote, D.DIR + '/' + n, pair[0]) for n, pair in edits.items()}
            before = state(remote)
            baseline = pages()
            remote.mkdir(backup)
            for i, (name, pair) in enumerate(edits.items()):
                for label, blob in zip(('original', 'candidate'), pair):
                    if blob is not None:
                        path = backup + '/' + label + '-' + str(i) + ('.php' if name.endswith('.php') else '.json')
                        remote.write_new(path, blob)
                        if name.endswith('.php'):
                            remote.lint(path)
            checker = (HERE / D.CHECKER).read_bytes().replace(b'\r\n', b'\n')
            remote.write_new(backup + '/' + D.CHECKER, checker)
            remote.lint(backup + '/' + D.CHECKER)
            for i, route in enumerate(ROUTES):
                remote.write_new(backup + '/baseline-' + str(i) + '.html', baseline[route])
            saved = {'pins': pins, 'digest': digest, 'before': before, 'metadata': metadata,
                     'routes': list(ROUTES), 'baseline': {r: sha(b) for r, b in baseline.items()}}
            D.write_json(remote, backup + '/manifest.json', saved)
            filters = D.validate_native(remote, backup)
            require(state(remote) == before, 'Protected state changed during preflight')
            print(json.dumps({'status': 'PRIVATE_BACKUP_READY', 'backup': backup, 'aggregate': digest}), flush=True)
            attempted = True
            for name, (old, new) in edits.items():
                replace(remote, D.DIR + '/' + name, old, new, metadata[name])
            remote.purge()
        else:
            remote.directory(backup, private=True)
            saved = json.loads(remote.read(backup + '/manifest.json', private=True))
            require(saved['pins'] == pins and saved['digest'] == digest and saved['routes'] == list(ROUTES), 'Backup identity changed')
            metadata, before = {name: tuple(value) for name, value in saved['metadata'].items()}, saved['before']
            raw = remote.read(backup + '/original-2.php', private=True)
            require(sha(raw) == OLD_LEGAL, 'Backup original legal source changed')
            edits = {DICT: (original(OLD_DICT), dictionary()), LANG: (original(LANG), local(LANG)), LEGAL: (raw, rewrite(raw))}
            for i, (name, pair) in enumerate(edits.items()):
                for label, blob in zip(('original', 'candidate'), pair):
                    if blob is not None:
                        suffix = '.php' if name.endswith('.php') else '.json'
                        require(remote.read(backup + '/' + label + '-' + str(i) + suffix, private=True) == blob, 'Backup source changed')
            require(remote.read(backup + '/' + D.CHECKER, private=True) == (HERE / D.CHECKER).read_bytes().replace(b'\r\n', b'\n'), 'Checker changed')
            baseline = {r: remote.read(backup + '/baseline-' + str(i) + '.html', private=True) for i, r in enumerate(ROUTES)}
            require({r: sha(b) for r, b in baseline.items()} == saved['baseline'], 'Backup baseline changed')
            if reverse:
                restore(remote, edits, metadata)
        for name, (old, new) in edits.items():
            expected = old if reverse else new
            path = D.DIR + '/' + name
            require(Q.P.absent(remote, path) if expected is None else Q.metadata(remote, path, expected) == tuple(metadata[name]), 'Final source identity failed')
        proof = prove(baseline, pages(), not reverse)
        if not reverse:
            blob, kind = Q.get('https://harmat22.hu/wp-content/mu-plugins/' + DICT + '?v=' + sha(dictionary())[:16])
            require('json' in kind and blob == dictionary(), 'Public dictionary bytes mismatch')
        filters = D.validate_native(remote, backup)
        require(state(remote) == before, 'Protected sources/business state changed; no data restoration allowed')
        result = {'status': 'RELEASED' if fresh else 'ROLLED_BACK_SOURCE_ONLY' if reverse else 'VERIFIED',
                  'backup': backup, 'aggregate': digest, 'proof': proof, 'filters': filters}
        if fresh:
            D.write_json(remote, backup + '/release.json', result)
        print(json.dumps(result), flush=True)
    except BaseException:
        if attempted:
            restore(remote, edits, metadata)
            print(json.dumps({'status': 'OWNED_SOURCE_ONLY_ROLLBACK', 'backup': backup}), flush=True)
        raise
    finally:
        try:
            if locked:
                remote.sftp.rmdir(D.LOCK)
        finally:
            remote.close()


def pins_for_old_dictionary():
    return sha(original(OLD_DICT))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--prepare', action='store_true')
    mode.add_argument('--deploy', action='store_true')
    mode.add_argument('--verify')
    mode.add_argument('--rollback')
    parser.add_argument('--expect')
    args = parser.parse_args()
    if args.prepare:
        (ROOT / 'wp-mu-plugins' / DICT).write_bytes(dictionary())
        print(json.dumps({'dictionary': sha(dictionary())}))
    else:
        pins, digest = bundle()
        if args.deploy or args.verify or args.rollback:
            require(args.expect == digest, 'Reviewed exact aggregate required')
            operate(args.verify or args.rollback, bool(args.rollback))
        else:
            print(json.dumps({'mode': 'OFFLINE_NO_NETWORK', 'pins': pins, 'aggregate': digest}, indent=2))
