"""Offline two-file refresh/rollback regression. No transport or business writes."""
import ast
from contextlib import redirect_stdout
import importlib.util
import io
import json
from pathlib import Path
import shlex
from types import ModuleType, SimpleNamespace
import unittest
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('offline_base',Path(__file__).with_name('2026-10-17-test-contact-family-deploy.py'))
B=importlib.util.module_from_spec(spec); spec.loader.exec_module(B)
D=B.D

def load_refresh():
    path=Path(__file__).with_name('2026-10-17-refresh-contact-family.py')
    source=ast.parse(path.read_text(encoding='utf-8'))
    selected=[]
    for node in source.body:
        if isinstance(node,ast.FunctionDef) and node.name=='refresh': selected.append(node)
        elif isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id in ('PREVIOUS','UPDATES','OLD') for t in node.targets): selected.append(node)
    if len(selected)!=4: raise AssertionError('Review refresh isolation after source change')
    result=ModuleType('offline_refresh'); result.D=D; result.json=json; result.shlex=shlex; result.uuid=__import__('uuid')
    exec(compile(ast.Module(body=selected,type_ignores=[]),str(path),'exec'),result.__dict__)
    return result

with B.offline(): R=load_refresh()

class Remote(B.MemoryRemote):
    def run(self,command):
        if D.NATIVE in command:
            backup=shlex.split(command)[-1].rsplit('/',1)[0]
            self.events.append(('run',command))
            self.write_new(backup+'/native-render.html',self.render)
            return self.native
        return super().run(command)

class RefreshTests(B.OfflineCase):
    def setUp(self):
        super().setUp(); self.r=Remote(); self.before={'protected':'same'}
        self.old=B.synthetic_blobs(); self.new=dict(self.old)
        for name in R.UPDATES: self.new[name]+=b'new exact display bytes'
        self.oldpins={n:D.sha(v) for n,v in self.old.items()}; self.pins={n:D.sha(v) for n,v in self.new.items()}
        self.r.dirs.update((D.DIR+'/'+D.ASSET,R.PREVIOUS))
        for name in D.FILES: self.r.files[D.DIR+'/'+name]=self.old[name]
        self.r.files[R.PREVIOUS+'/manifest.json']=json.dumps({'pins':self.oldpins,'before':self.before}).encode()
        self.r.fixture='PASS: 1162 assertions'
        for owner,name,value in ((D.H,'Remote',self.r),(D,'snapshot',self.before),(D,'public_proof',{'public':'ok'})):
            mock=self.stack.enter_context(patch.object(owner,name,return_value=value))
            if name=='public_proof': self.public=mock
            if name=='snapshot': self.snapshot=mock
        self.stack.enter_context(patch.object(R,'OLD',{n:self.oldpins[n] for n in R.UPDATES}))
        self.stack.enter_context(patch.object(R.uuid,'uuid4',return_value=SimpleNamespace(hex='a'*32)))
        self.stack.enter_context(redirect_stdout(io.StringIO()))

    def run_refresh(self): R.refresh(self.new,self.pins,'exact')
    def assert_originals(self):
        for name in D.FILES: self.assertEqual(self.r.files[D.DIR+'/'+name],self.old[name])
        self.assertNotIn(D.LOCK,self.r.dirs); self.assertTrue(self.r.closed)

    def test_success_updates_only_two_display_files_after_native_preflight(self):
        self.run_refresh()
        renames=[e for e in self.r.events if e[0]=='rename']
        self.assertEqual([e[2] for e in renames],[D.DIR+'/'+n for n in R.UPDATES])
        native=next(i for i,e in enumerate(self.r.events) if e[0]=='run' and D.NATIVE in e[1])
        self.assertLess(native,self.r.events.index(renames[0]))
        for name in D.FILES: self.assertEqual(self.r.files[D.DIR+'/'+name],self.new[name])
        self.assertTrue(self.r.closed); self.assertNotIn(D.LOCK,self.r.dirs)

    def test_native_failure_and_protected_drift_never_activate(self):
        self.r.native='{"native":false}'
        with self.assertRaises(RuntimeError): self.run_refresh()
        self.assertFalse(any(e[0]=='rename' for e in self.r.events)); self.assert_originals()

    def test_initial_protected_drift_never_activates(self):
        self.snapshot.return_value={'protected':'changed'}
        with self.assertRaises(RuntimeError): self.run_refresh()
        self.assertFalse(any(e[0]=='rename' for e in self.r.events)); self.assert_originals()

    def test_public_failure_restores_only_two_owned_display_files(self):
        self.public.side_effect=RuntimeError('public fixture failure')
        with self.assertRaisesRegex(RuntimeError,'public fixture'): self.run_refresh()
        self.assert_originals()
        renames=[e for e in self.r.events if e[0]=='rename']
        self.assertEqual([e[2] for e in renames[2:]],[D.DIR+'/'+n for n in reversed(R.UPDATES)])

    def test_second_rename_failure_restores_only_first_file(self):
        original=self.r.posix_rename
        def rename(stage,target):
            if target==D.DIR+'/'+D.MAIN: raise RuntimeError('rename fixture failure')
            return original(stage,target)
        self.stack.enter_context(patch.object(self.r,'posix_rename',side_effect=rename))
        with self.assertRaisesRegex(RuntimeError,'rename fixture'): self.run_refresh()
        self.assert_originals()
        self.assertFalse(any(p.startswith(D.DIR+'/') and p.endswith('.tmp') for p in self.r.files))

    def test_later_controller_edit_is_never_overwritten_by_rollback(self):
        target=D.DIR+'/'+D.MAIN
        def edited(*args):
            self.r.files[target]=b'later unrelated edit'
            raise RuntimeError('public failure')
        self.public.side_effect=edited
        with self.assertRaisesRegex(RuntimeError,'Rollback refuses'): self.run_refresh()
        self.assertEqual(self.r.files[target],b'later unrelated edit')
        self.assertEqual(len([e for e in self.r.events if e[0]=='rename']),2)
        self.assertTrue(self.r.closed)

if __name__=='__main__': unittest.main(verbosity=2)
