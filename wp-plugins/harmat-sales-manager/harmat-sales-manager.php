<?php
/**
 * Plugin Name: Harmat Sales Manager
 * Plugin URI: https://harmat22.hu
 * Description: Private sales dashboard for Harmat22 property status, prices, and broker accounts.
 * Version: 1.4.9
 * Author: Harmat22 Maintenance
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Harmat_Sales_Manager {
    const VERSION = '1.4.9';
    const PAGE_SLUG = 'harmat-sales-manager';
    const CAP_VIEW = 'harmat_view_sales';
    const CAP_MANAGE = 'harmat_manage_sales';
    const CAP_CUSTOMER = 'harmat_view_customer_portal';
    const ROLE_MANAGER = 'harmat_sales_manager';
    const ROLE_BROKER = 'harmat_broker_viewer';
    const ROLE_CUSTOMER = 'harmat_customer_owner';
    const LEAD_PROTECTION_DAYS = 30;

    public function __construct() {
        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
        add_action('init', array($this, 'disable_portal_caching'), 0);
        add_action('init', array($this, 'ensure_roles'), 5);
        add_action('init', array($this, 'register_shortcut'));
        add_action('init', array($this, 'handle_portal_auth_actions'));
        add_action('init', array($this, 'handle_actions'));
        add_action('send_headers', array($this, 'send_portal_nocache_headers'), 0);
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('template_redirect', array($this, 'handle_agent_portal'));
        add_action('template_redirect', array($this, 'handle_sales_portal'));
        add_action('template_redirect', array($this, 'handle_customer_portal'));
        add_action('template_redirect', array($this, 'handle_shortcut'));
        add_action('admin_menu', array($this, 'register_menu'), 20);
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_init', array($this, 'limit_private_roles'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('template_redirect', array($this, 'remove_lakaskereso_legacy_filters'), 1);
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'), 30);
        add_action('wp_head', array($this, 'frontend_structured_data'), 30);
        add_filter('wpcf7_posted_data', array($this, 'prefill_cf7_property_data'));
    }

    private function is_portal_request_path() {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');
        return in_array($path, array('client', 'customer', 'ugyfel', 'agent', 'sales'), true);
    }

    public function disable_portal_caching() {
        if (!$this->is_portal_request_path()) {
            return;
        }

        foreach (array('DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB') as $constant) {
            if (!defined($constant)) {
                define($constant, true);
            }
        }
    }

    public function send_portal_nocache_headers() {
        if ($this->is_portal_request_path()) {
            nocache_headers();
        }
    }

    public function remove_lakaskereso_legacy_filters() {
        if (is_admin() || wp_doing_ajax() || !(is_front_page() || is_page('lakaskereso'))) {
            return;
        }

        ob_start(function($html) {
            $html = preg_replace('~<div class="epl-premium-filter-wrapper">.*?</form>\s*</div>~is', '', $html);
            return $html;
        });
    }

    public static function activate() {
        add_role(self::ROLE_MANAGER, 'Harmat Sales Manager', array(
            'read' => true,
            self::CAP_VIEW => true,
            self::CAP_MANAGE => true,
        ));

        add_role(self::ROLE_BROKER, 'Harmat Broker Viewer', array(
            'read' => true,
            self::CAP_VIEW => true,
        ));

        add_role(self::ROLE_CUSTOMER, 'Harmat Customer Owner', array(
            'read' => true,
            self::CAP_CUSTOMER => true,
        ));

        foreach (array('administrator') as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->add_cap(self::CAP_VIEW);
                $role->add_cap(self::CAP_MANAGE);
                $role->add_cap(self::CAP_CUSTOMER);
            }
        }

        flush_rewrite_rules();
    }

    public function ensure_roles() {
        if (!get_role(self::ROLE_CUSTOMER)) {
            add_role(self::ROLE_CUSTOMER, 'Harmat Customer Owner', array(
                'read' => true,
                self::CAP_CUSTOMER => true,
            ));
        }

        $customer_role = get_role(self::ROLE_CUSTOMER);
        if ($customer_role && !$customer_role->has_cap(self::CAP_CUSTOMER)) {
            $customer_role->add_cap(self::CAP_CUSTOMER);
        }

        foreach (array('administrator') as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->add_cap(self::CAP_VIEW);
                $role->add_cap(self::CAP_MANAGE);
                $role->add_cap(self::CAP_CUSTOMER);
            }
        }
    }

    public function register_shortcut() {
        add_rewrite_rule('^agent/?$', 'index.php?harmat_agent_portal=1', 'top');
        add_rewrite_rule('^sales/?$', 'index.php?harmat_sales_portal=1', 'top');
        add_rewrite_rule('^client/?$', 'index.php?harmat_customer_portal=1', 'top');
        add_rewrite_rule('^customer/?$', 'index.php?harmat_customer_portal=1', 'top');
        add_rewrite_rule('^ugyfel/?$', 'index.php?harmat_customer_portal=1', 'top');
        add_rewrite_rule('^sales-admin/?$', 'index.php?harmat_sales_shortcut=1', 'top');
    }

    public function register_query_vars($vars) {
        $vars[] = 'harmat_agent_portal';
        $vars[] = 'harmat_sales_portal';
        $vars[] = 'harmat_customer_portal';
        $vars[] = 'harmat_sales_shortcut';
        return $vars;
    }

    public function handle_agent_portal() {
        if (!get_query_var('harmat_agent_portal')) {
            return;
        }

        if (!is_user_logged_in()) {
            $this->render_portal_login('agent');
            exit;
        }

        if (!current_user_can(self::CAP_VIEW)) {
            status_header(403);
            nocache_headers();
            echo '<!doctype html><html><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Harmat CRM</title></head><body style="font-family:Arial,sans-serif;background:#fbf5e8;margin:0;padding:40px;color:#253137;"><div style="max-width:720px;margin:auto;background:#fff;padding:28px;border-radius:18px;border:1px solid #ead9bc;"><h1>Hozzáférés szükséges</h1><p>Ez az oldal csak Harmat Lakópark értékesítési fiókkal érhető el.</p><p><a href="' . esc_url(wp_logout_url(home_url('/agent/'))) . '">Kilépés</a></p></div></body></html>';
            exit;
        }

        $this->render_agent_portal();
        exit;
    }

    public function handle_sales_portal() {
        if (!get_query_var('harmat_sales_portal')) {
            return;
        }

        if (!is_user_logged_in()) {
            $this->render_portal_login('sales');
            exit;
        }

        if (!current_user_can(self::CAP_MANAGE)) {
            if (current_user_can(self::CAP_VIEW)) {
                wp_safe_redirect(home_url('/agent/'));
                exit;
            }

            status_header(403);
            nocache_headers();
            echo '<!doctype html><html><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Harmat Sales</title></head><body style="font-family:Arial,sans-serif;background:#fbf5e8;margin:0;padding:40px;color:#253137;"><div style="max-width:720px;margin:auto;background:#fff;padding:28px;border-radius:18px;border:1px solid #ead9bc;"><h1>Hozzáférés szükséges</h1><p>Ez az oldal csak Harmat Lakópark értékesítési vezetői fiókkal érhető el.</p><p><a href="' . esc_url(wp_logout_url(home_url('/sales/'))) . '">Kilépés</a></p></div></body></html>';
            exit;
        }

        $this->render_sales_portal();
        exit;
    }

    public function handle_customer_portal() {
        if (!get_query_var('harmat_customer_portal')) {
            return;
        }

        if (!is_user_logged_in()) {
            $this->render_portal_login('client');
            exit;
        }

        if (!current_user_can(self::CAP_CUSTOMER) && !current_user_can(self::CAP_MANAGE)) {
            status_header(403);
            nocache_headers();
            echo '<!doctype html><html><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Harmat ügyfélközpont</title></head><body style="font-family:Arial,sans-serif;background:#fbf5e8;margin:0;padding:40px;color:#253137;"><div style="max-width:720px;margin:auto;background:#fff;padding:28px;border-radius:18px;border:1px solid #ead9bc;"><h1>Hozzáférés szükséges</h1><p>Ez az oldal csak Harmat Lakópark ügyfélfiókkal érhető el.</p><p><a href="' . esc_url(wp_logout_url(home_url('/client/'))) . '">Kilépés</a></p></div></body></html>';
            exit;
        }

        $deal = $this->current_customer_deal();
        $this->render_customer_portal($deal);
        exit;
    }


    public function handle_shortcut() {
        if (!get_query_var('harmat_sales_shortcut')) {
            return;
        }

        $target = admin_url('admin.php?page=' . self::PAGE_SLUG);
        if (is_user_logged_in()) {
            wp_safe_redirect($target);
        } else {
            wp_safe_redirect(wp_login_url($target));
        }
        exit;
    }

    public function handle_portal_auth_actions() {
        if (empty($_POST['harmat_portal_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['harmat_portal_action']));
        if (!in_array($action, array('login', 'lost_password'), true)) {
            return;
        }

        $portal = isset($_POST['harmat_portal']) ? sanitize_key(wp_unslash($_POST['harmat_portal'])) : 'client';
        if (!in_array($portal, array('sales', 'agent', 'client'), true)) {
            $portal = 'client';
        }

        $lang = isset($_POST['harmat_portal_lang']) ? sanitize_key(wp_unslash($_POST['harmat_portal_lang'])) : 'hu';
        if (!in_array($lang, array('hu', 'en', 'zh'), true)) {
            $lang = 'hu';
        }

        $redirect = $this->portal_url_with_lang($portal, $lang);
        $nonce_action = 'harmat_portal_' . $action . '_' . $portal;
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        $nonce_valid = wp_verify_nonce($nonce, $nonce_action);
        if (!$nonce_valid && $action !== 'login') {
            wp_safe_redirect(add_query_arg('auth_error', 'security', $redirect));
            exit;
        }

        if ($action === 'login') {
            $login = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : '';
            $password = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';

            if ($login === '' || $password === '') {
                wp_safe_redirect(add_query_arg('auth_error', 'empty', $redirect));
                exit;
            }

            $user = wp_signon(array(
                'user_login' => $login,
                'user_password' => $password,
                'remember' => !empty($_POST['rememberme']),
            ), is_ssl());

            if (is_wp_error($user)) {
                wp_safe_redirect(add_query_arg('auth_error', 'failed', $redirect));
                exit;
            }

            update_user_meta($user->ID, '_harmat_portal_lang_' . $portal, $lang);
            wp_safe_redirect($this->portal_url_with_lang($portal, $lang));
            exit;
        }

        $identifier = isset($_POST['user_login']) ? trim(sanitize_text_field(wp_unslash($_POST['user_login']))) : '';
        if ($identifier !== '') {
            $user = is_email($identifier) ? get_user_by('email', $identifier) : get_user_by('login', $identifier);
            if ($user) {
                $this->send_portal_password_reset($user, $portal, $lang);
            }
        }

        wp_safe_redirect(add_query_arg('reset_notice', 'sent', $this->portal_url_with_lang($portal, $lang)));
        exit;
    }

    private function send_portal_password_reset($user, $portal, $lang) {
        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            return false;
        }

        $text = $this->portal_auth_text($portal, $lang);
        $reset_url = network_site_url(
            'wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login),
            'login'
        );

        $subject = $text['reset_subject'];
        $message = $text['reset_email_intro'] . "\n\n";
        $message .= $reset_url . "\n\n";
        $message .= $text['reset_email_outro'] . "\n\n";
        $message .= 'Harmat Lakópark';

        return wp_mail($user->user_email, $subject, $message);
    }

    private function render_portal_login($portal) {
        nocache_headers();
        $portal = in_array($portal, array('sales', 'agent', 'client'), true) ? $portal : 'client';
        $lang = $this->portal_language_key();
        $text = $this->portal_auth_text($portal, $lang);
        $lost = !empty($_GET['lost_password']);
        $logo = get_site_icon_url(128);
        if (!$logo) {
            $logo = home_url('/wp-content/uploads/2025/11/cropped-Harmat_Logo_512-192x192.png');
        }

        $notice = $this->portal_auth_notice($text);
        $action_url = $lost
            ? add_query_arg(array('lost_password' => 1), $this->portal_url_with_lang($portal, $lang))
            : $this->portal_url_with_lang($portal, $lang);
        $login_url = $this->portal_url_with_lang($portal, $lang);
        $lost_url = add_query_arg('lost_password', 1, $login_url);

        echo '<!doctype html><html lang="' . esc_attr($text['html_lang']) . '"><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="robots" content="noindex,nofollow"><title>' . esc_html($text['title']) . '</title><style>' . $this->portal_login_css() . '</style></head>';
        echo '<body class="harmat-portal-login harmat-portal-login-' . esc_attr($portal) . '">';
        echo '<header class="harmat-portal-login-header"><a class="harmat-portal-brand" href="' . esc_url(home_url('/')) . '"><img src="' . esc_url($logo) . '" alt="Harmat"><span>Harmat Lakópark</span></a>';
        echo '<nav class="harmat-portal-lang" aria-label="Language">';
        foreach (array('hu' => 'Magyar', 'en' => 'English', 'zh' => '中文') as $code => $label) {
            echo '<a class="' . ($lang === $code ? 'is-active' : '') . '" href="' . esc_url($this->portal_language_url($portal, $code, $lost)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav></header>';
        echo '<main class="harmat-portal-login-main">';
        echo '<section class="harmat-portal-login-copy"><p class="harmat-portal-eyebrow">' . esc_html($text['eyebrow']) . '</p><h1>' . esc_html($text['hero_title']) . '</h1><p class="harmat-portal-lead">' . esc_html($text['hero_intro']) . '</p><ul>';
        foreach ($text['bullets'] as $bullet) {
            echo '<li>' . esc_html($bullet) . '</li>';
        }
        echo '</ul></section>';

        echo '<section class="harmat-portal-login-panel"><p class="harmat-portal-eyebrow">' . esc_html($text['panel_eyebrow']) . '</p><h2>' . esc_html($lost ? $text['lost_title'] : $text['login_title']) . '</h2><p>' . esc_html($lost ? $text['lost_intro'] : $text['login_intro']) . '</p>';
        if ($notice) {
            echo '<div class="harmat-portal-notice harmat-portal-notice-' . esc_attr($notice['type']) . '">' . esc_html($notice['message']) . '</div>';
        }

        if ($lost) {
            echo '<form method="post" action="' . esc_url($action_url) . '" class="harmat-portal-form">';
            wp_nonce_field('harmat_portal_lost_password_' . $portal);
            echo '<input type="hidden" name="harmat_portal_action" value="lost_password"><input type="hidden" name="harmat_portal" value="' . esc_attr($portal) . '"><input type="hidden" name="harmat_portal_lang" value="' . esc_attr($lang) . '">';
            echo '<label>' . esc_html($text['field_user']) . '<input type="text" name="user_login" autocomplete="username" required></label>';
            echo '<button type="submit">' . esc_html($text['lost_submit']) . '</button>';
            echo '</form><a class="harmat-portal-muted-link" href="' . esc_url($login_url) . '">' . esc_html($text['back_to_login']) . '</a>';
        } else {
            echo '<form method="post" action="' . esc_url($action_url) . '" class="harmat-portal-form">';
            wp_nonce_field('harmat_portal_login_' . $portal);
            echo '<input type="hidden" name="harmat_portal_action" value="login"><input type="hidden" name="harmat_portal" value="' . esc_attr($portal) . '"><input type="hidden" name="harmat_portal_lang" value="' . esc_attr($lang) . '">';
            echo '<label>' . esc_html($text['field_user']) . '<input type="text" name="log" autocomplete="username" required></label>';
            echo '<label>' . esc_html($text['field_password']) . '<input type="password" name="pwd" autocomplete="current-password" required></label>';
            echo '<label class="harmat-portal-check"><input type="checkbox" name="rememberme" value="forever"><span>' . esc_html($text['remember']) . '</span></label>';
            echo '<button type="submit">' . esc_html($text['login_submit']) . '</button>';
            echo '</form><a class="harmat-portal-muted-link" href="' . esc_url($lost_url) . '">' . esc_html($text['forgot']) . '</a>';
        }
        echo '</section></main></body></html>';
    }

    private function portal_auth_notice($text) {
        $error = isset($_GET['auth_error']) ? sanitize_key(wp_unslash($_GET['auth_error'])) : '';
        if ($error === 'empty') {
            return array('type' => 'error', 'message' => $text['error_empty']);
        }
        if ($error === 'failed') {
            return array('type' => 'error', 'message' => $text['error_failed']);
        }
        if ($error === 'security') {
            return array('type' => 'error', 'message' => $text['error_security']);
        }
        $reset = isset($_GET['reset_notice']) ? sanitize_key(wp_unslash($_GET['reset_notice'])) : '';
        if ($reset === 'sent') {
            return array('type' => 'success', 'message' => $text['reset_sent']);
        }
        return null;
    }

    private function portal_language_key() {
        $locale = isset($_GET['wp_lang']) ? sanitize_text_field(wp_unslash($_GET['wp_lang'])) : 'hu';
        if (stripos($locale, 'zh') === 0) {
            return 'zh';
        }
        if (stripos($locale, 'en') === 0) {
            return 'en';
        }
        return 'hu';
    }

    private function current_portal_language($portal) {
        $portal = in_array($portal, array('sales', 'agent', 'client'), true) ? $portal : 'client';
        if (isset($_GET['wp_lang'])) {
            $lang = $this->portal_language_key();
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), '_harmat_portal_lang_' . $portal, $lang);
            }
            return $lang;
        }

        if (is_user_logged_in()) {
            $saved = get_user_meta(get_current_user_id(), '_harmat_portal_lang_' . $portal, true);
            if (in_array($saved, array('hu', 'en', 'zh'), true)) {
                return $saved;
            }
        }

        return $portal === 'sales' ? 'zh' : 'hu';
    }

    private function portal_html_lang($lang) {
        if ($lang === 'zh') {
            return 'zh-CN';
        }
        if ($lang === 'en') {
            return 'en';
        }
        return 'hu';
    }

    private function portal_url($portal) {
        if ($portal === 'sales') {
            return home_url('/sales/');
        }
        if ($portal === 'agent') {
            return home_url('/agent/');
        }
        return home_url('/client/');
    }

    private function portal_url_with_lang($portal, $lang) {
        $locale = $lang === 'zh' ? 'zh_CN' : ($lang === 'en' ? 'en_US' : 'hu_HU');
        return add_query_arg('wp_lang', $locale, $this->portal_url($portal));
    }

    private function portal_language_url($portal, $lang, $lost = false) {
        $url = $this->portal_url_with_lang($portal, $lang);
        return $lost ? add_query_arg('lost_password', 1, $url) : $url;
    }

    private function portal_logged_language_switch($portal, $lang) {
        $html = '<nav class="harmat-portal-mini-lang" aria-label="Language">';
        foreach (array('hu' => 'HU', 'en' => 'EN', 'zh' => '中文') as $code => $label) {
            $html .= '<a class="' . ($lang === $code ? 'is-active' : '') . '" href="' . esc_url($this->portal_url_with_lang($portal, $code)) . '">' . esc_html($label) . '</a>';
        }
        $html .= '</nav>';
        return $html;
    }

    private function portal_auth_text($portal, $lang) {
        $texts = $this->portal_auth_texts();
        $lang = isset($texts[$lang]) ? $lang : 'hu';
        $portal = in_array($portal, array('sales', 'agent', 'client'), true) ? $portal : 'client';
        return array_merge($texts[$lang]['common'], $texts[$lang][$portal]);
    }

    private function portal_auth_texts() {
        return array(
            'hu' => array(
                'common' => array(
                    'html_lang' => 'hu',
                    'field_user' => 'Felhasználónév vagy e-mail-cím',
                    'field_password' => 'Jelszó',
                    'remember' => 'Emlékezzen rám',
                    'login_submit' => 'Belépés',
                    'forgot' => 'Elfelejtette a jelszavát?',
                    'lost_title' => 'Új jelszó kérése',
                    'lost_intro' => 'Adja meg felhasználónevét vagy e-mail-címét. Ha a fiók létezik, e-mailben küldünk biztonságos jelszó-visszaállító linket.',
                    'lost_submit' => 'Visszaállító link küldése',
                    'back_to_login' => 'Vissza a bejelentkezéshez',
                    'error_empty' => 'Kérjük, adja meg a felhasználónevet és a jelszót.',
                    'error_failed' => 'A megadott belépési adatok nem megfelelőek.',
                    'error_security' => 'A munkamenet lejárt. Kérjük, próbálja újra.',
                    'reset_sent' => 'Ha a fiók létezik, elküldtük a jelszó-visszaállító e-mailt.',
                    'reset_subject' => 'Harmat Lakópark jelszó-visszaállítás',
                    'reset_email_intro' => 'Jelszó-visszaállítási kérelmet kaptunk a Harmat Lakópark felületéhez. Az új jelszó beállításához nyissa meg az alábbi linket:',
                    'reset_email_outro' => 'Ha nem Ön kérte, hagyja figyelmen kívül ezt az üzenetet.',
                ),
                'client' => array(
                    'title' => 'Harmat ügyfélkapu',
                    'eyebrow' => 'Ügyfélkapu',
                    'hero_title' => 'Saját lakásinformációk biztonságosan',
                    'hero_intro' => 'A Harmat Lakópark ügyfélfelületén lakása státuszát, dokumentumait, fizetési és átadási információit követheti.',
                    'bullets' => array('Lakásadatok és státuszok', 'Fizetési és szerződéses információk', 'Projektanyagok és előrehaladási fotók'),
                    'panel_eyebrow' => 'Harmat Lakópark',
                    'login_title' => 'Ügyfél bejelentkezés',
                    'login_intro' => 'Lépjen be a kapott ügyfélfiókkal.',
                ),
                'agent' => array(
                    'title' => 'Harmat közvetítői belépés',
                    'eyebrow' => 'Közvetítői felület',
                    'hero_title' => 'Ügyfelek, védelem és jutalékok egy helyen',
                    'hero_intro' => 'A közvetítői munkafelület az ügyfélregisztrációhoz, követéshez, elérhető lakásokhoz és jutalékok áttekintéséhez készült.',
                    'bullets' => array('Saját ügyfelek és 30 napos védelem', 'Elérhető lakások és készletadatok', 'Lezárt ügyletek és jutalékok'),
                    'panel_eyebrow' => 'Harmat Lakópark',
                    'login_title' => 'Közvetítői bejelentkezés',
                    'login_intro' => 'Lépjen be közvetítői fiókjával.',
                ),
                'sales' => array(
                    'title' => 'Harmat értékesítési belépés',
                    'eyebrow' => 'Értékesítési felület',
                    'hero_title' => 'Érdeklődések, ügyfelek és lakáskészlet kezelése',
                    'hero_intro' => 'Az értékesítési munkafelület a napi ügyfélkezeléshez, lakásstátuszokhoz, jutalékokhoz és ügyfélanyagokhoz készült.',
                    'bullets' => array('Weboldali érdeklődések és értékesítési követés', 'Lakásárak, státuszok és készletadatok', 'Közvetítők, jutalékok és ügyfélanyagok'),
                    'panel_eyebrow' => 'Harmat Lakópark',
                    'login_title' => 'Értékesítési bejelentkezés',
                    'login_intro' => 'Lépjen be értékesítési jogosultságú fiókkal.',
                ),
            ),
            'en' => array(
                'common' => array(
                    'html_lang' => 'en',
                    'field_user' => 'Username or e-mail address',
                    'field_password' => 'Password',
                    'remember' => 'Remember me',
                    'login_submit' => 'Login',
                    'forgot' => 'Forgot your password?',
                    'lost_title' => 'Request a new password',
                    'lost_intro' => 'Enter your username or e-mail address. If the account exists, we will send a secure password reset link.',
                    'lost_submit' => 'Send reset link',
                    'back_to_login' => 'Back to login',
                    'error_empty' => 'Please enter your username and password.',
                    'error_failed' => 'The login details are not correct.',
                    'error_security' => 'The session has expired. Please try again.',
                    'reset_sent' => 'If the account exists, we have sent the password reset e-mail.',
                    'reset_subject' => 'Harmat Lakópark password reset',
                    'reset_email_intro' => 'We received a password reset request for the Harmat Lakópark portal. Open the link below to set a new password:',
                    'reset_email_outro' => 'If you did not request this, please ignore this message.',
                ),
                'client' => array(
                    'title' => 'Harmat client portal',
                    'eyebrow' => 'Client portal',
                    'hero_title' => 'Your apartment information, securely',
                    'hero_intro' => 'The Harmat Lakópark client portal lets you follow apartment status, documents, payment details and handover information.',
                    'bullets' => array('Apartment data and status', 'Payment and contract information', 'Project materials and progress photos'),
                    'panel_eyebrow' => 'Harmat Lakópark',
                    'login_title' => 'Client login',
                    'login_intro' => 'Sign in with the client account you received.',
                ),
                'agent' => array(
                    'title' => 'Harmat agent login',
                    'eyebrow' => 'Agent workspace',
                    'hero_title' => 'Clients, protection and commissions in one place',
                    'hero_intro' => 'The agent workspace is built for client registration, follow-up, available apartments and commission review.',
                    'bullets' => array('Own clients and 30-day protection', 'Available apartments and stock data', 'Closed deals and commissions'),
                    'panel_eyebrow' => 'Harmat Lakópark',
                    'login_title' => 'Agent login',
                    'login_intro' => 'Sign in with your agent account.',
                ),
                'sales' => array(
                    'title' => 'Harmat sales login',
                    'eyebrow' => 'Sales workspace',
                    'hero_title' => 'Manage inquiries, clients and apartment stock',
                    'hero_intro' => 'The sales workspace supports daily customer handling, apartment statuses, commissions and customer materials.',
                    'bullets' => array('Website inquiries and sales follow-up', 'Apartment prices, statuses and stock data', 'Agents, commissions and customer materials'),
                    'panel_eyebrow' => 'Harmat Lakópark',
                    'login_title' => 'Sales login',
                    'login_intro' => 'Sign in with a sales-authorized account.',
                ),
            ),
            'zh' => array(
                'common' => array(
                    'html_lang' => 'zh-CN',
                    'field_user' => '用户名或邮箱',
                    'field_password' => '密码',
                    'remember' => '记住我',
                    'login_submit' => '登录',
                    'forgot' => '忘记密码？',
                    'lost_title' => '重置密码',
                    'lost_intro' => '请输入用户名或邮箱。如果账号存在，系统会发送安全的密码重置链接。',
                    'lost_submit' => '发送重置链接',
                    'back_to_login' => '返回登录',
                    'error_empty' => '请输入用户名和密码。',
                    'error_failed' => '登录信息不正确。',
                    'error_security' => '当前会话已失效，请重新尝试。',
                    'reset_sent' => '如果账号存在，密码重置邮件已经发送。',
                    'reset_subject' => 'Harmat Lakópark 密码重置',
                    'reset_email_intro' => '我们收到了 Harmat Lakópark 系统的密码重置请求。请打开下面的链接设置新密码：',
                    'reset_email_outro' => '如果不是您本人操作，请忽略这封邮件。',
                ),
                'client' => array(
                    'title' => 'Harmat 客户中心',
                    'eyebrow' => '客户入口',
                    'hero_title' => '安全查看您的房屋资料',
                    'hero_intro' => '客户中心用于查看房屋状态、合同资料、付款信息、交付信息和项目进展。',
                    'bullets' => array('房屋资料和当前状态', '付款、合同和交付信息', '项目资料和进展照片'),
                    'panel_eyebrow' => 'Harmat Lakópark',
                    'login_title' => '客户登录',
                    'login_intro' => '请使用销售人员发送给您的客户账号登录。',
                ),
                'agent' => array(
                    'title' => 'Harmat 经纪人入口',
                    'eyebrow' => '经纪人工作台',
                    'hero_title' => '客户保护、房源和佣金集中管理',
                    'hero_intro' => '经纪人工作台用于登记客户、维护保护期、查看房源和跟踪成交佣金。',
                    'bullets' => array('自己的客户和30天保护期', '可售房源和库存数据', '成交记录和佣金状态'),
                    'panel_eyebrow' => 'Harmat Lakópark',
                    'login_title' => '经纪人登录',
                    'login_intro' => '请使用经纪人账号登录。',
                ),
                'sales' => array(
                    'title' => 'Harmat 销售管理',
                    'eyebrow' => '销售工作台',
                    'hero_title' => '询价、客户、房源库存集中管理',
                    'hero_intro' => '销售管理入口用于处理网站询价、客户跟进、房源状态、经纪人佣金和客户资料。',
                    'bullets' => array('网站询价和销售跟单', '房源价格、状态和库存', '经纪人、佣金和客户材料'),
                    'panel_eyebrow' => 'Harmat Lakópark',
                    'login_title' => '销售登录',
                    'login_intro' => '请使用销售管理账号登录。',
                ),
            ),
        );
    }

    private function portal_login_css() {
        return '
            html, body { margin:0; min-height:100%; }
            body.harmat-portal-login {
                min-height:100vh;
                background:#fbf4e8;
                color:#253137;
                font-family:Inter, Arial, sans-serif;
                overflow-x:hidden;
            }
            .harmat-portal-login * { box-sizing:border-box; letter-spacing:0; }
            .harmat-portal-login-header {
                height:86px;
                display:flex;
                align-items:center;
                justify-content:space-between;
                padding:0 clamp(22px, 5vw, 78px);
                background:rgba(255,253,249,.94);
                border-bottom:1px solid #e5c796;
            }
            .harmat-portal-brand {
                display:inline-flex;
                align-items:center;
                gap:12px;
                color:#b7832c;
                text-decoration:none;
                font-weight:800;
                text-transform:uppercase;
                font-size:13px;
            }
            .harmat-portal-brand img { width:54px; height:54px; object-fit:contain; }
            .harmat-portal-lang { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
            .harmat-portal-lang a {
                min-height:36px;
                display:inline-flex;
                align-items:center;
                padding:0 14px;
                border:1px solid #e2c89a;
                color:#8c621f;
                text-decoration:none;
                border-radius:999px;
                font-size:13px;
                font-weight:800;
            }
            .harmat-portal-lang a.is-active,
            .harmat-portal-lang a:hover { background:#b7832c; border-color:#b7832c; color:#fff; }
            .harmat-portal-login-main {
                width:min(1180px, calc(100% - 36px));
                min-height:calc(100vh - 86px);
                margin:0 auto;
                padding:clamp(36px, 6vw, 76px) 0;
                display:grid;
                grid-template-columns:minmax(0, 1fr) minmax(390px, 500px);
                align-items:center;
                gap:clamp(32px, 6vw, 72px);
            }
            .harmat-portal-login-copy {
                padding:0;
                min-width:0;
                display:flex;
                flex-direction:column;
                justify-content:center;
                background:transparent;
            }
            .harmat-portal-eyebrow {
                margin:0 0 16px;
                color:#b7832c;
                font-size:12px;
                font-weight:900;
                text-transform:uppercase;
            }
            .harmat-portal-login-copy h1 {
                max-width:820px;
                margin:0;
                color:#253137;
                font-family:Georgia, "Times New Roman", serif;
                font-size:clamp(36px, 4vw, 58px);
                line-height:1.08;
                font-weight:500;
                text-transform:uppercase;
                overflow-wrap:break-word;
            }
            .harmat-portal-lead {
                max-width:660px;
                margin:24px 0 0;
                color:#667178;
                font-size:17px;
                line-height:1.75;
            }
            .harmat-portal-login-copy ul {
                max-width:620px;
                margin:34px 0 0;
                padding:0;
                display:grid;
                gap:12px;
                list-style:none;
            }
            .harmat-portal-login-copy li {
                min-height:44px;
                display:flex;
                align-items:center;
                gap:10px;
                padding:0 16px;
                border:1px solid #efdcb9;
                background:rgba(255,253,249,.72);
                color:#5f696f;
                font-size:15px;
            }
            .harmat-portal-login-copy li:before {
                content:"";
                width:8px;
                height:8px;
                border-radius:50%;
                background:#d4a04a;
                flex:0 0 8px;
            }
            .harmat-portal-login-panel {
                padding:clamp(30px, 4vw, 42px);
                display:flex;
                flex-direction:column;
                justify-content:center;
                background:#fffdf9;
                border:1px solid #e5c796;
                border-radius:18px;
                box-shadow:0 20px 55px rgba(70,54,28,.08);
            }
            .harmat-portal-login-panel h2 {
                margin:0 0 12px;
                color:#253137;
                font-family:Georgia, "Times New Roman", serif;
                font-size:34px;
                line-height:1.12;
                font-weight:500;
            }
            .harmat-portal-login-panel p {
                margin:0 0 22px;
                color:#6b7479;
                font-size:15px;
                line-height:1.65;
            }
            .harmat-portal-form { display:grid; gap:16px; }
            .harmat-portal-form label {
                display:grid;
                gap:8px;
                color:#334148;
                font-size:12px;
                font-weight:900;
                text-transform:uppercase;
            }
            .harmat-portal-form input[type="text"],
            .harmat-portal-form input[type="password"] {
                width:100%;
                min-height:50px;
                padding:0 15px;
                border:1px solid #c9913c;
                background:#fffefa;
                color:#253137;
                font-size:15px;
                outline:none;
            }
            .harmat-portal-form input:focus { border-color:#1e7b78; box-shadow:0 0 0 3px rgba(30,123,120,.12); }
            .harmat-portal-check {
                display:flex !important;
                grid-template-columns:auto 1fr;
                align-items:center;
                gap:8px !important;
                color:#586267 !important;
                text-transform:none !important;
            }
            .harmat-portal-check input { width:18px; height:18px; margin:0; }
            .harmat-portal-form button {
                min-height:52px;
                border:0;
                background:#b7832c;
                color:#fff;
                font-size:13px;
                font-weight:900;
                text-transform:uppercase;
                cursor:pointer;
            }
            .harmat-portal-form button:hover { background:#9d6f25; }
            .harmat-portal-muted-link {
                margin-top:18px;
                min-height:42px;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                padding:0 16px;
                border:1px solid #e2c89a;
                border-radius:999px;
                color:#8c621f;
                background:#fff9ef;
                text-decoration:none;
                font-size:13px;
                font-weight:900;
                align-self:flex-start;
            }
            .harmat-portal-notice {
                margin:0 0 18px;
                padding:13px 14px;
                border:1px solid #e2c89a;
                background:#fff9ef;
                color:#253137;
                font-size:13px;
                line-height:1.45;
            }
            .harmat-portal-notice-error { border-color:#df8b7d; background:#fff3f0; }
            .harmat-portal-notice-success { border-color:#83b88a; background:#f3fff5; }
            @media (max-width: 900px) {
                .harmat-portal-login-header { height:auto; min-height:82px; gap:18px; align-items:flex-start; flex-direction:column; padding:18px 22px; }
                .harmat-portal-lang { justify-content:flex-start; }
                .harmat-portal-login-main { width:min(100% - 24px, 720px); grid-template-columns:1fr; padding:28px 0 44px; }
                .harmat-portal-login-copy, .harmat-portal-login-panel, .harmat-portal-lead, .harmat-portal-login-copy ul { width:100%; max-width:100%; }
                .harmat-portal-login-copy { padding:0; }
                .harmat-portal-login-copy h1 { font-size:32px; line-height:1.16; text-transform:none; overflow-wrap:break-word; }
                .harmat-portal-lead { font-size:15px; }
                .harmat-portal-login-copy li { min-height:38px; font-size:13px; }
                .harmat-portal-login-panel { padding:26px 20px 30px; }
                .harmat-portal-muted-link { align-self:stretch; }
            }
        ';
    }

    public function register_menu() {
        add_menu_page(
            'Harmat销售管理',
            'Harmat销售管理',
            self::CAP_VIEW,
            self::PAGE_SLUG,
            array($this, 'render_page'),
            'dashicons-chart-line',
            3
        );
    }

    public function admin_assets($hook) {
        if ($hook !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_register_style('harmat-sales-manager-admin', false, array(), self::VERSION);
        wp_enqueue_style('harmat-sales-manager-admin');
        wp_add_inline_style('harmat-sales-manager-admin', $this->css());

        wp_register_script('harmat-sales-manager-admin', false, array(), self::VERSION, true);
        wp_enqueue_script('harmat-sales-manager-admin');
        wp_add_inline_script('harmat-sales-manager-admin', $this->js());
    }

    public function frontend_assets() {
        if (is_admin()) {
            return;
        }

        if (!$this->should_load_frontend_assets()) {
            return;
        }

        $items = is_singular('property')
            ? $this->frontend_sales_data(array(get_queried_object_id()))
            : $this->frontend_sales_data();
        if (!$items) {
            return;
        }

        wp_register_style('harmat-sales-manager-front', false, array(), self::VERSION);
        wp_enqueue_style('harmat-sales-manager-front');
        wp_add_inline_style('harmat-sales-manager-front', $this->frontend_css());

        wp_register_script('harmat-sales-manager-front', false, array(), self::VERSION, true);
        wp_enqueue_script('harmat-sales-manager-front');
        wp_add_inline_script(
            'harmat-sales-manager-front',
            'window.harmatSalesFront=' . wp_json_encode(array('items' => $items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';',
            'before'
        );
        wp_add_inline_script('harmat-sales-manager-front', $this->frontend_js());
    }

    private function should_load_frontend_assets() {
        if (is_front_page() || is_singular('property') || is_post_type_archive('property')) {
            return true;
        }

        if (is_page()) {
            $slug = get_post_field('post_name', get_queried_object_id());
            return in_array($slug, array('lakaskereso', 'ajanlatkeres', 'idopont-foglalas'), true);
        }

        return false;
    }

    public function frontend_structured_data() {
        if (!is_singular('property')) {
            return;
        }

        $post_id = get_queried_object_id();
        $price = (int) get_post_meta($post_id, 'property_price', true);
        $sales_area = $this->get_sales_area($post_id);
        $status = $this->sales_status($post_id);
        $hide_price = get_post_meta($post_id, '_harmat_hide_front_price', true) === 'yes';
        $availability = $status === 'sold' ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock';

        if ($hide_price || !$price || !$sales_area) {
            return;
        }

        $data = array(
            '@context' => 'https://schema.org',
            '@type' => 'Apartment',
            'name' => get_the_title($post_id),
            'url' => get_permalink($post_id),
            'floorSize' => array(
                '@type' => 'QuantitativeValue',
                'value' => $sales_area,
                'unitText' => 'MTK',
            ),
            'offers' => array(
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => 'HUF',
                'availability' => $availability,
                'url' => get_permalink($post_id),
            ),
        );

        echo '<script type="application/ld+json" class="harmat-sales-structured-data">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    public function prefill_cf7_property_data($posted_data) {
        if (!is_array($posted_data)) {
            return $posted_data;
        }

        $has_property_fields = array_intersect(
            array('selected-building', 'selected-floor', 'selected-apartment', 'selected-area', 'selected-rooms', 'selected-price', 'selected-url'),
            array_keys($posted_data)
        );
        if (!$has_property_fields || !empty($posted_data['selected-apartment'])) {
            return $posted_data;
        }

        $referer = wp_get_referer();
        if (!$referer && !empty($_SERVER['HTTP_REFERER'])) {
            $referer = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        }

        $post_id = $referer ? url_to_postid($referer) : 0;
        if (!$post_id || get_post_type($post_id) !== 'property') {
            return $posted_data;
        }

        $price = (int) get_post_meta($post_id, 'property_price', true);
        $sales_area = $this->get_sales_area($post_id);
        $rooms = get_post_meta($post_id, 'property_rooms', true);
        $bedrooms = get_post_meta($post_id, 'property_bedrooms', true);

        $posted_data['selected-building'] = get_post_meta($post_id, 'property_address_street', true);
        $posted_data['selected-floor'] = get_post_meta($post_id, 'property_address_street_number', true);
        $posted_data['selected-apartment'] = get_the_title($post_id);
        $posted_data['selected-area'] = $this->format_area($sales_area) . ' m²';
        $posted_data['selected-rooms'] = trim($rooms . ' szoba' . ($bedrooms ? ' / ' . $bedrooms . ' háló' : ''));
        $posted_data['selected-price'] = $this->format_money($price) . ' Ft';
        $posted_data['selected-url'] = get_permalink($post_id);

        if (empty($posted_data['your-message'])) {
            $posted_data['your-message'] = 'A ' . get_the_title($post_id) . ' lakás iránt érdeklődöm.';
        }

        return $posted_data;
    }

    public function limit_private_roles() {
        if (!is_admin() || wp_doing_ajax() || current_user_can('manage_options')) {
            return;
        }

        if ($this->is_customer_user()) {
            wp_safe_redirect(home_url('/client/'));
            exit;
        }

        if (!$this->is_private_sales_user()) {
            return;
        }

        remove_menu_page('index.php');
        remove_menu_page('profile.php');

        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($GLOBALS['pagenow'] === 'admin.php' && $page === self::PAGE_SLUG) {
            return;
        }

        if ($GLOBALS['pagenow'] === 'profile.php') {
            return;
        }

        if ($GLOBALS['pagenow'] !== 'admin.php') {
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }
    }

    private function is_private_sales_user() {
        $user = wp_get_current_user();
        return array_intersect(array(self::ROLE_MANAGER, self::ROLE_BROKER), (array) $user->roles);
    }

    private function is_customer_user() {
        $user = wp_get_current_user();
        return in_array(self::ROLE_CUSTOMER, (array) $user->roles, true);
    }

    public function handle_actions() {
        if (!isset($_POST['harmat_sales_action'])) {
            return;
        }

        $action = sanitize_key($_POST['harmat_sales_action']);
        if (!current_user_can(self::CAP_VIEW)) {
            wp_die('Nincs jogosultság.');
        }

        check_admin_referer('harmat_sales_action_' . $action);

        if ($action === 'update_property') {
            $this->handle_property_update();
        }

        if ($action === 'bulk_update_properties') {
            $this->handle_bulk_update_properties();
        }

        if ($action === 'create_user') {
            $this->handle_user_create();
        }

        if ($action === 'update_user') {
            $this->handle_user_update();
        }

        if ($action === 'reset_password') {
            $this->handle_password_reset();
        }

        if ($action === 'delete_user') {
            $this->handle_user_delete();
        }

        if ($action === 'save_lead') {
            $this->handle_lead_save();
        }

        if ($action === 'delete_lead') {
            $this->handle_lead_delete();
        }

        if ($action === 'save_deal') {
            $this->handle_deal_save();
        }

        if ($action === 'generate_customer_account') {
            $this->handle_customer_account_generate();
        }

        if ($action === 'reset_customer_account_password') {
            $this->handle_customer_account_password_reset();
        }

        if ($action === 'upload_customer_material') {
            $this->handle_customer_material_upload();
        }

        if ($action === 'delete_customer_material') {
            $this->handle_customer_material_delete();
        }

        if ($action === 'delete_deal') {
            $this->handle_deal_delete();
        }
    }

    private function handle_property_update() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság a módosításhoz.');
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id || get_post_type($post_id) !== 'property') {
            wp_die('Hibás lakás azonosító.');
        }

        $price = isset($_POST['property_price']) ? preg_replace('/[^\d]/', '', wp_unslash($_POST['property_price'])) : '';
        $status = isset($_POST['sales_status']) ? sanitize_key($_POST['sales_status']) : 'current';
        $note = isset($_POST['sales_note']) ? sanitize_textarea_field(wp_unslash($_POST['sales_note'])) : '';

        update_post_meta($post_id, 'property_price', $price);
        update_post_meta($post_id, 'property_price_global', $price);
        update_post_meta($post_id, '_harmat_sales_note', $note);
        update_post_meta($post_id, '_harmat_sales_updated_by', get_current_user_id());
        update_post_meta($post_id, '_harmat_sales_updated_at', current_time('mysql'));

        $price_visibility = isset($_POST['price_visibility']) ? sanitize_key($_POST['price_visibility']) : '';
        if ($price_visibility === 'hide') {
            update_post_meta($post_id, '_harmat_hide_front_price', 'yes');
        } elseif ($price_visibility === 'show') {
            delete_post_meta($post_id, '_harmat_hide_front_price');
        }

        $this->set_property_sales_state($post_id, $status, $price);

        wp_safe_redirect($this->property_return_url(array('updated' => '1', 'property' => $post_id)));
        exit;
    }

    private function set_property_sales_state($post_id, $status, $price = null) {
        if ($status === 'sold') {
            update_post_meta($post_id, 'property_status', 'sold');
            update_post_meta($post_id, 'property_under_offer', '');
            if ($price !== null) {
                update_post_meta($post_id, 'property_sold_price', $price);
            }
            update_post_meta($post_id, 'property_sold_price_display', 'yes');
            if (!get_post_meta($post_id, 'property_sold_date', true)) {
                update_post_meta($post_id, 'property_sold_date', current_time('Y-m-d'));
            }
        } elseif ($status === 'reserved') {
            update_post_meta($post_id, 'property_status', 'current');
            update_post_meta($post_id, 'property_under_offer', 'yes');
            update_post_meta($post_id, 'property_sold_price', '');
            update_post_meta($post_id, 'property_sold_date', '');
            update_post_meta($post_id, 'property_sold_price_display', '');
        } else {
            update_post_meta($post_id, 'property_status', 'current');
            update_post_meta($post_id, 'property_under_offer', '');
            update_post_meta($post_id, 'property_sold_price', '');
            update_post_meta($post_id, 'property_sold_date', '');
            update_post_meta($post_id, 'property_sold_price_display', '');
        }
    }

    private function handle_bulk_update_properties() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság a módosításhoz.');
        }

        $ids = isset($_POST['bulk_property_ids']) ? array_map('absint', (array) $_POST['bulk_property_ids']) : array();
        $ids = array_values(array_filter(array_unique($ids), function($post_id) {
            return $post_id && get_post_type($post_id) === 'property';
        }));

        if (!$ids) {
            wp_safe_redirect($this->page_url(array('bulk_empty' => '1')));
            exit;
        }

        $bulk_status = isset($_POST['bulk_status']) ? sanitize_key($_POST['bulk_status']) : '';
        $price_visibility = isset($_POST['price_visibility']) ? sanitize_key($_POST['price_visibility']) : '';
        $valid_statuses = array_keys($this->status_options());
        $backup = array();

        foreach ($ids as $post_id) {
            $backup[$post_id] = array(
                'title' => get_the_title($post_id),
                'property_status' => get_post_meta($post_id, 'property_status', true),
                'property_under_offer' => get_post_meta($post_id, 'property_under_offer', true),
                'property_price' => get_post_meta($post_id, 'property_price', true),
                '_harmat_hide_front_price' => get_post_meta($post_id, '_harmat_hide_front_price', true),
            );
        }

        $backup_key = 'harmat_sales_bulk_backup_' . current_time('Ymd_His');
        add_option($backup_key, array(
            'created_at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'items' => $backup,
        ), '', false);

        $changed = 0;
        foreach ($ids as $post_id) {
            if ($bulk_status && in_array($bulk_status, $valid_statuses, true)) {
                $this->set_property_sales_state($post_id, $bulk_status, get_post_meta($post_id, 'property_price', true));
            }

            if ($price_visibility === 'hide') {
                update_post_meta($post_id, '_harmat_hide_front_price', 'yes');
            } elseif ($price_visibility === 'show') {
                delete_post_meta($post_id, '_harmat_hide_front_price');
            }

            update_post_meta($post_id, '_harmat_sales_updated_by', get_current_user_id());
            update_post_meta($post_id, '_harmat_sales_updated_at', current_time('mysql'));
            $changed++;
        }

        wp_safe_redirect($this->page_url(array('bulk_updated' => $changed, 'bulk_backup' => $backup_key)));
        exit;
    }

    private function handle_user_create() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság felhasználó létrehozásához.');
        }

        $role = isset($_POST['new_role']) ? sanitize_key($_POST['new_role']) : self::ROLE_BROKER;
        if ($role === self::ROLE_MANAGER && !current_user_can('manage_options')) {
            wp_die('Csak adminisztrátor hozhat létre sales manager fiókot.');
        }
        if (!in_array($role, array(self::ROLE_MANAGER, self::ROLE_BROKER), true)) {
            $role = self::ROLE_BROKER;
        }

        $login = isset($_POST['user_login']) ? sanitize_user(wp_unslash($_POST['user_login']), true) : '';
        $email = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
        $display = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : $login;
        $phone = isset($_POST['user_phone']) ? sanitize_text_field(wp_unslash($_POST['user_phone'])) : '';
        $commission_rate = isset($_POST['user_commission_rate']) ? $this->sanitize_commission_rate(wp_unslash($_POST['user_commission_rate'])) : '';

        $error = '';
        if (!$login) {
            $error = '请输入用户名。';
        } elseif (!validate_username($login)) {
            $error = '用户名格式不正确。请使用英文字母、数字、下划线或短横线。';
        } elseif (username_exists($login)) {
            $error = '这个用户名已经存在，请换一个用户名。';
        } elseif (!$email || !is_email($email)) {
            $error = '邮箱格式不正确，请填写真实有效的邮箱。';
        } elseif (preg_match('/@(example\.com|test\.com)$/i', $email)) {
            $error = '请不要使用示例邮箱，请填写真实邮箱。';
        } elseif (email_exists($email)) {
            $error = '这个邮箱已经被其他账号使用，请换一个邮箱。';
        } elseif ($commission_rate !== '' && (float) $commission_rate > 20) {
            $error = '佣金比例看起来过高，请确认后填写 0-20 之间的数字。';
        }

        if ($error) {
            set_transient('harmat_user_error_' . get_current_user_id(), $error, 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->account_return_url(array('user_error' => '1')));
            exit;
        }

        $password = wp_generate_password(16, true, true);
        $user_id = wp_insert_user(array(
            'user_login' => $login,
            'user_email' => $email,
            'display_name' => $display ?: $login,
            'user_pass' => $password,
            'role' => $role,
        ));

        if (is_wp_error($user_id)) {
            set_transient('harmat_user_error_' . get_current_user_id(), $user_id->get_error_message(), 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->account_return_url(array('user_error' => '1')));
            exit;
        }

        update_user_meta($user_id, '_harmat_broker_phone', $phone);
        update_user_meta($user_id, '_harmat_broker_commission_rate', $commission_rate);

        set_transient(
            'harmat_created_user_' . get_current_user_id(),
            array('login' => $login, 'password' => $password, 'role' => $role),
            10 * MINUTE_IN_SECONDS
        );

        wp_safe_redirect($this->account_return_url(array('created_user' => '1')));
        exit;
    }

    private function handle_user_update() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság felhasználó módosításához.');
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $user = $user_id ? get_userdata($user_id) : null;
        if (!$user || !array_intersect(array(self::ROLE_MANAGER, self::ROLE_BROKER), (array) $user->roles)) {
            wp_die('Hibás felhasználó.');
        }

        if (in_array(self::ROLE_MANAGER, (array) $user->roles, true) && !current_user_can('manage_options') && (int) $user_id !== get_current_user_id()) {
            wp_die('Sales manager fiókot csak adminisztrátor módosíthat.');
        }

        $display = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
        $email = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
        $phone = isset($_POST['user_phone']) ? sanitize_text_field(wp_unslash($_POST['user_phone'])) : '';
        $commission_rate = isset($_POST['user_commission_rate']) ? $this->sanitize_commission_rate(wp_unslash($_POST['user_commission_rate'])) : '';

        $error = '';
        if (!$display) {
            $error = '请填写姓名/显示名称。';
        } elseif (!$email || !is_email($email)) {
            $error = '邮箱格式不正确。';
        } else {
            $existing = email_exists($email);
            if ($existing && (int) $existing !== (int) $user_id) {
                $error = '这个邮箱已经被其他账号使用。';
            }
        }

        if (!$error && $commission_rate !== '' && (float) $commission_rate > 20) {
            $error = '佣金比例看起来过高，请填写 0-20 之间的数字。';
        }

        if ($error) {
            set_transient('harmat_user_error_' . get_current_user_id(), $error, 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->account_return_url(array('user_error' => '1')));
            exit;
        }

        $result = wp_update_user(array(
            'ID' => $user_id,
            'user_email' => $email,
            'display_name' => $display,
        ));

        if (is_wp_error($result)) {
            set_transient('harmat_user_error_' . get_current_user_id(), $result->get_error_message(), 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->account_return_url(array('user_error' => '1')));
            exit;
        }

        update_user_meta($user_id, '_harmat_broker_phone', $phone);
        update_user_meta($user_id, '_harmat_broker_commission_rate', $commission_rate);

        wp_safe_redirect($this->account_return_url(array('user_updated' => '1')));
        exit;
    }

    private function handle_password_reset() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság jelszó módosításához.');
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $user = $user_id ? get_userdata($user_id) : null;
        if (!$user || !array_intersect(array(self::ROLE_MANAGER, self::ROLE_BROKER), (array) $user->roles)) {
            wp_die('Hibás felhasználó.');
        }

        if (in_array(self::ROLE_MANAGER, (array) $user->roles, true) && !current_user_can('manage_options')) {
            wp_die('Sales manager jelszavát csak adminisztrátor módosíthatja.');
        }

        $password = wp_generate_password(16, true, true);
        wp_set_password($password, $user_id);
        set_transient(
            'harmat_reset_password_' . get_current_user_id(),
            array('login' => $user->user_login, 'password' => $password),
            10 * MINUTE_IN_SECONDS
        );

        wp_safe_redirect($this->account_return_url(array('password_reset' => '1')));
        exit;
    }

    private function handle_user_delete() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság felhasználó törléséhez.');
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $user = $user_id ? get_userdata($user_id) : null;
        if (!$user || (int) $user_id === get_current_user_id()) {
            wp_die('Hibás felhasználó.');
        }

        $is_private_role = array_intersect(array(self::ROLE_MANAGER, self::ROLE_BROKER), (array) $user->roles);
        if (!$is_private_role || in_array('administrator', (array) $user->roles, true)) {
            wp_die('Csak belső sales/jutalékos fiók törölhető innen.');
        }

        if (in_array(self::ROLE_MANAGER, (array) $user->roles, true) && !current_user_can('manage_options')) {
            wp_die('Sales manager fiókot csak adminisztrátor törölhet.');
        }

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $login = $user->user_login;
        wp_delete_user($user_id);
        set_transient('harmat_deleted_user_' . get_current_user_id(), $login, 5 * MINUTE_IN_SECONDS);

        wp_safe_redirect($this->account_return_url(array('deleted_user' => '1')));
        exit;
    }

    private function handle_lead_save() {
        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $leads = $this->get_leads();
        $current_user_id = get_current_user_id();
        $can_manage = current_user_can(self::CAP_MANAGE);

        if ($lead_id && empty($leads[$lead_id])) {
            wp_die('Hibás ügyfél azonosító.');
        }

        if ($lead_id && !$can_manage && (int) $leads[$lead_id]['broker_id'] !== $current_user_id) {
            wp_die('Nincs jogosultság az ügyfél módosításához.');
        }

        $client_name = isset($_POST['client_name']) ? sanitize_text_field(wp_unslash($_POST['client_name'])) : '';
        $phone = isset($_POST['client_phone']) ? sanitize_text_field(wp_unslash($_POST['client_phone'])) : '';
        $email = isset($_POST['client_email']) ? sanitize_email(wp_unslash($_POST['client_email'])) : '';
        $note = isset($_POST['client_note']) ? sanitize_textarea_field(wp_unslash($_POST['client_note'])) : '';
        $source = isset($_POST['client_source']) ? sanitize_text_field(wp_unslash($_POST['client_source'])) : '';
        $status = isset($_POST['lead_status']) ? sanitize_key($_POST['lead_status']) : 'new';
        $property_id = isset($_POST['property_id']) ? absint($_POST['property_id']) : 0;
        $next_followup = isset($_POST['next_followup']) ? sanitize_text_field(wp_unslash($_POST['next_followup'])) : '';
        $broker_id = $can_manage && isset($_POST['broker_id']) ? absint($_POST['broker_id']) : $current_user_id;

        if (!$client_name || (!$phone && !$email)) {
            set_transient('harmat_lead_error_' . $current_user_id, '请至少填写客户姓名，并填写电话或邮箱其中一项。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->lead_return_url(array('lead_error' => '1')));
            exit;
        }

        if ($email && !is_email($email)) {
            set_transient('harmat_lead_error_' . $current_user_id, '邮箱格式不正确。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->lead_return_url(array('lead_error' => '1')));
            exit;
        }

        if (!isset($this->lead_status_options()[$status])) {
            $status = 'new';
        }

        $previous_status = $lead_id && isset($leads[$lead_id]['status']) ? (string) $leads[$lead_id]['status'] : '';
        if (!$can_manage && $previous_status === 'closed') {
            $status = 'closed';
        } elseif (!$can_manage && $status === 'closed') {
            $status = $previous_status && isset($this->lead_status_options()[$previous_status]) ? $previous_status : 'new';
        }

        if ($property_id && get_post_type($property_id) !== 'property') {
            $property_id = 0;
        }

        if ($next_followup && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_followup)) {
            $next_followup = '';
        }

        if (!$this->is_sales_user($broker_id)) {
            $broker_id = $current_user_id;
        }

        $duplicate = $this->find_duplicate_lead($leads, $client_name, $phone, $lead_id);
        if ($duplicate) {
            $broker = !empty($duplicate['broker_id']) ? get_userdata((int) $duplicate['broker_id']) : null;
            $days_left = $this->lead_protection_days_left($duplicate);
            $message = '这个客户已登记：' . $duplicate['client_name'] . ' / ' . $duplicate['phone'];
            if ($days_left > 0) {
                $message .= '，保护期剩余 ' . $days_left . ' 天';
            }
            if ($broker && current_user_can(self::CAP_MANAGE)) {
                $message .= '，负责经纪人：' . $broker->display_name;
            }
            set_transient('harmat_lead_error_' . $current_user_id, $message, 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->lead_return_url(array('lead_error' => '1', 'duplicate_lead' => '1')));
            exit;
        }

        $now = current_time('mysql');
        if (!$lead_id) {
            $lead_id = $this->next_lead_id($leads);
        }

        $created_at = isset($leads[$lead_id]['created_at']) ? $leads[$lead_id]['created_at'] : $now;
        $leads[$lead_id] = array(
            'id' => $lead_id,
            'broker_id' => $broker_id,
            'client_name' => $client_name,
            'phone' => $phone,
            'email' => $email,
            'property_id' => $property_id,
            'status' => $status,
            'source' => $source,
            'next_followup' => $next_followup,
            'note' => $note,
            'created_at' => $created_at,
            'updated_at' => $now,
            'updated_by' => $current_user_id,
        );

        $this->save_leads($leads);
        wp_safe_redirect($this->lead_return_url(array('lead_saved' => '1')));
        exit;
    }

    private function handle_lead_delete() {
        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $leads = $this->get_leads();

        if (!$lead_id || empty($leads[$lead_id])) {
            wp_die('Hibás ügyfél azonosító.');
        }

        if (!current_user_can(self::CAP_MANAGE) && (int) $leads[$lead_id]['broker_id'] !== get_current_user_id()) {
            wp_die('Nincs jogosultság az ügyfél törléséhez.');
        }

        unset($leads[$lead_id]);
        $this->save_leads($leads);
        wp_safe_redirect($this->lead_return_url(array('lead_deleted' => '1')));
        exit;
    }

    private function handle_deal_save() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság az értékesítési ügylet módosításához.');
        }

        $deals = $this->get_deals();
        $deal_id = isset($_POST['deal_id']) ? absint($_POST['deal_id']) : 0;
        if ($deal_id && empty($deals[$deal_id])) {
            wp_die('Hibás sales ügylet azonosító.');
        }

        $lead_id = isset($_POST['deal_lead_id']) ? absint($_POST['deal_lead_id']) : 0;
        $inquiry_id = isset($_POST['deal_inquiry_id']) ? absint($_POST['deal_inquiry_id']) : 0;
        $property_id = isset($_POST['deal_property_id']) ? absint($_POST['deal_property_id']) : 0;
        $broker_id = isset($_POST['deal_broker_id']) ? absint($_POST['deal_broker_id']) : get_current_user_id();
        $stage = isset($_POST['deal_stage']) ? sanitize_key($_POST['deal_stage']) : 'new';
        $client_name = isset($_POST['deal_client_name']) ? sanitize_text_field(wp_unslash($_POST['deal_client_name'])) : '';
        $phone = isset($_POST['deal_phone']) ? sanitize_text_field(wp_unslash($_POST['deal_phone'])) : '';
        $email = isset($_POST['deal_email']) ? sanitize_email(wp_unslash($_POST['deal_email'])) : '';
        $amount = isset($_POST['deal_amount']) ? preg_replace('/[^\d]/', '', wp_unslash($_POST['deal_amount'])) : '';
        $deposit = isset($_POST['deal_deposit']) ? preg_replace('/[^\d]/', '', wp_unslash($_POST['deal_deposit'])) : '';
        $payment_received = isset($_POST['deal_payment_received']) ? preg_replace('/[^\d]/', '', wp_unslash($_POST['deal_payment_received'])) : '';
        $expected_close = isset($_POST['deal_expected_close']) ? sanitize_text_field(wp_unslash($_POST['deal_expected_close'])) : '';
        $next_followup = isset($_POST['deal_next_followup']) ? sanitize_text_field(wp_unslash($_POST['deal_next_followup'])) : '';
        $next_step = isset($_POST['deal_next_step']) ? sanitize_text_field(wp_unslash($_POST['deal_next_step'])) : '';
        $payment_method = isset($_POST['deal_payment_method']) ? sanitize_key($_POST['deal_payment_method']) : '';
        $payment_due_date = isset($_POST['deal_payment_due_date']) ? sanitize_text_field(wp_unslash($_POST['deal_payment_due_date'])) : '';
        $payment_status = isset($_POST['deal_payment_status']) ? sanitize_key($_POST['deal_payment_status']) : '';
        $payment_schedule = isset($_POST['deal_payment_schedule']) ? sanitize_textarea_field(wp_unslash($_POST['deal_payment_schedule'])) : '';
        $payment_plan_items = $this->sanitize_payment_plan_items(isset($_POST['payment_plan_items']) ? wp_unslash($_POST['payment_plan_items']) : array());
        $document_checklist = $this->sanitize_document_checklist(isset($_POST['document_checklist']) ? wp_unslash($_POST['document_checklist']) : array());
        $contract_status = isset($_POST['deal_contract_status']) ? sanitize_key($_POST['deal_contract_status']) : '';
        $handover_note = isset($_POST['deal_handover_note']) ? sanitize_textarea_field(wp_unslash($_POST['deal_handover_note'])) : '';
        $commission_rate = isset($_POST['deal_commission_rate']) ? sanitize_text_field(wp_unslash($_POST['deal_commission_rate'])) : '';
        $commission_rate = str_replace(',', '.', $commission_rate);
        $commission_amount = isset($_POST['deal_commission_amount']) ? preg_replace('/[^\d]/', '', wp_unslash($_POST['deal_commission_amount'])) : '';
        $commission_due_date = isset($_POST['deal_commission_due_date']) ? sanitize_text_field(wp_unslash($_POST['deal_commission_due_date'])) : '';
        $commission_status = isset($_POST['deal_commission_status']) ? sanitize_key($_POST['deal_commission_status']) : '';
        $commission_note = isset($_POST['deal_commission_note']) ? sanitize_textarea_field(wp_unslash($_POST['deal_commission_note'])) : '';
        $note = isset($_POST['deal_note']) ? sanitize_textarea_field(wp_unslash($_POST['deal_note'])) : '';
        $sync_property = !empty($_POST['sync_property_status']);

        $leads = $this->get_leads();
        if ($lead_id && isset($leads[$lead_id])) {
            $lead = $leads[$lead_id];
            $client_name = $client_name ?: ($lead['client_name'] ?? '');
            $phone = $phone ?: ($lead['phone'] ?? '');
            $email = $email ?: ($lead['email'] ?? '');
            $property_id = $property_id ?: (int) ($lead['property_id'] ?? 0);
            $broker_id = $broker_id ?: (int) ($lead['broker_id'] ?? 0);
        }

        if ($inquiry_id && get_post_type($inquiry_id) === 'harmat_offer_lead') {
            $inquiry = $this->offer_inquiry_data($inquiry_id);
            $client_name = $client_name ?: $inquiry['name'];
            $phone = $phone ?: $inquiry['phone'];
            $email = $email ?: $inquiry['email'];
            if (!$property_id && !empty($inquiry['apartment'])) {
                $property_id = $this->property_id_by_title($inquiry['apartment']);
            }
        } else {
            $inquiry_id = 0;
        }

        if (!$client_name) {
            set_transient('harmat_deal_error_' . get_current_user_id(), '请填写客户姓名，或选择一个客户/网站询价记录。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->deal_return_url(array('deal_error' => '1')));
            exit;
        }

        if ($email && !is_email($email)) {
            set_transient('harmat_deal_error_' . get_current_user_id(), '邮箱格式不正确。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->deal_return_url(array('deal_error' => '1')));
            exit;
        }

        if (!isset($this->deal_stage_options()[$stage])) {
            $stage = 'new';
        }
        if ($payment_method && !isset($this->payment_method_options()[$payment_method])) {
            $payment_method = '';
        }
        if ($payment_status && !isset($this->payment_status_options()[$payment_status])) {
            $payment_status = '';
        }
        if ($contract_status && !isset($this->contract_status_options()[$contract_status])) {
            $contract_status = '';
        }
        if ($commission_rate !== '' && !preg_match('/^\d+(\.\d{1,4})?$/', $commission_rate)) {
            $commission_rate = '';
        }
        if ($commission_status && !isset($this->commission_status_options()[$commission_status])) {
            $commission_status = '';
        }
        if ($property_id && get_post_type($property_id) !== 'property') {
            $property_id = 0;
        }
        if (!$this->is_sales_user($broker_id)) {
            $broker_id = get_current_user_id();
        }
        if ($commission_rate === '' && $broker_id) {
            $commission_rate = $this->broker_commission_rate($broker_id);
        }
        if ($expected_close && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected_close)) {
            $expected_close = '';
        }
        if ($next_followup && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_followup)) {
            $next_followup = '';
        }
        if ($payment_due_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_due_date)) {
            $payment_due_date = '';
        }
        if ($commission_due_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $commission_due_date)) {
            $commission_due_date = '';
        }
        if (!$payment_status) {
            $payment_status = $this->infer_payment_status($amount, $payment_received, $payment_due_date);
        }

        $now = current_time('mysql');
        if (!$deal_id) {
            $deal_id = $this->next_deal_id($deals);
        }
        $previous_deal = isset($deals[$deal_id]) ? $deals[$deal_id] : array();
        $created_at = isset($deals[$deal_id]['created_at']) ? $deals[$deal_id]['created_at'] : $now;
        $closed_at = !empty($previous_deal['closed_at']) ? (string) $previous_deal['closed_at'] : '';
        if ($stage === 'closed' && !$closed_at) {
            $closed_at = $expected_close ?: current_time('Y-m-d');
        }
        if ($commission_rate !== '' && $commission_amount === '' && (int) $amount > 0) {
            $commission_amount = (string) round(((int) $amount) * ((float) $commission_rate) / 100);
        }
        if ($stage === 'closed' && !$commission_due_date) {
            $commission_due_date = $this->date_plus_one_month($closed_at ?: current_time('Y-m-d'));
        }
        if ($stage === 'closed' && !$commission_status) {
            $commission_status = 'scheduled';
        }

        $deals[$deal_id] = array(
            'id' => $deal_id,
            'lead_id' => $lead_id,
            'inquiry_id' => $inquiry_id,
            'property_id' => $property_id,
            'broker_id' => $broker_id,
            'stage' => $stage,
            'client_name' => $client_name,
            'phone' => $phone,
            'email' => $email,
            'amount' => $amount,
            'deposit' => $deposit,
            'payment_received' => $payment_received,
            'expected_close' => $expected_close,
            'next_followup' => $next_followup,
            'next_step' => $next_step,
            'payment_method' => $payment_method,
            'payment_due_date' => $payment_due_date,
            'payment_status' => $payment_status,
            'payment_schedule' => $payment_schedule,
            'payment_plan_items' => $payment_plan_items,
            'document_checklist' => $document_checklist,
            'contract_status' => $contract_status,
            'handover_note' => $handover_note,
            'closed_at' => $closed_at,
            'commission_rate' => $commission_rate,
            'commission_amount' => $commission_amount,
            'commission_due_date' => $commission_due_date,
            'commission_status' => $commission_status,
            'commission_note' => $commission_note,
            'note' => $note,
            'customer_user_id' => isset($previous_deal['customer_user_id']) ? (int) $previous_deal['customer_user_id'] : 0,
            'customer_account_created_at' => isset($previous_deal['customer_account_created_at']) ? (string) $previous_deal['customer_account_created_at'] : '',
            'customer_account_sent_at' => isset($previous_deal['customer_account_sent_at']) ? (string) $previous_deal['customer_account_sent_at'] : '',
            'customer_materials' => isset($previous_deal['customer_materials']) && is_array($previous_deal['customer_materials']) ? $previous_deal['customer_materials'] : array(),
            'created_at' => $created_at,
            'updated_at' => $now,
            'updated_by' => get_current_user_id(),
        );
        $this->save_deals($deals);

        if ($lead_id && isset($leads[$lead_id])) {
            $leads[$lead_id]['status'] = $this->deal_stage_to_lead_status($stage);
            if ($property_id) {
                $leads[$lead_id]['property_id'] = $property_id;
            }
            if ($next_followup) {
                $leads[$lead_id]['next_followup'] = $next_followup;
            }
            if ($broker_id) {
                $leads[$lead_id]['broker_id'] = $broker_id;
            }
            $leads[$lead_id]['updated_at'] = $now;
            $leads[$lead_id]['updated_by'] = get_current_user_id();
            $this->save_leads($leads);
        }

        if ($sync_property && $property_id) {
            if (in_array($stage, array('reserved', 'contract'), true)) {
                $this->set_property_sales_state($property_id, 'reserved', get_post_meta($property_id, 'property_price', true));
            } elseif ($stage === 'closed') {
                $this->set_property_sales_state($property_id, 'sold', $amount ?: get_post_meta($property_id, 'property_price', true));
            }
            update_post_meta($property_id, '_harmat_sales_updated_by', get_current_user_id());
            update_post_meta($property_id, '_harmat_sales_updated_at', $now);
        }

        wp_safe_redirect($this->deal_return_url(array('deal_saved' => '1')));
        exit;
    }

    private function handle_customer_account_generate() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság ügyfélfiók létrehozásához.');
        }

        $deal_id = isset($_POST['deal_id']) ? absint($_POST['deal_id']) : 0;
        $deals = $this->get_deals();
        if (!$deal_id || empty($deals[$deal_id])) {
            wp_die('Hibás ügyfél azonosító.');
        }

        $deal = $deals[$deal_id];
        if (($deal['stage'] ?? '') !== 'closed') {
            set_transient('harmat_customer_account_error_' . get_current_user_id(), '只有已成交客户可以生成客户中心账号。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_error' => '1')));
            exit;
        }

        if (!empty($deal['customer_user_id']) && get_userdata((int) $deal['customer_user_id'])) {
            set_transient('harmat_customer_account_error_' . get_current_user_id(), '这个客户已经生成过账号，不能重复生成。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_error' => '1')));
            exit;
        }

        $email = sanitize_email($deal['email'] ?? '');
        if (!$email || !is_email($email)) {
            set_transient('harmat_customer_account_error_' . get_current_user_id(), '客户邮箱为空或格式不正确，无法发送客户中心账号。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_error' => '1')));
            exit;
        }

        if (email_exists($email)) {
            set_transient('harmat_customer_account_error_' . get_current_user_id(), '这个邮箱已经存在 WordPress 账号。请先换客户邮箱或手动确认账号归属。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_error' => '1')));
            exit;
        }

        $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
        $base_login = sanitize_user('customer_' . ($property_title ?: $deal_id), true);
        $base_login = $base_login ?: 'customer_' . $deal_id;
        $login = $base_login;
        $suffix = 1;
        while (username_exists($login)) {
            $suffix++;
            $login = $base_login . '_' . $suffix;
        }

        $password = wp_generate_password(14, true, false);
        $user_id = wp_insert_user(array(
            'user_login' => $login,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $deal['client_name'] ?: $property_title ?: $login,
            'role' => self::ROLE_CUSTOMER,
        ));

        if (is_wp_error($user_id)) {
            set_transient('harmat_customer_account_error_' . get_current_user_id(), '客户账号创建失败：' . $user_id->get_error_message(), 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_error' => '1')));
            exit;
        }

        update_user_meta($user_id, '_harmat_customer_deal_id', $deal_id);
        update_user_meta($user_id, '_harmat_customer_property_id', (int) ($deal['property_id'] ?? 0));

        $portal_url = home_url('/client/');
        $subject = 'Harmat Lakópark ügyfélközpont hozzáférés';
        $message = $this->customer_account_email_body($deal, $login, $password, $portal_url);
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $sent = wp_mail($email, $subject, $message, $headers);

        if (!$sent) {
            if (!function_exists('wp_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }
            wp_delete_user($user_id);
            set_transient('harmat_customer_account_error_' . get_current_user_id(), '客户账号已回滚：邮件发送失败，请检查网站邮件服务。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_error' => '1')));
            exit;
        }

        $now = current_time('mysql');
        $deals[$deal_id]['customer_user_id'] = (int) $user_id;
        $deals[$deal_id]['customer_account_created_at'] = $now;
        $deals[$deal_id]['customer_account_sent_at'] = $now;
        $deals[$deal_id]['updated_at'] = $now;
        $deals[$deal_id]['updated_by'] = get_current_user_id();
        $this->save_deals($deals);

        set_transient('harmat_customer_account_success_' . get_current_user_id(), array(
            'email' => $email,
            'login' => $login,
            'password' => $password,
            'portal' => $portal_url,
        ), 5 * MINUTE_IN_SECONDS);

        wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_created' => '1')));
        exit;
    }

    private function handle_customer_account_password_reset() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság ügyfélfiók jelszó módosításához.');
        }

        $deal_id = isset($_POST['deal_id']) ? absint($_POST['deal_id']) : 0;
        $deals = $this->get_deals();
        if (!$deal_id || empty($deals[$deal_id])) {
            wp_die('Hibás ügyfél azonosító.');
        }

        $deal = $deals[$deal_id];
        $user_id = (int) ($deal['customer_user_id'] ?? 0);
        $customer_user = $user_id ? get_userdata($user_id) : null;
        if (!$customer_user) {
            set_transient('harmat_customer_account_error_' . get_current_user_id(), '这个客户还没有客户中心账号，不能重置密码。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_error' => '1')));
            exit;
        }

        $email = sanitize_email($deal['email'] ?: $customer_user->user_email);
        if (!$email || !is_email($email)) {
            set_transient('harmat_customer_account_error_' . get_current_user_id(), '客户邮箱为空或格式不正确，无法发送新密码。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_error' => '1')));
            exit;
        }

        $password = wp_generate_password(14, true, false);
        $portal_url = home_url('/client/');
        $subject = 'Harmat Lakópark ügyfélközpont új jelszó';
        $message = $this->customer_account_email_body($deal, $customer_user->user_login, $password, $portal_url);
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $sent = wp_mail($email, $subject, $message, $headers);

        if (!$sent) {
            set_transient('harmat_customer_account_error_' . get_current_user_id(), '新密码已生成，但邮件发送失败。请检查网站邮件服务后再次重置。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_error' => '1')));
            exit;
        }

        wp_set_password($password, $user_id);

        $now = current_time('mysql');
        $deals[$deal_id]['customer_account_sent_at'] = $now;
        $deals[$deal_id]['updated_at'] = $now;
        $deals[$deal_id]['updated_by'] = get_current_user_id();
        $this->save_deals($deals);

        set_transient('harmat_customer_account_success_' . get_current_user_id(), array(
            'email' => $email,
            'login' => $customer_user->user_login,
            'password' => $password,
            'portal' => $portal_url,
            'reset' => 1,
        ), 5 * MINUTE_IN_SECONDS);

        wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'customer_account_reset' => '1')));
        exit;
    }

    private function handle_customer_material_upload() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság ügyfélanyag feltöltéséhez.');
        }

        $deal_id = isset($_POST['deal_id']) ? absint($_POST['deal_id']) : 0;
        $deals = $this->get_deals();
        if (!$deal_id || empty($deals[$deal_id])) {
            wp_die('Hibás ügyfél azonosító.');
        }

        if (empty($_FILES['customer_material']) || !is_array($_FILES['customer_material']) || empty($_FILES['customer_material']['name'])) {
            set_transient('harmat_customer_material_error_' . get_current_user_id(), '请选择要上传的附件。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'material_error' => '1')));
            exit;
        }

        $file = $_FILES['customer_material'];
        if (!empty($file['size']) && (int) $file['size'] > 25 * MB_IN_BYTES) {
            set_transient('harmat_customer_material_error_' . get_current_user_id(), '附件不能超过 25MB。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'material_error' => '1')));
            exit;
        }

        $allowed_mimes = $this->customer_material_mimes();
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        if (empty($check['type'])) {
            set_transient('harmat_customer_material_error_' . get_current_user_id(), '文件类型不支持。请上传 PDF、图片、Word、Excel、TXT 或 ZIP。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'material_error' => '1')));
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded = wp_handle_upload($file, array(
            'test_form' => false,
            'mimes' => $allowed_mimes,
        ));
        if (!empty($uploaded['error'])) {
            set_transient('harmat_customer_material_error_' . get_current_user_id(), '上传失败：' . $uploaded['error'], 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'material_error' => '1')));
            exit;
        }

        $title = isset($_POST['material_title']) ? sanitize_text_field(wp_unslash($_POST['material_title'])) : '';
        $note = isset($_POST['material_note']) ? sanitize_textarea_field(wp_unslash($_POST['material_note'])) : '';
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $uploaded['type'],
            'post_title' => $title ?: sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_content' => $note,
            'post_status' => 'private',
        ), $uploaded['file']);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            @unlink($uploaded['file']);
            set_transient('harmat_customer_material_error_' . get_current_user_id(), '附件保存失败，请重试。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'material_error' => '1')));
            exit;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
        if ($metadata) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        update_post_meta($attachment_id, '_harmat_customer_deal_id', $deal_id);
        update_post_meta($attachment_id, '_harmat_customer_material_note', $note);
        update_post_meta($attachment_id, '_harmat_customer_material_uploaded_by', get_current_user_id());
        $visibility = isset($_POST['material_visibility']) ? sanitize_key(wp_unslash($_POST['material_visibility'])) : 'customer';
        if (!in_array($visibility, array('customer', 'internal'), true)) {
            $visibility = 'customer';
        }
        update_post_meta($attachment_id, '_harmat_customer_material_visibility', $visibility);

        $now = current_time('mysql');
        $materials = isset($deals[$deal_id]['customer_materials']) && is_array($deals[$deal_id]['customer_materials']) ? $deals[$deal_id]['customer_materials'] : array();
        $materials[] = array(
            'attachment_id' => (int) $attachment_id,
            'title' => $title ?: get_the_title($attachment_id),
            'note' => $note,
            'visibility' => $visibility,
            'uploaded_at' => $now,
            'uploaded_by' => get_current_user_id(),
        );
        $deals[$deal_id]['customer_materials'] = $materials;
        $deals[$deal_id]['updated_at'] = $now;
        $deals[$deal_id]['updated_by'] = get_current_user_id();
        $this->save_deals($deals);

        wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'material_uploaded' => '1')));
        exit;
    }

    private function handle_customer_material_delete() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság ügyfélanyag törléséhez.');
        }

        $deal_id = isset($_POST['deal_id']) ? absint($_POST['deal_id']) : 0;
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        $deals = $this->get_deals();
        if (!$deal_id || empty($deals[$deal_id]) || !$attachment_id) {
            wp_die('Hibás ügyfélanyag azonosító.');
        }

        $materials = isset($deals[$deal_id]['customer_materials']) && is_array($deals[$deal_id]['customer_materials']) ? $deals[$deal_id]['customer_materials'] : array();
        $kept = array();
        $removed = false;
        foreach ($materials as $material) {
            if ((int) ($material['attachment_id'] ?? 0) === $attachment_id) {
                $removed = true;
                continue;
            }
            $kept[] = $material;
        }

        if (!$removed) {
            set_transient('harmat_customer_material_error_' . get_current_user_id(), '没有找到要删除的附件。', 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'material_error' => '1')));
            exit;
        }

        $owner_deal = (int) get_post_meta($attachment_id, '_harmat_customer_deal_id', true);
        if ($owner_deal === $deal_id && get_post_type($attachment_id) === 'attachment') {
            wp_delete_attachment($attachment_id, true);
        }

        $deals[$deal_id]['customer_materials'] = $kept;
        $deals[$deal_id]['updated_at'] = current_time('mysql');
        $deals[$deal_id]['updated_by'] = get_current_user_id();
        $this->save_deals($deals);

        wp_safe_redirect($this->sales_portal_url(array('view' => 'customers', 'customer_id' => $deal_id, 'material_deleted' => '1')));
        exit;
    }

    private function handle_deal_delete() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság az értékesítési ügylet törléséhez.');
        }

        $deal_id = isset($_POST['deal_id']) ? absint($_POST['deal_id']) : 0;
        $deals = $this->get_deals();
        if (!$deal_id || empty($deals[$deal_id])) {
            wp_die('Hibás sales ügylet azonosító.');
        }

        unset($deals[$deal_id]);
        $this->save_deals($deals);
        wp_safe_redirect($this->deal_return_url(array('deal_deleted' => '1')));
        exit;
    }

    public function render_page() {
        if (!current_user_can(self::CAP_VIEW)) {
            wp_die('Nincs jogosultság.');
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'properties';
        echo '<div class="wrap harmat-sales-wrap">';
        echo '<h1>Harmat销售管理</h1>';
        echo '<p class="description">用于内部查看楼盘销售状态、价格和销售账号。经纪人账号只能查看，不能修改。</p>';
        $this->render_notices();
        $this->render_dashboard_links();
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a class="nav-tab ' . ($tab === 'properties' ? 'nav-tab-active' : '') . '" href="' . esc_url($this->page_url()) . '">房源销售表</a>';
        echo '<a class="nav-tab ' . ($tab === 'leads' ? 'nav-tab-active' : '') . '" href="' . esc_url($this->page_url(array('tab' => 'leads'))) . '">客户跟进</a>';
        if (current_user_can(self::CAP_MANAGE)) {
            echo '<a class="nav-tab ' . ($tab === 'inquiries' ? 'nav-tab-active' : '') . '" href="' . esc_url($this->page_url(array('tab' => 'inquiries'))) . '">网站询价</a>';
            echo '<a class="nav-tab ' . ($tab === 'accounts' ? 'nav-tab-active' : '') . '" href="' . esc_url($this->page_url(array('tab' => 'accounts'))) . '">账号生成</a>';
        }
        echo '</h2>';

        if ($tab === 'leads') {
            $this->render_leads();
        } elseif ($tab === 'inquiries' && current_user_can(self::CAP_MANAGE)) {
            $this->render_inquiries();
        } elseif ($tab === 'accounts' && current_user_can(self::CAP_MANAGE)) {
            $this->render_accounts();
        } else {
            $this->render_properties();
        }

        echo '</div>';
    }

    private function render_dashboard_links() {
        $links = array(
            array(
                'label' => '销售管理直达',
                'url' => home_url('/sales/'),
                'note' => '推荐保存：未登录时会先进入登录页',
            ),
            array(
                'label' => '网站登录页',
                'url' => home_url('/belepes/'),
                'note' => '公开登录入口',
            ),
            array(
                'label' => '后台当前页',
                'url' => $this->page_url(),
                'note' => '登录后直接进入 Harmat销售管理',
            ),
            array(
                'label' => '经纪人入口',
                'url' => home_url('/agent/'),
                'note' => '经纪人客户登记与跟进',
            ),
            array(
                'label' => '客户入口',
                'url' => home_url('/client/'),
                'note' => '客户登录入口',
            ),
        );

        echo '<section class="harmat-admin-links">';
        echo '<div class="harmat-admin-links-head"><h2>常用登录入口</h2><p>这些链接可以复制保存，方便确认不同账号应该从哪里进入。</p></div>';
        echo '<div class="harmat-admin-link-grid">';
        foreach ($links as $link) {
            echo '<a class="harmat-admin-link-card" href="' . esc_url($link['url']) . '" target="_blank" rel="noopener">';
            echo '<strong>' . esc_html($link['label']) . '</strong>';
            echo '<code>' . esc_html($link['url']) . '</code>';
            echo '<span>' . esc_html($link['note']) . '</span>';
            echo '</a>';
        }
        echo '</div></section>';
    }

    private function render_notices() {
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>房源已更新。</p></div>';
        }

        if (isset($_GET['user_error'])) {
            $error = get_transient('harmat_user_error_' . get_current_user_id());
            delete_transient('harmat_user_error_' . get_current_user_id());
            echo '<div class="notice notice-error"><p>账号创建失败：' . esc_html($error ?: '请检查用户名、邮箱和角色。') . '</p></div>';
        }

        if (isset($_GET['created_user'])) {
            $created = get_transient('harmat_created_user_' . get_current_user_id());
            if ($created) {
                delete_transient('harmat_created_user_' . get_current_user_id());
                echo '<div class="notice notice-success harmat-password-notice"><p><strong>账号已创建。请立即记录密码，密码只显示这一次：</strong></p>';
                echo '<p>用户名：<code>' . esc_html($created['login']) . '</code></p>';
                echo '<p>密码：<code>' . esc_html($created['password']) . '</code></p>';
                echo '<p>角色：<code>' . esc_html($this->role_label($created['role'])) . '</code></p></div>';
            }
        }

        if (isset($_GET['password_reset'])) {
            $reset = get_transient('harmat_reset_password_' . get_current_user_id());
            if ($reset) {
                delete_transient('harmat_reset_password_' . get_current_user_id());
                echo '<div class="notice notice-success harmat-password-notice"><p><strong>密码已重置。请立即记录，新密码只显示这一次：</strong></p>';
                echo '<p>用户名：<code>' . esc_html($reset['login']) . '</code></p>';
                echo '<p>新密码：<code>' . esc_html($reset['password']) . '</code></p></div>';
            }
        }

        if (isset($_GET['deleted_user'])) {
            $deleted = get_transient('harmat_deleted_user_' . get_current_user_id());
            delete_transient('harmat_deleted_user_' . get_current_user_id());
            echo '<div class="notice notice-success is-dismissible"><p>账号已删除：<code>' . esc_html($deleted ?: '') . '</code></p></div>';
        }

        if (isset($_GET['lead_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>客户跟进已保存。</p></div>';
        }

        if (isset($_GET['lead_deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>客户跟进已删除。</p></div>';
        }

        if (isset($_GET['lead_error'])) {
            $error = get_transient('harmat_lead_error_' . get_current_user_id());
            delete_transient('harmat_lead_error_' . get_current_user_id());
            echo '<div class="notice notice-error"><p>' . esc_html($error ?: '客户跟进保存失败，请检查资料。') . '</p></div>';
        }
    }

    private function render_properties() {
        $status_filter = isset($_GET['sales_status']) ? sanitize_key($_GET['sales_status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $properties = $this->get_properties($search);
        $counts = array('current' => 0, 'reserved' => 0, 'sold' => 0);

        foreach ($properties as $post) {
            $counts[$this->sales_status($post->ID)]++;
        }

        echo '<div class="harmat-summary">';
        echo '<span>总套数 <b>' . count($properties) . '</b></span>';
        echo '<span>在售 <b>' . (int) $counts['current'] . '</b></span>';
        echo '<span>已预订 <b>' . (int) $counts['reserved'] . '</b></span>';
        echo '<span>已出售 <b>' . (int) $counts['sold'] . '</b></span>';
        echo '</div>';

        echo '<form method="get" class="harmat-filter">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="搜索房号，例如 A1-F-L1">';
        echo '<select name="sales_status">';
        echo '<option value="">全部状态</option>';
        foreach ($this->status_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($status_filter, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<button class="button">筛选</button>';
        echo '</form>';

        if (current_user_can(self::CAP_MANAGE)) {
            if (!empty($_GET['bulk_updated'])) {
                echo '<div class="notice notice-success inline"><p>批量操作完成：已更新 ' . (int) $_GET['bulk_updated'] . ' 套房源。备份编号：<code>' . esc_html(sanitize_text_field(wp_unslash($_GET['bulk_backup'] ?? ''))) . '</code></p></div>';
            } elseif (!empty($_GET['bulk_empty'])) {
                echo '<div class="notice notice-warning inline"><p>请先勾选需要批量修改的房源。</p></div>';
            }

            echo '<form id="harmat-bulk-form" method="post" class="harmat-bulk-tools">';
            wp_nonce_field('harmat_sales_action_bulk_update_properties');
            echo '<input type="hidden" name="harmat_sales_action" value="bulk_update_properties">';
            echo '<label class="harmat-bulk-check"><input type="checkbox" id="harmat-select-all"> 全选当前列表</label>';
            echo '<label>批量状态<select name="bulk_status"><option value="">不修改状态</option>';
            foreach ($this->status_options() as $value => $label) {
                echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
            }
            echo '</select></label>';
            echo '<label>前端价格<select name="price_visibility"><option value="">不修改显示</option><option value="show">显示价格</option><option value="hide">隐藏价格</option></select></label>';
            echo '<button class="button button-primary">应用到勾选房源</button>';
            echo '<p class="description">第一版只支持批量状态和价格显示/隐藏。隐藏价格不会删除后台真实价格，操作前会自动保存备份。</p>';
            echo '</form>';
        }

        echo '<div class="harmat-property-grid">';

        foreach ($properties as $post) {
            $sales_status = $this->sales_status($post->ID);
            if ($status_filter && $status_filter !== $sales_status) {
                continue;
            }
            $this->render_property_row($post, $sales_status);
        }

        echo '</div>';
    }

    private function render_property_row($post, $sales_status) {
        $post_id = $post->ID;
        $price = get_post_meta($post_id, 'property_price', true);
        $building = get_post_meta($post_id, 'property_address_street', true);
        $floor = get_post_meta($post_id, 'property_address_street_number', true);
        $unit = get_post_meta($post_id, 'property_address_sub_number', true);
        $rooms = get_post_meta($post_id, 'property_rooms', true);
        $bedrooms = get_post_meta($post_id, 'property_bedrooms', true);
        $sales_area = $this->get_sales_area($post_id);
        $sqm_price = $sales_area > 0 ? ((float) $price / $sales_area) : 0;
        $note = get_post_meta($post_id, '_harmat_sales_note', true);
        $updated = get_post_meta($post_id, '_harmat_sales_updated_at', true);
        $updated_by = get_post_meta($post_id, '_harmat_sales_updated_by', true);
        $updater = $updated_by ? get_userdata((int) $updated_by) : null;

        echo '<article class="harmat-property-card harmat-status-' . esc_attr($sales_status) . '">';
        if (current_user_can(self::CAP_MANAGE)) {
            echo '<label class="harmat-card-select"><input form="harmat-bulk-form" type="checkbox" name="bulk_property_ids[]" value="' . esc_attr($post_id) . '"> 选择</label>';
        }
        echo '<div class="harmat-card-top">';
        echo '<div><a class="harmat-unit-title" href="' . esc_url(get_permalink($post_id)) . '" target="_blank" rel="noopener">' . esc_html(get_the_title($post_id)) . '</a>';
        echo '<p>' . esc_html($building . ' épület · ' . trim($floor . ' / ' . $unit, ' /')) . '</p></div>';
        echo '<span class="harmat-status-badge">' . esc_html($this->status_options()[$sales_status]) . '</span>';
        echo '</div>';
        echo '<div class="harmat-card-metrics">';
        echo '<span><small>户型</small><b>' . esc_html($rooms . ' szoba' . ($bedrooms ? ' / ' . $bedrooms . ' háló' : '')) . '</b></span>';
        echo '<span><small>销售面积</small><b>' . esc_html($this->format_area($sales_area)) . ' m²</b></span>';
        echo '<span><small>每平米价格</small><b class="harmat-card-sqm-price">' . esc_html($this->format_money($sqm_price)) . ' HUF/m²</b></span>';
        echo '<span><small>总价格</small><b class="harmat-card-total-price">' . esc_html($this->format_money($price)) . ' HUF</b></span>';
        echo '</div>';

        if (current_user_can(self::CAP_MANAGE)) {
            $form_id = 'harmat-sales-form-' . $post_id;
            echo '<div class="harmat-card-fields">';
            echo '<label>总价格 HUF<input form="' . esc_attr($form_id) . '" class="harmat-price-input" name="property_price" value="' . esc_attr($price) . '" inputmode="numeric" data-sales-area="' . esc_attr($this->format_area($sales_area)) . '"></label>';
            echo '<label>状态<select form="' . esc_attr($form_id) . '" name="sales_status">';
            foreach ($this->status_options() as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"' . selected($sales_status, $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></label>';
            echo '<label class="harmat-note-field">备注<textarea form="' . esc_attr($form_id) . '" name="sales_note" rows="2">' . esc_textarea($note) . '</textarea></label>';
            echo '</div>';
            echo '<div class="harmat-card-footer">';
            echo '<span>最后更新：' . esc_html($updated ?: '-') . ($updater ? ' · ' . esc_html($updater->display_name) : '') . '</span>';
            echo '<form id="' . esc_attr($form_id) . '" method="post">';
            wp_nonce_field('harmat_sales_action_update_property');
            echo '<input type="hidden" name="harmat_sales_action" value="update_property">';
            echo '<input type="hidden" name="post_id" value="' . esc_attr($post_id) . '">';
            echo '<button class="button button-primary">保存</button>';
            echo '</form></div>';
        } else {
            echo '<div class="harmat-card-fields harmat-readonly">';
            echo '<span><small>销售面积</small><b>' . esc_html($this->format_area($sales_area)) . ' m²</b></span>';
            echo '<span><small>每平米价格</small><b>' . esc_html($this->format_money($sqm_price)) . ' HUF/m²</b></span>';
            echo '<span><small>总价格</small><b>' . esc_html($this->format_money($price)) . ' HUF</b></span>';
            echo '<span><small>备注</small><b>' . esc_html($note ?: '-') . '</b></span>';
            echo '</div>';
            echo '<div class="harmat-card-footer"><span>最后更新：' . esc_html($updated ?: '-') . ($updater ? ' · ' . esc_html($updater->display_name) : '') . '</span></div>';
        }

        echo '</article>';
    }

    private function render_accounts() {
        echo '<div class="harmat-card">';
        echo '<h2>生成销售/经纪人账号</h2>';
        echo '<p>经纪人账号只能查看销售表；销售管理账号可以修改价格、状态和创建经纪人账号。</p>';
        echo '<form method="post" class="harmat-account-form">';
        wp_nonce_field('harmat_sales_action_create_user');
        echo '<input type="hidden" name="harmat_sales_action" value="create_user">';
        echo '<label>用户名 <input required name="user_login" placeholder="agent01" pattern="[A-Za-z0-9_-]+" title="只能使用英文字母、数字、下划线或短横线"></label>';
        echo '<label>显示名称 <input name="display_name" placeholder="销售姓名"></label>';
        echo '<label>邮箱 <input required type="email" name="user_email" placeholder="请输入真实邮箱，例如 name@company.hu"></label>';
        echo '<label>角色 <select name="new_role">';
        echo '<option value="' . esc_attr(self::ROLE_BROKER) . '">经纪人查看</option>';
        if (current_user_can('manage_options')) {
            echo '<option value="' . esc_attr(self::ROLE_MANAGER) . '">销售管理</option>';
        }
        echo '</select></label>';
        echo '<button class="button button-primary button-hero">生成账号和密码</button>';
        echo '</form>';
        echo '</div>';

        echo '<h2>现有内部账号</h2>';
        $users = get_users(array('role__in' => array(self::ROLE_MANAGER, self::ROLE_BROKER), 'orderby' => 'registered', 'order' => 'DESC'));
        echo '<table class="widefat striped"><thead><tr><th>用户名</th><th>姓名</th><th>邮箱</th><th>角色</th><th>创建时间</th><th>操作</th></tr></thead><tbody>';
        foreach ($users as $user) {
            $role = in_array(self::ROLE_MANAGER, $user->roles, true) ? self::ROLE_MANAGER : self::ROLE_BROKER;
            echo '<tr><td>' . esc_html($user->user_login) . '</td><td>' . esc_html($user->display_name) . '</td><td>' . esc_html($user->user_email) . '</td><td>' . esc_html($this->role_label($role)) . '</td><td>' . esc_html($user->user_registered) . '</td><td>';
            if ($role !== self::ROLE_MANAGER || current_user_can('manage_options')) {
                echo '<form method="post" class="harmat-inline-form">';
                wp_nonce_field('harmat_sales_action_reset_password');
                echo '<input type="hidden" name="harmat_sales_action" value="reset_password">';
                echo '<input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '">';
                echo '<button class="button" onclick="return confirm(\'确定要重置这个账号的密码吗？旧密码会立即失效。\')">重置密码</button>';
                echo '</form>';
                if ((int) $user->ID !== get_current_user_id()) {
                    echo '<form method="post" class="harmat-inline-form">';
                    wp_nonce_field('harmat_sales_action_delete_user');
                    echo '<input type="hidden" name="harmat_sales_action" value="delete_user">';
                    echo '<input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '">';
                    echo '<button class="button button-link-delete" onclick="return confirm(\'确定删除这个账号吗？删除后该账号不能再登录。\')">删除</button>';
                    echo '</form>';
                }
            } else {
                echo '-';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function agent_portal_labels($lang) {
        $labels = array(
            'hu' => array(
                'title' => 'Harmat Lakópark | Közvetítői portál',
                'heading' => 'Közvetítői munkafelület',
                'intro' => 'Ügyfélregisztráció, védelmi idő, következő teendők és elérhető lakások egy helyen.',
                'logout' => 'Kilépés',
                'public_properties' => 'Nyilvános lakáskereső',
                'notice_saved' => 'Az ügyfélkövetés mentve.',
                'notice_deleted' => 'Az ügyfélkövetés törölve.',
                'notice_error' => 'Az ügyfélkövetés mentése sikertelen, kérjük ellenőrizze az adatokat.',
                'nav' => array(
                    'overview' => 'Áttekintés',
                    'clients' => 'Ügyfeleim',
                    'tasks' => 'Teendők',
                    'commissions' => 'Jutalék',
                    'properties' => 'Elérhető lakások',
                    'rules' => 'Szabályok',
                ),
                'kpis' => array(
                    'current' => 'Elérhető lakások',
                    'tasks' => 'Teendők',
                    'protected' => 'Védett ügyfelek',
                    'clients' => 'Ügyfeleim',
                    'commission' => 'Lezárt jutalék',
                ),
            ),
            'en' => array(
                'title' => 'Harmat Lakópark | Agent portal',
                'heading' => 'Agent workspace',
                'intro' => 'Client registration, protection period, follow-ups and available apartments in one place.',
                'logout' => 'Log out',
                'public_properties' => 'Public apartment search',
                'notice_saved' => 'Client follow-up has been saved.',
                'notice_deleted' => 'Client follow-up has been deleted.',
                'notice_error' => 'Client follow-up could not be saved. Please check the details.',
                'nav' => array(
                    'overview' => 'Overview',
                    'clients' => 'My clients',
                    'tasks' => 'Follow-ups',
                    'commissions' => 'Commission',
                    'properties' => 'Available apartments',
                    'rules' => 'Rules',
                ),
                'kpis' => array(
                    'current' => 'Available apartments',
                    'tasks' => 'Follow-ups',
                    'protected' => 'Protected clients',
                    'clients' => 'My clients',
                    'commission' => 'Closed commission',
                ),
            ),
            'zh' => array(
                'title' => 'Harmat Lakópark | 经纪人工作台',
                'heading' => '经纪人工作台',
                'intro' => '客户登记、保护期、待跟进和可售房源集中管理。',
                'logout' => '退出',
                'public_properties' => '公开房源',
                'notice_saved' => '客户跟进已保存。',
                'notice_deleted' => '客户跟进已删除。',
                'notice_error' => '客户跟进保存失败，请检查资料。',
                'nav' => array(
                    'overview' => '概览',
                    'clients' => '我的客户',
                    'tasks' => '待跟进',
                    'commissions' => '佣金',
                    'properties' => '在售房源',
                    'rules' => '规则说明',
                ),
                'kpis' => array(
                    'current' => '在售房源',
                    'tasks' => '待跟进',
                    'protected' => '保护中',
                    'clients' => '我的客户',
                    'commission' => '成交佣金',
                ),
            ),
        );

        return $labels[$lang] ?? $labels['hu'];
    }

    private function agent_portal_language_script($lang) {
        if ($lang === 'zh') {
            return '';
        }

        $dictionary = array(
            'hu' => array(
                '客户登记' => 'Ügyfél regisztráció',
                '编辑客户' => 'Ügyfél szerkesztése',
                '姓名 + 电话重复时，系统会提示客户已登记。' => 'Név és telefonszám egyezésekor a rendszer jelzi, ha az ügyfél már szerepel.',
                '客户姓名' => 'Ügyfél neve',
                '电话' => 'Telefon',
                '邮箱' => 'E-mail',
                '来源' => 'Forrás',
                '意向房源' => 'Érdeklődött lakás',
                '暂未指定' => 'Nincs megadva',
                '跟进状态' => 'Követési státusz',
                '下一次跟进' => 'Következő kapcsolatfelvétel',
                '备注' => 'Megjegyzés',
                '保存客户' => 'Ügyfél mentése',
                '保存修改' => 'Módosítások mentése',
                '取消编辑' => 'Szerkesztés megszakítása',
                '客户维护、保护期和待跟进。' => 'Ügyfélkezelés, védelmi idő és következő teendők.',
                '进入我的客户' => 'Ügyfeleim megnyitása',
                '查看登记时间、30天保护期、跟进状态和客户备注。' => 'Regisztrációs idő, 30 napos védelem, státusz és megjegyzések áttekintése.',
                '全部客户' => 'Összes ügyfél',
                '已联系 / Kapcsolatban' => 'Kapcsolatban',
                '已看房 / Megtekintés' => 'Megtekintés',
                '意向预订 / Foglalási szándék' => 'Foglalási szándék',
                '已成交 / Lezárva' => 'Lezárva',
                '暂缓/无效 / Lezáratlan' => 'Lezáratlan',
                '新客户 / Új' => 'Új ügyfél',
                '成交记录' => 'Lezárt ügyletek',
                '佣金总额' => 'Jutalék összesen',
                '待支付' => 'Fizetésre vár',
                '付款规则' => 'Fizetési szabály',
                '权限' => 'Jogosultság',
                '只读' => 'Csak olvasható',
                '佣金记录' => 'Jutalék rekordok',
                '成交由销售管理确认，经纪人只查看佣金金额、付款日期和结算状态。' => 'A lezárt ügyletet az értékesítés rögzíti; a közvetítő a jutalékot, esedékességet és státuszt látja.',
                '在售房源' => 'Elérhető lakások',
                '目前没有已成交佣金记录。销售端把跟单阶段改为“已成交”并填写佣金后，这里会自动出现。' => 'Jelenleg nincs lezárt jutalék rekord. Értékesítés után automatikusan megjelenik itt.',
                '规则说明' => 'Szabályok',
                '保护期' => 'Védelmi idő',
                '成交佣金' => 'Lezárt jutalék',
                '公开房源' => 'Nyilvános lakáskereső',
                '预算、需求、看房时间、沟通记录' => 'Keret, igény, megtekintési időpont, jegyzetek',
            ),
            'en' => array(
                '客户登记' => 'Client registration',
                '编辑客户' => 'Edit client',
                '姓名 + 电话重复时，系统会提示客户已登记。' => 'If name and phone number match an existing record, the system will warn you.',
                '客户姓名' => 'Client name',
                '电话' => 'Phone',
                '邮箱' => 'Email',
                '来源' => 'Source',
                '意向房源' => 'Interested apartment',
                '暂未指定' => 'Not specified',
                '跟进状态' => 'Follow-up status',
                '下一次跟进' => 'Next follow-up',
                '备注' => 'Notes',
                '保存客户' => 'Save client',
                '保存修改' => 'Save changes',
                '取消编辑' => 'Cancel editing',
                '客户维护、保护期和待跟进。' => 'Client care, protection period and follow-ups.',
                '进入我的客户' => 'Open my clients',
                '查看登记时间、30天保护期、跟进状态和客户备注。' => 'View registration time, 30-day protection, status and notes.',
                '全部客户' => 'All clients',
                '已联系 / Kapcsolatban' => 'Contacted',
                '已看房 / Megtekintés' => 'Visited',
                '意向预订 / Foglalási szándék' => 'Reservation intent',
                '已成交 / Lezárva' => 'Closed',
                '暂缓/无效 / Lezáratlan' => 'Inactive',
                '新客户 / Új' => 'New client',
                '成交记录' => 'Closed records',
                '佣金总额' => 'Commission total',
                '待支付' => 'Pending payment',
                '付款规则' => 'Payment rule',
                '权限' => 'Permission',
                '只读' => 'Read only',
                '佣金记录' => 'Commission records',
                '成交由销售管理确认，经纪人只查看佣金金额、付款日期和结算状态。' => 'Closed deals are confirmed by sales; agents can view commission, due date and settlement status.',
                '在售房源' => 'Available apartments',
                '目前没有已成交佣金记录。销售端把跟单阶段改为“已成交”并填写佣金后，这里会自动出现。' => 'No closed commission records yet. They will appear here after sales confirms the deal.',
                '规则说明' => 'Rules',
                '保护期' => 'Protection period',
                '成交佣金' => 'Closed commission',
                '公开房源' => 'Public apartment search',
                '预算、需求、看房时间、沟通记录' => 'Budget, needs, viewing time, notes',
            ),
        );

        $map = $dictionary[$lang] ?? $dictionary['hu'];
        $script = '(function(){var map=' . wp_json_encode($map, JSON_UNESCAPED_UNICODE) . ';function clean(v){return (v||"").replace(/\\s+/g," ").trim();}function translateNode(node){var key=clean(node.nodeValue);if(map[key]){node.nodeValue=node.nodeValue.replace(key,map[key]);}}function walk(root){var walker=document.createTreeWalker(root,NodeFilter.SHOW_TEXT,{acceptNode:function(node){return clean(node.nodeValue)?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_REJECT;}});var nodes=[];while(walker.nextNode()){nodes.push(walker.currentNode);}nodes.forEach(translateNode);document.querySelectorAll("input[placeholder],textarea[placeholder]").forEach(function(el){var key=clean(el.getAttribute("placeholder"));if(map[key]){el.setAttribute("placeholder",map[key]);}});}document.addEventListener("DOMContentLoaded",function(){walk(document.body);});})();';
        return '<script>' . $script . '</script>';
    }

    private function render_agent_portal() {
        $user = wp_get_current_user();
        $lang = $this->current_portal_language('agent');
        $labels = $this->agent_portal_labels($lang);
        $properties = $this->get_properties();
        $leads = $this->visible_leads($this->get_leads());
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'overview';
        if (!in_array($view, array('overview', 'clients', 'tasks', 'commissions', 'properties', 'rules'), true)) {
            $view = 'overview';
        }
        $property_counts = array('current' => 0, 'reserved' => 0, 'sold' => 0);
        foreach ($properties as $property) {
            $property_counts[$this->sales_status($property->ID)]++;
        }
        $available_properties = array_values(array_filter($properties, function($property) {
            return $this->sales_status($property->ID) === 'current';
        }));

        $lead_counts = array_fill_keys(array_keys($this->lead_status_options()), 0);
        foreach ($leads as $lead) {
            if (isset($lead_counts[$lead['status']])) {
                $lead_counts[$lead['status']]++;
            }
        }
        $agent_tasks = $this->agent_tasks($leads);
        $commission_deals = $this->broker_commission_deals(current_user_can(self::CAP_MANAGE) ? 0 : get_current_user_id());
        $commission_total = $this->sum_commissions($commission_deals);
        $protected_count = count(array_filter($leads, function($lead) {
            return $this->lead_protection_days_left($lead) > 0;
        }));

        $notice = '';
        $notice_type = 'success';
        if (isset($_GET['lead_saved'])) {
            $notice = $labels['notice_saved'];
        } elseif (isset($_GET['lead_deleted'])) {
            $notice = $labels['notice_deleted'];
        } elseif (isset($_GET['lead_error'])) {
            $notice_type = 'error';
            $notice = get_transient('harmat_lead_error_' . get_current_user_id());
            delete_transient('harmat_lead_error_' . get_current_user_id());
            $notice = $notice ?: $labels['notice_error'];
        }

        nocache_headers();
        echo '<!doctype html><html lang="' . esc_attr($this->portal_html_lang($lang)) . '"><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="robots" content="noindex,nofollow"><title>' . esc_html($labels['title']) . '</title><style>' . $this->agent_portal_css() . '</style>' . $this->agent_portal_language_script($lang) . '</head>';
        echo '<body class="harmat-agent-body">';
        echo '<main class="harmat-agent-shell">';
        echo '<header class="harmat-agent-hero">';
        echo '<div><p class="harmat-agent-eyebrow">Harmat Lakópark</p><h1>' . esc_html($labels['heading']) . '</h1><p>' . esc_html($labels['intro']) . '</p></div>';
        echo '<div class="harmat-agent-user">' . $this->portal_logged_language_switch('agent', $lang) . '<span>' . esc_html($user->display_name ?: $user->user_login) . '</span><a href="' . esc_url(wp_logout_url($this->portal_url_with_lang('agent', $lang))) . '">' . esc_html($labels['logout']) . '</a></div>';
        echo '</header>';
        $this->render_agent_nav($view, $lang);

        if ($notice) {
            echo '<div class="harmat-agent-notice harmat-agent-notice-' . esc_attr($notice_type) . '">' . esc_html($notice) . '</div>';
            if ($notice_type === 'error' && isset($_GET['duplicate_lead'])) {
                echo '<script>window.addEventListener("load",function(){alert(' . wp_json_encode($notice, JSON_UNESCAPED_UNICODE) . ');});</script>';
            }
        }

        echo '<section class="harmat-agent-kpis">';
        echo '<article><small>' . esc_html($labels['kpis']['current']) . '</small><strong>' . (int) $property_counts['current'] . '</strong></article>';
        echo '<article><small>' . esc_html($labels['kpis']['tasks']) . '</small><strong>' . count($agent_tasks) . '</strong></article>';
        echo '<article><small>' . esc_html($labels['kpis']['protected']) . '</small><strong>' . (int) $protected_count . '</strong></article>';
        echo '<article><small>' . esc_html($labels['kpis']['clients']) . '</small><strong>' . count($leads) . '</strong></article>';
        echo '<article><small>' . esc_html($labels['kpis']['commission']) . '</small><strong>' . esc_html($this->format_money($commission_total)) . '</strong></article>';
        echo '</section>';

        if ($view === 'clients') {
            $this->render_agent_clients_page($leads, $lead_counts);
            echo '</main></body></html>';
            return;
        }

        if ($view === 'tasks') {
            $this->render_agent_tasks_page($agent_tasks);
            echo '</main></body></html>';
            return;
        }

        if ($view === 'commissions') {
            $this->render_agent_commissions_page($commission_deals);
            echo '</main></body></html>';
            return;
        }

        if ($view === 'properties') {
            $this->render_agent_properties_page($available_properties);
            echo '</main></body></html>';
            return;
        }

        if ($view === 'rules') {
            $this->render_agent_rules_page();
            echo '</main></body></html>';
            return;
        }

        $edit_id = isset($_GET['edit_lead']) ? absint($_GET['edit_lead']) : 0;
        $editing = array();
        $all_leads = $this->get_leads();
        if ($edit_id && isset($all_leads[$edit_id]) && (current_user_can(self::CAP_MANAGE) || (int) $all_leads[$edit_id]['broker_id'] === get_current_user_id())) {
            $editing = $all_leads[$edit_id];
        }
        $agent_form = array_merge(array(
            'id' => 0,
            'client_name' => '',
            'phone' => '',
            'email' => '',
            'source' => '',
            'property_id' => 0,
            'status' => 'new',
            'next_followup' => '',
            'broker_id' => get_current_user_id(),
            'note' => '',
        ), $editing);

        echo '<section class="harmat-agent-grid">';
        echo '<div class="harmat-agent-panel harmat-agent-lead-form-panel">';
        echo '<div class="harmat-agent-panel-head"><h2>' . esc_html($agent_form['id'] ? '编辑客户' : '客户登记') . '</h2><p>姓名 + 电话重复时，系统会提示客户已登记。</p></div>';
        echo '<form method="post" class="harmat-agent-form">';
        wp_nonce_field('harmat_sales_action_save_lead');
        echo '<input type="hidden" name="harmat_sales_action" value="save_lead">';
        echo '<input type="hidden" name="return_to" value="agent">';
        echo '<input type="hidden" name="lead_id" value="' . esc_attr($agent_form['id']) . '">';
        echo '<label>客户姓名<input required name="client_name" value="' . esc_attr($agent_form['client_name']) . '" placeholder="客户姓名"></label>';
        echo '<label>电话<input name="client_phone" value="' . esc_attr($agent_form['phone']) . '" placeholder="+36 30 ..."></label>';
        echo '<label>邮箱<input type="email" name="client_email" value="' . esc_attr($agent_form['email']) . '" placeholder="name@email.com"></label>';
        echo '<label>来源<input name="client_source" value="' . esc_attr($agent_form['source']) . '" placeholder="官网 / 电话 / 转介绍"></label>';
        echo '<label>意向房源<select name="property_id"><option value="0">暂未指定</option>';
        foreach ($properties as $property) {
            echo '<option value="' . esc_attr($property->ID) . '"' . selected((int) $agent_form['property_id'], (int) $property->ID, false) . '>' . esc_html(get_the_title($property)) . '</option>';
        }
        echo '</select></label>';
        if ($agent_form['status'] === 'closed') {
            echo '<label>跟进状态<input class="harmat-agent-readonly-input" value="' . esc_attr($this->lead_status_options()['closed']) . '" readonly></label>';
            echo '<input type="hidden" name="lead_status" value="closed">';
        } else {
            echo '<label>跟进状态<select name="lead_status">';
            foreach ($this->agent_editable_lead_status_options() as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"' . selected($agent_form['status'], $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></label>';
        }
        echo '<label>下次跟进<input type="date" name="next_followup" value="' . esc_attr($agent_form['next_followup']) . '"></label>';
        if (current_user_can(self::CAP_MANAGE)) {
            echo '<label>负责经纪人<select name="broker_id">';
            foreach ($this->get_sales_users() as $sales_user) {
                echo '<option value="' . esc_attr($sales_user->ID) . '"' . selected((int) $agent_form['broker_id'], (int) $sales_user->ID, false) . '>' . esc_html($sales_user->display_name . ' (' . $sales_user->user_login . ')') . '</option>';
            }
            echo '</select></label>';
        }
        echo '<label class="harmat-agent-span">备注<textarea name="client_note" rows="4" placeholder="预算、需求、看房时间、沟通记录">' . esc_textarea($agent_form['note']) . '</textarea></label>';
        echo '<button class="harmat-agent-primary">' . esc_html($agent_form['id'] ? '保存修改' : '保存客户') . '</button>';
        if ($agent_form['id']) {
            echo '<a class="harmat-agent-secondary harmat-agent-span" href="' . esc_url(home_url('/agent/')) . '">取消编辑</a>';
        }
        echo '</form></div>';

        echo '<div class="harmat-agent-panel harmat-agent-client-gateway">';
        echo '<div class="harmat-agent-panel-head"><h2>我的客户</h2><p>客户维护、保护期和待跟进。</p></div>';
        echo '<div class="harmat-agent-mini-stats">';
        echo '<span><small>全部客户</small><b>' . count($leads) . '</b></span>';
        echo '<span><small>待跟进</small><b>' . count($agent_tasks) . '</b></span>';
        echo '<span><small>保护中</small><b>' . (int) $protected_count . '</b></span>';
        echo '<span><small>已成交</small><b>' . (int) $lead_counts['closed'] . '</b></span>';
        echo '</div>';
        if ($agent_tasks) {
            echo '<div class="harmat-agent-task-preview">';
            foreach (array_slice($agent_tasks, 0, 3) as $task) {
                echo '<a href="' . esc_url($task['url']) . '"><strong>' . esc_html($task['date']) . '</strong><span>' . esc_html($task['client'] ?: '-') . '</span><small>' . esc_html($task['title']) . '</small></a>';
            }
            echo '</div>';
        }
        echo '<a class="harmat-agent-primary harmat-agent-client-link" href="' . esc_url(add_query_arg('view', 'clients', home_url('/agent/'))) . '">进入我的客户</a>';
        echo '<p class="harmat-agent-helper">查看登记时间、30天保护期、跟进状态和客户备注。</p>';
        echo '</div></section>';

        echo '<section class="harmat-agent-panel harmat-agent-commission-preview">';
        echo '<div class="harmat-agent-panel-head"><div><h2>我的成交佣金</h2><p>只显示销售端已经确认成交的佣金记录。</p></div><a class="harmat-agent-secondary" href="' . esc_url(add_query_arg('view', 'commissions', home_url('/agent/'))) . '">查看佣金</a></div>';
        $this->render_agent_commission_table(array_slice($commission_deals, 0, 5), true);
        echo '</section>';

        echo '<section class="harmat-agent-panel harmat-agent-properties">';
        echo '<div class="harmat-agent-panel-head"><div><h2>在售房源</h2><p>共 ' . count($available_properties) . ' 套。经纪人可查看库存和价格信息；修改仍由销售管理执行。</p></div><a class="harmat-agent-secondary" href="' . esc_url(add_query_arg('view', 'properties', home_url('/agent/'))) . '">查看全部房源</a></div>';
        $this->render_agent_property_cards(array_slice($available_properties, 0, 18));
        echo '</section>';

        echo '</main></body></html>';
    }

    private function render_agent_clients_page($leads, $lead_counts) {
            echo '<section class="harmat-agent-panel harmat-agent-clients-page">';
            echo '<div class="harmat-agent-panel-head"><div><h2>我的客户</h2><p>登记客户、保护期和后续跟进集中维护。</p></div><a class="harmat-agent-secondary" href="' . esc_url(home_url('/agent/')) . '">返回登记</a></div>';
            echo '<div class="harmat-agent-lead-stats">';
            echo '<span>新客户 <b>' . (int) $lead_counts['new'] . '</b></span>';
            echo '<span>已联系 <b>' . (int) $lead_counts['contacted'] . '</b></span>';
            echo '<span>已看房 <b>' . (int) $lead_counts['visited'] . '</b></span>';
            echo '<span>已成交 <b>' . (int) $lead_counts['closed'] . '</b></span>';
            echo '</div>';
            if (!$leads) {
                echo '<div class="harmat-agent-empty">暂无客户记录。返回登记页录入第一个客户后，这里会显示维护列表。</div>';
            } else {
                echo '<div class="harmat-agent-lead-table-wrap"><table class="harmat-agent-lead-table"><thead><tr><th>客户</th><th>电话</th><th>邮箱</th><th>意向房源</th><th>状态</th><th>登记时间</th><th>保护期</th><th>下次跟进</th>';
                if (current_user_can(self::CAP_MANAGE)) {
                    echo '<th>经纪人</th>';
                }
                echo '<th>备注</th><th>操作</th></tr></thead><tbody>';
                foreach ($leads as $lead) {
                    $this->render_agent_lead_row($lead);
                }
                echo '</tbody></table></div>';
            }
            echo '</section>';
    }

    private function render_agent_nav($active, $lang = 'zh') {
        $labels = $this->agent_portal_labels($lang);
        $items = $labels['nav'];
        $base_url = $this->portal_url_with_lang('agent', $lang);

        echo '<nav class="harmat-agent-nav">';
        foreach ($items as $view => $label) {
            $url = $view === 'overview' ? $base_url : add_query_arg('view', $view, $base_url);
            echo '<a class="' . ($active === $view ? 'is-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '<a href="' . esc_url(home_url('/lakaskereso/')) . '" target="_blank" rel="noopener">' . esc_html($labels['public_properties']) . '</a>';
        echo '</nav>';
    }

    private function render_agent_property_cards($properties) {
        echo '<div class="harmat-agent-property-list">';
        if (!$properties) {
            echo '<div class="harmat-agent-empty">暂无可显示房源。</div>';
            echo '</div>';
            return;
        }

        foreach ($properties as $property) {
            $post_id = $property->ID;
            $status = $this->sales_status($post_id);
            $data = $this->frontend_sales_data(array($post_id));
            $item = isset($data[$post_id]) ? $data[$post_id] : array();
            echo '<a class="harmat-agent-property harmat-agent-property-' . esc_attr($status) . '" href="' . esc_url(get_permalink($post_id)) . '" target="_blank" rel="noopener">';
            echo '<strong>' . esc_html(get_the_title($post_id)) . '</strong>';
            echo '<span>' . esc_html($this->status_options()[$status]) . '</span>';
            echo '<small>' . esc_html(($item['salesArea'] ?? 0) ? $this->format_area($item['salesArea']) . ' m²' : '-') . ' · ' . esc_html(($item['price'] ?? 0) ? $this->format_money($item['price']) . ' HUF' : 'Ár egyeztetés alapján') . '</small>';
            echo '</a>';
        }
        echo '</div>';
    }

    private function render_agent_tasks_page($tasks) {
        echo '<section class="harmat-agent-panel">';
        echo '<div class="harmat-agent-panel-head"><div><h2>待跟进</h2><p>按日期排序，只显示当前经纪人名下客户的下一次跟进。</p></div><a class="harmat-agent-secondary" href="' . esc_url(home_url('/agent/')) . '">登记客户</a></div>';

        if (!$tasks) {
            echo '<div class="harmat-agent-empty">目前没有待跟进事项。给客户设置“下次跟进”日期后，这里会自动出现提醒。</div>';
            echo '</section>';
            return;
        }

        echo '<div class="harmat-agent-lead-table-wrap"><table class="harmat-agent-lead-table harmat-agent-task-table"><thead><tr><th>日期</th><th>紧急程度</th><th>事项</th><th>客户</th><th>房源</th><th>操作</th></tr></thead><tbody>';
        foreach ($tasks as $task) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($task['date']) . '</strong></td>';
            echo '<td><span class="harmat-agent-task-pill harmat-agent-task-' . esc_attr($task['urgency_key']) . '">' . esc_html($task['urgency']) . '</span></td>';
            echo '<td>' . esc_html($task['title']) . '<small>' . esc_html($task['type']) . '</small></td>';
            echo '<td>' . esc_html($task['client'] ?: '-') . '</td>';
            echo '<td>' . esc_html($task['property'] ?: '-') . '</td>';
            echo '<td class="harmat-agent-actions"><a href="' . esc_url($task['url']) . '">处理</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function render_agent_properties_page($available_properties) {
        echo '<section class="harmat-agent-panel harmat-agent-properties-page">';
        echo '<div class="harmat-agent-panel-head"><div><h2>在售房源</h2><p>这里展示当前可售房源，方便经纪人给客户快速匹配。状态和价格由销售管理统一维护。</p></div><a class="harmat-agent-secondary" href="' . esc_url(home_url('/lakaskereso/')) . '" target="_blank" rel="noopener">打开公开房源</a></div>';
        echo '<div class="harmat-agent-property-toolbar"><span>当前在售：<strong>' . count($available_properties) . '</strong> 套</span><span>价格口径：销售管理统一数据</span><span>状态口径：在售 / 预订 / 已售</span></div>';
        $this->render_agent_property_cards($available_properties);
        echo '</section>';
    }

    private function render_agent_commissions_page($commission_deals) {
        $total = $this->sum_commissions($commission_deals);
        $pending = count(array_filter($commission_deals, function($deal) {
            return !in_array(($deal['commission_status'] ?? ''), array('paid', 'withheld'), true);
        }));

        echo '<section class="harmat-agent-kpis harmat-agent-kpis-compact">';
        echo '<article><small>成交记录</small><strong>' . count($commission_deals) . '</strong></article>';
        echo '<article><small>佣金总额</small><strong>' . esc_html($this->format_money($total)) . '</strong></article>';
        echo '<article><small>待支付</small><strong>' . (int) $pending . '</strong></article>';
        echo '<article><small>付款规则</small><strong>30天</strong></article>';
        echo '<article><small>权限</small><strong>只读</strong></article>';
        echo '</section>';

        echo '<section class="harmat-agent-panel">';
        echo '<div class="harmat-agent-panel-head"><div><h2>佣金记录</h2><p>成交由销售管理确认；经纪人只查看佣金金额、付款日期和结算状态。</p></div></div>';
        $this->render_agent_commission_table($commission_deals, false);
        echo '</section>';
    }

    private function render_agent_commission_table($commission_deals, $compact = false) {
        if (!$commission_deals) {
            echo '<div class="harmat-agent-empty">目前没有已成交佣金记录。销售端把跟单阶段改为“已成交”并填写佣金后，这里会自动出现。</div>';
            return;
        }

        echo '<div class="harmat-agent-lead-table-wrap"><table class="harmat-agent-lead-table harmat-agent-commission-table"><thead><tr><th>成交日期</th><th>客户</th><th>房源</th><th>成交金额</th><th>佣金</th><th>预计付款日</th><th>状态</th>';
        if (!$compact) {
            echo '<th>备注</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($commission_deals as $deal) {
            $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
            $status = $deal['commission_status'] ?: 'scheduled';
            $statuses = $this->commission_status_options();
            echo '<tr>';
            echo '<td><strong>' . esc_html($deal['closed_at'] ?: ($deal['expected_close'] ?: '-')) . '</strong></td>';
            echo '<td><strong>' . esc_html($deal['client_name'] ?: '-') . '</strong><small>' . esc_html($deal['phone'] ?: ($deal['email'] ?: '-')) . '</small></td>';
            echo '<td>' . esc_html($property_title ?: '-') . '</td>';
            echo '<td>' . esc_html(!empty($deal['amount']) ? $this->format_money($deal['amount']) . ' Ft' : '-') . '</td>';
            echo '<td><strong>' . esc_html($this->deal_commission_amount($deal) ? $this->format_money($this->deal_commission_amount($deal)) . ' Ft' : '-') . '</strong><small>' . esc_html(!empty($deal['commission_rate']) ? $deal['commission_rate'] . '%' : '佣金比例未填') . '</small></td>';
            echo '<td>' . esc_html($deal['commission_due_date'] ?: '-') . '</td>';
            echo '<td><span class="harmat-agent-task-pill harmat-agent-commission-' . esc_attr($status) . '">' . esc_html($statuses[$status] ?? $status) . '</span></td>';
            if (!$compact) {
                echo '<td class="harmat-agent-note-cell">' . esc_html($deal['commission_note'] ?: '-') . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    private function render_agent_rules_page() {
        echo '<section class="harmat-agent-panel">';
        echo '<div class="harmat-agent-panel-head"><div><h2>经纪人规则说明</h2><p>这个页面用于统一客户登记、保护期和房源沟通口径。</p></div></div>';
        echo '<div class="harmat-agent-rule-list">';
        echo '<span><strong>客户重复判断</strong><b>客户姓名 + 电话</b><small>同一个姓名和电话在保护期内重复登记时，系统会提示已存在。</small></span>';
        echo '<span><strong>客户保护期</strong><b>' . esc_html((string) self::LEAD_PROTECTION_DAYS) . ' 天</b><small>从客户首次登记时间开始计算，过期后保护状态会自动显示为已过期。</small></span>';
        echo '<span><strong>经纪人权限</strong><b>只看自己的客户</b><small>普通经纪人只维护自己登记的客户；销售管理账号可以查看全部客户。</small></span>';
        echo '<span><strong>房源数据</strong><b>统一来自销售库存</b><small>房源状态、价格和前端显示由销售管理工作台统一维护，避免多个口径。</small></span>';
        echo '<span><strong>建议使用方式</strong><b>先登记，再跟进</b><small>录入客户后填写意向房源和下次跟进日期，系统会自动生成待办提醒。</small></span>';
        echo '</div>';
        echo '</section>';
    }

    private function render_sales_portal() {
        $user = wp_get_current_user();
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'dashboard';
        $allowed_views = array('dashboard', 'tasks', 'inquiries', 'deals', 'commissions', 'payments', 'customers', 'clients', 'brokers', 'properties', 'links');
        if (!in_array($view, $allowed_views, true)) {
            $view = 'dashboard';
        }

        $notice = '';
        $notice_type = 'success';
        if (isset($_GET['lead_saved'])) {
            $notice = '客户跟进已保存。';
        } elseif (isset($_GET['lead_deleted'])) {
            $notice = '客户跟进已删除。';
        } elseif (isset($_GET['deal_saved'])) {
            $notice = '销售跟单已保存。';
        } elseif (isset($_GET['deal_deleted'])) {
            $notice = '销售跟单已删除。';
        } elseif (isset($_GET['updated'])) {
            $notice = '房源状态已更新。';
        } elseif (isset($_GET['lead_error'])) {
            $notice_type = 'error';
            $notice = get_transient('harmat_lead_error_' . get_current_user_id());
            delete_transient('harmat_lead_error_' . get_current_user_id());
            $notice = $notice ?: '客户跟进保存失败，请检查资料。';
        } elseif (isset($_GET['deal_error'])) {
            $notice_type = 'error';
            $notice = get_transient('harmat_deal_error_' . get_current_user_id());
            delete_transient('harmat_deal_error_' . get_current_user_id());
            $notice = $notice ?: '销售跟单保存失败，请检查资料。';
        }

        nocache_headers();
        echo '<!doctype html><html lang="zh-CN"><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="robots" content="noindex,nofollow"><title>Harmat销售管理</title><style>' . $this->sales_portal_css() . '</style></head>';
        echo '<body class="harmat-sales-portal-body">';
        echo '<main class="harmat-sales-portal-shell">';
        echo '<header class="harmat-sales-portal-hero">';
        echo '<div><p class="harmat-sales-eyebrow">Harmat Lakópark</p><h1>销售管理工作台</h1><p>网站询价、客户跟进、房源库存和常用入口集中查看。</p></div>';
        echo '<div class="harmat-sales-user"><span>' . esc_html($user->display_name ?: $user->user_login) . '</span><a href="' . esc_url(wp_logout_url(home_url('/sales/'))) . '">退出</a></div>';
        echo '</header>';
        $this->render_sales_portal_nav($view);

        if ($notice) {
            echo '<div class="harmat-sales-notice harmat-sales-notice-' . esc_attr($notice_type) . '">' . esc_html($notice) . '</div>';
        }
        $this->render_sales_portal_account_notices();

        if ($view === 'tasks') {
            $this->render_sales_portal_tasks();
        } elseif ($view === 'inquiries') {
            $this->render_sales_portal_inquiries();
        } elseif ($view === 'deals') {
            $this->render_sales_portal_deals();
        } elseif ($view === 'commissions') {
            $this->render_sales_portal_commissions();
        } elseif ($view === 'payments') {
            $this->render_sales_portal_payments();
        } elseif ($view === 'customers') {
            $this->render_sales_portal_customers();
        } elseif ($view === 'clients') {
            $this->render_sales_portal_clients();
        } elseif ($view === 'brokers') {
            $this->render_sales_portal_brokers();
        } elseif ($view === 'properties') {
            $this->render_sales_portal_properties();
        } elseif ($view === 'links') {
            $this->render_sales_portal_links();
        } else {
            $this->render_sales_portal_dashboard();
        }

        echo '</main></body></html>';
    }

    private function render_sales_portal_nav($active) {
        $items = array(
            'dashboard' => '概览',
            'tasks' => '待办提醒',
            'inquiries' => '网站询价',
            'deals' => '销售跟单',
            'commissions' => '佣金结算',
            'payments' => '付款跟踪',
            'customers' => '客户管理',
            'clients' => '客户跟进',
            'brokers' => '经纪人',
            'properties' => '房源库存',
            'links' => '登录入口',
        );

        echo '<nav class="harmat-sales-nav">';
        foreach ($items as $view => $label) {
            $url = $view === 'dashboard' ? home_url('/sales/') : $this->sales_portal_url(array('view' => $view));
            echo '<a class="' . ($active === $view ? 'is-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '<a href="' . esc_url(home_url('/sales-admin/')) . '" target="_blank" rel="noopener">后台备用</a>';
        echo '</nav>';
    }

    private function render_sales_portal_account_notices() {
        if (isset($_GET['user_error'])) {
            $error = get_transient('harmat_user_error_' . get_current_user_id());
            delete_transient('harmat_user_error_' . get_current_user_id());
            echo '<div class="harmat-sales-notice harmat-sales-notice-error">账号创建失败：' . esc_html($error ?: '请检查用户名、邮箱和角色。') . '</div>';
        }

        if (isset($_GET['created_user'])) {
            $created = get_transient('harmat_created_user_' . get_current_user_id());
            if ($created) {
                delete_transient('harmat_created_user_' . get_current_user_id());
                echo '<div class="harmat-sales-notice harmat-sales-notice-success"><strong>账号已创建，请立即记录密码。</strong><span>用户名：<code>' . esc_html($created['login']) . '</code></span><span>密码：<code>' . esc_html($created['password']) . '</code></span><span>角色：<code>' . esc_html($this->role_label($created['role'])) . '</code></span></div>';
            }
        }

        if (isset($_GET['password_reset'])) {
            $reset = get_transient('harmat_reset_password_' . get_current_user_id());
            if ($reset) {
                delete_transient('harmat_reset_password_' . get_current_user_id());
                echo '<div class="harmat-sales-notice harmat-sales-notice-success"><strong>密码已重置，请立即记录。</strong><span>用户名：<code>' . esc_html($reset['login']) . '</code></span><span>新密码：<code>' . esc_html($reset['password']) . '</code></span></div>';
            }
        }

        if (isset($_GET['deleted_user'])) {
            $deleted = get_transient('harmat_deleted_user_' . get_current_user_id());
            delete_transient('harmat_deleted_user_' . get_current_user_id());
            echo '<div class="harmat-sales-notice harmat-sales-notice-success">账号已删除：<code>' . esc_html($deleted ?: '') . '</code></div>';
        }

        if (isset($_GET['user_updated'])) {
            echo '<div class="harmat-sales-notice harmat-sales-notice-success">经纪人/内部账号资料已保存。</div>';
        }
    }

    private function render_sales_portal_dashboard() {
        $properties = $this->get_properties();
        $leads = $this->visible_leads($this->get_leads());
        $sales_users = $this->get_sales_users();
        $deals = $this->get_deals();
        $tasks = $this->sales_tasks();
        $property_counts = array('current' => 0, 'reserved' => 0, 'sold' => 0);
        foreach ($properties as $property) {
            $property_counts[$this->sales_status($property->ID)]++;
        }

        echo '<section class="harmat-sales-kpis">';
        echo '<article><small>待办提醒</small><strong>' . count($tasks) . '</strong></article>';
        echo '<article><small>网站询价</small><strong>' . (int) $this->count_offer_inquiries() . '</strong></article>';
        echo '<article><small>销售跟单</small><strong>' . count($deals) . '</strong></article>';
        echo '<article><small>客户跟进</small><strong>' . count($leads) . '</strong></article>';
        echo '<article><small>在售房源</small><strong>' . (int) $property_counts['current'] . '</strong></article>';
        echo '</section>';

        echo '<section class="harmat-sales-split">';
        echo '<div class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>待办提醒</h2><p>今天和近期需要处理的销售事项。</p></div><a href="' . esc_url($this->sales_portal_url(array('view' => 'tasks'))) . '">查看全部</a></div>';
        if (!$tasks) {
            echo '<div class="harmat-sales-empty">暂无待办提醒。</div>';
        } else {
            echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table"><thead><tr><th>日期</th><th>事项</th><th>客户</th><th>状态</th></tr></thead><tbody>';
            foreach (array_slice($tasks, 0, 5) as $task) {
                echo '<tr><td><strong>' . esc_html($task['date']) . '</strong><small>' . esc_html($task['urgency']) . '</small></td><td><a href="' . esc_url($task['url']) . '">' . esc_html($task['title']) . '</a><small>' . esc_html($task['type']) . '</small></td><td>' . esc_html($task['client'] ?: '-') . '</td><td><span class="harmat-sales-pill harmat-sales-task-' . esc_attr($task['urgency_key']) . '">' . esc_html($task['urgency']) . '</span></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div>';

        echo '<div class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>最新网站询价</h2><p>客户从公开表单提交的记录。</p></div><a href="' . esc_url($this->sales_portal_url(array('view' => 'inquiries'))) . '">查看全部</a></div>';
        $inquiries = $this->get_offer_inquiry_posts(5);
        if (!$inquiries) {
            echo '<div class="harmat-sales-empty">暂无网站询价记录。</div>';
        } else {
            echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table"><thead><tr><th>客户</th><th>房源</th><th>联系</th><th>时间</th></tr></thead><tbody>';
            foreach ($inquiries as $inquiry) {
                $data = $this->offer_inquiry_data($inquiry->ID);
                echo '<tr><td><strong>' . esc_html($data['name'] ?: '未填写') . '</strong><small>' . esc_html(get_the_date('Y-m-d H:i', $inquiry->ID)) . '</small></td><td>' . esc_html($data['apartment'] ?: '-') . '</td><td><span>' . esc_html($data['phone'] ?: '-') . '</span><small>' . esc_html($data['email'] ?: '-') . '</small></td><td>' . esc_html(trim($data['date'] . ' ' . $data['time']) ?: '-') . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div></section>';

        echo '<section class="harmat-sales-split">';
        echo '<div class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>销售跟单</h2><p>跟进阶段、金额和下一步动作。</p></div><a href="' . esc_url($this->sales_portal_url(array('view' => 'deals'))) . '">查看全部</a></div>';
        if (!$deals) {
            echo '<div class="harmat-sales-empty">暂无销售跟单。</div>';
        } else {
            echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table"><thead><tr><th>客户</th><th>房源</th><th>阶段</th><th>下一步</th></tr></thead><tbody>';
            foreach (array_slice(array_values($deals), 0, 5) as $deal) {
                $stage_options = $this->deal_stage_options();
                $stage = isset($stage_options[$deal['stage']]) ? $deal['stage'] : 'new';
                echo '<tr><td><strong>' . esc_html($deal['client_name']) . '</strong><small>' . esc_html($deal['phone'] ?: '-') . '</small></td><td>' . esc_html($deal['property_id'] ? get_the_title((int) $deal['property_id']) : '暂未指定') . '</td><td><span class="harmat-sales-pill harmat-sales-deal-' . esc_attr($stage) . '">' . esc_html($stage_options[$stage]) . '</span></td><td>' . esc_html($deal['next_step'] ?: ($deal['next_followup'] ?: '-')) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div>';

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>客户保护</h2><p>最新登记客户和30天保护期。</p></div><a href="' . esc_url($this->sales_portal_url(array('view' => 'clients'))) . '">查看全部</a></div>';
        if (!$leads) {
            echo '<div class="harmat-sales-empty">暂无客户跟进记录。</div>';
        } else {
            echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table"><thead><tr><th>客户</th><th>意向房源</th><th>状态</th><th>保护</th></tr></thead><tbody>';
            foreach (array_slice($leads, 0, 5) as $lead) {
                $status_options = $this->lead_status_options();
                $status = isset($status_options[$lead['status']]) ? $lead['status'] : 'new';
                $days_left = $this->lead_protection_days_left($lead);
                echo '<tr><td><strong>' . esc_html($lead['client_name']) . '</strong><small>' . esc_html($lead['phone'] ?: '-') . '</small></td><td>' . esc_html($lead['property_id'] ? get_the_title((int) $lead['property_id']) : '暂未指定') . '</td><td><span class="harmat-sales-pill">' . esc_html($status_options[$status]) . '</span></td><td>' . esc_html($days_left > 0 ? '剩余 ' . $days_left . ' 天' : '已过期') . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section></section>';
    }

    private function render_sales_portal_inquiries() {
        $search = isset($_GET['sales_search']) ? sanitize_text_field(wp_unslash($_GET['sales_search'])) : '';
        $inquiries = $this->get_offer_inquiry_posts(80, $search);

        echo '<section class="harmat-sales-panel">';
        echo '<div class="harmat-sales-panel-head"><div><h2>网站询价记录</h2><p>客户从官网报价、预约表单提交的房号和联系方式。</p></div>';
        echo '<form method="get" class="harmat-sales-search"><input type="hidden" name="view" value="inquiries"><input name="sales_search" value="' . esc_attr($search) . '" placeholder="搜索客户、邮箱、房号"><button>搜索</button></form></div>';

        if (!$inquiries) {
            echo '<div class="harmat-sales-empty">没有找到网站询价记录。</div></section>';
            return;
        }

        echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table harmat-sales-inquiry-table"><thead><tr><th>提交时间</th><th>客户</th><th>房号</th><th>联系方式</th><th>房源信息</th><th>看房时间</th><th>邮件</th><th>留言/链接</th></tr></thead><tbody>';
        foreach ($inquiries as $inquiry) {
            $this->render_sales_portal_inquiry_row($inquiry->ID);
        }
        echo '</tbody></table></div></section>';
    }

    private function render_sales_portal_tasks() {
        $tasks = $this->sales_tasks();
        $overdue = count(array_filter($tasks, function($task) { return $task['urgency_key'] === 'overdue'; }));
        $today = count(array_filter($tasks, function($task) { return $task['urgency_key'] === 'today'; }));
        $upcoming = count(array_filter($tasks, function($task) { return $task['urgency_key'] === 'upcoming'; }));

        echo '<section class="harmat-sales-kpis harmat-sales-kpis-compact">';
        echo '<article><small>全部待办</small><strong>' . count($tasks) . '</strong></article>';
        echo '<article><small>已逾期</small><strong>' . (int) $overdue . '</strong></article>';
        echo '<article><small>今天</small><strong>' . (int) $today . '</strong></article>';
        echo '<article><small>近期</small><strong>' . (int) $upcoming . '</strong></article>';
        echo '<article><small>来源</small><strong>销售</strong></article>';
        echo '</section>';

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>待办提醒</h2><p>从客户跟进、销售跟单、付款截止日和预计成交日自动生成。</p></div></div>';
        if (!$tasks) {
            echo '<div class="harmat-sales-empty">暂无待办。给客户或跟单设置“下次跟进/付款截止日/预计成交日”后，这里会自动出现。</div></section>';
            return;
        }

        echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table harmat-sales-task-table"><thead><tr><th>日期</th><th>类型</th><th>客户</th><th>房源</th><th>事项</th><th>状态</th><th>入口</th></tr></thead><tbody>';
        foreach ($tasks as $task) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($task['date']) . '</strong><small>' . esc_html($task['urgency']) . '</small></td>';
            echo '<td>' . esc_html($task['type']) . '</td>';
            echo '<td>' . esc_html($task['client'] ?: '-') . '</td>';
            echo '<td>' . esc_html($task['property'] ?: '-') . '</td>';
            echo '<td>' . esc_html($task['title']) . '</td>';
            echo '<td><span class="harmat-sales-pill harmat-sales-task-' . esc_attr($task['urgency_key']) . '">' . esc_html($task['urgency']) . '</span></td>';
            echo '<td><a href="' . esc_url($task['url']) . '">处理</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function render_sales_portal_payments() {
        $deals = $this->payment_deals();
        $total = 0;
        $received = 0;
        $overdue = 0;
        $paid = 0;
        foreach ($deals as $deal) {
            $total += (int) ($deal['amount'] ?? 0);
            $received += (int) ($deal['payment_received'] ?? 0);
            if (($deal['payment_status'] ?? '') === 'overdue') {
                $overdue++;
            }
            if (($deal['payment_status'] ?? '') === 'paid') {
                $paid++;
            }
        }
        $balance = max(0, $total - $received);

        echo '<section class="harmat-sales-kpis harmat-sales-kpis-compact">';
        echo '<article><small>应收总额</small><strong>' . esc_html($this->format_money($total)) . '</strong></article>';
        echo '<article><small>已收总额</small><strong>' . esc_html($this->format_money($received)) . '</strong></article>';
        echo '<article><small>未收总额</small><strong>' . esc_html($this->format_money($balance)) . '</strong></article>';
        echo '<article><small>逾期</small><strong>' . (int) $overdue . '</strong></article>';
        echo '<article><small>已收齐</small><strong>' . (int) $paid . '</strong></article>';
        echo '</section>';

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>付款跟踪</h2><p>按销售跟单汇总应收、已收、未收和付款状态。</p></div></div>';
        if (!$deals) {
            echo '<div class="harmat-sales-empty">暂无付款记录。销售跟单填写金额后会进入付款跟踪。</div></section>';
            return;
        }

        echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table harmat-sales-payment-table"><thead><tr><th>客户</th><th>房源</th><th>应收</th><th>已收</th><th>未收</th><th>付款方式</th><th>状态</th><th>截止日</th><th>合同</th><th>操作</th></tr></thead><tbody>';
        foreach ($deals as $deal) {
            $payment_options = $this->payment_method_options();
            $payment_statuses = $this->payment_status_options();
            $contract_options = $this->contract_status_options();
            $status = $this->infer_payment_status($deal['amount'] ?? '', $deal['payment_received'] ?? '', $deal['payment_due_date'] ?? '', $deal['payment_status'] ?? '');
            $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
            echo '<tr>';
            echo '<td><strong>' . esc_html($deal['client_name'] ?: '未填写') . '</strong><small>' . esc_html($deal['phone'] ?: ($deal['email'] ?: '-')) . '</small></td>';
            echo '<td>' . esc_html($property_title ?: '-') . '</td>';
            echo '<td>' . esc_html(!empty($deal['amount']) ? $this->format_money($deal['amount']) . ' Ft' : '-') . '</td>';
            echo '<td>' . esc_html(!empty($deal['payment_received']) ? $this->format_money($deal['payment_received']) . ' Ft' : '0 Ft') . '</td>';
            echo '<td>' . esc_html($this->format_money($this->deal_payment_balance($deal)) . ' Ft') . '</td>';
            echo '<td>' . esc_html(!empty($deal['payment_method']) && isset($payment_options[$deal['payment_method']]) ? $payment_options[$deal['payment_method']] : '-') . '</td>';
            echo '<td><span class="harmat-sales-pill harmat-sales-payment-' . esc_attr($status) . '">' . esc_html($payment_statuses[$status] ?? $status) . '</span></td>';
            echo '<td>' . esc_html($deal['payment_due_date'] ?: '-') . '</td>';
            echo '<td>' . esc_html(!empty($deal['contract_status']) && isset($contract_options[$deal['contract_status']]) ? $contract_options[$deal['contract_status']] : '-') . '</td>';
            echo '<td class="harmat-sales-actions"><a href="' . esc_url($this->sales_portal_url(array('view' => 'deals', 'edit_deal' => (int) $deal['id']))) . '">编辑</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function render_sales_portal_deals() {
        $deals = $this->get_deals();
        $leads = $this->get_leads();
        $edit_id = isset($_GET['edit_deal']) ? absint($_GET['edit_deal']) : 0;
        $lead_id = isset($_GET['lead_id']) ? absint($_GET['lead_id']) : 0;
        $inquiry_id = isset($_GET['inquiry_id']) ? absint($_GET['inquiry_id']) : 0;
        $form = array(
            'id' => 0,
            'lead_id' => $lead_id,
            'inquiry_id' => $inquiry_id,
            'property_id' => 0,
            'broker_id' => get_current_user_id(),
            'stage' => 'new',
            'client_name' => '',
            'phone' => '',
            'email' => '',
            'amount' => '',
            'deposit' => '',
            'payment_received' => '',
            'expected_close' => '',
            'next_followup' => '',
            'next_step' => '',
            'payment_method' => '',
            'payment_due_date' => '',
            'payment_status' => '',
            'payment_schedule' => '',
            'payment_plan_items' => array(),
            'document_checklist' => array(),
            'contract_status' => '',
            'handover_note' => '',
            'closed_at' => '',
            'commission_rate' => '',
            'commission_amount' => '',
            'commission_due_date' => '',
            'commission_status' => '',
            'commission_note' => '',
            'note' => '',
        );

        if ($edit_id && isset($deals[$edit_id])) {
            $form = array_merge($form, $deals[$edit_id]);
        } elseif ($lead_id && isset($leads[$lead_id])) {
            $lead = $leads[$lead_id];
            $form['client_name'] = $lead['client_name'] ?? '';
            $form['phone'] = $lead['phone'] ?? '';
            $form['email'] = $lead['email'] ?? '';
            $form['property_id'] = (int) ($lead['property_id'] ?? 0);
            $form['broker_id'] = (int) ($lead['broker_id'] ?? get_current_user_id());
            $form['next_followup'] = $lead['next_followup'] ?? '';
            $form['note'] = $lead['note'] ?? '';
        } elseif ($inquiry_id && get_post_type($inquiry_id) === 'harmat_offer_lead') {
            $inquiry = $this->offer_inquiry_data($inquiry_id);
            $form['client_name'] = $inquiry['name'];
            $form['phone'] = $inquiry['phone'];
            $form['email'] = $inquiry['email'];
            $form['property_id'] = $inquiry['apartment'] ? $this->property_id_by_title($inquiry['apartment']) : 0;
            $form['note'] = $inquiry['message'];
        }

        $stage_counts = array_fill_keys(array_keys($this->deal_stage_options()), 0);
        foreach ($deals as $deal) {
            if (isset($stage_counts[$deal['stage']])) {
                $stage_counts[$deal['stage']]++;
            }
        }

        echo '<section class="harmat-sales-kpis harmat-sales-kpis-compact">';
        echo '<article><small>全部跟单</small><strong>' . count($deals) . '</strong></article>';
        echo '<article><small>看房/沟通</small><strong>' . ((int) $stage_counts['contacted'] + (int) $stage_counts['viewing'] + (int) $stage_counts['negotiation']) . '</strong></article>';
        echo '<article><small>预订/合同</small><strong>' . ((int) $stage_counts['reserved'] + (int) $stage_counts['contract']) . '</strong></article>';
        echo '<article><small>成交</small><strong>' . (int) $stage_counts['closed'] . '</strong></article>';
        echo '<article><small>流失</small><strong>' . (int) $stage_counts['lost'] . '</strong></article>';
        echo '</section>';

        echo '<section class="harmat-sales-split harmat-sales-deal-workspace">';
        echo '<div class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>' . esc_html($form['id'] ? '编辑销售跟单' : '新增销售跟单') . '</h2><p>把客户、房源、经纪人和销售阶段绑定在一起。</p></div></div>';
        echo '<form method="post" class="harmat-sales-form">';
        wp_nonce_field('harmat_sales_action_save_deal');
        echo '<input type="hidden" name="harmat_sales_action" value="save_deal">';
        echo '<input type="hidden" name="return_to" value="sales_deals">';
        echo '<input type="hidden" name="deal_id" value="' . esc_attr($form['id']) . '">';
        echo '<label>关联客户<select name="deal_lead_id"><option value="0">不关联客户跟进</option>';
        foreach ($leads as $lead) {
            echo '<option value="' . esc_attr($lead['id']) . '"' . selected((int) $form['lead_id'], (int) $lead['id'], false) . '>' . esc_html($lead['client_name'] . ' / ' . ($lead['phone'] ?: $lead['email'])) . '</option>';
        }
        echo '</select></label>';
        echo '<label>来源询价<select name="deal_inquiry_id"><option value="0">不关联网站询价</option>';
        foreach ($this->get_offer_inquiry_posts(60) as $inquiry_post) {
            $inquiry = $this->offer_inquiry_data($inquiry_post->ID);
            echo '<option value="' . esc_attr($inquiry_post->ID) . '"' . selected((int) $form['inquiry_id'], (int) $inquiry_post->ID, false) . '>' . esc_html(($inquiry['name'] ?: '未填写') . ' / ' . ($inquiry['apartment'] ?: '未选房源')) . '</option>';
        }
        echo '</select></label>';
        echo '<label>客户姓名<input name="deal_client_name" value="' . esc_attr($form['client_name']) . '" placeholder="客户姓名"></label>';
        echo '<label>电话<input name="deal_phone" value="' . esc_attr($form['phone']) . '" placeholder="+36..."></label>';
        echo '<label>邮箱<input type="email" name="deal_email" value="' . esc_attr($form['email']) . '" placeholder="name@email.com"></label>';
        echo '<label>负责经纪人<select name="deal_broker_id">';
        foreach ($this->get_sales_users() as $sales_user) {
            echo '<option value="' . esc_attr($sales_user->ID) . '"' . selected((int) $form['broker_id'], (int) $sales_user->ID, false) . '>' . esc_html($sales_user->display_name . ' (' . $sales_user->user_login . ')') . '</option>';
        }
        echo '</select></label>';
        echo '<label>意向房源<select name="deal_property_id"><option value="0">暂未指定</option>';
        foreach ($this->get_properties() as $property) {
            echo '<option value="' . esc_attr($property->ID) . '"' . selected((int) $form['property_id'], (int) $property->ID, false) . '>' . esc_html(get_the_title($property)) . '</option>';
        }
        echo '</select></label>';
        echo '<label>销售阶段<select name="deal_stage">';
        foreach ($this->deal_stage_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($form['stage'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>成交/报价金额 HUF<input name="deal_amount" value="' . esc_attr($form['amount']) . '" inputmode="numeric" placeholder="例如 114000000"></label>';
        echo '<label>定金 HUF<input name="deal_deposit" value="' . esc_attr($form['deposit']) . '" inputmode="numeric" placeholder="可选"></label>';
        echo '<label>佣金比例 %<input name="deal_commission_rate" value="' . esc_attr($form['commission_rate']) . '" inputmode="decimal" placeholder="例如 1.5"></label>';
        echo '<label>佣金金额 HUF<input name="deal_commission_amount" value="' . esc_attr($form['commission_amount']) . '" inputmode="numeric" placeholder="不填则按比例计算"></label>';
        echo '<label>已收金额 HUF<input name="deal_payment_received" value="' . esc_attr($form['payment_received']) . '" inputmode="numeric" placeholder="已经收到的总金额"></label>';
        echo '<label>付款截止日<input type="date" name="deal_payment_due_date" value="' . esc_attr($form['payment_due_date']) . '"></label>';
        echo '<label>预计成交日期<input type="date" name="deal_expected_close" value="' . esc_attr($form['expected_close']) . '"></label>';
        echo '<label>佣金付款日<input type="date" name="deal_commission_due_date" value="' . esc_attr($form['commission_due_date']) . '"></label>';
        echo '<label>下次跟进<input type="date" name="deal_next_followup" value="' . esc_attr($form['next_followup']) . '"></label>';
        echo '<label>付款方式<select name="deal_payment_method"><option value="">暂未确定</option>';
        foreach ($this->payment_method_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($form['payment_method'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>付款状态<select name="deal_payment_status"><option value="">自动判断</option>';
        foreach ($this->payment_status_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($form['payment_status'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>合同状态<select name="deal_contract_status"><option value="">暂未确定</option>';
        foreach ($this->contract_status_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($form['contract_status'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>佣金状态<select name="deal_commission_status"><option value="">成交后自动设为待支付</option>';
        foreach ($this->commission_status_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($form['commission_status'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label class="harmat-sales-form-wide">下一步动作<input name="deal_next_step" value="' . esc_attr($form['next_step']) . '" placeholder="例如：发送报价、约看房、准备合同"></label>';
        echo '<label class="harmat-sales-form-wide">付款节点<textarea name="deal_payment_schedule" rows="3" placeholder="例如：10% 定金，30% 合同签署后，60% 交付前">' . esc_textarea($form['payment_schedule']) . '</textarea></label>';
        $this->render_deal_payment_plan_editor($form);
        $this->render_deal_document_checklist_editor($form);
        echo '<label class="harmat-sales-form-wide">佣金备注<textarea name="deal_commission_note" rows="3" placeholder="佣金计算口径、付款条件、内部确认记录">' . esc_textarea($form['commission_note']) . '</textarea></label>';
        echo '<label class="harmat-sales-form-wide">交付/售后备注<textarea name="deal_handover_note" rows="3" placeholder="交付时间、钥匙、车位、储藏室、客户特殊要求等">' . esc_textarea($form['handover_note']) . '</textarea></label>';
        echo '<label class="harmat-sales-form-wide">备注<textarea name="deal_note" rows="4" placeholder="销售沟通记录、客户顾虑、付款计划等">' . esc_textarea($form['note']) . '</textarea></label>';
        echo '<label class="harmat-sales-check harmat-sales-form-wide"><input type="checkbox" name="sync_property_status" value="1" checked> 阶段为预订/合同/成交时，同步更新房源状态</label>';
        echo '<div class="harmat-sales-form-actions"><button>' . esc_html($form['id'] ? '保存跟单' : '新增跟单') . '</button>';
        if ($form['id']) {
            echo '<a href="' . esc_url($this->sales_portal_url(array('view' => 'deals'))) . '">取消编辑</a>';
        }
        echo '</div></form></div>';

        echo '<div class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>销售阶段</h2><p>建议每个客户都落到一个明确阶段，下一步动作必须清楚。</p></div></div>';
        echo '<div class="harmat-sales-stage-list">';
        foreach ($this->deal_stage_options() as $value => $label) {
            echo '<span class="harmat-sales-stage-' . esc_attr($value) . '"><strong>' . esc_html($label) . '</strong><b>' . esc_html((string) ($stage_counts[$value] ?? 0)) . '</b></span>';
        }
        echo '</div></div></section>';

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>销售跟单列表</h2><p>一行一个销售机会，可直接编辑阶段并同步房源状态。</p></div></div>';
        if (!$deals) {
            echo '<div class="harmat-sales-empty">暂无销售跟单。可以从网站询价或客户跟进生成第一条跟单。</div></section>';
            return;
        }

        echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table harmat-sales-deal-table"><thead><tr><th>客户</th><th>房源</th><th>阶段</th><th>金额/定金</th><th>佣金</th><th>付款/合同</th><th>下一步</th><th>经纪人</th><th>来源</th><th>更新</th><th>操作</th></tr></thead><tbody>';
        foreach ($deals as $deal) {
            $this->render_sales_portal_deal_row($deal);
        }
        echo '</tbody></table></div></section>';
    }

    private function render_deal_payment_plan_editor($form) {
        $rows = $this->payment_plan_form_rows($form, 6);
        $statuses = $this->payment_plan_status_options();

        echo '<div class="harmat-sales-form-wide harmat-sales-subtable-block"><h3>付款计划明细</h3><p>这里会同步到客户中心，适合填写每一期应付金额、截止日和已付状态。</p>';
        echo '<div class="harmat-sales-table-wrap harmat-sales-subtable-wrap"><table class="harmat-sales-table harmat-sales-plan-table"><thead><tr><th>节点</th><th>应付金额 HUF</th><th>截止日期</th><th>已付金额 HUF</th><th>状态</th><th>备注</th></tr></thead><tbody>';
        foreach ($rows as $index => $row) {
            echo '<tr>';
            echo '<td><input name="payment_plan_items[' . esc_attr($index) . '][label]" value="' . esc_attr($row['label'] ?? '') . '" placeholder="例如 定金 / 首付款"></td>';
            echo '<td><input name="payment_plan_items[' . esc_attr($index) . '][amount]" value="' . esc_attr($row['amount'] ?? '') . '" inputmode="numeric" placeholder="金额"></td>';
            echo '<td><input type="date" name="payment_plan_items[' . esc_attr($index) . '][due_date]" value="' . esc_attr($row['due_date'] ?? '') . '"></td>';
            echo '<td><input name="payment_plan_items[' . esc_attr($index) . '][paid_amount]" value="' . esc_attr($row['paid_amount'] ?? '') . '" inputmode="numeric" placeholder="已付"></td>';
            echo '<td><select name="payment_plan_items[' . esc_attr($index) . '][status]"><option value="">自动/待支付</option>';
            foreach ($statuses as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"' . selected(($row['status'] ?? ''), $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></td>';
            echo '<td><input name="payment_plan_items[' . esc_attr($index) . '][note]" value="' . esc_attr($row['note'] ?? '') . '" placeholder="可选"></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private function render_deal_document_checklist_editor($form) {
        $rows = $this->document_checklist_rows($form);
        $statuses = $this->document_checklist_status_options();

        echo '<div class="harmat-sales-form-wide harmat-sales-subtable-block"><h3>客户资料清单</h3><p>用于销售端确认资料是否齐全；勾选“客户可见”后，客户中心也会显示该项状态。</p>';
        echo '<div class="harmat-sales-table-wrap harmat-sales-subtable-wrap"><table class="harmat-sales-table harmat-sales-document-table"><thead><tr><th>资料</th><th>状态</th><th>客户可见</th><th>备注</th></tr></thead><tbody>';
        foreach ($rows as $key => $row) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($row['label_sales']) . '</strong><input type="hidden" name="document_checklist[' . esc_attr($key) . '][key]" value="' . esc_attr($key) . '"></td>';
            echo '<td><select name="document_checklist[' . esc_attr($key) . '][status]">';
            foreach ($statuses as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"' . selected(($row['status'] ?? 'missing'), $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></td>';
            echo '<td><label class="harmat-sales-check"><input type="checkbox" name="document_checklist[' . esc_attr($key) . '][visible]" value="1"' . checked(!empty($row['visible']), true, false) . '> 显示</label></td>';
            echo '<td><input name="document_checklist[' . esc_attr($key) . '][note]" value="' . esc_attr($row['note'] ?? '') . '" placeholder="可选内部/客户说明"></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private function render_sales_portal_commissions() {
        $deals = $this->broker_commission_deals(0);
        $total = $this->sum_commissions($deals);
        $paid_total = 0;
        $pending = 0;
        foreach ($deals as $deal) {
            if (($deal['commission_status'] ?? '') === 'paid') {
                $paid_total += $this->deal_commission_amount($deal);
            } else {
                $pending++;
            }
        }

        echo '<section class="harmat-sales-kpis harmat-sales-kpis-compact">';
        echo '<article><small>成交佣金单</small><strong>' . count($deals) . '</strong></article>';
        echo '<article><small>佣金总额</small><strong>' . esc_html($this->format_money($total)) . '</strong></article>';
        echo '<article><small>已支付</small><strong>' . esc_html($this->format_money($paid_total)) . '</strong></article>';
        echo '<article><small>待处理</small><strong>' . (int) $pending . '</strong></article>';
        echo '<article><small>付款周期</small><strong>30天</strong></article>';
        echo '</section>';

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>佣金结算</h2><p>只统计销售阶段为“已成交”的跟单。佣金金额、付款日和状态在销售跟单里编辑。</p></div></div>';
        if (!$deals) {
            echo '<div class="harmat-sales-empty">暂无已成交佣金记录。</div></section>';
            return;
        }

        $statuses = $this->commission_status_options();
        echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table harmat-sales-commission-table"><thead><tr><th>成交日期</th><th>客户</th><th>房源</th><th>经纪人</th><th>成交金额</th><th>佣金</th><th>付款日</th><th>状态</th><th>备注</th><th>操作</th></tr></thead><tbody>';
        foreach ($deals as $deal) {
            $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
            $broker = !empty($deal['broker_id']) ? get_userdata((int) $deal['broker_id']) : null;
            $status = $deal['commission_status'] ?: 'scheduled';
            echo '<tr>';
            echo '<td><strong>' . esc_html($deal['closed_at'] ?: ($deal['expected_close'] ?: '-')) . '</strong></td>';
            echo '<td><strong>' . esc_html($deal['client_name'] ?: '-') . '</strong><small>' . esc_html($deal['phone'] ?: ($deal['email'] ?: '-')) . '</small></td>';
            echo '<td>' . esc_html($property_title ?: '-') . '</td>';
            echo '<td>' . esc_html($broker ? $broker->display_name : '-') . '</td>';
            echo '<td>' . esc_html(!empty($deal['amount']) ? $this->format_money($deal['amount']) . ' Ft' : '-') . '</td>';
            echo '<td><strong>' . esc_html($this->deal_commission_amount($deal) ? $this->format_money($this->deal_commission_amount($deal)) . ' Ft' : '-') . '</strong><small>' . esc_html(!empty($deal['commission_rate']) ? $deal['commission_rate'] . '%' : '比例未填') . '</small></td>';
            echo '<td>' . esc_html($deal['commission_due_date'] ?: '-') . '</td>';
            echo '<td><span class="harmat-sales-pill harmat-sales-commission-' . esc_attr($status) . '">' . esc_html($statuses[$status] ?? $status) . '</span></td>';
            echo '<td class="harmat-sales-note-cell">' . esc_html($deal['commission_note'] ?: '-') . '</td>';
            echo '<td class="harmat-sales-actions"><a href="' . esc_url($this->sales_portal_url(array('view' => 'deals', 'edit_deal' => (int) $deal['id']))) . '">编辑</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function render_sales_portal_clients() {
        $all_leads = $this->get_leads();
        $leads = $this->visible_leads($this->get_leads());
        $status_options = $this->lead_status_options();
        $status_counts = array_fill_keys(array_keys($status_options), 0);
        foreach ($leads as $lead) {
            if (isset($status_counts[$lead['status']])) {
                $status_counts[$lead['status']]++;
            }
        }
        $edit_id = isset($_GET['edit_lead']) ? absint($_GET['edit_lead']) : 0;
        $editing = ($edit_id && isset($all_leads[$edit_id])) ? $all_leads[$edit_id] : array();
        $form = array_merge(array(
            'id' => 0,
            'broker_id' => get_current_user_id(),
            'client_name' => '',
            'phone' => '',
            'email' => '',
            'property_id' => 0,
            'status' => 'new',
            'source' => '',
            'next_followup' => '',
            'note' => '',
        ), $editing);

        echo '<section class="harmat-sales-kpis harmat-sales-kpis-compact">';
        echo '<article><small>全部客户</small><strong>' . count($leads) . '</strong></article>';
        echo '<article><small>新客户</small><strong>' . (int) $status_counts['new'] . '</strong></article>';
        echo '<article><small>已联系</small><strong>' . (int) $status_counts['contacted'] . '</strong></article>';
        echo '<article><small>已看房</small><strong>' . (int) $status_counts['visited'] . '</strong></article>';
        echo '<article><small>已成交</small><strong>' . (int) $status_counts['closed'] . '</strong></article>';
        echo '</section>';

        echo '<section class="harmat-sales-split harmat-sales-client-workspace">';
        echo '<div class="harmat-sales-panel">';
        echo '<div class="harmat-sales-panel-head"><div><h2>' . esc_html($form['id'] ? '编辑客户跟进' : '新增客户跟进') . '</h2><p>销售管理可以录入客户、分配经纪人，并自动进入30天保护期。</p></div></div>';
        echo '<form method="post" class="harmat-sales-form">';
        wp_nonce_field('harmat_sales_action_save_lead');
        echo '<input type="hidden" name="harmat_sales_action" value="save_lead">';
        echo '<input type="hidden" name="return_to" value="sales_clients">';
        echo '<input type="hidden" name="lead_id" value="' . esc_attr($form['id']) . '">';
        echo '<label>客户姓名<input required name="client_name" value="' . esc_attr($form['client_name']) . '" placeholder="客户姓名"></label>';
        echo '<label>电话<input name="client_phone" value="' . esc_attr($form['phone']) . '" placeholder="+36..."></label>';
        echo '<label>邮箱<input type="email" name="client_email" value="' . esc_attr($form['email']) . '" placeholder="name@email.com"></label>';
        echo '<label>来源<input name="client_source" value="' . esc_attr($form['source']) . '" placeholder="官网 / 电话 / 转介绍"></label>';
        echo '<label>意向房源<select name="property_id"><option value="0">暂未指定</option>';
        foreach ($this->get_properties() as $property) {
            echo '<option value="' . esc_attr($property->ID) . '"' . selected((int) $form['property_id'], (int) $property->ID, false) . '>' . esc_html(get_the_title($property)) . '</option>';
        }
        echo '</select></label>';
        echo '<label>跟进状态<select name="lead_status">';
        foreach ($status_options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($form['status'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>下次跟进<input type="date" name="next_followup" value="' . esc_attr($form['next_followup']) . '"></label>';
        echo '<label>负责经纪人<select name="broker_id">';
        foreach ($this->get_sales_users() as $sales_user) {
            echo '<option value="' . esc_attr($sales_user->ID) . '"' . selected((int) $form['broker_id'], (int) $sales_user->ID, false) . '>' . esc_html($sales_user->display_name . ' (' . $sales_user->user_login . ')') . '</option>';
        }
        echo '</select></label>';
        echo '<label class="harmat-sales-form-wide">备注<textarea name="client_note" rows="4" placeholder="预算、需求、看房时间、沟通记录">' . esc_textarea($form['note']) . '</textarea></label>';
        echo '<div class="harmat-sales-form-actions"><button>' . esc_html($form['id'] ? '保存修改' : '新增客户') . '</button>';
        if ($form['id']) {
            echo '<a href="' . esc_url($this->sales_portal_url(array('view' => 'clients'))) . '">取消编辑</a>';
        }
        echo '</div></form></div>';

        echo '<div class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>客户规则</h2><p>系统按照客户姓名 + 电话判断重复，保护期为30天。</p></div></div>';
        echo '<div class="harmat-sales-rule-list"><span><strong>保护期</strong><b>' . esc_html((string) self::LEAD_PROTECTION_DAYS) . ' 天</b></span><span><strong>重复判断</strong><b>姓名 + 电话</b></span><span><strong>销售权限</strong><b>查看全部客户</b></span></div></div>';
        echo '</section>';

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>客户跟进列表</h2><p>一行一个客户，显示登记时间、保护到期和负责人。</p></div></div>';
        if (!$leads) {
            echo '<div class="harmat-sales-empty">暂无客户记录。</div></section>';
            return;
        }

        echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table"><thead><tr><th>客户</th><th>联系方式</th><th>意向房源</th><th>状态</th><th>登记时间</th><th>保护到期</th><th>保护状态</th><th>下次跟进</th><th>负责人</th><th>备注</th><th>操作</th></tr></thead><tbody>';
        foreach ($leads as $lead) {
            $this->render_sales_portal_client_row($lead);
        }
        echo '</tbody></table></div></section>';
    }

    private function render_sales_portal_customers() {
        $deals = array_values(array_filter($this->get_deals(), function($deal) {
            return ($deal['stage'] ?? '') === 'closed';
        }));
        $customer_id = isset($_GET['customer_id']) ? absint($_GET['customer_id']) : 0;
        $selected = null;
        foreach ($deals as $deal) {
            if ((int) $deal['id'] === $customer_id) {
                $selected = $deal;
                break;
            }
        }

        if ($customer_id && $selected) {
            $this->render_sales_customer_profile($selected, $deals);
            return;
        }

        $amount_total = 0;
        $received_total = 0;
        $balance_total = 0;
        $commission_total = 0;
        foreach ($deals as $deal) {
            $amount_total += (int) ($deal['amount'] ?? 0);
            $received_total += (int) ($deal['payment_received'] ?? 0);
            $balance_total += $this->deal_payment_balance($deal);
            $commission_total += $this->deal_commission_amount($deal);
        }

        echo '<section class="harmat-sales-kpis harmat-sales-kpis-compact">';
        echo '<article><small>成交客户</small><strong>' . count($deals) . '</strong></article>';
        echo '<article><small>成交总额</small><strong>' . esc_html($this->format_money($amount_total)) . '</strong></article>';
        echo '<article><small>已收金额</small><strong>' . esc_html($this->format_money($received_total)) . '</strong></article>';
        echo '<article><small>未收金额</small><strong>' . esc_html($this->format_money($balance_total)) . '</strong></article>';
        echo '<article><small>佣金总额</small><strong>' . esc_html($this->format_money($commission_total)) . '</strong></article>';
        echo '</section>';

        if (!$deals) {
            echo '<section class="harmat-sales-panel"><div class="harmat-sales-empty">目前还没有已成交客户。销售跟单阶段改为“已成交”后，会自动进入客户管理。</div></section>';
            return;
        }

        $payment_statuses = $this->payment_status_options();
        $contract_options = $this->contract_status_options();
        $commission_statuses = $this->commission_status_options();

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>成交客户总览</h2><p>一行一个客户。点击“客户档案”查看房号、付款、合同、经纪人和佣金明细。</p></div><a href="' . esc_url($this->sales_portal_url(array('view' => 'deals'))) . '">进入销售跟单</a></div>';
        echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table harmat-sales-customer-table"><thead><tr><th>客户</th><th>房源</th><th>经纪人</th><th>成交金额</th><th>收款进度</th><th>付款状态</th><th>合同状态</th><th>佣金</th><th>成交日期</th><th>操作</th></tr></thead><tbody>';
        foreach ($deals as $deal) {
            $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
            $property_url = !empty($deal['property_id']) ? get_permalink((int) $deal['property_id']) : '';
            $broker = !empty($deal['broker_id']) ? get_userdata((int) $deal['broker_id']) : null;
            $payment_status = $this->infer_payment_status($deal['amount'] ?? '', $deal['payment_received'] ?? '', $deal['payment_due_date'] ?? '', $deal['payment_status'] ?? '');
            $contract_status = $deal['contract_status'] ?? '';
            $commission_status = $deal['commission_status'] ?: 'scheduled';
            echo '<tr>';
            echo '<td><strong>' . esc_html($deal['client_name'] ?: '未填写客户') . '</strong><small>' . esc_html($deal['phone'] ?: ($deal['email'] ?: '-')) . '</small></td>';
            echo '<td><strong>' . ($property_url ? '<a href="' . esc_url($property_url) . '" target="_blank" rel="noopener">' . esc_html($property_title ?: '-') . '</a>' : esc_html($property_title ?: '-')) . '</strong><small>' . esc_html(!empty($deal['deposit']) ? '定金 ' . $this->format_money($deal['deposit']) . ' Ft' : '定金未填') . '</small></td>';
            echo '<td>' . esc_html($broker ? $broker->display_name : '-') . '</td>';
            echo '<td><strong>' . esc_html(!empty($deal['amount']) ? $this->format_money($deal['amount']) . ' Ft' : '-') . '</strong></td>';
            echo '<td><strong>已收 ' . esc_html(!empty($deal['payment_received']) ? $this->format_money($deal['payment_received']) . ' Ft' : '0 Ft') . '</strong><small>未收 ' . esc_html($this->format_money($this->deal_payment_balance($deal)) . ' Ft') . '</small></td>';
            echo '<td><span class="harmat-sales-pill harmat-sales-payment-' . esc_attr($payment_status) . '">' . esc_html($payment_statuses[$payment_status] ?? '-') . '</span><small>' . esc_html($deal['payment_due_date'] ? '截止 ' . $deal['payment_due_date'] : '无截止日') . '</small></td>';
            echo '<td><span class="harmat-sales-pill">' . esc_html($contract_status && isset($contract_options[$contract_status]) ? $contract_options[$contract_status] : '未设置') . '</span></td>';
            echo '<td><strong>' . esc_html($this->deal_commission_amount($deal) ? $this->format_money($this->deal_commission_amount($deal)) . ' Ft' : '-') . '</strong><small>' . esc_html(($deal['commission_rate'] ? $deal['commission_rate'] . '%' : '比例未填') . ' / ' . ($commission_statuses[$commission_status] ?? $commission_status)) . '</small></td>';
            echo '<td>' . esc_html($deal['closed_at'] ?: ($deal['expected_close'] ?: '-')) . '</td>';
            echo '<td class="harmat-sales-actions"><a href="' . esc_url($this->sales_portal_url(array('view' => 'customers', 'customer_id' => (int) $deal['id']))) . '">客户档案</a><a href="' . esc_url($this->sales_portal_url(array('view' => 'deals', 'edit_deal' => (int) $deal['id']))) . '">编辑跟单</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function render_sales_customer_profile($deal, $deals) {
        $amount = (int) ($deal['amount'] ?? 0);
        $received = (int) ($deal['payment_received'] ?? 0);
        $balance = $this->deal_payment_balance($deal);
        $commission = $this->deal_commission_amount($deal);
        $payment_status = $this->infer_payment_status($deal['amount'] ?? '', $deal['payment_received'] ?? '', $deal['payment_due_date'] ?? '', $deal['payment_status'] ?? '');
        $payment_statuses = $this->payment_status_options();

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>客户档案：' . esc_html($deal['client_name'] ?: '未填写客户') . '</h2><p>这里集中查看成交客户、房源、付款、合同、交付和佣金。</p></div><div class="harmat-sales-head-actions"><a href="' . esc_url($this->sales_portal_url(array('view' => 'customers'))) . '">返回客户列表</a><a href="' . esc_url($this->sales_portal_url(array('view' => 'deals', 'edit_deal' => (int) $deal['id']))) . '">编辑跟单</a></div></div></section>';

        $this->render_sales_customer_account_box($deal);
        $this->render_sales_customer_materials($deal);

        echo '<section class="harmat-sales-kpis harmat-sales-kpis-compact">';
        echo '<article><small>成交金额</small><strong>' . esc_html($amount ? $this->format_money($amount) : '-') . '</strong></article>';
        echo '<article><small>已收金额</small><strong>' . esc_html($this->format_money($received)) . '</strong></article>';
        echo '<article><small>未收金额</small><strong>' . esc_html($this->format_money($balance)) . '</strong></article>';
        echo '<article><small>付款状态</small><strong>' . esc_html($payment_statuses[$payment_status] ?? '-') . '</strong></article>';
        echo '<article><small>佣金金额</small><strong>' . esc_html($commission ? $this->format_money($commission) : '-') . '</strong></article>';
        echo '</section>';

        echo '<section class="harmat-sales-split harmat-sales-customer-profile-grid">';
        echo '<div class="harmat-sales-panel">';
        $this->render_sales_customer_detail($deal);
        echo '</div>';
        echo '<div class="harmat-sales-panel">';
        $this->render_sales_customer_timeline($deal);
        echo '</div></section>';
    }

    private function render_sales_customer_account_box($deal) {
        $deal_id = (int) ($deal['id'] ?? 0);
        $customer_user_id = (int) ($deal['customer_user_id'] ?? 0);
        $customer_user = $customer_user_id ? get_userdata($customer_user_id) : null;
        $portal_url = home_url('/client/');
        $created = null;

        if (isset($_GET['customer_account_created']) || isset($_GET['customer_account_reset'])) {
            $created = get_transient('harmat_customer_account_success_' . get_current_user_id());
            delete_transient('harmat_customer_account_success_' . get_current_user_id());
            if ($created) {
                $message_title = !empty($created['reset']) ? '客户中心临时密码已重置并发送。' : '客户中心账号已生成并发送。';
                echo '<div class="harmat-sales-notice harmat-sales-notice-success"><strong>' . esc_html($message_title) . '</strong><span>客户入口：<code>' . esc_html($created['portal']) . '</code></span><span>用户名：<code>' . esc_html($created['login']) . '</code></span><span>临时密码：<code>' . esc_html($created['password']) . '</code></span><span>客户邮箱：<code>' . esc_html($created['email']) . '</code></span></div>';
            }
        }

        if (isset($_GET['customer_account_error'])) {
            $error = get_transient('harmat_customer_account_error_' . get_current_user_id());
            delete_transient('harmat_customer_account_error_' . get_current_user_id());
            echo '<div class="harmat-sales-notice harmat-sales-notice-error">客户中心账号生成失败：' . esc_html($error ?: '请检查客户邮箱和邮件服务。') . '</div>';
        }

        echo '<section class="harmat-sales-panel harmat-sales-customer-account-box"><div class="harmat-sales-panel-head"><div><h2>客户中心账号</h2><p>生成后会把登录链接、账号和临时密码发送到客户邮箱；客户以后可在这里查看房屋状态、资料和项目进展照片。</p></div></div>';
        echo '<div class="harmat-sales-rule-list">';
        echo '<span><strong>客户入口</strong><b><a href="' . esc_url($portal_url) . '" target="_blank" rel="noopener">' . esc_html($portal_url) . '</a></b></span>';
        echo '<span><strong>客户邮箱</strong><b>' . esc_html($deal['email'] ?: '未填写') . '</b></span>';
        if ($customer_user) {
            echo '<span><strong>账号状态</strong><b>已生成</b></span>';
            echo '<span><strong>用户名</strong><b>' . esc_html($customer_user->user_login) . '</b></span>';
            if (!empty($created['password'])) {
                echo '<span><strong>临时密码</strong><b>' . esc_html($created['password']) . '</b></span>';
            }
            echo '<span><strong>发送时间</strong><b>' . esc_html($deal['customer_account_sent_at'] ?: '-') . '</b></span>';
            echo '<span><strong>操作</strong><b>不可重复生成</b></span>';
        } else {
            echo '<span><strong>账号状态</strong><b>尚未生成</b></span>';
            echo '<span><strong>发送内容</strong><b>客户中心链接 + 账号 + 临时密码</b></span>';
        }
        echo '</div>';

        if ($customer_user) {
            echo '<div class="harmat-sales-form-actions"><button type="button" disabled class="harmat-sales-disabled-button">已生成客户账号</button>';
            echo '<form method="post" class="harmat-sales-inline-form">';
            wp_nonce_field('harmat_sales_action_reset_customer_account_password');
            echo '<input type="hidden" name="harmat_sales_action" value="reset_customer_account_password">';
            echo '<input type="hidden" name="deal_id" value="' . esc_attr($deal_id) . '">';
            echo '<button type="submit" onclick="return confirm(\'确定重置这个客户中心账号的临时密码，并发送到客户邮箱吗？旧密码会立即失效。\')">重置临时密码并发送</button>';
            echo '</form></div>';
        } else {
            echo '<form method="post" class="harmat-sales-form-actions harmat-sales-inline-form">';
            wp_nonce_field('harmat_sales_action_generate_customer_account');
            echo '<input type="hidden" name="harmat_sales_action" value="generate_customer_account">';
            echo '<input type="hidden" name="deal_id" value="' . esc_attr($deal_id) . '">';
            echo '<button>生成账号并发送给客户</button>';
            echo '</form>';
        }
        echo '</section>';
    }

    private function render_sales_customer_materials($deal) {
        $deal_id = (int) ($deal['id'] ?? 0);
        $materials = $this->deal_customer_materials($deal);

        if (isset($_GET['material_uploaded'])) {
            echo '<div class="harmat-sales-notice harmat-sales-notice-success">客户附件已上传，并会显示在客户端材料区。</div>';
        }

        if (isset($_GET['material_deleted'])) {
            echo '<div class="harmat-sales-notice harmat-sales-notice-success">客户附件已删除。</div>';
        }

        if (isset($_GET['material_error'])) {
            $error = get_transient('harmat_customer_material_error_' . get_current_user_id());
            delete_transient('harmat_customer_material_error_' . get_current_user_id());
            echo '<div class="harmat-sales-notice harmat-sales-notice-error">客户附件上传失败：' . esc_html($error ?: '请检查文件类型和大小。') . '</div>';
        }

        echo '<section class="harmat-sales-panel harmat-sales-customer-material-box"><div class="harmat-sales-panel-head"><div><h2>客户材料区</h2><p>由销售人员上传，客户登录客户端后可查看和下载。建议上传合同、付款凭证、交付资料、项目进展照片等。</p></div></div>';
        echo '<form method="post" enctype="multipart/form-data" class="harmat-sales-form harmat-sales-material-form">';
        wp_nonce_field('harmat_sales_action_upload_customer_material');
        echo '<input type="hidden" name="harmat_sales_action" value="upload_customer_material">';
        echo '<input type="hidden" name="deal_id" value="' . esc_attr($deal_id) . '">';
        echo '<label>资料名称<input name="material_title" placeholder="例如：合同扫描件 / 付款确认 / 进展照片"></label>';
        echo '<label>选择附件<input required type="file" name="customer_material" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt,.zip"></label>';
        echo '<label>可见范围<select name="material_visibility"><option value="customer">客户可见</option><option value="internal">仅内部可见</option></select></label>';
        echo '<label class="harmat-sales-form-wide">备注<textarea name="material_note" rows="3" placeholder="给客户看的简短说明，可为空"></textarea></label>';
        echo '<div class="harmat-sales-form-actions"><button>上传到客户材料区</button><span>支持 PDF、图片、Word、Excel、TXT、ZIP，单个文件不超过 25MB。</span></div>';
        echo '</form>';

        echo '<div class="harmat-sales-material-list">';
        if (!$materials) {
            echo '<div class="harmat-sales-empty">目前还没有上传客户附件。</div>';
        } else {
            foreach ($materials as $material) {
                $url = wp_get_attachment_url((int) $material['attachment_id']);
                $uploader = !empty($material['uploaded_by']) ? get_userdata((int) $material['uploaded_by']) : null;
                $visibility = ($material['visibility'] ?? 'customer') === 'internal' ? 'internal' : 'customer';
                echo '<article>';
                echo '<strong>' . esc_html($material['title'] ?: get_the_title((int) $material['attachment_id'])) . '</strong><span class="harmat-sales-material-badge harmat-sales-material-' . esc_attr($visibility) . '">' . esc_html($visibility === 'internal' ? '仅内部' : '客户可见') . '</span>';
                echo '<small>' . esc_html(($material['uploaded_at'] ?: '-') . ' / ' . ($uploader ? $uploader->display_name : '销售人员')) . '</small>';
                if (!empty($material['note'])) {
                    echo '<p>' . nl2br(esc_html($material['note'])) . '</p>';
                }
                echo '<div class="harmat-sales-material-actions">';
                echo $url ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">打开附件</a>' : '<span>文件不可用</span>';
                echo '<form method="post">';
                wp_nonce_field('harmat_sales_action_delete_customer_material');
                echo '<input type="hidden" name="harmat_sales_action" value="delete_customer_material">';
                echo '<input type="hidden" name="deal_id" value="' . esc_attr($deal_id) . '">';
                echo '<input type="hidden" name="attachment_id" value="' . esc_attr((int) $material['attachment_id']) . '">';
                echo '<button type="submit" onclick="return confirm(\'确定删除这个客户附件吗？删除后客户端也不会再显示。\')">删除</button>';
                echo '</form></div>';
                echo '</article>';
            }
        }
        echo '</div></section>';
    }

    private function render_sales_customer_detail($deal) {
        if (!$deal) {
            echo '<div class="harmat-sales-empty">请选择一个成交客户。</div>';
            return;
        }

        $payment_options = $this->payment_method_options();
        $contract_options = $this->contract_status_options();
        $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
        $property_url = !empty($deal['property_id']) ? get_permalink((int) $deal['property_id']) : '';
        $broker = !empty($deal['broker_id']) ? get_userdata((int) $deal['broker_id']) : null;
        $lead = !empty($deal['lead_id']) ? ($this->get_leads()[(int) $deal['lead_id']] ?? null) : null;
        $inquiry = !empty($deal['inquiry_id']) ? $this->offer_inquiry_data((int) $deal['inquiry_id']) : null;

        echo '<div class="harmat-sales-panel-head"><div><h2>档案资料</h2><p>客户、房源和来源信息。</p></div></div>';
        echo '<div class="harmat-sales-customer-detail">';
        echo '<section><h3>客户信息</h3><dl>';
        echo '<div><dt>客户姓名</dt><dd>' . esc_html($deal['client_name'] ?: '-') . '</dd></div>';
        echo '<div><dt>电话</dt><dd>' . esc_html($deal['phone'] ?: '-') . '</dd></div>';
        echo '<div><dt>邮箱</dt><dd>' . esc_html($deal['email'] ?: '-') . '</dd></div>';
        echo '<div><dt>负责经纪人</dt><dd>' . esc_html($broker ? $broker->display_name : '-') . '</dd></div>';
        echo '</dl></section>';

        echo '<section><h3>成交房源</h3><dl>';
        echo '<div><dt>房源</dt><dd>' . ($property_url ? '<a href="' . esc_url($property_url) . '" target="_blank" rel="noopener">' . esc_html($property_title) . '</a>' : esc_html($property_title ?: '-')) . '</dd></div>';
        echo '<div><dt>成交金额</dt><dd>' . esc_html(!empty($deal['amount']) ? $this->format_money($deal['amount']) . ' Ft' : '-') . '</dd></div>';
        echo '<div><dt>定金</dt><dd>' . esc_html(!empty($deal['deposit']) ? $this->format_money($deal['deposit']) . ' Ft' : '-') . '</dd></div>';
        echo '<div><dt>已收金额</dt><dd>' . esc_html(!empty($deal['payment_received']) ? $this->format_money($deal['payment_received']) . ' Ft' : '0 Ft') . '</dd></div>';
        echo '<div><dt>未收金额</dt><dd>' . esc_html($this->format_money($this->deal_payment_balance($deal)) . ' Ft') . '</dd></div>';
        echo '<div><dt>预计/成交日期</dt><dd>' . esc_html($deal['expected_close'] ?: '-') . '</dd></div>';
        echo '</dl></section>';

        echo '<section><h3>付款与合同</h3><dl>';
        $payment_statuses = $this->payment_status_options();
        $payment_status = $this->infer_payment_status($deal['amount'] ?? '', $deal['payment_received'] ?? '', $deal['payment_due_date'] ?? '', $deal['payment_status'] ?? '');
        echo '<div><dt>付款方式</dt><dd>' . esc_html(!empty($deal['payment_method']) && isset($payment_options[$deal['payment_method']]) ? $payment_options[$deal['payment_method']] : '-') . '</dd></div>';
        echo '<div><dt>付款状态</dt><dd>' . esc_html($payment_statuses[$payment_status] ?? '-') . '</dd></div>';
        echo '<div><dt>付款截止日</dt><dd>' . esc_html($deal['payment_due_date'] ?: '-') . '</dd></div>';
        echo '<div><dt>合同状态</dt><dd>' . esc_html(!empty($deal['contract_status']) && isset($contract_options[$deal['contract_status']]) ? $contract_options[$deal['contract_status']] : '-') . '</dd></div>';
        echo '<div class="harmat-sales-detail-wide"><dt>付款节点</dt><dd>' . nl2br(esc_html($deal['payment_schedule'] ?: '-')) . '</dd></div>';
        echo '</dl></section>';

        echo '<section><h3>经纪人佣金</h3><dl>';
        $commission_statuses = $this->commission_status_options();
        $commission_status = $deal['commission_status'] ?: 'scheduled';
        echo '<div><dt>佣金比例</dt><dd>' . esc_html(!empty($deal['commission_rate']) ? $deal['commission_rate'] . '%' : '-') . '</dd></div>';
        echo '<div><dt>佣金金额</dt><dd>' . esc_html($this->deal_commission_amount($deal) ? $this->format_money($this->deal_commission_amount($deal)) . ' Ft' : '-') . '</dd></div>';
        echo '<div><dt>预计付款日</dt><dd>' . esc_html($deal['commission_due_date'] ?: '-') . '</dd></div>';
        echo '<div><dt>结算状态</dt><dd>' . esc_html($commission_statuses[$commission_status] ?? '-') . '</dd></div>';
        echo '<div class="harmat-sales-detail-wide"><dt>佣金备注</dt><dd>' . nl2br(esc_html($deal['commission_note'] ?: '-')) . '</dd></div>';
        echo '</dl></section>';

        echo '<section><h3>来源与备注</h3><dl>';
        echo '<div><dt>来源客户</dt><dd>' . esc_html($lead ? ($lead['client_name'] . ' #' . $lead['id']) : '-') . '</dd></div>';
        echo '<div><dt>来源询价</dt><dd>' . esc_html($inquiry ? (($inquiry['name'] ?: '-') . ' / ' . ($inquiry['apartment'] ?: '-')) : '-') . '</dd></div>';
        echo '<div class="harmat-sales-detail-wide"><dt>销售备注</dt><dd>' . nl2br(esc_html($deal['note'] ?: '-')) . '</dd></div>';
        echo '<div class="harmat-sales-detail-wide"><dt>交付/售后备注</dt><dd>' . nl2br(esc_html($deal['handover_note'] ?: '-')) . '</dd></div>';
        echo '</dl></section>';
        echo '</div>';
    }

    private function render_sales_customer_timeline($deal) {
        $payment_statuses = $this->payment_status_options();
        $contract_options = $this->contract_status_options();
        $commission_statuses = $this->commission_status_options();
        $payment_status = $this->infer_payment_status($deal['amount'] ?? '', $deal['payment_received'] ?? '', $deal['payment_due_date'] ?? '', $deal['payment_status'] ?? '');
        $contract_status = $deal['contract_status'] ?? '';
        $commission_status = $deal['commission_status'] ?: 'scheduled';

        echo '<div class="harmat-sales-panel-head"><div><h2>执行状态</h2><p>销售、付款、合同、佣金和交付节点。</p></div></div>';
        echo '<div class="harmat-sales-customer-flow">';
        echo '<article><small>成交日期</small><strong>' . esc_html($deal['closed_at'] ?: ($deal['expected_close'] ?: '-')) . '</strong></article>';
        echo '<article><small>付款截止日</small><strong>' . esc_html($deal['payment_due_date'] ?: '-') . '</strong><span class="harmat-sales-pill harmat-sales-payment-' . esc_attr($payment_status) . '">' . esc_html($payment_statuses[$payment_status] ?? '-') . '</span></article>';
        echo '<article><small>合同状态</small><strong>' . esc_html($contract_status && isset($contract_options[$contract_status]) ? $contract_options[$contract_status] : '未设置') . '</strong></article>';
        echo '<article><small>佣金付款日</small><strong>' . esc_html($deal['commission_due_date'] ?: '-') . '</strong><span class="harmat-sales-pill harmat-sales-commission-' . esc_attr($commission_status) . '">' . esc_html($commission_statuses[$commission_status] ?? $commission_status) . '</span></article>';
        echo '<article><small>下一步动作</small><strong>' . esc_html($deal['next_step'] ?: '-') . '</strong></article>';
        echo '<article><small>下次跟进</small><strong>' . esc_html($deal['next_followup'] ?: '-') . '</strong></article>';
        echo '</div>';

        echo '<div class="harmat-sales-customer-ledger">';
        echo '<h3>付款账目</h3>';
        echo '<dl>';
        echo '<div><dt>成交金额</dt><dd>' . esc_html(!empty($deal['amount']) ? $this->format_money($deal['amount']) . ' Ft' : '-') . '</dd></div>';
        echo '<div><dt>定金</dt><dd>' . esc_html(!empty($deal['deposit']) ? $this->format_money($deal['deposit']) . ' Ft' : '-') . '</dd></div>';
        echo '<div><dt>已收金额</dt><dd>' . esc_html(!empty($deal['payment_received']) ? $this->format_money($deal['payment_received']) . ' Ft' : '0 Ft') . '</dd></div>';
        echo '<div><dt>未收金额</dt><dd>' . esc_html($this->format_money($this->deal_payment_balance($deal)) . ' Ft') . '</dd></div>';
        echo '<div class="harmat-sales-detail-wide"><dt>付款节点</dt><dd>' . nl2br(esc_html($deal['payment_schedule'] ?: '-')) . '</dd></div>';
        echo '<div class="harmat-sales-detail-wide"><dt>交付/售后</dt><dd>' . nl2br(esc_html($deal['handover_note'] ?: '-')) . '</dd></div>';
        echo '</dl></div>';
    }

    private function customer_portal_text($lang) {
        $texts = array(
            'hu' => array(
                'title' => 'Harmat Lakópark ügyfélközpont',
                'heading' => 'Ügyfélközpont',
                'intro' => 'Lakása státusza, fizetési és átadási információi egy helyen.',
                'logout' => 'Kilépés',
                'no_deal_title' => 'Nincs hozzárendelt lakás',
                'no_deal_intro' => 'Ehhez az ügyfélfiókhoz még nincs lakás hozzárendelve. Kérjük, vegye fel a kapcsolatot az értékesítéssel.',
                'kpi_apartment' => 'Lakás',
                'kpi_price' => 'Vételár',
                'kpi_payment' => 'Fizetési státusz',
                'kpi_contract' => 'Szerződés',
                'apartment_details' => 'Lakás adatai',
                'buyer' => 'Vevő',
                'deposit' => 'Foglaló',
                'date' => 'Várható/lezárt dátum',
                'payment_contract' => 'Fizetés és szerződés',
                'paid' => 'Befizetve',
                'balance' => 'Hátralék',
                'due_date' => 'Fizetési határidő',
                'schedule' => 'Fizetési ütemezés',
                'progress_title' => 'Projekt előrehaladása és dokumentumok',
                'progress_intro' => 'A projekt státuszát, az átadással kapcsolatos információkat és a friss előrehaladási fotókat ezen a felületen tesszük közzé.',
                'handover' => 'Átadás / ügyintézés',
                'handover_empty' => 'Az átadással kapcsolatos információk hamarosan elérhetők lesznek.',
                'sales_note' => 'Értékesítési megjegyzés',
                'materials_title' => 'Ügyfélanyagok',
                'materials_intro' => 'Itt találja az értékesítés által feltöltött dokumentumokat, visszaigazolásokat és projekt előrehaladási anyagokat.',
                'materials_empty' => 'Jelenleg még nincs feltöltött dokumentum.',
                'status' => 'Státusz',
                'open_file' => 'Megnyitás / letöltés',
                'file_missing' => 'Fájl nem elérhető',
                'payment_statuses' => array(
                    'not_started' => 'Még nem indult',
                    'partial' => 'Részben fizetve',
                    'paid' => 'Kifizetve',
                    'overdue' => 'Lejárt fizetés',
                ),
                'contract_statuses' => array(
                    'draft' => 'Szerződéstervezet',
                    'review' => 'Ügyfél/jogász ellenőrzi',
                    'signed' => 'Aláírva',
                    'paid_deposit' => 'Foglaló befizetve',
                    'handover_ready' => 'Átadásra kész',
                    'handover_done' => 'Átadva',
                ),
            ),
            'en' => array(
                'title' => 'Harmat Lakópark client portal',
                'heading' => 'Client portal',
                'intro' => 'Your apartment status, payment and handover information in one place.',
                'logout' => 'Log out',
                'no_deal_title' => 'No apartment assigned',
                'no_deal_intro' => 'No apartment has been assigned to this client account yet. Please contact sales.',
                'kpi_apartment' => 'Apartment',
                'kpi_price' => 'Purchase price',
                'kpi_payment' => 'Payment status',
                'kpi_contract' => 'Contract',
                'apartment_details' => 'Apartment details',
                'buyer' => 'Buyer',
                'deposit' => 'Deposit',
                'date' => 'Expected/closed date',
                'payment_contract' => 'Payment and contract',
                'paid' => 'Paid',
                'balance' => 'Balance',
                'due_date' => 'Payment due date',
                'schedule' => 'Payment schedule',
                'progress_title' => 'Project progress and documents',
                'progress_intro' => 'Project status, handover information and fresh progress photos will be published here.',
                'handover' => 'Handover / administration',
                'handover_empty' => 'Handover information will be available soon.',
                'sales_note' => 'Sales note',
                'materials_title' => 'Client materials',
                'materials_intro' => 'Documents, confirmations and project progress materials uploaded by the sales team are available here.',
                'materials_empty' => 'There are no uploaded documents yet.',
                'status' => 'Status',
                'open_file' => 'Open / download',
                'file_missing' => 'File not available',
                'payment_statuses' => array(
                    'not_started' => 'Not started',
                    'partial' => 'Partially paid',
                    'paid' => 'Paid',
                    'overdue' => 'Overdue',
                ),
                'contract_statuses' => array(
                    'draft' => 'Contract draft',
                    'review' => 'Under client/legal review',
                    'signed' => 'Signed',
                    'paid_deposit' => 'Deposit paid',
                    'handover_ready' => 'Ready for handover',
                    'handover_done' => 'Handed over',
                ),
            ),
            'zh' => array(
                'title' => 'Harmat Lakópark 客户中心',
                'heading' => '客户中心',
                'intro' => '房屋状态、付款和交付信息集中查看。',
                'logout' => '退出',
                'no_deal_title' => '尚未绑定房源',
                'no_deal_intro' => '这个客户账号还没有绑定房源，请联系销售人员。',
                'kpi_apartment' => '房源',
                'kpi_price' => '成交金额',
                'kpi_payment' => '付款状态',
                'kpi_contract' => '合同状态',
                'apartment_details' => '房屋信息',
                'buyer' => '买方',
                'deposit' => '定金',
                'date' => '预计/成交日期',
                'payment_contract' => '付款与合同',
                'paid' => '已付款',
                'balance' => '未收金额',
                'due_date' => '付款截止日',
                'schedule' => '付款节点',
                'progress_title' => '项目进展与资料',
                'progress_intro' => '这里会发布项目状态、交付信息和最新工程进度照片。',
                'handover' => '交付 / 办理',
                'handover_empty' => '交付相关信息会在这里更新。',
                'sales_note' => '销售备注',
                'materials_title' => '客户材料区',
                'materials_intro' => '销售人员上传的文件、确认函和项目进展资料会显示在这里。',
                'materials_empty' => '目前还没有上传的客户附件。',
                'status' => '状态',
                'open_file' => '打开 / 下载',
                'file_missing' => '文件不可用',
                'payment_statuses' => array(
                    'not_started' => '未开始收款',
                    'partial' => '部分已收',
                    'paid' => '已收齐',
                    'overdue' => '逾期未收',
                ),
                'contract_statuses' => array(
                    'draft' => '合同草案',
                    'review' => '客户/律师审核中',
                    'signed' => '已签约',
                    'paid_deposit' => '已付定金',
                    'handover_ready' => '可交付',
                    'handover_done' => '已交付',
                ),
            ),
        );

        return $texts[$lang] ?? $texts['hu'];
    }

    private function render_customer_portal($deal) {
        $user = wp_get_current_user();
        $lang = $this->current_portal_language('client');
        $text = $this->customer_portal_text($lang);
        nocache_headers();
        echo '<!doctype html><html lang="' . esc_attr($this->portal_html_lang($lang)) . '"><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="robots" content="noindex,nofollow"><title>' . esc_html($text['title']) . '</title><style>' . $this->customer_portal_css() . '</style></head>';
        echo '<body class="harmat-customer-body"><main class="harmat-customer-shell">';
        echo '<header class="harmat-customer-hero"><div><p class="harmat-customer-eyebrow">Harmat Lakópark</p><h1>' . esc_html($text['heading']) . '</h1><p>' . esc_html($text['intro']) . '</p></div><div class="harmat-customer-user">' . $this->portal_logged_language_switch('client', $lang) . '<span>' . esc_html($user->display_name ?: $user->user_login) . '</span><a href="' . esc_url(wp_logout_url($this->portal_url_with_lang('client', $lang))) . '">' . esc_html($text['logout']) . '</a></div></header>';

        if (!$deal) {
            echo '<section class="harmat-customer-panel"><h2>' . esc_html($text['no_deal_title']) . '</h2><p>' . esc_html($text['no_deal_intro']) . '</p></section>';
            echo '</main></body></html>';
            return;
        }

        $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
        $property_url = !empty($deal['property_id']) ? get_permalink((int) $deal['property_id']) : '';
        $payment_statuses = $this->payment_status_options();
        $contract_options = $this->contract_status_options();
        $payment_status = $this->infer_payment_status($deal['amount'] ?? '', $deal['payment_received'] ?? '', $deal['payment_due_date'] ?? '', $deal['payment_status'] ?? '');
        $contract_status = $deal['contract_status'] ?? '';
        $payment_label = $text['payment_statuses'][$payment_status] ?? ($payment_statuses[$payment_status] ?? '-');
        $contract_label = $contract_status && isset($text['contract_statuses'][$contract_status]) ? $text['contract_statuses'][$contract_status] : ($contract_status && isset($contract_options[$contract_status]) ? $contract_options[$contract_status] : '-');

        echo '<section class="harmat-customer-kpis">';
        echo '<article><small>' . esc_html($text['kpi_apartment']) . '</small><strong>' . esc_html($property_title ?: '-') . '</strong></article>';
        echo '<article><small>' . esc_html($text['kpi_price']) . '</small><strong>' . esc_html(!empty($deal['amount']) ? $this->format_money($deal['amount']) . ' Ft' : '-') . '</strong></article>';
        echo '<article><small>' . esc_html($text['kpi_payment']) . '</small><strong>' . esc_html($payment_label) . '</strong></article>';
        echo '<article><small>' . esc_html($text['kpi_contract']) . '</small><strong>' . esc_html($contract_label) . '</strong></article>';
        echo '</section>';

        echo '<section class="harmat-customer-grid">';
        echo '<div class="harmat-customer-panel"><h2>' . esc_html($text['apartment_details']) . '</h2><dl>';
        echo '<div><dt>' . esc_html($text['kpi_apartment']) . '</dt><dd>' . ($property_url ? '<a href="' . esc_url($property_url) . '" target="_blank" rel="noopener">' . esc_html($property_title ?: '-') . '</a>' : esc_html($property_title ?: '-')) . '</dd></div>';
        echo '<div><dt>' . esc_html($text['buyer']) . '</dt><dd>' . esc_html($deal['client_name'] ?: '-') . '</dd></div>';
        echo '<div><dt>' . esc_html($text['deposit']) . '</dt><dd>' . esc_html(!empty($deal['deposit']) ? $this->format_money($deal['deposit']) . ' Ft' : '-') . '</dd></div>';
        echo '<div><dt>' . esc_html($text['date']) . '</dt><dd>' . esc_html($deal['closed_at'] ?: ($deal['expected_close'] ?: '-')) . '</dd></div>';
        echo '</dl></div>';

        echo '<div class="harmat-customer-panel"><h2>' . esc_html($text['payment_contract']) . '</h2><dl>';
        echo '<div><dt>' . esc_html($text['paid']) . '</dt><dd>' . esc_html(!empty($deal['payment_received']) ? $this->format_money($deal['payment_received']) . ' Ft' : '0 Ft') . '</dd></div>';
        echo '<div><dt>' . esc_html($text['balance']) . '</dt><dd>' . esc_html($this->format_money($this->deal_payment_balance($deal)) . ' Ft') . '</dd></div>';
        echo '<div><dt>' . esc_html($text['due_date']) . '</dt><dd>' . esc_html($deal['payment_due_date'] ?: '-') . '</dd></div>';
        echo '<div><dt>' . esc_html($text['schedule']) . '</dt><dd>' . nl2br(esc_html($deal['payment_schedule'] ?: '-')) . '</dd></div>';
        echo '</dl></div>';
        echo '</section>';

        echo '<section class="harmat-customer-panel"><h2>' . esc_html($text['progress_title']) . '</h2><p>' . esc_html($text['progress_intro']) . '</p><dl>';
        echo '<div><dt>' . esc_html($text['handover']) . '</dt><dd>' . nl2br(esc_html($deal['handover_note'] ?: $text['handover_empty'])) . '</dd></div>';
        echo '<div><dt>' . esc_html($text['sales_note']) . '</dt><dd>' . nl2br(esc_html($deal['note'] ?: '-')) . '</dd></div>';
        echo '</dl></section>';

        $this->render_customer_materials($deal, $text);
        echo '</main></body></html>';
    }

    private function render_customer_materials($deal, $text = null) {
        $text = $text ?: $this->customer_portal_text($this->current_portal_language('client'));
        $materials = $this->deal_customer_materials($deal);
        echo '<section class="harmat-customer-panel harmat-customer-materials"><h2>' . esc_html($text['materials_title']) . '</h2><p>' . esc_html($text['materials_intro']) . '</p>';
        if (!$materials) {
            echo '<div><dt>' . esc_html($text['status']) . '</dt><dd>' . esc_html($text['materials_empty']) . '</dd></div>';
            echo '</section>';
            return;
        }

        echo '<div class="harmat-customer-material-list">';
        foreach ($materials as $material) {
            $url = wp_get_attachment_url((int) $material['attachment_id']);
            echo '<article>';
            echo '<strong>' . esc_html($material['title'] ?: get_the_title((int) $material['attachment_id'])) . '</strong>';
            echo '<small>' . esc_html($material['uploaded_at'] ?: '-') . '</small>';
            if (!empty($material['note'])) {
                echo '<p>' . nl2br(esc_html($material['note'])) . '</p>';
            }
            echo $url ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($text['open_file']) . '</a>' : '<span>' . esc_html($text['file_missing']) . '</span>';
            echo '</article>';
        }
        echo '</div></section>';
    }

    private function render_sales_portal_brokers() {
        $users = get_users(array(
            'role__in' => array(self::ROLE_MANAGER, self::ROLE_BROKER),
            'orderby' => 'registered',
            'order' => 'DESC',
        ));
        $leads = $this->get_leads();
        $lead_counts = array();
        foreach ($leads as $lead) {
            $broker_id = (int) ($lead['broker_id'] ?? 0);
            if (!$broker_id) {
                continue;
            }
            $lead_counts[$broker_id] = ($lead_counts[$broker_id] ?? 0) + 1;
        }

        $broker_id = isset($_GET['broker_id']) ? absint($_GET['broker_id']) : 0;
        $selected_user = $broker_id ? get_userdata($broker_id) : null;
        if ($selected_user && array_intersect(array(self::ROLE_MANAGER, self::ROLE_BROKER), (array) $selected_user->roles)) {
            $this->render_sales_portal_broker_detail($selected_user, $lead_counts[$broker_id] ?? 0);
            return;
        }

        echo '<section class="harmat-sales-split harmat-sales-broker-workspace">';
        echo '<div class="harmat-sales-panel">';
        echo '<div class="harmat-sales-panel-head"><div><h2>创建经纪人账号</h2><p>创建后会显示一次性密码，请立即保存给对应经纪人。</p></div></div>';
        echo '<form method="post" class="harmat-sales-form">';
        wp_nonce_field('harmat_sales_action_create_user');
        echo '<input type="hidden" name="harmat_sales_action" value="create_user">';
        echo '<input type="hidden" name="return_to" value="sales_brokers">';
        echo '<label>用户名<input required name="user_login" placeholder="agent01" pattern="[A-Za-z0-9_-]+" title="只能使用英文字母、数字、下划线或短横线"></label>';
        echo '<label>显示名称<input name="display_name" placeholder="经纪人姓名"></label>';
        echo '<label>邮箱<input required type="email" name="user_email" placeholder="name@company.hu"></label>';
        echo '<label>电话<input name="user_phone" placeholder="+36 30 ..."></label>';
        echo '<label>默认佣金比例 %<input name="user_commission_rate" inputmode="decimal" placeholder="例如 1.5"></label>';
        echo '<label>角色<select name="new_role">';
        echo '<option value="' . esc_attr(self::ROLE_BROKER) . '">经纪人查看</option>';
        if (current_user_can('manage_options')) {
            echo '<option value="' . esc_attr(self::ROLE_MANAGER) . '">销售管理</option>';
        }
        echo '</select></label>';
        echo '<div class="harmat-sales-form-actions"><button>生成账号和密码</button></div>';
        echo '</form></div>';

        echo '<div class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>账号说明</h2><p>销售管理可维护经纪人资料、默认佣金比例和账号入口；经纪人只维护自己的客户。</p></div></div>';
        echo '<div class="harmat-sales-rule-list"><span><strong>销售入口</strong><b>/sales/</b></span><span><strong>经纪人入口</strong><b>/agent/</b></span><span><strong>客户保护</strong><b>30天</b></span><span><strong>默认佣金</strong><b>跟单未填时自动带入</b></span></div></div>';
        echo '</section>';

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>内部账号</h2><p>这里只显示注册资料。点击“管理”进入经纪人详情，查看成交房号、买房人、成交金额和佣金状态。</p></div></div>';
        echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table harmat-sales-broker-table"><thead><tr><th>用户名</th><th>姓名</th><th>电话</th><th>邮箱</th><th>角色</th><th>客户数</th><th>创建时间</th><th>登录入口</th><th>操作</th></tr></thead><tbody>';
        foreach ($users as $user) {
            $role = in_array(self::ROLE_MANAGER, (array) $user->roles, true) ? self::ROLE_MANAGER : self::ROLE_BROKER;
            $login_url = $role === self::ROLE_MANAGER ? home_url('/sales/') : home_url('/agent/');
            echo '<tr>';
            echo '<td><strong>' . esc_html($user->user_login) . '</strong></td>';
            echo '<td>' . esc_html($user->display_name ?: '-') . '</td>';
            echo '<td>' . esc_html($this->broker_phone((int) $user->ID) ?: '-') . '</td>';
            echo '<td>' . esc_html($user->user_email ?: '-') . '</td>';
            echo '<td><span class="harmat-sales-pill">' . esc_html($this->role_label($role)) . '</span></td>';
            echo '<td>' . esc_html((string) ($lead_counts[$user->ID] ?? 0)) . '</td>';
            echo '<td>' . esc_html($this->format_lead_datetime($user->user_registered)) . '</td>';
            echo '<td><a href="' . esc_url($login_url) . '" target="_blank" rel="noopener">' . esc_html($login_url) . '</a></td>';
            echo '<td class="harmat-sales-actions">';
            echo '<a href="' . esc_url($this->sales_portal_url(array('view' => 'brokers', 'broker_id' => (int) $user->ID))) . '">管理</a>';
            if ($role !== self::ROLE_MANAGER || current_user_can('manage_options')) {
                echo '<form method="post">';
                wp_nonce_field('harmat_sales_action_reset_password');
                echo '<input type="hidden" name="harmat_sales_action" value="reset_password">';
                echo '<input type="hidden" name="return_to" value="sales_brokers">';
                echo '<input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '">';
                echo '<button onclick="return confirm(\'确定要重置这个账号的密码吗？旧密码会立即失效。\')">重置密码</button>';
                echo '</form>';
                if ((int) $user->ID !== get_current_user_id()) {
                    echo '<form method="post">';
                    wp_nonce_field('harmat_sales_action_delete_user');
                    echo '<input type="hidden" name="harmat_sales_action" value="delete_user">';
                    echo '<input type="hidden" name="return_to" value="sales_brokers">';
                    echo '<input type="hidden" name="user_id" value="' . esc_attr($user->ID) . '">';
                    echo '<button onclick="return confirm(\'确定删除这个账号吗？删除后该账号不能再登录。\')">删除</button>';
                    echo '</form>';
                }
            } else {
                echo '-';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function render_sales_portal_broker_detail($user, $lead_count) {
        $user_id = (int) $user->ID;
        $role = in_array(self::ROLE_MANAGER, (array) $user->roles, true) ? self::ROLE_MANAGER : self::ROLE_BROKER;
        $login_url = $role === self::ROLE_MANAGER ? home_url('/sales/') : home_url('/agent/');
        $deals = $this->broker_commission_deals($user_id);
        $sold_count = count($deals);
        $sold_total = $this->sum_deal_amounts($deals);
        $commission_total = $this->sum_commissions($deals);
        $paid_count = count(array_filter($deals, function($deal) {
            return ($deal['commission_status'] ?? '') === 'paid';
        }));
        $pending_count = max(0, $sold_count - $paid_count);
        $form_id = 'harmat-sales-broker-detail-' . $user_id;

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>经纪人管理：' . esc_html($user->display_name ?: $user->user_login) . '</h2><p>维护经纪人资料，并查看该经纪人的成交房源和佣金结算。</p></div><a href="' . esc_url($this->sales_portal_url(array('view' => 'brokers'))) . '">返回账号列表</a></div></section>';

        echo '<section class="harmat-sales-kpis harmat-sales-kpis-compact">';
        echo '<article><small>客户数</small><strong>' . (int) $lead_count . '</strong></article>';
        echo '<article><small>成交套数</small><strong>' . (int) $sold_count . '</strong></article>';
        echo '<article><small>成交金额</small><strong>' . esc_html($this->format_money($sold_total)) . '</strong></article>';
        echo '<article><small>佣金总额</small><strong>' . esc_html($this->format_money($commission_total)) . '</strong></article>';
        echo '<article><small>待支付</small><strong>' . (int) $pending_count . '</strong></article>';
        echo '</section>';

        echo '<section class="harmat-sales-split harmat-sales-broker-detail-grid">';
        echo '<div class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>注册资料</h2><p>这里维护姓名、电话、邮箱和默认佣金比例。</p></div></div>';
        echo '<form method="post" class="harmat-sales-form" id="' . esc_attr($form_id) . '">';
        wp_nonce_field('harmat_sales_action_update_user');
        echo '<input type="hidden" name="harmat_sales_action" value="update_user">';
        echo '<input type="hidden" name="return_to" value="sales_broker_detail">';
        echo '<input type="hidden" name="user_id" value="' . esc_attr($user_id) . '">';
        echo '<label>用户名<input value="' . esc_attr($user->user_login) . '" readonly></label>';
        echo '<label>姓名<input name="display_name" value="' . esc_attr($user->display_name ?: '') . '" placeholder="姓名"></label>';
        echo '<label>电话<input name="user_phone" value="' . esc_attr($this->broker_phone($user_id)) . '" placeholder="+36 30 ..."></label>';
        echo '<label>邮箱<input type="email" name="user_email" value="' . esc_attr($user->user_email ?: '') . '" placeholder="name@email.com"></label>';
        echo '<label>默认佣金比例 %<input name="user_commission_rate" value="' . esc_attr($this->broker_commission_rate($user_id)) . '" inputmode="decimal" placeholder="例如 1.5"></label>';
        echo '<label>角色<input value="' . esc_attr($this->role_label($role)) . '" readonly></label>';
        echo '<div class="harmat-sales-form-actions"><button>保存经纪人资料</button></div>';
        echo '</form></div>';

        echo '<div class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>入口与规则</h2><p>经纪人入口和佣金口径。</p></div></div>';
        echo '<div class="harmat-sales-rule-list"><span><strong>登录入口</strong><b><a href="' . esc_url($login_url) . '" target="_blank" rel="noopener">' . esc_html($login_url) . '</a></b></span><span><strong>默认佣金</strong><b>' . esc_html($this->broker_commission_rate($user_id) ?: '-') . '%</b></span><span><strong>付款规则</strong><b>成交后 30 天</b></span></div></div>';
        echo '</section>';

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>成交与佣金明细</h2><p>只显示销售跟单中已成交并绑定给该经纪人的记录。</p></div></div>';
        if (!$deals) {
            echo '<div class="harmat-sales-empty">这个经纪人目前没有成交记录。</div></section>';
            return;
        }

        $statuses = $this->commission_status_options();
        echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table harmat-sales-broker-detail-table"><thead><tr><th>成交日期</th><th>房号</th><th>买房人</th><th>成交金额</th><th>佣金比例</th><th>佣金金额</th><th>预计付款日</th><th>是否已付</th><th>备注</th><th>操作</th></tr></thead><tbody>';
        foreach ($deals as $deal) {
            $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
            $status = $deal['commission_status'] ?: 'scheduled';
            echo '<tr>';
            echo '<td><strong>' . esc_html($deal['closed_at'] ?: ($deal['expected_close'] ?: '-')) . '</strong></td>';
            echo '<td>' . esc_html($property_title ?: '-') . '</td>';
            echo '<td><strong>' . esc_html($deal['client_name'] ?: '-') . '</strong><small>' . esc_html($deal['phone'] ?: ($deal['email'] ?: '-')) . '</small></td>';
            echo '<td>' . esc_html(!empty($deal['amount']) ? $this->format_money($deal['amount']) . ' Ft' : '-') . '</td>';
            echo '<td>' . esc_html(!empty($deal['commission_rate']) ? $deal['commission_rate'] . '%' : '-') . '</td>';
            echo '<td><strong>' . esc_html($this->deal_commission_amount($deal) ? $this->format_money($this->deal_commission_amount($deal)) . ' Ft' : '-') . '</strong></td>';
            echo '<td>' . esc_html($deal['commission_due_date'] ?: '-') . '</td>';
            echo '<td><span class="harmat-sales-pill harmat-sales-commission-' . esc_attr($status) . '">' . esc_html($statuses[$status] ?? $status) . '</span></td>';
            echo '<td class="harmat-sales-note-cell">' . esc_html($deal['commission_note'] ?: '-') . '</td>';
            echo '<td class="harmat-sales-actions"><a href="' . esc_url($this->sales_portal_url(array('view' => 'deals', 'edit_deal' => (int) $deal['id']))) . '">编辑跟单</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function render_sales_portal_properties() {
        $properties = $this->get_properties();
        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>房源库存</h2><p>来自统一房源销售数据，可快速核对状态、面积和价格显示。</p></div><a href="' . esc_url(home_url('/lakaskereso/')) . '" target="_blank" rel="noopener">打开公开房源</a></div>';
        if (!$properties) {
            echo '<div class="harmat-sales-empty">暂无房源。</div></section>';
            return;
        }

        echo '<div class="harmat-sales-table-wrap"><table class="harmat-sales-table harmat-sales-property-table"><thead><tr><th>房号</th><th>状态</th><th>楼栋</th><th>楼层</th><th>房间</th><th>销售面积</th><th>露台/花园</th><th>总价 HUF</th><th>前端价格</th><th>备注</th><th>链接</th><th>操作</th></tr></thead><tbody>';
        foreach ($properties as $property) {
            $this->render_sales_portal_property_row($property);
        }
        echo '</tbody></table></div></section>';
    }

    private function render_sales_portal_links() {
        $links = array(
            array('销售管理独立页', home_url('/sales/'), '日常查看销售数据使用这个入口'),
            array('经纪人入口', home_url('/agent/'), '经纪人登记客户、查看客户保护'),
            array('客户入口', home_url('/client/'), '客户登录入口'),
            array('网站登录页', home_url('/belepes/'), '公开登录页面'),
            array('后台备用入口', home_url('/sales-admin/'), '需要高级维护时再进入后台'),
            array('公开房源搜索', home_url('/lakaskereso/'), '客户看到的房源列表'),
        );

        echo '<section class="harmat-sales-panel"><div class="harmat-sales-panel-head"><div><h2>常用登录入口</h2><p>这些链接可以复制保存，确认不同角色从哪里进入。</p></div></div>';
        echo '<div class="harmat-sales-link-grid">';
        foreach ($links as $link) {
            echo '<a href="' . esc_url($link[1]) . '" target="_blank" rel="noopener"><strong>' . esc_html($link[0]) . '</strong><code>' . esc_html($link[1]) . '</code><span>' . esc_html($link[2]) . '</span></a>';
        }
        echo '</div></section>';
    }

    private function render_sales_portal_inquiry_row($post_id) {
        $data = $this->offer_inquiry_data($post_id);
        echo '<tr>';
        echo '<td><strong>' . esc_html(get_the_date('Y-m-d H:i', $post_id)) . '</strong><small>ID ' . esc_html((string) $post_id) . '</small></td>';
        echo '<td><strong>' . esc_html($data['name'] ?: '未填写') . '</strong><small>' . esc_html($data['email'] ?: '-') . '</small></td>';
        echo '<td><strong>' . esc_html($data['apartment'] ?: '-') . '</strong></td>';
        echo '<td><span>' . esc_html($data['phone'] ?: '-') . '</span><small>' . esc_html($data['email'] ?: '-') . '</small></td>';
        echo '<td><span>' . esc_html(trim($data['area'] . ' · ' . $data['rooms'], ' ·') ?: '-') . '</span><small>' . esc_html($data['price'] ?: '-') . '</small></td>';
        echo '<td>' . esc_html(trim($data['date'] . ' ' . $data['time']) ?: '-') . '</td>';
        echo '<td><span>' . esc_html($data['mail_status'] ?: '-') . '</span><small>' . esc_html($data['checked_at'] ?: '') . '</small></td>';
        echo '<td class="harmat-sales-note-cell">' . esc_html($data['message'] ?: '-') . ($data['property_url'] ? '<small><a href="' . esc_url($data['property_url']) . '" target="_blank" rel="noopener">打开房源</a></small>' : '') . '<small><a href="' . esc_url($this->sales_portal_url(array('view' => 'deals', 'inquiry_id' => $post_id))) . '">生成跟单</a></small></td>';
        echo '</tr>';
    }

    private function render_sales_portal_deal_row($deal) {
        $stage_options = $this->deal_stage_options();
        $payment_options = $this->payment_method_options();
        $payment_statuses = $this->payment_status_options();
        $contract_options = $this->contract_status_options();
        $stage = isset($stage_options[$deal['stage']]) ? $deal['stage'] : 'new';
        $payment_status = $this->infer_payment_status($deal['amount'] ?? '', $deal['payment_received'] ?? '', $deal['payment_due_date'] ?? '', $deal['payment_status'] ?? '');
        $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
        $property_url = !empty($deal['property_id']) ? get_permalink((int) $deal['property_id']) : '';
        $broker = !empty($deal['broker_id']) ? get_userdata((int) $deal['broker_id']) : null;
        $source = array();
        if (!empty($deal['lead_id'])) {
            $source[] = '客户 #' . (int) $deal['lead_id'];
        }
        if (!empty($deal['inquiry_id'])) {
            $source[] = '询价 #' . (int) $deal['inquiry_id'];
        }

        echo '<tr>';
        echo '<td><strong>' . esc_html($deal['client_name'] ?: '未填写') . '</strong><small>' . esc_html($deal['phone'] ?: ($deal['email'] ?: '-')) . '</small></td>';
        echo '<td>';
        if ($property_title && $property_url) {
            echo '<a href="' . esc_url($property_url) . '" target="_blank" rel="noopener">' . esc_html($property_title) . '</a>';
        } else {
            echo '暂未指定';
        }
        echo '</td>';
        echo '<td><span class="harmat-sales-pill harmat-sales-deal-' . esc_attr($stage) . '">' . esc_html($stage_options[$stage]) . '</span></td>';
        echo '<td><span>' . esc_html(!empty($deal['amount']) ? $this->format_money($deal['amount']) . ' Ft' : '-') . '</span><small>定金：' . esc_html(!empty($deal['deposit']) ? $this->format_money($deal['deposit']) . ' Ft' : '-') . '</small></td>';
        $commission_status = $deal['commission_status'] ?: ($stage === 'closed' ? 'scheduled' : 'pending');
        $commission_statuses = $this->commission_status_options();
        echo '<td><span>' . esc_html($this->deal_commission_amount($deal) ? $this->format_money($this->deal_commission_amount($deal)) . ' Ft' : '-') . '</span><small>' . esc_html($deal['commission_due_date'] ?: '-') . ' / ' . esc_html($commission_statuses[$commission_status] ?? $commission_status) . '</small></td>';
        echo '<td><span>' . esc_html(!empty($deal['payment_method']) && isset($payment_options[$deal['payment_method']]) ? $payment_options[$deal['payment_method']] : '-') . '</span><small>' . esc_html($payment_statuses[$payment_status] ?? '-') . ' / ' . esc_html(!empty($deal['contract_status']) && isset($contract_options[$deal['contract_status']]) ? $contract_options[$deal['contract_status']] : '-') . '</small></td>';
        echo '<td><span>' . esc_html($deal['next_step'] ?: '-') . '</span><small>' . esc_html($deal['next_followup'] ?: '-') . '</small></td>';
        echo '<td>' . esc_html($broker ? $broker->display_name : '-') . '</td>';
        echo '<td>' . esc_html($source ? implode(' / ', $source) : '手动录入') . '</td>';
        echo '<td><span>' . esc_html($this->format_lead_datetime($deal['updated_at'] ?? '')) . '</span><small>预计：' . esc_html($deal['expected_close'] ?: '-') . '</small></td>';
        echo '<td class="harmat-sales-actions"><a href="' . esc_url($this->sales_portal_url(array('view' => 'deals', 'edit_deal' => (int) $deal['id']))) . '">编辑</a>';
        if ($stage === 'closed') {
            echo '<a href="' . esc_url($this->sales_portal_url(array('view' => 'customers', 'customer_id' => (int) $deal['id']))) . '">档案</a>';
        }
        echo '<form method="post">';
        wp_nonce_field('harmat_sales_action_delete_deal');
        echo '<input type="hidden" name="harmat_sales_action" value="delete_deal">';
        echo '<input type="hidden" name="return_to" value="sales_deals">';
        echo '<input type="hidden" name="deal_id" value="' . esc_attr($deal['id']) . '">';
        echo '<button onclick="return confirm(\'确定删除这个销售跟单吗？\')">删除</button>';
        echo '</form></td>';
        echo '</tr>';
    }

    private function render_sales_portal_client_row($lead) {
        $status_options = $this->lead_status_options();
        $status = isset($status_options[$lead['status']]) ? $lead['status'] : 'new';
        $broker = !empty($lead['broker_id']) ? get_userdata((int) $lead['broker_id']) : null;
        $days_left = $this->lead_protection_days_left($lead);
        $protection_class = $days_left > 0 ? 'active' : 'expired';
        $property_title = $lead['property_id'] ? get_the_title((int) $lead['property_id']) : '';
        $property_url = $lead['property_id'] ? get_permalink((int) $lead['property_id']) : '';

        echo '<tr>';
        echo '<td><strong>' . esc_html($lead['client_name']) . '</strong><small>' . esc_html($lead['source'] ?: '来源未填写') . '</small></td>';
        echo '<td><span>' . esc_html($lead['phone'] ?: '-') . '</span><small>' . esc_html($lead['email'] ?: '-') . '</small></td>';
        echo '<td>';
        if ($property_title && $property_url) {
            echo '<a href="' . esc_url($property_url) . '" target="_blank" rel="noopener">' . esc_html($property_title) . '</a>';
        } else {
            echo '暂未指定';
        }
        echo '</td>';
        echo '<td><span class="harmat-sales-pill">' . esc_html($status_options[$status]) . '</span></td>';
        echo '<td>' . esc_html($this->format_lead_datetime($lead['created_at'] ?? '')) . '</td>';
        echo '<td>' . esc_html($this->lead_protection_expires_at($lead) ?: '-') . '</td>';
        echo '<td><span class="harmat-sales-protection harmat-sales-protection-' . esc_attr($protection_class) . '">' . esc_html($days_left > 0 ? '剩余 ' . $days_left . ' 天' : '已过期') . '</span></td>';
        echo '<td>' . esc_html($lead['next_followup'] ?: '-') . '</td>';
        echo '<td>' . esc_html($broker ? $broker->display_name : '-') . '</td>';
        echo '<td class="harmat-sales-note-cell">' . esc_html($lead['note'] ?: '-') . '</td>';
        echo '<td class="harmat-sales-actions"><a href="' . esc_url($this->sales_portal_url(array('view' => 'clients', 'edit_lead' => (int) $lead['id']))) . '">编辑</a><a href="' . esc_url($this->sales_portal_url(array('view' => 'deals', 'lead_id' => (int) $lead['id']))) . '">跟单</a>';
        echo '<form method="post">';
        wp_nonce_field('harmat_sales_action_delete_lead');
        echo '<input type="hidden" name="harmat_sales_action" value="delete_lead">';
        echo '<input type="hidden" name="return_to" value="sales_clients">';
        echo '<input type="hidden" name="lead_id" value="' . esc_attr($lead['id']) . '">';
        echo '<button onclick="return confirm(\'确定删除这个客户跟进记录吗？\')">删除</button>';
        echo '</form></td>';
        echo '</tr>';
    }

    private function render_sales_portal_property_row($property) {
        $post_id = (int) $property->ID;
        $status = $this->sales_status($post_id);
        $status_options = $this->status_options();
        $price = (int) get_post_meta($post_id, 'property_price', true);
        $hide_price = get_post_meta($post_id, '_harmat_hide_front_price', true) === 'yes';
        $sales_area = $this->get_sales_area($post_id);
        $terrace = get_post_meta($post_id, 'property_land_area', true);
        $note = get_post_meta($post_id, '_harmat_sales_note', true);
        $form_id = 'harmat-sales-property-' . $post_id;

        echo '<tr>';
        echo '<td><strong>' . esc_html(get_the_title($post_id)) . '</strong><form method="post" id="' . esc_attr($form_id) . '">';
        wp_nonce_field('harmat_sales_action_update_property');
        echo '<input type="hidden" name="harmat_sales_action" value="update_property">';
        echo '<input type="hidden" name="return_to" value="sales_properties">';
        echo '<input type="hidden" name="post_id" value="' . esc_attr($post_id) . '">';
        echo '</form></td>';
        echo '<td><select form="' . esc_attr($form_id) . '" name="sales_status">';
        foreach ($status_options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($status, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td>';
        echo '<td>' . esc_html(get_post_meta($post_id, 'property_address_street', true) ?: '-') . '</td>';
        echo '<td>' . esc_html(get_post_meta($post_id, 'property_address_street_number', true) ?: '-') . '</td>';
        echo '<td>' . esc_html(get_post_meta($post_id, 'property_rooms', true) ?: '-') . '</td>';
        echo '<td>' . esc_html($sales_area ? $this->format_area($sales_area) . ' m²' : '-') . '</td>';
        echo '<td>' . esc_html($terrace ? $this->format_area($terrace) . ' m²' : '-') . '</td>';
        echo '<td><input form="' . esc_attr($form_id) . '" name="property_price" value="' . esc_attr($price) . '" inputmode="numeric"></td>';
        echo '<td><select form="' . esc_attr($form_id) . '" name="price_visibility"><option value="show"' . selected(!$hide_price, true, false) . '>显示价格</option><option value="hide"' . selected($hide_price, true, false) . '>隐藏价格</option></select><small>' . esc_html(($hide_price || !$price) ? 'Ár egyeztetés alapján' : $this->format_money($price) . ' Ft') . '</small></td>';
        echo '<td><input form="' . esc_attr($form_id) . '" name="sales_note" value="' . esc_attr($note) . '" placeholder="内部备注"></td>';
        echo '<td><a href="' . esc_url(get_permalink($post_id)) . '" target="_blank" rel="noopener">打开</a></td>';
        echo '<td class="harmat-sales-actions"><button form="' . esc_attr($form_id) . '">保存</button></td>';
        echo '</tr>';
    }

    private function get_offer_inquiry_posts($limit = 20, $search = '') {
        $args = array(
            'post_type' => 'harmat_offer_lead',
            'post_status' => array('private', 'publish', 'draft'),
            'posts_per_page' => absint($limit),
            'orderby' => 'date',
            'order' => 'DESC',
        );
        if ($search !== '') {
            $args['s'] = $search;
        }

        return get_posts($args);
    }

    private function count_offer_inquiries() {
        $query = new WP_Query(array(
            'post_type' => 'harmat_offer_lead',
            'post_status' => array('private', 'publish', 'draft'),
            'posts_per_page' => 1,
            'fields' => 'ids',
        ));
        return (int) $query->found_posts;
    }

    private function offer_inquiry_data($post_id) {
        $posted = get_post_meta($post_id, '_harmat_offer_posted', true);
        if (!is_array($posted)) {
            $posted = array();
        }

        $name = $this->inquiry_value($posted, 'your-name');
        if ($name === '') {
            $parts = explode(' - ', get_the_title($post_id));
            $name = trim($parts[0]);
            $name = preg_replace('/^Magán:\s*/u', '', $name);
        }

        return array(
            'name' => $name,
            'email' => get_post_meta($post_id, '_harmat_offer_email', true) ?: $this->inquiry_value($posted, 'your-email'),
            'phone' => get_post_meta($post_id, '_harmat_offer_phone', true) ?: $this->inquiry_value($posted, 'your-phone'),
            'apartment' => get_post_meta($post_id, '_harmat_offer_apartment', true) ?: $this->inquiry_value($posted, 'selected-apartment'),
            'date' => get_post_meta($post_id, '_harmat_offer_date', true) ?: $this->inquiry_value($posted, 'your-date'),
            'time' => get_post_meta($post_id, '_harmat_offer_time', true) ?: $this->inquiry_value($posted, 'your-time'),
            'area' => $this->inquiry_value($posted, 'selected-area'),
            'rooms' => $this->inquiry_value($posted, 'selected-rooms'),
            'price' => $this->inquiry_value($posted, 'selected-price'),
            'property_url' => $this->inquiry_value($posted, 'selected-url'),
            'message' => $this->inquiry_value($posted, 'your-message') ?: get_post_field('post_content', $post_id),
            'mail_status' => get_post_meta($post_id, '_harmat_offer_mail_status', true),
            'checked_at' => get_post_meta($post_id, '_harmat_offer_mail_checked_at', true),
        );
    }

    private function render_agent_lead_row($lead) {
        $status_options = $this->lead_status_options();
        $status = isset($status_options[$lead['status']]) ? $lead['status'] : 'new';
        $property_title = $lead['property_id'] ? get_the_title((int) $lead['property_id']) : '';
        $broker = current_user_can(self::CAP_MANAGE) && !empty($lead['broker_id']) ? get_userdata((int) $lead['broker_id']) : null;
        $days_left = $this->lead_protection_days_left($lead);
        $protection_class = $days_left > 0 ? 'active' : 'expired';
        $protection_label = $days_left > 0 ? '剩余 ' . $days_left . ' 天' : '已过期';

        echo '<tr class="harmat-agent-lead-row harmat-agent-lead-' . esc_attr($status) . '">';
        echo '<td><strong>' . esc_html($lead['client_name']) . '</strong><small>' . esc_html($lead['source'] ?: '来源未填写') . '</small></td>';
        echo '<td>' . esc_html($lead['phone'] ?: '-') . '</td>';
        echo '<td>' . esc_html($lead['email'] ?: '-') . '</td>';
        echo '<td>' . esc_html($property_title ?: '暂未指定') . '</td>';
        echo '<td><span class="harmat-agent-status-pill">' . esc_html($status_options[$status]) . '</span></td>';
        echo '<td>' . esc_html($this->format_lead_datetime($lead['created_at'] ?? '')) . '</td>';
        echo '<td><span class="harmat-agent-protection harmat-agent-protection-' . esc_attr($protection_class) . '">' . esc_html($protection_label) . '</span></td>';
        echo '<td>' . esc_html($lead['next_followup'] ?: '-') . '</td>';
        if ($broker) {
            echo '<td>' . esc_html($broker->display_name) . '</td>';
        } elseif (current_user_can(self::CAP_MANAGE)) {
            echo '<td>-</td>';
        }
        echo '<td class="harmat-agent-note-cell">' . esc_html($lead['note'] ?: '-') . '</td>';
        echo '<td class="harmat-agent-actions"><a href="' . esc_url(add_query_arg('edit_lead', (int) $lead['id'], home_url('/agent/'))) . '">编辑</a><form method="post">';
        wp_nonce_field('harmat_sales_action_delete_lead');
        echo '<input type="hidden" name="harmat_sales_action" value="delete_lead">';
        echo '<input type="hidden" name="return_to" value="agent_clients">';
        echo '<input type="hidden" name="lead_id" value="' . esc_attr($lead['id']) . '">';
        echo '<button onclick="return confirm(\'确定删除这个客户记录吗？\')">删除</button>';
        echo '</form></td></tr>';
    }

    private function render_inquiries() {
        $search = isset($_GET['inquiry_s']) ? sanitize_text_field(wp_unslash($_GET['inquiry_s'])) : '';
        $query_args = array(
            'post_type' => 'harmat_offer_lead',
            'post_status' => array('private', 'publish', 'draft'),
            'posts_per_page' => 80,
            'orderby' => 'date',
            'order' => 'DESC',
            's' => $search,
        );
        $query = new WP_Query($query_args);

        echo '<div class="harmat-inquiry-toolbar">';
        echo '<div><h2>网站询价记录</h2><p>这里显示客户从网站表单提交的询价、预约和房源选择信息。只有销售管理账号可以查看。</p></div>';
        echo '<form method="get" class="harmat-inquiry-search">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';
        echo '<input type="hidden" name="tab" value="inquiries">';
        echo '<input type="search" name="inquiry_s" value="' . esc_attr($search) . '" placeholder="搜索客户、邮箱、房号">';
        echo '<button class="button">搜索</button>';
        if ($search !== '') {
            echo '<a class="button" href="' . esc_url($this->page_url(array('tab' => 'inquiries'))) . '">清除</a>';
        }
        echo '</form></div>';

        echo '<div class="harmat-inquiry-summary">';
        echo '<span>当前显示 <b>' . esc_html((string) $query->post_count) . '</b></span>';
        echo '<span>来源 <b>网站表单</b></span>';
        echo '<span>权限 <b>销售管理</b></span>';
        echo '</div>';

        if (!$query->have_posts()) {
            echo '<div class="harmat-empty-state">目前没有找到网站询价记录。</div>';
            wp_reset_postdata();
            return;
        }

        echo '<div class="harmat-inquiry-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            $this->render_inquiry_card(get_the_ID());
        }
        echo '</div>';
        wp_reset_postdata();
    }

    private function render_inquiry_card($post_id) {
        $posted = get_post_meta($post_id, '_harmat_offer_posted', true);
        if (!is_array($posted)) {
            $posted = array();
        }

        $name = $this->inquiry_value($posted, 'your-name');
        if ($name === '') {
            $parts = explode(' - ', get_the_title($post_id));
            $name = trim($parts[0]);
        }

        $email = get_post_meta($post_id, '_harmat_offer_email', true);
        if ($email === '') {
            $email = $this->inquiry_value($posted, 'your-email');
        }

        $phone = get_post_meta($post_id, '_harmat_offer_phone', true);
        if ($phone === '') {
            $phone = $this->inquiry_value($posted, 'your-phone');
        }

        $apartment = get_post_meta($post_id, '_harmat_offer_apartment', true);
        if ($apartment === '') {
            $apartment = $this->inquiry_value($posted, 'selected-apartment');
        }

        $date = get_post_meta($post_id, '_harmat_offer_date', true);
        if ($date === '') {
            $date = $this->inquiry_value($posted, 'your-date');
        }

        $time = get_post_meta($post_id, '_harmat_offer_time', true);
        if ($time === '') {
            $time = $this->inquiry_value($posted, 'your-time');
        }

        $area = $this->inquiry_value($posted, 'selected-area');
        $rooms = $this->inquiry_value($posted, 'selected-rooms');
        $price = $this->inquiry_value($posted, 'selected-price');
        $property_url = $this->inquiry_value($posted, 'selected-url');
        $message = $this->inquiry_value($posted, 'your-message');
        if ($message === '') {
            $message = get_post_field('post_content', $post_id);
        }
        $mail_status = get_post_meta($post_id, '_harmat_offer_mail_status', true);
        $checked_at = get_post_meta($post_id, '_harmat_offer_mail_checked_at', true);

        echo '<article class="harmat-inquiry-card">';
        echo '<header>';
        echo '<div><h3>' . esc_html($name ?: '未填写姓名') . '</h3>';
        echo '<p>' . esc_html(get_the_date('Y-m-d H:i:s', $post_id)) . '</p></div>';
        echo '<span>' . esc_html($apartment ?: '未选择房源') . '</span>';
        echo '</header>';
        echo '<dl class="harmat-inquiry-details">';
        echo '<div><dt>电话</dt><dd>' . esc_html($phone ?: '-') . '</dd></div>';
        echo '<div><dt>邮箱</dt><dd>' . esc_html($email ?: '-') . '</dd></div>';
        echo '<div><dt>看房日期</dt><dd>' . esc_html($date ?: '-') . '</dd></div>';
        echo '<div><dt>时间段</dt><dd>' . esc_html($time ?: '-') . '</dd></div>';
        echo '<div><dt>面积</dt><dd>' . esc_html($area ?: '-') . '</dd></div>';
        echo '<div><dt>房间</dt><dd>' . esc_html($rooms ?: '-') . '</dd></div>';
        echo '<div><dt>价格显示</dt><dd>' . esc_html($price ?: '-') . '</dd></div>';
        echo '<div><dt>邮件状态</dt><dd>' . esc_html($mail_status ?: '-') . ($checked_at ? '<small>' . esc_html($checked_at) . '</small>' : '') . '</dd></div>';
        echo '</dl>';
        if ($message !== '') {
            echo '<div class="harmat-inquiry-message"><strong>客户留言</strong><p>' . nl2br(esc_html($message)) . '</p></div>';
        }
        echo '<footer>';
        if ($property_url !== '') {
            echo '<a class="button" href="' . esc_url($property_url) . '" target="_blank" rel="noopener">打开房源</a>';
        }
        echo '<a class="button" href="' . esc_url(get_edit_post_link($post_id, '')) . '" target="_blank" rel="noopener">后台原始记录</a>';
        echo '</footer>';
        echo '</article>';
    }

    private function inquiry_value($posted, $key) {
        if (!isset($posted[$key])) {
            return '';
        }
        if (is_array($posted[$key])) {
            return implode(', ', array_map('sanitize_text_field', $posted[$key]));
        }
        return sanitize_text_field((string) $posted[$key]);
    }

    private function render_leads() {
        $all_leads = $this->get_leads();
        $visible_leads = $this->visible_leads($all_leads);
        $edit_id = isset($_GET['edit_lead']) ? absint($_GET['edit_lead']) : 0;
        $editing = array();

        if ($edit_id && isset($all_leads[$edit_id])) {
            if (current_user_can(self::CAP_MANAGE) || (int) $all_leads[$edit_id]['broker_id'] === get_current_user_id()) {
                $editing = $all_leads[$edit_id];
            }
        }

        $defaults = array(
            'id' => 0,
            'broker_id' => get_current_user_id(),
            'client_name' => '',
            'phone' => '',
            'email' => '',
            'property_id' => 0,
            'status' => 'new',
            'source' => '',
            'next_followup' => '',
            'note' => '',
        );
        $form = array_merge($defaults, $editing);
        $status_counts = array_fill_keys(array_keys($this->lead_status_options()), 0);
        foreach ($visible_leads as $lead) {
            if (isset($status_counts[$lead['status']])) {
                $status_counts[$lead['status']]++;
            }
        }

        echo '<div class="harmat-lead-summary">';
        echo '<span>客户总数 <b>' . count($visible_leads) . '</b></span>';
        echo '<span>新客户 <b>' . (int) $status_counts['new'] . '</b></span>';
        echo '<span>已看房 <b>' . (int) $status_counts['visited'] . '</b></span>';
        echo '<span>已成交 <b>' . (int) $status_counts['closed'] . '</b></span>';
        echo '</div>';

        echo '<div class="harmat-leads-layout">';
        echo '<div class="harmat-card harmat-lead-editor">';
        echo '<h2>' . ($form['id'] ? '编辑客户跟进' : '新增客户跟进') . '</h2>';
        echo '<p>经纪人只能看到和维护自己录入的客户；销售管理可以查看全部客户跟进。</p>';
        echo '<form method="post" class="harmat-lead-form">';
        wp_nonce_field('harmat_sales_action_save_lead');
        echo '<input type="hidden" name="harmat_sales_action" value="save_lead">';
        echo '<input type="hidden" name="lead_id" value="' . esc_attr($form['id']) . '">';
        echo '<label>客户姓名<input required name="client_name" value="' . esc_attr($form['client_name']) . '" placeholder="客户姓名"></label>';
        echo '<label>电话<input name="client_phone" value="' . esc_attr($form['phone']) . '" placeholder="+36..."></label>';
        echo '<label>邮箱<input type="email" name="client_email" value="' . esc_attr($form['email']) . '" placeholder="name@email.com"></label>';
        echo '<label>来源<input name="client_source" value="' . esc_attr($form['source']) . '" placeholder="官网 / 电话 / 转介绍"></label>';
        echo '<label>意向房源<select name="property_id">';
        echo '<option value="0">暂未指定</option>';
        foreach ($this->get_properties() as $property) {
            echo '<option value="' . esc_attr($property->ID) . '"' . selected((int) $form['property_id'], (int) $property->ID, false) . '>' . esc_html(get_the_title($property)) . '</option>';
        }
        echo '</select></label>';
        echo '<label>跟进状态<select name="lead_status">';
        foreach ($this->lead_status_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($form['status'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>下次跟进时间<input type="date" name="next_followup" value="' . esc_attr($form['next_followup']) . '"></label>';
        if (current_user_can(self::CAP_MANAGE)) {
            echo '<label>负责经纪人<select name="broker_id">';
            foreach ($this->get_sales_users() as $user) {
                echo '<option value="' . esc_attr($user->ID) . '"' . selected((int) $form['broker_id'], (int) $user->ID, false) . '>' . esc_html($user->display_name . ' (' . $user->user_login . ')') . '</option>';
            }
            echo '</select></label>';
        }
        echo '<label class="harmat-lead-note">备注<textarea name="client_note" rows="4" placeholder="客户预算、看房意向、沟通记录等">' . esc_textarea($form['note']) . '</textarea></label>';
        echo '<div class="harmat-lead-actions"><button class="button button-primary button-hero">' . ($form['id'] ? '保存修改' : '新增客户') . '</button>';
        if ($form['id']) {
            echo '<a class="button" href="' . esc_url($this->page_url(array('tab' => 'leads'))) . '">取消编辑</a>';
        }
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<div class="harmat-lead-list">';
        echo '<h2>客户跟进列表</h2>';
        if (!$visible_leads) {
            echo '<div class="harmat-empty-state">目前还没有客户记录。先录入一个客户，后续可以继续扩展成交、合同和客户门户联动。</div>';
        } else {
            echo '<div class="harmat-lead-table-wrap"><table class="widefat striped harmat-lead-table">';
            echo '<thead><tr>';
            echo '<th>客户</th><th>联系方式</th><th>意向房源</th><th>状态</th><th>登记时间</th><th>保护到期</th><th>保护状态</th><th>下次跟进</th>';
            if (current_user_can(self::CAP_MANAGE)) {
                echo '<th>负责经纪人</th>';
            }
            echo '<th>备注</th><th>操作</th>';
            echo '</tr></thead><tbody>';
            foreach ($visible_leads as $lead) {
                $this->render_lead_table_row($lead);
            }
            echo '</tbody></table></div>';
        }
        echo '</div></div>';
    }

    private function render_lead_table_row($lead) {
        $status_options = $this->lead_status_options();
        $status = isset($status_options[$lead['status']]) ? $lead['status'] : 'new';
        $property_title = $lead['property_id'] ? get_the_title((int) $lead['property_id']) : '';
        $property_url = $lead['property_id'] ? get_permalink((int) $lead['property_id']) : '';
        $broker = !empty($lead['broker_id']) ? get_userdata((int) $lead['broker_id']) : null;
        $days_left = $this->lead_protection_days_left($lead);
        $protection_class = $days_left > 0 ? 'active' : 'expired';
        $protection_label = $days_left > 0 ? '保护中 / 剩余 ' . $days_left . ' 天' : '已过期';
        $created_at = $this->format_lead_datetime($lead['created_at'] ?? '');
        $expires_at = $this->lead_protection_expires_at($lead);

        echo '<tr class="harmat-lead-row harmat-lead-' . esc_attr($status) . '">';
        echo '<td class="harmat-lead-customer"><strong>' . esc_html($lead['client_name']) . '</strong><small>' . esc_html($lead['source'] ?: '来源未填写') . '</small></td>';
        echo '<td><span>' . esc_html($lead['phone'] ?: '-') . '</span><small>' . esc_html($lead['email'] ?: '-') . '</small></td>';
        echo '<td>';
        if ($property_title && $property_url) {
            echo '<a href="' . esc_url($property_url) . '" target="_blank" rel="noopener">' . esc_html($property_title) . '</a>';
        } else {
            echo '暂未指定';
        }
        echo '</td>';
        echo '<td><span class="harmat-lead-status-pill harmat-lead-status-' . esc_attr($status) . '">' . esc_html($status_options[$status]) . '</span></td>';
        echo '<td>' . esc_html($created_at) . '</td>';
        echo '<td>' . esc_html($expires_at ?: '-') . '</td>';
        echo '<td><span class="harmat-lead-protection harmat-lead-protection-' . esc_attr($protection_class) . '">' . esc_html($protection_label) . '</span></td>';
        echo '<td>' . esc_html($lead['next_followup'] ?: '-') . '</td>';
        if (current_user_can(self::CAP_MANAGE)) {
            echo '<td>' . esc_html($broker ? $broker->display_name : '-') . '</td>';
        }
        echo '<td class="harmat-lead-table-note">' . esc_html($lead['note'] ?: '-') . '</td>';
        echo '<td class="harmat-lead-table-actions">';
        echo '<a class="button button-small" href="' . esc_url($this->page_url(array('tab' => 'leads', 'edit_lead' => (int) $lead['id']))) . '">编辑</a>';
        echo '<form method="post" class="harmat-inline-form">';
        wp_nonce_field('harmat_sales_action_delete_lead');
        echo '<input type="hidden" name="harmat_sales_action" value="delete_lead">';
        echo '<input type="hidden" name="lead_id" value="' . esc_attr($lead['id']) . '">';
        echo '<button class="button button-small button-link-delete" onclick="return confirm(\'确定删除这个客户跟进记录吗？\')">删除</button>';
        echo '</form>';
        echo '<small>更新：' . esc_html($this->format_lead_datetime($lead['updated_at'] ?? '')) . '</small>';
        echo '</td>';
        echo '</tr>';
    }

    private function render_lead_card($lead) {
        $status_options = $this->lead_status_options();
        $status = isset($status_options[$lead['status']]) ? $lead['status'] : 'new';
        $property_title = $lead['property_id'] ? get_the_title((int) $lead['property_id']) : '';
        $property_url = $lead['property_id'] ? get_permalink((int) $lead['property_id']) : '';
        $broker = !empty($lead['broker_id']) ? get_userdata((int) $lead['broker_id']) : null;

        echo '<article class="harmat-lead-card harmat-lead-' . esc_attr($status) . '">';
        echo '<div class="harmat-lead-card-head">';
        echo '<div><h3>' . esc_html($lead['client_name']) . '</h3>';
        echo '<p>' . esc_html($lead['source'] ?: '来源未填写') . '</p></div>';
        echo '<span>' . esc_html($status_options[$status]) . '</span>';
        echo '</div>';
        echo '<dl>';
        echo '<div><dt>电话</dt><dd>' . esc_html($lead['phone'] ?: '-') . '</dd></div>';
        echo '<div><dt>邮箱</dt><dd>' . esc_html($lead['email'] ?: '-') . '</dd></div>';
        echo '<div><dt>意向房源</dt><dd>';
        if ($property_title && $property_url) {
            echo '<a href="' . esc_url($property_url) . '" target="_blank" rel="noopener">' . esc_html($property_title) . '</a>';
        } else {
            echo '-';
        }
        echo '</dd></div>';
        echo '<div><dt>下次跟进</dt><dd>' . esc_html($lead['next_followup'] ?: '-') . '</dd></div>';
        if (current_user_can(self::CAP_MANAGE)) {
            echo '<div><dt>负责经纪人</dt><dd>' . esc_html($broker ? $broker->display_name : '-') . '</dd></div>';
        }
        echo '</dl>';
        if (!empty($lead['note'])) {
            echo '<p class="harmat-lead-note-preview">' . nl2br(esc_html($lead['note'])) . '</p>';
        }
        echo '<div class="harmat-lead-card-actions">';
        echo '<a class="button" href="' . esc_url($this->page_url(array('tab' => 'leads', 'edit_lead' => (int) $lead['id']))) . '">编辑</a>';
        echo '<form method="post" class="harmat-inline-form">';
        wp_nonce_field('harmat_sales_action_delete_lead');
        echo '<input type="hidden" name="harmat_sales_action" value="delete_lead">';
        echo '<input type="hidden" name="lead_id" value="' . esc_attr($lead['id']) . '">';
        echo '<button class="button button-link-delete" onclick="return confirm(\'确定删除这个客户跟进记录吗？\')">删除</button>';
        echo '</form>';
        echo '<small>更新：' . esc_html($lead['updated_at'] ?: '-') . '</small>';
        echo '</div>';
        echo '</article>';
    }

    private function get_leads() {
        $raw = get_option('harmat_broker_leads_v1', array());
        if (!is_array($raw)) {
            return array();
        }

        $leads = array();
        foreach ($raw as $key => $lead) {
            if (!is_array($lead)) {
                continue;
            }
            $id = !empty($lead['id']) ? absint($lead['id']) : absint($key);
            if (!$id) {
                continue;
            }
            $leads[$id] = array_merge(array(
                'id' => $id,
                'broker_id' => 0,
                'client_name' => '',
                'phone' => '',
                'email' => '',
                'property_id' => 0,
                'status' => 'new',
                'source' => '',
                'next_followup' => '',
                'note' => '',
                'created_at' => '',
                'updated_at' => '',
                'updated_by' => 0,
            ), $lead);
            $leads[$id]['id'] = $id;
        }

        return $leads;
    }

    private function save_leads($leads) {
        update_option('harmat_broker_leads_v1', $leads, false);
    }

    private function next_lead_id($leads) {
        $ids = array_map('absint', array_keys($leads));
        return $ids ? max($ids) + 1 : 1;
    }

    private function get_deals() {
        $raw = get_option('harmat_sales_deals_v1', array());
        if (!is_array($raw)) {
            return array();
        }

        $deals = array();
        foreach ($raw as $key => $deal) {
            if (!is_array($deal)) {
                continue;
            }
            $id = !empty($deal['id']) ? absint($deal['id']) : absint($key);
            if (!$id) {
                continue;
            }
            $deals[$id] = array_merge(array(
                'id' => $id,
                'lead_id' => 0,
                'inquiry_id' => 0,
                'property_id' => 0,
                'broker_id' => 0,
                'stage' => 'new',
                'client_name' => '',
                'phone' => '',
                'email' => '',
                'amount' => '',
                'deposit' => '',
                'payment_received' => '',
                'expected_close' => '',
                'next_followup' => '',
                'next_step' => '',
                'payment_method' => '',
                'payment_due_date' => '',
                'payment_status' => '',
                'payment_schedule' => '',
                'payment_plan_items' => array(),
                'document_checklist' => array(),
                'contract_status' => '',
                'handover_note' => '',
                'closed_at' => '',
                'commission_rate' => '',
                'commission_amount' => '',
                'commission_due_date' => '',
                'commission_status' => '',
                'commission_note' => '',
                'note' => '',
                'customer_user_id' => 0,
                'customer_account_created_at' => '',
                'customer_account_sent_at' => '',
                'customer_materials' => array(),
                'created_at' => '',
                'updated_at' => '',
                'updated_by' => 0,
            ), $deal);
            $deals[$id]['id'] = $id;
        }

        uasort($deals, function($a, $b) {
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });

        return $deals;
    }

    private function save_deals($deals) {
        update_option('harmat_sales_deals_v1', $deals, false);
    }

    private function next_deal_id($deals) {
        $ids = array_map('absint', array_keys($deals));
        return $ids ? max($ids) + 1 : 1;
    }

    private function agent_tasks($leads) {
        $tasks = array();

        foreach ($leads as $lead) {
            if (empty($lead['next_followup'])) {
                continue;
            }

            $tasks[] = $this->make_sales_task(
                $lead['next_followup'],
                '客户跟进',
                '跟进客户',
                $lead['client_name'] ?? '',
                !empty($lead['property_id']) ? get_the_title((int) $lead['property_id']) : '',
                add_query_arg('edit_lead', (int) $lead['id'], home_url('/agent/'))
            );
        }

        $tasks = array_values(array_filter($tasks));
        usort($tasks, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $tasks;
    }

    private function sales_tasks() {
        $tasks = array();

        foreach ($this->visible_leads($this->get_leads()) as $lead) {
            if (!empty($lead['next_followup'])) {
                $tasks[] = $this->make_sales_task(
                    $lead['next_followup'],
                    '客户跟进',
                    '跟进客户：' . ($lead['client_name'] ?: '未填写客户'),
                    $lead['client_name'] ?? '',
                    !empty($lead['property_id']) ? get_the_title((int) $lead['property_id']) : '',
                    $this->sales_portal_url(array('view' => 'clients', 'edit_lead' => (int) $lead['id']))
                );
            }
        }

        foreach ($this->get_deals() as $deal) {
            $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
            $deal_url = $this->sales_portal_url(array('view' => 'deals', 'edit_deal' => (int) $deal['id']));
            if (!empty($deal['next_followup'])) {
                $tasks[] = $this->make_sales_task(
                    $deal['next_followup'],
                    '销售跟单',
                    $deal['next_step'] ?: '跟进销售机会',
                    $deal['client_name'] ?? '',
                    $property_title,
                    $deal_url
                );
            }
            if (!empty($deal['payment_due_date']) && $this->deal_payment_balance($deal) > 0) {
                $tasks[] = $this->make_sales_task(
                    $deal['payment_due_date'],
                    '付款提醒',
                    '未收款：' . $this->format_money($this->deal_payment_balance($deal)) . ' Ft',
                    $deal['client_name'] ?? '',
                    $property_title,
                    $deal_url
                );
            }
            if (($deal['stage'] ?? '') === 'closed' && !empty($deal['commission_due_date']) && ($deal['commission_status'] ?? '') !== 'paid') {
                $tasks[] = $this->make_sales_task(
                    $deal['commission_due_date'],
                    '佣金付款',
                    '经纪人佣金：' . ($this->deal_commission_amount($deal) ? $this->format_money($this->deal_commission_amount($deal)) . ' Ft' : '金额待确认'),
                    $deal['client_name'] ?? '',
                    $property_title,
                    $this->sales_portal_url(array('view' => 'commissions'))
                );
            }
            if (!empty($deal['expected_close']) && !in_array(($deal['stage'] ?? ''), array('closed', 'lost'), true)) {
                $tasks[] = $this->make_sales_task(
                    $deal['expected_close'],
                    '预计成交',
                    '预计成交/签约日期',
                    $deal['client_name'] ?? '',
                    $property_title,
                    $deal_url
                );
            }
        }

        $tasks = array_values(array_filter($tasks));
        usort($tasks, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $tasks;
    }

    private function make_sales_task($date, $type, $title, $client, $property, $url) {
        $timestamp = strtotime((string) $date);
        if (!$timestamp) {
            return null;
        }

        $today = strtotime(current_time('Y-m-d'));
        $task_day = strtotime(date('Y-m-d', $timestamp));
        if ($task_day < $today) {
            $urgency_key = 'overdue';
            $urgency = '已逾期';
        } elseif ($task_day === $today) {
            $urgency_key = 'today';
            $urgency = '今天';
        } else {
            $urgency_key = 'upcoming';
            $urgency = '近期';
        }

        return array(
            'date' => date_i18n('Y-m-d', $timestamp),
            'type' => $type,
            'title' => $title,
            'client' => $client,
            'property' => $property,
            'url' => $url,
            'urgency' => $urgency,
            'urgency_key' => $urgency_key,
        );
    }

    private function payment_deals() {
        return array_values(array_filter($this->get_deals(), function($deal) {
            return !empty($deal['amount']) || !empty($deal['payment_received']) || !empty($deal['payment_due_date']) || !empty($deal['payment_status']);
        }));
    }

    private function payment_plan_status_options() {
        return array(
            'pending' => '待支付 / Fizetésre vár',
            'partial' => '部分已付 / Részben fizetve',
            'paid' => '已付清 / Kifizetve',
            'overdue' => '逾期 / Lejárt',
        );
    }

    private function sanitize_payment_plan_items($raw) {
        if (!is_array($raw)) {
            return array();
        }

        $statuses = $this->payment_plan_status_options();
        $items = array();
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = isset($item['label']) ? sanitize_text_field($item['label']) : '';
            $amount = isset($item['amount']) ? preg_replace('/[^\d]/', '', (string) $item['amount']) : '';
            $due_date = isset($item['due_date']) ? sanitize_text_field($item['due_date']) : '';
            $paid_amount = isset($item['paid_amount']) ? preg_replace('/[^\d]/', '', (string) $item['paid_amount']) : '';
            $status = isset($item['status']) ? sanitize_key($item['status']) : '';
            $note = isset($item['note']) ? sanitize_text_field($item['note']) : '';

            if ($label === '' && $amount === '' && $due_date === '' && $paid_amount === '' && $note === '') {
                continue;
            }
            if ($due_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
                $due_date = '';
            }
            if (!$status || !isset($statuses[$status])) {
                $status = $this->infer_payment_plan_status($amount, $paid_amount, $due_date);
            }

            $items[] = array(
                'label' => $label,
                'amount' => $amount,
                'due_date' => $due_date,
                'paid_amount' => $paid_amount,
                'status' => $status,
                'note' => $note,
            );

            if (count($items) >= 12) {
                break;
            }
        }

        return $items;
    }

    private function infer_payment_plan_status($amount, $paid_amount, $due_date = '') {
        $amount = (int) $amount;
        $paid_amount = (int) $paid_amount;
        if ($amount > 0 && $paid_amount >= $amount) {
            return 'paid';
        }
        if ($paid_amount > 0) {
            return 'partial';
        }
        if ($due_date && strtotime($due_date) < strtotime(current_time('Y-m-d'))) {
            return 'overdue';
        }
        return 'pending';
    }

    private function deal_payment_plan_items($deal) {
        $items = isset($deal['payment_plan_items']) && is_array($deal['payment_plan_items']) ? $deal['payment_plan_items'] : array();
        return $this->sanitize_payment_plan_items($items);
    }

    private function payment_plan_form_rows($deal, $min_rows = 6) {
        $rows = $this->deal_payment_plan_items($deal);
        if (!$rows) {
            $deposit = (string) ($deal['deposit'] ?? '');
            $amount = (string) ($deal['amount'] ?? '');
            $received = (string) ($deal['payment_received'] ?? '');
            if ($deposit !== '') {
                $rows[] = array(
                    'label' => '定金 / Foglaló',
                    'amount' => $deposit,
                    'due_date' => $deal['expected_close'] ?? '',
                    'paid_amount' => ((int) $received >= (int) $deposit) ? $deposit : '',
                    'status' => ((int) $received >= (int) $deposit) ? 'paid' : 'pending',
                    'note' => '',
                );
            }
            if ($amount !== '') {
                $balance = max(0, (int) $amount - (int) $deposit);
                $rows[] = array(
                    'label' => '剩余房款 / Hátralék',
                    'amount' => $balance ? (string) $balance : '',
                    'due_date' => $deal['payment_due_date'] ?? '',
                    'paid_amount' => '',
                    'status' => $this->infer_payment_plan_status($balance, 0, $deal['payment_due_date'] ?? ''),
                    'note' => '',
                );
            }
        }

        while (count($rows) < $min_rows) {
            $rows[] = array(
                'label' => '',
                'amount' => '',
                'due_date' => '',
                'paid_amount' => '',
                'status' => '',
                'note' => '',
            );
        }

        return $rows;
    }

    private function payment_plan_display_items($deal) {
        $items = $this->deal_payment_plan_items($deal);
        if ($items) {
            return $items;
        }
        return array_values(array_filter($this->payment_plan_form_rows($deal, 0), function($item) {
            return !empty($item['label']) || !empty($item['amount']) || !empty($item['due_date']) || !empty($item['paid_amount']);
        }));
    }

    private function document_checklist_definitions() {
        return array(
            'identity' => array('sales' => '身份证明 / Személyazonosító', 'hu' => 'Személyazonosító okmány', 'en' => 'Identity document', 'zh' => '身份证明'),
            'contract' => array('sales' => '合同文件 / Szerződés', 'hu' => 'Szerződés', 'en' => 'Contract documents', 'zh' => '合同文件'),
            'payment_proof' => array('sales' => '付款凭证 / Fizetési igazolás', 'hu' => 'Fizetési igazolás', 'en' => 'Payment confirmation', 'zh' => '付款凭证'),
            'loan' => array('sales' => '贷款资料 / Hitelügyintézés', 'hu' => 'Hitelügyintézési dokumentumok', 'en' => 'Loan documents', 'zh' => '贷款资料'),
            'handover' => array('sales' => '交付资料 / Átadás', 'hu' => 'Átadási dokumentumok', 'en' => 'Handover documents', 'zh' => '交付资料'),
            'invoice' => array('sales' => '发票/收据 / Számla', 'hu' => 'Számla vagy nyugta', 'en' => 'Invoice or receipt', 'zh' => '发票/收据'),
            'progress_photos' => array('sales' => '项目进展照片 / Projektfotók', 'hu' => 'Projekt előrehaladási fotók', 'en' => 'Project progress photos', 'zh' => '项目进展照片'),
        );
    }

    private function document_checklist_status_options() {
        return array(
            'missing' => '未收到 / Hiányzik',
            'uploaded' => '已上传 / Feltöltve',
            'review' => '待确认 / Ellenőrzés alatt',
            'confirmed' => '已确认 / Jóváhagyva',
            'not_needed' => '不需要 / Nem szükséges',
        );
    }

    private function customer_document_status_labels($lang) {
        $labels = array(
            'hu' => array(
                'missing' => 'Hiányzik',
                'uploaded' => 'Feltöltve',
                'review' => 'Ellenőrzés alatt',
                'confirmed' => 'Jóváhagyva',
                'not_needed' => 'Nem szükséges',
            ),
            'en' => array(
                'missing' => 'Missing',
                'uploaded' => 'Uploaded',
                'review' => 'Under review',
                'confirmed' => 'Confirmed',
                'not_needed' => 'Not required',
            ),
            'zh' => array(
                'missing' => '未收到',
                'uploaded' => '已上传',
                'review' => '待确认',
                'confirmed' => '已确认',
                'not_needed' => '不需要',
            ),
        );
        return $labels[$lang] ?? $labels['hu'];
    }

    private function sanitize_document_checklist($raw) {
        if (!is_array($raw)) {
            return array();
        }

        $statuses = $this->document_checklist_status_options();
        $definitions = $this->document_checklist_definitions();
        $items = array();
        foreach ($definitions as $key => $definition) {
            $item = isset($raw[$key]) && is_array($raw[$key]) ? $raw[$key] : array();
            $status = isset($item['status']) ? sanitize_key($item['status']) : 'missing';
            if (!isset($statuses[$status])) {
                $status = 'missing';
            }
            $note = isset($item['note']) ? sanitize_text_field($item['note']) : '';
            $items[$key] = array(
                'status' => $status,
                'note' => $note,
                'visible' => !empty($item['visible']) ? 1 : 0,
            );
        }

        return $items;
    }

    private function document_checklist_rows($deal, $customer_only = false, $lang = 'zh') {
        $saved = isset($deal['document_checklist']) && is_array($deal['document_checklist']) ? $deal['document_checklist'] : array();
        $definitions = $this->document_checklist_definitions();
        $rows = array();
        foreach ($definitions as $key => $definition) {
            $item = isset($saved[$key]) && is_array($saved[$key]) ? $saved[$key] : array();
            $visible = array_key_exists('visible', $item) ? (int) $item['visible'] : 1;
            if ($customer_only && !$visible) {
                continue;
            }
            $rows[$key] = array(
                'key' => $key,
                'label_sales' => $definition['sales'],
                'label' => $definition[$lang] ?? $definition['hu'],
                'status' => $item['status'] ?? 'missing',
                'note' => $item['note'] ?? '',
                'visible' => $visible,
            );
        }
        return $rows;
    }

    private function deal_payment_balance($deal) {
        $amount = (int) ($deal['amount'] ?? 0);
        $received = (int) ($deal['payment_received'] ?? 0);
        return max(0, $amount - $received);
    }

    private function broker_commission_deals($broker_id = 0) {
        $deals = array_values(array_filter($this->get_deals(), function($deal) use ($broker_id) {
            if (($deal['stage'] ?? '') !== 'closed') {
                return false;
            }
            if ($broker_id && (int) ($deal['broker_id'] ?? 0) !== (int) $broker_id) {
                return false;
            }
            return true;
        }));

        usort($deals, function($a, $b) {
            $a_date = $a['commission_due_date'] ?: ($a['closed_at'] ?: ($a['expected_close'] ?: '9999-12-31'));
            $b_date = $b['commission_due_date'] ?: ($b['closed_at'] ?: ($b['expected_close'] ?: '9999-12-31'));
            return strcmp($a_date, $b_date);
        });

        return $deals;
    }

    private function deal_commission_amount($deal) {
        $amount = (int) ($deal['commission_amount'] ?? 0);
        if ($amount > 0) {
            return $amount;
        }

        $deal_amount = (int) ($deal['amount'] ?? 0);
        $rate = isset($deal['commission_rate']) ? (float) str_replace(',', '.', (string) $deal['commission_rate']) : 0.0;
        if ($deal_amount > 0 && $rate > 0) {
            return (int) round($deal_amount * $rate / 100);
        }

        return 0;
    }

    private function current_customer_deal() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return null;
        }

        $deal_id = (int) get_user_meta($user_id, '_harmat_customer_deal_id', true);
        $deals = $this->get_deals();
        if ($deal_id && isset($deals[$deal_id]) && (int) ($deals[$deal_id]['customer_user_id'] ?? 0) === $user_id) {
            return $deals[$deal_id];
        }

        foreach ($deals as $deal) {
            if ((int) ($deal['customer_user_id'] ?? 0) === $user_id) {
                return $deal;
            }
        }

        return null;
    }

    private function customer_material_mimes() {
        return array(
            'pdf' => 'application/pdf',
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
        );
    }

    private function deal_customer_materials($deal, $customer_only = false) {
        $materials = isset($deal['customer_materials']) && is_array($deal['customer_materials']) ? $deal['customer_materials'] : array();
        $items = array();

        foreach ($materials as $material) {
            if (!is_array($material)) {
                continue;
            }
            $attachment_id = (int) ($material['attachment_id'] ?? 0);
            if (!$attachment_id || get_post_type($attachment_id) !== 'attachment') {
                continue;
            }
            $item = array_merge(array(
                'attachment_id' => $attachment_id,
                'title' => '',
                'note' => '',
                'visibility' => 'customer',
                'uploaded_at' => '',
                'uploaded_by' => 0,
            ), $material);
            $item['visibility'] = ($item['visibility'] ?? 'customer') === 'internal' ? 'internal' : 'customer';
            if ($customer_only && $item['visibility'] !== 'customer') {
                continue;
            }
            $items[] = $item;
        }

        usort($items, function($a, $b) {
            return strcmp((string) ($b['uploaded_at'] ?? ''), (string) ($a['uploaded_at'] ?? ''));
        });

        return $items;
    }

    private function customer_account_email_body($deal, $login, $password, $portal_url) {
        $client_name = trim((string) ($deal['client_name'] ?? ''));
        $property_title = !empty($deal['property_id']) ? get_the_title((int) $deal['property_id']) : '';
        $greeting = $client_name ? 'Tisztelt ' . $client_name . '!' : 'Tisztelt Ügyfelünk!';

        return implode("\n", array(
            $greeting,
            '',
            'Köszönjük, hogy a Harmat Lakópark ingatlanát választotta.',
            '',
            'Elkészítettük az Ön személyes ügyfélközponti hozzáférését. Ezen a felületen a későbbiekben megtekintheti a lakása státuszát, a fizetési és szerződéses információkat, az átadással kapcsolatos adatokat, valamint itt fogjuk közzétenni a projekt előrehaladásáról készült friss fényképeket és tájékoztatókat.',
            '',
            'Ügyfélközpont link: ' . $portal_url,
            'Felhasználónév: ' . $login,
            'Ideiglenes jelszó: ' . $password,
            '',
            $property_title ? 'Lakás: ' . $property_title : '',
            '',
            'Kérjük, az első belépés után őrizze meg biztonságosan a hozzáférési adatait.',
            '',
            'Üdvözlettel,',
            'Harmat Lakópark értékesítés',
            'ertekesites@harmat22.hu',
            'https://harmat22.hu',
        ));
    }

    private function sum_commissions($deals) {
        $sum = 0;
        foreach ($deals as $deal) {
            $sum += $this->deal_commission_amount($deal);
        }
        return $sum;
    }

    private function sum_deal_amounts($deals) {
        $sum = 0;
        foreach ($deals as $deal) {
            $sum += (int) ($deal['amount'] ?? 0);
        }
        return $sum;
    }

    private function date_plus_one_month($date) {
        $timestamp = strtotime((string) $date);
        if (!$timestamp) {
            $timestamp = current_time('timestamp');
        }
        return date_i18n('Y-m-d', strtotime('+1 month', $timestamp));
    }

    private function find_duplicate_lead($leads, $client_name, $phone, $current_lead_id = 0) {
        $name_key = $this->normalize_lead_name($client_name);
        $phone_key = $this->normalize_lead_phone($phone);

        if (!$name_key || !$phone_key) {
            return null;
        }

        foreach ($leads as $lead) {
            if ((int) ($lead['id'] ?? 0) === (int) $current_lead_id) {
                continue;
            }

            if (
                $this->normalize_lead_name($lead['client_name'] ?? '') === $name_key &&
                $this->normalize_lead_phone($lead['phone'] ?? '') === $phone_key &&
                $this->lead_protection_days_left($lead) > 0
            ) {
                return $lead;
            }
        }

        return null;
    }

    private function normalize_lead_name($name) {
        $name = remove_accents((string) $name);
        $name = strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        return $name;
    }

    private function normalize_lead_phone($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) > 9) {
            return substr($digits, -9);
        }
        return $digits;
    }

    private function lead_protection_days_left($lead) {
        $created_at = isset($lead['created_at']) ? (string) $lead['created_at'] : '';
        $created_ts = $created_at ? strtotime($created_at) : 0;
        if (!$created_ts) {
            return self::LEAD_PROTECTION_DAYS;
        }

        $expires_ts = $created_ts + (self::LEAD_PROTECTION_DAYS * DAY_IN_SECONDS);
        $seconds_left = $expires_ts - current_time('timestamp');
        if ($seconds_left <= 0) {
            return 0;
        }

        return (int) ceil($seconds_left / DAY_IN_SECONDS);
    }

    private function lead_protection_expires_at($lead) {
        $created_at = isset($lead['created_at']) ? (string) $lead['created_at'] : '';
        $created_ts = $created_at ? strtotime($created_at) : 0;
        if (!$created_ts) {
            return '';
        }

        return date_i18n('Y-m-d H:i', $created_ts + (self::LEAD_PROTECTION_DAYS * DAY_IN_SECONDS));
    }

    private function format_lead_datetime($datetime) {
        $timestamp = $datetime ? strtotime((string) $datetime) : 0;
        if (!$timestamp) {
            return '-';
        }

        return date_i18n('Y-m-d H:i', $timestamp);
    }

    private function visible_leads($leads) {
        $current_user_id = get_current_user_id();
        $visible = array_values(array_filter($leads, function($lead) use ($current_user_id) {
            return current_user_can(self::CAP_MANAGE) || (int) $lead['broker_id'] === $current_user_id;
        }));

        usort($visible, function($a, $b) {
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });

        return $visible;
    }

    private function lead_status_options() {
        return array(
            'new' => '新客户 / Új',
            'contacted' => '已联系 / Kapcsolatban',
            'visited' => '已看房 / Megtekintés',
            'reserved' => '意向预订 / Foglalási szándék',
            'closed' => '已成交 / Lezárva',
            'lost' => '暂缓/无效 / Lezáratlan',
        );
    }

    private function agent_editable_lead_status_options() {
        $options = $this->lead_status_options();
        unset($options['closed']);
        return $options;
    }

    private function deal_stage_options() {
        return array(
            'new' => '新机会 / Új lehetőség',
            'contacted' => '已联系 / Kapcsolatban',
            'viewing' => '已约看房 / Megtekintés',
            'negotiation' => '价格沟通 / Egyeztetés',
            'reserved' => '已预订 / Foglalva',
            'contract' => '合同中 / Szerződés alatt',
            'closed' => '已成交 / Lezárva',
            'lost' => '流失 / Elveszett',
        );
    }

    private function deal_stage_to_lead_status($stage) {
        if ($stage === 'viewing') {
            return 'visited';
        }
        if (in_array($stage, array('reserved', 'contract'), true)) {
            return 'reserved';
        }
        if ($stage === 'closed') {
            return 'closed';
        }
        if ($stage === 'lost') {
            return 'lost';
        }
        if (in_array($stage, array('contacted', 'negotiation'), true)) {
            return 'contacted';
        }
        return 'new';
    }

    private function payment_method_options() {
        return array(
            'cash' => '现金/自有资金',
            'loan' => '银行贷款',
            'mixed' => '自有资金 + 贷款',
            'installment' => '分期付款',
            'csok' => 'CSOK / támogatás',
            'other' => '其他',
        );
    }

    private function payment_status_options() {
        return array(
            'not_started' => '未开始收款',
            'partial' => '部分已收',
            'paid' => '已收齐',
            'overdue' => '逾期未收',
        );
    }

    private function commission_status_options() {
        return array(
            'pending' => '待确认',
            'scheduled' => '待支付',
            'paid' => '已支付',
            'withheld' => '暂缓支付',
        );
    }

    private function infer_payment_status($amount, $received, $due_date = '', $manual_status = '') {
        if ($manual_status && isset($this->payment_status_options()[$manual_status])) {
            return $manual_status;
        }

        $amount = (int) $amount;
        $received = (int) $received;
        if ($amount > 0 && $received >= $amount) {
            return 'paid';
        }
        if ($due_date && strtotime($due_date) < strtotime(current_time('Y-m-d')) && $amount > $received) {
            return 'overdue';
        }
        if ($received > 0) {
            return 'partial';
        }
        return 'not_started';
    }

    private function contract_status_options() {
        return array(
            'draft' => '合同草案',
            'review' => '客户/律师审核中',
            'signed' => '已签约',
            'paid_deposit' => '已付定金',
            'handover_ready' => '可交付',
            'handover_done' => '已交付',
        );
    }

    private function count_deals_by_payment($deals, $payment_method) {
        $count = 0;
        foreach ($deals as $deal) {
            if (($deal['payment_method'] ?? '') === $payment_method) {
                $count++;
            }
        }
        return $count;
    }

    private function count_deals_by_contract($deals, $contract_status) {
        $count = 0;
        foreach ($deals as $deal) {
            if (($deal['contract_status'] ?? '') === $contract_status) {
                $count++;
            }
        }
        return $count;
    }

    private function get_sales_users() {
        return get_users(array(
            'role__in' => array(self::ROLE_MANAGER, self::ROLE_BROKER),
            'orderby' => 'display_name',
            'order' => 'ASC',
        ));
    }

    private function broker_phone($user_id) {
        return (string) get_user_meta((int) $user_id, '_harmat_broker_phone', true);
    }

    private function broker_commission_rate($user_id) {
        return (string) get_user_meta((int) $user_id, '_harmat_broker_commission_rate', true);
    }

    private function sanitize_commission_rate($rate) {
        $rate = trim(str_replace(',', '.', (string) $rate));
        if ($rate === '') {
            return '';
        }
        if (!preg_match('/^\d+(\.\d{1,4})?$/', $rate)) {
            return '';
        }
        return rtrim(rtrim($rate, '0'), '.');
    }

    private function is_sales_user($user_id) {
        $user = $user_id ? get_userdata((int) $user_id) : null;
        return $user && array_intersect(array(self::ROLE_MANAGER, self::ROLE_BROKER), (array) $user->roles);
    }

    private function property_id_by_title($title) {
        $title = trim((string) $title);
        if ($title === '') {
            return 0;
        }

        foreach ($this->get_properties() as $property) {
            if (strcasecmp(get_the_title($property), $title) === 0) {
                return (int) $property->ID;
            }
        }

        return 0;
    }

    private function get_properties($search = '') {
        $args = array(
            'post_type' => 'property',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        if ($search) {
            $args['s'] = $search;
        }

        return get_posts($args);
    }

    private function sales_status($post_id) {
        $status = get_post_meta($post_id, 'property_status', true);
        $under_offer = get_post_meta($post_id, 'property_under_offer', true);
        if ($status === 'sold') {
            return 'sold';
        }
        if ($under_offer) {
            return 'reserved';
        }
        return 'current';
    }

    private function status_options() {
        return array(
            'current' => '在售 / Elérhető',
            'reserved' => '已预订 / Foglalva',
            'sold' => '已出售 / Eladva',
        );
    }

    private function frontend_status_labels() {
        return array(
            'current' => 'Elérhető',
            'reserved' => 'Foglalva',
            'sold' => 'Eladva',
        );
    }

    public function frontend_sales_data($post_ids = array()) {
        if ($post_ids) {
            $posts = array_filter(array_map('get_post', array_map('absint', (array) $post_ids)), function($post) {
                return $post && $post->post_type === 'property' && $post->post_status === 'publish';
            });
        } else {
            $posts = get_posts(array(
                'post_type' => 'property',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
            ));
        }

        $labels = $this->frontend_status_labels();
        $items = array();

        foreach ($posts as $post) {
            $post_id = (int) $post->ID;
            $price = (int) get_post_meta($post_id, 'property_price', true);
            $sales_area = $this->get_sales_area($post_id);
            $status = $this->sales_status($post_id);
            $items[$post_id] = array(
                'id' => $post_id,
                'title' => get_the_title($post_id),
                'status' => $status,
                'statusLabel' => isset($labels[$status]) ? $labels[$status] : $labels['current'],
                'price' => $price,
                'salesArea' => $sales_area,
                'sqmPrice' => $sales_area > 0 ? round($price / $sales_area) : 0,
                'hidePrice' => get_post_meta($post_id, '_harmat_hide_front_price', true) === 'yes',
                'rooms' => get_post_meta($post_id, 'property_rooms', true),
                'bedrooms' => get_post_meta($post_id, 'property_bedrooms', true),
                'building' => get_post_meta($post_id, 'property_address_street', true),
                'floor' => get_post_meta($post_id, 'property_address_street_number', true),
                'unit' => get_post_meta($post_id, 'property_address_sub_number', true),
                'terrace' => get_post_meta($post_id, 'property_land_area', true),
                'b_area' => get_post_meta($post_id, 'property_building_area', true),
                'l_area' => get_post_meta($post_id, 'property_land_area', true),
                'url' => get_permalink($post_id),
            );
        }

        return $items;
    }

    private function format_money($value) {
        return number_format((float) $value, 0, '.', ' ');
    }

    private function get_sales_area($post_id) {
        $override = get_post_meta($post_id, '_harmat_sales_area', true);
        if ($override !== '') {
            return (float) $override;
        }

        $area = (float) get_post_meta($post_id, 'property_building_area', true);
        $land = (float) get_post_meta($post_id, 'property_land_area', true);
        return $area + $land;
    }

    private function format_area($value) {
        return number_format((float) $value, 2, '.', '');
    }

    private function role_label($role) {
        if ($role === self::ROLE_MANAGER) {
            return '销售管理';
        }
        if ($role === self::ROLE_CUSTOMER) {
            return '客户中心';
        }
        return '经纪人查看';
    }

    private function sales_portal_url($args = array()) {
        return add_query_arg($args, home_url('/sales/'));
    }

    private function page_url($args = array()) {
        return add_query_arg(array_merge(array('page' => self::PAGE_SLUG), $args), admin_url('admin.php'));
    }

    private function account_return_url($args = array()) {
        $return_to = isset($_POST['return_to']) ? sanitize_key(wp_unslash($_POST['return_to'])) : '';
        if ($return_to === 'sales_brokers') {
            return $this->sales_portal_url(array_merge(array('view' => 'brokers'), $args));
        }
        if ($return_to === 'sales_broker_detail') {
            $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
            return $this->sales_portal_url(array_merge(array('view' => 'brokers', 'broker_id' => $user_id), $args));
        }

        return $this->page_url(array_merge(array('tab' => 'accounts'), $args));
    }

    private function property_return_url($args = array()) {
        $return_to = isset($_POST['return_to']) ? sanitize_key(wp_unslash($_POST['return_to'])) : '';
        if ($return_to === 'sales_properties') {
            return $this->sales_portal_url(array_merge(array('view' => 'properties'), $args));
        }

        return $this->page_url($args);
    }

    private function deal_return_url($args = array()) {
        $return_to = isset($_POST['return_to']) ? sanitize_key(wp_unslash($_POST['return_to'])) : '';
        if ($return_to === 'sales_deals') {
            return $this->sales_portal_url(array_merge(array('view' => 'deals'), $args));
        }

        return $this->sales_portal_url(array_merge(array('view' => 'deals'), $args));
    }

    private function lead_return_url($args = array()) {
        $return_to = isset($_POST['return_to']) ? sanitize_key(wp_unslash($_POST['return_to'])) : '';
        if ($return_to === 'sales_clients') {
            return $this->sales_portal_url(array_merge(array('view' => 'clients'), $args));
        }
        if ($return_to === 'agent_clients') {
            return add_query_arg(array_merge(array('view' => 'clients'), $args), home_url('/agent/'));
        }
        if ($return_to === 'agent') {
            return add_query_arg($args, home_url('/agent/'));
        }

        return $this->page_url(array_merge(array('tab' => 'leads'), $args));
    }

    private function sales_portal_css() {
        return '
        *{box-sizing:border-box}body.harmat-sales-portal-body{margin:0;background:#fbf4e7;color:#253137;font-family:Montserrat,Arial,"Microsoft YaHei",sans-serif}
        .harmat-sales-portal-shell{width:min(1500px,calc(100% - 32px));margin:0 auto;padding:28px 0 44px}
        .harmat-sales-portal-hero{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:14px;padding:26px 30px;border:1px solid #ead8b8;border-radius:22px;background:linear-gradient(135deg,#fffaf1,#fff);box-shadow:0 18px 45px rgba(70,54,28,.08)}
        .harmat-sales-eyebrow{margin:0 0 8px;color:#a5742c;font-size:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.harmat-sales-portal-hero h1{margin:0;color:#253137;font-family:Georgia,"Times New Roman",serif;font-size:38px;font-weight:500}.harmat-sales-portal-hero p{margin:8px 0 0;color:#687178}
        .harmat-sales-user{display:flex;align-items:center;gap:14px;padding:10px 12px;border-radius:999px;background:#fff;border:1px solid #ead8b8}.harmat-sales-user span{font-weight:900}.harmat-sales-user a{color:#a5742c;font-weight:900;text-decoration:none}
        .harmat-sales-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px;padding:8px;border-radius:18px;background:#fff;border:1px solid #ead8b8;box-shadow:0 10px 28px rgba(70,54,28,.05)}.harmat-sales-nav a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:12px;color:#253137;text-decoration:none;font-weight:900}.harmat-sales-nav a.is-active{background:#a8762d;color:#fff}.harmat-sales-nav a:last-child{margin-left:auto;color:#a8762d}
        .harmat-sales-notice{margin:0 0 16px;padding:14px 16px;border-radius:14px;font-weight:800}.harmat-sales-notice span{display:block;margin-top:8px}.harmat-sales-notice code{display:inline-block;padding:3px 7px;border-radius:6px;background:rgba(255,255,255,.72);font-size:15px}.harmat-sales-notice-success{background:#eef8f1;color:#1f7a4d;border:1px solid #cce9d5}.harmat-sales-notice-error{background:#fff1f0;color:#b42318;border:1px solid #ffd1cc}
        .harmat-sales-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin:0 0 18px}.harmat-sales-kpis article{padding:18px;border-radius:18px;background:#fff;border:1px solid #ead8b8;box-shadow:0 12px 30px rgba(70,54,28,.06)}.harmat-sales-kpis small{display:block;color:#a5742c;font-weight:900;letter-spacing:.08em}.harmat-sales-kpis strong{display:block;margin-top:8px;color:#253137;font-size:34px;line-height:1}.harmat-sales-kpis-compact{grid-template-columns:repeat(5,minmax(0,1fr))}
        .harmat-sales-split{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-bottom:18px}.harmat-sales-panel{padding:22px;border-radius:22px;background:#fff;border:1px solid #ead8b8;box-shadow:0 18px 45px rgba(70,54,28,.08);margin-bottom:18px}.harmat-sales-panel-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}.harmat-sales-panel h2{margin:0;color:#253137;font-family:Georgia,"Times New Roman",serif;font-size:27px;font-weight:500}.harmat-sales-panel p{margin:5px 0 0;color:#6f7780}.harmat-sales-panel-head a{color:#a8762d;font-weight:900;text-decoration:none}
        .harmat-sales-table-wrap{overflow:auto;border:1px solid #ead8b8;border-radius:16px;background:#fffaf3}.harmat-sales-table{width:100%;min-width:960px;border-collapse:collapse}.harmat-sales-table th{padding:12px 14px;background:#fbf4e7;color:#9a6b27;font-size:12px;letter-spacing:.08em;text-align:left;text-transform:uppercase;white-space:nowrap}.harmat-sales-table td{padding:13px 14px;border-top:1px solid #ead8b8;color:#253137;vertical-align:top}.harmat-sales-table td strong{display:block;font-size:15px}.harmat-sales-table td span{display:block}.harmat-sales-table td small{display:block;margin-top:4px;color:#8a9299;overflow-wrap:anywhere}.harmat-sales-table a{color:#a8762d;font-weight:900;text-decoration:none}.harmat-sales-table input,.harmat-sales-table select{width:100%;min-width:120px;min-height:36px;padding:7px 9px;border:1px solid #e3cfad;border-radius:9px;background:#fff;color:#253137;font:inherit}
        .harmat-sales-inquiry-table{min-width:1260px}.harmat-sales-deal-table{min-width:1420px}.harmat-sales-task-table{min-width:1050px}.harmat-sales-payment-table{min-width:1200px}.harmat-sales-commission-table{min-width:1280px}.harmat-sales-property-table{min-width:1420px}.harmat-sales-pill,.harmat-sales-protection{display:inline-flex!important;width:max-content;max-width:190px;padding:6px 10px;border-radius:999px;background:#eef8f1;color:#1f7a4d;font-size:12px;font-weight:900;line-height:1.25;white-space:normal}.harmat-sales-property-reserved,.harmat-sales-deal-reserved,.harmat-sales-deal-contract,.harmat-sales-payment-partial,.harmat-sales-task-today,.harmat-sales-commission-scheduled{background:#fff2cf;color:#8a5a18}.harmat-sales-property-sold,.harmat-sales-deal-lost,.harmat-sales-payment-not_started,.harmat-sales-commission-pending{background:#eceff1;color:#687178}.harmat-sales-deal-contacted,.harmat-sales-deal-viewing,.harmat-sales-task-upcoming{background:#edf6ff;color:#145f94}.harmat-sales-deal-negotiation{background:#fff6e5;color:#8a5a18}.harmat-sales-deal-closed,.harmat-sales-payment-paid,.harmat-sales-commission-paid{background:#eef8f1;color:#1f7a4d}.harmat-sales-payment-overdue,.harmat-sales-task-overdue,.harmat-sales-commission-withheld{background:#fff1f0;color:#b42318}.harmat-sales-protection-active{background:#fff2cf;color:#8a5a18}.harmat-sales-protection-expired{background:#eceff1;color:#687178}.harmat-sales-note-cell{max-width:300px;color:#5d6670;overflow-wrap:anywhere}
        .harmat-sales-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.harmat-sales-form label{display:grid;gap:6px;color:#9a6b27;font-size:12px;font-weight:900;letter-spacing:.05em}.harmat-sales-form input,.harmat-sales-form select,.harmat-sales-form textarea,.harmat-sales-search input{width:100%;min-height:42px;padding:10px 12px;border:1px solid #e3cfad;border-radius:10px;background:#fffaf3;color:#253137;font:inherit}.harmat-sales-check{display:flex!important;grid-template-columns:none!important;align-items:center;gap:10px;color:#253137;font-size:13px;letter-spacing:0}.harmat-sales-check input{width:auto;min-height:0}.harmat-sales-form-wide,.harmat-sales-form-actions{grid-column:1/-1}.harmat-sales-form-actions{display:flex;gap:12px;align-items:center}.harmat-sales-form button,.harmat-sales-search button,.harmat-sales-inline-form button{min-height:44px;padding:0 18px;border:0;border-radius:10px;background:#a8762d;color:#fff;font-weight:900;letter-spacing:.08em;cursor:pointer}.harmat-sales-form-actions a{color:#a8762d;font-weight:900;text-decoration:none}.harmat-sales-disabled-button{min-height:44px;padding:0 18px;border:0;border-radius:10px;background:#eceff1;color:#687178;font-weight:900;letter-spacing:.08em;cursor:not-allowed}
        .harmat-sales-search{display:flex;gap:8px;align-items:center}.harmat-sales-search input{min-width:260px}.harmat-sales-rule-list,.harmat-sales-stage-list{display:grid;gap:12px}.harmat-sales-rule-list span,.harmat-sales-stage-list span{display:flex;justify-content:space-between;gap:16px;align-items:center;padding:14px;border-radius:14px;background:#fffaf3;border:1px solid #ead8b8}.harmat-sales-rule-list strong,.harmat-sales-stage-list strong{color:#9a6b27}.harmat-sales-rule-list b,.harmat-sales-stage-list b{color:#253137;font-size:20px}.harmat-sales-link-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}.harmat-sales-link-grid a{display:grid;gap:7px;padding:15px;border-radius:14px;background:#fffaf3;border:1px solid #ead8b8;text-decoration:none}.harmat-sales-link-grid strong{color:#253137}.harmat-sales-link-grid code{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#a8762d;background:#fff;padding:6px 8px;border-radius:8px}.harmat-sales-link-grid span{color:#6f7780;font-size:13px}
        .harmat-sales-customer-table{min-width:1380px}.harmat-sales-head-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end}.harmat-sales-customer-profile-grid{align-items:start}.harmat-sales-customer-detail{display:grid;gap:14px}.harmat-sales-customer-detail section{padding:14px;border-radius:14px;background:#fffaf3;border:1px solid #ead8b8}.harmat-sales-customer-detail h3,.harmat-sales-customer-ledger h3{margin:0 0 12px;color:#a8762d;font-size:14px;letter-spacing:.08em}.harmat-sales-customer-detail dl,.harmat-sales-customer-ledger dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:0}.harmat-sales-customer-detail div,.harmat-sales-customer-ledger div{padding:10px;border-radius:10px;background:#fff}.harmat-sales-customer-detail dt,.harmat-sales-customer-ledger dt{color:#6f7780;font-size:12px;font-weight:900}.harmat-sales-customer-detail dd,.harmat-sales-customer-ledger dd{margin:5px 0 0;color:#253137;font-weight:800;overflow-wrap:anywhere}.harmat-sales-customer-flow{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px}.harmat-sales-customer-flow article{display:grid;gap:7px;padding:14px;border-radius:14px;background:#fffaf3;border:1px solid #ead8b8}.harmat-sales-customer-flow small{color:#a8762d;font-weight:900;letter-spacing:.06em}.harmat-sales-customer-flow strong{color:#253137;font-size:20px;overflow-wrap:anywhere}.harmat-sales-customer-flow .harmat-sales-pill{margin-top:2px}.harmat-sales-customer-ledger{padding:14px;border-radius:14px;background:#fffaf3;border:1px solid #ead8b8}.harmat-sales-material-form{margin-bottom:16px}.harmat-sales-material-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}.harmat-sales-material-list article{display:grid;gap:8px;padding:14px;border-radius:14px;background:#fffaf3;border:1px solid #ead8b8}.harmat-sales-material-list strong{color:#253137}.harmat-sales-material-list small{color:#6f7780}.harmat-sales-material-list p{margin:0;color:#5d6670;line-height:1.55}.harmat-sales-material-list a{color:#a8762d;font-weight:900;text-decoration:none}.harmat-sales-material-actions{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-top:2px}.harmat-sales-material-actions form{margin:0}.harmat-sales-material-actions button{min-height:34px;padding:0 10px;border:1px solid #d92d20;border-radius:9px;background:#fff;color:#b42318;font:inherit;font-size:12px;font-weight:900;cursor:pointer}.harmat-sales-detail-wide{grid-column:1/-1}
        .harmat-sales-actions{min-width:150px}.harmat-sales-actions form{display:inline-block;margin:0 6px 6px 0}.harmat-sales-actions a,.harmat-sales-actions button{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:0 10px;border:1px solid #a8762d;border-radius:8px;background:#fff;color:#a8762d;font:inherit;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer}.harmat-sales-actions button{border-color:#d92d20;color:#b42318}.harmat-sales-empty{padding:24px;border:1px dashed #d4bea0;border-radius:16px;color:#6f7780;background:#fffaf3}
        @media(max-width:1000px){.harmat-sales-portal-shell{width:min(100% - 20px,760px);padding-top:14px}.harmat-sales-portal-hero,.harmat-sales-panel-head{display:grid}.harmat-sales-portal-hero h1{font-size:31px}.harmat-sales-nav a:last-child{margin-left:0}.harmat-sales-kpis,.harmat-sales-kpis-compact,.harmat-sales-split,.harmat-sales-form{grid-template-columns:1fr}.harmat-sales-panel{padding:16px}.harmat-sales-search{display:grid}.harmat-sales-table{min-width:980px}}
        ';
    }

    private function agent_portal_css() {
        return '
        *{box-sizing:border-box} body.harmat-agent-body{margin:0;background:#fbf4e7;color:#273238;font-family:Montserrat,Arial,"Microsoft YaHei",sans-serif}
        .harmat-agent-shell{width:min(1320px,calc(100% - 32px));margin:0 auto;padding:28px 0 42px}
        .harmat-agent-hero{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:18px;padding:26px 30px;border:1px solid #ead8b8;border-radius:22px;background:linear-gradient(135deg,#fffaf1,#fff);box-shadow:0 18px 45px rgba(70,54,28,.08)}
        .harmat-agent-eyebrow{margin:0 0 8px;color:#a5742c;font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.harmat-agent-hero h1{margin:0;color:#253137;font-family:Georgia,"Times New Roman",serif;font-size:36px;font-weight:500}.harmat-agent-hero p{margin:8px 0 0;color:#687178}
        .harmat-agent-user{display:flex;align-items:center;gap:14px;padding:10px 12px;border-radius:999px;background:#fff;border:1px solid #ead8b8}.harmat-agent-user span{font-weight:800}.harmat-agent-user a{color:#a5742c;font-weight:800;text-decoration:none}.harmat-portal-mini-lang{display:flex;gap:6px;align-items:center}.harmat-portal-mini-lang a{display:inline-flex!important;align-items:center;justify-content:center;min-height:30px;padding:0 9px;border-radius:999px;border:1px solid #ead8b8;color:#a5742c!important;background:#fffaf3;text-decoration:none;font-size:12px;font-weight:900}.harmat-portal-mini-lang a.is-active{background:#a8762d;color:#fff!important;border-color:#a8762d}
        .harmat-agent-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px;padding:8px;border-radius:18px;background:#fff;border:1px solid #ead8b8;box-shadow:0 10px 28px rgba(70,54,28,.05)}.harmat-agent-nav a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:12px;color:#253137;text-decoration:none;font-weight:900}.harmat-agent-nav a.is-active{background:#a8762d;color:#fff}.harmat-agent-nav a:last-child{margin-left:auto;color:#a8762d}
        .harmat-agent-notice{margin:0 0 16px;padding:14px 16px;border-radius:14px;font-weight:800}.harmat-agent-notice-success{background:#eef8f1;color:#1f7a4d;border:1px solid #cce9d5}.harmat-agent-notice-error{background:#fff1f0;color:#b42318;border:1px solid #ffd1cc}
        .harmat-agent-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin:0 0 18px}.harmat-agent-kpis article{padding:18px;border-radius:18px;background:#fff;border:1px solid #ead8b8;box-shadow:0 12px 30px rgba(70,54,28,.06)}.harmat-agent-kpis small{display:block;color:#a5742c;font-weight:800;letter-spacing:.08em}.harmat-agent-kpis strong{display:block;margin-top:8px;color:#253137;font-size:30px;line-height:1;overflow-wrap:anywhere}
        .harmat-agent-grid{display:grid;grid-template-columns:minmax(340px,440px) minmax(0,1fr);gap:18px;margin-bottom:18px}.harmat-agent-panel{padding:22px;border-radius:22px;background:#fff;border:1px solid #ead8b8;box-shadow:0 18px 45px rgba(70,54,28,.08)}.harmat-agent-panel-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:16px}.harmat-agent-panel h2{margin:0;color:#253137;font-family:Georgia,"Times New Roman",serif;font-weight:500;font-size:26px}.harmat-agent-panel p{margin:4px 0 0;color:#6f7780}
        .harmat-agent-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.harmat-agent-form label{display:grid;gap:6px;color:#9a6b27;font-size:12px;font-weight:800;letter-spacing:.06em}.harmat-agent-form input,.harmat-agent-form select,.harmat-agent-form textarea{width:100%;min-height:42px;padding:10px 12px;border:1px solid #e3cfad;border-radius:10px;background:#fffaf3;color:#253137;font:inherit}.harmat-agent-form .harmat-agent-readonly-input{background:#eef8f1;border-color:#cce9d5;color:#1f7a4d;font-weight:900;cursor:not-allowed}.harmat-agent-span{grid-column:1/-1}.harmat-agent-primary{grid-column:1/-1;display:inline-flex;align-items:center;justify-content:center;min-height:48px;border:0;border-radius:12px;background:#a8762d;color:#fff;font-weight:900;letter-spacing:.12em;text-decoration:none;cursor:pointer}.harmat-agent-secondary{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border:1px solid #a8762d;border-radius:12px;color:#a8762d;background:#fffaf3;font-weight:900;text-decoration:none}
        .harmat-agent-lead-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}.harmat-agent-lead-stats span{padding:12px;border-radius:14px;background:#fffaf3;border:1px solid #ead8b8;color:#a5742c;font-weight:800}.harmat-agent-lead-stats b{display:block;color:#253137;font-size:22px}
        .harmat-agent-client-gateway{display:flex;flex-direction:column}.harmat-agent-mini-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:6px 0 18px}.harmat-agent-mini-stats span{padding:16px;border-radius:16px;background:#fffaf3;border:1px solid #ead8b8}.harmat-agent-mini-stats small{display:block;color:#a5742c;font-weight:800;letter-spacing:.06em}.harmat-agent-mini-stats b{display:block;margin-top:8px;color:#253137;font-size:28px}.harmat-agent-task-preview{display:grid;gap:8px;margin:0 0 16px}.harmat-agent-task-preview a{display:grid;grid-template-columns:92px minmax(0,1fr);gap:4px 10px;padding:11px 12px;border-radius:12px;background:#fffaf3;border:1px solid #ead8b8;color:#253137;text-decoration:none}.harmat-agent-task-preview strong{color:#a8762d}.harmat-agent-task-preview small{grid-column:2;color:#6f7780}.harmat-agent-client-link{width:100%;margin-top:auto}.harmat-agent-helper{margin-top:14px!important;font-size:13px}
        .harmat-agent-lead-table-wrap{overflow:auto;border:1px solid #ead8b8;border-radius:16px;background:#fffaf3}.harmat-agent-lead-table{width:100%;min-width:1120px;border-collapse:collapse}.harmat-agent-lead-table th{padding:12px 14px;background:#fbf4e7;color:#9a6b27;font-size:12px;letter-spacing:.08em;text-align:left;text-transform:uppercase}.harmat-agent-lead-table td{padding:13px 14px;border-top:1px solid #ead8b8;color:#253137;vertical-align:top}.harmat-agent-lead-table td strong{display:block;font-size:15px}.harmat-agent-lead-table td small{display:block;margin-top:3px;color:#8a9299}.harmat-agent-status-pill,.harmat-agent-protection{display:inline-flex;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800;white-space:nowrap}.harmat-agent-status-pill{background:#eef8f1;color:#1f7a4d}.harmat-agent-protection-active{background:#fff2cf;color:#8a5a18}.harmat-agent-protection-expired{background:#eceff1;color:#687178}.harmat-agent-note-cell{max-width:240px;color:#6f7780}.harmat-agent-lead-table button{border:0;background:transparent;color:#b42318;font-weight:800;cursor:pointer}
        .harmat-agent-actions{min-width:128px}.harmat-agent-actions form{display:inline-block;margin:0 0 0 6px}.harmat-agent-actions a,.harmat-agent-actions button{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:0 10px;border:1px solid #a8762d;border-radius:8px;background:#fff;color:#a8762d;font:inherit;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer}.harmat-agent-actions button{border-color:#d92d20;color:#b42318}
        .harmat-agent-task-pill{display:inline-flex;width:max-content;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:900}.harmat-agent-task-overdue{background:#fff1f0;color:#b42318}.harmat-agent-task-today,.harmat-agent-commission-scheduled{background:#fff2cf;color:#8a5a18}.harmat-agent-task-upcoming{background:#edf6ff;color:#145f94}.harmat-agent-commission-paid{background:#eef8f1;color:#1f7a4d}.harmat-agent-commission-pending{background:#eceff1;color:#687178}.harmat-agent-commission-withheld{background:#fff1f0;color:#b42318}
        .harmat-agent-empty{padding:24px;border:1px dashed #d4bea0;border-radius:16px;color:#6f7780;background:#fffaf3}.harmat-agent-property-toolbar{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 14px}.harmat-agent-property-toolbar span{display:inline-flex;align-items:center;min-height:38px;padding:0 12px;border-radius:999px;background:#fffaf3;border:1px solid #ead8b8;color:#6f7780;font-weight:800}.harmat-agent-property-toolbar strong{color:#253137}.harmat-agent-property-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px;max-height:680px;overflow:auto;padding-right:4px}.harmat-agent-properties-page .harmat-agent-property-list{max-height:none}.harmat-agent-property{display:grid;gap:6px;padding:13px;border-radius:14px;background:#fffaf3;border:1px solid #ead8b8;text-decoration:none;color:#253137}.harmat-agent-property strong{font-size:16px}.harmat-agent-property span{color:#1f7a4d;font-weight:800}.harmat-agent-property small{color:#6f7780}.harmat-agent-property-reserved span{color:#9a6b27}.harmat-agent-property-sold{opacity:.7}.harmat-agent-property-sold span{color:#687178}
        .harmat-agent-rule-list{display:grid;gap:12px}.harmat-agent-rule-list span{display:grid;gap:6px;padding:16px;border-radius:14px;background:#fffaf3;border:1px solid #ead8b8}.harmat-agent-rule-list strong{color:#9a6b27;font-size:13px;letter-spacing:.08em}.harmat-agent-rule-list b{color:#253137;font-size:22px}.harmat-agent-rule-list small{color:#6f7780;line-height:1.55}
        @media(max-width:900px){.harmat-agent-shell{width:min(100% - 20px,720px);padding-top:14px}.harmat-agent-hero,.harmat-agent-panel-head{display:grid}.harmat-agent-hero h1{font-size:30px}.harmat-agent-nav a:last-child{margin-left:0}.harmat-agent-kpis,.harmat-agent-grid,.harmat-agent-form,.harmat-agent-lead-stats{grid-template-columns:1fr}.harmat-agent-panel{padding:16px}.harmat-agent-property-list{max-height:none}.harmat-agent-task-preview a{grid-template-columns:1fr}}
        ';
    }

    private function customer_portal_css() {
        return '
        *{box-sizing:border-box}body.harmat-customer-body{margin:0;background:#fbf4e7;color:#253137;font-family:Montserrat,Arial,sans-serif}
        .harmat-customer-shell{width:min(1180px,calc(100% - 32px));margin:0 auto;padding:28px 0 44px}
        .harmat-customer-hero{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:18px;padding:28px 30px;border:1px solid #ead8b8;border-radius:22px;background:linear-gradient(135deg,#fffaf1,#fff);box-shadow:0 18px 45px rgba(70,54,28,.08)}
        .harmat-customer-eyebrow{margin:0 0 8px;color:#a5742c;font-size:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.harmat-customer-hero h1{margin:0;color:#253137;font-family:Georgia,"Times New Roman",serif;font-size:38px;font-weight:500}.harmat-customer-hero p{margin:8px 0 0;color:#687178}
        .harmat-customer-user{display:flex;align-items:center;gap:14px;padding:10px 12px;border-radius:999px;background:#fff;border:1px solid #ead8b8}.harmat-customer-user span{font-weight:900}.harmat-customer-user a{color:#a5742c;font-weight:900;text-decoration:none}.harmat-portal-mini-lang{display:flex;gap:6px;align-items:center}.harmat-portal-mini-lang a{display:inline-flex!important;align-items:center;justify-content:center;min-height:30px;padding:0 9px;border-radius:999px;border:1px solid #ead8b8;color:#a5742c!important;background:#fffaf3;text-decoration:none;font-size:12px;font-weight:900}.harmat-portal-mini-lang a.is-active{background:#a8762d;color:#fff!important;border-color:#a8762d}
        .harmat-customer-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:0 0 18px}.harmat-customer-kpis article,.harmat-customer-panel{padding:20px;border-radius:20px;background:#fff;border:1px solid #ead8b8;box-shadow:0 14px 34px rgba(70,54,28,.06)}.harmat-customer-kpis small{display:block;color:#a5742c;font-weight:900;letter-spacing:.08em}.harmat-customer-kpis strong{display:block;margin-top:8px;color:#253137;font-size:24px;line-height:1.15;overflow-wrap:anywhere}
        .harmat-customer-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-bottom:18px}.harmat-customer-panel{margin-bottom:18px}.harmat-customer-panel h2{margin:0 0 14px;color:#253137;font-family:Georgia,"Times New Roman",serif;font-size:27px;font-weight:500}.harmat-customer-panel p{margin:0 0 14px;color:#687178;line-height:1.65}.harmat-customer-panel dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:0}.harmat-customer-panel div{padding:12px;border-radius:12px;background:#fffaf3;border:1px solid #ead8b8}.harmat-customer-panel dt{color:#a5742c;font-size:12px;font-weight:900;letter-spacing:.06em}.harmat-customer-panel dd{margin:6px 0 0;color:#253137;font-weight:800;overflow-wrap:anywhere}.harmat-customer-panel a{color:#a8762d;font-weight:900;text-decoration:none}.harmat-customer-material-list{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(240px,1fr))!important;gap:12px!important;padding:0!important;border:0!important;background:transparent!important}.harmat-customer-material-list article{display:grid;gap:8px;padding:14px;border-radius:14px;background:#fffaf3;border:1px solid #ead8b8}.harmat-customer-material-list strong{color:#253137}.harmat-customer-material-list small{color:#6f7780}.harmat-customer-material-list p{margin:0;color:#5d6670;line-height:1.55}.harmat-customer-material-list a{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 12px;border-radius:10px;background:#a8762d;color:#fff;text-decoration:none}
        @media(max-width:900px){.harmat-customer-shell{width:min(100% - 20px,720px);padding-top:14px}.harmat-customer-hero{display:grid}.harmat-customer-hero h1{font-size:31px}.harmat-customer-kpis,.harmat-customer-grid,.harmat-customer-panel dl{grid-template-columns:1fr}.harmat-customer-panel{padding:16px}}
        ';
    }

    private function css() {
        return '
        .harmat-sales-wrap { max-width: 1440px; }
        .harmat-admin-links { margin:16px 0 20px; padding:18px; border:1px solid #e7d6b8; border-radius:12px; background:#fffaf0; box-shadow:0 8px 24px rgba(45,38,25,.04); }
        .harmat-admin-links-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-end; margin-bottom:14px; }
        .harmat-admin-links h2 { margin:0; color:#1d2327; }
        .harmat-admin-links p { margin:4px 0 0; color:#667085; }
        .harmat-admin-link-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px; }
        .harmat-admin-link-card { display:grid; gap:7px; padding:14px; border:1px solid #ead8b8; border-radius:10px; background:#fff; text-decoration:none; box-shadow:0 8px 18px rgba(45,38,25,.035); }
        .harmat-admin-link-card strong { color:#1d2327; font-size:14px; }
        .harmat-admin-link-card code { display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#a5742c; background:#fbf6ea; padding:6px 8px; border-radius:6px; }
        .harmat-admin-link-card span { color:#667085; font-size:12px; }
        .harmat-summary { display:grid; grid-template-columns: repeat(4, minmax(140px, 1fr)); gap:14px; margin:18px 0 22px; }
        .harmat-summary span { background:linear-gradient(135deg,#fff,#fbf6ea); border:1px solid #e7d6b8; border-radius:10px; padding:16px 18px; font-size:14px; box-shadow:0 8px 24px rgba(45,38,25,.06); }
        .harmat-summary b { display:block; margin-top:8px; color:#1d2327; font-size:30px; line-height:1; }
        .harmat-filter { display:flex; gap:10px; align-items:center; margin:16px 0 20px; padding:14px; background:#fff; border:1px solid #dcdcde; border-radius:10px; }
        .harmat-filter input[type="search"] { min-width:280px; }
        .harmat-bulk-tools { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin:0 0 22px; padding:16px; background:#fffaf0; border:1px solid #e7d6b8; border-radius:12px; box-shadow:0 8px 24px rgba(45,38,25,.05); }
        .harmat-bulk-tools label { display:grid; gap:5px; color:#4b5563; font-weight:700; }
        .harmat-bulk-tools select { min-width:160px; }
        .harmat-bulk-tools .description { flex-basis:100%; margin:0; color:#667085; }
        .harmat-bulk-check { display:flex !important; grid-template-columns:none !important; align-items:center; gap:8px; min-height:32px; }
        .harmat-property-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap:16px; }
        .harmat-property-card { background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:16px; box-shadow:0 8px 24px rgba(0,0,0,.045); border-top:4px solid #2f8f5b; }
        .harmat-card-select { display:inline-flex; align-items:center; gap:6px; margin-bottom:10px; color:#667085; font-weight:700; }
        .harmat-property-card.harmat-status-reserved { border-top-color:#c48a2c; background:#fffaf0; }
        .harmat-property-card.harmat-status-sold { border-top-color:#667085; background:#f7f7f7; opacity:.88; }
        .harmat-card-top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:14px; }
        .harmat-unit-title { font-size:20px; font-weight:800; text-decoration:none; }
        .harmat-card-top p { margin:5px 0 0; color:#667085; }
        .harmat-status-badge { display:inline-flex; align-items:center; min-height:28px; padding:0 10px; border-radius:999px; background:#eef6f0; color:#226846; font-weight:700; white-space:nowrap; }
        .harmat-status-reserved .harmat-status-badge { background:#fff1cc; color:#8a5a18; }
        .harmat-status-sold .harmat-status-badge { background:#e5e7eb; color:#475467; }
        .harmat-card-metrics { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; margin-bottom:14px; }
        .harmat-card-metrics span, .harmat-readonly span { display:block; background:#f8fafc; border:1px solid #eef0f2; border-radius:8px; padding:10px; }
        .harmat-card-metrics small, .harmat-readonly small, .harmat-card-fields label { display:block; color:#667085; font-size:12px; font-weight:700; margin-bottom:5px; }
        .harmat-card-metrics b, .harmat-readonly b { color:#1d2327; font-size:14px; }
        .harmat-card-fields { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .harmat-card-fields input, .harmat-card-fields select, .harmat-card-fields textarea { width:100%; }
        .harmat-note-field { grid-column:1 / -1; }
        .harmat-card-fields textarea { resize:vertical; min-height:58px; }
        .harmat-card-footer { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-top:14px; padding-top:12px; border-top:1px solid #eef0f2; color:#667085; font-size:12px; }
        .harmat-card { max-width:760px; margin:18px 0 24px; padding:20px; background:#fff; border:1px solid #dcdcde; }
        .harmat-account-form { display:grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap:16px; }
        .harmat-account-form label { display:grid; gap:6px; font-weight:600; }
        .harmat-account-form .button { justify-self:start; align-self:end; }
        .harmat-password-notice code { font-size:16px; padding:4px 8px; }
        .harmat-inquiry-toolbar { display:flex; justify-content:space-between; gap:16px; align-items:flex-end; margin:18px 0; padding:18px; background:#fff; border:1px solid #dcdcde; border-radius:12px; }
        .harmat-inquiry-toolbar h2 { margin:0; }
        .harmat-inquiry-toolbar p { margin:5px 0 0; color:#667085; }
        .harmat-inquiry-search { display:flex; gap:8px; align-items:center; }
        .harmat-inquiry-search input[type="search"] { min-width:260px; }
        .harmat-inquiry-summary { display:grid; grid-template-columns:repeat(3,minmax(140px,1fr)); gap:12px; margin:0 0 16px; }
        .harmat-inquiry-summary span { padding:14px 16px; background:#fffaf0; border:1px solid #e7d6b8; border-radius:10px; color:#667085; font-weight:700; }
        .harmat-inquiry-summary b { display:block; margin-top:6px; color:#1d2327; font-size:20px; }
        .harmat-inquiry-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(420px,1fr)); gap:16px; }
        .harmat-inquiry-card { background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:16px; box-shadow:0 8px 24px rgba(0,0,0,.045); border-top:4px solid #a8762d; }
        .harmat-inquiry-card header { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:14px; }
        .harmat-inquiry-card h3 { margin:0; font-size:20px; }
        .harmat-inquiry-card header p { margin:5px 0 0; color:#667085; }
        .harmat-inquiry-card header span { display:inline-flex; min-height:28px; align-items:center; padding:0 10px; border-radius:999px; background:#eef6f0; color:#226846; font-weight:800; white-space:nowrap; }
        .harmat-inquiry-details { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin:0 0 12px; }
        .harmat-inquiry-details div { display:block; background:#f8fafc; border:1px solid #eef0f2; border-radius:8px; padding:10px; }
        .harmat-inquiry-details dt { color:#667085; font-size:12px; font-weight:700; margin-bottom:5px; }
        .harmat-inquiry-details dd { margin:0; color:#1d2327; font-weight:700; word-break:break-word; }
        .harmat-inquiry-details small { display:block; margin-top:4px; color:#667085; font-weight:400; }
        .harmat-inquiry-message { margin:12px 0; padding:12px; border:1px solid #e7d6b8; border-radius:10px; background:#fffaf0; }
        .harmat-inquiry-message strong { display:block; margin-bottom:6px; color:#a5742c; }
        .harmat-inquiry-message p { margin:0; color:#344054; }
        .harmat-inquiry-card footer { display:flex; flex-wrap:wrap; gap:8px; padding-top:12px; border-top:1px solid #eef0f2; }
        .harmat-inline-form { display:inline-block; margin:0 6px 0 0; }
        .harmat-lead-summary { display:grid; grid-template-columns:repeat(4, minmax(140px, 1fr)); gap:14px; margin:18px 0 22px; }
        .harmat-lead-summary span { background:linear-gradient(135deg,#fff,#f5fbf7); border:1px solid #d7eadf; border-radius:10px; padding:16px 18px; font-size:14px; box-shadow:0 8px 24px rgba(45,38,25,.06); }
        .harmat-lead-summary b { display:block; margin-top:8px; color:#1d2327; font-size:30px; line-height:1; }
        .harmat-leads-layout { display:grid; grid-template-columns:minmax(320px, 420px) minmax(0, 1fr); gap:18px; align-items:start; }
        .harmat-lead-editor { max-width:none; margin-top:0; border-radius:12px; box-shadow:0 8px 24px rgba(45,38,25,.05); }
        .harmat-lead-form { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:14px; }
        .harmat-lead-form label { display:grid; gap:6px; font-weight:700; color:#4b5563; }
        .harmat-lead-form input, .harmat-lead-form select, .harmat-lead-form textarea { width:100%; }
        .harmat-lead-note, .harmat-lead-actions { grid-column:1 / -1; }
        .harmat-lead-actions { display:flex; gap:10px; align-items:center; }
        .harmat-lead-list h2 { margin-top:0; }
        .harmat-lead-table-wrap { overflow:auto; border:1px solid #dcdcde; border-radius:12px; background:#fff; box-shadow:0 8px 24px rgba(45,38,25,.045); }
        .harmat-lead-table { min-width:1180px; border:0; }
        .harmat-lead-table th { padding:12px 14px; color:#7a541f; font-size:12px; letter-spacing:.06em; text-transform:uppercase; white-space:nowrap; }
        .harmat-lead-table td { padding:13px 14px; vertical-align:top; color:#1d2327; }
        .harmat-lead-table td strong, .harmat-lead-table td span { display:block; }
        .harmat-lead-table td small { display:block; margin-top:4px; color:#667085; overflow-wrap:anywhere; }
        .harmat-lead-customer strong { font-size:15px; }
        .harmat-lead-status-pill, .harmat-lead-protection { display:inline-flex !important; width:max-content; max-width:180px; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:800; line-height:1.25; white-space:normal; }
        .harmat-lead-status-pill { background:#eef6f0; color:#226846; }
        .harmat-lead-status-contacted { background:#edf6ff; color:#145f94; }
        .harmat-lead-status-visited { background:#fff6e5; color:#8a5a18; }
        .harmat-lead-status-reserved { background:#fff1cc; color:#8a5a18; }
        .harmat-lead-status-closed { background:#eef8f1; color:#1f7a4d; }
        .harmat-lead-status-lost { background:#e5e7eb; color:#475467; }
        .harmat-lead-protection-active { background:#fff2cf; color:#8a5a18; }
        .harmat-lead-protection-expired { background:#eceff1; color:#687178; }
        .harmat-lead-table-note { max-width:260px; color:#475467; overflow-wrap:anywhere; }
        .harmat-lead-table-actions { min-width:160px; }
        .harmat-lead-table-actions .button { margin:0 5px 6px 0; }
        .harmat-lead-table-actions small { margin-top:2px; }
        .harmat-lead-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:14px; }
        .harmat-lead-card { background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:16px; border-top:4px solid #2f8f5b; box-shadow:0 8px 24px rgba(0,0,0,.045); }
        .harmat-lead-card.harmat-lead-contacted { border-top-color:#2271b1; }
        .harmat-lead-card.harmat-lead-visited { border-top-color:#8a5a18; }
        .harmat-lead-card.harmat-lead-reserved { border-top-color:#c48a2c; background:#fffaf0; }
        .harmat-lead-card.harmat-lead-closed { border-top-color:#1f7a4d; background:#f6fbf7; }
        .harmat-lead-card.harmat-lead-lost { border-top-color:#667085; opacity:.9; }
        .harmat-lead-card-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
        .harmat-lead-card-head h3 { margin:0; font-size:18px; }
        .harmat-lead-card-head p { margin:5px 0 0; color:#667085; }
        .harmat-lead-card-head span { display:inline-flex; align-items:center; min-height:28px; padding:0 10px; border-radius:999px; background:#eef6f0; color:#226846; font-weight:700; white-space:nowrap; }
        .harmat-lead-card dl { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; margin:14px 0; }
        .harmat-lead-card dt { color:#667085; font-size:12px; font-weight:700; margin-bottom:4px; }
        .harmat-lead-card dd { margin:0; color:#1d2327; font-weight:600; overflow-wrap:anywhere; }
        .harmat-lead-note-preview { margin:12px 0; padding:10px 12px; border-radius:8px; background:#f8fafc; color:#475467; }
        .harmat-lead-card-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; padding-top:12px; border-top:1px solid #eef0f2; }
        .harmat-lead-card-actions small { margin-left:auto; color:#667085; }
        .harmat-empty-state { padding:22px; background:#fff; border:1px dashed #d0d5dd; border-radius:12px; color:#667085; }
        @media (max-width: 900px) {
            .harmat-summary, .harmat-account-form, .harmat-property-grid, .harmat-lead-summary, .harmat-leads-layout, .harmat-lead-form { grid-template-columns:1fr; }
            .harmat-filter, .harmat-bulk-tools { display:grid; }
            .harmat-card-footer { align-items:flex-start; flex-direction:column; }
            .harmat-lead-card dl { grid-template-columns:1fr; }
            .harmat-lead-card-actions small { margin-left:0; flex-basis:100%; }
        }';
    }

    private function js() {
        return '
        (function(){
            function formatMoney(value) {
                if (!isFinite(value) || value < 0) {
                    value = 0;
                }
                return Math.round(value).toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g, " ");
            }

            function updateCard(input) {
                var card = input.closest(".harmat-property-card");
                if (!card) {
                    return;
                }
                var rawPrice = (input.value || "").replace(/[^0-9]/g, "");
                var price = rawPrice ? parseInt(rawPrice, 10) : 0;
                var area = parseFloat(input.getAttribute("data-sales-area") || "0");
                var total = card.querySelector(".harmat-card-total-price");
                var sqm = card.querySelector(".harmat-card-sqm-price");

                if (total) {
                    total.textContent = formatMoney(price) + " HUF";
                }
                if (sqm) {
                    sqm.textContent = formatMoney(area > 0 ? price / area : 0) + " HUF/m²";
                }
            }

            document.addEventListener("input", function(event){
                if (event.target && event.target.classList.contains("harmat-price-input")) {
                    updateCard(event.target);
                }
            });
            document.addEventListener("change", function(event){
                if (event.target && event.target.id === "harmat-select-all") {
                    document.querySelectorAll("input[name=\\"bulk_property_ids[]\\"]").forEach(function(box){
                        box.checked = event.target.checked;
                    });
                }
            });
            document.addEventListener("submit", function(event){
                var form = event.target;
                if (!form || form.id !== "harmat-bulk-form") return;
                var selected = document.querySelectorAll("input[name=\\"bulk_property_ids[]\\"]:checked").length;
                if (!selected) {
                    event.preventDefault();
                    alert("请先勾选需要批量修改的房源。");
                    return;
                }
                if (!confirm("确认批量修改 " + selected + " 套房源吗？")) {
                    event.preventDefault();
                }
            });
        })();
        ';
    }

    private function frontend_css() {
        return '
        .harmat-front-card .property_loop { position:relative; overflow:hidden; border-radius:8px; }
        body.home .epl-premium-filter-wrapper,
        body.home .opalestate-search-properties,
        body.home .property-search-form,
        body.home .property-search,
        body.home .search-properties,
        body.home .opal-property-search,
        body.home .osf-property-search,
        body.home .elementor-widget-opal-property-search,
        body.home .elementor-widget-maisonco-property-search,
        body.home .elementor-element-a00bce3,
        body.home .harmat-front-property-filter,
        body.home .harmat-front-status-filter,
        body.page-id-6208 .epl-premium-filter-wrapper,
        body.page-id-6208 .opalestate-search-properties,
        body.page-id-6208 .property-search-form,
        body.page-id-6208 .property-search,
        body.page-id-6208 .search-properties,
        body.page-id-6208 .opal-property-search,
        body.page-id-6208 .osf-property-search,
        body.page-id-6208 .elementor-widget-opal-property-search,
        body.page-id-6208 .elementor-widget-maisonco-property-search { display:none !important; }
        .harmat-front-card .elementor-widget-heading.elementor-absolute { display:none !important; }
        .harmat-front-card.harmat-front-sold { filter:grayscale(.22); opacity:.82; }
        .harmat-front-badge, .harmat-front-price-chip { position:absolute; z-index:8; display:inline-flex; align-items:center; border-radius:999px; box-shadow:0 12px 28px rgba(20,26,31,.18); backdrop-filter:blur(10px); }
        .harmat-front-badge { top:14px; left:14px; min-height:32px; padding:0 12px; color:#fff; background:#1f7a4d; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
	        .harmat-front-price-chip { top:14px; right:14px; flex-direction:column; align-items:flex-end; gap:2px; padding:8px 12px; color:#283137; background:rgba(255,250,240,.94); border:1px solid rgba(166,113,39,.35); font-size:11px; font-weight:700; line-height:1.15; }
	        .harmat-front-price-chip strong { color:#a36c1e; font-size:15px; line-height:1.15; white-space:nowrap; }
	        .harmat-front-price-chip small { color:#5f6468; font-size:11px; white-space:nowrap; }
	        .harmat-front-card .harmat-front-price-chip.harmat-front-price-in-body { position:relative; inset:auto; display:block; width:calc(100% - 48px); margin:10px 24px 0; padding:12px 14px; border-radius:6px; background:#fffaf2; box-shadow:none; backdrop-filter:none; text-align:left; }
	        .harmat-front-card .harmat-front-price-chip.harmat-front-price-in-body::before { content:"Árinformáció"; display:block; margin-bottom:5px; color:#a1712b; font-family:Montserrat, Arial, sans-serif; font-size:10px; font-weight:800; letter-spacing:.08em; line-height:1.2; text-transform:uppercase; }
	        .harmat-front-card .harmat-front-price-chip.harmat-front-price-in-body strong { display:inline-block; color:#3f4448; font-size:17px; font-weight:800; }
	        .harmat-front-card .harmat-front-price-chip.harmat-front-price-in-body small { display:inline-block; margin-left:10px; color:#8f8f8f; font-size:12px; }
	        .harmat-front-card .harmat-front-area-bar { display:grid !important; grid-template-columns:repeat(2, minmax(0, 1fr)) !important; gap:0 !important; width:calc(100% - 48px) !important; margin:18px 24px 0 !important; padding:0 !important; border:1px solid rgba(152,112,51,.18) !important; border-radius:6px !important; background:rgba(255,250,242,.72) !important; overflow:hidden !important; }
	        .harmat-front-card .harmat-front-area-bar > div { display:block !important; min-width:0 !important; margin:0 !important; padding:12px 14px 11px !important; border:0 !important; background:transparent !important; text-align:left !important; }
	        .harmat-front-card .harmat-front-area-bar > div + div { border-left:1px solid rgba(152,112,51,.16) !important; }
	        .harmat-front-card .harmat-front-area-bar > div:nth-child(n+3) { display:none !important; }
	        .harmat-front-card .harmat-front-area-bar h6 { display:block !important; margin:0 0 6px !important; color:#a1712b !important; font-family:Marcellus SC, Georgia, serif !important; font-size:10px !important; line-height:1.15 !important; letter-spacing:.04em !important; white-space:nowrap !important; text-align:left !important; }
	        .harmat-front-card .harmat-front-area-bar p { display:block !important; margin:0 !important; color:#3f4448 !important; font-family:Montserrat, Arial, sans-serif !important; font-size:17px !important; font-weight:700 !important; line-height:1.15 !important; white-space:nowrap !important; text-align:left !important; }
	        .harmat-front-reserved .harmat-front-badge { background:#b77a24; }
        .harmat-front-sold .harmat-front-badge { background:#69727d; }
        .harmat-front-sold .elementor-button { pointer-events:none; opacity:.64; }
        .harmat-front-property-filter { width:min(1120px, calc(100% - 32px)); margin:18px auto 30px; padding:28px 30px; display:grid; grid-template-columns:1fr auto; gap:22px 28px; align-items:end; border:1px solid rgba(152,112,51,.18); border-radius:2px; background:linear-gradient(135deg, rgba(255,252,246,.96), rgba(250,242,228,.9)); box-shadow:0 22px 52px rgba(42,35,23,.08); font-family:Montserrat, Arial, sans-serif; }
        .harmat-filter-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; grid-column:1 / -1; }
        .harmat-filter-title { margin:0; color:#263033; font-family:Marcellus SC, Georgia, serif; font-size:25px; font-weight:400; line-height:1; letter-spacing:.11em; text-transform:uppercase; }
        .harmat-filter-count { align-self:flex-start; min-height:34px; display:inline-flex; align-items:center; padding:0 13px; border-radius:999px; background:#fff; color:#987033; box-shadow:0 8px 22px rgba(42,35,23,.06); font-size:12px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; white-space:nowrap; }
        .harmat-front-status-filter { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .harmat-front-status-filter button { min-height:36px; padding:0 17px; border:1px solid rgba(152,112,51,.22); border-radius:999px; background:rgba(255,255,255,.72); color:#987033; box-shadow:0 8px 18px rgba(42,35,23,.04); font-size:12px; font-weight:800; letter-spacing:.055em; text-transform:uppercase; cursor:pointer; transition:all .18s ease; }
        .harmat-front-status-filter button:hover, .harmat-front-status-filter button.is-active { background:#987033; border-color:#987033; color:#fff; box-shadow:0 10px 24px rgba(152,112,51,.22); }
        .harmat-front-status-filter button[data-status="current"].is-active { background:#1f7a4d; border-color:#1f7a4d; }
        .harmat-front-status-filter button[data-status="reserved"].is-active { background:#b77a24; border-color:#b77a24; }
        .harmat-front-status-filter button[data-status="sold"].is-active { background:#69727d; border-color:#69727d; }
        .harmat-filter-fields { display:grid; grid-template-columns:minmax(190px, 1.25fr) repeat(3, minmax(120px, .8fr)) auto; gap:14px; align-items:end; grid-column:1 / -1; }
        .harmat-filter-field { display:grid; gap:8px; color:#987033; font-size:11px; font-weight:800; letter-spacing:.075em; text-transform:uppercase; }
        .harmat-filter-field input, .harmat-filter-field select { width:100%; min-height:44px; border:1px solid rgba(152,112,51,.16); border-radius:2px; background:rgba(255,255,255,.62); color:#263033; padding:0 13px; font-family:Montserrat, Arial, sans-serif; font-size:14px; outline:none; transition:border-color .18s ease, box-shadow .18s ease, background .18s ease; }
        .harmat-filter-field select { appearance:auto; }
        .harmat-filter-field input:focus, .harmat-filter-field select:focus { border-color:#987033; background:#fff; box-shadow:0 0 0 3px rgba(152,112,51,.1); }
        .harmat-filter-clear { min-height:44px; border:0; border-radius:2px; background:#4f4c49; color:#d2a567; padding:0 24px; font-size:12px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; cursor:pointer; box-shadow:0 12px 24px rgba(42,35,23,.12); transition:all .18s ease; }
        .harmat-filter-clear:hover { background:#987033; color:#fff; }
        .harmat-filter-empty { display:none; width:min(1120px, calc(100% - 32px)); margin:0 auto 24px; padding:20px; border:1px solid rgba(152,112,51,.2); background:#fffaf0; color:#5f6468; text-align:center; }
        .harmat-filter-empty.is-visible { display:block; }
        .harmat-front-card.is-harmat-hidden { display:none !important; }
        .harmat-pagination-hidden { display:none !important; }
        .harmat-front-single-title-panel { width:100%; display:grid; grid-template-columns:minmax(190px, 1.05fr) repeat(3, minmax(130px, .7fr)) auto; gap:20px; align-items:center; padding:18px 0 0; font-family:Marcellus SC, Georgia, serif; }
        .harmat-front-single-title-main small, .harmat-front-single-title-metric small { display:block; margin-bottom:7px; color:#987033; font-family:Marcellus SC, Georgia, serif; font-size:14px; font-weight:400; line-height:1.2; letter-spacing:0; text-transform:uppercase; }
        .harmat-front-single-title-main strong { display:block; color:#3f4448; font-family:Marcellus SC, Georgia, serif; font-size:34px; font-weight:400; line-height:1.05; letter-spacing:0; }
        .harmat-front-single-title-metric strong { display:block; color:#3f4448; font-family:Montserrat, Arial, sans-serif; font-size:18px; font-weight:600; line-height:1.2; letter-spacing:0; white-space:nowrap; text-transform:none; font-variant-numeric:tabular-nums; }
        .harmat-front-single-title-status { justify-self:end; display:inline-flex; align-items:center; justify-content:center; min-height:38px; padding:0 18px; border-radius:999px; color:#fff; background:#1f7a4d; font-family:Montserrat, Arial, sans-serif; font-size:13px; font-weight:900; letter-spacing:.05em; text-transform:uppercase; white-space:nowrap; }
        .harmat-front-single-title-panel.harmat-front-reserved .harmat-front-single-title-status { background:#b77a24; }
        .harmat-front-single-title-panel.harmat-front-sold .harmat-front-single-title-status { background:#69727d; }
        @media (max-width: 980px) { .harmat-front-single-title-panel { grid-template-columns:1fr 1fr; gap:16px 22px; } .harmat-front-single-title-main { grid-column:1 / -1; } .harmat-front-single-title-status { justify-self:start; } }
        @media (max-width: 900px) { .harmat-front-property-filter { grid-template-columns:1fr; padding:24px 20px; } .harmat-filter-head { align-items:flex-start; flex-direction:column; } .harmat-filter-fields { grid-template-columns:1fr 1fr; } }
        @media (max-width: 560px) {
            .harmat-front-property-filter { width:calc(100% - 20px); margin:12px auto 18px; padding:16px 14px; }
            .harmat-front-status-filter { gap:8px; }
            .harmat-front-status-filter button { flex:1 1 calc(50% - 8px); padding:0 10px; }
            .harmat-filter-title { font-size:20px; }
            .harmat-filter-fields { grid-template-columns:1fr; }
	            .harmat-front-price-chip { left:14px; right:auto; top:52px; align-items:flex-start; }
	            .harmat-front-card .harmat-front-price-chip.harmat-front-price-in-body { width:calc(100% - 48px); margin:10px 24px 0; padding:11px 12px; }
	            .harmat-front-card .harmat-front-price-chip.harmat-front-price-in-body strong { font-size:15px; }
	            .harmat-front-card .harmat-front-price-chip.harmat-front-price-in-body small { display:block; margin-left:0; margin-top:3px; }
	            .harmat-front-card .property_loop { border-radius:0; }
            .harmat-front-card .harmat-mobile-meta-main, .harmat-front-card .harmat-mobile-meta-area { display:grid !important; gap:14px 18px !important; padding-left:24px !important; padding-right:24px !important; text-align:left !important; }
            .harmat-front-card .harmat-mobile-meta-main { grid-template-columns:repeat(3, minmax(0, 1fr)) !important; padding-top:28px !important; }
            .harmat-front-card .harmat-mobile-meta-area { grid-template-columns:repeat(2, minmax(0, 1fr)) !important; padding-top:24px !important; }
            .harmat-front-card .harmat-mobile-meta-area > div:nth-child(1) { grid-column:1; grid-row:1; }
            .harmat-front-card .harmat-mobile-meta-area > div:nth-child(2) { grid-column:2; grid-row:1; }
            .harmat-front-card .harmat-mobile-meta-area > div:nth-child(3) { grid-column:1; grid-row:2; }
            .harmat-front-card .harmat-mobile-meta-area > div:nth-child(4) { grid-column:2; grid-row:2; }
            .harmat-front-card .harmat-mobile-meta-main > div, .harmat-front-card .harmat-mobile-meta-area > div { width:auto !important; min-width:0 !important; margin:0 !important; padding:0 !important; display:block !important; text-align:left !important; }
            .harmat-front-card .harmat-mobile-meta-main h6, .harmat-front-card .harmat-mobile-meta-area h6 { display:block !important; margin:0 !important; color:#987033 !important; font-family:Marcellus SC, Georgia, serif !important; font-size:16px !important; line-height:1.2 !important; text-align:left !important; white-space:normal !important; overflow-wrap:anywhere !important; }
            .harmat-front-card .harmat-mobile-meta-main p, .harmat-front-card .harmat-mobile-meta-area p { display:block !important; margin:0 !important; color:#9ea0a6 !important; font-family:Montserrat, Arial, sans-serif !important; font-size:14px !important; line-height:1.35 !important; text-align:left !important; white-space:normal !important; }
	            .harmat-front-card .harmat-mobile-meta-main > div:nth-child(n+4), .harmat-front-card .harmat-mobile-meta-area > div:nth-child(3), .harmat-front-card .harmat-mobile-meta-area > div:nth-child(4) { margin-top:14px !important; }
	            .harmat-front-card .harmat-front-area-bar { grid-template-columns:repeat(2, minmax(0, 1fr)) !important; gap:0 !important; width:calc(100% - 48px) !important; margin:18px 24px 0 !important; padding:0 !important; }
	            .harmat-front-card .harmat-front-area-bar > div { padding:10px 10px !important; }
	            .harmat-front-card .harmat-front-area-bar h6 { font-size:9px !important; }
	            .harmat-front-card .harmat-front-area-bar p { font-size:15px !important; }
            .harmat-front-card .elementor-button-wrapper, .harmat-front-card .more-link-wrap, .harmat-front-card .property-box-button { display:flex !important; justify-content:center !important; margin-top:22px !important; }
            .harmat-front-single-title-panel { grid-template-columns:1fr; gap:14px; }
            .harmat-front-single-title-main strong { font-size:30px; }
            .harmat-front-single-title-status { justify-self:start; }
            .harmat-front-single-title-metric strong { font-size:17px; }
        }
        ';
    }

    private function frontend_js() {
        return '
        (function(){
            var data = (window.harmatSalesFront && window.harmatSalesFront.items) || {};
            function money(value) {
                var number = parseInt(value, 10) || 0;
                return new Intl.NumberFormat("hu-HU").format(number) + " Ft";
            }
            function priceLabel(item) {
                return item && item.hidePrice ? "Ár egyeztetés alapján" : money(item ? item.price : 0);
            }
            function sqmLabel(item) {
                return item && item.hidePrice ? "Érdeklődjön árainkról" : money(item ? item.sqmPrice : 0) + " / m²";
            }
            function area(value) {
                var number = parseFloat(value) || 0;
                return number.toLocaleString("hu-HU", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " m²";
            }
            function itemClass(item) {
                return item.status === "sold" ? "harmat-front-sold" : (item.status === "reserved" ? "harmat-front-reserved" : "harmat-front-current");
            }
	            function applyCards() {
	                if (document.body && (document.body.classList.contains("single-property") || document.body.classList.contains("page-id-4683") || document.body.classList.contains("tax-tax_feature") || document.body.classList.contains("tax-location"))) {
	                    return;
	                }
	                Object.keys(data).forEach(function(id){
                    var item = data[id];
                    document.querySelectorAll(".post-" + id + ".property, .e-loop-item-" + id).forEach(function(card){
                        card.dataset.harmatItemId = id;
                        card.dataset.harmatStatus = item.status || "current";
                        card.dataset.harmatTitle = (item.title || "").toLowerCase();
                        card.dataset.harmatBuilding = item.building || "";
                        card.dataset.harmatFloor = item.floor || "";
                        card.dataset.harmatRooms = item.rooms || "";
                        card.dataset.harmatArea = parseFloat(item.area || item.salesArea || 0) || 0;
                        if (card.dataset.harmatFrontReady === "1") return;
                        card.dataset.harmatFrontReady = "1";
                        card.classList.add("harmat-front-card", itemClass(item));
                        card.querySelectorAll(".e-grid.e-con-full").forEach(function(grid){
                            var count = grid.children ? grid.children.length : 0;
                            if (count === 6) grid.classList.add("harmat-mobile-meta-main");
                            if (count === 4) grid.classList.add("harmat-mobile-meta-area");
                        });
                        var box = card.querySelector(".property_loop") || card;
                        if (!box.querySelector(".harmat-front-badge")) {
                            var badge = document.createElement("span");
                            badge.className = "harmat-front-badge";
                            badge.textContent = item.title + " · " + item.statusLabel;
                            box.appendChild(badge);
                        }
	                        if (!box.querySelector(".harmat-front-price-chip")) {
	                            var chip = document.createElement("span");
	                            chip.className = "harmat-front-price-chip";
                            if (item.hidePrice) {
                                chip.dataset.harmatHidePrice = "1";
                            }
	                            chip.innerHTML = "<strong>" + priceLabel(item) + "</strong><small>" + sqmLabel(item) + "</small>";
	                            box.appendChild(chip);
	                        }
	                        var priceChip = card.querySelector(".harmat-front-price-chip");
	                        var areaGrid = card.querySelector(".harmat-mobile-meta-area");
	                        if (areaGrid) {
	                            areaGrid.classList.add("harmat-front-area-bar");
	                        }
	                        if (priceChip && areaGrid && priceChip.dataset.harmatMovedPrice !== "1") {
	                            priceChip.dataset.harmatMovedPrice = "1";
	                            priceChip.classList.add("harmat-front-price-in-body");
	                            areaGrid.parentNode.insertBefore(priceChip, areaGrid.nextSibling);
	                        }
	                        if (item.status === "sold") {
                            card.querySelectorAll(".elementor-button-text").forEach(function(text){ text.textContent = "Eladva"; });
                        }
                    });
                });
            }
	            function applyPropertyFilters() {
	                if (!document.body) {
	                    return;
	                }
	                if (document.body.classList.contains("home")) {
                    document.querySelectorAll(".elementor-element-a00bce3, .harmat-front-property-filter, .harmat-front-status-filter").forEach(function(node){
                        node.remove();
                    });
                    return;
                }
	                if (!document.body.classList.contains("page-id-6208")) {
	                    return;
	                }
                if (document.querySelector(".harmat-front-property-filter")) return;
                var cards = Array.prototype.slice.call(document.querySelectorAll(".harmat-front-card"));
                if (cards.length < 4) return;
                var host = cards[0].parentElement;
                if (!host || !host.parentElement) return;
                var labels = [
                    ["all", "Mind"],
                    ["current", "El\\u00e9rhet\\u0151"],
                    ["reserved", "Foglalva"],
                    ["sold", "Eladva"]
                ];
                function unique(values) {
                    return values.filter(Boolean).filter(function(value, index, array){ return array.indexOf(value) === index; }).sort(function(a, b){
                        return String(a).localeCompare(String(b), "hu", { numeric:true });
                    });
                }
                var values = Object.keys(data).map(function(id){ return data[id]; });
                var buildings = unique(values.map(function(item){ return item.building; }));
                var floors = unique(values.map(function(item){ return item.floor; }));
                var rooms = unique(values.map(function(item){ return item.rooms; }));
                function options(list, placeholder) {
                    return "<option value=\\"\\">" + placeholder + "</option>" + list.map(function(value){
                        return "<option value=\\"" + String(value).replace(/"/g, "&quot;") + "\\">" + value + "</option>";
                    }).join("");
                }
                var panel = document.createElement("div");
                panel.className = "harmat-front-property-filter";
                panel.innerHTML =
                    "<div class=\\"harmat-filter-head\\"><h3 class=\\"harmat-filter-title\\">Lak\\u00e1skeres\\u0151</h3><span class=\\"harmat-filter-count\\"></span></div>" +
                    "<div class=\\"harmat-front-status-filter\\">" + labels.map(function(item){
                    return "<button type=\\"button\\" data-status=\\"" + item[0] + "\\">" + item[1] + "</button>";
                }).join("") + "</div>" +
                    "<div class=\\"harmat-filter-fields\\">" +
                    "<label class=\\"harmat-filter-field\\">Lak\\u00e1s sz\\u00e1ma<input type=\\"search\\" data-filter=\\"query\\" placeholder=\\"pl. A1-F-L1, 3 szoba, Fsz\\"></label>" +
                    "<label class=\\"harmat-filter-field\\">\\u00c9p\\u00fclet<select data-filter=\\"building\\">" + options(buildings, "Mind") + "</select></label>" +
                    "<label class=\\"harmat-filter-field\\">Emelet<select data-filter=\\"floor\\">" + options(floors, "Mind") + "</select></label>" +
                    "<label class=\\"harmat-filter-field\\">Szoba<select data-filter=\\"rooms\\">" + options(rooms, "Mind") + "</select></label>" +
                    "<button class=\\"harmat-filter-clear\\" type=\\"button\\">Alaphelyzet</button>" +
                    "</div>";
                var empty = document.createElement("div");
                empty.className = "harmat-filter-empty";
                empty.textContent = "Nincs a feltételeknek megfelelő lakás. Kérjük, módosítsa a szűrést.";
                host.parentElement.insertBefore(panel, host);
                host.parentElement.insertBefore(empty, host);
                var state = { status:"all", query:"", building:"", floor:"", rooms:"" };
                var countNode = panel.querySelector(".harmat-filter-count");
                var listingWrap = host.closest(".elementor-widget-loop-grid") || host.parentElement;
                var paginationNodes = listingWrap ? Array.prototype.slice.call(listingWrap.querySelectorAll(".elementor-pagination, .e-load-more-anchor, .page-numbers")) : [];
                function normalizeFilterText(value) {
                    return String(value || "")
                        .toLowerCase()
                        .normalize("NFD")
                        .replace(/[\\u0300-\\u036f]/g, "")
                        .replace(/\\s+/g, " ")
                        .trim();
                }
                function normalizeApartmentCode(value) {
                    return normalizeFilterText(value).replace(/[^a-z0-9]/g, "");
                }
                function queryMatches(card) {
                    if (!state.query) return true;
                    var queryText = normalizeFilterText(state.query);
                    var queryCode = normalizeApartmentCode(state.query);
                    var titleText = normalizeFilterText(card.dataset.harmatTitle || "");
                    var titleCode = normalizeApartmentCode(card.dataset.harmatTitle || "");
                    var buildingText = normalizeFilterText(card.dataset.harmatBuilding || "");
                    var floorText = normalizeFilterText(card.dataset.harmatFloor || "");
                    var roomsText = normalizeFilterText(card.dataset.harmatRooms || "");
                    var roomMatch = queryText.match(/^([1-5])(?:\\s*(szoba|szobas|room|rooms))?$/);

                    if (roomMatch) {
                        return roomsText === roomMatch[1];
                    }
                    if (/^(fsz|fs|foldszint|foldszinti)$/.test(queryText)) {
                        return floorText === "fsz" || floorText === "foldszint";
                    }
                    if (/^a[1-4]$/.test(queryText)) {
                        return buildingText === queryText;
                    }
                    return (queryCode && titleCode.indexOf(queryCode) !== -1) || titleText.indexOf(queryText) !== -1;
                }
                function applyFilter() {
                    panel.querySelectorAll("button[data-status]").forEach(function(button){
                        button.classList.toggle("is-active", button.getAttribute("data-status") === state.status);
                    });
                    var isFiltered = state.status !== "all" || !!state.query || !!state.building || !!state.floor || !!state.rooms;
                    var visibleCount = 0;
                    cards.forEach(function(card){
                        var visible =
                            (state.status === "all" || card.dataset.harmatStatus === state.status) &&
                            queryMatches(card) &&
                            (!state.building || card.dataset.harmatBuilding === state.building) &&
                            (!state.floor || card.dataset.harmatFloor === state.floor) &&
                            (!state.rooms || card.dataset.harmatRooms === state.rooms);
                        if (visible) visibleCount += 1;
                        card.classList.toggle("is-harmat-hidden", !visible);
                    });
                    countNode.textContent = visibleCount + " / " + cards.length + " lakás";
                    countNode.textContent = visibleCount + " tal\\u00e1lat";
                    empty.classList.toggle("is-visible", visibleCount === 0);
                    paginationNodes.forEach(function(node){
                        node.classList.toggle("harmat-pagination-hidden", isFiltered);
                    });
                }
                panel.addEventListener("click", function(event){
                    var button = event.target.closest("button[data-status]");
                    if (button) {
                        state.status = button.getAttribute("data-status");
                        applyFilter();
                        return;
                    }
                    if (event.target.closest(".harmat-filter-clear")) {
                        state = { status:"all", query:"", building:"", floor:"", rooms:"" };
                        panel.querySelectorAll("[data-filter]").forEach(function(field){ field.value = ""; });
                        applyFilter();
                    }
                });
                panel.addEventListener("input", function(event){
                    var field = event.target.closest("[data-filter]");
                    if (!field) return;
                    state[field.dataset.filter] = field.value.trim().toLowerCase();
                    if (field.dataset.filter !== "query") state[field.dataset.filter] = field.value;
                    applyFilter();
                });
                panel.addEventListener("change", function(event){
                    var field = event.target.closest("[data-filter]");
                    if (!field) return;
                    state[field.dataset.filter] = field.value;
                    applyFilter();
                });
                applyFilter();
            }
            function currentSingleItem() {
                var body = document.body;
                if (!body || !body.classList.contains("single-property")) return null;
                var match = (body.className || "").match(/postid-(\\d+)/);
                return match ? data[match[1]] : null;
            }
            function applySingle() {
                var item = currentSingleItem();
                if (!item || document.querySelector(".harmat-front-single-title-panel")) return;
                var headings = Array.prototype.slice.call(document.querySelectorAll("body.single-property .site-content .elementor[data-elementor-type=\\"wp-post\\"] .elementor-heading-title, body.single-property .site-content .elementor[data-elementor-type=\\"wp-post\\"] h1, body.single-property .site-content .elementor[data-elementor-type=\\"wp-post\\"] h2"));
                var title = headings.find(function(node){ return (node.textContent || "").trim() === item.title; });
                if (!title) return;
                var titleWidget = title.closest(".elementor-widget-heading") || title.parentElement;
                var titleWrap = titleWidget && titleWidget.parentElement;
                if (!titleWrap) return;
                Array.prototype.slice.call(titleWrap.children).forEach(function(child){
                    var text = (child.textContent || "").trim().toLowerCase();
                    if (text === item.title.toLowerCase() || text.indexOf("lak") === 0) child.style.display = "none";
                });
                var panel = document.createElement("div");
                panel.className = "harmat-front-single-title-panel " + itemClass(item);
                panel.innerHTML =
                    "<div class=\\"harmat-front-single-title-main\\"><small>LAK\\u00c1S SZ\\u00c1M</small><strong>" + item.title + "</strong></div>" +
                    "<div class=\\"harmat-front-single-title-metric\\"><small>Teljes \\u00e1r</small><strong>" + money(item.price) + "</strong></div>" +
                    "<div class=\\"harmat-front-single-title-metric\\"><small>Elad\\u00e1si ter\\u00fclet</small><strong>" + area(item.salesArea) + "</strong></div>" +
                    "<div class=\\"harmat-front-single-title-metric\\"><small>N\\u00e9gyzetm\\u00e9ter\\u00e1r</small><strong>" + money(item.sqmPrice) + "</strong></div>" +
                    "<span class=\\"harmat-front-single-title-status\\">" + item.statusLabel + "</span>";
                if (item.hidePrice) {
                    panel.querySelectorAll(".harmat-front-single-title-metric strong").forEach(function(node){
                        var label = node.parentElement && node.parentElement.querySelector("small") ? node.parentElement.querySelector("small").textContent : "";
                        if (label.indexOf("Teljes") !== -1) node.textContent = priceLabel(item);
                        if (label.indexOf("N") === 0) node.textContent = "Kérjen ajánlatot";
                    });
                }
                titleWrap.appendChild(panel);
            }
            function setField(name, value) {
                Array.prototype.slice.call(document.querySelectorAll("[name=\\"" + name + "\\"]")).forEach(function(field){
                    var next = value || "";
                    if (field.value === next) return;
                    field.value = next;
                    field.dispatchEvent(new Event("input", { bubbles:true }));
                    field.dispatchEvent(new Event("change", { bubbles:true }));
                });
            }
            function roomText(item) {
                var rooms = item.rooms || "";
                var bedrooms = item.bedrooms || "";
                if (rooms && bedrooms) return rooms + " szoba / " + bedrooms + " h\\u00e1l\\u00f3";
                if (rooms) return rooms + " szoba";
                return "";
            }
            function fillInquiryForm(item) {
                if (!item) return;
                setField("selected-building", item.building || "");
                setField("selected-floor", item.floor || "");
                setField("selected-apartment", item.title || "");
                setField("selected-area", area(item.salesArea));
                setField("selected-rooms", roomText(item));
                setField("selected-price", money(item.price));
                setField("selected-url", item.url || window.location.href);
                setField("selected-price", priceLabel(item));

                document.querySelectorAll(".harmat-apt-info").forEach(function(info){
                    info.textContent = item.title + " · " + area(item.salesArea) + " · " + money(item.price);
                });

                if (item.hidePrice) {
                    document.querySelectorAll(".harmat-apt-info").forEach(function(info){
                        info.textContent = item.title + " · " + area(item.salesArea) + " · " + priceLabel(item);
                    });
                }

                var message = "A " + item.title + " lak\\u00e1s ir\\u00e1nt \\u00e9rdekl\\u0151d\\u00f6m.";
                var targets = Array.prototype.slice.call(document.querySelectorAll("textarea[name=\\"your-message\\"], textarea.wpcf7-textarea"));
                targets.forEach(function(field){
                    if (!field || (field.value && field.value.indexOf(item.title) !== -1)) return;
                    field.value = field.value ? field.value + "\\n" + message : message;
                    field.dispatchEvent(new Event("input", { bubbles:true }));
                    field.dispatchEvent(new Event("change", { bubbles:true }));
                });
            }
            function bindInquiryActions() {
                var item = currentSingleItem();
                if (!item) return;
                document.querySelectorAll("body.single-property a[href^=\\"#opal-contactform-popup\\"], body.single-property .opal-button-contact7 a, body.single-property a.opal-button-contact7").forEach(function(link){
                    if (link.dataset.harmatBound === "1") return;
                    link.dataset.harmatBound = "1";
                    link.addEventListener("click", function(){
                        window.harmatPendingInquiryItem = item;
                        setTimeout(function(){ fillInquiryForm(item); }, 120);
                        setTimeout(function(){ fillInquiryForm(item); }, 420);
                        setTimeout(function(){ fillInquiryForm(item); }, 900);
                    });
                });
                if (document.body.dataset.harmatGlobalInquiryBound !== "1") {
                    document.body.dataset.harmatGlobalInquiryBound = "1";
                    document.addEventListener("click", function(event){
                        var link = event.target && event.target.closest ? event.target.closest("a[href^=\\"#opal-contactform-popup\\"]") : null;
                        if (!link || !document.body.classList.contains("single-property")) return;
                        var current = currentSingleItem();
                        if (!current) return;
                        window.harmatPendingInquiryItem = current;
                        fillInquiryForm(current);
                        setTimeout(function(){ fillInquiryForm(current); }, 80);
                        setTimeout(function(){ fillInquiryForm(current); }, 300);
                        setTimeout(function(){ fillInquiryForm(current); }, 750);
                    }, true);
                }
                document.querySelectorAll("body.single-property form.wpcf7-form").forEach(function(form){
                    if (form.dataset.harmatSubmitBound === "1") return;
                    form.dataset.harmatSubmitBound = "1";
                    form.addEventListener("submit", function(){ fillInquiryForm(currentSingleItem()); }, true);
                });
                if (window.harmatPendingInquiryItem) fillInquiryForm(window.harmatPendingInquiryItem);
            }
            function syncOfferData() {
                if (!Array.isArray(window.harmatOfferApartments)) return;
                window.harmatOfferApartments.forEach(function(apt){
                    var item = data[apt.id];
                    if (!item) return;
                    apt.price = item.price;
                    apt.status = item.status;
                    apt.statusLabel = item.statusLabel;
                });
            }
            function run() {
                syncOfferData();
                applyCards();
                applyPropertyFilters();
                applySingle();
                bindInquiryActions();
            }
            var scheduled = false;
            function scheduleRun() {
                if (scheduled) return;
                scheduled = true;
                window.setTimeout(function(){
                    scheduled = false;
                    run();
                }, 160);
            }
            if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", run); else run();
            if ("MutationObserver" in window) new MutationObserver(scheduleRun).observe(document.documentElement, { childList:true, subtree:true });
        })();
        ';
    }
}

$GLOBALS['harmat_sales_manager'] = new Harmat_Sales_Manager();
