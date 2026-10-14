"""One-source encoding repair. Default/check/self-test are offline; no form is sent.

Parent review is required before --deploy --allow-live-write. --verify BACKUP
only checks files/lint (no WP boot). --rollback BACKUP --allow-live-write
restores pinned raw bytes only while the deployed hash still owns the target.
Public smoke and protected DB/source-parity audits remain the parent's job.
"""
import argparse
import hashlib
import json
import re
import shlex
import stat
import subprocess
import uuid
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = '716167cc85d991ad75834235bc358d090f8643cb'
SOURCE = 'wp-mu-plugins/zz-harmat-public-offer-integrity.php'
LIVE = '/home/harmath2/public_html'
TARGET = LIVE + '/wp-content/mu-plugins/zz-harmat-public-offer-integrity.php'
DIRECTORY = TARGET.rsplit('/', 1)[0]
BACKUPS = '/home/harmath2/codex-backups'
PREFIX = BACKUPS + '/appointment-google-source-'
LOCK = BACKUPS + '/.appointment-google-source.lock'
PHP = '/opt/alt/php83/usr/bin/php'
WP = PHP + ' /usr/local/bin/wp --path=' + LIVE
ORIGINAL_SHA = 'bd869d8fb175c4c25f7a42a6894e68fefb9389274eabdaef70d7f2dd0db7f565'
PATCHED_SHA = '9c5864a6a38581d06f0f82bf64ec3142b014f0443cab16f7c9a01ff4ebc33ed3'
OLD = b"        'Google keres&eacute;s',"
NEW = b"        harmat_offer_integrity_message('Google keres&eacute;s'),"
CACHE_CODE = '''if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('CLI only'); }
if (function_exists('wp_cache_clear_cache')) { wp_cache_clear_cache(); }
if (!wp_cache_flush()) { throw new RuntimeException('Object cache flush failed'); }
echo 'APPOINTMENT_SOURCE_CACHE_PURGED';'''


class GuardError(RuntimeError):
    pass


def require(condition, message):
    if not condition:
        raise GuardError(message)


def sha(data):
    return hashlib.sha256(data).hexdigest()


def safe_public_mode(mode, uid=None, gid=None, private_group=False):
    if not mode & 0o022:
        return True
    return bool(private_group and uid == 1608 and gid == 1611 and not mode & 0o002)


def patch(original):
    require(sha(original) == ORIGINAL_SHA, 'Pinned original hash mismatch')
    require(b'\r' not in original, 'Live original must retain raw LF bytes')
    require(original.count(OLD) == 1 and NEW not in original, 'Replacement not unique')
    candidate = original.replace(OLD, NEW, 1)
    require(sha(candidate) == PATCHED_SHA, 'Pinned candidate hash mismatch')
    return candidate


def local_check(guard_head=True):
    head = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT).decode().strip()
    if guard_head:
        require(head == BASE, 'Reviewed Git baseline changed')
    original = subprocess.check_output(['git', 'show', BASE + ':' + SOURCE], cwd=ROOT)
    candidate = patch(original)
    # Checkout CRLF is accepted locally, never used to transform live bytes.
    require((ROOT / SOURCE).read_bytes().replace(b'\r\n', b'\n') == candidate,
            'Local source is not the exact reviewed one-line patch')
    return original, candidate


def note(event, **values):
    print(json.dumps({'event': event, **values}, sort_keys=True), flush=True)


