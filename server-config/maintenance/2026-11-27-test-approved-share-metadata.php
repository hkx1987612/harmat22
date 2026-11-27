<?php
// Isolated public metadata fixtures; WordPress, forms and database are not booted.
$root = dirname(__DIR__, 2);
define('ABSPATH', $root);
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/harmat-seo-' . bin2hex(random_bytes(8)));

class WP_Post {
    public function __construct(public int $ID, public string $post_type, public string $post_name,
        public string $post_status = 'publish', public string $post_password = '') {}
}

$hooks = $flags = array();
$tested = 0;
$post = null;
function add_filter($hook, $callback, $priority, $args = 1) { $GLOBALS['hooks'][$hook] = $callback; }
function add_action(...$args) { add_filter(...$args); }
function is_admin() { return isset($GLOBALS['flags']['admin']); }
function wp_doing_ajax() { return isset($GLOBALS['flags']['ajax']); }
function wp_is_json_request() { return isset($GLOBALS['flags']['json']); }
function is_feed() { return isset($GLOBALS['flags']['feed']); }
function is_search() { return isset($GLOBALS['flags']['search']); }
function is_404() { return isset($GLOBALS['flags']['404']); }
function is_preview() { return isset($GLOBALS['flags']['preview']); }
function is_singular() { return !isset($GLOBALS['flags']['archive']); }
function get_queried_object() { return $GLOBALS['post']; }
function content_url($path) { return 'https://harmat22.hu/wp-content' . $path; }
function get_permalink($post) { return 'https://harmat22.hu/' . ($post->post_type === 'property' ? 'property/' : '') . $post->post_name . '/'; }
function harmat_sai_property_summary_data($id) { return $GLOBALS['unit']; }
function harmat_sai_format_area($value) { return number_format($value, 2, ',', ' ') . ' m²'; }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function check($ok, $label) {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    $GLOBALS['tested']++;
}

require $root . '/wp-mu-plugins/zz-harmat-approved-share-metadata.php';
check(array_keys($hooks) === array('wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description',
    'wpseo_frontend_presentation', 'wpseo_opengraph_image', 'wpseo_twitter_image', 'wpseo_schema_graph', 'wpseo_head'), 'metadata-only hooks');

foreach (array('magunkrol', 'lakaskereso', 'epitesi-naplo') as $id => $page) {
    $post = new WP_Post($id + 1, 'page', $page);
    $description = harmat_asm_description('native');
    check($description !== 'native' && str_contains($description, 'Harmat Lakópark'), $page . ' copy');
    check(harmat_asm_image_url('native-cover') === 'native-cover', $page . ' missing asset / no cover replacement');
}

$post = new WP_Post(11, 'property', 'a1-2-l5');
$unit = array('title' => 'A1-2-L5', 'rooms' => 4.0, 'ground' => false, 'floor' => '2. emelet', 'area' => 93.00,
    'sales_area' => 106.48, 'price' => 127426950, 'hide_price' => false, 'status' => 'current');
$current = 'A1-2-L5: 4 szobás, 2. emeleti lakás a Harmat Lakóparkban. Alapterület: 93,00 m²; értékesítési terület: 106,48 m². Alaprajz és aktuális ár az adatlapon.';
check(harmat_asm_description('native') === $current, 'approved available copy');
$unit['hide_price'] = true;
check(!str_contains(harmat_asm_description('native'), 'aktuális ár'), 'hidden price does not promise listing shows it');
$unit['hide_price'] = false;
foreach (array('reserved' => 'Foglalva.', 'sold' => 'Eladva.') as $status => $expected) {
    $unit['status'] = $status;
    $text = harmat_asm_description('native');
    check(str_contains($text, $expected) && !str_contains($text, 'aktuális ár'), $status . ' follows existing status');
}
$unit['status'] = 'current';

foreach (array('admin', 'ajax', 'json', 'feed', 'search', '404', 'preview', 'archive') as $flag) {
    $flags = array($flag => true);
    check(harmat_asm_description('native') === 'native', $flag . ' excluded');
}
$flags = array();
$allowed = $post;
foreach (array(new WP_Post(12, 'property', 'a1-2-l6'), new WP_Post(13, 'page', 'a1-2-l5'),
    new WP_Post(14, 'page', 'magunkrol', 'draft'), new WP_Post(15, 'page', 'magunkrol', 'publish', 'protected'),
    new WP_Post(16, 'page', 'finanszirozas'), null) as $post) {
    check(harmat_asm_description('native') === 'native' && harmat_asm_image_url('cover') === 'cover', 'outside scope');
}

$post = $allowed;
$dir = WP_CONTENT_DIR . '/uploads/2026/05';
$file = $dir . '/A1-2-L5-cn-floorplan-display.jpg';
mkdir($dir, 0700, true);
try {
    copy($root . '/assets/property-floorplans/A1-2-L5-cn-floorplan-display.jpg', $file);
    $cover = harmat_asm_cover();
    check($cover['width'] === 863 && $cover['height'] === 731 && $cover['type'] === 'image/jpeg', 'real asset dimensions');
    $presentation = (object) array('open_graph_images' => array(array('url' => 'previous')));
    check(harmat_asm_presentation($presentation, (object) array('open_graph_enabled' => true))->open_graph_images === array($cover), 'single, matching Open Graph asset');
    check(harmat_asm_image_url('previous') === $cover['url'], 'sharing URL');
    ob_start();
    harmat_asm_image_alt();
    $alt_markup = ob_get_clean();
    check(str_contains($alt_markup, 'og:image:alt') && str_contains($alt_markup, 'twitter:image:alt'), 'alt tags');
    $url = get_permalink($post);
    $graph = array(
        array('@type' => 'WebPage', 'url' => $url, 'description' => 'old', 'primaryImageOfPage' => array('@id' => $url . '#primaryimage'), 'thumbnailUrl' => 'prev'),
        array('@type' => 'ImageObject', '@id' => $url . '#primaryimage', 'url' => 'prev', 'caption' => 'old'),
        array('@type' => 'Organization', 'logo' => 'prev'),
        array('@type' => 'VideoObject', 'thumbnailUrl' => 'prev'),
        array('@type' => 'Apartment', 'image' => $cover['url'], 'offers' => array('price' => 127426950)),
    );
    $after = harmat_asm_graph($graph);
    check($after[0]['description'] === $current && $after[0]['thumbnailUrl'] === $cover['url'], 'target WebPage fields');
    check($after[1]['url'] === $cover['url'] && $after[1]['caption'] === $cover['alt'], 'referenced ImageObject fields');
    check(array_slice($after, 2) === array_slice($graph, 2), 'Apartment, Offer, video and brand entities unchanged');
    $post = new WP_Post(36, 'page', 'epitesi-naplo');
    $journal = array(array('@type' => 'WebPage', 'url' => get_permalink($post)), $graph[3]);
    check(harmat_asm_graph($journal) === $journal, 'journal graph and cover are left alone');
} finally {
    unlink($file);
    for ($path = $dir; $path !== dirname(WP_CONTENT_DIR); $path = dirname($path)) {
        rmdir($path);
    }
}

echo 'PASS ' . $tested . " metadata assertions\n";
