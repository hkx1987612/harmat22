'use strict';
// Local source/media and no-network DOM checks; not real-browser layout evidence.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const OUT = path.join(ROOT, 'outputs/2026-10-18-home-family');
const SOURCE = path.join(ROOT, 'server-config/previews/home-family');
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const html = fs.readFileSync(path.join(SOURCE, 'index.html'), 'utf8');
const css = fs.readFileSync(path.join(SOURCE, 'home-family.css'), 'utf8');
const js = fs.readFileSync(path.join(SOURCE, 'home-family.js'), 'utf8');
const facts = JSON.parse(fs.readFileSync(path.join(OUT, 'facts.json'), 'utf8'));
const tick = () => new Promise(resolve => setImmediate(resolve));

function fixture(t, reduced = true) {
  const dom = new JSDOM(html, { url: 'file:///' + path.join(OUT, 'index.html').replace(/\\/g, '/'), runScripts: 'outside-only', pretendToBeVisual: true });
  const w = dom.window, requests = [], media = { plays: 0, pauses: 0 };
  for (const key of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource']) {
    w[key] = () => { requests.push(key); throw new Error('NETWORK_FORBIDDEN'); };
  }
  w.navigator.sendBeacon = () => { requests.push('beacon'); throw new Error('NETWORK_FORBIDDEN'); };
  w.HTMLFormElement.prototype.submit = () => { throw new Error('FORM_FORBIDDEN'); };
  w.HTMLFormElement.prototype.requestSubmit = () => { throw new Error('FORM_FORBIDDEN'); };
  w.matchMedia = () => ({ matches: reduced, addEventListener() {}, removeEventListener() {} });
  const paused = new WeakMap();
  Object.defineProperty(w.HTMLMediaElement.prototype, 'paused', { configurable: true, get() { return paused.get(this) !== false; } });
  w.HTMLMediaElement.prototype.play = function () { paused.set(this, false); if (this.classList.contains('hero-video')) media.plays++; this.dispatchEvent(new w.Event('play')); if (!this.paused) this.dispatchEvent(new w.Event('playing')); return Promise.resolve(); };
  w.HTMLMediaElement.prototype.pause = function () { paused.set(this, true); if (this.classList.contains('hero-video')) media.pauses++; this.dispatchEvent(new w.Event('pause')); };
  w.HTMLMediaElement.prototype.load = function () {};
  const observers = [], viewers = [];
  w.IntersectionObserver = class {
    constructor(callback) { this.callback = callback; observers.push(this); }
    observe(target) { this.target = target; }
    unobserve() {}
    disconnect() {}
  };
  w.pannellum = { viewer: (host, config) => {
    const handlers = {}, calls = [];
    const api = { on: (event, callback) => { handlers[event] = callback; return api; },
      resize: () => calls.push('resize'), lookAt: (...args) => calls.push(['lookAt', ...args]),
      startAutoRotate: (...args) => calls.push(['startAutoRotate', ...args]), stopAutoRotate: () => calls.push('stopAutoRotate'), stopMovement: () => calls.push('stopMovement'),
      getHfov: () => 92, setHfov: (...args) => calls.push(['setHfov', ...args]),
      getConfig: () => config, destroy: () => calls.push('destroy') };
    viewers.push({ host, config, handlers, calls, api });
    return api;
  } };
  w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
  w.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new w.Event('close')); };
  let pageHidden = false, fullscreen = null;
  Object.defineProperty(w.document, 'hidden', { configurable: true, get: () => pageHidden });
  Object.defineProperty(w.document, 'fullscreenElement', { configurable: true, get: () => fullscreen });
  w.document.fullscreenEnabled = true;
  w.HTMLElement.prototype.requestFullscreen = function () { fullscreen = this; w.document.dispatchEvent(new w.Event('fullscreenchange')); return Promise.resolve(); };
  w.document.exitFullscreen = () => { fullscreen = null; w.document.dispatchEvent(new w.Event('fullscreenchange')); return Promise.resolve(); };
  t.after(() => { w.close(); assert.deepEqual(requests, []); });
  return { w, d: w.document, media, observers, viewers,
    setNear: value => observers.forEach(o => o.callback([{ target: o.target, isIntersecting: value, intersectionRatio: value ? 1 : 0 }])),
    setHidden: value => { pageHidden = value; w.document.dispatchEvent(new w.Event('visibilitychange')); } };
}

