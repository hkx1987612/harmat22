<?php
// Synthetic reconciliation; the shared harness rejects writes and mail.
require __DIR__ . '/2026-11-04-test-crm-simplification.php';
$start_checks = $checks;
$mode = 'manager';
$properties = array();
$posts = array();
$options['harmat_broker_leads_v1'] = array();
$options['harmat_sales_deals_v1'] = array();
$post_meta = array();
for ($i = 1; $i <= 124; $i++) {
    $id = 500 + $i;
    $properties[] = (object) array('ID' => $id, 'post_title' => sprintf('DEMO-A1-%03d', $i));
    $post_meta[$id] = array('property_status' => $i <= 38 ? 'reserved' : 'current', 'property_under_offer' => $i <= 38 ? 'yes' : '');
}
// 29 matching apartments, two available apartments, and one duplicate = 32 deals.
foreach (array_merge(range(501, 529), array(539, 540, 501)) as $offset => $property_id) {
    $id = $offset + 1;
    $options['harmat_sales_deals_v1'][$id] = array('id' => $id, 'property_id' => $property_id,
        'stage' => 'reserved', 'client_name' => 'DEMO client ' . $id, 'assigned_sales_id' => 11,
        'crm_code' => 'DEMO-' . $id, 'updated_at' => '2026-11-05 10:00:00');
}
foreach (array(502, 530) as $offset => $property_id) {
    $id = 90 + $offset;
    $options['harmat_sales_deals_v1'][$id] = array('id' => $id, 'property_id' => $property_id,
        'stage' => 'closed', 'client_name' => 'DEMO closed <script>alert(1)</script>', 'assigned_sales_id' => 22);
}
$before = serialize(array($options, $post_meta));
$snapshot = call_crm('sales_reservation_inventory', $properties, call_crm('get_deals'));
check(count($snapshot['reserved']) === 38, 'inventory is authoritative, not 32 deal records');
check(count($snapshot['conflicts']) === 2, 'available inventory with locking deals remains separate');
check(count(array_filter($snapshot['reserved'], fn($r) => $r['review'] === 'missing')) === 8, 'eight missing links');
check(count(array_filter($snapshot['reserved'], fn($r) => $r['review'] === 'conflict')) === 3, 'duplicate and closed-stage conflicts retained');
check(count($snapshot['reserved'][501]['deals']) === 2, 'two records remain attached to one apartment');
check(count(call_crm('sales_reservation_inventory', array_merge($properties, array($properties[0])), call_crm('get_deals'))['reserved']) === 38, 'deduplication uses stable property ID');
foreach (array('zh', 'hu') as $language) {
    $html = rendered('deals', $language); $xp = dom_of($html);
    $badge = $xp->query('//a[@class="harmat-sales-stage-reserved"]')->item(0);
    check(trim($badge->getElementsByTagName('b')->item(0)->textContent) === '38', 'manager badge follows stock');
    check(strpos($badge->getAttribute('href'), 'inventory_status=reserved') !== false, 'badge opens same 38 apartments');
    check($xp->query('//article[@class="harmat-sales-deal-focus-item"]')->length === 34, 'all original deal records retained');
    $view = rendered('deals', $language, array('inventory_status' => 'reserved')); $vx = dom_of($view);
    check($vx->query('//table[@data-inventory-kind="reserved"]/tbody/tr')->length === 38, 'exactly one row per reserved apartment');
    check($vx->query('//table[@data-inventory-kind="conflicts"]/tbody/tr')->length === 2, 'outside-inventory conflicts visible separately');
    check($vx->query('//form[@method="post"]')->length === 0, 'inventory view cannot mutate records');
    check($vx->query('//table//script')->length === 0 && strpos($view, '&lt;script&gt;') !== false, 'client labels escaped');
    check($vx->query('//a[contains(@href,"edit_deal=32")]')->length === 1, 'duplicate record remains individually accessible');
    if (in_array('--preview', $argv, true)) {
        $preview = $root . '/outputs/2026-11-05-inventory-deal-audit/preview';
        if (!is_dir($preview)) mkdir($preview, 0700, true);
        file_put_contents($preview . '/manager-' . $language . '-inventory.html', $view);
        file_put_contents($preview . '/manager-' . $language . '-deals.html', $html);
    }
}
$mode = 'staff';
$staff = dom_of(rendered('deals', 'zh', array('inventory_status' => 'reserved')));
check($staff->query('//table[@data-inventory-kind="reserved"]/tbody/tr')->length === 38, 'staff and manager share existing inventory count');
check(strpos($staff->document->textContent, 'DEMO closed') === false, 'other sales staff customer labels remain hidden');
check(strpos($staff->document->textContent, '无当前账号可见的跟单') !== false, 'invisible deals are not represented as globally missing');
$staff_default = dom_of(rendered('deals', 'zh'));
check($staff_default->query('//a[@class="harmat-sales-stage-reserved"]/b')->item(0)->textContent === '38', 'staff reservation badge also uses inventory grain');
$mode = 'manager';
$post_meta[538]['property_under_offer'] = '';
$post_meta[538]['property_status'] = 'current';
check(count(call_crm('sales_reservation_inventory', $properties, call_crm('get_deals'))['reserved']) === 37, 'count is live, never hardcoded to 38');
$post_meta[538]['property_under_offer'] = 'yes';
$post_meta[538]['property_status'] = 'reserved';
check(call_crm('sales_reservation_inventory', array(), call_crm('get_deals')) === array('reserved' => array(), 'conflicts' => array()), 'empty inventory remains empty');
check($before === serialize(array($options, $post_meta)), 'no business records changed');
echo 'PASS ' . ($checks - $start_checks) . " reservation-inventory checks; synthetic data only.\n";
