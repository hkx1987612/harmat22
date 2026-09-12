<?php
/**
 * Plugin Name: Harmat Public Audit Polish
 * Description: Public SEO and UX cleanup layer for Harmat Lakópark audit items.
 * Version: 2026.07.13.1
 */

defined('ABSPATH') || exit;

function harmat_audit_request_path() {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    return trim((string) $path, '/');
}

function harmat_audit_is_public() {
    return !is_admin() && !wp_doing_ajax() && !wp_is_json_request() && !is_feed() && !is_robots();
}

add_action('template_redirect', function () {
    if (!harmat_audit_is_public()) {
        return;
    }

    $path = harmat_audit_request_path();

    if ($path === 'a-lakopark') {
        wp_safe_redirect(home_url('/harmat-lakopark/'), 301);
        exit;
    }

    if ($path === 'blog') {
        wp_safe_redirect(home_url('/epitesi-naplo/'), 301);
        exit;
    }

    if ($path === 'osszes-alaprajz' || $path === 'apartment' || strpos($path, 'apartment/') === 0) {
        wp_safe_redirect(home_url('/lakaskereso/'), 301);
        exit;
    }

    ob_start('harmat_audit_output_cleanup');
}, 0);

add_action('init', function () {
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
            if ($existing->post_title !== $page['title']) {
                wp_update_post(array(
                    'ID' => (int) $existing->ID,
                    'post_title' => $page['title'],
                    'post_name' => $slug,
                ));
            }
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
}, 25);

add_filter('pre_get_document_title', function ($title) {
    $path = harmat_audit_request_path();
    if ($path === 'finanszirozas') {
        return 'Finanszírozás | Harmat Lakópark';
    }
    if ($path === 'epitesi-naplo') {
        return 'Építési napló | Harmat Lakópark';
    }
    return $title;
}, 20);

add_filter('wpseo_title', function ($title) {
    $path = harmat_audit_request_path();
    if ($path === 'finanszirozas') {
        return 'Finanszírozás | Harmat Lakópark';
    }
    if ($path === 'epitesi-naplo') {
        return 'Építési napló | Harmat Lakópark';
    }
    return $title;
}, 20);

add_filter('wpseo_canonical', function ($canonical) {
    if (!harmat_audit_is_public()) {
        return $canonical;
    }

    $path = harmat_audit_request_path();
    if ($path === '') {
        return home_url('/');
    }

    return home_url('/' . trailingslashit($path));
}, 20);

add_filter('wpseo_sitemap_entry', function ($url, $type, $object) {
    if (empty($url['loc'])) {
        return $url;
    }

    $path = trim((string) parse_url((string) $url['loc'], PHP_URL_PATH), '/');
    $blocked = array('a-lakopark', 'blog', 'osszes-alaprajz', 'apartment');

    if (in_array($path, $blocked, true) || strpos($path, 'apartment/') === 0) {
        return false;
    }

    return $url;
}, 20, 3);

function harmat_audit_replace_legacy_links($html) {
    $site = untrailingslashit(home_url());
    $replacements = array(
        $site . '/a-lakopark/' => home_url('/harmat-lakopark/'),
        '/a-lakopark/' => wp_make_link_relative(home_url('/harmat-lakopark/')),
        $site . '/blog/' => home_url('/epitesi-naplo/'),
        '/blog/' => wp_make_link_relative(home_url('/epitesi-naplo/')),
        $site . '/osszes-alaprajz/' => home_url('/lakaskereso/'),
        '/osszes-alaprajz/' => wp_make_link_relative(home_url('/lakaskereso/')),
    );

    return strtr($html, $replacements);
}

