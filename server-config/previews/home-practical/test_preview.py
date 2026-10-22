"""Offline invariants. Does not call WordPress or submit a customer form."""
import json
from pathlib import Path
import unittest

from lxml import html
from build import COPY, OUT, cls, digest


class PreviewTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls_):
        cls_.before = html.fromstring((OUT / 'before-main.html').read_text(encoding='utf-8'))
        cls_.after = html.fromstring((OUT / 'after-main.html').read_text(encoding='utf-8'))
        cls_.page = html.parse(str(OUT / 'index.html'))
        cls_.proof = json.loads((OUT / 'proof.json').read_text(encoding='utf-8'))

    def test_all_images_unchanged_in_order(self):
        fields = ('src', 'data-original-src', 'width', 'height', 'alt', 'loading')
        self.assertEqual([[e.get(k) for k in fields] for e in self.before.xpath('.//img')], [[e.get(k) for k in fields] for e in self.after.xpath('.//img')])

    def test_original_bytes(self):
        for image in self.proof['images']:
            self.assertEqual(digest(OUT / image['local']), image['sha256'])

    def test_card_facts_exact(self):
        before = cls(self.before, 'hhf-apartment')
        after = cls(self.after, 'hhf-apartment')
        self.assertEqual(len(after), 6)
        self.assertEqual([html.tostring(e) for e in before], [html.tostring(e) for e in after])

    def test_videos_exact(self):
        self.assertEqual([html.tostring(e) for e in self.before.xpath('.//video')], [html.tostring(e) for e in self.after.xpath('.//video')])

    def test_panorama_media_unchanged(self):
        for source in (self.before, self.after):
            viewer = source.get_element_by_id('hhf-panorama-viewer')
            self.assertEqual(len([k for k in viewer.attrib if k.startswith('data-cube-')]), 6)
        self.assertEqual(self.before.get_element_by_id('hhf-panorama-viewer').attrib, self.after.get_element_by_id('hhf-panorama-viewer').attrib)

    def test_actions_and_contacts_preserved(self):
        self.assertEqual(sorted(self.before.xpath('.//a/@href')), sorted(self.after.xpath('.//a/@href')))
        self.assertIn('mailto:ertekesites@harmat22.hu', self.after.xpath('.//a/@href'))
        self.assertIn('tel:+36300733375', self.after.xpath('.//a/@href'))

    def test_one_text_band_consolidated(self):
        self.assertEqual(len(cls(self.before, 'hhf-team')), 1)
        self.assertEqual(len(cls(self.after, 'hhf-team')), 0)
        self.assertEqual(len(cls(self.after, 'hhf-showroom-copy')[0].xpath('.//a[contains(@href,"magunkrol")]')), 1)

    def test_all_copy_and_zh_dictionary(self):
        runtime = (OUT / 'runtime-after.js').read_text(encoding='utf-8')
        for key, (hu, zh) in COPY.items():
            self.assertEqual(self.after.xpath('.//*[@data-i18n=$key]', key=key)[0].text, hu)
            self.assertIn(json.dumps(zh, ensure_ascii=False), runtime)

    def test_journal_exact(self):
        self.assertEqual(html.tostring(self.before.get_element_by_id('hhf-epitesi-naplo')), html.tostring(self.after.get_element_by_id('hhf-epitesi-naplo')))

    def test_no_forms_or_customer_service(self):
        self.assertFalse(self.page.xpath('//form|//input|//textarea|//iframe'))
        self.assertTrue(cls(self.after, 'harmat-local-ai-launch')[0].get('disabled') is not None)
        self.assertIn("form-action 'none'", self.page.xpath('//meta[@http-equiv="Content-Security-Policy"]/@content')[0])

    def test_resources_local_only(self):
        for element in self.page.getroot().iter():
            for attr in ('src', 'poster', 'data-src', 'data-hcu-video', 'data-hcu-poster'):
                value = element.get(attr)
                if value:
                    self.assertFalse(value.startswith(('https:', 'http:', '//')), value)

    def test_no_replacement_characters(self):
        for name in ('index.html', 'runtime-after.js', 'changes.html'):
            self.assertNotIn('\ufffd', (OUT / name).read_text(encoding='utf-8'))


if __name__ == '__main__':
    unittest.main()
