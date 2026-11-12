'use strict';
// Synthetic renderer output only; all external requests and form submissions blocked.
const fs = require('node:fs/promises');
const path = require('node:path');
const assert = require('node:assert/strict');
const {pathToFileURL} = require('node:url');
const {chromium} = require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root = path.resolve(__dirname, '../..');
const out = path.join(root, 'outputs/2026-11-12-inquiry-counter-fix/preview');
(async () => {
  const browser = await chromium.launch({executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true});
  const results = [];
  try {
    for (const language of ['zh', 'hu']) for (const viewport of [{width:1920,height:1080}, {width:1440,height:1000}, {width:390,height:844}]) {
      const context = await browser.newContext({viewport, serviceWorkers:'block'});
      await context.route('**/*', route => route.request().url().startsWith('file:') ? route.continue() : route.abort());
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.goto(pathToFileURL(path.join(out, `manager-${language}.html`)).href);
      await page.evaluate(() => {
        for (const form of document.forms) form.addEventListener('submit', e => e.preventDefault());
      });
      const summary = await page.evaluate(() => {
        const cards = [...document.querySelectorAll('.harmat-sales-kpis-compact article')];
        return {
          values:cards.map(card => Number(card.querySelector('strong').textContent)),
          overflowing:cards.some(card => card.scrollWidth > card.clientWidth + 1),
          overlap:cards.some((card,i) => cards.slice(i+1).some(other => {
            const a=card.getBoundingClientRect(), b=other.getBoundingClientRect();
            return Math.min(a.right,b.right)-Math.max(a.left,b.left)>1 && Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top)>1;
          })),
          horizontalOverflow:document.documentElement.scrollWidth-document.documentElement.clientWidth,
          tableHeaders:document.querySelectorAll('.harmat-sales-inquiry-table thead th').length,
          tableRows:document.querySelectorAll('.harmat-sales-inquiry-table tbody tr').length,
        };
      });
      assert.deepEqual(errors, []);
      assert.deepEqual(summary.values, [96,53,53,160,202]);
      assert.equal(summary.overflowing, false);
      assert.equal(summary.overlap, false);
      assert(summary.horizontalOverflow <= 1, 'No page-level horizontal overflow');
      assert.equal(summary.tableHeaders, 9);
      assert.equal(summary.tableRows, 160);
      await page.screenshot({path:path.join(out, `${language}-${viewport.width}.png`)});
      await page.locator('.harmat-sales-kpis-compact').screenshot({path:path.join(out, `${language}-${viewport.width}-summary.png`)});
      results.push({language, width:viewport.width, ...summary, errors});
      await context.close();
    }
    await fs.writeFile(path.join(out, 'layout.json'), JSON.stringify({status:'PASS',synthetic:true,results}, null, 2));
    console.log('PASS 6 Chromium layouts (1920/1440/390, Chinese/Hungarian); synthetic records only.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
