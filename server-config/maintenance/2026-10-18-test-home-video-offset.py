"""Exact desktop video offset and fake controller transport tests; no real SSH/HTTP."""
import contextlib
import importlib.util
import io
from pathlib import Path
import socket
import subprocess
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parent


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, HERE / filename)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


V = load('video_offset_under_test', '2026-10-18-update-home-video-offset.py')
SHARED = load('video_offset_fake_transport', '2026-10-18-test-home-header-flow-update.py')
U = V.configure()


class FakeRemote(SHARED.FakeRemote):
    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        return super().info(path)


class VideoOffsetTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NETWORK FORBIDDEN')))
        self.original, self.candidate, self.files, self.hashes, self.checker = V.local_release()
        self.assets = {U.D.DIR + '/' + name: (V.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')
                       for name in self.files if name != U.D.MAIN}
        self.stack.enter_context(patch.object(SHARED, 'M', U))
        self.r = FakeRemote(self.original, self.assets)
        self.stack.enter_context(patch.object(U.H, 'Remote', return_value=self.r))
        self.stack.enter_context(patch.object(U.D, 'snapshot', return_value={'protected': 'unchanged'}))
        self.stack.enter_context(patch.object(U.D, 'assert_home', return_value={'home': True}))
        self.stack.enter_context(patch.object(U.D, 'get', side_effect=self.get))
        self.proof = self.stack.enter_context(patch.object(U.D, 'public_proof', return_value={'routes': 9, 'inventory': 124}))
        self.filters = self.stack.enter_context(patch.object(U.D, 'validate_native', return_value={'offer_checks': 35, 'antispam_checks': 17}))
        self.out = self.stack.enter_context(contextlib.redirect_stdout(io.StringIO()))

    def get(self, url):
        self.assertEqual(url, U.D.URL)
        style = V.STYLE if self.r.files[U.TARGET] == self.candidate else V.F.STYLE
        return ('<html>' + style + '</html>').encode(), 'text/html'

    def deploy(self):
        U.deploy(self.original, self.candidate, self.files, self.hashes, self.checker)

    def backup(self):
        return next(p for p in self.r.dirs if p.startswith(V.PREFIX))

    def assert_assets_unchanged(self):
        self.assertEqual({p: v for p, v in self.r.files.items()
                          if p.startswith(U.D.DIR + '/') and p != U.TARGET}, self.assets)
        self.assertNotIn(U.D.LOCK, self.r.dirs)

    def test_exact_reversible_single_rule_and_version_only(self):
        self.assertEqual(U.sha(self.original), V.OLD_SHA)
        self.assertEqual(V.BASE, '63361eb')
        restored = self.candidate.replace(V.resource_block().encode(), V.F.resource_block().encode(), 1)
        self.assertEqual(restored.replace(b' * Version: 1.0.7', b' * Version: 1.0.6', 1), self.original)
        self.assertEqual(V.STYLE, V.F.STYLE[:-8] + ''.join(V.RULES) + '</style>')
        self.assertEqual(len(V.RULES), 1)
        self.assertEqual(V.RULES[0], '@media(min-width:1025px){body.home.page-id-164 #harmat-home-family .hhf-hero{margin-top:76px;scroll-margin-top:152px}}')
        for forbidden in ('#masthead', '#my-sticky-header', '#harmat-internal-language', 'position:',
                          'transform:', 'display:', 'padding:', 'font', 'height:', 'h22-offer', 'form'):
            self.assertNotIn(forbidden, V.RULES[0])

    def test_wrong_source_versions_crlf_and_duplicate_patch_refused(self):
        for bad in (self.original + b'drift', self.original.replace(b'1.0.6', b'1.0.5'),
                    self.original.replace(b'\n', b'\r\n'), self.candidate):
            with self.assertRaisesRegex(RuntimeError, 'Pinned v1.0.6'):
                V.patch(bad)

    def test_real_local_drift_refused(self):
        controller = (V.ROOT / 'wp-mu-plugins' / U.D.MAIN).resolve()
        real_read = Path.read_bytes
        with patch.object(Path, 'read_bytes', autospec=True,
                          side_effect=lambda p: b'drift' if p.resolve() == controller else real_read(p)):
            with self.assertRaisesRegex(RuntimeError, 'exact desktop video offset patch'):
                V.local_release()

    def test_all_assets_native_header_language_and_form_sources_match_base(self):
        names = [name for name in self.files if name != U.D.MAIN]
        names += ['harmat-header-sales-contact.php', 'harmat-internal-language/bootstrap.js',
                  'harmat-internal-language/runtime.css', 'harmat-unified-offer-modal.php']
        for name in names:
            released = subprocess.check_output(['git', 'show', V.BASE + ':wp-mu-plugins/' + name], cwd=V.ROOT)
            self.assertEqual((V.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n'), released, name)

    def test_fresh_backup_then_only_controller_changes(self):
        self.deploy()
        self.assertEqual(self.r.files[U.TARGET], self.candidate)
        self.assertEqual(self.r.files[self.backup() + '/original.php'], self.original)
        self.assertEqual(self.r.files[self.backup() + '/candidate.php'], self.candidate)
        self.assertEqual([p for op, p in self.r.events if op == 'renamed'], [U.TARGET])
        self.assertEqual(self.filters.call_count, 2)
        self.assert_assets_unchanged()

    def test_verify_and_rollback_restore_only_v106_controller(self):
        self.deploy()
        U.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes)
        U.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes, True)
        self.assertEqual(self.r.files[U.TARGET], self.original)
        self.assert_assets_unchanged()

    def test_public_failure_restores_controller_not_business_data(self):
        self.proof.side_effect = RuntimeError('public regression')
        with self.assertRaisesRegex(RuntimeError, 'public regression'):
            self.deploy()
        self.assertEqual(self.r.files[U.TARGET], self.original)
        self.assert_assets_unchanged()

    def test_foreign_live_controller_never_overwritten(self):
        self.r.files[U.TARGET] = b'foreign'
        with self.assertRaisesRegex(RuntimeError, 'ownership drift'):
            self.deploy()
        self.assertEqual(self.r.files[U.TARGET], b'foreign')
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_cli_offline_by_default_and_requires_exact_new_pin(self):
        with patch('sys.argv', ['video-offset.py']):
            U.main()
        self.assertIn('OFFLINE_NO_WRITES', self.out.getvalue())
        for args in (['--deploy'], ['--verify', 'backup'], ['--rollback', 'backup', '--expect', 'bad']):
            with patch('sys.argv', ['video-offset.py'] + args), self.assertRaisesRegex(RuntimeError, 'Exact reviewed'):
                U.main()
        self.assertEqual(self.r.events, [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
