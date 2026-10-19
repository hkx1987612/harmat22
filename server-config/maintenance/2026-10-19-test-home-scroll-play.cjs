'use strict';
// Local DOM/API stubs only: no browser, network, decoding, or visual QA.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const DIR = path.join(ROOT, 'wp-mu-plugins/harmat-home-family');
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HHF_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const original = fs.readFileSync(path.join(DIR, 'home-family.js'));
const candidate = fs.readFileSync(path.join(DIR, 'home-family-scroll-play.js'));
const script = candidate.toString('utf8');
// Reuse the real body renderer and approved public rows, not copied HTML or runtime code.
const html = execFileSync(PHP, [path.join(__dirname, '2026-10-18-test-home-family-presentation.php'), '--render'], { encoding: 'utf8' });
const tick = () => new Promise(resolve => setImmediate(resolve));
const sha = value => crypto.createHash('sha256').update(value).digest('hex');
const allowedChanges = [
  [
    "  const hero = video.closest('.hhf-hero');\n",
    ""
  ],
  [
    "  let heroInViewport = false;\n",
    ""
  ],
  [
    "  function heroWithinViewport() {\n    const bounds = hero.getBoundingClientRect();\n    return bounds.width > 0 && bounds.height > 0 && bounds.bottom > 0 && bounds.top < window.innerHeight\n      && bounds.right > 0 && bounds.left < window.innerWidth;\n  }\n\n",
    ""
  ],
  [
    "return !videoFailed && heroInViewport && heroWithinViewport() && !document.hidden && !userPaused",
    "return !videoFailed && !document.hidden && !userPaused"
  ],
  [
    "  if ('IntersectionObserver' in window) {\n    const heroObserver = new IntersectionObserver(entries => {\n      heroInViewport = entries.some(entry => entry.isIntersecting || entry.intersectionRatio > 0);\n      syncHeroPlayback();\n    }, { threshold: 0 });\n    heroObserver.observe(hero);\n  } else {\n    const updateHeroVisibility = () => {\n      heroInViewport = heroWithinViewport();\n      syncHeroPlayback();\n    };\n    window.addEventListener('scroll', updateHeroVisibility, { passive: true });\n    window.addEventListener('resize', updateHeroVisibility);\n    updateHeroVisibility();\n  }\n",
    "  syncHeroPlayback();\n"
  ],
  [
    "// A pending play request can settle after the hero or document becomes hidden.",
    "// A pending play request can settle after the document becomes hidden."
  ]
];

test('exact frozen bytes differ only by hero viewport removal, boot sync, and corrected comment', () => {
  assert.equal(sha(original), 'a3c3f3924d3c2023705328077d9297d7fa5291a8886c599398dedebe8f40423d');
  assert.equal(sha(candidate), '063358c160bcdbdf24c361f8d3350d890bb11b2fb8df0d129a4c30039e124e22');
  let expected = original.toString('utf8');
  for (const [before, after] of allowedChanges) {
    assert.equal(expected.split(before).length, 2, 'each allowlisted site occurs exactly once');
    expected = expected.replace(before, after);
  }
  assert.deepEqual(candidate, Buffer.from(expected, 'utf8'), 'no normalization or unapproved source changes');
  assert.doesNotMatch(script, /heroInViewport|heroWithinViewport|heroObserver|updateHeroVisibility|const hero =/);
  const panorama = source => source.slice(source.indexOf('  function initPanorama() {'), source.indexOf('  updatePanoramaLanguage = initPanorama();'));
  assert.equal(panorama(script), panorama(original.toString('utf8')), 'all panorama behavior is byte-identical');
});

