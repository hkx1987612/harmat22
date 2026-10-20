'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process');
const ROOT=path.resolve(__dirname,'../..'),PHP='C:/tools/php-8.5.6/php.exe';
const {JSDOM}=require(path.join(ROOT,'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const candidate=path.join(ROOT,'wp-mu-plugins/zz-harmat-home-apartment-rotation.php');
const assets=execFileSync(PHP,[path.join(__dirname,'2026-10-20-test-home-apartment-rotation.php'),candidate,'--render'],{encoding:'utf8'});
const script=assets.match(/<script id="harmat-home-rotation-script">([\s\S]*?)<\/script>/)[1];
const home=execFileSync(PHP,[path.join(__dirname,'2026-10-18-test-home-family-controller.php'),path.join(ROOT,'wp-mu-plugins/zz-harmat-home-family.php'),'--render'],{encoding:'utf8'}).match(/<main[\s\S]*?<\/main>/)[0];
const native=fs.readFileSync(path.join(ROOT,'wp-mu-plugins/harmat-home-family/home-family-six-r2.js'),'utf8');
const tick=()=>new Promise(resolve=>setImmediate(resolve));
async function fixture(t,{mobile=false,reduced=false,mutate}={}){
 const dom=new JSDOM(`<html data-harmat-language="hu"><head></head><body><header>Native</header>${home}${assets}<button class="harmat-local-ai-launch" data-hap-suspended="0">Chat</button><section id="harmat-local-ai-panel"></section><form id="quote"><input value="unsent"><input type="checkbox" required></form><footer>Native</footer></body></html>`,{url:'https://harmat22.hu/',runScripts:'outside-only',pretendToBeVisual:true});
 const w=dom.window,d=w.document,observers=[],timers=new Map(),effects=[],queries=new Map();let id=0;
 w.matchMedia=q=>{if(!queries.has(q))queries.set(q,{matches:q.includes('reduced-motion')?reduced:q.includes('620')?mobile:false,handlers:[],addEventListener(name,fn){this.handlers.push(fn);}});return queries.get(q);};
 w.IntersectionObserver=class{constructor(fn){this.fn=fn;this.targets=[];observers.push(this);}observe(n){this.targets.push(n);}disconnect(){}};
 w.ResizeObserver=class{observe(){}};
 w.HTMLMediaElement.prototype.play=()=>Promise.resolve();w.HTMLMediaElement.prototype.pause=()=>{};
 w.fetch=()=>{effects.push('fetch');throw Error('no fetch');};
 w.setTimeout=(fn,delay)=>{timers.set(++id,{fn,delay});return id;};w.clearTimeout=n=>timers.delete(n);
 w.eval(native);await tick();
 const section=d.querySelector('#hhf-lakasok'),grid=d.querySelector('#hhf-apartment-grid'),cards=[...grid.children];
 const protectedNodes=[...d.querySelectorAll('header,footer,form,video,#hhf-kezdolap,#hhf-panorama')];
 const protectedHTML=protectedNodes.map(n=>n.outerHTML);
 if(mutate)mutate({w,d,section,grid,cards});
 const before=d.body.innerHTML;
 const f={w,d,section,grid,cards,timers,queries,before,
  boot(){w.eval(script);},
  codes(){return [...grid.children].map(n=>n.dataset.code);},
  get controls(){return section.querySelector('.hhar-controls');},
  visible(on=true){const o=observers.find(o=>o.targets.includes(section));o.fn([{isIntersecting:on,intersectionRatio:on?.3:0}]);},
  advance(){const timer=[...timers].find(([,v])=>v.delay===12000);if(!timer)return false;timers.delete(timer[0]);timer[1].fn();return true;},
  pointer(type,kind='mouse',target=section){const e=new w.Event(type);Object.defineProperty(e,'pointerType',{value:kind});target.dispatchEvent(e);},
  async lang(code){d.documentElement.setAttribute('data-harmat-language',code);await tick();await tick();}
 };
 t.after(()=>{assert.deepEqual(protectedNodes.map(n=>n.outerHTML),protectedHTML);assert.deepEqual(effects,[]);w.close();});
 return f;
}
test('six existing slots, manual next/previous wrap, original anchors remain usable',async t=>{
 const f=await fixture(t);f.boot();const original=f.codes();
 assert.equal(f.controls.nextElementSibling,f.grid);assert.equal(f.grid.children.length,6);
 f.controls.querySelector('.hhar-next').click();assert.notDeepEqual(f.codes(),original);assert.equal(new Set(f.codes()).size,6);
 for(const card of f.cards){assert.equal(card.querySelector('h3').textContent,card.dataset.code);assert.ok(card.querySelector('a').href.includes(card.dataset.code.toLowerCase()));}
 f.controls.querySelector('.hhar-next').click();assert.deepEqual(f.codes(),original);
 f.controls.querySelector('.hhar-prev').click();assert.notDeepEqual(f.codes(),original);
 assert.deepEqual([...f.grid.children],f.cards);
});
test('automatic twelve-second cycle only while visible',async t=>{
 const f=await fixture(t);f.boot();const first=f.codes();assert.equal(f.advance(),false);
 f.visible();assert.ok(f.advance());assert.notDeepEqual(f.codes(),first);assert.ok(f.advance());assert.deepEqual(f.codes(),first);
 f.visible(false);assert.equal(f.advance(),false);
});
test('mouse hover pauses temporarily and leaving resumes',async t=>{
 const f=await fixture(t);f.boot();f.visible();f.pointer('pointerenter');assert.equal(f.advance(),false);f.pointer('pointerleave');assert.ok(f.advance());
});
test('touch and card focus pause persistently, explicit play resumes',async t=>{
 const f=await fixture(t);f.boot();f.visible();f.pointer('pointerdown','touch');assert.equal(f.advance(),false);
 const toggle=f.controls.querySelector('.hhar-toggle');toggle.focus();toggle.click();assert.ok(f.advance());
 f.cards[0].querySelector('a').focus();assert.equal(f.advance(),false);assert.equal(toggle.getAttribute('aria-pressed'),'false');
 toggle.focus();toggle.click();assert.ok(f.advance());
});
test('background document and native quote/chat suspension prevent rotation',async t=>{
 const f=await fixture(t);f.boot();f.visible();const first=f.codes();
 f.d.querySelector('.harmat-local-ai-launch').setAttribute('data-hap-suspended','1');f.advance();assert.deepEqual(f.codes(),first);
 f.d.querySelector('.harmat-local-ai-launch').setAttribute('data-hap-suspended','0');f.d.querySelector('#harmat-local-ai-panel').classList.add('is-open');f.advance();assert.deepEqual(f.codes(),first);
 f.d.querySelector('#harmat-local-ai-panel').classList.remove('is-open');Object.defineProperty(f.d,'hidden',{configurable:true,value:true});f.d.dispatchEvent(new f.w.Event('visibilitychange'));assert.equal(f.advance(),false);
 Object.defineProperty(f.d,'hidden',{configurable:true,value:false});f.d.dispatchEvent(new f.w.Event('visibilitychange'));assert.ok(f.advance());assert.notDeepEqual(f.codes(),first);
});
test('reduced motion starts paused but explicit play allowed',async t=>{
 const f=await fixture(t,{reduced:true});f.boot();f.visible();assert.equal(f.advance(),false);
 f.controls.querySelector('.hhar-toggle').click();assert.ok(f.advance());
 const motion=f.queries.get('(prefers-reduced-motion: reduce)');motion.matches=true;motion.handlers.forEach(fn=>fn());assert.equal(f.advance(),false);
});
test('mobile original three/six expansion survives rotation and HU/ZH roundtrip',async t=>{
 const f=await fixture(t,{mobile:true});f.boot();const toggle=f.section.querySelector('.hhf-apartment-toggle');
 assert.equal(f.cards.filter(c=>!c.hidden).length,3);f.controls.querySelector('.hhar-next').click();assert.equal(f.cards.filter(c=>!c.hidden).length,3);
 toggle.click();assert.equal(f.cards.filter(c=>!c.hidden).length,6);const codes=f.codes();
 await f.lang('zh-Hans');assert.deepEqual(f.codes(),codes);assert.ok(f.cards[0].textContent.includes('栋'));assert.ok(f.cards[0].textContent.includes('个房间'));
 assert.equal(f.controls.querySelector('.hhar-next').title,'下一组户型');
 await f.lang('hu');assert.deepEqual(f.codes(),codes);assert.ok(f.cards[0].textContent.includes('épület'));assert.ok(!f.cards[0].textContent.includes('栋'));
 toggle.click();assert.equal(f.cards.filter(c=>!c.hidden).length,3);
});
test('hidden price stays hidden and language-owned data does not revert to original six',async t=>{
 const f=await fixture(t,{mutate:({d})=>{const n=d.querySelector('#harmat-home-rotation-data'),rows=JSON.parse(n.textContent);rows[6].price=null;n.textContent=JSON.stringify(rows);}});
 f.boot();f.controls.querySelector('.hhar-next').click();assert.equal(f.cards[0].querySelector('strong').textContent,'Ár egyeztetés alapján');
 await f.lang('zh-Hans');assert.equal(f.cards[0].querySelector('strong').textContent,'价格面议');assert.equal(f.cards[0].dataset.code,'A1-F-L2');
 await f.lang('hu');assert.equal(f.cards[0].querySelector('strong').textContent,'Ár egyeztetés alapján');
});
test('idempotent boot and full final page of six unique apartments',async t=>{
 const f=await fixture(t,{mutate:({d})=>{const n=d.querySelector('#harmat-home-rotation-data');n.textContent=JSON.stringify(JSON.parse(n.textContent).slice(0,7));}});
 f.boot();f.boot();assert.equal(f.section.querySelectorAll('.hhar-controls').length,1);f.controls.querySelector('.hhar-next').click();assert.equal(new Set(f.codes()).size,6);assert.equal(f.codes()[5],'A1-F-L2');
});
for(const [name,mutate] of Object.entries({
 'missing readiness':({d})=>delete d.querySelector('#harmat-home-family').dataset.homeFamilyReady,
 'five cards':({cards})=>cards[5].remove(),
 'duplicate root':({d})=>d.body.insertAdjacentHTML('beforeend','<div id="harmat-home-family"></div>'),
 'invalid JSON':({d})=>d.querySelector('#harmat-home-rotation-data').textContent='bad',
 'only six':({d})=>{const n=d.querySelector('#harmat-home-rotation-data');n.textContent=JSON.stringify(JSON.parse(n.textContent).slice(0,6));},
 'foreign url':({d})=>{const n=d.querySelector('#harmat-home-rotation-data'),rows=JSON.parse(n.textContent);rows[6].url='https://evil.test/';n.textContent=JSON.stringify(rows);},
 'unmatched code':({d})=>{const n=d.querySelector('#harmat-home-rotation-data'),rows=JSON.parse(n.textContent);rows[0].code='A1-1-L99';n.textContent=JSON.stringify(rows);},
 'unsafe price':({d})=>{const n=d.querySelector('#harmat-home-rotation-data'),rows=JSON.parse(n.textContent);rows[6].price='hidden';n.textContent=JSON.stringify(rows);},
 'missing card field':({cards})=>cards[5].querySelector('strong').remove(),
 'missing observer':({w})=>delete w.IntersectionObserver
}))test(`fail-safe unchanged: ${name}`,async t=>{const f=await fixture(t,{mutate});f.boot();await tick();assert.equal(f.d.body.innerHTML,f.before);assert.equal(f.controls,null);});
test('no network, storage, form submission or automatic navigation APIs',()=>{
 assert.ok(!/fetch\(|XMLHttpRequest|sendBeacon|localStorage|sessionStorage|register_rest_route|requestSubmit|\.submit\(|location\s*=|window\.open/.test(script));
 assert.ok(script.includes('},12000)'));assert.ok(script.includes('cloneNode(true)'));
});
