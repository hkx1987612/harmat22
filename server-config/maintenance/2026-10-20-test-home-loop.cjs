'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process');
const {fixture,tick,sha,script,candidate,ROOT,PHP,DIR}=require('./2026-10-20-home-loop-fixture.cjs');
const changes=[
  [
    "  let userPaused = false;\n  let manualVideoPlayback = false;\n",
    ""
  ],
  [
    "    const label = controlText(videoFailed ? 'videoUnavailable' : paused ? 'videoPlay' : 'videoPause');\n    playIcon.toggleAttribute('hidden', !paused);\n    pauseIcon.toggleAttribute('hidden', paused);",
    "    const label = controlText(videoFailed ? 'videoUnavailable' : 'videoPlay');\n    playIcon.toggleAttribute('hidden', !paused);\n    pauseIcon.setAttribute('hidden', '');\n    // Keep a play-only fallback when the browser declines muted autoplay.\n    videoButton.hidden = !paused || videoFailed;\n    videoTooltip.hidden = videoButton.hidden;"
  ],
  [
    "    return !videoFailed && !document.hidden && !userPaused\n      && (!reducedMotion.matches || manualVideoPlayback);",
    "    return !videoFailed && !document.hidden;"
  ],
  [
    "    manualVideoPlayback = false;\n    if (!video.paused) video.pause();",
    "    if (!video.paused) video.pause();"
  ],
  [
    "  videoButton.addEventListener('click', () => {\n    if (video.paused) {\n      userPaused = false;\n      manualVideoPlayback = true;\n      playVideo();\n    } else {\n      userPaused = true;\n      pauseHeroVideo();\n    }\n  });",
    "  videoButton.addEventListener('click', () => {\n    if (video.paused) playVideo();\n  });"
  ],
  [
    "  reducedMotion.addEventListener('change', () => {\n    manualVideoPlayback = false;\n    syncHeroPlayback();\n  });\n",
    ""
  ],
  [
    "  videoControl.hidden = false;\n  updateVideoControl();",
    "  video.autoplay = true;\n  video.loop = true;\n  video.muted = true;\n  video.playsInline = true;\n  videoControl.hidden = false;\n  updateVideoControl();"
  ]
];

test('only explicit hero playback changes; panorama/mobile toggle/translation retained',()=>{
 const old=fs.readFileSync(path.join(DIR,'home-family-six-r2.js'),'utf8');
 assert.equal(sha(old),'514ac9461c3751804b001513c831c0993573b781b701b213286fab507138e766');
 let expected=old;
 for(const [before,after] of changes){assert.equal(expected.split(before).length,2);expected=expected.replace(before,after);}
 assert.equal(script,expected);
 assert.equal(sha(candidate),'fb9e31a153fde3e6264b236abf435ddb4de424ab08b85e05f372a24f230e0f88');
 const panorama=s=>s.slice(s.indexOf('  function initPanorama() {'),s.indexOf('  updatePanoramaLanguage = initPanorama();'));
 assert.equal(panorama(script),panorama(old));
});

for(const observers of [true,false])for(const reduced of [true,false])test(`autoplay/loop/play-only UI IO=${observers} reduced=${reduced}`,t=>{
 const f=fixture(t,{observers,reduced,heroVisible:false});f.boot();
 assert.equal(f.video.paused,false);assert.equal(f.video.muted,true);assert.equal(f.video.loop,true);
 assert.equal(f.video.autoplay,true);assert.equal(f.video.playsInline,true);
 assert.equal(f.button.hidden,true);assert.equal(f.root.querySelector('.hhf-video-pause-icon').hasAttribute('hidden'),true);
 assert.equal(f.root.querySelector('.hhf-video-control').hidden,false,'fullscreen owner remains available');
 const before=f.heroCalls();f.button.click();f.scroll(false);f.scroll(true);f.setReduced(!reduced);
 assert.deepEqual(f.heroCalls(),before,'no click-to-pause or scroll/motion pause');assert.equal(f.video.currentTime,17.25);
 assert.deepEqual(f.writes,[['src',f.video.dataset.src]]);
});

