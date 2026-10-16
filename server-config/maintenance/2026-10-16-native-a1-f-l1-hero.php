<?php
// Private native render comparison; does not dispatch REST, lead, mail or cron.
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WP_CLI')) {
    exit(1);
}
$directory = $argv[1] ?? '';
if (!preg_match('~\A/home/harmath2/codex-backups/area-https-[a-f0-9]{32}\z~D', $directory)) {
    exit(2);
}
function harmat_area_extract($source, $replacement) {
    $tokens = token_get_all($source, TOKEN_PARSE);
    $matches = array();
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        $name = $index + 1;
        while (isset($tokens[$name]) && is_array($tokens[$name]) && in_array($tokens[$name][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
            $name++;
        }
        if (!isset($tokens[$name]) || !is_array($tokens[$name]) || $tokens[$name][0] !== T_STRING || $tokens[$name][1] !== 'hm_migrated_property_hero_html') {
            continue;
        }
        $result = '';
        $depth = 0;
        $body = false;
        for ($i = $index; isset($tokens[$i]); $i++) {
            $part = $tokens[$i];
            $result .= $i === $name ? $replacement : (is_array($part) ? $part[1] : $part);
            if ($part === '{' || (is_array($part) && in_array($part[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
                $body = true;
                $depth++;
            } elseif ($part === '}' && --$depth === 0 && $body) {
                $matches[] = $result;
                break;
            }
        }
    }
    if (count($matches) !== 1) {
        throw new RuntimeException('Expected exactly one native hero function');
    }
    return $matches[0];
}
foreach (array('original.php', 'candidate.php') as $file) {
    if (!is_file($directory . '/' . $file) || is_link($directory . '/' . $file)) {
        throw new RuntimeException('Private candidate identity failed');
    }
}
eval(harmat_area_extract(file_get_contents($directory . '/original.php'), 'harmat_area_before'));
eval(harmat_area_extract(file_get_contents($directory . '/candidate.php'), 'harmat_area_after'));
define('DISABLE_WP_CRON', true);
define('DONOTCACHEPAGE', true);
define('WP_USE_THEMES', false);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'harmat22.hu';
$_SERVER['REQUEST_URI'] = '/property/a1-f-l1/';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
require '/home/harmath2/public_html/wp-load.php';
$ids = get_posts(array('post_type' => 'property', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC'));
if (count($ids) !== 124 || !in_array(3841, $ids, true)) {
    throw new RuntimeException('Public inventory identity changed');
}
$unchanged = 0;
$target = null;
foreach ($ids as $id) {
    query_posts(array('post_type' => 'property', 'p' => $id));
    if (!is_singular('property') || get_queried_object_id() !== $id) {
        throw new RuntimeException('Native property query context failed');
    }
    $before = harmat_area_before();
    $after = harmat_area_after();
    if ($id !== 3841) {
        if ($before === '' || $after !== $before) {
            throw new RuntimeException('Non-target native hero changed');
        }
        $unchanged++;
        continue;
    }
    $data = harmat_sai_property_summary_data($id);
    if ($data['title'] !== 'A1-F-L1' || (float) $data['sales_area'] !== 60.1 || (float) $data['area'] !== 57.05 || (int) $data['price'] !== 84342300) {
        throw new RuntimeException('Target authority changed; review new data before release');
    }
    $old_document = new DOMDocument();
    $new_document = new DOMDocument();
    $previous_errors = libxml_use_internal_errors(true);
    $old_document->loadHTML('<?xml encoding="UTF-8">' . $before);
    $new_document->loadHTML('<?xml encoding="UTF-8">' . $after);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);
    $old_values = $old_document->getElementsByTagName('dd');
    $new_values = $new_document->getElementsByTagName('dd');
    if ($old_values->length !== 9 || $new_values->length !== 9) {
        throw new RuntimeException('Unexpected native hero facts');
    }
    $expected = $before;
    $changed = array();
    foreach ($new_values as $index => $value) {
        if ($old_values[$index]->textContent !== $value->textContent) {
            $changed[] = $index;
            $label = '<dt>' . esc_html($old_values[$index]->previousSibling->textContent) . '</dt>';
            $expected = str_replace($label . '<dd>' . esc_html($old_values[$index]->textContent) . '</dd>', $label . '<dd>' . esc_html($value->textContent) . '</dd>', $expected, $replacements);
            if ($replacements !== 1) {
                throw new RuntimeException('Non-unique target fact replacement');
            }
        }
    }
    if ($changed !== array(2, 3) || $expected !== $after
        || $new_values[2]->textContent !== hm_migrated_format_square_meter($data['sales_area'])
        || $new_values[3]->textContent !== hm_migrated_format_huf(round($data['price'] / $data['sales_area'])) . " / m\u{00b2}") {
        throw new RuntimeException('Native target must change only authority area and derived unit price');
    }
    $target = array('id' => $id, 'sales_area' => $data['sales_area'], 'base_area' => $data['area'],
                    'price' => $data['price'], 'changed_fact_indices' => $changed);
}
if ($unchanged !== 123 || $target === null) {
    throw new RuntimeException('Incomplete native inventory comparison');
}
echo wp_json_encode(array('status' => 'PASS', 'properties' => 124, 'unchanged_heroes' => $unchanged,
                         'target' => $target, 'scope' => 'NATIVE_RENDER_NO_REST_NO_MAIL_NO_BUSINESS_WRITE'));
