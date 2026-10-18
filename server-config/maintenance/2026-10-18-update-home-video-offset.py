"""Lower only the desktop hero by one header height; safe controller-only update."""
import importlib.util
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('home_video_offset_fit', HERE / '2026-10-18-update-home-header-fit.py')
F = importlib.util.module_from_spec(spec)
spec.loader.exec_module(F)
U = F.U
BASE = '63361eb'
OLD_SHA = 'fec60e010844ed75673baf79d3f49f7c31eebe6edcd583c78d638712aaa020fd'
PREFIX = U.H.BACKUPS + '/home-video-offset-'
RULES = (
    '@media(min-width:1025px){body.home.page-id-164 #harmat-home-family .hhf-hero{margin-top:76px;scroll-margin-top:152px}}',
)
STYLE = F.STYLE.removesuffix('</style>') + ''.join(RULES) + '</style>'


def resource_block():
    return F.resource_block().removesuffix("\n            . '</style>';") + '\n' + '\n'.join(
        "            . '" + css + "'" for css in RULES) + "\n            . '</style>';"


def patch(original):
    U.require(U.sha(original) == OLD_SHA and b'\r' not in original, 'Pinned v1.0.6 LF controller required')
    U.require(original.count(F.resource_block().encode()) == 1
              and original.count(b' * Version: 1.0.6') == 1, 'Exact desktop video offset patch sites required')
    return (original.replace(F.resource_block().encode(), resource_block().encode(), 1)
            .replace(b' * Version: 1.0.6', b' * Version: 1.0.7', 1))


def local_release():
    original = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + U.D.MAIN], cwd=ROOT)
    candidate = patch(original)
    U.require((ROOT / 'wp-mu-plugins' / U.D.MAIN).read_bytes().replace(b'\r\n', b'\n') == candidate,
              'Local controller is not the exact desktop video offset patch')
    files, blobs, hashes, _ = U.D.bundle()
    return original, candidate, files, hashes, blobs[U.D.CHECKER]


def configure():
    U.PREFIX = PREFIX
    U.STYLE = STYLE
    U.local_release = local_release
    U.__doc__ = __doc__
    return U


if __name__ == '__main__':
    configure().main()
