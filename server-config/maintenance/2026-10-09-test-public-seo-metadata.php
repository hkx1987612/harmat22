<?php
// Isolated fixtures only: no WordPress boot, database, email or indexing requests.
define('ABSPATH', __DIR__);
define('OBJECT', 'OBJECT');
$filters = array();
$context_flags = array();
$current_page = 'harmat-lakopark';
$page = (object) array('post_status' => 'publish', 'post_password' => '',
    'post_modified_gmt' => '2026-06-08 11:30:31', 'post_date_gmt' => '2026-06-08 11:30:31');
$property_meta = array();
$floorplan_available = true;
$assertions = 0;

function add_filter($name, $callback, $priority = 10, $accepted = 1) {
    $GLOBALS['filters'][$name][] = array($callback, $priority, $accepted);
}
function add_action(...$args) {}
function home_url($path = '/') { return 'https://harmat22.hu' . $path; }
function get_page_by_path(...$args) { return $GLOBALS['page']; }
function is_admin() { return !empty($GLOBALS['context_flags']['admin']); }
function wp_doing_ajax() { return !empty($GLOBALS['context_flags']['ajax']); }
function wp_is_json_request() { return !empty($GLOBALS['context_flags']['json']); }
function is_feed() { return !empty($GLOBALS['context_flags']['feed']); }
function is_search() { return !empty($GLOBALS['context_flags']['search']); }
function is_404() { return !empty($GLOBALS['context_flags']['404']); }
function is_preview() { return !empty($GLOBALS['context_flags']['preview']); }
function post_password_required() { return !empty($GLOBALS['context_flags']['password']); }
function is_page($slugs) { return in_array($GLOBALS['current_page'], (array) $slugs, true); }
function get_queried_object_id() { return 4292; }
function get_post_status($id) { return !empty($GLOBALS['context_flags']['draft']) ? 'draft' : 'publish'; }
function is_singular($type) { return $type === 'property'; }
function get_the_title($id) { return 'A1-1-L2'; }
function get_permalink($id) { return home_url('/property/a1-1-l2/'); }
function get_the_post_thumbnail_url(...$args) { return ''; }
function get_post_meta($id, $key, $single) { return $GLOBALS['property_meta'][$key] ?? ''; }
function untrailingslashit($value) { return rtrim($value, '/'); }

if (!in_array('--without-floorplan-helper', $argv, true)) {
    function hm_migrated_property_floorplan_image_from_uploads($title) {
        return $GLOBALS['floorplan_available']
            ? home_url('/wp-content/uploads/2026/05/' . strtoupper($title) . '-cn-floorplan-display.jpg') : '';
    }
}

function check($condition, $label) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $GLOBALS['assertions']++;
}

// Model Yoast's lazy presentation contract, including its magic isset/getters.
#[AllowDynamicProperties]
class Metadata_Presentation_Fixture {
    public function __construct(private array $generated) {}
    public function __isset($name) { return array_key_exists($name, $this->generated); }
    public function __get($name) { return $this->{$name} = $this->generated[$name]; }
}
function presentation($images = array(), $twitter = '') {
    return new Metadata_Presentation_Fixture(array('open_graph_images' => $images, 'twitter_image' => $twitter));
}

$root = dirname(__DIR__, 2);
require $root . '/wp-mu-plugins/zz-harmat-public-seo-metadata.php';
$metadata_filters = $filters;
require $root . '/wp-mu-plugins/zz-harmat-search-ai-discovery.php';

check(array_keys($metadata_filters) === array('wpseo_sitemap_entry', 'wpseo_sitemap_index_links', 'wpseo_frontend_presentation'), 'only three metadata hooks');
foreach (array('wpseo_sitemap_entry' => 3, 'wpseo_sitemap_index_links' => 1, 'wpseo_frontend_presentation' => 2) as $name => $args) {
    check($metadata_filters[$name][0][1] === 100 && $metadata_filters[$name][0][2] === $args, $name . ' contract');
}

$release = HARMAT_SEO_CONSTRUCTION_MODIFIED_GMT;
$construction = clone $page;
$construction->post_type = 'page';
$construction->post_name = 'epitesi-naplo';
$entry = array('loc' => home_url('/epitesi-naplo/'), 'mod' => '2026-06-08 11:30:31',
    'images' => array(array('src' => home_url('/construction.jpg'))), 'pri' => 1);
