<?php
/**
 * Private WP-CLI-only repair. Default: dry run.
 * Apply: HARMAT_APPLY=1 HARMAT_BACKUP_DIR=/home/harmath2/codex-backups/sales-area-align-<32hex>
 * Rollback: HARMAT_ROLLBACK=1 with the same HARMAT_BACKUP_DIR (no APPLY flag).
 * Commercial fields are verified before commit. After commit, only the three
 * targets' derived _elementor_element_cache rows are invalidated, never restored.
 */
if (!(defined('HARMAT_SALES_AREA_TESTS_ONLY') && HARMAT_SALES_AREA_TESTS_ONLY)
    && (!defined('WP_CLI') || !WP_CLI || !defined('ABSPATH') || PHP_SAPI !== 'cli')) {
    exit;
}

function harmat_area_require(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function harmat_area_targets(): array
{
    return array(
        4349 => array('title' => 'A1-2-L4', 'old' => '55.82', 'new' => '55.81',
            'content_sha256' => '58bea99908950ab4815af936e261305d2c468b355162d7975197a4420b9c7a67',
            'elementor_sha256' => '5172a21a3306dc90785c68d6d46946bef0192df46ad5d6940c83efee979c21b6'),
        4388 => array('title' => 'A1-4-L1', 'old' => '67.04', 'new' => '67.05',
            'content_sha256' => 'ae72444714f7d6aeab41d5ceb3f73c4c486187c35d28e27934297b1b53124793',
            'elementor_sha256' => '1571f2ffdd141a60698f53f43b7744908651c73389ae410648785f72510889dc'),
        4418 => array('title' => 'A1-4-L4', 'old' => '59.49', 'new' => '59.48',
            'content_sha256' => 'e9af2385e0a0604c3150fbe846fe717f6001b5615eb54355bf6b0ec1f8b6b829',
            'elementor_sha256' => 'af84019db2c9a4f926229f272100ca617177cfb2067852b8d7f541d501acdd3c'),
    );
}

function harmat_area_json($value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR);
}

function harmat_area_hash($value): string
{
    return hash('sha256', harmat_area_json($value));
}

function harmat_area_content(string $content, string $from, string $to): string
{
    $label = "\u{00C9}rt\u{00E9}kes\u{00ED}tett alapter\u{00FC}let";
    $old = $label . "\n" . $from . " m\u{00B2}";
    harmat_area_require(substr_count($content, $label) === 1 && substr_count($content, $old) === 1,
        'Expected exactly one exact sales total in post_content.');
    return str_replace($old, $label . "\n" . $to . " m\u{00B2}", $content);
}

function harmat_area_walk(array $nodes, array &$matches): void
{
    foreach ($nodes as $node) {
        harmat_area_require($node instanceof stdClass, 'Invalid Elementor node.');
        if (($node->id ?? '') === '4520a1d') {
            harmat_area_require(($node->elType ?? '') === 'widget' && ($node->widgetType ?? '') === 'html'
                && isset($node->settings) && $node->settings instanceof stdClass
                && isset($node->settings->html) && is_string($node->settings->html), 'Wrong target widget type.');
            $matches[] = $node;
        }
        if (isset($node->elements)) {
            harmat_area_require(is_array($node->elements), 'Invalid Elementor children.');
            harmat_area_walk($node->elements, $matches);
        }
    }
}

