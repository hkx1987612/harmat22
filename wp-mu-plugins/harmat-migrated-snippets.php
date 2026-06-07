<?php
/**
 * Plugin Name: Harmat Migrated Snippet Logic
 * Description: Version-controlled replacement for public cleanup, SEO, legal footer, and legacy text Code Snippets.
 * Version: 2026.06.07.6
 */

defined('ABSPATH') || exit;

function hm_migrated_is_public_request() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    return !function_exists('wp_is_json_request') || !wp_is_json_request();
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
    $card = hm_migrated_property_hero_html(hm_migrated_extract_property_pdf_url($html));
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
        return substr($html, 0, $old_start) . $card . substr($html, $floorplan_start);
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

function hm_migrated_clean_footer_html() {
    $columns = array(
        'Projekt' => array(
            'Főoldal' => home_url('/'),
            'Harmat Lakópark' => home_url('/harmat-lakopark/'),
            'Környékünk' => home_url('/harmat-lakopark-kornyeke/'),
            'Galéria' => home_url('/galeria/'),
        ),
        'Lakások' => array(
            'Lakáskereső' => home_url('/lakaskereso/'),
            'Virtuális lakásválasztó' => home_url('/virtualis-lakasvalaszto/'),
            'Első ütem' => home_url('/virtualis-lakasvalaszto-elso-utem/'),
            'Összes alaprajz' => home_url('/osszes-alaprajz/'),
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
    $html .= '<p><strong>Telefon</strong><br><a href="tel:+36306410358">+36-30-641-03-58</a></p>';
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

        $chunks[] = substr($html, $pos, $end - $pos);
        $html = substr($html, 0, $pos) . $marker . substr($html, $end);
        $offset = $pos + strlen($marker);
    }

    if (!$chunks) {
        return $html;
    }

    $forms = "\n" . implode("\n", $chunks) . "\n";
    $footer_pos = strripos($html, '</footer>');
    if ($footer_pos !== false) {
        $insert_pos = $footer_pos + 9;
        return substr($html, 0, $insert_pos) . $forms . substr($html, $insert_pos);
    }

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
        '012-888-2222' => '+36-30-641-03-58',
        '012 888 2222' => '+36-30-641-03-58',
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

    if (is_front_page()) {
        $html = hm_migrated_insert_home_opening_notice($html);
    }

    $html = hm_migrated_move_hidden_forms_to_footer($html);
    $html = hm_migrated_replace_clean_footer($html);
    $html = hm_migrated_remove_legacy_canvas_menu($html);

    if (strlen(trim($original_html)) >= 1000 && (!is_string($html) || strlen(trim($html)) < 1000)) {
        return $original_html;
    }

    return is_string($html) ? $html : $original_html;
}

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
.harmat-home-opening-notice{max-width:1180px;margin:96px auto 0;padding:13px 20px;border:1px solid rgba(152,112,51,.24);border-left:4px solid #987033;background:#fff7e8;display:block;position:relative;z-index:3;font-family:Montserrat,Arial,sans-serif;color:#263135;box-shadow:0 12px 30px rgba(38,49,53,.06)}
.harmat-home-opening-notice div{display:flex;align-items:center;justify-content:center;gap:12px;min-width:0;text-align:center}
.harmat-home-opening-notice strong{flex:0 0 auto;color:#987033;font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.harmat-home-opening-notice span{color:#263135;font-size:13px;font-weight:900;letter-spacing:.02em;text-transform:uppercase;line-height:1.35}
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
@media(max-width:900px){.harmat-home-opening-notice{margin:88px 18px 0}.harmat-clean-footer-inner,.harmat-project-hero,.harmat-project-copy,.harmat-project-note{grid-template-columns:1fr}.harmat-project-stats,.harmat-project-gallery,.harmat-project-features{grid-template-columns:repeat(2,minmax(0,1fr))}.harmat-project-hero-image{min-height:280px}}
@media(max-width:560px){.harmat-clean-footer-inner{grid-template-columns:1fr;padding:28px 18px}.harmat-home-opening-notice{margin:82px 14px 0;padding:12px 14px}.harmat-home-opening-notice div{align-items:flex-start;flex-direction:column;gap:4px;text-align:left}.harmat-project-modern .entry-content{padding:26px 18px 44px}.harmat-project-hero{padding-top:34px;gap:22px}.harmat-project-actions a{flex:1 1 100%}.harmat-project-stats,.harmat-project-gallery,.harmat-project-features{grid-template-columns:1fr}.harmat-project-stats strong{font-size:23px}.harmat-project-hero-image{min-height:210px}.harmat-project-hero-image figcaption{left:12px;bottom:12px}}
</style>
        <?php
    }

    if (is_singular('property')) {
        ?>
<style id="harmat-property-hero-css">
.harmat-property-hero{max-width:1180px;margin:24px auto 30px;padding:22px 26px;border:1px solid rgba(152,112,51,.22);border-radius:6px;background:#fffaf1;box-shadow:0 18px 44px rgba(40,34,24,.08);font-family:Montserrat,Arial,sans-serif;color:#263135}
.single-property #page-title-bar,.single-property .elementor-element-6e26d68,.single-property .harmat-front-single-title-panel{display:none!important}
.harmat-property-hero-head{margin-bottom:16px}
.harmat-property-hero-head span{display:block;margin-bottom:8px;color:#987033;font-size:12px;font-weight:900;text-transform:uppercase}
.harmat-property-hero-head h1{margin:0!important;padding:0!important;color:#263135;font-family:Marcellus,Georgia,serif;font-size:clamp(36px,4vw,54px);line-height:.95;text-transform:uppercase}
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
@media(max-width:900px){.harmat-property-hero{margin:20px 18px 28px;padding:20px}.harmat-property-hero-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.harmat-property-hero-actions a{flex:1 1 calc(50% - 10px)}}
@media(max-width:520px){.harmat-property-hero{margin:16px 14px 24px;padding:18px}.harmat-property-hero-grid{grid-template-columns:1fr}.harmat-property-hero-grid div{min-height:auto}.harmat-property-hero-actions a{flex-basis:100%}.harmat-property-hero-head h1{font-size:34px}}
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

add_action('wp_footer', function () {
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
      [/012[\s-]*888[\s-]*2222/g, '+36-30-641-03-58'],
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
    <?php
}, 1005);
