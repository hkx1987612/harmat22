(function () {
  "use strict";
  var module = document.getElementById("SR7_1_1");
  var video = document.getElementById("harmat-native-home-video");
  if (!module || !video || video.dataset.installed) return;
  video.dataset.installed = "1";
  video.muted = true;
  video.defaultMuted = true;
  video.playsInline = true;
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)");
  var connection = navigator.connection;
  var inView = false;
  var failed = false;
  var pending = false;
  var watchdog = null;

  function eligible() {
    return inView && !document.hidden && !reduced.matches
      && !(connection && connection.saveData) && !failed;
  }
  function poster() {
    module.classList.remove("harmat-youtube-playing");
  }
  function clearWatchdog() {
    window.clearTimeout(watchdog);
    watchdog = null;
  }
  function fail() {
    failed = true;
    clearWatchdog();
    poster();
    video.pause();
    video.removeAttribute("src");
    video.load();
  }
  function sync() {
    if (!eligible()) {
      clearWatchdog();
      video.pause();
      return;
    }
    if (!video.getAttribute("src")) video.src = video.dataset.src;
    if (!video.paused || pending) return;
    pending = true;
    watchdog = window.setTimeout(fail, 20000);
    var play = video.play();
    if (play && typeof play.then === "function") {
      play.then(function () {
        pending = false;
        if (!eligible()) video.pause();
      }).catch(function (error) {
        pending = false;
        clearWatchdog();
        if (error.name !== "AbortError") fail();
        else if (eligible()) sync();
      });
    } else {
      pending = false;
    }
  }
  video.addEventListener("playing", function () {
    clearWatchdog();
    function reveal() {
      if (eligible() && !video.paused && video.readyState >= 2) {
        module.classList.add("harmat-youtube-playing");
      }
    }
    if (video.requestVideoFrameCallback) video.requestVideoFrameCallback(reveal);
    else window.requestAnimationFrame(reveal);
  });
  video.addEventListener("error", function () {
    if (!failed) fail();
  });
  document.addEventListener("visibilitychange", sync);
  window.addEventListener("pagehide", function () { clearWatchdog(); video.pause(); });
  window.addEventListener("pageshow", sync);
  if (reduced.addEventListener) reduced.addEventListener("change", function () {
    sync();
    if (reduced.matches) poster();
  });
  if (window.IntersectionObserver) {
    new IntersectionObserver(function (entries) {
      inView = entries[0].isIntersecting && entries[0].intersectionRatio > 0;
      sync();
    }, { threshold: [0, 0.01] }).observe(module);
  } else {
    inView = true;
    sync();
  }
})();