function harmat_area_elementor(string $raw, string $from, string $to): string
{
    $tree = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
    harmat_area_require(is_array($tree), 'Elementor root must be an array.');
    $matches = array();
    harmat_area_walk($tree, $matches);
    harmat_area_require(count($matches) === 1, 'Expected exactly one widget 4520a1d.');
    $html = $matches[0]->settings->html;
    $old = '<span class="area-total-size">' . $from . " m\u{00B2}</span>";
    $new = '<span class="area-total-size">' . $to . " m\u{00B2}</span>";
    harmat_area_require(substr_count($html, 'area-total-size') === 1 && substr_count($html, $old) === 1,
        'Expected exactly one exact area-total-size span.');
    $updated_html = str_replace($old, $new, $html);
    // Replace the uniquely identified JSON string, preserving all unrelated raw bytes.
    $token = harmat_area_json($html);
    harmat_area_require(substr_count($raw, $token) === 1, 'Target HTML JSON token is not unique/canonical.');
    $updated = str_replace($token, harmat_area_json($updated_html), $raw);
    $matches[0]->settings->html = $updated_html;
    harmat_area_require(harmat_area_json(json_decode($updated, false, 512, JSON_THROW_ON_ERROR))
        === harmat_area_json($tree), 'Elementor repair exceeded widget HTML scope.');
    return $updated;
}

function harmat_area_values(string $content, string $raw, string $area, array $target): array
{
    harmat_area_require($area === $target['old'] || $area === $target['new'], 'Unknown sales override.');
    $state = $area === $target['old'] ? 'old' : 'new';
    $other = $state === 'old' ? 'new' : 'old';
    $other_content = harmat_area_content($content, $target[$state], $target[$other]);
    $other_raw = harmat_area_elementor($raw, $target[$state], $target[$other]);
    $original_content = $state === 'old' ? $content : $other_content;
    $original_raw = $state === 'old' ? $raw : $other_raw;
    harmat_area_require(hash('sha256', $original_content) === $target['content_sha256']
        && hash('sha256', $original_raw) === $target['elementor_sha256'], 'Source differs from pinned evidence.');
    return array('state' => $state, 'content' => $state === 'old' ? $other_content : $content,
        'elementor' => $state === 'old' ? $other_raw : $raw, 'area' => $target['new']);
}

function harmat_area_meta_index(array $rows, string $key): int
{
    $found = array();
    foreach ($rows as $index => $row) {
        if ($row['meta_key'] === $key) {
            $found[] = $index;
        }
    }
    harmat_area_require(count($found) === 1, 'Expected one metadata row: ' . $key);
    return $found[0];
}

function harmat_area_plan(array $snapshot): array
{
    $after = $snapshot;
    $changes = array();
    $states = array();
    foreach (harmat_area_targets() as $id => $target) {
        harmat_area_require(isset($snapshot['posts'][$id], $snapshot['meta'][$id]), 'Missing target property.');
        $post = $snapshot['posts'][$id];
        harmat_area_require((int) $post['ID'] === $id && $post['post_type'] === 'property'
            && $post['post_status'] === 'publish' && $post['post_title'] === $target['title']
            && $post['post_name'] === strtolower($target['title']), 'Property identity/status mismatch.');
        $rows = $snapshot['meta'][$id];
        $ei = harmat_area_meta_index($rows, '_elementor_data');
        $ai = harmat_area_meta_index($rows, '_harmat_sales_area');
        $values = harmat_area_values($post['post_content'], $rows[$ei]['meta_value'], $rows[$ai]['meta_value'], $target);
        $states[] = $values['state'];
        if ($values['state'] === 'new') {
            continue;
        }
        $after['posts'][$id]['post_content'] = $values['content'];
        $after['meta'][$id][$ei]['meta_value'] = $values['elementor'];
        $after['meta'][$id][$ai]['meta_value'] = $values['area'];
        $changes[] = array('table' => 'posts', 'id' => $id, 'before' => $post['post_content'], 'after' => $values['content']);
        foreach (array($ei, $ai) as $index) {
            $changes[] = array('table' => 'postmeta', 'id' => $id, 'meta_id' => (int) $rows[$index]['meta_id'],
                'key' => $rows[$index]['meta_key'], 'before' => $rows[$index]['meta_value'],
                'after' => $after['meta'][$id][$index]['meta_value']);
        }
    }
    harmat_area_require(count(array_unique($states)) === 1, 'Mixed target states; refusing partial batch.');
    return array('state' => $states[0], 'after' => $after, 'changes' => $changes);
}

