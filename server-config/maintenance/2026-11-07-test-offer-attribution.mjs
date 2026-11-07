import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const sourcePath = 'wp-mu-plugins/harmat-unified-offer-modal.php';
const source = fs.readFileSync(path.join(root, sourcePath), 'utf8').replace(/\r\n/g, '\n');
const original = execFileSync('git', ['show', '9608d9c5789e12ac20dfa6147d0a1c1c6e57b3b0:' + sourcePath], {cwd: root, encoding: 'utf8'});
const start = '  function fillTracking() {';
const end = '\n  function open(trigger) {';
const block = source.slice(source.indexOf(start), source.indexOf(end, source.indexOf(start)));
const policy = '2026-06-09-v1.6';
const script = block.replace(/<\?php echo wp_json_encode\(function_exists\('hm_legal_policy_version_20260601'\)[\s\S]*?\?>/, JSON.stringify(policy));
const key = 'harmatOfferAttributionV1';
const tagged = 'https://harmat22.hu/lakaskereso/?utm_source=youtube&utm_medium=paid_video&utm_campaign=harmat_v4_leads_202611&utm_content=v4';
const detail = 'https://harmat22.hu/property/a1-1-l6/';
const consent = value => 'harmat_cookie_consent_v1=' + encodeURIComponent(JSON.stringify({policyVersion: policy, marketing: true, ...value}));
const cases = [];
const test = (name, fn) => { fn(); cases.push(name); };

function page(url = tagged, options = {}) {
  const store = options.store || new Map();
  const fields = {lead_source: 'ingatlan.com', 'your-date': '2026-11-20', 'selected-apartment': 'A1-1-L6'};
  const listeners = {window: {}, document: {}};
  const calls = {get: 0, set: 0, remove: 0, network: 0};
  const timers = [];
  const clock = {now: options.now ?? 1800000000000};
  const document = {cookie: options.cookie ?? consent(), referrer: options.referrer || '',
    addEventListener: (name, fn) => { listeners.document[name] = fn; }};
  const window = {location: new URL(url),
    addEventListener: (name, fn) => { listeners.window[name] = fn; },
    setTimeout: fn => { timers.push(fn); },
    sessionStorage: {
      getItem: name => { calls.get++; if (options.blockGet) throw Error('blocked'); return store.get(name) ?? null; },
      setItem: (name, value) => { calls.set++; if (options.blockSet) throw Error('quota'); store.set(name, value); },
      removeItem: name => { calls.remove++; if (options.blockRemove) throw Error('blocked'); store.delete(name); }
    }};
  class TestDate extends Date { static now() { return clock.now; } }
  const context = vm.createContext({window, document, URL, URLSearchParams, Date: TestDate,
    setField: (name, value) => {
      assert(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'source_url', 'landing_page', 'referrer'].includes(name));
      fields[name] = value;
    },
    fetch: () => { calls.network++; throw Error('No network allowed'); }});
  vm.runInContext(script, context);
  return {store, fields, calls, clock, document, window,
    fill: () => { vm.runInContext('fillTracking()', context); return fields; },
    cookieClick: () => {
      listeners.document.click({target: {closest: () => ({})}});
      while (timers.length) timers.shift()();
    },
    pageshow: () => listeners.window.pageshow()};
}

