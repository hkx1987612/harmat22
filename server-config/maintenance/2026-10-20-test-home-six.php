<?php
// Synthetic rows only: no actual price/area claims, WordPress, network, browser, or persistence.
if (PHP_SAPI !== 'cli') exit(1);
$hhf_candidate = isset($argv[1]) && $argv[1] !== '--render' ? $argv[1] : null;
if ($hhf_candidate !== null && !is_file($hhf_candidate)) throw new RuntimeException('Candidate controller required');
$hhf_asset_directory = $hhf_candidate === null
    ? dirname(__DIR__, 2) . '/wp-mu-plugins/harmat-home-family'
    : dirname(realpath($hhf_candidate)) . '/harmat-home-family';
if (!is_dir($hhf_asset_directory)) throw new RuntimeException('Sibling harmat-home-family asset directory required');
function hhf_test_rows(): array {
    $rows = array();
    foreach (array('A1-1-L6', 'A2-4-L5', 'A1-1-L5', 'A3-F-L3', 'A4-F-L1', 'A3-2-L2') as $i => $code) {
        $rows[$code] = array('code' => $code, 'url' => 'https://harmat22.hu/property/' . strtolower($code) . '/',
            'rooms' => array(1, 2, 4, 3, 2, 3)[$i], 'sales_area' => 40.25 + $i,
            'status' => 'current', 'price_huf' => 50000101 + $i,
            'floorplan_url' => 'https://harmat22.hu/wp-content/uploads/test-' . strtolower($code) . '.jpg');
    }
    return $rows;
}
function hhf_test_render($rows): string {
    global $hhf_asset_directory;
    $harmat_home_family_units = $rows;
    ob_start();
    $result = include $hhf_asset_directory . '/body-six.php';
    $output = ob_get_clean();
    if ($output !== '' || !is_string($result)) throw new RuntimeException('Template has output side effects');
    return $result;
}
if (in_array('--render', $argv, true)) {
    $input = stream_get_contents(STDIN);
    echo hhf_test_render($input === '' ? hhf_test_rows() : json_decode($input, true, 512, JSON_THROW_ON_ERROR));
    exit;
}
$checks = 0;
function hhf_check(bool $ok, string $message): void {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($message);
}
$frozen = array(
    'body-six.php' => array(42595, '0585ca56e2afa2a558932d9ee9ad85868641a199683d19caddeafd9a010d61d2'),
    'home-family-six.js' => array(34108, 'a2222a2e7cd882a128a146c1c35f6c316c63b3558073d5fcbe73d4dd59abca05'),
    'manifest-six.json' => array(794, '3b6c8c5248dab6e6eb78d15b3f8cca5203498095980a9f3875fb32a2caafefc1'),
);
foreach ($frozen as $name => $pin) {
    $file = $hhf_asset_directory . '/' . $name;
    hhf_check(filesize($file) === $pin[0] && hash_file('sha256', $file) === $pin[1], 'Frozen production asset: ' . $name);
}
$controller = $hhf_candidate ?? dirname($hhf_asset_directory) . '/zz-harmat-home-family.php';
hhf_check(filesize($controller) === 26813 && hash_file('sha256', $controller) === '2c0fdc06a013d9b74f82f24dd14c3ed71d92f0fa828809103558c55d76873f85', 'Frozen 1.0.14 controller');
$manifest = json_decode(file_get_contents($hhf_asset_directory . '/manifest-six.json'), true, 512, JSON_THROW_ON_ERROR);
$old_manifest = json_decode(file_get_contents($hhf_asset_directory . '/manifest-scroll-play.json'), true, 512, JSON_THROW_ON_ERROR);
hhf_check(count($manifest) === 9 && count($old_manifest) === 7, 'Nine pins retain seven original assets');
foreach ($old_manifest as $name => $pin) hhf_check(($manifest[$name] ?? '') === $pin, 'Original pin retained: ' . $name);
foreach ($manifest as $name => $pin) hhf_check(hash_file('sha256', $hhf_asset_directory . '/' . $name) === $pin, 'Mirrored asset hash: ' . $name);
$rows = hhf_test_rows();
$html = hhf_test_render($rows);
hhf_check(substr_count($html, 'class="hhf-apartment"') === 6, 'Six public cards');
hhf_check(strpos($html, '<main id="main" class="site-main">') === 0, 'Exact main wrapper');
hhf_check(substr_count($html, '<h1 id="hero-title">') === 1 && substr_count($html, '<h1') === 1, 'One expected H1');
hhf_check(strpos($html, 'data-language-protect=""') !== false, 'Native translator boundary');
hhf_check(strpos($html, '40,25 m²') !== false && strpos($html, '50 000 101') !== false, 'Live values formatted');
hhf_check(!preg_match('/<(?:form|input|textarea|header|footer|iframe|script|html|head|body)\b/i', $html), 'No shell/form/runtime');
hhf_check(strpos($html, 'target=') === false && strpos($html, '__HHF_') === false, 'Same tab, no unexpanded tokens');
hhf_check(strpos($html, 'data:') === false && strpos($html, 'src="assets/') === false, 'No offline media');
foreach (array('reserved', 'sold', 'available', '', null, array('current')) as $status) {
    $changed = $rows; $changed['A1-1-L6']['status'] = $status;
    hhf_check(substr_count(hhf_test_render($changed), 'class="hhf-apartment"') === 5, 'Non-current status omitted');
}
foreach (array('code', 'url', 'rooms', 'sales_area', 'status', 'price_huf', 'floorplan_url') as $field) {
    $changed = $rows; unset($changed['A1-1-L6'][$field]);
    hhf_check(substr_count(hhf_test_render($changed), 'class="hhf-apartment"') === 5, 'Missing field omitted: ' . $field);
}
foreach (array('rooms' => array(0, -1, INF, NAN, true, array(1), '1e2', '<b>1</b>'),
    'sales_area' => array(0, -1, INF, NAN, array(50), '50,25', '40<script>'),
    'price_huf' => array(0, -1, INF, NAN, true, array(5), '<script>')) as $field => $bad_values) {
    foreach ($bad_values as $value) {
        $changed = $rows; $changed['A1-1-L6'][$field] = $value;
        hhf_check(substr_count(hhf_test_render($changed), 'class="hhf-apartment"') === 5, 'Invalid numeric row omitted: ' . $field);
    }
}
foreach (array('url', 'floorplan_url') as $field) {
    foreach (array('javascript:alert(1)', 'data:image/jpeg,test', '//harmat22.hu/a', 'https://evil.test/a',
        'https://user@harmat22.hu/a', 'https://harmat22.hu:443/a', 'https://harmat22.hu/a b', null, array('/a')) as $value) {
        $changed = $rows; $changed['A1-1-L6'][$field] = $value;
        hhf_check(substr_count(hhf_test_render($changed), 'class="hhf-apartment"') === 5, 'Unsafe URL omitted: ' . $field);
    }
}
$changed = $rows; $changed['A1-1-L6']['price_huf'] = null;
$hidden = hhf_test_render($changed);
hhf_check(substr_count($hidden, 'class="hhf-apartment"') === 6, 'Hidden-price row retained');
hhf_check(strpos($hidden, 'Ár egyeztetés alapján') !== false && strpos($hidden, '50 000 101') === false, 'Hidden price never emitted');
$changed = $rows; $changed['A1-1-L6']['rooms'] = '3.5'; $changed['A1-1-L6']['sales_area'] = '72.91'; $changed['A1-1-L6']['price_huf'] = '99123456';
$fresh = hhf_test_render($changed);
hhf_check(strpos($fresh, '>3,5</dd>') !== false && strpos($fresh, '72,91 m²') !== false && strpos($fresh, '99 123 456') !== false, 'Fresh changed values used');
hhf_check(strpos($fresh, '40,25 m²') === false && strpos($fresh, '50 000 101') === false, 'No stale fallback facts');
$changed['A1-1-L6']['url'] .= '?a="x"&b=1';
$escaped = hhf_test_render($changed);
hhf_check(strpos($escaped, '?a=&quot;x&quot;&amp;b=1') !== false, 'URL attribute escaping');
foreach (array(array(), null, false, 'bad') as $value) hhf_check(strpos(hhf_test_render($value), 'class="hhf-apartment"') === false, 'Absent input omits cards');
hhf_check(hhf_test_render($rows) === $html && hhf_test_render($rows) === $html, 'Repeat includes deterministic without redeclaration');

