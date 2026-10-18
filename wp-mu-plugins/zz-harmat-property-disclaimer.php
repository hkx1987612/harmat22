<?php
/**
 * Plugin Name: Harmat Property Disclaimer
 * Description: Validated in-body property notice; legacy fallback remains removable.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_property_disclaimer_scope() {
    foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
        if (defined($flag) && constant($flag)) { return false; }
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'), true)
        || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed()
        || is_preview() || is_404() || is_search() || is_embed() || is_trackback()
        || !is_main_query() || !is_singular('property')
        || (function_exists('wp_is_json_request') && wp_is_json_request())) {
        return false;
    }
    foreach (array('preview', 'preview_id', 'preview_nonce', 'elementor-preview', 'rest_route', 'feed', 'embed') as $key) {
        if (array_key_exists($key, $_GET)) { return false; }
    }
    $post = get_queried_object();
    if (!($post instanceof WP_Post) || $post->post_type !== 'property'
        || $post->post_status !== 'publish' || $post->post_password !== ''
        || !preg_match('~\A[a-z0-9]+(?:-[a-z0-9]+)*\z~D', $post->post_name)) {
        return false;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return strpos($uri, '/property/') === 0
        && parse_url($uri, PHP_URL_PATH) === '/property/' . $post->post_name . '/';
}

function harmat_property_disclaimer_html() {
    return '<div class="harmat-property-disclaimer" data-harmat-property-disclaimer="1"><strong>Fontos tájékoztatás:</strong> Az árak kizárólag tájékoztató jellegűek, a végleges ár és fizetési feltételek a szerződésben kerülnek rögzítésre. A látványtervek, videók és képek illusztrációk. Az alapterületekben, terasz- és kertadatokban műszaki vagy mérési eltérés előfordulhat.</div>';
}

// DOM is validation-only. Never serialize or reparent the native document/forms.
function harmat_property_disclaimer_document($html) {
    if (!class_exists('DOMDocument') || !preg_match('~\A\s*<!doctype html>~i', $html)
        || !preg_match('~</html>\s*\z~i', $html)) { return false; }
    $positions = array();
    foreach (array('html', 'head', 'body') as $tag) {
        foreach (array('open' => '<' . $tag . '\b[^>]*>', 'close' => '</' . $tag . '\s*>') as $kind => $pattern) {
            if (preg_match_all('~' . $pattern . '~i', $html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                return false;
            }
            $positions[$tag][$kind] = $matches[0][0][1];
        }
    }
    $p = $positions;
    if (!($p['html']['open'] < $p['head']['open'] && $p['head']['open'] < $p['head']['close']
        && $p['head']['close'] < $p['body']['open'] && $p['body']['open'] < $p['body']['close']
        && $p['body']['close'] < $p['html']['close'])) { return false; }
    $document = new DOMDocument();
    if (!$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        return false;
    }
    foreach (array('html', 'head', 'body') as $tag) {
        if ($document->getElementsByTagName($tag)->length !== 1) { return false; }
    }
    $xpath = new DOMXPath($document);
    if ($xpath->query('//body[contains(concat(" ", normalize-space(@class), " "), " single-property ")]')->length !== 1) {
        return false;
    }
    return array($document, $xpath, $positions);
}

function harmat_property_disclaimer_output($html) {
    if (!is_string($html) || !harmat_property_disclaimer_scope()
        || !in_array(http_response_code(), array(false, 200), true)) { return $html; }
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0
            && !preg_match('~^Content-Type:\s*text/html(?:\s*;|\s*$)~i', $header)) { return $html; }
    }
    $parsed = harmat_property_disclaimer_document($html);
    if (!$parsed) { return $html; }
    list($document, $xpath, $positions) = $parsed;
    $notes = '//div[contains(concat(" ", normalize-space(@class), " "), " harmat-property-disclaimer ")]';
    if ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " harmat-property-disclaimer ")]')->length !== 0
        || $xpath->query('//*[@data-harmat-property-disclaimer]')->length !== 0) { return $html; }
    $footers = $xpath->query('//*[@id="colophon"]');
    $scripts = $xpath->query('//*[@id="harmat-migrated-public-cleanup-js"]');
    $styles = $xpath->query('//head/style[@id="harmat-migrated-public-cleanup-css"]');
    $footer = '<footer id="colophon" class="site-footer harmat-clean-footer" role="contentinfo">';
    $guard = 'if (true /* harmat-property-disclaimer-legacy */) addPropertyDisclaimer();';
    $script_open = '<script id="harmat-migrated-public-cleanup-js">';
    if ($footers->length !== 1 || $scripts->length !== 1 || $styles->length !== 1
        || substr_count($html, $footer) !== 1 || substr_count($html, $guard) !== 1
        || substr_count($html, $script_open) !== 1
        || $footers->item(0)->tagName !== 'footer' || $scripts->item(0)->tagName !== 'script'
        || $scripts->item(0)->parentNode->nodeName !== 'head'
        || strpos($styles->item(0)->textContent, '.harmat-property-disclaimer{') === false) { return $html; }
    $script_at = strpos($html, $script_open);
    $script_end = strpos($html, '</script>', $script_at);
    if ($script_at <= $positions['head']['open'] || $script_end === false
        || $script_end >= $positions['head']['close']) { return $html; }
    $raw_script = substr($html, $script_at, $script_end - $script_at);
    if (substr_count($raw_script, $guard) !== 1) { return $html; }
    // libxml can alter JS string textContent; pin raw function bytes only.
    if (!preg_match('~  function addPropertyDisclaimer\(\) \{[\s\S]*?\n  \}~',
        str_replace("\r\n", "\n", $raw_script), $legacy)
        || hash('sha256', $legacy[0]) !== '110d8f9d1e8e8408906b6b38ad41f2e96accbcce70d38fcba8a9c62f4a418af4') {
        return $html;
    }
    $in_body = false;
    for ($node = $footers->item(0)->parentNode; $node; $node = $node->parentNode) {
        if (in_array($node->nodeName, array('form', 'template', 'noscript', 'svg', 'main'), true)) { return $html; }
        if ($node->nodeName === 'body') { $in_body = true; }
    }
    $at = strpos($html, $footer);
    if (!$in_body || $at <= $positions['body']['open'] || $at >= $positions['body']['close']) { return $html; }
    $note = harmat_property_disclaimer_html();
    $result = substr_replace($html, $note, $at, 0);
    $result = str_replace($guard, 'if (false /* harmat-property-disclaimer-legacy */) addPropertyDisclaimer();', $result);
    $verified = harmat_property_disclaimer_document($result);
    if (!$verified) { return $html; }
    $rendered = $verified[1]->query($notes);
    if ($rendered->length !== 1) { return $html; }
    $next = $rendered->item(0)->nextSibling;
    if (!($next instanceof DOMElement) || $next->tagName !== 'footer'
        || $next->getAttribute('id') !== 'colophon'
        || $rendered->item(0)->getAttribute('data-harmat-property-disclaimer') !== '1') { return $html; }
    return $result;
}

function harmat_property_disclaimer_buffer() {
    if (!harmat_property_disclaimer_scope() || headers_sent()) { return; }
    // Start before migrated cleanup (priority 1); validate its final complete HTML.
    // No wp_footer readiness claim: missing/drifted shell or partial flush keeps legacy.
    $intact = true;
    ob_start(function ($html, $phase) use (&$intact) {
        if (!($phase & PHP_OUTPUT_HANDLER_FINAL) || ($phase & (PHP_OUTPUT_HANDLER_CLEAN | PHP_OUTPUT_HANDLER_FLUSH))) {
            $intact = false;
        }
        return $intact ? harmat_property_disclaimer_output($html) : $html;
    });
}

add_action('template_redirect', 'harmat_property_disclaimer_buffer', -3000);
