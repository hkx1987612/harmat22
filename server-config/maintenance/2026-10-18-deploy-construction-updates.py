"""Owned construction release; offline by default. No customer writes or DB rollback."""
import argparse
from collections import Counter
from html.parser import HTMLParser
import importlib.util
import json
from pathlib import Path
import re
import shlex
import stat
import subprocess
import urllib.request
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, HERE / filename)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result


D = load('construction_home_transport', '2026-10-18-deploy-home-family.py')
U = load('construction_atomic_transport', '2026-10-18-update-home-header-flow.py')
H = D.H
require, sha = D.require, D.sha
HOME = D.DIR + '/' + D.MAIN
MODULE = 'zz-harmat-construction-updates.php'
TARGET = D.DIR + '/' + MODULE
PROGRESS = 'zz-harmat-construction-progress-video.php'
FIXTURE = '2026-10-18-test-construction-updates.php'
PREFIX = H.BACKUPS + '/construction-updates-'
MEDIA_PARENT = H.LIVE + '/wp-content/uploads/2026/10'
MEDIA = MEDIA_PARENT + '/construction-updates'
MEDIA_LOCAL = ROOT / 'outputs/2026-10-18-construction-web/media-v1'
MEDIA_URL = 'https://harmat22.hu/wp-content/uploads/2026/10/construction-updates/'
JOURNAL = 'https://harmat22.hu/epitesi-naplo/'
OLD_SHA = 'c641cd493e4cc75f3596960c9096ff25c027f0ddfea6c22f80eff9166556c33d'
BASE = '6a492ed'
BASENAMES = ('2026-10-13-compilation-hu-subtitled',) + tuple('latest-site-%02d' % i for i in range(1, 5))
NAMES = tuple(base + extension for base in BASENAMES for extension in ('.mp4', '.jpg'))
ROOT_ID = 'harmat-construction-updates'


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':'), ensure_ascii=True).encode()


def file_hash(path):
    import hashlib
    digest = hashlib.sha256()
    with path.open('rb') as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b''):
            digest.update(chunk)
    return digest.hexdigest()


def validate_media(manifest, directory=MEDIA_LOCAL):
    entries = manifest.get('files')
    require(isinstance(entries, dict) and set(entries) == set(NAMES), 'Exactly ten allowlisted media names required')
    require(directory.is_dir() and not directory.is_symlink(), 'Unsafe local media directory')
    for name in NAMES:
        entry = entries[name]
        require(isinstance(entry, dict) and set(entry) == {'sha256', 'bytes'}, 'Invalid media entry')
        require(isinstance(entry['sha256'], str) and re.fullmatch('[a-f0-9]{64}', entry['sha256']), 'Invalid media digest')
        require(type(entry['bytes']) is int and 0 < entry['bytes'] <= 256 * 1024 * 1024, 'Invalid media size')
        path = directory / name
        require(path.is_file() and not path.is_symlink() and path.resolve().parent == directory.resolve(), 'Unsafe local media path')
        require(path.stat().st_size == entry['bytes'] and file_hash(path) == entry['sha256'], 'Local media drift: ' + name)
    return {name: dict(entries[name]) for name in NAMES}


