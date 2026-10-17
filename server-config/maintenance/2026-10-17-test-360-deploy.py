"""Offline viewer deployment contracts; no SSH, HTTP, subprocess or live writes."""
import ast
from contextlib import redirect_stdout
import io
import json
from pathlib import Path
from types import ModuleType,SimpleNamespace
import unittest
from unittest.mock import Mock,patch
import hashlib

SOURCE=Path(__file__).with_name('2026-10-17-deploy-360-comfort.py')
tree=ast.parse(SOURCE.read_text(encoding='utf-8'))
removed=0
for node in tree.body:
    if isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='D' for t in node.targets):
        node.value=ast.Name(id='_stub',ctx=ast.Load());removed+=1
    if isinstance(node,ast.Expr) and isinstance(node.value,ast.Call) and isinstance(node.value.func,ast.Attribute) and node.value.func.attr=='exec_module':
        node.value=ast.Constant(value=None);removed+=1
assert removed==2
M=ModuleType('viewer_deploy_offline');M.__file__=str(SOURCE)
def require(value,message):
    if not value:raise RuntimeError(message)
M._stub=SimpleNamespace(H=SimpleNamespace(LIVE='/live',BACKUPS='/backups'),require=require,
                       sha=lambda b:hashlib.sha256(b).hexdigest())
exec(compile(ast.fix_missing_locations(tree),str(SOURCE),'exec'),M.__dict__)

class Remote:
    def __init__(self):self.files={};self.events=[];self.sftp=self;self.dirs={M.DIR,M.H.BACKUPS};self.closed=False
    def read(self,p,private=False):return self.files[p]
    def directory(self,p):
        if p not in self.dirs:raise FileNotFoundError(p)
    def mkdir(self,p):
        if p in self.dirs:raise FileExistsError(p)
        self.dirs.add(p)
    def rmdir(self,p):self.dirs.remove(p)
    def run(self,cmd):return '{}'
    def close(self):self.closed=True
    def remove_owned(self,p,data):
        require(self.files[p]==data,'Wrong cleanup bytes');del self.files[p]
    def write_new(self,p,b):
        if p in self.files:raise FileExistsError(p)
        self.files[p]=b;self.events.append(('stage',p))
    def lint(self,p):self.events.append(('lint',p))
    def chmod(self,p,m):self.events.append(('mode',p,m))
    def posix_rename(self,p,t):self.files[t]=self.files.pop(p);self.events.append(('rename',t))
    def purge(self):self.events.append(('purge',))

class Contracts(unittest.TestCase):
    def test_php_allows_only_one_version_literal(self):
        old=b"<?php\r\nwp_enqueue_script('x', 'viewer.js', array(), '6.4', true);\r\n"
        new=old.replace(b'6.4',b'6.5').replace(b'\r\n',b'\n')
        self.assertEqual(M.php_only_version(old,new),old.replace(b'6.4',b'6.5'))
        for bad in (new+b'// extra',new.replace(b'6.5',b'6.6')):
            with self.assertRaises(RuntimeError):M.php_only_version(old,bad)
        with self.assertRaises(RuntimeError):M.php_only_version(old+old,new+new)

    def test_rollback_exact_two_files_reverse_order_lint_before_and_after(self):
        r=Remote();old={n:b'original '+n.encode() for n in M.FILES};new={n:b'new '+n.encode() for n in M.FILES}
        r.files={M.DIR+'/'+n:b for n,b in new.items()};r.files['/unrelated']=b'keep'
        M.rollback(r,list(M.FILES),old,{n:M.D.sha(b) for n,b in new.items()})
        self.assertEqual([e[1] for e in r.events if e[0]=='rename'],[M.DIR+'/'+n for n in reversed(M.FILES)])
        for n in M.FILES:self.assertEqual(r.read(M.DIR+'/'+n),old[n])
        self.assertEqual(r.files['/unrelated'],b'keep');self.assertEqual(r.events[-1],('purge',))
        lint=[i for i,e in enumerate(r.events) if e[0]=='lint'];rename=next(i for i,e in enumerate(r.events) if e==('rename',M.DIR+'/lakaspark-360.php'))
        self.assertLess(lint[0],rename);self.assertGreater(lint[1],rename)

    def test_rollback_refuses_concurrent_editor_and_never_touches_earlier_file(self):
        r=Remote();r.files={M.DIR+'/viewer.js':b'new',M.DIR+'/lakaspark-360.php':b'newer editor'}
        with self.assertRaisesRegex(RuntimeError,'later editor'):
            M.rollback(r,list(M.FILES),{n:b'old' for n in M.FILES},{n:M.D.sha(b'new') for n in M.FILES})
        self.assertEqual(r.events,[]);self.assertEqual(r.files[M.DIR+'/lakaspark-360.php'],b'newer editor')

    def test_no_installed_file_no_write_no_purge(self):
        r=Remote();M.rollback(r,[],{},{});self.assertEqual(r.events,[])

    def test_bad_backup_rejected_before_remote_construction(self):
        M.H.Remote=Mock(side_effect=AssertionError('No transport allowed'))
        for bad in ('/backup','/backups/360-comfort-'+'a'*31,'/backups/360-comfort-'+'A'*32):
            with self.assertRaises(RuntimeError):M.verify(bad)
        M.H.Remote.assert_not_called()

