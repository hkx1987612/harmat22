"""Offline exact ownership, backup and rollback tests; no network."""
import contextlib
import importlib.util
import io
from pathlib import Path
import socket
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parent


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, HERE / filename)
    value = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(value)
    return value


V = load('top_update_test', '2026-10-19-update-home-video-top.py')
S = load('top_fake_transport', '2026-10-18-test-home-header-flow-update.py')
U = V.configure()


class FakeRemote(S.FakeRemote):
    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        return super().info(path)


class FramingTests(unittest.TestCase):
    def setUp(self):
        stack = contextlib.ExitStack()
        self.addCleanup(stack.close)
        stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NO NETWORK')))
        self.old, self.new, self.files, self.hashes, self.checker = V.local_release()
        self.assets = {U.D.DIR + '/' + n: (V.ROOT / 'wp-mu-plugins' / n).read_bytes().replace(b'\r\n', b'\n')
                       for n in self.files if n != U.D.MAIN}
        stack.enter_context(patch.object(S, 'M', U))
        self.r = FakeRemote(self.old, self.assets)
        stack.enter_context(patch.object(U.H, 'Remote', return_value=self.r))
        stack.enter_context(patch.object(U, 'state', return_value={'protected': 'unchanged'}))
        stack.enter_context(patch.object(U.D, 'get', side_effect=self.get))
        stack.enter_context(patch.object(U.D, 'assert_home'))
        self.proof = stack.enter_context(patch.object(U.D, 'public_proof', return_value={'healthy': True}))
        stack.enter_context(patch.object(U.D, 'validate_native', return_value={'offer_checks': 35, 'antispam_checks': 17}))
        stack.enter_context(patch.object(V.V, 'policy'))
        self.output = stack.enter_context(contextlib.redirect_stdout(io.StringIO()))

    def get(self, url):
        if url == V.V.C.JOURNAL:
            return b'<html>unchanged journal</html>', 'text/html'
        style = U.STYLE if self.r.files[U.TARGET] == self.new else V.V.inline_style(self.old)
        return ('<html>' + style + '</html>').encode(), 'text/html'

    def deploy(self):
        U.deploy(self.old, self.new, self.files, self.hashes, self.checker)

    def backup(self):
        return next(p for p in self.r.dirs if p.startswith(V.PREFIX))

    def test_bounded_candidate(self):
        V.validate_candidate(self.old, self.new)
        for old, new in ((self.old+b'drift', self.new), (self.old, self.new+b'drift'),
                         (self.old, self.new.replace(b'\n', b'\r\n'))):
            with self.assertRaises(RuntimeError):
                V.validate_candidate(old, new)

    def test_backup_verify_rollback(self):
        self.deploy()
        b = self.backup()
        self.assertEqual(self.r.files[b+'/original.php'], self.old)
        self.assertEqual(self.r.files[b+'/candidate.php'], self.new)
        self.assertEqual(self.r.files[U.TARGET], self.new)
        U.verify_or_rollback(b, self.old, self.new, self.files, self.hashes)
        U.verify_or_rollback(b, self.old, self.new, self.files, self.hashes, True)
        self.assertEqual(self.r.files[U.TARGET], self.old)
        self.assertNotIn(U.D.LOCK, self.r.dirs)
        self.assertEqual({p:v for p,v in self.r.files.items() if p.startswith(U.D.DIR+'/') and p != U.TARGET}, self.assets)

    def test_failure_source_only_recovery(self):
        self.proof.side_effect = RuntimeError('public failed')
        with self.assertRaisesRegex(RuntimeError, 'public failed'):
            self.deploy()
        self.assertEqual(self.r.files[U.TARGET], self.old)
        self.assertNotIn(U.D.LOCK, self.r.dirs)

    def test_foreign_change_refused(self):
        self.r.files[U.TARGET] = b'foreign'
        with self.assertRaisesRegex(RuntimeError, 'ownership drift'):
            self.deploy()
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_offline_and_gate(self):
        with patch('sys.argv', ['top.py']):
            U.main()
        self.assertIn('OFFLINE_NO_WRITES', self.output.getvalue())
        with patch('sys.argv', ['top.py', '--deploy']), self.assertRaises(RuntimeError):
            U.main()
        self.assertEqual(self.r.events, [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
