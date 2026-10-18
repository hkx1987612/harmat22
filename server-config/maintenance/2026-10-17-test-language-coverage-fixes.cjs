'use strict';
// Local DOM only: no page scripts, remote resources, submission or mail.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ROOT = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const dir = path.join(ROOT, 'wp-mu-plugins/harmat-internal-language');
const dictionary = JSON.parse(fs.readFileSync(path.join(dir, 'zh-Hans.json'), 'utf8'));
const additions = JSON.parse(fs.readFileSync(path.join(dir, 'coverage-fixes.zh-Hans.json'), 'utf8'));
const runtime = fs.readFileSync(path.join(dir, 'runtime.js'), 'utf8');
const tick = () => new Promise(resolve => setTimeout(resolve, 0));
function setup() {
  const dom = new JSDOM('<!doctype html><html lang="hu"><body><main></main><form><input name="your-date" placeholder="Dátum"><input name="your-email" placeholder="Email-cím"><textarea name="your-message">A A1-1-L2 lakás iránt érdeklődöm.</textarea><input type="radio" name="lead_source" value="Google keresés" checked><input name="selected-apartment" value="A1-1-L2"></form><button class="harmat-local-ai-launch">Kérdezzen</button></body></html>', {url: 'https://harmat22.hu/', runScripts: 'outside-only'});
  const w = dom.window, d = w.document;
  w.fetch = () => {throw Error('NETWORK_FORBIDDEN');};
  w.XMLHttpRequest = function() {throw Error('NETWORK_FORBIDDEN');};
  w.eval(runtime);
  const tr = w.HarmatInternalLanguage.create(d, dictionary, {enabled: true, storage: false});
  return {w, d, tr, close() {tr.destroy();w.close();}};
}

test('all reviewed additions translate on first pass and roundtrip exactly', () => {
  const f = setup(), main = f.d.querySelector('main');
  const spans = Object.keys(additions).map(text => {const span=f.d.createElement('span');span.textContent=text;main.append(span);return span;});
  f.tr.setLanguage('zh-Hans');
  spans.forEach((span, i) => assert.equal(span.textContent, additions[Object.keys(additions)[i]]));
  f.tr.setLanguage('hu');
  spans.forEach((span, i) => assert.equal(span.textContent, Object.keys(additions)[i]));
  f.close();
});

test('late inserted home/legal text translates and restores without runtime changes', async () => {
  const f = setup();f.tr.setLanguage('zh-Hans');
  const main = f.d.querySelector('main');
  const rows = Object.entries(additions).filter(([key]) => !/ alaprajz$/.test(key));
  const nodes = rows.map(([key]) => {const e=f.d.createElement('p');e.textContent=key;main.append(e);return e;});
  await tick();await tick();
  rows.forEach((row, i) => assert.equal(nodes[i].textContent, row[1]));
  f.tr.setLanguage('hu');
  rows.forEach((row, i) => assert.equal(nodes[i].textContent, row[0]));
  f.close();
});

test('all floorplan alt descriptions preserve exact codes and source URLs', async () => {
  const f = setup();f.tr.setLanguage('zh-Hans');
  const rows = Object.entries(additions).filter(([key]) => /^A[1-4]-(F|[1-4])-L\d+ alaprajz$/.test(key));
  assert.equal(rows.length, 123);
  const images = rows.map(([key]) => {const img=f.d.createElement('img');img.alt=key;img.src='/unchanged-floorplan.png';f.d.querySelector('main').append(img);return img;});
  await tick();await tick();
  rows.forEach(([key, value], i) => {assert.equal(images[i].alt, value);assert.equal(value, key.replace(' alaprajz', ' 户型图'));assert.equal(images[i].getAttribute('src'), '/unchanged-floorplan.png');});
  f.tr.setLanguage('hu');rows.forEach(([key], i) => assert.equal(images[i].alt, key));f.close();
});

test('descriptions translate but field identity, values, native message and chat remain protected', () => {
  const f = setup(), form=f.d.querySelector('form'), nodes=[...form.elements];
  const before=JSON.stringify([...new f.w.FormData(form)]);
  for(let i=0;i<8;i++)f.tr.setLanguage(i%2 ? 'hu' : 'zh-Hans');
  f.tr.setLanguage('zh-Hans');
  assert.equal(nodes[0].placeholder, dictionary['Dátum']);
  assert.equal(nodes[1].placeholder, dictionary['Email-cím']);
  assert.equal(JSON.stringify([...new f.w.FormData(form)]), before);
  assert.deepEqual([...form.elements], nodes);
  assert.equal(f.d.querySelector('.harmat-local-ai-launch').textContent, 'Kérdezzen');
  for(const text of ['A3-4-L5','47,83 m²','63 214 200 Ft','93m2','UTEM-1'])assert.equal(f.tr.translateText(text), text);
  f.tr.setLanguage('hu');assert.equal(nodes[0].placeholder, 'Dátum');assert.equal(nodes[1].placeholder, 'Email-cím');f.close();
});
