'use strict';
// Synthetic DOM only, not browser/mobile/native WordPress integration proof.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const { JSDOM, ResourceLoader } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HARMAT_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const fixture = path.join(__dirname, '2026-10-18-test-property-disclaimer-scope.php');
const render = mode => execFileSync(PHP, [fixture, mode], { encoding: 'utf8' });
const rendered = render('--render');
const fallback = render('--fallback');
const legacyPath = path.join(ROOT, 'wp-mu-plugins/harmat-migrated-snippets.php');
const legacy = fs.readFileSync(legacyPath, 'utf8').replace(/\r\n/g, '\n');
const oldCall = '    addPropertyDisclaimer();';
const newCall = "    if (<?php echo !function_exists('harmat_property_disclaimer_scope') || harmat_property_disclaimer_scope() ? 'true' : 'false'; ?> /* harmat-property-disclaimer-legacy */) addPropertyDisclaimer();";
const dictionary = JSON.parse(fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-internal-language/zh-Hans.json'), 'utf8'));
const runtime = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-internal-language/runtime.js'), 'utf8');

function domFixture(t, html) {
  const requests = [];
  class NoNetwork extends ResourceLoader {
    fetch(url) { requests.push(url); throw new Error('NETWORK_BLOCKED'); }
  }
  const dom = new JSDOM(html, { url: 'https://harmat22.hu/property/a1-f-l1/',
    runScripts: 'outside-only', resources: new NoNetwork() });
  const w = dom.window;
  for (const name of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource']) {
    w[name] = () => { requests.push(name); throw new Error('NETWORK_BLOCKED'); };
  }
  w.navigator.sendBeacon = () => { throw new Error('BEACON_BLOCKED'); };
  w.HTMLFormElement.prototype.submit = () => { throw new Error('SUBMIT_BLOCKED'); };
  w.HTMLFormElement.prototype.requestSubmit = () => { throw new Error('SUBMIT_BLOCKED'); };
  t.after(() => { w.close(); assert.deepEqual(requests, []); });
  return w;
}

function runDisclaimerOnly(w) {
  const source = w.document.getElementById('harmat-migrated-public-cleanup-js').textContent;
  const fn = source.match(/  function addPropertyDisclaimer\(\) \{[\s\S]*?\n  \}/)[0];
  const call = source.match(/if \((?:true|false) \/\* harmat-property-disclaimer-legacy \*\/\) addPropertyDisclaimer\(\);/)[0];
  w.eval(fn + '\n' + call);
}

test('legacy whole source has exactly the permitted one-call guard diff', () => {
  const baseline = execFileSync('git', ['show', 'd1d4833:wp-mu-plugins/harmat-migrated-snippets.php'],
    { cwd: ROOT, encoding: 'utf8' }).replace(/\r\n/g, '\n');
  assert.equal(baseline.split(oldCall).length - 1, 1);
  assert.equal(legacy, baseline.replace(oldCall, newCall));
});

test('all five unchanged language assets still match controller pins', () => {
  const controller = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/zz-harmat-internal-language.php'), 'utf8');
  const pins = [...controller.matchAll(/'([^']+\.(?:js|css|json))' => '([a-f0-9]{64})'/g)];
  assert.equal(pins.length, 5);
  for (const [, filename, expected] of pins) {
    const bytes = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-internal-language', filename), 'utf8').replace(/\r\n/g, '\n');
    assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), expected, filename);
  }
});

test('active output has one normal note and never runs legacy insertion, including retries', t => {
  const w = domFixture(t, rendered);
  const d = w.document;
  const note = d.querySelector('.harmat-property-disclaimer');
  assert(note && d.body.contains(note));
  assert.equal(note.nextElementSibling.id, 'colophon');
  assert.equal(d.querySelectorAll('form').length, 1);
  assert.equal(d.documentElement.querySelector(':scope > .harmat-property-disclaimer'), null);
  const form = d.querySelector('form');
  const before = form.outerHTML;
  for (let i = 0; i < 20; i++) runDisclaimerOnly(w);
  assert.equal(d.querySelectorAll('.harmat-property-disclaimer').length, 1);
  assert.equal(d.querySelector('.harmat-property-disclaimer'), note);
  assert.equal(form.outerHTML, before);
  assert.equal(d.querySelector('form'), form);
});

test('missing module restores original insertion exactly once, preserving legacy fallback', t => {
  const w = domFixture(t, fallback);
  const d = w.document;
  assert.equal(d.querySelector('.harmat-property-disclaimer'), null);
  for (let i = 0; i < 20; i++) runDisclaimerOnly(w);
  assert.equal(d.querySelectorAll('.harmat-property-disclaimer').length, 1);
  assert.equal(d.querySelector('.harmat-property-disclaimer').parentElement, d.documentElement);
  assert.equal(d.querySelectorAll('form').length, 1);
});

test('in-body note uses existing reviewed Chinese dictionary and restores exact HU node/bytes', t => {
  const w = domFixture(t, rendered);
  const d = w.document;
  const note = d.querySelector('.harmat-property-disclaimer');
  const strong = note.firstChild;
  const text = strong.firstChild;
  const prose = strong.nextSibling;
  const original = note.outerHTML;
  const form = d.querySelector('form');
  const originalForm = form.outerHTML;
  const fields = [...form.elements];
  const values = fields.map(field => [field.value, field.checked]);
  let events = 0;
  for (const type of ['input', 'change', 'submit']) form.addEventListener(type, () => events++);
  w.eval(runtime);
  const tr = w.HarmatInternalLanguage.create(d, dictionary, { enabled: true, storage: false });
  t.after(() => tr.destroy());
  for (let i = 0; i < 10; i++) {
    tr.setLanguage('zh-Hans');
    assert.notEqual(strong.textContent, 'Fontos t\u00e1j\u00e9koztat\u00e1s:');
    assert.match(strong.textContent, /[\u3400-\u9fff]/);
    assert.match(prose.textContent, /[\u3400-\u9fff]/);
    assert.equal(note.firstChild, strong);
    assert.equal(strong.firstChild, text);
    assert.equal(d.querySelector('form'), form);
    assert.deepEqual([...form.elements], fields);
    assert.deepEqual(fields.map(field => [field.value, field.checked]), values);
    runDisclaimerOnly(w);
    assert.equal(d.querySelectorAll('.harmat-property-disclaimer').length, 1);
    tr.setLanguage('hu');
    assert.equal(note.outerHTML, original);
    assert.equal(form.outerHTML, originalForm);
  }
  assert.equal(events, 0);
  tr.setLanguage('zh-Hans');
  tr.destroy();
  assert.equal(note.outerHTML, original);
});

test('existing desktop and narrow-screen disclaimer styles remain unchanged', () => {
  assert(legacy.includes('.harmat-property-disclaimer{max-width:1180px;width:calc(100% - 48px);'));
  assert(legacy.includes('@media(max-width:640px){.harmat-property-disclaimer{width:auto!important;margin:22px 16px 0;font-size:12px}}'));
  const module = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/zz-harmat-property-disclaimer.php'), 'utf8');
  assert(!/<style|wp_enqueue_style|wp_enqueue_script|addEventListener|update_(?:post|option)/.test(module));
});
