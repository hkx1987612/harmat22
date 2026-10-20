'use strict';
// Offline native/HAP integration, not browser paint or customer delivery evidence.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const PHP = process.env.HHF_PHP || 'C:/tools/php-8.5.6/php.exe';
const candidate = process.env.HHAI_TEST_CONTROLLER || path.join(ROOT, 'wp-mu-plugins/zz-harmat-home-assistant-inline.php');
const assets = execFileSync(PHP, [path.join(__dirname, '2026-10-19-test-home-assistant-inline.php'), candidate, '--render'], {encoding:'utf8'});
const script = assets.match(/<script id="harmat-home-assistant-inline-script">([\s\S]*?)<\/script>/)[1];
const css = assets.match(/<style id="harmat-home-assistant-inline-style">([\s\S]*?)<\/style>/)[1];
const home = execFileSync(PHP, [path.join(__dirname,'2026-10-18-test-home-family-controller.php'),
  path.join(ROOT,'wp-mu-plugins/zz-harmat-home-family.php'),'--render'], {encoding:'utf8'}).match(/<main[\s\S]*?<\/main>/)[0];
const widget = execFileSync(PHP,[path.join(ROOT,'wp-mu-plugins/harmat-assistant-presentation/tests/widget-fixture.php'),'--render'],{encoding:'utf8'});
const tick = () => new Promise(resolve => setImmediate(resolve));

async function fixture(t, mutate) {
  const dom = new JSDOM(`<html lang="hu" data-harmat-language="hu"><head><title>Native SEO</title><style>${css}</style></head><body><header id="masthead">Header</header>${home}<i id="before-launch"></i>${widget}<i id="after-launch"></i><div id="h22-offer-modal" hidden aria-hidden="true"><form id="h22-offer-form"><input value="Unsent quote"><input type="checkbox" required></form></div><div id="hm-cookie-consent" hidden aria-hidden="true"></div><div id="harmat-clean-menu-modal" hidden aria-hidden="true"></div><form id="appointment-form"><input value="Unsent appointment"></form><footer id="colophon">Footer</footer></body></html>`,{url:'https://harmat22.hu/',runScripts:'outside-only',pretendToBeVisual:true});
  const w=dom.window,d=w.document,calls=[],effects=[];
  w.fetch=async (url,opts)=>{calls.push({url,payload:JSON.parse(opts.body)});assert.match(url,/\/harmat-local-assistant\/v1\/event$/);return {ok:true,json:async()=>({ok:true})};};
  for(const name of ['XMLHttpRequest','WebSocket','EventSource']) w[name]=function(){effects.push(name);throw Error('NO_NETWORK');};
  w.navigator.sendBeacon=()=>{effects.push('beacon');throw Error('NO_BEACON');};
  for(const name of ['getItem','setItem','removeItem','clear']) w.Storage.prototype[name]=()=>{effects.push(name);throw Error('NO_STORAGE');};
  w.HTMLFormElement.prototype.submit=w.HTMLFormElement.prototype.requestSubmit=()=>{effects.push('submit');throw Error('NO_SUBMIT');};
  w.HTMLMediaElement.prototype.play=w.HTMLMediaElement.prototype.pause=()=>{effects.push('media');throw Error('NO_MEDIA');};
  w.eval(d.querySelector('#harmat-local-assistant-script').textContent);
  w.eval(d.querySelector('#harmat-assistant-presentation-script').textContent);
  await tick();
  const launch=d.querySelector('.harmat-local-ai-launch'),panel=d.querySelector('#harmat-local-ai-panel');
  const copy=d.querySelector('.hhf-hero-copy'),actions=d.querySelector('.hhf-hero-actions');
  const all=d.querySelector('#hhf-lakasok .hhf-text-link'),allParent=all.parentNode;
  const input=panel.querySelector('input[name="message"]');input.value='Unsent draft';input.setSelectionRange(2,7);
  const history=panel.querySelector('[data-harmat-local-ai-body]');
  const protectedNodes=[...d.querySelectorAll('#masthead,#colophon,#h22-offer-form,#appointment-form,video,style')];
  const protectedHTML=protectedNodes.map(n=>n.outerHTML);
  const fields=[...d.querySelectorAll('#h22-offer-form input,#appointment-form input')].map(n=>[n.value,n.checked]);
  let ownClicks=0;launch.addEventListener('click',()=>{ownClicks++;});
  const f={w,d,launch,panel,copy,actions,all,allParent,input,history,calls,effects,
    boot(){w.eval(script);},get group(){return d.querySelector('#harmat-home-assistant-actions');},get ownClicks(){return ownClicks;},
    async language(code){d.documentElement.setAttribute('data-harmat-language',code);await tick();},
    async modal(id,on){const m=d.getElementById(id);m.getClientRects=()=>on?[{width:200,height:200}]:[];m.hidden=!on;m.setAttribute('aria-hidden',String(!on));await tick();}
  };
  if(mutate)mutate(f);
  f.before=d.body.innerHTML;f.launchHTML=launch.outerHTML;f.panelHTML=panel.outerHTML;f.allHTML=all.outerHTML;
  f.origin=[launch.parentNode,launch.nextSibling];
  t.after(()=>{assert.deepEqual(protectedNodes.map(n=>n.outerHTML),protectedHTML);assert.deepEqual([...d.querySelectorAll('#h22-offer-form input,#appointment-form input')].map(n=>[n.value,n.checked]),fields);assert.deepEqual([input.value,input.selectionStart,input.selectionEnd],['Unsent draft',2,7]);assert.equal(d.title,'Native SEO');assert.deepEqual(effects,[]);w.close();});
  return f;
}

