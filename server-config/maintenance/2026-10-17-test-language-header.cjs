'use strict';
// Deterministic placement geometry, not a browser pixel or network test.
const test=require('node:test');const assert=require('node:assert/strict');
const fs=require('node:fs');const path=require('node:path');
const root=path.resolve(__dirname,'../..');
const {JSDOM}=require(path.join(root,'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const boot=fs.readFileSync(path.join(root,'wp-mu-plugins/harmat-internal-language/bootstrap.js'),'utf8');
function setup({width=1200,left=80,top=20,logoLeft=540,noMenu=false,spacerOnly=false}={}){
  const row='<div class="headerrow"><div class="e-con-inner"><div data-id="58842f8"><div data-id="4d7a363"><div class="elementor-icon-wrapper"><a class="elementor-icon" href="#elementor-action%3Aaction%3Dpopup">menu</a></div></div></div><div data-id="298657c"><img alt="logo"></div></div></div>';
  const dom=new JSDOM('<!doctype html><body><header id="masthead">'+(noMenu?'':(spacerOnly?'':'<div id="live-header" class="elementor-sticky--active">'+row+'</div>')+'<div class="elementor-sticky__spacer">'+row+'</div>')+'</header><form id="native"><input name="lead_source" value="Google keresés"><input name="your-message" value="原文保留"></form><div id="harmat-internal-language"><button data-hil-language="hu">Magyar</button><button data-hil-language="zh-Hans" disabled>中文</button><span data-hil-status></span></div><script id="harmat-internal-language-config" type="application/json">{"dictionary":"https://harmat22.hu/wp-content/mu-plugins/harmat-internal-language/zh-Hans.json"}</script></body>',{url:'https://harmat22.hu/elerhetosegeink/',runScripts:'outside-only'});
  const w=dom.window,d=w.document,control=d.getElementById('harmat-internal-language');
  let now=0,next=1,requests=0;const frames=new Map();
  Object.defineProperty(w,'innerWidth',{get:()=>width});Object.defineProperty(w,'innerHeight',{value:800});
  Object.defineProperty(d.documentElement,'clientWidth',{get:()=>width});
  Object.defineProperty(w.performance,'now',{value:()=>now});
  w.requestAnimationFrame=fn=>{const id=next++;frames.set(id,fn);return id;};
  w.ResizeObserver=class {observe(){}disconnect(){}};
  w.fetch=async()=>{requests++;throw new Error('NO NETWORK');};
  w.HarmatInternalLanguage={create(){throw new Error('HU must not load translator');}};
  function rect(x,y,width,height){return {left:x,top:y,right:x+width,bottom:y+height,width,height};}
  const menu=d.querySelector('#live-header a');
  if(menu)menu.getBoundingClientRect=()=>rect(left,top,width<=480?42:56,42);
  d.querySelectorAll('.elementor-sticky__spacer a').forEach(e=>{e.getBoundingClientRect=()=>rect(700,0,56,42);});
  d.querySelectorAll('[data-id="298657c"] img').forEach(e=>{e.getBoundingClientRect=()=>rect(logoLeft,0,75,90);});
  control.getBoundingClientRect=()=>rect(0,0,control.dataset.hilLayout==='stacked'?52:width<=480?100:108,44);
  const form=d.getElementById('native'),before=form.outerHTML;
  w.eval(boot);
  async function flush(){await Promise.resolve();let count=0;while(frames.size){if(++count>30)throw new Error('Unbounded placement polling');const items=[...frames.values()];frames.clear();now+=100;items.forEach(fn=>fn(now));await Promise.resolve();}assert.equal(form.outerHTML,before);assert.equal(requests,0);assert.equal(d.querySelectorAll('#harmat-internal-language').length,1);}
  return {w,d,control,menu,flush,move(x,y){left=x;top=y;w.dispatchEvent(new w.Event('scroll'));},resize(value){width=value;w.dispatchEvent(new w.Event('resize'));},close(){w.close();}};
}
test('desktop anchored beside real menu, never cloned spacer',async()=>{const f=setup();await f.flush();assert.equal(f.control.dataset.hilAnchor,'menu');assert.equal(parseFloat(f.control.style.getPropertyValue('--hil-anchor-left')),146);assert.equal(parseFloat(f.control.style.getPropertyValue('--hil-anchor-top')),19);assert.equal(f.control.parentElement,f.d.body);f.close();});
test('320px uses readable inline mode when actual space allows',async()=>{const f=setup({width:320,left:45,top:45,logoLeft:211.25});await f.flush();assert.equal(f.control.dataset.hilLayout,'inline');assert.equal(f.control.dataset.hilAnchor,'menu');assert.equal(parseFloat(f.control.style.getPropertyValue('--hil-anchor-left')),93);f.close();});
test('narrow layout stacks only if inline would overlap logo',async()=>{const f=setup({width:320,left:45,top:45,logoLeft:145});await f.flush();assert.equal(f.control.dataset.hilLayout,'stacked');assert.equal(f.control.dataset.hilAnchor,'menu');assert.equal(parseFloat(f.control.style.getPropertyValue('--hil-anchor-left')),91);f.close();});
test('insufficient space uses visible fallback instead of covering logo',async()=>{const f=setup({width:320,left:45,logoLeft:120});await f.flush();assert.equal(f.control.dataset.hilAnchor,'fallback');assert.equal(f.control.dataset.hilAnchorReason,'no-space');f.close();});
test('native idle hide holds language position and reveal reanchors',async()=>{const f=setup();await f.flush();const h=f.d.getElementById('live-header');const left=f.control.style.getPropertyValue('--hil-anchor-left');h.classList.add('is-idle-hidden');await f.flush();assert.equal(f.control.dataset.hilAnchor,'held');assert.equal(f.control.style.getPropertyValue('--hil-anchor-left'),left);h.classList.remove('is-idle-hidden');await f.flush();assert.equal(f.control.dataset.hilAnchor,'menu');f.close();});
test('offscreen menu retains visible overlay at last good position',async()=>{const f=setup();await f.flush();const top=f.control.style.getPropertyValue('--hil-anchor-top');f.move(80,-60);await f.flush();assert.equal(f.control.dataset.hilAnchor,'held');assert.equal(f.control.style.getPropertyValue('--hil-anchor-top'),top);f.close();});
test('no recognized header retains original fallback',async()=>{const f=setup({noMenu:true});await f.flush();assert.equal(f.control.dataset.hilAnchor,'fallback');f.close();});
test('spacer alone is not an anchor',async()=>{const f=setup({spacerOnly:true});await f.flush();assert.equal(f.control.dataset.hilAnchor,'fallback');f.close();});
test('scroll and resize reuse original object and settle without polling',async()=>{const f=setup();const original=f.control;await f.flush();f.move(100,35);f.resize(390);await f.flush();assert.equal(f.d.getElementById('harmat-internal-language'),original);assert.equal(parseFloat(f.control.style.getPropertyValue('--hil-anchor-left')),148);f.close();});
test('initial opacity zero has visible fallback until animation ends',async()=>{const f=setup();const h=f.d.getElementById('live-header');h.style.opacity='0';await f.flush();assert.equal(f.control.dataset.hilAnchor,'fallback');h.style.opacity='1';h.dispatchEvent(new f.w.Event('animationend',{bubbles:true}));await f.flush();assert.equal(f.control.dataset.hilAnchor,'menu');f.close();});
test('repeated bootstrap does not install another control',async()=>{const f=setup();await f.flush();f.w.eval(boot);await f.flush();assert.equal(f.control.dataset.hilBooted,'true');f.close();});
test('held position clamps inside narrower viewport',async()=>{const f=setup();await f.flush();f.d.getElementById('live-header').classList.add('is-idle-hidden');f.resize(180);await f.flush();assert.equal(f.control.dataset.hilAnchor,'held');assert.equal(parseFloat(f.control.style.getPropertyValue('--hil-anchor-left')),76);f.close();});
test('anchor styles never hide or disable control',()=>{const css=fs.readFileSync(path.join(root,'wp-mu-plugins/harmat-internal-language/runtime.css'),'utf8');assert.ok(css.includes('[data-hil-anchor="held"]'));assert.ok(!css.includes('visibility: hidden'));assert.ok(!css.includes('pointer-events: none'));assert.ok(!boot.includes("state('hidden'"));});
