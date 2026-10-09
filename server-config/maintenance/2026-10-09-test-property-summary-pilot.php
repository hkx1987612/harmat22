<?php
// Local fixtures only. Optional: --snapshot [baseline-plugin.php] for output parity.
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) {
    exit(1);
}
define('ABSPATH', __DIR__);
$actions = $filters = $context_flags = array();
$current_id = 4349;
$singular_type = 'property';
$assertions = 0;
$old_ids = array(4292, 4311, 4317, 4327, 4365, 4385, 4386, 4419, 4263, 5148, 5141, 5312);
$new_ids = array(4349, 4388, 4418, 5146);

function add_action($name, $callback, $priority = 10, $accepted = 1) {
    $GLOBALS['actions'][$name][$priority][] = array($callback, $accepted);
}
function add_filter($name, $callback, $priority = 10, $accepted = 1) {
    $GLOBALS['filters'][$name][$priority][] = array($callback, $accepted);
}
function home_url($path = '/') { return 'https://harmat22.hu' . $path; }
function get_queried_object_id() { return $GLOBALS['current_id']; }
function is_singular($type) { return $type === $GLOBALS['singular_type']; }
function is_admin() { return !empty($GLOBALS['context_flags']['admin']); }
function wp_doing_ajax() { return !empty($GLOBALS['context_flags']['ajax']); }
function wp_is_json_request() { return !empty($GLOBALS['context_flags']['json']); }
function is_feed() { return !empty($GLOBALS['context_flags']['feed']); }
function is_search() { return !empty($GLOBALS['context_flags']['search']); }
function is_404() { return !empty($GLOBALS['context_flags']['404']); }
function get_the_title($id) { return $GLOBALS['fixtures'][$id]['title']; }
function get_post_meta($id, $key, $single) { return $GLOBALS['fixtures'][$id]['meta'][$key] ?? ''; }
function get_permalink($id) { return home_url('/property/' . strtolower(get_the_title($id)) . '/'); }
function get_the_post_thumbnail_url(...$args) { return ''; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return esc_html($value); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function hm_migrated_property_floorplan_image_from_uploads($title) {
    return home_url('/wp-content/uploads/2026/05/' . $title . '-cn-floorplan-display.jpg');
}
function update_option(...$args) { throw new RuntimeException('Unexpected option write'); }
function update_post_meta(...$args) { throw new RuntimeException('Unexpected metadata write'); }
function wp_update_post(...$args) { throw new RuntimeException('Unexpected post write'); }
function wp_remote_post(...$args) { throw new RuntimeException('Unexpected outbound POST'); }
function wp_schedule_single_event(...$args) { throw new RuntimeException('Unexpected scheduling'); }
function wp_mail(...$args) { throw new RuntimeException('Unexpected email'); }
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $GLOBALS['assertions']++;
}
function output_of($callback) {
    ob_start();
    $callback();
    return ob_get_clean();
}

$base_meta = array('property_address_street' => 'A1', 'property_address_street_number' => '2',
    'property_rooms' => '2', 'property_bedrooms' => '1', 'property_building_area' => '52.93',
    'property_land_area' => '5.77', '_harmat_sales_area' => '55.82', 'property_price' => '72327150',
    'property_status' => 'current', 'property_under_offer' => '', 'property_price_display' => 'yes',
    '_harmat_hide_front_price' => '');
