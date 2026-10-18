"""No-network transport contracts; real helper methods on an in-memory SFTP."""
import contextlib
import importlib.util
import io
import json
from pathlib import Path
import shlex
import socket
import stat
import subprocess
import tempfile
import types
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location('assistant_deployer_tests', Path(__file__).with_name('2026-10-18-deploy-assistant-presentation.py'))
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)

WIDGET = ('<style id="harmat-local-assistant-style">.original{color:red}</style>'
          '<button class="harmat-local-ai-launch"></button>'
          '<section id="harmat-local-ai-panel"><div data-harmat-local-ai-body></div>'
          '<div data-harmat-local-ai-suggestions></div><form data-harmat-local-ai-form>'
          '<input class="harmat-local-ai-input"><button class="harmat-local-ai-close"></button></form></section>'
          '<script id="harmat-local-assistant-script">originalAssistant();</script>')
BASE = ('<!doctype html><html><head><link rel="canonical" href="https://harmat22.hu/">'
        '<meta name="description" content="native"></head><body><header id="masthead"></header>'
        '<main id="main"><div id="harmat-home-family" data-language-protect>'
        '<h1 id="hero-title">Home</h1><div id="panorama-viewer"></div>'
        '<a href="tel:+36300733375">Call</a><a href="mailto:ertekesites@harmat22.hu">Email</a></div></main>'
        '<footer id="colophon"></footer><div id="h22-offer-modal"><form id="h22-offer-form">'
        '<input name="_harmat_offer_nonce" value="old"><textarea>Original message</textarea></form></div>'
        '<button id="hm-cookie-settings-button"></button><div id="harmat-internal-language"></div>' + WIDGET + '</body></html>')


def release():
    files = M.REQUIRED + (M.LICENSE, M.MAIN)
    blobs = {files[0]: b'.presentation{color:green}', files[1]: b'presentation();',
             M.LICENSE: b'Lucide license\n', M.MAIN: b'<?php // synthetic controller\n'}
    blobs.update({name: b'<?php // pure checker\n' if name.endswith('.php') else b'# test support\n'
                  for name in (M.CHECKER, M.SELF, M.TEST, M.NATIVE)})
    descriptor = {'schema': 1, 'files': list(files), 'pins': {n: M.sha(b) for n, b in blobs.items()}}
    return {'files': files, 'blobs': blobs, 'descriptor': descriptor, 'aggregate': M.sha(M.encoded(descriptor))}


def resources(value):
    def emitted(name):
        data = value['blobs'][name]
        return (data[:-1] if data.endswith(b'\n') else data).decode()
    return ('<style id="harmat-assistant-presentation-style">' + emitted(M.REQUIRED[0]) + '</style>'
            '<script id="harmat-assistant-presentation-script">' + emitted(M.REQUIRED[1]) + '</script>')


class Handle(io.BytesIO):
    def __init__(self, remote, path, writing):
        super().__init__(remote.files[path])
        self.remote, self.path, self.writing = remote, path, writing

    def write(self, data):
        self.remote.event('write', self.path)
        result = super().write(data)
        self.remote.files[self.path] = self.getvalue()
        self.remote.event('written', self.path)
        return result


