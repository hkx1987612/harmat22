"""Offline assertions for the two specifically approved homepage figure edits."""
import importlib.util
from pathlib import Path
import unittest
from lxml import html

spec = importlib.util.spec_from_file_location('showroom_release', Path(__file__).with_name('2026-10-23-home-showroom.py'))
R = importlib.util.module_from_spec(spec)
spec.loader.exec_module(R)


class ShowroomTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.before = R.original(R.ASSET + '/body-practical.php').decode()
        cls.after = R.rewrite(cls.before, '__HHF_ASSET_BASE__' + R.PHOTO)
        cls.a, cls.b = html.fromstring(cls.before), html.fromstring(cls.after)

    def test_generated_assets_and_controller(self):
        self.assertEqual(len(R.release()[0]), 5)

    def test_park_image_byte_exact(self):
        for root in (self.a, self.b):
            image = root.xpath('//img[@data-hhf-ohegy]')[0]
            image.tail = None
        self.assertEqual(html.tostring(self.a.xpath('//img[@data-hhf-ohegy]')[0]), html.tostring(self.b.xpath('//img[@data-hhf-ohegy]')[0]))

    def test_only_note_and_credit_removed(self):
        self.assertEqual(len(self.a.xpath('//figcaption[@data-i18n="parkCaption"]')), 1)
        self.assertEqual(len(self.b.xpath('//figcaption[@data-i18n="parkCaption"]')), 0)
        self.assertEqual(len(self.b.xpath('//span[@class="hhf-photo-credit"]')), 0)
        self.assertEqual(len(self.a.xpath('//img')), len(self.b.xpath('//img')))
        self.assertEqual(len(self.a.xpath('//figcaption')), len(self.b.xpath('//figcaption')) + 1)

    def test_showroom_full_original_frame(self):
        image = self.b.xpath('//img[@data-hhf-showroom-exterior]')[0]
        self.assertEqual((image.get('width'), image.get('height')), ('1706', '1280'))
        self.assertEqual(image.get('data-i18n-alt'), 'showroomAlt')
        self.assertEqual(image.get('loading'), 'lazy')
        self.assertEqual(image.get('src'), '__HHF_ASSET_BASE__' + R.PHOTO)
        self.assertIn('object-fit:contain!important', R.CSS)

    def test_showroom_caption_retained(self):
        self.assertEqual(html.tostring(self.a.xpath('//figcaption[@data-i18n="showroomCaption"]')[0]), html.tostring(self.b.xpath('//figcaption[@data-i18n="showroomCaption"]')[0]))

    def test_all_other_images_unchanged(self):
        select = '//img[not(@data-i18n-alt="showroomAlt")]'
        self.assertEqual([html.tostring(n) for n in self.a.xpath(select)], [html.tostring(n) for n in self.b.xpath(select)])

    def test_other_sections_and_links_unchanged(self):
        for key in ('hhf-kezdolap', 'hhf-lakasok', 'hhf-epitesi-naplo', 'hhf-panorama', 'hhf-lakasvalaszto', 'hhf-kapcsolat'):
            self.assertEqual(html.tostring(self.a.xpath('//*[@id="' + key + '"]')[0]), html.tostring(self.b.xpath('//*[@id="' + key + '"]')[0]))

    def test_runtime_only_alt_translation(self):
        old = R.original(R.ASSET + '/home-family-practical.js').decode().splitlines()
        new = R.runtime().decode().splitlines()
        delta = [(a, b) for a, b in zip(old, new) if a != b]
        self.assertEqual(len(old), len(new))
        self.assertEqual(len(delta), 1)
        self.assertIn('showroomAlt:', delta[0][0])
        self.assertIn('showroomAlt:', delta[0][1])

    def test_no_forms_or_business_logic_added(self):
        self.assertNotRegex(self.after, r'<(?:form|input|textarea|script)\b')
        self.assertEqual(self.after.count('data-i18n="showroomCaption"'), 1)

    def test_drift_or_duplicate_fails_closed(self):
        for value in (self.before + self.before, self.before.replace('data-hhf-ohegy=""', ''), self.before.replace('data-i18n-alt="showroomAlt"', '')):
            with self.assertRaises(RuntimeError):
                R.rewrite(value, R.PUBLIC_PHOTO)


if __name__ == '__main__':
    unittest.main()
