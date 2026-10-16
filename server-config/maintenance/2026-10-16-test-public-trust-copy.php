<?php
// Isolated fixtures: no WordPress boot, database, network or filesystem writes.
define('ABSPATH', __DIR__);
$candidate = realpath($argv[1] ?? dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-public-trust-copy.php');
if ($candidate === false || !is_file($candidate)) {
    throw new RuntimeException('Candidate file not found.');
}
$blocked = $argv[2] ?? '';
if (in_array($blocked, array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST'), true)) {
    define($blocked, true);
}
$filters = array();
$flags = array();
$queried = 164;
$assertions = 0;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = array();
set_error_handler(function ($level, $message, $file, $line) {
    throw new ErrorException($message, 0, $level, $file, $line);
});
function add_filter($name, $callback, $priority = 10, $accepted = 1) {
    $GLOBALS['filters'][$name][] = array($callback, $priority, $accepted);
}
function is_admin() { return !empty($GLOBALS['flags']['admin']); }
function wp_doing_ajax() { return !empty($GLOBALS['flags']['ajax']); }
function wp_doing_cron() { return !empty($GLOBALS['flags']['cron']); }
function is_feed() { return !empty($GLOBALS['flags']['feed']); }
function is_preview() { return !empty($GLOBALS['flags']['preview']); }
function is_404() { return !empty($GLOBALS['flags']['404']); }
function is_search() { return !empty($GLOBALS['flags']['search']); }
function is_embed() { return !empty($GLOBALS['flags']['embed']); }
function is_trackback() { return !empty($GLOBALS['flags']['trackback']); }
function wp_is_json_request() { return !empty($GLOBALS['flags']['json']); }
function is_page($id) { return empty($GLOBALS['flags']['not_page']) && $GLOBALS['queried'] === $id; }
function is_front_page() { return empty($GLOBALS['flags']['not_front']); }
function get_queried_object_id() { return $GLOBALS['queried']; }
function get_post_status($id) { return $GLOBALS['flags']['status'] ?? 'publish'; }
function get_post_field($field, $id) { return $GLOBALS['flags']['password'] ?? ''; }
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $GLOBALS['assertions']++;
}
function native_gallery_content($content) {
    return call_user_func($GLOBALS['filters']['elementor/frontend/the_content'][0][0], $content);
}
function widget($id = 'ab01017', $name = 'opal-revslider') {
    return new class($id, $name) {
        public function __construct(private $id, private $name) {}
        public function get_id() { return $this->id; }
        public function get_name() { return $this->name; }
    };
}
ob_start();
require $candidate;
check(ob_get_clean() === '', 'plugin inclusion has no output');

$old_title = "Csal\u{00e1}dbar\u{00e1}t otthon \u{2013} CSOK ig\u{00e9}nyelhet\u{0151}";
$new_title = "Csal\u{00e1}dbar\u{00e1}t otthonok";
$old_a = "CSOK - Okos beruh\u{00e1}z\u{00e1}s";
$old_b = "CSOK-bar\u{00e1}t lak\u{00e1}sokat k\u{00ed}n\u{00e1}l";
$new_a = "\u{00c1}tgondolt otthonteremt\u{00e9}s";
$new_b = "csal\u{00e1}dbar\u{00e1}t otthonokat k\u{00ed}n\u{00e1}l";
$target = array('id' => 'df8c809', 'elType' => 'widget', 'widgetType' => 'icon-box',
    'settings' => array('title_text' => $old_title, 'title' => $old_title,
        'description_text' => $old_title, 'link' => array('url' => '/lakaskereso/?a=1&b=2'),
        'style' => 'visibility:hidden', 'null' => null, 'false' => false, 'number' => 123.45),
    'elements' => array(), 'unrelated' => array('id' => 'df8c809', 'title_text' => $old_title));
$other = $target; $other['id'] = 'other';
$data = array(array('id' => 'section', 'elType' => 'section', 'settings' => array('raw' => "\0bytes\r\n"),
    'elements' => array(array('id' => 'column', 'elType' => 'column',
        'elements' => array($target, $other)), $other)));
$expected = $data;
$expected[0]['elements'][0]['elements'][0]['settings']['title_text'] = $new_title;
$slider = '<div class="rev_slider" style="color:red"><a href="/lakaskereso/?x=1&amp;y=2">'
    . $old_a . '</a><span>' . $old_b . '</span><b>Harmatliget</b></div>';
$slider_expected = '<div class="rev_slider" style="color:red"><a href="/lakaskereso/?x=1&amp;y=2">'
    . $new_a . '</a><span>' . $new_b . '</span><b>Harmatliget</b></div>';
$gallery = '<section class="elementor-element elementor-element-4b8fe87 elementor-widget-opal-image-gallery">'
    . '<ul class="elementor-galerry__filters"><li data-filter=".__all">All</li></ul>'
    . '<div class="row grid"><a href="/full.jpg" data-elementor-open-lightbox="yes">'
    . '<img src="/thumb.jpg" srcset="/full.jpg 1280w" alt="photo"></a></div></section>';

function check_blocked($data, $slider, $gallery, $label) {
    $GLOBALS['queried'] = 164;
    check(harmat_trust_builder_data($data, 164) === $data, $label . ' title blocked');
    check(harmat_trust_widget_content($slider, widget()) === $slider, $label . ' slider blocked');
    $GLOBALS['queried'] = 439;
    check(native_gallery_content($gallery) === $gallery, $label . ' gallery blocked');
    $GLOBALS['queried'] = 164;
}

if ($blocked !== '') {
    ob_start();
    check_blocked($data, $slider, $gallery, $blocked);
    check(ob_get_clean() === '', 'blocked callbacks have no output');
    echo 'PASS ' . $assertions . " assertions\n";
    exit;
}

ob_start();
check(array_keys($filters) === array('elementor/frontend/builder_content_data',
    'elementor/widget/render_content', 'elementor/frontend/the_content'), 'only three approved native hooks');
check(!array_key_exists('elementor/frontend/builder_content', $filters), 'nonexistent old gallery hook not registered');
foreach ($filters as $name => $entries) {
    $accepted = $name === 'elementor/frontend/the_content' ? 1 : 2;
    check(count($entries) === 1 && $entries[0][1] === 20 && $entries[0][2] === $accepted
        && is_callable($entries[0][0]), $name . ' hook contract');
}
$gallery_callback = new ReflectionFunction($filters['elementor/frontend/the_content'][0][0]);
check($gallery_callback->getNumberOfParameters() === 1
    && $gallery_callback->getNumberOfRequiredParameters() === 1, 'native gallery callback takes exactly one argument');
$snapshot = serialize($data);
check(harmat_trust_title_data($data) === $expected, 'exact title only; all unrelated values preserved');
check(serialize($data) === $snapshot, 'input remains byte/value identical');
$shared_title = $old_title;
$shared_node = $target;
$shared_node['settings']['title_text'] =& $shared_title;
$shared_settings =& $shared_node['settings'];
$referenced = array(&$shared_node);
$referenced_expected = array($target);
$referenced_expected[0]['settings']['title_text'] = $new_title;
check(harmat_trust_title_data($referenced) === $referenced_expected, 'referenced input produces correct new value');
check($shared_title === $old_title && $shared_settings === $target['settings']
    && $shared_node === $target, 'referenced input title/settings/node never mutated');
check(harmat_trust_title_data(array(&$shared_node, null)) === array($target, null)
    && $shared_title === $old_title, 'malformed referenced input fully unchanged');
check(harmat_trust_title_data($expected) === $expected, 'title idempotent');
check(harmat_trust_title_data(array($other)) === array($other), 'no match unchanged');
$shared_other = array(&$other, &$other);
check(serialize(harmat_trust_title_data($shared_other)) === serialize($shared_other), 'no match returns original reference structure');
check(harmat_trust_title_data(array($target, $target)) === array($target, $target), 'duplicate target unchanged');
$wrong = $target; $wrong['widgetType'] = 'heading';
check(harmat_trust_title_data(array($target, $wrong)) === array($target, $wrong), 'duplicate id wrong type unchanged');
foreach (array('id' => 'DF8C809', 'elType' => 'container', 'widgetType' => 'heading', 'settings' => null) as $key => $value) {
    $wrong = $target; $wrong[$key] = $value;
    check(harmat_trust_title_data(array($wrong)) === array($wrong), 'wrong target ' . $key);
    unset($wrong[$key]);
    check(harmat_trust_title_data(array($wrong)) === array($wrong), 'missing target ' . $key);
}
foreach (array(null, false, 123, array($old_title), $new_title, $old_title . ' ', str_replace("\u{2013}", '-', $old_title)) as $title) {
    $wrong = $target; $wrong['settings']['title_text'] = $title;
    check(harmat_trust_title_data(array($wrong)) === array($wrong), 'non-exact title unchanged');
}
$wrong = $target; unset($wrong['settings']['title_text']);
check(harmat_trust_title_data(array($wrong)) === array($wrong), 'other title fields never substituted');
foreach (array(null, false, '', 123, new stdClass(), array('nodes' => array($target)),
    array($target, null), array($target, array()), array($target, array('id' => 'bad', 'elType' => 'section', 'elements' => null))) as $malformed) {
    check(harmat_trust_title_data($malformed) === $malformed, 'malformed data unchanged, including earlier match');
}
$deep = array($target);
for ($i = 0; $i < 66; $i++) { $deep = array(array('id' => 'nest', 'elType' => 'section', 'elements' => $deep)); }
check(harmat_trust_title_data($deep) === $deep, 'excess depth unchanged');

check(harmat_trust_slider_copy($slider) === $slider_expected, 'slider exact bytes except approved literal text');
check(harmat_trust_slider_copy($slider_expected) === $slider_expected, 'slider idempotent');
check(harmat_trust_slider_copy('<p>' . $old_a . '</p>') === '<p>' . $new_a . '</p>', 'first literal independent');
check(harmat_trust_slider_copy('<p>' . $old_b . '</p>') === '<p>' . $new_b . '</p>', 'second literal independent');
foreach (array($slider . $old_a, $slider . $old_b, $old_a . $old_a, $old_b . $old_b,
    $slider . 'CSOK unknown', '<p>CSOK other</p>', str_replace('CSOK', 'csok', $slider),
    '', false, null, 123, array($slider), new stdClass()) as $unchanged) {
    check(harmat_trust_slider_copy($unchanged) === $unchanged, 'duplicate/unknown/malformed slider unchanged');
}
foreach (array(widget('other'), widget('ab01017', 'icon-box'), widget('AB01017'),
    null, false, array(), new stdClass(), new class { public function get_id() { return 'ab01017'; } }) as $wrong_widget) {
    check(harmat_trust_widget_content($slider, $wrong_widget) === $slider, 'non-target widget unchanged');
}

$queried = 439;
$notice = harmat_trust_gallery_notice($gallery);
check(native_gallery_content($gallery) === $notice, 'registered native one-argument gallery callback adds notice');
$notice_text = "A l\u{00e1}tv\u{00e1}nytervek t\u{00e1}j\u{00e9}koztat\u{00f3} illusztr\u{00e1}ci\u{00f3}k; a megval\u{00f3}sult \u{00e1}llapot elt\u{00e9}rhet a bemutatott k\u{00e9}pekt\u{0151}l.";
check(str_ends_with($notice, $gallery), 'all gallery bytes, filters, lightbox and images preserved');
$prefix = substr($notice, 0, -strlen($gallery));
check(substr_count($prefix, '<p ') === 1 && substr_count($prefix, '</p>') === 1
    && strip_tags($prefix) === $notice_text, 'one concise paragraph with exact UTF8 notice');
check(preg_match('//u', $notice) === 1 && !str_contains($prefix, '&#'), 'literal UTF8, no encoded text');
foreach (array('class="harmat-gallery-render-notice"', 'box-sizing:border-box', 'width:100%',
    'max-width:1240px', 'padding:0 12px', 'font-size:14px', 'line-height:1.6', 'letter-spacing:0') as $style) {
    check(str_contains($prefix, $style), 'notice scope/style ' . $style);
}
check(harmat_trust_gallery_notice($notice) === $notice, 'notice idempotent');
check(native_gallery_content($notice) === $notice, 'registered native gallery callback idempotent');
foreach (array('', null, false, 123, array($gallery), new stdClass(), '<p>other</p>',
    $slider, '<div class="elementor-element-other elementor-widget-heading">Other</div>',
    str_replace('4b8fe87', '4b8fe870', $gallery), str_replace('4b8fe87', 'other', $gallery),
    '<p class="harmat-gallery-render-notice">existing</p>' . $gallery) as $unchanged) {
    check(harmat_trust_gallery_notice($unchanged) === $unchanged, 'missing/wrong marker or malformed gallery unchanged');
    check(native_gallery_content($unchanged) === $unchanged, 'native callback rejects unrelated widgets/missing markers/malformed content');
}
$queried = 164;

foreach (array('GET', 'HEAD') as $method) {
    $_SERVER['REQUEST_METHOD'] = $method;
    check(harmat_trust_builder_data($data, 164) === $expected, $method . ' home data native gate');
    check(harmat_trust_widget_content($slider, widget()) === $slider_expected, $method . ' slider native gate');
    $queried = 439;
    check(native_gallery_content($gallery) === $notice, $method . ' gallery native one-argument gate');
    $queried = 164;
}
foreach (array('', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'get') as $method) {
    $_SERVER['REQUEST_METHOD'] = $method;
    check_blocked($data, $slider, $gallery, 'method ' . $method);
}
unset($_SERVER['REQUEST_METHOD']);
check_blocked($data, $slider, $gallery, 'missing method');
$_SERVER['REQUEST_METHOD'] = 'GET';
foreach (array('admin', 'ajax', 'cron', 'feed', 'preview', '404', 'search', 'embed', 'trackback', 'json', 'not_page') as $flag) {
    $flags[$flag] = true;
    check_blocked($data, $slider, $gallery, $flag);
    $flags = array();
}
foreach (array('draft', 'private', 'trash', null, false) as $status) {
    $flags['status'] = $status === null ? '' : $status;
    check_blocked($data, $slider, $gallery, 'not published');
}
$flags = array('password' => 'protected');
check_blocked($data, $slider, $gallery, 'password protected');
$flags = array();
$_GET['elementor-preview'] = '164';
check_blocked($data, $slider, $gallery, 'Elementor preview');
$_GET = array();
$flags = array('not_front' => true);
check(harmat_trust_builder_data($data, 164) === $data, 'non-home front context blocked');
check(harmat_trust_widget_content($slider, widget()) === $slider, 'non-home slider context blocked');
$flags = array();
foreach (array(0, 439, 999, '164', null) as $wrong_query) {
    $queried = $wrong_query;
    check(harmat_trust_builder_data($data, 164) === $data, 'wrong queried home id');
    check(harmat_trust_widget_content($slider, widget()) === $slider, 'wrong queried slider id');
}
$queried = 164;
foreach (array(0, 439, 999, null, true, 164.0, '0164') as $wrong_post) {
    check(harmat_trust_builder_data($data, $wrong_post) === $data, 'wrong builder home document');
}
check(harmat_trust_builder_data($data, '164') === $expected, 'canonical string document id accepted');
foreach (array(0, 164, 999, '439', null) as $wrong_query) {
    $queried = $wrong_query;
    check(native_gallery_content($gallery) === $gallery, 'native callback leaves unrelated queried page unchanged despite gallery marker');
}
check(ob_get_clean() === '', 'all helpers and native callbacks have no output leakage');

// Constants cannot be unset, so exercise native exclusions in fresh local PHP processes.
foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $constant) {
    $process = proc_open(array(PHP_BINARY, '-n', __FILE__, $candidate, $constant),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    check(is_resource($process), $constant . ' isolated process');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    check(proc_close($process) === 0 && $stderr === '' && $stdout === "PASS 5 assertions\n",
        $constant . ' actual native gate, no unexpected output');
    $assertions += 5;
}
echo 'PASS ' . $assertions . " assertions (including five isolated constant-gate processes)\n";
