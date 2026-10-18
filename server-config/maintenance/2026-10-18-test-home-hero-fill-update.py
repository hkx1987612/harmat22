"""Offline hero-only release guards and fake transport; network connections forbidden."""
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
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


V = load('fill_under_test', '2026-10-18-update-home-hero-fill.py')
SHARED = load('fill_fake_transport', '2026-10-18-test-home-header-flow-update.py')
U = V.configure()


class FakeRemote(SHARED.FakeRemote):
    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        return super().info(path)


class FillTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NETWORK FORBIDDEN')))
        self.original, self.candidate, self.files, self.hashes, self.checker = V.local_release()
        self.assets = {U.D.DIR + '/' + name: (V.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')
                       for name in self.files if name != U.D.MAIN}
        self.assets.update({U.D.DIR + '/' + name: (V.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')
                            for name in V.PROTECTED})
        self.stack.enter_context(patch.object(SHARED, 'M', U))
        self.r = FakeRemote(self.original, self.assets)
        self.stack.enter_context(patch.object(U.H, 'Remote', return_value=self.r))
        self.stack.enter_context(patch.object(U.D, 'snapshot', return_value={'protected': 'unchanged'}))
        self.stack.enter_context(patch.object(U.D, 'assert_home', return_value={'home': True}))
        self.stack.enter_context(patch.object(U.D, 'get', side_effect=self.get))
        self.proof = self.stack.enter_context(patch.object(U.D, 'public_proof', return_value={'routes': 9, 'inventory': 124}))
        self.filters = self.stack.enter_context(patch.object(U.D, 'validate_native', return_value={'offer_checks': 35, 'antispam_checks': 17}))
        self.stack.enter_context(patch.object(V, 'policy'))
        self.out = self.stack.enter_context(contextlib.redirect_stdout(io.StringIO()))

    def get(self, url):
        if url == V.C.JOURNAL:
            return b'<html>unchanged journal</html>', 'text/html'
        self.assertEqual(url, U.D.URL)
        style = V.STYLE if self.r.files[U.TARGET] == self.candidate else V.inline_style(self.original)
        return ('<html>' + style + '</html>').encode(), 'text/html'

    def deploy(self):
        U.deploy(self.original, self.candidate, self.files, self.hashes, self.checker)

    def backup(self):
        return next(p for p in self.r.dirs if p.startswith(V.PREFIX))

    def unchanged(self):
        self.assertEqual({p: v for p, v in self.r.files.items()
                          if p.startswith(U.D.DIR + '/') and p != U.TARGET}, self.assets)
        self.assertNotIn(U.D.LOCK, self.r.dirs)

    def test_only_final_hero_css_and_version_differ(self):
        before, after = V.hero_block(self.original), V.hero_block(self.candidate)
        self.assertEqual(self.candidate.replace(after, before, 1).replace(b' * Version: 1.0.9', b' * Version: 1.0.8', 1), self.original)
        self.assertEqual(U.sha(self.original), V.OLD_SHA)
        self.assertEqual(U.sha(self.candidate), V.NEW_SHA)
        self.assertEqual(V.STYLE.count('<style '), 1)
        self.assertTrue(V.STYLE.endswith('</style>'))

    def test_foreign_original_or_candidate_refused(self):
        for before, after in ((self.original + b'drift', self.candidate),
                              (self.original, self.candidate + b'drift'),
                              (self.original, self.candidate.replace(b'\n', b'\r\n'))):
            with self.assertRaises(RuntimeError):
                V.validate_candidate(before, after)

    def test_non_hero_change_refused_even_if_new_pin_reviewed_wrongly(self):
        altered = self.candidate.replace(b'function harmat_home_family_hero_actions', b'function changed_actions', 1)
        with patch.object(V, 'NEW_SHA', U.sha(altered)), self.assertRaisesRegex(RuntimeError, 'Only final hero'):
            V.validate_candidate(self.original, altered)

    def test_backup_and_only_controller_atomically_replaced(self):
        self.deploy()
        self.assertEqual(self.r.files[self.backup() + '/original.php'], self.original)
        self.assertEqual(self.r.files[self.backup() + '/candidate.php'], self.candidate)
        self.assertEqual(self.r.files[U.TARGET], self.candidate)
        self.assertEqual([p for op, p in self.r.events if op == 'renamed'], [U.TARGET])
        self.assertEqual(self.filters.call_count, 2)
        self.unchanged()

    def test_verify_and_controller_only_rollback(self):
        self.deploy()
        U.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes)
        U.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes, True)
        self.assertEqual(self.r.files[U.TARGET], self.original)
        self.unchanged()

    def test_failure_recovers_without_business_restore(self):
        self.proof.side_effect = RuntimeError('public failure')
        with self.assertRaisesRegex(RuntimeError, 'public failure'):
            self.deploy()
        self.assertEqual(self.r.files[U.TARGET], self.original)
        self.unchanged()

    def test_foreign_live_bytes_retained(self):
        self.r.files[U.TARGET] = b'foreign editor'
        with self.assertRaisesRegex(RuntimeError, 'ownership drift'):
            self.deploy()
        self.assertEqual(self.r.files[U.TARGET], b'foreign editor')
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_offline_default_and_exact_expect_required(self):
        with patch('sys.argv', ['fill.py']):
            U.main()
        self.assertIn('OFFLINE_NO_WRITES', self.out.getvalue())
        for args in (['--deploy'], ['--verify', 'backup'], ['--rollback', 'backup', '--expect', 'bad']):
            with patch('sys.argv', ['fill.py'] + args), self.assertRaisesRegex(RuntimeError, 'Exact reviewed'):
                U.main()
        self.assertEqual(self.r.events, [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
