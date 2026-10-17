<?php
/** Offline fixture: php this-file.php /absolute/path/to/zz-harmat-contact-family.php */
namespace HarmatContactFamilyFixture;

if (PHP_SAPI !== 'cli') { exit(1); }
$candidate = $argv[1] ?? '';
if (!is_file($candidate) || !\is_readable($candidate)) {
    fwrite(STDERR, "Supply the candidate plugin path as argv[1].\n");
    exit(1);
}
\define('ABSPATH', __DIR__ . '/');
$source = file_get_contents($candidate);
$tests = 0;
$state = array();
$hooks = array();
$styles = array();

function check($condition, $label) {
    global $tests;
    $tests++;
    if (!$condition) { throw new \RuntimeException('FAIL: ' . $label); }
}
function reset_state() {
    global $state, $styles;
    $state = array('queried' => 26, 'page' => true, 'post' => 26,
        'status' => 'publish', 'slug' => 'elerhetosegeink', 'password' => '',
        'raw' => str_repeat('R', 1862), 'main' => true, 'loop' => true,
        'owner' => 1001, 'flags' => array(), 'asset_unreadable' => '',
        'asset_corrupt' => '', 'asset_missing' => '', 'asset_link' => '');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = array();
    $styles = array();
}
function defined($name) {
    global $state;
    return array_key_exists($name, $state['flags']) || \defined($name);
}
function constant($name) {
    global $state;
    return $state['flags'][$name] ?? \constant($name);
}
function function_exists($name) {
    return \function_exists(__NAMESPACE__ . '\\' . $name) || \function_exists($name);
}
// This is an isolated synthetic raw preimage, NOT the stored WordPress content
// and NOT native-WP proof. Real sibling asset bytes always use genuine SHA256.
function hash($algorithm, $data, $binary = false) {
    if ($algorithm === 'sha256' && $data === str_repeat('R', 1862) && !$binary) {
        return '4dc84616c2e8e0ffed6766f041e8ad8485cd7a57c7b27ad0724116a5eaacfc0d';
    }
    return \hash($algorithm, $data, $binary);
}
function is_file($path) {
    global $state;
    return basename($path) !== ($state['asset_missing'] ?? '') && \is_file($path);
}
function is_readable($path) {
    global $state;
    return basename($path) !== $state['asset_unreadable'] && \is_readable($path);
}
function is_link($path) {
    global $state;
    return basename($path) === $state['asset_link'] || \is_link($path);
}
function hash_file($algorithm, $path) {
    global $state;
    return basename($path) === $state['asset_corrupt'] ? str_repeat('f', 64) : \hash_file($algorithm, $path);
}
function is_admin() { global $state; return $state['admin'] ?? false; }
function wp_doing_ajax() { global $state; return $state['ajax'] ?? false; }
function wp_doing_cron() { global $state; return $state['cron'] ?? false; }
function is_feed() { global $state; return $state['feed'] ?? false; }
function is_preview() { global $state; return $state['preview'] ?? false; }
function is_404() { global $state; return $state['404'] ?? false; }
function is_search() { global $state; return $state['search'] ?? false; }
function is_embed() { global $state; return $state['embed'] ?? false; }
function is_trackback() { global $state; return $state['trackback'] ?? false; }
function wp_is_json_request() { global $state; return $state['json'] ?? false; }
function get_queried_object_id() { global $state; return $state['queried']; }
function is_page($id) { global $state; return $id === 26 && $state['page']; }
function get_post_status($id) { global $state; return $id === 26 ? $state['status'] : false; }
function get_post_field($field, $id, $context = 'display') {
    global $state;
    check($id === 26 && $context === 'raw', 'only raw fields of exact Contact post requested');
    $keys = array('post_name' => 'slug', 'post_password' => 'password', 'post_content' => 'raw');
    return $state[$keys[$field]];
}
function has_filter($tag, $callback) {
    global $state;
    check($tag === 'the_content' && $callback === 'harmat_perf_contact_scene_content', 'only known Contact owner queried');
    return $state['owner'];
}
function is_main_query() { global $state; return $state['main']; }
function in_the_loop() { global $state; return $state['loop']; }
function get_the_ID() { global $state; return $state['post']; }
function content_url($path) { return 'https://fixture.invalid/wp-content' . $path; }
function esc_url($url) { return htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); }
function wp_enqueue_style(...$args) { global $styles; $styles[] = $args; }
function add_filter(...$args) { global $hooks; $hooks[] = array('filter', $args); }
function add_action(...$args) { global $hooks; $hooks[] = array('action', $args); }

