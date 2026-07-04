<?php
/**
 * Plugin Name: Harmat SEO Description Patch
 * Description: Adds clean page-level SEO descriptions for public pages where automatic excerpts can include footer text.
 * Version: 2026.07.04.1
 */

defined('ABSPATH') || exit;

function harmat_seo_description_patch_path() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    return trim((string) parse_url($request_uri, PHP_URL_PATH), '/');
}

function harmat_seo_description_patch_map() {
    return array(
        'adatvedelmi-tajekoztato' => 'Adatkezelési tájékoztató a Harmat Lakópark érdeklődői, ügyfelei és online felhasználói számára, az adatkezelés céljaival és jogaival.',
        'cookie-tajekoztato' => 'Süti tájékoztató a Harmat Lakópark weboldalán használt szükséges, statisztikai és marketing célú cookie-król, valamint a hozzájárulás kezeléséről.',
        'felhasznalasi-feltetelek' => 'A Harmat Lakópark weboldal használatának feltételei, tájékoztató jellegű tartalmai, szerzői jogi és felelősségi szabályai.',
        'harmat-lakopark' => 'Ismerje meg a Harmat Lakóparkot Budapest X. kerületében: új építésű lakások, zöld környezet, mélygarázs, tárolók és családbarát otthonok.',
        'impresszum' => 'A Harmat Lakópark hivatalos impresszuma: üzemeltetői, elérhetőségi és jogi adatok a harmat22.hu weboldalhoz.',
        'panaszkezeles' => 'Panaszkezelési tájékoztató a Harmat Lakópark érdeklődői és ügyfelei számára: ügyintézés, elérhetőségek és válaszadási alapelvek.',
        'szolgaltatasaink' => 'Harmat Lakópark szolgáltatások: mélygarázs, tárolók, lifttel megközelíthető lakások, energiatudatos műszaki megoldások és kényelmes otthonok.',
        'virtualis-lakasvalaszto-a1-epulet' => 'Válasszon lakást az A1 épületben: emeletek, alaprajzok, elérhető lakások és ajánlatkérés a Harmat Lakópark első ütemében.',
        'virtualis-lakasvalaszto-a2-epulet' => 'Válasszon lakást az A2 épületben: emeletek, alaprajzok, elérhető lakások és ajánlatkérés a Harmat Lakópark első ütemében.',
        'virtualis-lakasvalaszto-a3-epulet' => 'Válasszon lakást az A3 épületben: emeletek, alaprajzok, elérhető lakások és ajánlatkérés a Harmat Lakópark első ütemében.',
        'virtualis-lakasvalaszto-a4-epulet' => 'Válasszon lakást az A4 épületben: emeletek, alaprajzok, elérhető lakások és ajánlatkérés a Harmat Lakópark első ütemében.',
        'virtualis-lakasvalaszto-elso-utem' => 'Tekintse át a Harmat Lakópark első ütemének épületeit és elérhető lakásait az interaktív virtuális lakásválasztóban.',
    );
}

function harmat_seo_description_patch_current() {
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return '';
    }

    $path = harmat_seo_description_patch_path();
    $descriptions = harmat_seo_description_patch_map();

    return isset($descriptions[$path]) ? $descriptions[$path] : '';
}

function harmat_seo_description_patch_filter($description) {
    $patched = harmat_seo_description_patch_current();
    return $patched !== '' ? $patched : $description;
}
add_filter('wpseo_metadesc', 'harmat_seo_description_patch_filter', 2000);
add_filter('wpseo_opengraph_desc', 'harmat_seo_description_patch_filter', 2000);
add_filter('wpseo_twitter_description', 'harmat_seo_description_patch_filter', 2000);

add_filter('wpseo_schema_webpage', function ($data) {
    $patched = harmat_seo_description_patch_current();
    if ($patched !== '' && is_array($data)) {
        $data['description'] = $patched;
    }

    return $data;
}, 2000);
