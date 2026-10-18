'use strict';
// Real original PHP markup and original JS, isolated DOM + synthetic fetch only.
// This suite does not certify browser paint, keyboard viewport layout or real delivery.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const os = require('node:os');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const REPO = path.dirname(ROOT);
const { JSDOM } = require(path.join(REPO, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HAP_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const fixture = path.join(__dirname, 'widget-fixture.php');
const dir = path.dirname(__dirname);
const js = fs.readFileSync(path.join(dir, 'assistant-presentation.js'), 'utf8');
const css = fs.readFileSync(path.join(dir, 'assistant-presentation.css'), 'utf8');
const render = (locale = 'hu_HU') => execFileSync(PHP, [fixture, '--render'], { encoding: 'utf8', env: { ...process.env, HAP_TEST_LOCALE: locale } });
const html = render();
const tick = () => new Promise(resolve => setImmediate(resolve));
const sha = bytes => crypto.createHash('sha256').update(bytes).digest('hex');

function setup(t, { markup = html, locale = 'hu', native = true, addon = true } = {}) {
  const dom = new JSDOM(`<html lang="${locale}" data-harmat-language="${locale}"><body><header id="masthead">Native header</header><main><form id="native-appointment"><input name="customer" value="Untouched"><textarea>Unsent draft</textarea><input name="privacy" type="checkbox" required></form></main>${markup}<div id="h22-offer-modal" hidden aria-hidden="true"><form id="h22-offer-form"><input value="Original quote"></form></div><div id="hm-cookie-consent" style="display:none" role="dialog"></div><footer id="colophon">Native footer</footer></body></html>`, { url: 'https://harmat22.hu/?utm_source=fixture', runScripts: 'outside-only', pretendToBeVisual: true });
  const w = dom.window, d = w.document;
  const panel = d.getElementById('harmat-local-ai-panel'), launch = d.querySelector('.harmat-local-ai-launch');
  const body = panel?.querySelector('[data-harmat-local-ai-body]'), form = panel?.querySelector('form'), input = form?.querySelector('input');
  const outside = Array.from(d.querySelectorAll('#masthead,main,#colophon,#h22-offer-form'));
  const snapshot = outside.map(n => n.outerHTML), requests = [], dangerous = [], events = [], observers = [];
  let reply = { ok: true, answer: 'Synthetic answer', selection: { rooms: 2 }, suggestions: ['Continue'] }, pending = null;
  const fetch = async (url, options) => {
    requests.push({ url, ...options, payload: JSON.parse(options.body) });
    assert.match(url, /^https:\/\/harmat22\.hu\/wp-json\/harmat-local-assistant\/v1\/(ask|event|handoff)$/);
    if (url.endsWith('/ask') && pending) return pending;
    return { ok: true, json: async () => url.endsWith('/ask') ? reply : { ok: true, message: 'Synthetic only', crm: 'fixture' } };
  };
  w.fetch = fetch;
  for (const name of ['XMLHttpRequest', 'WebSocket', 'EventSource']) w[name] = function () { dangerous.push(name); throw new Error('Forbidden network'); };
  w.navigator.sendBeacon = () => { dangerous.push('beacon'); throw new Error('Forbidden beacon'); };
  w.Storage.prototype.setItem = w.Storage.prototype.removeItem = function () { dangerous.push('storage'); throw new Error('Forbidden persistence'); };
  const Observer = w.MutationObserver;
  w.MutationObserver = class extends Observer { observe(node, options) { observers.push({ node, options }); return super.observe(node, options); } };
  const nativeForm = d.getElementById('native-appointment');
  ['submit', 'input', 'change'].forEach(name => nativeForm.addEventListener(name, () => events.push(name)));
  w.HTMLFormElement.prototype.submit = w.HTMLFormElement.prototype.requestSubmit = function () { dangerous.push('native submit'); };
  const nativeScript = d.getElementById('harmat-local-assistant-script');
  if (native && nativeScript) w.eval(nativeScript.textContent);
  const initialFetch = w.fetch;
  function boot() { w.eval(js); assert.equal(w.fetch, initialFetch, 'no API overwrite'); }
  if (addon) boot();
  t.after(() => {
    assert.deepEqual(outside.map(n => n.outerHTML), snapshot, 'native forms/header/footer unchanged');
    assert.deepEqual(dangerous, []); assert.deepEqual(events, []); w.close();
  });
  return { w, d, panel, launch, body, form, input, requests, observers, boot,
    open() { launch.click(); }, close() { panel.querySelector('.harmat-local-ai-close').click(); },
    async language(code) { panel.querySelector(`[data-harmat-ai-lang="${code}"]`).click(); await tick(); },
    async pageLanguage(code) { d.documentElement.setAttribute('data-harmat-language', code); await tick(); },
    reply(value) { reply = value; },
    defer() { let resolve; pending = new Promise(r => { resolve = r; }); return value => { resolve({ ok: true, json: async () => value }); pending = null; }; },
    async ask(message) { input.value = message; form.dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true })); await tick(); },
    visible(id, value) { const node = d.getElementById(id); node.getClientRects = () => value ? [{ width: 200, height: 200 }] : []; node.hidden = !value; node.setAttribute('aria-hidden', String(!value)); node.style.display = value ? 'block' : 'none'; }
  };
}

