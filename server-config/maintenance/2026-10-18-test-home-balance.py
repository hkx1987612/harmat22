"""Offline presentation-only patch/composition tests. No SSH/HTTP or browser claims."""
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


B = load('balance_under_test', '2026-10-18-update-home-balance.py')
SHARED = load('balance_fake_transport', '2026-10-18-test-home-header-flow-update.py')
U = B.configure()


class FakeRemote(SHARED.FakeRemote):
    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        return super().info(path)


class BalanceTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NETWORK FORBIDDEN')))
        self.original, self.candidate, self.files, self.hashes, self.checker = B.local_release()
        self.assets = {U.D.DIR + '/' + name: (B.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')
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
        style = B.STYLE if self.r.files[U.TARGET] == self.candidate else B.C.STYLE
        return ('<html>' + style + '</html>').encode(), 'text/html'

    def deploy(self):
        U.deploy(self.original, self.candidate, self.files, self.hashes, self.checker)

    def backup(self):
        return next(p for p in self.r.dirs if p.startswith(B.PREFIX))

    def assert_assets_unchanged(self):
        self.assertEqual({p: v for p, v in self.r.files.items()
                          if p.startswith(U.D.DIR + '/') and p != U.TARGET}, self.assets)
        self.assertNotIn(U.D.LOCK, self.r.dirs)

    def test_exact_append_only_style_version_patch_keeps_php_logic_and_header(self):
        self.assertEqual(U.sha(self.original), B.OLD_SHA)
        restored = self.candidate.replace(B.resource_block().encode(), B.C.resource_block().encode(), 1)
        self.assertEqual(restored.replace(b' * Version: 1.0.3', b' * Version: 1.0.2', 1), self.original)
        self.assertEqual(B.STYLE, B.C.STYLE[:-8] + ''.join(B.RULES) + '</style>')
        self.assertTrue(all(rule.encode() in self.candidate for rule in B.C.RULES))
        with self.assertRaisesRegex(RuntimeError, 'Pinned v1.0.2'):
            B.patch(self.original + b'drift')

    def test_all_noncontroller_bundle_assets_stay_equal_to_released_git(self):
        for name in self.files:
            if name != U.D.MAIN:
                original = subprocess.check_output(['git', 'show', B.BASE + ':wp-mu-plugins/' + name], cwd=B.ROOT)
                self.assertEqual(original, self.assets[U.D.DIR + '/' + name], name)

    def test_fresh_backup_then_only_controller_changes(self):
        self.deploy()
        self.assertEqual(self.r.files[U.TARGET], self.candidate)
        self.assertEqual(self.r.files[self.backup() + '/original.php'], self.original)
        self.assertEqual(self.r.files[self.backup() + '/candidate.php'], self.candidate)
        self.assertEqual([p for op, p in self.r.events if op == 'renamed'], [U.TARGET])
        self.assertEqual(self.filters.call_count, 2)
        self.assert_assets_unchanged()

    def test_verify_and_rollback_restore_only_pinned_compact_controller(self):
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
        with patch('sys.argv', ['balance.py']):
            U.main()
        self.assertIn('OFFLINE_NO_WRITES', self.out.getvalue())
        for args in (['--deploy'], ['--verify', 'backup'], ['--rollback', 'backup', '--expect', 'bad']):
            with patch('sys.argv', ['balance.py'] + args), self.assertRaisesRegex(RuntimeError, 'Exact reviewed'):
                U.main()
        self.assertEqual(self.r.events, [])

    def test_cssom_scopes_sizing_responsive_rhythm_and_soft_band_contrast(self):
        script = r'''
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require(path.resolve('outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const input = JSON.parse(fs.readFileSync(0, 'utf8'));
const dom = new JSDOM('<!doctype html><html><head><style>' + input.css + '</style></head><body></body></html>');
const scope = 'body.home.page-id-164 #harmat-home-family';
const collected = [];
function inspect(rules, media = '') {
  for (const r of rules) {
    if (r.cssRules) { inspect(r.cssRules, r.conditionText || r.media.mediaText); continue; }
    assert(r.selectorText, 'Only style/media rules');
    for (const s of r.selectorText.split(',')) {
      assert(s.trim() === scope || s.trim().startsWith(scope + ' '), s);
      assert(!/(?:#masthead|my-sticky-header|form|input|textarea|h22-offer|harmat-internal-language)/.test(s));
    }
    for (const prop of ['display', 'visibility', 'opacity', 'transform', 'position', 'z-index', 'pointer-events', 'height']) {
      assert.equal(r.style.getPropertyValue(prop), '', prop + ' must remain unchanged');
    }
    assert(!/(?:vw|vmin|vmax)/.test(r.style.getPropertyValue('font-size')));
    collected.push({r, media});
  }
}
inspect(dom.window.document.styleSheets[0].cssRules);
assert.equal(collected.length, 33);
function active(media, width) {
  return (!/max-width:(\d+)px/.test(media) || width <= Number(media.match(/max-width:(\d+)px/)[1]))
    && (!/min-width:(\d+)px/.test(media) || width >= Number(media.match(/min-width:(\d+)px/)[1]));
}
function own(selector, property, width) {
  let value = '';
  for (const {r, media} of collected) {
    if (active(media, width) && r.selectorText.split(',').some(s => s.trim() === scope + (selector ? ' ' + selector : ''))) {
      value = r.style.getPropertyValue(property) || value;
    }
  }
  return value;
}
for (const width of [320, 390, 620, 621, 767, 900, 1000, 1023, 1280, 1920]) {
  assert.equal(own('h2', 'font-size', width), width <= 620 ? '28px' : width <= 900 ? '32px' : '36px');
  assert.equal(own('.hhf-section', 'padding-block', width), width <= 620 ? '40px' : '56px');
  assert.equal(own('.hhf-highlight-grid dd>strong', 'min-height', width), width <= 620 ? '60px' : '46px');
  assert.equal(own('.hhf-price strong:not(.hhf-price-hidden)', 'font-size', width), '24px');
  assert.equal(own('.hhf-apartment-facts dt', 'font-size', width), '14px');
  assert.equal(own('.hhf-apartment-top h3', 'font-size', width), '21px');
  assert.equal(own('.hhf-panorama-video-card h3', 'font-size', width), '23px');
  assert.equal(own('.hhf-panorama-detail h3', 'font-size', width), '24px');
  assert.equal(own('.hhf-apartment-grid', 'grid-template-columns', width), width >= 621 && width <= 1000 ? 'repeat(2,minmax(0,1fr))' : '');
}
assert(!collected.some(({r}) => /h1|hhf-hero|hhf-panorama-(?:screen|viewer|tool|tabs)/.test(r.selectorText)));
const backgrounds = {'': '#f8f8f6', '.hhf-highlights':'#f6f5f2', '.hhf-apartments':'#f5f4f2',
  '.hhf-journal':'#edf2ef', '.hhf-everyday':'#f8f8f6', '.hhf-panorama':'#fff',
  '.hhf-team':'#fff', '.hhf-chooser':'#e9f0eb', '.hhf-contact':'#f1f0ed'};
function rgb(hex) {
  const h = hex.slice(1); const full = h.length === 3 ? h.replace(/./g, x => x + x) : h;
  return full.match(/../g).map(s => parseInt(s, 16) / 255);
}
function luminance(hex) {
  const c = rgb(hex).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
  return .2126 * c[0] + .7152 * c[1] + .0722 * c[2];
}
const muted = own('', '--muted', 1280); assert.equal(muted, '#58645d');
for (const [selector, hex] of Object.entries(backgrounds)) {
  const color = own(selector, 'background', 1280);
  assert.equal(color.toLowerCase(), hex);
  assert((luminance(hex) + .05) / (luminance(muted) + .05) >= 4.5, selector);
}
dom.window.close(); process.stdout.write(JSON.stringify({scoped_rules: collected.length}));
'''
        result = subprocess.run(['node', '-e', script], input=json.dumps({'css': ''.join(B.RULES)}), text=True,
                                cwd=B.ROOT, capture_output=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(json.loads(result.stdout)['scoped_rules'], 33)


if __name__ == '__main__':
    unittest.main(verbosity=2)