class Remote:
    def __init__(self):
        import paramiko
        self.client = paramiko.SSHClient()
        try:
            self.client.load_host_keys(str(Path.home() / '.ssh/known_hosts'))
            self.client.set_missing_host_key_policy(paramiko.RejectPolicy())
            self.client.connect(
                '185.111.89.244', username='harmath2',
                key_filename=str(Path.home() / 'Downloads/harmat'),
                passphrase=(Path.home() / '.ssh/harmat_key_pass.txt').read_text(
                    encoding='utf-8').strip(),
                look_for_keys=False, allow_agent=False, timeout=30,
                auth_timeout=30, banner_timeout=30)
            self.sftp = self.client.open_sftp()
            self.sftp.get_channel().settimeout(60)
            self.private_group_verified = False
            self.verify_private_group()
        except Exception:
            self.client.close()
            raise

    def close(self):
        try:
            self.sftp.close()
        finally:
            self.client.close()

    def run(self, command):
        stdin, stdout, stderr = self.client.exec_command(command, timeout=120)
        stdin.close()
        body, error = stdout.read(), stderr.read()
        require(stdout.channel.recv_exit_status() == 0 and not error,
                'Remote command failed; no command output or credentials logged')
        return body.decode('utf-8', 'strict').strip()

    def lint(self, path):
        body = self.run(PHP + ' -l ' + shlex.quote(path))
        require('No syntax errors detected' in body, 'PHP CLI lint not confirmed')

    def verify_private_group(self):
        # Existing 0775/0664 permissions are safe only for this proved sole-account group.
        require(self.run('id -u') == '1608' and self.run('id -g') == '1611',
                'Unexpected deployment account identity')
        require(self.run('getent group 1611') == 'harmath2:x:1611:',
                'Group is shared or its identity changed')
        command = "getent passwd | awk -F: '$4 == 1611 && $3 != 1608 { n++ } END { print n+0 }'"
        require(self.run(command) == '0', 'Group is another account primary group')
        self.private_group_verified = True

    def purge(self):
        body = self.run(WP + ' eval ' + shlex.quote(CACHE_CODE))
        require(body == 'APPOINTMENT_SOURCE_CACHE_PURGED', 'Cache purge not confirmed')

    def info(self, path):
        return self.sftp.lstat(path)

    def directory(self, path, private=False):
        info = self.info(path)
        require(stat.S_ISDIR(info.st_mode) and self.sftp.normalize(path) == path,
                'Directory is missing, linked, or outside the exact path')
        if private:
            require(stat.S_IMODE(info.st_mode) == 0o700, 'Private directory must be 0700')
        else:
            require(safe_public_mode(stat.S_IMODE(info.st_mode), getattr(info, 'st_uid', None),
                                     getattr(info, 'st_gid', None),
                                     getattr(self, 'private_group_verified', False)),
                    'Directory is world writable or group is not proved private')

    def read(self, path, private=False):
        before = self.info(path)
        require(stat.S_ISREG(before.st_mode), 'Expected a regular non-symlink file')
        if private:
            require(stat.S_IMODE(before.st_mode) == 0o600, 'Private file must be 0600')
        with self.sftp.open(path, 'rb') as handle:
            data = handle.read()
        require(self.identity(before) == self.identity(self.info(path)),
                'File changed during read')
        return data

    @staticmethod
    def identity(info):
        return (info.st_mode, info.st_uid, info.st_gid, info.st_size, info.st_mtime)

    def mkdir(self, path):
        self.sftp.mkdir(path, 0o700)
        self.sftp.chmod(path, 0o700)
        self.directory(path, private=True)

    def write_new(self, path, data):
        # SFTP open has no public creation-mode argument. Create exclusively with
        # PHP/umask 077 first, so live-directory stages are private from birth.
        code = "umask(0077); $f=fopen($argv[1], 'x+b'); if (!$f) { exit(1); } fclose($f);"
        self.run(PHP + ' -r ' + shlex.quote(code) + ' -- ' + shlex.quote(path))
        require(self.read(path, private=True) == b'', 'Exclusive private creation failed')
        with self.sftp.open(path, 'r+b') as handle:
            handle.write(data)
            handle.flush()
        self.sftp.chmod(path, 0o600)
        require(self.read(path, private=True) == data, 'New private bytes not verified')

    def replace(self, stage, expected_sha, new_sha, metadata):
        require(sha(self.read(stage)) == new_sha, 'Staged hash changed')
        require(sha(self.read(TARGET)) == expected_sha, 'Target ownership hash changed')
        info = self.info(TARGET)
        require((stat.S_IMODE(info.st_mode), info.st_uid, info.st_gid) == metadata,
                'Target metadata changed')
        self.directory(DIRECTORY)
        # Final recheck immediately precedes same-directory atomic POSIX rename.
        # This lock coordinates this tool only; it cannot lock unrelated editors.
        require(sha(self.read(TARGET)) == expected_sha, 'Target changed before activation')
        self.sftp.posix_rename(stage, TARGET)

    def stage_metadata(self, stage, metadata):
        mode, uid, gid = metadata
        info = self.info(stage)
        if (info.st_uid, info.st_gid) != (uid, gid):
            self.sftp.chown(stage, uid, gid)
        self.sftp.chmod(stage, mode)
        info = self.info(stage)
        require((stat.S_IMODE(info.st_mode), info.st_uid, info.st_gid) == metadata,
                'Stage cannot preserve formal file metadata')

    def remove_owned(self, path, expected):
        try:
            require(self.read(path) == expected, 'Owned temporary bytes changed; retaining file')
        except FileNotFoundError:
            return
        self.sftp.remove(path)

    def rmdir(self, path):
        self.sftp.rmdir(path)


