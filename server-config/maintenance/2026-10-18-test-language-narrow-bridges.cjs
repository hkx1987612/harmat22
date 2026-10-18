'use strict';
// Isolated fixtures only: no native owner, server, browser, submission or network.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ROOT = path.resolve(__dirname, '../..');
const { JSDOM, ResourceLoader } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const runtime = fs.readFileSync(path.join(ROOT, 'wp-mu-plugins/harmat-internal-language/runtime.js'), 'utf8');
const ASK = 'K\u00e9rdezzen';
const ASK_ZH = '\u8bf7\u63d0\u95ee';
const HU_NOTE = 'Jogi megjegyz\u00e9s';
const ZH_NOTE = '\u6cd5\u5f8b\u58f0\u660e';
const dictionary = { [ASK]: ASK_ZH, [HU_NOTE]: ZH_NOTE, '\u00dczenet': '\u7559\u8a00' };
const hu = code => 'A ' + code + ' lak\u00e1s ir\u00e1nt \u00e9rdekl\u0151d\u00f6m.';
const zh = code => '\u6211\u60f3\u4e86\u89e3 ' + code + ' \u8fd9\u5957\u516c\u5bd3\u3002';
const MARKER = 'data-h22-auto-message';
const tick = () => new Promise(resolve => setImmediate(resolve));
const check = (name, run) => test(name, { timeout: 4000 }, run);

function formMarkup() {
  return '<div id="modal"><form id="h22-offer-form">'
    + '<select id="h22-offer-apartment" name="selected-apartment">'
    + ['A1-1-L1', 'A3-4-L5', 'A4-F-L12'].map(code => '<option value="' + code + '">' + code + '</option>').join('')
    + '</select><textarea id="h22-offer-message" name="your-message" placeholder="\u00dczenet"></textarea>'
    + '<input name="your-name" value="Customer"><input name="your-date" value="2026-10-20">'
    + '<input type="checkbox" name="privacy-acceptance"><textarea id="other-message" name="your-message">Other draft</textarea>'
    + '</form></div><button id="open" type="button">Open</button>';
}

function launcherMarkup() {
  return '<button class="harmat-local-ai-launch" type="button" aria-controls="harmat-local-ai-panel" aria-expanded="false" title="' + ASK + '">'
    + '<span class="harmat-local-ai-avatar" aria-hidden="true">' + ASK + '<svg><text>' + ASK + '</text></svg></span>'
    + '<span id="launch-label" title="' + ASK + '" aria-label="' + ASK + '">' + ASK + '</span></button>'
    + '<section id="harmat-local-ai-panel" title="' + ASK + '"><p>' + ASK + '</p>'
    + '<textarea placeholder="' + ASK + '">' + ASK + '</textarea><input value="' + ASK + '" placeholder="' + ASK + '"></section>';
}

