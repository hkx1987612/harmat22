'use strict';
// Genuine native/HAP scripts in an offline DOM. Not browser paint or physical-device evidence.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HHF_PHP || (process.platform === 'win32' ? 'C:/tools/php-8.5.6/php.exe' : 'php');
const candidate = process.env.HHAI_TEST_CONTROLLER || process.argv.slice(2).find(arg => !arg.startsWith('--'))
  || path.join(ROOT, 'wp-mu-plugins/zz-harmat-home-assistant-inline.php');
const source = fs.readFileSync(candidate, 'utf8');
const fixturePHP = path.join(__dirname, '2026-10-19-test-home-assistant-inline.php');
const assets = execFileSync(PHP, [fixturePHP, candidate, '--render'], { encoding: 'utf8' });
const script = assets.match(/<script id="harmat-home-assistant-inline-script">([\s\S]*?)<\/script>/)[1];
const css = assets.match(/<style id="harmat-home-assistant-inline-style">([\s\S]*?)<\/style>/)[1];
const homeBody = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-home-family/body.php'), 'utf8')
  .match(/<<<'HHF_MAIN_HTML'\r?\n([\s\S]*?)\r?\nHHF_MAIN_HTML/)[1];
const widget = execFileSync(PHP, [path.join(ROOT, 'wp-mu-plugins/harmat-assistant-presentation/tests/widget-fixture.php'), '--render'], { encoding: 'utf8' });
const tick = () => new Promise(resolve => setImmediate(resolve));

async function fixture(t, options = {}) {
  const dom = new JSDOM(`<html lang="hu" data-harmat-language="hu"><head><title>Native SEO</title><style id="harmat-home-assistant-inline-style">${css}</style></head><body><header id="masthead">Native header</header>${homeBody}<i id="native-launch-before"></i>${widget}<i id="native-launch-after"></i><div id="h22-offer-modal" hidden aria-hidden="true"><form id="h22-offer-form"><input value="Unsent quote"><input type="checkbox" required></form></div><div id="hm-cookie-consent" hidden aria-hidden="true"></div><div id="harmat-clean-menu-modal" hidden aria-hidden="true"></div><form id="appointment-form"><input value="Unsent appointment"><input type="date" value="2026-10-22"></form><footer id="colophon">Native footer</footer></body></html>`, { url: 'https://harmat22.hu/', runScripts: 'outside-only', pretendToBeVisual: true });
  const w = dom.window, d = w.document;
  const calls = [], effects = [], observers = [];
  w.fetch = async (url, opts) => {
    calls.push({ url, payload: JSON.parse(opts.body) });
    assert.match(url, /^https:\/\/harmat22\.hu\/wp-json\/harmat-local-assistant\/v1\/event$/);
    return { ok: true, json: async () => ({ ok: true }) };
  };
  for (const name of ['XMLHttpRequest', 'WebSocket', 'EventSource']) w[name] = function () { effects.push(name); throw Error('NO_NETWORK'); };
  w.navigator.sendBeacon = () => { effects.push('beacon'); throw Error('NO_BEACON'); };
  for (const name of ['setItem', 'removeItem', 'clear', 'getItem']) w.Storage.prototype[name] = () => { effects.push(name); throw Error('NO_STORAGE'); };
  w.HTMLFormElement.prototype.submit = w.HTMLFormElement.prototype.requestSubmit = () => { effects.push('submit'); throw Error('NO_SUBMIT'); };
  w.HTMLMediaElement.prototype.play = w.HTMLMediaElement.prototype.pause = () => { effects.push('media'); throw Error('NO_MEDIA'); };
  const Observer = w.MutationObserver;
  w.MutationObserver = class extends Observer {
    observe(node, opts) { observers.push({ node, opts }); return super.observe(node, opts); }
  };
  w.eval(d.querySelector('#harmat-local-assistant-script').textContent);
  w.eval(d.querySelector('#harmat-assistant-presentation-script').textContent);
  await tick();
  const launch = d.querySelector('.harmat-local-ai-launch'), panel = d.querySelector('#harmat-local-ai-panel');
  const link = d.querySelector('#hhf-lakasok .hhf-text-link'), heading = link.parentNode;
  const input = panel.querySelector('input[name="message"]');
  input.value = 'Unsent assistant draft'; input.setSelectionRange(2, 8);
  const history = panel.querySelector('[data-harmat-local-ai-body]');
  const draft = () => [input.value, input.selectionStart, input.selectionEnd];
  const originalDraft = draft();
  const protectedNodes = [...d.querySelectorAll('#masthead,#colophon,#h22-offer-form,#appointment-form,video,style')];
  const protectedHTML = protectedNodes.map(n => n.outerHTML);
  const protectedFields = [...d.querySelectorAll('#h22-offer-form input,#appointment-form input')].map(n => [n.value, n.checked]);
  for (const form of d.querySelectorAll('#h22-offer-form,#appointment-form')) {
    for (const name of ['input', 'change', 'submit', 'click']) form.addEventListener(name, () => effects.push('form:' + name));
  }
  let ownClicks = 0;
  launch.addEventListener('click', () => { ownClicks++; });
  const f = { w, d, launch, panel, link, heading, input, history, calls, effects, observers, draft,
    boot() { w.eval(script); },
    get group() { return d.querySelector('#harmat-home-assistant-actions'); },
    get ownClicks() { return ownClicks; },
    async language(code) { d.documentElement.setAttribute('data-harmat-language', code); await tick(); },
    async modal(id, on) {
      const modal = d.getElementById(id);
      modal.getClientRects = () => on ? [{ width: 200, height: 200 }] : [];
      modal.hidden = !on; modal.setAttribute('aria-hidden', String(!on));
      await tick();
    }
  };
  if (options.mutate) options.mutate(f);
  f.before = d.body.innerHTML;
  f.launchHTML = launch.outerHTML; f.panelHTML = panel.outerHTML; f.linkHTML = link.outerHTML;
  f.originLaunch = [launch.parentNode, launch.nextSibling];
  f.originLink = [link.parentNode, link.nextSibling];
  f.observersBefore = observers.length;
  if (options.boot !== false) f.boot();
  await tick();
  t.after(() => {
    assert.deepEqual(protectedNodes.map(n => n.outerHTML), protectedHTML, 'no shell/form/video/style edits');
    assert.deepEqual([...d.querySelectorAll('#h22-offer-form input,#appointment-form input')].map(n => [n.value, n.checked]), protectedFields);
    assert.deepEqual(draft(), originalDraft, 'chat draft and selection unchanged');
    assert.equal(d.title, 'Native SEO');
    assert.deepEqual(effects, [], 'no added network/storage/form/media effects');
    w.close();
  });
  return f;
}

