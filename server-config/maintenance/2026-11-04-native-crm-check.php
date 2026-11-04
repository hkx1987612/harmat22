<?php
// WP-CLI only. Native renders stay in memory; output contains counts/hashes only.
if (PHP_SAPI !== 'cli' || !defined('WP_CLI') || !WP_CLI) { exit(1); }
global $wpdb;
$file = $args[0] ?? '';
if (dirname($file) !== '/home/harmath2/codex-backups/' . basename(dirname($file)) || !is_file($file)) {
    throw new RuntimeException('Private staged class required');
}
require $file;
$old = (new ReflectionClass('Harmat_Sales_Manager'))->newInstanceWithoutConstructor();
$new = (new ReflectionClass('Harmat_Sales_Manager_Crm_Review'))->newInstanceWithoutConstructor();
$managers = get_users(array('role__in' => array('administrator', 'harmat_sales_manager'), 'fields' => 'ID', 'number' => 1));
if (!$managers) { throw new RuntimeException('Manager fixture unavailable'); }
wp_set_current_user($managers[0]);
if (!current_user_can('harmat_manage_sales')) { throw new RuntimeException('Manager capability unavailable'); }
add_filter('update_user_metadata', function () { return true; }, PHP_INT_MAX);
add_filter('pre_wp_mail', function () { throw new RuntimeException('Mail prohibited'); }, PHP_INT_MAX);
$wpdb->query('START TRANSACTION READ ONLY');
function crm_review_render($instance, $method) {
    ob_start();
    try { (new ReflectionMethod($instance, $method))->invoke($instance); return ob_get_contents(); }
    finally { ob_end_clean(); }
}
function crm_review_forms($html, $post_only = false) {
    $doc = new DOMDocument(); @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    $forms = array();
    foreach ($doc->getElementsByTagName('form') as $form) {
        if (!$post_only || strtolower($form->getAttribute('method')) === 'post') { $forms[] = $doc->saveHTML($form); }
    }
    if ($post_only) { sort($forms); }
    if (!$forms) { throw new RuntimeException('Native forms missing'); }
    return $forms;
}
$results = array();
try {
    foreach (array('zh', 'hu') as $lang) {
        $_GET = array('sales_lang' => $lang);
        foreach (array('clients', 'inquiries') as $view) {
            $_GET['view'] = $view;
            $method = 'render_sales_portal_' . $view;
            $a = crm_review_forms(crm_review_render($old, $method));
            $b = crm_review_forms(crm_review_render($new, $method));
            if ($a !== $b) { throw new RuntimeException('Native form contract changed: ' . $view); }
            $results[] = array('lang' => $lang, 'view' => $view, 'forms' => count($a), 'unchanged' => true);
        }
        if (method_exists($new, 'sort_sales_deals_for_display')) {
            $_GET['view'] = 'deals';
            $a = crm_review_forms(crm_review_render($old, 'render_sales_portal_deals'), true);
            $b = crm_review_forms(crm_review_render($new, 'render_sales_portal_deals'), true);
            if ($a !== $b) { throw new RuntimeException('Native deal POST forms changed'); }
            $results[] = array('lang' => $lang, 'view' => 'deals', 'post_forms' => count($a), 'unchanged' => true);
            $dates = array(current_time('Y-m-d'), date('Y-m-d', strtotime('-1 day', current_time('timestamp'))));
            $actual = (new ReflectionMethod($new, 'sales_walkin_counts_by_date'))->invoke($new, $dates);
            $expected = array_fill_keys($dates, 0);
            $leads = (new ReflectionMethod($old, 'get_leads'))->invoke($old);
            foreach ($leads as $lead) {
                $source = (new ReflectionMethod($old, 'lead_source_type'))->invoke($old, $lead);
                $day = substr((string) ($lead['created_at'] ?? ''), 0, 10);
                if ($source === 'walkin' && isset($expected[$day])) { $expected[$day]++; }
            }
            if ($actual !== $expected) { throw new RuntimeException('Native daily walk-in aggregation mismatch'); }
            $results[] = array('lang' => $lang, 'walkin_dates_checked' => count($dates), 'aggregation_matches' => true);
        }
        $dashboard = crm_review_render($new, 'render_sales_portal_dashboard');
        $traffic = crm_review_render($new, 'render_sales_portal_traffic');
        if (strpos($dashboard, 'harmat-sales-overview-lists') === false || strpos($traffic, 'id="sales-traffic-overview"') === false) {
            throw new RuntimeException('Native overview missing');
        }
    }
    $counts = array(); $dates = array();
    $since = date('Y-m-d H:i:s', current_time('timestamp') - 30 * DAY_IN_SECONDS);
    $logs = get_option('harmat_sales_activity_log_v1', array());
    foreach ((array) $logs as $row) {
        if (!is_array($row) || ($row['created_at'] ?? '') < $since) { continue; }
        $action = sanitize_key($row['action'] ?? 'unknown');
        $counts[$action] = ($counts[$action] ?? 0) + 1;
        $dates[] = substr($row['created_at'], 0, 10);
    }
    arsort($counts); sort($dates);
    echo wp_json_encode(array('status' => 'PASS', 'native_forms' => $results, 'read_only' => true,
        'activity' => array('retained_log_rows' => count((array) $logs), 'last_30_days_actions' => $counts,
            'first_day' => $dates ? reset($dates) : null, 'last_day' => $dates ? end($dates) : null,
            'limitation' => 'Capped action log, not page-view usage; absence does not prove a module unused.')));
} finally { $wpdb->query('ROLLBACK'); }
