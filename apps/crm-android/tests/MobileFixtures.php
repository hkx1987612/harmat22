<?php
// Current production renderer, in the existing offline WP harness. No network/database.
if (PHP_SAPI !== 'cli') { exit(1); }
ob_start();
require dirname(__DIR__, 3) . '/server-config/maintenance/2026-11-04-test-crm-simplification.php';
ob_end_clean();
$out = $root . '/outputs/2026-11-09-crm-android/fixtures';
$assets = dirname(__DIR__) . '/mobile/src/debug/assets/crm-fixtures';
foreach (array($out, $assets) as $directory) if (!is_dir($directory)) mkdir($directory, 0700, true);
$posts = array(); $properties = array(); $post_meta = array();
$options['harmat_broker_leads_v1'] = array();
$options['harmat_sales_deals_v1'] = array();
foreach (array(101 => 11, 102 => 22) as $id => $owner) {
    $posts[] = (object) array('ID' => $id, 'post_date' => '2026-11-04 14:' . ($id === 101 ? '30' : '40') . ':00', 'post_content' => 'DEMO', 'post_title' => 'DEMO Inquiry ' . $id);
    $post_meta[$id] = array('_harmat_assigned_sales_id' => $owner, '_harmat_offer_phone' => '+36000000000',
        '_harmat_offer_email' => 'demo-' . $id . '@example.invalid', '_harmat_offer_posted' => array('your-name' => 'DEMO Inquiry ' . $id));
}
foreach (array(201 => 11, 202 => 22) as $id => $owner) {
    $options['harmat_sales_deals_v1'][$id] = array('id' => $id, 'client_name' => 'DEMO Deal ' . $id,
        'assigned_sales_id' => $owner, 'crm_code' => 'DEMO-' . $id, 'property_id' => 501,
        'phone' => '+36000000000', 'email' => 'demo@example.invalid', 'source_type' => 'walkin',
        'updated_at' => $id === 201 ? '2026-11-04 14:00:00' : '2026-11-04 15:00:00', 'stage' => 'contacted');
}
foreach (array(401 => 11, 402 => 22) as $id => $owner) {
    $options['harmat_broker_leads_v1'][$id] = array('id' => $id, 'client_name' => 'DEMO Client ' . $id,
        'source' => 'walkin', 'created_at' => '2026-11-04 10:00:00', 'assigned_sales_id' => $owner,
        'phone' => '+36000000000', 'email' => 'demo@example.invalid', 'note' => 'Synthetic viewing enquiry; no actual client.', 'property_id' => 501);
}
$properties[] = (object) array('ID' => 501, 'post_title' => 'DEMO-A1-01', 'post_date' => '2026-11-04 10:00:00');
$post_meta[501] = array('property_status' => 'current', 'property_address_street' => 'DEMO A1',
    'property_address_street_number' => '1', 'property_rooms' => '2', 'property_building_area' => '58.5',
    'property_land_area' => '12', '_harmat_hide_front_price' => 'yes');
foreach (array('manager', 'staff') as $mode) foreach (array('zh', 'hu') as $lang) {
    foreach (array('dashboard', 'inquiries', 'clients', 'deals', 'properties') as $view) {
        $html = rendered($view, $lang);
        file_put_contents("$out/$mode-$lang-$view.html", $html);
        if ($mode === 'manager') file_put_contents("$assets/$lang-$view.html", $html);
    }
}
echo "PASS: 20 current-renderer role/language fixtures, synthetic only.\n";
