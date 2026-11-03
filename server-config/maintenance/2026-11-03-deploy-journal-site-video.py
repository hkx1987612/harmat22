"""Publish October 30 site footage to the journal, not the homepage."""
import argparse
import importlib.util
import json
import subprocess
import uuid

from pathlib import Path

HERE = Path(__file__).resolve().parent

spec = importlib.util.spec_from_file_location('daily_transport', HERE / '2026-10-20-deploy-daily-construction.py')
prior = importlib.util.module_from_spec(spec)
spec.loader.exec_module(prior)
B = prior.load('journal_snapshot', '2026-10-29-deploy-search-budget.py')
C, D, H, Q = prior.C, prior.D, prior.H, prior.Q
require, sha = prior.require, prior.sha
SOURCE = 'wp-mu-plugins/' + prior.NAME
TEST = '2026-11-03-test-journal-site-video.php'
PREFIX = H.BACKUPS + '/journal-site-video-'
OUT = 'outputs/2026-11-03-journal-site-video/'
FILES = {
    '2026-10-30-harmat-site.mp4': (177879130, 'ca44a476e741fee00de68fbc96a39afadcc137e12e50682aaa8dcfa30b59e60e'),
    '2026-10-30-harmat-site.jpg': (191048, '64163376b7c0f41a13aabe5f11f39366105b31f3b8600e3e3448f31de44d2cab'),
}
MEDIA = {name: {'bytes': size, 'sha256': digest} for name, (size, digest) in FILES.items()}


def local():
    for name, (size, digest) in FILES.items():
        path = prior.ROOT / OUT / name
        require(path.is_file() and not path.is_symlink() and path.stat().st_size == size
                and C.file_hash(path) == digest, 'Reviewed media mismatch: ' + name)
    new = B.norm((prior.ROOT / SOURCE).read_bytes())
    fixture = subprocess.check_output(['php', str(HERE / TEST), str(prior.ROOT / SOURCE)], cwd=prior.ROOT)
    require(json.loads(fixture) == {'status': 'PASS', 'checks': 10}, 'Local journal fixture failed')
    return new, sha(B.encoded({'source': sha(new), 'media': MEDIA}))


def snapshot(r):
    state = B.state(r)
    state['sources'].pop(prior.TARGET, None)
    return state


def proof(before, after):
    for route in before:
        old, new = before[route].decode(), after[route].decode()
        a, b = D.Page(old), D.Page(new)
        require(a.form == b.form and a.form and a.canonical == b.canonical, 'Native form/canonical changed: ' + route)
        if route.startswith('/property/'):
            continue  # Form and canonical checked; property data is covered by the protected snapshot.
        old_main, new_main = (C.owned(text, 'id', 'main') for text in (old, new))
        if route == '/epitesi-naplo/':
            journal = C.owned(new_main, 'id', 'harmat-construction-updates')
            require(journal.count('data-hcu-player data-hcu-video=') == 8, 'Expected eight lazy players')
            entry = C.owned(journal, 'id', 'construction-2026-10-30')
            require(entry.count('data-hcu-player data-hcu-video=') == 1 and '<video' not in entry, 'Expected click-only site video')
            new_main = new_main.replace(entry, '', 1)
        require(B.clean(B.html.fromstring(new_main)) == B.clean(B.html.fromstring(old_main)), 'Unexpected main delta: ' + route)
    return {'routes': len(before), 'journal_entries': 8, 'homepage_and_native_forms_unchanged': True}


def operate(new):
    r = H.Remote()
    backup = PREFIX + uuid.uuid4().hex
    locked = False
    old = candidate = meta = None
    stages, uploaded = {}, {}
    try:
        for directory in (H.BACKUPS, D.DIR, prior.MEDIA):
            r.directory(directory)
        r.mkdir(D.LOCK)
        locked = True
        old = r.read(prior.TARGET)
        require(sha(B.norm(old)) == prior.NEW, 'Live registry differs from known October 20 release')
        candidate = B.raw_patch(old, B.norm(old), new)
        meta = Q.metadata(r, prior.TARGET, old)
        require(all(D.absent(r, prior.MEDIA + '/' + n) for n in MEDIA), 'Media destination already exists')
        before = B.pages()
        protected = snapshot(r)
        r.mkdir(backup)
        r.write_new(backup + '/original-module.php', old)
        r.write_new(backup + '/candidate-module.php', candidate)
        r.lint(backup + '/original-module.php')
        r.lint(backup + '/candidate-module.php')
        for name in (TEST, '2026-10-18-test-construction-updates.php', D.CHECKER):
            r.write_new(backup + '/' + name, (HERE / name).read_bytes())
        r.mkdir(backup + '/harmat-home-family')
        r.write_new(backup + '/harmat-home-family/body.php', r.read(D.DIR + '/harmat-home-family/body.php'))
        fixture = json.loads(r.run(H.PHP + ' ' + backup + '/' + TEST + ' ' + backup + '/candidate-module.php'))
        require(fixture == {'status': 'PASS', 'checks': 10}, 'Private journal fixture failed')
        D.validate_native(r, backup)
        print(json.dumps({'status': 'BACKED_UP_AND_VALIDATED', 'backup': backup}), flush=True)
        C.NAMES = tuple(FILES)
        for name in FILES:
            C.upload_media(r, prior.ROOT / OUT / name, name, MEDIA[name], stages)
            uploaded[prior.MEDIA + '/' + name] = MEDIA[name]
            C.media_http(name, MEDIA[name])
            print('VERIFIED_MEDIA ' + name, flush=True)
        require(snapshot(r) == protected, 'Protected state drift before activation')
        Q.replace(r, prior.TARGET, old, candidate, meta)
        r.purge()
        result = proof(before, B.pages())
        native = D.validate_native(r, backup)
        require(snapshot(r) == protected, 'Protected state changed during release')
        Q.metadata(r, prior.TARGET, candidate)
        report = {'status': 'RELEASED', 'backup': backup, 'source_sha256': sha(candidate),
                  'public': result, 'native': native, 'media': MEDIA}
        D.write_json(r, backup + '/release.json', report)
        print(json.dumps(report), flush=True)
    except BaseException:
        if candidate is not None and meta is not None:
            current = r.read(prior.TARGET)
            require(current in (old, candidate), 'Foreign source retained; media cleanup stopped')
            if current == candidate:
                Q.replace(r, prior.TARGET, candidate, old, meta)
                r.purge()
            retained = C.cleanup_media(r, MEDIA, {**uploaded, **stages})
            print(json.dumps({'status': 'RELEASE_NOT_ACTIVE', 'backup': backup, 'retained_stages': retained}), flush=True)
        raise
    finally:
        try:
            if locked:
                r.sftp.rmdir(D.LOCK)
        finally:
            r.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--deploy', metavar='REVIEWED_DIGEST')
    args = parser.parse_args()
    source, digest = local()
    if args.deploy:
        require(args.deploy == digest, 'Reviewed digest required')
        operate(source)
    else:
        print(json.dumps({'status': 'OFFLINE_PASS', 'digest': digest}))
