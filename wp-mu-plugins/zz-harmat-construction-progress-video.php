<?php
/**
 * Plugin Name: Harmat Construction Progress Media
 * Description: Adds construction videos and a photo timeline to the public construction-log page.
 * Version: 1.3.0
 */

defined('ABSPATH') || exit;

const HARMAT_CONSTRUCTION_VIDEO_ID = 'HMgnTfeuQYM';
const HARMAT_CONSTRUCTION_VIDEO_DATE = '2026-08-28';
const HARMAT_CONSTRUCTION_VIDEO_DURATION = 'PT1M31S';

function harmat_construction_video_is_page(): bool
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request() || is_feed() || is_robots()) {
        return false;
    }

    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    return trim((string) $path, '/') === 'epitesi-naplo';
}

function harmat_construction_video_page_url(): string
{
    return home_url('/epitesi-naplo/');
}

function harmat_construction_video_poster_url(): string
{
    return content_url('/uploads/2026/08/harmat-epitesi-naplo-2026-08.jpg');
}

function harmat_construction_video_watch_url(): string
{
    return 'https://www.youtube.com/watch?v=' . rawurlencode(HARMAT_CONSTRUCTION_VIDEO_ID);
}

function harmat_construction_gallery_base_url(): string
{
    return content_url('/uploads/2026/08/construction-progress/');
}

function harmat_construction_gallery_groups(): array
{
    return array(
        array(
            'date' => '2026-08',
            'label' => '2026. augusztus',
            'title' => 'Tömörített zúzottkő ágyazat',
            'description' => 'Az alapozási munkákhoz szükséges teherbíró réteg kialakítása, terítése és tömörítése került előtérbe.',
            'images' => array(
                array('date' => '2026-08-26', 'date_label' => '2026. augusztus 26.', 'slug' => '2026-08-26-tomoritett-agyazat', 'caption' => 'A tömörített ágyazat aktuális állapota', 'alt' => 'Tömörített zúzottkő ágyazat a Harmat Lakópark munkaterületén'),
                array('date' => '2026-08-25', 'date_label' => '2026. augusztus 25.', 'slug' => '2026-08-25-helyszini-szemle', 'caption' => 'Helyszíni szemle a zúzottkő terítése után', 'alt' => 'Helyszíni szemle a Harmat Lakópark zúzottkővel előkészített munkagödrében'),
                array('date' => '2026-08-17', 'date_label' => '2026. augusztus 17.', 'slug' => '2026-08-17-zuzottko-agyazat', 'caption' => 'A zúzottkő ágyazat kialakítása', 'alt' => 'Munkagépek zúzottkő ágyazatot készítenek a Harmat Lakópark építési területén'),
                array('date' => '2026-08-13', 'date_label' => '2026. augusztus 13.', 'slug' => '2026-08-13-zuzottko-elokeszites', 'caption' => 'Előkészítés a zúzottkő terítéséhez', 'alt' => 'Zúzottkő érkezik a Harmat Lakópark alapozási munkáihoz'),
            ),
        ),
        array(
            'date' => '2026-07',
            'label' => '2026. július vége',
            'title' => 'Munkagödör, megtámasztás és tömörítés',
            'description' => 'A földkiemeléssel párhuzamosan haladt a munkagödör oldalfalainak kialakítása és az alapozási sík rendezése.',
            'images' => array(
                array('date' => '2026-07-31', 'date_label' => '2026. július 31.', 'slug' => '2026-07-31-oldalfal-megerosites', 'caption' => 'Oldalfali megerősítés az A1 területén', 'alt' => 'Az A1 épület munkagödrének oldalfali megerősítése'),
                array('date' => '2026-07-30', 'date_label' => '2026. július 30.', 'slug' => '2026-07-30-tomorites-a1', 'caption' => 'Tereprendezés és tömörítés az A1 épületnél', 'alt' => 'Úthenger dolgozik az A1 épület előkészített munkagödrében'),
                array('date' => '2026-07-28', 'date_label' => '2026. július 28.', 'slug' => '2026-07-28-parhuzamos-foldmunka', 'caption' => 'Párhuzamos földmunka az A1 épületnél', 'alt' => 'Két munkagép végez földmunkát az A1 épület területén'),
                array('date' => '2026-07-22', 'date_label' => '2026. július 22.', 'slug' => '2026-07-22-munkater-megtamasztasa', 'caption' => 'A megtámasztott munkatér kialakítása', 'alt' => 'Megtámasztott munkagödör a Harmat Lakópark építési területén'),
                array('date' => '2026-07-21', 'date_label' => '2026. július 21.', 'slug' => '2026-07-21-a1-munkaterulet', 'caption' => 'Az A1 épület munkaterületének kialakítása', 'alt' => 'Az A1 épület munkagödre és az oldalfali kialakítás részlete'),
            ),
        ),
        array(
            'date' => '2026-07',
            'label' => '2026. július eleje',
            'title' => 'Földkiemelés és kitűzési munkák',
            'description' => 'Az épületek helyén megkezdődött a földkiemelés, miközben folyamatosan zajlottak a helyszíni kitűzési és mérési feladatok.',
            'images' => array(
                array('date' => '2026-07-17', 'date_label' => '2026. július 17.', 'slug' => '2026-07-17-foldmunka-a4', 'caption' => 'Földmunka az A4 épület területén', 'alt' => 'Teherautók és munkagépek az A4 épület földmunkáinál'),
                array('date' => '2026-07-09', 'date_label' => '2026. július 9.', 'slug' => '2026-07-09-kituzi-meresek', 'caption' => 'Kitűzési és mérési munkák', 'alt' => 'Helyszíni kitűzési és mérési munkák a Harmat Lakópark területén'),
                array('date' => '2026-07-06', 'date_label' => '2026. július 6.', 'slug' => '2026-07-06-foldmunka-a1', 'caption' => 'Földkiemelés az A1 épületnél', 'alt' => 'Kialakított munkagödör az A1 épület területén'),
                array('date' => '2026-07-03', 'date_label' => '2026. július 3.', 'slug' => '2026-07-03-munkagodor-kialakitasa', 'caption' => 'A munkagödör kialakítása', 'alt' => 'Munkagépek alakítják ki a Harmat Lakópark munkagödrét'),
                array('date' => '2026-07-01', 'date_label' => '2026. július 1.', 'slug' => '2026-07-01-foldkiemeles-a2', 'caption' => 'Megkezdődött a földkiemelés az A2 épületnél', 'alt' => 'Kotrógép és teherautó az A2 épület földkiemelésénél'),
            ),
        ),
        array(
            'date' => '2026-06',
            'label' => '2026. június',
            'title' => 'A munkaterület előkészítése',
            'description' => 'A munkagépek érkezésével és a terület megtisztításával megkezdődött a kivitelezés helyszíni előkészítése.',
            'images' => array(
                array('date' => '2026-06-18', 'date_label' => '2026. június 18.', 'slug' => '2026-06-18-munkaterulet-elokeszitese', 'caption' => 'Az előkészített munkaterület', 'alt' => 'A Harmat Lakópark megtisztított építési területe'),
                array('date' => '2026-06-10', 'date_label' => '2026. június 10.', 'slug' => '2026-06-10-munkagepek-erkezese', 'caption' => 'A munkagépek érkezése', 'alt' => 'Munkagépek érkeznek a Harmat Lakópark építési területére'),
            ),
        ),
    );
}

