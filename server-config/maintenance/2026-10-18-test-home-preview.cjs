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
  let paused = true;
  Object.defineProperty(w.HTMLMediaElement.prototype, 'paused', { configurable: true, get() { return paused; } });
  w.HTMLMediaElement.prototype.play = function () { paused = false; media.plays++; this.dispatchEvent(new w.Event('playing')); return Promise.resolve(); };
  w.HTMLMediaElement.prototype.pause = function () { paused = true; media.pauses++; this.dispatchEvent(new w.Event('pause')); };
  t.after(() => { w.close(); assert.deepEqual(requests, []); });
  return { w, d: w.document, media };
}

function boot(f) {
  f.w.eval(fs.readFileSync(path.join(OUT, 'assets/lucide.min.js'), 'utf8'));
  f.w.eval(js);
  f.d.dispatchEvent(new f.w.Event('DOMContentLoaded'));
}

test('production WordPress/quote/appointment sources remain exactly the current stable checkout', () => {
  const changed = execFileSync('git', ['diff', '--name-only', '2e3f738', '--', 'wp-mu-plugins', 'wp-plugins'], { cwd: ROOT, encoding: 'utf8' });
  assert.equal(changed.trim(), '');
});

test('one branded H1, genuine media, no recreated forms or embedded live engines', t => {
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
  for (const selector of ['img[src]', 'video[src]', 'video[poster]', 'source[src]', 'script[src]', 'link[rel="stylesheet"]']) {
    for (const node of d.querySelectorAll(selector)) {
      const names = selector.startsWith('link') ? ['href'] : selector === 'video[poster]' ? ['poster'] : ['src'];
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
  const buttons = [...f.d.querySelectorAll('button')];
  const zh = buttons.find(b => b.textContent.trim() === '中文');
  const hu = buttons.find(b => b.textContent.trim() === 'Magyar');
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

test('desktop navigation is not trapped in a closed mobile details element', t => {
  const { d } = fixture(t);
  const desktop = d.querySelector('nav.desktop-nav');
  assert(desktop && !desktop.closest('details'));
  assert.equal(desktop.querySelectorAll('a').length, 4);
  assert(css.includes('.desktop-nav'));
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
  const zh = [...f.d.querySelectorAll('button')].find(b => b.textContent.trim() === '中文');
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
  [...f.d.querySelectorAll('button')].find(b => b.textContent.trim() === '中文').click();
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

test('360 panorama stays distinct from apartment selection and uses the original official module', t => {
  const { d } = fixture(t);
  const section = d.querySelector('#panorama');
  const chooser = d.querySelector('#lakasvalaszto');
  assert(section && chooser && section !== chooser);
  assert(section.compareDocumentPosition(chooser) & 4, 'panorama precedes chooser');
  assert(d.querySelector('.desktop-nav a[href="#panorama"]'));
  assert(d.querySelector('.site-menu nav a[href="#panorama"]'));
  const links = section.querySelectorAll('a[href="https://harmat22.hu/harmat-lakopark-kornyeke/#harmat-3d-tour"]');
  assert(links.length >= 2, 'poster and copy both lead to original official panorama');
  for (const a of links) {
    assert.equal(a.target, '_blank');
    assert(a.rel.includes('noopener') && a.rel.includes('noreferrer'));
  }
  assert(section.querySelector('img[src="assets/project-panorama.jpg"]'));
  assert.match(section.textContent, /Látványterv/i);
  assert.equal(d.querySelectorAll('canvas,iframe').length, 0, 'no offline substitute engine');
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