test('PHP public guards, verified inline assets and late hook', () => {
  for (const args of [[], ['--rest'], ['--cli']]) assert.match(execFileSync(PHP, [fixture, ...args], { encoding: 'utf8' }), /PASS \d+ PHP assertions/);
});

test('PHP missing or corrupt owned asset emits no addon at all, preserving original fallback', () => {
  const prefix = path.join(os.tmpdir(), 'harmat-assistant-presentation-test-');
  const temp = fs.mkdtempSync(prefix);
  try {
    const controller = path.join(temp, 'zz-harmat-assistant-presentation.php');
    const assets = path.join(temp, 'harmat-assistant-presentation'); fs.mkdirSync(assets);
    fs.copyFileSync(path.join(ROOT, 'zz-harmat-assistant-presentation.php'), controller);
    const files = ['assistant-presentation.css', 'assistant-presentation.js', 'LICENSE-lucide.txt'];
    for (const file of files) fs.copyFileSync(path.join(dir, file), path.join(assets, file));
    for (const file of files) {
      const target = path.join(assets, file); const bytes = fs.readFileSync(target);
      for (const corrupt of [false, true]) {
        if (corrupt) fs.writeFileSync(target, Buffer.concat([bytes, Buffer.from('corrupt')])); else fs.unlinkSync(target);
        const result = execFileSync(PHP, [fixture, '--render'], { encoding: 'utf8', env: { ...process.env, HAP_TEST_CONTROLLER: controller } });
        assert.ok(result.includes('id="harmat-local-assistant-script"'), 'native original remains');
        assert.ok(!result.includes('id="harmat-assistant-presentation-script"')); assert.ok(!result.includes('id="harmat-assistant-presentation-style"'));
        fs.writeFileSync(target, bytes);
      }
    }
  } finally {
    assert.ok(path.resolve(temp).startsWith(path.resolve(prefix)), 'cleanup stays in named temporary test directory');
    fs.rmSync(temp, { recursive: true, force: true });
  }
});

test('activation makes no requests, opens nothing, preserves native nodes and ten suggestions', async t => {
  const f = setup(t, { addon: false });
  const nodes = [f.panel, f.launch, f.body, f.form, f.input];
  f.input.value = 'Unsent question'; f.input.setSelectionRange(2, 6);
  f.boot();
  assert.equal(f.panel.dataset.hapReady, '1'); assert.equal(f.launch.dataset.hapReady, '1');
  assert.equal(f.panel.classList.contains('is-open'), false); assert.deepEqual(f.requests, []);
  assert.equal(f.panel.getAttribute('role'), null, 'region is not a modal');
  assert.equal(f.input.value, 'Unsent question'); assert.equal(f.input.selectionStart, 2); assert.equal(f.input.selectionEnd, 6);
  f.open(); await tick();
  assert.equal(f.panel.querySelectorAll('[data-harmat-local-ai-suggestions] button').length, 10);
  assert.equal(f.body.children.length, 1); assert.ok(f.body.textContent.includes('Automatizált'));
  assert.deepEqual([f.d.getElementById('harmat-local-ai-panel'), f.d.querySelector('.harmat-local-ai-launch'), f.panel.querySelector('[data-harmat-local-ai-body]'), f.panel.querySelector('form'), f.form.querySelector('input')], nodes);
  assert.equal(f.requests.length, 1); assert.ok(f.requests[0].url.endsWith('/event')); assert.equal(f.requests[0].payload.event, 'assistant_open');
  const footnote = f.panel.querySelector('.harmat-local-ai-footnote');
  assert.ok(footnote.textContent.includes('Nem hivatalos ajánlat, jogi vagy pénzügyi tanácsadás'));
  const initial = f.panel.outerHTML; f.boot(); assert.equal(f.panel.outerHTML, initial, 'idempotent addon');
});

