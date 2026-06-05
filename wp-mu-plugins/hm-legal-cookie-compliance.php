<?php
/**
 * Plugin Name: Harmat Legal Pages and Cookie Consent
 * Description: Adds Hungarian legal page content and a consent-based cookie banner.
 * Version: 2026.06.05
 */

defined('ABSPATH') || exit;

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    ob_start(function ($html) {
        $legal_links = '<a href="/felhasznalasi-feltetelek/">Felhasználási feltételek</a> · <a href="/adatvedelmi-tajekoztato/">Adatvédelmi tájékoztató</a> · <a href="/cookie-tajekoztato/">Süti tájékoztató</a> · <a href="/panaszkezeles/">Panaszkezelés</a> · <a href="/impresszum/">Impresszum</a>';

        $html = str_replace('<a href="#">Terms of use</a> and <a href="#">Adatv?delmi t?j?koztat?</a>', $legal_links, $html);
        $html = str_replace('Terms of use and Adatv?delmi t?j?koztat?', 'Felhasználási feltételek és Adatvédelmi tájékoztató', $html);
        $html = str_replace('Adatv?delmi t?j?koztat?', 'Adatvédelmi tájékoztató', $html);
        $html = str_replace('Adatv?delmi', 'Adatvédelmi', $html);
        $html = str_replace('t?j?koztat?', 'tájékoztató', $html);
        $html = str_replace('Terms of use', 'Felhasználási feltételek', $html);
        $html = preg_replace(
            '~Elfogadom az\s*<a([^>]*)>\s*Adatvédelmi tájékoztatót\s*</a>\s*,?\s*és hozzájárulok ahhoz, hogy a megadott adataimat az érdeklődés megválaszolása céljából kezeljék\.?~u',
            'Tudomásul veszem az <a$1>Adatvédelmi tájékoztatóban</a> foglaltakat. Az űrlap elküldésével kérem, hogy a Cooperation Power Kft. az érdeklődésemet megválaszolja, és ennek érdekében a megadott adataimat kezelje.',
            $html
        );

        return $html;
    });
}, 0);

function hm_legal_company_20260514() {
    return array(
        'name'      => 'Cooperation Power Kft.',
        'seat'      => '1107 Budapest, Mázsa utca 13. 6. em. 603. ajtó, Magyarország',
        'project'   => '1105 Budapest, Harmat utca 22.',
        'tax'       => '26203519-2-42',
        'reg'       => '01-09-307619',
        'email'         => 'ertekesites@harmat22.hu',
        'privacy_email' => 'adatvedelem@harmat22.hu',
        'phone'         => '+36-30-641-03-58',
        'website'       => 'https://harmat22.hu/',
        'authority'     => 'Fővárosi Törvényszék Cégbírósága',
    );
}

function hm_legal_policy_version_20260601() {
    return '2026-06-05-v1.4';
}

function hm_legal_google_tag_id_20260601() {
    $settings = get_option('googlesitekit_analytics-4_settings', array());

    if (is_array($settings) && ! empty($settings['googleTagID'])) {
        return (string) $settings['googleTagID'];
    }

    if (is_array($settings) && ! empty($settings['measurementID'])) {
        return (string) $settings['measurementID'];
    }

    return '';
}

function hm_legal_pages_20260514() {
    return array(
        'adatvedelmi-tajekoztato' => array(
            'title'    => 'Adatkezelési tájékoztató',
            'lead'     => 'Átlátható tájékoztatás arról, hogyan kezeljük a Harmat Lakópark weboldalán megadott személyes adatokat.',
            'callback' => 'hm_legal_privacy_20260514',
        ),
        'cookie-tajekoztato' => array(
            'title'    => 'Süti tájékoztató',
            'lead'     => 'Részletes információ a weboldalon használt szükséges, statisztikai és marketing sütikről.',
            'callback' => 'hm_legal_cookie_page_20260514',
        ),
        'felhasznalasi-feltetelek' => array(
            'title'    => 'Felhasználási feltételek',
            'lead'     => 'A harmat22.hu weboldal használatának alapvető feltételei és jogi tudnivalói.',
            'callback' => 'hm_legal_terms_20260514',
        ),
        'impresszum' => array(
            'title'    => 'Impresszum',
            'lead'     => 'A Harmat Lakópark weboldal üzemeltetőjének és szolgáltatójának jogi adatai.',
            'callback' => 'hm_legal_impressum_20260514',
        ),
        'panaszkezeles' => array(
            'title'    => 'Panaszkezelés',
            'lead'     => 'Tájékoztatás a fogyasztói panaszok benyújtásáról, kivizsgálásáról és a rendelkezésre álló jogorvoslati fórumokról.',
            'callback' => 'hm_legal_complaint_20260603',
        ),
        'marketing-hozzajarulas' => array(
            'title'    => 'Marketing hozzájárulás',
            'lead'     => 'Tájékoztató a Harmat Lakóparkhoz kapcsolódó marketing célú kapcsolattartásról.',
            'callback' => 'hm_legal_marketing_20260514',
        ),
    );
}

add_action('init', function () {
    $created_any = false;

    foreach (hm_legal_pages_20260514() as $slug => $page) {
        if (get_page_by_path($slug, OBJECT, 'page')) {
            continue;
        }

        wp_insert_post(array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $page['title'],
            'post_name'    => $slug,
            'post_content' => '[harmat_legal_page]',
        ));
        $created_any = true;
    }

    if ($created_any || get_option('hm_legal_pages_created_20260514') !== 'yes') {
        update_option('hm_legal_pages_created_20260514', 'yes', false);
    }
}, 30);

add_filter('the_content', function ($content) {
    if (is_admin() || ! is_singular('page')) {
        return $content;
    }

    global $post;
    if (! $post instanceof WP_Post) {
        return $content;
    }

    $pages = hm_legal_pages_20260514();
    if (empty($pages[$post->post_name])) {
        return $content;
    }

    $page = $pages[$post->post_name];
    $body = is_callable($page['callback']) ? call_user_func($page['callback']) : '';

    return hm_legal_wrap_20260514($page['title'], $page['lead'], $body);
}, 20);

