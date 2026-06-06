<?php
/**
 * Plugin Name: Harmat jogi dokumentumok
 * Plugin URI: https://harmat22.hu
 * Description: Védett ügyvédi dokumentumtár a Harmat22 értékesítési ügyeihez és lakásaihoz kapcsolva.
 * Version: 0.1.3
 * Author: Harmat22 Maintenance
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Harmat_Legal_Documents {
    const VERSION = '0.1.3';
    const ROLE_LAWYER = 'harmat_lawyer';
    const CAP_VIEW = 'harmat_view_legal_documents';
    const CAP_UPLOAD = 'harmat_upload_legal_documents';
    const CAP_MANAGE = 'harmat_manage_legal_documents';
    const OPTION_DOCS = 'harmat_legal_documents_v1';
    const OPTION_AUDIT = 'harmat_legal_audit_v1';
    const OPTION_CASES = 'harmat_legal_cases_v1';
    const MAX_FILE_SIZE = 52428800;
    const LEGAL_REMINDER_HOOK = 'harmat_legal_daily_task_reminder';

    public function __construct() {
        add_action('init', array($this, 'ensure_roles'), 4);
        add_action('init', array($this, 'register_rewrites'));
        add_action('init', array($this, 'schedule_daily_task_reminder'));
        add_action('init', array($this, 'handle_actions'), 20);
        add_action('init', array($this, 'handle_download'), 21);
        add_action(self::LEGAL_REMINDER_HOOK, array($this, 'send_daily_task_reminder_email'));
        add_action('send_headers', array($this, 'send_private_headers'), 0);
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('template_redirect', array($this, 'maybe_start_sales_nav_buffer'), 0);
        add_action('template_redirect', array($this, 'handle_sales_legal_view'), 1);
        add_action('template_redirect', array($this, 'handle_lawyer_portal'), 1);
    }

    public static function activate() {
        $instance = new self();
        $instance->ensure_roles();
        $instance->register_rewrites();
        $instance->ensure_private_directory();
        flush_rewrite_rules();
        self::schedule_task_reminder_event();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::LEGAL_REMINDER_HOOK);
        flush_rewrite_rules();
    }

    public function register_rewrites() {
        add_rewrite_rule('^lawyer/?$', 'index.php?harmat_lawyer_portal=1', 'top');
    }

    public function register_query_vars($vars) {
        $vars[] = 'harmat_lawyer_portal';
        return $vars;
    }

    public function ensure_roles() {
        if (!get_role(self::ROLE_LAWYER)) {
            add_role(self::ROLE_LAWYER, 'Harmat ügyvéd', array(
                'read' => true,
                self::CAP_VIEW => true,
                self::CAP_UPLOAD => true,
            ));
        }

        $lawyer = get_role(self::ROLE_LAWYER);
        if ($lawyer) {
            $lawyer->add_cap('read');
            $lawyer->add_cap(self::CAP_VIEW);
            $lawyer->add_cap(self::CAP_UPLOAD);
        }

        foreach (array('administrator', 'harmat_sales_manager') as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->add_cap(self::CAP_VIEW);
                $role->add_cap(self::CAP_UPLOAD);
                $role->add_cap(self::CAP_MANAGE);
            }
        }
    }

    private function request_path() {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        return trim((string) parse_url($path, PHP_URL_PATH), '/');
    }

    private function is_lawyer_path() {
        return get_query_var('harmat_lawyer_portal') || $this->request_path() === 'lawyer';
    }

    private function is_sales_path() {
        return $this->request_path() === 'sales';
    }

    private function is_sales_legal_view() {
        return $this->is_sales_path() && isset($_GET['view']) && sanitize_key(wp_unslash($_GET['view'])) === 'legal';
    }

    public function send_private_headers() {
        if ($this->is_lawyer_path() || $this->is_sales_legal_view() || isset($_GET['harmat_legal_download'])) {
            nocache_headers();
            header('X-Robots-Tag: noindex, nofollow', true);
        }
    }

    private static function next_reminder_timestamp($hour = 8) {
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $timezone);
        $target = $now->setTime((int) $hour, 0, 0);
        if ($target <= $now) {
            $target = $target->modify('+1 day');
        }
        return $target->getTimestamp();
    }

    public static function schedule_task_reminder_event() {
        if (!wp_next_scheduled(self::LEGAL_REMINDER_HOOK)) {
            wp_schedule_event(self::next_reminder_timestamp(8), 'daily', self::LEGAL_REMINDER_HOOK);
        }
    }

    public function schedule_daily_task_reminder() {
        self::schedule_task_reminder_event();
    }

    public function maybe_start_sales_nav_buffer() {
        if (is_admin() || wp_doing_ajax() || !$this->is_sales_path() || $this->is_sales_legal_view()) {
            return;
        }
        if (!is_user_logged_in() || !current_user_can(self::CAP_MANAGE)) {
            return;
        }
        ob_start(array($this, 'inject_sales_nav_link'));
    }

    public function inject_sales_nav_link($html) {
        if (!is_string($html) || strpos($html, 'harmat-sales-nav') === false || strpos($html, 'view=legal') !== false) {
            return $html;
        }

        $link = '<a href="' . esc_url($this->sales_legal_url()) . '">Ügyvédi dokumentumok</a>';
        $updated = preg_replace('~(<a href="[^"]*/sales-admin/[^"]*"[^>]*>.*?</a>)~s', $link . '$1', $html, 1);
        if (is_string($updated) && $updated !== $html) {
            return $updated;
        }
        return str_replace('</nav>', $link . '</nav>', $html);
    }

    public function handle_lawyer_portal() {
        if (!$this->is_lawyer_path()) {
            return;
        }
        $this->handle_portal_login('lawyer');
        if (!is_user_logged_in()) {
            $this->render_login('lawyer');
            exit;
        }
        if (!current_user_can(self::CAP_VIEW)) {
            $this->render_forbidden(home_url('/lawyer/'));
            exit;
        }
        $this->render_lawyer_portal();
        exit;
    }

    public function handle_sales_legal_view() {
        if (!$this->is_sales_legal_view()) {
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }
        if (!current_user_can(self::CAP_MANAGE)) {
            $this->render_forbidden(home_url('/sales/'));
            exit;
        }
        $this->render_sales_legal_portal();
        exit;
    }

    public function handle_actions() {
        if (empty($_POST['harmat_legal_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['harmat_legal_action']));
        if ($action === 'upload_document') {
            $this->handle_upload_document();
        } elseif ($action === 'delete_document') {
            $this->handle_delete_document();
        } elseif ($action === 'create_lawyer_user') {
            $this->handle_create_lawyer_user();
        } elseif ($action === 'update_case') {
            $this->handle_update_case();
        }
    }

    private function handle_upload_document() {
        if (!current_user_can(self::CAP_UPLOAD)) {
            wp_die('Nincs jogosultság jogi dokumentum feltöltéséhez.');
        }
        check_admin_referer('harmat_legal_upload_document');

        $context = $this->posted_context();
        $deal_id = isset($_POST['deal_id']) ? absint($_POST['deal_id']) : 0;
        $property_id = isset($_POST['property_id']) ? absint($_POST['property_id']) : 0;
        $deal = $deal_id ? $this->get_deal($deal_id) : array();
        if ($deal && !empty($deal['property_id'])) {
            $property_id = (int) $deal['property_id'];
        }
        $return_url = $this->context_url($context, $this->selection_args($deal_id, $property_id));

        if (empty($_FILES['legal_document']) || !is_array($_FILES['legal_document']) || empty($_FILES['legal_document']['name'])) {
            $this->redirect_with_error($return_url, 'Kérjük, válasszon ki egy feltöltendő fájlt.');
        }

        $file = $_FILES['legal_document'];
        if (!empty($file['size']) && (int) $file['size'] > self::MAX_FILE_SIZE) {
            $this->redirect_with_error($return_url, 'A fájl mérete legfeljebb 50 MB lehet.');
        }
        if (!empty($file['error'])) {
            $this->redirect_with_error($return_url, 'A feltöltés sikertelen. Kérjük, válassza ki újra a fájlt.');
        }

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $this->allowed_mimes());
        if (empty($check['type'])) {
            $this->redirect_with_error($return_url, 'Nem támogatott fájltípus. Használható: PDF, kép, Word, Excel, TXT vagy ZIP.');
        }

        $client_name = isset($_POST['client_name']) ? sanitize_text_field(wp_unslash($_POST['client_name'])) : '';
        $buyer_id_note = isset($_POST['buyer_id_note']) ? sanitize_text_field(wp_unslash($_POST['buyer_id_note'])) : '';
        $apartment_code = isset($_POST['apartment_code']) ? sanitize_text_field(wp_unslash($_POST['apartment_code'])) : '';
        $category = isset($_POST['category']) ? sanitize_key(wp_unslash($_POST['category'])) : 'other';
        $title = isset($_POST['document_title']) ? sanitize_text_field(wp_unslash($_POST['document_title'])) : '';
        $note = isset($_POST['document_note']) ? sanitize_textarea_field(wp_unslash($_POST['document_note'])) : '';

        if (!isset($this->categories()[$category])) {
            $category = 'other';
        }
        if ($deal && $client_name === '') {
            $client_name = (string) ($deal['client_name'] ?? '');
        }
        if ($apartment_code === '' && $property_id) {
            $apartment_code = get_the_title($property_id);
        }

        $original_name = sanitize_file_name(wp_basename($file['name']));
        if ($title === '') {
            $title = preg_replace('~\.[^.]+$~', '', $original_name);
        }

        $stored = $this->store_private_file($file, $check['type']);
        if (is_wp_error($stored)) {
            $this->redirect_with_error($return_url, $stored->get_error_message());
        }

        $docs = $this->get_documents(true);
        $id = $this->next_document_id($docs);
        $now = current_time('mysql');
        $docs[$id] = array(
            'id' => $id,
            'property_id' => $property_id,
            'deal_id' => $deal_id,
            'client_name' => $client_name,
            'buyer_id_note' => $buyer_id_note,
            'apartment_code' => $apartment_code,
            'category' => $category,
            'title' => $title,
            'note' => $note,
            'original_name' => $original_name,
            'stored_name' => $stored['stored_name'],
            'relative_path' => $stored['relative_path'],
            'mime_type' => $check['type'],
            'size' => (int) $stored['size'],
            'sha256' => $stored['sha256'],
            'download_key' => wp_generate_password(24, false, false),
            'uploaded_at' => $now,
            'uploaded_by' => get_current_user_id(),
            'deleted_at' => '',
            'deleted_by' => 0,
        );
        $this->save_documents($docs);
        $this->add_audit('upload', $id, $title, $client_name, $apartment_code);

        wp_safe_redirect(add_query_arg('legal_uploaded', '1', $return_url));
        exit;
    }

    private function handle_delete_document() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság jogi dokumentum törléséhez.');
        }
        $doc_id = isset($_POST['document_id']) ? absint($_POST['document_id']) : 0;
        check_admin_referer('harmat_legal_delete_document_' . $doc_id);

        $context = $this->posted_context();
        $deal_id = isset($_POST['deal_id']) ? absint($_POST['deal_id']) : 0;
        $property_id = isset($_POST['property_id']) ? absint($_POST['property_id']) : 0;
        $return_url = $this->context_url($context, $this->selection_args($deal_id, $property_id));
        $docs = $this->get_documents(true);

        if (!$doc_id || empty($docs[$doc_id]) || !empty($docs[$doc_id]['deleted_at'])) {
            $this->redirect_with_error($return_url, 'A jogi dokumentum nem található.');
        }

        $doc = $docs[$doc_id];
        $path = $this->absolute_private_path($doc['relative_path']);
        if ($path && file_exists($path)) {
            @unlink($path);
        }
        $docs[$doc_id]['deleted_at'] = current_time('mysql');
        $docs[$doc_id]['deleted_by'] = get_current_user_id();
        $this->save_documents($docs);
        $this->add_audit('delete', $doc_id, $doc['title'] ?? '', $doc['client_name'] ?? '', $doc['apartment_code'] ?? '');

        wp_safe_redirect(add_query_arg('legal_deleted', '1', $return_url));
        exit;
    }

    private function handle_create_lawyer_user() {
        if (!current_user_can(self::CAP_MANAGE)) {
            wp_die('Nincs jogosultság ügyvédi felhasználó létrehozásához.');
        }
        check_admin_referer('harmat_legal_create_lawyer_user');

        $return_url = $this->context_url('sales');
        $email = isset($_POST['lawyer_email']) ? sanitize_email(wp_unslash($_POST['lawyer_email'])) : '';
        $name = isset($_POST['lawyer_name']) ? sanitize_text_field(wp_unslash($_POST['lawyer_name'])) : '';
        $login = isset($_POST['lawyer_login']) ? sanitize_user(wp_unslash($_POST['lawyer_login']), true) : '';

        if (!$email || !is_email($email)) {
            $this->redirect_with_error($return_url, 'Kérjük, adjon meg érvényes ügyvédi e-mail címet.');
        }
        if ($login === '') {
            $parts = explode('@', $email);
            $login = sanitize_user($parts[0], true);
        }
        if (username_exists($login)) {
            $login .= '_' . wp_generate_password(4, false, false);
        }
        if (email_exists($email)) {
            $this->redirect_with_error($return_url, 'Ehhez az e-mail címhez már tartozik felhasználói fiók.');
        }

        $password = wp_generate_password(14, true, false);
        $user_id = wp_insert_user(array(
            'user_login' => $login,
            'user_email' => $email,
            'display_name' => $name ?: $login,
            'user_pass' => $password,
            'role' => self::ROLE_LAWYER,
        ));
        if (is_wp_error($user_id)) {
            $this->redirect_with_error($return_url, 'Az ügyvédi fiók létrehozása sikertelen: ' . $user_id->get_error_message());
        }

        set_transient('harmat_legal_lawyer_created_' . get_current_user_id(), array(
            'login' => $login,
            'email' => $email,
            'password' => $password,
            'portal' => home_url('/lawyer/'),
        ), 10 * MINUTE_IN_SECONDS);
        $this->add_audit('create_lawyer', 0, $login, $email, '');

        wp_safe_redirect(add_query_arg('lawyer_created', '1', $return_url));
        exit;
    }

    private function handle_update_case() {
        if (!current_user_can(self::CAP_UPLOAD)) {
            wp_die('Nincs jogosultság az ügyvédi ügy módosításához.');
        }
        check_admin_referer('harmat_legal_update_case');

        $context = $this->posted_context();
        $deal_id = isset($_POST['deal_id']) ? absint($_POST['deal_id']) : 0;
        $property_id = isset($_POST['property_id']) ? absint($_POST['property_id']) : 0;
        $deal = $deal_id ? $this->get_deal($deal_id) : array();
        if ($deal && !empty($deal['property_id'])) {
            $property_id = (int) $deal['property_id'];
        }
        if (!$deal && $property_id) {
            $deal = $this->get_deal_for_property($property_id);
            if ($deal && !empty($deal['id'])) {
                $deal_id = (int) $deal['id'];
            }
        }

        $return_url = $this->context_url($context, $this->selection_args($deal_id, $property_id));
        $target = $this->make_target($property_id, $deal);
        $case_key = $this->case_key_from_ids($deal_id, $property_id);
        if (!$case_key || empty($target['property_id'])) {
            $this->redirect_with_error($return_url, 'Az ügy módosítása előtt válasszon ki egy lakást.');
        }

        $case_status = isset($_POST['case_status']) ? sanitize_key(wp_unslash($_POST['case_status'])) : '';
        if (!isset($this->case_statuses()[$case_status])) {
            $case_status = 'new_reservation';
        }

        $deadline = isset($_POST['next_deadline']) ? sanitize_text_field(wp_unslash($_POST['next_deadline'])) : '';
        if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            $deadline = '';
        }

        $posted_items = isset($_POST['checklist']) && is_array($_POST['checklist']) ? wp_unslash($_POST['checklist']) : array();
        $checklist = array();
        foreach ($this->checklist_items() as $key => $label) {
            $value = isset($posted_items[$key]) ? sanitize_key($posted_items[$key]) : 'pending';
            if (!isset($this->checklist_statuses()[$value])) {
                $value = 'pending';
            }
            $checklist[$key] = $value;
        }

        $missing_note = isset($_POST['missing_note']) ? sanitize_textarea_field(wp_unslash($_POST['missing_note'])) : '';
        $case_note = isset($_POST['case_note']) ? sanitize_textarea_field(wp_unslash($_POST['case_note'])) : '';

        $cases = $this->get_cases(true);
        $cases[$case_key] = array(
            'case_key' => $case_key,
            'deal_id' => $deal_id,
            'property_id' => $property_id,
            'case_status' => $case_status,
            'checklist' => $checklist,
            'missing_note' => $missing_note,
            'case_note' => $case_note,
            'next_deadline' => $deadline,
            'updated_at' => current_time('mysql'),
            'updated_by' => get_current_user_id(),
        );
        $this->save_cases($cases);
        $this->add_audit('update_case', 0, $this->case_status_label($case_status), $target['client_name'] ?? '', $target['title'] ?? '');

        wp_safe_redirect(add_query_arg('legal_case_updated', '1', $return_url));
        exit;
    }

    public function handle_download() {
        if (empty($_GET['harmat_legal_download'])) {
            return;
        }
        if (!is_user_logged_in() || !current_user_can(self::CAP_VIEW)) {
            status_header(403);
            exit('Hozzáférés megtagadva');
        }

        $doc_id = absint(wp_unslash($_GET['harmat_legal_download']));
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $docs = $this->get_documents();
        if (!$doc_id || empty($docs[$doc_id]) || !hash_equals((string) $docs[$doc_id]['download_key'], $key)) {
            status_header(404);
            exit('Nem található');
        }

        $doc = $docs[$doc_id];
        $path = $this->absolute_private_path($doc['relative_path']);
        if (!$path || !file_exists($path) || !is_readable($path)) {
            status_header(404);
            exit('A fájl nem található');
        }

        $this->add_audit('download', $doc_id, $doc['title'] ?? '', $doc['client_name'] ?? '', $doc['apartment_code'] ?? '');
        nocache_headers();
        header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . rawurlencode($doc['original_name'] ?: ('jogi-dokumentum-' . $doc_id)) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function store_private_file($file, $mime_type) {
        $base = $this->ensure_private_directory();
        if (!$base) {
            return new WP_Error('storage_missing', 'A védett jogi dokumentumtár nem hozható létre.');
        }

        $subdir = gmdate('Y/m');
        $target_dir = trailingslashit($base) . $subdir;
        if (!wp_mkdir_p($target_dir)) {
            return new WP_Error('storage_subdir_missing', 'A havi jogi dokumentum mappa nem hozható létre.');
        }

        $original_name = sanitize_file_name(wp_basename($file['name']));
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $stored_name = wp_generate_password(28, false, false) . ($ext ? '.' . strtolower($ext) : '');
        $target = trailingslashit($target_dir) . $stored_name;

        if (!is_uploaded_file($file['tmp_name']) || !@move_uploaded_file($file['tmp_name'], $target)) {
            return new WP_Error('move_failed', 'A fájl mentése sikertelen. Kérjük, próbálja újra.');
        }
        @chmod($target, 0640);

        return array(
            'stored_name' => $stored_name,
            'relative_path' => $subdir . '/' . $stored_name,
            'size' => filesize($target),
            'sha256' => hash_file('sha256', $target),
            'mime_type' => $mime_type,
        );
    }

    private function ensure_private_directory() {
        $upload = wp_upload_dir(null, false);
        if (empty($upload['basedir'])) {
            return '';
        }

        $base = trailingslashit($upload['basedir']) . 'harmat-legal-private';
        if (!wp_mkdir_p($base)) {
            return '';
        }
        $htaccess = trailingslashit($base) . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
        $index = trailingslashit($base) . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Védett dokumentumtár.\n");
        }
        return $base;
    }

    private function absolute_private_path($relative_path) {
        $relative_path = ltrim(str_replace(array('\\', '..'), array('/', ''), (string) $relative_path), '/');
        $base = $this->ensure_private_directory();
        if (!$base || $relative_path === '') {
            return '';
        }

        $path = trailingslashit($base) . $relative_path;
        $real_base = realpath($base);
        $real_path = file_exists($path) ? realpath($path) : $path;
        if (!$real_base || strpos(str_replace('\\', '/', $real_path), str_replace('\\', '/', $real_base)) !== 0) {
            return '';
        }
        return $path;
    }

    private function allowed_mimes() {
        return array(
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
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

    private function categories() {
        return array(
            'contract' => 'Szerződés',
            'identity' => 'Vevői azonosító',
            'payment' => 'Fizetési igazolás',
            'bank' => 'Hitel / bank',
            'land_registry' => 'Földhivatal',
            'company' => 'Céges dokumentum',
            'handover' => 'Birtokbaadás',
            'other' => 'Egyéb',
        );
    }

    private function get_documents($include_deleted = false) {
        $raw = get_option(self::OPTION_DOCS, array());
        if (!is_array($raw)) {
            return array();
        }

        $docs = array();
        foreach ($raw as $key => $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $id = !empty($doc['id']) ? absint($doc['id']) : absint($key);
            if (!$id) {
                continue;
            }
            $doc = array_merge(array(
                'id' => $id,
                'property_id' => 0,
                'deal_id' => 0,
                'client_name' => '',
                'buyer_id_note' => '',
                'apartment_code' => '',
                'category' => 'other',
                'title' => '',
                'note' => '',
                'original_name' => '',
                'stored_name' => '',
                'relative_path' => '',
                'mime_type' => '',
                'size' => 0,
                'sha256' => '',
                'download_key' => '',
                'uploaded_at' => '',
                'uploaded_by' => 0,
                'deleted_at' => '',
                'deleted_by' => 0,
            ), $doc);
            $doc['id'] = $id;
            if (!$include_deleted && !empty($doc['deleted_at'])) {
                continue;
            }
            $docs[$id] = $doc;
        }
        uasort($docs, function($a, $b) {
            return strcmp((string) ($b['uploaded_at'] ?? ''), (string) ($a['uploaded_at'] ?? ''));
        });
        return $docs;
    }

    private function save_documents($docs) {
        update_option(self::OPTION_DOCS, $docs, false);
    }

    private function next_document_id($docs) {
        $ids = array_map('absint', array_keys($docs));
        return $ids ? max($ids) + 1 : 1;
    }

    private function get_cases($include_empty = false) {
        $raw = get_option(self::OPTION_CASES, array());
        if (!is_array($raw)) {
            return array();
        }

        $cases = array();
        foreach ($raw as $key => $case) {
            if (!is_array($case)) {
                continue;
            }
            $case_key = sanitize_key($case['case_key'] ?? $key);
            if ($case_key === '') {
                continue;
            }
            $case = array_merge($this->case_defaults(), $case);
            $case['case_key'] = $case_key;
            $case['deal_id'] = absint($case['deal_id'] ?? 0);
            $case['property_id'] = absint($case['property_id'] ?? 0);
            $case['checklist'] = $this->normalize_checklist($case['checklist'] ?? array());
            if (!$include_empty && empty($case['updated_at'])) {
                continue;
            }
            $cases[$case_key] = $case;
        }
        return $cases;
    }

    private function save_cases($cases) {
        update_option(self::OPTION_CASES, $cases, false);
    }

    private function case_defaults() {
        return array(
            'case_key' => '',
            'deal_id' => 0,
            'property_id' => 0,
            'case_status' => 'new_reservation',
            'checklist' => $this->normalize_checklist(array()),
            'missing_note' => '',
            'case_note' => '',
            'next_deadline' => '',
            'updated_at' => '',
            'updated_by' => 0,
        );
    }

    private function case_key_from_ids($deal_id, $property_id) {
        $deal_id = absint($deal_id);
        $property_id = absint($property_id);
        if ($deal_id) {
            return 'd' . $deal_id;
        }
        if ($property_id) {
            return 'p' . $property_id;
        }
        return '';
    }

    private function case_key_for_target($target) {
        return $this->case_key_from_ids($target['deal_id'] ?? 0, $target['property_id'] ?? 0);
    }

    private function get_case_for_target($target) {
        $cases = $this->get_cases(true);
        $primary_key = $this->case_key_for_target($target);
        $property_key = !empty($target['property_id']) ? 'p' . (int) $target['property_id'] : '';
        $case = $this->case_defaults();
        if ($property_key && !empty($cases[$property_key])) {
            $case = array_merge($case, $cases[$property_key]);
        }
        if ($primary_key && !empty($cases[$primary_key])) {
            $case = array_merge($case, $cases[$primary_key]);
        }
        if ($primary_key) {
            $case['case_key'] = $primary_key;
        }
        $case['deal_id'] = absint($target['deal_id'] ?? $case['deal_id']);
        $case['property_id'] = absint($target['property_id'] ?? $case['property_id']);
        $case['checklist'] = $this->normalize_checklist($case['checklist'] ?? array());
        return $case;
    }

    private function normalize_checklist($items) {
        $items = is_array($items) ? $items : array();
        $normalized = array();
        foreach ($this->checklist_items() as $key => $label) {
            $value = isset($items[$key]) ? sanitize_key((string) $items[$key]) : 'pending';
            if (!isset($this->checklist_statuses()[$value])) {
                $value = 'pending';
            }
            $normalized[$key] = $value;
        }
        return $normalized;
    }

    private function case_statuses() {
        return array(
            'new_reservation' => 'Új foglalás',
            'documents_pending' => 'Dokumentumokra vár',
            'contract_draft' => 'Szerződéstervezet',
            'client_review' => 'Ügyfél jóváhagyás',
            'signing' => 'Aláírásra kész',
            'signed' => 'Aláírva',
            'registry_filing' => 'Földhivatali beadás',
            'completed' => 'Lezárva',
        );
    }

    private function checklist_items() {
        return array(
            'buyer_identity' => 'Vevői azonosító okmány',
            'address_card' => 'Lakcímkártya / lakcímadat',
            'tax_number' => 'Adóazonosító jel',
            'company_documents' => 'Céges dokumentumok',
            'deposit_proof' => 'Foglaló igazolása',
            'payment_plan' => 'Fizetési ütemezés',
            'sales_confirmation' => 'Értékesítési visszaigazolás',
            'contract_draft' => 'Szerződéstervezet',
            'signed_contract' => 'Aláírt szerződés',
        );
    }

    private function checklist_statuses() {
        return array(
            'pending' => 'Függőben',
            'missing' => 'Hiányzik',
            'received' => 'Beérkezett',
            'not_applicable' => 'Nem releváns',
        );
    }

    private function case_status_label($status) {
        $statuses = $this->case_statuses();
        return $statuses[$status] ?? ($status ?: 'Új foglalás');
    }

    private function case_missing_count($case) {
        $count = 0;
        foreach (($case['checklist'] ?? array()) as $value) {
            if ($value === 'missing') {
                $count++;
            }
        }
        return $count;
    }

    private function with_case_data($target) {
        $case = $this->get_case_for_target($target);
        $target['case'] = $case;
        $target['case_status'] = $case['case_status'] ?? 'new_reservation';
        $target['case_status_label'] = $this->case_status_label($target['case_status']);
        $target['missing_count'] = $this->case_missing_count($case);
        $target['missing_note'] = $case['missing_note'] ?? '';
        $target['next_deadline'] = $case['next_deadline'] ?? '';
        return $target;
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
            $deal['id'] = $id;
            $deals[$id] = $deal;
        }
        uasort($deals, function($a, $b) {
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });
        return $deals;
    }

    private function get_deal($deal_id) {
        $deals = $this->get_deals();
        return isset($deals[$deal_id]) ? $deals[$deal_id] : array();
    }

    private function get_deal_for_property($property_id) {
        $property_id = absint($property_id);
        if (!$property_id) {
            return array();
        }

        $fallback = array();
        foreach ($this->get_deals() as $deal) {
            if ((int) ($deal['property_id'] ?? 0) !== $property_id) {
                continue;
            }
            if (empty($fallback)) {
                $fallback = $deal;
            }
            if (($deal['stage'] ?? '') !== 'lost') {
                return $deal;
            }
        }
        return $fallback;
    }

    private function crm_code_for_deal($deal, $deal_id) {
        $deal_id = absint($deal_id);
        $fields = array('crm_code', 'crm_id', 'customer_code', 'client_code', 'lead_code', 'deal_code', 'code');
        foreach ($fields as $field) {
            if (!empty($deal[$field])) {
                return sanitize_text_field((string) $deal[$field]);
            }
        }
        if (!$deal_id) {
            return '';
        }
        $date = '';
        foreach (array('created_at', 'updated_at', 'closed_at', 'expected_close') as $field) {
            if (!empty($deal[$field])) {
                $timestamp = strtotime((string) $deal[$field]);
                if ($timestamp) {
                    $date = date('Ymd', $timestamp);
                    break;
                }
            }
        }
        if ($date === '') {
            $date = current_time('Ymd');
        }
        return 'CRM-' . $date . '-' . str_pad((string) $deal_id, 4, '0', STR_PAD_LEFT);
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

    private function selected_target() {
        $deal_id = isset($_GET['deal_id']) ? absint(wp_unslash($_GET['deal_id'])) : 0;
        $property_id = isset($_GET['property_id']) ? absint(wp_unslash($_GET['property_id'])) : 0;
        $deal = $deal_id ? $this->get_deal($deal_id) : array();
        if ($deal && !empty($deal['property_id'])) {
            $property_id = (int) $deal['property_id'];
        }
        if (!$deal && $property_id) {
            $deal = $this->get_deal_for_property($property_id);
            if ($deal && !empty($deal['id'])) {
                $deal_id = (int) $deal['id'];
            }
        }
        if (!$deal_id && !$property_id) {
            return array();
        }
        return $this->with_case_data($this->make_target($property_id, $deal));
    }

    private function make_target($property_id, $deal = array()) {
        $property_id = absint($property_id);
        $deal_id = !empty($deal['id']) ? absint($deal['id']) : 0;
        $property_title = $property_id ? get_the_title($property_id) : '';
        $sales_status = $property_id ? $this->property_sales_status($property_id) : '';

        return array(
            'property_id' => $property_id,
            'deal_id' => $deal_id,
            'title' => $property_title ?: ($deal_id ? ('Ügylet #' . $deal_id) : 'Kézi dokumentum'),
            'crm_code' => $this->crm_code_for_deal($deal, $deal_id),
            'building' => $this->building_from_title($property_title),
            'sales_status' => $sales_status,
            'sales_status_label' => $this->property_status_label($sales_status),
            'deal_stage' => $deal['stage'] ?? '',
            'deal_stage_label' => $this->deal_stage_label($deal['stage'] ?? ''),
            'client_name' => $deal['client_name'] ?? '',
            'phone' => $deal['phone'] ?? '',
            'email' => $deal['email'] ?? '',
            'amount' => $deal['amount'] ?? '',
            'deposit' => $deal['deposit'] ?? '',
            'payment_received' => $deal['payment_received'] ?? '',
            'payment_status' => $deal['payment_status'] ?? '',
            'payment_due_date' => $deal['payment_due_date'] ?? '',
            'contract_status' => $deal['contract_status'] ?? '',
            'updated_at' => $deal['updated_at'] ?? '',
        );
    }

    private function legal_targets() {
        $docs = $this->get_documents();
        $doc_counts = array();
        foreach ($docs as $doc) {
            if (!empty($doc['deal_id'])) {
                $key = 'd' . (int) $doc['deal_id'];
                if (!isset($doc_counts[$key])) {
                    $doc_counts[$key] = 0;
                }
                $doc_counts[$key]++;
            }
            if (!empty($doc['property_id'])) {
                $key = 'p' . (int) $doc['property_id'];
                if (!isset($doc_counts[$key])) {
                    $doc_counts[$key] = 0;
                }
                $doc_counts[$key]++;
            }
        }

        $targets = array();
        $properties_with_deal = array();
        foreach ($this->get_deals() as $deal) {
            $property_id = !empty($deal['property_id']) ? (int) $deal['property_id'] : 0;
            if ($property_id && isset($properties_with_deal[$property_id])) {
                continue;
            }
            if ($property_id && ($deal['stage'] ?? '') === 'lost') {
                continue;
            }
            $target = $this->make_target($property_id, $deal);
            $target = $this->with_case_data($target);
            $target['doc_count'] = $doc_counts['d' . (int) $deal['id']] ?? ($property_id ? ($doc_counts['p' . $property_id] ?? 0) : 0);
            $target['needs_lawyer'] = in_array($target['deal_stage'], array('reserved', 'contract', 'closed'), true) || $target['sales_status'] === 'reserved' || $target['sales_status'] === 'sold';
            if (!empty($target['missing_count']) || !empty($target['missing_note'])) {
                $target['needs_lawyer'] = true;
            }
            $targets['d' . (int) $deal['id']] = $target;
            if ($property_id) {
                $properties_with_deal[$property_id] = true;
            }
        }

        foreach ($this->get_properties() as $property) {
            $property_id = (int) $property->ID;
            $key = 'p' . $property_id;
            if (isset($targets[$key]) || isset($properties_with_deal[$property_id])) {
                continue;
            }
            $target = $this->make_target($property_id);
            $target = $this->with_case_data($target);
            $target['doc_count'] = $doc_counts[$key] ?? 0;
            $target['needs_lawyer'] = $target['sales_status'] === 'reserved' || $target['sales_status'] === 'sold' || $target['doc_count'] > 0;
            if (!empty($target['missing_count']) || !empty($target['missing_note'])) {
                $target['needs_lawyer'] = true;
            }
            $targets[$key] = $target;
        }

        uasort($targets, function($a, $b) {
            if ((int) $a['needs_lawyer'] !== (int) $b['needs_lawyer']) {
                return (int) $b['needs_lawyer'] - (int) $a['needs_lawyer'];
            }
            if ((int) !empty($a['missing_count']) !== (int) !empty($b['missing_count'])) {
                return (int) !empty($b['missing_count']) - (int) !empty($a['missing_count']);
            }
            return strnatcasecmp((string) $a['title'], (string) $b['title']);
        });

        return $targets;
    }

    private function documents_for_target($target) {
        $docs = $this->get_documents();
        if (!$target) {
            return $docs;
        }

        $filtered = array();
        foreach ($docs as $id => $doc) {
            if (!empty($target['deal_id']) && (int) $doc['deal_id'] === (int) $target['deal_id']) {
                $filtered[$id] = $doc;
                continue;
            }
            if (!empty($target['property_id']) && (int) $doc['property_id'] === (int) $target['property_id']) {
                $filtered[$id] = $doc;
            }
        }
        return $filtered;
    }

    private function property_sales_status($property_id) {
        $status = get_post_meta($property_id, 'property_status', true);
        $under_offer = get_post_meta($property_id, 'property_under_offer', true);
        if ($status === 'sold') {
            return 'sold';
        }
        if ($under_offer) {
            return 'reserved';
        }
        return 'current';
    }

    private function property_status_label($status) {
        $labels = array(
            'current' => 'Elérhető',
            'reserved' => 'Foglalt',
            'sold' => 'Eladva',
        );
        return $labels[$status] ?? '-';
    }

    private function deal_stage_label($stage) {
        $labels = array(
            'new' => 'Új érdeklődő',
            'contacted' => 'Kapcsolatfelvétel',
            'viewing' => 'Megtekintés',
            'negotiation' => 'Tárgyalás',
            'reserved' => 'Foglalva',
            'contract' => 'Szerződés folyamatban',
            'closed' => 'Lezárva',
            'lost' => 'Elveszett',
        );
        return $labels[$stage] ?? ($stage ?: '-');
    }

    private function payment_status_label($status) {
        $labels = array(
            'not_started' => 'Nem indult',
            'partial' => 'Részben fizetve',
            'paid' => 'Fizetve',
            'overdue' => 'Lejárt',
        );
        return $labels[$status] ?? ($status ?: '-');
    }

    private function contract_status_label($status) {
        $labels = array(
            'draft' => 'Tervezet',
            'review' => 'Ellenőrzés alatt',
            'signed' => 'Aláírva',
            'paid_deposit' => 'Foglaló befizetve',
            'handover_ready' => 'Birtokbaadásra kész',
            'handover_done' => 'Birtokbaadva',
        );
        return $labels[$status] ?? ($status ?: '-');
    }

    private function building_from_title($title) {
        if (preg_match('~^(A[1-4])~i', (string) $title, $m)) {
            return strtoupper($m[1]);
        }
        return 'Egyéb';
    }

    private function money_label($value) {
        $value = preg_replace('/[^\d]/', '', (string) $value);
        return $value !== '' ? number_format((float) $value, 0, '.', ' ') . ' HUF' : '-';
    }

    private function add_audit($action, $doc_id, $title, $client_name, $apartment_code) {
        $audit = get_option(self::OPTION_AUDIT, array());
        if (!is_array($audit)) {
            $audit = array();
        }
        array_unshift($audit, array(
            'action' => sanitize_key($action),
            'document_id' => absint($doc_id),
            'title' => sanitize_text_field((string) $title),
            'client_name' => sanitize_text_field((string) $client_name),
            'apartment_code' => sanitize_text_field((string) $apartment_code),
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        ));
        update_option(self::OPTION_AUDIT, array_slice($audit, 0, 400), false);
    }

    private function get_audit() {
        $audit = get_option(self::OPTION_AUDIT, array());
        return is_array($audit) ? array_slice($audit, 0, 80) : array();
    }

    private function audit_action_label($action) {
        $labels = array(
            'upload' => 'Feltöltés',
            'delete' => 'Törlés',
            'download' => 'Letöltés',
            'create_lawyer' => 'Ügyvédi fiók létrehozása',
            'update_case' => 'Ügyfolyamat frissítése',
        );
        return $labels[$action] ?? (string) $action;
    }

    private function posted_context() {
        $context = isset($_POST['return_context']) ? sanitize_key(wp_unslash($_POST['return_context'])) : 'lawyer';
        return $context === 'sales' ? 'sales' : 'lawyer';
    }

    private function context_url($context, $args = array()) {
        $base = $context === 'sales' ? $this->sales_legal_url() : home_url('/lawyer/');
        return add_query_arg($args, $base);
    }

    private function selection_args($deal_id = 0, $property_id = 0) {
        if ($deal_id) {
            return array('deal_id' => absint($deal_id));
        }
        if ($property_id) {
            return array('property_id' => absint($property_id));
        }
        return array();
    }

    private function sales_legal_url($args = array()) {
        return add_query_arg(array_merge(array('view' => 'legal'), $args), home_url('/sales/'));
    }

    private function handle_portal_login($context) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['harmat_legal_login'])) {
            return;
        }

        $context = $context === 'sales' ? 'sales' : 'lawyer';
        $nonce = isset($_POST['_hld_login_nonce']) ? sanitize_text_field(wp_unslash($_POST['_hld_login_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'harmat_legal_login_' . $context)) {
            $this->render_login($context, 'A bejelentkezési kérés lejárt. Kérjük, próbálja újra.');
            exit;
        }

        $login = isset($_POST['log']) ? trim(sanitize_text_field(wp_unslash($_POST['log']))) : '';
        $password = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';
        if ($login === '' || $password === '') {
            $this->render_login($context, 'Kérjük, adja meg a felhasználónevet és a jelszót.');
            exit;
        }

        $user = is_email($login) ? get_user_by('email', $login) : get_user_by('login', $login);
        if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
            $this->render_login($context, 'Hibás felhasználónév vagy jelszó.');
            exit;
        }

        $required_cap = $context === 'sales' ? self::CAP_MANAGE : self::CAP_VIEW;
        if (!user_can($user, $required_cap)) {
            wp_clear_auth_cookie();
            wp_set_current_user(0);
            $this->render_login($context, 'Nincs jogosultsága ehhez a portálhoz.');
            exit;
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, !empty($_POST['rememberme']), is_ssl());
        do_action('wp_login', $user->user_login, $user);

        wp_safe_redirect($context === 'sales' ? $this->sales_legal_url() : home_url('/lawyer/'));
        exit;
    }

    private function target_url($context, $target) {
        return $this->context_url($context, $this->selection_args($target['deal_id'] ?? 0, $target['property_id'] ?? 0));
    }

    private function redirect_with_error($return_url, $message) {
        set_transient('harmat_legal_error_' . get_current_user_id(), $message, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('legal_error', '1', $return_url));
        exit;
    }

    private function download_url($doc) {
        return add_query_arg(array(
            'harmat_legal_download' => (int) $doc['id'],
            'key' => $doc['download_key'],
        ), home_url('/lawyer/'));
    }

    private function file_size_label($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    private function render_login($context, $error = '') {
        $title = $context === 'sales' ? 'Harmat értékesítés - ügyvédi dokumentumok' : 'Harmat ügyvédi dokumentumtár';
        $redirect = $context === 'sales' ? $this->sales_legal_url() : home_url('/lawyer/');
        $posted_login = isset($_POST['log']) ? sanitize_user(wp_unslash($_POST['log'])) : '';
        nocache_headers();
        echo '<!doctype html><html lang="hu"><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html($title) . '</title><style>' . $this->base_css() . $this->workflow_css() . '</style></head>';
        echo '<body class="hld-body"><main class="hld-login"><section class="hld-panel"><p class="hld-eyebrow">Harmat Lakópark</p><h1>' . esc_html($title) . '</h1><p class="hld-muted">A védett ügyvédi dokumentumtár használatához bejelentkezés szükséges.</p>';
        if ($error !== '') {
            echo '<div class="hld-notice hld-error">' . esc_html($error) . '</div>';
        }
        echo '<form method="post" action="' . esc_url($redirect) . '" id="loginform">';
        echo wp_nonce_field('harmat_legal_login_' . ($context === 'sales' ? 'sales' : 'lawyer'), '_hld_login_nonce', true, false);
        echo '<input type="hidden" name="harmat_legal_login" value="1">';
        echo '<p><label for="hld_user_login">Felhasználónév vagy e-mail cím<input type="text" name="log" id="hld_user_login" autocomplete="username" value="' . esc_attr($posted_login) . '" required></label></p>';
        echo '<p><label for="hld_user_pass">Jelszó<input type="password" name="pwd" id="hld_user_pass" autocomplete="current-password" required></label></p>';
        echo '<p class="login-remember"><label><input name="rememberme" type="checkbox" value="forever"> Emlékezzen rám</label></p>';
        echo '<p><input type="submit" value="Bejelentkezés"></p>';
        echo '</form>';
        echo '</section></main></body></html>';
    }

    private function render_forbidden($return_url) {
        status_header(403);
        nocache_headers();
        echo '<!doctype html><html lang="hu"><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Hozzáférés szükséges</title><style>' . $this->base_css() . $this->workflow_css() . '</style></head><body class="hld-body"><main class="hld-login"><section class="hld-panel"><h1>Hozzáférés szükséges</h1><p class="hld-muted">Ez az oldal kizárólag jogosult Harmat felhasználók számára érhető el.</p><a class="hld-button" href="' . esc_url(wp_logout_url($return_url)) . '">Kilépés</a></section></main></body></html>';
    }

    private function render_lawyer_portal() {
        $user = wp_get_current_user();
        nocache_headers();
        echo '<!doctype html><html lang="hu"><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Harmat ügyvédi munkafelület</title><style>' . $this->base_css() . $this->workflow_css() . '</style></head>';
        echo '<body class="hld-body"><main class="hld-shell">';
        echo '<header class="hld-hero"><div><p class="hld-eyebrow">Harmat Lakópark</p><h1>Ügyvédi munkafelület</h1><p>Válasszon lakást, ellenőrizze a vevői és értékesítési adatokat, majd töltse fel az adott lakáshoz tartozó jogi dokumentumokat.</p></div><div class="hld-user"><span>' . esc_html($user->display_name ?: $user->user_login) . '</span><a href="' . esc_url(wp_logout_url(home_url('/lawyer/'))) . '">Kilépés</a></div></header>';
        if (current_user_can(self::CAP_MANAGE)) {
            echo '<nav class="hld-nav"><a href="' . esc_url($this->sales_legal_url()) . '">Értékesítési nézet</a><a class="is-active" href="' . esc_url(home_url('/lawyer/')) . '">Ügyvédi portál</a></nav>';
        }
        $this->render_notices('lawyer');
        $this->render_workspace('lawyer');
        echo '</main></body></html>';
    }

    private function render_sales_legal_portal() {
        $user = wp_get_current_user();
        nocache_headers();
        echo '<!doctype html><html lang="hu"><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Harmat ügyvédi dokumentumok</title><style>' . $this->base_css() . $this->workflow_css() . '</style></head>';
        echo '<body class="hld-body hld-sales-body"><main class="hld-shell">';
        echo '<header class="hld-hero"><div><p class="hld-eyebrow">Harmat Lakópark</p><h1>Ügyvédi dokumentumok</h1><p>Az értékesítés itt ellenőrizheti a foglalási és ügyletadatokat; az ügyvéd lakásonként látja a vevőt, az összeget és az ügy állapotát.</p></div><div class="hld-user"><span>' . esc_html($user->display_name ?: $user->user_login) . '</span><a href="' . esc_url(wp_logout_url($this->sales_legal_url())) . '">Kilépés</a></div></header>';
        $this->render_sales_nav('legal');
        $this->render_notices('sales');
        $this->render_workspace('sales');
        echo '</main></body></html>';
    }

    private function render_sales_nav($active) {
        $items = array(
            'dashboard' => 'Vezérlőpult',
            'tasks' => 'Feladatok',
            'inquiries' => 'Érdeklődések',
            'deals' => 'Ügyletek',
            'commissions' => 'Jutalékok',
            'payments' => 'Fizetések',
            'customers' => 'Vevők',
            'clients' => 'Érdeklődők',
            'brokers' => 'Közvetítők',
            'properties' => 'Lakáslista',
            'links' => 'Linkek',
            'legal' => 'Ügyvédi dokumentumok',
        );
        echo '<nav class="hld-nav">';
        foreach ($items as $view => $label) {
            $url = $view === 'dashboard' ? home_url('/sales/') : add_query_arg('view', $view, home_url('/sales/'));
            echo '<a class="' . ($active === $view ? 'is-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '<a href="' . esc_url(home_url('/sales-admin/')) . '" target="_blank" rel="noopener">WP tartalék</a>';
        echo '</nav>';
    }

    private function render_notices($context) {
        if (isset($_GET['legal_uploaded'])) {
            echo '<div class="hld-notice hld-success">A jogi dokumentum feltöltve és mentve.</div>';
        }
        if (isset($_GET['legal_deleted'])) {
            echo '<div class="hld-notice hld-success">A jogi dokumentum törölve.</div>';
        }
        if (isset($_GET['legal_case_updated'])) {
            echo '<div class="hld-notice hld-success">Az ügyvédi ügyfolyamat frissítve.</div>';
        }
        if (isset($_GET['legal_error'])) {
            $error = get_transient('harmat_legal_error_' . get_current_user_id());
            delete_transient('harmat_legal_error_' . get_current_user_id());
            echo '<div class="hld-notice hld-error">' . esc_html($error ?: 'A művelet sikertelen. Kérjük, ellenőrizze a fájlt és a jogosultságokat.') . '</div>';
        }
        if ($context === 'sales' && isset($_GET['lawyer_created'])) {
            $created = get_transient('harmat_legal_lawyer_created_' . get_current_user_id());
            delete_transient('harmat_legal_lawyer_created_' . get_current_user_id());
            if ($created) {
                echo '<div class="hld-notice hld-success"><strong>Az ügyvédi fiók létrejött. Kérjük, azonnal mentse a jelszót:</strong><span>Portál: <code>' . esc_html($created['portal']) . '</code></span><span>Felhasználónév: <code>' . esc_html($created['login']) . '</code></span><span>Jelszó: <code>' . esc_html($created['password']) . '</code></span><span>E-mail: <code>' . esc_html($created['email']) . '</code></span></div>';
            }
        }
    }

    private function render_workspace($context) {
        $target = $this->selected_target();
        if (!$target) {
            $this->render_legal_home($context);
            return;
        }

        echo '<section class="hld-grid">';
        echo '<div class="hld-main">';
        $this->render_target_overview($context, $target);
        $this->render_case_panel($context, $target);
        $this->render_upload_panel($context, $target);
        $this->render_documents_panel($context, $target);
        echo '</div>';
        echo '<aside class="hld-side">';
        $this->render_summary_panel($target);
        if ($context === 'sales') {
            $this->render_lawyer_accounts_panel();
            $this->render_audit_panel();
        } else {
            $this->render_lawyer_help_panel();
        }
        echo '</aside></section>';
    }

    private function legal_tasks() {
        $tasks = array();
        foreach ($this->legal_targets() as $target) {
            $has_missing = !empty($target['missing_count']) || !empty($target['missing_note']);
            if (!empty($target['next_deadline'])) {
                $tasks[] = array(
                    'date' => $target['next_deadline'],
                    'type' => 'Határidő',
                    'title' => $target['title'],
                    'url' => $this->target_url('lawyer', $target),
                );
            } elseif ($has_missing) {
                $tasks[] = array(
                    'date' => current_time('Y-m-d'),
                    'type' => 'Hiánypótlás',
                    'title' => $target['title'],
                    'url' => $this->target_url('lawyer', $target),
                );
            }
        }
        usort($tasks, function($a, $b) {
            return strcmp((string) $a['date'], (string) $b['date']);
        });
        return $tasks;
    }

    private function task_bucket_counts($tasks) {
        $counts = array('overdue' => 0, 'today' => 0, 'upcoming7' => 0);
        $today = strtotime(current_time('Y-m-d'));
        $week = strtotime('+7 days', $today);
        foreach ($tasks as $task) {
            $task_day = !empty($task['date']) ? strtotime($task['date']) : false;
            if (!$task_day) {
                continue;
            }
            if ($task_day < $today) {
                $counts['overdue']++;
            } elseif ($task_day === $today) {
                $counts['today']++;
            } elseif ($task_day <= $week) {
                $counts['upcoming7']++;
            }
        }
        return $counts;
    }

    private function render_legal_task_reminder_summary($context, $tasks) {
        $counts = $this->task_bucket_counts($tasks);
        echo '<section class="hld-panel hld-reminder-panel">';
        echo '<div class="hld-panel-head"><div><h2>Napi teendők</h2><p>A rendszer minden reggel e-mailben jelzi az aktuális ügyvédi határidőket és hiánypótlásokat.</p></div><a class="hld-button hld-button-light" href="' . esc_url($this->context_url($context)) . '">Részletek</a></div>';
        echo '<div class="hld-task-kpis">';
        echo '<span><small>Mai teendők</small><b>' . (int) $counts['today'] . '</b></span>';
        echo '<span><small>Lejárt</small><b>' . (int) $counts['overdue'] . '</b></span>';
        echo '<span><small>Következő 7 nap</small><b>' . (int) $counts['upcoming7'] . '</b></span>';
        echo '</div></section>';
    }

    private function legal_task_reminder_recipients() {
        $users = get_users(array('role' => self::ROLE_LAWYER, 'fields' => array('user_email')));
        $recipients = array();
        foreach ($users as $user) {
            if (!empty($user->user_email)) {
                $recipients[] = $user->user_email;
            }
        }
        $recipients = apply_filters('harmat_legal_task_reminder_recipients', $recipients);
        if (!is_array($recipients)) {
            $recipients = array($recipients);
        }
        $recipients = array_filter(array_map('sanitize_email', $recipients));
        return array_values(array_unique($recipients));
    }

    public function send_daily_task_reminder_email() {
        $tasks = $this->legal_tasks();
        $counts = $this->task_bucket_counts($tasks);
        if ((int) $counts['overdue'] + (int) $counts['today'] + (int) $counts['upcoming7'] === 0) {
            return;
        }

        $recipients = $this->legal_task_reminder_recipients();
        if (!$recipients) {
            return;
        }

        $subject = 'Harmat ügyvédi teendők - ' . current_time('Y-m-d');
        $body = array(
            'Harmat Lakópark ügyvédi teendők',
            '',
            'Mai teendők: ' . (int) $counts['today'],
            'Lejárt: ' . (int) $counts['overdue'],
            'Következő 7 nap: ' . (int) $counts['upcoming7'],
            '',
            'Részletek csak bejelentkezés után érhetők el:',
            home_url('/lawyer/'),
            '',
            'Adatvédelmi okból ez az e-mail nem tartalmaz vevői adatot, összeget vagy dokumentumot.',
        );
        wp_mail($recipients, $subject, implode("\n", $body));
    }

    private function render_legal_home($context) {
        $targets = $this->legal_targets();
        $tasks = $this->legal_tasks();
        $q = isset($_GET['legal_q']) ? sanitize_text_field(wp_unslash($_GET['legal_q'])) : '';
        $status = isset($_GET['legal_status']) ? sanitize_key(wp_unslash($_GET['legal_status'])) : '';
        $base = $context === 'sales' ? $this->sales_legal_url() : home_url('/lawyer/');

        $this->render_legal_task_reminder_summary($context, $tasks);

        echo '<section class="hld-grid">';
        echo '<div class="hld-main">';
        echo '<section class="hld-panel"><div class="hld-panel-head"><div><h2>Lakás áttekintő</h2><p>Itt indul az ügyintézés. A lakáskártyákon megjelenik az értékesítési állapot, a vevő, az összeg, a CRM azonosító, az ügyvédi ügy állapota és a hiányzó tételek száma.</p></div></div>';
        echo '<form method="get" class="hld-filter" action="' . esc_url($base) . '">';
        if ($context === 'sales') {
            echo '<input type="hidden" name="view" value="legal">';
        }
        echo '<input name="legal_q" value="' . esc_attr($q) . '" placeholder="Keresés lakás, CRM azonosító, vevő, telefon vagy e-mail alapján">';
        echo '<select name="legal_status"><option value="">Minden állapot</option><option value="needs"' . selected($status, 'needs', false) . '>Ügyvédi teendő</option><option value="missing"' . selected($status, 'missing', false) . '>Hiányzó tételek</option><option value="reserved"' . selected($status, 'reserved', false) . '>Foglalt</option><option value="sold"' . selected($status, 'sold', false) . '>Eladva</option><option value="current"' . selected($status, 'current', false) . '>Elérhető</option></select>';
        echo '<button>Szűrés</button></form>';

        $groups = array();
        foreach ($targets as $target) {
            if (!$this->target_matches_home_filter($target, $q, $status)) {
                continue;
            }
            $groups[$target['building']][] = $target;
        }

        if (!$groups) {
            echo '<div class="hld-empty">Nincs a szűrésnek megfelelő lakás.</div></section></div>';
        } else {
            foreach ($groups as $building => $items) {
                echo '<div class="hld-section-title"><h3>' . esc_html($building) . '</h3><span>' . esc_html((string) count($items)) . '</span></div>';
                echo '<div class="hld-apartment-grid">';
                foreach ($items as $target) {
                    $this->render_target_card($context, $target);
                }
                echo '</div>';
            }
            echo '</section></div>';
        }

        echo '<aside class="hld-side">';
        $this->render_summary_panel();
        if ($context === 'sales') {
            $this->render_lawyer_accounts_panel();
            $this->render_audit_panel();
        } else {
            $this->render_lawyer_help_panel();
        }
        echo '</aside></section>';
    }

    private function target_matches_home_filter($target, $q, $status) {
        if ($status === 'needs' && empty($target['needs_lawyer'])) {
            return false;
        }
        if ($status === 'missing' && empty($target['missing_count']) && empty($target['missing_note'])) {
            return false;
        }
        if (in_array($status, array('reserved', 'sold', 'current'), true) && $target['sales_status'] !== $status) {
            return false;
        }
        if ($q === '') {
            return true;
        }

        $haystack = implode(' ', array($target['title'], $target['crm_code'], $target['client_name'], $target['phone'], $target['email'], $target['deal_stage_label'], $target['sales_status_label'], $target['case_status_label'], $target['missing_note']));
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);
        $needle = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);
        return strpos($haystack, $needle) !== false;
    }

    private function render_target_card($context, $target) {
        $class = !empty($target['needs_lawyer']) ? ' is-priority' : '';
        if (!empty($target['missing_count']) || !empty($target['missing_note'])) {
            $class .= ' has-missing';
        }
        echo '<a class="hld-apartment-card' . esc_attr($class) . '" href="' . esc_url($this->target_url($context, $target)) . '">';
        echo '<span class="hld-card-top"><strong>' . esc_html($target['title']) . '</strong><em>' . esc_html($target['sales_status_label']) . '</em></span>';
        echo '<span><small>CRM azonosító</small><b>' . esc_html($target['crm_code'] ?: 'CRM azonosító még nincs') . '</b></span>';
        echo '<span><small>Vevő</small><b>' . esc_html($target['client_name'] ?: '-') . '</b></span>';
        echo '<span><small>Értékesítési szakasz</small><b>' . esc_html($target['deal_stage_label']) . '</b></span>';
        echo '<span><small>Ügyvédi ügy</small><b>' . esc_html($target['case_status_label'] ?? '-') . '</b></span>';
        echo '<span><small>Összeg</small><b>' . esc_html($this->money_label($target['amount'])) . '</b></span>';
        echo '<span><small>Hiányzó tételek</small><b>' . esc_html(!empty($target['missing_count']) ? ((string) $target['missing_count']) : '-') . '</b></span>';
        echo '<span><small>Dokumentumok</small><b>' . esc_html((string) ($target['doc_count'] ?? 0)) . '</b></span>';
        echo '</a>';
    }

    private function render_target_overview($context, $target) {
        echo '<section class="hld-panel"><div class="hld-panel-head"><div><h2>' . esc_html($target['title']) . '</h2><p>A vevői adatok és az értékesítési állapot az értékesítési rendszerből érkezik. Az ügy száma a CRM azonosító.</p></div><a class="hld-button hld-button-light" href="' . esc_url($this->context_url($context)) . '">Vissza a lakásokhoz</a></div>';
        echo '<div class="hld-target-kpis">';
        echo '<article><small>CRM azonosító</small><strong>' . esc_html($target['crm_code'] ?: 'CRM azonosító még nincs') . '</strong></article>';
        echo '<article><small>Értékesítési állapot</small><strong>' . esc_html($target['sales_status_label']) . '</strong></article>';
        echo '<article><small>Értékesítési szakasz</small><strong>' . esc_html($target['deal_stage_label']) . '</strong></article>';
        echo '<article><small>Vevő</small><strong>' . esc_html($target['client_name'] ?: '-') . '</strong></article>';
        echo '<article><small>Összeg</small><strong>' . esc_html($this->money_label($target['amount'])) . '</strong></article>';
        echo '<article><small>Foglaló</small><strong>' . esc_html($this->money_label($target['deposit'])) . '</strong></article>';
        echo '<article><small>Befizetve</small><strong>' . esc_html($this->money_label($target['payment_received'])) . '</strong></article>';
        echo '<article><small>Fizetési állapot</small><strong>' . esc_html($this->payment_status_label($target['payment_status'])) . '</strong></article>';
        echo '<article><small>Szerződés állapota</small><strong>' . esc_html($this->contract_status_label($target['contract_status'])) . '</strong></article>';
        echo '<article><small>Ügyvédi ügy</small><strong>' . esc_html($target['case_status_label'] ?? '-') . '</strong></article>';
        echo '<article><small>Hiányzó tételek</small><strong>' . esc_html(!empty($target['missing_count']) ? ((string) $target['missing_count']) : '-') . '</strong></article>';
        echo '</div>';
        echo '<div class="hld-buyer-strip"><span><small>Telefon</small><b>' . esc_html($target['phone'] ?: '-') . '</b></span><span><small>E-mail</small><b>' . esc_html($target['email'] ?: '-') . '</b></span><span><small>Utolsó értékesítési frissítés</small><b>' . esc_html($target['updated_at'] ?: '-') . '</b></span></div>';
        echo '</section>';
    }

    private function render_case_panel($context, $target) {
        $case = $target['case'] ?? $this->get_case_for_target($target);
        $checklist = $case['checklist'] ?? $this->normalize_checklist(array());
        echo '<section class="hld-panel"><div class="hld-panel-head"><div><h2>Ügyvédi ügyfolyamat</h2><p>Az ügy száma a CRM azonosító. Itt követhető az ügy állapota, a szükséges dokumentumlista és a hiánypótlási megjegyzés.</p></div></div>';
        echo '<form method="post" class="hld-case-form">';
        wp_nonce_field('harmat_legal_update_case');
        echo '<input type="hidden" name="harmat_legal_action" value="update_case">';
        echo '<input type="hidden" name="return_context" value="' . esc_attr($context) . '">';
        echo '<input type="hidden" name="deal_id" value="' . esc_attr((int) ($target['deal_id'] ?? 0)) . '">';
        echo '<input type="hidden" name="property_id" value="' . esc_attr((int) ($target['property_id'] ?? 0)) . '">';
        echo '<div class="hld-case-top">';
        echo '<label>Ügy állapota<select name="case_status">';
        foreach ($this->case_statuses() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($case['case_status'] ?? '', $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Következő határidő<input type="date" name="next_deadline" value="' . esc_attr($case['next_deadline'] ?? '') . '"></label>';
        echo '</div>';

        echo '<div class="hld-checklist-grid">';
        foreach ($this->checklist_items() as $key => $label) {
            echo '<label><span>' . esc_html($label) . '</span><select name="checklist[' . esc_attr($key) . ']">';
            foreach ($this->checklist_statuses() as $status_key => $status_label) {
                echo '<option value="' . esc_attr($status_key) . '"' . selected($checklist[$key] ?? 'pending', $status_key, false) . '>' . esc_html($status_label) . '</option>';
            }
            echo '</select></label>';
        }
        echo '</div>';

        echo '<label class="hld-wide">Hiánypótlási megjegyzés<textarea name="missing_note" rows="3" placeholder="Példa: hiányzik a vevői személyi igazolvány, az értékesítés kérjen útlevélmásolatot.">' . esc_textarea($case['missing_note'] ?? '') . '</textarea></label>';
        echo '<label class="hld-wide">Belső ügyvédi megjegyzés<textarea name="case_note" rows="3" placeholder="Opcionális belső megjegyzés az értékesítés és az ügyvéd számára.">' . esc_textarea($case['case_note'] ?? '') . '</textarea></label>';
        echo '<div class="hld-form-actions"><button type="submit">Ügyfolyamat mentése</button><span>Csak a védett értékesítési és ügyvédi portálon látható.</span></div>';
        echo '</form></section>';
    }

    private function render_upload_panel($context, $target) {
        $categories = $this->categories();
        echo '<section class="hld-panel"><div class="hld-panel-head"><div><h2>Jogi dokumentum feltöltése</h2><p>A fájl ehhez a lakáshoz kapcsolódik, és nyilvános hozzáférés nélkül, védett tárhelyen kerül mentésre.</p></div></div>';
        echo '<form method="post" enctype="multipart/form-data" class="hld-form">';
        wp_nonce_field('harmat_legal_upload_document');
        echo '<input type="hidden" name="harmat_legal_action" value="upload_document">';
        echo '<input type="hidden" name="return_context" value="' . esc_attr($context) . '">';
        echo '<input type="hidden" name="deal_id" value="' . esc_attr((int) ($target['deal_id'] ?? 0)) . '">';
        echo '<input type="hidden" name="property_id" value="' . esc_attr((int) ($target['property_id'] ?? 0)) . '">';
        echo '<input type="hidden" name="client_name" value="' . esc_attr($target['client_name'] ?? '') . '">';
        echo '<input type="hidden" name="apartment_code" value="' . esc_attr($target['title'] ?? '') . '">';
        echo '<label>Fájltípus<select name="category">';
        foreach ($categories as $key => $label) {
            echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Cím<input name="document_title" placeholder="Szerződéstervezet, vevői okmány, fizetési igazolás"></label>';
        echo '<label>Vevői azonosító megjegyzés<input name="buyer_id_note" placeholder="Útlevél / személyi igazolvány megjegyzés, opcionális"></label>';
        echo '<label>Fájl kiválasztása<input required type="file" name="legal_document" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt,.zip"></label>';
        echo '<label class="hld-wide">Belső megjegyzés<textarea name="document_note" rows="3" placeholder="Opcionális ügyvédi vagy értékesítési megjegyzés"></textarea></label>';
        echo '<div class="hld-form-actions"><button type="submit">Fájl mentése</button><span>Legfeljebb 50 MB. A letöltéshez bejelentkezés és jogi dokumentum jogosultság szükséges.</span></div>';
        echo '</form></section>';
    }

    private function render_documents_panel($context, $target) {
        $docs = $this->documents_for_target($target);
        $categories = $this->categories();
        echo '<section class="hld-panel"><div class="hld-panel-head"><div><h2>A lakás dokumentumai</h2><p>Ezeket a fájlokat kizárólag a jogosult Harmat Lakópark értékesítés és ügyvédek tölthetik le.</p></div></div>';
        if (!$docs) {
            echo '<div class="hld-empty">Ehhez a lakáshoz még nincs feltöltött jogi dokumentum.</div></section>';
            return;
        }

        echo '<div class="hld-table-wrap"><table class="hld-table"><thead><tr><th>Fájl</th><th>Vevő / lakás</th><th>Típus</th><th>Feltöltés</th><th>Művelet</th></tr></thead><tbody>';
        foreach ($docs as $doc) {
            $uploader = !empty($doc['uploaded_by']) ? get_userdata((int) $doc['uploaded_by']) : null;
            echo '<tr>';
            echo '<td><strong>' . esc_html($doc['title'] ?: $doc['original_name']) . '</strong><small>' . esc_html($doc['original_name']) . ' / ' . esc_html($this->file_size_label($doc['size'])) . '</small>';
            if (!empty($doc['buyer_id_note'])) {
                echo '<small>Vevői azonosító megjegyzés: ' . esc_html($doc['buyer_id_note']) . '</small>';
            }
            if (!empty($doc['note'])) {
                echo '<small>' . esc_html($doc['note']) . '</small>';
            }
            echo '</td>';
            echo '<td><strong>' . esc_html($doc['client_name'] ?: ($target['client_name'] ?: '-')) . '</strong><small>' . esc_html($doc['apartment_code'] ?: $target['title']) . '</small></td>';
            echo '<td><span class="hld-badge">' . esc_html($categories[$doc['category']] ?? $categories['other']) . '</span></td>';
            echo '<td><strong>' . esc_html($doc['uploaded_at'] ?: '-') . '</strong><small>' . esc_html($uploader ? $uploader->display_name : '-') . '</small></td>';
            echo '<td class="hld-actions"><a href="' . esc_url($this->download_url($doc)) . '">Letöltés</a>';
            if (current_user_can(self::CAP_MANAGE)) {
                echo '<form method="post">';
                wp_nonce_field('harmat_legal_delete_document_' . (int) $doc['id']);
                echo '<input type="hidden" name="harmat_legal_action" value="delete_document">';
                echo '<input type="hidden" name="return_context" value="' . esc_attr($context) . '">';
                echo '<input type="hidden" name="deal_id" value="' . esc_attr((int) ($target['deal_id'] ?? 0)) . '">';
                echo '<input type="hidden" name="property_id" value="' . esc_attr((int) ($target['property_id'] ?? 0)) . '">';
                echo '<input type="hidden" name="document_id" value="' . esc_attr((int) $doc['id']) . '">';
                echo '<button type="submit" onclick="return confirm(\'Biztosan törli ezt a jogi dokumentumot a szerverről?\')">Törlés</button>';
                echo '</form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function render_summary_panel($target = null) {
        $docs = $target ? $this->documents_for_target($target) : $this->get_documents();
        $count = count($docs);
        $total_size = 0;
        foreach ($docs as $doc) {
            $total_size += (int) ($doc['size'] ?? 0);
        }
        $missing = 0;
        if ($target) {
            $case = $target['case'] ?? $this->get_case_for_target($target);
            $missing = $this->case_missing_count($case);
        } else {
            foreach ($this->legal_targets() as $row) {
                if (!empty($row['missing_count']) || !empty($row['missing_note'])) {
                    $missing++;
                }
            }
        }
        echo '<section class="hld-panel"><h2>Állapot</h2><div class="hld-stats"><span><small>Dokumentumok</small><b>' . esc_html((string) $count) . '</b></span><span><small>Tárhely</small><b>' . esc_html($this->file_size_label($total_size)) . '</b></span><span><small>Hiányzó tételek</small><b>' . esc_html((string) $missing) . '</b></span><span><small>Hozzáférés</small><b>Védett</b></span></div></section>';
    }

    private function render_lawyer_accounts_panel() {
        $users = get_users(array('role' => self::ROLE_LAWYER, 'orderby' => 'registered', 'order' => 'DESC'));
        echo '<section class="hld-panel"><h2>Ügyvédi fiókok</h2><form method="post" class="hld-mini-form">';
        wp_nonce_field('harmat_legal_create_lawyer_user');
        echo '<input type="hidden" name="harmat_legal_action" value="create_lawyer_user">';
        echo '<label>Név<input name="lawyer_name" placeholder="Ügyvéd neve"></label>';
        echo '<label>E-mail<input required type="email" name="lawyer_email" placeholder="ugyved@example.com"></label>';
        echo '<label>Felhasználónév<input name="lawyer_login" placeholder="Üresen hagyva automatikusan készül"></label>';
        echo '<button type="submit">Ügyvédi fiók létrehozása</button></form>';
        if (!$users) {
            echo '<p class="hld-muted">Még nincs ügyvédi fiók.</p>';
        } else {
            echo '<div class="hld-user-list">';
            foreach ($users as $user) {
                echo '<span><strong>' . esc_html($user->display_name ?: $user->user_login) . '</strong><small>' . esc_html($user->user_email) . '</small></span>';
            }
            echo '</div>';
        }
        echo '</section>';
    }

    private function render_audit_panel() {
        $audit = $this->get_audit();
        echo '<section class="hld-panel"><h2>Legutóbbi napló</h2>';
        if (!$audit) {
            echo '<p class="hld-muted">Még nincs aktivitás.</p></section>';
            return;
        }
        echo '<div class="hld-audit">';
        foreach ($audit as $row) {
            $user = !empty($row['user_id']) ? get_userdata((int) $row['user_id']) : null;
            echo '<span><strong>' . esc_html($this->audit_action_label($row['action'] ?? '')) . '</strong><small>' . esc_html(($row['title'] ?? '') . ' / ' . ($user ? $user->display_name : '-') . ' / ' . ($row['created_at'] ?? '')) . '</small></span>';
        }
        echo '</div></section>';
    }

    private function render_lawyer_help_panel() {
        echo '<section class="hld-panel"><h2>Folyamat</h2><p class="hld-muted">Nyissa meg a lakáskártyát. Ha az értékesítés foglaltnak vagy szerződés alattinak jelölte az ügyletet, itt megjelenik a CRM azonosító, a vevő neve, elérhetősége, az összeg és az állapot.</p><p class="hld-muted">A feltöltött fájlok nem nyilvános hivatkozások; minden letöltéshez bejelentkezés és jogosultság szükséges.</p></section>';
    }

    private function workflow_css() {
        return '
        .hld-case-form{display:grid;gap:14px}.hld-case-form label{display:grid;gap:6px;color:#9a6b27;font-size:12px;font-weight:900;letter-spacing:.05em}.hld-case-form select,.hld-case-form input,.hld-case-form textarea{width:100%;min-height:42px;padding:10px 12px;border:1px solid #e3cfad;border-radius:10px;background:#fffaf3;color:#253137;font:inherit}.hld-case-top{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.hld-checklist-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.hld-checklist-grid label{padding:12px;border:1px solid #ead8b8;border-radius:12px;background:#fffaf3}.hld-checklist-grid span{display:block;margin-bottom:8px;color:#253137;font-size:12px;font-weight:900;letter-spacing:0}.hld-apartment-card.has-missing{border-color:#d92d20}.hld-apartment-card.has-missing .hld-card-top em{background:#fff1f3;color:#b42318}@media(max-width:1000px){.hld-case-top,.hld-checklist-grid{grid-template-columns:1fr}}';
    }

    private function base_css() {
        return '
        *{box-sizing:border-box}body.hld-body{margin:0;background:#fbf4e7;color:#253137;font-family:Montserrat,Arial,sans-serif}.hld-shell{width:min(1320px,calc(100% - 32px));margin:0 auto;padding:28px 0 44px}.hld-login{min-height:100vh;display:grid;place-items:center;padding:24px}.hld-hero,.hld-panel{border:1px solid #ead8b8;background:#fff;border-radius:18px;box-shadow:0 14px 34px rgba(70,54,28,.06)}.hld-hero{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:16px;padding:28px 30px;background:linear-gradient(135deg,#fffaf1,#fff)}.hld-eyebrow{margin:0 0 8px;color:#a5742c;font-size:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.hld-hero h1,.hld-panel h1,.hld-panel h2,.hld-section-title h3{margin:0;color:#253137;font-family:Georgia,"Times New Roman",serif;font-weight:500}.hld-hero h1{font-size:38px}.hld-hero p,.hld-muted,.hld-panel p{color:#687178;line-height:1.65}.hld-user{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:999px;background:#fff;border:1px solid #ead8b8}.hld-user span{font-weight:900}.hld-user a,.hld-panel a{color:#a8762d;font-weight:900;text-decoration:none}.hld-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px;padding:8px;border-radius:18px;background:#fff;border:1px solid #ead8b8;box-shadow:0 10px 28px rgba(70,54,28,.05)}.hld-nav a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:12px;color:#253137;text-decoration:none;font-weight:900}.hld-nav a.is-active{background:#a8762d;color:#fff}.hld-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px}.hld-main,.hld-side{display:grid;gap:18px;align-content:start}.hld-panel{padding:20px}.hld-panel-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px}.hld-panel h2{font-size:27px}.hld-form,.hld-mini-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.hld-mini-form{grid-template-columns:1fr}.hld-form label,.hld-mini-form label{display:grid;gap:6px;color:#9a6b27;font-size:12px;font-weight:900;letter-spacing:.05em}.hld-form input,.hld-form select,.hld-form textarea,.hld-mini-form input,.hld-filter input,.hld-filter select{width:100%;min-height:42px;padding:10px 12px;border:1px solid #e3cfad;border-radius:10px;background:#fffaf3;color:#253137;font:inherit}.hld-wide,.hld-form-actions{grid-column:1/-1}.hld-form-actions{display:flex;gap:12px;align-items:center}.hld-form-actions span{color:#687178;font-size:13px}.hld-form button,.hld-mini-form button,.hld-filter button,.hld-button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border:0;border-radius:10px;background:#a8762d;color:#fff!important;font-weight:900;letter-spacing:.08em;text-decoration:none;cursor:pointer}.hld-button-light{background:#fffaf3!important;color:#a8762d!important;border:1px solid #ead8b8}.hld-filter{display:flex;gap:8px;margin-bottom:14px}.hld-filter input{min-width:280px}.hld-section-title{display:flex;align-items:center;gap:10px;margin:18px 0 10px}.hld-section-title h3{font-size:23px}.hld-section-title span{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;border-radius:999px;background:#a8762d;color:#fff;font-weight:900}.hld-apartment-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px}.hld-apartment-card{display:grid;gap:10px;padding:15px;border:1px solid #ead8b8;border-radius:16px;background:#fffaf3;color:#253137!important;text-decoration:none!important}.hld-apartment-card.is-priority{border-color:#a8762d;box-shadow:0 10px 28px rgba(168,118,45,.14)}.hld-card-top{display:flex;justify-content:space-between;gap:10px;align-items:start}.hld-card-top strong{font-size:18px}.hld-card-top em{padding:4px 8px;border-radius:999px;background:#efe4d2;color:#7c551d;font-style:normal;font-size:11px;font-weight:900}.hld-apartment-card small{display:block;color:#8a6a3a;font-size:11px;font-weight:900;text-transform:uppercase}.hld-apartment-card b{display:block;color:#253137;overflow-wrap:anywhere}.hld-target-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.hld-target-kpis article,.hld-buyer-strip span,.hld-task-kpis span{padding:12px;border-radius:12px;background:#fffaf3;border:1px solid #ead8b8}.hld-target-kpis small,.hld-buyer-strip small,.hld-task-kpis small{display:block;color:#9a6b27;font-size:11px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.hld-target-kpis strong,.hld-buyer-strip b,.hld-task-kpis b{display:block;margin-top:6px;color:#253137;overflow-wrap:anywhere}.hld-task-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.hld-task-kpis b{font-size:30px}.hld-buyer-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:10px}.hld-table-wrap{overflow:auto}.hld-table{width:100%;min-width:900px;border-collapse:separate;border-spacing:0 8px}.hld-table th{padding:0 12px 4px;text-align:left;color:#9a6b27;font-size:12px;letter-spacing:.08em}.hld-table td{padding:14px 12px;background:#fffaf3;border-top:1px solid #ead8b8;border-bottom:1px solid #ead8b8;vertical-align:top}.hld-table td:first-child{border-left:1px solid #ead8b8;border-radius:12px 0 0 12px}.hld-table td:last-child{border-right:1px solid #ead8b8;border-radius:0 12px 12px 0}.hld-table strong{display:block;color:#253137}.hld-table small{display:block;margin-top:5px;color:#687178;line-height:1.45}.hld-badge{display:inline-flex;align-items:center;min-height:28px;padding:0 10px;border-radius:999px;background:#efe4d2;color:#7c551d;font-size:12px;font-weight:900}.hld-actions{white-space:nowrap}.hld-actions a,.hld-actions button{display:inline-flex;align-items:center;justify-content:center;min-height:34px;margin:0 6px 6px 0;padding:0 10px;border:1px solid #a8762d;border-radius:9px;background:#fff;color:#a8762d;font:inherit;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer}.hld-actions button{border-color:#d92d20;color:#b42318}.hld-actions form{display:inline}.hld-notice{margin:0 0 16px;padding:14px 16px;border-radius:14px;font-weight:800}.hld-notice span{display:block;margin-top:6px}.hld-success{background:#ecfdf3;color:#027a48;border:1px solid #abefc6}.hld-error{background:#fff1f3;color:#b42318;border:1px solid #fecdca}.hld-empty{padding:24px;border:1px dashed #d4bea0;border-radius:16px;color:#6f7780;background:#fffaf3}.hld-stats,.hld-user-list,.hld-audit{display:grid;gap:10px}.hld-stats span,.hld-user-list span,.hld-audit span{display:block;padding:12px;border-radius:12px;background:#fffaf3;border:1px solid #ead8b8}.hld-stats small,.hld-user-list small,.hld-audit small{display:block;color:#687178;margin-top:5px}.hld-stats b{display:block;margin-top:6px;font-size:22px;color:#253137}code{padding:2px 6px;border-radius:6px;background:#fffaf3;color:#253137}.hld-login .hld-panel{width:min(470px,100%);padding:28px}.hld-login form{display:grid;gap:14px;margin-top:18px}.hld-login form p{margin:0}.hld-login label{display:grid;gap:7px;color:#52616a;font-size:14px;font-weight:800}.hld-login input[type=text],.hld-login input[type=password]{width:100%;min-height:46px;padding:10px 12px;border:1px solid #e3cfad;border-radius:10px;background:#fffaf3;color:#253137;font:inherit}.hld-login .login-remember label{display:flex;align-items:center;gap:8px}.hld-login input[type=checkbox]{width:18px;height:18px;accent-color:#a8762d}.hld-login input[type=submit]{min-height:44px;padding:0 18px;border:0;border-radius:10px;background:#a8762d;color:#fff;font-weight:900;letter-spacing:.06em;cursor:pointer}@media(max-width:1000px){.hld-shell{width:min(100% - 20px,760px);padding-top:14px}.hld-hero,.hld-panel-head{display:grid}.hld-hero h1{font-size:31px}.hld-grid,.hld-form,.hld-target-kpis,.hld-buyer-strip,.hld-task-kpis{grid-template-columns:1fr}.hld-panel{padding:16px}.hld-filter{display:grid}.hld-filter input{min-width:0}.hld-user{border-radius:14px;align-items:flex-start}}';
    }
}

register_activation_hook(__FILE__, array('Harmat_Legal_Documents', 'activate'));
register_deactivation_hook(__FILE__, array('Harmat_Legal_Documents', 'deactivate'));

$GLOBALS['harmat_legal_documents'] = new Harmat_Legal_Documents();
