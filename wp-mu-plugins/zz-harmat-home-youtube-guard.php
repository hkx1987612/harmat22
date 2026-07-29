<?php
/**
 * Plugin Name: Harmat Homepage YouTube Bandwidth Guard
 * Description: Replaces origin-hosted presentation videos with resilient YouTube players and monitors hosting bandwidth.
 * Version: 1.3.3
 */

if (!defined('ABSPATH')) {
    exit;
}

const HARMAT_BW_YOUTUBE_ID = 'kmAg_ki-yYY';
const HARMAT_BW_LIMIT_MIB = 512000;
const HARMAT_BW_ORIGIN_VIDEO = '/uploads/2026/05/yulu-garden-source-compressed-60m.mp4';
const HARMAT_BW_MOBILE_ORIGIN_VIDEO = '/uploads/2026/05/yulu-garden-mobile-720p.mp4';
const HARMAT_BW_POSTER = '/uploads/2026/02/Harmat22_latvany-3.jpg';
const HARMAT_BW_3D_VIDEO_FILENAME = 'spjs.mp4';
const HARMAT_BW_3D_POSTER = '/plugins/harmat22-map-redesign/assets/harmat-3d/video_spjs.jpg';
const HARMAT_BW_VIDEO_UPLOAD_DATE = '2026-05-01T00:00:00+02:00';

function harmat_bw_retired_video_paths(): array
{
    return array(
        (string) wp_parse_url(content_url(HARMAT_BW_ORIGIN_VIDEO), PHP_URL_PATH),
        (string) wp_parse_url(content_url(HARMAT_BW_MOBILE_ORIGIN_VIDEO), PHP_URL_PATH),
        (string) wp_parse_url(
            content_url(
                '/plugins/harmat22-map-redesign/assets/harmat-3d/'
                . HARMAT_BW_3D_VIDEO_FILENAME
            ),
            PHP_URL_PATH
        ),
    );
}

add_action('init', function (): void {
    $path = isset($_SERVER['REQUEST_URI'])
        ? (string) wp_parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH)
        : '';

    if (!in_array($path, harmat_bw_retired_video_paths(), true)) {
        return;
    }

    status_header(410);
    nocache_headers();
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow', true);
    exit;
}, -1000);

function harmat_bw_is_public_homepage(): bool
{
    return !is_admin()
        && !wp_doing_ajax()
        && !wp_is_json_request()
        && is_front_page();
}

function harmat_bw_is_public_3d_video_surface(): bool
{
    return !is_admin()
        && !wp_doing_ajax()
        && !wp_is_json_request()
        && (is_front_page() || is_page('harmat-lakopark-kornyeke'));
}

function harmat_bw_youtube_embed_url(bool $privacy_enhanced = true): string
{
    $host = $privacy_enhanced ? 'https://www.youtube-nocookie.com' : 'https://www.youtube.com';

    return $host . '/embed/' . HARMAT_BW_YOUTUBE_ID;
}

function harmat_bw_url_escape_variants(string $url): array
{
    $variants = array();

    for ($level = 0; $level <= 4; $level++) {
        $variants[] = $url;
        $url = str_replace('/', '\/', $url);
    }

    return array_values(array_unique($variants));
}

function harmat_bw_content_url_variants(string $path): array
{
    $content_url = content_url($path);
    $urls = array_unique(
        array(
            $content_url,
            set_url_scheme($content_url, 'http'),
            set_url_scheme($content_url, 'https'),
        )
    );
    $variants = array();

    foreach ($urls as $url) {
        $variants = array_merge($variants, harmat_bw_url_escape_variants($url));
    }

    return array_values(array_unique($variants));
}

add_action('muplugins_loaded', function (): void {
    if (function_exists('harmat_perf_mobile_hero_video_switch')) {
        remove_action('wp_head', 'harmat_perf_mobile_hero_video_switch', 2);
    }
});

add_action('template_redirect', function (): void {
    if (!harmat_bw_is_public_homepage()) {
        return;
    }

    ob_start('harmat_bw_filter_homepage_html');
}, -100);

