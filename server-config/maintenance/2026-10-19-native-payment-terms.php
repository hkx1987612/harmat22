<?php
// Private native theme render. The candidate lives in an isolated namespace,
// never alongside an installed second set of global harmat_ppc_* functions.
namespace HarmatPaymentTermsCandidate {
    function add_filter(...$args) { $GLOBALS['payment_candidate_hooks'][] = $args; }
    function add_action(...$args) { $GLOBALS['payment_candidate_hooks'][] = $args; }
}
namespace {
    if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WP_CLI')) exit(1);
    $candidate = $argv[1] ?? '';
    $route = $argv[2] ?? '';
    $mode = $argv[3] ?? '';
    $routes = array('/property/a1-1-l2/', '/property/a1-4-l5/', '/finanszirozas/');
    if (!preg_match('~\A/home/harmath2/codex-backups/public-payment-terms-[a-f0-9]{32}/candidate-module\.php\z~D', $candidate)
        || !is_file($candidate) || is_link($candidate) || realpath($candidate) !== $candidate
        || (fileperms(dirname($candidate)) & 0077) || (fileperms($candidate) & 0077)
        || !in_array($route, $routes, true) || !in_array($mode, array('original', 'candidate'), true)) exit(2);
    umask(0077);
    define('DISABLE_WP_CRON', true);
    define('DONOTCACHEPAGE', true);
    define('WP_USE_THEMES', true);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'harmat22.hu';
    $_SERVER['REQUEST_URI'] = $route;
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = '443';
    $_SERVER['HTTP_ACCEPT'] = 'text/html';
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
    require '/home/harmath2/public_html/wp-load.php';
    wp();
    http_response_code(200);
    $live = '/home/harmath2/public_html/wp-content/mu-plugins/zz-harmat-public-payment-copy.php';
    if (!function_exists('harmat_ppc_html') || hash_file('sha256', $live) !== 'cf66252152d173f4cdd8a242eedc25b94f67e80f1286fe35f1046af2bb8a70c0'
        || (new ReflectionFunction('harmat_ppc_html'))->getFileName() !== $live) throw new RuntimeException('Exact installed original required');
    if ($mode === 'candidate') {
        $source = file_get_contents($candidate);
        if (substr($source, 0, 5) !== '<?php') throw new RuntimeException('PHP source required');
        $GLOBALS['payment_candidate_hooks'] = array();
        eval('namespace HarmatPaymentTermsCandidate; use \\WP_Post; use \\DOMDocument; use \\DOMXPath; ' . substr($source, 5));
        if ($GLOBALS['payment_candidate_hooks'] !== array(
            array('elementor/widget/render_content', 'harmat_ppc_widget', 1000, 2),
            array('the_content', 'harmat_ppc_content', 1000),
            array('template_redirect', 'harmat_ppc_buffer', -4000))) throw new RuntimeException('Unexpected candidate hooks');
        foreach ($GLOBALS['payment_candidate_hooks'] as $hook) {
            if (!remove_filter($hook[0], $hook[1], $hook[2])) throw new RuntimeException('Original hook missing');
            add_filter($hook[0], 'HarmatPaymentTermsCandidate\\' . $hook[1], $hook[2], $hook[3] ?? 1);
        }
        if ((new ReflectionFunction('harmat_ppc_html'))->getFileName() !== $live) throw new RuntimeException('Global owner changed');
    }
    $level = ob_get_level();
    ob_start();
    do_action('template_redirect');
    $template = is_singular('property') ? get_single_template() : get_page_template();
    $template = apply_filters('template_include', $template);
    if (!is_string($template) || !is_file($template)) throw new RuntimeException('Native template missing');
    include $template;
    while (ob_get_level() > $level + 1) ob_end_flush();
    $html = ob_get_clean();
    while (ob_get_level()) ob_end_clean();
    $file = dirname($candidate) . '/native-' . substr(hash('sha256', $route), 0, 12) . '-' . $mode . '.html';
    $handle = fopen($file, 'x+b');
    if (!$handle) throw new RuntimeException('Private render collision');
    if (fwrite($handle, $html) !== strlen($html)) throw new RuntimeException('Incomplete private render');
    fclose($handle);
    echo json_encode(array('route' => $route, 'sha256' => hash('sha256', $html), 'file' => basename($file)));
}
