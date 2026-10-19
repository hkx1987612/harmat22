<?php
/**
 * Plugin Name: Harmat Public Payment Copy
 * Description: Removable, frontend-only public payment wording; no sales data or form changes.
 * Version: 1.0.1
 */
defined('ABSPATH') || exit;

function harmat_ppc_scope(): bool {
    foreach (array('WP_CLI', 'DOING_CRON', 'DOING_AJAX', 'REST_REQUEST', 'XMLRPC_REQUEST', 'WP_INSTALLING') as $flag) {
        if (defined($flag) && constant($flag)) return false;
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'), true)
        || is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_preview()
        || is_404() || is_search() || is_embed() || is_trackback() || !is_main_query()
        || (function_exists('wp_is_json_request') && wp_is_json_request())
        || (function_exists('is_customize_preview') && is_customize_preview())
        || !in_array(http_response_code(), array(false, 200), true)) return false;
    foreach (array('preview', 'preview_id', 'preview_nonce', 'elementor-preview', 'rest_route', 'feed', 'embed',
        'customize_changeset_uuid', 'customize_theme', 'action', 'wc-ajax') as $key) {
        if (array_key_exists($key, $_GET)) return false;
    }
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0
            && !preg_match('~^Content-Type:\s*text/html(?:\s*;|\s*$)~i', $header)) return false;
    }
    $post = get_queried_object();
    if (!($post instanceof WP_Post) || $post->post_status !== 'publish' || $post->post_password !== '') return false;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (is_singular('property') && $post->post_type === 'property'
        && preg_match('~\A[a-z0-9]+(?:-[a-z0-9]+)*\z~D', $post->post_name)) {
        return $path === '/property/' . $post->post_name . '/';
    }
    return is_page('finanszirozas') && $post->post_type === 'page' && $post->post_name === 'finanszirozas'
        && $path === '/finanszirozas/';
}

function harmat_ppc_text(string $tag, string $hu, string $zh): string {
    return '<' . $tag . ' data-hppc-hu="' . esc_attr($hu) . '" data-hppc-zh="' . esc_attr($zh) . '">'
        . esc_html($hu) . '</' . $tag . '>';
}