function harmat_bw_filter_homepage_html(string $html): string
{
    $origin_variants = harmat_bw_content_url_variants(HARMAT_BW_ORIGIN_VIDEO);
    $mobile_origin_variants = harmat_bw_content_url_variants(HARMAT_BW_MOBILE_ORIGIN_VIDEO);
    $embed_url = harmat_bw_youtube_embed_url(false);

    $html = preg_replace_callback(
        '~("bg":\{"video":\{)(.*?)(\}\},"tl")~s',
        static function (array $match) use (
            $origin_variants,
            $mobile_origin_variants
        ): string {
            if (
                strpos($match[2], 'yulu-garden-source-compressed-60m.mp4') === false
                && strpos($match[2], 'yulu-garden-mobile-720p.mp4') === false
            ) {
                return $match[0];
            }

            $video = str_replace(
                array_merge($origin_variants, $mobile_origin_variants),
                '',
                $match[2]
            );
            $video = str_replace('"autoPlay":true', '"autoPlay":false', $video);
            $video = str_replace('"loop":true', '"loop":false', $video);
            $video = str_replace('"preload":"auto"', '"preload":"none"', $video);
            $video = str_replace('"rewind":true', '"rewind":false', $video);
            $video = str_replace('"insteadVideo":false', '"insteadVideo":true', $video);

            return $match[1] . $video . $match[3];
        },
        $html
    );

    $html = preg_replace(
        '~\s*<script[^>]*id=["\']harmat-mobile-hero-video-switch["\'][^>]*>.*?</script>\s*~is',
        '',
        $html
    );
    $html = preg_replace(
        '~\s*<script[^>]*id=["\']harmat-video-schema["\'][^>]*>.*?</script>\s*~is',
        '',
        $html
    );
    $html = preg_replace(
        '~\s*<script[^>]*class=["\'][^"\']*\bharmat-home-seo-schema\b[^"\']*["\'][^>]*>.*?</script>\s*~is',
        '',
        $html
    );

    $content_url_patterns = array();
    foreach ($origin_variants as $variant) {
        $content_url_patterns[] = '"contentUrl":"' . $variant . '"';
    }
    $html = str_replace(
        $content_url_patterns,
        '"embedUrl":"' . $embed_url . '"',
        $html
    );

    // Fail closed: the heavy origin video must never remain in public homepage output.
    $html = str_replace(
        array_merge($origin_variants, $mobile_origin_variants),
        '',
        $html
    );

    return $html;
}

add_action('wp_head', function (): void {
    if (!harmat_bw_is_public_homepage()) {
        return;
    }

    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'VideoObject',
        '@id' => home_url('/#harmat-project-video'),
        'name' => 'Harmat Lakópark látványvideó',
        'description' => 'A Harmat Lakópark új építésű lakásait, környezetét és hangulatát bemutató látványvideó.',
        'thumbnailUrl' => array(content_url(HARMAT_BW_POSTER)),
        'uploadDate' => HARMAT_BW_VIDEO_UPLOAD_DATE,
        'embedUrl' => harmat_bw_youtube_embed_url(false),
        'duration' => 'PT1M30S',
        'inLanguage' => 'hu-HU',
        'publisher' => array(
            '@type' => 'Organization',
            'name' => 'Harmat Lakópark',
            'url' => home_url('/'),
        ),
    );

    echo '<script type="application/ld+json" id="harmat-youtube-video-schema">';
    echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo '</script>' . "\n";
}, 41);

