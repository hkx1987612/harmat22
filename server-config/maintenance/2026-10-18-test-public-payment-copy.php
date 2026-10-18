<?php
namespace HarmatPublicPaymentFixture;
use DOMDocument;
use DOMXPath;
use RuntimeException;

if (PHP_SAPI !== 'cli') exit(1);
\define('ABSPATH', __DIR__ . '/');
$module = $argv[1] ?? dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-public-payment-copy.php';
if (!is_file($module) || !is_readable($module)) throw new RuntimeException('Readable local module candidate required');
$checks = 0;
$state = array('flags' => array(), 'hooks' => array(), 'headers' => array(), 'response' => 200, 'current' => 101, 'callbacks' => array());
class WP_Post {
    public $ID = 101;
    public $post_type = 'property';
    public $post_status = 'publish';
    public $post_password = '';
    public $post_name = 'a1-1-l2';
}
$post = new WP_Post();
$_GET = array();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/property/a1-1-l2/';
function check($ok, $label) { global $checks; ++$checks; if (!$ok) throw new RuntimeException('FAIL: ' . $label); }
function flag($key) { global $state; return $state['flags'][$key] ?? false; }
function defined($name) { global $state; return array_key_exists($name, $state['flags']) || \defined($name); }
function constant($name) { global $state; return $state['flags'][$name] ?? \constant($name); }
function function_exists($name) { return \function_exists(__NAMESPACE__ . '\\' . $name) || \function_exists($name); }
function class_exists($name) { return !flag('no_dom') && \class_exists($name); }
function is_admin() { return flag('admin'); }
function wp_doing_ajax() { return flag('ajax'); }
function wp_doing_cron() { return flag('cron'); }
function is_feed() { return flag('feed'); }
function is_preview() { return flag('preview'); }
function is_404() { return flag('404'); }
function is_search() { return flag('search'); }
function is_embed() { return flag('embed'); }
function is_trackback() { return flag('trackback'); }
function is_main_query() { return !flag('secondary'); }
function wp_is_json_request() { return flag('json'); }
function is_customize_preview() { return flag('customizer'); }
function in_the_loop() { return !flag('outside_loop'); }
function is_singular($type) { global $post; return $post instanceof WP_Post && $post->post_type === $type; }
function is_page($slug) { global $post; return $post instanceof WP_Post && $post->post_type === 'page' && $post->post_name === $slug; }
function get_queried_object() { global $post; return $post; }
function get_queried_object_id() { global $post; return $post->ID; }
function get_the_ID() { global $state; return $state['current']; }
function http_response_code() { global $state; return $state['response']; }
function headers_list() { global $state; return $state['headers']; }
function headers_sent() { return flag('headers_sent'); }
function ob_start($callback) { global $state; $state['callbacks'][] = $callback; return true; }
function esc_attr($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_html($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function home_url($path) { return 'https://harmat22.hu' . $path; }
function add_filter(...$args) { global $state; $state['hooks'][] = $args; }
function add_action(...$args) { global $state; $state['hooks'][] = $args; }
function load_code($code, $namespace = __NAMESPACE__) {
    eval('namespace ' . $namespace . '; use \\DOMDocument; use \\DOMXPath; '
        . 'use function HarmatPublicPaymentFixture\\esc_url; use function HarmatPublicPaymentFixture\\home_url; ' . $code);
}

$source = file_get_contents($module);
load_code(substr($source, 5));
check($state['hooks'] === array(array('elementor/widget/render_content', 'harmat_ppc_widget', 1000, 2),
    array('the_content', 'harmat_ppc_content', 1000), array('template_redirect', 'harmat_ppc_buffer', -4000)), 'two scoped filters and one outer final-document buffer registered');
check(strpos($source, 'Version: 1.0.0') !== false, 'new module version 1.0.0');
check(harmat_ppc_scope(), 'canonical published passwordless property GET');
$_SERVER['REQUEST_METHOD'] = 'HEAD'; check(harmat_ppc_scope(), 'HEAD allowed');
foreach (array('POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', '') as $method) {
    $_SERVER['REQUEST_METHOD'] = $method; check(!harmat_ppc_scope(), 'non-read method excluded: ' . $method);
}
$_SERVER['REQUEST_METHOD'] = 'GET';
foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST', 'WP_INSTALLING',
    'admin', 'ajax', 'cron', 'feed', 'preview', '404', 'search', 'embed', 'trackback', 'secondary', 'json', 'customizer') as $flag) {
    $state['flags'][$flag] = true;
    check(!harmat_ppc_scope(), 'scope excludes ' . $flag);
    check(harmat_ppc_content('<p>Native</p>') === '<p>Native</p>', 'content unchanged for ' . $flag);
    $state['flags'] = array();
}
$state['flags']['WP_CLI'] = false; check(harmat_ppc_scope(), 'false WP_CLI does not exclude public simulation'); $state['flags'] = array();
foreach (array('preview', 'preview_id', 'preview_nonce', 'elementor-preview', 'rest_route', 'feed', 'embed',
    'customize_changeset_uuid', 'customize_theme', 'action', 'wc-ajax') as $key) {
    $_GET = array($key => ''); check(!harmat_ppc_scope(), 'query exclusion even empty: ' . $key);
}
$_GET = array('utm_source' => 'fixture', 'lang' => 'zh-Hans');
$_SERVER['REQUEST_URI'] .= '?utm_source=fixture&lang=zh-Hans';
check(harmat_ppc_scope(), 'canonical path with public language/tracking query'); $_GET = array();
foreach (array('/property/a1-1-l2', '/property/other/', '/Property/a1-1-l2/', '/property/a1-1-l2/feed/',
    '/property/a1-1-l2/../a1-1-l2/', '/?p=101', '/wp-json/', '/wp-admin/', '/finanszirozas/') as $path) {
    $_SERVER['REQUEST_URI'] = $path; check(!harmat_ppc_scope(), 'noncanonical or mismatched path excluded: ' . $path);
}
$_SERVER['REQUEST_URI'] = '/property/a1-1-l2/';
foreach (array('draft', 'private', 'future', 'trash') as $status) {
    $post->post_status = $status; check(!harmat_ppc_scope(), 'unpublished excluded: ' . $status);
}
$post->post_status = 'publish'; $post->post_password = 'already-unlocked';
check(!harmat_ppc_scope(), 'passworded post excluded even with hypothetical unlocked cookie'); $post->post_password = '';
$post->post_name = '../a1-1-l2'; check(!harmat_ppc_scope(), 'noncanonical slug excluded'); $post->post_name = 'a1-1-l2';
$savedPost = $post; $post = (object)array('post_status' => 'publish'); check(!harmat_ppc_scope(), 'not a WP_Post excluded'); $post = $savedPost;
foreach (array(301, 404, 500) as $code) { $state['response'] = $code; check(!harmat_ppc_scope(), 'non-success response excluded'); }
$state['response'] = false; check(harmat_ppc_scope(), 'unset response accepted'); $state['response'] = 200;
$state['headers'] = array('Content-Type: application/json'); check(!harmat_ppc_scope(), 'non-HTML response excluded');
$state['headers'] = array('Content-Type: text/html; charset=UTF-8'); check(harmat_ppc_scope(), 'HTML response allowed'); $state['headers'] = array();

