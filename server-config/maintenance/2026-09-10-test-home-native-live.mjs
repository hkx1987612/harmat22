import {createRequire} from 'node:module';
import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
const require=createRequire(import.meta.url);
const {chromium}=require('playwright');
const out='outputs/home-native-video';
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const results=[];
try {
  for(const viewport of [{width:1440,height:900},{width:390,height:844}]) {
    const page=await browser.newPage({viewport,locale:'hu-HU'});
    const requests=[], errors=[];
    page.on('request',r=>requests.push(r.url()));
    page.on('pageerror',e=>errors.push(String(e)));
    const response=await page.goto('https://harmat22.hu/',{waitUntil:'domcontentloaded',timeout:40000});
    assert.equal(response.status(),200);
    await page.waitForFunction(()=>document.querySelector('#harmat-native-home-video')?.currentTime>1,{},{timeout:30000});
    const cookies=page.getByRole('button',{name:'Csak szükséges sütik'});
    if(await cookies.isVisible())await cookies.click();
    const result=await page.evaluate(()=>{
      const v=document.querySelector('#harmat-native-home-video');
      const module=document.querySelector('#SR7_1_1');
      const canvas=document.createElement('canvas');canvas.width=64;canvas.height=36;
      const ctx=canvas.getContext('2d');ctx.drawImage(v,0,0,64,36);
      const colors=new Set(Array.from(ctx.getImageData(0,0,64,36).data)).size;
      const schema=JSON.parse(document.querySelector('#harmat-youtube-video-schema').textContent);
      return {width:v.videoWidth,height:v.videoHeight,currentTime:v.currentTime,muted:v.muted,inline:v.playsInline,
        source:v.currentSrc,opacity:getComputedStyle(v).opacity,colors,overflow:document.documentElement.scrollWidth-innerWidth,
        hero:module.getBoundingClientRect().toJSON(),video:v.getBoundingClientRect().toJSON(),schema,
        ctas:[...document.querySelectorAll('.harmat-home-hero-cta a')].map(a=>a.getAttribute('href')),
        iframeCount:module.querySelectorAll('iframe').length};
    });
    assert.equal(result.width,1920);assert.equal(result.height,1080);assert(result.muted&&result.inline);
    assert.equal(result.opacity,'1');assert(result.colors>20);assert(result.overflow<=2);assert.equal(result.iframeCount,0);
    assert.equal(result.hero.width,result.video.width);assert.equal(result.hero.height,result.video.height);
    assert(result.schema.contentUrl?.endsWith('harmat-home-1080p-v2.mp4'));assert(!result.schema.embedUrl);
    assert(result.ctas.length===2);
    assert(!requests.some(u=>/youtube\.com\/iframe_api|youtube(?:-nocookie)?\.com\/embed/.test(u)));
    await page.screenshot({path:`${out}/live-${viewport.width}.png`});
    const start=result.currentTime;
    await page.waitForFunction(t=>document.querySelector('#harmat-native-home-video').currentTime>t+.4,start);
    await page.evaluate(()=>scrollTo(0,innerHeight+200));
    await page.waitForFunction(()=>document.querySelector('#harmat-native-home-video').paused);
    await page.evaluate(()=>scrollTo(0,0));
    await page.waitForFunction(()=>!document.querySelector('#harmat-native-home-video').paused);
    assert.deepEqual(errors,[]);
    results.push({viewport,...result,errors});
    console.log('LIVE_HOMEPAGE_PASS',viewport.width,JSON.stringify(result));
    await page.close();
  }
  const page=await browser.newPage();
  const media='https://harmat22.hu/wp-content/uploads/harmat-video/harmat-home-1080p-v2.mp4';
  const response=await page.request.get(media,{headers:{Range:'bytes=0-1023'}});
  assert.equal(response.status(),206);assert.equal((await response.body()).length,1024);
  assert.match(response.headers()['content-type'],/video\/mp4/);
  assert.match(response.headers()['cache-control'],/max-age=31536000/);
  const sitemap=await page.request.get('https://harmat22.hu/harmat-video-sitemap.xml');
  assert((await sitemap.text()).includes('<video:content_loc>'+media+'</video:content_loc>'));
  for(const path of ['/wp-content/uploads/2026/05/yulu-garden-source-compressed-60m.mp4','/wp-content/uploads/2026/05/yulu-garden-mobile-720p.mp4','/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/spjs.mp4']) {
    assert.equal((await page.request.head('https://harmat22.hu'+path)).status(),410);
  }
  console.log('PASS media Range 206, cache, sitemap and retired URL protections');
  await page.close();
  await fs.writeFile(`${out}/live-browser.json`,JSON.stringify(results,null,2));
} finally {await browser.close();}
