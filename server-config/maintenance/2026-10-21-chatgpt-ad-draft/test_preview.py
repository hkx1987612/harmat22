"""Validate the local ad handoff without live account or website requests."""
import hashlib
import json
import unittest
from urllib.parse import parse_qs, urlsplit

from lxml import html
from PIL import Image, ImageStat

from build import ROOT, OUT, COPY, VARIANTS


class AdPreviewTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.data = json.loads((OUT / 'review-manifest.json').read_text(encoding='utf-8'))
        cls.tree = html.fromstring((OUT / 'index.html').read_bytes())

    def test_not_publication(self):
        self.assertEqual(self.data['status'], 'LOCAL_DRAFT_NOT_UPLOADED_NOT_APPROVED_FOR_PUBLICATION')
        self.assertTrue(self.data['native_platform_preview_required'])
        self.assertFalse(self.data['website_modified'])

    def test_one_budget_not_two(self):
        self.assertEqual(self.data['days'], 7)
        self.assertEqual(self.data['media_budget_total_huf'], 38500)
        self.assertEqual(7 * 5500, 38500)
        self.assertIsNone(self.data['start_date'])
        self.assertIsNone(self.data['end_date'])

    def test_copy_limits(self):
        self.assertLessEqual(len(COPY['headline']), 50)
        self.assertLessEqual(len(COPY['description']), 100)
        self.assertIn('látványterv', COPY['description'])

    def test_no_unconfirmed_promotions_or_area(self):
        text = COPY['headline'] + COPY['description']
        for token in ('%', 'm²', '2028', 'garantált', 'CSOK', 'ajándék'):
            self.assertNotIn(token, text)

    def test_existing_project_sources_unchanged(self):
        for filename, item in self.data['source_pins'].items():
            source = ROOT / item['source']
            self.assertEqual(hashlib.sha256(source.read_bytes()).hexdigest(), item['sha256'])
            self.assertEqual(source.read_bytes(), (OUT / 'assets' / filename).read_bytes())

    def test_square_exports_nonblank(self):
        for variant in VARIANTS:
            with Image.open(OUT / (variant['id'] + '-1200.jpg')) as image:
                self.assertEqual(image.format, 'JPEG')
                self.assertEqual(image.size, (1200, 1200))
                self.assertGreater(max(ImageStat.Stat(image.convert('RGB')).stddev), 30)

    def test_no_submission_surfaces(self):
        self.assertEqual(self.tree.xpath('//form|//input|//iframe|//script|//video'), [])

    def test_all_media_local(self):
        for url in self.tree.xpath('//img/@src|//link[@rel="stylesheet"]/@href'):
            self.assertFalse(urlsplit(url).scheme)
            self.assertTrue((OUT / url).is_file())

    def test_native_like_cards_are_labelled(self):
        ads = self.tree.xpath('//article[@class="ad"]')
        self.assertEqual(len(ads), 2)
        self.assertTrue(all('Hirdetés' in ad.text_content() for ad in ads))
        self.assertIn('不是平台实际投放截图', self.tree.text_content())

    def test_landing_and_tracking_only_in_links(self):
        links = self.tree.xpath('//a[@class="destination"]/@href')
        self.assertEqual(len(links), 2)
        for url in links:
            parts = urlsplit(url)
            self.assertEqual(parts.scheme, 'https')
            self.assertEqual(parts.netloc, 'harmat22.hu')
            self.assertEqual(parts.path, '/lakaskereso/')
            self.assertEqual(parse_qs(parts.query)['utm_source'], ['chatgpt'])

    def test_noindex_and_no_connections(self):
        self.assertIn('noindex', self.tree.xpath('//meta[@name="robots"]/@content')[0])
        self.assertIn("connect-src 'none'", self.tree.xpath('//meta[@http-equiv="Content-Security-Policy"]/@content')[0])

    def test_rendering_labels_and_full_frame(self):
        css = (OUT / 'artboard.css').read_text(encoding='utf-8')
        self.assertIn('object-fit:contain', css)
        for variant in VARIANTS:
            tree = html.fromstring((OUT / (variant['id'] + '-artboard.html')).read_bytes())
            self.assertIn('NEM AZ ELKÉSZÜLT ÁLLAPOT FÉNYKÉPE', tree.text_content())
            self.assertEqual(tree.xpath('//img[@class="project"]/@src'), ['assets/' + variant['file']])


if __name__ == '__main__':
    unittest.main()
