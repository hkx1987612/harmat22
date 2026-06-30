<?php
/**
 * Plugin Name: Harmat Offer Form Input Fix
 * Description: Restores pointer access to the lightweight offer modal form fields.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', function () {
    ?>
    <style id="harmat-offer-form-input-fix">
      #harmat-offer-light-modal,
      #harmat-offer-light-modal .harmat-offer-light-box,
      #harmat-offer-light-modal .harmat-offer-light-content {
        pointer-events: none !important;
      }

      #harmat-offer-light-modal .harmat-offer-light-box form,
      #harmat-offer-light-modal .harmat-offer-light-box form *,
      #harmat-offer-light-modal .harmat-offer-light-content form,
      #harmat-offer-light-modal .harmat-offer-light-content form *,
      #harmat-offer-light-modal .harmat-offer-light-content p,
      #harmat-offer-light-modal .harmat-offer-light-content label,
      #harmat-offer-light-modal .harmat-offer-light-content .wpcf7-form-control-wrap,
      #harmat-offer-light-modal .harmat-offer-light-content input,
      #harmat-offer-light-modal .harmat-offer-light-content textarea,
      #harmat-offer-light-modal .harmat-offer-light-content select,
      #harmat-offer-light-modal .harmat-offer-light-content button,
      #harmat-offer-light-modal .harmat-offer-light-content a,
      #harmat-offer-light-modal .harmat-offer-light-close {
        pointer-events: auto !important;
        position: relative;
        z-index: 3;
      }
    </style>
    <?php
}, 1000);

add_action('wp_footer', function () {
    ?>
    <script id="harmat-offer-form-inert-fix">
      (function () {
        function unlockOfferModal() {
          var modal = document.getElementById('harmat-offer-light-modal');
          if (!modal || !modal.classList || !modal.classList.contains('is-open')) return;

          modal.querySelectorAll('[inert]').forEach(function (node) {
            node.removeAttribute('inert');
          });

          modal.querySelectorAll('[aria-hidden="true"]').forEach(function (node) {
            node.setAttribute('aria-hidden', 'false');
          });
        }

        document.addEventListener('click', function () {
          window.setTimeout(unlockOfferModal, 0);
          window.setTimeout(unlockOfferModal, 100);
          window.setTimeout(unlockOfferModal, 500);
        }, true);

        document.addEventListener('focusin', unlockOfferModal, true);

        if (window.MutationObserver) {
          new MutationObserver(unlockOfferModal).observe(document.documentElement, {
            attributes: true,
            childList: true,
            subtree: true,
            attributeFilter: ['class', 'inert', 'aria-hidden']
          });
        }

        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', unlockOfferModal);
        } else {
          unlockOfferModal();
        }
      })();
    </script>
    <?php
}, 1000);
