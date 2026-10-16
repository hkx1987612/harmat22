<?php
// Offline CLI fixture: no WordPress bootstrap, database, network, or full-file eval.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function check($condition, $message) {
    $GLOBALS['checks']++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function extract_hero($source) {
    $tokens = token_get_all($source, TOKEN_PARSE);
    $matches = array();
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
            $j++;
        }
        if ($j >= $count || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== 'hm_migrated_property_hero_html') {
            continue;
        }
        $function = '';
        $depth = 0;
        $body = false;
        for ($k = $i; $k < $count; $k++) {
            $token = $tokens[$k];
            $function .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
                $body = true;
                $depth++;
            } elseif ($token === '}') {
                $depth--;
                if ($body && $depth === 0) {
                    $matches[] = $function;
                    break;
                }
            }
        }
    }
    if (count($matches) !== 1) {
        throw new RuntimeException('Expected exactly one complete named hero function');
    }
    return $matches[0];
}

function u($text) {
    return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
}
function esc_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function esc_attr($value) {
    return esc_html($value);
}
function esc_url($value) {
    return esc_html($value);
}
function home_url($path) {
    return 'https://harmat22.hu' . $path;
}
function is_singular($type) {
    return $GLOBALS['fixture']['singular'] && $type === 'property';
}
function get_queried_object_id() {
    return $GLOBALS['fixture']['id'];
}
function get_the_title($id) {
    check($id === $GLOBALS['fixture']['id'], 'Title read outside current property');
    return $GLOBALS['fixture']['title'];
}
function get_post_meta($id, $key, $single) {
    check($id === $GLOBALS['fixture']['id'] && $single === true, 'Metadata read outside current property');
    $GLOBALS['reads'][] = $key;
    return array_key_exists($key, $GLOBALS['fixture']['meta']) ? $GLOBALS['fixture']['meta'][$key] : '';
}
function hm_migrated_property_floor_label($title, $floor) {
    $floor = trim((string) $floor);
    if ($floor === '' || stripos((string) $title, '-F-') !== false || strcasecmp($floor, 'F') === 0 || strcasecmp($floor, 'FSZ') === 0) {
        return 'Fsz';
    }
    return is_numeric($floor) ? ((int) $floor . '.') : $floor;
}
function hm_migrated_property_status_label($id) {
    if (get_post_meta($id, 'property_status', true) === 'sold') {
        return array('Eladva', 'sold');
    }
    return get_post_meta($id, 'property_under_offer', true)
        ? array('Foglalva', 'reserved') : array(u('El&eacute;rhet&#337;'), 'current');
}
function hm_migrated_format_huf($value) {
    $number = (int) preg_replace('/[^\d]/', '', (string) $value);
    return $number > 0 ? number_format($number, 0, ' ', ' ') . ' Ft' : '';
}
function hm_migrated_format_square_meter($value) {
    $number = (float) str_replace(',', '.', (string) $value);
    return $number > 0 ? number_format($number, 2, ',', ' ') . u(' m&sup2;') : '';
}

function render_hero($override = '60.1', $changes = array(), $id = 3841, $title = 'A1-F-L1', $floorplan = '') {
    $GLOBALS['fixture'] = array(
        'id' => $id, 'title' => $title, 'singular' => true,
        'meta' => array_replace(array(
            'property_heading' => $title,
            'property_address_street' => 'A1',
            'property_address_street_number' => 'F',
            'property_rooms' => '3',
            'property_building_area' => '57.05',
            'property_land_area' => '64.05',
            'property_price' => '84342300',
            'property_under_offer' => '1',
            'property_floorplan' => 'https://harmat22.hu/floorplan.pdf',
            '_harmat_sales_area' => $override,
        ), $changes),
    );
    $GLOBALS['reads'] = array();
    $before = serialize($GLOBALS['fixture']);
    $html = hm_migrated_property_hero_html($floorplan);
    check(serialize($GLOBALS['fixture']) === $before, 'Fixture metadata changed');
    return $html;
}
function authority_reads() {
    return count(array_keys($GLOBALS['reads'], '_harmat_sales_area', true));
}
function dd_values($html) {
    preg_match_all('~<dd>(.*?)</dd>~s', $html, $matches);
    return $matches[1];
}