function fixture(t, options = {}) {
  const network = [];
  class NoNetwork extends ResourceLoader {
    fetch(url) { network.push(url); throw new Error('NETWORK_BLOCKED'); }
  }
  const dom = new JSDOM('<!doctype html><html lang="hu"><head><title>' + HU_NOTE + '</title></head><body class="single-property">'
    + (options.content || '') + '</body></html>', {
    url: 'https://harmat22.hu' + (options.route || '/property/a1-f-l1/'),
    runScripts: 'outside-only', resources: new NoNetwork()
  });
  const w = dom.window;
  const d = w.document;
  for (const name of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource']) {
    w[name] = function () { network.push(name); throw new Error('NETWORK_BLOCKED'); };
  }
  w.navigator.sendBeacon = () => { network.push('beacon'); throw new Error('NETWORK_BLOCKED'); };
  w.HTMLFormElement.prototype.submit = () => { throw new Error('SUBMISSION_BLOCKED'); };
  w.HTMLFormElement.prototype.requestSubmit = () => { throw new Error('SUBMISSION_BLOCKED'); };
  const events = { input: 0, change: 0, submit: 0 };
  Object.keys(events).forEach(type => d.addEventListener(type, event => {
    events[type]++;
    if (type === 'submit') event.preventDefault();
  }, true));
  const errors = [];
  w.addEventListener('error', event => errors.push(event.error || event.message));
  w.eval(runtime);
  const f = { w, d, events, tr: null,
    create() { this.tr = w.HarmatInternalLanguage.create(d, dictionary, { enabled: true, storage: false }); return this.tr; },
    native(code) {
      const field = d.getElementById('h22-offer-message');
      const select = d.getElementById('h22-offer-apartment');
      if (![...select.options].some(option => option.value === code)) select.add(new w.Option(code, code));
      select.value = code;
      const previous = field.getAttribute(MARKER) || '';
      const next = code ? hu(code) : '';
      if (!field.value || field.value === previous) field.value = next;
      field.setAttribute(MARKER, next);
    },
    edit(value, type = 'input') {
      const field = d.getElementById('h22-offer-message');
      field.value = value;
      field.dispatchEvent(new w.Event(type, { bubbles: true }));
    }
  };
  t.after(() => {
    f.tr?.destroy();
    w.close();
    assert.deepEqual(network, []);
    assert.deepEqual(errors, []);
    assert.equal(events.submit, 0);
  });
  if (options.before) options.before(f);
  if (options.create !== false) f.create();
  return f;
}

function outside(f, tag = 'div') {
  const node = f.d.createElement(tag);
  node.className = 'harmat-property-disclaimer';
  node.innerHTML = '<strong>' + HU_NOTE + '</strong><span translate="no">' + HU_NOTE + '</span>'
    + '<div class="notranslate">' + HU_NOTE + '</div><textarea placeholder="' + HU_NOTE + '">' + HU_NOTE + '</textarea>'
    + '<div data-language-protect>' + HU_NOTE + '</div><div id="harmat-local-ai-panel">' + HU_NOTE + '</div>';
  f.d.documentElement.append(node);
  return node;
}

check('preexisting html-child disclaimer: exact node/text identity, blocked descendants and HU/destroy restoration', t => {
  let note;
  const f = fixture(t, { before: f => { note = outside(f); } });
  const strong = note.firstChild;
  const text = strong.firstChild;
  const original = note.innerHTML;
  for (let i = 0; i < 8; i++) {
    f.tr.setLanguage('zh-Hans');
    assert.equal(strong.textContent, ZH_NOTE);
    assert.equal(strong.firstChild, text);
    assert.equal(note.parentElement, f.d.documentElement);
    [...note.children].slice(1).forEach(child => assert.equal(child.textContent, HU_NOTE));
    assert.equal(note.querySelector('textarea').placeholder, HU_NOTE);
    f.tr.setLanguage('hu');
    assert.equal(note.innerHTML, original);
  }
  f.tr.setLanguage('zh-Hans');
  f.tr.destroy();
  assert.equal(note.innerHTML, original);
  assert.equal(f.d.documentElement.lang, 'hu');
});

check('late html-child disclaimer is translated by html observer without moving it', async t => {
  const f = fixture(t);
  f.tr.setLanguage('zh-Hans');
  const note = outside(f);
  await tick();
  assert.equal(note.firstChild.textContent, ZH_NOTE);
  assert.equal(note.parentElement, f.d.documentElement);
  f.tr.setLanguage('hu');
  assert.equal(note.firstChild.textContent, HU_NOTE);
});

for (const route of ['/', '/agent/', '/property/a5-f-l1/', '/property/a1-5-l1/', '/property/a1-F-l1/',
  '/property/a1-f-l1/extra/', '/property/a1-f-l1', '/property/a1-f-l1.php/']) {
  check('outside disclaimer rejected on nonexact route ' + route, async t => {
    const f = fixture(t, { route });
    const note = outside(f);
    f.tr.setLanguage('zh-Hans');
    await tick();
    assert.equal(note.firstChild.textContent, HU_NOTE);
  });
}

