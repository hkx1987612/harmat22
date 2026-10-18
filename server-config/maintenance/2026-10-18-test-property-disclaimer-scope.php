<?php
// Offline synthetic shell only. No WordPress boot, native handlers, network or writes.
namespace HarmatPropertyDisclaimerFixture;

use DOMDocument;
use DOMXPath;
use DOMElement;

if (PHP_SAPI !== 'cli') { exit(1); }
\define('ABSPATH', __DIR__ . '/');
$root = dirname(__DIR__, 2);
$candidate = isset($argv[1]) && strpos($argv[1], '--') !== 0
    ? $argv[1] : $root . '/wp-mu-plugins/zz-harmat-property-disclaimer.php';
$legacy_path = isset($argv[2]) && strpos($argv[2], '--') !== 0
    ? $argv[2] : $root . '/wp-mu-plugins/harmat-migrated-snippets.php';
if (!is_file($candidate) || !is_readable($candidate) || !is_file($legacy_path) || !is_readable($legacy_path)) {
    fwrite(STDERR, "Supply readable candidate module and legacy paths as argv[1] and argv[2].\n");
    exit(1);
}
$source = file_get_contents($candidate);
$legacy_source = file_get_contents($legacy_path);
$checks = 0;
$state = array();
$hooks = array();
$buffers = array();

class WP_Post {
    public $post_type = 'property';
    public $post_status = 'publish';
    public $post_password = '';
    public $post_name = 'a1-f-l1';
}
function check($condition, $label) {
    global $checks;
    $checks++;
    if (!$condition) { throw new \RuntimeException('FAIL: ' . $label); }
}
function reset_state() {
    global $state, $buffers;
    $state = array('post' => new WP_Post(), 'flags' => array(), 'module' => true, 'code' => 200);
    $buffers = array();
    $_SERVER = array('REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/property/a1-f-l1/');
    $_GET = array();
}
function defined($name) { global $state; return array_key_exists($name, $state['flags']) || \defined($name); }
function constant($name) { global $state; return $state['flags'][$name] ?? \constant($name); }
function function_exists($name) {
    global $state;
    if ($name === 'harmat_property_disclaimer_scope') { return $state['module']; }
    return \function_exists(__NAMESPACE__ . '\\' . $name) || \function_exists($name);
}
function class_exists($name) { global $state; return !($state['no_dom'] ?? false) && \class_exists($name); }
function is_admin() { global $state; return $state['admin'] ?? false; }
function wp_doing_ajax() { global $state; return $state['ajax'] ?? false; }
function wp_doing_cron() { global $state; return $state['cron'] ?? false; }
function is_feed() { global $state; return $state['feed'] ?? false; }
function is_preview() { global $state; return $state['preview'] ?? false; }
function is_404() { global $state; return $state['404'] ?? false; }
function is_search() { global $state; return $state['search'] ?? false; }
function is_embed() { global $state; return $state['embed'] ?? false; }
function is_trackback() { global $state; return $state['trackback'] ?? false; }
function wp_is_json_request() { global $state; return $state['json'] ?? false; }
function is_main_query() { global $state; return !($state['secondary'] ?? false); }
function is_singular($type) { global $state; return $type === 'property' && !($state['archive'] ?? false); }
function get_queried_object() { global $state; return $state['post']; }
function http_response_code() { global $state; return $state['code']; }
function headers_list() { global $state; return $state['headers'] ?? array(); }
function headers_sent() { global $state; return $state['sent'] ?? false; }
function add_action(...$args) { global $hooks; $hooks[] = $args; }
function ob_start($callback) { global $buffers; $buffers[] = $callback; return true; }

reset_state();
$tokens = token_get_all($source);
check($tokens[0][0] === T_OPEN_TAG, 'PHP source opening');
$code = '';
foreach (array_slice($tokens, 1) as $token) { $code .= is_array($token) ? $token[1] : $token; }
eval('namespace ' . __NAMESPACE__ . '; use \\DOMDocument; use \\DOMXPath; use \\DOMElement;' . $code);

