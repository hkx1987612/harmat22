<?php
/**
 * Plugin Name: Harmat Public Trust Copy
 * Description: Narrow public Elementor copy corrections without stored-content changes.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_trust_public_page($post_id) {
    foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
        if (defined($flag) && constant($flag)) {
            return false;
        }
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'), true)
        || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed()
        || is_preview() || is_404() || is_search() || is_embed() || is_trackback()
        || (function_exists('wp_is_json_request') && wp_is_json_request())
        || isset($_GET['elementor-preview'])) {
        return false;
    }
    return get_queried_object_id() === $post_id && is_page($post_id)
        && get_post_status($post_id) === 'publish'
        && get_post_field('post_password', $post_id) === ''
        && ($post_id !== 164 || is_front_page());
}

// Traverse only Elementor's element lists, never settings or arbitrary nested data.
function harmat_trust_title_walk($nodes, &$seen, &$valid, &$matched, $depth = 0) {
    if (!is_array($nodes) || !array_is_list($nodes) || $depth > 64) {
        $valid = false;
        return $nodes;
    }
    $result = array();
    foreach ($nodes as $key => $node) {
        if (!is_array($node) || !is_string($node['id'] ?? null)
            || !is_string($node['elType'] ?? null)) {
            $valid = false;
            return $nodes;
        }
        $replace = false;
        if ($node['id'] === 'df8c809') {
            $seen++;
            if ($node['elType'] === 'widget' && ($node['widgetType'] ?? null) === 'icon-box'
                && is_array($node['settings'] ?? null)
                && ($node['settings']['title_text'] ?? null)
                    === "Csal\u{00e1}dbar\u{00e1}t otthon \u{2013} CSOK ig\u{00e9}nyelhet\u{0151}") {
                $replace = true;
                $matched = true;
            }
        }
        // Rebuild touched levels so even referenced input slots cannot be mutated.
        $copy = array();
        foreach ($node as $field => $value) {
            if ($field === 'elements') {
                $value = harmat_trust_title_walk($value, $seen, $valid, $matched, $depth + 1);
            } elseif ($field === 'settings' && $replace) {
                $settings = array();
                foreach ($value as $setting => $setting_value) {
                    $settings[$setting] = $setting === 'title_text'
                        ? "Csal\u{00e1}dbar\u{00e1}t otthonok" : $setting_value;
                }
                $value = $settings;
            }
            $copy[$field] = $value;
        }
        $result[$key] = $copy;
    }
    return $result;
}

function harmat_trust_title_data($data) {
    $seen = 0;
    $valid = true;
    $matched = false;
    $result = harmat_trust_title_walk($data, $seen, $valid, $matched);
    return $valid && $seen === 1 && $matched ? $result : $data;
}

function harmat_trust_builder_data($data, $post_id) {
    return in_array($post_id, array(164, '164'), true) && harmat_trust_public_page(164)
        ? harmat_trust_title_data($data) : $data;
}

function harmat_trust_slider_copy($content) {
    if (!is_string($content)) {
        return $content;
    }
    $copies = array(
        "CSOK - Okos beruh\u{00e1}z\u{00e1}s" => "\u{00c1}tgondolt otthonteremt\u{00e9}s",
        "CSOK-bar\u{00e1}t lak\u{00e1}sokat k\u{00ed}n\u{00e1}l" => "csal\u{00e1}dbar\u{00e1}t otthonokat k\u{00ed}n\u{00e1}l",
    );
    $counts = array();
    foreach ($copies as $old => $new) {
        $counts[$old] = substr_count($content, $old);
        if ($counts[$old] > 1) {
            return $content;
        }
    }
    // Unknown CSOK wording is not an approved replacement target.
    if (substr_count($content, 'CSOK') !== array_sum($counts)) {
        return $content;
    }
    foreach ($copies as $old => $new) {
        if ($counts[$old] === 1) {
            $content = str_replace($old, $new, $content);
        }
    }
    return $content;
}

function harmat_trust_widget_content($content, $widget) {
    if (!harmat_trust_public_page(164) || !is_object($widget)
        || !is_callable(array($widget, 'get_id')) || !is_callable(array($widget, 'get_name'))
        || $widget->get_id() !== 'ab01017' || $widget->get_name() !== 'opal-revslider') {
        return $content;
    }
    return harmat_trust_slider_copy($content);
}

function harmat_trust_gallery_notice($content) {
    if (!is_string($content) || str_contains($content, 'harmat-gallery-render-notice')
        || !preg_match('~(?<![a-zA-Z0-9_-])elementor-element-4b8fe87(?![a-zA-Z0-9_-])~', $content)) {
        return $content;
    }
    return '<p class="harmat-gallery-render-notice" style="box-sizing:border-box;width:100%;max-width:1240px;'
        . 'margin:0 auto 16px;padding:0 12px;font-size:14px;line-height:1.6;letter-spacing:0;">'
        . "A l\u{00e1}tv\u{00e1}nytervek t\u{00e1}j\u{00e9}koztat\u{00f3} illusztr\u{00e1}ci\u{00f3}k; "
        . "a megval\u{00f3}sult \u{00e1}llapot elt\u{00e9}rhet a bemutatott k\u{00e9}pekt\u{0151}l.</p>"
        . $content;
}

function harmat_trust_builder_content($content) {
    return harmat_trust_public_page(439) ? harmat_trust_gallery_notice($content) : $content;
}

add_filter('elementor/frontend/builder_content_data', 'harmat_trust_builder_data', 20, 2);
add_filter('elementor/widget/render_content', 'harmat_trust_widget_content', 20, 2);
add_filter('elementor/frontend/the_content', 'harmat_trust_builder_content', 20, 1);