test('same launcher follows both hero actions; apartment link, panel and handlers retained',async t=>{
  const f=await fixture(t);f.boot();await tick();
  assert.equal(f.group.parentNode,f.copy);assert.equal(f.group.previousElementSibling,f.actions);
  assert.deepEqual([...f.group.children],[f.launch]);assert.equal(f.actions.children.length,2);
  assert.equal(f.launch.outerHTML,f.launchHTML);assert.equal(f.panel.outerHTML,f.panelHTML);
  assert.equal(f.all.outerHTML,f.allHTML);assert.equal(f.all.parentNode,f.allParent);
  assert.equal(f.panel.parentNode,f.d.body);assert.equal(f.d.querySelectorAll('.harmat-local-ai-launch').length,1);
  assert.deepEqual(f.calls,[]);f.launch.querySelector('svg').dispatchEvent(new f.w.MouseEvent('click',{bubbles:true,cancelable:true}));await tick();
  assert.equal(f.ownClicks,1);assert.ok(f.panel.classList.contains('is-open'));assert.equal(f.calls.length,1);assert.equal(f.calls[0].payload.event,'assistant_open');
  f.panel.querySelector('.harmat-local-ai-close').click();await tick();assert.ok(!f.panel.classList.contains('is-open'));assert.equal(f.d.activeElement,f.launch);
});

test('idempotent init; no duplicate or automatic launch',async t=>{
  const f=await fixture(t);f.boot();const before=f.d.body.innerHTML;f.boot();f.boot();await tick();
  assert.equal(f.d.body.innerHTML,before);assert.equal(f.d.querySelectorAll('#harmat-home-assistant-actions').length,1);assert.deepEqual(f.calls,[]);
});

test('DOMContentLoaded init remains idempotent',async t=>{
  const f=await fixture(t);Object.defineProperty(f.d,'readyState',{configurable:true,value:'loading'});f.boot();f.boot();assert.equal(f.group,null);
  f.d.dispatchEvent(new f.w.Event('DOMContentLoaded'));f.d.dispatchEvent(new f.w.Event('DOMContentLoaded'));
  assert.equal(f.d.querySelectorAll('#harmat-home-assistant-actions').length,1);assert.equal(f.group.firstElementChild,f.launch);assert.deepEqual(f.calls,[]);
});

