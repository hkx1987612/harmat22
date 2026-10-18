'use strict';
// Offline PHP + jsdom/CSSOM only. No browser geometry, decoding or load-time claims.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const { JSDOM, ResourceLoader, VirtualConsole } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HHF_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const modulePath = path.join(ROOT, 'wp-mu-plugins/zz-harmat-construction-updates.php');
const homeDirectory = path.join(ROOT, 'wp-mu-plugins/harmat-home-family');
const rendered = JSON.parse(execFileSync(PHP, [
  path.join(__dirname, '2026-10-18-render-home-construction-policy.php'), modulePath,
], { encoding: 'utf8', maxBuffer: 2 * 1024 * 1024 }));
const MEDIA = 'https://harmat22.hu/wp-content/uploads/2026/10/construction-updates/';
const LATEST = MEDIA + '2026-10-13-compilation-hu-subtitled.mp4';
const IDS = ['2026-10-13', ...['01', '02', '03', '04'].map(n => '2026-10-12-site-' + n)];
const tick = () => new Promise(resolve => setImmediate(resolve));
const sha = bytes => crypto.createHash('sha256').update(bytes).digest('hex');

function documentFixture(t, markup = rendered.home, url = 'https://harmat22.hu/', close = true) {
  const requests = [], errors = [];
  const pins = JSON.parse(fs.readFileSync(path.join(homeDirectory, 'manifest.json'), 'utf8'));
  const stylesheets = new Set(['pannellum.css', 'home-family.css'].map(name =>
    'https://harmat22.hu/wp-content/mu-plugins/harmat-home-family/' + name + '?ver=' + pins[name].slice(0, 16)));
  class OfflineLoader extends ResourceLoader {
    // No call to super.fetch: even the existing pinned stylesheet links stay offline.
    fetch(url) { requests.push(url); return null; }
  }
  const console = new VirtualConsole();
  console.on('jsdomError', error => errors.push(error.message));
  const dom = new JSDOM(markup, {
    url, runScripts: 'outside-only', pretendToBeVisual: true,
    resources: new OfflineLoader(), virtualConsole: console,
  });
  t.after(() => {
    try {
      assert.deepEqual(requests.filter(url => !stylesheets.has(url)), [], 'no unexpected subresource requests; pinned CSS blocked offline');
      assert.deepEqual(errors, [], 'no swallowed runtime/jsdom errors');
    } finally { if (close) dom.window.close(); }
  });
  return dom;
}

