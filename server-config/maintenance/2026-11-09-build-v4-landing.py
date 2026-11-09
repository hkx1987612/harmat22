"""Mechanical promotion of the approved preview; native forms are not copied/edited."""
import hashlib
import json
from pathlib import Path
import re
import shutil
import tinycss2
from lxml import html

ROOT = Path(__file__).resolve().parents[2]
PREVIEW = ROOT / 'outputs/2026-11-09-v4-lead-preview'
DEST = ROOT / 'wp-mu-plugins/harmat-v4-landing'


def scope_rules(rules):
    result = []
    for rule in rules:
        if rule.type == 'qualified-rule':
            selectors = tinycss2.serialize(rule.prelude).strip().split(',')
            scoped = []
            for selector in selectors:
                selector = selector.strip()
                if '.review' in selector or '.ad-' in selector or '.copy-' in selector or '.budget' in selector or '.frame-' in selector or '.segment' in selector or '.state-label' in selector:
                    continue
                if selector in (':root', 'body', '.landing-body'):
                    scoped.append('.hvl-page')
                elif selector == 'html':
                    continue
                else:
                    scoped.append('.hvl-page ' + selector)
            if scoped:
                result.append(','.join(scoped) + '{' + tinycss2.serialize(rule.content) + '}')
        elif rule.type == 'at-rule' and rule.lower_at_keyword == 'media':
            result.append('@media ' + tinycss2.serialize(rule.prelude).strip() + '{' + scope_rules(tinycss2.parse_rule_list(rule.content)) + '}')
    return '\n'.join(result)


def build():
    page = html.fromstring((PREVIEW / 'landing.html').read_bytes())
    body = page.find('body')
    for node in body.xpath('./div[@class="local-preview-label"]|./dialog[@id="standalone-offer-note"]'):
        node.getparent().remove(node)
    for button in body.xpath('.//*[@data-offer]'):
        button.tag = 'a'
        button.attrib.pop('type', None)
        button.attrib.pop('data-offer', None)
        button.set('href', '#opal-contactform-popup')
    for link in body.xpath('.//a[@href="/"]|.//a[@class="landing-logo"]'):
        link.set('href', 'https://harmat22.hu/')
        if 'landing-logo' not in link.get('class', ''):
            link.text = 'Hivatalos weboldal'
    grid = body.xpath('.//*[@id="unit-grid"]')[0]
    grid.text = '{{CARDS}}'
    body.xpath('.//*[@id="large-plan"]')[0].attrib.pop('src', None)
    hero = body.xpath('.//*[@class="hero-price"]/strong')[0]
    hero.text = '{{PRICE}}'
    note = body.xpath('.//*[@class="data-note"]')[0]
    note.text = note.text.replace('2026.11.09.', '{{DATE}}')
    video = body.xpath('.//video')[0]
    video.set('src', '{{ASSET}}harmat-60s-v4-hu.mp4')
    video.set('playsinline', '')
    video.set('width', '1920'); video.set('height', '1080')
    text = ''.join(html.tostring(node, encoding='unicode') for node in body)
    text = text.replace('/assets/', '{{ASSET}}')
    assert '仅预览' not in text and 'standalone-offer-note' not in text
    (DEST / 'body.html').write_text(text, encoding='utf-8', newline='\n')
    css = scope_rules(tinycss2.parse_stylesheet((PREVIEW / 'style.css').read_text(encoding='utf-8')))
    css = 'body.hvl-document{margin:0;background:#f4f4f0}.hvl-page{font-family:Arial,sans-serif;font-size:16px;line-height:1.6;color:#233e38;overflow-wrap:break-word}.hvl-page dialog{margin:auto}.hvl-page a.button{color:inherit}.hvl-page a.primary{color:#fff}.hvl-page a.outline-light{color:#fff}\n' + css
    css += '\n.hvl-page .unit-grid>p{grid-column:1/-1}.hvl-page .hero-actions a.light{color:#233e38}.hvl-page .progress-grid video{height:auto}\n'
    css += '.hvl-page h1,.hvl-page h2,.hvl-page h3{text-transform:none;font-style:normal;color:var(--ink);letter-spacing:0}.hvl-page .hero-inner h1{color:#fff}.hvl-page .dialog-heading h2{font-family:Arial,sans-serif}\n'
    css += '.hvl-page .landing-header .header-offer{width:auto;min-width:0;flex:0 0 auto}\n'
    css += '.hvl-page h3{font-family:Arial,sans-serif}\n'
    (DEST / 'landing.css').write_text(css, encoding='utf-8', newline='\n')
    for name in ('lucide.js', 'LICENSE-lucide.txt'):
        shutil.copyfile(PREVIEW / 'assets' / name, DEST / name)
    names = ('template.php', 'body.html', 'landing.css', 'landing.js', 'lucide.js', 'LICENSE-lucide.txt')
    pins = {name: hashlib.sha256((DEST / name).read_bytes()).hexdigest() for name in names}
    (DEST / 'manifest.json').write_text(json.dumps(pins, indent=2) + '\n', encoding='ascii', newline='\n')
    print(json.dumps({'status': 'BUILT_FROM_APPROVED_PREVIEW', 'files': list(pins), 'native_form_sources_changed': False}))


if __name__ == '__main__':
    build()