add_action('wp_head', function (): void {
    if (!harmat_bw_is_public_homepage()) {
        return;
    }
    ?>
<style id="harmat-youtube-hero-style">
#SR7_1_1 {
  position:relative;
  background:#202522 url('<?php echo esc_url(content_url(HARMAT_BW_POSTER)); ?>') center center/cover no-repeat;
}
#SR7_1_1 sr7-prl {
  display:none!important;
  visibility:hidden!important;
  opacity:0!important;
  pointer-events:none!important;
}
#SR7_1_1 .harmat-youtube-hero {
  position:absolute;
  inset:0;
  z-index:1;
  overflow:hidden;
  pointer-events:none;
  background:#202522 url('<?php echo esc_url(content_url(HARMAT_BW_POSTER)); ?>') center center/cover no-repeat;
}
#SR7_1_1 .harmat-youtube-hero iframe {
  position:absolute;
  top:50%;
  left:50%;
  border:0;
  max-width:none;
  opacity:0;
  transform:translate(-50%,-50%);
  transition:opacity .45s ease;
  pointer-events:none;
}
#SR7_1_1.harmat-youtube-playing .harmat-youtube-hero iframe {
  opacity:1;
}
#SR7_1_1.harmat-youtube-playing sr7-module-bg,
#SR7_1_1.harmat-youtube-playing sr7-bg {
  opacity:0!important;
}
#SR7_1_1 sr7-content {
  position:relative;
  z-index:2;
}
</style>
    <?php
}, 42);

add_action('wp_footer', function (): void {
    if (!harmat_bw_is_public_homepage()) {
        return;
    }
    ?>
<script id="harmat-youtube-hero-runtime">
(function () {
  "use strict";

  var videoId = <?php echo wp_json_encode(HARMAT_BW_YOUTUBE_ID); ?>;
  var moduleId = "SR7_1_1";
  var player = null;
  var frame = null;
  var started = false;
  var fallbackTimer = null;
  var revealTimer = null;

  function sizeFrame() {
    var module = document.getElementById(moduleId);
    if (!module || !frame) return;

    var width = module.clientWidth;
    var height = module.clientHeight;
    var frameWidth = width;
    var frameHeight = width * 9 / 16;

    if (frameHeight < height) {
      frameHeight = height;
      frameWidth = height * 16 / 9;
    }

    frame.style.width = Math.ceil(frameWidth) + "px";
    frame.style.height = Math.ceil(frameHeight) + "px";
  }

  function clearPlayerTimers() {
    if (fallbackTimer) window.clearTimeout(fallbackTimer);
    if (revealTimer) window.clearTimeout(revealTimer);
    fallbackTimer = null;
    revealTimer = null;
  }

  function keepPosterFallback() {
    var module = document.getElementById(moduleId);
    clearPlayerTimers();
    if (module) module.classList.remove("harmat-youtube-playing");

    window.setTimeout(function () {
      if (player && typeof player.destroy === "function") {
        try {
          player.destroy();
        } catch (error) {
          // The poster remains visible when an embedded player cannot be destroyed.
        }
      }
      player = null;
      frame = null;
    }, 0);
  }

  function revealWhenPlaying(event) {
    if (!window.YT || event.data !== window.YT.PlayerState.PLAYING) return;

    if (fallbackTimer) {
      window.clearTimeout(fallbackTimer);
      fallbackTimer = null;
    }
    if (revealTimer) window.clearTimeout(revealTimer);

    // Give adaptive playback a moment to leave its low-resolution startup stream.
    revealTimer = window.setTimeout(function () {
      if (
        !player
        || typeof player.getPlayerState !== "function"
        || player.getPlayerState() !== window.YT.PlayerState.PLAYING
      ) {
        return;
      }

      var module = document.getElementById(moduleId);
      if (module) module.classList.add("harmat-youtube-playing");
    }, 1800);
  }

  function createPlayer() {
    if (started || !window.YT || !window.YT.Player) return;
    var host = document.getElementById("harmat-youtube-player");
    if (!host) return;
    started = true;

    player = new window.YT.Player(host, {
      host: "https://www.youtube.com",
      videoId: videoId,
      playerVars: {
        autoplay: 1,
        mute: 1,
        controls: 0,
        disablekb: 1,
        fs: 0,
        iv_load_policy: 3,
        loop: 1,
        origin: window.location.origin,
        playlist: videoId,
        playsinline: 1,
        rel: 0,
        vq: "hd1080",
        widget_referrer: window.location.href
      },
      events: {
        onReady: function (event) {
          frame = event.target.getIframe();
          sizeFrame();
          event.target.mute();
          event.target.playVideo();
        },
        onStateChange: function (event) {
          revealWhenPlaying(event);
          if (window.YT && event.data === window.YT.PlayerState.ENDED) {
            event.target.seekTo(0, true);
            event.target.playVideo();
          }
        },
        onError: keepPosterFallback
      }
    });
  }

  function installPlayer() {
    var module = document.getElementById(moduleId);
    if (!module || module.querySelector(".harmat-youtube-hero")) return false;
    var layer = document.createElement("div");
    layer.className = "harmat-youtube-hero";
    layer.setAttribute("aria-hidden", "true");

    var host = document.createElement("div");
    host.id = "harmat-youtube-player";
    layer.appendChild(host);
    module.appendChild(layer);

    fallbackTimer = window.setTimeout(keepPosterFallback, 15000);

    var previousReady = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = function () {
      if (typeof previousReady === "function") previousReady();
      createPlayer();
    };

    if (window.YT && window.YT.Player) {
      createPlayer();
    } else if (!document.querySelector('script[data-harmat-youtube-api]')) {
      var api = document.createElement("script");
      api.src = "https://www.youtube.com/iframe_api";
      api.async = true;
      api.setAttribute("data-harmat-youtube-api", "1");
      document.head.appendChild(api);
    }

    if (window.ResizeObserver) {
      new ResizeObserver(sizeFrame).observe(module);
    } else {
      window.addEventListener("resize", sizeFrame, {passive:true});
    }

    document.addEventListener("visibilitychange", function () {
      if (!player) return;
      if (document.hidden) {
        player.pauseVideo();
      } else {
        player.playVideo();
      }
    });

    return true;
  }

  var attempts = 0;
  var timer = window.setInterval(function () {
    attempts += 1;
    if (installPlayer() || attempts > 120) window.clearInterval(timer);
  }, 50);
  installPlayer();
})();
</script>
    <?php
}, 99);

