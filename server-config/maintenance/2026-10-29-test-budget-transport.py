"""Offline staging/metadata/ownership and mixed-EOL preservation regression."""
import importlib.util
import json
from pathlib import Path
import stat
from types import SimpleNamespace
import unittest

spec=importlib.util.spec_from_file_location('budget_transport',Path(__file__).with_name('2026-10-29-deploy-search-budget.py'))
M=importlib.util.module_from_spec(spec);spec.loader.exec_module(M)

class FakeRemote:
    private_group_verified=True
    def __init__(self):
        self.files={'/public/source.php':b'old'}
        self.sftp=self
        self.events=[]
    def read(self,path,private=False):return self.files[path]
    def info(self,path):
        if path not in self.files:raise FileNotFoundError(path)
        return SimpleNamespace(st_mode=stat.S_IFREG|0o644,st_uid=1608,st_gid=1611)
    def write_new(self,path,blob):
        assert path not in self.files
        self.files[path]=blob;self.events.append('stage')
    def lint(self,path):self.events.append('lint')
    def stage_metadata(self,path,metadata):
        assert isinstance(metadata,tuple) and metadata==(0o644,1608,1611)
        self.events.append('metadata')
    def posix_rename(self,stage,target):
        self.files[target]=self.files.pop(stage);self.events.append('rename')
    def remove_owned(self,path,blob):
        assert self.files[path]==blob
        del self.files[path]

class Checks(unittest.TestCase):
    def test_raw_patch_retains_unchanged_mixed_line_endings(self):
        raw=b'a\r\nb\nc\r\nd\n'
        changed=M.raw_patch(raw,M.norm(raw),b'a\nb\nC\nd\n')
        self.assertEqual(changed,b'a\r\nb\nC\r\nd\n')
    def test_raw_patch_refuses_foreign_baseline(self):
        with self.assertRaises(RuntimeError):M.raw_patch(b'foreign',b'old',b'new')
    def test_json_metadata_roundtrip_and_atomic_replacement(self):
        r=FakeRemote();saved=json.loads(json.dumps({'meta':[0o644,1608,1611]}))
        M.Q.replace(r,'/public/source.php',b'old',b'new',tuple(saved['meta']))
        self.assertEqual(r.files,{'/public/source.php':b'new'})
        self.assertLess(r.events.index('lint'),r.events.index('rename'))
        self.assertLess(r.events.index('metadata'),r.events.index('rename'))
    def test_foreign_source_prevents_activation(self):
        r=FakeRemote();r.files['/public/source.php']=b'foreign'
        with self.assertRaises(RuntimeError):M.Q.replace(r,'/public/source.php',b'old',b'new',(0o644,1608,1611))
        self.assertEqual(r.files,{'/public/source.php':b'foreign'})
    def test_scope(self):
        old,new=M.validate_local()
        self.assertEqual(set(new),{'search','smart'})

if __name__=='__main__':unittest.main()