$updated = harmat_seo_metadata_sitemap_entry($entry, 'post', $construction);
$expected = $entry; $expected['mod'] = $release;
check($updated === $expected, 'construction date only');
check(harmat_seo_metadata_sitemap_entry($updated, 'post', $construction) === $updated, 'stable repeated construction request');
foreach (array('', 'not-a-date', '0000-00-00 00:00:00', null) as $date) {
    check(harmat_seo_metadata_latest_modified($date) === $release, 'invalid/empty legacy date uses release');
}
foreach (array('2026-10-10 09:00:00', '2026-10-10T11:00:00+02:00') as $date) {
    check(harmat_seo_metadata_latest_modified($date) === $date, 'later modification retained');
}
$construction->post_modified_gmt = '2026-11-01 12:00:00';
check(harmat_seo_metadata_sitemap_entry($entry, 'post', $construction)['mod'] === $construction->post_modified_gmt, 'later WP modification retained');
$construction->post_date_gmt = '2026-11-02 12:00:00';
check(harmat_seo_metadata_sitemap_entry($entry, 'post', $construction)['mod'] === $construction->post_date_gmt, 'later WP publication retained');
$construction = clone $page; $construction->post_type = 'page'; $construction->post_name = 'epitesi-naplo';

foreach (array(false, null, '', array()) as $excluded) {
    check(harmat_seo_metadata_sitemap_entry($excluded, 'post', $construction) === $excluded, 'excluded entries remain excluded');
}
check(harmat_seo_metadata_sitemap_entry($entry, 'term', $construction) === $entry, 'terms unchanged');
check(harmat_seo_metadata_sitemap_entry($entry, 'post', null) === $entry, 'missing post unchanged');
$other = (object) array('post_type' => 'page', 'post_name' => 'finanszirozas');
check(harmat_seo_metadata_sitemap_entry($entry, 'post', $other) === $entry, 'other page unchanged');

$page_entries = array($entry);
for ($i = 1; $i < 21; $i++) { $page_entries[] = array('loc' => home_url('/page-' . $i . '/'), 'mod' => '2026-07-29 12:00:00'); }
$mapped = array_map(fn($item) => harmat_seo_metadata_sitemap_entry($item, 'post', $item['loc'] === $entry['loc'] ? $construction : $other), $page_entries);
check(count($mapped) === 21 && array_column($mapped, 'loc') === array_column($page_entries, 'loc'), '21-page URL set retained');
check(array_slice($mapped, 1) === array_slice($page_entries, 1), '20 other page entries unchanged');

$links = array(array('loc' => home_url('/page-sitemap.xml'), 'lastmod' => '2026-07-29 12:00:00'),
    array('loc' => home_url('/property-sitemap.xml'), 'lastmod' => '2026-06-08 11:30:31'),
    array('loc' => home_url('/harmat-video-sitemap.xml'), 'lastmod' => '2026-09-10 12:00:00'));
$result = harmat_seo_metadata_sitemap_index_links($links);
$expected = $links; $expected[0]['lastmod'] = $release;
check($result === $expected, 'only page sitemap index date updated');
check(harmat_seo_metadata_sitemap_index_links($result) === $result, 'index idempotence');
$page->post_modified_gmt = '2026-11-01 12:00:00';
check(harmat_seo_metadata_sitemap_index_links($links)[0]['lastmod'] === $page->post_modified_gmt, 'index follows later construction WP edit');
$links[0]['lastmod'] = '2026-12-01 12:00:00';
check(harmat_seo_metadata_sitemap_index_links($links) === $links, 'later other page maximum retained');
$page->post_status = 'draft';
check(harmat_seo_metadata_sitemap_index_links($links) === $links, 'unpublished journal does not alter index');
$page->post_status = 'publish'; $page->post_password = 'fixture';
check(harmat_seo_metadata_sitemap_index_links($links) === $links, 'protected journal does not alter index');
$page = null;
check(harmat_seo_metadata_sitemap_index_links($links) === $links, 'missing journal does not alter index');
check(harmat_seo_metadata_sitemap_index_links(false) === false, 'non-array index unchanged');

$original_properties = array(); $mapped_properties = array();
foreach (array('A1', 'A2', 'A3', 'A4') as $building) {
    foreach (array('F' => 7, 1 => 8, 2 => 8, 3 => 3, 4 => 5) as $floor => $units) {
        for ($unit = 1; $unit <= $units; $unit++) {
            $title = $building . '-' . $floor . '-L' . $unit;
            $post = (object) array('post_type' => 'property', 'post_title' => $title);
            $item = array('loc' => home_url('/property/' . strtolower($title) . '/'), 'mod' => '2026-05-01 12:00:00',
                'images' => array(array('src' => home_url('/legacy-display-400x400.jpg')),
                    array('src' => home_url('/eladolakas_3d8.jpg')), array('src' => home_url('/old-szintrajz.jpg'))));
            $mapped_item = harmat_seo_metadata_sitemap_entry($item, 'post', $post);
            if (function_exists('hm_migrated_property_floorplan_image_from_uploads')) {
                $expected = $item; $expected['images'] = array(array('src' => hm_migrated_property_floorplan_image_from_uploads($title)));
                check($mapped_item === $expected, $title . ' current floorplan only, other fields retained');
            } else {
                check($mapped_item === $item, $title . ' absent helper fails open');
            }
            $original_properties[] = $item; $mapped_properties[] = $mapped_item;
        }
    }
}
check(count($mapped_properties) === 124 && array_column($mapped_properties, 'loc') === array_column($original_properties, 'loc'), '124-property URL set retained');
$floorplan_available = false;
check(harmat_seo_metadata_sitemap_entry($item, 'post', $post) === $item, 'missing floorplan preserves all original images');
$floorplan_available = true;
$post->post_type = 'attachment';
check(harmat_seo_metadata_sitemap_entry($item, 'post', $post) === $item, 'attachment images unchanged');

