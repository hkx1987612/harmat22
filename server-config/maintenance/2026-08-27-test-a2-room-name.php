<?php
define('HARMAT_ROOM_NAME_TESTS_ONLY', true);
require __DIR__ . '/2026-08-27-correct-a2-room-name.php';

function room_test(bool $passed, string $name): void
{
    if (!$passed) {
        throw new RuntimeException('FAILED: ' . $name);
    }
    echo 'PASS ' . $name . PHP_EOL;
}

$row = static function (string $code, string $name, string $size): string {
    return '<div class="area-row"><span class="area-code">' . $code . '</span>' . "\r\n"
        . '<span class="area-name">' . $name . '</span>' . "\r\n"
        . '<span class="area-size">' . $size . " m\u{00B2}</span></div>";
};
$living = $row('A2-3-L1/04', 'Nappali', '22.68');
$bedroom = $row('A2-3-L1/08', 'Nappali', '18.34');
$html = $living . "\r\n" . $bedroom;
$content = "A2-3-L1/04\nNappali\n22.68 m\u{00B2}\nA2-3-L1/08\nNappali\n18.34 m\u{00B2}";
$data = array(array('id' => 'root', 'elements' => array(array('id' => '4520a1d', 'widgetType' => 'html', 'settings' => array('html' => $html, 'other' => 'Nappali')), array('id' => 'unrelated', 'settings' => array('html' => 'Nappali')))));
$raw = json_encode($data, JSON_THROW_ON_ERROR);
$result = harmat_a2_room_name_values($content, $raw);
$expected = $data;
$expected[0]['elements'][0]['settings']['html'] = $living . "\r\n" . $row('A2-3-L1/08', 'Szoba', '18.34');
room_test(json_decode($result['elementor'], true) === $expected, 'only target HTML row changes');
room_test($result['content'] === str_replace("A2-3-L1/08\nNappali", "A2-3-L1/08\nSzoba", $content), 'only target text line changes');
$again = harmat_a2_room_name_values($result['content'], $result['elementor']);
room_test(!$again['content_changed'] && !$again['elementor_changed'] && $again['elementor'] === $result['elementor'], 'idempotent');

$cases = array(
    'wrong area' => array(str_replace('18.34', '18.35', $content), $raw),
    'wrong name' => array(str_replace("A2-3-L1/08\nNappali", "A2-3-L1/08\nOffice", $content), $raw),
    'duplicate text row' => array($content . "\n" . $content, $raw),
    'wrong apartment' => array(str_replace('A2-3-L1', 'A2-3-L2', $content), $raw),
    'missing widget' => array($content, str_replace('4520a1d', 'different', $raw)),
    'wrong HTML area' => array($content, str_replace('18.34', '18.35', $raw)),
    'invalid JSON' => array($content, '{'),
    'duplicate widget' => array($content, json_encode(array($data[0], $data[0]), JSON_THROW_ON_ERROR)),
);
foreach ($cases as $name => [$test_content, $test_raw]) {
    $rejected = false;
    try {
        harmat_a2_room_name_values($test_content, $test_raw);
    } catch (Throwable $error) {
        $rejected = true;
    }
    room_test($rejected, $name . ' rejected');
}
