import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const origin = 'https://harmat22.hu';
const expected = [
  ['Főoldal', '/'],
  ['Lakáskereső', '/lakaskereso/'],
  ['Virtuális lakásválasztó', '/virtualis-lakasvalaszto/'],
  ['Harmat Lakópark', '/harmat-lakopark/'],
  ['Magunkról', '/magunkrol/'],
  ['Környékünk', '/harmat-lakopark-kornyeke/'],
  ['Galéria', '/galeria/'],
  ['Építési napló', '/epitesi-naplo/'],
  ['Elérhetőségek', '/elerhetosegeink/'],
];

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

const browser = await chromium.launch({
  executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  headless: true,
});

try {
  for (const device of [
    { name: 'desktop', viewport: { width: 1440, height: 1000 } },
    { name: 'mobile', viewport: { width: 390, height: 844 } },
  ]) {
    const page = await browser.newPage({ viewport: device.viewport, locale: 'hu-HU' });
    const errors = [];
    page.on('pageerror', (error) => errors.push(String(error)));

    const response = await page.goto(`${origin}/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    assert(response?.status() === 200, `${device.name}: homepage HTTP ${response?.status()}.`);
    const necessaryCookies = page.getByRole('button', { name: 'Csak szükséges sütik' });
    if (await necessaryCookies.isVisible().catch(() => false)) {
      await necessaryCookies.click();
    }

    const trigger = page.locator('.elementor-element-4d7a363 a').first();
    await trigger.waitFor({ state: 'visible', timeout: 15000 });
    await trigger.click();
    const modal = page.locator('#harmat-clean-menu-modal');
    await modal.waitFor({ state: 'visible', timeout: 10000 });
    assert(await modal.getAttribute('aria-hidden') === 'false', `${device.name}: menu did not open.`);

    const items = await modal.locator('nav a').evaluateAll((links) => links.map((link) => [
      (link.textContent || '').trim(),
      new URL(link.href).pathname,
    ]));
    assert(JSON.stringify(items) === JSON.stringify(expected), `${device.name}: menu changed: ${JSON.stringify(items)}.`);
    assert(items.filter((item) => item[1] === '/epitesi-naplo/').length === 1, `${device.name}: construction link is duplicated.`);
    assert(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) <= 1, `${device.name}: open menu caused overflow.`);

    await modal.getByRole('link', { name: 'Építési napló', exact: true }).click();
    await page.waitForURL(`${origin}/epitesi-naplo/`, { timeout: 15000 });
    await page.locator('[data-harmat-construction-gallery="1"]').waitFor({ state: 'attached' });
    assert(await page.locator('h1').count() === 1, `${device.name}: construction page H1 changed.`);
    const unexpectedErrors = errors.filter((error) => !error.includes('player.pauseVideo is not a function'));
    assert(unexpectedErrors.length === 0, `${device.name}: page errors: ${unexpectedErrors.join(' | ')}`);

    await page.close();
    console.log(`${device.name.toUpperCase()}_MENU_PASS`);
  }
} finally {
  await browser.close();
}

console.log('CONSTRUCTION_MENU_LINK_BROWSER_TESTS_PASSED');
