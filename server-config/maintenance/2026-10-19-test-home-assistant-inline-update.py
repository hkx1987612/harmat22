"""Offline absent-only deployment, source ownership and recovery tests."""
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


M=load('inline_update_test','2026-10-19-deploy-home-assistant-inline.py')
S=load('inline_fake_remote','2026-10-19-test-home-scroll-play-update.py')


class Remote(S.Remote):
    def __init__(self):
        super().__init__({});self.files[M.S.HOME]=b'metadata template'
    def run(self,command):return '{"status":"PASS","checks":79}'


class Tests(unittest.TestCase):
    def setUp(self):
        stack=contextlib.ExitStack();self.addCleanup(stack.close)
        stack.enter_context(patch.object(socket.socket,'connect',side_effect=AssertionError('NO NETWORK')))
        self.bundle=M.release();self.source=self.bundle[0];self.r=Remote()
        self.r.files[M.S.HOME]=self.bundle[1][M.D.MAIN]
        self.r.files['/protected/customer']=b'untouched live data'
        stack.enter_context(patch.object(M.H,'Remote',return_value=self.r))
        stack.enter_context(patch.object(M,'state',return_value={'protected':'same'}))
        stack.enter_context(patch.object(M.Q,'get',return_value=(b'<html>baseline</html>','text/html')))
        self.public=stack.enter_context(patch.object(M,'public',return_value={'native_form_unchanged':True}))
        stack.enter_context(patch.object(M.D,'validate_native',return_value={'offer_checks':35,'antispam_checks':17}))
        self.output=stack.enter_context(contextlib.redirect_stdout(io.StringIO()))
    def backup(self):return next(p for p in self.r.dirs if p.startswith(M.PREFIX))

    def test_absent_only_addon_and_preserved_sources(self):
        self.assertEqual(M.MAIN,'zz-harmat-home-assistant-inline.php')
        self.assertNotIn(M.MAIN,self.bundle[1])
        for path in (M.D.MAIN,'harmat-home-family/home-family-scroll-play.js','zz-harmat-home-video-fullscreen.php',
                     'zz-harmat-assistant-presentation.php','harmat-assistant-presentation/assistant-presentation.js'):
            self.assertIn(path,self.bundle[1])

    def test_backup_release_verify_and_remove_only_owned_addon(self):
        M.operate(self.bundle)
        self.assertEqual(self.r.renames,[M.TARGET])
        self.assertEqual(self.r.files[self.backup()+'/'+M.MAIN],self.source)
        M.operate(self.bundle,self.backup())
        M.operate(self.bundle,self.backup(),True)
        self.assertNotIn(M.TARGET,self.r.files)
        self.assertEqual(self.r.files['/protected/customer'],b'untouched live data')
        self.assertNotIn(M.D.LOCK,self.r.dirs)

    def test_verification_failure_removes_exact_addon(self):
        self.public.side_effect=RuntimeError('public failed')
        with self.assertRaisesRegex(RuntimeError,'public failed'):M.operate(self.bundle)
        self.assertNotIn(M.TARGET,self.r.files)
        self.assertNotIn(M.D.LOCK,self.r.dirs)

    def test_collision_blocks_activation(self):
        self.r.files[M.TARGET]=b'foreign'
        with self.assertRaisesRegex(RuntimeError,'already exists'):M.operate(self.bundle)
        self.assertEqual(self.r.files[M.TARGET],b'foreign')
        self.assertEqual(self.r.renames,[])

    def test_foreign_bytes_retained_in_recovery(self):
        self.r.files[M.TARGET]=b'foreign'
        with self.assertRaisesRegex(RuntimeError,'Foreign addon retained'):M.restore(self.r,self.source)
        self.assertEqual(self.r.files[M.TARGET],b'foreign')

    def test_saved_manifest_tamper_blocks_recovery(self):
        M.operate(self.bundle)
        self.r.files[self.backup()+'/manifest.json']=b'{}'
        with self.assertRaises((KeyError,RuntimeError)):M.operate(self.bundle,self.backup(),True)
        self.assertEqual(self.r.files[M.TARGET],self.source)

    def test_default_offline_and_pre_network_gate(self):
        with patch('sys.argv',['inline.py']):M.main()
        self.assertIn('OFFLINE_NO_NETWORK',self.output.getvalue())
        with patch('sys.argv',['inline.py','--deploy']),self.assertRaisesRegex(RuntimeError,'aggregate'):M.main()
        self.assertEqual(self.r.renames,[])


if __name__=='__main__':unittest.main(verbosity=2)
