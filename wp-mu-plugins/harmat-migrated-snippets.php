<?php
/**
 * Plugin Name: Harmat Migrated Snippet Logic
 * Description: Version-controlled replacement for public cleanup, SEO, legal footer, and legacy text Code Snippets.
 * Version: 2026.06.06
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

function hm_migrated_public_html_cleanup($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $replacements = array(
        'Harmatliget lakópark' => 'Harmat Lakópark',
        'Harmatliget Lakópark' => 'Harmat Lakópark',
        'Harmatliget' => 'Harmat Lakópark',
        'Harmat 22 Lakópark' => 'Harmat Lakópark',
        'Harmat 22 lakópark' => 'Harmat Lakópark',
        'Harmat 22 értékesítés' => 'Harmat Lakópark értékesítés',
        'Gipsz Jakab' => 'Harmat Lakópark értékesítés',
        '012-888-2222' => '+36-30-641-03-58',
        '012 888 2222' => '+36-30-641-03-58',
        'agent.name@example.com' => 'ertekesites@harmat22.hu',
        'mailto:agent.name@example.com' => 'mailto:ertekesites@harmat22.hu',
        'modumkft@gmail.com' => 'ertekesites@harmat22.hu',
        'mailto:modumkft@gmail.com' => 'mailto:ertekesites@harmat22.hu',
        'Marketing Consent' => '',
    );

    $html = strtr($html, $replacements);
    $html = str_ireplace('Harmat 22 Lakópark', 'Harmat Lakópark', $html);

    $html = preg_replace(
        '~\s*<a\b[^>]*href=(["\'])[^"\']*/marketing-hozzajarulas/?\1[^>]*>\s*</a>~iu',
        '',
        $html
    );

    return is_string($html) ? $html : '';
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

add_action('wp_head', function () {
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

    $links = hm_migrated_legal_links();
    ?>
<style id="harmat-migrated-public-cleanup-css">
.harmat-legal-footer{background:#fff7e8;border-top:1px solid rgba(152,112,51,.22);padding:18px 22px;text-align:center;font-family:Montserrat,Arial,sans-serif}
.harmat-legal-footer a{color:#7a5520!important;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;margin:0 10px 8px;display:inline-block}
.harmat-legal-footer-note{color:#5f6367;font-size:12px;line-height:1.6;max-width:980px;margin:6px auto 0}
.harmat-property-disclaimer{max-width:1180px;width:calc(100% - 48px);margin:52px auto 0;padding:16px 20px;background:rgba(255,247,232,.92);border-left:4px solid #987033;color:#4b5054;font-family:Montserrat,Arial,sans-serif;font-size:13px;line-height:1.7;text-align:left}
.harmat-property-disclaimer strong{color:#7a5520;font-weight:800}
.harmat-property-status-note{display:inline-flex;align-items:center;gap:8px;margin:10px 0 0;padding:7px 12px;border-radius:999px;background:#17875b;color:#fff;font:700 12px/1.2 Montserrat,Arial,sans-serif;letter-spacing:.04em;text-transform:uppercase}
@media(max-width:640px){.harmat-legal-footer a{display:block;margin:0 0 10px}.harmat-property-disclaimer{width:auto!important;margin:22px 16px 0;font-size:12px}}
</style>
<div class="harmat-legal-footer" role="contentinfo">
    <?php foreach ($links as $label => $url) : ?>
        <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
    <?php endforeach; ?>
    <div class="harmat-legal-footer-note">Az árak, alapterületek, látványtervek és műszaki tartalmak tájékoztató jellegűek. A végleges feltételeket minden esetben a szerződés és mellékletei tartalmazzák.</div>
</div>
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
    setTextIfExact('értékesítési vezető', 'Értékesítési csapat');
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
    if (document.querySelector('.harmat-property-status-note')) return;
    var anchor = document.querySelector('.property-title, h1, .entry-title') || document.querySelector('.site-main');
    if (!anchor || !anchor.parentNode) return;
    var note = document.createElement('div');
    note.className = 'harmat-property-status-note';
    note.textContent = 'Státusz: Elérhető';
    anchor.parentNode.insertBefore(note, anchor.nextSibling);
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
    addPropertyStatus();
    normalizeListingData();
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
