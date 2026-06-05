<?php
/**
 * Plugin Name: Harmat VR Demo
 * Description: Retires the experimental A1-F-L3 VR/3D demo and redirects old demo URLs back to the apartment page.
 * Version: 0.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function h22_vr_demo_current_path() {
    $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = (string) parse_url($path, PHP_URL_PATH);
    return trim($path, '/');
}

function h22_vr_demo_url($path = '') {
    return home_url('/' . ltrim($path, '/'));
}

function h22_vr_demo_asset($file) {
    return h22_vr_demo_url('wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/' . ltrim($file, '/'));
}

function h22_vr_demo_floorplan_url() {
    return h22_vr_demo_url('wp-content/uploads/2026/05/a1-f-l3-cn-floorplan.pdf');
}

function h22_vr_demo_is_retired() {
    return true;
}

function h22_vr_demo_render_page() {
    $path = h22_vr_demo_current_path();
    if (h22_vr_demo_is_retired()) {
        if ($path === '3d-a1-f-l3' || $path === 'vr-a1-f-l3') {
            wp_safe_redirect(h22_vr_demo_url('property/a1-f-l3/'), 302);
            exit;
        }
        return;
    }
    if ($path === '3d-a1-f-l3') {
        h22_vr_demo_render_3d_page();
        return;
    }
    if ($path !== 'vr-a1-f-l3') {
        return;
    }

    status_header(200);
    nocache_headers();
    $vr_url = h22_vr_demo_url('vr-a1-f-l3/');
    $property_url = h22_vr_demo_url('property/a1-f-l3/');
    $model_url = h22_vr_demo_url('3d-a1-f-l3/');
    $floorplan_url = h22_vr_demo_floorplan_url();
    $garden_image = h22_vr_demo_asset('xgt_8.jpg');
    $entrance_image = h22_vr_demo_asset('xgt_0.jpg');
    $aerial_image = h22_vr_demo_asset('pano_pano_f.jpg');
    ?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>A1-F-L3 virtuális lakásbemutató | Harmat Lakópark</title>
  <meta name="description" content="A1-F-L3 virtuális lakásbemutató demo látványtervek és alaprajz alapján.">
  <link rel="canonical" href="<?php echo esc_url($vr_url); ?>">
  <style>
    :root {
      --ink: #243034;
      --muted: #657074;
      --line: rgba(157, 114, 50, .22);
      --gold: #9a6a2a;
      --green: #29473f;
      --soft: #fff7ea;
      --paper: #fffdf8;
      --shadow: 0 22px 64px rgba(37, 32, 23, .18);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      color: var(--ink);
      background: #f6efe2;
      font-family: Montserrat, Arial, sans-serif;
    }
    button, a { font: inherit; }
    .demo {
      min-height: 100vh;
      background:
        linear-gradient(180deg, rgba(255, 250, 240, .95), rgba(246, 239, 226, .98)),
        url("<?php echo esc_url($garden_image); ?>") center/cover fixed;
    }
    .wrap {
      width: min(1260px, calc(100vw - 32px));
      margin: 0 auto;
      padding: 34px 0 44px;
    }
    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 18px;
      margin-bottom: 24px;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 800;
      color: var(--green);
      letter-spacing: .04em;
      text-transform: uppercase;
      font-size: 13px;
    }
    .brand-mark {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: linear-gradient(135deg, #2d7f7f, #d6ae63);
      box-shadow: inset 0 0 0 3px rgba(255, 255, 255, .58);
    }
    .pill {
      display: inline-flex;
      align-items: center;
      min-height: 34px;
      padding: 0 13px;
      border: 1px solid var(--line);
      border-radius: 999px;
      color: #73501f;
      background: rgba(255, 253, 248, .82);
      font-size: 12px;
      font-weight: 800;
      white-space: nowrap;
    }
    .hero {
      display: grid;
      grid-template-columns: minmax(0, .82fr) minmax(320px, .48fr);
      gap: 24px;
      align-items: stretch;
    }
    .viewer {
      overflow: hidden;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #14251f;
      box-shadow: var(--shadow);
    }
    .stage {
      position: relative;
      aspect-ratio: 16 / 9;
      min-height: 500px;
      overflow: hidden;
      background: #102822;
      cursor: grab;
      user-select: none;
    }
    .stage.is-dragging { cursor: grabbing; }
    .stage img {
      position: absolute;
      top: 0;
      left: 50%;
      height: 100%;
      width: 132%;
      max-width: none;
      object-fit: cover;
      transform: translateX(calc(-50% + var(--pan, 0px)));
      transition: opacity .18s ease;
    }
    .stage.pdf-mode {
      cursor: default;
      background: #f7f1e5;
    }
    .stage.pdf-mode img,
    .stage.pdf-mode .hotspots,
    .stage.pdf-mode .drag-hint { display: none; }
    .pdf-frame {
      display: none;
      width: 100%;
      height: 100%;
      border: 0;
      background: #fff;
    }
    .stage.pdf-mode .pdf-frame { display: block; }
    .caption {
      position: absolute;
      left: 22px;
      bottom: 22px;
      max-width: min(460px, calc(100% - 44px));
      padding: 16px 18px;
      border: 1px solid rgba(255,255,255,.18);
      border-radius: 8px;
      background: rgba(21, 37, 32, .78);
      color: #fff;
      backdrop-filter: blur(12px);
    }
    .caption strong {
      display: block;
      margin-bottom: 7px;
      font-family: Georgia, "Times New Roman", serif;
      font-size: clamp(22px, 2.3vw, 34px);
      font-weight: 400;
      line-height: 1.08;
    }
    .caption span {
      display: block;
      color: rgba(255,255,255,.78);
      font-size: 13px;
      line-height: 1.55;
    }
    .drag-hint {
      position: absolute;
      right: 18px;
      top: 18px;
      z-index: 4;
      padding: 8px 11px;
      border-radius: 999px;
      background: rgba(255, 253, 248, .88);
      color: #5b4525;
      font-size: 12px;
      font-weight: 800;
    }
    .hotspots button {
      position: absolute;
      z-index: 5;
      min-height: 31px;
      padding: 0 10px;
      border: 1px solid rgba(255,255,255,.42);
      border-radius: 999px;
      background: rgba(154, 106, 42, .92);
      color: #fff;
      box-shadow: 0 8px 22px rgba(0,0,0,.22);
      font-size: 12px;
      font-weight: 800;
      cursor: pointer;
    }
    .hotspots button::before {
      content: "";
      width: 9px;
      height: 9px;
      margin-right: 7px;
      display: inline-block;
      border-radius: 50%;
      background: #fff7e7;
      vertical-align: 1px;
    }
    .dock {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 12px;
      padding: 14px;
      background: #fffdf8;
      border-top: 1px solid var(--line);
    }
    .tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .tabs button,
    .action {
      min-height: 38px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      color: var(--ink);
      cursor: pointer;
      font-size: 13px;
      font-weight: 800;
    }
    .tabs button { padding: 0 13px; }
    .tabs button.active {
      background: var(--green);
      border-color: var(--green);
      color: #fff;
    }
    .action {
      padding: 0 14px;
      background: var(--gold);
      color: #fff;
      border-color: var(--gold);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .info {
      display: flex;
      flex-direction: column;
      min-width: 0;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: rgba(255, 253, 248, .94);
      box-shadow: 0 18px 50px rgba(37, 32, 23, .12);
      overflow: hidden;
    }
    .info-main { padding: 28px; }
    .eyebrow {
      margin: 0 0 12px;
      color: var(--gold);
      font-size: 12px;
      font-weight: 900;
      letter-spacing: 0;
      text-transform: uppercase;
    }
    h1 {
      margin: 0 0 13px;
      color: #263337;
      font-family: Georgia, "Times New Roman", serif;
      font-size: clamp(34px, 4vw, 56px);
      font-weight: 400;
      line-height: 1.04;
    }
    .lead {
      margin: 0;
      color: var(--muted);
      font-size: 15px;
      line-height: 1.68;
    }
    .facts {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 22px;
    }
    .fact {
      padding: 13px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff8eb;
    }
    .fact b {
      display: block;
      margin-bottom: 4px;
      color: var(--green);
      font-size: 19px;
    }
    .fact span {
      color: #6d7476;
      font-size: 12px;
      font-weight: 700;
    }
    .notice {
      margin-top: 22px;
      padding: 14px 15px;
      border-left: 3px solid var(--gold);
      background: #f8efdf;
      color: #5e5546;
      font-size: 13px;
      line-height: 1.55;
    }
    .cta {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: auto;
      padding: 18px 28px 28px;
    }
    .cta a {
      min-height: 42px;
      padding: 0 15px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      font-size: 13px;
      font-weight: 900;
    }
    .cta .primary {
      background: var(--green);
      color: #fff;
    }
    .cta .secondary {
      border: 1px solid var(--line);
      color: var(--gold);
      background: #fff;
    }
    .bottom {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
      margin-top: 18px;
    }
    .panel {
      padding: 18px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255, 253, 248, .92);
    }
    .panel h2 {
      margin: 0 0 8px;
      font-size: 16px;
      color: var(--green);
    }
    .panel p {
      margin: 0;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.58;
    }
    @media (max-width: 980px) {
      .hero,
      .bottom { grid-template-columns: 1fr; }
      .stage { min-height: 380px; }
      .dock { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
      .wrap {
        width: min(100vw - 20px, 1260px);
        padding-top: 18px;
      }
      .topbar {
        align-items: flex-start;
        flex-direction: column;
      }
      .stage {
        min-height: 300px;
        aspect-ratio: 4 / 5;
      }
      .stage img { width: 178%; }
      .caption {
        left: 12px;
        right: 12px;
        bottom: 12px;
        max-width: none;
        padding: 13px 14px;
      }
      .drag-hint {
        right: 10px;
        top: 10px;
      }
      .hotspots button {
        font-size: 11px;
        min-height: 29px;
      }
      .facts { grid-template-columns: 1fr; }
      .info-main,
      .cta {
        padding-left: 18px;
        padding-right: 18px;
      }
    }
  </style>
</head>
<body>
  <main class="demo">
    <div class="wrap">
      <header class="topbar">
        <div class="brand"><span class="brand-mark" aria-hidden="true"></span><span>Harmat Lakópark</span></div>
        <span class="pill">Demo: látványterv és alaprajz alapján</span>
      </header>
      <section class="hero" aria-label="A1-F-L3 virtuális lakásbemutató demo">
        <div class="viewer">
          <div class="stage" id="stage">
            <img id="sceneImage" src="<?php echo esc_url($garden_image); ?>" alt="Harmat Lakópark látványterv">
            <iframe class="pdf-frame" id="pdfFrame" title="A1-F-L3 alaprajz" src="<?php echo esc_url($floorplan_url); ?>"></iframe>
            <div class="drag-hint">Húzza el a nézetet</div>
            <div class="hotspots" aria-label="Lakásbemutató pontok">
              <button type="button" style="left:38%;top:72%" data-note="A földszinti kert a jelenlegi értékesítési információ szerint ajándék.">Ajándék kert</button>
              <button type="button" style="left:55%;top:52%" data-note="A1-F-L3: földszinti, 2 szobás lakás, 47,39 m² eladási terület.">A1-F-L3</button>
              <button type="button" style="left:70%;top:68%" data-note="A látványterv a lakópark zöld belső környezetét mutatja be.">Zöld környezet</button>
            </div>
            <div class="caption">
              <strong id="sceneTitle">Kert és földszinti kapcsolat</strong>
              <span id="sceneText">A demo a meglévő látványtervekből és az A1-F-L3 alaprajzából épül. Nem 360° belső fotó, hanem első virtuális bemutató-váz.</span>
            </div>
          </div>
          <div class="dock">
            <div class="tabs" aria-label="Nézetválasztó">
              <button class="active" type="button" data-scene="garden">Kert</button>
              <button type="button" data-scene="entrance">Bejárat</button>
              <button type="button" data-scene="aerial">Környezet</button>
              <button type="button" data-scene="plan">Alaprajz</button>
            </div>
            <a class="action" href="<?php echo esc_url($model_url); ?>">3D modell</a>
          </div>
        </div>
        <aside class="info">
          <div class="info-main">
            <p class="eyebrow">Virtuális lakásbemutató demo</p>
            <h1>A1-F-L3</h1>
            <p class="lead">Földszinti, 2 szobás lakás a jelenlegi adatbázis szerint. A demo célja, hogy egy konkrét lakásnál egy helyen jelenjen meg az alaprajz, a látványterv, a kert-információ és az ajánlatkérési út.</p>
            <div class="facts">
              <div class="fact"><b>2 szoba</b><span>Lakástípus</span></div>
              <div class="fact"><b>47,39 m²</b><span>Eladási terület</span></div>
              <div class="fact"><b>Földszint</b><span>Ajándék kert jelöléssel</span></div>
              <div class="fact"><b>67 290 608 Ft</b><span>Tájékoztató ár</span></div>
            </div>
            <div class="notice">
              A földszinti kert a jelenlegi értékesítési információ szerint ajándék, külön kertár nélkül. A kert méretét, használati részleteit és szerződéses rögzítését az értékesítés erősíti meg.
            </div>
          </div>
          <div class="cta">
            <a class="primary" href="mailto:ertekesites@harmat22.hu?subject=A1-F-L3%20virtu%C3%A1lis%20bemutat%C3%B3">Ajánlatot kérek</a>
            <a class="secondary" href="<?php echo esc_url($property_url); ?>">Lakás adatlap</a>
            <a class="secondary" href="<?php echo esc_url($floorplan_url); ?>" target="_blank" rel="noopener">PDF alaprajz</a>
          </div>
        </aside>
      </section>
      <section class="bottom" aria-label="Demo megjegyzések">
        <article class="panel">
          <h2>Mi ez a demo?</h2>
          <p>Első interaktív váz egy lakáshoz. A jelenlegi anyagokból dolgozik: látványtervek, alaprajz PDF és lakásadatok.</p>
        </article>
        <article class="panel">
          <h2>Mi hiányzik a valódi VR-hez?</h2>
          <p>Valódi 360° belső panoráma vagy 3D modell. Ezek nélkül a belső tér nem mutatható be pontos VR-ként.</p>
        </article>
        <article class="panel">
          <h2>Következő lépés</h2>
          <p>Ha ez az irány megfelelő, ugyanez több lakás-adatlapba is beépíthető.</p>
        </article>
      </section>
    </div>
  </main>
  <script>
    (function () {
      var stage = document.getElementById("stage");
      var image = document.getElementById("sceneImage");
      var title = document.getElementById("sceneTitle");
      var text = document.getElementById("sceneText");
      var tabs = Array.prototype.slice.call(document.querySelectorAll("[data-scene]"));
      var pan = 0;
      var startX = 0;
      var startPan = 0;
      var dragging = false;
      var scenes = {
        garden: {
          image: <?php echo wp_json_encode($garden_image); ?>,
          title: "Kert és földszinti kapcsolat",
          text: "A földszinti A1-F-L3 demo a zöld belső környezet és az ajándék kert értékesítési pontjára épül."
        },
        entrance: {
          image: <?php echo wp_json_encode($entrance_image); ?>,
          title: "Érkezés a lakóparkba",
          text: "Bejárati látványterv. A kép projekt-szintű illusztráció, nem az adott lakás belső tere."
        },
        aerial: {
          image: <?php echo wp_json_encode($aerial_image); ?>,
          title: "Környezet és elhelyezkedés",
          text: "Környezeti látvány, amely segít elhelyezni a projektet a városi környezetben."
        },
        plan: {
          pdf: true,
          title: "A1-F-L3 alaprajz",
          text: "A hivatalos alaprajz PDF külön nézetben jelenik meg. Pontos méretek és jelölések az alaprajzon ellenőrizhetők."
        }
      };
      function setPan(value) {
        pan = Math.max(-170, Math.min(170, value));
        stage.style.setProperty("--pan", pan + "px");
      }
      function showScene(name) {
        var scene = scenes[name];
        if (!scene) return;
        stage.classList.toggle("pdf-mode", !!scene.pdf);
        if (!scene.pdf) {
          image.src = scene.image;
          image.alt = scene.title;
          setPan(0);
        }
        title.textContent = scene.title;
        text.textContent = scene.text;
        tabs.forEach(function (button) {
          button.classList.toggle("active", button.dataset.scene === name);
        });
      }
      tabs.forEach(function (button) {
        button.addEventListener("click", function () {
          showScene(button.dataset.scene);
        });
      });
      stage.addEventListener("pointerdown", function (event) {
        if (stage.classList.contains("pdf-mode")) return;
        dragging = true;
        startX = event.clientX;
        startPan = pan;
        stage.classList.add("is-dragging");
        stage.setPointerCapture(event.pointerId);
      });
      stage.addEventListener("pointermove", function (event) {
        if (!dragging) return;
        setPan(startPan + event.clientX - startX);
      });
      function stopDrag() {
        dragging = false;
        stage.classList.remove("is-dragging");
      }
      stage.addEventListener("pointerup", stopDrag);
      stage.addEventListener("pointercancel", stopDrag);
      document.querySelectorAll(".hotspots button").forEach(function (button) {
        button.addEventListener("click", function () {
          text.textContent = button.dataset.note || text.textContent;
        });
      });
    })();
  </script>
</body>
</html>
    <?php
    exit;
}

function h22_vr_demo_render_3d_page() {
    status_header(200);
    nocache_headers();
    $model_url = h22_vr_demo_url('3d-a1-f-l3/');
    $vr_url = h22_vr_demo_url('vr-a1-f-l3/');
    $property_url = h22_vr_demo_url('property/a1-f-l3/');
    $floorplan_url = h22_vr_demo_floorplan_url();
    ?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>A1-F-L3 3D lakásmodell | Harmat Lakópark</title>
  <meta name="description" content="A1-F-L3 interaktív 3D lakásmodell bemutató szobalistából és alaprajz alapján.">
  <link rel="canonical" href="<?php echo esc_url($model_url); ?>">
  <style>
    :root {
      --ink: #243034;
      --muted: #667174;
      --line: rgba(157, 114, 50, .22);
      --gold: #9a6a2a;
      --green: #29473f;
      --paper: #fffdf8;
      --warm: #fff7ea;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      color: var(--ink);
      background: linear-gradient(180deg, #fffaf0, #f2eadb);
      font-family: Montserrat, Arial, sans-serif;
      overflow-x: hidden;
    }
    button, a { font: inherit; }
    .h3d-page {
      min-height: 100vh;
      padding: 24px;
    }
    .h3d-shell {
      width: min(1380px, 100%);
      margin: 0 auto;
      display: grid;
      grid-template-columns: minmax(0, 1fr) 360px;
      gap: 18px;
    }
    .h3d-main,
    .h3d-side,
    .h3d-card {
      min-width: 0;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255, 253, 248, .94);
      box-shadow: 0 18px 50px rgba(37, 32, 23, .12);
    }
    .h3d-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 18px;
      padding: 20px 22px;
      border-bottom: 1px solid var(--line);
    }
    .h3d-head > div {
      min-width: 0;
    }
    .h3d-eyebrow {
      margin: 0 0 8px;
      color: var(--gold);
      font-size: 12px;
      font-weight: 900;
      letter-spacing: 0;
      text-transform: uppercase;
    }
    h1 {
      margin: 0;
      font-family: Georgia, "Times New Roman", serif;
      font-size: 56px;
      font-weight: 400;
      line-height: 1.02;
      color: #263337;
    }
    .h3d-head p {
      max-width: 610px;
      margin: 12px 0 0;
      color: var(--muted);
      font-size: 14px;
      line-height: 1.62;
      overflow-wrap: anywhere;
    }
    .h3d-badge {
      min-height: 34px;
      padding: 0 13px;
      display: inline-flex;
      align-items: center;
      border: 1px solid var(--line);
      border-radius: 999px;
      background: var(--warm);
      color: #73501f;
      font-size: 12px;
      font-weight: 900;
      text-align: center;
      white-space: normal;
    }
    .h3d-viewer {
      position: relative;
      min-height: 640px;
      background: radial-gradient(circle at 50% 28%, #41584f, #16241f 74%);
      overflow: hidden;
    }
    #h22-3d-canvas {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      display: block;
    }
    .h3d-loading,
    .h3d-error {
      position: absolute;
      left: 22px;
      bottom: 22px;
      z-index: 4;
      max-width: 440px;
      padding: 14px 16px;
      border: 1px solid rgba(255,255,255,.2);
      border-radius: 8px;
      background: rgba(20, 38, 32, .82);
      color: #fff;
      font-size: 13px;
      line-height: 1.5;
      backdrop-filter: blur(10px);
    }
    .h3d-error { display: none; background: rgba(89, 43, 35, .9); }
    .h3d-tour-card {
      position: absolute;
      right: 18px;
      bottom: 18px;
      z-index: 5;
      min-width: 230px;
      max-width: min(360px, calc(100% - 36px));
      padding: 13px 15px;
      border: 1px solid rgba(255,255,255,.2);
      border-radius: 8px;
      background: rgba(20, 38, 32, .78);
      color: #fff;
      box-shadow: 0 16px 40px rgba(0,0,0,.24);
      backdrop-filter: blur(12px);
    }
    .h3d-tour-card b {
      display: block;
      margin-bottom: 4px;
      font-size: 13px;
    }
    .h3d-tour-card span {
      display: block;
      color: rgba(255,255,255,.78);
      font-size: 12px;
      line-height: 1.45;
    }
    .h3d-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      padding: 14px;
      border-top: 1px solid var(--line);
      background: #fffdf8;
    }
    .h3d-toolbar button,
    .h3d-link {
      min-height: 38px;
      padding: 0 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      color: var(--ink);
      cursor: pointer;
      font-size: 13px;
      font-weight: 900;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .h3d-toolbar button.active {
      border-color: var(--green);
      background: var(--green);
      color: #fff;
    }
    .h3d-link.primary {
      border-color: var(--gold);
      background: var(--gold);
      color: #fff;
    }
    .h3d-side {
      padding: 22px;
      align-self: start;
    }
    .h3d-side h2 {
      margin: 0 0 10px;
      color: var(--green);
      font-size: 18px;
    }
    .h3d-side p {
      margin: 0 0 16px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.6;
    }
    .h3d-facts {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 9px;
      margin-bottom: 16px;
    }
    .h3d-fact {
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: var(--warm);
    }
    .h3d-fact b {
      display: block;
      margin-bottom: 3px;
      color: var(--green);
      font-size: 17px;
    }
    .h3d-fact span {
      color: #6d7476;
      font-size: 11px;
      font-weight: 800;
    }
    .h3d-rooms {
      display: grid;
      gap: 7px;
      margin: 0 0 16px;
      padding: 0;
      list-style: none;
    }
    .h3d-rooms li {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 9px 10px;
      border: 1px solid rgba(157, 114, 50, .16);
      border-radius: 7px;
      background: #fff;
      color: #3d4648;
      font-size: 12px;
      font-weight: 800;
    }
    .h3d-note {
      padding: 13px 14px;
      border-left: 3px solid var(--gold);
      background: #f8efdf;
      color: #5f5547;
      font-size: 12px;
      line-height: 1.55;
    }
    .h3d-side-actions {
      display: grid;
      gap: 9px;
      margin-top: 16px;
    }
    @media (max-width: 1040px) {
      .h3d-shell { grid-template-columns: 1fr; }
      .h3d-viewer { min-height: 560px; }
    }
    @media (max-width: 620px) {
      html, body { width: 100%; max-width: 100%; }
      .h3d-page {
        width: 100%;
        max-width: 100vw;
        overflow: hidden;
        padding: 10px;
      }
      .h3d-shell,
      .h3d-main,
      .h3d-side {
        width: 100%;
        max-width: 100%;
      }
      .h3d-head { flex-direction: column; padding: 17px; }
      .h3d-head > div { width: 100%; }
      .h3d-head p { max-width: 100%; }
      h1 { font-size: 42px; }
      .h3d-viewer { min-height: 430px; }
      .h3d-tour-card {
        right: 12px;
        bottom: 12px;
        min-width: 0;
      }
      .h3d-facts { grid-template-columns: 1fr; }
      .h3d-toolbar { gap: 6px; }
      .h3d-toolbar button, .h3d-link {
        min-width: 0;
        flex: 1 1 calc(50% - 6px);
        padding: 0 9px;
      }
    }
    @media (max-width: 380px) {
      h1 { font-size: 37px; }
      .h3d-toolbar button { flex-basis: 100%; }
    }
  </style>
</head>
<body>
  <main class="h3d-page">
    <div class="h3d-shell">
      <section class="h3d-main" aria-label="A1-F-L3 interaktív 3D modell">
        <header class="h3d-head">
          <div>
            <p class="h3d-eyebrow">Interaktív 3D lakásmodell</p>
            <h1>A1-F-L3</h1>
            <p>Bemutató jellegű 3D modell a lakás helyiséglistája és alaprajza alapján. Nem BIM vagy kiviteli terv, hanem értékesítési vizualizáció.</p>
          </div>
          <span class="h3d-badge">Földszint · 2 szoba · ajándék kert</span>
        </header>
        <div class="h3d-viewer" id="h22-3d-viewer">
          <canvas id="h22-3d-canvas"></canvas>
          <div class="h3d-loading" id="h22-3d-loading">3D modell betöltése...</div>
          <div class="h3d-error" id="h22-3d-error">A 3D modell nem töltött be. Ellenőrizze a böngészőt vagy próbálja újra később.</div>
        </div>
        <div class="h3d-toolbar" aria-label="3D nézetek">
          <button type="button" class="active" data-view="overview">Áttekintés</button>
          <button type="button" data-view="top">Felülnézet</button>
          <button type="button" data-view="walk">Belső nézet</button>
          <button type="button" data-view="living">Nappali</button>
          <button type="button" data-view="bedroom">Szoba</button>
          <button type="button" data-view="garden">Kert</button>
          <button type="button" data-toggle="walls">Falak</button>
          <button type="button" data-toggle="furniture">Bútorok</button>
        </div>
      </section>
      <aside class="h3d-side">
        <h2>Modell adatok</h2>
        <p>A modell a nyilvános A1-F-L3 adatlap helyiséglistájából épül. A falvastagságok, nyílászárók és bútorok szemléltető jellegűek.</p>
        <div class="h3d-facts">
          <div class="h3d-fact"><b>43,74 m²</b><span>Lakás alapterület</span></div>
          <div class="h3d-fact"><b>47,39 m²</b><span>Értékesített terület</span></div>
          <div class="h3d-fact"><b>7,30 m²</b><span>Terasz</span></div>
          <div class="h3d-fact"><b>45,56 m²</b><span>Kert</span></div>
        </div>
        <ul class="h3d-rooms">
          <li><span>Előszoba</span><span>3,73 m²</span></li>
          <li><span>Helyiség 02</span><span>2,02 m²</span></li>
          <li><span>Nappali</span><span>15,99 m²</span></li>
          <li><span>Konyha</span><span>4,46 m²</span></li>
          <li><span>Helyiség 05</span><span>2,56 m²</span></li>
          <li><span>Fürdő</span><span>3,81 m²</span></li>
          <li><span>Szoba</span><span>11,17 m²</span></li>
          <li><span>Terasz</span><span>7,30 m²</span></li>
          <li><span>Kert</span><span>45,56 m²</span></li>
        </ul>
        <div class="h3d-note">A földszinti kert a jelenlegi értékesítési információ szerint ajándék, külön kertár nélkül. A pontos kertméretet, használati részleteket és szerződéses rögzítést az értékesítés erősíti meg.</div>
        <div class="h3d-side-actions">
          <a class="h3d-link primary" href="mailto:ertekesites@harmat22.hu?subject=A1-F-L3%203D%20modell">Ajánlatot kérek</a>
          <a class="h3d-link" href="<?php echo esc_url($vr_url); ?>">Virtuális bemutató</a>
          <a class="h3d-link" href="<?php echo esc_url($floorplan_url); ?>" target="_blank" rel="noopener">PDF alaprajz</a>
          <a class="h3d-link" href="<?php echo esc_url($property_url); ?>">Lakás adatlap</a>
        </div>
      </aside>
    </div>
  </main>
  <script>
    window.H22_3D_STATE = { initialized: false, rendered: false, frameCount: 0, objectCount: 0, samplePixel: null, nonBlankPixel: false, tourRunning: false, tourStep: null, error: null };
    window.setTimeout(function () {
      var state = window.H22_3D_STATE;
      if (state && state.rendered) return;
      var loading = document.getElementById("h22-3d-loading");
      var errorBox = document.getElementById("h22-3d-error");
      if (loading) loading.style.display = "none";
      if (errorBox) errorBox.style.display = "block";
      if (state && !state.error) state.error = "render-timeout";
    }, 8000);
  </script>
  <script type="module">
    import * as THREE from "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js";
    window.H22_THREE_READY = true;
    (function () {
      var viewer = document.getElementById("h22-3d-viewer");
      var canvas = document.getElementById("h22-3d-canvas");
      var loading = document.getElementById("h22-3d-loading");
      var errorBox = document.getElementById("h22-3d-error");
      if (!viewer || !canvas) {
        if (loading) loading.style.display = "none";
        if (errorBox) errorBox.style.display = "block";
        if (window.H22_3D_STATE) window.H22_3D_STATE.error = "missing-viewer";
        return;
      }

      var scene = new THREE.Scene();
      scene.background = new THREE.Color(0x172820);
      scene.fog = new THREE.Fog(0x172820, 12, 28);
      var camera = new THREE.PerspectiveCamera(45, 1, 0.1, 120);
      var renderer;
      try {
        renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true });
      } catch (err) {
        if (loading) loading.style.display = "none";
        if (errorBox) errorBox.style.display = "block";
        if (window.H22_3D_STATE) window.H22_3D_STATE.error = err && err.message ? err.message : "webgl-error";
        return;
      }
      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
      renderer.outputColorSpace = THREE.SRGBColorSpace;
      renderer.toneMapping = THREE.ACESFilmicToneMapping;
      renderer.toneMappingExposure = 1.12;
      renderer.shadowMap.enabled = true;
      renderer.shadowMap.type = THREE.PCFSoftShadowMap;

      var wallsGroup = new THREE.Group();
      var furnitureGroup = new THREE.Group();
      var labelGroup = new THREE.Group();
      var target = new THREE.Vector3(0, 0.7, -0.8);
      var yaw = -0.72;
      var pitch = 0.78;
      var distance = 13.5;
      var dragging = false;
      var lastX = 0;
      var lastY = 0;
      var tourRunning = false;
      var tourStart = 0;
      var tourDuration = 30000;
      var tourTitle = document.getElementById("h22-tour-title");
      var tourNote = document.getElementById("h22-tour-note");
      var tourButton = document.querySelector("[data-tour-toggle]");
      var tourSteps = [
        { t: 0, label: "Nappali", note: "Belépés a nappaliba, ügyfél szemmagasságból.", yaw: 0.03, pitch: 0.18, distance: 1.65, target: [-0.55, 1.08, -2.24], fov: 58 },
        { t: 5200, label: "Nappali és teraszajtó", note: "A nappali kapcsolata a terasszal és a kerttel.", yaw: -0.18, pitch: 0.2, distance: 2.25, target: [-0.25, 1.04, -2.42], fov: 60 },
        { t: 9800, label: "Konyha", note: "Átfordulás a konyha irányába.", yaw: -0.92, pitch: 0.2, distance: 2.15, target: [0.76, 1.08, -1.12], fov: 58 },
        { t: 14600, label: "Szoba", note: "A hálószoba berendezett, alacsony nézetből.", yaw: 0.08, pitch: 0.2, distance: 1.86, target: [3.18, 1.02, -1.78], fov: 58 },
        { t: 19800, label: "Fürdő", note: "Rövid betekintés a fürdő irányába.", yaw: -0.58, pitch: 0.24, distance: 2.1, target: [2.7, 1.1, 1.65], fov: 58 },
        { t: 24800, label: "Terasz és kert", note: "A bemutató a terasz és az ajándék kert felé zár.", yaw: -0.25, pitch: 0.45, distance: 8.8, target: [0.15, 0.38, -5.6], fov: 45 },
        { t: 30000, label: "Nappali", note: "A séta újraindul a nappaliból.", yaw: 0.03, pitch: 0.18, distance: 1.65, target: [-0.55, 1.08, -2.24], fov: 58 }
      ];

      function texture(draw, repeatX, repeatY) {
        var c = document.createElement("canvas");
        c.width = 512;
        c.height = 512;
        var ctx = c.getContext("2d");
        draw(ctx, c.width, c.height);
        var tex = new THREE.CanvasTexture(c);
        tex.wrapS = THREE.RepeatWrapping;
        tex.wrapT = THREE.RepeatWrapping;
        tex.repeat.set(repeatX || 1, repeatY || 1);
        tex.colorSpace = THREE.SRGBColorSpace;
        return tex;
      }

      function woodTexture() {
        return texture(function (ctx, w, h) {
          ctx.fillStyle = "#b98755";
          ctx.fillRect(0, 0, w, h);
          for (var y = 0; y < h; y += 64) {
            ctx.fillStyle = y % 128 === 0 ? "#c39464" : "#a97747";
            ctx.fillRect(0, y, w, 62);
            ctx.strokeStyle = "rgba(72, 45, 24, .22)";
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(0, y + 62);
            ctx.lineTo(w, y + 62);
            ctx.stroke();
          }
          for (var i = 0; i < 46; i += 1) {
            var yy = (i * 37) % h;
            ctx.strokeStyle = "rgba(255,255,255,.08)";
            ctx.beginPath();
            ctx.moveTo(0, yy);
            ctx.bezierCurveTo(w * .25, yy + 16, w * .68, yy - 13, w, yy + 8);
            ctx.stroke();
          }
        }, 3.4, 2.2);
      }

      function tileTexture(base, grout) {
        return texture(function (ctx, w, h) {
          ctx.fillStyle = grout;
          ctx.fillRect(0, 0, w, h);
          for (var y = 0; y < h; y += 128) {
            for (var x = 0; x < w; x += 128) {
              ctx.fillStyle = base;
              ctx.fillRect(x + 5, y + 5, 118, 118);
              ctx.fillStyle = "rgba(255,255,255,.12)";
              ctx.fillRect(x + 12, y + 12, 52, 4);
              ctx.fillStyle = "rgba(0,0,0,.045)";
              ctx.fillRect(x + 6, y + 112, 116, 7);
            }
          }
        }, 2.4, 2.4);
      }

      function fabricTexture(base, accent) {
        return texture(function (ctx, w, h) {
          ctx.fillStyle = base;
          ctx.fillRect(0, 0, w, h);
          for (var y = 0; y < h; y += 12) {
            ctx.strokeStyle = y % 24 === 0 ? accent : "rgba(255,255,255,.06)";
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(w, y + 18);
            ctx.stroke();
          }
          for (var x = 0; x < w; x += 18) {
            ctx.strokeStyle = "rgba(0,0,0,.035)";
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x + 20, h);
            ctx.stroke();
          }
        }, 2, 2);
      }

      function grassTexture() {
        return texture(function (ctx, w, h) {
          ctx.fillStyle = "#4f7f46";
          ctx.fillRect(0, 0, w, h);
          for (var y = 0; y < h; y += 24) {
            ctx.strokeStyle = y % 48 === 0 ? "rgba(255,255,255,.09)" : "rgba(0,0,0,.08)";
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(w, y + 10);
            ctx.stroke();
          }
          for (var i = 0; i < 120; i += 1) {
            ctx.fillStyle = i % 3 === 0 ? "rgba(190,220,150,.16)" : "rgba(28,70,30,.18)";
            ctx.fillRect((i * 41) % w, (i * 97) % h, 2, 11);
          }
        }, 2.6, 2.2);
      }

      function plasterTexture() {
        return texture(function (ctx, w, h) {
          ctx.fillStyle = "#f5ead8";
          ctx.fillRect(0, 0, w, h);
          for (var i = 0; i < 260; i += 1) {
            var v = i % 2 === 0 ? "rgba(255,255,255,.13)" : "rgba(98,78,49,.045)";
            ctx.fillStyle = v;
            ctx.fillRect((i * 29) % w, (i * 47) % h, 2 + (i % 5), 1 + (i % 4));
          }
        }, 2, 2);
      }

      var materials = {
        wall: new THREE.MeshStandardMaterial({ map: plasterTexture(), color: 0xfff4e4, roughness: 0.86 }),
        cap: new THREE.MeshStandardMaterial({ color: 0xd5bb83, roughness: 0.62 }),
        living: new THREE.MeshStandardMaterial({ map: woodTexture(), color: 0xffffff, roughness: 0.58 }),
        kitchen: new THREE.MeshStandardMaterial({ map: tileTexture("#d7e0dc", "#f3efe5"), color: 0xffffff, roughness: 0.64 }),
        bedroom: new THREE.MeshStandardMaterial({ map: woodTexture(), color: 0xf2dfc3, roughness: 0.62 }),
        bath: new THREE.MeshStandardMaterial({ map: tileTexture("#cad9dd", "#f7f4ec"), color: 0xffffff, roughness: 0.48 }),
        service: new THREE.MeshStandardMaterial({ map: tileTexture("#e5ddce", "#f8f1e4"), color: 0xffffff, roughness: 0.7 }),
        terrace: new THREE.MeshStandardMaterial({ map: tileTexture("#b5a17c", "#e6d7bf"), color: 0xffffff, roughness: 0.78 }),
        garden: new THREE.MeshStandardMaterial({ map: grassTexture(), color: 0xffffff, roughness: 0.92 }),
        glass: new THREE.MeshPhysicalMaterial({ color: 0xa9d7e7, transparent: true, opacity: 0.42, roughness: 0.06, metalness: 0, side: THREE.DoubleSide }),
        dark: new THREE.MeshStandardMaterial({ color: 0x303c3c, roughness: 0.55, metalness: 0.12 }),
        metal: new THREE.MeshStandardMaterial({ color: 0xb9b3a6, roughness: 0.34, metalness: 0.55 }),
        wood: new THREE.MeshStandardMaterial({ map: woodTexture(), color: 0xffffff, roughness: 0.54 }),
        cabinet: new THREE.MeshStandardMaterial({ color: 0xd8cfbd, roughness: 0.42 }),
        counter: new THREE.MeshStandardMaterial({ color: 0xefe7d8, roughness: 0.28, metalness: 0.05 }),
        fabric: new THREE.MeshStandardMaterial({ map: fabricTexture("#879b8a", "rgba(255,255,255,.1)"), color: 0xffffff, roughness: 0.86 }),
        warmFabric: new THREE.MeshStandardMaterial({ map: fabricTexture("#c49b70", "rgba(255,255,255,.1)"), color: 0xffffff, roughness: 0.82 }),
        rug: new THREE.MeshStandardMaterial({ map: fabricTexture("#d8c3a0", "rgba(92,55,28,.12)"), color: 0xffffff, roughness: 0.94 }),
        paper: new THREE.MeshStandardMaterial({ color: 0xf8f3e9, roughness: 0.92 }),
        curtain: new THREE.MeshStandardMaterial({ color: 0xf3eadb, transparent: true, opacity: 0.66, roughness: 0.88, side: THREE.DoubleSide }),
        art: new THREE.MeshStandardMaterial({ color: 0xb98956, roughness: 0.5 }),
        plant: new THREE.MeshStandardMaterial({ color: 0x3f7c49, roughness: 0.9 }),
        light: new THREE.MeshStandardMaterial({ color: 0xffe4a8, emissive: 0xffb45f, emissiveIntensity: 1.45, roughness: 0.28 })
      };

      function box(w, h, d, x, y, z, mat, parent) {
        var mesh = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
        mesh.position.set(x, y, z);
        mesh.castShadow = h > 0.08;
        mesh.receiveShadow = true;
        (parent || scene).add(mesh);
        return mesh;
      }

      function boxRot(w, h, d, x, y, z, rotY, mat, parent) {
        var mesh = box(w, h, d, x, y, z, mat, parent);
        mesh.rotation.y = rotY || 0;
        return mesh;
      }

      function cylinder(radius, h, x, y, z, mat, parent, segments) {
        var mesh = new THREE.Mesh(new THREE.CylinderGeometry(radius, radius, h, segments || 24), mat);
        mesh.position.set(x, y, z);
        mesh.castShadow = true;
        mesh.receiveShadow = true;
        (parent || scene).add(mesh);
        return mesh;
      }

      function plant(x, z, scale) {
        var s = scale || 1;
        cylinder(0.13 * s, 0.28 * s, x, 0.18 * s, z, materials.terrace, furnitureGroup, 18);
        box(0.42 * s, 0.06 * s, 0.18 * s, x - 0.1 * s, 0.42 * s, z, materials.plant, furnitureGroup);
        boxRot(0.34 * s, 0.05 * s, 0.15 * s, x + 0.13 * s, 0.54 * s, z + 0.03 * s, 0.55, materials.plant, furnitureGroup);
        boxRot(0.28 * s, 0.05 * s, 0.13 * s, x, 0.66 * s, z - 0.1 * s, -0.45, materials.plant, furnitureGroup);
      }

      function ceilingLight(x, z, intensity) {
        box(0.38, 0.05, 0.38, x, 2.48, z, materials.light, furnitureGroup);
        var light = new THREE.PointLight(0xffd9a0, intensity || 1.1, 5.6, 1.8);
        light.position.set(x, 2.28, z);
        scene.add(light);
      }

      function floor(room) {
        var mesh = box(room.w, 0.06, room.d, room.x, 0, room.z, room.mat, scene);
        mesh.userData.room = room.name;
        return mesh;
      }

      function wall(w, d, x, z, h) {
        return box(w, h || 2.65, d, x, (h || 2.65) / 2, z, materials.wall, wallsGroup);
      }

      function label(text, x, z) {
        var c = document.createElement("canvas");
        c.width = 512;
        c.height = 128;
        var ctx = c.getContext("2d");
        ctx.fillStyle = "rgba(255,253,248,.92)";
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.strokeStyle = "rgba(154,106,42,.45)";
        ctx.lineWidth = 5;
        ctx.strokeRect(3, 3, c.width - 6, c.height - 6);
        ctx.fillStyle = "#273136";
        ctx.font = "700 35px Arial";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(text, c.width / 2, c.height / 2);
        var tex = new THREE.CanvasTexture(c);
        var sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, transparent: true }));
        sprite.position.set(x, 0.18, z);
        sprite.scale.set(1.55, 0.38, 1);
        labelGroup.add(sprite);
      }

      var rooms = [
        { name: "Előszoba 3,73", x: -3.2, z: 1.72, w: 2.4, d: 1.55, mat: materials.service },
        { name: "Hely. 02 2,02", x: -1.35, z: 1.72, w: 1.3, d: 1.55, mat: materials.service },
        { name: "Hely. 05 2,56", x: 0.125, z: 1.72, w: 1.65, d: 1.55, mat: materials.service },
        { name: "Fürdő 3,81", x: 2.175, z: 1.72, w: 2.45, d: 1.55, mat: materials.bath },
        { name: "Nappali 15,99", x: -2.085, z: -0.775, w: 4.63, d: 3.45, mat: materials.living },
        { name: "Konyha 4,46", x: 0.875, z: -0.775, w: 1.29, d: 3.45, mat: materials.kitchen },
        { name: "Szoba 11,17", x: 3.14, z: -0.775, w: 3.24, d: 3.45, mat: materials.bedroom }
      ];
      rooms.forEach(function (room) {
        floor(room);
        label(room.name, room.x, room.z);
      });

      floor({ name: "Terasz 7,30", x: 0.18, z: -3.02, w: 9.16, d: 0.82, mat: materials.terrace });
      label("Terasz 7,30", -2.6, -3.02);
      floor({ name: "Ajándék kert 45,56", x: 0.18, z: -6.05, w: 9.16, d: 5.05, mat: materials.garden });
      label("Ajándék kert 45,56", -1.55, -6.05);

      // Outer and main partition walls. The model is visual, so door gaps are simplified.
      wall(9.26, 0.16, 0.18, 2.58);
      wall(0.16, 5.16, -4.48, 0.0);
      wall(0.16, 5.16, 4.84, 0.0);
      wall(2.9, 0.16, -2.95, -2.58);
      wall(3.7, 0.16, 2.9, -2.58);
      wall(9.16, 0.12, 0.18, 0.95, 2.35);
      wall(0.12, 1.55, -2.0, 1.72, 2.35);
      wall(0.12, 1.55, -0.7, 1.72, 2.35);
      wall(0.12, 1.55, 0.95, 1.72, 2.35);
      wall(0.12, 3.45, 0.23, -0.775, 2.35);
      wall(0.12, 3.45, 1.52, -0.775, 2.35);
      wall(9.16, 0.08, 0.18, -3.43, 0.92);
      box(2.8, 1.5, 0.04, -1.2, 1.1, -2.61, materials.glass, wallsGroup);
      box(2.1, 1.5, 0.04, 3.2, 1.1, -2.61, materials.glass, wallsGroup);

      // Interior staging: lightweight geometry with textured materials for a warmer showroom feel.
      box(2.1, 0.035, 1.22, -2.55, 0.08, -1.28, materials.rug, furnitureGroup);
      box(1.72, 0.36, 0.78, -3.12, 0.24, -1.83, materials.fabric, furnitureGroup);
      box(1.72, 0.78, 0.16, -3.12, 0.55, -2.24, materials.fabric, furnitureGroup);
      box(0.16, 0.52, 0.74, -4.05, 0.38, -1.83, materials.fabric, furnitureGroup);
      box(0.16, 0.52, 0.74, -2.18, 0.38, -1.83, materials.fabric, furnitureGroup);
      box(0.48, 0.16, 0.34, -3.52, 0.55, -1.72, materials.paper, furnitureGroup);
      box(0.48, 0.16, 0.34, -2.73, 0.55, -1.72, materials.warmFabric, furnitureGroup);
      box(0.92, 0.22, 0.58, -2.35, 0.18, -1.08, materials.wood, furnitureGroup);
      box(1.14, 0.64, 0.08, -3.43, 0.58, -0.17, materials.dark, furnitureGroup);
      box(0.56, 0.06, 0.42, -3.43, 0.93, -0.18, materials.light, furnitureGroup);

      box(1.08, 0.92, 2.16, 0.86, 0.5, -0.82, materials.cabinet, furnitureGroup);
      box(1.16, 0.08, 2.22, 0.86, 0.98, -0.82, materials.counter, furnitureGroup);
      box(0.64, 0.44, 0.04, 0.3, 0.55, -1.68, materials.dark, furnitureGroup);
      box(0.28, 0.06, 0.32, 1.02, 1.04, -0.28, materials.metal, furnitureGroup);
      cylinder(0.08, 0.05, 0.62, 1.1, -0.28, materials.glass, furnitureGroup, 18);
      box(0.7, 0.42, 0.46, 0.52, 1.33, -1.7, materials.cabinet, furnitureGroup);
      box(0.7, 0.42, 0.46, 1.22, 1.33, -1.7, materials.cabinet, furnitureGroup);

      box(2.08, 0.24, 1.55, 3.25, 0.18, -1.38, materials.wood, furnitureGroup);
      box(2.0, 0.28, 1.48, 3.25, 0.45, -1.38, materials.paper, furnitureGroup);
      box(0.52, 0.18, 0.46, 2.68, 0.68, -1.96, materials.paper, furnitureGroup);
      box(0.52, 0.18, 0.46, 3.36, 0.68, -1.96, materials.paper, furnitureGroup);
      box(0.52, 0.18, 0.46, 4.02, 0.68, -1.96, materials.warmFabric, furnitureGroup);
      box(0.36, 0.48, 0.42, 2.08, 0.3, -0.02, materials.wood, furnitureGroup);
      box(0.36, 0.48, 0.42, 4.42, 0.3, -0.02, materials.wood, furnitureGroup);
      box(0.82, 1.4, 0.12, 4.54, 0.76, -1.25, materials.cabinet, furnitureGroup);

      box(0.78, 0.52, 0.48, 2.2, 0.31, 1.75, materials.paper, furnitureGroup);
      box(0.58, 0.24, 0.36, 2.2, 0.82, 1.75, materials.glass, furnitureGroup);
      box(0.68, 0.42, 1.18, 2.96, 0.25, 1.75, materials.bath, furnitureGroup);
      box(0.78, 0.08, 1.12, 3.52, 0.92, 1.75, materials.glass, furnitureGroup);
      cylinder(0.18, 0.24, 1.55, 1.88, 1.75, materials.paper, furnitureGroup, 24);

      box(1.48, 0.38, 0.76, -0.2, 0.27, -3.05, materials.wood, furnitureGroup);
      box(0.42, 0.36, 0.42, -1.04, 0.27, -3.06, materials.warmFabric, furnitureGroup);
      box(0.42, 0.36, 0.42, 0.64, 0.27, -3.06, materials.warmFabric, furnitureGroup);
      box(0.94, 0.12, 0.94, 2.66, 0.12, -4.88, materials.wood, furnitureGroup);
      box(1.2, 0.04, 0.34, 2.7, 0.42, -4.88, materials.paper, furnitureGroup);
      plant(3.74, -4.35, 1.15);
      plant(-3.75, -3.12, .86);
      plant(-2.9, -5.25, 1.05);

      box(2.86, 0.08, 0.08, -1.2, 1.87, -2.64, materials.dark, furnitureGroup);
      box(0.08, 1.58, 0.08, -2.6, 1.1, -2.64, materials.dark, furnitureGroup);
      box(0.08, 1.58, 0.08, 0.2, 1.1, -2.64, materials.dark, furnitureGroup);
      box(2.14, 0.08, 0.08, 3.2, 1.87, -2.64, materials.dark, furnitureGroup);
      box(0.08, 1.58, 0.08, 2.15, 1.1, -2.64, materials.dark, furnitureGroup);
      box(0.08, 1.58, 0.08, 4.25, 1.1, -2.64, materials.dark, furnitureGroup);

      box(0.28, 1.42, 0.03, -2.46, 1.08, -2.68, materials.curtain, furnitureGroup);
      box(0.28, 1.42, 0.03, 0.04, 1.08, -2.68, materials.curtain, furnitureGroup);
      box(0.28, 1.42, 0.03, 2.28, 1.08, -2.68, materials.curtain, furnitureGroup);
      box(0.28, 1.42, 0.03, 4.12, 1.08, -2.68, materials.curtain, furnitureGroup);
      box(1.18, 0.58, 0.05, -4.42, 1.3, -0.62, materials.art, furnitureGroup);
      box(0.98, 0.48, 0.05, 4.78, 1.34, -1.55, materials.art, furnitureGroup);
      box(0.08, 0.18, 4.8, -4.42, 0.13, 0.0, materials.wood, furnitureGroup);
      box(0.08, 0.18, 4.8, 4.78, 0.13, 0.0, materials.wood, furnitureGroup);
      box(8.86, 0.18, 0.08, 0.18, 0.13, 2.46, materials.wood, furnitureGroup);
      box(5.7, 0.18, 0.08, 1.16, 0.13, -2.48, materials.wood, furnitureGroup);
      box(1.12, 0.5, 0.04, 1.48, 1.05, -1.68, materials.glass, furnitureGroup);
      box(0.04, 0.74, 0.62, 1.82, 1.28, 1.75, materials.glass, furnitureGroup);

      ceilingLight(-2.55, -0.85, 1.2);
      ceilingLight(0.92, -0.85, 0.95);
      ceilingLight(3.18, -0.95, 0.92);
      ceilingLight(2.22, 1.72, 0.8);

      scene.add(wallsGroup);
      scene.add(furnitureGroup);
      scene.add(labelGroup);

      var ambient = new THREE.HemisphereLight(0xfff4df, 0x1b332b, 0.82);
      scene.add(ambient);
      var sun = new THREE.DirectionalLight(0xffead0, 3.15);
      sun.position.set(-4.5, 8.5, -6.8);
      sun.castShadow = true;
      sun.shadow.mapSize.set(1024, 1024);
      sun.shadow.camera.near = 1;
      sun.shadow.camera.far = 24;
      scene.add(sun);
      var windowFill = new THREE.DirectionalLight(0xcfe9ff, 0.62);
      windowFill.position.set(3.8, 3.2, -4.8);
      scene.add(windowFill);
      var grid = new THREE.GridHelper(16, 16, 0x8e7c62, 0x564d3e);
      grid.position.y = -0.04;
      scene.add(grid);

      function countObjects(root) {
        var total = 0;
        root.traverse(function () { total += 1; });
        return total;
      }
      if (window.H22_3D_STATE) {
        window.H22_3D_STATE.initialized = true;
        window.H22_3D_STATE.objectCount = countObjects(scene);
      }

      function resize() {
        var rect = viewer.getBoundingClientRect();
        var w = Math.max(320, rect.width);
        var h = Math.max(320, rect.height);
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
      }

      function updateCamera() {
        pitch = Math.max(0.16, Math.min(1.42, pitch));
        distance = Math.max(1.2, Math.min(24, distance));
        var x = target.x + distance * Math.sin(yaw) * Math.cos(pitch);
        var z = target.z + distance * Math.cos(yaw) * Math.cos(pitch);
        var y = target.y + distance * Math.sin(pitch);
        camera.position.set(x, y, z);
        camera.lookAt(target);
      }

      function smooth(t) {
        return t * t * (3 - 2 * t);
      }

      function mix(a, b, t) {
        return a + (b - a) * t;
      }

      function setTourText(frame) {
        if (tourTitle) tourTitle.textContent = frame.label || "Automatikus lakásbejárás";
        if (tourNote) tourNote.textContent = frame.note || "Ügyfél szemmagasságú automatikus bemutató.";
      }

      function setTourButton() {
        if (!tourButton) return;
        tourButton.classList.toggle("active", tourRunning);
        tourButton.textContent = tourRunning ? "Séta leállítása" : "Séta indítása";
      }

      function applyTourFrame(frame) {
        yaw = frame.yaw;
        pitch = frame.pitch;
        distance = frame.distance;
        target.set(frame.target[0], frame.target[1], frame.target[2]);
        camera.fov = frame.fov || 58;
        camera.updateProjectionMatrix();
        labelGroup.visible = false;
        updateCamera();
        setTourText(frame);
      }

      function startTour(reset) {
        tourRunning = true;
        tourStart = performance.now();
        setTourButton();
        applyTourFrame(tourSteps[0]);
        canvas.setAttribute("data-h22-tour-running", "1");
        if (window.H22_3D_STATE) {
          window.H22_3D_STATE.tourRunning = true;
          window.H22_3D_STATE.tourStep = "Nappali";
        }
      }

      function stopTour() {
        if (!tourRunning) return;
        tourRunning = false;
        setTourButton();
        canvas.setAttribute("data-h22-tour-running", "0");
        if (window.H22_3D_STATE) {
          window.H22_3D_STATE.tourRunning = false;
        }
      }

      function updateTour(now) {
        if (!tourRunning) return;
        var t = (now - tourStart) % tourDuration;
        var from = tourSteps[0];
        var to = tourSteps[1];
        for (var i = 0; i < tourSteps.length - 1; i += 1) {
          if (t >= tourSteps[i].t && t <= tourSteps[i + 1].t) {
            from = tourSteps[i];
            to = tourSteps[i + 1];
            break;
          }
        }
        var local = smooth((t - from.t) / Math.max(1, to.t - from.t));
        var frame = {
          label: from.label,
          note: from.note,
          yaw: mix(from.yaw, to.yaw, local),
          pitch: mix(from.pitch, to.pitch, local),
          distance: mix(from.distance, to.distance, local),
          fov: mix(from.fov || 58, to.fov || 58, local),
          target: [
            mix(from.target[0], to.target[0], local),
            mix(from.target[1], to.target[1], local),
            mix(from.target[2], to.target[2], local)
          ]
        };
        applyTourFrame(frame);
        canvas.setAttribute("data-h22-tour-running", "1");
        canvas.setAttribute("data-h22-tour-step", from.label);
        if (window.H22_3D_STATE) {
          window.H22_3D_STATE.tourRunning = true;
          window.H22_3D_STATE.tourStep = from.label;
        }
      }

      function animate() {
        requestAnimationFrame(animate);
        updateTour(performance.now());
        labelGroup.children.forEach(function (sprite) {
          sprite.lookAt(camera.position);
        });
        renderer.render(scene, camera);
        canvas.setAttribute("data-h22-rendered", "1");
        if (window.H22_3D_STATE) {
          window.H22_3D_STATE.rendered = true;
          window.H22_3D_STATE.frameCount += 1;
          canvas.setAttribute("data-h22-frame-count", String(window.H22_3D_STATE.frameCount));
          if (!window.H22_3D_STATE.samplePixel) {
            try {
              var gl = renderer.getContext();
              var sample = new Uint8Array(4);
              gl.readPixels(
                Math.max(0, Math.floor(renderer.domElement.width / 2)),
                Math.max(0, Math.floor(renderer.domElement.height / 2)),
                1,
                1,
                gl.RGBA,
                gl.UNSIGNED_BYTE,
                sample
              );
              window.H22_3D_STATE.samplePixel = Array.prototype.slice.call(sample);
              window.H22_3D_STATE.nonBlankPixel = sample[3] > 0 && (sample[0] + sample[1] + sample[2]) > 0;
              canvas.setAttribute("data-h22-sample-pixel", window.H22_3D_STATE.samplePixel.join(","));
              canvas.setAttribute("data-h22-nonblank-pixel", window.H22_3D_STATE.nonBlankPixel ? "1" : "0");
            } catch (err) {
              window.H22_3D_STATE.samplePixel = ["unavailable"];
              canvas.setAttribute("data-h22-sample-pixel", "unavailable");
            }
          }
        }
      }

      function setView(name) {
        var compact = viewer.getBoundingClientRect().width < 520;
        var immersive = name === "walk" || name === "living" || name === "bedroom";
        document.querySelectorAll("[data-view]").forEach(function (button) {
          button.classList.toggle("active", button.dataset.view === name);
        });
        labelGroup.visible = name === "overview" || name === "top";
        camera.fov = immersive ? 58 : 45;
        camera.updateProjectionMatrix();
        if (name === "top") { yaw = 0.01; pitch = 1.42; distance = compact ? 15 : 12; target.set(0.15, 0.1, -1.2); }
        if (name === "overview") { yaw = -0.72; pitch = 0.78; distance = compact ? 18.5 : 13.5; target.set(0, 0.7, -0.9); }
        if (name === "walk") { yaw = 0.03; pitch = 0.18; distance = compact ? 2.05 : 1.65; target.set(-0.55, 1.08, -2.24); }
        if (name === "living") { yaw = 0.85; pitch = 0.2; distance = compact ? 2.55 : 2.12; target.set(-3.18, 1.05, -1.86); }
        if (name === "bedroom") { yaw = 0.06; pitch = 0.2; distance = compact ? 2.2 : 1.75; target.set(3.18, 1.02, -1.78); }
        if (name === "garden") { yaw = -0.25; pitch = 0.45; distance = compact ? 11 : 9.4; target.set(0.15, 0.35, -5.6); }
        updateCamera();
      }

      canvas.addEventListener("pointerdown", function (event) {
        stopTour();
        dragging = true;
        lastX = event.clientX;
        lastY = event.clientY;
        canvas.setPointerCapture(event.pointerId);
      });
      canvas.addEventListener("pointermove", function (event) {
        if (!dragging) return;
        var dx = event.clientX - lastX;
        var dy = event.clientY - lastY;
        lastX = event.clientX;
        lastY = event.clientY;
        yaw -= dx * 0.006;
        pitch += dy * 0.004;
        updateCamera();
      });
      function stopDrag() { dragging = false; }
      canvas.addEventListener("pointerup", stopDrag);
      canvas.addEventListener("pointercancel", stopDrag);
      canvas.addEventListener("wheel", function (event) {
        event.preventDefault();
        stopTour();
        distance += event.deltaY * 0.01;
        updateCamera();
      }, { passive: false });

      document.querySelectorAll("[data-view]").forEach(function (button) {
        button.addEventListener("click", function () {
          stopTour();
          setView(button.dataset.view);
          if (window.history && window.history.replaceState) {
            var params = new URLSearchParams(window.location.search);
            params.set("view", button.dataset.view);
            window.history.replaceState(null, "", window.location.pathname + "?" + params.toString());
          }
        });
      });
      if (tourButton) {
        tourButton.addEventListener("click", function () {
          if (tourRunning) {
            stopTour();
          } else {
            startTour(true);
          }
        });
      }
      document.querySelector("[data-toggle='walls']").addEventListener("click", function (event) {
        stopTour();
        wallsGroup.visible = !wallsGroup.visible;
        event.currentTarget.classList.toggle("active", wallsGroup.visible);
      });
      document.querySelector("[data-toggle='furniture']").addEventListener("click", function (event) {
        stopTour();
        furnitureGroup.visible = !furnitureGroup.visible;
        event.currentTarget.classList.toggle("active", furnitureGroup.visible);
      });

      window.addEventListener("resize", resize);
      resize();
      var allowedViews = ["overview", "top", "walk", "living", "bedroom", "garden"];
      var searchParams = new URLSearchParams(window.location.search);
      var shouldAutoplay = searchParams.get("autoplay") === "1";
      var initialView = searchParams.get("view") || "overview";
      if (allowedViews.indexOf(initialView) === -1) initialView = "overview";
      setView(initialView);
      if (shouldAutoplay) startTour(true);
      setTourButton();
      animate();
      if (loading) loading.style.display = "none";
    })();
  </script>
</body>
</html>
    <?php
    exit;
}

add_action('template_redirect', 'h22_vr_demo_render_page', 0);

function h22_vr_demo_property_button() {
    if (h22_vr_demo_is_retired()) {
        return;
    }
    if (h22_vr_demo_current_path() !== 'property/a1-f-l3') {
        return;
    }

    $vr_url = h22_vr_demo_url('vr-a1-f-l3/');
    $model_url = h22_vr_demo_url('3d-a1-f-l3/');
    ?>
    <style id="h22-vr-demo-property-cta-style">
      .h22-vr-inline-wrap {
        margin: 18px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
      }
      .h22-vr-inline-wrap .h22-vr-property-cta,
      .h22-vr-property-cta.h22-vr-fixed {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 16px;
        border-radius: 8px;
        background: #29473f;
        color: #fff !important;
        font: 800 13px/1 Montserrat, Arial, sans-serif;
        letter-spacing: 0;
        text-decoration: none !important;
        box-shadow: 0 12px 30px rgba(37, 32, 23, .18);
      }
      .h22-vr-inline-wrap span {
        color: #6d7476;
        font: 700 12px/1.45 Montserrat, Arial, sans-serif;
      }
      .h22-vr-property-cta.h22-vr-fixed {
        position: fixed;
        left: 18px;
        bottom: 18px;
        z-index: 99980;
      }
      @media (max-width: 640px) {
        .h22-vr-property-cta.h22-vr-fixed {
          left: 12px;
          bottom: 12px;
        }
      }
    </style>
    <script id="h22-vr-demo-property-cta-script">
      (function () {
        if (document.querySelector("[data-h22-vr-cta]")) return;
        var href = <?php echo wp_json_encode($vr_url); ?>;
        var modelHref = <?php echo wp_json_encode($model_url); ?>;
        var heading = document.querySelector(".elementor-widget-theme-post-title h1, h1.entry-title, .property_title, h1");
        var wrap = document.createElement("div");
        wrap.className = "h22-vr-inline-wrap";
        wrap.setAttribute("data-h22-vr-cta", "1");
        wrap.innerHTML = '<a class="h22-vr-property-cta" href="' + modelHref + '">3D lakásmodell</a><a class="h22-vr-property-cta" href="' + href + '">Virtuális lakásbemutató</a><span>A1-F-L3 demo látványterv és alaprajz alapján</span>';
        if (heading) {
          var target = heading.closest(".elementor-widget, header, .entry-header") || heading;
          target.insertAdjacentElement("afterend", wrap);
        } else {
          var link = document.createElement("a");
          link.className = "h22-vr-property-cta h22-vr-fixed";
          link.href = modelHref;
          link.setAttribute("data-h22-vr-cta", "1");
          link.textContent = "3D lakásmodell";
          document.body.appendChild(link);
        }
      })();
    </script>
    <?php
}
add_action('wp_footer', 'h22_vr_demo_property_button', 90);
