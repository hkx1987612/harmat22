<?php
/**
 * Plugin Name: Harmat Home Data Comfort
 * Description: Reuses the homepage sales dataset instead of shipping duplicate apartment data.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_home_data_comfort_should_run() {
    if (is_admin()) {
        return false;
    }

    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
        return false;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
    }

    return !is_feed() && is_front_page();
}

function harmat_home_data_comfort_data_script() {
    return <<<'JS'
(function(){
var source=window.harmatSalesFront&&window.harmatSalesFront.items;
if(!source||typeof source!=="object"){return;}
var raw=Array.isArray(source)?source:Object.keys(source).map(function(key){return source[key];});
raw=raw.filter(Boolean);
raw.forEach(function(item){
if(item.area===undefined||item.area===null||item.area===""){item.area=item.salesArea||"";}
if(!item.number){item.number=item.unit||"";}
});
var available=raw.filter(function(item){return String(item.status||"").toLowerCase()==="current";}).map(function(item){
return {id:item.id||"",title:item.title||item.code||"",building:item.building||"",floor:item.floor||"",number:item.number||item.unit||"",area:item.area||item.salesArea||"",rooms:item.rooms||"",terrace:item.terrace||"",price:item.price||"",hidePrice:!!item.hidePrice,url:item.url||""};
});
window.harmatUnifiedSalesData={items:source,apartments:raw,source:"harmat-home-data-comfort"};
window.harmatOfferApartments=available;
})();
JS;
}

function harmat_home_data_comfort_compact_html($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $required_markers = array(
        'harmat-sales-manager-front-js-before',
        'harmat-unified-sales-data-js-',
        'harmat-offer-apartment-picker-js',
    );
    foreach ($required_markers as $marker) {
        if (stripos($html, $marker) === false) {
            return $html;
        }
    }

    $data_script = harmat_home_data_comfort_data_script();
    $html = preg_replace_callback(
        '~(<script\b(?=[^>]*\bid=["\']harmat-unified-sales-data-js-[^"\']*["\'])[^>]*>).*?(</script>)~is',
        function ($matches) use ($data_script) {
            return $matches[1] . $data_script . $matches[2];
        },
        $html,
        1
    );

    return preg_replace(
        '~window\.harmatOfferApartments\s*=\s*\[.*?\];~s',
        'window.harmatOfferApartments = window.harmatOfferApartments || [];',
        $html,
        1
    );
}

function harmat_home_data_comfort_start_buffer() {
    if (!harmat_home_data_comfort_should_run() || headers_sent()) {
        return;
    }

    ob_start('harmat_home_data_comfort_compact_html');
}
add_action('template_redirect', 'harmat_home_data_comfort_start_buffer', 0);
