"""Offline existing-source update and owned rollback checks."""
import contextlib
import importlib.util
import io
from pathlib import Path
import socket
import unittest
from unittest.mock import patch

HERE=Path(__file__).resolve().parent
def load(name,file):
    spec=importlib.util.spec_from_file_location(name,HERE/file)
    m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
M=load('lower_test','2026-10-19-update-home-hero-lower.py')
R=load('lower_fake','2026-10-19-test-home-assistant-inline-update.py')


class Tests(unittest.TestCase):
    def setUp(self):
        stack=contextlib.ExitStack();self.addCleanup(stack.close)
        stack.enter_context(patch.object(socket.socket,'connect',side_effect=AssertionError('NO NETWORK')))
        self.b=M.release();self.r=R.Remote();self.r.files[M.TARGET]=self.b[0]
        self.r.files['/customer']=b'untouched'
        stack.enter_context(patch.object(M.H,'Remote',return_value=self.r))
        stack.enter_context(patch.object(M.V,'state',return_value={'protected':'same'}))
        stack.enter_context(patch.object(M.Q,'get',return_value=(b'<html>baseline</html>','text/html')))
        self.public=stack.enter_context(patch.object(M,'public',return_value={'native_form_unchanged':True}))
        stack.enter_context(patch.object(M.D,'validate_native',return_value={'offer_checks':35,'antispam_checks':17}))
        self.output=stack.enter_context(contextlib.redirect_stdout(io.StringIO()))
    def backup(self):return next(p for p in self.r.dirs if p.startswith(M.PREFIX))

    def test_exact_source_only_offset_and_version(self):
        old,new=self.b[:2]
        self.assertEqual(new.replace(M.RULE,b'',1).replace(b'Version: 1.0.1',b'Version: 1.0.0',1),old)

    def test_backup_deploy_verify_restore_no_customer_write(self):
        M.operate(self.b)
        self.assertEqual(self.r.files[self.backup()+'/original.php'],self.b[0])
        self.assertEqual(self.r.renames,[M.TARGET])
        M.operate(self.b,self.backup());M.operate(self.b,self.backup(),True)
        self.assertEqual(self.r.files[M.TARGET],self.b[0]);self.assertEqual(self.r.files['/customer'],b'untouched')
        self.assertNotIn(M.D.LOCK,self.r.dirs)

    def test_public_failure_restores_only_owned_source(self):
        self.public.side_effect=RuntimeError('public failed')
        with self.assertRaisesRegex(RuntimeError,'public failed'):M.operate(self.b)
        self.assertEqual(self.r.files[M.TARGET],self.b[0]);self.assertNotIn(M.D.LOCK,self.r.dirs)

    def test_foreign_source_refused_before_activation_and_recovery(self):
        self.r.files[M.TARGET]=b'foreign'
        with self.assertRaisesRegex(RuntimeError,'ownership'):M.operate(self.b)
        with self.assertRaisesRegex(RuntimeError,'Foreign source'):M.restore(self.r,self.b[0],self.b[1],(0o644,1608,1611))
        self.assertEqual(self.r.files[M.TARGET],b'foreign');self.assertEqual(self.r.renames,[])

    def test_offline_and_gate_before_remote(self):
        with patch('sys.argv',['lower.py']):M.main()
        self.assertIn('OFFLINE_NO_NETWORK',self.output.getvalue())
        with patch('sys.argv',['lower.py','--deploy']),self.assertRaisesRegex(RuntimeError,'aggregate'):M.main()
        self.assertEqual(self.r.renames,[])


if __name__=='__main__':unittest.main(verbosity=2)
