<?php
require __DIR__ . '/faq-router.php';

use function Harmat\AssistantOpenAI\request_body;
use function Harmat\AssistantOpenAI\response_topic;

$checks = 0;
function check(bool $condition): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException('Failed assertion ' . $checks);
}
function response(string $text, string $type = 'output_text'): array {
    return ['status' => 'completed', 'output' => [
        ['type' => 'reasoning'],
        ['type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => $type, 'text' => $text]]],
    ]];
}
foreach (['Van busz a kozelben?', '附近有公交吗？', 'Are there buses nearby?'] as $question) {
    $body = request_body('fixture-model', $question);
    check($body['input'] === $question && $body['store'] === false && !isset($body['tools']));
}
check(request_body('', 'Question') === null);
check(request_body('fixture-model', str_repeat('a', 1041)) === null);
check(request_body('fixture-model', '') === null);
check(response_topic(response('{"topic":"transport"}')) === 'transport');
foreach (['unknown', 'price', 'appointment', 'offer', 'PAYMENT'] as $topic) {
    check(response_topic(response(json_encode(['topic' => $topic]))) === null);
}
check(response_topic(response('{"topic":"contact","answer":"invented answer"}')) === null);
check(response_topic(response('{"topic":"contact"}', 'refusal')) === null);
check(response_topic(response('not JSON')) === null);
check(response_topic(['status' => 'incomplete', 'output' => []]) === null);
check(response_topic(['status' => 'completed', 'output' => [['type' => 'function_call']]]) === null);
$multiple = response('{"topic":"contact"}');
$multiple['output'][] = $multiple['output'][1];
check(response_topic($multiple) === null);
echo "PASS {$checks} offline assertions\n";