test('candidate PHP fixture has >=20 guards and candidate/render outputs are identical', () => {
  const result = JSON.parse(execFileSync(PHP, [fixturePHP, candidate], { encoding: 'utf8' }));
  assert.equal(result.status, 'PASS'); assert.ok(result.checks >= 20);
  const normal = execFileSync(PHP, [fixturePHP, '--render'], { encoding: 'utf8' });
  if (path.resolve(candidate) === path.join(ROOT, 'wp-mu-plugins/zz-harmat-home-assistant-inline.php')) assert.equal(normal, assets);
  assert.equal(assets.match(/<style\b/g).length, 1); assert.equal(assets.match(/<script\b/g).length, 1);
});

test('moves same original link/launcher below video with every child, label and direct handler intact', async t => {
  const f = await fixture(t);
  assert.equal(f.group.parentNode, f.heading);
  assert.deepEqual([...f.group.children], [f.link, f.launch]);
  assert.equal(f.heading.children.length, 2);
  assert.equal(f.launch.outerHTML, f.launchHTML);
  assert.equal(f.link.outerHTML, f.linkHTML);
  assert.equal(f.panel.outerHTML, f.panelHTML);
  assert.equal(f.panel.parentNode, f.d.body);
  assert.equal(f.d.querySelector('.harmat-local-ai-launch'), f.launch);
  assert.equal(f.d.querySelectorAll('.harmat-local-ai-launch').length, 1);
  assert.equal(f.link.href, 'https://harmat22.hu/lakaskereso/');
  assert.ok(f.d.querySelector('.hhf-hero').compareDocumentPosition(f.group) & f.w.Node.DOCUMENT_POSITION_FOLLOWING);
  assert.deepEqual(f.calls, [], 'no autolaunch or API call');
  assert.equal(f.panel.classList.contains('is-open'), false);
  assert.equal(f.observers.length, f.observersBefore, 'no addon observers');
  const icon = f.launch.querySelector('svg');
  icon.dispatchEvent(new f.w.MouseEvent('click', { bubbles: true, cancelable: true }));
  await tick();
  assert.equal(f.ownClicks, 1);
  assert.equal(f.panel.classList.contains('is-open'), true, 'original capture-document delegate opens original panel');
  assert.equal(f.calls.length, 1); assert.equal(f.calls[0].payload.event, 'assistant_open');
  assert.equal(f.input, f.panel.querySelector('input[name="message"]'));
  f.panel.querySelector('.harmat-local-ai-close').click(); await tick();
  assert.equal(f.panel.classList.contains('is-open'), false);
  assert.equal(f.d.activeElement, f.launch, 'HAP focus returns to relocated original launcher');
});

