<?php
/**
 * Plugin Name: Harmat Home Video Fullscreen
 * Description: Removable desktop hero fullscreen with visually pure video; original playback ownership retained.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

function harmat_home_video_fullscreen_context(): bool {
    if (!function_exists('harmat_home_family_context') || !harmat_home_family_context()
        || !in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'), true)
        || (defined('WP_INSTALLING') && constant('WP_INSTALLING'))
        || (function_exists('is_customize_preview') && is_customize_preview())
        || !in_array(http_response_code(), array(false, 200), true)) return false;
    foreach (array('preview', 'preview_id', 'preview_nonce', 'elementor-preview', 'rest_route', 'feed', 'embed',
        'customize_changeset_uuid', 'customize_theme', 'action', 'wc-ajax') as $key) {
        if (array_key_exists($key, $_GET)) return false;
    }
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if ($path === '' || preg_match('~^/(?:sales|agent|client|customer|ugyfel|lawyer|wp-admin|wp-json|wp-login\.php)(?:/|$)~i', $path)) return false;
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0
            && !preg_match('~^Content-Type:\s*text/html(?:\s*;|\s*$)~i', $header)) return false;
    }
    return true;
}

function harmat_home_video_fullscreen_footer(): void {
    static $emitted = false;
    if ($emitted || !harmat_home_video_fullscreen_context()) return;
    $emitted = true;
    echo <<<'HHVF_ASSETS'
<style id="harmat-home-video-fullscreen-css">
#harmat-home-family #harmat-home-video-fullscreen-control[hidden]{display:none!important}
#harmat-home-family #harmat-home-video-fullscreen-control{position:absolute;top:20px;right:24px;z-index:3}
#harmat-home-family #harmat-home-video-fullscreen-tooltip{position:absolute;right:0;top:54px;width:max-content;max-width:210px;padding:6px 10px;border-radius:3px;background:#fff;color:var(--forest);font-size:12px;line-height:1.5;pointer-events:none;visibility:hidden}
#harmat-home-family #harmat-home-video-fullscreen-button:hover #harmat-home-video-fullscreen-tooltip,#harmat-home-family #harmat-home-video-fullscreen-button:focus-visible #harmat-home-video-fullscreen-tooltip{visibility:visible}
@media(max-width:767px){#harmat-home-family #harmat-home-video-fullscreen-control{display:none!important}}
#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen{position:fixed!important;inset:0!important;width:100vw!important;height:100vh!important;min-width:0!important;min-height:0!important;max-width:none!important;max-height:none!important;aspect-ratio:auto!important;margin:0!important;overflow:hidden;background:#000!important}
#harmat-home-family .hhf-hero[data-hhvf-hero]::backdrop{background:#000}
#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen .hhf-hero-copy,#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen .hhf-media-label,#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen::before{display:none!important}
#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen .hhf-hero-poster,#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen .hhf-hero-video{position:absolute!important;inset:0!important;width:100%!important;height:100%!important;object-fit:contain!important;object-position:center!important;visibility:visible!important;background:#000!important}
#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen .hhf-hero-poster{z-index:0!important}
#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen .hhf-hero-video{z-index:1!important;opacity:0!important;transition:none!important}
#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen .hhf-hero-video[data-revealed="true"]{opacity:1!important}
#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen .hhf-hero-inner{position:absolute!important;inset:0!important;width:100%!important;max-width:none!important;height:100%!important;padding:0!important;pointer-events:none;z-index:2}
#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen .hhf-hero-bottom{left:auto;right:24px;bottom:20px}
#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen .hhf-video-control,#harmat-home-family .hhf-hero[data-hhvf-hero]:fullscreen #harmat-home-video-fullscreen-control{pointer-events:auto}
</style>
<script id="harmat-home-video-fullscreen-js">
(() => {
  'use strict';
  function init() {
    const roots = document.querySelectorAll('#harmat-home-family');
    if (roots.length !== 1 || !window.matchMedia) return;
    const root = roots[0];
    const one = selector => {
      const nodes = root.querySelectorAll(selector);
      return nodes.length === 1 ? nodes[0] : null;
    };
    const hero = one('.hhf-hero');
    const video = one('video.hhf-hero-video');
    const pause = one('.hhf-video-control');
    const enterSource = one('[data-panorama-fullscreen] [data-fullscreen-enter]');
    const exitSource = one('[data-panorama-fullscreen] [data-fullscreen-exit]');
    if (!hero || !video || !pause || !enterSource || !exitSource || !hero.contains(video) || !hero.contains(pause)
        || pause.parentElement !== hero.querySelector('.hhf-hero-bottom')
        || root.closest('form') || pause.closest('form') || video.closest('form')
        || document.querySelector('[data-hhvf-hero]')) return;
    const ids = ['control', 'button', 'tooltip', 'status'].map(name => `harmat-home-video-fullscreen-${name}`);
    if (ids.some(id => document.querySelectorAll(`[id="${id}"]`).length)) return;
    const desktop = window.matchMedia('(min-width: 768px)');
    const control = document.createElement('div');
    control.id = ids[0];
    control.hidden = true;
    const button = document.createElement('button');
    button.id = ids[1];
    button.type = 'button';
    button.className = 'hhf-icon-button';
    button.setAttribute('aria-controls', hero.id);
    button.setAttribute('aria-describedby', ids[2]);
    const icons = [enterSource, exitSource].map((source, index) => {
      const copy = source.cloneNode(true);
      copy.removeAttribute(index === 0 ? 'data-fullscreen-enter' : 'data-fullscreen-exit');
      copy.removeAttribute('hidden');
      copy.removeAttribute('id');
      copy.setAttribute('data-hhvf-icon', index === 0 ? 'enter' : 'exit');
      return copy;
    });
    const tooltip = document.createElement('span');
    tooltip.id = ids[2];
    tooltip.setAttribute('aria-hidden', 'true');
    const status = document.createElement('span');
    status.id = ids[3];
    status.className = 'hhf-sr-only';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    status.setAttribute('aria-atomic', 'true');
    button.append(...icons, tooltip);
    control.append(button, status);
    hero.append(control);
    hero.setAttribute('data-hhvf-hero', '');
    let busy = false;
    let owned = false;
    let wasFullscreen = document.fullscreenElement === hero;
    let failed = false;
    let focusPending = false;
    const text = () => document.documentElement.getAttribute('data-harmat-language') === 'zh-Hans'
      ? { enter: '\u5168\u5c4f\u64ad\u653e\u89c6\u9891', exit: '\u9000\u51fa\u5168\u5c4f', error: '\u65e0\u6cd5\u5207\u6362\u5168\u5c4f\u6a21\u5f0f\uff0c\u8bf7\u91cd\u8bd5\u3002' }
      : { enter: 'Vide\u00f3 teljes k\u00e9perny\u0151n', exit: 'Kil\u00e9p\u00e9s a teljes k\u00e9perny\u0151b\u0151l', error: 'A teljes k\u00e9perny\u0151s m\u00f3d nem v\u00e1lthat\u00f3. Pr\u00f3b\u00e1lja \u00fajra.' };
    const isFullscreen = () => document.fullscreenElement === hero;
    function render() {
      const active = isFullscreen();
      const hidden = !desktop.matches || pause.hidden || typeof hero.requestFullscreen !== 'function'
        || typeof document.exitFullscreen !== 'function'
        || document.fullscreenEnabled === false;
      const disabled = busy || (!!document.fullscreenElement && !active);
      const labels = text();
      const label = active ? labels.exit : labels.enter;
      const message = failed ? labels.error : '';
      if (control.hidden !== hidden) control.hidden = hidden;
      if (button.disabled !== disabled) button.disabled = disabled;
      if (button.getAttribute('aria-label') !== label) button.setAttribute('aria-label', label);
      if (button.title !== label) button.title = label;
      if (tooltip.textContent !== label) tooltip.textContent = label;
      if (status.textContent !== message) status.textContent = message;
      if (icons[0].hasAttribute('hidden') !== active) icons[0].toggleAttribute('hidden', active);
      if (icons[1].hasAttribute('hidden') !== !active) icons[1].toggleAttribute('hidden', !active);
    }
    function restoreFocus() {
      if (!focusPending || busy) return;
      focusPending = false;
      if (!control.hidden && !button.disabled && button.isConnected) button.focus({ preventScroll: true });
    }
    function syncFullscreen() {
      const active = isFullscreen();
      if (owned && wasFullscreen && !active) {
        owned = false;
        failed = false;
        focusPending = true;
      }
      wasFullscreen = active;
      render();
      restoreFocus();
    }
    button.addEventListener('click', async () => {
      if (busy || control.hidden || button.disabled) return;
      const entering = !isFullscreen();
      busy = true;
      failed = false;
      owned = true;
      render();
      try {
        if (entering) await hero.requestFullscreen();
        else await document.exitFullscreen();
        syncFullscreen();
        if (entering !== isFullscreen()) {
          failed = true;
          focusPending = true;
          if (entering) owned = false;
        }
      } catch {
        failed = true;
        focusPending = true;
        if (!isFullscreen()) owned = false;
      } finally {
        busy = false;
        render();
        restoreFocus();
      }
    });
    document.addEventListener('fullscreenchange', syncFullscreen);
    desktop.addEventListener('change', render);
    const observer = new MutationObserver(render);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-harmat-language'] });
    observer.observe(pause, { attributes: true, attributeFilter: ['hidden'] });
    render();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
</script>
HHVF_ASSETS;
}
add_action('wp_footer', 'harmat_home_video_fullscreen_footer', 100);
