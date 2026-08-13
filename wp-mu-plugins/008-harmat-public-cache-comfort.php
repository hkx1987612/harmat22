<?php
/**
 * Plugin Name: Harmat Public Cache Comfort
 * Description: Keeps anonymous public GET requests free of unnecessary EPL sessions.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_public_cache_comfort_request_method() {
    return isset($_SERVER['REQUEST_METHOD'])
        ? strtoupper((string) wp_unslash($_SERVER['REQUEST_METHOD']))
        : '';
}

function harmat_public_cache_comfort_path() {
    $request_uri = isset($_SERVER['REQUEST_URI'])
        ? (string) wp_unslash($_SERVER['REQUEST_URI'])
        : '';

    return trim((string) parse_url($request_uri, PHP_URL_PATH), '/');
}

function harmat_public_cache_comfort_has_logged_in_cookie() {
    foreach (array_keys($_COOKIE) as $name) {
        if (strpos((string) $name, 'wordpress_logged_in_') === 0) {
            return true;
        }
    }

    return false;
}

function harmat_public_cache_comfort_is_public_read() {
    if (!in_array(harmat_public_cache_comfort_request_method(), array('GET', 'HEAD'), true)) {
        return false;
    }

    if (harmat_public_cache_comfort_has_logged_in_cookie()) {
        return false;
    }

    $path = harmat_public_cache_comfort_path();
    if ($path === '') {
        return true;
    }

    return preg_match(
        '~^(wp-admin|wp-login\.php|wp-cron\.php|wp-json|sales|agent|client|customer|ugyfel|belepes|sales-admin|lawyer)(/|$)~i',
        $path
    ) !== 1;
}

function harmat_public_cache_comfort_epl_session($start_session) {
    if (harmat_public_cache_comfort_is_public_read()) {
        return false;
    }

    return $start_session;
}
add_filter('epl_start_session', 'harmat_public_cache_comfort_epl_session', 0);