function setup(t, { markup = rendered.home, url, marker = 'hu', homepage = false, rejectPlay = false } = {}) {
  const dom = documentFixture(t, markup, url, false), w = dom.window, d = w.document;
  d.documentElement.setAttribute('data-harmat-language', marker);
  const native = [...d.querySelectorAll('header,footer,#h22-offer-modal,#native-appointment-fixture,#hm-cookie-settings-button,#harmat-internal-language')];
  const snapshots = native.map(node => node.outerHTML);
  const forms = [...d.querySelectorAll('form')];
  const formState = () => forms.map(form => [...form.elements].map(node => ({
    value: node.value, checked: node.checked, required: node.required,
    selectedIndex: node.selectedIndex,
  })));
  // Keep live properties different from serialized defaults to catch accidental resets.
  d.querySelector('#h22-offer-form textarea,#h22-offer-form input').value = 'Edited, unsent quote';
  const appointment = d.getElementById('native-appointment-form');
  if (appointment) appointment.querySelector('input').value = 'Edited, unsent appointment';
  const values = formState(), nativeEvents = [], forbiddenCalls = [], media = [];
  const deny = name => function () { forbiddenCalls.push(name); throw new Error('FORBIDDEN ' + name); };
  for (const key of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource', 'Worker', 'SharedWorker', 'open']) w[key] = deny(key);
  w.navigator.sendBeacon = deny('sendBeacon');
  for (const key of ['localStorage', 'sessionStorage', 'indexedDB', 'caches']) {
    Object.defineProperty(w, key, { configurable: true, get: deny(key) });
  }
  Object.defineProperty(d, 'cookie', { configurable: true, get: deny('cookie-read'), set: deny('cookie-write') });
  w.HTMLFormElement.prototype.submit = deny('submit');
  w.HTMLFormElement.prototype.requestSubmit = deny('requestSubmit');
  for (const form of forms) for (const event of ['submit', 'input', 'change', 'click', 'reset']) {
    form.addEventListener(event, () => nativeEvents.push(event));
  }
  let hidden = false;
  Object.defineProperty(d, 'hidden', { configurable: true, get: () => hidden });
  const paused = new WeakMap();
  Object.defineProperty(w.HTMLMediaElement.prototype, 'paused', { configurable: true, get() { return paused.get(this) !== false; } });
  w.HTMLMediaElement.prototype.load = deny('media-load');
  w.HTMLMediaElement.prototype.play = function () {
    media.push(['play', this]);
    if (rejectPlay) return Promise.reject(new Error('Synthetic blocked play'));
    paused.set(this, false); this.dispatchEvent(new w.Event('play')); return Promise.resolve();
  };
  w.HTMLMediaElement.prototype.pause = function () { media.push(['pause', this]); paused.set(this, true); };
  w.matchMedia = () => ({ matches: true, addEventListener() {} });
  // Observers never report panorama proximity: marketing assets are a separate scope.
  w.IntersectionObserver = class { observe() {} disconnect() {} };
  t.after(() => {
    try {
      assert.deepEqual(native.map(node => node.outerHTML), snapshots, 'native shell and form markup unchanged');
      assert.deepEqual(forms, [...d.querySelectorAll('form')], 'same form node identities');
      assert.deepEqual(formState(), values, 'edited drafts, dates, times and privacy preserved');
      assert.deepEqual(nativeEvents, [], 'no native form events');
      assert.deepEqual(forbiddenCalls, [], 'no network/storage/submit API calls');
    } finally { w.close(); }
  });
  const runtime = d.getElementById('harmat-construction-updates-runtime').textContent;
  if (homepage) w.eval(fs.readFileSync(path.join(homeDirectory, 'home-family.js'), 'utf8'));
  w.eval(runtime);
  d.dispatchEvent(new w.Event('DOMContentLoaded'));
  return { w, d, media, runtime,
    setHidden(value) { hidden = value; d.dispatchEvent(new w.Event('visibilitychange')); },
    async language(value) { d.documentElement.setAttribute('data-harmat-language', value); await tick(); },
  };
}

function assertDeferredConstruction(d, expected) {
  const players = [...d.querySelectorAll('[data-hcu-player]')];
  assert.equal(players.length, expected);
  for (const player of players) {
    assert.equal(player.querySelectorAll('video,source,iframe').length, 0);
    const button = player.querySelector('[data-hcu-play]');
    assert.equal(button.tagName, 'BUTTON'); assert.equal(button.type, 'button');
    assert.ok(button.getAttribute('aria-label'));
  }
  for (const node of d.querySelectorAll('[src],[srcset],[poster],source,link')) {
    for (const attr of ['src', 'srcset', 'poster', 'href']) {
      if (node.tagName !== 'LINK' && attr === 'href') continue;
      assert.ok(!(node.getAttribute(attr) || '').includes(MEDIA) || !/\.mp4(?:[?#]|$)/.test(node.getAttribute(attr)), 'no construction MP4 fetch attribute or preload link');
    }
  }
}

test('real controller renders exactly one October 13 compilation, never October 12 home players', t => {
  assert.equal(rendered.fixture.status, 'PASS'); assert.ok(rendered.fixture.checks >= 90);
  const d = documentFixture(t).window.document;
  assert.equal(d.querySelectorAll('main#main > #harmat-home-family').length, 1);
  assert.equal(d.querySelectorAll('[data-hcu-home="1"]').length, 1);
  assert.equal(d.querySelectorAll('[data-hcu-journal],#harmat-construction-updates').length, 0);
  assert.equal(d.querySelectorAll('[data-hcu-player]').length, 1);
  assert.equal(d.querySelector('[data-hcu-player]').dataset.hcuVideo, LATEST);
  assert.equal(d.querySelector('[data-hcu-home] time').dateTime, '2026-10-13');
  assert.doesNotMatch(rendered.home, /latest-site-0[1-4]|2026-10-12-site-|data-hcu-date="2026-10-12"/);
  assert.equal(d.querySelectorAll('#harmat-construction-updates-runtime,#harmat-construction-updates-css').length, 2);
  assert.equal(d.querySelectorAll('h1').length, 1);
});

test('journal retains all five entries newest first and home deeplink resolves to the same compilation', t => {
  const home = documentFixture(t).window.document;
  const journal = documentFixture(t, rendered.journal, 'https://harmat22.hu/epitesi-naplo/').window.document;
  const entries = [...journal.querySelectorAll('[data-hcu-journal] .hcu-entry')];
  assert.deepEqual(entries.map(node => node.id), IDS.map(id => 'construction-' + id));
  assert.deepEqual(entries.map(node => node.dataset.hcuDate), ['2026-10-13', ...Array(4).fill('2026-10-12')]);
  assert.equal(journal.querySelectorAll('[data-hcu-player]').length, 5);
  const link = new URL(home.querySelector('[data-hcu-home] a[href*="/epitesi-naplo/#"]').href);
  assert.equal(link.origin + link.pathname, 'https://harmat22.hu/epitesi-naplo/');
  const target = journal.getElementById(link.hash.slice(1));
  assert.equal(target, entries[0]);
  assert.equal(target.querySelector('[data-hcu-player]').dataset.hcuVideo, LATEST);
  assert.equal(journal.querySelectorAll('.harmat-construction-september,.harmat-build-log-list').length, 2);
});

test('only the pinned journal fragment changes; marketing/3D and native form bytes survive', t => {
  const before = documentFixture(t, rendered.bodyBefore).window.document;
  const teaser = documentFixture(t, rendered.bodyTeaser).window.document;
  const home = documentFixture(t).window.document;
  const otherSections = d => [...d.querySelector('#harmat-home-family').children]
    .filter(node => !node.classList.contains('hhf-journal')).map(node => node.outerHTML);
  assert.deepEqual(otherSections(teaser), otherSections(before), 'non-journal main presentation exact');
  assert.ok(rendered.homeInput.includes(rendered.nativeForms));
  assert.ok(rendered.home.includes(rendered.nativeForms), 'byte-exact native form sentinels outside main');
  assert.equal(home.querySelectorAll('#harmat-home-family form').length, 0);
  assert.equal(home.querySelectorAll('.hhf-panorama-video-card video').length, 2);
  assert.equal(home.querySelectorAll('[data-panorama-tab]').length, 6);
  for (const [index, video] of [...home.querySelectorAll('.hhf-panorama-video-card video')].entries()) {
    assert.equal(video.outerHTML, before.querySelectorAll('.hhf-panorama-video-card video')[index].outerHTML);
    assert.ok(!video.dataset.src.startsWith(MEDIA), 'showcase is not construction media');
  }
  assert.equal(home.getElementById('hhf-hero-video').outerHTML, before.getElementById('hhf-hero-video').outerHTML);
});

test('all six construction posters have lazy decoding and explicit 16:9 dimensions', t => {
  let count = 0;
  for (const markup of [rendered.home, rendered.journal]) {
    const d = documentFixture(t, markup).window.document;
    for (const poster of d.querySelectorAll('[data-hcu-player] img')) {
      count++;
      assert.equal(poster.getAttribute('loading'), 'lazy');
      assert.equal(poster.getAttribute('decoding'), 'async');
      assert.equal(poster.width, 1280); assert.equal(poster.height, 720);
      assert.equal(poster.width / poster.height, 16 / 9);
      assert.ok(poster.src.startsWith(MEDIA)); assert.match(poster.src, /\.jpg$/);
    }
  }
  assert.equal(count, 6);
});

test('initial home runtime, language changes and idle events never activate construction MP4', async t => {
  const { d, w, media, language, setHidden } = setup(t, { homepage: true });
  assertDeferredConstruction(d, 1);
  await language('zh-Hans'); await language('hu');
  w.dispatchEvent(new w.Event('resize')); w.dispatchEvent(new w.Event('scroll'));
  setHidden(true); setHidden(false); await tick();
  assertDeferredConstruction(d, 1);
  assert.equal(media.filter(([kind, node]) => kind === 'play' && node.closest('[data-hcu-player]')).length, 0);
});

test('home click alone creates exactly one muted inline current player with preload none', async t => {
  const { d, w, media, runtime, language, setHidden } = setup(t, { homepage: true });
  const player = d.querySelector('[data-hcu-player]');
  player.querySelector('[data-hcu-play]').click();
  const video = player.querySelector('video');
  assert.equal(video.src, LATEST); assert.equal(video.preload, 'none');
  assert.ok(video.controls && video.playsInline && video.muted && video.defaultMuted);
  assert.equal(video.hasAttribute('autoplay'), false);
  assert.equal(d.activeElement, video);
  await language('zh-Hans'); assert.match(video.getAttribute('aria-label'), /2026\u5e7410\u670813\u65e5/);
  await language('hu'); assert.equal(video.getAttribute('aria-label'), player.dataset.hcuTitleHu);
  w.eval(runtime); d.dispatchEvent(new w.Event('DOMContentLoaded'));
  assert.equal(d.querySelectorAll('[data-hcu-player] video').length, 1);
  assert.equal(media.filter(([kind, node]) => kind === 'play' && node === video).length, 1);
  setHidden(true); assert.equal(video.paused, true);
});

test('journal runtime keeps four October 12 videos deferred until their own clicks', t => {
  const { d, media } = setup(t, { markup: rendered.journal, url: 'https://harmat22.hu/epitesi-naplo/' });
  assertDeferredConstruction(d, 5); assert.deepEqual(media, []);
  const players = [...d.querySelectorAll('[data-hcu-player]')];
  for (const [index, player] of players.entries()) {
    player.querySelector('[data-hcu-play]').click();
    assert.equal(player.querySelector('video').src, player.dataset.hcuVideo);
    assert.equal(player.querySelector('video').preload, 'none');
    for (const remaining of players.slice(index + 1)) assert.equal(remaining.querySelector('video,source'), null);
    assert.equal(d.querySelectorAll('[data-hcu-player] video').length, index + 1);
    assert.equal([...d.querySelectorAll('[data-hcu-player] video')].filter(video => !video.paused).length, 1);
  }
});

test('initial Chinese home with real home runtime keeps one dated teaser and unchanged deeplink', t => {
  const { d } = setup(t, { marker: 'zh-Hans', homepage: true });
  const root = d.querySelector('[data-hcu-home]');
  assert.equal(root.lang, 'zh-Hans');
  assert.match(root.querySelector('h2').textContent, /2026\u5e7410\u670813\u65e5/);
  assert.equal(root.querySelector('a[href*="/epitesi-naplo/#"]').hash, '#construction-2026-10-13');
  assertDeferredConstruction(d, 1);
});

test('rejected gesture play and decode error retain native controls and a direct local fallback', async t => {
  const { d, w, language } = setup(t, { rejectPlay: true });
  const player = d.querySelector('[data-hcu-player]'); player.querySelector('button').click(); await tick();
  const status = player.nextElementSibling, video = player.querySelector('video');
  assert.equal(status.dataset.hcuMessage, 'paused'); assert.equal(video.controls, true);
  video.dispatchEvent(new w.Event('error')); assert.equal(status.dataset.hcuMessage, 'error');
  await language('zh-Hans'); assert.ok(status.textContent.length > 0);
  const direct = player.parentNode.querySelector('.hcu-direct');
  assert.equal(direct.href, LATEST); assert.equal(direct.target, '_blank');
  assert.equal(direct.rel, 'noopener noreferrer');
});

test('construction mobile CSS has bounded tracks and stable uncropped media (CSSOM, not layout QA)', t => {
  const d = documentFixture(t).window.document;
  const sheet = d.getElementById('harmat-construction-updates-css').sheet;
  const rules = [];
  function collect(list, media = '') {
    for (const rule of list) {
      if (rule.cssRules) collect(rule.cssRules, rule.conditionText);
      else rules.push({ rule, media });
    }
  }
  collect(sheet.cssRules);
  function declared(selector, property, width) {
    let value = '';
    for (const { rule, media } of rules) {
      const max = media.match(/^\(max-width:\s*(\d+)px\)$/);
      if (media && (!max || width > Number(max[1]))) continue;
      if (rule.selectorText.split(',').map(value => value.trim()).includes(selector)) value = rule.style.getPropertyValue(property) || value;
    }
    return value;
  }
  const homeScope = 'body.home.page-id-164 #harmat-home-family [data-hcu-home]';
  for (const width of [320, 390, 620, 767, 900]) {
    assert.equal(declared(homeScope + ' .hhf-journal-grid', 'grid-template-columns', width), '1fr');
    assert.equal(declared('[data-hcu-root] .hcu-player', 'aspect-ratio', width), '16/9');
    assert.equal(declared('[data-hcu-root] .hcu-player', 'width', width), '100%');
    assert.equal(declared('[data-hcu-root] .hcu-player video', 'object-fit', width), 'contain');
    assert.equal(declared('[data-hcu-root]', 'min-width', width), '0');
    assert.equal(declared('[data-hcu-root]', 'overflow-wrap', width), 'anywhere');
  }
  assert.equal(declared(homeScope + ' .hhf-journal-grid', 'grid-template-columns', 1280), 'minmax(0,1.25fr) minmax(0,1fr)');
  assert.equal(declared(homeScope + ' h2', 'font-size', 320), '26px');
  assert.equal(declared('[data-hcu-root] .hcu-play-icon', 'width', 320), '48px');
  for (const { rule } of rules) {
    for (const selector of rule.selectorText.split(',')) assert.match(selector.trim(), /^(?:\[data-hcu-root\]|#harmat-construction-updates|body\.home\.page-id-164 #harmat-home-family \[data-hcu-home\])/);
    assert.doesNotMatch(rule.style.cssText, /url\s*\(|@import/i);
  }
});

test('existing homepage body/manifest and every asset still match immutable production pins', () => {
  const manifestBytes = fs.readFileSync(path.join(homeDirectory, 'manifest.json'));
  assert.equal(sha(manifestBytes), '21e6ebec9bf0bfcbc273c42b5dc33f07ce96547fa04ccdd0b53faf780d3587b3');
  const pins = JSON.parse(manifestBytes);
  assert.equal(pins['body.php'], 'de83c8ef7c72bdd4e88a8939afd0a823ef3fc5defd3fd66ab0b0d931af17d7ea');
  for (const [name, pin] of Object.entries(pins)) assert.equal(sha(fs.readFileSync(path.join(homeDirectory, name))), pin, name);
});
