<?php
/**
 * Plugin Name: Harmat SEO Structure Cleanup
 * Description: Cleans legacy frontend links and duplicate H1 output on selected public pages.
 * Version: 1.1.2
 */

defined('ABSPATH') || exit;

function harmat_seo_cleanup_request_path() {
    $path = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $path = parse_url($path, PHP_URL_PATH);
    return trim((string) $path, '/');
}

function harmat_seo_cleanup_is_public_frontend() {
    if (is_admin() || wp_doing_ajax() || is_feed() || is_robots() || is_trackback()) {
        return false;
    }

    return true;
}

function harmat_seo_cleanup_duplicate_h1_paths() {
    return array(
        'lakaskereso',
        'harmat-lakopark',
        'adatvedelmi-tajekoztato',
        'cookie-tajekoztato',
        'felhasznalasi-feltetelek',
        'impresszum',
        'epitesi-naplo',
        'finanszirozas',
        'harmat-lakopark-kornyeke',
        'magunkrol',
        'szolgaltatasaink',
    );
}

function harmat_seo_cleanup_add_class_to_attrs($attrs, $class_name) {
    if (preg_match('/\sclass=(["\'])(.*?)\1/i', $attrs, $match)) {
        $replacement = ' class=' . $match[1] . trim($match[2] . ' ' . $class_name) . $match[1];
        return preg_replace('/\sclass=(["\'])(.*?)\1/i', $replacement, $attrs, 1);
    }

    return rtrim($attrs) . ' class="' . esc_attr($class_name) . '"';
}

function harmat_seo_cleanup_demote_extra_h1($html) {
    $path = harmat_seo_cleanup_request_path();
    if (!in_array($path, harmat_seo_cleanup_duplicate_h1_paths(), true)) {
        return $html;
    }

    $seen = 0;
    return preg_replace_callback('/<h1\b([^>]*)>(.*?)<\/h1>/is', function ($matches) use (&$seen) {
        $seen++;
        if ($seen === 1) {
            return $matches[0];
        }

        $attrs = harmat_seo_cleanup_add_class_to_attrs($matches[1], 'harmat-demoted-h1');
        return '<h2' . $attrs . '>' . $matches[2] . '</h2>';
    }, $html);
}

function harmat_seo_cleanup_legacy_links($html) {
    $final_floorplans = home_url('/lakaskereso/');
    $site = untrailingslashit(home_url());

    $replacements = array(
        $site . '/osszes-alaprajz/' => $final_floorplans,
        '/osszes-alaprajz/' => wp_make_link_relative($final_floorplans),
        $site . '/a-lakopark/' => home_url('/harmat-lakopark/'),
        '/a-lakopark/' => wp_make_link_relative(home_url('/harmat-lakopark/')),
        $site . '/blog/' => home_url('/harmat-lakopark/'),
        '/blog/' => wp_make_link_relative(home_url('/harmat-lakopark/')),
    );

    return strtr($html, $replacements);
}

function harmat_seo_cleanup_build_log_details_html() {
    $items = array(
        array(
            'title' => 'Értékesítési iroda megnyitása',
            'text' => 'Az érdeklődők személyes tájékoztatást, alaprajzi egyeztetést és ajánlatkérést kérhetnek az értékesítési csapattól.',
        ),
        array(
            'title' => 'Projektmakett',
            'text' => 'A projekt bemutatását látványanyagok és makett segítik, hogy az épületek elhelyezkedése és az első ütem áttekinthető legyen.',
        ),
        array(
            'title' => 'Helyszíni arculat és kerítés',
            'text' => 'A helyszíni megjelenés, tájékoztató felületek és kerítés kialakítása a projekt arculatához igazodik.',
        ),
        array(
            'title' => 'Következő lépések',
            'text' => 'A következő időszakban a nyilvános projektfrissítések, elérhető lakásadatok és értékesítési információk folyamatosan bővülnek.',
        ),
    );

    $html = '<div class="harmat-build-log-details" data-harmat-build-log-details="1" aria-label="Építési napló részletek">';
    foreach ($items as $item) {
        $html .= '<section><h3>' . esc_html($item['title']) . '</h3><p>' . esc_html($item['text']) . '</p></section>';
    }
    $html .= '</div>';

    return $html;
}

function harmat_seo_cleanup_build_log_content($html) {
    if (harmat_seo_cleanup_request_path() !== 'epitesi-naplo' || strpos($html, 'data-harmat-build-log-details') !== false) {
        return $html;
    }

    $link = '<a href="' . esc_url(home_url('/harmat-lakopark/')) . '">Projekt bemutatása</a>';
    $needle = $link . '</article>';
    if (strpos($html, $needle) === false) {
        return $html;
    }

    return str_replace($needle, harmat_seo_cleanup_build_log_details_html() . $link . '</article>', $html);
}

function harmat_seo_cleanup_output($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $html = harmat_seo_cleanup_legacy_links($html);
    $html = harmat_seo_cleanup_build_log_content($html);
    $html = harmat_seo_cleanup_demote_extra_h1($html);

    return $html;
}

function harmat_seo_cleanup_start_buffer() {
    if (!harmat_seo_cleanup_is_public_frontend()) {
        return;
    }

    ob_start('harmat_seo_cleanup_output');
}
add_action('template_redirect', 'harmat_seo_cleanup_start_buffer', 0);

function harmat_seo_cleanup_styles() {
    if (harmat_seo_cleanup_request_path() !== 'epitesi-naplo') {
        return;
    }
    ?>
<style id="harmat-seo-build-log-details-css">
.harmat-build-log-details {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin: 22px 0 4px;
}
.harmat-build-log-details section {
  border: 1px solid rgba(152,112,51,.18);
  background: rgba(255,255,255,.72);
  padding: 18px;
}
.harmat-build-log-details h3 {
  margin: 0 0 8px;
  color: #263135;
  font-size: 16px;
  line-height: 1.3;
  font-weight: 900;
}
.harmat-build-log-details p {
  margin: 0;
  color: #50585d;
  font-size: 13px;
  line-height: 1.7;
}
@media (max-width: 700px) {
  .harmat-build-log-details {
    grid-template-columns: 1fr;
  }
}
</style>
    <?php
}
add_action('wp_head', 'harmat_seo_cleanup_styles', 89);