check('outside exception requires single-property, DIV, direct html child; head/private nodes stay excluded', async t => {
  const f = fixture(t);
  const note = outside(f);
  const span = outside(f, 'span');
  const wrapper = f.d.createElement('aside');
  const nested = outside(f);
  wrapper.append(nested);
  f.d.documentElement.append(wrapper);
  const headNote = outside(f);
  f.d.head.append(headNote);
  const other = f.d.createElement('div');
  other.textContent = HU_NOTE;
  f.d.documentElement.append(other);
  f.d.body.classList.remove('single-property');
  f.tr.setLanguage('zh-Hans');
  assert.equal(note.firstChild.textContent, HU_NOTE);
  f.d.body.classList.add('single-property');
  await tick();
  assert.equal(note.firstChild.textContent, ZH_NOTE);
  for (const n of [span, nested, headNote]) assert.equal(n.firstChild.textContent, HU_NOTE);
  assert.equal(other.textContent, HU_NOTE);
  assert.equal(f.d.title, HU_NOTE);
  f.d.body.classList.remove('single-property');
  await tick();
  assert.equal(note.firstChild.textContent, HU_NOTE);
});

check('normal body disclaimer remains supported on other routes; protected outside root stays blocked', t => {
  const f = fixture(t, { route: '/', content: '<div class="harmat-property-disclaimer">' + HU_NOTE + '</div>' });
  f.tr.setLanguage('zh-Hans');
  assert.equal(f.d.body.firstChild.textContent, ZH_NOTE);
  const note = outside(f);
  note.setAttribute('translate', 'no');
  f.tr.refresh(f.d);
  assert.equal(note.firstChild.textContent, HU_NOTE);
});

check('launcher exact leaf text only: node/listener/ARIA/avatar/chat/input identity and HU restoration', async t => {
  const f = fixture(t, { content: launcherMarkup() });
  const label = f.d.getElementById('launch-label');
  const button = label.parentElement;
  const text = label.firstChild;
  const panel = f.d.getElementById('harmat-local-ai-panel');
  const panelBefore = panel.outerHTML;
  const avatarBefore = button.firstElementChild.outerHTML;
  const attributes = element => [...element.attributes].map(a => [a.name, a.value]);
  const before = attributes(button);
  let clicks = 0;
  button.addEventListener('click', () => { clicks++; button.setAttribute('aria-expanded', String(clicks % 2 === 1)); });
  f.tr.setLanguage('zh-Hans');
  assert.equal(label.textContent, ASK_ZH);
  assert.equal(label.firstChild, text);
  assert.equal(label.title, ASK);
  assert.equal(label.getAttribute('aria-label'), ASK);
  assert.deepEqual(attributes(button), before);
  assert.equal(button.firstElementChild.outerHTML, avatarBefore);
  assert.equal(panel.outerHTML, panelBefore);
  button.click();
  assert.equal(clicks, 1);
  assert.equal(button.getAttribute('aria-expanded'), 'true');
  f.tr.setLanguage('hu');
  assert.equal(label.textContent, ASK);
  f.tr.setLanguage('zh-Hans');
  f.tr.destroy();
  assert.equal(label.textContent, ASK);
  button.click();
  assert.equal(clicks, 2);
  assert.equal(button.getAttribute('aria-controls'), 'harmat-local-ai-panel');
  await tick();
  assert.deepEqual(f.events, { input: 0, change: 0, submit: 0 });
});

