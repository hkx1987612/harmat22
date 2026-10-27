"""Reuse the loopback-only, exact-root review server. No production backend."""
import importlib.util
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('neighborhood_review_server', HERE.parent / 'home-practical/serve.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
M.ROOT = M.REPO / 'outputs/2026-10-27-neighborhood-practical/v1'
M.MEDIA = {
    '/media/project.mp4': M.REPO / 'outputs/2026-10-18-home-family/assets/panorama-project-video.mp4',
    '/media/visual.mp4': M.REPO / 'outputs/2026-10-18-home-family/assets/hero-video.mp4',
}

if __name__ == '__main__':
    with M.ThreadingHTTPServer(('127.0.0.1', 8795), M.Handler) as server:
        print('NEIGHBORHOOD_REVIEW http://127.0.0.1:8795/', flush=True)
        server.serve_forever()