$html = harmat_ppc_html();
$dom = new DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($dom);
check($xpath->query('//*[@id="harmat-public-payment-copy" and @data-language-protect and @data-harmat-public-payment-copy="1"]')->length === 1,
    'one stable protected root');
check($xpath->query('//dl/div[@class="hppc-row"]')->length === 3, 'three compact plan rows');
check($xpath->query('//*[@data-hppc-hu and @data-hppc-zh]')->length === 10, 'heading and all nine text nodes have own HU/ZH copy');
check($xpath->query('//form | //input | //select | //textarea | //button | //a | //iframe | //article')->length === 0, 'no forms, CTA, embedded resources or nested cards');
foreach (array('hu', 'zh') as $language) {
    $copy = array();
    foreach ($xpath->query('//*[@data-hppc-' . $language . ']') as $node) $copy[] = $node->getAttribute('data-hppc-' . $language);
    check(strpos($copy[3], '8%') !== false && strpos($copy[6], '4%') !== false, $language . ' approved discounts');
    preg_match_all('~(\d+)%~', $copy[2], $full);
    check($full[1] === array(), $language . ' full-price condition is not a fabricated split');
    preg_match_all('~(\d+)%~', $copy[5], $halves);
    check($halves[1] === array('50', '50') && array_sum($halves[1]) === 100, $language . ' half/half total 100');
    preg_match_all('~(\d+)%~', $copy[8], $milestones);
    check($milestones[1] === array('25', '25', '25', '20', '5') && array_sum($milestones[1]) === 100, $language . ' milestone total 100');
    check(!preg_match('~202[67]|31|30|május|May|5月31~u', $copy[8] . $copy[9]), $language . ' milestones have no fixed dates');
}
foreach (array('2026. december 31-ig', '2027. június 30-ig', '2026年12月31日前', '2027年6月30日前',
    'foglaló és előleg összesen', '定金与预付款合计', 'kulcsátvételi értesítéskor', '收到领取钥匙通知时',
    'Kedvezmény nélkül.', '无折扣', 'rögzített fizetési dátumok nélkül.', '不设固定付款日期') as $copy) {
    check(strpos($html, $copy) !== false, 'approved bilingual wording: ' . $copy);
}
check(!preg_match('~május|May|05[.-]31|5月31|nélkül。~u', $html), 'no superseded May31 or Chinese punctuation in HU');
check(strpos(harmat_ppc_text('p', '<script>"', '"<b>'), '<script>') === false, 'text and attribute escaping');
check(strpos($html, 'grid-column:1/-1') !== false && strpos($html, 'font:400 16px/') !== false
    && strpos($html, 'minmax(0,1fr)') !== false && !preg_match('~\d(?:vw|cqw)~', $html), 'fullwidth responsive unframed 16px text without viewport fonts');
