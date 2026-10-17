"""Exact two-file About numeral correction; default offline, no business writes."""
import argparse
import importlib.util
import json
import shlex
import uuid
from pathlib import Path

spec = importlib.util.spec_from_file_location('about_release', Path(__file__).with_name('2026-10-16-deploy-about-family.py'))
D = importlib.util.module_from_spec(spec); spec.loader.exec_module(D)
PREVIOUS = D.PREFIX + '49c368aea72c4b05a49bc60bb3fc9e00'
UPDATES = (D.ASSET + '/about-family.css', D.MAIN)
OLD = dict(zip(UPDATES, ('de5fa727295f6b76b0ea7945d71cce840043e88faa2e8d396b0652cebd214042',
                       'e46f0183118c11e23c6775d081242b2478df2b057d8a291f21d16d756bfd1ce2')))

def refresh(blobs, pins, digest):
    r = D.H.Remote(); locked = False; stages = {}; installed = []; originals = {}
    backup = D.PREFIX + uuid.uuid4().hex
    try:
        r.directory(D.DIR); r.directory(D.H.BACKUPS)
        r.mkdir(D.LOCK); locked = True
        previous = json.loads(r.read(PREVIOUS + '/manifest.json', private=True))
        D.require(all(previous['pins'][n] == OLD[n] for n in UPDATES), 'Previous release identity mismatch')
        D.require(all(previous['pins'][n] == pins[n] for n in D.FILES if n not in UPDATES), 'Unexpected extra file change')
        D.verify_files(r, previous['pins'])
        before = D.snapshot(r)
        D.require(before == previous['before'], 'Protected baseline drift')
        r.mkdir(backup); r.mkdir(backup + '/' + D.ASSET)
        manifest = {'bundle':digest, 'pins':pins, 'before':before, 'previous':PREVIOUS,
                    'original_absent':list(D.FILES), 'updated_files':list(UPDATES)}
        D.write_json(r, backup + '/manifest.json', manifest)
        for index, name in enumerate(UPDATES):
            originals[name] = r.read(D.DIR + '/' + name)
            r.write_new(backup + '/original-' + str(index) + '.bin', originals[name])
        for name, data in blobs.items():
            r.write_new(backup + '/' + name, data)
            if name.endswith('.php'): r.lint(backup + '/' + name)
        fixture = r.run(D.H.PHP + ' ' + shlex.quote(backup + '/' + D.TEST) + ' ' + shlex.quote(backup + '/' + D.MAIN))
        D.require('PASS: 513 assertions' in fixture, 'Updated isolated fixture failed')
        r.write_new(backup + '/fixture-result.txt', fixture.encode())
        native = json.loads(r.run(D.H.PHP + ' ' + shlex.quote(backup + '/' + D.NATIVE) + ' ' + shlex.quote(backup + '/' + D.MAIN)))
        D.require(native['native'] is True, 'Native render failed')
        D.assert_page(r.read(backup + '/native-render.html', private=True).decode('utf-8'))
        D.require(D.snapshot(r) == before, 'Protected drift before refresh')
        for name in UPDATES:
            stage = D.DIR + '/' + name + '.' + uuid.uuid4().hex + '.tmp'
            stages[stage] = blobs[name]; r.write_new(stage, blobs[name])
            if name.endswith('.php'): r.lint(stage)
            D.require(D.sha(r.read(stage)) == pins[name], 'Stage bytes changed')
            r.sftp.chmod(stage, 0o644)
        D.verify_files(r, previous['pins'])
        for name, stage in zip(UPDATES, tuple(stages)):
            r.sftp.posix_rename(stage, D.DIR + '/' + name); stages.pop(stage); installed.append(name)
            if name.endswith('.php'): r.lint(D.DIR + '/' + name)
        r.purge(); D.verify_files(r, pins)
        proof = D.public_proof(r, pins)
        D.require(D.snapshot(r) == before, 'Protected state changed; never restore business data')
        D.write_json(r, backup + '/release-proof.json', {'native':native, 'proof':proof, 'pins':pins})
        print(json.dumps({'status':'REFRESHED', 'backup':backup, 'bundle':digest, 'proof':proof}), flush=True)
    except BaseException:
        for stage, data in stages.items():
            if not D.absent(r, stage): r.remove_owned(stage, data)
        # Restore only the two owned display files; never restore database records.
        for name in reversed(installed):
            target = D.DIR + '/' + name
            D.require(D.sha(r.read(target)) == pins[name], 'Rollback refuses a changed display file')
            stage = target + '.' + uuid.uuid4().hex + '.rollback'
            r.write_new(stage, originals[name])
            if name.endswith('.php'): r.lint(stage)
            r.sftp.chmod(stage, 0o644); r.sftp.posix_rename(stage, target)
            if name.endswith('.php'): r.lint(target)
        if installed: r.purge()
        print(json.dumps({'status':'REFRESH_NOT_ACTIVE_OR_OWNED_FILES_RESTORED','backup':backup}), flush=True)
        raise
    finally:
        if locked: r.sftp.rmdir(D.LOCK)
        r.close()

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--refresh', action='store_true'); parser.add_argument('--expect')
    args = parser.parse_args(); blobs,pins,digest = D.bundle()
    if args.refresh:
        D.require(args.expect == digest, 'Exact reviewed bundle required')
        refresh(blobs,pins,digest)
    else:
        print(json.dumps({'mode':'OFFLINE_NO_WRITES', 'bundle':digest, 'pins':pins}, indent=2))
