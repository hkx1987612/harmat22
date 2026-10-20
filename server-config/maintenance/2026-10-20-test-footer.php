<?php
// CLI-only: extract two pure functions and the literal stylesheet, never load WP.
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || defined('WPINC') || isset($GLOBALS['wpdb'])) {
    fwrite(STDERR, "Isolated PHP CLI only.\n");
    exit(2);
}
$candidate = isset($argv[1]) ? $argv[1] : dirname(__DIR__, 2) . '/wp-mu-plugins/harmat-migrated-snippets.php';
$render = isset($argv[2]) && $argv[2] === '--render';
if (count($argv) > 3 || (isset($argv[2]) && !$render) || strpos($candidate, '://') !== false
    || !is_file($candidate) || !is_readable($candidate) || realpath($candidate) === __FILE__) {
    fwrite(STDERR, "Usage: php 2026-10-20-test-footer.php [local-candidate.php] [--render]\n");
    exit(2);
}
$dictionary_path = getenv('HARMAT_FOOTER_DICTIONARY');
if ($dictionary_path === false) {
    $dictionary_path = dirname(__DIR__, 2) . '/wp-mu-plugins/harmat-internal-language/zh-Hans.json';
}
$dictionary_absolute = $dictionary_path !== '' && ($dictionary_path[0] === '/'
    || preg_match('~^[A-Za-z]:[\\\\/]~', $dictionary_path) === 1);
if (!$dictionary_absolute || strpos($dictionary_path, '://') !== false
    || strpos($dictionary_path, "\0") !== false || substr($dictionary_path, 0, 2) === '//'
    || !is_file($dictionary_path) || !is_readable($dictionary_path)) {
    fwrite(STDERR, "HARMAT_FOOTER_DICTIONARY must name a readable absolute local file, not a URL.\n");
    exit(2);
}
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0;
function footer_check($condition, $label) {
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
}
function footer_extract_function($source, $name) {
    $tokens = token_get_all($source);
    $found = array();
    for ($i = 0; $i < count($tokens); $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $j = $i + 1;
        while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) {
            continue;
        }
        $text = '';
        $depth = 0;
        $started = false;
        for ($j = $i; $j < count($tokens); $j++) {
            $token = $tokens[$j];
            $text .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { $started = true; $depth++; }
            if ($token === '}') { $depth--; }
            if ($started && $depth === 0) { break; }
        }
        $found[] = $text;
    }
    footer_check(count($found) === 1, 'one extracted function: ' . $name);
    // Only the existing HTML/URL helpers may execute inside the extracted code.
    $allowed = array('home_url', 'html_entity_decode', 'esc_html', 'esc_attr', 'esc_url',
        'hm_migrated_clean_footer_html', 'hm_migrated_legal_links', 'ENT_QUOTES');
    $tokens = token_get_all('<?php ' . $found[0]);
    foreach ($tokens as $token) {
        if (!is_array($token)) { continue; }
        footer_check(!in_array($token[0], array(T_EVAL, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE,
            T_REQUIRE_ONCE, T_EXIT, T_NEW, T_GLOBAL), true), 'pure extracted code');
        if ($token[0] === T_STRING) {
            footer_check(in_array($token[1], $allowed, true), 'known helper: ' . $token[1]);
        }
        if ($token[0] === T_VARIABLE) {
            footer_check(in_array($token[1], array('$columns', '$html', '$title', '$links', '$label', '$url'), true), 'local variable');
        }
    }
    return $found[0];
}
function home_url($path) { return 'https://harmat22.hu' . $path; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_attr($value); }

