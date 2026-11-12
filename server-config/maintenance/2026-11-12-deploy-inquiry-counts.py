"""Guarded inquiry counter release. Source-only rollback; no business data writes."""
import argparse
import importlib.util
import json
import shlex
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('inquiry_audited_transport', HERE / '2026-11-05-deploy-reservation-inventory.py')
T = importlib.util.module_from_spec(spec)
spec.loader.exec_module(T)
M = T.M
M.BASE = 'd8dd101'
M.OUT = M.ROOT / 'outputs/2026-11-12-inquiry-counter-fix'
M.PREFIX = M.H.BACKUPS + '/crm-inquiry-counts-'
M.LOCK = M.H.BACKUPS + '/.crm-inquiry-counts.lock'
_native = M.native


def counters(remote, folder, candidate):
    helper = folder + '/native-inquiry-counts.php'
    try:
        existing = remote.read(helper, private=True)
        M.require(existing == (HERE / '2026-11-12-native-inquiry-counts.php').read_bytes(), 'Audit helper drift')
    except FileNotFoundError:
        remote.write_new(helper, (HERE / '2026-11-12-native-inquiry-counts.php').read_bytes())
    remote.lint(helper)
    result = json.loads(remote.run(M.H.WP + ' eval-file ' + shlex.quote(helper) + ' ' + shlex.quote(candidate)))
    M.require(result['status'] == 'PASS', 'Native inquiry count mismatch')
    return result


def native(remote, folder):
    report = _native(remote, folder)
    report['inquiry_counts'] = counters(remote, folder, folder + '/candidate-class.php')
    return report


M.native = native


def verify():
    saved = json.loads((M.OUT / 'preflight.json').read_text())
    remote = M.H.Remote()
    try:
        target = M.PATHS['crm'][1]
        M.require(M.sha(remote.read(target)) == saved['pins']['crm']['new'], 'Live source mismatch')
        result = counters(remote, saved['backup'], 'live')
        (M.OUT / 'live-counts.json').write_bytes(M.encoded(result))
        print(json.dumps(result), flush=True)
    finally:
        remote.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--preflight', action='store_true')
    group.add_argument('--deploy', action='store_true')
    group.add_argument('--verify', action='store_true')
    args = parser.parse_args()
    if args.preflight:
        import subprocess
        subprocess.run(['php.cmd', str(HERE / '2026-11-12-test-inquiry-counts.php')], check=True, cwd=M.ROOT)
        M.OUT.mkdir(parents=True, exist_ok=True)
        (M.OUT / 'backup.json').write_bytes((M.ROOT / 'outputs/2026-11-04-crm-review/backup.json').read_bytes())
        M.preflight()
    elif args.deploy:
        M.deploy()
        verify()
    elif args.verify:
        verify()
    else:
        print('Offline by default. Fresh verified backup and --preflight required before --deploy.')