test('only_version_and_attribution_block_change', () => {
  assert.equal(source.slice(0, source.indexOf(start)).replace('Version: 1.0.9', 'Version: 1.0.8'), original.slice(0, original.indexOf(start)));
  assert.equal(source.slice(source.indexOf(end)), original.slice(original.indexOf(end)));
});
test('all_embedded_javascript_parses', () => {
  for (const match of source.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)) {
    new vm.Script(match[1].replace(/<\?php[\s\S]*?\?>/g, '""'));
  }
});
test('capture_without_opening_modal', () => {
  const p = page();
  assert(p.store.has(key));
  assert.equal(p.fields.utm_campaign, undefined);
});
test('v4_entry_to_untagged_detail_retains_campaign', () => {
  const entry = page(tagged, {referrer: 'https://www.youtube.com/'});
  const p = page(detail, {store: entry.store, referrer: 'https://harmat22.hu/lakaskereso/'});
  const f = p.fill();
  assert.equal(f.utm_source, 'youtube');
  assert.equal(f.utm_medium, 'paid_video');
  assert.equal(f.utm_campaign, 'harmat_v4_leads_202611');
  assert.equal(f.utm_content, 'v4');
  assert.equal(f.landing_page, tagged);
  assert.equal(f.referrer, 'https://www.youtube.com/');
  assert.equal(f.source_url, detail);
});
for (const source of ['google', 'facebook', 'ingatlan.com']) {
  test(source + '_tagged_entry_survives_two_internal_pages', () => {
    const entry = page('https://harmat22.hu/?utm_source=' + source + '&utm_medium=cpc&utm_campaign=synthetic');
    const middle = page('https://harmat22.hu/lakaskereso/?rooms=3', {store: entry.store});
    const p = page(detail, {store: middle.store});
    assert.equal(p.fill().utm_source, source);
    assert.equal(p.fill().utm_campaign, 'synthetic');
    assert.equal(p.fill().lead_source, 'ingatlan.com');
    assert.equal(p.fields['your-date'], '2026-11-20');
    assert.equal(p.fields['selected-apartment'], 'A1-1-L6');
  });
}
test('new_campaign_replaces_all_old_fields', () => {
  const a = page(tagged + '&utm_term=old_term');
  const b = page('https://harmat22.hu/?utm_source=google&utm_campaign=new', {store: a.store});
  const f = page(detail, {store: b.store}).fill();
  assert.equal(f.utm_source, 'google');
  assert.equal(f.utm_campaign, 'new');
  assert.equal(f.utm_medium, '');
  assert.equal(f.utm_content, '');
  assert.equal(f.utm_term, '');
});
for (const marker of ['gclid', 'gbraid', 'wbraid']) {
  test(marker + '_only_entry_does_not_inherit_old_campaign', () => {
    const a = page();
    const incoming = 'https://harmat22.hu/lakaskereso/?' + marker + '=synthetic-click';
    const b = page(incoming, {store: a.store});
    const f = page(detail, {store: b.store}).fill();
    assert.equal(f.landing_page, incoming);
    assert.equal(f.utm_source, '');
    assert.equal(f.utm_campaign, '');
  });
}
test('organic_then_campaign_uses_tagged_entry', () => {
  const a = page('https://harmat22.hu/');
  const b = page(tagged, {store: a.store});
  assert.equal(page(detail, {store: b.store}).fill().landing_page, tagged);
});
test('same_tags_keep_first_landing_and_referrer', () => {
  const a = page(tagged, {referrer: 'https://www.youtube.com/watch?v=synthetic'});
  const b = page('https://harmat22.hu/?' + new URL(tagged).searchParams, {store: a.store, referrer: detail});
  assert.equal(b.fill().landing_page, tagged);
  assert.equal(b.fill().referrer, 'https://www.youtube.com/watch');
});
test('inactivity_expires_source_instead_of_fabricating_old_attribution', () => {
  const a = page();
  const b = page(detail, {store: a.store, now: a.clock.now + 30 * 60 * 1000 + 1});
  assert.equal(b.fill().utm_campaign, '');
  assert.equal(b.fill().landing_page, detail);
});
test('active_pages_refresh_session_time', () => {
  const a = page();
  const b = page(detail, {store: a.store, now: a.clock.now + 29 * 60 * 1000});
  const c = page('https://harmat22.hu/elerhetosegeink/', {store: b.store, now: b.clock.now + 29 * 60 * 1000});
  assert.equal(c.fill().utm_campaign, 'harmat_v4_leads_202611');
});
for (const cookie of ['', consent({marketing: false}), consent({marketing: false, analytics: true}),
                      consent({policyVersion: 'old'}), consent({marketing: 'true'}),
                      'harmat_cookie_consent_v1=%invalid']) {
  test('no_cross_page_storage_without_current_marketing_consent_' + cases.length, () => {
    const store = new Map([[key, 'stale'], ['unrelated', 'keep'], ['harmatLeadAttribution', 'legacy']]);
    const p = page(tagged, {cookie, store});
    assert.equal(p.fill().utm_source, 'youtube');
    assert.equal(p.calls.get, 0);
    assert.equal(p.calls.set, 0);
    assert.equal(store.has(key), false);
    assert.equal(store.get('unrelated'), 'keep');
    assert.equal(store.get('harmatLeadAttribution'), 'legacy');
    assert.equal(page(detail, {store, cookie}).fill().utm_campaign, '');
  });
}
test('accepting_cookie_choice_after_load_captures_current_entry', () => {
  const a = page(tagged, {cookie: ''});
  assert(!a.store.has(key));
  a.document.cookie = consent();
  a.cookieClick();
  assert.equal(page(detail, {store: a.store}).fill().utm_campaign, 'harmat_v4_leads_202611');
});
test('revocation_clears_only_owned_record_and_stale_form_fields', () => {
  const a = page();
  const b = page(detail, {store: a.store});
  assert.equal(b.fill().utm_campaign, 'harmat_v4_leads_202611');
  b.store.set('unrelated', 'keep');
  b.document.cookie = consent({marketing: false});
  b.cookieClick();
  assert(!b.store.has(key));
  assert.equal(b.fill().utm_campaign, '');
  assert.equal(b.fields.landing_page, detail);
  assert.equal(b.store.get('unrelated'), 'keep');
});
for (const options of [{blockGet: true}, {blockSet: true}, {cookie: '', blockRemove: true}]) {
  test('blocked_storage_does_not_break_existing_form_' + cases.length, () => {
    const p = page(tagged, options);
    assert.equal(p.fill().utm_source, 'youtube');
    assert.equal(p.fields.source_url, tagged);
    assert.equal(p.calls.network, 0);
  });
}
for (const bad of ['{bad json', 'null', '42', '[]', '"text"',
  JSON.stringify({version: 1, last_seen: 1800000000001, landing_page: tagged, referrer: ''}),
  JSON.stringify({version: 1, last_seen: 1800000000000, landing_page: 'https://evil.example/?utm_source=forged', referrer: ''}),
  JSON.stringify({version: 1, last_seen: 1800000000000, landing_page: 'https://user:password@harmat22.hu/', referrer: ''}),
  JSON.stringify({version: 1, last_seen: 1800000000000, landing_page: tagged, referrer: 'javascript:alert(1)'})]) {
  test('invalid_record_fails_safe_' + cases.length, () => {
    const p = page(detail, {store: new Map([[key, bad]])});
    assert.equal(p.fill().utm_campaign, '');
    assert.equal(p.fields.landing_page, detail);
  });
}
test('stored_entry_excludes_unrelated_query_data_and_fragments', () => {
  const p = page(tagged + '&email=synthetic%40example.invalid&token=synthetic-secret#fragment',
    {referrer: 'https://example.org/path?token=synthetic-secret#fragment'});
  const stored = p.store.get(key);
  assert(!stored.includes('synthetic-secret'));
  assert(!stored.includes('email='));
  assert(!stored.includes('#fragment'));
  assert.equal(p.fill().referrer, 'https://example.org/path');
});
test('parameter_values_are_bounded_and_controls_removed', () => {
  const p = page('https://harmat22.hu/?utm_source=' + 'a'.repeat(400) + '&utm_campaign=one%0Atwo');
  assert.equal(p.fill().utm_source.length, 180);
  assert.equal(p.fill().utm_campaign, 'onetwo');
});
test('pageshow_refreshes_session_without_network', () => {
  const p = page();
  p.clock.now += 1000;
  p.pageshow();
  assert.equal(JSON.parse(p.store.get(key)).last_seen, p.clock.now);
  assert.equal(p.calls.network, 0);
});
test('no_campaign_or_source_is_invented_for_direct_visitors', () => {
  const p = page(detail);
  assert.equal(p.fill().utm_source, '');
  assert.equal(p.fill().utm_campaign, '');
  assert.equal(p.fields.lead_source, 'ingatlan.com');
});

const result = {status: 'PASS', checks: cases.length, cases,
  scope: 'OFFLINE_REAL_ATTRIBUTION_BLOCK_NO_NETWORK_NO_SUBMISSIONS'};
const out = path.join(root, 'outputs/2026-11-07-offer-attribution');
fs.mkdirSync(out, {recursive: true});
fs.writeFileSync(path.join(out, 'unit-tests.json'), JSON.stringify(result, null, 2));
console.log(JSON.stringify(result));
