(function () {
  'use strict';
  const control = document.getElementById('harmat-internal-language');
  const configNode = document.getElementById('harmat-internal-language-config');
  if (!control || !configNode || !window.HarmatInternalLanguage || window.harmatInternalLanguage) return;
  let config;
  try {
    config = JSON.parse(configNode.textContent);
    const url = new URL(config.dictionary, window.location.href);
    if (url.origin !== window.location.origin || !url.pathname.endsWith('/harmat-internal-language/zh-Hans.json')) return;
  } catch (_) { return; }
  const chinese = control.querySelector('[data-hil-language="zh-Hans"]');
  const status = control.querySelector('[data-hil-status]');
  let desired = 'hu';
  let loading = null;
  let translator = null;
  chinese.disabled = false;
  async function choose(language) {
    if (language !== 'hu' && language !== 'zh-Hans') return;
    desired = language;
    if (!translator && language === 'hu') return;
    if (!translator) {
      control.setAttribute('aria-busy', 'true');
      status.textContent = '';
      try {
        if (!loading) loading = fetch(config.dictionary, { credentials: 'omit', mode: 'same-origin', cache: 'force-cache' })
          .then(response => {
            if (!response.ok) throw new Error('Local dictionary unavailable');
            return response.json();
          });
        const dictionary = await loading;
        if (!translator) {
          translator = window.HarmatInternalLanguage.create(document, dictionary, {
            enabled: true,
            translateDynamic: window.HarmatLanguagePatterns && window.HarmatLanguagePatterns.translate
          });
          window.harmatInternalLanguage = translator;
        }
      } catch (_) {
        loading = null;
        desired = 'hu';
        status.textContent = '中文暂不可用';
        return;
      } finally { control.removeAttribute('aria-busy'); }
    }
    translator.setLanguage(desired);
  }
  control.addEventListener('click', event => {
    const button = event.target.closest('[data-hil-language]');
    if (button && !translator) choose(button.dataset.hilLanguage);
  });
  try {
    if (sessionStorage.getItem('harmat.public.language.v1') === 'zh-Hans') choose('zh-Hans');
  } catch (_) { /* Storage restriction must not prevent manual switching. */ }
})();