function harmat_audit_demote_extra_h1($html) {
    $paths = array(
        'lakaskereso',
        'elerhetosegeink',
        'adatvedelmi-tajekoztato',
        'cookie-tajekoztato',
        'felhasznalasi-feltetelek',
        'impresszum',
        'panaszkezeles',
        'finanszirozas',
        'epitesi-naplo',
    );

    if (!in_array(harmat_audit_request_path(), $paths, true)) {
        return $html;
    }

    $seen = 0;
    return preg_replace_callback('~<h1\b([^>]*)>(.*?)</h1>~is', function ($match) use (&$seen) {
        $seen++;
        if ($seen === 1) {
            return $match[0];
        }

        $attrs = $match[1];
        if (preg_match('~\sclass=(["\'])(.*?)\1~i', $attrs, $class_match)) {
            $attrs = preg_replace('~\sclass=(["\'])(.*?)\1~i', ' class=' . $class_match[1] . trim($class_match[2] . ' harmat-demoted-h1') . $class_match[1], $attrs, 1);
        } else {
            $attrs .= ' class="harmat-demoted-h1"';
        }

        $content = $match[2];
        if (harmat_audit_request_path() === 'lakaskereso' && strip_tags($content) === 'Lakáskereső') {
            $content = 'Harmat Lakópark lakáskínálata';
        }

        return '<h2' . $attrs . '>' . $content . '</h2>';
    }, $html);
}

function harmat_audit_related_apartments_guard($html) {
    if (strpos(harmat_audit_request_path(), 'property/') !== 0) {
        return $html;
    }

    return preg_replace_callback('~<(section|div)\b([^>]*(?:hm-lakas-related|hasonlo|related)[^>]*)>([\s\S]*?Hasonl(?:ó|&oacute;)\s+lak[aá]sok[\s\S]*?)</\1>~iu', function ($match) {
        if (stripos($match[0], 'hm-lakas-related-source') !== false) {
            return $match[0];
        }

        $path_parts = explode('/', harmat_audit_request_path());
        $current_slug = strtolower((string) end($path_parts));
        $body = $match[3];
        if ($current_slug !== '') {
            $body = preg_replace('~<a\b[^>]*href=["\'][^"\']*/property/' . preg_quote($current_slug, '~') . '/?["\'][^>]*>[\s\S]*?</a>~iu', '', $body);
        }

        $count = 0;
        $body = preg_replace_callback('~<a\b[^>]*href=["\'][^"\']*/property/[^"\']+["\'][^>]*>[\s\S]*?</a>~iu', function ($item) use (&$count) {
            $count++;
            return $count <= 6 ? $item[0] : '';
        }, $body);

        return '<' . $match[1] . $match[2] . '>' . $body . '</' . $match[1] . '>';
    }, $html);
}

function harmat_audit_project_heading_cleanup($html) {
    if (harmat_audit_request_path() !== 'harmat-lakopark') {
        return $html;
    }

    return preg_replace(
        '~<h2\b([^>]*)>\s*Harmat\s+Lak(?:ó|&oacute;)park\s*</h2>~iu',
        '<h2$1>Új építésű lakópark Budapest X. kerületében</h2>',
        $html,
        1
    );
}

function harmat_audit_remove_related_after_footer($html) {
    if (strpos(harmat_audit_request_path(), 'property/') !== 0) {
        return $html;
    }

    $footer_pos = stripos($html, '<footer');
    if ($footer_pos === false) {
        return $html;
    }

    $before = substr($html, 0, $footer_pos);
    $after = substr($html, $footer_pos);

    $after = preg_replace_callback('~<section\b[^>]*(?:hm-lakas-related|hasonlo|related)[^>]*>[\s\S]*?Hasonl(?:ó|&oacute;)\s+lak[aá]sok[\s\S]*?</section>~iu', function ($match) {
        if (stripos($match[0], 'hm-lakas-related-source') !== false) {
            return $match[0];
        }
        return '<!-- duplicate similar apartments removed after footer -->';
    }, $after);
    $after = preg_replace_callback('~<div\b[^>]*(?:hm-lakas-related|hasonlo|related)[^>]*>[\s\S]*?Hasonl(?:ó|&oacute;)\s+lak[aá]sok[\s\S]*?</div>~iu', function ($match) {
        if (stripos($match[0], 'hm-lakas-related-source') !== false) {
            return $match[0];
        }
        return '<!-- duplicate similar apartments removed after footer -->';
    }, $after);

    return $before . $after;
}

