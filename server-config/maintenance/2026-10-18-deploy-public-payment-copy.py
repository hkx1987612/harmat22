"""Two exact source edits, private native preflight, guarded source-only rollback."""
import argparse
import gzip
import importlib.util
import io
import json
from pathlib import Path
import re
import shlex
import subprocess
import urllib.request
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]


def load(name, file):
    spec = importlib.util.spec_from_file_location(name, HERE / file)
    value = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(value)
    return value


P = load('payment_presentation_transport', '2026-10-18-deploy-assistant-presentation.py')
A = load('payment_old_reconstruction', '2026-10-18-update-assistant-payment.py')
D, H = P.D, P.H
require, sha = P.require, P.sha
MAIN = 'zz-harmat-public-payment-copy.php'
ASSISTANT = 'wp-plugins/harmat-local-assistant/harmat-local-assistant.php'
TARGET_A = H.LIVE + '/wp-content/plugins/harmat-local-assistant/harmat-local-assistant.php'
TARGET_N = P.DIR + '/' + MAIN
PREFIX = H.BACKUPS + '/public-payment-copy-'
NATIVE = '2026-10-18-native-public-payment-copy.php'
BASE = '02f1c91'
SAMPLES = ('/property/a1-1-l2/', '/property/a1-4-l5/', '/finanszirozas/')
OPTIONAL = ('/property/a1-4-l5/', '/property/a2-4-l5/', '/property/a3-4-l5/',
            '/property/a4-2-l7/', '/property/a4-4-l5/')
PUBLIC = SAMPLES + ('/property/a2-4-l5/', '/property/a3-4-l5/', '/property/a4-2-l7/',
                    '/property/a4-4-l5/', '/property/a1-f-l1/')


def get(url):
    limit = 2 * 1024 * 1024
    request = urllib.request.Request(url, headers={'User-Agent': 'HarmatOfferSafety/1.0', 'Accept-Encoding': 'gzip'})
    with urllib.request.urlopen(request, timeout=30) as response:
        raw = response.read(limit + 1)
        require(response.status == 200 and response.geturl() == url and len(raw) <= limit, 'Public GET identity/size failed')
        encoding = response.headers.get('Content-Encoding', 'identity').lower()
        require(encoding in ('identity', 'gzip'), 'Unsupported public response encoding')
        data = gzip.GzipFile(fileobj=io.BytesIO(raw)).read(limit + 1) if encoding == 'gzip' else raw
        require(len(data) <= limit, 'Decoded public response size failed')
        return data, response.headers.get('Content-Type', '')


D.get = get


def git(path, revision=BASE):
    return subprocess.check_output(['git', 'show', revision + ':' + path], cwd=ROOT)


def block(data, name, private=False):
    prefix = ('    private function ' if private else 'function ') + name + '('
    marker = prefix.encode()
    require(data.count(marker) == 1, 'Unique bounded method required')
    start = data.index(marker)
    ending = b'\n    }' if private else b'\n}'
    end = data.index(ending, start) + len(ending)
    return data[start:end]


