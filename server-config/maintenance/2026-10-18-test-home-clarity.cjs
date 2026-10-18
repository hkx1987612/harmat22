'use strict';
// CSSOM and synthetic DOM checks only, not actual browser geometry or device QA.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HHF_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const controller = path.join(ROOT, 'wp-mu-plugins/zz-harmat-home-family.php');
const rendered = execFileSync(PHP, [path.join(__dirname, '2026-10-18-test-home-family-controller.php'), controller, '--render'], {encoding:'utf8'});
const scope = 'body.home.page-id-164 #harmat-home-family';
const header = 'body.home.page-id-164.opal-header-absolute #my-sticky-header';
const language = 'body.home.page-id-164 #harmat-internal-language';
const dom = new JSDOM(rendered);
const all = [...dom.window.document.querySelector('#harmat-home-family-header-flow').sheet.cssRules];
const start = all.findIndex(r => r.style?.getPropertyValue('--muted') === '#47574e');
assert(start >= 0);
const rules = all.slice(start);
const flattened = [];
function collect(list, media = '') {
  for (const r of list) {
    if (r.cssRules) collect([...r.cssRules], r.conditionText);
    else flattened.push({r, media});
  }
}
collect(rules);
function value(selector, property, width = 1280) {
  let result = '';
  for (const {r, media} of flattened) {
    const max = media.match(/max-width:(\d+)px/);
    if (max && width > Number(max[1])) continue;
    if (r.selectorText.split(',').includes(selector)) result = r.style.getPropertyValue(property) || result;
  }
  return result;
}

test('clarity rules stay homepage scoped and preserve sticky behavior and business interfaces', () => {
  for (const {r, media} of flattened) {
    for (const s of r.selectorText.split(',')) {
      assert(s === scope || s.startsWith(scope + ' ') || s.startsWith(header + ' ') || s.startsWith(language), s);
      if (s.startsWith(header) || s.startsWith(language)) assert.equal(media, '(max-width:767px)');
      assert(!/h22-offer|form|input|textarea|data-id=/.test(s));
    }
    for (const prop of ['position','transform','opacity','animation','visibility','pointer-events','z-index']) {
      assert.equal(r.style.getPropertyValue(prop), '', prop);
    }
    assert(!/vw|vmin|vmax/.test(r.style.getPropertyValue('font-size')));
    assert.notEqual(r.style.getPropertyValue('display'), 'none');
  }
});

test('fixed hero and CTA sizes improve clarity without inflating compact 3D headings', () => {
  for (const [width, expected] of [[1280,'38px'],[767,'38px'],[620,'32px'],[390,'32px'],[360,'30px'],[320,'30px']]) {
    assert.equal(value(scope + ' .hhf-hero h1','font-size',width), expected);
    assert.equal(value(scope + ' .hhf-hero-actions .hhf-button','font-size',width), '16px');
  }
  assert.equal(value(scope + ' h1','font-weight'), '600');
  assert.equal(value(scope + ' h2','font-weight'), '600');
  assert.equal(value(scope + ' h3','font-weight'), '600');
  assert.equal(value(scope + ' .hhf-highlight-grid dd>strong','font-weight'), '500');
  assert.equal(value(scope + ' .hhf-hero-booking','background'), 'rgba(15,33,24,.3)');
  assert.equal(value(scope + ' .hhf-panorama-detail h3','font-size'), '');
  assert.equal(dom.window.document.querySelectorAll('#h22-offer-form').length, 1);
  assert.equal(dom.window.document.querySelectorAll('.hhf-hero-actions a[href*="#opal-contactform-popup"]').length, 0);
});

test('phone header has one bounded grid row, preserved quote control and room for both languages', () => {
  const row = header + ' .headerrow>.e-con-inner';
  assert.equal(value(row,'display',390), 'grid');
  assert.equal(value(row,'grid-template-columns',390), 'minmax(133px,1fr) 36px minmax(100px,1fr)');
  assert.equal(value(row,'gap',390), '4px');
  assert.equal(value(row,'padding-inline',390), '12px');
  assert.equal(value(row,'display',768), '');
  const quote = header + ' .elementor-element-56f08cc';
  assert.equal(value(quote,'display',320), 'block');
  assert.equal(value(quote,'width',320), '100px');
  assert.equal(value(quote,'gap',320), '0');
  assert.equal(value(quote + ' a.elementor-button','min-height',320), '40px');
  assert.equal(value(quote + ' a.elementor-button','text-transform',320), 'none');
  assert.equal(value(language + '[data-hil-anchor=menu]','width',320), '84px');
  assert.equal(value(language + '[data-hil-anchor=fallback]','left',320), '62px');
  assert.equal(value(language + '>button[data-hil-language=hu]','flex',320), '1.4 1 0');
  // Arithmetic for the declared grid; does not claim measured browser layout.
  for (const rootWidth of [301,320,371,390,749,767]) {
    const tracks = rootWidth - 24 - 8;
    const first = Math.max(133, (tracks - 36) / 2);
    const last = tracks - first - 36;
    const logoLeft = 12 + first + 4;
    const quoteRight = 12 + first + 4 + 36 + 4 + last;
    assert(last >= 100);
    assert(62 + 84 <= logoLeft, 'language ends before logo');
    assert(quoteRight <= rootWidth - 12);
  }
});

test('v1.0.6 clears only the three observed inherited phone paddings', () => {
  const last = rules.at(-1);
  assert.equal(last.conditionText, '(max-width:767px)');
  assert.equal(last.cssRules.length, 4);
  const expected = [header + ' .headerrow', header + ' .elementor-widget-icon', header + ' .elementor-element-56f08cc'];
  for (let i=0;i<3;i++) {
    const rule = last.cssRules[i];
    assert.equal(rule.selectorText, expected[i]);
    assert.equal(rule.style.length, 1);
    assert.equal(rule.style.getPropertyValue('padding-inline'), '0');
    assert.equal(rule.style.getPropertyPriority('padding-inline'), 'important');
    assert.equal(value(expected[i], 'padding-inline', 320), '0');
    assert.equal(value(expected[i], 'padding-inline', 768), '');
  }
  assert.equal(last.cssRules[3].style.getPropertyValue('flex'), '1.4 1 0');
});

test('owned body and muted text maintain strong contrast on approved bands', () => {
  const luminance = color => {
    const rgb = color.replace('#','').match(/../g).map(v => parseInt(v,16)/255)
      .map(v => v <= .04045 ? v/12.92 : ((v+.055)/1.055)**2.4);
    return rgb[0]*.2126 + rgb[1]*.7152 + rgb[2]*.0722;
  };
  for (const property of ['--body','--muted']) {
    const fg = luminance(value(scope,property));
    for (const bg of ['#ffffff','#f8f8f6','#f6f5f2','#f5f4f2','#edf2ef','#e9f0eb','#f1f0ed']) {
      assert((luminance(bg)+.05)/(fg+.05) >= 6.2, property + ' on ' + bg);
    }
  }
});