function legacy_script() {
    global $legacy_source;
    preg_match('~<script id="harmat-migrated-public-cleanup-js">[\s\S]*?</script>~', $legacy_source, $match);
    if (!$match) { throw new \RuntimeException('Missing legacy script'); }
    \ob_start();
    // Execute only the one guard expression embedded in this script, never the legacy plugin.
    eval('namespace ' . __NAMESPACE__ . '; ?>' . $match[0]);
    return \ob_get_clean();
}
function fixture_html() {
    return '<!doctype html><html lang="hu"><head><meta charset="UTF-8">'
        . '<style id="harmat-migrated-public-cleanup-css">.harmat-property-disclaimer{width:auto}</style>'
        . legacy_script() . '</head><body class="single-property"><header>Header</header>'
        . '<main id="main"><h1>A1-F-L1</h1><a id="quote" href="#h22-offer-modal">Quote</a></main>'
        . '<footer id="colophon" class="site-footer harmat-clean-footer" role="contentinfo">Footer</footer>'
        . '<div id="h22-offer-modal" hidden><form id="h22-offer-form"><input name="your-name" value="Customer">'
        . '<input name="date" type="date" value="2026-10-21"><input name="time" type="time" value="10:30">'
        . '<select name="source"><option value="Google">Google</option></select>'
        . '<textarea name="your-message">Untouched draft</textarea><input type="checkbox" name="privacy">'
        . '<button type="submit">Send</button></form></div><script id="native-fixture">window.nativeFixture=1;</script>'
        . '</body></html>';
}

if (in_array('--render', $argv, true)) { echo harmat_property_disclaimer_output(fixture_html()); exit; }
if (in_array('--fallback', $argv, true)) { $state['module'] = false; echo fixture_html(); exit; }

check($hooks === array(array('template_redirect', 'harmat_property_disclaimer_buffer', -3000)), 'one early outer-buffer hook only');
check(harmat_property_disclaimer_scope(), 'public exact property GET');
$_SERVER['REQUEST_METHOD'] = 'HEAD';
check(harmat_property_disclaimer_scope(), 'public exact property HEAD');
$_SERVER['REQUEST_URI'] .= '?utm_source=test';
$_GET['utm_source'] = 'test';
check(harmat_property_disclaimer_scope(), 'non-preview tracking query allowed');
reset_state();
$base = fixture_html();
$rendered = harmat_property_disclaimer_output($base);
$lf = str_replace("\r\n", "\n", $base);
$crlf = str_replace("\n", "\r\n", $lf);
check(harmat_property_disclaimer_output($lf) !== $lf, 'LF legacy output accepted');
check(harmat_property_disclaimer_output($crlf) !== $crlf, 'CRLF legacy output accepted without source normalization');
check(str_replace("\r\n", "\n", harmat_property_disclaimer_output($crlf)) === harmat_property_disclaimer_output($lf),
    'CRLF/LF rendering parity');
$on = 'if (true /* harmat-property-disclaimer-legacy */) addPropertyDisclaimer();';
$off = 'if (false /* harmat-property-disclaimer-legacy */) addPropertyDisclaimer();';
check($base !== $rendered, 'candidate renders');
check(substr_count($rendered, $off) === 1 && strpos($rendered, $on) === false, 'retire only validated invocation');
check(str_replace(array(harmat_property_disclaimer_html(), $off), array('', $on), $rendered) === $base,
    'every other native shell/form/script byte identical');
check(harmat_property_disclaimer_output($rendered) === $rendered, 'repeat filtering is byte-idempotent');
preg_match("~note\\.innerHTML = '([^']+)';~", $legacy_source, $note);
check(harmat_property_disclaimer_html() === '<div class="harmat-property-disclaimer" data-harmat-property-disclaimer="1">' . $note[1] . '</div>',
    'Hungarian wording and strong markup byte-exact with old owner');
$parsed = harmat_property_disclaimer_document($rendered);
check($parsed[1]->query('//body//div[@data-harmat-property-disclaimer="1"]/strong')->length === 1, 'one normal in-body strong note');
check($parsed[1]->query('//form')->length === 1, 'exactly one untouched form');
check($parsed[1]->query('//html/div[@data-harmat-property-disclaimer]')->length === 0, 'no HTML-child note');
$state['module'] = false;
check(strpos(legacy_script(), $on) !== false, 'missing module keeps original fallback invocation true');
$state['module'] = true;
$_SERVER['REQUEST_METHOD'] = 'POST';
check(strpos(legacy_script(), $off) !== false, 'installed module disables legacy on excluded request');
reset_state();