test('addon can enhance an already-used native widget without clearing nodes or handoff draft', async t => {
  const f = setup(t, { addon: false });
  f.reply({ ok: true, answer: 'Existing native answer', handoff: { title: 'Original', lang: 'hu' }, selection: { rooms: 2 }, suggestions: ['Existing suggestion'] });
  f.open(); await f.ask('Existing question');
  const nodes = Array.from(f.body.childNodes), handoff = f.body.querySelector('form');
  handoff.querySelector('[name="name"]').value = 'Existing synthetic draft'; f.input.value = 'Keep my question';
  const suggestions = Array.from(f.panel.querySelector('[data-harmat-local-ai-suggestions]').childNodes);
  const calls = f.requests.length;
  f.boot();
  assert.deepEqual(Array.from(f.body.childNodes), nodes); assert.equal(handoff.querySelector('[name="name"]').value, 'Existing synthetic draft');
  assert.equal(f.input.value, 'Keep my question'); assert.equal(f.requests.length, calls);
  assert.deepEqual(Array.from(f.panel.querySelector('[data-harmat-local-ai-suggestions]').childNodes), suggestions);
  await f.ask('Continue'); assert.deepEqual(f.requests.filter(r => r.url.endsWith('/ask')).at(-1).payload.selection, { rooms: 2 });
});

test('independent chat languages update only chrome; page switch preserves drafts, selection and history', async t => {
  const f = setup(t); f.open(); await f.language('zh');
  const intro = f.body.firstChild, history = f.body.innerHTML;
  f.input.value = 'Private unsent draft'; f.input.setSelectionRange(3, 9);
  const suggestion = f.panel.querySelector('[data-harmat-local-ai-suggestions] button');
  await f.pageLanguage('zh-Hans');
  assert.equal(f.launch.textContent.trim(), '咨询');
  assert.equal(f.panel.querySelector('strong').textContent, 'Harmat 助手');
  assert.match(f.panel.querySelector('.harmat-local-ai-footnote').textContent, /\u4e0d\u6784\u6210\u6b63\u5f0f\u62a5\u4ef7/);
  assert.equal(f.panel.lang, 'zh'); assert.equal(f.input.value, 'Private unsent draft'); assert.equal(f.input.selectionStart, 3);
  assert.equal(f.body.firstChild, intro); assert.equal(f.body.innerHTML, history);
  assert.equal(f.panel.querySelector('[data-harmat-local-ai-suggestions] button'), suggestion, 'page switch never invokes native language reset');
  await f.language('en'); assert.equal(f.panel.querySelector('strong').textContent, 'Harmat assistant');
  assert.equal(f.panel.querySelector('.harmat-local-ai-send').title, 'Send question');
  assert.match(f.panel.querySelector('.harmat-local-ai-footnote').textContent, /Not an official offer or legal or financial advice/);
  assert.equal(f.panel.querySelector('[data-harmat-ai-lang="en"]').getAttribute('aria-pressed'), 'true');
  assert.equal(f.launch.textContent.trim(), '咨询');
  await f.pageLanguage('hu'); assert.equal(f.launch.textContent.trim(), 'Kérdezzen');
  assert.equal(f.panel.lang, 'en'); assert.equal(f.input.value, 'Private unsent draft');
  assert.equal(f.body.innerHTML, history); assert.equal(f.panel.querySelector('.harmat-local-ai-lang').getAttribute('role'), 'group');
});

