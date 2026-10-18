'use strict';
// No browser / renderer / network: DOM and API stubs only, not pixel/WebGL evidence.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { buildFiles, selectors, ROOT, DEST } = require('./2026-10-18-build-home-family-presentation.cjs');
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HHF_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const fixturePhp = path.join(__dirname, '2026-10-18-test-home-family-presentation.php');
const dir = path.join(ROOT, DEST);
const read = name => fs.readFileSync(path.join(dir, name), 'utf8');
const js = read('home-family.js');
const html = execFileSync(PHP, [fixturePhp, '--render'], { encoding: 'utf8' });
const tick = () => new Promise(resolve => setImmediate(resolve));
const sha = value => crypto.createHash('sha256').update(value).digest('hex');

function fixture(t, { marker = 'hu', reduced = true, observers = true, markup = html } = {}) {
  const shell = `<html lang="hu" data-harmat-language="${marker}"><head><title>Native SEO</title><meta name="description" content="Native description"></head><body><header id="masthead"><details class="site-menu"><summary>Menu</summary><i data-lucide="menu"></i></details><button id="native-language">Native switch</button></header>${markup}<footer id="colophon">Native footer</footer><div id="h22-offer-modal"><form id="h22-offer-form"><input name="name" value="Unsent draft"><textarea name="message">Customer draft</textarea><select name="source"><option value="google" selected>Google</option></select><input type="checkbox" name="privacy" required><button type="submit">Send</button></form></div><i id="outside-icon" data-lucide="phone"></i><div class="hhf-panorama-console" id="outside-console"></div></body></html>`;
  const dom = new JSDOM(shell, { url: 'https://harmat22.hu/', runScripts: 'outside-only', pretendToBeVisual: true });
  const w = dom.window, d = w.document, root = d.getElementById('harmat-home-family');
  const native = Array.from(d.querySelectorAll('header,footer,#h22-offer-modal,#outside-icon,#outside-console'));
  const snapshot = native.map(n => n.outerHTML), requests = [], nativeEvents = [];
  const languageAttributes = d.documentElement.outerHTML.slice(0, d.documentElement.outerHTML.indexOf('>') + 1);
  const observerList = [], viewers = [], media = [], bounds = { hero: true, panorama: false };
  let hidden = false, full = null;
  root.querySelector('.hhf-hero').getBoundingClientRect = () => ({ width: 900, height: 570, left: 0, right: 900, top: bounds.hero ? 80 : -650, bottom: bounds.hero ? 650 : -80 });
  root.querySelector('[data-panorama-console]').getBoundingClientRect = () => ({ width: 900, height: 520, top: bounds.panorama ? 80 : 2000, bottom: bounds.panorama ? 600 : 2520 });
  for (const key of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource']) w[key] = () => { requests.push(key); throw new Error('NETWORK_FORBIDDEN'); };
  w.navigator.sendBeacon = () => { requests.push('beacon'); throw new Error('NETWORK_FORBIDDEN'); };
  w.HTMLFormElement.prototype.submit = w.HTMLFormElement.prototype.requestSubmit = () => { nativeEvents.push('submit'); throw new Error('NO_SUBMIT'); };
  const nativeForm = d.querySelector('form');
  for (const name of ['submit', 'input', 'change', 'click']) nativeForm.addEventListener(name, () => nativeEvents.push(name));
  w.matchMedia = () => ({ matches: reduced, addEventListener() {} });
  const paused = new WeakMap();
  Object.defineProperty(w.HTMLMediaElement.prototype, 'paused', { configurable: true, get() { return paused.get(this) !== false; } });
  w.HTMLMediaElement.prototype.play = function () { media.push(['play', this]); paused.set(this, false); this.dispatchEvent(new w.Event('play')); if (!this.paused) this.dispatchEvent(new w.Event('playing')); return Promise.resolve(); };
  w.HTMLMediaElement.prototype.pause = function () { media.push(['pause', this]); paused.set(this, true); this.dispatchEvent(new w.Event('pause')); };
  if (observers) w.IntersectionObserver = class { constructor(callback, options) { this.callback = callback; this.options = options; observerList.push(this); } observe(target) { this.target = target; } disconnect() {} };
  w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
  w.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new w.Event('close')); };
  Object.defineProperty(d, 'hidden', { get: () => hidden });
  Object.defineProperty(d, 'fullscreenElement', { get: () => full });
  w.HTMLElement.prototype.requestFullscreen = function () { full = this; d.dispatchEvent(new w.Event('fullscreenchange')); return Promise.resolve(); };
  d.exitFullscreen = () => { full = null; d.dispatchEvent(new w.Event('fullscreenchange')); return Promise.resolve(); };
  w.lucide = { createIcons() { throw new Error('GLOBAL_LUCIDE_FORBIDDEN'); } };
  w.pannellum = { viewer(host, config) {
    assert.ok(root.contains(host));
    const handlers = {}, calls = [];
    const drag = d.createElement('div'); drag.className = 'pnlm-dragfix'; host.append(drag);
    const api = { on(name, callback) { handlers[name] = callback; return api; }, getConfig: () => config,
      resize: () => calls.push('resize'), lookAt: (...args) => calls.push(['lookAt', ...args]),
      startAutoRotate: () => calls.push('start'), stopAutoRotate: () => calls.push('stop'), stopMovement: () => calls.push('stopMovement'), destroy: () => calls.push('destroy') };
    viewers.push({ host, config, handlers, calls }); return api;
  } };
  const storageSet = w.Storage.prototype.setItem;
  w.Storage.prototype.setItem = function () { requests.push('storage-write'); throw new Error('NO_PERSISTENCE'); };
  t.after(() => {
    assert.deepEqual(native.map(n => n.outerHTML), snapshot, 'native shell/control/form DOM unchanged');
    assert.equal(d.title, 'Native SEO');
    assert.equal(d.querySelector('meta[name="description"]').content, 'Native description');
    assert.equal(d.documentElement.lang, 'hu');
    assert.deepEqual(requests, [], 'no network/persistence operations');
    assert.deepEqual(nativeEvents, [], 'no native form events');
    w.Storage.prototype.setItem = storageSet;
    w.close();
  });
  return { w, d, root, viewers, media, native, snapshot, languageAttributes,
    boot: () => w.eval(js),
    setNear(value) { bounds.panorama = value; observerList.filter(o => o.target?.hasAttribute('data-panorama-console')).forEach(o => o.callback([{ isIntersecting: value, intersectionRatio: value ? 1 : 0 }])); },
    setHero(value) { bounds.hero = value; observerList.filter(o => o.target?.classList.contains('hhf-hero')).forEach(o => o.callback([{ isIntersecting: value, intersectionRatio: value ? 1 : 0 }])); },
    setHidden(value) { hidden = value; d.dispatchEvent(new w.Event('visibilitychange')); },
    async language(value) { d.documentElement.setAttribute('data-harmat-language', value); await tick(); assert.equal(d.documentElement.getAttribute('data-harmat-language'), value); } };
}

