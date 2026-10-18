"""Controller-only header-flow repair. Default is offline; no form submissions."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import stat
import subprocess
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('home_header_previous', HERE / '2026-10-18-deploy-home-family.py')
D = importlib.util.module_from_spec(spec)
spec.loader.exec_module(D)
H = D.H
TARGET = D.DIR + '/' + D.MAIN
PREFIX = H.BACKUPS + '/home-header-flow-'
BASE = '283fd91'
OLD_SHA = '894a579f25882a94c5af3a49b47b4c416ee81461bc11dfbc58bda9709706c2b6'
STYLE = '<style id="harmat-home-family-header-flow">body.home.page-id-164.opal-header-absolute #masthead,body.home.page-id-164.opal-header-absolute #masthead > div.site-header{position:relative!important;background:#fff}</style>'
OLD = b"        $resources='';"
NEW = ("        $resources='" + STYLE + "';").encode()
require = D.require
sha = D.sha


def patch(original):
    require(sha(original) == OLD_SHA and b'\r' not in original, 'Pinned original LF controller required')
    require(original.count(OLD) == 1 and original.count(b' * Version: 1.0.0') == 1, 'Exact patch sites required')
    return original.replace(OLD, NEW, 1).replace(b' * Version: 1.0.0', b' * Version: 1.0.1', 1)


def local_release():
    original = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + D.MAIN], cwd=ROOT)
    candidate = patch(original)
    require((ROOT / 'wp-mu-plugins' / D.MAIN).read_bytes().replace(b'\r\n', b'\n') == candidate,
            'Local controller is not the exact reviewed patch')
    files, blobs, hashes, _ = D.bundle()
    return original, candidate, files, hashes, blobs[D.CHECKER]


def target(r, expected, metadata=None):
    r.directory(D.DIR)
    require(r.read(TARGET) == expected, 'Controller ownership drift; refusing overwrite')
    info = r.info(TARGET)
    found = (stat.S_IMODE(info.st_mode), info.st_uid, info.st_gid)
    require(H.safe_public_mode(*found, getattr(r, 'private_group_verified', False)) and found[0] & 0o400,
            'Unsafe controller permissions')
    if metadata is not None:
        require(found == tuple(metadata), 'Controller metadata drift')
    return found


def assets(r, files, hashes):
    for name in files:
        if name != D.MAIN:
            require(sha(r.read(D.DIR + '/' + name)) == hashes[name], 'Unchanged asset drift: ' + name)


def state(r, files, hashes):
    assets(r, files, hashes)
    return D.snapshot(r)


def clean_stage(r, stage, expected):
    if stage and not D.absent(r, stage):
        actual = r.read(stage)
        require(expected.startswith(actual), 'Foreign temporary bytes retained')
        r.remove_owned(stage, actual)


def replace(r, expected, replacement, metadata):
    stage = TARGET + '.' + uuid.uuid4().hex + '.tmp'
    try:
        r.write_new(stage, replacement)
        r.lint(stage)
        r.stage_metadata(stage, metadata)
        require(r.read(stage) == replacement, 'Staged controller drift')
        target(r, expected, metadata)
        # Same-directory atomic replacement after the final exact ownership check.
        # D.LOCK coordinates both homepage tools, not unrelated external editors.
        require(r.read(TARGET) == expected, 'Controller changed immediately before replacement')
        r.sftp.posix_rename(stage, TARGET)
        r.lint(TARGET)
        target(r, replacement, metadata)
    finally:
        clean_stage(r, stage, replacement)


def public(r, files, hashes, baseline, updated):
    proof = D.public_proof(r, files, hashes, baseline)
    data, mime = D.get(D.URL)
    require('text/html' in mime, 'Homepage MIME mismatch')
    count = data.decode('utf-8').count(STYLE)
    require(count == (1 if updated else 0), 'Public header-flow state mismatch')
    return proof


def recover(r, original, candidate, metadata):
    current = r.read(TARGET)
    if current == original:
        target(r, original, metadata)
    else:
        require(current == candidate, 'Foreign controller retained; manual recovery required')
        replace(r, candidate, original, metadata)
    r.purge()


def deploy(original, candidate, files, hashes, checker):
    r = H.Remote()
    locked = False
    attempted = False
    backup = PREFIX + uuid.uuid4().hex
    try:
        r.directory(H.BACKUPS)
        r.mkdir(D.LOCK)
        locked = True
        metadata = target(r, original)
        before = state(r, files, hashes)
        baseline, mime = D.get(D.URL)
        require('text/html' in mime and STYLE.encode() not in baseline, 'Unexpected public baseline')
        D.assert_home(baseline.decode('utf-8'), baseline.decode('utf-8'))
        r.mkdir(backup)
        r.write_new(backup + '/original.php', original)
        r.write_new(backup + '/candidate.php', candidate)
        r.write_new(backup + '/public-original.html', baseline)
        r.write_new(backup + '/' + D.CHECKER, checker)
        D.write_json(r, backup + '/manifest.json', {
            'target': TARGET, 'old': sha(original), 'new': sha(candidate),
            'metadata': metadata, 'before': before,
            'assets': {name: hashes[name] for name in files if name != D.MAIN}})
        r.lint(backup + '/original.php')
        r.lint(backup + '/candidate.php')
        r.lint(backup + '/' + D.CHECKER)
        filters = D.validate_native(r, backup)
        require(state(r, files, hashes) == before, 'Protected state drift before replacement')
        D.write_json(r, backup + '/preflight-proof.json', {'filters': filters, 'controller_only': True})
        print(json.dumps({'status': 'PRIVATE_BACKUP_READY', 'backup': backup}), flush=True)
        attempted = True
        replace(r, original, candidate, metadata)
        r.purge()
        proof = public(r, files, hashes, baseline.decode('utf-8'), True)
        filters = D.validate_native(r, backup)
        require(state(r, files, hashes) == before, 'Protected state drift after replacement; never restore business data')
        D.write_json(r, backup + '/release-proof.json', {'public': proof, 'filters': filters})
        print(json.dumps({'status': 'RELEASED_HOME_HEADER_FLOW', 'backup': backup, 'sha256': sha(candidate),
                          'proof': proof, 'filters': filters}), flush=True)
    except BaseException:
        if attempted:
            recover(r, original, candidate, metadata)
            print(json.dumps({'status': 'CONTROLLER_ONLY_ROLLED_BACK', 'backup': backup}), flush=True)
        raise
    finally:
        try:
            if locked:
                r.sftp.rmdir(D.LOCK)
        finally:
            r.close()


def verify_or_rollback(backup, original, candidate, files, hashes, reverse=False):
    require(re.fullmatch(re.escape(PREFIX) + r'[a-f0-9]{32}', backup), 'Exact private backup path required')
    r = H.Remote()
    locked = False
    try:
        r.directory(H.BACKUPS)
        r.mkdir(D.LOCK)
        locked = True
        r.directory(backup, private=True)
        saved = json.loads(r.read(backup + '/manifest.json', private=True))
        require(saved.get('target') == TARGET and saved.get('old') == sha(original)
                and saved.get('new') == sha(candidate), 'Saved ownership evidence mismatch')
        require(saved.get('assets') == {name: hashes[name] for name in files if name != D.MAIN},
                'Saved unchanged asset evidence mismatch')
        require(r.read(backup + '/original.php', private=True) == original
                and r.read(backup + '/candidate.php', private=True) == candidate, 'Saved raw controller mismatch')
        metadata = saved['metadata']
        require(len(metadata) == 3 and all(isinstance(v, int) for v in metadata), 'Invalid saved metadata')
        target(r, candidate, metadata)
        assets(r, files, hashes)
        baseline = r.read(backup + '/public-original.html', private=True).decode('utf-8')
        require(STYLE not in baseline, 'Invalid pre-update public baseline')
        require(r.read(backup + '/' + D.CHECKER, private=True)
                == (HERE / D.CHECKER).read_bytes().replace(b'\r\n', b'\n'), 'Saved native checker drift')
        if reverse:
            # Only this exact owned controller is restored; never restore any database.
            recover(r, original, candidate, metadata)
        else:
            r.lint(TARGET)
        proof = public(r, files, hashes, baseline, not reverse)
        filters = D.validate_native(r, backup)
        require(state(r, files, hashes) == saved['before'], 'Protected source/data drift; no data restored')
        print(json.dumps({'status': 'ROLLED_BACK_HOME_HEADER_FLOW' if reverse else 'VERIFIED_HOME_HEADER_FLOW',
                          'backup': backup, 'proof': proof, 'filters': filters}), flush=True)
    finally:
        try:
            if locked:
                r.sftp.rmdir(D.LOCK)
        finally:
            r.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument('--deploy', action='store_true')
    modes.add_argument('--verify')
    modes.add_argument('--rollback')
    parser.add_argument('--expect')
    args = parser.parse_args()
    original, candidate, files, hashes, checker = local_release()
    if not (args.deploy or args.verify or args.rollback):
        print(json.dumps({'mode': 'OFFLINE_NO_WRITES', 'old_sha256': sha(original), 'new_sha256': sha(candidate),
                          'target': TARGET, 'scope': 'CONTROLLER_ONLY_NATIVE_HEADER_FLOW'}))
        return
    require(args.expect == sha(candidate), 'Exact reviewed new controller SHA required')
    if args.deploy:
        deploy(original, candidate, files, hashes, checker)
    else:
        verify_or_rollback(args.verify or args.rollback, original, candidate, files, hashes, bool(args.rollback))


if __name__ == '__main__':
    main()
