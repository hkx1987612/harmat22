"""Two-file property disclaimer migration. Default is offline; no form dispatch."""
import argparse
import importlib.util
import json
from html.parser import HTMLParser
from pathlib import Path
import re
import shlex
import subprocess
import uuid

ROOT = Path(__file__).resolve().parents[2]
HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('disclaimer_existing_safety', HERE / '2026-10-17-refresh-language-header.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
D = M.D
H = D.H
DIR = D.DIR
BASE = 'd1d483316a4c05bccc1ed2d4e181f451b76920d4'
LEGACY = 'harmat-migrated-snippets.php'
MAIN = 'zz-harmat-property-disclaimer.php'
FILES = (LEGACY, MAIN)
TEST = '2026-10-18-test-property-disclaimer-scope.php'
NATIVE = '2026-10-18-native-property-disclaimer.php'
PREFIX = H.BACKUPS + '/property-disclaimer-'
LOCK = H.BACKUPS + '/.property-disclaimer.lock'
SLUGS = ('a1-f-l1', 'a1-1-l2', 'a3-4-l5')
ROUTES = ('/', '/harmat-lakopark/', '/magunkrol/', '/lakaskereso/',
          *('/property/' + s + '/' for s in SLUGS), '/virtualis-lakasvalaszto/',
          '/virtualis-lakasvalaszto-elso-utem/', '/virtualis-lakasvalaszto-a2-epulet/',
          '/galeria/', '/elerhetosegeink/', '/epitesi-naplo/', '/adatvedelmi-tajekoztato/')
OLD_CALL = b'    addPropertyDisclaimer();\n'
NEW_CALL = b"    if (<?php echo !function_exists('harmat_property_disclaimer_scope') || harmat_property_disclaimer_scope() ? 'true' : 'false'; ?> /* harmat-property-disclaimer-legacy */) addPropertyDisclaimer();\n"
sha = D.sha
require = D.require
absent = D.D.absent


def live_line_endings(data):
    # Four historical LF-only lines were independently read from the exact live
    # preimage. Preserve every existing newline, not just normalized PHP content.
    return b''.join(line if i in (5, 176, 177, 178) else line.replace(b'\n', b'\r\n')
                    for i, line in enumerate(data.replace(b'\r\n', b'\n').splitlines(keepends=True), 1))


def original():
    return live_line_endings(subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + LEGACY], cwd=ROOT))


def bundle():
    blobs = {n: (ROOT / 'wp-mu-plugins' / n).read_bytes().replace(b'\r\n', b'\n') for n in FILES}
    old = original()
    require(sha(old) == 'c63d48b79940f43c2311bb9c0fd85e280d35634bccd2caebcb4c583aacd65517', 'Legacy baseline hash')
    old_lf = old.replace(b'\r\n', b'\n')
    require(old_lf.count(OLD_CALL) == 1 and blobs[LEGACY] == old_lf.replace(OLD_CALL, NEW_CALL, 1), 'Only exact legacy disclaimer call may change')
    blobs[LEGACY] = live_line_endings(blobs[LEGACY])
    for n in (TEST, NATIVE):
        blobs[n] = (HERE / n).read_bytes().replace(b'\r\n', b'\n')
    pins = {n: sha(b) for n, b in blobs.items()}
    digest = sha(json.dumps(pins, sort_keys=True, separators=(',', ':')).encode())
    return blobs, pins, digest


def snapshot(r):
    result = D.snapshot(r)
    result['language'] = {n: sha(r.read(DIR + '/' + n)) for n in D.FILES}
    return result


def expected_snapshot(before, legacy_pin):
    result = json.loads(json.dumps(before))
    result['other_sources']['migratedSnippets'] = legacy_pin
    return result


class Page(HTMLParser):
    def __init__(self, text):
        super().__init__(convert_charrefs=True)
        self.body = False
        self.notes = []
        self.forms = 0
        self.in_form = False
        self.fields = []
        self.switches = 0
        self.canonicals = []
        self.lang = None
        self.feed(text)

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'html':
            self.lang = a.get('lang')
        if tag == 'body':
            self.body = True
        if 'harmat-property-disclaimer' in a.get('class', '').split():
            self.notes.append({'in_body': self.body, 'owned': a.get('data-harmat-property-disclaimer') == '1'})
        if a.get('id') == 'harmat-internal-language':
            self.switches += 1
        if tag == 'link' and a.get('rel') == 'canonical':
            self.canonicals.append(a.get('href'))
        if tag == 'form' and a.get('id') == 'h22-offer-form':
            self.forms += 1
            self.in_form = True
        if self.in_form and tag in ('input', 'select', 'textarea', 'option', 'button'):
            if a.get('name') == '_harmat_offer_nonce':
                a.pop('value', None)
            self.fields.append((tag, sorted(a.items())))

    def handle_endtag(self, tag):
        if tag == 'body':
            self.body = False
        if tag == 'form':
            self.in_form = False

    def signature(self):
        return json.loads(json.dumps([self.lang, self.canonicals, self.forms, self.switches, self.fields]))


