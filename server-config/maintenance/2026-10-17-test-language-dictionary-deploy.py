"""Offline dictionary release ownership checks; no network or business writes."""
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

M = load('dictionary_refresh', '2026-10-17-refresh-language-dictionary.py').M
FakeRemote = load('header_fake', '2026-10-17-test-language-header-deploy.py').FakeRemote
FOLLOWUP_KEYS = ('360 nézet', 'Bemutató', 'Áttekintés', 'Látványtervek', 'Lokáció', 'Fontos')

class DictionaryDeployTests(unittest.TestCase):
    def previous(self):
        old = {n: subprocess.check_output(['git', 'show', 'bb8cd18:wp-mu-plugins/' + n], cwd=M.D.ROOT) for n in M.D.FILES}
        # Reconstruct the pinned code-only v1.0.3 checkpoint, never private HTML.
        previous_dictionary = json.loads((M.D.ROOT / 'wp-mu-plugins' / M.CHANGED[0]).read_bytes())
        for key in FOLLOWUP_KEYS:
            previous_dictionary.pop(key)
        old[M.CHANGED[0]] = (json.dumps(previous_dictionary, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()
        old[M.D.MAIN] = old[M.D.MAIN].replace(b'Version: 1.0.2', b'Version: 1.0.3', 1).replace(
            b'32e658c9ef1112e47b02af8c1fc4a10466b72c97af50ced5fbf3ce40ad7f82e4',
            M.PRIOR[M.CHANGED[0]].encode(), 1)
        for n, pin in M.PRIOR.items():
            self.assertEqual(M.D.sha(old[n]), pin)
        r = FakeRemote()
        r.files.update({M.D.DIR + '/' + n: data for n, data in old.items()})
        r.files[M.PREVIOUS + '/manifest.json'] = json.dumps({'pins': {n: M.D.sha(data) for n, data in old.items()}}).encode()
        return r, old

    def test_only_dictionary_and_controller(self):
        self.assertEqual(M.CHANGED, (M.D.ASSET + '/zh-Hans.json', M.D.MAIN))

    def test_exact_reviewed_controller_refresh(self):
        r, old = self.previous()
        blobs, pins, digest = M.bundle()
        self.assertEqual(M.match_previous(r, blobs, pins), old)
        self.assertEqual(len(digest), 64)

    def test_old_entries_are_unchanged(self):
        _, old = self.previous()
        before = json.loads(old[M.CHANGED[0]])
        after = json.loads(M.bundle()[0][M.CHANGED[0]])
        self.assertEqual(len(before), 1181)
        self.assertTrue(all(after.get(k) == v for k, v in before.items()))
        self.assertGreater(len(after), len(before))
        initial = json.loads(subprocess.check_output(['git', 'show', 'bb8cd18:wp-mu-plugins/' + M.CHANGED[0]], cwd=M.D.ROOT))
        self.assertEqual(len(initial), 1035)
        self.assertTrue(all(after.get(k) == v for k, v in initial.items()))

    def test_unchanged_four_assets_are_enforced(self):
        for name in set(M.D.FILES) - set(M.CHANGED):
            r, _ = self.previous()
            blobs, pins, _ = M.bundle()
            blobs[name] += b'\n'
            pins[name] = M.D.sha(blobs[name])
            with self.assertRaises(RuntimeError):
                M.match_previous(r, blobs, pins)

    def test_foreign_controller_change_rejected(self):
        r, _ = self.previous()
        blobs, pins, _ = M.bundle()
        blobs[M.D.MAIN] += b'\n'
        with self.assertRaises(RuntimeError):
            M.match_previous(r, blobs, pins)

    def test_foreign_live_preimage_rejected(self):
        r, _ = self.previous()
        blobs, pins, _ = M.bundle()
        r.files[M.D.DIR + '/' + M.CHANGED[0]] = b'foreign'
        with self.assertRaises(RuntimeError):
            M.match_previous(r, blobs, pins)

    def test_temporary_php_lint_before_rename(self):
        r = FakeRemote()
        target = M.D.DIR + '/' + M.D.MAIN
        r.files[target] = b'old'
        M.replace(r, M.D.MAIN, b'new', b'old')
        events = [e[0] for e in r.events]
        self.assertLess(events.index('lint'), events.index('rename'))
        self.assertEqual(r.files, {target: b'new'})

    def test_failed_lint_preserves_original(self):
        r = FakeRemote()
        target = M.D.DIR + '/' + M.D.MAIN
        r.files[target] = b'old'
        r.reject_lint = True
        with self.assertRaises(RuntimeError):
            M.replace(r, M.D.MAIN, b'new', b'old')
        self.assertEqual(r.files, {target: b'old'})

    def test_owned_source_only_reverse_replacement(self):
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
