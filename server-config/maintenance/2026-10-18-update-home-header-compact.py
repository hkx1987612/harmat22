"""Homepage-only 76px header and readable presentation; reuse reviewed controller transport."""
import importlib.util
from pathlib import Path
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('home_compact_transport', HERE / '2026-10-18-update-home-header-flow.py')
U = importlib.util.module_from_spec(spec)
spec.loader.exec_module(U)
ORIGINAL_STYLE = U.STYLE
OLD_SHA = '6dd05836e76d6e0d666f272f55c6fd95df3af38090b30d207f502699e9e723f0'
PREFIX = U.H.BACKUPS + '/home-header-compact-'
RULES = (
    "body.home.page-id-164.opal-header-absolute #masthead,body.home.page-id-164.opal-header-absolute #masthead > div.site-header{position:relative!important;background:#fff}",
    "body.home.page-id-164.opal-header-absolute #my-sticky-header,body.home.page-id-164.opal-header-absolute #my-sticky-header.elementor-sticky__spacer{height:76px!important;min-height:0!important}",
    "body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow,body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow>.e-con-inner{height:76px!important;min-height:0!important;padding-block:0!important}",
    "body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow>.e-con-inner{align-items:center}",
    "body.home.page-id-164.opal-header-absolute #my-sticky-header .headerrow>.e-con-inner>.e-con{height:100%!important;min-height:0!important;padding-block:0!important;justify-content:center}",
    "body.home.page-id-164.opal-header-absolute #my-sticky-header .elementor-widget-icon{padding-block:0!important}",
    "body.home.page-id-164.opal-header-absolute #my-sticky-header .elementor-widget-opal-site-logo img{width:60px!important;height:60px!important;object-fit:contain}",
    "body.home.page-id-164 #harmat-home-family h1,body.home.page-id-164 #harmat-home-family h2,body.home.page-id-164 #harmat-home-family h3{padding:0}",
    "body.home.page-id-164 #harmat-home-family .hhf-eyebrow,body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dt,body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>span{font-size:14px}",
    "body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>strong,body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>.hhf-highlight-date{font-size:32px}",
    "body.home.page-id-164 #harmat-home-family .hhf-highlight-grid .hhf-highlight-unit{font-size:20px}",
    "body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>.hhf-highlight-word{font-size:28px;line-height:1.4;overflow-wrap:anywhere}",
    "body.home.page-id-164 #harmat-home-family .hhf-highlight-date>span{font-size:16px}",
    "body.home.page-id-164 #harmat-home-family .hhf-apartment-top h3{font-size:21px}",
    "body.home.page-id-164 #harmat-home-family .hhf-apartment-floor,body.home.page-id-164 #harmat-home-family .hhf-price>span,body.home.page-id-164 #harmat-home-family .hhf-fine-print,body.home.page-id-164 #harmat-home-family .hhf-apartment-note,body.home.page-id-164 #harmat-home-family .hhf-journal-date,body.home.page-id-164 #harmat-home-family figcaption{font-size:14px}",
    "body.home.page-id-164 #harmat-home-family .hhf-status,body.home.page-id-164 #harmat-home-family .hhf-apartment-facts dt{font-size:13px}",
    "body.home.page-id-164 #harmat-home-family .hhf-apartment-facts dd{font-size:18px}",
    "body.home.page-id-164 #harmat-home-family .hhf-apartment-link,body.home.page-id-164 #harmat-home-family .hhf-text-link{font-size:15px}",
    "body.home.page-id-164 #harmat-home-family .hhf-section-heading,body.home.page-id-164 #harmat-home-family .hhf-everyday-heading{margin-bottom:24px}",
    "body.home.page-id-164 #harmat-home-family .hhf-journal-copy h2,body.home.page-id-164 #harmat-home-family .hhf-showroom-row h2,body.home.page-id-164 #harmat-home-family .hhf-chooser h2{margin-bottom:12px}",
    "body.home.page-id-164 #harmat-home-family .hhf-section{padding-block:64px}",
    "body.home.page-id-164 #harmat-home-family .hhf-panorama{padding-top:0}",
    "@media(max-width:620px){body.home.page-id-164 #harmat-home-family .hhf-section{padding-block:40px}body.home.page-id-164 #harmat-home-family .hhf-panorama{padding-top:0}body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>strong,body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>.hhf-highlight-date{font-size:30px}body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>.hhf-highlight-word{font-size:24px}body.home.page-id-164 #harmat-home-family figcaption{font-size:13px}}",
    "@media(max-width:360px){body.home.page-id-164 #harmat-home-family .hhf-highlight-grid dd>.hhf-highlight-word{font-size:22px}}",
)
STYLE = '<style id="harmat-home-family-header-flow">' + ''.join(RULES) + '</style>'


def resource_block():
    return ("        $resources='<style id=\"harmat-home-family-header-flow\">'\n"
            + '\n'.join("            . '" + rule + "'" for rule in RULES)
            + "\n            . '</style>';")


def patch(original):
    U.require(U.sha(original) == OLD_SHA and b'\r' not in original, 'Pinned v1.0.1 LF controller required')
    old = ("        $resources='" + ORIGINAL_STYLE + "';").encode()
    U.require(original.count(old) == 1 and original.count(b' * Version: 1.0.1') == 1,
              'Exact compact patch sites required')
    return original.replace(old, resource_block().encode(), 1).replace(
        b' * Version: 1.0.1', b' * Version: 1.0.2', 1)


def local_release():
    base = subprocess.check_output(['git', 'show', U.BASE + ':wp-mu-plugins/' + U.D.MAIN], cwd=ROOT)
    # Reconstruct the released v1.0.1 from the original pinned Git version.
    original = U.patch(base)
    candidate = patch(original)
    U.require((ROOT / 'wp-mu-plugins' / U.D.MAIN).read_bytes().replace(b'\r\n', b'\n') == candidate,
              'Local controller is not the exact compact/readability patch')
    files, blobs, hashes, _ = U.D.bundle()
    return original, candidate, files, hashes, blobs[U.D.CHECKER]


def configure():
    # Configure only this separately loaded module; preserve the v1.0.1 updater file.
    U.PREFIX = PREFIX
    U.STYLE = STYLE
    U.local_release = local_release
    U.__doc__ = __doc__
    return U


if __name__ == '__main__':
    configure().main()
