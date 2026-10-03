import { createRequire } from 'node:module';
import { mkdir } from 'node:fs/promises';
import { join } from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const origin = 'https://harmat22.hu';
const chromePath = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const outputDir = join(process.cwd(), 'outputs', '2026-09-28-search-construction-park-qa');
const devices = [
  { name: 'desktop', viewport: { width: 1440, height: 900 } },
  { name: 'mobile', viewport: { width: 390, height: 844 } },
];

function check(condition, message) {
  if (!condition) throw new Error(message);
}

async function dismissCookies(page) {
  const button = page.getByRole('button', { name: 'Csak szükséges sütik' });
  if (await button.isVisible().catch(() => false)) await button.click();
}

async function visit(page, path) {
  const response = await page.goto(origin + path, { waitUntil: 'domcontentloaded', timeout: 30000 });
  check(response?.status() === 200, `${path}: HTTP ${response?.status()}`);
  await dismissCookies(page);
}

async function noOverflow(page, label) {
  const width = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  check(width <= 2, `${label}: ${width}px horizontal overflow`);
}

async function visibleCards(page) {
  return page.locator('[data-hm-lakas-page] [data-card]:visible').count();
}

async function resultCount(page) {
  return Number(await page.locator('[data-count]').innerText());
}

async function searchChecks(page, device) {
  await visit(page, '/lakaskereso/');
  check(await page.locator('.hm-lakas-stats').count() === 0, 'Search hero stats remain');
  const hero = page.locator('.hm-lakas-hero');
  check(await hero.locator('.hm-lakas-construction-link').count() === 1, 'Expected one construction link in hero');
  check(!/124\s+lakás|92\s+elérhető|Árak\s+lakásonként/i.test(await hero.innerText()), 'Old hero pills remain');
  const link = hero.locator('.hm-lakas-construction-link');
  check(new URL(await link.getAttribute('href')).pathname === '/epitesi-naplo/', 'Construction link target changed');
  await noOverflow(page, `${device} search`);

  const allCards = await page.locator('[data-hm-lakas-page] [data-card]').count();
  check(allCards > 0, 'Search cards missing');
  await page.waitForFunction(() => document.querySelector('[data-hm-lakas-page] [data-card]')?.dataset.originalOrder === '0', { timeout: 12000 });
  check(await resultCount(page) === allCards, 'Default result count does not include every card');
  check(await visibleCards(page) > 0, 'Default search shows no cards');
  const first = page.locator('[data-hm-lakas-page] [data-card]').first();
  const firstQuery = await first.getAttribute('data-query');
  const firstBuilding = await first.getAttribute('data-building');
  const statuses = await page.locator('[data-hm-lakas-page] [data-card]').evaluateAll(
    cards => [...new Set(cards.map(card => card.dataset.status))]);
  const restrictedStatus = statuses.find(status => status !== 'all');
  check(!!firstQuery && !!firstBuilding && !!restrictedStatus, 'Search fixture data incomplete');

  await page.locator(`[data-hm-filter] [data-status="${restrictedStatus}"]`).click();
  await page.waitForFunction(expected => Number(document.querySelector('[data-count]')?.textContent) === expected,
    await page.locator(`[data-hm-lakas-page] [data-card][data-status="${restrictedStatus}"]`).count());
  const statusCount = await resultCount(page);
  const statusActive = await page.locator(`[data-hm-filter] [data-status="${restrictedStatus}"].is-active`).count();
  check(statusCount > 0 && statusCount < allCards && statusActive === 1,
    `Status filter did not narrow results (status=${restrictedStatus}, count=${statusCount}, all=${allCards})`);
  check(await page.locator(`[data-hm-lakas-page] [data-card]:visible:not([data-status="${restrictedStatus}"])`).count() === 0, 'Status filter showed wrong cards');

  await page.locator('[data-hm-filter] [data-reset]').click();
  await page.locator('[data-hm-filter] [data-filter="building"]').selectOption(firstBuilding);
  const buildingCount = await resultCount(page);
  check(buildingCount > 0 && buildingCount < allCards, 'Building filter did not narrow results');
  check(await page.locator(`[data-hm-lakas-page] [data-card]:visible:not([data-building="${firstBuilding}"])`).count() === 0, 'Building filter showed wrong cards');

  await page.locator('[data-hm-filter] [data-reset]').click();
  await page.locator('[data-hm-filter] [data-filter="query"]').fill(firstQuery);
  check(await resultCount(page) >= 1 && await resultCount(page) < allCards, 'Query filter did not narrow results');
  await page.locator('[data-hm-filter] [data-reset]').click();
  check(await resultCount(page) === allCards, 'Reset did not restore results');
  check(await page.locator('[data-hm-filter] [data-filter="query"]').inputValue() === '', 'Reset left query text');
  check(await page.locator('[data-hm-filter] [data-filter="building"]').inputValue() === '', 'Reset left building selection');
  check(await page.locator('[data-hm-filter] [data-status="all"].is-active').count() === 1, 'Reset left status selected');
  check(Number(await page.locator('[data-count]').innerText()) === allCards, 'Result counter did not reset');

  await link.click();
  await page.waitForURL(url => url.pathname === '/epitesi-naplo/', { timeout: 15000 });
  check(new URL(page.url()).origin === origin, 'Construction link left the site');
  console.log(`${device}: search entry, status/building/query filters, reset, navigation PASS`);
}

