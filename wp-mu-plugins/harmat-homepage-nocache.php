<?php
/**
 * Plugin Name: Harmat Homepage No Cache
 * Description: Temporarily prevents browser/page cache from holding stale homepage HTML after emergency rollback.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_homepage_nocache_headers() {
    if (is_admin() || wp_doing_ajax() || !is_front_page()) {
        return;
    }

    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }

    nocache_headers();
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
add_action('send_headers', 'harmat_homepage_nocache_headers', 999999);
add_action('template_redirect', 'harmat_homepage_nocache_headers', 0);

function harmat_homepage_nocache_wp_headers($headers) {
    if (is_admin() || wp_doing_ajax() || !is_front_page()) {
        return $headers;
    }

    $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate, max-age=0';
    $headers['Pragma'] = 'no-cache';
    $headers['Expires'] = 'Wed, 11 Jan 1984 05:00:00 GMT';
    return $headers;
}
add_filter('wp_headers', 'harmat_homepage_nocache_wp_headers', 999999);
