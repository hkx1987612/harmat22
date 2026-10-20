"""Offline release pins, source boundary and before-connect gates."""
import importlib.util
from pathlib import Path
import subprocess
import sys
import unittest

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('hero_assistant',HERE/'2026-10-20-update-home-hero-assistant.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class ReleaseTests(unittest.TestCase):
    def test_frozen_source(self):
        old,new,protected,helpers,descriptor,digest = M.release()
        self.assertEqual(M.sha(old),M.OLD)
        self.assertEqual(M.sha(new),M.NEW)
        self.assertEqual(M.sha(M.Q.P.encoded(descriptor)),digest)
        self.assertNotIn(M.U.V.MAIN,protected)
        self.assertEqual(M.sha(protected[M.D.MAIN]),M.R.NEW_SHA)
        self.assertEqual(set(helpers),{M.U.V.TEST,M.D.CHECKER})

    def test_home_native_and_footer_pinned(self):
        protected = M.release()[2]
        for name in ('harmat-home-family/body-six.php','harmat-home-family/home-family-six-r2.js',
                     'zz-harmat-home-video-fullscreen.php','zz-harmat-assistant-presentation.php',
                     'harmat-assistant-presentation/assistant-presentation.js'):
            self.assertIn(name,protected)
        self.assertEqual(M.sha(protected['harmat-migrated-snippets.php']),
                         'f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077')

    def test_same_php_scope_and_desktop_rules(self):
        old,new,*_ = M.release()
        for data in (old,new):
            self.assertIn(b"add_action('wp_footer', 'harmat_home_assistant_inline_footer', 1200);",data)
        self.assertEqual([l for l in old.splitlines() if l.startswith(b'@media(min-width:768px)')],
                         [l for l in new.splitlines() if l.startswith(b'@media(min-width:768px)')])

    def test_exact_public_blocks(self):
        old,new,*_ = M.release()
        self.assertNotEqual(M.asset_blocks(old.decode()),M.asset_blocks(new.decode()))
        with self.assertRaises(Exception): M.asset_blocks(new.decode()+new.decode())
        with self.assertRaises(Exception): M.asset_blocks('<html></html>')

    def test_offline_default(self):
        result = subprocess.run([sys.executable,str(HERE/'2026-10-20-update-home-hero-assistant.py')],capture_output=True,text=True)
        self.assertEqual(result.returncode,0)
        self.assertIn('OFFLINE_NO_NETWORK',result.stdout)

    def test_wrong_digest_stops_before_network(self):
        result = subprocess.run([sys.executable,str(HERE/'2026-10-20-update-home-hero-assistant.py'),
                                 '--deploy','--expect','invalid'],capture_output=True,text=True)
        self.assertNotEqual(result.returncode,0)
        self.assertIn('Exact reviewed aggregate required before connecting',result.stderr)


if __name__ == '__main__': unittest.main()