function fixture(t, options = {}) {
  const dom = new JSDOM(`<html lang="hu" data-harmat-language="hu"><head><title>Native SEO</title></head><body><header id="masthead">Native menu</header>${html}<footer id="colophon">Native footer</footer><form id="h22-offer-form"><input name="contact" value="Unsent draft"><textarea name="message">Original draft</textarea><select name="source"><option value="Google" selected>Google</option></select><input name="privacy" type="checkbox" required></form><form id="appointment-form"><input type="date" value="2026-10-22"><input name="time" value="12-15"></form></body></html>`, {
    url: 'https://harmat22.hu/', runScripts: 'outside-only', pretendToBeVisual: true
  });
  const w = dom.window, d = w.document, root = d.getElementById('harmat-home-family');
  const hero = root.querySelector('.hhf-hero'), video = root.querySelector('.hhf-hero-video');
  const button = root.querySelector('.hhf-video-button'), consoleRoot = root.querySelector('[data-panorama-console]');
  const protectedNodes = [...d.querySelectorAll('header,footer,form')], protectedHTML = protectedNodes.map(n => n.outerHTML);
  const fields = () => [...d.querySelectorAll('form input,form textarea,form select')].map(n => [n.value, n.checked]);
  const protectedFields = fields(), effects = [], observers = [], viewers = [], media = [], writes = [];
  const mediaAttributes = [...video.attributes].map(a => [a.name, a.value]);
  const paused = new WeakMap(), motionListeners = [], pending = [];
  const motion = { matches: !!options.reduced, addEventListener(type, callback) { if (type === 'change') motionListeners.push(callback); } };
  let hidden = !!options.hidden, heroVisible = options.heroVisible !== false, near = false, heroReads = 0;
  w.matchMedia = () => motion;
  Object.defineProperty(d, 'hidden', { get: () => hidden });
  hero.getBoundingClientRect = () => {
    heroReads++;
    return { width: 1280, height: 720, top: heroVisible ? 76 : -800, bottom: heroVisible ? 796 : -80, left: 0, right: 1280 };
  };
  consoleRoot.getBoundingClientRect = () => ({ width: 900, height: 520, top: near ? 80 : 2000, bottom: near ? 600 : 2520 });
  Object.defineProperty(w.HTMLMediaElement.prototype, 'paused', { configurable: true, get() { return paused.get(this) !== false; } });
  const src = Object.getOwnPropertyDescriptor(w.HTMLMediaElement.prototype, 'src');
  const time = Object.getOwnPropertyDescriptor(w.HTMLMediaElement.prototype, 'currentTime');
  time.set.call(video, 17.25);
  Object.defineProperty(video, 'src', {
    get() { return src.get.call(this); },
    set(value) { writes.push(['src', value]); src.set.call(this, value); }
  });
  Object.defineProperty(video, 'currentTime', {
    get() { return time.get.call(this); },
    set(value) { writes.push(['time', value]); time.set.call(this, value); }
  });
  function start(element) {
    paused.set(element, false);
    element.dispatchEvent(new w.Event('play'));
    if (!element.paused) element.dispatchEvent(new w.Event('playing'));
  }
  w.HTMLMediaElement.prototype.play = function () {
    media.push(['play', this]);
    if (this === video && options.play === 'throw') throw Error('NotAllowedError');
    if (this === video && options.play === 'reject') return Promise.reject(Error('NotAllowedError'));
    if (this === video && options.play === 'pending') return new Promise(resolve => pending.push(() => { start(this); resolve(); }));
    start(this);
    return Promise.resolve();
  };
  w.HTMLMediaElement.prototype.pause = function () {
    media.push(['pause', this]); paused.set(this, true); this.dispatchEvent(new w.Event('pause'));
  };
  w.HTMLMediaElement.prototype.load = function () { media.push(['load', this]); };
  if (options.observers !== false) w.IntersectionObserver = class {
    constructor(callback, observerOptions) { this.callback = callback; this.options = observerOptions; observers.push(this); }
    observe(target) { this.target = target; }
    disconnect() { this.disconnected = true; }
  };
  w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
  w.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new w.Event('close')); };
  w.pannellum = { viewer(host, config) {
    assert.ok(root.contains(host));
    const handlers = {}, calls = [];
    const drag = d.createElement('div'); drag.className = 'pnlm-dragfix'; host.append(drag);
    const api = {
      on(name, callback) { handlers[name] = callback; return api; },
      getConfig: () => config,
      resize: () => calls.push('resize'), lookAt: (...args) => calls.push(['lookAt', ...args]),
      startAutoRotate: () => calls.push('start'), stopAutoRotate: () => calls.push('stop'),
      stopMovement: () => calls.push('stopMovement'), destroy: () => calls.push('destroy')
    };
    viewers.push({ host, config, handlers, calls });
    return api;
  } };
  for (const key of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource']) w[key] = () => { effects.push(key); throw Error('NETWORK_FORBIDDEN'); };
  w.navigator.sendBeacon = () => { effects.push('beacon'); throw Error('NO_BEACON'); };
  w.Storage.prototype.setItem = () => { effects.push('storage'); throw Error('NO_STORAGE'); };
  w.HTMLFormElement.prototype.submit = w.HTMLFormElement.prototype.requestSubmit = () => { effects.push('submit'); throw Error('NO_SUBMIT'); };
  for (const form of d.querySelectorAll('form')) for (const event of ['submit', 'input', 'change', 'click']) form.addEventListener(event, () => effects.push(event));
  t.after(() => {
    try {
      assert.deepEqual(protectedNodes.map(n => n.outerHTML), protectedHTML, 'native menu/footer/forms unchanged');
      assert.deepEqual(fields(), protectedFields, 'unsent quote/appointment drafts unchanged');
      assert.deepEqual(effects, [], 'no network/storage/form operations');
      assert.equal(d.title, 'Native SEO');
      assert.equal(d.documentElement.lang, 'hu');
      for (const [name, value] of mediaAttributes) assert.equal(video.getAttribute(name), value, 'original media/layout attribute: ' + name);
    } finally { w.close(); }
  });
  function emit(target, value) {
    for (const o of observers) if (!o.disconnected && o.target === target) {
      o.callback([{ target, isIntersecting: value, intersectionRatio: value ? 1 : 0 }]);
    }
  }
  return {
    w, d, root, hero, video, button, consoleRoot, observers, viewers, media, writes,
    heroCalls: () => media.filter(([, element]) => element === video).map(([name]) => name),
    heroReads: () => heroReads,
    boot() { w.eval(script); },
    scroll(value) { heroVisible = value; emit(hero, value); w.dispatchEvent(new w.Event('scroll')); w.dispatchEvent(new w.Event('resize')); },
    panorama(value) { near = value; emit(consoleRoot, value); if (options.observers === false) w.dispatchEvent(new w.Event('scroll')); },
    setHidden(value) { hidden = value; d.dispatchEvent(new w.Event('visibilitychange')); },
    setReduced(value) { motion.matches = value; motionListeners.forEach(callback => callback()); },
    finishPlay() { pending.splice(0).forEach(finish => finish()); },
    tab(name) { root.querySelector('[data-panorama-tab="' + name + '"]').click(); },
    async language(value) { d.documentElement.setAttribute('data-harmat-language', value); await tick(); }
  };
}