def health(before=None, active=False):
    signatures = {}
    for route in ROUTES:
        data, mime = D.D.http('https://harmat22.hu' + route)
        require('text/html' in mime and b'Fatal error:' not in data, 'Public HTML health ' + route)
        page = Page(data.decode('utf-8'))
        require(page.forms == 1 and page.switches == 1 and page.lang == 'hu', 'Native form/language health ' + route)
        if active and route.startswith('/property/'):
            require(page.notes == [{'in_body': True, 'owned': True}], 'Unique in-body disclaimer ' + route)
            require(b'if (false /* harmat-property-disclaimer-legacy */) addPropertyDisclaimer();' in data, 'Legacy renderer not retired ' + route)
        else:
            require(not page.notes, 'Unexpected disclaimer ' + route)
        signatures[route] = page.signature()
    if before is not None:
        require(signatures == before, 'Native field attributes/order/default HU SEO changed')
    return signatures


def replace(r, name, data, expected):
    target = DIR + '/' + name
    tmp = target + '.' + uuid.uuid4().hex + '.tmp'
    r.write_new(tmp, data)
    try:
        r.lint(tmp)
        require(r.read(tmp) == data, 'Staged source identity')
        require(absent(r, target) if expected is None else r.read(target) == expected, 'Foreign target/preimage ' + name)
        if expected is None:
            r.sftp.chmod(tmp, 0o644)
        else:
            info = r.info(target)
            import stat
            r.stage_metadata(tmp, (stat.S_IMODE(info.st_mode), info.st_uid, info.st_gid))
        r.sftp.posix_rename(tmp, target)
    finally:
        if not absent(r, tmp):
            r.remove_owned(tmp, data)


def reverse(r, old, blobs, updated):
    for name in reversed(updated):
        require(r.read(DIR + '/' + name) == blobs[name], 'Refuse foreign source rollback ' + name)
        if name == MAIN:
            r.remove_owned(DIR + '/' + name, blobs[name])
        else:
            replace(r, name, old, blobs[name])
            r.lint(DIR + '/' + name)
    if updated:
        r.purge()


def deploy(blobs, pins, digest, prepare_only=False):
    r = H.Remote()
    backup = PREFIX + uuid.uuid4().hex
    updated = []
    locked = False
    try:
        r.directory(DIR)
        r.directory(H.BACKUPS)
        r.mkdir(LOCK)
        locked = True
        old = r.read(DIR + '/' + LEGACY)
        require(old == original() and absent(r, DIR + '/' + MAIN), 'Live preimage/new module collision')
        before = snapshot(r)
        baseline = health()
        r.mkdir(backup)
        r.write_new(backup + '/original-legacy.php.txt', old)
        for n, data in blobs.items():
            r.write_new(backup + '/' + n, data)
            r.lint(backup + '/' + n)
        D.D.write_json(r, backup + '/manifest.json', {'bundle': digest, 'pins': pins, 'before': before,
            'baseline': baseline, 'prior_legacy_pin': sha(old), 'original_absent': MAIN})
        print(json.dumps({'phase': 'PRIVATE_BACKUP_SAVED', 'backup': backup}), flush=True)
        test = r.run(H.PHP + ' ' + shlex.quote(backup + '/' + TEST) + ' ' + shlex.quote(backup + '/' + MAIN)
            + ' ' + shlex.quote(backup + '/' + LEGACY))
        require(re.fullmatch(r'PASS [1-9][0-9]+ isolated property-disclaimer assertions', test), 'Isolated candidate fixture failed')
        r.write_new(backup + '/scope-proof.txt', test.encode())
        native = M.pure_native(r, backup)
        render = []
        for slug in SLUGS:
            proof = json.loads(r.run(H.PHP + ' ' + shlex.quote(backup + '/' + NATIVE) + ' '
                + shlex.quote(backup + '/' + MAIN) + ' ' + shlex.quote(slug)))
            require(proof.get('status') == 'PASS', 'Full-theme private candidate failed')
            render.append(proof)
            out = ROOT / 'outputs/2026-10-18-property-disclaimer'
            out.mkdir(parents=True, exist_ok=True)
            (out / ('native-' + slug + '.html')).write_bytes(r.read(backup + '/native-' + slug + '.html', private=True))
        require(snapshot(r) == before, 'Protected drift before activation; do not restore data')
        print(json.dumps({'phase': 'CANDIDATE_AND_NATIVE_PREFLIGHT_PASS', 'properties': len(render)}), flush=True)
        if prepare_only:
            D.D.write_json(r, backup + '/preflight-proof.json', {'native': native, 'render': render})
            print(json.dumps({'status': 'PRIVATE_PREFLIGHT_PASS_NO_LIVE_WRITES', 'backup': backup, 'bundle': digest, 'native': native, 'render': render}), flush=True)
            return
        # Legacy guard is behavior-preserving without new module; activate controller last.
        for name in FILES:
            replace(r, name, blobs[name], old if name == LEGACY else None)
            updated.append(name)
            r.lint(DIR + '/' + name)
        r.purge()
        print(json.dumps({'phase': 'ATOMIC_SOURCE_REPLACEMENT_AND_FORMAL_LINT_PASS'}), flush=True)
        health(baseline, active=True)
        for name in FILES:
            require(sha(r.read(DIR + '/' + name)) == pins[name], 'Formal source identity')
        with D.D.T.bounded_shared_requests():
            inventory = D.D.I.public_check(r)
        require(snapshot(r) == expected_snapshot(before, pins[LEGACY]), 'Protected drift after activation; never restore customer data')
        D.D.write_json(r, backup + '/release-proof.json', {'native': native, 'render': render, 'inventory': inventory,
            'routes': len(ROUTES), 'native_fields_and_seo_unchanged': True})
        print(json.dumps({'status': 'PROPERTY_DISCLAIMER_RELEASED', 'backup': backup, 'bundle': digest,
            'routes': len(ROUTES), 'native': native, 'inventory': inventory}), flush=True)
    except BaseException:
        reverse(r, old if updated else None, blobs, updated)
        print(json.dumps({'status': 'OWNED_SOURCE_ROLLED_BACK' if updated else 'STOPPED_BEFORE_ACTIVATION', 'backup': backup}), flush=True)
        raise
    finally:
        if locked:
            r.sftp.rmdir(LOCK)
        r.close()