function hm_legal_wrap_20260514($title, $lead, $body) {
    ob_start();
    ?>
    <section class="hm-legal-page">
        <style>
            .hm-legal-page{max-width:1060px;margin:72px auto;padding:0 24px;color:#30383b;font-family:Montserrat,Arial,sans-serif;line-height:1.72}
            .hm-legal-page h1,.hm-legal-page h2,.hm-legal-page h3{color:#263135;font-family:Marcellus,Georgia,serif;letter-spacing:0}
            .hm-legal-page h1{font-size:clamp(34px,4vw,54px);margin:0 0 18px}
            .hm-legal-page h2{font-size:28px;margin:42px 0 14px;border-top:1px solid rgba(152,112,51,.22);padding-top:28px}
            .hm-legal-page h3{font-size:21px;margin:26px 0 10px}
            .hm-legal-page .lead{max-width:820px;font-size:17px;color:#586064;margin-bottom:28px}
            .hm-legal-page table{width:100%;border-collapse:collapse;margin:18px 0 26px;background:#fffaf0}
            .hm-legal-page th,.hm-legal-page td{border:1px solid rgba(152,112,51,.26);padding:12px 14px;text-align:left;vertical-align:top}
            .hm-legal-page th{color:#8a5a18;text-transform:uppercase;font-size:12px;letter-spacing:.08em;background:#fff3dc}
            .hm-legal-page a{color:#8a5a18;font-weight:700}
            .hm-legal-page ul{padding-left:22px}
            .hm-legal-page .hm-note{background:#fff7e8;border-left:4px solid #987033;padding:14px 16px;margin:18px 0;color:#42494d}
            @media(max-width:700px){.hm-legal-page{margin:42px auto;padding:0 18px}.hm-legal-page table{display:block;overflow-x:auto}.hm-legal-page th,.hm-legal-page td{min-width:190px}}
        </style>
        <h1><?php echo esc_html($title); ?></h1>
        <p class="lead"><?php echo esc_html($lead); ?></p>
        <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <p class="hm-note">Hatályos: 2026. június 5. Verzió: 1.4. A tájékoztatók a weboldal jelenlegi működéséhez készültek; érdemi szolgáltatási vagy adatkezelési változás esetén frissíteni kell őket.</p>
    </section>
    <?php
    return ob_get_clean();
}

function hm_legal_company_table_20260514() {
    $c = hm_legal_company_20260514();
    ob_start();
    ?>
    <table>
        <tbody>
            <tr><th>Adatkezelő / szolgáltató</th><td><?php echo esc_html($c['name']); ?></td></tr>
            <tr><th>Székhely</th><td><?php echo esc_html($c['seat']); ?></td></tr>
            <tr><th>Projekt / értékesítési cím</th><td><?php echo esc_html($c['project']); ?></td></tr>
            <tr><th>Cégjegyzékszám</th><td><?php echo esc_html($c['reg']); ?></td></tr>
            <tr><th>Adószám</th><td><?php echo esc_html($c['tax']); ?></td></tr>
            <tr><th>E-mail</th><td><a href="mailto:<?php echo esc_attr($c['email']); ?>"><?php echo esc_html($c['email']); ?></a></td></tr>
            <tr><th>Adatvédelmi kapcsolat</th><td><a href="mailto:<?php echo esc_attr($c['privacy_email']); ?>"><?php echo esc_html($c['privacy_email']); ?></a></td></tr>
            <tr><th>Telefon</th><td><?php echo esc_html($c['phone']); ?></td></tr>
            <tr><th>Weboldal</th><td><a href="<?php echo esc_url($c['website']); ?>"><?php echo esc_html($c['website']); ?></a></td></tr>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

function hm_legal_privacy_20260514() {
    $c = hm_legal_company_20260514();
    ob_start();
    echo hm_legal_company_table_20260514(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>
    <h2>1. A tájékoztató célja</h2>
    <p>Jelen adatkezelési tájékoztató bemutatja, hogy a Harmat Lakópark weboldal használata, ajánlatkérés, időpontfoglalás, kapcsolatfelvétel, értékesítési érdeklődés, panaszkezelés és a Harmat asszisztens használata során milyen személyes adatokat kezelünk, milyen célból, milyen jogalapon, meddig, és az érintettek milyen jogokat gyakorolhatnak.</p>

    <h2>2. Adatvédelmi tisztviselő</h2>
    <p>Az adatkezelő jelen adatkezelések tekintetében külön adatvédelmi tisztviselőt nem jelölt ki. Adatvédelmi kérdésekben, érintetti joggyakorlás esetén vagy marketing hozzájárulás visszavonásához az <a href="mailto:<?php echo esc_attr($c['privacy_email']); ?>"><?php echo esc_html($c['privacy_email']); ?></a> címen lehet kapcsolatba lépni velünk.</p>

    <h2>3. Kezelt adatok, célok, jogalapok és megőrzési idők</h2>
    <table>
        <thead><tr><th>Cél</th><th>Adatkör</th><th>Jogalap</th><th>Megőrzés</th></tr></thead>
        <tbody>
            <tr><td>Konkrét lakásra vonatkozó ajánlatkérés, visszahívás vagy időpontkérés</td><td>Név, e-mail cím, telefonszám, üzenet, kiválasztott lakás adatai, beküldés időpontja</td><td>GDPR 6. cikk (1) b) pont: szerződéskötést megelőző lépések megtétele az érintett kérésére</td><td>Az utolsó érdemi kapcsolatfelvételtől számított legfeljebb 24 hónap, vagy jogvita esetén az igényérvényesítés elévülési idejéig</td></tr>
            <tr><td>Általános érdeklődés, kapcsolattartás és ügyfél-visszahívás</td><td>Név, e-mail cím, telefonszám, üzenet, beküldés időpontja, az ügyintézéshez szükséges belső megjegyzések</td><td>GDPR 6. cikk (1) f) pont: jogos érdek az érdeklődések megválaszolása és az ügyintézés igazolása érdekében</td><td>Az utolsó érdemi kapcsolatfelvételtől számított legfeljebb 24 hónap, vagy tiltakozás/törlési kérés esetén a kérelem elbírálásáig</td></tr>
            <tr><td>Időpontfoglalás és értékesítési egyeztetés</td><td>Név, e-mail cím, telefonszám, dátum, idősáv, lakásazonosító, üzenet</td><td>GDPR 6. cikk (1) b) pont</td><td>Az egyeztetés lezárásától számított legfeljebb 24 hónap</td></tr>
            <tr><td>Szerződés-előkészítés és értékesítési adminisztráció</td><td>Kapcsolattartási adatok, kiválasztott ingatlan, ajánlat és kapcsolódó dokumentumok</td><td>GDPR 6. cikk (1) b) pont; létrejött szerződés esetén 6. cikk (1) c) pont jogi kötelezettségek teljesítése</td><td>Szerződéses vagy számviteli kötelezettség esetén a jogszabályban előírt ideig, jellemzően legfeljebb 8 évig</td></tr>
            <tr><td>Ügyfél-, értékesítési és ügyvédi portálok, dokumentumkezelés</td><td>CRM azonosító, ügyállapot, lakásazonosító, vételár- és fizetési státusz, feltöltött ügyfél- vagy ügyvédi dokumentumok, hozzáférési jogosultságok és belső megjegyzések</td><td>GDPR 6. cikk (1) b) pont: szerződés előkészítése vagy teljesítése; 6. cikk (1) c) pont: jogi kötelezettség; 6. cikk (1) f) pont: jogos érdek a jogosultságkezelt ügyintézés és igénykezelés érdekében</td><td>Az ügy lezárásáig, illetve létrejött szerződés, számviteli vagy jogi kötelezettség esetén a jogszabályban előírt ideig; hozzáférési naplók és technikai adatok általában rövidebb, biztonsági célhoz igazodó időtartamig</td></tr>
            <tr><td>Marketing célú megkeresés külön hozzájárulás esetén</td><td>Név, e-mail cím, telefonszám, hozzájárulás ténye és időpontja</td><td>GDPR 6. cikk (1) a) pont: hozzájárulás</td><td>A hozzájárulás visszavonásáig, de inaktív kapcsolat esetén legfeljebb 24 hónapig</td></tr>
            <tr><td>Harmat asszisztens használata és minőségbiztosítása</td><td>A látogató által beírt kérdés, a válasz technikai adatai, időpont, esetlegesen megadott lakás- vagy kapcsolattartási adat</td><td>GDPR 6. cikk (1) f) pont: jogos érdek a látogatói kérdések megválaszolása és a szolgáltatás fejlesztése érdekében; ajánlatkérés esetén 6. cikk (1) b) pont</td><td>Rövid technikai időtartam, illetve értékesítési ügyként kezelt megkeresés esetén legfeljebb 24 hónap</td></tr>
            <tr><td>Fogyasztói panaszok kezelése</td><td>Név, elérhetőség, panasz tartalma, kapcsolódó iratok, válasz és ügyintézési adatok</td><td>GDPR 6. cikk (1) c) pont: jogi kötelezettség teljesítése; kiegészítőleg 6. cikk (1) f) pont: jogi igények kezelése</td><td>A panaszról felvett jegyzőkönyv és válasz a vonatkozó fogyasztóvédelmi szabályok szerint, jellemzően 3 évig</td></tr>
            <tr><td>Weboldal biztonsága, naplózás, visszaélés-megelőzés</td><td>IP-cím, böngészőadatok, eszközadatok, látogatás időpontja, technikai naplók</td><td>GDPR 6. cikk (1) f) pont: a weboldal biztonságos működtetéséhez fűződő jogos érdek</td><td>Általában 30-90 nap, biztonsági esemény esetén a kivizsgálás lezárásáig</td></tr>
            <tr><td>Statisztikai és marketing sütik</td><td>Online azonosítók, eszköz- és használati adatok, hozzájárulási beállítások</td><td>GDPR 6. cikk (1) a) pont: hozzájárulás</td><td>A süti típusától függően; részletek a Süti tájékoztatóban</td></tr>
        </tbody>
    </table>

    <h2>4. Címzettek és adatfeldolgozók</h2>
    <p>Az adatokhoz kizárólag az arra jogosult munkatársak és közreműködők férhetnek hozzá. Az aktuálisan használt fő címzettek és adatfeldolgozói kategóriák, illetve ismert szolgáltatók:</p>
    <table>
        <thead><tr><th>Kategória / szolgáltató</th><th>Szerep</th><th>Tipikus adat</th></tr></thead>
        <tbody>
            <tr><td>Tárhely.Eu Szolgáltató Kft. / tarhely.com szerverkörnyezet</td><td>A weboldal, adatbázis, fájlok és technikai naplók tárhelyszolgáltatása</td><td>Technikai naplók, űrlapadatok, weboldaladatok</td></tr>
            <tr><td>Weboldal-fejlesztő és karbantartó, például Szabados Attila EV. / 21stcenturywebsites.hu</td><td>Hibajavítás, biztonsági és működési támogatás</td><td>Csak a feladat elvégzéséhez szükséges adatok</td></tr>
            <tr><td>SMTP2GO és a weboldal levelezési rendszere</td><td>Értesítések, visszaigazolások és ügyfélkommunikáció továbbítása</td><td>Név, e-mail cím, üzenettartalom, kézbesítési állapot</td></tr>
            <tr><td>Értékesítési, CRM, ügyfél-, ügynöki és ügyvédi portálok</td><td>Érdeklődések, foglalások, szerződés-előkészítés és jogosultságkezelt dokumentumkezelés</td><td>Kapcsolattartási adatok, lakásazonosító, ügyállapot, szerződés-előkészítési adatok</td></tr>
            <tr><td>Jogi, számviteli és pénzügyi tanácsadók</td><td>Szerződés-előkészítés, jogi kötelezettségek és igénykezelés</td><td>Csak az adott ügy kezeléséhez szükséges adatok</td></tr>
            <tr><td>Google Ireland Limited / Google Analytics, Google Tag Manager, Google Site Kit</td><td>Statisztika és kampánymérés, kizárólag megfelelő hozzájárulás alapján</td><td>Online azonosítók és használati adatok</td></tr>
            <tr><td>Meta Platforms Ireland Ltd. és egyéb hirdetési szolgáltatók, ha marketing kampány vagy pixel aktiválásra kerül</td><td>Remarketing, kampánymérés és közösségi média hirdetések, kizárólag külön hozzájárulás alapján</td><td>Online azonosítók és használati adatok</td></tr>
        </tbody>
    </table>

    <h2>5. EU-n kívüli adattovábbítás</h2>
    <p>Az adatkezelő törekszik arra, hogy személyes adatok az Európai Gazdasági Térségen belül kerüljenek kezelésre. Egyes technikai, analitikai, e-mail-kézbesítési vagy marketing szolgáltatók - különösen Google, SMTP2GO vagy hirdetési szolgáltatók - esetében előfordulhat EU-n kívüli adattovábbítás vagy EU-n kívüli hozzáférés. Ilyen esetben az adattovábbítás megfelelő GDPR szerinti garanciák, például EU megfelelőségi határozat, EU-USA Data Privacy Framework szerinti tanúsítás, általános szerződési feltételek vagy más alkalmazható garancia mellett történhet.</p>
    <p>Google Analytics 4 használata esetén a Google tájékoztatása szerint az EU-ból érkező mérési adatok gyűjtése EU-alapú infrastruktúrán keresztül történik, ugyanakkor a szolgáltatás igénybevétele továbbra is külső szolgáltató bevonásának minősül, ezért a süti hozzájárulás és a tájékoztatás szükséges.</p>

    <h2>6. Érintetti jogok</h2>
    <p>Az érintett jogosult hozzáférést kérni a személyes adataihoz, kérheti azok helyesbítését, törlését, kezelésük korlátozását, tiltakozhat a jogos érdeken alapuló adatkezelés és a közvetlen üzletszerzés ellen, és hozzájáruláson vagy szerződésen alapuló automatizált adatkezelés esetén kérheti az adathordozhatóságot. Hozzájárulás bármikor visszavonható; ez nem érinti a visszavonás előtti adatkezelés jogszerűségét. A kérelmeket az <a href="mailto:<?php echo esc_attr($c['privacy_email']); ?>"><?php echo esc_html($c['privacy_email']); ?></a> címen lehet benyújtani. A kérelmekre főszabály szerint egy hónapon belül válaszolunk.</p>

    <h2>7. Panasz és jogorvoslat</h2>
    <p>Adatvédelmi panasz esetén először kérjük, keressen minket az <a href="mailto:<?php echo esc_attr($c['privacy_email']); ?>"><?php echo esc_html($c['privacy_email']); ?></a> címen. Az érintett panaszt tehet a Nemzeti Adatvédelmi és Információszabadság Hatóságnál: 1055 Budapest, Falk Miksa utca 9-11.; postacím: 1363 Budapest, Pf. 9.; web: <a href="https://www.naih.hu/" target="_blank" rel="noopener">www.naih.hu</a>; e-mail: ugyfelszolgalat@naih.hu. Az érintett bírósághoz is fordulhat. Fogyasztói panaszokra külön <a href="<?php echo esc_url(home_url('/panaszkezeles/')); ?>">Panaszkezelési tájékoztató</a> vonatkozik.</p>

    <h2>8. Automatizált döntéshozatal</h2>
    <p>A Harmat asszisztens automatizált válaszokat adhat a weboldalon elérhető információk alapján, de nem hoz joghatással járó vagy hasonlóan jelentős döntést, nem minősül hivatalos ajánlatnak, jogi vagy pénzügyi tanácsadásnak, és nem dönt lakás foglalásáról, elérhetőségéről vagy szerződéses feltételeiről. A végleges feltételeket minden esetben az értékesítés és az írásbeli szerződéses dokumentumok erősítik meg.</p>

    <h2>9. Jogos érdek és adatbiztonság</h2>
    <p>Jogos érdeken alapuló adatkezelés esetén - például weboldalbiztonság, naplózás, visszaélés-megelőzés, ügyintézés igazolása vagy jogosultságkezelt portálműködés - az adatkezelő a szükséges érdekmérlegelést elvégzi, és annak lényegéről kérésre tájékoztatást ad.</p>
    <p>Az adatbiztonság érdekében a weboldal titkosított HTTPS kapcsolatot, jogosultságkezelt belépést, szerepkör-alapú hozzáférést, védett dokumentumletöltést, technikai naplózást, rendszeres karbantartást és biztonsági mentéseket alkalmaz. Az ügyfél-, értékesítési és ügyvédi dokumentumokhoz csak az arra jogosult felhasználók férhetnek hozzá.</p>
    <?php
    return ob_get_clean();
}

