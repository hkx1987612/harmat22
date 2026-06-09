<?php
/**
 * Plugin Name: Harmat Gallery Mobile Polish
 * Description: Fixes the standalone gallery layout on mobile screens.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_gallery_mobile_is_gallery_page() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    $path = isset($_SERVER['REQUEST_URI']) ? trim(parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
    return is_page('galeria') || $path === 'galeria';
}

function harmat_gallery_mobile_body_class($classes) {
    if (harmat_gallery_mobile_is_gallery_page()) {
        $classes[] = 'harmat-gallery-mobile-polished';
    }

    return $classes;
}
add_filter('body_class', 'harmat_gallery_mobile_body_class', 30);

function harmat_gallery_mobile_styles() {
    if (!harmat_gallery_mobile_is_gallery_page()) {
        return;
    }
    ?>
<style id="harmat-gallery-mobile-polish-css">
@media (max-width: 767px) {
  body.harmat-gallery-mobile-polished,
  body.harmat-gallery-mobile-polished #page,
  body.harmat-gallery-mobile-polished .site-content-contain,
  body.harmat-gallery-mobile-polished .site-content,
  body.harmat-gallery-mobile-polished .entry-content,
  body.harmat-gallery-mobile-polished .elementor,
  body.harmat-gallery-mobile-polished .elementor-section,
  body.harmat-gallery-mobile-polished .elementor-container,
  body.harmat-gallery-mobile-polished .elementor-widget-wrap {
    max-width: 100vw !important;
    overflow-x: hidden !important;
  }
  body.harmat-gallery-mobile-polished .elementor-element-4b8fe87 {
    width: min(100%, calc(100vw - 24px)) !important;
    max-width: 100% !important;
    margin: 0 auto !important;
  }
  body.harmat-gallery-mobile-polished .elementor-galerry__filters {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px !important;
    width: min(100%, 330px) !important;
    margin: 0 auto 22px !important;
    padding: 0 !important;
  }
  body.harmat-gallery-mobile-polished .elementor-galerry__filter {
    display: flex !important;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    margin: 0 !important;
    padding: 0 10px !important;
    text-align: center;
    line-height: 1.2;
    white-space: normal;
  }
  body.harmat-gallery-mobile-polished .elementor-opal-image-gallery,
  body.harmat-gallery-mobile-polished .elementor-opal-image-gallery .row,
  body.harmat-gallery-mobile-polished .elementor-opal-image-gallery .grid,
  body.harmat-gallery-mobile-polished .elementor-opal-image-gallery .isotope-grid {
    display: grid !important;
    grid-template-columns: 1fr !important;
    gap: 16px !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    position: relative !important;
    inset: auto !important;
    transform: none !important;
    overflow: visible !important;
  }
  body.harmat-gallery-mobile-polished .elementor-opal-image-gallery .column-item {
    display: block !important;
    float: none !important;
    clear: both !important;
    position: relative !important;
    inset: auto !important;
    left: auto !important;
    right: auto !important;
    top: auto !important;
    bottom: auto !important;
    transform: none !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    height: auto !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  body.harmat-gallery-mobile-polished .elementor-opal-image-gallery .column-item a {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    background: #fff !important;
    border: 1px solid rgba(168, 116, 42, .18) !important;
    box-shadow: 0 14px 34px rgba(38, 47, 50, .08) !important;
  }
  body.harmat-gallery-mobile-polished .elementor-opal-image-gallery .column-item img {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    min-height: 0 !important;
    margin: 0 !important;
    aspect-ratio: 3 / 2;
    object-fit: cover !important;
    object-position: center center !important;
    opacity: 1 !important;
    visibility: visible !important;
  }
  body.harmat-gallery-mobile-polished .elementor-opal-image-gallery .gallery-item-overlay,
  body.harmat-gallery-mobile-polished .elementor-opal-image-gallery .opal-icon-zoom {
    pointer-events: none !important;
  }
}
</style>
    <?php
}
add_action('wp_head', 'harmat_gallery_mobile_styles', 88);

function harmat_gallery_mobile_script() {
    if (!harmat_gallery_mobile_is_gallery_page()) {
        return;
    }
    ?>
<script id="harmat-gallery-mobile-polish-js">
(function () {
  function shouldRun() {
    return window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
  }

  function polishGallery() {
    if (!shouldRun()) return;
    document.body.classList.add('harmat-gallery-mobile-polished');

    document.querySelectorAll('.elementor-opal-image-gallery .isotope-grid, .elementor-opal-image-gallery .grid').forEach(function (grid) {
      grid.style.height = 'auto';
      grid.style.minHeight = '0';
      grid.style.position = 'relative';
      grid.style.transform = 'none';
      grid.style.width = '100%';
      grid.style.maxWidth = '100%';
    });

    document.querySelectorAll('.elementor-opal-image-gallery .column-item').forEach(function (item) {
      item.style.position = 'relative';
      item.style.left = 'auto';
      item.style.top = 'auto';
      item.style.transform = 'none';
      item.style.width = '100%';
      item.style.maxWidth = '100%';
      item.style.height = 'auto';
    });

    document.querySelectorAll('.elementor-opal-image-gallery img').forEach(function (img) {
      img.setAttribute('loading', 'lazy');
      img.setAttribute('decoding', 'async');
      if (!img.getAttribute('alt')) {
        img.setAttribute('alt', 'Harmat Lakópark látványterv');
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', polishGallery);
  } else {
    polishGallery();
  }
  window.addEventListener('load', polishGallery);
  window.addEventListener('resize', polishGallery);
  setTimeout(polishGallery, 600);
  setTimeout(polishGallery, 1600);
  setTimeout(polishGallery, 3200);
}());
</script>
    <?php
}
add_action('wp_footer', 'harmat_gallery_mobile_script', 88);
