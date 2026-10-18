<?php
// Isolated genuine assistant routing; no WordPress/server/HTTP/CRM requests.
if (PHP_SAPI !== 'cli') { exit(1); }
ob_start();
try { require __DIR__ . '/2026-09-10-test-assistant.php'; }
finally { ob_end_clean(); }
$questions = array(
    'hu' => array('Fizetési ütemezés', '8% kedvezmény', '4% kedvezmény', 'Milyen akciók vannak?', '50-50', '4% illeték'),
    'zh' => array('付款节点', '全款优惠', '4%优惠', '有特价吗', '付款有什么优惠', '4%购置税'),
    'en' => array('payment options', '8% discount', '4% discount', 'Are there any promotions?', '50-50', '4% tax'),
);
$result = array();
foreach ($questions as $lang => $list) {
    foreach ($list as $question) {
        $reply = ask($question, $lang, array());
        $text = $reply['answer'];
        $result[] = array('lang' => $lang, 'question' => $question,
            'three_plans' => str_contains($text, '8%') && str_contains($text, '4%') && str_contains($text, '25%'),
            'old_milestone_date' => (bool) preg_match('/2027[.\/-]0?[35][.\/-]31|2027年[35]月31日/u', $text),
            'answer' => $text);
    }
}
echo json_encode(array('scope' => 'ISOLATED_REAL_ROUTING_NO_NETWORK_NO_CUSTOMER_DATA', 'results' => $result),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
