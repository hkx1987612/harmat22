"""Offline invariants for the desktop complete-source hero correction."""
import hashlib
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
ASSETS = ROOT / 'wp-mu-plugins/harmat-home-family'
SOURCE = (ROOT / 'wp-mu-plugins/zz-harmat-home-family.php').read_bytes()
OLD_SHA = '168e24940d01fa1dd108544346c83c0e500fa7635d7ccf710c8effbcb51b5a05'
OLD_RULE = b"            . '@media(min-width:768px){body.home.page-id-164 #harmat-home-family .hhf-hero-poster,body.home.page-id-164 #harmat-home-family .hhf-hero-video{object-position:center top}}'\n"
RULE = b"            . '@media(min-width:768px){body.home.page-id-164 #harmat-home-family .hhf-hero{height:auto;min-height:0;max-height:calc(100svh - 108px);aspect-ratio:16/9;background:#111a15}body.home.page-id-164 #harmat-home-family .hhf-hero-poster,body.home.page-id-164 #harmat-home-family .hhf-hero-video{object-fit:contain;object-position:center}}'\n"


class VideoCompleteSourceTests(unittest.TestCase):
    def test_only_version_and_final_desktop_rule_change_from_v110(self):
        self.assertEqual(SOURCE.count(b' * Version: 1.0.11\n'), 1)
        self.assertEqual(SOURCE.count(RULE), 1)
        self.assertNotIn(OLD_RULE, SOURCE)
        self.assertIn(RULE + b"            . '</style>';", SOURCE)
        restored = SOURCE.replace(RULE, OLD_RULE, 1).replace(
            b' * Version: 1.0.11\n', b' * Version: 1.0.10\n', 1)
        self.assertEqual(hashlib.sha256(restored).hexdigest(), OLD_SHA)

    def test_pinned_assets_mobile_and_playback_preserved(self):
        manifest = (ASSETS / 'manifest.json').read_bytes()
        self.assertEqual(hashlib.sha256(manifest).hexdigest(),
                         '21e6ebec9bf0bfcbc273c42b5dc33f07ce96547fa04ccdd0b53faf780d3587b3')
        for name, digest in json.loads(manifest).items():
            with self.subTest(asset=name):
                self.assertEqual(hashlib.sha256((ASSETS / name).read_bytes()).hexdigest(), digest)
        css = (ASSETS / 'home-family.css').read_text(encoding='utf-8')
        self.assertIn('object-fit: cover; object-position: center 52%;', css)
        self.assertIn('object-position: 58% center;', css)
        self.assertIn(b'.hhf-hero{width:100%;max-width:none;margin:0;scroll-margin-top:76px}', SOURCE)
        self.assertIn(b'#masthead > div.site-header{position:relative!important;background:#fff}', SOURCE)


if __name__ == '__main__':
    unittest.main(verbosity=2)
