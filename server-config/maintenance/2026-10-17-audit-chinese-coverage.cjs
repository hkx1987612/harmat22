'use strict';
// Offline presentation audit. Only the two owned translator scripts are evaluated.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const ROOT = path.resolve(__dirname, '../..');
const OUTPUT_ROOT = path.join(ROOT, 'outputs/2026-10-17-chinese-audit');
const DEFAULT_MANIFEST = path.join(ROOT, 'outputs/2026-10-17-internal-language/manifest.json');
const OWNED = path.join(ROOT, 'wp-mu-plugins/harmat-internal-language');
const { JSDOM, VirtualConsole } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const TRANSLATED_DESCRIPTIONS = ['alt', 'title', 'aria-label', 'placeholder'];
const DESCRIPTIONS = [...TRANSLATED_DESCRIPTIONS, 'aria-description', 'aria-valuetext'];
const EXCLUDED = 'script,style,noscript,template,svg,canvas,code,pre,output,head,input,textarea,' +
  '#wpadminbar,#harmat-internal-language,.fb-customerchat,.fb_dialog,#fb-root,' +
  '[data-customer-chat],[data-customerchat],[data-harmat-customer-chat],' +
  '#harmat-local-ai-panel,.harmat-local-ai-launch,[class^="harmat-local-ai-"],' +
  '#harmat-ai-assistant,#harmat-ai-panel,[data-harmat-ai-chat],#harmat22-ai-widget,' +
  '#harmat22-ai-root,#harmat22-ai-widget-root,[data-harmat-local-ai-body],' +
  '[contenteditable]:not([contenteditable="false"])';
const PROTECTED = '[translate="no"],.notranslate,[data-language-protect]';
const DEFERRED = '[role="dialog"],dialog,[aria-live],.mfp-hide,[id*="modal"],[id*="popup"],' +
  '[id*="toast"],[data-h22-status],.screen-reader-text,.sr-only';