async function constructionChecks(page, device, mp4Requests) {
  check(await page.locator('.harmat-info-hero').count() === 0, 'Old construction intro panel remains');
  check(await page.locator('h1').count() === 1, 'Construction page does not have exactly one H1');
  check(await page.locator('[data-harmat-construction-video="1"]').count() === 1, 'Main video missing');
  check(await page.locator('[data-harmat-construction-play]').count() === 1, 'Main video trigger missing');
  check(await page.locator('[data-harmat-construction-gallery="1"]').count() === 1, 'Gallery missing');
  const historicGallery = page.locator('[data-harmat-construction-gallery="1"]');
  check(await historicGallery.locator('[data-harmat-construction-photo]').count() === 16, 'Historical gallery is not 16 photos');
  const september = page.locator('[data-harmat-construction-september="1"]');
  if (await september.count()) {
    check(await september.count() === 1, 'September section duplicated');
    check(await september.locator('[data-harmat-construction-photo]').count() === 4, 'September gallery is not 4 photos');
    check(await page.locator('[data-harmat-construction-photo]').count() === 20, 'Combined galleries are not 20 photos');
  } else {
    check(await page.locator('[data-harmat-construction-photo]').count() === 16, 'Gallery is not 16 photos');
  }
  const thumbnails = historicGallery.locator('[data-harmat-construction-photo] img');
  check(await thumbnails.count() === 16, 'Gallery thumbnail count changed');
  check(await page.locator('[data-harmat-nearby-player]').count() === 1, 'Nearby video section missing');
  check(await page.locator('[data-harmat-nearby-player] video').count() === 0, 'Nearby video loaded before click');
  const poster = page.locator('[data-harmat-nearby-play] img');
  await poster.scrollIntoViewIfNeeded();
  await page.waitForFunction(() => {
    const image = document.querySelector('[data-harmat-nearby-play] img');
    return image?.complete && image.naturalWidth > 0;
  }, { timeout: 12000 });
  check(mp4Requests.length === 0, `MP4 requested before click: ${mp4Requests.join(', ')}`);
  const fallback = page.locator('.harmat-construction-nearby .harmat-construction-nearby-link a');
  check(await fallback.count() === 1, 'Nearby video fallback missing');
  const videoUrl = await page.locator('[data-harmat-nearby-player]').getAttribute('data-video-url');
  check(new URL(await fallback.getAttribute('href')).href === new URL(videoUrl).href, 'Fallback video URL differs');
  for (let index = 0; index < 16; index++) {
    await thumbnails.nth(index).scrollIntoViewIfNeeded();
    await thumbnails.nth(index).evaluate(image => new Promise((resolve, reject) => {
      if (image.complete) return image.naturalWidth > 0 ? resolve() : reject(new Error('Thumbnail failed'));
      image.addEventListener('load', resolve, { once: true });
      image.addEventListener('error', () => reject(new Error('Thumbnail failed')), { once: true });
    }));
  }
  check(mp4Requests.length === 0, 'MP4 requested during gallery scrolling');
  await noOverflow(page, `${device} construction`);
  await page.locator('.harmat-construction-nearby').evaluate(section => section.scrollIntoView({ block: 'center', behavior: 'instant' }));
  if (device === 'desktop') await page.evaluate(() => window.scrollBy({ top: -50, behavior: 'instant' }));
  await page.waitForFunction(() => {
    const section = document.querySelector('.harmat-construction-nearby');
    const heading = section?.querySelector('h2')?.getBoundingClientRect();
    const poster = section?.querySelector('[data-harmat-nearby-player]')?.getBoundingClientRect();
    return heading && poster && heading.top >= 100 && poster.bottom <= window.innerHeight - 20;
  }, null, { timeout: 5000 });
  await page.screenshot({ path: join(outputDir, `nearby-video-poster-${device}.png`), fullPage: false });

  await page.locator('[data-harmat-nearby-play]').click();
  const video = page.locator('[data-harmat-nearby-player] video');
  await video.waitFor({ state: 'attached', timeout: 5000 });
  await page.waitForFunction(() => {
    const media = document.querySelector('[data-harmat-nearby-player] video');
    if (media?.readyState >= 2 && media.videoWidth > 0) {
      media.pause();
      return true;
    }
    return false;
  }, { timeout: 15000 });
  check(mp4Requests.some(url => new URL(url).href === new URL(videoUrl).href), 'Click did not fetch nearby MP4');
  check(await video.evaluate(media => media.controls && media.paused && media.videoWidth > 0), 'Native video did not decode/pause');
  await page.screenshot({ path: join(outputDir, `nearby-video-first-frame-${device}.png`), fullPage: false });

  await historicGallery.locator('[data-harmat-construction-photo]').first().click();
  const lightbox = page.locator('[data-harmat-construction-lightbox]');
  check(await lightbox.evaluate(dialog => dialog.open), 'Gallery lightbox did not open');
  await page.locator('[data-harmat-construction-next]').click();
  check(await page.locator('[data-harmat-construction-lightbox-image]').getAttribute('src') !== '', 'Lightbox next image missing');
  await page.locator('[data-harmat-construction-close]').click();
  check(!await lightbox.evaluate(dialog => dialog.open), 'Gallery lightbox did not close');
  await noOverflow(page, `${device} construction after interaction`);
  console.log(`${device}: construction heading, video, gallery, lightbox, poster, lazy MP4 PASS`);
}

