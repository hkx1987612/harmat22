"""Offline transport/ownership tests. Real network access is forbidden."""
import contextlib
import copy
import importlib.util
import io
import json
from pathlib import Path
import shlex
import socket
import stat
import tempfile
import types
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('construction_transport_tests', HERE / '2026-10-18-deploy-construction-updates.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
REAL_PUBLIC = M.public


class FakeRemote:
    def __init__(self, release):
        self.files = {M.HOME: release['original'], M.D.DIR + '/' + M.PROGRESS: b'protected-progress'}
        self.files.update({M.D.DIR + '/' + n: release['blobs'][n] for n in release['files'] if n != M.D.MAIN})
        self.dirs = {M.H.BACKUPS, M.D.DIR}
        self.dirs.update(M.H.LIVE + s for s in ('', '/wp-content', '/wp-content/uploads', '/wp-content/uploads/2026', '/wp-content/uploads/2026/10'))
        self.modes = {p: 0o644 for p in self.files}
        self.modes.update({p: 0o755 for p in self.dirs})
        self.modes[M.H.BACKUPS] = 0o700
        self.events = []
        self.sftp = self
        self.fail = lambda *args: None
        self.closed = False
        self.private_group_verified = False

    def event(self, op, path):
        self.events.append((op, path))
        self.fail(op, path)

    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        mode = self.modes[path]
        return types.SimpleNamespace(st_mode=(stat.S_IFDIR if path in self.dirs else stat.S_IFREG) | mode,
                                     st_uid=1000, st_gid=1000, st_size=len(self.files.get(path, b'')), st_mtime=1)

    def directory(self, path, private=False):
        self.event('directory', path)
        if path not in self.dirs:
            raise FileNotFoundError(path)
        if private and self.modes[path] != 0o700:
            raise RuntimeError('private directory mode')

    def mkdir(self, path, mode=0o700):
        self.event('mkdir', path)
        if path in self.dirs or path in self.files:
            raise FileExistsError(path)
        self.dirs.add(path)
        self.modes[path] = mode

    def rmdir(self, path):
        self.event('rmdir', path)
        if any(p.startswith(path + '/') for p in self.files) or any(p.startswith(path + '/') for p in self.dirs):
            raise OSError('not empty')
        self.dirs.remove(path)
        self.modes.pop(path)

    def read(self, path, private=False):
        self.event('read', path)
        if path.startswith(M.MEDIA + '/') and self.files[path]:
            raise AssertionError('Media must never be downloaded through Remote.read')
        if private and self.modes[path] != 0o600:
            raise RuntimeError('private file mode')
        return self.files[path]

    def write_new(self, path, data):
        self.event('write', path)
        if path in self.files or path in self.dirs:
            raise FileExistsError(path)
        if path.rsplit('/', 1)[0] not in self.dirs:
            raise FileNotFoundError(path)
        self.files[path], self.modes[path] = data, 0o600
        self.event('written', path)

    def put(self, local, path, confirm=True):
        self.event('put', path)
        if self.files[path] != b'' or self.modes[path] != 0o600 or not confirm:
            raise AssertionError('Not an exclusive private empty media stage')
        self.files[path] = Path(local).read_bytes()
        self.event('uploaded', path)

    def chmod(self, path, mode):
        self.modes[path] = mode

    def stage_metadata(self, path, metadata):
        if tuple(metadata[1:]) != (1000, 1000):
            raise RuntimeError('foreign uid')
        self.modes[path] = metadata[0]

    def rename(self, stage, target):
        self.event('before_rename', target)
        if target in self.files or target in self.dirs:
            raise FileExistsError(target)
        self.move(stage, target)

    def posix_rename(self, stage, target):
        self.event('before_replace', target)
        self.move(stage, target)

    def move(self, stage, target):
        self.files[target] = self.files.pop(stage)
        self.modes[target] = self.modes.pop(stage)
        self.event('renamed', target)

    def remove(self, path):
        self.event('remove', path)
        del self.files[path]
        self.modes.pop(path)

    def remove_owned(self, path, expected):
        if path not in self.files:
            return
        if self.files[path] != expected:
            raise RuntimeError('foreign bytes retained')
        self.remove(path)

    def lint(self, path):
        self.event('lint', path)

    def run(self, command):
        args = shlex.split(command)
        if args[:2] == ['sha256sum', '--']:
            path = args[2]
            self.event('hash', path)
            return M.sha(self.files[path]) + '  ' + path
        self.event('fixture', args[-1])
        return '{"status":"PASS","checks":20}'

    def purge(self):
        self.event('purge', None)

    def close(self):
        self.closed = True


def home_html():
    ids = ('harmat-home-family', 'main', 'masthead', 'colophon', 'h22-offer-modal',
           'hm-cookie-settings-button', 'harmat-internal-language')
    return '<link rel="canonical" href="https://harmat22.hu/">' + ''.join('<div id="%s"></div>' % i for i in ids) + (
        '<h1 id="hero-title">Harmat</h1><form id="h22-offer-form"><input name="email"></form>'
        '<span data-language-protect></span><div id="panorama-viewer"></div>'
        '<a href="tel:+36300733375"></a><a href="mailto:ertekesites@harmat22.hu"></a>')


def journal_html(extra=''):
    return '<link rel="canonical" href="' + M.JOURNAL + '">' + extra + (
        '<section id="september" data-harmat-construction-september="1">old</section>'
        '<div class="harmat-build-log-list">archive</div>')


class TransportTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NETWORK FORBIDDEN')))
        temp = self.stack.enter_context(tempfile.TemporaryDirectory())
        self.local = Path(temp)
        media = {}
        for name in M.NAMES:
            data = (name + ' approved media').encode()
            (self.local / name).write_bytes(data)
            media[name] = {'sha256': M.sha(data), 'bytes': len(data)}
        self.manifest = {'files': media}
        self.original, self.candidate = b'<?php /* original */', b'<?php /* candidate */'
        files = (M.D.ASSET + '/body.php', M.D.ASSET + '/manifest.json', M.D.MAIN)
        blobs = {files[0]: b'unchanged body', files[1]: b'unchanged manifest', M.D.MAIN: self.candidate,
                 M.MODULE: b'<?php /* module */', M.FIXTURE: b'<?php /* fixture */', M.D.CHECKER: b'<?php /* native */'}
        hashes = {n: M.sha(v) for n, v in blobs.items()}
        pins = {'schema': 1, 'old': M.sha(self.original), 'home': M.HOME, 'module': M.TARGET,
                'media_directory': M.MEDIA, 'original_absent': [M.TARGET, M.MEDIA], 'media': media,
                'pins': hashes, 'assets': {n: hashes[n] for n in files if n != M.D.MAIN}}
        self.release = {'original': self.original, 'blobs': blobs, 'files': files, 'hashes': hashes,
                        'release': pins, 'digest': M.sha(M.canonical(pins)), 'media_local': self.local}
        self.r = FakeRemote(self.release)
        self.stack.enter_context(patch.object(M.H, 'Remote', return_value=self.r))
        self.stack.enter_context(patch.object(M.D, 'snapshot', return_value={'business': 'unchanged'}))
        self.stack.enter_context(patch.object(M.D, 'get', side_effect=lambda url: (
            (home_html() if url == M.D.URL else journal_html()).encode(), 'text/html')))
        self.proof = self.stack.enter_context(patch.object(M, 'public', return_value={'routes': 9, 'inventory': 124}))
        self.filters = self.stack.enter_context(patch.object(M.D, 'validate_native', return_value={'offer_checks': 35, 'antispam_checks': 17}))
        self.out = self.stack.enter_context(contextlib.redirect_stdout(io.StringIO()))

    def backup(self):
        return next(path for path in self.r.dirs if path.startswith(M.PREFIX))

    def assert_original(self):
        self.assertEqual(self.r.files[M.HOME], self.original)
        self.assertNotIn(M.TARGET, self.r.files)
        self.assertNotIn(M.D.LOCK, self.r.dirs)
        self.assertTrue(self.r.closed)

    def fail_once(self, op, test):
        def fail(operation, path):
            if operation == op and test(path):
                self.r.fail = lambda *args: None
                raise RuntimeError('synthetic ' + op)
        self.r.fail = fail

    def test_manifest_exact_fixed_names_hashes_sizes_and_symlink(self):
        self.assertEqual(M.validate_media(self.manifest, self.local), self.manifest['files'])
        for mutate in ('extra', 'traversal', 'size', 'hash', 'bool'):
            candidate = copy.deepcopy(self.manifest)
            name = M.NAMES[0]
            if mutate == 'extra':
                candidate['files']['other.mp4'] = candidate['files'][name]
            elif mutate == 'traversal':
                candidate['files']['../' + name] = candidate['files'].pop(name)
            elif mutate == 'size':
                candidate['files'][name]['bytes'] += 1
            elif mutate == 'hash':
                candidate['files'][name]['sha256'] = 'a' * 64
            else:
                candidate['files'][name]['bytes'] = True
            with self.assertRaises(RuntimeError, msg=mutate):
                M.validate_media(candidate, self.local)
        with patch.object(Path, 'is_symlink', return_value=True), self.assertRaises(RuntimeError):
            M.validate_media(self.manifest, self.local)

    def test_default_offline_and_exact_expect_all_online_modes(self):
        with patch.object(M, 'local_release', return_value=self.release):
            with patch('sys.argv', ['deploy.py']):
                M.main()
            self.assertIn('OFFLINE_NO_WRITES', self.out.getvalue())
            for args in (['--deploy'], ['--prepare', '--expect', 'bad'], ['--verify', 'x'], ['--rollback', 'x']):
                with patch('sys.argv', ['deploy.py', *args]), self.assertRaisesRegex(RuntimeError, 'Exact reviewed aggregate'):
                    M.main()
        self.assertEqual(self.r.events, [])

    def test_backup_and_preflight_before_media_module_then_home(self):
        M.deploy(self.release)
        self.assertEqual(self.r.files[M.HOME], self.candidate)
        self.assertEqual(self.r.files[M.TARGET], self.release['blobs'][M.MODULE])
        first_upload = next(i for i, (op, _) in enumerate(self.r.events) if op == 'put')
        for name in ('manifest.json', 'media-manifest.json', 'original.php', 'candidate-home.php',
                     'public-home-original.html', 'public-journal-original.html', 'preflight-proof.json', M.FIXTURE, M.D.CHECKER):
            self.assertIn(('written', self.backup() + '/' + name), self.r.events[:first_upload])
        renamed = [p for op, p in self.r.events if op == 'renamed']
        self.assertEqual(renamed, [M.MEDIA + '/' + n for n in M.NAMES] + [M.TARGET, M.HOME])
        self.assertEqual(self.filters.call_count, 2)
        self.assertFalse(any(op == 'read' and p.startswith(M.MEDIA + '/') for op, p in self.r.events))
        self.assertNotIn(M.D.LOCK, self.r.dirs)

    def test_prepare_writes_only_private_backup(self):
        M.deploy(self.release, True)
        self.assert_original()
        self.assertNotIn(M.MEDIA, self.r.dirs)
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_existing_media_module_and_home_drift_refused(self):
        for target in (M.TARGET, M.MEDIA, M.HOME):
            with self.subTest(target=target):
                original_files = dict(self.r.files)
                self.r.files[target] = b'foreign'
                self.r.modes[target] = 0o644
                with self.assertRaises(RuntimeError):
                    M.deploy(self.release)
                self.assertEqual(self.r.files[target], b'foreign')
                self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))
                self.r.files = original_files

    def test_public_failure_rolls_back_home_then_module_and_owned_media(self):
        self.proof.side_effect = RuntimeError('public failed')
        with self.assertRaisesRegex(RuntimeError, 'public failed'):
            M.deploy(self.release)
        self.assert_original()
        self.assertNotIn(M.MEDIA, self.r.dirs)
        restore = max(i for i, e in enumerate(self.r.events) if e == ('renamed', M.HOME))
        remove = self.r.events.index(('remove', M.TARGET))
        self.assertLess(restore, remove)

    def test_mid_media_failure_preserves_partial_only_no_activation(self):
        def fail(op, path):
            if op == 'put':
                self.r.fail = lambda *args: None
                self.r.files[path] = b'partial'
                raise RuntimeError('partial upload')
        self.r.fail = fail
        with self.assertRaisesRegex(RuntimeError, 'partial upload'):
            M.deploy(self.release)
        self.assert_original()
        self.assertTrue(any(v == b'partial' for v in self.r.files.values()))
        self.assertNotIn(('renamed', M.TARGET), self.r.events)

    def test_failure_after_module_rename_deactivates_owned_module(self):
        self.fail_once('renamed', lambda p: p == M.TARGET)
        with self.assertRaisesRegex(RuntimeError, 'synthetic renamed'):
            M.deploy(self.release)
        self.assert_original()
        self.assertNotIn(M.MEDIA, self.r.dirs)

    def test_formal_home_lint_failure_restores_both_modules(self):
        self.fail_once('lint', lambda p: p == M.HOME)
        with self.assertRaisesRegex(RuntimeError, 'synthetic lint'):
            M.deploy(self.release)
        self.assert_original()
        self.assertNotIn(M.MEDIA, self.r.dirs)

    def test_cache_failure_does_not_prevent_source_deactivation(self):
        self.r.fail = lambda op, path: (_ for _ in ()).throw(RuntimeError('cache unavailable')) if op == 'purge' else None
        with self.assertRaisesRegex(RuntimeError, 'cache unavailable'):
            M.deploy(self.release)
        self.assert_original()
        self.assertNotIn(M.MEDIA, self.r.dirs)

    def test_preflight_filters_failure_never_activates(self):
        self.filters.side_effect = RuntimeError('native filter failed')
        with self.assertRaisesRegex(RuntimeError, 'native filter failed'):
            M.deploy(self.release)
        self.assert_original()
        self.assertNotIn(M.MEDIA, self.r.dirs)

    def test_existing_progress_hash_protected_before_activation(self):
        original_protected = M.protected
        checks = 0
        def drift(r, release):
            nonlocal checks
            checks += 1
            if checks == 2:
                r.files[M.D.DIR + '/' + M.PROGRESS] = b'legitimate external edit'
            return original_protected(r, release)
        with patch.object(M, 'protected', side_effect=drift), self.assertRaisesRegex(RuntimeError, 'Protected state drift before activation'):
            M.deploy(self.release)
        self.assert_original()
        self.assertEqual(self.r.files[M.D.DIR + '/' + M.PROGRESS], b'legitimate external edit')

    def test_independent_verify_checks_final_ownership_after_public_requests(self):
        M.deploy(self.release)
        def drift(*args):
            self.r.files[M.TARGET] = b'foreign after request'
            return {'ok': True}
        self.proof.side_effect = drift
        with self.assertRaisesRegex(RuntimeError, 'Foreign module retained'):
            M.verify_or_rollback(self.backup(), self.release)
        self.assertEqual(self.r.files[M.TARGET], b'foreign after request')

    def test_foreign_home_during_public_not_overwritten_or_dependencies_removed(self):
        def drift(*args):
            self.r.files[M.HOME] = b'foreign editor'
            return {'ok': True}
        self.proof.side_effect = drift
        with self.assertRaisesRegex(RuntimeError, 'Foreign controller retained'):
            M.deploy(self.release)
        self.assertEqual(self.r.files[M.HOME], b'foreign editor')
        self.assertIn(M.TARGET, self.r.files)
        self.assertIn(M.MEDIA, self.r.dirs)

    def test_foreign_module_during_public_not_deleted(self):
        def drift(*args):
            self.r.files[M.TARGET] = b'foreign module'
            return {'ok': True}
        self.proof.side_effect = drift
        with self.assertRaisesRegex(RuntimeError, 'Foreign module retained'):
            M.deploy(self.release)
        self.assertEqual(self.r.files[M.HOME], self.original)
        self.assertEqual(self.r.files[M.TARGET], b'foreign module')

    def test_verify_and_independent_source_only_rollback(self):
        M.deploy(self.release)
        M.verify_or_rollback(self.backup(), self.release)
        M.verify_or_rollback(self.backup(), self.release, True)
        self.assert_original()
        self.assertEqual(self.r.files[M.D.DIR + '/' + M.PROGRESS], b'protected-progress')

    def test_backup_pin_public_baseline_and_metadata_tamper_refused(self):
        M.deploy(self.release)
        path = self.backup() + '/manifest.json'
        original = self.r.files[path]
        for field in ('digest', 'metadata', 'public_baselines'):
            saved = json.loads(original)
            saved[field] = None
            self.r.files[path] = json.dumps(saved).encode()
            with self.assertRaises(RuntimeError):
                M.verify_or_rollback(self.backup(), self.release, True)
            self.assertEqual(self.r.files[M.HOME], self.candidate)
        self.r.files[path] = original

    def test_rollback_retains_foreign_media_and_extra_file(self):
        M.deploy(self.release)
        path = M.MEDIA + '/' + M.NAMES[0]
        self.r.files[path] = b'foreign media'
        self.r.files[M.MEDIA + '/unrelated.bin'] = b'unrelated'
        M.verify_or_rollback(self.backup(), self.release, True)
        self.assert_original()
        self.assertEqual(self.r.files[path], b'foreign media')
        self.assertEqual(self.r.files[M.MEDIA + '/unrelated.bin'], b'unrelated')

    def test_upload_hash_mismatch_and_destination_race_never_overwrite(self):
        self.r.mkdir(M.MEDIA, 0o755)
        entry = self.manifest['files'][M.NAMES[0]]
        self.fail_once('hash', lambda p: True)
        with self.assertRaisesRegex(RuntimeError, 'synthetic hash'):
            M.upload_media(self.r, self.local / M.NAMES[0], M.NAMES[0], entry, {})
        target = M.MEDIA + '/' + M.NAMES[0]
        def race(op, path):
            if op == 'before_rename' and path == target:
                self.r.files[target], self.r.modes[target] = b'foreign', 0o644
        self.r.fail = race
        with self.assertRaises(FileExistsError):
            M.upload_media(self.r, self.local / M.NAMES[0], M.NAMES[0], entry, {})
        self.assertEqual(self.r.files[target], b'foreign')

    def test_no_form_journal_preserved_and_dates_checked_inside_owned_root(self):
        baseline = journal_html()
        self.assertTrue(M.journal_assert(baseline, baseline, False))
        section = '<section id="' + M.ROOT_ID + '"><time>2026-10-13</time><div><time>2026-10-12</time></div></section>'
        self.assertTrue(M.journal_assert(journal_html(section), baseline, True))
        for changed in (journal_html(section.replace('2026-10-13', 'wrong')),
                        journal_html(section) + '<form id="h22-offer-form"></form>',
                        journal_html(section) + '<div id="september"></div>',
                        journal_html(section).replace(M.JOURNAL, 'https://bad.invalid/')):
            with self.assertRaises((RuntimeError, ValueError)):
                M.journal_assert(changed, baseline, True)
        head_dates = '<meta content="2026-10-13 2026-10-12">'
        with self.assertRaises(RuntimeError):
            M.journal_assert(head_dates + journal_html(section.replace('2026-10-13', '2026-10-11')), baseline, True)

    def test_media_symlink_or_unsafe_mode_refused(self):
        self.r.mkdir(M.MEDIA, 0o755)
        path = M.MEDIA + '/' + M.NAMES[0]
        entry = self.manifest['files'][M.NAMES[0]]
        self.r.files[path] = (self.local / M.NAMES[0]).read_bytes()
        self.r.modes[path] = 0o666
        with self.assertRaisesRegex(RuntimeError, 'permissions'):
            M.remote_media(self.r, path, entry)
        original_info = self.r.info
        with patch.object(self.r, 'info', side_effect=lambda p: types.SimpleNamespace(st_mode=stat.S_IFLNK) if p == path else original_info(p)):
            with self.assertRaisesRegex(RuntimeError, 'symlink'):
                M.remote_media(self.r, path, entry)

    def test_real_public_proof_requires_same_home_latest_video_poster_date_deeplink(self):
        M.deploy(self.release)
        fragment = ('<section class="hhf-section hhf-journal" data-hcu-home="1">'
                    '<time datetime="2026-10-13"></time><img src="' + M.MEDIA_URL + M.BASENAMES[0] + '.jpg">'
                    '<div data-hcu-video="' + M.MEDIA_URL + M.BASENAMES[0] + '.mp4"></div>'
                    '<a href="' + M.JOURNAL + '#construction-2026-10-13">Read</a></section>')
        section = '<section id="' + M.ROOT_ID + '"><time>2026-10-13</time><time>2026-10-12</time></section>'
        current = home_html() + fragment
        def get(url):
            return (current if url == M.D.URL else journal_html(section)).encode(), 'text/html'
        with patch.object(M.D, 'get', side_effect=get), patch.object(M.D, 'public_proof', return_value={}), patch.object(M, 'media_http', return_value={'head': True}):
            self.assertTrue(REAL_PUBLIC(self.r, self.release, home_html(), journal_html())['journal']['active'])
            for marker in ('datetime="2026-10-13"', '.jpg', '.mp4', '#construction-2026-10-13', 'data-hcu-home="1"'):
                current = home_html() + fragment.replace(marker, 'wrong')
                with self.assertRaises(RuntimeError, msg=marker):
                    REAL_PUBLIC(self.r, self.release, home_html(), journal_html())


