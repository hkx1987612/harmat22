"""Offline tests for the two-target TV update; no network or business writes."""
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
    value=importlib.util.module_from_spec(spec);spec.loader.exec_module(value);return value


M=load('tv_update_under_test','2026-10-19-update-home-tv-video.py')
S=load('tv_source_fake_remote','2026-10-19-test-payment-terms-update.py')


class Remote(S.Remote):
    def __init__(self,edits):
        super().__init__();self.dirs={M.H.BACKUPS,M.D.DIR};self.closed=False
        self.files.update({p:a for p,(a,b) in edits.items() if a is not None})
    def directory(self,path,private=False):
        if path not in self.dirs:raise FileNotFoundError(path)
    def mkdir(self,path):
        if path in self.dirs:raise FileExistsError(path)
        self.dirs.add(path)
    def rmdir(self,path):self.dirs.remove(path)
    def close(self):self.closed=True
    def run(self,command):return '{"status":"PASS","checks":42}'
    def rename(self,source,target):
        if target in self.files:raise FileExistsError(target)
        self.posix_rename(source,target)


class TVUpdateTests(unittest.TestCase):
    def setUp(self):
        stack=contextlib.ExitStack();self.addCleanup(stack.close)
        stack.enter_context(patch.object(socket.socket,'connect',side_effect=AssertionError('NO NETWORK')))
        self.bundle=M.release();self.edits=self.bundle[0];self.r=Remote(self.edits)
        self.r.files['/protected/native.php']=b'unchanged quote appointment CRM'
        stack.enter_context(patch.object(M.H,'Remote',return_value=self.r))
        stack.enter_context(patch.object(M.T,'state',return_value={'protected':'same'}))
        stack.enter_context(patch.object(M.Q,'get',return_value=(b'<html>baseline</html>','text/html')))
        self.public=stack.enter_context(patch.object(M,'public',return_value={'native_form_unchanged':True}))
        stack.enter_context(patch.object(M.D,'validate_native',return_value={'offer_checks':35,'antispam_checks':17}))
        self.output=stack.enter_context(contextlib.redirect_stdout(io.StringIO()))

    def backup(self):return next(p for p in self.r.dirs if p.startswith(M.PREFIX))

    def test_exact_scope_and_existing_absent_states(self):
        self.assertEqual(set(self.edits),{M.U.TARGET,M.TARGET})
        self.assertIsNone(self.edits[M.TARGET][0])
        old,new=self.edits[M.U.TARGET]
        rule=M.C.RULE.replace(b'max-height:calc(100svh - 108px)',b'max-height:none')
        self.assertEqual(new.replace(rule,M.C.RULE,1).replace(b'Version: 1.0.12',b'Version: 1.0.11',1),old)

    def test_backup_release_verify_and_owned_only_rollback(self):
        M.operate(self.bundle)
        self.assertEqual(self.r.renames,[M.U.TARGET,M.TARGET])
        self.assertEqual(self.r.files[self.backup()+'/original-home.php'],self.edits[M.U.TARGET][0])
        M.operate(self.bundle,self.backup())
        M.operate(self.bundle,self.backup(),True)
        self.assertNotIn(M.TARGET,self.r.files)
        self.assertEqual(self.r.files[M.U.TARGET],self.edits[M.U.TARGET][0])
        self.assertEqual(self.r.files['/protected/native.php'],b'unchanged quote appointment CRM')
        self.assertNotIn(M.D.LOCK,self.r.dirs)

    def test_failed_public_checks_recover_both_owned_sources(self):
        self.public.side_effect=RuntimeError('public failed')
        with self.assertRaisesRegex(RuntimeError,'public failed'):M.operate(self.bundle)
        self.assertNotIn(M.TARGET,self.r.files)
        self.assertEqual(self.r.files[M.U.TARGET],self.edits[M.U.TARGET][0])
        self.assertNotIn(M.D.LOCK,self.r.dirs)

    def test_foreign_addon_path_prevents_any_release(self):
        self.r.files[M.TARGET]=b'foreign'
        with self.assertRaisesRegex(RuntimeError,'already exists'):M.operate(self.bundle)
        self.assertEqual(self.r.files[M.TARGET],b'foreign')
        self.assertEqual(self.r.renames,[])

    def test_foreign_addon_retained_during_recovery(self):
        self.r.files[M.U.TARGET]=self.edits[M.U.TARGET][1]
        self.r.files[M.TARGET]=b'foreign'
        meta={p:(0o644,1608,1611) for p in self.edits}
        with self.assertRaisesRegex(RuntimeError,'Foreign source retained'):M.restore(self.r,self.edits,meta)
        self.assertEqual(self.r.files[M.TARGET],b'foreign')
        self.assertEqual(self.r.files[M.U.TARGET],self.edits[M.U.TARGET][0])

    def test_offline_and_gate_before_remote(self):
        with patch('sys.argv',['tv.py']):M.main()
        self.assertIn('OFFLINE_NO_NETWORK',self.output.getvalue())
        with patch('sys.argv',['tv.py','--deploy']),self.assertRaisesRegex(RuntimeError,'aggregate'):M.main()
        self.assertEqual(self.r.renames,[])


if __name__=='__main__':unittest.main(verbosity=2)
