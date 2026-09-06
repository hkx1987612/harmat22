import http from 'node:http';
import { readFileSync } from 'node:fs';
const root = new URL('../../', import.meta.url);
const read = file => readFileSync(new URL(file, root));
const page = `<!doctype html><html lang="hu"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/viewer.css"><style>
body{margin:0;font:14px Arial;background:#fff}#buildingViewer{height:620px;max-width:1100px;margin:auto;position:relative}
.tools{display:flex;gap:10px;padding:10px}#compassSlider{width:80%}#report{white-space:pre-wrap}
@media(max-width:767px){#buildingViewer{height:auto;aspect-ratio:4/3;width:100%}}
</style><div class="tools"><button id="stress">Forgatási teszt</button><button id="slow">Lassú képkocka</button><button id="fail">Hibás képkocka</button></div>
<div id="mainLayout"><div id="buildingViewer" class="viewer-container">
<img id="lakasparkPoster" class="viewer-image" src="/frames/bld-test-frame-01.webp" alt="Harmat épületnézet">
<svg id="hitboxLayer" class="viewer-svg" viewBox="0 0 1920 1080"></svg><div id="viewerTooltip"></div>
<div class="rotation-controls"><button id="rotateLeftBtn">Balra</button><button id="rotateRightBtn">Jobbra</button></div>
</div></div><input id="compassSlider" aria-label="Épületnézet vezérlése" type="range" min="1" max="72" value="1"><pre id="report">Várakozás</pre>
<script>window.LakasparkData={scene:'test',baseUrl:'/frames/',jsonUrl:'/hitboxes.json',toggle:'off'};</script>
<script src="/viewer.js"></script><script>
document.addEventListener('DOMContentLoaded',()=>{
 const slider=document.getElementById('compassSlider'), report=document.getElementById('report');
 const stats={samples:0,blank:0,frames:[],finished:false,mode:'ready'};
 function go(n){slider.value=n;slider.dispatchEvent(new Event('input'));}
 function sample(){const c=document.querySelector('canvas');if(c?.dataset.painted){
  const p=c.getContext('2d').getImageData(c.width/2,c.height/2,8,8).data;
  let blank=0;for(let i=0;i<p.length;i+=4)if(p[i+3]===0||(p[i]>248&&p[i+1]>248&&p[i+2]>248))blank++;
  stats.samples++;if(blank===64)stats.blank++;if(!stats.frames.includes(c.dataset.frame))stats.frames.push(c.dataset.frame);
 }report.textContent=JSON.stringify(stats);requestAnimationFrame(sample);}sample();
 document.getElementById('stress').onclick=()=>{stats.mode='stress';stats.finished=false;let n=0;const timer=setInterval(()=>{
  go(n<72?n+1:144-n);if(++n===144){clearInterval(timer);stats.finished=true;}
 },45);};
 document.getElementById('slow').onclick=()=>{stats.mode='slow';go(40);};
 document.getElementById('fail').onclick=()=>{stats.mode='failed';go(50);};
});</script></html>`;
const server = http.createServer((req,res)=>{
 const path = new URL(req.url,'http://localhost').pathname;
 if(path==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(page);}
 if(path==='/mobile'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end('<!doctype html><html><body style="margin:0"><iframe title="390px mobile" src="/" style="border:0;width:390px;height:844px"></iframe></body></html>');}
 if(path==='/viewer.js'||path==='/viewer.css'){
  res.setHeader('Content-Type',path.endsWith('js')?'text/javascript':'text/css');
  return res.end(read('wp-plugins/360'+path));
 }
 if(path==='/hitboxes.json'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify(Object.fromEntries(Array.from({length:72},(_,i)=>[i+1,{}]))));}
 const match=path.match(/^\/frames\/bld-test-frame-(\d{2})\.webp$/);
 if(match){
  const frame=Number(match[1]);
  if(frame===50){res.statusCode=503;return res.end('Controlled image failure');}
  const send=()=>{res.setHeader('Content-Type','image/webp');res.end(read('outputs/360-rotation/frame-'+(frame%2?'01':'02')+'.webp'));};
  if(frame===40)return setTimeout(send,4000);
  return send();
 }
 res.statusCode=404;res.end();
});
server.listen(8767,'127.0.0.1',()=>console.log('360 test fixture: http://127.0.0.1:8767'));
