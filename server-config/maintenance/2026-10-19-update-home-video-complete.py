"""Desktop complete-frame hero only; existing native header/forms stay unchanged."""
import importlib.util
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('complete_frame_transport', HERE / '2026-10-19-update-home-video-top.py')
T = importlib.util.module_from_spec(spec)
spec.loader.exec_module(T)
U = T.U
BASE = 'b6dedc0'
OLD_SHA = '168e24940d01fa1dd108544346c83c0e500fa7635d7ccf710c8effbcb51b5a05'
RULE = (b"            . '@media(min-width:768px){body.home.page-id-164 #harmat-home-family .hhf-hero"
        b"{height:auto;min-height:0;max-height:calc(100svh - 108px);aspect-ratio:16/9;background:#111a15}"
        b"body.home.page-id-164 #harmat-home-family .hhf-hero-poster,"
        b"body.home.page-id-164 #harmat-home-family .hhf-hero-video{object-fit:contain;object-position:center}}'\n")
PREFIX = U.H.BACKUPS + '/home-video-complete-'


def validate_candidate(original, candidate):
    U.require(U.sha(original) == OLD_SHA and b'\r' not in original, 'Exact v1.0.10 LF original required')
    U.require(b'\r' not in candidate and candidate.count(RULE) == 1, 'One reviewed desktop rule required')
    restored = candidate.replace(RULE, T.RULE, 1).replace(b' * Version: 1.0.11', b' * Version: 1.0.10', 1)
    U.require(restored == original, 'Only desktop framing rule and version may change')
    return original, candidate


def local_release():
    original = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + U.D.MAIN], cwd=ROOT)
    candidate = (ROOT / 'wp-mu-plugins' / U.D.MAIN).read_bytes().replace(b'\r\n', b'\n')
    validate_candidate(original, candidate)
    files, blobs, hashes, _ = U.D.bundle()
    return original, candidate, files, hashes, blobs[U.D.CHECKER]


def public(remote, files, hashes, baseline, updated):
    proof = T.public(remote, files, hashes, baseline, updated)
    proof['framing'] = 'desktop_complete_frame' if updated else 'prior_top_crop'
    return proof


def configure():
    original, candidate, _, _, _ = local_release()
    U.STYLE = T.V.inline_style(candidate)
    U.require(U.STYLE != T.V.inline_style(original), 'Complete-frame CSS must differ')
    U.D.get = T.Q.get
    U.PREFIX, U.local_release, U.state, U.public = PREFIX, local_release, T.state, public
    U.__doc__ = __doc__
    return U


if __name__ == '__main__':
    configure().main()
