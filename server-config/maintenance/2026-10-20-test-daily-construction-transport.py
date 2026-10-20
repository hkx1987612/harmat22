"""Offline allowlist, bounded upload and owned rollback tests; no SSH or HTTP."""
import hashlib
import importlib.util
from pathlib import Path
import stat
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('daily_transport_test',Path(__file__).with_name('2026-10-20-deploy-daily-construction.py'))
M=importlib.util.module_from_spec(spec);spec.loader.exec_module(M)


class Tests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.directory=Path(self.temp.name)
        self.name=M.NAMES[0];(self.directory/self.name).write_bytes(b'abc')
        self.entry={'sha256':hashlib.sha256(b'abc').hexdigest(),'bytes':3}
    def tearDown(self):self.temp.cleanup()
    def test_allowlisted_small_media(self):M.local_media(self.name,self.entry,self.directory)
    def test_names_are_exact_two_dates_and_four_public_files(self):
        self.assertEqual(len(M.NAMES),4)
        self.assertEqual({Path(n).suffix for n in M.NAMES},{'.mp4','.jpg'})
        self.assertTrue(all('-silent' not in n for n in M.NAMES))
    def test_path_escape_refused(self):
        with self.assertRaises(RuntimeError):M.local_media('../'+self.name,self.entry,self.directory)
    def test_wrong_extension_refused(self):
        with self.assertRaises(RuntimeError):M.local_media('test.php',self.entry,self.directory)
    def test_sizes_refused(self):
        for size in (0,-1,True,3.0,512*1024*1024+1):
            with self.subTest(size=size),self.assertRaises(RuntimeError):
                M.local_media(self.name,{**self.entry,'bytes':size},self.directory)
    def test_unknown_metadata_refused(self):
        with self.assertRaises(RuntimeError):M.local_media(self.name,{**self.entry,'url':'private'},self.directory)
    def test_digest_mismatch_refused(self):
        with self.assertRaises(RuntimeError):M.local_media(self.name,{**self.entry,'sha256':'0'*64},self.directory)
    def test_size_mismatch_refused(self):
        with self.assertRaises(RuntimeError):M.local_media(self.name,{**self.entry,'bytes':4},self.directory)
    def test_owned_remote_media_identity(self):
        info=SimpleNamespace(st_mode=stat.S_IFREG|0o644,st_uid=1,st_gid=1,st_size=3,st_mtime=1)
        r=SimpleNamespace(info=lambda p:info,run=lambda command:self.entry['sha256']+'  /owned')
        M.media_check(r,'/owned',self.entry)
    def test_foreign_remote_media_never_removed(self):
        removed=[]
        r=SimpleNamespace(sftp=SimpleNamespace(remove=lambda p:removed.append(p)))
        with patch.object(M.D,'absent',lambda r,p:False),patch.object(M,'media_check',side_effect=RuntimeError('foreign')):
            with self.assertRaisesRegex(RuntimeError,'foreign'):M.remove_media(r,{self.name:self.entry})
        self.assertEqual(removed,[])
    def test_nonallowlisted_cleanup_refused(self):
        with self.assertRaises(RuntimeError):M.remove_media(None,{'../foreign':self.entry})
    def test_protected_neighborhood_release_pins(self):
        self.assertEqual(len(M.MAP_PINS),2)
        self.assertEqual(M.MAP_PINS['assets/map-redesign.js'],'a9d07a0f3c823d40fcd39b4ad0011ed74545f4c99ef28d3c8d534bcb039870f2')


if __name__=='__main__':unittest.main()
