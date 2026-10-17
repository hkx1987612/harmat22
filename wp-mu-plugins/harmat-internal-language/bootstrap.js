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
  if (control.hasAttribute('data-hil-booted')) return;
  control.setAttribute('data-hil-booted', 'true');

  function anchorToMenu() {
    if (typeof window.requestAnimationFrame !== 'function') return;
    const selector = '#masthead .headerrow > .e-con-inner > [data-id="58842f8"] '
      + '[data-id="4d7a363"] .elementor-icon-wrapper > a.elementor-icon[href^="#elementor-action"]';
    let frame = 0;
    let trackUntil = 0;
    let observed = [];
    let lastGood = null;
    const resizeObserver = typeof ResizeObserver === 'function' ? new ResizeObserver(schedule) : null;
    const headerObserver = new MutationObserver(() => {
      // Follow native hide/show transitions for a bounded interval, then stop.
      trackUntil = performance.now() + 1000;
      schedule();
    });

    function schedule() {
      if (!frame) frame = window.requestAnimationFrame(update);
    }

    function watch(menus) {
      const nodes = new Set();
      menus.forEach(menu => {
        for (let node = menu; node && node !== document.body; node = node.parentElement) {
          nodes.add(node);
          if (node.id === 'masthead') break;
        }
      });
      const next = [...nodes];
      if (next.length === observed.length && next.every((node, i) => node === observed[i])) return;
      headerObserver.disconnect();
      if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver.observe(control);
      }
      observed = next;
      observed.forEach(node => {
        headerObserver.observe(node, { attributes: true, attributeFilter: ['class', 'style', 'hidden', 'aria-hidden'] });
        if (resizeObserver) resizeObserver.observe(node);
      });
    }

    function visible(menu, rect, width, height) {
      if (rect.width <= 0 || rect.height <= 0 || rect.left < 0 || rect.top < 0
        || rect.right > width || rect.bottom > height) return false;
      for (let node = menu; node && node !== document.body; node = node.parentElement) {
        const style = window.getComputedStyle(node);
        if (node.hidden || node.getAttribute('aria-hidden') === 'true'
          || [...node.classList].some(name => /idle[-_]?hidden/i.test(name))
          || style.display === 'none' || style.visibility === 'hidden' || style.visibility === 'collapse'
          || style.opacity === '0') return false;
      }
      return true;
    }

    function state(anchor, reason) {
      if (control.getAttribute('data-hil-anchor') !== anchor) control.setAttribute('data-hil-anchor', anchor);
      if (control.getAttribute('data-hil-anchor-reason') !== reason) control.setAttribute('data-hil-anchor-reason', reason);
    }

    function fallback(reason) {
      state('fallback', reason);
      control.style.removeProperty('--hil-anchor-left');
      control.style.removeProperty('--hil-anchor-top');
      control.removeAttribute('data-hil-layout');
    }

    function setPosition(position) {
      control.style.setProperty('--hil-anchor-left', position.left + 'px');
      control.style.setProperty('--hil-anchor-top', position.top + 'px');
    }

    function hold(menus, width, height, reason) {
      if (!lastGood) {
        fallback(reason);
        return;
      }
      state('held', reason);
      control.setAttribute('data-hil-layout', lastGood.layout);
      const size = control.getBoundingClientRect();
      const style = window.getComputedStyle(control);
      const edge = side => Math.max(4, parseFloat(style.getPropertyValue('--hil-safe-' + side)) || 0);
      const minLeft = edge('left');
      const minTop = edge('top');
      const maxLeft = width - edge('right') - size.width;
      const maxTop = height - edge('bottom') - size.height;
      if (size.width <= 0 || size.height <= 0 || maxLeft < minLeft || maxTop < minTop) {
        fallback('no-space');
        return;
      }
      const left = Math.max(minLeft, Math.min(lastGood.left, maxLeft));
      const top = Math.max(minTop, Math.min(lastGood.top, maxTop));
      const logos = new Set(menus.flatMap(menu => {
        const row = menu.closest('.headerrow');
        const logo = row && row.querySelector('[data-id="298657c"]');
        const images = logo ? [...logo.querySelectorAll('img')] : [];
        return images.length ? images : logo ? [logo] : [];
      }));
      const overlapsLogo = [...logos].some(logo => {
        const rect = logo.getBoundingClientRect();
        return visible(logo, rect, width, height) && left < rect.right && left + size.width > rect.left
          && top < rect.bottom && top + size.height > rect.top;
      });
      if (overlapsLogo) fallback('logo-collision');
      else setPosition({ left, top });
    }

    function update() {
      frame = 0;
      const menus = [...document.querySelectorAll(selector)]
        .filter(menu => !menu.closest('.elementor-sticky__spacer'));
      watch(menus);
      const width = document.documentElement.clientWidth || window.innerWidth;
      const height = window.innerHeight;
      if (!menus.length) {
        hold(menus, width, height, 'no-menu');
      } else {
        const candidates = menus.map(menu => ({ menu, rect: menu.getBoundingClientRect() }))
          .filter(candidate => visible(candidate.menu, candidate.rect, width, height));
        const candidate = candidates.find(item => item.menu.closest('.elementor-sticky--active')) || candidates[0];
        if (!candidate) {
          hold(menus, width, height, 'menu-hidden');
        } else {
          state('menu', 'visible');
          const row = candidate.menu.closest('.headerrow');
          const logo = row.querySelector('[data-id="298657c"]');
          const images = logo ? [...logo.querySelectorAll('img')] : [];
          const logoRects = (images.length ? images : logo ? [logo] : [])
            .map(node => node.getBoundingClientRect()).filter(rect => rect.width > 0 && rect.height > 0);
          function placement(layout) {
            if (control.getAttribute('data-hil-layout') !== layout) control.setAttribute('data-hil-layout', layout);
            const size = control.getBoundingClientRect();
            const left = candidate.rect.right + (width <= 480 ? layout === 'stacked' ? 4 : 6 : 10);
            const top = candidate.rect.top + (candidate.rect.height - size.height) / 2;
            const overlapsLogo = logoRects.some(rect => left < rect.right && left + size.width > rect.left
              && top < rect.bottom && top + size.height > rect.top);
            return { left, top, layout, fits: top >= 0 && top + size.height <= height
              && left + size.width <= width - 4 && !overlapsLogo };
          }
          let position = placement('inline');
          if (!position.fits && width <= 480) position = placement('stacked');
          if (!position.fits) {
            fallback('no-space');
          } else {
            lastGood = position;
            setPosition(position);
          }
        }
      }
      if (performance.now() < trackUntil) schedule();
    }

    function motion(event) {
      if (!observed.includes(event.target)) return;
      if (event.type === 'transitionrun' || event.type === 'animationstart') trackUntil = performance.now() + 1000;
      schedule();
    }

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    window.addEventListener('pageshow', schedule);
    window.addEventListener('load', schedule, { once: true });
    document.addEventListener('DOMContentLoaded', schedule, { once: true });
    document.addEventListener('transitionrun', motion, true);
    document.addEventListener('transitionend', motion, true);
    document.addEventListener('transitioncancel', motion, true);
    document.addEventListener('animationstart', motion, true);
    document.addEventListener('animationend', motion, true);
    document.addEventListener('animationcancel', motion, true);
    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', schedule, { passive: true });
      window.visualViewport.addEventListener('scroll', schedule, { passive: true });
    }
    schedule();
  }
  anchorToMenu();

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
