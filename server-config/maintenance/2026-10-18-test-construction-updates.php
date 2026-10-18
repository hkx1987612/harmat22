<?php
namespace HarmatConstructionFixture;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') exit(1);
$candidate = $argv[1] ?? '';
if (!\is_file($candidate)) throw new RuntimeException('Candidate required');
\define('ABSPATH', __DIR__ . '/');
\define('WP_CONTENT_DIR', '/hcu-fixture-content');
$checks = 0;
$state = array('page' => 'epitesi-naplo', 'missing' => false, 'flags' => array(), 'status' => 'publish', 'password' => false, 'hooks' => array(), 'link' => false, 'empty' => false);
$_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = array();
function check($value, $label) { global $checks; $checks++; if (!$value) throw new RuntimeException('FAIL ' . $label); }
function load_candidate($path) {
    $tokens = token_get_all(file_get_contents($path), TOKEN_PARSE);
    $code = 'namespace ' . __NAMESPACE__ . '; use \\DateTimeImmutable; use \\DateTimeZone; use \\DOMDocument; use \\DOMXPath; use \\DOMElement; use \\Throwable;';
    $first_open = true;
    foreach ($tokens as $token) {
        if (!is_array($token)) { $code .= $token; continue; }
        if ($token[0] === T_OPEN_TAG && $first_open) { $first_open = false; continue; }
        $code .= $token[0] === T_DIR ? var_export(dirname($path), true) : $token[1];
    }
    eval($code);
}
function defined($name) { global $state; return isset($state['flags'][$name]) || \defined($name); }
function constant($name) { global $state; return $state['flags'][$name] ?? \constant($name); }
function function_exists($name) { return \function_exists(__NAMESPACE__ . '\\' . $name) || \function_exists($name); }
function is_admin() { global $state; return $state['flags']['admin'] ?? false; }
function wp_doing_ajax() { return false; } function wp_doing_cron() { return false; }
function is_feed() { return false; } function is_preview() { return false; } function is_404() { return false; }
function is_search() { return false; } function is_embed() { return false; } function is_trackback() { return false; }
function is_main_query() { global $state; return !isset($state['flags']['secondary']); }
function wp_is_json_request() { global $state; return isset($state['flags']['json']); }
function is_page($page) { global $state; return $page === $state['page']; }
function get_queried_object_id() { global $state; return $state['page'] === 164 ? 164 : 900; }
function get_post_status($id) { global $state; return $state['status']; }
function post_password_required() { global $state; return $state['password']; }
function is_front_page() { global $state; return $state['page'] === 164; }
function get_option($key) { return $key === 'page_on_front' ? 164 : 'page'; }
function get_page_template_slug($id) { return ''; }
function get_post_field($field, $id, $context) { return array('post_content' => str_repeat('R', 42575), 'post_name' => 'fooldal', 'post_password' => '')[$field]; }
function hash($algo, $value) { return $value === str_repeat('R', 42575) ? 'dabadc35c52bd029d1097178a52eebd08a5a5ccca1bb5a9d53c12797a473dedf' : \hash($algo, $value); }
function get_post($id) { return (object)array('post_type' => 'property', 'post_status' => 'publish', 'post_password' => ''); }
function get_post_meta($id, $key, $single) { return ''; }
function harmat_sai_property_summary_data($id) { return array('title' => array(4317 => 'A1-1-L6', 5212 => 'A2-4-L5', 4311 => 'A1-1-L5')[$id], 'rooms' => 2, 'sales_area' => 47.83, 'status' => 'current', 'price' => 0, 'hide_price' => true); }
function get_permalink($id) { return 'https://harmat22.hu/property/' . strtolower(harmat_sai_property_summary_data($id)['title']) . '/'; }
function hm_migrated_property_floorplan_image_from_uploads($code, $pdf) { return 'https://harmat22.hu/wp-content/uploads/2026/05/' . $code . '.jpg'; }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); } function esc_url($value) { return esc_html($value); }
function content_url($path) { return 'https://harmat22.hu/wp-content' . $path; }
function home_url($path = '/') { return 'https://harmat22.hu' . $path; }
function add_action(...$args) { global $state; $state['hooks'][] = $args; }
function add_filter(...$args) { global $state; $state['hooks'][] = $args; }
function fake_path($path) { return strpos($path, WP_CONTENT_DIR . '/') === 0; }
function realpath($path) { return fake_path($path) ? str_replace('/', DIRECTORY_SEPARATOR, $path) : \realpath($path); }
function is_link($path) { global $state; return fake_path($path) ? $state['link'] : \is_link($path); }
function is_dir($path) { return strpos(str_replace(DIRECTORY_SEPARATOR, '/', $path), WP_CONTENT_DIR . '/') === 0 || \is_dir($path); }
function is_file($path) { global $state; return fake_path($path) ? !$state['missing'] : \is_file($path); }
function is_readable($path) { return fake_path($path) || \is_readable($path); }
function filesize($path) { global $state; return fake_path($path) ? ($state['empty'] ? 0 : 100) : \filesize($path); }
load_candidate($candidate);
$registry = harmat_cu_registry();
$ready = static function ($basename) { return true; };
$records = harmat_cu_records(array_reverse($registry), '2026-10-18', $ready);
check(count($records) === 5 && $records[0]['date'] === '2026-10-13', 'five confirmed records sorted newest first');
check($records[1]['id'] === '2026-10-12-site-01' && $records[4]['id'] === '2026-10-12-site-04', 'same day stable ascending clip order');
check($records[0]['duration'] === 89.3 && $records[4]['duration'] === 27.16, 'confirmed durations');
check($records[0]['uploaded'] === '2026-10-18' && $records[0]['date'] === '2026-10-13', 'website release and capture date distinct');
check(strpos($records[0]['note']['hu'], 'szintetikus') !== false, 'synthetic narration disclosed');
foreach ($records as $record) check(strpos($record['video'], HARMAT_CU_MEDIA_BASE) === 0 && strpos($record['poster'], HARMAT_CU_MEDIA_BASE) === 0, 'exact local public media base');
foreach (array('2026-02-30', '2026-10-32', '2026-1-01', '', null, array()) as $date) check(!harmat_cu_date($date), 'invalid calendar dates rejected');
check(harmat_cu_records($registry, 'bad', $ready) === array(), 'invalid today rejected');
check(harmat_cu_records($registry, '2026-10-17', $ready) === array(), 'unreleased website publication excluded');
foreach (array(
    array('approved', false), array('status', 'draft'), array('date', '2027-01-01'), array('uploaded', '2027-01-01'),
    array('uploaded', '2026-10-01'), array('file', '../secret'), array('file', 'x?query'), array('file', 'https://evil.test/x'),
    array('id', '2026-10-13"><script>'), array('date', '2026-02-30'), array('duration', -1), array('duration', INF),
    array('title', 'bad'), array('summary', false), array('note', null), array('title', array('hu' => 'x')),
    array('summary', array('hu' => array(), 'zh' => 'x')), array('note', array('hu' => "\xFF", 'zh' => 'x')),
) as $change) {
    $bad = $registry[0]; $bad[$change[0]] = $change[1];
    check(harmat_cu_records(array($bad), '2026-10-18', $ready) === array(), 'malformed ' . $change[0] . ' rejected without exception');
}
check(count(harmat_cu_records(array($registry[0], $registry[0]), '2026-10-18', $ready)) === 1, 'duplicate id excluded');
$samefile = $registry[0]; $samefile['id'] = '2026-10-13-site-01';
check(count(harmat_cu_records(array($registry[0], $samefile), '2026-10-18', $ready)) === 1, 'duplicate source excluded');
check(harmat_cu_records($registry, '2026-10-18', static function ($file) { return false; }) === array(), 'missing media excluded');
check(harmat_cu_media_ready('latest-site-01'), 'local paired media exists');
foreach (array('missing', 'link', 'empty') as $flag) { $state[$flag] = true; check(!harmat_cu_media_ready('latest-site-01'), 'unsafe paired media ' . $flag); $state[$flag] = false; }
check(!harmat_cu_media_ready('../secret'), 'direct path traversal rejected');
check(harmat_cu_context(), 'journal main read context allowed');
foreach (array('POST', 'PUT', 'DELETE') as $method) { $_SERVER['REQUEST_METHOD'] = $method; check(!harmat_cu_context(), 'write method excluded'); }
$_SERVER['REQUEST_METHOD'] = 'HEAD'; check(harmat_cu_context(), 'HEAD allowed'); $_SERVER['REQUEST_METHOD'] = 'GET';
foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST', 'admin', 'secondary', 'json') as $flag) {
    $state['flags'][$flag] = true; check(!harmat_cu_context(), 'excluded ' . $flag); $state['flags'] = array();
}
$state['password'] = true; check(!harmat_cu_context(), 'password excluded'); $state['password'] = false;
$state['status'] = 'draft'; check(!harmat_cu_context(), 'unpublished page excluded'); $state['status'] = 'publish';
$_GET['elementor-preview'] = '1'; check(!harmat_cu_context(), 'builder preview excluded'); $_GET = array();
$markup = harmat_cu_journal_markup($records);
check(strpos($markup, 'id="harmat-construction-updates"') !== false && substr_count($markup, 'data-hcu-player') === 5, 'one root five lazy poster players');
check(!preg_match('~<(?:video|source|iframe|form|input|textarea)\b~', $markup), 'initial markup has no media downloads or forms');
check(strpos($markup, 'data-i18n=') === false && strpos($markup, 'data-language-protect') !== false, 'independent internal translation ownership');
$escaped = $records[0]; $escaped['title']['hu'] = '\"><script>alert(1)</script>';
check(strpos(harmat_cu_journal_markup(array($escaped)), '<script>alert') === false, 'localized text and attributes escaped');
$units = array(); $bodyPath = dirname($candidate) . '/harmat-home-family/body.php';
$harmat_home_family_units = array();
if (\is_file($bodyPath)) {
    $body = include $bodyPath;
    $teaser = harmat_cu_home_teaser($body);
    check($teaser !== $body && strpos($teaser, 'data-hcu-home="1"') !== false, 'manifest exact journal replaced');
    check(strpos($teaser, '#construction-2026-10-13') !== false && strpos($teaser, 'journalDate') === false, 'same latest record and deeplink not old translations');
    check(!preg_match('~<(?:form|input|textarea|header|footer|iframe|script|html|head|body)\b~i', $teaser), 'teaser respects homepage guard');
    check(harmat_cu_home_teaser($teaser) === $teaser, 'home repeated pass idempotent');
    $drift = str_replace('journalDescription', 'journalDrift', $body);
    check(harmat_cu_home_teaser($drift) === $drift, 'owned journal drift retains original');
    check(harmat_cu_home_teaser($body . $body) === $body . $body, 'duplicate journal retains original');
    $state['missing'] = true; check(harmat_cu_home_teaser($body) === $body, 'home missing files retains original'); $state['missing'] = false;
}
$legacyPath = dirname($candidate) . '/zz-harmat-construction-progress-video.php';
if (\is_file($legacyPath)) {
    load_candidate($legacyPath);
    $legacy = harmat_construction_video_inject('<section class="harmat-build-log-list"><article>Archive retained</article></section>');
} else {
    $legacy = '<section class="harmat-construction-september" data-harmat-construction-september="1" aria-labelledby="harmat-construction-september-title">September retained</section><section class="harmat-build-log-list"><article>Archive retained</article></section>';
}
$form = '<div id="h22-offer-modal"><form id="h22-offer-form"><input name="your-name"><select name="source"><option>Google keresés</option></select><input type="checkbox" required></form></div>';
$input = '<!doctype html><html lang="hu"><head><title>Journal</title></head><body><header>Native header</header><main id="main">' . $legacy . '</main>' . $form . '<footer>Native footer</footer></body></html>';
$output = harmat_cu_journal_output($input);
check($output !== $input && substr_count($output, 'data-hcu-journal="1"') === 1, 'final journal injected once');
check(strpos($output, 'data-hcu-date="2026-10-13"') < strpos($output, 'data-hcu-date="2026-10-12"') && strpos($output, 'data-hcu-date="2026-10-12"') < strpos($output, 'data-harmat-construction-september="1"'), 'October latest before September');
check(strpos($output, $legacy) !== false && strpos($output, $form) !== false, 'archive and native form byte exact retained');
check(harmat_cu_journal_output($output) === $output, 'journal repeated pass idempotent');
foreach (array('not html', str_replace('</html>', '', $input), str_replace('</main>', '</main></main>', $input), str_replace('aria-labelledby="harmat-construction-september-title"', '', $input), str_replace('<main id="main">', '<main id="other">', $input)) as $bad) check(harmat_cu_journal_output($bad) === $bad, 'partial invalid ownership fails open');
$state['page'] = 'other'; check(harmat_cu_journal_output($input) === $input && harmat_cu_description('Native') === 'Native', 'other route unchanged'); $state['page'] = 'epitesi-naplo';
$state['missing'] = true; check(harmat_cu_journal_output($input) === $input && harmat_cu_schema(array()) === array(), 'missing registry leaves journal and SEO unchanged'); $state['missing'] = false;
check(strpos(harmat_cu_description('old'), '2026. október 13.') !== false, 'current metadata uses shared latest');
$graph = array(array('@id' => 'legacy-video', '@type' => 'VideoObject'), array('@id' => 'webpage', '@type' => 'WebPage'));
$schema = harmat_cu_schema($graph);
check(count($schema) === 7 && array_slice($schema, 0, 2) === $graph, 'legacy schema untouched latest appended');
check($schema[2]['uploadDate'] === '2026-10-18' && $schema[2]['dateCreated'] === '2026-10-13' && $schema[2]['duration'] === 'PT89.3S', 'schema factual capture release duration');
check(harmat_cu_schema($schema) === $schema, 'schema repeated pass idempotent');
$registered = array_values(array_filter($state['hooks'], static function ($hook) { return $hook[0] === 'template_redirect' && $hook[1] === 'harmat_cu_buffer' && $hook[2] === -101; }));
check(count($registered) === 1, 'outer buffer priority before legacy -100');
// Exercise real nested final callbacks, not a the_content approximation.
ob_start();
harmat_cu_buffer();
ob_start(static function ($html) { return \function_exists(__NAMESPACE__ . '\\harmat_construction_video_inject') ? harmat_construction_video_inject($html) : str_replace('<section class="harmat-build-log-list">', '<section class="harmat-construction-september" data-harmat-construction-september="1" aria-labelledby="harmat-construction-september-title">September retained</section><section class="harmat-build-log-list">', $html); });
echo str_replace($legacy, '<section class="harmat-build-log-list"><article>Archive retained</article></section>', $input);
ob_end_flush(); ob_end_flush(); $nested = ob_get_clean();
check(substr_count($nested, 'data-hcu-journal="1"') === 1 && strpos($nested, $form) !== false, 'real nested buffer preserves native form');
ob_start(); harmat_cu_buffer(); echo $input; ob_flush(); ob_end_flush(); $flushed = ob_get_clean();
check($flushed === $input, 'explicit flush disables mutation');
ob_start(); harmat_cu_buffer(); echo $input; ob_clean(); echo $input; ob_end_flush(); $cleaned = ob_get_clean();
check($cleaned === $input, 'clean event disables mutation');
$homeController = dirname($candidate) . '/zz-harmat-home-family.php';
if (\is_file($homeController) && \is_file($bodyPath)) {
    load_candidate($homeController);
    $state['page'] = 164;
    $seo = '<h1 class="harmat-seo-h1">Új építésű lakások Budapest X. kerületében - Harmat Lakópark</h1>';
    $shell = '<!doctype html><html><head><title>Native SEO</title></head><body><header id="masthead">Native menu</header>' . $seo
        . '<main id="main" class="site-main"><div class="elementor-164"><video id="harmat-native-home-video"></video><h2 id="harmat-home-featured-title">Native featured</h2></div></main>'
        . $form . '<footer id="colophon">Native footer</footer><button id="hm-cookie-settings-button">Cookie</button><div id="harmat-internal-language">Native language</div></body></html>';
    $combined = harmat_home_family_output($shell);
    check($combined !== $shell && strpos($combined, 'data-hcu-home="1"') !== false, 'actual homepage controller accepts new teaser');
    check(substr_count($combined, 'id="harmat-construction-updates-runtime"') === 1 && substr_count($combined, '<h1 ') === 1, 'one runtime and one homepage h1');
    check(strpos($combined, $form) !== false && strpos($combined, '<title>Native SEO</title>') !== false, 'actual homepage retains native form and SEO');
    check(harmat_cu_journal_output($combined) === $combined, 'journal output does not touch home');
    $state['missing'] = true;
    $fallback = harmat_home_family_output($shell);
    check($fallback !== $shell && strpos($fallback, 'data-hcu-home') === false && strpos($fallback, 'journalDate') !== false, 'module missing media retains complete old home journal');
    check(strpos($fallback, 'harmat-construction-updates-runtime') === false && strpos($fallback, $form) !== false, 'no inactive module runtime or native form change');
    $state['missing'] = false; $state['page'] = 'epitesi-naplo';
}
$render = $argv[2] ?? '';
if ($render === '--render') { echo $output; exit; }
if ($render === '--resources') { echo harmat_cu_resources(); exit; }
echo json_encode(array('status' => 'PASS', 'checks' => $checks), JSON_UNESCAPED_UNICODE) . PHP_EOL;
