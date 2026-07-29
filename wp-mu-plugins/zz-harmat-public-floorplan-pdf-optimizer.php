<?php
/**
 * Plugin Name: Harmat Public Floorplan PDF Optimizer
 * Description: Serves verified compact floorplan PDF copies on public property pages while preserving originals.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_floorplan_pdf_optimized_path(string $href): string
{
    $path = (string) wp_parse_url($href, PHP_URL_PATH);
    if (
        !$path
        || !preg_match(
            '~^/wp-content/uploads/2026/05/[^/]+-cn-floorplan\.pdf$~i',
            $path
        )
    ) {
        return '';
    }

    $optimized_path = preg_replace(
        '~\.pdf$~i',
        '-web.pdf',
        $path
    );
    if (!$optimized_path) {
        return '';
    }

    $relative = ltrim(
        substr($optimized_path, strlen('/wp-content/uploads/')),
        '/'
    );
    $local_path = trailingslashit(WP_CONTENT_DIR) . 'uploads/' . rawurldecode($relative);

    return is_file($local_path) ? $optimized_path : '';
}

function harmat_floorplan_pdf_optimize_public_links(string $html): string
{
    if (
        is_admin()
        || !is_singular('property')
        || $html === ''
        || !class_exists('WP_HTML_Tag_Processor')
    ) {
        return $html;
    }

    $processor = new WP_HTML_Tag_Processor($html);
    while ($processor->next_tag('A')) {
        $href = (string) $processor->get_attribute('href');
        $optimized_path = harmat_floorplan_pdf_optimized_path($href);
        if (!$optimized_path) {
            continue;
        }

        $processor->set_attribute(
            'href',
            str_replace(
                (string) wp_parse_url($href, PHP_URL_PATH),
                $optimized_path,
                $href
            )
        );
    }

    return $processor->get_updated_html();
}
add_filter('the_content', 'harmat_floorplan_pdf_optimize_public_links', 9999);
