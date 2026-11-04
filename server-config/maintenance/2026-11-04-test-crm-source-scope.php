<?php
// Compare parsed methods; no WordPress load, database, or network.
if (PHP_SAPI !== 'cli') { exit(1); }
function methods_from_source($source) {
    $tokens = token_get_all(str_replace("\r\n", "\n", $source));
    $methods = array();
    for ($i = 0; $i < count($tokens); $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $start = $i; $name = null;
        for ($j = $i + 1; $j < count($tokens); $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { continue; }
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) { $name = $tokens[$j][1]; }
            break;
        }
        if ($name === null) { continue; }
        $depth = 0; $opened = false; $body = '';
        for (; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $body .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { $depth++; $opened = true; }
            if ($token === '}') { $depth--; if ($opened && $depth === 0) { break; } }
        }
        if (isset($methods[$name])) { throw new RuntimeException('Duplicate method'); }
        $methods[$name] = $body;
    }
    return $methods;
}
$root = dirname(__DIR__, 2);
$relative = 'wp-plugins/harmat-sales-manager/harmat-sales-manager.php';
chdir($root);
$base = $argv[1] ?? '7877476';
if (!preg_match('/^[a-f0-9]{7,40}$/', $base)) { throw new RuntimeException('Exact Git revision required'); }
$old = methods_from_source(shell_exec('git show ' . $base . ':' . $relative));
$new = methods_from_source(file_get_contents($root . '/' . $relative));
$allowed = array('render_sales_portal', 'render_sales_portal_nav', 'sales_portal_allowed_views',
    'render_sales_traffic_strip', 'render_sales_traffic_summary', 'render_sales_portal_dashboard',
    'render_sales_portal_traffic', 'render_sales_portal_inquiry_summary_row',
    'render_sales_portal_clients', 'sales_portal_css', 'sales_portal_chrome_css');
$changed = array(); $unchanged = 0;
foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $name) {
    if (isset($old[$name], $new[$name]) && $old[$name] === $new[$name]) { $unchanged++; continue; }
    if (!in_array($name, $allowed, true)) { throw new RuntimeException('Unapproved method change: ' . $name); }
    $changed[] = $name;
}
echo json_encode(array('status' => 'PASS', 'unchanged_methods' => $unchanged, 'ui_only_changed' => $changed), JSON_PRETTY_PRINT) . "\n";
