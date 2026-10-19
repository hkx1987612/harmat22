<?php
namespace HarmatHomeVideoFullscreenFixture;
if (PHP_SAPI !== 'cli') exit(1);
\define('ABSPATH', __DIR__ . '/');
$checks = 0;
$state = array('context' => true, 'helper' => true, 'customize' => false, 'flags' => array(), 'headers' => array());
$actions = array();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_GET = array();
function check($ok, $name) { global $checks; ++$checks; if (!$ok) throw new \RuntimeException('FAIL ' . $name); }
function function_exists($name) { global $state; return $name === 'harmat_home_family_context' ? $state['helper'] : \function_exists(__NAMESPACE__ . '\\' . $name); }
function harmat_home_family_context() { global $state; return $state['context']; }
function is_customize_preview() { global $state; return $state['customize']; }
function defined($name) { global $state; return isset($state['flags'][$name]) || \defined($name); }
function constant($name) { global $state; return $state['flags'][$name] ?? \constant($name); }
function headers_list() { global $state; return $state['headers']; }
function add_action(...$args) { global $actions; $actions[] = $args; }
function output() { ob_start(); harmat_home_video_fullscreen_footer(); return ob_get_clean(); }
$candidate = isset($argv[1]) && $argv[1] !== '--render' ? $argv[1] : dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-home-video-fullscreen.php';
$source = file_get_contents($candidate);
$tokens = token_get_all($source, TOKEN_PARSE);
$code = 'namespace ' . __NAMESPACE__ . ';';
foreach ($tokens as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
    $code .= is_array($token) ? $token[1] : $token;
}
eval($code);
if (in_array('--render', $argv, true)) { echo output(); exit; }
check($actions === array(array('wp_footer', 'harmat_home_video_fullscreen_footer', 100)), 'one footer hook at priority100');
check(harmat_home_video_fullscreen_context(), 'public GET');
$_SERVER['REQUEST_METHOD'] = 'HEAD';
check(harmat_home_video_fullscreen_context(), 'public HEAD');
foreach (array('POST', 'PUT', 'DELETE', 'OPTIONS', '') as $method) {
    $_SERVER['REQUEST_METHOD'] = $method;
    check(!harmat_home_video_fullscreen_context() && output() === '', 'no assets for method ' . $method);
}
$_SERVER['REQUEST_METHOD'] = 'GET';
foreach (array('sales', 'agent', 'client', 'customer', 'ugyfel', 'lawyer', 'wp-admin', 'wp-json', 'wp-login.php', '%73ales') as $path) {
    $_SERVER['REQUEST_URI'] = '/' . $path . '/';
    check(!harmat_home_video_fullscreen_context() && output() === '', 'private path ' . $path);
}
$_SERVER['REQUEST_URI'] = '/';
foreach (array('preview', 'preview_id', 'preview_nonce', 'elementor-preview', 'rest_route', 'feed', 'embed', 'customize_changeset_uuid', 'customize_theme', 'action', 'wc-ajax') as $key) {
    $_GET = array($key => null);
    check(!harmat_home_video_fullscreen_context() && output() === '', 'native query ' . $key);
}
$_GET = array();
foreach (array('helper', 'context') as $key) {
    $state[$key] = false;
    check(!harmat_home_video_fullscreen_context() && output() === '', 'existing helper required: ' . $key);
    $state[$key] = true;
}
$state['flags']['WP_INSTALLING'] = true;
check(!harmat_home_video_fullscreen_context() && output() === '', 'installing excluded');
$state['flags'] = array();
$state['customize'] = true;
check(!harmat_home_video_fullscreen_context() && output() === '', 'customizer excluded');
$state['customize'] = false;
foreach (array(301, 404, 500) as $status) {
    http_response_code($status);
    check(!harmat_home_video_fullscreen_context() && output() === '', 'response ' . $status);
}
http_response_code(200);
$state['headers'] = array('Content-Type: application/json');
check(!harmat_home_video_fullscreen_context() && output() === '', 'nonHTML excluded');
$state['headers'] = array('Content-Type: text/html; charset=UTF-8');
check(harmat_home_video_fullscreen_context(), 'HTML allowed');
$assets = output();
check(substr_count($assets, 'id="harmat-home-video-fullscreen-css"') === 1, 'own style marker');
check(substr_count($assets, 'id="harmat-home-video-fullscreen-js"') === 1, 'own script marker');
check(output() === '', 'footer emission idempotent');
check(!preg_match('~<(?:form|input|iframe|link)\b|\bsrc=~i', $assets), 'inline only; no forms or external resources');
echo json_encode(array('status' => 'PASS', 'checks' => $checks, 'scope' => 'STANDALONE_PHP_GUARDS_NO_NETWORK_NO_SUBMISSION'));