function harmat_construction_gallery_image_url(string $slug, string $size): string
{
    return harmat_construction_gallery_base_url() . rawurlencode($slug . '-' . $size . '.webp');
}

function harmat_construction_gallery_markup(): string
{
    $html = '<section id="epitesi-fotok" class="harmat-construction-gallery" data-harmat-construction-gallery="1" aria-labelledby="harmat-construction-gallery-title">'
        . '<header class="harmat-construction-gallery-head"><time datetime="2026-06/2026-08">2026. június–augusztus</time>'
        . '<h2 id="harmat-construction-gallery-title">A munkaterület átalakulása</h2>'
        . '<p>Válogatott helyszíni felvételek követik végig az első ütem előkészítését a munkagépek érkezésétől a tömörített zúzottkő ágyazat elkészültéig.</p></header>';

    foreach (harmat_construction_gallery_groups() as $group) {
        $html .= '<article class="harmat-construction-milestone">'
            . '<header><time datetime="' . esc_attr($group['date']) . '">' . esc_html($group['label']) . '</time>'
            . '<h3>' . esc_html($group['title']) . '</h3>'
            . '<p>' . esc_html($group['description']) . '</p></header>'
            . '<div class="harmat-construction-photo-grid">';

        foreach ($group['images'] as $image) {
            $thumb_url = harmat_construction_gallery_image_url($image['slug'], '960');
            $full_url = harmat_construction_gallery_image_url($image['slug'], '1920');
            $dialog_caption = $image['date_label'] . ' — ' . $image['caption'];
            $html .= '<figure class="harmat-construction-photo">'
                . '<button type="button" data-harmat-construction-photo data-full="' . esc_url($full_url) . '" data-alt="' . esc_attr($image['alt']) . '" data-caption="' . esc_attr($dialog_caption) . '" aria-label="' . esc_attr($image['caption'] . ' – kép nagyítása') . '" title="Kép nagyítása">'
                . '<img src="' . esc_url($thumb_url) . '" width="960" height="720" alt="' . esc_attr($image['alt']) . '" loading="lazy" decoding="async">'
                . '<span class="harmat-construction-expand" aria-hidden="true">⛶</span></button>'
                . '<figcaption><time datetime="' . esc_attr($image['date']) . '">' . esc_html($image['date_label']) . '</time><span>' . esc_html($image['caption']) . '</span></figcaption>'
                . '</figure>';
        }

        $html .= '</div></article>';
    }

    $html .= '<dialog class="harmat-construction-lightbox" data-harmat-construction-lightbox aria-labelledby="harmat-construction-lightbox-caption">'
        . '<button type="button" class="harmat-construction-lightbox-close" data-harmat-construction-close aria-label="Bezárás" title="Bezárás">×</button>'
        . '<button type="button" class="harmat-construction-lightbox-nav harmat-construction-lightbox-prev" data-harmat-construction-prev aria-label="Előző kép" title="Előző kép">‹</button>'
        . '<figure><img data-harmat-construction-lightbox-image alt=""><figcaption id="harmat-construction-lightbox-caption" data-harmat-construction-lightbox-caption></figcaption></figure>'
        . '<button type="button" class="harmat-construction-lightbox-nav harmat-construction-lightbox-next" data-harmat-construction-next aria-label="Következő kép" title="Következő kép">›</button>'
        . '</dialog></section>';

    return $html;
}