const KNOWN_NAMES = [
  'Harmat Lak\u00f3park 22', 'Harmat Lakopark 22', 'Harmat Lak\u00f3park', 'Harmat Lakopark',
  'Harmat utca 22.', 'Harmat utca 22', '1105 Budapest', 'Budapest',
  'Cooperation Power Kft.', 'Cooperation Power Kft', 'Avant-Garde & MODUM', 'Value4Real Group',
  '1107 Budapest, M\u00e1zsa utca 13. 6. em. 603. ajt\u00f3, Magyarorsz\u00e1g',
  'Nemzeti Adatv\u00e9delmi \u00e9s Inform\u00e1ci\u00f3szabads\u00e1g Hat\u00f3s\u00e1g',
  '1055 Budapest, Falk Miksa utca 9-11.', '1363 Budapest, Pf. 9.',
  'Szabados Attila EV.', 'Ybl', 'Pro Architectura', 'EU-USA Data Privacy Framework',
  'Google Ireland Limited', 'Google Analytics', 'Google Ads', 'Google Tag Manager', 'Google Site Kit', 'Google Maps',
  'Meta Platforms Ireland Ltd.', 'T\u00e1rhely.Eu Szolg\u00e1ltat\u00f3 Kft.', 'K\u0151b\u00e1nya',
  '\u00d3hegy park', 'K\u0151b\u00e1nya als\u00f3', 'K\u0151b\u00e1nya-Kispest', '\u00c1RK\u00c1D Budapest', '\u00c1RK\u00c1D',
  'Avant-Garde \u00c9p\u00edt\u00e9sz St\u00fadi\u00f3', 'MODUM \u00c9p\u00edt\u00e9sziroda Kft.', 'Bajcsy-Zsilinszky K\u00f3rh\u00e1z',
  'Budapesti B\u00e9k\u00e9ltet\u0151 Test\u00fclet', 'F\u0151v\u00e1rosi T\u00f6rv\u00e9nysz\u00e9k C\u00e9gb\u00edr\u00f3s\u00e1ga',
  'Gyermek K\u00f6zleked\u00e9si Park', 'K\u0151b\u00e1nyai Harmat \u00c1ltal\u00e1nos Iskola', 'K\u0151b\u00e1nyai Szent L\u00e1szl\u00f3 Gimn\u00e1zium',
  'K\u0151b\u00e1nyai Polg\u00e1rmesteri Hivatal', 'K\u00d6KI Termin\u00e1l', 'K\u00d6KI', '\u00d6rs vez\u00e9r tere',
  'Semmelweis Egyetem Nagyv\u00e1rad t\u00e9r', 'Sug\u00e1r \u00dczletk\u00f6zpont', 'Szent L\u00e1szl\u00f3 t\u00e9r',
  'Cs\u0151sztorony', '\u00d3hegy', '\u00dajhegy', 'Sportliget', 'Harmat utca', 'Globetrotter19', 'Wikimedia Commons',
  'Prof. Dr. Kert\u00e9sz Andr\u00e1s Tibor DLA', 'Krizs\u00e1n Andr\u00e1s DLA', 'K\u00f3s K\u00e1roly',
  'Normafa', 'Yacht Club, Balatonf\u00fcred', 'F\u00f3t', 'Milestone Di\u00e1ksz\u00e1ll\u00f3', 'Bolzano',
  'Sarepta lak\u00f3otthon', 'Z\u00e1nkai \u00daj Nemzed\u00e9k K\u00f6zpont', 'Kast\u00e9lydombi Luther k\u00e1polna',
  'Piliscsabai evang\u00e9likus templom', 'NKE Ludovika Campus', 'Budafoki Piac', 'Reitter99', 'Nagy Lajos 210\u2013216',
  '1016 Budapest, Krisztina krt. 99. I. em. 111.', '1253 Budapest, Pf. 10.', '1122 Budapest, V\u00e1rosmajor utca 35.',
  'Nemzeti Kereskedelmi \u00e9s Fogyaszt\u00f3v\u00e9delmi Hat\u00f3s\u00e1g',
  'Consent Mode', 'Google tag', 'SMTP2GO', 'Meta', 'ingatlan.com', 'Google', 'Facebook', 'Instagram',
  'YouTube', 'LinkedIn', 'WhatsApp', 'WordPress', 'Cookiebot', 'Harmat'
];
const TECHNICAL_TERMS = ['Cookie', 'Cookies', 'GDPR', 'CRM', 'HTTPS', 'IP', 'AI', 'CDN', 'CSOK', 'BKV', 'H\u00c9V', 'Design&Build'];
const EXEMPT_CATEGORIES = ['no-latin', 'url-email', 'code-unit', 'legitimate-name', 'legitimate-term'];
// Independent browser evidence supplied by the parent/user, not observed by jsdom.
const BROWSER_FINDINGS = [
  'Virtu\u00e1lis v\u00e1laszt\u00f3', 'Tervezett \u00e1tad\u00e1s', 'Energetikai besorol\u00e1s',
  '24 \u00f3r\u00e1s z\u00e1rt lak\u00f3park', 'H\u0151szivatty\u00fas f\u0171t\u00e9s-h\u0171t\u00e9s',
  'Alapter\u00fclet az els\u0151 \u00fctemben', 'Lak\u00e1s az els\u0151 \u00fctemben',
  'M\u00e9lygar\u00e1zs parkol\u00f3', 'Interakt\u00edv bemutat\u00f3', 'Panor\u00e1m\u00e1s l\u00e1tv\u00e1nyt\u00e9r',
  'H\u00fazza el a k\u00e9pet, \u00e9s n\u00e9zze k\u00f6rbe a projekt t\u00e9rbeli bemutat\u00f3j\u00e1t.',
  'Panor\u00e1ma', 'Vide\u00f3k', 'T\u00e1j\u00e9koztat\u00f3', 'Sz\u00fcnet', 'Teljes k\u00e9perny\u0151', 'Teljes gal\u00e9ria'
];
const BROWSER_CASES = [...BROWSER_FINDINGS.map(text => ({ text, route: '/', routePrefix: false, context: 'homepage' })),
  { text: 'M\u0171szaki PDF', route: '/property/', routePrefix: true, context: 'property details' },
  { text: 'Fontos t\u00e1j\u00e9koztat\u00e1s:', route: '/property/', routePrefix: true, context: 'property legal note; parent reports late dynamic insertion' },
  { text: 'Az \u00e1rak kiz\u00e1r\u00f3lag t\u00e1j\u00e9koztat\u00f3 jelleg\u0171ek, a v\u00e9gleges \u00e1r \u00e9s fizet\u00e9si felt\u00e9telek a szerz\u0151d\u00e9sben ker\u00fclnek r\u00f6gz\u00edt\u00e9sre. A l\u00e1tv\u00e1nytervek, vide\u00f3k \u00e9s k\u00e9pek illusztr\u00e1ci\u00f3k. Az alapter\u00fcletekben, terasz- \u00e9s kertadatokban m\u0171szaki vagy m\u00e9r\u00e9si elt\u00e9r\u00e9s el\u0151fordulhat.',
    route: '/property/', routePrefix: true, context: 'price/rendering legal note; parent reports late dynamic insertion' },
  { text: 'UTEM-1', route: '/virtualis-lakasvalaszto/', routePrefix: false,
    context: 'virtual overview badge; main label meaning first phase requires independent confirmation, not a confirmed translation bug' }
];
const hash = value => crypto.createHash('sha256').update(value).digest('hex');
const normalize = value => value.normalize('NFC').replace(/\s+/gu, ' ').trim();
const fold = value => normalize(value).toLocaleLowerCase('hu');
const latin = value => /\p{Script=Latin}/u.test(value);
const han = value => /\p{Script=Han}/u.test(value);
const escapeRE = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const within = (parent, child) => {
  const relative = path.relative(parent, child);
  return !path.isAbsolute(relative) && relative !== '..' && !relative.startsWith('..' + path.sep);
};

function argumentsFor(argv) {
  const result = { manifest: DEFAULT_MANIFEST, out: path.join(OUTPUT_ROOT, 'old-debug'), limit: null, selfTest: false };
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === '--self-test') result.selfTest = true;
    else if (argv[i] === '--help') result.help = true;
    else if (['--manifest', '--out', '--limit'].includes(argv[i])) {
      const key = argv[i].slice(2), value = argv[++i];
      if (!value || value.startsWith('--')) throw new Error('Missing value for --' + key);
      result[key] = key === 'limit' ? Number(value) : path.resolve(value);
    } else throw new Error('Unknown argument: ' + argv[i]);
  }
  if (result.limit !== null && (!Number.isInteger(result.limit) || result.limit < 1 || result.limit > 145)) {
    throw new Error('--limit must be an integer from 1 to 145');
  }
  if (!within(OUTPUT_ROOT, result.out) || result.out === OUTPUT_ROOT) {
    throw new Error('--out must be a report directory beneath ' + OUTPUT_ROOT);
  }
  if (within(path.join(OUTPUT_ROOT, 'fresh'), result.out) || within(result.out, result.manifest)) {
    throw new Error('Reports must not overlap the fresh snapshot directory or manifest input');
  }
  return result;
}

const CLASSIFICATION_CACHE = new Map();
function classify(value) {
  const text = normalize(value);
  if (!CLASSIFICATION_CACHE.has(text)) CLASSIFICATION_CACHE.set(text, classifyText(text));
  return CLASSIFICATION_CACHE.get(text);
}

