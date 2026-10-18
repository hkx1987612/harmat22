'use strict';
// Isolated DOM tests; not browser, mobile layout or live booking evidence.
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const php = process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php';
const source = path.join(ROOT, 'wp-mu-plugins/zz-harmat-public-payment-copy.php');
const code = 'define("ABSPATH", __DIR__); function add_filter(...$a){} function add_action(...$a){} function esc_attr($s){return htmlspecialchars($s, ENT_QUOTES, "UTF-8");} function esc_html($s){return htmlspecialchars($s, ENT_QUOTES, "UTF-8");} require $argv[1]; echo harmat_ppc_html();';
const html = execFileSync(php, ['-r', code, '--', source], { encoding: 'utf8' });
const script = /<script data-hppc-runtime>([\s\S]*?)<\/script>/.exec(html)[1];
const tick = () => new Promise(resolve => setImmediate(resolve));

function fixture(t, language = 'hu', body = html) {
  const native = '<form id="h22-offer-form"><input name="name" value="Unsent draft"><textarea name="message">Customer 50% draft</textarea><select name="source"><option value="google" selected>Google</option></select><input type="checkbox" name="privacy" required></form>';
  const dom = new JSDOM(`<html data-harmat-language="${language}"><body>${body}${native}</body></html>`, { runScripts: 'outside-only', url: 'https://harmat22.hu/property/a1-1-l2/' });
  t.after(() => dom.window.close());
  const w = dom.window, d = w.document, form = d.querySelector('form');
  const before = form.outerHTML, children = Array.from(form.querySelectorAll('*'));
  for (const key of ['fetch', 'XMLHttpRequest', 'WebSocket']) w[key] = () => { throw new Error('NO NETWORK'); };
  form.addEventListener('submit', () => { throw new Error('NO SUBMISSION'); });
  w.eval(script);
  return { w, d, form, before, children, root: d.getElementById('harmat-public-payment-copy') };
}

test('Hungarian defaults and exact Chinese roundtrip preserve native draft/fields/nodes', async t => {
  const f = fixture(t);
  const original = Array.from(f.root.querySelectorAll('[data-hppc-hu]')).map(n => n.textContent);
  for (let i = 0; i < 5; i++) {
    f.d.documentElement.setAttribute('data-harmat-language', 'zh-Hans'); await tick();
    assert.equal(f.root.lang, 'zh-Hans');
    for (const n of f.root.querySelectorAll('[data-hppc-hu]')) assert.equal(n.textContent, n.getAttribute('data-hppc-zh'));
    f.d.documentElement.setAttribute('data-harmat-language', 'hu'); await tick();
    assert.deepEqual(Array.from(f.root.querySelectorAll('[data-hppc-hu]')).map(n => n.textContent), original);
  }
  assert.equal(f.form.outerHTML, f.before);
  assert.deepEqual(Array.from(f.form.querySelectorAll('*')), f.children);
  assert.equal(f.form.elements.privacy.checked, false);
});

test('Restored Chinese preference applies on first execution without waiting for another click', t => {
  const f = fixture(t, 'zh-Hans');
  assert.match(f.root.textContent, /付款安排/);
  assert.match(f.root.textContent, /2027年6月30日/);
  assert.equal(f.form.outerHTML, f.before);
});

test('Only the existing language marker drives own text, never customer input', async t => {
  const f = fixture(t);
  f.form.elements.message.value = 'New customer draft';
  f.d.documentElement.setAttribute('class', 'other'); await tick();
  assert.equal(f.root.lang, 'hu');
  f.d.documentElement.setAttribute('data-harmat-language', 'zh-Hans'); await tick();
  assert.equal(f.form.elements.message.value, 'New customer draft');
});

test('Unknown locale falls back to Hungarian and an absent owned root is harmless', t => {
  const f = fixture(t, 'en');
  assert.equal(f.root.lang, 'hu');
  assert.match(f.root.textContent, /Fizetési ütemezés/);
  fixture(t, 'hu', '<p>Unrelated content</p>');
});
