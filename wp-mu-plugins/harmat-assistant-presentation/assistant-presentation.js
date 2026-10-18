/* Presentation only; native closure remains the sole owner of chat state and requests. */
(function () {
  'use strict';
  var script = document.getElementById('harmat-assistant-presentation-script');
  var panel = document.getElementById('harmat-local-ai-panel');
  var launch = document.querySelector('.harmat-local-ai-launch');
  if (!script || !panel || !launch || panel.hasAttribute('data-hap-ready')
      || window.__harmatLocalAssistantReady !== true || !window.MutationObserver
      || !document.getElementById('harmat-local-assistant-script')
      || !document.getElementById('harmat-assistant-presentation-style')
      || getComputedStyle(document.getElementById('harmat-assistant-presentation-style')).getPropertyValue('--hap-asset-ready').trim() !== '1') return;

  function one(root, selector) { var all = root.querySelectorAll(selector); return all.length === 1 ? all[0] : null; }
  var head = one(panel, '.harmat-local-ai-head');
  var title = head && one(head, 'strong');
  var subtitle = head && one(head, 'small');
  var close = one(panel, 'button.harmat-local-ai-close[type="button"]');
  var language = one(panel, '.harmat-local-ai-lang');
  var body = one(panel, '[data-harmat-local-ai-body]');
  var suggestions = one(panel, '[data-harmat-local-ai-suggestions]');
  var form = one(panel, 'form[data-harmat-local-ai-form]');
  var input = form && one(form, 'input.harmat-local-ai-input[name="message"]');
  var send = form && one(form, 'button.harmat-local-ai-send[type="submit"]');
  var footnote = one(panel, '.harmat-local-ai-footnote');
  var avatar = one(launch, '.harmat-local-ai-avatar[aria-hidden="true"]');
  var label = launch.children.length === 2 ? launch.children[1] : null;
  if (document.querySelectorAll('#harmat-local-ai-panel').length !== 1
      || document.querySelectorAll('.harmat-local-ai-launch').length !== 1
      || !panel.matches('section.harmat-local-ai-panel') || panel.children.length !== 5
      || !head || !title || !subtitle || !close || !language || !body || !suggestions || !form || !input || !send || !footnote
      || panel.children[0] !== head || panel.children[1] !== body || panel.children[2] !== suggestions
      || panel.children[3] !== form || panel.children[4] !== footnote
      || head.children.length !== 2 || title.parentElement !== head.children[0] || subtitle.parentElement !== title.parentElement
      || !head.children[1].matches('.harmat-local-ai-head-tools') || close.parentElement !== head.children[1]
      || language.parentElement !== head.children[1]
      || !launch.matches('button[type="button"][aria-controls="harmat-local-ai-panel"]')
      || !avatar || !label || !label.matches('span') || label.childElementCount || label === avatar
      || language.children.length !== 3 || form.elements.length !== 2 || input.form !== form
      || !footnote.textContent.trim()) return;
  var buttons = ['hu', 'zh', 'en'].map(function (code) { return one(language, 'button[type="button"][data-harmat-ai-lang="' + code + '"]'); });
  if (buttons.some(function (button) { return !button; })) return;

  var copy = {
    hu: { launch: 'K\u00e9rdezzen', title: 'Harmat asszisztens', subtitle: 'Lak\u00e1s, alaprajz \u00e9s id\u0151pont', close: 'Bez\u00e1r\u00e1s', send: 'K\u00e9rd\u00e9s k\u00fcld\u00e9se', input: 'K\u00e9rd\u00e9s', language: 'Besz\u00e9lget\u00e9s nyelve' },
    zh: { launch: '\u54a8\u8be2', title: 'Harmat \u52a9\u624b', subtitle: '\u623f\u6e90\u3001\u6237\u578b\u56fe\u4e0e\u9884\u7ea6', close: '\u5173\u95ed', send: '\u53d1\u9001\u95ee\u9898', input: '\u95ee\u9898', language: '\u5bf9\u8bdd\u8bed\u8a00' },
    en: { launch: 'Ask us', title: 'Harmat assistant', subtitle: 'Apartments, plans & viewings', close: 'Close', send: 'Send question', input: 'Question', language: 'Conversation language' }
  };
  var safety = {
    hu: 'Automatiz\u00e1lt, t\u00e1j\u00e9koztat\u00f3 v\u00e1laszok. Nem hivatalos aj\u00e1nlat, jogi vagy p\u00e9nz\u00fcgyi tan\u00e1csad\u00e1s; a v\u00e9gleges adatokat az \u00e9rt\u00e9kes\u00edt\u00e9s \u00e9s a szerz\u0151d\u00e9s er\u0151s\u00edti meg.',
    zh: '\u81ea\u52a8\u56de\u590d\u4ec5\u4f9b\u53c2\u8003\uff0c\u4e0d\u6784\u6210\u6b63\u5f0f\u62a5\u4ef7\u3001\u6cd5\u5f8b\u6216\u8d22\u52a1\u5efa\u8bae\uff1b\u6700\u7ec8\u4fe1\u606f\u4ee5\u9500\u552e\u56e2\u961f\u786e\u8ba4\u548c\u5408\u540c\u4e3a\u51c6\u3002',
    en: 'Automated, informative answers only. Not an official offer or legal or financial advice; final details are confirmed by the sales team and the contract.'
  };
  // Exact MessageCircle / X / Send nodes from pinned Lucide 0.468.0. No runtime library.
  var icons = {
    chat: ['M7.9 20A9 9 0 1 0 4 16.1L2 22Z'],
    close: ['M18 6 6 18', 'm6 6 12 12'],
    send: ['M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z', 'm21.854 2.147-10.94 10.939']
  };
  function icon(name) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    var attrs = { width: '24', height: '24', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true', focusable: 'false', 'class': 'hap-icon' };
    Object.keys(attrs).forEach(function (key) { svg.setAttribute(key, attrs[key]); });
    icons[name].forEach(function (d) { var path = document.createElementNS(svg.namespaceURI, 'path'); path.setAttribute('d', d); svg.appendChild(path); });
    return svg;
  }
  function lang(value) { value = (value || '').toLowerCase().split(/[-_]/)[0]; return copy[value] ? value : 'hu'; }
  var fallback = lang(script.getAttribute('data-chat-locale'));
  var observers = [], undo = [], stopped = false, savedAttributes = new WeakMap(), transitions = [];
  function attr(node, key, value) {
    if (node.getAttribute(key) === value) return;
    var saved = savedAttributes.get(node);
    if (!saved) { saved = new Set(); savedAttributes.set(node, saved); }
    if (!saved.has(key)) {
      saved.add(key);
      var before = node.getAttribute(key);
      undo.push(function () { if (before === null) node.removeAttribute(key); else node.setAttribute(key, before); });
    }
    node.setAttribute(key, value);
  }
  function text(node, value) { if (node.textContent !== value) node.textContent = value; }
  function remember(node) {
    var children = Array.from(node.childNodes);
    undo.push(function () { node.replaceChildren.apply(node, children); });
  }
  function restore() {
    if (stopped) return;
    stopped = true;
    observers.forEach(function (observer) { observer.disconnect(); });
    panel.removeEventListener('keydown', keydown);
    close.removeEventListener('click', focusLaunch);
    transitions.forEach(function (node) { node.removeEventListener('transitionend', modalChanged); });
    window.removeEventListener('resize', modalChanged);
    undo.reverse().forEach(function (revert) { revert(); });
  }
  function safe(callback) { return function () { if (!stopped) { try { callback(); } catch (error) { restore(); } } }; }
  function observe(node, options, callback) { var observer = new MutationObserver(safe(callback)); observer.observe(node, options); observers.push(observer); }
  function chrome() {
    var active = buttons.filter(function (button) { return button.classList.contains('is-active'); });
    var current = active.length === 1 ? active[0].getAttribute('data-harmat-ai-lang') : fallback;
    var words = copy[current] || copy.hu;
    text(title, words.title); text(subtitle, words.subtitle); text(footnote, safety[current] || safety.hu);
    attr(panel, 'aria-label', words.title); attr(panel, 'lang', current);
    attr(close, 'aria-label', words.close); attr(close, 'title', words.close);
    attr(send, 'aria-label', words.send); attr(send, 'title', words.send);
    attr(input, 'aria-label', words.input); attr(language, 'aria-label', words.language);
    buttons.forEach(function (button) { attr(button, 'aria-pressed', String(button.getAttribute('data-harmat-ai-lang') === current)); });
  }
  function launcher() {
    var page = lang(document.documentElement.getAttribute('data-harmat-language') || document.documentElement.lang);
    text(label, copy[page].launch); attr(launch, 'aria-label', copy[page].launch); attr(launch, 'title', copy[page].launch); attr(label, 'lang', page);
  }
  var watchedModals = new WeakSet();
  var modalIds = ['h22-offer-modal', 'hm-cookie-consent', 'elementor-popup-modal-3527', 'harmat-clean-menu-modal'];
  function visible(node) {
    if (node.hidden || node.getAttribute('aria-hidden') === 'true') return false;
    var style = getComputedStyle(node);
    return style.display !== 'none' && style.visibility !== 'hidden' && node.getClientRects().length > 0;
  }
  function modalChanged() {
    if (stopped) return;
    var blocked = false;
    modalIds.forEach(function (id) {
      var node = document.getElementById(id);
      if (!node) return;
      if (!watchedModals.has(node)) {
        watchedModals.add(node);
        observe(node, { attributes: true, attributeFilter: ['class', 'style', 'hidden', 'aria-hidden', 'open'] }, modalChanged);
        node.addEventListener('transitionend', modalChanged); transitions.push(node);
      }
      if (visible(node)) blocked = true;
    });
    attr(panel, 'data-hap-suspended', blocked ? '1' : '0');
    attr(launch, 'data-hap-suspended', blocked ? '1' : '0');
  }
  function focusLaunch() { if (launch.getAttribute('data-hap-suspended') !== '1') launch.focus({ preventScroll: true }); }
  function keydown(event) {
    if (event.key === 'Escape' && panel.classList.contains('is-open') && panel.getAttribute('data-hap-suspended') !== '1') {
      event.stopPropagation(); close.click();
    }
  }
  try {
    [title, subtitle, label, avatar, close, send, footnote].forEach(remember);
    // This label is already handled by the native bridge; protect it from double ownership.
    attr(label, 'data-language-protect', ''); attr(label, 'translate', 'no');
    avatar.replaceChildren(icon('chat')); close.replaceChildren(icon('close')); send.replaceChildren(icon('send'));
    attr(language, 'role', 'group');
    buttons.forEach(function (button, i) { attr(button, 'lang', ['hu', 'zh', 'en'][i]); attr(button, 'aria-label', ['Magyar', '\u4e2d\u6587', 'English'][i]); attr(button, 'title', ['Magyar', '\u4e2d\u6587', 'English'][i]); });
    attr(body, 'role', 'log'); attr(body, 'aria-live', 'polite'); attr(body, 'aria-relevant', 'additions text');
    chrome(); launcher(); modalChanged();
    observe(language, { subtree: true, attributes: true, attributeFilter: ['class'] }, chrome);
    observe(panel, { attributes: true, attributeFilter: ['class'] }, chrome);
    observe(document.documentElement, { attributes: true, attributeFilter: ['lang', 'data-harmat-language'] }, launcher);
    // Elementor inserts its menu lazily; its existing body class is the bounded discovery signal.
    observe(document.body, { attributes: true, attributeFilter: ['class'] }, modalChanged);
    panel.addEventListener('keydown', keydown); close.addEventListener('click', focusLaunch);
    window.addEventListener('resize', modalChanged);
    attr(panel, 'data-hap-ready', '1'); attr(launch, 'data-hap-ready', '1');
  } catch (error) { restore(); }
})();