foreach (array('POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', '', 'get') as $method) {
    reset_state(); $_SERVER['REQUEST_METHOD'] = $method;
    check(!harmat_property_disclaimer_scope(), 'exclude method ' . $method);
    check(harmat_property_disclaimer_output($base) === $base, 'excluded method no response writes ' . $method);
}
foreach (array('admin', 'ajax', 'cron', 'feed', 'preview', '404', 'search', 'embed', 'trackback', 'json', 'secondary', 'archive') as $flag) {
    reset_state(); $state[$flag] = true;
    check(!harmat_property_disclaimer_scope(), 'exclude condition ' . $flag);
    check(strpos(legacy_script(), $off) !== false, 'legacy guard false ' . $flag);
    harmat_property_disclaimer_buffer();
    check($buffers === array(), 'no buffer ' . $flag);
}
foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
    reset_state(); $state['flags'][$flag] = true;
    check(!harmat_property_disclaimer_scope(), 'exclude runtime flag ' . $flag);
    $state['flags'][$flag] = false;
    check(harmat_property_disclaimer_scope(), 'false runtime flag allowed ' . $flag);
}
foreach (array('preview', 'preview_id', 'preview_nonce', 'elementor-preview', 'rest_route', 'feed', 'embed') as $key) {
    reset_state(); $_GET[$key] = '';
    check(!harmat_property_disclaimer_scope(), 'exclude query key even empty ' . $key);
}
foreach (array('post_status' => array('private', 'draft', 'pending', 'future', 'trash'),
    'post_password' => array('secret', '0'), 'post_type' => array('page', 'attachment'),
    'post_name' => array('', 'a1-f-l1/extra', 'A1-F-L1', '../a1-f-l1')) as $field => $values) {
    foreach ($values as $value) {
        reset_state(); $state['post']->$field = $value;
        check(!harmat_property_disclaimer_scope(), 'exclude post field ' . $field . ':' . $value);
    }
}
foreach (array(null, new \stdClass()) as $post) {
    reset_state(); $state['post'] = $post;
    check(!harmat_property_disclaimer_scope(), 'no valid WP_Post');
}
foreach (array('/', '/sales/', '/agent/', '/lawyer/', '/client/', '/wp-admin/', '/wp-json/',
    '/property/a1-f-l1', '/property/other/', '/property/a1-f-l1/extra/', '/property/a1-f-l1//',
    '/PROPERTY/a1-f-l1/', '/property/a1%2df-l1/', '/property/%61%31-f-l1/',
    '//harmat22.hu/property/a1-f-l1/', 'https://harmat22.hu/property/a1-f-l1/', '') as $uri) {
    reset_state(); $_SERVER['REQUEST_URI'] = $uri;
    check(!harmat_property_disclaimer_scope(), 'exclude nonexact route ' . $uri);
}
reset_state(); unset($_SERVER['REQUEST_METHOD']);
check(!harmat_property_disclaimer_scope(), 'missing request method');
reset_state(); unset($_SERVER['REQUEST_URI']);
check(!harmat_property_disclaimer_scope(), 'missing request URI');

