'use strict';
// Offline PHP/DOM/CSSOM contracts only, not browser layout, pixels or video decoding.
// Actual 320/390 HU/ZH and desktop geometry/playback QA belongs to the parent workflow.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const { JSDOM, VirtualConsole } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HHF_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const read = file => fs.readFileSync(path.join(ROOT, file), 'utf8');
const sha = value => crypto.createHash('sha256').update(value).digest('hex');
const controller = read('wp-mu-plugins/zz-harmat-home-family.php').replace(/\r\n/g, '\n');
const rendered = execFileSync(PHP, [
  path.join(__dirname, '2026-10-18-test-home-family-controller.php'),
  path.join(ROOT, 'wp-mu-plugins/zz-harmat-home-family.php'), '--render',
], { encoding: 'utf8' });

function fixture(t) {
  // No resources option or script execution: all linked production resources stay offline.
  const dom = new JSDOM(rendered);
  t.after(() => dom.window.close());
  const d = dom.window.document;
  const inline = [...d.querySelector('#harmat-home-family-header-flow').sheet.cssRules];
  const style = d.createElement('style');
  style.textContent = read('wp-mu-plugins/harmat-home-family/home-family.css');
  d.head.append(style);
  return { d, inline, base: [...style.sheet.cssRules] };
}
const owned = 'body.home.page-id-164 #harmat-home-family ';
const baseRule = (rules, selector) => {
  const rule = rules.find(rule => rule.selectorText === selector);
  assert.ok(rule, selector);
  return rule.style;
};

test('v1.0.9 changes only the final hero overrides and version, relative to v1.0.8', () => {
  assert.match(controller, /Version: 1\.0\.9\n/);
  const start = controller.indexOf("            . '" + owned + '.hhf-hero{');
  const end = controller.indexOf('\n', controller.indexOf('</style>', start));
  assert.ok(start > 0 && end > start);
  const unchanged = (controller.slice(0, start) + 'HERO_LAYOUT\n' + controller.slice(end + 1))
    .replace('Version: 1.0.9', 'Version: VERSION');
  assert.equal(sha(unchanged), '5b806a694d0946fafbaf4e54a203db2289388d482e8d4fdd1a27e2f3cb146889');
});

test('final CSSOM rules are hero-only; full parent width without a 1280px cap or header gap', t => {
  const { inline } = fixture(t);
  const tail = inline.slice(-3);
  assert.deepEqual(tail.map(rule => rule.selectorText), [
    owned + '.hhf-hero', owned + '.hhf-hero-actions .hhf-button',
    owned + '.hhf-hero-actions .hhf-button>span',
  ]);
  assert.equal(tail[0].style.width, '100%');
  assert.equal(tail[0].style.getPropertyValue('max-width'), 'none');
  assert.equal(tail[0].style.margin, '0');
  assert.equal(tail[0].style.getPropertyValue('scroll-margin-top'), '76px');
  for (const rule of tail) assert.doesNotMatch(rule.cssText, /!important|100vw|header|form|language/);
  for (const property of ['height', 'min-height', 'position', 'display', 'grid-template-columns']) {
    assert.equal(tail[0].style.getPropertyValue(property), '', 'retain pinned baseline ' + property);
  }
  assert.doesNotMatch(inline.map(rule => rule.cssText).join('\n'), /object-fit:\s*contain;?[^}]*object-position:\s*center|grid-area:\s*2\s*\/\s*1|[;{]\s*margin-top:\s*76px/);
});

test('pinned poster/video cover the same full hero, with existing reveal and overlay layers', t => {
  const { base, inline } = fixture(t);
  const selector = '#harmat-home-family .hhf-hero-poster, #harmat-home-family .hhf-hero-video';
  const media = baseRule(base, selector);
  assert.equal(media.position, 'absolute');
  assert.equal(media.getPropertyValue('inset'), '0');
  assert.equal(media.width, '100%'); assert.equal(media.height, '100%');
  assert.equal(media.getPropertyValue('object-fit'), 'cover');
  assert.equal(baseRule(base, '#harmat-home-family .hhf-hero-video').opacity, '0');
  assert.equal(baseRule(base, '#harmat-home-family .hhf-hero-video[data-revealed="true"]').opacity, '1');
  const shade = baseRule(base, '#harmat-home-family .hhf-hero::before');
  assert.match(shade.background, /linear-gradient/);
  assert.equal(shade.getPropertyValue('pointer-events'), 'none');
  assert.equal(inline.filter(rule => /hhf-hero-(poster|video|inner|copy|bottom)|hhf-hero::before/.test(rule.cssText)).length, 0);
});