test('original request body/selection/history and pending-language guard remain original', async t => {
  const f = setup(t); f.open(); await f.language('en');
  const resolve = f.defer();
  await f.ask('Two rooms');
  const request = f.requests.find(r => r.url.endsWith('/ask'));
  assert.deepEqual(request.payload, { message: 'Two rooms', lang: 'en', selection: {} });
  assert.equal(request.credentials, 'same-origin'); assert.equal(request.method, 'POST');
  await f.language('zh'); assert.equal(f.panel.lang, 'en', 'native asking guard controls UI');
  await f.pageLanguage('zh-Hans'); assert.equal(f.panel.lang, 'en');
  resolve({ ok: true, answer: 'Synthetic units', selection: { rooms: 2, building: 'A1' }, suggestions: ['More options'] }); await tick();
  const historyNodes = Array.from(f.body.children);
  await f.ask('With balcony');
  const second = f.requests.filter(r => r.url.endsWith('/ask'))[1];
  assert.deepEqual(second.payload.selection, { rooms: 2, building: 'A1' }); assert.equal(second.payload.lang, 'en');
  historyNodes.forEach((n, i) => assert.equal(f.body.children[i], n));
  f.close(); f.open(); await tick(); historyNodes.forEach((n, i) => assert.equal(f.body.children[i], n));
});

test('every original default suggestion dispatches through original submit and endpoint', async t => {
  const f = setup(t); f.open(); await tick();
  const texts = Array.from(f.panel.querySelectorAll('[data-harmat-local-ai-suggestions] button')).map(b => b.textContent);
  for (const value of texts) {
    f.reply({ ok: true, answer: 'Synthetic only', suggestions: texts, selection: {} });
    const button = Array.from(f.panel.querySelectorAll('[data-harmat-local-ai-suggestions] button')).find(b => b.textContent === value);
    button.click(); await tick();
    assert.equal(f.requests.filter(r => r.url.endsWith('/ask')).at(-1).payload.message, value);
  }
  assert.equal(f.requests.filter(r => r.url.endsWith('/ask')).length, 10);
});

test('original native offer-link handler retains the exact DOM link and selection', async t => {
  const f = setup(t); let received = null;
  f.w.harmatUnifiedOfferOpen = link => { received = link; f.visible('h22-offer-modal', true); };
  f.reply({ ok: true, answer: 'Synthetic apartment', cards: [{ title: 'A1-1-L1', meta: 'Fixture', url: '/property/a1-1-l1/', offer_url: '/property/a1-1-l1/#opal-contactform-popup' }], selection: { rooms: 2 } });
  f.open(); await f.ask('A1-1-L1');
  const link = f.body.querySelector('[data-harmat-ai-offer]'); link.click(); await tick();
  assert.equal(received, link); assert.equal(link.getAttribute('href'), '/property/a1-1-l1/#opal-contactform-popup');
  assert.equal(f.panel.classList.contains('is-open'), false); assert.equal(f.panel.dataset.hapSuspended, '1');
  assert.equal(f.requests.filter(r => r.url.endsWith('/handoff')).length, 0);
});

test('synthetic handoff payload still contains native conversation, values and endpoint', async t => {
  const f = setup(t);
  f.reply({ ok: true, answer: 'Synthetic handoff context', selection: { rooms: 2 }, handoff: { title: 'Fixture handoff', intent: 'appointment', lang: 'en', privacy: 'Fixture consent' } });
  f.open(); await f.ask('Synthetic viewing request');
  const handoff = f.body.querySelector('[data-harmat-local-ai-handoff]');
  handoff.querySelector('[name="name"]').value = 'Synthetic Fixture'; handoff.querySelector('[name="email"]').value = 'fixture@example.invalid';
  handoff.querySelector('[name="message"]').value = 'Synthetic no real lead'; handoff.querySelector('[name="interested_unit"]').value = 'A1-1-L1';
  handoff.querySelector('[name="privacy"]').checked = true;
  const snapshot = handoff.innerHTML;
  await f.pageLanguage('zh-Hans'); assert.equal(handoff.innerHTML, snapshot); assert.equal(handoff.querySelector('[name="name"]').value, 'Synthetic Fixture');
  handoff.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true })); await tick();
  const call = f.requests.find(r => r.url.endsWith('/handoff'));
  assert.equal(call.payload.name, 'Synthetic Fixture'); assert.equal(call.payload.email, 'fixture@example.invalid');
  assert.equal(call.payload.intent, 'appointment'); assert.equal(call.payload.lang, 'en'); assert.equal(call.payload.interested_unit, 'A1-1-L1');
  assert.ok(call.payload.conversation_summary.includes('Synthetic viewing request')); assert.ok(call.payload.conversation_summary.includes('Synthetic handoff context'));
  assert.equal(handoff.classList.contains('is-sent'), true);
});

