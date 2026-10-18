<?php
// Private CLI theme rendering only: no REST dispatch, form submission or mail.
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WP_CLI')) { exit(1); }
$candidate = $argv[1] ?? '';
$slug = $argv[2] ?? '';
if (!preg_match('~\A/home/harmath2/codex-backups/property-disclaimer-[a-f0-9]{32}/zz-harmat-property-disclaimer\.php\z~D', $candidate)
    || !in_array($slug, array('a1-f-l1', 'a1-1-l2', 'a3-4-l5'), true)
    || !is_file($candidate) || is_link($candidate) || realpath($candidate) !== $candidate
    || is_link(dirname($candidate)) || (fileperms(dirname($candidate)) & 0077) !== 0
    || (fileperms($candidate) & 0077) !== 0) { exit(2); }
umask(0077);
define('DISABLE_WP_CRON', true);
define('DONOTCACHEPAGE', true);
define('WP_USE_THEMES', false);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'harmat22.hu';
$_SERVER['SERVER_NAME'] = 'harmat22.hu';
$_SERVER['REQUEST_URI'] = '/property/' . $slug . '/';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
require '/home/harmath2/public_html/wp-load.php';
$post = get_page_by_path($slug, OBJECT, 'property');
if (!($post instanceof WP_Post) || $post->post_status !== 'publish' || $post->post_password !== '') {
    throw new RuntimeException('Missing exact public property');
}
query_posts(array('post_type' => 'property', 'p' => $post->ID));
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
if (!is_singular('property') || get_queried_object_id() !== $post->ID) {
    throw new RuntimeException('Native property query failed');
}
require $candidate;
// Shadow only the reviewed legacy call-site output; live legacy PHP is untouched.
// Its staged PHP source/lint and exact one-line diff are checked by the deployer.
$old_call = '    addPropertyDisclaimer();';
$guarded_call = '    if (true /* harmat-property-disclaimer-legacy */) addPropertyDisclaimer();';
add_action('template_redirect', static function () use ($old_call, $guarded_call) {
    ob_start(static function ($html) use ($old_call, $guarded_call) {
        if (substr_count($html, $old_call) !== 1) {
            throw new RuntimeException('Native legacy shadow call mismatch');
        }
        return str_replace($old_call, $guarded_call, $html);
    });
}, 0);
$base = ob_get_level();
ob_start();
do_action('template_redirect');
$template = apply_filters('template_include', get_single_template());
if (!is_string($template) || !is_file($template)) { throw new RuntimeException('Missing native template'); }
include $template;
while (ob_get_level() > $base + 1) { ob_end_flush(); }
$html = ob_get_clean();
$marker = 'class="harmat-property-disclaimer"';
$position = strpos($html, $marker);
$body_start = strpos($html, '<body');
$body_end = strripos($html, '</body>');
if (substr_count($html, $marker) !== 1 || $position === false || $body_start === false
    || $body_end === false || $position <= $body_start || $position >= $body_end
    || substr_count($html, 'id="h22-offer-form"') !== 1
    || substr_count($html, 'id="h22-offer-modal"') !== 1
    || substr_count($html, 'id="harmat-internal-language"') !== 1
    || strpos($html, 'id="hm-cookie-settings-button"') === false
    || strpos($html, 'Fatal error:') !== false) {
    throw new RuntimeException('Candidate body/unique disclaimer/native shell failed');
}
$target = dirname($candidate) . '/native-' . $slug . '.html';
$handle = fopen($target, 'x+b');
if (!$handle) { throw new RuntimeException('Native proof path collision'); }
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
    throw new RuntimeException('Private native proof mismatch');
}
echo wp_json_encode(array('status' => 'PASS', 'slug' => $slug, 'body_disclaimer' => 1,
    'native_form' => 1, 'bytes' => strlen($html), 'sha256' => hash('sha256', $html)));
