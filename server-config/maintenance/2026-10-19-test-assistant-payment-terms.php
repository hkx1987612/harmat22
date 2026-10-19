<?php
// Isolated candidate only: no WordPress, REST dispatch, submissions or records.
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) exit(1);
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function remove_action(...$args) {}
function add_shortcode(...$args) {}
function home_url($path = '/') { return 'https://harmat22.hu' . $path; }
require $argv[1] ?? dirname(__DIR__, 2) . '/wp-plugins/harmat-local-assistant/harmat-local-assistant.php';
$assistant = $GLOBALS['harmat_local_assistant'];
$checks = 0;
function check($ok, $label) { global $checks; if (!$ok) throw new RuntimeException($label); ++$checks; }
function invoke($name, ...$args) {
    global $assistant;
    return (new ReflectionMethod($assistant, $name))->invoke($assistant, ...$args);
}
$personal = array('hu' => 'egyedileg egyeztessen az értékesítéssel', 'zh' => '优惠请与销售面议',
    'en' => 'discuss terms and any discount personally with the sales team');
$questions = array('hu' => array('Fizetési ütemezés', 'Milyen akciók vannak?', '8% kedvezmény', '4% kedvezmeny'),
    'zh' => array('付款节点', '有特价吗', '8%折扣', '4%优惠'),
    'en' => array('payment options', 'Any promotions?', '8% discount', '4% discount'));
foreach ($questions as $lang => $list) {
    $answer = invoke('payment_schedule_answer', $lang);
    check(!preg_match('~(?:8|4)%|kedvezmény nélkül|无折扣|no discount~iu', $answer), $lang . ' no discount promises');
    check(strpos($answer, $personal[$lang]) !== false, $lang . ' personal sales discussion');
    preg_match_all('~(\d+)%~', $answer, $parts);
    check($parts[1] === array('50', '50', '25', '25', '25', '20', '5'), $lang . ' exact payment percentages');
    $dates = $lang === 'hu' ? array('2026.12.31', '2027.06.30')
        : ($lang === 'zh' ? array('2026年12月31日', '2027年6月30日') : array('2026-12-31', '2027-06-30'));
    foreach ($dates as $date) check(strpos($answer, $date) !== false, $lang . ' date retained');
    $notice = $lang === 'hu' ? 'kulcsátvételi értesítés' : ($lang === 'zh' ? '收到领取钥匙通知时' : 'notification to collect the keys');
    check(strpos($answer, $notice) !== false, $lang . ' key notice retained');
    foreach ($list as $question) {
        $text = invoke('normalize', $question);
        check(invoke('is_payment_schedule_question', $text), $question . ' payment route');
        check(invoke('faq_answer', $text, $lang, 'discount') === $answer, $question . ' exact FAQ answer');
    }
    $tax = invoke('tax_vat_answer', $lang);
    check(strpos($tax, '4%') !== false && strpos($tax, '27%') !== false && strpos($tax, '5%') !== false, $lang . ' tax percentages preserved');
    foreach (array('4% tax discount', 'promotion VAT', '4% illeték kedvezmény', '4%税费优惠') as $question) {
        $text = invoke('normalize', $question);
        check(!invoke('is_payment_schedule_question', $text), $question . ' tax not payment');
        check(invoke('faq_answer', $text, $lang, 'discount') === $tax, $question . ' tax priority');
    }
}
check(Harmat_Local_Assistant::VERSION === '0.4.4', 'runtime version');
echo json_encode(array('status' => 'PASS', 'checks' => $checks, 'mode' => 'ISOLATED_NO_WORDPRESS_NO_NETWORK')) . PHP_EOL;
