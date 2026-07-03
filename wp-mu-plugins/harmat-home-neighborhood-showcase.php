<?php
/**
 * Plugin Name: Harmat Home Neighborhood Showcase
 * Description: Adds the neighborhood-page preview into the existing homepage environment section.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_home_neighborhood_showcase_footer() {
    if (is_admin() || !is_front_page()) {
        return;
    }

    $neighborhood_url = home_url('/harmat-lakopark-kornyeke/');
    $apartments_url = home_url('/lakaskereso/');
    ?>
<style id="harmat-home-neighborhood-showcase-css">
  body.home .elementor-element-f2943fc.harmat-home-neighborhood-section {
    padding-top: clamp(52px, 6vw, 86px) !important;
    padding-bottom: clamp(54px, 6vw, 90px) !important;
    background: #f5f2eb !important;
  }
  body.home .elementor-element-f2943fc.harmat-home-neighborhood-section .elementor-container {
    width: min(1160px, calc(100vw - 36px));
    max-width: 1160px !important;
  }
  body.home .elementor-element-f2943fc .elementor-heading-title a {
    text-decoration: none !important;
  }
  body.home .harmat-home-neighborhood-showcase,
  body.home .harmat-home-neighborhood-showcase * {
    box-sizing: border-box;
  }
  body.home .harmat-home-neighborhood-showcase {
    display: grid;
    grid-template-columns: minmax(0, .95fr) minmax(0, 1.25fr);
    gap: 22px;
    width: 100%;
    margin-top: 30px;
    color: #24343a;
    font-family: Montserrat, Arial, sans-serif;
  }
  body.home .harmat-home-neighborhood-panel,
  body.home .harmat-home-neighborhood-map,
  body.home .harmat-home-neighborhood-card {
    min-width: 0;
    border: 1px solid rgba(154, 107, 37, .24);
    border-radius: 8px;
    background: rgba(255, 253, 248, .86);
    box-shadow: 0 16px 38px rgba(38, 49, 55, .08);
  }
  body.home .harmat-home-neighborhood-panel {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 22px;
    padding: clamp(24px, 3vw, 34px);
    border-top: 4px solid #177d69;
  }
  body.home .harmat-home-neighborhood-eyebrow {
    display: inline-flex;
    margin: 0 0 14px;
    color: #946721;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .08em;
    line-height: 1.2;
    text-transform: uppercase;
  }
  body.home .harmat-home-neighborhood-panel h3 {
    margin: 0;
    color: #1f3137;
    font-family: "Marcellus SC", Georgia, serif;
    font-size: clamp(28px, 3.4vw, 42px);
    font-weight: 400;
    line-height: 1.08;
    text-transform: uppercase;
  }
  body.home .harmat-home-neighborhood-panel p {
    margin: 16px 0 0;
    color: #53646a;
    font-size: 15px;
    font-weight: 600;
    line-height: 1.76;
  }
  body.home .harmat-home-neighborhood-facts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 22px;
  }
  body.home .harmat-home-neighborhood-facts span {
    min-width: 0;
    padding: 14px 14px 13px;
    border: 1px solid rgba(23, 125, 105, .16);
    border-radius: 8px;
    background: rgba(23, 125, 105, .065);
  }
  body.home .harmat-home-neighborhood-facts strong,
  body.home .harmat-home-neighborhood-facts small {
    display: block;
  }
  body.home .harmat-home-neighborhood-facts strong {
    color: #14705f;
    font-size: 18px;
    font-weight: 900;
    line-height: 1.15;
  }
  body.home .harmat-home-neighborhood-facts small {
    margin-top: 5px;
    color: #4f5e64;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.35;
  }
  body.home .harmat-home-neighborhood-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 22px;
  }
  body.home .harmat-home-neighborhood-actions a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 18px;
    border: 1px solid #9a6b25;
    border-radius: 6px;
    color: #8f611f !important;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .06em;
    line-height: 1.2;
    text-decoration: none !important;
    text-transform: uppercase;
  }
  body.home .harmat-home-neighborhood-actions a:first-child {
    border-color: #26383d;
    background: #26383d;
    color: #fffdf8 !important;
  }
  body.home .harmat-home-neighborhood-visual {
    display: grid;
    grid-template-rows: minmax(260px, 1fr) auto;
    gap: 14px;
    min-width: 0;
  }
  body.home .harmat-home-neighborhood-map {
    position: relative;
    min-height: 330px;
    overflow: hidden;
    background:
      linear-gradient(135deg, rgba(23, 125, 105, .13), transparent 38%),
      linear-gradient(45deg, transparent 0 18%, rgba(255,255,255,.48) 18% 20%, transparent 20% 45%, rgba(255,255,255,.42) 45% 47%, transparent 47%),
      #dfe8dc;
  }
  body.home .harmat-home-neighborhood-map:before,
  body.home .harmat-home-neighborhood-map:after {
    content: "";
    position: absolute;
    background: rgba(154, 107, 37, .38);
    transform-origin: center;
  }
  body.home .harmat-home-neighborhood-map:before {
    width: 116%;
    height: 12px;
    left: -8%;
    top: 51%;
    transform: rotate(-18deg);
  }
  body.home .harmat-home-neighborhood-map:after {
    width: 12px;
    height: 116%;
    left: 57%;
    top: -8%;
    transform: rotate(24deg);
  }
  body.home .harmat-home-neighborhood-ring {
    position: absolute;
    left: 50%;
    top: 50%;
    border: 1px dashed rgba(23, 125, 105, .45);
    border-radius: 50%;
    transform: translate(-50%, -50%);
  }
  body.home .harmat-home-neighborhood-ring.is-one {
    width: 134px;
    height: 134px;
  }
  body.home .harmat-home-neighborhood-ring.is-two {
    width: 232px;
    height: 232px;
  }
  body.home .harmat-home-neighborhood-ring.is-three {
    width: 330px;
    height: 330px;
  }
  body.home .harmat-home-neighborhood-home,
  body.home .harmat-home-neighborhood-pin {
    position: absolute;
    z-index: 2;
    border-radius: 8px;
    box-shadow: 0 12px 26px rgba(32, 48, 55, .14);
  }
  body.home .harmat-home-neighborhood-home {
    left: 50%;
    top: 50%;
    padding: 13px 15px;
    background: #177d69;
    color: #fff;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .08em;
    line-height: 1.2;
    text-transform: uppercase;
    transform: translate(-50%, -50%);
  }
  body.home .harmat-home-neighborhood-pin {
    min-width: 120px;
    padding: 10px 12px;
    background: rgba(255, 253, 248, .95);
    color: #29383e;
    font-size: 12px;
    font-weight: 900;
    line-height: 1.28;
  }
  body.home .harmat-home-neighborhood-pin strong {
    display: block;
    color: #177d69;
    font-size: 14px;
    line-height: 1.25;
  }
  body.home .harmat-home-neighborhood-pin.is-pet { left: 41%; top: 12%; }
  body.home .harmat-home-neighborhood-pin.is-park { left: 8%; top: 28%; }
  body.home .harmat-home-neighborhood-pin.is-school { right: 9%; top: 20%; }
  body.home .harmat-home-neighborhood-pin.is-transport { left: 44%; bottom: 8%; }
  body.home .harmat-home-neighborhood-card-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
  }
  body.home .harmat-home-neighborhood-card {
    padding: 18px 16px;
  }
  body.home .harmat-home-neighborhood-card strong {
    display: block;
    color: #93651f;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .08em;
    line-height: 1.2;
    text-transform: uppercase;
  }
  body.home .harmat-home-neighborhood-card span {
    display: block;
    margin-top: 10px;
    color: #24343a;
    font-size: 15px;
    font-weight: 900;
    line-height: 1.35;
  }
  body.home .harmat-home-neighborhood-card small {
    display: block;
    margin-top: 8px;
    color: #647378;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.45;
  }
  @media (max-width: 980px) {
    body.home .harmat-home-neighborhood-showcase {
      grid-template-columns: 1fr;
    }
    body.home .harmat-home-neighborhood-visual {
      grid-template-rows: auto auto;
    }
  }
  @media (max-width: 640px) {
    body.home .elementor-element-f2943fc.harmat-home-neighborhood-section .elementor-container {
      width: min(100%, calc(100vw - 22px));
    }
    body.home .harmat-home-neighborhood-showcase {
      gap: 14px;
      margin-top: 22px;
    }
    body.home .harmat-home-neighborhood-panel {
      padding: 23px 20px;
    }
    body.home .harmat-home-neighborhood-panel h3 {
      font-size: 28px;
    }
    body.home .harmat-home-neighborhood-panel p {
      font-size: 14px;
      line-height: 1.68;
    }
    body.home .harmat-home-neighborhood-facts,
    body.home .harmat-home-neighborhood-card-grid {
      grid-template-columns: 1fr;
    }
    body.home .harmat-home-neighborhood-actions {
      display: grid;
      grid-template-columns: 1fr;
    }
    body.home .harmat-home-neighborhood-map {
      min-height: 430px;
    }
    body.home .harmat-home-neighborhood-ring.is-three {
      width: 292px;
      height: 292px;
    }
    body.home .harmat-home-neighborhood-pin {
      min-width: 106px;
      padding: 9px 10px;
      font-size: 11px;
    }
    body.home .harmat-home-neighborhood-pin strong {
      font-size: 13px;
    }
    body.home .harmat-home-neighborhood-pin.is-pet {
      left: 50%;
      top: 7%;
      transform: translateX(-50%);
    }
    body.home .harmat-home-neighborhood-pin.is-park { left: 6%; top: 23%; }
    body.home .harmat-home-neighborhood-pin.is-school { right: 6%; top: 24%; }
    body.home .harmat-home-neighborhood-pin.is-transport {
      left: 50%;
      bottom: 7%;
      transform: translateX(-50%);
    }
  }
</style>
<script id="harmat-home-neighborhood-showcase-js">
(function () {
  if (!document.body || !document.body.classList.contains('home')) return;

  var neighborhoodUrl = <?php echo wp_json_encode($neighborhood_url); ?>;
  var apartmentsUrl = <?php echo wp_json_encode($apartments_url); ?>;

  function buildShowcase() {
    return '' +
      '<div class="harmat-home-neighborhood-showcase" data-harmat-home-neighborhood="1">' +
        '<div class="harmat-home-neighborhood-panel">' +
          '<div>' +
            '<span class="harmat-home-neighborhood-eyebrow">K&ouml;rny&eacute;k&uuml;nk</span>' +
            '<h3>R&ouml;vid utak, val&oacute;di mindennapi el&#337;ny</h3>' +
            '<p>A Harmat utca 22. k&ouml;rny&eacute;ke egyszerre ad z&ouml;ldebb lak&oacute;&eacute;rzetet &eacute;s k&ouml;nnyen haszn&aacute;lhat&oacute; v&aacute;rosi kapcsolatokat. Park, kutyafuttat&oacute;, iskola, bev&aacute;s&aacute;rl&aacute;s &eacute;s k&ouml;zleked&eacute;s a mindennapokhoz k&ouml;zel.</p>' +
            '<div class="harmat-home-neighborhood-facts">' +
              '<span><strong>kb. 200 m</strong><small>k&ouml;zeli kutyafuttat&oacute;</small></span>' +
              '<span><strong>kb. 600 m</strong><small>&Oacute;hegy park</small></span>' +
              '<span><strong>kb. 800 m</strong><small>ker&uuml;leti k&ouml;zpont</small></span>' +
              '<span><strong>kb. 1,2 km</strong><small>K&#337;b&aacute;nya als&oacute;</small></span>' +
            '</div>' +
          '</div>' +
          '<div class="harmat-home-neighborhood-actions">' +
            '<a href="' + neighborhoodUrl + '">K&ouml;rny&eacute;k megtekint&eacute;se</a>' +
            '<a href="' + apartmentsUrl + '">Lak&aacute;sok megtekint&eacute;se</a>' +
          '</div>' +
        '</div>' +
        '<div class="harmat-home-neighborhood-visual">' +
          '<div class="harmat-home-neighborhood-map" role="img" aria-label="Harmat Lak&oacute;park k&ouml;rny&eacute;ki pontok t&eacute;rk&eacute;pes &aacute;ttekint&eacute;se">' +
            '<span class="harmat-home-neighborhood-ring is-one"></span>' +
            '<span class="harmat-home-neighborhood-ring is-two"></span>' +
            '<span class="harmat-home-neighborhood-ring is-three"></span>' +
            '<span class="harmat-home-neighborhood-home">Harmat 22</span>' +
            '<span class="harmat-home-neighborhood-pin is-pet">Kutyafuttat&oacute;<strong>200 m</strong></span>' +
            '<span class="harmat-home-neighborhood-pin is-park">&Oacute;hegy park<strong>600 m</strong></span>' +
            '<span class="harmat-home-neighborhood-pin is-school">Gimn&aacute;zium<strong>700 m</strong></span>' +
            '<span class="harmat-home-neighborhood-pin is-transport">K&#337;b&aacute;nya als&oacute;<strong>1,2 km</strong></span>' +
          '</div>' +
          '<div class="harmat-home-neighborhood-card-grid" aria-label="K&ouml;rny&eacute;ki kiemel&eacute;sek">' +
            '<div class="harmat-home-neighborhood-card"><strong>Z&ouml;ld</strong><span>Park &eacute;s s&eacute;ta</span><small>&Oacute;hegy park, kutyafuttat&oacute; &eacute;s k&ouml;rnyezeti rekre&aacute;ci&oacute;.</small></div>' +
            '<div class="harmat-home-neighborhood-card"><strong>Csal&aacute;d</strong><span>Iskola k&ouml;zel</span><small>Oktat&aacute;si pontok &eacute;s ker&uuml;leti szolg&aacute;ltat&aacute;sok r&ouml;vid t&aacute;vols&aacute;gon bel&uuml;l.</small></div>' +
            '<div class="harmat-home-neighborhood-card"><strong>Kapcsolat</strong><span>V&aacute;rosi el&eacute;r&eacute;s</span><small>K&#337;b&aacute;nya als&oacute;, &Ouml;rs vez&eacute;r tere, K&Ouml;KI &eacute;s nagyobb bev&aacute;s&aacute;rl&oacute;pontok.</small></div>' +
          '</div>' +
        '</div>' +
      '</div>';
  }

  function enhanceNeighborhoodSection() {
    var section = document.querySelector('.elementor-element-f2943fc');
    if (!section || section.querySelector('[data-harmat-home-neighborhood="1"]')) {
      return false;
    }

    section.classList.add('harmat-home-neighborhood-section');

    var titleLink = section.querySelector('.elementor-heading-title a');
    if (titleLink) {
      titleLink.href = neighborhoodUrl;
      titleLink.removeAttribute('target');
      titleLink.setAttribute('aria-label', 'Harmat Lak\u00f3park k\u00f6rny\u00e9k\u00e9nek megnyit\u00e1sa');
    }

    var shortcode = section.querySelector('.elementor-shortcode');
    var host = shortcode || section.querySelector('.elementor-widget-wrap') || section;
    host.innerHTML = buildShowcase();
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
