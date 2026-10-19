"""Exact existing two-source update. Offline by default; no database restore."""
import argparse
import json
from pathlib import Path
import re
import shlex
import uuid
import importlib.util

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('payment_terms_transport', HERE / '2026-10-18-deploy-public-payment-copy.py')
Q = importlib.util.module_from_spec(spec)
spec.loader.exec_module(Q)
H, D, P = Q.H, Q.D, Q.P
require, sha = Q.require, Q.sha
TARGET_N, TARGET_A = Q.TARGET_N, Q.TARGET_A
PREFIX = H.BACKUPS + '/public-payment-terms-'
BASE = '3723709'
NATIVE = '2026-10-19-native-payment-terms.php'
TEST_N = '2026-10-18-test-public-payment-copy.php'
TEST_A = '2026-10-19-test-assistant-payment-terms.php'
OLD_N = 'cf66252152d173f4cdd8a242eedc25b94f67e80f1286fe35f1046af2bb8a70c0'
OLD_A = 'f141335d73e7d0245bea0c56d0f824c426126d15c8e6061164e4136678c485b7'
NEW_N_LF = 'f3faec27172819a0db9704d50256c767c035d6a71f628cc59c71875a0429b2ad'
NEW_A_LF = '38cba03f6fb668749efa255b489f0b893c5028f58c055fe8a67fc1bded63df83'


def originals():
    historical = Q.git(Q.ASSISTANT, Q.A.BASE)
    raw = b''.join(line if not line.endswith(b'\n') or any(a <= i <= b for a, b in Q.A.LF_ONLY)
                   else line[:-1] + b'\r\n' for i, line in enumerate(historical.splitlines(keepends=True)))
    require(sha(raw) == Q.A.OLD_SHA, 'Historical raw reconstruction failed')
    prior = Q.git(Q.ASSISTANT, Q.BASE)
    for name in ('payment_schedule_answer', 'is_payment_schedule_question'):
        raw = raw.replace(Q.block(raw, name, True), Q.block(prior, name, True).replace(b'\n', b'\r\n'), 1)
    raw = raw.replace(b"const VERSION = '0.4.0'", b"const VERSION = '0.4.2'", 1)
    current = Q.git(Q.ASSISTANT, BASE)
    name = 'is_payment_schedule_question'
    raw = raw.replace(Q.block(raw, name, True), Q.block(current, name, True).replace(b'\n', b'\r\n'), 1)
    raw = raw.replace(b"const VERSION = '0.4.2'", b"const VERSION = '0.4.3'", 1)
    module = Q.git('wp-mu-plugins/' + Q.MAIN, BASE)
    require(sha(raw) == OLD_A and sha(module) == OLD_N, 'Exact production originals required')
    return module, raw


def release():
    old_n, old_a = originals()
    new_n = (ROOT / 'wp-mu-plugins' / Q.MAIN).read_bytes().replace(b'\r\n', b'\n')
    new_a_lf = (ROOT / Q.ASSISTANT).read_bytes().replace(b'\r\n', b'\n')
    require(sha(new_n) == NEW_N_LF and sha(new_a_lf) == NEW_A_LF, 'Reviewed payment sources required')
    restored = new_n.replace(Q.block(new_n, 'harmat_ppc_html'), Q.block(old_n, 'harmat_ppc_html'), 1)
    require(restored.replace(b'Version: 1.0.1', b'Version: 1.0.0', 1) == old_n, 'Module changes outside copy/version refused')
    name = 'payment_schedule_answer'
    new_a = old_a.replace(Q.block(old_a, name, True), Q.block(new_a_lf, name, True).replace(b'\n', b'\r\n'), 1)
    new_a = new_a.replace(b"const VERSION = '0.4.3'", b"const VERSION = '0.4.4'", 1)
    require(new_a.replace(b'\r\n', b'\n') == new_a_lf, 'Assistant changes outside answer/version refused')
    edits = {TARGET_N: (old_n, new_n), TARGET_A: (old_a, new_a)}
    helpers = {name: (HERE / name).read_bytes().replace(b'\r\n', b'\n') for name in (NATIVE, TEST_N, TEST_A, D.CHECKER)}
    pins = {path: {'old': sha(old), 'new': sha(new)} for path, (old, new) in edits.items()}
    # Include this adapter and its shared transports in the review gate.
    dependencies = {p.name: sha(p.read_bytes().replace(b'\r\n', b'\n')) for p in HERE.glob('*.py')
                    if p.name.startswith(('2026-10-18-deploy-', '2026-10-18-update-assistant-payment',
                                          '2026-10-19-update-public-payment-terms'))}
    digest = sha(P.encoded({'sources': pins, 'helpers': {n: sha(b) for n, b in helpers.items()}, 'transport': dependencies}))
    return edits, helpers, pins, digest


