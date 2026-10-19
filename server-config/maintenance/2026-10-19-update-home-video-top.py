"""Exact homepage desktop video framing update; no native form or media changes."""
import importlib.util
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, HERE / filename)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


V = load('video_top_fill_helpers', '2026-10-18-update-home-hero-fill.py')
U = V.U
Q = load('video_top_protected', '2026-10-18-deploy-public-payment-copy.py')
BASE = '3723709'
OLD_SHA = '03e7fa36f1b3f23649f99240123b5a6eeefe6ee3af68edfec6f97a8f588b89c7'
RULE = (b"            . '@media(min-width:768px){body.home.page-id-164 #harmat-home-family .hhf-hero-poster,"
        b"body.home.page-id-164 #harmat-home-family .hhf-hero-video{object-position:center top}}'\n")
PREFIX = U.H.BACKUPS + '/home-video-top-'


def validate_candidate(original, candidate):
    U.require(U.sha(original) == OLD_SHA and b'\r' not in original, 'Pinned v1.0.9 LF controller required')
    U.require(b'\r' not in candidate and candidate.count(RULE) == 1, 'Single desktop framing rule required')
    restored = candidate.replace(RULE, b'', 1).replace(b' * Version: 1.0.10', b' * Version: 1.0.9', 1)
    U.require(restored == original, 'Only desktop video/poster position and version may change')
    U.require(candidate.index(RULE) < candidate.index(V.END), 'Rule must remain inside owned style')
    return original, candidate


def local_release():
    original = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + U.D.MAIN], cwd=ROOT)
    candidate = (ROOT / 'wp-mu-plugins' / U.D.MAIN).read_bytes().replace(b'\r\n', b'\n')
    validate_candidate(original, candidate)
    files, blobs, hashes, _ = U.D.bundle()
    return original, candidate, files, hashes, blobs[U.D.CHECKER]


def state(remote, files, hashes):
    U.assets(remote, files, hashes)
    value = Q.snapshot(remote)
    value['sources'].pop(U.TARGET, None)
    for path in (Q.TARGET_A, Q.TARGET_N):
        value['sources'][path] = U.sha(remote.read(path))
    return value


def public(remote, files, hashes, baseline, updated):
    proof = U.D.public_proof(remote, files, hashes, baseline)
    data, mime = U.D.get(U.D.URL)
    U.require('text/html' in mime and data.decode('utf-8').count(U.STYLE) == int(updated),
              'Public framing style mismatch')
    V.policy(data.decode('utf-8'))
    journal, mime = U.D.get(V.C.JOURNAL)
    U.require('text/html' in mime, 'Journal MIME mismatch')
    V.policy(journal.decode('utf-8'), journal=True)
    proof['framing'] = 'desktop_top' if updated else 'prior_center'
    return proof


def configure():
    original, candidate, _, _, _ = local_release()
    U.STYLE = V.inline_style(candidate)
    U.require(U.STYLE != V.inline_style(original), 'No framing change')
    U.D.get = Q.get
    U.PREFIX, U.local_release, U.state, U.public = PREFIX, local_release, state, public
    U.__doc__ = __doc__
    return U


if __name__ == '__main__':
    configure().main()
