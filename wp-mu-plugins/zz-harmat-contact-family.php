<?php
/**
 * Plugin Name: Harmat Contact Family
 * Description: Hash-pinned Contact presentation retaining the native offer and appointment owners.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_contact_family_context() {
    foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
        if (defined($flag) && constant($flag)) {
            return false;
        }
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'), true)
        || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed()
        || is_preview() || is_404() || is_search() || is_embed() || is_trackback()
        || (function_exists('wp_is_json_request') && wp_is_json_request())
        || isset($_GET['elementor-preview'])
        || get_queried_object_id() !== 26 || !is_page(26)
        || get_post_status(26) !== 'publish'
        || get_post_field('post_name', 26, 'raw') !== 'elerhetosegeink'
        || get_post_field('post_password', 26, 'raw') !== ''
        || has_filter('the_content', 'harmat_perf_contact_scene_content') !== 1001) {
        return false;
    }
    $raw = get_post_field('post_content', 26, 'raw');
    return is_string($raw) && strlen($raw) === 1862
        && hash('sha256', $raw) === '4dc84616c2e8e0ffed6766f041e8ad8485cd7a57c7b27ad0724116a5eaacfc0d';
}

function harmat_contact_family_assets() {
    $hashes = array(
        'body.php' => '88fe701c1822fdd2c7edffcf6edb56231cefb05ed4eb48e021d1a884c62e98ac',
        'contact-family.css' => 'ba621f955a5321cae41684a16d59f385284338ebe95eda84203adfc33a489904',
        'project-garden.jpg' => '96eaf92e4a8d32f6ec73ce0f7bf7b605394e893e30caa9b1a55cb38c081368b9',
        'showroom-model.jpg' => 'e4ca1407126d7dcb9ea939fec5761d6d9982cf40c86d5a293673a628704bd457',
        'showroom-lounge.jpg' => 'b3194b063d8d2bfdba7dadeffd981a79ba7560040d8d40b0939f008c154a9af3',
        'project-location.jpg' => '71ef699ca6bc75b6d911e1ef1cbe4ae74be3bfcba2f90f449d8349ed7b209b3e',
    );
    foreach ($hashes as $name => $expected) {
        $path = __DIR__ . '/harmat-contact-family/' . $name;
        if (!is_file($path) || !is_readable($path) || is_link($path)
            || hash_file('sha256', $path) !== $expected) {
            return false;
        }
    }
    return true;
}

function harmat_contact_family_content($content) {
    $marker = '<section class="harmat-contact-scene" aria-labelledby="harmat-contact-scene-title">';
    if (!is_string($content) || strpos($content, 'harmat-contact-family') !== false
        || substr_count($content, $marker) !== 1
        || substr_count($content, 'class="harmat-contact-scene"') !== 1
        || !harmat_contact_family_context() || !is_main_query() || !in_the_loop()
        || get_the_ID() !== 26 || !harmat_contact_family_assets()) {
        return $content;
    }
    $body = include __DIR__ . '/harmat-contact-family/body.php';
    if (!is_string($body)
        || substr_count($body, '<div id="harmat-contact-family" class="harmat-contact-family">') !== 1
        || substr_count($body, '<h2 id="hcf-title" class="hcf-title">Harmat Lak' . "\u{00f3}" . 'park</h2>') !== 1) {
        return $content;
    }
    foreach (array('project-garden.jpg', 'showroom-model.jpg', 'showroom-lounge.jpg', 'project-location.jpg') as $name) {
        $old = 'src="assets/' . $name . '"';
        if (substr_count($body, $old) !== 1) {
            return $content;
        }
        $body = str_replace($old, 'src="' . esc_url(content_url('/mu-plugins/harmat-contact-family/' . $name)) . '"', $body);
    }
    // Never fall back to the stored legacy CF7 content: retain the current scene.
    return strpos($body, 'src="assets/') === false ? $body : $content;
}

function harmat_contact_family_enqueue() {
    if (harmat_contact_family_context() && harmat_contact_family_assets()) {
        wp_enqueue_style('harmat-contact-family', content_url('/mu-plugins/harmat-contact-family/contact-family.css'),
            array(), 'ba621f955a5321ca', 'all');
    }
}

// Locate a known div's byte range without reserializing the native shell.
function harmat_contact_family_div_range($html, $id) {
    $tokens = array();
    preg_match_all('~</?div\b[^>]*>~i', $html, $tokens, PREG_OFFSET_CAPTURE);
    $start = null;
    $depth = 0;
    foreach ($tokens[0] as $token) {
        $closing = substr($token[0], 0, 2) === '</';
        if ($start === null) {
            if (!$closing && preg_match('~\bid\s*=\s*(["\x27])' . preg_quote($id, '~') . '\1~i', $token[0])) {
                $start = $token[1];
                $depth = 1;
            }
        } else {
            $depth += $closing ? -1 : 1;
            if ($depth === 0) {
                return array($start, $token[1] + strlen($token[0]));
            }
        }
    }
    return false;
}

function harmat_contact_family_output($html) {
    if (!is_string($html) || !harmat_contact_family_context() || !harmat_contact_family_assets()) {
        return $html;
    }
    $hero = '<h2 id="hcf-title" class="hcf-title">Harmat Lak' . "\u{00f3}" . 'park</h2>';
    $root = '<div id="harmat-contact-family" class="harmat-contact-family">';
    $theme = array();
    if (substr_count($html, $root) !== 1 || substr_count($html, $hero) !== 1
        || preg_match_all('~\bid\s*=\s*(["\x27])harmat-contact-family\1~i', $html) !== 1
        || preg_match_all('~\bid\s*=\s*(["\x27])hcf-title\1~i', $html) !== 1
        || preg_match_all('~\bid\s*=\s*(["\x27])page-title-bar\1~i', $html) !== 1
        || preg_match_all('~<h1\b~i', $html) !== 1 || preg_match_all('~</h1\s*>~i', $html) !== 1
        || preg_match_all('~<h1 class="page-title typo-heading">([^<]*)</h1>~', $html, $theme, PREG_OFFSET_CAPTURE) !== 1
        || html_entity_decode($theme[1][0][0], ENT_QUOTES | ENT_HTML5, 'UTF-8') !== "El\u{00e9}rhet\u{0151}s\u{00e9}geink"
        || substr_count($html, '</head>') !== 1
        || strpos($html, 'harmat-contact-family-title') !== false) {
        return $html;
    }
    $bar = harmat_contact_family_div_range($html, 'page-title-bar');
    $main = harmat_contact_family_div_range($html, 'harmat-contact-family');
    $hero_at = strpos($html, $hero);
    $head_at = strpos($html, '</head>');
    if (!$bar || !$main || $head_at >= $bar[0] || $bar[1] > $main[0]
        || $theme[0][0][1] <= $bar[0]
        || $theme[0][0][1] + strlen($theme[0][0][0]) > $bar[1]
        || $hero_at <= $main[0] || $hero_at + strlen($hero) > $main[1]) {
        return $html;
    }
    // Demotion, promotion and native-bar hiding form one all-or-nothing change.
    $result = str_replace($theme[0][0][0], '<h2 class="page-title typo-heading">' . $theme[1][0][0] . '</h2>', $html);
    $result = str_replace($hero, '<h1 id="hcf-title" class="hcf-title">Harmat Lak' . "\u{00f3}" . 'park</h1>', $result);
    return str_replace('</head>', '<style id="harmat-contact-family-title">body.page-id-26 #page-title-bar{display:none!important;}</style></head>', $result);
}

function harmat_contact_family_buffer() {
    if (harmat_contact_family_context() && harmat_contact_family_assets()) {
        ob_start('harmat_contact_family_output');
    }
}

add_filter('the_content', 'harmat_contact_family_content', 1002, 1);
add_action('wp_enqueue_scripts', 'harmat_contact_family_enqueue', 99);
add_action('template_redirect', 'harmat_contact_family_buffer', 99);
