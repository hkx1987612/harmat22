(function () {
  "use strict";

  var config = window.Harmat22InteractiveConfig || {};
  var base = config.assetBase || "/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/";

  function ready(fn) {
    if (document.readyState !== "loading") {
      fn();
    } else {
      document.addEventListener("DOMContentLoaded", fn);
    }
  }

  function normalize(value) {
    return (value || "").toLowerCase().replace(/\s+/g, " ").trim();
  }

  function asset(file) {
    return base + file;
  }

  function findTargetSection() {
    var mapNode = document.querySelector('iframe[src*="maps"], iframe[src*="google.com"], .leaflet-container, #map, .cml-wrapper');
    if (mapNode) {
      var node = mapNode;
      while (node && node !== document.body) {
        var text = normalize(node.textContent);
        if (
          (node.matches && node.matches("section, .elementor-section, .e-con, .elementor-top-section")) &&
          (text.indexOf("lakópark környezete") !== -1 ||
            text.indexOf("fedezze fel a környéket") !== -1 ||
            text.indexOf("környék előnyei") !== -1)
        ) {
          return node;
        }
        node = node.parentElement;
      }

      return mapNode.closest("section, .elementor-section, .e-con") || mapNode.parentElement;
    }

    var candidates = Array.prototype.slice.call(
      document.querySelectorAll("section, .elementor-section, .e-con, .elementor-top-section")
    );
    var best = null;
    candidates.forEach(function (candidate) {
      var text = normalize(candidate.textContent);
      var hasMapHeading =
        text.indexOf("fedezze fel a") !== -1 ||
        text.indexOf("harmat lak") !== -1 && text.indexOf("korny") !== -1 ||
        text.indexOf("körny") !== -1 && text.indexOf("előny") !== -1;
      var hasMapContent =
        text.indexOf("bev") !== -1 ||
        text.indexOf("park") !== -1 ||
        text.indexOf("google maps") !== -1;

      if (hasMapHeading && hasMapContent) {
        if (!best || candidate.textContent.length < best.textContent.length) {
          best = candidate;
        }
      }
    });

    if (best) {
      return best;
    }

    if (window.location.pathname.indexOf("/harmat-lakopark-kornyeke") !== -1) {
      var pageSections = Array.prototype.slice.call(
        document.querySelectorAll("section, .elementor-section, .e-con, .elementor-top-section")
      );
      for (var i = 0; i < pageSections.length; i++) {
        if (normalize(pageSections[i].textContent).indexOf("fedezze fel a") !== -1) {
          return pageSections[i];
        }
      }
    }

    return null;
  }

  function markup() {
    return [
      '<section class="harmat-interactive" id="harmat-3d-tour">',
      '  <div class="hi-wrap">',
      '    <div class="hi-head">',
      '      <div>',
      '        <p class="hi-eyebrow">Interaktív bemutató</p>',
      '        <h2 class="hi-title">Harmat Lakópark élményközpont</h2>',
      '      </div>',
      '      <p class="hi-lead">Tekintse meg a Harmat Lakópark látványterveit, környezetét és bemutatóanyagait egy áttekinthető, modern felületen.</p>',
      '    </div>',
      '    <div class="hi-console" aria-label="Harmat Lakópark interaktív bemutató">',
      '      <div class="hi-screen">',
      '        <div class="hi-panel active" data-panel="panorama">',
      '          <div class="hi-pano-wrap">',
      '            <img class="hi-fallback" src="' + asset("pano_pano_f.jpg") + '" alt="Harmat Lakópark panoráma előnézet" loading="lazy" decoding="async" fetchpriority="low">',
      '            <div id="harmat-panorama" aria-label="Harmat Lakópark panorámás látványtér"></div>',
      '          </div>',
      '          <div class="hi-panel-caption"><strong>Panorámás látványtér</strong><span>Húzza el a képet, és nézze körbe a projekt térbeli bemutatóját.</span></div>',
      '        </div>',
      '        <div class="hi-panel" data-panel="video">',
      '          <div class="hi-video-grid">',
      '            <article class="hi-video-card"><video controls preload="none" playsinline data-poster="' + asset("video_swsp_xmsp.jpg") + '"><source src="' + asset("swsp_xmsp.mp4") + '" type="video/mp4"></video><div><strong>Projektbemutató</strong><span>A lakópark elhelyezkedése, épülettömege és környezeti kapcsolatai.</span></div></article>',
      '            <article class="hi-video-card"><video controls preload="none" playsinline data-poster="' + asset("video_spjs.jpg") + '"><source src="' + asset("spjs.mp4") + '" type="video/mp4"></video><div><strong>Látványvideó</strong><span>Átfogó képet ad a tervezett lakókörnyezetről és a projekt hangulatáról.</span></div></article>',
      '          </div>',
      '        </div>',
      '        <div class="hi-panel" data-panel="plans">',
      '          <div class="hi-split">',
      '            <div class="hi-copy"><small>Projekt áttekintés</small><h3>Modern lakókörnyezet Kőbányán</h3><p>A Harmat Lakópark a X. kerületben, a Harmat utca 22. szám alatt kínál új építésű otthonokat átgondolt alaprajzokkal, zöld környezettel és kényelmes városi kapcsolatokkal.</p><div class="hi-stat-row"><span><b>124 lakás</b>első ütem</span><span><b>Harmat utca 22.</b>Budapest X. kerület</span><span><b>Zöld környezet</b>élhető városi ritmus</span></div></div>',
      '            <button class="hi-feature-image" type="button" data-full="' + asset("xgt_8.jpg") + '"><img src="' + asset("xgt_8.jpg") + '" alt="Harmat Lakópark madártávlati látványterv" loading="lazy" decoding="async" fetchpriority="low"></button>',
      '          </div>',
      '        </div>',
      '        <div class="hi-panel" data-panel="gallery">',
      '          <div class="hi-gallery-grid">',
      galleryButton("xgt_0.jpg", "Áttekintő látvány"),
      galleryButton("xgt_1.jpg", "Épülethomlokzat"),
      galleryButton("xgt_4.jpg", "Lakókörnyezeti tér"),
      galleryButton("xgt_8.jpg", "Madártávlati nézet"),
      galleryButton("xgt_10.jpg", "Lakóépületi részlet"),
      galleryButton("xgt_5.jpg", "Kert és közösségi tér"),
      '          </div>',
      '        </div>',
      '        <div class="hi-panel" data-panel="location">',
      '          <div class="hi-split">',
      '            <button class="hi-feature-image hi-location-map" type="button" data-full="' + asset("video_swsp_xmsp.jpg") + '"><img src="' + asset("video_swsp_xmsp.jpg") + '" alt="Harmat Lakópark környezeti áttekintő" loading="lazy" decoding="async" fetchpriority="low"></button>',
      '            <div class="hi-copy"><small>Elhelyezkedés</small><h3>Otthon, ahol a város és a természet találkozik</h3><p>A környék mindennapi élethez szükséges szolgáltatásokat, zöldterületeket és jó városi kapcsolatokat kínál. A bemutató segít gyorsan átlátni a lakópark környezetét.</p><ul><li>Budapest X. kerület, Harmat utca 22.</li><li>Közeli bevásárlási, oktatási és egészségügyi lehetőségek</li><li>Könnyen értelmezhető projekt- és környezetbemutató</li></ul></div>',
      '          </div>',
      '        </div>',
      '        <div class="hi-panel" data-panel="notice">',
      '          <div class="hi-notice"><small>Tájékoztató</small><h3>Fontos információk</h3><p>A látványtervek, videók és bemutatóanyagok tájékoztató jellegűek. Az árak, műszaki tartalom, alapterületek, átadási határidők és felszereltség minden esetben a hivatalos dokumentáció és a szerződés szerint irányadók.</p><div class="hi-notice-grid"><span>A látványtervek illusztrációk</span><span>Az adatok tájékoztató jellegűek</span><span>A szerződés az irányadó</span></div></div>',
      '        </div>',
      '      </div>',
      '      <div class="hi-dock">',
      '        <div class="hi-tabs" aria-label="Harmat Lakópark bemutató menü">',
      tabButton("panorama", "Panoráma", "360 nézet", true),
      tabButton("video", "Videók", "Bemutató", false),
      tabButton("plans", "Projekt", "Áttekintés", false),
      tabButton("gallery", "Galéria", "Látványtervek", false),
      tabButton("location", "Környezet", "Lokáció", false),
      tabButton("notice", "Tájékoztató", "Fontos", false),
      '        </div>',
      '        <div class="hi-actions"><button type="button" data-hi-rotate>Szünet</button><button type="button" data-hi-reset>Alaphelyzet</button><button type="button" data-hi-full>Teljes képernyő</button></div>',
      '      </div>',
      '    </div>',
      '    <p class="hi-note">Megjegyzés: a bemutatóanyagok tájékoztató jellegűek, a végleges tartalom a szerződés és a hivatalos dokumentáció szerint irányadó.</p>',
      '  </div>',
      '</section>',
      '<div class="hi-lightbox" aria-hidden="true"><button type="button" aria-label="Bezárás">×</button><img alt=""></div>'
    ].join("");
  }

  function galleryButton(file, label) {
    return '<button type="button" data-full="' + asset(file) + '"><img src="' + asset(file) + '" alt="' + label + '" loading="lazy" decoding="async" fetchpriority="low"><span>' + label + "</span></button>";
  }

  function tabButton(target, label, sub, active) {
    return '<button class="' + (active ? "active" : "") + '" type="button" data-target="' + target + '">' + label + "<span>" + sub + "</span></button>";
  }

  function initializeModule(root) {
    if (!root || root.dataset.harmatReady === "1") {
      return;
    }
    root.dataset.harmatReady = "1";

    var viewerEl = document.getElementById("harmat-panorama");
    var fallback = root.querySelector(".hi-fallback");
    var autoRotate = true;
    var startPitch = -27;
    var startYaw = 0;
    var startHfov = 92;

    function hydrateVideos() {
      root.querySelectorAll(".hi-video-card video[data-poster]").forEach(function (video) {
        video.setAttribute("poster", video.getAttribute("data-poster"));
        video.removeAttribute("data-poster");
      });
    }

    function ensureViewer() {
      if (window.harmatViewer || !window.pannellum || !viewerEl) {
        return;
      }
      try {
        window.harmatViewer = pannellum.viewer("harmat-panorama", {
          type: "cubemap",
          cubeMap: [
            asset("pano_pano_f.jpg"),
            asset("pano_pano_r.jpg"),
            asset("pano_pano_b.jpg"),
            asset("pano_pano_l.jpg"),
            asset("pano_pano_u.jpg"),
            asset("pano_pano_d.jpg")
          ],
          autoLoad: true,
          showControls: false,
          showFullscreenCtrl: false,
          autoRotate: -1,
          autoRotateInactivityDelay: 2600,
          compass: false,
          hfov: startHfov,
          pitch: startPitch,
          yaw: startYaw,
          minHfov: 44,
          maxHfov: 112
        });
        if (fallback) {
          fallback.style.display = "none";
        }
      } catch (error) {
        if (fallback) {
          fallback.style.display = "block";
        }
      }
    }

    function whenNearViewport(callback) {
      if (!("IntersectionObserver" in window)) {
        window.setTimeout(callback, 900);
        return;
      }

      var done = false;
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!done && (entry.isIntersecting || entry.intersectionRatio > 0)) {
            done = true;
            observer.disconnect();
            callback();
          }
        });
      }, { rootMargin: "420px 0px", threshold: 0.01 });

      observer.observe(root);
    }

    whenNearViewport(ensureViewer);

    var tabs = Array.prototype.slice.call(root.querySelectorAll(".hi-tabs button[data-target]"));
    var panels = Array.prototype.slice.call(root.querySelectorAll(".hi-panel[data-panel]"));
    root.querySelectorAll(".hi-video-card video").forEach(function (video) {
      function getVideoRate() {
        var source = video.querySelector("source");
        var src = ((video.currentSrc || video.getAttribute("src") || "") + " " + (source ? source.getAttribute("src") : "")).toLowerCase();
        return src.indexOf("spjs.mp4") !== -1 ? 1 : 0.25;
      }

      function applyVideoRate() {
        var rate = getVideoRate();
        video.defaultPlaybackRate = rate;
        if (Math.abs(video.playbackRate - rate) > 0.01) {
          video.playbackRate = rate;
        }
      }

      applyVideoRate();
      video.addEventListener("loadedmetadata", applyVideoRate);
      video.addEventListener("play", applyVideoRate);
      video.addEventListener("ratechange", applyVideoRate);
    });

    function showPanel(name) {
      if (name === "video") {
        hydrateVideos();
      }
      if (name === "panorama") {
        ensureViewer();
      }
      panels.forEach(function (panel) {
        panel.classList.toggle("active", panel.dataset.panel === name);
      });
      tabs.forEach(function (tab) {
        tab.classList.toggle("active", tab.dataset.target === name);
      });
      root.querySelectorAll("video").forEach(function (video) {
        if (name !== "video") {
          video.pause();
        }
      });
      if (window.harmatViewer && name === "panorama") {
        setTimeout(function () {
          window.harmatViewer.resize();
        }, 80);
      }
    }

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        showPanel(tab.dataset.target);
      });
    });

    var resetBtn = root.querySelector("[data-hi-reset]");
    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        ensureViewer();
        showPanel("panorama");
        if (window.harmatViewer) {
          window.harmatViewer.lookAt(startPitch, startYaw, startHfov, 900);
        }
      });
    }

    var rotateBtn = root.querySelector("[data-hi-rotate]");
    if (rotateBtn) {
      rotateBtn.addEventListener("click", function () {
        if (!window.harmatViewer) {
          ensureViewer();
        }
        if (!window.harmatViewer) {
          return;
        }
        showPanel("panorama");
        autoRotate = !autoRotate;
        if (autoRotate) {
          window.harmatViewer.startAutoRotate(-1);
          rotateBtn.textContent = "Szünet";
        } else {
          window.harmatViewer.stopAutoRotate();
          rotateBtn.textContent = "Forgatás";
        }
      });
    }

    var fullBtn = root.querySelector("[data-hi-full]");
    if (fullBtn) {
      fullBtn.addEventListener("click", function () {
        var el = root.querySelector(".hi-console");
        if (el && el.requestFullscreen) {
          el.requestFullscreen();
        }
      });
    }

    document.addEventListener("fullscreenchange", function () {
      if (window.harmatViewer) {
        setTimeout(function () {
          window.harmatViewer.resize();
        }, 120);
      }
    });

    var lightbox = document.querySelector(".hi-lightbox");
    var lightImg = lightbox ? lightbox.querySelector("img") : null;
    root.addEventListener("click", function (event) {
      var btn = event.target.closest("[data-full]");
      if (!btn || !root.contains(btn) || !lightbox || !lightImg) {
        return;
      }
      lightImg.src = btn.dataset.full;
      var img = btn.querySelector("img");
      lightImg.alt = img ? img.alt : "";
      lightbox.classList.add("open");
      event.preventDefault();
    });

    if (lightbox) {
      lightbox.addEventListener("click", function (event) {
        if (event.target === lightbox || event.target.tagName === "BUTTON") {
          lightbox.classList.remove("open");
          if (lightImg) {
            lightImg.removeAttribute("src");
          }
        }
      });
      document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
          lightbox.classList.remove("open");
          if (lightImg) {
            lightImg.removeAttribute("src");
          }
        }
      });
    }
  }

  ready(function () {
    if (document.body && (document.body.classList.contains("home") || window.location.pathname.replace(/\/+$/, "/") === "/")) {
      return;
    }

    if (document.querySelector(".harmat-interactive")) {
      initializeModule(document.querySelector(".harmat-interactive"));
      return;
    }

    var target = findTargetSection();
    if (!target) {
      return;
    }

    var replacement = document.createElement("div");
    replacement.innerHTML = markup();
    target.replaceWith(replacement);
    initializeModule(replacement.querySelector(".harmat-interactive"));
  });
})();