$fixtures = array();
// Public source values captured read-only on 2026-10-09; production still reads live metadata.
foreach (array(
    4349 => array('A1-2-L4', 'A1', '2', '2', '1', '52.93', '5.77', '55.82', '72327150'),
    4388 => array('A1-4-L1', 'A1', '4', '3', '2', '61.6', '10.9', '67.04', '83379450'),
    4418 => array('A1-4-L4', 'A1', '4', '2', '1', '53.65', '11.68', '59.49', '73810800'),
    5146 => array('A2-1-L5', 'A2', '1', '4', '3', '93.01', '26.95', '106.48', '127426950'),
) as $id => $row) {
    $title = array_shift($row);
    $keys = array('property_address_street', 'property_address_street_number', 'property_rooms',
        'property_bedrooms', 'property_building_area', 'property_land_area', '_harmat_sales_area', 'property_price');
    $fixtures[$id] = array('title' => $title, 'meta' => array_replace($base_meta, array_combine($keys, $row)));
}
// Synthetic old-pilot/non-pilot fixtures, not claims about their live property data.
foreach (array_merge($old_ids, array(9999, 4286, 4383, 4384, 4387, 4416, 4417, 5140, 5307)) as $id) {
    $fixtures[$id] = array('title' => 'FIXTURE-' . $id, 'meta' => $base_meta);
}
$source = ($argv[1] ?? '') === '--snapshot' && isset($argv[2])
    ? $argv[2] : dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-search-ai-discovery.php';
require $source;

