"""Local landing scope, output and recovery contracts; no production requests."""
import hashlib
import json
from pathlib import Path
import re
import runpy
import unittest
from lxml import html

ROOT = Path(__file__).resolve().parents[2]
DIR = ROOT / 'wp-mu-plugins/harmat-v4-landing'
OUT = ROOT / 'outputs/2026-11-09-v4-landing-release'
D = runpy.run_path(str(Path(__file__).with_name('2026-11-09-deploy-v4-landing.py')))


class LandingTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.render = (OUT / 'candidate-render.html').read_bytes()
        cls.page = html.fromstring(cls.render)
        cls.controller = (ROOT / 'wp-mu-plugins/zz-harmat-v4-landing.php').read_text(encoding='utf-8')
        cls.template = (DIR / 'template.php').read_text(encoding='utf-8')

    def test_bundle_pins(self):
        self.assertIn('zz-harmat-v4-landing.php', D['bundle']())

    def test_exact_route_and_read_methods(self):
        self.assertIn("=== '/uj-otthonok/'", self.controller)
        self.assertIn("array('GET', 'HEAD')", self.controller)
        self.assertIn("'REST_REQUEST'", self.controller)
        self.assertIn('!is_admin()', self.controller)

    def test_no_write_or_new_endpoint(self):
        self.assertNotRegex(self.controller + self.template, r'\b(?:wp_mail|update_option|update_post_meta|wp_insert_post|register_rest_route|rest_do_request)\s*\(')

    def test_existing_form_and_script(self):
        result = D['check_landing'](self.render, (OUT / 'native-reference.html').read_bytes())
        self.assertTrue(result['native_modal_exact'])
        self.assertEqual(result['native_form_count'], 1)

    def test_no_new_form(self):
        self.assertEqual(self.page.xpath('//*[@class="hvl-page"]//form'), [])

    def test_public_current_priced_only(self):
        self.assertIn("$item['status'] === 'current'", self.template)
        self.assertIn("is_int($item['price_huf'])", self.template)
        self.assertIn("$item['price_huf'] > 0", self.template)

    def test_three_correct_cards(self):
        cards = self.page.xpath('//article[@class="unit-card"]')
        self.assertEqual([n.get('data-rooms') for n in cards], ['1','2','3'])
        self.assertEqual([n.get('data-apartment-id') for n in cards], ['A1-1-L6','A1-4-L5','A1-4-L1'])
        self.assertIn('49 256 550 Ft', cards[0].text_content())

    def test_area_not_added(self):
        card = self.page.xpath('//article[@data-apartment-id="A1-4-L5"]')[0]
        self.assertIn('47,83 m²', card.text_content())
        self.assertIn('11,95 m²', card.text_content())
        self.assertNotIn('59,78', card.text_content())

    def test_original_offer_anchor(self):
        links = self.page.xpath('//article[@class="unit-card"]/a')
        self.assertTrue(all(n.get('href') == '#opal-contactform-popup' for n in links))
        self.assertNotIn('harmatUnifiedOfferOpen', (DIR/'landing.js').read_text())

    def test_no_form_sending_code(self):
        source = (DIR/'landing.js').read_text()
        self.assertNotRegex(source, r'\b(fetch|XMLHttpRequest|sendBeacon|submit)\s*\(')

    def test_video_unchanged_and_lazy(self):
        video = self.page.xpath('//video')[0]
        self.assertEqual(video.get('preload'), 'none')
        self.assertEqual(video.get('width'), '1920')
        self.assertEqual(video.get('height'), '1080')
        self.assertTrue(video.get('src').endswith('/harmat-60s-v4-hu.mp4'))
        self.assertEqual(hashlib.sha256(D['VIDEO'].read_bytes()).hexdigest(), D['VIDEO_SHA'])

    def test_no_hidden_plan_image_request(self):
        self.assertIsNone(self.page.xpath('//*[@id="large-plan"]')[0].get('src'))

    def test_disclosures_and_date_distinction(self):
        text = self.page.text_content()
        self.assertIn('Látványterv', text)
        self.assertIn('2027. április', text)
        self.assertIn('2028. II. negyedév', text)
        self.assertIn('Ez nem az átadás időpontja', text)
        self.assertNotRegex(text, r'8%|4%|CSOK')

    def test_css_scoped_away_from_native_modal(self):
        css = (DIR/'landing.css').read_text()
        self.assertNotIn('.h22-offer-', css)
        self.assertIn('.hvl-page .progress-grid video{height:auto}', css)
        self.assertNotRegex(css, r'(?m)^(?:h1|h2|button|input|select|:root)\s*\{')

    def test_source_only_recovery(self):
        source = Path(D['__file__']).read_text(encoding='utf-8')
        self.assertIn('Foreign controller retained', source)
        self.assertIn('Existing page owns route', source)
        self.assertNotRegex(source, r'\b(?:wp db import|DELETE FROM|TRUNCATE|DROP TABLE)\b')

    def test_only_normal_traffic_counter_may_advance(self):
        first = {'options': {'harmat_sales_visit_stats': 'a', 'harmat_clients': 'original'}, 'tables': {}, 'sources': {}}
        second = {'options': {'harmat_sales_visit_stats': 'b', 'harmat_clients': 'original'}, 'tables': {}, 'sources': {}}
        self.assertTrue(D['same_protected'](first, second))
        second['options']['harmat_clients'] = 'changed'
        self.assertFalse(D['same_protected'](first, second))

    def test_source_drift_never_ignored(self):
        self.assertFalse(D['same_protected']({'options': {}, 'sources': {'form.php': 'a'}}, {'options': {}, 'sources': {'form.php': 'b'}}))


if __name__ == '__main__':
    unittest.main()
