import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const root = new URL('../../', import.meta.url);
const read = (file) => readFileSync(new URL(file, root), 'utf8');
const config = {
  ads: 'AW-18191634808/7FpbCJ-ahLQcEPiiueJD',
  analytics: 'G-TEST123',
  policyVersion: 'test-policy',
  thankYou: false,
};
const php = read('wp-mu-plugins/zz-harmat-confirmed-lead-tracking.php');
const script = php.match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];
const consentCookie = (value) => 'harmat_cookie_consent_v1=' + encodeURIComponent(JSON.stringify({
  policyVersion: config.policyVersion, analytics: true, marketing: true, ...value,
}));
const pendingKey = 'harmat_ads_pending_offer_v1';

function harness(options = {}) {
  const calls = [];
  const listeners = {};
  const storage = options.storage || new Map();
  const document = {
    cookie: options.cookie ?? consentCookie(),
    addEventListener: (name, handler) => { listeners[name] = handler; },
  };
  const window = {
    sessionStorage: {
      getItem: (key) => {
        if (options.storageThrows) throw new Error('blocked storage');
        return storage.get(key) ?? null;
      },
      setItem: (key, value) => {
        if (options.storageThrows) throw new Error('blocked storage');
        storage.set(key, value);
      },
      removeItem: (key) => storage.delete(key),
    },
    gtag: (...args) => {
      if (options.tagThrows) throw new Error('blocked Google tag');
      calls.push(args);
    },
  };
  if (options.noTag) delete window.gtag;
  const source = script.replace('<?php echo wp_json_encode($config); ?>', JSON.stringify({
    ...config, ...options.config,
  }));
  const context = vm.createContext({ window, document });
  vm.runInContext(source, context);
  return { window, document, storage, calls, listeners, source, context,
    track: window.harmatTrackConfirmedOffer };
}

const savedLead = { success: true, id: 1234, crm: 'not-for-analytics', email: 'not-for-analytics' };
const adsCalls = (h) => h.calls.filter((call) => call[1] === 'conversion');

test('only confirmed saved inquiries send one primary conversion and one GA4 lead', () => {
  const h = harness();
  h.track(savedLead);
  h.track(savedLead);
  assert.equal(adsCalls(h).length, 1);
  assert.equal(h.calls.filter((call) => call[1] === 'generate_lead').length, 1);
  const payload = adsCalls(h)[0][2];
  assert.equal(payload.send_to, config.ads);
  assert.equal(payload.transaction_id, 'harmat-offer-1234');
  assert.deepEqual(Object.keys(payload).sort(), ['event_callback', 'send_to', 'transaction_id']);
  assert.equal(JSON.stringify(h.calls).includes('not-for-analytics'), false);
});

test('spam, failed, missing and malformed IDs never count', () => {
  const h = harness();
  for (const result of [null, {}, { success: false, id: 12 }, { success: 'true', id: 12 },
    { success: true, ignored: true, id: 12 }, { success: true },
    ...[0, -1, 1.2, 'email@example.test', '1e2', '001', '1234567890123456'].map((id) => ({ success: true, id }))]) {
    h.track(result);
  }
  assert.equal(h.calls.length, 0);
});

test('no consent, rejection, malformed or outdated consent does not send or store', () => {
  for (const cookie of ['', consentCookie({ analytics: false, marketing: false }),
    consentCookie({ policyVersion: 'old' }), 'harmat_cookie_consent_v1=%INVALID']) {
    const h = harness({ cookie });
    h.track(savedLead);
    assert.equal(h.calls.length, 0);
    assert.equal(h.storage.size, 0);
  }
});

test('analytics and marketing choices remain independent', () => {
  const analyticsOnly = harness({ cookie: consentCookie({ marketing: false }) });
  analyticsOnly.track(savedLead);
  assert.deepEqual(analyticsOnly.calls.map((call) => call[1]), ['generate_lead']);
  const adsOnly = harness({ cookie: consentCookie({ analytics: false }) });
  adsOnly.track(savedLead);
  assert.deepEqual(adsOnly.calls.map((call) => call[1]), ['conversion']);
});

test('missing and throwing Google tags or blocked storage do not break submission', () => {
  for (const options of [{ noTag: true }, { tagThrows: true }, { storageThrows: true }]) {
    const h = harness(options);
    assert.doesNotThrow(() => h.track(savedLead));
  }
});

test('tag callback removes only its own pending reference', () => {
  const h = harness();
  h.track(savedLead);
  assert.equal(JSON.parse(h.storage.get(pendingKey)).id, '1234');
  h.track({ success: true, id: 1235 });
  adsCalls(h)[0][2].event_callback();
  assert.equal(JSON.parse(h.storage.get(pendingKey)).id, '1235');
  adsCalls(h)[1][2].event_callback();
  assert.equal(h.storage.has(pendingKey), false);
});

test('interrupted navigation retries once with the same Ads deduplication ID', () => {
  const first = harness();
  first.track(savedLead);
  const thanks = harness({ storage: first.storage, config: { thankYou: true } });
  assert.equal(adsCalls(thanks).length, 1);
  assert.equal(adsCalls(thanks)[0][2].transaction_id, adsCalls(first)[0][2].transaction_id);
  assert.equal(thanks.calls.filter((call) => call[1] === 'generate_lead').length, 0);
  const refresh = harness({ storage: first.storage, config: { thankYou: true } });
  assert.equal(refresh.calls.length, 0);
});