function harmat_audit_financing_html() {
    return '<main id="main" class="site-main harmat-info-page harmat-financing-page" role="main">'
        . '<article class="page type-page status-publish hentry"><div class="entry-content">'
        . '<section class="harmat-info-hero"><span>Vásárlási információk</span><h1>Finanszírozás</h1><p>A Harmat Lakópark értékesítési csapata a kiválasztott lakáshoz kapcsolódó fizetési ütemezésről, banki finanszírozásról és támogatási kérdésekről tájékoztató jelleggel segít.</p></section>'
        . '<section class="harmat-info-grid">'
        . '<article><h2>Fizetési ütemezés</h2><p>A jelenlegi projektmérföldkövek: szerződéskötés a tényleges aláírás napján, szerkezetkész / tetőszint 2027. május, belső munkák 2027. szeptember, műszaki átadás / ellenőrzés 2028. március, várható átadás 2028. június. A pontos fizetési arányokat és határidőket mindig az ajánlat és a szerződés rögzíti.</p></article>'
        . '<article><h2>CSOK lehetőség</h2><p>Új építésű lakás vásárlásánál bizonyos családtámogatási lehetőségek felmerülhetnek. A jogosultság mindig egyedi vizsgálatot igényel, ezért a végleges választ banki vagy hivatalos tanácsadó erősíti meg.</p></article>'
        . '<article><h2>Hitelügyintézés</h2><p>Igény esetén az értékesítés segít elindítani a finanszírozási egyeztetést és a szükséges dokumentumok áttekintését. A hitelképességet, kamatot és banki feltételeket minden esetben a finanszírozó bank határozza meg.</p></article>'
        . '<article><h2>Banki finanszírozás</h2><p>Banki finanszírozásnál a jövedelmi helyzet, önerő, futamidő, ingatlanérték és banki kockázati szabályok együtt számítanak. A projektoldal nem vállal hiteljóváhagyási kötelezettséget.</p></article>'
        . '</section>'
        . '<section class="harmat-info-note"><h2>Tájékoztató jellegű nyilatkozat</h2><p>Az oldalon szereplő finanszírozási információk nem minősülnek pénzügyi tanácsadásnak, hitelígérvénynek vagy szerződéses ajánlatnak. A végleges fizetési, támogatási és banki feltételeket az adásvételi szerződés, az értékesítési egyeztetés és az érintett pénzügyi intézmény dokumentumai határozzák meg.</p><a href="' . esc_url(home_url('/elerhetosegeink/')) . '">Kapcsolat az értékesítéssel</a></section>'
        . '</div></article></main>';
}

function harmat_audit_build_log_html() {
    return '<main id="main" class="site-main harmat-info-page harmat-build-log-page" role="main">'
        . '<article class="page type-page status-publish hentry"><div class="entry-content">'
        . '<section class="harmat-info-hero"><span>Projektfrissítések</span><h1>Építési napló</h1><p>A Harmat Lakópark nyilvános projektmérföldkövei és építési hírei egy helyen.</p></section>'
        . '<section class="harmat-build-log-list"><article><time datetime="2026-06-12">2026. június 12.</time><h2>Ünnepélyes alapkőletétel és hivatalos értékesítési nyitás</h2><p>A Harmat Lakópark első ütemének bemutatása és hivatalos értékesítési nyitása 2026. június 12-én indult. Ettől az időponttól az első ütem lakásadatai, alaprajzai és ajánlatkérési folyamata részletesen elérhető a weboldalon és az értékesítési csapatnál.</p>'
        . '<div class="harmat-build-log-details" data-harmat-build-log-details="1">'
        . '<section><h3>Értékesítési iroda megnyitása</h3><p>Az érdeklődők személyes tájékoztatást, alaprajzi egyeztetést és ajánlatkérést kérhetnek az értékesítési csapattól.</p></section>'
        . '<section><h3>Projektmakett</h3><p>A projekt bemutatását látványanyagok és makett segítik, hogy az épületek elhelyezkedése és az első ütem áttekinthető legyen.</p></section>'
        . '<section><h3>Helyszíni arculat és kerítés</h3><p>A helyszíni megjelenés, tájékoztató felületek és kerítés kialakítása a projekt arculatához igazodik.</p></section>'
        . '<section><h3>Következő lépések</h3><p>A következő időszakban a nyilvános projektfrissítések, elérhető lakásadatok és értékesítési információk folyamatosan bővülnek.</p></section>'
        . '</div><a href="' . esc_url(home_url('/harmat-lakopark/')) . '">Projekt bemutatása</a></article></section>'
        . '</div></article></main>';
}

