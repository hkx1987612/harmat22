<?php
// Isolated tests: no WordPress connection, customer records or tracking writes.
define('ABSPATH', __DIR__);
function add_filter(...$args) {}
function add_action(...$args) {}
function remove_action(...$args) {}
function add_shortcode(...$args) {}
function home_url($path = '/') { return 'https://harmat22.hu' . $path; }
function esc_url_raw($value) { return $value; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_key($value) { return $value; }
function current_time($format) { return date($format); }
function get_option($key, $default = false) { return $default; }
function update_option(...$args) {}
function do_action(...$args) {}
function get_posts($args) { return array_map(fn($id) => (object) array('ID' => $id), array_keys($GLOBALS['source'])); }
function harmat_sai_property_summary_data($id) { return $GLOBALS['source'][$id]; }
function get_permalink($id) { return home_url('/property/' . strtolower($GLOBALS['source'][$id]['title']) . '/'); }
function get_post_meta($id, $key, $single) { return home_url('/wp-content/uploads/' . $id . '.pdf'); }
function apply_filters($name, $value) { return harmat_assistant_public_apartments($value); }

$root = dirname(__DIR__, 2);
require $root . '/wp-mu-plugins/zz-harmat-assistant-live-data.php';
require $root . '/wp-plugins/harmat-local-assistant/harmat-local-assistant.php';
$GLOBALS['source'] = array();
foreach (array(1 => 65000000, 2 => 69000000, 3 => 63000000, 4 => 61000000, 5 => 62000000, 6 => 68000000) as $id => $price) {
    $GLOBALS['source'][$id] = array('title' => 'A1-1-L' . $id, 'ground' => false, 'rooms' => $id === 6 ? 3 : 2,
        'bedrooms' => $id === 6 ? 2 : 1, 'sales_area' => 47.83, 'outdoor' => 8.5,
        'price' => $price, 'hide_price' => $id === 3,
        'status' => $id === 4 ? 'reserved' : ($id === 5 ? 'sold' : 'current'));
}
$method = new ReflectionMethod(Harmat_Local_Assistant::class, 'answer_message');
$assistant = $GLOBALS['harmat_local_assistant'];
$count = 0;
function check($condition, $label) {
    global $count;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $count++;
}
function ask($message, $lang = 'hu', $context = array()) {
    global $method, $assistant;
    return $method->invoke($assistant, $message, $lang, $context);
}
foreach (array('hu' => array('2 szobás 70 millió', 'erkéllyel', 'olcsóbb', 'inkább 3 szoba', 'új keresés'),
    'en' => array('2 rooms 70 million', 'with balcony', 'cheaper', '3 rooms instead', 'new search'),
    'zh' => array('2房 7000万', '带阳台', '便宜的', '改成3房', '重新选房')) as $lang => $messages) {
    $r = ask($messages[0], $lang);
    check(($r['selection']['rooms'] ?? 0) === 2 && ($r['selection']['budget'] ?? 0) === 70000000, $lang . ' initial selection');
    check(count($r['cards']) === 2, $lang . ' available with known price only');
    $r = ask($messages[1], $lang, $r['selection']);
    check(($r['selection']['rooms'] ?? 0) === 2 && !empty($r['selection']['terrace']), $lang . ' balcony continuation');
    $r = ask($messages[2], $lang, $r['selection']);
    check(($r['cards'][0]['title'] ?? '') === 'A1-1-L1', $lang . ' cheaper first');
    $r = ask($messages[3], $lang, $r['selection']);
    check(($r['selection']['rooms'] ?? 0) === 3 && ($r['cards'][0]['title'] ?? '') === 'A1-1-L6', $lang . ' changed rooms');
    $faq = ask($lang === 'hu' ? 'Fizetési ütemezés' : ($lang === 'en' ? 'payment schedule' : '付款节点'), $lang, $r['selection']);
    check(!$faq['cards'] && $faq['selection'] === $r['selection'], $lang . ' FAQ preserves context');
    $reset = ask($messages[4], $lang, $r['selection']);
    check(!$reset['selection'] && !$reset['cards'], $lang . ' reset');
    $reserved = ask('A1-1-L4', $lang);
    check(str_contains($reserved['answer'], array('hu' => 'Foglalva', 'en' => 'Reserved', 'zh' => '已预订')[$lang]), $lang . ' reserved exact lookup');
    $hidden = ask('A1-1-L3', $lang);
    check(!str_contains($hidden['answer'], '63 000 000') && str_contains($hidden['answer'], array('hu' => 'Ár egyeztetés alapján', 'en' => 'Price on request', 'zh' => '价格面议')[$lang]), $lang . ' hidden price');
    check(str_contains($hidden['answer'], '47,83'), $lang . ' exact public area');
    check($hidden['cards'][0]['offer_url'] === 'https://harmat22.hu/property/a1-1-l3/#opal-contactform-popup', $lang . ' unified quote URL');
}
$context = array('rooms' => 2, 'budget' => 70000000, 'terrace' => true, 'floor' => '2');
$r = ask('no balcony', 'en', $context);
check(empty($r['selection']['terrace']) && $r['selection']['rooms'] === 2, 'remove balcony');
$r = ask('any floor', 'en', $context);
check(empty($r['selection']['floor']), 'remove floor');
$r = ask('no budget limit', 'en', $context);
check(empty($r['selection']['budget']), 'remove budget');
check(harmat_assistant_clean_context(array('rooms' => array(2), 'budget' => INF, 'building' => 'A9', 'floor' => '999', 'name' => 'Private', 'garden' => 'true')) === array(), 'reject untrusted context');
$public = harmat_assistant_public_apartments(null);
check(count($public) === 6 && $public[2]['price_huf'] === 0 && $public[2]['sqm_price_huf'] === 0, 'live adapter hides price');
check(!isset($public[0]['name']) && $public[0]['sales_area_m2'] === 47.83, 'allowlisted public fields');
echo 'PASS ' . $count . " assertions\n";
