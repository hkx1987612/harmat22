<?php
/**
 * Plugin Name: Harmat Robots Output Guard
 * Description: Keeps robots.txt machine-readable when public HTML cleanup buffers run.
 * Version: 2026.07.04.1
 */

defined('ABSPATH') || exit;

function harmat_robots_output_guard_is_request() {
    $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if ($path === 'robots.txt') {
        return true;
    }

    return function_exists('is_robots') && is_robots();
}

function harmat_robots_output_guard_clean($output) {
    if (!is_string($output) || $output === '') {
        return $output;
    }

    foreach (array('<footer', '<!doctype', '<html') as $marker) {
        $pos = stripos($output, $marker);
        if ($pos !== false) {
            $output = substr($output, 0, $pos);
        }
    }

    return rtrim($output) . "\n";
}

add_action('template_redirect', function () {
    if (!harmat_robots_output_guard_is_request()) {
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8', true);
    }

    ob_start('harmat_robots_output_guard_clean');
}, 0);

add_filter('robots_txt', 'harmat_robots_output_guard_clean', PHP_INT_MAX);
