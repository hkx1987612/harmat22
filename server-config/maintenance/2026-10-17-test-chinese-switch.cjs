'use strict';
// DOM unit tests only: jsdom does not render pixels or prove browser integration.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '../..');
const { JSDOM } = require(path.join(root, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const source = path.join(root, 'server-config/previews/chinese-switch');
const read = name => fs.readFileSync(path.join(source, name), 'utf8');
const dictionary = JSON.parse(read('zh-Hans.json'));
const engine = read('switch.js');
const page = fs.readFileSync(path.join(root, 'outputs/2026-10-17-chinese-switch/harmat-chinese-switch-v1.html'), 'utf8');
const tick = () => new Promise(resolve => setTimeout(resolve, 0));

function fixture(html = page) {
  const dom = new JSDOM(html, { runScripts: 'outside-only', url: 'file:///isolated-harmat-language-preview.html' });
  dom.window.fetch = () => { throw new Error('Unexpected network'); };
  dom.window.XMLHttpRequest = function () { throw new Error('Unexpected network'); };
  dom.window.eval(engine);
  const translator = dom.window.HarmatLanguagePreview.create(dom.window.document, dictionary);
  return { dom, win: dom.window, d: dom.window.document, translator };
}
function cleanup(f) { f.translator.destroy(); f.win.close(); }
function values(f) {
  return JSON.stringify([...new f.win.FormData(f.d.querySelector('form')).entries()]);
}

test('explicit preview root is mandatory; ordinary pages cannot initialize', () => {
  const dom = new JSDOM('<p>Építési napló</p>', { runScripts: 'outside-only' });
  dom.window.eval(engine);
  assert.throws(() => dom.window.HarmatLanguagePreview.create(dom.window.document, dictionary), /isolated preview/);
  assert.equal(dom.window.document.querySelector('p').textContent, 'Építési napló');
  dom.window.close();
});
test('default HU, click Chinese, click Magyar and exact document roundtrip', () => {
  const f = fixture();
  const before = f.d.documentElement.outerHTML;
  assert.equal(f.d.documentElement.lang, 'hu');
  f.d.querySelector('[data-hlp-language="zh-Hans"]').click();
  assert.equal(f.d.documentElement.lang, 'zh-Hans');
  assert.equal(f.d.querySelector('.hpf-hero-description').textContent, 'Óhegy 的新建住宅。');
  assert.equal(f.d.querySelector('[data-hlp-language="zh-Hans"]').getAttribute('aria-pressed'), 'true');
  f.d.querySelector('[data-hlp-language="hu"]').click();
  assert.equal(f.d.documentElement.outerHTML, before);
  cleanup(f);
});
test('all reviewed source strings translate while actual branding/contact/numerals/links stay intact', () => {
  const f = fixture();
  const links = [...f.d.querySelectorAll('a')].map(a => a.getAttribute('href'));
  const before = [...f.d.querySelectorAll('.hpf-fact-number')].map(n => n.textContent);
  f.translator.setLanguage('zh-Hans');
  assert.deepEqual([...f.d.querySelectorAll('.hpf-fact-number')].map(n => n.textContent), before);
  assert.deepEqual(before, ['398','124','8388','2028']);
  assert.equal(f.d.querySelector('h1').textContent, 'Harmat Lakópark');
  assert.equal(f.d.querySelector('.hpf-phone').textContent, '+36 30 073 3375');
  assert.equal(f.d.querySelector('.hpf-email').textContent, 'ertekesites@harmat22.hu');
  assert.deepEqual([...f.d.querySelectorAll('a')].map(a => a.getAttribute('href')), links);
  const walker = f.d.createTreeWalker(f.d.documentElement, f.win.NodeFilter.SHOW_TEXT);
  const residual = []; let n;
  while ((n = walker.nextNode())) {
    if (!n.parentElement.closest('script,style,[translate="no"]') && dictionary[n.data.trim()] && dictionary[n.data.trim()] !== n.data.trim()) residual.push(n.data);
  }
  assert.deepEqual(residual, []);
  cleanup(f);
});
test('alt and accessible labels translate, unrelated attributes stay byte-identical', () => {
  const f = fixture();
  const before = [...f.d.querySelectorAll('*')].map(e => [...e.attributes].filter(a => !['alt','title','aria-label','lang','aria-pressed'].includes(a.name)).map(a => [a.name,a.value]));
  f.translator.setLanguage('zh-Hans');
  assert.equal(f.d.querySelector('.hpf-desktop-nav').getAttribute('aria-label'), '主导航');
  assert.match(f.d.querySelector('.hpf-hero-image').alt, /鸟瞰效果图/);
  assert.deepEqual([...f.d.querySelectorAll('*')].map(e => [...e.attributes].filter(a => !['alt','title','aria-label','lang','aria-pressed'].includes(a.name)).map(a => [a.name,a.value])), before);
  cleanup(f);
});
test('filled data, selections, required privacy, handlers and field node identities survive 20 toggles', () => {
  const f = fixture(); const form = f.d.querySelector('form');
  for (const [name, value] of Object.entries({ 'selected-building':'A3', 'selected-floor':'4', 'selected-apartment':'A3-4-L5', 'your-name':'测试 用户 / Google keresés', 'your-email':'test@example.invalid', 'your-phone':'+36000000000', 'your-date':'2026-10-20', 'your-time':'12:00-15:00', 'your-message':'Építési napló 中文用户原文' })) form.elements.namedItem(name).value = value;
  form.querySelector('[value="Közösségi média"]').checked = true;
  form.elements.namedItem('selected-price').value = '123456789';
  const before = values(f); const inputs = [...form.elements]; let changes = 0;
  form.addEventListener('change', () => changes++);
  for (let i=0;i<20;i++) f.translator.setLanguage(i%2 ? 'hu' : 'zh-Hans');
  assert.equal(values(f), before);
  assert.deepEqual([...form.elements], inputs);
  assert.equal(changes, 0);
  assert.equal(form.elements.namedItem('selected-price').value, '123456789');
  assert.equal(form.elements.namedItem('selected-area').value, '47.83');
  const privacy = form.elements.namedItem('privacy-acceptance');
  assert.equal(privacy.required, true); assert.equal(privacy.checked, false);
  privacy.checked = true;
  f.translator.setLanguage('zh-Hans');
  assert.equal(privacy.checked, true);
  form.elements.namedItem('your-name').dispatchEvent(new f.win.Event('change', {bubbles:true}));
  assert.equal(changes, 1);
  cleanup(f);
});
test('all five lead-source payloads match real native source and remain unchanged in Chinese', () => {
  const f = fixture();
  const native = fs.readFileSync(path.join(root, 'wp-mu-plugins/harmat-unified-offer-modal.php'), 'utf8');
  const start = native.indexOf('<fieldset class="h22-offer-source">');
  assert.ok(start > 0);
  const dom = new JSDOM(native.slice(start, native.indexOf('</fieldset>', start) + 11));
  const expected = [...dom.window.document.querySelectorAll('[name="lead_source"]')].map(n => n.value);
  assert.equal(expected.length, 5);
  f.translator.setLanguage('zh-Hans');
  const radios = [...f.d.querySelectorAll('[name="lead_source"]')];
  assert.deepEqual(radios.map(n => n.value), expected);
  assert.equal(radios[1].nextElementSibling.textContent, 'Google 搜索');
  assert.equal(radios[3].nextElementSibling.textContent, '社交媒体');
  for (const radio of radios) {
    radio.checked = true;
    assert.equal(new f.win.FormData(f.d.querySelector('form')).get('lead_source'), radio.value);
  }
  dom.window.close(); cleanup(f);
});
test('new dynamic text and original-language updates are translated then correctly restored', async () => {
  const f = fixture(); f.translator.setLanguage('zh-Hans');
  const p = f.d.createElement('p'); p.textContent = 'Építési napló'; f.d.querySelector('main').append(p);
  await tick(); assert.equal(p.textContent, '施工日志');
  p.firstChild.data = 'Lakáskereső'; await tick(); assert.equal(p.textContent, '公寓查询');
  f.translator.setLanguage('hu'); assert.equal(p.textContent, 'Lakáskereső');
  cleanup(f);
});
test('new image descriptions and detached nodes do not produce stale rollback', async () => {
  const f = fixture(); f.translator.setLanguage('zh-Hans');
  const image = f.d.querySelector('.hpf-hero-image'); image.setAttribute('alt', 'Építési napló');
  await tick(); assert.equal(image.alt, '施工日志');
  const p = f.d.createElement('p'); p.textContent='Ajánlatkérés'; f.d.body.append(p);
  await tick(); p.remove(); p.firstChild.data='Kapcsolat'; f.d.body.append(p);
  await tick(); assert.equal(p.textContent, '联系我们');
  f.translator.setLanguage('hu'); assert.equal(p.textContent, 'Kapcsolat'); assert.equal(image.alt, 'Építési napló');
  cleanup(f);
});
test('unknown strings and malicious-looking input remain literal; translations never create HTML', () => {
  const f = fixture(); const p = f.d.createElement('p'); p.textContent='Unreviewed original'; f.d.body.append(p);
  const input = f.d.querySelector('[name="your-name"]'); input.value='<script>never translate customer data</script>';
  f.translator.setLanguage('zh-Hans'); assert.equal(p.textContent,'Unreviewed original'); assert.equal(input.value,'<script>never translate customer data</script>');
  cleanup(f);
  const x = fixture('<body data-harmat-language-preview="local-v1"><p>Ajánlatkérés</p></body>');
  x.translator.destroy();
  const t=x.win.HarmatLanguagePreview.create(x.d, {'Ajánlatkérés':'<img src=x onerror=alert(1)>'});
  t.setLanguage('zh-Hans'); assert.equal(x.d.querySelector('p').textContent,'<img src=x onerror=alert(1)>'); assert.equal(x.d.querySelector('img'),null);
  t.destroy(); x.win.close();
});
test('unsafe option implicit values, editable content, native modals and protected fields are untouched', () => {
  const f = fixture('<body data-harmat-language-preview="local-v1"><select><option>Építési napló</option><option value="stable">Építési napló</option></select><div contenteditable="true">Ajánlatkérés</div><p translate="no">Ajánlatkérés</p><div id="h22-offer-modal">Ajánlatkérés</div><code>Ajánlatkérés</code><textarea>Ajánlatkérés</textarea><div data-language-protect>Ajánlatkérés</div></body>');
  const old = f.d.querySelector('select').value; f.translator.setLanguage('zh-Hans');
  assert.equal(f.d.querySelector('select').value,old);
  assert.equal(f.d.querySelectorAll('option')[0].textContent,'Építési napló');
  assert.equal(f.d.querySelectorAll('option')[1].textContent,'施工日志');
  for (const q of ['[contenteditable]','[translate]','#h22-offer-modal','code','textarea','[data-language-protect]']) assert.equal(f.d.querySelector(q).textContent,'Ajánlatkérés');
  cleanup(f);
});
test('destroy restores HU, disconnects observer and removes click handler', async () => {
  const f=fixture(); f.translator.setLanguage('zh-Hans'); f.translator.destroy();
  assert.equal(f.d.documentElement.lang,'hu');
  f.d.querySelector('[data-hlp-language="zh-Hans"]').click(); await tick();
  assert.equal(f.d.documentElement.lang,'hu');
  assert.throws(()=>f.translator.setLanguage('zh-Hans'),/Disposed/);
  f.win.close();
});
test('unsupported locale fails without mutation', () => {
  const f=fixture(); const before=f.d.documentElement.outerHTML;
  assert.throws(()=>f.translator.setLanguage('en'),/Unsupported/);
  assert.equal(f.d.documentElement.outerHTML,before); cleanup(f);
});
test('single bundled script is hash-allowlisted; network/forms denied, assets embedded', () => {
  const dom=new JSDOM(page); const d=dom.window.document;
  const scripts=d.querySelectorAll('script'); assert.equal(scripts.length,1);
  const hash=crypto.createHash('sha256').update(scripts[0].textContent).digest('base64');
  const csp=d.querySelector('[http-equiv="Content-Security-Policy"]').content;
  assert.ok(csp.includes("script-src 'sha256-"+hash+"'"));
  assert.ok(csp.includes("connect-src 'none'")); assert.ok(csp.includes("form-action 'none'"));
  for(const img of d.querySelectorAll('img')) assert.match(img.src,/^data:image\/(jpeg|webp|png);base64,/);
  assert.equal(d.querySelectorAll('[src]:not(img),link,iframe,video,audio,base,[onclick]').length,0);
  assert.equal(d.querySelector('button[type="submit"]').disabled,true);
  assert.equal(d.querySelector('[name="_harmat_offer_nonce"]'),null);
  assert.equal(d.querySelector('form').hasAttribute('action'),false);
  assert.equal(d.querySelector('[name="selected-price"]').value,'');
  dom.window.close();
});
test('actual bundled bootstrap works, all submit events prevented locally', () => {
  const dom=new JSDOM(page,{runScripts:'outside-only'}); const w=dom.window; const d=w.document;
  w.eval(d.querySelector('script').textContent);
  assert.equal(w.harmatLocalLanguage.getLanguage(),'hu');
  d.querySelector('[data-hlp-language="zh-Hans"]').click(); assert.equal(d.documentElement.lang,'zh-Hans');
  const event=new w.Event('submit',{cancelable:true,bubbles:true});
  assert.equal(d.querySelector('form').dispatchEvent(event),false); assert.equal(event.defaultPrevented,true);
  w.harmatLocalLanguage.destroy(); w.close();
});