def snapshot(r):
    # Q.snapshot hashes media on-server and captures the CURRENT home controller.
    # Neither owned source belongs to the protected comparison.
    value = Q.snapshot(r)
    value['sources'].pop(TARGET_N, None)
    value['sources'].pop(TARGET_A, None)
    return value


def assert_page(text, old, active, optional=False):
    page, original = D.Page(text), D.Page(old)
    require(page.form == original.form and page.form and page.seo == original.seo and page.canonical == original.canonical,
            'Native offer form/SEO contract changed')
    count, old_count = text.count('id="harmat-public-payment-copy"'), old.count('id="harmat-public-payment-copy"')
    require(count == old_count and count in ((0, 1) if optional else (1,)), 'Payment root missing/duplicate/drift')
    if not count:
        require(optional and 'elementor-4214' not in text and 'Foglaló: 10%' not in text, 'Known old payment silently retained')
    else:
        # Inspect only our section. A tax 4% elsewhere is not a discount finding.
        match = re.search(r'<section id="harmat-public-payment-copy"[\s\S]*?</section>', text)
        require(match is not None, 'Owned payment section missing')
        copy = match.group()
        require(all(token in copy for token in ('2026. december 31', '2027. június 30', '25%', '20%', '5%')), 'Payment dates/milestones missing')
        if active:
            require(not re.search(r'(?:8|4)%|Kedvezmény nélkül|无折扣|no discount', copy, re.I), 'Public discount promise retained')
            require(copy.count('优惠请与销售面议') == 3 and copy.count('egyedileg egyeztessen az értékesítéssel') >= 3,
                    'Sales discussion missing')
        else:
            require('8%' in copy and '4%' in copy, 'Original public copy mismatch')
    return {'native_form_unchanged': True, 'native_seo_unchanged': True, 'payment_roots': count}


def native(r, backup):
    proof = []
    for route in Q.SAMPLES:
        texts = []
        for mode in ('original', 'candidate'):
            data = json.loads(r.run(H.PHP + ' ' + shlex.quote(backup + '/' + NATIVE) + ' '
                                   + shlex.quote(backup + '/candidate-module.php') + ' ' + shlex.quote(route) + ' ' + mode))
            expected = 'native-' + sha(route.encode())[:12] + '-' + mode + '.html'
            require(data['file'] == expected and data['route'] == route, 'Native proof identity changed')
            raw = r.read(backup + '/' + expected, private=True)
            require(sha(raw) == data['sha256'], 'Native proof bytes changed')
            texts.append(raw.decode('utf-8'))
        proof.append({'route': route, **assert_page(texts[1], texts[0], True, route in Q.OPTIONAL)})
    return proof


