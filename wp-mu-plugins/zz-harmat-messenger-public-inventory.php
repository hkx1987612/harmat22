<?php
/**
 * Plugin Name: Harmat Messenger Public Inventory
 * Description: Read-only public apartment data; no customer, CRM or Messenger writes.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;

function harmat_mpi_unavailable() {
    return new WP_REST_Response(array(
        'code' => 'harmat_inventory_unavailable',
        'message' => html_entity_decode('A lak&aacute;sadatok jelenleg nem &eacute;rhet&#337;k el.', ENT_QUOTES, 'UTF-8'),
    ), 503, array('Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex'));
}

function harmat_mpi_number($value, $positive) {
    return (is_int($value) || is_float($value)) && is_finite((float) $value)
        && ($positive ? $value > 0 : $value >= 0);
}

function harmat_mpi_public_url($url, $source) {
    if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    $parts = parse_url($url);
    $origin = parse_url($source);
    return is_array($parts) && is_array($origin)
        && in_array($parts['scheme'] ?? '', array('https', 'http'), true)
        && isset($parts['host'], $origin['host'])
        && strtolower($parts['host']) === strtolower($origin['host'])
        && $parts['scheme'] === ($origin['scheme'] ?? '')
        && ($parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80))
            === ($origin['port'] ?? (($origin['scheme'] ?? '') === 'https' ? 443 : 80))
        && !isset($parts['user']) && !isset($parts['pass'])
        && !isset($parts['query']) && !isset($parts['fragment']);
}

function harmat_mpi_apartments() {
    if (!function_exists('harmat_sai_property_summary_data')) return harmat_mpi_unavailable();
    try {
        $source = home_url('/lakaskereso/');
        if (!harmat_mpi_public_url($source, $source)) return harmat_mpi_unavailable();
        // Full posts and ordinary post/meta cache priming; never an IDs-only scan.
        $posts = get_posts(array(
            'post_type' => 'property', 'post_status' => 'publish', 'has_password' => false,
            'numberposts' => 501, 'fields' => 'all', 'orderby' => 'title', 'order' => 'ASC',
            'cache_results' => true, 'update_post_meta_cache' => true,
            'update_post_term_cache' => false, 'suppress_filters' => true,
        ));
        if (!is_array($posts) || count($posts) > 500) return harmat_mpi_unavailable();
        $items = array();
        $codes = array();
        foreach ($posts as $post) {
            if (!($post instanceof WP_Post)) return harmat_mpi_unavailable();
            // Defense in depth even if a query hook supplies nonpublic posts.
            if ($post->post_type !== 'property' || $post->post_status !== 'publish'
                || $post->post_password !== '') continue;
            $d = harmat_sai_property_summary_data((int) $post->ID);
            if (!is_array($d) || !isset($d['title']) || !is_string($d['title'])) {
                return harmat_mpi_unavailable();
            }
            if (preg_match('/\AA[1-4]-(F|[1-9])-L[0-9]+\z/i', $d['title'], $match) !== 1) continue;
            $code = strtoupper($d['title']);
            if (isset($codes[$code])) return harmat_mpi_unavailable();
            foreach (array('rooms', 'sales_area', 'outdoor', 'status', 'hide_price', 'price') as $field) {
                if (!array_key_exists($field, $d)) return harmat_mpi_unavailable();
            }
            if (!harmat_mpi_number($d['rooms'], true)
                || !harmat_mpi_number($d['sales_area'], true)
                || !harmat_mpi_number($d['outdoor'], false)
                || !in_array($d['status'], array('current', 'reserved', 'sold'), true)
                || !is_bool($d['hide_price']) || !is_int($d['price'])) {
                return harmat_mpi_unavailable();
            }
            $url = get_permalink($post->ID);
            if (!harmat_mpi_public_url($url, $source)) return harmat_mpi_unavailable();
            $codes[$code] = true;
            $items[] = array(
                'code' => $code, 'url' => $url, 'building' => substr($code, 0, 2),
                'floor' => strtoupper($match[1]), 'rooms' => $d['rooms'],
                'sales_area_m2' => $d['sales_area'], 'outdoor_area_m2' => $d['outdoor'],
                'status' => $d['status'],
                'price_huf' => !$d['hide_price'] && $d['price'] > 0 ? $d['price'] : null,
            );
        }
        return new WP_REST_Response(array(
            'schema_version' => 1, 'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'source' => $source, 'currency' => 'HUF', 'items' => $items,
        ), 200, array('Cache-Control' => 'public, max-age=60, must-revalidate', 'X-Robots-Tag' => 'noindex'));
    } catch (Throwable $error) {
        // Never echo exception text, post objects, source prices or internal metadata.
        return harmat_mpi_unavailable();
    }
}

function harmat_mpi_register_route() {
    register_rest_route('harmat-public/v1', '/apartments', array(
        'methods' => 'GET', 'callback' => 'harmat_mpi_apartments',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'harmat_mpi_register_route');
