<?php
/** Offline: php fixture.php /absolute/candidate.php /absolute/approved-preview.html */
namespace HarmatProjectFamilyFixture;

if (PHP_SAPI !== 'cli') { exit(1); }
$candidate = $argv[1] ?? '';
$approved = $argv[2] ?? '';
if (!\is_file($candidate) || !\is_readable($candidate)) {
    fwrite(STDERR, "Supply candidate plugin path as argv[1].\n");
    exit(1);
}
if (!\is_file($approved) || !\is_readable($approved)) {
    fwrite(STDERR, "Supply the approved preview file as argv[2].\n");
    exit(1);
}
\define('ABSPATH', __DIR__ . '/');
$tests = 0;
$state = array();
$hooks = array();
$styles = array();
$buffers = array();
$source = file_get_contents($candidate);

function check($condition, $label) {
    global $tests;
    $tests++;
    if (!$condition) { throw new \RuntimeException('FAIL: ' . $label); }
}
function reset_state() {
    global $state, $styles, $buffers;
    $state = array('queried' => 1777, 'page' => true, 'main' => true,
        'status' => 'publish', 'slug' => 'harmat-lakopark', 'password' => '', 'template' => '',
        'raw' => str_repeat('R', 2963), 'flags' => array(), 'missing_owner' => '',
        'asset_missing' => '', 'asset_corrupt' => '', 'asset_link' => '', 'asset_unreadable' => '');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = array();
    $styles = array();
    $buffers = array();
}
function fixture_main() {
    $main = '<main id="main" class="site-main harmat-project-modern" role="main">'
        . '<article id="post-1777" class="post-1777 page type-page status-publish hentry"><div class="entry-content">'
        . '<section class="harmat-project-hero"><div class="harmat-project-hero-text"><span>Budapest X. kerulet</span>'
        . '<h2>Harmat Lak' . "\u{00f3}" . 'park</h2><p>Existing keyword-adjusted legacy project copy.</p>'
        . '<div class="harmat-project-actions"><a href="https://harmat22.hu/lakaskereso/">Lakasok</a>'
        . '<a href="https://harmat22.hu/virtualis-lakasvalaszto/">Virtualis valaszto</a>'
        . '<a href="https://harmat22.hu/elerhetosegeink/">Kapcsolat</a></div></div>'
        . '<figure><img src="https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-3-1536x864.jpg" alt="Legacy aerial"></figure></section>'
        . '<section class="harmat-project-stats"><div><strong>398</strong></div><div><strong>124</strong></div>'
        . '<div><strong>8388 m2</strong></div><div><strong>2028 Q2</strong></div></section>'
        . '<section class="harmat-project-copy"><h2>Existing project</h2><p>Keyword-adjusted project text.</p></section>'
        . '<section class="harmat-project-gallery"><article><h3>Legacy gallery</h3></article></section>'
        . '<section class="harmat-project-features"><article><h3>Existing features</h3></article></section>'
        . '<section class="harmat-project-note"><h2>Existing sales information</h2></section>';
    $tail = '</div></article></main>';
    $padding = '<!-- SYNTHETIC NON-NATIVE FIXTURE ';
    return $main . $padding . str_repeat('x', 4476 - strlen($main . $padding . ' -->' . $tail)) . ' -->' . $tail;
}
function theme_title() { return '<h1 class="page-title typo-heading">Harmat Lak' . "\u{00f3}" . 'park</h1>'; }
function fixture_html() {
    return '<!doctype html><html lang="hu"><head><meta charset="UTF-8"><title>Native project</title>'
        . '<link id="harmat-project-family-css" rel="stylesheet" href="https://harmat22.hu/wp-content/mu-plugins/harmat-project-family/project-family.css?ver=digest">'
        . '<script>window.nativeHead={keep:true};</script></head><body class="page-id-1777 opal-header-absolute">'
        . '<header id="masthead"><div class="site-header"><a id="native-offer" href="#opal-contactform-popup">Ajanlat</a>'
        . '<div id="my-sticky-header">Desktop native duplicate</div><div id="my-sticky-header">Mobile native duplicate</div></div></header>'
        . '<div id="page-title-bar"><div class="wrap">' . theme_title() . '</div></div>'
        . '<div id="content"><div id="primary">' . fixture_main() . '</div></div>'
        . '<footer id="colophon"><a href="/adatvedelmi-tajekoztato/">Privacy</a></footer>'
        . '<div class="h22-offer-modal" id="h22-offer-modal" hidden><div><form class="h22-offer-form" id="h22-offer-form" novalidate>'
        . '<input name="property" value=""><input name="date" type="date"><select name="source"><option>Google</option></select>'
        . '<input type="checkbox" name="privacy"><button type="submit">Send</button></form></div></div>'
        . '<button type="button" id="hm-cookie-settings-button">Cookie settings</button>'
        . '<script id="native-sales-script">window.nativeSales={appointment:true};</script></body></html>';
}
function defined($name) { global $state; return array_key_exists($name, $state['flags']) || \defined($name); }
function constant($name) { global $state; return $state['flags'][$name] ?? \constant($name); }
function function_exists($name) {
    global $state;
    if (in_array($name, array('hm_migrated_replace_project_page', 'harmat_keyword_filter_html', 'harmat_seo_cleanup_output'), true)) {
        return $name !== $state['missing_owner'];
    }
    return \function_exists(__NAMESPACE__ . '\\' . $name) || \function_exists($name);
}
// Only these two exact synthetic preimages are mapped, never assets or drifted HTML.
// This is NOT native WordPress proof. Sibling files always use genuine SHA256.
function hash($algorithm, $data, $binary = false) {
    if ($algorithm === 'sha256' && !$binary) {
        if ($data === str_repeat('R', 2963)) {
            return '6659cc4e514d92373321f12095971bea145270866125765909ef7db52f5efdd4';
        }
        if ($data === fixture_main()) {
            return 'db8446abb9b2702f0ae8b3e2468926e2fd4c5284292c031650b9a80fbb78bf17';
        }
    }
    return \hash($algorithm, $data, $binary);
}
function is_file($path) { global $state; return basename($path) !== $state['asset_missing'] && \is_file($path); }
function is_readable($path) { global $state; return basename($path) !== $state['asset_unreadable'] && \is_readable($path); }
function is_link($path) { global $state; return basename($path) === $state['asset_link'] || \is_link($path); }
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
function is_page($id) { global $state; return $id === 1777 && $state['page']; }
function is_main_query() { global $state; return $state['main']; }
function get_post_status($id) { global $state; check($id === 1777, 'exact status post'); return $state['status']; }
function get_page_template_slug($id) { global $state; check($id === 1777, 'exact template post'); return $state['template']; }
function get_post_field($field, $id, $context = 'display') {
    global $state;
    check($id === 1777 && $context === 'raw', 'only exact raw project fields');
    $keys = array('post_name' => 'slug', 'post_password' => 'password', 'post_content' => 'raw');
    check(isset($keys[$field]), 'no unrelated post fields read');
    return $state[$keys[$field]];
}
function content_url($path) { return 'https://fixture.invalid/wp-content' . $path; }
function esc_url($url) { return htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); }
function wp_enqueue_style(...$args) { global $styles; $styles[] = $args; }
function add_action(...$args) { global $hooks; $hooks[] = $args; }
function add_filter(...$args) { throw new \RuntimeException('Unexpected filter registration'); }
function ob_start($callback) { global $buffers; $buffers[] = $callback; return true; }

