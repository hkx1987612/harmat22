<?php
/**
 * Plugin Name: Harmat App Portal
 * Description: Lightweight mobile app entry for buyers, sales staff, and brokers.
 * Version: 0.4.0
 */

defined('ABSPATH') || exit;

function harmat_app_portal_path_20260609() {
    $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = (string) parse_url($path, PHP_URL_PATH);
    return trim($path, '/');
}

function harmat_app_portal_lang_20260609() {
    $raw = '';
    if (isset($_GET['wp_lang'])) {
        $raw = sanitize_text_field(wp_unslash($_GET['wp_lang']));
    } elseif (isset($_GET['lang'])) {
        $raw = sanitize_text_field(wp_unslash($_GET['lang']));
    }

    return stripos($raw, 'zh') !== false ? 'zh' : 'hu';
}

function harmat_app_portal_locale_20260609($lang) {
    return $lang === 'zh' ? 'zh_CN' : 'hu_HU';
}

function harmat_app_portal_logo_20260609($size = 192) {
    $icon = get_site_icon_url((int) $size);
    if ($icon) {
        return $icon;
    }

    return home_url('/wp-content/uploads/2025/11/cropped-Harmat_Logo_512-192x192.png');
}

function harmat_app_portal_manifest_20260609() {
    status_header(200);
    nocache_headers();
    header('Content-Type: application/manifest+json; charset=utf-8');

    $manifest = array(
        'name' => 'Harmat Lakópark',
        'short_name' => 'Harmat App',
        'description' => 'Harmat Lakópark ügyfél-, értékesítési és közvetítői belépési pont.',
        'start_url' => home_url('/app/?wp_lang=hu_HU'),
        'scope' => home_url('/app/'),
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#f7f0e4',
        'theme_color' => '#253137',
        'icons' => array(
            array(
                'src' => harmat_app_portal_logo_20260609(192),
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ),
            array(
                'src' => harmat_app_portal_logo_20260609(512),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ),
        ),
    );

    echo wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function harmat_app_portal_service_worker_20260609() {
    status_header(200);
    nocache_headers();
    header('Content-Type: application/javascript; charset=utf-8');
    ?>
const HARMAT_APP_CACHE = 'harmat-app-v6';
const HARMAT_APP_URLS = [
  '/app/?wp_lang=hu_HU',
  '/app/?wp_lang=zh_CN',
  '/app/demo/?wp_lang=hu_HU',
  '/app/demo/?wp_lang=zh_CN'
];

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(HARMAT_APP_CACHE)
      .then(function(cache) { return cache.addAll(HARMAT_APP_URLS); })
      .catch(function() { return true; })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(keys.filter(function(key) {
        return key !== HARMAT_APP_CACHE && key.indexOf('harmat-app-') === 0;
      }).map(function(key) { return caches.delete(key); }));
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function(event) {
  const requestUrl = new URL(event.request.url);
  if (requestUrl.origin !== location.origin || requestUrl.pathname.indexOf('/app') !== 0) {
    return;
  }
  event.respondWith(
    fetch(event.request).then(function(response) {
      const copy = response.clone();
      caches.open(HARMAT_APP_CACHE).then(function(cache) { cache.put(event.request, copy); });
      return response;
    }).catch(function() {
      return caches.match(event.request).then(function(response) {
        return response || caches.match('/app/?wp_lang=hu_HU');
      });
    })
  );
});
    <?php
    exit;
}

