"""Derive scoped production assets from the approved local draft; no server access."""
import hashlib
from html.parser import HTMLParser
from pathlib import Path
import shutil

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'server-config/previews/about-family'
TARGET = ROOT / 'wp-mu-plugins/harmat-about-family'

class Main(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.inside = False
        self.parts = []
        self.count = 0
    def handle_starttag(self, tag, attrs):
        if tag == 'main':
            if self.inside: raise ValueError('Nested main')
            self.inside = True
            self.count += 1
        elif self.inside:
            text = self.get_starttag_text()
            if tag == 'h1':
                if dict(attrs) != {'id':'hap-title'}: raise ValueError('Unknown H1')
                text = '<h2 id="hap-title" class="hap-title">'
            self.parts.append(text)
    def handle_endtag(self, tag):
        if tag == 'main': self.inside = False
        elif self.inside: self.parts.append('</h2>' if tag == 'h1' else '</'+tag+'>')
    def handle_data(self, text):
        if self.inside: self.parts.append(text)
    def handle_entityref(self, name):
        if self.inside: self.parts.append('&'+name+';')
    def handle_charref(self, name):
        if self.inside: self.parts.append('&#'+name+';')

def main():
    parser = Main()
    parser.feed((SOURCE/'index.html').read_text(encoding='utf-8'))
    if parser.count != 1 or parser.inside: raise ValueError('Main contract changed')
    body = '<div id="harmat-about-redesign" class="harmat-about-preview">'+''.join(parser.parts)+'</div>'
    if body.count('id="hap-title"') != 1 or '<script' in body or '<form' in body: raise ValueError('Unsafe body')
    css = (SOURCE/'about-family.css').read_text(encoding='utf-8')
    css = css.replace('.harmat-about-preview', '#harmat-about-redesign.harmat-about-preview').replace(' h1', ' .hap-title')
    css += '''
/* Keep prominent numerals consistent at every breakpoint; the fourth fact is text. */
#harmat-about-redesign.harmat-about-preview .hap-facts-grid > div:nth-child(-n+3) > dd,
#harmat-about-redesign.harmat-about-preview .hap-progress-date > p:not(.hap-kicker) {
  font: 400 34px/1.25 Arial, Helvetica, sans-serif;
  font-variant-numeric: lining-nums tabular-nums;
}
#harmat-about-redesign.harmat-about-preview .hap-progress-date > p > span {
  font-size: 14px;
  line-height: 1.5;
}

/* Only this page's layout shell changes; native navigation/forms remain owned. */
body.page-id-2686 #content, body.page-id-2686 #content > .wrap,
body.page-id-2686 #primary, body.page-id-2686 #main {
  width:100%; max-width:none; margin:0; padding:0; float:none;
}
body.page-id-2686.opal-header-absolute #masthead {position:relative!important;background:#fff;}
body.page-id-2686.opal-header-absolute #masthead > div.site-header {position:relative!important;}
#harmat-about-redesign.harmat-about-preview h3 {font-family:inherit;}
#harmat-about-redesign.harmat-about-preview :is(h1,h2,h3,p,a,summary,dt,dd) {text-transform:none!important;letter-spacing:0!important;}
'''
    php = "<?php\ndefined('ABSPATH') || exit;\nreturn <<<'HARMAT_ABOUT_FAMILY_HTML'\n"+body+"\nHARMAT_ABOUT_FAMILY_HTML;\n"
    TARGET.mkdir(parents=True, exist_ok=True)
    (TARGET/'body.php').write_text(php,encoding='utf-8',newline='\n')
    (TARGET/'about-family.css').write_text(css,encoding='utf-8',newline='\n')
    for name in ('harmat-courtyard.jpg','harmat-living-room.jpg'):
        shutil.copyfile(SOURCE/'assets'/name,TARGET/name)
    for path in sorted(TARGET.iterdir()):
        print(path.name, path.stat().st_size, hashlib.sha256(path.read_bytes()).hexdigest())

if __name__=='__main__': main()
