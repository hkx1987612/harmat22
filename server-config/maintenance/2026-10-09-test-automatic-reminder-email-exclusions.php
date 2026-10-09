<?php
// Isolated synthetic tests. No WordPress boot, database or mail transport.
define('ABSPATH', __DIR__);
$GLOBALS['test_filters'] = array();
$GLOBALS['test_exclusions'] = array();
$GLOBALS['test_legal_enabled'] = true;
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['test_filters'][$hook][] = array($callback, $priority, $accepted_args);
}
function get_option($name, $default = false) {
    if ($name === 'harmat_legal_task_reminder_email_enabled') {
        return $GLOBALS['test_legal_enabled'] ?? $default;
    }
    if ($name !== 'harmat_automatic_reminder_email_exclusions') {
        throw new RuntimeException('Unexpected option read');
    }
    return $GLOBALS['test_exclusions'];
}
function wp_mail(...$args) { throw new RuntimeException('Mail transport is forbidden'); }
function apply_filters($hook, $value) {
    foreach ($GLOBALS['test_filters'][$hook] ?? array() as $filter) {
        $value = call_user_func($filter[0], $value);
    }
    return $value;
}
require dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-automatic-reminder-email-exclusions.php';
$count = 0;
function check($actual, $expected, $label) {
    global $count;
    if ($actual !== $expected) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $count++;
}
$hooks = array('harmat_legal_task_reminder_recipients', 'harmat_sales_task_reminder_recipients');
check(array_keys($GLOBALS['test_filters']), $hooks, 'only dedicated reminder hooks');
foreach ($hooks as $hook) {
    check($GLOBALS['test_filters'][$hook][0][1], PHP_INT_MAX, 'late priority');
    $GLOBALS['test_exclusions'] = array('blocked@example.com', 'SECOND@EXAMPLE.COM', '', null);
    foreach (array(
        array(array('blocked@example.com', 'keep@example.com', 'second@example.com'), array('keep@example.com')),
        array(array('BLOCKED@EXAMPLE.COM', 'Keep@Example.com', 'keep@example.com'), array('Keep@Example.com', 'keep@example.com')),
        array(array(' blocked@example.com ', 'unblocked@example.com'), array('unblocked@example.com')),
        array(array('blocked+tag@example.com', 'xblocked@example.com'), array('blocked+tag@example.com', 'xblocked@example.com')),
        array(array(7 => 'keep@example.com'), array(7 => 'keep@example.com')),
        array(array('blocked@example.com', null, false), array(null, false)),
        array('blocked@example.com', array()),
        array('BLOCKED@EXAMPLE.COM', array()),
        array('keep@example.com', 'keep@example.com'),
        array('', ''), array(array(), array()), array(null, null), array(false, false),
        array(array('blocked@example.com', 'second@example.com'), array()),
    ) as $case) {
        check(apply_filters($hook, $case[0]), $case[1], $hook . ' recipient shape');
    }
    $GLOBALS['test_exclusions'] = 'blocked@example.com';
    check(apply_filters($hook, array('blocked@example.com', 'keep@example.com')), array('keep@example.com'), 'string option');
    foreach (array(array(), '', null, false, array('', null)) as $empty) {
        $GLOBALS['test_exclusions'] = $empty;
        $input = array(4 => 'blocked@example.com', 9 => 'keep@example.com');
        check(apply_filters($hook, $input), $input, 'no exclusions preserves array exactly');
        check(apply_filters($hook, 'blocked@example.com'), 'blocked@example.com', 'no exclusions preserves string');
    }
}
$GLOBALS['test_exclusions'] = array('blocked@example.com');
$inputs = array(array('blocked@example.com', 'future-lawyer@example.com'), array(7 => 'future@example.com'), 'future@example.com', '', array(), null, false);
foreach (array(false, null, '', '0', 0, 'false', array()) as $disabled) {
    $GLOBALS['test_legal_enabled'] = $disabled;
    foreach ($inputs as $input) {
        check(apply_filters($hooks[0], $input), array(), 'all current and future legal recipients paused');
    }
    check(apply_filters($hooks[1], array('blocked@example.com', 'keep@example.com')), array('keep@example.com'), 'sales retains exclusions only');
}
foreach (array(true, 1, '1') as $enabled) {
    $GLOBALS['test_legal_enabled'] = $enabled;
    check(apply_filters($hooks[0], array('BLOCKED@EXAMPLE.COM', 'keep@example.com')), array('keep@example.com'), 're-enable retains exclusions');
    $GLOBALS['test_exclusions'] = array();
    foreach ($inputs as $input) {
        check(apply_filters($hooks[0], $input), $input, 'explicit enable with no exclusions preserves input');
    }
    $GLOBALS['test_exclusions'] = array('blocked@example.com');
}
foreach (array('wp_mail', 'retrieve_password_message', 'harmat_sales_public_offer_mail') as $unrelated) {
    $input = array('unchanged@example.com');
    check(apply_filters($unrelated, $input), $input, 'unrelated hook untouched');
}
echo 'PASS ' . $count . " assertions\n";
