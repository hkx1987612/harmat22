<?php
require __DIR__ . '/faq-router.php';
if (PHP_SAPI !== 'cli') exit(1);

if (($argv[1] ?? '') === '--parse') {
    $response = json_decode(stream_get_contents(STDIN), true);
    echo json_encode(is_array($response) ? Harmat\AssistantOpenAI\parse_topic($response) : null);
    exit;
}

$model = $argv[1] ?? 'gpt-5.4-mini';
$cases = [
    ['zh', 'transport', '从小区出门，不开车的话怎么坐公共交通？'],
    ['zh', 'education', '小朋友明年上学，附近有哪些学校？'],
    ['zh', 'technical', '房子的供暖和制冷用什么系统？'],
    ['zh', 'construction', '最近工程做到哪一步了？'],
    ['zh', 'unknown', '这套能打折吗？直接给我一个最低价。'],
    ['zh', 'unknown', '帮我预约周六下午看房。'],
    ['hu', 'transport', 'Autó nélkül milyen járatokkal lehet hazajutni?'],
    ['hu', 'location', 'Merre találom a Harmat értékesítési irodáját?'],
    ['hu', 'contact', 'Milyen e-mail-címen érhetem el az értékesítőket?'],
    ['hu', 'construction', 'Hol tart most az építkezés?'],
    ['hu', 'unknown', 'Küldjön hivatalos árajánlatot erre a lakásra.'],
    ['hu', 'unknown', 'Foglaljon időpontot nekem holnapra.'],
];
$result = [];
foreach ($cases as [$lang, $expected, $question]) {
    $body = Harmat\AssistantOpenAI\request_body($model, $question);
    $body['reasoning'] = ['effort' => 'none'];
    $result[] = ['lang' => $lang, 'expected' => $expected, 'request' => $body];
}
echo json_encode($result, JSON_THROW_ON_ERROR);
