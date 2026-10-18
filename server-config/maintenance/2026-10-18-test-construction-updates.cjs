'use strict';
// Pure DOM/media stubs only. Not browser geometry, decoding, listening or fullscreen QA.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HHF_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const modulePath = path.join(ROOT, 'wp-mu-plugins/zz-harmat-construction-updates.php');
const fixture = path.join(__dirname, '2026-10-18-test-construction-updates.php');
const html = execFileSync(PHP, [fixture, modulePath, '--render'], { encoding: 'utf8' });
const moduleSource = fs.readFileSync(modulePath, 'utf8');
const controller = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/zz-harmat-home-family.php'), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));

function setup(t, marker = 'hu', reject = false) {
  const dom = new JSDOM(html, { url: 'https://harmat22.hu/epitesi-naplo/', runScripts: 'outside-only', pretendToBeVisual: true });
  const w = dom.window, d = w.document;
  d.documentElement.setAttribute('data-harmat-language', marker);
  const native = [...d.querySelectorAll('header,footer,#h22-offer-modal,.harmat-construction-september,.harmat-build-log-list')];
  const snapshots = native.map(node => node.outerHTML);
  const calls = [], paused = new WeakMap();
  let hidden = false;
  Object.defineProperty(d, 'hidden', { configurable: true, get: () => hidden });
  Object.defineProperty(w.HTMLMediaElement.prototype, 'paused', { configurable: true, get() { return paused.get(this) !== false; } });
  w.HTMLMediaElement.prototype.play = function () {
    calls.push(['play', this]);
    if (reject) return Promise.reject(new Error('blocked'));
    paused.set(this, false); this.dispatchEvent(new w.Event('play')); return Promise.resolve();
  };
  w.HTMLMediaElement.prototype.pause = function () { calls.push(['pause', this]); paused.set(this, true); };
  for (const key of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource']) w[key] = () => { throw new Error(`NO ${key}`); };
  w.HTMLFormElement.prototype.submit = w.HTMLFormElement.prototype.requestSubmit = () => { throw new Error('NO submit'); };
  const script = d.getElementById('harmat-construction-updates-runtime').textContent;
  w.eval(script);
  d.dispatchEvent(new w.Event('DOMContentLoaded'));
  t.after(() => {
    assert.deepEqual(native.map(node => node.outerHTML), snapshots, 'native shell, form, September and archive exact');
    dom.window.close();
  });
  return { w, d, calls, script, setHidden(value) { hidden = value; d.dispatchEvent(new w.Event('visibilitychange')); } };
}

test('five posters, dated descending, no MP4 source before human click', t => {
  const { d, calls } = setup(t);
  assert.equal(d.querySelectorAll('[data-hcu-player]').length, 5);
  assert.equal(d.querySelectorAll('[data-hcu-player] video,[data-hcu-player] source').length, 0);
  assert.deepEqual(calls, []);
  assert.equal(d.querySelector('[data-hcu-date]').dataset.hcuDate, '2026-10-13');
  assert.equal(d.querySelectorAll('h1').length, 0, 'module does not add heading-one');
  assert.equal(d.querySelector('[data-hcu-play]').tagName, 'BUTTON');
  assert.equal(d.querySelector('[data-hcu-play]').type, 'button');
});

test('click creates accessible muted inline native controls and exact local source', t => {
  const { d, calls } = setup(t);
  const button = d.querySelector('[data-hcu-play]'); button.click();
  const video = d.querySelector('[data-hcu-player] video');
  assert.ok(video.controls && video.playsInline && video.muted && video.defaultMuted);
  assert.equal(video.preload, 'none');
  assert.equal(video.hasAttribute('autoplay'), false);
  assert.equal(video.getAttribute('tabindex'), '0');
  assert.equal(d.activeElement, video);
  assert.match(video.src, /\/2026\/10\/construction-updates\/2026-10-13-compilation-hu-subtitled\.mp4$/);
  assert.match(video.getAttribute('aria-label'), /október 13/);
  assert.equal(calls.filter(([kind]) => kind === 'play').length, 1);
});

test('only one own active player including native-control resume, hidden page pauses', t => {
  const { d, calls, setHidden } = setup(t);
  const players = [...d.querySelectorAll('[data-hcu-player]')];
  players[0].querySelector('button').click();
  players[1].querySelector('button').click();
  const first = players[0].querySelector('video'), second = players[1].querySelector('video');
  assert.ok(first.paused && !second.paused);
  first.play(); assert.ok(!first.paused && second.paused);
  setHidden(true); assert.ok(first.paused);
  assert.ok(calls.some(([kind, video]) => kind === 'pause' && video === first));
});

test('HU to Chinese and back covers copy, posters, controls and live video', async t => {
  const { d } = setup(t);
  const root = d.querySelector('[data-hcu-root]'), hu = root.textContent;
  const poster = root.querySelector('img');
  d.documentElement.setAttribute('data-harmat-language', 'zh-Hans'); await tick();
  assert.equal(root.lang, 'zh-Hans');
  assert.match(root.querySelector('h3').textContent, /2026年10月13日/);
  assert.match(poster.alt, /施工日志/);
  assert.equal(root.querySelector('button').getAttribute('aria-label'), '播放视频');
  root.querySelector('button').click();
  const video = root.querySelector('video'); assert.match(video.getAttribute('aria-label'), /施工日志/);
  d.documentElement.setAttribute('data-harmat-language', 'hu'); await tick();
  assert.equal(root.lang, 'hu'); assert.match(video.getAttribute('aria-label'), /Építési napló/);
  assert.equal(root.querySelector('h3').textContent, 'Építési napló – 2026. október 13.');
  assert.ok(hu.includes(root.querySelector('h3').textContent));
});

test('initial remembered Chinese mode does not flash permanent Hungarian copy', t => {
  const { d } = setup(t, 'zh-Hans');
  assert.equal(d.querySelector('[data-hcu-root]').lang, 'zh-Hans');
  assert.equal(d.querySelector('[data-hcu-root] h2').textContent, '最新施工影像');
});

test('decode error has persistent local direct fallback and bilingual status', async t => {
  const { d, w } = setup(t);
  const player = d.querySelector('[data-hcu-player]'); player.querySelector('button').click();
  const fallback = player.parentNode.querySelector('a.hcu-direct');
  player.querySelector('video').dispatchEvent(new w.Event('error'));
  const status = player.nextElementSibling; assert.equal(status.getAttribute('role'), 'status');
  assert.match(status.textContent, /nem tölthető/);
  assert.equal(fallback.getAttribute('target'), '_blank'); assert.match(fallback.href, /\.mp4$/);
  d.documentElement.setAttribute('data-harmat-language', 'zh-Hans'); await tick();
  assert.match(status.textContent, /无法加载/);
  assert.equal(fallback.textContent, '在新标签页打开视频');
});

test('rejected gesture play retains native controls and localized guidance', async t => {
  const { d } = setup(t, 'zh-Hans', true);
  d.querySelector('[data-hcu-play]').click(); await tick();
  assert.match(d.querySelector('[data-hcu-status]').textContent, /视频控件/);
  assert.ok(d.querySelector('[data-hcu-player] video').controls);
});

test('malformed media URL cannot create a player or dispatch a request', t => {
  const { d, calls } = setup(t);
  const player = d.querySelector('[data-hcu-player]');
  player.dataset.hcuVideo = 'https://evil.test/private.mp4';
  player.querySelector('button').click();
  assert.equal(player.querySelector('video'), null); assert.deepEqual(calls, []);
});

test('runtime repeated initialization is a no-op, no duplicate click handling', t => {
  const { d, w, script, calls } = setup(t);
  w.eval(script); d.querySelector('[data-hcu-play]').click();
  assert.equal(calls.filter(([kind]) => kind === 'play').length, 1);
  assert.equal(d.querySelectorAll('[data-hcu-player] video').length, 1);
});

test('scoped CSS resets legacy all-caps/padding and uses full-frame stable media', () => {
  const dom = new JSDOM(html);
  const css = dom.window.document.getElementById('harmat-construction-updates-css').textContent;
  assert.match(css, /\[data-hcu-root\] h2,\[data-hcu-root\] h3\{padding:0;[^}]*text-transform:none/);
  assert.match(css, /aspect-ratio:16\/9/); assert.match(css, /object-fit:contain/);
  assert.match(css, /max-width:960px/); assert.match(css, /@media\(max-width:620px\)/);
  assert.equal(css.includes('object-fit:cover'), false);
  dom.window.close();
});

test('homepage hero fills width while construction retains comfortable click-only media', () => {
  assert.equal(controller.includes('.hhf-hero{margin-top:76px;scroll-margin-top:152px}'), false);
  assert.match(controller, /\.hhf-hero\{width:100%;max-width:none;margin:0;scroll-margin-top:76px/);
  assert.equal(controller.includes('.hhf-hero-inner{grid-area:2/1'), false);
  assert.equal(controller.includes('.hhf-hero::before{display:none'), false);
  const pinnedCss = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-home-family/home-family.css'), 'utf8');
  assert.match(pinnedCss, /\.hhf-hero-video\s*\{[^}]*object-fit:\s*cover/);
  assert.match(controller, /function_exists\('harmat_cu_home_teaser'\)/);
  assert.match(controller, /function_exists\('harmat_cu_resources'\)/);
  assert.equal(/update_post|wp_insert|wp_mail|register_rest_route|fetch\(/.test(moduleSource), false);
});
