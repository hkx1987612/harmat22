"""Bounded guest HTTP audit. No browser emulation, writes to site or form submissions."""
import gzip
from html.parser import HTMLParser
import importlib.util
import io
import json
from pathlib import Path
import subprocess
import sys
from urllib.parse import urljoin, urlsplit
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'outputs/2026-10-18-home-load-audit/http-v1'
ORIGIN = 'https://harmat22.hu'
HOME = ORIGIN + '/'
JOURNAL = ORIGIN + '/epitesi-naplo/'


def require(ok, message):
    if not ok:
        raise RuntimeError(message)


def owner_helpers():
    path = Path(__file__).with_name('2026-10-18-deploy-construction-updates.py')
    spec = importlib.util.spec_from_file_location('audit_owned_html', path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class Markup(HTMLParser):
    def __init__(self, text):
        super().__init__(convert_charrefs=True)
        self.nodes = []
        self.feed(text)

    def handle_starttag(self, tag, attrs):
        self.nodes.append((tag, dict(attrs)))


def safe_url(url):
    p = urlsplit(url)
    require(p.scheme == 'https' and p.netloc == 'harmat22.hu' and not p.fragment,
            'Guest audit URL outside the exact public origin')
    require(not p.username and not p.password, 'Credential URL refused')
    return url


def guest_html(url):
    request = urllib.request.Request(safe_url(url), headers={
        'Accept-Encoding': 'gzip', 'User-Agent': 'HarmatReadOnlyLoadAudit/1.0'})
    with urllib.request.urlopen(request, timeout=30) as response:
        require(response.status == 200 and response.geturl() == url, 'HTML status/identity mismatch')
        require(response.headers.get_content_type() == 'text/html', 'Wrong HTML MIME')
        wire = response.read(2 * 1024 * 1024 + 1)
        require(len(wire) <= 2 * 1024 * 1024, 'HTML response too large')
        if response.headers.get('Content-Encoding') == 'gzip':
            with gzip.GzipFile(fileobj=io.BytesIO(wire)) as source:
                body = source.read(2 * 1024 * 1024 + 1)
        else:
            body = wire
        require(len(body) <= 2 * 1024 * 1024, 'Decoded HTML too large')
        return body.decode('utf-8'), {'wire_bytes': len(wire), 'decoded_bytes': len(body),
                                     'encoding': response.headers.get('Content-Encoding'),
                                     'cache': response.headers.get('Cache-Control')}


def timing(url):
    fields = '{"status":%{http_code},"ttfb_s":%{time_starttransfer},"total_s":%{time_total},"wire_bytes":%{size_download}}'
    p = subprocess.run(['curl.exe', '--compressed', '--silent', '--show-error', '--max-time', '30',
                        '--output', 'NUL', '--write-out', fields, safe_url(url)], capture_output=True, text=True)
    require(p.returncode == 0, 'Curl HTTP timing failed')
    result = json.loads(p.stdout)
    require(result['status'] == 200, 'Guest timing route failed')
    return result


def head(url, video=False):
    request = urllib.request.Request(safe_url(url), method='HEAD', headers={'User-Agent': 'HarmatReadOnlyLoadAudit/1.0'})
    with urllib.request.urlopen(request, timeout=30) as response:
        require(response.status == 200 and response.geturl() == url, 'Asset HEAD failed')
        mime = response.headers.get_content_type()
        if video:
            require(mime == 'video/mp4' and response.headers.get('Accept-Ranges') == 'bytes', 'Video MIME/range mismatch')
        return {'mime': mime, 'bytes': int(response.headers.get('Content-Length', 0)),
                'cache': response.headers.get('Cache-Control'), 'ranges': response.headers.get('Accept-Ranges')}


def main():
    require(not OUT.exists(), 'Keep existing evidence; choose a new attempt directory')
    OUT.mkdir(parents=True)
    helpers = owner_helpers()
    home, home_http = guest_html(HOME)
    journal, journal_http = guest_html(JOURNAL)
    home_owned = helpers.owned(home, 'id', 'harmat-home-family')
    teaser = helpers.owned(home, 'class', 'hhf-journal')
    journal_owned = helpers.owned(journal, 'id', 'harmat-construction-updates')
    home_nodes, teaser_nodes, journal_nodes = Markup(home_owned).nodes, Markup(teaser).nodes, Markup(journal_owned).nodes
    home_players = [a for _, a in teaser_nodes if 'data-hcu-player' in a]
    journal_players = [a for _, a in journal_nodes if 'data-hcu-player' in a]
    require(len(home_players) == 1 and len(journal_players) == 5, 'Construction count policy mismatch')
    require(home_players[0]['data-hcu-video'].endswith('/2026-10-13-compilation-hu-subtitled.mp4'), 'Homepage is not latest approved compilation')
    require(not any(t in ('video', 'source', 'iframe') for t, _ in teaser_nodes), 'Construction media attaches before play')
    require(not any(t in ('form', 'input', 'textarea') for t, _ in teaser_nodes), 'Unexpected form in owned teaser')
    require(all(a.get('loading') == 'lazy' and a.get('width') == '1280' and a.get('height') == '720'
                for t, a in teaser_nodes + journal_nodes if t == 'img'), 'Construction posters not dimensioned/lazy')
    images = list(dict.fromkeys(a['src'] for t, a in home_nodes if t == 'img' and a.get('src')))
    require(len(images) <= 30, 'Unexpected image fanout')
    same_origin_images = [safe_url(urljoin(HOME, x)) for x in images if urlsplit(urljoin(HOME, x)).netloc == 'harmat22.hu']
    media = list(dict.fromkeys(a.get('data-src') for t, a in home_nodes if t == 'video' and a.get('data-src')))
    require(len(media) <= 5, 'Unexpected background/showcase video fanout')
    result = {'scope': 'GUEST_HTTP_ONLY_NOT_BROWSER_CWV_OR_4G', 'home': home_http, 'journal': journal_http,
              'policy': {'home_construction_players': 1, 'journal_players': 5, 'home_construction_download_before_click': False},
              'timings': {'home': [timing(HOME) for _ in range(3)], 'journal': [timing(JOURNAL) for _ in range(3)]},
              'home_image_heads': {url: head(url) for url in same_origin_images},
              'background_and_showcase_heads': {url: head(url, video=True) for url in media},
              'construction_video_heads': {a['data-hcu-video']: head(a['data-hcu-video'], video=True) for a in journal_players}}
    require(len(result['home_image_heads']) == len(same_origin_images), 'Unexamined homepage image')
    (OUT / 'report.json').write_text(json.dumps(result, ensure_ascii=True, indent=2), encoding='utf-8')
    print(json.dumps(result, ensure_ascii=True, indent=2))


def routes():
    helpers = owner_helpers()
    with helpers.D.T.bounded_shared_requests():
        result = helpers.D.I.HEALTH.smoke()
    require(OUT.is_dir(), 'Run the HTTP baseline audit first')
    with (OUT / 'routes.json').open('x', encoding='utf-8') as output:
        json.dump(result, output, indent=2)
    print(json.dumps(result))


if __name__ == '__main__':
    if sys.argv[1:] == ['--routes']:
        routes()
    else:
        require(not sys.argv[1:], 'Unsupported audit arguments')
        main()
