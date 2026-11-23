import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import path from 'node:path';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {attribute, fixtures, period, summarize} from './previews/crm-attribution-model.mjs';

const model = summarize(fixtures, period);
assert.deepEqual(model.breakdown, {
  youtube: {records: 4, qualified: 1}, search: {records: 3, qualified: 1},
  facebook: {records: 3, qualified: 1}, unknown: {records: 3, qualified: 2},
});
assert.equal(model.hits.length, 13);
assert.equal(model.qualified.length, 5);
assert.equal(model.qualified.find(x => x.customerKey === 'C-03').records.length, 2);
assert.equal(model.qualified.find(x => x.customerKey === 'C-09').conflict, true);
assert(!model.qualified.some(x => [null, 'C-02', 'C-04'].includes(x.customerKey)));
assert.deepEqual(attribute({clickIdPresent: true}), {channel: 'unknown', reason: 'clickOnly'});
assert.equal(attribute({tag: {source: 'google', medium: 'cpc'}, clickIdPresent: true}).channel, 'unknown');
assert.equal(attribute({tag: {source: 'youtube', campaign: 'other_campaign'}}).channel, 'unknown');
assert.equal(attribute({tag: {source: 'facebook', campaign: 'harmat_v4_leads_202611'}}).reason, 'conflict');
assert.equal(attribute({tag: {source: 'youtube', campaign: 'harmat_v4_leads_202611'}, verifiedSearch: true}).reason, 'conflict');
assert.equal(attribute({tag: {campaign: '__proto__'}}).reason, 'unmapped');
assert.throws(() => summarize(fixtures, {start: '2026-02-31', end: '2026-11-22'}));
assert.throws(() => summarize(fixtures, {start: period.end, end: period.start}));
assert.equal(summarize(fixtures, {start: '2026-11-09', end: '2026-11-09'}).qualified.length, 1);
assert.equal(summarize(fixtures, {start: '2026-11-23', end: '2026-11-23'}).hits.length, 0);
assert.equal(summarize([{...fixtures[0], sourceConflict: true}], period).qualified[0].channel, 'unknown');

if (process.argv.includes('--browser')) {
  const require = createRequire(import.meta.url);
  const {chromium} = require(process.env.HARMAT_PLAYWRIGHT || path.join(process.env.USERPROFILE,
    '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
  const output = path.join(path.dirname(fileURLToPath(import.meta.url)), '../../outputs/2026-11-23-crm-attribution-preview');
  const browser = await chromium.launch({channel: 'chrome'});
  try {
    for (const [width, height] of [[1440, 1000], [1920, 1080], [390, 844], [320, 640]]) {
      const page = await browser.newPage({viewport: {width, height}});
      const requests = [], errors = [];
      page.on('request', request => requests.push(request.url()));
      page.on('pageerror', error => errors.push(String(error)));
      await page.goto(pathToFileURL(path.join(output, 'index.html')).href);
      assert.equal(await page.locator('#count-records').textContent(), '13');
      assert.equal(await page.locator('#count-qualified').textContent(), '5');
      assert.equal(await page.locator('#count-unknown').textContent(), '3');
      assert.equal(await page.locator('#detail-rows tr').count(), 13);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width);
      const numberHeights = await page.locator('.metric strong').evaluateAll(nodes => nodes.map(n => n.getBoundingClientRect().height));
      assert(numberHeights.every(h => h === numberHeights[0] && h > 0));
      if (width === 1440) await page.screenshot({path: path.join(output, 'desktop.png')});
      await page.getByRole('tab', {name: '有效客户'}).click();
      assert.equal(await page.locator('#detail-rows tr').count(), 5);
      await page.locator('#detail-source').selectOption('unknown');
      assert.equal(await page.locator('#detail-rows tr').count(), 2);
      assert.match(await page.locator('#detail-rows').textContent(), /来源冲突/);
      if (width === 1440) await page.screenshot({path: path.join(output, 'qualified.png')});
      await page.locator('#detail-source').selectOption('search');
      assert.equal(await page.locator('#detail-rows tr').count(), 1);
      await page.getByRole('tab', {name: '有效客户'}).press('ArrowLeft');
      assert.equal(await page.locator('#detail-rows tr').count(), 3);
      await page.locator('#detail-source').selectOption('youtube');
      await page.locator('#detail-rows details summary').first().click();
      assert.equal(await page.locator('#detail-rows details[open] code').textContent(), 'harmat_v4_leads_202611');
      await page.getByRole('button', {name: 'Magyar'}).click();
      assert.equal(await page.locator('html').getAttribute('lang'), 'hu');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width);
      if (width === 390) {
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({path: path.join(output, 'mobile-hu.png')});
      }
      await page.locator('#start').fill('2026-11-23');
      assert(await page.locator('#date-error').isVisible());
      await page.locator('#end').fill('2026-11-23');
      assert.equal(await page.locator('#count-qualified').textContent(), '0');
      assert.match(await page.locator('#detail-rows').textContent(), /nincs találat/);
      await page.locator('#reset').click();
      assert.equal(await page.locator('#count-qualified').textContent(), '5');
      assert.equal(await page.locator('form, iframe').count(), 0);
      assert.equal(requests.filter(url => !url.startsWith('file:')).length, 0);
      assert.deepEqual(errors, []);
      await page.close();
    }
  } finally { await browser.close(); }
}
console.log('PASS: attribution rules, consent-safe unknown bucket, deduplication, and synthetic record boundaries.');
