<?php
// CLI-only synthetic assistant fixtures; never sends a request or creates a lead.
if (PHP_SAPI !== 'cli') exit(1);
ob_start();
try {
    require __DIR__ . '/2026-09-10-test-assistant.php';
    $previous = ob_get_contents();
} finally {
    ob_end_clean();
}
if (trim($previous) !== 'PASS 39 assertions' || $count !== 39) throw new RuntimeException('Base assistant tests failed');
$payment = new ReflectionMethod(Harmat_Local_Assistant::class, 'payment_schedule_answer');
$answers = array();
$cases = 0;
function payment_check($condition, $label) {
    global $cases;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $cases++;
}
$expected = array(
    'hu' => array(
        'full' => array("Teljes fizet\u{e9}s", "a lak\u{e1}s teljes v\u{e9}tel\u{e1}r\u{e1}nak megfizet\u{e9}se 2026.12.31-ig", "8% kedvezm\u{e9}nnyel a lak\u{e1}s v\u{e9}tel\u{e1}r\u{e1}b\u{f3}l"),
        'half' => array("50/50 fizet\u{e9}s", "50% megfizet\u{e9}se 2026.12.31-ig", "a fennmarad\u{f3} 50% megfizet\u{e9}se 2027.06.30-ig", "4% kedvezm\u{e9}nnyel a lak\u{e1}s v\u{e9}tel\u{e1}r\u{e1}b\u{f3}l"),
        'milestones' => array("\u{c9}p\u{ed}t\u{e9}si m\u{e9}rf\u{f6}ldk\u{f6}vekhez k\u{f6}t\u{f6}tt r\u{e9}szletfizet\u{e9}s, kedvezm\u{e9}ny n\u{e9}lk\u{fc}l", "25%: foglal\u{f3} \u{e9}s el\u{151}leg.", "25%: szerkezetk\u{e9}sz \u{e1}llapot / tet\u{151}szint.", "25%: bels\u{151}\u{e9}p\u{ed}t\u{e9}szeti munk\u{e1}k.", "20%: kulcs\u{e1}tv\u{e9}teli \u{e9}rtes\u{ed}t\u{e9}s.", "5%: \u{e1}tad\u{e1}s."),
        'disclaimer' => "az adott lak\u{e1}s al\u{e1}\u{ed}rt v\u{e9}gleges aj\u{e1}nlata \u{e9}s szerz\u{151}d\u{e9}se",
        'obsolete' => array("2027.05.31", "2027.03.31", "m\u{e1}jus 31", "m\u{e1}rcius 31", "3 napon bel\u{fc}l", "V\u{e9}teli biztos\u{ed}t\u{e9}k"),
        'dates' => array("2026.12.31", "2026.12.31", "2027.06.30"),
    ),
    'zh' => array(
        'full' => array("\u{5168}\u{989d}\u{4ed8}\u{6b3e}", "\u{4e8e}2026\u{5e74}12\u{6708}31\u{65e5}\u{524d}\u{4ed8}\u{6e05}\u{5168}\u{90e8}\u{623f}\u{6b3e}", "\u{4eab}\u{53d7}\u{623f}\u{4ef7}8%\u{7684}\u{6298}\u{6263}"),
        'half' => array("50/50\u{4ed8}\u{6b3e}", "\u{4e8e}2026\u{5e74}12\u{6708}31\u{65e5}\u{524d}\u{652f}\u{4ed8}50%", "\u{4e8e}2027\u{5e74}6\u{6708}30\u{65e5}\u{524d}\u{652f}\u{4ed8}\u{5269}\u{4f59}50%", "\u{4eab}\u{53d7}\u{623f}\u{4ef7}4%\u{7684}\u{6298}\u{6263}"),
        'milestones' => array("\u{6309}\u{65bd}\u{5de5}\u{8282}\u{70b9}\u{5206}\u{671f}\u{4ed8}\u{6b3e}\u{ff0c}\u{65e0}\u{6298}\u{6263}", "25%\u{ff1a}\u{5b9a}\u{91d1}\u{53ca}\u{9884}\u{4ed8}\u{6b3e}\u{3002}", "25%\u{ff1a}\u{5c01}\u{9876}\u{3002}", "25%\u{ff1a}\u{5ba4}\u{5185}\u{88c5}\u{4fee}\u{3002}", "20%\u{ff1a}\u{6536}\u{5230}\u{9886}\u{53d6}\u{94a5}\u{5319}\u{901a}\u{77e5}\u{65f6}\u{3002}", "5%\u{ff1a}\u{4ea4}\u{4ed8}\u{3002}"),
        'disclaimer' => "\u{5177}\u{4f53}\u{623f}\u{6e90}\u{6700}\u{7ec8}\u{7b7e}\u{7f72}\u{7684}\u{62a5}\u{4ef7}\u{4e66}\u{548c}\u{5408}\u{540c}\u{4e3a}\u{51c6}",
        'obsolete' => array("2027\u{5e74}5\u{6708}31\u{65e5}", "2027\u{5e74}3\u{6708}31\u{65e5}", "3\u{5929}\u{5185}", "\u{8d2d}\u{623f}\u{610f}\u{5411}\u{4fdd}\u{8bc1}\u{91d1}"),
        'dates' => array("2026\u{5e74}12\u{6708}31\u{65e5}", "2026\u{5e74}12\u{6708}31\u{65e5}", "2027\u{5e74}6\u{6708}30\u{65e5}"),
    ),
    'en' => array(
        'full' => array("Full payment", "pay the full apartment price by 2026-12-31", "8% discount on the apartment price"),
        'half' => array("50/50 payment", "pay 50% by 2026-12-31", "the remaining 50% by 2027-06-30", "4% discount on the apartment price"),
        'milestones' => array("Construction-milestone installments, with no discount", "25%: deposit and down payment.", "25%: topping-out.", "25%: interior fitout.", "20%: notification to collect the keys.", "5%: handover."),
        'disclaimer' => "selected apartment's final signed offer and contract",
        'obsolete' => array("2027-05-31", "2027-03-31", "May 31", "March 31", "within 3 days", "security deposit"),
        'dates' => array("2026-12-31", "2026-12-31", "2027-06-30"),
    ),
);
payment_check(Harmat_Local_Assistant::VERSION === '0.4.2', 'runtime version');
foreach ($expected as $lang => $policy) {
    $text = $payment->invoke($assistant, $lang);
    $answers[$lang] = $text;
    $plans = explode("\n\n", $text);
    payment_check(count($plans) === 4, $lang . ' three plans and final disclaimer');
    foreach (array('full', 'half', 'milestones') as $index => $plan) {
        payment_check(str_contains($plans[$index], ($index + 1) . '. '), $lang . ' numbered ' . $plan);
        foreach ($policy[$plan] as $fragment) {
            payment_check(str_contains($plans[$index], $fragment), $lang . ' ' . $plan . ': ' . $fragment);
        }
    }
    foreach (array(array('8%'), array('50%', '50%', '4%'), array('25%', '25%', '25%', '20%', '5%')) as $index => $percentages) {
        preg_match_all('/[0-9]+%/', $plans[$index], $matches);
        payment_check($matches[0] === $percentages, $lang . ' exact plan percentages ' . $index);
    }
    preg_match_all('/20[0-9]{2}(?:[.-][0-9]{2}[.-][0-9]{2}|\x{5e74}[0-9]{1,2}\x{6708}[0-9]{1,2}\x{65e5})/u', $text, $dates);
    payment_check($dates[0] === $policy['dates'], $lang . ' exact approved dates and plan order');
    payment_check(preg_match_all('/20[0-9]{2}/', $text) === 3, $lang . ' no additional project years');
    payment_check(!preg_match('/[0-9]{4}|[0-9]{1,2}[.\/-][0-9]{1,2}/', $plans[2]), $lang . ' no milestone calendar dates');
    payment_check(count(explode("\n- ", $plans[2])) === 6, $lang . ' exactly five milestone installments');
    payment_check(array_slice(explode("\n- ", $plans[2]), 1) === array_slice($policy['milestones'], 1), $lang . ' exact milestone order');
    payment_check(str_contains($plans[3], $policy['disclaimer']), $lang . ' final signed offer and contract');
    foreach (array_merge($policy['obsolete'], array('2027.05.31', '2027-05-31', '2027.03.31', '2027-03-31', '1.000.000', '1,000,000', '1000000')) as $stale) {
        payment_check(!str_contains($text, $stale), $lang . ' obsolete term absent: ' . $stale);
    }
    payment_check(!str_contains($text, '\\u') && !str_contains($text, '\\n'), $lang . ' Unicode and newlines decoded');
    $result = ask(array('hu' => 'Fizetési ütemezés', 'zh' => '付款节点', 'en' => 'payment schedule')[$lang], $lang, array('rooms' => 2));
    if ($result['answer'] !== $text || $result['cards'] || ($result['selection']['rooms'] ?? 0) !== 2) {
        throw new RuntimeException('Real payment intent/context regression');
    }
    $cases++;
}
foreach (array('hu' => '4% kedvezmeny', 'zh' => "4%\u{4f18}\u{60e0}", 'en' => '4% discount') as $lang => $question) {
    $reply = ask($question, $lang, array());
    payment_check($reply['answer'] === $answers[$lang], $lang . ' payment discount not mistaken for duty');
}
$faq = new ReflectionMethod(Harmat_Local_Assistant::class, 'faq_answer');
foreach (array('hu' => '4% illetek kedvezmeny', 'zh' => "4%\u{7a0e}\u{8d39}\u{4f18}\u{60e0}", 'en' => '4% tax discount') as $lang => $question) {
    payment_check($faq->invoke($assistant, $question, $lang, 'discount') !== $answers[$lang], $lang . ' explicit duty query retains tax guidance');
}
if (($argv[1] ?? '') === '--answers') echo json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
else echo json_encode(array('status' => 'PASS', 'payment_checks' => $cases,
    'base' => trim($previous), 'scope' => 'PURE_NO_NETWORK_NO_SUBMISSION_NO_MAIL')) . PHP_EOL;
