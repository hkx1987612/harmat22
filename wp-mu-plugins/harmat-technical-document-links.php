<?php
/**
 * Plugin Name: Harmat Technical Document Links
 * Description: Adds the Harmat Lakopark technical specification PDF to public pages.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_technical_document_pdf_url() {
    return home_url('/wp-content/uploads/2026/06/harmat-lakopark-muszaki-leiras.pdf');
}

function harmat_technical_document_is_public_page() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
    if (preg_match('~^(sales|agent|client|customer|ugyfel|lawyer|wp-admin|wp-login\.php|wp-json)(/|$)~i', $path)) {
        return false;
    }

    return true;
}

function harmat_technical_document_footer() {
    if (!harmat_technical_document_is_public_page()) {
        return;
    }

    $pdf_url = harmat_technical_document_pdf_url();
    ?>
<style id="harmat-technical-document-css">
  .harmat-tech-doc-card,
  .harmat-tech-doc-card * {
    box-sizing: border-box;
  }
  .harmat-tech-doc-card {
    width: min(1120px, calc(100% - 32px));
    margin: 18px auto 28px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    border: 1px solid rgba(168, 112, 39, .26);
    border-radius: 8px;
    background: #fffaf3;
    box-shadow: 0 12px 32px rgba(70, 54, 28, .06);
    color: #253137;
    font-family: Montserrat, Arial, sans-serif;
  }
  .harmat-tech-doc-card small {
    display: block;
    margin-bottom: 5px;
    color: #a87027;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .12em;
    text-transform: uppercase;
  }
  .harmat-tech-doc-card strong {
    display: block;
    margin: 0;
    color: #253137;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 24px;
    font-weight: 500;
    line-height: 1.15;
  }
  .harmat-tech-doc-card p {
    margin: 7px 0 0;
    color: #667178;
    font-size: 14px;
    line-height: 1.55;
  }
  .harmat-tech-doc-card a,
  .harmat-tech-doc-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 16px;
    border: 1px solid #a87027;
    border-radius: 999px;
    background: #a87027;
    color: #fff !important;
    font: 900 12px/1 Montserrat, Arial, sans-serif;
    letter-spacing: .03em;
    text-decoration: none !important;
    text-transform: uppercase;
    white-space: nowrap;
  }
  .harmat-property-hero-actions .harmat-tech-doc-link {
    background: #fff;
    color: #987033 !important;
    border-radius: 0;
  }
  @media (max-width: 720px) {
    .harmat-tech-doc-card {
      width: calc(100% - 24px);
      margin: 14px auto 22px;
      padding: 16px;
      display: grid;
    }
    .harmat-tech-doc-card a {
      width: 100%;
    }
  }
</style>
<script id="harmat-technical-document-js">
(function () {
  var pdfUrl = <?php echo wp_json_encode($pdf_url); ?>;
  if (!pdfUrl || !document.body) return;

  var labels = {
    eyebrow: 'Projekt dokumentum',
    title: 'M\u0171szaki le\u00edr\u00e1s',
    body: 'A lak\u00e1sok \u00e9s az \u00e9p\u00fclet f\u0151 m\u0171szaki tartalma PDF form\u00e1tumban. A k\u00f6zleked\u00e9st l\u00e9pcs\u0151h\u00e1zank\u00e9nt 1 db, 630 kg teherb\u00edr\u00e1s\u00fa szem\u00e9lyfelvon\u00f3 seg\u00edti.',
    button: 'PDF megnyit\u00e1sa'
  };

  function makeLink(className, text) {
    var link = document.createElement('a');
    link.className = className || 'harmat-tech-doc-link';
    link.href = pdfUrl;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = text || labels.title;
    link.setAttribute('data-harmat-tech-doc', '1');
    return link;
  }

  function insertAfter(node, newNode) {
    if (!node || !node.parentNode) return false;
    node.parentNode.insertBefore(newNode, node.nextSibling);
    return true;
  }

  function insertPropertyButton() {
    if (!document.body.classList.contains('single-property')) return;
    var actions = document.querySelector('.harmat-property-hero-actions');
    if (!actions || actions.querySelector('[data-harmat-tech-doc]')) return;
    var back = Array.prototype.slice.call(actions.querySelectorAll('a')).find(function (link) {
      return /lakaskereso/i.test(link.getAttribute('href') || '');
    });
    var link = makeLink('harmat-tech-doc-link', labels.title);
    if (back) {
      actions.insertBefore(link, back);
    } else {
      actions.appendChild(link);
    }
  }

  function makeCard() {
    var card = document.createElement('section');
    card.className = 'harmat-tech-doc-card';
    card.setAttribute('data-harmat-tech-doc-card', '1');
    card.innerHTML = '<div><small>' + labels.eyebrow + '</small><strong>' + labels.title + '</strong><p>' + labels.body + '</p></div>';
    card.appendChild(makeLink('', labels.button));
    return card;
  }

  function insertPublicCard() {
    if (document.querySelector('[data-harmat-tech-doc-card]')) return;
    var path = window.location.pathname.replace(/\/+$/, '/');
    var isHome = document.body.classList.contains('home') || path === '/';
    var isSearch = path.indexOf('/lakaskereso/') === 0;
    var isSelector = path.indexOf('/virtualis-lakasvalaszto') === 0;
    if (!isHome && !isSearch && !isSelector) return;

    var target = null;
    if (isHome) {
      target = document.querySelector('.harmat-home-featured') ||
        document.querySelector('.elementor-widget-button a[href*="#opal-contactform-popup"]') ||
        document.querySelector('main .elementor-section, main .e-con');
    } else if (isSearch) {
      target = document.querySelector('.harmat-front-property-filter') ||
        document.querySelector('.hm-lakas-search, .harmat-lakaskereso, main .elementor-section, main .e-con');
    } else {
      target = document.querySelector('main .elementor-section, main .e-con, main');
    }

    if (target) {
      insertAfter(target.closest('.elementor-widget-button') || target, makeCard());
    }
  }

  insertPropertyButton();
  insertPublicCard();
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_technical_document_footer', 118);
