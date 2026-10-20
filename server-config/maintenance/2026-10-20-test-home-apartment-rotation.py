"""Offline deployment boundaries for the removable homepage rotation."""
import importlib.util
from pathlib import Path
import subprocess
import sys
import unittest

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('rotation',HERE/'2026-10-20-deploy-home-apartment-rotation.py')
M=importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class RotationReleaseTests(unittest.TestCase):
    def test_exact_public_source(self):
        source,protected,helpers,descriptor,digest=M.release()
        self.assertEqual(M.sha(source),M.NEW)
        self.assertNotIn(M.MAIN,protected)
        self.assertEqual(M.sha(protected[M.HERO_MAIN]),M.M.NEW)
        self.assertEqual(M.sha(protected[M.D.MAIN]),M.M.R.NEW_SHA)
        self.assertEqual(set(helpers),{M.TEST,M.D.CHECKER})
        self.assertEqual(digest,M.sha(M.Q.P.encoded(descriptor)))

    def test_configure_keeps_prior_bundle_frozen(self):
        before=M.release()
        module=M.configure()
        self.assertEqual(module.TARGET,M.TARGET)
        self.assertEqual(M.release(),before)

    def test_resources_only_addon(self):
        source=M.release()[0].decode()
        self.assertEqual(len(M.blocks(source)),2)
        self.assertNotIn('wp_remote_',source)
        self.assertNotIn('register_rest_route',source)
        self.assertIn("add_action('wp_footer','harmat_har_footer',1250);",source)
        with self.assertRaises(Exception): M.blocks(source+source)

    def test_native_assets_and_raw_footer_retained(self):
        protected=M.release()[1]
        for name in ('harmat-home-family/body-six.php','harmat-home-family/home-family-six-r2.js',
                     'zz-harmat-home-video-fullscreen.php','harmat-assistant-presentation/assistant-presentation.js'):
            self.assertIn(name,protected)
        self.assertEqual(M.sha(protected['harmat-migrated-snippets.php']),
                         'f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077')

    def test_offline_default(self):
        result=subprocess.run([sys.executable,str(HERE/'2026-10-20-deploy-home-apartment-rotation.py')],capture_output=True,text=True)
        self.assertEqual(result.returncode,0)
        self.assertIn('OFFLINE_NO_NETWORK',result.stdout)

    def test_digest_gate_before_network(self):
        result=subprocess.run([sys.executable,str(HERE/'2026-10-20-deploy-home-apartment-rotation.py'),
                               '--deploy','--expect','bad'],capture_output=True,text=True)
        self.assertNotEqual(result.returncode,0)
        self.assertIn('Exact reviewed aggregate required before connecting',result.stderr)


if __name__=='__main__': unittest.main()
