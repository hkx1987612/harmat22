'use strict';
// Private full-theme candidate HTML, actual native scripts, strictly no network.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ROOT = path.resolve(__dirname, '../..');
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const dir = path.join(ROOT, 'wp-mu-plugins/harmat-internal-language');
const dictionary = JSON.parse(fs.readFileSync(path.join(dir, 'zh-Hans.json'), 'utf8'));
const tick = () => new Promise(resolve => setImmediate(resolve));

for (const slug of ['a1-f-l1', 'a1-1-l2', 'a3-4-l5']) {
  test('full native candidate footer/form identity and HU/Chinese/HU ' + slug, async t => {
    const requests = [];
    const html = fs.readFileSync(path.join(ROOT, 'outputs/2026-10-18-property-disclaimer/native-' + slug + '.html'), 'utf8');
    // Default JSDOM resource loading is disabled; do not enable CSS/iframe loads.
    const dom = new JSDOM(html, { url: 'https://harmat22.hu/property/' + slug + '/', runScripts: 'outside-only' });
    const w = dom.window, d = w.document;
    t.after(() => { w.close(); assert.deepEqual(requests, []); });
    for (const name of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource']) {
      w[name] = () => { requests.push(name); throw new Error('NETWORK_FORBIDDEN'); };
    }
    w.navigator.sendBeacon = () => { throw new Error('BEACON_FORBIDDEN'); };
    w.HTMLFormElement.prototype.submit = () => { throw new Error('SUBMIT_FORBIDDEN'); };
    w.HTMLFormElement.prototype.requestSubmit = () => { throw new Error('SUBMIT_FORBIDDEN'); };
    const note = d.querySelector('[data-harmat-property-disclaimer="1"]');
    assert(note && d.body.contains(note));
    assert.equal(note.nextElementSibling.id, 'colophon');
    assert.equal(d.querySelectorAll('.harmat-property-disclaimer').length, 1);
    const source = d.getElementById('harmat-migrated-public-cleanup-js').textContent;
    const fn = source.match(/  function addPropertyDisclaimer\(\) \{[\s\S]*?\n  \}/)[0];
    const call = source.match(/if \(false \/\* harmat-property-disclaimer-legacy \*\/\) addPropertyDisclaimer\(\);/)[0];
    const original = note.outerHTML;
    const form = d.getElementById('h22-offer-form');
    const fields = [...form.elements];
    const values = () => [...new w.FormData(form).entries()];
    const before = values();
    for (let i = 0; i < 20; i++) w.eval(fn + '\n' + call);
    w.eval(fs.readFileSync(path.join(dir, 'patterns.js'), 'utf8'));
    w.eval(fs.readFileSync(path.join(dir, 'runtime.js'), 'utf8'));
    const tr = w.HarmatInternalLanguage.create(d, dictionary, { enabled: true, storage: false, translateDynamic: w.HarmatLanguagePatterns.translate });
    tr.setLanguage('zh-Hans');
    await tick();
    assert.match(note.textContent, /重要提示/);
    assert(!note.textContent.includes('Fontos'));
    assert.equal(d.querySelector('.harmat-property-disclaimer'), note);
    assert.deepEqual([...form.elements], fields);
    assert.deepEqual(values(), before);
    assert.equal(d.getElementById('h22-offer-form'), form);
    tr.setLanguage('hu');
    assert.equal(note.outerHTML, original);
    assert.deepEqual(values(), before);
    assert.equal(d.querySelectorAll('.harmat-property-disclaimer').length, 1);
    assert.equal(d.documentElement.querySelector(':scope > .harmat-property-disclaimer'), null);
    tr.destroy();
  });
}