function harmat_app_portal_text_20260609($lang) {
    if ($lang === 'zh') {
        return array(
            'html_lang' => 'zh-Hans',
            'title' => 'Harmat App',
            'eyebrow' => 'Harmat Lakópark',
            'headline' => '统一入口',
            'lead' => '买房者、销售团队和经纪人使用同一个移动入口，选择身份后进入对应工作台。',
            'language' => '语言',
            'hu_label' => '匈牙利语',
            'zh_label' => '中文',
            'home' => '返回网站',
            'install' => '可添加到手机桌面',
            'install_cta' => '安装 App',
            'privacy' => '隐私政策',
            'demo_cta' => '查看试用版',
            'shortcuts_title' => '常用操作',
            'shortcuts_intro' => '选择身份后，也可以直接进入对应模块。',
            'visual_title' => 'Harmat utca 22.',
            'visual_subtitle' => '1105 布达佩斯',
            'continue_tag' => '已登录',
            'continue_title' => '继续进入当前工作台',
            'continue_prefix' => '当前账号可以直接打开',
            'continue_button' => '继续进入',
            'roles' => array(
                array(
                    'key' => 'buyer',
                    'mark' => '客',
                    'label' => '买房者',
                    'sub' => '客户中心',
                    'body' => '查看房源资料、付款进度、合同文件和售后事项。',
                    'cta' => '进入买房者通道',
                    'path' => '/client/',
                    'modules' => array(
                        array('label' => '房源资料', 'path' => '/client/', 'anchor' => 'harmat-customer-apartment'),
                        array('label' => '付款节点', 'path' => '/client/', 'anchor' => 'harmat-customer-payment'),
                        array('label' => '合同文件', 'path' => '/client/', 'anchor' => 'harmat-customer-documents'),
                        array('label' => '售后事项', 'path' => '/client/', 'anchor' => 'harmat-customer-aftercare'),
                    ),
                ),
                array(
                    'key' => 'sales',
                    'mark' => '销',
                    'label' => '销售',
                    'sub' => '销售工作台',
                    'body' => '处理询价、跟单、成交客户、付款提醒和房源库存。',
                    'cta' => '进入销售通道',
                    'path' => '/sales/',
                    'modules' => array(
                        array('label' => '今日待办', 'path' => '/sales/', 'query' => array('view' => 'tasks')),
                        array('label' => '询价汇总', 'path' => '/sales/', 'query' => array('view' => 'inquiries')),
                        array('label' => '成交客户', 'path' => '/sales/', 'query' => array('view' => 'customers')),
                        array('label' => '房源库存', 'path' => '/sales/', 'query' => array('view' => 'properties')),
                    ),
                ),
                array(
                    'key' => 'agent',
                    'mark' => '经',
                    'label' => '经纪人',
                    'sub' => '经纪人中心',
                    'body' => '登记客户、维护跟进、查看在售房源和佣金记录。',
                    'cta' => '进入经纪人通道',
                    'path' => '/agent/',
                    'modules' => array(
                        array('label' => '客户登记', 'path' => '/agent/'),
                        array('label' => '我的客户', 'path' => '/agent/', 'query' => array('view' => 'clients')),
                        array('label' => '待跟进', 'path' => '/agent/', 'query' => array('view' => 'tasks')),
                        array('label' => '房源库存', 'path' => '/agent/', 'query' => array('view' => 'properties')),
                    ),
                ),
            ),
        );
    }

    return array(
        'html_lang' => 'hu',
        'title' => 'Harmat App',
        'eyebrow' => 'Harmat Lakópark',
        'headline' => 'Egységes belépési pont',
        'lead' => 'Vevők, értékesítők és közvetítők egy közös mobil belépőből jutnak a saját felületükre.',
        'language' => 'Nyelv',
        'hu_label' => 'Magyar',
        'zh_label' => 'Kínai',
        'home' => 'Vissza a weboldalra',
        'install' => 'Hozzáadható a telefon kezdőképernyőjéhez',
        'install_cta' => 'App telepítése',
        'privacy' => 'Adatvédelem',
        'demo_cta' => 'Próbaverzió',
        'shortcuts_title' => 'Gyors műveletek',
        'shortcuts_intro' => 'Válasszon szerepet, vagy nyissa meg közvetlenül a gyakori modulokat.',
        'visual_title' => 'Harmat utca 22.',
        'visual_subtitle' => '1105 Budapest',
        'continue_tag' => 'Bejelentkezve',
        'continue_title' => 'Folytatás az aktuális felületen',
        'continue_prefix' => 'A jelenlegi fiókkal közvetlenül megnyitható',
        'continue_button' => 'Tovább',
        'roles' => array(
            array(
                'key' => 'buyer',
                'mark' => 'V',
                'label' => 'Vevő',
                'sub' => 'Ügyfélfelület',
                'body' => 'Lakásadatok, fizetési ütemezés, szerződéses fájlok és ügyintézés.',
                'cta' => 'Vevői belépés',
                'path' => '/client/',
                'modules' => array(
                    array('label' => 'Lakásadatok', 'path' => '/client/', 'anchor' => 'harmat-customer-apartment'),
                    array('label' => 'Fizetések', 'path' => '/client/', 'anchor' => 'harmat-customer-payment'),
                    array('label' => 'Dokumentumok', 'path' => '/client/', 'anchor' => 'harmat-customer-documents'),
                    array('label' => 'Ügyintézés', 'path' => '/client/', 'anchor' => 'harmat-customer-aftercare'),
                ),
            ),
            array(
                'key' => 'sales',
                'mark' => 'É',
                'label' => 'Értékesítés',
                'sub' => 'Munkafelület',
                'body' => 'Érdeklődések, ügyek, lezárt ügyfelek, fizetések és lakáskészlet.',
                'cta' => 'Értékesítési belépés',
                'path' => '/sales/',
                'modules' => array(
                    array('label' => 'Teendők', 'path' => '/sales/', 'query' => array('view' => 'tasks')),
                    array('label' => 'Érdeklődések', 'path' => '/sales/', 'query' => array('view' => 'inquiries')),
                    array('label' => 'Ügyfelek', 'path' => '/sales/', 'query' => array('view' => 'customers')),
                    array('label' => 'Lakáskészlet', 'path' => '/sales/', 'query' => array('view' => 'properties')),
                ),
            ),
            array(
                'key' => 'agent',
                'mark' => 'K',
                'label' => 'Közvetítő',
                'sub' => 'Partnerfelület',
                'body' => 'Ügyfélrögzítés, követés, elérhető lakások és jutalékrekordok.',
                'cta' => 'Közvetítői belépés',
                'path' => '/agent/',
                'modules' => array(
                    array('label' => 'Ügyfélrögzítés', 'path' => '/agent/'),
                    array('label' => 'Ügyfelek', 'path' => '/agent/', 'query' => array('view' => 'clients')),
                    array('label' => 'Teendők', 'path' => '/agent/', 'query' => array('view' => 'tasks')),
                    array('label' => 'Lakások', 'path' => '/agent/', 'query' => array('view' => 'properties')),
                ),
            ),
        ),
    );
}

function harmat_app_portal_module_url_20260609($module, $locale) {
    $path = isset($module['path']) ? (string) $module['path'] : '/app/';
    $args = array('wp_lang' => $locale);
    if (!empty($module['query']) && is_array($module['query'])) {
        foreach ($module['query'] as $key => $value) {
            $args[sanitize_key($key)] = sanitize_text_field((string) $value);
        }
    }

    $url = add_query_arg($args, home_url($path));
    if (!empty($module['anchor'])) {
        $url .= '#' . rawurlencode(sanitize_title((string) $module['anchor']));
    }

    return $url;
}

