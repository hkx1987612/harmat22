<?php
/**
 * Run with WP-CLI from the WordPress root.
 *
 * Dry run:
 *   wp eval-file /path/to/2026-07-29-direct-internal-links.php
 *
 * Apply:
 *   HARMAT_APPLY=1 wp eval-file /path/to/2026-07-29-direct-internal-links.php
 */

defined('ABSPATH') || exit;

$apply = getenv('HARMAT_APPLY') === '1';
$home_id = (int) get_option('page_on_front');
$about_page = get_page_by_path('magunkrol', OBJECT, 'page');
$about_id = $about_page instanceof WP_Post ? (int) $about_page->ID : 0;

if (!$home_id || !$about_id) {
    WP_CLI::error('Could not resolve the homepage and Magunkrol page IDs.');
}

$targets = array(
    '/studio-apartman/' => '/lakaskereso/?rooms=1',
    '/2-szobas/' => '/lakaskereso/?rooms=2',
    '/3-szobas/' => '/lakaskereso/?rooms=3',
    '/4-szobas/' => '/lakaskereso/?rooms=4',
    '/5-szobas/' => '/lakaskereso/?rooms=5',
    '/3d-viewer/' => '/virtualis-lakasvalaszto/',
    '/ajanlatkeres/' => '/lakaskereso/',
);

$build_variants = static function ($path) {
    $home = untrailingslashit(home_url());
    $without_trailing_slash = untrailingslashit($path);
    return array(
        $path,
        $without_trailing_slash,
        ltrim($path, '/'),
        ltrim($without_trailing_slash, '/'),
        $home . $path,
        $home . $without_trailing_slash,
        set_url_scheme($home . $path, 'http'),
        set_url_scheme($home . $without_trailing_slash, 'http'),
        '//' . wp_parse_url($home, PHP_URL_HOST) . $path,
        '//' . wp_parse_url($home, PHP_URL_HOST) . $without_trailing_slash,
    );
};

$exact_replacements = array();
foreach ($targets as $old_path => $new_path) {
    $new_relative = $new_path;
    $new_absolute = untrailingslashit(home_url()) . $new_path;
    foreach ($build_variants($old_path) as $index => $old_value) {
        $exact_replacements[$old_value] = $index < 4 ? $new_relative : $new_absolute;
    }
}

$home_dynamic_targets = array(
    'ea43ce7' => array('post_id' => 4683, 'url' => home_url('/lakaskereso/?rooms=1')),
    '04d0c8f' => array('post_id' => 4704, 'url' => home_url('/lakaskereso/?rooms=2')),
    '59e1ba6' => array('post_id' => 4718, 'url' => home_url('/lakaskereso/?rooms=3')),
    '609bb16' => array('post_id' => 4722, 'url' => home_url('/lakaskereso/?rooms=4')),
    '0770c14' => array('post_id' => 4726, 'url' => home_url('/lakaskereso/?rooms=5')),
);

$replace_scalars = static function ($value, $path, &$changes) use (&$replace_scalars, $exact_replacements) {
    if (is_array($value)) {
        foreach ($value as $key => $child) {
            $child_path = $path . '[' . (is_int($key) ? $key : json_encode((string) $key)) . ']';
            $value[$key] = $replace_scalars($child, $child_path, $changes);
        }
        return $value;
    }

    if (is_string($value) && isset($exact_replacements[$value])) {
        $changes[] = array(
            'path' => $path,
            'old' => $value,
            'new' => $exact_replacements[$value],
        );
        return $exact_replacements[$value];
    }

    return $value;
};

$replace_home_dynamic_links = static function ($value, $path, &$changes, &$seen, &$errors) use (&$replace_home_dynamic_links, $home_dynamic_targets) {
    if (!is_array($value)) {
        return $value;
    }

    if (isset($value['id']) && isset($home_dynamic_targets[$value['id']])) {
        $element_id = $value['id'];
        $target = $home_dynamic_targets[$element_id];
        if (isset($seen[$element_id])) {
            $errors[] = 'Duplicate homepage Elementor element ID: ' . $element_id . '.';
        } else {
            $seen[$element_id] = true;
            $settings = isset($value['settings']) && is_array($value['settings']) ? $value['settings'] : array();
            $dynamic_link = isset($settings['__dynamic__']['link']) ? (string) $settings['__dynamic__']['link'] : '';
            $static_link = isset($settings['link']['url']) ? (string) $settings['link']['url'] : '';
            $expected_post_marker = '%22post_id%22%3A%22' . $target['post_id'] . '%22';

            if ($static_link === $target['url'] && $dynamic_link === '') {
                // Already migrated.
            } elseif ($dynamic_link !== '' && strpos($dynamic_link, $expected_post_marker) !== false) {
                $value['settings']['link'] = array(
                    'url' => $target['url'],
                    'is_external' => '',
                    'nofollow' => '',
                    'custom_attributes' => '',
                );
                unset($value['settings']['__dynamic__']['link']);
                $changes[] = array(
                    'path' => $path . '["settings"]["link"]',
                    'old' => 'Elementor internal post ' . $target['post_id'],
                    'new' => $target['url'],
                );
            } else {
                $errors[] = sprintf(
                    'Unexpected link configuration for homepage Elementor element %s.',
                    $element_id
                );
            }
        }
    }

    foreach ($value as $key => $child) {
        if (!is_array($child)) {
            continue;
        }
        $child_path = $path . '[' . (is_int($key) ? $key : json_encode((string) $key)) . ']';
        $value[$key] = $replace_home_dynamic_links($child, $child_path, $changes, $seen, $errors);
    }

    return $value;
};

