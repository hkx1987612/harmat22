<?php
/**
 * Plugin Name: Harmat Confirmed Lead Tracking
 * Description: Consent-aware measurement of confirmed public inquiries without changing submission behavior.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', function () {
    if (!function_exists('harmat_unified_offer_modal_is_public_page')
        || !harmat_unified_offer_modal_is_public_page()
        || is_user_logged_in() || is_feed() || is_404() || wp_is_json_request()
        || !function_exists('hm_legal_policy_version_20260601')) {
        return;
    }

    $settings = get_option('googlesitekit_analytics-4_settings', array());
    $measurement_id = is_array($settings) ? (string) ($settings['measurementID'] ?? '') : '';
    $config = array(
        'ads' => 'AW-18191634808/7FpbCJ-ahLQcEPiiueJD',
        'analytics' => preg_match('/^G-[A-Z0-9]+$/', $measurement_id) ? $measurement_id : '',
        'policyVersion' => hm_legal_policy_version_20260601(),
        'thankYou' => is_page('koszonjuk'),
    );
    ?>
<script id="harmat-confirmed-lead-tracking" data-no-optimize="1" data-noptimize="1" data-cfasync="false">
(function () {
  if (window.harmatTrackConfirmedOffer) return;
  var config = <?php echo wp_json_encode($config); ?>;
  var pendingKey = 'harmat_ads_pending_offer_v1';
  var seen = {};

  function consent() {
    try {
      var match = document.cookie.match(/(?:^|;\s*)harmat_cookie_consent_v1=([^;]*)/);
      var value = match ? JSON.parse(decodeURIComponent(match[1])) : null;
      return value && value.policyVersion === config.policyVersion ? value : {};
    } catch (error) { return {}; }
  }

  function pending() {
    try { return JSON.parse(window.sessionStorage.getItem(pendingKey) || 'null'); }
    catch (error) { return null; }
  }

  function clearPending(id) {
    try {
      var value = pending();
      if (!id || (value && value.id === id)) window.sessionStorage.removeItem(pendingKey);
    } catch (error) {}
  }

  function analytics(name, parameters) {
    if (!config.analytics || consent().analytics !== true || typeof window.gtag !== 'function') return;
    try {
      parameters.send_to = config.analytics;
      window.gtag('event', name, parameters);
    } catch (error) {}
  }

  function sendAds(id, remember) {
    if (consent().marketing !== true || typeof window.gtag !== 'function') return;
    // Retain only a non-personal reference briefly, in case navigation interrupts the tag.
    if (remember) {
      try { window.sessionStorage.setItem(pendingKey, JSON.stringify({ id: id, at: Date.now() })); }
      catch (error) {}
    }
    try {
      window.gtag('event', 'conversion', {
        send_to: config.ads,
        transaction_id: 'harmat-offer-' + id,
        event_callback: function () { clearPending(id); }
      });
    } catch (error) {}
  }

  window.harmatTrackConfirmedOffer = function (result) {
    // The REST endpoint also returns success for ignored spam; only saved lead IDs qualify.
    if (!result || result.success !== true || result.ignored) return;
    var id = String(result.id || '');
    if (!/^[1-9][0-9]{0,14}$/.test(id) || seen[id]) return;
    seen[id] = true;
    sendAds(id, true);
    analytics('generate_lead', { method: 'website_inquiry' });
  };

  if (config.thankYou) {
    var saved = pending();
    clearPending();
    if (saved && /^[1-9][0-9]{0,14}$/.test(String(saved.id))
        && typeof saved.at === 'number' && Date.now() >= saved.at && Date.now() - saved.at < 300000) {
      sendAds(String(saved.id), false);
    }
  }

  document.addEventListener('click', function (event) {
    var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!link) return;
    var href = link.getAttribute('href') || '';
    // Contact clicks are Analytics observations, never successful Ads inquiries.
    if (/^tel:/i.test(href)) analytics('harmat_phone_click', { method: 'phone' });
    if (/^mailto:/i.test(href)) analytics('harmat_email_click', { method: 'email' });
  }, true);
})();
</script>
    <?php
}, 110);