def public(r, baseline, edits, active):
    proof = D.public_proof(r, (), {}, baseline['/'].decode('utf-8'))
    proof['payment_pages'] = {}
    for route in Q.PUBLIC:
        data, mime = Q.get(P.ORIGIN + route)
        require('text/html' in mime, 'Public MIME mismatch')
        proof['payment_pages'][route] = assert_page(data.decode('utf-8'), baseline[route].decode('utf-8'), active, route in Q.OPTIONAL)
    reflection = '''$r=new ReflectionClass('Harmat_Local_Assistant');$o=$r->newInstanceWithoutConstructor();
$a=$r->getMethod('payment_schedule_answer');$t=$r->getMethod('tax_vat_answer');$g=$r->getMethod('is_payment_schedule_question');
$v=array();foreach(array('hu','zh','en') as $l){$v[$l]=$a->invoke($o,$l);}
$tax=array();foreach(array('hu','zh','en') as $l){$tax[$l]=hash('sha256',$t->invoke($o,$l));}
echo wp_json_encode(array('version'=>Harmat_Local_Assistant::VERSION,'answers'=>$v,'tax'=>$tax,
'guard'=>hash('sha256',file_get_contents($r->getFileName()))));'''
    result = json.loads(r.run(H.WP + ' eval ' + shlex.quote(reflection)))
    expected = edits[TARGET_A][1 if active else 0]
    # Static three-answer parser handles the escaped apostrophe in the EN copy.
    require(result['version'] == ('0.4.4' if active else '0.4.3') and result['guard'] == sha(expected), 'Actual assistant source/version mismatch')
    require(result['answers'] == answers(expected), 'Actual assistant payment answers differ')
    require(result['tax'] == baseline_tax(edits[TARGET_A][0]), 'Actual tax guidance changed')
    proof['assistant_pure_no_dispatch'] = {'version': result['version'], 'payment_languages': 3, 'tax_unchanged': True}
    return proof


def strings(block):
    values = re.findall(rb"\$this->u\('((?:\\.|[^'\\])*)'\)", block)
    require(len(values) == 3, 'Three static language answers required')
    return {lang: json.loads('"' + raw.decode('ascii').replace("\\'", "'") + '"')
            for lang, raw in zip(('hu', 'zh', 'en'), values)}


def answers(source):
    return strings(Q.block(source, 'payment_schedule_answer', True))


def baseline_tax(source):
    return {lang: sha(value.encode()) for lang, value in strings(Q.block(source, 'tax_vat_answer', True)).items()}


def restore(r, edits, meta):
    failures = []
    for path, (old, new) in edits.items():
        try:
            current = r.read(path)
            require(current in (old, new), 'Foreign source retained: ' + path)
            if current == new:
                Q.replace(r, path, new, old, meta[path])
            require(Q.metadata(r, path, old) == tuple(meta[path]), 'Original metadata changed')
        except Exception as error:
            failures.append(str(error))
    r.purge()
    require(not failures, 'Source-only recovery incomplete: ' + '; '.join(failures))


def saved_release(r, backup, edits, helpers, pins, digest):
    r.directory(backup, private=True)
    saved = json.loads(r.read(backup + '/manifest.json', private=True))
    require(saved['pins'] == pins and saved['digest'] == digest, 'Saved release pins differ')
    for path, label in ((TARGET_N, 'module'), (TARGET_A, 'assistant')):
        for state, blob in zip(('original', 'candidate'), edits[path]):
            require(r.read(backup + '/' + state + '-' + label + '.php', private=True) == blob, 'Saved source changed')
    for name, blob in helpers.items():
        require(r.read(backup + '/' + name, private=True) == blob, 'Saved helper changed')
    require(saved['routes'] == list(('/',) + Q.PUBLIC), 'Saved routes changed')
    baseline = {p: r.read(backup + '/baseline-' + str(i) + '.html', private=True) for i, p in enumerate(saved['routes'])}
    require({p: sha(b) for p, b in baseline.items()} == saved['baseline_pins'], 'Saved baseline changed')
    return saved, baseline