check(strpos($html, "document.getElementById('harmat-public-payment-copy')") !== false
    && strpos($html, 'if(!root||!root.hasAttribute') !== false && strpos($html, 'document.currentScript') === false, 'stable own root and null guard survive script relocation');
check(substr_count($html, '.observe(') === 1 && strpos($html, "attributeFilter:['data-harmat-language']") !== false
    && strpos($html, 'subtree:') === false && strpos($html, 'childList:') === false, 'only existing HTML language attribute observed');
check(!preg_match('~fetch\(|XMLHttpRequest|localStorage|sessionStorage|\.cookie|sendBeacon|addEventListener|data-i18n|<script[^>]*src=~i', $source),
    'no network, storage, inputs, global dictionary or external runtime');

$oldText = array('Fizetési Ütemezés', 'Foglaló: 10%', 'Előleg: 15%', '(A foglalóval együtt ez alkotja az első fizetést)',
    'Második részlet: 25%', 'szerkezetkész állapot elérése után', 'Harmadik részlet: 25%', 'belsőépítészeti munkálatok megkezdésekor',
    'Negyedik részlet: 20%', 'Miután értesítettük a vevőt a lakások elkészültéről', 'Végső részlet: 5%', 'a kulcsátadáskor');
$old = '<div data-elementor-type="container" data-elementor-id="4214" class="elementor elementor-4214"><h2>'
    . array_shift($oldText) . '</h2><p>' . implode('</p><p>', $oldText) . '</p></div>';
