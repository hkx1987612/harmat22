<?php
// Private full-theme/pipeline preflight. No endpoint, form, mail or cron dispatch.
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WP_CLI')) { exit(1); }
$candidate = $argv[1] ?? '';
if (!preg_match('~\A/home/harmath2/codex-backups/project-family-[a-f0-9]{32}/zz-harmat-project-family\.php\z~D', $candidate)
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
$_SERVER['SERVER_NAME'] = 'harmat22.hu';
$_SERVER['REQUEST_URI'] = '/harmat-lakopark/';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
require '/home/harmath2/public_html/wp-load.php';
query_posts(array('page_id' => 1777));
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$source = file_get_contents($candidate);
$tokens = token_get_all($source, TOKEN_PARSE);
$names = array();
$pending = false;
foreach ($tokens as $token) {
    if (!is_array($token)) {
        if ($pending && $token === '(') { $pending = false; }
        continue;
    }
    if ($token[0] === T_FUNCTION) { $pending = true; continue; }
    if ($pending && $token[0] === T_STRING) {
        if (strpos($token[1], 'harmat_project_family_') !== 0) { throw new RuntimeException('Unexpected candidate function'); }
        $names[$token[1]] = str_replace('harmat_project_family_', 'harmat_project_preflight_', $token[1]);
        $pending = false;
    }
}
$code = '';
foreach ($tokens as $token) {
    if (!is_array($token)) { $code .= $token; continue; }
    if ($token[0] === T_OPEN_TAG) { continue; }
    if ($token[0] === T_DIR) { $code .= var_export(dirname($candidate), true); }
    elseif ($token[0] === T_FILE) { $code .= var_export($candidate, true); }
    elseif ($token[0] === T_STRING && isset($names[$token[1]])) { $code .= $names[$token[1]]; }
    elseif ($token[0] === T_CONSTANT_ENCAPSED_STRING && isset($names[substr($token[1], 1, -1)])) {
        $code .= var_export($names[substr($token[1], 1, -1)], true);
    } else { $code .= $token[1]; }
}
remove_action('wp_enqueue_scripts', 'harmat_project_family_enqueue', 99);
remove_action('template_redirect', 'harmat_project_family_buffer', -3000);
eval($code);
if (!is_main_query() || !harmat_project_preflight_context() || !harmat_project_preflight_assets()
    || get_page_template_slug(1777) !== '') {
    throw new RuntimeException('Native Project context/assets/template failed');
}
$base = ob_get_level();
ob_start();
do_action('template_redirect');
$template = apply_filters('template_include', get_page_template());
if (!is_string($template) || !is_file($template)) { throw new RuntimeException('Missing native template'); }
include $template;
while (ob_get_level() > $base + 1) { ob_end_flush(); }
$html = ob_get_clean();
if (substr_count($html, 'id="harmat-project-family"') !== 1
    || preg_match_all('~<h1\b~i', $html) !== 1
    || substr_count($html, '<h1 id="hpf-title">Harmat Lak' . "\u{00f3}" . 'park</h1>') !== 1
    || strpos($html, 'id="h22-offer-modal"') === false
    || strpos($html, 'id="h22-offer-form"') === false
    || strpos($html, 'id="hm-cookie-settings-button"') === false
    || strpos($html, 'class="site-main harmat-project-modern"') !== false
    || !preg_match('~<link\b[^>]*rel=["\x27]canonical["\x27][^>]*href=["\x27]https://harmat22\.hu/harmat-lakopark/["\x27]~i', $html)) {
    throw new RuntimeException('Native Project replacement/H1/shell failed');
}
$target = dirname($candidate) . '/native-render.html';
$handle = fopen($target, 'x+b');
if (!$handle) { throw new RuntimeException('Native render already exists or cannot be created'); }
try {
    $written = 0;
    while ($written < strlen($html)) {
        $n = fwrite($handle, substr($html, $written));
        if ($n === false || $n === 0) { throw new RuntimeException('Native proof write failed'); }
        $written += $n;
    }
    if (!fflush($handle)) { throw new RuntimeException('Native proof flush failed'); }
} finally { fclose($handle); }
if (file_get_contents($target) !== $html || (fileperms($target) & 0077) !== 0) {
    throw new RuntimeException('Native proof readback failed');
}
echo wp_json_encode(array('native' => true, 'bytes' => strlen($html), 'sha256' => hash('sha256', $html), 'template' => basename($template)));