test('bounded approved heights and in-frame copy are restored at desktop/tablet/mobile breakpoints', t => {
  const { base } = fixture(t);
  const hero = '#harmat-home-family .hhf-hero';
  const desktop = baseRule(base, hero);
  assert.equal(desktop.height, 'min(570px, calc(100svh - 206px))');
  assert.equal(desktop.getPropertyValue('min-height'), '400px');
  for (const [width, height] of [[900, 'min(530px, calc(100svh - 190px))'], [650, 'min(520px, calc(100svh - 180px))']]) {
    const media = base.find(rule => rule.conditionText === `(max-width: ${width}px)`
      && [...rule.cssRules].some(child => child.selectorText === hero));
    assert.ok(media);
    assert.equal(baseRule([...media.cssRules], hero).height, height);
  }
  const inner = baseRule(base, '#harmat-home-family .hhf-hero-inner');
  assert.equal(inner.display, 'flex'); assert.equal(inner.height, '100%');
  assert.equal(inner.getPropertyValue('justify-content'), 'flex-end');
  assert.equal(baseRule(base, '#harmat-home-family .hhf-hero h1').color, '#fff');
  // Declaration arithmetic only: these are NOT measured browser rectangles.
  for (const [viewport, cap, reserve, minimum] of [[568,520,180,388], [844,520,180,388], [800,570,206,400], [900,570,206,400]]) {
    const declaredHeight = Math.max(minimum, Math.min(cap, viewport - reserve));
    assert.ok(viewport - 76 - declaredHeight >= 60, 'space after header + hero at representative portrait/desktop heights');
  }
});

test('narrow action labels can shrink/wrap while existing stacked controls and fixed type survive', t => {
  const { inline } = fixture(t);
  const button = inline.at(-2).style;
  assert.equal(button.getPropertyValue('min-width'), '0');
  assert.equal(button.getPropertyValue('max-width'), '100%');
  assert.equal(button.getPropertyValue('white-space'), 'normal');
  assert.equal(inline.at(-1).style.getPropertyValue('min-width'), '0');
  const stacked = inline.find(rule => rule.conditionText === '(max-width:620px)'
    && [...rule.cssRules].some(child => child.selectorText === owned + '.hhf-hero-actions'));
  assert.equal(baseRule([...stacked.cssRules], owned + '.hhf-hero-actions').getPropertyValue('flex-direction'), 'column');
  assert.equal(baseRule(inline, owned + '.hhf-hero-actions .hhf-button').getPropertyValue('font-size'), '16px');
});

test('real PHP output keeps both official actions, original media and native shell sentinels', t => {
  const { d } = fixture(t);
  assert.deepEqual([...d.querySelectorAll('.hhf-hero-actions a')].map(node => node.getAttribute('href')), [
    'https://harmat22.hu/virtualis-lakasvalaszto/', 'https://harmat22.hu/lakaskereso/',
  ]);
  const video = d.querySelector('#hhf-hero-video');
  assert.equal(video.dataset.src, 'https://harmat22.hu/wp-content/uploads/harmat-video/harmat-home-1080p-v2.mp4');
  assert.equal(video.getAttribute('preload'), 'none');
  assert.ok(video.hasAttribute('muted') && video.hasAttribute('playsinline') && video.hasAttribute('loop'));
  assert.ok(!video.hasAttribute('autoplay') && !video.hasAttribute('src'), 'visibility-aware JS retains playback ownership');
  assert.equal(d.querySelector('#hero-title').textContent, 'Harmat Lak\u00f3park');
  assert.equal(d.querySelector('#masthead').textContent, 'Native header');
  assert.equal(d.querySelectorAll('#h22-offer-form').length, 1);
  assert.equal(d.querySelectorAll('#harmat-internal-language button').length, 2);
});

test('all manifest-pinned assets, including autoplay/reduced-motion/manual/offscreen runtime, are untouched', () => {
  const directory = path.join(ROOT, 'wp-mu-plugins/harmat-home-family');
  const manifest = fs.readFileSync(path.join(directory, 'manifest.json'));
  assert.equal(sha(manifest), '21e6ebec9bf0bfcbc273c42b5dc33f07ce96547fa04ccdd0b53faf780d3587b3');
  for (const [file, pin] of Object.entries(JSON.parse(manifest))) {
    assert.equal(sha(fs.readFileSync(path.join(directory, file))), pin, file);
  }
});

