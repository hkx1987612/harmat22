<?php
// Local CLI-only tests. No WordPress, network, mail, or database bootstrap.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
define('ABSPATH', __DIR__ . '/');
$hooks = array();
function add_filter($tag, $callback, $priority, $accepted_args) {
    $GLOBALS['hooks'][] = array($tag, $callback, $priority, $accepted_args);
}
class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct($code, $message, $data) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
}
class WP_REST_Request {
    public $method;
    public $route;
    public $params;
    public function __construct($method, $route, $params) {
        $this->method = $method;
        $this->route = $route;
        $this->params = $params;
    }
    public function get_method() { return $this->method; }
    public function get_route() { return $this->route; }
    public function get_param($key) {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }
}
require isset($argv[1]) ? $argv[1] : dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-public-offer-antispam.php';
$checks = 0;
function check($condition, $label) {
    $GLOBALS['checks']++;
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
        exit(1);
    }
}
check($hooks === array(
    array('rest_request_before_callbacks', 'harmat_offer_antispam_filter', 20, 3),
    array('wpcf7_spam', 'harmat_offer_antispam_cf7_spam', 20, 2),
), 'exactly two intended hooks');
$normal = array(
    'Kov&aacute;cs &Aacute;gnes', '&#243;&#337;&#369; &Ouml;d&ouml;n',
    '&#24352;&#20255;', '&#26446;&#23567;&#40857;', 'John Smith', "Anne O'Neill",
    'Mary-Jane Watson', 'www Smith', 'https Smith', 'example.com', '',
    null, false, true, 123, 1.5, array('https://example.com'), new stdClass(),
);
$urls = array(
    'https://www.google.com/share.google', 'HTTP://example.com', 'HtTpS://example.com',
    'WWW.example.com', 'John www.example.com Smith', 'John https://example.com',
    'https&colon;&sol;&sol;example.com', 'http&#58;&#47;&#47;example.com',
    '&#104;ttps://example.com', 'w&#119;w&#46;example.com', 'WwW&period;example.com',
);
foreach ($normal as $name) {
    check(!harmat_offer_antispam_name_has_url(is_string($name) ? html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $name), 'normal scalar/non-scalar policy');
}
foreach ($urls as $name) {
    check(harmat_offer_antispam_name_has_url($name), 'URL marker policy: ' . $name);
}
$routes = array('/harmat-sales-manager/v1/offer', '/contact-form-7/v1/contact-forms/1002/feedback', '/contact-form-7/v1/contact-forms/8761/feedback', '/harmat-sales-manager/v1/offer/', '/HARMAT-SALES-MANAGER/v1/OFFER', '/contact-form-7/v1/contact-forms/1002/feedback/');
foreach ($routes as $route) {
    foreach (array_merge($normal, $urls) as $name) {
        $params = array('your-name' => $name, 'your-message' => 'https://example.com WWW.example.com', 'post_title' => 'https://example.com');
        $request = new WP_REST_Request('POST', $route, $params);
        $result = harmat_offer_antispam_filter(null, array(), $request);
        if (in_array($name, $urls, true)) {
            check($result instanceof WP_Error, 'reject targeted URL name');
            check($result->code === 'harmat_offer_antispam_invalid_name' && $result->data === array('status' => 400), 'validation error code/status');
            check(bin2hex($result->message) === '4bc3a9726ac3bc6b2c2061206ec3a976206d657ac59162656e206e652061646a6f6e206d65672077656263c3ad6d65742e', 'exact accented Hungarian message');
        } else {
            check($result === null, 'allow normal name and message-only URL');
        }
        check($request->params === $params, 'request not mutated');
    }
    foreach (array('GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD') as $method) {
        check(harmat_offer_antispam_filter(null, null, new WP_REST_Request($method, $route, array('your-name' => $urls[0]))) === null, 'unrelated method');
    }
}
foreach (array('/harmat-sales-manager/v1/offers', '/contact-form-7/v1/contact-forms/1003/feedback', '/contact-form-7/v1/contact-forms/11002/feedback', '/contact-form-7/v1/contact-forms/8761/feedback/extra', '/contact-form-7/v1/contact-forms/1002/feedback' . "\n", '/other') as $route) {
    check(harmat_offer_antispam_filter(null, null, new WP_REST_Request('POST', $route, array('your-name' => $urls[0]))) === null, 'unrelated route/form');
}
foreach (array(false, 0, '', array(), new stdClass(), new WP_Error('existing', 'existing', array('status' => 403))) as $response) {
    check(harmat_offer_antispam_filter($response, null, new WP_REST_Request('POST', $routes[0], array('your-name' => $urls[0]))) === $response, 'strict preexisting response passthrough');
    check(harmat_offer_antispam_filter($response, null, null) === $response, 'passthrough before request access');
}
check(harmat_offer_antispam_filter(null, null, new stdClass()) === null, 'non-REST request passthrough');
check(harmat_offer_antispam_filter(null, null, new WP_REST_Request('POST', $routes[0], array('your-message' => $urls[0]))) === null, 'missing name with message URL');
class AntispamTestForm {
    private $value;
    public function __construct($value) { $this->value = $value; }
    public function id() { return $this->value; }
}
class AntispamTestSubmission {
    private $form;
    private $data;
    public function __construct($form, $data) { $this->form = $form; $this->data = $data; }
    public function get_contact_form() { return $this->form; }
    public function get_posted_data() { return $this->data; }
}
foreach (array(1002, 8761, '1002', '8761') as $id) {
    foreach (array_merge($normal, $urls) as $name) {
        $submission = new AntispamTestSubmission(new AntispamTestForm($id), array('your-name' => $name, 'your-message' => $urls[0]));
        check(harmat_offer_antispam_cf7_spam(false, $submission) === in_array($name, $urls, true), 'CF7 name policy');
    }
    foreach (array(null, false, 'invalid', new stdClass(), array(), array('your-message' => $urls[0]), array('your-name' => array($urls[0]))) as $data) {
        check(harmat_offer_antispam_cf7_spam(false, new AntispamTestSubmission(new AntispamTestForm($id), $data)) === false, 'CF7 missing/malformed name data');
    }
}
foreach (array(null, new stdClass(), new AntispamTestForm(1002)) as $submission) {
    check(harmat_offer_antispam_cf7_spam(false, $submission) === false, 'CF7 missing submission methods');
}
foreach (array(null, new stdClass(), new AntispamTestForm(1003), new AntispamTestForm('01002'), new AntispamTestForm(1002.0)) as $form) {
    check(harmat_offer_antispam_cf7_spam(false, new AntispamTestSubmission($form, array('your-name' => $urls[0]))) === false, 'CF7 unrelated/malformed form');
}
foreach (array(true, 1, 'already-spam', array('reason')) as $spam) {
    check(harmat_offer_antispam_cf7_spam($spam, null) === $spam, 'CF7 existing spam preserved before method access');
}
foreach (array(false, null, 0, '') as $spam) {
    check(harmat_offer_antispam_cf7_spam($spam, new AntispamTestSubmission(new AntispamTestForm(1002), array('your-name' => 'John Smith'))) === $spam, 'CF7 unchanged non-spam value');
}
check(count($hooks) === 2, 'no extra runtime hooks');
echo 'PASS: ' . $checks . ' checks; no WordPress or external services loaded.' . PHP_EOL;