function harmat_area_table_names($db): void
{
    foreach (array($db->posts, $db->postmeta) as $name) {
        harmat_area_require(preg_match('/^[A-Za-z0-9_]+$/D', $name) === 1, 'Unsafe table name.');
    }
}

function harmat_area_engines($db): void
{
    harmat_area_table_names($db);
    $rows = $db->get_results($db->prepare('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s)', $db->posts, $db->postmeta), ARRAY_A);
    harmat_area_require(is_array($rows) && count($rows) === 2, 'Cannot verify storage engines.');
    $names = array();
    foreach ($rows as $row) {
        harmat_area_require(strcasecmp($row['ENGINE'], 'InnoDB') === 0, 'Both tables must use InnoDB.');
        $names[] = $row['TABLE_NAME'];
    }
    sort($names);
    $expected = array($db->posts, $db->postmeta);
    sort($expected);
    harmat_area_require($names === $expected, 'Wrong storage-engine tables.');
}

function harmat_area_snapshot($db, bool $lock = false): array
{
    harmat_area_table_names($db);
    $suffix = $lock ? ' FOR UPDATE' : '';
    $posts = $db->get_results("SELECT * FROM `{$db->posts}` WHERE post_type = 'property' "
        . "AND post_status = 'publish' ORDER BY ID" . $suffix, ARRAY_A);
    harmat_area_require(is_array($posts) && count($posts) === 124, 'Expected exactly 124 published properties.');
    $snapshot = array('posts' => array(), 'meta' => array());
    foreach ($posts as $row) {
        $id = (int) $row['ID'];
        harmat_area_require($id > 0 && !isset($snapshot['posts'][$id]), 'Duplicate/invalid property ID.');
        $snapshot['posts'][$id] = $row;
        $snapshot['meta'][$id] = array();
    }
    $ids = implode(',', array_keys($snapshot['posts']));
    $meta = $db->get_results("SELECT * FROM `{$db->postmeta}` WHERE post_id IN ($ids) ORDER BY post_id, meta_id" . $suffix, ARRAY_A);
    harmat_area_require(is_array($meta), 'Cannot read property metadata.');
    foreach ($meta as $row) {
        harmat_area_require(isset($snapshot['posts'][(int) $row['post_id']]), 'Unexpected metadata property.');
        $snapshot['meta'][(int) $row['post_id']][] = $row;
    }
    return $snapshot;
}

function harmat_area_hash_map(array $snapshot): array
{
    $map = array();
    foreach ($snapshot['posts'] as $id => $post) {
        $map[$id] = array('post' => harmat_area_hash($post), 'meta' => harmat_area_hash($snapshot['meta'][$id]));
    }
    return $map;
}

function harmat_area_manifest(array $before, array $plan): array
{
    $targets = array('posts' => array(), 'meta' => array());
    foreach (harmat_area_targets() as $id => $target) {
        $targets['posts'][$id] = $before['posts'][$id];
        $targets['meta'][$id] = $before['meta'][$id];
    }
    return array('format' => 'harmat-sales-area-align-2026-10-09-v1', 'before' => $targets,
        'before_hashes' => harmat_area_hash_map($before), 'after_hashes' => harmat_area_hash_map($plan['after']),
        'changes' => $plan['changes']);
}