function harmat_audit_replace_info_page_main($html) {
    $path = harmat_audit_request_path();
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

    $replacement = $path === 'finanszirozas' ? harmat_audit_financing_html() : harmat_audit_build_log_html();
    return substr($html, 0, $start) . $replacement . substr($html, $end + 7);
}

function harmat_audit_modal_script() {
    return '<script id="harmat-audit-modal-dom-fix">(function(){function fix(){var nodes=Array.prototype.slice.call(document.querySelectorAll("#opal-contactform-popup,[id^=\'opal-contactform-popup\'],.contactform-content"));var seen={};nodes.forEach(function(node){var id=node.id||"";if(id){if(seen[id]){node.remove();return;}seen[id]=true;}if(node.parentNode!==document.body){document.body.appendChild(node);}var open=node.classList&&node.classList.contains("mfp-hide")===false&&node.offsetParent!==null;node.setAttribute("aria-hidden",open?"false":"true");if(!open&&"inert" in node){node.inert=true;}else if("inert" in node){node.inert=false;}});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",fix);}else{fix();}document.addEventListener("click",function(){setTimeout(fix,80);},true);})();</script>';
}

function harmat_audit_related_cards_script() {
    return '<script id="harmat-audit-related-cards-fix">(function(){function mark(){var heads=Array.prototype.slice.call(document.querySelectorAll("h1,h2,h3,h4"));heads.forEach(function(head){if(head.closest&&head.closest("#hm-lakas-related-source")){return;}var text=(head.textContent||"").toLowerCase();if(text.indexOf("hasonló lakások")===-1&&text.indexOf("hasonlo lakasok")===-1){return;}var section=head.closest("section,div.elementor-section,div.elementor-widget-wrap,div");if(!section){return;}section.classList.add("harmat-related-modern");Array.prototype.slice.call(section.querySelectorAll("article,.property,.opalestate-property,.elementor-post,.post,.item")).forEach(function(card){card.classList.add("harmat-related-modern-card");});});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",mark);}else{mark();}})();</script>';
}

function harmat_audit_stability_content_cleanup($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    return str_replace(
        array(
            '<span><strong>Ár</strong> egyeztetés alapján</span>',
            'Az I. ütemben 124 lakás érhető el A1, A2, A3 és A4 épületekben.',
        ),
        array(
            '<span><strong>Árak</strong> lakásonként</span>',
            'Az I. ütem összesen 124 lakást tartalmaz az A1, A2, A3 és A4 épületekben.',
        ),
        $html
    );
}

