<?php
/**
 * Plugin Name: Harmat Single Property Comfort
 * Description: Keeps single apartment pages from shipping full offer-picker apartment datasets.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_single_property_comfort_should_run() {
    if (is_admin()) {
        return false;
    }

    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
        return false;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
    }

    if (is_feed() || !is_singular('property')) {
        return false;
    }

    return true;
}

function harmat_single_property_comfort_apartment_list_js() {
    return '(function(){var source=window.harmatSalesFront&&window.harmatSalesFront.items;var raw=[];if(Array.isArray(source)){raw=source;}else if(source&&typeof source==="object"){raw=Object.keys(source).map(function(key){return source[key];});}return raw.filter(Boolean).map(function(item){return {id:item.id||"",title:item.title||item.code||"",building:item.building||"",floor:item.floor||"",number:item.number||item.unit||"",area:item.area||item.salesArea||"",rooms:item.rooms||"",terrace:item.terrace||"",price:item.price||"",hidePrice:!!item.hidePrice,url:item.url||""};});})()';
}

function harmat_single_property_comfort_data_script() {
    $list_js = harmat_single_property_comfort_apartment_list_js();

    return '(function(){var source=window.harmatSalesFront&&window.harmatSalesFront.items;var list=' . $list_js . ';window.harmatUnifiedSalesData={items:source||{},apartments:list,source:"harmat-single-property-comfort"};window.harmatOfferApartments=list;})();';
}

function harmat_single_property_comfort_compact_html($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    if (stripos($html, 'harmat-unified-sales-data-js-') === false && stripos($html, 'harmat-offer-apartment-picker-js') === false) {
        return $html;
    }

    $data_script = harmat_single_property_comfort_data_script();
    $html = preg_replace_callback(
        '~(<script\b(?=[^>]*\bid=["\']harmat-unified-sales-data-js-[^"\']*["\'])[^>]*>).*?(</script>)~is',
        function ($matches) use ($data_script) {
            return $matches[1] . $data_script . $matches[2];
        },
        $html,
        1
    );

    $assignment = 'window.harmatOfferApartments = ' . harmat_single_property_comfort_apartment_list_js() . ';';
    $html = preg_replace_callback(
        '~window\.harmatOfferApartments\s*=\s*\[.*?\];~s',
        function () use ($assignment) {
            return $assignment;
        },
        $html,
        1
    );

    return $html;
}

function harmat_single_property_comfort_start_buffer() {
    if (!harmat_single_property_comfort_should_run() || headers_sent()) {
        return;
    }

    ob_start('harmat_single_property_comfort_compact_html');
}
add_action('template_redirect', 'harmat_single_property_comfort_start_buffer', 0);
