<?php
/**
 * Plugin Name: Harmat About Family
 * Description: Hash-pinned, public About-page presentation; no stored-content changes.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_about_family_context() {
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
        || get_queried_object_id() !== 2686 || !is_page(2686)
        || get_post_status(2686) !== 'publish'
        || get_post_field('post_name', 2686, 'raw') !== 'magunkrol'
        || get_post_field('post_password', 2686, 'raw') !== '') {
        return false;
    }
    $raw = get_post_field('post_content', 2686, 'raw');
    return is_string($raw) && strlen($raw) === 14922
        && hash('sha256', $raw) === '4b272195ad1bfd35ba659fff2b9e285e6d2e6a9f1ebfd65bdb0a5f29232ee465';
}

function harmat_about_family_assets() {
    $hashes = array(
        'body.php' => '2bb078625505ba20ab0702823c6954a93601425a310ede7f4f6073009ccd4f1e',
        'about-family.css' => 'de5fa727295f6b76b0ea7945d71cce840043e88faa2e8d396b0652cebd214042',
        'harmat-courtyard.jpg' => 'b9081b36c296f5c1ff519467eb8e1d3ce9379f03f78e4f0b49e4c7ce9405ee78',
        'harmat-living-room.jpg' => '3d68545fda27820a95c976074a89254d8ac442471119c2acfa8e385340e7d0c4',
    );
    foreach ($hashes as $name => $expected) {
        $path = __DIR__ . '/harmat-about-family/' . $name;
        if (!is_file($path) || !is_readable($path) || hash_file('sha256', $path) !== $expected) {
            return false;
        }
    }
    return true;
}

function harmat_about_family_content($content) {
    if (!is_string($content) || strpos($content, 'harmat-about-redesign') !== false
        || substr_count($content, 'class="hm-about-v2"') !== 1
        || !harmat_about_family_context() || !is_main_query() || !in_the_loop()
        || get_the_ID() !== 2686 || !harmat_about_family_assets()) {
        return $content;
    }
    $body = include __DIR__ . '/harmat-about-family/body.php';
    if (!is_string($body)) {
        return $content;
    }
    foreach (array('harmat-courtyard.jpg', 'harmat-living-room.jpg') as $name) {
        $old = 'src="assets/' . $name . '"';
        if (substr_count($body, $old) !== 1) {
            return $content;
        }
        $body = str_replace($old, 'src="' . esc_url(content_url('/mu-plugins/harmat-about-family/' . $name)) . '"', $body);
    }
    return strpos($body, 'src="assets/') === false ? $body : $content;
}

function harmat_about_family_enqueue() {
    if (harmat_about_family_context() && harmat_about_family_assets()) {
        wp_enqueue_style('harmat-about-family', content_url('/mu-plugins/harmat-about-family/about-family.css'),
            array(), 'de5fa727295f6b76', 'all');
    }
}

// Locate only a known div's byte range; never reserialize the native shell.
function harmat_about_family_div_range($html, $id) {
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

function harmat_about_family_output($html) {
    if (!is_string($html) || !harmat_about_family_context() || !harmat_about_family_assets()) {
        return $html;
    }
    $hero = '<h2 id="hap-title" class="hap-title">Harmat<br>Lak' . "\u{00f3}" . 'park</h2>';
    $root = '<div id="harmat-about-redesign" class="harmat-about-preview">';
    $theme = array();
    if (substr_count($html, $root) !== 1 || substr_count($html, $hero) !== 1
        || preg_match_all('~\bid\s*=\s*(["\x27])harmat-about-redesign\1~i', $html) !== 1
        || preg_match_all('~\bid\s*=\s*(["\x27])hap-title\1~i', $html) !== 1
        || preg_match_all('~\bid\s*=\s*(["\x27])page-title-bar\1~i', $html) !== 1
        || preg_match_all('~<h1\b~i', $html) !== 1 || preg_match_all('~</h1\s*>~i', $html) !== 1
        || preg_match_all('~<h1 class="page-title typo-heading">([^<]*)</h1>~', $html, $theme, PREG_OFFSET_CAPTURE) !== 1
        || html_entity_decode($theme[1][0][0], ENT_QUOTES | ENT_HTML5, 'UTF-8') !== "Magunkr\u{00f3}l"
        || substr_count($html, '</head>') !== 1
        || strpos($html, 'harmat-about-family-title') !== false) {
        return $html;
    }
    $bar = harmat_about_family_div_range($html, 'page-title-bar');
    $main = harmat_about_family_div_range($html, 'harmat-about-redesign');
    $hero_at = strpos($html, $hero);
    $head_at = strpos($html, '</head>');
    if (!$bar || !$main || $head_at >= $bar[0] || $bar[1] > $main[0]
        || $theme[0][0][1] <= $bar[0]
        || $theme[0][0][1] + strlen($theme[0][0][0]) > $bar[1]
        || $hero_at <= $main[0] || $hero_at + strlen($hero) > $main[1]) {
        return $html;
    }
    // Commit all three changes together, only after every contract has matched.
    $result = str_replace($theme[0][0][0], '<h2 class="page-title typo-heading">' . $theme[1][0][0] . '</h2>', $html);
    $result = str_replace($hero, '<h1 id="hap-title" class="hap-title">Harmat<br>Lak' . "\u{00f3}" . 'park</h1>', $result);
    return str_replace('</head>', '<style id="harmat-about-family-title">body.page-id-2686 #page-title-bar{display:none!important;}</style></head>', $result);
}

function harmat_about_family_buffer() {
    if (harmat_about_family_context() && harmat_about_family_assets()) {
        // Hold the complete document: chunk callbacks must not split the heading pair.
        ob_start('harmat_about_family_output');
    }
}

add_filter('the_content', 'harmat_about_family_content', 99, 1);
add_action('wp_enqueue_scripts', 'harmat_about_family_enqueue', 99);
add_action('template_redirect', 'harmat_about_family_buffer', 99);