test('init twice leaves exactly one group and no DOM or chat/history changes', async t => {
  const f = await fixture(t);
  const before = f.d.body.innerHTML, history = f.history.innerHTML;
  f.boot(); f.boot(); await tick();
  assert.equal(f.d.body.innerHTML, before);
  assert.equal(f.history.innerHTML, history);
  assert.deepEqual(f.calls, []);
});

test('DOMContentLoaded scheduling is idempotent even with two script evaluations', async t => {
  const f = await fixture(t, { boot: false });
  Object.defineProperty(f.d, 'readyState', { configurable: true, value: 'loading' });
  f.boot(); f.boot(); assert.equal(f.group, null);
  f.d.dispatchEvent(new f.w.Event('DOMContentLoaded'));
  f.d.dispatchEvent(new f.w.Event('DOMContentLoaded'));
  assert.equal(f.d.querySelectorAll('#harmat-home-assistant-actions').length, 1);
  assert.equal(f.group.children[1], f.launch);
  assert.deepEqual(f.calls, []);
});

test('HU/ZH page bridge retains original launcher, icon, link and all native chat nodes', async t => {
  const f = await fixture(t);
  const label = f.launch.children[1], avatar = f.launch.children[0], icon = avatar.firstChild;
  const linkLabel = f.link.querySelector('[data-i18n="allApartments"]');
  const nodes = [f.launch, label, avatar, icon, f.link, linkLabel, f.panel, f.history, f.input, f.panel.querySelector('form')];
  const history = f.history.innerHTML;
  for (const code of ['zh-Hans', 'hu', 'zh-Hans', 'hu']) {
    await f.language(code);
    assert.equal(label.textContent, code === 'hu' ? 'K\u00e9rdezzen' : '\u54a8\u8be2');
    assert.deepEqual([f.group.children[1], f.launch.children[1], f.launch.children[0], avatar.firstChild, f.group.children[0], f.link.querySelector('[data-i18n="allApartments"]'), f.d.getElementById(f.panel.id), f.panel.querySelector('[data-harmat-local-ai-body]'), f.panel.querySelector('input[name="message"]'), f.panel.querySelector('form')], nodes);
    assert.equal(f.link.getAttribute('href'), 'https://harmat22.hu/lakaskereso/');
    assert.equal(f.history.innerHTML, history);
  }
  assert.deepEqual(f.calls, []);
});

for (const id of ['h22-offer-modal', 'hm-cookie-consent', 'harmat-clean-menu-modal']) {
  test(`original HAP suspension and focus behavior remain respected for ${id}`, async t => {
    const f = await fixture(t, { boot: false });
    await f.modal(id, true);
    const attrs = [f.launch.getAttribute('data-hap-suspended'), f.panel.getAttribute('data-hap-suspended')];
    assert.deepEqual(attrs, ['1', '1']);
    f.boot(); await tick();
    assert.equal(f.group.children[1], f.launch);
    assert.deepEqual([f.launch.getAttribute('data-hap-suspended'), f.panel.getAttribute('data-hap-suspended')], attrs);
    assert.equal(f.w.getComputedStyle(f.launch).display, 'none');
    assert.equal(f.w.getComputedStyle(f.panel).display, 'none');
    assert.equal(f.panel.classList.contains('is-open'), false);
    await f.modal(id, false);
    assert.equal(f.launch.getAttribute('data-hap-suspended'), '0');
    assert.notEqual(f.w.getComputedStyle(f.launch).display, 'none');
    assert.equal(f.panel.classList.contains('is-open'), false, 'unsuspending never launches chat');
    assert.deepEqual(f.calls, []);
  });
}