class Widget {
    private $name; private $id;
    public function __construct($name = 'template', $id = 4214) { $this->name = $name; $this->id = $id; }
    public function get_name() { return $this->name; }
    public function get_settings($key) { return $key === 'template_id' ? $this->id : null; }
}
check(harmat_ppc_known_template($old), 'complete known template text recognized');
check(harmat_ppc_widget($old, new Widget()) === $html, 'template widget 4214 replaced');
check(harmat_ppc_widget($old, new Widget('template', '4214')) === $html, 'normal string template id accepted');
check(harmat_ppc_known_template('<style>.elementor-4214{color:red}</style>' . $old), 'Elementor style text ignored in validation only');
check(harmat_ppc_known_template(str_replace('Foglaló:', "\n Foglaló: ", $old)), 'render whitespace does not cause drift');
foreach (array(new Widget('heading'), new Widget('template', 4215), new Widget('template', true), new Widget('template', '4214.0'), new \stdClass(), null) as $widget) {
    check(harmat_ppc_widget($old, $widget) === $old, 'unrelated widget/name/id unchanged');
}
check(harmat_ppc_widget($html, new Widget()) === $html, 'widget repeated pass idempotent');
$form = '<form id="native-offer"><input name="price" value="70995750"><select name="source"><option selected>Google keresés</option></select><textarea name="message">Native default 50%</textarea><input type="checkbox" name="privacy" required></form>';
foreach (array(str_replace('20%', '21%', $old), str_replace('elkészültéről', 'changed', $old), $old . '<p>New copy</p>',
    str_replace('4214', '4215', $old), $old . $old, '', '<div data-elementor-id="4214"></div>',
    $old . $form, $old . '<input value="untouched">', $old . '<textarea>Untouched</textarea>', $old . '<button>Native</button>',
    $old . '<a href="/other/">Native link</a>', $old . '<script>native()</script>') as $drift) {
    unset($GLOBALS['harmat_ppc_unknown_template']);
    check(harmat_ppc_widget($drift, new Widget()) === $drift, 'drift or controls retained byte-for-byte');
    check(harmat_ppc_content('<p>Native without template marker</p>') === '<p>Native without template marker</p>', 'observed unknown template blocks fallback even without rendered marker');
}
unset($GLOBALS['harmat_ppc_unknown_template']);
$native = '<div class="entry-content"><p>Native property</p>' . $form . '</div>';
$appended = harmat_ppc_content($native);
check($appended === $native . $html && substr_count($appended, 'id="harmat-public-payment-copy"') === 1, 'missing section appended exactly once after unchanged native bytes');
check(strpos($appended, $form) !== false, 'all unrelated native form/default bytes retained');
check(harmat_ppc_content($appended) === $appended, 'content repeated pass idempotent');
check(harmat_ppc_content('<section id="harmat-public-payment-copy"></section>') === '<section id="harmat-public-payment-copy"></section>', 'stable id alone blocks duplicate');
foreach (array('a1-4-l5', 'a2-4-l5', 'a3-4-l5', 'a4-2-l7', 'a4-4-l5') as $slug) {
    $post->post_name = $slug; $_SERVER['REQUEST_URI'] = '/property/' . $slug . '/';
    check(harmat_ppc_content($native) === $native . $html, 'missing-section canonical property covered in stub: ' . $slug);
}
$post->post_name = 'a1-1-l2'; $_SERVER['REQUEST_URI'] = '/property/a1-1-l2/';
foreach (array($old, '<div data-elementor-id = \'4214\'>cached drift</div>', '<div class="elementor-4214">unknown cache</div>',
    '[elementor-template id="4214"]', '{"template_id":4214}', '{&quot;template_id&quot;:4214}', '<h2>Fizetési ütemezés</h2>') as $cached) {
    check(harmat_ppc_content($native . $cached) === $native . $cached, 'cached/unknown 4214 or payment heading prevents duplicate and stays untouched');
}
$state['flags']['outside_loop'] = true; check(harmat_ppc_content($native) === $native, 'outside loop excluded'); $state['flags'] = array();
$state['current'] = 999; check(harmat_ppc_content($native) === $native, 'related or nested property content excluded'); $state['current'] = 101;
check(harmat_ppc_content(null) === null && harmat_ppc_widget(null, new Widget()) === null, 'non-string filter values unchanged');

// Complete-document fixtures use the genuine property shell: div#content.site-content, no main.
$footer = '<footer id="colophon" class="site-footer harmat-clean-footer" role="contentinfo"><p>Native footer</p></footer>';
$note = '<div class="harmat-property-disclaimer" data-harmat-property-disclaimer="1"><strong>Fontos tájékoztatás:</strong> Native legal notice.</div>';
function page_shell($content, $after = '', $finance = false) {
    global $form, $footer;
    $owner = $finance ? '<main id="main" class="site-main harmat-info-page harmat-financing-page">' . $content . '</main>'
        : '<div class="site-content-contain"><div id="content" class="site-content">' . $content . '</div></div>';
    return '<!doctype html><html lang="hu"><head><title>Native SEO</title><script>var nativeMarkup="<div>not a raw div</div>";</script><style>.native:before{content:"</div>"}</style></head><body class="'
        . ($finance ? 'page' : 'single-property') . '"><header>Native header</header>' . $form . $owner . $after . $footer
        . '<script>var tail="<div>native tail</div>";</script></body></html>';
}
$cachedOld = substr(str_replace('<h2>', '<div title="a > b"><!-- fake </div> --><h2>', $old), 0, -6) . '</div></div>';
$outsideStyle = '<style id="cached-4214-css">.elementor-4214{color:red}</style>';
$property = page_shell('<div class="elementor elementor-4292">' . $outsideStyle . $cachedOld . '<div>Native after template</div></div>', $note);
check(strpos($property, '<main') === false && harmat_ppc_document($property) !== false, 'genuine no-main property owner accepted');
$replaced = harmat_ppc_output($property);
check($replaced === str_replace($cachedOld, $html, $property), 'cached 4214 replaced by its exact raw balanced-div range only');
check(strpos($replaced, $outsideStyle) !== false && strpos($replaced, $form) !== false && strpos($replaced, $note) !== false,
    'cached external style, native form/defaults, notice and all other bytes preserved');