for (const [name, mutate] of [
  ['nested element', label => { label.innerHTML = '<b>' + ASK + '</b>'; }],
  ['aria-hidden true', label => label.setAttribute('aria-hidden', 'true')],
  ['aria-hidden false', label => label.setAttribute('aria-hidden', 'false')],
  ['avatar class', label => { label.className = 'other harmat-local-ai-avatar'; }],
  ['other label', label => { label.textContent = HU_NOTE; }],
  ['protected label', label => label.setAttribute('data-language-protect', '')],
  ['wrong type', label => { label.parentElement.type = 'submit'; }],
  ['missing type', label => label.parentElement.removeAttribute('type')],
  ['wrong controls', label => label.parentElement.setAttribute('aria-controls', 'other')],
  ['protected button', label => label.parentElement.setAttribute('translate', 'no')],
  ['hidden button', label => label.parentElement.setAttribute('aria-hidden', 'true')],
  ['mixed chat class on button', label => label.parentElement.classList.add('harmat-local-ai-panel')],
  ['mixed chat ancestor class', label => { label.parentElement.parentElement.className = 'other harmat-local-ai-panel'; }],
  ['chat ancestor', label => { label.parentElement.parentElement.id = 'harmat-local-ai-panel'; }],
  ['outside body', (label, f) => f.d.documentElement.append(label.parentElement)]
]) {
  check('launcher excludes ' + name, t => {
    const f = fixture(t, { content: launcherMarkup(), before: f => mutate(f.d.getElementById('launch-label'), f) });
    const label = f.d.getElementById('launch-label');
    const before = label.outerHTML;
    f.tr.setLanguage('zh-Hans');
    assert.equal(label.outerHTML, before);
  });
}

check('late launcher and changed qualification restore only its owned label text', async t => {
  const f = fixture(t);
  f.tr.setLanguage('zh-Hans');
  f.d.body.insertAdjacentHTML('beforeend', launcherMarkup());
  await tick();
  const label = f.d.getElementById('launch-label');
  assert.equal(label.textContent, ASK_ZH);
  label.parentElement.setAttribute('aria-controls', 'other');
  await tick();
  assert.equal(label.textContent, ASK);
});

check('inserting a child element revokes the launcher leaf exception without a language toggle', async t => {
  const f = fixture(t, { content: launcherMarkup() });
  const label = f.d.getElementById('launch-label');
  f.tr.setLanguage('zh-Hans');
  const nested = f.d.createElement('b');
  nested.textContent = HU_NOTE;
  label.append(nested);
  await tick();
  assert.equal(label.firstChild.data, ASK);
  assert.equal(nested.textContent, HU_NOTE);
  nested.remove();
  await tick();
  assert.equal(label.textContent, ASK_ZH);
});

check('all blocked descendant types remain excluded inside the outside disclaimer', t => {
  const f = fixture(t);
  const note = outside(f);
  const guards = ['script', 'style', 'noscript', 'template', 'input', 'textarea', 'svg', 'canvas', 'code', 'pre', 'output'];
  guards.forEach(tag => {
    const element = f.d.createElement(tag);
    element.textContent = HU_NOTE;
    element.title = HU_NOTE;
    element.setAttribute('placeholder', HU_NOTE);
    note.append(element);
  });
  const editable = f.d.createElement('div');
  editable.contentEditable = 'true';
  editable.setAttribute('contenteditable', 'true');
  editable.textContent = HU_NOTE;
  note.append(editable);
  const before = [...note.children].slice(1).map(element => element.outerHTML);
  f.tr.setLanguage('zh-Hans');
  assert.equal(note.firstChild.textContent, ZH_NOTE);
  assert.deepEqual([...note.children].slice(1).map(element => element.outerHTML), before);
});

check('default before language selection: repeated HU/ZH, native A1 -> A3 -> F, owned marker and no events', async t => {
  const f = fixture(t, { content: formMarkup(), before: f => f.native('A1-1-L1') });
  const field = f.d.getElementById('h22-offer-message');
  const other = [...f.d.querySelectorAll('input,textarea,select')].filter(n => n !== field);
  const values = other.map(n => [n, n.value, n.checked]);
  for (const code of ['A1-1-L1', 'A3-4-L5', 'A4-F-L12']) {
    f.native(code);
    for (let i = 0; i < 8; i++) {
      const chinese = i % 2 === 0;
      f.tr.setLanguage(chinese ? 'zh-Hans' : 'hu');
      assert.equal(field.value, chinese ? zh(code) : hu(code));
      assert.equal(field.getAttribute(MARKER), field.value);
      assert.equal(f.d.getElementById('h22-offer-message'), field);
    }
    f.tr.setLanguage('zh-Hans');
    await tick();
    assert.equal(field.value, zh(code));
  }
  values.filter(([node]) => node.tagName !== 'SELECT').forEach(([node, value, checked]) => {
    assert.equal(node.value, value);
    assert.equal(node.checked, checked);
  });
  assert.deepEqual(f.events, { input: 0, change: 0, submit: 0 });
  f.tr.destroy();
  assert.equal(field.value, hu('A4-F-L12'));
  assert.equal(field.getAttribute(MARKER), hu('A4-F-L12'));
});