const invalid = {
  'missing root': f => f.d.querySelector('#harmat-home-family').remove(),
  'duplicate root': f => f.d.body.insertAdjacentHTML('beforeend', '<div id="harmat-home-family"></div>'),
  'missing apartments': f => f.d.querySelector('#hhf-lakasok').remove(),
  'duplicate apartments': f => f.d.body.insertAdjacentHTML('beforeend', '<section id="hhf-lakasok"></section>'),
  'apartments outside root': f => f.d.body.appendChild(f.d.querySelector('#hhf-lakasok')),
  'wrong apartments type': f => f.d.querySelector('#hhf-lakasok').classList.remove('hhf-apartments'),
  'missing heading': f => f.heading.className = 'unknown-heading',
  'duplicate heading': f => f.heading.parentNode.insertAdjacentHTML('beforeend', '<div class="hhf-section-heading"></div>'),
  'heading extra child': f => f.heading.appendChild(f.d.createElement('div')),
  'heading unexpected text': f => f.heading.appendChild(f.d.createTextNode('Unknown action')),
  'heading reversed children': f => f.heading.insertBefore(f.link, f.heading.firstChild),
  'missing title': f => f.d.querySelector('#hhf-apartments-title').remove(),
  'duplicate title': f => f.d.body.insertAdjacentHTML('beforeend', '<h2 id="hhf-apartments-title"></h2>'),
  'wrong title': f => f.d.querySelector('#hhf-apartments-title').setAttribute('data-i18n', 'other'),
  'wrong link destination': f => f.link.setAttribute('href', '/other/'),
  'wrong all apartments marker': f => f.link.querySelector('span').removeAttribute('data-i18n'),
  'duplicate all apartments marker': f => f.heading.children[0].insertAdjacentHTML('beforeend', '<span data-i18n="allApartments"></span>'),
  'missing launch': f => f.launch.remove(),
  'duplicate launch': f => f.d.body.appendChild(f.launch.cloneNode(true)),
  'wrong launch type': f => f.launch.type = 'submit',
  'wrong launch controls': f => f.launch.setAttribute('aria-controls', 'other-panel'),
  'launcher inside homepage': f => f.d.querySelector('#harmat-home-family').appendChild(f.launch),
  'launcher inside panel': f => f.panel.appendChild(f.launch),
  'missing panel': f => f.panel.remove(),
  'duplicate panel': f => f.d.body.appendChild(f.panel.cloneNode(true)),
  'wrong panel class': f => f.panel.className = 'other-panel',
  'panel inside homepage': f => f.d.querySelector('#harmat-home-family').appendChild(f.panel),
  'missing native readiness': f => delete f.w.__harmatLocalAssistantReady,
  'nonboolean native readiness': f => f.w.__harmatLocalAssistantReady = 'true',
  'missing launcher HAP readiness': f => f.launch.removeAttribute('data-hap-ready'),
  'wrong panel HAP readiness': f => f.panel.setAttribute('data-hap-ready', '0'),
  'action group ID collision': f => f.d.body.insertAdjacentHTML('beforeend', '<div id="harmat-home-assistant-actions"></div>'),
  'own attribute collision': f => f.d.body.setAttribute('data-hhai-ready', 'other'),
  'launcher inside a form': f => { const form = f.d.createElement('form'); f.d.body.appendChild(form); form.appendChild(f.launch); },
  'root inside a form': f => { const form = f.d.createElement('form'); f.d.body.appendChild(form); form.appendChild(f.d.querySelector('#harmat-home-family')); }
};
for (const [name, mutate] of Object.entries(invalid)) test(`fail closed without hiding or moving anything: ${name}`, async t => {
  const f = await fixture(t, { mutate });
  assert.equal(f.d.body.innerHTML, f.before);
  assert.equal(f.launch.outerHTML, f.launchHTML);
  assert.equal(f.panel.outerHTML, f.panelHTML);
  assert.deepEqual(f.calls, []);
  assert.equal(f.observers.length, f.observersBefore);
});

