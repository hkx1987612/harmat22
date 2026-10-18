"""Offline exact hero patch and safe controller transport composition. No real SSH/HTTP."""
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


A = load('actions_under_test', '2026-10-18-update-home-actions.py')
SHARED = load('actions_fake_transport', '2026-10-18-test-home-header-flow-update.py')
U = A.configure()


class FakeRemote(SHARED.FakeRemote):
    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        return super().info(path)


class ActionsTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NETWORK FORBIDDEN')))
        base = subprocess.check_output(['git', 'show', A.BASE + ':wp-mu-plugins/' + A.U.D.MAIN], cwd=A.ROOT)
        historical = A.patch(base)
        controller = (A.ROOT / 'wp-mu-plugins' / A.U.D.MAIN).resolve()
        real_read = Path.read_bytes
        # Keep the historical v1.0.4 release fixture without loosening production pins.
        self.stack.enter_context(patch.object(Path, 'read_bytes', autospec=True,
            side_effect=lambda path: historical if path.resolve() == controller else real_read(path)))
        self.original, self.candidate, self.files, self.hashes, self.checker = A.local_release()
        self.assets = {U.D.DIR + '/' + name: (A.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')
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
        style = A.STYLE if self.r.files[U.TARGET] == self.candidate else A.B.STYLE
        return ('<html>' + style + '</html>').encode(), 'text/html'

    def deploy(self):
        U.deploy(self.original, self.candidate, self.files, self.hashes, self.checker)

    def backup(self):
        return next(p for p in self.r.dirs if p.startswith(A.PREFIX))

    def assert_assets_unchanged(self):
        self.assertEqual({p: v for p, v in self.r.files.items()
                          if p.startswith(U.D.DIR + '/') and p != U.TARGET}, self.assets)
        self.assertNotIn(U.D.LOCK, self.r.dirs)

    def test_exact_scoped_helper_invocation_style_and_version_only(self):
        self.assertEqual(U.sha(self.original), A.OLD_SHA)
        restored = self.candidate.replace(A.resource_block().encode(), A.B.resource_block().encode(), 1)
        restored = restored.replace((A.HELPER + '\n\n').encode(), b'', 1)
        restored = restored.replace((A.INCLUDE + A.CALL).encode(), A.INCLUDE.encode(), 1)
        self.assertEqual(restored.replace(b' * Version: 1.0.4', b' * Version: 1.0.3', 1), self.original)
        self.assertEqual(A.STYLE, A.B.STYLE[:-8] + ''.join(A.RULES) + '</style>')
        with self.assertRaisesRegex(RuntimeError, 'Pinned v1.0.3'):
            A.patch(self.original + b'drift')

    def test_all_assets_and_native_popup_handler_match_pinned_release(self):
        for name in self.files:
            if name != U.D.MAIN:
                original = subprocess.check_output(['git', 'show', A.BASE + ':wp-mu-plugins/' + name], cwd=A.ROOT)
                self.assertEqual(original, self.assets[U.D.DIR + '/' + name], name)
        name = 'wp-mu-plugins/harmat-unified-offer-modal.php'
        native = (A.ROOT / name).read_bytes().replace(b'\r\n', b'\n')
        self.assertEqual(native, subprocess.check_output(['git', 'show', A.BASE + ':' + name], cwd=A.ROOT))
        self.assertIn(b"if (href.indexOf('#opal-contactform-popup') !== -1) return true;", native)

    def test_fresh_backup_then_only_controller_changes(self):
        self.deploy()
        self.assertEqual(self.r.files[U.TARGET], self.candidate)
        self.assertEqual(self.r.files[self.backup() + '/original.php'], self.original)
        self.assertEqual(self.r.files[self.backup() + '/candidate.php'], self.candidate)
        self.assertEqual([p for op, p in self.r.events if op == 'renamed'], [U.TARGET])
        self.assertEqual(self.filters.call_count, 2)
        self.assert_assets_unchanged()

    def test_verify_and_rollback_restore_only_pinned_balance_controller(self):
        self.deploy()
        U.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes)
        U.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes, True)
        self.assertEqual(self.r.files[U.TARGET], self.original)
        self.assert_assets_unchanged()

    def test_public_failure_automatically_restores_controller_not_business_data(self):
        self.proof.side_effect = RuntimeError('public regression')
        with self.assertRaisesRegex(RuntimeError, 'public regression'):
            self.deploy()
        self.assertEqual(self.r.files[U.TARGET], self.original)
        self.assert_assets_unchanged()

    def test_foreign_live_controller_is_never_overwritten(self):
        self.r.files[U.TARGET] = b'foreign'
        with self.assertRaisesRegex(RuntimeError, 'ownership drift'):
            self.deploy()
        self.assertEqual(self.r.files[U.TARGET], b'foreign')
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_cli_is_offline_by_default_and_requires_exact_new_pin(self):
        with patch('sys.argv', ['actions.py']):
            U.main()
        self.assertIn('OFFLINE_NO_WRITES', self.out.getvalue())
        for args in (['--deploy'], ['--verify', 'backup'], ['--rollback', 'backup', '--expect', 'bad']):
            with patch('sys.argv', ['actions.py'] + args), self.assertRaisesRegex(RuntimeError, 'Exact reviewed'):
                U.main()
        self.assertEqual(self.r.events, [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
