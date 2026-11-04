<?php
// Offline WP stubs: synthetic records only; writes and mail fail closed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
$root = dirname(__DIR__, 2);
$checks = 0;
$mode = 'manager';
$lang = 'zh';
$options = array();
$user_meta = array();
$posts = array();
$post_meta = array();
function register_activation_hook(...$args) {}
function register_deactivation_hook(...$args) {}
function add_action(...$args) {}
function add_filter(...$args) {}
function apply_filters($tag, $value, ...$args) { return $value; }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function esc_textarea($v) { return esc_html($v); }
function esc_url($v) { return esc_html($v); }
function esc_url_raw($v) { return (string) $v; }
function esc_js($v) { return addslashes((string) $v); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $v)); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_email($v) { return (string) $v; }
function wp_unslash($v) { return $v; }
function wp_strip_all_tags($v) { return strip_tags((string) $v); }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function absint($v) { return abs((int) $v); }
function home_url($path = '') { return 'https://crm.example.test' . $path; }
function admin_url($path = '') { return home_url('/wp-admin/' . $path); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option(...$args) { throw new RuntimeException('Forbidden option write'); }
function wp_mail(...$args) { throw new RuntimeException('Forbidden mail'); }
function get_transient($key) { return false; }
function delete_transient($key) {
    if ($key === 'harmat_lead_error_11' && isset($_GET['lead_error'])) { return true; }
    throw new RuntimeException('Unexpected transient deletion');
}
function get_current_user_id() { return $GLOBALS['mode'] === 'guest' ? 0 : 11; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function current_user_can($cap) {
    if (!is_user_logged_in()) { return false; }
    return $cap === 'harmat_view_sales' || ($GLOBALS['mode'] === 'manager' && in_array($cap, array('harmat_manage_sales', 'harmat_delete_sales_records'), true));
}
function wp_get_current_user() {
    return (object) array('ID' => get_current_user_id(), 'display_name' => 'DEMO', 'user_login' => 'demo',
        'roles' => array($GLOBALS['mode'] === 'manager' ? 'harmat_sales_manager' : 'harmat_sales_staff'));
}
function get_userdata($id) { return (object) array('ID' => $id, 'display_name' => 'DEMO ' . $id, 'user_login' => 'demo' . $id, 'roles' => array('harmat_sales_staff')); }
function get_users($args = array()) { return array(); }
function get_user_meta($id, $key, $single = false) { return $GLOBALS['user_meta'][$id][$key] ?? ''; }
function update_user_meta($id, $key, $value) {
    if ($key !== '_harmat_portal_lang_sales') { throw new RuntimeException('Unexpected user write'); }
    $GLOBALS['user_meta'][$id][$key] = $value;
}
function current_time($format) { return $format === 'timestamp' ? strtotime('2026-11-04 12:00:00') : date($format === 'mysql' ? 'Y-m-d H:i:s' : $format, strtotime('2026-11-04 12:00:00')); }
function wp_date($format, $stamp) { return date($format, $stamp); }
function date_i18n($format, $stamp) { return date($format, $stamp); }
function get_bloginfo($key) { return $key === 'charset' ? 'UTF-8' : 'DEMO'; }
function nocache_headers() {}
function wp_logout_url($url = '') { return home_url('/logout'); }
function wp_die($msg) { throw new RuntimeException((string) $msg); }
function wp_nonce_field(...$args) { echo '<input type="hidden" name="_wpnonce" value="demo-only">'; }
function wp_create_nonce(...$args) { return 'demo-only'; }
function selected($value, $compare, $echo = true) { $s = (string) $value === (string) $compare ? ' selected' : ''; if ($echo) echo $s; return $s; }
function add_query_arg($args, $value = null, $url = null) {
    if (!is_array($args)) { $args = array($args => $value); } else { $url = $value; }
    $url = $url ?: home_url('/sales/');
    $parts = parse_url($url);
    parse_str($parts['query'] ?? '', $query);
    $query = array_merge($query, $args);
    $base = explode('?', explode('#', $url)[0])[0];
    return $base . ($query ? '?' . http_build_query($query) : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}
function remove_query_arg($keys, $url) {
    $p = parse_url($url); parse_str($p['query'] ?? '', $q);
    foreach ((array) $keys as $key) unset($q[$key]);
    return explode('?', $url)[0] . ($q ? '?' . http_build_query($q) : '');
}
function get_posts($args) {
    $rows = $args['post_type'] === 'harmat_offer_lead' ? $GLOBALS['posts'] : array();
    return array_slice($rows, 0, $args['posts_per_page'] ?? 20);
}
function get_post_meta($id, $key, $single = false) { return $GLOBALS['post_meta'][$id][$key] ?? ''; }
function get_post_field($field, $id) { foreach ($GLOBALS['posts'] as $p) { if ($p->ID === $id) return $p->$field ?? ''; } return ''; }
function get_the_date($format, $id) { return date($format, strtotime(get_post_field('post_date', $id))); }
function get_the_title($id) { return get_post_field('post_title', $id) ?: 'DEMO A1'; }
function get_permalink($id) { return home_url('/property/demo-' . $id . '/'); }
function get_post_type($id) { return 'harmat_offer_lead'; }
class WP_Query {
    public $found_posts = 0;
    public function __construct($args) {
        foreach ($GLOBALS['posts'] as $p) {
            if (!isset($args['date_query']) || substr($p->post_date, 0, 10) === substr($args['date_query'][0]['after'], 0, 10)) $this->found_posts++;
        }
    }
}
require $root . '/wp-plugins/harmat-sales-manager/harmat-sales-manager.php';
require $root . '/wp-mu-plugins/zz-harmat-crm-bandwidth-widget.php';
$crm = $GLOBALS['harmat_sales_manager'];
function call_crm($method, ...$args) { return (new ReflectionMethod($GLOBALS['crm'], $method))->invoke($GLOBALS['crm'], ...$args); }
function check($ok, $message) { $GLOBALS['checks']++; if (!$ok) throw new RuntimeException('FAIL: ' . $message); }
function rendered($view, $language = 'zh', $extra = array()) {
    $_GET = array_merge(array('view' => $view, 'sales_lang' => $language), $extra);
    $_SERVER['REQUEST_URI'] = '/sales/?' . http_build_query($_GET);
    ob_start(); call_crm('render_sales_portal'); $html = ob_get_clean();
    $html = call_crm('localize_sales_portal_html', $html, $language);
    return harmat_crm_bw_filter_sales_html($html);
}
function dom_of($html) {
    $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    return new DOMXPath($dom);
}
$options['harmat_sales_visit_stats'] = array('2026-11-04' => array('pageviews' => 12, 'visitors' => array('demo1' => 1, 'demo2' => 1),
    'pages' => array('/lakaskereso/' => array('title' => 'DEMO Lakáskereső', 'views' => 8))));
foreach (array(101 => 11, 102 => 22, 103 => 0) as $id => $assignee) {
    $posts[] = (object) array('ID' => $id, 'post_date' => '2026-11-04 10:00:00', 'post_content' => 'DEMO', 'post_title' => 'DEMO Inquiry ' . $id);
    $post_meta[$id] = array('_harmat_assigned_sales_id' => $assignee, '_harmat_offer_phone' => '+36000000000',
        '_harmat_offer_email' => 'demo-' . $id . '@example.test', '_harmat_offer_posted' => array('your-name' => 'DEMO Inquiry ' . $id));
}
foreach (array(201 => 11, 202 => 22, 203 => 0) as $id => $assignee) {
    $options['harmat_sales_deals_v1'][$id] = array('id' => $id, 'client_name' => 'DEMO Deal ' . $id,
        'assigned_sales_id' => $assignee, 'crm_code' => 'DEMO-' . $id, 'updated_at' => '2026-11-04 10:00:00');
}
$out = $root . '/outputs/2026-11-04-crm-review';
if (in_array('--preview', $argv, true) && !is_dir($out)) mkdir($out, 0700, true);
foreach (array('manager', 'staff') as $mode) {
    foreach (array('zh', 'hu') as $lang) {
        $html = rendered('dashboard', $lang); $xp = dom_of($html);
        check($xp->query('//nav[@class="harmat-sales-nav"]/a')->length === 5, "$mode $lang five core navigation entries");
        check($xp->query('//nav[@class="harmat-sales-nav"]/a[contains(@href,"view=tasks")]')->length === 0, 'tasks removed from primary navigation');
        check($xp->query('//details[contains(@class,"harmat-sales-nav-more")]//a[contains(@href,"view=tasks")]')->length === 1, 'task records remain accessible');
        check($xp->query('//section[@class="harmat-sales-traffic-strip"]/a[contains(@href,"view=traffic")]')->length === 1, 'single direct traffic destination');
        check(strpos($html, 'harmat-sales-task-overdue"') === false, 'no overdue task list on overview');
        check(strpos($html, 'harmat-sales-linkage-fold"') === false, 'no duplicate funnel');
        check(strpos($html, 'harmat-crm-bandwidth-card ') === false, 'bandwidth removed from daily overview');
        check($xp->query('//section[@class="harmat-sales-overview-lists"]//table/tbody/tr')->length === 7, 'seven daily traffic rows replace overdue tasks');
        $visible = array_column(call_crm('visible_deals', call_crm('get_deals')), 'id');
        check(in_array(201, $visible, true) && (in_array(202, $visible, true) === ($mode === 'manager')), 'deal scope retained');
        check((strpos($html, 'DEMO Inquiry 102') !== false) === ($mode === 'manager'), 'other inquiry scope retained');
        check(strpos($html, '#inquiry-website-101') !== false, 'recent inquiry links to matching record');
        $visible_copy = str_replace('中文', '', strip_tags(preg_replace('~<(script|style)\b[^>]*>.*?</\1>~s', '', $html)));
        check($lang !== 'hu' || !preg_match('/[\x{4e00}-\x{9fff}]/u', $visible_copy), 'Hungarian visible copy has no Chinese except language selector');
        if (in_array('--preview', $argv, true)) file_put_contents("$out/$mode-$lang-dashboard.html", $html);
        $traffic = rendered('traffic', $lang); $xp = dom_of($traffic);
        check($xp->query('//*[@id="sales-traffic-overview" and not(ancestor::details)]')->length === 1, 'traffic visible without second click');
        check($xp->query('//details[contains(@class,"harmat-sales-nav-more") and @open]')->length === 0, 'more menu does not cover destination');
        check($xp->query('//section[@class="harmat-sales-traffic-strip"]')->length === 0, 'no duplicate traffic strip on traffic page');
        check(($xp->query('//details[@class="harmat-crm-technical-details"]')->length === 1) === ($mode === 'manager'), 'bandwidth technical detail manager only');
        if (in_array('--preview', $argv, true)) file_put_contents("$out/$mode-$lang-traffic.html", $traffic);
        $inquiries = rendered('inquiries', $lang); $xp = dom_of($inquiries);
        check($xp->query('//tr[@id="inquiry-website-101"]')->length === 1, 'inquiry target exists');
        check($xp->query('//section[@class="harmat-sales-traffic-strip"]')->length === 0, 'inquiry page starts with its own business content');
        if (in_array('--preview', $argv, true)) file_put_contents("$out/$mode-$lang-inquiries.html", $inquiries);
        $clients = rendered('clients', $lang); $xp = dom_of($clients);
        check($xp->query('//details[@class="harmat-sales-client-editor" and not(@open)]')->length === 1, 'new client form collapsed by default');
        check($xp->query('//details[@class="harmat-sales-client-editor"]//form[@method="post"]//input[@name="client_name"]')->length === 1, 'native client fields retained');
        if (in_array('--preview', $argv, true)) file_put_contents("$out/$mode-$lang-clients.html", $clients);
    }
}
$mode = 'manager';
$options['harmat_broker_leads_v1'] = array(301 => array('id' => 301, 'client_name' => 'DEMO EDIT',
    'assigned_sales_id' => 11, 'created_at' => '2026-11-04 10:00:00'));
foreach (array('manager', 'staff') as $mode) {
    foreach (array('zh', 'hu') as $lang) {
        $edit = dom_of(rendered('clients', $lang, array('edit_lead' => 301)));
        check($edit->query('//details[@class="harmat-sales-client-editor" and @open]')->length === 1, 'editing auto-opens native form');
        check($edit->query('//input[@name="client_name" and @value="DEMO EDIT"]')->length === 1, 'editing values retained');
        $error = dom_of(rendered('clients', $lang, array('lead_error' => 'test')));
        check($error->query('//details[@class="harmat-sales-client-editor" and @open]')->length === 1, 'validation error leaves form visible');
    }
}
$mode = 'manager';
$options['harmat_broker_leads_v1'] = array();
$options['harmat_sales_deals_v1'] = array(); $posts = array();
check(strpos(rendered('dashboard'), '暂无可查看的询价。') !== false, 'empty overview');
check(harmat_crm_bw_filter_sales_html('<html><body>public</body></html>') === '<html><body>public</body></html>', 'non-CRM HTML unchanged');
echo "PASS $checks CRM UI checks; no database, mail, or network used.\n";
