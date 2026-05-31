<?php
/**
 * Plugin Name: Harmat Performance Guard
 * Description: Keeps heavy presentation assets off listing and virtual-selector pages, and suppresses the replaced legacy homepage map.
 * Version: 1.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_perf_is_fast_path() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = trim((string) parse_url($path, PHP_URL_PATH), '/');

    if (is_page(array(
        'lakaskereso',
        'virtualis-lakasvalaszto',
        'virtualis-lakasvalaszto-a1-epulet',
        'virtualis-lakasvalaszto-a2-epulet',
        'virtualis-lakasvalaszto-a3-epulet',
        'virtualis-lakasvalaszto-a4-epulet',
        'virtualis-lakasvalaszto-elso-utem',
        'virtualis-lakasvalaszto-teszt',
    ))) {
        return true;
    }

    return preg_match('~^(lakaskereso|virtualis-lakasvalaszto)(/|$)~', $path) === 1;
}


function harmat_perf_is_property_detail() {
    return !is_admin() && is_singular('property');
}

function harmat_perf_text($text) {
    return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
}

function harmat_perf_fix_visible_mojibake($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    return strtr($html, array(
        'Felhaszn?l?si felt?telek' => 'Felhasználási feltételek',
        'Marketing hozz?j?rul?s' => 'Marketing hozzájárulás',
        'Adatv?delmi t?j?koztat?t' => 'Adatvédelmi tájékoztatót',
        'Adatv?delmi t?j?koztat?' => 'Adatvédelmi tájékoztató',
        'Felhaszn?l?si' => 'Felhasználási',
        'hozz?j?rul?s' => 'hozzájárulás',
        't?j?koztat?' => 'tájékoztató',
        '?sszes' => 'Összes',
        'Lak?s' => 'Lakás',
        'lak?s' => 'lakás',
    ));
}
add_filter('wpcf7_form_elements', 'harmat_perf_fix_visible_mojibake', 99);
add_filter('the_content', 'harmat_perf_fix_visible_mojibake', 99);

function harmat_perf_start_visible_text_cleanup() {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    ob_start('harmat_perf_fix_visible_mojibake');
}
add_action('template_redirect', 'harmat_perf_start_visible_text_cleanup', 0);

function harmat_perf_request_path() {
    $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    return trim((string) parse_url($path, PHP_URL_PATH), '/');
}

function harmat_perf_is_private_portal_path() {
    if (is_admin()) {
        return true;
    }

    $path = harmat_perf_request_path();
    return preg_match('~^(sales|agent|client|belepes)(/|$)~', $path) === 1;
}

function harmat_perf_dequeue_heavy_assets() {
    $is_fast_path = harmat_perf_is_fast_path();
    $is_property_detail = harmat_perf_is_property_detail();

    if (!$is_fast_path && !$is_property_detail && !is_front_page()) {
        return;
    }

    $handles = array(
        'custom-map-frontend-css-css',
        'custom-map-frontend-js-js',
    );

    if ($is_fast_path || $is_property_detail) {
        $handles = array_merge($handles, array(
            'harmat22-pannellum',
            'harmat22-map-redesign',
            'tp-tools',
            'sr7',
            'sr7css',
        ));
    }

    foreach ($handles as $handle) {
        wp_dequeue_style($handle);
        wp_deregister_style($handle);
        wp_dequeue_script($handle);
        wp_deregister_script($handle);
    }
}
add_action('wp_enqueue_scripts', 'harmat_perf_dequeue_heavy_assets', 1001);
add_action('wp_print_styles', 'harmat_perf_dequeue_heavy_assets', 1001);
add_action('wp_print_scripts', 'harmat_perf_dequeue_heavy_assets', 1001);

function harmat_perf_register_legacy_map_placeholder() {
    if (is_admin()) {
        return;
    }

    if (!shortcode_exists('custom_map_layer')) {
        return;
    }

    $original_custom_map_layer = $GLOBALS['shortcode_tags']['custom_map_layer'];

    remove_shortcode('custom_map_layer');

    add_shortcode('custom_map_layer', function ($atts = array(), $content = null, $tag = '') use ($original_custom_map_layer) {
        $atts = shortcode_atts(array('id' => ''), (array) $atts, 'custom_map_layer');

        if (is_front_page() && (string) $atts['id'] === '6982') {
            return '<div class="cml-wrapper harmat-legacy-map-placeholder" aria-hidden="true" style="display:none!important"></div>';
        }

        if (is_callable($original_custom_map_layer)) {
            return call_user_func($original_custom_map_layer, $atts, $content, $tag);
        }

        return '';
    });
}
add_action('init', 'harmat_perf_register_legacy_map_placeholder', 100);

function harmat_perf_prefetch_key_pages() {
    if (is_admin() || !is_front_page()) {
        return;
    }

    echo '<link rel="prefetch" href="' . esc_url(home_url('/lakaskereso/')) . '" as="document">' . "\n";
    echo '<link rel="prefetch" href="' . esc_url(home_url('/virtualis-lakasvalaszto/')) . '" as="document">' . "\n";
}
add_action('wp_head', 'harmat_perf_prefetch_key_pages', 8);

function harmat_perf_mobile_hero_video_switch() {
    if (is_admin() || !is_front_page()) {
        return;
    }

    $desktop = content_url('/uploads/2026/05/yulu-garden-source-compressed-60m.mp4');
    $mobile = content_url('/uploads/2026/05/yulu-garden-mobile-720p.mp4');
    ?>
<script id="harmat-mobile-hero-video-switch">
(function () {
  if (!window.matchMedia || !window.matchMedia("(max-width: 767px)").matches) return;
  var desktop = <?php echo wp_json_encode($desktop); ?>;
  var mobile = <?php echo wp_json_encode($mobile); ?>;
  var escapedDesktop = desktop.replace(/\//g, "\\/");
  var escapedMobile = mobile.replace(/\//g, "\\/");

  function replaceString(value) {
    return value === desktop ? mobile : (value === escapedDesktop ? escapedMobile : value);
  }

  function patchObject(value, seen) {
    if (!value || typeof value !== "object") return false;
    if (seen.indexOf(value) !== -1) return false;
    seen.push(value);
    var changed = false;
    Object.keys(value).forEach(function (key) {
      var item = value[key];
      if (typeof item === "string") {
        var next = replaceString(item);
        if (next !== item) {
          value[key] = next;
          changed = true;
        }
      } else if (item && typeof item === "object") {
        changed = patchObject(item, seen) || changed;
      }
    });
    return changed;
  }

  function patchSliderVideo() {
    var changed = false;
    if (window.SR7 && window.SR7.JSON) {
      changed = patchObject(window.SR7.JSON, []) || changed;
    }
    document.querySelectorAll("video source[src], video[src]").forEach(function (node) {
      var src = node.getAttribute("src");
      if (src === desktop || src === escapedDesktop) {
        node.setAttribute("src", mobile);
        if (node.parentElement && node.parentElement.load) node.parentElement.load();
        changed = true;
      }
    });
    return changed;
  }

  var tries = 0;
  var timer = window.setInterval(function () {
    tries += 1;
    if (patchSliderVideo() || tries > 160) window.clearInterval(timer);
  }, 25);
  patchSliderVideo();
})();
</script>
    <?php
}
add_action('wp_head', 'harmat_perf_mobile_hero_video_switch', 2);


function harmat_perf_home_hero_cta() {
    if (is_admin() || !is_front_page()) {
        return;
    }

    $finder = esc_url(home_url('/lakaskereso/'));
    $virtual = esc_url(home_url('/virtualis-lakasvalaszto/'));
    ?>
<style id="harmat-home-hero-cta-style">
  .harmat-home-hero-cta {
    position: absolute;
    left: clamp(28px, 7vw, 118px);
    bottom: clamp(92px, 16vh, 180px);
    z-index: 28;
    display: flex;
    align-items: center;
    gap: 12px;
    pointer-events: auto;
  }
  .harmat-home-hero-cta a {
    min-width: 205px;
    min-height: 48px;
    padding: 0 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255,255,255,.72);
    background: rgba(168,112,39,.95);
    color: #fff !important;
    font-family: Montserrat, Arial, sans-serif;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .11em;
    line-height: 1.2;
    text-transform: uppercase;
    text-decoration: none !important;
    box-shadow: 0 14px 34px rgba(0,0,0,.18);
    transition: transform .18s ease, background-color .18s ease, border-color .18s ease;
  }
  .harmat-home-hero-cta a:nth-child(2) {
    background: rgba(20,42,36,.62);
    border-color: rgba(255,255,255,.86);
  }
  .harmat-home-hero-cta a:hover,
  .harmat-home-hero-cta a:focus-visible {
    transform: translateY(-2px);
    background: rgba(132,86,24,.98);
  }
  .harmat-home-hero-cta a:nth-child(2):hover,
  .harmat-home-hero-cta a:nth-child(2):focus-visible {
    background: rgba(20,42,36,.86);
  }
  @media (max-width: 767px) {
    .harmat-home-hero-cta {
      left: 50%;
      right: auto;
      bottom: clamp(150px, 20vh, 178px);
      transform: translateX(-50%);
      width: min(88vw, 360px);
      display: grid;
      grid-template-columns: 1fr;
      gap: 9px;
    }
    .harmat-home-hero-cta a {
      min-width: 0;
      width: 100%;
      min-height: 44px;
      padding: 0 18px;
      font-size: 11px;
    }
  }
</style>
<script id="harmat-home-hero-cta-script">
(function () {
  if (window.harmatHomeHeroCtaReady) return;
  window.harmatHomeHeroCtaReady = true;
  var html = '<div class="harmat-home-hero-cta" aria-label="Gyors lak\u00e1sv\u00e1laszt\u00e1s"><a href="<?php echo $finder; ?>">Lak\u00e1sok megtekint\u00e9se</a><a href="<?php echo $virtual; ?>">Virtu\u00e1lis v\u00e1laszt\u00f3</a></div>';

  function findHero() {
    return document.querySelector('rs-module-wrap, sr7-module, .rev_slider_wrapper, rs-module');
  }

  function attach() {
    if (document.querySelector('.harmat-home-hero-cta')) return true;
    var hero = findHero();
    if (!hero) return false;
    var style = window.getComputedStyle(hero);
    if (style.position === 'static') hero.style.position = 'relative';
    hero.insertAdjacentHTML('beforeend', html);
    return true;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attach);
  } else {
    attach();
  }
  var tries = 0;
  var timer = window.setInterval(function () {
    tries += 1;
    if (attach() || tries > 120) window.clearInterval(timer);
  }, 100);
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_perf_home_hero_cta', 20);


function harmat_perf_global_navigation_polish() {
    if (is_admin()) {
        return;
    }
    ?>
<style id="harmat-global-navigation-polish">
  body .elementor-element-4d7a363 .elementor-icon {
    width: 46px !important;
    height: 46px !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(38,49,55,.28);
    background: rgba(255,255,255,.82);
    box-shadow: 0 10px 24px rgba(38,47,50,.08);
    transition: border-color .2s ease, background .2s ease, box-shadow .2s ease, transform .2s ease;
  }
  body .elementor-element-4d7a363 .elementor-icon:hover {
    border-color: rgba(168,116,42,.58);
    background: rgba(255,255,255,.95);
    box-shadow: 0 14px 30px rgba(38,47,50,.12);
    transform: translateY(-1px);
  }
  body .elementor-element-4d7a363 .elementor-icon svg {
    width: 22px !important;
    height: 22px !important;
  }
  body .elementor-element-4d7a363 .elementor-icon svg path,
  body .elementor-element-4d7a363 .elementor-icon svg line,
  body .elementor-element-4d7a363 .elementor-icon svg rect {
    stroke: #263238 !important;
    fill: #263238 !important;
  }
  body:not(.elementor-editor-active) .harmat-apartment-picker .harmat-floorplan-preview,
  body:not(.elementor-editor-active) .harmat-apartment-picker .harmat-floorplan-preview img {
    display: none !important;
  }
  body:not(.elementor-editor-active) .wpcf7 form.submitting .wpcf7-submit {
    opacity: .72 !important;
    pointer-events: none !important;
    background: #a8742a !important;
    color: #fff !important;
  }
  body:not(.elementor-editor-active) .wpcf7 form.submitting .wpcf7-spinner {
    visibility: visible !important;
    margin-left: 12px !important;
  }
  body:not(.elementor-editor-active) .wpcf7 input[type="checkbox"] {
    width: 16px !important;
    height: 16px !important;
    min-width: 16px !important;
    margin: 2px 10px 0 0 !important;
    appearance: auto !important;
    accent-color: #a8742a !important;
    opacity: 1 !important;
    visibility: visible !important;
  }
  body:not(.elementor-editor-active) .wpcf7 .wpcf7-list-item {
    margin-left: 0 !important;
  }
  body:not(.elementor-editor-active) .wpcf7 .wpcf7-list-item label {
    display: inline-flex !important;
    align-items: flex-start !important;
    gap: 8px !important;
    color: #253137 !important;
    font-family: Montserrat, Arial, sans-serif !important;
    font-size: 12px !important;
    line-height: 1.45 !important;
  }
  body:not(.elementor-editor-active) .wpcf7 .wpcf7-submit:disabled,
  body:not(.elementor-editor-active) .wpcf7 .wpcf7-submit.harmat-submit-disabled {
    opacity: .48 !important;
    cursor: not-allowed !important;
    filter: grayscale(.16) !important;
    pointer-events: none !important;
  }
  body:not(.elementor-editor-active) .harmat-privacy-confirm {
    display: flex !important;
    align-items: flex-start !important;
    gap: 10px !important;
    margin: 2px 0 0 !important;
    color: #253137 !important;
    font-family: Montserrat, Arial, sans-serif !important;
    font-size: 12px !important;
    line-height: 1.45 !important;
  }
  body:not(.elementor-editor-active) .harmat-privacy-confirm a {
    color: #a8742a !important;
    font-weight: 900 !important;
    text-decoration: underline !important;
  }
  body:not(.elementor-editor-active) .wpcf7 form .wpcf7-response-output {
    border-color: rgba(168,116,42,.28) !important;
    background: #fffaf2 !important;
    color: #253137 !important;
    font-family: Montserrat, Arial, sans-serif !important;
    font-size: 13px !important;
    line-height: 1.55 !important;
  }
</style>
    <?php
}
add_action('wp_head', 'harmat_perf_global_navigation_polish', 80);

function harmat_perf_offer_form_guard() {
    if (is_admin()) {
        return;
    }
    ?>
<script id="harmat-offer-form-guard">
(function () {
  function valueOf(form, selector) {
    var field = form.querySelector(selector);
    return field ? String(field.value || '').trim() : '';
  }

  function isEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function requiredReady(form) {
    var hasPicker = !!form.querySelector('.harmat-apartment-picker');
    var privacy = form.querySelector('[name="harmat-privacy-confirm"], input[type="checkbox"]');
    var required = [
      valueOf(form, '[data-harmat-apt="building"], [name="selected-building"]'),
      valueOf(form, '[data-harmat-apt="floor"], [name="selected-floor"]'),
      valueOf(form, '[data-harmat-apt="number"], [name="selected-apartment"]'),
      valueOf(form, '[name="your-name"]'),
      valueOf(form, '[name="your-email"]'),
      valueOf(form, '[name="your-date"]'),
      valueOf(form, '[name="your-time"]'),
      valueOf(form, '[name="your-phone"]')
    ];
    if (!hasPicker) {
      required = required.slice(3);
    }
    return required.every(Boolean) && isEmail(valueOf(form, '[name="your-email"]')) && (!privacy || privacy.checked);
  }

  function prepareForm(form) {
    if (!form || form.dataset.harmatOfferGuard === '1') return;
    form.dataset.harmatOfferGuard = '1';

    var submit = form.querySelector('.wpcf7-submit');
    if (!submit) return;

    function ensurePrivacy() {
      if (form.querySelector('[name="harmat-privacy-confirm"], input[type="checkbox"]')) return;
      var privacyHtml = '<label class="harmat-privacy-confirm"><input type="checkbox" name="harmat-privacy-confirm" value="1"> <span>Elfogadom az <a href="<?php echo esc_url(home_url('/adatvedelmi-tajekoztato/')); ?>" target="_blank" rel="noopener">Adatv\u00e9delmi t\u00e1j\u00e9koztat\u00f3t</a>, \u00e9s hozz\u00e1j\u00e1rulok az adataim kapcsolatfelv\u00e9tel c\u00e9lj\u00e1b\u00f3l t\u00f6rt\u00e9n\u0151 kezel\u00e9s\u00e9hez.</span></label>';
      var textarea = form.querySelector('textarea');
      var anchor = textarea ? (textarea.closest('[class*="col-"]') || textarea.parentElement) : null;
      if (anchor && anchor.insertAdjacentHTML) {
        anchor.insertAdjacentHTML('afterend', privacyHtml);
      } else if (submit.parentNode && submit.parentNode.insertAdjacentHTML) {
        submit.parentNode.insertAdjacentHTML('beforebegin', privacyHtml);
      }
    }

    submit.classList.add('harmat-submit-disabled');
    submit.disabled = true;
    submit.setAttribute('aria-disabled', 'true');

    function update() {
      ensurePrivacy();
      var ready = requiredReady(form);
      submit.disabled = !ready;
      submit.classList.toggle('harmat-submit-disabled', !ready);
      submit.setAttribute('aria-disabled', ready ? 'false' : 'true');
    }

    form.querySelectorAll('input, select, textarea').forEach(function (field) {
      field.addEventListener('input', update);
      field.addEventListener('change', update);
    });
    form.addEventListener('submit', function (event) {
      update();
      if (!requiredReady(form)) {
        event.preventDefault();
        event.stopPropagation();
        var response = form.querySelector('.wpcf7-response-output');
        if (response) {
          response.textContent = 'K\u00e9rj\u00fck, t\u00f6ltse ki a k\u00f6telez\u0151 mez\u0151ket, \u00e9s fogadja el az adatv\u00e9delmi t\u00e1j\u00e9koztat\u00f3t.';
          response.style.display = 'block';
        }
      }
    }, true);
    submit.addEventListener('click', function (event) {
      update();
      if (!requiredReady(form)) {
        event.preventDefault();
        event.stopPropagation();
      }
    }, true);
    update();
    setTimeout(update, 300);
    setTimeout(update, 1000);
    setTimeout(update, 2200);
    var ticks = 0;
    var timer = window.setInterval(function () {
      ticks += 1;
      update();
      if (ticks > 30 || !document.documentElement.contains(form)) {
        window.clearInterval(timer);
      }
    }, 500);
  }
  function run() {
    document.querySelectorAll('.wpcf7 form').forEach(prepareForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
  window.addEventListener('load', run);
  setTimeout(run, 800);
  setTimeout(run, 2200);
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_perf_offer_form_guard', 90);

function harmat_perf_test_page_body_classes($classes) {
    if (is_front_page() || is_page('fooldal-teszt')) {
        $classes[] = 'harmat-home-test';
    }
    if (is_page('virtualis-lakasvalaszto-teszt')) {
        $classes[] = 'harmat-virtual-selector-test';
    }
    if (!empty($_GET['hm_home_embed']) && strpos(harmat_perf_request_path(), 'virtualis-lakasvalaszto') === 0) {
        $classes[] = 'harmat-virtual-home-embed';
        $classes[] = 'harmat-virtual-selector-test';
    }
    return $classes;
}
add_filter('body_class', 'harmat_perf_test_page_body_classes', 20);


function harmat_perf_home_launch_polish() {
    if (is_admin() || !is_front_page()) {
        return;
    }

    $gallery_url = esc_url(home_url('/galeria/'));
    $virtual_entry_url = esc_url(home_url('/virtualis-lakasvalaszto-elso-utem/'));
    $virtual_embed_enabled = !empty($_GET['hm_virtual_embed']);
    $virtual_embed_url = esc_url(add_query_arg('hm_home_embed', '1', home_url('/virtualis-lakasvalaszto-elso-utem/')));
    $virtual_preview = esc_url(content_url('/uploads/2026/03/Start/bld-Start-frame-01.webp'));
    $maps_url = esc_url('https://www.google.com/maps/search/?api=1&query=Harmat%20utca%2022%2C%201105%20Budapest');
    $map_embed_url = esc_url('https://maps.google.com/maps?q=Harmat%20utca%2022%2C%201105%20Budapest&t=k&z=16&output=embed');
    ?>
<style id="harmat-home-launch-polish">
  @media (max-width: 767px) {
    body.home rs-module-wrap,
    body.home rs-module,
    body.home sr7-module,
    body.home .rev_slider_wrapper {
      min-height: 100svh !important;
      height: 100svh !important;
    }
    body.home rs-module video,
    body.home sr7-module video,
    body.home video.sr7-html5-video {
      width: 100% !important;
      height: 100% !important;
      min-width: 100% !important;
      min-height: 100% !important;
      object-fit: cover !important;
      object-position: center center !important;
    }
  }

  body .elementor-element-4d7a363 .elementor-icon {
    width: 46px !important;
    height: 46px !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(38,49,55,.28);
    background: rgba(255,255,255,.82);
    box-shadow: 0 10px 24px rgba(38,47,50,.08);
  }
  body .elementor-element-4d7a363 .elementor-icon svg {
    width: 22px !important;
    height: 22px !important;
  }
  body .elementor-element-4d7a363 .elementor-icon svg path,
  body .elementor-element-4d7a363 .elementor-icon svg line,
  body .elementor-element-4d7a363 .elementor-icon svg rect {
    stroke: #263238 !important;
    fill: #263238 !important;
  }

  body.home .elementor-element-dff4be8 .column-item:nth-of-type(n+7) {
    display: none !important;
  }
  body.home .elementor-element-dff4be8 .isotope-grid,
  body.home .elementor-element-dff4be8 .grid {
    height: auto !important;
    min-height: 0 !important;
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
  }
  body.home .elementor-element-dff4be8 .column-item {
    position: relative !important;
    inset: auto !important;
    width: auto !important;
    max-width: none !important;
    transform: none !important;
  }
  body.home .elementor-element-dff4be8 .column-item a {
    display: block !important;
    width: 100% !important;
  }
  body.home .elementor-element-dff4be8 .column-item img {
    display: block !important;
    width: 100% !important;
    height: 220px !important;
    object-fit: cover !important;
  }
  body.home .harmat-gallery-more {
    display: none !important;
  }
  body.home .harmat-gallery-heading-row {
    width: min(1120px, calc(100% - 32px));
    margin: 0 auto 34px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
  }
  body.home .harmat-gallery-heading-row .elementor-widget-heading,
  body.home .harmat-gallery-heading-row .elementor-widget-container {
    margin: 0 !important;
  }
  body.home .harmat-gallery-heading-row .elementor-widget-heading {
    display: none !important;
  }
  body.home .harmat-gallery-title-clean {
    margin: 0;
    color: #3f4448;
    font-family: "Marcellus SC", Georgia, serif;
    font-size: clamp(28px, 3vw, 42px);
    line-height: 1;
    font-weight: 400;
    letter-spacing: 0;
    text-transform: uppercase;
  }
  body.home .elementor-element-dff4be8 .elementor-widget-heading .elementor-heading-title::before,
  body.home .elementor-element-dff4be8 .elementor-widget-heading .elementor-widget-container::before,
  body.home .elementor-element-dff4be8 .elementor-widget-heading .aux-head-before,
  body.home .elementor-element-dff4be8 .elementor-widget-heading .aux-modern-heading-divider {
    display: none !important;
    content: none !important;
  }
  body.home .harmat-gallery-title-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 30px;
    border: 1px solid #a8742a;
    background: #a8742a;
    color: #fff !important;
    font-family: Montserrat, Arial, sans-serif;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
    text-decoration: none !important;
    flex: 0 0 auto;
  }

  body.harmat-home-test .elementor-element-9c1e1fe .harmat-virtual-entry-media,
  body.harmat-home-test .elementor-element-bd8cb2e .harmat-virtual-entry-media {
    position: relative;
    display: block;
    width: 100% !important;
    aspect-ratio: 16 / 9;
    line-height: 0;
    overflow: hidden;
    background: #17372f;
    box-shadow: 0 22px 42px rgba(25, 39, 35, .13);
    text-decoration: none !important;
  }
  @media (min-width: 1024px) {
    body.harmat-home-test .elementor-element-9c1e1fe > .e-con-inner {
      display: grid !important;
      grid-template-columns: minmax(300px, .78fr) minmax(720px, 1.35fr) !important;
      align-items: center !important;
      gap: clamp(52px, 5vw, 92px) !important;
      max-width: 1320px !important;
      width: min(94vw, 1320px) !important;
    }
    body.harmat-home-test .elementor-element-9c1e1fe .elementor-element-d0cd545,
    body.harmat-home-test .elementor-element-9c1e1fe .elementor-element-bd8cb2e {
      width: 100% !important;
      max-width: none !important;
      flex: none !important;
    }
  }
  body.harmat-home-test .elementor-element-9c1e1fe .elementor-element-bd8cb2e,
  body.harmat-home-test .elementor-element-9c1e1fe .elementor-element-bd8cb2e > .e-con-inner,
  body.harmat-home-test .elementor-element-9c1e1fe .elementor-element-bd8cb2e .elementor-widget-container,
  body.harmat-home-test .elementor-element-9c1e1fe .elementor-element-bd8cb2e .elementor-image {
    --min-height: 0 !important;
    min-height: 0 !important;
    height: auto !important;
    width: 100% !important;
    max-width: none !important;
    padding: 0 !important;
    margin-bottom: 0 !important;
    background: transparent !important;
  }
  body.harmat-home-test .elementor-element-9c1e1fe .elementor-element-bd8cb2e .elementor-element-e4e6d34 {
    position: relative !important;
    inset: auto !important;
    left: auto !important;
    right: auto !important;
    top: auto !important;
    bottom: auto !important;
    width: min(64vw, 820px) !important;
    max-width: none !important;
    min-width: 680px !important;
    margin: 0 auto !important;
    transform: none !important;
  }
  body.harmat-home-test .elementor-element-9c1e1fe .elementor-element-bd8cb2e .elementor-element-e4e6d34 > .elementor-widget-container,
  body.harmat-home-test .elementor-element-9c1e1fe .elementor-element-bd8cb2e .elementor-image {
    display: block !important;
  }
  body.harmat-home-test .harmat-virtual-entry-media img {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
    aspect-ratio: 16 / 9;
    object-fit: cover !important;
    object-position: center center !important;
    transform: scale(1.055);
    transition: transform .28s ease, filter .28s ease;
  }
  body.harmat-home-test .harmat-virtual-entry-media::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(23,55,47,.02), rgba(23,55,47,.15));
    pointer-events: none;
  }
  body.harmat-home-test .harmat-virtual-entry-media:hover img,
  body.harmat-home-test .harmat-virtual-entry-media:focus-visible img {
    transform: scale(1.085);
    filter: saturate(1.04) contrast(1.03);
  }
  body.harmat-home-test .harmat-virtual-entry-badge {
    display: none !important;
  }
  body.harmat-home-test .harmat-virtual-entry-media:focus-visible {
    outline: 2px solid #a8742a;
    outline-offset: 4px;
  }
  body.harmat-home-test .harmat-home-virtual-embed {
    position: relative;
    display: block;
    width: 100% !important;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: #f7efe3;
    border: 1px solid rgba(168, 116, 42, .18);
    box-shadow: 0 24px 54px rgba(25, 39, 35, .14);
  }
  body.harmat-home-test .harmat-home-virtual-embed iframe {
    position: absolute;
    inset: 0;
    display: block;
    width: 100% !important;
    height: 100% !important;
    border: 0;
    background: #fff;
  }
  body.harmat-home-test .harmat-home-virtual-embed::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    box-shadow: inset 0 0 0 1px rgba(168, 116, 42, .16);
  }
  @media (max-width: 767px) {
    body.harmat-home-test .elementor-element-9c1e1fe .elementor-element-bd8cb2e .elementor-element-e4e6d34 {
      width: 100% !important;
      max-width: 100% !important;
      min-width: 0 !important;
    }
  }

  body.home .elementor-element-7db0e20 {
    padding: clamp(54px, 6vw, 86px) 0 28px !important;
    background: linear-gradient(180deg, #fff 0%, #fbf4e9 100%) !important;
  }
  body.home .elementor-element-7db0e20 .elementor-heading-title {
    color: #263238 !important;
    letter-spacing: .04em !important;
  }
  body.home .elementor-element-7db0e20 .elementor-element-592464d .elementor-heading-title {
    font-size: clamp(20px, 2.2vw, 28px) !important;
    line-height: 1.25 !important;
  }
  body.home .elementor-element-7db0e20 .elementor-element-3caa750 .elementor-heading-title {
    font-size: clamp(28px, 3.2vw, 42px) !important;
    line-height: 1.16 !important;
  }
  body.home .elementor-element-7db0e20 .elementor-element-7bec9e4 .elementor-heading-title {
    font-size: clamp(18px, 2vw, 26px) !important;
    line-height: 1.25 !important;
  }
  body.home .elementor-element-e21913f {
    padding: 0 20px clamp(74px, 7vw, 108px) !important;
    background: #fbf4e9 !important;
  }
  body.home .elementor-element-e21913f > .elementor-container {
    width: min(1120px, 100%) !important;
    max-width: 1120px !important;
    display: grid !important;
    grid-template-columns: minmax(260px, 320px) minmax(0, 560px);
    gap: clamp(46px, 6vw, 76px);
    justify-content: center;
    align-items: stretch;
    border: 0;
    background: transparent;
    box-shadow: none;
  }
  body.home .elementor-element-e21913f > .elementor-container > .elementor-column {
    width: auto !important;
  }
  body.home .elementor-element-e21913f .elementor-element-008711c,
  body.home .elementor-element-e21913f .elementor-element-0c63073 {
    display: none !important;
  }
  body.home .elementor-element-e21913f .elementor-element-f37b649,
  body.home .elementor-element-e21913f .elementor-element-d7feb88 {
    border: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
  }
  body.home .elementor-element-e21913f .elementor-element-f37b649 {
    border-right: 0 !important;
    background: transparent !important;
  }
  body.home .elementor-element-e21913f .elementor-element-f37b649 > .elementor-widget-wrap,
  body.home .elementor-element-e21913f .elementor-element-d7feb88 > .elementor-widget-wrap {
    padding: 0 !important;
  }
  body.home .elementor-element-e21913f .elementor-element-f37b649 .elementor-widget-heading .elementor-heading-title,
  body.home .elementor-element-e21913f .elementor-element-d7feb88 .elementor-widget-heading .elementor-heading-title {
    color: #a8742a !important;
    font-family: Montserrat, Arial, sans-serif !important;
    font-size: 12px !important;
    font-weight: 900 !important;
    letter-spacing: .18em !important;
    text-transform: uppercase !important;
  }
  body.home .elementor-element-e21913f .elementor-element-f37b649 .elementor-widget-text-editor {
    color: #58636b !important;
    font-size: 14px !important;
    line-height: 1.85 !important;
  }
  body.home .elementor-element-e21913f .elementor-element-f37b649 .elementor-inner-section {
    margin-top: 14px !important;
    padding: 12px 0 !important;
    border-top: 1px solid rgba(168,116,42,.13);
  }
  body.home .elementor-element-a2d4fc9 {
    width: 100% !important;
  }
  body.home .elementor-element-a2d4fc9 .row {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 16px 10px !important;
    width: 100% !important;
    margin: 0 !important;
    grid-column: 1 / -1 !important;
  }
  body.home .elementor-element-a2d4fc9 .row > [class*="col-"] {
    width: auto !important;
    max-width: none !important;
    flex: none !important;
    padding: 0 !important;
  }
  body.home .elementor-element-a2d4fc9 .row > .col-sm-12 {
    grid-column: 1 / -1 !important;
  }
  body.home .elementor-element-a2d4fc9 .harmat-privacy-confirm {
    grid-column: 1 / -1 !important;
  }
  body.home .elementor-element-a2d4fc9 .harmat-apartment-picker {
    display: grid !important;
    gap: 12px !important;
    margin: 0 0 2px !important;
  }
  body.home .elementor-element-a2d4fc9 .harmat-apt-select-grid {
    display: block !important;
    width: 100% !important;
  }
  body.home .elementor-element-a2d4fc9 .harmat-apt-select-grid p {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 10px !important;
    width: 100% !important;
    margin: 0 !important;
  }
  body.home .elementor-element-a2d4fc9 .harmat-apt-select-grid br {
    display: none !important;
  }
  body.home .elementor-element-a2d4fc9 .harmat-apt-info {
    width: 100% !important;
    margin: 0 !important;
    padding: 13px 16px !important;
    border: 1px solid rgba(168,116,42,.35) !important;
    border-left: 3px solid #a8742a !important;
    background: #fffaf2 !important;
    color: #253137 !important;
    font-family: Montserrat, Arial, sans-serif !important;
    font-size: 13px !important;
    line-height: 1.55 !important;
  }
  body.home .elementor-element-a2d4fc9 form {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px 10px;
  }
  body.home .elementor-element-a2d4fc9 form > p,
  body.home .elementor-element-a2d4fc9 form > div {
    margin: 0 !important;
  }
  body.home .elementor-element-a2d4fc9 input:not([type="hidden"]):not([type="submit"]):not([type="checkbox"]):not([type="radio"]),
  body.home .elementor-element-a2d4fc9 select,
  body.home .elementor-element-a2d4fc9 textarea {
    width: 100% !important;
    height: 48px !important;
    border: 1px solid rgba(168,116,42,.55) !important;
    background: #fffdf8 !important;
    color: #32383b !important;
    font-family: Montserrat, Arial, sans-serif !important;
    font-size: 13px !important;
  }
  body.home .elementor-element-a2d4fc9 textarea {
    grid-column: 1 / -1;
    min-height: 114px !important;
    resize: vertical;
  }
  body.home .elementor-element-a2d4fc9 input[type="checkbox"] {
    width: 14px !important;
    height: 14px !important;
    min-width: 14px !important;
    max-width: 14px !important;
    min-height: 14px !important;
    margin: 2px 10px 0 0 !important;
    padding: 0 !important;
    border: 1px solid rgba(168,116,42,.55) !important;
    box-shadow: none !important;
    transform: none !important;
    appearance: auto !important;
    accent-color: #a8742a !important;
  }
  body.home .elementor-element-a2d4fc9 .wpcf7-submit {
    grid-column: 1 / -1;
    width: 100% !important;
    min-height: 48px !important;
    border: 1px solid #a8742a !important;
    background: #d2bb92 !important;
    color: #fff !important;
    font-family: Montserrat, Arial, sans-serif !important;
    font-size: 13px !important;
    font-weight: 900 !important;
    letter-spacing: .18em !important;
    text-transform: uppercase !important;
  }
  body.home .elementor-element-a2d4fc9 .wpcf7-submit:not(:disabled):not(.harmat-submit-disabled) {
    background: #a8742a !important;
  }
  body.home .elementor-element-a2d4fc9 .wpcf7-spinner {
    position: absolute;
  }
  body.home .elementor-element-a2d4fc9 .wpcf7-response-output {
    grid-column: 1 / -1;
    margin: 0 !important;
  }
  body.home .elementor-element-a2d4fc9 .harmat-native-legal {
    display: none !important;
  }
  body.home .elementor-element-dff4be8 .elementor-widget-heading .elementor-widget-container,
  body.home .elementor-element-dff4be8 .elementor-widget-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
  }
  body.home .elementor-element-dff4be8 .elementor-widget-heading .harmat-gallery-more {
    margin-left: auto;
  }
  @media (max-width: 767px) {
    body.home .elementor-element-dff4be8 .isotope-grid,
    body.home .elementor-element-dff4be8 .grid {
      grid-template-columns: 1fr;
      gap: 16px;
    }
    body.home .elementor-element-dff4be8 .column-item img {
      height: auto !important;
      min-height: 210px;
      aspect-ratio: 16 / 10;
    }
    body.home .harmat-gallery-heading-row {
      align-items: flex-start;
      flex-direction: column;
      margin-bottom: 24px;
    }
    body.home .harmat-gallery-title-link {
      width: 100%;
      min-height: 48px;
    }
    body.home .elementor-element-7db0e20 {
      padding-top: 46px !important;
    }
    body.home .elementor-element-e21913f > .elementor-container,
    body.home .elementor-element-a2d4fc9 form {
      grid-template-columns: 1fr;
    }
    body.home .elementor-element-a2d4fc9 .row {
      grid-template-columns: 1fr !important;
    }
    body.home .elementor-element-a2d4fc9 .harmat-apt-select-grid p {
      grid-template-columns: 1fr !important;
    }
    body.home .elementor-element-e21913f {
      padding-left: 12px !important;
      padding-right: 12px !important;
    }
  }

  body.home .elementor-element-73a9239 {
    background-image: none !important;
  }
  body.home .elementor-element-d60b1b2 > .elementor-container,
  body.home .elementor-element-d60b1b2 > .elementor-column-gap-default {
    display: none !important;
  }
  body.home .elementor-element-d60b1b2 {
    padding: clamp(34px, 4vw, 56px) 0 clamp(70px, 7vw, 104px) !important;
    background: linear-gradient(180deg, #fff 0%, #fbf4e9 54%, #fff 100%) !important;
  }
  body.home .harmat-about-remake {
    width: min(1380px, calc(100% - 36px));
    margin: 0 auto;
    font-family: Montserrat, Arial, sans-serif;
    color: #263238;
  }
  body.home .harmat-about-grid {
    display: grid;
    grid-template-columns: minmax(420px, .78fr) minmax(700px, 1.22fr);
    gap: 0;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid rgba(168,116,42,.22);
    background: rgba(255,255,255,.86);
    box-shadow: 0 24px 58px rgba(38,47,50,.08);
  }
  body.home .harmat-about-copy {
    position: relative;
    padding: clamp(40px, 4vw, 64px) clamp(34px, 4vw, 58px);
    background: linear-gradient(145deg, rgba(255,255,255,.97), rgba(251,244,233,.86));
  }
  body.home .harmat-about-copy::before {
    content: "";
    position: absolute;
    left: clamp(38px, 4.5vw, 66px);
    top: 0;
    width: 86px;
    height: 5px;
    background: #a8742a;
  }
  body.home .harmat-about-eyebrow {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    margin-bottom: 20px;
    padding: 0 12px;
    border: 1px solid rgba(168,116,42,.24);
    background: rgba(255,255,255,.76);
    color: #a8742a;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .18em;
    text-transform: uppercase;
  }
  body.home .harmat-about-copy h2 {
    margin: 0 0 20px;
    color: #263238;
    font-family: "Marcellus SC", Georgia, serif;
    font-size: clamp(38px, 3.7vw, 58px);
    line-height: 1.02;
    font-weight: 400;
    letter-spacing: 0;
  }
  body.home .harmat-about-copy p {
    max-width: 560px;
    margin: 0 0 28px;
    color: #667078;
    font-size: 15px;
    line-height: 1.85;
  }
  body.home .harmat-about-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 0 0 24px;
  }
  body.home .harmat-about-meta-item {
    min-height: 82px;
    padding: 16px 15px;
    border: 1px solid rgba(168,116,42,.20);
    background: rgba(255,255,255,.82);
  }
  body.home .harmat-about-meta-item span {
    display: block;
    margin-bottom: 8px;
    color: #a8742a;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .13em;
    text-transform: uppercase;
  }
  body.home .harmat-about-meta-item strong {
    display: block;
    color: #263238;
    font-family: "Marcellus SC", Georgia, serif;
    font-size: clamp(22px, 1.65vw, 26px);
    font-weight: 400;
    line-height: 1.1;
    white-space: nowrap;
  }
  body.home .harmat-about-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 12px;
    margin: 0;
    padding: 0;
    list-style: none;
  }
  body.home .harmat-about-list li {
    position: relative;
    min-height: 42px;
    padding: 10px 12px 10px 30px;
    border: 1px solid rgba(168,116,42,.16);
    background: rgba(255,255,255,.72);
    color: #39464c;
    font-size: 12px;
    line-height: 1.45;
    font-weight: 700;
  }
  body.home .harmat-about-list li::before {
    content: "";
    position: absolute;
    left: 13px;
    top: 17px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #a8742a;
  }
  body.home .harmat-about-map {
    position: relative;
    width: 100%;
    min-height: 620px;
    overflow: hidden;
    border: 0;
    border-left: 1px solid rgba(168,116,42,.18);
    background: #1c2924;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.40);
  }
  body.home .harmat-about-map iframe {
    position: absolute;
    inset: 0;
    z-index: 1;
    width: 100%;
    height: 100%;
    border: 0;
    filter: saturate(1.06) contrast(1.04);
  }
  body.home .harmat-about-map-image {
    position: absolute;
    inset: 0;
    z-index: 1;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    filter: saturate(1.08) contrast(1.04);
  }
  body.home .harmat-about-map::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(180deg, rgba(15,42,36,.08), rgba(255,255,255,0) 34%, rgba(15,42,36,.12)),
      linear-gradient(90deg, rgba(15,42,36,.12), rgba(255,255,255,0) 46%, rgba(168,116,42,.08));
    pointer-events: none;
    z-index: 2;
  }
  body.home .harmat-map-pin {
    position: absolute;
    z-index: 3;
    display: inline-flex;
    transform: translate(-50%, -50%);
    align-items: center;
    min-height: 30px;
    padding: 0 11px;
    border-radius: 7px;
    background: rgba(255,255,255,.94);
    color: #253137;
    border: 1px solid rgba(168,116,42,.34);
    box-shadow: 0 10px 24px rgba(0,0,0,.13);
    font-family: Montserrat, Arial, sans-serif;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
  }
  body.home .harmat-map-pin::before {
    content: "";
    width: 8px;
    height: 8px;
    margin-right: 7px;
    border-radius: 50%;
    background: #a8742a;
  }
  body.home .harmat-map-pin-main {
    background: #17372f;
    color: #fff;
    font-size: 12px;
    min-height: 36px;
    padding: 0 15px;
    box-shadow: 0 14px 32px rgba(0,0,0,.22);
  }
  body.home .harmat-map-pin-main::after {
    content: "";
    position: absolute;
    left: 50%;
    bottom: -12px;
    width: 16px;
    height: 16px;
    transform: translateX(-50%) rotate(45deg);
    background: #17372f;
    box-shadow: 7px 7px 18px rgba(0,0,0,.14);
  }
  body.home .harmat-map-pin-main::before {
    background: #58b6c1;
    position: relative;
    z-index: 2;
  }
  body.home .harmat-map-cta {
    position: absolute;
    left: 22px;
    bottom: 22px;
    z-index: 4;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 22px;
    background: #a8742a;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,.75);
    font-family: Montserrat, Arial, sans-serif;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    text-decoration: none !important;
    box-shadow: 0 12px 28px rgba(0,0,0,.18);
  }
  body.home .harmat-map-controls {
    display: none !important;
  }
  body.home .harmat-map-controls button {
    width: 38px;
    height: 38px;
    border: 1px solid rgba(255,255,255,.8);
    background: rgba(37,49,55,.88);
    color: #fff;
    font-size: 20px;
    font-weight: 800;
    line-height: 1;
    cursor: pointer;
  }
  body.home .harmat-about-visual {
    position: relative;
    min-height: 620px;
    overflow: hidden;
    border-left: 1px solid rgba(168,116,42,.18);
    background: #17372f;
  }
  body.home .harmat-about-visual img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center center;
    display: block;
    filter: saturate(1.08) contrast(1.03) brightness(1.08);
  }
  body.home .harmat-about-visual::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(180deg, rgba(16,43,37,.02), rgba(16,43,37,.1)),
      linear-gradient(90deg, rgba(16,43,37,.12), rgba(16,43,37,0) 58%);
    z-index: 1;
    pointer-events: none;
  }
  body.home .harmat-visual-card {
    position: absolute;
    left: 28px;
    bottom: 28px;
    z-index: 2;
    width: min(360px, calc(100% - 56px));
    padding: 24px 26px;
    background: rgba(23,55,47,.88);
    color: #fff;
    box-shadow: 0 24px 50px rgba(0,0,0,.22);
    backdrop-filter: blur(8px);
  }
  body.home .harmat-visual-card strong {
    display: block;
    margin-bottom: 10px;
    font-family: "Marcellus SC", Georgia, serif;
    font-size: 28px;
    font-weight: 400;
    line-height: 1.12;
  }
  body.home .harmat-visual-card span {
    display: block;
    color: rgba(255,255,255,.82);
    font-size: 13px;
    line-height: 1.7;
  }
  body.home .harmat-visual-badges {
    position: absolute;
    right: 24px;
    top: 24px;
    z-index: 2;
    display: grid;
    gap: 10px;
  }
  body.home .harmat-visual-badges span {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    justify-content: center;
    padding: 0 14px;
    background: rgba(255,255,255,.92);
    color: #17372f;
    border: 1px solid rgba(168,116,42,.25);
    box-shadow: 0 12px 28px rgba(0,0,0,.13);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .06em;
    text-transform: uppercase;
    white-space: nowrap;
  }
  body.home .harmat-visual-actions {
    position: absolute;
    left: 28px;
    top: 28px;
    z-index: 2;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }
  body.home .harmat-visual-actions a {
    display: inline-flex;
    min-height: 42px;
    align-items: center;
    justify-content: center;
    padding: 0 18px;
    background: #a8742a;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,.68);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    text-decoration: none !important;
    box-shadow: 0 12px 28px rgba(0,0,0,.16);
  }
  body.home .harmat-visual-actions a:nth-child(2) {
    background: rgba(23,55,47,.86);
  }
  body.home .harmat-about-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-top: 14px;
  }
  body.home .harmat-about-stat {
    position: relative;
    min-height: 124px;
    padding: 22px 24px 18px;
    border: 1px solid rgba(168,116,42,.18);
    background: rgba(255,255,255,.82);
    box-shadow: 0 18px 40px rgba(38,47,50,.07);
  }
  body.home .harmat-about-stat::before {
    content: "";
    position: absolute;
    left: 24px;
    top: 0;
    width: 54px;
    height: 4px;
    background: #a8742a;
  }
  body.home .harmat-about-stat strong {
    display: inline-flex;
    align-items: baseline;
    gap: 4px;
    color: #3f4448;
    font-family: "Marcellus SC", Georgia, serif;
    font-size: clamp(34px, 3.1vw, 46px);
    font-weight: 400;
    line-height: 1;
  }
  body.home .harmat-about-stat small {
    color: #8d9499;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  body.home .harmat-about-stat span {
    display: block;
    margin-top: 10px;
    color: #8f969b;
    font-size: 12px;
    text-transform: uppercase;
    line-height: 1.5;
    font-weight: 700;
  }
  @media (max-width: 767px) {
    body.home .harmat-about-grid,
    body.home .harmat-about-stats,
    body.home .harmat-about-list,
    body.home .harmat-about-meta {
      grid-template-columns: 1fr;
    }
    body.home .harmat-about-remake {
      width: min(100% - 24px, 440px);
    }
    body.home .harmat-about-grid {
      border: 1px solid rgba(168,116,42,.20);
    }
    body.home .harmat-about-copy {
      padding: 34px 24px 28px;
    }
    body.home .harmat-about-copy::before {
      left: 24px;
    }
    body.home .harmat-about-map {
      min-height: 430px;
      border-left: 0;
      border-top: 1px solid rgba(168,116,42,.18);
    }
    body.home .harmat-about-visual {
      min-height: 380px;
      border-left: 0;
      border-top: 1px solid rgba(168,116,42,.18);
    }
    body.home .harmat-visual-actions {
      left: 16px;
      top: 16px;
    }
    body.home .harmat-visual-actions a {
      min-height: 38px;
      padding: 0 13px;
      font-size: 10px;
    }
    body.home .harmat-visual-badges {
      right: 16px;
      top: 68px;
    }
    body.home .harmat-visual-badges span {
      min-height: 32px;
      padding: 0 10px;
      font-size: 10px;
    }
    body.home .harmat-visual-card {
      left: 16px;
      bottom: 16px;
      width: calc(100% - 32px);
      padding: 18px 18px;
    }
    body.home .harmat-visual-card strong {
      font-size: 23px;
    }
    body.home .harmat-map-pin {
      font-size: 10px;
      min-height: 27px;
      padding: 0 8px;
    }
    body.home .harmat-map-pin-main {
      font-size: 11px;
    }
    body.home .harmat-map-cta {
      left: 14px;
      bottom: 14px;
      min-height: 40px;
      padding: 0 15px;
      font-size: 11px;
    }
    body.home .harmat-about-stats {
      gap: 12px;
      margin-top: 14px;
    }
    body.home .harmat-about-stat {
      min-height: 108px;
      padding: 22px 20px 18px;
    }
  }
</style>
<script id="harmat-home-launch-polish-script">
(function () {
  function trimGallery() {
    var gallery = document.querySelector('body.home .elementor-element-dff4be8');
    if (!gallery) return;
    var items = Array.prototype.slice.call(gallery.querySelectorAll('.column-item'));
    items.forEach(function (item, index) {
      if (index > 5) {
        item.querySelectorAll('img').forEach(function (img) {
          img.removeAttribute('src');
          img.removeAttribute('srcset');
          img.removeAttribute('data-src');
          img.removeAttribute('data-srcset');
          img.setAttribute('loading', 'lazy');
        });
      }
    });
    gallery.querySelectorAll('.harmat-gallery-more').forEach(function (node) {
      node.remove();
    });
    if (!gallery.querySelector('.harmat-gallery-title-link')) {
      var headingWidget = gallery.querySelector('.elementor-widget-heading');
      var headingRow = document.createElement('div');
      headingRow.className = 'harmat-gallery-heading-row';
      if (headingWidget && headingWidget.parentNode) {
        headingWidget.parentNode.insertBefore(headingRow, headingWidget);
        headingRow.appendChild(headingWidget);
      } else {
        gallery.insertBefore(headingRow, gallery.firstChild);
        headingRow.innerHTML = '<div class="elementor-widget-heading"><div class="elementor-widget-container"><h2 class="elementor-heading-title elementor-size-default">Gal\u00e9ria</h2></div></div>';
      }
      var cleanTitle = document.createElement('h2');
      cleanTitle.className = 'harmat-gallery-title-clean';
      cleanTitle.textContent = 'Gal\u00e9ria';
      headingRow.insertBefore(cleanTitle, headingRow.firstChild);
      var link = document.createElement('a');
      link.className = 'harmat-gallery-title-link';
      link.href = '<?php echo $gallery_url; ?>';
      link.textContent = 'Teljes gal\u00e9ria';
      headingRow.appendChild(link);
    }
  }

  function polishVirtualSelectorEntry() {
    if (!document.body.classList.contains('harmat-home-test')) return;
    var section = document.querySelector('.elementor-element-9c1e1fe');
    if (section && section.querySelector('.harmat-home-virtual-embed')) {
      section.querySelectorAll('a[href*="/virtualis-lakasvalaszto/"]').forEach(function (link) {
        link.href = '<?php echo $virtual_entry_url; ?>';
      });
      return;
    }
    var mediaArea = section ? section.querySelector('.elementor-element-bd8cb2e') : null;
    var img = mediaArea ? mediaArea.querySelector('img') : null;
    if (!img && !<?php echo $virtual_embed_enabled ? 'true' : 'false'; ?>) {
      img = document.querySelector('img[src*="Harmat22_latvany-3"], img[src*="Harmat22_l%C3%A1tv%C3%A1ny-3"], img[src*="Harmat22_látvány-3"]');
      mediaArea = img ? (img.closest('.elementor-widget-image') || img.parentElement) : null;
      section = img ? (img.closest('section') || mediaArea) : section;
    }
    if (!img) return;

    var mediaLink = img.closest('a');
    if (!mediaLink) {
      mediaLink = document.createElement('a');
      img.parentNode.insertBefore(mediaLink, img);
      mediaLink.appendChild(img);
    }

    mediaLink.classList.add('harmat-virtual-entry-media');
    mediaLink.href = '<?php echo $virtual_entry_url; ?>';
    mediaLink.setAttribute('aria-label', 'Els\u0151 \u00fctem virtu\u00e1lis lak\u00e1sv\u00e1laszt\u00f3 megnyit\u00e1sa');

    if (<?php echo $virtual_embed_enabled ? 'true' : 'false'; ?>) {
      var embed = document.createElement('div');
      embed.className = 'harmat-home-virtual-embed';
      embed.innerHTML = '<iframe title="Harmat Lak\u00f3park virtu\u00e1lis lak\u00e1sv\u00e1laszt\u00f3" src="<?php echo $virtual_embed_url; ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>';
      mediaLink.parentNode.replaceChild(embed, mediaLink);
      section.querySelectorAll('a[href*="/virtualis-lakasvalaszto/"]').forEach(function (link) {
        link.href = '<?php echo $virtual_entry_url; ?>';
      });
      return;
    }

    var preview = '<?php echo $virtual_preview; ?>';
    img.src = preview;
    img.setAttribute('src', preview);
    img.removeAttribute('srcset');
    img.removeAttribute('sizes');
    img.removeAttribute('data-src');
    img.removeAttribute('data-srcset');
    img.removeAttribute('data-lazy-src');
    img.removeAttribute('data-lazy-srcset');
    img.setAttribute('loading', 'lazy');
    img.setAttribute('decoding', 'async');
    img.alt = 'Harmat Lak\u00f3park virtu\u00e1lis lak\u00e1sv\u00e1laszt\u00f3';

    mediaLink.querySelectorAll('.harmat-virtual-phase-overlay, .harmat-virtual-phase-label').forEach(function (node) {
      node.remove();
    });

    section.querySelectorAll('a[href*="/virtualis-lakasvalaszto/"]').forEach(function (link) {
      link.href = '<?php echo $virtual_entry_url; ?>';
    });
  }

  function injectNeighborhoodMap() {
    var section = document.querySelector('body.home .elementor-element-d60b1b2');
    if (!section || section.querySelector('.harmat-about-remake')) return;
    section.insertAdjacentHTML('afterbegin',
      '<div class="harmat-about-remake">' +
        '<div class="harmat-about-grid">' +
          '<div class="harmat-about-copy">' +
            '<div class="harmat-about-eyebrow">••••</div>' +
            '<h2>A Harmat Lak\u00f3park</h2>' +
            '<p>Modern \u00faj \u00e9p\u00edt\u00e9s\u0171 otthonok Budapest X. ker\u00fclet\u00e9ben, z\u00f6ld k\u00f6rnyezetben, mindennapi szolg\u00e1ltat\u00e1sokhoz \u00e9s k\u00f6zleked\u00e9shez k\u00f6zel.</p>' +
            '<ul class="harmat-about-list">' +
              '<li>398 lak\u00e1s</li><li>z\u00f6ld k\u00f6rnyezet</li><li>h\u0151szivatty\u00fas f\u0171t\u00e9s-h\u0171t\u00e9s</li><li>m\u00e9lygar\u00e1zs</li><li>csal\u00e1dbar\u00e1t kialak\u00edt\u00e1s</li><li>\u00d3hegy park k\u00f6zels\u00e9ge</li>' +
            '</ul>' +
          '</div>' +
          '<div class="harmat-about-map" data-zoom="1" aria-label="Harmat Lak\u00f3park k\u00f6rny\u00e9ke">' +
            '<span class="harmat-map-pin harmat-map-pin-main" style="left:52%;top:49%">Harmat Lak\u00f3park</span>' +
            '<span class="harmat-map-pin" style="left:29%;top:62%">Bev\u00e1s\u00e1rl\u00e1s</span>' +
            '<span class="harmat-map-pin" style="left:40%;top:31%">K\u00f6zleked\u00e9s</span>' +
            '<span class="harmat-map-pin" style="left:78%;top:76%">\u00d3hegy park</span>' +
            '<span class="harmat-map-pin" style="left:72%;top:18%">Oktat\u00e1s</span>' +
            '<span class="harmat-map-pin" style="left:18%;top:55%">Eg\u00e9szs\u00e9g\u00fcgy</span>' +
            '<a class="harmat-map-cta" href="<?php echo $maps_url; ?>" target="_blank" rel="noopener">Google t\u00e9rk\u00e9p</a>' +
            '<div class="harmat-map-controls" aria-label="T\u00e9rk\u00e9p nagy\u00edt\u00e1s"><button type="button" data-map-zoom="in">+</button><button type="button" data-map-zoom="out">−</button></div>' +
          '</div>' +
        '</div>' +
        '<div class="harmat-about-stats">' +
          '<div class="harmat-about-stat"><strong>8388<small>m\u00b2</small></strong><span>Alapter\u00fclet az els\u0151 \u00fctemben</span></div>' +
          '<div class="harmat-about-stat"><strong>124<small>db</small></strong><span>Lak\u00e1s az els\u0151 \u00fctemben</span></div>' +
          '<div class="harmat-about-stat"><strong>124<small>db</small></strong><span>M\u00e9lygar\u00e1zs parkol\u00f3</span></div>' +
          '<div class="harmat-about-stat"><strong>92<small>db</small></strong><span>T\u00e1rol\u00f3</span></div>' +
        '</div>' +
      '</div>');
  }

  function injectNeighborhoodMapV2() {
    var section = document.querySelector('body.home .elementor-element-d60b1b2');
    if (!section || section.querySelector('.harmat-about-remake')) return;
    section.insertAdjacentHTML('afterbegin',
      '<div class="harmat-about-remake">' +
        '<div class="harmat-about-grid">' +
          '<div class="harmat-about-copy">' +
            '<div class="harmat-about-eyebrow">Budapest X. ker\u00fclet</div>' +
            '<h2>A Harmat Lak\u00f3park</h2>' +
            '<p>Modern \u00faj \u00e9p\u00edt\u00e9s\u0171 otthonok Budapest X. ker\u00fclet\u00e9ben, z\u00f6ld k\u00f6rnyezetben, mindennapi szolg\u00e1ltat\u00e1sokhoz \u00e9s k\u00f6zleked\u00e9shez k\u00f6zel.</p>' +
            '<div class="harmat-about-meta">' +
              '<div class="harmat-about-meta-item"><span>Tervezett \u00e1tad\u00e1s</span><strong>2028 Q2</strong></div>' +
              '<div class="harmat-about-meta-item"><span>M\u0171szaki tartalom</span><strong>Hamarosan</strong></div>' +
            '</div>' +
            '<ul class="harmat-about-list">' +
              '<li>398 lak\u00e1s</li><li>m\u00e9lygar\u00e1zs</li><li>h\u0151szivatty\u00fas f\u0171t\u00e9s-h\u0171t\u00e9s</li><li>csal\u00e1dbar\u00e1t kialak\u00edt\u00e1s</li>' +
            '</ul>' +
          '</div>' +
          '<div class="harmat-about-visual" aria-label="Harmat Lak\u00f3park l\u00e1tv\u00e1nyterv">' +
            '<img src="/wp-content/uploads/2026/02/Harmat22_latvany-3-1536x864.jpg" alt="Harmat Lak\u00f3park l\u00e1tv\u00e1nyterv" loading="lazy" decoding="async">' +
            '<div class="harmat-visual-badges"><span>24 \u00f3r\u00e1s z\u00e1rt lak\u00f3park</span><span>75% z\u00f6ldfel\u00fclet</span></div>' +
          '</div>' +
        '</div>' +
        '<div class="harmat-about-stats">' +
          '<div class="harmat-about-stat"><strong>8388<small>m\u00b2</small></strong><span>Alapter\u00fclet az els\u0151 \u00fctemben</span></div>' +
          '<div class="harmat-about-stat"><strong>124<small>db</small></strong><span>Lak\u00e1s az els\u0151 \u00fctemben</span></div>' +
          '<div class="harmat-about-stat"><strong>124<small>db</small></strong><span>M\u00e9lygar\u00e1zs parkol\u00f3</span></div>' +
          '<div class="harmat-about-stat"><strong>92<small>db</small></strong><span>T\u00e1rol\u00f3</span></div>' +
        '</div>' +
      '</div>');
  }

  function run() {
    trimGallery();
    polishVirtualSelectorEntry();
    injectNeighborhoodMapV2();
    document.querySelectorAll('body.home .elementor-element-a2d4fc9 .wpcf7-submit').forEach(function (button) {
      if (!button.value) button.value = 'K\u00fcld\u00e9s';
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
  window.addEventListener('load', run);
  setTimeout(run, 800);
  setTimeout(run, 2200);
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-map-zoom]');
    if (!button) return;
    var map = document.querySelector('.harmat-about-map');
    if (!map) return;
    var zoom = parseInt(map.getAttribute('data-zoom') || '1', 10);
    zoom += button.getAttribute('data-map-zoom') === 'in' ? 1 : -1;
    zoom = Math.max(1, Math.min(3, zoom));
    map.setAttribute('data-zoom', String(zoom));
  });
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_perf_home_launch_polish', 24);

function harmat_perf_virtual_selector_test_polish() {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    $path = harmat_perf_request_path();
    if (strpos($path, 'virtualis-lakasvalaszto') !== 0) {
        return;
    }
    ?>
<style id="harmat-virtual-selector-test-style">
  body.harmat-virtual-home-embed {
    margin: 0 !important;
    background: #fff !important;
    overflow: hidden !important;
  }
  body.harmat-virtual-home-embed #wpadminbar,
  body.harmat-virtual-home-embed header,
  body.harmat-virtual-home-embed footer,
  body.harmat-virtual-home-embed .elementor-location-header,
  body.harmat-virtual-home-embed .elementor-location-footer,
  body.harmat-virtual-home-embed .aux-goto-top-btn,
  body.harmat-virtual-home-embed .grecaptcha-badge,
  body.harmat-virtual-home-embed .cookie-notice-container,
  body.harmat-virtual-home-embed #cookie-notice {
    display: none !important;
  }
  body.harmat-virtual-home-embed .lakaspark-app-container {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  body.harmat-virtual-home-embed .lakaspark-main-layout,
  body.harmat-virtual-home-embed .lakaspark-main-layout:not(.list-closed) {
    display: block !important;
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    gap: 0 !important;
  }
  body.harmat-virtual-home-embed .lakaspark-viewer-section {
    width: 100% !important;
    max-width: none !important;
  }
  body.harmat-virtual-home-embed #buildingViewer,
  body.harmat-virtual-home-embed .viewer-container {
    width: 100% !important;
    height: 100vh !important;
    min-height: 0 !important;
    aspect-ratio: auto !important;
  }
  body.harmat-virtual-home-embed .lakaspark-list-section {
    display: none !important;
  }
  body.harmat-virtual-selector-test .lakaspark-app-container {
    max-width: none !important;
  }
  body.harmat-virtual-selector-test .lakaspark-main-layout,
  body.harmat-virtual-selector-test .lakaspark-main-layout:not(.list-closed) {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) !important;
    gap: 18px !important;
    width: min(1180px, calc(100% - 32px)) !important;
    max-width: none !important;
    margin-left: auto !important;
    margin-right: auto !important;
    align-items: start !important;
    overflow: visible !important;
    transform: none !important;
  }
  body.harmat-virtual-selector-test .lakaspark-viewer-section {
    width: 100% !important;
    max-width: none !important;
    min-width: 0 !important;
    flex: none !important;
  }
  body.harmat-virtual-selector-test #buildingViewer,
  body.harmat-virtual-selector-test .viewer-container {
    width: 100% !important;
    max-width: none !important;
    min-height: min(70vh, 680px) !important;
    height: auto !important;
    aspect-ratio: 16 / 9 !important;
  }
  body.harmat-virtual-selector-test .viewer-image,
  body.harmat-virtual-selector-test .viewer-poster {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
  }
  body.harmat-virtual-selector-test .lakaspark-list-section {
    position: relative !important;
    inset: auto !important;
    width: 100% !important;
    max-width: none !important;
    height: auto !important;
    max-height: none !important;
    overflow: visible !important;
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)) !important;
    gap: 14px !important;
    padding: 16px !important;
    background: #fffaf2 !important;
    border: 1px solid rgba(168,116,42,.22) !important;
    box-shadow: 0 20px 50px rgba(37,49,55,.08) !important;
    transform: none !important;
  }
  body.harmat-virtual-selector-test:not(.harmat-virtual-stage-view) .lakaspark-list-section,
  body.harmat-virtual-selector-test .lakaspark-list-section.is-empty {
    display: none !important;
  }
  body.harmat-virtual-selector-test.harmat-virtual-stage-view .lakaspark-app-container[data-toggle="off"] .lakaspark-list-section {
    display: grid !important;
  }
  body.harmat-virtual-selector-test .apt-card {
    min-height: 0 !important;
    margin: 0 !important;
  }
  @media (max-width: 767px) {
    body.harmat-virtual-selector-test .lakaspark-main-layout,
    body.harmat-virtual-selector-test .lakaspark-main-layout:not(.list-closed) {
      width: calc(100% - 18px) !important;
      gap: 12px !important;
    }
    body.harmat-virtual-selector-test #buildingViewer,
    body.harmat-virtual-selector-test .viewer-container {
      min-height: 0 !important;
      aspect-ratio: 4 / 3 !important;
    }
    body.harmat-virtual-selector-test .lakaspark-list-section {
      grid-template-columns: 1fr !important;
      padding: 12px !important;
    }
  }
</style>
<script id="harmat-virtual-selector-test-script">
(function () {
  var params = new URLSearchParams(window.location.search || '');
  var explicitOn = params.get('hm_vtest') === '1';
  var explicitOff = params.get('hm_vtest') === '0';
  var isClonePage = document.body.classList.contains('harmat-virtual-selector-test');
  var path = window.location.pathname.replace(/^\/+|\/+$/g, '');
  var isVirtualPath = path.indexOf('virtualis-lakasvalaszto') === 0;

  if (explicitOff || (path === 'virtualis-lakasvalaszto' && !explicitOn && !isClonePage)) {
    try { sessionStorage.removeItem('harmatVirtualSelectorTest'); } catch (error) {}
  }
  if (explicitOn || isClonePage) {
    try { sessionStorage.setItem('harmatVirtualSelectorTest', '1'); } catch (error) {}
  }
  if (!isVirtualPath) return;

  var stored = false;
  try { stored = sessionStorage.getItem('harmatVirtualSelectorTest') === '1'; } catch (error) {}
  if (explicitOn || isClonePage || stored || (isVirtualPath && path !== 'virtualis-lakasvalaszto')) {
    document.body.classList.add('harmat-virtual-selector-test');
  }
  document.body.classList.toggle(
    'harmat-virtual-stage-view',
    document.body.classList.contains('harmat-virtual-selector-test') &&
      path !== 'virtualis-lakasvalaszto' &&
      path !== 'virtualis-lakasvalaszto-teszt'
  );

  var cardCache = [];
  var nativeCache = [];
  var lockedSelectionCode = '';
  var selectionRendered = false;
  var restoreBusy = false;
  var cardSelector = '.apt-card, .apartment-card, [data-apartment-id]';

  function codeFromText(text) {
    var match = String(text || '').match(/\bA[1-4]-[A-Z0-9]+-L\d+\b/i);
    return match ? match[0].toUpperCase() : '';
  }

  function buildingFromText(text) {
    var code = codeFromText(text);
    if (code) return code.split('-')[0];
    var match = String(text || '').match(/\bA[1-4]\b/i);
    return match ? match[0].toUpperCase() : '';
  }

  function floorFromText(text) {
    var code = codeFromText(text);
    if (!code) return '';
    var parts = code.split('-');
    return parts[1] ? parts[1].toUpperCase() : '';
  }

  function cardsToData(cards) {
    return Array.prototype.map.call(cards || [], function (card) {
      var text = card.textContent || '';
      return {
        html: card.outerHTML,
        text: text,
        code: codeFromText(text),
        building: buildingFromText(text),
        floor: floorFromText(text)
      };
    });
  }

  function refreshCardCache(cards, selectedCode) {
    var nextCache = cardsToData(cards);
    if (!nextCache.length) return;
    var nextCodes = nextCache.map(function (item) { return item.code; }).filter(Boolean).join('|');
    var cacheCodes = cardCache.map(function (item) { return item.code; }).filter(Boolean).join('|');
    var nextHasSelected = selectedCode && nextCache.some(function (item) { return item.code === selectedCode; });
    var cacheHasSelected = selectedCode && cardCache.some(function (item) { return item.code === selectedCode; });
    if (nextCodes === cacheCodes) return;
    if (nextHasSelected && !cacheHasSelected) {
      cardCache = nextCache;
      return;
    }
    if (nextCache.length > cardCache.length || (nextCache.length > 1 && cardCache.length <= 1)) {
      cardCache = nextCache;
    }
  }

  function updateNativeCache(cards) {
    var nextCache = cardsToData(cards);
    if (!nextCache.length) return;
    nativeCache = nextCache;
  }

  function selectedCodeFromViewer() {
    var viewer = document.querySelector('#buildingViewer, .viewer-container, .lakaspark-viewer-section');
    if (!viewer) return '';
    var nodes = Array.prototype.slice.call(viewer.querySelectorAll('*'));
    for (var index = nodes.length - 1; index >= 0; index--) {
      var node = nodes[index];
      var rect = node.getBoundingClientRect();
      var style = window.getComputedStyle(node);
      if (rect.width <= 0 || rect.height <= 0 || style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) continue;
      var text = (node.textContent || '').trim();
      var code = codeFromText(text);
      if (code) return code;
    }
    return '';
  }

  function renderRelatedCards(list, selectedCode) {
    if (restoreBusy || !selectedCode) return;
    var sourceCards = cardCache.length ? cardCache : (nativeCache.length ? nativeCache : cardsToData(list.querySelectorAll(cardSelector)));
    var nextCards = sourceCards.filter(function (item) {
      return item.code === selectedCode;
    });
    if (!nextCards.length) return;

    var currentCodes = Array.prototype.map.call(list.querySelectorAll(cardSelector), function (card) {
      return codeFromText(card.textContent || '');
    }).filter(Boolean).join('|');
    var nextCodes = nextCards.map(function (item) { return item.code; }).filter(Boolean).join('|');
    if (currentCodes === nextCodes) return;

    restoreBusy = true;
    list.innerHTML = nextCards.map(function (item) { return item.html; }).join('');
    list.classList.remove('is-empty');
    selectionRendered = true;
    restoreBusy = false;
  }

  function restoreNativeCards(list) {
    if (restoreBusy || !selectionRendered || !nativeCache.length) return;
    restoreBusy = true;
    list.innerHTML = nativeCache.map(function (item) { return item.html; }).join('');
    list.classList.toggle('is-empty', nativeCache.length === 0);
    selectionRendered = false;
    restoreBusy = false;
  }

  function clearSelection(list) {
    lockedSelectionCode = '';
    document.body.classList.remove('harmat-virtual-has-selection');
    if (list) restoreNativeCards(list);
  }

  function markListState() {
    if (!document.body.classList.contains('harmat-virtual-selector-test')) return;
    var list = document.querySelector('.lakaspark-list-section');
    if (!list) return;
    var cards = list.querySelectorAll(cardSelector);
    if (!lockedSelectionCode) {
      if (!selectionRendered) updateNativeCache(cards);
      restoreNativeCards(list);
      cards = list.querySelectorAll(cardSelector);
      refreshCardCache(cards, '');
      list.classList.toggle('is-empty', cards.length === 0);
      return;
    }
    refreshCardCache(cards, lockedSelectionCode);
    document.body.classList.add('harmat-virtual-has-selection');
    renderRelatedCards(list, lockedSelectionCode);
    cards = list.querySelectorAll(cardSelector);
    list.classList.toggle('is-empty', cards.length === 0);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', markListState);
  } else {
    markListState();
  }
  window.addEventListener('load', markListState);
  setTimeout(markListState, 600);
  setTimeout(markListState, 1800);
  document.addEventListener('click', function (event) {
    var list = document.querySelector('.lakaspark-list-section');
    var viewer = event.target.closest && event.target.closest('#buildingViewer, .viewer-container, .lakaspark-viewer-section');
    var hitbox = event.target.closest && event.target.closest('.hitbox-polygon[data-id], [data-id].hitbox-polygon');
    var control = event.target.closest && event.target.closest('button, a, select, input, textarea, label, .lakaspark-filter, .lakaspark-toolbar, .rotate-btn, .back-btn-modern');
    if (!viewer || control || !hitbox) {
      clearSelection(list);
      setTimeout(markListState, 120);
      setTimeout(markListState, 500);
      return;
    }
    var clickedCode = codeFromText(hitbox.getAttribute('data-id') || '');
    setTimeout(function () {
      var selectedCode = clickedCode || selectedCodeFromViewer();
      if (selectedCode) {
        lockedSelectionCode = selectedCode;
      }
      markListState();
    }, 120);
    setTimeout(markListState, 520);
    setTimeout(function () {
      markListState();
      if (lockedSelectionCode && window.innerWidth <= 767 && list) {
        list.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    }, 1100);
  }, true);
  document.addEventListener('change', function () {
    clearSelection(document.querySelector('.lakaspark-list-section'));
    setTimeout(markListState, 120);
    setTimeout(markListState, 600);
  }, true);
  var observer = new MutationObserver(function () {
    setTimeout(markListState, 50);
  });
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      observer.observe(document.body, { childList: true, subtree: true });
    });
  } else {
    observer.observe(document.body, { childList: true, subtree: true });
  }
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_perf_virtual_selector_test_polish', 35);

function harmat_perf_is_contact_page() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    $path = harmat_perf_request_path();
    return is_page(array('elerhetosegeink', 'kapcsolat')) || in_array($path, array('elerhetosegeink', 'kapcsolat'), true);
}

function harmat_perf_contact_showroom_markup() {
    $base = content_url('/uploads/2026/05/contact-showroom/');
    $photos = array(
        array('file' => 'harmat-showroom-01.jpg', 'alt' => 'Harmat Lakopark ertekesitesi iroda es projektmakett'),
        array('file' => 'harmat-showroom-02.jpg', 'alt' => 'Harmat Lakopark ugyfelter es targyalo'),
        array('file' => 'harmat-showroom-03.jpg', 'alt' => 'Harmat Lakopark ertekesitesi iroda belso ter'),
        array('file' => 'harmat-showroom-04.jpg', 'alt' => 'Harmat Lakopark projektmakett kozelrol'),
        array('file' => 'harmat-showroom-05.jpg', 'alt' => 'Harmat Lakopark bemutatoiroda'),
        array('file' => 'harmat-showroom-06.jpg', 'alt' => 'Harmat Lakopark projektterulet kerites'),
        array('file' => 'harmat-showroom-07.jpg', 'alt' => 'Harmat Lakopark ertekesitesi pont bejarat'),
        array('file' => 'harmat-showroom-08.jpg', 'alt' => 'Harmat Lakopark projektterulet es marketing fal'),
        array('file' => 'harmat-showroom-09.jpg', 'alt' => 'Harmat Lakopark helyszini projektterulet'),
        array('file' => 'harmat-showroom-10.jpg', 'alt' => 'Harmat Lakopark utcai projektbemutato fal'),
        array('file' => 'harmat-showroom-11.jpg', 'alt' => 'Harmat Lakopark helyszini latogatoi utvonal'),
        array('file' => 'harmat-showroom-12.jpg', 'alt' => 'Harmat Lakopark kapu es arculati fal'),
    );
    $maps_url = 'https://www.google.com/maps/search/?api=1&query=1105%20Budapest%2C%20Harmat%20utca%2022';

    ob_start();
    ?>
    <section class="harmat-contact-showroom" aria-labelledby="harmat-contact-title">
        <div class="harmat-contact-shell">
            <div class="harmat-contact-copy">
                <p class="harmat-contact-eyebrow">Harmat Lakópark értékesítés</p>
                <h1 id="harmat-contact-title">Értékesítési iroda és projektbemutató</h1>
                <p class="harmat-contact-lead">Várjuk Önt a Harmat Lakópark értékesítési pontján, ahol személyesen megtekintheti a projekt makettjét, átbeszélheti az elérhető lakásokat és időpontot egyeztethet kollégáinkkal.</p>

                <div class="harmat-contact-info" aria-label="Kapcsolati adatok">
                    <div>
                        <span>Cím</span>
                        <strong>1105 Budapest, Harmat utca 22.</strong>
                    </div>
                    <div>
                        <span>Telefon</span>
                        <strong><a href="tel:+36306410358">+36-30-641-03-58</a></strong>
                    </div>
                    <div>
                        <span>E-mail</span>
                        <strong><a href="mailto:ertekesites@harmat22.hu">ertekesites@harmat22.hu</a></strong>
                    </div>
                    <div>
                        <span>Időpont</span>
                        <strong>előzetes egyeztetés alapján</strong>
                    </div>
                </div>

                <div class="harmat-contact-actions">
                    <a class="harmat-contact-button is-primary" href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener">Útvonalterv Google térképen</a>
                    <a class="harmat-contact-button" href="<?php echo esc_url(home_url('/lakaskereso/')); ?>">Lakáskereső megnyitása</a>
                </div>
            </div>

            <div class="harmat-contact-visual" aria-label="Értékesítési iroda fotók">
                <a class="harmat-contact-main-photo" href="<?php echo esc_url($base . $photos[0]['file']); ?>" target="_blank" rel="noopener">
                    <img src="<?php echo esc_url($base . $photos[0]['file']); ?>" alt="<?php echo esc_attr($photos[0]['alt']); ?>" loading="eager" decoding="async">
                </a>
                <div class="harmat-contact-side-photos">
                    <?php foreach (array_slice($photos, 1, 2) as $photo) : ?>
                        <a href="<?php echo esc_url($base . $photo['file']); ?>" target="_blank" rel="noopener">
                            <img src="<?php echo esc_url($base . $photo['file']); ?>" alt="<?php echo esc_attr($photo['alt']); ?>" loading="lazy" decoding="async">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="harmat-contact-gallery" aria-label="Projektterület és bemutatóiroda képek">
            <?php foreach (array_slice($photos, 3) as $photo) : ?>
                <a href="<?php echo esc_url($base . $photo['file']); ?>" target="_blank" rel="noopener">
                    <img src="<?php echo esc_url($base . $photo['file']); ?>" alt="<?php echo esc_attr($photo['alt']); ?>" loading="lazy" decoding="async">
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function harmat_perf_contact_showroom_content($content) {
    if (!harmat_perf_is_contact_page() || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    return harmat_perf_contact_showroom_markup();
}
add_filter('the_content', 'harmat_perf_contact_showroom_content', 999);

function harmat_perf_contact_showroom_styles() {
    if (!harmat_perf_is_contact_page()) {
        return;
    }
    ?>
    <style id="harmat-contact-showroom-20260526">
        body.page-id-26 .site-content,
        body.page-id-26 #content {
            background: #fbf4e9;
        }
        body.page-id-26 footer .elementor-element-7db0e20,
        body.page-id-26 footer .elementor-element-e21913f {
            display: none !important;
        }
        .harmat-contact-showroom {
            width: min(1220px, calc(100% - 36px));
            margin: 0 auto;
            padding: clamp(86px, 9vw, 132px) 0 clamp(64px, 8vw, 104px);
            color: #263238;
            font-family: Montserrat, Arial, sans-serif;
        }
        .harmat-contact-shell {
            display: grid;
            grid-template-columns: minmax(300px, .82fr) minmax(0, 1.18fr);
            gap: 0;
            border: 1px solid rgba(168, 116, 42, .22);
            background: #fffaf2;
            box-shadow: 0 28px 70px rgba(38, 47, 50, .09);
        }
        .harmat-contact-copy {
            padding: clamp(30px, 4.6vw, 62px);
            background: linear-gradient(135deg, #fffaf2 0%, #fff 100%);
            border-right: 1px solid rgba(168, 116, 42, .18);
        }
        .harmat-contact-eyebrow {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            margin: 0 0 34px;
            padding: 0 16px;
            border: 1px solid rgba(168, 116, 42, .28);
            color: #a8742a;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .18em;
            line-height: 1;
            text-transform: uppercase;
        }
        .harmat-contact-copy h1 {
            margin: 0;
            max-width: 520px;
            color: #1f3037;
            font-family: "Marcellus SC", Georgia, serif;
            font-size: clamp(35px, 4vw, 58px);
            font-weight: 400;
            line-height: .98;
            letter-spacing: 0;
            text-transform: uppercase;
        }
        .harmat-contact-lead {
            margin: 24px 0 0;
            max-width: 540px;
            color: #69747a;
            font-size: 15px;
            line-height: 1.9;
        }
        .harmat-contact-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin: 34px 0 0;
        }
        .harmat-contact-info > div {
            min-height: 78px;
            padding: 14px 16px;
            border: 1px solid rgba(168, 116, 42, .2);
            background: rgba(255, 255, 255, .68);
        }
        .harmat-contact-info span {
            display: block;
            margin: 0 0 8px;
            color: #a8742a;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .harmat-contact-info strong,
        .harmat-contact-info a {
            color: #223037;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.45;
            text-decoration: none;
        }
        .harmat-contact-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 34px;
        }
        .harmat-contact-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 20px;
            border: 1px solid #a8742a;
            color: #a8742a;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .13em;
            text-transform: uppercase;
            text-decoration: none;
            transition: background .2s ease, color .2s ease, transform .2s ease;
        }
        .harmat-contact-button:hover,
        .harmat-contact-button:focus {
            background: #a8742a;
            color: #fff;
            transform: translateY(-1px);
        }
        .harmat-contact-button.is-primary {
            background: #a8742a;
            color: #fff;
        }
        .harmat-contact-button.is-primary:hover,
        .harmat-contact-button.is-primary:focus {
            background: #173f36;
            border-color: #173f36;
        }
        .harmat-contact-visual {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(160px, .65fr);
            gap: 12px;
            padding: 12px;
            background: #17352f;
        }
        .harmat-contact-visual a,
        .harmat-contact-gallery a {
            display: block;
            overflow: hidden;
            background: #e9dfd2;
        }
        .harmat-contact-visual img,
        .harmat-contact-gallery img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scale(1.002);
            transition: transform .35s ease, filter .35s ease;
        }
        .harmat-contact-visual a:hover img,
        .harmat-contact-gallery a:hover img {
            filter: saturate(1.05);
            transform: scale(1.035);
        }
        .harmat-contact-main-photo {
            min-height: 560px;
        }
        .harmat-contact-side-photos {
            display: grid;
            gap: 12px;
            grid-template-rows: 1fr 1fr;
        }
        .harmat-contact-gallery {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 22px;
        }
        .harmat-contact-gallery a {
            aspect-ratio: 4 / 3;
        }
        @media (max-width: 1024px) {
            .harmat-contact-shell {
                grid-template-columns: 1fr;
            }
            .harmat-contact-copy {
                border-right: 0;
                border-bottom: 1px solid rgba(168, 116, 42, .18);
            }
            .harmat-contact-main-photo {
                min-height: 430px;
            }
            .harmat-contact-gallery {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .harmat-contact-showroom {
                width: min(100%, calc(100% - 22px));
                padding-top: 74px;
            }
            .harmat-contact-copy {
                padding: 28px 22px;
            }
            .harmat-contact-copy h1 {
                font-size: 34px;
                line-height: 1.02;
            }
            .harmat-contact-info {
                grid-template-columns: 1fr;
            }
            .harmat-contact-actions {
                display: grid;
            }
            .harmat-contact-visual {
                grid-template-columns: 1fr;
            }
            .harmat-contact-main-photo,
            .harmat-contact-side-photos a {
                min-height: 260px;
            }
            .harmat-contact-side-photos {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: none;
            }
            .harmat-contact-gallery {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .harmat-contact-gallery a {
                aspect-ratio: 16 / 11;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'harmat_perf_contact_showroom_styles', 84);


function harmat_perf_seo_context() {
    if (is_admin() || wp_doing_ajax()) {
        return array();
    }

    $path = harmat_perf_request_path();

    if (is_front_page()) {
        return array(
            'title' => harmat_perf_text('Harmat Lak&oacute;park | &Uacute;j &eacute;p&iacute;t&eacute;s&#369; lak&aacute;sok Budapest X. ker&uuml;let&eacute;ben'),
            'description' => harmat_perf_text('Harmat Lak&oacute;park: &uacute;j &eacute;p&iacute;t&eacute;s&#369; lak&aacute;sok Budapest X. ker&uuml;let&eacute;ben, a Harmat utca 22. alatt. Lak&aacute;skeres&#337;, virtu&aacute;lis lak&aacute;sv&aacute;laszt&oacute;, gal&eacute;ria &eacute;s id&#337;pontfoglal&aacute;s egy helyen.'),
        );
    }

    if (is_page('lakaskereso') || $path === 'lakaskereso') {
        return array(
            'title' => harmat_perf_text('Elad&oacute; &uacute;j lak&aacute;sok Budapest X. ker&uuml;let | Harmat Lak&oacute;park'),
            'description' => harmat_perf_text('Tekintse meg a Harmat Lak&oacute;park el&eacute;rhet&#337; &uacute;j lak&aacute;sait: &eacute;p&uuml;let, emelet, szobasz&aacute;m, alapter&uuml;let, terasz vagy kert &eacute;s aj&aacute;nlatk&eacute;r&eacute;s.'),
        );
    }

    if (is_page('virtualis-lakasvalaszto') || $path === 'virtualis-lakasvalaszto') {
        return array(
            'title' => harmat_perf_text('Virtu&aacute;lis lak&aacute;sv&aacute;laszt&oacute; | Harmat Lak&oacute;park'),
            'description' => harmat_perf_text('V&aacute;lasszon lak&aacute;st a Harmat Lak&oacute;park virtu&aacute;lis lak&aacute;sv&aacute;laszt&oacute;j&aacute;ban, &eacute;s n&eacute;zze meg az el&eacute;rhet&#337; lak&aacute;sokat &eacute;p&uuml;letenk&eacute;nt.'),
        );
    }

    if (is_page('galeria') || $path === 'galeria') {
        return array(
            'title' => harmat_perf_text('Gal&eacute;ria | Harmat Lak&oacute;park l&aacute;tv&aacute;nytervek'),
            'description' => harmat_perf_text('Harmat Lak&oacute;park gal&eacute;ria: k&uuml;ls&#337; &eacute;s bels&#337; l&aacute;tv&aacute;nytervek, k&ouml;rnyezet, kil&aacute;t&aacute;s &eacute;s lak&aacute;sbels&#337;k.'),
        );
    }

    if (is_page('magunkrol') || $path === 'magunkrol') {
        return array(
            'title' => harmat_perf_text('Harmat Lak&oacute;park bemutat&aacute;sa | Budapest X. ker&uuml;let'),
            'description' => harmat_perf_text('Ismerje meg a Harmat Lak&oacute;parkot: &uacute;j &eacute;p&iacute;t&eacute;s&#369; lak&aacute;sok, z&ouml;ld k&ouml;rnyezet, m&eacute;lygar&aacute;zs &eacute;s modern lak&oacute;parki szolg&aacute;ltat&aacute;sok.'),
        );
    }

    if (is_page(array('kapcsolat', 'elerhetosegeink')) || in_array($path, array('kapcsolat', 'elerhetosegeink'), true)) {
        return array(
            'title' => harmat_perf_text('El&eacute;rhet&#337;s&eacute;gek &eacute;s &eacute;rt&eacute;kes&iacute;t&eacute;si iroda | Harmat Lak&oacute;park'),
            'description' => harmat_perf_text('Harmat Lak&oacute;park &eacute;rt&eacute;kes&iacute;t&eacute;si iroda &eacute;s projektbemutat&oacute;. C&iacute;m: 1105 Budapest, Harmat utca 22. Telefon: +36-30-641-03-58.'),
        );
    }

    if (is_singular('property')) {
        $post_id = get_queried_object_id();
        $title = get_the_title($post_id);
        $rooms = get_post_meta($post_id, 'property_rooms', true);
        $area = (float) get_post_meta($post_id, 'property_building_area', true);
        $status = get_post_meta($post_id, 'property_status', true);
        $status_label = $status === 'sold' ? 'Eladva' : ($status === 'reserved' ? 'Foglalva' : 'El&eacute;rhet&#337;');
        $room_text = $rooms ? $rooms . ' szob&aacute;s ' : '';
        $area_text = $area ? number_format_i18n($area, 2) . ' m&sup2; ' : '';

        return array(
            'title' => harmat_perf_text($title . ' lak&aacute;s | ' . $room_text . '&uacute;j lak&aacute;s Budapest X. ker&uuml;let'),
            'description' => harmat_perf_text($title . ' lak&aacute;s a Harmat Lak&oacute;parkban. ' . $area_text . $room_text . 'lak&aacute;s, st&aacute;tusz: ' . $status_label . '. K&eacute;rjen aj&aacute;nlatot az &eacute;rt&eacute;kes&iacute;t&eacute;si csapatt&oacute;l.'),
        );
    }

    return array();
}

function harmat_perf_wpseo_title($title) {
    $context = harmat_perf_seo_context();
    return !empty($context['title']) ? $context['title'] : $title;
}
add_filter('wpseo_title', 'harmat_perf_wpseo_title', 999);
add_filter('wpseo_opengraph_title', 'harmat_perf_wpseo_title', 999);
add_filter('wpseo_twitter_title', 'harmat_perf_wpseo_title', 999);

function harmat_perf_wpseo_description($description) {
    $context = harmat_perf_seo_context();
    return !empty($context['description']) ? $context['description'] : $description;
}
add_filter('wpseo_metadesc', 'harmat_perf_wpseo_description', 999);
add_filter('wpseo_opengraph_desc', 'harmat_perf_wpseo_description', 999);
add_filter('wpseo_twitter_description', 'harmat_perf_wpseo_description', 999);

function harmat_perf_document_title($title) {
    $context = harmat_perf_seo_context();
    return !empty($context['title']) ? $context['title'] : $title;
}
add_filter('pre_get_document_title', 'harmat_perf_document_title', 20);

function harmat_perf_robots($robots) {
    if (harmat_perf_is_private_portal_path()) {
        return 'noindex,nofollow';
    }

    return $robots;
}
add_filter('wpseo_robots', 'harmat_perf_robots', 20);

function harmat_perf_private_noindex_meta() {
    if (harmat_perf_is_private_portal_path()) {
        echo '<meta name="robots" content="noindex,nofollow">' . "\n";
    }
}
add_action('wp_head', 'harmat_perf_private_noindex_meta', 1);

function harmat_perf_home_structured_data() {
    if (is_admin() || !is_front_page()) {
        return;
    }

    $logo = content_url('/uploads/2025/11/Harmat-Logo-250.png');
    $thumbnail = content_url('/uploads/2026/02/Harmat22_latvany-3-1024x576.jpg');
    $video = content_url('/uploads/2026/05/yulu-garden-source-compressed-60m.mp4');
    $data = array(
        '@context' => 'https://schema.org',
        '@graph' => array(
            array(
                '@type' => 'Organization',
                '@id' => home_url('/#organization'),
                'name' => harmat_perf_text('Harmat Lak&oacute;park'),
                'url' => home_url('/'),
                'logo' => $logo,
                'contactPoint' => array(
                    '@type' => 'ContactPoint',
                    'telephone' => '+36-30-641-03-58',
                    'email' => 'ertekesites@harmat22.hu',
                    'contactType' => 'sales',
                    'areaServed' => 'HU',
                    'availableLanguage' => array('hu', 'en', 'zh'),
                ),
            ),
            array(
                '@type' => 'VideoObject',
                '@id' => home_url('/#intro-video'),
                'name' => harmat_perf_text('Harmat Lak&oacute;park bemutat&oacute; vide&oacute;'),
                'description' => harmat_perf_text('A Harmat Lak&oacute;park l&aacute;tv&aacute;nyvide&oacute;ja &eacute;s projektbemutat&oacute;ja Budapest X. ker&uuml;let&eacute;ben.'),
                'thumbnailUrl' => array($thumbnail),
                'contentUrl' => $video,
                'uploadDate' => '2026-05-19',
                'publisher' => array('@id' => home_url('/#organization')),
            ),
        ),
    );

    echo '<script type="application/ld+json" class="harmat-home-seo-schema">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'harmat_perf_home_structured_data', 35);

function harmat_perf_property_structured_data() {
    if (is_admin() || !is_singular('property')) {
        return;
    }

    $post_id = get_queried_object_id();
    $price = (int) get_post_meta($post_id, 'property_price', true);
    $hide_price = get_post_meta($post_id, '_harmat_hide_front_price', true) === 'yes';
    $area = (float) get_post_meta($post_id, 'property_building_area', true);
    $rooms = get_post_meta($post_id, 'property_rooms', true);
    $building = get_post_meta($post_id, 'property_address_street', true);
    $floor = get_post_meta($post_id, 'property_address_street_number', true);
    $unit = get_post_meta($post_id, 'property_address_sub_number', true);
    $status = get_post_meta($post_id, 'property_status', true);
    $availability = $status === 'sold' ? 'https://schema.org/SoldOut' : ($status === 'reserved' ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/InStock');
    $image = get_the_post_thumbnail_url($post_id, 'large');

    $data = array(
        '@context' => 'https://schema.org',
        '@type' => 'Apartment',
        '@id' => get_permalink($post_id) . '#apartment',
        'name' => get_the_title($post_id),
        'url' => get_permalink($post_id),
        'address' => array(
            '@type' => 'PostalAddress',
            'streetAddress' => 'Harmat utca 22.',
            'addressLocality' => 'Budapest',
            'postalCode' => '1105',
            'addressCountry' => 'HU',
        ),
    );

    if ($image) {
        $data['image'] = $image;
    }
    if ($area) {
        $data['floorSize'] = array(
            '@type' => 'QuantitativeValue',
            'value' => $area,
            'unitCode' => 'MTK',
        );
    }
    if ($rooms) {
        $data['numberOfRooms'] = (float) $rooms;
    }
    if ($building || $floor || $unit) {
        $data['additionalProperty'] = array_filter(array(
            $building ? array('@type' => 'PropertyValue', 'name' => 'Epulet', 'value' => $building) : null,
            $floor ? array('@type' => 'PropertyValue', 'name' => 'Emelet', 'value' => $floor) : null,
            $unit ? array('@type' => 'PropertyValue', 'name' => 'Lakas', 'value' => $unit) : null,
        ));
    }
    if (!$hide_price && $price) {
        $data['offers'] = array(
            '@type' => 'Offer',
            'price' => $price,
            'priceCurrency' => 'HUF',
            'availability' => $availability,
            'url' => get_permalink($post_id),
        );
    } else {
        $data['offers'] = array(
            '@type' => 'Offer',
            'availability' => $availability,
            'url' => get_permalink($post_id),
        );
    }

    echo '<script type="application/ld+json" class="harmat-property-seo-schema">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'harmat_perf_property_structured_data', 36);

function harmat_perf_attachment_alt($attr, $attachment) {
    if (!empty($attr['alt']) && strpos($attr['alt'], "\xEF\xBF\xBD") === false) {
        return $attr;
    }

    $title = get_the_title($attachment);
    $attr['alt'] = $title ? harmat_perf_text('Harmat Lak&oacute;park l&aacute;tv&aacute;nyterv - ') . $title : harmat_perf_text('Harmat Lak&oacute;park l&aacute;tv&aacute;nyterv');
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'harmat_perf_attachment_alt', 20, 2);

function harmat_perf_ai_customer_assistant() {
    if (is_admin() || harmat_perf_is_private_portal_path()) {
        return;
    }

    echo '<script id="harmat22-ai-widget" async src="https://jiankong.nogakft.hu/harmat22-ai/widget.js?v=20260526-knowledge-scroll"></script>' . "\n";
    return;

    $finder_url = esc_url(home_url('/lakaskereso/'));
    $virtual_url = esc_url(home_url('/virtualis-lakasvalaszto/'));
    ?>
<style id="harmat-ai-assistant-style">
  .harmat-ai-assistant {
    position: fixed;
    right: clamp(14px, 2vw, 28px);
    bottom: clamp(18px, 2.4vw, 34px);
    z-index: 9992;
    font-family: Montserrat, Arial, sans-serif;
    color: #253137;
  }
  .harmat-ai-toggle {
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid rgba(255,255,255,.7);
    background: linear-gradient(135deg, #12372f 0%, #1d5549 100%);
    color: #253137;
    padding: 10px 15px 10px 10px;
    box-shadow: 0 18px 42px rgba(18,55,47,.28);
    cursor: pointer;
    min-width: 206px;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  }
  .harmat-ai-toggle:hover {
    transform: translateY(-2px);
    border-color: rgba(246,225,190,.9);
    box-shadow: 0 24px 52px rgba(37,49,55,.21);
  }
  .harmat-ai-avatar {
    position: relative;
    width: 50px;
    height: 50px;
    flex: 0 0 50px;
    border-radius: 999px;
    overflow: hidden;
    background: linear-gradient(145deg, #f7dfb5, #fff7ea 52%, #0f4e45 53%);
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px rgba(168,116,42,.32);
  }
  .harmat-ai-avatar:before {
    content: "";
    position: absolute;
    left: 15px;
    top: 9px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #f4c79f;
    box-shadow: 0 20px 0 10px #253137;
  }
  .harmat-ai-avatar:after {
    content: "";
    position: absolute;
    left: 11px;
    top: 7px;
    width: 26px;
    height: 16px;
    border-radius: 20px 20px 8px 8px;
    background: #273137;
  }
  .harmat-ai-copy {
    display: grid;
    gap: 2px;
    text-align: left;
  }
  .harmat-ai-copy strong {
    color: #f5d6a4;
    font-size: 12px;
    line-height: 1.1;
    letter-spacing: .08em;
    text-transform: uppercase;
  }
  .harmat-ai-copy span {
    color: #fffaf2;
    font-size: 13px;
    line-height: 1.3;
    font-weight: 800;
  }
  .harmat-ai-panel {
    position: absolute;
    right: 0;
    bottom: 78px;
    width: min(590px, calc(100vw - 28px));
    background: #fffaf2;
    border: 1px solid rgba(168,116,42,.34);
    box-shadow: 0 28px 80px rgba(37,49,55,.25);
    opacity: 0;
    transform: translateY(12px);
    pointer-events: none;
    transition: opacity .2s ease, transform .2s ease;
  }
  .harmat-ai-assistant.is-open .harmat-ai-panel {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
  }
  .harmat-ai-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px 20px;
    background: #12372f;
    color: #fffaf2;
  }
  .harmat-ai-head .harmat-ai-avatar {
    width: 42px;
    height: 42px;
    flex-basis: 42px;
  }
  .harmat-ai-title {
    flex: 1;
    min-width: 0;
  }
  .harmat-ai-title strong {
    display: block;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 22px;
    line-height: 1.1;
    font-weight: 500;
  }
  .harmat-ai-title span {
    display: block;
    margin-top: 4px;
    color: rgba(255,250,242,.76);
    font-size: 12px;
    line-height: 1.35;
  }
  .harmat-ai-tools {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
  }
  .harmat-ai-lang {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px;
    border: 1px solid rgba(255,255,255,.18);
    background: rgba(255,255,255,.08);
  }
  .harmat-ai-lang button {
    border: 0;
    background: transparent;
    color: rgba(255,250,242,.72);
    min-width: 34px;
    height: 28px;
    padding: 0 8px;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .08em;
    cursor: pointer;
  }
  .harmat-ai-lang button.is-active {
    background: #f4d9aa;
    color: #12372f;
  }
  .harmat-ai-close {
    border: 0;
    background: transparent;
    color: #fffaf2;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
    padding: 2px 0 2px 10px;
  }
  .harmat-ai-body {
    padding: 18px 20px 20px;
  }
  .harmat-ai-chat {
    display: grid;
    gap: 10px;
    max-height: 380px;
    overflow: auto;
    padding-right: 2px;
  }
  .harmat-ai-message {
    width: fit-content;
    max-width: 88%;
    background: #fff;
    border: 1px solid rgba(168,116,42,.16);
    padding: 12px 14px;
    font-size: 13px;
    line-height: 1.55;
    box-shadow: 0 10px 24px rgba(37,49,55,.06);
  }
  .harmat-ai-message.is-user {
    justify-self: end;
    background: #12372f;
    border-color: #12372f;
    color: #fffaf2;
  }
  .harmat-ai-quick {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin: 14px 0 11px;
  }
  .harmat-ai-quick button {
    border: 1px solid rgba(168,116,42,.38);
    background: #fff;
    color: #253137;
    min-height: 34px;
    padding: 7px 10px;
    font-size: 11px;
    line-height: 1.25;
    font-weight: 900;
    letter-spacing: .04em;
    text-transform: uppercase;
    cursor: pointer;
    text-align: center;
  }
  .harmat-ai-quick button:hover {
    border-color: #a8742a;
    color: #a8742a;
  }
  .harmat-ai-input {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    margin-top: 8px;
  }
  .harmat-ai-input input {
    min-width: 0;
    border: 1px solid rgba(168,116,42,.42);
    background: #fff;
    color: #253137;
    min-height: 46px;
    padding: 0 12px;
    font-size: 13px;
  }
  .harmat-ai-input button {
    border: 1px solid #a8742a;
    background: #a8742a;
    color: #fff;
    min-width: 86px;
    padding: 0 14px;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    cursor: pointer;
  }
  .harmat-ai-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 12px;
  }
  .harmat-ai-actions a {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    border: 1px solid rgba(168,116,42,.36);
    background: #fff;
    color: #253137;
    text-decoration: none;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .06em;
    text-transform: uppercase;
  }
  .harmat-ai-actions a:first-child {
    background: #a8742a;
    border-color: #a8742a;
    color: #fff;
  }
  .harmat-ai-actions a:hover {
    border-color: #a8742a;
    color: #a8742a;
  }
  .harmat-ai-actions a:first-child:hover {
    color: #fff;
  }
  .harmat-ai-note {
    margin: 10px 0 0;
    color: #6d767b;
    font-size: 11px;
    line-height: 1.45;
  }
  @media (max-width: 767px) {
    .harmat-ai-assistant {
      right: 12px;
      bottom: 12px;
    }
    .harmat-ai-toggle {
      min-width: 0;
      width: 62px;
      height: 62px;
      padding: 6px;
      border-radius: 999px;
    }
    .harmat-ai-toggle .harmat-ai-copy {
      display: none;
    }
    .harmat-ai-panel {
      right: -2px;
      bottom: 76px;
      max-height: calc(100svh - 104px);
      overflow: auto;
    }
    .harmat-ai-actions {
      grid-template-columns: 1fr;
    }
    .harmat-ai-head {
      align-items: flex-start;
    }
    .harmat-ai-tools {
      flex-direction: column-reverse;
      align-items: flex-end;
      gap: 6px;
    }
  }
</style>
<div class="harmat-ai-assistant" id="harmat-ai-assistant">
  <button class="harmat-ai-toggle" type="button" aria-expanded="false" aria-controls="harmat-ai-panel">
    <span class="harmat-ai-avatar" aria-hidden="true"></span>
    <span class="harmat-ai-copy"><strong>Harmat AI</strong><span data-ai-i18n="toggle">AI &uuml;gyf&eacute;lszolg&aacute;lat</span></span>
  </button>
  <section class="harmat-ai-panel" id="harmat-ai-panel" aria-label="Harmat AI ugyfelszolgalat">
    <div class="harmat-ai-head">
      <span class="harmat-ai-avatar" aria-hidden="true"></span>
      <div class="harmat-ai-title">
        <strong data-ai-i18n="title">AI &uuml;gyf&eacute;lszolg&aacute;lat</strong>
        <span data-ai-i18n="subtitle">Lak&aacute;stan&aacute;csad&oacute; pr&oacute;ba&uuml;zem</span>
      </div>
      <div class="harmat-ai-tools">
        <div class="harmat-ai-lang" aria-label="Nyelv">
          <button type="button" data-ai-lang="hu" class="is-active">HU</button>
          <button type="button" data-ai-lang="en">EN</button>
          <button type="button" data-ai-lang="zh">ZH</button>
        </div>
        <button class="harmat-ai-close" type="button" aria-label="Bezaras">&times;</button>
      </div>
    </div>
    <div class="harmat-ai-body">
      <div class="harmat-ai-chat" data-harmat-ai-chat>
        <div class="harmat-ai-message" data-ai-welcome>
          &Uuml;dv&ouml;zl&ouml;m! Miben seg&iacute;thetek? K&eacute;rdezhet lak&aacute;sr&oacute;l, id&#337;pontr&oacute;l, &aacute;rr&oacute;l vagy &aacute;tad&aacute;sr&oacute;l.
        </div>
      </div>
      <div class="harmat-ai-quick" aria-label="Gyors kerdesek">
        <button type="button" data-ai-answer="available" data-ai-i18n="quickAvailable">El&eacute;rhet&#337; lak&aacute;sok</button>
        <button type="button" data-ai-answer="visit" data-ai-i18n="quickVisit">Id&#337;pont</button>
        <button type="button" data-ai-answer="price" data-ai-i18n="quickPrice">&Aacute;rinform&aacute;ci&oacute;</button>
      </div>
      <div class="harmat-ai-input">
        <input type="text" data-harmat-ai-input placeholder="K&eacute;rdezzen a lak&aacute;sokr&oacute;l">
        <button type="button" data-harmat-ai-send data-ai-i18n="send">K&uuml;ld&eacute;s</button>
      </div>
      <div class="harmat-ai-actions">
        <a href="<?php echo $finder_url; ?>" data-ai-i18n="finder">Lak&aacute;skeres&#337;</a>
        <a href="<?php echo $virtual_url; ?>" data-ai-i18n="virtual">Virtu&aacute;lis v&aacute;laszt&oacute;</a>
      </div>
      <p class="harmat-ai-note" data-ai-i18n="note">K&iacute;s&eacute;rleti funkci&oacute;. A pontos aj&aacute;nlatot az &eacute;rt&eacute;kes&iacute;t&eacute;si csapat er&#337;s&iacute;ti meg.</p>
    </div>
  </section>
</div>
<script id="harmat-ai-assistant-script">
(function () {
  var root = document.getElementById('harmat-ai-assistant');
  if (!root) return;
  var toggle = root.querySelector('.harmat-ai-toggle');
  var close = root.querySelector('.harmat-ai-close');
  var chat = root.querySelector('[data-harmat-ai-chat]');
  var input = root.querySelector('[data-harmat-ai-input]');
  var send = root.querySelector('[data-harmat-ai-send]');
  var lang = 'hu';
  var copy = {
    hu: {
      toggle: 'AI ügyfélszolgálat',
      title: 'AI ügyfélszolgálat',
      subtitle: 'Lakástanácsadó próbaüzem',
      welcome: 'Üdvözöljük! Miben segíthetek? Kérdezhet lakásról, időpontról, árról vagy átadásról.',
      quickAvailable: 'Elérhető lakások',
      quickVisit: 'Időpont',
      quickPrice: 'Árinformáció',
      send: 'Küldés',
      finder: 'Lakáskereső',
      virtual: 'Virtuális választó',
      note: 'Kísérleti funkció. A pontos ajánlatot az értékesítési csapat erősíti meg.',
      placeholder: 'Kérdezzen a lakásokról',
      fallback: 'Köszönöm a kérdést. A próba AI jelenleg lakáskeresésben, ajánlatkérésben, ár-egyeztetésben és időpontfoglalásban segít. Részletes kérdésnél az értékesítési csapat tud pontos választ adni.',
      answers: {
        available: 'A szabad lakásokat a Lakáskereső oldalon találja. Ott épület, emelet, szobaszám és státusz alapján tud szűrni.',
        visit: 'Időpontot az ajánlatkérési űrlapon lehet kérni. Válasszon lakást, majd adja meg nevét, e-mail címét, telefonszámát és a kívánt időpontot.',
        delivery: 'A tervezett átadás jelenleg 2028 Q2. A végleges ütemezést az értékesítési csapat erősíti meg.',
        price: 'Az ár jelenleg egyeztetés alapján érhető el. Válasszon lakást, és kérjen ajánlatot az értékesítési csapattól.'
      }
    },
    en: {
      toggle: 'AI concierge',
      title: 'AI concierge',
      subtitle: 'Apartment advisor beta',
      welcome: 'Welcome. How can I help? You can ask about apartments, appointments, pricing or handover.',
      quickAvailable: 'Available apartments',
      quickVisit: 'Appointment',
      quickPrice: 'Price info',
      send: 'Send',
      finder: 'Apartment finder',
      virtual: 'Virtual selector',
      note: 'Experimental feature. The sales team confirms every final offer.',
      placeholder: 'Ask about apartments',
      fallback: 'Thank you for the question. The beta AI currently helps with apartment search, offers, pricing and appointments. For details, our sales team will confirm the exact answer.',
      answers: {
        available: 'You can browse available apartments in the Apartment Finder and filter by building, floor, rooms and status.',
        visit: 'You can request an appointment through the offer form. Choose an apartment and enter your name, email, phone number and preferred time.',
        delivery: 'The planned handover is currently 2028 Q2. The final schedule is confirmed by the sales team.',
        price: 'Prices are available on request. Choose an apartment and ask our sales team for an offer.'
      }
    },
    zh: {
      toggle: '\u667a\u80fd\u5ba2\u670d',
      title: '\u667a\u80fd\u5ba2\u670d',
      subtitle: '\u516c\u5bd3\u54a8\u8be2\u52a9\u624b\u6d4b\u8bd5\u7248',
      welcome: '\u60a8\u597d\uff0c\u6211\u53ef\u4ee5\u5e2e\u60a8\u67e5\u770b\u623f\u6e90\u3001\u9884\u7ea6\u770b\u623f\u3001\u4ef7\u683c\u548c\u4ea4\u4ed8\u4fe1\u606f\u3002',
      quickAvailable: '\u53ef\u552e\u623f\u6e90',
      quickVisit: '\u9884\u7ea6\u770b\u623f',
      quickPrice: '\u4ef7\u683c\u4fe1\u606f',
      send: '\u53d1\u9001',
      finder: '\u623f\u6e90\u641c\u7d22',
      virtual: '\u865a\u62df\u9009\u623f',
      note: '\u8bd5\u8fd0\u884c\u529f\u80fd\u3002\u6700\u7ec8\u62a5\u4ef7\u548c\u4fe1\u606f\u4ee5\u9500\u552e\u56e2\u961f\u786e\u8ba4\u4e3a\u51c6\u3002',
      placeholder: '\u8bf7\u8f93\u5165\u60a8\u7684\u95ee\u9898',
      fallback: '\u8c22\u8c22\u60a8\u7684\u95ee\u9898\u3002\u76ee\u524d\u8bd5\u7528 AI \u53ef\u4ee5\u534f\u52a9\u623f\u6e90\u67e5\u8be2\u3001\u62a5\u4ef7\u3001\u4ef7\u683c\u548c\u9884\u7ea6\u770b\u623f\u3002\u66f4\u8be6\u7ec6\u7684\u5185\u5bb9\u7531\u9500\u552e\u56e2\u961f\u56de\u590d\u786e\u8ba4\u3002',
      answers: {
        available: '\u60a8\u53ef\u4ee5\u5728\u623f\u6e90\u641c\u7d22\u9875\u9762\u6309\u697c\u680b\u3001\u697c\u5c42\u3001\u623f\u95f4\u6570\u548c\u72b6\u6001\u7b5b\u9009\u53ef\u552e\u623f\u6e90\u3002',
        visit: '\u60a8\u53ef\u4ee5\u901a\u8fc7\u62a5\u4ef7\u8868\u5355\u9884\u7ea6\u770b\u623f\u3002\u8bf7\u9009\u62e9\u623f\u6e90\uff0c\u5e76\u7559\u4e0b\u59d3\u540d\u3001\u90ae\u7bb1\u3001\u7535\u8bdd\u548c\u671f\u671b\u65f6\u95f4\u3002',
        delivery: '\u76ee\u524d\u8ba1\u5212\u4ea4\u4ed8\u65f6\u95f4\u4e3a 2028 \u5e74\u7b2c\u4e8c\u5b63\u5ea6\uff0c\u6700\u7ec8\u65f6\u95f4\u7531\u9500\u552e\u56e2\u961f\u786e\u8ba4\u3002',
        price: '\u4ef7\u683c\u76ee\u524d\u4ee5\u54a8\u8be2\u4e3a\u51c6\u3002\u8bf7\u9009\u62e9\u623f\u6e90\u540e\u5411\u9500\u552e\u56e2\u961f\u83b7\u53d6\u62a5\u4ef7\u3002'
      }
    }
  };

  function text(key) {
    return (copy[lang] && copy[lang][key]) || copy.hu[key] || '';
  }

  function answer(key) {
    var group = copy[lang] && copy[lang].answers ? copy[lang].answers : copy.hu.answers;
    return group[key] || group.available;
  }

  function setLanguage(next) {
    lang = copy[next] ? next : 'hu';
    root.setAttribute('data-ai-lang', lang);
    root.querySelectorAll('[data-ai-lang]').forEach(function (button) {
      button.classList.toggle('is-active', button.getAttribute('data-ai-lang') === lang);
    });
    root.querySelectorAll('[data-ai-i18n]').forEach(function (node) {
      node.textContent = text(node.getAttribute('data-ai-i18n'));
    });
    if (input) {
      input.setAttribute('placeholder', text('placeholder'));
    }
    var welcome = root.querySelector('[data-ai-welcome]');
    if (welcome && chat && chat.children.length === 1) {
      welcome.textContent = text('welcome');
    }
    try {
      window.localStorage.setItem('harmatAiLang', lang);
    } catch (error) {}
  }

  function setOpen(open) {
    root.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open && input) window.setTimeout(function () { input.focus(); }, 80);
  }

  function addMessage(text, user) {
    if (!chat) return;
    var bubble = document.createElement('div');
    bubble.className = 'harmat-ai-message' + (user ? ' is-user' : '');
    bubble.textContent = text;
    chat.appendChild(bubble);
    chat.scrollTop = chat.scrollHeight;
  }

  function reply(text) {
    var value = String(text || '').toLowerCase();
    if (/ar|ár|price|huf|ft|\u4ef7\u683c|\u591a\u5c11\u94b1/.test(value)) return answer('price');
    if (/ido|idő|visit|appointment|foglal|\u9884\u7ea6|\u770b\u623f/.test(value)) return answer('visit');
    if (/atadas|átadás|delivery|handover|2028|\u4ea4\u4ed8/.test(value)) return answer('delivery');
    if (/lakas|lakás|flat|apartment|available|elerheto|elérhető|szoba|\u623f/.test(value)) return answer('available');
    return text('fallback');
  }

  toggle.addEventListener('click', function () {
    setOpen(!root.classList.contains('is-open'));
  });
  close.addEventListener('click', function () {
    setOpen(false);
  });
  root.querySelectorAll('[data-ai-answer]').forEach(function (button) {
    button.addEventListener('click', function () {
      addMessage(button.textContent, true);
      addMessage(answer(button.getAttribute('data-ai-answer')), false);
    });
  });
  root.querySelectorAll('[data-ai-lang]').forEach(function (button) {
    button.addEventListener('click', function () {
      setLanguage(button.getAttribute('data-ai-lang'));
    });
  });
  function submitQuestion() {
    var text = input ? input.value.trim() : '';
    if (!text) return;
    addMessage(text, true);
    window.setTimeout(function () {
      addMessage(reply(text), false);
    }, 180);
    if (input) input.value = '';
  }
  send.addEventListener('click', submitQuestion);
  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') submitQuestion();
  });
  try {
    setLanguage(window.localStorage.getItem('harmatAiLang') || 'hu');
  } catch (error) {
    setLanguage('hu');
  }
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_perf_ai_customer_assistant', 130);

function harmat_perf_lakaskereso_search_patch() {
    if (is_admin() || !is_page('lakaskereso')) {
        return;
    }
    ?>
<style id="harmat-lakaskereso-search-patch-style">
  .hm-lakas-card.hm-smart-hidden {
    display: none !important;
  }
</style>
<script id="harmat-lakaskereso-search-patch">
(function () {
  function normalizeText(value) {
    return String(value || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/\s+/g, " ")
      .trim();
  }
  function normalizeCode(value) {
    return normalizeText(value).replace(/[^a-z0-9]/g, "");
  }
  function queryMatches(card, query) {
    if (!query) return true;

    var queryText = normalizeText(query);
    var queryCode = normalizeCode(query);
    var cardCode = normalizeCode(card.dataset.query || card.innerText || "");
    var building = normalizeText(card.dataset.building || "");
    var floor = normalizeText(card.dataset.floor || "");
    var rooms = normalizeText(card.dataset.rooms || "");
    var roomMatch = queryText.match(/^([1-5])(?:\s*(szoba|szobas|room|rooms))?$/);

    if (roomMatch) {
      return rooms === roomMatch[1];
    }
    if (/^(fsz|fs|foldszint|foldszinti)$/.test(queryText)) {
      return floor === "fsz" || floor === "foldszint";
    }
    if (/^a[1-4]$/.test(queryText)) {
      return building === queryText;
    }
    return !!queryCode && cardCode.indexOf(queryCode) !== -1;
  }
  function selectedValue(toolbar, name) {
    var field = toolbar.querySelector('[data-filter="' + name + '"]');
    return field ? String(field.value || "") : "";
  }
  function applySmartSearch() {
    var toolbar = document.querySelector(".hm-lakas-toolbar");
    var input = toolbar ? toolbar.querySelector('[data-filter="query"]') : null;
    var cards = Array.prototype.slice.call(document.querySelectorAll(".hm-lakas-card"));
    if (!toolbar || !input || !cards.length) return;

    input.setAttribute("placeholder", "pl. A1-F-L1, 3 szoba, Fsz");

    var query = input.value || "";
    var building = selectedValue(toolbar, "building");
    var floor = selectedValue(toolbar, "floor");
    var rooms = selectedValue(toolbar, "rooms");
    var activeStatus = toolbar.querySelector("button.is-active[data-status]");
    var status = activeStatus ? activeStatus.getAttribute("data-status") : "all";
    var visibleCount = 0;

    cards.forEach(function (card) {
      var visible =
        (status === "all" || !status || card.dataset.status === status) &&
        (!building || card.dataset.building === building) &&
        (!floor || card.dataset.floor === floor) &&
        (!rooms || card.dataset.rooms === rooms) &&
        queryMatches(card, query);

      card.classList.remove("is-hidden");
      card.classList.toggle("hm-smart-hidden", !visible);
      if (visible) visibleCount += 1;
    });

    var count = document.querySelector(".hm-lakas-resultbar [data-count]");
    if (count) {
      count.textContent = String(visibleCount);
    }
  }
  function scheduleSmartSearch() {
    window.setTimeout(applySmartSearch, 0);
    window.setTimeout(applySmartSearch, 80);
  }
  document.addEventListener("input", function (event) {
    if (event.target && event.target.closest && event.target.closest(".hm-lakas-toolbar")) {
      scheduleSmartSearch();
    }
  }, true);
  document.addEventListener("change", function (event) {
    if (event.target && event.target.closest && event.target.closest(".hm-lakas-toolbar")) {
      scheduleSmartSearch();
    }
  }, true);
  document.addEventListener("click", function (event) {
    if (event.target && event.target.closest && event.target.closest(".hm-lakas-toolbar")) {
      scheduleSmartSearch();
    }
  }, true);
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", scheduleSmartSearch);
  } else {
    scheduleSmartSearch();
  }
  window.addEventListener("load", scheduleSmartSearch);
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_perf_lakaskereso_search_patch', 150);


function harmat_perf_redirect_duplicate_listing_pages() {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = trim((string) parse_url($path, PHP_URL_PATH), '/');

    if (in_array($path, array('osszes-alaprajz', 'property'), true)) {
        wp_safe_redirect(home_url('/lakaskereso/'), 301);
        exit;
    }

    if ($path === 'ajanlatkeres') {
        wp_safe_redirect(home_url('/lakaskereso/#ajanlatkeres'), 301);
        exit;
    }
}
add_action('template_redirect', 'harmat_perf_redirect_duplicate_listing_pages', 1);