function classifyText(text) {
  if (!latin(text)) return { category: 'no-latin', residual: '' };
  if (/^[\d\s.,+\-]+\s*(?:m2|m\u00b2|m\u00b3|m|km|cm|mm|Ft|HUF|EUR|kW|kWh)(?:\s*\/\s*m(?:2|\u00b2)?)?$/i.test(text) || /^A[1-9][0-9]*(?:-[A-Z0-9]+)+$/i.test(text)) {
    return { category: 'code-unit', residual: '' };
  }
  if (/^(?:https?:\/\/|www\.|mailto:|tel:|\/|#)/i.test(text) ||
      /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text) ||
      /^(?:[\w-]+\.)+(?:hu|com|org|net|eu)(?:\/\S*)?$/i.test(text)) {
    return { category: 'url-email', residual: '' };
  }
  if (/^[^\s<>]+\.(?:png|jpe?g|gif|webp|svg|pdf|mp4|css|js)$/i.test(text) ||
      /^[\p{L}\d]*_[\p{L}\d_*.-]+(?:\s*[,\u3001]\s*[\p{L}\d]*_[\p{L}\d_*.-]+)*$/u.test(text)) {
    return { category: 'code-unit', residual: '' };
  }
  const names = [];
  let rest = text;
  for (const name of [...KNOWN_NAMES].sort((a, b) => b.length - a.length)) {
    const re = new RegExp('(?<![\\p{L}\\p{N}])' + escapeRE(name) + '(?![\\p{L}\\p{N}])', 'giu');
    if (re.test(rest)) { names.push(name); rest = rest.replace(re, ' '); }
  }
  let technical = false;
  for (const term of TECHNICAL_TERMS) {
    const re = new RegExp('(?<![\\p{L}\\p{N}])' + escapeRE(term) + '(?![\\p{L}\\p{N}])', 'giu');
    if (re.test(rest)) { technical = true; rest = rest.replace(re, ' '); }
  }
  rest = rest.replace(/\bA[1-9][0-9]*(?:-[A-Z0-9]+-L[0-9]+)?\b/gi, ' ')
    .replace(/(?<!\p{Script=Latin})(?:Ft|HUF|EUR|m2|m\u00b2|m\u00b3|m|kWh|kW|W|Hz|MHz|GHz|cm|mm|km|kg|CO2|MB|GB|PDF|JPG|JPEG|PNG|MP4|HTML|CSS)(?!\p{Script=Latin})/giu, ' ')
    .replace(/\bM[1-4](?!\p{Script=Latin})/gu, ' ')
    .replace(/\beladolakas_[A-Za-z0-9]+\b/g, ' ')
    .replace(/\bAW-[0-9]+\b/g, ' ')
    .replace(/(?<![\p{L}\p{N}])[\p{L}\d]*_[\p{L}\d_*.-]+/gu, ' ')
    .replace(/\b(?:[a-z0-9-]+\.)+(?:hu|com|org|net|eu)\b/gi, ' ')
    .replace(/https?:\/\/[^\s<>]+|[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+/gi, ' ');
  if (han(text)) rest = rest.replace(/(?<!\p{Script=Latin})[a-z](?=\))/gu, ' ').replace(/(?<!\p{Script=Latin})X(?=\u533a)/gu, ' ');
  if (!latin(rest)) return { category: names.length ? 'legitimate-name' : technical ? 'legitimate-term' : 'code-unit', residual: '', names };
  if (/^[A-G]{1,2}\+{0,3}$/.test(text)) {
    return { category: 'code-unit', residual: '', names };
  }
  const words = rest.match(/\p{Script=Latin}+/gu) || [];
  const isUnknownName = !han(text) && words.length <= 4 &&
    words.every(word => /^[\p{Lu}]/u.test(word)) &&
    !/[\u00e1\u00e9\u00ed\u00f3\u00f6\u0151\u00fa\u00fc\u0171]/i.test(rest);
  return { category: isUnknownName ? 'unknown-name-candidate' : (han(text) ? 'mixed-chinese-residual' : 'likely-missed'),
    residual: normalize(rest), names };
}

function selector(element) {
  const parts = [];
  for (let node = element; node && node.nodeType === 1 && parts.length < 6; node = node.parentElement) {
    const tag = node.localName;
    if (node.id) { parts.unshift(tag + '[id=' + JSON.stringify(node.id) + ']'); break; }
    const siblings = node.parentElement ? [...node.parentElement.children].filter(sibling => sibling.localName === tag) : [];
    parts.unshift(tag + (siblings.length > 1 ? ':nth-of-type(' + (siblings.indexOf(node) + 1) + ')' : ''));
    if (tag === 'body') break;
  }
  return parts.join(' > ');
}

function visibility(element) {
  const reasons = [];
  for (let node = element; node; node = node.parentElement) {
    if (node.hasAttribute('hidden')) reasons.push('hidden-attribute');
    if (node.hasAttribute('inert')) reasons.push('inert-attribute');
    if (node.getAttribute('aria-hidden') === 'true') reasons.push('aria-hidden-not-visual-proof');
    if (node.style.display === 'none') reasons.push('inline-display-none');
    if (['hidden', 'collapse'].includes(node.style.visibility)) reasons.push('inline-visibility-hidden');
    if (node.style.opacity === '0') reasons.push('inline-opacity-zero');
    if (node.localName === 'dialog' && !node.hasAttribute('open')) reasons.push('closed-dialog');
    if (node.localName === 'details' && !node.hasAttribute('open') && !element.closest('summary')) reasons.push('closed-details');
  }
  const deferred = element.closest(DEFERRED);
  const explicitHidden = reasons.some(reason => reason !== 'aria-hidden-not-visual-proof' && reason !== 'inert-attribute');
  return { state: explicitHidden ? 'explicitly-hidden-source' : (deferred ? 'deferred-or-accessibility-ui' : 'not-proven-visible'),
    reasons: [...new Set(reasons)], deferred: deferred ? selector(deferred) : null,
    cssVisibility: 'unavailable-in-jsdom; external CSS, layout, viewport and actual rendering not evaluated' };
}