foreach (array_keys($rows) as $code) {
    hhf_check(substr_count($html, 'data-code="' . $code . '"') === 1, 'Exactly one card: ' . $code);
    foreach (array('reserved', 'sold', 'unexpected') as $status) {
        $changed = $rows; $changed[$code]['status'] = $status;
        $filtered = hhf_test_render($changed);
        hhf_check(substr_count($filtered, 'class="hhf-apartment"') === 5
            && strpos($filtered, 'data-code="' . $code . '"') === false, 'Fresh status filter: ' . $code);
    }
    $changed = $rows; $changed[$code]['price_huf'] = null;
    $hidden = hhf_test_render($changed);
    hhf_check(substr_count($hidden, 'class="hhf-apartment"') === 6
        && substr_count($hidden, 'class="hhf-price-hidden"') === 1, 'Hidden price row retained: ' . $code);
    $changed = $rows; $changed[$code]['code'] = 'A1-1-L6';
    if ($code !== 'A1-1-L6') hhf_check(substr_count(hhf_test_render($changed), 'class="hhf-apartment"') === 5, 'Mismatched binding omitted: ' . $code);
}
$changed = $rows;
$changed['duplicate'] = $rows['A1-1-L6'];
$changed['A9-F-L1'] = $rows['A1-1-L6'];
hhf_check(hhf_test_render($changed) === $html, 'Unknown and duplicate keys never add cards');
hhf_check(strpos($html, 'data-i18n="threeRooms">3 szoba') !== false, 'Three-room Hungarian localization');
foreach (array('A3 épület · Földszint', 'A4 épület · Földszint', 'A3 épület · 2. emelet') as $floor) {
    hhf_check(strpos($html, $floor) !== false, 'Approved floor: ' . $floor);
}
hhf_check(substr_count($html, 'id="hhf-apartment-grid"') === 1, 'Unique controlled grid');
hhf_check(strpos($html, 'aria-controls="hhf-apartment-grid" aria-expanded="false" hidden') !== false, 'Toggle hidden without JS');
hhf_check(strpos($html, 'class="hhf-apartment" hidden') === false, 'No-JS cards remain visible');
$harmat_home_family_units = $rows;
$original = include $hhf_asset_directory . '/body.php';
$outside = static function ($value) {
    return preg_replace('~<section class="hhf-section hhf-apartments"[\\s\\S]*?</section>~', '', $value);
};
hhf_check($outside($original) === $outside($html), 'Every byte outside apartment region unchanged');

echo 'PASS ' . $checks . " presentation PHP assertions\n";