class Operation:
    def __init__(self, remote):
        self.remote = remote

    def target(self, digest, metadata=None):
        self.remote.directory(DIRECTORY)
        data = self.remote.read(TARGET)
        require(sha(data) == digest, 'Formal file does not have the expected ownership hash')
        info = self.remote.info(TARGET)
        found = (stat.S_IMODE(info.st_mode), info.st_uid, info.st_gid)
        require(safe_public_mode(*found, getattr(self.remote, 'private_group_verified', False))
                and found[0] & 0o400, 'Unsafe target permissions')
        if metadata is not None:
            require(found == tuple(metadata), 'Formal file metadata drift')
        return data, found

    def backup(self, original, metadata):
        self.remote.directory(BACKUPS)
        path = PREFIX + uuid.uuid4().hex
        self.remote.mkdir(path)
        manifest = {
            'schema': 1, 'baseline': BASE, 'backup': path, 'target': TARGET,
            'originalSHA256': ORIGINAL_SHA, 'patchedSHA256': PATCHED_SHA,
            'metadata': list(metadata), 'createdUTC': datetime.now(timezone.utc).isoformat(),
        }
        self.remote.write_new(path + '/original.php', original)
        self.remote.write_new(path + '/original.sha256', (ORIGINAL_SHA + '\n').encode('ascii'))
        self.remote.write_new(path + '/manifest.json', json.dumps(
            manifest, indent=2, sort_keys=True).encode('ascii') + b'\n')
        self.validate_backup(path)
        note('PRIVATE_BACKUP_VERIFIED', backup=path, originalSHA256=ORIGINAL_SHA)
        return path

    def validate_backup(self, path):
        require(isinstance(path, str) and re.fullmatch(re.escape(PREFIX) + '[0-9a-f]{32}', path),
                'Exact unique private backup path required')
        self.remote.directory(BACKUPS)
        self.remote.directory(path, private=True)
        manifest = json.loads(self.remote.read(path + '/manifest.json', private=True))
        expected = {'schema': 1, 'baseline': BASE, 'backup': path, 'target': TARGET,
                    'originalSHA256': ORIGINAL_SHA, 'patchedSHA256': PATCHED_SHA}
        require(all(manifest.get(k) == v for k, v in expected.items()), 'Backup manifest mismatch')
        metadata = manifest.get('metadata')
        require(isinstance(metadata, list) and len(metadata) == 3
                and all(type(v) is int and v >= 0 for v in metadata)
                and 0 < metadata[0] <= 0o777
                and safe_public_mode(*metadata, getattr(self.remote, 'private_group_verified', False))
                and metadata[0] & 0o400, 'Backup metadata invalid')
        original = self.remote.read(path + '/original.php', private=True)
        patch(original)
        require(self.remote.read(path + '/original.sha256', private=True)
                == (ORIGINAL_SHA + '\n').encode('ascii'), 'Backup digest file mismatch')
        return original, tuple(metadata)

    def install(self, backup, restoring=False):
        original, metadata = self.validate_backup(backup)
        expected = PATCHED_SHA if restoring else ORIGINAL_SHA
        candidate = original if restoring else patch(original)
        self.target(expected, metadata)
        stage = DIRECTORY + '/.appointment-google-source-' + uuid.uuid4().hex + '.tmp'
        created = False
        note('OWNED_STAGE_PATH', path=stage)
        try:
            self.remote.write_new(stage, candidate)
            created = True
            self.remote.lint(stage)
            self.remote.stage_metadata(stage, metadata)
            self.validate_backup(backup)
            self.target(expected, metadata)
            self.remote.replace(stage, expected, sha(candidate), metadata)
            self.target(sha(candidate), metadata)
            self.remote.lint(TARGET)
            self.remote.purge()
            self.target(sha(candidate), metadata)
        finally:
            if created:
                self.remote.remove_owned(stage, candidate)

    def verify(self, backup):
        _, metadata = self.validate_backup(backup)
        self.target(PATCHED_SHA, metadata)
        self.remote.lint(TARGET)
        self.target(PATCHED_SHA, metadata)
        note('VERIFIED_SOURCE_ONLY', backup=backup, sha256=PATCHED_SHA)

    def mutate(self, mode, backup=None):
        self.remote.directory(BACKUPS)
        self.remote.mkdir(LOCK)
        owner = uuid.uuid4().hex.encode('ascii')
        owner_file = LOCK + '/owner'
        try:
            self.remote.write_new(owner_file, owner)
            if mode == 'deploy':
                original, metadata = self.target(ORIGINAL_SHA)
                patch(original)
                backup = self.backup(original, metadata)
                try:
                    self.install(backup)
                except Exception:
                    # A failed/uncertain rename is recoverable only if our bytes own TARGET.
                    current = sha(self.remote.read(TARGET))
                    if current == PATCHED_SHA:
                        note('GUARDED_RECOVERY_START', backup=backup)
                        self.install(backup, restoring=True)
                        note('GUARDED_RECOVERY_RESTORED', backup=backup, sha256=ORIGINAL_SHA)
                    elif current != ORIGINAL_SHA:
                        note('RECOVERY_BLOCKED_USER_CHANGE', backup=backup)
                    raise
            else:
                self.install(backup, restoring=True)
            note('DEPLOYED' if mode == 'deploy' else 'ROLLED_BACK', backup=backup,
                 sha256=PATCHED_SHA if mode == 'deploy' else ORIGINAL_SHA)
        finally:
            self.remote.directory(LOCK, private=True)
            require(self.remote.read(owner_file, private=True) == owner,
                    'Lock owner changed; retaining unexpected lock')
            self.remote.remove_owned(owner_file, owner)
            self.remote.rmdir(LOCK)
            note('OWNED_LOCK_REMOVED')


