"""Approved preview to production-body contracts; offline only."""
import hashlib
import importlib.util
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
MAINT = Path(__file__).parent
spec = importlib.util.spec_from_file_location('project_preview_tests', MAINT / '2026-10-17-test-project-family-preview.py')
P = importlib.util.module_from_spec(spec)
spec.loader.exec_module(P)
LIVE = ROOT / 'wp-mu-plugins/harmat-project-family'


class CopyTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        source = (LIVE / 'body.php').read_text(encoding='utf-8')
        match = re.fullmatch(r"<\?php\s+return\s+<<<'HARMAT_PROJECT_FAMILY_BODY'\n(.*?)\nHARMAT_PROJECT_FAMILY_BODY;\s*", source, re.S)
        if not match:
            raise AssertionError('Expected only a data-returning PHP nowdoc')
        cls.body = match[1]
        cls.document = P.Document(cls.body)
        cls.preview = P.Document(P.B.local_file('index.html').read_text(encoding='utf-8'))
        cls.css = (LIVE / 'project-family.css').read_text(encoding='utf-8')
        cls.controller = (ROOT / 'wp-mu-plugins/zz-harmat-project-family.php').read_text(encoding='utf-8')

    def test_all_approved_main_copy_retained(self):
        self.assertEqual(self.document.tags('main')[0]['text'], self.preview.tags('main')[0]['text'])
        self.assertEqual(len(self.document.tags('main')), 1)
        self.assertEqual(len(self.document.tags('h1')), 1)
        self.assertEqual(self.document.tags('h1')[0]['attrs'].get('id'), 'hpf-title')

    def test_no_preview_shell_or_active_feature(self):
        for tag in ('script', 'form', 'input', 'button', 'iframe', 'audio', 'video', 'header', 'footer', 'meta', 'link', 'details'):
            self.assertFalse(self.document.tags(tag), tag)
        self.assertNotIn('harmat-logo.png', self.body)
        self.assertNotIn('Content-Security-Policy', self.body)

    def test_approved_four_image_attributes_unchanged(self):
        images = [n['attrs'] for n in self.preview.tags('img') if n['attrs']['src'] != 'assets/harmat-logo.png']
        self.assertEqual([n['attrs'] for n in self.document.tags('img')], images)
        for attrs in images:
            name = attrs['src']
            original = P.B.local_file(name).read_bytes()
            self.assertEqual((LIVE / Path(name).name).read_bytes(), original)
            self.assertEqual(hashlib.sha256(original).hexdigest(), P.B.ASSETS[name][1])

    def test_links_and_facts_match_approved_preview(self):
        images = self.preview.tags('main')[0]['text']
        links = [n['attrs'].get('href') for n in self.document.tags('a')]
        self.assertTrue(all(P.B.public_link(link) for link in links))
        for href in ('https://harmat22.hu/lakaskereso/', 'https://harmat22.hu/virtualis-lakasvalaszto/',
                     'https://harmat22.hu/elerhetosegeink/', 'https://harmat22.hu/epitesi-naplo/',
                     'tel:+36300733375', 'mailto:ertekesites@harmat22.hu'):
            self.assertIn(href, links)
        self.assertIn('8388', images)
        self.assertNotRegex(self.body, r'[\u3400-\u9fff\ufffd]')

    def test_css_rooted_and_no_dependencies(self):
        self.assertNotIn('.hpf-preview', self.css)
        self.assertIn('#harmat-project-family', self.css)
        self.assertNotRegex(self.css, r'@import|@font-face|url\(|letter-spacing:\s*-|font-size:[^;{}]*\d+vw')
        self.assertIn('lining-nums', self.css)
        self.assertIn('tabular-nums', self.css)
        self.assertRegex(self.css, r'\.hpf-fact-number\s*\{[^}]*font-size:\s*36px')
        self.assertIn('body.page-id-1777', self.css)
        self.assertIn('padding: 0', self.css)

    def test_body_css_digest_pins_match_real_bytes(self):
        for name in ('body.php', 'project-family.css'):
            digest = hashlib.sha256((LIVE / name).read_bytes().replace(b'\r\n', b'\n')).hexdigest()
            self.assertIn(digest, self.controller)
        self.assertIn('6659cc4e514d92373321f12095971bea145270866125765909ef7db52f5efdd4', self.controller)
        self.assertIn('db8446abb9b2702f0ae8b3e2468926e2fd4c5284292c031650b9a80fbb78bf17', self.controller)


if __name__ == '__main__':
    unittest.main(verbosity=2)
