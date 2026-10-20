"""Offline release safety checks. No browser or network emulation claims."""
import importlib.util
import json
from pathlib import Path
import socket
import subprocess
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('footer_r2',HERE/'2026-10-20-deploy-footer-r2.py')
R = importlib.util.module_from_spec(spec)
spec.loader.exec_module(R)
F = R.F


class FooterTransport(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.block = patch.object(socket.socket,'connect',side_effect=AssertionError('NO_NETWORK'))
        cls.block.start()
        cls.bundle = F.release()

    @classmethod
    def tearDownClass(cls):
        cls.block.stop()

    def test_exact_mixed_eol_and_frozen_hashes(self):
        old,new,_,_,pins,_ = self.bundle
        self.assertEqual(F.sha(old),F.OLD_RAW)
        self.assertEqual(F.sha(new.replace(b'\r\n',b'\n')),F.NEW_LF)
        self.assertEqual(pins['new'],'f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077')
        self.assertEqual(new.count(b'\n')-new.count(b'\r\n'),4)
        self.assertEqual(old.splitlines(keepends=True)[175:178],new.splitlines(keepends=True)[175:178])

    def test_protected_owners(self):
        protected = self.bundle[3]
        self.assertEqual(len(protected),17)
        self.assertEqual(F.sha(protected['zz-harmat-home-family.php']),'7d823dc258bd7e64ede2586455ce222000632d2f47aa5523df779542d0bf3dab')
        self.assertNotIn(F.NAME,protected)

    def test_pure_preview_no_form_nonce_or_native_inline_scripts(self):
        value = json.loads(subprocess.check_output(['C:/tools/php-8.5.6/php.exe',str(HERE/F.TEST),str(F.ROOT/'wp-mu-plugins'/F.NAME),'--render']))
        home = '<html><head><link rel="stylesheet" href="https://harmat22.hu/style.css"><style id="harmat-migrated-public-layout-css">OLD</style></head><body class="home"><form><input name="_nonce" value="SECRET"></form><script>PRIVATE()</script></body></html>'
        result = F.preview_document(home,value).decode()
        self.assertIn('noindex,nofollow,noarchive',result)
        self.assertIn('runtime.js',result)
        for forbidden in ('SECRET','PRIVATE()','<form','<input','<iframe','>OLD<'):
            self.assertNotIn(forbidden,result)
        self.assertEqual(result.count('id="colophon"'),1)

    def test_staged_fixture_strict_output(self):
        class Remote:
            def run(self,cmd):return 'PASS footer: 485 assertions; pure extraction, no WordPress/network.\n'
        self.assertEqual(F.fixture(Remote(),'/private')['checks'],485)
        with patch.object(Remote,'run',return_value='PASS footer: 484 assertions; pure extraction, no WordPress/network.\n'):
            with self.assertRaises(Exception):F.fixture(Remote(),'/private')

    def test_invalid_backup_before_connection(self):
        with patch.object(F.H,'Remote',side_effect=AssertionError('CONNECTED')):
            with self.assertRaisesRegex(Exception,'Exact backup required'):
                F.operate(self.bundle,F.PREFIX+'../bad','deploy')

    def test_activation_gate_before_connection(self):
        with patch('sys.argv',['footer','--deploy',F.PREFIX+'a'*32,'--expect',self.bundle[5]]),patch.object(F.H,'Remote',side_effect=AssertionError('CONNECTED')):
            with self.assertRaisesRegex(Exception,'Actual desktop/mobile preview gate required'):F.main()

    def test_wrong_aggregate_before_connection(self):
        with patch('sys.argv',['footer','--prepare','--expect','wrong']),patch.object(F.H,'Remote',side_effect=AssertionError('CONNECTED')):
            with self.assertRaisesRegex(Exception,'Exact reviewed aggregate required'):F.main()

    def test_saved_metadata_roundtrip_matches_atomic_helper_tuple(self):
        value = json.loads(json.dumps((0o664,1608,1611)))
        self.assertEqual(F.saved_metadata(value),(0o664,1608,1611))
        self.assertIsInstance(F.saved_metadata(value),tuple)
        for bad in (None,[],['664',1608,1611],[True,1608,1611]):
            with self.assertRaises(Exception):F.saved_metadata(bad)


if __name__ == '__main__':unittest.main()
