'use strict';
// Synthetic DOM, not a real browser, popup send, responsive pixel or device claim.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const {JSDOM} = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HHF_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const controller = path.join(ROOT, 'wp-mu-plugins/zz-harmat-home-family.php');
const html = execFileSync(PHP, [path.join(__dirname, '2026-10-18-test-home-family-controller.php'), controller, '--render'], {encoding:'utf8'});
const js = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-home-family/home-family.js'), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));

test('rendered hero uses original selector and native booking destinations with only two anchors', () => {
  const dom = new JSDOM(html);
  try {
    const d = dom.window.document, hero = d.querySelector('.hhf-hero-actions'), anchors = [...hero.querySelectorAll('a')];
    assert.equal(anchors.length, 2);
    assert.deepEqual(anchors.map(a => a.getAttribute('href')), ['https://harmat22.hu/virtualis-lakasvalaszto/', '#opal-contactform-popup-56f08cc']);
    assert.deepEqual(anchors.map(a => a.querySelector('span').textContent), ['Virtuális lakásválasztó', 'Időpontkérés']);
    assert.equal(hero.querySelectorAll('.hhf-button-white').length, 2);
    assert(!hero.querySelector('[target],form,input,iframe,script'));
    assert(d.getElementById('hhf-epitesi-naplo'));
    assert.equal(d.querySelectorAll('#h22-offer-form').length, 1);
  } finally { dom.window.close(); }
});

test('unchanged owned runtime translates both action labels HU-ZH-HU without changing native form or destinations', async () => {
  const dom = new JSDOM(html, {url:'https://harmat22.hu/', runScripts:'outside-only', pretendToBeVisual:true});
  const w = dom.window, d = w.document;
  try {
    const requests = [], form = d.getElementById('h22-offer-form'), native = form.outerHTML;
    for (const key of ['fetch','XMLHttpRequest','WebSocket','EventSource']) w[key] = () => {requests.push(key); throw new Error('NO_NETWORK');};
    w.navigator.sendBeacon = () => {requests.push('beacon'); throw new Error('NO_NETWORK');};
    w.HTMLFormElement.prototype.submit = w.HTMLFormElement.prototype.requestSubmit = () => {throw new Error('NO_SUBMIT');};
    w.matchMedia = () => ({matches:true, addEventListener(){}});
    w.IntersectionObserver = class {observe(){} disconnect(){}};
    w.HTMLMediaElement.prototype.pause = function(){};
    const links = [...d.querySelectorAll('.hhf-hero-actions a')], hrefs = links.map(a => a.getAttribute('href'));
    form.querySelector('input').value = 'Unsent draft';
    w.eval(js);
    for (let i=0; i<3; i++) {
      d.documentElement.setAttribute('data-harmat-language', 'zh-Hans'); await tick();
      assert.deepEqual(links.map(a => a.querySelector('span').textContent), ['虚拟选房','预约参观']);
      d.documentElement.setAttribute('data-harmat-language', 'hu'); await tick();
      assert.deepEqual(links.map(a => a.querySelector('span').textContent), ['Virtuális lakásválasztó','Időpontkérés']);
    }
    assert.deepEqual(links.map(a => a.getAttribute('href')), hrefs);
    assert.equal(form.outerHTML, native);
    assert.equal(form.querySelector('input').value, 'Unsent draft');
    assert.equal(form.querySelector('[required]').checked, false);
    assert.deepEqual(requests, []);
    let nativeDelegated = 0;
    d.addEventListener('click', e => {if(e.target.closest('a')?.getAttribute('href') === '#opal-contactform-popup-56f08cc'){nativeDelegated++; e.preventDefault();}});
    links[1].click();
    assert.equal(nativeDelegated, 1, 'owned runtime leaves native delegated click available; native popup itself requires live QA');
  } finally { w.close(); }
});

test('new CSS is scoped to hero actions and summary minimum sizing only', () => {
  const source = fs.readFileSync(controller, 'utf8');
  const lines = source.split('\n');
  const additions = lines.filter(l => l.includes(".hhf-hero-booking{") || l.includes(".hhf-hero-booking:hover{") || l.includes('max-width:320px'));
  assert.equal(additions.length, 3);
  const css = additions.map(l => l.match(/\. '([^']*)'/)[1]).join('');
  const dom = new JSDOM('<style>' + css + '</style>');
  try {
    const rules = [...dom.window.document.styleSheets[0].cssRules];
    assert.equal(rules.length, 3);
    assert.match(rules[2].conditionText, /max-width:620px/);
    assert.equal(rules[2].cssRules[0].style.getPropertyValue('flex-direction'), 'column');
    assert.equal(rules[2].cssRules[0].style.getPropertyValue('max-width'), '320px');
    assert.equal(rules[2].cssRules[1].style.getPropertyValue('display'), 'block');
    assert.equal(rules[2].cssRules[1].style.getPropertyValue('font-size'), '16px');
    assert.equal(rules[2].cssRules[2].style.getPropertyValue('min-height'), '64px');
    for (const rule of [rules[0], rules[1], ...rules[2].cssRules]) {
      assert(rule.selectorText.startsWith('body.home.page-id-164 #harmat-home-family '));
      assert(!/form|input|textarea|masthead|sticky|\.hhf-panorama/.test(rule.selectorText));
      assert.equal(rule.style.getPropertyValue('height'), '');
    }
  } finally {dom.window.close();}
});
