"""Offline exact-delta and public-contract tests, synthetic data only."""
import importlib.util
from pathlib import Path
import subprocess
import unittest

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('contact_image',HERE/'2026-10-20-update-home-contact-image.py')
M=importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class ContactImage(unittest.TestCase):
    def setUp(self):
        self.bundle=M.bundle()

    def test_exact_controller_delta(self):
        M.validate_delta(*self.bundle[:2])

    def test_foreign_candidate_refused(self):
        with self.assertRaises(RuntimeError): M.validate_delta(self.bundle[0],self.bundle[1]+b' ')

    def test_foreign_baseline_refused(self):
        with self.assertRaises(RuntimeError): M.validate_delta(self.bundle[0]+b' ',self.bundle[1])

    def test_retained_assets(self):
        protected=self.bundle[2]
        self.assertIn(M.D.ASSET+'/home-family-six-r2.js',protected)
        self.assertIn('harmat-migrated-snippets.php',protected)
        self.assertIn('zz-harmat-home-assistant-inline.php',protected)
        self.assertIn('zz-harmat-home-video-fullscreen.php',protected)

    def test_image_description_uses_existing_translations(self):
        runtime=self.bundle[2][M.D.ASSET+'/home-family-six-r2.js'].decode()
        self.assertIn('panoramaGallery5:',runtime)
        self.assertIn('rendering:',runtime)
        self.assertNotIn('locationCaption',M.NEW_FIGURE)
        self.assertIn('data-i18n="rendering"',M.NEW_FIGURE)
        self.assertIn('loading="lazy" decoding="async"',M.NEW_FIGURE)

    def test_exact_rendered_main(self):
        original='<main id="main" class="site-main">'+M.OLD_FIGURE+'</main>'
        changed=original.replace(M.OLD_FIGURE,M.NEW_FIGURE)
        self.assertEqual(M.main_html(changed),changed)
        with self.assertRaises(RuntimeError): M.main_html(changed+changed)

    def test_public_proof_rejects_unrelated_main_change(self):
        original=('<!doctype html><html><head></head><body><main id="main" class="site-main">'
                  +M.OLD_FIGURE+'</main></body></html>').encode()
        changed=original.replace(M.OLD_FIGURE.encode(),M.NEW_FIGURE.encode()).replace(b'</main>',b'<div>extra</div></main>')
        get,assert_home=M.Q.get,M.D.assert_home
        try:
            M.Q.get=lambda url:(changed,'text/html')
            M.D.assert_home=lambda text,baseline:{}
            with self.assertRaisesRegex(RuntimeError,'exceeds exact figure'): M.public_proof(original,True)
        finally: M.Q.get,M.D.assert_home=get,assert_home

    def test_synthetic_controller_and_contact_figure(self):
        result=subprocess.check_output(['C:/tools/php-8.5.6/php.exe',str(HERE/M.D.TEST),
                                       str(M.ROOT/'wp-mu-plugins'/M.D.MAIN),'--render'])
        text=result.decode()
        self.assertEqual(text.count(M.NEW_FIGURE),1)
        self.assertNotIn(M.OLD_FIGURE,text)
        self.assertIn('<form id="h22-offer-form">',text)
        self.assertIn('window.nativeSales={keep:true};',text)
        self.assertEqual(text.count('class="hhf-apartment"'),6)


if __name__=='__main__': unittest.main()