foreach (array(false, 200, 201, 204, 301, 302, 403, 404, 500) as $status) {
    reset_state(); $state['code'] = $status;
    check((harmat_property_disclaimer_output($base) !== $base) === in_array($status, array(false, 200), true), 'HTTP status ' . var_export($status, true));
}
foreach (array('application/json', 'application/xhtml+xml', 'text/plain', 'text/htmlish', 'text/html; charset=UTF-8', 'text/html') as $type) {
    reset_state(); $state['headers'] = array('Content-Type: ' . $type);
    check((harmat_property_disclaimer_output($base) !== $base) === (strpos($type, 'text/html;') === 0 || $type === 'text/html'), 'content type ' . $type);
}
reset_state(); $state['no_dom'] = true;
check(harmat_property_disclaimer_output($base) === $base, 'missing DOM preserves fallback');
reset_state();
$bad = array('', 'not HTML', substr($base, 0, -14), str_replace('</body>', '', $base),
    str_replace('</body>', '</body></body>', $base), str_replace('<head>', '<head><head>', $base),
    $base . '<p>tail</p>', str_replace('single-property', 'single-page', $base),
    str_replace('id="colophon"', 'id="other-footer"', $base),
    str_replace('<footer id="colophon"', '<!-- <footer id="colophon"', $base),
    str_replace(array('<footer id="colophon"', '</footer>'), array('<!-- <footer id="colophon"', '</footer> -->'), $base),
    str_replace($on, 'addPropertyDisclaimer();', $base), str_replace($on, $on . $on, $base),
    str_replace('note.className =', 'note.className  =', $base),
    str_replace('harmat-migrated-public-cleanup-js', 'unowned-script', $base),
    str_replace('harmat-migrated-public-cleanup-css', 'unowned-style', $base),
    str_replace('.harmat-property-disclaimer{width:auto}', '', $base),
    str_replace('</body>', '<span class="harmat-property-disclaimer">Existing owner</span></body>', $base),
    str_replace('</body>', '<div data-harmat-property-disclaimer="1">Existing owner</div></body>', $base),
    str_replace('</body>', '<footer id="colophon"></footer></body>', $base),
    str_replace('<footer id="colophon"', '<form><footer id="colophon"', str_replace('</footer>', '</footer></form>', $base)),
    str_replace('<footer id="colophon"', '<template><footer id="colophon"', str_replace('</footer>', '</footer></template>', $base)),
    str_replace('<footer id="colophon"', '<main><footer id="colophon"', str_replace('</footer>', '</footer></main>', $base)));
foreach ($bad as $index => $html) {
    check(harmat_property_disclaimer_output($html) === $html, 'malformed/drift/duplicate fallback ' . $index);
}
foreach (array(null, false, 7, array()) as $value) {
    check(harmat_property_disclaimer_output($value) === $value, 'non-string unchanged');
}
reset_state();
harmat_property_disclaimer_buffer();
check(count($buffers) === 1, 'eligible request registers buffer');
$callback = $buffers[0];
check($callback($base, PHP_OUTPUT_HANDLER_START | PHP_OUTPUT_HANDLER_FINAL) === $rendered, 'complete final phase transforms');
foreach (array(PHP_OUTPUT_HANDLER_FLUSH, PHP_OUTPUT_HANDLER_CLEAN, 0) as $phase) {
    harmat_property_disclaimer_buffer(); $callback = $buffers[count($buffers) - 1];
    check($callback($base, PHP_OUTPUT_HANDLER_START | $phase) === $base, 'partial/flush/clean untouched ' . $phase);
    check($callback($base, PHP_OUTPUT_HANDLER_FINAL) === $base, 'prior partial permanently disables takeover ' . $phase);
}
foreach (array(PHP_OUTPUT_HANDLER_FINAL | PHP_OUTPUT_HANDLER_FLUSH, PHP_OUTPUT_HANDLER_FINAL | PHP_OUTPUT_HANDLER_CLEAN) as $phase) {
    harmat_property_disclaimer_buffer(); $callback = $buffers[count($buffers) - 1];
    check($callback($base, $phase) === $base, 'final flush/clean unchanged ' . $phase);
}
reset_state(); $state['sent'] = true;
harmat_property_disclaimer_buffer();
check($buffers === array(), 'headers already sent never registers buffer');

// libxml's normal mode must not print parser warnings or alter caller error mode.
reset_state();
$mode = libxml_use_internal_errors();
$captured = array();
set_error_handler(function ($severity, $message) use (&$captured) { $captured[] = $message; return true; });
\ob_start();
harmat_property_disclaimer_output(str_replace('<main id="main">', '<main id="main"><unknown-tag>&broken;', $base));
$noise = \ob_get_clean();
restore_error_handler();
check($captured === array() && $noise === '', 'LIBXML_NOERROR/NOWARNING emit no PHP warning/output for malformed markup');
check(libxml_use_internal_errors() === $mode, 'caller libxml mode unchanged');
echo 'PASS ' . $checks . " isolated property-disclaimer assertions\n";