function harmat_construction_september_base_url(): string
{
    return content_url('/uploads/2026/09/construction-september/');
}

function harmat_construction_september_player(string $slug, string $label, bool $primary = false): string
{
    $base = harmat_construction_september_base_url();
    return '<div class="harmat-construction-september-player" data-harmat-september-player data-video-url="' . esc_url($base . $slug . '.mp4') . '" data-poster-url="' . esc_url($base . $slug . '.jpg') . '">'
        . '<button type="button" class="harmat-construction-trigger" data-harmat-september-play aria-label="' . esc_attr($label . ' – lejátszás') . '">'
        . '<img src="' . esc_url($base . $slug . '.jpg') . '" width="1280" height="720" alt="' . esc_attr($label) . '" decoding="async" ' . ($primary ? 'fetchpriority="high"' : 'loading="lazy"') . '>'
        . '<span class="harmat-construction-play-icon" aria-hidden="true"></span>'
        . '<span class="harmat-construction-play-label">Videó lejátszása</span></button></div>'
        . '<p class="harmat-construction-nearby-link"><a href="' . esc_url($base . $slug . '.mp4') . '" target="_blank" rel="noopener noreferrer">Videó megnyitása külön lapon</a></p>';
}

function harmat_construction_september_markup(): string
{
    $html = '<section class="harmat-construction-september" data-harmat-construction-september="1" aria-labelledby="harmat-construction-september-title">'
        . '<header class="harmat-construction-gallery-head"><time datetime="2026-09-30">2026. szeptember 30-i állapot</time>'
        . '<h2 id="harmat-construction-september-title">Építési napló – 2026. szeptember</h2>'
        . '<p>Az első ütem alapozási és pincei szerkezetépítési munkáinak szeptemberi állása.</p></header>'
        . harmat_construction_september_player('2026-09-overview', 'A Harmat Lakópark 2026. október 2-i helyszíni felvétele', true)
        . '<p class="harmat-construction-september-duration"><time datetime="2026-10-02">2026. október 2-i helyszíni felvétel</time> · 32 másodperc<br>A szeptember 30-i beszámoló kiegészítése.</p>'
        . '<dl class="harmat-construction-progress-rows">';
    $progress = array(
        'A1' => 'A pincefalak és pillérek betonozása elkészült. A pincefödém zsaluzása 30%-os készültségű.',
        'A2' => 'A pincefalak és pillérek betonozása 80%-os, a pincefödém zsaluzása 20%-os készültségű.',
        'A3' => 'Az alaplemez vasalása 40%-os készültségű.',
        'A4' => 'Az alaplemez betonozása elkészült. A falak és pillérek vasalása folyamatban van.',
    );
    foreach ($progress as $building => $status) {
        $html .= '<div><dt>' . esc_html($building . ' épület') . '</dt><dd>' . esc_html($status) . '</dd></div>';
    }
    $html .= '</dl><div class="harmat-construction-photo-grid">';
    $photo_captions = array(
        'Alaplemez és pinceszinti vasalás – 2026. október 2.',
        'Zsaluzási munkák a pince szintjén',
        'Toronydaru és pinceszinti szerkezet',
        'Alaplemez vasalása – 2026. október 2.',
    );
    for ($index = 1; $index <= 4; $index++) {
        $slug = 'harmat-2026-09-site-' . sprintf('%02d', $index);
        $caption = $photo_captions[$index - 1];
        $base = harmat_construction_september_base_url();
        $html .= '<figure class="harmat-construction-photo"><button type="button" data-harmat-construction-photo data-full="' . esc_url($base . $slug . '-1920.webp') . '" data-alt="' . esc_attr($caption) . '" data-caption="' . esc_attr($caption) . '" aria-label="' . esc_attr($caption . ' – kép nagyítása') . '" title="Kép nagyítása">'
            . '<img src="' . esc_url($base . $slug . '-960.webp') . '" width="960" height="720" alt="' . esc_attr($caption) . '" loading="lazy" decoding="async">'
            . '<span class="harmat-construction-expand" aria-hidden="true">⛶</span></button><figcaption><span>' . esc_html($caption) . '</span></figcaption></figure>';
    }
    $html .= '</div><details class="harmat-construction-september-clips"><summary>Szeptemberi helyszíni videók (7)</summary><div class="harmat-construction-clip-grid">';
    $clips = array(
        array('2026-09-21-a1-a2', '2026-09', '2026. szeptember', 'A1 és A2'),
        array('2026-09-22-a2', '2026-09-22', '2026. szeptember 22.', 'A2'),
        array('2026-09-23-a1', '2026-09-23', '2026. szeptember 23.', 'A1'),
        array('2026-09-24-a3', '2026-09-24', '2026. szeptember 24.', 'A3'),
        array('2026-09-25-a1', '2026-09-25', '2026. szeptember 25.', 'A1'),
        array('2026-09-25-a2', '2026-09-25', '2026. szeptember 25.', 'A2'),
        array('2026-09-25-a4', '2026-09-25', '2026. szeptember 25.', 'A4'),
    );
    foreach ($clips as $clip) {
        $html .= '<article><h3><time datetime="' . esc_attr($clip[1]) . '">' . esc_html($clip[2]) . '</time> · ' . esc_html($clip[3]) . '</h3>'
            . harmat_construction_september_player($clip[0], $clip[2] . ' – ' . $clip[3]) . '</article>';
    }
    return $html . '</div></details></section>';
}