for (const observers of [true, false]) {
  const mode = observers ? 'IO' : 'no IO fallback';
  test(mode + ': autoplay starts even initially out of view; scroll/IO never pause, reset, or reload hero', t => {
    const f = fixture(t, { observers, heroVisible: false }); f.boot();
    assert.equal(f.video.paused, false);
    assert.equal(f.video.muted, true);
    assert.equal(f.video.loop, true);
    assert.equal(f.video.src, f.video.dataset.src);
    assert.equal(f.video.dataset.revealed, 'true');
    assert.equal(f.video.currentTime, 17.25);
    assert.deepEqual(f.writes, [['src', f.video.dataset.src]]);
    assert.deepEqual(f.heroCalls(), ['play']);
    const before = f.heroCalls();
    for (const visible of [false, true, false, false, true]) {
      f.scroll(visible); f.panorama(visible);
      assert.equal(f.video.paused, false);
      assert.equal(f.video.currentTime, 17.25);
      assert.equal(f.video.src, f.video.dataset.src);
    }
    assert.deepEqual(f.heroCalls(), before);
    assert.deepEqual(f.writes, [['src', f.video.dataset.src]], 'one source attachment, no seek');
    assert.equal(f.heroReads(), 0, 'hero geometry is never consulted');
    assert.ok(f.observers.every(o => o.target === f.consoleRoot), 'only panorama observers remain');
    if (observers) {
      assert.equal(f.observers.length, 2);
      assert.equal(f.observers[0].options.rootMargin, '420px 0px');
      assert.equal(f.observers[0].disconnected, true);
    }
  });

  test(mode + ': explicit pause survives scrolling and hidden/visible; manual resume keeps timeline', t => {
    const f = fixture(t, { observers }); f.boot(); f.button.click();
    assert.equal(f.video.paused, true);
    assert.equal(f.button.getAttribute('aria-label'), f.root.querySelector('#hhf-video-tooltip').textContent);
    assert.equal(f.root.querySelector('.hhf-video-play-icon').hasAttribute('hidden'), false);
    const before = f.heroCalls();
    f.scroll(false); f.panorama(true); f.setHidden(true); f.scroll(true); f.setHidden(false); f.scroll(false);
    assert.equal(f.video.paused, true);
    assert.deepEqual(f.heroCalls(), before, 'no automatic retry after manual pause');
    f.button.click();
    assert.equal(f.video.paused, false, 'manual play works while still offscreen');
    assert.equal(f.video.currentTime, 17.25);
    assert.deepEqual(f.writes, [['src', f.video.dataset.src]]);
    assert.deepEqual(f.heroCalls(), ['play', 'pause', 'play']);
  });

  test(mode + ': background pauses and visible resumes offscreen without source/time changes', t => {
    const f = fixture(t, { observers }); f.boot(); f.scroll(false); f.setHidden(true);
    assert.equal(f.video.paused, true);
    f.scroll(false); f.panorama(true);
    assert.equal(f.video.paused, true);
    f.setHidden(false);
    assert.equal(f.video.paused, false);
    assert.equal(f.video.currentTime, 17.25);
    assert.deepEqual(f.writes, [['src', f.video.dataset.src]]);
    assert.deepEqual(f.heroCalls(), ['play', 'pause', 'play']);
  });

  test(mode + ': initial hidden document defers media attachment until visible', t => {
    const f = fixture(t, { observers, hidden: true, heroVisible: false }); f.boot();
    assert.equal(f.video.paused, true);
    assert.equal(f.video.hasAttribute('src'), false);
    f.scroll(false); assert.deepEqual(f.heroCalls(), []);
    f.setHidden(false); assert.equal(f.video.paused, false);
    assert.deepEqual(f.heroCalls(), ['play']);
  });
}

