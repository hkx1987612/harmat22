const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('../../outputs/2026-10-17-chinese-switch/node_modules/jsdom');
const root = path.resolve(__dirname, '../..');
const script = fs.readFileSync(path.join(root, 'wp-plugins/harmat22-map-redesign/assets/map-redesign.js'), 'utf8');
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }
function run(html, url = 'https://harmat22.hu/harmat-lakopark-kornyeke/') {
  const dom = new JSDOM(html, { url, runScripts: 'outside-only' });
  const w = dom.window;
  w.IntersectionObserver = class { observe() {} disconnect() {} };
  w.HTMLMediaElement.prototype.pause = function () {};
  const section = w.document.querySelector('.harmat-neighborhood-page');
  const original = section && section.outerHTML;
  const form = w.document.querySelector('form');
  const formHTML = form && form.outerHTML;
  const input = form && form.querySelector('input');
  if (input) input.value = 'LOCAL-FIXTURE-ONLY';
  w.eval(script);
  w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
  return { dom, w, section, original, form, formHTML, input };
}
const facilities = '<main><section class="harmat-neighborhood-page"><h1>Környék</h1>' +
  ['Zöld környezet', 'Oktatás', 'Bevásárlás', 'Közlekedés', 'Egészség'].map(t =>
    `<article class="harmat-neighborhood-distance-card"><h2>${t}</h2><p>A környék előnyei</p></article>`).join('') +
  '<iframe src="https://www.google.com/maps/embed"></iframe></section></main>' +
  '<form id="h22-offer-form"><input name="name"><button>Send</button></form>';
for (const html of [facilities, facilities.replace('Környék</h1>', '周边环境</h1>')]) {
  const t = run(html), d = t.w.document;
  check(t.section.isConnected && d.querySelector('.harmat-neighborhood-page') === t.section, 'Original node identity retained');
  check(t.section.outerHTML === t.original, 'Every original content byte retained');
  check(d.querySelectorAll('.harmat-neighborhood-distance-card').length === 5, 'All five facility categories retained');
  check(d.querySelectorAll('#harmat-3d-tour').length === 1, 'Exactly one interactive viewer');
  check(t.section.nextElementSibling.querySelector('#harmat-3d-tour'), 'Viewer follows the detailed section');
  check(t.form === d.querySelector('form') && t.form.outerHTML === t.formHTML, 'Native form node and markup untouched');
  check(t.input.value === 'LOCAL-FIXTURE-ONLY', 'Input value retained');
  d.querySelector('[data-target="location"]').click();
  check(d.querySelector('[data-panel="location"]').classList.contains('active'), 'Location tab remains interactive');
  d.querySelector('[data-target="panorama"]').click();
  check(d.querySelector('[data-panel="panorama"]').classList.contains('active'), 'Panorama tab remains interactive');
  t.w.eval(script); d.dispatchEvent(new t.w.Event('DOMContentLoaded'));
  check(d.querySelectorAll('#harmat-3d-tour').length === 1 && t.section.outerHTML === t.original, 'Repeated initialization is idempotent');
  t.dom.window.close();
}
let t = run('<main><section id="old"><h2>A környék előnyei</h2><p>Park és bevásárlás</p></section></main>');
check(!t.w.document.querySelector('#old') && t.w.document.querySelector('#harmat-3d-tour'), 'Legacy map replacement unchanged');
t.dom.window.close();
t = run('<body class="home">' + facilities + '</body>', 'https://harmat22.hu/');
check(!t.w.document.querySelector('#harmat-3d-tour') && t.section.isConnected, 'Homepage explicitly excluded');
t.dom.window.close();
t = run('<main><p>Contact page without a map</p></main>');
check(!t.w.document.querySelector('#harmat-3d-tour'), 'Unmatched pages untouched');
t.dom.window.close();
if (process.argv[2]) {
  t = run(fs.readFileSync(process.argv[2], 'utf8'));
  check(t.section && t.section.isConnected && t.section.outerHTML === t.original, 'Real server-rendered detailed section retained');
  check(t.w.document.querySelectorAll('.harmat-neighborhood-distance-card').length === 6, 'Real six-card facility list retained');
  check(t.w.document.querySelectorAll('#harmat-3d-tour').length === 1, 'Real page retains exactly one appended viewer');
  t.dom.window.close();
}
console.log(`PASS ${checks} neighborhood preservation assertions`);