if (($argv[1] ?? '') === '--snapshot') {
    $snapshot = array('old_html' => array(), 'schema_and_default_text' => array(), 'hooks' => array());
    foreach ($old_ids as $id) { $snapshot['old_html'][$id] = harmat_sai_property_summary_html($id); }
    foreach (array_merge($old_ids, $new_ids, array(9999)) as $id) {
        $current_id = $id;
        foreach (array('current', 'reserved', 'sold') as $status) {
            $fixtures[$id]['meta']['property_status'] = $status === 'sold' ? 'sold' : 'current';
            $fixtures[$id]['meta']['property_under_offer'] = $status === 'reserved' ? 'yes' : '';
            foreach (array('', 'yes') as $hidden) {
                $fixtures[$id]['meta']['_harmat_hide_front_price'] = $hidden;
                $snapshot['schema_and_default_text'][$id][$status][$hidden] = array(
                    harmat_sai_entity_graph(), harmat_sai_property_summary_text($id),
                    output_of($actions['wp_head'][38][0][0]),
                );
            }
        }
    }
    foreach (array('actions' => $actions, 'filters' => $filters) as $type => $registry) {
        foreach ($registry as $hook => $priorities) {
            foreach ($priorities as $priority => $callbacks) {
                foreach ($callbacks as [$callback, $accepted]) {
                    $snapshot['hooks'][] = array($type, $hook, $priority, is_string($callback) ? $callback : 'closure', $accepted);
                }
            }
        }
    }
    echo json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

check(harmat_sai_extended_pilot_property_ids() === $new_ids, 'exact four additional pilots');
check(harmat_sai_pilot_property_ids() === array_merge($old_ids, $new_ids), 'original twelve in order plus four');
check(count(array_unique(harmat_sai_pilot_property_ids())) === 16, 'no duplicate IDs');
check(array_keys($filters) === array('wpseo_schema_organization'), 'no new metadata filters');
check(array_keys($actions) === array('plugins_loaded', 'wp_head', 'template_redirect', 'save_post',
    'added_post_meta', 'updated_post_meta', 'deleted_post_meta', 'harmat_sai_send_indexnow_queue'), 'no new action hooks');

$before = '<html><head><title>Unchanged</title><link rel="canonical" href="/original/">'
    . '<meta name="robots" content="index,follow"><script type="application/ld+json">{"unchanged":true}</script>'
    . '</head><body><section class="harmat-property-hero"><h1>Original hero</h1></section>'
    . '<section id="original-plan"><img src="/original.jpg"></section></body></html>';
foreach ($new_ids as $id) {
    $current_id = $id;
    $data = harmat_sai_property_summary_data($id);
    $summary = harmat_sai_property_summary_html($id);
    check(str_contains($summary, 'A belső alapterület ' . harmat_sai_format_area($data['area'])), $id . ' indoor area');
    check(!str_contains($summary, 'értékesítési terület'), $id . ' sales area remains in existing facts table');
    check(str_contains($summary, harmat_sai_format_area($data['outdoor']) . '-es terasz / erkély'), $id . ' outdoor fact');
    check(str_contains($summary, $data['floor'] . 'én') && str_contains($summary, $data['rooms'] . ' szobás'), $id . ' floor and room facts');
    check(str_contains($summary, number_format($data['price'], 0, ',', ' ') . ' Ft.'), $id . ' current public price');
    check(substr_count($summary, '<a href=') === 3, $id . ' exactly three ordinary links');
    foreach (array('/virtualis-lakasvalaszto-' . strtolower($data['building']) . '-epulet/', '/lakaskereso/', '/epitesi-naplo/') as $path) {
        check(substr_count($summary, 'href="' . home_url($path) . '"') === 1, $id . ' exact unique destination ' . $path);
    }
    check(!str_contains($summary, 'nofollow') && !str_contains($summary, 'onclick') && !str_contains($summary, 'dateModified'), $id . ' no link script/indexing/date side effects');
    $after = harmat_sai_insert_property_summary($before);
    check(substr_count($after, 'data-harmat-property-search-summary=') === 1, $id . ' summary once');
    check(str_replace($summary, '', $after) === $before, $id . ' all original HTML including head unchanged');
    check(harmat_sai_insert_property_summary($after) === $after, $id . ' duplicate guard');
    check(strpos($after, $summary) === strpos($after, '</section>') + 10, $id . ' immediately after hero');
    foreach (array('current' => 'elérhető', 'reserved' => 'foglalva', 'sold' => 'eladva') as $status => $label) {
        $fixtures[$id]['meta']['property_status'] = $status === 'sold' ? 'sold' : 'current';
        $fixtures[$id]['meta']['property_under_offer'] = $status === 'current' ? '' : 'yes';
        check(str_contains(harmat_sai_property_summary_html($id), 'jelenlegi státusza: ' . $label . '.'), $id . ' dynamic ' . $status);
        $availability = array('current' => 'InStock', 'reserved' => 'LimitedAvailability', 'sold' => 'SoldOut');
        check(harmat_sai_entity_graph()[1]['offers']['availability'] === 'https://schema.org/' . $availability[$status], $id . ' unchanged offer ' . $status);
    }
    $fixtures[$id]['meta']['property_status'] = 'current';
    $fixtures[$id]['meta']['property_under_offer'] = '';
    check(str_contains(harmat_sai_property_summary_html($id), 'jelenlegi státusza: elérhető.'), $id . ' return to available');
    foreach (array('_harmat_hide_front_price' => 'yes', 'property_price_display' => 'no', 'property_price' => '0') as $key => $value) {
        $original = $fixtures[$id]['meta'][$key];
        $fixtures[$id]['meta'][$key] = $value;
        $hidden = harmat_sai_property_summary_html($id);
        check(!str_contains($hidden, ' Ft.') && str_contains($hidden, 'értékesítési csapat ad tájékoztatást'), $id . ' hidden/absent price ' . $key);
        check(!isset(harmat_sai_entity_graph()[1]['offers']['price']), $id . ' schema hidden/absent price ' . $key);
        $fixtures[$id]['meta'][$key] = $original;
    }
}

foreach ($old_ids as $id) {
    $current_id = $id;
    $text = 'Az FIXTURE-' . $id . ' a Harmat Lakópark A1 épületének 2. emeletén található, 2 szobás lakás.'
        . ' Az alapterület 52,93 m². A lakáshoz 5,77 m²-es terasz / erkély kapcsolódik.'
        . ' Az értékesítési terület 55,82 m². Az ingatlan jelenlegi státusza: elérhető. Az aktuális vételár 72 327 150 Ft.';
    $expected = '<section class="harmat-search-summary" data-harmat-property-search-summary="1" aria-labelledby="harmat-search-summary-title">'
        . '<h2 id="harmat-search-summary-title">FIXTURE-' . $id . ' lakás röviden</h2><p>' . $text . '</p></section>';
    check(harmat_sai_property_summary_html($id) === $expected, $id . ' old HTML unchanged');
    check(str_contains(harmat_sai_insert_property_summary($before), $expected), $id . ' old insertion retained');
}
foreach (array(9999, 4286, 4383, 4384, 4387, 4416, 4417, 5140, 5307) as $id) {
    $current_id = $id;
    check(harmat_sai_insert_property_summary($before) === $before, $id . ' non-pilot untouched');
    check(output_of($actions['wp_head'][80][0][0]) === '', $id . ' no summary CSS');
    $level = ob_get_level();
    $actions['template_redirect'][0][0][0]();
    check(ob_get_level() === $level, $id . ' no output buffer');
}
$current_id = 4349;
foreach (array('page', 'post', '') as $type) {
    $singular_type = $type;
    check(harmat_sai_insert_property_summary($before) === $before, 'non-property ' . $type . ' untouched');
}
$singular_type = 'property';
foreach (array('admin', 'ajax', 'json', 'feed', 'search', '404') as $flag) {
    $context_flags = array($flag => true);
    $level = ob_get_level();
    $actions['template_redirect'][0][0][0]();
    check(ob_get_level() === $level, $flag . ' no summary buffer');
    check(output_of($actions['wp_head'][38][0][0]) === '', $flag . ' schema request guard retained');
}
$context_flags = array();
foreach (array('<p>No hero</p>', '<section class="harmat-property-hero">Unclosed',
    '<p data-harmat-property-search-summary="1">Existing</p>' . $before) as $html) {
    check(harmat_sai_insert_property_summary($html) === $html, 'missing/malformed hero or existing summary untouched');
}
$level = ob_get_level();
$actions['template_redirect'][0][0][0]();
check(ob_get_level() === $level + 1, 'pilot opens existing summary buffer');
ob_end_clean();

$fixtures[4349]['title'] = '<script>alert("x")</script> & unit';
$fixtures[4349]['meta']['property_address_street'] = 'A1"><img src=x onerror=alert(1)>';
$fixtures[4349]['meta']['property_address_street_number'] = '<b>4</b>';
$escaped = harmat_sai_property_summary_html(4349);
check(!str_contains($escaped, '<script') && !str_contains($escaped, '<img') && !str_contains($escaped, '<b>'), 'metadata/title escaped');
check(str_contains($escaped, '&lt;script&gt;') && str_contains($escaped, '&amp; unit'), 'escaped text retained');
check(substr_count($escaped, '<a href=') === 2 && !str_contains($escaped, 'virtualis-lakasvalaszto-'), 'unknown building cannot create a URL');

$fixtures[4349] = array('title' => 'A1-F-TEST', 'meta' => array_replace($base_meta, array(
    'property_address_street_number' => 'F', '_harmat_sales_area' => '', 'property_land_area' => '40',
)));
$ground = harmat_sai_property_summary_html(4349);
check(str_contains($ground, 'földszintjén') && str_contains($ground, '40,00 m²-es kert / terasz'), 'ground-floor helper facts');
check(str_contains(harmat_sai_property_summary_text(4349), 'értékesítési terület 52,93 m²'), 'default ground-floor sales-area calculation retained');
$fixtures[4349]['meta']['_harmat_sales_area'] = '60.12';
check(str_contains(harmat_sai_property_summary_text(4349), 'értékesítési terület 60,12 m²'), 'default explicit sales override retained');
$fixtures[4349] = array('title' => 'EMPTY', 'meta' => array());
$empty = harmat_sai_property_summary_html(4349);
check(!str_contains($empty, '0,00 m²') && !str_contains($empty, '0 szobás'), 'missing facts not invented');
check(!str_contains($empty, 'virtualis-lakasvalaszto-'), 'missing building has no guessed selector');

echo 'PASS ' . $assertions . " isolated assertions\n";