reset_state();
// Evaluate the unchanged source in a stub namespace, preserving include paths.
$tokens = token_get_all($source);
check($tokens[0][0] === T_OPEN_TAG, 'candidate PHP opening tag');
$code = '';
foreach ($tokens as $index => $token) {
    if ($index === 0) { continue; }
    if (is_array($token)) {
        $code .= $token[0] === T_DIR ? var_export(dirname(realpath($candidate)), true)
            : ($token[0] === T_FILE ? var_export(realpath($candidate), true) : $token[1]);
    } else { $code .= $token; }
}
eval('namespace ' . __NAMESPACE__ . ';' . $code);
check(count($hooks) === 3, 'only three presentation hooks');
check($hooks === array(
    array('filter', array('the_content', 'harmat_contact_family_content', 1002, 1)),
    array('action', array('wp_enqueue_scripts', 'harmat_contact_family_enqueue', 99)),
    array('action', array('template_redirect', 'harmat_contact_family_buffer', 99)),
), 'exact presentation hooks, after native scene owner');
check(strpos($source, "hash('sha256', \$raw) === '4dc84616c2e8e0ffed6766f041e8ad8485cd7a57c7b27ad0724116a5eaacfc0d'") !== false,
    'production pins actual raw SHA256 without fixture bypass');