for (const point of ['insert-before', 'insert-after', 'link-before', 'link-after', 'launch-before', 'launch-after', 'marker-before', 'marker-after']) {
  test(`partial mutation rollback preserves exact original node positions: ${point}`, async t => {
    const f = await fixture(t, { boot: false });
    const nodeInsert = f.w.Node.prototype.insertBefore, nodeAppend = f.w.Node.prototype.appendChild;
    const elementAttr = f.w.Element.prototype.setAttribute;
    let fired = false;
    const attempt = (target, node, operation) => {
      const key = target === f.heading && node.id === 'harmat-home-assistant-actions' ? 'insert'
        : target.id === 'harmat-home-assistant-actions' && node === f.link ? 'link'
          : target.id === 'harmat-home-assistant-actions' && node === f.launch ? 'launch' : null;
      if (!fired && point === key + '-before') { fired = true; throw Error('injected'); }
      const result = operation();
      if (!fired && point === key + '-after') { fired = true; throw Error('injected'); }
      return result;
    };
    f.w.Node.prototype.insertBefore = function (node, next) { return attempt(this, node, () => nodeInsert.call(this, node, next)); };
    f.w.Node.prototype.appendChild = function (node) { return attempt(this, node, () => nodeAppend.call(this, node)); };
    f.w.Element.prototype.setAttribute = function (name, value) {
      if (this === f.heading && name === 'data-hhai-ready' && !fired && point === 'marker-before') { fired = true; throw Error('injected'); }
      const result = elementAttr.call(this, name, value);
      if (this === f.heading && name === 'data-hhai-ready' && !fired && point === 'marker-after') { fired = true; throw Error('injected'); }
      return result;
    };
    try { f.boot(); } finally {
      f.w.Node.prototype.insertBefore = nodeInsert; f.w.Node.prototype.appendChild = nodeAppend;
      f.w.Element.prototype.setAttribute = elementAttr;
    }
    assert.equal(fired, true);
    assert.equal(f.group, null);
    assert.equal(f.d.body.innerHTML, f.before);
    assert.deepEqual([f.launch.parentNode, f.launch.nextSibling], f.originLaunch);
    assert.deepEqual([f.link.parentNode, f.link.nextSibling], f.originLink);
    assert.equal(f.d.querySelector('.harmat-local-ai-launch'), f.launch);
    assert.deepEqual(f.calls, []);
    f.boot(); assert.equal(f.group.children[1], f.launch, 'safe retry after rollback');
  });
}

