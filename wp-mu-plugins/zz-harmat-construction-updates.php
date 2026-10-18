<?php
/**
 * Plugin Name: Harmat Construction Updates
 * Description: Removable, read-only construction registry shared by the journal and homepage.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

const HARMAT_CU_MEDIA_BASE = 'https://harmat22.hu/wp-content/uploads/2026/10/construction-updates/';

function harmat_cu_registry(): array {
    $records = array(array(
        'id' => '2026-10-13', 'date' => '2026-10-13', 'uploaded' => '2026-10-18',
        'status' => 'published', 'approved' => true,
        'file' => '2026-10-13-compilation-hu-subtitled', 'duration' => 89.3,
        'title' => array('hu' => 'Építési napló – 2026. október 13.', 'zh' => '施工日志 · 2026年10月13日'),
        'summary' => array(
            'hu' => 'A1 és A3: vasbeton szerkezetek és zsaluzatok. A2: a pince feletti födém betonozása. A4 és A2: szerkezetépítés, majd helyszíni körkép.',
            'zh' => 'A1与A3的钢筋混凝土结构及模板施工，A2地下室顶板混凝土浇筑，A4与A2的结构施工，以及现场全景。'),
        'note' => array(
            'hu' => 'Valódi helyszíni felvételek, a felvétel napjának állapotával. Magyar felirat és szintetikus magyar narráció; az eredeti helyszíni hang nélkül.',
            'zh' => '真实现场影像，反映拍摄当日状态。配有匈牙利语字幕和合成旁白，已去除现场原声。'),
    ));
    foreach (array(30.28, 35.59, 32.87, 27.16) as $index => $duration) {
        $number = sprintf('%02d', $index + 1);
        $records[] = array(
            'id' => '2026-10-12-site-' . $number, 'date' => '2026-10-12', 'uploaded' => '2026-10-18',
            'status' => 'published', 'approved' => true, 'file' => 'latest-site-' . $number, 'duration' => $duration,
            'title' => array('hu' => 'Helyszíni felvétel – 2026. október 12. · ' . ($index + 1), 'zh' => '2026年10月12日现场记录 · ' . ($index + 1)),
            'summary' => array('hu' => 'A Harmat Lakópark építési területének október 12-i felvétele.', 'zh' => 'Harmat Lakópark项目10月12日的施工现场影像。'),
            'note' => array('hu' => 'A felvétel az akkori helyszíni állapotot mutatja, nem elkészült otthonokat.', 'zh' => '视频记录拍摄时的施工状态，并非已竣工住宅。'),
        );
    }
    return $records;
}

function harmat_cu_date($date): bool {
    if (!is_string($date) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $date)) return false;
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Budapest'));
    return $parsed && $parsed->format('Y-m-d') === $date;
}

// Local stat only: never make public requests or load MP4 bytes during a page request.
function harmat_cu_media_ready(string $basename): bool {
    if (!defined('WP_CONTENT_DIR') || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $basename) || strlen($basename) > 100) return false;
    $directory = WP_CONTENT_DIR . '/uploads/2026/10/construction-updates';
    $real = realpath($directory);
    if (!$real || is_link($directory) || !is_dir($real)) return false;
    foreach (array('.mp4', '.jpg') as $extension) {
        $path = $directory . '/' . $basename . $extension;
        if (is_link($path) || !is_file($path) || !is_readable($path) || filesize($path) <= 0
            || realpath($path) !== $real . DIRECTORY_SEPARATOR . $basename . $extension) return false;
    }
    return true;
}

function harmat_cu_records(?array $candidates = null, ?string $today = null, ?callable $available = null): array {
    $today = $today ?? (new DateTimeImmutable('now', new DateTimeZone('Europe/Budapest')))->format('Y-m-d');
    if (!harmat_cu_date($today)) return array();
    $available = $available ?? static function ($basename) { return harmat_cu_media_ready($basename); };
    $valid = array(); $seen = array();
    foreach ($candidates ?? harmat_cu_registry() as $record) {
        if (!is_array($record) || ($record['approved'] ?? null) !== true || ($record['status'] ?? '') !== 'published'
            || !harmat_cu_date($record['date'] ?? null) || !harmat_cu_date($record['uploaded'] ?? null)
            || $record['date'] > $today || $record['uploaded'] > $today || $record['uploaded'] < $record['date']
            || !is_string($record['id'] ?? null) || !preg_match('/\A\d{4}-\d{2}-\d{2}(?:-site-\d{2})?\z/D', $record['id'])
            || strpos($record['id'], $record['date']) !== 0 || isset($seen[$record['id']])
            || !is_string($record['file'] ?? null) || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $record['file'])
            || strlen($record['file']) > 100 || isset($seen['file:' . $record['file']])
            || !is_numeric($record['duration'] ?? null) || !is_finite((float)$record['duration'])
            || $record['duration'] <= 0 || $record['duration'] > 3600) continue;
        $copy_ok = true;
        foreach (array('title', 'summary', 'note') as $field) {
            if (!is_array($record[$field] ?? null)) { $copy_ok = false; continue; }
            foreach (array('hu', 'zh') as $language) {
                $text = $record[$field][$language] ?? null;
                if (!is_string($text) || trim($text) === '' || strlen($text) > 2000
                    || preg_match('//u', $text) !== 1) $copy_ok = false;
            }
        }
        if (!$copy_ok || !$available($record['file'])) continue;
        $seen[$record['id']] = true; $seen['file:' . $record['file']] = true;
        $record['video'] = HARMAT_CU_MEDIA_BASE . $record['file'] . '.mp4';
        $record['poster'] = HARMAT_CU_MEDIA_BASE . $record['file'] . '.jpg';
        $valid[] = $record;
    }
    usort($valid, static function ($a, $b) { return strcmp($b['date'], $a['date']) ?: strcmp($a['id'], $b['id']); });
    return $valid;
}

function harmat_cu_context(string $page = 'epitesi-naplo'): bool {
    foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST') as $flag) {
        if (defined($flag) && constant($flag)) return false;
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'), true)
        || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_preview() || is_404()
        || is_search() || is_embed() || is_trackback() || !is_main_query()
        || (function_exists('wp_is_json_request') && wp_is_json_request()) || isset($_GET['elementor-preview'])
        || !is_page($page) || get_post_status(get_queried_object_id()) !== 'publish' || post_password_required()) return false;
    return true;
}

function harmat_cu_text(string $tag, array $copy, string $attributes = ''): string {
    return '<' . $tag . ' data-hcu-hu="' . esc_attr($copy['hu']) . '" data-hcu-zh="' . esc_attr($copy['zh']) . '"' . $attributes . '>'
        . esc_html($copy['hu']) . '</' . $tag . '>';
}

function harmat_cu_date_label(string $date): array {
    list($year, $month, $day) = explode('-', $date);
    $months = array('január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december');
    return array('hu' => $year . '. ' . $months[(int)$month - 1] . ' ' . (int)$day . '.', 'zh' => $year . '年' . (int)$month . '月' . (int)$day . '日');
}

function harmat_cu_player(array $record): string {
    $play = array('hu' => 'Videó lejátszása', 'zh' => '播放视频');
    return '<div class="hcu-player" data-hcu-player data-hcu-video="' . esc_url($record['video']) . '" data-hcu-poster="' . esc_url($record['poster'])
        . '" data-hcu-title-hu="' . esc_attr($record['title']['hu']) . '" data-hcu-title-zh="' . esc_attr($record['title']['zh']) . '">'
        . '<button class="hcu-trigger" type="button" data-hcu-play data-hcu-label-hu="' . esc_attr($play['hu']) . '" data-hcu-label-zh="' . esc_attr($play['zh']) . '" aria-label="' . esc_attr($play['hu']) . '">'
        . '<img src="' . esc_url($record['poster']) . '" width="1280" height="720" alt="' . esc_attr($record['title']['hu']) . '" data-hcu-alt-hu="' . esc_attr($record['title']['hu']) . '" data-hcu-alt-zh="' . esc_attr($record['title']['zh']) . '" loading="lazy" decoding="async">'
        . '<span class="hcu-play-icon" aria-hidden="true">&#9654;</span></button></div>'
        . '<p class="hcu-status" data-hcu-status role="status" aria-live="polite"></p>'
        . '<a class="hcu-direct" href="' . esc_url($record['video']) . '" target="_blank" rel="noopener noreferrer">'
        . harmat_cu_text('span', array('hu' => 'Videó megnyitása külön lapon', 'zh' => '在新标签页打开视频')) . '</a>';
}

function harmat_cu_journal_markup(array $records): string {
    if (!$records) return '';
    $html = '<section id="harmat-construction-updates" class="hcu-updates" data-hcu-root data-hcu-journal="1" data-language-protect="" lang="hu" aria-labelledby="hcu-latest-title">'
        . harmat_cu_text('h2', array('hu' => 'Friss helyszíni videók', 'zh' => '最新施工影像'), ' id="hcu-latest-title"');
    foreach ($records as $index => $record) {
        $html .= '<article class="hcu-entry" id="construction-' . esc_attr($record['id']) . '" data-hcu-date="' . esc_attr($record['date']) . '">'
            . harmat_cu_text('time', harmat_cu_date_label($record['date']), ' datetime="' . esc_attr($record['date']) . '"')
            . harmat_cu_text('h3', $record['title']) . harmat_cu_text('p', $record['summary'])
            . harmat_cu_player($record) . harmat_cu_text('p', $record['note'], ' class="hcu-note"') . '</article>';
    }
    return $html . '</section>';
}

// Only replace the exact manifest-pinned old journal fragment, never native form HTML.
function harmat_cu_home_teaser($html) {
    if (!is_string($html) || !($records = harmat_cu_records()) || strpos($html, 'data-hcu-home') !== false) return $html;
    $pattern = '~    <section class="hhf-section hhf-journal"[^>]*>[\s\S]*?    </section>~';
    if (preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE) !== 1) return $html;
    $old = $matches[0][0][0];
    if (hash('sha256', str_replace("\r\n", "\n", $old)) !== '0007ce4444a48ceaa0338891e1fa790a78cdf2b54324b310593e2355c722a061') return $html;
    $record = $records[0];
    $replacement = '<section class="hhf-section hhf-journal" id="hhf-epitesi-naplo" aria-labelledby="hhf-journal-title" data-hcu-root data-hcu-home="1" data-language-protect="" lang="hu">'
        . '<div class="hhf-container hhf-journal-grid"><div class="hcu-home-media">' . harmat_cu_player($record) . '</div><div class="hhf-journal-copy">'
        . harmat_cu_text('p', array('hu' => 'Építési napló', 'zh' => '施工日志'), ' class="hhf-eyebrow"')
        . harmat_cu_text('time', harmat_cu_date_label($record['date']), ' class="hhf-journal-date" datetime="' . esc_attr($record['date']) . '"')
        . harmat_cu_text('h2', $record['title'], ' id="hhf-journal-title"') . harmat_cu_text('p', $record['summary'])
        . '<a class="hhf-text-link" href="https://harmat22.hu/epitesi-naplo/#construction-' . esc_attr($record['id']) . '">'
        . harmat_cu_text('span', array('hu' => 'Összes építési hír', 'zh' => '查看全部施工动态')) . ' <span aria-hidden="true">&#8594;</span></a>'
        . harmat_cu_text('p', $record['note'], ' class="hcu-note"') . '</div></div></section>';
    return substr_replace($html, $replacement, $matches[0][0][1], strlen($old));
}

function harmat_cu_styles(): string {
    return <<<'CSS'
<style id="harmat-construction-updates-css">
[data-hcu-root]{color:#35463c;font:16px/1.7 Arial,Helvetica,sans-serif;min-width:0;overflow-wrap:anywhere;letter-spacing:0}
[data-hcu-root] *{box-sizing:border-box;letter-spacing:0}
[data-hcu-root].hcu-updates{margin:28px 0 44px;padding:28px;background:#edf2ef}
[data-hcu-root] h2,[data-hcu-root] h3{padding:0;margin:0 0 14px;text-transform:none;letter-spacing:0;color:#244537;font:600 30px/1.3 Georgia,serif}
[data-hcu-root] h3{font-size:25px}
#harmat-construction-updates h2,#harmat-construction-updates h3{padding:0;margin:0 0 14px;text-transform:none;letter-spacing:0;color:#244537;font:600 30px/1.3 Georgia,serif}
#harmat-construction-updates h3{font-size:25px}
[data-hcu-root] p{padding:0;margin:0 0 16px;font-size:16px;line-height:1.7}
[data-hcu-root] time{display:block;margin-bottom:8px;font-size:14px;color:#47574e}
[data-hcu-root] .hcu-entry{max-width:960px;margin:28px auto 0;padding:28px 0 0;border-top:1px solid #cbd7ce;scroll-margin-top:100px}
[data-hcu-root] .hcu-player{position:relative;aspect-ratio:16/9;width:100%;background:#14241c;isolation:isolate}
[data-hcu-root] .hcu-trigger{position:absolute;inset:0;display:block;width:100%;height:100%;margin:0;padding:0;border:0;border-radius:0;background:#14241c;color:#fff;cursor:pointer}
[data-hcu-root] .hcu-trigger img,[data-hcu-root] .hcu-player video{display:block;width:100%;height:100%;max-width:100%;object-fit:contain;object-position:center;margin:0;border:0}
[data-hcu-root] .hcu-player video{position:absolute;inset:0}
[data-hcu-root] .hcu-play-icon{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);display:grid;place-items:center;width:60px;height:60px;border:2px solid #fff;border-radius:50%;background:#244537e6;font:24px/1 Arial,sans-serif;padding-left:4px}
[data-hcu-root] .hcu-trigger:focus-visible,[data-hcu-root] video:focus-visible,[data-hcu-root] a:focus-visible{outline:3px solid #917b44;outline-offset:4px}
[data-hcu-root] .hcu-status:empty{display:none}
[data-hcu-root] .hcu-status{margin-top:8px;color:#71381d}
[data-hcu-root] .hcu-direct{display:inline-block;padding:8px 0;font-size:14px;color:#244537;text-decoration:underline;text-underline-offset:3px}
[data-hcu-root] p.hcu-note{margin-top:10px;font-size:14px;color:#47574e;line-height:1.65}
body.home.page-id-164 #harmat-home-family [data-hcu-home] .hhf-journal-grid{grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);align-items:center}
body.home.page-id-164 #harmat-home-family [data-hcu-home] .hcu-home-media{min-width:0}
body.home.page-id-164 #harmat-home-family [data-hcu-home] h2{font-size:30px;margin-top:12px}
@media(max-width:900px){body.home.page-id-164 #harmat-home-family [data-hcu-home] .hhf-journal-grid{grid-template-columns:1fr}}
@media(max-width:620px){[data-hcu-root].hcu-updates{padding:20px 14px;margin-top:20px}[data-hcu-root] h2,#harmat-construction-updates h2,body.home.page-id-164 #harmat-home-family [data-hcu-home] h2{font-size:26px}[data-hcu-root] h3,#harmat-construction-updates h3{font-size:23px}[data-hcu-root] .hcu-entry{padding-top:22px;margin-top:22px}[data-hcu-root] .hcu-play-icon{width:48px;height:48px;font-size:21px}}
</style>
CSS;
}

function harmat_cu_runtime(): string {
    return <<<'JS'
<script id="harmat-construction-updates-runtime">
(function(){
  'use strict';
  function init(){
    var roots=Array.from(document.querySelectorAll('[data-hcu-root]'));
    if(!roots.length||window.harmatConstructionUpdatesReady)return;
    window.harmatConstructionUpdatesReady=true;
    var language=null;
    var messages={error:{hu:'A videó nem tölthető be. Megnyithatja külön lapon az alábbi linken.',zh:'视频暂时无法加载，可使用下方链接在新标签页打开。'},paused:{hu:'A lejátszáshoz használja a videó vezérlőit.',zh:'请使用视频控件开始播放。'}};
    function sync(){
      var next=document.documentElement.getAttribute('data-harmat-language')==='zh-Hans'?'zh':'hu';
      if(next===language)return;
      language=next;
      roots.forEach(function(root){
        root.lang=language==='zh'?'zh-Hans':'hu';
        root.querySelectorAll('[data-hcu-hu]').forEach(function(node){node.textContent=node.getAttribute('data-hcu-'+language);});
        root.querySelectorAll('[data-hcu-label-hu]').forEach(function(node){node.setAttribute('aria-label',node.getAttribute('data-hcu-label-'+language));});
        root.querySelectorAll('[data-hcu-alt-hu]').forEach(function(node){node.alt=node.getAttribute('data-hcu-alt-'+language);});
        root.querySelectorAll('[data-hcu-player]').forEach(function(player){var video=player.querySelector('video');if(video)video.setAttribute('aria-label',player.getAttribute('data-hcu-title-'+language));});
        root.querySelectorAll('[data-hcu-status]').forEach(function(node){if(messages[node.dataset.hcuMessage])node.textContent=messages[node.dataset.hcuMessage][language];});
      });
    }
    function pauseOthers(current){roots.forEach(function(root){root.querySelectorAll('[data-hcu-player] video').forEach(function(video){if(video!==current&&!video.paused)video.pause();});});}
    roots.forEach(function(root){
      root.querySelectorAll('[data-hcu-play]').forEach(function(button){
        button.addEventListener('click',function(){
          var player=button.closest('[data-hcu-player]');
          if(!player||player.querySelector('video'))return;
          var url=player.getAttribute('data-hcu-video');
          if(!/^https:\/\/harmat22\.hu\/wp-content\/uploads\/2026\/10\/construction-updates\/[a-z0-9]+(?:-[a-z0-9]+)*\.mp4$/.test(url||''))return;
          var video=document.createElement('video');
          var status=player.nextElementSibling;
          function message(key){if(status&&status.hasAttribute('data-hcu-status')){status.dataset.hcuMessage=key;status.textContent=messages[key][language];}}
          video.controls=true;video.playsInline=true;video.preload='none';video.muted=true;video.defaultMuted=true;
          video.poster=player.getAttribute('data-hcu-poster');video.setAttribute('tabindex','0');
          video.setAttribute('aria-label',player.getAttribute('data-hcu-title-'+language));
          video.addEventListener('play',function(){pauseOthers(video);if(status){delete status.dataset.hcuMessage;status.textContent='';}});
          video.addEventListener('error',function(){message('error');});
          video.src=url;button.replaceWith(video);pauseOthers(video);video.focus();
          try{var pending=video.play();if(pending&&pending.catch)pending.catch(function(){message('paused');});}catch(error){message('paused');}
        },{once:true});
      });
    });
    sync();
    new MutationObserver(sync).observe(document.documentElement,{attributes:true,attributeFilter:['data-harmat-language']});
    document.addEventListener('visibilitychange',function(){if(document.hidden)pauseOthers(null);});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
</script>
JS;
}

function harmat_cu_resources(): string { return harmat_cu_styles() . harmat_cu_runtime(); }

function harmat_cu_journal_output($html) {
    if (!is_string($html) || !harmat_cu_context() || !($records = harmat_cu_records())
        || (http_response_code() && http_response_code() !== 200)
        || !preg_match('~\A\s*<!doctype html>~i', $html) || !preg_match('~</html>\s*\z~i', $html)
        || strpos($html, 'data-hcu-journal=') !== false || strpos($html, 'id="harmat-construction-updates"') !== false || substr_count($html, '</head>') !== 1
        || substr_count($html, '</body>') !== 1 || substr_count($html, '</main>') !== 1) return $html;
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) return $html;
    }
    $anchor = '<section class="harmat-construction-september" data-harmat-construction-september="1" aria-labelledby="harmat-construction-september-title">';
    $archive = '<section class="harmat-build-log-list">';
    if (substr_count($html, $anchor) !== 1 || substr_count($html, $archive) !== 1 || !class_exists('DOMDocument')) return $html;
    $doc = new DOMDocument();
    if (!$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) return $html;
    foreach (array('html', 'head', 'body', 'main') as $tag) if ($doc->getElementsByTagName($tag)->length !== 1) return $html;
    $main = $doc->getElementsByTagName('main')->item(0);
    if ($main->getAttribute('id') !== 'main') return $html;
    $xpath = new DOMXPath($doc);
    if ($xpath->query('//main[@id="main"]//section[@data-harmat-construction-september="1"]')->length !== 1
        || $xpath->query('//main[@id="main"]//section[@class="harmat-build-log-list"]')->length !== 1) return $html;
    $start = strpos($html, $anchor);
    if ($start <= strpos($html, '</head>') || $start >= strpos($html, $archive) || strpos($html, $archive) >= strpos($html, '</main>')) return $html;
    // Preserve every legacy byte; the buffer is outer to the legacy -100 injector.
    $result = substr_replace($html, harmat_cu_journal_markup($records), $start, 0);
    $result = str_replace('</head>', harmat_cu_styles() . '</head>', $result);
    return str_replace('</body>', harmat_cu_runtime() . '</body>', $result);
}

function harmat_cu_buffer(): void {
    if (!harmat_cu_context() || !harmat_cu_records() || headers_sent()) return;
    $intact = true;
    ob_start(static function ($html, $phase) use (&$intact) {
        if (!($phase & PHP_OUTPUT_HANDLER_FINAL) || ($phase & (PHP_OUTPUT_HANDLER_CLEAN | PHP_OUTPUT_HANDLER_FLUSH))) $intact = false;
        return $intact ? harmat_cu_journal_output($html) : $html;
    });
}
add_action('template_redirect', 'harmat_cu_buffer', -101);

function harmat_cu_description($original) {
    if (!harmat_cu_context() || !($records = harmat_cu_records())) return $original;
    return 'A Harmat Lakópark építési naplója. ' . $records[0]['title']['hu'] . ' ' . $records[0]['summary']['hu'] . ' Korábbi videók és fotók időrendben.';
}
foreach (array('wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description') as $filter) add_filter($filter, 'harmat_cu_description', 120);
function harmat_cu_share_image($original) {
    return harmat_cu_context() && ($records = harmat_cu_records()) ? $records[0]['poster'] : $original;
}
foreach (array('wpseo_opengraph_image', 'wpseo_twitter_image') as $filter) add_filter($filter, 'harmat_cu_share_image', 120);

function harmat_cu_schema($graph) {
    if (!is_array($graph) || !harmat_cu_context()) return $graph;
    $ids = array_column($graph, '@id');
    foreach (harmat_cu_records() as $record) {
        $id = 'https://harmat22.hu/epitesi-naplo/#construction-' . $record['id'];
        if (in_array($id, $ids, true)) continue;
        $graph[] = array('@type' => 'VideoObject', '@id' => $id, 'name' => $record['title']['hu'],
            'description' => $record['summary']['hu'] . ' ' . $record['note']['hu'], 'thumbnailUrl' => $record['poster'],
            'contentUrl' => $record['video'], 'uploadDate' => $record['uploaded'], 'dateCreated' => $record['date'],
            'duration' => 'PT' . rtrim(rtrim(number_format((float)$record['duration'], 2, '.', ''), '0'), '.') . 'S',
            'inLanguage' => 'hu-HU', 'isPartOf' => array('@id' => 'https://harmat22.hu/epitesi-naplo/'),
            'publisher' => array('@id' => 'https://harmat22.hu/#organization'));
    }
    return $graph;
}
add_filter('wpseo_schema_graph', 'harmat_cu_schema', 120);
