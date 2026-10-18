<?php
/**
 * Plugin Name: Harmat Assistant Presentation
 * Description: Removable public-only chrome for the existing local assistant.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) { exit; }

function harmat_assistant_presentation_scope() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()
        || (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_CLI') && WP_CLI)
        || !isset($_SERVER['REQUEST_METHOD']) || !in_array($_SERVER['REQUEST_METHOD'], array('GET', 'HEAD'), true)
        || is_feed() || is_embed() || is_search() || is_404() || is_preview()) { return false; }
    $path = rawurldecode((string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH));
    if (preg_match('~^/(?:sales|sales-admin|agent|client|customer|ugyfel|lawyer|wp-admin|wp-json|wp-login\.php)(?:/|$)~i', $path)) { return false; }
    $post = get_queried_object();
    return $post instanceof WP_Post && $post->post_status === 'publish' && $post->post_password === ''
        && in_array($post->post_type, array('page', 'property'), true)
        && !in_array(strtolower((string) $post->post_name), array('sales', 'sales-admin', 'agent', 'client', 'customer', 'ugyfel', 'lawyer'), true);
}

function harmat_assistant_presentation_assets() {
    return array(
        'assistant-presentation.css' => '19f8692b310b83c1ecb0535d7e30846782f7736b5304065382f5f6bc21e4496c',
        'assistant-presentation.js' => '92a67eef8d00ef436270d791f03bfd8dbce06c57e319bde062094d49eca5b9b7',
        'LICENSE-lucide.txt' => 'bde82befa48737eb050cbf8b6011cc803536c892af037e724f06dfb729b3ea3e',
    );
}

function harmat_assistant_presentation_footer() {
    if (!harmat_assistant_presentation_scope()) { return; }
    $directory = __DIR__ . '/harmat-assistant-presentation';
    if (!is_dir($directory) || is_link($directory)) { return; }
    $assets = array();
    foreach (harmat_assistant_presentation_assets() as $name => $hash) {
        $path = $directory . '/' . $name;
        if (!is_file($path) || is_link($path) || !is_readable($path)) { return; }
        $bytes = file_get_contents($path);
        if (!is_string($bytes) || !hash_equals($hash, hash('sha256', $bytes))) { return; }
        $assets[$name] = $bytes;
    }
    // Inline verified assets: no extra resource requests, no partially styled fallback.
    $locale = substr((string) get_locale(), 0, 2);
    if (!in_array($locale, array('hu', 'zh', 'en'), true)) { $locale = 'hu'; }
    // Match the existing full-document pipeline's single final-LF removal.
    foreach (array('assistant-presentation.css', 'assistant-presentation.js') as $name) {
        if (substr($assets[$name], -1) === "\n") { $assets[$name] = substr($assets[$name], 0, -1); }
    }
    echo '<style id="harmat-assistant-presentation-style" data-version="1.0.0">' . $assets['assistant-presentation.css'] . '</style>';
    echo '<script id="harmat-assistant-presentation-script" data-version="1.0.0" data-chat-locale="' . $locale . '">' . $assets['assistant-presentation.js'] . '</script>';
}
add_action('wp_footer', 'harmat_assistant_presentation_footer', 1100);