function harmat_app_portal_demo_data_20260609($lang) {
    if ($lang === 'zh') {
        return array(
            'title' => 'Harmat App 试用版',
            'eyebrow' => '功能试用',
            'headline' => '完整 App 工作台',
            'intro' => '这里先展示买房者、销售和经纪人的完整手机端逻辑。当前为试用界面，不会修改真实数据。',
            'notice' => '试用版只用于确认界面和流程；正式数据仍在原有客户、销售、经纪人后台中。',
            'live_entry' => '返回正式入口',
            'open_live' => '进入正式模块',
            'roles_label' => '身份选择',
            'language' => '语言',
            'hu_label' => '匈牙利语',
            'zh_label' => '中文',
            'panels' => array(
                'buyer' => array(
                    'mark' => '客',
                    'label' => '买房者',
                    'title' => '买房者售后中心',
                    'summary' => '房源、付款、合同文件、项目进度和售后事项集中在一个手机页面。',
                    'kpis' => array(
                        array('label' => '当前房源', 'value' => 'A1-1-L1'),
                        array('label' => '付款进度', 'value' => '25%'),
                        array('label' => '文件', 'value' => '3'),
                        array('label' => '下个节点', 'value' => '2026-12-31'),
                    ),
                    'modules' => array(
                        array('label' => '房源资料', 'detail' => '户型、面积、价格、房号和交付资料集中查看。', 'path' => '/client/', 'anchor' => 'harmat-customer-apartment'),
                        array('label' => '付款计划', 'detail' => '首付、阶段款、尾款和已付款比例清晰显示。', 'path' => '/client/', 'anchor' => 'harmat-customer-payment'),
                        array('label' => '合同文件', 'detail' => '合同、付款凭证、附件和交付材料统一下载。', 'path' => '/client/', 'anchor' => 'harmat-customer-documents'),
                        array('label' => '售后事项', 'detail' => '客户提交问题后，销售端可以继续跟进处理。', 'path' => '/client/', 'anchor' => 'harmat-customer-aftercare'),
                        array('label' => '项目进度', 'detail' => '施工节点、照片和公告以后可作为售后内容更新。', 'path' => '/client/', 'anchor' => 'harmat-customer-progress'),
                    ),
                ),
                'sales' => array(
                    'mark' => '销',
                    'label' => '销售',
                    'title' => '销售移动工作台',
                    'summary' => '询价、分配、跟单、成交客户、付款提醒和房源库存从手机端快速进入。',
                    'kpis' => array(
                        array('label' => '今日待办', 'value' => '8'),
                        array('label' => '待指派', 'value' => '3'),
                        array('label' => '逾期跟进', 'value' => '2'),
                        array('label' => '本周询价', 'value' => '24'),
                    ),
                    'modules' => array(
                        array('label' => '今日待办', 'detail' => '按今天、逾期、未来 7 天集中处理任务。', 'path' => '/sales/', 'query' => array('view' => 'tasks')),
                        array('label' => '询价汇总', 'detail' => '网站询价、经纪人询价和自来客户统一汇总。', 'path' => '/sales/', 'query' => array('view' => 'inquiries')),
                        array('label' => '销售漏斗', 'detail' => '从新机会到预订、合同、成交形成自动化 CRM。', 'path' => '/sales/', 'query' => array('view' => 'deals')),
                        array('label' => '成交客户', 'detail' => '维护客户档案、售后跟单、文件和后续事项。', 'path' => '/sales/', 'query' => array('view' => 'customers')),
                        array('label' => '房源库存', 'detail' => '查看在售、预订、出售状态以及面积和价格筛选。', 'path' => '/sales/', 'query' => array('view' => 'properties')),
                    ),
                ),
                'agent' => array(
                    'mark' => '经',
                    'label' => '经纪人',
                    'title' => '经纪人合作中心',
                    'summary' => '客户登记、跟进任务、房源查询、佣金记录和规则说明统一放在经纪人通道。',
                    'kpis' => array(
                        array('label' => '保护客户', 'value' => '12'),
                        array('label' => '待跟进', 'value' => '4'),
                        array('label' => '可售房源', 'value' => '124'),
                        array('label' => '佣金记录', 'value' => '5'),
                    ),
                    'modules' => array(
                        array('label' => '客户登记', 'detail' => '登记客户姓名、电话、意向房源和下次跟进时间。', 'path' => '/agent/'),
                        array('label' => '我的客户', 'detail' => '查看客户保护期、状态、备注和后续跟进。', 'path' => '/agent/', 'query' => array('view' => 'clients')),
                        array('label' => '待跟进', 'detail' => '把需要联系的客户变成清晰的任务列表。', 'path' => '/agent/', 'query' => array('view' => 'tasks')),
                        array('label' => '房源库存', 'detail' => '按房号、楼栋、面积、价格和状态筛选房源。', 'path' => '/agent/', 'query' => array('view' => 'properties')),
                        array('label' => '规则说明', 'detail' => '佣金规则、客户保护和合作流程集中说明。', 'path' => '/agent/', 'query' => array('view' => 'rules')),
                    ),
                ),
            ),
        );
    }

    return array(
        'title' => 'Harmat App próbaverzió',
        'eyebrow' => 'Funkciópróba',
        'headline' => 'Teljes mobil munkafelület',
        'intro' => 'Ez a próbaverzió bemutatja a vevői, értékesítési és közvetítői mobil logikát. Nem módosít valódi adatot.',
        'notice' => 'A próbaverzió a felület és a folyamat ellenőrzésére szolgál; az éles adatok továbbra is a meglévő portálokon maradnak.',
        'live_entry' => 'Vissza az éles belépéshez',
        'open_live' => 'Éles modul megnyitása',
        'roles_label' => 'Szerepválasztás',
        'language' => 'Nyelv',
        'hu_label' => 'Magyar',
        'zh_label' => 'Kínai',
        'panels' => array(
            'buyer' => array(
                'mark' => 'V',
                'label' => 'Vevő',
                'title' => 'Vevői ügyfélközpont',
                'summary' => 'Lakásadatok, fizetés, szerződéses fájlok, projektállapot és ügyintézés egy mobil nézetben.',
                'kpis' => array(
                    array('label' => 'Lakás', 'value' => 'A1-1-L1'),
                    array('label' => 'Fizetés', 'value' => '25%'),
                    array('label' => 'Fájl', 'value' => '3'),
                    array('label' => 'Következő határidő', 'value' => '2026-12-31'),
                ),
                'modules' => array(
                    array('label' => 'Lakásadatok', 'detail' => 'Alaprajz, terület, ár, lakásszám és átadási információk.', 'path' => '/client/', 'anchor' => 'harmat-customer-apartment'),
                    array('label' => 'Fizetési terv', 'detail' => 'Előleg, ütemezett részletek, fennmaradó összeg és fizetési arány.', 'path' => '/client/', 'anchor' => 'harmat-customer-payment'),
                    array('label' => 'Dokumentumok', 'detail' => 'Szerződés, bizonylatok, mellékletek és ügyfélanyagok letöltése.', 'path' => '/client/', 'anchor' => 'harmat-customer-documents'),
                    array('label' => 'Ügyintézés', 'detail' => 'A vevő kérdést küldhet, az értékesítés pedig követni tudja.', 'path' => '/client/', 'anchor' => 'harmat-customer-aftercare'),
                    array('label' => 'Projektállapot', 'detail' => 'Később építési állapot, fotók és közlemények jelenhetnek meg.', 'path' => '/client/', 'anchor' => 'harmat-customer-progress'),
                ),
            ),
            'sales' => array(
                'mark' => 'É',
                'label' => 'Értékesítés',
                'title' => 'Értékesítési mobil munkafelület',
                'summary' => 'Érdeklődések, kiosztás, ügykövetés, lezárt ügyfelek, fizetések és lakáskészlet gyors elérése.',
                'kpis' => array(
                    array('label' => 'Mai teendő', 'value' => '8'),
                    array('label' => 'Kiosztásra vár', 'value' => '3'),
                    array('label' => 'Lejárt követés', 'value' => '2'),
                    array('label' => 'Heti érdeklődés', 'value' => '24'),
                ),
                'modules' => array(
                    array('label' => 'Teendők', 'detail' => 'Mai, lejárt és következő 7 napos feladatok egy listában.', 'path' => '/sales/', 'query' => array('view' => 'tasks')),
                    array('label' => 'Érdeklődések', 'detail' => 'Weboldali, közvetítői és saját érdeklődések közös nézete.', 'path' => '/sales/', 'query' => array('view' => 'inquiries')),
                    array('label' => 'Értékesítési ügyek', 'detail' => 'CRM folyamat az új lehetőségtől a foglaláson át a lezárásig.', 'path' => '/sales/', 'query' => array('view' => 'deals')),
                    array('label' => 'Ügyfelek', 'detail' => 'Lezárt ügyfelek, utógondozás, fájlok és következő lépések.', 'path' => '/sales/', 'query' => array('view' => 'customers')),
                    array('label' => 'Lakáskészlet', 'detail' => 'Elérhető, foglalt és eladott lakások szűrése ár és terület szerint.', 'path' => '/sales/', 'query' => array('view' => 'properties')),
                ),
            ),
            'agent' => array(
                'mark' => 'K',
                'label' => 'Közvetítő',
                'title' => 'Közvetítői partnerközpont',
                'summary' => 'Ügyfélrögzítés, követési feladatok, lakáskeresés, jutalékrekordok és szabályok egy helyen.',
                'kpis' => array(
                    array('label' => 'Védett ügyfél', 'value' => '12'),
                    array('label' => 'Teendő', 'value' => '4'),
                    array('label' => 'Elérhető lakás', 'value' => '124'),
                    array('label' => 'Jutalékrekord', 'value' => '5'),
                ),
                'modules' => array(
                    array('label' => 'Ügyfélrögzítés', 'detail' => 'Név, telefon, érdeklődött lakás és következő kapcsolatfelvétel.', 'path' => '/agent/'),
                    array('label' => 'Ügyfelek', 'detail' => 'Védelmi idő, státusz, megjegyzés és következő feladat.', 'path' => '/agent/', 'query' => array('view' => 'clients')),
                    array('label' => 'Teendők', 'detail' => 'A következő kapcsolattartások feladatlistává alakulnak.', 'path' => '/agent/', 'query' => array('view' => 'tasks')),
                    array('label' => 'Lakások', 'detail' => 'Lakásszám, épület, terület, ár és státusz szerinti keresés.', 'path' => '/agent/', 'query' => array('view' => 'properties')),
                    array('label' => 'Szabályok', 'detail' => 'Jutalék, ügyfélvédelem és együttműködési folyamat.', 'path' => '/agent/', 'query' => array('view' => 'rules')),
                ),
            ),
        ),
    );
}