function playbackFixture(t, reduced) {
  // Synthetic media and visibility APIs: no actual playback/decoding/browser evidence.
  const errors = [], virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', error => errors.push(error.message));
  const dom = new JSDOM(rendered, { runScripts: 'outside-only', pretendToBeVisual: true, virtualConsole });
  const w = dom.window, d = w.document, hero = d.querySelector('.hhf-hero');
  const video = d.querySelector('#hhf-hero-video');
  const native = [...d.querySelectorAll('#masthead,#colophon,#h22-offer-modal,#harmat-internal-language')];
  const snapshots = native.map(node => node.outerHTML);
  const observers = [], calls = [];
  let visible = false, hidden = false, paused = true;
  hero.getBoundingClientRect = () => ({ width: 320, height: 388, left: 0, right: 320,
    top: visible ? 76 : -500, bottom: visible ? 464 : -112 });
  Object.defineProperty(d, 'hidden', { get: () => hidden });
  Object.defineProperty(video, 'paused', { get: () => paused });
  video.play = () => {
    calls.push('play'); paused = false; video.dispatchEvent(new w.Event('playing'));
    return Promise.resolve();
  };
  video.pause = () => { calls.push('pause'); paused = true; video.dispatchEvent(new w.Event('pause')); };
  for (const clip of d.querySelectorAll('video:not(#hhf-hero-video)')) {
    // Hidden, still-deferred panorama clips receive unconditional background pauses.
    clip.pause = () => {};
    clip.play = () => { throw new Error('Unexpected nonhero autoplay'); };
  }
  w.matchMedia = () => ({ matches: reduced, addEventListener() {} });
  w.IntersectionObserver = class {
    constructor(callback) { this.callback = callback; observers.push(this); }
    observe(target) { this.target = target; }
    disconnect() {}
  };
  t.after(() => {
    try {
      assert.deepEqual(native.map(node => node.outerHTML), snapshots);
      assert.deepEqual(errors, [], 'no ignored runtime/jsdom errors');
    }
    finally { w.close(); }
  });
  w.eval(read('wp-mu-plugins/harmat-home-family/home-family.js'));
  return { video, calls, button: d.querySelector('.hhf-video-button'),
    visibility(value) {
      visible = value;
      observers.filter(observer => observer.target === hero).forEach(observer =>
        observer.callback([{ isIntersecting: value, intersectionRatio: value ? 1 : 0 }]));
    },
    background(value) { hidden = value; d.dispatchEvent(new w.Event('visibilitychange')); },
  };
}

test('mocked media: visible hero autoplays muted; offscreen/background pause and manual pause persists', t => {
  const f = playbackFixture(t, false);
  assert.deepEqual(f.calls, []); assert.equal(f.video.hasAttribute('src'), false);
  f.visibility(true);
  assert.equal(f.video.paused, false); assert.equal(f.video.muted, true);
  assert.equal(f.video.dataset.revealed, 'true');
  assert.equal(f.video.getAttribute('src'), f.video.dataset.src);
  f.visibility(false); assert.equal(f.video.paused, true);
  f.visibility(true); assert.equal(f.video.paused, false);
  f.background(true); assert.equal(f.video.paused, true);
  f.background(false); assert.equal(f.video.paused, false);
  f.button.click(); assert.equal(f.video.paused, true);
  const plays = f.calls.filter(call => call === 'play').length;
  f.visibility(false); f.visibility(true); f.background(true); f.background(false);
  assert.equal(f.video.paused, true);
  assert.equal(f.calls.filter(call => call === 'play').length, plays);
  f.button.click(); assert.equal(f.video.paused, false);
});

test('mocked media: reduced motion stays click-only, and offscreen cancels manual playback permission', t => {
  const f = playbackFixture(t, true);
  f.visibility(true);
  assert.deepEqual(f.calls, []); assert.equal(f.video.hasAttribute('src'), false);
  f.button.click(); assert.equal(f.video.paused, false); assert.equal(f.video.muted, true);
  f.visibility(false); assert.equal(f.video.paused, true);
  f.visibility(true); assert.equal(f.video.paused, true);
  f.button.click(); assert.equal(f.video.paused, false);
  f.background(true); assert.equal(f.video.paused, true);
  f.background(false); assert.equal(f.video.paused, true);
});
