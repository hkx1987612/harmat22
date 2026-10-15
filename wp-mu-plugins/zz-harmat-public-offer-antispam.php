<?php
/**
 * Plugin Name: Harmat Public Offer Anti-Spam
 * Description: Rejects explicit URL markers in public offer names only.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_offer_antispam_name_has_url($value) {
    if (!is_scalar($value)) {
        return false;
    }

    $name = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return preg_match('~https?://|www\.~i', $name) === 1;
}

function harmat_offer_antispam_filter($response, $handler, $request) {
    if (null !== $response || !($request instanceof WP_REST_Request)) {
        return $response;
    }

    if (strtoupper($request->get_method()) !== 'POST') {
        return $response;
    }

    $route = strtolower(rtrim($request->get_route(), '/'));
    if (
        $route !== '/harmat-sales-manager/v1/offer'
        && !preg_match('~^/contact-form-7/v1/contact-forms/(1002|8761)/feedback$~D', $route)
    ) {
        return $response;
    }

    if (!harmat_offer_antispam_name_has_url($request->get_param('your-name'))) {
        return $response;
    }

    return new WP_Error(
        'harmat_offer_antispam_invalid_name',
        html_entity_decode(
            'K&eacute;rj&uuml;k, a n&eacute;v mez&#337;ben ne adjon meg webc&iacute;met.',
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ),
        array('status' => 400)
    );
}
add_filter('rest_request_before_callbacks', 'harmat_offer_antispam_filter', 20, 3);

function harmat_offer_antispam_cf7_spam($spam, $submission) {
    if ($spam || !is_object($submission)
        || !is_callable(array($submission, 'get_contact_form'))
        || !is_callable(array($submission, 'get_posted_data'))
    ) {
        return $spam;
    }

    $form = $submission->get_contact_form();
    if (!is_object($form) || !is_callable(array($form, 'id'))
        || !in_array($form->id(), array(1002, 8761, '1002', '8761'), true))
    {
        return $spam;
    }

    $data = $submission->get_posted_data();
    if (!is_array($data) || !isset($data['your-name'])) {
        return $spam;
    }

    return harmat_offer_antispam_name_has_url($data['your-name']) ? true : $spam;
}
add_filter('wpcf7_spam', 'harmat_offer_antispam_cf7_spam', 20, 2);
