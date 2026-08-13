<?php
/**
 * Plugin Name: Harmat Search and AI Discovery
 * Description: Consolidates public entities, property facts, and IndexNow discovery without changing property data.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

const HARMAT_SAI_INDEXNOW_KEY = 'dd9b45afff1be991410cbeff16a313bf';
const HARMAT_SAI_INDEXNOW_QUEUE = 'harmat_sai_indexnow_queue';
const HARMAT_SAI_INDEXNOW_LAST_RESULT = 'harmat_sai_indexnow_last_result';

function harmat_sai_pilot_property_ids(): array
{
    return array(4292, 4311, 4317, 4327, 4365, 4385, 4386, 4419, 4263, 5148, 5141, 5312);
}

function harmat_sai_is_public_request(): bool
{
    return !is_admin()
        && !wp_doing_ajax()
        && !wp_is_json_request()
        && !is_feed()
        && !is_search()
        && !is_404();
}

add_action('plugins_loaded', function (): void {
    remove_action('wp_head', 'harmat_perf_property_structured_data', 36);

    if (!empty($GLOBALS['harmat_sales_manager']) && is_object($GLOBALS['harmat_sales_manager'])) {
        remove_action(
            'wp_head',
            array($GLOBALS['harmat_sales_manager'], 'frontend_structured_data'),
            30
        );
    }
}, PHP_INT_MAX);

add_filter('wpseo_schema_organization', function ($data) {
    if (!is_array($data)) {
        return $data;
    }

    $data['name'] = 'Harmat Lakópark';
    $data['url'] = home_url('/');
    $data['telephone'] = '+36300733375';
    $data['email'] = 'ertekesites@harmat22.hu';
    $data['address'] = array(
        '@type' => 'PostalAddress',
        'streetAddress' => 'Harmat utca 22.',
        'postalCode' => '1105',
        'addressLocality' => 'Budapest',
        'addressCountry' => 'HU',
    );
    $data['contactPoint'] = array(
        '@type' => 'ContactPoint',
        'telephone' => '+36300733375',
        'email' => 'ertekesites@harmat22.hu',
        'contactType' => 'értékesítés',
        'areaServed' => 'HU',
        'availableLanguage' => array('hu', 'en', 'zh'),
    );

    if (!empty($data['sameAs']) && is_array($data['sameAs'])) {
        $home = untrailingslashit(home_url('/'));
        $data['sameAs'] = array_values(array_filter(
            $data['sameAs'],
            static function ($url) use ($home): bool {
                return untrailingslashit((string) $url) !== $home;
            }
        ));
        if (!$data['sameAs']) {
            unset($data['sameAs']);
        }
    }

    return $data;
}, 20);

function harmat_sai_property_status(int $post_id): string
{
    if (get_post_meta($post_id, 'property_status', true) === 'sold') {
        return 'sold';
    }

    if (get_post_meta($post_id, 'property_under_offer', true)) {
        return 'reserved';
    }

    return 'current';
}

function harmat_sai_is_ground_floor(int $post_id): bool
{
    $title = (string) get_the_title($post_id);
    $floor = strtolower(trim((string) get_post_meta($post_id, 'property_address_street_number', true)));

    return preg_match('/(?:^|-)F(?:-|$)/i', $title) === 1
        || in_array($floor, array('f', 'fsz', 'földszint', '0'), true);
}

function harmat_sai_sales_area(int $post_id): float
{
    $override = get_post_meta($post_id, '_harmat_sales_area', true);
    if ($override !== '') {
        return (float) $override;
    }

    $area = (float) get_post_meta($post_id, 'property_building_area', true);
    $outdoor = (float) get_post_meta($post_id, 'property_land_area', true);
    $sales_area = $area + (harmat_sai_is_ground_floor($post_id) ? 0 : ($outdoor * 0.5));

    return $sales_area > 0 ? floor($sales_area * 100) / 100 : 0;
}

function harmat_sai_property_floor_label(int $post_id): string
{
    if (harmat_sai_is_ground_floor($post_id)) {
        return 'földszint';
    }

    $floor = trim((string) get_post_meta($post_id, 'property_address_street_number', true));
    if ($floor === '') {
        return '';
    }

    return rtrim($floor, '.') . '. emelet';
}

function harmat_sai_property_floor_location(int $post_id): string
{
    if (harmat_sai_is_ground_floor($post_id)) {
        return 'földszintjén';
    }

    $floor = trim((string) get_post_meta($post_id, 'property_address_street_number', true));
    if ($floor === '') {
        return '';
    }

    return rtrim($floor, '.') . '. emeletén';
}

function harmat_sai_property_summary_data(int $post_id): array
{
    $status = harmat_sai_property_status($post_id);
    $status_labels = array(
        'current' => 'elérhető',
        'reserved' => 'foglalva',
        'sold' => 'eladva',
    );

    return array(
        'title' => (string) get_the_title($post_id),
        'building' => trim((string) get_post_meta($post_id, 'property_address_street', true)),
        'floor' => harmat_sai_property_floor_label($post_id),
        'ground' => harmat_sai_is_ground_floor($post_id),
        'rooms' => (float) get_post_meta($post_id, 'property_rooms', true),
        'bedrooms' => (float) get_post_meta($post_id, 'property_bedrooms', true),
        'area' => (float) get_post_meta($post_id, 'property_building_area', true),
        'outdoor' => (float) get_post_meta($post_id, 'property_land_area', true),
        'sales_area' => harmat_sai_sales_area($post_id),
        'price' => (int) get_post_meta($post_id, 'property_price', true),
        'hide_price' => get_post_meta($post_id, '_harmat_hide_front_price', true) === 'yes'
            || get_post_meta($post_id, 'property_price_display', true) === 'no',
        'status' => $status,
        'status_label' => $status_labels[$status],
    );
}

function harmat_sai_format_area(float $value): string
{
    return number_format($value, 2, ',', ' ') . ' m²';
}

function harmat_sai_property_summary_text(int $post_id): string
{
    $data = harmat_sai_property_summary_data($post_id);
    $location = '';
    $floor_location = harmat_sai_property_floor_location($post_id);

    if ($data['building'] !== '' && $floor_location !== '') {
        $location = ' a Harmat Lakópark ' . $data['building'] . ' épületének ' . $floor_location . ' található';
    }

    $room_text = $data['rooms'] > 0 ? number_format($data['rooms'], 0, ',', ' ') . ' szobás ' : '';
    $summary = 'Az ' . $data['title'] . $location . ', ' . $room_text . 'lakás.';

    if ($data['area'] > 0) {
        $summary .= ' Az alapterület ' . harmat_sai_format_area($data['area']) . '.';
    }

    if ($data['outdoor'] > 0) {
        $outdoor_label = $data['ground'] ? 'kert / terasz' : 'terasz / erkély';
        $summary .= ' A lakáshoz ' . harmat_sai_format_area($data['outdoor']) . '-es ' . $outdoor_label . ' kapcsolódik.';
    }

    if ($data['sales_area'] > 0) {
        $summary .= ' Az értékesítési terület ' . harmat_sai_format_area($data['sales_area']) . '.';
    }

    $summary .= ' Az ingatlan jelenlegi státusza: ' . $data['status_label'] . '.';

    if (!$data['hide_price'] && $data['price'] > 0) {
        $summary .= ' Az aktuális vételár ' . number_format($data['price'], 0, ',', ' ') . ' Ft.';
    } else {
        $summary .= ' Az aktuális vételárról az értékesítési csapat ad tájékoztatást.';
    }

    return $summary;
}

function harmat_sai_entity_graph(): array
{
    $project = array(
        '@type' => 'ApartmentComplex',
        '@id' => home_url('/#harmat-lakopark'),
        'name' => 'Harmat Lakópark',
        'url' => home_url('/'),
        'description' => 'Új építésű lakópark Budapest X. kerületében, a Harmat utca 22. alatt.',
        'image' => home_url('/wp-content/uploads/2025/11/Harmat_Logo_250.png'),
        'telephone' => '+36300733375',
        'address' => array(
            '@type' => 'PostalAddress',
            'streetAddress' => 'Harmat utca 22.',
            'postalCode' => '1105',
            'addressLocality' => 'Budapest',
            'addressCountry' => 'HU',
        ),
    );

    $graph = array($project);

    if (!is_singular('property')) {
        return $graph;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return $graph;
    }

    $data = harmat_sai_property_summary_data($post_id);
    $url = get_permalink($post_id);
    $availability = array(
        'current' => 'https://schema.org/InStock',
        'reserved' => 'https://schema.org/LimitedAvailability',
        'sold' => 'https://schema.org/SoldOut',
    );
    $image = '';

    if (function_exists('hm_migrated_property_floorplan_image_from_uploads')) {
        $image = hm_migrated_property_floorplan_image_from_uploads($data['title']);
    }
    if ($image === '') {
        $image = (string) get_the_post_thumbnail_url($post_id, 'large');
    }

    $apartment = array(
        '@type' => 'Apartment',
        '@id' => $url . '#apartment',
        'name' => $data['title'],
        'identifier' => $data['title'],
        'url' => $url,
        'mainEntityOfPage' => $url . '#webpage',
        'description' => harmat_sai_property_summary_text($post_id),
        'isPartOf' => array('@id' => home_url('/#harmat-lakopark')),
        'address' => array(
            '@type' => 'PostalAddress',
            'streetAddress' => 'Harmat utca 22.',
            'postalCode' => '1105',
            'addressLocality' => 'Budapest',
            'addressCountry' => 'HU',
        ),
        'offers' => array(
            '@type' => 'Offer',
            'url' => $url,
            'availability' => $availability[$data['status']],
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => array('@id' => home_url('/#organization')),
        ),
    );

    if ($image !== '') {
        $apartment['image'] = $image;
    }
    if ($data['sales_area'] > 0) {
        $apartment['floorSize'] = array(
            '@type' => 'QuantitativeValue',
            'value' => round($data['sales_area'], 2),
            'unitCode' => 'MTK',
        );
    }
    if ($data['rooms'] > 0) {
        $apartment['numberOfRooms'] = $data['rooms'];
    }
    if ($data['bedrooms'] > 0) {
        $apartment['numberOfBedrooms'] = $data['bedrooms'];
    }
    if ($data['floor'] !== '') {
        $apartment['floorLevel'] = $data['floor'];
    }
    if (!$data['hide_price'] && $data['price'] > 0) {
        $apartment['offers']['price'] = $data['price'];
        $apartment['offers']['priceCurrency'] = 'HUF';
    }

    $properties = array();
    if ($data['building'] !== '') {
        $properties[] = array('@type' => 'PropertyValue', 'name' => 'Épület', 'value' => $data['building']);
    }
    if ($data['floor'] !== '') {
        $properties[] = array('@type' => 'PropertyValue', 'name' => 'Emelet', 'value' => $data['floor']);
    }
    if ($data['outdoor'] > 0) {
        $properties[] = array(
            '@type' => 'PropertyValue',
            'name' => $data['ground'] ? 'Kert / terasz' : 'Terasz / erkély',
            'value' => round($data['outdoor'], 2),
            'unitCode' => 'MTK',
        );
    }
    if ($properties) {
        $apartment['additionalProperty'] = $properties;
    }

    $graph[] = $apartment;

    return $graph;
}

add_action('wp_head', function (): void {
    if (!harmat_sai_is_public_request()) {
        return;
    }

    $schema = array(
        '@context' => 'https://schema.org',
        '@graph' => harmat_sai_entity_graph(),
    );

    echo '<script type="application/ld+json" id="harmat-search-entity-schema">';
    echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo '</script>' . "\n";
}, 38);

function harmat_sai_property_summary_html(int $post_id): string
{
    $title = get_the_title($post_id);

    return '<section class="harmat-search-summary" data-harmat-property-search-summary="1" aria-labelledby="harmat-search-summary-title">'
        . '<h2 id="harmat-search-summary-title">' . esc_html($title) . ' lakás röviden</h2>'
        . '<p>' . esc_html(harmat_sai_property_summary_text($post_id)) . '</p>'
        . '</section>';
}

function harmat_sai_insert_property_summary(string $html): string
{
    if (
        !is_singular('property')
        || strpos($html, 'data-harmat-property-search-summary=') !== false
    ) {
        return $html;
    }

    $post_id = get_queried_object_id();
    if (!in_array($post_id, harmat_sai_pilot_property_ids(), true)) {
        return $html;
    }

    $hero_start = strpos($html, '<section class="harmat-property-hero');
    if ($hero_start === false) {
        return $html;
    }

    $hero_end = strpos($html, '</section>', $hero_start);
    if ($hero_end === false) {
        return $html;
    }

    $insert_at = $hero_end + strlen('</section>');

    return substr($html, 0, $insert_at)
        . harmat_sai_property_summary_html($post_id)
        . substr($html, $insert_at);
}

add_action('template_redirect', function (): void {
    if (
        !harmat_sai_is_public_request()
        || !is_singular('property')
        || !in_array(get_queried_object_id(), harmat_sai_pilot_property_ids(), true)
    ) {
        return;
    }

    ob_start('harmat_sai_insert_property_summary');
}, 0);

add_action('wp_head', function (): void {
    if (!is_singular('property') || !in_array(get_queried_object_id(), harmat_sai_pilot_property_ids(), true)) {
        return;
    }
    ?>
<style id="harmat-search-summary-style">
.harmat-search-summary{max-width:1180px;margin:0 auto 30px;padding:6px 26px 2px;border-top:1px solid rgba(152,112,51,.24);font-family:Montserrat,Arial,sans-serif;color:#263135}
.harmat-search-summary h2{margin:16px 0 10px;font-family:Marcellus,Georgia,serif;font-size:26px;font-weight:500;line-height:1.2;color:#263135}
.harmat-search-summary p{max-width:980px;margin:0;font-size:15px;line-height:1.75;color:#535d61}
@media(max-width:700px){.harmat-search-summary{margin:0 16px 24px;padding:4px 2px 0}.harmat-search-summary h2{font-size:22px}.harmat-search-summary p{font-size:14px;line-height:1.7}}
</style>
    <?php
}, 80);

function harmat_sai_indexnow_url(string $url): string
{
    $url = esc_url_raw($url);
    $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $url_host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));

    return $url !== '' && $site_host === $url_host ? $url : '';
}

function harmat_sai_indexnow_submit_urls(array $urls): array
{
    $urls = array_values(array_unique(array_filter(array_map('harmat_sai_indexnow_url', $urls))));
    if (!$urls) {
        return array('success' => false, 'code' => 0, 'count' => 0);
    }

    $payload = array(
        'host' => (string) wp_parse_url(home_url('/'), PHP_URL_HOST),
        'key' => HARMAT_SAI_INDEXNOW_KEY,
        'keyLocation' => home_url('/' . HARMAT_SAI_INDEXNOW_KEY . '.txt'),
        'urlList' => array_slice($urls, 0, 10000),
    );
    $response = wp_remote_post('https://api.indexnow.org/indexnow', array(
        'timeout' => 20,
        'headers' => array('Content-Type' => 'application/json; charset=UTF-8'),
        'body' => wp_json_encode($payload, JSON_UNESCAPED_SLASHES),
    ));
    $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
    $success = in_array($code, array(200, 202), true);
    $result = array(
        'success' => $success,
        'code' => $code,
        'count' => count($payload['urlList']),
        'time' => current_time('mysql'),
    );

    update_option(HARMAT_SAI_INDEXNOW_LAST_RESULT, $result, false);

    return $result;
}

function harmat_sai_store_indexnow_queue(array $urls): void
{
    $urls = array_values(array_unique(array_filter(array_map('harmat_sai_indexnow_url', $urls))));
    update_option(HARMAT_SAI_INDEXNOW_QUEUE, array_slice($urls, 0, 10000), false);
}

function harmat_sai_queue_indexnow_url(string $url): void
{
    $url = harmat_sai_indexnow_url($url);
    if ($url === '') {
        return;
    }

    $queue = get_option(HARMAT_SAI_INDEXNOW_QUEUE, array());
    $queue = is_array($queue) ? $queue : array();
    $queue[] = $url;
    harmat_sai_store_indexnow_queue($queue);

    if (!wp_next_scheduled('harmat_sai_send_indexnow_queue')) {
        wp_schedule_single_event(time() + 60, 'harmat_sai_send_indexnow_queue');
    }
}

function harmat_sai_queue_post(int $post_id): void
{
    if (
        wp_is_post_revision($post_id)
        || wp_is_post_autosave($post_id)
        || get_post_status($post_id) !== 'publish'
        || !in_array(get_post_type($post_id), array('post', 'page', 'property'), true)
        || !harmat_sai_indexnow_post_is_public($post_id)
    ) {
        return;
    }

    harmat_sai_queue_indexnow_url((string) get_permalink($post_id));
}

function harmat_sai_indexnow_post_is_public(int $post_id): bool
{
    if (get_post_type($post_id) !== 'page') {
        return true;
    }

    $excluded_slugs = array(
        'marketing-hozzajarulas',
        'koszonjuk',
        'property',
        '5-szobas',
        '4-szobas',
        '3-szobas',
        '2-szobas',
        'studio-apartman',
        'a-lakopark',
        'blog',
    );

    return !in_array((string) get_post_field('post_name', $post_id), $excluded_slugs, true);
}

add_action('save_post', 'harmat_sai_queue_post', 100);

function harmat_sai_queue_property_meta($meta_id, $post_id, $meta_key): void
{
    if (
        get_post_type($post_id) !== 'property'
        || !in_array($meta_key, array(
            'property_price',
            'property_status',
            'property_under_offer',
            'property_building_area',
            'property_land_area',
            '_harmat_sales_area',
            '_harmat_hide_front_price',
        ), true)
    ) {
        return;
    }

    harmat_sai_queue_post((int) $post_id);
}

add_action('added_post_meta', 'harmat_sai_queue_property_meta', 20, 3);
add_action('updated_post_meta', 'harmat_sai_queue_property_meta', 20, 3);
add_action('deleted_post_meta', 'harmat_sai_queue_property_meta', 20, 3);

add_action('harmat_sai_send_indexnow_queue', function (): void {
    $queue = get_option(HARMAT_SAI_INDEXNOW_QUEUE, array());
    $queue = is_array($queue) ? array_values(array_unique($queue)) : array();
    if (!$queue) {
        return;
    }

    harmat_sai_store_indexnow_queue(array());
    $batch = array_slice($queue, 0, 10000);
    $remaining = array_slice($queue, 10000);
    $result = harmat_sai_indexnow_submit_urls($batch);

    if (!$result['success']) {
        $remaining = array_merge($batch, $remaining);
    }
    harmat_sai_store_indexnow_queue($remaining);

    if ($remaining && !wp_next_scheduled('harmat_sai_send_indexnow_queue')) {
        wp_schedule_single_event(time() + ($result['success'] ? 60 : 900), 'harmat_sai_send_indexnow_queue');
    }
});
