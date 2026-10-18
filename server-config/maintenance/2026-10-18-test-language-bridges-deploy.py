"""Offline exact preimage, staging and source-only recovery checks."""
import importlib.util
import json
from pathlib import Path
import subprocess
import unittest

HERE = Path(__file__).resolve().parent
def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, HERE / filename)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module

M = load('bridges_refresh', '2026-10-18-refresh-language-bridges.py').M
FakeRemote = load('bridges_fake', '2026-10-17-test-language-header-deploy.py').FakeRemote

class BridgeDeployTests(unittest.TestCase):
    def previous(self):
        old = {n: subprocess.check_output(['git', 'show', 'f89f78c:wp-mu-plugins/' + n], cwd=M.D.ROOT) for n in M.D.FILES}
        for name, pin in M.PRIOR.items():
            self.assertEqual(M.D.sha(old[name]), pin)
        remote = FakeRemote()
        remote.files.update({M.D.DIR + '/' + n: data for n, data in old.items()})
        remote.files[M.PREVIOUS + '/manifest.json'] = json.dumps({'pins': {n: M.D.sha(data) for n, data in old.items()}}).encode()
        return remote, old

    def test_only_runtime_dictionary_controller(self):
        self.assertEqual(M.CHANGED, (M.D.ASSET + '/runtime.js', M.D.ASSET + '/zh-Hans.json', M.D.MAIN))

    def test_exact_two_pin_version_controller_delta(self):
        r, old = self.previous()
        blobs, pins, digest = M.bundle()
        self.assertEqual(M.match_previous(r, blobs, pins), old)
        self.assertEqual(len(digest), 64)

    def test_all_existing_dictionary_values_preserved(self):
        _, old = self.previous()
        before = json.loads(old[M.D.ASSET + '/zh-Hans.json'])
        after = json.loads(M.bundle()[0][M.D.ASSET + '/zh-Hans.json'])
        self.assertEqual(len(before), 1187)
        self.assertTrue(all(after.get(k) == v for k, v in before.items()))

    def test_unchanged_three_assets_enforced(self):
        for name in set(M.D.FILES) - set(M.CHANGED):
            r, _ = self.previous()
            blobs, pins, _ = M.bundle()
            blobs[name] += b'\n'
            pins[name] = M.D.sha(blobs[name])
            with self.assertRaises(RuntimeError):
                M.match_previous(r, blobs, pins)

    def test_foreign_controller_rejected(self):
        r, _ = self.previous()
        blobs, pins, _ = M.bundle()
        blobs[M.D.MAIN] += b'\n'
        with self.assertRaises(RuntimeError):
            M.match_previous(r, blobs, pins)

    def test_foreign_live_file_rejected(self):
        r, _ = self.previous()
        blobs, pins, _ = M.bundle()
        r.files[M.D.DIR + '/' + M.CHANGED[0]] = b'foreign'
        with self.assertRaises(RuntimeError):
            M.match_previous(r, blobs, pins)

    def test_php_lint_before_atomic_rename(self):
        r = FakeRemote()
        target = M.D.DIR + '/' + M.D.MAIN
        r.files[target] = b'old'
        M.replace(r, M.D.MAIN, b'new', b'old')
        actions = [e[0] for e in r.events]
        self.assertLess(actions.index('lint'), actions.index('rename'))
        self.assertEqual(r.files, {target: b'new'})

    def test_failed_php_lint_keeps_original(self):
        r = FakeRemote()
        target = M.D.DIR + '/' + M.D.MAIN
        r.files[target] = b'old'
        r.reject_lint = True
        with self.assertRaises(RuntimeError):
            M.replace(r, M.D.MAIN, b'new', b'old')
        self.assertEqual(r.files, {target: b'old'})

    def test_owned_reverse_replacement_not_foreign_data(self):
        r = FakeRemote()
        target = M.D.DIR + '/' + M.CHANGED[0]
        r.files[target] = b'old'
        M.replace(r, M.CHANGED[0], b'new', b'old')
        M.replace(r, M.CHANGED[0], b'old', b'new')
        self.assertEqual(r.files, {target: b'old'})
        r.files[target] = b'foreign'
        with self.assertRaises(RuntimeError):
            M.replace(r, M.CHANGED[0], b'old', b'new')
        self.assertEqual(r.files, {target: b'foreign'})

if __name__ == '__main__':
    unittest.main()