test('direct or stale thank-you visits and withdrawn consent do not convert', () => {
  for (const entry of [null, { id: '1234', at: Date.now() - 301000 },
    { id: '1234', at: Date.now() + 60000 }, { id: 'bad', at: Date.now() }]) {
    const storage = new Map(entry ? [[pendingKey, JSON.stringify(entry)]] : []);
    assert.equal(harness({ storage, config: { thankYou: true } }).calls.length, 0);
  }
  const first = harness();
  first.track(savedLead);
  assert.equal(harness({ storage: first.storage, config: { thankYou: true },
    cookie: consentCookie({ marketing: false }) }).calls.length, 0);
});

test('phone and email clicks are observations only and do not prevent navigation', () => {
  const h = harness();
  for (const href of ['tel:+36300733375', 'mailto:ertekesites@harmat22.hu', '/lakaskereso/']) {
    h.listeners.click({ target: { closest: () => ({ getAttribute: () => href }) } });
  }
  assert.deepEqual(h.calls.map((call) => call[1]), ['harmat_phone_click', 'harmat_email_click']);
  assert.equal(adsCalls(h).length, 0);
  h.document.cookie = consentCookie({ analytics: false });
  h.listeners.click({ target: { closest: () => ({ getAttribute: () => 'tel:123' }) } });
  assert.equal(h.calls.length, 2);
});

test('repeated plugin initialization does not add duplicate listeners', () => {
  const h = harness();
  const original = h.track;
  vm.runInContext(h.source, h.context);
  assert.equal(h.window.harmatTrackConfirmedOffer, original);
});

async function submitHarness({ legacy = false, ok = true, data = savedLead,
  validation = '', reject = false, trackerThrows = false, noTracker = false } = {}) {
  const file = legacy ? 'harmat-performance-guard.php' : 'harmat-unified-offer-modal.php';
  const full = read('wp-mu-plugins/' + file);
  const start = legacy ? '  function submitViaRest(form) {' : '  function submit(event) {';
  const end = legacy ? '  function handleOfferSubmit(' : '  window.harmatUnifiedOfferOpen = open;';
  const source = full.slice(full.indexOf(start), full.indexOf(end, full.indexOf(start)));
  const calls = [];
  const timers = [];
  const fields = { status: { className: '', textContent: '' } };
  const form = { dataset: {} };
  const window = {
    sessionStorage: { getItem: () => null },
    location: { href: 'https://harmat22.hu/' },
    setTimeout: (callback, delay) => timers.push({ callback, delay }),
    harmatTrackConfirmedOffer: (result) => {
      if (trackerThrows) throw new Error('measurement unavailable');
      calls.push(result);
    },
  };
  if (noTracker) delete window.harmatTrackConfirmedOffer;
  let requests = 0;
  let fallback = 0;
  let redirected = false;
  const context = vm.createContext({
    window, fields, form, endpoint: '/offer', fastOfferEndpoint: '/offer', offerNonce: 'test',
    thankYouUrl: '/koszonjuk/', TXT: { success: 'ok', failed: 'failed' },
    validate: () => validation, fillTracking: () => {}, setSubmitting: () => {},
    valueOf: () => '', redirectSoon: () => { redirected = true; }, showResponse: () => {},
    submitViaCf7: () => { fallback++; },
    FormData: class { append() {} get() { return ''; } },
    fetch: async () => {
      requests++;
      if (reject) throw new Error('network failure');
      return { ok, status: ok ? 200 : 400, json: async () => data };
    },
  });
  vm.runInContext(source + (legacy ? '\nsubmitViaRest(form);' : '\nsubmit({preventDefault(){}});'), context);
  await new Promise(setImmediate);
  await new Promise(setImmediate);
  return { calls, timers, fields, requests, fallback, redirected };
}

test('unified modal only invokes measurement on successful HTTP and application response', async () => {
  const success = await submitHarness();
  assert.equal(success.calls.length, 1);
  assert.equal(success.fields.status.textContent, 'ok');
  assert.equal(success.timers[0].delay, 300);
  for (const options of [{ ok: false }, { data: { success: false } }, { reject: true }, { validation: 'required' }]) {
    assert.equal((await submitHarness(options)).calls.length, 0);
  }
  assert.equal((await submitHarness({ validation: 'required' })).requests, 0);
});

test('missing or throwing measurement cannot break the success UI or redirect', async () => {
  for (const options of [{ trackerThrows: true }, { noTracker: true }]) {
    const result = await submitHarness(options);
    assert.equal(result.fields.status.textContent, 'ok');
    assert.equal(result.timers.length, 1);
    assert.equal((await submitHarness({ ...options, legacy: true })).redirected, true);
  }
});

test('legacy REST appointment success tracks, but fallback and failures do not invent leads', async () => {
  assert.equal((await submitHarness({ legacy: true })).calls.length, 1);
  for (const options of [{ ok: false, data: { message: 'invalid' } }, { reject: true }]) {
    assert.equal((await submitHarness({ ...options, legacy: true })).calls.length, 0);
  }
});
