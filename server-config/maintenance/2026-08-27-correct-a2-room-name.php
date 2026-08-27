<?php
/**
 * Exact content repair for A2-3-L1/08. Never loaded on public requests.
 * Dry run: wp eval-file /private/path/2026-08-27-correct-a2-room-name.php
 * Apply: HARMAT_APPLY=1 HARMAT_BACKUP_DIR=/private/backup wp eval-file /private/path/2026-08-27-correct-a2-room-name.php
 */

function harmat_a2_room_name_values(string $content, string $raw): array
{
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Elementor data must be an array.');
    }

    $code = 'A2-3-L1/08';
    $unit = "m\u{00B2}";
    $old_text = $code . "\nNappali\n18.34 " . $unit;
    $new_text = $code . "\nSzoba\n18.34 " . $unit;
    if (substr_count($content, $code) !== 1) {
        throw new RuntimeException('Expected exactly one room code in post_content.');
    }
    $content_changed = substr_count($content, $old_text) === 1;
    if (!$content_changed && substr_count($content, $new_text) !== 1) {
        throw new RuntimeException('Unexpected room name, area or content formatting.');
    }
    $updated_content = $content_changed ? str_replace($old_text, $new_text, $content) : $content;

    $prefix = '<div class="area-row"><span class="area-code">' . $code . '</span>' . "\r\n";
    $suffix = "\r\n" . '<span class="area-size">18.34 ' . $unit . '</span></div>';
    $old_html = $prefix . '<span class="area-name">Nappali</span>' . $suffix;
    $new_html = $prefix . '<span class="area-name">Szoba</span>' . $suffix;
    $matched = 0;
    $html_changed = false;
    $walk = static function (array &$nodes) use (&$walk, &$matched, &$html_changed, $code, $old_html, $new_html): void {
        foreach ($nodes as &$node) {
            if (!is_array($node)) {
                continue;
            }
            if (($node['id'] ?? '') === '4520a1d') {
                $matched++;
                $html = $node['settings']['html'] ?? null;
                if (($node['widgetType'] ?? '') !== 'html' || !is_string($html) || substr_count($html, $code) !== 1) {
                    throw new RuntimeException('Unexpected target Elementor widget.');
                }
                if (substr_count($html, $old_html) === 1) {
                    $node['settings']['html'] = str_replace($old_html, $new_html, $html);
                    $html_changed = true;
                } elseif (substr_count($html, $new_html) !== 1) {
                    throw new RuntimeException('Unexpected target HTML row.');
                }
            }
            if (!empty($node['elements']) && is_array($node['elements'])) {
                $walk($node['elements']);
            }
        }
        unset($node);
    };
    $walk($data);
    if ($matched !== 1) {
        throw new RuntimeException('Expected exactly one target Elementor widget.');
    }

    return array(
        'content' => $updated_content,
        'elementor' => $html_changed ? json_encode($data, JSON_THROW_ON_ERROR) : $raw,
        'content_changed' => $content_changed,
        'elementor_changed' => $html_changed,
    );
}

if (defined('HARMAT_ROOM_NAME_TESTS_ONLY') && HARMAT_ROOM_NAME_TESTS_ONLY) {
    return;
}
if (!defined('WP_CLI') || !WP_CLI || !defined('ABSPATH')) {
    exit;
}

$post_id = 5205;
$post = get_post($post_id);
if (!$post || $post->post_type !== 'property' || $post->post_name !== 'a2-3-l1' || $post->post_title !== 'A2-3-L1' || $post->post_status !== 'publish') {
    WP_CLI::error('Property identity/status mismatch. No content changed.');
}
$original_content = (string) $post->post_content;
$original_meta = get_post_meta($post_id);
if (count($original_meta['_elementor_data'] ?? array()) !== 1) {
    WP_CLI::error('Expected a single Elementor metadata row.');
}
$original_raw = (string) get_post_meta($post_id, '_elementor_data', true);