test('build is deterministic; exact LF manifest and pinned original Pannellum bytes', () => {
  const result = buildFiles(), built = result.files;
  assert.deepEqual(JSON.parse(fs.readFileSync(path.join(__dirname, '2026-10-18-home-family-presentation-assets.json'), 'utf8')), result.assets);
  assert.deepEqual(Object.keys(built).sort(), fs.readdirSync(dir).sort());
  for (const [name, content] of Object.entries(built)) { assert.equal(read(name), content, name); assert.ok(!read(name).includes('\r')); }
  const pins = JSON.parse(read('manifest.json'));
  assert.deepEqual(Object.keys(pins).sort(), Object.keys(built).filter(n => n !== 'manifest.json').sort());
  for (const [name, pin] of Object.entries(pins)) assert.equal(sha(fs.readFileSync(path.join(dir, name))), pin, name);
  assert.equal(pins['pannellum.js'], 'a28b2f7b339fd0a602c6769df1dca6ad43af73bc8c6a5be67209715289c12a9a');
  assert.match(read('COPYING'), /Matthew Petroff/); assert.match(read('COPYING'), /Lucide Contributors/);
});

test('all own and vendor CSS selectors are owner-scoped; animation names cannot collide', () => {
  const dom = new JSDOM('<style></style>');
  try {
    for (const filename of ['home-family.css', 'pannellum.css']) {
      dom.window.document.querySelector('style').textContent = read(filename);
      let count = 0;
      function walk(rules) { for (const rule of rules) {
        if (rule.selectorText) for (const selector of selectors(rule.selectorText)) { count++; assert.match(selector, /^#harmat-home-family(?:$|\s|\[|:)/, selector); assert.doesNotMatch(selector, /(?:\bhtml\b|\bbody\b|:root|\.harmat-home-test)/); }
        else if (rule.type === 7) assert.match(rule.name, /^hhf-/);
        else if (rule.cssRules) walk(rule.cssRules);
      } }
      walk(dom.window.document.styleSheets[0].cssRules); assert.ok(count > 60);
    }
  } finally { dom.window.close(); }
});

test('main-only structure, owned ID references, build-time icons, original media URLs and same-tab official links', () => {
  const dom = new JSDOM(html), d = dom.window.document;
  try {
    assert.equal(d.querySelectorAll('main').length, 1); assert.equal(d.querySelector('main').outerHTML.startsWith('<main id="main" class="site-main">'), true);
    const root = d.getElementById('harmat-home-family');
    assert.ok(root.hasAttribute('data-language-protect')); assert.equal(root.querySelectorAll('h1').length, 1); assert.equal(root.querySelector('h1').id, 'hero-title');
    assert.equal(root.querySelectorAll('header,footer,nav,.site-menu,.language-switch,[data-language],script,style,meta,link,form,input,textarea,iframe,[data-lucide]').length, 0);
    assert.ok(root.querySelectorAll('svg').length > 25);
    const ids = Array.from(d.querySelectorAll('[id]'), n => n.id); assert.equal(ids.length, new Set(ids).size);
    for (const n of root.querySelectorAll('[aria-controls],[aria-labelledby],[aria-describedby],a[href^="#"]')) {
      for (const attr of ['aria-controls', 'aria-labelledby', 'aria-describedby']) if (n.hasAttribute(attr)) for (const id of n.getAttribute(attr).split(' ')) assert.ok(d.getElementById(id), id);
      if (n.getAttribute('href')?.startsWith('#')) assert.ok(d.getElementById(n.getAttribute('href').slice(1)));
    }
    for (const n of root.querySelectorAll('a')) { assert.equal(n.hasAttribute('target'), false); assert.match(n.getAttribute('href'), /^(?:#|https:\/\/harmat22\.hu\/|tel:|mailto:)/); }
    for (const n of root.querySelectorAll('*')) for (const attr of n.attributes) if (/^(?:src|poster|data-src|data-cube-)/.test(attr.name)) assert.match(attr.value, /^https:\/\/harmat22\.hu\//);
    for (const video of root.querySelectorAll('video')) { assert.equal(video.preload, 'none'); assert.equal(video.hasAttribute('src'), false); }
    assert.doesNotMatch(html, /data:|__HHF_|49 256 550|62 236 650|125 297 550/);
  } finally { dom.window.close(); }
});

test('PHP input validation, escaping, hidden-price and fresh-value matrix', () => {
  assert.match(execFileSync(PHP, [fixturePhp], { encoding: 'utf8' }), /^PASS \d+ presentation PHP assertions/);
});

test('all owned translation keys exist; approved Chinese mappings are copied unchanged', () => {
  const dictionary = Function(js.match(/const zh = (\{[\s\S]*?\n  \});/)[1].replace(/;$/, '').replace(/^/, 'return '))();
  const approvedJs = fs.readFileSync(path.join(ROOT, 'server-config/previews/home-family/home-family.js'), 'utf8');
  const approved = Function(approvedJs.match(/const zh = (\{[\s\S]*?\n  \});/)[1].replace(/;$/, '').replace(/^/, 'return '))();
  for (const [key, value] of Object.entries(approved)) assert.equal(dictionary[key], value, key);
  const dom = new JSDOM(html);
  try { for (const n of dom.window.document.querySelectorAll('*')) for (const a of n.attributes) if (a.name === 'data-i18n' || a.name.startsWith('data-i18n-')) assert.equal(typeof dictionary[a.value], 'string', a.value); }
  finally { dom.window.close(); }
});

test('native HU -> zh-Hans -> HU transitions preserve native controls, edited form state, IDs and facts', async t => {
  const f = fixture(t); f.boot();
  const original = f.root.querySelector('[data-i18n="apartmentsTitle"]').textContent;
  const links = Array.from(f.root.querySelectorAll('a'), n => n.href);
  const numeric = Array.from(f.root.querySelectorAll('.hhf-price strong,.hhf-apartment-facts dd:not([data-i18n])'), n => n.outerHTML);
  const nativeInput = f.d.querySelector('input[name="name"]'); nativeInput.value = 'Customer edited draft';
  for (let i = 0; i < 20; i++) {
    await f.language('zh-Hans'); assert.equal(f.root.lang, 'zh-Hans'); assert.equal(f.root.querySelector('[data-i18n="apartmentsTitle"]').textContent, '从一个新家，开启共同的生活计划。');
    await f.language('hu'); assert.equal(f.root.querySelector('[data-i18n="apartmentsTitle"]').textContent, original);
  }
  assert.equal(nativeInput.value, 'Customer edited draft');
  assert.deepEqual(Array.from(f.root.querySelectorAll('a'), n => n.href), links);
  assert.deepEqual(Array.from(f.root.querySelectorAll('.hhf-price strong,.hhf-apartment-facts dd:not([data-i18n])'), n => n.outerHTML), numeric);
  assert.equal(f.d.documentElement.outerHTML.slice(0, f.d.documentElement.outerHTML.indexOf('>') + 1), f.languageAttributes);
});

test('already-Chinese page initializes locally; unrelated HTML mutations do not switch owned copy', async t => {
  const f = fixture(t, { marker: 'zh-Hans' }); f.boot();
  assert.equal(f.root.lang, 'zh-Hans');
  f.d.documentElement.setAttribute('data-unrelated', '1'); await tick(); assert.equal(f.root.lang, 'zh-Hans');
  await f.language('hu'); assert.equal(f.root.lang, 'hu');
});

test('real unchanged native translator excludes only owned subtree and drives local language through its marker', async t => {
  const f = fixture(t); f.boot();
  f.w.eval(fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-internal-language/runtime.js'), 'utf8'));
  const api = f.w.HarmatInternalLanguage.create(f.d, {
    'Harmat Lakópark': 'NATIVE_MUST_NOT_TOUCH',
    'Kicsi kezdetek. Nagy közös tervek.': 'NATIVE_MUST_NOT_TOUCH',
    'Native footer': '原生页脚'
  }, { enabled: true, storage: false });
  try {
    api.setLanguage('zh-Hans'); await tick();
    assert.equal(f.root.querySelector('h1').textContent, 'Harmat Lakópark');
    assert.equal(f.root.querySelector('[data-i18n="apartmentsTitle"]').textContent, '从一个新家，开启共同的生活计划。');
    assert.equal(f.d.getElementById('colophon').textContent, '原生页脚');
    api.refresh(f.root); await tick(); assert.doesNotMatch(f.root.textContent, /NATIVE_MUST_NOT_TOUCH/);
    api.setLanguage('hu'); await tick();
    assert.equal(f.root.querySelector('[data-i18n="apartmentsTitle"]').textContent, 'Kicsi kezdetek. Nagy közös tervek.');
  } finally { api.destroy(); await tick(); }
});

test('hidden price stays absent through Chinese and Hungarian transitions', async t => {
  const rows = { 'A1-1-L6': { code: 'A1-1-L6', url: 'https://harmat22.hu/property/a1-1-l6/', rooms: 1, sales_area: 73.29,
    status: 'current', price_huf: null, floorplan_url: 'https://harmat22.hu/wp-content/uploads/test.jpg' } };
  const markup = execFileSync(PHP, [fixturePhp, '--render'], { input: JSON.stringify(rows), encoding: 'utf8' });
  const f = fixture(t, { markup }); f.boot();
  assert.equal(f.root.querySelector('.hhf-price strong').textContent, 'Ár egyeztetés alapján');
  await f.language('zh-Hans'); assert.equal(f.root.querySelector('.hhf-price strong').textContent, '价格面议');
  await f.language('hu'); assert.equal(f.root.querySelector('.hhf-price strong').textContent, 'Ár egyeztetés alapján');
  assert.doesNotMatch(f.root.querySelector('.hhf-price').textContent, /\d/);
});

test('approved section order and main copy outside live apartment cards are retained', () => {
  const before = new JSDOM(fs.readFileSync(path.join(ROOT, 'server-config/previews/home-family/index.html'), 'utf8'));
  const after = new JSDOM(html);
  try {
    for (const n of before.window.document.querySelectorAll('[data-i18n="officialNewTab"]')) n.remove();
    before.window.document.querySelector('[data-i18n="homes"]').textContent = 'otthon';
    const sections = dom => Array.from(dom.window.document.querySelectorAll('main > section,main > #harmat-home-family > section'));
    const a = sections(before), b = sections(after); assert.equal(a.length, b.length);
    a.forEach((n, i) => {
      assert.equal(b[i].id, n.id ? 'hhf-' + n.id : '');
      if (n.classList.contains('apartments')) return;
      assert.equal(b[i].textContent.replace(/\s+/g, ' ').trim(), n.textContent.replace(/\s+/g, ' ').trim());
    });
  } finally { before.window.close(); after.window.close(); }
});

test('live theme uppercase and legacy standalone-unit rewrite cannot distort owned summary', () => {
  assert.match(read('home-family.css'), /#harmat-home-family h1, #harmat-home-family h2, #harmat-home-family h3 \{ text-transform: none; \}/);
  const dom = new JSDOM(html);
  try {
    const unit = dom.window.document.querySelector('[data-i18n="homes"]');
    assert.equal(unit.textContent, 'otthon');
    for (const el of dom.window.document.querySelectorAll('h6,p,span')) {
      if (el.textContent.trim().toLowerCase() === 'lakás') el.textContent = 'lakás az első ütemben';
    }
    assert.equal(unit.textContent, 'otthon');
  } finally { dom.window.close(); }
});

test('no root is a no-op and duplicate initialization installs no second viewer', t => {
  const f = fixture(t); f.boot(); f.boot(); f.setNear(true); assert.equal(f.viewers.length, 1);
  f.root.remove(); assert.doesNotThrow(() => f.boot());
});

test('Pannellum instantiates lazily in owned root; native language updates same viewer strings', async t => {
  const f = fixture(t); f.boot(); assert.equal(f.viewers.length, 0); f.setNear(true);
  assert.equal(f.viewers.length, 1); const viewer = f.viewers[0];
  assert.equal(viewer.config.type, 'cubemap'); assert.equal(viewer.config.pitch, -27); assert.equal(viewer.config.hfov, 92);
  assert.equal(viewer.config.cubeMap.length, 6); viewer.handlers.load();
  await f.language('zh-Hans'); assert.equal(viewer.config.strings.loadingLabel, '全景加载中…');
  await f.language('hu'); assert.equal(viewer.config.strings.loadingLabel, 'Panoráma betöltése…'); assert.equal(f.viewers.length, 1);
});

test('owned tabs/keyboard/reset/rotation and fullscreen do not operate native widgets', async t => {
  const f = fixture(t, { reduced: false }); f.boot(); f.setNear(true); const v = f.viewers[0]; v.handlers.load();
  const tab = name => f.root.querySelector(`[data-panorama-tab="${name}"]`);
  tab('gallery').click(); assert.equal(tab('gallery').getAttribute('aria-selected'), 'true');
  tab('gallery').dispatchEvent(new f.w.KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
  assert.equal(tab('location').getAttribute('aria-selected'), 'true');
  f.root.querySelector('[data-panorama-reset]').click(); assert.ok(v.calls.some(c => Array.isArray(c) && c[0] === 'lookAt'));
  v.host.dispatchEvent(new f.w.MouseEvent('mousedown', { bubbles: true })); assert.equal(f.root.querySelector('[data-panorama-rotate]').getAttribute('aria-pressed'), 'false');
  f.root.querySelector('[data-panorama-rotate]').click(); assert.equal(f.root.querySelector('[data-panorama-rotate]').getAttribute('aria-pressed'), 'true');
  f.root.querySelector('[data-panorama-fullscreen]').click(); await tick(); assert.equal(f.d.fullscreenElement, f.root.querySelector('[data-panorama-console]'));
  f.root.querySelector('[data-panorama-fullscreen]').click(); await tick(); assert.equal(f.d.fullscreenElement, null);
});

test('gallery dialog localizes without recreating viewer and returns focus', async t => {
  const f = fixture(t); f.boot(); f.root.querySelector('[data-panorama-tab="gallery"]').click();
  const trigger = f.root.querySelector('[data-gallery]'); trigger.click(); const dialog = f.root.querySelector('dialog');
  assert.equal(dialog.open, true); await f.language('zh-Hans'); assert.equal(dialog.querySelector('img').alt, trigger.querySelector('img').alt);
  f.root.querySelector('[data-panorama-close]').click(); assert.equal(dialog.open, false); assert.equal(f.d.activeElement, trigger);
});

test('panel videos attach original URLs only on owned tab action; one plays and hidden panels pause', t => {
  const f = fixture(t); f.boot(); f.setNear(true); const clips = Array.from(f.root.querySelectorAll('.hhf-panorama-video-card video'));
  assert.ok(clips.every(v => !v.hasAttribute('src'))); f.root.querySelector('[data-panorama-tab="video"]').click(); assert.ok(clips.every(v => v.src === v.dataset.src));
  clips[0].play(); assert.equal(clips[0].playbackRate, 0.25); clips[1].play(); assert.equal(clips[0].paused, true); assert.equal(clips[1].playbackRate, 1);
  f.root.querySelector('[data-panorama-tab="plans"]').click(); assert.ok(clips.every(v => v.paused));
});

test('reduced motion blocks autoplay; user play pauses offscreen/background and never writes live state', t => {
  const f = fixture(t); f.boot(); f.setHero(true); const hero = f.root.querySelector('.hhf-hero-video');
  assert.equal(hero.hasAttribute('src'), false); f.root.querySelector('.hhf-video-button').click(); assert.equal(hero.paused, false);
  f.setHero(false); assert.equal(hero.paused, true); f.setHero(true); assert.equal(hero.paused, true);
  f.root.querySelector('.hhf-video-button').click(); f.setHidden(true); assert.equal(hero.paused, true);
});

test('missing vendor gives owned poster/error fallback without touching native page', t => {
  const f = fixture(t); delete f.w.pannellum; f.boot(); f.setNear(true);
  assert.equal(f.root.querySelector('.hhf-panorama-viewer').hidden, true); assert.equal(f.root.querySelector('.hhf-panorama-fallback').hidden, false);
  assert.match(f.root.querySelector('[data-panorama-status]').textContent, /nem érhető el/);
});

test('without intersection observers, visible-only fallback still initializes and owned error is safe', t => {
  const f = fixture(t, { observers: false }); f.boot(); assert.equal(f.viewers.length, 0); f.setNear(true); f.w.dispatchEvent(new f.w.Event('scroll'));
  assert.equal(f.viewers.length, 1); f.viewers[0].handlers.error(); assert.equal(f.root.querySelector('.hhf-panorama-viewer').hidden, true);
});

test('source has no broad selectors, SEO/control/storage writes, forms, API calls, or global icon pass', () => {
  assert.doesNotMatch(js, /document\.(?:querySelector|querySelectorAll|title|body)|document\.documentElement\.(?:lang\s*=|setAttribute)|createIcons|languageSwitch|menu\.addEventListener|localStorage|sessionStorage|fetch\(|XMLHttpRequest|sendBeacon|\.submit\(|requestSubmit/);
  assert.equal((js.match(/document\.getElementById/g) || []).length, 1);
  assert.match(js, /attributeFilter: \['data-harmat-language'\]/);
});
