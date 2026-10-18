<?php
// Private frontend simulation; print counts/booleans only, never raw page/field data.
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) exit(1);
$candidate = $argv[1] ?? '';
$route = $argv[2] ?? '/property/a1-1-l2/';
if (!preg_match('~\A/home/harmath2/codex-backups/public-payment-copy-[a-f0-9]{32}/zz-harmat-public-payment-copy\.php\z~D', $candidate)
    || !is_file($candidate) || is_link($candidate) || (fileperms(dirname($candidate)) & 0077)
    || !in_array($route, array('/property/a1-1-l2/', '/property/a1-4-l5/', '/finanszirozas/'), true)) exit(2);
define('DISABLE_WP_CRON', true); define('DONOTCACHEPAGE', true);
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = $route;
$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'harmat22.hu';
$_SERVER['HTTPS'] = 'on'; $_SERVER['SERVER_PORT'] = '443';
require '/home/harmath2/public_html/wp-load.php'; wp(); http_response_code(200);
require $candidate;
$file = dirname($candidate) . '/native-' . substr(hash('sha256', $_SERVER['REQUEST_URI']), 0, 12) . '-with.html';
if (!is_file($file) || is_link($file) || (fileperms($file) & 0077)) exit(3);
$html = file_get_contents($file);
$document = new DOMDocument(); $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($document);
$nodes = $xpath->query('//*[@data-elementor-id="4214"]');
$known = $nodes->length === 1 ? harmat_ppc_known_template($document->saveHTML($nodes->item(0))) : null;
$result = harmat_ppc_output($html);
echo json_encode(array('scope' => harmat_ppc_scope(), 'response' => http_response_code(), 'bytes' => strlen($html),
    'ranges' => harmat_ppc_ranges($html) !== false, 'document' => harmat_ppc_document($html) !== false,
    'templates' => $nodes->length, 'known' => $known, 'headings' => harmat_ppc_payment_headings($xpath),
    'existing_roots' => substr_count($html, 'id="harmat-public-payment-copy"'),
    'direct_roots' => substr_count($result, 'id="harmat-public-payment-copy"'),
    'content_owners' => $xpath->query('//*[@id="content"]')->length,
    'html_count' => $document->getElementsByTagName('html')->length,
    'head_count' => $document->getElementsByTagName('head')->length,
    'body_count' => $document->getElementsByTagName('body')->length));