check('language first, late form/marker and later native unit updates are handled without refresh', async t => {
  const f = fixture(t);
  f.tr.setLanguage('zh-Hans');
  f.d.body.insertAdjacentHTML('beforeend', formMarkup());
  await tick();
  const field = f.d.getElementById('h22-offer-message');
  assert.equal(field.value, '');
  for (const code of ['A1-1-L1', 'A3-4-L5', 'A4-F-L12']) {
    f.native(code);
    await tick();
    assert.equal(field.value, zh(code));
    assert.equal(field.getAttribute(MARKER), zh(code));
  }
  assert.deepEqual(f.events, { input: 0, change: 0, submit: 0 });
});

for (const [name, value] of [
  ['HU customer prose', 'Egyedi vev\u0151i sz\u00f6veg'], ['ZH customer prose', '\u8bf7\u8054\u7cfb\u6211'],
  ['exact HU default', hu('A1-1-L1')], ['exact ZH default', zh('A1-1-L1')], ['empty deletion', '']
]) {
  for (const language of ['hu', 'zh-Hans']) {
    check('customer input stays dirty through native selection, toggle, close/reopen, destroy/recreate: ' + name + ' in ' + language, async t => {
      const f = fixture(t, { content: formMarkup(), before: f => f.native('A1-1-L1') });
      const field = f.d.getElementById('h22-offer-message');
      const modal = f.d.getElementById('modal');
      f.tr.setLanguage(language);
      f.edit(value);
      assert.equal(field.value, value);
      assert.equal(field.hasAttribute(MARKER), false);
      f.tr.setLanguage('zh-Hans');
      f.tr.setLanguage('hu');
      assert.equal(field.value, value);
      modal.hidden = true;
      modal.hidden = false;
      f.native('A3-4-L5');
      const expected = value || hu('A3-4-L5'); // Only the native owner repopulates a blank.
      await tick();
      assert.equal(field.value, expected);
      assert.equal(field.hasAttribute(MARKER), false);
      f.tr.setLanguage('zh-Hans');
      f.native('A4-F-L12');
      await tick();
      assert.equal(field.value, expected);
      f.tr.destroy();
      assert.equal(field.value, expected);
      f.create();
      f.tr.setLanguage('zh-Hans');
      f.native('A1-1-L1');
      await tick();
      assert.equal(field.value, expected);
      assert.equal(field.hasAttribute(MARKER), false);
      assert.deepEqual(f.events, { input: 1, change: 0, submit: 0 });
    });
  }
}

check('change capture and click/select capture clear stale dirty markers before native bubble owner', async t => {
  const f = fixture(t, { content: formMarkup(), before: f => f.native('A1-1-L1') });
  const field = f.d.getElementById('h22-offer-message');
  f.tr.setLanguage('zh-Hans');
  f.edit(hu('A1-1-L1'), 'change');
  const open = f.d.getElementById('open');
  open.addEventListener('click', () => f.native('A3-4-L5'));
  field.setAttribute(MARKER, field.value);
  open.click();
  assert.equal(field.value, hu('A1-1-L1'));
  const select = f.d.getElementById('h22-offer-apartment');
  select.addEventListener('change', () => f.native('A4-F-L12'));
  field.setAttribute(MARKER, field.value);
  select.dispatchEvent(new f.w.Event('change', { bubbles: true }));
  assert.equal(field.value, hu('A1-1-L1'));
  await tick();
  assert.equal(field.hasAttribute(MARKER), false);
  assert.deepEqual(f.events, { input: 0, change: 2, submit: 0 });
});

