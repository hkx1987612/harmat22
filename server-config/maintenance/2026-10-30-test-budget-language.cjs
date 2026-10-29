// Real retained translation runtime integration; child timeout detects observer starvation.
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
if(!process.argv.includes('--worker')) {
    try { process.stdout.write(execFileSync(process.execPath,[__filename,'--worker'],{encoding:'utf8',timeout:15000})); }
    catch(error) { console.error('FAIL: real language integration did not settle within its bounded worker'); process.exitCode=1; }
} else {
    const root=path.resolve(__dirname,'../..');
    const {JSDOM}=require(path.join(root,'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
    const data=JSON.parse(execFileSync('C:/tools/php-8.5.6/php.exe',[path.join(__dirname,'2026-10-29-search-budget-fixture.php'),path.join(root,'wp-plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php'),path.join(root,'wp-mu-plugins/harmat-performance-guard.php')],{encoding:'utf8'}));
    const langDir=path.join(root,'wp-mu-plugins/harmat-internal-language');
    (async()=>{
        const dom=new JSDOM('<html lang="hu"><body><main id="main">'+data.html+'</main></body></html>',{runScripts:'outside-only',url:'https://harmat22.hu/lakaskereso/'});
        const w=dom.window,d=w.document;
        w.eval(data.js);
        for(const markup of [data.patch,data.comfort]) w.eval(new JSDOM(markup).window.document.querySelector('script').textContent);
        w.eval(fs.readFileSync(path.join(langDir,'runtime.js'),'utf8'));
        w.eval(fs.readFileSync(path.join(langDir,'patterns.js'),'utf8'));
        const api=w.HarmatInternalLanguage.create(d,JSON.parse(fs.readFileSync(path.join(langDir,'zh-Hans.json'),'utf8')),{enabled:true,storage:false,translateDynamic:w.HarmatLanguagePatterns.translate});
        const settle=()=>new Promise(r=>setTimeout(r,100));
        let changes=0;
        new w.MutationObserver(records=>{changes+=records.length;}).observe(d.documentElement,{attributes:true,attributeFilter:['data-harmat-language']});
        for(const name of ['budgetMin','budgetMax']) {
            const field=d.querySelector('[data-filter='+name+']');field.value=name==='budgetMin'?'50':'80';field.dispatchEvent(new w.Event('input',{bubbles:true}));
        }
        for(let i=0;i<4;i++) {
            api.setLanguage('zh-Hans');await settle();
            assert.equal(d.querySelector('[data-budget-copy=title]').textContent,'房屋总价（百万福林）');
            assert.equal(d.querySelector('[data-count]').textContent,'3');
            assert.equal(d.querySelector('[data-filter=budgetMin]').value,'50');
            api.setLanguage('hu');await settle();
            assert.equal(d.querySelector('[data-budget-copy=title]').textContent,'Teljes vételár (millió Ft)');
        }
        const stable=changes;await settle();assert.equal(changes,stable);assert.ok(changes<40);
        api.destroy();await settle();w.close();
        console.log('PASS real HU/ZH runtime: 8 switches, budget preserved, observer settled ('+changes+' marker mutations)');
    })().catch(error=>{console.error(error);process.exitCode=1;});
}
