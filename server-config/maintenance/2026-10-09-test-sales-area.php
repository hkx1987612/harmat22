<?php
/** Standalone, no WordPress boot or database connection. Optional argv[1]: public evidence JSON. */
define('HARMAT_SALES_AREA_TESTS_ONLY', true);
define('ARRAY_A', 'ARRAY_A');
require __DIR__ . '/2026-10-09-correct-sales-area.php';

$harmat_area_assertions = 0;
$harmat_area_test_backup = null;
$harmat_area_test_backup_fail = false;
$harmat_area_test_purges = array();

class WP_CLI
{
    public static array $messages = array();
    public static function log(string $message): void
    {
        self::$messages[] = $message;
    }
    public static function success(string $message): void
    {
        self::$messages[] = $message;
    }
}

function harmat_area_test_assert(bool $condition, string $message): void
{
    global $harmat_area_assertions;
    $harmat_area_assertions++;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

function harmat_area_test_throws(string $function, array $args, string $message): void
{
    try {
        $function(...$args);
    } catch (Throwable $error) {
        harmat_area_test_assert(true, $message);
        return;
    }
    harmat_area_test_assert(false, $message);
}

function harmat_area_test_save_backup(string $path, array $manifest): void
{
    global $harmat_area_test_backup, $harmat_area_test_backup_fail;
    harmat_area_validate_manifest($manifest);
    harmat_area_test_assert($path === 'MOCK_NO_FILESYSTEM', 'Mock backup path, not a real server path.');
    if ($harmat_area_test_backup_fail) {
        throw new RuntimeException('Simulated backup failure.');
    }
    $harmat_area_test_backup = $manifest;
}

function clean_post_cache($id): void
{
    $GLOBALS['harmat_area_test_purges'][] = 'post:' . $id;
}
function delete_post_meta($id, $key): bool
{
    harmat_area_test_assert(isset(harmat_area_targets()[$id]), 'Derived cache invalidation targets only the three properties.');
    harmat_area_test_assert($key === '_elementor_element_cache', 'Only the derived Elementor element cache key may be deleted.');
    $GLOBALS['harmat_area_test_purges'][] = 'element:' . $id;
    $db = $GLOBALS['wpdb'];
    $rows = array();
    $deleted = false;
    foreach ($db->data['meta'][$id] as $row) {
        if ($row['meta_key'] === $key) {
            $deleted = true;
        } else {
            $rows[] = $row;
        }
    }
    $db->data['meta'][$id] = $rows;
    return $deleted;
}
function harmat_lakas_redesign_clear_cache(): void
{
    $GLOBALS['harmat_area_test_purges'][] = 'search';
}
function wp_cache_clear_cache(): void
{
    $GLOBALS['harmat_area_test_purges'][] = 'page';
}
function wp_cache_flush(): void
{
    $GLOBALS['harmat_area_test_purges'][] = 'object';
}

class HarmatAreaMockDB
{
    public string $posts = 'fixture_posts';
    public string $postmeta = 'fixture_postmeta';
    public array $data;
    public array $log = array();
    public ?array $saved = null;
    public int $writes = 0;
    public int $fail_write = 0;
    public bool $fail_commit = false;
    public bool $fail_start = false;
    public bool $fail_read = false;
    public string $engine = 'InnoDB';
    public string $tamper = '';

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function prepare(string $sql, ...$args): string
    {
        return 'PREPARED:' . harmat_area_json(array($sql, $args));
    }

    public function get_results(string $sql, $format)
    {
        if (strpos($sql, 'information_schema.TABLES') !== false) {
            return array(array('TABLE_NAME' => $this->posts, 'ENGINE' => $this->engine),
                array('TABLE_NAME' => $this->postmeta, 'ENGINE' => $this->engine));
        }
        if ($this->fail_read) {
            return null;
        }
        $this->log[] = strpos($sql, 'FOR UPDATE') !== false ? 'LOCKED_READ' : 'READ';
        if (strpos($sql, '`' . $this->posts . '`') !== false) {
            return array_values($this->data['posts']);
        }
        $rows = array();
        foreach ($this->data['meta'] as $meta) {
            foreach ($meta as $row) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function query(string $sql)
    {
        if ($sql === 'START TRANSACTION') {
            $this->log[] = 'START';
            if ($this->fail_start) {
                return false;
            }
            $this->saved = $this->data;
            return 0;
        }
        if ($sql === 'ROLLBACK') {
            $this->log[] = 'ROLLBACK';
            if ($this->saved !== null) {
                $this->data = $this->saved;
            }
            $this->saved = null;
            return 0;
        }
        if ($sql === 'COMMIT') {
            $this->log[] = 'COMMIT';
            if ($this->fail_commit) {
                return false;
            }
            $this->saved = null;
            return 0;
        }
        harmat_area_require(strpos($sql, 'PREPARED:') === 0, 'Unexpected mock SQL.');
        [$template, $args] = json_decode(substr($sql, 9), true, 512, JSON_THROW_ON_ERROR);
        $this->writes++;
        $this->log[] = 'WRITE:' . $this->writes;
        if ($this->fail_write === $this->writes) {
            return 0;
        }
        if (strpos($template, 'SET post_content') !== false) {
            [$after, $id, $before] = $args;
            if (($this->data['posts'][$id]['post_content'] ?? null) !== $before) {
                return 0;
            }
            $this->data['posts'][$id]['post_content'] = $after;
        } else {
            [$after, $meta_id, $id, $key, $before] = $args;
            $found = false;
            foreach ($this->data['meta'][$id] as &$row) {
                if ((int) $row['meta_id'] === $meta_id && (int) $row['post_id'] === $id
                    && $row['meta_key'] === $key && $row['meta_value'] === $before) {
                    $row['meta_value'] = $after;
                    $found = true;
                    break;
                }
            }
            unset($row);
            if (!$found) {
                return 0;
            }
        }
        if ($this->writes === 9) {
            if ($this->tamper === 'other-post') {
                $this->data['posts'][10000]['post_content'] .= 'unexpected';
            } elseif ($this->tamper === 'modified-date') {
                $this->data['posts'][4349]['post_modified'] = '2099-01-01 00:00:00';
            } elseif ($this->tamper === 'price') {
                $this->data['meta'][4349][2]['meta_value'] = '1';
            } elseif ($this->tamper === 'cache-meta') {
                $this->data['meta'][10000][] = array('meta_id' => '99999', 'post_id' => '10000',
                    'meta_key' => '_elementor_css', 'meta_value' => 'unexpected');
            }
        }
        return 1;
    }
}

function harmat_area_test_pure(): void
{
    $label = "\u{00C9}rt\u{00E9}kes\u{00ED}tett alapter\u{00FC}let";
    foreach (harmat_area_targets() as $id => $target) {
        $html = '<span class="area-size">12.34 m' . "\u{00B2}</span>\r\n"
            . '<span class="area-total-size">' . $target['old'] . " m\u{00B2}</span>";
        $node = array('id' => '4520a1d', 'elType' => 'widget', 'widgetType' => 'html',
            'settings' => array('html' => $html), 'elements' => array());
        $tree = array(array('id' => 'parent', 'settings' => new stdClass(), 'elements' => array($node)));
        $raw = harmat_area_json($tree);
        $content = "Room detail\n12.34 m\u{00B2}\n" . $label . "\n" . $target['old'] . " m\u{00B2}\nunchanged";
        $target['content_sha256'] = hash('sha256', $content);
        $target['elementor_sha256'] = hash('sha256', $raw);
        $new = harmat_area_values($content, $raw, $target['old'], $target);
        harmat_area_test_assert($new['state'] === 'old' && $new['area'] === $target['new'], 'Forward fixture ' . $id);
        harmat_area_test_assert(str_replace($target['new'] . " m\u{00B2}", $target['old'] . " m\u{00B2}", $new['content']) === $content,
            'Only total changes in plain content.');
        harmat_area_test_assert(harmat_area_elementor($new['elementor'], $target['new'], $target['old']) === $raw,
            'Raw Elementor exact reverse, including empty object.');
        $again = harmat_area_values($new['content'], $new['elementor'], $target['new'], $target);
        harmat_area_test_assert($again === array('state' => 'new', 'content' => $new['content'],
            'elementor' => $new['elementor'], 'area' => $target['new']), 'Already-new is idempotent.');
        harmat_area_test_throws('harmat_area_values', array($content, $raw, '999.99', $target), 'Unknown override.');
        harmat_area_test_throws('harmat_area_values', array($content, $new['elementor'], $target['old'], $target), 'Mixed HTML state.');
        harmat_area_test_throws('harmat_area_values', array($new['content'], $raw, $target['old'], $target), 'Mixed content state.');
        harmat_area_test_throws('harmat_area_values', array($content, $raw, $target['new'], $target), 'Mixed override state.');
        harmat_area_test_throws('harmat_area_values', array($content . 'edit', $raw, $target['old'], $target), 'Pinned content mismatch.');
        harmat_area_test_throws('harmat_area_values', array($content, $raw . ' ', $target['old'], $target), 'Pinned raw mismatch.');
        harmat_area_test_throws('harmat_area_content', array($content . $label, $target['old'], $target['new']), 'Duplicate plain total.');
        harmat_area_test_throws('harmat_area_content', array(str_replace("\n", "\r\n", $content), $target['old'], $target['new']), 'Unexpected line endings.');
        harmat_area_test_throws('harmat_area_elementor', array('{bad', $target['old'], $target['new']), 'Malformed JSON.');
        harmat_area_test_throws('harmat_area_elementor', array('{}', $target['old'], $target['new']), 'Wrong root.');
        harmat_area_test_throws('harmat_area_elementor', array('[]', $target['old'], $target['new']), 'Missing widget.');
        harmat_area_test_throws('harmat_area_elementor', array(harmat_area_json(array($node, $node)), $target['old'], $target['new']), 'Duplicate widget.');
        $bad = $node;
        $bad['widgetType'] = 'heading';
        harmat_area_test_throws('harmat_area_elementor', array(harmat_area_json(array($bad)), $target['old'], $target['new']), 'Wrong widget.');
        $bad = $node;
        $bad['settings']['html'] .= '<span class="area-total-size">' . $target['old'] . " m\u{00B2}</span>";
        harmat_area_test_throws('harmat_area_elementor', array(harmat_area_json(array($bad)), $target['old'], $target['new']), 'Duplicate span.');
        $bad['settings']['html'] = str_replace('area-total-size', 'other-size', $html);
        harmat_area_test_throws('harmat_area_elementor', array(harmat_area_json(array($bad)), $target['old'], $target['new']), 'Missing span.');
    }
    $allowed = array();
    foreach (array('repair.php', 'test.php', '2026-10-09-correct-sales-area.php', 'release.json', 'fixture.json') as $name) {
        $allowed[] = array('name' => $name, 'regular' => true, 'symlink' => false, 'mode' => 0600);
    }
    harmat_area_staging_entries(array());
    harmat_area_test_assert(true, 'Empty new directory allowed.');
    harmat_area_staging_entries($allowed);
    harmat_area_test_assert(true, 'Only all five approved staging files allowed.');
    foreach ($allowed as $entry) {
        harmat_area_staging_entries(array($entry));
        harmat_area_test_assert(true, 'Approved staging subset allowed.');
        $bad = $entry;
        $bad['symlink'] = true;
        harmat_area_test_throws('harmat_area_staging_entries', array(array($bad)), 'Each staging symlink blocked.');
        $bad = $entry;
        $bad['regular'] = false;
        harmat_area_test_throws('harmat_area_staging_entries', array(array($bad)), 'Each staging subdirectory blocked.');
        $bad = $entry;
        $bad['mode'] = 0644;
        harmat_area_test_throws('harmat_area_staging_entries', array(array($bad)), 'Each public staging file blocked.');
    }
    foreach (array('unknown', 'before.json', 'before.sha256', 'repair.php.tmp') as $name) {
        $bad = $allowed;
        $bad[] = array('name' => $name, 'regular' => true, 'symlink' => false, 'mode' => 0600);
        harmat_area_test_throws('harmat_area_staging_entries', array($bad), 'Unknown/preexisting backup entry blocked: ' . $name);
    }
    harmat_area_test_throws('harmat_area_staging_entries', array(array($allowed[0], $allowed[0])), 'Duplicate staging entry blocked.');
    foreach (array('', '/tmp/sales-area-align-' . str_repeat('a', 32),
        '/home/harmath2/codex-backups/sales-area-align-' . str_repeat('a', 31),
        '/home/harmath2/codex-backups/sales-area-align-' . str_repeat('A', 32),
        '/home/harmath2/codex-backups/sales-area-align-' . str_repeat('a', 32) . '/..') as $path) {
        harmat_area_test_throws('harmat_area_backup_path', array($path, true), 'Unsafe backup path blocked.');
    }
}

function harmat_area_test_snapshot(array $evidence): array
{
    $snapshot = array('posts' => array(), 'meta' => array());
    $ids = array_merge(array_keys(harmat_area_targets()), range(10000, 10120));
    sort($ids);
    foreach ($ids as $id) {
        $v = $evidence[$id] ?? array('title' => 'FIXTURE-' . $id, 'slug' => 'fixture-' . $id, 'content' => 'Public fixture',
            'elementor' => '[]', 'area' => '1.00', 'price' => '100', 'unitPrice' => '100');
        $snapshot['posts'][$id] = array('ID' => (string) $id, 'post_type' => 'property', 'post_status' => 'publish',
            'post_title' => $v['title'], 'post_name' => $v['slug'], 'post_content' => $v['content'],
            'post_modified' => '2026-01-01 00:00:00', 'post_modified_gmt' => '2026-01-01 00:00:00', 'post_author' => '1');
        $snapshot['meta'][$id] = array();
        $values = array('_elementor_data' => $v['elementor'], '_harmat_sales_area' => $v['area'],
            'property_price' => $v['price'], '_harmat_sales_unit_price' => $v['unitPrice'],
            'property_status' => 'current', '_elementor_css' => 'cachefixture');
        if (isset(harmat_area_targets()[$id])) {
            $values['_elementor_element_cache'] = 'derived fixture cache';
        }
        $index = 0;
        foreach ($values as $key => $value) {
            $snapshot['meta'][$id][] = array('meta_id' => (string) ($id * 10 + $index++), 'post_id' => (string) $id,
                'meta_key' => $key, 'meta_value' => $value);
        }
    }
    return $snapshot;
}

function harmat_area_test_transactions(array $evidence): void
{
    global $harmat_area_test_backup, $harmat_area_test_backup_fail;
    $before = harmat_area_test_snapshot($evidence);
    $plan = harmat_area_plan($before);
    $after = $plan['after'];
    harmat_area_test_assert($plan['state'] === 'old' && count($plan['changes']) === 9, 'Exactly nine field writes.');
    harmat_area_test_assert(count(harmat_area_hash_map($before)) === 124, 'All 124 posts/meta hash map.');
    $expected = $before;
    foreach (harmat_area_targets() as $id => $target) {
        $v = harmat_area_values($evidence[$id]['content'], $evidence[$id]['elementor'], $target['old'], $target);
        $expected['posts'][$id]['post_content'] = $v['content'];
        $expected['meta'][$id][0]['meta_value'] = $v['elementor'];
        $expected['meta'][$id][1]['meta_value'] = $v['area'];
        harmat_area_test_assert(hash('sha256', $evidence[$id]['content']) === $target['content_sha256'], 'Real content pin.');
        harmat_area_test_assert(hash('sha256', $evidence[$id]['elementor']) === $target['elementor_sha256'], 'Real Elementor pin.');
    }
    harmat_area_test_assert($expected === $after, 'Every row, date, price, status and cachemeta otherwise unchanged.');
    $noop = harmat_area_plan($after);
    harmat_area_test_assert($noop['state'] === 'new' && $noop['after'] === $after && $noop['changes'] === array(), 'Whole-batch all-new idempotence.');
    $manifest = harmat_area_manifest($before, $plan);
    harmat_area_test_assert(harmat_area_validate_manifest($manifest)['changes'] === $plan['changes'], 'Manifest reconstructs exact pinned plan.');
    harmat_area_test_assert(harmat_area_rollback_plan($after, $manifest)['after'] === $before, 'Rollback exact original bytes/rows.');
    harmat_area_test_assert(harmat_area_rollback_plan($before, $manifest)['changes'] === array(), 'Already-restored rollback no-op.');
    $operational = $after;
    $operational['meta'][4349][5]['meta_value'] = 'post-purge-cache';
    $operational['posts'][10000]['post_content'] = 'Later independent change';
    $restored = harmat_area_rollback_plan($operational, $manifest)['after'];
    harmat_area_test_assert($restored['meta'][4349][5]['meta_value'] === 'post-purge-cache'
        && $restored['posts'][10000]['post_content'] === 'Later independent change', 'Rollback preserves unrelated later edits/cachemeta.');
    foreach (array('identity', 'missing-meta', 'duplicate-meta', 'mixed-unit', 'mixed-batch', 'changed-source') as $case) {
        $bad = $before;
        if ($case === 'identity') $bad['posts'][4349]['post_title'] = 'WRONG';
        if ($case === 'missing-meta') array_shift($bad['meta'][4349]);
        if ($case === 'duplicate-meta') $bad['meta'][4349][] = $bad['meta'][4349][1];
        if ($case === 'mixed-unit') $bad['meta'][4349][1]['meta_value'] = '55.81';
        if ($case === 'mixed-batch') {
            $bad['posts'][4349] = $after['posts'][4349];
            $bad['meta'][4349] = $after['meta'][4349];
        }
        if ($case === 'changed-source') $bad['posts'][4349]['post_content'] .= 'independent edit';
        harmat_area_test_throws('harmat_area_plan', array($bad), 'Plan rejects ' . $case);
        $db = new HarmatAreaMockDB($bad);
        harmat_area_test_throws('harmat_area_transaction', array($db, false, 'MOCK_NO_FILESYSTEM', null, 'harmat_area_test_save_backup'),
            'Transaction rejects ' . $case);
        harmat_area_test_assert($db->data === $bad && $db->writes === 0 && in_array('ROLLBACK', $db->log, true), 'Guard failure has zero writes/rollback.');
    }
    for ($n = 1; $n <= 9; $n++) {
        $db = new HarmatAreaMockDB($before);
        $db->fail_write = $n;
        harmat_area_test_throws('harmat_area_transaction', array($db, false, 'MOCK_NO_FILESYSTEM', null, 'harmat_area_test_save_backup'),
            'CAS failure at write ' . $n);
        harmat_area_test_assert($db->data === $before && $db->writes === $n && in_array('ROLLBACK', $db->log, true)
            && !in_array('COMMIT', $db->log, true), 'CAS failure restores all prior writes, including ninth-write failure.');
    }
    foreach (array('other-post', 'modified-date', 'price', 'cache-meta') as $tamper) {
        $db = new HarmatAreaMockDB($before);
        $db->tamper = $tamper;
        harmat_area_test_throws('harmat_area_transaction', array($db, false, 'MOCK_NO_FILESYSTEM', null, 'harmat_area_test_save_backup'),
            'Scope verification detects ' . $tamper);
        harmat_area_test_assert($db->data === $before && in_array('ROLLBACK', $db->log, true), 'Scope violation fully rolls back.');
    }
    $db = new HarmatAreaMockDB($before);
    $db->fail_commit = true;
    harmat_area_test_throws('harmat_area_transaction', array($db, false, 'MOCK_NO_FILESYSTEM', null, 'harmat_area_test_save_backup'), 'Failed commit rolls back.');
    harmat_area_test_assert($db->data === $before && in_array('ROLLBACK', $db->log, true), 'Failed commit exact restore.');
    $harmat_area_test_backup_fail = true;
    $db = new HarmatAreaMockDB($before);
    harmat_area_test_throws('harmat_area_transaction', array($db, false, 'MOCK_NO_FILESYSTEM', null, 'harmat_area_test_save_backup'), 'Backup failure blocks writes.');
    harmat_area_test_assert($db->writes === 0 && $db->data === $before, 'Backup failure exact no-write state.');
    $harmat_area_test_backup_fail = false;
    $db = new HarmatAreaMockDB($before);
    $db->engine = 'MyISAM';
    harmat_area_test_throws('harmat_area_transaction', array($db, false, 'MOCK_NO_FILESYSTEM', null, 'harmat_area_test_save_backup'), 'Nontransactional engine rejected.');
    harmat_area_test_assert($db->log === array() && $db->writes === 0, 'Engine guard precedes transaction.');
    $db = new HarmatAreaMockDB($before);
    harmat_area_test_assert(harmat_area_transaction($db, false, 'MOCK_NO_FILESYSTEM', null, 'harmat_area_test_save_backup') === 'APPLIED_EXACT_SCOPE', 'Successful apply.');
    harmat_area_test_assert($db->data === $after && $db->writes === 9 && end($db->log) === 'COMMIT', 'Exactly nine writes and commit.');
    harmat_area_test_assert($harmat_area_test_backup === $manifest, 'Backup captured full target rows/meta and 124-property hashes.');
    $db = new HarmatAreaMockDB($after);
    harmat_area_test_assert(harmat_area_transaction($db, false, 'MOCK_NO_FILESYSTEM', null, 'harmat_area_test_save_backup') === 'ALREADY_CORRECTED'
        && $db->writes === 0 && $db->data === $after, 'Repeated apply no writes.');
    $db = new HarmatAreaMockDB($after);
    harmat_area_test_assert(harmat_area_transaction($db, true, 'MOCK_NO_FILESYSTEM', $manifest) === 'RESTORED_EXACT_SCOPE', 'Successful rollback.');
    harmat_area_test_assert($db->data === $before && $db->writes === 9 && end($db->log) === 'COMMIT', 'Rollback nine inverse CAS writes.');
    $db = new HarmatAreaMockDB($after);
    $db->fail_write = 9;
    harmat_area_test_throws('harmat_area_transaction', array($db, true, 'MOCK_NO_FILESYSTEM', $manifest), 'Rollback ninth CAS failure.');
    harmat_area_test_assert($db->data === $after && $db->writes === 9 && in_array('ROLLBACK', $db->log, true), 'Failed rollback restores repaired state.');
    $bad = $after;
    $bad['meta'][4349][0]['meta_id'] = '123456789';
    harmat_area_test_throws('harmat_area_rollback_plan', array($bad, $manifest), 'Changed metadata identity rejects rollback.');
    foreach (array('format', 'plan', 'hash', 'unrelated-hash', 'extra-target') as $case) {
        $bad = $manifest;
        if ($case === 'format') $bad['format'] = 'unknown';
        if ($case === 'plan') $bad['changes'][0]['after'] .= 'tamper';
        if ($case === 'hash') $bad['before_hashes'][4349]['post'] = str_repeat('0', 64);
        if ($case === 'unrelated-hash') $bad['after_hashes'][10000]['meta'] = str_repeat('0', 64);
        if ($case === 'extra-target') {
            $bad['before']['posts'][10000] = $before['posts'][10000];
            $bad['before']['meta'][10000] = $before['meta'][10000];
        }
        harmat_area_test_throws('harmat_area_validate_manifest', array($bad), 'Manifest rejects ' . $case);
    }
    $db = new HarmatAreaMockDB($before);
    array_pop($db->data['posts']);
    harmat_area_test_throws('harmat_area_snapshot', array($db), 'Wrong published-property count.');
    $db = new HarmatAreaMockDB($before);
    $db->posts = 'posts;DROP';
    harmat_area_test_throws('harmat_area_engines', array($db), 'Unsafe table name.');
    $db = new HarmatAreaMockDB($before);
    $db->fail_read = true;
    harmat_area_test_throws('harmat_area_transaction', array($db, false, 'MOCK_NO_FILESYSTEM', null, 'harmat_area_test_save_backup'), 'Read failure rolls back.');
    harmat_area_test_assert($db->writes === 0 && end($db->log) === 'ROLLBACK', 'No mutation after read failure.');
    $db = new HarmatAreaMockDB($before);
    $db->fail_start = true;
    harmat_area_test_throws('harmat_area_transaction', array($db, false, 'MOCK_NO_FILESYSTEM', null, 'harmat_area_test_save_backup'), 'Begin failure blocks all writes.');
    harmat_area_test_assert($db->writes === 0 && $db->data === $before, 'No mutation after begin failure.');
    foreach (array('target', 'key', 'table', 'value') as $case) {
        $change = $plan['changes'][1];
        if ($case === 'target') $change['id'] = 10000;
        if ($case === 'key') $change['key'] = 'property_price';
        if ($case === 'table') $change['table'] = 'options';
        if ($case === 'value') $change['after'] = null;
        $db = new HarmatAreaMockDB($before);
        harmat_area_test_throws('harmat_area_cas', array($db, $change), 'CAS whitelist blocks ' . $case);
        harmat_area_test_assert($db->writes === 0, 'CAS whitelist fails before SQL.');
    }
    $db = new HarmatAreaMockDB($before);
    $change = $plan['changes'][0];
    $change['before'] .= 'stale';
    harmat_area_test_throws('harmat_area_cas', array($db, $change), 'Content CAS rejects stale bytes.');
    harmat_area_test_assert($db->data === $before, 'Stale CAS cannot change content.');
    $db = new HarmatAreaMockDB($before);
    $change = $plan['changes'][1];
    $change['meta_id']++;
    harmat_area_test_throws('harmat_area_cas', array($db, $change), 'Metadata CAS rejects wrong row identity.');
    harmat_area_test_assert($db->data === $before, 'Wrong metadata CAS cannot change values.');
    harmat_area_test_dry_run($before, $after);
}

function harmat_area_test_dry_run(array $before, array $after): void
{
    global $wpdb;
    $saved = array();
    foreach (array('HARMAT_APPLY', 'HARMAT_ROLLBACK', 'HARMAT_BACKUP_DIR') as $name) {
        $saved[$name] = getenv($name);
        putenv($name);
    }
    try {
        foreach (array($before, $after) as $snapshot) {
            $wpdb = new HarmatAreaMockDB($snapshot);
            WP_CLI::$messages = array();
            harmat_area_main();
            harmat_area_test_assert($wpdb->data === $snapshot && $wpdb->writes === 0
                && !in_array('START', $wpdb->log, true), 'Default dry-run has no transaction or write.');
            $report = json_decode(WP_CLI::$messages[0], true, 512, JSON_THROW_ON_ERROR);
            harmat_area_test_assert($report['mode'] === 'dry-run' && count($report['property_hashes']) === 124,
                'Dry-run reports all 124 hashes.');
            harmat_area_test_assert($report['changes'] === ($snapshot === $before ? 9 : 0), 'Dry-run all-new reports zero writes.');
            harmat_area_test_assert($GLOBALS['harmat_area_test_purges'] === array(), 'Dry-run does not purge.');
        }
        putenv('HARMAT_APPLY=1');
        putenv('HARMAT_ROLLBACK=1');
        harmat_area_test_throws('harmat_area_main', array(), 'Conflicting modes blocked.');
        putenv('HARMAT_ROLLBACK');
        putenv('HARMAT_BACKUP_DIR=/tmp/invalid');
        harmat_area_test_throws('harmat_area_main', array(), 'Apply invalid backup path blocked.');
        harmat_area_test_assert($wpdb->writes === 0, 'Mode/path guards no writes.');
    } finally {
        foreach ($saved as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
    }
}

function harmat_area_test_static(): void
{
    $source = file_get_contents(__DIR__ . '/2026-10-09-correct-sales-area.php');
    $tokens = token_get_all($source);
    $forbidden = array('update_post_meta', 'add_post_meta', 'update_metadata', 'add_metadata', 'delete_metadata',
        'update_post_meta_by_id', 'delete_post_meta_by_id', 'wp_update_post', 'wp_insert_post',
        'wp_mail', 'wp_schedule_event', 'wp_schedule_single_event', 'wp_clear_scheduled_hook', 'do_action',
        'wp_remote_post', 'wp_remote_get', 'save_post', 'clear_cache');
    $unsafe = array();
    $anonymous = false;
    $cache_delete_calls = 0;
    foreach ($tokens as $index => $token) {
        if (!is_array($token)) continue;
        if ($token[0] === T_STRING) {
            if (in_array(strtolower($token[1]), $forbidden, true)) $unsafe[] = $token[1];
            if (strtolower($token[1]) === 'delete_post_meta') $cache_delete_calls++;
        }
        if ($token[0] === T_FN) $anonymous = true;
        if ($token[0] === T_FUNCTION) {
            $next = $index + 1;
            while (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) $next++;
            if (!is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING) $anonymous = true;
        }
    }
    harmat_area_test_assert($unsafe === array(), 'No forbidden mutator/sender/cron/Elementor calls.');
    harmat_area_test_assert(!$anonymous, 'Named functions only, no closures or arrow functions.');
    $reflection = new ReflectionFunction('harmat_area_purge');
    $purge_source = implode("\n", array_slice(explode("\n", $source), $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1));
    harmat_area_test_assert($cache_delete_calls === 1
        && substr_count($purge_source, "delete_post_meta($" . "id, '_elementor_element_cache');") === 1
        && strpos($purge_source, 'foreach (array_keys(harmat_area_targets()) as $id)') !== false,
        'Only one exact native metadata deletion call, scoped inside the target-only purge loop.');
    $reflection = new ReflectionFunction('harmat_area_transaction');
    $transaction_source = implode("\n", array_slice(explode("\n", $source), $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1));
    harmat_area_test_assert(strpos($transaction_source, 'delete_post_meta') === false
        && strpos($transaction_source, 'harmat_area_purge') === false, 'No cache invalidation before transaction verification/commit.');
    harmat_area_test_assert(strpos($source, "if ($" . "result === 'APPLIED_EXACT_SCOPE' || $" . "result === 'RESTORED_EXACT_SCOPE')") !== false,
        'Purge gated to successful apply/rollback commit, excluding dry-run and no-op.');
    foreach (array("fopen($" . "file, 'x')", '0600', '0700', 'START TRANSACTION', 'BINARY post_content', 'BINARY meta_value',
        "getenv('HARMAT_APPLY') === '1'", "getenv('HARMAT_ROLLBACK') === '1'") as $needle) {
        harmat_area_test_assert(strpos($source, $needle) !== false, 'Guard retained: ' . $needle);
    }
    harmat_area_test_assert(strpos($source, 'SET post_modified') === false && strpos($source, 'SET property_price') === false,
        'No date/price SQL writes.');
    harmat_area_test_assert($GLOBALS['harmat_area_test_purges'] === array(), 'No purge inside transactions/tests so far.');
    harmat_area_test_purge_scope('apply');
    foreach (array_keys(harmat_area_targets()) as $id) {
        $GLOBALS['wpdb']->data['posts'][$id] = $GLOBALS['harmat_area_test_backup']['before']['posts'][$id];
        $GLOBALS['wpdb']->data['meta'][$id] = $GLOBALS['harmat_area_test_backup']['before']['meta'][$id];
    }
    harmat_area_test_purge_scope('rollback');
}

function harmat_area_test_purge_scope(string $label): void
{
    $db = $GLOBALS['wpdb'];
    harmat_area_test_assert($db->saved === null, $label . ': purge outside any transaction.');
    $expected = $db->data;
    foreach (array_keys(harmat_area_targets()) as $id) {
        $index = harmat_area_meta_index($expected['meta'][$id], '_elementor_element_cache');
        array_splice($expected['meta'][$id], $index, 1);
    }
    $GLOBALS['harmat_area_test_purges'] = array();
    harmat_area_purge();
    harmat_area_test_assert($GLOBALS['harmat_area_test_purges'] === array('element:4349', 'post:4349',
        'element:4388', 'post:4388', 'element:4418', 'post:4418', 'search', 'page', 'object'),
        $label . ': three derived cache invalidations and only approved purge sequence.');
    harmat_area_test_assert($db->data === $expected && count(harmat_area_hash_map($db->data)) === 124,
        $label . ': every commercial post/meta value unchanged; only three derived cache rows removed.');
}

harmat_area_test_pure();
$evidence_path = $argv[1] ?? dirname(__DIR__, 2) . '/outputs/2026-10-09-sales-area-align/source-details-before.json';
if (!is_file($evidence_path)) {
    throw new RuntimeException('Public evidence fixture required for pinned/transaction tests: pass its path as argv[1].');
}
$evidence = json_decode(file_get_contents($evidence_path), true, 512, JSON_THROW_ON_ERROR);
harmat_area_test_transactions($evidence);
harmat_area_test_static();
echo 'PASS: ' . $harmat_area_assertions . " assertions; pinned public fixtures, staging guards, mixed states, ninth-write/rollback and exact scope.\n";
