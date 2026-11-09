<?php
defined('ABSPATH') || exit;
$hvl_response = harmat_mpi_apartments();
$hvl_data = $hvl_response instanceof WP_REST_Response && $hvl_response->get_status() === 200
    ? $hvl_response->get_data() : array('items' => array());
$hvl_available = array_values(array_filter($hvl_data['items'] ?? array(), static function ($item) {
    return $item['status'] === 'current' && is_int($item['price_huf']) && $item['price_huf'] > 0;
}));
$hvl_selected = array_values(array_filter($hvl_available, static function ($item) {
    return in_array($item['code'], array('A1-1-L6', 'A1-4-L5', 'A1-4-L1'), true);
}));
usort($hvl_selected, static function ($a, $b) { return $a['rooms'] <=> $b['rooms']; });
$hvl_base = content_url('/mu-plugins/harmat-v4-landing/');
$hvl_media = content_url('/uploads/harmat-v4-landing/');
$hvl_body = file_get_contents(__DIR__ . '/body.html');
$hvl_cards = '';
foreach ($hvl_selected as $hvl_item) {
    $hvl_code = $hvl_item['code'];
    $hvl_plan = content_url('/uploads/2026/05/' . rawurlencode($hvl_code) . '-cn-floorplan-display.jpg');
    ob_start(); ?>
<article class="unit-card" data-apartment-id="<?php echo esc_attr($hvl_code); ?>" data-rooms="<?php echo esc_attr($hvl_item['rooms']); ?>">
  <div class="unit-heading"><h3><?php echo esc_html($hvl_code); ?></h3><span>Elérhető</span></div>
  <button type="button" class="floorplan-button" data-plan="<?php echo esc_attr($hvl_code); ?>" aria-label="<?php echo esc_attr($hvl_code . ' alaprajzának nagyítása'); ?>" title="Alaprajz nagyítása"><img src="<?php echo esc_url($hvl_plan); ?>" width="424" height="786" loading="lazy" alt="<?php echo esc_attr($hvl_code . ' hivatalos alaprajza'); ?>"><span><i data-lucide="zoom-in"></i>Alaprajz</span></button>
  <dl class="unit-specs"><div><dt>Szobaszám</dt><dd><?php echo esc_html($hvl_item['rooms']); ?></dd></div><div><dt>Értékesítési terület</dt><dd><?php echo esc_html(number_format($hvl_item['sales_area_m2'], 2, ',', ' ')); ?> m²</dd></div><div><dt>Kültéri terület</dt><dd><?php echo esc_html(number_format($hvl_item['outdoor_area_m2'], 2, ',', ' ')); ?> m²</dd></div></dl>
  <div class="unit-price"><span>Nyilvános vételár</span><strong><?php echo esc_html(number_format($hvl_item['price_huf'], 0, ',', ' ')); ?> Ft</strong></div>
  <a class="button primary" href="#opal-contactform-popup">Ajánlatot kérek<i data-lucide="arrow-right"></i></a>
</article>
<?php $hvl_cards .= ob_get_clean();
}
if ($hvl_cards === '') {
    $hvl_cards = '<p>A lakásadatok jelenleg nem érhetők el. Kérjen tájékoztatást az értékesítéstől, vagy nézze meg a teljes lakáskínálatot.</p>';
}
$hvl_price = $hvl_available
    ? number_format(min(array_column($hvl_available, 'price_huf')) / 1000000, 2, ',', ' ') . ' M Ft-tól'
    : 'Kérjen aktuális ajánlatot';
$hvl_body = strtr($hvl_body, array(
    '{{ASSET}}' => esc_url($hvl_media), '{{CARDS}}' => $hvl_cards,
    '{{PRICE}}' => esc_html($hvl_price), '{{DATE}}' => esc_html(wp_date('Y.m.d.')),
));
// Supply the existing modal with the same public, available inventory; no form changes.
$hvl_offer_items = array_map(static function ($item) {
    return array('title' => $item['code'], 'url' => $item['url'], 'building' => $item['building'],
        'floor' => $item['floor'], 'rooms' => $item['rooms'], 'sales_area' => $item['sales_area_m2'],
        'status' => $item['status'], 'hidePrice' => $item['price_huf'] === null,
        'price' => $item['price_huf']);
}, array_values(array_filter($hvl_data['items'] ?? array(), static function ($item) {
    return $item['status'] === 'current';
})));
?>
<!doctype html><html <?php language_attributes(); ?>><head>
<meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Harmat Lakópark: új építésű lakások Budapest X. kerületében. Nézze meg az alaprajzokat és a nyilvános árakat, kérjen személyes ajánlatot.">
<?php if (!defined('WPSEO_VERSION')) : ?>
<link rel="canonical" href="<?php echo esc_url(home_url('/uj-otthonok/')); ?>">
<?php endif; ?>
<?php wp_head(); ?>
<link rel="stylesheet" href="<?php echo esc_url($hvl_base . 'landing.css?ver=' . substr(hash_file('sha256', __DIR__ . '/landing.css'), 0, 16)); ?>">
<script defer src="<?php echo esc_url($hvl_base . 'lucide.js'); ?>"></script>
<script defer src="<?php echo esc_url($hvl_base . 'landing.js?ver=' . substr(hash_file('sha256', __DIR__ . '/landing.js'), 0, 16)); ?>"></script>
</head><body class="hvl-document">
<?php wp_body_open(); ?>
<div class="hvl-page"><?php echo $hvl_body; ?></div>
<script id="harmat-v4-public-inventory">window.harmatOfferApartments = <?php echo wp_json_encode($hvl_offer_items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<?php wp_footer(); ?>
</body></html>
