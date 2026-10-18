"""Restore homepage hero destinations only; unchanged native sales popup handles booking."""
import importlib.util
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('home_actions_balance', HERE / '2026-10-18-update-home-balance.py')
B = importlib.util.module_from_spec(spec)
spec.loader.exec_module(B)
U = B.U
BASE = '1cd751c'
OLD_SHA = '3b82f3e7fcd085b25ec4e4f9fba54af0ae63dd1e311145f421859a2a3d900337'
PREFIX = U.H.BACKUPS + '/home-actions-'
RULES = (
    B.rule(('.hhf-hero-booking',), 'background:transparent;border-color:#fff;color:#fff'),
    B.rule(('.hhf-hero-booking:hover',), 'background:#fff;color:var(--forest)'),
    '@media(max-width:620px){' + B.rule(('.hhf-hero-actions',), 'flex-direction:column;align-items:stretch;max-width:320px')
    + B.rule(('.hhf-highlight-grid .hhf-highlight-unit',), 'display:block;font-size:16px;line-height:1.25')
    + B.rule(('.hhf-highlight-grid dd>strong',), 'min-height:64px') + '}',
)
STYLE = B.STYLE.removesuffix('</style>') + ''.join(RULES) + '</style>'
OUTPUT_START = 'function harmat_home_family_output($html) {'
INCLUDE = "        $replacement=include __DIR__.'/harmat-home-family/body.php';"
CALL = '\n        $replacement=harmat_home_family_hero_actions($replacement);'
HELPER = r'''// Rewrite only the two manifest-pinned hero anchors; native popup handling stays unchanged.
function harmat_home_family_hero_actions($html) {
    if (!is_string($html)) return false;
    $choose='<a class="hhf-button hhf-button-white" href="#hhf-lakasok"><span data-i18n="chooseApartment">'
        . "Lak\u{00e1}st v\u{00e1}lasztok</span>";
    $journal='<a class="hhf-hero-journal" href="#hhf-epitesi-naplo"><span data-i18n="readJournal">'
        . "\u{00c9}p\u{00ed}t\u{00e9}si napl\u{00f3}</span>"
        . '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg></a>';
    if (substr_count($html,'<div class="hhf-actions hhf-hero-actions">')!==1
        || substr_count($html,$choose)!==1 || substr_count($html,$journal)!==1) return false;
    $selector='<a class="hhf-button hhf-button-white" href="https://harmat22.hu/virtualis-lakasvalaszto/"><span data-i18n="chooserLink">'
        . "Virtu\u{00e1}lis lak\u{00e1}sv\u{00e1}laszt\u{00f3}</span>";
    $booking='<a class="hhf-button hhf-button-white hhf-hero-booking" href="#opal-contactform-popup-56f08cc"><span data-i18n="appointment">'
        . "Id\u{0151}pontk\u{00e9}r\u{00e9}s</span>"
        . '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg></a>';
    return str_replace(array($choose,$journal),array($selector,$booking),$html);
}'''


def resource_block():
    return B.resource_block().removesuffix("\n            . '</style>';") + '\n' + '\n'.join(
        "            . '" + css + "'" for css in RULES) + "\n            . '</style>';"


def patch(original):
    U.require(U.sha(original) == OLD_SHA and b'\r' not in original, 'Pinned v1.0.3 LF controller required')
    U.require(original.count(B.resource_block().encode()) == 1
              and original.count(OUTPUT_START.encode()) == 1 and original.count(INCLUDE.encode()) == 1
              and original.count(b' * Version: 1.0.3') == 1
              and b'function harmat_home_family_hero_actions' not in original, 'Exact hero action patch sites required')
    return (original.replace(B.resource_block().encode(), resource_block().encode(), 1)
            .replace(OUTPUT_START.encode(), (HELPER + '\n\n' + OUTPUT_START).encode(), 1)
            .replace(INCLUDE.encode(), (INCLUDE + CALL).encode(), 1)
            .replace(b' * Version: 1.0.3', b' * Version: 1.0.4', 1))


def local_release():
    original = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + U.D.MAIN], cwd=ROOT)
    candidate = patch(original)
    U.require((ROOT / 'wp-mu-plugins' / U.D.MAIN).read_bytes().replace(b'\r\n', b'\n') == candidate,
              'Local controller is not the exact hero action patch')
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
