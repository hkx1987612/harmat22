<?php
/**
 * Plugin Name: Harmat Project Family
 * Description: Atomic, hash-pinned Project presentation around the native sales shell.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_project_family_context() {
    foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
        if (defined($flag) && constant($flag)) {
            return false;
        }
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'), true)
        || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed()
        || is_preview() || is_404() || is_search() || is_embed() || is_trackback()
        || (function_exists('wp_is_json_request') && wp_is_json_request())
        || array_key_exists('elementor-preview', $_GET)
        || get_queried_object_id() !== 1777 || !is_page(1777) || !is_main_query()
        || get_post_status(1777) !== 'publish'
        || get_post_field('post_name', 1777, 'raw') !== 'harmat-lakopark'
        || get_post_field('post_password', 1777, 'raw') !== ''
        || get_page_template_slug(1777) !== '') {
        return false;
    }
    foreach (array('hm_migrated_replace_project_page', 'harmat_keyword_filter_html', 'harmat_seo_cleanup_output') as $owner) {
        if (!function_exists($owner)) {
            return false;
        }
    }
    $raw = get_post_field('post_content', 1777, 'raw');
    return is_string($raw) && strlen($raw) === 2963
        && hash('sha256', $raw) === '6659cc4e514d92373321f12095971bea145270866125765909ef7db52f5efdd4';
}

function harmat_project_family_assets() {
    $directory = __DIR__ . '/harmat-project-family';
    $hashes = array(
        'body.php' => '51d757aea310744f63a5585815ab98ab33338fcb8613b6da07024c6831c23fc0',
        'project-family.css' => 'ecc70a853b0d19d01869b0f4ccaff48a08862b7fb6db7b73cffadabeffe2febd',
        'project-aerial.jpg' => 'efc5fc46c290fc4861ac37d309a4456d8b8866d37b5699ba742546e3ebb9c25d',
        'project-courtyard.jpg' => 'b9081b36c296f5c1ff519467eb8e1d3ce9379f03f78e4f0b49e4c7ce9405ee78',
        'project-living-room.jpg' => '3d68545fda27820a95c976074a89254d8ac442471119c2acfa8e385340e7d0c4',
        'construction-site.webp' => '1855fec6962b3e546263a26ffc09b3523e3ce777bac409ed39ff9d1b67a29ee9',
    );
    if (is_link($directory)) {
        return false;
    }
    foreach ($hashes as $name => $expected) {
        $path = $directory . '/' . $name;
        if (!is_file($path) || !is_readable($path) || is_link($path)
            || hash_file('sha256', $path) !== $expected) {
            return false;
        }
    }
    return true;
}

function harmat_project_family_enqueue() {
    if (harmat_project_family_context() && harmat_project_family_assets()) {
        wp_enqueue_style('harmat-project-family', content_url('/mu-plugins/harmat-project-family/project-family.css'),
            array(), 'ecc70a853b0d19d0', 'all');
    }
}

// Parse for validation only; all successful changes are bounded byte splices.
function harmat_project_family_document($html) {
    if (!class_exists('DOMDocument') || !preg_match('~\A\s*<!doctype html>~i', $html)
        || !preg_match('~</html>\s*\z~i', $html)) {
        return false;
    }
    $positions = array();
    foreach (array('html', 'head', 'body', 'main', 'h1') as $tag) {
        foreach (array('open' => '<' . $tag . '\b[^>]*>', 'close' => '</' . $tag . '\s*>') as $kind => $pattern) {
            $matches = array();
            if (preg_match_all('~' . $pattern . '~i', $html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                return false;
            }
            $positions[$tag][$kind] = $matches[0][0][1];
        }
    }
    $p = $positions;
    if (!($p['html']['open'] < $p['head']['open'] && $p['head']['open'] < $p['head']['close']
        && $p['head']['close'] < $p['body']['open'] && $p['body']['open'] < $p['main']['open']
        && $p['main']['open'] < $p['main']['close'] && $p['main']['close'] < $p['body']['close']
        && $p['body']['close'] < $p['html']['close']
        && $p['h1']['open'] < $p['h1']['close'])) {
        return false;
    }
    $document = new \DOMDocument();
    if (!$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        return false;
    }
    foreach (array('html', 'head', 'body', 'main', 'h1') as $tag) {
        if ($document->getElementsByTagName($tag)->length !== 1) {
            return false;
        }
    }
    $ids = array();
    $protected = array('main', 'page-title-bar', 'masthead', 'colophon', 'h22-offer-modal',
        'h22-offer-form', 'hm-cookie-settings-button');
    foreach ($document->getElementsByTagName('*') as $node) {
        if ($node->hasAttribute('id')) {
            $id = $node->getAttribute('id');
            $owned = strpos($id, 'hpf-') === 0 || strpos($id, 'harmat-project-family') === 0;
            if (isset($ids[$id]) && ($owned || in_array($id, $protected, true))) {
                return false;
            }
            $ids[$id] = $node;
        }
    }
    foreach (array('main' => 'main', 'page-title-bar' => 'div', 'masthead' => 'header',
        'colophon' => 'footer', 'h22-offer-modal' => 'div', 'h22-offer-form' => 'form',
        'hm-cookie-settings-button' => 'button') as $id => $tag) {
        if (!isset($ids[$id]) || $ids[$id]->tagName !== $tag) {
            return false;
        }
        $node = $ids[$id];
        $inside_body = false;
        while ($node = $node->parentNode) {
            if ($node instanceof \DOMElement && $node->tagName === 'main') {
                return false;
            }
            if ($node instanceof \DOMElement && $node->tagName === 'body') {
                $inside_body = true;
            }
        }
        if (!$inside_body) {
            return false;
        }
    }
    $node = $ids['h22-offer-form']->parentNode;
    while ($node && $node !== $ids['h22-offer-modal']) {
        $node = $node->parentNode;
    }
    if (!$node) {
        return false;
    }
    return array($document, $ids, $positions);
}

function harmat_project_family_div_range($html, $id) {
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

function harmat_project_family_output($html) {
    if (!is_string($html)
        || preg_match('~\bid\s*=\s*(["\x27])harmat-project-family(?:-title)?\1~i', $html)
        || !harmat_project_family_context() || !harmat_project_family_assets()) {
        return $html;
    }
    $opening = '<main id="main" class="site-main harmat-project-modern" role="main">';
    $theme = '<h1 class="page-title typo-heading">Harmat Lak' . "\u{00f3}" . 'park</h1>';
    $parsed = harmat_project_family_document($html);
    if (!$parsed || substr_count($html, $opening) !== 1 || substr_count($html, $theme) !== 1
        || substr_count($html, '</head>') !== 1 || substr_count($html, '</main>') !== 1) {
        return $html;
    }
    $main_at = strpos($html, $opening);
    $main_end = strpos($html, '</main>') + 7;
    $old = substr($html, $main_at, $main_end - $main_at);
    $bar = harmat_project_family_div_range($html, 'page-title-bar');
    $theme_at = strpos($html, $theme);
    $head_at = strpos($html, '</head>');
    $title = $parsed[0]->getElementsByTagName('h1')->item(0);
    $ancestor = $title->parentNode;
    while ($ancestor && $ancestor !== $parsed[1]['page-title-bar']) {
        $ancestor = $ancestor->parentNode;
    }
    if (strlen($old) !== 4476
        || hash('sha256', $old) !== 'db8446abb9b2702f0ae8b3e2468926e2fd4c5284292c031650b9a80fbb78bf17'
        || !$bar || !$ancestor || $head_at >= $bar[0] || $bar[1] > $main_at
        || $theme_at <= $bar[0] || $theme_at + strlen($theme) > $bar[1]) {
        return $html;
    }
    // Confirm native anchors occur in the shell, not in comments or the old main.
    foreach (array('masthead', 'colophon', 'h22-offer-modal', 'h22-offer-form', 'hm-cookie-settings-button') as $id) {
        $matches = array();
        if (preg_match_all('~<[^>]+\bid\s*=\s*(["\x27])' . $id . '\1[^>]*>~i', $html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return $html;
        }
        $at = $matches[0][0][1];
        if ($at <= $parsed[2]['body']['open'] || $at >= $parsed[2]['body']['close']
            || ($at >= $main_at && $at < $main_end)
            || ($id === 'masthead' && $at >= $bar[0])
            || ($id === 'colophon' && $at < $main_end)) {
            return $html;
        }
    }
    $body = include __DIR__ . '/harmat-project-family/body.php';
    if (!is_string($body)) {
        return $html;
    }
    foreach (array('project-aerial.jpg', 'project-courtyard.jpg', 'project-living-room.jpg', 'construction-site.webp') as $name) {
        $source = 'src="assets/' . $name . '"';
        if (substr_count($body, $source) !== 1) {
            return $html;
        }
        $body = str_replace($source, 'src="' . esc_url(content_url('/mu-plugins/harmat-project-family/' . $name)) . '"', $body);
    }
    if (strpos($body, 'assets/') !== false) {
        return $html;
    }
    $result = substr($html, 0, $main_at) . $body . substr($html, $main_end);
    $result = substr_replace($result, str_replace(array('<h1 ', '</h1>'), array('<h2 ', '</h2>'), $theme), $theme_at, strlen($theme));
    $style = '<style id="harmat-project-family-title">body.page-id-1777 #page-title-bar{display:none!important;}</style>';
    $result = substr_replace($result, $style, $head_at, 0);
    $verified = harmat_project_family_document($result);
    return $verified && isset($verified[1]['harmat-project-family'], $verified[1]['hpf-title'])
        && $verified[0]->getElementsByTagName('h1')->item(0)->getAttribute('id') === 'hpf-title'
        ? $result : $html;
}

function harmat_project_family_buffer() {
    if (!harmat_project_family_context() || !harmat_project_family_assets()) {
        return;
    }
    // A flush/clean irrevocably disables transformation for this buffer instance.
    $intact = true;
    ob_start(function ($html, $phase) use (&$intact) {
        if (!($phase & PHP_OUTPUT_HANDLER_FINAL) || ($phase & (PHP_OUTPUT_HANDLER_CLEAN | PHP_OUTPUT_HANDLER_FLUSH))) {
            $intact = false;
        }
        return $intact ? harmat_project_family_output($html) : $html;
    });
}

add_action('wp_enqueue_scripts', 'harmat_project_family_enqueue', 99);
add_action('template_redirect', 'harmat_project_family_buffer', -3000);
