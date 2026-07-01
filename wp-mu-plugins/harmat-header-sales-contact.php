<?php
/**
 * Plugin Name: Harmat Header Sales Contact
 * Description: Adds the sales contact avatar next to the homepage offer button.
 * Version: 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_header_sales_contact_should_render() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    return is_front_page() || is_home();
}

function harmat_header_sales_contact_assets() {
    if (!harmat_header_sales_contact_should_render()) {
        return;
    }

    $avatar_url = home_url('/wp-content/uploads/2026/06/julia-wirth-sales.jpg');
    ?>
<style id="harmat-header-sales-contact-css">
  body.home .harmat-header-sales-target,
  body.front-page .harmat-header-sales-target {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 10px !important;
    width: auto !important;
    max-width: none !important;
  }
  body.home .harmat-header-sales-target .elementor-button-wrapper,
  body.front-page .harmat-header-sales-target .elementor-button-wrapper {
    flex: 0 0 auto !important;
    width: auto !important;
  }
  body.home #masthead #my-sticky-header.elementor-sticky--active,
  body.home #masthead #my-sticky-header.elementor-sticky--effects,
  body.front-page #masthead #my-sticky-header.elementor-sticky--active,
  body.front-page #masthead #my-sticky-header.elementor-sticky--effects {
    top: 0 !important;
    opacity: 1 !important;
    visibility: visible !important;
    transform: none !important;
    pointer-events: auto !important;
  }
  .harmat-header-sales-contact {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 146px;
    min-width: 146px;
    padding: 4px 0;
    color: #1c3137;
    text-decoration: none !important;
  }
  .harmat-header-sales-contact:hover,
  .harmat-header-sales-contact:focus {
    color: #a87027;
    text-decoration: none !important;
  }
  .harmat-header-sales-avatar {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    border-radius: 50%;
    object-fit: cover;
    object-position: 50% 42%;
    border: 1px solid rgba(168, 112, 39, .28);
    box-shadow: 0 5px 14px rgba(28, 32, 33, .12);
    background: #fffaf3;
  }
  .harmat-header-sales-text {
    display: grid;
    gap: 1px;
    min-width: 0;
    line-height: 1.12;
  }
  .harmat-header-sales-role {
    color: #a87027;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
  }
  .harmat-header-sales-name {
    color: #1c3137;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
  }
  .harmat-header-sales-phone {
    color: #5b6670;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
  }
  @media (max-width: 1160px) {
    .harmat-header-sales-contact {
      width: 128px;
      min-width: 128px;
      gap: 7px;
    }
    .harmat-header-sales-role {
      display: none;
    }
    .harmat-header-sales-avatar {
      width: 34px;
      height: 34px;
      flex-basis: 34px;
    }
    .harmat-header-sales-name {
      font-size: 11px;
    }
    .harmat-header-sales-phone {
      font-size: 9px;
    }
  }
  @media (max-width: 1024px) {
    .harmat-header-sales-contact {
      display: none !important;
    }
  }
</style>
<script id="harmat-header-sales-contact-js">
(function () {
  var avatarUrl = <?php echo wp_json_encode($avatar_url); ?>;
  var phoneHref = 'tel:+36300733375';
  var phoneText = '+36300733375';

  function visible(node) {
    if (!node) return false;
    var rect = node.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  }

  function isHeaderOffer(link) {
    if (!link || !visible(link)) return false;
    var href = link.getAttribute('href') || '';
    var text = (link.textContent || '').toLowerCase();
    var rect = link.getBoundingClientRect();
    return rect.top < 150 && href.indexOf('#opal-contactform-popup') !== -1 && text.indexOf('aj') !== -1;
  }

  function buildContact() {
    var link = document.createElement('a');
    link.className = 'harmat-header-sales-contact';
    link.href = phoneHref;
    link.setAttribute('aria-label', 'Ertekesites: Julia Wirth, ' + phoneText);
    link.innerHTML = '' +
      '<img class="harmat-header-sales-avatar" src="' + avatarUrl + '" alt="J&uacute;lia Wirth" loading="eager" decoding="async">' +
      '<span class="harmat-header-sales-text">' +
        '<span class="harmat-header-sales-role">&Eacute;rt&eacute;kes&iacute;t&eacute;s</span>' +
        '<span class="harmat-header-sales-name">J&uacute;lia Wirth</span>' +
        '<span class="harmat-header-sales-phone">' + phoneText + '</span>' +
      '</span>';
    return link;
  }

  function install() {
    if (document.querySelector('.harmat-header-sales-contact')) return;

    var offer = Array.prototype.slice.call(document.querySelectorAll('a[href*="#opal-contactform-popup"]')).find(isHeaderOffer);
    if (!offer) return;

    var widget = offer.closest('.elementor-widget-button') || offer.closest('.elementor-element') || offer.parentElement;
    var wrapper = offer.closest('.elementor-button-wrapper') || offer.parentElement;
    if (!widget || !wrapper) return;

    widget.classList.add('harmat-header-sales-target');
    widget.insertBefore(buildContact(), wrapper);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', install);
  } else {
    install();
  }
  window.setTimeout(install, 350);
  window.setTimeout(install, 1400);
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_header_sales_contact_assets', 30);