add_action('wp_head', function (): void {
    if (!harmat_bw_is_public_3d_video_surface()) {
        return;
    }
    ?>
<style id="harmat-youtube-3d-style">
.hi-video-card > .harmat-youtube-3d-trigger,
.hi-video-card > .harmat-youtube-3d-player,
.hi-video-card > .harmat-youtube-3d-frame {
  display:block;
  width:100%;
  aspect-ratio:16 / 9;
  margin:0;
  padding:0;
  border:0;
  border-radius:0;
  background-color:#000;
}
.hi-video-card > .harmat-youtube-3d-trigger {
  position:relative;
  overflow:hidden;
  cursor:pointer;
  background-image:url('<?php echo esc_url(content_url(HARMAT_BW_3D_POSTER)); ?>');
  background-position:center;
  background-size:cover;
}
.hi-video-card > .harmat-youtube-3d-player {
  position:relative;
  overflow:hidden;
  background-image:url('<?php echo esc_url(content_url(HARMAT_BW_3D_POSTER)); ?>');
  background-position:center;
  background-size:cover;
}
.hi-video-card > .harmat-youtube-3d-player iframe {
  position:absolute;
  inset:0;
  width:100%;
  height:100%;
  border:0;
  opacity:0;
  transition:opacity .35s ease;
}
.hi-video-card > .harmat-youtube-3d-player.harmat-youtube-3d-playing iframe {
  opacity:1;
}
.hi-video-card > .harmat-youtube-3d-trigger::before {
  content:"";
  position:absolute;
  top:50%;
  left:50%;
  width:64px;
  height:64px;
  border:1px solid rgba(255,255,255,.76);
  border-radius:50%;
  background:rgba(18,43,37,.88);
  box-shadow:0 8px 24px rgba(0,0,0,.28);
  transform:translate(-50%,-50%);
  transition:background-color .2s ease,transform .2s ease;
}
.hi-video-card > .harmat-youtube-3d-trigger::after {
  content:"";
  position:absolute;
  top:50%;
  left:50%;
  width:0;
  height:0;
  margin-left:3px;
  border-top:10px solid transparent;
  border-bottom:10px solid transparent;
  border-left:16px solid #fff;
  transform:translate(-50%,-50%);
}
.hi-video-card > .harmat-youtube-3d-trigger:hover::before,
.hi-video-card > .harmat-youtube-3d-trigger:focus-visible::before {
  background:#2e6f5e;
  transform:translate(-50%,-50%) scale(1.05);
}
.hi-video-card > .harmat-youtube-3d-trigger:focus-visible {
  outline:3px solid #fff;
  outline-offset:-3px;
}
.hi-video-card > .harmat-youtube-3d-frame {
  background:#000;
}
@media (max-width:600px) {
  .hi-video-card > .harmat-youtube-3d-trigger::before {
    width:54px;
    height:54px;
  }
}
</style>
    <?php
}, 43);

