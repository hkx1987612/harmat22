<?php
// CLI-only, dependency-free fixture. Never bootstrap WordPress or modify assets.
// Run after final pins: php this-file.php /absolute/path/to/candidate.php
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WPINC')
    || class_exists('WP_Post', false) || isset($GLOBALS['wpdb'])) {
    fwrite(STDERR, "Run only in an isolated PHP CLI process, without WordPress.\n");
    exit(2);
}
if (!isset($argv[1]) || !is_string($argv[1]) || strpos($argv[1], '://') !== false
    || !is_file($argv[1]) || !is_readable($argv[1])) {
    fwrite(STDERR, "Usage: php this-file.php /local/path/to/candidate.php\n");
    exit(2);
}
$candidate = realpath($argv[1]);
$context = isset($argv[2]) ? $argv[2] : 'main';
$contexts = array('main', 'rest-true', 'rest-false', 'cli-true', 'cli-false', 'no-preview', 'public');
if ($candidate === false || $candidate === __FILE__ || count($argv) > 3
    || !in_array($context, $contexts, true)) {
    fwrite(STDERR, "Invalid candidate or internal fixture context.\n");
    exit(2);
}
foreach (array('REST_REQUEST', 'WP_CLI', 'DONOTCACHEPAGE') as $constant) {
    if (defined($constant)) {
        fwrite(STDERR, 'Predefined constant prevents isolation: ' . $constant . PHP_EOL);
        exit(2);
    }
}
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$GLOBALS['h22_counts'] = array('checks' => 0, 'failures' => 0, 'cases' => 0);
$GLOBALS['h22_hooks'] = array();

function h22_check($condition, $label) {
    $GLOBALS['h22_counts']['checks']++;
    if (!$condition) {
        $GLOBALS['h22_counts']['failures']++;
        fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
    }
}

function h22_test($label, $callback) {
    $GLOBALS['h22_counts']['cases']++;
    $level = ob_get_level();
    try {
        $callback();
    } catch (Throwable $error) {
        h22_check(false, $label . ': ' . get_class($error) . ': ' . $error->getMessage());
    } finally {
        while (ob_get_level() > $level) { ob_end_clean(); }
    }
}

// Declare stubs only after rejecting a real WordPress process.
function h22_define_stubs() {
    class WP_Post {
        public $ID = 42;
        public $post_status = 'publish';
        public $post_password = '';
        public $post_type = 'page';
        public $post_name = 'harmat-lakopark';
        public function __construct($fields = array()) {
            foreach ($fields as $key => $value) { $this->$key = $value; }
        }
    }
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['h22_hooks'][] = array('action', $tag, $callback, $priority, $accepted_args);
    }
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['h22_hooks'][] = array('filter', $tag, $callback, $priority, $accepted_args);
    }
    function is_admin() { return $GLOBALS['h22_state']['admin']; }
    function wp_doing_ajax() { return $GLOBALS['h22_state']['ajax']; }
    function wp_doing_cron() { return $GLOBALS['h22_state']['cron']; }
    function is_feed() { return $GLOBALS['h22_state']['feed']; }
    function is_search() { return $GLOBALS['h22_state']['search']; }
    function is_embed() { return $GLOBALS['h22_state']['embed']; }
    function is_preview() { return $GLOBALS['h22_state']['preview']; }
    function is_404() { return $GLOBALS['h22_state']['not_found']; }
    function get_queried_object() { return $GLOBALS['h22_state']['post']; }
    function current_user_can($capability) {
        $GLOBALS['h22_cap_calls'][] = $capability;
        return in_array($capability, $GLOBALS['h22_state']['caps'], true);
    }
    function wp_unslash($value) {
        if (!is_string($value)) { throw new RuntimeException('Non-string wp_unslash input'); }
        return stripslashes($value);
    }
    function plugins_url($path = '', $plugin = '') {
        if ($plugin !== $GLOBALS['h22_candidate']) {
            throw new RuntimeException('Asset URL not relative to candidate');
        }
        return 'https://fixture.invalid/wp-content/mu-plugins/' . ltrim($path, '/');
    }
    function wp_enqueue_style($handle, $src = '', $deps = array(), $version = false, $media = 'all') {
        $GLOBALS['h22_enqueues'][] = array('style', $handle, $src, $deps, $version, $media);
    }
    function wp_enqueue_script($handle, $src = '', $deps = array(), $version = false, $footer = false) {
        $GLOBALS['h22_enqueues'][] = array('script', $handle, $src, $deps, $version, $footer);
    }
    function wp_json_encode($value, $flags = 0, $depth = 512) {
        return json_encode($value, $flags, $depth);
    }
    function wp_mail(...$args) { throw new RuntimeException('Mail forbidden'); }
    function wp_remote_get(...$args) { throw new RuntimeException('Network forbidden'); }
    function wp_remote_post(...$args) { throw new RuntimeException('Network forbidden'); }
    function wp_remote_request(...$args) { throw new RuntimeException('Network forbidden'); }
    function register_rest_route(...$args) { throw new RuntimeException('REST registration forbidden'); }
    function get_option(...$args) { throw new RuntimeException('Database access forbidden'); }
    function update_option(...$args) { throw new RuntimeException('Database writes forbidden'); }
    function wp_insert_post(...$args) { throw new RuntimeException('Post writes forbidden'); }
    function update_post_meta(...$args) { throw new RuntimeException('Metadata writes forbidden'); }
}

