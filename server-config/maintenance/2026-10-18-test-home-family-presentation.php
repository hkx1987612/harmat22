<?php
// Local, no WordPress bootstrap, network, browser, or persistence.
function hhf_test_rows(): array {
    $rows = array();
    foreach (array('A1-1-L6', 'A2-4-L5', 'A1-1-L5') as $i => $code) {
        $rows[$code] = array('code' => $code, 'url' => 'https://harmat22.hu/property/' . strtolower($code) . '/',
            'rooms' => array(1, 2, 4)[$i], 'sales_area' => 40.25 + $i,
            'status' => 'current', 'price_huf' => 50000101 + $i,
            'floorplan_url' => 'https://harmat22.hu/wp-content/uploads/test-' . strtolower($code) . '.jpg');
    }
    return $rows;
}
function hhf_test_render($rows): string {
    $harmat_home_family_units = $rows;
    ob_start();
    $result = include dirname(__DIR__, 2) . '/wp-mu-plugins/harmat-home-family/body.php';
    $output = ob_get_clean();
    if ($output !== '' || !is_string($result)) throw new RuntimeException('Template has output side effects');
    return $result;
}
if (($argv[1] ?? '') === '--render') {
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
$rows = hhf_test_rows();
$html = hhf_test_render($rows);
hhf_check(substr_count($html, 'class="hhf-apartment"') === 3, 'Three public cards');
hhf_check(strpos($html, '<main id="main" class="site-main">') === 0, 'Exact main wrapper');
hhf_check(substr_count($html, '<h1 id="hero-title">') === 1 && substr_count($html, '<h1') === 1, 'One expected H1');
hhf_check(strpos($html, 'data-language-protect=""') !== false, 'Native translator boundary');
hhf_check(strpos($html, '40,25 m²') !== false && strpos($html, '50 000 101') !== false, 'Live values formatted');
hhf_check(!preg_match('/<(?:form|input|textarea|header|footer|iframe|script|html|head|body)\b/i', $html), 'No shell/form/runtime');
hhf_check(strpos($html, 'target=') === false && strpos($html, '__HHF_') === false, 'Same tab, no unexpanded tokens');
hhf_check(strpos($html, 'data:') === false && strpos($html, 'src="assets/') === false, 'No offline media');
foreach (array('reserved', 'sold', 'available', '', null, array('current')) as $status) {
    $changed = $rows; $changed['A1-1-L6']['status'] = $status;
    hhf_check(substr_count(hhf_test_render($changed), 'class="hhf-apartment"') === 2, 'Non-current status omitted');
}
foreach (array('code', 'url', 'rooms', 'sales_area', 'status', 'price_huf', 'floorplan_url') as $field) {
    $changed = $rows; unset($changed['A1-1-L6'][$field]);
    hhf_check(substr_count(hhf_test_render($changed), 'class="hhf-apartment"') === 2, 'Missing field omitted: ' . $field);
}
foreach (array('rooms' => array(0, -1, INF, NAN, true, array(1), '1e2', '<b>1</b>'),
    'sales_area' => array(0, -1, INF, NAN, array(50), '50,25', '40<script>'),
    'price_huf' => array(0, -1, INF, NAN, true, array(5), '<script>')) as $field => $bad_values) {
    foreach ($bad_values as $value) {
        $changed = $rows; $changed['A1-1-L6'][$field] = $value;
        hhf_check(substr_count(hhf_test_render($changed), 'class="hhf-apartment"') === 2, 'Invalid numeric row omitted: ' . $field);
    }
}
foreach (array('url', 'floorplan_url') as $field) {
    foreach (array('javascript:alert(1)', 'data:image/jpeg,test', '//harmat22.hu/a', 'https://evil.test/a',
        'https://user@harmat22.hu/a', 'https://harmat22.hu:443/a', 'https://harmat22.hu/a b', null, array('/a')) as $value) {
        $changed = $rows; $changed['A1-1-L6'][$field] = $value;
        hhf_check(substr_count(hhf_test_render($changed), 'class="hhf-apartment"') === 2, 'Unsafe URL omitted: ' . $field);
    }
}
$changed = $rows; $changed['A1-1-L6']['price_huf'] = null;
$hidden = hhf_test_render($changed);
hhf_check(substr_count($hidden, 'class="hhf-apartment"') === 3, 'Hidden-price row retained');
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
echo 'PASS ' . $checks . " presentation PHP assertions\n";
