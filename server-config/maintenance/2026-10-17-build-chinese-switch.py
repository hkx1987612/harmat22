"""Export an offline internal-dictionary language-switch prototype, never deploy."""
import argparse
import base64
import hashlib
from html import escape
from html.parser import HTMLParser
import importlib.util
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'server-config/previews/chinese-switch'
OUTPUT = ROOT / 'outputs/2026-10-17-chinese-switch/harmat-chinese-switch-v1.html'
spec = importlib.util.spec_from_file_location('project_preview', Path(__file__).with_name('2026-10-17-build-project-family-preview.py'))
project = importlib.util.module_from_spec(spec)
spec.loader.exec_module(project)


def read(name):
    path = SOURCE / name
    if path.is_symlink():
        raise ValueError('No symlink input')
    return path.read_text(encoding='utf-8')


def script_source():
    dictionary = json.loads(read('zh-Hans.json'))
    if not dictionary or any(not isinstance(k, str) or not isinstance(v, str) or not k or not v for k, v in dictionary.items()):
        raise ValueError('Nonempty string dictionary required')
    payload = json.dumps(dictionary, ensure_ascii=True).replace('<', '\\u003c')
    return read('switch.js') + '\n' + '''
(function () {
  'use strict';
  const dictionary = DICTIONARY;
  document.getElementById('hlp-test-form').addEventListener('submit', event => event.preventDefault());
  window.harmatLocalLanguage = window.HarmatLanguagePreview.create(document, dictionary);
})();
'''.replace('DICTIONARY', payload)


class Export(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.parts = []
        self.switches = self.fixtures = self.csps = self.boots = 0
        self.script = script_source()
        self.digest = base64.b64encode(hashlib.sha256(self.script.encode()).digest()).decode()

    def handle_decl(self, data): self.parts.append('<!' + data + '>')
    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'body':
            self.parts.append('<body class="hpf-preview" data-harmat-language-preview="local-v1">')
        elif tag == 'meta' and values.get('http-equiv', '').lower() == 'content-security-policy':
            policy = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; script-src 'sha256-" + self.digest + "'; connect-src 'none'; base-uri 'none'; form-action 'none'"
            self.parts.append('<meta http-equiv="Content-Security-Policy" content="' + escape(policy, quote=True) + '">')
            self.csps += 1
        else:
            if tag == 'nav' and values.get('class') == 'hpf-desktop-nav':
                self.parts.append('''<div class="hlp-language" role="group" aria-label="Language / 语言" translate="no">
<button type="button" data-hlp-language="hu" lang="hu" aria-pressed="true">Magyar</button>
<button type="button" data-hlp-language="zh-Hans" lang="zh-Hans" aria-pressed="false">中文</button></div>''')
                self.switches += 1
            if tag == 'footer':
                self.parts.append(read('form-fixture.html'))
                self.fixtures += 1
            self.parts.append(self.get_starttag_text())

    def handle_endtag(self, tag):
        if tag == 'head':
            css = read('switch.css')
            if any(token in css.lower() for token in ['</style', 'url(', '@import']):
                raise ValueError('Unexpected stylesheet dependency')
            self.parts.append('<style>' + css + '</style>')
        if tag == 'body':
            if '</script' in self.script.lower():
                raise ValueError('Unexpected script termination')
            self.parts.append('<script>' + self.script + '</script>')
            self.boots += 1
        self.parts.append('</' + tag + '>')

    def handle_data(self, data): self.parts.append(data)
    def handle_entityref(self, name): self.parts.append('&' + name + ';')
    def handle_charref(self, name): self.parts.append('&#' + name + ';')
    def handle_comment(self, data): self.parts.append('<!--' + data + '-->')


def assemble():
    builder = Export()
    builder.feed(project.assemble())
    builder.close()
    if (builder.switches, builder.fixtures, builder.csps, builder.boots) != (1, 1, 1, 1):
        raise ValueError('Unexpected preview structure')
    return ''.join(builder.parts)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--write', action='store_true')
    args = parser.parse_args()
    data = assemble()
    if args.write:
        OUTPUT.parent.mkdir(parents=True, exist_ok=True)
        with OUTPUT.open('x', encoding='utf-8', newline='\n') as file:
            file.write(data)
        print(str(OUTPUT))
        print('sha256=' + hashlib.sha256(OUTPUT.read_bytes()).hexdigest())
    else:
        print('Offline build validated. No server access or file writes.')
