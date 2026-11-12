<?php
// Offline fixtures only. The shared harness rejects business writes and mail.
require __DIR__ . '/2026-11-04-test-crm-simplification.php';
$start_checks = $checks;
function inquiry_fixture($website_count, $lead_count) {
    $GLOBALS['mode'] = 'manager';
    $GLOBALS['posts'] = $GLOBALS['post_meta'] = $GLOBALS['properties'] = array();
    $GLOBALS['options']['harmat_broker_leads_v1'] = array();
    $GLOBALS['options']['harmat_sales_deals_v1'] = array();
    foreach (range(1, max(1, $website_count)) as $i) {
        if ($i > $website_count) { break; }
        $id = 1000 + $i;
        $GLOBALS['posts'][] = (object) array('ID' => $id, 'post_date' => date('Y-m-d H:i:s', strtotime('2026-11-12 12:00:00') - $i * 60),
            'post_title' => 'DEMO ' . $id, 'post_content' => '');
        $GLOBALS['post_meta'][$id] = array('_harmat_assigned_sales_id' => $i % 2 ? 11 : 22,
            '_harmat_offer_posted' => array('your-name' => 'DEMO WEBSITE ' . $i),
            '_harmat_offer_email' => 'demo-' . $id . '@example.test');
    }
    for ($i = 1; $i <= $lead_count; $i++) {
        $id = 2000 + $i;
        $GLOBALS['options']['harmat_broker_leads_v1'][$id] = array('id' => $id,
            'client_name' => 'DEMO CLIENT ' . $i, 'source' => $i % 2 ? 'walkin' : 'broker',
            'assigned_sales_id' => $i % 3 ? 11 : 22, 'status' => 'new', 'created_at' => '2026-11-11 09:00:00');
    }
}
function inquiry_kpis($html) {
    $xp = dom_of($html); $result = array();
    foreach ($xp->query('//section[contains(@class,"harmat-sales-kpis-compact")]/article') as $card) {
        $label = trim($card->getElementsByTagName('small')->item(0)->textContent);
        $result[$label] = (int) $card->getElementsByTagName('strong')->item(0)->textContent;
    }
    return $result;
}
foreach (array(0, 1, 120, 121, 160, 200, 201, 400, 401) as $size) {
    inquiry_fixture($size, 106);
    $query_log = array();
    $all = call_crm('sales_inquiry_summary_rows', '', '', 'zh', array(), 0);
    check(count($all) === $size + 106, 'uncapped count at website boundary ' . $size);
    check(count(call_crm('sales_inquiry_summary_rows')) === min(160, $size + 106), 'default rendered limit ' . $size);
    $batch_queries = array_values(array_filter($query_log, fn($q) => ($q['posts_per_page'] ?? 0) === 200));
    check(count($batch_queries) === 2 * (intdiv($size, 200) + 1), 'bounded batches including exact multiples');
    foreach ($batch_queries as $q) {
        check($q['post_status'] === array('private', 'publish', 'draft'), 'original website status scope');
        check($q['orderby'] === array('date' => 'DESC', 'ID' => 'DESC'), 'stable batch order');
    }
}
inquiry_fixture(96, 106);
$before = serialize(array($options, $post_meta));
foreach (array('zh', 'hu') as $language) {
    $labels = $language === 'zh' ? array('网站询价', '自来询价', '经纪人询价', '本次载入', '总计')
        : array('Weboldali érdeklődés', 'Saját érdeklődés', 'Közvetítői érdeklődés', 'Betöltött rekordok', 'Összes rekord');
    $html = rendered('inquiries', $language);
    $kpis = inquiry_kpis($html);
    check(array_keys($kpis) === $labels, 'ordered source and total labels ' . $language);
    check(array_values($kpis) === array(96, 53, 53, 160, 202), 'actual source counts, loaded rows and total ' . $language);
    check(array_sum(array_slice($kpis, 0, 3)) === end($kpis), 'sources reconcile with total');
    $xp = dom_of($html);
    check($xp->query('//table[contains(@class,"harmat-sales-inquiry-table")]/thead/tr/th')->length === 9, 'Android table contract retained');
    check($xp->query('//table[contains(@class,"harmat-sales-inquiry-table")]/tbody/tr')->length === 160, 'HTML still bounded');
    $filtered = rendered('inquiries', $language, array('inquiry_source' => 'walkin'));
    check(array_values(inquiry_kpis($filtered)) === array(96, 53, 53, 53, 202), 'filter does not redefine full totals');
    check(dom_of($filtered)->query('//table/tbody/tr')->length === 53, 'walk-in filter exact rows');
    if (in_array('--preview', $argv, true)) {
        $out = $root . '/outputs/2026-11-12-inquiry-counter-fix/preview';
        if (!is_dir($out)) { mkdir($out, 0700, true); }
        file_put_contents($out . '/manager-' . $language . '.html', $html);
    }
}
check($before === serialize(array($options, $post_meta)), 'render does not change business records');
$options['harmat_broker_leads_v1'][2107] = array('id' => 2107, 'source' => 'walkin', 'created_at' => '2026-11-12 12:30:00', 'assigned_sales_id' => 11);
check(count(call_crm('sales_inquiry_summary_rows', '', '', 'zh', array(), 0)) === 203, 'new record increases actual total beyond 160');
unset($options['harmat_broker_leads_v1'][2107]);
check(count(call_crm('sales_inquiry_summary_rows', '', '', 'zh', array(), 0)) === 202, 'removed record decreases actual total');
inquiry_fixture(401, 6);
check(count(call_crm('sales_inquiry_summary_rows', 'DEMO WEBSITE 401', '', 'zh')) === 1, 'old website record searchable beyond former 120 limit');
$mode = 'staff';
$authorized = call_crm('sales_inquiry_summary_rows', '', '', 'zh', array(), 0);
check(count($authorized) === 205, 'staff total includes only assigned website and visible client records');
check(count(call_crm('sales_inquiry_summary_rows', 'DEMO WEBSITE 401', '', 'zh')) === 1, 'staff can reach own historical record');
check(count(call_crm('sales_inquiry_summary_rows', 'DEMO WEBSITE 400', '', 'zh')) === 0, 'staff cannot search foreign historical record');
foreach ($authorized as $row) { check($row['assigned_sales_id'] === 11, 'no unauthorized owner in full counts'); }
check(count(call_crm('sales_inquiry_summary_rows', '', '', 'zh', array('owner' => '22'), 0)) === 0, 'owner filter cannot widen staff access');
check(count(call_crm('sales_inquiry_summary_rows', '', 'walkin', 'zh', array('status' => 'new'), 0)) === 2, 'source and status filters retained');
$mode = 'manager';
check(count(call_crm('sales_inquiry_summary_rows', '', '', 'zh', array('owner' => '22'), 0)) === 202, 'manager owner filter over full data');
inquiry_fixture(0, 0);
check(array_values(inquiry_kpis(rendered('inquiries', 'zh'))) === array(0, 0, 0, 0, 0), 'empty counts truthful');
echo 'PASS ' . ($checks - $start_checks) . " inquiry counter checks; synthetic data only.\n";
