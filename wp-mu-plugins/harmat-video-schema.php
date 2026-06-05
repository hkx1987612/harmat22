<?php
/**
 * Plugin Name: Harmat Video Schema
 * Description: Adds Google-friendly VideoObject structured data for the homepage video.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', function () {
    if (is_admin() || !(is_front_page() || is_home())) {
        return;
    }

    $video_url = 'https://harmat22.hu/wp-content/uploads/2026/05/yulu-garden-source-compressed-60m.mp4';
    $thumb_url = 'https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-3.jpg';
    $page_url  = home_url('/');

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'VideoObject',
        '@id' => $page_url . '#harmat-project-video',
        'name' => 'Harmat Lakópark látványvideó',
        'description' => 'A Harmat Lakópark új építésű lakásait, környezetét és hangulatát bemutató látványvideó.',
        'thumbnailUrl' => [
            $thumb_url,
        ],
        'uploadDate' => '2026-05-01T00:00:00+02:00',
        'contentUrl' => $video_url,
        'embedUrl' => $page_url,
        'duration' => 'PT1M30S',
        'inLanguage' => 'hu-HU',
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Harmat Lakópark',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => 'https://harmat22.hu/wp-content/uploads/2025/11/cropped-Harmat_Logo_250.png',
                'width' => 250,
                'height' => 250,
            ],
        ],
    ];

    echo "\n" . '<script type="application/ld+json" id="harmat-video-schema">' .
        wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
        '</script>' . "\n";
}, 40);

function harmat_video_sitemap_url(): string
{
    return home_url('/harmat-video-sitemap.xml');
}

function harmat_video_schema_data(): array
{
    return [
        'page_url' => home_url('/'),
        'video_url' => 'https://harmat22.hu/wp-content/uploads/2026/05/yulu-garden-source-compressed-60m.mp4',
        'thumb_url' => 'https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-3.jpg',
        'title' => 'Harmat Lakópark látványvideó',
        'description' => 'A Harmat Lakópark új építésű lakásait, környezetét és hangulatát bemutató látványvideó.',
        'publication_date' => '2026-05-01T00:00:00+02:00',
        'duration_seconds' => 90,
    ];
}

add_filter('query_vars', function ($vars) {
    $vars[] = 'harmat_video_sitemap';
    return $vars;
});

add_action('init', function () {
    $path = isset($_SERVER['REQUEST_URI']) ? (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    if (untrailingslashit($path) === '/harmat-video-sitemap.xml') {
        harmat_render_video_sitemap();
    }

    add_rewrite_rule('^harmat-video-sitemap\.xml$', 'index.php?harmat_video_sitemap=1', 'top');
});

add_action('template_redirect', function () {
    if (!get_query_var('harmat_video_sitemap')) {
        return;
    }

    harmat_render_video_sitemap();
});

function harmat_render_video_sitemap(): void
{
    $video = harmat_video_schema_data();

    status_header(200);
    header('Content-Type: application/xml; charset=UTF-8');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
    echo "  <url>\n";
    echo '    <loc>' . esc_url($video['page_url']) . "</loc>\n";
    echo "    <video:video>\n";
    echo '      <video:thumbnail_loc>' . esc_url($video['thumb_url']) . "</video:thumbnail_loc>\n";
    echo '      <video:title><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $video['title']) . "]]></video:title>\n";
    echo '      <video:description><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $video['description']) . "]]></video:description>\n";
    echo '      <video:content_loc>' . esc_url($video['video_url']) . "</video:content_loc>\n";
    echo '      <video:duration>' . (int) $video['duration_seconds'] . "</video:duration>\n";
    echo '      <video:publication_date>' . esc_html($video['publication_date']) . "</video:publication_date>\n";
    echo "      <video:family_friendly>yes</video:family_friendly>\n";
    echo "    </video:video>\n";
    echo "  </url>\n";
    echo "</urlset>\n";
    exit;
}

add_filter('robots_txt', function ($output) {
    if (strpos($output, harmat_video_sitemap_url()) !== false) {
        return $output;
    }

    return rtrim($output) . "\nSitemap: " . harmat_video_sitemap_url() . "\n";
}, 20);

add_filter('wpseo_sitemap_index', function ($index) {
    if (strpos($index, harmat_video_sitemap_url()) !== false) {
        return $index;
    }

    return $index . "\n" .
        '<sitemap><loc>' . esc_url(harmat_video_sitemap_url()) . '</loc><lastmod>' .
        esc_html(gmdate('c')) . '</lastmod></sitemap>';
});
