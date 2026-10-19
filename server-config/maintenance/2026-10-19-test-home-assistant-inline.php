<?php
namespace HarmatHomeAssistantInlineFixture;
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
function output() { ob_start(); harmat_home_assistant_inline_footer(); return ob_get_clean(); }
$candidate = isset($argv[1]) && $argv[1] !== '--render' ? $argv[1] : dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-home-assistant-inline.php';
$source = file_get_contents($candidate);
$tokens = token_get_all($source, TOKEN_PARSE);
$code = 'namespace ' . __NAMESPACE__ . ';';
foreach ($tokens as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
    $code .= is_array($token) ? $token[1] : $token;
}
eval($code);
if (in_array('--render', $argv, true)) { echo output(); exit; }
check($actions === array(array('wp_footer', 'harmat_home_assistant_inline_footer', 1200)), 'one footer hook after HAP1100');
check(harmat_home_assistant_inline_context(), 'public GET');
$_SERVER['REQUEST_METHOD'] = 'HEAD';
check(harmat_home_assistant_inline_context(), 'public HEAD');
foreach (array('POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'get', '') as $method) {
    $_SERVER['REQUEST_METHOD'] = $method;
    check(!harmat_home_assistant_inline_context() && output() === '', 'no assets for method ' . $method);
}
unset($_SERVER['REQUEST_METHOD']);
check(!harmat_home_assistant_inline_context() && output() === '', 'missing method');
$_SERVER['REQUEST_METHOD'] = 'GET';
foreach (array('sales', 'sales-admin', 'agent', 'client', 'customer', 'ugyfel', 'lawyer', 'wp-admin', 'wp-json', 'wp-login.php', '%73ales', 'SALES') as $path) {
    $_SERVER['REQUEST_URI'] = '/' . $path . '/';
    check(!harmat_home_assistant_inline_context() && output() === '', 'private path ' . $path);
}
$_SERVER['REQUEST_URI'] = '';
check(!harmat_home_assistant_inline_context() && output() === '', 'missing path');
$_SERVER['REQUEST_URI'] = '/';
foreach (array('preview', 'preview_id', 'preview_nonce', 'elementor-preview', 'rest_route', 'feed', 'embed', 'customize_changeset_uuid', 'customize_theme', 'action', 'wc-ajax') as $key) {
    foreach (array(null, '') as $value) {
        $_GET = array($key => $value);
        check(!harmat_home_assistant_inline_context() && output() === '', 'query exclusion even empty/null ' . $key);
    }
}
$_GET = array('utm_source' => 'fixture');
check(harmat_home_assistant_inline_context(), 'ordinary attribution query allowed');
$_GET = array();
foreach (array('helper', 'context') as $key) {
    $state[$key] = false;
    check(!harmat_home_assistant_inline_context() && output() === '', 'existing strict home identity/context required: ' . $key);
    $state[$key] = true;
}
foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST', 'WP_INSTALLING') as $flag) {
    $state['flags'][$flag] = true;
    check(!harmat_home_assistant_inline_context() && output() === '', $flag . ' excluded');
    $state['flags'][$flag] = false;
    check(harmat_home_assistant_inline_context(), $flag . ' false allowed');
    $state['flags'] = array();
}
$state['customize'] = true;
check(!harmat_home_assistant_inline_context() && output() === '', 'customizer excluded');
$state['customize'] = false;
foreach (array(201, 204, 206, 301, 302, 403, 404, 500, 503) as $status) {
    http_response_code($status);
    check(!harmat_home_assistant_inline_context() && output() === '', 'response ' . $status);
}
http_response_code(200);
foreach (array('application/json', 'text/plain', 'application/xhtml+xml') as $type) {
    $state['headers'] = array('Content-Type: ' . $type);
    check(!harmat_home_assistant_inline_context() && output() === '', 'nonHTML excluded ' . $type);
}
$state['headers'] = array('Content-Type: text/html; charset=UTF-8');
check(harmat_home_assistant_inline_context(), 'HTML allowed');
$assets = output();
check(substr_count($assets, 'id="harmat-home-assistant-inline-style"') === 1, 'own style marker');
check(substr_count($assets, 'id="harmat-home-assistant-inline-script"') === 1, 'own script marker');
check(output() === '', 'footer emission idempotent');
check(!preg_match('~<(?:form|input|iframe|link)\b|\bsrc=~i', $assets), 'inline only; no forms or external resources');
echo json_encode(array('status' => 'PASS', 'checks' => $checks, 'scope' => 'STANDALONE_PHP_GUARDS_NO_NETWORK_NO_SUBMISSION'));
