'use strict';
// Mechanical derivation of the approved preview; no network or deployment.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const ROOT = path.resolve(__dirname, '../..');
const SRC = path.join(ROOT, 'server-config/previews/neighborhood-practical');
const DEST = path.join(ROOT, 'wp-mu-plugins/harmat-neighborhood-practical');
const OUT = path.join(ROOT, 'outputs/2026-10-27-neighborhood-release');
const { JSDOM } = require(path.join(ROOT, 'outputs/2026-10-17-chinese-switch/node_modules/jsdom'));
const read = file => fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');
const sha = blob => crypto.createHash('sha256').update(blob).digest('hex');
const once = (text, old, next) => {
  if (text.split(old).length !== 2) throw new Error('Expected one adapter fragment: ' + old.slice(0, 80));
  return text.replace(old, next);
};
const data = JSON.parse(read(path.join(SRC, 'facilities.json')));
const media = JSON.parse(read(path.join(ROOT, 'outputs/2026-10-18-home-family/interactive-manifest.json')));
const base = 'https://harmat22.hu/wp-content/mu-plugins/';
const assets = Object.fromEntries(Object.entries(media).map(([name, row]) => [name, row.url]));
assets['ohegy-park.jpg'] = base + 'harmat-home-family/ohegy-park-2024-original.jpg';
assets['hero-video.mp4'] = 'https://harmat22.hu/wp-content/uploads/harmat-video/harmat-home-1080p-v2.mp4';
const dom = new JSDOM(read(path.join(SRC, 'index.html')));
const d = dom.window.document;
const root = d.createElement('div');
root.id = 'harmat-neighborhood-practical';
root.lang = 'hu';
root.setAttribute('data-language-protect', '');
root.setAttribute('data-harmat-keyword-intro', 'neighborhood');
root.append(...Array.from(d.querySelector('main').children));
root.append(d.getElementById('lightbox'), d.getElementById('facility-data'));
root.querySelector('#facility-data').textContent = JSON.stringify(data).replace(/</g, '\\u003c');
root.querySelectorAll('[id]').forEach(n => { n.id = 'hn-' + n.id; });
root.querySelectorAll('[aria-labelledby],[aria-controls],[for],[href]').forEach(n => {
  for (const attr of ['aria-labelledby', 'aria-controls', 'for']) {
    if (n.hasAttribute(attr)) n.setAttribute(attr, n.getAttribute(attr).split(' ').map(v => 'hn-' + v).join(' '));
  }
  const href = n.getAttribute('href');
  if (href?.startsWith('#')) n.setAttribute('href', '#hn-' + href.slice(1));
});
root.querySelectorAll('[src],[poster]').forEach(n => {
  for (const attr of ['src', 'poster']) {
    const value = n.getAttribute(attr);
    if (!value) continue;
    const name = value.replace(/^assets\//, '');
    const url = assets[name] || (value === '/media/project.mp4' ? assets['panorama-project-video.mp4']
      : value === '/media/visual.mp4' ? assets['hero-video.mp4'] : null);
    if (!url) throw new Error('No verified existing media URL: ' + value);
    n.setAttribute(attr, url);
  }
});
// Server-render all public facilities so the content remains useful without JS.
const list = root.querySelector('#hn-place-list');
for (const group of data.categories) {
  const section = d.createElement('section'); section.className = 'place-group';
  const h = d.createElement('h3'); h.textContent = group.hu; section.append(h);
  for (const place of data.places.filter(p => p.category === group.id)) {
    const row = d.createElement('article'); row.className = 'place-row'; row.dataset.place = place.id;
    const copy = d.createElement('div');
    const name = d.createElement('h4'); name.className = 'place-name'; name.textContent = place.name;
    const desc = d.createElement('p'); desc.className = 'place-description'; desc.textContent = place.descriptionHu;
    copy.append(name, desc);
    const dist = d.createElement('span'); dist.className = 'place-distance'; dist.textContent = place.distance;
    const link = d.createElement('a'); link.className = 'map-link'; link.target = '_blank'; link.rel = 'noopener noreferrer';
    const query = ['1', '17'].includes(place.id) ? place.name + ', Harmat utca 22, Budapest'
      : place.name.includes('Budapest') ? place.name : place.name + ', Budapest';
    const url = new URL('https://www.google.com/maps/search/'); url.searchParams.set('api', '1'); url.searchParams.set('query', query);
    link.href = url.href; link.setAttribute('aria-label', 'Térkép: ' + place.name);
    const icon = d.createElement('i'); icon.dataset.lucide = 'arrow-up-right'; icon.setAttribute('aria-hidden', 'true'); link.append(icon);
    row.append(copy, dist, link); section.append(row);
  }
  list.append(section);
}
root.querySelector('#hn-result-count').textContent = '17 hely és szolgáltatás';
root.querySelectorAll('[data-lucide]').forEach(n => { n.setAttribute('data-hn-lucide', n.getAttribute('data-lucide')); n.removeAttribute('data-lucide'); });
root.querySelectorAll('a[href^="https://harmat22.hu/"]').forEach(n => { n.removeAttribute('target'); n.removeAttribute('rel'); });

const OWNER = '#harmat-neighborhood-practical';
function scopeCSS(source, vendor = false) {
  const style = d.createElement('style'); style.textContent = source; d.head.append(style);
  if (!style.sheet) throw new Error('CSSOM unavailable');
  function rules(list) {
    return Array.from(list, rule => {
      if (rule.selectorText) {
        // Approved CSS has no functional selector containing a comma.
        if (/[([][^)\]]*,/.test(rule.selectorText)) throw new Error('Complex selector needs explicit review');
        const selectors = rule.selectorText.split(',').map(s => s.trim()).filter(s => vendor || !/(?:review-bar|site-header|header-inner|site-nav|header-controls|language|menu-toggle|\.brand|footer|lightbox-bottom strong)/.test(s));
        const next = selectors.filter(s => s !== 'html').map(s => {
          if (s === 'body') return OWNER;
          s = s.replace(/#([a-z][\w-]*)/gi, (_, id) => '#hn-' + id);
          s = s.replace(/\.hero-copy h1\b/g, '.hero-copy #hn-page-title');
          return OWNER + ' ' + s;
        });
        return next.length ? next.join(',') + '{' + rule.style.cssText + '}\n' : '';
      }
      if (rule.media) return '@media ' + rule.media.mediaText + '{\n' + rules(rule.cssRules) + '}\n';
      if (rule.type === 7) return rule.cssText + '\n';
      throw new Error('Unreviewed CSS rule type ' + rule.type);
    }).join('');
  }
  const result = rules(style.sheet.cssRules); style.remove(); return result;
}
const previewOut = path.join(ROOT, 'outputs/2026-10-27-neighborhood-practical/v1/assets');
const css = scopeCSS(read(path.join(previewOut, 'pannellum.css')), true).replace(/pnlm-mv/g, 'hn-pnlm-mv')
  + scopeCSS(read(path.join(SRC, 'preview.css')))
  + `${OWNER}{display:block;min-width:0;font:16px/1.65 Arial,"Microsoft YaHei",sans-serif;color:#203b36;background:#fafbf9;letter-spacing:0}
${OWNER} h1,${OWNER} h2,${OWNER} h3,${OWNER} h4,${OWNER} p,${OWNER} figure{padding:0;letter-spacing:0}
${OWNER} h1,${OWNER} h2{font-weight:500;text-transform:none}
${OWNER} .hero-copy #hn-page-title{font-size:46px!important;line-height:1.2}
@media(max-width:1000px){${OWNER} .hero-copy #hn-page-title{font-size:40px!important}}
@media(max-width:700px){${OWNER} .hero-copy #hn-page-title{font-size:32px!important}}
@media(max-width:350px){${OWNER} .hero-copy #hn-page-title{font-size:29px!important}}
${OWNER} button{letter-spacing:0;text-transform:none;box-shadow:none;background-image:none}
${OWNER} #hn-place-search{box-shadow:none;height:auto;margin:0;border-radius:0;line-height:1.65}
${OWNER} a{box-shadow:none} ${OWNER} img{border-radius:0}
body.page-id-3959 #masthead,body.page-id-3959 #masthead>div.site-header{position:relative!important;background:#fff}
body.page-id-3959 #content,body.page-id-3959 .site-content{background:#fafbf9;padding:0!important}
body.page-id-3959 #main .entry-content{margin:0!important}
body.page-id-3959 #content .wrap,body.page-id-3959 #primary,body.page-id-3959 #main{width:100%!important;max-width:100%!important;min-width:0;padding:0!important;margin:0!important}
body.page-id-3959 #page-title-bar{display:none}
${OWNER} #hn-project-view{scroll-margin-top:90px} ${OWNER} #hn-places{scroll-margin-top:90px}
${OWNER} [hidden]{display:none!important}
`;
let js = read(path.join(SRC, 'preview.js'));
js = once(js, "  'use strict';", "  'use strict';\n  const root = document.getElementById('harmat-neighborhood-practical');\n  if (!root || root.dataset.booted) return;\n  root.dataset.booted = 'true';\n  const byId = id => root.querySelector('#hn-' + id);\n  const assets = " + JSON.stringify(assets) + ';');
js = js.replaceAll('document.getElementById(', 'byId(');
js = once(js, "const root = byId('harmat-neighborhood-practical');", "const root = document.getElementById('harmat-neighborhood-practical');");
js = js.replaceAll('document.querySelectorAll(', 'root.querySelectorAll(');
js = once(js, "node.dataset.lucide = name;", "node.setAttribute('data-hn-lucide', name);");
js = once(js, 'const icons = () => window.lucide?.createIcons();', "const icons = () => {\n    window.HarmatNeighborhoodIcons?.createIcons({ nameAttr: 'data-hn-lucide' });\n    root.querySelectorAll('[data-lucide]').forEach(node => node.removeAttribute('data-lucide'));\n  };");
js = once(js, "document.documentElement.lang = value === 'hu' ? 'hu' : 'zh-Hans';", "root.lang = value === 'hu' ? 'hu' : 'zh-Hans';");
const begin = js.indexOf("  const menu = byId('menu-toggle');");
const end = js.indexOf("  search.addEventListener('input', renderPlaces);", begin);
if (begin < 0 || end < 0) throw new Error('Preview menu adapter not found');
js = js.slice(0, begin) + js.slice(end);
js = once(js, "viewer = window.pannellum.viewer('panorama',", "viewer = window.pannellum.viewer('hn-panorama',");
js = once(js, '`assets/panorama-${face}.jpg`', 'assets[`panorama-${face}.jpg`]');
js = js.replaceAll('`assets/panorama-gallery-${galleryFiles[imageIndex]}.jpg`', 'assets[`panorama-gallery-${galleryFiles[imageIndex]}.jpg`]');
js = js.replaceAll('`assets/panorama-gallery-${file}.jpg`', 'assets[`panorama-gallery-${file}.jpg`]');
js = once(js, "  changeLanguage('hu');\n  icons();", `  let appliedLanguage;
  function syncLanguage() {
    const next = document.documentElement.getAttribute('data-harmat-language') === 'zh-Hans' ? 'zh' : 'hu';
    if (appliedLanguage === next) return;
    appliedLanguage = next;
    changeLanguage(next);
  }
  new MutationObserver(syncLanguage).observe(document.documentElement, {
    attributes: true, attributeFilter: ['data-harmat-language']
  });
  syncLanguage();
  icons();`);
const lucide = once(read(path.join(previewOut, 'lucide.min.js')), 'n(a.lucide={})', 'n(a.HarmatNeighborhoodIcons={})');
const files = {'body.html': root.outerHTML + '\n', 'neighborhood.css': css, 'neighborhood.js': js,
  'lucide.js': lucide, 'LICENSE-lucide.txt': read(path.join(previewOut, 'lucide.LICENSE'))};
fs.mkdirSync(DEST, {recursive:true}); fs.mkdirSync(OUT, {recursive:true});
for (const [name, blob] of Object.entries(files)) fs.writeFileSync(path.join(DEST, name), blob);
const manifest = JSON.stringify(Object.fromEntries(Object.entries(files).map(([n,b])=>[n,sha(b)])), null, 2) + '\n';
fs.writeFileSync(path.join(DEST, 'manifest.json'), manifest);
const template = read(path.join(__dirname, '2026-10-27-neighborhood-controller.php.in'));
fs.writeFileSync(path.join(ROOT, 'wp-mu-plugins/zz-harmat-neighborhood-practical.php'), once(template, '__MANIFEST_SHA__', sha(manifest)));
fs.writeFileSync(path.join(OUT, 'existing-media.json'), JSON.stringify(assets,null,2));
console.log(JSON.stringify({status:'OFFLINE_DERIVED', manifest:sha(manifest), files:Object.keys(files)}));