try {
    $updated = harmat_a2_room_name_values($original_content, $original_raw);
} catch (Throwable $error) {
    WP_CLI::error($error->getMessage() . ' No content changed.');
}
if (!$updated['content_changed'] && !$updated['elementor_changed']) {
    WP_CLI::success('Already corrected: A2-3-L1/08 Szoba, 18.34 m2.');
    return;
}
if (hash('sha256', $original_content) !== '96fa9d6f3494fe6a4778e8f945c026d68964c669409cf05ebac8f82300cfd473'
    || hash('sha256', $original_raw) !== '9397dc73bbd41b418e7c1500516f2e46c780b6d20ba535f0e0843d6c466add85') {
    WP_CLI::error('Live source changed since inspection. Refusing to overwrite it.');
}
WP_CLI::log('Validated: only post_content and Elementor widget 4520a1d, room A2-3-L1/08, Nappali -> Szoba.');
if (getenv('HARMAT_APPLY') !== '1') {
    WP_CLI::success('Dry run passed. No content changed.');
    return;
}

$backup_dir = realpath((string) getenv('HARMAT_BACKUP_DIR'));
if (!$backup_dir || strpos($backup_dir . '/', '/home/harmath2/codex-backups/') !== 0 || !is_writable($backup_dir)) {
    WP_CLI::error('A writable, private codex-backups directory is required.');
}
$snapshot = array(
    'post' => (array) $post,
    'meta' => $original_meta,
    'content_sha256' => hash('sha256', $original_content),
    'elementor_sha256' => hash('sha256', $original_raw),
    'expected_content' => $updated['content'],
    'expected_elementor' => $updated['elementor'],
);
$backup_file = $backup_dir . '/property-5205-before.json';
$backup_json = wp_json_encode($snapshot, JSON_PRETTY_PRINT);
$handle = fopen($backup_file, 'x');
if (!$handle) {
    WP_CLI::error('Backup must be a new file. No content changed.');
}
$written = fwrite($handle, $backup_json);
fclose($handle);
chmod($backup_file, 0600);
if ($written !== strlen($backup_json) || hash_file('sha256', $backup_file) !== hash('sha256', $backup_json)) {
    WP_CLI::error('Backup verification failed. No content changed.');
}
WP_CLI::log('BACKUP=' . $backup_file);

$purge = static function () use ($post_id): void {
    clean_post_cache($post_id);
    if (class_exists('Elementor\\Plugin')) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
    wp_cache_flush();
};

// Compare-and-swap guards reject another editor's changes before either write.
global $wpdb;
$content_written = false;
$meta_written = false;
try {
    $meta_written = update_post_meta($post_id, '_elementor_data', wp_slash($updated['elementor']), $original_raw);
    if (!$meta_written) {
        throw new RuntimeException('Elementor compare-and-swap failed.');
    }
    $changed = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->posts} SET post_content = %s WHERE ID = %d AND BINARY post_content = BINARY %s",
        $updated['content'], $post_id, $original_content
    ));
    $content_written = $changed === 1;
    if (!$content_written) {
        throw new RuntimeException('Content compare-and-swap failed.');
    }
    clean_post_cache($post_id);
    $after_post = get_post($post_id);
    $after_raw = (string) get_post_meta($post_id, '_elementor_data', true);
    $expected_post = (array) $post;
    $expected_post['post_content'] = $updated['content'];
    $expected_meta = $original_meta;
    $expected_meta['_elementor_data'] = array($updated['elementor']);
    if ((array) $after_post !== $expected_post || get_post_meta($post_id) !== $expected_meta || $after_raw !== $updated['elementor']) {
        throw new RuntimeException('Post or metadata verification failed.');
    }
    $purge();
    WP_CLI::success('Corrected both sources; all other post fields and metadata verified unchanged. Cache cleared.');
} catch (Throwable $error) {
    if ($content_written) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_content = %s WHERE ID = %d AND BINARY post_content = BINARY %s",
            $original_content, $post_id, $updated['content']
        ));
    }
    if ($meta_written) {
        update_post_meta($post_id, '_elementor_data', wp_slash($original_raw), $updated['elementor']);
    }
    $purge();
    $restored = get_post_field('post_content', $post_id) === $original_content
        && get_post_meta($post_id, '_elementor_data', true) === $original_raw;
    WP_CLI::error($error->getMessage() . ($restored ? ' Original content restored.' : ' Inspect the private backup before any further write.'));
}
