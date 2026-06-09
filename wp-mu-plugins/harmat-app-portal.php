<?php
/**
 * Plugin Name: Harmat App Portal
 * Description: Lightweight mobile app entry for buyers, sales staff, and brokers.
 * Version: 0.2.0
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
const HARMAT_APP_CACHE = 'harmat-app-v4';
const HARMAT_APP_URLS = [
  '/app/?wp_lang=hu_HU',
  '/app/?wp_lang=zh_CN'
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
                ),
                array(
                    'key' => 'sales',
                    'mark' => '销',
                    'label' => '销售',
                    'sub' => '销售工作台',
                    'body' => '处理询价、跟单、成交客户、付款提醒和房源库存。',
                    'cta' => '进入销售通道',
                    'path' => '/sales/',
                ),
                array(
                    'key' => 'agent',
                    'mark' => '经',
                    'label' => '经纪人',
                    'sub' => '经纪人中心',
                    'body' => '登记客户、维护跟进、查看在售房源和佣金记录。',
                    'cta' => '进入经纪人通道',
                    'path' => '/agent/',
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
            ),
            array(
                'key' => 'sales',
                'mark' => 'É',
                'label' => 'Értékesítés',
                'sub' => 'Munkafelület',
                'body' => 'Érdeklődések, ügyek, lezárt ügyfelek, fizetések és lakáskészlet.',
                'cta' => 'Értékesítési belépés',
                'path' => '/sales/',
            ),
            array(
                'key' => 'agent',
                'mark' => 'K',
                'label' => 'Közvetítő',
                'sub' => 'Partnerfelület',
                'body' => 'Ügyfélrögzítés, követés, elérhető lakások és jutalékrekordok.',
                'cta' => 'Közvetítői belépés',
                'path' => '/agent/',
            ),
        ),
    );
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
            <footer class="harmat-app-foot">
                <span><?php echo esc_html($text['install']); ?></span>
                <button class="harmat-app-install" type="button" hidden><?php echo esc_html($text['install_cta']); ?></button>
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
    if ($path === 'app') {
        harmat_app_portal_render_20260609();
    }
}, 0);
