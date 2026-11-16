import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import {reviewModel, escapeHtml, recordedSource, sourceDetails, filterExamples, sourceExamples} from './previews/crm-source-review.mjs';

// Fictional counts, deliberately independent of the private business audit.
const makeAudit = () => ({reporting_period:{start:'2026-11-09',end:'2026-11-15'},verified:{crm:{period_website_records:3,period_walkin_registration_records:5,period_broker_records:1,website_records_with_contact_progress_not_recorded:2,walkin_records_matching_earlier_website_email_in_loaded_records:1}}});

test('record totals reconcile without representing records as qualified people or visits', () => {
  const m = reviewModel(makeAudit());
  assert.equal(m.total,9);
  assert.equal(m.qualified,null);
  assert.equal(m.visits,null);
  assert.equal(m.attribution,'unverified');
  assert.equal(m.possibleReturning,1);
});
test('missing, invalid and partial counts never silently become zero', () => {
  for (const value of [undefined,null,-1,1.5,'4',Infinity]) {
    const a = makeAudit(); a.verified.crm.period_website_records=value;
    assert.throws(()=>reviewModel(a));
  }
  assert.throws(()=>reviewModel({}));
});
test('empty date, reversed period and invalid dates are rejected', () => {
  for (const range of [{start:'',end:'2026-11-15'},{start:'2026-11-16',end:'2026-11-15'},{start:'2026-1-9',end:'2026-11-15'},{start:'2026-02-30',end:'2026-11-15'},{start:'2026-99-01',end:'2026-11-15'}]) {
    const a=makeAudit();a.reporting_period=range;assert.throws(()=>reviewModel(a));
  }
});
test('real zero remains a record count, not proof of zero qualified people', () => {
  const a=makeAudit();for(const key of ['period_website_records','period_walkin_registration_records','period_broker_records'])a.verified.crm[key]=0;
  a.verified.crm.website_records_with_contact_progress_not_recorded=0;
  a.verified.crm.walkin_records_matching_earlier_website_email_in_loaded_records=0;
  assert.equal(reviewModel(a).total,0);assert.equal(reviewModel(a).visits,null);
});
test('supporting counts are bounded and missing confirmations stay unknown', () => {
  const a=makeAudit();
  a.verified.crm.website_records_with_contact_progress_not_recorded=undefined;
  assert.equal(reviewModel(a).unrecorded,null);
  a.verified.crm.walkin_records_matching_earlier_website_email_in_loaded_records=7;
  assert.throws(()=>reviewModel(a));
  a.verified.crm.walkin_records_matching_earlier_website_email_in_loaded_records=-1;
  assert.throws(()=>reviewModel(a));
});
test('unread source is distinct from read but missing source', () => {
  assert.equal(recordedSource({utm_source:'google'}),'unread');
  assert.equal(recordedSource({source_read:true}),'missing');
  assert.equal(recordedSource({source_read:true,utm_source:'google'}),'recorded');
  assert.equal(recordedSource({source_read:true,utm_source:'  '}),'missing');
  assert.equal(recordedSource({source_read:true,source_page:'https://harmat22.hu/property/example/'}),'missing');
  assert.equal(recordedSource({source_read:true,landing_page:'https://harmat22.hu/'}),'missing');
  assert.equal(recordedSource({source_read:true,referrer:'https://www.google.com/'}),'recorded');
});
test('stored source fields are escaped, non-clickable, and never execute code', () => {
  const bad='<img src=x onerror=alert(1)>';
  const output=sourceDetails({source_read:true,utm_source:bad,landing_page:'javascript:alert(1)'});
  assert(!output.includes('<img'));
  assert(!output.includes('href='));
  assert(output.includes('&lt;img'));
  assert.equal(escapeHtml('"\'&<>'),'&quot;&#39;&amp;&lt;&gt;');
});
test('source examples never claim reliable campaign attribution or qualification', () => {
  for(const lang of ['zh','hu'])for(const row of sourceExamples){
    const output=sourceDetails({...row,source_read:true},lang);
    assert(output.includes('<details'));
    assert(output.includes('utm_campaign'));
    assert(!output.includes('已确认到访'));
  }
});
test('filter and sort are independent, deterministic and do not mutate fixtures', () => {
  assert.equal(filterExamples(sourceExamples,'youtube').length,1);
  assert.equal(filterExamples(sourceExamples,'facebook')[0].utm_medium,'paid_social');
  assert.equal(filterExamples(sourceExamples,'missing')[0].utm_source,'');
  assert.equal(filterExamples(sourceExamples,'all','asc')[0].id,'S-04');
  assert.equal(filterExamples(sourceExamples,'all','desc')[0].id,'S-01');
  assert.equal(sourceExamples[0].id,'S-01');
  assert.equal(recordedSource({...sourceExamples[3],source_read:true}),'missing');
});
test('preview application has no write, network, cookie or production navigation APIs', () => {
  const source=readFileSync(new URL('./previews/crm-source-review.mjs',import.meta.url),'utf8');
  assert(!/\b(fetch|XMLHttpRequest|WebSocket|sendBeacon)\s*\(/.test(source));
  assert(!/document\.cookie|localStorage|sessionStorage|location\.(href|assign|replace)/.test(source));
  assert(!/<form|type=["']submit/.test(source));
  assert(!/\bqualified\s*:\s*0|\bvisits\s*:\s*0/.test(source));
});
