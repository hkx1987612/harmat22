<?php
// Private CLI-only native Elementor rendering; no lead, mail or cron invocation.
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WP_CLI')) {
    exit(1);
}
$candidate = $argv[1] ?? '';
$page = $argv[2] ?? '';
if (!preg_match('~\A/home/harmath2/codex-backups/public-trust-[a-f0-9]{32}/0-source\.php\z~D', $candidate)
    || !in_array($page, array('164', '439'), true) || !is_file($candidate) || is_link($candidate)) {
    exit(2);
}
define('DISABLE_WP_CRON', true);
define('DONOTCACHEPAGE', true);
define('WP_USE_THEMES', false);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'harmat22.hu';
$_SERVER['REQUEST_URI'] = $page === '164' ? '/' : '/galeria/';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
require '/home/harmath2/public_html/wp-load.php';
query_posts(array('page_id' => (int) $page));
require $candidate;
if (!harmat_trust_public_page((int) $page)) {
    throw new RuntimeException('Native public context was not established');
}
$content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display((int) $page);
$result = array('page' => (int) $page, 'bytes' => strlen($content));
if ($page === '439') {
    $result['notices'] = substr_count($content, 'class="harmat-gallery-render-notice"');
    $result['gallery_marker'] = substr_count($content, 'elementor-element-4b8fe87');
    if ($result['notices'] !== 1 || $result['gallery_marker'] !== 1) {
        throw new RuntimeException('Native gallery notice/marker count failed');
    }
} else {
    foreach (array(
        "Csal\u{00e1}dbar\u{00e1}t otthon \u{2013} CSOK ig\u{00e9}nyelhet\u{0151}",
        "CSOK - Okos beruh\u{00e1}z\u{00e1}s",
        "CSOK-bar\u{00e1}t lak\u{00e1}sokat k\u{00ed}n\u{00e1}l",
    ) as $old) {
        if (str_contains(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $old)) {
            throw new RuntimeException('Native old homepage copy remains');
        }
    }
    $result['old_copy_absent'] = true;
}
echo wp_json_encode($result);
