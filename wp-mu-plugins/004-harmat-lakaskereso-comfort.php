<?php
/**
 * Plugin Name: Harmat Lakaskereso Comfort
 * Description: Adds progressive card display to the public apartment search page.
 * Version: 2026.07.04.1
 */

defined('ABSPATH') || exit;

function harmat_lakaskereso_comfort_is_page() {
    if (is_admin() || wp_doing_ajax() || wp_is_json_request() || is_feed() || is_robots()) {
        return false;
    }

    $path = isset($_SERVER['REQUEST_URI']) ? trim((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
    return is_page('lakaskereso') || $path === 'lakaskereso';
}

function harmat_lakaskereso_comfort_body_class($classes) {
    if (harmat_lakaskereso_comfort_is_page()) {
        $classes[] = 'harmat-lakaskereso-comfort';
    }

    return $classes;
}
add_filter('body_class', 'harmat_lakaskereso_comfort_body_class', 99);

function harmat_lakaskereso_comfort_styles() {
    if (!harmat_lakaskereso_comfort_is_page()) {
        return;
    }
    ?>
<style id="harmat-lakaskereso-comfort-css">
body.harmat-lakaskereso-comfort .hm-lakas-card.hm-lakas-page-hidden {
  display: none !important;
}
body.harmat-lakaskereso-comfort .hm-lakas-progress {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin: 26px 0 0;
  padding: 18px;
  border: 1px solid rgba(168,118,45,.18);
  background: #fffdf8;
  color: #687078;
  font-family: Montserrat, Arial, sans-serif;
  font-size: 13px;
  font-weight: 800;
  text-align: center;
}
body.harmat-lakaskereso-comfort .hm-lakas-progress[hidden] {
  display: none !important;
}
body.harmat-lakaskereso-comfort .hm-lakas-progress strong {
  color: #253137;
  font-weight: 900;
}
body.harmat-lakaskereso-comfort .hm-lakas-progress button {
  min-height: 42px;
  padding: 0 22px;
  border: 1px solid #a8762d;
  background: #a8762d;
  color: #fff;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: .1em;
  text-transform: uppercase;
  cursor: pointer;
}
body.harmat-lakaskereso-comfort .hm-lakas-progress button:focus {
  outline: 3px solid rgba(168,118,45,.24);
  outline-offset: 2px;
}
@media (max-width: 680px) {
  body.harmat-lakaskereso-comfort .hm-lakas-progress {
    margin-top: 20px;
    padding: 16px 12px;
  }
  body.harmat-lakaskereso-comfort .hm-lakas-progress button {
    width: 100%;
  }
}
</style>
    <?php
}
add_action('wp_head', 'harmat_lakaskereso_comfort_styles', 9999);

function harmat_lakaskereso_comfort_script() {
    if (!harmat_lakaskereso_comfort_is_page()) {
        return;
    }
    ?>
<script id="harmat-lakaskereso-comfort-js">
(function () {
  var INITIAL_LIMIT = 24;
  var STEP = 24;
  var limit = INITIAL_LIMIT;
  var timer = null;
  var secondaryTimer = null;

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function visibleBase(card) {
    return !card.classList.contains('is-hidden') && !card.classList.contains('hm-smart-hidden');
  }

  function makeProgress(grid) {
    var existing = document.querySelector('[data-hm-lakas-progress]');
    if (existing) {
      return existing;
    }

    var progress = document.createElement('div');
    progress.className = 'hm-lakas-progress';
    progress.setAttribute('data-hm-lakas-progress', '1');
    progress.setAttribute('aria-live', 'polite');
    progress.hidden = true;
    progress.innerHTML = '<span data-hm-lakas-progress-text></span><button type="button" data-hm-lakas-more>Tov\u00e1bbi lak\u00e1sok</button>';
    grid.insertAdjacentElement('afterend', progress);
    return progress;
  }

  function init() {
    var page = document.querySelector('[data-hm-lakas-page]');
    if (!page) return;

    var grid = page.querySelector('.hm-lakas-grid');
    var toolbar = page.querySelector('.hm-lakas-toolbar');
    if (!grid || !toolbar) return;

    var progress = makeProgress(grid);
    var progressText = progress.querySelector('[data-hm-lakas-progress-text]');
    var moreButton = progress.querySelector('[data-hm-lakas-more]');

    function cards() {
      return Array.prototype.slice.call(grid.querySelectorAll('.hm-lakas-card'));
    }

    function applyProgress() {
      var allCards = cards();
      var visibleCards = [];

      allCards.forEach(function (card) {
        card.classList.remove('hm-lakas-page-hidden');
      });

      allCards.forEach(function (card) {
        if (visibleBase(card)) {
          visibleCards.push(card);
        }
      });

      visibleCards.forEach(function (card, index) {
        card.classList.toggle('hm-lakas-page-hidden', index >= limit);
      });

      var shown = Math.min(limit, visibleCards.length);
      if (visibleCards.length > shown) {
        progress.hidden = false;
        if (progressText) {
          progressText.innerHTML = '<strong>' + shown + ' / ' + visibleCards.length + '</strong> lak\u00e1s megjelen\u00edtve';
        }
      } else {
        progress.hidden = true;
      }

      document.body.classList.add('hm-lakas-comfort-ready');
    }

    function schedule(reset) {
      if (reset) {
        limit = INITIAL_LIMIT;
      }
      window.clearTimeout(timer);
      window.clearTimeout(secondaryTimer);
      timer = window.setTimeout(applyProgress, 160);
      secondaryTimer = window.setTimeout(applyProgress, 380);
    }

    if (moreButton) {
      moreButton.addEventListener('click', function () {
        limit += STEP;
        applyProgress();
      });
    }

    toolbar.addEventListener('input', function () {
      schedule(true);
    }, true);
    toolbar.addEventListener('change', function () {
      schedule(true);
    }, true);
    toolbar.addEventListener('click', function (event) {
      if (event.target && event.target.closest('[data-status], [data-reset]')) {
        schedule(true);
      }
    }, true);

    if (window.MutationObserver) {
      new MutationObserver(function () {
        schedule(false);
      }).observe(grid, { childList: true });
    }

    schedule(false);
    window.addEventListener('load', function () {
      schedule(false);
    });
  }

  ready(init);
}());
</script>
    <?php
}
add_action('wp_footer', 'harmat_lakaskereso_comfort_script', 9999);
