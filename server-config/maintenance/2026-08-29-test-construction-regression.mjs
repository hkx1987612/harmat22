import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const origin = 'https://harmat22.hu';
const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const routes = [
  '/',
  '/epitesi-naplo/',
  '/galeria/',
  '/lakaskereso/',
  '/property/a1-1-l2/',
  '/virtualis-lakasvalaszto/',
  '/virtualis-lakasvalaszto-elso-utem/',
  '/virtualis-lakasvalaszto-a1-epulet/',
  '/elerhetosegeink/',
];

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

const browser = await chromium.launch({ executablePath: chromePath, headless: true });

try {
  for (const device of [
    { name: 'desktop', viewport: { width: 1440, height: 1000 } },
    { name: 'mobile', viewport: { width: 390, height: 844 } },
  ]) {
    const context = await browser.newContext({ viewport: device.viewport, locale: 'hu-HU' });
    const page = await context.newPage();

    for (const route of routes) {
      const url = origin + route;
      const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      assert(response?.status() === 200, `${device.name} ${route}: HTTP ${response?.status()}.`);
      await page.waitForSelector('body', { timeout: 15000 });

      const necessaryCookies = page.getByRole('button', { name: 'Csak szükséges sütik' });
      if (await necessaryCookies.isVisible().catch(() => false)) {
        await necessaryCookies.click();
      }

      const audit = await page.evaluate(() => {
        const html = document.documentElement;
        const text = document.body?.innerText || '';
        return {
          lang: html.lang,
          overflow: html.scrollWidth - html.clientWidth,
          text,
          h1Count: document.querySelectorAll('h1').length,
          contentRootCount: document.querySelectorAll('main,#primary,.site-content').length,
        };
      });

      assert(audit.lang.toLowerCase().startsWith('hu'), `${device.name} ${route}: language changed.`);
      assert(audit.overflow <= 2, `${device.name} ${route}: horizontal overflow is ${audit.overflow}px.`);
      assert(audit.contentRootCount >= 1, `${device.name} ${route}: main content is missing.`);
      assert(audit.h1Count === 1, `${device.name} ${route}: expected one H1, found ${audit.h1Count}.`);
      assert(!/[\u3400-\u9fff]/u.test(audit.text), `${device.name} ${route}: public Chinese text detected.`);
      assert(!/Fatal error|Parse error|Kritikus hiba|critical error/iu.test(audit.text), `${device.name} ${route}: fatal output detected.`);

      if (route === '/epitesi-naplo/') {
        assert(await page.locator('[data-harmat-construction-video="1"]').count() === 1, `${device.name}: construction video missing.`);
        assert(await page.locator('[data-harmat-construction-gallery="1"]').count() === 1, `${device.name}: construction gallery missing.`);
        assert(await page.locator('[data-harmat-construction-photo]').count() === 16, `${device.name}: construction photo count changed.`);
        assert(await page.locator('[data-harmat-construction-player] iframe').count() === 0, `${device.name}: construction iframe loaded early.`);
      }
      if (route === '/lakaskereso/') {
        assert(/124\s+lakás/iu.test(audit.text) || /124/iu.test(audit.text), `${device.name}: apartment count is missing.`);
      }
      if (route === '/galeria/') {
        assert(await page.locator('main img').count() >= 1, `${device.name}: gallery images are missing.`);
      }
      if (route === '/property/a1-1-l2/' && device.name === 'mobile') {
        const cta = page.getByText(/Ajánlatot kérek/i).first();
        await cta.click();
        await page.getByText('Honnan hallott rólunk?', { exact: true }).waitFor({ state: 'visible', timeout: 10000 });
        const sources = await page.locator('input[type="radio"][name="lead_source"]:visible').evaluateAll((nodes) => nodes.map((node) => node.value));
        assert(JSON.stringify(sources) === JSON.stringify([
          'Kültéri hirdetés',
          'Google keresés',
          'ingatlan.com',
          'Közösségi média',
          'Egyéb',
        ]), `${device.name}: offer sources changed: ${sources.join(', ')}.`);
        assert((await page.locator('body').innerText()).includes('A1-1-L2 | 57,36 m² | 2 szoba / 1 háló | 70'), `${device.name}: offer property data is missing.`);
      }

      console.log(`${device.name.toUpperCase()} ${route} PASS`);
    }

    await context.close();
  }
} finally {
  await browser.close();
}

console.log('CONSTRUCTION_CHANGE_KEY_PAGE_REGRESSION_PASSED');
