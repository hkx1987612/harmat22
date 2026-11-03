<?php
if (!defined('WP_CLI') || !WP_CLI) exit(1);
require __DIR__ . '/pilot-module.php';
if (!harmat_openai_ready()) throw new RuntimeException('Private runtime not ready');
foreach ([['zh', '附近怎么坐公交？'], ['hu', 'Milyen járatok vannak a közelben?']] as [$lang, $question]) {
    $ticket = harmat_openai_ticket($question, $lang);
    if (!$ticket || harmat_openai_topic($question, $lang, $ticket) !== 'transport'
        || harmat_openai_topic($question, $lang, $ticket) !== null) {
        throw new RuntimeException('Native API or single-use check failed');
    }
}
add_filter('pre_http_request', static function ($response, $args, $url) {
    return $url === 'https://api.openai.com/v1/responses' ? new WP_Error('synthetic_timeout') : $response;
}, 10, 3);
$question = '附近有哪些学校？';
$ticket = harmat_openai_ticket($question, 'zh');
if (!$ticket || harmat_openai_topic($question, 'zh', $ticket) !== null) throw new RuntimeException('Fallback failed');
echo 'PILOT_NATIVE_PASS';