class Lifecycle(unittest.TestCase):
    def setUp(self):
        self.r=Remote()
        self.old={'viewer.js':b'old-js','lakaspark-360.php':b"<?php\n'viewer.js', array(), '6.4', true)\n"}
        self.new={'viewer.js':b'new-js','lakaspark-360.php':self.old['lakaspark-360.php'].replace(b'6.4',b'6.5')}
        self.r.files={M.DIR+'/'+n:b for n,b in self.old.items()}
        self.pins={n:M.D.sha(b) for n,b in self.new.items()}
        self.contexts=[]
        def patching(*a,**k):
            p=patch.object(*a,**k);self.contexts.append(p);return p.start()
        self.addCleanup(lambda:[p.stop() for p in reversed(self.contexts)])
        patching(M,'OLD',{n:M.D.sha(b) for n,b in self.old.items()})
        patching(M.H,'Remote',return_value=self.r,create=True)
        patching(M.H,'WP','offline-wp',create=True)
        patching(M.subprocess,'run',return_value=None)
        patching(M.D,'write_json',side_effect=lambda r,p,v:r.write_new(p,json.dumps(v).encode()),create=True)
        patching(M.D,'absent',side_effect=lambda r,p:p not in r.files,create=True)
        self.state=patching(M,'snapshot',return_value={'protected':'same'})
        self.pure=patching(M,'native_checks',return_value={'no_dispatch':True})
        self.public=patching(M,'public_proof',return_value={'public':True})
        self.silence=redirect_stdout(io.StringIO());self.silence.__enter__();self.addCleanup(lambda:self.silence.__exit__(None,None,None))
    def deploy(self):M.deploy(dict(self.new),dict(self.pins),'exact-reviewed')
    def assert_original(self):
        for n,b in self.old.items():self.assertEqual(self.r.files[M.DIR+'/'+n],b)
        self.assertTrue(self.r.closed);self.assertNotIn(M.LOCK,self.r.dirs)
    def test_success_backups_and_php_lint_before_rename(self):
        self.deploy()
        renamed=[e[1] for e in self.r.events if e[0]=='rename']
        self.assertEqual(renamed,[M.DIR+'/'+n for n in M.FILES])
        for n,b in self.new.items():self.assertEqual(self.r.files[M.DIR+'/'+n],b)
        backup=next(p.rsplit('/',1)[0] for p in self.r.files if p.endswith('/manifest.json'))
        for n,b in self.old.items():self.assertEqual(self.r.files[backup+'/original-'+n],b)
        events=self.r.events;activation=next(i for i,e in enumerate(events) if e[0]=='rename')
        self.assertTrue(any(e[0]=='lint' and e[1].endswith('candidate-lakaspark-360.php') for e in events[:activation]))
        self.public.assert_called_once();self.assertTrue(self.r.closed)
    def test_version_extra_php_edit_stops_without_replacement(self):
        self.new['lakaspark-360.php']+=b'// not allowed'
        with self.assertRaisesRegex(RuntimeError,'beyond one'):self.deploy()
        self.assert_original();self.assertFalse(any(e[0]=='rename' for e in self.r.events))
    def test_pure_check_failure_never_activates(self):
        self.pure.side_effect=RuntimeError('pure failure')
        with self.assertRaisesRegex(RuntimeError,'pure failure'):self.deploy()
        self.assert_original();self.public.assert_not_called()
    def test_public_failure_restores_both_files_only(self):
        self.r.files['/protected']=b'keep';self.public.side_effect=RuntimeError('public failure')
        with self.assertRaisesRegex(RuntimeError,'public failure'):self.deploy()
        self.assert_original();self.assertEqual(self.r.files['/protected'],b'keep')
    def test_partial_atomic_failure_restores_first_file_and_cleans_stage(self):
        rename=self.r.posix_rename
        def fail_php(p,t):
            if t.endswith('/lakaspark-360.php'):raise RuntimeError('atomic failure')
            rename(p,t)
        with patch.object(self.r,'posix_rename',side_effect=fail_php):
            with self.assertRaisesRegex(RuntimeError,'atomic failure'):self.deploy()
        self.assert_original();self.assertFalse(any(p.endswith('.tmp') for p in self.r.files))

if __name__=='__main__':unittest.main(verbosity=2)
