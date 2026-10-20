"""Offline exact-delta, image identity, and retained-content checks."""
import importlib.util
from pathlib import Path
import subprocess
import unittest

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('roof_garden',HERE/'2026-10-20-update-home-roof-garden.py')
M=importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class RoofGarden(unittest.TestCase):
    def setUp(self): self.parts=M.bundle()

    def test_exact_delta(self): M.validate_delta(*self.parts[:2])

    def test_foreign_candidate(self):
        with self.assertRaises(RuntimeError): M.validate_delta(self.parts[0],self.parts[1]+b' ')

    def test_foreign_baseline(self):
        with self.assertRaises(RuntimeError): M.validate_delta(self.parts[0]+b' ',self.parts[1])

    def test_image_pin_configuration(self):
        configured=M.configure()
        self.assertEqual(configured.IMAGE_MIME,'image/webp')
        self.assertEqual(configured.IMAGE_BYTES,271163)
        self.assertEqual(len(configured.IMAGE_SHA),64)
        self.assertTrue(configured.IMAGE_URL.startswith('https://harmat22.hu/wp-content/uploads/2026/02/'))

    def test_existing_translation_keys_and_lazy_image(self):
        js=self.parts[2][M.D.ASSET+'/home-family-six-r2.js'].decode()
        self.assertIn('rendering:',js)
        self.assertIn('data-i18n-alt="rendering"',M.NEW_FIGURE)
        self.assertIn('data-i18n="rendering"',M.NEW_FIGURE)
        self.assertNotIn('Helyszíni',M.NEW_FIGURE)
        self.assertIn('loading="lazy" decoding="async"',M.NEW_FIGURE)

    def test_render_retains_previous_work_and_native_form(self):
        rendered=subprocess.check_output(['C:/tools/php-8.5.6/php.exe',str(HERE/M.D.TEST),
                                         str(M.ROOT/'wp-mu-plugins'/M.D.MAIN),'--render']).decode()
        self.assertEqual(rendered.count(M.NEW_FIGURE),1)
        self.assertNotIn(M.OLD_FIGURE,rendered)
        self.assertIn('href="https://harmat22.hu/virtualis-lakasvalaszto/"',rendered)
        self.assertIn('grid-template-rows:subgrid',rendered)
        self.assertIn('assets/harmat-3d/xgt_5.jpg',rendered)
        self.assertIn('<form id="h22-offer-form">',rendered)
        self.assertEqual(rendered.count('class="hhf-apartment"'),6)


if __name__=='__main__': unittest.main()