function harmat_ppc_html(): string {
    $plans = array(
        array('Teljes lakásvételár', '全额付款',
            'A lakás teljes vételárának kifizetése 2026. december 31-ig.', '于2026年12月31日前支付全部公寓房款。',
            'A feltételekről és az esetleges kedvezményről egyedileg egyeztessen az értékesítéssel.', '具体条件及优惠请与销售面议。'),
        array('50% + 50%', '50% + 50%',
            'A lakás vételárának 50%-a 2026. december 31-ig, a fennmaradó 50% 2027. június 30-ig.',
            '于2026年12月31日前支付公寓房款的50%，剩余50%于2027年6月30日前支付。',
            'A feltételekről és az esetleges kedvezményről egyedileg egyeztessen az értékesítéssel.', '具体条件及优惠请与销售面议。'),
        array('Építési mérföldkövek', '按施工节点付款',
            '25% foglaló és előleg összesen; 25% szerkezetkész állapot; 25% belsőépítészeti munkálatok; 20% a kulcsátvételi értesítéskor; 5% átadáskor.',
            '25%（定金与预付款合计）；25%主体结构封顶；25%室内装修施工；20%收到领取钥匙通知时；5%交房时。',
            'A részletek az építési mérföldkövekhez kötöttek, rögzített fizetési dátumok nélkül. A feltételekről és az esetleges kedvezményről egyedileg egyeztessen az értékesítéssel.',
            '各期款项按施工节点支付，不设固定付款日期。具体条件及优惠请与销售面议。'),
    );
    $html = '<section id="harmat-public-payment-copy" class="hppc-plans" data-harmat-public-payment-copy="1" data-language-protect="" lang="hu">'
        . harmat_ppc_text('h2', 'Fizetési ütemezés', '付款安排') . '<dl>';
    foreach ($plans as $plan) {
        $html .= '<div class="hppc-row">' . harmat_ppc_text('dt', $plan[0], $plan[1]) . '<dd>'
            . harmat_ppc_text('p', $plan[2], $plan[3]) . harmat_ppc_text('p', $plan[4], $plan[5]) . '</dd></div>';
    }
    return $html . '</dl>' . <<<'HTML'
<style data-hppc-style>
[data-harmat-public-payment-copy]{grid-column:1/-1;display:block;width:100%;min-width:0;max-width:100%;margin:28px 0;padding:0;border:0;border-radius:0;background:transparent;color:#35463c;font:16px/1.65 Arial,Helvetica,sans-serif;overflow-wrap:anywhere;letter-spacing:0}
[data-harmat-public-payment-copy] *{box-sizing:border-box;min-width:0;letter-spacing:0}
[data-harmat-public-payment-copy] h2{margin:0 0 16px;padding:0;color:#244537;font:600 24px/1.3 Georgia,serif;text-transform:none;letter-spacing:0}
[data-harmat-public-payment-copy] dl,[data-harmat-public-payment-copy] dd{margin:0;padding:0}
[data-harmat-public-payment-copy] .hppc-row{display:grid;grid-template-columns:minmax(140px,1fr) minmax(0,3fr);gap:16px;padding:16px 0;border-top:1px solid #cbd7ce}
[data-harmat-public-payment-copy] dt{margin:0;padding:0;font:600 16px/1.65 Arial,Helvetica,sans-serif;text-transform:none}
[data-harmat-public-payment-copy] p{margin:0;padding:0;font:400 16px/1.65 Arial,Helvetica,sans-serif}
[data-harmat-public-payment-copy] p+p{margin-top:4px;font-weight:600}
@media(max-width:620px){[data-harmat-public-payment-copy] .hppc-row{grid-template-columns:minmax(0,1fr);gap:6px}}
</style>
<script data-hppc-runtime>
(function(){
  'use strict';
  var root=document.getElementById('harmat-public-payment-copy');
  if(!root||!root.hasAttribute('data-harmat-public-payment-copy'))return;
  function sync(){
    var language=document.documentElement.getAttribute('data-harmat-language')==='zh-Hans'?'zh':'hu';
    root.lang=language==='zh'?'zh-Hans':'hu';
    root.querySelectorAll('[data-hppc-hu]').forEach(function(node){
      var text=node.getAttribute('data-hppc-'+language);
      if(node.textContent!==text)node.textContent=text;
    });
  }
  sync();
  new MutationObserver(sync).observe(document.documentElement,{attributes:true,attributeFilter:['data-harmat-language']});
})();
</script>
</section>
HTML;
}

// Validation only: never serialize native Elementor output or form markup.
function harmat_ppc_known_template(string $html): bool {
    if (!class_exists('DOMDocument') || preg_match('~<(?:form|input|select|textarea|button|script|iframe|frame|frameset|object|embed|template|noscript|svg|canvas|video|audio|img)\b~i', $html)) return false;
    $document = new DOMDocument();
    if (!$document->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) return false;
    $xpath = new DOMXPath($document);
    $roots = $xpath->query('//*[@data-elementor-id="4214"]');
    if ($roots->length !== 1 || $roots->item(0)->getAttribute('data-elementor-type') !== 'container'
        || $xpath->query('//a | //form | //input | //select | //textarea | //button | //script')->length !== 0) return false;
    foreach ($document->getElementsByTagName('*') as $element) {
        if (!in_array($element->tagName, array('html', 'body', 'head', 'div', 'h2', 'p', 'br', 'span', 'style'), true)) return false;
    }
    foreach (iterator_to_array($document->getElementsByTagName('style')) as $style) $style->parentNode->removeChild($style);
    $expected = 'Fizetési Ütemezés Foglaló: 10% Előleg: 15% (A foglalóval együtt ez alkotja az első fizetést) '
        . 'Második részlet: 25% szerkezetkész állapot elérése után Harmadik részlet: 25% belsőépítészeti munkálatok megkezdésekor '
        . 'Negyedik részlet: 20% Miután értesítettük a vevőt a lakások elkészültéről Végső részlet: 5% a kulcsátadáskor';
    $normalize = static function ($text) { return preg_replace('~[\s\x{00a0}]+~u', '', $text); };
    return $normalize($document->getElementsByTagName('body')->item(0)->textContent) === $normalize($expected)
        && $normalize($roots->item(0)->textContent) === $normalize($expected);
}

function harmat_ppc_widget($content, $widget) {
    if (!is_string($content) || !harmat_ppc_scope() || !is_singular('property') || !is_object($widget)
        || !is_callable(array($widget, 'get_name')) || !is_callable(array($widget, 'get_settings'))
        || $widget->get_name() !== 'template' || !in_array($widget->get_settings('template_id'), array(4214, '4214'), true)) return $content;
    if (strpos($content, 'data-harmat-public-payment-copy') !== false || strpos($content, 'harmat-public-payment-copy') !== false) return $content;
    if (!harmat_ppc_known_template($content)) {
        $GLOBALS['harmat_ppc_unknown_template'] = true;
        return $content;
    }
    return harmat_ppc_html();
}

function harmat_ppc_content($content) {
    if (!is_string($content) || !harmat_ppc_scope() || !is_singular('property') || !in_the_loop()
        || (int)get_the_ID() !== (int)get_queried_object_id()
        || !empty($GLOBALS['harmat_ppc_unknown_template'])
        || strpos($content, 'data-harmat-public-payment-copy') !== false || strpos($content, 'harmat-public-payment-copy') !== false) return $content;
    // Cached template HTML bypasses widget filters; the outer final-document buffer owns that fallback.
    if (preg_match('~elementor-4214\b|data-elementor-id\s*=\s*[\'"]?4214\b|(?:template_id|elementor-template)[\s\S]{0,100}?4214\b~i', $content)
        || strpos($content, 'Fizetési Ütemezés') !== false || strpos($content, 'Fizetési ütemezés') !== false) return $content;
    return $content . harmat_ppc_html();
}

function harmat_ppc_financing_articles(): array {
    return array(
        '<article><h2>Fizetési ütemezés</h2><p>A pontos fizetési ütemezés a választott lakástól, az adásvételi folyamat állapotától és az egyedi megállapodástól függ. Az értékesítési csapat minden ajánlatnál külön egyezteti az aktuális részleteket.</p></article>',
        '<article><h2>Fizetési ütemezés</h2><p>A jelenlegi projektmérföldkövek: szerződéskötés a tényleges aláírás napján, szerkezetkész / tetőszint 2027. május, belső munkák 2027. szeptember, műszaki átadás / ellenőrzés 2028. március, várható átadás 2028. június. A pontos fizetési arányokat és határidőket mindig az ajánlat és a szerződés rögzíti.</p></article>',
    );
}

// Byte offsets only. Skip comments and raw-text elements, honor quoted '>', and bound work/depth.
// Track structural containers (including every div) without imposing optional inline HTML end tags.
function harmat_ppc_ranges(string $html) {
    $tracked = array('html', 'head', 'body', 'main', 'div', 'form', 'template', 'noscript', 'svg', 'object', 'article', 'section', 'footer');
    $rawtext = array('script', 'style', 'textarea', 'title', 'iframe', 'xmp', 'noembed', 'noframes');
    $ranges = array(); $stack = array(); $at = 0; $tokens = 0; $doctypes = 0;
    while (($at = strpos($html, '<', $at)) !== false) {
        if (++$tokens > 100000) return false;
        if (substr($html, $at, 4) === '<!--') {
            $end = strpos($html, '-->', $at + 4);
            if ($end === false) return false;
            $at = $end + 3; continue;
        }
        if (preg_match('~\G<!doctype\s+html\s*>~i', $html, $match, 0, $at)) {
            if (++$doctypes > 1) return false;
            $at += strlen($match[0]); continue;
        }
        if (!preg_match('~\G</?([a-z][a-z0-9:-]*)\b(?:[^<>\'" ]|\s|"[^"]*"|\'[^\']*\')*>~i', $html, $match, 0, $at)) {
            if (preg_match('~\G<(?:[a-z!/?])~i', $html, $unused, 0, $at)) return false;
            ++$at; continue;
        }
        $raw = $match[0]; $tag = strtolower($match[1]); $end = $at + strlen($raw);
        $closing = substr($raw, 0, 2) === '</';
        if (!$closing && in_array($tag, $rawtext, true)) {
            if (preg_match('~/\s*>$~', $raw)
                || !preg_match('~</' . $tag . '\s*>~i', $html, $close, PREG_OFFSET_CAPTURE, $end)) return false;
            $at = $close[0][1] + strlen($close[0][0]); continue;
        }
        if (in_array($tag, $tracked, true)) {
            if ($closing) {
                if (!$stack || $ranges[end($stack)]['tag'] !== $tag || !preg_match('~\A</' . $tag . '\s*>\z~i', $raw)) return false;
                $index = array_pop($stack);
                $ranges[$index]['close'] = $at; $ranges[$index]['end'] = $end;
            } else {
                if (count($stack) >= 256 || preg_match('~/\s*>$~', $raw)) return false;
                $index = count($ranges);
                $ranges[] = array('tag' => $tag, 'raw' => $raw, 'start' => $at, 'open_end' => $end, 'parents' => $stack);
                $stack[] = $index;
            }
        }
        $at = $end;
    }
    return !$stack && $doctypes === 1 ? $ranges : false;
}

function harmat_ppc_raw_attr(string $tag, string $name, string $value): bool {
    $name = preg_quote($name, '~'); $value = preg_quote($value, '~');
    return preg_match('~\s' . $name . '\s*=\s*(?:"' . $value . '"|\'' . $value . '\'|' . $value . '(?=\s|>))~i', $tag) === 1;
}

function harmat_ppc_safe_node($node, bool $outside_main = false): bool {
    $in_body = false;
    for ($parent = $node->parentNode; $parent; $parent = $parent->parentNode) {
        if (in_array($parent->nodeName, array('form', 'template', 'noscript', 'svg', 'iframe', 'object', 'script', 'style', 'textarea', 'select', 'button', 'a'), true)
            || ($outside_main && $parent->nodeName === 'main')) return false;
        if ($parent->nodeName === 'body') $in_body = true;
    }
    return $in_body;
}

function harmat_ppc_document(string $html) {
    if (!class_exists('DOMDocument') || strlen($html) > 8 * 1024 * 1024
        || !preg_match('~\A\s*<!doctype html>~i', $html)
        || !($ranges = harmat_ppc_ranges($html))) return false;
    $document = new DOMDocument();
    if (!$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) return false;
    $positions = array();
    foreach (array('html', 'head', 'body') as $tag) {
        $found = array_values(array_filter($ranges, static function ($range) use ($tag) { return $range['tag'] === $tag; }));
        if (count($found) !== 1 || $document->getElementsByTagName($tag)->length !== 1) return false;
        $positions[$tag] = $found[0];
    }
    $p = $positions;
    // WP Super Cache may append comments after the complete document; never accept trailing markup/text.
    if (trim(preg_replace('~<!--[\s\S]*?-->~', '', substr($html, $p['html']['end']))) !== '') return false;
    if (!($p['html']['start'] < $p['head']['start'] && $p['head']['end'] <= $p['body']['start']
        && $p['body']['end'] <= $p['html']['close'])) return false;
    $xpath = new DOMXPath($document);
    $property = is_singular('property');
    $id = $property ? 'content' : 'main'; $tag = $property ? 'div' : 'main';
    $class = $property ? 'site-content' : 'harmat-financing-page';
    $owners = $xpath->query('//' . $tag . '[@id="' . $id . '" and contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]');
    $raw_owners = array_values(array_filter($ranges, static function ($range) use ($id, $tag) {
        return $range['tag'] === $tag && harmat_ppc_raw_attr($range['raw'], 'id', $id);
    }));
    if ($owners->length !== 1 || $xpath->query('//*[@id="' . $id . '"]')->length !== 1 || count($raw_owners) !== 1
        || !harmat_ppc_safe_node($owners->item(0)) || (!$property && $document->getElementsByTagName('main')->length !== 1)) return false;
    $owner = $raw_owners[0];
    if ($owner['start'] < $p['body']['open_end'] || $owner['end'] > $p['body']['close']) return false;
    foreach ($owner['parents'] as $index) {
        if (in_array($ranges[$index]['tag'], array('form', 'template', 'noscript', 'svg', 'object'), true)) return false;
    }
    if ($property && $xpath->query('//body[contains(concat(" ", normalize-space(@class), " "), " single-property ")]')->length !== 1) return false;
    $positions['content'] = $owner;
    return array($document, $xpath, $ranges, $positions);
}

function harmat_ppc_payment_headings($xpath): int {
    $count = 0;
    foreach ($xpath->query('//body//h1 | //body//h2 | //body//h3 | //body//h4 | //body//h5 | //body//h6') as $heading) {
        if (preg_match('~fizet[eé]si\s+[uü]temez[eé]s|付款安排|付款计划~iu', $heading->textContent)) ++$count;
    }
    return $count;
}

function harmat_ppc_output($html) {
    if (!is_string($html) || !harmat_ppc_scope() || http_response_code() !== 200 || !($parsed = harmat_ppc_document($html))) return $html;
    list($document, $xpath, $ranges, $positions) = $parsed;
    if ($xpath->query('//*[@id="harmat-public-payment-copy" or @data-harmat-public-payment-copy]')->length !== 0
        || !empty($GLOBALS['harmat_ppc_unknown_template'])) return $html;
    $start = null; $length = 0;
    if (is_page('finanszirozas')) {
        $articles = $xpath->query('//main[@id="main" and contains(concat(" ", normalize-space(@class), " "), " harmat-financing-page ")]//section[contains(concat(" ", normalize-space(@class), " "), " harmat-info-grid ")]/article[h2[normalize-space()="Fizetési ütemezés"]]');
        if ($articles->length !== 1 || harmat_ppc_payment_headings($xpath) !== 1 || !harmat_ppc_safe_node($articles->item(0))) return $html;
        $matches = array();
        foreach (harmat_ppc_financing_articles() as $literal) {
            $count = substr_count($html, $literal);
            if ($count > 1) return $html;
            if ($count === 1) $matches[] = $literal;
        }
        if (count($matches) !== 1) return $html;
        $start = strpos($html, $matches[0]); $length = strlen($matches[0]);
        $raw_articles = array_filter($ranges, static function ($range) use ($start, $length) {
            return $range['tag'] === 'article' && $range['start'] === $start && $range['end'] === $start + $length;
        });
        if (count($raw_articles) !== 1 || $start < $positions['content']['open_end'] || $start + $length > $positions['content']['close']) return $html;
        foreach (reset($raw_articles)['parents'] as $index) {
            if (in_array($ranges[$index]['tag'], array('form', 'template', 'noscript', 'svg', 'object'), true)) return $html;
        }
    } else {
        $templates = $xpath->query('//*[@data-elementor-id="4214"]');
        if ($templates->length > 1) return $html;
        if ($templates->length === 1) {
            $node = $templates->item(0);
            if ($node->nodeName !== 'div' || !harmat_ppc_safe_node($node) || harmat_ppc_payment_headings($xpath) !== 1) return $html;
            $raw_templates = array_values(array_filter($ranges, static function ($range) {
                return $range['tag'] === 'div' && harmat_ppc_raw_attr($range['raw'], 'data-elementor-id', '4214');
            }));
            if (count($raw_templates) !== 1) return $html;
            $range = $raw_templates[0]; $start = $range['start']; $length = $range['end'] - $start;
            if ($start < $positions['content']['open_end'] || $range['end'] > $positions['content']['close']
                || !harmat_ppc_known_template(substr($html, $start, $length))) return $html;
            foreach ($range['parents'] as $index) {
                if (in_array($ranges[$index]['tag'], array('form', 'template', 'noscript', 'svg', 'object'), true)) return $html;
            }
        } else {
            if (harmat_ppc_payment_headings($xpath) !== 0 || preg_match('~elementor-4214\b|(?:template_id|elementor-template|data-elementor-id)[\s\S]{0,100}?4214\b~i', $html)) return $html;
            $notes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " harmat-property-disclaimer ")]');
            $footers = $xpath->query('//*[@id="colophon"]');
            $marker = '<div class="harmat-property-disclaimer" data-harmat-property-disclaimer="1">';
            if ($notes->length > 1 || $footers->length !== 1) return $html;
            if ($notes->length === 1) {
                if ($notes->item(0)->nodeName !== 'div' || !harmat_ppc_safe_node($notes->item(0), true)) return $html;
            } else {
                $marker = '<footer id="colophon" class="site-footer harmat-clean-footer" role="contentinfo">';
                if ($footers->item(0)->nodeName !== 'footer' || !harmat_ppc_safe_node($footers->item(0), true)) return $html;
            }
            if (substr_count($html, $marker) !== 1) return $html;
            $start = strpos($html, $marker);
            $anchors = array_values(array_filter($ranges, static function ($range) use ($start, $marker) { return $range['start'] === $start && $range['raw'] === $marker; }));
            if (count($anchors) !== 1 || $start < $positions['content']['end'] || $start >= $positions['body']['close']) return $html;
            foreach ($anchors[0]['parents'] as $index) {
                if (in_array($ranges[$index]['tag'], array('main', 'form', 'template', 'noscript', 'svg', 'object'), true)) return $html;
            }
        }
    }
    $result = substr_replace($html, harmat_ppc_html(), $start, $length);
    $verified = harmat_ppc_document($result);
    if (!$verified) return $html;
    $roots = $verified[1]->query('//*[@id="harmat-public-payment-copy" and @data-harmat-public-payment-copy="1"]');
    if ($roots->length !== 1 || !harmat_ppc_safe_node($roots->item(0))) return $html;
    return $result;
}

function harmat_ppc_buffer_chunk($html, int $phase, bool &$intact) {
    if (!($phase & PHP_OUTPUT_HANDLER_FINAL) || ($phase & (PHP_OUTPUT_HANDLER_CLEAN | PHP_OUTPUT_HANDLER_FLUSH))) $intact = false;
    return $intact ? harmat_ppc_output($html) : $html;
}

function harmat_ppc_buffer() {
    if (!harmat_ppc_scope() || headers_sent()) return;
    $intact = true;
    ob_start(static function ($html, $phase) use (&$intact) { return harmat_ppc_buffer_chunk($html, $phase, $intact); });
}

add_filter('elementor/widget/render_content', 'harmat_ppc_widget', 1000, 2);
add_filter('the_content', 'harmat_ppc_content', 1000);
add_action('template_redirect', 'harmat_ppc_buffer', -4000);
