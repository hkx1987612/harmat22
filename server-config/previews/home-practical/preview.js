(() => {
  'use strict';
  if (window.lucide) window.lucide.createIcons();
  const buttons = document.querySelectorAll('[data-language]');
  buttons.forEach(button => button.addEventListener('click', () => {
    const language = button.dataset.language;
    document.documentElement.setAttribute('data-harmat-language', language === 'zh' ? 'zh-Hans' : 'hu');
    document.documentElement.lang = language === 'zh' ? 'zh-Hans' : 'hu';
    buttons.forEach(other => other.setAttribute('aria-pressed', String(other === button)));
    document.querySelectorAll('[data-review-hu]').forEach(node => {
      node.textContent = node.getAttribute(`data-review-${language}`);
    });
  }));
  document.querySelectorAll('.preview-menu nav a').forEach(link => link.addEventListener('click', () => {
    document.querySelector('.preview-menu').open = false;
  }));
})();
