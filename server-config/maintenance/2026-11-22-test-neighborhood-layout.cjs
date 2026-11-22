'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.HARMAT_PLAYWRIGHT || path.join(process.env.USERPROFILE,
  '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const root = path.resolve(__dirname, '../..');
const bundle = path.join(root, 'wp-mu-plugins/harmat-neighborhood-practical/r2');
const origin = 'https://harmat22.hu';
const route = '/harmat-lakopark-kornyeke/';
const asset = '/wp-content/mu-plugins/harmat-neighborhood-practical/';
const local = process.argv.includes('--candidate');
const output = path.join(root, 'outputs/2026-11-22-site-maintenance');
fs.mkdirSync(output, {recursive: true});

(async () => {
  const browser = await chromium.launch({channel: 'chrome'});
  const results = [];
  try {
    for (const [width, height, zh] of [[1440, 900, false], [1920, 1080, false], [390, 844, true], [320, 640, false]]) {
      const page = await browser.newPage({viewport: {width, height}});
      await page.route('**/*', async request => {
        const url = new URL(request.request().url());
        if (url.origin !== origin || !['GET', 'HEAD'].includes(request.request().method())) return request.abort();
        if (!local) return request.continue();
        if (url.pathname === route && request.request().isNavigationRequest()) {
          const response = await request.fetch();
          const source = await response.text();
          const start = source.indexOf('<div id="harmat-neighborhood-practical"');
          const end = source.indexOf('</main>', start);
          const endMarker = '</script></div>';
          const bodyEnd = source.indexOf(endMarker, source.indexOf('id="hn-facility-data"', start)) + endMarker.length;
          assert(start >= 0 && end > bodyEnd && bodyEnd > start, 'existing neighborhood boundary');
          return request.fulfill({response, body: source.slice(0, start) + fs.readFileSync(path.join(bundle, 'body.html'), 'utf8') + source.slice(bodyEnd)});
        }
        const match = url.pathname.match(/^\/wp-content\/mu-plugins\/harmat-neighborhood-practical\/(neighborhood\.(css|js))$/);
        if (match) return request.fulfill({path: path.join(bundle, match[1]), contentType: match[2] === 'css' ? 'text/css' : 'application/javascript'});
        return request.continue();
      });
      await page.goto(origin + route, {waitUntil: 'domcontentloaded'});
      if (zh) {
        await page.locator('#harmat-internal-language').getByRole('button', {name: '中文', exact: true}).click();
        await page.waitForFunction(() => document.querySelector('#harmat-neighborhood-practical').lang === 'zh-Hans');
      }
      const hero = page.locator('.park-hero');
      await hero.locator('.hero-photo').evaluate(img => img.decode(), {timeout: 15000});
      const metrics = await hero.evaluate(section => {
        const rect = selector => section.querySelector(selector).getBoundingClientRect();
        const photo = rect('.hero-photo'), title = rect('.hero-copy'), caption = rect('.photo-label');
        return {width: innerWidth, height: innerHeight, documentWidth: document.documentElement.scrollWidth,
          ratio: photo.width / photo.height, fit: getComputedStyle(section.querySelector('.hero-photo')).objectFit,
          photoTop: photo.top, photoBottom: photo.bottom, headerBottom: document.querySelector('#masthead').getBoundingClientRect().bottom,
          titleTop: title.top, titleBottom: title.bottom, captionTop: caption.top, captionBottom: caption.bottom,
          nextSectionTop: document.querySelector('#hn-places').getBoundingClientRect().top};
      });
      assert.equal(metrics.documentWidth, width, 'no horizontal overflow');
      assert.equal(metrics.fit, 'contain');
      assert(Math.abs(metrics.ratio - 4/3) < .01, 'uncropped original ratio');
      assert(metrics.photoTop >= metrics.headerBottom - 1 && metrics.photoBottom < height, 'photo within first screen, below header: ' + JSON.stringify(metrics));
      assert(metrics.titleTop >= metrics.photoTop && metrics.titleBottom < metrics.captionTop && metrics.captionBottom <= metrics.photoBottom, 'title and caption clear');
      assert(metrics.nextSectionTop < height, 'next section begins within viewport');
      await page.screenshot({path: path.join(output, `${local ? 'candidate' : 'live'}-${width}.png`)});
      await page.locator('[data-place="6"] .place-address').waitFor();
      assert.match(await page.locator('[data-place="6"]').textContent(), /1104 Budapest, Harmat utca 88/);
      const input = page.locator('#hn-place-search');
      await input.fill('185');
      assert.equal(await page.locator('.place-row:visible').count(), 1);
      assert.match(await page.locator('.place-row:visible').textContent(), /Ihász utca/);
      assert.equal(await page.locator('.place-timetable:visible').getAttribute('href'), 'https://bkk.hu/menetrendek/');
      results.push({viewport: [width, height], lang: zh ? 'zh' : 'hu', photoAspect: metrics.ratio});
      if (!local && [1440, 390].includes(width)) {
        await page.goto(origin + '/#hhf-epitesi-naplo', {waitUntil: 'domcontentloaded'});
        const section = page.locator('#hhf-epitesi-naplo');
        const player = section.locator('[data-hcu-player]');
        assert.equal(await section.locator('time').getAttribute('datetime'), '2026-10-30');
        assert.match(await player.getAttribute('data-hcu-video'), /2026-10-30-harmat-site\.mp4$/);
        assert.match(await page.locator('#hhf-hero-video').getAttribute('data-src'), /harmat-home-1080p-v2\.mp4$/);
        assert.equal(await player.locator('video').count(), 0, 'no construction download before play');
        await player.scrollIntoViewIfNeeded();
        await player.locator('img').evaluate(img => img.decode());
        assert(await player.locator('img').evaluate(img => img.naturalWidth > 0 && img.getBoundingClientRect().right <= innerWidth));
        await page.screenshot({path: path.join(output, `home-live-${width}.png`)});
        await player.locator('[data-hcu-play]').click();
        await page.waitForFunction(() => {
          const video = document.querySelector('#hhf-epitesi-naplo video');
          return video && video.videoWidth === 1920 && video.currentTime > 0.1;
        }, null, {timeout: 15000});
        results[results.length - 1].constructionPlayback = true;
      }
      await page.close();
    }
    console.log(JSON.stringify({status: 'PASS', candidate: local, results}));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
