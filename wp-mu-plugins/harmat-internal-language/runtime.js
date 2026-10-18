/* Opt-in DOM presentation only. Bootstrap owns dictionary loading and session restore. */
(function (global) {
  'use strict';

  const instances = new WeakMap();
  const editedMessages = new WeakSet();
  const storageKey = 'harmat.public.language.v1';
  const actionable = 'a, button, [role="button"]';
  const offerScope = '.hm-lakas-card, .apt-card, .apartment-card, .property, article, .elementor-widget-container';
  const blockedSelectors = [
    'head', 'script', 'style', 'noscript', 'template', 'input', 'textarea',
    'svg', 'canvas', 'code', 'pre', 'output', '#wpadminbar',
    '[contenteditable]:not([contenteditable="false"])', '[translate="no"]',
    '.notranslate', '[data-language-protect]', '#harmat-internal-language',
    '#harmat-local-ai-panel', '.harmat-local-ai-launch', '[class^="harmat-local-ai-"]',
    '#harmat-ai-assistant', '#harmat-ai-panel', '[data-harmat-ai-chat]',
    '#harmat22-ai-widget', '#harmat22-ai-root', '#harmat22-ai-widget-root',
    '.fb-customerchat', '.fb_dialog', '#fb-root', '[data-customer-chat]',
    '[data-customerchat]', '[data-harmat-customer-chat]', '[data-harmat-local-ai-body]'
  ];
  const blocked = blockedSelectors.join(',');
  const launcherBlocked = blockedSelectors.filter(selector => selector !== '.harmat-local-ai-launch'
    && selector !== '[class^="harmat-local-ai-"]').join(',');
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
    let originalMessage = null;
    let ownedMessage = null;

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

    function outsideDisclaimers() {
      return Array.from(html.children).filter(element => element.matches('div.harmat-property-disclaimer'));
    }

    function presentationRoots() {
      return [document.body].concat(outsideDisclaimers());
    }

    function permitted(element) {
      if (!element || element.closest(blocked)) return false;
      if (!document.body.contains(element)) {
        const disclaimer = element.closest('div.harmat-property-disclaimer');
        if (!disclaimer || disclaimer.parentElement !== html || !document.body.matches('.single-property')
          || !/^\/property\/a[1-4]-(?:f|[1-4])-l[0-9]+\/$/.test(win.location.pathname)) return false;
      }
      const option = element.closest('option');
      return !option || option.hasAttribute('value');
    }

    function launcherLabel(element) {
      if (!element || !element.matches('span') || element.childElementCount || element.hasAttribute('aria-hidden')
        || element.closest('[aria-hidden="true"]')
        || Array.from(element.classList).some(name => /avatar/i.test(name)) || element.matches(blocked)) return false;
      const button = element.parentElement;
      return !!button && button.matches('button.harmat-local-ai-launch[type="button"][aria-controls="harmat-local-ai-panel"]')
        && document.body.contains(button) && !button.matches(launcherBlocked)
        && !Array.from(button.classList).some(name => name.indexOf('harmat-local-ai-') === 0 && name !== 'harmat-local-ai-launch')
        && !button.parentElement.closest(blocked) && !button.parentElement.closest('[class*="harmat-local-ai-"]');
    }

    function offerMessage() {
      if (originalMessage) return originalMessage;
      const form = document.getElementById('h22-offer-form');
      const field = document.getElementById('h22-offer-message');
      if (form && form.matches('form') && document.body.contains(form) && permitted(form)
        && field && field.matches('textarea[name="your-message"]') && field.closest('form') === form && field.form === form) {
        originalMessage = field;
      }
      return originalMessage;
    }

    function syncOfferMessage() {
      const field = offerMessage();
      if (!field) return;
      const marker = 'data-h22-auto-message';
      // A dirty draft can equal either generated language. Do not let the native
      // equality check mistake it for a replaceable default on a later opening.
      if (editedMessages.has(field)) {
        if (field.hasAttribute(marker)) field.removeAttribute(marker);
        ownedMessage = null;
        return;
      }
      const form = document.getElementById('h22-offer-form');
      const valid = form && form.matches('form') && document.body.contains(form) && permitted(form)
        && document.getElementById('h22-offer-message') === field && field.matches('textarea[name="your-message"]')
        && field.closest('form') === form && field.form === form
        && permittedAttributes(field);
      const apartment = valid && form.querySelector('select#h22-offer-apartment[name="selected-apartment"]');
      const code = apartment && apartment.form === form ? apartment.value : '';
      const source = /^A[1-4]-(?:F|[1-4])-L[1-9][0-9]*$/.test(code)
        ? 'A ' + code + ' lak\u00e1s ir\u00e1nt \u00e9rdekl\u0151d\u00f6m.' : null;
      // The native owner compares value to its marker before selecting another unit.
      if (ownedMessage) {
        if (field.getAttribute(marker) === ownedMessage.last) {
          const untouched = field.value === ownedMessage.last;
          if (untouched && language === 'zh-Hans' && source === ownedMessage.source) return;
          if (untouched) field.value = ownedMessage.source;
          field.setAttribute(marker, ownedMessage.source);
        }
        ownedMessage = null;
      }
      if (language !== 'zh-Hans' || !source
        || field.getAttribute(marker) !== source || field.value !== source) return;
      const translated = '\u6211\u60f3\u4e86\u89e3 ' + code + ' \u8fd9\u5957\u516c\u5bd3\u3002';
      field.value = translated;
      field.setAttribute(marker, translated);
      ownedMessage = { source, last: translated };
    }

    function messageEdit(event) {
      if (disposed || rendering) return;
      const field = offerMessage();
      if (!field) return;
      if (event.target === field) editedMessages.add(field);
      if (editedMessages.has(field)) apply([]);
    }

    function normalize(value) {
      value = String(value || '').toLowerCase();
      try { value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch (error) {}
      return value;
    }

    function permittedAttributes(element) {
      if (permitted(element)) return true;
      // Descriptive attributes can change without touching customer-entered values.
      return !!element && document.body.contains(element) && element.matches('textarea,input:not([type="hidden"]):not([type="password"])')
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
        const allowed = permitted(node.parentElement) || (launcherLabel(node.parentElement)
          && (previous && node.data === previous.last ? previous.source : node.data).trim() === 'K\u00e9rdezzen'
          && sourceText(node.parentElement).trim() === 'K\u00e9rdezzen' && entries.has('K\u00e9rdezzen'));
        if (!previous && (!node.data.trim() || !allowed)) return;
        const state = stateFor(node.data, previous, allowed);
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
        if (record.target === document.body || record.target === html) roots.push(...outsideDisclaimers());
        // A child replacement may change the source of an already translated CTA.
        if (record.type === 'childList') {
          if (record.target.nodeType === 1 && record.target.matches('span') && record.target.parentElement
            && record.target.parentElement.matches('button.harmat-local-ai-launch')) roots.push(record.target);
          let ancestor = record.target.nodeType === 1 ? record.target : record.target.parentElement;
          while (ancestor) { cacheTrigger(ancestor); ancestor = ancestor.parentElement; }
        }
      });
      return roots;
    }

    function watch() {
      observer.observe(html, {
        subtree: true, childList: true, characterData: true, attributes: true,
        attributeFilter: attributeNames.concat(['class', 'id', 'translate', 'contenteditable', 'value', 'href', 'data-mfp-src', 'role', 'data-language-protect',
          'data-h22-auto-message', 'type', 'aria-controls', 'aria-hidden', 'name', 'form'])
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
        syncOfferMessage();
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
      apply(presentationRoots().concat(Array.from(changedText), Array.from(changedAttributes)));
      if (options.storage !== false) {
        try { win.sessionStorage.setItem(storageKey, language); } catch (error) {}
      }
      return language;
    }

    function click(event) {
      if (!disposed && editedMessages.has(offerMessage())) apply([]);
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
        if (!root || root === document || root === html) { apply(presentationRoots()); return; }
        if (root.ownerDocument !== document) throw new Error('Foreign document root');
        apply([root]);
      },
      destroy() {
        if (disposed) return;
        language = 'hu';
        apply(presentationRoots().concat(Array.from(changedText), Array.from(changedAttributes)));
        disposed = true;
        observer.disconnect();
        document.removeEventListener('click', click, true);
        document.removeEventListener('input', messageEdit, true);
        document.removeEventListener('change', messageEdit, true);
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
    document.addEventListener('input', messageEdit, true);
    document.addEventListener('change', messageEdit, true);
    instances.set(document, api);
    apply(presentationRoots());
    return api;
  }

  global.HarmatInternalLanguage = Object.freeze({ create });
})(typeof window !== 'undefined' ? window : globalThis);