try {
    $source = str_replace("\r\n", "\n", file_get_contents($candidate));
    footer_check(substr_count($source, " * Version: 2026.10.20.2\n") === 1, 'exact R2 version');
    footer_check(hash('sha256', $source) === 'a74f6de8d93bc5f183cf7447be3100acbebacf19228cf2f5ea1de62d5d2b375f', 'frozen R2 LF source hash');
    footer_check(substr_count($source, 'padding:18px 24px 76px;text-align:left}') === 1, 'desktop footer bottom clearance');
    footer_check(substr_count($source, '.harmat-clean-footer-bottom{padding:16px 16px 76px}') === 1, 'mobile footer bottom clearance');
    $r1_source = $source;
    foreach (array(
        " * Version: 2026.10.20.2\n" => " * Version: 2026.10.20.1\n",
        'padding:18px 24px 76px;text-align:left}' => 'padding:18px 24px 22px;text-align:left}',
        '.harmat-clean-footer-bottom{padding:16px 16px 76px}' => '.harmat-clean-footer-bottom{padding:16px 16px 20px}',
    ) as $from => $to) {
        $r1_source = str_replace($from, $to, $r1_source, $replacements);
        footer_check($replacements === 1, 'one exact R2-to-R1 reversal');
    }
    footer_check(hash('sha256', $r1_source) === '32868104d0da9d2a85ba0b779db408fe1688e8ec1ea3da4fda3615b3e8f92b19', 'only three source changes since released R1');
    $footer_function = footer_extract_function($source, 'hm_migrated_clean_footer_html');
    $legal_function = footer_extract_function($source, 'hm_migrated_legal_links');
    eval($legal_function);
    eval($footer_function);
    footer_check(preg_match_all('~<style id="harmat-migrated-public-layout-css">(.*?)</style>~s', $source, $styles) === 1, 'one literal layout stylesheet');
    $css = $styles[1][0];
    footer_check(strpos($css, '<?') === false, 'no PHP evaluated from CSS');
    $html = hm_migrated_clean_footer_html();
    $opening = '<footer id="colophon" class="site-footer harmat-clean-footer" role="contentinfo">';
    footer_check(strpos($html, $opening) === 0, 'exact protected footer opening');
    footer_check(substr($html, -9) === '</footer>', 'closed footer');
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
    $xp = new DOMXPath($doc);
    footer_check($xp->query('//footer')->length === 1, 'one footer');
    footer_check($xp->query('//form|//input|//textarea|//select|//button|//script|//img|//video|//iframe|//svg')->length === 0, 'no forms, scripts, media or new controls');
    foreach ($xp->query('//@*') as $attribute) {
        footer_check(stripos($attribute->nodeName, 'on') !== 0, 'no event handler attributes');
    }
    $ids = array();
    foreach ($xp->query('//*[@id]') as $node) { $ids[] = $node->getAttribute('id'); }
    footer_check($ids === array('colophon'), 'only original unique ID');
    $headings = array();
    foreach ($xp->query('//footer/div[1]/section/h2') as $node) { $headings[] = $node->textContent; }
    footer_check($headings === array('Projekt', 'Lakások', 'Kapcsolat'), 'three retained translated column headings');
    $expected = array(
        'Főoldal' => '/', 'Harmat Lakópark' => '/harmat-lakopark/', 'Magunkról' => '/magunkrol/',
        'Környékünk' => '/harmat-lakopark-kornyeke/', 'Galéria' => '/galeria/',
        'Lakáskereső' => '/lakaskereso/', 'Virtuális lakásválasztó' => '/virtualis-lakasvalaszto/',
        'Első ütem' => '/virtualis-lakasvalaszto-elso-utem/', 'Építési napló' => '/epitesi-naplo/',
        '+36300733375' => 'tel:+36300733375', 'ertekesites@harmat22.hu' => 'mailto:ertekesites@harmat22.hu',
        'Adatkezelési tájékoztató' => '/adatvedelmi-tajekoztato/', 'Süti tájékoztató' => '/cookie-tajekoztato/',
        'Felhasználási feltételek' => '/felhasznalasi-feltetelek/', 'Impresszum' => '/impresszum/',
    );
    $actual = array();
    foreach ($xp->query('//footer//a') as $node) {
        $label = $node->textContent;
        footer_check(!isset($actual[$label]) && isset($expected[$label]), 'unique known link: ' . $label);
        $actual[$label] = $node->getAttribute('href');
        $url = $expected[$label];
        footer_check($actual[$label] === ($url[0] === '/' ? home_url($url) : $url), 'exact original or journal href: ' . $label);
    }
    footer_check(count($actual) === count($expected), 'all fifteen links present');
    footer_check(count(array_unique(array_values($actual))) === 15, 'duplicate apartment-search link removed');
    footer_check($xp->query('//footer/div[1]/section')->length === 3, 'three top columns');
    footer_check($xp->query('//section[@class="harmat-clean-footer-contact"]//nav')->length === 0, 'no legal links in contact');
    footer_check($xp->query('//footer/div[@class="harmat-clean-footer-bottom"]/nav/a')->length === 4, 'four legal links in separate bottom row');
    footer_check($xp->query('//nav[@aria-label="Jogi dokumentumok"]')->length === 1, 'original legal aria label');
    footer_check(strpos($html, '1105 Budapest, Harmat utca 22.') !== false, 'unchanged public address');
    $copyright = '© Cooperation Power Kft. Minden jog fenntartva. Az árak, alapterületek, látványtervek és műszaki tartalmak tájékoztató jellegűek.';
    footer_check($xp->query('//footer/div[@class="harmat-clean-footer-bottom"]/p')->item(0)->textContent === $copyright, 'exact original copyright/disclaimer');
    $dictionary = json_decode(file_get_contents($dictionary_path), true, 512, JSON_THROW_ON_ERROR);
    $neutral = array('Harmat Lakópark', '1105 Budapest, Harmat utca 22.', '+36300733375', 'ertekesites@harmat22.hu');
    foreach ($xp->query('//footer//text()|//footer//@aria-label') as $node) {
        $text = trim($node->nodeValue);
        if ($text === '' || in_array($text, $neutral, true)) { continue; }
        footer_check(isset($dictionary[$text]) && $dictionary[$text] !== '', 'existing dictionary key: ' . $text);
    }
    foreach (array('Projekt', 'Lakások', 'Kapcsolat', 'Telefon', 'E-mail', 'Harmat Lakópark címe',
        'Jogi / Kapcsolat', 'Jogi dokumentumok', 'Összes alaprajz', 'Építési napló', $copyright) as $key) {
        footer_check(isset($dictionary[$key]), 'old and current keys remain available: ' . $key);
    }
    if ($render) {
        echo json_encode(array('html' => $html, 'css' => $css), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    } else {
        echo 'PASS footer: ' . $checks . " assertions; pure extraction, no WordPress/network.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
