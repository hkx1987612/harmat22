import { createRequire } from 'node:module';
import fs from 'node:fs';
import vm from 'node:vm';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const out = 'outputs/assistant-live-data';
fs.mkdirSync(out, { recursive: true });
const php = fs.readFileSync('wp-plugins/harmat-local-assistant/harmat-local-assistant.php', 'utf8');
new vm.Script(php.split('<script id="harmat-local-assistant-script">')[1].split('</script>')[0].replace(/<\?php[\s\S]*?\?>/g, 'null'));
const assert = (ok, label) => { if (!ok) throw new Error(label); };
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const report = { results: [], inquiryRequests: 0, pageErrors: [] };
try {
  for (const [device, viewport] of Object.entries({ desktop: { width: 1440, height: 1000 }, mobile: { width: 390, height: 844 } })) {
    const context = await browser.newContext({ viewport, locale: 'hu-HU' });
    await context.route('**/*', async route => {
      const request = route.request();
      const url = request.url();
      if (/google-analytics|googletagmanager|googleadservices|doubleclick|facebook\.net/.test(url)) return route.abort();
      if (url.includes('harmat-local-assistant/v1/event')) return route.fulfill({ json: { ok: true } });
      if (request.method() === 'POST' && !url.includes('harmat-local-assistant/v1/ask')) {
        report.inquiryRequests++;
        return route.abort();
      }
      return route.continue();
    });
    const page = await context.newPage();
    page.on('pageerror', e => report.pageErrors.push(device + ': ' + e.message));
    await page.goto('https://harmat22.hu/property/a1-1-l2/', { waitUntil: 'domcontentloaded' });
    const cookie = page.getByRole('button', { name: 'Csak szükséges sütik' });
    if (await cookie.isVisible()) await cookie.click();
    await page.locator('.harmat-local-ai-launch').click();
    async function ask(message) {
      await page.locator('.harmat-local-ai-input').fill(message);
      const response = page.waitForResponse(r => r.url().includes('harmat-local-assistant/v1/ask') && r.request().method() === 'POST');
      await page.locator('.harmat-local-ai-input').press('Enter');
      const r = await response;
      assert(r.status() === 200, 'ask HTTP ' + r.status());
      const result = await r.json();
      assert(result.ok, 'assistant response not okay');
      await page.waitForFunction(() => !Array.from(document.querySelectorAll('.harmat-local-ai-msg')).some(n => /^(Válasz készül|Checking apartments|正在查询房源)/.test(n.textContent)));
      return result;
    }
    for (const lang of ['hu', 'zh', 'en']) {
      await page.locator(`[data-harmat-ai-lang="${lang}"]`).click();
      const r = await ask('A3-4-L5');
      assert(r.cards.length === 1 && r.cards[0].title === 'A3-4-L5', 'exact apartment');
      assert(r.answer.includes('47,83'), 'corrected live area');
      assert(!/státusz: current|状态：current|Status: current/i.test(r.answer), 'unlocalized status');
      const actions = page.locator('[data-harmat-ai-offer]');
      await actions.last().click();
      await page.locator('#h22-offer-modal').waitFor({ state: 'visible' });
      assert((await page.locator('[data-h22-summary]').innerText()).includes('A3-4-L5'), 'correct apartment in quote');
      assert(!await page.locator('.harmat-local-ai-panel').isVisible(), 'assistant must close for quote');
      assert(await page.locator('[name="lead_source"]').count() === 5, 'five quote sources');
      await page.locator('[data-h22-offer-close]').click();
      await page.locator('.harmat-local-ai-launch').click();
      report.results.push(`${device} ${lang}: public area, labels, quote selection PASS`);
    }
    await page.locator('[data-harmat-ai-lang="hu"]').click();
    let r = await ask('2 szobás 70 millió');
    assert(r.selection.rooms === 2 && r.selection.budget === 70000000, 'initial criteria');
    r = await ask('erkéllyel');
    assert(r.selection.terrace && r.selection.rooms === 2 && r.selection.budget === 70000000, 'continued criteria');
    r = await ask('olcsóbb');
    assert(r.selection.cheap && r.cards.length > 0, 'cheaper refinement');
    assert(!/[\u3400-\u9fff]/u.test(r.answer), 'Hungarian answer mixed with Chinese');
    const overflow = await page.evaluate(() => {
      const p = document.querySelector('.harmat-local-ai-panel');
      return { page: document.documentElement.scrollWidth - innerWidth, panel: p.scrollWidth - p.clientWidth };
    });
    assert(overflow.page <= 2 && overflow.panel <= 2, 'overflow: ' + JSON.stringify(overflow));
    await page.screenshot({ path: `${out}/${device}-assistant.png` });
    r = await ask('Új keresés');
    assert(Object.keys(r.selection).length === 0 && r.cards.length === 0, 'reset criteria');
    report.results.push(`${device}: multiround selection, reset, no horizontal overflow PASS`);
    // Simulated backend outage: no production endpoint or form is changed.
    await page.route('**/harmat-local-assistant/v1/ask', route => route.fulfill({ status: 503, json: { ok: false } }));
    await page.locator('.harmat-local-ai-input').fill('A1-1-L2');
    await page.locator('.harmat-local-ai-input').press('Enter');
    await page.getByText('Most nem sikerült válaszolni.', { exact: false }).waitFor();
    assert(await page.locator('.harmat-local-ai-input').isEnabled(), 'input remains usable after failure');
    report.results.push(`${device}: simulated failure contact fallback PASS`);
    await context.close();
  }
  assert(report.inquiryRequests === 0, 'Unexpected form submission attempted');
  assert(report.pageErrors.length === 0, 'Browser errors: ' + report.pageErrors.join('; '));
} finally {
  fs.writeFileSync(`${out}/browser-tests.json`, JSON.stringify(report, null, 2));
  await browser.close();
}
console.log(JSON.stringify(report, null, 2));