function hm_legal_cookie_page_20260514() {
    ob_start();
    echo hm_legal_company_table_20260514(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>
    <h2>1. Mik azok a sütik?</h2>
    <p>A sütik olyan kis adatfájlok, amelyeket a böngésző tárol a felhasználó eszközén. Segíthetik a weboldal működését, a beállítások megjegyzését, illetve hozzájárulás esetén statisztikai vagy marketing célú mérést.</p>

    <h2>2. Süti kategóriák</h2>
    <table>
        <thead><tr><th>Kategória</th><th>Cél</th><th>Jogalap</th><th>Kezelés</th></tr></thead>
        <tbody>
            <tr><td>Szükséges sütik</td><td>A weboldal biztonságos működése, űrlapok, munkamenet és süti-beállítások megjegyzése</td><td>GDPR 6. cikk (1) f) pont: jogos érdek; a szolgáltatás technikai biztosítása</td><td>Ezek nélkül a weboldal nem működik megfelelően, ezért nem kapcsolhatók ki a weboldalon</td></tr>
            <tr><td>Statisztikai sütik</td><td>Látogatottság és használat mérése, a weboldal fejlesztése</td><td>GDPR 6. cikk (1) a) pont: hozzájárulás</td><td>Csak akkor aktiválódnak, ha a látogató hozzájárul</td></tr>
            <tr><td>Marketing sütik</td><td>Hirdetések, kampánymérés, remarketing</td><td>GDPR 6. cikk (1) a) pont: hozzájárulás</td><td>Csak külön hozzájárulás esetén aktiválódnak</td></tr>
        </tbody>
    </table>

    <h2>3. Jelenleg használt főbb sütik</h2>
    <table>
        <thead><tr><th>Név</th><th>Szolgáltató</th><th>Első / harmadik fél</th><th>Típus</th><th>Cél</th><th>Időtartam / aktiválás</th></tr></thead>
        <tbody>
            <tr><td>harmat_cookie_consent_v1</td><td>Harmat22.hu</td><td>Első fél</td><td>Szükséges</td><td>A süti-hozzájárulási kategóriák, mentés időpontja és tájékoztató verziójának tárolása</td><td>180 nap</td></tr>
            <tr><td>epl_wp_session</td><td>WordPress / ingatlanmodul</td><td>Első fél</td><td>Szükséges</td><td>Ingatlanos keresési és munkamenet-funkciók technikai támogatása</td><td>Jellemzően 12 óra</td></tr>
            <tr><td>wordpress_test_cookie, wordpress_logged_in_*, wordpress_sec_*</td><td>WordPress</td><td>Első fél</td><td>Szükséges</td><td>Adminisztrációs bejelentkezés, biztonság és munkamenet-kezelés</td><td>Munkamenet vagy bejelentkezés időtartama; nyilvános látogatóknál jellemzően nem aktív</td></tr>
            <tr><td>űrlap- és biztonsági munkamenet sütik</td><td>Harmat22.hu / biztonsági bővítmények</td><td>Első fél</td><td>Szükséges</td><td>Űrlapvédelem, visszaélés-megelőzés és a weboldal biztonságos működése</td><td>Munkamenet vagy rövid technikai megőrzés</td></tr>
            <tr><td>_ga, _ga_*, Google tag azonosítók</td><td>Google Analytics / Google Tag Manager</td><td>Google szolgáltatás, a weboldal domainjén tárolt azonosítókkal</td><td>Statisztikai</td><td>Látogatottsági statisztika és weboldalhasználat mérése</td><td>Legfeljebb 2 év; csak a statisztikai sütik elfogadása után töltődik be</td></tr>
            <tr><td>beágyazott térkép-, videó- vagy hirdetési azonosítók</td><td>Google, YouTube, Meta vagy más külső szolgáltató, ha az adott funkció aktív</td><td>Harmadik fél</td><td>Marketing / külső tartalom</td><td>Kampánymérés, remarketing, beágyazott videó vagy térképes tartalom működése</td><td>A szolgáltató beállításai szerint; csak megfelelő hozzájárulás vagy felhasználói művelet esetén</td></tr>
        </tbody>
    </table>
    <p class="hm-note">A sütilista a 2026. június 5-i ellenőrzés szerinti főbb sütiket és szolgáltatásokat tartalmazza. A WordPress-bővítmények és külső beágyazások frissítésekor a lista változhat, ezért rendszeres ellenőrzés szükséges.</p>

    <h2>4. Hozzájárulás kezelése</h2>
    <p>A weboldal saját hozzájárulás-kezelő megoldást használ. A látogató a süti ablakban elfogadhatja az összes sütit, elutasíthatja a nem szükséges sütiket, vagy részletes beállításokat választhat. A hozzájárulás verzióját, kategóriáit és időpontját a böngészőben tároljuk, hogy a weboldal a látogató beállításai szerint működjön. Személyhez kötött, szerveroldali consent logot a weboldal jelenleg nem vezet.</p>
    <p>A hozzájárulás bármikor módosítható vagy visszavonható a weboldal alján megjelenő „Süti beállítások” gombbal. A visszavonás nem érinti a visszavonás előtti adatkezelés jogszerűségét.</p>
    <p>A Google statisztikai címke csak a statisztikai sütik elfogadása után aktiválódik. Hirdetési vagy remarketing célú címkék, ha ilyenek a weboldalon aktívak, kizárólag marketing hozzájárulás után használhatók. A nem szükséges sütik elutasítása nem akadályozza a weboldal alapvető használatát.</p>

    <h2>5. Külső szolgáltatók és harmadik országba történő adattovábbítás</h2>
    <p>Statisztikai vagy marketing sütik elfogadása esetén Google Analytics, Google Tag Manager, Google Site Kit, Google Maps, YouTube, Meta vagy más külső szolgáltatás adatkezelése is érintett lehet, amennyiben az adott funkció aktív. Ezek a szolgáltatók az Európai Gazdasági Térségen kívülre is továbbíthatnak adatot, vagy EU-n kívüli hozzáférést biztosíthatnak. Ilyen esetben az adattovábbítás megfelelő GDPR szerinti garanciák, például EU-USA Data Privacy Framework szerinti tanúsítás, általános szerződési feltételek vagy más alkalmazható garancia alapján történhet.</p>

    <h2>6. Érintetti jogok és panasz</h2>
    <p>A sütikkel kapcsolatos adatkezelésre is irányadók az <a href="<?php echo esc_url(home_url('/adatvedelmi-tajekoztato/')); ?>">Adatkezelési tájékoztatóban</a> szereplő érintetti jogok. Adatvédelmi kérés vagy hozzájárulás-visszavonási kérdés esetén az <a href="mailto:adatvedelem@harmat22.hu">adatvedelem@harmat22.hu</a> címen lehet kapcsolatba lépni velünk. Panasz tehető a Nemzeti Adatvédelmi és Információszabadság Hatóságnál: 1055 Budapest, Falk Miksa utca 9-11.; web: <a href="https://www.naih.hu/" target="_blank" rel="noopener">www.naih.hu</a>; e-mail: ugyfelszolgalat@naih.hu. Az érintett bírósághoz is fordulhat.</p>

    <h2>7. Rendszeres felülvizsgálat</h2>
    <p>A weboldal bővítményei, beágyazott tartalmai és analitikai beállításai változhatnak, ezért a sütilistát rendszeresen ellenőrizni és frissíteni kell. Új statisztikai vagy marketing szolgáltató bekapcsolása előtt ellenőrizni kell, hogy az csak megfelelő hozzájárulás után töltődik-e be.</p>
    <?php
    return ob_get_clean();
}

