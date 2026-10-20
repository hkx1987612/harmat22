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
const r1Script = fs.readFileSync(path.join(DIR, 'home-family-six.js'), 'utf8');
const candidate = fs.readFileSync(path.join(DIR, 'home-family-six-r2.js'));
const script = candidate.toString('utf8');
// Reuse the real body renderer and approved public rows, not copied HTML or runtime code.
const html = execFileSync(PHP, [path.join(__dirname, '2026-10-20-test-home-six.php'), '--render'], { encoding: 'utf8' });
const tick = () => new Promise(resolve => setImmediate(resolve));
const sha = value => crypto.createHash('sha256').update(value).digest('hex');
const FROZEN = {
  "baseline": "29c906b",
  "files": {
    "wp-mu-plugins/harmat-home-family/body-six.php": {
      "bytes": 42595,
      "sha256": "0585ca56e2afa2a558932d9ee9ad85868641a199683d19caddeafd9a010d61d2"
    },
    "wp-mu-plugins/harmat-home-family/home-family-six.js": {
      "bytes": 34108,
      "sha256": "a2222a2e7cd882a128a146c1c35f6c316c63b3558073d5fcbe73d4dd59abca05"
    },
    "wp-mu-plugins/harmat-home-family/manifest-six.json": {
      "bytes": 794,
      "sha256": "3b6c8c5248dab6e6eb78d15b3f8cca5203498095980a9f3875fb32a2caafefc1"
    },
    "wp-mu-plugins/zz-harmat-home-family.php": {
      "bytes": 26813,
      "sha256": "2c0fdc06a013d9b74f82f24dd14c3ed71d92f0fa828809103558c55d76873f85"
    }
  }
};
const BODY_CHANGES = [
  [
    "'A1-1-L5' => array('a1Floor1', 'A1 épület · 1. emelet'));",
    "'A1-1-L5' => array('a1Floor1', 'A1 épület · 1. emelet'),\n        'A3-F-L3' => array('a3GroundFloor', 'A3 épület · Földszint'),\n        'A4-F-L1' => array('a4GroundFloor', 'A4 épület · Földszint'),\n        'A3-2-L2' => array('a3Floor2', 'A3 épület · 2. emelet'));"
  ],
  [
    "'2' => array('twoRooms', '2 szoba'), '4'",
    "'2' => array('twoRooms', '2 szoba'), '3' => array('threeRooms', '3 szoba'), '4'"
  ],
  [
    "<div class=\"hhf-apartment-grid\">__HHF_UNITS_HTML__</div>",
    "<div class=\"hhf-apartment-grid\" id=\"hhf-apartment-grid\">__HHF_UNITS_HTML__</div>\n        <button class=\"hhf-button hhf-apartment-toggle\" type=\"button\" aria-controls=\"hhf-apartment-grid\" aria-expanded=\"false\" hidden>További lakások</button>"
  ]
];
const SCRIPT_CHANGES = [
  [
    "    a2Floor4: 'A2 栋 · 4 层',",
    "    a2Floor4: 'A2 栋 · 4 层',\n    a3GroundFloor: 'A3 栋 · 底层',\n    a4GroundFloor: 'A4 栋 · 底层',\n    a3Floor2: 'A3 栋 · 2 层',"
  ],
  [
    "    twoRooms: '2 个房间',",
    "    twoRooms: '2 个房间',\n    threeRooms: '3 个房间',"
  ],
  [
    "  let updatePanoramaLanguage = () => {};",
    "  let updatePanoramaLanguage = () => {};\n  let updateApartmentToggle = () => {};"
  ],
  [
    "    updatePanoramaLanguage();\n  }\n\n  let appliedNativeLanguage",
    "    updatePanoramaLanguage();\n    updateApartmentToggle();\n  }\n\n  function initApartmentToggle() {\n    const grid = ownedRoot.querySelector('#hhf-apartment-grid');\n    const button = ownedRoot.querySelector('.hhf-apartment-toggle');\n    if (!grid || !button || typeof window.matchMedia !== 'function') return () => {};\n    const extras = Array.from(grid.querySelectorAll('.hhf-apartment')).slice(3);\n    if (!extras.length) return () => {};\n    const mobile = window.matchMedia('(max-width: 620px)');\n    if (typeof mobile.addEventListener !== 'function') return () => {};\n    let expanded = false;\n\n    function render() {\n      const compact = mobile.matches;\n      if (!compact) expanded = false;\n      // Move focus before hiding a card or the desktop-only-hidden toggle.\n      if (compact && !expanded && extras.some(card => card.contains(document.activeElement))) {\n        button.hidden = false;\n        button.focus();\n      } else if (!compact && document.activeElement === button) {\n        const firstLink = grid.querySelector('a');\n        if (firstLink) firstLink.focus();\n      }\n      extras.forEach(card => { card.hidden = compact && !expanded; });\n      button.hidden = !compact;\n      button.setAttribute('aria-expanded', String(!compact || expanded));\n      button.textContent = language === 'zh'\n        ? expanded ? '收起更多公寓' : `再显示 ${extras.length} 套公寓`\n        : expanded ? 'Kevesebb lakás' : `További ${extras.length} lakás megtekintése`;\n    }\n\n    button.addEventListener('click', () => {\n      if (!mobile.matches) return;\n      expanded = !expanded;\n      render();\n    });\n    mobile.addEventListener('change', render);\n    render();\n    return render;\n  }\n\n  let appliedNativeLanguage"
  ],
  [
    "  updatePanoramaLanguage = initPanorama();\n  syncNativeLanguage();",
    "  updatePanoramaLanguage = initPanorama();\n  updateApartmentToggle = initApartmentToggle();\n  syncNativeLanguage();"
  ]
];
const CONTROLLER_CHANGES = [
  [
    " * Version: 1.0.13",
    " * Version: 1.0.14"
  ],
  [
    "$directory.'/manifest-scroll-play.json'",
    "$directory.'/manifest-six.json'"
  ],
  [
    "4916615afd43d01394b22b362e5e4878f9eca02ee086791cbb0081b52bbf7b81",
    "3b6c8c5248dab6e6eb78d15b3f8cca5203498095980a9f3875fb32a2caafefc1"
  ],
  [
    "$pins['home-family-scroll-play.js']))",
    "$pins['home-family-scroll-play.js'],$pins['body-six.php'],$pins['home-family-six.js']))"
  ],
  [
    "array('A1-1-L6'=>4317,'A2-4-L5'=>5212,'A1-1-L5'=>4311)",
    "array('A1-1-L6'=>4317,'A2-4-L5'=>5212,'A1-1-L5'=>4311,'A3-F-L3'=>5302,'A4-F-L1'=>5460,'A3-2-L2'=>5318)"
  ],
  [
    "include __DIR__.'/harmat-home-family/body.php'",
    "include __DIR__.'/harmat-home-family/body-six.php'"
  ],
  [
    "            . '</style>';",
    "            . 'body.home.page-id-164 #harmat-home-family .hhf-apartment-toggle{margin-top:24px;max-width:100%;white-space:normal}'\n            . 'body.home.page-id-164 #harmat-home-family .hhf-apartment[hidden],body.home.page-id-164 #harmat-home-family .hhf-apartment-toggle[hidden]{display:none!important}'\n            . '</style>';"
  ],
  [
    "array('pannellum.js','home-family-scroll-play.js')",
    "array('pannellum.js','home-family-six.js')"
  ]
];
function allowlisted(source, changes) {
  for (const [before, after] of changes) {
    assert.equal(source.split(before).length, 2, 'each explicit allowlist site occurs once: ' + before.slice(0, 70));
    source = source.replace(before, after);
  }
  return source;
}
const R2_FROZEN = {
  "wp-mu-plugins/harmat-home-family/home-family-six-r2.js": {
    "bytes": 34492,
    "sha256": "514ac9461c3751804b001513c831c0993573b781b701b213286fab507138e766"
  },
  "wp-mu-plugins/harmat-home-family/manifest-six-r2.json": {
    "bytes": 889,
    "sha256": "b7ad673660278889725b832cf748656c5b4e59fc92220519e75117eb99f25280"
  },
  "wp-mu-plugins/zz-harmat-home-family.php": {
    "bytes": 26850,
    "sha256": "7d823dc258bd7e64ede2586455ce222000632d2f47aa5523df779542d0bf3dab"
  }
};
const R2_SCRIPT_CHANGES = [
  [
    "      expanded = !expanded;\n      render();\n",
    "      expanded = !expanded;\n      render();\n      if (!expanded && typeof button.scrollIntoView === 'function') {\n        const bounds = button.getBoundingClientRect();\n        // Keep the collapsed control visible below the native sticky header.\n        if (bounds.top < 76 || bounds.bottom > window.innerHeight) {\n          button.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });\n        }\n      }\n"
  ]
];
const R2_CONTROLLER_CHANGES = [
  [
    " * Version: 1.0.14",
    " * Version: 1.0.15"
  ],
  [
    "$directory.'/manifest-six.json'",
    "$directory.'/manifest-six-r2.json'"
  ],
  [
    "3b6c8c5248dab6e6eb78d15b3f8cca5203498095980a9f3875fb32a2caafefc1",
    "b7ad673660278889725b832cf748656c5b4e59fc92220519e75117eb99f25280"
  ],
  [
    "$pins['body-six.php'],$pins['home-family-six.js']))",
    "$pins['body-six.php'],$pins['home-family-six.js'],$pins['home-family-six-r2.js']))"
  ],
  [
    "array('pannellum.js','home-family-six.js')",
    "array('pannellum.js','home-family-six-r2.js')"
  ]
];
const currentController = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/zz-harmat-home-family.php'), 'utf8');
// Restore only in memory, prove the released hash, and use an isolated local mirror below.
const r1Controller = allowlisted(currentController, R2_CONTROLLER_CHANGES.map(([before, after]) => [after, before]));

