<?php
/**
 * Plugin Name: Harmat Assistant Live Public Data
 * Description: Read-only public apartment data and bounded selection context for the local assistant.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;

function harmat_assistant_public_apartments($fallback) {
    if (!function_exists('harmat_sai_property_summary_data')) return array();
    $posts = get_posts(array('post_type' => 'property', 'post_status' => 'publish',
        'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC',
        'update_post_meta_cache' => true, 'update_post_term_cache' => false));
    $rows = array();
    foreach ($posts as $post) {
        $d = harmat_sai_property_summary_data((int) $post->ID);
        if (!preg_match('/^(A[1-4])-(F|[1-9])-L[0-9]+$/i', $d['title'], $code)) continue;
        $price = $d['hide_price'] ? 0 : max(0, (int) $d['price']);
        $pdf = (string) get_post_meta($post->ID, 'property_floorplan', true);
        $rows[] = array(
            'apartment' => strtoupper($d['title']), 'building' => strtoupper($code[1]),
            'floor' => $d['ground'] ? 'Fsz' : $code[2],
            'rooms' => $d['rooms'], 'bedrooms' => $d['bedrooms'],
            'sales_area_m2' => $d['sales_area'], 'outdoor_area_m2' => $d['outdoor'],
            'garden_m2' => $d['ground'] ? $d['outdoor'] : 0,
            'terrace_m2' => $d['ground'] ? 0 : $d['outdoor'],
            'price_huf' => $price,
            'sqm_price_huf' => $price > 0 && $d['sales_area'] > 0 ? (int) round($price / $d['sales_area']) : 0,
            'status' => $d['status'], 'property_url' => get_permalink($post->ID),
            'floorplan_pdf' => esc_url_raw($pdf),
        );
    }
    return $rows;
}
add_filter('harmat_assistant_public_apartments', 'harmat_assistant_public_apartments');

function harmat_assistant_clean_context($input) {
    if (!is_array($input)) return array();
    $clean = array();
    foreach (array('rooms' => array(1, 5), 'budget' => array(1000000, 2000000000),
        'area' => array(1, 1000), 'area_min' => array(1, 1000), 'area_max' => array(1, 1000)) as $key => $range) {
        if (isset($input[$key]) && is_scalar($input[$key]) && is_numeric($input[$key])) {
            $value = (float) $input[$key];
            if (is_finite($value) && $value >= $range[0] && $value <= $range[1]) $clean[$key] = $value;
        }
    }
    if (isset($clean['rooms'])) $clean['rooms'] = (int) $clean['rooms'];
    if (isset($clean['budget'])) $clean['budget'] = (int) $clean['budget'];
    if (isset($input['building']) && is_string($input['building']) && preg_match('/^A[1-4]$/', $input['building'])) $clean['building'] = $input['building'];
    if (isset($input['floor']) && is_scalar($input['floor']) && preg_match('/^(Fsz|[1-9])$/', (string) $input['floor'])) $clean['floor'] = (string) $input['floor'];
    foreach (array('garden', 'terrace', 'cheap', 'ground_floor') as $key) {
        if (isset($input[$key]) && $input[$key] === true) $clean[$key] = true;
    }
    return $clean;
}

function harmat_assistant_merge_context($filters, $prior, $message, $intent) {
    $prior = harmat_assistant_clean_context($prior);
    $reset = preg_match('/uj kereses|new search|start over|重新选房|重新开始|清空条件/u', $message);
    if ($reset) return array($filters, harmat_assistant_clean_context($filters));
    if (!in_array($intent, array('apartment_search', 'recommendation', 'price', 'availability', 'floorplan', 'unknown'), true)
        || ($intent === 'unknown' && !preg_match('/olcsobb|cheaper|便宜|erkely|balcony|阳台|改成|instead|inkabb|barmelyik emelet|any floor|不限楼层|nincs arkorlat|no budget limit|不限预算/u', $message))) {
        return array($filters, $prior);
    }
    $current = harmat_assistant_clean_context($filters);
    if (isset($current['area']) || isset($current['area_min']) || isset($current['area_max'])) {
        unset($prior['area'], $prior['area_min'], $prior['area_max']);
    }
    $merged = array_merge($prior, $current);
    if (preg_match('/olcsobb|cheaper|便宜/u', $message)) $merged['cheap'] = true;
    if (preg_match('/kert nelkul|nem kell kert|no garden|without.*garden|不要花园|不需要花园/u', $message)) {
        unset($merged['garden'], $merged['ground_floor']);
        $filters['garden'] = $filters['ground_floor'] = false;
    }
    if (preg_match('/erkely nelkul|terasz nelkul|no balcony|without.*(?:balcony|terrace)|不要阳台|不要露台/u', $message)) {
        unset($merged['terrace']); $filters['terrace'] = false;
    }
    if (preg_match('/barmelyik emelet|any floor|不限楼层/u', $message)) {
        unset($merged['floor'], $merged['ground_floor'], $merged['garden']);
        $filters['floor'] = null; $filters['ground_floor'] = $filters['garden'] = false;
    }
    if (isset($current['floor']) && $current['floor'] !== 'Fsz') {
        unset($merged['ground_floor'], $merged['garden']);
        $filters['ground_floor'] = $filters['garden'] = false;
    }
    if (preg_match('/nincs arkorlat|no budget limit|不限预算/u', $message)) {
        unset($merged['budget']); $filters['budget'] = null;
    }
    if ($merged) {
        $filters = array_merge($filters, $merged);
        $filters['has_search'] = true;
    }
    return array($filters, $merged);
}