function harmat_construction_video_markup(): string
{
    $poster_url = harmat_construction_video_poster_url();
    $watch_url = harmat_construction_video_watch_url();

    return '<section class="harmat-construction-feature" data-harmat-construction-video="1" aria-labelledby="harmat-construction-video-title">'
        . '<div class="harmat-construction-feature-head">'
        . '<div><time datetime="2026-08">2026. augusztus</time>'
        . '<h2 id="harmat-construction-video-title">Az építkezés augusztusi állása</h2>'
        . '<p>Helyszíni és légi felvételek mutatják be az első ütem augusztusi földmunkáit, az alapozás előkészítését és a munkaterület akkori állapotát.</p></div>'
        . '<span aria-label="A videó hossza 1 perc 31 másodperc">1:31</span>'
        . '</div>'
        . '<div class="harmat-construction-player" data-harmat-construction-player>'
        . '<button type="button" class="harmat-construction-trigger" data-harmat-construction-play data-video-id="' . esc_attr(HARMAT_CONSTRUCTION_VIDEO_ID) . '" aria-label="A 2026. augusztusi építési videó lejátszása">'
        . '<img src="' . esc_url($poster_url) . '" width="1280" height="720" alt="A Harmat Lakópark építési területe 2026 augusztusában" loading="lazy" decoding="async">'
        . '<span class="harmat-construction-play-icon" aria-hidden="true"></span>'
        . '<span class="harmat-construction-play-label">Videó lejátszása</span>'
        . '</button>'
        . '<noscript><p><a href="' . esc_url($watch_url) . '">A 2026. augusztusi építési videó megnyitása a YouTube-on</a></p></noscript>'
        . '</div>'
        . '<div class="harmat-construction-feature-meta">'
        . '<span>Helyszíni felvételek · Harmat utca 22.</span>'
        . '<a href="' . esc_url($watch_url) . '" target="_blank" rel="noopener noreferrer">Megnyitás a YouTube-on</a>'
        . '</div>'
        . '</section>';
}

function harmat_construction_nearby_video_markup(): string
{
    $video_url = content_url('/uploads/2026/09/harmat-kornyek-kutyapark-2026-09.mp4');
    $poster_url = content_url('/uploads/2026/09/harmat-kornyek-kutyapark-2026-09.jpg');

    return '<section class="harmat-construction-nearby" aria-labelledby="harmat-construction-nearby-title">'
        . '<div class="harmat-construction-gallery-head">'
        . '<h2 id="harmat-construction-nearby-title">Közeli park és kutyafuttató környezete</h2>'
        . '<p>Felvételek a Harmat Lakópark környékéről.</p></div>'
        . '<div class="harmat-construction-nearby-player" data-harmat-nearby-player data-video-url="' . esc_url($video_url) . '" data-poster-url="' . esc_url($poster_url) . '">'
        . '<button type="button" class="harmat-construction-trigger" data-harmat-nearby-play aria-label="A közeli park videójának lejátszása">'
        . '<img src="' . esc_url($poster_url) . '" width="1280" height="720" alt="A Harmat Lakópark közelében lévő park környezete" loading="lazy" decoding="async">'
        . '<span class="harmat-construction-play-icon" aria-hidden="true"></span>'
        . '<span class="harmat-construction-play-label">Videó lejátszása</span>'
        . '</button></div>'
        . '<p class="harmat-construction-nearby-link"><a href="' . esc_url($video_url) . '" target="_blank" rel="noopener noreferrer">Videó megnyitása külön lapon</a></p>'
        . '</section>';
}

function harmat_construction_video_inject(string $html): string
{
    if ($html === '' || strpos($html, 'data-harmat-construction-video="1"') !== false) {
        return $html;
    }

    $anchor = '<section class="harmat-build-log-list">';
    $position = strpos($html, $anchor);
    if ($position === false) {
        return $html;
    }

    return substr($html, 0, $position)
        . harmat_construction_september_markup()
        . harmat_construction_video_markup()
        . harmat_construction_nearby_video_markup()
        . harmat_construction_gallery_markup()
        . substr($html, $position);
}

add_action('template_redirect', static function (): void {
    if (harmat_construction_video_is_page()) {
        // This outer buffer runs after the existing construction-page renderer.
        ob_start('harmat_construction_video_inject');
    }
}, -100);

