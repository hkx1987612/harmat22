<?php
// Isolated CLI fixtures only: no WordPress boot, network, database or deployment.
if (PHP_SAPI !== 'cli') exit(1);
define('ABSPATH', __DIR__);
$checks = 0;
$hooks = array();
$routes = array();
$posts = array();
$meta = array();
$query = null;

function check($ok, $name) {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $name);
}
class WP_REST_Response {
    public $data;
    public $status;
    public $headers;
    function __construct($data, $status, $headers) {
        $this->data = $data; $this->status = $status; $this->headers = $headers;
    }
}
class WP_Post {
    public $ID;
    public $post_type = 'property';
    public $post_status = 'publish';
    public $post_password = '';
    public $post_title = 'A1-F-L1';
    function __construct($id) { $this->ID = $id; }
}
function add_action($name, $callback, ...$unused) { $GLOBALS['hooks'][] = array($name, $callback); }
function add_filter(...$unused) {}
function register_rest_route($namespace, $route, $options) {
    $GLOBALS['routes'][] = array($namespace, $route, $options);
}
function home_url($path) { return 'https://example.test' . $path; }
function get_posts($args) { $GLOBALS['query'] = $args; return $GLOBALS['posts']; }
function get_permalink($id) {
    return $GLOBALS['bad_url'] ?? 'https://example.test/property/' . strtolower(get_the_title($id)) . '/';
}
function get_the_title($id) {
    foreach ($GLOBALS['posts'] as $post) if ($post->ID === $id) return $post->post_title;
    throw new RuntimeException('unknown fixture');
}
function get_post_meta($id, $key, $single) {
    if (!empty($GLOBALS['authority_failure'])) throw new RuntimeException('INTERNAL CUSTOMER');
    return $GLOBALS['meta'][$id][$key] ?? '';
}
// Any accidental persistence/network call fails the isolated contract immediately.
function update_option(...$args) { throw new RuntimeException('write forbidden'); }
function set_transient(...$args) { throw new RuntimeException('write forbidden'); }
function update_post_meta(...$args) { throw new RuntimeException('write forbidden'); }
function wp_remote_get(...$args) { throw new RuntimeException('network forbidden'); }
function wp_remote_post(...$args) { throw new RuntimeException('network forbidden'); }

$root = dirname(__DIR__, 2);
$plugin = $argv[1] ?? $root . '/wp-mu-plugins/zz-harmat-messenger-public-inventory.php';
require $plugin;
check($hooks === array(array('rest_api_init', 'harmat_mpi_register_route')), 'only REST hook');
harmat_mpi_register_route();
check($routes === array(array('harmat-public/v1', '/apartments', array(
    'methods' => 'GET', 'callback' => 'harmat_mpi_apartments', 'permission_callback' => '__return_true',
))), 'exact GET public permission registration');
check(harmat_mpi_apartments()->status === 503, 'missing dependency');
check(harmat_mpi_apartments()->headers === array('Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex'), 'error no-store/noindex');
check(strpos(harmat_mpi_apartments()->data['message'], 'lakásadatok') !== false, 'Hungarian error accents');

// Load the actual authority functions, registering but never executing its hooks.
require $argv[2] ?? $root . '/wp-mu-plugins/zz-harmat-search-ai-discovery.php';
function fixture($title = 'A1-F-L1') {
    $p = new WP_Post(1); $p->post_title = $title;
    $GLOBALS['posts'] = array($p);
    $GLOBALS['meta'] = array(1 => array(
        'property_rooms' => '2', 'property_building_area' => '40',
        'property_land_area' => '10', '_harmat_sales_area' => '47.83',
        'property_price' => '98765432', 'property_address_street' => 'INTERNAL ADDRESS',
        'property_address_street_number' => 'wrong floor',
        '_client_secret' => 'INTERNAL CUSTOMER',
    ));
    unset($GLOBALS['bad_url']);
}
fixture('a2-3-l12');
$r = harmat_mpi_apartments();
check($r->status === 200, 'valid response');
check(array_keys($r->data) === array('schema_version', 'generated_at', 'source', 'currency', 'items'), 'top-level allowlist');
check($r->data['schema_version'] === 1 && $r->data['currency'] === 'HUF', 'version currency');
check($r->data['source'] === home_url('/lakaskereso/'), 'canonical source');
check(preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $r->data['generated_at']) === 1, 'UTC ISO8601');
check(abs(time() - strtotime($r->data['generated_at'])) < 5, 'fresh generation');
check($r->headers === array('Cache-Control' => 'public, max-age=60, must-revalidate', 'X-Robots-Tag' => 'noindex'), 'bounded public cache/noindex no CORS');
$item = $r->data['items'][0];
check(array_keys($item) === array('code', 'url', 'building', 'floor', 'rooms', 'sales_area_m2', 'outdoor_area_m2', 'status', 'price_huf'), 'item allowlist');
check($item['code'] === 'A2-3-L12' && $item['building'] === 'A2' && $item['floor'] === '3', 'code-derived building floor');
check($item['sales_area_m2'] === 47.83, 'actual authority sales override');
check($item['url'] === get_permalink(1), 'actual permalink');
check($item['price_huf'] === 98765432 && $item['status'] === 'current', 'visible current price');
check(strpos(json_encode($r->data), 'INTERNAL') === false, 'no internal fields');
check($query === array('post_type' => 'property', 'post_status' => 'publish', 'has_password' => false,
    'numberposts' => 501, 'fields' => 'all', 'orderby' => 'title', 'order' => 'ASC',
    'cache_results' => true, 'update_post_meta_cache' => true,
    'update_post_term_cache' => false, 'suppress_filters' => true), 'bounded full-post cache query');