add_action('wp_footer', function (): void {
    if (!harmat_bw_is_public_3d_video_surface()) {
        return;
    }
    ?>
<script id="harmat-youtube-3d-runtime">
(function () {
  "use strict";

  var videoId = <?php echo wp_json_encode(HARMAT_BW_YOUTUBE_ID); ?>;
  var retiredPathSuffix = "/spjs" + ".mp4";

  function createTrigger() {
    var trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "harmat-youtube-3d-trigger";
    trigger.setAttribute("aria-label", "Látványvideó lejátszása");

    trigger.addEventListener("click", function () {
      var shell = document.createElement("div");
      var host = document.createElement("div");
      var player = null;
      var fallbackTimer = null;
      var revealTimer = null;

      shell.className = "harmat-youtube-3d-player";
      shell.setAttribute("aria-label", "Harmat Lakópark látványvideó");
      host.id = "harmat-youtube-3d-player-" + Math.random().toString(36).slice(2);
      shell.appendChild(host);
      trigger.replaceWith(shell);

      function clearTimers() {
        if (fallbackTimer) window.clearTimeout(fallbackTimer);
        if (revealTimer) window.clearTimeout(revealTimer);
        fallbackTimer = null;
        revealTimer = null;
      }

      function keepPoster() {
        clearTimers();
        shell.classList.remove("harmat-youtube-3d-playing");
        window.setTimeout(function () {
          if (player && typeof player.destroy === "function") {
            try {
              player.destroy();
            } catch (error) {
              // The project poster remains visible when YouTube is blocked.
            }
          }
          player = null;
        }, 0);
      }

      function createPlayer() {
        if (player || !window.YT || !window.YT.Player || !document.getElementById(host.id)) {
          return;
        }

        player = new window.YT.Player(host.id, {
          host: "https://www.youtube.com",
          videoId: videoId,
          playerVars: {
            autoplay: 1,
            controls: 1,
            origin: window.location.origin,
            playsinline: 1,
            rel: 0,
            vq: "hd1080",
            widget_referrer: window.location.href
          },
          events: {
            onReady: function (event) {
              var frame = event.target.getIframe();
              frame.className = "harmat-youtube-3d-frame";
              frame.title = "Harmat Lakópark látványvideó";
              frame.allow = "autoplay; encrypted-media; picture-in-picture; fullscreen";
              frame.allowFullscreen = true;
              event.target.playVideo();
            },
            onStateChange: function (event) {
              if (!window.YT || event.data !== window.YT.PlayerState.PLAYING) return;
              if (fallbackTimer) window.clearTimeout(fallbackTimer);
              fallbackTimer = null;
              if (revealTimer) window.clearTimeout(revealTimer);
              revealTimer = window.setTimeout(function () {
                if (
                  player
                  && typeof player.getPlayerState === "function"
                  && player.getPlayerState() === window.YT.PlayerState.PLAYING
                ) {
                  shell.classList.add("harmat-youtube-3d-playing");
                }
              }, 1200);
            },
            onError: keepPoster
          }
        });
      }

      fallbackTimer = window.setTimeout(keepPoster, 15000);

      var previousReady = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = function () {
        if (typeof previousReady === "function") previousReady();
        createPlayer();
      };

      if (window.YT && window.YT.Player) {
        createPlayer();
      } else if (!document.querySelector('script[data-harmat-youtube-api]')) {
        var api = document.createElement("script");
        api.src = "https://www.youtube.com/iframe_api";
        api.async = true;
        api.setAttribute("data-harmat-youtube-api", "1");
        document.head.appendChild(api);
      }
    }, {once:true});

    return trigger;
  }

  function replaceOriginVideos(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var videos = [];

    function isRetiredVideo(video) {
      if (!video || video.nodeName !== "VIDEO") return false;
      if (video.hasAttribute("data-harmat-youtube-replacement")) return true;

      var urls = [
        video.getAttribute("src"),
        video.getAttribute("data-src")
      ];

      video.querySelectorAll("source").forEach(function (source) {
        urls.push(source.getAttribute("src"));
        urls.push(source.getAttribute("data-src"));
      });

      return urls.some(function (url) {
        if (!url) return false;

        try {
          return new URL(url, window.location.href).pathname.toLowerCase().endsWith(retiredPathSuffix);
        } catch (error) {
          return String(url).toLowerCase().indexOf(retiredPathSuffix.slice(1)) !== -1;
        }
      });
    }

    if (scope.matches && scope.matches("video") && isRetiredVideo(scope)) {
      videos.push(scope);
    }
    scope.querySelectorAll("video").forEach(function (video) {
      if (isRetiredVideo(video)) videos.push(video);
    });

    videos.forEach(function (video) {
      try {
        video.pause();
      } catch (error) {
        // The element may not have initialized yet.
      }

      video.querySelectorAll("source").forEach(function (source) {
        source.removeAttribute("src");
        source.removeAttribute("data-src");
      });
      video.removeAttribute("src");
      video.removeAttribute("data-src");
      video.replaceWith(createTrigger());
    });
  }

  replaceOriginVideos(document);

  if (window.MutationObserver && document.body) {
    new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) replaceOriginVideos(node);
        });
      });
    }).observe(document.body, {childList:true,subtree:true});
  }
})();
</script>
    <?php
}, 100);

