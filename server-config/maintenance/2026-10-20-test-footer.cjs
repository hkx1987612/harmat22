'use strict';
// Offline CSSOM/translation contracts, not browser layout or pixel verification.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync, spawnSync } = require('node:child_process');
const ROOT = path.resolve(__dirname, '../..');
const REL = 'wp-mu-plugins/harmat-migrated-snippets.php';
const candidate = path.resolve(process.env.HARMAT_FOOTER_CANDIDATE || process.argv[2] || path.join(ROOT, REL));
const php = process.env.HARMAT_PHP || 'php';
const { JSDOM, ResourceLoader, VirtualConsole } = require(process.env.HARMAT_JSDOM
  || path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const sha = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const normalize = value => value.replace(/\r\n/g, '\n');
const raw = fs.readFileSync(candidate);
const source = raw.toString('utf8');
const original = process.env.HARMAT_FOOTER_BASELINE
  ? fs.readFileSync(process.env.HARMAT_FOOTER_BASELINE)
  : execFileSync('git', ['show', 'd608550:' + REL], { cwd: ROOT });
const baseline = original.toString('utf8');
const fixture = JSON.parse(execFileSync(php, [path.join(__dirname, '2026-10-20-test-footer.php'), candidate, '--render'], {
  cwd: ROOT, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024
}));
const langDir = path.join(ROOT, 'wp-mu-plugins/harmat-internal-language');
const dictionary = JSON.parse(fs.readFileSync(path.join(langDir, 'zh-Hans.json'), 'utf8'));
const runtime = fs.readFileSync(path.join(langDir, 'runtime.js'), 'utf8');
const patterns = fs.readFileSync(path.join(langDir, 'patterns.js'), 'utf8');
const OPENING = '<footer id="colophon" class="site-footer harmat-clean-footer" role="contentinfo">';
const SCOPE = '#colophon.harmat-clean-footer';
const CANDIDATE_LF_SHA256 = 'a74f6de8d93bc5f183cf7447be3100acbebacf19228cf2f5ea1de62d5d2b375f';

function relocatedFixture(dictionaryPath, render = false) {
  // Evaluated CLI code resolves __DIR__ away from the repo, without staging writes.
  const bootstrap = '$fixture = $argv[1]; $argv = array_slice($argv, 1); eval(substr(file_get_contents($fixture), 5));';
  return spawnSync(php, ['-r', bootstrap, path.join(__dirname, '2026-10-20-test-footer.php'), candidate,
    ...(render ? ['--render'] : [])], {
    cwd: require('node:os').tmpdir(), encoding: 'utf8', maxBuffer: 4 * 1024 * 1024,
    env: { ...process.env, HARMAT_FOOTER_DICTIONARY: dictionaryPath }
  });
}

function ownedMask(value) {
  const replacements = [
    [/ \* Version: [^\r\n]+/, ' * Version: <owned>'],
    [/function hm_migrated_clean_footer_html\(\) \{[\s\S]*?\r?\n\}/, '<owned-footer-function>'],
    [/(<style id="harmat-migrated-public-layout-css">\r?\n)[\s\S]*?(?=body:not\(\.elementor-editor-active\) \.harmat-ai-assistant)/,
      '$1<owned-footer-css>'],
  ];
  for (const [pattern, replacement] of replacements) {
    assert.equal([...value.matchAll(new RegExp(pattern.source, 'g'))].length, 1, 'one bounded owned span');
    value = value.replace(pattern, replacement);
  }
  // Exactly these footer fragments may leave the shared legacy media rules.
  return value.replace('.harmat-clean-footer-inner,', '')
    .replace('.harmat-clean-footer-inner{grid-template-columns:1fr;padding:28px 18px}', '');
}
function setup(t, { css = true } = {}) {
  const network = [], errors = [];
  class NoNetwork extends ResourceLoader {
    fetch(url) { network.push(url); throw new Error('NETWORK_BLOCKED'); }
  }
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', error => errors.push(error.message));
  const dom = new JSDOM('<!doctype html><html lang="hu"><head>'
    + (css ? '<style>' + fixture.css + '</style>' : '') + '</head><body>' + fixture.html + '</body></html>', {
    url: 'https://harmat22.hu/', runScripts: 'outside-only', resources: new NoNetwork(), virtualConsole
  });
  const w = dom.window, d = w.document;
  for (const name of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource']) {
    w[name] = () => { network.push(name); throw new Error('NETWORK_BLOCKED'); };
  }
  w.navigator.sendBeacon = () => { network.push('beacon'); throw new Error('NETWORK_BLOCKED'); };
  w.HTMLFormElement.prototype.submit = w.HTMLFormElement.prototype.requestSubmit = () => { throw new Error('SUBMISSION_BLOCKED'); };
  let submits = 0;
  d.addEventListener('submit', event => { submits++; event.preventDefault(); }, true);
  w.addEventListener('error', event => errors.push(event.message));
  let tr;
  t.after(() => {
    tr?.destroy(); w.close();
    assert.deepEqual(network, []); assert.deepEqual(errors, []); assert.equal(submits, 0);
  });
  return { w, d, translator() {
    w.eval(patterns); w.eval(runtime);
    tr = w.HarmatInternalLanguage.create(d, dictionary, {
      enabled: true, storage: false, translateDynamic: w.HarmatLanguagePatterns.translate
    });
    return tr;
  } };
}
function footerRules(d, width) {
  const list = [];
  function walk(rules, active) {
    for (const rule of rules) {
      if (rule.media) {
        const text = rule.conditionText || rule.media.mediaText;
        const bounded = /^\(max-width:\s*(\d+)px\)$/.exec(text);
        const hasFooter = /harmat-clean-footer/.test(rule.cssText);
        if (hasFooter) assert.ok(bounded, 'footer media condition explicitly understood');
        walk(rule.cssRules, active && (!hasFooter || width <= Number(bounded[1])));
      } else if (rule.selectorText && /harmat-clean-footer/.test(rule.selectorText)) {
        for (const selector of rule.selectorText.split(',')) {
          assert.ok(selector.trim().startsWith(SCOPE), 'every footer selector strongly scoped: ' + selector);
        }
        if (active) list.push(rule);
      }
    }
  }
  for (const sheet of d.styleSheets) walk(sheet.cssRules, true);
  assert.ok(list.length >= 17, 'real parsed footer rules');
  return list;
}
// Resolve the owned rules only. CSSOM parses declarations/media; jsdom has no layout.
function declarations(rules, element) {
  const values = {}, priorities = {};
  for (const rule of rules) {
    if (!element.matches(rule.selectorText)) continue;
    for (let i = 0; i < rule.style.length; i++) {
      const name = rule.style[i];
      const priority = rule.style.getPropertyPriority(name);
      if (priorities[name] === 'important' && priority !== 'important') continue;
      values[name] = rule.style.getPropertyValue(name).trim();
      priorities[name] = priority;
    }
  }
  return values;
}
function padding(value) {
  const p = value.split(/\s+/).map(Number.parseFloat);
  return p.length === 3 ? [p[0], p[1], p[2], p[1]] : p;
}

test('exact d608550 recovery allowlist, including original raw nonowned bytes', () => {
  assert.equal(sha(normalize(baseline)), '588e2740b09e02aff24f6d5ed3d89d34a76d3c142518c1a3c38e5e81646db2aa');
  assert.equal(sha(normalize(source)), CANDIDATE_LF_SHA256, 'frozen reviewed candidate, independent of transported EOL');
  assert.equal(ownedMask(normalize(source)), ownedMask(normalize(baseline)), 'all nonowned source, hooks, forms, JS and legal function unchanged');
  let rawBaseline = baseline;
  if (!process.env.HARMAT_FOOTER_BASELINE) {
    const crlf = (source.match(/\r\n/g) || []).length;
    const bareLF = (source.match(/(?<!\r)\n/g) || []).length;
    assert.ok(!(crlf && bareLF), 'mixed-EOL proof requires HARMAT_FOOTER_BASELINE pointing to exact original raw source');
    if (crlf) rawBaseline = normalize(baseline).replace(/\n/g, '\r\n');
  }
  assert.equal(ownedMask(source), ownedMask(rawBaseline), 'byte-for-byte original nonowned spans with original EOL');
  assert.match(source, / \* Version: 2026\.10\.20\.2\r?\n/);
  assert.equal(Object.keys(fixture).sort().join(','), 'css,html');
  assert.ok(fixture.html.startsWith(OPENING));
  const literal = normalize(source).match(/<style id="harmat-migrated-public-layout-css">([\s\S]*?)<\/style>/);
  assert.equal(fixture.css, literal[1], '--render returns entire literal native stylesheet');
});

test('exactly three R2 source edits reverse to released R1 without changing any other bytes', () => {
  let r1 = source;
  for (const [from, to] of [
    [' * Version: 2026.10.20.2', ' * Version: 2026.10.20.1'],
    ['padding:18px 24px 76px;text-align:left}', 'padding:18px 24px 22px;text-align:left}'],
    ['.harmat-clean-footer-bottom{padding:16px 16px 76px}', '.harmat-clean-footer-bottom{padding:16px 16px 20px}'],
  ]) {
    assert.equal(r1.split(from).length - 1, 1, 'one exact R2-to-R1 reversal');
    r1 = r1.replace(from, to);
  }
  assert.equal(sha(normalize(r1)), '32868104d0da9d2a85ba0b779db408fe1688e8ec1ea3da4fda3615b3e8f92b19');
  if (!source.includes('\r')) assert.equal(sha(r1), '32868104d0da9d2a85ba0b779db408fe1688e8ec1ea3da4fda3615b3e8f92b19');
  else if (!/(?<!\r)\n/.test(source)) assert.equal(sha(r1), 'b6c766dccc3665ca0ad484207b8411c3cc0f527461faa966ca5c412f93bd0338');
});

test('relocated pure fixture accepts absolute local dictionary override in normal and render modes; count is 485', () => {
  const dictionaryPath = path.join(langDir, 'zh-Hans.json');
  const normal = relocatedFixture(dictionaryPath);
  assert.ifError(normal.error);
  assert.equal(normal.status, 0, normal.stderr);
  assert.equal(normal.stderr, '');
  assert.equal(normal.stdout, 'PASS footer: 485 assertions; pure extraction, no WordPress/network.\n');
  const render = relocatedFixture(dictionaryPath, true);
  assert.ifError(render.error);
  assert.equal(render.status, 0, render.stderr);
  assert.equal(render.stderr, '');
  assert.deepEqual(JSON.parse(render.stdout), fixture);
});

test('dictionary override rejects URL/wrapper, relative, empty, directory and missing paths before reading', () => {
  for (const dictionaryPath of ['', 'relative-dictionary.json', 'C:relative-dictionary.json',
    'https://harmat22.hu/dictionary.json', 'file:///tmp/dictionary.json', 'php://input',
    'data:application/json,{}', '//server/share/dictionary.json', '\\\\server\\share\\dictionary.json',
    langDir, path.join(langDir, 'missing-footer-dictionary.json')]) {
    const result = relocatedFixture(dictionaryPath);
    assert.ifError(result.error);
    assert.equal(result.status, 2, dictionaryPath);
    assert.equal(result.stdout, '');
    assert.equal(result.stderr, 'HARMAT_FOOTER_DICTIONARY must name a readable absolute local file, not a URL.\n');
  }
});

test('allowlist rejects in-memory drift in other owners and shared media declarations', () => {
  const masked = ownedMask(source);
  for (const [before, after] of [
    ['function hm_migrated_legal_links()', 'function hm_migrated_legal_links_changed()'],
    ['.harmat-project-note{grid-template-columns:1fr}', '.harmat-project-note{grid-template-columns:2fr}'],
    ['.harmat-home-opening-notice{margin:82px 14px 0', '.harmat-home-opening-notice{margin:99px 14px 0'],
    ['function hm_migrated_move_hidden_forms_to_footer(', 'function hm_migrated_move_hidden_forms_changed('],
  ]) {
    assert.ok(source.includes(before), 'real protected sentinel exists');
    assert.notEqual(ownedMask(source.replace(before, after)), masked, 'nonowned mutation detected');
  }
});

test('entire changed stylesheet prefix contains only scoped footer rules and its 620px media', t => {
  const prefix = fixture.css.slice(0, fixture.css.indexOf('body:not(.elementor-editor-active) .harmat-ai-assistant'));
  assert.ok(prefix.length > 0);
  const f = setup(t, { css: false });
  const style = f.d.createElement('style');
  style.textContent = prefix;
  f.d.head.append(style);
  function validate(rules) {
    for (const rule of rules) {
      if (rule.media) {
        assert.equal(rule.media.mediaText, '(max-width:620px)');
        validate(rule.cssRules);
      } else {
        assert.ok(rule.selectorText, 'no imports, font-face, keyframes or unknown at-rules');
        for (const selector of rule.selectorText.split(',')) {
          assert.ok(selector.trim().startsWith(SCOPE), 'no changed global/nonfooter CSS: ' + selector);
        }
      }
    }
  }
  assert.equal(style.sheet.cssRules.length, 18, 'seventeen base rules plus one footer media block');
  validate(style.sheet.cssRules);
});

test('unchanged real dictionary/runtime reversibly translate every eligible footer node and label', t => {
  const f = setup(t);
  const footer = f.d.querySelector(SCOPE);
  const before = footer.outerHTML;
  const nodes = [];
  const walker = f.d.createTreeWalker(footer, f.w.NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) nodes.push([walker.currentNode, walker.currentNode.data]);
  const labels = [...footer.querySelectorAll('[aria-label]')].map(el => [el, el.getAttribute('aria-label')]);
  const anchors = [...footer.querySelectorAll('a')].map(a => [a, a.getAttribute('href')]);
  const neutral = new Set(['Harmat Lak\u00f3park', '1105 Budapest, Harmat utca 22.', '+36300733375', 'ertekesites@harmat22.hu']);
  for (const [, text] of nodes) if (!neutral.has(text)) assert.ok(dictionary[text], 'existing key: ' + text);
  const tr = f.translator();
  for (let i = 0; i < 12; i++) {
    tr.setLanguage('zh-Hans');
    for (const [node, text] of nodes) assert.equal(node.data, dictionary[text] || text);
    for (const [el, label] of labels) assert.equal(el.getAttribute('aria-label'), dictionary[label]);
    for (const [a, href] of anchors) assert.equal(a.getAttribute('href'), href);
    assert.equal(footer.querySelectorAll('form,input,button,select,textarea,img,video,script').length, 0);
    tr.setLanguage('hu');
    assert.equal(footer.outerHTML, before, 'exact HU markup and same nodes restored');
  }
  tr.setLanguage('zh-Hans'); tr.destroy();
  assert.equal(footer.outerHTML, before);
  assert.equal(f.d.documentElement.lang, 'hu');
});

test('palette, focus, typography and link importance are footer-only parsed declarations', t => {
  const f = setup(t), rules = footerRules(f.d, 1280);
  const footer = declarations(rules, f.d.querySelector(SCOPE));
  assert.equal(footer.background, '#193e34');
  assert.equal(footer['border-top'], '2px solid #c6a66a');
  assert.equal(footer.color, '#f4f7f5');
  assert.equal(footer['font-family'], 'Montserrat,Arial,sans-serif');
  for (const a of f.d.querySelectorAll(SCOPE + ' a')) {
    const style = declarations(rules, a);
    assert.equal(style.color, '#f4f7f5');
    assert.ok([15, 16].includes(parseFloat(style['font-size'])));
    assert.ok(Number(style['line-height']) >= 1.5);
    assert.equal(style['letter-spacing'], '0');
    assert.equal(style['overflow-wrap'], 'anywhere');
  }
  const baseLink = rules.find(rule => rule.selectorText === SCOPE + ' a');
  assert.equal(baseLink.style.getPropertyPriority('color'), 'important');
  assert.ok(rules.some(rule => rule.selectorText === SCOPE + ' a:focus-visible' && rule.style.outline));
  for (const rule of rules) {
    if (rule.style.cssText.includes('#c6a66a')) assert.ok([SCOPE, SCOPE + ' h2'].includes(rule.selectorText));
    assert.doesNotMatch(rule.style.cssText, /#fff7e8|#987033|url\(|@import/i);
    if (rule.style['letter-spacing']) assert.equal(rule.style['letter-spacing'], '0');
  }
});

for (const width of [320, 390, 620, 621, 768, 1024, 1280, 1920]) {
  test('CSSOM-only responsive sizing contracts at ' + width + 'px (not visual layout)', t => {
    const f = setup(t), rules = footerRules(f.d, width);
    const grid = declarations(rules, f.d.querySelector('.harmat-clean-footer-inner'));
    const contact = declarations(rules, f.d.querySelector('.harmat-clean-footer-contact'));
    const legal = declarations(rules, f.d.querySelector('.harmat-clean-footer-bottom nav'));
    const bottom = declarations(rules, f.d.querySelector('.harmat-clean-footer-bottom'));
    const columns = width <= 620 ? 2 : 3;
    assert.equal(grid['grid-template-columns'], 'repeat(' + columns + ',minmax(0,1fr))');
    assert.equal(grid['max-width'], '1180px');
    assert.equal(grid['box-sizing'], 'border-box');
    const pad = padding(grid.padding);
    const gap = parseFloat(grid.gap.split(/\s+/).at(-1));
    const inner = Math.min(width, parseFloat(grid['max-width']));
    const track = (inner - pad[1] - pad[3] - gap * (columns - 1)) / columns;
    assert.ok(track >= 130 && track < inner, 'bounded positive zero-min tracks fit available width');
    assert.equal(contact['grid-column'], width <= 620 ? '1 / -1' : undefined);
    assert.equal(contact['min-width'], '0');
    assert.equal(legal.display, 'flex');
    assert.equal(legal['flex-wrap'], 'wrap');
    assert.equal(bottom['max-width'], '1180px');
    assert.equal(bottom['box-sizing'], 'border-box');
    assert.equal(bottom.padding, width <= 620 ? '16px 16px 76px' : '18px 24px 76px');
    assert.equal(padding(bottom.padding)[2], 76, 'copyright clears existing fixed cookie-settings control');
    for (const a of f.d.querySelectorAll('.harmat-clean-footer-bottom a')) {
      assert.equal(declarations(rules, a)['max-width'], '100%');
    }
  });
}

test('source hash and EOL report for parent transport (no file/network writes)', () => {
  console.log(JSON.stringify({ candidate, rawSHA256: sha(raw), normalizedLFSHA256: sha(normalize(source)),
    CRLF: (source.match(/\r\n/g) || []).length, bareLF: (source.match(/(?<!\r)\n/g) || []).length,
    bareCR: (source.match(/\r(?!\n)/g) || []).length,
    nonownedNormalizedSHA256: sha(ownedMask(normalize(source))) }));
});
