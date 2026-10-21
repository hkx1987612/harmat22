"""Serve only the public-media preview on loopback; never serve the repository."""
import argparse
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlsplit

ROOT=Path(__file__).parent/'v1'
class Handler(SimpleHTTPRequestHandler):
    def list_directory(self,path):self.send_error(404)
    def do_GET(self):
        path=unquote(urlsplit(self.path).path)
        candidate=(ROOT/path.lstrip('/')).resolve()
        if not candidate.is_relative_to(ROOT.resolve()) or candidate.is_symlink():
            self.send_error(404);return
        if path!='/' and (not candidate.is_file() or candidate.suffix.lower() not in ('.html','.css','.js','.jpg','.jpeg','.png','.webp')):
            self.send_error(404);return
        return super().do_GET()
    def end_headers(self):
        self.send_header('X-Robots-Tag','noindex, nofollow, noarchive')
        self.send_header('Cache-Control','no-store')
        super().end_headers()

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--port',type=int,default=8789);args=parser.parse_args()
    with ThreadingHTTPServer(('127.0.0.1',args.port),partial(Handler,directory=str(ROOT))) as server:
        print('LOCAL_PREVIEW http://127.0.0.1:'+str(server.server_port)+'/',flush=True)
        server.serve_forever()