reset_state();
$tokens = token_get_all($source);
check($tokens[0][0] === T_OPEN_TAG, 'candidate opening tag');
$code = '';
foreach ($tokens as $index => $token) {
    if ($index === 0) { continue; }
    $code .= is_array($token)
        ? ($token[0] === T_DIR ? var_export(dirname(realpath($candidate)), true)
            : ($token[0] === T_FILE ? var_export(realpath($candidate), true) : $token[1]))
        : $token;
}
eval('namespace ' . __NAMESPACE__ . ';' . $code);
check($hooks === array(array('wp_enqueue_scripts', 'harmat_project_family_enqueue', 99),
    array('template_redirect', 'harmat_project_family_buffer', -3000)), 'exactly two hooks and outer buffer priority');
check(strpos($source, "hash('sha256', \$raw) === '6659cc4e514d92373321f12095971bea145270866125765909ef7db52f5efdd4'") !== false, 'real raw pin');
check(strpos($source, "hash('sha256', \$old) !== 'db8446abb9b2702f0ae8b3e2468926e2fd4c5284292c031650b9a80fbb78bf17'") !== false, 'real old main pin');
check(strlen(fixture_main()) === 4476, 'explicit synthetic legacy-main size');
check(harmat_project_family_context(), 'GET context');
check(harmat_project_family_assets(), 'six genuine sibling hashes (no fixture asset bypass)');
$directory = dirname(realpath($candidate)) . '/harmat-project-family';
$body = include $directory . '/body.php';
$preview = str_replace("\r\n", "\n", file_get_contents($approved));
$start = strpos($preview, '<main id="hpf-tartalom">') + strlen('<main id="hpf-tartalom">');
$inner = substr($preview, $start, strpos($preview, '</main>', $start) - $start);
check($body === '<main id="main" class="site-main harmat-project-family" role="main"><div id="harmat-project-family" class="hpf-preview">' . $inner . '</div></main>', 'exact approved main contents, only outer wrapper replaced');
check(substr_count($body, '<h1 id="hpf-title">Harmat Lak' . "\u{00f3}" . 'park</h1>') === 1, 'unchanged approved H1');
check(!preg_match('~<(?:header|footer|nav|form|script|input|textarea|select|iframe|meta|link)\b~i', $body), 'body excludes shell/forms/scripts/meta');
check(strpos($body, 'harmat-logo') === false, 'no preview logo');
check(substr_count($body, 'https://harmat22.hu/elerhetosegeink/') === 2, 'two real contact-page links');
$css = file_get_contents($directory . '/project-family.css');
check(strpos($css, '.hpf-preview') === false, 'CSS root replaced');
check(!preg_match('~\.hpf-(?:header|footer|brand|desktop-nav|mobile-menu|skip)\b~', $css), 'no preview shell selectors');
check(preg_match('~\.hpf-fact-number\s*\{[^}]*font-size:\s*36px\s*!important~s', $css) === 1, 'fixed 36px numerals');
check(substr_count($css, 'font-size: 36px') === 1, 'no responsive fact numeral changes');
check(strpos($css, 'font-family: Georgia, "Times New Roman", serif !important;') !== false, 'heading Georgia important');
check(strpos($css, 'padding: 0 !important;') !== false && strpos($css, 'text-transform: none !important;') !== false, 'native padding/case override');
check(substr_count($css, 'body.page-id-1777.opal-header-absolute #masthead') === 2, 'page-specific native header correction');
check(!preg_match('~(?:url\(|@import|@font-face)~i', $css), 'no additional CSS resources');
$old = fixture_html();
$result = harmat_project_family_output($old);
check($result !== $old, 'valid full document transforms despite own CSS href and native duplicate sticky IDs');
$resolved = $body;
foreach (array('project-aerial.jpg', 'project-courtyard.jpg', 'project-living-room.jpg', 'construction-site.webp') as $name) {
    $resolved = str_replace('src="assets/' . $name . '"', 'src="https://fixture.invalid/wp-content/mu-plugins/harmat-project-family/' . $name . '"', $resolved);
    check(substr_count($result, 'src="https://fixture.invalid/wp-content/mu-plugins/harmat-project-family/' . $name . '"') === 1, 'single absolute approved image ' . $name);
}
$style = '<style id="harmat-project-family-title">body.page-id-1777 #page-title-bar{display:none!important;}</style>';
$expected = str_replace(fixture_main(), $resolved, $old);
$expected = str_replace(theme_title(), str_replace(array('<h1 ', '</h1>'), array('<h2 ', '</h2>'), theme_title()), $expected);
$expected = str_replace('</head>', $style . '</head>', $expected);
check($result === $expected, 'entire result exact: only main, title demotion, success style differ');
check(substr_count($result, 'id="my-sticky-header"') === 2, 'unrelated native duplicate IDs preserved');
check(preg_match_all('~<h1\b~i', $result) === 1 && substr_count($result, '<h1 id="hpf-title">') === 1, 'single approved H1');
check(substr_count($result, '<img ') === 4 && strpos($result, 'src="assets/') === false, 'four resolved images only');
check(harmat_project_family_output($result) === $result, 'no reprocessing');
foreach (array('form', 'script', 'header', 'footer') as $tag) {
    preg_match_all('~<' . $tag . '\b[^>]*>[\s\S]*?</' . $tag . '>~i', $old, $before);
    preg_match_all('~<' . $tag . '\b[^>]*>[\s\S]*?</' . $tag . '>~i', $result, $after);
    check($before === $after, 'native ' . $tag . ' bytes preserved');
}
harmat_project_family_enqueue();
check(count($styles) === 1 && $styles[0][0] === 'harmat-project-family'
    && $styles[0][1] === 'https://fixture.invalid/wp-content/mu-plugins/harmat-project-family/project-family.css'
    && $styles[0][2] === array() && $styles[0][3] === substr(\hash_file('sha256', $directory . '/project-family.css'), 0, 16)
    && $styles[0][4] === 'all', 'guarded enqueue with CSS digest version');
