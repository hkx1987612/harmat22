<?php
// WP-CLI only. Private backup + recoverable trash; never permanently deletes.
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') {
    throw new RuntimeException('CLI only');
}

function harmat_spam_trash_require($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, 'HARMAT_SPAM_TRASH_GUARD:' . json_encode(array('guard' => $message)) . PHP_EOL);
        throw new RuntimeException($message);
    }
}

function harmat_spam_trash_fingerprint($wpdb, $excluded, $cutoff) {
    $ids = implode(',', array_map('intval', $excluded));
    $where = "p.ID <= " . (int) $cutoff . " AND p.ID NOT IN ($ids) AND p.post_type IN ('property','harmat_offer_lead')";
    $posts = $wpdb->get_results("SELECT p.* FROM {$wpdb->posts} p WHERE $where ORDER BY p.ID", ARRAY_A);
    $meta = $wpdb->get_results("SELECT m.* FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE $where AND m.meta_key<>'_elementor_element_cache' ORDER BY m.meta_id", ARRAY_A);
    $options = $wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ('harmat_sales_deals_v1','harmat_broker_leads_v1','harmat_sales_activity_log_v1','harmat_sales_trash_v1') ORDER BY option_name", ARRAY_A);
    harmat_spam_trash_require($wpdb->last_error === '', 'Protected snapshot read failed');
    return array('posts' => hash('sha256', serialize($posts)), 'meta' => hash('sha256', serialize($meta)), 'crm_options' => hash('sha256', serialize($options)));
}

function harmat_spam_trash_write($path, $value) {
    $data = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    harmat_spam_trash_require(is_string($data), 'Backup encoding failed');
    $old = umask(0077);
    $file = fopen($path, 'x+b');
    umask($old);
    harmat_spam_trash_require($file !== false, 'Exclusive private backup creation failed');
    try {
        harmat_spam_trash_require(fwrite($file, $data) === strlen($data) && fflush($file), 'Backup write failed');
    } finally {
        fclose($file);
    }
    harmat_spam_trash_require((fileperms($path) & 0777) === 0600 && hash_file('sha256', $path) === hash('sha256', $data), 'Backup verification failed');
}

global $wpdb;
$mode = isset($args[0]) ? $args[0] : '';
$backup = isset($args[1]) ? $args[1] : '';
$expected = isset($args[2]) ? $args[2] : '';
harmat_spam_trash_require(in_array($mode, array('preview', 'trash'), true), 'Explicit preview/trash mode required');
harmat_spam_trash_require(defined('EMPTY_TRASH_DAYS') && EMPTY_TRASH_DAYS > 0, 'Recoverable trash must be enabled');

