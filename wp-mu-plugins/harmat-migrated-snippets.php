<?php
/**
 * Plugin Name: Harmat Migrated Snippet Logic
 * Description: Version-controlled replacement for public cleanup, SEO, legal footer, and legacy text Code Snippets.
 * Version: 2026.06.08.12
 */

defined('ABSPATH') || exit;

function hm_migrated_is_public_request() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    if (hm_migrated_is_portal_request_path()) {
        return false;
    }

    return !function_exists('wp_is_json_request') || !wp_is_json_request();
}

function hm_migrated_is_portal_request_path() {
    $path = hm_migrated_request_path();
    if (!preg_match('~^(sales|agent|client|customer|ugyfel|belepes|sales-admin|lawyer)(/|$)~i', $path)) {
        return false;
    }

    return true;
}

function hm_migrated_request_path() {
    return trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
}

function hm_migrated_format_huf($value) {
    $number = (int) preg_replace('/[^\d]/', '', (string) $value);
    if ($number <= 0) {
        return '';
    }

    return number_format($number, 0, ' ', ' ') . ' Ft';
}

function hm_migrated_format_square_meter($value) {
    $number = (float) str_replace(',', '.', (string) $value);
    if ($number <= 0) {
        return '';
    }

    return number_format($number, 2, ',', ' ') . ' m²';
}

function hm_migrated_property_floor_label($title, $floor) {
    $floor = trim((string) $floor);
    if ($floor === '' || stripos((string) $title, '-F-') !== false || strcasecmp($floor, 'F') === 0 || strcasecmp($floor, 'FSZ') === 0) {
        return 'Fsz';
    }

    return is_numeric($floor) ? ((int) $floor . '.') : $floor;
}

function hm_migrated_property_status_label($post_id) {
    $status = get_post_meta($post_id, 'property_status', true);
    if ($status === 'sold') {
        return array('Eladva', 'sold');
    }

    if (get_post_meta($post_id, 'property_under_offer', true)) {
        return array('Foglalva', 'reserved');
    }

    return array('Elérhető', 'current');
}

function hm_migrated_extract_property_pdf_url($html) {
    if (!is_string($html) || $html === '') {
        return '';
    }

    if (!preg_match_all('/href=["\']([^"\']+\.pdf(?:\?[^"\']*)?)["\']/i', $html, $matches)) {
        return '';
    }

    foreach ($matches[1] as $url) {
        if (preg_match('/(floorplan|alaprajz)/i', $url)) {
            return html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        }
    }

    return html_entity_decode($matches[1][0], ENT_QUOTES, 'UTF-8');
}

function hm_migrated_extract_property_floorplan_image_url($html) {
    if (!is_string($html) || $html === '') {
        return '';
    }

    if (!preg_match_all('/(?:href|data-src|src)=["\']([^"\']+\.(?:jpe?g|png|webp)(?:\?[^"\']*)?)["\']/i', $html, $matches)) {
        return '';
    }

    $fallback = '';
    foreach ($matches[1] as $url) {
        $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        if (stripos($url, 'alaprajz') === false) {
            continue;
        }

        if ($fallback === '') {
            $fallback = preg_replace('/-\d+x\d+(?=\.(?:jpe?g|png|webp)(?:\?|$))/i', '', $url);
        }

        if (stripos($url, '_nagy') !== false && !preg_match('/-\d+x\d+(?=\.(?:jpe?g|png|webp)(?:\?|$))/i', $url)) {
            return $url;
        }
    }

    return $fallback;
}

function hm_migrated_upload_file_url_if_exists($relative_path) {
    $upload = wp_upload_dir();
    if (empty($upload['basedir']) || empty($upload['baseurl'])) {
        return '';
    }

    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return '';
    }

    $path = trailingslashit($upload['basedir']) . $relative_path;
    if (!file_exists($path)) {
        $dir = dirname($path);
        $base = basename($path);
        if (!is_dir($dir)) {
            return '';
        }

        $found = '';
        foreach (scandir($dir) ?: array() as $file) {
            if (strcasecmp($file, $base) === 0) {
                $found = trailingslashit($dir) . $file;
                break;
            }
        }
        if ($found === '') {
            return '';
        }
        $path = $found;
        $relative_path = ltrim(str_replace('\\', '/', substr($path, strlen(trailingslashit($upload['basedir'])))), '/');
    }

    $parts = array_map('rawurlencode', explode('/', $relative_path));
    return trailingslashit($upload['baseurl']) . implode('/', $parts);
}

function hm_migrated_property_floorplan_image_from_uploads($title, $floorplan_url = '') {
    $title = preg_replace('/[^A-Za-z0-9-]/', '', (string) $title);
    if ($title === '') {
        return '';
    }

    $lower = strtolower($title);
    $upper = strtoupper($title);
    $candidates = array(
        '2026/05/' . $title . '-cn-floorplan.jpg',
        '2026/05/' . $upper . '-cn-floorplan.jpg',
        '2026/05/' . $lower . '-cn-floorplan.jpg',
        '2026/05/' . $lower . '-page-floorplan-fixed.jpg',
        '2026/02/' . $title . '-alaprajz.jpg',
        '2026/02/' . $lower . '-alaprajz_nagy.jpg',
        '2026/02/' . $lower . '_alaprajz.jpg',
        '2026/02/' . $title . '_szintrajz.jpg',
    );

    if (is_string($floorplan_url) && $floorplan_url !== '') {
        $path = wp_parse_url($floorplan_url, PHP_URL_PATH);
        if (is_string($path) && preg_match('~/uploads/(.+)\.pdf$~i', $path, $match)) {
            $candidates[] = $match[1] . '.jpg';
        }
    }

    foreach (array_unique($candidates) as $candidate) {
        $url = hm_migrated_upload_file_url_if_exists($candidate);
        if ($url !== '') {
            return $url;
        }
    }

    return '';
}

function hm_migrated_extract_property_room_rows($html) {
    if (!is_string($html) || $html === '') {
        return array();
    }

    if (!preg_match_all('~<div class="area-row">\s*<span class="area-code">([^<]*)</span>\s*<span class="area-name">([^<]*)</span>\s*<span class="area-size">([^<]*)</span>\s*</div>~u', $html, $matches, PREG_SET_ORDER)) {
        return array();
    }

    $rows = array();
    foreach ($matches as $match) {
        $rows[] = array(
            'code' => trim(wp_strip_all_tags($match[1])),
            'name' => trim(wp_strip_all_tags($match[2])),
            'size' => trim(wp_strip_all_tags($match[3])),
        );
        if (count($rows) >= 18) {
            break;
        }
    }

    return $rows;
}

function hm_migrated_extract_property_room_total($html) {
    if (!is_string($html) || $html === '') {
        return array('', '');
    }

    if (preg_match('~<div class="area-total-header">\s*([^<]+)\s*<span class="area-total-size">([^<]*)</span>~u', $html, $match)) {
        return array(trim(wp_strip_all_tags($match[1])), trim(wp_strip_all_tags($match[2])));
    }

    return array('', '');
}

function hm_migrated_find_div_block_end($html, $start) {
    if (!is_string($html) || $start === false || $start < 0) {
        return false;
    }

    $slice = substr($html, $start);
    if (!preg_match_all('~</?div\b[^>]*>~i', $slice, $matches, PREG_OFFSET_CAPTURE)) {
        return false;
    }

    $depth = 0;
    foreach ($matches[0] as $match) {
        $tag = $match[0];
        if (stripos($tag, '</div') === 0) {
            $depth--;
        } else {
            $depth++;
        }

        if ($depth === 0) {
            return $start + $match[1] + strlen($tag);
        }
    }

    return false;
}


function hm_migrated_extract_property_room_rows_from_text($html, $title) {
    if (!is_string($html) || $html === '' || !is_string($title) || $title === '') {
        return array();
    }

    $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\x{00a0}/u', ' ', $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = trim($text);
    $prefix = preg_quote($title, '/');
    $pattern = '/(' . $prefix . '(?:\/\d{2})?)\s+([^\r\n]+)\s+([0-9]+(?:[\.,][0-9]+)?\s*m(?:\x{00B2}|2)?)/u';
    if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
        return array();
    }

    $rows = array();
    foreach ($matches as $match) {
        $rows[] = array(
            'code' => trim($match[1]),
            'name' => trim($match[2]),
            'size' => trim($match[3]),
        );
    }

    return $rows;
}

function hm_migrated_extract_property_room_total_from_text($html) {
    if (!is_string($html) || $html === '') {
        return array('', '');
    }

    $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\x{00a0}/u', ' ', $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $lines = preg_split('/\R+/u', $text);
    if (!is_array($lines)) {
        return array('', '');
    }

    $clean = array();
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $clean[] = $line;
        }
    }

    $count = count($clean);
    for ($i = 0; $i < $count - 1; $i++) {
        if (stripos($clean[$i], '?rt?kes?tett alapter?let') !== false) {
            $size = $clean[$i + 1];
            if (preg_match('/^[0-9]+(?:[\.,][0-9]+)?\s*m(?:?|2)?$/iu', $size)) {
                $size = preg_replace('/\s*m2$/iu', ' m?', $size);
                $size = preg_replace('/\s*m$/iu', ' m?', $size);
                return array('?rt?kes?tett alapter?let', $size);
            }
        }
    }

    return array('', '');
}

function hm_migrated_property_sample_detail_html($source_html, $floorplan_override = '') {
    if (!is_singular('property')) {
        return '';
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return '';
    }

    $title = get_post_meta($post_id, 'property_heading', true);
    if ($title === '') {
        $title = get_the_title($post_id);
    }

    $building = get_post_meta($post_id, 'property_address_street', true);
    $floor_raw = get_post_meta($post_id, 'property_address_street_number', true);
    $floor = hm_migrated_property_floor_label($title, $floor_raw);
    $is_ground_floor = $floor === 'Fsz' || stripos((string) $title, '-F-') !== false;
    $rooms = get_post_meta($post_id, 'property_rooms', true);
    $area = (float) get_post_meta($post_id, 'property_building_area', true);
    $outdoor = (float) get_post_meta($post_id, 'property_land_area', true);
    $sale_area = $area + ($is_ground_floor ? 0 : ($outdoor * 0.5));
    $sale_area = $sale_area > 0 ? floor($sale_area * 100) / 100 : 0;
    $price = (int) get_post_meta($post_id, 'property_price', true);
    $price_hidden = get_post_meta($post_id, '_harmat_hide_front_price', true) === 'yes' || get_post_meta($post_id, 'property_price_display', true) === 'no';
    $unit_price = (!$price_hidden && $price > 0 && $sale_area > 0) ? round($price / $sale_area) : 0;
    $floorplan_url = $floorplan_override !== '' ? $floorplan_override : get_post_meta($post_id, 'property_floorplan', true);
    $image_url = hm_migrated_property_floorplan_image_from_uploads($title, $floorplan_url);
    if ($image_url === '') {
        $image_url = hm_migrated_extract_property_floorplan_image_url($source_html);
    }
    list($status_label) = hm_migrated_property_status_label($post_id);
    $rows = hm_migrated_extract_property_room_rows($source_html);
    if (!$rows) {
        $post_content = (string) get_post_field('post_content', $post_id);
        $rows = hm_migrated_extract_property_room_rows_from_text($post_content, $title);
    }
    list($total_label, $total_size) = hm_migrated_extract_property_room_total($source_html);
    if ($total_label === '' || $total_size === '') {
        list($total_label, $total_size) = hm_migrated_extract_property_room_total_from_text((string) get_post_field('post_content', $post_id));
    }
    if (($total_label === '' || $total_size === '') && $sale_area > 0) {
        $total_label = html_entity_decode('&Eacute;rt&eacute;kes&iacute;tett alapter&uuml;let', ENT_QUOTES, 'UTF-8');
        $total_size = hm_migrated_format_square_meter($sale_area);
    }
    if ($image_url === '' && !$rows) {
        return '';
    }

    $facts = array(
        array('Státusz', $status_label),
        array('Épület', $building),
        array('Emelet', $floor),
        array('Szobaszám', $rooms ? $rooms . ' szoba' : ''),
        array('Alapterület', $area > 0 ? hm_migrated_format_square_meter($area) : ''),
        array($is_ground_floor ? 'Kert / terasz' : 'Terasz / erkély', $outdoor > 0 ? hm_migrated_format_square_meter($outdoor) : ''),
        array('Eladási terület', $sale_area > 0 ? hm_migrated_format_square_meter($sale_area) : ''),
        array('Ár', (!$price_hidden && $price > 0) ? hm_migrated_format_huf($price) : 'Értékesítési egyeztetés alapján'),
        array('Egységár', $unit_price > 0 ? hm_migrated_format_huf($unit_price) . ' / m²' : ''),
    );
    $facts = array_slice($facts, 4);

    $html = '<section class="harmat-property-detail-sample" aria-label="' . esc_attr($title) . ' részletes adatok">';
    $html .= '<div class="harmat-property-detail-media">';
    $html .= '<div class="harmat-property-detail-media-head"><span>Alaprajz</span><h2>' . esc_html($title) . '</h2></div>';
    if ($image_url) {
        $html .= '<a class="harmat-property-detail-image" href="' . esc_url($image_url) . '" target="_blank" rel="noopener">';
        $html .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($title . ' alaprajz') . '" loading="eager" decoding="async">';
        $html .= '</a>';
    }
    $html .= '</div>';

    if ($rows) {
        $html .= '<div class="harmat-property-detail-rooms"><div class="harmat-property-detail-rooms-head"><span>Helyiséglista</span><h3>Helyiségek és méretek</h3></div><div class="harmat-property-room-table">';
        foreach ($rows as $row) {
            $html .= '<div class="harmat-property-room-row"><span>' . esc_html($row['code']) . '</span><strong>' . esc_html($row['name']) . '</strong><em>' . esc_html($row['size']) . '</em></div>';
        }
        if ($total_label !== '' && $total_size !== '') {
            $safe_total_label = html_entity_decode('&Eacute;rt&eacute;kes&iacute;tett alapter&uuml;let', ENT_QUOTES, 'UTF-8');
            $html .= '<div class="harmat-property-room-total"><span>' . esc_html($safe_total_label) . '</span><strong>' . esc_html($total_size) . '</strong></div>';
        }
        $html .= '</div></div>';
    }

    $html .= '</section>';

    return $html;
}

