<?php
/**
 * Plugin Name: Harmat V4 Inquiry Landing
 * Description: Isolated advertising landing page; existing offer and consent owners retained.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

function harmat_v4_landing_context() {
    foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
        if (defined($flag) && constant($flag)) return false;
    }
    return !is_admin() && in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'), true)
        && parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/uj-otthonok/'
        && !isset($_GET['elementor-preview']) && !isset($_GET['preview']);
}

function harmat_v4_landing_assets() {
    $dir = __DIR__ . '/harmat-v4-landing';
    $file = $dir . '/manifest.json';
    if (!is_file($file) || is_link($file) || is_link($dir)) return false;
    $pins = json_decode(file_get_contents($file), true);
    if (!is_array($pins) || array_keys($pins) !== array('template.php', 'body.html', 'landing.css', 'landing.js', 'lucide.js', 'LICENSE-lucide.txt')) return false;
    foreach ($pins as $name => $pin) {
        if (!is_string($pin) || !preg_match('/\A[a-f0-9]{64}\z/D', $pin)
            || !is_file($dir . '/' . $name) || is_link($dir . '/' . $name)
            || hash_file('sha256', $dir . '/' . $name) !== $pin) return false;
    }
    return $pins;
}

function harmat_v4_landing_route() {
    if (!harmat_v4_landing_context() || !harmat_v4_landing_assets()
        || !function_exists('harmat_mpi_apartments')
        || !function_exists('harmat_unified_offer_modal_footer')) return;
    global $wp_query;
    $wp_query->is_404 = false;
    status_header(200);
    nocache_headers();
    header('X-Robots-Tag: noindex, follow', true);
    header('X-Content-Type-Options: nosniff', true);
    remove_action('wp_head', 'rel_canonical');
    add_filter('pre_get_document_title', static function () {
        return 'Harmat Lakópark | Alaprajzok és személyes ajánlat';
    }, 999);
    add_filter('wp_robots', static function ($robots) {
        unset($robots['index'], $robots['nofollow']);
        $robots['noindex'] = true;
        return $robots;
    }, 999);
    require __DIR__ . '/harmat-v4-landing/template.php';
    exit;
}
add_action('template_redirect', 'harmat_v4_landing_route', -1);