await mkdir(outputDir, { recursive: true });
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const failures = [];
try {
  for (const device of devices) {
    const context = await browser.newContext({ viewport: device.viewport, locale: 'hu-HU', serviceWorkers: 'block' });
    // Block every off-origin request (including tracking) and all write methods.
    await context.route('**/*', async route => {
      const request = route.request();
      const url = new URL(request.url());
      if (url.origin !== origin || !['GET', 'HEAD', 'OPTIONS'].includes(request.method())
          || /\/harmat-local-assistant\/v1\/event(?:[/?]|$)|\/wp-admin\/(?:admin-ajax|admin-post)\.php/i.test(url.pathname)) {
        return route.abort('blockedbyclient');
      }
      return route.continue();
    });
    const page = await context.newPage();
    const pageErrors = [];
    const mp4Requests = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    page.on('request', request => {
      if (/\.mp4(?:[?#]|$)/i.test(request.url())) mp4Requests.push(request.url());
    });
    try {
      await searchChecks(page, device.name);
      await constructionChecks(page, device.name, mp4Requests);
      const financePage = await context.newPage();
      financePage.on('pageerror', error => pageErrors.push(error.message));
      const financeResponse = await financePage.goto(origin + '/finanszirozas/', { waitUntil: 'commit', timeout: 15000 });
      check(financeResponse?.status() === 200, `Finance page: HTTP ${financeResponse?.status()}`);
      await financePage.locator('.harmat-info-hero').waitFor({ state: 'attached', timeout: 8000 });
      check(await financePage.locator('.harmat-info-hero').count() === 1, 'Finance intro was removed');
      await noOverflow(financePage, `${device.name} finance`);
      await financePage.close();
      check(pageErrors.length === 0, `${device.name}: browser errors: ${pageErrors.join(' | ')}`);
      console.log(`${device.name}: finance intro, no page errors PASS`);
    } catch (error) {
      failures.push(`${device.name}: ${error.message}`);
      await page.screenshot({ path: join(outputDir, `failure-${device.name}.png`), fullPage: true }).catch(() => {});
      console.error(`FAIL ${device.name}: ${error.message}; pageErrors=${pageErrors.join(' | ') || 'none'}`);
    } finally {
      await context.close();
    }
  }
} finally {
  await browser.close();
}

if (failures.length) {
  console.error(`LIVE_QA_FAILED (${failures.length}): ${failures.join(' | ')}`);
  process.exitCode = 1;
} else {
  console.log('LIVE_QA_PASSED');
}
