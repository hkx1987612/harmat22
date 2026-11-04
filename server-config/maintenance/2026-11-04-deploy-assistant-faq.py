"""Replace only the tested local assistant FAQ source; leave the OpenAI runtime untouched."""
import argparse
import json
from pathlib import Path
import runpy
import subprocess
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
P = runpy.run_path(str(HERE.parent / 'assistant-openai' / 'deploy-pilot.py'))
B, H, Q, require = P['B'], P['H'], P['Q'], P['require']
BASE = '4aba20c'
OLD_RAW = 'abcd38b0dbdecec96a8c72085614f48dae744dce2b2664c6fcd50fc0860c2e02'
PILOT_SHA = '353f976c8507ade6cfe061d9eb871bbb30b9f62a03c816c41075896220b6fc86'
SOURCE, TARGET, ADDON = P['SOURCE'], P['TARGET'], P['ADDON']
LOCK = H.BACKUPS + '/.openai-faq-release.lock'


def local():
    old = subprocess.check_output(['git', 'show', BASE + ':' + SOURCE], cwd=ROOT)
    new = B['norm']((ROOT / SOURCE).read_bytes())
    for path in (P['MU'], 'server-config/assistant-openai/faq-router.php'):
        expected = subprocess.check_output(['git', 'show', BASE + ':' + path], cwd=ROOT)
        require(B['norm']((ROOT / path).read_bytes()) == expected, 'Pilot source changed')
    for test in (HERE / '2026-11-04-test-assistant-faq.php', HERE / '2026-10-19-test-assistant-payment-terms.php',
                 HERE.parent / 'assistant-openai' / 'test-pilot-policy.php'):
        subprocess.run(['php', str(test)], cwd=ROOT, check=True)
    return old, new


def deploy(old_lf, new_lf):
    r = H.Remote()
    backup = H.BACKUPS + '/assistant-local-faq-' + uuid.uuid4().hex
    locked = False
    old = new = meta = None
    try:
        r.directory(H.BACKUPS)
        r.directory(TARGET.rsplit('/', 1)[0])
        r.mkdir(LOCK)
        locked = True
        old = r.read(TARGET)
        require(B['sha'](old) == OLD_RAW and B['sha'](r.read(ADDON)) == PILOT_SHA, 'Live source drift; no update made')
        new = B['raw_patch'](old, old_lf, new_lf)
        meta = Q.metadata(r, TARGET, old)
        before, protected = P['pages'](), P['snapshot'](r)
        r.mkdir(backup)
        for name, source in (('original.php', old), ('candidate.php', new)):
            r.write_new(backup + '/' + name, source)
            r.lint(backup + '/' + name)
        checker = B['N'].D.CHECKER
        r.write_new(backup + '/' + checker, (HERE / checker).read_bytes())
        B['native_filters'](r, backup)
        print('BACKED_UP ' + backup, flush=True)
        Q.replace(r, TARGET, old, new, meta)
        r.purge()
        after = P['pages']()
        for path, baseline in before.items():
            current = after[path]
            require(current.form == baseline.form and current.form and current.canonical == baseline.canonical
                    and current.seo == baseline.seo, 'Public form/SEO mismatch: ' + path)
        native = B['native_filters'](r, backup)
        require(P['snapshot'](r) == protected and B['sha'](r.read(ADDON)) == PILOT_SHA, 'Protected state changed')
        Q.metadata(r, TARGET, new)
        report = {'status': 'RELEASED', 'backup': backup, 'raw_sha256': B['sha'](new),
                  'public_routes': len(after), 'native_checks': native, 'pilot_source_unchanged': True}
        r.write_new(backup + '/release.json', json.dumps(report).encode())
        print(json.dumps(report), flush=True)
    except BaseException:
        if new is not None and meta is not None and r.read(TARGET) == new:
            Q.replace(r, TARGET, new, old, meta)
            r.purge()
            print('ROLLED_BACK_SOURCE_ONLY ' + backup, flush=True)
        raise
    finally:
        try:
            if locked:
                r.sftp.rmdir(LOCK)
        finally:
            r.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--deploy', metavar='REVIEWED_SHA256')
    args = parser.parse_args()
    old, new = local()
    digest = B['sha'](new)
    if args.deploy:
        require(args.deploy == digest, 'Reviewed candidate digest required')
        deploy(old, new)
    else:
        print('OFFLINE_PASS ' + digest)
