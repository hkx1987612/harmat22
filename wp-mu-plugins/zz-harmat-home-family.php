<?php
/**
 * Plugin Name: Harmat Home Family
 * Description: Reversible homepage presentation; native sales shell remains owned by existing modules.
 * Version: 1.0.11
 */
defined('ABSPATH') || exit;

function harmat_home_family_context() {
    foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
        if (defined($flag) && constant($flag)) return false;
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'), true)
        || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_preview()
        || is_404() || is_search() || is_embed() || is_trackback() || !is_main_query()
        || (function_exists('wp_is_json_request') && wp_is_json_request())
        || isset($_GET['elementor-preview']) || !is_front_page() || !is_page(164)
        || get_queried_object_id() !== 164 || (int)get_option('page_on_front') !== 164
        || get_option('show_on_front') !== 'page' || get_page_template_slug(164) !== ''
        || get_post_status(164) !== 'publish' || get_post_field('post_name',164,'raw') !== 'fooldal'
        || get_post_field('post_password',164,'raw') !== '') return false;
    $raw=get_post_field('post_content',164,'raw');
    return is_string($raw) && strlen($raw)===42575
        && hash('sha256',$raw)==='dabadc35c52bd029d1097178a52eebd08a5a5ccca1bb5a9d53c12797a473dedf'
        && function_exists('harmat_sai_property_summary_data')
        && function_exists('hm_migrated_property_floorplan_image_from_uploads');
}

function harmat_home_family_assets() {
    $directory=__DIR__.'/harmat-home-family';
    $manifest=$directory.'/manifest.json';
    if (is_link($directory) || !is_file($manifest) || is_link($manifest)
        || hash_file('sha256',$manifest)!=='21e6ebec9bf0bfcbc273c42b5dc33f07ce96547fa04ccdd0b53faf780d3587b3') return false;
    $pins=json_decode(file_get_contents($manifest),true);
    if (!is_array($pins) || !isset($pins['body.php'],$pins['home-family.css'],$pins['home-family.js'])) return false;
    foreach ($pins as $name=>$hash) {
        if (!is_string($name) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/D',$name)
            || !is_string($hash) || !preg_match('/\A[a-f0-9]{64}\z/D',$hash)
            || !is_file($directory.'/'.$name) || is_link($directory.'/'.$name)
            || hash_file('sha256',$directory.'/'.$name)!==$hash) return false;
    }
    return $pins;
}

function harmat_home_family_units() {
    $units=array();
    foreach (array('A1-1-L6'=>4317,'A2-4-L5'=>5212,'A1-1-L5'=>4311) as $code=>$id) {
        $post=get_post($id);
        if (!$post || $post->post_type!=='property' || $post->post_status!=='publish' || $post->post_password!=='') return false;
        $data=harmat_sai_property_summary_data($id);
        if (!is_array($data) || ($data['title'] ?? '')!==$code
            || !isset($data['rooms'],$data['sales_area'],$data['status'],$data['price'],$data['hide_price'])
            || !is_numeric($data['rooms']) || $data['rooms']<=0
            || !is_numeric($data['sales_area']) || !is_finite((float)$data['sales_area']) || $data['sales_area']<=0
            || !in_array($data['status'],array('current','reserved','sold'),true)
            || !is_bool($data['hide_price']) || !is_int($data['price'])) return false;
        $url=get_permalink($id);
        $floorplan=hm_migrated_property_floorplan_image_from_uploads($code,get_post_meta($id,'property_floorplan',true));
        foreach (array($url,$floorplan) as $link) {
            $parts=parse_url($link);
            if (!is_array($parts) || ($parts['scheme'] ?? '')!=='https' || ($parts['host'] ?? '')!=='harmat22.hu'
                || isset($parts['user']) || isset($parts['pass'])) return false;
        }
        $units[$code]=array('code'=>$code,'url'=>$url,'rooms'=>$data['rooms'],'sales_area'=>$data['sales_area'],
            'status'=>$data['status'],'price_huf'=>!$data['hide_price'] && $data['price']>0 ? $data['price'] : null,
            'floorplan_url'=>$floorplan);
    }
    return $units;
}

