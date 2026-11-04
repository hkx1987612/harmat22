"""Loopback-only preview of generated synthetic CRM fixtures; no live credentials."""
import argparse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlsplit, parse_qs, urlencode
from lxml import html

ROOT = Path(__file__).resolve().parents[2] / 'outputs/2026-11-04-crm-review'
WARM = Path(__file__).resolve().parent / 'previews/2026-11-04-crm-warm.css'


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        query = parse_qs(urlsplit(self.path).query)
        role = query.get('demo_role', ['manager'])[0]
        lang = query.get('sales_lang', ['zh'])[0]
        view = query.get('view', ['dashboard'])[0]
        theme = query.get('theme', ['current'])[0]
        if role not in ('manager', 'staff') or lang not in ('zh', 'hu') or view not in ('dashboard', 'traffic', 'inquiries', 'clients', 'deals', 'properties') or theme not in ('current', 'warm'):
            self.send_error(404, 'This offline preview only includes reviewed views.')
            return
        path = ROOT / f'{role}-{lang}-{view}.html'
        if view == 'deals' and query.get('deal_order') == ['followup']:
            path = ROOT / f'{role}-{lang}-deals-followup.html'
        if not path.is_file():
            self.send_error(404)
            return
        doc = html.fromstring(path.read_bytes())
        for anchor in doc.xpath('//a[@href]'):
            url = urlsplit(anchor.get('href'))
            if url.hostname == 'crm.example.test' and url.path == '/sales/':
                args = {k: v[-1] for k, v in parse_qs(url.query).items()}
                args.setdefault('sales_lang', lang)
                args['demo_role'] = role
                args['theme'] = theme
                anchor.set('href', '/?' + urlencode(args) + ('#' + url.fragment if url.fragment else ''))
        for form in doc.xpath('//form'):
            form.set('onsubmit', 'return false')
        for style in doc.xpath('//style'):
            style.text = (style.text or '').replace('@import url("https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Source+Sans+3:wght@400;600;700;800;900&display=swap");', '')
        if theme == 'warm' and not any('--crm-bg:' in (s.text or '') for s in doc.xpath('//style')):
            style = html.Element('style', id='crm-warm-preview-only')
            style.text = WARM.read_text(encoding='utf-8')
            doc.find('head').append(style)
        notice = html.Element('aside', style='padding:8px 16px;background:#e5eeea;color:#264b3d;font:13px Arial')
        notice.text = '离线预览 | 示例数据 | 未上线' if lang == 'zh' else 'Előnézet | Mintaadatok | Nem éles'
        doc.find('body').insert(0, notice)
        body = html.tostring(doc, encoding='utf-8', method='html')
        self.send_response(200)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Cache-Control', 'no-store')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self):
        self.send_error(405, 'Read-only offline preview')

    def log_message(self, *args):
        pass


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--port', type=int, default=8804)
    args = parser.parse_args()
    ThreadingHTTPServer(('127.0.0.1', args.port), Handler).serve_forever()
