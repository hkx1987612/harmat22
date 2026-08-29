import fs from 'node:fs/promises';
import { createRequire } from 'node:module';
import path from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const outputDir = path.resolve('outputs/construction-video');
const url = 'https://harmat22.hu/epitesi-naplo/';

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

await fs.mkdir(outputDir, { recursive: true });
const browser = await chromium.launch({ executablePath: chromePath, headless: true });

try {
  for (const test of [
    { name: 'desktop', viewport: { width: 1440, height: 1000 } },
    { name: 'mobile', viewport: { width: 390, height: 844 } },
  ]) {
    const page = await browser.newPage({ viewport: test.viewport, locale: 'hu-HU' });
    const errors = [];
    const galleryRequests = [];
    page.on('pageerror', (error) => errors.push(String(error)));
    page.on('request', (request) => {
      if (request.url().includes('/construction-progress/')) galleryRequests.push(request.url());
    });

    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    assert(response?.status() === 200, `${test.name}: page returned ${response?.status()}.`);
    await page.waitForSelector('[data-harmat-construction-video="1"]', { timeout: 15000 });
    const necessaryCookies = page.getByRole('button', { name: 'Csak szükséges sütik' });
    if (await necessaryCookies.isVisible().catch(() => false)) {
      await necessaryCookies.click();
    }

    const audit = await page.evaluate(() => {
      const html = document.documentElement;
      const feature = document.querySelector('[data-harmat-construction-video="1"]');
      const heading = document.querySelector('.harmat-construction-feature-head');
      const player = document.querySelector('[data-harmat-construction-player]');
      const poster = document.querySelector('.harmat-construction-trigger img');
      const gallery = document.querySelector('[data-harmat-construction-gallery="1"]');
      const photos = [...document.querySelectorAll('[data-harmat-construction-photo]')];
      const list = document.querySelector('.harmat-build-log-list');
      const schemas = [...document.querySelectorAll('script[type="application/ld+json"]')]
        .map((node) => {
          try { return JSON.parse(node.textContent || ''); } catch { return null; }
        });
      const featureRect = feature?.getBoundingClientRect();
      const headingRect = heading?.getBoundingClientRect();
      const playerRect = player?.getBoundingClientRect();
      const galleryRect = gallery?.getBoundingClientRect();
      const listRect = list?.getBoundingClientRect();

      return {
        title: document.title,
        description: document.querySelector('meta[name="description"]')?.content || '',
        canonical: document.querySelector('link[rel="canonical"]')?.href || '',
        h1: [...document.querySelectorAll('h1')].map((node) => (node.textContent || '').trim()),
        markerCount: document.querySelectorAll('[data-harmat-construction-video="1"]').length,
        galleryCount: document.querySelectorAll('[data-harmat-construction-gallery="1"]').length,
        photoCount: photos.length,
        lightboxOpen: document.querySelector('[data-harmat-construction-lightbox]')?.hasAttribute('open') || false,
        iframeCount: feature?.querySelectorAll('iframe').length || 0,
        poster: {
          complete: poster?.complete || false,
          width: poster?.naturalWidth || 0,
          height: poster?.naturalHeight || 0,
        },
        overflow: html.scrollWidth - html.clientWidth,
        bodyText: document.body?.innerText || '',
        schemaText: JSON.stringify(schemas),
        layout: {
          featureWidth: featureRect?.width || 0,
          headingBottom: headingRect?.bottom || 0,
          playerTop: playerRect?.top || 0,
          playerWidth: playerRect?.width || 0,
          playerHeight: playerRect?.height || 0,
          playerBottom: playerRect?.bottom || 0,
          galleryTop: galleryRect?.top || 0,
          galleryBottom: galleryRect?.bottom || 0,
          listTop: listRect?.top || 0,
        },
      };
    });

    assert(audit.title === 'Építési napló | Harmat Lakópark', `${test.name}: title changed.`);
    assert(audit.description.includes('fényképes idővonal'), `${test.name}: description is stale.`);
    assert(audit.canonical === url, `${test.name}: canonical is incorrect.`);
    assert(audit.h1.length === 1 && audit.h1[0] === 'Építési napló', `${test.name}: H1 is incorrect.`);
    assert(audit.markerCount === 1, `${test.name}: video module count is incorrect.`);
    assert(audit.galleryCount === 1, `${test.name}: gallery module count is incorrect.`);
    assert(audit.photoCount === 16, `${test.name}: gallery photo count is ${audit.photoCount}.`);
    assert(!audit.lightboxOpen, `${test.name}: gallery lightbox opened without interaction.`);
    assert(audit.iframeCount === 0, `${test.name}: YouTube loaded before interaction.`);
    assert(audit.poster.complete && audit.poster.width === 1280 && audit.poster.height === 720, `${test.name}: poster failed.`);
    assert(audit.overflow <= 1, `${test.name}: horizontal overflow is ${audit.overflow}px.`);
    assert(!/[\u3400-\u9fff]/u.test(audit.bodyText), `${test.name}: public Chinese text detected.`);
    assert(!/lorem|placeholder|undefined|null/iu.test(audit.bodyText), `${test.name}: placeholder text detected.`);
    assert(audit.schemaText.includes('VideoObject') && audit.schemaText.includes('HMgnTfeuQYM'), `${test.name}: VideoObject is missing.`);
    assert(audit.schemaText.includes('ImageGallery') && audit.schemaText.includes('2026-08-26-tomoritett-agyazat-1920.webp'), `${test.name}: ImageGallery is missing.`);
    assert(audit.schemaText.includes('"uploadDate":"2026-08-28"'), `${test.name}: VideoObject publication date is incorrect.`);
    assert(audit.layout.featureWidth > 0, `${test.name}: video feature is hidden.`);
    assert(audit.layout.headingBottom <= audit.layout.playerTop + 1, `${test.name}: heading overlaps video.`);
    assert(Math.abs(audit.layout.playerWidth / audit.layout.playerHeight - 16 / 9) < 0.03, `${test.name}: video ratio shifted.`);
    assert(audit.layout.playerBottom <= audit.layout.galleryTop, `${test.name}: video overlaps the photo timeline.`);
    assert(audit.layout.galleryBottom <= audit.layout.listTop, `${test.name}: photo timeline overlaps the project log.`);
    assert(errors.length === 0, `${test.name}: page errors: ${errors.join(' | ')}`);

    assert(!galleryRequests.some((requestUrl) => requestUrl.includes('-1920.webp')), `${test.name}: full-size images loaded before interaction.`);
    await page.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 500) {
        scrollTo(0, y);
        await new Promise((resolve) => setTimeout(resolve, 35));
      }
      scrollTo(0, 0);
    });
    await page.waitForFunction(() => [...document.querySelectorAll('[data-harmat-construction-photo] img')]
      .every((image) => image.complete && image.naturalWidth === 960 && image.naturalHeight === 720));
    assert(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) <= 1, `${test.name}: gallery scrolling caused overflow.`);

    await page.screenshot({ path: path.join(outputDir, `epitesi-naplo-${test.name}.png`), fullPage: true });

    if (test.name === 'desktop') {
      await page.locator('[data-harmat-construction-photo]').first().click();
      const lightbox = page.locator('[data-harmat-construction-lightbox]');
      await lightbox.waitFor({ state: 'visible' });
      const fullImage = lightbox.locator('[data-harmat-construction-lightbox-image]');
      await page.waitForFunction(() => {
        const image = document.querySelector('[data-harmat-construction-lightbox-image]');
        return image?.complete && image.naturalWidth > 1000;
      });
      assert((await fullImage.getAttribute('src'))?.includes('2026-08-26-tomoritett-agyazat-1920.webp'), 'desktop: wrong lightbox image.');
      await lightbox.locator('[data-harmat-construction-next]').click();
      assert((await fullImage.getAttribute('src'))?.includes('2026-08-25-helyszini-szemle-1920.webp'), 'desktop: lightbox navigation failed.');
      await lightbox.locator('[data-harmat-construction-close]').click();
      assert(!await lightbox.getAttribute('open'), 'desktop: lightbox did not close.');
    } else {
      await page.getByRole('button', { name: 'A 2026. augusztusi építési videó lejátszása' }).click();
      const frame = page.locator('[data-harmat-construction-player] iframe');
      await frame.waitFor({ state: 'visible', timeout: 10000 });
      assert((await frame.getAttribute('src'))?.includes('youtube-nocookie.com/embed/HMgnTfeuQYM'), 'mobile: wrong video embed.');
      assert(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) <= 1, 'mobile: playback caused overflow.');
      await page.screenshot({ path: path.join(outputDir, 'epitesi-naplo-mobile-playing.png'), fullPage: true });
    }

    await page.close();
    console.log(`${test.name.toUpperCase()}_PASS`);
  }
} finally {
  await browser.close();
}

console.log('CONSTRUCTION_PAGE_BROWSER_TESTS_PASSED');
