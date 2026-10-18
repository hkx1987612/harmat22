"""Homepage search action, phone header fit and text clarity; controller-only update."""
import importlib.util
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('home_clarity_actions', HERE / '2026-10-18-update-home-actions.py')
A = importlib.util.module_from_spec(spec)
spec.loader.exec_module(A)
U = A.U
BASE = 'c258a8a'
OLD_SHA = '680369cfa7dd3613a28d9b62d9ccb96e334cdb615a42d124cdc10c607e393bf1'
PREFIX = U.H.BACKUPS + '/home-clarity-'
HOME = 'body.home.page-id-164 '
HEADER = 'body.home.page-id-164.opal-header-absolute #my-sticky-header '
RULES = (
    A.B.rule(('',), '--muted:#47574e;--body:#35463c'),
    A.B.rule(('h1', 'h2', 'h3'), 'font-weight:600'),
    A.B.rule(('.hhf-highlight-grid dd>strong',), 'font-weight:500'),
    A.B.rule(('.hhf-eyebrow', '.hhf-highlight-grid dt', '.hhf-apartment-facts dt',
              '.hhf-apartment-floor', '.hhf-journal-date'), 'font-weight:500'),
    A.B.rule(('.hhf-hero h1',), 'font-size:38px;text-shadow:0 1px 3px rgba(15,33,24,.55)'),
    A.B.rule(('.hhf-hero-actions .hhf-button',), 'font-size:16px;font-weight:600'),
    A.B.rule(('.hhf-hero-booking',), 'background:rgba(15,33,24,.3)'),
    '@media(max-width:620px){' + A.B.rule(('.hhf-hero h1',), 'font-size:32px') + '}',
    '@media(max-width:360px){' + A.B.rule(('.hhf-hero h1',), 'font-size:30px') + '}',
    '@media(max-width:767px){'
    + HEADER + '.headerrow>.e-con-inner{display:grid!important;grid-template-columns:minmax(133px,1fr) 36px minmax(100px,1fr);gap:4px;padding-inline:12px!important;width:100%!important;box-sizing:border-box;flex-wrap:nowrap}'
    + HEADER + '.headerrow>.e-con-inner>.e-con{width:auto!important;min-width:0;padding-inline:0!important}'
    + HEADER + '.headerrow>.e-con-inner>.elementor-element-58842f8{align-items:flex-start}'
    + HEADER + '.headerrow>.e-con-inner>.elementor-element-fba2323{align-items:flex-end}'
    + HEADER + '.elementor-widget-opal-site-logo img{width:36px!important;height:36px!important}'
    + HEADER + '.elementor-element-56f08cc{display:block!important;width:100px!important;max-width:100%!important;min-width:0;gap:0!important}'
    + HEADER + '.elementor-element-56f08cc .elementor-button-wrapper{display:block!important;width:100%!important;max-width:100%!important;min-width:0;gap:0!important}'
    + HEADER + '.elementor-element-56f08cc a.elementor-button{display:flex;align-items:center;justify-content:center;box-sizing:border-box;width:100%;min-height:40px;padding:8px 6px;font-size:12px;line-height:1.2;letter-spacing:0;text-transform:none!important;white-space:nowrap}'
    + HOME + '#harmat-internal-language[data-hil-anchor=menu],'
    + HOME + '#harmat-internal-language[data-hil-anchor=held],'
    + HOME + '#harmat-internal-language[data-hil-anchor=fallback]{width:84px!important;max-width:84px!important}'
    + HOME + '#harmat-internal-language[data-hil-anchor=fallback]{left:62px!important;right:auto!important;top:16px!important;bottom:auto!important}'
    + HOME + '#harmat-internal-language>button[data-hil-language]{flex:1 1 0;width:auto;padding-inline:1px}'
    + HOME + '#harmat-internal-language>button[data-hil-language=hu]{flex:1.3 1 0}'
    + '}',
)
STYLE = A.STYLE.removesuffix('</style>') + ''.join(RULES) + '</style>'
OLD_ACTION = r'''$booking='<a class="hhf-button hhf-button-white hhf-hero-booking" href="#opal-contactform-popup-56f08cc"><span data-i18n="appointment">'
        . "Id\u{0151}pontk\u{00e9}r\u{00e9}s</span>"'''
NEW_ACTION = r'''$booking='<a class="hhf-button hhf-button-white hhf-hero-booking" href="https://harmat22.hu/lakaskereso/"><span data-i18n="navSearch">'
        . "Lak\u{00e1}skeres\u{0151}</span>"'''


def resource_block():
    return A.resource_block().removesuffix("\n            . '</style>';") + '\n' + '\n'.join(
        "            . '" + css + "'" for css in RULES) + "\n            . '</style>';"


def patch(original):
    U.require(U.sha(original) == OLD_SHA and b'\r' not in original, 'Pinned v1.0.4 LF controller required')
    U.require(original.count(A.resource_block().encode()) == 1
              and original.count(OLD_ACTION.encode()) == 1
              and original.count(b' * Version: 1.0.4') == 1, 'Exact clarity patch sites required')
    return (original.replace(A.resource_block().encode(), resource_block().encode(), 1)
            .replace(OLD_ACTION.encode(), NEW_ACTION.encode(), 1)
            .replace(b' * Version: 1.0.4', b' * Version: 1.0.5', 1))


def local_release():
    original = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + U.D.MAIN], cwd=ROOT)
    candidate = patch(original)
    U.require((ROOT / 'wp-mu-plugins' / U.D.MAIN).read_bytes().replace(b'\r\n', b'\n') == candidate,
              'Local controller is not the exact clarity patch')
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
