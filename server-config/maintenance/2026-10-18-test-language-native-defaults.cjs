'use strict';
// Actual native scripts in saved public HTML. Every request is intercepted locally.
const test=require('node:test'), assert=require('node:assert/strict');
const fs=require('node:fs'), path=require('node:path');
const ROOT=path.resolve(__dirname,'../..');
const {JSDOM}=require(path.join(ROOT,'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const snapshots=path.join(ROOT,'outputs/2026-10-17-internal-language');
const manifest=JSON.parse(fs.readFileSync(path.join(snapshots,'manifest.json'),'utf8'));
const frontData=JSON.parse(fs.readFileSync(path.join(snapshots,'front-data.json'),'utf8'));
const dir=path.join(ROOT,'wp-mu-plugins/harmat-internal-language');
const dictionary=JSON.parse(fs.readFileSync(path.join(dir,'zh-Hans.json'),'utf8'));
const native=fs.readFileSync(path.join(ROOT,'wp-mu-plugins/harmat-unified-offer-modal.php'),'utf8');
const tick=()=>new Promise(resolve=>setTimeout(resolve,0));
const hu=code=>'A '+code+' lakás iránt érdeklődöm.';
const zh=code=>'我想了解 '+code+' 这套公寓。';
function setup(route='/elerhetosegeink/') {
  const row=manifest.find(r=>new URL(r.url).pathname===route);assert.ok(row);
  const dom=new JSDOM(fs.readFileSync(path.join(snapshots,row.file),'utf8'),{url:'https://harmat22.hu'+route,runScripts:'outside-only'});
  const w=dom.window,d=w.document,sent=[];
  w.fetch=async(url,options)=>{sent.push({url,method:options.method,data:[...options.body.entries()]});return {ok:false,json:async()=>({message:'LOCAL_ONLY_NO_SEND'})};};
  w.XMLHttpRequest=function(){throw Error('NETWORK_FORBIDDEN');};
  w.harmatSalesFront=JSON.parse(JSON.stringify(frontData[route]));
  // The saved Contact page has no page-local items; supply only captured public
  // property records through the native owner's existing global inventory slot.
  w.harmatOfferApartments=JSON.parse(JSON.stringify(Object.values(frontData)
    .flatMap(data=>Object.values(data.items||{}))));
  for(const id of ['harmat-unified-offer-gate','harmat-unified-offer-modal-js']) {
    const match=native.match(new RegExp('<script id="'+id+'">([\\s\\S]*?)<\\/script>'));assert.ok(match,id);
    const js=match[1].replace('<?php echo wp_json_encode($endpoint); ?>',JSON.stringify('https://harmat22.hu/wp-json/harmat-sales-manager/v1/offer'))
      .replace('<?php echo wp_json_encode($thank_you_url); ?>',JSON.stringify('https://harmat22.hu/koszonjuk/'));
    assert.ok(!js.includes('<?php'));w.eval(js);
  }
  w.eval(fs.readFileSync(path.join(dir,'patterns.js'),'utf8'));
  w.eval(fs.readFileSync(path.join(dir,'runtime.js'),'utf8'));
  const tr=w.HarmatInternalLanguage.create(d,dictionary,{enabled:true,storage:false,translateDynamic:w.HarmatLanguagePatterns.translate});
  const form=d.getElementById('h22-offer-form'), field=d.getElementById('h22-offer-message');
  return {w,d,tr,form,field,sent,open(code) {const a=d.createElement('a');a.href='/property/'+code.toLowerCase()+'/#opal-contactform-popup';a.textContent='Ajánlatkérés';d.body.append(a);w.harmatUnifiedOfferOpen(a);a.remove();},close(){tr.destroy();w.close();}};
}
function entries(f){return [...new f.w.FormData(f.form).entries()];}
function change(f,id,value){const e=f.d.getElementById(id);e.value=value;e.dispatchEvent(new f.w.Event('change',{bubbles:true}));}

test('real native defaults follow Chinese/HU and successive apartment selections',async()=>{
  const f=setup();f.tr.setLanguage('zh-Hans');f.open('A1-1-L2');await tick();
  assert.equal(f.field.value,zh('A1-1-L2'));assert.equal(f.field.dataset.h22AutoMessage,f.field.value);
  change(f,'h22-offer-building','A3');change(f,'h22-offer-floor','4');change(f,'h22-offer-apartment','A3-4-L5');await tick();
  assert.equal(f.d.getElementById('h22-offer-apartment').value,'A3-4-L5');
  assert.equal(f.field.value,zh('A3-4-L5'));assert.equal(f.field.dataset.h22AutoMessage,f.field.value);
  f.tr.setLanguage('hu');assert.equal(f.field.value,hu('A3-4-L5'));assert.equal(f.field.dataset.h22AutoMessage,f.field.value);
  f.tr.setLanguage('zh-Hans');assert.equal(f.field.value,zh('A3-4-L5'));
  assert.equal(f.sent.length,0);f.close();
});

test('real native close/reopen remains one owner and does not strand an old apartment default',async()=>{
  const f=setup();f.open('A1-1-L2');f.tr.setLanguage('zh-Hans');await tick();
  f.d.querySelector('[data-h22-offer-close]').click();f.open('A3-4-L5');await tick();
  assert.equal(f.field.value,zh('A3-4-L5'));assert.equal(f.d.getElementById('h22-offer-modal').hidden,false);
  assert.equal(f.d.querySelectorAll('#h22-offer-form').length,1);assert.equal(f.sent.length,0);
  f.tr.destroy();assert.equal(f.field.value,hu('A3-4-L5'));assert.equal(f.field.dataset.h22AutoMessage,hu('A3-4-L5'));f.w.close();
});

for(const draft of ['客户自己写的留言','Please call after 17:00',hu('A1-1-L2'),zh('A1-1-L2')]) {
  test('real customer-edited draft survives language/reopen/select '+draft,async()=>{
    const f=setup();f.tr.setLanguage('zh-Hans');f.open('A1-1-L2');await tick();
    f.field.value=draft;f.field.dispatchEvent(new f.w.Event('input',{bubbles:true}));
    f.tr.setLanguage('hu');f.tr.setLanguage('zh-Hans');
    f.d.querySelector('[data-h22-offer-close]').click();f.open('A3-4-L5');await tick();
    assert.equal(f.field.value,draft);f.tr.destroy();assert.equal(f.field.value,draft);
    assert.equal(f.sent.length,0);f.w.close();
  });
}

for(const lead of ['Kültéri hirdetés','Google keresés','ingatlan.com','Közösségi média','Egyéb']) {
  test('native shadow payload changes only owned default message '+lead,async()=>{
    const f=setup('/property/a1-1-l2/');f.open('A1-1-L2');
    for(const [name,value] of Object.entries({'your-name':'LOCAL TEST NO NETWORK','your-email':'test@example.invalid','your-phone':'+36000000000','your-date':'2026-10-20','your-time':'12:00-15:00'}))f.form.elements.namedItem(name).value=value;
    f.form.elements.namedItem('privacy-acceptance').checked=true;
    [...f.form.querySelectorAll('[name="lead_source"]')].find(e=>e.value===lead).checked=true;
    const nodes=[...f.form.elements],before=entries(f);
    f.tr.setLanguage('zh-Hans');await tick();
    const expected=before.map(([key,value])=>[key,key==='your-message'?zh('A1-1-L2'):value]);
    assert.deepEqual(entries(f),expected);assert.deepEqual([...f.form.elements],nodes);
    f.form.dispatchEvent(new f.w.Event('submit',{bubbles:true,cancelable:true}));await tick();
    assert.equal(f.sent.length,1);assert.equal(f.sent[0].method,'POST');
    const sent=new Map(f.sent[0].data);assert.equal(sent.get('your-message'),zh('A1-1-L2'));
    assert.equal(sent.get('lead_source'),lead);assert.equal(sent.get('selected-apartment'),'A1-1-L2');
    for(const key of ['selected-area','selected-price','your-date','your-time','privacy-acceptance'])assert.equal(sent.get(key),new Map(before).get(key));
    f.close();
  });
}

test('native validation still rejects unchecked privacy in Chinese without any request',async()=>{
  const f=setup();f.tr.setLanguage('zh-Hans');f.open('A1-1-L2');await tick();
  f.form.elements.namedItem('privacy-acceptance').checked=false;
  f.form.dispatchEvent(new f.w.Event('submit',{bubbles:true,cancelable:true}));await tick();
  assert.equal(f.sent.length,0);assert.equal(f.form.elements.namedItem('privacy-acceptance').required,true);
  assert.equal(f.field.value,zh('A1-1-L2'));f.close();
});