add_action('init', function (): void {
    $path = isset($_SERVER['REQUEST_URI'])
        ? (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH)
        : '';

    if (untrailingslashit($path) !== '/harmat-video-sitemap.xml') {
        return;
    }

    status_header(200);
    header('Content-Type: application/xml; charset=UTF-8');

    $page_url = home_url('/');
    $poster_url = content_url(HARMAT_BW_POSTER);
    $player_url = harmat_bw_youtube_embed_url(false);

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
    echo "  <url>\n";
    echo '    <loc>' . esc_url($page_url) . "</loc>\n";
    echo "    <video:video>\n";
    echo '      <video:thumbnail_loc>' . esc_url($poster_url) . "</video:thumbnail_loc>\n";
    echo "      <video:title><![CDATA[Harmat Lakópark látványvideó]]></video:title>\n";
    echo "      <video:description><![CDATA[A Harmat Lakópark új építésű lakásait, környezetét és hangulatát bemutató látványvideó.]]></video:description>\n";
    echo '      <video:player_loc>' . esc_url($player_url) . "</video:player_loc>\n";
    echo "      <video:duration>90</video:duration>\n";
    echo '      <video:publication_date>' . esc_html(HARMAT_BW_VIDEO_UPLOAD_DATE) . "</video:publication_date>\n";
    echo "      <video:family_friendly>yes</video:family_friendly>\n";
    echo "    </video:video>\n";
    echo "  </url>\n";
    echo "</urlset>\n";
    exit;
}, 0);

add_filter('wpseo_sitemap_index', function (string $index): string {
    $video_sitemap_url = esc_url(home_url('/harmat-video-sitemap.xml'));
    $pattern = '~<sitemap>\s*<loc>'
        . preg_quote($video_sitemap_url, '~')
        . '</loc>\s*<lastmod>.*?</lastmod>\s*</sitemap>~s';
    $replacement = '<sitemap><loc>' . $video_sitemap_url . '</loc><lastmod>'
        . esc_html(HARMAT_BW_VIDEO_UPLOAD_DATE)
        . '</lastmod></sitemap>';

    return (string) preg_replace($pattern, $replacement, $index, 1);
}, 999);