def local_release():
    original = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + D.MAIN], cwd=ROOT)
    require(sha(original) == OLD_SHA and b'\r' not in original, 'Pinned live homepage baseline required')
    files, blobs, hashes, _ = D.bundle()
    require(blobs[D.MAIN] != original, 'Homepage candidate is not ready')
    for name in files:
        if name != D.MAIN:
            baseline = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + name], cwd=ROOT)
            require(blobs[name] == baseline, 'Pinned unchanged homepage asset modified: ' + name)
    for name in (MODULE,):
        blobs[name] = (ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')
    blobs[FIXTURE] = (HERE / FIXTURE).read_bytes().replace(b'\r\n', b'\n')
    manifest_path = MEDIA_LOCAL / 'manifest.json'
    require(not manifest_path.is_symlink(), 'Linked media manifest refused')
    media = validate_media(json.loads(manifest_path.read_text(encoding='utf-8')))
    pins = {name: sha(data) for name, data in blobs.items()}
    release = {'schema': 1, 'old': OLD_SHA, 'home': HOME, 'module': TARGET, 'media_directory': MEDIA,
               'original_absent': [TARGET, MEDIA], 'pins': pins, 'media': media,
               'assets': {name: hashes[name] for name in files if name != D.MAIN}}
    return {'original': original, 'blobs': blobs, 'files': files, 'hashes': hashes,
            'release': release, 'digest': sha(canonical(release)), 'media_local': MEDIA_LOCAL}


def directories(r):
    r.directory(D.DIR)
    r.directory(H.BACKUPS)
    # Verify every upload path component, not just a symlink-free final directory.
    for suffix in ('', '/wp-content', '/wp-content/uploads', '/wp-content/uploads/2026', '/wp-content/uploads/2026/10'):
        r.directory(H.LIVE + suffix)


def protected(r, release):
    U.assets(r, release['files'], release['hashes'])
    result = dict(D.snapshot(r))
    result['construction_progress_video'] = sha(r.read(D.DIR + '/' + PROGRESS))
    return result


def remote_media(r, path, entry, public=True):
    before = r.info(path)
    require(stat.S_ISREG(before.st_mode), 'Media must be regular, never a symlink')
    require(before.st_size == entry['bytes'], 'Remote media size mismatch')
    require(stat.S_IMODE(before.st_mode) == (0o644 if public else 0o600), 'Remote media permissions mismatch')
    result = r.run('sha256sum -- ' + shlex.quote(path))
    require(result == entry['sha256'] + '  ' + path, 'Remote media hash mismatch')
    after = r.info(path)
    identity = lambda info: (info.st_mode, info.st_uid, info.st_gid, info.st_size, info.st_mtime)
    require(identity(before) == identity(after), 'Media changed during hashing')


def upload_media(r, local, name, entry, stages):
    require(name in NAMES, 'Media filename not allowlisted')
    require(local.is_file() and not local.is_symlink() and local.stat().st_size == entry['bytes']
            and file_hash(local) == entry['sha256'], 'Local media changed before upload')
    r.directory(MEDIA)
    target = MEDIA + '/' + name
    require(D.absent(r, target), 'Media destination already exists')
    stage = MEDIA + '/.' + name + '.' + uuid.uuid4().hex + '.tmp'
    # write_new creates an exclusive 0600 empty file using PHP umask 077.
    r.write_new(stage, b'')
    stages[stage] = entry
    info = r.info(stage)
    require(stat.S_ISREG(info.st_mode) and stat.S_IMODE(info.st_mode) == 0o600 and info.st_size == 0,
            'Exclusive media stage changed before upload')
    r.sftp.put(str(local), stage, confirm=True)
    remote_media(r, stage, entry, public=False)
    require(file_hash(local) == entry['sha256'], 'Local media changed during upload')
    r.sftp.chmod(stage, 0o644)
    remote_media(r, stage, entry)
    r.directory(MEDIA)
    require(D.absent(r, target), 'Media destination appeared during upload')
    r.sftp.rename(stage, target)  # Standard rename must refuse an existing target.
    stages.pop(stage)
    remote_media(r, target, entry)


def module_owned(r, blob):
    r.directory(D.DIR)
    require(r.read(TARGET) == blob, 'Foreign module retained')
    info = r.info(TARGET)
    require(stat.S_IMODE(info.st_mode) == 0o644, 'Module metadata drift')


def publish_module(r, blob):
    require(D.absent(r, TARGET), 'Module destination already exists')
    stage = TARGET + '.' + uuid.uuid4().hex + '.tmp'
    try:
        r.write_new(stage, blob)
        r.lint(stage)
        r.sftp.chmod(stage, 0o644)
        require(r.read(stage) == blob, 'Staged module drift')
        r.directory(D.DIR)
        require(D.absent(r, TARGET), 'Module appeared during staging')
        r.sftp.rename(stage, TARGET)
        module_owned(r, blob)
        r.lint(TARGET)
    finally:
        U.clean_stage(r, stage, blob)


class OwnedRoot(HTMLParser):
    """Extract one exact owned subtree without serializing the native document."""
    VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'}

    def __init__(self, text, attribute, value):
        super().__init__(convert_charrefs=True)
        self.text, self.attribute, self.value = text, attribute, value
        self.offsets = [0]
        for match in re.finditer('\n', text):
            self.offsets.append(match.end())
        self.depth = 0
        self.starts = []
        self.fragments = []
        self.feed(text)
        require(self.depth == 0 and len(self.fragments) == 1, 'Unique complete owned subtree required')

    def position(self):
        line, column = self.getpos()
        return self.offsets[line - 1] + column

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        match = self.value in a.get('class', '').split() if self.attribute == 'class' else a.get(self.attribute) == self.value
        if match:
            require(self.depth == 0, 'Nested duplicate owned root')
            self.starts.append(self.position())
            self.depth = 1
        elif self.depth and tag not in self.VOID:
            self.depth += 1

    def handle_startendtag(self, tag, attrs):
        if tag not in self.VOID and self.depth:
            self.handle_starttag(tag, attrs)
            self.handle_endtag(tag)

    def handle_endtag(self, tag):
        if self.depth and tag not in self.VOID:
            self.depth -= 1
            if self.depth == 0:
                end = self.text.find('>', self.position())
                require(end >= 0, 'Incomplete owned end tag')
                self.fragments.append(self.text[self.starts[-1]:end + 1])


def owned(text, attribute, value):
    return OwnedRoot(text, attribute, value).fragments[0]


def journal_assert(text, baseline, active):
    page, previous = D.Page(text), D.Page(baseline)
    require(page.form == previous.form, 'Journal native form changed')
    require(page.canonical == previous.canonical == [JOURNAL], 'Journal canonical changed')
    previous_ids, current_ids = Counter(previous.ids), Counter(page.ids)
    for name, count in previous_ids.items():
        require(current_ids[name] == count, 'Journal native ID count changed: ' + name)
    require(page.ids.count(ROOT_ID) == int(active), 'Journal construction root activation mismatch')
    for marker in ('data-harmat-construction-september="1"', 'harmat-build-log-list'):
        require(text.count(marker) == baseline.count(marker) and marker in text, 'Journal archive marker changed')
    if active:
        fragment = owned(text, 'id', ROOT_ID)
        first, second = fragment.find('2026-10-13'), fragment.find('2026-10-12')
        require(0 <= first < second, 'Journal latest date order incorrect')
        require(text.index(fragment) < text.index('data-harmat-construction-september="1"'), 'New journal section must precede archive')
    # Description/OG description intentionally change; native canonical/forms/IDs do not.
    return {'native_form_unchanged': True, 'native_id_counts_unchanged': True,
            'canonical_unchanged': True, 'archive_markers_preserved': True, 'active': active}


def media_http(name, entry):
    url = MEDIA_URL + name
    mime = 'video/mp4' if name.endswith('.mp4') else 'image/jpeg'
    request = urllib.request.Request(url, method='HEAD', headers={'User-Agent': 'HarmatOfferSafety/1.0'})
    with urllib.request.urlopen(request, timeout=30) as response:
        require(response.status == 200 and response.geturl() == url, 'Media HEAD identity failed')
        require(response.headers.get_content_type() == mime
                and response.headers.get('Content-Length') == str(entry['bytes']), 'Media HEAD MIME/size failed')
    if name.endswith('.mp4'):
        request = urllib.request.Request(url, headers={'Range': 'bytes=0-1023', 'User-Agent': 'HarmatOfferSafety/1.0'})
        with urllib.request.urlopen(request, timeout=30) as response:
            require(response.status == 206 and response.geturl() == url, 'Video range not supported')
            require(response.headers.get_content_type() == mime
                    and response.headers.get('Content-Range') == 'bytes 0-1023/' + str(entry['bytes']), 'Video Content-Range mismatch')
            require(len(response.read(1025)) == 1024, 'Video bounded range size mismatch')
    return {'head': True, 'range': name.endswith('.mp4')}


def public(r, release, home_baseline, journal_baseline, active=True):
    proof = D.public_proof(r, release['files'], release['hashes'], home_baseline)
    home, mime = D.get(D.URL)
    require('text/html' in mime, 'Home MIME mismatch')
    D.assert_home(home.decode('utf-8'), home_baseline)
    if active:
        fragment = owned(home.decode('utf-8'), 'class', 'hhf-journal')
        require('datetime="2026-10-13"' in fragment and MEDIA_URL + BASENAMES[0] + '.jpg' in fragment,
                'Homepage latest journal date/poster missing')
        require(JOURNAL + '#construction-2026-10-13' in fragment, 'Homepage latest journal deeplink missing')
        require(MEDIA_URL + BASENAMES[0] + '.mp4' in fragment and 'data-hcu-home="1"' in fragment,
                'Homepage latest journal player missing')
        require('2026. szeptember 30' not in fragment and 'okt\u00f3ber 2-i' not in fragment,
                'Stale homepage September teaser retained')
    journal, mime = D.get(JOURNAL)
    require('text/html' in mime, 'Journal MIME mismatch')
    proof['journal'] = journal_assert(journal.decode('utf-8'), journal_baseline, active)
    if active:
        r.directory(MEDIA)
        proof['media'] = {}
        for name, entry in release['release']['media'].items():
            remote_media(r, MEDIA + '/' + name, entry)
            proof['media'][name] = media_http(name, entry)
    return proof


def cleanup_media(r, entries, paths=None):
    retained = []
    if D.absent(r, MEDIA):
        return retained
    r.directory(MEDIA)
    for path, entry in (paths if paths is not None else {MEDIA + '/' + n: e for n, e in entries.items()}).items():
        if D.absent(r, path):
            continue
        try:
            info = r.info(path)
            remote_media(r, path, entry, public=stat.S_IMODE(info.st_mode) == 0o644)
            # Final ownership check immediately precedes removal, never download full media.
            remote_media(r, path, entry, public=stat.S_IMODE(info.st_mode) == 0o644)
            r.sftp.remove(path)
        except Exception:
            retained.append(path)
    if paths is None:
        try:
            r.sftp.rmdir(MEDIA)  # Never recursive; foreign/partial files keep this directory alive.
        except OSError:
            if MEDIA not in retained:
                retained.append(MEDIA)
    return retained


def recover(r, release, metadata, stages=None):
    # Home may depend on the module; restore home first, then remove only our module.
    current = r.read(HOME)
    if current == release['original']:
        U.target(r, release['original'], metadata)
    else:
        require(current == release['blobs'][D.MAIN], 'Foreign controller retained; manual recovery required')
        U.replace(r, release['blobs'][D.MAIN], release['original'], metadata)
    if not D.absent(r, TARGET):
        module_owned(r, release['blobs'][MODULE])
        r.remove_owned(TARGET, release['blobs'][MODULE])
    retained = cleanup_media(r, release['release']['media'], stages) if stages else []
    retained += cleanup_media(r, release['release']['media'])
    r.purge()
    return sorted(set(retained))


def fixture(r, backup):
    value = json.loads(r.run(H.PHP + ' ' + shlex.quote(backup + '/' + FIXTURE)
                             + ' ' + shlex.quote(backup + '/' + MODULE)))
    require(value.get('status') == 'PASS', 'Construction PHP fixture failed')
    return value


def deploy(release, prepare=False):
    r = H.Remote()
    locked = started = False
    stages = {}
    backup = PREFIX + uuid.uuid4().hex
    try:
        directories(r)
        r.mkdir(D.LOCK)
        locked = True
        metadata = U.target(r, release['original'])
        require(D.absent(r, TARGET) and D.absent(r, MEDIA), 'New module/media paths must be absent')
        before = protected(r, release)
        home, mime = D.get(D.URL)
        require('text/html' in mime, 'Home baseline MIME')
        D.assert_home(home.decode('utf-8'), home.decode('utf-8'))
        journal, mime = D.get(JOURNAL)
        require('text/html' in mime, 'Journal baseline MIME')
        journal_assert(journal.decode('utf-8'), journal.decode('utf-8'), False)
        r.mkdir(backup)
        for name, data in {'original.php': release['original'], 'candidate-home.php': release['blobs'][D.MAIN],
                           MODULE: release['blobs'][MODULE], FIXTURE: release['blobs'][FIXTURE],
                           D.CHECKER: release['blobs'][D.CHECKER], 'public-home-original.html': home,
                           'public-journal-original.html': journal}.items():
            r.write_new(backup + '/' + name, data)
            if name.endswith('.php'):
                r.lint(backup + '/' + name)
        saved = {'release': release['release'], 'digest': release['digest'], 'metadata': list(metadata), 'before': before,
                 'public_baselines': {'home': sha(home), 'journal': sha(journal)}}
        D.write_json(r, backup + '/manifest.json', saved)
        D.write_json(r, backup + '/media-manifest.json', {'files': release['release']['media']})
        pure = fixture(r, backup)
        filters = D.validate_native(r, backup)
        require(protected(r, release) == before, 'Protected state drift before activation')
        U.target(r, release['original'], metadata)
        require(D.absent(r, TARGET) and D.absent(r, MEDIA), 'New path appeared before activation')
        D.write_json(r, backup + '/preflight-proof.json', {'pure': pure, 'filters': filters})
        print(json.dumps({'status': 'PRIVATE_BACKUP_READY', 'backup': backup, 'aggregate': release['digest']}), flush=True)
        if prepare:
            print(json.dumps({'status': 'PREFLIGHT_ONLY_NO_ACTIVATION', 'backup': backup}), flush=True)
            return
        r.sftp.mkdir(MEDIA, 0o755)
        started = True
        r.sftp.chmod(MEDIA, 0o755)
        r.directory(MEDIA)
        for name, entry in release['release']['media'].items():
            upload_media(r, release['media_local'] / name, name, entry, stages)
        for name, entry in release['release']['media'].items():
            remote_media(r, MEDIA + '/' + name, entry)
        require(protected(r, release) == before, 'Protected state drift after media staging')
        U.target(r, release['original'], metadata)
        publish_module(r, release['blobs'][MODULE])
        U.replace(r, release['original'], release['blobs'][D.MAIN], metadata)
        r.purge()
        proof = public(r, release, home.decode('utf-8'), journal.decode('utf-8'))
        filters = D.validate_native(r, backup)
        U.target(r, release['blobs'][D.MAIN], metadata)
        module_owned(r, release['blobs'][MODULE])
        require(protected(r, release) == before, 'Protected source/data drift; never restore business data')
        D.write_json(r, backup + '/release-proof.json', {'public': proof, 'filters': filters})
        print(json.dumps({'status': 'RELEASED_CONSTRUCTION_UPDATES', 'backup': backup, 'aggregate': release['digest'], 'proof': proof}), flush=True)
    except BaseException:
        if started:
            retained = recover(r, release, metadata, stages)
            print(json.dumps({'status': 'OWNED_SOURCE_ROLLED_BACK', 'backup': backup, 'retained_inert_media': retained}), flush=True)
        raise
    finally:
        try:
            if locked:
                r.sftp.rmdir(D.LOCK)
        finally:
            r.close()


def verify_or_rollback(backup, release, reverse=False):
    require(isinstance(backup, str) and re.fullmatch(re.escape(PREFIX) + '[a-f0-9]{32}', backup), 'Exact private backup path required')
    r = H.Remote()
    locked = False
    try:
        directories(r)
        r.mkdir(D.LOCK)
        locked = True
        r.directory(backup, private=True)
        saved = json.loads(r.read(backup + '/manifest.json', private=True))
        require(saved.get('release') == release['release'] and saved.get('digest') == release['digest'], 'Saved release pins differ')
        metadata = saved.get('metadata')
        require(isinstance(metadata, list) and len(metadata) == 3 and all(type(i) is int and i >= 0 for i in metadata)
                and 0 < metadata[0] <= 0o777 and H.safe_public_mode(*metadata, getattr(r, 'private_group_verified', False))
                and metadata[0] & 0o400, 'Invalid saved metadata')
        for name, value in {'original.php': release['original'], 'candidate-home.php': release['blobs'][D.MAIN],
                            MODULE: release['blobs'][MODULE], FIXTURE: release['blobs'][FIXTURE], D.CHECKER: release['blobs'][D.CHECKER]}.items():
            require(r.read(backup + '/' + name, private=True) == value, 'Saved raw code differs')
        require(json.loads(r.read(backup + '/media-manifest.json', private=True)) == {'files': release['release']['media']}, 'Saved media manifest differs')
        home = r.read(backup + '/public-home-original.html', private=True).decode('utf-8')
        journal = r.read(backup + '/public-journal-original.html', private=True).decode('utf-8')
        require(saved.get('public_baselines') == {'home': sha(home.encode()), 'journal': sha(journal.encode())}, 'Saved public baseline drift')
        D.assert_home(home, home)
        journal_assert(journal, journal, False)
        require(protected(r, release) == saved['before'], 'Protected state differs before recovery; no business data restored')
        U.target(r, release['blobs'][D.MAIN], metadata)
        module_owned(r, release['blobs'][MODULE])
        retained = []
        if reverse:
            retained = recover(r, release, metadata)
        else:
            r.lint(HOME)
            r.lint(TARGET)
            fixture(r, backup)
        proof = public(r, release, home, journal, not reverse)
        filters = D.validate_native(r, backup)
        U.target(r, release['original'] if reverse else release['blobs'][D.MAIN], metadata)
        if reverse:
            require(D.absent(r, TARGET), 'Module appeared after rollback')
        else:
            module_owned(r, release['blobs'][MODULE])
        require(protected(r, release) == saved['before'], 'Protected state drift; no business data restored')
        print(json.dumps({'status': 'ROLLED_BACK_CONSTRUCTION_UPDATES' if reverse else 'VERIFIED_CONSTRUCTION_UPDATES',
                          'backup': backup, 'proof': proof, 'filters': filters, 'retained_inert_media': retained}), flush=True)
    finally:
        try:
            if locked:
                r.sftp.rmdir(D.LOCK)
        finally:
            r.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument('--prepare', action='store_true')
    modes.add_argument('--deploy', action='store_true')
    modes.add_argument('--verify')
    modes.add_argument('--rollback')
    parser.add_argument('--expect', help='Exact aggregate from offline invocation')
    args = parser.parse_args()
    release = local_release()
    if not (args.prepare or args.deploy or args.verify or args.rollback):
        print(json.dumps({'mode': 'OFFLINE_NO_WRITES', 'aggregate': release['digest'], 'release': release['release']}, indent=2))
        return
    require(args.expect == release['digest'], 'Exact reviewed aggregate --expect required')
    if args.prepare or args.deploy:
        deploy(release, args.prepare)
    else:
        verify_or_rollback(args.verify or args.rollback, release, bool(args.rollback))


if __name__ == '__main__':
    main()
