"""Offline compact adapter tests using the unchanged reviewed transport and fake SSH."""
import contextlib
import importlib.util
import io
import json
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


C = load('compact_under_test', '2026-10-18-update-home-header-compact.py')
SHARED = load('compact_fake_transport', '2026-10-18-test-home-header-flow-update.py')
U = C.configure()


class FakeRemote(SHARED.FakeRemote):
    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        return super().info(path)


class CompactTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NETWORK FORBIDDEN')))
        base = subprocess.check_output(['git', 'show', C.U.BASE + ':wp-mu-plugins/' + C.U.D.MAIN], cwd=C.ROOT)
        historical = C.patch(C.U.patch(base))
        controller = (C.ROOT / 'wp-mu-plugins' / C.U.D.MAIN).resolve()
        real_read = Path.read_bytes
        # Preserve this version-specific updater's pins after later releases.
        self.stack.enter_context(patch.object(Path, 'read_bytes', autospec=True,
            side_effect=lambda path: historical if path.resolve() == controller else real_read(path)))
        self.original, self.candidate, self.files, self.hashes, self.checker = C.local_release()
        self.assets = {U.D.DIR + '/' + name: (C.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')
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
        style = C.STYLE if self.r.files[U.TARGET] == self.candidate else C.ORIGINAL_STYLE
        return ('<html>' + style + '</html>').encode(), 'text/html'

    def deploy(self):
        U.deploy(self.original, self.candidate, self.files, self.hashes, self.checker)

    def backup(self):
        return next(p for p in self.r.dirs if p.startswith(C.PREFIX))

    def assert_assets_unchanged(self):
        self.assertEqual({p: v for p, v in self.r.files.items()
                          if p.startswith(U.D.DIR + '/') and p != U.TARGET}, self.assets)
        self.assertNotIn(U.D.LOCK, self.r.dirs)

    def test_reconstruct_exact_released_v101_and_only_compact_patch(self):
        self.assertEqual(U.sha(self.original), C.OLD_SHA)
        self.assertIn(b'Version: 1.0.2', self.candidate)
        restored = self.candidate.replace(C.resource_block().encode(),
                                         ("        $resources='" + C.ORIGINAL_STYLE + "';").encode())
        restored = restored.replace(b' * Version: 1.0.2', b' * Version: 1.0.1')
        self.assertEqual(restored, self.original)
        self.assertEqual(U.STYLE, C.STYLE)
        self.assertTrue(U.PREFIX.endswith('/home-header-compact-'))

    def test_compact_transport_deploys_controller_only_with_fresh_evidence(self):
        self.deploy()
        self.assertEqual(self.r.files[U.TARGET], self.candidate)
        self.assertTrue(self.backup().startswith(C.PREFIX))
        self.assertEqual(self.r.files[self.backup() + '/original.php'], self.original)
        self.assertEqual(self.r.files[self.backup() + '/candidate.php'], self.candidate)
        self.assertEqual([p for op, p in self.r.events if op == 'renamed'], [U.TARGET])
        self.assertEqual(self.filters.call_count, 2)
        self.assert_assets_unchanged()

    def test_compact_verify_and_rollback_keep_v101_flow_style(self):
        self.deploy()
        U.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes)
        U.verify_or_rollback(self.backup(), self.original, self.candidate, self.files, self.hashes, True)
        self.assertEqual(self.r.files[U.TARGET], self.original)
        self.assertIn(C.ORIGINAL_STYLE.encode(), self.r.files[U.TARGET])
        self.assertNotIn(C.STYLE.encode(), self.r.files[U.TARGET])
        self.assert_assets_unchanged()

    def test_compact_public_failure_rolls_back_only_owned_controller(self):
        self.proof.side_effect = RuntimeError('public regression')
        with self.assertRaisesRegex(RuntimeError, 'public regression'):
            self.deploy()
        self.assertEqual(self.r.files[U.TARGET], self.original)
        self.assert_assets_unchanged()

    def test_compact_rejects_unexpected_live_version(self):
        self.r.files[U.TARGET] = b'foreign'
        with self.assertRaisesRegex(RuntimeError, 'ownership drift'):
            self.deploy()
        self.assertEqual(self.r.files[U.TARGET], b'foreign')
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_compact_cli_default_has_no_remote_calls_and_pin_is_required(self):
        with patch('sys.argv', ['compact.py']):
            U.main()
        self.assertIn('OFFLINE_NO_WRITES', self.out.getvalue())
        for args in (['--deploy'], ['--verify', 'backup'], ['--rollback', 'backup', '--expect', 'bad']):
            with patch('sys.argv', ['compact.py'] + args), self.assertRaisesRegex(RuntimeError, 'Exact reviewed'):
                U.main()
        self.assertEqual(self.r.events, [])

    def test_cssom_proves_all_selectors_scoped_and_no_form_or_global_changes(self):
        script = r'''
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require(path.resolve('outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const style = JSON.parse(fs.readFileSync(0, 'utf8'));
const dom = new JSDOM('<!doctype html><html><head>' + style + '</head><body></body></html>');
let count = 0;
function inspect(rules) {
  for (const rule of rules) {
    if (rule.cssRules) { inspect(rule.cssRules); continue; }
    assert(rule.selectorText, 'Only style/media rules allowed');
    for (const selector of rule.selectorText.split(',')) {
      assert(/^body\.home\.page-id-164(?:\.opal-header-absolute)? #(?:harmat-home-family|masthead|my-sticky-header)(?:\b|[ >.])/.test(selector.trim()), selector);
      assert(!/(?:form|input|textarea|h22-offer|harmat-internal-language)/.test(selector));
    }
    for (const prop of ['display', 'visibility', 'opacity', 'transform', 'z-index', 'pointer-events']) {
      assert.equal(rule.style.getPropertyValue(prop), '', prop + ' must remain native');
    }
    assert(!/(?:vw|vmin|vmax)\b/.test(rule.style.getPropertyValue('font-size')));
    count++;
  }
}
inspect(dom.window.document.styleSheets[0].cssRules);
assert(count >= 25);
dom.window.close();
process.stdout.write(JSON.stringify({scoped_rules: count}));
'''
        result = subprocess.run(['node', '-e', script], input=json.dumps(C.STYLE), text=True,
                                cwd=C.ROOT, capture_output=True, check=True)
        self.assertGreaterEqual(json.loads(result.stdout)['scoped_rules'], 25)


if __name__ == '__main__':
    unittest.main(verbosity=2)