$ctx = (object) array('open_graph_enabled' => true);
$fallback = home_url('/wp-content/uploads/2026/02/Harmat22_latvany-3.jpg');
foreach (array('harmat-lakopark', 'harmat-lakopark-kornyeke', 'elerhetosegeink', 'virtualis-lakasvalaszto') as $route) {
    $current_page = $route;
    $p = harmat_seo_metadata_share_presentation(presentation(), $ctx);
    check($p->open_graph_images === array(array('url' => $fallback)) && $p->twitter_image === $fallback, $route . ' two-channel fallback');
    check(harmat_seo_metadata_share_presentation($p, $ctx)->open_graph_images === array(array('url' => $fallback)), $route . ' no duplicate OG on repeated call');
}
foreach (array('', 'lakaskereso', 'epitesi-naplo', 'property/a1-1-l2', 'adatvedelmi-tajekoztato', 'cookie-tajekoztato', 'felhasznalasi-feltetelek', 'impresszum', 'virtualis-lakasvalaszto-elso-utem') as $route) {
    $current_page = $route; $p = presentation();
    harmat_seo_metadata_share_presentation($p, $ctx);
    check($p->open_graph_images === array() && $p->twitter_image === '', $route . ' out of scope');
}
$current_page = 'harmat-lakopark';
foreach (array('admin', 'ajax', 'json', 'feed', 'search', '404', 'preview', 'draft', 'password') as $flag) {
    $context_flags = array($flag => true); $p = presentation();
    harmat_seo_metadata_share_presentation($p, $ctx);
    check($p->open_graph_images === array() && $p->twitter_image === '', $flag . ' share fallback excluded');
}
$context_flags = array();
$curated = array(array('url' => home_url('/curated.jpg'), 'width' => 1200, 'height' => 630, 'type' => 'image/jpeg'));
$p = harmat_seo_metadata_share_presentation(presentation($curated, home_url('/twitter-curated.jpg')), $ctx);
check($p->open_graph_images === $curated && $p->twitter_image === home_url('/twitter-curated.jpg'), 'both curated images and dimensions preserved');
$p = harmat_seo_metadata_share_presentation(presentation($curated), $ctx);
check($p->open_graph_images === $curated && $p->twitter_image === $curated[0]['url'], 'empty Twitter reuses curated OG rather than project fallback');
$p = harmat_seo_metadata_share_presentation(presentation(array(), home_url('/twitter-curated.jpg')), $ctx);
check($p->open_graph_images[0]['url'] === $p->twitter_image, 'empty OG reuses curated Twitter rather than project fallback');
$p = harmat_seo_metadata_share_presentation(presentation(), (object) array('open_graph_enabled' => false));
check($p->open_graph_images === array() && $p->twitter_image === $fallback, 'disabled OG remains disabled');
check(harmat_seo_metadata_share_presentation(false, $ctx) === false, 'invalid presentation unchanged');

$property_meta = array('property_price' => '65000000', 'property_building_area' => '50', 'property_land_area' => '14.72',
    'property_rooms' => '2', 'property_bedrooms' => '1', 'property_address_street' => 'A1', 'property_address_street_number' => '1');
$apartment = harmat_sai_entity_graph()[1];
check($apartment['mainEntityOfPage'] === get_permalink(4292), 'Apartment references exact Yoast WebPage ID');
check($apartment['offers']['price'] === 65000000 && $apartment['offers']['priceCurrency'] === 'HUF', 'public price unchanged');
check($apartment['floorSize']['value'] === 57.36 && $apartment['numberOfRooms'] === 2.0, 'area/rooms unchanged');
foreach (array('current' => 'InStock', 'reserved' => 'LimitedAvailability', 'sold' => 'SoldOut') as $status => $availability) {
    $property_meta['property_status'] = $status === 'sold' ? 'sold' : '';
    $property_meta['property_under_offer'] = $status === 'reserved' ? 'yes' : '';
    $apartment = harmat_sai_entity_graph()[1];
    check($apartment['offers']['availability'] === 'https://schema.org/' . $availability, $status . ' availability unchanged');
}
$property_meta['_harmat_hide_front_price'] = 'yes';
$apartment = harmat_sai_entity_graph()[1];
check(!isset($apartment['offers']['price']) && !isset($apartment['offers']['priceCurrency']), 'hidden price remains hidden');

echo 'PASS ' . $assertions . " isolated assertions\n";
