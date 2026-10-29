<?php
// Isolated renderer fixture: no WordPress boot, network, mail or customer writes.
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
function add_action(...$args) {}
function add_filter(...$args) {}
function get_transient($key) { return false; }
function set_transient(...$args) {}
function home_url($path = '') { return 'https://harmat22.hu' . $path; }
function esc_attr($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_html($v) { return esc_attr($v); }
function esc_url($v) { return esc_attr($v); }
function number_format_i18n($v, $d = 0) { return number_format($v, $d, ',', ' '); }
function get_the_post_thumbnail_url(...$args) { return false; }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function wp_is_json_request() { return false; }
function is_feed() { return false; }
function is_robots() { return false; }
function is_page($slug) { return $slug === 'lakaskereso'; }
class BudgetFixtureSales {
    function frontend_sales_data() {
        $rows = [];
        foreach ([49256550, 50000000, 60000000, 80000000, 90000000, 0, 55000000] as $i => $price) {
            $rows[] = ['id' => $i + 1, 'title' => 'A1-1-L' . ($i + 1),
                'building' => $i < 4 ? 'A1' : 'A2', 'floor' => '1', 'rooms' => $i % 2 + 1,
                'status' => $i === 2 ? 'reserved' : 'current', 'statusLabel' => $i === 2 ? 'Foglalva' : 'Elérhető',
                'salesArea' => 50, 'b_area' => 45, 'terrace' => 10, 'price' => $price,
                'sqmPrice' => $price ? $price / 50 : 0, 'hidePrice' => $i === 6,
                'url' => home_url('/property/a1-1-l' . ($i + 1) . '/')];
        }
        return $rows;
    }
}
$harmat_sales_manager = new BudgetFixtureSales();
require $argv[1];
$perf = file_get_contents($argv[2]);
$start = strpos($perf, 'function harmat_perf_lakaskereso_search_patch()');
$end = strpos($perf, "add_action('wp_footer', 'harmat_perf_lakaskereso_search_patch'", $start);
eval(substr($perf, $start, $end - $start));
ob_start(); harmat_perf_lakaskereso_search_patch(); $patch = ob_get_clean();
require dirname($argv[2]) . '/004-harmat-lakaskereso-comfort.php';
ob_start(); harmat_lakaskereso_comfort_script(); $comfort = ob_get_clean();
echo json_encode(['html' => harmat_lakas_redesign_render(), 'css' => harmat_lakas_redesign_css(),
    'js' => harmat_lakas_redesign_js(), 'patch' => $patch, 'comfort' => $comfort], JSON_UNESCAPED_UNICODE);