$GLOBALS['checks'] = 0;
try {
    $path = $argv[1] ?? dirname(__DIR__, 2) . '/wp-mu-plugins/harmat-migrated-snippets.php';
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('Cannot read candidate');
    }
    $hero = extract_hero($source);
    check(strpos($hero, 'hm_migrated_property_sample_detail_html') === false, 'Extraction included sample detail');
    // Token boundaries must ignore braces inside strings/comments and all adjacent code.
    $synthetic = '<?php function unrelated() {} function hm_migrated_property_hero_html() { $s = "}"; /* { */ return $s; } throw new Exception();';
    check(extract_hero($synthetic) === 'function hm_migrated_property_hero_html() { $s = "}"; /* { */ return $s; }', 'Token extraction boundaries');
    foreach (array('<?php function other() {}', '<?php function hm_migrated_property_hero_html() {} function hm_migrated_property_hero_html() {}') as $invalid) {
        $rejected = false;
        try {
            extract_hero($invalid);
        } catch (RuntimeException $error) {
            $rejected = true;
        }
        check($rejected, 'Missing/duplicate named hero not rejected');
    }
    // Only this exact extracted function is evaluated, never the candidate file.
    eval($hero);

    $legacy = render_hero('');
    $golden = u('<section class="harmat-property-hero harmat-property-hero-reserved" aria-label="Lak&aacute;s &ouml;sszefoglal&oacute;"><div class="harmat-property-hero-head"><span>Harmat Lak&oacute;park</span><h1>A1-F-L1</h1><div class="harmat-property-hero-title-status"><span>St&aacute;tusz</span><strong>Foglalva</strong></div></div><dl class="harmat-property-hero-grid"><div class="status"><dt>St&aacute;tusz</dt><dd>Foglalva</dd></div><div class="highlight"><dt>&Aacute;rinform&aacute;ci&oacute;</dt><dd>84 342 300 Ft</dd></div><div class=""><dt>Elad&aacute;si ter&uuml;let</dt><dd>57,05 m&sup2;</dd></div><div class=""><dt>Egys&eacute;g&aacute;r</dt><dd>1 478 393 Ft / m&sup2;</dd></div><div class=""><dt>&Eacute;p&uuml;let</dt><dd>A1</dd></div><div class=""><dt>Emelet</dt><dd>Fsz</dd></div><div class=""><dt>Szoba</dt><dd>3 szoba</dd></div><div class=""><dt>Alapter&uuml;let</dt><dd>57,05 m&sup2;</dd></div><div class=""><dt>Kert / terasz</dt><dd>64,05 m&sup2;</dd></div></dl><div class="harmat-property-hero-actions"><a class="primary" href="#opal-contactform-popup">Aj&aacute;nlatot k&eacute;rek</a><a href="https://harmat22.hu/floorplan.pdf" target="_blank" rel="noopener">Alaprajz let&ouml;lt&eacute;se</a><a href="https://harmat22.hu/lakaskereso/">Vissza a lak&aacute;skeres&#337;h&ouml;z</a></div><p class="harmat-property-hero-note">A f&ouml;ldszinti kert aj&aacute;nd&eacute;k.</p></section>');
    check($legacy === $golden, 'Legacy HTML differs from reviewed golden');
    $target = render_hero();
    check(authority_reads() === 1, 'Target authority must be read exactly once');
    $expected = str_replace(
        array('<dd>57,05' . u(' m&sup2;') . '</dd></div><div class=""><dt>Egys', '<dd>1 478 393 Ft'),
        array('<dd>60,10' . u(' m&sup2;') . '</dd></div><div class=""><dt>Egys', '<dd>1 403 366 Ft'),
        $golden, $replacements
    );
    check($replacements === 2 && $target === $expected, 'Target must change only two DD values');
    $old_dd = dd_values($legacy);
    $new_dd = dd_values($target);
    check(count($new_dd) === 9, 'Hero fact count');
    $changed = array();
    foreach ($old_dd as $index => $value) {
        if ($new_dd[$index] !== $value) {
            $changed[] = $index;
        }
    }
    check($changed === array(2, 3), 'Only sales area and unit price may differ');
    check($new_dd[2] === u('60,10 m&sup2;') && $new_dd[3] === u('1 403 366 Ft / m&sup2;'), 'Approved area and derived unit price');
    check($new_dd[1] === '84 342 300 Ft' && $new_dd[7] === u('57,05 m&sup2;') && $new_dd[8] === u('64,05 m&sup2;'), 'Total/base/outdoor preserved');
    preg_match_all('~href="[^"]*"~', $legacy, $old_hrefs);
    preg_match_all('~href="[^"]*"~', $target, $new_hrefs);
    check($old_hrefs === $new_hrefs && count($new_hrefs[0]) === 3, 'All action hrefs preserved');

    $invalid_values = array('', null, false, true, array(), array('60.1'), new stdClass(), 'garbage', '60,1', '60.1 m2', '0', 0, 0.0, -1, '-60.1', NAN, INF, -INF, 'NAN', 'INF', '1e309', '-1e309', '1e-9999');
    foreach ($invalid_values as $value) {
        check(render_hero($value) === $legacy, 'Invalid override must preserve legacy HTML: ' . get_debug_type($value));
        check(authority_reads() === 1, 'Invalid target value read boundary');
    }
    foreach (array('60.1', 60.1, '6.01e1', ' 60.1 ') as $value) {
        check(render_hero($value) === $target, 'Valid numeric authority');
    }
    foreach (array(61, '72.25', 93.125) as $value) {
        $values = dd_values(render_hero($value));
        check($values[2] === esc_html(hm_migrated_format_square_meter($value)), 'Authority not hardcoded');
        check($values[3] === esc_html(hm_migrated_format_huf(round(84342300 / (float) $value)) . u(' / m&sup2;')), 'Existing unit-price formula');
    }
    render_hero();
    unset($GLOBALS['fixture']['meta']['_harmat_sales_area']);
    check(hm_migrated_property_hero_html() === $legacy, 'Absent authority fallback');

    foreach (array(array(3842, 'A1-F-L1'), array(4349, 'A1-2-L4'), array(4388, 'A1-4-L1'), array(4418, 'A1-4-L4'), array(3841, 'A1-F-L2'), array(3841, 'a1-f-l1'), array(3841, 'A1-F-L1 '), array('3841', 'A1-F-L1')) as $case) {
        foreach (array('F', '2') as $floor) {
            $changes = array('property_address_street_number' => $floor);
            $before = render_hero('', $changes, $case[0], $case[1]);
            check(authority_reads() === 0, 'Non-target baseline authority read');
            check(render_hero('123.45', $changes, $case[0], $case[1]) === $before, 'Other ID/title HTML must be byte-identical');
            check(authority_reads() === 0, 'No out-of-scope authority read');
        }
    }
    check(render_hero('60.1', array('property_heading' => '')) === $target, 'Exact fallback title accepted');
    render_hero('60.1', array('property_heading' => 'A1-F-L2'));
    check(authority_reads() === 0, 'Different heading must not use matching post title');
    check(render_hero('60.1', array('property_heading' => ''), 3841, 'A1-F-L2') === render_hero('', array('property_heading' => ''), 3841, 'A1-F-L2'), 'Different fallback title unchanged');

    foreach (array(array('_harmat_hide_front_price' => 'yes'), array('property_price_display' => 'no'), array('_harmat_hide_front_price' => 'yes', 'property_price_display' => 'no'), array('property_price' => '0'), array('property_price' => '-1')) as $policy) {
        $before = render_hero('', $policy);
        $after = render_hero('60.1', $policy);
        $only_area = str_replace('<dt>' . u('Elad&aacute;si ter&uuml;let') . '</dt><dd>57,05', '<dt>' . u('Elad&aacute;si ter&uuml;let') . '</dt><dd>60,10', $before, $replacements);
        check($replacements === 1 && $after === $only_area, 'Hidden/missing price HTML policy preserved');
        $values = dd_values($after);
        check($values[1] === u('&Aacute;r egyeztet&eacute;s alapj&aacute;n') && $values[3] === u('&Eacute;rt&eacute;kes&iacute;t&eacute;si egyeztet&eacute;s alapj&aacute;n'), 'No total or unit-price disclosure');
    }
    foreach (array(array('property_status' => 'sold'), array('property_under_offer' => ''), array('property_floorplan' => ''), array('property_land_area' => '0'), array('property_rooms' => ''), array('property_price' => '60100000')) as $changes) {
        $before = render_hero('', $changes);
        $after = render_hero('60.1', $changes);
        $before_dd = dd_values($before);
        $after_dd = dd_values($after);
        $normalized = str_replace(array('<dd>' . $after_dd[2] . '</dd>', '<dd>' . $after_dd[3] . '</dd>'), array('<dd>' . $before_dd[2] . '</dd>', '<dd>' . $before_dd[3] . '</dd>'), $after);
        check($normalized === $before, 'Status, links, base, outdoor, total price and remaining HTML preserved');
    }
    $override_url = 'https://harmat22.hu/custom.pdf?x=1&y=2';
    $with_url = render_hero('60.1', array(), 3841, 'A1-F-L1', $override_url);
    check(strpos($with_url, 'href="' . esc_url($override_url) . '"') !== false, 'Floorplan override href preserved');
    check(!in_array('property_floorplan', $GLOBALS['reads'], true), 'Floorplan override keeps legacy read behavior');
    $GLOBALS['fixture']['singular'] = false;
    $GLOBALS['reads'] = array();
    check(hm_migrated_property_hero_html() === '' && $GLOBALS['reads'] === array(), 'Non-property early return');
    $GLOBALS['fixture']['singular'] = true;
    $GLOBALS['fixture']['id'] = 0;
    check(hm_migrated_property_hero_html() === '' && $GLOBALS['reads'] === array(), 'Missing ID early return');
    echo 'PASS ' . $GLOBALS['checks'] . " checks; isolated exact hero function; candidate SHA256 " . hash('sha256', $source) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
