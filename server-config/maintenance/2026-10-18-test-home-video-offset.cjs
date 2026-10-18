'use strict';
// Synthetic CSSOM checks only: actual scroll geometry remains a browser acceptance step.
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
const dom = new JSDOM(rendered);
const all = [...dom.window.document.querySelector('#harmat-home-family-header-flow').sheet.cssRules];
const desktop = all.at(-1);
const hero = 'body.home.page-id-164 #harmat-home-family .hhf-hero';

test('v1.0.7 appends exactly one homepage hero rule, strictly above 1024px', () => {
  assert.equal(desktop.conditionText, '(min-width:1025px)');
  assert.equal(desktop.cssRules.length, 1);
  const rule = desktop.cssRules[0];
  assert.equal(rule.selectorText, hero);
  const properties = Array.from({length:rule.style.length}, (_, i) => rule.style[i]);
  assert.deepEqual(properties, ['margin-top','scroll-margin-top']);
  assert.equal(rule.style.getPropertyValue('margin-top'), '76px');
  assert.equal(rule.style.getPropertyValue('scroll-margin-top'), '152px');
  assert.equal(rule.style.getPropertyValue('height'), '');
  for (const name of properties) assert.equal(rule.style.getPropertyPriority(name), '');
  assert(!/header|language|form|position|font|transform/.test(rule.cssText));
});

test('phone/tablet media never receive the added offset, header and min-height stay unchanged', () => {
  const min = Number(desktop.conditionText.match(/min-width:(\d+)px/)[1]);
  for (const width of [320,390,767,768,1024]) assert.equal(width >= min, false);
  for (const width of [1025,1280,1920,2400]) assert.equal(width >= min, true);
  assert.equal(desktop.cssRules[0].style.getPropertyValue('min-height'), '');
  const base = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-home-family/home-family.css'), 'utf8');
  assert.match(base, /min-height:\s*400px/);
  assert.match(base, /100svh\s*-\s*206px/);
  const previous = all.at(-2);
  assert.equal(previous.conditionText, '(max-width:767px)');
  assert.equal(previous.cssRules.length, 4);
});

test('desktop offset does not change existing hero actions or native form markup', () => {
  const actions = [...dom.window.document.querySelectorAll('.hhf-hero-actions a')];
  assert.deepEqual(actions.map(a => a.href), ['https://harmat22.hu/virtualis-lakasvalaszto/', 'https://harmat22.hu/lakaskereso/']);
  assert.equal(dom.window.document.querySelectorAll('#h22-offer-form').length, 1);
  assert.equal(dom.window.document.querySelectorAll('#masthead').length, 1);
  assert.equal(dom.window.document.querySelector('#masthead').textContent, 'Native header');
  assert.equal(dom.window.document.querySelectorAll('#harmat-internal-language button').length, 2);
});
