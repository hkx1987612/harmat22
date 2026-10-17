'use strict';
// Native HTML/JS in a no-network DOM. Captured submissions never leave this process.
const test=require('node:test');const assert=require('node:assert/strict');
const fs=require('node:fs');const path=require('node:path');
const ROOT=path.resolve(__dirname,'../..');
const {JSDOM}=require(path.join(ROOT,'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const dir=path.join(ROOT,'wp-mu-plugins/harmat-internal-language');
const snapshots=path.join(ROOT,'outputs/2026-10-17-internal-language');
const manifest=JSON.parse(fs.readFileSync(path.join(snapshots,'manifest.json'),'utf8'));
const frontData=JSON.parse(fs.readFileSync(path.join(snapshots,'front-data.json'),'utf8'));
const dictionary=JSON.parse(fs.readFileSync(path.join(dir,'zh-Hans.json'),'utf8'));
const runtime=fs.readFileSync(path.join(dir,'runtime.js'),'utf8');
const patterns=fs.readFileSync(path.join(dir,'patterns.js'),'utf8');
const native=fs.readFileSync(path.join(ROOT,'wp-mu-plugins/harmat-unified-offer-modal.php'),'utf8');
const tick=()=>new Promise(resolve=>setTimeout(resolve,0));
function source(route){const row=manifest.find(r=>new URL(r.url).pathname===route);assert.ok(row,route);return fs.readFileSync(path.join(snapshots,row.file),'utf8');}
function setup(route, offer=false){
  const dom=new JSDOM(source(route),{url:'https://harmat22.hu'+route,runScripts:'outside-only'});
  const w=dom.window,d=w.document;const sent=[];
  w.fetch=async(url,options)=>{sent.push({url,method:options.method,data:[...options.body.entries()]});return {ok:false,json:async()=>({message:'LOCAL_INTERCEPT_NO_SEND'})};};
  w.XMLHttpRequest=function(){throw new Error('NETWORK_BLOCKED');};
  if(offer){
    w.harmatSalesFront=JSON.parse(JSON.stringify(frontData[route]));
    for(const id of ['harmat-unified-offer-gate','harmat-unified-offer-modal-js']){
      const escaped=id.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
      const match=native.match(new RegExp('<script id="'+escaped+'">([\\s\\S]*?)<\\/script>'));
      assert.ok(match,id);
      let js=match[1].replace('<?php echo wp_json_encode($endpoint); ?>',JSON.stringify('https://harmat22.hu/wp-json/harmat-sales-manager/v1/offer'))
        .replace('<?php echo wp_json_encode($thank_you_url); ?>',JSON.stringify('https://harmat22.hu/koszonjuk/'));
      assert.ok(!js.includes('<?php'));w.eval(js);
    }
  }
  w.eval(patterns);w.eval(runtime);
  const tr=w.HarmatInternalLanguage.create(d,dictionary,{enabled:true,storage:false,translateDynamic:w.HarmatLanguagePatterns.translate});
  return {w,d,tr,sent,close(){tr.destroy();w.close();}};
}
function formState(f){return JSON.stringify([...new f.w.FormData(f.d.getElementById('h22-offer-form')).entries()]);}
function fill(f,lead='Google keresés'){
  const form=f.d.getElementById('h22-offer-form');
  for(const [name,value] of Object.entries({'your-name':'LOCAL TEST - NO NETWORK','your-email':'test@example.invalid','your-phone':'+36000000000','your-date':'2026-10-20','your-time':'12:00-15:00','your-message':'原文保留 Google keresés'}))form.elements.namedItem(name).value=value;
  form.elements.namedItem('privacy-acceptance').checked=true;
  [...form.querySelectorAll('[name="lead_source"]')].find(n=>n.value===lead).checked=true;
  return form;
}

for(const route of ['/','/harmat-lakopark/','/magunkrol/','/elerhetosegeink/','/lakaskereso/','/property/a1-f-l1/','/property/a1-1-l2/','/virtualis-lakasvalaszto/','/virtualis-lakasvalaszto-elso-utem/','/virtualis-lakasvalaszto-a1-epulet/','/virtualis-lakasvalaszto-a2-epulet/','/virtualis-lakasvalaszto-a3-epulet/','/virtualis-lakasvalaszto-a4-epulet/','/galeria/','/epitesi-naplo/','/adatvedelmi-tajekoztato/','/cookie-tajekoztato/','/felhasznalasi-feltetelek/','/impresszum/']){
  test('real HTML protected attributes and HU roundtrip '+route,()=>{
    const f=setup(route);const control=f.d.getElementById('harmat-internal-language');
    const before=f.d.body.innerHTML;
    const attrs=()=>JSON.stringify([...f.d.querySelectorAll('*')].map(n=>[...n.attributes].filter(a=>!['alt','aria-label','title','placeholder','lang','data-harmat-language','aria-pressed'].includes(a.name)).map(a=>[a.name,a.value])));
    const state=attrs();f.tr.setLanguage('zh-Hans');assert.equal(attrs(),state);
    f.tr.setLanguage('hu');assert.equal(f.d.body.innerHTML,before);assert.ok(control);f.close();
  });
}
test('native header quote opens exactly once after Chinese translation',async()=>{
  const f=setup('/',true);f.tr.setLanguage('zh-Hans');
  const trigger=[...f.d.querySelectorAll('a,button,[role="button"]')].find(n=>n.textContent.trim()==='索取报价');assert.ok(trigger);
  let calls=0;const original=f.w.harmatUnifiedOfferOpen;f.w.harmatUnifiedOfferOpen=t=>{calls++;original(t);};
  trigger.click();await tick();assert.equal(calls,1);assert.equal(f.d.getElementById('h22-offer-modal').hidden,false);
  assert.equal(f.sent.length,0);f.close();
});
test('input descriptions translate without modifying typed values or secure fields',()=>{
  const f=setup('/lakaskereso/');const field=f.d.querySelector('input[type="search"]');assert.ok(field);
  field.value='A3-4-L5';const before=field.getAttribute('placeholder');
  f.tr.setLanguage('zh-Hans');assert.match(field.getAttribute('placeholder'),/例如/);assert.equal(field.value,'A3-4-L5');
  f.tr.setLanguage('hu');assert.equal(field.getAttribute('placeholder'),before);assert.equal(field.value,'A3-4-L5');f.close();
});
for(const lead of ['Kültéri hirdetés','Google keresés','ingatlan.com','Közösségi média','Egyéb']){
  test('native quote payload identity and shadow submission '+lead,async()=>{
    const f=setup('/property/a1-1-l2/',true);const trigger=f.d.querySelector('a[href*="#opal-contactform-popup"]');assert.ok(trigger);
    f.w.harmatUnifiedOfferOpen(trigger);const form=fill(f,lead);const before=formState(f);const nodes=[...form.elements];
    f.tr.setLanguage('zh-Hans');await tick();assert.equal(formState(f),before);assert.deepEqual([...form.elements],nodes);
    for(let i=0;i<6;i++)f.tr.setLanguage(i%2?'zh-Hans':'hu');assert.equal(formState(f),before);
    form.dispatchEvent(new f.w.Event('submit',{bubbles:true,cancelable:true}));await tick();
    assert.equal(f.sent.length,1);const sent=f.sent[0];assert.equal(sent.method,'POST');
    const entries=new Map(sent.data);assert.equal(entries.get('lead_source'),lead);assert.equal(entries.get('selected-apartment'),'A1-1-L2');
    assert.equal(entries.get('selected-area'),new Map(JSON.parse(before)).get('selected-area'));
    assert.equal(entries.get('selected-price'),new Map(JSON.parse(before)).get('selected-price'));
    assert.equal(entries.get('your-message'),'原文保留 Google keresés');
    assert.equal(entries.get('privacy-acceptance'),'1');assert.equal(entries.get('your-time'),'12:00-15:00');
    assert.equal(f.w.location.pathname,'/property/a1-1-l2/');f.close();
  });
}
test('native appointment CTA, required privacy, validation and close work in Chinese without request',async()=>{
  const f=setup('/elerhetosegeink/',true);f.tr.setLanguage('zh-Hans');
  const button=f.d.querySelector('a[href*="#opal-contactform-popup"],button[data-h22-offer-trigger]');
  if(button)button.click();else f.w.harmatUnifiedOfferOpen(null);
  const form=f.d.getElementById('h22-offer-form');fill(f);form.elements.namedItem('privacy-acceptance').checked=false;
  form.dispatchEvent(new f.w.Event('submit',{bubbles:true,cancelable:true}));await tick();
  assert.equal(f.sent.length,0);assert.equal(form.elements.namedItem('privacy-acceptance').required,true);
  assert.match(f.d.querySelector('[data-h22-status]').textContent,/姓名|隐私|Kérjük/);
  f.d.querySelector('[data-h22-offer-close]').click();assert.equal(f.d.getElementById('h22-offer-modal').hidden,true);f.close();
});
test('dynamic property data uses fresh values and never stale exact dictionary paragraphs',()=>{
  const f=setup('/property/a1-f-l1/');f.tr.setLanguage('zh-Hans');
  const text='Az A3-4-L5 a Harmat Lakópark A3 épületének 4. emeletén található, 2 szobás lakás. Az alapterület 41,86 m². A lakáshoz 11,95 m²-es terasz / erkély kapcsolódik. Az értékesítési terület 47,83 m². Az ingatlan jelenlegi státusza: elérhető. Az aktuális vételár 12 345 678 Ft.';
  const chinese=f.tr.translateText(text);assert.match(chinese,/47,83/);assert.match(chinese,/12 345 678 Ft/);assert.match(chinese,/当前售价/);
  assert.equal(chinese.match(/\d+(?:[.,]\d+)*/g).join('|'),text.match(/\d+(?:[.,]\d+)*/g).join('|'));
  assert.equal(f.tr.translateText('A3-4-L5'), 'A3-4-L5');assert.equal(f.tr.translateText('12 345 678 Ft'),'12 345 678 Ft');f.close();
});
test('bootstrap has no HU request; single same-origin dictionary load; session restore and unavailable fallback',async()=>{
  const f=setup('/');f.tr.destroy();f.d.getElementById('harmat-internal-language')?.remove();
  const control=f.d.createElement('div');control.id='harmat-internal-language';
  control.innerHTML='<button type="button" data-hil-language="hu">Magyar</button><button type="button" data-hil-language="zh-Hans" disabled>中文</button><span data-hil-status></span>';f.d.body.append(control);
  const config=f.d.createElement('script');config.id='harmat-internal-language-config';config.type='application/json';config.textContent=JSON.stringify({dictionary:'https://harmat22.hu/wp-content/mu-plugins/harmat-internal-language/zh-Hans.json?v=test'});f.d.body.append(config);
  let loads=0;f.w.fetch=async url=>{loads++;assert.ok(url.startsWith('https://harmat22.hu/'));return {ok:true,json:async()=>dictionary};};
  f.w.eval(fs.readFileSync(path.join(dir,'bootstrap.js'),'utf8'));assert.equal(loads,0);
  control.querySelector('[data-hil-language="zh-Hans"]').click();await tick();await tick();assert.equal(loads,1);assert.equal(f.d.documentElement.lang,'zh-Hans');
  control.querySelector('[data-hil-language="hu"]').click();assert.equal(f.d.documentElement.lang,'hu');
  control.querySelector('[data-hil-language="zh-Hans"]').click();assert.equal(loads,1);
  f.w.harmatInternalLanguage.destroy();f.w.close();
});