function hm_migrated_property_hero_html($floorplan_override = '') {
    if (!is_singular('property')) {
        return '';
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return '';
    }

    $title = get_post_meta($post_id, 'property_heading', true);
    if ($title === '') {
        $title = get_the_title($post_id);
    }

    $building = get_post_meta($post_id, 'property_address_street', true);
    $floor_raw = get_post_meta($post_id, 'property_address_street_number', true);
    $floor = hm_migrated_property_floor_label($title, $floor_raw);
    $is_ground_floor = $floor === 'Fsz' || stripos((string) $title, '-F-') !== false;
    $rooms = get_post_meta($post_id, 'property_rooms', true);
    $area = (float) get_post_meta($post_id, 'property_building_area', true);
    $outdoor = (float) get_post_meta($post_id, 'property_land_area', true);
    $sale_area = $area + ($is_ground_floor ? 0 : ($outdoor * 0.5));
    $sale_area = $sale_area > 0 ? floor($sale_area * 100) / 100 : 0;
    $price = (int) get_post_meta($post_id, 'property_price', true);
    $price_hidden = get_post_meta($post_id, '_harmat_hide_front_price', true) === 'yes' || get_post_meta($post_id, 'property_price_display', true) === 'no';
    $floorplan_url = $floorplan_override !== '' ? $floorplan_override : get_post_meta($post_id, 'property_floorplan', true);
    list($status_label, $status_key) = hm_migrated_property_status_label($post_id);
    $unit_price = (!$price_hidden && $price > 0 && $sale_area > 0) ? round($price / $sale_area) : 0;
    $outdoor_label = $is_ground_floor ? 'Kert / terasz' : 'Terasz / erkély';

    $facts = array(
        array('Státusz', $status_label, 'status'),
        array('Árinformáció', (!$price_hidden && $price > 0) ? hm_migrated_format_huf($price) : 'Ár egyeztetés alapján', 'highlight'),
        array('Eladási terület', $sale_area > 0 ? hm_migrated_format_square_meter($sale_area) : '', ''),
        array('Egységár', $unit_price > 0 ? hm_migrated_format_huf($unit_price) . ' / m²' : 'Értékesítési egyeztetés alapján', ''),
        array('Épület', $building, ''),
        array('Emelet', $floor, ''),
        array('Szoba', $rooms ? $rooms . ' szoba' : '', ''),
        array('Alapterület', $area > 0 ? hm_migrated_format_square_meter($area) : '', ''),
        array($outdoor_label, $outdoor > 0 ? hm_migrated_format_square_meter($outdoor) : '', ''),
    );

    $html = '<section class="harmat-property-hero harmat-property-hero-' . esc_attr($status_key) . '" aria-label="Lakás összefoglaló">';
    $html .= '<div class="harmat-property-hero-head">';
    $html .= '<span>Harmat Lakópark</span><h1>' . esc_html($title) . '</h1>';
    $html .= '<div class="harmat-property-hero-title-status"><span>Státusz</span><strong>' . esc_html($status_label) . '</strong></div>';
    $html .= '</div><dl class="harmat-property-hero-grid">';
    foreach ($facts as $fact) {
        if ($fact[1] === '') {
            continue;
        }
        $html .= '<div class="' . esc_attr($fact[2]) . '"><dt>' . esc_html($fact[0]) . '</dt><dd>' . esc_html($fact[1]) . '</dd></div>';
    }
    $html .= '</dl><div class="harmat-property-hero-actions">';
    $html .= '<a class="primary" href="#opal-contactform-popup">Ajánlatot kérek</a>';
    if ($floorplan_url) {
        $html .= '<a href="' . esc_url($floorplan_url) . '" target="_blank" rel="noopener">Alaprajz letöltése</a>';
    }
    $html .= '<a href="' . esc_url(home_url('/lakaskereso/')) . '">Vissza a lakáskeresőhöz</a>';
    $html .= '</div>';
    if ($is_ground_floor && $outdoor > 0) {
        $html .= '<p class="harmat-property-hero-note">A földszinti kert ajándék.</p>';
    }
    $html .= '</section>';

    return $html;
}

function hm_migrated_replace_property_header($html) {
    if (!is_singular('property') || !is_string($html) || $html === '') {
        return $html;
    }

    $original_html = $html;
    $floorplan_url = hm_migrated_extract_property_pdf_url($html);
    $card = hm_migrated_property_hero_html($floorplan_url);
    if ($card === '') {
        return $html;
    }

    $title_start = strpos($html, '<div id="page-title-bar"');
    $content_start = $title_start === false ? false : strpos($html, '<div class="site-content-contain">', $title_start);
    if ($title_start !== false && $content_start !== false) {
        $html = substr($html, 0, $title_start) . substr($html, $content_start);
    }

    $old_start = strpos($html, '<div class="elementor-element elementor-element-6e26d68');
    $floorplan_start = $old_start === false ? false : strpos($html, '<div class="elementor-element elementor-element-03069d8', $old_start);
    if ($old_start !== false && $floorplan_start !== false) {
        $sample = hm_migrated_property_sample_detail_html($html, $floorplan_url);
        if ($sample !== '') {
            $legacy_detail_end = hm_migrated_find_div_block_end($html, $floorplan_start);
            if ($legacy_detail_end !== false) {
                return substr($html, 0, $old_start) . $card . $sample . substr($html, $legacy_detail_end);
            }
        }

        return substr($html, 0, $old_start) . $card . substr($html, $floorplan_start);
    }

    $sample = hm_migrated_property_sample_detail_html($html, $floorplan_url);
    if ($sample !== '') {
        $replacement = $card . $sample;
        $site_start = strpos($html, '<div class="site-content-contain"');
        if ($site_start !== false) {
            $site_open_end = strpos($html, '>', $site_start);
            $footer_start = $site_open_end === false ? false : strpos($html, '<footer', $site_open_end);
            if ($site_open_end !== false && $footer_start !== false) {
                return substr($html, 0, $site_open_end + 1) . $replacement . substr($html, $footer_start);
            }
        }
    }

    return $original_html;
}

function hm_migrated_virtual_selector_static_html() {
    if (hm_migrated_request_path() !== 'virtualis-lakasvalaszto') {
        return '';
    }

    $links = array(
        'A1 épület lakásai' => home_url('/virtualis-lakasvalaszto-a1-epulet/'),
        'A2 épület lakásai' => home_url('/virtualis-lakasvalaszto-a2-epulet/'),
        'A3 épület lakásai' => home_url('/virtualis-lakasvalaszto-a3-epulet/'),
        'A4 épület lakásai' => home_url('/virtualis-lakasvalaszto-a4-epulet/'),
        'Lakáskereső megnyitása' => home_url('/lakaskereso/'),
    );

    $html_out = '<section class="harmat-virtual-static-intro" aria-label="Virtuális lakásválasztó összefoglaló">';
    $html_out .= '<span>Virtuális lakásválasztó</span>';
    $html_out .= '<h2>Válasszon épületet az első ütem lakásai közül</h2>';
    $html_out .= '<p>Az I. ütemben 124 lakás érhető el A1, A2, A3 és A4 épületekben. Az online lakáskereső és a virtuális lakásválasztó segíti az épület, emelet, szobaszám és alapterület szerinti választást.</p>';
    $html_out .= '<nav aria-label="Épületek">';
    foreach ($links as $label => $url) {
        $html_out .= '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    $html_out .= '<a href="#opal-contactform-popup">Árajánlatot kérek</a>';
    $html_out .= '</nav></section>';

    return $html_out;
}

function hm_migrated_insert_after_page_title($html, $insert_html) {
    if (!is_string($html) || $html === '' || !is_string($insert_html) || $insert_html === '') {
        return $html;
    }

    if (strpos($html, '<section class="harmat-virtual-static-intro') !== false && strpos($insert_html, 'harmat-virtual-static-intro') !== false) {
        return $html;
    }

    $needle = '<div class="site-content-contain">';
    $pos = strpos($html, $needle);
    if ($pos === false) {
        return $insert_html . $html;
    }

    return substr($html, 0, $pos) . $insert_html . substr($html, $pos);
}

function hm_migrated_home_opening_notice_html() {
    $html = '<section class="harmat-home-opening-notice" aria-label="Értékesítési nyitás">';
    $html .= '<div><strong>2026. június 12.</strong><span>Alapkőletételi ünnepség és hivatalos értékesítési nyitás</span></div>';
    $html .= '</section>';

    return $html;
}

function hm_migrated_insert_home_opening_notice($html) {
    if (!is_front_page() || !is_string($html) || $html === '' || strpos($html, '<section class="harmat-home-opening-notice"') !== false) {
        return $html;
    }

    $notice = hm_migrated_home_opening_notice_html();
    $needle = '<div class="site-content-contain">';
    $pos = strpos($html, $needle);
    if ($pos !== false) {
        return substr($html, 0, $pos) . $notice . substr($html, $pos);
    }

    $header_end = stripos($html, '</header>');
    if ($header_end !== false) {
        $insert_at = $header_end + 9;
        return substr($html, 0, $insert_at) . $notice . substr($html, $insert_at);
    }

    return $notice . $html;
}

function hm_migrated_home_partners_html() {
    $t = function ($text) {
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    };

    $cards = array(
        array(
            'kicker' => 'Beruh&aacute;z&oacute;',
            'title' => 'Cooperation Power Kft.',
            'text' => 'A Harmat Lak&oacute;park fejleszt&eacute;s&eacute;t, szakmai koordin&aacute;ci&oacute;j&aacute;t &eacute;s &eacute;rt&eacute;kes&iacute;t&eacute;si folyamat&aacute;t fogja &ouml;ssze.',
        ),
        array(
            'kicker' => 'Tervez&#337;i partnerek',
            'title' => 'Avant-Garde &amp; MODUM',
            'text' => 'K&eacute;t tapasztalt &eacute;p&iacute;t&eacute;szeti partner, Ybl- &eacute;s Pro Architectura d&iacute;jas szakmai h&aacute;tt&eacute;rrel.',
        ),
        array(
            'kicker' => 'Megval&oacute;s&iacute;t&aacute;s',
            'title' => 'Value4Real Group',
            'text' => 'Projektfejleszt&eacute;s, gener&aacute;lkivitelez&eacute;s, projektmenedzsment &eacute;s m&#369;szaki ellen&#337;rz&eacute;s szakmai t&aacute;mogat&aacute;sa.',
        ),
    );

    $html = '<section class="harmat-home-partners" aria-labelledby="harmat-home-partners-title">';
    $html .= '<div class="harmat-home-partners-head"><span>' . esc_html($t('A projekt m&ouml;g&ouml;tt')) . '</span><h2 id="harmat-home-partners-title">' . esc_html($t('Szakmai h&aacute;tt&eacute;r')) . '</h2><p>' . esc_html($t('Ismerje meg a Harmat Lak&oacute;park beruh&aacute;z&oacute;j&aacute;t, tervez&#337;i partnereit &eacute;s a megval&oacute;s&iacute;t&aacute;st t&aacute;mogat&oacute; szakmai csapatot.')) . '</p></div>';
    $html .= '<div class="harmat-home-partners-grid">';
    foreach ($cards as $card) {
        $html .= '<article><span>' . esc_html($t($card['kicker'])) . '</span><h3>' . esc_html($t($card['title'])) . '</h3><p>' . esc_html($t($card['text'])) . '</p></article>';
    }
    $html .= '</div><a class="harmat-home-partners-link" href="' . esc_url(home_url('/magunkrol/')) . '">' . esc_html($t('R&eacute;szletek a Magunkr&oacute;l oldalon')) . '</a>';
    $html .= '</section>';

    return $html;
}

function hm_migrated_home_featured_apartments_items() {
    global $harmat_sales_manager;

    if (!is_object($harmat_sales_manager) || !method_exists($harmat_sales_manager, 'frontend_sales_data')) {
        return array();
    }

    $items = $harmat_sales_manager->frontend_sales_data();
    if (!is_array($items) || !$items) {
        return array();
    }

    $featured = array();
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $status = isset($item['status']) ? (string) $item['status'] : '';
        $price = isset($item['price']) ? (int) $item['price'] : 0;
        $area = isset($item['salesArea']) ? (float) $item['salesArea'] : 0;
        $hide_price = !empty($item['hidePrice']);
        if ($status !== 'current' || $hide_price || $price <= 0 || $area <= 0 || empty($item['url']) || empty($item['title'])) {
            continue;
        }

        $item['sqmPrice'] = !empty($item['sqmPrice']) ? (int) $item['sqmPrice'] : (int) round($price / $area);
        $item['roomsNumeric'] = isset($item['rooms']) ? (float) str_replace(',', '.', preg_replace('/[^0-9.,]/', '', (string) $item['rooms'])) : 0;
        $item['outsideArea'] = isset($item['terrace']) ? (float) str_replace(',', '.', preg_replace('/[^0-9.,-]/', '', (string) $item['terrace'])) : 0;
        $featured[] = $item;
    }

    if (!$featured) {
        return array();
    }

    $sort_by = function ($items, $key, $direction = 'asc') {
        usort($items, function ($a, $b) use ($key, $direction) {
            $a_value = isset($a[$key]) ? (float) $a[$key] : 0;
            $b_value = isset($b[$key]) ? (float) $b[$key] : 0;
            if ($a_value === $b_value) {
                return strnatcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            }

            return $direction === 'desc' ? ($b_value <=> $a_value) : ($a_value <=> $b_value);
        });

        return $items;
    };

    $selected = array();
    $selected_ids = array();
    $building_counts = array();
    $select_one = function ($candidates, $reason, $respect_building_limit = true) use (&$selected, &$selected_ids, &$building_counts) {
        foreach ($candidates as $candidate) {
            $id = isset($candidate['id']) ? (int) $candidate['id'] : 0;
            if ($id <= 0 || isset($selected_ids[$id])) {
                continue;
            }

            $building = isset($candidate['building']) ? (string) $candidate['building'] : '';
            if ($respect_building_limit && $building !== '' && isset($building_counts[$building]) && $building_counts[$building] >= 2) {
                continue;
            }

            $candidate['featureReason'] = $reason;
            $selected[] = $candidate;
            $selected_ids[$id] = true;
            if ($building !== '') {
                $building_counts[$building] = isset($building_counts[$building]) ? $building_counts[$building] + 1 : 1;
            }

            return true;
        }

        return false;
    };

    $compact = array_values(array_filter($featured, function ($item) {
        return isset($item['roomsNumeric']) && $item['roomsNumeric'] > 0 && $item['roomsNumeric'] <= 1.5;
    }));
    $two_room = array_values(array_filter($featured, function ($item) {
        return isset($item['roomsNumeric']) && $item['roomsNumeric'] >= 1.75 && $item['roomsNumeric'] <= 2.5;
    }));
    $larger = array_values(array_filter($featured, function ($item) {
        $rooms = isset($item['roomsNumeric']) ? (float) $item['roomsNumeric'] : 0;
        $area = isset($item['salesArea']) ? (float) $item['salesArea'] : 0;
        return $rooms >= 3 || $area >= 70;
    }));
    $outside = array_values(array_filter($featured, function ($item) {
        return isset($item['outsideArea']) && (float) $item['outsideArea'] >= 8;
    }));

    $select_one($sort_by($featured, 'price'), 'Belépő ár');
    $select_one($sort_by($featured, 'sqmPrice'), 'Kedvező m² ár');
    $select_one($sort_by($compact, 'price'), 'Kompakt lakás');
    $select_one($sort_by($two_room, 'price'), '2 szobás ajánlat');
    $select_one($sort_by($outside, 'outsideArea', 'desc'), 'Külső térrel');
    $select_one($sort_by($larger, 'sqmPrice'), 'Tágasabb választás');

    $fallback = $sort_by($featured, 'price');
    while (count($selected) < 6 && $select_one($fallback, 'Kiegyensúlyozott ajánlat', false)) {
        // Fill remaining cards from the current live inventory.
    }

    return array_slice($selected, 0, 6);
}

