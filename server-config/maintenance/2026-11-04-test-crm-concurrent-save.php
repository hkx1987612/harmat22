<?php
// Interleaved requests against a memory-only option store. Never load WordPress.
if (PHP_SAPI !== 'cli') { exit(1); }
define('ABSPATH', __DIR__ . '/');
function register_activation_hook(...$args) {}
function register_deactivation_hook(...$args) {}
function add_action(...$args) {}
function add_filter(...$args) {}
$memory = array();
function update_option($key, $value, $autoload = null) { $GLOBALS['memory'][$key] = $value; return true; }
require dirname(__DIR__, 2) . '/wp-plugins/harmat-sales-manager/harmat-sales-manager.php';
$crm = $GLOBALS['harmat_sales_manager'];
$results = array();
foreach (array('save_leads' => 'harmat_broker_leads_v1', 'save_deals' => 'harmat_sales_deals_v1') as $method => $key) {
    $save = new ReflectionMethod($crm, $method);
    $base = array(1 => array('id' => 1, 'note' => 'before'), 2 => array('id' => 2, 'note' => 'before'));
    $request_a = $base; $request_b = $base;
    $request_a[1]['note'] = 'request A';
    $request_b[2]['note'] = 'request B';
    $save->invoke($crm, $request_a);
    $save->invoke($crm, $request_b);
    $lost_update = $memory[$key][1]['note'] !== 'request A';
    $request_a = $base; $request_b = $base;
    $request_a[3] = array('id' => 3, 'note' => 'new A');
    $request_b[3] = array('id' => 3, 'note' => 'new B');
    $save->invoke($crm, $request_a);
    $save->invoke($crm, $request_b);
    $id_collision = count($memory[$key]) === 3 && $memory[$key][3]['note'] === 'new B';
    $results[$method] = array('lost_update_reproduced' => $lost_update, 'concurrent_id_collision_reproduced' => $id_collision);
}
$report = array('status' => 'RISK_REPRODUCED', 'simulation_only' => true, 'live_writes' => 0, 'results' => $results,
    'scope' => 'Existing whole-option save methods; production persistence left unchanged.',
    'next_step' => 'Shared per-record conflict protection must cover sales-manager and legal-documents writers, plus caller success handling. Separate migration and rollback tests before any production storage change.');
$out = dirname(__DIR__, 2) . '/outputs/2026-11-04-crm-review';
if (!is_dir($out)) { mkdir($out, 0700, true); }
file_put_contents($out . '/concurrent-save-audit.json', json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