$rows = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type='harmat_offer_lead' AND post_status='private' AND post_date>='2026-10-15 00:00:00' AND post_date<'2026-10-16 00:00:00' AND LOWER(post_title) LIKE '%https://www.google.com/share.google%' ORDER BY ID", ARRAY_A);
harmat_spam_trash_require($wpdb->last_error === '' && count($rows) === 120, 'Exact reviewed120-record scope changed; stop for inspection');
$ids = array_map('intval', array_column($rows, 'ID'));
$id_sql = implode(',', $ids);
$metadata = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ($id_sql) ORDER BY meta_id", ARRAY_A);
harmat_spam_trash_require($wpdb->last_error === '', 'Target metadata read failed');
$by_id = array();
foreach ($metadata as $item) {
    $by_id[(int) $item['post_id']][$item['meta_key']][] = $item['meta_value'];
}
foreach ($ids as $id) {
    foreach ($by_id[$id]['_harmat_assigned_sales_id'] ?? array() as $value) {
        harmat_spam_trash_require((int) $value === 0, 'Assigned inquiry excluded');
    }
    foreach ($by_id[$id]['_harmat_offer_apartment'] ?? array() as $value) {
        harmat_spam_trash_require($value === '', 'Property-linked inquiry excluded');
    }
    $mail = $by_id[$id]['_harmat_offer_mail_status'] ?? array();
    harmat_spam_trash_require(count($mail) === 1 && in_array($mail[0], array('sent', 'sent-to-provider-sync'), true), 'Unsettled mail record excluded');
    harmat_spam_trash_require(!wp_next_scheduled('harmat_sales_public_offer_mail', array($id)), 'Pending mail job excluded');
}
$deals_raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='harmat_sales_deals_v1'");
$deals = $deals_raw === null ? array() : maybe_unserialize($deals_raw);
harmat_spam_trash_require(is_array($deals) && $wpdb->last_error === '', 'CRM deals shape/read changed');
foreach ($deals as $deal) {
    harmat_spam_trash_require(!is_array($deal) || !in_array((int) ($deal['inquiry_id'] ?? 0), $ids, true), 'Sales-linked inquiry excluded');
}
harmat_spam_trash_require((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID IN ($id_sql)") === 0, 'Comment-bearing record excluded');
harmat_spam_trash_require((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_parent IN ($id_sql)") === 0, 'Parent record excluded');
$hash = hash('sha256', serialize(array($rows, $metadata)));
$cutoff = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
$protected = harmat_spam_trash_fingerprint($wpdb, $ids, $cutoff);
$summary = array('scope' => 'EXACT120_PROMOTIONAL_URL_NAMES_ONLY', 'target_count' => count($ids), 'target_snapshot_sha256' => $hash, 'trash_retention_days' => (int) EMPTY_TRASH_DAYS, 'protected' => $protected, 'customer_content_exported' => false);
if ($mode === 'preview') {
    echo wp_json_encode(array_merge($summary, array('status' => 'READ_ONLY_PREVIEW')));
    return;
}
harmat_spam_trash_require(hash_equals($hash, $expected), 'Reviewed target snapshot changed');
harmat_spam_trash_require(preg_match('~^/home/harmath2/codex-backups/offer-spam-trash-[a-f0-9]{32}$~D', $backup) === 1 && is_dir($backup) && !is_link($backup) && (fileperms($backup) & 0777) === 0700, 'Exact private backup directory required');
harmat_spam_trash_require(!file_exists($backup . '/result.json') && !file_exists($backup . '/originals.json'), 'Prior operation exists; inspect state instead of retrying');
$engines = $wpdb->get_results($wpdb->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (%s,%s,%s)", $wpdb->posts, $wpdb->postmeta, $wpdb->options), ARRAY_A);
harmat_spam_trash_require(count($engines) === 3 && count(array_filter($engines, static function ($row) { return $row['ENGINE'] === 'InnoDB'; })) === 3, 'Transactional tables required');
harmat_spam_trash_write($backup . '/originals.json', array('posts' => $rows, 'metadata' => $metadata, 'protected' => $protected, 'cutoff' => $cutoff, 'target_hash' => $hash, 'scope' => $summary['scope']));
$committed = false;
try {
    harmat_spam_trash_require($wpdb->query('START TRANSACTION') !== false, 'Transaction start failed');
    $locked = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE ID IN ($id_sql) ORDER BY ID FOR UPDATE", ARRAY_A);
    $locked_meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ($id_sql) ORDER BY meta_id FOR UPDATE", ARRAY_A);
    harmat_spam_trash_require(hash_equals($hash, hash('sha256', serialize(array($locked, $locked_meta)))), 'Target changed before trash');
    $locked_options = $wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ('harmat_sales_deals_v1','harmat_broker_leads_v1','harmat_sales_activity_log_v1','harmat_sales_trash_v1') ORDER BY option_name FOR UPDATE", ARRAY_A);
    harmat_spam_trash_require(hash('sha256', serialize($locked_options)) === $protected['crm_options'], 'CRM options changed before trash');
    foreach ($ids as $id) {
        harmat_spam_trash_require(wp_trash_post($id) instanceof WP_Post, 'Native recoverable trash failed');
    }
    $after = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE ID IN ($id_sql) ORDER BY ID", ARRAY_A);
    harmat_spam_trash_require(count($after) === 120, 'Target count changed');
    foreach ($after as $index => $row) {
        harmat_spam_trash_require($row['post_status'] === 'trash', 'Trash status not verified');
        if ($row['guid'] !== $rows[$index]['guid']) {
            // Keep pre-existing GUID bytes if core only normalized HTML entities.
            $decode = static function ($value) { return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); };
            harmat_spam_trash_require($decode($row['guid']) === $decode($rows[$index]['guid']), 'Non-equivalent GUID change');
            harmat_spam_trash_require($wpdb->update($wpdb->posts, array('guid' => $rows[$index]['guid']), array('ID' => (int) $row['ID'], 'post_status' => 'trash', 'guid' => $row['guid']), array('%s'), array('%d', '%s', '%s')) === 1, 'Own GUID normalization restore failed');
            $row['guid'] = $wpdb->get_var($wpdb->prepare("SELECT guid FROM {$wpdb->posts} WHERE ID=%d", (int) $row['ID']));
        }
        foreach ($rows[$index] as $key => $value) {
            if (!in_array($key, array('post_status', 'post_modified', 'post_modified_gmt', 'post_name'), true)) {
                harmat_spam_trash_require($row[$key] === $value, 'Non-status customer data changed: ' . $key);
            }
        }
    }
    $original_meta_ids = implode(',', array_map('intval', array_column($metadata, 'meta_id')));
    $old_after = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE meta_id IN ($original_meta_ids) ORDER BY meta_id", ARRAY_A);
    harmat_spam_trash_require($old_after === $metadata, 'Original contact metadata changed');
    harmat_spam_trash_require(harmat_spam_trash_fingerprint($wpdb, $ids, $cutoff) === $protected, 'Non-target customer/property/CRM data changed');
    harmat_spam_trash_require($wpdb->query('COMMIT') !== false, 'Commit failed; inspect before any retry');
    $committed = true;
} finally {
    if (!$committed) {
        $wpdb->query('ROLLBACK');
    }
    foreach ($ids as $id) {
        clean_post_cache($id);
    }
}
$result = array_merge($summary, array('status' => 'RECOVERABLY_TRASHED', 'backup' => $backup, 'non_target_data_unchanged' => true, 'permanent_deletion' => false, 'test_inquiry_or_mail_sent' => false));
harmat_spam_trash_write($backup . '/result.json', $result);
echo wp_json_encode($result);
