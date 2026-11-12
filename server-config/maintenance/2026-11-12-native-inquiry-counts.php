<?php
// Private WP-CLI audit. Only counts leave memory; no customer HTML is exported.
if (PHP_SAPI !== 'cli' || !defined('WP_CLI') || !WP_CLI) { exit(1); }
$file = $args[0] ?? '';
if ($file !== 'live') {
    if (dirname($file) !== '/home/harmath2/codex-backups/' . basename(dirname($file)) || !is_file($file)) {
        throw new RuntimeException('Private staged class required');
    }
    require $file;
}
$class = $file === 'live' ? 'Harmat_Sales_Manager' : 'Harmat_Sales_Manager_Crm_Review';
$instance = (new ReflectionClass($class))->newInstanceWithoutConstructor();
$invoke = function($method, ...$arguments) use ($instance) { return (new ReflectionMethod($instance, $method))->invoke($instance, ...$arguments); };
add_filter('update_user_metadata', function() { return true; }, PHP_INT_MAX);
add_filter('pre_wp_mail', function() { throw new RuntimeException('Mail prohibited'); }, PHP_INT_MAX);
global $wpdb;
$wpdb->query('START TRANSACTION READ ONLY');
try {
    $managers = get_users(array('role__in' => array('administrator', 'harmat_sales_manager'), 'fields' => 'ID', 'number' => 1));
    if (!$managers) { throw new RuntimeException('Manager audit context unavailable'); }
    wp_set_current_user($managers[0]);
    if (!current_user_can('harmat_manage_sales')) { throw new RuntimeException('Manager capability unavailable'); }
    $post_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='harmat_offer_lead' AND post_status IN ('private','publish','draft') ORDER BY post_date DESC, ID DESC");
    $leads = $invoke('get_leads');
    $expected_sources = array('website' => count($post_ids), 'walkin' => 0, 'broker' => 0);
    foreach ($leads as $lead) { $expected_sources[$invoke('lead_source_type', $lead)]++; }
    $expected_total = count($post_ids) + count($leads);
    $all = $invoke('sales_inquiry_summary_rows', '', '', 'zh', array(), 0);
    if (count($all) !== $expected_total) { throw new RuntimeException('Full manager count does not match source records'); }
    $actual_sources = array_fill_keys(array_keys($expected_sources), 0);
    foreach ($all as $row) { $actual_sources[$row['source']]++; }
    if ($actual_sources !== $expected_sources) { throw new RuntimeException('Source subtotals mismatch'); }
    $languages = array();
    foreach (array('zh', 'hu') as $lang) {
        $_GET = array('sales_lang' => $lang, 'view' => 'inquiries');
        ob_start();
        try { $invoke('render_sales_portal_inquiries'); $html = ob_get_contents(); }
        finally { ob_end_clean(); }
        $doc = new DOMDocument(); @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xp = new DOMXPath($doc);
        $cards = $xp->query('//section[contains(@class,"harmat-sales-kpis-compact")]/article');
        $labels = $lang === 'zh' ? array('网站询价', '自来询价', '经纪人询价', '本次载入', '总计')
            : array('Weboldali érdeklődés', 'Saját érdeklődés', 'Közvetítői érdeklődés', 'Betöltött rekordok', 'Összes rekord');
        $expected = array($expected_sources['website'], $expected_sources['walkin'], $expected_sources['broker'], min(160, $expected_total), $expected_total);
        if ($cards->length !== 5) { throw new RuntimeException('Summary card contract'); }
        foreach ($cards as $index => $card) {
            if (trim($card->getElementsByTagName('small')->item(0)->textContent) !== $labels[$index]
                || (int) $card->getElementsByTagName('strong')->item(0)->textContent !== $expected[$index]) {
                throw new RuntimeException('Rendered source/total card mismatch');
            }
        }
        if ($xp->query('//table[contains(@class,"harmat-sales-inquiry-table")]/tbody/tr')->length !== min(160, $expected_total)
            || ($expected_total && $xp->query('//table[contains(@class,"harmat-sales-inquiry-table")]/thead/tr/th')->length !== 9)) {
            throw new RuntimeException('Bounded table contract');
        }
        $languages[] = array('language' => $lang, 'source_cards_match' => true, 'total_matches' => true);
        unset($html, $doc, $xp);
    }
    $staff_checks = 0;
    $staff = get_users(array('role' => 'harmat_sales_staff', 'fields' => 'ID', 'number' => 3));
    foreach ($staff as $user_id) {
        wp_set_current_user($user_id);
        if ($invoke('is_sales_manager_user')) { continue; }
        $expected = count($invoke('visible_leads', $leads));
        foreach ($post_ids as $id) { if ($invoke('offer_inquiry_assigned_sales_id', (int) $id) === (int) $user_id) { $expected++; } }
        $visible = $invoke('sales_inquiry_summary_rows', '', '', 'zh', array(), 0);
        if (count($visible) !== $expected) { throw new RuntimeException('Staff count scope mismatch'); }
        $staff_checks++;
    }
    echo wp_json_encode(array('status' => 'PASS', 'version' => constant($class . '::VERSION'), 'website_records' => count($post_ids),
        'client_file_records' => count($leads), 'source_counts' => $actual_sources, 'total_records' => $expected_total,
        'loaded_records' => min(160, $expected_total), 'languages' => $languages, 'staff_contexts_checked' => $staff_checks,
        'read_only' => true, 'submissions' => 0, 'grain' => 'records_not_unique_people'));
} finally { $wpdb->query('ROLLBACK'); }