class HttpTests(unittest.TestCase):
    def response(self, status, length, content_range=None):
        from email.message import Message
        headers = Message()
        headers['Content-Type'] = 'video/mp4'
        headers['Content-Length'] = str(length)
        if content_range:
            headers['Content-Range'] = content_range
        response = types.SimpleNamespace(status=status, headers=headers,
                                         geturl=lambda: M.MEDIA_URL + M.NAMES[0],
                                         read=lambda amount: b'x' * min(1024, amount))
        return contextlib.nullcontext(response)

    def test_video_head_then_only_1024_byte_range(self):
        with patch.object(M.urllib.request, 'urlopen', side_effect=[self.response(200, 10000), self.response(206, 1024, 'bytes 0-1023/10000')]) as get:
            self.assertTrue(M.media_http(M.NAMES[0], {'bytes': 10000})['range'])
            self.assertEqual(get.call_args_list[0].args[0].get_method(), 'HEAD')
            self.assertEqual(get.call_args_list[1].args[0].get_header('Range'), 'bytes=0-1023')

    def test_no_full_video_fallback_if_range_ignored(self):
        with patch.object(M.urllib.request, 'urlopen', side_effect=[self.response(200, 10000), self.response(200, 10000)]):
            with self.assertRaisesRegex(RuntimeError, 'range not supported'):
                M.media_http(M.NAMES[0], {'bytes': 10000})


if __name__ == '__main__':
    unittest.main(verbosity=2)