def self_test(original, candidate):
    """In-memory deployment/failure tests; never creates an SSH client or local files."""
    import io
    from contextlib import redirect_stdout
    from types import SimpleNamespace

    class Fake:
        identity = staticmethod(Remote.identity)
        def __init__(self):
            self.files = {TARGET: original}
            self.modes = {TARGET: 0o644}
            self.dirs = {BACKUPS, DIRECTORY}
            self.dir_modes = {BACKUPS: 0o755, DIRECTORY: 0o755}
            self.replacements = 0
            self.purges = 0
            self.lints = 0
            self.fail = None
        def info(self, path):
            require(path in self.files, 'Fake file missing')
            return SimpleNamespace(st_mode=stat.S_IFREG | self.modes[path],
                                   st_uid=1001, st_gid=1001, st_size=len(self.files[path]), st_mtime=1)
        def directory(self, path, private=False):
            require(path in self.dirs, 'Fake directory missing')
            require(not private or self.dir_modes[path] == 0o700, 'Fake private directory mode')
        def mkdir(self, path):
            require(path not in self.dirs, 'Lock/directory already exists')
            self.dirs.add(path)
            self.dir_modes[path] = 0o700
        def read(self, path, private=False):
            if path not in self.files:
                raise FileNotFoundError(path)
            require(not private or self.modes[path] == 0o600, 'Fake private file mode')
            return self.files[path]
        def write_new(self, path, data):
            require(path not in self.files, 'Exclusive write collision')
            self.files[path], self.modes[path] = data, 0o600
        def lint(self, path):
            self.lints += 1
            if self.fail == 'stage_lint' and path != TARGET:
                raise GuardError('Injected staging lint failure')
            if self.fail == 'formal_lint' and path == TARGET:
                self.fail = None
                raise GuardError('Injected formal lint failure')
        def stage_metadata(self, path, metadata):
            self.modes[path] = metadata[0]
        def replace(self, stage, expected_sha, new_sha, metadata):
            if self.fail == 'race':
                self.files[TARGET] = b'user change'
            require(sha(self.files[TARGET]) == expected_sha, 'Injected ownership guard')
            require(sha(self.files[stage]) == new_sha, 'Fake staged hash mismatch')
            self.files[TARGET] = self.files.pop(stage)
            self.modes[TARGET] = self.modes.pop(stage)
            self.replacements += 1
            if self.fail == 'uncertain_rename':
                self.fail = None
                raise GuardError('Injected lost rename response')
        def purge(self):
            self.purges += 1
            if self.fail == 'purge':
                self.fail = None
                raise GuardError('Injected cache failure')
            if self.fail == 'post_user_change':
                self.files[TARGET] = b'user change'
                raise GuardError('Injected post-install editor')
        def remove_owned(self, path, data):
            if path in self.files:
                require(self.files[path] == data, 'Fake stage ownership changed')
                del self.files[path]
                del self.modes[path]
        def rmdir(self, path):
            self.dirs.remove(path)

    cases = []
    def test(name, callback):
        with redirect_stdout(io.StringIO()):
            callback()
        cases.append(name)
    for name, mode, uid, gid, trusted, expected in [
            ('private_group_backup_root', 0o775, 1608, 1611, True, True),
            ('private_group_target', 0o664, 1608, 1611, True, True),
            ('unverified_group_denied', 0o775, 1608, 1611, False, False),
            ('foreign_group_denied', 0o664, 1608, 1612, True, False),
            ('world_write_denied', 0o777, 1608, 1611, True, False),
            ('ordinary_readable_mode', 0o755, 1001, 1001, False, True)]:
        def verify_mode(m=mode, u=uid, g=gid, t=trusted, e=expected):
            assert safe_public_mode(m, u, g, t) is e
        test(name, verify_mode)
    def rejected(callback):
        try:
            callback()
        except (GuardError, ValueError, FileNotFoundError):
            return
        raise AssertionError('Expected refusal')
    def released(fake):
        assert LOCK not in fake.dirs
        assert not any(p.endswith('.tmp') for p in fake.files)
    def happy():
        fake = Fake()
        op = Operation(fake)
        op.mutate('deploy')
        backup = next(p for p in fake.dirs if p.startswith(PREFIX))
        assert fake.files[TARGET] == candidate and fake.replacements == 1
        before = (dict(fake.files), fake.purges, fake.replacements)
        op.verify(backup)
        assert before == (fake.files, fake.purges, fake.replacements)
        op.mutate('rollback', backup)
        assert fake.files[TARGET] == original and fake.replacements == 2
        assert fake.purges == 2
        released(fake)
    def failure(kind, result, swaps):
        fake = Fake()
        fake.fail = kind
        rejected(lambda: Operation(fake).mutate('deploy'))
        assert fake.files[TARGET] == result and fake.replacements == swaps
        released(fake)
    def invalid_backup(kind):
        fake = Fake()
        op = Operation(fake)
        backup = op.backup(original, (0o644, 1001, 1001))
        if kind == 'raw':
            fake.files[backup + '/original.php'] += b'x'
        elif kind == 'digest':
            fake.files[backup + '/original.sha256'] = b'bad'
        elif kind == 'mode':
            fake.modes[backup + '/original.php'] = 0o644
        elif kind == 'directory':
            fake.dir_modes[backup] = 0o755
        else:
            m = json.loads(fake.files[backup + '/manifest.json'])
            m['target'] += '.other'
            fake.files[backup + '/manifest.json'] = json.dumps(m).encode()
        rejected(lambda: op.install(backup))
        assert fake.replacements == 0 and fake.files[TARGET] == original
    def rollback_drift():
        fake = Fake()
        op = Operation(fake)
        backup = op.backup(original, (0o644, 1001, 1001))
        fake.files[TARGET] = b'user change'
        rejected(lambda: op.mutate('rollback', backup))
        assert fake.files[TARGET] == b'user change' and fake.replacements == 0
        released(fake)
    def lock_collision():
        fake = Fake()
        fake.mkdir(LOCK)
        rejected(lambda: Operation(fake).mutate('deploy'))
        assert LOCK in fake.dirs and fake.replacements == 0
    def drift():
        fake = Fake()
        fake.files[TARGET] += b'x'
        rejected(lambda: Operation(fake).mutate('deploy'))
        assert fake.replacements == 0
        released(fake)

    test('deploy_verify_readonly_rollback_exact_bytes', happy)
    for kind, result, swaps in [('stage_lint', original, 0), ('race', b'user change', 0),
                                ('purge', original, 2), ('formal_lint', original, 2),
                                ('uncertain_rename', original, 2),
                                ('post_user_change', b'user change', 1)]:
        test(kind, lambda k=kind, r=result, s=swaps: failure(k, r, s))
    for kind in ['raw', 'digest', 'mode', 'directory', 'manifest']:
        test('backup_' + kind, lambda k=kind: invalid_backup(k))
    test('rollback_refuses_user_change', rollback_drift)
    test('existing_lock_preserved', lock_collision)
    test('live_drift_no_install', drift)
    for name, bad in [('crlf_original', original.replace(b'\n', b'\r\n')),
                      ('duplicate_match', original + OLD), ('already_patched', candidate)]:
        test(name, lambda b=bad: rejected(lambda: patch(b)))
    for bad in [PREFIX + '../escape', PREFIX + 'a' * 32 + '/child', TARGET]:
        test('reject_backup_path_' + str(len(cases)),
             lambda p=bad: rejected(lambda: Operation(Fake()).validate_backup(p)))

    # Exercise the real Remote primitives over an in-memory SFTP transport too.
    class MemorySFTP:
        def __init__(self, fake):
            self.fake = fake
            self.link = None
            self.changed_read = False
        def lstat(self, path):
            if path in self.fake.dirs:
                return SimpleNamespace(st_mode=stat.S_IFDIR | self.fake.dir_modes[path])
            info = self.fake.info(path)
            if path == self.link:
                info.st_mode = stat.S_IFLNK | 0o777
            return info
        def normalize(self, path):
            return path + '/elsewhere' if path == self.link else path
        def open(self, path, mode):
            transport = self
            class Buffer(io.BytesIO):
                def close(self):
                    if mode == 'r+b':
                        transport.fake.files[path] = self.getvalue()
                    super().close()
            buffer = Buffer(self.fake.files[path])
            if self.changed_read:
                self.changed_read = False
                self.fake.files[path] += b'changed'
            return buffer
        def chmod(self, path, mode):
            self.fake.modes[path] = mode
        def chown(self, path, uid, gid):
            assert (uid, gid) == (1001, 1001)
        def remove(self, path):
            del self.fake.files[path]
            del self.fake.modes[path]
        def posix_rename(self, stage, target):
            self.fake.files[target] = self.fake.files.pop(stage)
            self.fake.modes[target] = self.fake.modes.pop(stage)
            self.fake.replacements += 1

    def low_level(kind):
        fake = Fake()
        remote = object.__new__(Remote)
        remote.sftp = MemorySFTP(fake)
        stage = DIRECTORY + '/.test.tmp'
        fake.write_new(stage, candidate)
        fake.modes[stage] = 0o644
        metadata = (0o644, 1001, 1001)
        if kind == 'replace_exact':
            remote.replace(stage, ORIGINAL_SHA, PATCHED_SHA, metadata)
            assert fake.files[TARGET] == candidate and fake.replacements == 1
        elif kind in ['stage_hash', 'target_hash', 'metadata']:
            if kind == 'stage_hash':
                fake.files[stage] += b'x'
            elif kind == 'target_hash':
                fake.files[TARGET] += b'x'
            else:
                fake.modes[TARGET] = 0o640
            rejected(lambda: remote.replace(stage, ORIGINAL_SHA, PATCHED_SHA, metadata))
            assert fake.replacements == 0
        elif kind == 'symlink_file':
            remote.sftp.link = TARGET
            rejected(lambda: remote.read(TARGET))
        elif kind == 'symlink_directory':
            remote.sftp.link = DIRECTORY
            rejected(lambda: remote.directory(DIRECTORY))
        elif kind == 'read_race':
            remote.sftp.changed_read = True
            rejected(lambda: remote.read(TARGET))
        elif kind == 'private_mode':
            rejected(lambda: remote.read(TARGET, private=True))
        elif kind == 'cleanup_drift':
            rejected(lambda: remote.remove_owned(stage, original))
            assert stage in fake.files
        elif kind == 'cleanup_exact':
            remote.remove_owned(stage, candidate)
            assert stage not in fake.files
        elif kind == 'preserve_metadata':
            remote.stage_metadata(stage, (0o640, 1001, 1001))
            assert fake.modes[stage] == 0o640
        else:
            commands = []
            new_path = DIRECTORY + '/.exclusive.tmp'
            def create(command):
                commands.append(command)
                parts = shlex.split(command)
                assert parts[:2] == [PHP, '-r'] and parts[3] == '--'
                assert "umask(0077)" in parts[2] and "'x+b'" in parts[2]
                fake.write_new(parts[4], b'')
                return ''
            remote.run = create
            remote.write_new(new_path, candidate)
            assert fake.files[new_path] == candidate and fake.modes[new_path] == 0o600
            rejected(lambda: remote.write_new(new_path, original))
            assert fake.files[new_path] == candidate and len(commands) == 2

    for kind in ['replace_exact', 'stage_hash', 'target_hash', 'metadata',
                 'symlink_file', 'symlink_directory', 'read_race', 'private_mode',
                 'cleanup_drift', 'cleanup_exact', 'preserve_metadata', 'exclusive_private_create']:
        test('remote_' + kind, lambda k=kind: low_level(k))
    note('OFFLINE_SELF_TEST_PASSED', count=len(cases), cases=cases)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument('--check', action='store_true', help='Offline default; check baseline and exact edit')
    modes.add_argument('--self-test', action='store_true', help='Offline in-memory failure-path tests')
    modes.add_argument('--deploy', action='store_true', help='Parent-reviewed live deployment')
    modes.add_argument('--verify', metavar='PRIVATE_BACKUP', help='Read-only remote file/lint verification')
    modes.add_argument('--rollback', metavar='PRIVATE_BACKUP', help='Guarded restoration from verified backup')
    parser.add_argument('--allow-live-write', action='store_true', help='Explicit gate for deploy/rollback only')
    args = parser.parse_args()
    changing = args.deploy or args.rollback is not None
    if bool(changing) != args.allow_live_write:
        parser.error('--allow-live-write is required only with --deploy or --rollback')
    remote = None
    try:
        if args.verify or args.rollback:
            require(re.fullmatch(re.escape(PREFIX) + '[0-9a-f]{32}', args.verify or args.rollback),
                    'Invalid private backup path; refusing even to connect')
        else:
            # Offline fixtures remain repeatable after the release commit; live writes retain the HEAD gate.
            original, candidate = local_check(guard_head=not args.self_test)
            note('LOCAL_EXACT_PATCH_PASSED', baseline=BASE,
                 originalSHA256=sha(original), patchedLFSHA256=sha(candidate))
            if args.self_test:
                self_test(original, candidate)
            if not args.deploy:
                return 0
        remote = Remote()
        operation = Operation(remote)
        if args.verify:
            operation.verify(args.verify)
        else:
            operation.mutate('deploy' if args.deploy else 'rollback', args.rollback)
        return 0
    except Exception as error:
        # Unexpected errors may contain connection details; print only their type.
        note('FAILED', error=str(error) if isinstance(error, GuardError) else type(error).__name__)
        return 1
    finally:
        if remote is not None:
            remote.close()


if __name__ == '__main__':
    raise SystemExit(main())