test('HU/ZH bridge preserves original icon, launcher, chat draft and history',async t=>{
  const f=await fixture(t);f.boot();const label=f.launch.children[1],icon=f.launch.querySelector('svg'),history=f.history.innerHTML;
  for(const code of ['zh-Hans','hu','zh-Hans','hu']){await f.language(code);assert.equal(label.textContent,code==='hu'?'K\u00e9rdezzen':'\u54a8\u8be2');assert.equal(f.group.firstElementChild,f.launch);assert.equal(f.launch.querySelector('svg'),icon);assert.equal(f.history.innerHTML,history);}
  assert.deepEqual(f.calls,[]);
});

for(const id of ['h22-offer-modal','hm-cookie-consent','harmat-clean-menu-modal']) test(`original suspension retained: ${id}`,async t=>{
  const f=await fixture(t);await f.modal(id,true);f.boot();await tick();
  assert.equal(f.launch.getAttribute('data-hap-suspended'),'1');assert.equal(f.w.getComputedStyle(f.launch).display,'none');assert.equal(f.w.getComputedStyle(f.panel).display,'none');
  await f.modal(id,false);assert.equal(f.launch.getAttribute('data-hap-suspended'),'0');assert.notEqual(f.w.getComputedStyle(f.launch).display,'none');assert.deepEqual(f.calls,[]);
});

const invalid={
  'missing root':f=>f.d.querySelector('#harmat-home-family').remove(),
  'duplicate root':f=>f.d.body.insertAdjacentHTML('beforeend','<div id="harmat-home-family"></div>'),
  'missing hero':f=>f.d.querySelector('#hhf-kezdolap').remove(),
  'duplicate hero':f=>f.d.querySelector('#harmat-home-family').insertAdjacentHTML('beforeend','<section id="hhf-kezdolap"></section>'),
  'hero outside root':f=>f.d.body.appendChild(f.d.querySelector('#hhf-kezdolap')),
  'wrong hero':f=>f.d.querySelector('#hhf-kezdolap').classList.remove('hhf-hero'),
  'missing copy':f=>f.copy.className='other',
  'duplicate copy':f=>f.copy.parentNode.appendChild(f.copy.cloneNode(true)),
  'copy extra node':f=>f.copy.appendChild(f.d.createElement('div')),
  'copy unexpected text':f=>f.copy.appendChild(f.d.createTextNode('Foreign content')),
  'missing title':f=>f.d.querySelector('#hero-title').remove(),
  'duplicate title':f=>f.d.body.insertAdjacentHTML('beforeend','<h1 id="hero-title"></h1>'),
  'wrong action order':f=>f.actions.insertBefore(f.actions.lastElementChild,f.actions.firstElementChild),
  'wrong selector':f=>f.actions.firstElementChild.href='https://harmat22.hu/other/',
  'wrong search':f=>f.actions.lastElementChild.href='https://harmat22.hu/other/',
  'extra action':f=>f.actions.appendChild(f.d.createElement('a')),
  'missing launch':f=>f.launch.remove(),
  'duplicate launch':f=>f.d.body.appendChild(f.launch.cloneNode(true)),
  'submit launch':f=>f.launch.type='submit',
  'wrong aria controls':f=>f.launch.setAttribute('aria-controls','other'),
  'launch inside root':f=>f.d.querySelector('#harmat-home-family').appendChild(f.launch),
  'missing panel':f=>f.panel.remove(),
  'duplicate panel':f=>f.d.body.appendChild(f.panel.cloneNode(true)),
  'panel inside root':f=>f.d.querySelector('#harmat-home-family').appendChild(f.panel),
  'native not ready':f=>delete f.w.__harmatLocalAssistantReady,
  'presentation not ready':f=>f.launch.removeAttribute('data-hap-ready'),
  'group collision':f=>f.d.body.insertAdjacentHTML('beforeend','<div id="harmat-home-assistant-actions"></div>'),
  'marker collision':f=>f.d.body.setAttribute('data-hhai-ready','other'),
  'launch inside form':f=>{const form=f.d.createElement('form');f.d.body.appendChild(form);form.appendChild(f.launch);}
};
for(const [name,mutate] of Object.entries(invalid))test(`reject without mutation: ${name}`,async t=>{
  const f=await fixture(t,mutate);const originalGroup=f.group;f.boot();await tick();assert.equal(f.d.body.innerHTML,f.before);assert.equal(f.group,originalGroup);assert.deepEqual(f.calls,[]);
});