function hm_legal_terms_20260514() {
    ob_start();
    ?>
    <h2>1. A weboldal célja</h2>
    <p>A harmat22.hu a Harmat Lakópark bemutatását, az elérhető lakások keresését, ajánlatkérések, kapcsolatfelvételek és időpontfoglalások elősegítését szolgálja. A weboldal nem webshop, nem online szerződéskötési felület és nem minősül nyilvános adásvételi ajánlatnak.</p>

    <h2>2. Ingatlanadatok és ajánlatok</h2>
    <p>Az alapterületek, terasz-, erkély- és kertadatok, látványtervek, alaprajzok, műszaki leírások, árak, akciók és elérhetőségi státuszok tájékoztató jellegűek, és változhatnak. A látványtervek illusztrációk; a végleges műszaki tartalom, méret, ár, fizetési ütemezés, kert- vagy egyéb használati feltétel kizárólag az egyedi írásbeli ajánlatban, az adásvételi szerződésben és annak mellékleteiben rögzül.</p>

    <h2>3. Online szerződéskötés kizárása</h2>
    <p>A weboldalon történő ajánlatkérés, időpontfoglalás, chatbot-üzenet vagy űrlapbeküldés önmagában nem minősül szerződéskötésnek, foglalásnak, vételi ajánlat elfogadásának vagy lakás lefoglalásának. A lakás elérhetősége és feltételei minden esetben külön értékesítési egyeztetés, majd írásbeli dokumentum tárgyát képezik.</p>

    <h2>4. Harmat asszisztens</h2>
    <p>A weboldalon működő Harmat asszisztens automatizált, tájékoztató jellegű válaszokat adhat a projekt, a lakáskeresés és az ügyintézés kapcsán. Válaszai nem minősülnek hivatalos ajánlatnak, jogi, pénzügyi vagy műszaki tanácsadásnak. Pontatlan, hiányos vagy elavult válasz előfordulhat; a végleges információkat az értékesítési csapat, illetve a szerződéses dokumentumok erősítik meg.</p>

    <h2>5. Külső hivatkozások és elérhetőség</h2>
    <p>A weboldal külső hivatkozásokat, térképet, beágyazott tartalmat vagy harmadik fél szolgáltatásait tartalmazhatja. Ezek tartalmáért és működéséért a szolgáltató saját feltételei irányadók. A weboldal folyamatos, hibamentes vagy megszakítás nélküli elérhetősége nem garantált; karbantartás vagy technikai hiba átmeneti kiesést okozhat.</p>

    <h2>6. Szellemi tulajdon</h2>
    <p>A weboldalon található szövegek, képek, látványtervek, alaprajzok, logók, adatbázisok és egyéb tartalmak szerzői jogi vagy más védelem alatt állhatnak. A tartalmak előzetes írásbeli engedély nélküli üzleti célú felhasználása, másolása, tömeges letöltése, adatbázisba rendezése, scrapingje vagy terjesztése tilos.</p>

    <h2>7. Felelősség</h2>
    <p>Törekszünk az adatok pontosságára és naprakészségére, de a weboldal tartalmának esetleges hibájáért, elírásáért, elavulásáért vagy átmeneti elérhetetlenségéért a jogszabályok által megengedett mértékben felelősséget nem vállalunk. A felhasználó a weboldal információi alapján meghozott döntések előtt köteles az aktuális, írásbeli értékesítési információkat ellenőrizni.</p>

    <h2>8. Panaszkezelés</h2>
    <p>Fogyasztói panasz esetén a <a href="<?php echo esc_url(home_url('/panaszkezeles/')); ?>">Panaszkezelési tájékoztató</a> tartalmazza a benyújtás, kivizsgálás és jogorvoslat fő szabályait.</p>

    <h2>9. Irányadó jog</h2>
    <p>A weboldal használatára és a kapcsolódó jogviszonyokra Magyarország joga irányadó.</p>
    <?php
    return ob_get_clean();
}