function boot(f) {
  f.w.eval(fs.readFileSync(path.join(OUT, 'assets/lucide.min.js'), 'utf8'));
  f.w.eval(js);
  f.d.dispatchEvent(new f.w.Event('DOMContentLoaded'));
}

test('production WordPress/quote/appointment sources remain exactly the current stable checkout', () => {
  const changed = execFileSync('git', ['diff', '--name-only', '2e3f738', '--', 'wp-mu-plugins', 'wp-plugins'], { cwd: ROOT, encoding: 'utf8' });
  assert.equal(changed.trim(), '');
  const previous = execFileSync('git', ['show', 'b9809de:server-config/previews/home-family/index.html'], { cwd: ROOT, encoding: 'utf8' });
  const oldPage = new JSDOM(previous), newPage = new JSDOM(html);
  try {
    for (const selector of ['header', 'footer']) {
      assert.equal(newPage.window.document.querySelector(selector).outerHTML,
        oldPage.window.document.querySelector(selector).outerHTML, 'hero revision preserves ' + selector);
    }
    for (const page of [oldPage, newPage]) {
      page.window.document.querySelector('.hero').remove();
      page.window.document.querySelector('#panorama').remove();
    }
    assert.equal(newPage.window.document.querySelector('main').outerHTML,
      oldPage.window.document.querySelector('main').outerHTML, 'all nonhero/nonpanorama main content preserved');
  } finally {
    oldPage.window.close();
    newPage.window.close();
  }
});

test('one branded H1, genuine media, no recreated forms or remote embedded frames', t => {
  const { d } = fixture(t);
  assert.equal(d.querySelectorAll('h1').length, 1);
  assert.match(d.querySelector('h1').textContent, /Harmat Lakópark/);
  assert.equal(d.querySelectorAll('form,input,iframe,object,embed,[type="submit"]').length, 0);
  for (const img of d.querySelectorAll('img')) {
    assert(img.getAttribute('alt')?.trim(), 'descriptive image alternative');
    assert(img.getAttribute('width') && img.getAttribute('height'), 'stable image dimensions');
  }
  assert(d.body.textContent.includes('Látványterv'));
  assert(d.body.textContent.includes('2026'));
});

