<?php
/** Include returns main HTML only; caller supplies fresh public-summary rows keyed by code. */
return (static function (array $harmat_home_family_units): string {
    $escape = static function ($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $public_url = static function ($value): bool {
        if (!is_string($value) || preg_match('/[\x00-\x20\x7f]/', $value)) return false;
        $parts = parse_url($value);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === 'harmat22.hu'
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
            && strpos($parts['path'] ?? '', '/') === 0;
    };
    $number = static function ($value, float $max): bool {
        return (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+(?:\.\d+)?$/D', $value)))
            && is_finite((float) $value) && (float) $value > 0 && (float) $value <= $max;
    };
    $cards = '';
    $floors = array('A1-1-L6' => array('a1Floor1', 'A1 épület · 1. emelet'),
        'A2-4-L5' => array('a2Floor4', 'A2 épület · 4. emelet'),
        'A1-1-L5' => array('a1Floor1', 'A1 épület · 1. emelet'),
        'A3-F-L3' => array('a3GroundFloor', 'A3 épület · Földszint'),
        'A4-F-L1' => array('a4GroundFloor', 'A4 épület · Földszint'),
        'A3-2-L2' => array('a3Floor2', 'A3 épület · 2. emelet'));
    foreach ($floors as $code => $floor) {
        $row = $harmat_home_family_units[$code] ?? null;
        if (!is_array($row) || ($row['code'] ?? null) !== $code || ($row['status'] ?? null) !== 'current'
            || !$public_url($row['url'] ?? null) || !$public_url($row['floorplan_url'] ?? null)
            || !$number($row['rooms'] ?? null, 20) || !$number($row['sales_area'] ?? null, 2000)
            || !array_key_exists('price_huf', $row)
            || ($row['price_huf'] !== null && !$number($row['price_huf'], 1000000000000))) continue;
        $url = $escape($row['url']);
        $image = $escape($row['floorplan_url']);
        $rooms = $escape(rtrim(rtrim(number_format((float) $row['rooms'], 2, ',', ''), '0'), ','));
        $room_keys = array('1' => array('oneRoom', '1 szoba'), '2' => array('twoRooms', '2 szoba'), '3' => array('threeRooms', '3 szoba'), '4' => array('fourRooms', '4 szoba'));
        $room_copy = $room_keys[(string) (float) $row['rooms']] ?? null;
        $rooms_html = $room_copy === null ? '<dd>' . $rooms . '</dd>'
            : '<dd data-i18n="' . $escape($room_copy[0]) . '">' . $escape($room_copy[1]) . '</dd>';
        $area = $escape(number_format((float) $row['sales_area'], 2, ',', ' '));
        $price = $row['price_huf'] === null
            ? '<strong class="hhf-price-hidden" data-i18n="hiddenPrice">Ár egyeztetés alapján</strong>'
            : '<strong>' . $escape(number_format((float) $row['price_huf'], 0, ',', ' ')) . ' <span>Ft</span></strong>';
        $cards .= '<article class="hhf-apartment" data-code="' . $escape($code) . '" data-status="current">'
            . '<div class="hhf-apartment-top"><h3>' . $escape($code) . '</h3><span class="hhf-status" data-i18n="available">Elérhető</span></div>'
            . '<p class="hhf-apartment-floor" data-i18n="' . $escape($floor[0]) . '">' . $escape($floor[1]) . '</p>'
            . '<a class="hhf-floorplan" href="' . $url . '"><img src="' . $image . '" width="700" height="600" alt="' . $escape($code) . '" loading="lazy" decoding="async"></a>'
            . '<div class="hhf-apartment-details"><dl class="hhf-apartment-facts"><div><dt data-i18n="roomCount">Szobák</dt>' . $rooms_html . '</div>'
            . '<div><dt data-i18n="salesArea">Értékesítési terület</dt><dd>' . $area . ' m²</dd></div></dl>'
            . '<p class="hhf-price"><span data-i18n="price">Vételár</span>' . $price . '</p>'
            . '<a class="hhf-apartment-link" href="' . $url . '"><span data-i18n="apartmentDetails">Lakás adatlapja</span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 7h10v10"></path><path d="M7 17 17 7"></path></svg></a></div></article>';
    }
    $html = <<<'HHF_MAIN_HTML'
<main id="main" class="site-main"><div id="harmat-home-family" data-language-protect="" lang="hu">
    <section class="hhf-hero" id="hhf-kezdolap" aria-labelledby="hero-title">
      <img class="hhf-hero-poster" src="https://harmat22.hu/wp-content/mu-plugins/harmat-project-family/project-aerial.jpg" width="1920" height="1080" alt="A Harmat Lakópark tervezett épületeinek és zöld udvarának madártávlati látványterve" data-i18n-alt="heroAlt" fetchpriority="high">
      <video class="hhf-hero-video" id="hhf-hero-video" muted="" playsinline="" loop="" preload="none" poster="https://harmat22.hu/wp-content/mu-plugins/harmat-project-family/project-aerial.jpg" data-src="https://harmat22.hu/wp-content/uploads/harmat-video/harmat-home-1080p-v2.mp4" aria-hidden="true" tabindex="-1"></video>
      <div class="hhf-container hhf-hero-inner">
        <div class="hhf-hero-copy">
          <h1 id="hero-title">Harmat Lakópark</h1>
          <div class="hhf-actions hhf-hero-actions">
            <a class="hhf-button hhf-button-white" href="#hhf-lakasok"><span data-i18n="chooseApartment">Lakást választok</span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg></a>
            <a class="hhf-hero-journal" href="#hhf-epitesi-naplo"><span data-i18n="readJournal">Építési napló</span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg></a>
          </div>
        </div>
        <div class="hhf-hero-bottom">
          <span class="hhf-media-label" data-i18n="rendering">Látványterv</span>
          <div class="hhf-video-control" hidden="">
            <button class="hhf-icon-button hhf-video-button" type="button" aria-controls="hhf-hero-video" aria-label="Videó lejátszása" aria-describedby="hhf-video-tooltip" title="Videó lejátszása">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hhf-video-play-icon" aria-hidden="true" focusable="false"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hhf-video-pause-icon" aria-hidden="true" hidden="" focusable="false"><rect x="14" y="4" width="4" height="16" rx="1"></rect><rect x="6" y="4" width="4" height="16" rx="1"></rect></svg>
            </button>
            <span class="hhf-tooltip" id="hhf-video-tooltip" role="tooltip">Videó lejátszása</span>
          </div>
        </div>
      </div>
      <p class="hhf-sr-only" id="hhf-video-status" role="status"></p>
    </section>

    <section class="hhf-highlights" aria-label="A lakópark számokban" data-i18n-aria-label="highlights">
      <dl class="hhf-container hhf-highlight-grid">
        <div><dt data-i18n="greenLabel">Zöldfelület</dt><dd><strong>75%</strong><span data-i18n="greenNote">a tervek szerint</span></dd></div>
        <div><dt data-i18n="phaseLabel">Az első ütemben</dt><dd><strong><span>124</span> <span class="hhf-highlight-unit" data-i18n="homes">otthon</span></strong><span data-i18n="phaseNote">különböző élethelyzetekhez</span></dd></div>
        <div><dt data-i18n="comfortLabel">Otthoni komfort</dt><dd><strong class="hhf-highlight-word" data-i18n="heatPump">Hőszivattyú</strong><span data-i18n="comfortNote">fűtés és hűtés</span></dd></div>
        <div><dt data-i18n="handoverLabel">Tervezett átadás</dt><dd><strong class="hhf-highlight-date">2028 <span data-i18n="quarter">II. negyedév</span></strong><span data-i18n="handoverNote">tervezett időpont</span></dd></div>
      </dl>
    </section>

    <section class="hhf-section hhf-apartments" id="hhf-lakasok" aria-labelledby="hhf-apartments-title">
      <div class="hhf-container">
        <div class="hhf-section-heading">
          <div><p class="hhf-eyebrow" data-i18n="apartmentsEyebrow">Találja meg a saját helyét</p><h2 id="hhf-apartments-title" data-i18n="apartmentsTitle">Kicsi kezdetek. Nagy közös tervek.</h2></div>
          <a class="hhf-text-link" href="https://harmat22.hu/lakaskereso/"><span data-i18n="allApartments">Összes lakás</span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 7h10v10"></path><path d="M7 17 17 7"></path></svg></a>
        </div>
        <div class="hhf-apartment-grid" id="hhf-apartment-grid">__HHF_UNITS_HTML__</div>
        <button class="hhf-button hhf-apartment-toggle" type="button" aria-controls="hhf-apartment-grid" aria-expanded="false" hidden>További lakások</button>
        <p class="hhf-fine-print hhf-apartment-note" data-i18n="priceNote">Az árak és az elérhetőség változhatnak. Az aktuális feltételekről a hivatalos lakásadatlapokon és értékesítési csapatunknál tájékozódhat.</p>
      </div>
    </section>

    <section class="hhf-section hhf-journal" id="hhf-epitesi-naplo" aria-labelledby="hhf-journal-title">
      <div class="hhf-container hhf-journal-grid">
        <figure class="hhf-journal-figure">
          <img src="https://harmat22.hu/wp-content/mu-plugins/harmat-project-family/construction-site.webp" width="1200" height="900" alt="A Harmat Lakópark építkezése a szeptemberi beszámoló helyszíni felvételén" data-i18n-alt="constructionAlt" loading="lazy" decoding="async">
          <figcaption><span class="hhf-caption-label" data-i18n="sitePhoto">Helyszíni felvétel</span><span data-i18n="constructionCaption">A szeptember 30-i beszámoló október 2-i kiegészítése.</span></figcaption>
        </figure>
        <div class="hhf-journal-copy">
          <p class="hhf-eyebrow" data-i18n="navJournal">Építési napló</p>
          <p class="hhf-journal-date"><time datetime="2026-09-30" data-i18n="journalDate">2026. szeptember 30-i beszámoló</time></p>
          <h2 id="hhf-journal-title" data-i18n="journalTitle">Már épül a következő fejezet.</h2>
          <p data-i18n="journalDescription">Az első ütem alapozási és pincei szerkezetépítési munkái a szeptemberi beszámolóban. Valódi helyszíni képek, az október 2-i felvétellel kiegészítve.</p>
          <a class="hhf-text-link" href="https://harmat22.hu/epitesi-naplo/"><span data-i18n="journalLink">Megnézem az építési naplót</span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 7h10v10"></path><path d="M7 17 17 7"></path></svg></a>
          <p class="hhf-planned-note"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="M8 14h.01"></path><path d="M12 14h.01"></path><path d="M16 14h.01"></path><path d="M8 18h.01"></path><path d="M12 18h.01"></path><path d="M16 18h.01"></path></svg><span data-i18n="plannedNote">Tervezett átadás: 2028. II. negyedév. A dátum tervezett, nem garantált időpont.</span></p>
        </div>
      </div>
    </section>

    <section class="hhf-section hhf-everyday" id="hhf-mindennapok" aria-labelledby="hhf-everyday-title">
      <div class="hhf-container">
        <div class="hhf-section-heading hhf-everyday-heading"><div><p class="hhf-eyebrow" data-i18n="everydayEyebrow">Ami igazán otthonossá teszi</p><h2 id="hhf-everyday-title" data-i18n="everydayTitle">Hely a családnak. Tér a mindennapoknak.</h2></div><p data-i18n="everydayIntro">Közös reggelik, egy kis levegő, hazaérkezés. Egy otthon a hétköznapi pillanatoktól lesz igazán a miénk.</p></div>
        <div class="hhf-everyday-grid">
          <div class="hhf-everyday-story">
            <figure><img src="https://harmat22.hu/wp-content/mu-plugins/harmat-project-family/project-courtyard.jpg" width="1200" height="800" alt="A Harmat Lakópark tervezett zöld belső udvara" data-i18n-alt="courtyardAlt" loading="lazy" decoding="async"><figcaption data-i18n="rendering">Látványterv</figcaption></figure>
            <h3 data-i18n="courtyardTitle">Zöld a házak között</h3>
            <p data-i18n="courtyardDescription">A tervek szerinti 75%-os zöldfelület a lakópark egyik meghatározó eleme. Több tér a levegőnek, a pihenésnek, az együtt töltött időnek.</p>
          </div>
          <div class="hhf-everyday-story">
            <figure><img src="https://harmat22.hu/wp-content/mu-plugins/harmat-project-family/project-living-room.jpg" width="1200" height="800" alt="Világos családi nappali a Harmat Lakópark belsőépítészeti látványtervén" data-i18n-alt="livingRoomAlt" loading="lazy" decoding="async"><figcaption data-i18n="rendering">Látványterv</figcaption></figure>
            <h3 data-i18n="livingRoomTitle">Otthon, a saját ritmusában</h3>
            <p data-i18n="livingRoomDescription">Hőszivattyús fűtés és hűtés, mélygarázs és tárolók szerepelnek a projekt terveiben. Az egyes lakások részleteit az alaprajzokon és a műszaki leírásban ismerheti meg.</p>
          </div>
        </div>
        <div class="hhf-park-row">
          <figure><img src="https://harmat22.hu/wp-content/uploads/2026/09/harmat-kornyek-kutyapark-2026-09.jpg" width="900" height="600" alt="Közeli nyilvános park a Harmat Lakópark környékén" data-i18n-alt="parkAlt" loading="lazy" decoding="async"><figcaption data-i18n="parkCaption">Helyszíni felvétel · közeli nyilvános park, nem a lakópark belső kertje</figcaption></figure>
          <div><p class="hhf-eyebrow" data-i18n="neighborhoodEyebrow">Kőbánya, közelebbről</p><h3 data-i18n="parkTitle">Egy séta is belefér.</h3><p data-i18n="parkDescription">Az otthon nem ér véget a bejárati ajtónál. Ismerje meg a Harmat utca környékét és a közeli parkot is.</p><a class="hhf-text-link" href="https://harmat22.hu/harmat-lakopark-kornyeke/"><span data-i18n="neighborhoodLink">A környék felfedezése</span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 7h10v10"></path><path d="M7 17 17 7"></path></svg></a></div>
        </div>
        <div class="hhf-showroom-row">
          <div class="hhf-showroom-copy"><p class="hhf-eyebrow" data-i18n="showroomEyebrow">Találkozzunk személyesen</p><h2 data-i18n="showroomTitle">Kérdésekből közös tervek.</h2><p data-i18n="showroomDescription">Nézzük át együtt az alaprajzokat, a lehetőségeket és az Önök elképzeléseit. Bemutatótermünkben személyesen is beszélhet értékesítési csapatunkkal.</p><a class="hhf-text-link" href="#hhf-kapcsolat"><span data-i18n="contactTeam">Beszélek a csapattal</span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg></a></div>
          <figure><img src="https://harmat22.hu/wp-content/mu-plugins/harmat-contact-family/showroom-lounge.jpg" width="1200" height="800" alt="A Harmat Lakópark bemutatótermének társalgója" data-i18n-alt="showroomAlt" loading="lazy" decoding="async"><figcaption data-i18n="showroomCaption">Helyszíni felvétel · bemutatóterem</figcaption></figure>
        </div>
      </div>
    </section>

    <section class="hhf-section hhf-panorama" id="hhf-panorama" aria-labelledby="hhf-panorama-title">
      <div class="hhf-container">
        <div class="hhf-panorama-heading">
          <div><p class="hhf-eyebrow" data-i18n="panoramaEyebrow">Harmat Lakópark · 3D/360°</p><h2 id="hhf-panorama-title" data-i18n="panoramaTitle">360° panoráma</h2></div>
        </div>
        <figure class="hhf-panorama-figure">
          <div class="hhf-panorama-console" data-panorama-console="" aria-label="Harmat Lakópark interaktív bemutató" data-i18n-aria-label="panoramaConsole">
            <div class="hhf-panorama-screen">
              <div class="hhf-panorama-panel" id="hhf-panorama-panel-panorama" data-panorama-panel="panorama" role="tabpanel" aria-labelledby="hhf-panorama-tab-panorama">
                <div class="hhf-panorama-viewer" id="hhf-panorama-viewer" data-panorama-state="idle" data-cube-f="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_f.jpg" data-cube-r="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_r.jpg" data-cube-b="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_b.jpg" data-cube-l="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_l.jpg" data-cube-u="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_u.jpg" data-cube-d="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_d.jpg" aria-label="Panorámás látványtér" data-i18n-aria-label="panoramaView" tabindex="0"></div>
                <img class="hhf-panorama-fallback" src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_f.jpg" width="2048" height="2048" alt="A Harmat Lakópark tervezett épületeinek külső látványterve a 360°-os panorámából" data-i18n-alt="panoramaAlt" loading="lazy" decoding="async">
                <span class="hhf-media-label hhf-panorama-label" data-i18n="rendering">Látványterv</span>
                <p class="hhf-panorama-status" data-panorama-status="" role="status" hidden=""></p>
              </div>
              <div class="hhf-panorama-panel" id="hhf-panorama-panel-video" data-panorama-panel="video" role="tabpanel" aria-labelledby="hhf-panorama-tab-video" tabindex="0" hidden="">
                <div class="hhf-panorama-videos">
                  <article class="hhf-panorama-video-card">
                    <video controls="" playsinline="" preload="none" poster="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/video_swsp_xmsp.jpg" data-src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/swsp_xmsp.mp4" data-playback-rate="0.25" aria-label="Projektbemutató" data-i18n-aria-label="panoramaProjectVideo"></video>
                    <div><h3 data-i18n="panoramaProjectVideo">Projektbemutató</h3><p data-i18n="panoramaProjectVideoCopy">A lakópark elhelyezkedése, épülettömege és környezeti kapcsolatai.</p><p class="hhf-panorama-video-error" role="status" hidden=""></p></div>
                  </article>
                  <article class="hhf-panorama-video-card">
                    <video controls="" playsinline="" preload="none" poster="https://harmat22.hu/wp-content/mu-plugins/harmat-project-family/project-aerial.jpg" data-src="https://harmat22.hu/wp-content/uploads/harmat-video/harmat-home-1080p-v2.mp4" data-playback-rate="1" aria-label="Látványvideó" data-i18n-aria-label="panoramaVisualVideo"></video>
                    <div><h3 data-i18n="panoramaVisualVideo">Látványvideó</h3><p data-i18n="panoramaVisualVideoCopy">A tervezett lakókörnyezet és a projekt hangulata.</p><p class="hhf-panorama-video-error" role="status" hidden=""></p></div>
                  </article>
                </div>
              </div>
              <div class="hhf-panorama-panel" id="hhf-panorama-panel-plans" data-panorama-panel="plans" role="tabpanel" aria-labelledby="hhf-panorama-tab-plans" tabindex="0" hidden="">
                <div class="hhf-panorama-split">
                  <div class="hhf-panorama-detail"><p class="hhf-eyebrow" data-i18n="panoramaProjectOverview">Projektáttekintés</p><h3 data-i18n="panoramaProjectTitle">Modern lakókörnyezet Kőbányán</h3><p data-i18n="panoramaProjectCopy">A Harmat Lakópark a X. kerületben, a Harmat utca 22. szám alatt kínál új építésű otthonokat átgondolt alaprajzokkal, zöld környezettel és kényelmes városi kapcsolatokkal.</p><dl class="hhf-panorama-facts"><div><dt data-i18n="phaseLabel">Az első ütemben</dt><dd>124 <span data-i18n="homes">lakás</span></dd></div><div><dt data-i18n="panoramaLocation">Környezet</dt><dd>Harmat utca 22.</dd></div><div><dt data-i18n="panoramaGreen">Zöld környezet</dt><dd data-i18n="panoramaUrban">Élhető városi ritmus</dd></div></dl></div>
                  <button class="hhf-panorama-image" type="button" data-gallery="" aria-label="Madártávlati nézet nagyítása" data-i18n-aria-label="panoramaAerialZoom" title="Madártávlati nézet nagyítása" data-i18n-title="panoramaAerialZoom"><img src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_8.jpg" width="1920" height="1080" alt="Madártávlati nézet" data-i18n-alt="panoramaGallery8" loading="lazy" decoding="async"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"></circle><line x1="21" x2="16.65" y1="21" y2="16.65"></line><line x1="11" x2="11" y1="8" y2="14"></line><line x1="8" x2="14" y1="11" y2="11"></line></svg></button>
                </div>
              </div>
              <div class="hhf-panorama-panel" id="hhf-panorama-panel-gallery" data-panorama-panel="gallery" role="tabpanel" aria-labelledby="hhf-panorama-tab-gallery" tabindex="0" hidden="">
                <div class="hhf-panorama-gallery">
                  <button type="button" data-gallery="" aria-label="Áttekintő látvány nagyítása" data-i18n-aria-label="panoramaGallery0Zoom" title="Áttekintő látvány nagyítása" data-i18n-title="panoramaGallery0Zoom"><img src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_0.jpg" width="1920" height="1080" alt="Áttekintő látvány" data-i18n-alt="panoramaGallery0" loading="lazy" decoding="async"><span data-i18n="panoramaGallery0">Áttekintő látvány</span></button>
                  <button type="button" data-gallery="" aria-label="Épülethomlokzat nagyítása" data-i18n-aria-label="panoramaGallery1Zoom" title="Épülethomlokzat nagyítása" data-i18n-title="panoramaGallery1Zoom"><img src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_1.jpg" width="1920" height="1080" alt="Épülethomlokzat" data-i18n-alt="panoramaGallery1" loading="lazy" decoding="async"><span data-i18n="panoramaGallery1">Épülethomlokzat</span></button>
                  <button type="button" data-gallery="" aria-label="Lakókörnyezeti tér nagyítása" data-i18n-aria-label="panoramaGallery4Zoom" title="Lakókörnyezeti tér nagyítása" data-i18n-title="panoramaGallery4Zoom"><img src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_4.jpg" width="1920" height="1080" alt="Lakókörnyezeti tér" data-i18n-alt="panoramaGallery4" loading="lazy" decoding="async"><span data-i18n="panoramaGallery4">Lakókörnyezeti tér</span></button>
                  <button type="button" data-gallery="" aria-label="Madártávlati nézet nagyítása" data-i18n-aria-label="panoramaAerialZoom" title="Madártávlati nézet nagyítása" data-i18n-title="panoramaAerialZoom"><img src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_8.jpg" width="1920" height="1080" alt="Madártávlati nézet" data-i18n-alt="panoramaGallery8" loading="lazy" decoding="async"><span data-i18n="panoramaGallery8">Madártávlati nézet</span></button>
                  <button type="button" data-gallery="" aria-label="Lakóépületi részlet nagyítása" data-i18n-aria-label="panoramaGallery10Zoom" title="Lakóépületi részlet nagyítása" data-i18n-title="panoramaGallery10Zoom"><img src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_10.jpg" width="1920" height="1080" alt="Lakóépületi részlet" data-i18n-alt="panoramaGallery10" loading="lazy" decoding="async"><span data-i18n="panoramaGallery10">Lakóépületi részlet</span></button>
                  <button type="button" data-gallery="" aria-label="Kert és közösségi tér nagyítása" data-i18n-aria-label="panoramaGallery5Zoom" title="Kert és közösségi tér nagyítása" data-i18n-title="panoramaGallery5Zoom"><img src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_5.jpg" width="1920" height="1080" alt="Kert és közösségi tér" data-i18n-alt="panoramaGallery5" loading="lazy" decoding="async"><span data-i18n="panoramaGallery5">Kert és közösségi tér</span></button>
                </div>
              </div>
              <div class="hhf-panorama-panel" id="hhf-panorama-panel-location" data-panorama-panel="location" role="tabpanel" aria-labelledby="hhf-panorama-tab-location" tabindex="0" hidden="">
                <div class="hhf-panorama-split">
                  <button class="hhf-panorama-image" type="button" data-gallery="" aria-label="Környezeti áttekintő nagyítása" data-i18n-aria-label="panoramaOverviewZoom" title="Környezeti áttekintő nagyítása" data-i18n-title="panoramaOverviewZoom"><img src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/video_swsp_xmsp.jpg" width="1920" height="1080" alt="Harmat Lakópark környezeti áttekintő" data-i18n-alt="panoramaOverviewAlt" loading="lazy" decoding="async"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"></circle><line x1="21" x2="16.65" y1="21" y2="16.65"></line><line x1="11" x2="11" y1="8" y2="14"></line><line x1="8" x2="14" y1="11" y2="11"></line></svg></button>
                  <div class="hhf-panorama-detail"><p class="hhf-eyebrow" data-i18n="panoramaLocation">Környezet</p><h3 data-i18n="panoramaLocationTitle">A város és a természet találkozása</h3><p data-i18n="panoramaLocationCopy">A környék mindennapi élethez szükséges szolgáltatásokat, zöldterületeket és jó városi kapcsolatokat kínál.</p><ul><li data-i18n="panoramaAddress">Budapest X. kerület, Harmat utca 22.</li><li data-i18n="panoramaLocationServices">Közeli bevásárlási, oktatási és egészségügyi lehetőségek</li></ul></div>
                </div>
              </div>
              <div class="hhf-panorama-panel" id="hhf-panorama-panel-notice" data-panorama-panel="notice" role="tabpanel" aria-labelledby="hhf-panorama-tab-notice" tabindex="0" hidden="">
                <div class="hhf-panorama-detail hhf-panorama-notice"><p class="hhf-eyebrow" data-i18n="panoramaNotice">Tájékoztató</p><h3 data-i18n="panoramaNoticeTitle">Fontos információk</h3><p data-i18n="panoramaNoticeCopy">A látványtervek, videók és bemutatóanyagok tájékoztató jellegűek. Az árak, műszaki tartalom, alapterületek, átadási határidők és felszereltség minden esetben a hivatalos dokumentáció és a szerződés szerint irányadók.</p><ul><li data-i18n="panoramaNoticeVisual">A látványtervek illusztrációk</li><li data-i18n="panoramaNoticeData">Az adatok tájékoztató jellegűek</li><li data-i18n="panoramaNoticeContract">A szerződés az irányadó</li></ul></div>
              </div>
            </div>
            <div class="hhf-panorama-dock">
              <div class="hhf-panorama-tabs" role="tablist" aria-label="Harmat Lakópark bemutató menü" data-i18n-aria-label="panoramaTabs">
                <button type="button" id="hhf-panorama-tab-panorama" data-panorama-tab="panorama" role="tab" aria-controls="hhf-panorama-panel-panorama" aria-selected="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 17v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2"></path><path d="M21 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2"></path><circle cx="12" cy="12" r="1"></circle><path d="M18.944 12.33a1 1 0 0 0 0-.66 7.5 7.5 0 0 0-13.888 0 1 1 0 0 0 0 .66 7.5 7.5 0 0 0 13.888 0"></path></svg><span data-i18n="panoramaTab">Panoráma</span></button>
                <button type="button" id="hhf-panorama-tab-video" data-panorama-tab="video" role="tab" aria-controls="hhf-panorama-panel-video" aria-selected="false" tabindex="-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20.2 6 3 11l-.9-2.4c-.3-1.1.3-2.2 1.3-2.5l13.5-4c1.1-.3 2.2.3 2.5 1.3Z"></path><path d="m6.2 5.3 3.1 3.9"></path><path d="m12.4 3.4 3.1 4"></path><path d="M3 11h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"></path></svg><span data-i18n="panoramaVideos">Videók</span></button>
                <button type="button" id="hhf-panorama-tab-plans" data-panorama-tab="plans" role="tab" aria-controls="hhf-panorama-panel-plans" aria-selected="false" tabindex="-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path><path d="M10 6h4"></path><path d="M10 10h4"></path><path d="M10 14h4"></path><path d="M10 18h4"></path></svg><span data-i18n="panoramaProject">Projekt</span></button>
                <button type="button" id="hhf-panorama-tab-gallery" data-panorama-tab="gallery" role="tab" aria-controls="hhf-panorama-panel-gallery" aria-selected="false" tabindex="-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 22H4a2 2 0 0 1-2-2V6"></path><path d="m22 13-1.296-1.296a2.41 2.41 0 0 0-3.408 0L11 18"></path><circle cx="12" cy="8" r="2"></circle><rect width="16" height="16" x="6" y="2" rx="2"></rect></svg><span data-i18n="navGallery">Galéria</span></button>
                <button type="button" id="hhf-panorama-tab-location" data-panorama-tab="location" role="tab" aria-controls="hhf-panorama-panel-location" aria-selected="false" tabindex="-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg><span data-i18n="panoramaLocation">Környezet</span></button>
                <button type="button" id="hhf-panorama-tab-notice" data-panorama-tab="notice" role="tab" aria-controls="hhf-panorama-panel-notice" aria-selected="false" tabindex="-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg><span data-i18n="panoramaNotice">Tájékoztató</span></button>
              </div>
              <div class="hhf-panorama-controls">
                <button type="button" class="hhf-panorama-tool" data-panorama-rotate="" aria-label="Forgatás" title="Forgatás" aria-pressed="false"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-rotate-icon="" aria-hidden="true" focusable="false"><path d="M16.466 7.5C15.643 4.237 13.952 2 12 2 9.239 2 7 6.477 7 12s2.239 10 5 10c.342 0 .677-.069 1-.2"></path><path d="m15.194 13.707 3.814 1.86-1.86 3.814"></path><path d="M19 15.57c-1.804.885-4.274 1.43-7 1.43-5.523 0-10-2.239-10-5s4.477-5 10-5c4.838 0 8.873 1.718 9.8 4"></path></svg><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-rotate-pause="" aria-hidden="true" hidden="" focusable="false"><rect x="14" y="4" width="4" height="16" rx="1"></rect><rect x="6" y="4" width="4" height="16" rx="1"></rect></svg><span class="hhf-panorama-tooltip">Forgatás</span></button>
                <button type="button" class="hhf-panorama-tool" data-panorama-reset="" aria-label="Alaphelyzet" data-i18n-aria-label="panoramaReset" title="Alaphelyzet" data-i18n-title="panoramaReset"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg><span class="hhf-panorama-tooltip" data-i18n="panoramaReset">Alaphelyzet</span></button>
                <button type="button" class="hhf-panorama-tool" data-panorama-fullscreen="" aria-label="Teljes képernyő" title="Teljes képernyő" aria-pressed="false"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-fullscreen-enter="" aria-hidden="true" focusable="false"><path d="M8 3H5a2 2 0 0 0-2 2v3"></path><path d="M21 8V5a2 2 0 0 0-2-2h-3"></path><path d="M3 16v3a2 2 0 0 0 2 2h3"></path><path d="M16 21h3a2 2 0 0 0 2-2v-3"></path></svg><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-fullscreen-exit="" aria-hidden="true" hidden="" focusable="false"><path d="M8 3v3a2 2 0 0 1-2 2H3"></path><path d="M21 8h-3a2 2 0 0 1-2-2V3"></path><path d="M3 16h3a2 2 0 0 1 2 2v3"></path><path d="M16 21v-3a2 2 0 0 1 2-2h3"></path></svg><span class="hhf-panorama-tooltip">Teljes képernyő</span></button>
              </div>
              <p class="hhf-panorama-action-status" data-panorama-action-status="" role="status" hidden=""></p>
            </div>
            <dialog class="hhf-panorama-lightbox" aria-label="Látványterv nagyítása" data-i18n-aria-label="panoramaZoom">
              <button type="button" class="hhf-panorama-tool" data-panorama-close="" aria-label="Bezárás" data-i18n-aria-label="panoramaClose" title="Bezárás" data-i18n-title="panoramaClose" autofocus=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg><span class="hhf-panorama-tooltip" data-i18n="panoramaClose">Bezárás</span></button>
              <img src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_f.jpg" width="2048" height="2048" alt="A Harmat Lakópark tervezett épületeinek külső látványterve a 360°-os panorámából" data-i18n-alt="panoramaAlt">
              <p class="hhf-panorama-lightbox-caption"></p>
            </dialog>
          </div>
          <figcaption data-i18n="panoramaCaption">A panoráma a tervezett lakópark látványterve, nem az elkészült épületekről készült fénykép.</figcaption>
        </figure>
      </div>
    </section>

    <section class="hhf-section hhf-chooser" id="hhf-lakasvalaszto" aria-labelledby="hhf-chooser-title">
      <div class="hhf-container hhf-chooser-grid">
        <figure><img src="https://harmat22.hu/wp-content/mu-plugins/harmat-contact-family/showroom-model.jpg" width="1000" height="750" alt="A Harmat Lakópark épületmakettje a bemutatóteremben" data-i18n-alt="modelAlt" loading="lazy" decoding="async"><figcaption data-i18n="modelCaption">Helyszíni felvétel · a lakópark makettje a bemutatóteremben</figcaption></figure>
        <div><p class="hhf-eyebrow" data-i18n="chooserEyebrow">Nézze meg más nézőpontból</p><h2 id="hhf-chooser-title" data-i18n="chooserTitle">Melyik épületben lenne az otthona?</h2><p data-i18n="chooserDescription">Fedezze fel az épületek elhelyezkedését és a lakásokat a hivatalos virtuális lakásválasztóban.</p><a class="hhf-button" href="https://harmat22.hu/virtualis-lakasvalaszto/"><span data-i18n="chooserLink">Virtuális lakásválasztó</span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 7h10v10"></path><path d="M7 17 17 7"></path></svg></a></div>
      </div>
    </section>

    <section class="hhf-team hhf-section" aria-labelledby="hhf-team-title">
      <div class="hhf-container hhf-team-grid"><div><p class="hhf-eyebrow" data-i18n="teamEyebrow">A Harmat Lakópark mögött</p><h2 id="hhf-team-title" data-i18n="teamTitle">Emberek, akikkel lehet tervezni.</h2></div><div><p data-i18n="teamDescription">Ismerje meg a projekt mögött álló csapatot. A személyes beszélgetésben az Ön számára fontos kérdésekre is jut idő.</p><a class="hhf-text-link" href="https://harmat22.hu/magunkrol/"><span data-i18n="teamLink">Bemutatkozunk</span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 7h10v10"></path><path d="M7 17 17 7"></path></svg></a></div></div>
    </section>

    <section class="hhf-section hhf-contact" id="hhf-kapcsolat" aria-labelledby="hhf-contact-title">
      <div class="hhf-container hhf-contact-grid">
        <div><p class="hhf-eyebrow" data-i18n="contactEyebrow">Az első lépés egy beszélgetés</p><h2 id="hhf-contact-title" data-i18n="contactTitle">Meséljen az új otthonáról.</h2><p class="hhf-contact-intro" data-i18n="contactDescription">Kérjen tájékoztatást a lakásokról, vagy egyeztessen személyes találkozót értékesítési csapatunkkal.</p>
          <address class="hhf-contact-details">
            <a href="tel:+36300733375"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg><span>+36 30 073 3375</span></a>
            <a href="mailto:ertekesites@harmat22.hu"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg><span>ertekesites@harmat22.hu</span></a>
            <p><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg><span>1105 Budapest, Harmat utca 22.</span></p>
          </address>
          <a class="hhf-button" href="https://harmat22.hu/elerhetosegeink/"><span data-i18n="bookingLink">Kapcsolat és időpont-egyeztetés</span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 7h10v10"></path><path d="M7 17 17 7"></path></svg></a>
        </div>
        <figure><img src="https://harmat22.hu/wp-content/mu-plugins/harmat-contact-family/project-location.jpg" width="1000" height="750" alt="A Harmat utca 22. környéke helyszíni felvételen" data-i18n-alt="locationAlt" loading="lazy" decoding="async"><figcaption data-i18n="locationCaption">Helyszíni felvétel · Harmat utca és környéke</figcaption></figure>
      </div>
    </section>
  </div></main>
HHF_MAIN_HTML;
    return str_replace('__HHF_UNITS_HTML__', $cards, $html);
})(isset($harmat_home_family_units) && is_array($harmat_home_family_units) ? $harmat_home_family_units : array());
