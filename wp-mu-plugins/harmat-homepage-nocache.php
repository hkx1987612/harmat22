<?php
/**
 * Plugin Name: Harmat Public Page Cache Policy
 * Description: Allows short anonymous caching for the homepage and apartment search with safe invalidation.
 * Version: 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_homepage_nocache_is_target() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = trim((string) parse_url($path, PHP_URL_PATH), '/');

    return is_front_page() || is_page('lakaskereso') || $path === 'lakaskereso';
}

function harmat_homepage_nocache_headers() {
    if (!harmat_homepage_nocache_is_target()) {
        return;
    }

    if (
        !function_exists('harmat_public_cache_comfort_is_public_read')
        || !harmat_public_cache_comfort_is_public_read()
        || is_user_logged_in()
    ) {
        return;
    }

    header_remove('Pragma');
    header_remove('Expires');
    header('Cache-Control: public, max-age=300, stale-while-revalidate=86400', true);
}
add_action('send_headers', 'harmat_homepage_nocache_headers', 999999);
add_action('template_redirect', 'harmat_homepage_nocache_headers', 0);

function harmat_homepage_nocache_wp_headers($headers) {
    if (!harmat_homepage_nocache_is_target()) {
        return $headers;
    }

    if (
        !function_exists('harmat_public_cache_comfort_is_public_read')
        || !harmat_public_cache_comfort_is_public_read()
        || is_user_logged_in()
    ) {
        return $headers;
    }

    $headers['Cache-Control'] = 'public, max-age=300, stale-while-revalidate=86400';
    unset($headers['Pragma'], $headers['Expires']);
    return $headers;
}
add_filter('wp_headers', 'harmat_homepage_nocache_wp_headers', 999999);

function harmat_public_page_cache_property_changed($post_id, $meta_key = '') {
    if (get_post_type((int) $post_id) !== 'property') {
        return;
    }

    if (
        $meta_key !== ''
        && !preg_match('/^(property_|propeerty_|_harmat_)/', (string) $meta_key)
        && $meta_key !== '_thumbnail_id'
    ) {
        return;
    }

    $GLOBALS['harmat_public_page_cache_purge_pending'] = true;
}

function harmat_public_page_cache_property_saved($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    harmat_public_page_cache_property_changed($post_id);
}
add_action('save_post_property', 'harmat_public_page_cache_property_saved', 30, 3);

function harmat_public_page_cache_property_meta_changed($meta_id, $post_id, $meta_key) {
    harmat_public_page_cache_property_changed($post_id, $meta_key);
}
add_action('added_post_meta', 'harmat_public_page_cache_property_meta_changed', 30, 4);
add_action('updated_post_meta', 'harmat_public_page_cache_property_meta_changed', 30, 4);
add_action('deleted_post_meta', 'harmat_public_page_cache_property_meta_changed', 30, 4);

function harmat_public_page_cache_flush_pending() {
    if (empty($GLOBALS['harmat_public_page_cache_purge_pending'])) {
        return;
    }

    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
}
add_action('shutdown', 'harmat_public_page_cache_flush_pending', 9999);