for (const [name, modify] of [
  ['missing marker', f => f.d.getElementById('h22-offer-message').removeAttribute(MARKER)],
  ['invalid marker', f => f.d.getElementById('h22-offer-message').setAttribute(MARKER, hu('A1-1-L1') + ' ')],
  ['foreign draft', f => { f.d.getElementById('h22-offer-message').value = 'Foreign programmatic draft'; }],
  ['value/marker mismatch', f => { f.d.getElementById('h22-offer-message').value = hu('A3-4-L5'); }],
  ['selected-code mismatch', f => { f.d.getElementById('h22-offer-apartment').value = 'A3-4-L5'; }],
  ['wrong form', f => { f.d.getElementById('h22-offer-form').id = 'foreign-form'; }],
  ['wrong field name', f => { f.d.getElementById('h22-offer-message').name = 'foreign-message'; }],
  ['wrong apartment name', f => { f.d.getElementById('h22-offer-apartment').name = 'foreign-apartment'; }],
  ['missing apartment', f => f.d.getElementById('h22-offer-apartment').remove()],
  ['protected ancestor', f => { f.d.getElementById('h22-offer-form').setAttribute('data-language-protect', ''); }],
  ['protected textarea', f => { f.d.getElementById('h22-offer-message').setAttribute('translate', 'no'); }],
  ['foreign form association', f => { f.d.getElementById('h22-offer-message').setAttribute('form', 'missing'); }]
]) {
  check('message bridge fails closed for ' + name, async t => {
    const f = fixture(t, { content: formMarkup(), before: f => { f.native('A1-1-L1'); modify(f); } });
    const field = f.d.getElementById('h22-offer-message');
    const value = field.value;
    const marker = field.getAttribute(MARKER);
    f.tr.setLanguage('zh-Hans');
    await tick();
    f.tr.setLanguage('hu');
    f.tr.destroy();
    assert.equal(field.value, value);
    assert.equal(field.getAttribute(MARKER), marker);
  });
}

for (const code of ['A0-1-L1', 'A5-F-L1', 'A1-0-L1', 'A1-5-L1', 'A1-f-L1', 'A1-F-L0', 'A1-F-L01', 'A1-F-L-1', 'a1-F-L1', 'A1-F-L1 extra', '']) {
  check('message rejects nonnative apartment code ' + JSON.stringify(code), async t => {
    const f = fixture(t, { content: formMarkup(), before: f => f.native(code) });
    const field = f.d.getElementById('h22-offer-message');
    const value = field.value;
    f.tr.setLanguage('zh-Hans');
    await tick();
    assert.equal(field.value, value);
    assert.equal(field.getAttribute(MARKER), code ? hu(code) : '');
  });
}

check('foreign draft replacing an owned ZH value never rewrites; owned marker alone restores on HU/destroy', t => {
  const f = fixture(t, { content: formMarkup(), before: f => f.native('A1-1-L1') });
  const field = f.d.getElementById('h22-offer-message');
  f.tr.setLanguage('zh-Hans');
  field.value = 'Foreign late draft';
  f.tr.setLanguage('hu');
  assert.equal(field.value, 'Foreign late draft');
  assert.equal(field.getAttribute(MARKER), hu('A1-1-L1'));
  f.tr.setLanguage('zh-Hans');
  f.tr.destroy();
  assert.equal(field.value, 'Foreign late draft');
});

check('unowned changed marker prevents restoration of values the runtime can no longer claim', t => {
  const f = fixture(t, { content: formMarkup(), before: f => f.native('A1-1-L1') });
  const field = f.d.getElementById('h22-offer-message');
  f.tr.setLanguage('zh-Hans');
  field.value = 'Foreign late draft';
  field.setAttribute(MARKER, 'Foreign marker');
  f.tr.destroy();
  assert.equal(field.value, 'Foreign late draft');
  assert.equal(field.getAttribute(MARKER), 'Foreign marker');
});