function harmat_audit_stability_script() {
    return <<<'HTML'
<script id="harmat-audit-stability-js">
(function () {
  if (window.__harmatAuditStabilityReady) return;
  window.__harmatAuditStabilityReady = true;

  function normalizeHeader() {
    var headers = Array.prototype.slice.call(document.querySelectorAll('[id="my-sticky-header"]'));
    headers.forEach(function (header, index) {
      if (index > 0) header.id = 'my-sticky-header-source-' + index;
    });

    document.querySelectorAll('a.elementor-icon[href*="elementor-action"]').forEach(function (link) {
      if (!link.getAttribute('aria-label') && !(link.textContent || '').trim()) {
        link.setAttribute('aria-label', 'Menü megnyitása');
      }
    });
  }

  function applyRoomFilter() {
    if (window.location.pathname.replace(/\/+$/, '') !== '/lakaskereso') return;

    var page = document.querySelector('[data-hm-lakas-page]');
    if (!page || page.getAttribute('data-hm-url-filter-applied')) return;

    var rooms = new URLSearchParams(window.location.search).get('rooms') || '';
    if (!/^[1-5]$/.test(rooms)) return;

    var field = page.querySelector('[data-filter="rooms"]');
    if (!field || !field.querySelector('option[value="' + rooms + '"]')) return;

    field.value = rooms;
    page.setAttribute('data-hm-url-filter-applied', rooms);
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function patchHeroPoster() {
    if (window.location.pathname !== '/') return;

    document.querySelectorAll('video').forEach(function (video) {
      var source = video.currentSrc || video.getAttribute('src') || '';
      var nestedSource = video.querySelector('source');
      if (!source && nestedSource) source = nestedSource.getAttribute('src') || '';
      if (source.indexOf('yulu-garden-') === -1 || video.getAttribute('poster')) return;

      video.setAttribute('poster', 'https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-3.jpg');
    });
  }

  function labelVirtualControls() {
    if (window.location.pathname.indexOf('/virtualis-lakasvalaszto') !== 0) return;

    document.querySelectorAll('input[type="range"]:not([aria-label]):not([aria-labelledby])').forEach(function (field) {
      field.setAttribute('aria-label', 'Épületnézet vezérlése');
    });
  }

  function run() {
    normalizeHeader();
    applyRoomFilter();
    patchHeroPoster();
    labelVirtualControls();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
  window.addEventListener('load', run);
  window.setTimeout(run, 900);
  window.setTimeout(run, 2200);
}());
</script>
HTML;
}

function harmat_audit_output_cleanup($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $html = harmat_audit_replace_legacy_links($html);
    $html = harmat_audit_replace_info_page_main($html);
    $html = harmat_audit_related_apartments_guard($html);
    $html = harmat_audit_remove_related_after_footer($html);
    $html = harmat_audit_project_heading_cleanup($html);
    $html = harmat_audit_demote_extra_h1($html);
    $html = harmat_audit_stability_content_cleanup($html);

    if (strpos($html, 'harmat-audit-modal-dom-fix') === false) {
        $html = str_ireplace('</body>', harmat_audit_modal_script() . '</body>', $html);
    }
    if (strpos(harmat_audit_request_path(), 'property/') === 0 && strpos($html, 'harmat-audit-related-cards-fix') === false) {
        $html = str_ireplace('</body>', harmat_audit_related_cards_script() . '</body>', $html);
    }
    if (strpos($html, 'harmat-audit-stability-js') === false) {
        $html = str_ireplace('</body>', harmat_audit_stability_script() . '</body>', $html);
    }

    return $html;
}

add_action('wp_head', function () {
    $path = harmat_audit_request_path();
    if ($path === 'galeria') {
        ?>
<style id="harmat-audit-gallery-wide-css">
body.page-id-439 .elementor-widget-opal-image-gallery { max-width:1280px; margin:0 auto; }
body.page-id-439 .elementor-galerry__filters { display:flex!important; flex-wrap:wrap!important; justify-content:center!important; gap:10px!important; margin:0 0 28px!important; }
body.page-id-439 .elementor-galerry__filter { min-height:38px!important; padding:0 16px!important; border:1px solid rgba(154,106,42,.28)!important; background:#fff!important; color:#263135!important; font-weight:800!important; letter-spacing:.06em!important; }
body.page-id-439 .elementor-galerry__filter.elementor-active { background:#9a6a2a!important; color:#fff!important; border-color:#9a6a2a!important; }
body.page-id-439 .elementor-opal-image-gallery,
body.page-id-439 .elementor-opal-image-gallery.row,
body.page-id-439 .isotope-grid.gallery-visibility { display:grid!important; grid-template-columns:repeat(2,minmax(0,1fr))!important; gap:22px!important; height:auto!important; align-items:stretch!important; margin:0!important; }
body.page-id-439 .elementor-opal-image-gallery .column-item,
body.page-id-439 .isotope-grid .grid__item,
body.page-id-439 .gallery-visibility .masonry-item { position:relative!important; left:auto!important; top:auto!important; transform:none!important; width:100%!important; max-width:none!important; padding:0!important; margin:0!important; float:none!important; }
body.page-id-439 .elementor-opal-image-gallery .column-item[style],
body.page-id-439 .gallery-visibility .masonry-item[style] { position:relative!important; left:auto!important; top:auto!important; transform:none!important; width:100%!important; }
body.page-id-439 .elementor-opal-image-gallery a,
body.page-id-439 .elementor-opal-image-gallery .gallery-item,
body.page-id-439 .elementor-opal-image-gallery .image-gallery,
body.page-id-439 .elementor-opal-image-gallery figure { display:block!important; overflow:hidden!important; width:100%!important; height:auto!important; aspect-ratio:16/9!important; background:#f4eadc!important; border:1px solid rgba(154,106,42,.18)!important; box-shadow:0 18px 42px rgba(31,45,52,.09)!important; }
body.page-id-439 .elementor-opal-image-gallery img { display:block!important; width:100%!important; height:100%!important; min-height:0!important; max-height:none!important; aspect-ratio:16/9!important; object-fit:cover!important; object-position:center!important; transition:transform .35s ease!important; }
body.page-id-439 .elementor-opal-image-gallery a:hover img,
body.page-id-439 .elementor-opal-image-gallery figure:hover img { transform:scale(1.035)!important; }
@media (min-width:1440px){body.page-id-439 .elementor-widget-opal-image-gallery{max-width:1440px}body.page-id-439 .elementor-opal-image-gallery,body.page-id-439 .elementor-opal-image-gallery.row,body.page-id-439 .isotope-grid.gallery-visibility{gap:26px!important}}
@media (max-width:780px){body.page-id-439 .elementor-opal-image-gallery,body.page-id-439 .elementor-opal-image-gallery.row,body.page-id-439 .isotope-grid.gallery-visibility{grid-template-columns:1fr!important;gap:16px!important}body.page-id-439 .elementor-opal-image-gallery a,body.page-id-439 .elementor-opal-image-gallery .gallery-item,body.page-id-439 .elementor-opal-image-gallery .image-gallery,body.page-id-439 .elementor-opal-image-gallery figure{aspect-ratio:4/3!important}}
</style>
        <?php
    }

    if (strpos($path, 'property/') === 0) {
        ?>
<style id="harmat-audit-related-cards-css">
.harmat-related-modern { margin-top: 42px; }
.harmat-related-modern h2,
.harmat-related-modern h3 { color:#1f2d34!important; font-family:Georgia,"Times New Roman",serif!important; font-size:34px!important; font-weight:500!important; letter-spacing:0!important; text-transform:none!important; }
.harmat-related-modern .elementor-container,
.harmat-related-modern .elementor-row,
.harmat-related-modern .row,
.harmat-related-modern .properties,
.harmat-related-modern .opalestate-rows,
.harmat-related-modern .elementor-posts-container { display:grid!important; grid-template-columns:repeat(4,minmax(0,1fr))!important; gap:18px!important; align-items:stretch!important; }
.harmat-related-modern .elementor-column,
.harmat-related-modern .col-md-3,
.harmat-related-modern .col-sm-6,
.harmat-related-modern .col-lg-3 { width:auto!important; max-width:none!important; flex:initial!important; padding:0!important; }
.harmat-related-modern-card,
.harmat-related-modern article,
.harmat-related-modern .property,
.harmat-related-modern .opalestate-property,
.harmat-related-modern .elementor-post,
.harmat-related-modern .post,
.harmat-related-modern .item { overflow:hidden!important; height:100%!important; border:1px solid rgba(154,106,42,.18)!important; border-radius:0!important; background:#fff!important; box-shadow:0 18px 42px rgba(31,45,52,.08)!important; transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease!important; }
.harmat-related-modern-card:hover,
.harmat-related-modern article:hover,
.harmat-related-modern .property:hover,
.harmat-related-modern .opalestate-property:hover,
.harmat-related-modern .elementor-post:hover { transform:translateY(-3px)!important; border-color:rgba(154,106,42,.42)!important; box-shadow:0 24px 54px rgba(31,45,52,.13)!important; }
.harmat-related-modern a { color:#1f2d34!important; text-decoration:none!important; }
.harmat-related-modern img { display:block!important; width:100%!important; aspect-ratio:4/3!important; object-fit:cover!important; background:#f6efe3!important; }
.harmat-related-modern .entry-title,
.harmat-related-modern h4,
.harmat-related-modern h5,
.harmat-related-modern .property-title,
.harmat-related-modern .opalestate-property-title { margin:0!important; padding:16px 16px 6px!important; color:#1f2d34!important; font-size:20px!important; line-height:1.2!important; font-family:Georgia,"Times New Roman",serif!important; font-weight:500!important; }
.harmat-related-modern .property-meta,
.harmat-related-modern .meta,
.harmat-related-modern .entry-meta,
.harmat-related-modern .opalestate-property-meta,
.harmat-related-modern .property-address,
.harmat-related-modern .property-price,
.harmat-related-modern .price { display:block!important; padding:0 16px 14px!important; color:#6f7780!important; font-size:13px!important; line-height:1.45!important; }
.harmat-related-modern .button,
.harmat-related-modern .btn,
.harmat-related-modern .more-link { display:inline-flex!important; align-items:center!important; justify-content:center!important; min-height:38px!important; margin:0 16px 16px!important; padding:0 13px!important; border:1px solid #9a6a2a!important; background:#fff!important; color:#9a6a2a!important; font-size:12px!important; font-weight:800!important; letter-spacing:.08em!important; text-transform:uppercase!important; }
body.single-property .elementor-widget-loop-grid[data-widget_type="loop-grid.post"] .elementor-loop-container { display:grid!important; grid-template-columns:repeat(4,minmax(0,1fr))!important; gap:18px!important; }
body.single-property .elementor-widget-loop-grid[data-widget_type="loop-grid.post"] .e-loop-item { width:auto!important; max-width:none!important; }
body.single-property .elementor-4110 .property_loop,
body.single-property .elementor-4110 .property_loop>.e-con-inner { height:100%!important; overflow:hidden!important; border:1px solid rgba(154,106,42,.18)!important; background:#fff!important; box-shadow:0 18px 42px rgba(31,45,52,.08)!important; transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease!important; }
body.single-property .elementor-4110 .property_loop:hover,
body.single-property .elementor-4110 .property_loop:hover>.e-con-inner { transform:translateY(-3px)!important; border-color:rgba(154,106,42,.42)!important; box-shadow:0 24px 54px rgba(31,45,52,.13)!important; }
body.single-property .elementor-4110 .loopimage,
body.single-property .elementor-4110 .loopimage>.e-con-inner,
body.single-property .elementor-4110 .elementor-element-5af2898,
body.single-property .elementor-4110 .elementor-widget-image,
body.single-property .elementor-4110 .elementor-widget-image a { display:block!important; width:100%!important; height:auto!important; max-height:none!important; overflow:hidden!important; background:#f6efe3!important; }
body.single-property .elementor-4110 .elementor-element-5af2898 img,
body.single-property .elementor-4110 .elementor-widget-image img { display:block!important; width:100%!important; height:auto!important; aspect-ratio:4/3!important; object-fit:cover!important; object-position:center!important; max-width:none!important; max-height:none!important; transition:transform .35s ease!important; }
body.single-property .elementor-4110 .property_loop:hover img { transform:scale(1.035)!important; }
body.single-property .elementor-4110 .elementor-heading-title,
body.single-property .elementor-4110 .elementor-widget-text-editor,
body.single-property .elementor-4110 .elementor-widget-text-editor * { color:#263135!important; }
body.single-property .elementor-4110 .elementor-element-ccb30f2 { padding:16px 16px 18px!important; background:#fff!important; }
body.single-property .elementor-4110 .elementor-element-ea1d2a7,
body.single-property .elementor-4110 .elementor-element-06fb68b { --e-con-grid-template-columns:repeat(2,1fr)!important; gap:8px!important; padding-left:0!important; padding-right:0!important; }
body.single-property .elementor-4110 .elementor-element-90ce068 { background:#9a6a2a!important; border-radius:0!important; }
@media (max-width:1024px){.harmat-related-modern .elementor-container,.harmat-related-modern .elementor-row,.harmat-related-modern .row,.harmat-related-modern .properties,.harmat-related-modern .opalestate-rows,.harmat-related-modern .elementor-posts-container{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media (max-width:1024px){body.single-property .elementor-widget-loop-grid[data-widget_type="loop-grid.post"] .elementor-loop-container{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media (max-width:640px){.harmat-related-modern .elementor-container,.harmat-related-modern .elementor-row,.harmat-related-modern .row,.harmat-related-modern .properties,.harmat-related-modern .opalestate-rows,.harmat-related-modern .elementor-posts-container{grid-template-columns:1fr!important}.harmat-related-modern h2,.harmat-related-modern h3{font-size:29px!important}body.single-property .elementor-widget-loop-grid[data-widget_type="loop-grid.post"] .elementor-loop-container{grid-template-columns:1fr!important}}
</style>
        <?php
    }

    if ($path !== 'finanszirozas' && $path !== 'epitesi-naplo') {
        return;
    }
    ?>
<style id="harmat-audit-info-page-css">
.harmat-info-page { background:#fffaf2; color:#263135; }
.harmat-info-page .entry-content { max-width:1120px; margin:0 auto; padding:72px 18px; }
.harmat-info-hero { border:1px solid rgba(154,106,42,.2); background:#fff; padding:34px; margin-bottom:18px; }
.harmat-info-hero span { display:block; color:#9a6a2a; font:800 12px/1.2 Montserrat,Arial,sans-serif; letter-spacing:.12em; text-transform:uppercase; margin-bottom:12px; }
.harmat-info-hero h1 { margin:0 0 14px; font:700 42px/1.08 Georgia,serif; color:#1f2d34; }
.harmat-info-hero p { max-width:780px; margin:0; color:#536066; font-size:16px; line-height:1.75; }
.harmat-info-grid,.harmat-build-log-details { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin:18px 0; }
.harmat-info-grid article,.harmat-build-log-list article,.harmat-build-log-details section,.harmat-info-note { border:1px solid rgba(154,106,42,.18); background:#fff; padding:22px; }
.harmat-info-grid h2,.harmat-info-note h2,.harmat-build-log-list h2 { margin:0 0 10px; color:#263135; font-size:22px; line-height:1.25; }
.harmat-build-log-details h3 { margin:0 0 8px; font-size:16px; color:#263135; }
.harmat-info-page p { color:#50585d; line-height:1.75; }
.harmat-build-log-list time { display:block; color:#9a6a2a; font-weight:800; margin-bottom:8px; }
.harmat-info-note a,.harmat-build-log-list a { display:inline-flex; margin-top:10px; min-height:42px; align-items:center; padding:0 16px; background:#9a6a2a; color:#fff; text-decoration:none; font-weight:800; }
@media (max-width:720px){.harmat-info-page .entry-content{padding:44px 14px}.harmat-info-hero{padding:24px}.harmat-info-hero h1{font-size:34px}.harmat-info-grid,.harmat-build-log-details{grid-template-columns:1fr}}
</style>
    <?php
}, 90);

add_action('wp_head', function () {
    if (!harmat_audit_is_public()) {
        return;
    }
    ?>
<style id="harmat-audit-stability-css">
.harmat-local-ai-launch { color:#fff!important; border-color:#a8762d!important; }
@media (min-width:1121px) and (max-width:1360px) {
  body.harmat-lakas-redesign-page .hm-lakas-toolbar { grid-template-columns:minmax(180px,1.05fr) repeat(3,minmax(108px,.68fr)) minmax(168px,.9fr) minmax(120px,.7fr)!important; }
  body.harmat-lakas-redesign-page .hm-lakas-range-field { grid-column:1/6!important; }
  body.harmat-lakas-redesign-page .hm-lakas-reset { grid-column:6!important; }
}
@media (max-width:560px) {
  #my-sticky-header .headerrow,
  [id^="my-sticky-header-source-"] .headerrow { height:100px!important; min-height:100px!important; }
  #my-sticky-header a[href*="opal-contactform-popup"],
  [id^="my-sticky-header-source-"] a[href*="opal-contactform-popup"] { display:none!important; }
  #hm-cookie-settings-button { left:12px!important; right:auto!important; bottom:max(70px,calc(env(safe-area-inset-bottom,0px) + 70px))!important; }
  body:not(.elementor-editor-active) .harmat-local-ai-launch { right:12px!important; bottom:max(14px,calc(env(safe-area-inset-bottom,0px) + 14px))!important; }
  body.home .elementor-element-c19513c,
  body.home .elementor-element-c19513c > .elementor-container,
  body.home .elementor-element-c19513c .elementor-column,
  body.home .elementor-element-c19513c .elementor-widget-wrap,
  body.home .elementor-element-c19513c .elementor-widget-opal-revslider,
  body.home .elementor-element-c19513c .elementor-widget-container,
  body.home .elementor-element-c19513c sr7-module { height:clamp(360px,104vw,430px)!important; min-height:0!important; max-height:430px!important; }
}
</style>
    <?php
}, 9999);