test('CSS rule allowlist isolates entry layout and desktop non-fullscreen hero placement', () => {
  const dom = new JSDOM(`<style>${css}</style>`);
  let heroRules = 0, entryRules = 0;
  function inspect(rules, media = '') {
    for (const rule of rules) {
      if (rule.cssRules) { inspect(rule.cssRules, rule.conditionText); continue; }
      for (const selector of rule.selectorText.split(',')) {
        if (selector.startsWith('#harmat-home-family .hhf-hero:not(:fullscreen) ')) {
          heroRules++;
          assert.match(media, /^\(min-width:768px\)/);
          const props = [...Array(rule.style.length)].map((_, i) => rule.style[i]);
          if (selector.endsWith('.hhf-hero-inner')) assert.ok(props.every(p => ['width', 'max-width', 'justify-content', 'padding'].includes(p)));
          else if (selector.endsWith('.hhf-hero-copy')) {
            assert.equal(media, '(min-width:768px)');
            assert.deepEqual(props, ['position', 'top']);
            assert.equal(rule.style.getPropertyValue('position'), 'relative');
            assert.equal(rule.style.getPropertyValue('top'), '64px');
          } else { assert.ok(selector.endsWith('.hhf-hero-bottom')); assert.deepEqual(props, ['left', 'right']); }
        } else {
          entryRules++;
          assert.match(selector, /^#harmat-home-family #hhf-lakasok /);
          assert.ok(selector.includes('#harmat-home-assistant-actions') || selector.includes('[data-hhai-ready="1"]'));
          if (selector.includes('.harmat-local-ai-launch')) assert.equal(rule.style.getPropertyValue('display'), '', 'never override HAP suspension');
        }
      }
    }
  }
  inspect(dom.window.document.styleSheets[0].cssRules);
  assert.equal(heroRules, 5); assert.equal(entryRules, 9);
  assert.match(css, /min-width:768px\)\{#harmat-home-family \.hhf-hero:not\(:fullscreen\) \.hhf-hero-inner\{width:100%;max-width:none;justify-content:center;padding:28px 48px\}/);
  assert.match(css, /min-width:768px\) and \(max-width:1000px\)/);
  assert.match(css, /padding:28px 24px/);
  assert.match(css, /\.hhf-hero-bottom\{left:48px;right:48px\}/);
  assert.match(css, /\.hhf-hero-bottom\{left:24px;right:24px\}/);
  dom.window.close();
});

test('entry CSS resets fixed positioning and home font/padding/minwidth while allowing 320px wrapping', () => {
  for (const rule of ['position:static!important', 'inset:auto!important', 'z-index:auto!important', 'transform:none!important', 'margin:0!important', 'min-width:0!important', 'max-width:100%!important', 'padding:10px 14px!important', 'font:600 14px/1.3 Arial,sans-serif', 'white-space:normal', 'overflow-wrap:anywhere']) assert.ok(css.includes(rule), rule);
  assert.match(css, /heading\[data-hhai-ready="1"\]>div:first-child\{flex:1 1 300px;min-width:0;max-width:100%\}/);
  assert.match(css, /#harmat-home-assistant-actions\{display:flex;flex:0 1 auto;flex-wrap:wrap;/);
  assert.ok(!css.includes('display:none') && !css.includes('display:flex!important'));
});

test('1.0.1 source differs from pinned c43260f only by version and desktop copy offset', () => {
  const old = execFileSync('git', ['show', 'c43260f:wp-mu-plugins/zz-harmat-home-assistant-inline.php'], { cwd: ROOT, encoding: 'utf8' });
  assert.equal(crypto.createHash('sha256').update(old).digest('hex'), '449ffd8868549b5eba50a15e84efa9883517b828e678b96b987ca9ebcc1a9f71');
  const boundary = 'padding:28px 48px}#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-bottom';
  const insertion = 'padding:28px 48px}#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-copy{position:relative;top:64px}#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-bottom';
  assert.equal(old.split(boundary).length, 2);
  assert.equal(source.replaceAll('\r\n', '\n'), old.replace('Version: 1.0.0', 'Version: 1.0.1').replace(boundary, insertion));
});

test('source forbids new handlers, translations, chat writes, requests, observers and resource/video changes', () => {
  assert.ok(!/cloneNode|innerHTML|outerHTML|replaceChildren|textContent\s*=|\.click\(|dispatchEvent|MutationObserver|setTimeout|setInterval|fetch\(|XMLHttpRequest|WebSocket|EventSource|sendBeacon|Storage|localStorage|sessionStorage|\.value\s*=|\.src\s*=|\.play\(|\.pause\(/.test(script));
  assert.deepEqual([...script.matchAll(/addEventListener\(([^,]+)/g)].map(m => m[1]), ["'DOMContentLoaded'"]);
  assert.ok(!/\b(?:data-hap-suspended|data-harmat-language|data-harmat-ai-lang)\b/.test(script));
  assert.ok(!/<(?:form|input|textarea|select|video|iframe|link)\b|\bsrc=|@import|url\(/i.test(assets));
  const copyRule = '#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-copy{position:relative;top:64px}';
  assert.equal(css.split(copyRule).length, 2, 'exactly one allowed desktop copy offset');
  assert.ok(!/\.hhf-hero-(?:copy|actions|video|poster)\b|\bh1\b/.test(css.replace(copyRule, '')), 'no other hero copy/typography/media changes');
  assert.match(source, /add_action\('wp_footer', 'harmat_home_assistant_inline_footer', 1200\)/);
});
