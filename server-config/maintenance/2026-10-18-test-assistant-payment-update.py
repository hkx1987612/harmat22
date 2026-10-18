"""Payment-copy scope and fake atomic recovery; no real SSH/HTTP."""
import contextlib
import importlib.util
import io
import json
from pathlib import Path
import socket
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parent


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, HERE / filename)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


V = load('payment_under_test', '2026-10-18-update-assistant-payment.py')
S = load('payment_fake_transport', '2026-10-18-test-home-header-flow-update.py')
U = V.configure()


class Remote(S.FakeRemote):
    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        return super().info(path)

    def run(self, command):
        if command != U.H.WP + ' eval ' + V.shlex.quote(V.REFLECT):
            raise AssertionError('Unexpected remote command')
        data = self.files[V.TARGET]
        return json.dumps({'version': '0.4.1' if U.sha(data) == V.NEW_SHA else '0.4.0', 'answers': V.answers(data)})


class PaymentUpdate(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NETWORK FORBIDDEN')))
        # Historical0.4.1 transport fixture stays runnable after the newer policy release.
        historical = V.subprocess.check_output(['git', 'show', 'c25ba61:' + V.SOURCE], cwd=V.ROOT)
        read_bytes = Path.read_bytes
        self.stack.enter_context(patch.object(Path, 'read_bytes', lambda path: historical
            if path == V.ROOT / V.SOURCE else read_bytes(path)))
        self.original, self.candidate, self.files, self.hashes, self.checker = V.local_release()
        self.assets = {U.D.DIR + '/' + name: (V.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')
                       for name in self.files if name != U.D.MAIN}
        self.assets.update({U.D.DIR + '/' + name: (V.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')
                            for name in V.PROTECTED})
        self.stack.enter_context(patch.object(S, 'M', U))
        self.r = Remote(self.original, self.assets)
        self.r.dirs.update((U.H.LIVE + '/wp-content/plugins', V.TARGET.rsplit('/', 1)[0]))
        self.stack.enter_context(patch.object(U.H, 'Remote', return_value=self.r))
        self.stack.enter_context(patch.object(U.D, 'snapshot', return_value={'protected': 'unchanged'}))
        self.stack.enter_context(patch.object(U.D, 'assert_home', return_value={'home': True}))
        self.stack.enter_context(patch.object(U.D, 'get', return_value=(b'<html>native</html>', 'text/html')))
        self.proof = self.stack.enter_context(patch.object(U.D, 'public_proof', return_value={'routes': 9}))
        self.stack.enter_context(patch.object(U.D, 'validate_native', return_value={'offer_checks': 35, 'antispam_checks': 17}))
        self.out = self.stack.enter_context(contextlib.redirect_stdout(io.StringIO()))

    def deploy(self):
        U.deploy(self.original, self.candidate, self.files, self.hashes, self.checker)

    def backup(self):
        return next(p for p in self.r.dirs if p.startswith(V.PREFIX))

    def unchanged(self):
        self.assertEqual({p: v for p, v in self.r.files.items()
                          if p.startswith(U.D.DIR + '/')}, self.assets)
        self.assertNotIn(U.D.LOCK, self.r.dirs)

    def test_only_payment_copy_version_and_existing_endpoints_unchanged(self):
        restored = self.candidate.replace(V.payment_block(self.candidate), V.payment_block(self.original), 1)
        self.assertEqual(restored.replace(b"const VERSION = '0.4.1'", b"const VERSION = '0.4.0'", 1), self.original)
        for text in V.answers(self.candidate).values():
            self.assertNotIn('2026', text)
            self.assertNotIn('2027-03-31', text)
            self.assertIn('25%', text)

    def test_foreign_or_non_copy_candidate_refused(self):
        for a, b in ((self.original + b'x', self.candidate), (self.original, self.candidate + b'x')):
            with self.assertRaises(RuntimeError):
                V.validate(a, b)
        bad = self.candidate.replace(b"'/ask'", b"'/new-ask'", 1)
        with patch.object(V, 'NEW_SHA', U.sha(bad)), self.assertRaisesRegex(RuntimeError, 'outside payment'):
            V.validate(self.original, bad)

    def test_backup_before_atomic_plugin_only_replacement(self):
        self.deploy()
        self.assertEqual(self.r.files[self.backup() + '/original.php'], self.original)
        self.assertEqual(self.r.files[V.TARGET], self.candidate)
        self.assertEqual([p for op, p in self.r.events if op == 'renamed'], [V.TARGET])
        self.unchanged()

    def test_independent_verify_and_exact_copy_rollback(self):
        self.deploy()
        U.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes)
        U.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes, True)
        self.assertEqual(self.r.files[V.TARGET], self.original)
        self.unchanged()

    def test_failure_after_activation_restores_only_plugin(self):
        self.proof.side_effect = RuntimeError('public regression')
        with self.assertRaisesRegex(RuntimeError, 'public regression'):
            self.deploy()
        self.assertEqual(self.r.files[V.TARGET], self.original)
        self.unchanged()

    def test_foreign_live_file_is_not_overwritten(self):
        self.r.files[V.TARGET] = b'foreign'
        with self.assertRaisesRegex(RuntimeError, 'ownership drift'):
            self.deploy()
        self.assertEqual(self.r.files[V.TARGET], b'foreign')
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_offline_and_exact_expect_guard(self):
        with patch('sys.argv', ['payment.py']):
            U.main()
        self.assertIn('OFFLINE_NO_WRITES', self.out.getvalue())
        with patch('sys.argv', ['payment.py', '--deploy']), self.assertRaisesRegex(RuntimeError, 'Exact reviewed'):
            U.main()
        self.assertEqual(self.r.events, [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
