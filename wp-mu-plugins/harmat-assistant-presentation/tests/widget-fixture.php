<?php
// Isolated genuine render_widget, never bootstrap WordPress or touch customer data.
define('ABSPATH', __DIR__);
if (in_array('--rest', $argv, true)) { define('REST_REQUEST', true); }
if (in_array('--cli', $argv, true)) { define('WP_CLI', true); }
$flags = array();
$hooks = array();
class WP_Post {
    public $post_status = 'publish';
    public $post_password = '';
    public $post_type = 'page';
    public $post_name = 'home';
}
$post = new WP_Post();
function add_action($hook, $callback, $priority = 10) { $GLOBALS['hooks'][] = array($hook, $callback, $priority); }
function remove_action(...$args) {}
function add_shortcode(...$args) {}
function is_admin() { return !empty($GLOBALS['flags']['admin']); }
function wp_doing_ajax() { return !empty($GLOBALS['flags']['ajax']); }
function wp_doing_cron() { return !empty($GLOBALS['flags']['cron']); }
function is_feed() { return !empty($GLOBALS['flags']['feed']); }
function is_embed() { return !empty($GLOBALS['flags']['embed']); }
function is_search() { return !empty($GLOBALS['flags']['search']); }
function is_404() { return !empty($GLOBALS['flags']['404']); }
function is_preview() { return !empty($GLOBALS['flags']['preview']); }
function get_queried_object() { return $GLOBALS['post']; }
function wp_unslash($value) { return $value; }
function rest_url($value) { return 'https://harmat22.hu/wp-json/' . $value; }
function esc_url_raw($value) { return $value; }
function wp_create_nonce(...$args) { return 'isolated-unused-nonce'; }
function get_locale() { return getenv('HAP_TEST_LOCALE') ?: 'hu_HU'; }
function wp_json_encode($value) { return json_encode($value); }
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$root = dirname(__DIR__, 3);
require $root . '/wp-plugins/harmat-local-assistant/harmat-local-assistant.php';
require getenv('HAP_TEST_CONTROLLER') ?: dirname(__DIR__, 2) . '/zz-harmat-assistant-presentation.php';
if (in_array('--render', $argv, true)) {
    $GLOBALS['harmat_local_assistant']->render_widget();
    harmat_assistant_presentation_footer();
    exit;
}
$count = 0;
function check($value, $name) { if (!$value) { throw new RuntimeException($name); } $GLOBALS['count']++; }
if (defined('REST_REQUEST') || defined('WP_CLI')) {
    check(!harmat_assistant_presentation_scope(), 'REST/CLI blocked');
} else {
    check(harmat_assistant_presentation_scope(), 'published public page');
    foreach (array('admin', 'ajax', 'cron', 'feed', 'embed', 'search', '404', 'preview') as $flag) {
        $flags[$flag] = true; check(!harmat_assistant_presentation_scope(), $flag . ' blocked'); $flags = array();
    }
    foreach (array('POST', 'PUT', 'DELETE') as $method) {
        $_SERVER['REQUEST_METHOD'] = $method; check(!harmat_assistant_presentation_scope(), $method . ' blocked');
    }
    $_SERVER['REQUEST_METHOD'] = 'HEAD'; check(harmat_assistant_presentation_scope(), 'public HEAD');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    foreach (array('/sales', '/sales-admin/x', '/agent/', '/client/x', '/customer/', '/ugyfel/', '/lawyer/', '/wp-json/x', '/wp-admin/', '/wp-login.php', '/%73ales/') as $path) {
        $_SERVER['REQUEST_URI'] = $path; check(!harmat_assistant_presentation_scope(), $path . ' blocked');
    }
    $_SERVER['REQUEST_URI'] = '/';
    foreach (array('sales', 'sales-admin', 'agent', 'client', 'customer', 'ugyfel', 'lawyer') as $slug) {
        $post->post_name = $slug; check(!harmat_assistant_presentation_scope(), 'queried private slug');
    }
    $post->post_name = 'home';
    foreach (array('draft', 'private') as $status) {
        $post->post_status = $status; check(!harmat_assistant_presentation_scope(), 'unpublished blocked');
    }
    $post->post_status = 'publish'; $post->post_password = 'secret'; check(!harmat_assistant_presentation_scope(), 'password blocked');
    $post->post_password = ''; $post->post_type = 'post'; check(!harmat_assistant_presentation_scope(), 'unapproved post type');
    $post->post_type = 'property'; check(harmat_assistant_presentation_scope(), 'public property');
    $post = null; check(!harmat_assistant_presentation_scope(), 'missing queried post'); $post = new WP_Post();
    foreach (harmat_assistant_presentation_assets() as $file => $pin) {
        check(hash_file('sha256', dirname(__DIR__) . '/' . $file) === $pin, 'asset hash ' . $file);
    }
    ob_start(); harmat_assistant_presentation_footer(); $output = ob_get_clean();
    check(substr_count($output, 'id="harmat-assistant-presentation-style"') === 1, 'style marker');
    check(substr_count($output, 'id="harmat-assistant-presentation-script"') === 1, 'script marker');
    check(strpos($output, '<script src=') === false && strpos($output, '<link') === false, 'inline assets only');
    check(in_array(array('wp_footer', 'harmat_assistant_presentation_footer', 1100), $hooks, true), 'late isolated footer');
}
echo 'PASS ' . $count . " PHP assertions\n";
