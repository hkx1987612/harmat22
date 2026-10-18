"""Offline controller-update safety checks. SSH/HTTP are stubbed and forbidden."""
import contextlib
import importlib.abc
import importlib.util
import io
import json
from pathlib import Path
import socket
import stat
import types
import unittest
from unittest.mock import patch
import hashlib


def load_updater():
    class Stub(importlib.abc.Loader):
        def create_module(self, spec):
            return None

        def exec_module(self, mod):
            mod.DIR = '/fake/public_html/wp-content/mu-plugins'
            mod.MAIN = 'zz-harmat-home-family.php'
            mod.LOCK = '/fake/backups/.home-family.lock'
            mod.CHECKER = '2026-10-16-native-offer-readonly.php'
            mod.URL = 'https://harmat22.hu/'
            mod.H = types.SimpleNamespace(BACKUPS='/fake/backups', Remote=None,
                                          safe_public_mode=lambda mode, *args: not mode & 0o022)
            mod.require = lambda ok, msg: None if ok else (_ for _ in ()).throw(RuntimeError(msg))
            mod.sha = lambda data: hashlib.sha256(data).hexdigest()
            mod.absent = lambda r, path: path not in r.files and path not in r.dirs
            mod.write_json = lambda r, path, data: r.write_new(path, json.dumps(data).encode())
            mod.get = lambda url: (b'<html>baseline</html>', 'text/html')
            mod.assert_home = lambda *args: {'home': True}
            mod.snapshot = lambda r: {'protected': 'same'}
            mod.public_proof = lambda *args: {'routes': 9}
            mod.validate_native = lambda *args: {'offer_checks': 35, 'antispam_checks': 17}

    original_spec = importlib.util.spec_from_file_location
    path = Path(__file__).with_name('2026-10-18-update-home-header-flow.py')
    spec = original_spec('header_flow_under_test', path)
    module = importlib.util.module_from_spec(spec)

    def dependency(name, filename):
        if Path(filename).name != '2026-10-18-deploy-home-family.py':
            raise AssertionError('Unexpected dependency')
        return importlib.util.spec_from_loader(name, Stub())

    with patch.object(importlib.util, 'spec_from_file_location', side_effect=dependency):
        spec.loader.exec_module(module)
    return module


M = load_updater()
REAL_PUBLIC = M.public


class FakeRemote:
    def __init__(self, original, assets):
        self.files = {M.TARGET: original, **assets}
        self.dirs = {M.D.DIR, M.H.BACKUPS}
        self.events = []
        self.sftp = self
        self.fail = lambda *args: None
        self.closed = False
        self.meta = (0o644, 1000, 1000)

    def event(self, op, path):
        self.events.append((op, path))
        self.fail(op, path)

    def directory(self, path, private=False):
        self.event('directory', path)
        if path not in self.dirs:
            raise FileNotFoundError(path)

    def mkdir(self, path):
        self.event('mkdir', path)
        if path in self.dirs or path in self.files:
            raise FileExistsError(path)
        self.dirs.add(path)

    def rmdir(self, path):
        self.event('rmdir', path)
        self.dirs.remove(path)

    def info(self, path):
        self.event('info', path)
        return types.SimpleNamespace(st_mode=stat.S_IFREG | self.meta[0],
                                     st_uid=self.meta[1], st_gid=self.meta[2])

    def read(self, path, private=False):
        self.event('read', path)
        return self.files[path]

    def write_new(self, path, data):
        self.event('write', path)
        if path in self.files:
            raise FileExistsError(path)
        if path.rsplit('/', 1)[0] not in self.dirs:
            raise FileNotFoundError(path)
        self.files[path] = data
        self.event('written', path)

    def lint(self, path):
        self.event('lint', path)

    def stage_metadata(self, stage, metadata):
        self.event('metadata', stage)
        if tuple(metadata) != self.meta:
            raise RuntimeError('metadata mismatch')

    def posix_rename(self, stage, target):
        self.event('before_rename', target)
        self.files[target] = self.files.pop(stage)
        self.event('renamed', target)

    def remove_owned(self, path, expected):
        if self.files.get(path) != expected:
            raise RuntimeError('foreign temporary bytes')
        self.event('remove', path)
        del self.files[path]

    def purge(self):
        self.event('purge', None)

    def close(self):
        self.closed = True
        self.event('close', None)


class UpdateTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NETWORK FORBIDDEN')))
        self.original = b'<?php\n * Version: 1.0.0\n' + M.OLD + b'\n'
        self.stack.enter_context(patch.object(M, 'OLD_SHA', M.sha(self.original)))
        self.candidate = M.patch(self.original)
        self.files = ('harmat-home-family/body.php', 'harmat-home-family/manifest.json', M.D.MAIN)
        self.asset_bytes = {M.D.DIR + '/' + self.files[0]: b'body',
                            M.D.DIR + '/' + self.files[1]: b'manifest'}
        self.hashes = {name: M.sha(self.asset_bytes.get(M.D.DIR + '/' + name, self.candidate)) for name in self.files}
        self.r = FakeRemote(self.original, self.asset_bytes)
        self.checker = (M.HERE / M.D.CHECKER).read_bytes().replace(b'\r\n', b'\n')
        self.stack.enter_context(patch.object(M.H, 'Remote', return_value=self.r))
        self.public = self.stack.enter_context(patch.object(M, 'public', return_value={'routes': 9}))
        self.filters = self.stack.enter_context(patch.object(M.D, 'validate_native', return_value={'offer_checks': 35, 'antispam_checks': 17}))
        self.out = self.stack.enter_context(contextlib.redirect_stdout(io.StringIO()))

    def deploy(self):
        M.deploy(self.original, self.candidate, self.files, self.hashes, self.checker)

    def fail_once(self, op, condition):
        def failure(operation, path):
            if operation == op and condition(path):
                self.r.fail = lambda *args: None
                raise RuntimeError('synthetic ' + op)
        self.r.fail = failure

    def assert_clean(self, original=True):
        self.assertEqual(self.r.files[M.TARGET], self.original if original else self.candidate)
        self.assertEqual({p: v for p, v in self.r.files.items() if p.startswith(M.D.DIR + '/') and p != M.TARGET}, self.asset_bytes)
        self.assertNotIn(M.D.LOCK, self.r.dirs)
        self.assertTrue(self.r.closed)

    def backup(self):
        return next(p for p in self.r.dirs if p.startswith(M.PREFIX))

    def test_exact_patch_and_no_other_source_change(self):
        self.assertEqual(self.candidate.replace(M.NEW, M.OLD).replace(b' * Version: 1.0.1', b' * Version: 1.0.0'), self.original)
        self.assertEqual(self.candidate.count(M.STYLE.encode()), 1)
        with self.assertRaisesRegex(RuntimeError, 'Pinned original'):
            M.patch(self.original + b'drift')

    def test_fresh_backup_lints_and_filters_before_atomic_controller_only_write(self):
        self.deploy()
        events = self.r.events
        first = next(i for i, (op, p) in enumerate(events) if op == 'write' and p.startswith(M.TARGET + '.'))
        for name in ('original.php', 'candidate.php', 'public-original.html', 'manifest.json', 'preflight-proof.json', M.D.CHECKER):
            self.assertIn(('written', self.backup() + '/' + name), events[:first])
        renamed = events.index(('renamed', M.TARGET))
        self.assertTrue(any(op == 'lint' and p.startswith(M.TARGET + '.') for op, p in events[:renamed]))
        self.assertIn(('lint', M.TARGET), events[renamed + 1:])
        self.assertEqual([p for op, p in events if op == 'renamed'], [M.TARGET])
        self.assertEqual(self.filters.call_count, 2)
        self.assertIn('RELEASED_HOME_HEADER_FLOW', self.out.getvalue())
        self.assert_clean(False)

    def test_preflight_failures_never_touch_controller(self):
        for op, condition in (('written', lambda p: p.endswith('/original.php')),
                              ('lint', lambda p: p.endswith('/candidate.php'))):
            with self.subTest(op=op):
                self.r.events.clear()
                self.fail_once(op, condition)
                with self.assertRaises(RuntimeError):
                    self.deploy()
                self.assertFalse(any(o == 'renamed' for o, _ in self.r.events))
                self.assert_clean()

    def test_stage_lint_failure_cleans_stage(self):
        self.fail_once('lint', lambda p: p.startswith(M.TARGET + '.'))
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_clean()

    def test_short_stage_upload_is_removed(self):
        def fail(op, path):
            if op == 'written' and path.startswith(M.TARGET + '.'):
                self.r.files[path] = self.r.files[path][:7]
                self.r.fail = lambda *args: None
                raise RuntimeError('short write')
        self.r.fail = fail
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_clean()

    def test_protected_drift_before_activation_refuses_write(self):
        with patch.object(M.D, 'snapshot', side_effect=[{'v': 1}, {'v': 2}]):
            with self.assertRaisesRegex(RuntimeError, 'before replacement'):
                self.deploy()
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))
        self.assert_clean()

    def test_protected_drift_after_activation_restores_only_controller(self):
        with patch.object(M.D, 'snapshot', side_effect=[{'v': 1}, {'v': 1}, {'v': 2}]):
            with self.assertRaisesRegex(RuntimeError, 'after replacement'):
                self.deploy()
        self.assertEqual(sum(op == 'renamed' for op, _ in self.r.events), 2)
        self.assert_clean()

    def test_foreign_initial_controller_is_never_overwritten(self):
        self.r.files[M.TARGET] = b'foreign'
        with self.assertRaisesRegex(RuntimeError, 'ownership drift'):
            self.deploy()
        self.assertEqual(self.r.files[M.TARGET], b'foreign')
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_asset_drift_aborts_before_production_write(self):
        self.r.files[M.D.DIR + '/' + self.files[0]] = b'foreign'
        with self.assertRaisesRegex(RuntimeError, 'asset drift'):
            self.deploy()
        self.assertEqual(self.r.files[M.TARGET], self.original)
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_concurrent_controller_after_staging_not_overwritten(self):
        def fail(op, path):
            if op == 'metadata':
                self.r.files[M.TARGET] = b'concurrent editor'
                self.r.fail = lambda *args: None
        self.r.fail = fail
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assertEqual(self.r.files[M.TARGET], b'concurrent editor')
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))
        self.assertFalse(any(p.startswith(M.TARGET + '.') for p in self.r.files))

    def test_rename_committed_but_response_lost_is_recovered(self):
        self.fail_once('renamed', lambda p: p == M.TARGET)
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_clean()

    def test_formal_lint_failure_restores_original(self):
        self.fail_once('lint', lambda p: p == M.TARGET)
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_clean()

    def test_cache_purge_failure_restores_original(self):
        self.fail_once('purge', lambda p: True)
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_clean()

    def test_preflight_native_filter_failure_never_activates(self):
        self.filters.side_effect = RuntimeError('native filter failed')
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))
        self.assert_clean()

    def test_post_activation_native_filter_failure_rolls_back(self):
        self.filters.side_effect = [{'offer_checks': 35, 'antispam_checks': 17}, RuntimeError('native filter failed')]
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_clean()

    def test_stage_metadata_failure_cleans_temporary_file(self):
        self.fail_once('metadata', lambda p: True)
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_clean()

    def test_existing_shared_lock_is_not_removed(self):
        self.r.dirs.add(M.D.LOCK)
        with self.assertRaises(FileExistsError):
            self.deploy()
        self.assertIn(M.D.LOCK, self.r.dirs)
        self.assertTrue(self.r.closed)
        self.assertEqual(self.r.files[M.TARGET], self.original)

    def test_unsafe_controller_metadata_is_rejected(self):
        self.r.meta = (0o666, 1000, 1000)
        with self.assertRaisesRegex(RuntimeError, 'Unsafe controller'):
            self.deploy()
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))
        self.assert_clean()

    def test_rollback_refuses_foreign_current_controller(self):
        self.deploy()
        self.r.files[M.TARGET] = b'later external edit'
        with self.assertRaisesRegex(RuntimeError, 'ownership drift'):
            M.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes, True)
        self.assertEqual(self.r.files[M.TARGET], b'later external edit')

    def test_public_failure_restores_original(self):
        self.public.side_effect = RuntimeError('public failed')
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_clean()

    def test_foreign_controller_after_public_failure_retained(self):
        def failed(*args):
            self.r.files[M.TARGET] = b'new external edit'
            raise RuntimeError('public failed')
        self.public.side_effect = failed
        with self.assertRaisesRegex(RuntimeError, 'Foreign controller retained'):
            self.deploy()
        self.assertEqual(self.r.files[M.TARGET], b'new external edit')

    def test_saved_exact_rollback_preserves_assets(self):
        self.deploy()
        M.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes, True)
        self.assert_clean()
        self.assertIn('ROLLED_BACK_HOME_HEADER_FLOW', self.out.getvalue())

    def test_verify_does_not_replace_controller(self):
        self.deploy()
        before = sum(op == 'renamed' for op, _ in self.r.events)
        M.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes)
        self.assertEqual(sum(op == 'renamed' for op, _ in self.r.events), before)
        self.assert_clean(False)
        self.assertIn('VERIFIED_HOME_HEADER_FLOW', self.out.getvalue())

    def test_backup_tampering_prevents_rollback(self):
        self.deploy()
        self.r.files[self.backup() + '/original.php'] = b'tampered'
        with self.assertRaisesRegex(RuntimeError, 'Saved raw'):
            M.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes, True)
        self.assert_clean(False)

    def test_wrong_backup_path_is_rejected_without_connection(self):
        with self.assertRaisesRegex(RuntimeError, 'Exact private backup'):
            M.verify_or_rollback('/fake/backups/other', self.original, self.candidate, self.files, self.hashes, True)
        self.assertEqual(self.r.events, [])

    def test_default_cli_offline_and_writes_require_exact_sha(self):
        with patch.object(M, 'local_release', return_value=(self.original, self.candidate, self.files, self.hashes, b'checker')), \
                patch.object(M, 'deploy') as deploy, patch.object(M, 'verify_or_rollback') as verify:
            with patch('sys.argv', ['update.py']):
                M.main()
            self.assertIn('OFFLINE_NO_WRITES', self.out.getvalue())
            for args in (['--deploy'], ['--deploy', '--expect', 'bad'], ['--verify', 'backup'], ['--rollback', 'backup']):
                with patch('sys.argv', ['update.py'] + args), self.assertRaisesRegex(RuntimeError, 'Exact reviewed'):
                    M.main()
            deploy.assert_not_called()
            verify.assert_not_called()
            with patch('sys.argv', ['update.py', '--deploy', '--expect', M.sha(self.candidate)]):
                M.main()
            deploy.assert_called_once()
        self.assertEqual(self.r.events, [])

    def test_public_wrapper_requires_exact_single_updated_style(self):
        for content, updated, valid in ((M.STYLE, True, True), ('', False, True),
                                        ('', True, False), (M.STYLE, False, False),
                                        (M.STYLE * 2, True, False)):
            with self.subTest(updated=updated, valid=valid), patch.object(M.D, 'get', return_value=(content.encode(), 'text/html')):
                if valid:
                    self.assertEqual(REAL_PUBLIC(self.r, self.files, self.hashes, 'baseline', updated), {'routes': 9})
                else:
                    with self.assertRaisesRegex(RuntimeError, 'state mismatch'):
                        REAL_PUBLIC(self.r, self.files, self.hashes, 'baseline', updated)


if __name__ == '__main__':
    unittest.main(verbosity=2)
