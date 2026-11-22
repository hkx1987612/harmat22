'use strict';
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),crypto=require('node:crypto');
const ROOT=path.resolve(__dirname,'../..');
const {JSDOM}=require(path.join(ROOT,'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const DIR=path.join(ROOT,'wp-mu-plugins/harmat-neighborhood-practical');
const read=n=>fs.readFileSync(path.join(DIR,n==='lucide.js'?'':'r2',n),'utf8');
const dom=new JSDOM(read('body.html'),{runScripts:'outside-only',url:'https://harmat22.hu/harmat-lakopark-kornyeke/'});
const w=dom.window,d=w.document;
const data=JSON.parse(d.getElementById('hn-facility-data').textContent);
const checkDirectory=()=>{
  check(d.querySelectorAll('.place-row').length===19,'original places and two BKK stops');
  for(const place of data.places.filter(p=>p.address || p.timetable)){
    const row=d.querySelector(`[data-place="${place.id}"]`);
    const query=new URL(row.querySelector('.map-link').href).searchParams.get('query');
    if(place.address){
      check(row.querySelector('.place-address').textContent===place.address,'verified school address');
      check(query.endsWith(', '+place.address),'school map query uses exact address');
    }else{
      check(query===place.mapQuery,'exact BKK stop location');
      check(row.querySelector('.place-timetable').href==='https://bkk.hu/menetrendek/','official timetable');
    }
    check(row.querySelector('.place-distance').textContent==='','no unverified distance');
  }
};
w.HTMLMediaElement.prototype.pause=function(){};
let checks=0;
const check=(value,message)=>{assert.ok(value,message);checks++;};
(async()=>{
  checkDirectory();
  w.eval(read('lucide.js'));
  w.eval(read('neighborhood.js'));
  checkDirectory();
  check(!d.querySelector('form,header,footer,iframe'),'no preview chrome or forms');
  check(d.querySelectorAll('h1').length===1,'one page title');
  check(!d.querySelector('[data-lucide]'),'icons use owned attribute');
  check(d.querySelectorAll('[data-hn-lucide] svg').length===0 && d.querySelectorAll('svg').length>20,'owned lucide renders');
  check(!w.lucide,'vendor does not overwrite global lucide');
  d.querySelector('[data-category=education]').click();
  check(d.querySelectorAll('.place-row').length===4,'education filter');
  const input=d.getElementById('hn-place-search');
  input.value='harmat utca 88';input.dispatchEvent(new w.Event('input'));
  check(d.querySelector('.place-row')?.dataset.place==='6'&&d.querySelectorAll('.place-row').length===1,'search by official address');
  input.value='szent laszlo';input.dispatchEvent(new w.Event('input'));
  check(d.querySelectorAll('.place-row').length===1,'accent-insensitive search');
  const nativeLang=d.documentElement.lang;
  d.documentElement.setAttribute('data-harmat-language','zh-Hans');
  await new Promise(resolve=>w.queueMicrotask(resolve));
  check(d.getElementById('harmat-neighborhood-practical').lang==='zh-Hans','native language bridge');
  check(d.documentElement.lang===nativeLang,'never overwrites native html language');
  check(d.querySelector('.place-name').textContent.includes('中学'),'Chinese row');
  input.value='不存在的配套';input.dispatchEvent(new w.Event('input'));
  check(!d.getElementById('hn-empty-state').hidden,'empty state');
  d.getElementById('hn-reset-filters').click();
  checkDirectory();
  d.querySelector('[data-category=transport]').click();
  check(d.querySelectorAll('.place-row').length===5,'existing transport plus bus stops');
  input.value='185';input.dispatchEvent(new w.Event('input'));
  check(d.querySelectorAll('.place-row').length===1&&d.querySelector('.place-timetable').textContent.includes('时刻表'),'search by bus line, Chinese timetable');
  check([...d.querySelectorAll('video')].every(v=>v.preload==='none'&&v.controls&&!v.autoplay),'native deferred videos');
  check(d.querySelectorAll('canvas').length===0,'closed panorama does not create canvas');
  const ids=[...d.querySelectorAll('[id]')].map(n=>n.id);
  check(ids.length===new Set(ids).size,'unique IDs');
  check(!read('neighborhood.js').includes('fetch(')&&!read('neighborhood.js').includes('localStorage'),'no new API/customer state');
  check(!read('body.html').includes('/media/')&&!read('neighborhood.js').includes('assets/panorama-'),'resolved production media');
  const pins=JSON.parse(read('manifest.json'));
  for(const [n,pin]of Object.entries(pins))check(crypto.createHash('sha256').update(read(n).replace(/\r\n/g,'\n')).digest('hex')===pin,'asset pin '+n);
  const css=d.createElement('style');css.textContent=read('neighborhood.css');d.head.append(css);
  check(read('neighborhood.css').includes('#hn-page-title{font-size:46px!important'),'demoted native title keeps desktop size');
  check(read('neighborhood.css').includes('.site-content{background:#fafbf9;padding:0!important}'),'native extra content padding removed on this page only');
  const inspect=rules=>{for(const rule of rules){if(rule.selectorText)check(rule.selectorText.split(',').every(s=>s.trim().startsWith('#harmat-neighborhood-practical')||s.trim().startsWith('body.page-id-3959')),'scoped selector');else if(rule.media)inspect(rule.cssRules);}};
  inspect(css.sheet.cssRules);
  console.log(JSON.stringify({status:'PASS',checks,places:19,submission:false}));dom.window.close();
})().catch(error=>{console.error(error);process.exitCode=1;dom.window.close();});