def saved_backup(backup):
    require(re.fullmatch(re.escape(PREFIX) + r'[a-f0-9]{32}', backup), 'Exact disclaimer backup required')


def verify(backup, rollback=False):
    saved_backup(backup)
    r = H.Remote()
    locked = False
    try:
        manifest = json.loads(r.read(backup + '/manifest.json', private=True))
        blobs = {n: r.read(backup + '/' + n, private=True) for n in FILES}
        for n in FILES:
            require(sha(blobs[n]) == manifest['pins'][n] and r.read(DIR + '/' + n) == blobs[n], 'Owned released source changed ' + n)
            r.lint(DIR + '/' + n)
        if rollback:
            r.mkdir(LOCK)
            locked = True
            before = snapshot(r)
            old = r.read(backup + '/original-legacy.php.txt', private=True)
            require(sha(old) == manifest['prior_legacy_pin'], 'Original backup identity')
            reverse(r, old, blobs, list(FILES))
            health(manifest['baseline'], active=False)
            require(snapshot(r) == expected_snapshot(before, sha(old)), 'Protected drift during rollback')
            status = 'ROLLED_BACK_DISCLAIMER_SOURCE_ONLY'
        else:
            health(manifest['baseline'], active=True)
            checker = backup + '/native-offer-readonly.php.txt'
            require(r.read(checker, private=True) == (HERE / '2026-10-16-native-offer-readonly.php').read_bytes().replace(b'\r\n', b'\n'), 'Pure checker ownership')
            native = json.loads(r.run(H.WP + ' eval-file ' + shlex.quote(checker)))
            spam = json.loads(r.run(H.WP + ' eval ' + shlex.quote(D.D.I.HEALTH.RUNTIME_CHECK)))
            require(native.get('status') == 'PASS' and native.get('checks') == 35
                and spam.get('status') == 'PASS' and spam.get('checks') == 17, 'Final pure native validation failed')
            with D.D.T.bounded_shared_requests():
                D.D.I.public_check(r)
            require(snapshot(r) == expected_snapshot(manifest['before'], manifest['pins'][LEGACY]), 'Protected state drift')
            status = 'VERIFIED_PROPERTY_DISCLAIMER'
        print(json.dumps({'status': status, 'routes': len(ROUTES), 'native_fields_and_seo_unchanged': True}), flush=True)
    finally:
        if locked:
            r.sftp.rmdir(LOCK)
        r.close()


def main():
    p = argparse.ArgumentParser(description=__doc__)
    a = p.add_mutually_exclusive_group()
    a.add_argument('--prepare', action='store_true')
    a.add_argument('--deploy', action='store_true')
    a.add_argument('--verify')
    a.add_argument('--rollback')
    p.add_argument('--expect')
    args = p.parse_args()
    if args.verify or args.rollback:
        verify(args.verify or args.rollback, bool(args.rollback))
        return
    blobs, pins, digest = bundle()
    if args.prepare or args.deploy:
        require(args.expect == digest, 'Exact reviewed bundle required')
        deploy(blobs, pins, digest, args.prepare)
    else:
        print(json.dumps({'mode': 'OFFLINE_NO_WRITES', 'bundle': digest, 'pins': pins}, indent=2))


if __name__ == '__main__':
    main()