$updates = array();
$validation_errors = array();

$home_raw = get_post_meta($home_id, '_elementor_data', true);
$home_data = json_decode((string) $home_raw, true);
if (!is_array($home_data)) {
    WP_CLI::error('Invalid homepage Elementor JSON.');
}

$home_changes = array();
$home_updated = $replace_scalars($home_data, '$', $home_changes);
$home_dynamic_seen = array();
$home_updated = $replace_home_dynamic_links($home_updated, '$', $home_changes, $home_dynamic_seen, $validation_errors);
if (!in_array(count($home_changes), array(0, 1, 5, 6), true)) {
    $validation_errors[] = sprintf('Homepage has %d pending link changes; expected 0, 1, 5, or 6.', count($home_changes));
}
if (count($home_dynamic_seen) !== count($home_dynamic_targets)) {
    $validation_errors[] = sprintf(
        'Found %d of %d expected homepage room-card elements.',
        count($home_dynamic_seen),
        count($home_dynamic_targets)
    );
}
$updates[$home_id] = array(
    'type' => 'elementor',
    'data' => $home_updated,
    'changes' => $home_changes,
);

if (!class_exists('WP_HTML_Tag_Processor')) {
    WP_CLI::error('WP_HTML_Tag_Processor is unavailable.');
}

$about_content = (string) get_post_field('post_content', $about_id);
$about_processor = new WP_HTML_Tag_Processor($about_content);
$about_changes = array();
$about_anchor_index = 0;
while ($about_processor->next_tag('a')) {
    $about_anchor_index++;
    $href = $about_processor->get_attribute('href');
    if (!is_string($href) || !isset($exact_replacements[$href])) {
        continue;
    }

    $replacement = $exact_replacements[$href];
    $about_processor->set_attribute('href', $replacement);
    $about_changes[] = array(
        'path' => sprintf('a[%d]@href', $about_anchor_index),
        'old' => $href,
        'new' => $replacement,
    );
}
$about_updated = $about_processor->get_updated_html();
if (!in_array(count($about_changes), array(0, 2), true)) {
    $validation_errors[] = sprintf('Magunkrol page has %d pending links; expected 0 or 2.', count($about_changes));
}
$updates[$about_id] = array(
    'type' => 'post_content',
    'data' => $about_updated,
    'changes' => $about_changes,
);

$total = count($home_changes) + count($about_changes);

foreach ($updates as $post_id => $update) {
    foreach ($update['changes'] as $change) {
        WP_CLI::log(
            sprintf('Post %d %s: %s -> %s', $post_id, $change['path'], $change['old'], $change['new'])
        );
    }
}

if ($validation_errors) {
    WP_CLI::error(implode(' ', $validation_errors) . ' No changes were written.');
}

if (!$apply) {
    WP_CLI::success(sprintf('Dry run passed: %d direct-link updates are ready.', $total));
    return;
}

foreach ($updates as $post_id => $update) {
    if (!$update['changes']) {
        continue;
    }

    if ($update['type'] === 'elementor') {
        $encoded = wp_json_encode($update['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || $encoded === '') {
            WP_CLI::error('Could not encode Elementor JSON for post ' . $post_id . '.');
        }
        if (update_post_meta($post_id, '_elementor_data', wp_slash($encoded)) === false) {
            WP_CLI::error('Could not update Elementor data for post ' . $post_id . '.');
        }
    } else {
        $result = wp_update_post(
            array(
                'ID' => $post_id,
                'post_content' => $update['data'],
            ),
            true
        );
        if (is_wp_error($result)) {
            WP_CLI::error('Could not update post content for post ' . $post_id . ': ' . $result->get_error_message());
        }
    }
    clean_post_cache($post_id);
}

WP_CLI::success(sprintf('Applied %d direct-link updates.', $total));
