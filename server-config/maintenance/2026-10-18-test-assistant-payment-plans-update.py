"""Pinned payment choices and plugin-only recovery; no live network."""
import contextlib
import importlib.util
import io
import json
from pathlib import Path
import socket
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parent


def load(name, file):
    spec = importlib.util.spec_from_file_location(name, HERE / file)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


V = load('plans_update', '2026-10-18-update-assistant-payment-plans.py')
S = load('plans_fake', '2026-10-18-test-home-header-flow-update.py')
U = V.configure()


class Remote(S.FakeRemote):
    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        return super().info(path)

    def run(self, command):
        if command != U.H.WP + ' eval ' + V.shlex.quote(V.A.REFLECT):
            raise AssertionError('Unexpected command')
        data = self.files[V.TARGET]
        return json.dumps({'version': '0.4.2' if U.sha(data) == V.NEW_SHA else '0.4.1', 'answers': V.answers(data)})


class Plans(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NO NETWORK')))
        self.old, self.new, self.files, self.hashes, self.checker = V.local_release()
        names = tuple(n for n in self.files if n != U.D.MAIN) + V.A.PROTECTED
        self.assets = {U.D.DIR + '/' + n: (V.ROOT / 'wp-mu-plugins' / n).read_bytes().replace(b'\r\n', b'\n') for n in names}
        self.stack.enter_context(patch.object(S, 'M', U))
        self.r = Remote(self.old, self.assets)
        self.r.dirs.update((U.H.LIVE + '/wp-content/plugins', V.TARGET.rsplit('/', 1)[0]))
        self.stack.enter_context(patch.object(U.H, 'Remote', return_value=self.r))
        self.stack.enter_context(patch.object(U.D, 'snapshot', return_value={'protected': 'unchanged'}))
        self.stack.enter_context(patch.object(U.D, 'assert_home', return_value={'home': True}))
        self.stack.enter_context(patch.object(U.D, 'get', return_value=(b'<html>native</html>', 'text/html')))
        self.proof = self.stack.enter_context(patch.object(U.D, 'public_proof', return_value={'routes': 9}))
        self.stack.enter_context(patch.object(U.D, 'validate_native', return_value={'offer_checks': 35, 'antispam_checks': 17}))
        self.stack.enter_context(contextlib.redirect_stdout(io.StringIO()))

    def deploy(self):
        U.deploy(self.old, self.new, self.files, self.hashes, self.checker)

    def backup(self):
        return next(p for p in self.r.dirs if p.startswith(V.PREFIX))

    def test_exact_pins_and_only_two_faq_methods_and_version(self):
        V.validate(self.old, self.new)
        self.assertEqual(U.sha(self.new), V.NEW_SHA)
        self.assertEqual(len(V.answers(self.new)), 3)
        self.assertIn("apartment's final signed offer", V.answers(self.new)['en'])
        for text in V.answers(self.new).values():
            self.assertIn('8%', text)
            self.assertIn('4%', text)

    def test_foreign_bytes_and_endpoint_change_refused(self):
        with self.assertRaises(RuntimeError):
            V.validate(self.old + b'x', self.new)
        bad = self.new.replace(b"'/ask'", b"'/different-ask'", 1)
        with patch.object(V, 'NEW_SHA', U.sha(bad)), self.assertRaisesRegex(RuntimeError, 'outside payment'):
            V.validate(self.old, bad)

    def test_private_original_before_plugin_only_atomic_replace(self):
        self.deploy()
        self.assertEqual(self.r.files[self.backup() + '/original.php'], self.old)
        self.assertEqual([p for op, p in self.r.events if op == 'renamed'], [V.TARGET])
        self.assertEqual({p: v for p, v in self.r.files.items() if p.startswith(U.D.DIR + '/')}, self.assets)

    def test_verify_and_guarded_source_only_rollback(self):
        self.deploy()
        U.verify_or_rollback(self.backup(), self.old, self.new, self.files, self.hashes)
        U.verify_or_rollback(self.backup(), self.old, self.new, self.files, self.hashes, True)
        self.assertEqual(self.r.files[V.TARGET], self.old)
        self.assertNotIn(U.D.LOCK, self.r.dirs)

    def test_failed_proof_recovers_exact_plugin_only(self):
        self.proof.side_effect = RuntimeError('public proof')
        with self.assertRaisesRegex(RuntimeError, 'public proof'):
            self.deploy()
        self.assertEqual(self.r.files[V.TARGET], self.old)

    def test_foreign_live_plugin_retained(self):
        self.r.files[V.TARGET] = b'foreign'
        with self.assertRaisesRegex(RuntimeError, 'ownership'):
            self.deploy()
        self.assertEqual(self.r.files[V.TARGET], b'foreign')


if __name__ == '__main__':
    unittest.main()
