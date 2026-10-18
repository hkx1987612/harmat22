"""Reset three observed phone header paddings only; reuse controller-only safe transport."""
import importlib.util
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('home_header_fit_clarity', HERE / '2026-10-18-update-home-clarity.py')
C = importlib.util.module_from_spec(spec)
spec.loader.exec_module(C)
U = C.U
OLD_SHA = '4ffbebca0b2d93a23b46368f9c0515f2e6b626ec758e91d9fd087dd4ad84d2d4'
PREFIX = U.H.BACKUPS + '/home-header-fit-'
RULES = (
    '@media(max-width:767px){'
    + C.HEADER + '.headerrow{padding-inline:0!important}'
    + C.HEADER + '.elementor-widget-icon{padding-inline:0!important}'
    + C.HEADER + '.elementor-element-56f08cc{padding-inline:0!important}'
    + C.HOME + '#harmat-internal-language>button[data-hil-language=hu]{flex:1.4 1 0}'
    + '}',
)
STYLE = C.STYLE.removesuffix('</style>') + ''.join(RULES) + '</style>'


def resource_block():
    return C.resource_block().removesuffix("\n            . '</style>';") + '\n' + '\n'.join(
        "            . '" + css + "'" for css in RULES) + "\n            . '</style>';"


def patch(original):
    U.require(U.sha(original) == OLD_SHA and b'\r' not in original, 'Pinned v1.0.5 LF controller required')
    U.require(original.count(C.resource_block().encode()) == 1
              and original.count(b' * Version: 1.0.5') == 1, 'Exact header fit patch sites required')
    return (original.replace(C.resource_block().encode(), resource_block().encode(), 1)
            .replace(b' * Version: 1.0.5', b' * Version: 1.0.6', 1))


def original_release():
    base = subprocess.check_output(['git', 'show', C.BASE + ':wp-mu-plugins/' + U.D.MAIN], cwd=ROOT)
    return C.patch(base)


def local_release():
    original = original_release()
    candidate = patch(original)
    U.require((ROOT / 'wp-mu-plugins' / U.D.MAIN).read_bytes().replace(b'\r\n', b'\n') == candidate,
              'Local controller is not the exact header fit patch')
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