function harmat_area_validate_manifest(array $manifest): array
{
    harmat_area_require(($manifest['format'] ?? '') === 'harmat-sales-area-align-2026-10-09-v1'
        && isset($manifest['before'], $manifest['before_hashes'], $manifest['after_hashes'], $manifest['changes']), 'Invalid backup format.');
    $plan = harmat_area_plan($manifest['before']);
    harmat_area_require(array_keys($manifest['before']['posts']) === array_keys(harmat_area_targets())
        && array_keys($manifest['before']['meta']) === array_keys(harmat_area_targets()), 'Backup must contain exactly three target rows.');
    harmat_area_require($plan['state'] === 'old' && $plan['changes'] === $manifest['changes'], 'Invalid backup repair plan.');
    $before = $manifest['before_hashes'];
    $after = $manifest['after_hashes'];
    harmat_area_require(count($before) === 124 && array_keys($before) === array_keys($after), 'Invalid backup property hash map.');
    foreach ($before as $id => $hashes) {
        foreach (array('post', 'meta') as $field) {
            harmat_area_require(isset($hashes[$field], $after[$id][$field])
                && preg_match('/^[a-f0-9]{64}$/D', $hashes[$field]) === 1
                && preg_match('/^[a-f0-9]{64}$/D', $after[$id][$field]) === 1, 'Invalid property hash.');
        }
    }
    foreach (harmat_area_hash_map($manifest['before']) as $id => $hashes) {
        harmat_area_require(($before[$id] ?? null) === $hashes, 'Backup target hash mismatch.');
        $before[$id] = harmat_area_hash_map($plan['after'])[$id];
    }
    harmat_area_require($before === $after, 'Backup changes extend beyond the three targets.');
    return $plan;
}

function harmat_area_staging_entries(array $entries): void
{
    $allowed = array('repair.php', 'test.php', '2026-10-09-correct-sales-area.php', 'release.json', 'fixture.json');
    $seen = array();
    foreach ($entries as $entry) {
        harmat_area_require(in_array($entry['name'] ?? '', $allowed, true)
            && !isset($seen[$entry['name']]) && ($entry['regular'] ?? false) === true
            && ($entry['symlink'] ?? true) === false && ($entry['mode'] ?? 0) === 0600,
            'Backup directory contains an unknown, duplicate, nonregular, linked or unprotected staging entry.');
        $seen[$entry['name']] = true;
    }
}

function harmat_area_backup_path(string $path, bool $create): string
{
    harmat_area_require(preg_match('~^/home/harmath2/codex-backups/sales-area-align-[a-f0-9]{32}$~D', $path) === 1,
        'Backup path must use the exact private prefix plus 32 lowercase hex characters.');
    harmat_area_require(realpath(dirname($path)) === '/home/harmath2/codex-backups' && !is_link($path), 'Unsafe backup parent/path.');
    if ($create && !file_exists($path)) {
        harmat_area_require(mkdir($path, 0700), 'Cannot create backup directory.');
    }
    clearstatcache(true, $path);
    harmat_area_require(realpath($path) === $path && is_dir($path)
        && (fileperms($path) & 0777) === 0700, 'Backup directory must be real and mode 0700.');
    if ($create) {
        harmat_area_require(is_writable($path), 'Backup directory is not writable.');
        $names = scandir($path);
        harmat_area_require(is_array($names), 'Cannot inspect backup directory.');
        $entries = array();
        foreach ($names as $name) {
            if ($name !== '.' && $name !== '..') {
                $file = $path . '/' . $name;
                clearstatcache(true, $file);
                $entries[] = array('name' => $name, 'regular' => is_file($file), 'symlink' => is_link($file),
                    'mode' => fileperms($file) & 0777);
            }
        }
        harmat_area_staging_entries($entries);
    }
    return $path;
}

function harmat_area_write_private(string $file, string $bytes): void
{
    $old_umask = umask(0077);
    try {
        $handle = fopen($file, 'x');
        harmat_area_require($handle !== false, 'Backup file already exists or cannot be created.');
        try {
            harmat_area_require(fwrite($handle, $bytes) === strlen($bytes) && fflush($handle), 'Incomplete backup write.');
            if (function_exists('fsync')) {
                harmat_area_require(fsync($handle), 'Backup fsync failed.');
            }
        } finally {
            fclose($handle);
        }
        harmat_area_require(chmod($file, 0600), 'Cannot protect backup file.');
        clearstatcache(true, $file);
        harmat_area_require(!is_link($file) && (fileperms($file) & 0777) === 0600
            && hash_file('sha256', $file) === hash('sha256', $bytes), 'Backup file verification failed.');
    } finally {
        umask($old_umask);
    }
}