function hm_legal_impressum_20260514() {
    $c = hm_legal_company_20260514();
    ob_start();
    echo hm_legal_company_table_20260514(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>
    <h2>Felelős kiadó</h2>
    <p>A weboldal üzemeltetéséért és tartalmáért a <?php echo esc_html($c['name']); ?> felel.</p>
    <h2>Nyilvántartó hatóság</h2>
    <p><?php echo esc_html($c['authority']); ?></p>
    <h2>Tárhely és technikai üzemeltetés</h2>
    <p>A weboldal működtetéséhez tárhelyszolgáltatót, weboldal-karbantartási szolgáltatót, e-mail kézbesítési és biztonsági szolgáltatásokat veszünk igénybe. Az aktuális adatfeldolgozói kör a szolgáltatások változásával módosulhat; adatvédelmi kérdés esetén tájékoztatást adunk.</p>
    <h2>Kapcsolat</h2>
    <p>Általános és értékesítési megkeresések: <a href="mailto:<?php echo esc_attr($c['email']); ?>"><?php echo esc_html($c['email']); ?></a>, telefon: <?php echo esc_html($c['phone']); ?>.</p>
    <p>Adatvédelmi megkeresések: <a href="mailto:<?php echo esc_attr($c['privacy_email']); ?>"><?php echo esc_html($c['privacy_email']); ?></a>. Fogyasztói panaszok kezeléséről a <a href="<?php echo esc_url(home_url('/panaszkezeles/')); ?>">Panaszkezelési tájékoztató</a> ad részletes információt.</p>
    <?php
    return ob_get_clean();
}

function hm_legal_complaint_20260603() {
    $c = hm_legal_company_20260514();
    ob_start();
    ?>
    <h2>1. Panasz benyújtása</h2>
    <p>A Harmat Lakóparkkal, a weboldalon megjelenő tájékoztatással, az értékesítési kommunikációval vagy az ügyintézéssel kapcsolatos fogyasztói panasz az alábbi elérhetőségeken nyújtható be:</p>
    <table>
        <tbody>
            <tr><th>E-mail</th><td><a href="mailto:<?php echo esc_attr($c['email']); ?>"><?php echo esc_html($c['email']); ?></a></td></tr>
            <tr><th>Postai / személyes cím</th><td><?php echo esc_html($c['project']); ?></td></tr>
            <tr><th>Telefon</th><td><?php echo esc_html($c['phone']); ?></td></tr>
            <tr><th>Szolgáltató</th><td><?php echo esc_html($c['name']); ?>, székhely: <?php echo esc_html($c['seat']); ?></td></tr>
        </tbody>
    </table>

    <h2>2. A panasz kezelése</h2>
    <p>A panaszt kérjük úgy benyújtani, hogy az ügy azonosításához szükséges adatok rendelkezésre álljanak: név, elérhetőség, érintett lakás vagy ügy azonosítója, a panasz rövid leírása, valamint a rendelkezésre álló kapcsolódó dokumentumok.</p>
    <p>Az írásbeli panaszt kivizsgáljuk, és főszabály szerint 30 napon belül írásban válaszolunk. Ha a panasz azonnali kivizsgálása nem lehetséges, vagy a fogyasztó a válasszal nem ért egyet, a panaszról és az álláspontról jegyzőkönyv készülhet. A panaszról készült jegyzőkönyvet és a választ a vonatkozó fogyasztóvédelmi szabályok szerint, jellemzően 3 évig őrizzük meg.</p>

    <h2>3. Békéltető testület</h2>
    <p>Amennyiben a fogyasztói jogvita közvetlenül nem rendezhető, a fogyasztó a lakóhelye vagy tartózkodási helye szerint illetékes békéltető testülethez fordulhat. Budapesti székhelyű ügyekben elérhető:</p>
    <table>
        <tbody>
            <tr><th>Név</th><td>Budapesti Békéltető Testület</td></tr>
            <tr><th>Cím</th><td>1016 Budapest, Krisztina krt. 99. I. em. 111.</td></tr>
            <tr><th>Levelezési cím</th><td>1253 Budapest, Pf. 10.</td></tr>
            <tr><th>E-mail</th><td>bekelteto.testulet@bkik.hu</td></tr>
            <tr><th>Telefon</th><td>+36 (1) 488-2131</td></tr>
            <tr><th>Web</th><td><a href="https://bekeltet.bkik.hu/" target="_blank" rel="noopener">bekeltet.bkik.hu</a></td></tr>
        </tbody>
    </table>

    <h2>4. Fogyasztóvédelmi hatóság és bíróság</h2>
    <p>A fogyasztó a fogyasztóvédelmi hatósági ügyekben az illetékes kormányhivatalhoz, illetve a Nemzeti Kereskedelmi és Fogyasztóvédelmi Hatóság ügyfélszolgálatához is fordulhat. Központi ügyfélszolgálat: <a href="mailto:ugyfelszolgalat@nkfh.gov.hu">ugyfelszolgalat@nkfh.gov.hu</a>, telefon: 06 80 310 020, cím: 1122 Budapest, Városmajor utca 35., web: <a href="https://nkfh.gov.hu/" target="_blank" rel="noopener">nkfh.gov.hu</a>.</p>
    <p>A fogyasztó jogvita esetén bírósághoz is fordulhat. Adatvédelmi panasz esetén az <a href="<?php echo esc_url(home_url('/adatvedelmi-tajekoztato/')); ?>">Adatkezelési tájékoztató</a> szerinti adatvédelmi jogorvoslati lehetőségek irányadók.</p>
    <?php
    return ob_get_clean();
}

function hm_legal_marketing_20260514() {
    $c = hm_legal_company_20260514();
    ob_start();
    ?>
    <h2>1. A hozzájárulás célja</h2>
    <p>Marketing célú hozzájárulás esetén a <?php echo esc_html($c['name']); ?> a Harmat Lakóparkhoz kapcsolódó hírekről, elérhető lakásokról, akciókról, finanszírozási lehetőségekről és értékesítési információkról e-mailben vagy telefonon tájékoztatást küldhet.</p>
    <h2>2. Kezelt adatok</h2>
    <p>Név, e-mail cím, telefonszám, hozzájárulás ténye és időpontja, valamint a kapcsolattartáshoz szükséges technikai adatok.</p>
    <h2>3. Jogalap és visszavonás</h2>
    <p>A marketing célú adatkezelés jogalapja a GDPR 6. cikk (1) a) pont szerinti hozzájárulás. A hozzájárulás bármikor visszavonható az <a href="mailto:<?php echo esc_attr($c['privacy_email']); ?>"><?php echo esc_html($c['privacy_email']); ?></a> vagy az <a href="mailto:<?php echo esc_attr($c['email']); ?>"><?php echo esc_html($c['email']); ?></a> címre küldött üzenettel. Amennyiben egy marketing üzenet közvetlen leiratkozási lehetőséget tartalmaz, az azon keresztül is visszavonható. A visszavonás nem érinti a korábbi adatkezelés jogszerűségét.</p>
    <h2>4. Megőrzési idő</h2>
    <p>A marketing célú adatokat a hozzájárulás visszavonásáig, illetve inaktív kapcsolat esetén legfeljebb 24 hónapig kezeljük.</p>
    <?php
    return ob_get_clean();
}

