<?php
/**
 * Plugin Name: Harmat Home Assistant Inline
 * Description: Removable homepage assistant entrance and desktop hero copy placement.
 * Version: 1.0.2
 */
defined('ABSPATH') || exit;

function harmat_home_assistant_inline_context(): bool {
    if (!function_exists('harmat_home_family_context') || !harmat_home_family_context()
        || !in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'), true)
        || (function_exists('is_customize_preview') && is_customize_preview())
        || !in_array(http_response_code(), array(false, 200), true)) return false;
    foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST', 'WP_INSTALLING') as $flag) {
        if (defined($flag) && constant($flag)) return false;
    }
    foreach (array('preview', 'preview_id', 'preview_nonce', 'elementor-preview', 'rest_route', 'feed', 'embed',
        'customize_changeset_uuid', 'customize_theme', 'action', 'wc-ajax') as $key) {
        if (array_key_exists($key, $_GET)) return false;
    }
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if ($path === '' || preg_match('~^/(?:sales|sales-admin|agent|client|customer|ugyfel|lawyer|wp-admin|wp-json|wp-login\.php)(?:/|$)~i', $path)) return false;
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0
            && !preg_match('~^Content-Type:\s*text/html(?:\s*;|\s*$)~i', $header)) return false;
    }
    return true;
}

function harmat_home_assistant_inline_footer(): void {
    static $emitted = false;
    if ($emitted || !harmat_home_assistant_inline_context()) return;
    $emitted = true;
    echo <<<'HHAI_ASSETS'
<style id="harmat-home-assistant-inline-style">
#harmat-home-family #hhf-kezdolap #harmat-home-assistant-actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-start;min-width:0;max-width:100%;margin:16px 0 0}
#harmat-home-family #hhf-kezdolap #harmat-home-assistant-actions>.harmat-local-ai-launch[data-hap-ready="1"]{position:static!important;inset:auto!important;z-index:auto!important;transform:none!important;margin:0!important;width:auto!important;min-width:0!important;max-width:100%!important;min-height:44px;padding:8px 10px!important;border:0!important;border-radius:4px;background:rgba(15,33,24,.38)!important;box-shadow:none!important;color:#fff!important;font:500 14px/1.3 Arial,sans-serif;white-space:normal;overflow-wrap:anywhere;text-shadow:0 1px 3px rgba(15,33,24,.55)}
#harmat-home-family #hhf-kezdolap #harmat-home-assistant-actions>.harmat-local-ai-launch[data-hap-ready="1"]:hover{background:rgba(15,33,24,.65)!important}
#harmat-home-family #hhf-kezdolap #harmat-home-assistant-actions>.harmat-local-ai-launch[data-hap-ready="1"]>span{min-width:0}
#harmat-home-family #hhf-kezdolap #harmat-home-assistant-actions>.harmat-local-ai-launch[data-hap-ready="1"] .harmat-local-ai-avatar,#harmat-home-family #hhf-kezdolap #harmat-home-assistant-actions>.harmat-local-ai-launch[data-hap-ready="1"] svg{width:18px;height:18px;flex:0 0 18px}
@media(min-width:768px){#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-inner{width:100%;max-width:none;justify-content:center;padding:28px 48px}#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-copy{position:relative;top:64px}#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-bottom{left:48px;right:48px}}
@media(min-width:768px) and (max-width:1000px){#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-inner{padding:28px 24px}#harmat-home-family .hhf-hero:not(:fullscreen) .hhf-hero-bottom{left:24px;right:24px}}
</style>
<script id="harmat-home-assistant-inline-script">
(() => {
  'use strict';
  function init() {
    const one = (scope, selector) => {
      const nodes = scope.querySelectorAll(selector);
      return nodes.length === 1 ? nodes[0] : null;
    };
    const root = one(document, '#harmat-home-family');
    const section = root && one(root, '#hhf-kezdolap');
    const launch = one(document, '.harmat-local-ai-launch');
    const panel = one(document, '#harmat-local-ai-panel');
    if (!root || !section || !launch || !panel || !root.contains(section)
        || window.__harmatLocalAssistantReady !== true
        || launch.getAttribute('data-hap-ready') !== '1' || panel.getAttribute('data-hap-ready') !== '1'
        || !section.matches('section.hhf-hero')
        || !launch.matches('button[type="button"][aria-controls="harmat-local-ai-panel"]')
        || !panel.matches('section.harmat-local-ai-panel')
        || root.contains(launch) || root.contains(panel) || panel.contains(launch) || launch.contains(panel)
        || root.closest('form') || launch.closest('form') || panel.closest('form')
        || document.querySelector('#harmat-home-assistant-actions, [data-hhai-ready]')) return;
    const copy = one(section, '.hhf-hero-copy');
    const title = one(document, '#hero-title');
    const actions = copy && one(copy, '.hhf-hero-actions');
    if (!copy || copy.children.length !== 2 || !title || title.parentElement !== copy
        || !title.matches('h1') || !actions || actions.parentElement !== copy
        || copy.children[0] !== title || copy.children[1] !== actions || actions.children.length !== 2
        || Array.from(copy.childNodes).some(node => node.nodeType === 3 && node.textContent.trim())
        || !actions.children[0].matches('a.hhf-button[href="https://harmat22.hu/virtualis-lakasvalaszto/"]')
        || !actions.children[1].matches('a.hhf-hero-booking[href="https://harmat22.hu/lakaskereso/"]')
        || copy.closest('form')) return;
    const group = document.createElement('div');
    group.id = 'harmat-home-assistant-actions';
    const origins = [{ node: launch, parent: launch.parentNode, next: launch.nextSibling }];
    try {
      copy.insertBefore(group, actions.nextSibling);
      group.appendChild(launch);
      copy.setAttribute('data-hhai-ready', '1');
    } catch (error) {
      // Restore the same nodes, including their original whitespace-relative positions.
      origins.slice().reverse().forEach(({ node, parent, next }) => {
        if (node.parentNode !== parent || node.nextSibling !== next) parent.insertBefore(node, next);
      });
      copy.removeAttribute('data-hhai-ready');
      group.remove();
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
</script>
HHAI_ASSETS;
}
add_action('wp_footer', 'harmat_home_assistant_inline_footer', 1200);