for(const point of ['insert-before','insert-after','launch-before','launch-after','marker-before','marker-after'])test(`partial mutation recovery: ${point}`,async t=>{
  const f=await fixture(t);const insert=f.w.Node.prototype.insertBefore,append=f.w.Node.prototype.appendChild,set=f.w.Element.prototype.setAttribute;let fired=false;
  const act=(target,node,operation)=>{const key=target===f.copy&&node.id==='harmat-home-assistant-actions'?'insert':target.id==='harmat-home-assistant-actions'&&node===f.launch?'launch':null;if(!fired&&point===key+'-before'){fired=true;throw Error('injected');}const result=operation();if(!fired&&point===key+'-after'){fired=true;throw Error('injected');}return result;};
  f.w.Node.prototype.insertBefore=function(node,next){return act(this,node,()=>insert.call(this,node,next));};
  f.w.Node.prototype.appendChild=function(node){return act(this,node,()=>append.call(this,node));};
  f.w.Element.prototype.setAttribute=function(name,value){if(this===f.copy&&name==='data-hhai-ready'&&!fired&&point==='marker-before'){fired=true;throw Error('injected');}const result=set.call(this,name,value);if(this===f.copy&&name==='data-hhai-ready'&&!fired&&point==='marker-after'){fired=true;throw Error('injected');}return result;};
  try{f.boot();}finally{f.w.Node.prototype.insertBefore=insert;f.w.Node.prototype.appendChild=append;f.w.Element.prototype.setAttribute=set;}
  assert.ok(fired);assert.equal(f.d.body.innerHTML,f.before);assert.deepEqual([f.launch.parentNode,f.launch.nextSibling],f.origin);assert.equal(f.group,null);assert.deepEqual(f.calls,[]);
  f.boot();assert.equal(f.group.firstElementChild,f.launch);
});

test('desktop copy/media placement rules retained byte for byte',()=>{
  const old=execFileSync('git',['show','7f7dabd:wp-mu-plugins/zz-harmat-home-assistant-inline.php'],{cwd:ROOT,encoding:'utf8'});
  assert.deepEqual(css.split('\n').filter(l=>l.startsWith('@media(min-width:768px)')),old.split(/\r?\n/).filter(l=>l.startsWith('@media(min-width:768px)')));
  assert.ok(css.includes('margin:16px 0 0'));assert.ok(css.includes('min-height:44px'));assert.ok(css.includes('position:static!important'));
  assert.ok(!css.includes('display:none')&&!css.includes('display:flex!important'));
});

test('no new listeners, observers, backend calls, storage or form/media mutations',()=>{
  assert.ok(!/cloneNode|innerHTML|outerHTML|replaceChildren|textContent\s*=|\.click\(|dispatchEvent|MutationObserver|setTimeout|setInterval|fetch\(|XMLHttpRequest|sendBeacon|Storage|localStorage|sessionStorage|\.value\s*=|\.src\s*=|\.play\(|\.pause\(/.test(script));
  assert.deepEqual([...script.matchAll(/addEventListener\(([^,]+)/g)].map(m=>m[1]),["'DOMContentLoaded'"]);
  assert.ok(!/data-hap-suspended|data-harmat-language|data-harmat-ai-lang/.test(script));
  assert.ok(!/<(?:form|input|textarea|select|video|iframe|link)\b|\bsrc=|@import|url\(/i.test(assets));
});