function descriptionsAllowed(element) {
  if (element.matches('input[type="hidden"],input[type="password"]')) return false;
  const anchor = element.matches('input,textarea') ? element.parentElement : element;
  return anchor && !anchor.closest(EXCLUDED);
}

function inventory(document) {
  const records = [], excluded = {};
  const count = reason => { excluded[reason] = (excluded[reason] || 0) + 1; };
  const walker = document.createTreeWalker(document.body, document.defaultView.NodeFilter.SHOW_TEXT);
  for (let node; (node = walker.nextNode());) {
    if (!normalize(node.data)) continue;
    if (node.parentElement.closest(EXCLUDED)) { count('excluded-text-node'); continue; }
    records.push({ node, element: node.parentElement, kind: 'text', original: node.data });
  }
  for (const element of document.body.querySelectorAll(DESCRIPTIONS.map(name => '[' + name + ']').join(','))) {
    if (!descriptionsAllowed(element)) { count('excluded-descriptive-element'); continue; }
    for (const kind of DESCRIPTIONS) {
      const original = element.getAttribute(kind);
      if (original && normalize(original)) records.push({ node: element, element, kind, original });
    }
  }
  return { records, excluded };
}

function allNodes(document) {
  const result = [], walker = document.createTreeWalker(document, 0xFFFFFFFF);
  for (let node; (node = walker.nextNode());) result.push(node);
  return result;
}

function nativeState(document) {
  const elements = [...document.querySelectorAll('*')].filter(element => !element.closest('#harmat-internal-language'));
  return JSON.stringify(elements.map(element => {
    const attributes = [...element.attributes].filter(attribute => !TRANSLATED_DESCRIPTIONS.includes(attribute.name) &&
      !(element === document.documentElement && ['lang', 'data-harmat-language'].includes(attribute.name)))
      .map(attribute => [attribute.name, attribute.value]);
    const field = element.matches('input,textarea,select,option') ? {
      value: element.value, name: element.name, checked: element.checked,
      selected: element.selected, selectedIndex: element.selectedIndex,
      selectionStart: element.selectionStart, selectionEnd: element.selectionEnd, selectionDirection: element.selectionDirection,
      textareaText: element.matches('textarea') ? element.textContent : undefined
    } : null;
    return [element.localName, attributes, field];
  }));
}

function noNetworkDOM(html, url) {
  const blockedAttempts = [], diagnostics = [];
  const console = new VirtualConsole();
  console.on('jsdomError', error => { if (diagnostics.length < 5) diagnostics.push(String(error.message).slice(0, 200)); });
  // No resources option: jsdom never loads external scripts, styles, frames or images.
  const dom = new JSDOM(html, { url, runScripts: 'outside-only', virtualConsole: console });
  const stop = name => () => { blockedAttempts.push(name); throw new Error('OFFLINE_AUDIT_BLOCKED_' + name); };
  const w = dom.window;
  for (const name of ['fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource', 'Worker', 'SharedWorker', 'open']) w[name] = stop(name);
  Object.defineProperty(w.navigator, 'sendBeacon', { value: stop('sendBeacon'), configurable: true });
  for (const name of ['submit', 'requestSubmit']) w.HTMLFormElement.prototype[name] = stop('form.' + name);
  return { dom, blockedAttempts, diagnostics };
}

