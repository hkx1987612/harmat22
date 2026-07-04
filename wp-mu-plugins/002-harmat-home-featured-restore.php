<?php
/**
 * Plugin Name: Harmat Home Featured Restore
 * Description: Restores the homepage featured apartment section through the existing migrated snippet renderer.
 * Version: 2026.07.04.1
 */

defined('ABSPATH') || exit;

function harmat_home_featured_restore_is_public_home() {
    return !is_admin()
        && !wp_doing_ajax()
        && !wp_is_json_request()
        && !is_feed()
        && !is_robots()
        && is_front_page();
}

function harmat_home_featured_restore_filter($html) {
    if (!harmat_home_featured_restore_is_public_home() || !is_string($html) || $html === '') {
        return $html;
    }

    if (strpos($html, 'class="harmat-home-featured"') !== false) {
        return $html;
    }

    if (!function_exists('hm_migrated_insert_home_featured_apartments')) {
        return $html;
    }

    $filtered = hm_migrated_insert_home_featured_apartments($html);
    if (!is_string($filtered) || $filtered === '' || strlen(trim($filtered)) < 1000) {
        return $html;
    }

    return $filtered;
}

add_action('template_redirect', function () {
    if (!harmat_home_featured_restore_is_public_home()) {
        return;
    }

    ob_start('harmat_home_featured_restore_filter');
}, 0);