add_filter('googlesitekit_gtag_opt', function ($gtag_opt) {
    if (! is_array($gtag_opt)) {
        $gtag_opt = array();
    }

    $gtag_opt['send_page_view'] = false;

    return $gtag_opt;
}, 20);

add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }

    wp_dequeue_script('google_gtagjs');
    wp_deregister_script('google_gtagjs');
}, 1000);

add_filter('wp_resource_hints', function ($urls, $relation_type) {
    if ('dns-prefetch' !== $relation_type && 'preconnect' !== $relation_type) {
        return $urls;
    }

    return array_values(array_filter($urls, function ($url) {
        return stripos((string) $url, 'googletagmanager.com') === false;
    }));
}, 100, 2);

add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }
    ?>
    <script type="application/javascript" id="hm-google-consent-default" data-cfasync="false" data-no-optimize="1" data-noptimize="1">
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', {
            analytics_storage: 'denied',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            wait_for_update: 500
        });
    </script>
    <style id="hm-cookie-consent-style">
        #hm-cookie-consent{position:fixed;left:24px;right:24px;bottom:24px;z-index:999999;display:none;justify-content:center;pointer-events:none}
        #hm-cookie-consent.is-visible{display:flex}
        #hm-cookie-consent .hm-cookie-box{width:min(100%,860px);background:#fff;border:1px solid rgba(152,112,51,.34);box-shadow:0 18px 60px rgba(0,0,0,.22);padding:24px;pointer-events:auto;color:#30383b;font-family:Montserrat,Arial,sans-serif}
        #hm-cookie-consent h2{margin:0 0 8px;color:#263135;font-family:Marcellus,Georgia,serif;font-size:28px}
        #hm-cookie-consent p{margin:0 0 14px;line-height:1.6}
        #hm-cookie-consent a{color:#8a5a18;font-weight:700}
        #hm-cookie-consent .hm-cookie-options{display:none;gap:10px;margin:12px 0 16px}
        #hm-cookie-consent.is-settings .hm-cookie-options{display:grid}
        #hm-cookie-consent label{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;background:#fff7e8;border:1px solid rgba(152,112,51,.22)}
        #hm-cookie-consent input{margin-top:4px}
        #hm-cookie-consent .hm-cookie-actions{display:flex;flex-wrap:wrap;gap:10px}
        #hm-cookie-consent button,#hm-cookie-settings-button{border:1px solid #a87027;background:#a87027;color:#fff;min-height:42px;padding:0 16px;font-weight:700;letter-spacing:.04em;cursor:pointer}
        #hm-cookie-consent button.hm-secondary,#hm-cookie-settings-button{background:#fff;color:#8a5a18}
        #hm-cookie-settings-button{position:fixed;left:18px;bottom:18px;z-index:99999;display:none;box-shadow:0 10px 30px rgba(0,0,0,.14)}
        #hm-cookie-settings-button.is-visible{display:block}
        @media(max-width:640px){#hm-cookie-consent{left:10px;right:10px;bottom:10px}#hm-cookie-consent .hm-cookie-box{padding:18px}#hm-cookie-consent .hm-cookie-actions{display:grid}#hm-cookie-consent button{width:100%}}
    </style>
    <?php
}, 1);