check(harmat_ppc_output($replaced) === $replaced, 'already-one-owned-root final pass does nothing');
$cacheComments = "\n<!-- Cached public document -->\n<!-- Dynamic WPCache Super Cache -->";
check(harmat_ppc_output($property . $cacheComments) === $replaced . $cacheComments, 'complete HTML may retain exact trailing cache comments');
check(harmat_ppc_output(page_shell($html . $html, $note)) === page_shell($html . $html, $note), 'duplicate owned roots unchanged, not repaired');
check(harmat_ppc_output(page_shell($old . $old, $note)) === page_shell($old . $old, $note), 'duplicate old templates unchanged');
foreach (array(str_replace('20%', '21%', $old), $old . '<h2>Fizetési ütemezés</h2>',
    str_replace('</p></div>', '</p><input name="native" value="keep"></div>', $old),
    str_replace('</p></div>', '</p><iframe src="about:blank"></iframe></div>', $old),
    str_replace('</p></div>', '</p><svg></svg></div>', $old),
    '<form>' . $old . '</form>', '<template>' . $old . '</template>', '<noscript>' . $old . '</noscript>',
    '<iframe>' . $old . '</iframe>', '<svg>' . $old . '</svg>', '<object>' . $old . '</object>') as $badTemplate) {
    $bad = page_shell($badTemplate, $note);
    check(harmat_ppc_output($bad) === $bad, 'unknown/control/frame/unsafe-ancestor cached template unchanged');
}
$spoofed = str_replace('<head>', '<head><!-- ' . $old . ' -->', $property);
check(harmat_ppc_output($spoofed) === str_replace($cachedOld, $html, $spoofed), 'comment fake roots and div closings do not affect lexical range');
$outside = page_shell('<p>Native owner</p>', $old . $note);
check(harmat_ppc_output($outside) === $outside, 'old template outside property content owner unchanged');
$missing = page_shell('<p>Native owner</p>', $note);
check(harmat_ppc_output($missing) === str_replace($note, $html . $note, $missing), 'no-template section inserted immediately before unique native notice outside content');
$noNote = page_shell('<p>Native owner</p>');
check(harmat_ppc_output($noNote) === str_replace($footer, $html . $footer, $noNote), 'no notice uses exact validated colophon footer outside content');
foreach (array('a1-4-l5', 'a2-4-l5', 'a3-4-l5', 'a4-2-l7', 'a4-4-l5') as $slug) {
    $post->post_name = $slug; $_SERVER['REQUEST_URI'] = '/property/' . $slug . '/';
    check(harmat_ppc_output($missing) === str_replace($note, $html . $note, $missing), 'five missing-section routes covered in no-main fixture: ' . $slug);
}
$post->post_name = 'a1-1-l2'; $_SERVER['REQUEST_URI'] = '/property/a1-1-l2/';
foreach (array(page_shell('<h2>Fizetési ütemezés</h2>', $note), page_shell('<div class="elementor-4214">unknown cache</div>', $note),
    page_shell('No template', $note . $note), str_replace($footer, $footer . $footer, $missing),
    page_shell('No template', '<form>' . $note . '</form>'), page_shell('<main>' . $note . '</main>'),
    page_shell($note), str_replace($footer, '<footer id="colophon">Drifted footer</footer>', $noNote),
    str_replace($note, '<template>' . $note . '</template>', $missing)) as $bad) {
    check(harmat_ppc_output($bad) === $bad, 'missing-section heading/unknown marker/duplicate/unsafe anchor unchanged');
}
foreach (array('plain text', substr($property, 0, -7), str_replace('</head>', '', $property), str_replace('</body>', '</body></body>', $property),
    str_replace('class="single-property"', 'class="page"', $property), str_replace('id="content"', 'id="other"', $property),
    str_replace('class="site-content"', 'class="other"', $property), str_replace('<!-- fake </div> -->', '<!-- never closed', $property),
    str_replace('<!-- fake </div> -->', '</section>', $property), str_replace('title="a > b"', 'title="unterminated', $property),
    str_replace('<div class="elementor elementor-4292">', '<div id="content"><div class="elementor elementor-4292">', $property),
    str_replace('</html>', '</html>Unexpected tail', $property)) as $bad) {
    check(harmat_ppc_output($bad) === $bad, 'partial/malformed/wrong/duplicate document shell unchanged');
}
check(harmat_ppc_ranges(str_repeat('<div>', 257) . str_repeat('</div>', 257)) === false, 'bounded structural depth');
check(harmat_ppc_output(str_repeat('X', 8 * 1024 * 1024 + 1)) === str_repeat('X', 8 * 1024 * 1024 + 1), 'oversize document unchanged');
$state['flags']['no_dom'] = true; check(harmat_ppc_output($property) === $property, 'missing DOM extension unchanged'); $state['flags'] = array();
foreach (array(false, 301, 404, 500) as $code) { $state['response'] = $code; check(harmat_ppc_output($property) === $property, 'final buffer requires actual response 200'); }
$state['response'] = 200;
$state['headers'] = array('Content-Type: application/json'); check(harmat_ppc_output($property) === $property, 'non-HTML final response unchanged'); $state['headers'] = array();
$state['flags']['REST_REQUEST'] = true; check(harmat_ppc_output($property) === $property, 'final response scope exclusions retained'); $state['flags'] = array();

