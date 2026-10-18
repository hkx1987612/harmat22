"""Homepage presentation balance only; reuse reviewed controller-only safe transport."""
import importlib.util
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('home_balance_compact', HERE / '2026-10-18-update-home-header-compact.py')
C = importlib.util.module_from_spec(spec)
spec.loader.exec_module(C)
U = C.U
BASE = '8a14c01'
OLD_SHA = 'b46c184678fe59eab2a01d1c6377abf1b76ceee7f32c299703148f4a62a3db03'
PREFIX = U.H.BACKUPS + '/home-balance-'
SCOPE = 'body.home.page-id-164 #harmat-home-family'


def rule(selectors, declarations):
    return ','.join(SCOPE + (' ' + selector if selector else '') for selector in selectors) + '{' + declarations + '}'


RULES = (
    rule(('',), '--muted:#58645d;background:#f8f8f6'),
    rule(('.hhf-highlights',), 'background:#f6f5f2'),
    rule(('.hhf-apartments',), 'background:#f5f4f2'),
    rule(('.hhf-journal',), 'background:#edf2ef'),
    rule(('.hhf-everyday',), 'background:#f8f8f6'),
    rule(('.hhf-panorama', '.hhf-team'), 'background:#fff'),
    rule(('.hhf-chooser',), 'background:#e9f0eb'),
    rule(('.hhf-contact',), 'background:#f1f0ed'),
    rule(('h2',), 'font-size:36px;line-height:1.3'),
    rule(('h3',), 'font-size:26px;line-height:1.35'),
    rule(('.hhf-apartment-top h3',), 'font-size:21px'),
    rule(('.hhf-panorama-video-card h3',), 'font-size:23px'),
    rule(('.hhf-panorama-detail h3',), 'font-size:24px'),
    rule(('.hhf-journal-copy>p:not(.hhf-eyebrow):not(.hhf-journal-date):not(.hhf-planned-note)',
          '.hhf-everyday-heading>p', '.hhf-everyday-story>p', '.hhf-park-row p:not(.hhf-eyebrow)',
          '.hhf-showroom-copy>p:not(.hhf-eyebrow)', '.hhf-chooser p:not(.hhf-eyebrow)',
          '.hhf-team p:not(.hhf-eyebrow)', '.hhf-contact-intro'), 'font-size:16px;line-height:1.75'),
    rule(('.hhf-journal-copy .hhf-planned-note',), 'font-size:14px;line-height:1.7'),
    rule(('.hhf-media-label',), 'font-size:13px;line-height:1.5'),
    rule(('.hhf-apartment-facts dt', '.hhf-apartment-floor'), 'font-size:14px;line-height:1.6'),
    rule(('.hhf-price strong:not(.hhf-price-hidden)',), 'font-size:24px'),
    rule(('.hhf-price strong span',), 'font-size:16px'),
    rule(('.hhf-highlight-grid dd>strong',), 'min-height:46px'),
    rule(('.hhf-section',), 'padding-block:56px'),
    rule(('.hhf-panorama',), 'padding-top:56px'),
    rule(('.hhf-section-heading', '.hhf-everyday-heading'), 'margin-bottom:24px'),
    rule(('.hhf-journal-copy h2', '.hhf-showroom-copy h2', '.hhf-chooser h2'), 'margin-bottom:16px'),
    rule(('.hhf-journal-grid', '.hhf-everyday-heading', '.hhf-park-row', '.hhf-showroom-row',
          '.hhf-chooser-grid', '.hhf-team-grid', '.hhf-contact-grid'), 'gap:48px'),
    '@media(max-width:1000px) and (min-width:621px){'
    + rule(('.hhf-apartment-grid',), 'grid-template-columns:repeat(2,minmax(0,1fr));gap:20px') + '}',
    '@media(max-width:900px){' + rule(('h2',), 'font-size:32px')
    + rule(('.hhf-journal-grid', '.hhf-everyday-heading', '.hhf-park-row', '.hhf-showroom-row',
            '.hhf-chooser-grid', '.hhf-team-grid', '.hhf-contact-grid'), 'gap:28px') + '}',
    '@media(max-width:620px){' + rule(('h2',), 'font-size:28px')
    + rule(('.hhf-everyday-story h3', '.hhf-park-row h3'), 'font-size:23px')
    + rule(('.hhf-section',), 'padding-block:40px')
    + rule(('.hhf-highlight-grid dd>strong',), 'min-height:60px')
    + rule(('.hhf-panorama',), 'padding-top:40px') + '}',
)
STYLE = C.STYLE.removesuffix('</style>') + ''.join(RULES) + '</style>'


def resource_block():
    return C.resource_block().removesuffix("\n            . '</style>';") + '\n' + '\n'.join(
        "            . '" + css + "'" for css in RULES) + "\n            . '</style>';"


def patch(original):
    U.require(U.sha(original) == OLD_SHA and b'\r' not in original, 'Pinned v1.0.2 LF controller required')
    old = C.resource_block().encode()
    U.require(original.count(old) == 1 and original.count(b' * Version: 1.0.2') == 1,
              'Exact presentation balance patch sites required')
    return original.replace(old, resource_block().encode(), 1).replace(b' * Version: 1.0.2', b' * Version: 1.0.3', 1)


def local_release():
    original = subprocess.check_output(['git', 'show', BASE + ':wp-mu-plugins/' + U.D.MAIN], cwd=ROOT)
    candidate = patch(original)
    U.require((ROOT / 'wp-mu-plugins' / U.D.MAIN).read_bytes().replace(b'\r\n', b'\n') == candidate,
              'Local controller is not the exact presentation balance patch')
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
