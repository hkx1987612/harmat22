<?php
/**
 * Plugin Name: Harmat CRM Bandwidth Widget
 * Description: Shows current-month hosting traffic beside visitor metrics in the private sales CRM.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_crm_bw_is_sales_request(): bool
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return false;
    }

    $path = isset($_SERVER['REQUEST_URI'])
        ? (string) parse_url((string) wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH)
        : '';

    return untrailingslashit($path) === '/sales'
        && is_user_logged_in()
        && current_user_can('harmat_view_sales');
}

add_action('template_redirect', function (): void {
    if (!harmat_crm_bw_is_sales_request()) {
        return;
    }

    ob_start('harmat_crm_bw_filter_sales_html');
}, -200);

function harmat_crm_bw_current_usage(): array
{
    $current_month = current_time('Y-m');
    $usage = get_option('harmat_bw_last_usage', array());
    $usage = is_array($usage) ? $usage : array();

    if (($usage['month'] ?? '') !== $current_month) {
        $usage = array(
            'ok' => true,
            'usage_mib' => 0,
            'limit_mib' => defined('HARMAT_BW_LIMIT_MIB') ? HARMAT_BW_LIMIT_MIB : 512000,
            'percent' => 0,
            'month' => $current_month,
            'source' => 'monthly_reset',
            'reset_at' => current_time('mysql'),
        );
    }

    $usage['usage_mib'] = max(0, (float) ($usage['usage_mib'] ?? 0));
    $usage['limit_mib'] = max(1, (int) ($usage['limit_mib'] ?? 512000));
    $usage['percent'] = max(0, (float) ($usage['percent'] ?? 0));

    return $usage;
}

function harmat_crm_bw_text(string $lang, array $usage): array
{
    $month_number = (int) current_time('n');
    $hu_months = array(
        1 => 'január',
        2 => 'február',
        3 => 'március',
        4 => 'április',
        5 => 'május',
        6 => 'június',
        7 => 'július',
        8 => 'augusztus',
        9 => 'szeptember',
        10 => 'október',
        11 => 'november',
        12 => 'december',
    );
    $updated_at = (string) (
        $usage['archive_updated_at']
        ?? $usage['reset_at']
        ?? current_time('mysql')
    );
    $updated_timestamp = strtotime($updated_at);
    $updated_label = $updated_timestamp
        ? wp_date('Y-m-d H:i', $updated_timestamp)
        : current_time('Y-m-d H:i');

    if ($lang === 'hu') {
        return array(
            'title' => 'Havi adatforgalom',
            'month' => ucfirst($hu_months[$month_number] ?? ''),
            'reset' => 'Minden hónap 1-jén automatikusan nullázódik',
            'updated' => 'Frissítve: ' . $updated_label,
        );
    }

    return array(
        'title' => '本月带宽',
        'month' => $month_number . '月流量',
        'reset' => '每月1日自动归零',
        'updated' => '更新于 ' . $updated_label,
    );
}

function harmat_crm_bw_number(float $value, string $lang, int $decimals = 0): string
{
    return number_format(
        $value,
        $decimals,
        $lang === 'hu' ? ',' : '.',
        $lang === 'hu' ? ' ' : ','
    );
}

function harmat_crm_bw_widget_markup(string $lang, bool $compact = false): string
{
    $usage = harmat_crm_bw_current_usage();
    $text = harmat_crm_bw_text($lang, $usage);
    $percent = (float) $usage['percent'];
    $percent_label = harmat_crm_bw_number($percent, $lang, 1) . '%';
    $usage_label = harmat_crm_bw_number((float) $usage['usage_mib'], $lang)
        . ' / '
        . harmat_crm_bw_number((float) $usage['limit_mib'], $lang)
        . ' MB';
    $tone = $percent >= 95
        ? 'danger'
        : ($percent >= 85 ? 'warning' : ($percent >= 70 ? 'watch' : 'normal'));

    if ($compact) {
        return '<span class="harmat-crm-bandwidth-compact harmat-crm-bandwidth-' . esc_attr($tone) . '"'
            . ' title="' . esc_attr($usage_label . ' · ' . $text['reset']) . '">'
            . '<small>' . esc_html($text['title']) . '</small>'
            . '<strong>' . esc_html($percent_label) . '</strong>'
            . '</span>';
    }

    $bar_width = min(100, max(0, $percent));

    return '<article class="harmat-crm-bandwidth-card harmat-crm-bandwidth-' . esc_attr($tone) . '">'
        . '<small>' . esc_html($text['title']) . '</small>'
        . '<strong>' . esc_html($percent_label) . '</strong>'
        . '<span>' . esc_html($usage_label) . '</span>'
        . '<div class="harmat-crm-bandwidth-progress" aria-hidden="true"><i style="width:'
        . esc_attr(number_format($bar_width, 1, '.', '')) . '%"></i></div>'
        . '<em>' . esc_html($text['month'] . ' · ' . $text['reset']) . '</em>'
        . '<em>' . esc_html($text['updated']) . '</em>'
        . '</article>';
}

function harmat_crm_bw_filter_sales_html(string $html): string
{
    if ($html === '' || strpos($html, 'harmat-sales-portal-body') === false) {
        return $html;
    }

    $lang = strpos($html, 'data-sales-lang="hu"') !== false ? 'hu' : 'zh';
    $style = <<<'CSS'
<style id="harmat-crm-bandwidth-style">
.harmat-sales-traffic-grid{grid-template-columns:repeat(5,minmax(0,1fr))}
.harmat-sales-traffic-strip{grid-template-columns:repeat(5,minmax(0,1fr)) auto}
.harmat-crm-bandwidth-card{position:relative;overflow:hidden}
.harmat-crm-bandwidth-card em{display:block;margin-top:7px;color:#687178;font-size:11px;font-style:normal;font-weight:800;line-height:1.4}
.harmat-crm-bandwidth-progress{height:7px;margin-top:10px;overflow:hidden;border-radius:999px;background:#e8e2d8}
.harmat-crm-bandwidth-progress i{display:block;height:100%;border-radius:inherit;background:#1f7a4d}
.harmat-crm-bandwidth-watch .harmat-crm-bandwidth-progress i{background:#c48a2c}
.harmat-crm-bandwidth-warning .harmat-crm-bandwidth-progress i{background:#dc6b1c}
.harmat-crm-bandwidth-danger{border-color:#efb6b1!important;background:#fff4f3!important}
.harmat-crm-bandwidth-danger .harmat-crm-bandwidth-progress i{background:#c9362b}
.harmat-sales-traffic-strip .harmat-crm-bandwidth-danger{border-color:#efb6b1;background:#fff4f3}
.harmat-sales-traffic-strip .harmat-crm-bandwidth-danger strong{color:#b42318}
@media(max-width:1000px){
  .harmat-sales-traffic-grid,.harmat-sales-traffic-strip{grid-template-columns:1fr}
}
</style>
CSS;

    $html = str_replace('</head>', $style . '</head>', $html);
    $html = preg_replace(
        '~(<div class="harmat-sales-traffic-grid">)~',
        '$1' . harmat_crm_bw_widget_markup($lang),
        $html,
        1
    );
    $html = preg_replace(
        '~(<section class="harmat-sales-traffic-strip">)~',
        '$1' . harmat_crm_bw_widget_markup($lang, true),
        $html,
        1
    );

    return $html;
}
