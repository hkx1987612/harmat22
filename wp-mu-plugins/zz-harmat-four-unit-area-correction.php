<?php
/**
 * Plugin Name: Harmat Four Unit Area Correction
 * Description: Applies the approved 47.83 m2 sales area to the four matching L5 units and their Elementor detail cards.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

const HARMAT_FOUR_UNIT_AREA_VERSION = '2026-07-29-1';
const HARMAT_FOUR_UNIT_SALES_AREA = '47.83';

function harmat_four_unit_area_targets(): array
{
    return array(
        4419 => 'A1-4-L5',
        5212 => 'A2-4-L5',
        5332 => 'A3-4-L5',
        5490 => 'A4-4-L5',
    );
}

function harmat_four_unit_area_update_elementor_raw(string $raw): array
{
    $found = strpos($raw, 'area-total-size') !== false;
    $replacement_count = 0;
    $updated = preg_replace(
        '~(area-total-size[^>]*>\s*)48(?:[.,]9(?:0)?)~u',
        '${1}' . HARMAT_FOUR_UNIT_SALES_AREA,
        $raw,
        -1,
        $replacement_count
    );
    $corrected = is_string($updated)
        && preg_match(
            '~area-total-size[^>]*>\s*47[.,]83~u',
            $updated
        ) === 1;

    return array(
        'found' => $found,
        'changed' => $replacement_count > 0,
        'corrected' => $corrected,
        'data' => is_string($updated) ? $updated : $raw,
    );
}

function harmat_four_unit_area_apply(): void
{
    if (
        (string) get_option('harmat_four_unit_area_version', '')
        === HARMAT_FOUR_UNIT_AREA_VERSION
    ) {
        return;
    }

    $completed = true;
    $changed_posts = array();

    foreach (harmat_four_unit_area_targets() as $post_id => $apartment_code) {
        if (
            get_post_type($post_id) !== 'property'
            || get_the_title($post_id) !== $apartment_code
        ) {
            $completed = false;
            continue;
        }

        $raw_elementor_data = (string) get_post_meta($post_id, '_elementor_data', true);
        $elementor_result = harmat_four_unit_area_update_elementor_raw(
            $raw_elementor_data
        );

        if (!$elementor_result['found'] || !$elementor_result['corrected']) {
            $completed = false;
            continue;
        }

        update_post_meta($post_id, '_harmat_sales_area', HARMAT_FOUR_UNIT_SALES_AREA);

        if ($elementor_result['changed']) {
            update_post_meta(
                $post_id,
                '_elementor_data',
                wp_slash($elementor_result['data'])
            );
        }

        $price = (int) get_post_meta($post_id, 'property_price', true);
        if ($price > 0) {
            update_post_meta(
                $post_id,
                '_harmat_sales_unit_price',
                (string) round($price / (float) HARMAT_FOUR_UNIT_SALES_AREA)
            );
        }

        clean_post_cache($post_id);

        $saved_area = (string) get_post_meta(
            $post_id,
            '_harmat_sales_area',
            true
        );
        $saved_elementor_data = (string) get_post_meta(
            $post_id,
            '_elementor_data',
            true
        );
        $saved_elementor_result = harmat_four_unit_area_update_elementor_raw(
            $saved_elementor_data
        );
        if (
            $saved_area !== HARMAT_FOUR_UNIT_SALES_AREA
            || !$saved_elementor_result['corrected']
        ) {
            $completed = false;
            continue;
        }

        $changed_posts[] = $post_id;
    }

    if (!$completed || count($changed_posts) !== count(harmat_four_unit_area_targets())) {
        return;
    }

    delete_transient('harmat_lakas_redesign_markup_v11');

    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }

    update_option(
        'harmat_four_unit_area_version',
        HARMAT_FOUR_UNIT_AREA_VERSION,
        false
    );
}

add_action('init', 'harmat_four_unit_area_apply', 2);
