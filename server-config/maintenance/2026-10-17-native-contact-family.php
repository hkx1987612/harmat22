<?php
// Private, full native-theme preflight. No endpoint dispatch, mail, lead or cron call.
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WP_CLI')) { exit(1); }
$candidate = $argv[1] ?? '';
if (!preg_match('~\A/home/harmath2/codex-backups/contact-family-[a-f0-9]{32}/zz-harmat-contact-family\.php\z~D', $candidate)
    || !is_file($candidate) || is_link($candidate) || realpath($candidate) !== $candidate
    || !is_dir(dirname($candidate)) || is_link(dirname($candidate))
    || (fileperms(dirname($candidate)) & 0077) !== 0
    || (fileperms($candidate) & 0077) !== 0) { exit(2); }
umask(0077);
register_shutdown_function(static function () use ($candidate) {
    $error = error_get_last();
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        $handle = fopen(dirname($candidate) . '/native-error.json', 'x+b');
        if ($handle) { fwrite($handle, json_encode($error)); fclose($handle); }
    }
});
define('DISABLE_WP_CRON', true);
define('DONOTCACHEPAGE', true);
define('WP_USE_THEMES', false);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'harmat22.hu';
$_SERVER['REQUEST_URI'] = '/elerhetosegeink/';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
require '/home/harmath2/public_html/wp-load.php';
query_posts(array('page_id' => 26));
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
if (has_filter('the_content', 'harmat_perf_contact_scene_content') !== 1001) {
    throw new RuntimeException('Existing Contact scene owner drifted');
}
// Isolate only this candidate's seven identifiers/callback literals. The native
// scene at priority 1001 remains installed and is the only supported fallback.
$names = array();
foreach (array('context', 'assets', 'content', 'enqueue', 'div_range', 'output', 'buffer') as $suffix) {
    $names['harmat_contact_family_' . $suffix] = 'harmat_contact_preflight_' . $suffix;
}
$code = '';
foreach (token_get_all(file_get_contents($candidate), TOKEN_PARSE) as $token) {
    if (!is_array($token)) { $code .= $token; continue; }
    if ($token[0] === T_OPEN_TAG) { continue; }
    if ($token[0] === T_DIR) { $code .= var_export(dirname($candidate), true); }
    elseif ($token[0] === T_FILE) { $code .= var_export($candidate, true); }
    elseif ($token[0] === T_STRING && isset($names[$token[1]])) { $code .= $names[$token[1]]; }
    elseif ($token[0] === T_CONSTANT_ENCAPSED_STRING && isset($names[substr($token[1], 1, -1)])) {
        $code .= var_export($names[substr($token[1], 1, -1)], true);
    } else { $code .= $token[1]; }
}
remove_filter('the_content', 'harmat_contact_family_content', 1002);
remove_action('wp_enqueue_scripts', 'harmat_contact_family_enqueue', 99);
remove_action('template_redirect', 'harmat_contact_family_buffer', 99);
eval($code);
if (!is_main_query() || !harmat_contact_preflight_context() || !harmat_contact_preflight_assets()
    || get_page_template_slug(26) !== 'page-elementor.php') {
    throw new RuntimeException('Native Contact context/assets/template failed');
}
ob_start();
include get_template_directory() . '/page-elementor.php';
$html = ob_get_clean();
$html = harmat_contact_preflight_output($html);
if (function_exists('harmat_seo_cleanup_output')) { $html = harmat_seo_cleanup_output($html); }
if (substr_count($html, '<div id="harmat-contact-family" class="harmat-contact-family">') !== 1
    || preg_match_all('~<h1\b~i', $html) !== 1
    || substr_count($html, '<h1 id="hcf-title" class="hcf-title">Harmat Lak' . "\u{00f3}" . 'park</h1>') !== 1
    || substr_count($html, 'id="harmat-contact-family-title"') !== 1
    || strpos($html, 'id="h22-offer-modal"') === false
    || strpos($html, 'id="h22-offer-form"') === false
    || strpos($html, 'id="hm-cookie-settings-button"') === false
    || !preg_match('~<link\b[^>]*rel=["\x27]canonical["\x27][^>]*href=["\x27]https://harmat22\.hu/elerhetosegeink/["\x27]~i', $html)
    || has_filter('the_content', 'harmat_perf_contact_scene_content') !== 1001) {
    throw new RuntimeException('Native Contact replacement/H1/shell failed');
}
// Exclusive creation prevents accidental replacement of an earlier proof.
$target = dirname($candidate) . '/native-render.html';
$handle = fopen($target, 'x+b');
if (!$handle) { throw new RuntimeException('Native render already exists or cannot be created'); }
$written = 0;
try {
    while ($written < strlen($html)) {
        $n = fwrite($handle, substr($html, $written));
        if ($n === false || $n === 0) { throw new RuntimeException('Native proof write failed'); }
        $written += $n;
    }
    if (!fflush($handle)) { throw new RuntimeException('Native proof flush failed'); }
} finally {
    fclose($handle);
}
if (file_get_contents($target) !== $html || (fileperms($target) & 0077) !== 0) {
    throw new RuntimeException('Native proof readback failed');
}
echo wp_json_encode(array('native' => true, 'bytes' => strlen($html), 'sha256' => hash('sha256', $html)));
