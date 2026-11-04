<?php
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/2026-09-10-test-assistant.php';

function harmat_cu_records() {
    return $GLOBALS['fixture_journal'] ?? [['id' => '2026-10-30', 'title' => [
        'hu' => 'Helyszíni felvétel – 2026. október 30.', 'zh' => '施工现场实拍 · 2026年10月30日',
    ]]];
}

$cases = [
    ['zh', 'progress', '施工现在进展到哪一步了？', '#construction-2026-10-30'],
    ['hu', 'progress', 'Hol tart most az építkezés?', '#construction-2026-10-30'],
    ['zh', 'progress', '最新工地视频在哪里看？', '#construction-2026-10-30'],
    ['hu', 'progress', 'Van friss helyszíni videó?', '#construction-2026-10-30'],
    ['zh', 'health', '附近有医院吗？', 'Bajcsy-Zsilinszky'],
    ['hu', 'health', 'Melyik kórház van a közelben?', 'Bajcsy-Zsilinszky'],
    ['zh', 'health', '周围有药店或诊所吗？', '药房'],
    ['hu', 'health', 'Van a környéken patika vagy rendelő?', 'gyógyszertár'],
    ['zh', 'shopping', '附近哪里买东西？', 'KÖKI Terminál'],
    ['hu', 'shopping', 'Hol lehet bevásárolni?', 'KÖKI Terminál'],
    ['zh', 'shopping', '离商场远不远？', '直线距离'],
    ['hu', 'shopping', 'Milyen bevásárlóközpontok vannak a környéken?', 'légvonalban'],
    ['zh', 'rail', '附近有地铁站吗？', 'Kőbánya-Kispest'],
    ['hu', 'rail', 'Melyik metróállomás van a közelben?', 'Kőbánya-Kispest'],
    ['zh', 'rail', '周边有没有火车站？', 'Kőbánya alsó'],
    ['hu', 'rail', 'Van vasútállomás a környéken?', 'Kőbánya alsó'],
    ['zh', 'park', 'Óhegy 公园是小区自己的花园吗？', '公共公园'],
    ['hu', 'park', 'Az Óhegy park a lakópark saját kertje?', 'közpark'],
    ['zh', 'park', '附近有散步的公园吗？', 'Óhegy park'],
    ['hu', 'park', 'Hol lehet sétálni a közelben?', 'Óhegy park'],
    ['zh', 'transport', '不开车的话，平时出门方便吗？', 'BudapestGO'],
    ['hu', 'transport', 'Autó nélkül is könnyű hazajutni?', 'BudapestGO'],
    ['zh', 'technical', '请给我项目的技术说明', '热泵'],
    ['hu', 'technical', 'Hol találom a műszaki leírást?', 'hőszivattyú'],
];

$failed = [];
foreach ($cases as [$lang, $label, $question, $expected]) {
    $r = ask($question, $lang);
    if (!str_contains($r['answer'], $expected) || $r['event'] === 'unknown_question' || $r['handoff']) {
        $failed[] = [$lang, $label, $question, $r['event'], $r['intent']];
    }
}
echo json_encode(['faq_cases' => count($cases), 'passed' => count($cases) - count($failed), 'failures' => $failed], JSON_UNESCAPED_UNICODE) . PHP_EOL;
if (in_array('--audit', $argv, true)) exit;
check(!$failed, 'all expanded bilingual FAQ examples');
foreach ([['zh', '我要报价', 'offer'], ['hu', 'Árajánlatot kérek', 'offer'],
          ['zh', '预约看房', 'appointment'], ['hu', 'Időpontot foglalok', 'appointment']] as [$lang, $question, $intent]) {
    $r = ask($question, $lang);
    check($r['intent'] === $intent && !empty($r['handoff']) && !isset($r['openai_ticket']), 'protected flow ' . $intent);
}
foreach ([['zh', '有结构安全检测报告吗？'], ['hu', 'Van helyszíni talajmechanikai szakvélemény?']] as [$lang, $question]) {
    $r = ask($question, $lang);
    check($r['event'] === 'unknown_question' && !empty($r['handoff']), 'unsupported engineering claim falls back');
}
$GLOBALS['fixture_journal'] = [];
check(!str_contains(ask('施工进展', 'zh')['answer'], '#construction-2026-10-30'), 'no stale date when journal empty');
$GLOBALS['fixture_journal'] = [['id' => '2026-11-01', 'title' => ['hu' => 'Új felvétel', 'zh' => '更新现场影像']]];
check(str_contains(ask('施工进展', 'zh')['answer'], '#construction-2026-11-01'), 'uses newest published record');
echo "PASS 24 bilingual FAQ cases and 8 boundaries\n";