test('known native modals suspend only owned surfaces and restore state without clearing', async t => {
  const f = setup(t); f.open(); await tick(); f.input.value = 'Draft'; const intro = f.body.firstChild;
  for (const id of ['h22-offer-modal', 'hm-cookie-consent']) {
    f.visible(id, true); await tick(); assert.equal(f.panel.dataset.hapSuspended, '1'); assert.equal(f.launch.dataset.hapSuspended, '1');
    assert.equal(f.panel.classList.contains('is-open'), true); assert.equal(f.input.value, 'Draft');
    f.visible(id, false); await tick(); assert.equal(f.panel.dataset.hapSuspended, '0'); assert.equal(f.body.firstChild, intro);
  }
  for (const [id, bodyClass] of [['elementor-popup-modal-3527', 'dialog-lightbox-body'], ['harmat-clean-menu-modal', 'harmat-clean-menu-open']]) {
    const menu = f.d.createElement('div'); menu.id = id; f.d.body.appendChild(menu);
    f.visible(menu.id, true); f.d.body.classList.add(bodyClass); await tick();
    assert.equal(f.panel.dataset.hapSuspended, '1'); f.visible(menu.id, false); await tick(); assert.equal(f.panel.dataset.hapSuspended, '0');
  }
  assert.ok(f.observers.every(o => !o.options.childList && (!o.options.subtree || o.node === f.panel.querySelector('.harmat-local-ai-lang'))), 'no document-wide subtree observer');
});

