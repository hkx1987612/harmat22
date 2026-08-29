<?php
/**
 * Plugin Name: Harmat Construction Progress Video
 * Description: Adds the current construction video to the public construction-log page.
 * Version: 1.0.1
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

function harmat_construction_video_markup(): string
{
    $poster_url = harmat_construction_video_poster_url();
    $watch_url = harmat_construction_video_watch_url();

    return '<section class="harmat-construction-feature" data-harmat-construction-video="1" aria-labelledby="harmat-construction-video-title">'
        . '<div class="harmat-construction-feature-head">'
        . '<div><time datetime="2026-08">2026. augusztus</time>'
        . '<h2 id="harmat-construction-video-title">Az építkezés aktuális állása</h2>'
        . '<p>Helyszíni és légi felvételek mutatják be az első ütem földmunkáit, az alapozás előkészítését és a munkaterület jelenlegi állapotát.</p></div>'
        . '<span aria-label="A videó hossza 1 perc 31 másodperc">1:31</span>'
        . '</div>'
        . '<div class="harmat-construction-player" data-harmat-construction-player>'
        . '<button type="button" class="harmat-construction-trigger" data-harmat-construction-play data-video-id="' . esc_attr(HARMAT_CONSTRUCTION_VIDEO_ID) . '" aria-label="A 2026. augusztusi építési videó lejátszása">'
        . '<img src="' . esc_url($poster_url) . '" width="1280" height="720" alt="A Harmat Lakópark építési területe 2026 augusztusában" decoding="async" fetchpriority="high">'
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
        . harmat_construction_video_markup()
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
@media(max-width:720px){.harmat-construction-feature{margin:24px 0 32px}.harmat-construction-feature-head{align-items:flex-start;gap:12px}.harmat-construction-feature-head h2{font-size:25px}.harmat-construction-feature-head p{font-size:14px}.harmat-construction-feature-head>span{margin-top:20px}.harmat-construction-feature-meta{align-items:flex-start;flex-direction:column;gap:7px}.harmat-construction-play-icon{width:62px;height:62px}.harmat-construction-play-icon:after{left:25px;top:19px;border-top-width:11px;border-bottom-width:11px;border-left-width:17px}.harmat-construction-play-label{top:calc(50% + 45px)}}
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
  if(!trigger){return;}
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
})();
</script>
    <?php
}, 100);

add_filter('wpseo_metadesc', static function ($description) {
    if (!harmat_construction_video_is_page()) {
        return $description;
    }

    return 'A Harmat Lakópark építési naplója: a 2026. augusztusi földmunkák, alapozási előkészítés és az első ütem aktuális helyszíni videója.';
}, 99);

add_filter('wpseo_opengraph_desc', static function ($description) {
    if (!harmat_construction_video_is_page()) {
        return $description;
    }

    return 'A Harmat Lakópark 2026. augusztusi építési helyzetképe videón, a Harmat utca 22. munkaterületéről.';
}, 99);

add_filter('wpseo_twitter_description', static function ($description) {
    if (!harmat_construction_video_is_page()) {
        return $description;
    }

    return 'A Harmat Lakópark 2026. augusztusi építési helyzetképe videón, a Harmat utca 22. munkaterületéről.';
}, 99);

add_filter('wpseo_opengraph_image', static function ($image) {
    return harmat_construction_video_is_page() ? harmat_construction_video_poster_url() : $image;
}, 99);

add_filter('wpseo_twitter_image', static function ($image) {
    return harmat_construction_video_is_page() ? harmat_construction_video_poster_url() : $image;
}, 99);

add_filter('wpseo_schema_graph', static function ($graph) {
    if (!harmat_construction_video_is_page() || !is_array($graph)) {
        return $graph;
    }

    $video_id = harmat_construction_video_page_url() . '#construction-video';
    foreach ($graph as $node) {
        if (is_array($node) && ($node['@id'] ?? '') === $video_id) {
            return $graph;
        }
    }

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

    return $graph;
}, 99);
