<?php
/** Offline fixture. Usage: php this-file.php /absolute/path/to/zz-harmat-about-family.php */
namespace HarmatAboutFamilyFixture;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
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
    if (!$condition) {
        throw new \RuntimeException('FAIL: ' . $label);
    }
}
function reset_state() {
    global $state, $styles;
    $state = array('queried' => 2686, 'page' => true, 'post' => 2686,
        'status' => 'publish', 'slug' => 'magunkrol', 'password' => '',
        'raw' => str_repeat('R', 14922), 'main' => true, 'loop' => true,
        'flags' => array(), 'asset_unreadable' => '', 'asset_corrupt' => '');
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
// The native raw content is not shipped here. Only this synthetic preimage is
// mapped to the pinned digest; changed bytes and every real asset use real SHA256.
function hash($algorithm, $data, $binary = false) {
    if ($algorithm === 'sha256' && $data === str_repeat('R', 14922) && !$binary) {
        return '4b272195ad1bfd35ba659fff2b9e285e6d2e6a9f1ebfd65bdb0a5f29232ee465';
    }
    return \hash($algorithm, $data, $binary);
}
function is_readable($path) {
    global $state;
    return basename($path) !== $state['asset_unreadable'] && \is_readable($path);
}
function hash_file($algorithm, $path) {
    global $state;
    return basename($path) === $state['asset_corrupt'] ? str_repeat('0', 64) : \hash_file($algorithm, $path);
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
function is_page($id) { global $state; return $id === 2686 && $state['page']; }
function get_post_status($id) { global $state; return $id === 2686 ? $state['status'] : false; }
function get_post_field($field, $id, $context = 'display') {
    global $state;
    check($id === 2686 && $context === 'raw', 'only raw fields of exact post requested');
    $keys = array('post_name' => 'slug', 'post_password' => 'password', 'post_content' => 'raw');
    return $state[$keys[$field]];
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
// Namespace the unmodified code's functions for isolated WP/hash stubs; preserve
// __DIR__/__FILE__ so includes always load the candidate's actual sibling assets.
$tokens = token_get_all($source);
check($tokens[0][0] === T_OPEN_TAG, 'candidate PHP opening tag');
$code = '';
foreach ($tokens as $index => $token) {
    if ($index === 0) { continue; }
    if (is_array($token)) {
        $code .= $token[0] === T_DIR ? var_export(dirname(realpath($candidate)), true)
            : ($token[0] === T_FILE ? var_export(realpath($candidate), true) : $token[1]);
    } else {
        $code .= $token;
    }
}
eval('namespace ' . __NAMESPACE__ . ';' . $code);
check(count($hooks) === 3, 'only three presentation hooks');
check($hooks === array(
    array('filter', array('the_content', 'harmat_about_family_content', 99, 1)),
    array('action', array('wp_enqueue_scripts', 'harmat_about_family_enqueue', 99)),
    array('action', array('template_redirect', 'harmat_about_family_buffer', 99)),
), 'exact hook names and priorities');
check(strpos($source, "hash('sha256', \$raw) === '4b272195ad1bfd35ba659fff2b9e285e6d2e6a9f1ebfd65bdb0a5f29232ee465'") !== false,
    'production pins real raw SHA256, without fixture bypass');
check(harmat_about_family_context(), 'public context');
check(harmat_about_family_assets(), 'all real asset hashes');
$old = '<section class="hm-about-v2"><h2>Old public copy</h2></section>';
$body = harmat_about_family_content($old);
check($body !== $old, 'approved content replaces old marker');
check(substr_count($body, 'id="harmat-about-redesign"') === 1, 'single owned root');
check(substr_count($body, '<img ') === 2, 'exactly two images');
check(strpos($body, 'src="assets/') === false, 'no relative images');
foreach (array('harmat-courtyard.jpg', 'harmat-living-room.jpg') as $name) {
    check(substr_count($body, 'src="https://fixture.invalid/wp-content/mu-plugins/harmat-about-family/' . $name . '"') === 1,
        'absolute approved image ' . $name);
}
foreach (array('398', '124', '2028', 'II. negyed', '1105 Budapest, Harmat utca 22.',
    'tel:+36300733375', 'mailto:ertekesites@harmat22.hu', 'https://harmat22.hu/lakaskereso/',
    'https://harmat22.hu/elerhetosegeink/', 'https://harmat22.hu/epitesi-naplo/',
    'Cooperation Power Kft.', 'Value4Real Group') as $fact) {
    check(strpos($body, $fact) !== false, 'approved fact/contact/navigation ' . $fact);
}
check(!preg_match('~<(?:form|script|header|footer|nav)\b~i', $body), 'no added native shell/forms/scripts');
check(harmat_about_family_content($body) === $body, 'content idempotence');
foreach (array(null, false, 123, array('value'), new \stdClass()) as $value) {
    check(harmat_about_family_content($value) === $value, 'nonstring content unchanged');
    check(harmat_about_family_output($value) === $value, 'nonstring output unchanged');
}
foreach (array('', '<p>unrecognized</p>', $old . $old, '<div class="hm-about-v2 extra">x</div>') as $unknown) {
    check(harmat_about_family_content($unknown) === $unknown, 'unknown/duplicate content unchanged');
}
$hero = '<h2 id="hap-title" class="hap-title">Harmat<br>Lak' . "\u{00f3}" . 'park</h2>';
$title = '<h1 class="page-title typo-heading">Magunkr' . "\u{00f3}" . 'l</h1>';
$prefix = '<!doctype html><html><head><title>Native SEO</title></head><body class="page-id-2686"><header><a href="/ajanlatkeres/">Native quote</a></header><div id="page-title-bar"><div>' . $title . '</div></div><main>';
$suffix = '</main><footer><form action="/native-appointment"><input name="native"></form><script>nativeCookie();</script></footer></body></html>';
$page = $prefix . $body . $suffix;
$paired = harmat_about_family_output($page);
check($paired !== $page, 'paired output applied');
check(substr_count($paired, '<h1 ') === 1 && strpos($paired, '<h1 id="hap-title"') !== false, 'sole H1 is hero');
check(strpos($paired, str_replace(array('<h1 ', '</h1>'), array('<h2 ', '</h2>'), $title)) !== false, 'native title demoted');
check(substr_count($paired, '<style id="harmat-about-family-title">') === 1, 'paired hide style once');
check(strpos($paired, 'body.page-id-2686 #page-title-bar{display:none!important;}') !== false, 'hide style page scoped');
$restored = str_replace('<style id="harmat-about-family-title">body.page-id-2686 #page-title-bar{display:none!important;}</style>', '', $paired);
$restored = str_replace('<h1 id="hap-title" class="hap-title">Harmat<br>Lak' . "\u{00f3}" . 'park</h1>', $hero, $restored);
$restored = str_replace(str_replace(array('<h1 ', '</h1>'), array('<h2 ', '</h2>'), $title), $title, $restored);
check($restored === $page, 'every other byte including quote/appointment/SEO/cookies preserved');
check(harmat_about_family_output($paired) === $paired, 'paired output idempotent');
$entity_page = str_replace("Magunkr\u{00f3}l", 'Magunkr&#243;l', $page);
check(harmat_about_family_output($entity_page) !== $entity_page, 'decoded native title accepted');
$extra_h2 = str_replace('</main>', '<h2>Native extra heading</h2></main>', $page);
check(strpos(harmat_about_family_output($extra_h2), '<h2>Native extra heading</h2>') !== false, 'unrelated H2 preserved');
$fallbacks = array(
    str_replace($title, '<h1 class="page-title">Magunkrol</h1>', $page),
    str_replace($title, str_replace('Magunkr', 'Unknownr', $title), $page),
    str_replace('</h1>', '</h2>', $page),
    str_replace($hero, str_replace('<br>', '<br/>', $hero), $page),
    str_replace($hero, '', $page),
    $page . $body,
    $page . '<div id=\'harmat-about-redesign\'></div>',
    $page . '<h1>Another H1</h1>',
    $page . $title,
    str_replace('</head>', '', $page),
    str_replace('id="page-title-bar"', 'id="unknown-title"', $page),
    str_replace($title, '', $prefix) . $title . $body . $suffix,
    str_replace($hero, '', $page) . $hero,
    str_replace('</head>', '<style id="harmat-about-family-title"></style></head>', $page),
);
foreach ($fallbacks as $index => $fallback) {
    check(harmat_about_family_output($fallback) === $fallback, 'malformed/duplicate output fallback ' . $index);
}
harmat_about_family_enqueue();
check($styles === array(array('harmat-about-family',
    'https://fixture.invalid/wp-content/mu-plugins/harmat-about-family/about-family.css', array(), 'de5fa727295f6b76', 'all')), 'owned versioned stylesheet');
check(str_contains(file_get_contents(dirname($candidate) . '/harmat-about-family/about-family.css'),
    'body.page-id-2686.opal-header-absolute #masthead > div.site-header {position:relative!important;}'),
    'native inner header participates in flow only on the About page');
check(str_contains(file_get_contents(dirname($candidate) . '/harmat-about-family/about-family.css'),
    '#harmat-about-redesign.harmat-about-preview h3 {font-family:inherit;}'),
    'subheadings inherit the approved readable font instead of native small caps');
// The namespaced evaluation changes function registration lookup only in tests.
// Alias its callback for the actual ob_start integration test, without WP.
eval('namespace { function harmat_about_family_output($html) { return \\HarmatAboutFamilyFixture\\harmat_about_family_output($html); } }');
$level = ob_get_level();
harmat_about_family_buffer();
check(ob_get_level() === $level + 1, 'target starts one buffer');
echo $page;
$buffered = ob_get_contents();
ob_end_clean();
check($buffered === $page, 'buffer retains complete native document until flush');
ob_start();
harmat_about_family_buffer();
echo $page;
ob_end_flush();
$flushed = ob_get_clean();
check($flushed === $paired, 'registered output buffer transforms complete document');

$rejects = array(
    array('queried', 164), array('queried', '2686'), array('page', false),
    array('status', 'draft'), array('status', 'private'), array('slug', 'other'), array('password', 'secret'),
    array('raw', str_repeat('X', 14922)), array('raw', str_repeat('R', 14921)), array('raw', null),
);
foreach (array('admin', 'ajax', 'cron', 'feed', 'preview', '404', 'search', 'embed', 'trackback', 'json') as $flag) {
    $rejects[] = array($flag, true);
}
foreach ($rejects as $case) {
    reset_state();
    $state[$case[0]] = $case[1];
    check(!harmat_about_family_context(), 'context rejects ' . $case[0] . ':' . var_export($case[1], true));
    check(harmat_about_family_content($old) === $old, 'rejected content byte identity');
    check(harmat_about_family_output($page) === $page, 'rejected page byte identity');
    harmat_about_family_enqueue();
    check($styles === array(), 'rejected context no CSS');
    $level = ob_get_level();
    harmat_about_family_buffer();
    check(ob_get_level() === $level, 'rejected context no buffer');
}
foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
    reset_state();
    $state['flags'][$flag] = true;
    check(!harmat_about_family_context(), 'constant blocks ' . $flag);
    check(harmat_about_family_content($old) === $old && harmat_about_family_output($page) === $page, 'constant no changes');
    $state['flags'][$flag] = false;
    check(harmat_about_family_context(), 'false flag allows ' . $flag);
}
foreach (array('POST', 'PUT', 'DELETE', 'OPTIONS', '', 'get', null) as $method) {
    reset_state();
    $_SERVER['REQUEST_METHOD'] = $method;
    check(!harmat_about_family_context(), 'reject method ' . var_export($method, true));
    check(harmat_about_family_content($old) === $old && harmat_about_family_output($page) === $page, 'method no changes');
}
reset_state();
$_SERVER['REQUEST_METHOD'] = 'HEAD';
check(harmat_about_family_context(), 'HEAD accepted');
reset_state();
$_GET['elementor-preview'] = '';
check(!harmat_about_family_context(), 'empty Elementor preview parameter rejected');
check(harmat_about_family_content($old) === $old && harmat_about_family_output($page) === $page, 'Elementor no changes');
foreach (array(array('main', false), array('loop', false), array('post', 164), array('post', '2686')) as $case) {
    reset_state();
    $state[$case[0]] = $case[1];
    check(harmat_about_family_content($old) === $old, 'wrong loop/post content identity');
}
foreach (array('body.php', 'about-family.css', 'harmat-courtyard.jpg', 'harmat-living-room.jpg') as $asset) {
    foreach (array('asset_unreadable', 'asset_corrupt') as $failure) {
        reset_state();
        $state[$failure] = $asset;
        check(!harmat_about_family_assets(), 'reject ' . $failure . ' ' . $asset);
        check(harmat_about_family_content($old) === $old && harmat_about_family_output($page) === $page, 'asset failure no changes');
        harmat_about_family_enqueue();
        check($styles === array(), 'asset failure no CSS');
        $level = ob_get_level();
        harmat_about_family_buffer();
        check(ob_get_level() === $level, 'asset failure no buffer');
    }
}
reset_state();
foreach (array(164, 439, 1, 0, 99999) as $id) {
    $state['queried'] = $id;
    check(harmat_about_family_content($old) === $old && harmat_about_family_output($page) === $page, 'non-target byte identity ' . $id);
}
fwrite(STDOUT, 'PASS: ' . $tests . " assertions (synthetic raw-hash shim; real sibling asset hashes). No WordPress/network/DB.\n");
