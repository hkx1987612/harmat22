<?php
// WP-CLI pure filters only. Never dispatches REST or invokes sales/mail callbacks.
if (!defined('WP_CLI') || !WP_CLI) {
    exit(1);
}
if (has_filter('rest_request_before_callbacks', 'harmat_offer_integrity_filter') !== 10) {
    throw new RuntimeException('Offer integrity hook changed');
}
$sources = array('K&uuml;lt&eacute;ri hirdet&eacute;s', 'Google keres&eacute;s', 'ingatlan.com', 'K&ouml;z&ouml;ss&eacute;gi m&eacute;dia', 'Egy&eacute;b');
$times = array('09:00-12:00', '12:00-15:00', '15:00-18:00');
$date = (new DateTimeImmutable('tomorrow', wp_timezone()))->format('Y-m-d');
$checks = 1;
foreach (array('http', 'https') as $scheme) {
    $_SERVER['HTTP_ORIGIN'] = $scheme . '://harmat22.hu';
    $_SERVER['HTTP_REFERER'] = $scheme . '://harmat22.hu/property/a1-f-l1/';
    foreach ($sources as $source) {
        foreach ($times as $time) {
            $request = new WP_REST_Request('POST', '/harmat-sales-manager/v1/offer');
            $request->set_param('lead_source', html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $request->set_param('your-date', $date);
            $request->set_param('your-time', $time);
            $request->set_param('selected-apartment', 'A1-F-L1');
            $request->set_param('selected-url', 'https://harmat22.hu/property/a1-f-l1/');
            $request->set_param('your-name', 'Harmat pure validation fixture');
            if (harmat_offer_integrity_filter(null, array(), $request) !== null
                || harmat_offer_antispam_filter(null, array(), $request) !== null
                || $request->get_param('selected-apartment') !== 'A1-F-L1'
                || $request->get_param('selected-area') !== '') {
                throw new RuntimeException('Normal source/date/time/property validation regression');
            }
            $checks++;
        }
    }
}
foreach (array(
    array('lead_source', 'invalid fixture', 'harmat_offer_integrity_bad_source'),
    array('your-date', '2026-02-30', 'harmat_offer_integrity_bad_date'),
    array('your-time', '03:00-05:00', 'harmat_offer_integrity_bad_time'),
    array('_harmat_offer_nonce', 'invalid fixture', 'harmat_offer_integrity_bad_nonce'),
) as $case) {
    $request = new WP_REST_Request('POST', '/harmat-sales-manager/v1/offer');
    $request->set_param($case[0], $case[1]);
    $response = harmat_offer_integrity_filter(null, array(), $request);
    if (!is_wp_error($response) || $response->get_error_code() !== $case[2]) {
        throw new RuntimeException('Invalid validation boundary no longer enforced');
    }
    $checks++;
}
echo wp_json_encode(array('status' => 'PASS', 'checks' => $checks, 'valid_source_date_time_cases' => 30,
                         'scope' => 'PURE_FILTERS_NO_REST_DISPATCH_NO_SALES_CALLBACK_NO_MAIL_NO_BUSINESS_WRITE'));
