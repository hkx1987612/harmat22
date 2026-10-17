/* Local preview only. No automatic production initialization or network calls. */
(function (global) {
  'use strict';
  function create(document, dictionary) {
    if (document.body.getAttribute('data-harmat-language-preview') !== 'local-v1') {
      throw new Error('Explicit isolated preview required');
    }
    const win = document.defaultView;
    const originals = new WeakMap();
    const attributes = new WeakMap();
    const blocked = 'script,style,noscript,textarea,input,code,pre,svg,canvas,' +
      '[contenteditable]:not([contenteditable="false"]),[translate="no"],.notranslate,' +
      '[data-language-protect],#h22-offer-modal,.contactform-content';
    let language = 'hu';
    let disposed = false;
    const observer = new win.MutationObserver(() => apply());
    const known = value => Object.prototype.hasOwnProperty.call(dictionary, value);
    function permitted(element) {
      if (!element || element.closest(blocked)) return false;
      const option = element.closest('option');
      // Without an explicit value, translating option text changes its payload.
      return !option || option.hasAttribute('value');
    }
    function render(current, state) {
      if (!state || current !== state.last) state = { source: current, last: current };
      const key = state.source.trim();
      if (language === 'zh-Hans' && known(key)) {
        const start = state.source.indexOf(key);
        state.last = state.source.slice(0, start) + dictionary[key] + state.source.slice(start + key.length);
      } else state.last = state.source;
      return state;
    }
    function watch() {
      observer.observe(document.documentElement, {
        subtree: true, childList: true, characterData: true, attributes: true,
        attributeFilter: ['alt', 'title', 'aria-label']
      });
    }
    function apply() {
      if (disposed) return;
      observer.disconnect();
      try {
        const walker = document.createTreeWalker(document.documentElement, win.NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
          if (!permitted(node.parentElement) || !node.data.trim()) continue;
          const state = render(node.data, originals.get(node));
          originals.set(node, state);
          if (node.data !== state.last) node.data = state.last;
        }
        document.querySelectorAll('[alt],[title],[aria-label]').forEach(element => {
          if (!permitted(element)) return;
          const saved = attributes.get(element) || {};
          ['alt', 'title', 'aria-label'].forEach(name => {
            if (!element.hasAttribute(name)) return;
            const state = render(element.getAttribute(name), saved[name]);
            saved[name] = state;
            if (element.getAttribute(name) !== state.last) element.setAttribute(name, state.last);
          });
          attributes.set(element, saved);
        });
        document.documentElement.lang = language;
        document.querySelectorAll('[data-hlp-language]').forEach(button => {
          button.setAttribute('aria-pressed', String(button.dataset.hlpLanguage === language));
        });
      } finally { if (!disposed) watch(); }
    }
    function setLanguage(next) {
      if (disposed) throw new Error('Disposed preview');
      if (next !== 'hu' && next !== 'zh-Hans') throw new Error('Unsupported language');
      language = next;
      apply();
    }
    function click(event) {
      const button = event.target.closest('[data-hlp-language]');
      if (button) setLanguage(button.dataset.hlpLanguage);
    }
    document.addEventListener('click', click);
    watch();
    return Object.freeze({
      setLanguage,
      refresh: apply,
      getLanguage: () => language,
      destroy() {
        if (disposed) return;
        setLanguage('hu');
        disposed = true;
        observer.disconnect();
        document.removeEventListener('click', click);
      }
    });
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = { create };
  else global.HarmatLanguagePreview = Object.freeze({ create });
})(typeof window !== 'undefined' ? window : globalThis);