function analyzeHTML(html, url, sources) {
  const { dom, blockedAttempts, diagnostics } = noNetworkDOM(html, url);
  let translator;
  try {
    const w = dom.window, d = w.document;
    const parsedOriginal = d.documentElement.outerHTML, sourceNodes = allNodes(d);
    const parsedOriginalLang = d.documentElement.getAttribute('lang');
    const hadControl = !!d.getElementById('harmat-internal-language');
    const originalNative = nativeState(d);
    w.eval(sources.patterns);
    w.eval(sources.runtime);
    translator = w.HarmatInternalLanguage.create(d, sources.dictionary,
      { enabled: true, storage: false, translateDynamic: w.HarmatLanguagePatterns.translate });
    const huHTML = d.documentElement.outerHTML, huNodes = allNodes(d);
    const collected = inventory(d), stateHU = nativeState(d);
    translator.setLanguage('zh-Hans');
    const chineseState = nativeState(d);
    const coverage = { text: { eligible: 0, changed: 0, hanOutput: 0, unchanged: 0, protected: 0 },
      attributes: Object.fromEntries(DESCRIPTIONS.map(name => [name, { eligible: 0, changed: 0, hanOutput: 0, unchanged: 0, protected: 0 }])) };
    const occurrences = [], observed = new Map();
    for (const record of collected.records) {
      const current = record.kind === 'text' ? record.node.data : record.node.getAttribute(record.kind);
      const protectedNode = record.element.closest(PROTECTED) || record.element.closest('option:not([value])');
      const originalClass = classify(record.original), currentClass = classify(current || '');
      const sourceKey = fold(record.original);
      if (BROWSER_CASES.some(item => item.text && fold(item.text) === sourceKey &&
        (item.routePrefix ? new URL(url).pathname.startsWith(item.route) : new URL(url).pathname === item.route))) {
        const prior = observed.get(sourceKey) || { sourceOccurrences: 0, leftoverOccurrences: 0 };
        prior.sourceOccurrences++;
        if (fold(current || '') === sourceKey) prior.leftoverOccurrences++;
        observed.set(sourceKey, prior);
      }
      const metrics = record.kind === 'text' ? coverage.text : coverage.attributes[record.kind];
      if (latin(record.original) && !EXEMPT_CATEGORIES.includes(originalClass.category)) {
        if (protectedNode) metrics.protected++;
        else {
          metrics.eligible++;
          if (current !== record.original) { metrics.changed++; if (han(current || '')) metrics.hanOutput++; }
          else metrics.unchanged++;
        }
      }
      if (!latin(current || '')) continue;
      if (currentClass.category === 'url-email' || currentClass.category === 'code-unit') {
        collected.excluded[currentClass.category] = (collected.excluded[currentClass.category] || 0) + 1;
        continue;
      }
      const presentation = visibility(record.element);
      const category = protectedNode ? 'translate-protected' : currentClass.category;
      occurrences.push({ category, lexicalCategory: currentClass.category, text: normalize(current), source: normalize(record.original),
        residual: currentClass.residual, names: currentClass.names || [], changed: current !== record.original,
        kind: record.kind, route: new URL(url).pathname, url, selector: selector(record.element),
        textNodeIndex: record.kind === 'text' ? [...record.element.childNodes].indexOf(record.node) : null,
        snippet: normalize(record.original).slice(0, 240), visibility: presentation,
        protectedBy: protectedNode ? selector(protectedNode) : null,
        dictionaryExactKey: Object.hasOwn(sources.dictionary, record.original.trim()),
        futureReview: !EXEMPT_CATEGORIES.includes(category) && category !== 'translate-protected' ?
          'Unknown or future copy requires human review; not automatically a bug' : null });
    }
    const zhNodes = allNodes(d);
    translator.setLanguage('hu');
    const restoredHTML = d.documentElement.outerHTML, restoredNodes = allNodes(d), restoredState = nativeState(d);
    const same = (a, b) => a.length === b.length && a.every((node, index) => node === b[index]);
    const checks = {
      sourceToInitialHUProtectedIdentity: originalNative === stateHU,
      zhNativeValuesNamesDataHrefsAndSelectionsUnchanged: stateHU === chineseState,
      huNativeValuesNamesDataHrefsAndSelectionsUnchanged: stateHU === restoredState,
      zhAllNodeIdentity: same(huNodes, zhNodes), huAllNodeIdentity: same(huNodes, restoredNodes),
      huZhHuExactSerializedDOM: huHTML === restoredHTML,
      textareaValuesAndTextPreserved: true,
      blockedNetworkOrSubmitAttempts: blockedAttempts.length
    };
    // nativeState covers textarea live/default values and text; expose a specific assertion too.
    checks.textareaValuesAndTextPreserved = checks.zhNativeValuesNamesDataHrefsAndSelectionsUnchanged &&
      checks.huNativeValuesNamesDataHrefsAndSelectionsUnchanged;
    translator.destroy(); translator = null;
    checks.destroyExactParsedOriginalDOM = parsedOriginal === d.documentElement.outerHTML;
    checks.destroyOriginalNodeIdentity = same(sourceNodes, allNodes(d));
    return { occurrences, observed: Object.fromEntries(observed), page: { url, hadLanguageControlInSnapshot: hadControl,
      parsedOriginalLang,
      coverage, excluded: collected.excluded, checks,
      protectedStateSHA256: { initialHU: hash(stateHU), chinese: hash(chineseState), restoredHU: hash(restoredState) },
      roundtripSHA256: { initialHU: hash(huHTML), restoredHU: hash(restoredHTML) },
      diagnostics, status: Object.entries(checks).every(([key, value]) => key === 'blockedNetworkOrSubmitAttempts' ? value === 0 : value) ? 'PASS' : 'FAIL' } };
  } finally {
    if (translator) translator.destroy();
    dom.window.close();
  }
}

function readInputs(args) {
  const blob = fs.readFileSync(args.manifest), parsed = JSON.parse(blob.toString('utf8').replace(/^\uFEFF/, ''));
  const rows = Array.isArray(parsed) ? parsed : (parsed.pages || parsed.snapshots || parsed.entries || parsed.urls);
  if (!Array.isArray(rows) || !rows.length || rows.length > 145) throw new Error('Manifest must contain 1..145 snapshot entries');
  const seen = new Set(), directory = path.dirname(args.manifest);
  const selected = rows.slice(0, args.limit || rows.length).map(row => {
    if (!row || typeof row !== 'object' || typeof row.url !== 'string') throw new Error('Each manifest entry needs url and file');
    const url = new URL(row.url);
    if (url.origin !== 'https://harmat22.hu' || url.username || url.password || url.hash) throw new Error('Noncanonical same-origin URL');
    if (seen.has(row.url)) throw new Error('Duplicate URL in manifest');
    seen.add(row.url);
    const file = row.file || row.path || row.html_file;
    if (typeof file !== 'string' || !file.endsWith('.html')) throw new Error('Each snapshot needs a local .html file');
    const full = path.resolve(directory, file);
    if (!within(directory, full) || !within(fs.realpathSync(directory), fs.realpathSync(full))) throw new Error('Snapshot escapes manifest directory');
    return { ...row, full };
  });
  const stat = fs.statSync(args.manifest);
  return { rows: selected, provenance: { manifest: args.manifest, manifestSHA256: hash(blob),
    manifestMtime: stat.mtime.toISOString(), manifestEntries: rows.length, selectedEntries: selected.length,
    limitedDebugRun: selected.length !== rows.length,
    inputKind: path.resolve(args.manifest) === DEFAULT_MANIFEST ? 'OLD_DEBUG_SNAPSHOTS_NOT_FRESH' :
      (within(path.join(OUTPUT_ROOT, 'fresh'), args.manifest) ? 'PARENT_FRESH_SNAPSHOT_INPUT' : 'EXPLICIT_LOCAL_MANIFEST'),
    collectorTimestamp: Array.isArray(parsed) ? null : (parsed.collectedAt || parsed.capturedAt || parsed.generated_at || parsed.created_at || null),
    freshness: 'Input identity/path and file timestamps are recorded; mtime alone does not prove capture time or current live content',
    metadata: Array.isArray(parsed) ? null : Object.fromEntries(Object.entries(parsed).filter(([key]) => !['pages','snapshots','entries','urls'].includes(key))) } };
}

