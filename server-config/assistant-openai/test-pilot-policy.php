<?php
if (PHP_SAPI !== 'cli') exit(1);
define('ABSPATH', __DIR__);
function wp_salt($unused) { return 'test-only-salt'; }
require $argv[1] ?? dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-openai-pilot.php';

function require_check($condition, $label) {
    if (!$condition) throw new RuntimeException($label);
}
$now = strtotime('2026-11-03T11:00:00+01:00');
$question = '附近有没有适合小朋友的学校？';
$stamp = $now . '.' . str_repeat('a', 32);
$ticket = $stamp . '.' . harmat_openai_mac($question, 'zh', $stamp);
require_check(harmat_openai_valid_ticket($question, 'zh', $ticket, $now), 'valid consent');
require_check(!harmat_openai_valid_ticket($question, 'zh', $ticket, $now + 301), 'expired consent');
require_check(!harmat_openai_valid_ticket('different', 'zh', $ticket, $now), 'different question');
require_check(!harmat_openai_valid_ticket($question, 'hu', $ticket, $now), 'different language');
require_check(harmat_openai_eligible($question, 'zh'), 'synthetic FAQ eligible');
foreach (['我叫张三', '预约看房', '我要报价', 'abc@example.test', '+36 30 123 4567', 'https://example.test'] as $input) {
    require_check(!harmat_openai_eligible($input, 'zh'), 'blocked contact or protected intent');
}
require_check(!harmat_openai_eligible($question, 'en'), 'pilot languages only');
$path = tempnam(sys_get_temp_dir(), 'hcu');
try {
    file_put_contents($path, '{"date":"2026-11-03","tickets":[]}');
    for ($i = 0; $i < 30; $i++) {
        require_check(harmat_openai_reserve($path, 'ticket-' . $i, '2026-11-03'), 'reserve within limit');
        require_check(!harmat_openai_reserve($path, 'ticket-' . $i, '2026-11-03'), 'refuse replay');
    }
    require_check(!harmat_openai_reserve($path, 'another', '2026-11-03'), 'hard daily cap');
    require_check(harmat_openai_reserve($path, 'new-day', '2026-11-04'), 'next day');
    require_check(!harmat_openai_reserve($path, 'backdated', '2026-11-03'), 'cannot reset backwards');
    file_put_contents($path, 'broken');
    require_check(!harmat_openai_reserve($path, 'another', '2026-11-04'), 'corrupt quota fails closed');
} finally {
    unlink($path);
}
echo "PASS pilot consent, quota, replay and fail-closed checks\n";