add_action('init', function (): void {
    if (!wp_next_scheduled('harmat_bw_hourly_check')) {
        wp_schedule_event(time() + 300, 'hourly', 'harmat_bw_hourly_check');
    }
}, 20);

add_action('init', 'harmat_bw_maybe_reset_month', 1);

function harmat_bw_maybe_reset_month(): void
{
    $current_month = current_time('Y-m');
    $stored_month = (string) get_option('harmat_bw_month_state', '');
    $last_usage = get_option('harmat_bw_last_usage', array());
    $last_month = is_array($last_usage) ? (string) ($last_usage['month'] ?? '') : '';

    if ($stored_month === '') {
        update_option('harmat_bw_month_state', $current_month, false);
        if ($last_month === '' || $last_month === $current_month) {
            return;
        }
    } elseif ($stored_month === $current_month) {
        return;
    }

    $limit_mib = max(1, (int) get_option('harmat_bw_limit_mib', HARMAT_BW_LIMIT_MIB));
    $reset_usage = array(
        'ok' => true,
        'usage_bytes' => 0,
        'usage_mib' => 0,
        'limit_mib' => $limit_mib,
        'percent' => 0,
        'threshold' => 0,
        'month' => $current_month,
        'source' => 'monthly_reset',
        'reset_at' => current_time('mysql'),
    );

    update_option('harmat_bw_last_usage', $reset_usage, false);
    update_option('harmat_bw_month_state', $current_month, false);
    delete_option('harmat_bw_notice_state');
    delete_option('harmat_bw_archived_usage_cache');
}

add_action('harmat_bw_hourly_check', 'harmat_bw_collect_bandwidth');

function harmat_bw_collect_archived_http_bytes(): array
{
    $month_suffix = gmdate('M-Y');
    $files = glob('/home/harmath2/logs/*-' . $month_suffix . '.gz');
    $files = is_array($files)
        ? array_values(
            array_filter(
                $files,
                static function (string $file): bool {
                    return strpos(basename($file), '-ftp_log-') === false;
                }
            )
        )
        : array();

    if (empty($files)) {
        return array(
            'ok' => false,
            'error' => 'bandwidth_archives_missing',
        );
    }

    sort($files);
    $signature_parts = array();
    $latest_mtime = 0;
    foreach ($files as $file) {
        $mtime = (int) filemtime($file);
        $signature_parts[] = $file . ':' . (int) filesize($file) . ':' . $mtime;
        $latest_mtime = max($latest_mtime, $mtime);
    }
    $signature = hash('sha256', implode('|', $signature_parts));
    $cached = get_option('harmat_bw_archived_usage_cache', array());

    if (
        is_array($cached)
        && ($cached['signature'] ?? '') === $signature
        && isset($cached['usage_bytes'])
    ) {
        return array(
            'ok' => true,
            'usage_bytes' => (int) $cached['usage_bytes'],
            'month' => gmdate('Y-m'),
            'source' => 'apache_archives_cached',
            'archive_files' => count($files),
            'archive_updated_at' => $latest_mtime ? gmdate('c', $latest_mtime) : null,
        );
    }

    $usage_bytes = 0;
    foreach ($files as $file) {
        $handle = gzopen($file, 'rb');
        if ($handle === false) {
            continue;
        }

        while (!gzeof($handle)) {
            $line = gzgets($handle);
            if (
                is_string($line)
                && preg_match('~"\s+\d{3}\s+(\d+|-)~', $line, $match)
                && $match[1] !== '-'
            ) {
                $usage_bytes += (int) $match[1];
            }
        }
        gzclose($handle);
    }

    update_option(
        'harmat_bw_archived_usage_cache',
        array(
            'signature' => $signature,
            'usage_bytes' => $usage_bytes,
            'updated_at' => current_time('mysql'),
        ),
        false
    );

    return array(
        'ok' => true,
        'usage_bytes' => $usage_bytes,
        'month' => gmdate('Y-m'),
        'source' => 'apache_archives',
        'archive_files' => count($files),
        'archive_updated_at' => $latest_mtime ? gmdate('c', $latest_mtime) : null,
    );
}