def operate(edits, helpers, pins, digest, backup=None, reverse=False):
    fresh = backup is None
    backup = backup or PREFIX + uuid.uuid4().hex
    require(re.fullmatch(re.escape(PREFIX) + r'[a-f0-9]{32}', backup), 'Exact private backup required')
    r = H.Remote()
    locked = attempted = False
    try:
        r.directory(H.BACKUPS); r.directory(P.DIR)
        r.mkdir(D.LOCK); locked = True
        if fresh:
            meta = {p: Q.metadata(r, p, old) for p, (old, _) in edits.items()}
            before = snapshot(r)
            baseline = {route: Q.get(P.ORIGIN + route)[0] for route in ('/',) + Q.PUBLIC}
            r.mkdir(backup)
            for name, blob in helpers.items():
                r.write_new(backup + '/' + name, blob); r.lint(backup + '/' + name)
            for path, label in ((TARGET_N, 'module'), (TARGET_A, 'assistant')):
                for state, blob in zip(('original', 'candidate'), edits[path]):
                    r.write_new(backup + '/' + state + '-' + label + '.php', blob)
                    r.lint(backup + '/' + state + '-' + label + '.php')
            for i, route in enumerate(baseline):
                r.write_new(backup + '/baseline-' + str(i) + '.html', baseline[route])
            saved = {'pins': pins, 'digest': digest, 'metadata': meta, 'before': before,
                     'routes': list(baseline), 'baseline_pins': {p: sha(b) for p, b in baseline.items()}}
            D.write_json(r, backup + '/manifest.json', saved)
            print(json.dumps({'status': 'PRIVATE_BACKUP_READY', 'backup': backup, 'aggregate': digest}), flush=True)
            for test, label in ((TEST_N, 'module'), (TEST_A, 'assistant')):
                pure = json.loads(r.run(H.PHP + ' ' + shlex.quote(backup + '/' + test) + ' ' + shlex.quote(backup + '/candidate-' + label + '.php')))
                require(pure.get('status') == 'PASS', 'Isolated candidate tests failed')
            rendered = native(r, backup)
            filters = D.validate_native(r, backup)
            require(snapshot(r) == before, 'Protected drift during preflight; no activation')
            # Check BOTH originals again before activating either source.
            for path, (old, _) in edits.items():
                require(Q.metadata(r, path, old) == tuple(meta[path]), 'Original changed before activation')
            D.write_json(r, backup + '/preflight.json', {'rendered': rendered, 'filters': filters})
            attempted = True
            for path, (old, new) in edits.items():
                Q.replace(r, path, old, new, meta[path])
            r.purge()
        else:
            saved, baseline = saved_release(r, backup, edits, helpers, pins, digest)
            before, meta = saved['before'], saved['metadata']
            if reverse:
                restore(r, edits, meta)
        for path, (old, new) in edits.items():
            require(Q.metadata(r, path, old if reverse else new) == tuple(meta[path]), 'Released metadata changed')
        proof = public(r, baseline, edits, not reverse)
        filters = D.validate_native(r, backup)
        require(snapshot(r) == before, 'Protected state drift; NEVER restore business data')
        if fresh:
            D.write_json(r, backup + '/release.json', {'proof': proof, 'filters': filters})
        print(json.dumps({'status': 'RELEASED' if fresh else 'ROLLED_BACK_SOURCE_ONLY' if reverse else 'VERIFIED',
                          'backup': backup, 'aggregate': digest, 'proof': proof, 'filters': filters}), flush=True)
    except BaseException:
        if attempted:
            restore(r, edits, meta)
            print(json.dumps({'status': 'EXACT_OWNED_SOURCES_ROLLED_BACK', 'backup': backup}), flush=True)
        raise
    finally:
        try:
            if locked: r.sftp.rmdir(D.LOCK)
        finally:
            r.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--deploy', action='store_true')
    mode.add_argument('--verify', metavar='PRIVATE_BACKUP')
    mode.add_argument('--rollback', metavar='PRIVATE_BACKUP')
    parser.add_argument('--expect')
    args = parser.parse_args()
    edits, helpers, pins, digest = release()
    if not any((args.deploy, args.verify, args.rollback)):
        print(json.dumps({'mode': 'OFFLINE_NO_NETWORK', 'aggregate': digest, 'pins': pins}, indent=2)); return
    require(args.expect == digest, 'Exact reviewed aggregate required before connecting')
    operate(edits, helpers, pins, digest, args.verify or args.rollback, bool(args.rollback))


if __name__ == '__main__': main()
