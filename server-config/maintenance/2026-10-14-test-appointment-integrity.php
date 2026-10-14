<?php
/** Isolated public appointment validation; no WordPress, DB, network or mail. */
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) {
    exit(2);
}
define('ABSPATH', 'MOCK_NO_WORDPRESS');
class WP_REST_Request {
    public function __construct(private array $params, private string $route = '/harmat-sales-manager/v1/offer', private string $method = 'POST') {}
    public function get_param($key) { return $this->params[$key] ?? null; }
    public function set_param($key, $value) { $this->params[$key] = $value; }
    public function get_route() { return $this->route; }
    public function get_method() { return $this->method; }
}
class WP_Error {
    public function __construct(public string $code, public string $message, public array $data) {}
}
class WP_Query {
    public array $posts = [];
    public function __construct($args) {}
}
function add_filter(...$args) {}
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return stripslashes($value); }
function wp_parse_url($value, $component = -1) { return parse_url($value, $component); }
function home_url($path) { return 'https://harmat22.hu' . $path; }
function wp_timezone() { return new DateTimeZone('Europe/Budapest'); }
function wp_date($format) { return '2026-10-14'; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'TEST_VALID_NONCE'; }
function url_to_postid($url) { return 0; }
function get_post_type($id) { return ''; }
require __DIR__ . '/../../wp-mu-plugins/zz-harmat-public-offer-integrity.php';

$results = [];
function check_case($name, $params, $expected, $route = '/harmat-sales-manager/v1/offer', $method = 'POST') {
    global $results;
    $_SERVER['HTTP_ORIGIN'] = 'https://harmat22.hu';
    $_SERVER['HTTP_REFERER'] = 'https://harmat22.hu/';
    $actual = harmat_offer_integrity_filter(null, [], new WP_REST_Request($params, $route, $method));
    $code = $actual instanceof WP_Error ? $actual->code : null;
    $results[] = ['case' => $name, 'expected' => $expected, 'actual' => $code, 'pass' => $code === $expected];
}
$sources = [
    'outdoor' => html_entity_decode('K&uuml;lt&eacute;ri hirdet&eacute;s', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    'google' => html_entity_decode('Google keres&eacute;s', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    'ingatlan' => 'ingatlan.com',
    'social' => html_entity_decode('K&ouml;z&ouml;ss&eacute;gi m&eacute;dia', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    'other' => html_entity_decode('Egy&eacute;b', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
];
foreach ($sources as $key => $value) {
    check_case('valid_source_' . $key, ['lead_source' => $value, 'your-date' => '2026-10-20', 'your-time' => '09:00-12:00'], null);
}
check_case('source_empty', [], null);
check_case('source_unknown', ['lead_source' => 'INVALID_TEST_SOURCE'], 'harmat_offer_integrity_bad_source');
foreach (['', '09:00-12:00', '12:00-15:00', '15:00-18:00'] as $time) {
    check_case('valid_time_' . $time, ['your-time' => $time], null);
}
check_case('time_unknown', ['your-time' => '03:00-05:00'], 'harmat_offer_integrity_bad_time');
foreach (['2026-10-14', '2026-10-20', '2028-02-29'] as $date) {
    check_case('valid_date_' . $date, ['your-date' => $date], null);
}
foreach (['2026-10-13', '2026-02-30', '2026-13-01', '20/10/2026', 'not-a-date'] as $date) {
    check_case('invalid_date_' . $date, ['your-date' => $date], 'harmat_offer_integrity_bad_date');
}
check_case('nonce_expired', ['_harmat_offer_nonce' => 'TEST_INVALID_NONCE'], 'harmat_offer_integrity_bad_nonce');
check_case('nonce_valid', ['_harmat_offer_nonce' => 'TEST_VALID_NONCE'], null);
check_case('property_unknown', ['selected-apartment' => 'INVALID_TEST_PROPERTY'], 'harmat_offer_integrity_bad_property');
check_case('source_cross_site', ['source_url' => 'https://example.invalid/'], 'harmat_offer_integrity_bad_origin');
check_case('unrelated_route_untouched', ['lead_source' => 'INVALID'], null, '/unrelated/v1/test');
check_case('unrelated_method_untouched', ['lead_source' => 'INVALID'], null, '/harmat-sales-manager/v1/offer', 'GET');
$failed = array_values(array_filter($results, fn($case) => !$case['pass']));
echo json_encode(['scope' => 'ISOLATED_NO_DB_NETWORK_MAIL', 'cases' => $results, 'count' => count($results), 'failures' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
exit($failed ? 1 : 0);