class FakeRemote(M.H.Remote):
    """Use H.read/write_new/lint/stage_metadata/remove_owned/directory unchanged."""
    def __init__(self):
        self.sftp = self
        self.private_group_verified = True
        self.files, self.attrs, self.events = {}, {}, []
        self.dirs = {M.DIR, M.H.BACKUPS}
        self.closed = False
        self.fail = lambda *args: None
        self.public_drift = None
        for path in self.dirs:
            self.attrs[path] = [0o755, 1608, 1611]
        self.seed(M.DIR + '/' + M.HOME[1], b'{"home-family.css":"' + b'a' * 64 + b'"}')
        for path in M.protected_paths(self):
            if path not in self.files:
                self.seed(path, b'current protected raw\r\nuntouched LF\n')

    def seed(self, path, data, mode=0o644):
        self.files[path] = data
        self.attrs[path] = [mode, 1608, 1611]

    def event(self, operation, path):
        self.events.append((operation, path))
        self.fail(operation, path)

    def lstat(self, path):
        self.event('info', path)
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        mode, uid, gid = self.attrs[path]
        return types.SimpleNamespace(st_mode=mode | (stat.S_IFDIR if path in self.dirs else stat.S_IFREG),
                                     st_uid=uid, st_gid=gid, st_size=len(self.files.get(path, b'')), st_mtime=1)

    def normalize(self, path):
        return path

    def open(self, path, mode):
        self.event('open', path)
        return Handle(self, path, '+' in mode)

    def mkdir(self, path, mode=None):
        if mode is None:
            return super().mkdir(path)
        self.event('mkdir', path)
        if path in self.files or path in self.dirs:
            raise FileExistsError(path)
        self.dirs.add(path)
        self.attrs[path] = [mode, 1608, 1611]

    def chmod(self, path, mode):
        self.event('chmod', path)
        self.attrs[path][0] = mode

    def chown(self, path, uid, gid):
        self.event('chown', path)
        self.attrs[path][1:] = [uid, gid]

    def rmdir(self, path):
        self.event('rmdir', path)
        if any(p.startswith(path + '/') for p in self.files) or any(p.startswith(path + '/') for p in self.dirs):
            raise OSError('Not empty')
        self.dirs.remove(path)
        self.attrs.pop(path)

    def remove(self, path):
        self.event('remove', path)
        del self.files[path]
        self.attrs.pop(path)

    def rename(self, source, target):
        self.event('before_rename', target)
        if target in self.files or target in self.dirs:
            raise FileExistsError(target)
        self.files[target] = self.files.pop(source)
        self.attrs[target] = self.attrs.pop(source)
        self.event('renamed', target)

    def posix_rename(self, *args):
        raise AssertionError('Overwrite-capable rename forbidden')

    def run(self, command):
        self.event('run', command)
        if ' -r ' in command:
            path = shlex.split(command)[-1]
            if path in self.files or path in self.dirs:
                raise FileExistsError(path)
            parent = path.rsplit('/', 1)[0]
            if parent not in self.dirs:
                raise FileNotFoundError(parent)
            self.seed(path, b'', 0o600)
            return ''
        if ' -l ' in command:
            self.event('lint', shlex.split(command)[-1])
            return 'No syntax errors detected'
        if ' eval-file ' in command and M.CHECKER in command:
            return '{"status":"PASS","checks":35}'
        if M.D.I.HEALTH.RUNTIME_CHECK in shlex.split(command):
            return '{"status":"PASS","checks":17}'
        if M.H.CACHE_CODE in shlex.split(command):
            self.event('purge', None)
            return 'APPOINTMENT_SOURCE_CACHE_PURGED'
        raise AssertionError('Unexpected command: ' + command)

    def close(self):
        self.closed = True
        self.event('close', None)


class TransportTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(patch.object(socket.socket, 'connect', side_effect=AssertionError('NO NETWORK')))
        self.stack.enter_context(patch.object(M.D.urllib.request, 'urlopen', side_effect=AssertionError('NO HTTP')))
        self.stack.enter_context(patch.object(subprocess, 'Popen', side_effect=AssertionError('NO PROCESS')))
        self.r = FakeRemote()
        self.original = dict(self.r.files)
        self.value = release()
        self.factory = self.stack.enter_context(patch.object(M.H, 'Remote', return_value=self.r))
        self.native = self.stack.enter_context(patch.object(M, 'native_presentation', return_value=[{'synthetic': True}]))
        self.stack.enter_context(patch.object(M.D, 'snapshot', return_value={'protected': 'native-current'}))
        self.stack.enter_context(patch.object(M.D, 'get', side_effect=self.get))
        self.smoke = self.stack.enter_context(patch.object(M.D.I.HEALTH, 'smoke', return_value=[{'path': p, 'status': 200} for p in M.ROUTES]))
        self.inventory = self.stack.enter_context(patch.object(M.D.I, 'public_check', return_value={'items': 124}))
        self.output = self.stack.enter_context(contextlib.redirect_stdout(io.StringIO()))

    def get(self, url):
        prefix = M.ORIGIN + '/wp-content/mu-plugins/'
        if url.startswith(prefix):
            name = url[len(prefix):]
            kind = 'text/css' if name.endswith('.css') else 'application/javascript' if name.endswith('.js') else 'text/plain'
            return self.r.files[M.DIR + '/' + name], kind
        route = url[len(M.ORIGIN):]
        self.assertIn(route, M.ROUTES)
        text = BASE.replace('href="https://harmat22.hu/"', 'href="' + url + '"')
        if M.DIR + '/' + M.MAIN in self.r.files:
            text = text.replace('</body>', resources(self.value) + '</body>')
        if self.r.public_drift:
            text = self.r.public_drift(text)
        return text.encode(), 'text/html; charset=UTF-8'

    def operate(self, backup=None, reverse=False):
        return M.operate(self.value, backup, reverse)

    def backup(self):
        return next(p for p in self.r.dirs if p.startswith(M.PREFIX) and '/' not in p[len(M.PREFIX):])

    def fail_once(self, operation, predicate):
        def fail(op, path):
            if op == operation and predicate(path):
                self.r.fail = lambda *args: None
                raise RuntimeError('synthetic ' + operation)
        self.r.fail = fail

    def assert_original(self):
        self.assertEqual({p: b for p, b in self.r.files.items() if not p.startswith(M.H.BACKUPS + '/')}, self.original)
        self.assertNotIn(M.DIR + '/' + M.ASSET, self.r.dirs)
        self.assertNotIn(M.D.LOCK, self.r.dirs)
        self.assertTrue(self.r.closed)

    def test_success_private_backup_assets_first_controller_last_and_real_pure_contract(self):
        self.operate()
        events = self.r.events
        renamed = [p for op, p in events if op == 'renamed']
        self.assertEqual(renamed, [M.DIR + '/' + n for n in self.value['files']])
        backup = self.backup()
        saved = json.loads(self.r.files[backup + '/manifest.json'])
        self.assertEqual(saved['original_absent'], list(self.value['files']) + [M.ASSET])
        self.assertEqual(saved['before']['sources'][M.OLD], M.sha(self.original[M.OLD]))
        first = events.index(('mkdir', M.DIR + '/' + M.ASSET))
        self.assertLess(events.index(('written', backup + '/preflight-proof.json')), first)
        for path, data in self.r.files.items():
            if path.startswith(backup + '/'):
                self.assertEqual(self.r.attrs[path][0], 0o600)
        for name in self.value['files']:
            self.assertEqual(self.r.attrs[M.DIR + '/' + name], [0o644, 1608, 1611])
        self.assertIn('"offer_checks": 35', self.output.getvalue())
        self.assertIn('"antispam_checks": 17', self.output.getvalue())
        self.assertEqual(self.inventory.call_count, 1)
        self.assertEqual(self.smoke.call_count, 1)
        controller = M.DIR + '/' + M.MAIN
        self.assertIn(('lint', controller), events)
        self.assertTrue(any(op == 'lint' and p.endswith('.tmp') for op, p in events))

    def test_independent_verify_and_explicit_rollback(self):
        self.operate()
        backup = self.backup()
        self.operate(backup)
        self.assertIn('VERIFIED_ASSISTANT_PRESENTATION', self.output.getvalue())
        self.operate(backup, True)
        self.assertIn('ROLLED_BACK_SOURCE_ONLY', self.output.getvalue())
        removed = [p for op, p in self.r.events if op == 'remove' and p.startswith(M.DIR)]
        self.assertEqual(removed[0], M.DIR + '/' + M.MAIN)
        self.assert_original()

    def test_current_mixed_eol_assistant_raw_bytes_not_git_parity(self):
        raw = b'<?php\r\n// settled payment 0.4.1\n// raw mixed EOL\r\n'
        self.r.seed(M.OLD, raw)
        self.operate()
        backup = self.backup()
        self.assertEqual(self.r.files[backup + '/protected-' + M.sha(M.OLD.encode()) + '.bin'], raw)
        self.assertEqual(self.r.files[M.OLD], raw)

    def test_native_render_failure_never_activates(self):
        self.native.side_effect = RuntimeError('Native inline content mismatch')
        with self.assertRaisesRegex(RuntimeError, 'Native inline'):
            self.operate()
        self.assert_original()

    def test_partial_write_cleans_prefix_and_previously_published_assets(self):
        def fail(op, path):
            if op == 'written' and path.startswith(M.DIR) and '.js.' in path:
                self.r.files[path] = self.r.files[path][:3]
                self.r.fail = lambda *args: None
                raise RuntimeError('short write')
        self.r.fail = fail
        with self.assertRaisesRegex(RuntimeError, 'short write'):
            self.operate()
        self.assert_original()

    def test_uncertain_rename_recovered_even_for_controller(self):
        for name in self.value['files']:
            with self.subTest(name=name):
                self.r = FakeRemote()
                self.factory.return_value = self.r
                self.fail_once('renamed', lambda p: p == M.DIR + '/' + name)
                with self.assertRaisesRegex(RuntimeError, 'synthetic renamed'):
                    self.operate()
                self.assert_original()

    def test_stage_and_formal_lint_error_recovery(self):
        for staged in (True, False):
            with self.subTest(staged=staged):
                self.r = FakeRemote()
                self.factory.return_value = self.r
                self.fail_once('lint', lambda p: p.startswith(M.DIR) and (p.endswith('.tmp') if staged else p.endswith(M.MAIN)))
                with self.assertRaises(RuntimeError):
                    self.operate()
                self.assert_original()

    def test_private_backup_write_and_lint_failures_never_activate(self):
        for operation, suffix in (('written', '/manifest.json'), ('lint', '/' + M.CHECKER)):
            with self.subTest(operation=operation):
                self.r = FakeRemote()
                self.factory.return_value = self.r
                self.fail_once(operation, lambda p: p.startswith(M.PREFIX) and p.endswith(suffix))
                with self.assertRaises(RuntimeError):
                    self.operate()
                self.assert_original()

    def test_real_native_pure_35_and_17_counts_are_required(self):
        run = self.r.run
        def wrong(command):
            if ' eval-file ' in command:
                return '{"status":"PASS","checks":34}'
            return run(command)
        with patch.object(self.r, 'run', side_effect=wrong):
            with self.assertRaisesRegex(RuntimeError, 'Pure native offer'):
                self.operate()
        self.assert_original()

    def test_public_artifact_hash_and_mime_failures_recover(self):
        for bad_mime in (True, False):
            with self.subTest(bad_mime=bad_mime):
                self.r = FakeRemote()
                self.factory.return_value = self.r
                original = self.get
                def bad(url):
                    data, mime = original(url)
                    if url.endswith('.css'):
                        return (data, 'text/html') if bad_mime else (b'wrong', mime)
                    return data, mime
                with patch.object(M.D, 'get', side_effect=bad):
                    with self.assertRaisesRegex(RuntimeError, 'artifact hash/MIME'):
                        self.operate()
                self.assert_original()

    def test_stage_hash_drift_is_retained_never_activated(self):
        def fail(op, path):
            if op == 'chmod' and path.startswith(M.DIR) and path.endswith('.tmp'):
                if self.r.attrs[path][0] == 0o600:
                    self.r.files[path] = b'foreign bytes'
        self.r.fail = fail
        with self.assertRaises(RuntimeError):
            self.operate()
        self.assertNotIn(M.DIR + '/' + M.MAIN, self.r.files)
        self.assertTrue(any(value == b'foreign bytes' for value in self.r.files.values()))

    def test_public_proof_failure_deactivates_controller_first(self):
        def fail(op, path):
            if op == 'renamed' and path.endswith(M.MAIN):
                self.r.public_drift = lambda text: text.replace('originalAssistant();', 'changed();')
        self.r.fail = fail
        with self.assertRaisesRegex(RuntimeError, 'assistant/widget changed'):
            self.operate()
        removed = [p for op, p in self.r.events if op == 'remove' and p.startswith(M.DIR)]
        self.assertEqual(removed[0], M.DIR + '/' + M.MAIN)
        self.assert_original()

    def test_foreign_existing_paths_are_never_overwritten(self):
        self.r.seed(M.DIR + '/' + M.MAIN, b'foreign')
        with self.assertRaisesRegex(RuntimeError, 'already exist'):
            self.operate()
        self.assertEqual(self.r.files[M.DIR + '/' + M.MAIN], b'foreign')
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_foreign_target_race_retained_even_if_identical_bytes(self):
        target = M.DIR + '/' + M.REQUIRED[0]
        def fail(op, path):
            if op == 'before_rename' and path == target:
                self.r.seed(target, self.value['blobs'][M.REQUIRED[0]])
        self.r.fail = fail
        with self.assertRaises(FileExistsError):
            self.operate()
        self.assertEqual(self.r.files[target], self.value['blobs'][M.REQUIRED[0]])
        self.assertNotIn(('remove', target), self.r.events)

    def test_foreign_stage_drift_is_retained_but_controller_is_deactivated(self):
        def fail(op, path):
            if op == 'written' and path.startswith(M.DIR) and '.php.' in path:
                self.r.files[path] = b'foreign-stage'
                self.r.fail = lambda *args: None
                raise RuntimeError('foreign stage')
        self.r.fail = fail
        with self.assertRaises(RuntimeError):
            self.operate()
        self.assertTrue(any(b == b'foreign-stage' for b in self.r.files.values()))
        self.assertNotIn(M.DIR + '/' + M.MAIN, self.r.files)
        self.assertIn('retained', self.output.getvalue())

    def test_foreign_controller_retains_its_dependencies(self):
        self.operate()
        self.r.files[M.DIR + '/' + M.MAIN] = b'foreign-controller'
        with self.assertRaisesRegex(RuntimeError, 'Foreign file bytes'):
            self.operate(self.backup(), True)
        for name in self.value['files']:
            self.assertIn(M.DIR + '/' + name, self.r.files)

    def test_rollback_missing_asset_still_removes_controller(self):
        self.operate()
        del self.r.files[M.DIR + '/' + M.REQUIRED[0]]
        self.operate(self.backup(), True)
        self.assert_original()

    def test_rollback_foreign_asset_retains_it_after_deactivation(self):
        self.operate()
        target = M.DIR + '/' + M.REQUIRED[0]
        self.r.files[target] = b'foreign asset'
        with self.assertRaisesRegex(RuntimeError, 'Foreign assets retained'):
            self.operate(self.backup(), True)
        self.assertNotIn(M.DIR + '/' + M.MAIN, self.r.files)
        self.assertEqual(self.r.files[target], b'foreign asset')

    def test_backup_tampering_refuses_rollback_before_removal(self):
        self.operate()
        backup = self.backup()
        for suffix in ('manifest.json', M.MAIN, 'public-baseline-0.html'):
            with self.subTest(suffix=suffix):
                path = backup + '/' + suffix
                original = self.r.files[path]
                if suffix == 'manifest.json':
                    data = json.loads(original)
                    data['original_absent'] = []
                    self.r.files[path] = json.dumps(data).encode()
                else:
                    self.r.files[path] = b'tampered'
                with self.assertRaises(RuntimeError):
                    self.operate(backup, True)
                self.assertIn(M.DIR + '/' + M.MAIN, self.r.files)
                self.r.files[path] = original

    def test_protected_old_plugin_drift_before_activation_blocks_write(self):
        original = M.snapshot
        count = 0
        def snap(remote):
            nonlocal count
            count += 1
            if count == 2:
                remote.files[M.OLD] += b'concurrent change'
            return original(remote)
        with patch.object(M, 'snapshot', side_effect=snap):
            with self.assertRaisesRegex(RuntimeError, 'drift before activation'):
                self.operate()
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))
        self.assertTrue(self.r.files[M.OLD].endswith(b'concurrent change'))

    def test_protected_state_drift_after_activation_never_restored(self):
        def fail(op, path):
            if op == 'renamed' and path.endswith(M.MAIN):
                self.r.files[M.OLD] += b'external authorized change'
        self.r.fail = fail
        with self.assertRaisesRegex(RuntimeError, 'Protected state drift'):
            self.operate()
        self.assertNotIn(M.DIR + '/' + M.MAIN, self.r.files)
        self.assertTrue(self.r.files[M.OLD].endswith(b'external authorized change'))
        commands = [p for op, p in self.r.events if op == 'run']
        self.assertFalse(any(token in c.lower() for c in commands for token in ('db import', 'wp_mail(', 'rest_do_request(', 'update_option(', 'wp_insert_post(')))

    def test_metadata_drift_retains_changed_asset(self):
        self.operate()
        target = M.DIR + '/' + M.REQUIRED[0]
        self.r.attrs[target][1] = 9999
        with self.assertRaisesRegex(RuntimeError, 'Foreign assets retained'):
            self.operate(self.backup(), True)
        self.assertNotIn(M.DIR + '/' + M.MAIN, self.r.files)
        self.assertIn(target, self.r.files)

    def test_unsafe_metadata_template_refuses_install(self):
        self.r.attrs[M.DIR + '/' + M.HOME[0]][0] = 0o666
        with self.assertRaisesRegex(RuntimeError, 'Unsafe existing'):
            self.operate()
        self.assertFalse(any(op == 'renamed' for op, _ in self.r.events))

    def test_directory_chmod_failure_recovers_owned_directory(self):
        self.fail_once('chmod', lambda p: p == M.DIR + '/' + M.ASSET)
        with self.assertRaises(RuntimeError):
            self.operate()
        self.assert_original()

    def test_lock_foreign_owner_retained_and_connection_closed(self):
        def fail(op, path):
            if op == 'renamed' and path.endswith(M.MAIN):
                self.r.files[M.D.LOCK + '/owner'] = b'foreign-owner'
        self.r.fail = fail
        with self.assertRaisesRegex(RuntimeError, 'Lock owner changed'):
            self.operate()
        self.assertEqual(self.r.files[M.D.LOCK + '/owner'], b'foreign-owner')
        self.assertTrue(self.r.closed)

    def test_lock_cleanup_failure_still_closes_transport(self):
        self.fail_once('rmdir', lambda p: p == M.D.LOCK)
        with self.assertRaises(RuntimeError):
            self.operate()
        self.assertTrue(self.r.closed)

    def test_private_backup_directory_drift_refuses_recovery(self):
        self.operate()
        backup = self.backup()
        self.r.attrs[backup + '/' + M.ASSET][0] = 0o755
        with self.assertRaisesRegex(RuntimeError, 'Private directory'):
            self.operate(backup, True)
        self.assertIn(M.DIR + '/' + M.MAIN, self.r.files)

    def test_exact_expect_all_remote_modes_and_default_offline(self):
        with patch.object(M, 'local_release', return_value=self.value), patch.object(M, 'operate') as remote:
            with patch('sys.argv', ['deploy.py']):
                M.main()
            remote.assert_not_called()
            for mode in ('--deploy', '--verify', '--rollback'):
                args = [mode] + ([M.PREFIX + 'a' * 32] if mode != '--deploy' else [])
                for expect in ([], ['--expect', 'wrong']):
                    with patch('sys.argv', ['deploy.py'] + args + expect):
                        with self.assertRaisesRegex(RuntimeError, 'Exact reviewed aggregate'):
                            M.main()
                with patch('sys.argv', ['deploy.py'] + args + ['--expect', self.value['aggregate']]):
                    M.main()
            self.assertEqual(remote.call_count, 3)