check(harmat_contact_family_context(), 'public GET context');
check(harmat_contact_family_assets(), 'all real sibling asset hashes; parent must finalize body/CSS pins first');
$old = '<section class="harmat-contact-scene" aria-labelledby="harmat-contact-scene-title"><h2>Existing public Contact</h2></section>';
$body = harmat_contact_family_content($old);
$root = '<div id="harmat-contact-family" class="harmat-contact-family">';
$hero = '<h2 id="hcf-title" class="hcf-title">Harmat Lak' . "\u{00f3}" . 'park</h2>';
check($body !== $old, 'approved body replaces native scene');
check(substr_count($body, $root) === 1, 'exact single owned root');
check(substr_count($body, $hero) === 1, 'exact default H2 hero contract');
check(substr_count($body, '<img ') === 4, 'exactly four approved images');
check(strpos($body, 'src="assets/') === false, 'no relative asset placeholders');
foreach (array('project-garden.jpg', 'showroom-model.jpg', 'showroom-lounge.jpg', 'project-location.jpg') as $name) {
    check(substr_count($body, 'src="https://fixture.invalid/wp-content/mu-plugins/harmat-contact-family/' . $name . '"') === 1,
        'absolute approved image ' . $name);
}
foreach (array('tel:+36300733375', 'mailto:ertekesites@harmat22.hu', '#opal-contactform-popup',
    '/epitesi-naplo/') as $fact) {
    check(strpos($body, $fact) !== false, 'approved native contact/navigation ' . $fact);
}
check(!preg_match('~<(?:form|script|header|footer|iframe)\b~i', $body), 'no copied shell/form/script/iframe');
check(!preg_match('~<(?:input|textarea|select)\b~i', $body), 'no independent form fields');
check(strpos($body, 'harmat-contact-scene') === false, 'old scene not embedded in new body');
check(harmat_contact_family_content($body) === $body, 'content idempotence');
foreach (array(null, false, 123, array('value'), new \stdClass()) as $value) {
    check(harmat_contact_family_content($value) === $value, 'nonstring content unchanged');
    check(harmat_contact_family_output($value) === $value, 'nonstring output unchanged');
}
foreach (array('', '<p>unrecognized</p>', $old . $old,
    '<section class="harmat-contact-scene extra" aria-labelledby="harmat-contact-scene-title">x</section>',
    '<section class="harmat-contact-scene">x</section>',
    '<section aria-labelledby="harmat-contact-scene-title" class="harmat-contact-scene">x</section>',
    $old . '<div class="harmat-contact-scene"></div>',
    $old . '<div id="harmat-contact-family"></div>',
    '[contact-form-7 id="694" title="Legacy form"]') as $unknown) {
    check(harmat_contact_family_content($unknown) === $unknown, 'unknown/duplicate/legacy content unchanged');
}
$title = '<h1 class="page-title typo-heading">El' . "\u{00e9}" . 'rhet' . "\u{0151}" . 's' . "\u{00e9}" . 'geink</h1>';
$prefix = '<!doctype html><html><head><title>Native SEO</title><link rel="canonical" href="https://harmat22.hu/elerhetosegeink/"></head><body class="page-id-26"><header><a href="#opal-contactform-popup">Native quote</a></header><div id="page-title-bar"><div>' . $title . '</div></div><main>';
$suffix = '</main><footer><form id="h22-offer-form" action="/native-appointment"><input name="native"></form><script>nativeCookie();</script></footer></body></html>';
$page = $prefix . $body . $suffix;
$paired = harmat_contact_family_output($page);
check($paired !== $page, 'paired output applied');
check(substr_count($paired, '<h1 ') === 1 && strpos($paired, '<h1 id="hcf-title"') !== false, 'sole H1 is owned hero');
$demoted = str_replace(array('<h1 ', '</h1>'), array('<h2 ', '</h2>'), $title);
$promoted = str_replace(array('<h2 ', '</h2>'), array('<h1 ', '</h1>'), $hero);
$hide = '<style id="harmat-contact-family-title">body.page-id-26 #page-title-bar{display:none!important;}</style>';
check(strpos($paired, $demoted) !== false, 'native title demoted');
check(substr_count($paired, $hide) === 1, 'single exact page-only native-bar hiding style');
$restored = str_replace($hide, '', $paired);
$restored = str_replace($promoted, $hero, $restored);
$restored = str_replace($demoted, $title, $restored);
check($restored === $page, 'all other bytes including form, SEO, header, footer and cookies preserved');
check(harmat_contact_family_output($paired) === $paired, 'output idempotence');
$entity_page = str_replace('El' . "\u{00e9}" . 'rhet' . "\u{0151}" . 's' . "\u{00e9}" . 'geink', 'El&#233;rhet&#337;s&#233;geink', $page);
check(harmat_contact_family_output($entity_page) !== $entity_page, 'decoded native title accepted');
$extra_h2 = str_replace('</main>', '<h2>Native extra heading</h2></main>', $page);
check(strpos(harmat_contact_family_output($extra_h2), '<h2>Native extra heading</h2>') !== false, 'unrelated H2 preserved');
$fallbacks = array(
    str_replace($title, '<h1 class="page-title">Contact</h1>', $page),
    str_replace($title, str_replace('geink', 'unknown', $title), $page),
    str_replace('</h1>', '</h2>', $page),
    str_replace($hero, str_replace('Harmat', 'Unknown', $hero), $page),
    str_replace($hero, '', $page),
    $page . $body,
    $page . '<div id=\'harmat-contact-family\'></div>',
    $page . '<div id=\'hcf-title\'></div>',
    $page . '<div id=\'page-title-bar\'></div>',
    $page . '<h1>Another H1</h1>',
    $page . $title,
    str_replace('</head>', '', $page),
    str_replace('</head>', '</head></head>', $page),
    str_replace('id="page-title-bar"', 'id="unknown-title"', $page),
    str_replace($title, '', $prefix) . $title . $body . $suffix,
    str_replace($hero, '', $page) . $hero,
    str_replace('</head>', '<style id="harmat-contact-family-title"></style></head>', $page),
    str_replace($root, str_replace('class="harmat-contact-family"', 'class="changed"', $root), $page),
    str_replace($root, str_replace('id="harmat-contact-family"', 'id="changed"', $root), $page),
    str_replace('<div id="page-title-bar"><div>' . $title . '</div></div>', $title, $page),
    str_replace('<div id="page-title-bar"><div>' . $title . '</div></div>', '<div id="page-title-bar">' . $title, $page),
    str_replace('</head>', '</head><h1>', $page),
    str_replace('</head>', '</head></h1>', $page),
);
foreach ($fallbacks as $index => $fallback) {
    check(harmat_contact_family_output($fallback) === $fallback, 'atomic fallback on malformed/duplicate output ' . $index);
}
harmat_contact_family_enqueue();
$css_hash = \hash_file('sha256', dirname($candidate) . '/harmat-contact-family/contact-family.css');
check($styles === array(array('harmat-contact-family',
    'https://fixture.invalid/wp-content/mu-plugins/harmat-contact-family/contact-family.css', array(), substr($css_hash, 0, 16), 'all')),
    'owned stylesheet version follows actual final CSS digest');
// Namespaced evaluation only changes callable lookup; this alias allows the
// actual ob_start hook to be exercised without WordPress or an HTTP request.
eval('namespace { function harmat_contact_family_output($html) { return \\HarmatContactFamilyFixture\\harmat_contact_family_output($html); } }');
$level = ob_get_level();
harmat_contact_family_buffer();
check(ob_get_level() === $level + 1, 'target starts one whole-document buffer');
echo $page;
$buffered = ob_get_contents();
ob_end_clean();
check($buffered === $page, 'buffer retains complete document before flush');
ob_start();
harmat_contact_family_buffer();
echo $page;
ob_end_flush();
$flushed = ob_get_clean();
check($flushed === $paired, 'registered buffer transforms complete document');