function harmat_app_portal_render_demo_20260609() {
    $lang = harmat_app_portal_lang_20260609();
    $locale = harmat_app_portal_locale_20260609($lang);
    $demo = harmat_app_portal_demo_data_20260609($lang);
    $logo = harmat_app_portal_logo_20260609(192);
    $app_url = add_query_arg('wp_lang', $locale, home_url('/app/'));

    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    ?>
<!doctype html>
<html lang="<?php echo esc_attr($lang === 'zh' ? 'zh-Hans' : 'hu'); ?>">
<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#253137">
    <title><?php echo esc_html($demo['title']); ?></title>
    <style>
        *{box-sizing:border-box}
        html,body{min-height:100%;margin:0}
        body{background:#f7f0e4;color:#253137;font-family:Montserrat,Arial,"Microsoft YaHei",sans-serif}
        .happ-demo-shell{width:min(1180px,calc(100% - 28px));margin:0 auto;padding:24px 0 42px}
        .happ-demo-top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px;padding:16px;border:1px solid #ead8b8;border-radius:18px;background:#fff;box-shadow:0 14px 34px rgba(70,54,28,.06)}
        .happ-demo-brand{display:flex;align-items:center;gap:12px;color:#253137;text-decoration:none}.happ-demo-brand img{width:38px;height:38px;object-fit:contain}.happ-demo-brand span{display:block;color:#a5742c;font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.happ-demo-brand strong{display:block;font-family:Georgia,"Times New Roman",serif;font-size:21px;font-weight:500}
        .happ-demo-actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:8px}.happ-demo-actions a{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border:1px solid #ead8b8;border-radius:999px;background:#fffaf3;color:#8a5a18;font-size:13px;font-weight:900;text-decoration:none}.happ-demo-actions a.is-active{background:#253137;color:#fff;border-color:#253137}
        .happ-demo-hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.42fr);gap:16px;align-items:stretch;margin-bottom:16px}
        .happ-demo-intro,.happ-demo-notice{padding:22px;border:1px solid #ead8b8;border-radius:22px;background:linear-gradient(135deg,#fffaf1,#fff);box-shadow:0 18px 45px rgba(70,54,28,.08)}
        .happ-demo-intro small{display:block;margin-bottom:8px;color:#a5742c;font-size:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.happ-demo-intro h1{margin:0;color:#18262c;font-family:Georgia,"Times New Roman",serif;font-size:clamp(34px,5vw,58px);font-weight:500;line-height:1;letter-spacing:0}.happ-demo-intro p,.happ-demo-notice p{margin:10px 0 0;color:#5d6670;line-height:1.65}
        .happ-demo-notice{display:grid;align-content:center;background:#253137;color:#fff}.happ-demo-notice strong{font-family:Georgia,"Times New Roman",serif;font-size:27px;font-weight:500}.happ-demo-notice p{color:#d7e0e3}
        .happ-demo-role-tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:16px;padding:8px;border:1px solid #ead8b8;border-radius:18px;background:#fff}
        .happ-demo-role-tabs button{display:flex;align-items:center;justify-content:center;gap:8px;min-height:46px;border:0;border-radius:12px;background:#fffaf3;color:#253137;font:inherit;font-weight:900;cursor:pointer}.happ-demo-role-tabs button span{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:#253137;color:#fff;font-size:12px}.happ-demo-role-tabs button.is-active{background:#a8762d;color:#fff}.happ-demo-role-tabs button.is-active span{background:#fff;color:#a8762d}
        .happ-demo-panel{display:none}.happ-demo-panel.is-active{display:grid;gap:16px}
        .happ-demo-panel-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;padding:22px;border:1px solid #ead8b8;border-radius:22px;background:#fff;box-shadow:0 18px 45px rgba(70,54,28,.08)}
        .happ-demo-panel-head h2{margin:0;color:#253137;font-family:Georgia,"Times New Roman",serif;font-size:34px;font-weight:500;letter-spacing:0}.happ-demo-panel-head p{max-width:760px;margin:7px 0 0;color:#687178;line-height:1.6}.happ-demo-panel-head>a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 16px;border-radius:10px;background:#253137;color:#fff;font-size:13px;font-weight:900;text-decoration:none;white-space:nowrap}
        .happ-demo-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.happ-demo-kpis article{padding:15px;border:1px solid #ead8b8;border-radius:16px;background:#fff}.happ-demo-kpis small{display:block;color:#a5742c;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.happ-demo-kpis strong{display:block;margin-top:7px;color:#253137;font-size:24px;line-height:1.1;overflow-wrap:anywhere}
        .happ-demo-modules{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:10px}.happ-demo-module{display:grid;grid-template-rows:auto auto 1fr auto;gap:9px;min-height:198px;padding:16px;border:1px solid #ead8b8;border-left:4px solid #a8762d;border-radius:16px;background:#fffaf3;color:#253137;text-decoration:none}.happ-demo-module b{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:999px;background:#253137;color:#fff;font-size:12px}.happ-demo-module strong{font-size:18px}.happ-demo-module p{margin:0;color:#5d6670;font-size:13px;line-height:1.55}.happ-demo-module span{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 10px;border-radius:9px;background:#a8762d;color:#fff;font-size:12px;font-weight:900}
        @media(max-width:760px){.happ-demo-shell{width:min(100% - 20px,720px);padding-top:12px}.happ-demo-top,.happ-demo-hero,.happ-demo-panel-head{grid-template-columns:1fr;display:grid}.happ-demo-actions{justify-content:flex-start}.happ-demo-role-tabs{grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}.happ-demo-role-tabs button{display:grid;gap:4px;min-height:74px;padding:8px 4px;font-size:12px}.happ-demo-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.happ-demo-panel-head>a{width:100%}.happ-demo-modules{grid-template-columns:1fr}.happ-demo-module{min-height:0}.happ-demo-intro,.happ-demo-notice,.happ-demo-panel-head{padding:18px}}
    </style>
</head>
<body>
    <main class="happ-demo-shell">
        <header class="happ-demo-top">
            <a class="happ-demo-brand" href="<?php echo esc_url($app_url); ?>">
                <img src="<?php echo esc_url($logo); ?>" alt="Harmat">
                <span>Harmat Lakópark</span>
                <strong>Harmat App</strong>
            </a>
            <nav class="happ-demo-actions" aria-label="<?php echo esc_attr($demo['language']); ?>">
                <a class="<?php echo $lang === 'hu' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('wp_lang', 'hu_HU', home_url('/app/demo/'))); ?>"><?php echo esc_html($demo['hu_label']); ?></a>
                <a class="<?php echo $lang === 'zh' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('wp_lang', 'zh_CN', home_url('/app/demo/'))); ?>"><?php echo esc_html($demo['zh_label']); ?></a>
                <a href="<?php echo esc_url($app_url); ?>"><?php echo esc_html($demo['live_entry']); ?></a>
            </nav>
        </header>
        <section class="happ-demo-hero">
            <div class="happ-demo-intro">
                <small><?php echo esc_html($demo['eyebrow']); ?></small>
                <h1><?php echo esc_html($demo['headline']); ?></h1>
                <p><?php echo esc_html($demo['intro']); ?></p>
            </div>
            <aside class="happ-demo-notice">
                <strong>Demo</strong>
                <p><?php echo esc_html($demo['notice']); ?></p>
            </aside>
        </section>
        <nav class="happ-demo-role-tabs" aria-label="<?php echo esc_attr($demo['roles_label']); ?>">
            <?php foreach ($demo['panels'] as $key => $panel) : ?>
                <button type="button" class="<?php echo $key === 'buyer' ? 'is-active' : ''; ?>" data-demo-role="<?php echo esc_attr($key); ?>"><span><?php echo esc_html($panel['mark']); ?></span><?php echo esc_html($panel['label']); ?></button>
            <?php endforeach; ?>
        </nav>
        <?php foreach ($demo['panels'] as $key => $panel) : ?>
            <?php $primary_module = $panel['modules'][0] ?? array('path' => '/app/'); ?>
            <section class="happ-demo-panel <?php echo $key === 'buyer' ? 'is-active' : ''; ?>" data-demo-panel="<?php echo esc_attr($key); ?>">
                <header class="happ-demo-panel-head">
                    <div>
                        <h2><?php echo esc_html($panel['title']); ?></h2>
                        <p><?php echo esc_html($panel['summary']); ?></p>
                    </div>
                    <a href="<?php echo esc_url(harmat_app_portal_module_url_20260609($primary_module, $locale)); ?>"><?php echo esc_html($demo['open_live']); ?></a>
                </header>
                <section class="happ-demo-kpis" aria-label="<?php echo esc_attr($panel['title']); ?>">
                    <?php foreach ($panel['kpis'] as $kpi) : ?>
                        <article><small><?php echo esc_html($kpi['label']); ?></small><strong><?php echo esc_html($kpi['value']); ?></strong></article>
                    <?php endforeach; ?>
                </section>
                <section class="happ-demo-modules">
                    <?php foreach ($panel['modules'] as $index => $module) : ?>
                        <a class="happ-demo-module" href="<?php echo esc_url(harmat_app_portal_module_url_20260609($module, $locale)); ?>">
                            <b><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></b>
                            <strong><?php echo esc_html($module['label']); ?></strong>
                            <p><?php echo esc_html($module['detail']); ?></p>
                            <span><?php echo esc_html($demo['open_live']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </section>
            </section>
        <?php endforeach; ?>
    </main>
    <script>
        (function(){
            var buttons = document.querySelectorAll('[data-demo-role]');
            var panels = document.querySelectorAll('[data-demo-panel]');
            function activate(role) {
                buttons.forEach(function(button) { button.classList.toggle('is-active', button.getAttribute('data-demo-role') === role); });
                panels.forEach(function(panel) { panel.classList.toggle('is-active', panel.getAttribute('data-demo-panel') === role); });
            }
            buttons.forEach(function(button) {
                button.addEventListener('click', function() { activate(button.getAttribute('data-demo-role')); });
            });
        })();
    </script>
</body>
</html>
    <?php
    exit;
}

function harmat_app_portal_current_workspace_20260609($text, $locale) {
    if (!is_user_logged_in()) {
        return null;
    }

    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    $role_map = array();
    foreach ($text['roles'] as $role) {
        $role_map[$role['key']] = $role;
    }

    if (current_user_can('manage_options') || in_array('harmat_sales_manager', $roles, true) || in_array('harmat_sales_staff', $roles, true)) {
        $workspace = $role_map['sales'] ?? null;
    } elseif (in_array('harmat_broker_viewer', $roles, true)) {
        $workspace = $role_map['agent'] ?? null;
    } elseif (current_user_can('harmat_view_customer_portal') || in_array('harmat_customer_owner', $roles, true)) {
        $workspace = $role_map['buyer'] ?? null;
    } else {
        return null;
    }

    if (!$workspace) {
        return null;
    }

    $workspace['url'] = add_query_arg('wp_lang', $locale, home_url($workspace['path']));
    $workspace['user_label'] = $user->display_name ?: $user->user_login;

    return $workspace;
}

function harmat_app_portal_render_20260609() {
    $lang = harmat_app_portal_lang_20260609();
    $locale = harmat_app_portal_locale_20260609($lang);
    $text = harmat_app_portal_text_20260609($lang);
    $current_workspace = harmat_app_portal_current_workspace_20260609($text, $locale);
    $logo = harmat_app_portal_logo_20260609(192);
    $hero_image = home_url('/wp-content/uploads/2026/02/Harmat22_latvany-3-1536x864.jpg');
    $manifest_url = home_url('/app/manifest.webmanifest');

    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    ?>
<!doctype html>
<html lang="<?php echo esc_attr($text['html_lang']); ?>">
<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#253137">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Harmat App">
    <title><?php echo esc_html($text['title']); ?></title>
    <link rel="manifest" href="<?php echo esc_url($manifest_url); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url($logo); ?>">
    <style>
        *{box-sizing:border-box}
        html,body{min-height:100%;margin:0}
        body{background:#f7f0e4;color:#253137;font-family:Montserrat,Arial,"Microsoft YaHei",sans-serif}
        .harmat-app-shell{min-height:100svh;display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,43vw)}
        .harmat-app-main{display:flex;flex-direction:column;gap:24px;padding:clamp(22px,5vw,64px)}
        .harmat-app-top{display:flex;align-items:center;justify-content:space-between;gap:16px}
        .harmat-app-brand{display:flex;align-items:center;gap:12px;color:#253137;text-decoration:none}
        .harmat-app-brand img{width:42px;height:42px;object-fit:contain}
        .harmat-app-brand span{display:block;color:#8a5a18;font-size:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
        .harmat-app-brand strong{display:block;font-family:Georgia,"Times New Roman",serif;font-size:20px;font-weight:500}
        .harmat-app-lang{display:flex;align-items:center;gap:6px;padding:6px;border:1px solid rgba(138,90,24,.22);border-radius:999px;background:#fff}
        .harmat-app-lang a{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 12px;border-radius:999px;color:#8a5a18;font-size:13px;font-weight:900;text-decoration:none;white-space:nowrap}
        .harmat-app-lang a.is-active{background:#253137;color:#fff}
        .harmat-app-hero{max-width:780px}
        .harmat-app-eyebrow{margin:0 0 10px;color:#8a5a18;font-size:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
        .harmat-app-hero h1{margin:0;color:#18262c;font-family:Georgia,"Times New Roman",serif;font-size:clamp(40px,7vw,82px);font-weight:500;line-height:.98;letter-spacing:0}
        .harmat-app-hero p{max-width:620px;margin:18px 0 0;color:#526069;font-size:17px;line-height:1.65}
        .harmat-app-continue{display:flex;align-items:center;justify-content:space-between;gap:16px;width:min(100%,780px);padding:16px 18px;border:1px solid rgba(168,118,45,.28);border-radius:8px;background:#fff;box-shadow:0 14px 35px rgba(39,49,56,.08)}
        .harmat-app-continue small{display:block;margin-bottom:4px;color:#1f7a4d;font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
        .harmat-app-continue strong{display:block;color:#18262c;font-family:Georgia,"Times New Roman",serif;font-size:25px;font-weight:500;line-height:1.12}
        .harmat-app-continue p{margin:6px 0 0;color:#687178;font-size:13px;line-height:1.45}
        .harmat-app-continue a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:6px;background:#253137;color:#fff;font-size:13px;font-weight:900;text-decoration:none;white-space:nowrap}
        .harmat-app-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:auto}
        .harmat-app-card{display:grid;grid-template-rows:auto auto 1fr auto;gap:12px;min-height:260px;padding:18px;border:1px solid rgba(138,90,24,.2);border-radius:8px;background:#fff;color:#253137;text-decoration:none;box-shadow:0 18px 45px rgba(39,49,56,.08);transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease}
        .harmat-app-card:hover{transform:translateY(-2px);border-color:#a8762d;box-shadow:0 24px 55px rgba(39,49,56,.12)}
        .harmat-app-mark{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;background:#253137;color:#fff;font-weight:900;font-style:normal}
        .harmat-app-card h2{margin:0;color:#17262c;font-family:Georgia,"Times New Roman",serif;font-size:28px;font-weight:500;line-height:1.1;letter-spacing:0;overflow-wrap:anywhere}
        .harmat-app-card small{display:block;margin-top:5px;color:#a8762d;font-family:Montserrat,Arial,"Microsoft YaHei",sans-serif;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
        .harmat-app-card p{margin:0;color:#5f6970;font-size:14px;line-height:1.55}
        .harmat-app-card span:last-child{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 12px;border-radius:6px;background:#a8762d;color:#fff;font-size:13px;font-weight:900;text-align:center}
        .harmat-app-shortcuts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .harmat-app-shortcuts-head{grid-column:1/-1;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-top:2px}
        .harmat-app-shortcuts-head h2{margin:0;color:#18262c;font-family:Georgia,"Times New Roman",serif;font-size:30px;font-weight:500;line-height:1.05;letter-spacing:0}
        .harmat-app-shortcuts-head p{max-width:520px;margin:0;color:#687178;font-size:13px;line-height:1.5;text-align:right}
        .harmat-app-shortcut-group{display:grid;gap:10px;padding:14px;border:1px solid rgba(138,90,24,.18);border-radius:8px;background:rgba(255,255,255,.72)}
        .harmat-app-shortcut-group strong{display:flex;align-items:center;justify-content:space-between;gap:8px;color:#253137;font-size:14px}
        .harmat-app-shortcut-group strong i{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:999px;background:#253137;color:#fff;font-size:11px;font-style:normal}
        .harmat-app-shortcut-links{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
        .harmat-app-shortcut-links a{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 9px;border:1px solid rgba(168,118,45,.26);border-radius:6px;background:#fff;color:#8a5a18;font-size:12px;font-weight:900;text-align:center;text-decoration:none;line-height:1.2}
        .harmat-app-foot{display:flex;flex-wrap:wrap;gap:10px;align-items:center;color:#687178;font-size:13px}
        .harmat-app-foot a,.harmat-app-install{display:inline-flex;align-items:center;min-height:34px;color:#8a5a18;font-weight:900;text-decoration:none}
        .harmat-app-install{border:1px solid rgba(138,90,24,.28);border-radius:999px;background:#fff;padding:0 12px;font:inherit;font-size:13px;cursor:pointer}
        .harmat-app-install[hidden]{display:none}
        .harmat-app-visual{position:relative;min-height:100%;background:#253137;overflow:hidden}
        .harmat-app-visual img{width:100%;height:100%;object-fit:cover;filter:saturate(.94);opacity:.82}
        .harmat-app-visual:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(37,49,55,.16),rgba(37,49,55,.58))}
        .harmat-app-visual-card{position:absolute;left:24px;right:24px;bottom:24px;z-index:1;padding:18px;border:1px solid rgba(255,255,255,.26);border-radius:8px;background:rgba(255,255,255,.9);backdrop-filter:blur(8px)}
        .harmat-app-visual-card strong{display:block;color:#253137;font-family:Georgia,"Times New Roman",serif;font-size:24px;font-weight:500}
        .harmat-app-visual-card span{display:block;margin-top:6px;color:#687178;font-size:13px;line-height:1.5}
        @media(max-width:980px){
            .harmat-app-shell{grid-template-columns:1fr}
            .harmat-app-visual{display:none}
            .harmat-app-main{min-height:100svh}
            .harmat-app-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
            .harmat-app-card{min-height:118px;padding:12px 8px;align-content:start;justify-items:center;text-align:center;grid-template-rows:auto auto;gap:7px}
            .harmat-app-mark{width:32px;height:32px;font-size:13px}
            .harmat-app-card h2{font-size:clamp(13px,3.8vw,18px);line-height:1.08}
            .harmat-app-card small{font-size:9px;letter-spacing:.03em;line-height:1.25}
            .harmat-app-card p,.harmat-app-card span:last-child{display:none}
            .harmat-app-shortcuts{grid-template-columns:1fr;gap:8px}
            .harmat-app-shortcuts-head{display:grid;gap:4px;margin-top:0}
            .harmat-app-shortcuts-head h2{font-size:24px}
            .harmat-app-shortcuts-head p{text-align:left}
            .harmat-app-shortcut-group{grid-template-columns:88px minmax(0,1fr);align-items:center;padding:10px}
            .harmat-app-shortcut-group strong{display:grid;gap:4px;justify-items:start;font-size:13px}
            .harmat-app-shortcut-links{grid-template-columns:repeat(4,minmax(0,1fr));gap:5px}
            .harmat-app-shortcut-links a{min-height:34px;padding:0 4px;font-size:10px}
            .harmat-app-continue{display:grid;gap:12px;padding:14px}
            .harmat-app-continue strong{font-size:22px}
            .harmat-app-continue a{width:100%}
            .harmat-app-top{align-items:flex-start}
            .harmat-app-lang{flex:0 0 auto}
        }
        @media(max-width:520px){
            .harmat-app-main{padding:18px 14px 22px;gap:20px}
            .harmat-app-top{display:grid;gap:12px}
            .harmat-app-lang{justify-self:start}
            .harmat-app-hero h1{font-size:42px}
            .harmat-app-hero p{font-size:15px}
            .harmat-app-grid{gap:7px}
            .harmat-app-card{padding:11px 6px}
            .harmat-app-shortcut-group{grid-template-columns:1fr}
            .harmat-app-shortcut-links a{font-size:10px}
        }
    </style>
</head>
<body>
    <main class="harmat-app-shell">
        <section class="harmat-app-main" aria-label="Harmat App">
            <header class="harmat-app-top">
                <a class="harmat-app-brand" href="<?php echo esc_url(home_url('/')); ?>">
                    <img src="<?php echo esc_url($logo); ?>" alt="Harmat">
                    <span><?php echo esc_html($text['eyebrow']); ?></span>
                    <strong>Harmat App</strong>
                </a>
                <nav class="harmat-app-lang" aria-label="<?php echo esc_attr($text['language']); ?>">
                    <a class="<?php echo $lang === 'hu' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('wp_lang', 'hu_HU', home_url('/app/'))); ?>"><?php echo esc_html($text['hu_label']); ?></a>
                    <a class="<?php echo $lang === 'zh' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('wp_lang', 'zh_CN', home_url('/app/'))); ?>"><?php echo esc_html($text['zh_label']); ?></a>
                </nav>
            </header>
            <section class="harmat-app-hero">
                <p class="harmat-app-eyebrow"><?php echo esc_html($text['eyebrow']); ?></p>
                <h1><?php echo esc_html($text['headline']); ?></h1>
                <p><?php echo esc_html($text['lead']); ?></p>
            </section>
            <?php if ($current_workspace) : ?>
                <section class="harmat-app-continue" aria-label="<?php echo esc_attr($text['continue_title']); ?>">
                    <div>
                        <small><?php echo esc_html($text['continue_tag']); ?></small>
                        <strong><?php echo esc_html($text['continue_title']); ?></strong>
                        <p><?php echo esc_html($text['continue_prefix'] . ': ' . $current_workspace['label'] . ' / ' . $current_workspace['user_label']); ?></p>
                    </div>
                    <a href="<?php echo esc_url($current_workspace['url']); ?>"><?php echo esc_html($text['continue_button']); ?></a>
                </section>
            <?php endif; ?>
            <section class="harmat-app-grid" aria-label="App portals">
                <?php foreach ($text['roles'] as $role) : ?>
                    <?php $url = add_query_arg('wp_lang', $locale, home_url($role['path'])); ?>
                    <a class="harmat-app-card harmat-app-card-<?php echo esc_attr($role['key']); ?>" href="<?php echo esc_url($url); ?>">
                        <i class="harmat-app-mark" aria-hidden="true"><?php echo esc_html($role['mark']); ?></i>
                        <h2><?php echo esc_html($role['label']); ?><small><?php echo esc_html($role['sub']); ?></small></h2>
                        <p><?php echo esc_html($role['body']); ?></p>
                        <span><?php echo esc_html($role['cta']); ?></span>
                    </a>
                <?php endforeach; ?>
            </section>
            <section class="harmat-app-shortcuts" aria-label="<?php echo esc_attr($text['shortcuts_title']); ?>">
                <div class="harmat-app-shortcuts-head">
                    <h2><?php echo esc_html($text['shortcuts_title']); ?></h2>
                    <p><?php echo esc_html($text['shortcuts_intro']); ?></p>
                </div>
                <?php foreach ($text['roles'] as $role) : ?>
                    <article class="harmat-app-shortcut-group">
                        <strong><i aria-hidden="true"><?php echo esc_html($role['mark']); ?></i><?php echo esc_html($role['label']); ?></strong>
                        <div class="harmat-app-shortcut-links">
                            <?php foreach (($role['modules'] ?? array()) as $module) : ?>
                                <a href="<?php echo esc_url(harmat_app_portal_module_url_20260609($module, $locale)); ?>"><?php echo esc_html($module['label']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
            <footer class="harmat-app-foot">
                <span><?php echo esc_html($text['install']); ?></span>
                <button class="harmat-app-install" type="button" hidden><?php echo esc_html($text['install_cta']); ?></button>
                <a href="<?php echo esc_url(add_query_arg('wp_lang', $locale, home_url('/app/demo/'))); ?>"><?php echo esc_html($text['demo_cta']); ?></a>
                <a href="<?php echo esc_url(home_url('/adatvedelmi-tajekoztato/')); ?>"><?php echo esc_html($text['privacy']); ?></a>
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html($text['home']); ?></a>
            </footer>
        </section>
        <aside class="harmat-app-visual" aria-hidden="true">
            <img src="<?php echo esc_url($hero_image); ?>" alt="">
            <div class="harmat-app-visual-card">
                <strong><?php echo esc_html($text['visual_title']); ?></strong>
                <span><?php echo esc_html($text['visual_subtitle']); ?></span>
            </div>
        </aside>
    </main>
    <script>
        (function(){
            var deferredInstallPrompt = null;
            var installButton = document.querySelector('.harmat-app-install');

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function(){
                    navigator.serviceWorker.register('/app/sw.js', { scope: '/app/' }).catch(function(){});
                });
            }

            window.addEventListener('beforeinstallprompt', function(event) {
                event.preventDefault();
                deferredInstallPrompt = event;
                if (installButton) installButton.hidden = false;
            });

            if (installButton) {
                installButton.addEventListener('click', function() {
                    if (!deferredInstallPrompt) return;
                    deferredInstallPrompt.prompt();
                    deferredInstallPrompt.userChoice.finally(function() {
                        deferredInstallPrompt = null;
                        installButton.hidden = true;
                    });
                });
            }
        })();
    </script>
</body>
</html>
    <?php
    exit;
}

add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }

    $path = harmat_app_portal_path_20260609();
    if ($path === 'app/manifest.webmanifest') {
        harmat_app_portal_manifest_20260609();
    }
    if ($path === 'app/sw.js') {
        harmat_app_portal_service_worker_20260609();
    }
    if ($path === 'app/demo') {
        harmat_app_portal_render_demo_20260609();
    }
    if ($path === 'app') {
        if (!empty($_GET['demo'])) {
            harmat_app_portal_render_demo_20260609();
        }
        harmat_app_portal_render_20260609();
    }
}, 0);
