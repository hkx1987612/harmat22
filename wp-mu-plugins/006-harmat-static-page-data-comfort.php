<?php
/**
 * Plugin Name: Harmat Static Page Data Comfort
 * Description: Removes a duplicate apartment dataset from static public pages while preserving the offer picker data.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_static_page_data_comfort_should_run() {
    if (is_admin()) {
        return false;
    }

    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
        return false;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
    }

    if (is_feed()) {
        return false;
    }

    return is_page(array('galeria', 'elerhetosegeink'));
}

function harmat_static_page_data_comfort_compact_html($html) {
    if (!is_string($html) || $html === '' || stripos($html, 'harmat-unified-sales-data-js-') === false) {
        return $html;
    }

    $data_script = '(function(){window.harmatUnifiedSalesData={items:{},apartments:[],source:"harmat-static-page-data-comfort"};})();';

    return preg_replace_callback(
        '~(<script\b(?=[^>]*\bid=["\']harmat-unified-sales-data-js-[^"\']*["\'])[^>]*>).*?(</script>)~is',
        function ($matches) use ($data_script) {
            return $matches[1] . $data_script . $matches[2];
        },
        $html,
        1
    );
}

function harmat_static_page_data_comfort_start_buffer() {
    if (!harmat_static_page_data_comfort_should_run() || headers_sent()) {
        return;
    }

    ob_start('harmat_static_page_data_comfort_compact_html');
}
add_action('template_redirect', 'harmat_static_page_data_comfort_start_buffer', 0);
