<?php
/**
 * Plugin Name: Harmat Home Neighborhood Showcase
 * Description: Adds the full neighborhood interactive presentation into the existing homepage environment section.
 * Version: 1.2.1
 */

defined('ABSPATH') || exit;

function harmat_home_neighborhood_showcase_assets() {
    if (is_admin() || !is_front_page()) {
        return;
    }

    wp_enqueue_style(
        'harmat-home-pannellum',
        'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css',
        array(),
        '2.5.6'
    );

    wp_enqueue_script(
        'harmat-home-pannellum',
        'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js',
        array(),
        '2.5.6',
        true
    );

    wp_enqueue_style(
        'harmat-home-map-redesign',
        content_url('/plugins/harmat22-map-redesign/assets/map-redesign.css'),
        array('harmat-home-pannellum'),
        '2.2'
    );
}
add_action('wp_enqueue_scripts', 'harmat_home_neighborhood_showcase_assets', 30);

function harmat_home_neighborhood_showcase_footer() {
    if (is_admin() || !is_front_page()) {
        return;
    }

    $asset_base = trailingslashit(content_url('/plugins/harmat22-map-redesign/assets/harmat-3d/'));
    $neighborhood_url = home_url('/harmat-lakopark-kornyeke/');
    ?>
<style id="harmat-home-neighborhood-showcase-css">
  body.home .elementor-element-f2943fc.harmat-home-neighborhood-section {
    padding-top: clamp(52px, 6vw, 86px) !important;
    padding-bottom: clamp(54px, 6vw, 90px) !important;
    background: #f5f2eb !important;
  }
  body.home .elementor-element-f2943fc.harmat-home-neighborhood-section .elementor-container {
    width: min(1360px, calc(100vw - 36px));
    max-width: 1360px !important;
  }
  body.home .elementor-element-f2943fc .elementor-heading-title a {
    text-decoration: none !important;
  }
  body.home .elementor-element-f2943fc .elementor-shortcode {
    width: 100%;
  }
  body.home .harmat-home-interactive {
    margin-top: 30px;
    border-radius: 10px;
    background: transparent;
  }
  body.home .harmat-home-interactive .hi-wrap {
    width: 100%;
    padding: 0;
  }
  body.home .harmat-home-interactive .hi-head {
    grid-template-columns: 1fr;
    margin-bottom: 24px;
  }
  body.home .harmat-home-interactive .hi-console {
    border-color: rgba(154, 107, 37, .28);
  }
  body.home .harmat-home-interactive .hi-panel-caption {
    border: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    backdrop-filter: none !important;
    padding: 0 !important;
    color: #fff !important;
    text-shadow: 0 2px 14px rgba(0, 0, 0, .52);
  }
  body.home .harmat-home-interactive .hi-panel-caption span {
    color: rgba(255, 255, 255, .88) !important;
  }
  body.home .harmat-home-interactive .hi-feature-image::after {
    content: "Nagy\00EDt\00E1s";
  }
  body.home .harmat-home-interactive .hi-lightbox button {
    font-family: Arial, sans-serif;
  }
  @media (max-width: 640px) {
    body.home .elementor-element-f2943fc.harmat-home-neighborhood-section .elementor-container {
      width: min(100%, calc(100vw - 22px));
    }
    body.home .harmat-home-interactive {
      margin-top: 22px;
    }
    body.home .harmat-home-interactive .hi-screen {
      min-height: 520px;
    }
  }
</style>
<script id="harmat-home-neighborhood-showcase-js">
(function () {
  if (!document.body || !document.body.classList.contains('home')) return;

  var assetBase = <?php echo wp_json_encode($asset_base); ?>;
  var neighborhoodUrl = <?php echo wp_json_encode($neighborhood_url); ?>;

  function asset(file) {
    return assetBase + file;
  }

  function galleryButton(file, label) {
    return '<button type="button" data-full="' + asset(file) + '"><img src="' + asset(file) + '" alt="' + label + '" loading="lazy" decoding="async" fetchpriority="low"><span>' + label + '</span></button>';
  }

  function tabButton(target, label, sub, active) {
    return '<button class="' + (active ? 'active' : '') + '" type="button" data-target="' + target + '">' + label + '<span>' + sub + '</span></button>';
  }

  function buildInteractive() {
    return [
      '<section class="harmat-interactive harmat-home-interactive" id="harmat-home-3d-tour" data-harmat-home-neighborhood="1">',
      '  <div class="hi-wrap">',
      '    <div class="hi-head">',
      '      <div>',
      '        <p class="hi-eyebrow">Interakt&iacute;v bemutat&oacute;</p>',
      '        <h3 class="hi-title">Harmat Lak&oacute;park &eacute;lm&eacute;nyk&ouml;zpont</h3>',
      '      </div>',
      '    </div>',
      '    <div class="hi-console" aria-label="Harmat Lak&oacute;park interakt&iacute;v bemutat&oacute;">',
      '      <div class="hi-screen">',
      '        <div class="hi-panel active" data-panel="panorama">',
      '          <div class="hi-pano-wrap">',
      '            <img class="hi-fallback" src="' + asset('pano_pano_f.jpg') + '" alt="Harmat Lak&oacute;park panor&aacute;ma el&#337;n&eacute;zet" loading="lazy" decoding="async" fetchpriority="low">',
      '            <div id="harmat-panorama" aria-label="Harmat Lak&oacute;park panor&aacute;m&aacute;s l&aacute;tv&aacute;nyt&eacute;r"></div>',
      '          </div>',
      '          <div class="hi-panel-caption"><strong>Panor&aacute;m&aacute;s l&aacute;tv&aacute;nyt&eacute;r</strong><span>H&uacute;zza el a k&eacute;pet, &eacute;s n&eacute;zze k&ouml;rbe a projekt t&eacute;rbeli bemutat&oacute;j&aacute;t.</span></div>',
      '        </div>',
      '        <div class="hi-panel" data-panel="video">',
      '          <div class="hi-video-grid">',
      '            <article class="hi-video-card"><video controls preload="none" playsinline data-poster="' + asset('video_swsp_xmsp.jpg') + '"><source src="' + asset('swsp_xmsp.mp4') + '" type="video/mp4"></video><div><strong>Projektbemutat&oacute;</strong><span>A lak&oacute;park elhelyezked&eacute;se, &eacute;p&uuml;lett&ouml;mege &eacute;s k&ouml;rnyezeti kapcsolatai.</span></div></article>',
      '            <article class="hi-video-card"><video controls preload="none" playsinline data-harmat-youtube-replacement="1" data-poster="' + asset('video_spjs.jpg') + '"></video><div><strong>L&aacute;tv&aacute;nyvide&oacute;</strong><span>&Aacute;tfog&oacute; k&eacute;pet ad a tervezett lak&oacute;k&ouml;rnyezetr&#337;l &eacute;s a projekt hangulat&aacute;r&oacute;l.</span></div></article>',
      '          </div>',
      '        </div>',
      '        <div class="hi-panel" data-panel="plans">',
      '          <div class="hi-split">',
      '            <div class="hi-copy"><small>Projekt &aacute;ttekint&eacute;s</small><h3>Modern lak&oacute;k&ouml;rnyezet K&#337;b&aacute;ny&aacute;n</h3><p>A Harmat Lak&oacute;park a X. ker&uuml;letben, a Harmat utca 22. sz&aacute;m alatt k&iacute;n&aacute;l &uacute;j &eacute;p&iacute;t&eacute;s&#369; otthonokat &aacute;tgondolt alaprajzokkal, z&ouml;ld k&ouml;rnyezettel &eacute;s k&eacute;nyelmes v&aacute;rosi kapcsolatokkal.</p><div class="hi-stat-row"><span><b>124 lak&aacute;s</b>els&#337; &uuml;tem</span><span><b>Harmat utca 22.</b>Budapest X. ker&uuml;let</span><span><b>Z&ouml;ld k&ouml;rnyezet</b>&eacute;lhet&#337; v&aacute;rosi ritmus</span></div></div>',
      '            <button class="hi-feature-image" type="button" data-full="' + asset('xgt_8.jpg') + '"><img src="' + asset('xgt_8.jpg') + '" alt="Harmat Lak&oacute;park mad&aacute;rt&aacute;vlati l&aacute;tv&aacute;nyterv" loading="lazy" decoding="async" fetchpriority="low"></button>',
      '          </div>',
      '        </div>',
      '        <div class="hi-panel" data-panel="gallery">',
      '          <div class="hi-gallery-grid">',
      galleryButton('xgt_0.jpg', '&Aacute;ttekint&#337; l&aacute;tv&aacute;ny'),
      galleryButton('xgt_1.jpg', '&Eacute;p&uuml;lethomlokzat'),
      galleryButton('xgt_4.jpg', 'Lak&oacute;k&ouml;rnyezeti t&eacute;r'),
      galleryButton('xgt_8.jpg', 'Mad&aacute;rt&aacute;vlati n&eacute;zet'),
      galleryButton('xgt_10.jpg', 'Lak&oacute;&eacute;p&uuml;leti r&eacute;szlet'),
      galleryButton('xgt_5.jpg', 'Kert &eacute;s k&ouml;z&ouml;ss&eacute;gi t&eacute;r'),
      '          </div>',
      '        </div>',
      '        <div class="hi-panel" data-panel="location">',
      '          <div class="hi-split">',
      '            <button class="hi-feature-image hi-location-map" type="button" data-full="' + asset('video_swsp_xmsp.jpg') + '"><img src="' + asset('video_swsp_xmsp.jpg') + '" alt="Harmat Lak&oacute;park k&ouml;rnyezeti &aacute;ttekint&#337;" loading="lazy" decoding="async" fetchpriority="low"></button>',
      '            <div class="hi-copy"><small>Elhelyezked&eacute;s</small><h3>Otthon, ahol a v&aacute;ros &eacute;s a term&eacute;szet tal&aacute;lkozik</h3><p>A k&ouml;rny&eacute;k mindennapi &eacute;lethez sz&uuml;ks&eacute;ges szolg&aacute;ltat&aacute;sokat, z&ouml;ldter&uuml;leteket &eacute;s j&oacute; v&aacute;rosi kapcsolatokat k&iacute;n&aacute;l. A bemutat&oacute; seg&iacute;t gyorsan &aacute;tl&aacute;tni a lak&oacute;park k&ouml;rnyezet&eacute;t.</p><ul><li>Budapest X. ker&uuml;let, Harmat utca 22.</li><li>K&ouml;zeli bev&aacute;s&aacute;rl&aacute;si, oktat&aacute;si &eacute;s eg&eacute;szs&eacute;g&uuml;gyi lehet&#337;s&eacute;gek</li><li>K&ouml;nnyen &eacute;rtelmezhet&#337; projekt- &eacute;s k&ouml;rnyezetbemutat&oacute;</li></ul></div>',
      '          </div>',
      '        </div>',
      '        <div class="hi-panel" data-panel="notice">',
      '          <div class="hi-notice"><small>T&aacute;j&eacute;koztat&oacute;</small><h3>Fontos inform&aacute;ci&oacute;k</h3><p>A l&aacute;tv&aacute;nytervek, vide&oacute;k &eacute;s bemutat&oacute;anyagok t&aacute;j&eacute;koztat&oacute; jelleg&#369;ek. Az &aacute;rak, m&#369;szaki tartalom, alapter&uuml;letek, &aacute;tad&aacute;si hat&aacute;rid&#337;k &eacute;s felszerelts&eacute;g minden esetben a hivatalos dokument&aacute;ci&oacute; &eacute;s a szerz&#337;d&eacute;s szerint ir&aacute;nyad&oacute;k.</p><div class="hi-notice-grid"><span>A l&aacute;tv&aacute;nytervek illusztr&aacute;ci&oacute;k</span><span>Az adatok t&aacute;j&eacute;koztat&oacute; jelleg&#369;ek</span><span>A szerz&#337;d&eacute;s az ir&aacute;nyad&oacute;</span></div></div>',
      '        </div>',
      '      </div>',
      '      <div class="hi-dock">',
      '        <div class="hi-tabs" aria-label="Harmat Lak&oacute;park bemutat&oacute; men&uuml;">',
      tabButton('panorama', 'Panor&aacute;ma', '360 n&eacute;zet', true),
      tabButton('video', 'Vide&oacute;k', 'Bemutat&oacute;', false),
      tabButton('plans', 'Projekt', '&Aacute;ttekint&eacute;s', false),
      tabButton('gallery', 'Gal&eacute;ria', 'L&aacute;tv&aacute;nytervek', false),
      tabButton('location', 'K&ouml;rnyezet', 'Lok&aacute;ci&oacute;', false),
      tabButton('notice', 'T&aacute;j&eacute;koztat&oacute;', 'Fontos', false),
      '        </div>',
      '        <div class="hi-actions"><button type="button" data-hi-rotate>Sz&uuml;net</button><button type="button" data-hi-reset>Alaphelyzet</button><button type="button" data-hi-full>Teljes k&eacute;perny&#337;</button></div>',
      '      </div>',
      '    </div>',
      '  </div>',
      '</section>',
      '<div class="hi-lightbox" aria-hidden="true"><button type="button" aria-label="Bez&aacute;r&aacute;s">&times;</button><img alt=""></div>'
    ].join('');
  }

  function initInteractive(root) {
    if (!root || root.dataset.harmatReady === '1') return;
    root.dataset.harmatReady = '1';

    var viewerEl = root.querySelector('#harmat-panorama');
    var fallback = root.querySelector('.hi-fallback');
    var autoRotate = true;
    var startPitch = -27;
    var startYaw = 0;
    var startHfov = 92;
    var viewer = null;

    function ensureViewer() {
      if (viewer || !window.pannellum || !viewerEl) return;
      try {
        viewer = window.pannellum.viewer(viewerEl, {
          type: 'cubemap',
          cubeMap: [
            asset('pano_pano_f.jpg'),
            asset('pano_pano_r.jpg'),
            asset('pano_pano_b.jpg'),
            asset('pano_pano_l.jpg'),
            asset('pano_pano_u.jpg'),
            asset('pano_pano_d.jpg')
          ],
          autoLoad: true,
          showControls: false,
          showFullscreenCtrl: false,
          autoRotate: -1,
          autoRotateInactivityDelay: 2600,
          compass: false,
          hfov: startHfov,
          pitch: startPitch,
          yaw: startYaw,
          minHfov: 44,
          maxHfov: 112
        });
        if (fallback) fallback.style.display = 'none';
      } catch (error) {
        if (fallback) fallback.style.display = 'block';
      }
    }

    function hydrateVideos() {
      root.querySelectorAll('.hi-video-card video[data-poster]').forEach(function (video) {
        video.setAttribute('poster', video.getAttribute('data-poster'));
        video.removeAttribute('data-poster');
      });
    }

    function whenNearViewport(callback) {
      if (!('IntersectionObserver' in window)) {
        window.setTimeout(callback, 900);
        return;
      }
      var done = false;
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!done && (entry.isIntersecting || entry.intersectionRatio > 0)) {
            done = true;
            observer.disconnect();
            callback();
          }
        });
      }, { rootMargin: '420px 0px', threshold: 0.01 });
      observer.observe(root);
    }

    whenNearViewport(ensureViewer);

    var tabs = Array.prototype.slice.call(root.querySelectorAll('.hi-tabs button[data-target]'));
    var panels = Array.prototype.slice.call(root.querySelectorAll('.hi-panel[data-panel]'));

    root.querySelectorAll('.hi-video-card video').forEach(function (video) {
      function getVideoRate() {
        if (video.hasAttribute('data-harmat-youtube-replacement')) return 1;
        var source = video.querySelector('source');
        var src = ((video.currentSrc || video.getAttribute('src') || '') + ' ' + (source ? source.getAttribute('src') : '')).toLowerCase();
        return src ? 0.25 : 1;
      }
      function applyVideoRate() {
        var rate = getVideoRate();
        video.defaultPlaybackRate = rate;
        if (Math.abs(video.playbackRate - rate) > 0.01) {
          video.playbackRate = rate;
        }
      }
      applyVideoRate();
      video.addEventListener('loadedmetadata', applyVideoRate);
      video.addEventListener('play', applyVideoRate);
      video.addEventListener('ratechange', applyVideoRate);
    });

    function showPanel(name) {
      if (name === 'video') {
        hydrateVideos();
      }
      if (name === 'panorama') {
        ensureViewer();
      }
      panels.forEach(function (panel) {
        panel.classList.toggle('active', panel.dataset.panel === name);
      });
      tabs.forEach(function (tab) {
        tab.classList.toggle('active', tab.dataset.target === name);
      });
      root.querySelectorAll('video').forEach(function (video) {
        if (name !== 'video') video.pause();
      });
      if (viewer && name === 'panorama') {
        setTimeout(function () { viewer.resize(); }, 80);
      }
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        showPanel(tab.dataset.target);
      });
    });

    var resetBtn = root.querySelector('[data-hi-reset]');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        ensureViewer();
        showPanel('panorama');
        if (viewer) viewer.lookAt(startPitch, startYaw, startHfov, 900);
      });
    }

    var rotateBtn = root.querySelector('[data-hi-rotate]');
    if (rotateBtn) {
      rotateBtn.addEventListener('click', function () {
        if (!viewer) ensureViewer();
        if (!viewer) return;
        showPanel('panorama');
        autoRotate = !autoRotate;
        if (autoRotate) {
          viewer.startAutoRotate(-1);
          rotateBtn.innerHTML = 'Sz&uuml;net';
        } else {
          viewer.stopAutoRotate();
          rotateBtn.innerHTML = 'Forgat&aacute;s';
        }
      });
    }

    var fullBtn = root.querySelector('[data-hi-full]');
    if (fullBtn) {
      fullBtn.addEventListener('click', function () {
        var el = root.querySelector('.hi-console');
        if (el && el.requestFullscreen) el.requestFullscreen();
      });
    }

    document.addEventListener('fullscreenchange', function () {
      if (viewer) setTimeout(function () { viewer.resize(); }, 120);
    });

    var lightbox = document.querySelector('.hi-lightbox');
    var lightImg = lightbox ? lightbox.querySelector('img') : null;
    root.addEventListener('click', function (event) {
      var btn = event.target.closest('[data-full]');
      if (!btn || !root.contains(btn) || !lightbox || !lightImg) return;
      lightImg.src = btn.dataset.full;
      var img = btn.querySelector('img');
      lightImg.alt = img ? img.alt : '';
      lightbox.classList.add('open');
      lightbox.setAttribute('aria-hidden', 'false');
      event.preventDefault();
    });

    if (lightbox) {
      lightbox.addEventListener('click', function (event) {
        if (event.target === lightbox || event.target.tagName === 'BUTTON') {
          lightbox.classList.remove('open');
          lightbox.setAttribute('aria-hidden', 'true');
          if (lightImg) lightImg.removeAttribute('src');
        }
      });
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          lightbox.classList.remove('open');
          lightbox.setAttribute('aria-hidden', 'true');
          if (lightImg) lightImg.removeAttribute('src');
        }
      });
    }
  }

  function enhanceNeighborhoodSection() {
    var section = document.querySelector('.elementor-element-f2943fc');
    if (!section || section.querySelector('[data-harmat-home-neighborhood="1"]')) {
      return false;
    }

    section.classList.add('harmat-home-neighborhood-section');

    var oldHeading = section.querySelector('.elementor-element-53a286b');
    var oldText = section.querySelector('.elementor-element-bcd3a0f');
    if (oldHeading && oldHeading.parentNode) oldHeading.parentNode.removeChild(oldHeading);
    if (oldText && oldText.parentNode) oldText.parentNode.removeChild(oldText);

    var shortcode = section.querySelector('.elementor-shortcode');
    var host = shortcode || section.querySelector('.elementor-widget-wrap') || section;
    host.innerHTML = buildInteractive();
    initInteractive(host.querySelector('.harmat-home-interactive'));
    return true;
  }

  function run() {
    enhanceNeighborhoodSection();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
  window.addEventListener('load', run);
  setTimeout(run, 700);
  setTimeout(run, 1800);
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_home_neighborhood_showcase_footer', 100);