test('background pauses, foreground resumes without seek/source reset',t=>{
 const f=fixture(t,{reduced:true});f.boot();f.setHidden(true);assert.equal(f.video.paused,true);
 f.scroll(false);f.setHidden(false);assert.equal(f.video.paused,false);assert.equal(f.button.hidden,true);
 assert.equal(f.video.currentTime,17.25);assert.deepEqual(f.heroCalls(),['play','pause','play']);
});

test('initial background defers source, then foreground automatically plays',t=>{
 const f=fixture(t,{hidden:true,reduced:true});f.boot();assert.equal(f.video.hasAttribute('src'),false);
 f.setHidden(false);assert.equal(f.video.paused,false);assert.equal(f.video.loop,true);
});

for(const play of ['reject','throw'])test(`blocked autoplay has play-only fallback: ${play}`,async t=>{
 const options={play,reduced:true};const f=fixture(t,options);f.boot();await tick();
 assert.equal(f.video.paused,true);assert.equal(f.button.hidden,false);assert.equal(f.button.disabled,false);
 assert.equal(f.root.querySelector('.hhf-video-pause-icon').hasAttribute('hidden'),true);
 options.play=undefined;f.button.click();await tick();assert.equal(f.video.paused,false);assert.equal(f.button.hidden,true);
 f.button.click();assert.equal(f.video.paused,false,'no pause path even synthetic repeated click');
});

test('pending autoplay that settles in background is safely paused',t=>{
 const f=fixture(t,{play:'pending'});f.boot();f.setHidden(true);f.finishPlay();assert.equal(f.video.paused,true);
 f.setHidden(false);f.finishPlay();assert.equal(f.video.paused,false);assert.equal(f.button.hidden,true);
});

test('media error retains poster/status, hides unusable button and does not retry forever',async t=>{
 const f=fixture(t);f.boot();f.video.dispatchEvent(new f.w.Event('error'));
 assert.equal(f.video.dataset.revealed,undefined);assert.equal(f.button.hidden,true);assert.equal(f.button.disabled,true);
 const before=f.heroCalls();f.setHidden(true);f.setHidden(false);f.button.click();assert.deepEqual(f.heroCalls(),[...before,'pause']);
 f.setHidden(true);f.setHidden(false);assert.deepEqual(f.heroCalls(),[...before,'pause']);
 await f.language('zh-Hans');assert.ok(f.root.querySelector('#hhf-video-status').textContent.includes('视频'));
});

test('language switch does not expose pause or restart the media',async t=>{
 const f=fixture(t);f.boot();const before=f.heroCalls();
 for(const lang of ['zh-Hans','hu','zh-Hans','hu']){await f.language(lang);assert.equal(f.button.hidden,true);assert.equal(f.root.querySelector('.hhf-video-pause-icon').hasAttribute('hidden'),true);}
 assert.deepEqual(f.heroCalls(),before);
});

test('desktop fullscreen remains available while the pause button is hidden',async t=>{
 const f=fixture(t);f.boot();let fullscreen=null;
 Object.defineProperty(f.d,'fullscreenElement',{get:()=>fullscreen});Object.defineProperty(f.d,'fullscreenEnabled',{value:true});
 f.w.matchMedia=q=>({matches:q.includes('min-width'),addEventListener(){}});
 f.hero.requestFullscreen=async()=>{fullscreen=f.hero;f.d.dispatchEvent(new f.w.Event('fullscreenchange'));};
 f.d.exitFullscreen=async()=>{fullscreen=null;f.d.dispatchEvent(new f.w.Event('fullscreenchange'));};
 const output=execFileSync(PHP,[path.join(__dirname,'2026-10-19-test-home-video-fullscreen.php'),'--render'],{encoding:'utf8'});
 f.w.eval(output.match(/<script id="harmat-home-video-fullscreen-js">([\s\S]*?)<\/script>/)[1]);await tick();
 const control=f.d.querySelector('#harmat-home-video-fullscreen-control'),button=f.d.querySelector('#harmat-home-video-fullscreen-button');
 assert.equal(control.hidden,false);assert.equal(f.button.hidden,true);const before=f.heroCalls();
 button.click();await tick();assert.equal(fullscreen,f.hero);button.click();await tick();assert.equal(fullscreen,null);
 assert.deepEqual(f.heroCalls(),before);assert.equal(f.video.paused,false);
});