function hm_migrated_home_featured_apartments_html() {
    $items = hm_migrated_home_featured_apartments_items();
    if (!$items) {
        return '';
    }

    $html = '<section class="harmat-home-featured" aria-labelledby="harmat-home-featured-title">';
    $html .= '<div class="harmat-home-featured-head"><span>Kiemelt ajánlatok</span><h2 id="harmat-home-featured-title">Kiemelt lakások</h2><a href="' . esc_url(home_url('/lakaskereso/')) . '">Összes lakás</a></div>';
    $html .= '<div class="harmat-home-featured-grid">';

    foreach ($items as $item) {
        $title = (string) ($item['title'] ?? '');
        $url = (string) ($item['url'] ?? '');
        $rooms = trim((string) ($item['rooms'] ?? ''));
        $area = hm_migrated_format_square_meter($item['salesArea'] ?? 0);
        $price = hm_migrated_format_huf($item['price'] ?? 0);
        $sqm_price = hm_migrated_format_huf($item['sqmPrice'] ?? 0);
        $room_label = $rooms !== '' ? $rooms . ' szoba' : 'Szobaszám egyeztetés alatt';
        $reason = trim((string) ($item['featureReason'] ?? 'Kiemelt ajánlat'));
        $offer_url = $url ? $url . '#opal-contactform-popup' : home_url('/elerhetosegeink/');

        $html .= '<article class="harmat-home-featured-card">';
        $html .= '<div class="harmat-home-featured-card-top"><span>Elérhető</span><h3>' . esc_html($title) . '</h3></div>';
        $html .= '<p class="harmat-home-featured-reason">' . esc_html($reason) . '</p>';
        $html .= '<dl>';
        $html .= '<div><dt>Szobaszám</dt><dd>' . esc_html($room_label) . '</dd></div>';
        $html .= '<div><dt>Eladási terület</dt><dd>' . esc_html($area) . '</dd></div>';
        $html .= '<div><dt>Ár</dt><dd>' . esc_html($price) . '</dd></div>';
        $html .= '<div><dt>Egységár</dt><dd>' . esc_html($sqm_price) . ' / m²</dd></div>';
        $html .= '</dl>';
        $html .= '<div class="harmat-home-featured-actions"><a href="' . esc_url($url) . '">Megnézem</a><a href="' . esc_url($offer_url) . '">Árajánlatot kérek</a></div>';
        $html .= '</article>';
    }

    $html .= '</div></section>';

    return $html;
}

function hm_migrated_insert_home_featured_apartments($html) {
    if (!is_front_page() || !is_string($html) || $html === '' || strpos($html, 'class="harmat-home-featured"') !== false) {
        return $html;
    }

    $featured_section = hm_migrated_home_featured_apartments_html();
    if ($featured_section === '') {
        return $html;
    }

    $partners_section = strpos($html, 'class="harmat-home-partners"') === false ? hm_migrated_home_partners_html() : '';
    $section = $partners_section . $featured_section;

    $appointment_pos = stripos($html, 'Lakópark-beli lakásaink');
    $gallery_container_pos = stripos($html, 'elementor-element-dff4be8');
    if ($appointment_pos !== false && $gallery_container_pos !== false && $appointment_pos < $gallery_container_pos) {
        $before_gallery = substr($html, 0, $gallery_container_pos);
        $insert_at = strripos($before_gallery, '<div');
        if ($insert_at !== false) {
            return substr($html, 0, $insert_at) . $section . substr($html, $insert_at);
        }
    }

    $footer_pos = stripos($html, '<footer');
    if ($footer_pos !== false) {
        return substr($html, 0, $footer_pos) . $section . substr($html, $footer_pos);
    }

    $body_pos = strripos($html, '</body>');
    if ($body_pos !== false) {
        return substr($html, 0, $body_pos) . $section . substr($html, $body_pos);
    }

    return $html . $section;
}

