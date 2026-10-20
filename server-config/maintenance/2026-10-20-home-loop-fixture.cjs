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
const candidate = fs.readFileSync(path.join(DIR, 'home-family-loop.js'));
const script = candidate.toString('utf8');
// Reuse the real body renderer and approved public rows, not copied HTML or runtime code.
const html = execFileSync(PHP, [path.join(__dirname, '2026-10-20-test-home-six.php'), '--render'], { encoding: 'utf8' });
const tick = () => new Promise(resolve => setImmediate(resolve));
const sha = value => crypto.createHash('sha256').update(value).digest('hex');
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
  w.matchMedia = query => query.includes('reduced-motion') ? motion : { matches:false, addEventListener() {} };
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

module.exports = { fixture, tick, sha, script, candidate, original, ROOT, PHP, DIR };