def release():
    historical = git(ASSISTANT, A.BASE)
    raw = b''.join(line if not line.endswith(b'\n') or any(a <= i <= b for a, b in A.LF_ONLY)
                   else line[:-1] + b'\r\n' for i, line in enumerate(historical.splitlines(keepends=True)))
    require(sha(raw) == A.OLD_SHA, 'Historical raw reconstruction failed')
    prior = git(ASSISTANT)
    for name in ('payment_schedule_answer', 'is_payment_schedule_question'):
        raw = raw.replace(block(raw, name, True), block(prior, name, True).replace(b'\n', b'\r\n'), 1)
    raw = raw.replace(b"const VERSION = '0.4.0'", b"const VERSION = '0.4.2'", 1)
    require(sha(raw) == 'e520fd3549820c6b77f798f6f3cb2ec2ae28c3bf206054866f9a48de7f4ff46d', 'Current raw assistant mismatch')
    candidate = (ROOT / ASSISTANT).read_bytes().replace(b'\r\n', b'\n')
    guard = 'is_payment_schedule_question'
    changed = raw.replace(block(raw, guard, True), block(candidate, guard, True).replace(b'\n', b'\r\n'), 1)
    changed = changed.replace(b"const VERSION = '0.4.2'", b"const VERSION = '0.4.3'", 1)
    require(changed.replace(b'\r\n', b'\n') == candidate, 'Non-promotion assistant change refused')
    for name in ('harmat-migrated-snippets.php', 'harmat-public-audit-polish.php'):
        require((ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n') == git('wp-mu-plugins/' + name),
                'Legacy public owner must remain unchanged')
    module = (ROOT / 'wp-mu-plugins' / MAIN).read_bytes().replace(b'\r\n', b'\n')
    require(b'function harmat_ppc_html(' in module and len(module) < 40000, 'Bounded public module required')
    edits = {TARGET_A: (raw, changed), TARGET_N: (None, module)}
    helpers = {n: (HERE / n).read_bytes().replace(b'\r\n', b'\n') for n in (NATIVE, D.CHECKER, '2026-10-18-test-public-payment-copy.php')}
    pins = {p: {'old': None if a is None else sha(a), 'new': sha(b)} for p, (a, b) in edits.items()}
    digest = sha(P.encoded({'sources': pins, 'helpers': {n: sha(b) for n, b in helpers.items()}}))
    return edits, helpers, pins, digest


def snapshot(r):
    # Hash assets on the server instead of downloading unchanged multi-MB media.
    paths = set(P.protected_paths(r))
    paths.discard(TARGET_A)
    for group, names in (
        ('harmat-about-family', ('body.php', 'about-family.css', 'harmat-courtyard.jpg', 'harmat-living-room.jpg')),
        ('harmat-contact-family', ('body.php', 'contact-family.css', 'project-garden.jpg', 'showroom-model.jpg', 'showroom-lounge.jpg', 'project-location.jpg')),
    ):
        paths.update(P.DIR + '/' + group + '/' + n for n in names)
    paths.update(P.DIR + '/' + n for n in ('zz-harmat-about-family.php', 'zz-harmat-contact-family.php', 'zz-harmat-reserved-virtual-view.php'))
    paths.update(H.LIVE + '/wp-content/plugins/360/' + n for n in ('viewer.js', 'viewer.css', 'lakaspark-360.php'))
    code = '$r=array();foreach(json_decode($argv[1],true) as $p){if(!is_file($p)||is_link($p))throw new RuntimeException("Source missing");$r[$p]=hash_file("sha256",$p);}echo json_encode($r);'
    values = json.loads(r.run(H.PHP + ' -r ' + shlex.quote(code) + ' -- ' + shlex.quote(json.dumps(sorted(paths)))))
    require(set(values) == paths and all(re.fullmatch(r'[a-f0-9]{64}', v) for v in values.values()), 'Protected source hash set incomplete')
    require(all(values[p] == expected for p, expected in D.I.CRITICAL.items()), 'Pinned offer/sales/inventory source changed')
    return {'sources': values, 'protected': D.I.protected(r), 'public_pages': D.T.pages(r)}


def metadata(r, path, expected):
    require(r.read(path) == expected, 'Source ownership drift: ' + path)
    info = r.info(path)
    import stat
    result = (stat.S_IMODE(info.st_mode), info.st_uid, info.st_gid)
    require(H.safe_public_mode(*result, r.private_group_verified), 'Unsafe source metadata')
    return result


def replace(r, path, old, new, meta):
    stage = path.rsplit('/', 1)[0] + '/.' + Path(path).name + '.' + uuid.uuid4().hex + '.tmp'
    try:
        r.write_new(stage, new)
        r.lint(stage)
        r.stage_metadata(stage, meta)
        require(r.read(stage) == new, 'Stage bytes changed')
        if old is None:
            require(P.absent(r, path), 'Absent target collision')
            r.sftp.rename(stage, path)
        else:
            require(metadata(r, path, old) == tuple(meta), 'Source metadata changed')
            r.sftp.posix_rename(stage, path)
        r.lint(path)
        metadata(r, path, new)
    finally:
        if not P.absent(r, stage):
            actual = r.read(stage)
            require(new.startswith(actual), 'Foreign stage retained')
            r.remove_owned(stage, actual)


def assert_page(text, old, active, optional=False):
    page, original = D.Page(text), D.Page(old)
    require(page.form == original.form and page.form and page.seo == original.seo and page.canonical == original.canonical,
            'Native offer form/SEO contract changed')
    marker = text.count('id="harmat-public-payment-copy"')
    require(marker in (0, 1) if active and optional else marker == (1 if active else 0), 'Public payment root missing/duplicate')
    if active and marker == 0:
        require(optional and 'class="elementor elementor-4214"' not in old
                and 'class="elementor elementor-4214"' not in text and 'Foglaló: 10%' not in text,
                'A known old payment block cannot be silently omitted')
    if active and marker == 1:
        require('class="elementor elementor-4214"' not in text and 'Foglaló: 10%' not in text, 'Old payment section retained')
        require(all(token in text for token in ('8%', '4%', '2026. december 31', '2027. június 30', '25%', '20%', '5%')), 'Approved payment values missing')
        require('2027.05.31' not in text and '2027.06.01' not in text, 'Obsolete public deadline')
    return {'native_form_unchanged': True, 'native_seo_unchanged': True, 'payment_roots': marker}


def native(r, backup):
    proofs = []
    for route in SAMPLES:
        texts = []
        for without in (True, False):
            data = json.loads(r.run(H.PHP + ' ' + shlex.quote(backup + '/' + NATIVE) + ' '
                                   + shlex.quote(backup + '/' + MAIN) + ' ' + shlex.quote(route) + (' --without' if without else '')))
            text = r.read(backup + '/' + data['file'], private=True).decode('utf-8')
            require(sha(text.encode()) == data['sha256'], 'Native proof bytes changed')
            texts.append(text)
        try:
            proofs.append({'route': route, **assert_page(texts[1], texts[0], True, route in OPTIONAL)})
        except Exception as error:
            raise RuntimeError('Native payment proof failed on ' + route + ': ' + str(error)) from error
    return proofs


def public(r, baseline, active):
    results = D.public_proof(r, (), {}, baseline['/'].decode('utf-8'))
    results['payment_pages'] = {}
    for route in PUBLIC:
        data, mime = D.get(P.ORIGIN + route)
        require('text/html' in mime, 'Public MIME mismatch')
        results['payment_pages'][route] = assert_page(data.decode('utf-8'), baseline[route].decode('utf-8'), active, route in OPTIONAL)
    reflect = '''$r=new ReflectionClass('Harmat_Local_Assistant');$o=$r->newInstanceWithoutConstructor();
$n=$r->getMethod('normalize');$g=$r->getMethod('is_payment_schedule_question');$a=$r->getMethod('payment_schedule_answer');
$q=json_decode('["Milyen akci\\u00f3k vannak?","\\u6709\\u7279\\u4ef7\\u5417","Are there any promotions?"]',true);
$v=array();foreach($q as $t){$v[]=$g->invoke($o,$n->invoke($o,$t));}
$h=array();foreach(array('hu','zh','en') as $l){$h[$l]=hash('sha256',$a->invoke($o,$l));}
echo wp_json_encode(array('version'=>Harmat_Local_Assistant::VERSION,'promotion_guards'=>$v,'answer_hashes'=>$h));'''
    assistant = json.loads(r.run(H.WP + ' eval ' + shlex.quote(reflect)))
    require(assistant['version'] == ('0.4.3' if active else '0.4.2')
            and assistant['promotion_guards'] == ([True, True, True] if active else [False, False, False]),
            'Actual pure assistant promotion guard mismatch')
    require(assistant['answer_hashes'] == {'hu': 'dfe5a56b582f7a649d8b9027470d32b10fae90cf7fff511f169a9a23d4c2e10d',
            'zh': '7f1c25c2b80ef4ac6500aba29c0cd656d5636456787e8f0cdc971c9d89932fb4',
            'en': '4a62c4d7b82fa5a1e876d2e51b0aa7c28d7deccbbbe847d53e0259e5857b411c'}, 'Approved assistant answer changed')
    results['assistant_pure_no_ask_dispatch'] = assistant
    return results


def restore(r, edits, meta):
    # Restore only exact owned source bytes; never rewind customer records.
    for path in (TARGET_N, TARGET_A):
        old, new = edits[path]
        if P.absent(r, path):
            require(old is None, 'Existing source unexpectedly absent')
            continue
        current = r.read(path)
        if old is not None and current == old:
            continue
        require(current == new, 'Foreign source retained during recovery')
        if old is None:
            r.remove_owned(path, new)
        else:
            replace(r, path, new, old, meta[path])
    r.purge()


def operate(edits, helpers, pins, digest, backup=None, reverse=False, prepare=False):
    fresh = backup is None
    backup = backup or PREFIX + uuid.uuid4().hex
    require(re.fullmatch(re.escape(PREFIX) + r'[a-f0-9]{32}', backup), 'Exact private backup required')
    r = H.Remote()
    locked = attempted = False
    try:
        r.directory(H.BACKUPS); r.directory(P.DIR)
        r.mkdir(D.LOCK); locked = True
        if fresh:
            require(P.absent(r, TARGET_N), 'Public module already exists')
            meta = {p: metadata(r, p, old) for p, (old, _) in edits.items() if old is not None}
            meta[TARGET_N] = (0o644, 1608, 1611)
            before = snapshot(r)
            baseline = {route: D.get(P.ORIGIN + route)[0] for route in ('/',) + PUBLIC}
            r.mkdir(backup)
            for name, blob in helpers.items():
                r.write_new(backup + '/' + name, blob); r.lint(backup + '/' + name)
            r.write_new(backup + '/' + MAIN, edits[TARGET_N][1]); r.lint(backup + '/' + MAIN)
            for label, path in (('assistant', TARGET_A),):
                for state, blob in zip(('original', 'candidate'), edits[path]):
                    r.write_new(backup + '/' + state + '-' + label + '.php', blob)
                    r.lint(backup + '/' + state + '-' + label + '.php')
            for i, route in enumerate(baseline):
                r.write_new(backup + '/baseline-' + str(i) + '.html', baseline[route])
            saved = {'pins': pins, 'digest': digest, 'metadata': meta, 'before': before,
                     'routes': list(baseline), 'baseline_pins': {p: sha(b) for p, b in baseline.items()}}
            D.write_json(r, backup + '/manifest.json', saved)
            print(json.dumps({'status': 'PRIVATE_BACKUP_READY', 'backup': backup, 'aggregate': digest}), flush=True)
            pure = json.loads(r.run(H.PHP + ' ' + shlex.quote(backup + '/2026-10-18-test-public-payment-copy.php')
                                    + ' ' + shlex.quote(backup + '/' + MAIN)))
            require(pure.get('status') == 'PASS', 'Pure public copy tests failed')
            rendered = native(r, backup)
            filters = D.validate_native(r, backup)
            require(snapshot(r) == before, 'Protected data/source drift in preflight')
            D.write_json(r, backup + '/preflight.json', {'pure': pure, 'rendered': rendered, 'filters': filters})
            if prepare:
                print(json.dumps({'status': 'PREPARED_ONLY', 'backup': backup, 'rendered': rendered, 'filters': filters}), flush=True)
                return
            attempted = True
            for path in (TARGET_N, TARGET_A):
                replace(r, path, *edits[path], meta[path])
            r.purge()
        else:
            r.directory(backup, private=True)
            saved = json.loads(r.read(backup + '/manifest.json', private=True))
            require(saved['pins'] == pins and saved['digest'] == digest, 'Saved source pins differ')
            before, meta = saved['before'], saved['metadata']
            for label, path in (('assistant', TARGET_A),):
                for state, blob in zip(('original', 'candidate'), edits[path]):
                    require(r.read(backup + '/' + state + '-' + label + '.php', private=True) == blob, 'Saved exact backup changed')
            require(r.read(backup + '/' + MAIN, private=True) == edits[TARGET_N][1], 'Saved module changed')
            baseline = {p: r.read(backup + '/baseline-' + str(i) + '.html', private=True) for i, p in enumerate(saved['routes'])}
            require({p: sha(b) for p, b in baseline.items()} == saved['baseline_pins'], 'Saved public baseline changed')
            if reverse: restore(r, edits, meta)
        proof = public(r, baseline, not reverse)
        filters = D.validate_native(r, backup)
        require(snapshot(r) == before, 'Protected state drift after release; no business-data recovery')
        for path, (old, new) in edits.items():
            if reverse and old is None:
                require(P.absent(r, path), 'Module remains after rollback')
            else:
                metadata(r, path, old if reverse else new)
        if fresh: D.write_json(r, backup + '/release.json', {'public': proof, 'filters': filters})
        print(json.dumps({'status': 'RELEASED' if fresh else 'ROLLED_BACK_SOURCE_ONLY' if reverse else 'VERIFIED',
                          'backup': backup, 'aggregate': digest, 'proof': proof, 'filters': filters}), flush=True)
    except BaseException:
        if attempted:
            restore(r, edits, meta)
            print(json.dumps({'status': 'EXACT_SOURCES_ROLLED_BACK', 'backup': backup}), flush=True)
        raise
    finally:
        try:
            if locked: r.sftp.rmdir(D.LOCK)
        finally: r.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--deploy', action='store_true')
    mode.add_argument('--prepare', action='store_true')
    mode.add_argument('--verify')
    mode.add_argument('--rollback')
    parser.add_argument('--expect')
    args = parser.parse_args()
    edits, helpers, pins, digest = release()
    if not any((args.deploy, args.prepare, args.verify, args.rollback)):
        print(json.dumps({'mode': 'OFFLINE_NO_NETWORK', 'aggregate': digest, 'pins': pins}, indent=2)); return
    require(args.expect == digest, 'Exact reviewed aggregate required')
    operate(edits, helpers, pins, digest, args.verify or args.rollback, bool(args.rollback), args.prepare)


if __name__ == '__main__': main()