// DOM parsing validates ownership; never serialize the native document or form.
function harmat_home_family_document($html) {
    if (!class_exists('DOMDocument') || !preg_match('~\A\s*<!doctype html>~i',$html)
        || !preg_match('~</html>\s*\z~i',$html)) return false;
    $doc=new DOMDocument();
    if (!$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING)) return false;
    foreach (array('html','head','body','main','h1') as $tag) {
        if ($doc->getElementsByTagName($tag)->length!==1) return false;
    }
    $ids=array();
    $protected=array('main'=>'main','masthead'=>'header','colophon'=>'footer','h22-offer-modal'=>'div',
        'h22-offer-form'=>'form','hm-cookie-settings-button'=>'button','harmat-internal-language'=>'div');
    foreach ($doc->getElementsByTagName('*') as $node) {
        $id=$node->getAttribute('id');
        if ($id==='') continue;
        if (isset($ids[$id]) && (isset($protected[$id]) || $id==='harmat-home-family')) return false;
        $ids[$id]=$node;
    }
    foreach ($protected as $id=>$tag) {
        if (!isset($ids[$id]) || $ids[$id]->tagName!==$tag) return false;
        if ($id==='main') continue;
        $body=false;
        for ($node=$ids[$id]->parentNode;$node;$node=$node->parentNode) {
            if ($node instanceof DOMElement && $node->tagName==='main') return false;
            if ($node instanceof DOMElement && $node->tagName==='body') $body=true;
        }
        if (!$body) return false;
    }
    for ($node=$ids['h22-offer-form']->parentNode;$node && $node!==$ids['h22-offer-modal'];$node=$node->parentNode) {}
    if (!$node) return false;
    return array($doc,$ids);
}

// Rewrite only the two manifest-pinned hero anchors; native popup handling stays unchanged.
function harmat_home_family_hero_actions($html) {
    if (!is_string($html)) return false;
    $choose='<a class="hhf-button hhf-button-white" href="#hhf-lakasok"><span data-i18n="chooseApartment">'
        . "Lak\u{00e1}st v\u{00e1}lasztok</span>";
    $journal='<a class="hhf-hero-journal" href="#hhf-epitesi-naplo"><span data-i18n="readJournal">'
        . "\u{00c9}p\u{00ed}t\u{00e9}si napl\u{00f3}</span>"
        . '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg></a>';
    if (substr_count($html,'<div class="hhf-actions hhf-hero-actions">')!==1
        || substr_count($html,$choose)!==1 || substr_count($html,$journal)!==1) return false;
    $selector='<a class="hhf-button hhf-button-white" href="https://harmat22.hu/virtualis-lakasvalaszto/"><span data-i18n="chooserLink">'
        . "Virtu\u{00e1}lis lak\u{00e1}sv\u{00e1}laszt\u{00f3}</span>";
    $booking='<a class="hhf-button hhf-button-white hhf-hero-booking" href="https://harmat22.hu/lakaskereso/"><span data-i18n="navSearch">'
        . "Lak\u{00e1}skeres\u{0151}</span>"
        . '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg></a>';
    return str_replace(array($choose,$journal),array($selector,$booking),$html);
}

