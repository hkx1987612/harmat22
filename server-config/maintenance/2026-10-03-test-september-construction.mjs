import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const repo = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const args = process.argv.slice(2);
// Live QA is opt-in; loopback mode can exercise the existing PHP/media harness.
assert(args.length === 1 && args[0] === '--live'
  || args.length === 2 && args[0] === '--local-url',
'Usage: node server-config/maintenance/2026-10-03-test-september-construction.mjs --live | --local-url http://127.0.0.1:8765/epitesi-naplo/');
const live = args[0] === '--live';
const target = new URL(live ? 'https://harmat22.hu/epitesi-naplo/' : args[1]);
assert(live || target.protocol === 'http:' && ['127.0.0.1', 'localhost', '[::1]'].includes(target.hostname), 'Local URL must be loopback HTTP');
const outputDir = join(repo, 'outputs', `2026-10-03-september-construction-${live ? 'live' : 'local'}-qa`);
const septemberSelector = '[data-harmat-construction-september="1"]';
const historicSelector = '[data-harmat-construction-gallery="1"]';
const playerSelector = '[data-harmat-september-player]';
const mp4Pattern = /\.mp4(?:[?#]|$)/i;
const fullPattern = /-1920\.webp(?:[?#]|$)/i;
const expectedClips = [
  ['2026-09-21-a1-a2', '2026-09', 'A1', 'A2'],
  ['2026-09-22-a2', '2026-09-22', 'A2'],
  ['2026-09-23-a1', '2026-09-23', 'A1'],
  ['2026-09-24-a3', '2026-09-24', 'A3'],
  ['2026-09-25-a1', '2026-09-25', 'A1'],
  ['2026-09-25-a2', '2026-09-25', 'A2'],
  ['2026-09-25-a4', '2026-09-25', 'A4'],
];

async function noOverflow(page, result, phase) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  result.overflow.push({ phase, pixels: overflow });
  assert(overflow <= 2, `${phase}: ${overflow}px horizontal overflow`);
}

async function captureViewport(page, result, filename, top) {
  await page.evaluate(async y => {
    window.scrollTo({ top: y, left: 0, behavior: 'instant' });
    await new Promise(resolvePaint => requestAnimationFrame(() => requestAnimationFrame(resolvePaint)));
  }, top);
  const state = await page.evaluate(() => ({ scrollY: window.scrollY, width: window.innerWidth, height: window.innerHeight }));
  if (top === 0) assert.equal(state.scrollY, 0, 'Top viewport screenshot must be at scrollY=0');
  await page.screenshot({ path: join(outputDir, filename), fullPage: false });
  result.viewportScreenshots.push({ filename, ...state });
}

async function decodeImage(image) {
  await image.scrollIntoViewIfNeeded();
  await image.evaluate(async node => {
    // Lazy loading starts on a rendering tick, not necessarily at scroll return.
    const deadline = Date.now() + 15000;
    while (!node.complete || !node.naturalWidth) {
      if (Date.now() > deadline) throw new Error(`Image did not load: ${node.currentSrc || node.src}`);
      await new Promise(resolveLoad => setTimeout(resolveLoad, 50));
    }
    await node.decode();
  });
}

async function loadedImage(image, width) {
  await decodeImage(image);
  const state = await image.evaluate(node => ({ width: node.naturalWidth, height: node.naturalHeight, alt: node.alt }));
  assert.equal(state.width, width, 'Image decoded at wrong width');
  assert.equal(state.height, width * 3 / 4, 'Photo aspect ratio changed');
  assert(state.alt.trim(), 'Photo alt text missing');
  return state;
}

async function lightboxChecks(page, buttons, result, label) {
  const dialog = page.locator('[data-harmat-construction-lightbox]');
  const image = dialog.locator('[data-harmat-construction-lightbox-image]');
  await buttons.first().click();
  await dialog.waitFor({ state: 'visible' });
  assert(await dialog.evaluate(node => node.open), 'Lightbox did not open');
  assert(await dialog.locator('[data-harmat-construction-close]').evaluate(node => node === document.activeElement), 'Lightbox close control not focused');
  for (let index = 0; index < 2; index++) {
    if (index) await dialog.locator('[data-harmat-construction-next]').click();
    assert.equal(await image.getAttribute('src'), await buttons.nth(index).getAttribute('data-full'), 'Lightbox next chose wrong photo');
    await loadedImage(image, 1920);
    assert.equal(await image.getAttribute('alt'), await buttons.nth(index).getAttribute('data-alt'), 'Lightbox alt changed');
    assert.equal(await dialog.locator('[data-harmat-construction-lightbox-caption]').textContent(), await buttons.nth(index).getAttribute('data-caption'), 'Lightbox caption changed');
  }
  await page.keyboard.press('ArrowLeft');
  assert.equal(await image.getAttribute('src'), await buttons.first().getAttribute('data-full'), 'Keyboard previous failed');
  await loadedImage(image, 1920);
  await page.screenshot({ path: join(outputDir, `${result.device}-${label}-lightbox.png`) });
  await noOverflow(page, result, `${label} lightbox`);
  await page.keyboard.press('Escape');
  assert(!await dialog.evaluate(node => node.open), 'Escape did not close lightbox');
  assert(await buttons.first().evaluate(node => node === document.activeElement), 'Lightbox did not restore trigger focus');
  assert.equal(await image.getAttribute('src'), null, 'Closed lightbox retained full-size source');
  result.lightboxes.push(`${label}: open, next, keyboard previous, Escape, restored focus`);
}

async function videoChecks(player, trigger, page, result, label) {
  const source = await player.getAttribute('data-video-url');
  await trigger.click();
  const video = player.locator('video');
  await video.waitFor({ state: 'visible' });
  await page.waitForFunction(selector => {
    const media = document.querySelector(selector);
    return media && media.readyState >= 2 && media.videoWidth > 0 && media.currentTime > 0.3 && !media.paused;
  }, `${label === 'nearby' ? '[data-harmat-nearby-player]' : `${septemberSelector} ${label === 'overview' ? '> [data-harmat-september-player]' : '.harmat-construction-clip-grid [data-harmat-september-player]'}`} video`, { timeout: 30000 });
  const sample = () => video.evaluate(media => {
    const canvas = document.createElement('canvas');
    canvas.width = 64; canvas.height = 36;
    const context = canvas.getContext('2d');
    context.drawImage(media, 0, 0, 64, 36);
    const pixels = Array.from(context.getImageData(0, 0, 64, 36).data);
    const colors = new Set();
    let nonBlack = 0;
    for (let index = 0; index < pixels.length; index += 4) {
      colors.add(`${pixels[index]},${pixels[index + 1]},${pixels[index + 2]}`);
      if (pixels[index] + pixels[index + 1] + pixels[index + 2] > 24) nonBlack++;
    }
    return { width: media.videoWidth, height: media.videoHeight, time: media.currentTime,
      duration: media.duration, controls: media.controls, inline: media.playsInline,
      preload: media.preload, source: media.currentSrc, colors: colors.size, nonBlack, pixels,
      decodedFrames: media.getVideoPlaybackQuality().totalVideoFrames };
  });
  const first = await sample();
  await video.evaluate(async media => {
    const start = media.currentTime;
    await new Promise((resolveFrame, reject) => {
      const timer = setTimeout(() => reject(new Error('Video stopped advancing')), 10000);
      function tick() {
        if (media.currentTime > start + 0.5) { clearTimeout(timer); resolveFrame(); }
        else requestAnimationFrame(tick);
      }
      tick();
    });
  });
  const second = await sample();
  await video.evaluate(media => media.pause());
  const delta = first.pixels.reduce((sum, value, index) => sum + Math.abs(value - second.pixels[index]), 0) / first.pixels.length;
  for (const frame of [first, second]) {
    assert.equal(frame.width, 1280, `${label}: video width changed`);
    assert.equal(frame.height, 720, `${label}: video height changed`);
    assert(frame.controls && frame.inline && frame.preload === 'none', `${label}: native controls/inline/lazy attributes changed`);
    assert.equal(frame.source, source, `${label}: video source changed`);
    assert(frame.colors > 50 && frame.nonBlack > 1000, `${label}: decoded pixels blank or uniform`);
  }
  assert(second.time > first.time + 0.4 && second.decodedFrames > first.decodedFrames, `${label}: frames did not advance`);
  assert(delta > 0.1, `${label}: decoded frame pixels did not change (${delta})`);
  if (label === 'overview') assert(Math.abs(second.duration - 32) < 1, 'Overview is not the 32-second derivative');
  assert(result.mp4Requests.includes(source), `${label}: click did not request its MP4`);
  const { pixels: ignored, ...state } = second;
  result.videos.push({ label, ...state, pixelDelta: delta });
  await player.screenshot({ path: join(outputDir, `${result.device}-${label}-decoded.png`) });
  await noOverflow(page, result, `${label} decoded`);
}

async function constructionChecks(page, result) {
  const response = await page.goto(target.href, { waitUntil: 'domcontentloaded', timeout: 45000 });
  assert.equal(response?.status(), 200, 'Construction page HTTP status');
  const september = page.locator(septemberSelector);
  await september.waitFor({ state: 'visible', timeout: 15000 });
  assert.equal(await september.count(), 1, 'September marker missing or duplicated; confirm parent deployment first');
  const cookies = page.getByRole('button', { name: 'Csak sz\u00fcks\u00e9ges s\u00fctik' });
  if (await cookies.isVisible().catch(() => false)) await cookies.click();
  assert.equal(await september.locator('header time').getAttribute('datetime'), '2026-09-30', 'Progress cutoff date changed');
  assert.equal(await september.locator('.harmat-construction-september-duration time').getAttribute('datetime'), '2026-10-02', 'Overview must disclose actual October 2 footage date');
  assert.match(await september.locator('.harmat-construction-september-duration').innerText(), /32.*\n.*szeptember 30-i/iu, 'Overview duration/supplement disclosure missing');
  const progress = await september.locator('.harmat-construction-progress-rows > div').evaluateAll(rows => rows.map(row => ({ building: row.querySelector('dt').textContent, status: row.querySelector('dd').textContent })));
  assert.deepEqual(progress.map(row => row.building.slice(0, 2)), ['A1', 'A2', 'A3', 'A4'], 'Building progress rows changed');
  assert.match(progress[0].status, /betonoz\u00e1sa elk\u00e9sz\u00fclt.*30%/u, 'A1 completed walls/pillars or 30% formwork changed');
  assert.match(progress[1].status, /betonoz\u00e1sa 80%.*zsaluz\u00e1sa 20%/u, 'A2 80% concrete / 20% formwork changed');
  assert.match(progress[2].status, /alaplemez vasal\u00e1sa 40%/iu, 'A3 slab reinforcement 40% changed');
  assert.match(progress[3].status, /alaplemez betonoz\u00e1sa elk\u00e9sz\u00fclt.*falak \u00e9s pill\u00e9rek vasal\u00e1sa folyamatban/iu, 'A4 completed slab / ongoing walls/pillars reinforcement changed');
  result.progress = progress;
  const players = september.locator(playerSelector);
  const clips = september.locator('.harmat-construction-september-clips');
  assert.equal(await players.count(), 8, 'Expected overview plus seven September players');
  assert.equal(await september.locator('[data-harmat-september-play]').count(), 8, 'Expected eight click-to-load controls');
  assert(!await clips.evaluate(node => node.open), 'Seven clips must start collapsed');
  assert.equal(await clips.locator('article').count(), 7, 'Collapsed clip count changed');
  assert.equal(await september.locator('video,iframe').count(), 0, 'September media instantiated before click');
  const urls = await players.evaluateAll(nodes => nodes.map(node => node.dataset.videoUrl));
  assert.equal(new Set(urls).size, 8, 'September player URLs duplicated');
  for (let index = 0; index < 8; index++) {
    const slug = index ? expectedClips[index - 1][0] : '2026-09-overview';
    assert.equal(urls[index], `${target.origin}/wp-content/uploads/2026/09/construction-september/${slug}.mp4`, 'Unexpected September media URL');
    const player = players.nth(index);
    assert.equal(await player.getAttribute('data-poster-url'), urls[index].replace(/\.mp4$/, '.jpg'), 'Poster URL differs from video');
    assert(await player.locator('button').getAttribute('aria-label'), 'Video button missing accessible name');
    assert.equal(await september.locator(`a[href="${urls[index]}"]`).count(), 1, 'Direct MP4 fallback missing/duplicated');
    if (index) {
      const article = clips.locator('article').nth(index - 1);
      assert.equal(await article.locator('time').getAttribute('datetime'), expectedClips[index - 1][1], 'Clip date changed');
      for (const building of expectedClips[index - 1].slice(2)) assert((await article.locator('h3').textContent()).includes(building), 'Clip building label changed');
    }
  }

  const historic = page.locator(historicSelector);
  const oldVideo = page.locator('[data-harmat-construction-video="1"]');
  const nearby = page.locator('[data-harmat-nearby-player]');
  assert.equal(await historic.count(), 1, 'Historical gallery missing');
  assert.equal(await oldVideo.count(), 1, 'Original August video missing');
  assert.equal(await oldVideo.locator('[data-harmat-construction-play]').getAttribute('data-video-id'), 'HMgnTfeuQYM', 'Original August YouTube ID changed');
  assert.equal(await oldVideo.locator('time').getAttribute('datetime'), '2026-08', 'August video label changed');
  assert.equal(await oldVideo.locator('iframe').count(), 0, 'August iframe loaded before click');
  assert.equal(await nearby.count(), 1, 'Nearby video missing');
  assert.equal(await nearby.getAttribute('data-video-url'), `${target.origin}/wp-content/uploads/2026/09/harmat-kornyek-kutyapark-2026-09.mp4`, 'Nearby video URL changed');
  assert.equal(await nearby.locator('video').count(), 0, 'Nearby video loaded early');
  for (const image of [players.first().locator('img'), oldVideo.locator('img'), nearby.locator('img')]) {
    await decodeImage(image);
    assert.deepEqual(await image.evaluate(node => [node.naturalWidth, node.naturalHeight]), [1280, 720], 'Overview/August/nearby poster failed');
  }
  assert.equal(await september.locator('[data-harmat-construction-photo]').count(), 4, 'New photo count changed');
  assert.equal(await historic.locator('[data-harmat-construction-photo]').count(), 16, 'Historical photo count changed');
  assert.equal(await page.locator('[data-harmat-construction-photo]').count(), 20, 'Combined photo count changed');
  for (let index = 0; index < 4; index++) {
    assert.equal(await september.locator('[data-harmat-construction-photo]').nth(index).getAttribute('data-full'), `${target.origin}/wp-content/uploads/2026/09/construction-september/harmat-2026-09-site-0${index + 1}-1920.webp`, 'September photo source changed');
  }
  assert.equal(await page.locator('[data-harmat-construction-lightbox]').count(), 1, 'Shared lightbox missing or duplicated');
  assert.equal(await page.locator('.harmat-info-hero').count(), 0, 'Removed introduction returned');

  const seo = await page.evaluate(() => ({ title: document.title,
    lang: document.documentElement.lang, text: document.body.innerText,
    h1: [...document.querySelectorAll('h1')].map(node => node.textContent.trim()),
    canonical: document.querySelector('link[rel="canonical"]')?.href,
    robots: document.querySelector('meta[name="robots"]')?.content || '',
    description: document.querySelector('meta[name="description"]')?.content,
    ogDescription: document.querySelector('meta[property="og:description"]')?.content,
    twitterDescription: document.querySelector('meta[name="twitter:description"]')?.content,
    ogImage: document.querySelector('meta[property="og:image"]')?.content,
    twitterImage: document.querySelector('meta[name="twitter:image"]')?.content,
    schemas: [...document.querySelectorAll('script[type="application/ld+json"]')].map(node => JSON.parse(node.textContent)),
  }));
  assert.equal(seo.title, '\u00c9p\u00edt\u00e9si napl\u00f3 | Harmat Lak\u00f3park', 'SEO title changed');
  assert.deepEqual(seo.h1, ['\u00c9p\u00edt\u00e9si napl\u00f3'], 'Expected exactly one construction H1');
  assert.match(seo.lang, /^hu/i, 'Public language changed');
  assert.equal(seo.canonical, `${target.origin}/epitesi-naplo/`, 'Canonical changed');
  assert(!/noindex/i.test(seo.robots), 'Construction page became noindex');
  for (const field of ['description', 'ogDescription', 'twitterDescription']) assert.match(seo[field] || '', /2026\. szeptember 30-i.*vide\u00f3k.*fot\u00f3k/iu, `${field} is stale`);
  for (const field of ['ogImage', 'twitterImage']) assert.equal(seo[field], urls[0].replace(/\.mp4$/, '.jpg'), `${field} did not use overview poster`);
  assert(!/[\u3400-\u9fff]|Fatal error|Parse error|lorem ipsum|placeholder|\uFFFD/iu.test(seo.text), 'Public text contains errors or placeholders');
  const nodes = seo.schemas.flatMap(schema => schema['@graph'] || (Array.isArray(schema) ? schema : [schema]));
  const node = suffix => {
    const found = nodes.filter(item => item['@id'] === `${target.origin}/epitesi-naplo/#${suffix}`);
    assert.equal(found.length, 1, `Expected one ${suffix} schema node`);
    return found[0];
  };
  const overviewSchema = node('construction-september-video');
  assert.equal(overviewSchema['@type'], 'VideoObject');
  assert.equal(overviewSchema.dateCreated, '2026-10-02', 'Overview schema creation date must be October 2');
  assert.equal(overviewSchema.uploadDate, '2026-10-03', 'Overview publication date changed');
  assert.equal(overviewSchema.duration, 'PT32S');
  assert.equal(overviewSchema.contentUrl, urls[0]);
  assert.equal(overviewSchema.thumbnailUrl, urls[0].replace(/\.mp4$/, '.jpg'));
  assert.match(overviewSchema.name, /2026\. okt\u00f3ber 2-i/u, 'Schema name incorrectly calls October footage September');
  assert.match(overviewSchema.description, /okt\u00f3ber 2-i.*szeptember 30-i/u, 'Schema date/cutoff disclosure missing');
  assert.equal(overviewSchema.isPartOf?.['@id'], `${target.origin}/epitesi-naplo/`);
  assert.equal(overviewSchema.publisher?.['@id'], `${target.origin}/#organization`);
  const augustSchema = node('construction-video');
  assert.equal(augustSchema.uploadDate, '2026-08-28');
  assert.equal(augustSchema.duration, 'PT1M31S');
  assert.match(augustSchema.contentUrl, /HMgnTfeuQYM/);
  const gallerySchema = node('construction-gallery');
  assert.equal(gallerySchema['@type'], 'ImageGallery');
  assert.equal(gallerySchema.image.length, 16, 'Historical schema photo count changed');
  assert.deepEqual(gallerySchema.image.map(image => image.contentUrl), await historic.locator('[data-harmat-construction-photo]').evaluateAll(buttons => buttons.map(button => button.dataset.full)), 'Historical schema images differ from gallery');
  result.seo = { ...seo, text: undefined, schemas: undefined, overviewSchema, augustSchema, historicSchemaImages: gallerySchema.image.length };

  await noOverflow(page, result, 'initial');
  await captureViewport(page, result, `${result.device}-initial.png`, 0);
  const thumbnails = page.locator('[data-harmat-construction-photo] img');
  result.photos = [];
  for (let index = 0; index < 20; index++) {
    assert.equal(await thumbnails.nth(index).getAttribute('loading'), 'lazy', 'Thumbnail no longer lazy');
    result.photos.push(await loadedImage(thumbnails.nth(index), 960));
    await noOverflow(page, result, `thumbnail ${index + 1}`);
  }
  assert.equal(result.mp4Requests.length, 0, `MP4 requested before click: ${result.mp4Requests.join(', ')}`);
  assert.equal(result.fullImageRequests.length, 0, '1920px images requested before lightbox interaction');
  await clips.locator('summary').click();
  assert(await clips.evaluate(node => node.open), 'Clip disclosure did not open');
  for (const image of await clips.locator('[data-harmat-september-play] img').all()) {
    await decodeImage(image);
    assert.deepEqual(await image.evaluate(node => [node.naturalWidth, node.naturalHeight]), [640, 360], 'Clip poster failed to decode');
  }
  await noOverflow(page, result, 'seven clips expanded without playback');
  assert.equal(result.mp4Requests.length, 0, 'Expanding clips fetched MP4s');
  assert.equal(result.fullImageRequests.length, 0, 'Expanding clips fetched full photos');
  result.beforeClick = { septemberAndNearbyMp4Requests: 0, full1920ImageRequests: 0, loadedPhotos: 20, decodedClipPosters: 7 };
  await clips.locator('summary').click();
  const reportTop = await september.evaluate(node => Math.max(0, node.getBoundingClientRect().top + window.scrollY - 110));
  await captureViewport(page, result, `${result.device}-september-viewport.png`, reportTop);
  await lightboxChecks(page, september.locator('[data-harmat-construction-photo]'), result, 'september');
  await lightboxChecks(page, historic.locator('[data-harmat-construction-photo]'), result, 'historic');
  await videoChecks(players.first(), players.first().locator('[data-harmat-september-play]'), page, result, 'overview');
  await clips.locator('summary').click();
  await videoChecks(players.nth(1), players.nth(1).locator('[data-harmat-september-play]'), page, result, 'clip');
  assert.equal(await september.locator('video').count(), 2, 'Untouched September clips instantiated videos');
  assert.deepEqual([...new Set(result.mp4Requests)], urls.slice(0, 2), 'Unclicked MP4s were fetched');
  await videoChecks(nearby, nearby.locator('[data-harmat-nearby-play]'), page, result, 'nearby');
  await oldVideo.locator('[data-harmat-construction-play]').click();
  const frame = oldVideo.locator('iframe');
  await frame.waitFor({ state: 'visible' });
  assert.match(await frame.getAttribute('src'), /^https:\/\/www\.youtube-nocookie\.com\/embed\/HMgnTfeuQYM\?/);
  assert(result.blockedExternal.some(url => url.includes('youtube-nocookie.com/embed/HMgnTfeuQYM')), 'YouTube external request was not intercepted');
  result.august = 'Original click-to-load embed preserved; external playback deliberately blocked';
  await noOverflow(page, result, 'all interactions');
  assert.deepEqual(result.pageErrors, [], 'Browser JavaScript errors');
}

await mkdir(outputDir, { recursive: true });
const report = { mode: live ? 'live-after-parent-deployment' : 'isolated-local-harness-not-live-evidence', url: target.href, startedAt: new Date().toISOString(), results: [] };
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
  for (const device of [
    { name: 'desktop', viewport: { width: 1440, height: 900 } },
    { name: 'mobile', viewport: { width: 390, height: 844 } },
  ]) {
    const result = { device: device.name, viewport: device.viewport, passed: false, pageErrors: [], mp4Requests: [], fullImageRequests: [], blockedExternal: [], blockedWrites: [], blockedTelemetry: [], overflow: [], lightboxes: [], videos: [], viewportScreenshots: [] };
    report.results.push(result);
    const context = await browser.newContext({ viewport: device.viewport, locale: 'hu-HU', serviceWorkers: 'block' });
    await context.route('**/*', async route => {
      const request = route.request();
      const url = new URL(request.url());
      if (url.origin !== target.origin) {
        result.blockedExternal.push(url.href);
        return route.abort('blockedbyclient');
      }
      if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) {
        result.blockedWrites.push({ method: request.method(), url: url.href });
        return route.abort('blockedbyclient');
      }
      if (/\/harmat-local-assistant\/v1\/event(?:[/?]|$)|\/wp-admin\/(?:admin-ajax|admin-post)\.php/i.test(url.pathname)) {
        result.blockedTelemetry.push(url.href);
        return route.abort('blockedbyclient');
      }
      return route.continue();
    });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('pageerror', error => result.pageErrors.push(error.message));
    page.on('request', request => {
      if (mp4Pattern.test(request.url())) result.mp4Requests.push(request.url());
      if (fullPattern.test(request.url())) result.fullImageRequests.push(request.url());
    });
    try {
      await constructionChecks(page, result);
      result.passed = true;
      console.log(`${device.name.toUpperCase()} SEPTEMBER_CONSTRUCTION_PASS`);
    } catch (error) {
      result.failure = error.stack || error.message;
      await page.screenshot({ path: join(outputDir, `${device.name}-failure.png`), fullPage: true }).catch(() => {});
      console.error(`${device.name.toUpperCase()} FAIL: ${error.message}`);
    } finally {
      await context.close();
    }
  }
} finally {
  await browser.close();
  report.finishedAt = new Date().toISOString();
  report.passed = report.results.length === 2 && report.results.every(result => result.passed);
  await writeFile(join(outputDir, 'results.json'), JSON.stringify(report, null, 2));
}
console.log(`Report: ${join(outputDir, 'results.json')}`);
if (!report.passed) process.exitCode = 1;
