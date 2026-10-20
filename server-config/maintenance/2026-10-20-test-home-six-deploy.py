"""Offline source-only recovery and collision tests; sockets disabled."""
import contextlib
import importlib.util
import io
from pathlib import Path
import re
import socket
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parent


def load(name, file):
    spec = importlib.util.spec_from_file_location(name, HERE/file)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


M = load('six_transport_test', '2026-10-20-deploy-home-six.py')
F = load('six_fake_remote', '2026-10-19-test-home-scroll-play-update.py')


class Tests(unittest.TestCase):
    def setUp(self):
        stack = contextlib.ExitStack()
        self.addCleanup(stack.close)
        stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NO NETWORK')))
        self.bundle = M.release()
        self.edits = self.bundle[0]
        self.r = F.Remote(self.edits)
        self.r.files['/protected/customer-record'] = b'never restore customer data'
        stack.enter_context(patch.object(M.H, 'Remote', return_value=self.r))
        stack.enter_context(patch.object(M.S, 'state', return_value={'protected':'unchanged'}))
        stack.enter_context(patch.object(M.Q, 'get', return_value=(b'<html>baseline</html>', 'text/html')))
        self.public = stack.enter_context(patch.object(M.S, 'public', return_value={'cards':6}))
        stack.enter_context(patch.object(M.D, 'validate_native', return_value={'offer_checks':35,'antispam_checks':17}))
        self.output = stack.enter_context(contextlib.redirect_stdout(io.StringIO()))
        M.S.JS, M.S.MANIFEST = M.JS, M.MANIFEST
        M.S.PREFIX = M.H.BACKUPS+'/home-six-'
        M.S.release = M.release

    def backup(self):
        return next(p for p in self.r.dirs if re.fullmatch(re.escape(M.S.PREFIX)+r'[a-f0-9]{32}',p))

    def test_four_files_dependencies_first_controller_last(self):
        self.assertEqual(tuple(self.edits), tuple(M.ASSET+'/'+n for n in (M.BODY,M.JS,M.MANIFEST))+(M.HOME,))
        self.assertTrue(all(old is None for p,(old,new) in self.edits.items() if p != M.HOME))
        self.assertIn(M.D.ASSET+'/home-family-scroll-play.js', self.bundle[2])
        self.assertIn('zz-harmat-home-assistant-inline.php', self.bundle[2])
        self.assertIn(b'id="harmat-home-assistant-inline-script"', self.bundle[2]['zz-harmat-home-assistant-inline.php'])

    def test_release_verify_source_only_rollback(self):
        M.S.operate(self.bundle)
        self.assertEqual(self.r.renames, list(self.edits))
        M.S.operate(self.bundle, self.backup())
        M.S.operate(self.bundle, self.backup(), True)
        self.assertEqual(self.r.files[M.HOME], self.edits[M.HOME][0])
        for p in self.edits:
            if p != M.HOME:
                self.assertNotIn(p, self.r.files)
        self.assertEqual(self.r.files['/protected/customer-record'], b'never restore customer data')
        self.assertNotIn(M.D.LOCK, self.r.dirs)

    def test_failed_public_proof_recovers_controller_and_only_owned_assets(self):
        self.public.side_effect = RuntimeError('proof failed')
        with self.assertRaisesRegex(RuntimeError, 'proof failed'):
            M.S.operate(self.bundle)
        self.assertEqual(self.r.files[M.HOME], self.edits[M.HOME][0])
        self.assertNotIn(M.ASSET+'/'+M.BODY, self.r.files)

    def test_dependency_collision_stops_before_activation(self):
        self.r.files[M.ASSET+'/'+M.BODY] = b'foreign body'
        with self.assertRaisesRegex(RuntimeError, 'already exists'):
            M.S.operate(self.bundle)
        self.assertEqual(self.r.renames, [])
        self.assertEqual(self.r.files[M.ASSET+'/'+M.BODY], b'foreign body')

    def test_foreign_controller_keeps_all_dependencies(self):
        for p,(old,new) in self.edits.items():
            self.r.files[p] = new
        self.r.files[M.HOME] = b'foreign controller'
        with self.assertRaisesRegex(RuntimeError, 'Foreign source retained'):
            M.S.restore(self.r, self.edits, {p:(0o644,1608,1611) for p in self.edits})
        self.assertEqual(self.r.files[M.ASSET+'/'+M.JS], self.edits[M.ASSET+'/'+M.JS][1])

    def test_partial_install_can_remove_only_own_new_body(self):
        p = M.ASSET+'/'+M.BODY
        self.r.files[p] = self.edits[p][1]
        M.S.restore(self.r,self.edits,{p:(0o644,1608,1611) for p in self.edits})
        self.assertNotIn(p,self.r.files)
        self.assertEqual(self.r.files[M.HOME],self.edits[M.HOME][0])

    def test_staging_lint_failure_never_activates(self):
        with patch.object(self.r,'lint',side_effect=RuntimeError('lint failed')):
            with self.assertRaisesRegex(RuntimeError,'lint failed'):
                M.S.operate(self.bundle)
        self.assertEqual(self.r.renames,[])

    def test_offline_default_and_exact_aggregate_gate(self):
        with patch('sys.argv',['six.py']):
            M.S.main()
        self.assertIn('OFFLINE_NO_NETWORK',self.output.getvalue())
        with patch('sys.argv',['six.py','--deploy']), self.assertRaisesRegex(RuntimeError,'aggregate'):
            M.S.main()
        self.assertEqual(self.r.renames,[])

    def test_real_staged_fixture_output_and_body_lint_are_required(self):
        with patch.object(self.r,'lint') as lint, patch.object(self.r,'run',return_value='PASS 136 presentation PHP assertions\n'), patch.object(M,'native_validation',return_value={'offer_checks':35}):
            result = M.validate_native(self.r,'/private/stage')
            self.assertEqual(result['six_card_checks'],136)
            lint.assert_called_once_with('/private/stage/harmat-home-family/body-six.php')
        with patch.object(self.r,'run',return_value='PASS 135 presentation PHP assertions'), self.assertRaisesRegex(RuntimeError,'fixture failed'):
            M.validate_native(self.r,'/private/stage')

    def test_business_snapshot_excludes_only_three_native_presentation_cache_keys(self):
        candidate = M.business_snapshot_code()
        self.assertEqual(candidate.replace(M.CACHE_PREDICATE,"m.meta_key<>'_elementor_element_cache'",1), M.D.I.SNAPSHOT)
        self.assertIn("p.post_type IN ('property','harmat_offer_lead')",candidate)
        self.assertIn("harmat_sales_deals_v1",candidate)
        self.assertNotIn('DELETE',candidate)
        self.assertNotIn('UPDATE',candidate)


if __name__ == '__main__':
    unittest.main(verbosity=2)
