"""Offline release ordering, protected UI and exact source gates."""
import importlib.util
from pathlib import Path
import subprocess
import sys
import unittest

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('loop',HERE/'2026-10-20-deploy-home-loop.py')
M=importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class LoopReleaseTests(unittest.TestCase):
    def test_three_source_bundle_order(self):
        edits,helpers,protected,pins,digest=M.release()
        self.assertEqual(list(edits),[M.ASSET+'/'+M.JS,M.ASSET+'/'+M.MANIFEST,M.HOME])
        self.assertIsNone(edits[M.ASSET+'/'+M.JS][0])
        self.assertIsNone(edits[M.ASSET+'/'+M.MANIFEST][0])
        self.assertEqual(M.sha(edits[M.HOME][0]),M.OLD)
        self.assertEqual(M.sha(edits[M.HOME][1]),M.PINS[M.D.MAIN])
        self.assertEqual(len(digest),64)

    def test_existing_ui_pinned(self):
        protected=M.release()[2]
        for name in ('zz-harmat-home-apartment-rotation.php','zz-harmat-home-assistant-inline.php',
                     'zz-harmat-home-video-fullscreen.php','harmat-home-family/body-six.php',
                     'harmat-home-family/home-family-six-r2.js'):
            self.assertIn(name,protected)
        self.assertNotIn(M.D.MAIN,protected)
        self.assertEqual(M.sha(protected['harmat-migrated-snippets.php']),
                         'f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077')

    def test_controller_changes_no_public_markup(self):
        old,new=M.release()[0][M.HOME]
        self.assertEqual(old[old.index(b'function harmat_home_family_output'):old.index(b'        foreach (array(\'pannellum.js\'')],
                         new[new.index(b'function harmat_home_family_output'):new.index(b'        foreach (array(\'pannellum.js\'')])

    def test_main_boundary(self):
        with self.assertRaises(Exception): M.main_html('no main')
        main='<main id="main" class="site-main"><p>Kept</p></main>'
        self.assertEqual(M.main_html('prefix'+main+'suffix'),main)
        with self.assertRaises(Exception): M.main_html(main+main)

    def test_offline_default(self):
        r=subprocess.run([sys.executable,str(HERE/'2026-10-20-deploy-home-loop.py')],capture_output=True,text=True)
        self.assertEqual(r.returncode,0)
        self.assertIn('OFFLINE_NO_NETWORK',r.stdout)

    def test_digest_gate_before_connect(self):
        r=subprocess.run([sys.executable,str(HERE/'2026-10-20-deploy-home-loop.py'),'--deploy','--expect','bad'],capture_output=True,text=True)
        self.assertNotEqual(r.returncode,0)
        self.assertIn('Exact reviewed aggregate required before connecting',r.stderr)

    def test_current_verifier_excludes_only_post_release_crm_aggregate(self):
        import copy
        spec=importlib.util.spec_from_file_location('loop_verify',HERE/'2026-10-20-verify-home-loop-current.py')
        verifier=importlib.util.module_from_spec(spec)
        spec.loader.exec_module(verifier)
        value={'protected':{'crm_options':'old','properties':'stable','appointments':'stable'},
               'sources':{'native':'same'},'pages':{'home':'same'}}
        changed=copy.deepcopy(value)
        changed['protected']['crm_options']='new'
        self.assertEqual(verifier.non_crm(value),verifier.non_crm(changed))
        for key in ('properties','appointments'):
            other=copy.deepcopy(changed);other['protected'][key]='different'
            self.assertNotEqual(verifier.non_crm(value),verifier.non_crm(other))
        changed['sources']['native']='different'
        self.assertNotEqual(verifier.non_crm(value),verifier.non_crm(changed))
        self.assertEqual(value['protected']['crm_options'],'old','never mutate saved baseline')


if __name__=='__main__': unittest.main()
