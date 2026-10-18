<?php
// CLI-only genuine routing with synthetic fixtures; no requests or record writes.
if (PHP_SAPI !== 'cli') exit(1);
ob_start();
try {
    require __DIR__ . '/2026-09-10-test-assistant.php';
    $previous = ob_get_contents();
} finally {
    ob_end_clean();
}
if (trim($previous) !== 'PASS 39 assertions' || $count !== 39) throw new RuntimeException('Base assistant tests failed');
$checks = 0;
function promotion_check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
$normalize = new ReflectionMethod(Harmat_Local_Assistant::class, 'normalize');
$guard = new ReflectionMethod(Harmat_Local_Assistant::class, 'is_payment_schedule_question');
$payment = new ReflectionMethod(Harmat_Local_Assistant::class, 'payment_schedule_answer');
$tax = new ReflectionMethod(Harmat_Local_Assistant::class, 'tax_vat_answer');
$faq = new ReflectionMethod(Harmat_Local_Assistant::class, 'faq_answer');
$hashes = array(
    'hu' => 'dfe5a56b582f7a649d8b9027470d32b10fae90cf7fff511f169a9a23d4c2e10d',
    'zh' => '7f1c25c2b80ef4ac6500aba29c0cd656d5636456787e8f0cdc971c9d89932fb4',
    'en' => '4a62c4d7b82fa5a1e876d2e51b0aa7c28d7deccbbbe847d53e0259e5857b411c',
);
$questions = array(
    'hu' => array("Milyen akci\u{f3}k vannak?", 'Milyen akciok vannak?', "Van akci\u{f3}?",
        "AKCI\u{d3}K?", "Akci\u{f3}s lehet\u{151}s\u{e9}gek?", "(akci\u{f3}k)?", "8% kedvezm\u{e9}ny", '4% kedvezmeny', "Fizet\u{e9}si \u{fc}temez\u{e9}s"),
    'zh' => array("\u{6709}\u{7279}\u{4ef7}\u{5417}", "\u{7279}\u{4ef7}\u{ff1f}", "\u{6709}\u{4ec0}\u{4e48}\u{7279}\u{4ef7}\u{4f18}\u{60e0}\u{ff1f}",
        "4%\u{4f18}\u{60e0}", "\u{6298}\u{6263}", "\u{4ed8}\u{6b3e}\u{8282}\u{70b9}"),
    'en' => array('Are there any promotions?', 'Any promotion?', 'PROMOTIONS?', '(promotions)?',
        '8% discount', '4% discount', 'payment options'),
);
$context = array('rooms' => 2, 'budget' => 70000000, 'floor' => '2', 'terrace' => true);
foreach ($questions as $lang => $list) {
    $answer = $payment->invoke($assistant, $lang);
    promotion_check(hash('sha256', $answer) === $hashes[$lang], $lang . ' approved answer unchanged');
    foreach ($list as $question) {
        $text = $normalize->invoke($assistant, $question);
        promotion_check($guard->invoke($assistant, $text), $question . ' payment guard');
        $reply = ask($question, $lang, $context);
        promotion_check($reply['answer'] === $answer, $question . ' exact three-plan answer');
        promotion_check(!$reply['cards'] && !$reply['handoff'], $question . ' no cards or handoff');
        promotion_check($reply['selection'] === $context, $question . ' preserves selection');
    }
}
$tax_questions = array(
    'hu' => array("4% illet\u{e9}k kedvezm\u{e9}ny", "Akci\u{f3}k \u{e9}s \u{e1}fa?", "Akci\u{f3}s ad\u{f3}?", 'akciok illetek'),
    'zh' => array("4%\u{7a0e}\u{8d39}\u{4f18}\u{60e0}", "\u{7279}\u{4ef7}\u{8d2d}\u{7f6e}\u{7a0e}", "\u{7279}\u{4ef7}\u{589e}\u{503c}\u{7a0e}"),
    'en' => array('4% tax discount', 'promotions tax', 'promotion VAT', 'promotions duty'),
);
foreach ($tax_questions as $lang => $list) {
    foreach ($list as $question) {
        $text = $normalize->invoke($assistant, $question);
        promotion_check(!$guard->invoke($assistant, $text), $question . ' excluded from promotion guard');
        promotion_check($faq->invoke($assistant, $text, $lang, 'discount') === $tax->invoke($assistant, $lang), $question . ' explicit tax takes precedence');
        promotion_check(ask($question, $lang, $context)['answer'] === $tax->invoke($assistant, $lang), $question . ' genuine tax routing');
    }
}
foreach (array('reakcio', 'tranzakcio', 'frakciok', 'akciokod', 'xakcio', 'xpromotions',
    'promotionsx', 'promotional', 'unpromotional', 'negotiate', 'alku', "\u{780d}\u{4ef7}") as $question) {
    $text = $normalize->invoke($assistant, $question);
    promotion_check(!$guard->invoke($assistant, $text), $question . ' not a bounded promotion/payment question');
    promotion_check($faq->invoke($assistant, $text, 'en', 'discount') !== $payment->invoke($assistant, 'en'), $question . ' no invented negotiated plan');
}
promotion_check(Harmat_Local_Assistant::VERSION === '0.4.3', 'runtime version');
echo json_encode(array('status' => 'PASS', 'promotion_payment_checks' => $checks,
    'base' => trim($previous), 'scope' => 'PURE_NO_NETWORK_NO_SUBMISSION_NO_MAIL')) . PHP_EOL;