function summarize(pages, groups, browserEvidence) {
  const categories = {}, kinds = {}, visibilityStates = {};
  for (const group of groups) {
    categories[group.category] = (categories[group.category] || 0) + group.occurrences;
    for (const [kind, count] of Object.entries(group.kinds)) kinds[kind] = (kinds[kind] || 0) + count;
    for (const [state, count] of Object.entries(group.visibilityStates)) visibilityStates[state] = (visibilityStates[state] || 0) + count;
  }
  const totals = { eligible: 0, changed: 0, hanOutput: 0, unchanged: 0, protected: 0 };
  const attributeCoverage = Object.fromEntries(DESCRIPTIONS.map(name => [name, { ...totals }]));
  for (const page of pages) if (page.coverage) {
    for (const key of Object.keys(totals)) totals[key] += page.coverage.text[key];
    for (const name of DESCRIPTIONS) for (const key of Object.keys(totals)) attributeCoverage[name][key] += page.coverage.attributes[name][key];
  }
  const likely = groups.filter(group => !EXEMPT_CATEGORIES.includes(group.category) && group.category !== 'translate-protected');
  likely.sort((a, b) => b.reviewPriority - a.reviewPriority || b.routes.length - a.routes.length || b.occurrences - a.occurrences || a.text.localeCompare(b.text));
  return { pagesAudited: pages.length, invariantPassPages: pages.filter(page => page.status === 'PASS').length,
    errorOrInvariantFailPages: pages.filter(page => page.status !== 'PASS').length, textNodeCoverage: totals,
    descriptiveAttributeCoverage: attributeCoverage, leftoverOccurrencesByCategory: categories,
    leftoverOccurrencesByKind: kinds, leftoverOccurrencesBySourcePresentation: visibilityStates,
    distinctLeftoverGroups: groups.length, candidateGroupsRequiringReview: likely.length,
    independentlyReportedBrowserMisses: browserEvidence.length,
    top50LikelyMissedPhrases: likely.slice(0, 50),
    coverageMeaning: 'Eligible means lexical Latin prose candidates, not rendered visibility. Changed/Han output is a mechanical coverage signal, not translation quality or whole-site completeness.' };
}

function markdown(report) {
  const s = report.summary, safe = text => String(text).replace(/[|\r\n]/g, ' ').slice(0, 220);
  const lines = ['# Offline Chinese Coverage Audit', '',
    '- Input: `' + report.input.manifest + '`', '- Input classification: ' + report.input.inputKind,
    '- Selected/manifest pages: ' + s.pagesAudited + '/' + report.input.manifestEntries,
    '- Invariant pass/error pages: ' + s.invariantPassPages + '/' + s.errorOrInvariantFailPages,
    '- Eligible source text nodes: ' + s.textNodeCoverage.eligible + '; changed: ' + s.textNodeCoverage.changed +
      '; Han output: ' + s.textNodeCoverage.hanOutput + '; unchanged: ' + s.textNodeCoverage.unchanged,
    '- Candidate groups requiring review: ' + s.candidateGroupsRequiringReview,
    '- CSS visibility is unavailable in jsdom. No leftover is certified visible by this audit.',
    '- Page-owned scripts were not executed. Runtime-inserted content is outside source coverage.',
    '- No HTTP success count is used as Chinese coverage evidence.', '', '## Descriptive Attributes', '',
    '| Kind | Eligible | Changed | Han output | Unchanged | Protected |', '| --- | ---: | ---: | ---: | ---: | ---: |'];
  for (const [kind, counts] of Object.entries(s.descriptiveAttributeCoverage)) lines.push('| ' + kind + ' | ' +
    [counts.eligible, counts.changed, counts.hanOutput, counts.unchanged, counts.protected].join(' | ') + ' |');
  lines.push('', '## Independent Chrome 390 Findings', '',
    'User/parent-reported rendered misses and badge review; not independently observed by this script.', '',
    '| Phrase | Matching source occurrences | Unchanged after owned translator | Source relation |', '| --- | ---: | ---: | --- |');
  for (const row of report.independentBrowserEvidence) lines.push('| ' + safe(row.text || row.context) + ' | ' + row.sourceOccurrences + ' | ' + row.leftoverOccurrences + ' | ' + row.sourceRelation + ' |');
  lines.push('', '## Top 50 Source Candidates', '', '| Phrase | Category | Routes | Occurrences | Presentation evidence |', '| --- | --- | ---: | ---: | --- |');
  for (const group of s.top50LikelyMissedPhrases) lines.push('| ' + safe(group.text) + ' | ' + group.category + ' | ' + group.routes.length + ' | ' + group.occurrences + ' | ' + Object.keys(group.visibilityStates).join(', ') + ' |');
  lines.push('', '## Limitations', '', ...report.limitations.map(text => '- ' + text), '');
  return lines.join('\n');
}