test('four frozen production files and explicit body/script/controller allowlist', () => {
  for (const [file, pin] of Object.entries(FROZEN.files)) {
    const bytes = file === 'wp-mu-plugins/zz-harmat-home-family.php'
      ? Buffer.from(r1Controller) : fs.readFileSync(path.join(ROOT, file));
    assert.equal(bytes.length, pin.bytes, file + ' byte count');
    assert.equal(sha(bytes), pin.sha256, file + ' SHA-256');
  }
  assert.equal(fs.readFileSync(path.join(DIR, 'body-six.php'), 'utf8'),
    allowlisted(fs.readFileSync(path.join(DIR, 'body.php'), 'utf8'), BODY_CHANGES));
  const oldScript = fs.readFileSync(path.join(DIR, 'home-family-scroll-play.js'), 'utf8');
  assert.equal(r1Script, allowlisted(oldScript, SCRIPT_CHANGES));
  assert.equal(script, allowlisted(r1Script, R2_SCRIPT_CHANGES));
  const oldController = execFileSync('git', ['show', FROZEN.baseline + ':wp-mu-plugins/zz-harmat-home-family.php'], { cwd: ROOT, encoding: 'utf8' });
  assert.equal(r1Controller, allowlisted(oldController, CONTROLLER_CHANGES));
  assert.equal(currentController, allowlisted(r1Controller, R2_CONTROLLER_CHANGES));
  const oldManifest = JSON.parse(fs.readFileSync(path.join(DIR, 'manifest-scroll-play.json')));
  for (const name of ['manifest.json', 'manifest-scroll-play.json', ...Object.keys(oldManifest)]) {
    const baseline = execFileSync('git', ['show', FROZEN.baseline + ':wp-mu-plugins/harmat-home-family/' + name], { cwd: ROOT });
    assert.deepEqual(fs.readFileSync(path.join(DIR, name)), baseline, 'original file byte-identical: ' + name);
  }
  const manifest = JSON.parse(fs.readFileSync(path.join(DIR, 'manifest-six.json')));
  assert.deepEqual(Object.keys(manifest), [...Object.keys(oldManifest), 'body-six.php', 'home-family-six.js']);
  for (const [name, pin] of Object.entries(oldManifest)) assert.equal(manifest[name], pin, 'retained pin: ' + name);
  for (const [name, pin] of Object.entries(manifest)) assert.equal(sha(fs.readFileSync(path.join(DIR, name))), pin, 'actual asset: ' + name);
  const panorama = source => source.slice(source.indexOf('  function initPanorama() {'), source.indexOf('  updatePanoramaLanguage = initPanorama();'));
  const playback = source => source.slice(source.indexOf('  function canPlayVideo() {'), source.indexOf('  function initPanorama() {'));
  assert.equal(panorama(script), panorama(oldScript), 'all panorama code byte-identical');
  assert.equal(playback(script), playback(oldScript), 'all hero playback code byte-identical');
  const addedCode = SCRIPT_CHANGES.map(([, after]) => after).join('\n');
  assert.doesNotMatch(addedCode, /fetch\(|XMLHttpRequest|localStorage|sessionStorage|sendBeacon|\.submit\(|innerHTML/);
  const body = fs.readFileSync(path.join(DIR, 'body-six.php'), 'utf8');
  assert.doesNotMatch(body, /4317|5212|4311|5302|5460|5318|62236650|47\.83/);
  const fixturePath = 'server-config/maintenance/2026-10-18-test-home-family-controller.php';
  const oldFixture = execFileSync('git', ['show', FROZEN.baseline + ':' + fixturePath], { cwd: ROOT, encoding: 'utf8' });
  assert.equal(fs.readFileSync(path.join(ROOT, fixturePath), 'utf8'), allowlisted(oldFixture, [[
    "array(4317=>'A1-1-L6',5212=>'A2-4-L5',4311=>'A1-1-L5')",
    "array(4317=>'A1-1-L6',5212=>'A2-4-L5',4311=>'A1-1-L5',5302=>'A3-F-L3',5460=>'A4-F-L1',5318=>'A3-2-L2')"
  ]]));
});
test('existing native controller fixture retains all 76 checks with six exact code/ID bindings', () => {
  const output = execFileSync(PHP, [path.join(__dirname, '2026-10-18-test-home-family-controller.php'),
    path.join(ROOT, 'wp-mu-plugins/zz-harmat-home-family.php')], { encoding: 'utf8' });
  const result = JSON.parse(output);
  assert.equal(result.status, 'PASS');
  assert.equal(result.checks, 76);
});

test('PHP CLI resolves candidate-home.php sibling mirrored assets with no repository fallback', () => {
  const os = require('node:os');
  const tempRoot = fs.realpathSync(os.tmpdir());
  const mirror = fs.mkdtempSync(path.join(tempRoot, 'harmat-home-six-'));
  try {
    const assetDir = path.join(mirror, 'harmat-home-family');
    fs.mkdirSync(assetDir);
    for (const name of fs.readdirSync(DIR)) {
      if (fs.statSync(path.join(DIR, name)).isFile()) fs.copyFileSync(path.join(DIR, name), path.join(assetDir, name));
    }
    const candidate = path.join(mirror, 'candidate-home.php');
    fs.writeFileSync(candidate, r1Controller);
    const output = execFileSync(PHP, [path.join(__dirname, '2026-10-20-test-home-six.php'), candidate], { encoding: 'utf8' });
    assert.match(output, /^PASS 136 presentation PHP assertions/);
    const controller = JSON.parse(execFileSync(PHP, [path.join(__dirname, '2026-10-18-test-home-family-controller.php'), candidate], { encoding: 'utf8' }));
    assert.equal(controller.status, 'PASS');
    assert.equal(controller.checks, 76);
    assert.equal(execFileSync(PHP, [path.join(__dirname, '2026-10-20-test-home-six.php'), candidate, '--render'], { encoding: 'utf8' }), html);
  } finally {
    const relative = path.relative(tempRoot, fs.realpathSync(mirror));
    assert.ok(relative.startsWith('harmat-home-six-') && !relative.includes(path.sep) && !path.isAbsolute(relative), 'cleanup is confined to this exact temporary mirror');
    fs.rmSync(mirror, { recursive: true });
  }
});

function fixture(t, options = {}) {
  const dom = new JSDOM(`<html lang="hu" data-harmat-language="hu"><head><title>Native SEO</title></head><body><header id="masthead">Native menu</header>${options.html || html}<footer id="colophon">Native footer</footer><form id="h22-offer-form"><input name="contact" value="Unsent draft"><textarea name="message">Original draft</textarea><select name="source"><option value="Google" selected>Google</option></select><input name="privacy" type="checkbox" required></form><form id="appointment-form"><input type="date" value="2026-10-22"><input name="time" value="12-15"></form></body></html>`, {
    url: 'https://harmat22.hu/', runScripts: 'outside-only', pretendToBeVisual: true
  });
  const w = dom.window, d = w.document, root = d.getElementById('harmat-home-family');
  const hero = root.querySelector('.hhf-hero'), video = root.querySelector('.hhf-hero-video');
  const button = root.querySelector('.hhf-video-button'), consoleRoot = root.querySelector('[data-panorama-console]');
  const protectedNodes = [...d.querySelectorAll('header,footer,form')], protectedHTML = protectedNodes.map(n => n.outerHTML);
  const fields = () => [...d.querySelectorAll('form input,form textarea,form select')].map(n => [n.value, n.checked]);
  const protectedFields = fields(), effects = [], observers = [], viewers = [], media = [], writes = [];
  const apartmentToggle = root.querySelector('.hhf-apartment-toggle'), scrolls = [];
  let toggleBounds = options.toggleBounds || { top: 200, bottom: 252 };
  apartmentToggle.getBoundingClientRect = () => toggleBounds;
  if (options.scrollMethod !== false) apartmentToggle.scrollIntoView = function (scrollOptions) {
    scrolls.push({ target: this, options: { ...scrollOptions }, expanded: this.getAttribute('aria-expanded'),
      visible: [...root.querySelectorAll('.hhf-apartment')].filter(card => !card.hidden).length });
  };
  const mediaAttributes = [...video.attributes].map(a => [a.name, a.value]);
  const paused = new WeakMap(), motionListeners = [], pending = [];
  const motion = { matches: !!options.reduced, addEventListener(type, callback) { if (type === 'change') motionListeners.push(callback); } };
  let hidden = !!options.hidden, heroVisible = options.heroVisible !== false, near = false, heroReads = 0;
  const mobileListeners = [];
  const mobile = { matches: !!options.mobile };
  if (options.mobileEvents !== false) mobile.addEventListener = (type, callback) => { if (type === 'change') mobileListeners.push(callback); };
  w.matchMedia = query => query === '(max-width: 620px)' ? mobile : motion;
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
    w, d, root, hero, video, button, consoleRoot, observers, viewers, media, writes, scrolls,
    setToggleBounds(value) { toggleBounds = value; },
    setMobile(value) { mobile.matches = value; mobileListeners.forEach(callback => callback()); },
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

const codes = ['A1-1-L6', 'A2-4-L5', 'A1-1-L5', 'A3-F-L3', 'A4-F-L1', 'A3-2-L2'];
const cards = f => [...f.root.querySelectorAll('#hhf-apartment-grid > .hhf-apartment')];
const visibleCards = f => cards(f).filter(card => !card.hidden);
const toggle = f => f.root.querySelector('.hhf-apartment-toggle');
function filteredHTML(count) {
  const rows = Object.fromEntries(codes.map((code, i) => [code, {
    code, url: 'https://harmat22.hu/property/' + code.toLowerCase() + '/',
    floorplan_url: 'https://harmat22.hu/wp-content/uploads/synthetic-' + i + '.jpg',
    rooms: 3, sales_area: 50 + i, price_huf: null, status: i < count ? 'current' : 'sold'
  }]));
  return execFileSync(PHP, [path.join(__dirname, '2026-10-20-test-home-six.php'), '--render'],
    { encoding: 'utf8', input: JSON.stringify(rows) });
}

test('no-JS and unsupported mobile change API show all six with hidden toggle', t => {
  const f = fixture(t, { mobile: true });
  assert.deepEqual(cards(f).map(card => card.dataset.code), codes);
  assert.equal(visibleCards(f).length, 6);
  assert.equal(toggle(f).hidden, true);
  const fallback = fixture(t, { mobile: true, mobileEvents: false }); fallback.boot();
  assert.equal(visibleCards(fallback).length, 6);
  assert.equal(toggle(fallback).hidden, true);
});

test('mobile first three, expand six, focus-safe collapse, native HU/ZH roundtrip and three rooms', async t => {
  const f = fixture(t, { mobile: true }); f.boot();
  const b = toggle(f);
  assert.deepEqual(visibleCards(f).map(card => card.dataset.code), codes.slice(0, 3));
  assert.equal(b.hidden, false);
  assert.equal(b.getAttribute('aria-expanded'), 'false');
  assert.equal(b.getAttribute('aria-controls'), 'hhf-apartment-grid');
  assert.equal(b.textContent, 'További 3 lakás megtekintése');
  b.click();
  assert.equal(visibleCards(f).length, 6);
  assert.equal(b.getAttribute('aria-expanded'), 'true');
  assert.equal(b.textContent, 'Kevesebb lakás');
  const extraLink = cards(f)[5].querySelector('a'); extraLink.focus();
  assert.equal(f.d.activeElement, extraLink);
  b.click();
  assert.equal(visibleCards(f).length, 3);
  assert.equal(f.d.activeElement, b, 'focus returned before hiding extra card');
  await f.language('zh-Hans');
  assert.equal(b.textContent, '再显示 3 套公寓');
  assert.equal(f.root.querySelector('[data-i18n="threeRooms"]').textContent, '3 个房间');
  for (const [key, text] of [['a3GroundFloor', 'A3 栋 · 底层'], ['a4GroundFloor', 'A4 栋 · 底层'], ['a3Floor2', 'A3 栋 · 2 层']]) {
    assert.equal(f.root.querySelector('[data-i18n="' + key + '"]').textContent, text);
  }
  b.click();
  assert.equal(b.textContent, '收起更多公寓');
  await f.language('hu');
  assert.equal(b.textContent, 'Kevesebb lakás');
  assert.equal(f.root.querySelector('[data-i18n="threeRooms"]').textContent, '3 szoba');
  b.click();
  assert.equal(b.textContent, 'További 3 lakás megtekintése');
  const nativeInput = f.d.querySelector('#h22-offer-form input'); nativeInput.focus();
  await f.language('zh-Hans'); await f.language('hu');
  assert.equal(f.d.activeElement, nativeInput, 'language changes never steal native form focus');
});

test('desktop six, resize collapse, focused extras and focused toggle have visible focus destination', t => {
  const f = fixture(t); f.boot();
  const b = toggle(f);
  assert.equal(visibleCards(f).length, 6);
  assert.equal(b.hidden, true);
  const extra = cards(f)[4].querySelector('a'); extra.focus();
  f.setMobile(true);
  assert.equal(visibleCards(f).length, 3);
  assert.equal(f.d.activeElement, b);
  b.click(); b.focus();
  f.setMobile(false);
  assert.equal(visibleCards(f).length, 6);
  assert.equal(b.hidden, true);
  assert.equal(f.d.activeElement, cards(f)[0].querySelector('a'));
  f.setMobile(true);
  assert.equal(visibleCards(f).length, 3, 'mobile returns to compact initial state');
  assert.equal(b.getAttribute('aria-expanded'), 'false');
  assert.equal(f.d.activeElement, cards(f)[0].querySelector('a'), 'already-visible focus retained');
});

for (const count of [0, 1, 2, 3, 4, 5, 6]) {
  test('fresh status-filtered ' + count + ' cards: actual extra count and no empty disclosure', async t => {
    const f = fixture(t, { mobile: true, html: filteredHTML(count) }); f.boot();
    const b = toggle(f);
    assert.equal(cards(f).length, count);
    assert.equal(visibleCards(f).length, Math.min(3, count));
    assert.equal(b.hidden, count <= 3);
    if (count > 3) {
      assert.equal(b.textContent, 'További ' + (count - 3) + ' lakás megtekintése');
      await f.language('zh-Hans');
      assert.equal(b.textContent, '再显示 ' + (count - 3) + ' 套公寓');
      b.click(); assert.equal(visibleCards(f).length, count);
      b.click(); assert.equal(visibleCards(f).length, 3);
    }
    f.setMobile(false);
    assert.equal(visibleCards(f).length, count);
  });
}

test('duplicate boot does not reset expanded cards or install a second toggle handler', t => {
  const f = fixture(t, { mobile: true }); f.boot(); toggle(f).click();
  assert.equal(visibleCards(f).length, 6);
  f.boot(); assert.equal(visibleCards(f).length, 6);
  toggle(f).click(); assert.equal(visibleCards(f).length, 3);
});

test('r2 frozen production descriptor and ten-entry manifest retain every released r1 pin', () => {
  for (const [file, pin] of Object.entries(R2_FROZEN)) {
    const bytes = fs.readFileSync(path.join(ROOT, file));
    assert.equal(bytes.length, pin.bytes, file);
    assert.equal(sha(bytes), pin.sha256, file);
  }
  const r1 = JSON.parse(fs.readFileSync(path.join(DIR, 'manifest-six.json')));
  const r2 = JSON.parse(fs.readFileSync(path.join(DIR, 'manifest-six-r2.json')));
  assert.deepEqual(Object.keys(r2), [...Object.keys(r1), 'home-family-six-r2.js']);
  for (const [name, hash] of Object.entries(r1)) assert.equal(r2[name], hash);
  for (const [name, hash] of Object.entries(r2)) assert.equal(sha(fs.readFileSync(path.join(DIR, name))), hash);
  assert.match(currentController, /Version: 1\.0\.15/);
  assert.equal(script, allowlisted(r1Script, R2_SCRIPT_CHANGES), 'only collapse-click scroll return changes');
});

test('r2 offscreen collapse calls actual scrollIntoView on collapsed toggle; never init/expand/language/resize', async t => {
  const f = fixture(t, { mobile: true }); f.boot();
  Object.defineProperty(f.w, 'innerHeight', { value: 844, configurable: true });
  const b = toggle(f);
  assert.equal(f.scrolls.length, 0, 'no init scroll');
  b.click();
  assert.equal(f.scrolls.length, 0, 'no expand scroll');
  b.focus();
  f.setToggleBounds({ top: -1429, bottom: -1377 });
  b.click();
  assert.equal(f.scrolls.length, 1);
  assert.equal(f.scrolls[0].target, b);
  assert.deepEqual(f.scrolls[0].options, { block: 'center', inline: 'nearest', behavior: 'instant' });
  assert.equal(f.scrolls[0].expanded, 'false', 'scroll occurs after collapse render');
  assert.equal(f.scrolls[0].visible, 3);
  assert.equal(f.d.activeElement, b);
  assert.equal(f.video.currentTime, 17.25);
  assert.equal(f.video.paused, false);
  await f.language('zh-Hans'); await f.language('hu');
  f.setMobile(false); f.setMobile(true); f.scroll(false);
  assert.equal(f.scrolls.length, 1, 'no scroll from translation, resize, or page scrolling');
});

test('r2 collapse scroll is bounded to sticky-header and viewport edges', t => {
  for (const [top, bottom, expected] of [[-5, 47, 1], [75, 127, 1], [76, 128, 0], [500, 552, 0], [792, 844, 0], [793, 845, 1]]) {
    const f = fixture(t, { mobile: true, toggleBounds: { top, bottom } }); f.boot();
    Object.defineProperty(f.w, 'innerHeight', { value: 844, configurable: true });
    toggle(f).click();
    assert.equal(f.scrolls.length, 0, 'never center on expand');
    toggle(f).click();
    assert.equal(f.scrolls.length, expected, 'top=' + top + ',bottom=' + bottom);
  }
});

test('r2 missing scroll method, no JS, desktop click, and unsupported mobile API keep original fallback', t => {
  const noJS = fixture(t, { mobile: true });
  assert.equal(visibleCards(noJS).length, 6);
  assert.equal(toggle(noJS).hidden, true);
  assert.equal(noJS.scrolls.length, 0);
  const noMethod = fixture(t, { mobile: true, scrollMethod: false, toggleBounds: { top: -1429, bottom: -1377 } }); noMethod.boot();
  toggle(noMethod).click(); toggle(noMethod).click();
  assert.equal(visibleCards(noMethod).length, 3);
  assert.equal(noMethod.scrolls.length, 0);
  const desktop = fixture(t); desktop.boot(); toggle(desktop).click();
  assert.equal(visibleCards(desktop).length, 6);
  assert.equal(desktop.scrolls.length, 0);
  const unsupported = fixture(t, { mobile: true, mobileEvents: false }); unsupported.boot();
  assert.equal(visibleCards(unsupported).length, 6);
  assert.equal(unsupported.scrolls.length, 0);
});
