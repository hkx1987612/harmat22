'use strict';
// Pure local derivation: emits apply_patch input, never uploads or edits source files.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const ROOT = path.resolve(__dirname, '../..');
const SOURCE = path.join(ROOT, 'server-config/previews/home-family');
const OUT = path.join(ROOT, 'outputs/2026-10-18-home-family');
const DEST = 'wp-mu-plugins/harmat-home-family/';
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const read = name => fs.readFileSync(name, 'utf8').replace(/\r\n/g, '\n');
const hash = value => crypto.createHash('sha256').update(value).digest('hex');
const OWNER = '#harmat-home-family';

function selectors(value) {
  // CSSOM parses declarations/rules; retain commas inside functional selectors.
  const result = []; let depth = 0, quote = '', start = 0;
  for (let i = 0; i < value.length; i++) {
    const c = value[i];
    if (c === '\\') { i++; continue; }
    if (quote) { if (c === quote) quote = ''; continue; }
    if (c === '"' || c === "'") { quote = c; continue; }
    if (c === '(' || c === '[') depth++;
    if (c === ')' || c === ']') depth--;
    if (c === ',' && depth === 0) { result.push(value.slice(start, i).trim()); start = i + 1; }
  }
  result.push(value.slice(start).trim());
  return result;
}

function buildFiles() {
  const dom = new JSDOM(read(path.join(SOURCE, 'index.html')), { runScripts: 'outside-only' });
  const d = dom.window.document;
  const main = d.querySelector('main');
  // The retained legacy home polisher expands every standalone span "lakas".
  main.querySelector('[data-i18n="homes"]').textContent = 'otthon';
  const classes = new Set(Array.from(main.querySelectorAll('[class]')).flatMap(n => Array.from(n.classList)));
  const ids = new Set(Array.from(main.querySelectorAll('[id]'), n => n.id));
  const classPattern = /\.([a-z][\w-]*)/g;
  const classRename = value => value.replace(classPattern, (all, name) => classes.has(name) ? '.hhf-' + name : all);
  const idRename = value => value.replace(/#([a-z][\w-]*)/g, (all, name) => ids.has(name) && name !== 'hero-title' ? '#hhf-' + name : all);
  const scopeCss = (source, vendor = false) => {
    const style = d.createElement('style'); style.textContent = source; d.head.append(style);
    const sheet = style.sheet;
    if (!sheet) throw new Error('CSS parse failed');
    function rules(list) {
      return Array.from(list, rule => {
        if (rule.selectorText) {
          const scoped = selectors(rule.selectorText).filter(s => vendor || !/\.(?:site-header|header-|site-menu|menu-|language-switch|booking-|brand\b|site-footer|footer-|copyright\b|skip-link)/.test(s)).map(s => {
            if (vendor) return OWNER + ' ' + s;
            s = idRename(classRename(s));
            if (s === ':root' || s === 'html' || s === 'body') return OWNER;
            if (s.startsWith('html[lang="zh-Hans"]')) return s.replace('html[lang="zh-Hans"]', OWNER + '[lang="zh-Hans"]');
            return OWNER + ' ' + s;
          });
          return scoped.length ? scoped.join(', ') + ' { ' + rule.style.cssText + ' }\n' : '';
        }
        if (rule.media) return '@media ' + rule.media.mediaText + ' {\n' + rules(rule.cssRules) + '}\n';
        if (rule.type === 7) return rule.cssText.replace(/pnlm-mv/g, 'hhf-pnlm-mv') + '\n';
        throw new Error('Unsupported CSS rule: ' + rule.cssText.slice(0, 80));
      }).join('');
    }
    let result = rules(sheet.cssRules).replace(/pnlm-mv/g, 'hhf-pnlm-mv').replace(/hhf-hhf-pnlm/g, 'hhf-pnlm');
    style.remove();
    return result;
  };

  const manifest = JSON.parse(read(path.join(OUT, 'asset-manifest.json')));
  const interactive = JSON.parse(read(path.join(OUT, 'interactive-manifest.json')));
  const assets = {};
  for (const [name, record] of Object.entries(manifest)) {
    if (record.local_source?.startsWith('wp-mu-plugins/')) assets[name] = 'https://harmat22.hu/wp-content/mu-plugins/' + record.local_source.slice('wp-mu-plugins/'.length);
    else if (record.url) assets[name] = record.url;
  }
  for (const [name, record] of Object.entries(interactive)) if (record.url) assets[name] = record.url;
  assets['hero-video.mp4'] = 'https://harmat22.hu/wp-content/uploads/harmat-video/harmat-home-1080p-v2.mp4';
  assets['project-panorama.jpg'] = interactive['panorama-f.jpg'].url;
  // Exact deployed path recorded by the September park release, not a guessed path.
  const parkOwner = read(path.join(ROOT, 'server-config/maintenance/2026-09-28-deploy-search-construction-park.py'));
  const parkPath = '/wp-content/uploads/2026/09/harmat-kornyek-kutyapark-2026-09.jpg';
  if (!parkOwner.includes('"' + parkPath + '"')) throw new Error('Park URL proof drift');
  assets['park.jpg'] = 'https://harmat22.hu' + parkPath;

  for (const n of main.querySelectorAll('.sr-only[data-i18n="officialNewTab"]')) n.remove();
  for (const n of main.querySelectorAll('[target], [rel]')) { n.removeAttribute('target'); n.removeAttribute('rel'); }
  for (const n of main.querySelectorAll('[data-i18n-aria-label$="Link"]')) {
    if (n.closest('.apartment')) continue;
    n.removeAttribute('data-i18n-aria-label');
  }
  for (const n of main.querySelectorAll('[class]')) n.className = Array.from(n.classList, c => 'hhf-' + c).join(' ');
  for (const n of main.querySelectorAll('[id]')) if (n.id !== 'hero-title') n.id = 'hhf-' + n.id;
  for (const n of main.querySelectorAll('*')) {
    for (const attr of ['aria-labelledby', 'aria-describedby', 'aria-controls']) {
      if (n.hasAttribute(attr)) n.setAttribute(attr, n.getAttribute(attr).split(' ').map(v => ids.has(v) && v !== 'hero-title' ? 'hhf-' + v : v).join(' '));
    }
    for (const attr of Array.from(n.attributes)) {
      if (attr.value.startsWith('#') && ids.has(attr.value.slice(1))) n.setAttribute(attr.name, '#hhf-' + attr.value.slice(1));
      if (attr.value.startsWith('assets/')) {
        const name = attr.value.slice(7);
        if (!assets[name]) throw new Error('Missing asset: ' + name);
        n.setAttribute(attr.name, assets[name]);
      }
    }
  }
  const lucideBytes = fs.readFileSync(path.join(OUT, 'assets/lucide.min.js'));
  // Manifest SRI pins the npm tarball, not the extracted UMD member.
  if (hash(lucideBytes) !== '3411692820cb8d47543f69496aa25fd603a358f4498046f41c508a5a3342210e') throw new Error('Lucide integrity drift');
  dom.window.eval(lucideBytes.toString('utf8'));
  function iconNode(spec) {
    const [tag, attrs, children = []] = spec;
    const n = d.createElementNS('http://www.w3.org/2000/svg', tag);
    for (const [key, value] of Object.entries(attrs)) n.setAttribute(key, value);
    children.forEach(child => n.append(iconNode(child)));
    return n;
  }
  for (const n of main.querySelectorAll('[data-lucide]')) {
    const name = n.getAttribute('data-lucide');
    const key = name.split('-').map(s => s[0].toUpperCase() + s.slice(1)).join('');
    const svg = iconNode(dom.window.lucide.icons[key]);
    for (const attr of n.attributes) if (attr.name !== 'data-lucide') svg.setAttribute(attr.name, attr.value);
    svg.setAttribute('focusable', 'false'); n.replaceWith(svg);
  }
  const cardIcon = main.querySelector('.hhf-apartment-link svg').outerHTML;
  main.querySelector('.hhf-apartment-grid').innerHTML = '__HHF_UNITS_HTML__';
  main.id = 'main'; main.className = 'site-main';
  const wrapper = d.createElement('div'); wrapper.id = 'harmat-home-family';
  wrapper.setAttribute('data-language-protect', ''); wrapper.lang = 'hu';
  while (main.firstChild) wrapper.append(main.firstChild);
  main.append(wrapper);
  const template = main.outerHTML;

  const php = `<?php
/** Include returns main HTML only; caller supplies fresh public-summary rows keyed by code. */
return (static function (array $harmat_home_family_units): string {
    $escape = static function ($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $public_url = static function ($value): bool {
        if (!is_string($value) || preg_match('/[\\x00-\\x20\\x7f]/', $value)) return false;
        $parts = parse_url($value);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === 'harmat22.hu'
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
            && strpos($parts['path'] ?? '', '/') === 0;
    };
    $number = static function ($value, float $max): bool {
        return (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\\d+(?:\\.\\d+)?$/D', $value)))
            && is_finite((float) $value) && (float) $value > 0 && (float) $value <= $max;
    };
    $cards = '';
    $floors = array('A1-1-L6' => array('a1Floor1', 'A1 épület · 1. emelet'),
        'A2-4-L5' => array('a2Floor4', 'A2 épület · 4. emelet'),
        'A1-1-L5' => array('a1Floor1', 'A1 épület · 1. emelet'));
    foreach ($floors as $code => $floor) {
        $row = $harmat_home_family_units[$code] ?? null;
        if (!is_array($row) || ($row['code'] ?? null) !== $code || ($row['status'] ?? null) !== 'current'
            || !$public_url($row['url'] ?? null) || !$public_url($row['floorplan_url'] ?? null)
            || !$number($row['rooms'] ?? null, 20) || !$number($row['sales_area'] ?? null, 2000)
            || !array_key_exists('price_huf', $row)
            || ($row['price_huf'] !== null && !$number($row['price_huf'], 1000000000000))) continue;
        $url = $escape($row['url']);
        $image = $escape($row['floorplan_url']);
        $rooms = $escape(rtrim(rtrim(number_format((float) $row['rooms'], 2, ',', ''), '0'), ','));
        $room_keys = array('1' => array('oneRoom', '1 szoba'), '2' => array('twoRooms', '2 szoba'), '4' => array('fourRooms', '4 szoba'));
        $room_copy = $room_keys[(string) (float) $row['rooms']] ?? null;
        $rooms_html = $room_copy === null ? '<dd>' . $rooms . '</dd>'
            : '<dd data-i18n="' . $escape($room_copy[0]) . '">' . $escape($room_copy[1]) . '</dd>';
        $area = $escape(number_format((float) $row['sales_area'], 2, ',', ' '));
        $price = $row['price_huf'] === null
            ? '<strong class="hhf-price-hidden" data-i18n="hiddenPrice">Ár egyeztetés alapján</strong>'
            : '<strong>' . $escape(number_format((float) $row['price_huf'], 0, ',', ' ')) . ' <span>Ft</span></strong>';
        $cards .= '<article class="hhf-apartment" data-code="' . $escape($code) . '" data-status="current">'
            . '<div class="hhf-apartment-top"><h3>' . $escape($code) . '</h3><span class="hhf-status" data-i18n="available">Elérhető</span></div>'
            . '<p class="hhf-apartment-floor" data-i18n="' . $escape($floor[0]) . '">' . $escape($floor[1]) . '</p>'
            . '<a class="hhf-floorplan" href="' . $url . '"><img src="' . $image . '" width="700" height="600" alt="' . $escape($code) . '" loading="lazy" decoding="async"></a>'
            . '<div class="hhf-apartment-details"><dl class="hhf-apartment-facts"><div><dt data-i18n="roomCount">Szobák</dt>' . $rooms_html . '</div>'
            . '<div><dt data-i18n="salesArea">Értékesítési terület</dt><dd>' . $area . ' m²</dd></div></dl>'
            . '<p class="hhf-price"><span data-i18n="price">Vételár</span>' . $price . '</p>'
            . '<a class="hhf-apartment-link" href="' . $url . '"><span data-i18n="apartmentDetails">Lakás adatlapja</span>${cardIcon}</a></div></article>';
    }
    $html = <<<'HHF_MAIN_HTML'
${template}
HHF_MAIN_HTML;
    return str_replace('__HHF_UNITS_HTML__', $cards, $html);
})(isset($harmat_home_family_units) && is_array($harmat_home_family_units) ? $harmat_home_family_units : array());
`;

  let js = read(path.join(SOURCE, 'home-family.js'));
  js = js.replace(/  if \(document\.documentElement\.dataset\.homeFamilyReady\)[\s\S]*?(?=  const zh =)/, `  const ownedRoot = document.getElementById('harmat-home-family');\n  if (!ownedRoot || ownedRoot.dataset.homeFamilyReady) return;\n  ownedRoot.dataset.homeFamilyReady = 'true';\n\n`);
  js = js.replace("    skip: '跳转到主要内容',", "    hiddenPrice: '价格面议',\n    skip: '跳转到主要内容',");
  js = js.replace(/document\.querySelectorAll/g, 'ownedRoot.querySelectorAll').replace(/document\.querySelector/g, 'ownedRoot.querySelector');
  js = js.replace(/  const originalTitle[\s\S]*?(?=  const video =)/, '');
  js = js.replace(/  function updateMenuLabel\(\)[\s\S]*?(?=  function updateVideoControl)/, '');
  js = js.replace(/    document\.documentElement\.lang = .*\n/, "    ownedRoot.lang = language === 'zh' ? 'zh-Hans' : 'hu';\n");
  js = js.replace('original: node.innerHTML', 'original: node.textContent');
  js = js.replace('entry.node.innerHTML = entry.original', 'entry.node.textContent = entry.original');
  js = js.replace(/else entry\.node\.textContent = zh\[entry\.key\];/, "else if (typeof zh[entry.key] === 'string') entry.node.textContent = zh[entry.key];");
  js = js.replace(/language === 'zh' \? zh\[entry.key\] : entry.original/g, "language === 'zh' && typeof zh[entry.key] === 'string' ? zh[entry.key] : entry.original");
  js = js.replace(/    languageSwitch\.querySelectorAll[\s\S]*?(?=    updateVideoControl\(\);)/, '');
  js = js.replace(/  \/\/ Do not expose a language choice[\s\S]*?(?=  function heroWithinViewport)/, `  let appliedNativeLanguage = null;\n  function syncNativeLanguage() {\n    const marker = document.documentElement.getAttribute('data-harmat-language');\n    const next = marker === 'zh-Hans' ? 'zh' : 'hu';\n    // The native runtime may reassert an unchanged marker after observing DOM work.\n    if (appliedNativeLanguage === next) return;\n    appliedNativeLanguage = next;\n    setLanguage(next);\n  }\n  const languageObserver = new MutationObserver(syncNativeLanguage);\n  languageObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-harmat-language'] });\n\n`);
  js = js.replace(/  updateMenuLabel\(\);\n/g, '');
  js = js.replace(/document.getElementById\('(video-tooltip|video-status)'\)/g, (_, id) => "ownedRoot.querySelector('#hhf-" + id + "')");
  js = classRename(js);
  js = js.replace("  updatePanoramaLanguage = initPanorama();", "  updatePanoramaLanguage = initPanorama();\n  syncNativeLanguage();");
  // Keep panel clip URLs lazy as well as preload=none, including the shared native hero video.
  js = js.replace("      if (name !== 'video') pauseClips();", "      if (name !== 'video') pauseClips();\n      else clips.forEach(clip => { if (!clip.getAttribute('src')) clip.src = clip.dataset.src; });");
  const body = php.replace(/(<video controls[^>]*?) src=/g, '$1 data-src=');
  const vendor = fs.readFileSync(path.join(OUT, 'assets/pannellum.js'));
  if (hash(vendor) !== 'a28b2f7b339fd0a602c6769df1dca6ad43af73bc8c6a5be67209715289c12a9a') throw new Error('Pannellum pin drift');
  let css = scopeCss(read(path.join(SOURCE, 'home-family.css')));
  css += OWNER + ' { display: block; width: 100%; min-width: 0; }\n';
  css += OWNER + ' h1, ' + OWNER + ' h2, ' + OWNER + ' h3 { text-transform: none; }\n';
  css += OWNER + ' .hhf-price .hhf-price-hidden { white-space: normal; font-size: 19px; }\n';
  css += OWNER + ' [id] { scroll-margin-top: 140px; }\n';
  // CSSOM strips no vendor declarations; only selector ownership and keyframe names change.
  const vendorCss = scopeCss(read(path.join(OUT, 'assets/pannellum.css')), true);
  const files = { 'body.php': body, 'home-family.css': css, 'home-family.js': js,
    'pannellum.js': vendor.toString('utf8'), 'pannellum.css': '/* Pannellum 2.5.6; scoped to owned subtree. See COPYING. */\n' + vendorCss,
    'COPYING': 'Pannellum 2.5.6 (pannellum.js / pannellum.css)\n\n' + read(path.join(OUT, 'assets/pannellum.LICENSE')) + '\nLucide 0.468.0 (build-time SVG icons in body.php)\n\n' + read(path.join(OUT, 'assets/lucide.LICENSE')) };
  for (const name of Object.keys(files)) files[name] = files[name].replace(/\r\n/g, '\n').trimEnd() + '\n';
  files['manifest.json'] = JSON.stringify(Object.fromEntries(Object.entries(files).sort(([a], [b]) => a.localeCompare(b, 'en')).map(([name, content]) => [name, hash(content)])), null, 2) + '\n';
  dom.window.close();
  return { files, assets };
}

module.exports = { buildFiles, selectors, ROOT, DEST };
if (require.main === module) {
  const { files, assets } = buildFiles();
  if (process.argv[2] === '--asset-map') process.stdout.write(JSON.stringify(assets, null, 2) + '\n');
  else if (process.argv[2] === '--patch' && files[process.argv[3]]) {
    const name = process.argv[3], target = path.join(ROOT, DEST, name).replace(/\\/g, '/');
    let patch = '*** Begin Patch\n';
    if (fs.existsSync(target)) patch += '*** Update File: ' + target + '\n@@\n' + read(target).trimEnd().split('\n').map(l => '-' + l).join('\n') + '\n';
    else patch += '*** Add File: ' + target + '\n';
    patch += files[name].trimEnd().split('\n').map(l => '+' + l).join('\n') + '\n*** End Patch\n';
    process.stdout.write(patch);
  } else throw new Error('Use --patch <owned filename> or --asset-map; no direct filesystem writes');
}
