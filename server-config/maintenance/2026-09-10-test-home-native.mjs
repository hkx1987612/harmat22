import fs from 'node:fs/promises';
import http from 'node:http';
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const out = 'outputs/home-native-video';
const videoBytes = await fs.readFile(`${out}/harmat-home-1080p-v2.mp4`);
const js = await fs.readFile('wp-mu-plugins/assets/harmat-home-native-video.js', 'utf8');
const fixture = `<html><style>body{margin:0}#SR7_1_1{height:100vh;background:#202522}video{width:100%;height:100%;object-fit:cover}footer{height:100vh}</style><div id="SR7_1_1"><video id="harmat-native-home-video" muted loop playsinline preload="none" data-src="/video.mp4"></video></div><footer>Footer</footer><script>${js}</script></html>`;
const server = http.createServer((req, res) => {
  if (req.url === '/video.mp4') {
    const range = req.headers.range?.match(/bytes=(\d+)-(\d*)/);
    const start = range ? Number(range[1]) : 0;
    const end = range?.[2] ? Math.min(Number(range[2]), videoBytes.length - 1) : videoBytes.length - 1;
    res.writeHead(range ? 206 : 200, { 'Content-Type': 'video/mp4', 'Accept-Ranges': 'bytes',
      'Content-Length': end - start + 1, ...(range ? {'Content-Range': `bytes ${start}-${end}/${videoBytes.length}`} : {}) });
    res.end(videoBytes.subarray(start, end + 1));
  } else { res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(fixture); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const url = `http://127.0.0.1:${server.address().port}/`;
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
  for (const viewport of [{width:1440,height:900},{width:390,height:844}]) {
    const page = await browser.newPage({ viewport });
    const errors = [];
    page.on('pageerror', e => errors.push(String(e)));
    await page.goto(url);
    try {
      await page.waitForFunction(() => document.querySelector('video').currentTime > .5);
    } catch (error) {
      console.log('PLAYBACK_DIAGNOSTIC', await page.evaluate(() => {
        const v=document.querySelector('video');
        return {html:v.outerHTML,state:v.readyState,network:v.networkState,error:v.error?.message,
          hidden:document.hidden,paused:v.paused,box:v.getBoundingClientRect().toJSON()};
      }), errors);
      throw error;
    }
    const data = await page.evaluate(() => {
      const v = document.querySelector('video');
      const c = document.createElement('canvas'); c.width=32; c.height=18;
      const ctx = c.getContext('2d'); ctx.drawImage(v,0,0,32,18);
      const pixels = ctx.getImageData(0,0,32,18).data;
      return {width:v.videoWidth,height:v.videoHeight,muted:v.muted,inline:v.playsInline,
        colors:new Set(Array.from(pixels)).size,playing:document.querySelector('#SR7_1_1').classList.contains('harmat-youtube-playing')};
    });
    assert.equal(data.width,1920); assert.equal(data.height,1080); assert(data.muted && data.inline && data.playing); assert(data.colors>20);
    await page.evaluate(() => scrollTo(0, innerHeight+20));
    await page.waitForFunction(() => document.querySelector('video').paused);
    await page.evaluate(() => scrollTo(0,0));
    await page.waitForFunction(() => !document.querySelector('video').paused);
    await page.evaluate(() => { const v=document.querySelector('video'); v.currentTime=v.duration-.2; });
    await page.waitForFunction(() => document.querySelector('video').currentTime<2);
    await page.screenshot({path:`${out}/local-${viewport.width}.png`});
    assert.deepEqual(errors, []);
    console.log('PASS native playback/pixels/scroll pause/resume/loop', viewport.width, data);
    await page.close();
  }
  for (const mode of ['reduced','saveData','blocked','error']) {
    const page = await browser.newPage({ reducedMotion:mode==='reduced'?'reduce':'no-preference' });
    const requests=[];
    page.on('request',r=>{if(r.url().includes('video.mp4'))requests.push(r.url());});
    if(mode==='saveData') await page.addInitScript(() => Object.defineProperty(navigator,'connection',{value:{saveData:true}}));
    if(mode==='blocked') await page.addInitScript(() => {HTMLMediaElement.prototype.play=function(){return Promise.reject(new DOMException('Blocked','NotAllowedError'));};});
    if(mode==='error') await page.route('**/video.mp4', route=>route.abort());
    await page.goto(url);
    if (mode==='reduced'||mode==='saveData') {
      await page.waitForTimeout(700); assert.equal(requests.length,0);
    } else await page.waitForFunction(() => document.querySelector('video').dataset.installed && !document.querySelector('video').getAttribute('src'));
    assert.equal(await page.locator('#SR7_1_1').evaluate(el=>el.classList.contains('harmat-youtube-playing')),false);
    console.log('PASS fallback',mode); await page.close();
  }
} finally { await browser.close(); await new Promise(resolve=>server.close(resolve)); }