$literal = '<article><h2>Fizetési ütemezés</h2><p>A pontos fizetési ütemezés a választott lakástól, az adásvételi folyamat állapotától és az egyedi megállapodástól függ. Az értékesítési csapat minden ajánlatnál külön egyezteti az aktuális részleteket.</p></article>';
$datedLiteral = '<article><h2>Fizetési ütemezés</h2><p>A jelenlegi projektmérföldkövek: szerződéskötés a tényleges aláírás napján, szerkezetkész / tetőszint 2027. május, belső munkák 2027. szeptember, műszaki átadás / ellenőrzés 2028. március, várható átadás 2028. június. A pontos fizetési arányokat és határidőket mindig az ajánlat és a szerződés rögzíti.</p></article>';
check(harmat_ppc_financing_articles() === array($literal, $datedLiteral), 'only exact generic and genuine dated-polish article alternatives pinned');
$post->post_type = 'page'; $post->post_name = 'finanszirozas'; $_SERVER['REQUEST_URI'] = '/finanszirozas/';
check(harmat_ppc_scope(), 'canonical published financing page allowed');
$_SERVER['REQUEST_METHOD'] = 'HEAD'; check(harmat_ppc_scope(), 'financing HEAD allowed'); $_SERVER['REQUEST_METHOD'] = 'GET';
check(harmat_ppc_content($native) === $native && harmat_ppc_widget($old, new Widget()) === $old, 'property filters never run on finance');
$otherArticles = '<article><h2>CSOK lehetőség</h2><p>Native CSOK.</p></article><article><h2>Hitelügyintézés</h2><p>Native loan.</p></article><article><h2>Banki finanszírozás</h2><p>Native bank.</p></article>';
function finance_shell($article) {
    global $otherArticles;
    return page_shell('<section class="harmat-info-hero"><h1>Finanszírozás</h1></section><section class="harmat-info-grid">'
        . $article . $otherArticles . '</section><section class="harmat-info-note"><h2>Tájékoztató jellegű nyilatkozat</h2><p>Native legal conditions.</p></section>', '', true);
}
foreach (array($literal, $datedLiteral) as $oldArticle) {
    $financeDoc = finance_shell($oldArticle); $financed = harmat_ppc_output($financeDoc);
    check($financed === str_replace($oldArticle, $html, $financeDoc), 'finance replaces only exact known raw article after both native owners');
    check(strpos($financed, $form) !== false && strpos($financed, $otherArticles) !== false, 'all native form/default and other three financing article bytes retained');
    check(harmat_ppc_output($financed) === $financed, 'finance final buffer idempotent');
    $dom->loadHTML('<?xml encoding="UTF-8">' . $financed, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); $xpath = new DOMXPath($dom);
    check($xpath->query('//section[@class="harmat-info-grid"]/article')->length === 3
        && $xpath->query('//section[@class="harmat-info-grid"]/section[@id="harmat-public-payment-copy"]')->length === 1,
        'finance grid keeps three articles plus fullwidth unframed plan section');
}
foreach (array(finance_shell($literal . $datedLiteral), finance_shell($literal . $literal), finance_shell(str_replace('május', 'augusztus', $datedLiteral)),
    finance_shell(str_replace('</p>', '<input value="keep"></p>', $literal)), finance_shell('<form>' . $literal . '</form>'),
    finance_shell('<template>' . $literal . '</template>'), finance_shell('<iframe>' . $literal . '</iframe>'),
    str_replace('class="harmat-info-grid"', 'class="unknown-grid"', finance_shell($literal)),
    str_replace('class="site-main harmat-info-page harmat-financing-page"', 'class="other"', finance_shell($literal)),
    str_replace('<h2>Fizetési ütemezés</h2>', '<h2>Unknown heading</h2>', finance_shell($literal)),
    str_replace('id="main"', 'id="other"', finance_shell($literal)), finance_shell('<h2>Fizetési ütemezés</h2>Unknown article'),
    str_replace('</head>', '<!-- ' . $literal . ' --></head>', finance_shell($literal))) as $bad) {
    check(harmat_ppc_output($bad) === $bad, 'unknown/duplicate/unsafe financing article or shell unchanged');
}
$state['flags']['admin'] = true; $financeDoc = finance_shell($datedLiteral);
check(harmat_ppc_output($financeDoc) === $financeDoc, 'excluded finance context unchanged'); $state['flags'] = array();
$_SERVER['REQUEST_URI'] = '/finanszirozas'; check(!harmat_ppc_scope() && harmat_ppc_output($financeDoc) === $financeDoc, 'noncanonical finance path unchanged');
$_SERVER['REQUEST_URI'] = '/finanszirozas/';