function harmat_home_family_output($html) {
    if (!is_string($html) || !harmat_home_family_context() || !($pins=harmat_home_family_assets())
        || (http_response_code() && http_response_code()!==200) || strpos($html,'id="harmat-home-family"')!==false) return $html;
    try {
        $parsed=harmat_home_family_document($html);
        if (!$parsed || substr_count($html,'</head>')!==1 || substr_count($html,'</main>')!==1
            || substr_count($html,'<main id="main" class="site-main">')!==1) return $html;
        $start=strpos($html,'<main id="main" class="site-main">');
        $end=strpos($html,'</main>')+7;
        $old=substr($html,$start,$end-$start);
        foreach (array('elementor-164','harmat-native-home-video','harmat-home-featured-title') as $marker) {
            if (strpos($old,$marker)===false) return $html;
        }
        $head=strpos($html,'</head>');
        if ($head>=$start) return $html;
        $seo='<h1 class="harmat-seo-h1">' . "\u{00da}j \u{00e9}p\u{00ed}t\u{00e9}s\u{0171} lak\u{00e1}sok Budapest X. ker\u{00fc}let\u{00e9}ben - Harmat Lak\u{00f3}park</h1>";
        if (substr_count($html,$seo)!==1) return $html;
        $seo_at=strpos($html,$seo);
        if ($seo_at>=$start && $seo_at<$end) return $html;
        foreach (array('masthead','colophon','h22-offer-modal','h22-offer-form','hm-cookie-settings-button','harmat-internal-language') as $id) {
            if (preg_match_all('~<[^>]+\bid=["\x27]'.preg_quote($id,'~').'["\x27][^>]*>~i',$html,$m,PREG_OFFSET_CAPTURE)!==1) return $html;
            if ($m[0][0][1]>=$start && $m[0][0][1]<$end) return $html;
        }
        $harmat_home_family_units=harmat_home_family_units();
        if (!$harmat_home_family_units) return $html;
        $replacement=include __DIR__.'/harmat-home-family/body.php';
        $replacement=harmat_home_family_hero_actions($replacement);
        if (function_exists('harmat_cu_home_teaser')) $replacement=harmat_cu_home_teaser($replacement);
        if (!is_string($replacement) || substr_count($replacement,'id="harmat-home-family"')!==1
            || preg_match('~<(?:form|input|textarea|header|footer|iframe|script|html|head|body)\b~i',$replacement)) return $html;
        $base=content_url('/mu-plugins/harmat-home-family/');
        $replacement=str_replace('__HHF_ASSET_BASE__',esc_url($base),$replacement);
        if (strpos($replacement,'__HHF_')!==false || strpos($replacement,'src="assets/')!==false) return $html;
        $resources='<style id="harmat-home-family-header-flow">'
            . 'body.home.page-id-164.opal-header-absolute #masthead,body.home.page-id-164.opal-header-absolute #masthead > div.site-header{position:relative!important;background:#fff}'
            . 'body.home.page-id-164.opal-header-absolute #my-sticky-header,body.home.page-id-164.opal-header-absolute #my-sticky-header.elementor-sticky__spacer{height:76px!important;min-height:0!important}'
            . 'body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow,body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow>.e-con-inner{height:76px!important;min-height:0!important;padding-block:0!important}'
            . 'body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow>.e-con-inner{align-items:center}'
            . 'body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow>.e-con-inner>.e-con{height:100%!important;min-height:0!important;padding-block:0!important;justify-content:center}'
            . 'body.home.page-id-164.opal-header-absolute #my-sticky-header .elementor-widget-icon{padding-block:0!important}'
            . 'body.home.page-id-164.opal-header-absolute #my-sticky-header .elementor-widget-opal-site-logo img{width:60px!important;height:60px!important;object-fit:contain}'
            . 'body.home.page-id-164 #harmat-home-family h1,body.home.page-id-164 #harmat-home-family h2,body.home.page-id-164 #harmat-home-family h3{padding:0}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-eyebrow,body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dt,body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>span{font-size:14px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>strong,body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>.hhf-highlight-date{font-size:32px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-highlight-grid .hhf-highlight-unit{font-size:20px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>.hhf-highlight-word{font-size:28px;line-height:1.4;overflow-wrap:anywhere}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-highlight-date>span{font-size:16px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-apartment-top h3{font-size:21px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-apartment-floor,body.home.page-id-164 #harmat-home-family .hhf-price>span,body.home.page-id-164 #harmat-home-family .hhf-fine-print,body.home.page-id-164 #harmat-home-family .hhf-apartment-note,body.home.page-id-164 #harmat-home-family .hhf-journal-date,body.home.page-id-164 #harmat-home-family figcaption{font-size:14px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-status,body.home.page-id-164 #harmat-home-family .hhf-apartment-facts dt{font-size:13px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-apartment-facts dd{font-size:18px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-apartment-link,body.home.page-id-164 #harmat-home-family .hhf-text-link{font-size:15px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-section-heading,body.home.page-id-164 #harmat-home-family .hhf-everyday-heading{margin-bottom:24px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-journal-copy h2,body.home.page-id-164 #harmat-home-family .hhf-showroom-row h2,body.home.page-id-164 #harmat-home-family .hhf-chooser h2{margin-bottom:12px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-section{padding-block:64px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-panorama{padding-top:0}'
            . '@media(max-width:620px){body.home.page-id-164 #harmat-home-family .hhf-section{padding-block:40px}body.home.page-id-164 #harmat-home-family .hhf-panorama{padding-top:0}body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>strong,body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>.hhf-highlight-date{font-size:30px}body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>.hhf-highlight-word{font-size:24px}body.home.page-id-164 #harmat-home-family figcaption{font-size:13px}}'
            . '@media(max-width:360px){body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>.hhf-highlight-word{font-size:22px}}'
            . 'body.home.page-id-164 #harmat-home-family{--muted:#58645d;background:#f8f8f6}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-highlights{background:#f6f5f2}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-apartments{background:#f5f4f2}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-journal{background:#edf2ef}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-everyday{background:#f8f8f6}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-panorama,body.home.page-id-164 #harmat-home-family .hhf-team{background:#fff}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-chooser{background:#e9f0eb}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-contact{background:#f1f0ed}'
            . 'body.home.page-id-164 #harmat-home-family h2{font-size:36px;line-height:1.3}'
            . 'body.home.page-id-164 #harmat-home-family h3{font-size:26px;line-height:1.35}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-apartment-top h3{font-size:21px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-panorama-video-card h3{font-size:23px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-panorama-detail h3{font-size:24px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-journal-copy>p:not(.hhf-eyebrow):not(.hhf-journal-date):not(.hhf-planned-note),body.home.page-id-164 #harmat-home-family .hhf-everyday-heading>p,body.home.page-id-164 #harmat-home-family .hhf-everyday-story>p,body.home.page-id-164 #harmat-home-family .hhf-park-row p:not(.hhf-eyebrow),body.home.page-id-164 #harmat-home-family .hhf-showroom-copy>p:not(.hhf-eyebrow),body.home.page-id-164 #harmat-home-family .hhf-chooser p:not(.hhf-eyebrow),body.home.page-id-164 #harmat-home-family .hhf-team p:not(.hhf-eyebrow),body.home.page-id-164 #harmat-home-family .hhf-contact-intro{font-size:16px;line-height:1.75}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-journal-copy .hhf-planned-note{font-size:14px;line-height:1.7}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-media-label{font-size:13px;line-height:1.5}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-apartment-facts dt,body.home.page-id-164 #harmat-home-family .hhf-apartment-floor{font-size:14px;line-height:1.6}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-price strong:not(.hhf-price-hidden){font-size:24px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-price strong span{font-size:16px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>strong{min-height:46px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-section{padding-block:56px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-panorama{padding-top:56px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-section-heading,body.home.page-id-164 #harmat-home-family .hhf-everyday-heading{margin-bottom:24px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-journal-copy h2,body.home.page-id-164 #harmat-home-family .hhf-showroom-copy h2,body.home.page-id-164 #harmat-home-family .hhf-chooser h2{margin-bottom:16px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-journal-grid,body.home.page-id-164 #harmat-home-family .hhf-everyday-heading,body.home.page-id-164 #harmat-home-family .hhf-park-row,body.home.page-id-164 #harmat-home-family .hhf-showroom-row,body.home.page-id-164 #harmat-home-family .hhf-chooser-grid,body.home.page-id-164 #harmat-home-family .hhf-team-grid,body.home.page-id-164 #harmat-home-family .hhf-contact-grid{gap:48px}'
            . '@media(max-width:1000px) and (min-width:621px){body.home.page-id-164 #harmat-home-family .hhf-apartment-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}}'
            . '@media(max-width:900px){body.home.page-id-164 #harmat-home-family h2{font-size:32px}body.home.page-id-164 #harmat-home-family .hhf-journal-grid,body.home.page-id-164 #harmat-home-family .hhf-everyday-heading,body.home.page-id-164 #harmat-home-family .hhf-park-row,body.home.page-id-164 #harmat-home-family .hhf-showroom-row,body.home.page-id-164 #harmat-home-family .hhf-chooser-grid,body.home.page-id-164 #harmat-home-family .hhf-team-grid,body.home.page-id-164 #harmat-home-family .hhf-contact-grid{gap:28px}}'
            . '@media(max-width:620px){body.home.page-id-164 #harmat-home-family h2{font-size:28px}body.home.page-id-164 #harmat-home-family .hhf-everyday-story h3,body.home.page-id-164 #harmat-home-family .hhf-park-row h3{font-size:23px}body.home.page-id-164 #harmat-home-family .hhf-section{padding-block:40px}body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>strong{min-height:60px}body.home.page-id-164 #harmat-home-family .hhf-panorama{padding-top:40px}}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-hero-booking{background:transparent;border-color:#fff;color:#fff}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-hero-booking:hover{background:#fff;color:var(--forest)}'
            . '@media(max-width:620px){body.home.page-id-164 #harmat-home-family .hhf-hero-actions{flex-direction:column;align-items:stretch;max-width:320px}body.home.page-id-164 #harmat-home-family .hhf-highlight-grid .hhf-highlight-unit{display:block;font-size:16px;line-height:1.25}body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>strong{min-height:64px}}'
            . 'body.home.page-id-164 #harmat-home-family{--muted:#47574e;--body:#35463c}'
            . 'body.home.page-id-164 #harmat-home-family h1,body.home.page-id-164 #harmat-home-family h2,body.home.page-id-164 #harmat-home-family h3{font-weight:600}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>strong{font-weight:500}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-eyebrow,body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dt,body.home.page-id-164 #harmat-home-family .hhf-apartment-facts dt,body.home.page-id-164 #harmat-home-family .hhf-apartment-floor,body.home.page-id-164 #harmat-home-family .hhf-journal-date{font-weight:500}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-hero h1{font-size:38px;text-shadow:0 1px 3px rgba(15,33,24,.55)}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-hero-actions .hhf-button{font-size:16px;font-weight:600}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-hero-booking{background:rgba(15,33,24,.3)}'
            . '@media(max-width:620px){body.home.page-id-164 #harmat-home-family .hhf-hero h1{font-size:32px}}'
            . '@media(max-width:360px){body.home.page-id-164 #harmat-home-family .hhf-hero h1{font-size:30px}}'
            . '@media(max-width:767px){body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow>.e-con-inner{display:grid!important;grid-template-columns:minmax(133px,1fr) 36px minmax(100px,1fr);gap:4px;padding-inline:12px!important;width:100%!important;box-sizing:border-box;flex-wrap:nowrap}body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow>.e-con-inner>.e-con{width:auto!important;min-width:0;padding-inline:0!important}body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow>.e-con-inner>.elementor-element-58842f8{align-items:flex-start}body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow>.e-con-inner>.elementor-element-fba2323{align-items:flex-end}body.home.page-id-164.opal-header-absolute #my-sticky-header .elementor-widget-opal-site-logo img{width:36px!important;height:36px!important}body.home.page-id-164.opal-header-absolute #my-sticky-header .elementor-element-56f08cc{display:block!important;width:100px!important;max-width:100%!important;min-width:0;gap:0!important}body.home.page-id-164.opal-header-absolute #my-sticky-header .elementor-element-56f08cc .elementor-button-wrapper{display:block!important;width:100%!important;max-width:100%!important;min-width:0;gap:0!important}body.home.page-id-164.opal-header-absolute #my-sticky-header .elementor-element-56f08cc a.elementor-button{display:flex;align-items:center;justify-content:center;box-sizing:border-box;width:100%;min-height:40px;padding:8px 6px;font-size:12px;line-height:1.2;letter-spacing:0;text-transform:none!important;white-space:nowrap}body.home.page-id-164 #harmat-internal-language[data-hil-anchor=menu],body.home.page-id-164 #harmat-internal-language[data-hil-anchor=held],body.home.page-id-164 #harmat-internal-language[data-hil-anchor=fallback]{width:84px!important;max-width:84px!important}body.home.page-id-164 #harmat-internal-language[data-hil-anchor=fallback]{left:62px!important;right:auto!important;top:16px!important;bottom:auto!important}body.home.page-id-164 #harmat-internal-language>button[data-hil-language]{flex:1 1 0;width:auto;padding-inline:1px}body.home.page-id-164 #harmat-internal-language>button[data-hil-language=hu]{flex:1.3 1 0}}'
            . '@media(max-width:767px){body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow{padding-inline:0!important}body.home.page-id-164.opal-header-absolute #my-sticky-header .elementor-widget-icon{padding-inline:0!important}body.home.page-id-164.opal-header-absolute #my-sticky-header .elementor-element-56f08cc{padding-inline:0!important}body.home.page-id-164 #harmat-internal-language>button[data-hil-language=hu]{flex:1.4 1 0}}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-hero{width:100%;max-width:none;margin:0;scroll-margin-top:76px}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-hero-actions .hhf-button{min-width:0;max-width:100%;white-space:normal}'
            . 'body.home.page-id-164 #harmat-home-family .hhf-hero-actions .hhf-button>span{min-width:0}'
            . '@media(min-width:768px){body.home.page-id-164 #harmat-home-family .hhf-hero{height:auto;min-height:0;max-height:calc(100svh - 108px);aspect-ratio:16/9;background:#111a15}body.home.page-id-164 #harmat-home-family .hhf-hero-poster,body.home.page-id-164 #harmat-home-family .hhf-hero-video{object-fit:contain;object-position:center}}'
            . '</style>';
        if (strpos($replacement,'data-hcu-home="1"')!==false && function_exists('harmat_cu_resources')) {
            $resources.=harmat_cu_resources();
        }
        foreach (array('pannellum.css','home-family.css') as $name) {
            if (!isset($pins[$name])) return $html;
            $resources.='<link rel="stylesheet" href="'.esc_url($base.$name.'?ver='.substr($pins[$name],0,16)).'">';
        }
        foreach (array('pannellum.js','home-family.js') as $name) {
            if (!isset($pins[$name])) return $html;
            $resources.='<script defer src="'.esc_url($base.$name.'?ver='.substr($pins[$name],0,16)).'"></script>';
        }
        $changes=array(array($start,$end-$start,$replacement),array($head,0,$resources),
            array($seo_at,strlen($seo),str_replace(array('<h1 ','</h1>'),array('<p ','</p>'),$seo)));
        usort($changes,static function($a,$b){return $b[0]<=>$a[0];});
        $result=$html;
        foreach ($changes as $change) $result=substr_replace($result,$change[2],$change[0],$change[1]);
        $verified=harmat_home_family_document($result);
        return $verified && isset($verified[1]['harmat-home-family'])
            && $verified[0]->getElementsByTagName('h1')->item(0)->getAttribute('id')==='hero-title' ? $result : $html;
    } catch (Throwable $error) {
        return $html;
    }
}

function harmat_home_family_buffer() {
    if (!harmat_home_family_context() || !harmat_home_family_assets() || headers_sent()) return;
    $intact=true;
    ob_start(static function($html,$phase) use (&$intact) {
        if (!($phase & PHP_OUTPUT_HANDLER_FINAL) || ($phase & (PHP_OUTPUT_HANDLER_CLEAN|PHP_OUTPUT_HANDLER_FLUSH))) $intact=false;
        return $intact ? harmat_home_family_output($html) : $html;
    });
}
add_action('template_redirect','harmat_home_family_buffer',-4000);
