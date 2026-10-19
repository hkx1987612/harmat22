'use strict';
// API/DOM fixtures only, not browser fullscreen, visual, or real IntersectionObserver evidence.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HHF_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const controller = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/zz-harmat-home-family.php'), 'utf8');
const body = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-home-family/body.php'), 'utf8').match(/<<<'HHF_MAIN_HTML'\r?\n([\s\S]*?)\r?\nHHF_MAIN_HTML/)[1];
const playbackJS = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-home-family/home-family-scroll-play.js'), 'utf8');
const assets = execFileSync(PHP, [path.join(__dirname, '2026-10-19-test-home-video-fullscreen.php'), '--render'], { encoding: 'utf8' });
const script = assets.match(/<script id="harmat-home-video-fullscreen-js">([\s\S]*?)<\/script>/)[1];
const css = assets.match(/<style id="harmat-home-video-fullscreen-css">([\s\S]*?)<\/style>/)[1];
const tick = () => new Promise(resolve => setImmediate(resolve));

async function fixture(t, options = {}) {
  const shell = `<html data-harmat-language="${options.language || 'hu'}"><head><title>Native SEO</title></head><body><header id="masthead">Native menu</header>${options.body || body}<footer id="colophon">Native footer</footer><form id="h22-offer-form"><input name="contact" value="Unsent draft"><textarea name="message">Original draft</textarea><select name="source"><option value="Google" selected>Google</option></select><input name="privacy" type="checkbox" required></form><form id="appointment-form"><input type="date" value="2026-10-22"><input name="time" value="12-15"></form></body></html>`;
  const dom = new JSDOM(shell, { url: 'https://harmat22.hu/', runScripts: 'outside-only', pretendToBeVisual: true });
  const w = dom.window, d = w.document;
  const root = d.querySelector('#harmat-home-family');
  const hero = root.querySelector('.hhf-hero');
  const video = root.querySelector('video.hhf-hero-video');
  const pause = root.querySelector('.hhf-video-control');
  const protectedNodes = [...d.querySelectorAll('header,footer,form')];
  const originals = protectedNodes.map(n => n.outerHTML);
  const originalFields = [...d.querySelectorAll('form input,form textarea,form select')].map(n => [n.value, n.checked]);
  const calls = [], exitCalls = [], focus = [], effects = [], observers = [], media = [];
  const desktop = { matches: options.mobile !== true, listeners: [], addEventListener(type, cb) { this.listeners.push(cb); } };
  const reduced = { matches: !!options.reduced, addEventListener() {} };
  w.matchMedia = query => query.includes('min-width') ? desktop : reduced;
  let fullscreen = null, hidden = false, paused = true;
  Object.defineProperty(d, 'fullscreenElement', { get: () => fullscreen });
  Object.defineProperty(d, 'fullscreenEnabled', { value: options.supported !== false });
  Object.defineProperty(d, 'hidden', { get: () => hidden });
  Object.defineProperty(video, 'paused', { get: () => paused });
  w.HTMLMediaElement.prototype.play = function () { media.push(['play', this]); if (this === video) { paused = false; this.dispatchEvent(new w.Event('playing')); } return Promise.resolve(); };
  w.HTMLMediaElement.prototype.pause = function () { media.push(['pause', this]); if (this === video) paused = true; };
  for (const name of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource']) w[name] = () => { effects.push(name); throw Error('NETWORK_FORBIDDEN'); };
  w.navigator.sendBeacon = () => { effects.push('beacon'); throw Error('NO_BEACON'); };
  w.Storage.prototype.setItem = () => { effects.push('storage'); throw Error('NO_STORAGE'); };
  w.HTMLFormElement.prototype.submit = w.HTMLFormElement.prototype.requestSubmit = () => { effects.push('submit'); throw Error('NO_SUBMIT'); };
  for (const form of d.querySelectorAll('form')) for (const event of ['submit', 'input', 'change', 'click']) form.addEventListener(event, () => effects.push(event));
  const nativeFocus = w.HTMLElement.prototype.focus;
  w.HTMLElement.prototype.focus = function (opts) {
    if (this.id === 'harmat-home-video-fullscreen-button') focus.push({ disabled: this.disabled, opts: { preventScroll: opts?.preventScroll } });
    return nativeFocus.call(this, opts);
  };
  function full(element) { fullscreen = element; d.dispatchEvent(new w.Event('fullscreenchange')); }
  hero.requestFullscreen = function () {
    calls.push(this);
    if (options.request) return options.request({ full, hero });
    full(hero);
    return Promise.resolve();
  };
  d.exitFullscreen = () => {
    exitCalls.push(fullscreen);
    if (options.exit) return options.exit({ full, hero });
    full(null);
    return Promise.resolve();
  };
  if (options.noAPI) delete hero.requestFullscreen;
  if (options.noExitAPI) delete d.exitFullscreen;
  hero.getBoundingClientRect = () => ({ width: 1280, height: 720, top: fullscreen === hero ? 0 : 76, bottom: fullscreen === hero ? 720 : 796, left: 0, right: 1280 });
  w.IntersectionObserver = class {
    constructor(cb) { this.cb = cb; observers.push(this); }
    observe(target) { this.target = target; }
  };
  if (options.native) w.eval(playbackJS);
  else if (!options.pauseHidden) pause.hidden = false;
  const pauseHTML = pause.outerHTML;
  const videoHTML = video.outerHTML;
  w.eval(script);
  await tick();
  const button = d.querySelector('#harmat-home-video-fullscreen-button');
  const control = d.querySelector('#harmat-home-video-fullscreen-control');
  const tooltip = d.querySelector('#harmat-home-video-fullscreen-tooltip');
  const status = d.querySelector('#harmat-home-video-fullscreen-status');
  t.after(() => {
    assert.deepEqual(protectedNodes.map(n => n.outerHTML), originals, 'native shell/form markup unchanged');
    assert.deepEqual([...d.querySelectorAll('form input,form textarea,form select')].map(n => [n.value, n.checked]), originalFields, 'native field values unchanged');
    assert.deepEqual(effects, [], 'no network/storage/form effects');
    assert.equal(d.title, 'Native SEO');
    if (!options.native) assert.equal(pause.outerHTML, pauseHTML, 'pause control and hidden attribute unchanged');
    if (!options.native) assert.equal(video.outerHTML, videoHTML, 'all video attributes unchanged, including initialization');
    w.close();
  });
  return { w, d, root, hero, video, pause, desktop, button, control, tooltip, status, calls, exitCalls, focus, media,
    full,
    async resize(matches) { desktop.matches = matches; desktop.listeners.forEach(cb => cb()); await tick(); },
    async language(marker) { d.documentElement.setAttribute('data-harmat-language', marker); await tick(); },
    intersect(on) { observers.filter(o => o.target === root.querySelector('.hhf-hero')).forEach(o => o.cb([{ isIntersecting: on, intersectionRatio: on ? 1 : 0 }])); },
    setHidden(on) { hidden = on; d.dispatchEvent(new w.Event('visibilitychange')); }
  };
}

test('current home preserves full-frame CSS and changes only manifest/runtime selection plus version', () => {
  const old = execFileSync('git', ['show', '5813e37:wp-mu-plugins/zz-harmat-home-family.php'], { cwd: ROOT, encoding: 'utf8' });
  const crypto = require('node:crypto');
  assert.equal(crypto.createHash('sha256').update(old).digest('hex'), 'b1aab40a51dab20d042a3a966e426fe6ff8cfe10bfe992e852acbd551da82d8b');
  const dir = path.join(ROOT, 'wp-mu-plugins/harmat-home-family');
  const manifest = fs.readFileSync(path.join(dir, 'manifest-scroll-play.json'));
  const manifestSha = crypto.createHash('sha256').update(manifest).digest('hex');
  const expected = old.replace('Version: 1.0.12', 'Version: 1.0.13')
    .replace("$directory.'/manifest.json'", "$directory.'/manifest-scroll-play.json'")
    .replace('21e6ebec9bf0bfcbc273c42b5dc33f07ce96547fa04ccdd0b53faf780d3587b3', manifestSha)
    .replace("$pins['home-family.js']))", "$pins['home-family.js'],$pins['home-family-scroll-play.js']))")
    .replace("array('pannellum.js','home-family.js')", "array('pannellum.js','home-family-scroll-play.js')");
  assert.equal(controller.replaceAll('\r\n', '\n'), expected);
  const pins = JSON.parse(manifest);
  assert.deepEqual(pins, { ...JSON.parse(fs.readFileSync(path.join(dir, 'manifest.json'), 'utf8')),
    'home-family-scroll-play.js': crypto.createHash('sha256').update(playbackJS).digest('hex') });
  for (const [name, sha] of Object.entries(pins)) {
    assert.equal(crypto.createHash('sha256').update(fs.readFileSync(path.join(dir, name))).digest('hex'), sha, name);
  }
});

test('own CSS scopes top-right entry and below-button tooltip; fullscreen overrides negative stacking and opacity', () => {
  assert.match(css, /control\{position:absolute;top:20px;right:24px;z-index:3\}/);
  assert.match(css, /tooltip\{position:absolute;right:0;top:54px;/);
  assert.match(css, /@media\(max-width:767px\)/);
  for (const value of ['position:fixed!important', 'object-fit:contain!important', 'opacity:1!important', 'background:#000!important', 'z-index:1!important', 'pointer-events:none', 'pointer-events:auto']) assert.ok(css.includes(value));
  assert.match(css, /:fullscreen \.hhf-hero-copy,[^\n]*\.hhf-media-label,[^\n]*:fullscreen::before\{display:none!important\}/);
  assert.match(css, /:fullscreen \.hhf-hero-inner\{position:absolute!important;inset:0!important;width:100%!important;max-width:none!important;height:100%!important;padding:0!important;pointer-events:none/);
  assert.match(css, /:fullscreen \.hhf-hero-bottom\{left:auto;right:24px;bottom:20px\}/);
  assert.ok(css.split('\n').filter(line => line.trim()).every(line => line.startsWith('#harmat-home-family ') || line.startsWith('@media(')));
});

test('top-right button clones both existing Lucide SVGs without old attributes; boot twice is inert', async t => {
  const f = await fixture(t);
  assert.equal(f.control.parentNode, f.root.querySelector('.hhf-hero'));
  assert.equal(f.control.hidden, false);
  assert.equal(f.button.type, 'button');
  assert.equal(f.button.getAttribute('aria-controls'), f.hero.id);
  for (const name of ['enter', 'exit']) {
    const original = f.root.querySelector(`[data-panorama-fullscreen] [data-fullscreen-${name}]`);
    assert.equal(f.button.querySelector(`[data-hhvf-icon="${name}"]`).innerHTML, original.innerHTML);
    assert.equal(f.button.querySelector(`[data-fullscreen-${name}]`), null);
  }
  assert.equal(f.button.querySelector('[data-hhvf-icon="exit"]').hasAttribute('hidden'), true);
  const snapshot = f.control.outerHTML;
  f.w.eval(script);
  assert.equal(f.control.outerHTML, snapshot);
  assert.equal(f.d.querySelectorAll('#harmat-home-video-fullscreen-button').length, 1);
});

test('HU/ZH labels, tooltip and failure live status follow only existing HTML marker', async t => {
  const f = await fixture(t, { request: () => Promise.reject(Error('denied')) });
  assert.equal(f.button.title, 'Vide\u00f3 teljes k\u00e9perny\u0151n');
  await f.language('zh-Hans');
  assert.equal(f.button.title, '\u5168\u5c4f\u64ad\u653e\u89c6\u9891');
  assert.equal(f.tooltip.textContent, f.button.title);
  f.button.click(); await tick();
  assert.match(f.status.textContent, /\u65e0\u6cd5/);
  assert.equal(f.status.getAttribute('aria-live'), 'polite');
  await f.language('hu');
  assert.match(f.status.textContent, /Pr\u00f3b\u00e1lja/);
  assert.equal(f.button.getAttribute('aria-label'), f.button.title);
});

test('own fullscreen exposes exit icon and HU/ZH exit labels; repeated marker stays inert', async t => {
  const f = await fixture(t);
  f.button.click(); await tick();
  assert.match(f.button.title, /^Kil\u00e9p\u00e9s/);
  assert.equal(f.button.disabled, false);
  assert.equal(f.button.querySelector('[data-hhvf-icon="enter"]').hasAttribute('hidden'), true);
  assert.equal(f.button.querySelector('[data-hhvf-icon="exit"]').hasAttribute('hidden'), false);
  await f.language('zh-Hans');
  assert.equal(f.button.title, '\u9000\u51fa\u5168\u5c4f');
  assert.equal(f.button.getAttribute('aria-label'), f.tooltip.textContent);
  const changes = [], tooltipText = f.tooltip.firstChild;
  const observer = new f.w.MutationObserver(records => changes.push(...records));
  observer.observe(f.control, { subtree: true, attributes: true, childList: true, characterData: true });
  for (let i = 0; i < 4; ++i) await f.language('zh-Hans');
  assert.equal(changes.length, 0);
  assert.equal(f.tooltip.firstChild, tooltipText);
  observer.disconnect();
  f.button.click(); await tick();
  assert.equal(f.exitCalls.length, 1);
  assert.equal(f.button.title, '\u5168\u5c4f\u64ad\u653e\u89c6\u9891');
  assert.equal(f.button.querySelector('[data-hhvf-icon="enter"]').hasAttribute('hidden'), false);
});

test('same-marker language reassertions do not mutate own DOM or replace text nodes', async t => {
  const f = await fixture(t, { request: () => Promise.reject(Error('denied')) });
  f.button.click(); await tick();
  const tooltipText = f.tooltip.firstChild, statusText = f.status.firstChild;
  const changes = [];
  const observer = new f.w.MutationObserver(records => changes.push(...records));
  observer.observe(f.control, { subtree: true, attributes: true, characterData: true, childList: true });
  for (let i = 0; i < 8; ++i) await f.language('hu');
  assert.equal(f.tooltip.firstChild, tooltipText);
  assert.equal(f.status.firstChild, statusText);
  assert.equal(changes.length, 0);
  observer.disconnect();
});

for (const controls of [null, '', 'controls', 'custom-original']) test(`hero fullscreen never mutates video attributes (${controls})`, async t => {
  const f = await fixture(t);
  const original = f.video.outerHTML;
  if (controls !== null) f.video.setAttribute('controls', controls);
  const before = f.video.outerHTML;
  const muted = f.video.muted, time = f.video.currentTime, src = f.video.getAttribute('src');
  f.button.click(); await tick();
  assert.equal(f.calls[0], f.hero, 'requests hero, never video or whole page');
  assert.equal(f.video.outerHTML, before);
  assert.equal(f.button.disabled, false, 'own exit remains enabled');
  f.button.click(); await tick();
  assert.deepEqual(f.exitCalls, [f.hero]);
  assert.equal(f.video.outerHTML, before);
  assert.deepEqual(f.focus, [{ disabled: false, opts: { preventScroll: true } }]);
  assert.equal(f.d.activeElement, f.button);
  assert.equal(f.video.muted, muted);
  assert.equal(f.video.currentTime, time);
  assert.equal(f.video.getAttribute('src'), src);
  assert.deepEqual(f.media, [], 'no addon playback calls');
  if (controls !== null) f.video.removeAttribute('controls');
  assert.equal(f.video.outerHTML, original);
});

for (const mode of ['reject', 'throw']) test(`request ${mode} is handled and focus restores only after enabled`, async t => {
  const f = await fixture(t, { request: () => { if (mode === 'throw') throw Error('denied'); return Promise.reject(Error('denied')); } });
  const before = f.video.outerHTML;
  f.button.click(); await tick();
  assert.equal(f.video.outerHTML, before);
  assert.equal(f.button.disabled, false);
  assert.equal(f.d.activeElement, f.button);
  assert.equal(f.focus.length, 1);
  assert.equal(f.focus[0].disabled, false);
  assert.equal(f.focus[0].opts.preventScroll, true);
  assert.ok(f.status.textContent);
});

test('pending double clicks request once; retry works after escape or failed entry', async t => {
  let resolve;
  const f = await fixture(t, { request: () => new Promise(done => { resolve = done; }) });
  f.button.click(); f.button.click();
  f.button.dispatchEvent(new f.w.MouseEvent('click'));
  assert.equal(f.calls.length, 1);
  assert.equal(f.video.controls, false);
  assert.equal(f.video.getAttribute('aria-hidden'), 'true');
  f.full(f.hero); resolve(); await tick();
  assert.equal(f.video.controls, false);
  assert.equal(f.button.disabled, false);
  f.full(null); await tick();
  f.button.click(); assert.equal(f.calls.length, 2);
  resolve(); await tick();
  assert.equal(f.video.controls, false);
  assert.ok(f.status.textContent, 'resolved without entering is handled as failure');
});

test('unrelated fullscreen cannot change video attributes or steal focus', async t => {
  const f = await fixture(t);
  const before = f.video.outerHTML;
  f.full(f.root.querySelector('[data-panorama-console]')); await tick();
  f.button.click();
  f.full(null); await tick();
  assert.equal(f.calls.length, 0);
  assert.equal(f.video.outerHTML, before);
  assert.deepEqual(f.focus, []);
});

test('exit rejection leaves retry enabled, reports status, and restores focus after busy clears', async t => {
  const f = await fixture(t, { exit: () => Promise.reject(Error('exit denied')) });
  f.button.click(); await tick();
  const before = f.video.outerHTML;
  f.button.click(); await tick();
  assert.equal(f.d.fullscreenElement, f.hero);
  assert.equal(f.button.disabled, false);
  assert.match(f.button.title, /^Kil\u00e9p\u00e9s/);
  assert.ok(f.status.textContent);
  assert.equal(f.d.activeElement, f.button);
  assert.equal(f.focus[0].disabled, false);
  assert.equal(f.video.outerHTML, before);
  f.full(null); await tick();
  assert.equal(f.status.textContent, '');
});

test('pending exit ignores repeated clicks and restores focus once after completion', async t => {
  let resolve;
  const f = await fixture(t, { exit: () => new Promise(done => { resolve = done; }) });
  f.button.click(); await tick();
  f.button.click(); f.button.dispatchEvent(new f.w.MouseEvent('click'));
  assert.equal(f.exitCalls.length, 1);
  f.full(null);
  assert.equal(f.focus.length, 0, 'fullscreenchange while busy cannot focus disabled button');
  resolve(); await tick();
  assert.equal(f.focus.length, 1);
  assert.equal(f.focus[0].disabled, false);
});

test('mobile starts hidden, responds to768+ resize and never changes pause hidden state', async t => {
  const f = await fixture(t, { mobile: true, pauseHidden: true });
  assert.equal(f.control.hidden, true);
  await f.resize(true); assert.equal(f.control.hidden, true);
  f.pause.hidden = false; await tick(); assert.equal(f.control.hidden, false);
  await f.resize(false); assert.equal(f.control.hidden, true);
  f.button.click(); assert.equal(f.calls.length, 0);
  f.pause.hidden = true;
});

for (const options of [{ supported: false }, { noAPI: true }, { noExitAPI: true }]) test(`unsupported fullscreen adds no visible entry ${JSON.stringify(options)}`, async t => {
  const f = await fixture(t, options);
  assert.equal(f.control.hidden, true);
  f.button.click(); assert.equal(f.calls.length, 0);
});

for (const mode of ['duplicate-root', 'duplicate-video', 'missing-icon', 'missing-exit-icon', 'collision']) test(`ambiguous DOM fails closed: ${mode}`, async t => {
  let markup = body;
  if (mode === 'duplicate-root') markup += '<div id="harmat-home-family"></div>';
  if (mode === 'duplicate-video') markup = markup.replace('<video class="hhf-hero-video"', '<video class="hhf-hero-video"></video><video class="hhf-hero-video"');
  if (mode === 'missing-icon') markup = markup.replace('data-fullscreen-enter=""', 'data-other-enter=""');
  if (mode === 'missing-exit-icon') markup = markup.replace('data-fullscreen-exit=""', 'data-other-exit=""');
  if (mode === 'collision') markup += '<span id="harmat-home-video-fullscreen-status"></span>';
  const f = await fixture(t, { body: markup });
  assert.equal(f.button, null);
  assert.equal(f.hero.hasAttribute('data-hhvf-hero'), false);
});

test('current playback keeps playing offscreen and retains manual/background pause with addon present', async t => {
  const f = await fixture(t, { native: true });
  f.intersect(true);
  assert.equal(f.video.paused, false);
  assert.equal(f.video.muted, true);
  f.pause.querySelector('button').click();
  assert.equal(f.video.paused, true);
  const calls = f.media.length;
  f.button.click(); await tick();
  f.intersect(true);
  assert.equal(f.video.paused, true, 'enter/resize does not reset private userPaused');
  f.pause.querySelector('button').click();
  assert.equal(f.video.paused, false, 'existing pause button can resume inside fullscreen');
  f.pause.querySelector('button').click();
  assert.equal(f.video.paused, true, 'existing pause button owns pause inside fullscreen');
  const pausedCalls = f.media.length;
  f.button.click(); await tick();
  f.intersect(true);
  assert.equal(f.video.paused, true);
  assert.equal(f.media.length, pausedCalls, 'fullscreen exit never resumes manually paused video');
  assert.equal(pausedCalls, calls + 2, 'only original pause button triggers play/pause');
  f.pause.querySelector('button').click(); assert.equal(f.video.paused, false);
  f.intersect(false); assert.equal(f.video.paused, false, 'scrolling offscreen no longer pauses');
  f.intersect(true); assert.equal(f.video.paused, false);
  f.setHidden(true); assert.equal(f.video.paused, true);
});

test('fullscreen reveal CSS retains native poster fallback and never changes failed video attributes', async t => {
  const opacityRules = css.split('\n').filter(line => line.includes('.hhf-hero-video') && line.includes('opacity:'));
  assert.equal(opacityRules.length, 2);
  assert.match(opacityRules[0], /\.hhf-hero-video\{z-index:1!important;opacity:0!important;/);
  assert.match(opacityRules[1], /\.hhf-hero-video\[data-revealed="true"\]\{opacity:1!important\}/);
  assert.match(css, /\.hhf-hero-poster\{z-index:0!important\}/);
  const f = await fixture(t, { native: true });
  f.intersect(true);
  assert.equal(f.video.getAttribute('data-revealed'), 'true');
  f.pause.querySelector('button').click();
  assert.equal(f.video.paused, true);
  assert.equal(f.video.getAttribute('data-revealed'), 'true', 'paused revealed frame stays visible');
  f.button.click(); await tick();
  f.video.dispatchEvent(new f.w.Event('error'));
  assert.equal(f.video.hasAttribute('data-revealed'), false, 'native failure owns poster fallback');
  const failedHTML = f.video.outerHTML;
  f.button.click(); await tick();
  f.button.click(); await tick();
  assert.equal(f.video.outerHTML, failedHTML, 'fullscreen exit/reentry leaves every failed video attribute untouched');
  assert.equal(f.video.controls, false);
});

test('pinned reduced-motion policy is unchanged by fullscreen entry/exit', async t => {
  const f = await fixture(t, { native: true, reduced: true });
  f.intersect(true); assert.equal(f.video.paused, true);
  f.button.click(); await tick(); f.full(null); await tick();
  assert.equal(f.video.paused, true);
  assert.equal(f.media.length, 0);
});
