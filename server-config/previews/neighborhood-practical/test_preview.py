"""Offline preview invariants. No production calls or customer submissions."""
from hashlib import sha256
import importlib.util
import json
from pathlib import Path
import re
import subprocess
import unittest

from lxml import html

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('neighborhood_build', HERE / 'build.py')
B = importlib.util.module_from_spec(spec)
spec.loader.exec_module(B)


class Preview(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.raw = (B.OUT / 'index.html').read_text(encoding='utf-8')
        cls.tree = html.fromstring(cls.raw)
        cls.data = json.loads(cls.tree.get_element_by_id('facility-data').text)

    def test_original_seventeen_places_and_distances(self):
        original = html.fromstring((B.OUT / 'public-before.html').read_text(encoding='utf-8'))
        rows = original.xpath('//*[contains(concat(" ",normalize-space(@class)," ")," harmat-neighborhood-distance-card ")]//li')
        pairs = [(' '.join(r.xpath('.//span')[0].text_content().split()),
                  ' '.join(r.xpath('.//strong')[0].text_content().split())) for r in rows]
        self.assertEqual(len(pairs), 17)
        self.assertEqual(pairs, [(p['name'], p['distance']) for p in self.data['places']])
        self.assertEqual([p['name'] for p in self.data['places']], B.SOURCE_NAMES)

    def test_categories_complete(self):
        self.assertEqual([sum(p['category'] == group['id'] for p in self.data['places'])
                          for group in self.data['categories']], [4, 4, 3, 3, 3])

    def test_no_forms_or_production_integration(self):
        self.assertFalse(self.tree.xpath('//form|//iframe|//*[@name="_harmat_offer_nonce"]'))
        code = (HERE / 'preview.js').read_text(encoding='utf-8')
        for marker in ('fetch(', 'XMLHttpRequest', 'wp-json', 'localStorage', 'sessionStorage'):
            self.assertNotIn(marker, code)

    def test_csp_and_noindex(self):
        self.assertEqual(self.tree.xpath('//meta[@name="robots"]/@content'), ['noindex,nofollow,noarchive'])
        csp = self.tree.xpath('//meta[@http-equiv="Content-Security-Policy"]/@content')[0]
        self.assertIn("form-action 'none'", csp)
        self.assertIn("connect-src 'none'", csp)

    def test_all_display_copy_bilingual(self):
        nodes = self.tree.xpath('//*[@data-hu]')
        self.assertGreater(len(nodes), 25)
        self.assertTrue(all(n.get('data-zh') for n in nodes))
        self.assertTrue(all(p['zh'] and p['descriptionZh'] for p in self.data['places']))

    def test_no_external_media_or_executable_scripts(self):
        for node in self.tree.xpath('//img[@src]|//script[@src]|//source[@src]|//video[@poster]|//link[@rel="stylesheet"]'):
            value = node.get('src') or node.get('poster') or node.get('href')
            self.assertFalse(value.startswith(('http:', 'https:', '//')), value)

    def test_ids_unique(self):
        ids = self.tree.xpath('//*[@id]/@id')
        self.assertEqual(len(ids), len(set(ids)))
        self.assertEqual(len(self.tree.xpath('//h1')), 1)

    def test_original_asset_bytes(self):
        pins = json.loads((B.OUT / 'asset-pins.json').read_text())
        self.assertGreater(len(pins), 15)
        for name, expected in pins.items():
            self.assertEqual(sha256((B.OUT / 'assets' / name).read_bytes()).hexdigest(), expected, name)
        self.assertEqual(pins['ohegy-park.jpg'], B.PARK_SHA)

    def test_no_new_travel_time_promises_or_meta_copy(self):
        for marker in ('8-10 perc', '10-15 perc', '15-20 perc', 'erős érv', 'Ez a blokk', 'Saját, friss'):
            self.assertNotIn(marker, self.raw)
        self.assertIn('légvonalbeli', self.raw)
        self.assertIn('2024', self.raw)

    def test_no_production_source_edits(self):
        changed = subprocess.check_output(['git', 'diff', '--name-only', 'HEAD', '--', 'wp-mu-plugins', 'wp-plugins'], cwd=B.REPO)
        self.assertEqual(changed, b'')

    def test_no_arbitrary_files_in_local_server(self):
        code = (HERE / 'serve.py').read_text()
        self.assertIn("('127.0.0.1', 8795)", code)
        self.assertEqual(code.count("'/media/"), 2)
        shared = (HERE.parent / 'home-practical/serve.py').read_text()
        self.assertIn('is_relative_to(ROOT)', shared)
        self.assertNotIn('def do_POST', shared)


if __name__ == '__main__':
    unittest.main()
