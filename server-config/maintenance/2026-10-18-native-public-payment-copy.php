<?php
// Private candidate render, never installed on the public web root.
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WP_CLI')) exit(1);
$candidate = $argv[1] ?? '';
$route = $argv[2] ?? '';
$without = ($argv[3] ?? '') === '--without';
$routes = array('/property/a1-1-l2/', '/property/a1-4-l5/', '/finanszirozas/');
if (!preg_match('~\A/home/harmath2/codex-backups/public-payment-copy-[a-f0-9]{32}/zz-harmat-public-payment-copy\.php\z~D', $candidate)
    || !is_file($candidate) || is_link($candidate) || realpath($candidate) !== $candidate
    || (fileperms(dirname($candidate)) & 0077) || (fileperms($candidate) & 0077)
    || !in_array($route, $routes, true)) exit(2);
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
http_response_code(200);
if (function_exists('harmat_ppc_html')) throw new RuntimeException('Candidate already active');
if (!$without) require $candidate;
$level = ob_get_level();
ob_start();
do_action('template_redirect');
$template = is_singular('property') ? get_single_template() : get_page_template();
$template = apply_filters('template_include', $template);
if (!is_string($template) || !is_file($template)) throw new RuntimeException('Native template missing');
include $template;
while (ob_get_level() > $level + 1) ob_end_flush();
$html = ob_get_clean();
while (ob_get_level()) ob_end_clean();
$file = dirname($candidate) . '/native-' . substr(hash('sha256', $route), 0, 12) . ($without ? '-without' : '-with') . '.html';
if (file_exists($file)) throw new RuntimeException('Private render collision');
$f = fopen($file, 'x+b');
if (!$f) throw new RuntimeException('Cannot create private proof');
fwrite($f, $html); fclose($f);
echo json_encode(array('route' => $route, 'bytes' => strlen($html), 'sha256' => hash('sha256', $html), 'file' => basename($file)));