function hm_migrated_clean_footer_html() {
    $columns = array(
        'Projekt' => array(
            'Főoldal' => home_url('/'),
            'Harmat Lakópark' => home_url('/harmat-lakopark/'),
            html_entity_decode('Magunkr&oacute;l', ENT_QUOTES, 'UTF-8') => home_url('/magunkrol/'),
            'Környékünk' => home_url('/harmat-lakopark-kornyeke/'),
            'Galéria' => home_url('/galeria/'),
        ),
        'Lakások' => array(
            'Lakáskereső' => home_url('/lakaskereso/'),
            'Virtuális lakásválasztó' => home_url('/virtualis-lakasvalaszto/'),
            'Első ütem' => home_url('/virtualis-lakasvalaszto-elso-utem/'),
            'Összes alaprajz' => home_url('/lakaskereso/'),
        ),
    );

    $html = '<footer id="colophon" class="site-footer harmat-clean-footer" role="contentinfo">';
    $html .= '<div class="harmat-clean-footer-inner">';
    foreach ($columns as $title => $links) {
        $html .= '<section><h2>' . esc_html($title) . '</h2><nav aria-label="' . esc_attr($title) . '">';
        foreach ($links as $label => $url) {
            $html .= '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        $html .= '</nav></section>';
    }

    $html .= '<section><h2>Jogi / Kapcsolat</h2>';
    $html .= '<p><strong>Harmat Lakópark címe</strong><br>1105 Budapest, Harmat utca 22.</p>';
    $html .= '<p><strong>E-mail</strong><br><a href="mailto:ertekesites@harmat22.hu">ertekesites@harmat22.hu</a></p>';
    $html .= '<p><strong>Telefon</strong><br><a href="tel:+36300733375">+36300733375</a></p>';
    $html .= '<nav aria-label="Jogi dokumentumok">';
    foreach (hm_migrated_legal_links() as $label => $url) {
        $html .= '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    $html .= '</nav></section>';
    $html .= '</div>';
    $html .= '<div class="harmat-clean-footer-bottom">© Cooperation Power Kft. Minden jog fenntartva. Az árak, alapterületek, látványtervek és műszaki tartalmak tájékoztató jellegűek.</div>';
    $html .= '</footer>';

    return $html;
}

function hm_migrated_financing_page_html() {
    $html = '<main id="main" class="site-main harmat-info-page harmat-financing-page" role="main">';
    $html .= '<article class="page type-page status-publish hentry"><div class="entry-content">';
    $html .= '<section class="harmat-info-hero"><span>Vásárlási információk</span><h1>Finanszírozás</h1><p>A Harmat Lakópark értékesítési csapata a kiválasztott lakáshoz kapcsolódó fizetési ütemezésről, banki finanszírozási lehetőségekről és támogatási kérdésekről tájékoztató jelleggel ad segítséget.</p></section>';
    $html .= '<section class="harmat-info-grid">';
    $html .= '<article><h2>Fizetési ütemezés</h2><p>A pontos fizetési ütemezés a választott lakástól, az adásvételi folyamat állapotától és az egyedi megállapodástól függ. Az értékesítési csapat minden ajánlatnál külön egyezteti az aktuális részleteket.</p></article>';
    $html .= '<article><h2>CSOK lehetőség</h2><p>Új építésű lakás vásárlásánál bizonyos családtámogatási lehetőségek felmerülhetnek. A jogosultság mindig egyedi vizsgálatot igényel, ezért a végleges döntést banki vagy hivatalos tanácsadó erősíti meg.</p></article>';
    $html .= '<article><h2>Hitelügyintézés</h2><p>Igény esetén segítünk a hitelügyintézés előkészítésében és a szükséges dokumentumok áttekintésében. A hitelképességet, kamatot és banki feltételeket minden esetben a finanszírozó bank határozza meg.</p></article>';
    $html .= '<article><h2>Banki finanszírozás</h2><p>A banki finanszírozásnál a jövedelmi helyzet, önerő, futamidő, ingatlanérték és banki kockázati szabályok együttesen számítanak. A Harmat Lakópark nem vállal hiteljóváhagyási kötelezettséget.</p></article>';
    $html .= '</section>';
    $html .= '<section class="harmat-info-note"><h2>Tájékoztató jellegű nyilatkozat</h2><p>Az oldalon szereplő finanszírozási információk nem minősülnek pénzügyi tanácsadásnak, hitelígérvénynek vagy szerződéses ajánlatnak. A végleges fizetési, támogatási és banki feltételeket az adásvételi szerződés, az értékesítési egyeztetés és az érintett pénzügyi intézmény dokumentumai határozzák meg.</p><a href="' . esc_url(home_url('/elerhetosegeink/')) . '">Kapcsolat az értékesítéssel</a></section>';
    $html .= '</div></article></main>';

    return $html;
}

function hm_migrated_build_log_page_html() {
    $html = '<main id="main" class="site-main harmat-info-page harmat-build-log-page" role="main">';
    $html .= '<article class="page type-page status-publish hentry"><div class="entry-content">';
    $html .= '<section class="harmat-info-hero"><span>Projektfrissítések</span><h1>Építési napló</h1><p>A Harmat Lakópark fontosabb projektmérföldkövei és nyilvános építési hírei egy helyen.</p></section>';
    $html .= '<section class="harmat-build-log-list">';
    $html .= '<article><time datetime="2026-06-12">2026. június 12.</time><h2>Ünnepélyes alapkőletétel és hivatalos értékesítési nyitás</h2><p>A Harmat Lakópark első ütemének bemutatása és hivatalos értékesítési nyitása 2026. június 12-én indul. Ettől az időponttól az első ütem lakásadatai, alaprajzai és ajánlatkérési folyamata részletesen elérhető a weboldalon és az értékesítési csapatnál.</p><a href="' . esc_url(home_url('/harmat-lakopark/')) . '">Projekt bemutatása</a></article>';
    $html .= '</section>';
    $html .= '</div></article></main>';

    return $html;
}

function hm_migrated_replace_info_page($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $path = hm_migrated_request_path();
    if ($path !== 'finanszirozas' && $path !== 'epitesi-naplo') {
        return $html;
    }

    if (!preg_match('~<main\b[^>]*\bid=(["\'])main\1[^>]*>~i', $html, $match, PREG_OFFSET_CAPTURE)) {
        return $html;
    }

    $start = $match[0][1];
    $end = stripos($html, '</main>', $start);
    if ($end === false) {
        return $html;
    }

    $replacement = $path === 'finanszirozas' ? hm_migrated_financing_page_html() : hm_migrated_build_log_page_html();
    return substr($html, 0, $start) . $replacement . substr($html, $end + 7);
}

function hm_migrated_ensure_info_pages() {
    if (wp_doing_ajax() || defined('DOING_CRON')) {
        return;
    }

    $pages = array(
        'finanszirozas' => array(
            'title' => 'Finanszírozás',
            'content' => 'Finanszírozási információk a Harmat Lakópark vásárlói számára.',
        ),
        'epitesi-naplo' => array(
            'title' => 'Építési napló',
            'content' => 'Harmat Lakópark projektfrissítések és építési napló.',
        ),
    );

    foreach ($pages as $slug => $page) {
        $existing = get_page_by_path($slug);
        if ($existing) {
            continue;
        }

        wp_insert_post(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_name' => $slug,
            'post_title' => $page['title'],
            'post_content' => $page['content'],
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ));
    }
}

add_action('init', 'hm_migrated_ensure_info_pages', 20);

function hm_migrated_project_page_html() {
    $hero_image = content_url('/uploads/2026/02/Harmat22_latvany-3-1536x864.jpg');
    $gallery = array(
        array(
            'url' => content_url('/uploads/2026/02/Harmat22_latvany-4-400x225.jpg'),
            'title' => 'Építészeti karakter',
            'text' => 'Letisztult tömegformálás és világos, kortárs lakóparki hangulat.',
            'alt' => 'Harmat Lakópark építészeti látványterv',
        ),
        array(
            'url' => content_url('/uploads/2026/02/Harmat22_latvany-8-400x225.jpg'),
            'title' => 'Zöld lakókörnyezet',
            'text' => 'Külső terek és zöldfelületek, amelyek a mindennapi komfortot erősítik.',
            'alt' => 'Harmat Lakópark zöld lakókörnyezeti látványterv',
        ),
        array(
            'url' => content_url('/uploads/2026/02/Harmat22_latvany-10-400x225.jpg'),
            'title' => 'Otthonos belső terek',
            'text' => 'Átgondolt alaprajzok, jól használható nappali zónák és családbarát méretek.',
            'alt' => 'Harmat Lakópark belső tér látványterv',
        ),
    );

    $stats = array(
        array('398', 'tervezett lakás'),
        array('124', 'lakás az első ütemben'),
        array('8388 m²', 'első ütem alapterülete'),
        array('2028 Q2', 'várható átadás'),
    );

    $features = array(
        array('Zöld környezet', 'A Harmat utca környéke csendesebb, lakóövezeti hangulatot ad, miközben a mindennapi szolgáltatások gyorsan elérhetők.'),
        array('Átlátható lakásválaszték', 'Stúdiótól nagyobb családi otthonokig több méret és alaprajz érhető el, online lakáskeresővel és virtuális épületválasztóval.'),
        array('Parkolás és tárolás', 'Az első ütemhez 124 mélygarázs parkoló és 92 tároló kapcsolódik, hogy a mindennapi használat kényelmesebb legyen.'),
        array('Tiszta értékesítési folyamat', 'A lakások aktuális adatai, árai és elérhetőségei egy központi rendszerből frissülnek.'),
    );

    $html = '<main id="main" class="site-main harmat-project-modern" role="main">';
    $html .= '<article id="post-1777" class="post-1777 page type-page status-publish hentry"><div class="entry-content">';
    $html .= '<section class="harmat-project-hero"><div class="harmat-project-hero-text"><span>Budapest X. kerület</span><div class="harmat-project-opening"><strong>2026. június 12.</strong><b>Alapkőletételi ünnepség és hivatalos értékesítési nyitás</b><small>Az első ütem lakásairól ettől a naptól részletes tájékoztatás és ajánlatkérés érhető el.</small></div><h1>Harmat Lakópark</h1><p>Modern új építésű otthonok a Harmat utca 22. alatt, zöldebb lakókörnyezettel, átgondolt alaprajzokkal és átlátható értékesítési adatokkal.</p><div class="harmat-project-actions"><a href="' . esc_url(home_url('/lakaskereso/')) . '">Lakáskereső</a><a href="' . esc_url(home_url('/virtualis-lakasvalaszto/')) . '">Virtuális lakásválasztó</a><a href="' . esc_url(home_url('/elerhetosegeink/')) . '">Kapcsolat</a></div></div><figure class="harmat-project-hero-image"><img src="' . esc_url($hero_image) . '" alt="Harmat Lakópark madártávlati látványterv" loading="eager" decoding="async" fetchpriority="high"><figcaption>Harmat Lakópark - első ütem</figcaption></figure></section>';
    $html .= '<section class="harmat-project-stats" aria-label="Projekt adatok">';
    foreach ($stats as $stat) {
        $html .= '<div><strong>' . esc_html($stat[0]) . '</strong><span>' . esc_html($stat[1]) . '</span></div>';
    }
    $html .= '</section>';
    $html .= '<section class="harmat-project-copy"><div><span>Otthon a Harmat utcában</span><h2>Nyugodt lakóparki környezet, városi kapcsolatokkal</h2></div><p>A Harmat Lakópark több ütemben megvalósuló lakóprojekt. Az első ütem 124 lakással indul, a teljes fejlesztés tervezetten 398 lakást foglal magában. A cél egy letisztult, könnyen fenntartható, jól használható otthonokat kínáló lakópark Budapest X. kerületében.</p></section>';
    $html .= '<section class="harmat-project-gallery" aria-label="Projekt képek">';
    foreach ($gallery as $item) {
        $html .= '<article><img src="' . esc_url($item['url']) . '" alt="' . esc_attr($item['alt']) . '" loading="lazy" decoding="async"><div><h3>' . esc_html($item['title']) . '</h3><p>' . esc_html($item['text']) . '</p></div></article>';
    }
    $html .= '</section>';
    $html .= '<section class="harmat-project-features" aria-label="Projekt jellemzők">';
    foreach ($features as $feature) {
        $html .= '<article><h3>' . esc_html($feature[0]) . '</h3><p>' . esc_html($feature[1]) . '</p></article>';
    }
    $html .= '</section>';
    $html .= '<section class="harmat-project-note"><h2>Fontos értékesítési információk</h2><p>Az alapkőletételi ünnepség és a hivatalos értékesítési nyitás 2026. június 12-én lesz. A földszinti lakásokhoz kapcsolódó kertek ajándékként kerülnek feltüntetésre; a végleges műszaki és szerződéses feltételeket minden esetben az értékesítési csapat erősíti meg.</p></section>';
    $html .= '</div></article></main>';

    return $html;
}

function hm_migrated_replace_project_page($html) {
    if (hm_migrated_request_path() !== 'harmat-lakopark' || !is_string($html) || $html === '') {
        return $html;
    }

    if (!preg_match('~<main\b[^>]*\bid=(["\'])main\1[^>]*>~i', $html, $match, PREG_OFFSET_CAPTURE)) {
        return $html;
    }

    $start = $match[0][1];
    $end = stripos($html, '</main>', $start);
    if ($end === false) {
        return $html;
    }

    return substr($html, 0, $start) . hm_migrated_project_page_html() . substr($html, $end + 7);
}

function hm_migrated_replace_clean_footer($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $footer = hm_migrated_clean_footer_html();
    $next = preg_replace('~<footer\b(?=[^>]*\bid=(["\'])colophon\1)[\s\S]*?</footer>~i', $footer, $html, 1, $count);
    if (is_string($next) && $count > 0) {
        return $next;
    }

    $body_pos = strripos($html, '</body>');
    if ($body_pos === false) {
        return $html . $footer;
    }

    return substr($html, 0, $body_pos) . $footer . substr($html, $body_pos);
}

function hm_migrated_remove_legacy_canvas_menu($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $next = preg_replace('~<nav\b(?=[^>]*\bid=(["\'])opal-canvas-menu\1)[\s\S]*?</nav>~i', '', $html, 1);
    return is_string($next) ? $next : $html;
}

function hm_migrated_remove_footer_popup_menu_template($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $offset = 0;
    while (preg_match('~<div\b[^>]*\bdata-elementor-type=(["\'])popup\1[^>]*>~i', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $pos = $match[0][1];
        $end = hm_migrated_find_balanced_div_end($html, $pos);
        if ($end === false || $end <= $pos) {
            $offset = $pos + strlen($match[0][0]);
            continue;
        }

        $chunk = substr($html, $pos, $end - $pos);
        $is_menu_popup = stripos($chunk, 'id="popupmenu"') !== false
            || stripos($chunk, "id='popupmenu'") !== false
            || stripos($chunk, 'elementor-id="3527"') !== false
            || stripos($chunk, "elementor-id='3527'") !== false;
        $has_legacy_tail = stripos($chunk, 'Magunkról') !== false
            && stripos($chunk, 'Szolgáltatásaink') !== false
            && stripos($chunk, 'Harmat Lakópark címe') !== false;

        if (!$is_menu_popup || !$has_legacy_tail) {
            $offset = $end;
            continue;
        }

        $html = substr($html, 0, $pos) . '<!-- harmat-footer-popup-menu-template-removed -->' . substr($html, $end);
        $offset = $pos;
    }

    return $html;
}

function hm_migrated_remove_legacy_elementor_footer_nav($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $offset = 0;
    while (preg_match('~<div\b[^>]*(?:elementor-widget-icon-list|elementor-icon-list)[^>]*>~i', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $pos = $match[0][1];
        $end = hm_migrated_find_balanced_div_end($html, $pos);
        if ($end === false || $end <= $pos) {
            $offset = $pos + strlen($match[0][0]);
            continue;
        }

        $chunk = substr($html, $pos, $end - $pos);
        $is_legacy_footer_menu = stripos($chunk, 'Magunkról') !== false
            && stripos($chunk, 'Szolgáltatásaink') !== false
            && stripos($chunk, 'Elérhetőségek') !== false;
        if (!$is_legacy_footer_menu) {
            $offset = $end;
            continue;
        }

        $html = substr($html, 0, $pos) . '<!-- harmat-legacy-footer-nav-removed -->' . substr($html, $end);
        $offset = $pos;
    }

    return $html;
}

function hm_migrated_normalize_area_formatting($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $next = preg_replace_callback(
        '~(?<![A-Za-z0-9])([0-9]{1,3})[\.,]([0-9]{1,2})\s*m(?:2|²|&sup2;)(?![A-Za-z0-9])~iu',
        function ($match) {
            return $match[1] . ',' . str_pad($match[2], 2, '0') . ' m²';
        },
        $html
    );

    return is_string($next) ? $next : $html;
}

function hm_migrated_normalize_counter_units($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $original = $html;
    $html = preg_replace('~(<span\b[^>]*\belementor-counter-number-suffix\b[^>]*>\s*)m2(\s*</span>)~i', '$1m²$2', $html);
    $html = preg_replace('~(8388)\s*m2\b~i', '$1 m²', $html);

    return is_string($html) ? $html : $original;
}

function hm_migrated_hidden_form_id($chunk) {
    if (preg_match('~<div\b[^>]*\bid=(["\'])([^"\']*opal-contactform-popup[^"\']*)\1~i', $chunk, $match)) {
        return $match[2];
    }

    return '';
}

function hm_migrated_hidden_form_key($chunk) {
    $form_id = '';
    if (preg_match('~data-wpcf7-id=(["\'])([0-9]+)\1~i', $chunk, $match)) {
        $form_id = $match[2];
    } elseif (preg_match('~<input\b[^>]*\bname=(["\'])_wpcf7\1[^>]*\bvalue=(["\'])([0-9]+)\2~i', $chunk, $match)) {
        $form_id = $match[3];
    }

    $title = '';
    if (preg_match('~<div\b[^>]*\bclass=(["\'])form-title\1[^>]*>(.*?)</div>~is', $chunk, $match)) {
        $title = trim(wp_strip_all_tags($match[2]));
    }

    if ($form_id === '8761') {
        return 'property-offer-' . $form_id;
    }

    if ($form_id !== '') {
        return 'appointment-' . $form_id;
    }

    return strtolower(md5($title . '|' . substr(wp_strip_all_tags($chunk), 0, 240)));
}

function hm_migrated_prepare_hidden_form_chunk($chunk) {
    if (!is_string($chunk) || $chunk === '') {
        return $chunk;
    }

    $chunk = preg_replace_callback('~<div\b([^>]*)>~i', function ($match) {
        $attrs = $match[1];
        if (stripos($attrs, 'contactform-content') === false || stripos($attrs, 'opal-contactform-popup') === false) {
            return $match[0];
        }
        if (stripos($attrs, 'aria-hidden=') === false) {
            $attrs .= ' aria-hidden="true"';
        }
        if (stripos($attrs, 'data-harmat-hidden-modal=') === false) {
            $attrs .= ' data-harmat-hidden-modal="1"';
        }
        if (stripos($attrs, 'inert') === false) {
            $attrs .= ' inert';
        }
        return '<div' . $attrs . '>';
    }, $chunk, 1);

    return is_string($chunk) ? $chunk : '';
}

function hm_migrated_find_balanced_div_end($html, $start) {
    $depth = 0;
    $offset = $start;
    while (preg_match('~</?div\b[^>]*>~i', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $tag = $match[0][0];
        $pos = $match[0][1];
        if (stripos($tag, '</div') === 0) {
            $depth--;
            if ($depth === 0) {
                return $pos + strlen($tag);
            }
        } elseif (substr($tag, -2) !== '/>') {
            $depth++;
        }
        $offset = $pos + strlen($tag);
    }

    return false;
}

function hm_migrated_move_hidden_forms_to_footer($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $chunks = array();
    $seen = array();
    $retarget = array();
    $offset = 0;
    $marker = '<!-- harmat-hidden-form-moved -->';
    while (($pos = stripos($html, '<div', $offset)) !== false) {
        $tag_end = strpos($html, '>', $pos);
        if ($tag_end === false) {
            break;
        }

        $tag = substr($html, $pos, $tag_end - $pos + 1);
        $is_hidden_contact_form = stripos($tag, 'opal-contactform-popup') !== false
            && stripos($tag, 'mfp-hide') !== false
            && stripos($tag, 'contactform-content') !== false;
        if (!$is_hidden_contact_form) {
            $offset = $tag_end + 1;
            continue;
        }

        $end = hm_migrated_find_balanced_div_end($html, $pos);
        if ($end === false || $end <= $pos) {
            $offset = $tag_end + 1;
            continue;
        }

        $chunk = substr($html, $pos, $end - $pos);
        $id = hm_migrated_hidden_form_id($chunk);
        $key = hm_migrated_hidden_form_key($chunk);
        if (isset($seen[$key])) {
            if ($id !== '' && $seen[$key] !== '') {
                $retarget[$id] = $seen[$key];
            }
        } else {
            $seen[$key] = $id;
            $chunks[] = hm_migrated_prepare_hidden_form_chunk($chunk);
        }
        $html = substr($html, 0, $pos) . $marker . substr($html, $end);
        $offset = $pos + strlen($marker);
    }

    foreach ($retarget as $old_id => $new_id) {
        $html = str_replace(array('#' . $old_id, rawurlencode('#' . $old_id)), array('#' . $new_id, rawurlencode('#' . $new_id)), $html);
    }

    if (!$chunks) {
        return $html;
    }

    $forms = "\n" . implode("\n", $chunks) . "\n";
    $body_pos = strripos($html, '</body>');
    if ($body_pos === false) {
        return $html . $forms;
    }

    return substr($html, 0, $body_pos) . $forms . substr($html, $body_pos);
}

function hm_migrated_public_html_cleanup($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $original_html = $html;
    if (hm_migrated_is_portal_request_path()) {
        return $original_html;
    }

    $replacements = array(
        'Harmatliget lakópark' => 'Harmat Lakópark',
        'Harmatliget Lakópark' => 'Harmat Lakópark',
        'Harmatliget' => 'Harmat Lakópark',
        'Harmat 22 Lakópark' => 'Harmat Lakópark',
        'Harmat 22 lakópark' => 'Harmat Lakópark',
        'Harmat 22 értékesítés' => 'Harmat Lakópark értékesítés',
        'Harmat 22' => 'Harmat Lakópark',
        'harmat lakópark' => 'Harmat Lakópark',
        'Harmat lakópark' => 'Harmat Lakópark',
        'Harmat lakópark címe' => 'Harmat Lakópark címe',
        'Harmat lakópark környéke' => 'Harmat Lakópark környéke',
        'Gipsz Jakab' => 'Harmat Lakópark értékesítés',
        '012-888-2222' => '+36300733375',
        '012 888 2222' => '+36300733375',
        'agent.name@example.com' => 'ertekesites@harmat22.hu',
        'mailto:agent.name@example.com' => 'mailto:ertekesites@harmat22.hu',
        'modumkft@gmail.com' => 'ertekesites@harmat22.hu',
        'mailto:modumkft@gmail.com' => 'mailto:ertekesites@harmat22.hu',
        '50 m² alatt' => '50&nbsp;m² alatt',
        '50 - 100 m²' => '50&nbsp;-&nbsp;100&nbsp;m²',
        'Marketing Consent' => '',
    );

    $html = strtr($html, $replacements);
    $sqm = html_entity_decode('&sup2;', ENT_QUOTES, 'UTF-8');
    $html = str_replace('50 m' . $sqm . ' alatt', '50&nbsp;m' . $sqm . ' alatt', $html);
    $html = str_replace('50 - 100 m' . $sqm, '50&nbsp;-&nbsp;100&nbsp;m' . $sqm, $html);
    $html = preg_replace('~(<option\s+value=(["\'])0-50\2[^>]*>)50\s+m(?:²|&sup2;)\s+alatt(</option>)~i', '${1}50&nbsp;m² alatt$3', $html);
    $html = preg_replace('~(<option\s+value=(["\'])50-100\2[^>]*>)50\s*-\s*100\s+m(?:²|&sup2;)(</option>)~i', '${1}50&nbsp;-&nbsp;100&nbsp;m²$3', $html);
    $html = hm_migrated_normalize_counter_units($html);
    if (!is_string($html)) {
        return $original_html;
    }
    $html = str_ireplace('Harmat 22 Lakópark', 'Harmat Lakópark', $html);
    $html = preg_replace('~\bÉrtékesítési vezető\b~u', 'Értékesítési csapat', $html);
    $html = preg_replace('~\bértékesítési vezető\b~u', 'Harmat Lakópark értékesítés', $html);
    if (!is_string($html)) {
        return $original_html;
    }
    $html = hm_migrated_normalize_area_formatting($html);

    $html = preg_replace(
        '~\s*<a\b[^>]*href=(["\'])[^"\']*/marketing-hozzajarulas/?\1[^>]*>\s*</a>~i',
        '',
        $html
    );
    if (!is_string($html)) {
        return $original_html;
    }

    if (is_singular('property')) {
        $html = str_replace('Terasz/Erkély', 'Terasz / erkély', $html);
        $html = preg_replace('~<h2>\s*([0-9]+)\.([0-9]{1,2})\s*m(?:²|2|&sup2;)\s*</h2>~i', '<h2>$1,$2 m²</h2>', $html);
        if (!is_string($html)) {
            return $original_html;
        }
        $html = preg_replace('~<h2>\s*([0-9]+),([0-9]{1,2})\s*m(?:²|2|&sup2;)\s*</h2>~i', '<h2>$1,$2 m²</h2>', $html);
        if (!is_string($html)) {
            return $original_html;
        }
        $html = preg_replace(
            '~<div\b(?=[^>]*\belementor-widget-text-editor\b)[^>]*>\s*<h6>\s*Kert\s*</h6>\s*<h2>\s*0\s*m(?:²|2|&sup2;)\s*</h2>\s*</div>~i',
            '',
            $html
        );
        if (!is_string($html)) {
            return $original_html;
        }
        $html = hm_migrated_replace_property_header($html);
    }

    if (hm_migrated_request_path() === 'virtualis-lakasvalaszto') {
        $html = hm_migrated_insert_after_page_title($html, hm_migrated_virtual_selector_static_html());
    }

    if (hm_migrated_request_path() === 'harmat-lakopark') {
        $html = hm_migrated_replace_project_page($html);
    }

    $html = hm_migrated_replace_info_page($html);

    if (false && is_front_page()) {
        /* disabled during earlier homepage rollback */
        /* disabled during earlier homepage rollback */
    }

    $html = hm_migrated_move_hidden_forms_to_footer($html);
    $html = hm_migrated_replace_clean_footer($html);
    $html = hm_migrated_remove_legacy_canvas_menu($html);
    $html = hm_migrated_remove_footer_popup_menu_template($html);
    $html = hm_migrated_remove_legacy_elementor_footer_nav($html);

    if (strlen(trim($original_html)) >= 1000 && (!is_string($html) || strlen(trim($html)) < 1000)) {
        return $original_html;
    }

    return is_string($html) ? $html : $original_html;
}

function hm_migrated_strip_private_portal_footer($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $path = hm_migrated_request_path();
    if (!preg_match('~^(sales|agent|client|customer|ugyfel|belepes|sales-admin|lawyer)(/|$)~i', $path)) {
        return $html;
    }

    $patterns = array(
        '~<footer\\b[^>]*\\bid\\s*=\\s*(?:colophon|[\'"]colophon[\'"])[^>]*>\\s*[\\s\\S]*?</footer>~i',
        '~<footer\\b[^>]*\\bclass\\s*=\\s*(?:[\'"][^\'"]*site-footer[^\'"]*[\'"]|[^\\s>\'"]*site-footer[^\\s>\'"]*)[^>]*>\\s*[\\s\\S]*?</footer>~i',
        '~<footer\\b[^>]*\\brole\\s*=\\s*(?:contentinfo|[\'"]contentinfo[\'"])[^>]*>\\s*[\\s\\S]*?</footer>~i',
    );

    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, '', $html);
    }

    return $html;
}

function hm_migrated_start_private_portal_html_filter() {
    if (is_admin() || wp_doing_ajax() || defined('DOING_CRON')) {
        return;
    }

    if (!hm_migrated_is_portal_request_path()) {
        return;
    }

    ob_start('hm_migrated_strip_private_portal_footer');
}

add_action('init', 'hm_migrated_start_private_portal_html_filter', 1);

add_action('template_redirect', function () {
    if (!hm_migrated_is_public_request()) {
        return;
    }

    $path = hm_migrated_request_path();
    if ($path === 'kapcsolat') {
        wp_safe_redirect(home_url('/elerhetosegeink/'), 301);
        exit;
    }
    if ($path === 'a-lakopark' || $path === 'blog') {
        wp_safe_redirect(home_url('/harmat-lakopark/'), 301);
        exit;
    }
    if ($path === 'apartment') {
        wp_safe_redirect(home_url('/lakaskereso/'), 301);
        exit;
    }

    ob_start('hm_migrated_public_html_cleanup');
}, 1);

add_filter('wpseo_sitemap_exclude_post_type', function ($excluded, $post_type) {
    if (in_array($post_type, array('header', 'footer', 'elementor_library'), true)) {
        return true;
    }

    return $excluded;
}, 10, 2);

add_filter('wpseo_sitemap_exclude_taxonomy', function ($excluded, $taxonomy) {
    if (in_array($taxonomy, array('location', 'tax_feature', 'osf_property_category'), true)) {
        return true;
    }

    return $excluded;
}, 10, 2);

function hm_migrated_seo_page_data() {
    if (is_front_page() || is_home()) {
        return array(
            'description' => 'Harmat Lakópark Budapest X. kerületében, a Harmat utca 22. alatt. Modern új építésű lakások, zöld környezet, lakáskereső és virtuális lakásválasztó.',
            'og_image' => 'https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-3.jpg',
        );
    }

    if (is_page('lakaskereso')) {
        return array(
            'title' => 'Lakáskereső | Új építésű lakások Budapest X. kerületében',
            'description' => 'Keressen új építésű lakást a Harmat Lakóparkban, Budapest X. kerületében. Szűrés épület, emelet, szobaszám, alapterület és elérhetőség szerint.',
            'og_image' => 'https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-3.jpg',
        );
    }

    if (hm_migrated_request_path() === 'finanszirozas') {
        return array(
            'title' => 'Finanszírozás | Harmat Lakópark',
            'description' => 'Finanszírozási és fizetési ütemezési tájékoztató a Harmat Lakópark új építésű lakásaihoz.',
            'og_image' => 'https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-3.jpg',
        );
    }

    if (hm_migrated_request_path() === 'epitesi-naplo') {
        return array(
            'title' => 'Építési napló | Harmat Lakópark',
            'description' => 'Harmat Lakópark építési napló és projektfrissítések, az első hivatalos mérföldkővel: 2026. június 12.',
            'og_image' => 'https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-3.jpg',
        );
    }

    return null;
}

add_filter('pre_get_document_title', function ($title) {
    $data = hm_migrated_seo_page_data();
    if ($data && !empty($data['title'])) {
        return $data['title'];
    }

    return $title;
}, 20);

add_filter('wpseo_title', function ($title) {
    $data = hm_migrated_seo_page_data();
    if ($data && !empty($data['title'])) {
        return $data['title'];
    }

    return str_ireplace('Harmat 22 Lakópark', 'Harmat Lakópark', $title);
}, 99);

add_filter('wpseo_metadesc', function ($description) {
    $data = hm_migrated_seo_page_data();
    return $data && !empty($data['description']) ? $data['description'] : hm_migrated_public_html_cleanup($description);
}, 20);

add_filter('wpseo_opengraph_desc', function ($description) {
    $data = hm_migrated_seo_page_data();
    return $data && !empty($data['description']) ? $data['description'] : hm_migrated_public_html_cleanup($description);
}, 20);

add_filter('wpseo_twitter_description', function ($description) {
    $data = hm_migrated_seo_page_data();
    return $data && !empty($data['description']) ? $data['description'] : hm_migrated_public_html_cleanup($description);
}, 20);

add_filter('wpseo_opengraph_image', function ($image) {
    $data = hm_migrated_seo_page_data();
    return $data && !empty($data['og_image']) ? $data['og_image'] : $image;
}, 20);

add_action('wpseo_add_opengraph_images', function ($image_container) {
    $data = hm_migrated_seo_page_data();
    if ($data && !empty($data['og_image']) && is_object($image_container) && method_exists($image_container, 'add_image')) {
        $image_container->add_image($data['og_image']);
    }
}, 20);

add_filter('wpseo_twitter_image', function ($image) {
    $data = hm_migrated_seo_page_data();
    return $data && !empty($data['og_image']) ? $data['og_image'] : $image;
}, 20);

add_filter('wpseo_robots', function ($robots) {
    if (in_array(hm_migrated_request_path(), array('blog', 'a-lakopark'), true)) {
        return 'noindex,follow';
    }

    return $robots;
}, 30);

add_filter('wpseo_sitemap_entry', function ($url, $type, $object) {
    if (!is_array($url) || empty($url['loc'])) {
        return $url;
    }

    $path = trim((string) wp_parse_url($url['loc'], PHP_URL_PATH), '/');
    if (in_array($path, array('blog', 'a-lakopark', 'marketing-hozzajarulas'), true)) {
        return false;
    }

    if (is_object($object) && isset($object->ID) && in_array((int) $object->ID, array(174, 10513, 10539, 6219), true)) {
        return false;
    }

    return $url;
}, 20, 3);

add_action('wp_head', function () {
    if (hm_migrated_is_public_request()) {
        ?>
<style id="harmat-migrated-public-layout-css">
.harmat-clean-footer{background:#fff7e8;border-top:1px solid rgba(152,112,51,.22);font-family:Montserrat,Arial,sans-serif;color:#263135}
.harmat-clean-footer-inner{max-width:1180px;margin:0 auto;padding:34px 24px 24px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:34px}
.harmat-clean-footer h2{margin:0 0 14px;color:#987033;font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
.harmat-clean-footer nav{display:grid;gap:8px}
.harmat-clean-footer a{color:#263135!important;text-decoration:none;font-size:14px;font-weight:700;line-height:1.45}
.harmat-clean-footer a:hover{color:#987033!important}
.harmat-clean-footer p{margin:0 0 12px;color:#4f575d;font-size:13px;line-height:1.65}
.harmat-clean-footer strong{color:#987033;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
.harmat-clean-footer-bottom{border-top:1px solid rgba(152,112,51,.16);padding:14px 24px 18px;text-align:center;color:#687078;font-size:12px;line-height:1.6}
body:not(.elementor-editor-active) .harmat-ai-assistant,body:not(.elementor-editor-active) .harmat-ai-toggle{display:none!important}
body:not(.elementor-editor-active) .harmat-local-ai-launch{right:18px!important;bottom:max(22px,env(safe-area-inset-bottom,0px) + 22px)!important;z-index:100080!important;box-sizing:border-box!important;isolation:isolate!important;transform:none!important}
body:not(.elementor-editor-active) .harmat-local-ai-panel{right:18px!important;bottom:max(88px,env(safe-area-inset-bottom,0px) + 88px)!important;z-index:100081!important}
body:not(.elementor-editor-active) .scrollup{right:22px!important;bottom:max(92px,env(safe-area-inset-bottom,0px) + 92px)!important;z-index:99960!important}
body.dialog-lightbox-body .scrollup,body.dialog-container .scrollup{display:none!important}
.harmat-home-opening-notice{max-width:1180px;margin:96px auto 0;padding:13px 20px;border:1px solid rgba(152,112,51,.24);border-left:4px solid #987033;background:#fff7e8;display:block;position:relative;z-index:3;font-family:Montserrat,Arial,sans-serif;color:#263135;box-shadow:0 12px 30px rgba(38,49,53,.06)}
.harmat-home-opening-notice div{display:flex;align-items:center;justify-content:center;gap:12px;min-width:0;text-align:center}
.harmat-home-opening-notice strong{flex:0 0 auto;color:#987033;font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.harmat-home-opening-notice span{color:#263135;font-size:13px;font-weight:900;letter-spacing:.02em;text-transform:uppercase;line-height:1.35}
.harmat-home-partners{max-width:1180px;margin:42px auto 30px;padding:0 24px;font-family:Montserrat,Arial,sans-serif;color:#263135}
.harmat-home-partners-head{display:grid;grid-template-columns:minmax(240px,.72fr) minmax(0,1.28fr);gap:22px;align-items:end;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid rgba(152,112,51,.18)}
.harmat-home-partners-head span{display:block;margin-bottom:7px;color:#987033;font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
.harmat-home-partners-head h2{margin:0;color:#263135;font-family:Marcellus,Georgia,serif;font-size:clamp(30px,4vw,48px);line-height:1.05}
.harmat-home-partners-head p{margin:0;color:#50585d;font-size:15px;line-height:1.75}
.harmat-home-partners-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.harmat-home-partners-grid article{position:relative;min-height:168px;padding:22px 20px;border:1px solid rgba(152,112,51,.2);background:linear-gradient(180deg,#fffdf8 0%,#fff7e8 100%);box-shadow:0 16px 36px rgba(38,49,53,.06);overflow:hidden}
.harmat-home-partners-grid article:before{content:"";position:absolute;left:20px;top:0;width:44px;height:4px;background:#987033}
.harmat-home-partners-grid span{display:block;margin:8px 0 12px;color:#987033;font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.harmat-home-partners-grid h3{margin:0 0 10px;color:#263135;font-size:20px;font-weight:900;line-height:1.18}
.harmat-home-partners-grid p{margin:0;color:#50585d;font-size:13px;line-height:1.7}
.harmat-home-partners-link{display:inline-flex;align-items:center;justify-content:center;min-height:40px;margin-top:14px;padding:0 15px;border:1px solid #987033;background:#fff;color:#987033!important;font-size:12px;font-weight:900;text-decoration:none;text-transform:uppercase}
.harmat-home-featured{max-width:1180px;margin:38px auto 46px;padding:0 24px;font-family:Montserrat,Arial,sans-serif;color:#263135}
.harmat-home-featured-head{display:flex;align-items:end;justify-content:space-between;gap:18px;margin-bottom:18px;border-bottom:1px solid rgba(152,112,51,.18);padding-bottom:16px}
.harmat-home-featured-head span{display:block;margin-bottom:6px;color:#987033;font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
.harmat-home-featured-head h2{margin:0;color:#263135;font-family:Marcellus,Georgia,serif;font-size:clamp(30px,4vw,48px);line-height:1.05}
.harmat-home-featured-head a{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 15px;border:1px solid #987033;background:#fff;color:#987033!important;font-size:12px;font-weight:900;text-decoration:none;text-transform:uppercase}
.harmat-home-featured-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.harmat-home-featured-card{border:1px solid rgba(152,112,51,.2);border-radius:6px;background:#fffaf1;overflow:hidden;box-shadow:0 14px 32px rgba(38,49,53,.06)}
.harmat-home-featured-card-top{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 18px;border-bottom:1px solid rgba(152,112,51,.16);background:#fff}
.harmat-home-featured-card-top span{color:#17875b;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
.harmat-home-featured-card h3{margin:0;color:#263135;font-size:21px;font-weight:900;line-height:1;text-transform:uppercase}
.harmat-home-featured-reason{margin:0;padding:11px 18px 0;color:#987033;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
.harmat-home-featured-card dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));margin:0}
.harmat-home-featured-card dl div{min-height:74px;padding:14px 16px;border-right:1px solid rgba(152,112,51,.14);border-bottom:1px solid rgba(152,112,51,.14)}
.harmat-home-featured-card dl div:nth-child(2n){border-right:0}
.harmat-home-featured-card dt{margin:0 0 7px;color:#987033;font-size:11px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}
.harmat-home-featured-card dd{margin:0;color:#263135;font-size:15px;font-weight:900;line-height:1.25}
.harmat-home-featured-actions{display:grid;grid-template-columns:1fr 1fr}
.harmat-home-featured-actions a{display:flex;align-items:center;justify-content:center;min-height:42px;padding:0 12px;border-top:1px solid rgba(152,112,51,.18);background:#fff;color:#987033!important;font-size:12px;font-weight:900;text-decoration:none;text-transform:uppercase;text-align:center}
.harmat-home-featured-actions a:last-child{background:#987033;color:#fff!important}
.harmat-project-modern{background:#fff}
.harmat-project-modern .entry-content{max-width:1180px;margin:0 auto;padding:34px 24px 58px;font-family:Montserrat,Arial,sans-serif;color:#263135}
.harmat-project-hero{padding:54px 0 34px;border-bottom:1px solid rgba(152,112,51,.18);display:grid;grid-template-columns:minmax(0,.88fr) minmax(420px,1.12fr);gap:34px;align-items:center}
.harmat-project-hero span,.harmat-project-copy span{display:block;margin-bottom:10px;color:#987033;font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
.harmat-project-opening{display:grid;gap:5px;max-width:560px;margin:0 0 20px;padding:14px 16px;border-left:4px solid #987033;background:#fff7e8;box-shadow:0 12px 30px rgba(38,49,53,.07)}
.harmat-project-opening strong{color:#987033;font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.harmat-project-opening b{color:#263135;font-size:17px;font-weight:900;line-height:1.32}
.harmat-project-opening small{color:#5d666c;font-size:13px;font-weight:600;line-height:1.55}
.harmat-project-hero h1{margin:0 0 16px;color:#263135;font-family:Marcellus,Georgia,serif;font-size:clamp(44px,7vw,86px);line-height:.95}
.harmat-project-hero p{max-width:760px;margin:0;color:#50585d;font-size:18px;line-height:1.7}
.harmat-project-hero-image{position:relative;margin:0;aspect-ratio:16/10;overflow:hidden;border-radius:6px;background:#f4ead8;box-shadow:0 22px 54px rgba(38,49,53,.12)}
.harmat-project-hero-image img{display:block;width:100%;height:100%;object-fit:cover}
.harmat-project-hero-image figcaption{position:absolute;left:16px;bottom:14px;margin:0;padding:7px 10px;background:rgba(255,250,241,.92);color:#987033;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
.harmat-project-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}
.harmat-project-actions a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border:1px solid #987033;background:#fff;color:#987033!important;font-size:12px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}
.harmat-project-actions a:first-child{background:#987033;color:#fff!important}
.harmat-project-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;margin:26px 0;border-top:1px solid rgba(152,112,51,.2);border-left:1px solid rgba(152,112,51,.2)}
.harmat-project-stats div{padding:18px 16px;border-right:1px solid rgba(152,112,51,.2);border-bottom:1px solid rgba(152,112,51,.2);background:#fffaf1}
.harmat-project-stats strong{display:block;margin-bottom:6px;color:#263135;font-size:26px;font-weight:900;line-height:1}
.harmat-project-stats span{color:#987033;font-size:12px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}
.harmat-project-copy{display:grid;grid-template-columns:minmax(240px,.85fr) 1.15fr;gap:40px;align-items:start;margin:34px 0}
.harmat-project-copy h2,.harmat-project-note h2{margin:0;color:#263135;font-family:Marcellus,Georgia,serif;font-size:clamp(30px,4vw,48px);line-height:1.08}
.harmat-project-copy p,.harmat-project-note p{margin:0;color:#50585d;font-size:15px;line-height:1.8}
.harmat-project-gallery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:30px 0}
.harmat-project-gallery article{overflow:hidden;border:1px solid rgba(152,112,51,.2);border-radius:6px;background:#fffaf1}
.harmat-project-gallery img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover}
.harmat-project-gallery div{padding:18px}
.harmat-project-gallery h3{margin:0 0 8px;color:#263135;font-size:17px;font-weight:900}
.harmat-project-gallery p{margin:0;color:#50585d;font-size:13px;line-height:1.65}
.harmat-project-features{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:28px 0}
.harmat-project-features article{border:1px solid rgba(152,112,51,.2);background:#fffaf1;padding:20px}
.harmat-project-features h3{margin:0 0 10px;color:#263135;font-size:17px;font-weight:900}
.harmat-project-features p{margin:0;color:#50585d;font-size:13px;line-height:1.65}
.harmat-project-note{margin-top:30px;padding:24px;border-left:4px solid #987033;background:#fff7e8;display:grid;grid-template-columns:minmax(220px,.7fr) 1.3fr;gap:28px;align-items:start}
.harmat-info-page{background:#fff}
.harmat-info-page .entry-content{max-width:1180px;margin:0 auto;padding:54px 24px 64px;font-family:Montserrat,Arial,sans-serif;color:#263135}
.harmat-info-hero{padding:18px 0 30px;border-bottom:1px solid rgba(152,112,51,.18)}
.harmat-info-hero span{display:block;margin-bottom:10px;color:#987033;font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
.harmat-info-hero h1{margin:0 0 14px;color:#263135;font-family:Marcellus,Georgia,serif;font-size:clamp(42px,6vw,74px);line-height:1}
.harmat-info-hero p{max-width:860px;margin:0;color:#50585d;font-size:17px;line-height:1.75}
.harmat-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin:28px 0}
.harmat-info-grid article,.harmat-build-log-list article{border:1px solid rgba(152,112,51,.2);background:#fffaf1;padding:24px}
.harmat-info-grid h2,.harmat-build-log-list h2,.harmat-info-note h2{margin:0 0 10px;color:#263135;font-size:22px;font-weight:900;line-height:1.25}
.harmat-info-grid p,.harmat-build-log-list p,.harmat-info-note p{margin:0;color:#50585d;font-size:14px;line-height:1.75}
.harmat-info-note{margin-top:26px;padding:24px;border-left:4px solid #987033;background:#fff7e8}
.harmat-info-note a,.harmat-build-log-list a{display:inline-flex;align-items:center;justify-content:center;min-height:40px;margin-top:18px;padding:0 15px;border:1px solid #987033;background:#987033;color:#fff!important;font-size:12px;font-weight:900;text-transform:uppercase;text-decoration:none}
.harmat-build-log-list{display:grid;gap:16px;margin-top:28px}
.harmat-build-log-list time{display:block;margin-bottom:8px;color:#987033;font-size:12px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}
@media(max-width:900px){.harmat-home-opening-notice{margin:88px 18px 0}.harmat-clean-footer-inner,.harmat-home-partners-head,.harmat-project-hero,.harmat-project-copy,.harmat-project-note{grid-template-columns:1fr}.harmat-home-featured-grid,.harmat-home-partners-grid,.harmat-project-stats,.harmat-project-gallery,.harmat-project-features,.harmat-info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.harmat-project-hero-image{min-height:280px}}
@media(max-width:560px){body:not(.elementor-editor-active) .scrollup{display:none!important}body:not(.elementor-editor-active) .harmat-local-ai-launch{right:12px!important;bottom:max(14px,env(safe-area-inset-bottom,0px) + 14px)!important}body:not(.elementor-editor-active) .harmat-local-ai-panel{right:12px!important;bottom:max(72px,env(safe-area-inset-bottom,0px) + 72px)!important}.harmat-clean-footer-inner{grid-template-columns:1fr;padding:28px 18px}.harmat-home-opening-notice{margin:82px 14px 0;padding:12px 14px}.harmat-home-opening-notice div{align-items:flex-start;flex-direction:column;gap:4px;text-align:left}.harmat-home-featured,.harmat-home-partners{margin:28px auto 34px;padding:0 16px}.harmat-home-featured-head{display:block}.harmat-home-featured-head a{margin-top:14px;width:100%}.harmat-home-featured-grid,.harmat-home-partners-grid{grid-template-columns:1fr}.harmat-home-featured-card dl{grid-template-columns:1fr}.harmat-home-featured-card dl div{border-right:0}.harmat-home-featured-actions{grid-template-columns:1fr}.harmat-project-modern .entry-content,.harmat-info-page .entry-content{padding:26px 18px 44px}.harmat-project-hero{padding-top:34px;gap:22px}.harmat-project-actions a{flex:1 1 100%}.harmat-project-stats,.harmat-project-gallery,.harmat-project-features,.harmat-info-grid{grid-template-columns:1fr}.harmat-project-stats strong{font-size:23px}.harmat-project-hero-image{min-height:210px}.harmat-project-hero-image figcaption{left:12px;bottom:12px}}
</style>
        <?php
    }

    if (is_singular('property')) {
        ?>
<style id="harmat-property-hero-css">
.harmat-property-hero{max-width:1180px;margin:96px auto 30px;padding:22px 26px;border:1px solid rgba(152,112,51,.22);border-radius:6px;background:#fffaf1;box-shadow:0 18px 44px rgba(40,34,24,.08);font-family:Montserrat,Arial,sans-serif;color:#263135}
.single-property #page-title-bar,.single-property .elementor-element-6e26d68,.single-property .harmat-front-single-title-panel{display:none!important}
.harmat-property-hero-head{margin-bottom:16px}
.harmat-property-hero-head span{display:block;margin-bottom:8px;color:#987033;font-size:12px;font-weight:900;text-transform:uppercase}
.harmat-property-hero-head h1{margin:0!important;padding:0!important;color:#263135;font-family:Marcellus,Georgia,serif;font-size:clamp(36px,4vw,54px);line-height:.95;text-transform:uppercase}
.harmat-property-hero-title-status{display:none}
.harmat-property-hero-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;margin:0;border-top:1px solid rgba(152,112,51,.18);border-left:1px solid rgba(152,112,51,.18)}
.harmat-property-hero-grid div{min-height:72px;padding:13px 16px;border-right:1px solid rgba(152,112,51,.18);border-bottom:1px solid rgba(152,112,51,.18);background:rgba(255,255,255,.64)}
.harmat-property-hero-grid div.highlight{background:#fff}
.harmat-property-hero-grid div.status dd{color:#17875b;text-transform:uppercase}
.harmat-property-hero-reserved .harmat-property-hero-grid div.status dd{color:#b77a24}
.harmat-property-hero-sold .harmat-property-hero-grid div.status dd{color:#6f7882}
.harmat-property-hero-grid dt{margin:0 0 7px;color:#987033;font-size:11px;font-weight:900;text-transform:uppercase}
.harmat-property-hero-grid dd{margin:0;color:#263135;font-size:16px;font-weight:900;line-height:1.22}
.harmat-property-hero-grid div.highlight dd{font-size:20px}
.harmat-property-hero-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}
.harmat-property-hero-actions a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border:1px solid #987033;background:#fff;color:#987033!important;font-size:12px;font-weight:900;text-transform:uppercase}
.harmat-property-hero-actions a.primary{background:#987033;color:#fff!important}
.harmat-property-hero-note{margin:12px 0 0;color:#626a70;font-size:13px;font-weight:700;line-height:1.5}
.single-property .harmat-property-hero-head{display:flex;align-items:flex-end;justify-content:space-between;gap:22px}
.single-property .harmat-property-hero-title-status{display:block;min-width:154px;padding:11px 14px;border:1px solid rgba(23,135,91,.18);border-left:4px solid #17875b;background:#f6fbf8;box-shadow:0 10px 24px rgba(23,135,91,.08)}
.single-property .harmat-property-hero-title-status span{margin-bottom:6px;color:#7a5520;font-size:10px;letter-spacing:.11em}
.single-property .harmat-property-hero-title-status strong{display:block;color:#17875b;font-size:15px;font-weight:900;line-height:1.2;text-transform:uppercase}
.single-property .harmat-property-hero-grid div.status{display:none!important}
.single-property .elementor-element-03069d8{max-width:1180px!important;margin:0 auto 58px!important;padding:0 26px 8px!important;background:transparent!important;overflow:visible!important}
.single-property .elementor-element-03069d8>.e-con-inner{display:grid!important;grid-template-columns:minmax(280px,520px) minmax(0,1fr)!important;gap:28px!important;align-items:start!important;max-width:1180px!important;margin:0 auto!important;padding:0!important}
.single-property .elementor-element-8ca2fdc,.single-property .elementor-element-638b32a{width:100%!important;min-width:0!important;max-width:100%!important;margin:0!important;position:relative!important;transform:none!important}
.single-property .elementor-element-568dd8b{display:none!important}
.single-property .lakas_galeria,.single-property .lakas_galeria .elementor-widget-container,.single-property .lakas_galeria .elementor-opal-image-gallery{width:100%!important;max-width:100%!important;height:auto!important;margin:0!important;padding:0!important;overflow:visible!important;background:transparent!important}
.single-property .lakas_galeria .isotope-grid,.single-property .lakas_galeria .grid,.single-property .lakas_galeria .row{display:block!important;width:100%!important;height:auto!important;min-height:0!important;margin:0!important;padding:0!important;position:relative!important;overflow:visible!important;background:transparent!important}
.single-property .lakas_galeria .column-item{display:block!important;float:none!important;width:100%!important;max-width:520px!important;height:auto!important;margin:0 auto!important;padding:0!important;position:relative!important;left:auto!important;right:auto!important;top:auto!important;bottom:auto!important;transform:none!important}
.single-property .lakas_galeria a{display:block!important;width:100%!important;height:auto!important;margin:0!important;padding:0!important;position:relative!important;background:#fff!important;border:1px solid rgba(152,112,51,.18)!important;box-shadow:0 14px 36px rgba(40,34,24,.07)!important;overflow:hidden!important}
.single-property .lakas_galeria img{display:block!important;width:100%!important;max-width:100%!important;height:auto!important;min-height:0!important;max-height:none!important;margin:0!important;object-fit:contain!important;opacity:1!important;position:relative!important;z-index:1!important}
.single-property .lakas_galeria .gallery-item-overlay,.single-property .lakas_galeria .opal-icon-zoom{display:none!important}
body.single-property:has(.harmat-property-detail-sample) .elementor-element-03069d8{display:none!important}
.harmat-property-detail-sample{max-width:1180px;margin:0 auto 64px;padding:0 26px 8px;display:grid;grid-template-columns:minmax(470px,1.06fr) minmax(430px,.94fr);gap:20px;align-items:stretch;font-family:Montserrat,Arial,sans-serif;color:#263135}
.harmat-property-detail-media,.harmat-property-detail-info,.harmat-property-detail-rooms{background:#fff;border:1px solid rgba(152,112,51,.18);box-shadow:0 18px 42px rgba(40,34,24,.065)}
.harmat-property-detail-media{padding:28px 30px;background:linear-gradient(180deg,#fffdf8 0%,#fff 100%);display:flex;flex-direction:column}
.harmat-property-detail-info{padding:24px}
.harmat-property-detail-media-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid rgba(152,112,51,.14)}
.harmat-property-detail-rooms-head{margin:0;padding:24px 26px 16px;border-bottom:1px solid rgba(152,112,51,.14);background:#fffdf8}
.harmat-property-detail-media-head span,.harmat-property-detail-info>span,.harmat-property-detail-rooms-head span{display:block;margin-bottom:7px;color:#987033;font-size:11px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}
.harmat-property-detail-media-head h2,.harmat-property-detail-info h2,.harmat-property-detail-rooms-head h3{margin:0;color:#263135;font-family:Marcellus,Georgia,serif;font-size:clamp(26px,3vw,38px);line-height:1.05}
.harmat-property-detail-image{display:flex;align-items:center;justify-content:center;width:100%;max-width:100%;min-height:390px;flex:1;margin:0 auto;padding:14px;overflow:hidden;border:1px solid rgba(152,112,51,.14);background:#f7f0e2}
.harmat-property-detail-image img{display:block;width:100%;max-width:100%;height:auto;max-height:min(70vh,680px);object-fit:contain;background:#fff;box-shadow:0 10px 28px rgba(40,34,24,.08)}
.harmat-property-detail-media p,.harmat-property-detail-info p{margin:16px 0 0;color:#5b6267;font-size:14px;line-height:1.65}
.harmat-property-detail-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));margin:20px 0 0;border-top:1px solid rgba(152,112,51,.16);border-left:1px solid rgba(152,112,51,.16)}
.harmat-property-detail-facts div{min-height:72px;padding:13px 15px;border-right:1px solid rgba(152,112,51,.16);border-bottom:1px solid rgba(152,112,51,.16);background:#fffaf1}
.harmat-property-detail-facts dt{margin:0 0 6px;color:#987033;font-size:10px;font-weight:900;text-transform:uppercase}
.harmat-property-detail-facts dd{margin:0;color:#263135;font-size:15px;font-weight:900;line-height:1.25}
.harmat-property-detail-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.harmat-property-detail-actions a{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 15px;border:1px solid #987033;background:#fff;color:#987033!important;font-size:12px;font-weight:900;text-transform:uppercase;text-decoration:none}
.harmat-property-detail-actions a.primary{background:#987033;color:#fff!important}
.harmat-property-detail-rooms{grid-column:auto;padding:0;overflow:hidden}
.harmat-property-room-table{display:grid;grid-template-columns:1fr;gap:6px;padding:15px 14px 14px}
.harmat-property-room-row{display:grid;grid-template-columns:92px minmax(0,1fr) 82px;gap:10px;align-items:center;min-height:38px;padding:8px 10px;border:1px solid rgba(152,112,51,.12);background:#fff}
.harmat-property-room-row:nth-child(odd){background:#fffaf1}
.harmat-property-room-row span{color:#987033;font-size:10px;font-weight:900;letter-spacing:.03em}
.harmat-property-room-row strong{color:#263135;font-size:14px;font-weight:900}
.harmat-property-room-row em{color:#263135;font-size:14px;font-style:normal;font-weight:900;text-align:right}
.harmat-property-room-total{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:8px;padding:17px 16px;background:#987033;color:#fff;box-shadow:0 10px 22px rgba(152,112,51,.18)}
.harmat-property-room-total span,.harmat-property-room-total strong{font-size:15px;font-weight:900}
@media(max-width:900px){.harmat-property-hero{margin:86px 18px 28px;padding:20px}.harmat-property-hero-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.harmat-property-hero-actions a{flex:1 1 calc(50% - 10px)}.single-property .elementor-element-03069d8{padding:0 18px 4px!important;margin-bottom:42px!important}.single-property .elementor-element-03069d8>.e-con-inner{grid-template-columns:1fr!important;gap:20px!important}.single-property .lakas_galeria .column-item{max-width:560px!important}.harmat-property-detail-sample{grid-template-columns:1fr;padding:0 18px 4px;margin-bottom:42px}.harmat-property-room-table{grid-template-columns:1fr}}
@media(max-width:520px){.harmat-property-hero{margin:82px 14px 24px;padding:18px}.single-property .harmat-property-hero-head{align-items:flex-start;flex-direction:column}.single-property .harmat-property-hero-title-status{width:100%;min-width:0}.harmat-property-hero-grid{grid-template-columns:1fr}.harmat-property-hero-grid div{min-height:auto}.harmat-property-hero-actions a{flex-basis:100%}.harmat-property-hero-head h1{font-size:34px}.harmat-property-detail-sample{padding:0 14px 4px}.harmat-property-detail-media,.harmat-property-detail-info,.harmat-property-detail-rooms{padding:18px}.harmat-property-detail-image{min-height:240px;padding:10px}.harmat-property-detail-image img{max-height:68vh}.harmat-property-detail-facts{grid-template-columns:1fr}.harmat-property-detail-actions a{flex:1 1 100%}.harmat-property-room-row{grid-template-columns:1fr;gap:3px}.harmat-property-room-row em{text-align:left}.harmat-property-room-total{align-items:flex-start;flex-direction:column}}
</style>
        <?php
    }

    if (hm_migrated_request_path() === 'virtualis-lakasvalaszto') {
        ?>
<style id="harmat-virtual-static-intro-css">
.harmat-virtual-static-intro{max-width:1180px;margin:28px auto 30px;padding:24px;border:1px solid rgba(152,112,51,.2);background:#fffaf1;font-family:Montserrat,Arial,sans-serif;box-shadow:0 18px 44px rgba(40,34,24,.07)}
.harmat-virtual-static-intro span{display:block;margin-bottom:8px;color:#987033;font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.harmat-virtual-static-intro h2{margin:0 0 10px;color:#263135;font-family:Marcellus,Georgia,serif;font-size:clamp(28px,3.2vw,42px);line-height:1.1}
.harmat-virtual-static-intro p{max-width:850px;margin:0 0 18px;color:#50585d;font-size:15px;line-height:1.65}
.harmat-virtual-static-intro nav{display:flex;flex-wrap:wrap;gap:10px}
.harmat-virtual-static-intro a{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 14px;border:1px solid rgba(152,112,51,.35);background:#fff;color:#987033!important;font-size:12px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}
.harmat-virtual-static-intro a:last-child{background:#987033;color:#fff!important}
@media(max-width:640px){.harmat-virtual-static-intro{margin:18px 14px 24px;padding:18px}.harmat-virtual-static-intro a{flex:1 1 100%}}
</style>
        <?php
    }

    if (!is_front_page()) {
        return;
    }

    echo '<style id="harmat-home-seo-h1-css">.harmat-seo-h1{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}</style>' . "\n";
}, 20);

add_action('wp_body_open', function () {
    if (!is_front_page()) {
        return;
    }

    echo '<h1 class="harmat-seo-h1">Új építésű lakások Budapest X. kerületében - Harmat Lakópark</h1>' . "\n";
}, 1);

function hm_migrated_legal_links() {
    return array(
        'Adatkezelési tájékoztató' => home_url('/adatvedelmi-tajekoztato/'),
        'Süti tájékoztató' => home_url('/cookie-tajekoztato/'),
        'Felhasználási feltételek' => home_url('/felhasznalasi-feltetelek/'),
        'Impresszum' => home_url('/impresszum/'),
    );
}

add_action('wp_head', function () {
    if (!hm_migrated_is_public_request()) {
        return;
    }
    ?>
<style id="harmat-migrated-public-cleanup-css">
.harmat-property-disclaimer{max-width:1180px;width:calc(100% - 48px);margin:52px auto 0;padding:16px 20px;background:rgba(255,247,232,.92);border-left:4px solid #987033;color:#4b5054;font-family:Montserrat,Arial,sans-serif;font-size:13px;line-height:1.7;text-align:left}
.harmat-property-disclaimer strong{color:#7a5520;font-weight:800}
.harmat-property-status-note{display:inline-flex;align-items:center;gap:8px;margin:10px 0 0;padding:7px 12px;border-radius:999px;background:#17875b;color:#fff;font:700 12px/1.2 Montserrat,Arial,sans-serif;letter-spacing:.04em;text-transform:uppercase}
@media(max-width:640px){.harmat-property-disclaimer{width:auto!important;margin:22px 16px 0;font-size:12px}}
</style>
<script id="harmat-migrated-public-cleanup-js">
(function () {
  function cleanText(value) {
    return (value || '').replace(/\s+/g, ' ').trim();
  }

  function setTextIfExact(from, to) {
    Array.prototype.forEach.call(document.querySelectorAll('body *'), function (el) {
      if (el.children.length) return;
      if (cleanText(el.textContent) === from) el.textContent = to;
    });
  }

  function replaceTextNodes() {
    var replacements = [
      [/Harmatliget lakópark/gi, 'Harmat Lakópark'],
      [/Harmatliget/gi, 'Harmat Lakópark'],
      [/Harmat 22 Lakópark/gi, 'Harmat Lakópark'],
      [/Harmat 22 értékesítés/gi, 'Harmat Lakópark értékesítés'],
      [/Gipsz\s*Jakab/gi, 'Harmat Lakópark értékesítés'],
      [/012[\s-]*888[\s-]*2222/g, '+36300733375'],
      [/agent\.name@example\.com/gi, 'ertekesites@harmat22.hu'],
      [/modumkft@gmail\.com/gi, 'ertekesites@harmat22.hu']
    ];
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    var nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(function (node) {
      var next = node.nodeValue || '';
      replacements.forEach(function (pair) { next = next.replace(pair[0], pair[1]); });
      if (next !== node.nodeValue) node.nodeValue = next;
    });
    document.querySelectorAll('a[href^="mailto:modumkft@gmail.com"],a[href^="mailto:agent.name@example.com"]').forEach(function (a) {
      a.href = 'mailto:ertekesites@harmat22.hu';
    });
  }

  function repairPlaceholderLinks() {
    var links = {
      'terms of use': '/felhasznalasi-feltetelek/',
      'privacy policy': '/adatvedelmi-tajekoztato/',
      'cookie policy': '/cookie-tajekoztato/',
      'impres': '/impresszum/',
      'legal notice': '/impresszum/'
    };
    document.querySelectorAll('a[href="#"], a:not([href])').forEach(function (a) {
      var text = cleanText(a.textContent).toLowerCase();
      Object.keys(links).some(function (key) {
        if (text.indexOf(key) !== -1) {
          a.setAttribute('href', links[key]);
          return true;
        }
        return false;
      });
    });
  }

  function fixSalesContact() {
    setTextIfExact('Harmat Jakab', 'Harmat Lakópark értékesítés');
    setTextIfExact('Értékesítési vezető', 'Értékesítési csapat');
    setTextIfExact('értékesítési vezető', 'Harmat Lakópark értékesítés');
  }

  function fixBrokenLegalFooter() {
    Array.prototype.forEach.call(document.querySelectorAll('footer p, footer .elementor-widget-text-editor p'), function (p) {
      var text = cleanText(p.textContent);
      if (text.indexOf('Terms of use') === -1 && text.indexOf('Adatv?delmi') === -1) return;
      p.innerHTML = '<span translate="no" class="notranslate" style="color:#ffffff;">© Cooperation Power Kft.</span> Minden jog fenntartva <a href="/felhasznalasi-feltetelek/">Felhasználási feltételek</a> és <a href="/adatvedelmi-tajekoztato/">Adatvédelmi tájékoztató</a>';
    });
  }

  function cleanAutoPageMenu() {
    var allowed = [
      '/', '/lakaskereso/', '/virtualis-lakasvalaszto/', '/a-lakopark/', '/harmat-lakopark/',
      '/harmat-lakopark-kornyeke/', '/galeria/', '/elerhetosegeink/',
      '/adatvedelmi-tajekoztato/', '/impresszum/'
    ];
    Array.prototype.forEach.call(document.querySelectorAll('#opal-canvas-menu a[href], .opal-menu-canvas a[href]'), function (a) {
      var url;
      try { url = new URL(a.href, window.location.origin); } catch (e) { return; }
      var path = url.pathname;
      if (path !== '/' && path.slice(-1) !== '/') path += '/';
      if (allowed.indexOf(path) === -1) {
        var item = a.closest('li');
        if (item) item.remove();
      }
    });
  }

  function fixOsszesText() {
    setTextIfExact('?sszes alaprajz', 'Összes alaprajz');
    document.querySelectorAll('a, h1, h2, title').forEach(function (el) {
      if (cleanText(el.textContent) === '?sszes alaprajz') el.textContent = 'Összes alaprajz';
    });
    if (document.title.indexOf('?sszes alaprajz') !== -1) {
      document.title = document.title.replace('?sszes alaprajz', 'Összes alaprajz');
    }
  }

  function clarifyHomeStats() {
    if (!document.body.classList.contains('home')) return;
    Array.prototype.forEach.call(document.querySelectorAll('.elementor-counter-title, .funfact-content, h6, p, span'), function (el) {
      var txt = cleanText(el.textContent).toLowerCase();
      if (txt === 'lakás') el.textContent = 'lakás az első ütemben';
      if (txt === 'alapterület') el.textContent = 'alapterület az első ütemben';
    });
  }

  function addPropertyDisclaimer() {
    if (!document.body.classList.contains('single-property')) return;
    if (document.querySelector('.harmat-property-disclaimer')) return;
    var anchor = document.querySelector('body.single-property .elementor-element[data-elementor-type="wp-post"]') || document.querySelector('body.single-property .site-content') || document.body;
    var note = document.createElement('div');
    note.className = 'harmat-property-disclaimer';
    note.innerHTML = '<strong>Fontos tájékoztatás:</strong> Az árak kizárólag tájékoztató jellegűek, a végleges ár és fizetési feltételek a szerződésben kerülnek rögzítésre. A látványtervek, videók és képek illusztrációk. Az alapterületekben, terasz- és kertadatokban műszaki vagy mérési eltérés előfordulhat.';
    anchor.parentNode.insertBefore(note, anchor.nextSibling);
  }

  function addPropertyStatus() {
    if (!document.body.classList.contains('single-property')) return;
    if (document.querySelector('.harmat-property-hero')) return;
    if (document.querySelector('.harmat-property-status-note')) return;
    var anchor = document.querySelector('.property-title, h1, .entry-title') || document.querySelector('.site-main');
    if (!anchor || !anchor.parentNode) return;
    var note = document.createElement('div');
    note.className = 'harmat-property-status-note';
    note.textContent = 'Státusz: Elérhető';
    anchor.parentNode.insertBefore(note, anchor.nextSibling);
  }

  function removeLegacyPropertyTopRows() {
    if (!document.body.classList.contains('single-property')) return;
    if (!document.querySelector('.harmat-property-hero')) return;

    Array.prototype.forEach.call(document.querySelectorAll('#page-title-bar, .elementor-element-6e26d68'), function (el) {
      if (el && el.parentNode) el.parentNode.removeChild(el);
    });

    Array.prototype.forEach.call(document.querySelectorAll('body.single-property .harmat-front-single-title-panel, body.single-property .e-con, body.single-property .elementor-section, body.single-property .elementor-element, body.single-property [class*="summary"], body.single-property [class*="property"]'), function (el) {
      var hero = document.querySelector('.harmat-property-hero');
      if (!el || !hero || el === hero || el.contains(hero)) return;
      var txt = cleanText(el.textContent);
      var lower = txt.toLowerCase();
      var isLegacySummary = lower.indexOf('lakás ' + 'szám') !== -1 && lower.indexOf('teljes ' + 'ár') !== -1 && lower.indexOf('négyzet' + 'méterár') !== -1;
      var isNewHeroGrid = el.classList && el.classList.contains('harmat-property-hero-grid') && lower.indexOf('státusz') !== -1;
      if ((el.classList && el.classList.contains('harmat-front-single-title-panel')) || (isLegacySummary && !isNewHeroGrid)) {
        el.parentNode.removeChild(el);
      }
    });
  }

  function normalizeListingData() {
    if (!/\/lakaskereso\/?$/.test(window.location.pathname)) return;
    function firstNonEmptyArray() {
      for (var i = 0; i < arguments.length; i++) {
        if (Array.isArray(arguments[i]) && arguments[i].length) return arguments[i];
      }
      return [];
    }
    var unified = window.harmatUnifiedSalesData || {};
    var sales = window.harmatSalesFront || {};
    var items = firstNonEmptyArray(
      sales.items,
      sales.apartments,
      unified.items,
      unified.apartments,
      window.harmatOfferApartments
    );
    window.harmatUnifiedSalesData = unified;
    unified.items = items;
    unified.apartments = items;
    unified.source = unified.source || 'harmat-migrated-snippets';
  }

  function setHiddenModalState(modal, open) {
    if (!modal || !modal.matches || !modal.matches('.contactform-content[id^="opal-contactform-popup"]')) return;
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (open) {
      modal.removeAttribute('inert');
    } else {
      modal.setAttribute('inert', '');
    }
  }

  function modalFromHref(href) {
    if (!href || href.indexOf('#opal-contactform-popup') === -1) return null;
    var hash = href.charAt(0) === '#' ? href : '';
    if (!hash) {
      try { hash = new URL(href, window.location.href).hash || ''; } catch (e) {}
    }
    if (!hash) return null;
    try { return document.getElementById(decodeURIComponent(hash.slice(1))); } catch (e) { return null; }
  }

  function bindHiddenModals() {
    document.querySelectorAll('.contactform-content[id^="opal-contactform-popup"]').forEach(function (modal) {
      if (!modal.closest('.mfp-content')) setHiddenModalState(modal, false);
    });
    if (document.documentElement.dataset.harmatModalStateBound === '1') return;
    document.documentElement.dataset.harmatModalStateBound = '1';
    document.addEventListener('click', function (event) {
      var link = event.target && event.target.closest ? event.target.closest('a[href*="#opal-contactform-popup"], button[data-mfp-src*="#opal-contactform-popup"]') : null;
      if (!link) return;
      var modal = modalFromHref(link.getAttribute('href') || link.getAttribute('data-mfp-src') || '');
      if (modal) setHiddenModalState(modal, true);
    }, true);
    if (window.jQuery) {
      window.jQuery(document).on('mfpBeforeOpen', function () {
        document.querySelectorAll('.contactform-content[id^="opal-contactform-popup"]').forEach(function (modal) { setHiddenModalState(modal, true); });
      });
      window.jQuery(document).on('mfpClose', function () {
        document.querySelectorAll('.contactform-content[id^="opal-contactform-popup"]').forEach(function (modal) { setHiddenModalState(modal, false); });
      });
    }
  }

  function run() {
    replaceTextNodes();
    repairPlaceholderLinks();
    fixSalesContact();
    fixBrokenLegalFooter();
    cleanAutoPageMenu();
    fixOsszesText();
    clarifyHomeStats();
    addPropertyDisclaimer();
    removeLegacyPropertyTopRows();
    addPropertyStatus();
    normalizeListingData();
    bindHiddenModals();
    window.setTimeout(removeLegacyPropertyTopRows, 300);
    window.setTimeout(removeLegacyPropertyTopRows, 1200);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  window.setTimeout(run, 400);
  window.setTimeout(run, 1200);
  window.setTimeout(run, 2500);
})();
</script>
<script id="harmat-conversion-events">
(function () {
  if (window.__harmatConversionEventsReady) return;
  window.__harmatConversionEventsReady = true;

  function push(name, data) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(Object.assign({
      event: name,
      page_path: window.location.pathname,
      page_title: document.title
    }, data || {}));
  }

  function text(el) {
    return (el && el.textContent || '').replace(/\s+/g, ' ').trim();
  }

  document.addEventListener('click', function (event) {
    var target = event.target && event.target.closest ? event.target.closest('a, button, [role="button"], [data-filter], [data-status]') : null;
    if (!target) return;
    var href = target.getAttribute('href') || '';
    if (href.indexOf('tel:') === 0) push('harmat_phone_click', { link_url: href });
    if (href.indexOf('mailto:') === 0) push('harmat_email_click', { link_url: href });
    if (/\.pdf(?:$|[?#])/i.test(href) || /alaprajz/i.test(text(target))) push('harmat_floorplan_download', { link_url: href || '', label: text(target) });
    if (href.indexOf('#opal-contactform-popup') !== -1 || /ajanlat|ajánlat|árajánlat/i.test(text(target))) push('harmat_offer_open', { link_url: href || '', label: text(target) });
    if (target.closest('.harmat-local-ai-launch, .harmat-ai-toggle')) push('harmat_assistant_open');
    if (target.closest('[data-hm-filter]') || target.matches('[data-filter], [data-status]')) push('harmat_lakaskereso_filter_use', { label: text(target), filter: target.getAttribute('data-filter') || target.getAttribute('data-status') || '' });
    if (window.location.pathname.indexOf('/virtualis-lakasvalaszto') === 0 && target.closest('#buildingViewer, .viewer-container, .lakaspark-viewer-section')) push('harmat_virtual_selector_building_click', { label: text(target) });
  }, true);

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.matches || !form.matches('form')) return;
    var formId = form.querySelector('[name="_wpcf7"]');
    var id = formId ? formId.value : '';
    if (id === '1002') push('harmat_appointment_form_submit', { form_id: id });
    if (id === '8761') push('harmat_offer_form_submit', { form_id: id });
    if (form.closest('.harmat-local-ai-panel, .harmat-ai-panel')) push('harmat_assistant_lead_submit');
  }, true);
})();
</script>
    <?php
}, 1005);

add_action('wp_head', function () {
    if (!hm_migrated_is_public_request()) {
        return;
    }
    ?>
<style id="harmat-clean-click-menu-style">
#harmat-clean-menu-modal{position:fixed;inset:0;z-index:999999;display:none;background:rgba(22,27,30,.42);font-family:Montserrat,Arial,sans-serif}
#harmat-clean-menu-modal[aria-hidden="false"]{display:block}
#harmat-clean-menu-modal .harmat-clean-menu-panel{position:absolute;left:0;top:0;width:min(420px,92vw);height:100%;padding:42px 34px 30px;background:#fffaf1;box-shadow:0 28px 80px rgba(0,0,0,.2);overflow:auto}
#harmat-clean-menu-modal .harmat-clean-menu-head{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:28px}
#harmat-clean-menu-modal .harmat-clean-menu-logo{height:58px;width:auto;display:block}
#harmat-clean-menu-modal .harmat-clean-menu-close{width:42px;height:42px;border:1px solid rgba(152,112,51,.28);background:#fff;color:#263135;font-size:28px;line-height:1;cursor:pointer}
#harmat-clean-menu-modal nav{display:grid;gap:8px}
#harmat-clean-menu-modal nav a{display:flex;align-items:center;min-height:44px;border-bottom:1px solid rgba(152,112,51,.16);color:#263135!important;font-size:19px;font-weight:900;text-decoration:none}
#harmat-clean-menu-modal nav a:hover{color:#987033!important}
#harmat-clean-menu-modal .harmat-clean-menu-actions{display:grid;gap:10px;margin-top:28px}
#harmat-clean-menu-modal .harmat-clean-menu-actions a{display:flex;align-items:center;justify-content:center;min-height:42px;border:1px solid #987033;background:#987033;color:#fff!important;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;text-decoration:none}
body.harmat-clean-menu-open{overflow:hidden!important}
@media(max-width:480px){#harmat-clean-menu-modal .harmat-clean-menu-panel{width:100%;padding:34px 26px 26px}#harmat-clean-menu-modal nav a{font-size:18px}}
</style>
<script id="harmat-clean-click-menu-js">
(function () {
  if (window.__harmatCleanClickMenuReady) return;
  window.__harmatCleanClickMenuReady = true;

  function t(points) {
    return String.fromCodePoint.apply(String, points);
  }

  var items = [
    { label: [70,337,111,108,100,97,108], url: '/' },
    { label: [76,97,107,225,115,107,101,114,101,115,337], url: '/lakaskereso/' },
    { label: [86,105,114,116,117,225,108,105,115,32,108,97,107,225,115,118,225,108,97,115,122,116,243], url: '/virtualis-lakasvalaszto/' },
    { label: [72,97,114,109,97,116,32,76,97,107,243,112,97,114,107], url: '/harmat-lakopark/' },
    { label: [77,97,103,117,110,107,114,243,108], url: '/magunkrol/' },
    { label: [75,246,114,110,121,233,107,252,110,107], url: '/harmat-lakopark-kornyeke/' },
    { label: [71,97,108,233,114,105,97], url: '/galeria/' },
    { label: [69,108,233,114,104,101,116,337,115,233,103,101,107], url: '/elerhetosegeink/' }
  ];

  function isLegacyPopupTrigger(link) {
    if (!link || !link.getAttribute) return false;
    var href = link.getAttribute('href') || '';
    var decoded = href;
    try { decoded = decodeURIComponent(href); } catch (e) {}
    return !!(link.closest('.elementor-element-4d7a363') || (decoded.indexOf('popup:open') !== -1 && decoded.indexOf('3527') !== -1));
  }

  function ensureMenu() {
    var existing = document.getElementById('harmat-clean-menu-modal');
    if (existing) return existing;

    var modal = document.createElement('div');
    modal.id = 'harmat-clean-menu-modal';
    modal.setAttribute('aria-hidden', 'true');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');

    var panel = document.createElement('div');
    panel.className = 'harmat-clean-menu-panel';
    modal.appendChild(panel);

    var head = document.createElement('div');
    head.className = 'harmat-clean-menu-head';
    panel.appendChild(head);

    var logo = document.createElement('img');
    logo.className = 'harmat-clean-menu-logo';
    logo.src = 'https://harmat22.hu/wp-content/uploads/2025/11/cropped-Harmat_Logo_250.png';
    logo.alt = 'Harmat';
    head.appendChild(logo);

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'harmat-clean-menu-close';
    close.setAttribute('aria-label', 'Close');
    close.textContent = String.fromCharCode(215);
    head.appendChild(close);

    var nav = document.createElement('nav');
    panel.appendChild(nav);
    items.forEach(function (item) {
      var a = document.createElement('a');
      a.href = item.url;
      a.textContent = t(item.label);
      nav.appendChild(a);
    });

    var actions = document.createElement('div');
    actions.className = 'harmat-clean-menu-actions';
    panel.appendChild(actions);
    var offer = document.createElement('a');
    offer.href = '/elerhetosegeink/';
    offer.textContent = t([75,97,112,99,115,111,108,97,116]);
    actions.appendChild(offer);

    close.addEventListener('click', closeMenu);
    modal.addEventListener('click', function (event) {
      if (event.target === modal) closeMenu();
    });
    modal.addEventListener('click', function (event) {
      if (event.target && event.target.closest && event.target.closest('nav a')) closeMenu();
    });
    document.body.appendChild(modal);
    return modal;
  }

  function openMenu() {
    var modal = ensureMenu();
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('harmat-clean-menu-open');
    var close = modal.querySelector('.harmat-clean-menu-close');
    if (close) close.focus({ preventScroll: true });
  }

  function closeMenu() {
    var modal = document.getElementById('harmat-clean-menu-modal');
    if (!modal) return;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('harmat-clean-menu-open');
  }

  document.addEventListener('click', function (event) {
    var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!isLegacyPopupTrigger(link)) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    openMenu();
  }, true);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeMenu();
  });
})();
</script>
    <?php
}, 60);
