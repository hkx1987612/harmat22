"""Offline source-order, collision and recovery tests. No network or form dispatch."""
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
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);return module


M=load('scroll_update_test','2026-10-19-update-home-scroll-play.py')
S=load('scroll_fake_remote_base','2026-10-19-test-payment-terms-update.py')


class Remote(S.Remote):
    def __init__(self,edits):
        super().__init__();self.dirs={M.H.BACKUPS,M.D.DIR,M.ASSET};self.closed=False
        self.files.update({path:old for path,(old,new) in edits.items() if old is not None})
    def directory(self,path,private=False):
        if path not in self.dirs:raise FileNotFoundError(path)
    def mkdir(self,path):
        if path in self.dirs:raise FileExistsError(path)
        self.dirs.add(path)
    def rmdir(self,path):self.dirs.remove(path)
    def close(self):self.closed=True
    def run(self,command):return '{"status":"PASS","checks":76}'
    def rename(self,source,target):
        if target in self.files:raise FileExistsError(target)
        self.posix_rename(source,target)


class Tests(unittest.TestCase):
    def setUp(self):
        stack=contextlib.ExitStack();self.addCleanup(stack.close)
        stack.enter_context(patch.object(socket.socket,'connect',side_effect=AssertionError('NO NETWORK')))
        self.bundle=M.release();self.edits=self.bundle[0];self.r=Remote(self.edits)
        self.r.files['/protected/customer-record']=b'live immutable-by-this-task'
        stack.enter_context(patch.object(M.H,'Remote',return_value=self.r))
        stack.enter_context(patch.object(M,'state',return_value={'protected':'same'}))
        stack.enter_context(patch.object(M.Q,'get',return_value=(b'<html>baseline</html>','text/html')))
        self.proof=stack.enter_context(patch.object(M,'public',return_value={'native_form_unchanged':True}))
        stack.enter_context(patch.object(M.D,'validate_native',return_value={'offer_checks':35,'antispam_checks':17}))
        self.output=stack.enter_context(contextlib.redirect_stdout(io.StringIO()))
    def backup(self):return next(p for p in self.r.dirs if p.startswith(M.PREFIX))
    def meta(self):return {path:(0o644,1608,1611) for path in self.edits}

    def test_exact_three_sources_original_asset_files_retained(self):
        self.assertEqual(tuple(self.edits),(M.ASSET+'/'+M.JS,M.ASSET+'/'+M.MANIFEST,M.HOME))
        self.assertEqual([old for path,(old,new) in self.edits.items() if path!=M.HOME],[None,None])
        self.assertIn(M.D.ASSET+'/manifest.json',self.bundle[2])
        self.assertIn(M.D.ASSET+'/home-family.js',self.bundle[2])

    def test_controller_last_release_verify_controller_first_rollback(self):
        M.operate(self.bundle)
        self.assertEqual(self.r.renames,list(self.edits))
        self.assertEqual(self.r.files[self.backup()+'/original-home.php'],self.edits[M.HOME][0])
        M.operate(self.bundle,self.backup())
        M.operate(self.bundle,self.backup(),True)
        self.assertEqual(self.r.files[M.HOME],self.edits[M.HOME][0])
        for path in self.edits:
            if path!=M.HOME:self.assertNotIn(path,self.r.files)
        self.assertEqual(self.r.files['/protected/customer-record'],b'live immutable-by-this-task')
        self.assertNotIn(M.D.LOCK,self.r.dirs)

    def test_failed_public_verification_recovers_owned_sources(self):
        self.proof.side_effect=RuntimeError('public failed')
        with self.assertRaisesRegex(RuntimeError,'public failed'):M.operate(self.bundle)
        self.assertEqual(self.r.files[M.HOME],self.edits[M.HOME][0])
        self.assertNotIn(M.ASSET+'/'+M.JS,self.r.files)
        self.assertNotIn(M.D.LOCK,self.r.dirs)

    def test_new_asset_collision_stops_before_any_release(self):
        self.r.files[M.ASSET+'/'+M.JS]=b'foreign'
        with self.assertRaisesRegex(RuntimeError,'already exists'):M.operate(self.bundle)
        self.assertEqual(self.r.files[M.ASSET+'/'+M.JS],b'foreign')
        self.assertEqual(self.r.renames,[])

    def test_foreign_controller_keeps_all_dependencies_during_recovery(self):
        for path,(old,new) in self.edits.items():self.r.files[path]=new
        self.r.files[M.HOME]=b'foreign controller'
        with self.assertRaisesRegex(RuntimeError,'Foreign source retained'):M.restore(self.r,self.edits,self.meta())
        self.assertEqual(self.r.files[M.HOME],b'foreign controller')
        self.assertEqual(self.r.files[M.ASSET+'/'+M.JS],self.edits[M.ASSET+'/'+M.JS][1])

    def test_partial_asset_install_does_not_touch_original_runtime(self):
        first=M.ASSET+'/'+M.JS;self.r.files[first]=self.edits[first][1]
        M.restore(self.r,self.edits,self.meta())
        self.assertNotIn(first,self.r.files)
        self.assertEqual(self.r.files[M.HOME],self.edits[M.HOME][0])

    def test_offline_default_and_explicit_aggregate_gate(self):
        with patch('sys.argv',['scroll.py']):M.main()
        self.assertIn('OFFLINE_NO_NETWORK',self.output.getvalue())
        with patch('sys.argv',['scroll.py','--deploy']),self.assertRaisesRegex(RuntimeError,'aggregate'):M.main()
        self.assertEqual(self.r.renames,[])


if __name__=='__main__':unittest.main(verbosity=2)
