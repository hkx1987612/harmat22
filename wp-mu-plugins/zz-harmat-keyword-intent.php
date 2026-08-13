<?php
/**
 * Plugin Name: Harmat Keyword Intent
 * Description: Maps core Hungarian search intents to distinct public pages with concise, factual copy.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function harmat_keyword_request_path(): string
{
    $path = isset($_SERVER['REQUEST_URI'])
        ? (string) wp_parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH)
        : '';

    return trim($path, '/');
}

function harmat_keyword_page_key(): string
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request() || is_feed() || is_search() || is_404()) {
        return '';
    }

    $path = harmat_keyword_request_path();

    if (is_front_page() || $path === '') {
        return 'home';
    }
    if (is_page('harmat-lakopark') || $path === 'harmat-lakopark') {
        return 'project';
    }
    if (is_page('lakaskereso') || $path === 'lakaskereso') {
        return 'search';
    }
    if (is_page('harmat-lakopark-kornyeke') || $path === 'harmat-lakopark-kornyeke') {
        return 'neighborhood';
    }
    if (is_page('finanszirozas') || $path === 'finanszirozas') {
        return 'financing';
    }

    return '';
}

function harmat_keyword_seo_map(): array
{
    return array(
        'home' => array(
            'title' => 'Harmat Lakópark | Új építésű lakások Budapest X. kerületében',
            'description' => 'Harmat Lakópark: új építésű lakások Kőbányán, Budapest X. kerületében, a Harmat utca 22. alatt. Lakáskereső, alaprajzok és ajánlatkérés.',
        ),
        'project' => array(
            'title' => 'Új építésű lakópark Kőbányán | Harmat Lakópark',
            'description' => 'Ismerje meg a Harmat Lakópark új építésű otthonait Kőbányán: 124 lakás az első ütemben, zöld környezet, mélygarázs és tárolók.',
        ),
        'search' => array(
            'title' => 'Eladó új építésű lakások Kőbányán | Harmat Lakópark',
            'description' => 'Eladó új építésű lakások Kőbányán, Budapest X. kerületében. Szűrés épület, emelet, szobaszám, alapterület, terasz vagy kert szerint.',
        ),
        'neighborhood' => array(
            'title' => 'Kőbánya és Óhegy környéke | Harmat Lakópark',
            'description' => 'A Harmat utca 22. kőbányai környezete: Óhegy park, kutyafuttató, iskolák, bevásárlás és közlekedési kapcsolatok a közelben.',
        ),
        'financing' => array(
            'title' => 'Új építésű lakás finanszírozása | Harmat Lakópark',
            'description' => 'Tájékoztató új építésű lakás finanszírozásáról, banki hitelről, CSOK lehetőségről és fizetési ütemezésről a Harmat Lakóparkban.',
        ),
    );
}

function harmat_keyword_seo_value(string $field, string $fallback): string
{
    $key = harmat_keyword_page_key();
    $map = harmat_keyword_seo_map();

    return $key !== '' && !empty($map[$key][$field]) ? $map[$key][$field] : $fallback;
}

function harmat_keyword_title(string $title): string
{
    return harmat_keyword_seo_value('title', $title);
}

function harmat_keyword_description(string $description): string
{
    return harmat_keyword_seo_value('description', $description);
}

add_filter('wpseo_title', 'harmat_keyword_title', 2000);
add_filter('wpseo_opengraph_title', 'harmat_keyword_title', 2000);
add_filter('wpseo_twitter_title', 'harmat_keyword_title', 2000);
add_filter('pre_get_document_title', 'harmat_keyword_title', 2000);
add_filter('wpseo_metadesc', 'harmat_keyword_description', 2000);
add_filter('wpseo_opengraph_desc', 'harmat_keyword_description', 2000);
add_filter('wpseo_twitter_description', 'harmat_keyword_description', 2000);

function harmat_keyword_json_text(string $text): string
{
    $encoded = wp_json_encode($text);

    return is_string($encoded) ? trim($encoded, '"') : $text;
}

function harmat_keyword_replace_text(string $html, string $old, string $new): string
{
    return str_replace(
        array($old, harmat_keyword_json_text($old)),
        array($new, harmat_keyword_json_text($new)),
        $html
    );
}

function harmat_keyword_search_intro(): string
{
    return '<p class="harmat-keyword-intro" data-harmat-keyword-intro="search">'
        . 'Válogasson az eladó új építésű lakások között Kőbányán, Budapest X. kerületében. '
        . 'Szűrjön épület, emelet, szobaszám, alapterület, terasz vagy kert szerint; a '
        . '<a href="' . esc_url(home_url('/finanszirozas/')) . '">finanszírozási tájékoztató</a> külön oldalon érhető el.'
        . '</p>';
}

function harmat_keyword_neighborhood_section(): string
{
    return '<section class="harmat-keyword-location" data-harmat-keyword-intro="neighborhood" aria-labelledby="harmat-keyword-location-title">'
        . '<div class="harmat-keyword-location-inner">'
        . '<p class="harmat-keyword-eyebrow">1105 Budapest, Harmat utca 22.</p>'
        . '<h2 id="harmat-keyword-location-title">Új otthon Kőbányán, az Óhegy park közelében</h2>'
        . '<p>A Harmat Lakópark Budapest X. kerületében, Kőbányán kínál új építésű lakásokat. '
        . 'A Harmat utca 22. környezetében zöldterületek, oktatási intézmények, bevásárlási lehetőségek és városi közlekedési kapcsolatok érhetők el.</p>'
        . '<p>Az Óhegy park megközelítőleg 600 méterre, a közeli kutyafuttató körülbelül 200 méterre található. '
        . 'A távolságok tájékoztató jellegű, közelítő értékek; a tényleges útvonal és menetidő eltérhet.</p>'
        . '<nav aria-label="Kapcsolódó oldalak">'
        . '<a href="' . esc_url(home_url('/lakaskereso/')) . '">Elérhető lakások</a>'
        . '<a href="' . esc_url(home_url('/harmat-lakopark/')) . '">A lakópark bemutatása</a>'
        . '<a href="' . esc_url(home_url('/elerhetosegeink/')) . '">Kapcsolat</a>'
        . '</nav>'
        . '</div>'
        . '</section>';
}

function harmat_keyword_filter_html(string $html): string
{
    if ($html === '') {
        return $html;
    }

    $key = harmat_keyword_page_key();

    if ($key === 'home') {
        $old = 'A zárt, parkosított lakópark kényelmes, alacsony energiaigényű otthonokat kínál hőszivattyús fűtés-hűtéssel, átgondolt alaprajzokkal és mindennapi szolgáltatásokhoz közeli elhelyezkedéssel.';
        $new = 'A Harmat Lakópark Budapest X. kerületében, Kőbányán kínál kényelmes, alacsony energiaigényű otthonokat hőszivattyús fűtés-hűtéssel, átgondolt alaprajzokkal és mindennapi szolgáltatásokhoz közeli elhelyezkedéssel.';

        return harmat_keyword_replace_text($html, $old, $new);
    }

    if ($key === 'project') {
        $html = harmat_keyword_replace_text(
            $html,
            'Modern új építésű otthonok a Harmat utca 22. alatt, zöldebb lakókörnyezettel, átgondolt alaprajzokkal és átlátható értékesítési adatokkal.',
            'Új építésű lakópark Kőbányán, Budapest X. kerületében, a Harmat utca 22. alatt, zöldebb lakókörnyezettel, átgondolt alaprajzokkal és átlátható értékesítési adatokkal.'
        );

        return harmat_keyword_replace_text(
            $html,
            'A Harmat Lakópark több ütemben megvalósuló lakóprojekt. Az első ütem 124 lakással indul, a teljes fejlesztés tervezetten 398 lakást foglal magában. A cél egy letisztult, könnyen fenntartható, jól használható otthonokat kínáló lakópark Budapest X. kerületében.',
            'A Harmat Lakópark Kőbányán, Budapest X. kerületében több ütemben megvalósuló lakóprojekt. Az első ütem 124 lakással indul, a teljes fejlesztés tervezetten 398 lakást foglal magában. A cél egy letisztult, könnyen fenntartható, jól használható otthonokat kínáló városi lakópark.'
        );
    }

    if ($key === 'search' && strpos($html, 'data-harmat-keyword-intro="search"') === false) {
        return preg_replace(
            '/(<div class="hm-lakas-toolbar"(?=\s|>))/',
            harmat_keyword_search_intro() . '$1',
            $html,
            1
        ) ?: $html;
    }

    if ($key === 'neighborhood' && strpos($html, 'data-harmat-keyword-intro="neighborhood"') === false) {
        $section = harmat_keyword_neighborhood_section();
        $footer_position = stripos($html, '<footer');

        if ($footer_position !== false) {
            return substr($html, 0, $footer_position) . $section . substr($html, $footer_position);
        }
    }

    if ($key === 'financing') {
        return harmat_keyword_replace_text(
            $html,
            'A Harmat Lakópark értékesítési csapata a kiválasztott lakáshoz kapcsolódó fizetési ütemezésről, banki finanszírozásról és támogatási kérdésekről tájékoztató jelleggel segít.',
            'Az új építésű lakás finanszírozása minden vásárlónál egyedi. A Harmat Lakópark értékesítési csapata a kiválasztott otthon fizetési ütemezéséről, banki finanszírozásáról és támogatási lehetőségeiről tájékoztató jelleggel segít.'
        );
    }

    return $html;
}

add_action('template_redirect', function (): void {
    if (harmat_keyword_page_key() === '') {
        return;
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, array('GET', 'HEAD'), true)) {
        return;
    }

    ob_start('harmat_keyword_filter_html');
}, -2000);

add_action('wp_head', function (): void {
    $key = harmat_keyword_page_key();
    if (!in_array($key, array('search', 'neighborhood'), true)) {
        return;
    }
    ?>
<style id="harmat-keyword-intent-style">
.harmat-keyword-intro{margin:-4px 0 22px;padding:0 2px;color:#566167;font-family:Montserrat,Arial,sans-serif;font-size:14px;line-height:1.7}
.harmat-keyword-intro a{color:#8c621f;font-weight:800;text-decoration:underline;text-underline-offset:3px}
.harmat-keyword-location{width:100%;border-top:1px solid rgba(152,112,51,.24);border-bottom:1px solid rgba(152,112,51,.18);background:#fff}
.harmat-keyword-location-inner{width:min(1180px,calc(100% - 40px));margin:0 auto;padding:44px 0 48px;color:#263135;font-family:Montserrat,Arial,sans-serif}
.harmat-keyword-eyebrow{margin:0 0 10px;color:#987033;font-size:12px;font-weight:900;text-transform:uppercase}
.harmat-keyword-location h2{max-width:820px;margin:0 0 18px;color:#263135;font-family:Marcellus,Georgia,serif;font-size:32px;font-weight:500;line-height:1.2}
.harmat-keyword-location p:not(.harmat-keyword-eyebrow){max-width:920px;margin:0 0 12px;color:#566167;font-size:15px;line-height:1.75}
.harmat-keyword-location nav{display:flex;flex-wrap:wrap;gap:18px;margin-top:22px}
.harmat-keyword-location nav a{color:#8c621f;font-size:13px;font-weight:900;text-decoration:underline;text-underline-offset:4px}
@media(max-width:680px){.harmat-keyword-intro{margin-bottom:18px;font-size:13.5px}.harmat-keyword-location-inner{width:calc(100% - 32px);padding:34px 0 38px}.harmat-keyword-location h2{font-size:25px}.harmat-keyword-location p:not(.harmat-keyword-eyebrow){font-size:14px;line-height:1.7}.harmat-keyword-location nav{display:grid;gap:12px}}
</style>
    <?php
}, 90);