harmat_project_family_buffer();
check(count($buffers) === 1, 'one outer buffer');
check($buffers[0]($old, PHP_OUTPUT_HANDLER_START | PHP_OUTPUT_HANDLER_FINAL) === $expected, 'whole final buffer transforms');
foreach (array(PHP_OUTPUT_HANDLER_START, PHP_OUTPUT_HANDLER_CONT, PHP_OUTPUT_HANDLER_FLUSH,
    PHP_OUTPUT_HANDLER_CLEAN, PHP_OUTPUT_HANDLER_CLEAN | PHP_OUTPUT_HANDLER_FINAL,
    PHP_OUTPUT_HANDLER_START | PHP_OUTPUT_HANDLER_FLUSH) as $phase) {
    reset_state();
    harmat_project_family_buffer();
    check($buffers[0]($old, $phase) === $old, 'partial/flush/clean full-looking chunk unchanged');
    check($buffers[0]($old, PHP_OUTPUT_HANDLER_FINAL) === $old, 'interrupted buffer never transforms later chunk');
}
function assert_excluded($label) {
    global $old, $styles, $buffers;
    check(!harmat_project_family_context(), $label . ' context excluded');
    check(harmat_project_family_output($old) === $old, $label . ' original bytes');
    harmat_project_family_enqueue();
    harmat_project_family_buffer();
    check(!$styles && !$buffers, $label . ' no enqueue/buffer');
}
foreach (array('HEAD', 'GET') as $method) {
    reset_state(); $_SERVER['REQUEST_METHOD'] = $method;
    check(harmat_project_family_context() && harmat_project_family_output($old) === $expected, $method . ' supported');
}
foreach (array('', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'get', 'head') as $method) {
    reset_state(); $_SERVER['REQUEST_METHOD'] = $method; assert_excluded('method ' . $method);
}
foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
    reset_state(); $state['flags'][$flag] = true; assert_excluded($flag);
    reset_state(); $state['flags'][$flag] = false; check(harmat_project_family_context(), $flag . ' false allowed');
}
foreach (array('admin', 'ajax', 'cron', 'feed', 'preview', '404', 'search', 'embed', 'trackback', 'json') as $flag) {
    reset_state(); $state[$flag] = true; assert_excluded($flag);
}
foreach (array('123', '', null) as $value) {
    reset_state(); $_GET['elementor-preview'] = $value; assert_excluded('elementor preview');
}
foreach (array('hm_migrated_replace_project_page', 'harmat_keyword_filter_html', 'harmat_seo_cleanup_output') as $owner) {
    reset_state(); $state['missing_owner'] = $owner; assert_excluded('missing owner ' . $owner);
}
foreach (array('queried' => array(26, '1777', 0), 'page' => array(false), 'main' => array(false),
    'status' => array('draft', 'private', 'future', false), 'slug' => array('magunkrol', 'harmat-lakopark-2', ''),
    'password' => array('protected', null), 'template' => array('custom.php', false, null),
    'raw' => array('', str_repeat('R', 2962), str_repeat('R', 2964), str_repeat('S', 2963), null, false)) as $key => $values) {
    foreach ($values as $value) {
        reset_state(); $state[$key] = $value; assert_excluded('identity/content/template drift ' . $key);
    }
}
foreach (array('body.php', 'project-family.css', 'project-aerial.jpg', 'project-courtyard.jpg', 'project-living-room.jpg', 'construction-site.webp') as $asset) {
    foreach (array('asset_missing', 'asset_corrupt', 'asset_link', 'asset_unreadable') as $failure) {
        reset_state(); $state[$failure] = $asset;
        check(!harmat_project_family_assets(), $failure . ' ' . $asset);
        check(harmat_project_family_output($old) === $old, 'asset drift original bytes');
        harmat_project_family_enqueue(); harmat_project_family_buffer();
        check(!$styles && !$buffers, 'asset drift no enqueue/buffer');
    }
}
reset_state(); $state['asset_link'] = 'harmat-project-family';
check(!harmat_project_family_assets() && harmat_project_family_output($old) === $old, 'symlink asset directory excluded');
reset_state();
$bad = array('', '<p>fragment</p>', fixture_main(), substr($old, 0, -7), substr($old, strpos($old, '<body')),
    str_replace(fixture_main(), '', $old), str_replace(fixture_main(), fixture_main() . fixture_main(), $old),
    str_replace('harmat-project-modern', 'harmat-project-other', $old),
    str_replace('Existing features', 'Changed features', $old),
    str_replace('Existing features', 'Existing featurex', $old),
    str_replace('</main>', '</main><main>Extra</main>', $old),
    str_replace('</main>', '', $old), str_replace('</head>', '</head></head>', $old),
    str_replace('<head>', '<head><head>', $old), str_replace('</body>', '</body></body>', $old),
    str_replace('<body ', '<body></body><body ', $old), str_replace('</html>', '</html><p>tail</p>', $old),
    str_replace(theme_title(), '', $old), str_replace(theme_title(), theme_title() . theme_title(), $old),
    str_replace(theme_title(), '<h1>Wrong title</h1>', $old),
    str_replace(theme_title(), '<h2>Wrong level</h2>', $old),
    str_replace('</footer>', '<h1>Extra H1</h1></footer>', $old),
    str_replace('</footer>', '<div id="harmat-project-family"></div></footer>', $old),
    str_replace('</head>', '<style id="harmat-project-family-title"></style></head>', $old),
    str_replace('</footer>', '<div id="hpf-lakopark"></div><div id="hpf-lakopark"></div></footer>', $old),
    str_replace('<div class="wrap">' . theme_title() . '</div>', '<div class="wrap"></div>', $old),
    str_replace('</main>', '</main>' . theme_title(), str_replace(theme_title(), '', $old)),
    str_replace(fixture_main(), '', str_replace('</head>', fixture_main() . '</head>', $old)),
    str_replace('id="h22-offer-form"', 'id="wrong-form"', $old),
    str_replace('<form class="h22-offer-form"', '<div class="h22-offer-form"', str_replace('</form>', '</div>', $old)),
    str_replace('</main>', '<button id="hm-cookie-settings-button">Misplaced</button></main>', str_replace('id="hm-cookie-settings-button"', 'id="other-cookie"', $old)));