class PublicTests(unittest.TestCase):
    def setUp(self):
        self.value = release()
        self.active = BASE.replace('</body>', resources(self.value) + '</body>')

    def test_exact_original_contract_and_inline_hashes(self):
        self.assertTrue(M.assert_public(self.active, BASE, self.value, True)['active'])
        M.assert_public(BASE, BASE, self.value, False)

    def test_reject_style_script_widget_form_seo_and_activation_drift(self):
        for text in (self.active.replace('color:red', 'color:blue'),
                     self.active.replace('originalAssistant();', 'newLead();'),
                     self.active.replace('data-harmat-local-ai-form', 'data-other'),
                     self.active.replace('Original message', 'Changed message').replace('<textarea>', '<textarea name="changed">'),
                     self.active.replace('content="native"', 'content="changed"'),
                     self.active.replace('presentation();', 'changed();'),
                     self.active.replace(resources(self.value), ''),
                     self.active.replace(resources(self.value), resources(self.value) * 2)):
            with self.subTest(text=text[-120:]):
                with self.assertRaises(RuntimeError):
                    M.assert_public(text, BASE, self.value, True)

    def test_resources_must_follow_original_script(self):
        text = BASE.replace(WIDGET, resources(self.value) + WIDGET)
        with self.assertRaisesRegex(RuntimeError, 'precedes original'):
            M.assert_public(text, BASE, self.value, True)

    def test_only_original_svg_html_attribute_case_is_equivalent(self):
        upper = BASE.replace('<button class="harmat-local-ai-launch">', '<button class="harmat-local-ai-launch"><svg viewBox="0 0 24 24" focusable="false"></svg>')
        lower = upper.replace('viewBox=', 'viewbox=')
        active = lower.replace('</body>', resources(self.value) + '</body>')
        M.assert_public(active, upper, self.value, True)
        with self.assertRaisesRegex(RuntimeError, 'Original inline'):
            M.assert_public(active.replace('0 0 24 24', '0 0 48 48'), upper, self.value, True)

    def test_single_final_lf_emission_preserves_all_other_bytes(self):
        for name in M.REQUIRED:
            self.value['blobs'][name] += b'\n'
        text = BASE.replace('</body>', resources(self.value) + '</body>')
        M.assert_public(text, BASE, self.value, True)
        for changed in (text.replace('color:green', 'color: red'),
                        text.replace('presentation();</script>', 'presentation();\n</script>'),
                        text.replace('.presentation{color:green}', '.presentation{color:green} ')):
            with self.assertRaisesRegex(RuntimeError, 'Inline presentation'):
                M.assert_public(changed, BASE, self.value, True)

    def test_local_references_allowed_foreign_refs_rejected(self):
        css = M.ORIGIN + '/wp-content/mu-plugins/' + M.REQUIRED[0]
        js = M.ORIGIN + '/wp-content/mu-plugins/' + M.REQUIRED[1]
        refs = '<link id="harmat-assistant-presentation-style" rel="stylesheet" href="' + css + '">'
        refs += '<script id="harmat-assistant-presentation-script" src="' + js + '"></script>'
        text = BASE.replace('</body>', refs + '</body>')
        M.assert_public(text, BASE, self.value, True)
        with self.assertRaisesRegex(RuntimeError, 'Foreign presentation'):
            M.assert_public(text.replace(js, 'https://foreign.test/lead.js'), BASE, self.value, True)

    def test_new_unowned_script_and_resource_event_attributes_rejected(self):
        with self.assertRaisesRegex(RuntimeError, 'unowned resource'):
            M.assert_public(self.active.replace('</body>', '<script>newLead();</script></body>'), BASE, self.value, True)
        with self.assertRaisesRegex(RuntimeError, 'resource attributes'):
            M.assert_public(self.active.replace('id="harmat-assistant-presentation-script"',
                                                'id="harmat-assistant-presentation-script" onload="newLead()"'), BASE, self.value, True)


class BundleTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        root = Path(self.stack.enter_context(tempfile.TemporaryDirectory()))
        self.root = root
        self.here = root / 'maintenance'
        self.here.mkdir()
        self.assets = root / 'wp-mu-plugins' / M.ASSET
        self.assets.mkdir(parents=True)
        self.controller = root / 'wp-mu-plugins' / M.MAIN
        self.stack.enter_context(patch.object(M, 'ROOT', root))
        self.stack.enter_context(patch.object(M, 'HERE', self.here))
        self.stack.enter_context(patch.object(M.H, 'Remote', side_effect=AssertionError('NO SSH')))
        for name in (M.CHECKER, M.SELF, M.TEST, M.NATIVE):
            (self.here / name).write_bytes(b'pure support\n')
        (self.assets / 'assistant-presentation.css').write_bytes(b'.test{color:red}\r\n')
        (self.assets / 'assistant-presentation.js').write_bytes(b'// no requests\r\nvoid 0;\r\n')
        self.pins()

    def pins(self):
        pairs = []
        for path in sorted(self.assets.iterdir()):
            if path.is_file():
                pairs.append("'%s'=>'%s'" % (path.name, M.sha(path.read_bytes().replace(b'\r\n', b'\n'))))
        self.controller.write_text('<?php return array(' + ','.join(pairs) + ');\n', encoding='ascii')

    def test_exact_files_normalized_lf_and_optional_license(self):
        first = M.local_release()
        self.assertEqual(first['files'], M.REQUIRED + (M.MAIN,))
        self.assertNotIn(b'\r', first['blobs'][M.REQUIRED[0]])
        (self.assets / 'LICENSE-lucide.txt').write_bytes(b'license\n')
        self.pins()
        second = M.local_release()
        self.assertIn(M.LICENSE, second['files'])
        self.assertNotEqual(first['aggregate'], second['aggregate'])
        (self.assets / 'tests').mkdir()
        (self.assets / 'tests' / 'widget-fixture.php').write_bytes(b'not deployed')
        self.assertEqual(M.local_release()['aggregate'], second['aggregate'])

    def test_asset_drift_or_extra_file_rejected(self):
        (self.assets / 'assistant-presentation.js').write_bytes(b'changed')
        with self.assertRaisesRegex(RuntimeError, 'pin mismatch'):
            M.local_release()
        self.pins()
        (self.assets / 'other.php').write_bytes(b'<?php malicious();')
        with self.assertRaisesRegex(RuntimeError, 'Unexpected release asset'):
            M.local_release()

    def test_network_and_backend_primitives_rejected(self):
        for payload in (b'fetch("/lead")', b'new XMLHttpRequest()', b'navigator.sendBeacon("/lead")',
                        b'import("https://foreign.test/api.js")', b'var x="https://foreign.test/api";'):
            (self.assets / 'assistant-presentation.js').write_bytes(payload)
            self.pins()
            with self.subTest(payload=payload):
                with self.assertRaises(RuntimeError):
                    M.local_release()
        (self.assets / 'assistant-presentation.js').write_bytes(b'void 0;')
        self.pins()
        self.controller.write_text(self.controller.read_text() + 'wp_mail("x");', encoding='ascii')
        with self.assertRaisesRegex(RuntimeError, 'Non-presentation'):
            M.local_release()

    def test_support_bytes_are_aggregate_pinned(self):
        first = M.local_release()['aggregate']
        (self.here / M.CHECKER).write_bytes(b'changed checker')
        self.assertNotEqual(M.local_release()['aggregate'], first)


if __name__ == '__main__':
    unittest.main()
