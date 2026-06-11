<?php
/**
 * Plugin Name: Harmat Site Maintenance
 * Description: Admin-only maintenance dashboard for Harmat Lakopark checks, property audits, mail status, and cache clearing.
 * Version: 0.1.0
 * Author: Cooperation Power Kft.
 */

defined('ABSPATH') || exit;

final class Harmat_Site_Maintenance {
    const VERSION = '0.1.0';
    const PAGE_SLUG = 'harmat-site-maintenance';
    const OPTION_REPORT = 'harmat_site_maintenance_last_report';
    const OPTION_CACHE = 'harmat_site_maintenance_last_cache_clear';

    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_post_harmat_maintenance_run_audit', array($this, 'handle_run_audit'));
        add_action('admin_post_harmat_maintenance_clear_cache', array($this, 'handle_clear_cache'));
    }

    public function register_menu() {
        add_menu_page(
            'Harmat Maintenance',
            'Harmat Maintenance',
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'render_page'),
            'dashicons-shield-alt',
            58
        );
    }

    public function admin_assets($hook) {
        if ($hook !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_register_style('harmat-site-maintenance-admin', false, array(), self::VERSION);
        wp_enqueue_style('harmat-site-maintenance-admin');
        wp_add_inline_style('harmat-site-maintenance-admin', $this->css());
    }

    public function handle_run_audit() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        check_admin_referer('harmat_maintenance_run_audit');
        update_option(self::OPTION_REPORT, $this->run_audit(), false);
        wp_safe_redirect(add_query_arg(array('page' => self::PAGE_SLUG, 'hm_audit' => 'done'), admin_url('admin.php')));
        exit;
    }

    public function handle_clear_cache() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        check_admin_referer('harmat_maintenance_clear_cache');
        update_option(self::OPTION_CACHE, $this->clear_caches(), false);
        wp_safe_redirect(add_query_arg(array('page' => self::PAGE_SLUG, 'hm_cache' => 'done'), admin_url('admin.php')));
        exit;
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        $report = get_option(self::OPTION_REPORT, array());
        $cache = get_option(self::OPTION_CACHE, array());
        $has_report = is_array($report) && !empty($report['created_at']);
        ?>
        <div class="wrap harmat-maintenance">
            <div class="harmat-maintenance-head">
                <div>
                    <span>Harmat Lakopark</span>
                    <h1>Site Maintenance</h1>
                    <p>Admin-only checks for public pages, apartment data, inquiry mail state, and cache cleanup.</p>
                </div>
                <div class="harmat-maintenance-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="harmat_maintenance_run_audit">
                        <?php wp_nonce_field('harmat_maintenance_run_audit'); ?>
                        <button class="button button-primary button-hero" type="submit">Run audit</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="harmat_maintenance_clear_cache">
                        <?php wp_nonce_field('harmat_maintenance_clear_cache'); ?>
                        <button class="button button-secondary button-hero" type="submit">Clear cache</button>
                    </form>
                </div>
            </div>

            <?php if (isset($_GET['hm_audit'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Maintenance audit completed.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['hm_cache'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Cache cleanup completed.</p></div>
            <?php endif; ?>

            <?php if (!$has_report) : ?>
                <div class="harmat-maintenance-empty">
                    <h2>No report yet</h2>
                    <p>Run the first audit to create a maintenance snapshot.</p>
                </div>
            <?php else : ?>
                <?php $this->render_report($report, $cache); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_report($report, $cache) {
        $summary = isset($report['summary']) && is_array($report['summary']) ? $report['summary'] : array();
        $health = isset($report['health']) && is_array($report['health']) ? $report['health'] : array();
        $properties = isset($report['properties']) && is_array($report['properties']) ? $report['properties'] : array();
        $mail = isset($report['mail']) && is_array($report['mail']) ? $report['mail'] : array();
        $issues = isset($properties['issues']) && is_array($properties['issues']) ? $properties['issues'] : array();
        ?>
        <div class="harmat-maintenance-meta">
            Last audit: <strong><?php echo esc_html($report['created_at']); ?></strong>
            <?php if (!empty($cache['created_at'])) : ?>
                <span>Last cache clear: <strong><?php echo esc_html($cache['created_at']); ?></strong></span>
            <?php endif; ?>
        </div>

        <div class="harmat-maintenance-cards">
            <?php
            $this->metric_card('Public pages', $summary['health_ok'] ?? 0, $summary['health_total'] ?? 0);
            $this->metric_card('Properties', $summary['property_ok'] ?? 0, $summary['property_total'] ?? 0);
            $this->metric_card('Property issues', $summary['property_issues'] ?? 0, null, 'warn');
            $this->metric_card('Queued mail', $summary['mail_queued'] ?? 0, null, ($summary['mail_queued'] ?? 0) ? 'warn' : 'ok');
            ?>
        </div>

        <div class="harmat-maintenance-grid">
            <section class="harmat-maintenance-panel">
                <h2>Public page health</h2>
                <table class="widefat striped">
                    <thead><tr><th>Page</th><th>Status</th><th>Checks</th></tr></thead>
                    <tbody>
                    <?php foreach ($health as $row) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($row['label']); ?></a></td>
                            <td><?php echo $this->badge($row['ok'] ? 'OK' : 'Issue', $row['ok'] ? 'ok' : 'bad'); ?></td>
                            <td><?php echo esc_html($row['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="harmat-maintenance-panel">
                <h2>Mail status</h2>
                <?php $this->render_mail_status($mail); ?>
            </section>
        </div>

        <section class="harmat-maintenance-panel">
            <h2>Apartment audit</h2>
            <p class="description">Checks published property pages for price, PDF, official floorplan image, and room-list data.</p>
            <?php $this->render_property_summary($properties); ?>
            <?php if ($issues) : ?>
                <table class="widefat striped harmat-maintenance-issues">
                    <thead><tr><th>Apartment</th><th>Issues</th><th>URL</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($issues, 0, 80) as $issue) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($issue['title']); ?></strong></td>
                            <td><?php echo esc_html(implode(', ', $issue['issues'])); ?></td>
                            <td><a href="<?php echo esc_url($issue['url']); ?>" target="_blank" rel="noopener">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="harmat-maintenance-okbox">All published apartments passed the first-phase audit.</div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function metric_card($label, $value, $total = null, $tone = 'ok') {
        $text = $total === null ? (string) $value : $value . ' / ' . $total;
        echo '<div class="harmat-maintenance-card harmat-maintenance-card-' . esc_attr($tone) . '">';
        echo '<span>' . esc_html($label) . '</span>';
        echo '<strong>' . esc_html($text) . '</strong>';
        echo '</div>';
    }

    private function render_property_summary($properties) {
        $total = (int) ($properties['total'] ?? 0);
        $with_price = (int) ($properties['with_price'] ?? 0);
        $with_pdf = (int) ($properties['with_pdf'] ?? 0);
        $with_image = (int) ($properties['with_image'] ?? 0);
        $with_rooms = (int) ($properties['with_rooms'] ?? 0);
        ?>
        <div class="harmat-maintenance-mini">
            <div><span>Total</span><strong><?php echo esc_html($total); ?></strong></div>
            <div><span>With price</span><strong><?php echo esc_html($with_price); ?></strong></div>
            <div><span>With PDF</span><strong><?php echo esc_html($with_pdf); ?></strong></div>
            <div><span>With image</span><strong><?php echo esc_html($with_image); ?></strong></div>
            <div><span>With room list</span><strong><?php echo esc_html($with_rooms); ?></strong></div>
        </div>
        <?php
    }

    private function render_mail_status($mail) {
        $statuses = isset($mail['statuses']) && is_array($mail['statuses']) ? $mail['statuses'] : array();
        $recent = isset($mail['recent']) && is_array($mail['recent']) ? $mail['recent'] : array();
        ?>
        <div class="harmat-maintenance-statuses">
            <?php foreach (array('sent', 'queued', 'failed', 'sending', 'retrying') as $status) : ?>
                <div><span><?php echo esc_html($status); ?></span><strong><?php echo esc_html((int) ($statuses[$status] ?? 0)); ?></strong></div>
            <?php endforeach; ?>
        </div>
        <table class="widefat striped">
            <thead><tr><th>Lead</th><th>Status</th><th>Checked</th></tr></thead>
            <tbody>
            <?php if (!$recent) : ?>
                <tr><td colspan="3">No inquiry records found.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $row) : ?>
                <tr>
                    <td><?php echo esc_html($row['title']); ?></td>
                    <td><?php echo $this->badge($row['status'] ?: 'unknown', $row['status'] === 'sent' ? 'ok' : 'warn'); ?></td>
                    <td><?php echo esc_html($row['checked_at'] ?: '-'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function badge($text, $tone) {
        return '<span class="harmat-maintenance-badge harmat-maintenance-badge-' . esc_attr($tone) . '">' . esc_html($text) . '</span>';
    }

    private function run_audit() {
        $health = $this->audit_public_pages();
        $properties = $this->audit_properties();
        $mail = $this->audit_mail();
        $summary = array(
            'health_total' => count($health),
            'health_ok' => count(array_filter($health, function ($row) {
                return !empty($row['ok']);
            })),
            'property_total' => (int) ($properties['total'] ?? 0),
            'property_ok' => max(0, (int) ($properties['total'] ?? 0) - count($properties['issues'] ?? array())),
            'property_issues' => count($properties['issues'] ?? array()),
            'mail_queued' => (int) (($mail['statuses']['queued'] ?? 0) + ($mail['statuses']['sending'] ?? 0) + ($mail['statuses']['retrying'] ?? 0)),
        );

        return array(
            'created_at' => current_time('mysql'),
            'summary' => $summary,
            'health' => $health,
            'properties' => $properties,
            'mail' => $mail,
        );
    }

    private function audit_public_pages() {
        $pages = array(
            array('label' => 'Home', 'path' => '/', 'must' => array('Harmat'), 'bad' => array('0 m2', '0 db', 'Harmat 22')),
            array('label' => 'Lakaskereso', 'path' => '/lakaskereso/', 'must' => array(), 'bad' => array('0 m2 Alapterulet')),
            array('label' => 'Kapcsolat', 'path' => '/elerhetosegeink/', 'must' => array('ertekesites@harmat22.hu'), 'bad' => array('Gipsz Jakab')),
            array('label' => 'Property sample', 'path' => '/property/a1-1-l1/', 'must' => array('harmat-property-detail-sample'), 'bad' => array('<div class="elementor-element elementor-element-03069d8', 'PDF alaprajz')),
            array('label' => 'Finanszirozas', 'path' => '/finanszirozas/', 'must' => array(), 'bad' => array()),
        );
        $rows = array();
        foreach ($pages as $page) {
            $url = add_query_arg('hm_maintenance', time(), home_url($page['path']));
            $message = array();
            $ok = true;
            $response = wp_remote_get($url, array('timeout' => 12, 'redirection' => 3));
            if (is_wp_error($response)) {
                $ok = false;
                $message[] = $response->get_error_message();
                $code = 0;
                $body = '';
            } else {
                $code = (int) wp_remote_retrieve_response_code($response);
                $body = (string) wp_remote_retrieve_body($response);
                if ($code < 200 || $code >= 400) {
                    $ok = false;
                    $message[] = 'HTTP ' . $code;
                }
            }
            foreach ($page['must'] as $needle) {
                if ($needle !== '' && stripos($body, $needle) === false) {
                    $ok = false;
                    $message[] = 'Missing: ' . $needle;
                }
            }
            foreach ($page['bad'] as $needle) {
                if ($needle !== '' && stripos($body, $needle) !== false) {
                    $ok = false;
                    $message[] = 'Found old text: ' . $needle;
                }
            }
            if (!$message) {
                $message[] = 'HTTP ' . $code . ', checks passed';
            }
            $rows[] = array(
                'label' => $page['label'],
                'url' => home_url($page['path']),
                'ok' => $ok,
                'message' => implode('; ', $message),
            );
        }
        return $rows;
    }

    private function audit_properties() {
        $ids = get_posts(array(
            'post_type' => 'property',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));
        $stats = array(
            'total' => count($ids),
            'with_price' => 0,
            'with_pdf' => 0,
            'with_image' => 0,
            'with_rooms' => 0,
            'issues' => array(),
        );
        foreach ($ids as $post_id) {
            $title = $this->property_title($post_id);
            $price = (int) get_post_meta($post_id, 'property_price', true);
            $pdf = $this->property_pdf_exists($post_id, $title);
            $image = $this->property_image_exists($title);
            $rooms = $this->property_room_count($post_id);
            $issues = array();
            if ($price > 0) {
                $stats['with_price']++;
            } else {
                $issues[] = 'missing price';
            }
            if ($pdf) {
                $stats['with_pdf']++;
            } else {
                $issues[] = 'missing PDF';
            }
            if ($image) {
                $stats['with_image']++;
            } else {
                $issues[] = 'missing floorplan image';
            }
            if ($rooms > 0) {
                $stats['with_rooms']++;
            } else {
                $issues[] = 'missing room list';
            }
            if ($issues) {
                $stats['issues'][] = array(
                    'title' => $title,
                    'url' => get_permalink($post_id),
                    'issues' => $issues,
                );
            }
        }
        return $stats;
    }

    private function audit_mail() {
        $ids = get_posts(array(
            'post_type' => 'harmat_offer_lead',
            'post_status' => array('publish', 'private', 'draft'),
            'fields' => 'ids',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));
        $statuses = array();
        $recent = array();
        foreach ($ids as $post_id) {
            $status = (string) get_post_meta($post_id, '_harmat_offer_mail_status', true);
            $status = $status ?: 'unknown';
            $statuses[$status] = isset($statuses[$status]) ? $statuses[$status] + 1 : 1;
            if (count($recent) < 10) {
                $recent[] = array(
                    'title' => get_the_title($post_id),
                    'status' => $status,
                    'checked_at' => get_post_meta($post_id, '_harmat_offer_mail_checked_at', true),
                );
            }
        }
        return array(
            'statuses' => $statuses,
            'recent' => $recent,
        );
    }

    private function clear_caches() {
        global $wpdb;
        $result = array(
            'created_at' => current_time('mysql'),
            'steps' => array(),
        );
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $result['steps'][] = 'WordPress object cache flushed';
        }
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'");
        $result['steps'][] = 'Transients deleted';
        if (did_action('elementor/loaded') && class_exists('\\Elementor\\Plugin')) {
            try {
                \Elementor\Plugin::instance()->files_manager->clear_cache();
                $result['steps'][] = 'Elementor CSS cache cleared';
            } catch (Exception $e) {
                $result['steps'][] = 'Elementor clear failed: ' . $e->getMessage();
            }
        }
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
            $result['steps'][] = 'Autoptimize cache cleared';
        }
        do_action('litespeed_purge_all');
        $result['steps'][] = 'LiteSpeed purge action sent';
        return $result;
    }

    private function property_title($post_id) {
        $title = (string) get_post_meta($post_id, 'property_heading', true);
        return $title !== '' ? $title : get_the_title($post_id);
    }

    private function property_pdf_exists($post_id, $title) {
        $url = (string) get_post_meta($post_id, 'property_floorplan', true);
        if ($url !== '' && $this->url_to_existing_upload_path($url)) {
            return true;
        }
        return $this->upload_file_exists('2026/05/' . strtolower($title) . '-cn-floorplan.pdf');
    }

    private function property_image_exists($title) {
        return $this->upload_file_exists('2026/05/' . $title . '-cn-floorplan.jpg')
            || $this->upload_file_exists('2026/05/' . strtoupper($title) . '-cn-floorplan.jpg')
            || $this->upload_file_exists('2026/05/' . strtolower($title) . '-cn-floorplan.jpg');
    }

    private function property_room_count($post_id) {
        $content = (string) get_post_field('post_content', $post_id);
        $elementor = (string) get_post_meta($post_id, '_elementor_data', true);
        return substr_count($content . ' ' . $elementor, 'area-row');
    }

    private function upload_file_exists($relative_path) {
        $upload = wp_upload_dir();
        if (empty($upload['basedir'])) {
            return false;
        }
        $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
        $path = trailingslashit($upload['basedir']) . $relative_path;
        if (file_exists($path)) {
            return true;
        }
        $dir = dirname($path);
        $base = basename($path);
        if (!is_dir($dir)) {
            return false;
        }
        foreach (scandir($dir) ?: array() as $file) {
            if (strcasecmp($file, $base) === 0) {
                return true;
            }
        }
        return false;
    }

    private function url_to_existing_upload_path($url) {
        $upload = wp_upload_dir();
        if (empty($upload['baseurl']) || empty($upload['basedir'])) {
            return false;
        }
        $url = strtok((string) $url, '?');
        if (stripos($url, $upload['baseurl']) !== 0) {
            return false;
        }
        $relative = ltrim(substr($url, strlen($upload['baseurl'])), '/');
        $path = trailingslashit($upload['basedir']) . $relative;
        return file_exists($path) ? $path : false;
    }

    private function css() {
        return '
            .harmat-maintenance { max-width: 1280px; }
            .harmat-maintenance-head { display:flex; align-items:flex-end; justify-content:space-between; gap:24px; margin:24px 0; padding:26px 28px; background:#fff; border:1px solid #d8c6a9; box-shadow:0 14px 38px rgba(40,34,24,.08); }
            .harmat-maintenance-head span { display:block; margin-bottom:6px; color:#987033; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
            .harmat-maintenance-head h1 { margin:0; color:#263135; font-size:34px; line-height:1.1; }
            .harmat-maintenance-head p { margin:8px 0 0; color:#5b6267; font-size:14px; }
            .harmat-maintenance-actions { display:flex; flex-wrap:wrap; gap:10px; }
            .harmat-maintenance-empty, .harmat-maintenance-panel { background:#fff; border:1px solid #d8c6a9; padding:22px; box-shadow:0 12px 32px rgba(40,34,24,.06); }
            .harmat-maintenance-meta { margin:0 0 16px; color:#50585d; }
            .harmat-maintenance-meta span { margin-left:18px; }
            .harmat-maintenance-cards { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:16px; }
            .harmat-maintenance-card { padding:18px; background:#fff; border:1px solid #d8c6a9; border-left:4px solid #17875b; }
            .harmat-maintenance-card-warn { border-left-color:#b77a24; }
            .harmat-maintenance-card span, .harmat-maintenance-mini span, .harmat-maintenance-statuses span { display:block; color:#987033; font-size:11px; font-weight:800; text-transform:uppercase; }
            .harmat-maintenance-card strong { display:block; margin-top:8px; color:#263135; font-size:28px; line-height:1; }
            .harmat-maintenance-grid { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(360px,.75fr); gap:16px; margin-bottom:16px; }
            .harmat-maintenance-panel h2 { margin:0 0 14px; color:#263135; font-size:22px; }
            .harmat-maintenance-badge { display:inline-flex; align-items:center; min-height:24px; padding:0 9px; border-radius:999px; font-size:11px; font-weight:800; text-transform:uppercase; }
            .harmat-maintenance-badge-ok { color:#17875b; background:#f0faf5; }
            .harmat-maintenance-badge-warn { color:#8a5a17; background:#fff6e6; }
            .harmat-maintenance-badge-bad { color:#a33d2e; background:#fff1ed; }
            .harmat-maintenance-mini, .harmat-maintenance-statuses { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; margin:14px 0; }
            .harmat-maintenance-mini div, .harmat-maintenance-statuses div { padding:12px; background:#fffaf1; border:1px solid #eadcc4; }
            .harmat-maintenance-mini strong, .harmat-maintenance-statuses strong { display:block; margin-top:6px; color:#263135; font-size:20px; }
            .harmat-maintenance-okbox { padding:16px; background:#f0faf5; border:1px solid #b7dbc7; color:#176846; font-weight:700; }
            .harmat-maintenance table td, .harmat-maintenance table th { vertical-align:middle; }
            @media(max-width:980px){ .harmat-maintenance-head, .harmat-maintenance-grid { display:block; } .harmat-maintenance-actions { margin-top:18px; } .harmat-maintenance-cards, .harmat-maintenance-mini, .harmat-maintenance-statuses { grid-template-columns:1fr 1fr; } }
        ';
    }
}

new Harmat_Site_Maintenance();
