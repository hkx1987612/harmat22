"""Offline deployment safety contract. Fake transport only; never loads SSH helpers.

Run directly with Python. Tests use a synthetic pinned bundle, so an in-progress
production manifest is neither required nor treated as a completed release.
"""
import contextlib
import importlib.abc
import importlib.util
import io
import json
from pathlib import Path
import socket
import tempfile
import types
import unittest
from unittest.mock import patch


def load_deployer():
    class StubLoader(importlib.abc.Loader):
        def create_module(self, spec):
            return None

        def exec_module(self, module):
            module.H = types.SimpleNamespace(
                LIVE='/fake/public_html', BACKUPS='/fake/backups',
                WP='fake-wp', PHP='fake-php')
            module.T = types.SimpleNamespace(OTHER_SOURCES={'source': '/fake/source'})
            module.I = types.SimpleNamespace(CRITICAL=('/fake/critical',))
            module.absent = lambda remote, path: path not in remote.files and path not in remote.dirs

    path = Path(__file__).with_name('2026-10-18-deploy-home-family.py')
    original = importlib.util.spec_from_file_location
    spec = original('home_family_deployer_under_test', path)
    module = importlib.util.module_from_spec(spec)

    def dependency(name, filename, *args, **kwargs):
        if Path(filename).name != '2026-10-17-deploy-project-family.py':
            raise AssertionError('Unexpected dependency import: ' + str(filename))
        return importlib.util.spec_from_loader(name, StubLoader())

    with patch.object(importlib.util, 'spec_from_file_location', side_effect=dependency):
        spec.loader.exec_module(module)
    return module


M = load_deployer()
BASELINE = (f'<!doctype html><html><head><link rel="canonical" href="{M.URL}">'
            '<meta name="description" content="native"></head><body>'
            '<header id="masthead"></header><main id="main"><h1>Old</h1></main>'
            '<footer id="colophon"></footer><div id="h22-offer-modal">'
            '<form id="h22-offer-form"><input name="_harmat_offer_nonce" value="old">'
            '<textarea name="message">Original message</textarea></form></div>'
            '<button id="hm-cookie-settings-button"></button>'
            '<div id="harmat-internal-language"></div></body></html>')
SHADOW = BASELINE.replace('<h1>Old</h1>',
                         '<div id="harmat-home-family" data-language-protect>'
                         '<h1 id="hero-title">Home</h1><div id="panorama-viewer"></div>'
                         '<a href="tel:+36300733375">Call</a>'
                         '<a href="mailto:ertekesites@harmat22.hu">Email</a></div>')