add_action('wp_footer', function () {
    if (is_admin()) {
        return;
    }
    ?>
    <div id="hm-cookie-consent" role="dialog" aria-modal="true" aria-labelledby="hm-cookie-title">
        <div class="hm-cookie-box">
            <h2 id="hm-cookie-title">Süti beállítások</h2>
            <p>A weboldal működéséhez szükséges sütiket mindig használjuk. Statisztikai és marketing sütiket csak az Ön hozzájárulása után aktiválunk. Részletek: <a href="/cookie-tajekoztato/">Süti tájékoztató</a> és <a href="/adatvedelmi-tajekoztato/">Adatkezelési tájékoztató</a>.</p>
            <div class="hm-cookie-options">
                <label><input type="checkbox" checked disabled> <span><strong>Szükséges sütik</strong><br>Biztonságos működés, űrlapok és beállítások.</span></label>
                <label><input type="checkbox" id="hm-cookie-analytics"> <span><strong>Statisztikai sütik</strong><br>Látogatottsági és használati mérés.</span></label>
                <label><input type="checkbox" id="hm-cookie-marketing"> <span><strong>Marketing sütik</strong><br>Hirdetési és kampánymérési célok.</span></label>
            </div>
            <div class="hm-cookie-actions">
                <button type="button" data-hm-cookie-accept>Összes elfogadása</button>
                <button type="button" class="hm-secondary" data-hm-cookie-necessary>Csak szükséges sütik</button>
                <button type="button" class="hm-secondary" data-hm-cookie-settings>Beállítások</button>
                <button type="button" data-hm-cookie-save style="display:none">Mentés</button>
            </div>
        </div>
    </div>
    <button type="button" id="hm-cookie-settings-button">Süti beállítások</button>
    <script type="application/javascript" id="hm-cookie-consent-script" data-cfasync="false" data-no-optimize="1" data-noptimize="1">
        (function(){
            var name = 'harmat_cookie_consent_v1';
            var box = document.getElementById('hm-cookie-consent');
            var settingsBtn = document.getElementById('hm-cookie-settings-button');
            var analytics = document.getElementById('hm-cookie-analytics');
            var marketing = document.getElementById('hm-cookie-marketing');
            var saveBtn = box.querySelector('[data-hm-cookie-save]');
            var settingsToggle = box.querySelector('[data-hm-cookie-settings]');
            var policyVersion = <?php echo wp_json_encode(hm_legal_policy_version_20260601()); ?>;
            var googleTagId = <?php echo wp_json_encode(hm_legal_google_tag_id_20260601()); ?>;
            var googleTagLoaded = false;

            function readConsent(){
                var found = document.cookie.split('; ').find(function(row){ return row.indexOf(name + '=') === 0; });
                if (!found) return null;
                try { return JSON.parse(decodeURIComponent(found.split('=').slice(1).join('='))); } catch(e){ return null; }
            }
            function isCurrentConsent(value){
                return !!(value && value.policyVersion === policyVersion);
            }
            function loadGoogleTag(value){
                if (!value || !value.analytics || !googleTagId || googleTagLoaded) return;
                googleTagLoaded = true;
                window.dataLayer = window.dataLayer || [];
                window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };
                var existing = document.querySelector('script[src*="googletagmanager.com/gtag/js"]');
                if (!existing) {
                    var script = document.createElement('script');
                    script.async = true;
                    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(googleTagId);
                    script.setAttribute('data-hm-google-tag', 'consent-loaded');
                    document.head.appendChild(script);
                }
                window.gtag('js', new Date());
                window.gtag('config', googleTagId, { send_page_view: true });
            }
            function writeConsent(value){
                value.necessary = true;
                value.policyVersion = policyVersion;
                value.savedAt = new Date().toISOString();
                document.cookie = name + '=' + encodeURIComponent(JSON.stringify(value)) + '; Max-Age=' + (180 * 24 * 60 * 60) + '; Path=/; SameSite=Lax';
                updateConsent(value);
            }
            function updateConsent(value){
                window.dataLayer = window.dataLayer || [];
                window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };
                window.gtag('consent', 'update', {
                    analytics_storage: value.analytics ? 'granted' : 'denied',
                    ad_storage: value.marketing ? 'granted' : 'denied',
                    ad_user_data: value.marketing ? 'granted' : 'denied',
                    ad_personalization: value.marketing ? 'granted' : 'denied'
                });
                loadGoogleTag(value);
            }
            function show(settings){
                var current = readConsent();
                current = isCurrentConsent(current) ? current : null;
                analytics.checked = !!(current && current.analytics);
                marketing.checked = !!(current && current.marketing);
                box.classList.toggle('is-settings', !!settings);
                saveBtn.style.display = settings ? '' : 'none';
                box.classList.add('is-visible');
                settingsBtn.classList.remove('is-visible');
            }
            function hide(){
                box.classList.remove('is-visible');
                settingsBtn.classList.add('is-visible');
            }

            var current = readConsent();
            if (isCurrentConsent(current)) {
                updateConsent(current);
                settingsBtn.classList.add('is-visible');
            } else {
                setTimeout(function(){ show(false); }, 0);
                window.addEventListener('scroll', function onScroll(){
                    if (window.scrollY > 160) {
                        window.removeEventListener('scroll', onScroll);
                        if (!readConsent()) show(false);
                    }
                }, {passive:true});
            }

            box.querySelector('[data-hm-cookie-accept]').addEventListener('click', function(){ writeConsent({necessary:true, analytics:true, marketing:true}); hide(); });
            box.querySelector('[data-hm-cookie-necessary]').addEventListener('click', function(){ writeConsent({necessary:true, analytics:false, marketing:false}); hide(); });
            settingsToggle.addEventListener('click', function(){ show(true); });
            saveBtn.addEventListener('click', function(){ writeConsent({necessary:true, analytics:analytics.checked, marketing:marketing.checked}); hide(); });
            settingsBtn.addEventListener('click', function(){ show(true); });
        })();
    </script>
    <?php
}, 100);
