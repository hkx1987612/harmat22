"""Local preview integrity only; no live GET, form request or deployment."""
import hashlib
import json
from pathlib import Path
import unittest
from urllib.parse import urlsplit
from lxml import html

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'outputs/2026-10-21-image-preview/v1'
M=Path(__file__).with_name('2026-10-21-image-preview-manifest.json')
DATA=json.loads(M.read_text(encoding='utf-8'))
PINS=json.loads((OUT/'asset-pins.json').read_text(encoding='utf-8'))
TREE=html.fromstring((OUT/'index.html').read_bytes())

class Tests(unittest.TestCase):
    def test_preview_not_release(self):
        self.assertEqual(DATA['status'],'LOCAL_REVIEW_ONLY_NOT_APPROVED_FOR_DEPLOYMENT')
    def test_manifest_identity(self):
        self.assertEqual(PINS['manifest_sha256'],hashlib.sha256(M.read_bytes()).hexdigest())
    def test_six_pairs_and_three_references(self):
        self.assertEqual(len(DATA['cases']),6)
        self.assertEqual(len(DATA['references']),3)
        self.assertEqual(len(TREE.xpath('//figure')),15)
    def test_no_submission_or_tracking_surfaces(self):
        self.assertEqual(TREE.xpath('//form|//iframe|//input|//select|//textarea|//video'),[])
    def test_all_rendered_media_local(self):
        source=Path(__file__).with_name('2026-10-21-image-preview')
        for name in ('preview.css','preview.js'):
            self.assertEqual((source/name).read_bytes(),(OUT/name).read_bytes())
        self.assertEqual((source/'serve.py').read_bytes(),(OUT.parent/'serve.py').read_bytes())
        for url in TREE.xpath('//img/@src|//script/@src|//link[@rel="stylesheet"]/@href'):
            with self.subTest(url=url):
                self.assertFalse(urlsplit(url).scheme)
                self.assertTrue((OUT/url).is_file())
    def test_images_not_changed(self):
        for name,item in PINS['images'].items():
            file=OUT/'assets'/name
            with self.subTest(name=name):
                self.assertEqual(file.stat().st_size,item['bytes'])
                self.assertEqual(hashlib.sha256(file.read_bytes()).hexdigest(),item['sha256'])
    def test_retained_local_originals(self):
        for item in [c['after'] for c in DATA['cases']]+DATA['references']:
            if 'local' in item:
                with self.subTest(file=item['file']):
                    self.assertEqual((ROOT/item['local']).read_bytes(),(OUT/'assets'/item['file']).read_bytes())
    def test_exact_property_mapping(self):
        for code in ('A1-F-L1','A4-F-L1','A3-F-L3'):
            case=next(c for c in DATA['cases'] if code in c['title'])
            self.assertEqual(case['after']['url'],'https://harmat22.hu/wp-content/uploads/2026/05/'+code+'-cn-floorplan-display.jpg')
    def test_bilingual_after_captions(self):
        for case in DATA['cases']:
            self.assertTrue(case['after']['hu'])
            self.assertTrue(case['after']['zh'])
            self.assertTrue(case['after']['alt_hu'])
    def test_effect_and_real_qualifiers(self):
        for key in ('about','gallery'):
            case=next(c for c in DATA['cases'] if c['id']==key)
            self.assertIn('Látványterv'.lower(),case['after']['hu'].lower())
            self.assertIn('并非',case['after']['zh'])
        park=next(r for r in DATA['references'] if r['id']=='park')
        self.assertIn('Nem a lakópark belső kertje',park['hu'])
        self.assertIn('日期',park['zh'])
    def test_noindex_and_no_external_connection(self):
        self.assertIn('noindex',TREE.xpath('//meta[@name="robots"]/@content')[0])
        csp=TREE.xpath('//meta[@http-equiv="Content-Security-Policy"]/@content')[0]
        self.assertIn("connect-src 'none'",csp)
        self.assertIn("form-action 'none'",csp)
    def test_image_controls_and_dimensions(self):
        self.assertEqual(len(TREE.xpath('//button[@data-image]')),15)
        self.assertTrue(all(n.get('aria-label','').startswith('放大：') for n in TREE.xpath('//button[@data-image]')))
        self.assertIn('object-fit:contain',(OUT/'preview.css').read_text(encoding='utf-8'))
    def test_no_area_inference(self):
        self.assertIn('No area edits; no coefficient inferred',DATA['area_scope'])
        self.assertIn('不默认阳台全额计入或按一半折算',TREE.text_content())

if __name__=='__main__':unittest.main()
