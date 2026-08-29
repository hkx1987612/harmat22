<?php

declare(strict_types=1);

define('ABSPATH', __DIR__);

$test_is_admin = false;
$test_actions = array();
$test_filters = array();

function is_admin(): bool
{
    global $test_is_admin;
    return $test_is_admin;
}

function wp_doing_ajax(): bool
{
    return false;
}

function wp_is_json_request(): bool
{
    return false;
}

function is_feed(): bool
{
    return false;
}

function is_robots(): bool
{
    return false;
}

function home_url(string $path = ''): string
{
    return 'https://harmat22.hu' . $path;
}

function content_url(string $path = ''): string
{
    return 'https://harmat22.hu/wp-content' . $path;
}

function esc_attr(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_url(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function add_action(string $tag, callable $callback, int $priority = 10): void
{
    global $test_actions;
    $test_actions[$tag][$priority][] = $callback;
}

function add_filter(string $tag, callable $callback, int $priority = 10): void
{
    global $test_filters;
    $test_filters[$tag][$priority][] = $callback;
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require dirname(__DIR__, 2) . '/wp-mu-plugins/zz-harmat-construction-progress-video.php';

$_SERVER['REQUEST_URI'] = '/epitesi-naplo/';
check(harmat_construction_video_is_page(), 'Construction page was not detected.');

$_SERVER['REQUEST_URI'] = '/epitesi-naplo/?preview=1';
check(harmat_construction_video_is_page(), 'Construction page with a query string was not detected.');

$_SERVER['REQUEST_URI'] = '/';
check(!harmat_construction_video_is_page(), 'Homepage was incorrectly detected.');

$_SERVER['REQUEST_URI'] = '/epitesi-naplo/';
$test_is_admin = true;
check(!harmat_construction_video_is_page(), 'Admin request was incorrectly detected.');
$test_is_admin = false;

$markup = harmat_construction_video_markup();
check(substr_count($markup, 'data-harmat-construction-video="1"') === 1, 'Video marker is missing or duplicated.');
check(strpos($markup, HARMAT_CONSTRUCTION_VIDEO_ID) !== false, 'YouTube video ID is missing.');
check(strpos($markup, 'harmat-epitesi-naplo-2026-08.jpg') !== false, 'Local poster is missing.');
check(strpos($markup, '<iframe') === false, 'The initial markup must not load YouTube.');

$gallery = harmat_construction_gallery_markup();
check(substr_count($gallery, 'data-harmat-construction-gallery="1"') === 1, 'Gallery marker is missing or duplicated.');
check(substr_count($gallery, 'data-harmat-construction-photo') === 16, 'Gallery must contain exactly 16 selected photos.');
check(substr_count($gallery, '-960.webp') === 16, 'Gallery thumbnail count is incorrect.');
check(substr_count($gallery, '-1920.webp') === 16, 'Gallery full-size image count is incorrect.');
check(strpos($gallery, 'A munkaterület átalakulása') !== false, 'Gallery heading is missing.');
check(strpos($gallery, 'data-harmat-construction-lightbox') !== false, 'Gallery lightbox is missing.');
check(strpos($gallery, '<iframe') === false, 'Gallery must not add an iframe.');

$source = '<main><section class="harmat-info-hero"></section><section class="harmat-build-log-list"></section></main>';
$injected = harmat_construction_video_inject($source);
check(substr_count($injected, 'data-harmat-construction-video="1"') === 1, 'Video section was not injected exactly once.');
check(substr_count($injected, 'data-harmat-construction-gallery="1"') === 1, 'Gallery section was not injected exactly once.');
check(strpos($injected, 'data-harmat-construction-video="1"') < strpos($injected, 'harmat-build-log-list'), 'Video section was inserted in the wrong position.');
check(strpos($injected, 'data-harmat-construction-video="1"') < strpos($injected, 'data-harmat-construction-gallery="1"'), 'Gallery must follow the video.');
check(strpos($injected, 'data-harmat-construction-gallery="1"') < strpos($injected, 'harmat-build-log-list'), 'Gallery section was inserted in the wrong position.');
check(harmat_construction_video_inject($injected) === $injected, 'Video injection is not idempotent.');
check(harmat_construction_video_inject('<main></main>') === '<main></main>', 'Missing anchor changed unrelated HTML.');

$description_filter = $test_filters['wpseo_metadesc'][99][0] ?? null;
check(is_callable($description_filter), 'SEO description filter was not registered.');
check(strpos((string) $description_filter('old'), 'fényképes idővonal') !== false, 'SEO description was not updated for the photo timeline.');

$schema_filter = $test_filters['wpseo_schema_graph'][99][0] ?? null;
check(is_callable($schema_filter), 'Video schema filter was not registered.');
$graph = $schema_filter(array(array('@type' => 'WebPage', '@id' => harmat_construction_video_page_url())));
$video_nodes = array_values(array_filter($graph, static function ($node): bool {
    return is_array($node) && ($node['@type'] ?? '') === 'VideoObject';
}));
check(count($video_nodes) === 1, 'VideoObject was not added exactly once.');
check(($video_nodes[0]['uploadDate'] ?? '') === '2026-08-28', 'Video upload date is incorrect.');
check(($video_nodes[0]['duration'] ?? '') === 'PT1M31S', 'Video duration is incorrect.');
check(($video_nodes[0]['contentUrl'] ?? '') === harmat_construction_video_watch_url(), 'Video content URL is incorrect.');
$gallery_nodes = array_values(array_filter($graph, static function ($node): bool {
    return is_array($node) && ($node['@type'] ?? '') === 'ImageGallery';
}));
check(count($gallery_nodes) === 1, 'ImageGallery was not added exactly once.');
check(count($gallery_nodes[0]['image'] ?? array()) === 16, 'ImageGallery must contain 16 ImageObject entries.');
check(strpos((string) ($gallery_nodes[0]['url'] ?? ''), '#epitesi-fotok') !== false, 'ImageGallery URL is incorrect.');
check(count($schema_filter($graph)) === count($graph), 'Construction media schema is not idempotent.');

echo "Construction media tests passed.\n";
