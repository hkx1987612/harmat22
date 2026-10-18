<?php
// Private full-theme render: no form, REST, mail or cron dispatch.
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WP_CLI')) { exit(1); }
$candidate = $argv[1] ?? '';
$route = $argv[2] ?? '/';
$without = ($argv[3] ?? '') === '--without';
if (!preg_match('~\A/home/harmath2/codex-backups/assistant-presentation-[a-f0-9]{32}/zz-harmat-assistant-presentation\.php\z~D', $candidate)
    || !is_file($candidate) || is_link($candidate) || realpath($candidate) !== $candidate
    || (fileperms(dirname($candidate)) & 0077) !== 0 || (fileperms($candidate) & 0077) !== 0
    || !in_array($route, array('/', '/lakaskereso/', '/property/a1-1-l2/', '/virtualis-lakasvalaszto/',
        '/virtualis-lakasvalaszto-elso-utem/', '/galeria/', '/elerhetosegeink/', '/epitesi-naplo/', '/koszonjuk/'), true)) { exit(2); }
umask(0077);
define('DISABLE_WP_CRON', true);
define('DONOTCACHEPAGE', true);
define('WP_USE_THEMES', true);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'harmat22.hu';
$_SERVER['REQUEST_URI'] = $route;
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
require '/home/harmath2/public_html/wp-load.php';
wp();
if (function_exists('harmat_assistant_presentation_footer')) { throw new RuntimeException('Candidate already active'); }
require $candidate;
if (!harmat_assistant_presentation_scope()) { throw new RuntimeException('Native scope failed'); }
if ($without) { remove_action('wp_footer', 'harmat_assistant_presentation_footer', 1100); }
$base = ob_get_level();
ob_start();
do_action('template_redirect');
$template = is_front_page() ? get_front_page_template() : (is_singular('property') ? get_single_template() : get_page_template());
$template = apply_filters('template_include', $template);
if (!is_string($template) || !is_file($template)) { throw new RuntimeException('Native template missing'); }
include $template;
while (ob_get_level() > $base + 1) { ob_end_flush(); }
$html = ob_get_clean();
$result = array('route' => $route, 'bytes' => strlen($html), 'resources' => array());
if (!preg_match('~<style\b[^>]*id="harmat-local-assistant-style"[^>]*>[\s\S]*?<script\b[^>]*id="harmat-local-assistant-script"[^>]*>[\s\S]*?</script>~', $html, $native)) {
    throw new RuntimeException('Native widget missing');
}
$result['native_widget_sha256'] = hash('sha256', $native[0]);
$proof = dirname($candidate) . '/native-widget-' . substr(hash('sha256', $route), 0, 12) . ($without ? '-without' : '-with') . '.html';
if (file_exists($proof)) {
    if (is_link($proof) || !is_file($proof) || file_get_contents($proof) !== $native[0]) { throw new RuntimeException('Private widget proof drift'); }
} else {
    $file = fopen($proof, 'x+b');
    if (!$file) { throw new RuntimeException('Private widget proof create failed'); }
    fwrite($file, $native[0]); fclose($file);
}
if (!$without) {
foreach (array('style' => 'style', 'script' => 'script') as $kind => $tag) {
    if (!preg_match('~<' . $tag . '\b[^>]*id="harmat-assistant-presentation-' . $kind . '"[^>]*>([\s\S]*?)</' . $tag . '>~', $html, $match)) {
        throw new RuntimeException('Native presentation resource missing');
    }
    $name = 'assistant-presentation.' . ($kind === 'style' ? 'css' : 'js');
    $expected = file_get_contents(dirname($candidate) . '/harmat-assistant-presentation/' . $name);
    if (substr($expected, -1) === "\n") { $expected = substr($expected, 0, -1); }
    $actual = $match[1];
    $difference = null;
    for ($i = 0, $len = min(strlen($actual), strlen($expected)); $i < $len; $i++) {
        if ($actual[$i] !== $expected[$i]) { $difference = $i; break; }
    }
    $result['resources'][$kind] = array('expected' => hash('sha256', $expected), 'actual' => hash('sha256', $actual),
        'expected_bytes' => strlen($expected), 'actual_bytes' => strlen($actual), 'first_difference' => $difference,
        'expected_context' => $difference === null ? null : substr($expected, max(0, $difference - 45), 120),
        'actual_context' => $difference === null ? null : substr($actual, max(0, $difference - 45), 120));
}
}
while (ob_get_level() > 0) { ob_end_clean(); }
foreach ($result['resources'] as $resource) {
    if ($resource['expected'] !== $resource['actual']) { throw new RuntimeException('Native inline content mismatch'); }
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
