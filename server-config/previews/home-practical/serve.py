"""Loopback-only review server. Exact local media aliases; no backend or uploads."""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import mimetypes
from pathlib import Path
import re
from urllib.parse import unquote, urlsplit

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[2]
ROOT = REPO / 'outputs/2026-10-22-home-practical/v1'
MEDIA = {
    '/media/hero.mp4': REPO / 'outputs/2026-10-18-home-family/assets/hero-video.mp4',
    '/media/panorama.mp4': REPO / 'outputs/2026-10-18-home-family/assets/panorama-project-video.mp4',
    '/media/construction.mp4': REPO / 'outputs/2026-10-20-site-media/final-v1/2026-10-20-compilation-hu-subtitled.mp4',
}
STATIC = {'.html', '.css', '.js', '.jpg', '.webp', '.png'}


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        self.serve(True)

    def do_HEAD(self):
        self.serve(False)

    def serve(self, body):
        url = urlsplit(self.path)
        path = unquote(url.path)
        if path in MEDIA:
            target = MEDIA[path]
        else:
            target = (ROOT / ('index.html' if path == '/' else path.lstrip('/'))).resolve()
            if (not target.is_relative_to(ROOT) or target.is_symlink()
                    or target.suffix not in STATIC or not target.is_file()):
                self.send_error(404)
                return
        if not target.is_file():
            self.send_error(404)
            return
        size = target.stat().st_size
        start, end, status = 0, size - 1, 200
        requested = self.headers.get('Range')
        if requested:
            match = re.fullmatch(r'bytes=(\d+)-(\d*)', requested)
            if not match:
                self.send_error(416)
                return
            start = int(match[1])
            end = min(int(match[2]), size - 1) if match[2] else size - 1
            if start > end or start >= size:
                self.send_error(416)
                return
            status = 206
        self.send_response(status)
        self.send_header('Content-Type', mimetypes.guess_type(str(target))[0] or 'application/octet-stream')
        self.send_header('Content-Length', str(end - start + 1))
        self.send_header('Accept-Ranges', 'bytes')
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.send_header('X-Robots-Tag', 'noindex, nofollow, noarchive')
        if status == 206:
            self.send_header('Content-Range', f'bytes {start}-{end}/{size}')
        self.end_headers()
        if not body:
            return
        try:
            with target.open('rb') as source:
                source.seek(start)
                remaining = end - start + 1
                while remaining:
                    chunk = source.read(min(256 * 1024, remaining))
                    if not chunk:
                        break
                    self.wfile.write(chunk)
                    remaining -= len(chunk)
        except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError):
            pass


if __name__ == '__main__':
    with ThreadingHTTPServer(('127.0.0.1', 8793), Handler) as server:
        print('LOCAL_COPY_REVIEW http://127.0.0.1:8793/', flush=True)
        server.serve_forever()