check('reset of untouched default is not translated as a draft; next native default is; destroy/recreate restores ownership', async t => {
  const f = fixture(t, { content: formMarkup(), before: f => f.native('A1-1-L1') });
  const field = f.d.getElementById('h22-offer-message');
  f.tr.setLanguage('zh-Hans');
  f.d.getElementById('h22-offer-form').reset();
  f.tr.refresh();
  assert.equal(field.value, '');
  assert.equal(field.getAttribute(MARKER), hu('A1-1-L1'));
  f.native('A3-4-L5');
  await tick();
  assert.equal(field.value, zh('A3-4-L5'));
  f.tr.destroy();
  assert.equal(field.value, hu('A3-4-L5'));
  f.create();
  f.tr.setLanguage('zh-Hans');
  assert.equal(field.value, zh('A3-4-L5'));
  assert.deepEqual(f.events, { input: 0, change: 0, submit: 0 });
});

check('dirty exact-default identity survives reset/destroy/recreate, even if native repopulates it', async t => {
  const f = fixture(t, { content: formMarkup(), before: f => f.native('A1-1-L1') });
  const field = f.d.getElementById('h22-offer-message');
  f.edit(hu('A1-1-L1'));
  f.d.getElementById('h22-offer-form').reset();
  f.native('A1-1-L1');
  await tick();
  f.tr.destroy();
  f.create();
  f.tr.setLanguage('zh-Hans');
  assert.equal(field.value, hu('A1-1-L1'));
  assert.equal(field.hasAttribute(MARKER), false);
});

check('only the original textarea is owned; replacing it with a clone never adopts the clone', async t => {
  const f = fixture(t, { content: formMarkup(), before: f => f.native('A1-1-L1') });
  const original = f.d.getElementById('h22-offer-message');
  const clone = original.cloneNode(true);
  original.replaceWith(clone);
  f.tr.setLanguage('zh-Hans');
  await tick();
  assert.equal(clone.value, hu('A1-1-L1'));
  assert.equal(clone.getAttribute(MARKER), hu('A1-1-L1'));
});

check('destroy removes capture listeners and observer; later input does not mark a fresh instance dirty', async t => {
  const f = fixture(t, { content: formMarkup(), before: f => f.native('A1-1-L1') });
  const field = f.d.getElementById('h22-offer-message');
  f.tr.destroy();
  f.edit(hu('A1-1-L1'));
  field.setAttribute(MARKER, hu('A1-1-L1'));
  await tick();
  f.create();
  f.tr.setLanguage('zh-Hans');
  assert.equal(field.value, zh('A1-1-L1'));
});

check('dirty textarea has zero runtime value writes during invalidation, detachment, HU restore and destroy/recreate', async t => {
  const f = fixture(t, { content: formMarkup(), before: f => f.native('A1-1-L1') });
  const field = f.d.getElementById('h22-offer-message');
  f.tr.setLanguage('zh-Hans');
  f.edit(zh('A1-1-L1'));
  const descriptor = Object.getOwnPropertyDescriptor(f.w.HTMLTextAreaElement.prototype, 'value');
  const writes = [];
  Object.defineProperty(field, 'value', {
    configurable: true,
    get() { return descriptor.get.call(this); },
    set(value) { writes.push(value); descriptor.set.call(this, value); }
  });
  field.name = 'foreign';
  f.d.getElementById('h22-offer-apartment').remove();
  f.d.getElementById('h22-offer-form').id = 'foreign';
  field.setAttribute(MARKER, field.value);
  await tick();
  f.tr.setLanguage('hu');
  field.remove();
  f.tr.setLanguage('zh-Hans');
  f.tr.destroy();
  f.d.getElementById('foreign').append(field);
  f.d.getElementById('foreign').id = 'h22-offer-form';
  field.name = 'your-message';
  f.create();
  f.tr.setLanguage('zh-Hans');
  f.tr.destroy();
  assert.deepEqual(writes, []);
  assert.equal(field.value, zh('A1-1-L1'));
});