foreach (array('_harmat_hide_front_price' => 'yes', 'property_price_display' => 'no') as $field => $value) {
    fixture(); $meta[1][$field] = $value;
    $r = harmat_mpi_apartments();
    check($r->data['items'][0]['price_huf'] === null, 'hidden price ' . $field);
    check(strpos(json_encode($r->data), '98765432') === false, 'no hidden source amount ' . $field);
}
foreach (array('0', '-1', '') as $price) {
    fixture(); $meta[1]['property_price'] = $price;
    check(harmat_mpi_apartments()->data['items'][0]['price_huf'] === null, 'nonpositive price');
}
foreach (array('reserved', 'sold') as $status) {
    fixture();
    if ($status === 'sold') $meta[1]['property_status'] = 'sold';
    $meta[1]['property_under_offer'] = 'yes';
    check(harmat_mpi_apartments()->data['items'][0]['status'] === $status, 'authority status ' . $status);
}
foreach (array('private', 'draft', 'trash') as $status) {
    fixture(); $posts[0]->post_status = $status;
    check(harmat_mpi_apartments()->data['items'] === array(), 'exclude ' . $status);
}
fixture(); $posts[0]->post_password = 'synthetic';
check(harmat_mpi_apartments()->data['items'] === array(), 'exclude password');
fixture(); $posts[0]->post_type = 'page';
check(harmat_mpi_apartments()->data['items'] === array(), 'exclude nonproperty');
foreach (array('A5-F-L1', 'A1-0-L1', 'A1-10-L1', 'A1-F-L1 suffix', "A1-F-L1\n", 'garage', ' A1-F-L1') as $title) {
    fixture($title);
    check(harmat_mpi_apartments()->data['items'] === array(), 'strict apartment title');
}
foreach (array('A1-F-L1', 'A2-1-L2', 'A3-9-L3', 'A4-F-L4') as $title) {
    fixture($title);
    check(harmat_mpi_apartments()->data['items'][0]['code'] === $title, 'A1-A4 allowed');
}
$posts = array(); $r = harmat_mpi_apartments();
check($r->status === 200 && $r->data['items'] === array(), 'empty published inventory no fallback');
fixture(); $posts = array_fill(0, 501, $posts[0]);
check(harmat_mpi_apartments()->status === 503, '501 cap');
fixture(); $base_meta = $meta[1]; $posts = array();
for ($id = 1; $id <= 500; $id++) {
    $p = new WP_Post($id); $p->post_title = 'A1-F-L' . $id;
    $posts[] = $p; $meta[$id] = $base_meta;
}
$r = harmat_mpi_apartments();
check($r->status === 200 && count($r->data['items']) === 500, '500 cap boundary accepted');
fixture(); $GLOBALS['authority_failure'] = true;
$r = harmat_mpi_apartments();
unset($GLOBALS['authority_failure']);
check($r->status === 503, 'authority failure no fallback');
check(strpos(json_encode($r->data), 'INTERNAL') === false, 'authority failure redacted');
fixture(); $posts[] = clone $posts[0];
check(harmat_mpi_apartments()->status === 503, 'duplicate code fails closed');
foreach (array('property_rooms', '_harmat_sales_area', 'property_land_area') as $field) {
    fixture(); $meta[1][$field] = '-1';
    check(harmat_mpi_apartments()->status === 503, 'malformed numeric authority');
}
foreach (array(false, 'https://elsewhere.test/property/x/', 'javascript:alert(1)',
    'https://example.test/property/x/?secret=synthetic',
    'http://example.test/property/x/', 'https://example.test:8080/property/x/') as $url) {
    fixture(); $GLOBALS['bad_url'] = $url;
    check(harmat_mpi_apartments()->status === 503, 'invalid public permalink');
}
fixture(); $GLOBALS['bad_url'] = 'https://example.test:443/property/a1-f-l1/';
check(harmat_mpi_apartments()->status === 200, 'equivalent default HTTPS port');
// Token-level read-only allowlist: no dynamic callback execution or write APIs.
$calls = array(); $tokens = token_get_all(file_get_contents($plugin));
for ($i = 0; $i < count($tokens); $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING) continue;
    $j = $i + 1;
    while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
    if (($tokens[$j] ?? null) === '(') $calls[] = $tokens[$i][1];
}
$allowed = array('defined', 'harmat_mpi_unavailable', 'WP_REST_Response', 'html_entity_decode',
    'harmat_mpi_number', 'is_int', 'is_float', 'is_finite', 'harmat_mpi_public_url', 'is_string',
    'filter_var', 'parse_url', 'is_array', 'in_array', 'strtolower', 'harmat_mpi_apartments',
    'function_exists', 'home_url', 'get_posts', 'count', 'harmat_sai_property_summary_data',
    'preg_match', 'strtoupper', 'array_key_exists', 'is_bool', 'get_permalink', 'substr',
    'gmdate', 'harmat_mpi_register_route', 'register_rest_route', 'add_action');
check(array_diff($calls, $allowed) === array(), 'read-only function-call allowlist');
echo 'PASS: ' . $checks . " checks; isolated local fixtures only, not deployed.\n";
