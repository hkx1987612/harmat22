<?php
/**
 * Plugin Name: Harmat App Portal
 * Description: PWA-style portal app entry for Harmat Lakópark 22.
 * Version: 0.5.4
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('template_redirect', 'harmat_app_portal_route_20260610', 0);

function harmat_app_portal_route_20260610(): void
{
    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $path = (string) parse_url($request_uri, PHP_URL_PATH);
    $path = '/' . trim($path, '/');

    if ($path === '/app') {
        harmat_app_portal_render_20260610(false);
        exit;
    }

    if ($path === '/app/demo') {
        harmat_app_portal_render_20260610(true);
        exit;
    }

    if ($path === '/app/floorplans') {
        harmat_app_portal_render_floorplans_20260610();
        exit;
    }

    if ($path === '/app/manifest.webmanifest') {
        harmat_app_portal_manifest_20260610();
        exit;
    }

    if ($path === '/app/sw.js') {
        harmat_app_portal_service_worker_20260610();
        exit;
    }

    if ($path === '/app/icon.svg') {
        harmat_app_portal_icon_20260610();
        exit;
    }
}

function harmat_app_portal_lang_20260610(): string
{
    $raw = isset($_GET['wp_lang']) ? sanitize_text_field(wp_unslash($_GET['wp_lang'])) : '';
    return $raw === 'zh_CN' ? 'zh_CN' : 'hu_HU';
}

function harmat_app_portal_is_android_app_20260610(): bool
{
    $source = isset($_GET['source']) ? sanitize_text_field(wp_unslash($_GET['source'])) : '';
    return $source === 'android_app';
}

function harmat_app_portal_private_headers_20260610(string $content_type): void
{
    status_header(200);
    header('Content-Type: ' . $content_type . '; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow', true);
    header('X-Content-Type-Options: nosniff', true);
}

function harmat_app_portal_url_20260610(string $path): string
{
    return esc_url(home_url($path));
}

function harmat_app_portal_app_url_20260610(string $path, string $lang, bool $is_android_app = false): string
{
    $path = add_query_arg('wp_lang', $lang, $path);
    if ($is_android_app) {
        $path = add_query_arg('source', 'android_app', $path);
    }
    return harmat_app_portal_url_20260610($path);
}

function harmat_app_portal_sales_area_20260610(int $post_id): float
{
    $override = get_post_meta($post_id, '_harmat_sales_area', true);
    if ($override !== '') {
        return (float) $override;
    }

    $building = (float) get_post_meta($post_id, 'property_building_area', true);
    $outdoor = (float) get_post_meta($post_id, 'property_land_area', true);
    return $building + $outdoor;
}

function harmat_app_portal_floorplan_image_20260610(string $title, string $floorplan_url = ''): string
{
    if (function_exists('hm_migrated_property_floorplan_image_from_uploads')) {
        $image = (string) hm_migrated_property_floorplan_image_from_uploads($title, $floorplan_url);
        if ($image !== '') {
            return $image;
        }
    }

    $upload = wp_upload_dir();
    $base = trailingslashit((string) ($upload['baseurl'] ?? content_url('/uploads'))) . '2026/05/';
    $candidates = [
        $title . '-cn-floorplan-display.jpg',
        strtoupper($title) . '-cn-floorplan-display.jpg',
        strtolower($title) . '-cn-floorplan-display.jpg',
        $title . '-cn-floorplan.jpg',
        strtoupper($title) . '-cn-floorplan.jpg',
        strtolower($title) . '-cn-floorplan.jpg',
    ];

    return $base . rawurlencode($candidates[0]);
}

function harmat_app_portal_status_label_20260610(int $post_id): string
{
    if (function_exists('hm_migrated_property_status_label')) {
        $status = hm_migrated_property_status_label($post_id);
        if (is_array($status) && !empty($status[0])) {
            return (string) $status[0];
        }
    }

    $raw = (string) get_post_meta($post_id, 'property_status', true);
    if ($raw === 'sold') {
        return 'Eladva';
    }
    if ($raw === 'reserved') {
        return 'Foglalva';
    }
    return 'Elérhető';
}

function harmat_app_portal_floorplans_20260610(): array
{
    $ids = get_posts([
        'post_type' => 'property',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);

    $items = [];
    foreach ($ids as $post_id) {
        $post_id = (int) $post_id;
        $title = get_the_title($post_id);
        if ($title === '') {
            continue;
        }

        $rooms = (string) get_post_meta($post_id, 'property_rooms', true);
        $bedrooms = (string) get_post_meta($post_id, 'property_bedrooms', true);
        $floor_raw = get_post_meta($post_id, 'property_floor', true);
        $floor = function_exists('hm_migrated_property_floor_label') ? (string) hm_migrated_property_floor_label($title, $floor_raw) : (string) $floor_raw;
        $area = harmat_app_portal_sales_area_20260610($post_id);
        $floorplan_url = (string) get_post_meta($post_id, 'property_floorplan', true);
        $image = harmat_app_portal_floorplan_image_20260610($title, $floorplan_url);

        $items[] = [
            'id' => $post_id,
            'title' => $title,
            'rooms' => $rooms,
            'bedrooms' => $bedrooms,
            'floor' => $floor,
            'area' => $area,
            'status' => harmat_app_portal_status_label_20260610($post_id),
            'image' => $image,
            'url' => get_permalink($post_id),
        ];
    }

    usort($items, static function ($a, $b) {
        return strnatcasecmp((string) $a['title'], (string) $b['title']);
    });

    return $items;
}

function harmat_app_portal_format_area_20260610(float $area): string
{
    if ($area <= 0) {
        return '';
    }
    return number_format($area, 2, ',', ' ') . ' m²';
}

function harmat_app_portal_text_20260610(string $lang): array
{
    $copy = [
        'hu_HU' => [
            'html_lang' => 'hu',
            'brand' => 'Harmat Lakópark 22',
            'portal' => 'Portál app',
            'eyebrow' => '1105 Budapest, Harmat utca 22.',
            'title' => 'Otthon, ahol jó megérkezni',
            'subtitle' => 'A Harmat utca 22. új lakóparkja nyugodt mindennapokat, átgondolt alaprajzokat és kényelmes városi kapcsolatokat kínál Budapest X. kerületében.',
            'install' => 'Telepítés a telefonra',
            'open_demo' => 'Demo portál megnyitása',
            'language' => '中文',
            'language_href' => '/app/?wp_lang=zh_CN',
            'demo_language_href' => '/app/demo/?wp_lang=zh_CN',
            'continue' => 'Folytatás a portálon',
            'section_tools' => 'Gyors műveletek',
            'section_roles' => 'Belépési pontok szerepkör szerint',
            'section_demo' => 'App-mód előnézet',
            'section_demo_text' => 'A demo nézet megmutatja, hogyan áll majd össze a személyes ügyfélmappa: kedvencek, státusz, dokumentumok és üzenetek.',
            'offline_floorplans_title' => 'Offline alaprajztár',
            'offline_floorplans_heading' => 'Alaprajzok előkészítve a telefonra',
            'offline_floorplans_body' => 'A portál külön, mobilra rendezett alaprajznézetet kap. A megnyitott alaprajzok gyorsítótárba kerülnek, így gyenge hálózat vagy offline helyzetben is visszanézhetők.',
            'offline_floorplans_cta' => 'Alaprajztár megnyitása',
            'offline_floorplans_note' => 'Tipp: nyisd meg a fontos alaprajzokat egyszer online, utána az app gyorsítótárból is vissza tudja adni őket.',
            'floorplans_title' => 'Offline alaprajztár',
            'floorplans_subtitle' => 'Mobilra rendezett Harmat 22 alaprajzok. Online megnyitás után a képek az app cache-be kerülnek, így később offline is visszanézhetők.',
            'floorplans_back' => 'Vissza a portálra',
            'floorplans_all' => 'Mind',
            'floorplans_open_property' => 'Lakás adatlap',
            'floorplans_cache_hint' => 'Offline előkészítés: az app a háttérben menti az alaprajzokat, amíg ez az oldal nyitva van.',
            'floorplans_cache_progress' => 'Alaprajzok mentése offline használatra',
            'floorplans_cache_complete' => 'Kész: a megnyitott alaprajzok offline is elérhetők.',
            'floorplans_cache_wait' => 'A mentés a háttérben fut, az app közben használható.',
            'open_full_search' => 'Részletes kereső',
            'offline' => 'Offline indulóképernyő előkészítve',
            'android' => 'Android WebView shell előkészítve',
            'pwa' => 'PWA telepítés támogatva',
            'footer' => 'Harmat Lakópark portál | külön ügyfél-, értékesítői és partnerfelületekkel.',
            'dock_portal' => 'Portál',
            'dock_flats' => 'Lakások',
            'dock_virtual' => 'Választó',
            'dock_ai' => 'AI',
            'dock_account' => 'Fiók',
            'tools' => [
                ['tag' => 'Offline', 'title' => 'Alaprajztár', 'body' => 'Mobilra rendezett alaprajzok, cache-elhető képekkel.', 'href' => '/app/floorplans/'],
                ['tag' => 'Kínálat', 'title' => 'Lakáskereső', 'body' => 'Szűrés szobaszám, ár és státusz alapján.', 'href' => '/lakaskereso/?source=portal_app'],
                ['tag' => 'Térbeli nézet', 'title' => 'Virtuális választó', 'body' => 'Épület- és szintalapú lakásválasztás az első ütemhez.', 'href' => '/virtualis-lakasvalaszto-elso-utem/?source=portal_app'],
                ['tag' => 'Segítség', 'title' => 'AI asszisztens', 'body' => 'Gyors kérdések lakásokról, foglalásról és kapcsolattartásról.', 'href' => '/?hm_assistant=open&source=portal_app'],
            ],
            'roles' => [
                ['key' => 'client', 'tag' => 'Ügyfél', 'title' => 'Vevői portál', 'body' => 'Kedvenc lakások, foglalási állapot, dokumentumok és üzenetek.', 'href' => '/client/?source=portal_app'],
                ['key' => 'sales', 'tag' => 'Csapat', 'title' => 'Értékesítői felület', 'body' => 'Érdeklődők, státuszok és gyors ügyfélkezelési belépés.', 'href' => '/sales/?source=portal_app'],
                ['key' => 'agent', 'tag' => 'Partner', 'title' => 'Közvetítői belépés', 'body' => 'Partneri leadek és együttműködési folyamatok külön felületen.', 'href' => '/agent/?source=portal_app'],
            ],
            'demo_cards' => [
                ['label' => 'Kedvencek', 'value' => 'Lakáslista mentése'],
                ['label' => 'Státusz', 'value' => 'Érdeklődés és foglalás követése'],
                ['label' => 'Dokumentumok', 'value' => 'Szerződéses anyagok egy helyen'],
                ['label' => 'Üzenetek', 'value' => 'Kapcsolat az értékesítéssel'],
            ],
        ],
        'zh_CN' => [
            'html_lang' => 'zh-Hans',
            'brand' => 'Harmat Lakópark 22',
            'portal' => '门户 App',
            'eyebrow' => '1105 Budapest, Harmat utca 22.',
            'title' => 'Harmat 22，回家是一件轻松的事',
            'subtitle' => '位于 Budapest X. 区 Harmat utca 22.，把安静的日常、实用的户型和便捷的城市连接放进一个手机门户里。',
            'install' => '安装到手机桌面',
            'open_demo' => '打开门户演示',
            'language' => 'Magyar',
            'language_href' => '/app/?wp_lang=hu_HU',
            'demo_language_href' => '/app/demo/?wp_lang=hu_HU',
            'continue' => '进入门户',
            'section_tools' => '快捷功能',
            'section_roles' => '按身份进入',
            'section_demo' => 'App 模式预览',
            'section_demo_text' => '演示页展示未来个人客户文件夹的结构：收藏房源、状态、文件和消息。',
            'offline_floorplans_title' => '离线户型册',
            'offline_floorplans_heading' => '把户型图先放进手机',
            'offline_floorplans_body' => '门户会提供一个适合手机查看的户型图册。打开过的户型图会进入缓存，网络弱或离线时也能继续查看。',
            'offline_floorplans_cta' => '打开户型册',
            'offline_floorplans_note' => '提示：重要户型先在线打开一次，之后 app 可以从缓存里继续显示。',
            'floorplans_title' => '离线户型册',
            'floorplans_subtitle' => 'Harmat 22 手机户型图册。在线打开后，图片会进入 app 缓存，之后离线也可以回看。',
            'floorplans_back' => '返回门户',
            'floorplans_all' => '全部',
            'floorplans_open_property' => '查看房源页',
            'floorplans_cache_hint' => '离线准备：保持本页打开时，app 会在后台保存户型图缓存。',
            'floorplans_cache_progress' => '正在保存户型图，供离线查看',
            'floorplans_cache_complete' => '已完成：打开过的户型图可离线查看。',
            'floorplans_cache_wait' => '缓存会在后台继续，不影响你使用 app。',
            'open_full_search' => '详细找房',
            'offline' => '已准备离线启动页',
            'android' => 'Android WebView 外壳已准备',
            'pwa' => '支持 PWA 安装',
            'footer' => 'Harmat Lakópark 门户 | 客户、销售和合作伙伴入口分离。',
            'dock_portal' => '门户',
            'dock_flats' => '房源',
            'dock_virtual' => '选房',
            'dock_ai' => 'AI',
            'dock_account' => '我的',
            'tools' => [
                ['tag' => '离线', 'title' => '离线户型册', 'body' => '手机端户型图册，支持图片缓存。', 'href' => '/app/floorplans/'],
                ['tag' => '房源', 'title' => '找房器', 'body' => '按房间数、价格和状态筛选公寓。', 'href' => '/lakaskereso/?source=portal_app'],
                ['tag' => '空间视图', 'title' => '虚拟选房', 'body' => '用楼栋和楼层视图查看第一期房源。', 'href' => '/virtualis-lakasvalaszto-elso-utem/?source=portal_app'],
                ['tag' => '帮助', 'title' => 'AI 助手', 'body' => '快速询问房源、预订和联系方式。', 'href' => '/?hm_assistant=open&source=portal_app'],
            ],
            'roles' => [
                ['key' => 'client', 'tag' => '客户', 'title' => '客户门户', 'body' => '收藏房源、预订状态、文件和消息。', 'href' => '/client/?source=portal_app'],
                ['key' => 'sales', 'tag' => '团队', 'title' => '销售入口', 'body' => '线索、状态和客户跟进入口。', 'href' => '/sales/?source=portal_app'],
                ['key' => 'agent', 'tag' => '合作', 'title' => '中介入口', 'body' => '合作伙伴线索和流程单独管理。', 'href' => '/agent/?source=portal_app'],
            ],
            'demo_cards' => [
                ['label' => '收藏', 'value' => '保存感兴趣的公寓'],
                ['label' => '状态', 'value' => '跟进咨询和预订'],
                ['label' => '文件', 'value' => '合同资料集中管理'],
                ['label' => '消息', 'value' => '联系销售团队'],
            ],
        ],
    ];

    return $copy[$lang] ?? $copy['hu_HU'];
}

function harmat_app_portal_render_20260610(bool $demo): void
{
    $lang = harmat_app_portal_lang_20260610();
    $t = harmat_app_portal_text_20260610($lang);
    $is_android_app = harmat_app_portal_is_android_app_20260610();
    $android_source = $is_android_app ? '&source=android_app' : '';
    $floorplans = $demo ? [] : harmat_app_portal_floorplans_20260610();
    $language_href = $demo ? $t['demo_language_href'] : $t['language_href'];
    if ($is_android_app) {
        $language_href = add_query_arg('source', 'android_app', $language_href);
    }

    harmat_app_portal_private_headers_20260610('text/html');
    ?>
<!doctype html>
<html lang="<?php echo esc_attr($t['html_lang']); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#28453b">
    <title><?php echo esc_html($t['brand'] . ' | ' . $t['portal']); ?></title>
    <link rel="manifest" href="<?php echo harmat_app_portal_url_20260610('/app/manifest.webmanifest'); ?>">
    <link rel="icon" href="<?php echo harmat_app_portal_url_20260610('/app/icon.svg'); ?>" type="image/svg+xml">
    <style>
        :root {
            --ink: #1f2d2a;
            --muted: #66766f;
            --moss: #355f4f;
            --moss-dark: #24443a;
            --brick: #b35b3c;
            --sand: #d8c19b;
            --cream: #f4ecdf;
            --card: rgba(255, 252, 245, .82);
            --line: rgba(31, 45, 42, .12);
            --shadow: 0 24px 70px rgba(44, 52, 45, .18);
        }
        * { box-sizing: border-box; }
        html { min-height: 100%; background: #efe4d2; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: "Aptos", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 12% 4%, rgba(179, 91, 60, .20), transparent 28rem),
                radial-gradient(circle at 86% 12%, rgba(53, 95, 79, .24), transparent 30rem),
                linear-gradient(145deg, #f7efe4 0%, #eadcc7 52%, #d7c3a2 100%);
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .18;
            background-image:
                linear-gradient(90deg, rgba(31,45,42,.16) 1px, transparent 1px),
                linear-gradient(0deg, rgba(31,45,42,.10) 1px, transparent 1px);
            background-size: 46px 46px;
            mask-image: linear-gradient(to bottom, #000, transparent 72%);
        }
        a { color: inherit; text-decoration: none; }
        .shell {
            width: min(1160px, calc(100% - 28px));
            margin: 0 auto;
            padding: 24px 0 108px;
            position: relative;
        }
        body.is-android-app .shell {
            padding-top: max(46px, calc(env(safe-area-inset-top) + 24px));
            padding-bottom: 28px;
        }
        body.is-android-app .dock {
            display: none;
        }
        body.is-android-app .hero {
            min-height: 0;
            padding: 20px;
            border-radius: 28px;
        }
        body.is-android-app .hero::after {
            display: none;
        }
        body.is-android-app h1 {
            margin: 12px 0 9px;
            font-size: clamp(34px, 9.4vw, 48px);
            line-height: .95;
        }
        body.is-android-app .subtitle {
            font-size: 16px;
            line-height: 1.42;
        }
        body.is-android-app .actions {
            margin-top: 14px;
        }
        body.is-android-app .button {
            min-height: 44px;
            padding: 0 16px;
        }
        body.is-android-app .badges {
            display: none;
        }
        body.is-android-app .offline-showcase {
            margin-top: 14px;
        }
        body.is-android-app .offline-copy {
            padding: 18px;
        }
        body.is-android-app .offline-copy h2 {
            margin: 8px 0;
            font-size: clamp(28px, 8vw, 38px);
        }
        body.is-android-app .offline-copy p {
            margin-bottom: 12px;
            font-size: 15px;
            line-height: 1.38;
        }
        body.is-android-app .offline-note,
        body.is-android-app .offline-preview {
            display: none;
        }
        body.is-android-app .offline-copy .button {
            width: 100%;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 15px;
            color: #fff8ec;
            background: linear-gradient(145deg, var(--moss), var(--brick));
            box-shadow: 0 12px 28px rgba(36, 68, 58, .24);
            font-weight: 900;
        }
        .lang {
            padding: 10px 13px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: rgba(255, 252, 245, .64);
            backdrop-filter: blur(16px);
            font-weight: 700;
            font-size: 14px;
        }
        .hero {
            overflow: hidden;
            position: relative;
            min-height: 420px;
            padding: clamp(28px, 6vw, 64px);
            border: 1px solid rgba(255,255,255,.56);
            border-radius: 38px;
            background:
                linear-gradient(135deg, rgba(255,252,245,.88), rgba(255,252,245,.56)),
                radial-gradient(circle at 78% 18%, rgba(179, 91, 60, .22), transparent 20rem);
            box-shadow: var(--shadow);
        }
        .hero::after {
            content: "22";
            position: absolute;
            right: clamp(16px, 5vw, 64px);
            bottom: -34px;
            font-size: clamp(128px, 23vw, 300px);
            font-weight: 900;
            line-height: .8;
            letter-spacing: -.12em;
            color: rgba(53, 95, 79, .10);
        }
        .hero-content { position: relative; z-index: 1; max-width: 720px; }
        .eyebrow {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(53, 95, 79, .10);
            color: var(--moss-dark);
            font-weight: 800;
            font-size: 13px;
        }
        h1 {
            margin: 18px 0 14px;
            font-family: "Georgia", "Times New Roman", serif;
            font-size: clamp(42px, 7vw, 82px);
            line-height: .92;
            letter-spacing: -.07em;
        }
        .subtitle {
            margin: 0;
            color: var(--muted);
            max-width: 660px;
            font-size: clamp(17px, 2.2vw, 22px);
            line-height: 1.55;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 26px;
        }
        .button {
            border: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 999px;
            color: #fff8ec;
            background: var(--moss-dark);
            box-shadow: 0 16px 34px rgba(36, 68, 58, .24);
            font-weight: 800;
            cursor: pointer;
        }
        .button.secondary { color: var(--moss-dark); background: rgba(255,252,245,.78); border: 1px solid var(--line); box-shadow: none; }
        .badges {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 18px 0 0;
        }
        .badge {
            padding: 14px;
            border-radius: 20px;
            border: 1px solid var(--line);
            background: rgba(255, 252, 245, .58);
            color: var(--muted);
            font-size: 14px;
            font-weight: 700;
        }
        .offline-showcase {
            margin-top: 18px;
            display: grid;
            grid-template-columns: .92fr 1.08fr;
            gap: 14px;
            align-items: stretch;
        }
        .offline-copy,
        .offline-preview {
            border: 1px solid rgba(255,255,255,.56);
            border-radius: 30px;
            background: rgba(255, 252, 245, .82);
            box-shadow: 0 18px 54px rgba(49, 57, 50, .12);
            backdrop-filter: blur(20px);
        }
        .offline-copy {
            padding: 24px;
        }
        .offline-copy h2 {
            margin: 10px 0 10px;
            font-family: "Georgia", "Times New Roman", serif;
            font-size: clamp(30px, 4.5vw, 52px);
            line-height: .98;
            letter-spacing: -.06em;
        }
        .offline-copy p {
            margin: 0 0 16px;
            color: var(--muted);
            line-height: 1.55;
        }
        .offline-note {
            display: block;
            margin-top: 12px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }
        .offline-preview {
            padding: 14px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            overflow: hidden;
        }
        .offline-plan {
            min-height: 230px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 10px;
            border-radius: 22px;
            background: #fffdf8;
            border: 1px solid rgba(31,45,42,.10);
        }
        .offline-plan img {
            width: 100%;
            height: 150px;
            object-fit: contain;
            border-radius: 16px;
            background: #fff;
        }
        .offline-plan strong {
            font-size: 15px;
            letter-spacing: -.03em;
        }
        .offline-plan span {
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
        }
        .section-title {
            margin: 34px 0 14px;
            font-size: 22px;
            letter-spacing: -.04em;
        }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .roles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .card {
            min-height: 190px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px;
            border: 1px solid rgba(255,255,255,.54);
            border-radius: 28px;
            background: var(--card);
            box-shadow: 0 16px 50px rgba(49, 57, 50, .10);
            backdrop-filter: blur(20px);
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .card:hover { transform: translateY(-3px); box-shadow: 0 22px 58px rgba(49, 57, 50, .16); background: rgba(255, 252, 245, .95); }
        .tag { color: var(--brick); font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; }
        .card h2 { margin: 14px 0 8px; font-size: 22px; letter-spacing: -.04em; }
        .card p { margin: 0; color: var(--muted); line-height: 1.48; }
        .arrow { margin-top: 20px; font-weight: 900; color: var(--moss-dark); }
        .demo-panel {
            margin-top: 16px;
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            gap: 14px;
        }
        .panel {
            padding: 22px;
            border: 1px solid rgba(255,255,255,.54);
            border-radius: 30px;
            background: rgba(31, 45, 42, .88);
            color: #fff8ec;
            box-shadow: var(--shadow);
        }
        .panel p { color: rgba(255, 248, 236, .76); line-height: 1.55; }
        .mini-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .mini-card {
            padding: 16px;
            border-radius: 20px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.14);
        }
        .mini-card strong { display: block; margin-bottom: 6px; }
        .phone {
            min-height: 340px;
            padding: 18px;
            border-radius: 34px;
            background: linear-gradient(160deg, #fdf8ef, #d7c3a2);
            border: 10px solid rgba(31, 45, 42, .88);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.6), 0 26px 70px rgba(31,45,42,.22);
        }
        .phone-line { height: 12px; border-radius: 999px; background: rgba(31,45,42,.16); margin-bottom: 12px; }
        .phone-card { padding: 16px; border-radius: 22px; background: rgba(255,255,255,.66); margin-bottom: 10px; }
        .footer { margin: 32px 0 0; color: var(--muted); text-align: center; font-size: 14px; }
        .dock {
            position: fixed;
            z-index: 20;
            left: 50%;
            bottom: max(14px, env(safe-area-inset-bottom));
            transform: translateX(-50%);
            width: min(680px, calc(100% - 24px));
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
            padding: 8px;
            border-radius: 26px;
            border: 1px solid rgba(255,255,255,.56);
            background: rgba(255, 252, 245, .82);
            backdrop-filter: blur(22px);
            box-shadow: 0 18px 50px rgba(49, 57, 50, .18);
        }
        .dock a { padding: 10px 4px; border-radius: 18px; text-align: center; font-size: 12px; font-weight: 900; color: var(--muted); }
        .dock a:first-child { background: var(--moss-dark); color: #fff8ec; }
        @media (max-width: 900px) {
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .roles, .demo-panel, .offline-showcase { grid-template-columns: 1fr; }
            .offline-preview { grid-template-columns: repeat(3, minmax(150px, 1fr)); overflow-x: auto; }
            .badges { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .shell { width: min(100% - 20px, 1160px); padding-top: 14px; }
            .hero { border-radius: 28px; min-height: 0; }
            .grid { grid-template-columns: 1fr; }
            .offline-preview { grid-template-columns: repeat(3, 72vw); }
            .card { min-height: 160px; }
            .topbar { align-items: flex-start; }
            .brand span:last-child { max-width: 160px; }
        }
        @media (prefers-reduced-motion: no-preference) {
            .hero, .card, .panel, .phone { animation: rise .5s ease both; }
            .card:nth-child(2) { animation-delay: .04s; }
            .card:nth-child(3) { animation-delay: .08s; }
            .card:nth-child(4) { animation-delay: .12s; }
            @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        }
    </style>
</head>
<body class="<?php echo $is_android_app ? 'is-android-app' : ''; ?>">
    <main class="shell">
        <div class="topbar">
            <a class="brand" href="<?php echo harmat_app_portal_url_20260610('/app/?wp_lang=' . rawurlencode($lang) . $android_source); ?>">
                <span class="mark">H22</span>
                <span><?php echo esc_html($t['brand']); ?></span>
            </a>
            <a class="lang" href="<?php echo harmat_app_portal_url_20260610($language_href); ?>"><?php echo esc_html($t['language']); ?></a>
        </div>

        <section class="hero">
            <div class="hero-content">
                <div class="eyebrow"><?php echo esc_html($t['eyebrow']); ?></div>
                <h1><?php echo esc_html($demo ? $t['section_demo'] : $t['title']); ?></h1>
                <p class="subtitle"><?php echo esc_html($demo ? $t['section_demo_text'] : $t['subtitle']); ?></p>
                <div class="actions">
                    <?php if ($is_android_app && !$demo) : ?>
                        <a class="button" href="<?php echo harmat_app_portal_app_url_20260610('/app/floorplans/', $lang, true); ?>"><?php echo esc_html($t['offline_floorplans_cta']); ?></a>
                        <a class="button secondary" href="<?php echo harmat_app_portal_url_20260610('/lakaskereso/?source=android_app'); ?>"><?php echo esc_html($t['open_full_search']); ?></a>
                    <?php else : ?>
                        <a class="button" href="<?php echo harmat_app_portal_url_20260610('/app/demo/?wp_lang=' . rawurlencode($lang) . $android_source); ?>"><?php echo esc_html($t['open_demo']); ?></a>
                        <button class="button secondary" type="button" data-install hidden><?php echo esc_html($t['install']); ?></button>
                    <?php endif; ?>
                </div>
                <div class="badges">
                    <div class="badge"><?php echo esc_html($t['offline']); ?></div>
                    <div class="badge"><?php echo esc_html($t['android']); ?></div>
                    <div class="badge"><?php echo esc_html($t['pwa']); ?></div>
                </div>
            </div>
        </section>

        <?php if (!$demo) : ?>
            <section class="offline-showcase" aria-label="<?php echo esc_attr($t['offline_floorplans_title']); ?>">
                <div class="offline-copy">
                    <span class="tag"><?php echo esc_html($t['offline_floorplans_title']); ?></span>
                    <h2><?php echo esc_html($t['offline_floorplans_heading']); ?></h2>
                    <p><?php echo esc_html($t['offline_floorplans_body']); ?></p>
                    <a class="button" href="<?php echo harmat_app_portal_app_url_20260610('/app/floorplans/', $lang, $is_android_app); ?>"><?php echo esc_html($t['offline_floorplans_cta']); ?></a>
                    <small class="offline-note"><?php echo esc_html($t['offline_floorplans_note']); ?></small>
                </div>
                <div class="offline-preview">
                    <?php foreach (array_slice($floorplans, 0, 3) as $plan) : ?>
                        <a class="offline-plan" href="<?php echo harmat_app_portal_app_url_20260610('/app/floorplans/', $lang, $is_android_app); ?>">
                            <img src="<?php echo esc_url($plan['image']); ?>" alt="<?php echo esc_attr($plan['title']); ?>" loading="lazy" decoding="async">
                            <strong><?php echo esc_html($plan['title']); ?></strong>
                            <span><?php echo esc_html(trim(($plan['rooms'] ? $plan['rooms'] . ' szoba' : '') . ' · ' . harmat_app_portal_format_area_20260610((float) $plan['area']), ' ·')); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <h2 class="section-title"><?php echo esc_html($t['section_tools']); ?></h2>
            <section class="grid" aria-label="<?php echo esc_attr($t['section_tools']); ?>">
                <?php foreach ($t['tools'] as $tool) : ?>
                    <a class="card" href="<?php echo strpos($tool['href'], '/app/') === 0 ? harmat_app_portal_app_url_20260610($tool['href'], $lang, $is_android_app) : harmat_app_portal_url_20260610($tool['href']); ?>">
                        <span class="tag"><?php echo esc_html($tool['tag']); ?></span>
                        <span>
                            <h2><?php echo esc_html($tool['title']); ?></h2>
                            <p><?php echo esc_html($tool['body']); ?></p>
                        </span>
                        <span class="arrow">&rarr;</span>
                    </a>
                <?php endforeach; ?>
            </section>

            <h2 class="section-title"><?php echo esc_html($t['section_roles']); ?></h2>
            <section class="roles" aria-label="<?php echo esc_attr($t['section_roles']); ?>">
                <?php foreach ($t['roles'] as $role) : ?>
                    <a class="card" href="<?php echo harmat_app_portal_url_20260610($role['href']); ?>" data-last-role="<?php echo esc_attr($role['key']); ?>">
                        <span class="tag"><?php echo esc_html($role['tag']); ?></span>
                        <span>
                            <h2><?php echo esc_html($role['title']); ?></h2>
                            <p><?php echo esc_html($role['body']); ?></p>
                        </span>
                        <span class="arrow">&rarr;</span>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php else : ?>
            <section class="demo-panel" aria-label="<?php echo esc_attr($t['section_demo']); ?>">
                <div class="panel">
                    <h2><?php echo esc_html($t['continue']); ?></h2>
                    <p><?php echo esc_html($t['section_demo_text']); ?></p>
                    <div class="mini-grid">
                        <?php foreach ($t['demo_cards'] as $card) : ?>
                            <div class="mini-card">
                                <strong><?php echo esc_html($card['label']); ?></strong>
                                <span><?php echo esc_html($card['value']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="phone" aria-hidden="true">
                    <div class="phone-line"></div>
                    <div class="phone-card"><strong><?php echo esc_html($t['brand']); ?></strong><br><?php echo esc_html($t['portal']); ?></div>
                    <div class="phone-card"><?php echo esc_html($t['dock_flats']); ?> + <?php echo esc_html($t['dock_virtual']); ?></div>
                    <div class="phone-card"><?php echo esc_html($t['dock_account']); ?> + <?php echo esc_html($t['dock_ai']); ?></div>
                </div>
            </section>
        <?php endif; ?>

        <p class="footer"><?php echo esc_html($t['footer']); ?></p>
    </main>

    <nav class="dock" aria-label="App navigation">
        <a href="<?php echo harmat_app_portal_url_20260610('/app/?wp_lang=' . rawurlencode($lang) . $android_source); ?>"><?php echo esc_html($t['dock_portal']); ?></a>
        <a href="<?php echo harmat_app_portal_url_20260610('/lakaskereso/?source=portal_app'); ?>"><?php echo esc_html($t['dock_flats']); ?></a>
        <a href="<?php echo harmat_app_portal_url_20260610('/virtualis-lakasvalaszto-elso-utem/?source=portal_app'); ?>"><?php echo esc_html($t['dock_virtual']); ?></a>
        <a href="<?php echo harmat_app_portal_url_20260610('/?hm_assistant=open&source=portal_app'); ?>"><?php echo esc_html($t['dock_ai']); ?></a>
        <a href="<?php echo harmat_app_portal_url_20260610('/client/?source=portal_app'); ?>"><?php echo esc_html($t['dock_account']); ?></a>
    </nav>

    <script>
    (function () {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/app/sw.js').catch(function () {});
            });
        }

        var deferredPrompt = null;
        var installButton = document.querySelector('[data-install]');

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferredPrompt = event;
            if (installButton) {
                installButton.hidden = false;
            }
        });

        if (installButton) {
            installButton.addEventListener('click', function () {
                if (!deferredPrompt) {
                    return;
                }
                deferredPrompt.prompt();
                deferredPrompt.userChoice.finally(function () {
                    deferredPrompt = null;
                    installButton.hidden = true;
                });
            });
        }

        var roleLinks = document.querySelectorAll('[data-last-role]');
        for (var i = 0; i < roleLinks.length; i += 1) {
            roleLinks[i].addEventListener('click', function () {
                try {
                    window.localStorage.setItem('harmat_last_role', this.getAttribute('data-last-role'));
                } catch (error) {}
            });
        }
    }());
    </script>
</body>
</html>
    <?php
}

function harmat_app_portal_manifest_20260610(): void
{
    harmat_app_portal_private_headers_20260610('application/manifest+json');

    echo wp_json_encode([
        'name' => 'Harmat Lakópark Portál',
        'short_name' => 'Harmat 22',
        'description' => 'Harmat Lakópark 22 mobil portál.',
        'start_url' => home_url('/app/?wp_lang=hu_HU&source=pwa'),
        'scope' => home_url('/'),
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#f4ecdf',
        'theme_color' => '#28453b',
        'icons' => [
            ['src' => home_url('/app/icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
        ],
        'shortcuts' => [
            ['name' => 'Alaprajztár', 'url' => home_url('/app/floorplans/?wp_lang=hu_HU&source=pwa_shortcut')],
            ['name' => 'Virtuális választó', 'url' => home_url('/virtualis-lakasvalaszto-elso-utem/?source=pwa_shortcut')],
            ['name' => 'Ügyfélfiók', 'url' => home_url('/client/?source=pwa_shortcut')],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function harmat_app_portal_render_floorplans_20260610(): void
{
    $lang = harmat_app_portal_lang_20260610();
    $t = harmat_app_portal_text_20260610($lang);
    $is_android_app = harmat_app_portal_is_android_app_20260610();
    $plans = harmat_app_portal_floorplans_20260610();
    $assets = array_values(array_unique(array_filter(array_map(static function ($plan) {
        return (string) ($plan['image'] ?? '');
    }, $plans))));

    harmat_app_portal_private_headers_20260610('text/html');
    ?>
<!doctype html>
<html lang="<?php echo esc_attr($t['html_lang']); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#28453b">
    <title><?php echo esc_html($t['brand'] . ' | ' . $t['floorplans_title']); ?></title>
    <link rel="manifest" href="<?php echo harmat_app_portal_url_20260610('/app/manifest.webmanifest'); ?>">
    <style>
        :root{--ink:#1f2d2a;--muted:#66766f;--moss:#24443a;--brick:#b35b3c;--cream:#f4ecdf;--card:rgba(255,252,245,.88);--line:rgba(31,45,42,.12)}
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;color:var(--ink);font-family:"Aptos","Segoe UI",sans-serif;background:radial-gradient(circle at 12% 4%,rgba(179,91,60,.18),transparent 28rem),radial-gradient(circle at 90% 4%,rgba(53,95,79,.22),transparent 30rem),linear-gradient(145deg,#f7efe4 0%,#eadcc7 55%,#d7c3a2 100%)}
        body::before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.14;background-image:linear-gradient(90deg,rgba(31,45,42,.16) 1px,transparent 1px),linear-gradient(0deg,rgba(31,45,42,.10) 1px,transparent 1px);background-size:46px 46px}
        a{color:inherit;text-decoration:none}
        .shell{position:relative;width:min(1160px,calc(100% - 24px));margin:0 auto;padding:24px 0 104px}
        body.is-android-app .shell{padding-top:max(46px,calc(env(safe-area-inset-top) + 24px));padding-bottom:28px}
        .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
        .brand{display:inline-flex;align-items:center;gap:10px;font-weight:900;letter-spacing:-.03em}
        .mark{width:42px;height:42px;display:grid;place-items:center;border-radius:15px;color:#fff8ec;background:linear-gradient(145deg,#355f4f,#b35b3c);font-weight:900}
        .back{padding:10px 13px;border:1px solid var(--line);border-radius:999px;background:rgba(255,252,245,.68);font-weight:900}
        .hero{padding:clamp(24px,5vw,52px);border-radius:34px;background:rgba(255,252,245,.78);border:1px solid rgba(255,255,255,.58);box-shadow:0 24px 70px rgba(44,52,45,.16)}
        .eyebrow{display:inline-flex;padding:8px 12px;border-radius:999px;background:rgba(53,95,79,.10);color:#24443a;font-size:13px;font-weight:900}
        h1{margin:16px 0 12px;font-family:"Georgia","Times New Roman",serif;font-size:clamp(42px,7vw,78px);line-height:.92;letter-spacing:-.07em}
        .subtitle{max-width:760px;margin:0;color:var(--muted);font-size:clamp(17px,2.2vw,22px);line-height:1.55}
        .cache-hint{margin-top:16px;padding:14px 16px;border:1px solid var(--line);border-radius:20px;background:rgba(255,255,255,.42);color:var(--muted);font-weight:800}
        .filters{position:sticky;top:0;z-index:4;display:flex;gap:8px;margin:18px 0;padding:10px;border:1px solid rgba(255,255,255,.58);border-radius:24px;background:rgba(255,252,245,.82);backdrop-filter:blur(20px);overflow:auto}
        .filters button{border:0;padding:11px 15px;border-radius:999px;background:rgba(31,45,42,.08);color:var(--muted);font-weight:900;white-space:nowrap}
        .filters button.is-active{background:#24443a;color:#fff8ec}
        .plan-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .plan-card{display:flex;flex-direction:column;gap:12px;min-height:360px;padding:14px;border:1px solid rgba(255,255,255,.58);border-radius:28px;background:var(--card);box-shadow:0 16px 48px rgba(49,57,50,.12)}
        .plan-card img{width:100%;height:230px;object-fit:contain;border-radius:20px;background:#fff;border:1px solid rgba(31,45,42,.08)}
        .plan-meta{display:flex;justify-content:space-between;gap:10px;color:var(--brick);font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.06em}
        .plan-card h2{margin:0;font-size:22px;letter-spacing:-.04em}
        .facts{display:flex;flex-wrap:wrap;gap:8px}
        .facts span{padding:8px 10px;border-radius:999px;background:rgba(31,45,42,.07);color:var(--muted);font-size:13px;font-weight:900}
        .property-link{margin-top:auto;display:inline-flex;justify-content:center;padding:12px 14px;border-radius:999px;background:#24443a;color:#fff8ec;font-weight:900}
        .dock{position:fixed;z-index:20;left:50%;bottom:max(14px,env(safe-area-inset-bottom));transform:translateX(-50%);width:min(680px,calc(100% - 24px));display:grid;grid-template-columns:repeat(5,1fr);gap:6px;padding:8px;border-radius:26px;border:1px solid rgba(255,255,255,.56);background:rgba(255,252,245,.82);backdrop-filter:blur(22px);box-shadow:0 18px 50px rgba(49,57,50,.18)}
        .dock a{padding:10px 4px;border-radius:18px;text-align:center;font-size:12px;font-weight:900;color:var(--muted)}
        .dock a:nth-child(2){background:#24443a;color:#fff8ec}
        body.is-android-app .dock{display:none}
        @media(max-width:900px){.plan-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:560px){.shell{width:min(100% - 20px,1160px);padding-top:14px}.plan-grid{grid-template-columns:1fr}.plan-card{min-height:0}.plan-card img{height:255px}.topbar{align-items:flex-start}.brand span:last-child{max-width:170px}}
    </style>
</head>
<body class="<?php echo $is_android_app ? 'is-android-app' : ''; ?>">
    <main class="shell">
        <div class="topbar">
            <a class="brand" href="<?php echo harmat_app_portal_app_url_20260610('/app/', $lang, $is_android_app); ?>">
                <span class="mark">H22</span>
                <span><?php echo esc_html($t['brand']); ?></span>
            </a>
            <a class="back" href="<?php echo harmat_app_portal_app_url_20260610('/app/', $lang, $is_android_app); ?>"><?php echo esc_html($t['floorplans_back']); ?></a>
        </div>

        <section class="hero">
            <span class="eyebrow"><?php echo esc_html($t['eyebrow']); ?></span>
            <h1><?php echo esc_html($t['floorplans_title']); ?></h1>
            <p class="subtitle"><?php echo esc_html($t['floorplans_subtitle']); ?></p>
            <div class="cache-hint" data-cache-status><?php echo esc_html($t['floorplans_cache_hint']); ?></div>
        </section>

        <nav class="filters" aria-label="Room filters">
            <button class="is-active" type="button" data-room-filter=""><?php echo esc_html($t['floorplans_all']); ?></button>
            <?php foreach (['1', '2', '3', '4', '5'] as $room) : ?>
                <button type="button" data-room-filter="<?php echo esc_attr($room); ?>"><?php echo esc_html($room); ?> szoba</button>
            <?php endforeach; ?>
        </nav>

        <section class="plan-grid" data-plan-grid>
            <?php foreach ($plans as $plan) : ?>
                <?php
                $room_label = $plan['rooms'] ? $plan['rooms'] . ' szoba' : '';
                $bedroom_label = $plan['bedrooms'] ? $plan['bedrooms'] . ' háló' : '';
                $area_label = harmat_app_portal_format_area_20260610((float) $plan['area']);
                ?>
                <article class="plan-card" data-rooms="<?php echo esc_attr($plan['rooms']); ?>">
                    <img src="<?php echo esc_url($plan['image']); ?>" alt="<?php echo esc_attr($plan['title']); ?>" loading="lazy" decoding="async" data-floorplan-image>
                    <div class="plan-meta">
                        <span><?php echo esc_html($plan['status']); ?></span>
                        <span><?php echo esc_html($plan['floor']); ?></span>
                    </div>
                    <h2><?php echo esc_html($plan['title']); ?></h2>
                    <div class="facts">
                        <?php foreach (array_filter([$room_label, $bedroom_label, $area_label]) as $fact) : ?>
                            <span><?php echo esc_html($fact); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <a class="property-link" href="<?php echo esc_url($plan['url']); ?>"><?php echo esc_html($t['floorplans_open_property']); ?></a>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <nav class="dock" aria-label="App navigation">
        <a href="<?php echo harmat_app_portal_app_url_20260610('/app/', $lang, $is_android_app); ?>"><?php echo esc_html($t['dock_portal']); ?></a>
        <a href="<?php echo harmat_app_portal_app_url_20260610('/app/floorplans/', $lang, $is_android_app); ?>"><?php echo esc_html($t['dock_flats']); ?></a>
        <a href="<?php echo harmat_app_portal_url_20260610('/virtualis-lakasvalaszto-elso-utem/?source=portal_app'); ?>"><?php echo esc_html($t['dock_virtual']); ?></a>
        <a href="<?php echo harmat_app_portal_url_20260610('/?hm_assistant=open&source=portal_app'); ?>"><?php echo esc_html($t['dock_ai']); ?></a>
        <a href="<?php echo harmat_app_portal_url_20260610('/client/?source=portal_app'); ?>"><?php echo esc_html($t['dock_account']); ?></a>
    </nav>

    <script>
    window.HARMAT_FLOORPLAN_ASSETS = <?php echo wp_json_encode($assets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    (function () {
        var buttons = document.querySelectorAll('[data-room-filter]');
        var cards = document.querySelectorAll('[data-rooms]');
        for (var i = 0; i < buttons.length; i += 1) {
            buttons[i].addEventListener('click', function () {
                var room = this.getAttribute('data-room-filter') || '';
                for (var b = 0; b < buttons.length; b += 1) {
                    buttons[b].classList.toggle('is-active', buttons[b] === this);
                }
                for (var c = 0; c < cards.length; c += 1) {
                    var rooms = cards[c].getAttribute('data-rooms') || '';
                    cards[c].hidden = !!room && rooms !== room;
                }
            });
        }

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/app/sw.js').catch(function () {});
        }

        if ('caches' in window && Array.isArray(window.HARMAT_FLOORPLAN_ASSETS)) {
            var status = document.querySelector('[data-cache-status]');
            var assets = window.HARMAT_FLOORPLAN_ASSETS.slice(0);
            var total = assets.length;
            var done = 0;
            var cacheName = 'harmat-app-floorplans-v2';
            var batchSize = 3;

            function setStatus(message) {
                if (status) {
                    status.textContent = message;
                }
            }

            function cacheBatch(cache) {
                var batch = assets.splice(0, batchSize);
                if (!batch.length) {
                    setStatus('<?php echo esc_js($t['floorplans_cache_complete']); ?>');
                    return Promise.resolve();
                }

                setStatus('<?php echo esc_js($t['floorplans_cache_progress']); ?> ' + done + '/' + total + '. <?php echo esc_js($t['floorplans_cache_wait']); ?>');
                return Promise.all(batch.map(function (url) {
                    return cache.add(url).catch(function () {});
                })).then(function () {
                    done += batch.length;
                    setStatus('<?php echo esc_js($t['floorplans_cache_progress']); ?> ' + Math.min(done, total) + '/' + total + '. <?php echo esc_js($t['floorplans_cache_wait']); ?>');
                    return new Promise(function (resolve) {
                        window.setTimeout(function () {
                            resolve(cacheBatch(cache));
                        }, 180);
                    });
                });
            }

            caches.open(cacheName).then(function (cache) {
                if (!total) {
                    setStatus('<?php echo esc_js($t['floorplans_cache_hint']); ?>');
                    return;
                }
                if ('requestIdleCallback' in window) {
                    window.requestIdleCallback(function () { cacheBatch(cache); }, { timeout: 1200 });
                } else {
                    window.setTimeout(function () { cacheBatch(cache); }, 400);
                }
            }).catch(function () {
                setStatus('<?php echo esc_js($t['floorplans_cache_hint']); ?>');
            });
        }
    }());
    </script>
</body>
</html>
    <?php
}

function harmat_app_portal_service_worker_20260610(): void
{
    harmat_app_portal_private_headers_20260610('application/javascript');
    ?>
const HARMAT_APP_CACHE = 'harmat-app-portal-v13';
const HARMAT_APP_PRECACHE = [
  '/app/?wp_lang=hu_HU&source=pwa_cache',
  '/app/?wp_lang=zh_CN&source=pwa_cache',
  '/app/demo/?wp_lang=hu_HU&source=pwa_cache',
  '/app/demo/?wp_lang=zh_CN&source=pwa_cache',
  '/app/floorplans/?wp_lang=hu_HU&source=pwa_cache',
  '/app/floorplans/?wp_lang=zh_CN&source=pwa_cache',
  '/app/icon.svg'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(HARMAT_APP_CACHE)
      .then(function (cache) { return cache.addAll(HARMAT_APP_PRECACHE); })
      .catch(function () {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (key) {
        if (key !== HARMAT_APP_CACHE && key.indexOf('harmat-app-') === 0) {
          return caches.delete(key);
        }
        return Promise.resolve();
      }));
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function (event) {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).then(function (response) {
        const copy = response.clone();
        caches.open(HARMAT_APP_CACHE).then(function (cache) { cache.put(request, copy); });
        return response;
      }).catch(function () {
        return caches.match(request).then(function (cached) {
          return cached || caches.match('/app/?wp_lang=hu_HU&source=pwa_cache');
        });
      })
    );
    return;
  }

  event.respondWith(
    caches.match(request).then(function (cached) {
      if (cached) {
        return cached;
      }
      return fetch(request).then(function (response) {
        if (response && response.status === 200 && new URL(request.url).origin === location.origin) {
          const copy = response.clone();
          caches.open(HARMAT_APP_CACHE).then(function (cache) { cache.put(request, copy); });
        }
        return response;
      });
    })
  );
});
    <?php
}

function harmat_app_portal_icon_20260610(): void
{
    harmat_app_portal_private_headers_20260610('image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop stop-color="#355f4f"/><stop offset="1" stop-color="#b35b3c"/></linearGradient></defs><rect width="512" height="512" rx="120" fill="#f4ecdf"/><path d="M116 336V156h54v70h84v-70h54v180h-54v-68h-84v68h-54Zm228 0v-42l70-62c19-17 27-29 27-43 0-15-11-25-28-25-18 0-32 10-48 31l-38-30c22-32 48-50 90-50 48 0 80 28 80 70 0 35-18 56-52 85l-36 30h90v36H344Z" fill="url(#g)"/></svg>';
}
