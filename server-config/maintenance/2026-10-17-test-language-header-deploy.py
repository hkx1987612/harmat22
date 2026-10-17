"""Offline transport/ownership checks. No server connection or form submission."""
import importlib.util
import json
from pathlib import Path
import subprocess
import unittest

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('header_refresh_visible_test',HERE/'2026-10-17-refresh-language-header-visible.py')
V=importlib.util.module_from_spec(spec);spec.loader.exec_module(V);M=V.M
BASELINE=M.D.ROOT/'outputs/2026-10-17-internal-language/header'
BASELINE_FILES={'harmat-internal-language/bootstrap.js':'bootstrap-v1.js','harmat-internal-language/runtime.css':'runtime-v1.css','zz-harmat-internal-language.php':'controller-v1.php'}
HAS_BASELINE=all((BASELINE/n).is_file() for n in BASELINE_FILES.values())

class FakeRemote:
    def __init__(self):self.files={};self.events=[];self.sftp=self;self.reject_lint=False
    def write_new(self,path,data):
        if path in self.files:raise RuntimeError('collision')
        self.files[path]=data;self.events.append(('write',path))
    def read(self,path,private=False):return self.files[path]
    def info(self,path):
        if path not in self.files:raise FileNotFoundError(path)
        return object()
    def lint(self,path):
        self.events.append(('lint',path))
        if self.reject_lint:raise RuntimeError('synthetic lint failure')
    def chmod(self,path,mode):self.events.append(('chmod',path,mode))
    def posix_rename(self,src,dst):self.files[dst]=self.files.pop(src);self.events.append(('rename',src,dst))
    def remove_owned(self,path,data):
        if self.files[path]!=data:raise RuntimeError('foreign temporary file')
        del self.files[path];self.events.append(('remove',path))

class HeaderDeployTests(unittest.TestCase):
    def test_only_three_presentation_files(self):
        self.assertEqual(M.CHANGED,('harmat-internal-language/runtime.css','harmat-internal-language/bootstrap.js','zz-harmat-internal-language.php'))
    def test_php_lint_precedes_atomic_rename(self):
        r=FakeRemote();target=M.D.DIR+'/'+M.D.MAIN;r.files[target]=b'old'
        M.replace(r,M.D.MAIN,b'new',b'old')
        self.assertEqual(r.files,{target:b'new'})
        events=[x[0] for x in r.events]
        self.assertLess(events.index('lint'),events.index('rename'))
    def test_lint_failure_preserves_original(self):
        r=FakeRemote();target=M.D.DIR+'/'+M.D.MAIN;r.files[target]=b'old';r.reject_lint=True
        with self.assertRaises(RuntimeError):M.replace(r,M.D.MAIN,b'new',b'old')
        self.assertEqual(r.files,{target:b'old'})
        self.assertNotIn('rename',[x[0] for x in r.events])
    def test_foreign_preimage_is_never_replaced(self):
        r=FakeRemote();target=M.D.DIR+'/'+M.D.MAIN;r.files[target]=b'foreign'
        with self.assertRaises(RuntimeError):M.replace(r,M.D.MAIN,b'new',b'old')
        self.assertEqual(r.files,{target:b'foreign'})
    def previous(self):
        old={n:subprocess.check_output(['git','show','ecdf9d8:wp-mu-plugins/'+n],cwd=M.D.ROOT) for n in M.D.FILES}
        for n,filename in BASELINE_FILES.items():
            old[n]=(BASELINE/filename).read_bytes().replace(b'\r\n',b'\n')
            self.assertEqual(M.D.sha(old[n]),M.PRIOR[n])
        r=FakeRemote();r.files.update({M.D.DIR+'/'+n:data for n,data in old.items()})
        r.files[M.PREVIOUS+'/manifest.json']=json.dumps({'pins':{n:M.D.sha(v) for n,v in old.items()}}).encode()
        return r,old
    @unittest.skipUnless(HAS_BASELINE,'Pinned local v1 baseline required; retrieve code-only original from header backup')
    def test_actual_bundle_is_exact_pin_version_refresh(self):
        r,old=self.previous();blobs,pins,digest=M.bundle()
        self.assertEqual(M.match_previous(r,blobs,pins),old)
        self.assertEqual(len(digest),64)
    @unittest.skipUnless(HAS_BASELINE,'Pinned local v1 baseline required')
    def test_runtime_change_is_rejected(self):
        r,_=self.previous();blobs,pins,_=M.bundle();name=M.D.ASSET+'/runtime.js'
        blobs[name]+=b'\n';pins[name]=M.D.sha(blobs[name])
        with self.assertRaises(RuntimeError):M.match_previous(r,blobs,pins)
    @unittest.skipUnless(HAS_BASELINE,'Pinned local v1 baseline required')
    def test_controller_scope_change_is_rejected(self):
        r,_=self.previous();blobs,pins,_=M.bundle()
        blobs[M.D.MAIN]=blobs[M.D.MAIN].replace(b'function harmat_internal_language_scope()',b'function modified_scope()',1)
        with self.assertRaises(RuntimeError):M.match_previous(r,blobs,pins)
    @unittest.skipUnless(HAS_BASELINE,'Pinned local v1 baseline required')
    def test_foreign_live_file_is_rejected(self):
        r,_=self.previous();blobs,pins,_=M.bundle();r.files[M.D.DIR+'/'+M.CHANGED[0]]=b'foreign'
        with self.assertRaises(RuntimeError):M.match_previous(r,blobs,pins)

if __name__=='__main__':unittest.main()
