<?php
/**
 * Plugin Name: Harmat Resource Comfort
 * Description: Keeps lightweight public resource hints aligned with the current public assets.
 * Version: 2026.07.04.1
 */

defined('ABSPATH') || exit;

function harmat_resource_comfort_is_public_request() {
    if (is_admin() || wp_doing_ajax() || wp_is_json_request() || is_feed() || is_robots()) {
        return false;
    }

    $path = isset($_SERVER['REQUEST_URI']) ? trim((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
    return preg_match('~^(sales|agent|client|customer|ugyfel|belepes|sales-admin|lawyer)(/|$)~i', $path) !== 1;
}

function harmat_resource_comfort_filter($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    return str_replace(
        content_url('/uploads/2025/11/Harmat_Logo_250.png'),
        content_url('/uploads/2025/11/cropped-Harmat_Logo_250.png'),
        $html
    );
}

add_action('template_redirect', function () {
    if (!harmat_resource_comfort_is_public_request()) {
        return;
    }

    ob_start('harmat_resource_comfort_filter');
}, 0);