async function main(args) {
  const sources = { runtime: fs.readFileSync(path.join(OWNED, 'runtime.js'), 'utf8'),
    patterns: fs.readFileSync(path.join(OWNED, 'patterns.js'), 'utf8'),
    dictionary: JSON.parse(fs.readFileSync(path.join(OWNED, 'zh-Hans.json'), 'utf8')) };
  if (args.selfTest) return selfTest(sources);
  const input = readInputs(args), groups = new Map(), pages = [], observedByRoute = {};
  const started = new Date().toISOString();
  for (const row of input.rows) {
    try {
      if (fs.statSync(row.full).size > 4 * 1024 * 1024) throw new Error('Snapshot exceeds 4 MiB bound');
      const bytes = fs.readFileSync(row.full), digest = hash(bytes);
      if (row.sha256 && digest !== row.sha256.toLowerCase()) throw new Error('Snapshot SHA256 mismatch');
      const analyzed = analyzeHTML(bytes.toString('utf8'), row.url, sources);
      pages.push({ ...analyzed.page, file: row.full, snapshotSHA256: digest, manifestSHA256Verified: !!row.sha256,
        snapshotMtime: fs.statSync(row.full).mtime.toISOString(), collectorCapturedAt: row.capturedAt || row.captured_at || row.fetched_at || null });
      observedByRoute[new URL(row.url).pathname] = analyzed.observed;
      for (const occurrence of analyzed.occurrences) {
        const key = occurrence.category + '\0' + fold(occurrence.text);
        let group = groups.get(key);
        if (!group) {
          group = { text: occurrence.text, normalizedText: fold(occurrence.text), category: occurrence.category,
            occurrences: 0, routes: new Set(), kinds: {}, visibilityStates: {}, samples: [],
            reviewPriority: 0, futureReview: occurrence.futureReview };
          groups.set(key, group);
        }
        group.occurrences++; group.routes.add(occurrence.route);
        group.kinds[occurrence.kind] = (group.kinds[occurrence.kind] || 0) + 1;
        group.visibilityStates[occurrence.visibility.state] = (group.visibilityStates[occurrence.visibility.state] || 0) + 1;
        const priority = (occurrence.category === 'likely-missed' ? 100 : occurrence.category === 'mixed-chinese-residual' ? 25 : 5) +
          (occurrence.visibility.state === 'not-proven-visible' ? 20 : 0) + (occurrence.kind === 'text' ? 10 : 0);
        group.reviewPriority = Math.max(group.reviewPriority, priority);
        // Bound examples, but retain every affected route and per-kind/presentation totals.
        if (group.samples.length < 8 && !group.samples.some(sample => sample.route === occurrence.route && sample.selector === occurrence.selector && sample.kind === occurrence.kind)) group.samples.push(occurrence);
      }
    } catch (error) {
      pages.push({ url: row.url, file: row.full, status: 'ERROR', error: String(error.message).slice(0, 500) });
    }
    if (pages.length % 25 === 0) process.stdout.write('audited ' + pages.length + '/' + input.rows.length + '\n');
    await new Promise(resolve => setImmediate(resolve));
  }
  const browserEvidence = BROWSER_CASES.map(item => {
    const seen = { sourceOccurrences: 0, leftoverOccurrences: 0 }, affectedSourceRoutes = [];
    for (const [route, observations] of Object.entries(observedByRoute)) {
      if (!(item.routePrefix ? route.startsWith(item.route) : route === item.route)) continue;
      const found = item.text ? observations[fold(item.text)] : null;
      if (found) { seen.sourceOccurrences += found.sourceOccurrences; seen.leftoverOccurrences += found.leftoverOccurrences; affectedSourceRoutes.push(route); }
    }
    const matchingPages = pages.filter(page => page.status !== 'ERROR' &&
      (item.routePrefix ? new URL(page.url).pathname.startsWith(item.route) : new URL(page.url).pathname === item.route));
    return { ...item, affectedSourceRoutes, evidence: 'User/parent-reported visible in actual Chrome at 390px, after Chinese selection',
      independentlyObservedByThisScript: false, ...seen,
      sourceRelation: !item.text ? 'no-verbatim-phrase-supplied; parent-reports-late-dynamic-content' : !matchingPages.length ? 'matching-routes-not-audited' :
        seen.sourceOccurrences ? 'present-in-audited-source; compare-rendered-context' : 'absent-from-audited-text-and-descriptive-attributes; possible-page-JS-insertion',
      notExecutableScriptSearch: 'Excluded script/template bodies are never treated as rendered text' };
  });
  const grouped = [...groups.values()].map(group => ({ ...group, routes: [...group.routes].sort() }));
  const report = { schemaVersion: 1, startedAt: started, finishedAt: new Date().toISOString(),
    scope: 'OFFLINE_SAVED_HTML_WITH_CURRENT_OWNED_TRANSLATOR_NO_NETWORK_NO_SUBMIT_NO_DEPLOY',
    input: input.provenance, evaluatedLocalSources: { runtime: path.join(OWNED, 'runtime.js'), runtimeSHA256: hash(sources.runtime),
      patterns: path.join(OWNED, 'patterns.js'), patternsSHA256: hash(sources.patterns),
      dictionary: path.join(OWNED, 'zh-Hans.json'), dictionarySHA256: hash(fs.readFileSync(path.join(OWNED, 'zh-Hans.json'))),
      dictionaryEntries: Object.keys(sources.dictionary).length },
    policy: { runScripts: 'outside-only', resources: 'default disabled; no external resource loader',
      evaluated: ['owned patterns.js', 'owned runtime.js'], knownNameWhitelist: KNOWN_NAMES, legitimateTechnicalTerms: TECHNICAL_TERMS,
      excludedText: EXCLUDED, separatelyClassifiedProtection: PROTECTED,
      textareaValuesNeverTranslated: true, inputsValuesNeverCollectedAsTranslationCandidates: true,
      unknownNames: 'Candidates for review, not confirmed bugs', sampleLimitPerGroup: 8 },
    summary: summarize(pages, grouped, browserEvidence), independentBrowserEvidence: browserEvidence,
    independentBrowserVerification: { source: 'User/parent report, not this offline script', viewport: 'Chrome 390px and 320px',
      scope: 'All 21 nonproperty pages and 3 property samples at both widths',
      reportedNoIssues: ['page overflow', 'clipped major visible text', 'broken completed images'],
      falsePositivesExcludedByParent: ['inherited sr-only 1px H1', 'blank image hover anchor'],
      appointment320: 'Five native source values, date/time and required unchecked privacy work without send',
      protectedTextareaUX: 'Generated default Hungarian interest message remains Hungarian by protected-input design; optional UX issue, not payload corruption',
      dynamicPropertyCaveat: 'Legal note appeared late; an early A3 sample may miss the common dynamic rule',
      virtualBadgeCaveat: 'UTEM-1 main label meaning first phase needs confirmation; no translator edits performed' },
    pages, groups: grouped.sort((a, b) => b.occurrences - a.occurrences),
    limitations: [
      'No browser, network, server, deployment, native page scripts, form submission, mail or API calls were used.',
      'Source HTML only: page JS insertions, interaction states, CSS-generated text and embedded image/video/PDF text are not covered.',
      'CSS visibility/layout is unavailable in jsdom. Explicit hidden/inline flags and deferred UI are classified, not falsely declared visible.',
      'aria-hidden is accessibility evidence, not proof of visual hiding. Hidden/deferred strings may be legitimate future interaction copy.',
      'Text nodes and alt/title/aria-label/placeholder are audited separately. Native field values, names, data, hrefs, node identity and selections are compared without exposing their values.',
      'HU->ZH->HU exact DOM compares initialized HU DOM; destroy separately checks the original parsed DOM, including absent-control cleanup and original language restoration.',
      'Original HTML bytes are not DOM serialization: parser normalization is not a translation mutation. Snapshot SHA256 records actual input bytes.',
      'Only initial native values/selections are audited; no typing, change events, opening modals or submitting forms. No interaction or delivery success is claimed.',
      'Mechanical changed/Han counts cannot establish semantic quality or complete Chinese coverage. Unknown names/address fragments and split text require human review.',
      'Independent Chrome 390/320 findings are attributed to the user/parent. Source absence may indicate JS insertion or split text; page scripts are intentionally not run.',
      'Snapshots may predate current source/assets; input kind, timestamps and hashes distinguish old debug inputs from parent fresh inputs without fabricating freshness.'
    ] };
  fs.mkdirSync(args.out, { recursive: true });
  fs.writeFileSync(path.join(args.out, 'report.json'), JSON.stringify(report, null, 2) + '\n');
  fs.writeFileSync(path.join(args.out, 'summary.md'), markdown(report));
  process.stdout.write(JSON.stringify({ pages: pages.length, inputKind: report.input.inputKind,
    invariantPassPages: report.summary.invariantPassPages, errorOrInvariantFailPages: report.summary.errorOrInvariantFailPages,
    eligibleTextNodes: report.summary.textNodeCoverage.eligible, changedTextNodes: report.summary.textNodeCoverage.changed,
    candidateGroups: report.summary.candidateGroupsRequiringReview, out: args.out }) + '\n');
  if (report.summary.errorOrInvariantFailPages) process.exitCode = 1;
}