test('all display dependencies resolve locally; network and submission forbidden by CSP', t => {
  const { d } = fixture(t);
  const policy = d.querySelector('meta[http-equiv="Content-Security-Policy"]')?.content || '';
  for (const directive of ["connect-src 'none'", "form-action 'none'", "frame-src 'none'", "object-src 'none'"]) assert(policy.includes(directive), directive);
  for (const selector of ['img[src]', 'video[src]', 'video[data-src]', 'video[poster]', 'source[src]', 'script[src]', 'link[rel="stylesheet"]']) {
    for (const node of d.querySelectorAll(selector)) {
      const names = selector.startsWith('link') ? ['href'] : selector === 'video[poster]' ? ['poster'] : selector === 'video[data-src]' ? ['data-src'] : ['src'];
      for (const name of names) {
        const value = node.getAttribute(name);
        assert(value && !/^(?:https?:|\/\/|data:)/i.test(value), 'local source ' + value);
        const file = path.resolve(OUT, value);
        assert(file.startsWith(OUT + path.sep));
        assert(fs.existsSync(file), value);
      }
    }
  }
  assert(!/@import|https?:\/\//i.test(css));
  assert(!/fetch\s*\(|XMLHttpRequest|sendBeacon|WebSocket|FormData|localStorage|sessionStorage/.test(js));
});

test('three recommended apartments match the current verified public inventory snapshot', t => {
  const { d } = fixture(t);
  for (const row of facts) {
    const card = [...d.querySelectorAll('article')].find(n => n.textContent.includes(row.code));
    assert(card, row.code);
    const text = card.textContent.replace(/\s/g, '');
    assert(text.includes(String(row.price_huf)), 'official total price ' + row.code);
    assert(text.includes(String(row.sales_area_m2).replace('.', ',')), 'official sales area ' + row.code);
    assert(card.querySelector('img'), 'real floorplan thumbnail');
    assert.equal(row.status, 'current');
  }
});

test('no remote/unsafe links, missing anchor targets or page explanation placeholders', t => {
  const { d } = fixture(t);
  for (const a of d.querySelectorAll('a[href]')) {
    const href = a.getAttribute('href');
    if (href.startsWith('#')) assert(href.length > 1 && d.getElementById(href.slice(1)), href);
    else assert(/^https:\/\/harmat22\.hu(?:\/|$)|^mailto:ertekesites@harmat22\.hu$|^tel:\+36300733375$/.test(href), href);
  }
  assert(!/lorem ipsum|coming soon|placehold|TODO/i.test(d.body.textContent));
  assert(!/font-size\s*:[^;{}]*\b(?:[0-9.]+vw)\b|letter-spacing\s*:\s*-/.test(css));
});

test('local HU/Chinese switch roundtrips copy without changing codes/links or submitting anything', async t => {
  const f = fixture(t);
  boot(f);
  await tick();
  const links = [...f.d.querySelectorAll('a')].map(a => a.getAttribute('href'));
  const before = f.d.querySelector('main').textContent;
  const zh = f.d.querySelector('[data-language="zh"]');
  const hu = f.d.querySelector('[data-language="hu"]');
  assert(zh && hu, 'always-visible language controls');
  zh.click();
  await tick();
  assert.match(f.d.querySelector('main').textContent, /[\u4e00-\u9fff]/);
  assert(facts.every(row => f.d.querySelector('main').textContent.includes(row.code)));
  assert.deepEqual([...f.d.querySelectorAll('a')].map(a => a.getAttribute('href')), links);
  hu.click();
  await tick();
  assert.equal(f.d.querySelector('main').textContent, before);
});

test('reduced motion never automatically starts the local hero video', async t => {
  const f = fixture(t, true);
  boot(f);
  await tick();
  assert.equal(f.media.plays, 0);
});

test('header has an all-width menu and an explicit unchanged official booking destination', async t => {
  const f = fixture(t);
  boot(f);
  await tick();
  const header = f.d.querySelector('.site-header');
  const menu = header.querySelector('.site-menu');
  const summary = menu.querySelector('summary');
  assert(summary.querySelector('svg'), 'familiar hamburger control');
  assert(!summary.hidden && !menu.hidden, 'menu not deliberately hidden');
  assert.equal(header.querySelector('.language-switch').hidden, false);
  const booking = header.querySelector('.header-cta');
  assert(booking && !booking.hidden, 'visible appointment control');
  assert.equal(booking.getAttribute('href'), 'https://harmat22.hu/elerhetosegeink/');
  assert.match(booking.textContent, /Időpont/i);
  assert(!/\.header-cta\s*\{[^}]*display\s*:\s*none/.test(css), 'no old breakpoint hides appointment');
  f.d.querySelector('[data-language="zh"]').click();
  assert.match(booking.textContent, /预约/);
  assert.equal(booking.getAttribute('href'), 'https://harmat22.hu/elerhetosegeink/');
});

test('mobile menu closes after a local navigation link and Escape', async t => {
  const f = fixture(t);
  boot(f);
  const menu = f.d.querySelector('details.site-menu');
  assert(menu);
  menu.open = true;
  menu.dispatchEvent(new f.w.Event('toggle'));
  assert.equal(menu.querySelector('summary').getAttribute('aria-label'), 'Menü bezárása');
  menu.querySelector('a[href^="#"]').click();
  await tick();
  assert.equal(menu.open, false);
  menu.open = true;
  f.d.dispatchEvent(new f.w.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  assert.equal(menu.open, false);
});

test('language remains available while the local menu is open and preserves all three unit facts', async t => {
  const f = fixture(t);
  boot(f);
  const menu = f.d.querySelector('details.site-menu');
  menu.open = true;
  const zh = f.d.querySelector('[data-language="zh"]');
  zh.click();
  await tick();
  assert.equal(f.d.documentElement.lang, 'zh-Hans');
  assert.equal(f.d.querySelector('.language-switch').hidden, false);
  for (const row of facts) {
    const card = f.d.querySelector('article[data-code="' + row.code + '"]');
    assert(card.textContent.replace(/\s/g, '').includes(String(row.price_huf)));
  }
  assert.equal(menu.querySelector('summary').getAttribute('aria-label'), '关闭菜单');
});

test('video play/pause controls remain local, translated and show the poster after error', async t => {
  const f = fixture(t, false);
  boot(f);
  await tick();
  const video = f.d.querySelector('video');
  const button = f.d.querySelector('.video-button');
  assert.equal(f.media.plays, 1);
  assert.equal(video.getAttribute('src'), 'assets/hero-video.mp4');
  assert.equal(video.muted, true);
  assert.equal(video.dataset.revealed, 'true');
  button.click();
  assert.equal(f.media.pauses, 1);
  button.click();
  assert.equal(f.media.plays, 2);
  video.dispatchEvent(new f.w.Event('error'));
  assert.equal(video.dataset.revealed, undefined);
  assert.equal(button.disabled, true);
  f.d.querySelector('[data-language="zh"]').click();
  assert.match(button.getAttribute('aria-label'), /视频暂时无法播放/);
});

test('initialization is idempotent and never duplicates local interactions', t => {
  const f = fixture(t);
  boot(f);
  const before = f.d.querySelectorAll('svg').length;
  f.w.eval(js);
  assert.equal(f.d.querySelectorAll('svg').length, before);
  assert.equal(f.d.querySelectorAll('.language-switch').length, 1);
});

test('360 presentation is inline, separate from apartment selection, with all six original columns', t => {
  const { d } = fixture(t);
  const section = d.querySelector('#panorama');
  const chooser = d.querySelector('#lakasvalaszto');
  assert(section && chooser && section !== chooser);
  assert(section.compareDocumentPosition(chooser) & 4, 'panorama precedes chooser');
  assert(d.querySelector('.site-menu nav a[href="#panorama"]'));
  assert.equal(section.querySelectorAll('a[href]').length, 0, 'no navigate-away substitute');
  assert.deepEqual([...section.querySelectorAll('[data-panorama-tab]')].map(n => n.dataset.panoramaTab), ['panorama', 'video', 'plans', 'gallery', 'location', 'notice']);
  const viewer = section.querySelector('#panorama-viewer');
  for (const face of 'frblud') {
    const source = viewer.getAttribute('data-cube-' + face);
    assert.equal(source, 'assets/panorama-' + face + '.jpg');
    assert(fs.existsSync(path.join(OUT, source)));
  }
  assert(d.querySelector('script[src="assets/pannellum.js"]'));
  assert(d.querySelector('link[href="assets/pannellum.css"]'));
  assert(section.querySelector('img[src="assets/project-panorama.jpg"]'));
  assert.match(section.textContent, /Látványterv/i);
  assert.equal(d.querySelectorAll('iframe').length, 0, 'no remote embedded page');
});

test('panorama HU/Chinese copy and accessible poster link fully roundtrip', async t => {
  const f = fixture(t);
  boot(f);
  const section = f.d.querySelector('#panorama');
  const before = section.outerHTML;
  f.d.querySelector('[data-language="zh"]').click();
  await tick();
  assert.match(section.textContent, /360/);
  assert.match(section.textContent, /全景/);
  for (const node of section.querySelectorAll('[data-i18n]')) {
    if (node.dataset.i18n === 'panoramaEyebrow') assert.equal(node.textContent, 'Harmat Lakópark · 3D/360°');
    else assert.match(node.textContent, /[\u4e00-\u9fff]/, node.dataset.i18n);
    assert(!node.textContent.includes('undefined'));
  }
  for (const node of section.querySelectorAll('[data-i18n-alt]')) assert.match(node.alt, /[\u4e00-\u9fff]/);
  for (const node of section.querySelectorAll('[data-i18n-aria-label]')) assert.match(node.getAttribute('aria-label'), /[\u4e00-\u9fff]/);
  f.d.querySelector('[data-language="hu"]').click();
  await tick();
  assert.equal(section.outerHTML, before);
});

test('menu retains main official pages plus distinct 360 and appointment entries', async t => {
  const f = fixture(t);
  boot(f);
  const menu = f.d.querySelector('.site-menu');
  const hrefs = [...menu.querySelectorAll('nav a')].map(a => a.getAttribute('href'));
  for (const suffix of ['/lakaskereso/', '/virtualis-lakasvalaszto/', '/harmat-lakopark/', '/magunkrol/', '/harmat-lakopark-kornyeke/', '/galeria/', '/epitesi-naplo/', '/elerhetosegeink/']) {
    assert(hrefs.includes('https://harmat22.hu' + suffix), 'official page ' + suffix);
  }
  assert(hrefs.includes('#kezdolap'));
  assert(hrefs.includes('#panorama'));
  const appointment = menu.querySelector('.menu-booking');
  assert(appointment);
  assert.equal(appointment.getAttribute('href'), 'https://harmat22.hu/elerhetosegeink/');
  const before = menu.querySelector('nav').outerHTML;
  f.d.querySelector('[data-language="zh"]').click();
  await tick();
  assert.match(appointment.textContent, /预约/);
  assert.deepEqual([...menu.querySelectorAll('nav a')].map(a => a.getAttribute('href')), hrefs);
  f.d.querySelector('[data-language="hu"]').click();
  assert.equal(menu.querySelector('nav').outerHTML, before);
});

test('hero copy is reduced to the brand and original two working entry points', t => {
  const { d } = fixture(t);
  const hero = d.querySelector('.hero');
  assert.equal(hero.querySelector('h1').textContent.trim(), 'Harmat Lakópark');
  assert.equal(hero.querySelector('.hero-location'), null);
  assert.equal(hero.querySelector('.hero-description'), null);
  assert(hero.querySelector('.hero-copy').textContent.replace(/\s+/g, ' ').trim().length < 90);
  assert.deepEqual([...hero.querySelectorAll('.hero-actions a')].map(a => a.getAttribute('href')), ['#lakasok', '#epitesi-naplo']);
  assert(hero.querySelector('.video-button'));
  assert(hero.querySelector('[data-i18n="rendering"]'));
  assert.match(css, /\.hero h1\s*\{[^}]*font-size:\s*36px/);
});

test('original cubemap configuration initializes lazily and preserves viewer identity across languages', async t => {
  const f = fixture(t, false);
  boot(f);
  assert.equal(f.viewers.length, 0, 'no eager GPU initialization at the top of homepage');
  f.setNear(true);
  assert.equal(f.viewers.length, 1);
  const player = f.viewers[0], host = f.d.querySelector('#panorama-viewer');
  assert.equal(player.config.type, 'cubemap');
  assert.deepEqual(Array.from(player.config.cubeMap), [...'frblud'].map(face => 'assets/panorama-' + face + '.jpg'));
  assert.equal(player.config.pitch, -27);
  assert.equal(player.config.hfov, 92);
  assert.equal(player.config.mouseZoom, 'fullscreenonly');
  assert.equal(f.d.querySelector('.panorama-fallback').hidden, false);
  player.handlers.load();
  assert.equal(host.dataset.panoramaState, 'loaded');
  assert.equal(f.d.querySelector('.panorama-fallback').hidden, true);
  assert(player.calls.some(call => Array.isArray(call) && call[0] === 'startAutoRotate'));
  f.d.querySelector('[data-language="zh"]').click();
  await tick();
  assert.equal(f.d.querySelector('#panorama-viewer'), host);
  assert.equal(f.viewers.length, 1);
  assert.match(player.config.strings.loadingLabel, /全景/);
  f.d.querySelector('[data-language="hu"]').click();
  assert.equal(f.viewers.length, 1);
});

test('six inline panels switch with pointer and keyboard without navigation; hidden videos pause', async t => {
  const f = fixture(t);
  boot(f);
  f.setNear(true);
  const select = name => f.d.querySelector('[data-panorama-tab="' + name + '"]').click();
  for (const name of ['video', 'plans', 'gallery', 'location', 'notice', 'panorama']) {
    select(name);
    assert.deepEqual([...f.d.querySelectorAll('[data-panorama-panel]')].filter(n => !n.hidden).map(n => n.dataset.panoramaPanel), [name]);
    assert.equal(f.d.querySelector('[data-panorama-tab="' + name + '"]').getAttribute('aria-selected'), 'true');
  }
  const current = f.d.querySelector('[data-panorama-tab="panorama"]');
  current.dispatchEvent(new f.w.KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
  assert.equal(f.d.querySelector('[data-panorama-tab="video"]').getAttribute('aria-selected'), 'true');
  const [clip, other] = f.d.querySelectorAll('.panorama-video-card video');
  assert.equal(clip.autoplay, false);
  assert.equal(clip.preload, 'none');
  assert.equal(clip.playbackRate, 0.25);
  await clip.play();
  assert.equal(clip.paused, false);
  await other.play();
  assert.equal(clip.paused, true);
  assert.equal(other.paused, false);
  select('gallery');
  assert.equal(other.paused, true);
  assert.equal(f.viewers.length, 1);
});

test('rotate/reset/fullscreen controls preserve pause preference and stop offscreen/background movement', async t => {
  const f = fixture(t, false);
  boot(f); f.setNear(true); f.viewers[0].handlers.load();
  const rotate = f.d.querySelector('[data-panorama-rotate]'), player = f.viewers[0];
  assert.equal(rotate.getAttribute('aria-pressed'), 'true');
  f.setHidden(true);
  assert.equal(rotate.getAttribute('aria-pressed'), 'false');
  assert(player.calls.includes('stopAutoRotate'));
  f.setHidden(false);
  assert.equal(rotate.getAttribute('aria-pressed'), 'true');
  rotate.click();
  f.setNear(false); f.setNear(true);
  assert.equal(rotate.getAttribute('aria-pressed'), 'false', 'manual pause survives leaving and returning');
  f.d.querySelector('[data-panorama-reset]').click();
  assert(player.calls.some(call => Array.isArray(call) && call[0] === 'lookAt' && call[1] === -27));
  const full = f.d.querySelector('[data-panorama-fullscreen]');
  full.click(); await tick();
  assert.equal(f.d.fullscreenElement, f.d.querySelector('[data-panorama-console]'));
  assert.equal(player.config.mouseZoom, true);
  full.click(); await tick();
  assert.equal(f.d.fullscreenElement, null);
  assert.equal(player.config.mouseZoom, 'fullscreenonly');
});

test('gallery enlarges locally, translates while open and restores the triggering control', async t => {
  const f = fixture(t);
  boot(f);
  f.d.querySelector('[data-panorama-tab="gallery"]').click();
  const trigger = f.d.querySelector('[data-panorama-panel="gallery"] [data-gallery]');
  trigger.click();
  const dialog = f.d.querySelector('.panorama-lightbox');
  assert(dialog.open);
  assert.equal(dialog.querySelector('img').src, trigger.querySelector('img').src);
  f.d.querySelector('[data-language="zh"]').click();
  await tick();
  assert.match(dialog.querySelector('img').alt, /项目/);
  assert.match(dialog.querySelector('.panorama-lightbox-caption').textContent, /项目/);
  f.d.querySelector('[data-panorama-close]').click();
  assert.equal(dialog.open, false);
  assert.equal(f.d.activeElement, trigger);
});

test('WebGL or constructor failure retains a translated poster instead of an endless loader', async t => {
  const f = fixture(t);
  boot(f); f.setNear(true);
  f.viewers[0].handlers.error();
  const host = f.d.querySelector('#panorama-viewer'), status = f.d.querySelector('[data-panorama-status]');
  assert.equal(host.dataset.panoramaState, 'error');
  assert.equal(host.hidden, true);
  assert.equal(f.d.querySelector('.panorama-fallback').hidden, false);
  assert.equal(host.getAttribute('aria-busy'), 'false');
  f.d.querySelector('[data-language="zh"]').click();
  await tick();
  assert.match(status.textContent, /暂不可用/);
  f.viewers[0].handlers.load();
  assert(f.viewers[0].calls.includes('destroy'), 'late load cannot replace the safe fallback');
  assert.equal(host.dataset.panoramaState, 'error');
  f.d.querySelector('[data-panorama-tab="gallery"]').click();
  assert.equal(f.d.querySelector('[data-panorama-panel="gallery"]').hidden, false);
  const broken = fixture(t);
  broken.w.pannellum.viewer = () => { throw new Error('no WebGL'); };
  boot(broken); broken.setNear(true);
  assert.equal(broken.d.querySelector('#panorama-viewer').dataset.panoramaState, 'error');
  assert.equal(broken.d.querySelector('.panorama-fallback').hidden, false);
});

test('reduced motion and fullscreen rejection remain safe and fully localized', async t => {
  const f = fixture(t);
  boot(f); f.setNear(true); f.viewers[0].handlers.load();
  assert(!f.viewers[0].calls.some(call => Array.isArray(call) && call[0] === 'startAutoRotate'));
  const root = f.d.querySelector('[data-panorama-console]');
  root.requestFullscreen = () => Promise.reject(new Error('fullscreen denied'));
  f.d.querySelector('[data-panorama-fullscreen]').click(); await tick();
  const status = f.d.querySelector('[data-panorama-action-status]');
  assert.equal(status.hidden, false);
  f.d.querySelector('[data-language="zh"]').click();
  assert.match(status.textContent, /无法进入全屏/);
  assert.equal(f.d.querySelector('#panorama-viewer').dataset.panoramaState, 'loaded');
});

test('browsers without IntersectionObserver initialize the inline viewer when its bounds become visible', t => {
  const f = fixture(t);
  delete f.w.IntersectionObserver;
  const consoleNode = f.d.querySelector('[data-panorama-console]');
  consoleNode.getBoundingClientRect = () => ({ width: 900, height: 600, top: 50, bottom: 650 });
  boot(f);
  assert.equal(f.viewers.length, 1);
  assert.equal(f.d.querySelector('#panorama-viewer').dataset.panoramaState, 'loading');
});

test('actual V3 export embeds all original cube faces, local images and scripts byte-exact', t => {
  const bundle = fs.readFileSync(path.join(OUT, 'harmat-home-family-preview-v3.html'), 'utf8');
  const dom = new JSDOM(bundle, { runScripts: 'outside-only' });
  t.after(() => dom.window.close());
  const d = dom.window.document, host = d.querySelector('#panorama-viewer');
  for (const face of 'frblud') {
    const value = host.getAttribute('data-cube-' + face);
    assert(value.startsWith('data:image/jpeg;base64,'));
    assert(Buffer.from(value.split(',')[1], 'base64').equals(fs.readFileSync(path.join(OUT, 'assets/panorama-' + face + '.jpg'))));
  }
  for (const img of d.querySelectorAll('img')) assert(img.src.startsWith('data:image/'));
  assert.equal(d.querySelectorAll('script[src],link[rel="stylesheet"],iframe,form').length, 0);
  const scripts = [...d.querySelectorAll('script')];
  assert.equal(scripts.length, 3);
  assert.equal(scripts[2].textContent.replace(/\r\n/g, '\n'), js.replace(/\r\n/g, '\n'));
  assert(scripts[1].textContent.includes('pannellum'));
  assert.deepEqual([...new Set([...d.querySelectorAll('video')].map(v => v.getAttribute('src') || v.getAttribute('data-src')))].sort(),
    ['assets/hero-video.mp4', 'assets/panorama-project-video.mp4']);
});