function h22_reset($changes = array()) {
    $GLOBALS['h22_state'] = array_replace(array(
        'admin' => false, 'ajax' => false, 'cron' => false, 'feed' => false,
        'search' => false, 'embed' => false, 'preview' => false, 'not_found' => false,
        'post' => new WP_Post(), 'caps' => array('manage_options'),
        'method' => 'GET', 'uri' => '/harmat-lakopark/?h22_language_test=1',
        'query' => array('h22_language_test' => '1'),
    ), $changes);
    $_GET = $GLOBALS['h22_state']['query'];
    // A POST/REQUEST flag must never substitute for the explicit GET flag.
    $_POST = array('h22_language_test' => '1', 'unchanged_form_value' => 'sentinel');
    $_REQUEST = array_merge($_POST, $_GET);
    $_SERVER = array('REQUEST_URI' => $GLOBALS['h22_state']['uri']);
    if ($GLOBALS['h22_state']['method'] !== null) {
        $_SERVER['REQUEST_METHOD'] = $GLOBALS['h22_state']['method'];
    }
    $GLOBALS['h22_cap_calls'] = array();
    $GLOBALS['h22_enqueues'] = array();
}

function h22_snapshot() {
    return serialize(array($_GET, $_POST, $_REQUEST, $_SERVER, $GLOBALS['h22_state']));
}

function h22_case($label, $changes, $expected) {
    h22_test($label, function () use ($label, $changes, $expected) {
        h22_reset($changes);
        $before = h22_snapshot();
        h22_check(harmat_internal_language_scope() === $expected, $label . ': exact scope boolean');
        ob_start();
        harmat_internal_language_enqueue();
        $enqueue_output = ob_get_clean();
        ob_start();
        harmat_internal_language_controls();
        $controls = ob_get_clean();
        h22_check($enqueue_output === '', $label . ': enqueue produces no markup');
        if (!$expected) {
            h22_check($GLOBALS['h22_enqueues'] === array(), $label . ': no denied assets');
            h22_check($controls === '', $label . ': no denied controls/config');
        } else {
            h22_check(count($GLOBALS['h22_enqueues']) === 4, $label . ': four pinned assets');
            h22_check(strpos($controls, 'id="harmat-internal-language"') !== false,
                $label . ': preview controls present');
        }
        h22_check(h22_snapshot() === $before, $label . ': no request/post/value mutation');
        h22_check(array_diff($GLOBALS['h22_cap_calls'], array('manage_options')) === array(),
            $label . ': only administrator capability queried');
    });
}

