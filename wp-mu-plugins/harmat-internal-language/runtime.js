/* Opt-in DOM presentation only. Bootstrap owns dictionary loading and session restore. */
(function (global) {
  'use strict';

  const instances = new WeakMap();
  const storageKey = 'harmat.public.language.v1';
  const actionable = 'a, button, [role="button"]';
  const offerScope = '.hm-lakas-card, .apt-card, .apartment-card, .property, article, .elementor-widget-container';
  const blocked = [
    'head', 'script', 'style', 'noscript', 'template', 'input', 'textarea',
    'svg', 'canvas', 'code', 'pre', 'output', '#wpadminbar',
    '[contenteditable]:not([contenteditable="false"])', '[translate="no"]',
    '.notranslate', '[data-language-protect]', '#harmat-internal-language',
    '#harmat-local-ai-panel', '.harmat-local-ai-launch', '[class^="harmat-local-ai-"]',
    '#harmat-ai-assistant', '#harmat-ai-panel', '[data-harmat-ai-chat]',
    '#harmat22-ai-widget', '#harmat22-ai-root', '#harmat22-ai-widget-root',
    '.fb-customerchat', '.fb_dialog', '#fb-root', '[data-customer-chat]',
    '[data-customerchat]', '[data-harmat-customer-chat]', '[data-harmat-local-ai-body]'
  ].join(',');
  const attributeNames = ['alt', 'aria-label', 'title', 'placeholder'];

  function create(document, dictionary, options) {
    options = options || {};
    if (options.enabled !== true) throw new Error('Explicit enabled: true required');
    if (!document || !document.defaultView || !document.body) {
      throw new Error('A document with a body is required');
    }
    if (!dictionary || typeof dictionary !== 'object' || Array.isArray(dictionary)) {
      throw new Error('An exact text dictionary is required');
    }
    if (instances.has(document)) return instances.get(document);

    const win = document.defaultView;
    const html = document.documentElement;
    const initialLang = html.getAttribute('lang');
    const initialMarker = html.getAttribute('data-harmat-language');
    const entries = new Map(Object.entries(dictionary).filter(entry => typeof entry[1] === 'string'));
    const originals = new WeakMap();
    const attributes = new WeakMap();
    const triggers = new WeakMap();
    const changedText = new Set();
    const changedAttributes = new Set();
    const dynamic = typeof options.translateDynamic === 'function' ? options.translateDynamic : null;
    let language = 'hu';
    let disposed = false;
    let rendering = false;

    let control = document.getElementById('harmat-internal-language');
    const createdControl = !control;
    if (!control) {
      control = document.createElement('div');
      control.id = 'harmat-internal-language';
      control.setAttribute('role', 'group');
      control.setAttribute('aria-label', 'Language / \u8bed\u8a00');
      control.setAttribute('translate', 'no');
      [['hu', 'Magyar'], ['zh-Hans', '\u4e2d\u6587']].forEach(([code, label]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.lang = code;
        button.setAttribute('data-hil-language', code);
        button.setAttribute('aria-pressed', String(code === 'hu'));
        button.textContent = label;
        control.appendChild(button);
      });
      document.body.appendChild(control);
    }
    const buttonState = new Map();
    control.querySelectorAll('button[data-hil-language]').forEach(button => {
      buttonState.set(button, button.getAttribute('aria-pressed'));
    });

    function permitted(element) {
      if (!element || !document.body.contains(element) || element.closest(blocked)) return false;
      const option = element.closest('option');
      return !option || option.hasAttribute('value');
    }

    function normalize(value) {
      value = String(value || '').toLowerCase();
      try { value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch (error) {}
      return value;
    }

    function permittedAttributes(element) {
      if (permitted(element)) return true;
      // Descriptive attributes can change without touching customer-entered values.
      return !!element && element.matches('textarea,input:not([type="hidden"]):not([type="password"])')
        && permitted(element.parentElement) && !element.matches('[translate="no"],.notranslate,[data-language-protect]');
    }

    // Keep these tests identical to the native head gate, including its ancestor scope.
    function nativeOfferTrigger(node, text) {
      if (!node || !node.matches(actionable)) return false;
      const href = node.getAttribute('href') || node.getAttribute('data-mfp-src') || '';
      text = normalize(text);
      if (href.indexOf('#opal-contactform-popup') !== -1) return true;
      if (href.indexOf('/property/') !== -1 && (text.indexOf('ajanlat') !== -1 || text.indexOf('arajanlat') !== -1)) return true;
      return (text.indexOf('ajanlatot kerek') !== -1 || text.indexOf('arajanlatot kerek') !== -1 || text.indexOf('ajanlatkeres') !== -1)
        && !!node.closest(offerScope);
    }

    function sourceText(element) {
      const walker = document.createTreeWalker(element, win.NodeFilter.SHOW_TEXT);
      let text = '';
      let node;
      while ((node = walker.nextNode())) {
        const state = originals.get(node);
        text += state && node.data === state.last ? state.source : node.data;
      }
      return text;
    }

    function cacheTrigger(element) {
      if (!permitted(element) || !element.matches(actionable)) return;
      const source = sourceText(element);
      if (nativeOfferTrigger(element, source)) triggers.set(element, source);
      else triggers.delete(element);
    }

    function cacheTriggers(root) {
      let element = root.nodeType === 1 ? root : root.parentElement;
      for (let ancestor = element; ancestor; ancestor = ancestor.parentElement) cacheTrigger(ancestor);
      if (root.querySelectorAll) root.querySelectorAll(actionable).forEach(cacheTrigger);
    }

    function sameTokens(source, translated) {
      const tokens = value => JSON.stringify(value.match(/\d+(?:[.,]\d+)*/g) || []);
      const codes = value => JSON.stringify(value.match(/\bA[0-9]+(?:-[A-Z0-9]+-L[0-9]+)?\b/gi) || []);
      return tokens(source) === tokens(translated) && codes(source) === codes(translated);
    }

    function smallDynamic(text) {
      let match = text.match(/^(\d{1,3}(?:[.,]\d{1,2})?) (szoba|h\u00e1l\u00f3)$/);
      if (match) return match[1] + (match[2] === 'szoba' ? ' \u95f4\u623f' : ' \u95f4\u5367\u5ba4');
      match = text.match(/^Tal\u00e1lat:? (\d{1,4}) lak\u00e1s$/);
      if (match) return '\u627e\u5230 ' + match[1] + ' \u5957\u516c\u5bd3';
      match = text.match(/^(\d{1,2})\.? (?:em\.|emelet)$/);
      if (match) return '\u5730\u9762\u4ee5\u4e0a\u7b2c' + match[1] + '\u5c42';
      if (/^(?:Fsz|Fsz\.|F\u00f6ldszint)(?: em\.)?$/.test(text)) return '\u5730\u9762\u5c42';
      return null;
    }

    function translateText(value) {
      if (typeof value !== 'string' || language !== 'zh-Hans') return value;
      const key = value.trim();
      if (!key || /^[\d\s.,%+\-]+$/.test(key)
        || /^(?:Ft|HUF|EUR|\u20ac|m\u00b2|m2|%)$/i.test(key)
        || /^[+\-]?[\d\s.,]+\s*(?:Ft|HUF|EUR|\u20ac|m\u00b2|m2)(?:\s*\/\s*m\u00b2)?$/i.test(key)
        || /^(?:Ft|HUF|EUR|\u20ac)\s*[+\-]?[\d\s.,]+$/i.test(key)
        || /^A[0-9]+(?:-[A-Z0-9]+-L[0-9]+)?$/i.test(key)) return value;
      const start = value.indexOf(key);
      const wrap = translated => value.slice(0, start) + translated + value.slice(start + key.length);
      let translated = null;
      // Live property prose must use a parser, never a stale exact snapshot. Reviewed
      // static dates/legal copy may legitimately change numeric tokens in Chinese.
      const liveProperty = /^Az A[0-9]+-[A-Z0-9]+-L[0-9]+\b/i.test(key);
      if (!liveProperty && entries.has(key)) {
        translated = entries.get(key);
        return translated.trim() ? wrap(translated) : value;
      }
      if (dynamic) {
        try { translated = dynamic(key); } catch (error) { translated = null; }
      }
      if (typeof translated !== 'string' || !translated.trim()) translated = smallDynamic(key);
      if (typeof translated !== 'string' || !translated.trim() || !sameTokens(key, translated)) return value;
      return wrap(translated);
    }

    function stateFor(current, previous, allowed) {
      const state = previous && current === previous.last ? previous : { source: current, last: current };
      state.last = allowed ? translateText(state.source) : state.source;
      return state;
    }

    function renderNode(node) {
      if (node.nodeType === 3) {
        const previous = originals.get(node);
        if (!previous && (!node.data.trim() || !permitted(node.parentElement))) return;
        const state = stateFor(node.data, previous, permitted(node.parentElement));
        originals.set(node, state);
        if (node.data !== state.last) node.data = state.last;
        if (state.source !== state.last) changedText.add(node);
        else changedText.delete(node);
      } else if (node.nodeType === 1) {
        let saved = attributes.get(node);
        const allowed = permittedAttributes(node);
        if (!saved && !allowed) return;
        if (!saved) saved = new Map();
        attributeNames.forEach(name => {
          if (!node.hasAttribute(name)) { saved.delete(name); return; }
          const current = node.getAttribute(name);
          const state = stateFor(current, saved.get(name), allowed);
          saved.set(name, state);
          if (current !== state.last) node.setAttribute(name, state.last);
        });
        if (saved.size) attributes.set(node, saved);
        if (Array.from(saved.values()).some(state => state.source !== state.last)) changedAttributes.add(node);
        else changedAttributes.delete(node);
      }
    }

    function scan(root) {
      renderNode(root);
      const walker = document.createTreeWalker(root, win.NodeFilter.SHOW_ELEMENT | win.NodeFilter.SHOW_TEXT);
      let node;
      while ((node = walker.nextNode())) renderNode(node);
    }

    function rootsFrom(records) {
      const roots = [];
      records.forEach(record => {
        if (record.type === 'childList') roots.push(...record.addedNodes, ...record.removedNodes);
        else roots.push(record.target);
        // A child replacement may change the source of an already translated CTA.
        if (record.type === 'childList') {
          let ancestor = record.target.nodeType === 1 ? record.target : record.target.parentElement;
          while (ancestor) { cacheTrigger(ancestor); ancestor = ancestor.parentElement; }
        }
      });
      return roots;
    }

    function watch() {
      observer.observe(html, {
        subtree: true, childList: true, characterData: true, attributes: true,
        attributeFilter: attributeNames.concat(['class', 'id', 'translate', 'contenteditable', 'value', 'href', 'data-mfp-src', 'role', 'data-language-protect'])
      });
    }

    function apply(roots) {
      if (disposed || rendering) return;
      const pending = observer.takeRecords();
      roots = roots.concat(rootsFrom(pending));
      observer.disconnect();
      rendering = true;
      try {
        const unique = Array.from(new Set(roots)).filter(root => root && (root.nodeType === 1 || root.nodeType === 3));
        const minimal = unique.filter(root => !unique.some(other => other !== root && other.contains(root)));
        minimal.forEach(cacheTriggers);
        minimal.forEach(scan);
        html.setAttribute('lang', language);
        html.setAttribute('data-harmat-language', language);
        control.querySelectorAll('button[data-hil-language]').forEach(button => {
          button.setAttribute('aria-pressed', String(button.getAttribute('data-hil-language') === language));
        });
      } finally {
        rendering = false;
        if (!disposed) watch();
      }
    }

    const observer = new win.MutationObserver(records => apply(rootsFrom(records)));

    function setLanguage(next) {
      if (disposed) throw new Error('Disposed language runtime');
      if (next !== 'hu' && next !== 'zh-Hans') throw new Error('Unsupported language');
      language = next;
      apply([document.body].concat(Array.from(changedText), Array.from(changedAttributes)));
      if (options.storage !== false) {
        try { win.sessionStorage.setItem(storageKey, language); } catch (error) {}
      }
      return language;
    }

    function click(event) {
      if (disposed || event.defaultPrevented) return;
      const target = event.target && (event.target.nodeType === 1 ? event.target : event.target.parentElement);
      if (!target || !target.closest) return;
      const languageButton = target.closest('button[data-hil-language]');
      if (languageButton && control.contains(languageButton)) {
        const next = languageButton.getAttribute('data-hil-language');
        if (next === 'hu' || next === 'zh-Hans') setLanguage(next);
        return;
      }
      if (language !== 'zh-Hans') return;
      const trigger = target.closest(actionable);
      if (!trigger || !permitted(trigger) || nativeOfferTrigger(trigger, trigger.textContent)
        || typeof win.harmatUnifiedOfferOpen !== 'function') return;
      if (trigger.tagName === 'BUTTON' && trigger.form && trigger.type === 'submit') return;
      const source = triggers.get(trigger);
      if (!source || source !== sourceText(trigger) || source === trigger.textContent || !nativeOfferTrigger(trigger, source)) return;
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      win.harmatUnifiedOfferOpen(trigger);
    }

    function restoreAttribute(element, name, value) {
      if (value === null) element.removeAttribute(name);
      else element.setAttribute(name, value);
    }

    const api = Object.freeze({
      setLanguage,
      getLanguage: () => language,
      translateText,
      refresh(root) {
        if (disposed) throw new Error('Disposed language runtime');
        root = root || document.body;
        if (root === document || root === html) root = document.body;
        if (root.ownerDocument !== document) throw new Error('Foreign document root');
        apply([root]);
      },
      destroy() {
        if (disposed) return;
        language = 'hu';
        apply([document.body].concat(Array.from(changedText), Array.from(changedAttributes)));
        disposed = true;
        observer.disconnect();
        document.removeEventListener('click', click, true);
        restoreAttribute(html, 'lang', initialLang);
        restoreAttribute(html, 'data-harmat-language', initialMarker);
        if (createdControl) control.remove();
        else buttonState.forEach((value, button) => restoreAttribute(button, 'aria-pressed', value));
        changedText.clear();
        changedAttributes.clear();
        instances.delete(document);
      }
    });
    document.addEventListener('click', click, true);
    instances.set(document, api);
    apply([document.body]);
    return api;
  }

  global.HarmatInternalLanguage = Object.freeze({ create });
})(typeof window !== 'undefined' ? window : globalThis);
