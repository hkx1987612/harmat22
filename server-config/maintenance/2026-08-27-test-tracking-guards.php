<?php
// Isolated WordPress hook stubs: no database, email, HTTP or production tag execution.
define('ABSPATH', __DIR__);
$state = array();
$callback = null;
function add_action($hook, $handler, $priority = 10) {
    global $callback;
    if ($hook === 'wp_footer' && $priority === 110) $callback = $handler;
}
function is_admin() { return !empty($GLOBALS['state']['admin']); }
function wp_doing_ajax() { return !empty($GLOBALS['state']['ajax']); }
function is_user_logged_in() { return !empty($GLOBALS['state']['logged_in']); }
function is_feed() { return !empty($GLOBALS['state']['feed']); }
function is_404() { return !empty($GLOBALS['state']['404']); }
function wp_is_json_request() { return !empty($GLOBALS['state']['json']); }
function wp_unslash($value) { return $value; }
function get_option($key, $default = false) { return $GLOBALS['state']['settings'] ?? array('measurementID' => 'G-TEST123'); }
function hm_legal_policy_version_20260601() { return 'test-policy'; }
function is_page($slug) { return $_SERVER['REQUEST_URI'] === '/' . $slug . '/'; }
function wp_json_encode($data) { return json_encode($data); }
require __DIR__ . '/../../wp-mu-plugins/harmat-unified-offer-modal.php';
require __DIR__ . '/../../wp-mu-plugins/zz-harmat-confirmed-lead-tracking.php';
function output() {
    ob_start();
    ($GLOBALS['callback'])();
    return ob_get_clean();
}
function check($condition, $label) {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS $label\n";
}
function configuration() {
    preg_match('/var config = (.+);/', output(), $match);
    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
}
foreach (array('/sales/', '/agent/', '/client/', '/customer/', '/ugyfel/', '/lawyer/', '/wp-admin/', '/wp-login.php', '/wp-json/') as $path) {
    $_SERVER['REQUEST_URI'] = $path;
    check(output() === '', 'private path excluded: ' . $path);
}
$_SERVER['REQUEST_URI'] = '/';
foreach (array('admin', 'ajax', 'logged_in', 'feed', '404', 'json') as $flag) {
    $state = array($flag => true);
    check(output() === '', 'request excluded: ' . $flag);
}
$state = array();
check(configuration()['ads'] === 'AW-18191634808/7FpbCJ-ahLQcEPiiueJD', 'public page has exact Ads label');
check(configuration()['analytics'] === 'G-TEST123', 'GA4 measurement ID configured');
$_SERVER['REQUEST_URI'] = '/koszonjuk/';
check(configuration()['thankYou'] === true, 'thank-you recovery enabled only on matching page');
$state = array('settings' => array('measurementID' => 'not-a-ga4-id'));
check(configuration()['analytics'] === '', 'invalid analytics destination ignored');