function selfTest(sources) {
  assert.equal(classify('Avant-Garde & MODUM').category, 'legitimate-name');
  assert.equal(classify('Value4Real Group').category, 'legitimate-name');
  assert.equal(classify('47,83 m\u00b2').category, 'code-unit');
  assert.equal(classify('93m2').category, 'code-unit');
  assert.equal(classify('200 m').category, 'code-unit');
  assert.equal(classify('epl_wp_session').category, 'code-unit');
  assert.equal(classify('cropped-Harmat_Logo_250.png').category, 'code-unit');
  assert.equal(classify('Cookie \u8bbe\u7f6e').category, 'legitimate-term');
  assert.equal(classify('A3-4-L5').category, 'code-unit');
  assert.equal(classify('https://harmat22.hu/property/a1-f-l1/').category, 'url-email');
  assert.equal(classify('Unknown Partner').category, 'unknown-name-candidate');
  assert.equal(classify('Teljes gal\u00e9ria').category, 'likely-missed');
  const dictionaryKey = Object.keys(sources.dictionary).find(key => key === 'Kapcsolat');
  assert.ok(dictionaryKey, 'Current dictionary fixture key');
  const html = '<!doctype html><html lang="hu"><body><h1>Kapcsolat</h1>' +
    '<p hidden>Unknown hidden phrase</p><p translate="no">Protected source prose</p>' +
    '<p>Unknown future phrase</p><p>Avant-Garde &amp; MODUM</p>' +
    '<input name="your-name" value="DO NOT TRANSLATE" placeholder="Unknown placeholder">' +
    '<textarea name="message" aria-label="Unknown aria label">Customer message unchanged</textarea>' +
    '<select name="choice"><option value="a" selected>Kapcsolat</option><option>Unvalued option</option></select>' +
    '<a href="/native/" data-value="Kapcsolat" title="Unknown title">Kapcsolat</a>' +
    '<script>throw new Error("PAGE_SCRIPT_MUST_NOT_RUN")</script><svg><text>Excluded SVG prose</text></svg></body></html>';
  const result = analyzeHTML(html, 'https://harmat22.hu/', sources);
  assert.equal(result.page.status, 'PASS');
  assert.equal(result.page.diagnostics.length, 0);
  assert.ok(result.page.coverage.text.changed >= 2);
  assert.ok(result.occurrences.some(row => row.text === 'Unknown hidden phrase' && row.visibility.state === 'explicitly-hidden-source'));
  assert.ok(result.occurrences.some(row => row.text === 'Protected source prose' && row.category === 'translate-protected'));
  assert.ok(result.occurrences.some(row => row.kind === 'placeholder'));
  assert.ok(result.occurrences.some(row => row.kind === 'aria-label'));
  assert.ok(!result.occurrences.some(row => /Customer message unchanged|DO NOT TRANSLATE|Excluded SVG/.test(row.text)));
  process.stdout.write('SELF_TEST_PASS: classification, hidden/protected separation, descriptive attributes, no page JS, exact roundtrip and native identity\n');
}

module.exports = { classify, summarize, markdown, BROWSER_CASES, KNOWN_NAMES, TECHNICAL_TERMS };
if (require.main === module) {
  const args = argumentsFor(process.argv.slice(2));
  if (args.help) process.stdout.write('node server-config/maintenance/2026-10-17-audit-chinese-coverage.cjs [--manifest PATH] [--out DIRECTORY] [--limit 1..145] [--self-test]\n');
  else main(args).catch(error => { process.stderr.write(String(error.stack || error) + '\n'); process.exitCode = 1; });
}
