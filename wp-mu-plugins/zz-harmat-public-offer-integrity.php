<?php
/**
 * Plugin Name: Harmat Public Offer Integrity
 * Description: Validates public offer requests and restores property details from WordPress.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_offer_integrity_message($value) {
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function harmat_offer_integrity_request_text(WP_REST_Request $request, $key) {
    $value = $request->get_param($key);
    if (!is_scalar($value)) {
        return '';
    }

    return sanitize_text_field(wp_unslash((string) $value));
}

function harmat_offer_integrity_error($code, $message, $status) {
    return new WP_Error(
        $code,
        harmat_offer_integrity_message($message),
        array('status' => (int) $status)
    );
}

function harmat_offer_integrity_date_is_valid($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
    $errors = DateTimeImmutable::getLastErrors();

    return $date instanceof DateTimeImmutable
        && (!$errors || (!$errors['warning_count'] && !$errors['error_count']))
        && $date->format('Y-m-d') === $value;
}

function harmat_offer_integrity_same_site_evidence(WP_REST_Request $request) {
    $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    if (!$site_host) {
        return array('valid' => true, 'present' => false);
    }

    $values = array();
    foreach (array('HTTP_ORIGIN', 'HTTP_REFERER') as $server_key) {
        $values[] = isset($_SERVER[$server_key])
            ? (string) wp_unslash($_SERVER[$server_key])
            : '';
    }
    $values[] = harmat_offer_integrity_request_text($request, 'source_url');

    $present = false;
    foreach ($values as $value) {
        if ($value === '') {
            continue;
        }

        $host = wp_parse_url($value, PHP_URL_HOST);
        if (!$host || strcasecmp($host, $site_host) !== 0) {
            return array('valid' => false, 'present' => $present);
        }
        $present = true;
    }

    return array('valid' => true, 'present' => $present);
}

function harmat_offer_integrity_property_id_by_title($title) {
    if ($title === '') {
        return 0;
    }

    $query = new WP_Query(array(
        'post_type' => 'property',
        'post_status' => 'publish',
        'title' => $title,
        'posts_per_page' => 2,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'ID',
        'order' => 'ASC',
    ));

    if (count($query->posts) !== 1) {
        return 0;
    }

    return (int) $query->posts[0];
}

function harmat_offer_integrity_property_id(WP_REST_Request $request) {
    $apartment = harmat_offer_integrity_request_text($request, 'selected-apartment');
    $selected_url = harmat_offer_integrity_request_text($request, 'selected-url');
    $title_id = harmat_offer_integrity_property_id_by_title($apartment);
    $url_id = $selected_url !== '' ? (int) url_to_postid($selected_url) : 0;

    if ($url_id && get_post_type($url_id) !== 'property') {
        $url_id = 0;
    }

    if ($title_id && $url_id && $title_id !== $url_id) {
        return -1;
    }

    return $title_id ?: $url_id;
}

function harmat_offer_integrity_filter($response, $handler, $request) {
    if (
        null !== $response
        || !($request instanceof WP_REST_Request)
        || $request->get_route() !== '/harmat-sales-manager/v1/offer'
        || strtoupper($request->get_method()) !== 'POST'
    ) {
        return $response;
    }

    $evidence = harmat_offer_integrity_same_site_evidence($request);
    if (!$evidence['valid']) {
        return harmat_offer_integrity_error(
            'harmat_offer_integrity_bad_origin',
            'A k&uuml;ld&eacute;s biztons&aacute;gi ellen&#337;rz&eacute;se nem siker&uuml;lt.',
            403
        );
    }

    $nonce = harmat_offer_integrity_request_text($request, '_harmat_offer_nonce');
    $nonce_valid = $nonce !== '' && wp_verify_nonce($nonce, 'harmat_public_offer');
    if ($nonce !== '' && !$nonce_valid) {
        return harmat_offer_integrity_error(
            'harmat_offer_integrity_bad_nonce',
            'A munkamenet lej&aacute;rt. K&eacute;rj&uuml;k, friss&iacute;tse az oldalt &eacute;s pr&oacute;b&aacute;lja &uacute;jra.',
            403
        );
    }
    if (!$nonce_valid && !$evidence['present']) {
        return harmat_offer_integrity_error(
            'harmat_offer_integrity_missing_context',
            'A k&uuml;ld&eacute;s biztons&aacute;gi ellen&#337;rz&eacute;se nem siker&uuml;lt.',
            403
        );
    }

    $source = harmat_offer_integrity_request_text($request, 'lead_source');
    $allowed_sources = array(
        '',
        harmat_offer_integrity_message('K&uuml;lt&eacute;ri hirdet&eacute;s'),
        'Google keres&eacute;s',
        'ingatlan.com',
        harmat_offer_integrity_message('K&ouml;z&ouml;ss&eacute;gi m&eacute;dia'),
        harmat_offer_integrity_message('Egy&eacute;b'),
    );
    if (!in_array($source, $allowed_sources, true)) {
        return harmat_offer_integrity_error(
            'harmat_offer_integrity_bad_source',
            'K&eacute;rj&uuml;k, v&aacute;lasszon a megadott forr&aacute;sok k&ouml;z&uuml;l.',
            400
        );
    }

    $time = harmat_offer_integrity_request_text($request, 'your-time');
    if (!in_array($time, array('', '09:00-12:00', '12:00-15:00', '15:00-18:00'), true)) {
        return harmat_offer_integrity_error(
            'harmat_offer_integrity_bad_time',
            'K&eacute;rj&uuml;k, v&aacute;lasszon a megadott id&#337;s&aacute;vok k&ouml;z&uuml;l.',
            400
        );
    }

    $date = harmat_offer_integrity_request_text($request, 'your-date');
    if ($date !== '') {
        if (!harmat_offer_integrity_date_is_valid($date) || $date < wp_date('Y-m-d')) {
            return harmat_offer_integrity_error(
                'harmat_offer_integrity_bad_date',
                'K&eacute;rj&uuml;k, v&aacute;lasszon mai vagy k&eacute;s&#337;bbi d&aacute;tumot.',
                400
            );
        }
    }

    $apartment = harmat_offer_integrity_request_text($request, 'selected-apartment');
    $selected_url = harmat_offer_integrity_request_text($request, 'selected-url');
    $property_id = harmat_offer_integrity_property_id($request);
    if (($apartment !== '' || $selected_url !== '') && $property_id <= 0) {
        return harmat_offer_integrity_error(
            'harmat_offer_integrity_bad_property',
            'A kiv&aacute;lasztott lak&aacute;s nem azonos&iacute;that&oacute;. K&eacute;rj&uuml;k, friss&iacute;tse az oldalt.',
            400
        );
    }

    if ($property_id > 0) {
        $request->set_param('selected-apartment', get_the_title($property_id));
        $request->set_param('selected-url', get_permalink($property_id));
        foreach (array(
            'selected-building',
            'selected-floor',
            'selected-area',
            'selected-rooms',
            'selected-price',
        ) as $key) {
            $request->set_param($key, '');
        }
    }

    return $response;
}
add_filter('rest_request_before_callbacks', 'harmat_offer_integrity_filter', 10, 3);
