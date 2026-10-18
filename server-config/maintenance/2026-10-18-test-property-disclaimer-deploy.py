"""Offline staging/rollback invariants. No SSH or business requests."""
import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('disclaimer_deploy_test', Path(__file__).with_name('2026-10-18-deploy-property-disclaimer.py'))
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class FakeRemote:
    def __init__(self):
        self.files = {}
        self.events = []
        self.sftp = self
        self.fail_lint = False

    def write_new(self, path, data):
        if path in self.files:
            raise RuntimeError('collision')
        self.files[path] = data
        self.events.append(('write', path))

    def read(self, path, private=False):
        return self.files[path]

    def info(self, path):
        if path not in self.files:
            raise FileNotFoundError(path)
        return type('Info', (), {'st_mode': 0o100644, 'st_uid': 1608, 'st_gid': 1611})()

    def lint(self, path):
        self.events.append(('lint', path))
        if self.fail_lint:
            raise RuntimeError('synthetic lint failure')

    def chmod(self, path, mode):
        self.events.append(('chmod', path))

    def stage_metadata(self, path, metadata):
        self.events.append(('metadata', path))

    def posix_rename(self, source, target):
        self.files[target] = self.files.pop(source)
        self.events.append(('rename', target))

    def remove_owned(self, path, data):
        if self.files.get(path) != data:
            raise RuntimeError('foreign bytes')
        del self.files[path]
        self.events.append(('remove', path))

    def purge(self):
        self.events.append(('purge', None))


class DeploymentTests(unittest.TestCase):
    def test_scope_exact_two_files(self):
        self.assertEqual(M.FILES, ('harmat-migrated-snippets.php', 'zz-harmat-property-disclaimer.php'))

    def test_preimage_and_only_call_change(self):
        blobs, pins, digest = M.bundle()
        old = M.original()
        self.assertEqual(M.sha(old), 'c63d48b79940f43c2311bb9c0fd85e280d35634bccd2caebcb4c583aacd65517')
        self.assertEqual(blobs[M.LEGACY], old.replace(M.OLD_CALL.replace(b'\n', b'\r\n'), M.NEW_CALL.replace(b'\n', b'\r\n'), 1))
        self.assertEqual(old.count(b'\r\n'), blobs[M.LEGACY].count(b'\r\n'))
        self.assertEqual(len(digest), 64)

    def test_lint_precedes_rename(self):
        r = FakeRemote()
        target = M.DIR + '/' + M.LEGACY
        r.files[target] = b'old'
        M.replace(r, M.LEGACY, b'new', b'old')
        self.assertEqual(r.files, {target: b'new'})
        events = [x[0] for x in r.events]
        self.assertLess(events.index('lint'), events.index('rename'))

    def test_new_file_lint_failure_never_activates(self):
        r = FakeRemote()
        r.fail_lint = True
        with self.assertRaises(RuntimeError):
            M.replace(r, M.MAIN, b'new', None)
        self.assertEqual(r.files, {})
        self.assertNotIn('rename', [x[0] for x in r.events])

    def test_existing_lint_failure_preserves_original(self):
        r = FakeRemote()
        target = M.DIR + '/' + M.LEGACY
        r.files[target] = b'old'
        r.fail_lint = True
        with self.assertRaises(RuntimeError):
            M.replace(r, M.LEGACY, b'new', b'old')
        self.assertEqual(r.files, {target: b'old'})

    def test_foreign_target_stops(self):
        for expected in (None, b'old'):
            r = FakeRemote()
            target = M.DIR + '/' + M.MAIN
            r.files[target] = b'foreign'
            with self.assertRaises(RuntimeError):
                M.replace(r, M.MAIN, b'new', expected)
            self.assertEqual(r.files, {target: b'foreign'})

    def test_rollback_removes_only_owned_module_restores_legacy(self):
        r = FakeRemote()
        blobs = {M.MAIN: b'module', M.LEGACY: b'guarded'}
        r.files = {M.DIR + '/' + n: b for n, b in blobs.items()}
        M.reverse(r, b'original', blobs, list(M.FILES))
        self.assertEqual(r.files, {M.DIR + '/' + M.LEGACY: b'original'})

    def test_rollback_refuses_foreign_module(self):
        r = FakeRemote()
        r.files[M.DIR + '/' + M.MAIN] = b'foreign'
        with self.assertRaises(RuntimeError):
            M.reverse(r, b'old', {M.MAIN: b'owned'}, [M.MAIN])
        self.assertEqual(r.files[M.DIR + '/' + M.MAIN], b'foreign')

    def test_only_reviewed_snapshot_pin_may_change(self):
        before = {'other_sources': {'migratedSnippets': 'old', 'other': 'same'}, 'protected': {'records': 'fixed'}}
        after = M.expected_snapshot(before, 'new')
        self.assertEqual(before['other_sources']['migratedSnippets'], 'old')
        self.assertEqual(after, {'other_sources': {'migratedSnippets': 'new', 'other': 'same'}, 'protected': {'records': 'fixed'}})

    def test_backup_paths_cannot_escape(self):
        for path in ('/tmp/test', M.PREFIX + '../other', M.PREFIX + 'a' * 32 + '/x'):
            with self.assertRaises(RuntimeError):
                M.saved_backup(path)
        M.saved_backup(M.PREFIX + 'a' * 32)


if __name__ == '__main__':
    unittest.main()