foreach (array('main', 'page-title-bar', 'masthead', 'colophon', 'h22-offer-modal', 'h22-offer-form', 'hm-cookie-settings-button') as $id) {
    $bad[] = str_replace('id="' . $id . '"', 'id="missing-' . $id . '"', $old);
    $bad[] = str_replace('</body>', '<div id="' . $id . '"></div></body>', $old);
}
foreach ($bad as $index => $html) {
    check(harmat_project_family_output($html) === $html, 'unexpected/shifted/duplicate HTML unchanged #' . $index);
    check(strpos(harmat_project_family_output($html), $style) === false, 'no partial success style #' . $index);
}
foreach (array(null, false, 123, array('value'), new \stdClass()) as $value) {
    check(harmat_project_family_output($value) === $value, 'nonstring output unchanged');
}
reset_state();
harmat_project_family_buffer();
$cut = intdiv(strlen($old), 2);
check($buffers[0](substr($old, 0, $cut), PHP_OUTPUT_HANDLER_START) === substr($old, 0, $cut), 'first document fragment unchanged');
check($buffers[0](substr($old, $cut), PHP_OUTPUT_HANDLER_FINAL) === substr($old, $cut), 'last document fragment unchanged');
echo 'PASS ' . $tests . " isolated fixture checks; synthetic raw/legacy preimages, real six-file asset hashes. Not native WP proof.\n";
