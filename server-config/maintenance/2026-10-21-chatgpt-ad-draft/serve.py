"""Local artifact review only; never expose the repository or create campaigns."""
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parent / 'v1'


class Handler(SimpleHTTPRequestHandler):
    def list_directory(self, path):
        self.send_error(404)

    def do_GET(self):
        path = unquote(urlsplit(self.path).path)
        target = (ROOT / path.lstrip('/')).resolve()
        if not target.is_relative_to(ROOT) or target.is_symlink():
            self.send_error(404)
            return
        if path != '/' and (not target.is_file() or target.suffix.lower() not in ('.html', '.css', '.jpg', '.png')):
            self.send_error(404)
            return
        super().do_GET()

    def do_HEAD(self):
        self.send_error(405)

    def end_headers(self):
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Robots-Tag', 'noindex, nofollow, noarchive')
        super().end_headers()


if __name__ == '__main__':
    with ThreadingHTTPServer(('127.0.0.1', 8791), partial(Handler, directory=str(ROOT))) as server:
        print('LOCAL_AD_REVIEW http://127.0.0.1:8791/', flush=True)
        server.serve_forever()