$intact = true;
check(harmat_ppc_buffer_chunk($financeDoc, PHP_OUTPUT_HANDLER_START | PHP_OUTPUT_HANDLER_FINAL, $intact) === str_replace($datedLiteral, $html, $financeDoc), 'single intact final buffer transforms');
foreach (array(0, PHP_OUTPUT_HANDLER_START, PHP_OUTPUT_HANDLER_FLUSH, PHP_OUTPUT_HANDLER_FINAL | PHP_OUTPUT_HANDLER_CLEAN, PHP_OUTPUT_HANDLER_FINAL | PHP_OUTPUT_HANDLER_FLUSH) as $phase) {
    $intact = true;
    check(harmat_ppc_buffer_chunk($financeDoc, $phase, $intact) === $financeDoc && !$intact, 'partial/flush/clean chunk never transforms');
    check(harmat_ppc_buffer_chunk($financeDoc, PHP_OUTPUT_HANDLER_FINAL, $intact) === $financeDoc, 'later final chunk cannot recover a flushed/partial buffer');
}
harmat_ppc_buffer();
check(count($state['callbacks']) === 1, 'one scoped output buffer started');
check($state['callbacks'][0]($financeDoc, PHP_OUTPUT_HANDLER_START | PHP_OUTPUT_HANDLER_FINAL) === str_replace($datedLiteral, $html, $financeDoc), 'registered final callback transforms known finance');
$state['flags']['headers_sent'] = true; harmat_ppc_buffer(); check(count($state['callbacks']) === 1, 'already-sent headers do not start buffer'); $state['flags'] = array();
$state['flags']['WP_CLI'] = true; harmat_ppc_buffer(); check(count($state['callbacks']) === 1, 'ordinary WP CLI never starts buffer'); $state['flags'] = array();
$post->post_name = 'other'; $_SERVER['REQUEST_URI'] = '/other/'; check(!harmat_ppc_scope(), 'other page excluded');

echo json_encode(array('status' => 'PASS', 'assertions' => $checks, 'mode' => 'pure-stubs',
    'network' => false, 'wordpress_boot' => false), JSON_UNESCAPED_SLASHES) . "\n";
