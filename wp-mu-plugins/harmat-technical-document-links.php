<?php
/**
 * Plugin Name: Harmat Technical Document Links
 * Description: Adds a compact technical specification PDF link to property floorplan blocks only.
 * Version: 1.1.0
 */

defined('ABSPATH') || exit;

function harmat_technical_document_pdf_url() {
    return home_url('/wp-content/uploads/2026/06/harmat-lakopark-muszaki-leiras.pdf');
}

function harmat_technical_document_should_render() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
    if (preg_match('~^(sales|agent|client|customer|ugyfel|lawyer|wp-admin|wp-login\.php|wp-json|virtualis-lakasvalaszto)(/|$)~i', $path)) {
        return false;
    }

    return is_singular('property');
}

function harmat_technical_document_footer() {
    if (!harmat_technical_document_should_render()) {
        return;
    }

    $pdf_url = harmat_technical_document_pdf_url();
    ?>
<style id="harmat-technical-document-css">
  .harmat-property-detail-media-head .harmat-tech-doc-inline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    margin-left: auto;
    padding: 0 12px;
    border: 1px solid rgba(152, 112, 51, .42);
    border-radius: 999px;
    background: #fff;
    color: #987033 !important;
    font-family: Montserrat, Arial, sans-serif;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .04em;
    line-height: 1;
    text-decoration: none !important;
    text-transform: uppercase;
    white-space: nowrap;
    flex: 0 0 auto;
    box-shadow: 0 6px 16px rgba(40, 34, 24, .06);
  }
  .harmat-property-detail-media-head .harmat-tech-doc-inline:hover,
  .harmat-property-detail-media-head .harmat-tech-doc-inline:focus-visible {
    background: #987033;
    color: #fff !important;
  }
  @media (max-width: 620px) {
    .harmat-property-detail-media-head .harmat-tech-doc-inline {
      width: auto;
      margin: 10px 0 0;
    }
  }
</style>
<script id="harmat-technical-document-js">
(function () {
  var pdfUrl = <?php echo wp_json_encode($pdf_url); ?>;
  if (!pdfUrl || !document.body || !document.body.classList.contains('single-property')) return;

  var label = 'M\u0171szaki PDF';
  var title = 'M\u0171szaki le\u00edr\u00e1s PDF let\u00f6lt\u00e9se';

  function insertLink() {
    var head = document.querySelector('.harmat-property-detail-media-head');
    if (!head || head.querySelector('[data-harmat-tech-doc]')) return;

    var link = document.createElement('a');
    link.className = 'harmat-tech-doc-inline';
    link.href = pdfUrl;
    link.target = '_blank';
    link.rel = 'noopener';
    link.download = 'harmat-lakopark-muszaki-leiras.pdf';
    link.textContent = label;
    link.title = title;
    link.setAttribute('aria-label', title);
    link.setAttribute('data-harmat-tech-doc', '1');
    head.appendChild(link);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', insertLink);
  } else {
    insertLink();
  }
  window.addEventListener('load', insertLink, { once: true });
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_technical_document_footer', 118);