test('reduced motion blocks autoplay and visibility retries; explicit manual play remains allowed offscreen', t => {
  const f = fixture(t, { reduced: true, heroVisible: false }); f.boot();
  f.scroll(false); f.panorama(true); f.setHidden(true); f.setHidden(false);
  assert.equal(f.video.hasAttribute('src'), false);
  assert.deepEqual(f.heroCalls(), []);
  f.button.click(); assert.equal(f.video.paused, false);
  f.scroll(false); f.panorama(false); assert.equal(f.video.paused, false);
  f.setHidden(true); assert.equal(f.video.paused, true);
  f.setHidden(false);
  assert.equal(f.video.paused, true, 'original reduced-motion manual override ends on background pause');
  f.button.click(); assert.equal(f.video.paused, false);
  f.button.click(); f.scroll(true); f.setHidden(true); f.setHidden(false);
  assert.equal(f.video.paused, true);
  assert.deepEqual(f.writes, [['src', f.video.dataset.src]]);
});

test('live reduced-motion changes stop autoplay and do not undo explicit pause', t => {
  const f = fixture(t); f.boot();
  f.setReduced(true); assert.equal(f.video.paused, true);
  f.scroll(false); assert.equal(f.video.paused, true);
  f.button.click(); assert.equal(f.video.paused, false);
  f.setReduced(true); assert.equal(f.video.paused, true, 'change resets the original manual motion override');
  f.setReduced(false); assert.equal(f.video.paused, false);
  f.button.click(); f.setReduced(true); f.setReduced(false); f.setHidden(true); f.setHidden(false);
  assert.equal(f.video.paused, true);
});

for (const play of ['throw', 'reject']) {
  test('play ' + play + ' keeps poster/manual retry; scrolling does not retry or reload', async t => {
    const f = fixture(t, { play }); f.boot(); await tick();
    assert.equal(f.video.paused, true);
    assert.equal(f.video.dataset.revealed, undefined);
    assert.equal(f.button.disabled, false);
    assert.equal(f.root.querySelector('.hhf-video-play-icon').hasAttribute('hidden'), false);
    f.scroll(false); f.panorama(true); f.scroll(true); await tick();
    assert.deepEqual(f.heroCalls(), ['play']);
    f.button.click(); await tick();
    assert.deepEqual(f.heroCalls(), ['play', 'play']);
    assert.deepEqual(f.writes, [['src', f.video.dataset.src]]);
    assert.equal(f.video.currentTime, 17.25);
  });
}

test('late playing after backgrounding is stopped; a later visible event can resume', async t => {
  const f = fixture(t, { play: 'pending' }); f.boot(); f.scroll(false); f.setHidden(true);
  f.finishPlay(); await tick();
  assert.equal(f.video.paused, true);
  assert.equal(f.video.dataset.revealed, undefined);
  assert.deepEqual(f.heroCalls(), ['play', 'pause']);
  f.setHidden(false); f.finishPlay(); await tick();
  assert.equal(f.video.paused, false);
  assert.equal(f.video.dataset.revealed, 'true');
  assert.deepEqual(f.writes, [['src', f.video.dataset.src]]);
});

test('media error disables hero, restores poster, localizes fallback, and never retries', async t => {
  const f = fixture(t); f.boot();
  f.video.pause(); f.video.dispatchEvent(new f.w.Event('error'));
  assert.equal(f.button.disabled, true);
  assert.equal(f.video.dataset.revealed, undefined);
  const status = f.root.querySelector('#hhf-video-status');
  assert.ok(status.textContent.length > 0);
  assert.equal(status.textContent, f.button.getAttribute('aria-label'));
  const before = f.heroCalls();
  f.scroll(false); f.panorama(true); f.setHidden(true); f.setHidden(false); f.setReduced(true); f.setReduced(false); f.button.click();
  assert.deepEqual(f.heroCalls(), before);
  await f.language('zh-Hans'); assert.equal(status.textContent, f.button.getAttribute('aria-label'));
  await f.language('hu'); assert.equal(status.textContent, f.button.getAttribute('aria-label'));
  assert.deepEqual(f.writes, [['src', f.video.dataset.src]]);
});

