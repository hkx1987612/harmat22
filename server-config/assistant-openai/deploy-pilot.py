"""Guarded pilot release. API key is accepted only over stdin and stored privately."""
import json
from pathlib import Path
import re
import runpy
import shlex
import subprocess
import sys
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
MAINT = HERE.parent / 'maintenance'
B = runpy.run_path(str(MAINT / '2026-10-29-deploy-search-budget.py'))
H, Q, require = B['H'], B['Q'], B['require']
SOURCE = 'wp-plugins/harmat-local-assistant/harmat-local-assistant.php'
MU = 'wp-mu-plugins/zz-harmat-openai-pilot.php'
TARGET = H.LIVE + '/wp-content/plugins/harmat-local-assistant/harmat-local-assistant.php'
ADDON = H.LIVE + '/wp-content/mu-plugins/zz-harmat-openai-pilot.php'
PRIVATE = H.BACKUPS + '/assistant-openai-pilot'
BASE = 'c270be1'


def pages():
    result = {}
    for path in ('/', '/elerhetosegeink/', '/lakaskereso/', '/epitesi-naplo/'):
        body, mime = Q.get('https://harmat22.hu' + path)
        require('text/html' in mime and b'harmat-local-assistant-script' in body, 'Missing native page or assistant')
        require(not re.search(rb'Fatal error:|Parse error:', body), 'Public page error')
        result[path] = B['N'].D.Page(body.decode())
    return result


def snapshot(r):
    state = B['state'](r)
    for path in (TARGET, ADDON):
        state['sources'].pop(path, None)
    return state


def deploy(key):
    old_lf = subprocess.check_output(['git', 'show', BASE + ':' + SOURCE], cwd=ROOT)
    new_lf = B['norm']((ROOT / SOURCE).read_bytes())
    addon = B['norm']((ROOT / MU).read_bytes())
    library = (HERE / 'faq-router.php').read_bytes()
    r = H.Remote()
    backup = H.BACKUPS + '/openai-faq-release-' + uuid.uuid4().hex
    lock = H.BACKUPS + '/.openai-faq-release.lock'
    locked = created_private = False
    old = new = meta = None
    private_files = []
    try:
        for directory in (H.BACKUPS, TARGET.rsplit('/', 1)[0], ADDON.rsplit('/', 1)[0]):
            r.directory(directory)
        r.mkdir(lock)
        locked = True
        absent = B['N'].D.absent
        require(absent(r, ADDON) and absent(r, PRIVATE), 'Existing pilot must not be overwritten')
        old = r.read(TARGET)
        new = B['raw_patch'](old, old_lf, new_lf)
        meta = Q.metadata(r, TARGET, old)
        before = pages()
        protected = snapshot(r)
        r.mkdir(backup)
        for name, blob in {'original.php': old, 'candidate.php': new, 'pilot-module.php': addon,
                           'native-preflight.php': (HERE / 'native-preflight.php').read_bytes()}.items():
            path = backup + '/' + name
            r.write_new(path, blob)
            r.lint(path)
        checker = B['N'].D.CHECKER
        r.write_new(backup + '/' + checker, (MAINT / checker).read_bytes())
        B['native_filters'](r, backup)
        r.mkdir(PRIVATE)
        created_private = True
        day = r.run(H.PHP + ' -r ' + shlex.quote('echo (new DateTimeImmutable("now",new DateTimeZone("Europe/Budapest")))->format("Y-m-d");'))
        require(re.fullmatch(r'\d{4}-\d{2}-\d{2}', day), 'Host date unavailable')
        for name, blob in {'faq-router.php': library, 'quota.json': json.dumps({'date': day, 'tickets': []}).encode(),
                           'api-key': key}.items():
            private_files.append(PRIVATE + '/' + name)
            r.write_new(private_files[-1], blob)
        require(r.run(H.WP + ' eval-file ' + shlex.quote(backup + '/native-preflight.php')) == 'PILOT_NATIVE_PASS', 'Native preflight failed')
        require(snapshot(r) == protected, 'Protected state drift before activation')
        print('PRIVATE_PREFLIGHT_PASS ' + backup, flush=True)
        Q.replace(r, ADDON, None, addon, (0o644, meta[1], meta[2]))
        Q.replace(r, TARGET, old, new, meta)
        r.purge()
        after = pages()
        for path, baseline in before.items():
            current = after[path]
            require(current.form == baseline.form and current.form and current.canonical == baseline.canonical and current.seo == baseline.seo,
                    'Offer form or page metadata changed: ' + path)
        B['native_filters'](r, backup)
        require(snapshot(r) == protected, 'Protected state drift after activation')
        Q.metadata(r, TARGET, new)
        Q.metadata(r, ADDON, addon)
        print('RELEASED ' + backup, flush=True)
    except BaseException:
        if created_private:
            # Remove the credential first so any remaining code fails closed.
            path = PRIVATE + '/api-key'
            if not B['N'].D.absent(r, path):
                r.remove_owned(path, key)
        if new is not None and meta is not None and r.read(TARGET) == new:
            Q.replace(r, TARGET, new, old, meta)
        if created_private and not B['N'].D.absent(r, ADDON):
            r.remove_owned(ADDON, addon)
        if created_private:
            r.purge()
        raise
    finally:
        try:
            if locked:
                r.sftp.rmdir(lock)
        finally:
            r.close()


if __name__ == '__main__':
    key = sys.stdin.buffer.readline(1025).strip()
    if not re.fullmatch(rb'sk-[A-Za-z0-9_-]{16,512}', key):
        raise SystemExit('No recognized key; no server changes.')
    deploy(key)
