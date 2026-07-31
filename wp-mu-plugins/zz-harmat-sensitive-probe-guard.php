<?php
/**
 * Plugin Name: Harmat Sensitive Probe Guard
 * Description: Stops common secret, repository, database, and backup probes before WordPress renders a full 404 page.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_sensitive_probe_is_blocked_path(string $path): bool
{
    $patterns = array(
        '#(^|/)\.env(\..*)?$#i',
        '#(^|/)\.(git|svn|hg)(/|$)#i',
        '#(^|/)\.DS_Store$#i',
        '#(^|/)wp-config\.php([._~-].*)?$#i',
        '#(^|/)(debug\.log|error_log|php\.ini|\.user\.ini|composer\.(json|lock))$#i',
        '#\.(sql|sqlite|sqlite3|bak|backup|old|orig|save)$#i',
        '#(^|/)(backup|database|db|site-backup|website-backup|wordpress-backup|public_html)([-_.][^/]*)?\.(zip|7z|tar|tgz|gz)$#i',
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $path) === 1) {
            return true;
        }
    }

    return false;
}

add_action('muplugins_loaded', function (): void {
    $request_uri = isset($_SERVER['REQUEST_URI'])
        ? (string) wp_unslash($_SERVER['REQUEST_URI'])
        : '';
    $path = (string) parse_url($request_uri, PHP_URL_PATH);
    $path = rawurldecode($path);

    if (!harmat_sensitive_probe_is_blocked_path($path)) {
        return;
    }

    status_header(403);
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    header('Content-Length: 0', true);
    exit;
}, -1000);