class FakeRemote:
    def __init__(self):
        self.files = {'/fake/critical': b'critical', '/fake/source': b'source',
                      M.DIR + '/harmat-unified-offer-modal.php': b'native form',
                      M.DIR + '/zz-harmat-internal-language.php': b'language',
                      M.H.LIVE + '/.htaccess': b'htaccess'}
        self.dirs = {M.DIR, M.H.BACKUPS}
        self.events = []
        self.sftp = self
        self.fail = lambda operation, path: None
        self.closed = False

    def event(self, operation, path):
        self.events.append((operation, path))
        self.fail(operation, path)

    def directory(self, path, private=False):
        self.event('directory', path)
        if path not in self.dirs:
            raise FileNotFoundError(path)

    def mkdir(self, path, mode=0o700):
        self.event('mkdir', path)
        if path in self.files or path in self.dirs:
            raise FileExistsError(path)
        self.dirs.add(path)

    def chmod(self, path, mode):
        self.event('chmod', path)

    def rmdir(self, path):
        self.event('rmdir', path)
        if any(p.startswith(path + '/') for p in self.files):
            raise OSError('Directory not empty')
        self.dirs.remove(path)

    def write_new(self, path, data):
        self.event('write', path)
        if path in self.files:
            raise FileExistsError(path)
        if path.rsplit('/', 1)[0] not in self.dirs:
            raise FileNotFoundError('Parent missing: ' + path)
        self.files[path] = data
        self.event('written', path)

    def read(self, path, private=False):
        self.event('read', path)
        return self.files[path]

    def lint(self, path):
        self.event('lint', path)

    def posix_rename(self, source, target):
        self.event('before_rename', target)
        self.files[target] = self.files.pop(source)
        self.event('rename', target)

    def rename(self, source, target):
        self.event('before_rename', target)
        if target in self.files:
            raise RuntimeError('SFTP destination exists')
        self.files[target] = self.files.pop(source)
        self.event('rename', target)

    def remove_owned(self, path, data):
        if self.files.get(path) != data:
            self.event('refuse_foreign', path)
            raise RuntimeError('foreign bytes')
        self.event('remove', path)
        del self.files[path]

    def run(self, command):
        self.event('run', command)
        if M.NATIVE in command:
            backup = next(p for p in self.dirs if p.startswith(M.PREFIX) and '/' not in p[len(M.PREFIX):])
            self.files[backup + '/home-baseline.html'] = BASELINE.encode()
            self.files[backup + '/native-render.html'] = SHADOW.encode()
            return json.dumps({'native': True, 'outside_main_preserved': True})
        if M.TEST in command:
            return '{"status":"PASS"}'
        return '{"post":{},"meta":{}}'

    def purge(self):
        self.event('purge', None)

    def close(self):
        self.closed = True
        self.event('close', None)


class DeploymentTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NETWORK FORBIDDEN')))
        self.stack.enter_context(patch.object(M.urllib.request, 'urlopen', side_effect=AssertionError('HTTP FORBIDDEN')))
        self.remote = FakeRemote()
        self.original = dict(self.remote.files)
        self.files = (M.ASSET + '/body.php', M.ASSET + '/home-family.css',
                      M.ASSET + '/home-family.js', M.ASSET + '/manifest.json', M.MAIN)
        self.blobs = {name: ('synthetic ' + name).encode() for name in self.files}
        self.blobs.update({name: b'<?php // fixture\n' for name in (M.TEST, M.NATIVE, M.CHECKER)})
        self.hashes = {name: M.sha(data) for name, data in self.blobs.items()}
        self.digest = M.sha(json.dumps(self.hashes, sort_keys=True, separators=(',', ':')).encode())
        self.stack.enter_context(patch.object(M.H, 'Remote', return_value=self.remote, create=True))
        self.stack.enter_context(patch.object(M, 'snapshot', side_effect=self.snapshot))
        self.stack.enter_context(patch.object(M, 'get', return_value=(BASELINE.encode(), 'text/html')))
        self.proof = self.stack.enter_context(patch.object(M, 'public_proof', return_value={'public': True}))
        self.filters = self.stack.enter_context(patch.object(M, 'validate_native', return_value={'pure': True}))
        self.output = self.stack.enter_context(contextlib.redirect_stdout(io.StringIO()))

    def snapshot(self, remote):
        remote.event('snapshot', None)
        return {'protected': 'unchanged'}

    def deploy(self, prepare=False):
        M.deploy(self.files, self.blobs, self.hashes, self.digest, prepare)

    def fail_once(self, operation, predicate):
        def failure(op, path):
            if op == operation and predicate(path):
                self.remote.fail = lambda *args: None
                raise RuntimeError('synthetic ' + operation + ' failure')
        self.remote.fail = failure

    def assert_production_original(self):
        current = {path: data for path, data in self.remote.files.items()
                   if not path.startswith(M.H.BACKUPS + '/')}
        self.assertEqual(current, self.original)
        self.assertNotIn(M.DIR + '/' + M.ASSET, self.remote.dirs)
        self.assertNotIn(M.LOCK, self.remote.dirs)
        self.assertTrue(self.remote.closed)

    def test_controller_last_and_stage_formal_php_lint(self):
        self.deploy()
        events = self.remote.events
        self.assertEqual([path for op, path in events if op == 'rename'],
                         [M.DIR + '/' + name for name in self.files])
        for name in self.files:
            target = M.DIR + '/' + name
            renamed = events.index(('rename', target))
            if name.endswith('.php'):
                stage_lints = [i for i, (op, p) in enumerate(events)
                               if op == 'lint' and p.startswith(target + '.') and p.endswith('.tmp')]
                self.assertEqual(len(stage_lints), 1)
                self.assertLess(stage_lints[0], renamed)
                self.assertLess(renamed, events.index(('lint', target)))
        self.assertIn('RELEASED', self.output.getvalue())

    def test_backups_and_preflight_proof_precede_first_production_write(self):
        self.deploy()
        events = self.remote.events
        first = events.index(('mkdir', M.DIR + '/' + M.ASSET))
        for suffix in ('/manifest.json', '/public-original.html', '/original-page.json', '/preflight-proof.json'):
            self.assertTrue(any(i < first and op == 'written' and path.startswith(M.PREFIX) and path.endswith(suffix)
                                for i, (op, path) in enumerate(events)))
        self.assertTrue(any(i < first and op == 'run' and M.NATIVE in path for i, (op, path) in enumerate(events)))
        self.assertEqual(sum(op == 'snapshot' for op, _ in events[:first]), 2)

    def test_prepare_is_private_backup_only(self):
        self.deploy(prepare=True)
        self.assert_production_original()
        self.proof.assert_not_called()

    def test_preflight_failures_leave_production_untouched(self):
        for operation, predicate in (
                ('write', lambda p: p.endswith('/original-page.json')),
                ('lint', lambda p: p.startswith(M.PREFIX)),
                ('run', lambda p: M.TEST in p),
                ('run', lambda p: M.NATIVE in p)):
            with self.subTest(operation=operation, predicate=predicate):
                self.remote.files = dict(self.original)
                self.remote.dirs = {M.DIR, M.H.BACKUPS}
                self.fail_once(operation, predicate)
                with self.assertRaises(RuntimeError):
                    self.deploy()
                self.assert_production_original()

    def test_protected_drift_before_activation_refuses_write(self):
        with patch.object(M, 'snapshot', side_effect=[{'v': 1}, {'v': 2}]):
            with self.assertRaisesRegex(RuntimeError, 'drift before'):
                self.deploy()
        self.assert_production_original()

    def test_existing_release_is_never_touched(self):
        target = M.DIR + '/' + M.MAIN
        self.remote.files[target] = b'foreign controller'
        with self.assertRaisesRegex(RuntimeError, 'already exist'):
            self.deploy()
        self.assertEqual(self.remote.files[target], b'foreign controller')
        self.assertFalse(any(op in ('rename', 'remove') for op, _ in self.remote.events))

    def test_stage_lint_failure_cleans_partial_release(self):
        self.fail_once('lint', lambda p: p.startswith(M.DIR) and p.endswith('.tmp'))
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_production_original()

    def test_formal_lint_failure_cleans_partial_release(self):
        self.fail_once('lint', lambda p: p == M.DIR + '/' + self.files[0])
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_production_original()

    def test_controller_formal_lint_failure_deactivates_first(self):
        self.fail_once('lint', lambda p: p == M.DIR + '/' + M.MAIN)
        with self.assertRaises(RuntimeError):
            self.deploy()
        removed = [p for op, p in self.remote.events if op == 'remove']
        self.assertEqual(removed[0], M.DIR + '/' + M.MAIN)
        self.assert_production_original()

    def test_staged_hash_mismatch_never_activates_controller(self):
        hashes = dict(self.hashes)
        hashes[self.files[0]] = '0' * 64
        with self.assertRaisesRegex(RuntimeError, 'Staged hash mismatch'):
            M.deploy(self.files, self.blobs, hashes, self.digest, False)
        self.assertFalse(any(op == 'rename' and p == M.DIR + '/' + M.MAIN
                             for op, p in self.remote.events))
        self.assert_production_original()

    def test_failed_public_proof_removes_controller_first(self):
        self.proof.side_effect = RuntimeError('public proof failed')
        with self.assertRaisesRegex(RuntimeError, 'public proof failed'):
            self.deploy()
        removed = [p for op, p in self.remote.events if op == 'remove']
        self.assertEqual(removed[0], M.DIR + '/' + M.MAIN)
        self.assert_production_original()

    def test_rollback_refuses_foreign_bytes(self):
        for name in self.files:
            self.remote.files[M.DIR + '/' + name] = self.blobs[name]
        controller = M.DIR + '/' + M.MAIN
        self.remote.files[controller] = b'foreign controller'
        with self.assertRaisesRegex(RuntimeError, 'foreign bytes'):
            M.rollback(self.remote, self.files, self.blobs)
        self.assertEqual(self.remote.files[controller], b'foreign controller')
        self.assertFalse(any(op == 'remove' for op, _ in self.remote.events))

    def test_rollback_never_rewinds_business_state(self):
        self.proof.side_effect = RuntimeError('fail after activation')
        with self.assertRaises(RuntimeError):
            self.deploy()
        commands = [p for op, p in self.remote.events if op == 'run']
        self.assertFalse(any(token in command.lower() for command in commands
                             for token in ('db import', 'mysql ', 'update_post', 'update_option', 'delete_post')))
        for path, data in self.original.items():
            self.assertEqual(self.remote.files[path], data)

    def test_default_cli_is_offline_and_pin_required_for_writes(self):
        with patch.object(M, 'bundle', return_value=(self.files, self.blobs, self.hashes, self.digest)), \
                patch.object(M, 'deploy') as deploy:
            with patch('sys.argv', ['deploy.py']):
                M.main()
            self.assertIn('OFFLINE_NO_WRITES', self.output.getvalue())
            for mode in ('--prepare', '--deploy'):
                for args in ([mode], [mode, '--expect', 'wrong']):
                    with patch('sys.argv', ['deploy.py'] + args):
                        with self.assertRaisesRegex(RuntimeError, 'Exact reviewed bundle'):
                            M.main()
                with patch('sys.argv', ['deploy.py', mode, '--expect', self.digest]):
                    M.main()
            self.assertEqual(deploy.call_count, 2)

    def test_partial_stage_upload_recovers_earlier_assets(self):
        def interrupted(op, path):
            if op == 'written' and path.startswith(M.DIR + '/' + self.files[1] + '.'):
                self.remote.files[path] = self.remote.files[path][:3]
                self.remote.fail = lambda *args: None
                raise RuntimeError('short upload')
        self.remote.fail = interrupted
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_production_original()

    def test_directory_chmod_failure_recovers_new_directory(self):
        self.fail_once('chmod', lambda p: p == M.DIR + '/' + M.ASSET)
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assert_production_original()

    def test_target_appearing_during_upload_is_not_overwritten(self):
        target = M.DIR + '/' + self.files[0]
        def concurrent_editor(op, path):
            if op == 'before_rename' and path == target:
                self.remote.files[target] = b'foreign concurrent bytes'
                self.remote.fail = lambda *args: None
        self.remote.fail = concurrent_editor
        try:
            self.deploy()
        except RuntimeError:
            pass
        self.assertEqual(self.remote.files.get(target), b'foreign concurrent bytes')

    def test_rollback_requires_saved_original_absence(self):
        backup = M.PREFIX + 'a' * 32
        saved = {'pins': self.hashes, 'bundle': self.digest, 'files': self.files,
                 'before': {'protected': 'unchanged'}, 'original_absent': []}
        self.remote.files[backup + '/manifest.json'] = json.dumps(saved).encode()
        self.remote.dirs.add(M.DIR + '/' + M.ASSET)
        self.remote.files.update({M.DIR + '/' + name: self.blobs[name] for name in self.files})
        try:
            with patch.object(M, 'bundle', return_value=(self.files, self.blobs, self.hashes, self.digest)):
                M.verify(backup, reverse=True)
        except RuntimeError:
            pass
        self.assertEqual(self.remote.files.get(M.DIR + '/' + M.MAIN), self.blobs[M.MAIN])

    def test_explicit_rollback_can_recover_missing_asset(self):
        backup = M.PREFIX + 'b' * 32
        saved = {'pins': self.hashes, 'bundle': self.digest, 'files': self.files,
                 'before': {'protected': 'unchanged'}, 'original_absent': self.files}
        self.remote.files[backup + '/manifest.json'] = json.dumps(saved).encode()
        self.remote.dirs.add(M.DIR + '/' + M.ASSET)
        self.remote.files.update({M.DIR + '/' + name: self.blobs[name] for name in self.files[1:]})
        with patch.object(M, 'bundle', return_value=(self.files, self.blobs, self.hashes, self.digest)):
            try:
                M.verify(backup, reverse=True)
            except (RuntimeError, KeyError, FileNotFoundError):
                pass
        self.assertNotIn(M.DIR + '/' + M.MAIN, self.remote.files)


class BundleTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.root = Path(self.stack.enter_context(tempfile.TemporaryDirectory()))
        self.here = self.root / 'maintenance'
        self.asset = self.root / 'wp-mu-plugins' / M.ASSET
        self.asset.mkdir(parents=True)
        self.here.mkdir()
        self.pins = {}
        for name, content in {'body.php': b'<?php return "home";\n',
                              'home-family.css': b'.home { display: block; }\n',
                              'home-family.js': b'/* synthetic */\n'}.items():
            (self.asset / name).write_bytes(content)
            self.pins[name] = M.sha(content)
        for name in (M.TEST, M.NATIVE, M.CHECKER):
            (self.here / name).write_bytes(b'<?php // offline fixture\n')
        self.stack.enter_context(patch.object(M, 'ROOT', self.root))
        self.stack.enter_context(patch.object(M, 'HERE', self.here))
        self.save_manifest()

    def save_manifest(self):
        data = (json.dumps(self.pins, sort_keys=True) + '\n').encode()
        (self.asset / 'manifest.json').write_bytes(data)
        (self.asset.parent / M.MAIN).write_bytes(b'<?php // ' + M.sha(data).encode() + b'\n')

    def test_exact_assets_manifest_helpers_and_controller_all_pinned(self):
        files, blobs, hashes, digest = M.bundle()
        self.assertEqual(files[-1], M.MAIN)
        self.assertEqual(set(files[:-2]), {M.ASSET + '/' + n for n in self.pins})
        self.assertEqual(set(blobs), set(files) | {M.TEST, M.NATIVE, M.CHECKER})
        self.assertEqual(hashes, {n: M.sha(b) for n, b in blobs.items()})
        self.assertEqual(digest, M.sha(json.dumps(hashes, sort_keys=True, separators=(',', ':')).encode()))

    def test_asset_drift_rejected(self):
        (self.asset / 'home-family.js').write_bytes(b'changed')
        with self.assertRaisesRegex(RuntimeError, 'manifest drift'):
            M.bundle()

    def test_controller_manifest_pin_required(self):
        (self.asset.parent / M.MAIN).write_bytes(b'<?php // wrong manifest\n')
        with self.assertRaisesRegex(RuntimeError, 'manifest pin mismatch'):
            M.bundle()

    def test_unsafe_asset_name_rejected_before_read(self):
        self.pins['../foreign.php'] = '0' * 64
        self.save_manifest()
        with self.assertRaisesRegex(RuntimeError, 'Invalid release filename'):
            M.bundle()

    def test_helper_change_changes_exact_review_digest(self):
        original = M.bundle()[3]
        (self.here / M.NATIVE).write_bytes(b'<?php // changed preflight\n')
        self.assertNotEqual(M.bundle()[3], original)


if __name__ == '__main__':
    unittest.main(verbosity=2)