test('Escape closes only the focused owned open chat; native dialog is never closed', async t => {
  const f = setup(t); f.open(); await tick(); f.input.focus();
  f.input.dispatchEvent(new f.w.KeyboardEvent('keydown', { key: 'Escape', bubbles: true })); await tick();
  assert.equal(f.panel.classList.contains('is-open'), false); assert.equal(f.d.activeElement, f.launch);
  f.open(); f.visible('h22-offer-modal', true); await tick();
  f.input.dispatchEvent(new f.w.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  assert.equal(f.d.getElementById('h22-offer-modal').hidden, false); assert.equal(f.panel.classList.contains('is-open'), true);
});

test('contract drift and missing dependencies fail open with original DOM untouched', async t => {
  const variations = [
    value => value.replace('id="harmat-assistant-presentation-style"', 'id="missing-style"'),
    value => value.replace('--hap-asset-ready: 1;', '--hap-asset-ready: 0;'),
    value => value.replace('class="harmat-local-ai-footnote"', 'class="unexpected-footnote"'),
    value => value.replace('data-harmat-ai-lang="en"', 'data-harmat-ai-lang="xx"'),
    value => value.replace('<section class="harmat-local-ai-panel"', '<section data-new-owner="1" class="changed-panel"'),
    value => value.replace('<div class="harmat-local-ai-head">', '<div>New child</div><div class="harmat-local-ai-head">')
  ];
  for (const alter of variations) {
    const f = setup(t, { markup: alter(html), addon: false });
    const before = f.panel.outerHTML, launch = f.launch.outerHTML;
    f.boot(); assert.equal(f.panel.outerHTML, before); assert.equal(f.launch.outerHTML, launch); assert.deepEqual(f.requests, []);
  }
  const f = setup(t, { native: false, addon: false }); const before = f.panel.outerHTML; f.boot(); assert.equal(f.panel.outerHTML, before);
});

test('an addon runtime failure removes its chrome without touching live conversation or draft', async t => {
  const f = setup(t); f.open(); await f.ask('Keep this conversation');
  const nodes = Array.from(f.body.childNodes); f.input.value = 'Keep this draft';
  const original = f.d.documentElement.getAttribute;
  f.d.documentElement.getAttribute = function () { throw new Error('synthetic presentation failure'); };
  f.d.documentElement.setAttribute('data-harmat-language', 'en'); await tick();
  f.d.documentElement.getAttribute = original;
  assert.equal(f.panel.hasAttribute('data-hap-ready'), false); assert.equal(f.panel.classList.contains('is-open'), true);
  assert.equal(f.input.value, 'Keep this draft'); assert.deepEqual(Array.from(f.body.childNodes), nodes);
  f.close(); assert.equal(f.panel.classList.contains('is-open'), false, 'native original listeners remain');
});

test('native language bridge and addon coexist without forced chat language or infinite mutations', async t => {
  const f = setup(t); f.open(); await f.language('en'); f.input.value = 'Draft';
  f.w.eval(fs.readFileSync(path.join(ROOT, 'harmat-internal-language/runtime.js'), 'utf8'));
  const bridge = f.w.HarmatInternalLanguage.create(f.d, { 'Kérdezzen': '请咨询' }, { enabled: true, storage: false });
  bridge.setLanguage('zh-Hans'); await tick(); await tick();
  assert.equal(f.launch.textContent.trim(), '咨询'); assert.equal(f.panel.lang, 'en'); assert.equal(f.input.value, 'Draft');
  bridge.setLanguage('hu'); await tick(); assert.equal(f.launch.textContent.trim(), 'Kérdezzen'); assert.equal(f.panel.lang, 'en');
});

test('native page language may already be active before addon without taking over chat language', async t => {
  const f = setup(t, { addon: false });
  f.w.eval(fs.readFileSync(path.join(ROOT, 'harmat-internal-language/runtime.js'), 'utf8'));
  const bridge = f.w.HarmatInternalLanguage.create(f.d, { 'Kérdezzen': '请咨询' }, { enabled: true, storage: false });
  bridge.setLanguage('zh-Hans'); await tick();
  f.boot(); await tick(); assert.equal(f.launch.textContent.trim(), '咨询');
  f.open(); await tick(); assert.equal(f.panel.lang, 'hu'); assert.equal(f.launch.textContent.trim(), '咨询');
  bridge.setLanguage('hu'); await tick(); assert.equal(f.launch.textContent.trim(), 'Kérdezzen');
});

test('initial chrome follows the original locale without clicking language controls or resetting suggestions', async t => {
  for (const locale of ['zh_CN', 'en_GB']) {
    const f = setup(t, { markup: render(locale), locale: locale.slice(0, 2) });
    assert.equal(f.panel.lang, locale.slice(0, 2)); assert.deepEqual(f.requests, []);
    assert.equal(f.panel.querySelector('[data-harmat-local-ai-suggestions]').children.length, 0);
    f.open(); await tick(); assert.equal(f.panel.querySelector('[data-harmat-local-ai-suggestions]').children.length, 10);
    assert.equal(f.panel.querySelector('[aria-pressed="true"]').dataset.harmatAiLang, locale.slice(0, 2));
  }
});

test('shipped icons exactly match pinned Lucide source, with no icon runtime or new IO', () => {
  const bytes = fs.readFileSync(path.join(REPO, 'outputs/2026-10-18-home-family/assets/lucide.min.js'));
  assert.equal(sha(bytes), '3411692820cb8d47543f69496aa25fd603a358f4498046f41c508a5a3342210e');
  const context = {}; vm.createContext(context); vm.runInContext(bytes.toString(), context);
  for (const name of ['MessageCircle', 'X', 'Send']) for (const node of context.lucide.icons[name][2]) assert.ok(js.includes(JSON.stringify(node[1].d).slice(1, -1)), name + ' exact path');
  assert.doesNotMatch(js, /\bfetch\s*\(|XMLHttpRequest|sendBeacon|localStorage|sessionStorage|createIcons|innerHTML|harmatUnifiedOfferOpen\s*=/);
  assert.doesNotMatch(css, /@import|url\(|font-face|gradient|letter-spacing:\s*-/);
});

test('every visible CSS selector is gated to the owned original surfaces', () => {
  const dom = new JSDOM(`<style>${css}</style>`);
  function inspect(rules) {
    for (const rule of rules) {
      if (rule.cssRules) inspect(rule.cssRules);
      if (!rule.selectorText) continue;
      if (rule.selectorText === '#harmat-assistant-presentation-style') continue;
      for (const selector of rule.selectorText.split(',')) assert.match(selector.trim(), /^(?:#harmat-local-ai-panel|\.harmat-local-ai-launch)\[data-hap-ready="1"\]/);
    }
  }
  inspect(dom.window.document.styleSheets[0].cssRules); dom.window.close();
});