function harmat_area_read_backup(string $path): array
{
    harmat_area_backup_path($path, false);
    foreach (array('before.json', 'before.sha256') as $name) {
        $file = $path . '/' . $name;
        clearstatcache(true, $file);
        harmat_area_require(is_file($file) && !is_link($file) && is_readable($file)
            && (fileperms($file) & 0777) === 0600, 'Backup files must be real and mode 0600.');
    }
    $bytes = file_get_contents($path . '/before.json');
    $digest = file_get_contents($path . '/before.sha256');
    harmat_area_require(is_string($bytes) && is_string($digest)
        && preg_match('/^[a-f0-9]{64}\n$/D', $digest) === 1
        && hash_equals(trim($digest), hash('sha256', $bytes)), 'Backup manifest digest mismatch.');
    $manifest = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    harmat_area_require(is_array($manifest), 'Invalid backup JSON.');
    harmat_area_validate_manifest($manifest);
    return $manifest;
}

function harmat_area_save_backup(string $path, array $manifest): void
{
    harmat_area_validate_manifest($manifest);
    harmat_area_backup_path($path, true);
    $bytes = harmat_area_json($manifest);
    harmat_area_write_private($path . '/before.json', $bytes);
    harmat_area_write_private($path . '/before.sha256', hash('sha256', $bytes) . "\n");
    harmat_area_require(harmat_area_read_backup($path) === $manifest, 'Backup readback mismatch.');
}

function harmat_area_rollback_plan(array $current, array $manifest): array
{
    harmat_area_validate_manifest($manifest);
    $plan = harmat_area_plan($current);
    if ($plan['state'] === 'old') {
        return array('state' => 'old', 'after' => $current, 'changes' => array());
    }
    $after = $current;
    $changes = array();
    foreach ($manifest['changes'] as $change) {
        $id = $change['id'];
        if ($change['table'] === 'posts') {
            harmat_area_require($current['posts'][$id]['post_content'] === $change['after'], 'Rollback content guard failed.');
            $after['posts'][$id]['post_content'] = $change['before'];
        } else {
            $index = harmat_area_meta_index($current['meta'][$id], $change['key']);
            $row = $current['meta'][$id][$index];
            harmat_area_require((int) $row['meta_id'] === $change['meta_id'] && $row['meta_value'] === $change['after'],
                'Rollback metadata guard failed.');
            $after['meta'][$id][$index]['meta_value'] = $change['before'];
        }
        $changes[] = array_merge($change, array('before' => $change['after'], 'after' => $change['before']));
    }
    return array('state' => 'new', 'after' => $after, 'changes' => $changes);
}

function harmat_area_cas($db, array $change): void
{
    harmat_area_require(isset(harmat_area_targets()[$change['id'] ?? 0])
        && is_string($change['before'] ?? null) && is_string($change['after'] ?? null), 'Forbidden mutation target/value.');
    if ($change['table'] === 'posts') {
        $sql = $db->prepare("UPDATE `{$db->posts}` SET post_content = %s WHERE ID = %d AND BINARY post_content = BINARY %s",
            $change['after'], $change['id'], $change['before']);
    } else {
        harmat_area_require($change['table'] === 'postmeta'
            && in_array($change['key'], array('_elementor_data', '_harmat_sales_area'), true), 'Forbidden mutation key/table.');
        $sql = $db->prepare("UPDATE `{$db->postmeta}` SET meta_value = %s WHERE meta_id = %d AND post_id = %d "
            . 'AND BINARY meta_key = BINARY %s AND BINARY meta_value = BINARY %s',
            $change['after'], $change['meta_id'], $change['id'], $change['key'], $change['before']);
    }
    harmat_area_require($db->query($sql) === 1, 'Compare-and-swap failed; transaction must roll back.');
}

