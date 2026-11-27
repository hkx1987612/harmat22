<?php
/**
 * Plugin Name: Harmat Approved Share Metadata
 * Description: Aligns search and sharing metadata for the reviewed public pages.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_asm_target(): ?WP_Post
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request() || is_feed() || is_search() || is_404() || is_preview() || !is_singular()) {
        return null;
    }

    $post = get_queried_object();
    if (!($post instanceof WP_Post) || $post->post_status !== 'publish' || $post->post_password !== '') {
        return null;
    }

    $allowed = array(
        'page' => array('magunkrol', 'lakaskereso', 'epitesi-naplo'),
        'property' => array('a1-2-l5', 'a3-f-l3', 'a4-f-l1', 'a1-f-l1', 'a2-4-l5', 'a1-1-l6', 'a1-1-l1', 'a3-f-l5'),
    );
    return in_array($post->post_name, $allowed[$post->post_type] ?? array(), true) ? $post : null;
}

function harmat_asm_description($original)
{
    $post = harmat_asm_target();
    if ($post === null) {
        return $original;
    }

    if ($post->post_type === 'page') {
        $copy = array(
            'magunkrol' => 'Harmat Lakópark, Budapest X. kerület, Harmat utca 22. A kőbányai lakópark tervei, első üteme és a projekt szakmai szereplői.',
            'lakaskereso' => 'Új építésű lakások a Harmat Lakóparkban, Kőbányán. Szűrés épület, emelet, szobaszám és teljes vételár szerint. Nézze meg a lakásadatokat és az alaprajzokat.',
            'epitesi-naplo' => 'Kövesse a Harmat Lakópark építkezését dátumozott helyszíni videókon és fotókon. Az építési napló bejegyzései időrendben olvashatók.',
        );
        return $copy[$post->post_name];
    }

    if (!function_exists('harmat_sai_property_summary_data')) {
        return $original;
    }

    $unit = harmat_sai_property_summary_data($post->ID);
    $code = strtoupper($post->post_name);
    if ($unit['title'] !== $code || $unit['rooms'] < 1 || $unit['area'] <= 0 || $unit['sales_area'] <= 0
        || (!$unit['ground'] && preg_match('/^\d+\. emelet$/D', $unit['floor']) !== 1)) {
        return $original;
    }

    $floor = $unit['ground'] ? 'földszinti' : $unit['floor'] . 'i';
    $closing = 'Alaprajz az adatlapon.';
    if ($unit['status'] === 'reserved') {
        $closing = 'Foglalva. ' . $closing;
    } elseif ($unit['status'] === 'sold') {
        $closing = 'Eladva. ' . $closing;
    } elseif (!$unit['hide_price'] && $unit['price'] > 0) {
        $closing = 'Alaprajz és aktuális ár az adatlapon.';
    }

    return sprintf('%s: %d szobás, %s lakás a Harmat Lakóparkban. Alapterület: %s; értékesítési terület: %s. %s',
        $code, $unit['rooms'], $floor, harmat_sai_format_area($unit['area']), harmat_sai_format_area($unit['sales_area']), $closing);
}

function harmat_asm_cover(): ?array
{
    $post = harmat_asm_target();
    if ($post === null) {
        return null;
    }

    static $covers = array();
    if (array_key_exists($post->ID, $covers)) {
        return $covers[$post->ID];
    }

    if ($post->post_type === 'property') {
        $code = strtoupper($post->post_name);
        $path = '/uploads/2026/05/' . $code . '-cn-floorplan-display.jpg';
        $alt = $code . ' – lakásalaprajz';
    } elseif ($post->post_name === 'magunkrol') {
        $path = '/mu-plugins/harmat-about-family/harmat-courtyard.jpg';
        $alt = 'A Harmat Lakópark tervezett belső udvara – látványterv';
    } else {
        return $covers[$post->ID] = null;
    }

    $file = WP_CONTENT_DIR . $path;
    $size = is_readable($file) ? getimagesize($file) : false;
    if (!$size || $size['mime'] !== 'image/jpeg') {
        return $covers[$post->ID] = null;
    }

    return $covers[$post->ID] = array(
        'url' => content_url($path), 'width' => $size[0], 'height' => $size[1], 'type' => $size['mime'], 'alt' => $alt,
    );
}

function harmat_asm_presentation($presentation, $context)
{
    $cover = harmat_asm_cover();
    if ($cover && is_object($presentation) && ($context->open_graph_enabled ?? false)) {
        $presentation->open_graph_images = array($cover);
    }
    return $presentation;
}

function harmat_asm_image_url($original)
{
    return harmat_asm_cover()['url'] ?? $original;
}

function harmat_asm_image_alt(): void
{
    $cover = harmat_asm_cover();
    if ($cover) {
        printf("\t<meta property=\"og:image:alt\" content=\"%s\" />\n", esc_attr($cover['alt']));
        printf("\t<meta name=\"twitter:image:alt\" content=\"%s\" />\n", esc_attr($cover['alt']));
    }
}

function harmat_asm_graph($graph)
{
    $post = harmat_asm_target();
    if ($post === null || !is_array($graph)) {
        return $graph;
    }

    $url = get_permalink($post);
    $cover = harmat_asm_cover();
    foreach ($graph as $i => $page) {
        if (!in_array('WebPage', (array) ($page['@type'] ?? array()), true) || ($page['url'] ?? '') !== $url) {
            continue;
        }
        if (isset($page['description'])) {
            $graph[$i]['description'] = harmat_asm_description($page['description']);
        }
        $primary = $page['primaryImageOfPage']['@id'] ?? '';
        if (!$cover || $primary === '') {
            continue;
        }
        foreach ($graph as $j => $image) {
            if (($image['@id'] ?? '') !== $primary || ($image['@type'] ?? '') !== 'ImageObject') {
                continue;
            }
            if (isset($page['thumbnailUrl'])) {
                $graph[$i]['thumbnailUrl'] = $cover['url'];
            }
            $graph[$j] = array_replace($image, array(
                'url' => $cover['url'], 'contentUrl' => $cover['url'], 'width' => $cover['width'],
                'height' => $cover['height'], 'caption' => $cover['alt'],
            ));
            break;
        }
    }
    return $graph;
}

foreach (array('wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description') as $harmat_asm_hook) {
    add_filter($harmat_asm_hook, 'harmat_asm_description', 2100);
}
add_filter('wpseo_frontend_presentation', 'harmat_asm_presentation', 2100, 2);
add_filter('wpseo_opengraph_image', 'harmat_asm_image_url', 2100);
add_filter('wpseo_twitter_image', 'harmat_asm_image_url', 2100);
add_filter('wpseo_schema_graph', 'harmat_asm_graph', 2100);
add_action('wpseo_head', 'harmat_asm_image_alt', 20);
