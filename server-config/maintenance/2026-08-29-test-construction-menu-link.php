<?php

declare(strict_types=1);

define('ABSPATH', __DIR__);

$test_is_admin = false;
$test_actions = array();

function is_admin(): bool
{
    global $test_is_admin;
    return $test_is_admin;
}

function wp_doing_ajax(): bool { return false; }
function wp_is_json_request(): bool { return false; }
function is_feed(): bool { return false; }
function is_robots(): bool { return false; }

function add_action(string $tag, callable $callback, int $priority = 10): void
{
    global $test_actions;
    $test_actions[$tag][$priority][] = $callback;
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-construction-menu-link.php';

check(harmat_construction_menu_link_is_public(), 'Public request was not detected.');
$test_is_admin = true;
check(!harmat_construction_menu_link_is_public(), 'Admin request was incorrectly detected.');
$test_is_admin = false;

$callback = $test_actions['wp_footer'][1010][0] ?? null;
check(is_callable($callback), 'Footer callback was not registered.');
ob_start();
$callback();
$output = (string) ob_get_clean();
check(substr_count($output, 'id="harmat-construction-menu-link"') === 1, 'Runtime marker is missing or duplicated.');
check(strpos($output, "link.href='/epitesi-naplo/'") !== false, 'Construction URL is missing.');
check(strpos($output, "link.textContent='Építési napló'") !== false, 'Hungarian menu label is missing.');
check(strpos($output, '/galeria/') === false, 'The runtime must not replace the existing gallery link.');
check(strpos($output, '/elerhetosegeink/') !== false, 'Contact insertion anchor is missing.');

echo "Construction menu-link tests passed.\n";