function harmat_area_transaction($db, bool $rollback, string $path, ?array $manifest = null,
    string $backup_writer = 'harmat_area_save_backup'): string
{
    harmat_area_engines($db);
    harmat_area_require($db->query('START TRANSACTION') !== false, 'Cannot begin transaction.');
    $before = null;
    try {
        $before = harmat_area_snapshot($db, true);
        $plan = $rollback ? harmat_area_rollback_plan($before, $manifest ?? array()) : harmat_area_plan($before);
        if (($rollback && $plan['state'] === 'old') || (!$rollback && $plan['state'] === 'new')) {
            harmat_area_require($db->query('ROLLBACK') !== false, 'Cannot end no-op transaction.');
            return $rollback ? 'ALREADY_RESTORED' : 'ALREADY_CORRECTED';
        }
        if (!$rollback) {
            $backup_writer($path, harmat_area_manifest($before, $plan));
        }
        foreach ($plan['changes'] as $change) {
            harmat_area_cas($db, $change);
        }
        $after = harmat_area_snapshot($db, true);
        harmat_area_require($after === $plan['after']
            && harmat_area_hash_map($after) === harmat_area_hash_map($plan['after']),
            'Full 124-property post/metadata scope verification failed.');
        harmat_area_require($db->query('COMMIT') !== false, 'Commit failed.');
    } catch (Throwable $error) {
        $restored = $db->query('ROLLBACK') !== false;
        if ($restored && $before !== null) {
            try {
                $restored = harmat_area_snapshot($db) === $before;
            } catch (Throwable $verification_error) {
                $restored = false;
            }
        }
        throw new RuntimeException($error->getMessage() . ($restored ? ' Transaction rollback verified.'
            : ' ROLLBACK NOT VERIFIED; inspect backup before further writes.'), 0, $error);
    }
    return $rollback ? 'RESTORED_EXACT_SCOPE' : 'APPLIED_EXACT_SCOPE';
}

function harmat_area_purge(): void
{
    foreach (array_keys(harmat_area_targets()) as $id) {
        delete_post_meta($id, '_elementor_element_cache');
        clean_post_cache($id);
    }
    if (function_exists('harmat_lakas_redesign_clear_cache')) {
        harmat_lakas_redesign_clear_cache();
    }
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
    wp_cache_flush();
}

function harmat_area_main(): void
{
    global $wpdb;
    $apply = getenv('HARMAT_APPLY') === '1';
    $rollback = getenv('HARMAT_ROLLBACK') === '1';
    harmat_area_require(!($apply && $rollback), 'Apply and rollback modes are mutually exclusive.');
    $path = (string) getenv('HARMAT_BACKUP_DIR');
    harmat_area_engines($wpdb);
    if (!$apply && !$rollback) {
        $snapshot = harmat_area_snapshot($wpdb);
        $plan = harmat_area_plan($snapshot);
        WP_CLI::log(harmat_area_json(array('mode' => 'dry-run', 'state' => $plan['state'],
            'property_hashes' => harmat_area_hash_map($snapshot), 'changes' => count($plan['changes']))));
        WP_CLI::success('DRY_RUN_PASSED; no writes or cache operations.');
        return;
    }
    $manifest = $rollback ? harmat_area_read_backup($path) : null;
    if (!$rollback) {
        harmat_area_require(preg_match('~^/home/harmath2/codex-backups/sales-area-align-[a-f0-9]{32}$~D', $path) === 1, 'Invalid backup path.');
    }
    $result = harmat_area_transaction($wpdb, $rollback, $path, $manifest);
    WP_CLI::log($result . '; BACKUP=' . $path);
    if ($result === 'APPLIED_EXACT_SCOPE' || $result === 'RESTORED_EXACT_SCOPE') {
        try {
            harmat_area_purge();
        } catch (Throwable $error) {
            throw new RuntimeException($result . ' COMMITTED; cache purge failed: ' . $error->getMessage()
                . '. Database was not rolled back; use the verified backup for guarded rollback.', 0, $error);
        }
    }
    WP_CLI::success($result);
}

if (defined('HARMAT_SALES_AREA_TESTS_ONLY') && HARMAT_SALES_AREA_TESTS_ONLY) {
    return;
}
try {
    harmat_area_main();
} catch (Throwable $error) {
    WP_CLI::error($error->getMessage());
}