for (const observers of [true, false]) {
  test((observers ? 'IO' : 'no IO') + ': panorama lazy load, rotation, panel clips and offscreen/background gating stay separate', async t => {
    const f = fixture(t, { observers }); f.boot();
    assert.equal(f.viewers.length, 0);
    const clips = [...f.root.querySelectorAll('.hhf-panorama-video-card video')];
    assert.ok(clips.every(clip => !clip.hasAttribute('src')));
    f.scroll(false); assert.equal(f.viewers.length, 0);
    f.panorama(true); assert.equal(f.viewers.length, 1);
    const v = f.viewers[0]; v.handlers.load();
    assert.equal(v.config.type, 'cubemap'); assert.equal(v.config.cubeMap.length, 6);
    assert.equal(v.config.pitch, -27); assert.equal(v.config.hfov, 92);
    assert.equal(f.root.querySelector('[data-panorama-rotate]').getAttribute('aria-pressed'), 'true');
    f.panorama(false);
    assert.equal(f.root.querySelector('[data-panorama-rotate]').getAttribute('aria-pressed'), 'false');
    assert.equal(f.video.paused, false);
    f.panorama(true);
    assert.equal(f.viewers.length, 1);
    f.tab('video');
    assert.ok(clips.every(clip => clip.src === clip.dataset.src));
    await clips[0].play(); assert.equal(clips[0].playbackRate, 0.25);
    await clips[1].play(); assert.equal(clips[0].paused, true); assert.equal(clips[1].paused, false);
    assert.equal(clips[1].playbackRate, 1);
    f.panorama(false); assert.ok(clips.every(clip => clip.paused));
    await clips[0].play(); assert.equal(clips[0].paused, true, 'hidden panorama clip cannot play');
    assert.equal(f.video.paused, false, 'hero keeps playing when panorama clips stop');
    f.panorama(true); await clips[0].play();
    f.setHidden(true); assert.ok(clips.every(clip => clip.paused)); assert.equal(f.video.paused, true);
    f.setHidden(false); assert.ok(clips.every(clip => clip.paused)); assert.equal(f.video.paused, false);
    f.tab('plans'); assert.ok(clips.every(clip => clip.paused));
    f.tab('panorama');
    f.root.querySelector('[data-panorama-reset]').click();
    assert.ok(v.calls.some(call => Array.isArray(call) && call[0] === 'lookAt'));
    v.host.dispatchEvent(new f.w.MouseEvent('mousedown', { bubbles: true }));
    assert.equal(f.root.querySelector('[data-panorama-rotate]').getAttribute('aria-pressed'), 'false');
    f.panorama(false); f.panorama(true);
    assert.equal(f.root.querySelector('[data-panorama-rotate]').getAttribute('aria-pressed'), 'false', 'manual rotation pause stays honored');
    assert.equal(f.video.currentTime, 17.25);
    assert.deepEqual(f.writes, [['src', f.video.dataset.src]]);
  });
}

test('panorama vendor failure and clip error keep owned fallback without stopping hero', async t => {
  const f = fixture(t); delete f.w.pannellum; f.boot(); f.panorama(true);
  assert.equal(f.root.querySelector('.hhf-panorama-viewer').hidden, true);
  assert.equal(f.root.querySelector('.hhf-panorama-fallback').hidden, false);
  assert.equal(f.video.paused, false);
  f.tab('video');
  const clip = f.root.querySelector('.hhf-panorama-video-card video');
  clip.dispatchEvent(new f.w.Event('error'));
  const error = clip.closest('article').querySelector('.hhf-panorama-video-error');
  assert.equal(error.hidden, false);
  const hu = error.textContent;
  await f.language('zh-Hans'); assert.notEqual(error.textContent, hu);
  await f.language('hu'); assert.equal(error.textContent, hu);
  assert.equal(f.video.paused, false);
});

test('duplicate boot and absent root are no-ops, with no second hero play or panorama viewer', t => {
  const f = fixture(t); f.boot(); f.boot();
  assert.deepEqual(f.heroCalls(), ['play']);
  assert.equal(f.observers.length, 2);
  f.panorama(true); assert.equal(f.viewers.length, 1);
  f.root.remove(); assert.doesNotThrow(() => f.boot());
  assert.deepEqual(f.heroCalls(), ['play']);
});
