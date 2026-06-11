<?php
/**
 * Plugin Name: Harmat SEO Index Cleanup
 * Description: SEO-only redirects and sitemap cleanup for legacy property templates.
 * Version: 2026.06.10.1
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_seo_index_request_path() {
    return trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
}

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return;
    }

    $path = harmat_seo_index_request_path();

    if ($path === 'property' || $path === 'apartment' || strpos($path, 'apartment/') === 0) {
        wp_safe_redirect(home_url('/lakaskereso/'), 301);
        exit;
    }

    if ($path === 'kornyekunk') {
        wp_safe_redirect(home_url('/harmat-lakopark-kornyeke/'), 301);
        exit;
    }
}, 0);

add_filter('wpseo_sitemap_exclude_post_type', function ($excluded, $post_type) {
    if ($post_type === 'osf_property') {
        return true;
    }

    return $excluded;
}, 10, 2);

add_filter('wpseo_robots', function ($robots) {
    if (is_post_type_archive('osf_property') || is_singular('osf_property')) {
        return 'noindex,follow';
    }

    return $robots;
});

add_filter('wpseo_sitemap_entry', function ($url, $type, $object) {
    if (isset($url['loc']) && untrailingslashit($url['loc']) === untrailingslashit(home_url('/property/'))) {
        return false;
    }

    $hidden_page_ids = array(174, 10513, 10539, 4683, 4704, 4718, 4722, 4726);
    if (is_object($object) && isset($object->ID) && in_array((int) $object->ID, $hidden_page_ids, true)) {
        return false;
    }

    return $url;
}, 10, 3);

add_filter('wpseo_sitemap_post_type_first_links', function ($links, $post_type) {
    if ($post_type !== 'property' || !is_array($links)) {
        return $links;
    }

    return array_values(array_filter($links, function ($link) {
        return !isset($link['loc']) || untrailingslashit($link['loc']) !== untrailingslashit(home_url('/property/'));
    }));
}, 10, 2);

add_filter('wpseo_robots', function ($robots) {
    if (is_page(array(174, 10513, 10539))) {
        return 'noindex,follow';
    }

    return $robots;
}, 20);
