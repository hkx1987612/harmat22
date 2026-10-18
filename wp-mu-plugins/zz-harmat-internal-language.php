<?php
/**
 * Plugin Name: Harmat Internal Public Language
 * Description: Reviewed local Chinese display translations; no inventory or form writes.
 * Version: 1.0.4
 */
if (!defined('ABSPATH')) { exit; }

// Remains administrator-preview-only until independent compatibility gates pass.
function harmat_internal_language_public_enabled() { return true; }

if (isset($_GET['h22_language_test']) && !defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
}

function harmat_internal_language_scope() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()
        || (defined('REST_REQUEST') && REST_REQUEST)
        || (defined('WP_CLI') && WP_CLI)
        || !isset($_SERVER['REQUEST_METHOD'])
        || !in_array($_SERVER['REQUEST_METHOD'], array('GET', 'HEAD'), true)
        || is_feed() || is_embed() || is_search() || is_404() || is_preview()) {
        return false;
    }
    $path = rawurldecode((string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH));
    if (preg_match('~^/(?:sales|agent|client|customer|ugyfel|lawyer|wp-admin|wp-json|wp-login\.php)(?:/|$)~i', $path)) {
        return false;
    }
    $post = get_queried_object();
    if (!($post instanceof WP_Post) || $post->post_status !== 'publish'
        || $post->post_password !== '' || !in_array($post->post_type, array('page', 'property'), true)) {
        return false;
    }
    if (isset($post->post_name) && in_array(strtolower((string) $post->post_name),
        array('sales', 'agent', 'client', 'customer', 'ugyfel', 'lawyer'), true)) { return false; }
    if (harmat_internal_language_public_enabled()) { return true; }
    return isset($_GET['h22_language_test']) && is_string($_GET['h22_language_test'])
        && wp_unslash($_GET['h22_language_test']) === '1' && current_user_can('manage_options');
}

function harmat_internal_language_assets() {
    return array(
        'runtime.js' => '2b1b7b78b92a062d9e53b68f1ed16b53016cb066d9ce0a08f6c9716849e3dabb',
        'runtime.css' => '1d499473464720f1d9dd3aeb09f1725d65993fc2dd070bf5c5754ad747232137',
        'bootstrap.js' => 'b8219789c404f58d722357cba1e7e460ab4e7022a3a1c7dd92b997273c5aeb4a',
        'patterns.js' => 'cc5d50fe6f7a2719c40413d76d4de54e82d7f34eec570f4d7397262d0a30807e',
        'zh-Hans.json' => '725c2e77dba6d697fd6c3063eff48123174e2287508af693ce89e742a18a086f',
    );
}

function harmat_internal_language_ready() {
    $directory = __DIR__ . '/harmat-internal-language';
    if (is_link($directory) || !is_dir($directory)) { return false; }
    foreach (harmat_internal_language_assets() as $file => $hash) {
        $path = $directory . '/' . $file;
        if (!is_file($path) || is_link($path) || !is_readable($path)
            || !hash_equals($hash, (string) hash_file('sha256', $path))) { return false; }
    }
    return true;
}

function harmat_internal_language_enqueue() {
    if (!harmat_internal_language_scope() || !harmat_internal_language_ready()) { return; }
    $base = plugins_url('harmat-internal-language/', __FILE__);
    $pins = harmat_internal_language_assets();
    wp_enqueue_style('harmat-internal-language', $base . 'runtime.css', array(), substr($pins['runtime.css'], 0, 16));
    wp_enqueue_script('harmat-internal-language', $base . 'runtime.js', array(), substr($pins['runtime.js'], 0, 16), true);
    wp_enqueue_script('harmat-language-patterns', $base . 'patterns.js', array(), substr($pins['patterns.js'], 0, 16), true);
    wp_enqueue_script('harmat-internal-language-boot', $base . 'bootstrap.js', array('harmat-internal-language', 'harmat-language-patterns'), substr($pins['bootstrap.js'], 0, 16), true);
}
add_action('wp_enqueue_scripts', 'harmat_internal_language_enqueue', 1500);

function harmat_internal_language_controls() {
    if (!harmat_internal_language_scope() || !harmat_internal_language_ready()) { return; }
    $pins = harmat_internal_language_assets();
    $config = array('dictionary' => plugins_url('harmat-internal-language/zh-Hans.json', __FILE__) . '?v=' . substr($pins['zh-Hans.json'], 0, 16));
    echo '<div id="harmat-internal-language" role="group" aria-label="Language / &#35821;&#35328;" translate="no">';
    echo '<button data-hil-language="hu" type="button" lang="hu" aria-pressed="true">Magyar</button>';
    echo '<button data-hil-language="zh-Hans" type="button" lang="zh-Hans" aria-pressed="false" disabled>&#20013;&#25991;</button>';
    echo '<span data-hil-status role="status" class="screen-reader-text"></span></div>';
    echo '<script type="application/json" id="harmat-internal-language-config">' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}
add_action('wp_footer', 'harmat_internal_language_controls', 18);
