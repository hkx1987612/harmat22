"""Offline tests for the exact two-rule alignment patch and retained content."""
import importlib.util
from pathlib import Path
import subprocess
import unittest

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('everyday_alignment',HERE/'2026-10-20-align-home-everyday.py')
M=importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class EverydayAlignment(unittest.TestCase):
    def setUp(self): self.parts=M.bundle()

    def test_exact_delta(self): M.validate_delta(*self.parts[:2])

    def test_foreign_baseline_refused(self):
        with self.assertRaises(RuntimeError): M.validate_delta(self.parts[0]+b' ',self.parts[1])

    def test_foreign_candidate_refused(self):
        with self.assertRaises(RuntimeError): M.validate_delta(self.parts[0],self.parts[1]+b' ')

    def test_scope_and_mobile_fallback(self):
        self.assertEqual(len(M.RULES),2)
        for rule in M.RULES:
            self.assertIn('@media(min-width:621px)',rule)
            self.assertNotIn('!important',rule)
            self.assertNotIn('font-size',rule)
        self.assertIn('@supports(grid-template-rows:subgrid)',M.RULES[1])
        self.assertIn('grid-template-columns:repeat(2,minmax(0,1fr))',M.RULES[0])
        self.assertIn('padding-top:0',M.RULES[0])
        self.assertIn('grid-row:span 3',M.RULES[1])

    def test_style_exact_and_refuse_extra(self):
        M.validate_style('existing','existing'+''.join(M.RULES),True)
        M.validate_style('existing','existing',False)
        with self.assertRaises(RuntimeError): M.validate_style('existing','existing'+''.join(M.RULES)+'body{}',True)

    def test_main_and_media_unchanged(self):
        rendered=subprocess.check_output(['C:/tools/php-8.5.6/php.exe',str(HERE/M.D.TEST),
                                         str(M.ROOT/'wp-mu-plugins'/M.D.MAIN),'--render']).decode()
        self.assertEqual(rendered.count(M.M.NEW_FIGURE),1)
        self.assertEqual(rendered.count('class="hhf-everyday-story"'),2)
        self.assertIn('project-courtyard.jpg',rendered)
        self.assertIn('project-living-room.jpg',rendered)
        self.assertIn('<form id="h22-offer-form">',rendered)
        self.assertEqual(rendered.count('class="hhf-apartment"'),6)
        style=M.inline_style(rendered)
        self.assertTrue(style.endswith(''.join(M.RULES)))
        for rule in M.RULES: self.assertEqual(style.count(rule),1)

    def test_protected_assets(self):
        protected=self.parts[2]
        self.assertIn(M.D.ASSET+'/body-six.php',protected)
        self.assertIn(M.D.ASSET+'/home-family-six-r2.js',protected)
        self.assertIn('harmat-migrated-snippets.php',protected)
        self.assertIn('zz-harmat-home-video-fullscreen.php',protected)


if __name__=='__main__': unittest.main()