function harmat_bw_collect_bandwidth(bool $send_alert = true): array
{
    $result = array(
        'ok' => false,
        'usage_bytes' => 0,
        'percent' => 0,
        'threshold' => 0,
    );

    if (!function_exists('shell_exec')) {
        $result['error'] = 'shell_exec_unavailable';
        return $result;
    }

    $json = shell_exec('/usr/local/cpanel/bin/uapi --output=json Stats get_bandwidth 2>/dev/null');
    $decoded = is_string($json) ? json_decode($json, true) : null;
    $rows = is_array($decoded) && isset($decoded['result']['data']) && is_array($decoded['result']['data'])
        ? $decoded['result']['data']
        : array();

    $current = null;
    foreach ($rows as $row) {
        if (
            ($row['domain'] ?? '') !== 'harmat22.hu'
            || ($row['protocol'] ?? '') !== 'http'
        ) {
            continue;
        }

        if ($current === null || (int) $row['month_start'] > (int) $current['month_start']) {
            $current = $row;
        }
    }

    if ($current !== null) {
        $usage_bytes = (int) $current['bytes'];
        $month_key = wp_date('Y-m', (int) $current['month_start']);
        $source = 'cpanel_uapi';
        $archive_data = array();
    } else {
        $archive_data = harmat_bw_collect_archived_http_bytes();
        if (!($archive_data['ok'] ?? false)) {
            $result['error'] = $archive_data['error'] ?? 'bandwidth_data_missing';
            return $result;
        }

        $usage_bytes = (int) $archive_data['usage_bytes'];
        $month_key = (string) $archive_data['month'];
        $source = (string) $archive_data['source'];
    }

    $limit_mib = max(1, (int) get_option('harmat_bw_limit_mib', HARMAT_BW_LIMIT_MIB));
    $limit_bytes = $limit_mib * 1024 * 1024;
    $percent = round(($usage_bytes / $limit_bytes) * 100, 1);
    $threshold = 0;

    foreach (array(50, 70, 85, 95) as $candidate) {
        if ($percent >= $candidate) {
            $threshold = $candidate;
        }
    }

    $result = array(
        'ok' => true,
        'usage_bytes' => $usage_bytes,
        'usage_mib' => round($usage_bytes / 1024 / 1024, 2),
        'limit_mib' => $limit_mib,
        'percent' => $percent,
        'threshold' => $threshold,
        'month' => $month_key,
        'source' => $source,
    );
    if (!empty($archive_data)) {
        $result['archive_files'] = (int) ($archive_data['archive_files'] ?? 0);
        $result['archive_updated_at'] = $archive_data['archive_updated_at'] ?? null;
    }
    update_option('harmat_bw_last_usage', $result, false);

    if (!$send_alert || $threshold === 0) {
        return $result;
    }

    $notice = get_option('harmat_bw_notice_state', array());
    $notified_threshold = (
        is_array($notice)
        && ($notice['month'] ?? '') === $month_key
    ) ? (int) ($notice['threshold'] ?? 0) : 0;

    if ($threshold <= $notified_threshold) {
        return $result;
    }

    $subject = sprintf('[Harmat22] Tárhelyforgalom: %.1f%%', $percent);
    $message = sprintf(
        "A harmat22.hu havi HTTP-forgalma elérte a(z) %.1f%%-ot.\n\nFelhasználás: %.2f MB\nKeret: %d MB\nKüszöb: %d%%\n\nA kezdőlapi videó YouTube-ról töltődik, ezért nem terheli a tárhely adatforgalmát. Ellenőrizze a további nagyméretű médiafájlokat és a hozzáférési naplókat.",
        $percent,
        $result['usage_mib'],
        $limit_mib,
        $threshold
    );

    if (wp_mail('ertekesites@harmat22.hu', $subject, $message)) {
        update_option(
            'harmat_bw_notice_state',
            array(
                'month' => $month_key,
                'threshold' => $threshold,
                'sent_at' => current_time('mysql'),
            ),
            false
        );
        $result['alert_sent'] = true;
    } else {
        $result['alert_sent'] = false;
    }

    return $result;
}