$rejects = array(
    array('queried', 2686), array('queried', '26'), array('page', false),
    array('status', 'draft'), array('status', 'private'), array('status', false),
    array('slug', 'other'), array('password', 'secret'),
    array('raw', str_repeat('X', 1862)), array('raw', str_repeat('R', 1861)),
    array('raw', str_repeat('R', 1863)), array('raw', null), array('raw', false),
    array('owner', false), array('owner', 0), array('owner', 1000), array('owner', 1002), array('owner', '1001'),
);
foreach (array('admin', 'ajax', 'cron', 'feed', 'preview', '404', 'search', 'embed', 'trackback', 'json') as $flag) {
    $rejects[] = array($flag, true);
}
foreach ($rejects as $case) {
    reset_state();
    $state[$case[0]] = $case[1];
    check(!harmat_contact_family_context(), 'context rejects ' . $case[0] . ':' . var_export($case[1], true));
    check(harmat_contact_family_content($old) === $old, 'current public scene retained byte-identically');
    check(harmat_contact_family_output($page) === $page, 'rejected full document unchanged');
    harmat_contact_family_enqueue();
    check($styles === array(), 'rejected context no CSS');
    $level = ob_get_level();
    harmat_contact_family_buffer();
    check(ob_get_level() === $level, 'rejected context no buffer');
}
foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
    reset_state();
    $state['flags'][$flag] = true;
    check(!harmat_contact_family_context(), 'constant blocks ' . $flag);
    check(harmat_contact_family_content($old) === $old && harmat_contact_family_output($page) === $page, 'constant byte identity');
    $state['flags'][$flag] = false;
    check(harmat_contact_family_context(), 'false flag permits ' . $flag);
}
foreach (array('POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', '', 'get', null) as $method) {
    reset_state();
    $_SERVER['REQUEST_METHOD'] = $method;
    check(!harmat_contact_family_context(), 'reject method ' . var_export($method, true));
    check(harmat_contact_family_content($old) === $old && harmat_contact_family_output($page) === $page, 'method byte identity');
}
reset_state();
$_SERVER['REQUEST_METHOD'] = 'HEAD';
check(harmat_contact_family_context(), 'HEAD accepted');
check(harmat_contact_family_content($old) === $body, 'HEAD same approved presentation');
reset_state();
$_GET['elementor-preview'] = '';
check(!harmat_contact_family_context(), 'empty Elementor preview rejected');
check(harmat_contact_family_content($old) === $old && harmat_contact_family_output($page) === $page, 'Elementor byte identity');
foreach (array(array('main', false), array('loop', false), array('post', 2686), array('post', '26')) as $case) {
    reset_state();
    $state[$case[0]] = $case[1];
    check(harmat_contact_family_content($old) === $old, 'wrong loop/post content unchanged');
}
foreach (array('body.php', 'contact-family.css', 'project-garden.jpg', 'showroom-model.jpg', 'showroom-lounge.jpg', 'project-location.jpg') as $asset) {
    foreach (array('asset_unreadable', 'asset_corrupt', 'asset_missing', 'asset_link') as $failure) {
        reset_state();
        $state[$failure] = $asset;
        check(!harmat_contact_family_assets(), 'reject ' . $failure . ' ' . $asset);
        check(harmat_contact_family_content($old) === $old && harmat_contact_family_output($page) === $page, 'asset failure leaves current scene and shell unchanged');
        harmat_contact_family_enqueue();
        check($styles === array(), 'asset failure no CSS');
        $level = ob_get_level();
        harmat_contact_family_buffer();
        check(ob_get_level() === $level, 'asset failure no buffer');
        $state[$failure] = '';
        check(harmat_contact_family_assets(), 'asset recovery observed without cached failure');
    }
}
reset_state();
foreach (array(2686, 164, 439, 1, 0, 99999) as $id) {
    $state['queried'] = $id;
    check(harmat_contact_family_content($old) === $old && harmat_contact_family_output($page) === $page, 'non-target byte identity ' . $id);
}
reset_state();
check(harmat_contact_family_context() && harmat_contact_family_assets(), 'successful guards initially');
$state['owner'] = false;
check(!harmat_contact_family_context() && harmat_contact_family_content($old) === $old, 'owner drift observed after success, no static context cache');
$state['owner'] = 1001;
$state['raw'] = str_repeat('X', 1862);
check(!harmat_contact_family_context() && harmat_contact_family_content($old) === $old, 'raw drift observed after success');
$state['raw'] = str_repeat('R', 1862);
$state['asset_corrupt'] = 'project-garden.jpg';
check(!harmat_contact_family_assets() && harmat_contact_family_output($page) === $page, 'asset drift observed after success, no static asset cache');
fwrite(STDOUT, 'PASS: ' . $tests . " assertions (synthetic raw-hash shim; real sibling asset hashes). No WordPress/network/DB.\n");
