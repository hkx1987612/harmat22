<?php
/**
 * Plugin Name: Harmat Public SEO Metadata
 * Description: Aligns sitemap and sharing metadata with existing public content.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Released v1.3.0 source mtime, verified against the October 3 backup hash.
const HARMAT_SEO_CONSTRUCTION_MODIFIED_GMT = '2026-10-03 08:30:47';

function harmat_seo_metadata_latest_modified($modified, string $minimum = HARMAT_SEO_CONSTRUCTION_MODIFIED_GMT): string
{
    if (is_string($modified) && $modified !== '') {
        try {
            $utc = new DateTimeZone('UTC');
            if (new DateTimeImmutable($modified, $utc) > new DateTimeImmutable($minimum, $utc)) {
                return $modified;
            }
        } catch (Exception $exception) {
            // Missing or invalid legacy dates must not mask the known release.
        }
    }

    return $minimum;
}

function harmat_seo_metadata_sitemap_entry($url, $type, $post)
{
    if (!is_array($url) || empty($url['loc']) || $type !== 'post' || !is_object($post)) {
        return $url;
    }

    if (($post->post_type ?? '') === 'page' && ($post->post_name ?? '') === 'epitesi-naplo') {
        $url['mod'] = harmat_seo_metadata_latest_modified($url['mod'] ?? '');
        $url['mod'] = harmat_seo_metadata_latest_modified($post->post_modified_gmt ?? '', $url['mod']);
        $url['mod'] = harmat_seo_metadata_latest_modified($post->post_date_gmt ?? '', $url['mod']);
    }

    if (($post->post_type ?? '') === 'property' && function_exists('hm_migrated_property_floorplan_image_from_uploads')) {
        $image = hm_migrated_property_floorplan_image_from_uploads((string) ($post->post_title ?? ''));
        if (is_string($image) && $image !== '') {
            // The public detail renderer shows this floorplan, not the legacy gallery.
            $url['images'] = array(array('src' => $image));
        }
    }

    return $url;
}
add_filter('wpseo_sitemap_entry', 'harmat_seo_metadata_sitemap_entry', 100, 3);

function harmat_seo_metadata_sitemap_index_links($links)
{
    if (!is_array($links)) {
        return $links;
    }

    $page = get_page_by_path('epitesi-naplo', OBJECT, 'page');
    if (!$page || $page->post_status !== 'publish' || $page->post_password !== '') {
        return $links;
    }

    $modified = harmat_seo_metadata_latest_modified($page->post_modified_gmt);
    $modified = harmat_seo_metadata_latest_modified($page->post_date_gmt, $modified);
    foreach ($links as $key => $link) {
        if (is_array($link) && ($link['loc'] ?? '') === home_url('/page-sitemap.xml')) {
            $links[$key]['lastmod'] = harmat_seo_metadata_latest_modified($link['lastmod'] ?? '', $modified);
        }
    }

    return $links;
}
add_filter('wpseo_sitemap_index_links', 'harmat_seo_metadata_sitemap_index_links', 100);

function harmat_seo_metadata_share_presentation($presentation, $context)
{
    if (!is_object($presentation)
        || is_admin() || wp_doing_ajax() || wp_is_json_request()
        || is_feed() || is_search() || is_404() || is_preview()
        || !is_page(array('harmat-lakopark', 'harmat-lakopark-kornyeke', 'elerhetosegeink', 'virtualis-lakasvalaszto'))
        || get_post_status(get_queried_object_id()) !== 'publish'
        || post_password_required()
    ) {
        return $presentation;
    }

    // Read generated values first so curated, template and site-default images win.
    $images = $presentation->open_graph_images;
    $twitter = $presentation->twitter_image;
    $fallback = home_url('/wp-content/uploads/2026/02/Harmat22_latvany-3.jpg');
    if (empty($images) && ($context->open_graph_enabled ?? false)) {
        $images = array(array('url' => $twitter !== '' ? $twitter : $fallback));
        $presentation->open_graph_images = $images;
    }
    if ($twitter === '') {
        $first_image = is_array($images) ? reset($images) : false;
        $presentation->twitter_image = !empty($first_image['url']) ? $first_image['url'] : $fallback;
    }

    return $presentation;
}
add_filter('wpseo_frontend_presentation', 'harmat_seo_metadata_share_presentation', 100, 2);