function h22_children($candidate) {
    foreach (array('rest-true', 'rest-false', 'cli-true', 'cli-false', 'no-preview') as $context) {
        h22_test('isolated ' . $context, function () use ($candidate, $context) {
            if (!function_exists('proc_open')) {
                throw new RuntimeException('proc_open is required for independent constant cases');
            }
            $pipes = array();
            $process = proc_open(array(PHP_BINARY, '-n', __FILE__, $candidate, $context),
                array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
            if (!is_resource($process)) { throw new RuntimeException('Cannot start isolated PHP'); }
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            $counts = json_decode($stdout, true);
            if ($stderr !== '') { fwrite(STDERR, '[' . $context . '] ' . $stderr); }
            h22_check(is_array($counts) && isset($counts['checks'], $counts['failures'], $counts['cases'])
                && $counts['checks'] > 0, $context . ': valid child assertion counts');
            h22_check($status === 0 && is_array($counts) && $counts['failures'] === 0,
                $context . ': isolated child passes');
            if (is_array($counts) && isset($counts['checks'], $counts['failures'], $counts['cases'])) {
                foreach (array('checks', 'failures', 'cases') as $key) {
                    $GLOBALS['h22_counts'][$key] += $counts[$key];
                }
                echo $context . ': ' . $counts['checks'] . ' checks, ' . $counts['failures'] . " failures\n";
            }
        });
    }
}

h22_define_stubs();
define('ABSPATH', __DIR__ . '/__fixture_no_wordpress__/');
if (strpos($context, 'rest-') === 0) { define('REST_REQUEST', $context === 'rest-true'); }
if (strpos($context, 'cli-') === 0) { define('WP_CLI', $context === 'cli-true'); }
$GLOBALS['h22_candidate'] = $candidate;
h22_reset($context === 'no-preview' ? array('query' => array()) : array());
h22_test('candidate bootstrap', function () use ($candidate) {
    ob_start();
    require $candidate;
    h22_check(ob_get_clean() === '', 'candidate include has no output');
    h22_check($GLOBALS['h22_enqueues'] === array(), 'include registers but does not enqueue');
});

h22_test('public flag and registered hooks', function () use ($context) {
    h22_check(harmat_internal_language_public_enabled() === ($context === 'public'), 'explicit preview/public phase matches candidate');
    h22_check($GLOBALS['h22_hooks'] === array(
        array('action', 'wp_enqueue_scripts', 'harmat_internal_language_enqueue', 1500, 1),
        array('action', 'wp_footer', 'harmat_internal_language_controls', 18, 1),
    ), 'exactly the two intended presentation hooks');
    foreach ($GLOBALS['h22_hooks'] as $hook) {
        h22_check(is_callable($hook[2]), 'registered callback callable: ' . $hook[2]);
    }
});

if ($context === 'public') {
    foreach (array('GET', 'HEAD') as $method) {
        $base = array('method' => $method, 'query' => array(), 'caps' => array());
        h22_case('public anonymous ' . $method, $base, true);
        foreach (array('admin', 'ajax', 'cron', 'feed', 'search', 'embed', 'preview', 'not_found') as $flag) {
            h22_case('public excluded ' . $method . ' ' . $flag, array_replace($base, array($flag => true)), false);
        }
        foreach (array('/sales/', '/agent/', '/client/', '/lawyer/', '/wp-admin/', '/wp-json/', '/s%61les/') as $uri) {
            h22_case('public private ' . $method . ' ' . $uri, array_replace($base, array('uri' => $uri)), false);
        }
        foreach (array('draft', 'private', 'trash') as $status) {
            h22_case('public nonpublish ' . $method . ' ' . $status, array_replace($base,
                array('post' => new WP_Post(array('post_status' => $status)))), false);
        }
        h22_case('public private slug ' . $method, array_replace($base,
            array('post' => new WP_Post(array('post_name' => 'sales')), 'uri' => '/alias/')), false);
        h22_case('public password ' . $method, array_replace($base,
            array('post' => new WP_Post(array('post_password' => 'secret')))), false);
        h22_case('public non-public type ' . $method, array_replace($base,
            array('post' => new WP_Post(array('post_type' => 'harmat_offer_lead')))), false);
    }
    foreach (array('POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', null) as $method) {
        h22_case('public nonread method', array('method' => $method), false);
    }
    $counts = $GLOBALS['h22_counts'];
    echo 'PUBLIC TOTAL: ' . $counts['checks'] . ' checks, ' . $counts['cases'] . ' cases, '
        . $counts['failures'] . ' failures' . PHP_EOL;
    exit($counts['failures'] === 0 ? 0 : 1);
}

if ($context !== 'main') {
    h22_test('bootstrap cache policy', function () use ($context) {
        h22_check($context === 'no-preview' ? !defined('DONOTCACHEPAGE')
            : defined('DONOTCACHEPAGE') && DONOTCACHEPAGE === true, 'preview-only bootstrap cache opt-out');
    });
    foreach (array('GET', 'HEAD') as $method) {
        $allowed = in_array($context, array('rest-false', 'cli-false', 'no-preview'), true);
        h22_case($context . ' ' . $method, array('method' => $method), $allowed);
        h22_case($context . ' no flag ' . $method, array('method' => $method, 'query' => array()), false);
        h22_case($context . ' no capability ' . $method, array('method' => $method, 'caps' => array()), false);
    }
    echo json_encode($GLOBALS['h22_counts']) . PHP_EOL;
    exit($GLOBALS['h22_counts']['failures'] === 0 ? 0 : 1);
}

h22_test('real sibling readiness', function () use ($candidate) {
    $pins = harmat_internal_language_assets();
    h22_check(array_keys($pins) === array('runtime.js', 'runtime.css', 'bootstrap.js', 'patterns.js', 'zh-Hans.json'),
        'exact asset manifest');
    $directory = dirname($candidate) . '/harmat-internal-language';
    foreach ($pins as $file => $hash) {
        h22_check(is_string($hash) && preg_match('/\A[0-9a-f]{64}\z/', $hash) === 1,
            'final SHA256 pin required: ' . $file);
        $path = $directory . '/' . $file;
        $readable = is_dir($directory) && !is_link($directory)
            && is_file($path) && !is_link($path) && is_readable($path);
        h22_check($readable, 'real regular readable sibling: ' . $file);
        h22_check($readable && hash_equals($hash, hash_file('sha256', $path)), 'real sibling hash: ' . $file);
    }
    // Do not rename, remove, copy, corrupt, or repin the original siblings.
    h22_check(harmat_internal_language_ready() === true, 'ready accepts the final real sibling assets');
    h22_check(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE === true, 'preview bootstrap disables page cache');
});

foreach (array('GET', 'HEAD') as $method) {
    $base = array('method' => $method);
    foreach (array('/', '/harmat-lakopark/', '/elerhetosegeink/', '/lakaskereso/',
        '/virtualis-lakasvalaszto/', '/magunkrol/', '/property/a1-f-l1/') as $uri) {
        foreach (array('page', 'property') as $type) {
            h22_case($method . ' allowed ' . $type . ' ' . $uri,
                array_replace($base, array('uri' => $uri, 'post' => new WP_Post(array('post_type' => $type)))), true);
        }
    }
    h22_case($method . ' anonymous public visit', array_replace($base, array('query' => array(), 'caps' => array())), false);
    h22_case($method . ' admin without flag', array_replace($base, array('query' => array())), false);
    foreach (array(array(), array('read'), array('edit_posts'), array('edit_pages'),
        array('administrator'), array('manage_options_extra')) as $index => $caps) {
        h22_case($method . ' capability ' . $index, array_replace($base, array('caps' => $caps)), false);
    }
    foreach (array('admin', 'ajax', 'cron', 'feed', 'search', 'embed', 'preview', 'not_found') as $flag) {
        h22_case($method . ' excluded ' . $flag, array_replace($base, array($flag => true)), false);
    }
    foreach (array('/s%61les/', '/%61gent/', '/lawyer%2Fnested') as $uri) {
        h22_case($method . ' encoded private ' . $uri, array_replace($base, array('uri' => $uri)), false);
    }
    foreach (array('sales', 'lawyer') as $slug) {
        h22_case($method . ' queried private slug ' . $slug, array_replace($base,
            array('post' => new WP_Post(array('post_name' => $slug)), 'uri' => '/public-alias/')), false);
    }
    foreach (array('sales', 'agent', 'client', 'customer', 'ugyfel', 'lawyer', 'wp-admin', 'wp-json', 'wp-login.php') as $prefix) {
        foreach (array('/' . $prefix, '/' . $prefix . '/', '/' . $prefix . '/nested',
            '/' . strtoupper($prefix) . '/nested', '/' . $prefix . '?h22_language_test=1') as $uri) {
            h22_case($method . ' private ' . $uri, array_replace($base, array('uri' => $uri)), false);
        }
        h22_case($method . ' exact prefix boundary ' . $prefix,
            array_replace($base, array('uri' => '/' . $prefix . '-public/')), true);
    }
    foreach (array('draft', 'private', 'pending', 'future', 'trash', 'inherit', 'auto-draft', 'Publish', '', null) as $index => $status) {
        h22_case($method . ' non-public status ' . $index,
            array_replace($base, array('post' => new WP_Post(array('post_status' => $status)))), false);
    }
    foreach (array('secret', '0', ' ', false, null, 0) as $index => $password) {
        h22_case($method . ' nonempty/non-string password ' . $index,
            array_replace($base, array('post' => new WP_Post(array('post_password' => $password)))), false);
    }
    foreach (array('post', 'attachment', 'revision', 'nav_menu_item', 'offer', 'Page', 'PROPERTY', '', null, array('page')) as $index => $type) {
        h22_case($method . ' excluded post type ' . $index,
            array_replace($base, array('post' => new WP_Post(array('post_type' => $type)))), false);
    }
    foreach (array(null, false, 42, array('post_status' => 'publish'), new stdClass(),
        (object) array('post_status' => 'publish', 'post_type' => 'page', 'post_password' => '')) as $index => $post) {
        h22_case($method . ' not WP_Post ' . $index, array_replace($base, array('post' => $post)), false);
    }
    $invalid_values = array(null, false, true, 0, 1, 1.0, '', '0', '01', '1.0', 'true', 'yes',
        'hu', 'zh-Hans', ' 1', '1 ', "1\n", "\t1", "1\0", '%31', '1&other=1',
        array(), array('1'), array('value' => '1'), array(array('1')), new stdClass());
    foreach ($invalid_values as $index => $value) {
        h22_case($method . ' invalid flag value ' . $index,
            array_replace($base, array('query' => array('h22_language_test' => $value))), false);
    }
    foreach (array(array('H22_language_test' => '1'), array('h22_language_test[]' => '1'),
        array('language' => '1'), array('h22_language_test ' => '1')) as $index => $query) {
        h22_case($method . ' wrong query key ' . $index, array_replace($base, array('query' => $query)), false);
    }
    h22_case($method . ' unrelated query preserved', array_replace($base,
        array('query' => array('h22_language_test' => '1', 'other' => array('unchanged' => 'value')))), true);
}
foreach (array('POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT', 'get', 'head', '', null, false, 0, array('GET')) as $index => $method) {
    h22_case('excluded/missing request method ' . $index, array('method' => $method), false);
}

h22_test('allowed preview asset and controls contract', function () {
    h22_reset();
    $before = h22_snapshot();
    $pins = harmat_internal_language_assets();
    $base = 'https://fixture.invalid/wp-content/mu-plugins/harmat-internal-language/';
    ob_start();
    harmat_internal_language_enqueue();
    h22_check(ob_get_clean() === '', 'enqueue silent');
    h22_check($GLOBALS['h22_enqueues'] === array(
        array('style', 'harmat-internal-language', $base . 'runtime.css', array(), substr($pins['runtime.css'], 0, 16), 'all'),
        array('script', 'harmat-internal-language', $base . 'runtime.js', array(), substr($pins['runtime.js'], 0, 16), true),
        array('script', 'harmat-language-patterns', $base . 'patterns.js', array(), substr($pins['patterns.js'], 0, 16), true),
        array('script', 'harmat-internal-language-boot', $base . 'bootstrap.js',
            array('harmat-internal-language', 'harmat-language-patterns'), substr($pins['bootstrap.js'], 0, 16), true),
    ), 'exact local assets, dependencies, pin versions, and footer loading');
    ob_start();
    harmat_internal_language_controls();
    $html = ob_get_clean();
    h22_check(substr_count($html, 'id="harmat-internal-language"') === 1, 'one language group');
    h22_check(substr_count($html, '<button ') === 2, 'two language buttons');
    h22_check(strpos($html, 'data-hil-language="hu" type="button" lang="hu" aria-pressed="true"') !== false,
        'Hungarian is default and button cannot submit');
    h22_check(strpos($html, 'data-hil-language="zh-Hans" type="button" lang="zh-Hans" aria-pressed="false" disabled') !== false,
        'Chinese starts disabled and cannot submit');
    h22_check(strpos($html, 'data-hil-status role="status"') !== false, 'accessible status retained');
    $matched = preg_match('~<script type="application/json" id="harmat-internal-language-config">(.*?)</script>~s', $html, $matches);
    h22_check($matched === 1, 'inert JSON config exists');
    h22_check($matched === 1 && json_decode($matches[1], true) === array(
        'dictionary' => $base . 'zh-Hans.json?v=' . substr($pins['zh-Hans.json'], 0, 16),
    ), 'config contains only pinned local dictionary URL');
    h22_check(h22_snapshot() === $before, 'asset/control functions preserve all request values and post');
});

$local = $GLOBALS['h22_counts'];
echo 'main: ' . $local['checks'] . ' checks, ' . $local['failures'] . " failures\n";
h22_children($candidate);
$counts = $GLOBALS['h22_counts'];
echo 'TOTAL: ' . $counts['checks'] . ' checks, ' . $counts['cases'] . ' cases, '
    . $counts['failures'] . ' failures; no WordPress bootstrap or fixture file writes.' . PHP_EOL;
exit($counts['failures'] === 0 ? 0 : 1);
