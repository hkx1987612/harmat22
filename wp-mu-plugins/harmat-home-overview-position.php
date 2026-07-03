<?php
/**
 * Plugin Name: Harmat Home Overview Position
 * Description: Restores the homepage overview image block to its intended position.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_home_overview_position_footer() {
    if (is_admin() || !is_front_page()) {
        return;
    }

    $preview_url = content_url('/uploads/2026/03/Start/bld-Start-frame-01.webp');
    $selector_url = home_url('/virtualis-lakasvalaszto-elso-utem/');
    ?>
<style id="harmat-home-overview-position-css">
  body.home .elementor-element-9c1e1fe.harmat-home-overview-restored {
    scroll-margin-top: 110px;
  }
</style>
<script id="harmat-home-overview-position-js">
(function () {
  if (!document.body || !document.body.classList.contains('home')) return;

  var previewUrl = <?php echo wp_json_encode($preview_url); ?>;
  var selectorUrl = <?php echo wp_json_encode($selector_url); ?>;

  function restoreOverviewPosition() {
    var overview = document.querySelector('.elementor-element-9c1e1fe');
    var roomEntry = document.querySelector('.elementor-element-05ffbbb');
    if (!overview || !roomEntry || !roomEntry.parentNode) return false;

    if (roomEntry.previousElementSibling !== overview) {
      roomEntry.parentNode.insertBefore(overview, roomEntry);
    }

    overview.classList.add('harmat-home-overview-restored');

    var mediaArea = overview.querySelector('.elementor-element-bd8cb2e') || overview;
    var image = mediaArea.querySelector('img') || overview.querySelector('img');
    if (image && previewUrl) {
      image.src = previewUrl;
      image.setAttribute('src', previewUrl);
      image.removeAttribute('srcset');
      image.removeAttribute('sizes');
      image.removeAttribute('data-src');
      image.removeAttribute('data-srcset');
      image.removeAttribute('data-lazy-src');
      image.removeAttribute('data-lazy-srcset');
      image.setAttribute('loading', 'lazy');
      image.setAttribute('decoding', 'async');
      image.alt = 'Harmat Lak\u00f3park virtu\u00e1lis lak\u00e1sv\u00e1laszt\u00f3';

      var link = image.closest('a');
      if (!link && image.parentNode) {
        link = document.createElement('a');
        image.parentNode.insertBefore(link, image);
        link.appendChild(image);
      }
      if (link) {
        link.classList.add('harmat-virtual-entry-media');
        link.href = selectorUrl;
        link.setAttribute('aria-label', 'Virtu\u00e1lis lak\u00e1sv\u00e1laszt\u00f3 megnyit\u00e1sa');
      }
    }

    overview.querySelectorAll('a[href*="/virtualis-lakasvalaszto"]').forEach(function (link) {
      link.href = selectorUrl;
    });

    return true;
  }

  function run() {
    restoreOverviewPosition();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
  window.addEventListener('load', run);
  setTimeout(run, 600);
  setTimeout(run, 1800);
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_home_overview_position_footer', 99);