add_action('wp_head', static function (): void {
    if (!harmat_construction_video_is_page()) {
        return;
    }
    ?>
<style id="harmat-construction-video-css">
.harmat-construction-september{margin:30px 0 42px;color:#263135;min-width:0;overflow-wrap:anywhere}
.harmat-construction-september .harmat-construction-gallery-head h2{font-size:28px}
.harmat-construction-september .harmat-construction-gallery-head time{letter-spacing:0}
.harmat-construction-september-player{position:relative;width:100%;aspect-ratio:16/9;overflow:hidden;background:#17272d}
.harmat-construction-september-player video{display:block;width:100%;height:100%;object-fit:contain;background:#17272d}
.harmat-construction-september-duration{margin:10px 0 20px;color:#536066;font-size:13px}
.harmat-construction-progress-rows{margin:0 0 26px}
.harmat-construction-progress-rows>div{display:grid;grid-template-columns:110px minmax(0,1fr);gap:18px;padding:14px 0;border-top:1px solid #dce1e2}
.harmat-construction-progress-rows dt{font-weight:700}
.harmat-construction-progress-rows dd{margin:0;font-size:15px;line-height:1.6}
.harmat-construction-september-clips{margin:26px 0 0;border-block:1px solid #dce1e2}
.harmat-construction-september-clips summary{padding:16px 0;cursor:pointer;font-weight:700}
.harmat-construction-september-clips summary:focus-visible{outline:2px solid #263135;outline-offset:3px}
.harmat-construction-clip-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px 18px;padding-bottom:24px}
.harmat-construction-clip-grid article{min-width:0}
.harmat-construction-clip-grid h3{margin:0 0 10px;font:700 14px/1.5 Arial,sans-serif;letter-spacing:0;color:#263135}
@media(max-width:720px){.harmat-construction-september .harmat-construction-gallery-head h2{font-size:24px}.harmat-construction-progress-rows>div{grid-template-columns:1fr;gap:5px}.harmat-construction-clip-grid{grid-template-columns:1fr}}
.harmat-construction-feature{margin:30px 0 38px;color:#263135}
.harmat-construction-feature-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin:0 0 18px}
.harmat-construction-feature-head>div{max-width:790px}
.harmat-construction-feature-head time{display:block;margin:0 0 8px;color:#9a6a2a;font:800 12px/1.2 Montserrat,Arial,sans-serif;letter-spacing:.1em;text-transform:uppercase}
.harmat-construction-feature-head h2{margin:0 0 10px;color:#1f2d34;font:700 30px/1.18 Georgia,"Times New Roman",serif;letter-spacing:0}
.harmat-construction-feature-head p{margin:0;color:#536066;font-size:15px;line-height:1.7}
.harmat-construction-feature-head>span{flex:0 0 auto;padding:7px 10px;border:1px solid rgba(154,106,42,.35);background:#fff;color:#536066;font-size:12px;font-weight:800}
.harmat-construction-player{position:relative;width:100%;aspect-ratio:16/9;overflow:hidden;background:#17272d}
.harmat-construction-trigger{position:relative;display:block;width:100%;height:100%;padding:0;border:0;background:#17272d;cursor:pointer}
.harmat-construction-trigger:focus-visible{outline:3px solid #fff;outline-offset:-7px}
.harmat-construction-trigger img{display:block;width:100%;height:100%;object-fit:cover;object-position:center}
.harmat-construction-trigger:after{content:"";position:absolute;inset:0;background:rgba(10,25,31,.18);transition:background-color .2s ease}
.harmat-construction-trigger:hover:after,.harmat-construction-trigger:focus-visible:after{background:rgba(10,25,31,.08)}
.harmat-construction-play-icon{position:absolute;z-index:2;left:50%;top:50%;width:76px;height:76px;transform:translate(-50%,-50%);border:2px solid #fff;border-radius:50%;background:rgba(20,43,50,.86);box-shadow:0 10px 26px rgba(0,0,0,.22)}
.harmat-construction-play-icon:after{content:"";position:absolute;left:31px;top:24px;border-top:13px solid transparent;border-bottom:13px solid transparent;border-left:20px solid #fff}
.harmat-construction-play-label{position:absolute;z-index:2;left:50%;top:calc(50% + 54px);transform:translateX(-50%);color:#fff;font-size:13px;font-weight:800;text-shadow:0 2px 5px rgba(0,0,0,.65);white-space:nowrap}
.harmat-construction-player iframe{display:block;width:100%;height:100%;border:0;background:#17272d}
.harmat-construction-feature-meta{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:13px 0;border-bottom:1px solid rgba(154,106,42,.22);color:#667278;font-size:13px}
.harmat-construction-feature-meta a{color:#8b6128;font-weight:800;text-decoration:underline;text-underline-offset:3px}
.harmat-construction-nearby{margin:42px 0 0;color:#263135}
.harmat-construction-nearby .harmat-construction-gallery-head h2{margin:0 0 10px;font-size:30px}
.harmat-construction-nearby-player{position:relative;width:100%;aspect-ratio:16/9;overflow:hidden;background:#17272d}
.harmat-construction-nearby-player video{display:block;width:100%;height:100%;object-fit:contain;background:#17272d}
.harmat-construction-nearby-link{margin:12px 0 0;font-size:13px}
.harmat-construction-nearby-link a{color:#8b6128;font-weight:800;text-decoration:underline;text-underline-offset:3px}
.harmat-construction-gallery{margin:46px 0 44px;color:#263135}
.harmat-construction-gallery-head{max-width:820px;margin:0 0 12px}
.harmat-construction-gallery-head time,.harmat-construction-milestone>header time{display:block;margin:0 0 8px;color:#9a6a2a;font:800 12px/1.2 Montserrat,Arial,sans-serif;letter-spacing:.1em;text-transform:uppercase}
.harmat-construction-gallery-head h2{margin:0 0 12px;color:#1f2d34;font:700 34px/1.16 Georgia,"Times New Roman",serif;letter-spacing:0}
.harmat-construction-gallery-head p,.harmat-construction-milestone>header p{margin:0;color:#536066;font-size:15px;line-height:1.7}
.harmat-construction-milestone{display:grid;grid-template-columns:minmax(210px,280px) minmax(0,1fr);gap:34px;padding:32px 0;border-top:1px solid rgba(154,106,42,.22)}
.harmat-construction-milestone>header h3{margin:0 0 10px;color:#263135;font:700 24px/1.24 Georgia,"Times New Roman",serif;letter-spacing:0}
.harmat-construction-photo-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:start}
.harmat-construction-photo{min-width:0;margin:0;border:1px solid rgba(154,106,42,.2);background:#fff;overflow:hidden}
.harmat-construction-photo>button{position:relative;display:block;width:100%;aspect-ratio:4/3;padding:0;border:0;background:#e8e2d6;cursor:zoom-in;overflow:hidden}
.harmat-construction-photo>button:focus-visible{outline:3px solid #9a6a2a;outline-offset:-5px}
.harmat-construction-photo img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .25s ease}
.harmat-construction-photo>button:hover img,.harmat-construction-photo>button:focus-visible img{transform:scale(1.025)}
.harmat-construction-expand{position:absolute;right:10px;top:10px;display:grid;width:34px;height:34px;place-items:center;background:rgba(24,39,45,.84);color:#fff;font-size:20px;line-height:1}
.harmat-construction-photo figcaption{display:flex;min-height:78px;flex-direction:column;gap:5px;padding:13px 15px 15px}
.harmat-construction-photo figcaption time{color:#9a6a2a;font-size:11px;font-weight:800;text-transform:uppercase}
.harmat-construction-photo figcaption span{color:#263135;font-size:14px;font-weight:800;line-height:1.4}
.harmat-construction-lightbox{width:min(1180px,calc(100vw - 36px));max-width:none;height:min(820px,calc(100vh - 36px));max-height:none;margin:auto;padding:0;border:0;background:#10191d;color:#fff;overflow:hidden}
.harmat-construction-lightbox::backdrop{background:rgba(8,14,17,.86)}
.harmat-construction-lightbox[open]{display:grid;grid-template-columns:56px minmax(0,1fr) 56px;align-items:center}
.harmat-construction-lightbox figure{display:grid;min-width:0;height:100%;margin:0;grid-template-rows:minmax(0,1fr) auto;align-items:center}
.harmat-construction-lightbox figure img{display:block;max-width:100%;max-height:calc(100vh - 120px);margin:auto;object-fit:contain}
.harmat-construction-lightbox figcaption{min-height:56px;padding:14px 10px 16px;color:#fff;font-size:14px;font-weight:700;line-height:1.45;text-align:center}
.harmat-construction-lightbox-close,.harmat-construction-lightbox-nav{display:grid;place-items:center;border:0;background:rgba(255,255,255,.11);color:#fff;cursor:pointer}
.harmat-construction-lightbox-close:hover,.harmat-construction-lightbox-close:focus-visible,.harmat-construction-lightbox-nav:hover,.harmat-construction-lightbox-nav:focus-visible{background:rgba(255,255,255,.22);outline:2px solid #fff;outline-offset:-2px}
.harmat-construction-lightbox-close{position:absolute;z-index:2;right:10px;top:10px;width:42px;height:42px;font-size:30px;line-height:1}
.harmat-construction-lightbox-nav{width:44px;height:60px;margin:auto;font-size:42px;line-height:1}
body.harmat-construction-lightbox-open{overflow:hidden}
@media(max-width:820px){.harmat-construction-milestone{grid-template-columns:1fr;gap:18px}.harmat-construction-milestone>header{max-width:700px}}
@media(max-width:720px){.harmat-construction-feature{margin:24px 0 32px}.harmat-construction-feature-head{align-items:flex-start;gap:12px}.harmat-construction-feature-head h2{font-size:25px}.harmat-construction-feature-head p{font-size:14px}.harmat-construction-feature-head>span{margin-top:20px}.harmat-construction-feature-meta{align-items:flex-start;flex-direction:column;gap:7px}.harmat-construction-play-icon{width:62px;height:62px}.harmat-construction-play-icon:after{left:25px;top:19px;border-top-width:11px;border-bottom-width:11px;border-left-width:17px}.harmat-construction-play-label{top:calc(50% + 45px)}.harmat-construction-gallery{margin:38px 0}.harmat-construction-gallery-head h2{font-size:28px}.harmat-construction-milestone{padding:27px 0}.harmat-construction-milestone>header h3{font-size:22px}.harmat-construction-photo-grid{grid-template-columns:1fr}.harmat-construction-photo figcaption{min-height:0}.harmat-construction-lightbox{width:100vw;height:100dvh}.harmat-construction-lightbox[open]{grid-template-columns:44px minmax(0,1fr) 44px}.harmat-construction-lightbox-nav{width:38px;height:54px;font-size:36px}.harmat-construction-lightbox figure img{max-height:calc(100dvh - 104px)}.harmat-construction-lightbox figcaption{padding-inline:4px;font-size:13px}}
@media(prefers-reduced-motion:reduce){.harmat-construction-photo img{transition:none}}
</style>
    <?php
}, 100);

add_action('wp_footer', static function (): void {
    if (!harmat_construction_video_is_page()) {
        return;
    }
    ?>
<script id="harmat-construction-video-runtime">
(function(){
  var trigger=document.querySelector('[data-harmat-construction-play]');
  if(trigger){
    trigger.addEventListener('click',function(){
      var videoId=trigger.getAttribute('data-video-id');
      var player=trigger.closest('[data-harmat-construction-player]');
      if(!videoId||!player||player.getAttribute('data-player-loaded')==='1'){return;}
      var frame=document.createElement('iframe');
      frame.src='https://www.youtube-nocookie.com/embed/'+encodeURIComponent(videoId)+'?autoplay=1&playsinline=1&rel=0&modestbranding=1&hl=hu';
      frame.title='Harmat Lakópark építkezés – 2026. augusztus';
      frame.allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
      frame.referrerPolicy='strict-origin-when-cross-origin';
      frame.allowFullscreen=true;
      player.setAttribute('data-player-loaded','1');
      trigger.replaceWith(frame);
      frame.focus();
    },{once:true});
  }

  var nearbyTrigger=document.querySelector('[data-harmat-nearby-play]');
  if(nearbyTrigger){
    nearbyTrigger.addEventListener('click',function(){
      var player=nearbyTrigger.closest('[data-harmat-nearby-player]');
      var videoUrl=player&&player.getAttribute('data-video-url');
      if(!videoUrl){return;}
      var video=document.createElement('video');
      video.controls=true;
      video.playsInline=true;
      video.preload='none';
      video.poster=player.getAttribute('data-poster-url')||'';
      video.setAttribute('aria-label','A közeli park videója');
      video.src=videoUrl;
      nearbyTrigger.replaceWith(video);
      video.focus();
      var playback=video.play();
      if(playback&&typeof playback.catch==='function'){playback.catch(function(){});}
    },{once:true});
  }

  document.querySelectorAll('[data-harmat-september-play]').forEach(function(button){
    button.addEventListener('click',function(){
      var player=button.closest('[data-harmat-september-player]');
      var videoUrl=player&&player.getAttribute('data-video-url');
      if(!videoUrl){return;}
      var video=document.createElement('video');
      video.controls=true;
      video.playsInline=true;
      video.preload='none';
      video.poster=player.getAttribute('data-poster-url')||'';
      video.setAttribute('aria-label',button.querySelector('img').alt);
      video.setAttribute('tabindex','0');
      video.src=videoUrl;
      button.replaceWith(video);
      video.focus();
      var playback=video.play();
      if(playback&&typeof playback.catch==='function'){playback.catch(function(){});}
    },{once:true});
  });

  var photos=Array.prototype.slice.call(document.querySelectorAll('[data-harmat-construction-photo]'));
  var lightbox=document.querySelector('[data-harmat-construction-lightbox]');
  if(!photos.length||!lightbox){return;}
  var lightboxImage=lightbox.querySelector('[data-harmat-construction-lightbox-image]');
  var lightboxCaption=lightbox.querySelector('[data-harmat-construction-lightbox-caption]');
  var activeIndex=0;
  var returnFocus=null;

  function renderPhoto(index){
    activeIndex=(index+photos.length)%photos.length;
    var photo=photos[activeIndex];
    lightboxImage.src=photo.getAttribute('data-full')||'';
    lightboxImage.alt=photo.getAttribute('data-alt')||'';
    lightboxCaption.textContent=photo.getAttribute('data-caption')||'';
  }
  function openPhoto(index,button){
    returnFocus=button;
    renderPhoto(index);
    document.body.classList.add('harmat-construction-lightbox-open');
    if(typeof lightbox.showModal==='function'){lightbox.showModal();}else{lightbox.setAttribute('open','');}
    lightbox.querySelector('[data-harmat-construction-close]').focus();
  }
  function closeLightbox(){
    if(typeof lightbox.close==='function'&&lightbox.open){lightbox.close();}else{lightbox.removeAttribute('open');}
    document.body.classList.remove('harmat-construction-lightbox-open');
    lightboxImage.removeAttribute('src');
    if(returnFocus){returnFocus.focus();}
  }
  photos.forEach(function(photo,index){photo.addEventListener('click',function(){openPhoto(index,photo);});});
  lightbox.querySelector('[data-harmat-construction-close]').addEventListener('click',closeLightbox);
  lightbox.querySelector('[data-harmat-construction-prev]').addEventListener('click',function(){renderPhoto(activeIndex-1);});
  lightbox.querySelector('[data-harmat-construction-next]').addEventListener('click',function(){renderPhoto(activeIndex+1);});
  lightbox.addEventListener('cancel',function(event){event.preventDefault();closeLightbox();});
  lightbox.addEventListener('click',function(event){if(event.target===lightbox){closeLightbox();}});
  document.addEventListener('keydown',function(event){
    if(!lightbox.hasAttribute('open')){return;}
    if(event.key==='ArrowLeft'){renderPhoto(activeIndex-1);}
    if(event.key==='ArrowRight'){renderPhoto(activeIndex+1);}
  });
})();
</script>
    <?php
}, 100);

add_filter('wpseo_metadesc', static function ($description) {
    if (!harmat_construction_video_is_page()) {
        return $description;
    }

    return 'A Harmat Lakópark építési naplója: 2026. szeptember 30-i készültség, helyszíni videók és fotók, valamint a június–augusztusi munkák képes idővonala.';
}, 99);

add_filter('wpseo_opengraph_desc', static function ($description) {
    if (!harmat_construction_video_is_page()) {
        return $description;
    }

    return 'A Harmat Lakópark 2026. szeptember 30-i készültsége, helyszíni videók és fotók a Harmat utca 22. munkaterületéről.';
}, 99);

add_filter('wpseo_twitter_description', static function ($description) {
    if (!harmat_construction_video_is_page()) {
        return $description;
    }

    return 'A Harmat Lakópark 2026. szeptember 30-i készültsége, helyszíni videók és fotók a Harmat utca 22. munkaterületéről.';
}, 99);

add_filter('wpseo_opengraph_image', static function ($image) {
    return harmat_construction_video_is_page() ? harmat_construction_september_base_url() . '2026-09-overview.jpg' : $image;
}, 99);

add_filter('wpseo_twitter_image', static function ($image) {
    return harmat_construction_video_is_page() ? harmat_construction_september_base_url() . '2026-09-overview.jpg' : $image;
}, 99);

add_filter('wpseo_schema_graph', static function ($graph) {
    if (!harmat_construction_video_is_page() || !is_array($graph)) {
        return $graph;
    }

    $video_id = harmat_construction_video_page_url() . '#construction-video';
    $september_video_id = harmat_construction_video_page_url() . '#construction-september-video';
    $gallery_id = harmat_construction_video_page_url() . '#construction-gallery';
    $existing_ids = array();
    foreach ($graph as $node) {
        if (is_array($node) && isset($node['@id'])) {
            $existing_ids[] = $node['@id'];
        }
    }

    if (!in_array($video_id, $existing_ids, true)) {
        $graph[] = array(
            '@type' => 'VideoObject',
            '@id' => $video_id,
            'name' => 'Harmat Lakópark építkezés – 2026. augusztus',
            'description' => 'Helyszíni és légi felvételek a Harmat Lakópark első ütemének földmunkáiról és az alapozás előkészítéséről.',
            'thumbnailUrl' => harmat_construction_video_poster_url(),
            'uploadDate' => HARMAT_CONSTRUCTION_VIDEO_DATE,
            'duration' => HARMAT_CONSTRUCTION_VIDEO_DURATION,
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/' . HARMAT_CONSTRUCTION_VIDEO_ID,
            'contentUrl' => harmat_construction_video_watch_url(),
            'inLanguage' => 'hu-HU',
            'isPartOf' => array('@id' => harmat_construction_video_page_url()),
            'about' => array('@id' => home_url('/#harmat-lakopark')),
            'publisher' => array('@id' => home_url('/#organization')),
        );
    }

    if (!in_array($september_video_id, $existing_ids, true)) {
        $graph[] = array(
            '@type' => 'VideoObject',
            '@id' => $september_video_id,
            'name' => 'Harmat Lakópark – 2026. október 2-i helyszíni felvétel',
            'description' => '2026. október 2-i helyszíni felvétel a Harmat Lakópark első ütemének építkezéséről, a szeptember 30-i beszámoló kiegészítéseként.',
            'thumbnailUrl' => harmat_construction_september_base_url() . '2026-09-overview.jpg',
            'contentUrl' => harmat_construction_september_base_url() . '2026-09-overview.mp4',
            'uploadDate' => '2026-10-03',
            'dateCreated' => '2026-10-02',
            'duration' => 'PT32S',
            'inLanguage' => 'hu-HU',
            'isPartOf' => array('@id' => harmat_construction_video_page_url()),
            'about' => array('@id' => home_url('/#harmat-lakopark')),
            'publisher' => array('@id' => home_url('/#organization')),
        );
    }

    if (!in_array($gallery_id, $existing_ids, true)) {
        $gallery_images = array();
        foreach (harmat_construction_gallery_groups() as $group) {
            foreach ($group['images'] as $image) {
                $gallery_images[] = array(
                    '@type' => 'ImageObject',
                    'contentUrl' => harmat_construction_gallery_image_url($image['slug'], '1920'),
                    'thumbnailUrl' => harmat_construction_gallery_image_url($image['slug'], '960'),
                    'caption' => $image['date_label'] . ' — ' . $image['caption'],
                    'dateCreated' => $image['date'],
                    'inLanguage' => 'hu-HU',
                );
            }
        }
        $graph[] = array(
            '@type' => 'ImageGallery',
            '@id' => $gallery_id,
            'name' => 'A Harmat Lakópark építkezése képekben',
            'description' => 'Fényképes idővonal a munkaterület előkészítésétől a tömörített zúzottkő ágyazat kialakításáig.',
            'url' => harmat_construction_video_page_url() . '#epitesi-fotok',
            'inLanguage' => 'hu-HU',
            'isPartOf' => array('@id' => harmat_construction_video_page_url()),
            'image' => $gallery_images,
        );
    }

    return $graph;
}, 99);
