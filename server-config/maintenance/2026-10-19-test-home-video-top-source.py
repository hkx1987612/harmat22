"""Offline source invariants for the bounded desktop hero top-crop fix."""
import hashlib
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
ASSETS = ROOT / 'wp-mu-plugins/harmat-home-family'
SOURCE = (ROOT / 'wp-mu-plugins/zz-harmat-home-family.php').read_bytes().replace(b'\r\n', b'\n')
RULE = b"            . '@media(min-width:768px){body.home.page-id-164 #harmat-home-family .hhf-hero-poster,body.home.page-id-164 #harmat-home-family .hhf-hero-video{object-position:center top}}'\n"


class VideoTopSourceTests(unittest.TestCase):
    def test_only_version_and_final_rule_change_from_v109(self):
        self.assertEqual(SOURCE.count(b' * Version: 1.0.10\n'), 1)
        self.assertEqual(SOURCE.count(RULE), 1)
        self.assertIn(RULE + b"            . '</style>';", SOURCE)
        restored = SOURCE.replace(RULE, b'', 1).replace(b' * Version: 1.0.10\n', b' * Version: 1.0.9\n', 1)
        self.assertEqual(hashlib.sha256(restored).hexdigest(),
                         '03e7fa36f1b3f23649f99240123b5a6eeefe6ee3af68edfec6f97a8f588b89c7')

    def test_pinned_assets_cover_mobile_and_playback_preserved(self):
        manifest = (ASSETS / 'manifest.json').read_bytes()
        self.assertEqual(hashlib.sha256(manifest).hexdigest(),
                         '21e6ebec9bf0bfcbc273c42b5dc33f07ce96547fa04ccdd0b53faf780d3587b3')
        for name, digest in json.loads(manifest).items():
            with self.subTest(asset=name):
                self.assertEqual(hashlib.sha256((ASSETS / name).read_bytes()).hexdigest(), digest)
        css = (ASSETS / 'home-family.css').read_text(encoding='utf-8')
        self.assertIn('object-fit: cover; object-position: center 52%;', css)
        self.assertIn('object-position: 58% center;', css)
        self.assertIn('.hhf-hero{width:100%;max-width:none;margin:0;scroll-margin-top:76px}', SOURCE.decode())


if __name__ == '__main__':
    unittest.main(verbosity=2)
