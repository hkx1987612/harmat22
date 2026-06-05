<?php
/**
 * Plugin Name: Harmat SEO Redirects
 * Description: Keeps outdated public URLs consolidated to the active SEO landing pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }

    $path = isset($_SERVER['REQUEST_URI']) ? (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    $path = '/' . trim($path, '/') . '/';

    $redirects = [
        '/osszes-alaprajz/' => home_url('/lakaskereso/'),
    ];

    if (!isset($redirects[$path])) {
        return;
    }

    wp_safe_redirect($redirects[$path], 301);
    exit;
}, 1);
