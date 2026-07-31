<?php
/**
 * Plugin Name: Harmat Unified Offer Modal
 * Description: Single public offer modal and CRM submission flow for Harmat Lakopark.
 * Version: 1.0.7
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_unified_offer_modal_is_public_page() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = trim((string) parse_url($request_uri, PHP_URL_PATH), '/');

    if (preg_match('~^(sales|agent|client|customer|ugyfel|lawyer|wp-admin|wp-login\.php|wp-json)(/|$)~i', $path)) {
        return false;
    }

    return true;
}

function harmat_unified_offer_modal_head() {
    if (!harmat_unified_offer_modal_is_public_page()) {
        return;
    }
    ?>
<script id="harmat-unified-offer-gate">
(function () {
  window.harmatOfferLightModalReady = true;
  window.harmatOfferLightModalDisabled = true;

  function normalize(value) {
    value = String(value || '').toLowerCase();
    try {
      value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    } catch (error) {}
    return value;
  }

  function isOfferTrigger(node) {
    if (!node || !node.matches || !node.matches('a, button, [role="button"]')) return false;
    var href = node.getAttribute('href') || node.getAttribute('data-mfp-src') || '';
    var text = normalize(node.textContent || '');
    if (href.indexOf('#opal-contactform-popup') !== -1) return true;
    if (href.indexOf('/property/') !== -1 && (text.indexOf('ajanlat') !== -1 || text.indexOf('arajanlat') !== -1)) return true;
    if ((text.indexOf('ajanlatot kerek') !== -1 || text.indexOf('arajanlatot kerek') !== -1 || text.indexOf('ajanlatkeres') !== -1) && node.closest('.hm-lakas-card, .apt-card, .apartment-card, .property, article, .elementor-widget-container')) return true;
    return false;
  }

  document.addEventListener('click', function (event) {
    var node = event.target && event.target.closest ? event.target.closest('a, button, [role="button"]') : null;
    if (!isOfferTrigger(node) || typeof window.harmatUnifiedOfferOpen !== 'function') return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    window.harmatUnifiedOfferOpen(node);
  }, true);
})();
</script>
    <?php
}
add_action('wp_head', 'harmat_unified_offer_modal_head', -1000);

function harmat_unified_offer_modal_footer() {
    if (!harmat_unified_offer_modal_is_public_page()) {
        return;
    }

    $endpoint = rest_url('harmat-sales-manager/v1/offer');
    $nonce = wp_create_nonce('harmat_public_offer');
    $thank_you_url = home_url('/koszonjuk/');
    $privacy_url = home_url('/adatvedelmi-tajekoztato/');
    ?>
<style id="harmat-unified-offer-modal-css">
  #harmat-offer-light-modal,
  .contactform-content[id^="opal-contactform-popup"],
  .contactform-content[id*="opal-contactform-popup"] {
    display: none !important;
  }
  .h22-offer-modal,
  .h22-offer-modal * {
    box-sizing: border-box;
  }
  .h22-offer-modal[hidden] {
    display: none !important;
  }
  .h22-offer-modal {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: grid;
    place-items: center;
    padding: 22px;
    background: rgba(29, 35, 39, .66);
  }
  .h22-offer-dialog {
    position: relative;
    width: min(760px, calc(100vw - 32px));
    max-height: calc(100vh - 44px);
    overflow: auto;
    padding: 34px 38px 38px;
    background: #fffaf3;
    border: 1px solid #d8b77c;
    border-radius: 10px;
    box-shadow: 0 24px 70px rgba(29, 35, 39, .24);
    color: #253137;
    font-family: Montserrat, Arial, sans-serif;
  }
  .h22-offer-close {
    position: absolute;
    top: 10px;
    right: 12px;
    width: 38px;
    height: 38px;
    border: 0;
    background: transparent;
    color: #2f383b;
    font-size: 34px;
    line-height: 1;
    cursor: pointer;
  }
  .h22-offer-title {
    margin: 0 0 8px;
    color: #a87027;
    font-family: "Marcellus SC", Georgia, serif;
    font-size: 30px;
    font-weight: 400;
    line-height: 1.2;
    text-align: center;
    text-transform: uppercase;
  }
  .h22-offer-subtitle {
    margin: 0 auto 20px;
    max-width: 560px;
    color: #5b6770;
    font-size: 13px;
    line-height: 1.45;
    text-align: center;
  }
  .h22-offer-summary {
    display: none;
    margin: 0 0 18px;
    padding: 12px 14px;
    border: 1px solid #ead8b8;
    border-radius: 8px;
    background: #fff;
    color: #253137;
    font-size: 13px;
    line-height: 1.45;
  }
  .h22-offer-summary.is-visible {
    display: block;
  }
  .h22-offer-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 14px;
  }
  .h22-offer-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .h22-offer-field.is-wide {
    grid-column: 1 / -1;
  }
  .h22-offer-field label,
  .h22-offer-check {
    color: #44515a;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.35;
  }
  .h22-offer-field input,
  .h22-offer-field select,
  .h22-offer-field textarea {
    width: 100%;
    min-height: 44px;
    border: 1px solid #dec18f;
    border-radius: 6px;
    background: #fff;
    color: #17242a;
    font: 600 13px/1.35 Montserrat, Arial, sans-serif;
    padding: 11px 12px;
    outline: none;
  }
  .h22-offer-field textarea {
    min-height: 96px;
    resize: vertical;
  }
  .h22-offer-field input:focus,
  .h22-offer-field select:focus,
  .h22-offer-field textarea:focus {
    border-color: #a87027;
    box-shadow: 0 0 0 3px rgba(168, 112, 39, .14);
  }
  .h22-offer-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 14px;
  }
  .h22-offer-row.is-single {
    grid-template-columns: 1fr;
  }
  .h22-offer-source {
    min-width: 0;
    margin: 0 0 14px;
    padding: 0;
    border: 0;
  }
  .h22-offer-source legend {
    margin-bottom: 7px;
    color: #44515a;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.35;
  }
  .h22-offer-source-options {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
  }
  .h22-offer-source-option {
    position: relative;
    min-width: 0;
    cursor: pointer;
  }
  .h22-offer-source-option input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
  }
  .h22-offer-source-option span {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 9px 10px;
    border: 1px solid #dec18f;
    border-radius: 6px;
    background: #fff;
    color: #35434b;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.3;
    text-align: center;
  }
  .h22-offer-source-option input:checked + span {
    border-color: #a87027;
    background: #fff6e8;
    color: #805018;
    box-shadow: inset 0 0 0 1px #a87027;
  }
  .h22-offer-source-option input:focus-visible + span {
    outline: 3px solid rgba(168, 112, 39, .18);
    outline-offset: 2px;
  }
  .h22-offer-privacy {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 4px 0 14px;
    color: #4a5660;
    font-size: 12px;
    line-height: 1.5;
  }
  .h22-offer-privacy input {
    width: 16px;
    height: 16px;
    margin-top: 2px;
    accent-color: #a87027;
  }
  .h22-offer-privacy a {
    color: #a87027;
    font-weight: 700;
    text-decoration: underline;
  }
  .h22-offer-actions {
    display: flex;
    justify-content: center;
    margin-top: 8px;
  }
  .h22-offer-submit {
    min-width: 210px;
    min-height: 48px;
    border: 0;
    border-radius: 999px;
    background: #a87027;
    color: #fff;
    font: 800 13px/1 Montserrat, Arial, sans-serif;
    letter-spacing: .04em;
    text-transform: uppercase;
    cursor: pointer;
    box-shadow: 0 10px 20px rgba(168, 112, 39, .2);
  }
  .h22-offer-submit:disabled {
    cursor: progress;
    opacity: .72;
  }
  .h22-offer-status {
    min-height: 22px;
    margin-top: 12px;
    color: #c0392b;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.45;
    text-align: center;
  }
  .h22-offer-status.is-success {
    color: #0f7a4a;
  }
  .h22-offer-honey {
    position: absolute !important;
    left: -10000px !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
  }
  @media (max-width: 720px) {
    .h22-offer-modal {
      padding: 12px;
    }
    .h22-offer-dialog {
      width: min(100%, calc(100vw - 18px));
      max-height: calc(100vh - 18px);
      padding: 28px 18px 24px;
      border-radius: 8px;
    }
    .h22-offer-title {
      font-size: 24px;
    }
    .h22-offer-grid,
    .h22-offer-row {
      grid-template-columns: 1fr;
      gap: 10px;
    }
    .h22-offer-source-options {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
</style>
<div class="h22-offer-modal" id="h22-offer-modal" hidden aria-hidden="true">
  <div class="h22-offer-dialog" role="dialog" aria-modal="true" aria-labelledby="h22-offer-title">
    <button class="h22-offer-close" type="button" data-h22-offer-close aria-label="Bez&aacute;r&aacute;s">&times;</button>
    <h2 class="h22-offer-title" id="h22-offer-title">Aj&aacute;nlatk&eacute;r&eacute;s</h2>
    <p class="h22-offer-subtitle">T&ouml;ltse ki az adatokat, &eacute;s &eacute;rt&eacute;kes&iacute;t&eacute;si munkat&aacute;rsunk hamarosan felveszi &Ouml;nnel a kapcsolatot.</p>
    <div class="h22-offer-summary" data-h22-summary></div>
    <form class="h22-offer-form" id="h22-offer-form" novalidate>
      <div class="h22-offer-grid">
        <div class="h22-offer-field">
          <label for="h22-offer-building">&Eacute;p&uuml;let</label>
          <select id="h22-offer-building" name="selected-building" data-h22-building></select>
        </div>
        <div class="h22-offer-field">
          <label for="h22-offer-floor">Emelet</label>
          <select id="h22-offer-floor" name="selected-floor" data-h22-floor></select>
        </div>
        <div class="h22-offer-field">
          <label for="h22-offer-apartment">Lak&aacute;s</label>
          <select id="h22-offer-apartment" name="selected-apartment" data-h22-apartment></select>
        </div>
      </div>
      <div class="h22-offer-row">
        <div class="h22-offer-field">
          <label for="h22-offer-name">N&eacute;v *</label>
          <input id="h22-offer-name" name="your-name" type="text" autocomplete="name" required>
        </div>
        <div class="h22-offer-field">
          <label for="h22-offer-email">E-mail c&iacute;m *</label>
          <input id="h22-offer-email" name="your-email" type="email" autocomplete="email" required>
        </div>
      </div>
      <div class="h22-offer-row">
        <div class="h22-offer-field">
          <label for="h22-offer-phone">Telefonsz&aacute;m *</label>
          <input id="h22-offer-phone" name="your-phone" type="tel" autocomplete="tel" required>
        </div>
        <div class="h22-offer-field">
          <label for="h22-offer-date">K&iacute;v&aacute;nt d&aacute;tum *</label>
          <input id="h22-offer-date" name="your-date" type="date" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" required>
        </div>
      </div>
      <div class="h22-offer-row is-single">
        <div class="h22-offer-field">
          <label for="h22-offer-time">Id&#337;s&aacute;v</label>
          <select id="h22-offer-time" name="your-time">
            <option value="">Id&#337;s&aacute;v</option>
            <option value="09:00-12:00">09:00-12:00</option>
            <option value="12:00-15:00">12:00-15:00</option>
            <option value="15:00-18:00">15:00-18:00</option>
          </select>
        </div>
      </div>
      <fieldset class="h22-offer-source">
        <legend>Honnan hallott r&oacute;lunk?</legend>
        <div class="h22-offer-source-options">
          <label class="h22-offer-source-option"><input type="radio" name="lead_source" value="K&uuml;lt&eacute;ri hirdet&eacute;s"><span>K&uuml;lt&eacute;ri hirdet&eacute;s</span></label>
          <label class="h22-offer-source-option"><input type="radio" name="lead_source" value="Google keres&eacute;s"><span>Google keres&eacute;s</span></label>
          <label class="h22-offer-source-option"><input type="radio" name="lead_source" value="ingatlan.com"><span>ingatlan.com</span></label>
          <label class="h22-offer-source-option"><input type="radio" name="lead_source" value="K&ouml;z&ouml;ss&eacute;gi m&eacute;dia"><span>K&ouml;z&ouml;ss&eacute;gi m&eacute;dia</span></label>
          <label class="h22-offer-source-option"><input type="radio" name="lead_source" value="Egy&eacute;b"><span>Egy&eacute;b</span></label>
        </div>
      </fieldset>
      <div class="h22-offer-field is-wide">
        <label for="h22-offer-message">&Uuml;zenet</label>
        <textarea id="h22-offer-message" name="your-message"></textarea>
      </div>
      <label class="h22-offer-privacy">
        <input type="checkbox" name="privacy-acceptance" value="1" required>
        <span>Elfogadom az <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener">adatkezel&eacute;si t&aacute;j&eacute;koztat&oacute;t</a>.</span>
      </label>
      <input type="hidden" name="_wpcf7" value="1002">
      <input type="hidden" name="_harmat_offer_nonce" value="<?php echo esc_attr($nonce); ?>">
      <input type="hidden" name="harmat_company_url" value="">
      <input type="hidden" name="selected-area" value="">
      <input type="hidden" name="selected-rooms" value="">
      <input type="hidden" name="selected-price" value="">
      <input type="hidden" name="selected-url" value="">
      <input type="hidden" name="source_url" value="">
      <input type="hidden" name="landing_page" value="">
      <input type="hidden" name="referrer" value="">
      <input type="hidden" name="utm_source" value="">
      <input type="hidden" name="utm_medium" value="">
      <input type="hidden" name="utm_campaign" value="">
      <input type="hidden" name="utm_content" value="">
      <input type="hidden" name="utm_term" value="">
      <div class="h22-offer-actions">
        <button class="h22-offer-submit" type="submit">K&uuml;ld&eacute;s</button>
      </div>
      <div class="h22-offer-status" data-h22-status></div>
    </form>
  </div>
</div>
<script id="harmat-unified-offer-modal-js">
(function () {
  if (window.harmatUnifiedOfferReady) return;
  window.harmatUnifiedOfferReady = true;
  window.harmatOfferLightModalReady = true;
  window.harmatOfferLightModalDisabled = true;

  var endpoint = <?php echo wp_json_encode($endpoint); ?>;
  var thankYouUrl = <?php echo wp_json_encode($thank_you_url); ?>;
  var modal = document.getElementById('h22-offer-modal');
  var form = document.getElementById('h22-offer-form');
  if (!modal || !form) return;

  var fields = {
    building: form.querySelector('[data-h22-building]'),
    floor: form.querySelector('[data-h22-floor]'),
    apartment: form.querySelector('[data-h22-apartment]'),
    summary: modal.querySelector('[data-h22-summary]'),
    status: modal.querySelector('[data-h22-status]'),
    submit: form.querySelector('.h22-offer-submit')
  };

  var TXT = {
    building: '\u00c9p\u00fclet',
    floor: 'Emelet',
    apartment: 'Lak\u00e1s',
    sending: 'K\u00fcld\u00e9s...',
    send: 'K\u00fcld\u00e9s',
    priceOnRequest: '\u00c1r egyeztet\u00e9s alapj\u00e1n',
    required: 'K\u00e9rj\u00fck, t\u00f6ltse ki a nevet, e-mail c\u00edmet, d\u00e1tumot, telefonsz\u00e1mot, \u00e9s fogadja el az adatkezel\u00e9si t\u00e1j\u00e9koztat\u00f3t.',
    invalidEmail: 'K\u00e9rj\u00fck, adjon meg egy \u00e9rv\u00e9nyes e-mail c\u00edmet.',
    invalidDate: 'K\u00e9rj\u00fck, v\u00e1lasszon mai vagy k\u00e9s\u0151bbi d\u00e1tumot.',
    success: 'K\u00f6sz\u00f6nj\u00fck, megkaptuk \u00e9rdekl\u0151d\u00e9s\u00e9t.',
    failed: 'A k\u00fcld\u00e9s nem siker\u00fclt. K\u00e9rj\u00fck, pr\u00f3b\u00e1lja \u00fajra.',
    messagePrefix: 'A ',
    messageSuffix: ' lak\u00e1s ir\u00e1nt \u00e9rdekl\u0151d\u00f6m.'
  };

  function removeLegacyPopups() {
    Array.prototype.slice.call(document.querySelectorAll('#harmat-offer-light-modal, .contactform-content[id^="opal-contactform-popup"], .contactform-content[id*="opal-contactform-popup"]')).forEach(function (node) {
      if (node && !node.closest('#h22-offer-modal') && node.parentNode) {
        node.parentNode.removeChild(node);
      }
    });
  }

  function normalize(value) {
    value = String(value || '').trim();
    try {
      value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    } catch (error) {}
    return value;
  }

  function unique(values) {
    var seen = {};
    return values.map(function (value) { return String(value || '').trim(); }).filter(function (value) {
      if (!value || seen[value]) return false;
      seen[value] = true;
      return true;
    }).sort(function (a, b) {
      return a.localeCompare(b, 'hu', { numeric: true, sensitivity: 'base' });
    });
  }

  function pathOf(url) {
    try {
      return new URL(url, window.location.href).pathname.replace(/\/+$/, '/');
    } catch (error) {
      return '';
    }
  }

  function slugFromHref(url) {
    var path = pathOf(url);
    var match = path.match(/\/property\/([^\/?#]+)/i);
    return match ? match[1] : '';
  }

  function codeFromSlug(url) {
    var slug = slugFromHref(url);
    return slug ? slug.toUpperCase().replace(/-/g, '-') : '';
  }

  function codeFromText(text) {
    var match = String(text || '').toUpperCase().match(/\bA[0-9]+-[A-Z0-9]+-L[0-9]+\b/);
    return match ? match[0] : '';
  }

  function itemCode(item) {
    return String(item && (item.title || item.code || item.apartment || item.apartment_number || item.number || item.name || '') || '').trim();
  }

  function itemUrl(item) {
    return String(item && (item.url || item.permalink || item.link || item.property_url || '') || '').trim();
  }

  function itemBuilding(item) {
    var value = item && (item.building || item.epulet || item.house || item.block || '');
    if (value) return String(value).trim();
    var code = itemCode(item);
    var match = code.match(/^(A[0-9]+)/i);
    return match ? match[1].toUpperCase() : '';
  }

  function itemFloor(item) {
    var value = item && (item.floor || item.emelet || item.level || '');
    if (value !== undefined && value !== null && String(value).trim() !== '') return String(value).trim();
    var code = itemCode(item);
    var match = code.match(/^A[0-9]+-([A-Z0-9]+)-L[0-9]+$/i);
    return match ? match[1].toUpperCase() : '';
  }

  function itemArea(item) {
    var value = item && (item.salesArea || item.sales_area || item.area || item.size || '');
    if (value === undefined || value === null || value === '') return '';
    if (typeof value === 'number') return String(value).replace('.', ',') + ' m\u00b2';
    return String(value);
  }

  function itemRooms(item) {
    var rooms = item && (item.rooms || item.room_count || item.szoba || '');
    var bedrooms = item && (item.bedrooms || item.bedroom_count || '');
    if (rooms && bedrooms) return rooms + ' szoba / ' + bedrooms + ' h\u00e1l\u00f3';
    if (rooms) return rooms + ' szoba';
    return '';
  }

  function money(value) {
    if (value === undefined || value === null || value === '' || value === false) return '';
    if (typeof value === 'string' && value.match(/[A-Za-z\u00c0-\u017f]/)) return value;
    var num = Number(String(value).replace(/[^0-9.-]/g, ''));
    if (!isFinite(num) || num <= 0) return '';
    return Math.round(num).toLocaleString('hu-HU') + ' Ft';
  }

  function itemPrice(item) {
    if (!item) return '';
    if (item.hidePrice || item.price_hidden || item.hiddenPrice) return TXT.priceOnRequest;
    return item.priceLabel || item.price_label || item.totalPriceLabel || money(item.price || item.totalPrice || item.total_price || item.brutto || '');
  }

  function itemIsAvailable(item) {
    var status = normalize(item && (item.status || item.sale_status || item.availability || item.statusLabel || '')).toLowerCase();
    if (!status) return true;
    return status === 'current' || status === 'available' || status === 'elerheto' || status === 'elado';
  }

  function collectItems() {
    var pools = [
      window.harmatUnifiedSalesData && window.harmatUnifiedSalesData.items,
      window.harmatUnifiedSalesData && window.harmatUnifiedSalesData.apartments,
      window.harmatSalesFront && window.harmatSalesFront.items,
      window.harmatSalesFront && window.harmatSalesFront.apartments,
      window.harmatOfferApartments
    ];
    var found = [];
    var seen = {};

    pools.forEach(function (pool) {
      if (!pool) return;
      if (!Array.isArray(pool)) pool = Object.keys(pool).map(function (key) { return pool[key]; });
      pool.forEach(function (item) {
        if (!itemIsAvailable(item)) return;
        var code = itemCode(item);
        if (!code || seen[code]) return;
        seen[code] = true;
        found.push(item);
      });
    });

    return found;
  }

  var items = collectItems();

  function addOption(select, value, label) {
    if (!select) return;
    var option = document.createElement('option');
    option.value = value;
    option.textContent = label || value;
    select.appendChild(option);
  }

  function populateSelects() {
    if (!fields.building || !fields.floor || !fields.apartment) return;
    fields.building.innerHTML = '';
    fields.floor.innerHTML = '';
    fields.apartment.innerHTML = '';
    addOption(fields.building, '', TXT.building);
    addOption(fields.floor, '', TXT.floor);
    addOption(fields.apartment, '', TXT.apartment);
    unique(items.map(itemBuilding)).forEach(function (value) { addOption(fields.building, value, value); });
    unique(items.map(itemFloor)).forEach(function (value) { addOption(fields.floor, value, value); });
    unique(items.map(itemCode)).forEach(function (value) { addOption(fields.apartment, value, value); });
  }

  function findItemByCode(code) {
    code = String(code || '').trim().toUpperCase();
    if (!code) return null;
    return items.find(function (item) { return itemCode(item).toUpperCase() === code; }) || null;
  }

  function findItemByUrl(url) {
    var wanted = pathOf(url);
    if (!wanted) return null;
    return items.find(function (item) { return pathOf(itemUrl(item)) === wanted; }) || null;
  }

  function currentPageItem() {
    var slugCode = codeFromSlug(window.location.href);
    var titleCode = codeFromText(document.title);
    return findItemByUrl(window.location.href) || findItemByCode(slugCode) || findItemByCode(titleCode) || null;
  }

  function triggerItem(trigger) {
    if (!trigger) return currentPageItem();
    var holder = trigger.closest('[data-harmat-item-id], [data-apartment-id], [data-id], .hm-lakas-card, .apt-card, .apartment-card, .harmat-front-card, .property, article');
    if (holder && holder.dataset) {
      var holderCode = codeFromText(holder.dataset.apartmentId || holder.dataset.id || holder.dataset.harmatTitle || holder.textContent || '');
      var byHolder = findItemByCode(holderCode);
      if (byHolder) return byHolder;
    }
    var href = trigger.getAttribute('href') || '';
    var hrefCode = codeFromSlug(href);
    var textCode = codeFromText((trigger.textContent || '') + ' ' + (holder ? holder.textContent || '' : ''));
    var exactItem = findItemByUrl(href) || findItemByCode(hrefCode) || findItemByCode(textCode);
    if (exactItem) return exactItem;
    if (hrefCode && href.indexOf('/property/') !== -1) {
      return {
        title: hrefCode,
        url: href.split('#')[0]
      };
    }
    return currentPageItem();
  }

  function setField(name, value) {
    var field = form.querySelector('[name="' + name + '"]');
    if (field) field.value = value || '';
  }

  function selectValue(select, value) {
    if (!select) return;
    value = String(value || '');
    var has = Array.prototype.some.call(select.options, function (option) { return option.value === value; });
    if (value && !has) addOption(select, value, value);
    select.value = value;
  }

  function setSummary(item) {
    if (!fields.summary) return;
    var parts = [];
    if (itemCode(item)) parts.push(itemCode(item));
    if (itemArea(item)) parts.push(itemArea(item));
    if (itemRooms(item)) parts.push(itemRooms(item));
    if (itemPrice(item)) parts.push(itemPrice(item));
    fields.summary.textContent = parts.join(' | ');
    fields.summary.classList.toggle('is-visible', parts.length > 0);
  }

  function applyItem(item) {
    var code = itemCode(item);
    selectValue(fields.building, itemBuilding(item));
    selectValue(fields.floor, itemFloor(item));
    selectValue(fields.apartment, code);
    setField('selected-area', itemArea(item));
    setField('selected-rooms', itemRooms(item));
    setField('selected-price', itemPrice(item));
    setField('selected-url', itemUrl(item) || window.location.href);
    setSummary(item);
    var messageField = form.querySelector('[name="your-message"]');
    if (messageField) {
      var previousAutoMessage = messageField.getAttribute('data-h22-auto-message') || '';
      var nextAutoMessage = code ? TXT.messagePrefix + code + TXT.messageSuffix : '';
      if (!messageField.value || messageField.value === previousAutoMessage) {
        messageField.value = nextAutoMessage;
      }
      messageField.setAttribute('data-h22-auto-message', nextAutoMessage);
    }
  }

  function syncSelectedFromSelects() {
    var item = findItemByCode(fields.apartment && fields.apartment.value);
    if (item) applyItem(item);
  }

  function refreshDependentSelects(kind) {
    var building = fields.building.value;
    var floor = fields.floor.value;
    var pool = items.filter(function (item) {
      return (!building || itemBuilding(item) === building) && (!floor || itemFloor(item) === floor);
    });

    if (kind === 'building') {
      fields.floor.innerHTML = '';
      addOption(fields.floor, '', TXT.floor);
      unique(items.filter(function (item) {
        return !building || itemBuilding(item) === building;
      }).map(itemFloor)).forEach(function (value) { addOption(fields.floor, value, value); });
    }

    fields.apartment.innerHTML = '';
    addOption(fields.apartment, '', TXT.apartment);
    unique(pool.map(itemCode)).forEach(function (value) { addOption(fields.apartment, value, value); });
  }

  function isOfferTrigger(node) {
    if (!node || !node.matches || !node.matches('a, button, [role="button"]')) return false;
    var href = node.getAttribute('href') || node.getAttribute('data-mfp-src') || '';
    var text = normalize(node.textContent || '').toLowerCase();
    if (href.indexOf('#opal-contactform-popup') !== -1) return true;
    if (href.indexOf('/property/') !== -1 && (text.indexOf('ajanlat') !== -1 || text.indexOf('arajanlat') !== -1)) return true;
    if ((text.indexOf('ajanlatot kerek') !== -1 || text.indexOf('arajanlatot kerek') !== -1 || text.indexOf('ajanlatkeres') !== -1) && node.closest('.hm-lakas-card, .apt-card, .apartment-card, .property, article, .elementor-widget-container')) return true;
    return false;
  }

  function validate() {
    var nameField = form.querySelector('[name="your-name"]');
    var emailField = form.querySelector('[name="your-email"]');
    var phoneField = form.querySelector('[name="your-phone"]');
    var dateField = form.querySelector('[name="your-date"]');
    var privacyField = form.querySelector('[name="privacy-acceptance"]');
    var name = nameField.value.trim();
    var email = emailField.value.trim();
    var phone = phoneField.value.trim();
    var date = dateField.value.trim();

    if (!name || !email || !phone || !date || !privacyField.checked) {
      return TXT.required;
    }
    if (!emailField.checkValidity()) {
      return TXT.invalidEmail;
    }
    if ((dateField.min && date < dateField.min) || !dateField.checkValidity()) {
      return TXT.invalidDate;
    }
    return '';
  }

  function setSubmitting(active) {
    fields.submit.disabled = !!active;
    fields.submit.textContent = active ? TXT.sending : TXT.send;
  }

  function fillTracking() {
    var params = new URLSearchParams(window.location.search);
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function (name) {
      setField(name, params.get(name) || '');
    });
    setField('source_url', window.location.href);
    setField('landing_page', window.location.href);
    setField('referrer', document.referrer || '');
  }

  function open(trigger) {
    populateSelects();
    fillTracking();
    fields.status.className = 'h22-offer-status';
    fields.status.textContent = '';
    applyItem(triggerItem(trigger));
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('h22-offer-open');
    window.setTimeout(function () {
      var target = form.querySelector('[name="your-name"]');
      if (target) target.focus({ preventScroll: true });
    }, 80);
  }

  function close() {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('h22-offer-open');
  }

  function submit(event) {
    event.preventDefault();
    fields.status.className = 'h22-offer-status';
    fields.status.textContent = '';

    var validationMessage = validate();
    if (validationMessage) {
      fields.status.textContent = validationMessage;
      return;
    }

    fillTracking();
    setSubmitting(true);

    fetch(endpoint, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        return { ok: response.ok, data: data };
      });
    }).then(function (result) {
      if (result.ok && result.data && result.data.success) {
        fields.status.className = 'h22-offer-status is-success';
        fields.status.textContent = TXT.success;
        window.setTimeout(function () { window.location.href = thankYouUrl; }, 300);
        return;
      }
      fields.status.textContent = result.data && result.data.message ? result.data.message : TXT.failed;
      setSubmitting(false);
    }).catch(function () {
      fields.status.textContent = TXT.failed;
      setSubmitting(false);
    });
  }

  window.harmatUnifiedOfferOpen = open;

  modal.addEventListener('click', function (event) {
    if (event.target === modal || event.target.closest('[data-h22-offer-close]')) close();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) close();
  });
  fields.building.addEventListener('change', function () {
    refreshDependentSelects('building');
  });
  fields.floor.addEventListener('change', function () {
    refreshDependentSelects('floor');
  });
  fields.apartment.addEventListener('change', syncSelectedFromSelects);
  form.addEventListener('submit', submit);

  removeLegacyPopups();
  document.addEventListener('DOMContentLoaded', removeLegacyPopups);
  window.setTimeout(removeLegacyPopups, 600);
  window.setTimeout(removeLegacyPopups, 1800);

  document.addEventListener('click', function (event) {
    var trigger = event.target && event.target.closest ? event.target.closest('a, button, [role="button"]') : null;
    if (!isOfferTrigger(trigger)) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    open(trigger);
  }, true);

  if (window.location.hash && window.location.hash.indexOf('#opal-contactform-popup') === 0) {
    window.setTimeout(function () { open(null); }, 450);
  }
})();
</script>
    <?php
}
add_action('wp_footer', 'harmat_unified_offer_modal_footer', 999);
