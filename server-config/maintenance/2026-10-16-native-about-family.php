<?php
// Private native theme render. No endpoint dispatch, mail, lead or cron call.
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WP_CLI')) { exit(1); }
$candidate = $argv[1] ?? '';
if (!preg_match('~\A/home/harmath2/codex-backups/about-family-[a-f0-9]{32}/zz-harmat-about-family\.php\z~D', $candidate)
    || !is_file($candidate) || is_link($candidate)) { exit(2); }
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
$_SERVER['REQUEST_URI'] = '/magunkrol/';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
require '/home/harmath2/public_html/wp-load.php';
query_posts(array('page_id' => 2686));
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
// The installed version may already define the names. Rename only this exact
// candidate's seven function identifiers/callback literals for private rendering.
$names = array();
foreach (array('context','assets','content','enqueue','div_range','output','buffer') as $suffix) {
    $names['harmat_about_family_' . $suffix] = 'harmat_about_preflight_' . $suffix;
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
remove_filter('the_content', 'harmat_about_family_content', 99);
remove_action('wp_enqueue_scripts', 'harmat_about_family_enqueue', 99);
remove_action('template_redirect', 'harmat_about_family_buffer', 99);
eval($code);
if (!is_main_query() || !harmat_about_preflight_context() || !harmat_about_preflight_assets()) {
    throw new RuntimeException('Native About context/assets failed');
}
ob_start();
include get_template_directory() . '/page-elementor.php';
$html = ob_get_clean();
$html = harmat_about_preflight_output($html);
if (function_exists('harmat_seo_cleanup_output')) { $html = harmat_seo_cleanup_output($html); }
umask(0077);
$target = dirname($candidate) . '/native-render.html';
if (is_file($target)) { throw new RuntimeException('Native render already exists'); }
if (file_put_contents($target, $html, LOCK_EX) !== strlen($html)) { throw new RuntimeException('Native proof write failed'); }
if (substr_count($html, 'id="harmat-about-redesign"') !== 1
    || substr_count($html, '<h1') !== 1
    || !preg_match('~<h1\b[^>]*\bid="hap-title"[^>]*>~', $html)) {
    throw new RuntimeException('Native About replacement/H1 failed');
}
echo wp_json_encode(array('native'=>true,'bytes'=>strlen($html),'sha256'=>hash('sha256',$html)));
