"""Stdlib-only offline regressions. Run with python -B <this file>.

All transport/process entry points are blocked; Git output and remote files are
synthetic. No PHP, Apache, SSH, browser, database or production checks run here.
"""
import builtins
from contextlib import ExitStack, contextmanager, redirect_stdout
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import socket
import stat
import subprocess
import sys
from types import SimpleNamespace
import unittest
from unittest.mock import Mock, patch
import urllib.error
import urllib.request


sys.dont_write_bytecode = True
HERE = Path(__file__).resolve().parent
REVIEWED_BLOCK = b"""    if ($post_id === 3841 && $title === 'A1-F-L1') {
        $sales_area = get_post_meta($post_id, '_harmat_sales_area', true);
        if (is_scalar($sales_area) && !is_bool($sales_area) && is_numeric($sales_area) && is_finite((float) $sales_area) && (float) $sales_area > 0) {
            $sale_area = (float) $sales_area;
        }
    }
"""


@contextmanager
def offline():
    """Fail before any process, network, optional SSH import or disk mutation."""
    real_import = builtins.__import__
    real_open, real_io_open = builtins.open, io.open

    def guarded_import(name, *args, **kwargs):
        if name.split('.')[0] == 'paramiko':
            raise AssertionError('Optional SSH dependency imported')
        return real_import(name, *args, **kwargs)

    def reader(opener):
        def guarded(file, mode='r', *args, **kwargs):
            if any(flag in mode for flag in 'wax+'):
                raise AssertionError('Disk write attempted')
            return opener(file, mode, *args, **kwargs)
        return guarded

    with ExitStack() as stack:
        stack.enter_context(patch.object(builtins, '__import__', guarded_import))
        stack.enter_context(patch.object(builtins, 'open', reader(real_open)))
        stack.enter_context(patch.object(io, 'open', reader(real_io_open)))
        for owner, names in (
            (subprocess, ('Popen', 'check_output')),
            (socket, ('create_connection',)),
            (socket.socket, ('connect', 'connect_ex')),
            (urllib.request, ('urlopen', 'build_opener')),
            (os, ('system', 'open', 'mkdir', 'remove', 'unlink', 'rename', 'replace')),
            (Path, ('write_bytes', 'write_text')),
        ):
            for name in names:
                stack.enter_context(patch.object(owner, name, side_effect=AssertionError(
                    'Offline fixture forbids ' + name)))
        yield


def load(filename):
    spec = importlib.util.spec_from_file_location('offline_' + filename.replace('-', '_'), HERE / filename)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


with offline():
    A = load('2026-10-16-deploy-area-https.py')
    P = load('2026-10-16-probe-https.py')
STAGE_METADATA = A.D.HELPER.Remote.stage_metadata


class MemoryRoot:
    def __init__(self, files, name=''):
        self.files, self.name = files, name

    def __truediv__(self, name):
        return MemoryRoot(self.files, name)

    def read_bytes(self):
        return self.files[self.name]


def originals():
    area = (b'<?php\r\n// untouched LF\n' + A.ANCHOR + b'\r\n'
            + b'// sample detail\r\n// lone CR\r// hero\n'
            + A.ANCHOR + b'\r\n// trailing bytes without newline')
    htaccess = (b'# cache\r\nHeader set X-Fixture "untouched"\n\r\n'
                b'# BEGIN WordPress\nRewriteEngine On\r\n# END WordPress\n# tail\r')
    return area, htaccess


def fixture_blobs():
    area, _ = originals()
    offset = area.rfind(A.ANCHOR + b'\r\n') + len(A.ANCHOR + b'\r\n')
    candidate = area[:offset] + REVIEWED_BLOCK.replace(b'\n', b'\r\n') + area[offset:]
    return {
        A.SOURCE: candidate.replace(b'\r\n', b'\n'),
        A.TEST: b'<?php\necho "PASS 1402";\n',
        A.NATIVE: b'<?php\n// synthetic native fixture\n',
        A.RULE: (A.ROOT / A.RULE).read_bytes().replace(b'\r\n', b'\n'),
    }


@contextmanager
def pin_original(module, original, digest):
    # Only synthetic original bytes bypass the production pin; all candidates,
    # manifests, drift and other inputs retain the real SHA256 implementation.
    real_sha = module.D.sha
    with patch.object(module.D, 'sha', side_effect=lambda data: digest if data == original else real_sha(data)):
        yield


class FakeRemote:
    """Byte store plus actual shared metadata/owned-cleanup implementations."""
    metadata = (0o664, 1608, 1611)
    remove_owned = A.D.HELPER.Remote.remove_owned

    def __init__(self, target=A.AREA, original=b'original'):
        self.files = {target: original}
        self.attrs = {target: self.metadata}
        self.dirs = {A.D.HELPER.BACKUPS, A.D.DIRECTORY}
        self.events = []
        self.sftp = self
        self.private_group_verified = True
        self.target = target
        self.fail = None
        self.before_activation = None
        self.after_rename = None
        self.closed = False
        self.data = {'customer': 'current', 'property': 'current', 'crm': 'current'}

    def event(self, name, *args):
        self.events.append((name, *args))
        if self.fail == name:
            self.fail = None
            raise RuntimeError('Injected ' + name)

    def read(self, path, private=False):
        self.event('read', path)
        if path not in self.files:
            raise FileNotFoundError(path)
        if private:
            A.D.require(self.attrs[path][0] == 0o600, 'Private fake mode')
        return self.files[path]

    def info(self, path):
        self.event('info', path)
        mode, uid, gid = self.attrs[path]
        return SimpleNamespace(st_mode=stat.S_IFREG | mode, st_uid=uid, st_gid=gid)

    def write_new(self, path, content):
        self.event('write', path)
        A.D.require(path not in self.files, 'Exclusive fake create')
        self.files[path], self.attrs[path] = content, (0o600, 999, 999)

    def lint(self, path):
        self.event('formal_lint' if path == self.target else 'stage_lint', path)

    def stage_metadata(self, path, metadata):
        STAGE_METADATA(self, path, metadata)
        if self.before_activation:
            self.before_activation(self, path)

    def chown(self, path, uid, gid):
        self.event('chown', path, uid, gid)
        self.attrs[path] = (self.attrs[path][0], uid, gid)

    def chmod(self, path, mode):
        self.event('chmod', path, mode)
        self.attrs[path] = (mode, *self.attrs[path][1:])

    def posix_rename(self, stage, target):
        self.event('rename', stage, target)
        A.D.require(stage.rsplit('/', 1)[0] == target.rsplit('/', 1)[0], 'Cross-directory rename')
        self.files[target], self.attrs[target] = self.files.pop(stage), self.attrs.pop(stage)
        if self.after_rename:
            self.after_rename(self, target)
        self.event('after_rename', target)

    def remove(self, path):
        self.event('remove', path)
        del self.files[path]
        del self.attrs[path]

    def mkdir(self, path):
        self.event('mkdir', path)
        A.D.require(path not in self.dirs, 'Exclusive fake directory')
        self.dirs.add(path)

    def directory(self, path, private=False):
        A.D.require(path in self.dirs, 'Missing fake directory')

    def rmdir(self, path):
        self.event('rmdir', path)
        self.dirs.remove(path)

    def run(self, command):
        self.event('run', command)
        if '/fixture.php' in command:
            return 'PASS 1402'
        if '/native.php' in command:
            return json.dumps({'status': 'PASS', 'unchanged_heroes': 123})
        raise AssertionError('Unreviewed remote command: ' + command)

    def purge(self):
        self.event('purge')

    def close(self):
        self.event('close')
        self.closed = True


class OfflineCase(unittest.TestCase):
    def setUp(self):
        self.stack = ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(offline())


class SourceTests(OfflineCase):
    def test_reviewed_block_is_exactly_six_lines_and_checkout_has_one_copy(self):
        self.assertEqual(A.BLOCK, REVIEWED_BLOCK)
        self.assertEqual(len(A.BLOCK.splitlines()), 6)
        source = (A.ROOT / A.SOURCE).read_bytes().replace(b'\r\n', b'\n')
        self.assertEqual(source.count(REVIEWED_BLOCK), 1)

    def test_area_insertion_preserves_every_original_mixed_line_end_byte(self):
        original, _ = originals()
        offset = original.rfind(A.ANCHOR + b'\r\n') + len(A.ANCHOR + b'\r\n')
        with pin_original(A, original, A.AREA_SHA):
            candidate = A.patch('area', original, fixture_blobs())
        inserted = REVIEWED_BLOCK.replace(b'\n', b'\r\n')
        self.assertEqual(candidate, original[:offset] + inserted + original[offset:])
        self.assertEqual(candidate[:offset] + candidate[offset + len(inserted):], original)

    def test_area_rejects_stale_original_and_reviewed_source_drift(self):
        original, _ = originals()
        with self.assertRaisesRegex(RuntimeError, 'baseline changed'):
            A.patch('area', original + b'drift', fixture_blobs())
        with pin_original(A, original, A.AREA_SHA):
            blobs = fixture_blobs()
            blobs[A.SOURCE] += b'// unrelated edit\n'
            with self.assertRaisesRegex(RuntimeError, 'parity failed'):
                A.patch('area', original, blobs)

    def test_area_rejects_missing_or_duplicate_formula_boundaries(self):
        original, _ = originals()
        for raw in (original.replace(A.ANCHOR, b'other', 1), original + A.ANCHOR + b'\r\n'):
            with self.subTest(raw=raw), pin_original(A, raw, A.AREA_SHA):
                blobs = fixture_blobs()
                blobs[A.SOURCE] = raw.replace(b'\r\n', b'\n') + REVIEWED_BLOCK
                with self.assertRaisesRegex(RuntimeError, 'two legacy'):
                    A.patch('area', raw, blobs)

    def test_https_exact_reviewed_block_precedes_sole_marker_without_byte_loss(self):
        _, original = originals()
        rule = fixture_blobs()[A.RULE]
        offset = original.index(b'# BEGIN WordPress\n')
        with pin_original(A, original, A.HTACCESS_SHA):
            candidate = A.patch('https', original, {A.RULE: rule})
        self.assertEqual(candidate, original[:offset] + rule + b'\n' + original[offset:])
        self.assertEqual(candidate[:offset] + candidate[offset + len(rule) + 1:], original)

    def test_https_rejects_stale_already_installed_missing_and_duplicate_markers(self):
        _, original = originals()
        with self.assertRaisesRegex(RuntimeError, 'baseline changed'):
            A.patch('https', original + b'drift', fixture_blobs())
        variants = (
            original + b'\n# BEGIN Harmat public HTTPS\n',
            original.replace(b'# BEGIN WordPress\n', b'# BEGIN WordPress\r\n'),
            original + b'\n# BEGIN WordPress\n',
        )
        for raw in variants:
            with self.subTest(raw=raw), pin_original(A, raw, A.HTACCESS_SHA):
                with self.assertRaises(RuntimeError):
                    A.patch('https', raw, fixture_blobs())

    def test_bundle_allows_only_reviewed_insertion_and_uses_mocked_git(self):
        blobs = fixture_blobs()
        baseline = blobs[A.SOURCE].replace(REVIEWED_BLOCK, b'', 1)
        with patch.object(A, 'ROOT', MemoryRoot(blobs)), patch.object(
                A.subprocess, 'check_output', return_value=baseline) as git:
            descriptor, result = A.bundle()
            self.assertEqual(result, blobs)
            self.assertEqual(descriptor['files'][A.RULE], hashlib.sha256(blobs[A.RULE]).hexdigest())
            git.assert_called_once_with(['git', 'show', A.BASE + ':' + A.SOURCE], cwd=A.ROOT)
            for changed in (blobs[A.SOURCE] + b'// unrelated\n', blobs[A.SOURCE] + REVIEWED_BLOCK):
                blobs[A.SOURCE] = changed
                with self.subTest(changed=changed), self.assertRaisesRegex(RuntimeError, 'six-line'):
                    A.bundle()


class RuleTests(OfflineCase):
    def setUp(self):
        super().setUp()
        self.rule = fixture_blobs()[A.RULE].decode('ascii')
        self.conditions = re.findall(r'^RewriteCond %\{([^}]+)\} (\S+)(?: (\[NC\]))?$', self.rule, re.M)

    def matches_text(self, **overrides):
        values = {'HTTP_HOST': 'harmat22.hu', 'HTTP:X-Forwarded-Proto': 'http',
                  'REQUEST_METHOD': 'GET', 'REQUEST_URI': '/galeria/', 'QUERY_STRING': ''}
        values.update(overrides)
        # This interprets only the pinned regex conditions, not Apache routing.
        for variable, expression, flags in self.conditions:
            negative = expression.startswith('!')
            match = re.search(expression[1:] if negative else expression,
                              values[variable], re.I if flags else 0)
            if bool(match) == negative:
                return False
        return True

    def test_exact_conditions_fixed_origin_noescape_and_implicit_query_preservation(self):
        self.assertEqual(self.conditions, [
            ('HTTP_HOST', r'^(www\.)?harmat22\.hu(:80)?$', '[NC]'),
            ('HTTP:X-Forwarded-Proto', '^http$', '[NC]'),
            ('REQUEST_METHOD', '^(GET|HEAD)$', ''),
            ('REQUEST_URI', '!^/(wp-admin|wp-json|agent|sales|sales-admin|client|customer|ugyfel)(/|$)', '[NC]'),
            ('REQUEST_URI', r'!\.php(/|$)', '[NC]'),
            ('QUERY_STRING', '!(^|&)(rest_route|action|_wpnonce|_ajax_nonce|nonce|token|key|preview|preview_id|preview_nonce|harmat_agent_portal|harmat_sales_portal|harmat_customer_portal|harmat_sales_shortcut)=', '[NC]'),
        ])
        directives = [line for line in self.rule.splitlines() if line and not line.startswith('#')]
        self.assertEqual(directives[0:2], ['<IfModule mod_rewrite.c>', 'RewriteEngine On'])
        self.assertEqual(directives[-2:], ['RewriteRule ^ https://harmat22.hu%{REQUEST_URI} [R=301,L,NE]', '</IfModule>'])
        self.assertEqual(len(directives), 10)

    def test_method_host_and_forwarded_protocol_boundaries(self):
        cases = {
            'REQUEST_METHOD': (('GET', 'HEAD'), ('POST', 'PUT', 'DELETE', 'OPTIONS', 'get')),
            'HTTP_HOST': (('harmat22.hu', 'www.harmat22.hu', 'HARMAT22.HU:80'),
                          ('harmat22.hu.evil', 'evil.hu', 'harmat22.hu:443', 'harmat22.hu:8080')),
            'HTTP:X-Forwarded-Proto': (('http', 'HTTP'), ('https', '', 'http,https', 'https,http')),
        }
        for variable, (allowed, excluded) in cases.items():
            for value in allowed + excluded:
                with self.subTest(variable=variable, value=value):
                    self.assertEqual(self.matches_text(**{variable: value}), value in allowed)

    def test_every_private_path_and_php_boundary_is_excluded(self):
        for prefix in ('wp-admin', 'wp-json', 'agent', 'sales', 'sales-admin', 'client', 'customer', 'ugyfel'):
            for path in ('/' + prefix, '/' + prefix.upper() + '/nested'):
                with self.subTest(path=path):
                    self.assertFalse(self.matches_text(REQUEST_URI=path))
            self.assertTrue(self.matches_text(REQUEST_URI='/' + prefix + '-public/'))
        for path in ('/wp-login.php', '/index.PHP/api', '/nested/file.php'):
            with self.subTest(path=path):
                self.assertFalse(self.matches_text(REQUEST_URI=path))
        for path in ('/', '/property/a1-f-l1/', '/galeria/', '/file.php.jpg', '/a%20b/%23x'):
            with self.subTest(path=path):
                self.assertTrue(self.matches_text(REQUEST_URI=path))

    def test_every_sensitive_query_key_and_parameter_boundary_is_excluded(self):
        keys = ('rest_route', 'action', '_wpnonce', '_ajax_nonce', 'nonce', 'token', 'key',
                'preview', 'preview_id', 'preview_nonce', 'harmat_agent_portal',
                'harmat_sales_portal', 'harmat_customer_portal', 'harmat_sales_shortcut')
        for key in keys:
            for query in (key + '=', 'rooms=3&' + key.upper() + '=private'):
                with self.subTest(query=query):
                    self.assertFalse(self.matches_text(QUERY_STRING=query))
            self.assertTrue(self.matches_text(QUERY_STRING='not_' + key + '=public'))
        self.assertTrue(self.matches_text(QUERY_STRING='rooms=3&utm_source=a%26b&utm_medium=check'))


class RequestTests(OfflineCase):
    def test_imports_and_default_entrypoints_are_offline(self):
        with redirect_stdout(io.StringIO()) as output:
            fresh_a = load('2026-10-16-deploy-area-https.py')
            fresh_p = load('2026-10-16-probe-https.py')
        self.assertEqual(output.getvalue(), '')
        for module in (fresh_a, fresh_p):
            with self.subTest(module=module.__name__), patch.object(
                    module.D.HELPER, 'Remote', side_effect=AssertionError('No SSH')) as remote:
                with ExitStack() as stack, redirect_stdout(io.StringIO()):
                    stack.enter_context(patch.object(sys, 'argv', [module.__file__]))
                    if module is fresh_a:
                        blobs = fixture_blobs()
                        stack.enter_context(patch.object(module, 'ROOT', MemoryRoot(blobs)))
                        stack.enter_context(patch.object(module.subprocess, 'check_output', return_value=
                                                       blobs[A.SOURCE].replace(REVIEWED_BLOCK, b'', 1)))
                    module.main()
                remote.assert_not_called()

    def test_live_mutation_switches_require_explicit_permission_before_connect(self):
        for args in (['--deploy-area'], ['--deploy-https'], ['--rollback', 'not-a-backup']):
            with self.subTest(args=args), patch.object(A, 'bundle', return_value=({}, {})), patch.object(
                    sys, 'argv', [A.__file__, *args]), patch.object(A.D.HELPER, 'Remote') as remote:
                with self.assertRaisesRegex(RuntimeError, 'live-write switch'):
                    A.main()
                remote.assert_not_called()

    def test_wrong_reviewed_bundle_pin_stops_before_connect(self):
        with patch.object(A, 'bundle', return_value=({'combined_sha256': 'reviewed'}, {})), patch.object(
                A.D, 'checkout_head', return_value=A.BASE), patch.object(sys, 'argv', [
                    A.__file__, '--deploy-area', '--allow-live-write', '--expected-sha256', 'stale'
                ]), patch.object(A.D.HELPER, 'Remote') as remote:
            with self.assertRaisesRegex(RuntimeError, 'checkout/bundle pin'):
                A.main()
            remote.assert_not_called()

    def test_no_redirect_handler_does_not_follow_location(self):
        request = urllib.request.Request('http://harmat22.hu/example?x=1')
        for module in (A, P):
            for code in (301, 302, 307, 308):
                with self.subTest(module=module.__name__, code=code):
                    self.assertIsNone(module.D.NoRedirect().redirect_request(
                        request, None, code, 'redirect', {}, 'https://harmat22.hu/example?x=1'))

    def test_403_stops_once_before_body_read_without_retry_or_identity_change(self):
        class Body(io.BytesIO):
            def __init__(self):
                super().__init__(b'forbidden')
                self.reads = 0

            def read(self, *args):
                self.reads += 1
                return super().read(*args)

        for module in (A, P):
            body = Body()
            error = urllib.error.HTTPError('http://harmat22.hu/', 403, 'Forbidden', {}, body)
            opener = Mock()
            opener.open.side_effect = error
            with self.subTest(module=module.__name__), patch.object(
                    urllib.request, 'build_opener', return_value=opener) as factory:
                with self.assertRaisesRegex(RuntimeError, 'HTTP403: stop'):
                    module.request('http://harmat22.hu/')
                factory.assert_called_once()
                self.assertIsInstance(factory.call_args.args[0], module.D.NoRedirect)
                opener.open.assert_called_once()
                req = opener.open.call_args.args[0]
                self.assertEqual(req.full_url, 'http://harmat22.hu/')
                self.assertEqual(req.get_header('User-agent'), module.D.AGENT)
                self.assertEqual(opener.open.call_args.kwargs, {'timeout': 30})
                self.assertEqual(body.reads, 0)

    def test_redirect_response_is_returned_without_following_or_losing_location(self):
        for module in (A, P):
            error = urllib.error.HTTPError('http://harmat22.hu/a%20b?x=%26', 301, 'Moved',
                                          {'Location': 'https://harmat22.hu/a%20b?x=%26'}, io.BytesIO(b''))
            opener = Mock()
            opener.open.side_effect = error
            with self.subTest(module=module.__name__), patch.object(
                    urllib.request, 'build_opener', return_value=opener):
                result = module.request(error.url, method='HEAD')
                self.assertEqual((result['status'], result['location']), (301, error.headers['Location']))
                opener.open.assert_called_once()


class AtomicTests(OfflineCase):
    def setUp(self):
        super().setUp()
        self.remote = FakeRemote()

    def activate(self, php=True):
        return A.atomic(self.remote, self.remote.target, A.D.sha(b'original'), b'candidate',
                        self.remote.metadata, php)

    def stages(self):
        return [path for path in self.remote.files if '/.harmat-area-https-' in path]

    def test_success_orders_stage_lint_metadata_ownership_rename_formal_lint_cleanup(self):
        self.activate()
        events = self.remote.events
        stage = events[0][1]
        critical = [event for event in events if event[0] in
                    ('write', 'stage_lint', 'chown', 'chmod', 'rename', 'formal_lint')]
        self.assertEqual([event[0] for event in critical],
                         ['write', 'stage_lint', 'chown', 'chmod', 'rename', 'formal_lint'])
        metadata_end = events.index(('chmod', stage, self.remote.metadata[0]))
        rename_at = events.index(('rename', stage, self.remote.target))
        self.assertIn(('read', self.remote.target), events[metadata_end:rename_at])
        self.assertIn(('info', self.remote.target), events[metadata_end:rename_at])
        self.assertEqual(stage.rsplit('/', 1)[0], self.remote.target.rsplit('/', 1)[0])
        self.assertEqual(events[-1], ('read', stage))
        self.assertEqual(self.remote.files[self.remote.target], b'candidate')
        self.assertEqual(self.remote.attrs[self.remote.target], self.remote.metadata)
        self.assertEqual(self.stages(), [])

    def test_https_activation_never_php_lints(self):
        self.remote = FakeRemote(A.HTACCESS)
        self.activate(php=False)
        self.assertFalse(any('lint' in event[0] for event in self.remote.events))

    def test_pre_rename_failures_preserve_original_and_clean_exact_owned_stage(self):
        for failure in ('write', 'stage_lint', 'chown', 'chmod', 'rename'):
            self.remote = FakeRemote()
            self.remote.fail = failure
            with self.subTest(failure=failure), self.assertRaisesRegex(RuntimeError, 'Injected'):
                self.activate()
            self.assertEqual(self.remote.files, {self.remote.target: b'original'})
            self.assertEqual(self.stages(), [])

    def test_target_byte_or_metadata_drift_stops_before_rename(self):
        def change_bytes(remote, stage):
            remote.files[remote.target] = b'other editor'

        def change_owner(remote, stage):
            remote.attrs[remote.target] = (0o644, 42, 42)

        for change in (change_bytes, change_owner):
            self.remote = FakeRemote()
            self.remote.before_activation = change
            with self.subTest(change=change.__name__), self.assertRaisesRegex(RuntimeError, 'Target drift'):
                self.activate()
            self.assertFalse(any(event[0] == 'rename' for event in self.remote.events))
            self.assertEqual(self.stages(), [])
            if change is change_bytes:
                self.assertEqual(self.remote.files[self.remote.target], b'other editor')

    def test_changed_stage_is_not_activated_or_deleted_as_owned(self):
        self.remote.before_activation = lambda remote, stage: remote.files.__setitem__(stage, b'foreign bytes')
        with self.assertRaisesRegex(RuntimeError, 'Owned temporary bytes changed'):
            self.activate()
        self.assertEqual(self.remote.files[self.remote.target], b'original')
        self.assertEqual([self.remote.files[path] for path in self.stages()], [b'foreign bytes'])
        self.assertFalse(any(event[0] == 'rename' for event in self.remote.events))

    def test_after_rename_failure_leaves_candidate_for_callers_owned_rollback(self):
        for failure in ('after_rename', 'formal_lint'):
            self.remote = FakeRemote()
            self.remote.fail = failure
            with self.subTest(failure=failure), self.assertRaisesRegex(RuntimeError, 'Injected'):
                self.activate()
            self.assertEqual(self.remote.files[self.remote.target], b'candidate')
            self.assertEqual(self.stages(), [])

    def test_nonregular_or_symlink_target_is_rejected(self):
        for mode in (stat.S_IFLNK | 0o644, stat.S_IFDIR | 0o755):
            with self.subTest(mode=mode), patch.object(self.remote, 'info', return_value=
                    SimpleNamespace(st_mode=mode, st_uid=1608, st_gid=1611)):
                with self.assertRaisesRegex(RuntimeError, 'non-symlink regular'):
                    A.target_info(self.remote, self.remote.target)


class LifecycleTests(OfflineCase):
    def setUp(self):
        super().setUp()
        self.original, self.htaccess = originals()
        self.blobs = fixture_blobs()
        self.descriptor = {'combined_sha256': 'reviewed'}
        self.remote = FakeRemote(A.AREA, self.original)
        self.stack.enter_context(pin_original(A, self.original, A.AREA_SHA))
        self.stack.enter_context(patch.object(A, 'bundle', return_value=(self.descriptor, self.blobs)))
        self.stack.enter_context(patch.object(A.D, 'checkout_head', return_value=A.BASE))
        self.stack.enter_context(patch.object(A.D.HELPER, 'Remote', return_value=self.remote))
        self.stack.enter_context(patch.object(A, 'snapshot', side_effect=lambda remote: {
            'data': dict(remote.data), 'other_sources': {'migratedSnippets': A.D.sha(remote.files[A.AREA])}}))
        self.stack.enter_context(patch.object(A.D, 'get', return_value='synthetic gallery'))
        self.stack.enter_context(patch.object(A.D, 'gallery_signature', return_value=['unchanged']))
        self.stack.enter_context(patch.object(A.D, 'health', return_value={'offline': True}))
        self.stack.enter_context(patch.object(A, 'area_public', return_value={'offline': True}))
        self.output = self.stack.enter_context(redirect_stdout(io.StringIO()))

    def main(self, *args):
        with patch.object(sys, 'argv', [A.__file__, *args]):
            A.main()

    def deploy(self):
        self.main('--deploy-area', '--allow-live-write', '--expected-sha256', 'reviewed')

    def renames(self):
        return [event for event in self.remote.events if event[0] == 'rename']

    def assert_clean_closed(self):
        self.assertTrue(self.remote.closed)
        self.assertNotIn(A.LOCK, self.remote.dirs)
        self.assertFalse(any('/.harmat-area-https-' in path for path in self.remote.files))

    def test_success_backups_precede_activation_and_result_follows_checks(self):
        self.deploy()
        paths = [event[1] for event in self.remote.events if event[0] == 'write']
        backup = next(path.rsplit('/', 1)[0] for path in paths if path.endswith('/manifest.json'))
        rename_at = self.remote.events.index(self.renames()[0])
        self.assertLess(self.remote.events.index(('write', backup + '/original.php')), rename_at)
        self.assertLess(self.remote.events.index(('write', backup + '/manifest.json')), rename_at)
        self.assertEqual(self.remote.files[backup + '/original.php'], self.original)
        self.assertIn(backup + '/result.json', self.remote.files)
        self.assertEqual(len(self.renames()), 1)
        self.assert_clean_closed()

    def https_preflight(self):
        probe = A.D.HELPER.BACKUPS + '/https-probe-' + 'b' * 32
        self.remote.dirs.add(probe)
        self.remote.write_new(probe + '/tested-public-https.htaccess', self.blobs[A.RULE])
        self.remote.write_new(probe + '/results.json', json.dumps([{'status': 200}] * 20).encode('ascii'))
        self.remote.files[A.HTACCESS] = self.htaccess
        self.remote.attrs[A.HTACCESS] = self.remote.metadata
        self.remote.target = A.HTACCESS
        return probe

    def deploy_https(self, probe):
        self.main('--deploy-https', '--allow-live-write', '--expected-sha256', 'reviewed',
                  '--https-preflight-backup', probe)

    def test_https_success_requires_exact_preflight_rule_and_leaves_area_untouched(self):
        probe = self.https_preflight()
        with pin_original(A, self.htaccess, A.HTACCESS_SHA), patch.object(
                A, 'https_public', return_value={'offline': True}) as public:
            self.deploy_https(probe)
        self.assertEqual(self.remote.files[A.AREA], self.original)
        self.assertEqual(self.remote.files[A.HTACCESS], self.htaccess.replace(
            b'# BEGIN WordPress\n', self.blobs[A.RULE] + b'\n# BEGIN WordPress\n', 1))
        self.assertEqual({event[2] for event in self.renames()}, {A.HTACCESS})
        self.assertFalse(any('lint' in event[0] or event[0] == 'run' for event in self.remote.events))
        public.assert_called_once()
        self.assert_clean_closed()

    def test_https_preflight_rule_or_proof_drift_stops_before_any_activation(self):
        probe = self.https_preflight()
        for changed in ('rule', 'proof'):
            with self.subTest(changed=changed):
                self.remote.files[probe + '/tested-public-https.htaccess'] = (
                    self.blobs[A.RULE] + (b'drift' if changed == 'rule' else b''))
                self.remote.files[probe + '/results.json'] = json.dumps([{'status': 200}] * 19).encode('ascii')
                with self.assertRaisesRegex(RuntimeError, 'differs from release|Incomplete isolated'):
                    self.deploy_https(probe)
                self.assertEqual(self.renames(), [])
                self.assertFalse(any(event == ('mkdir', A.LOCK) for event in self.remote.events))
                self.assertEqual(self.remote.files[A.HTACCESS], self.htaccess)
                self.assert_clean_closed()

    def test_post_activation_failures_automatically_restore_only_owned_file(self):
        for failure in ('after_rename', 'formal_lint', 'purge'):
            with self.subTest(failure=failure):
                self.remote.files[A.AREA] = self.original
                self.remote.fail = failure
                before = len(self.renames())
                with self.assertRaisesRegex(RuntimeError, 'Injected'):
                    self.deploy()
                self.assertEqual(self.remote.files[A.AREA], self.original)
                self.assertEqual(len(self.renames()) - before, 2)
                self.assert_clean_closed()

    def test_protected_data_drift_triggers_file_rollback_but_never_rewinds_data(self):
        def new_customer(remote, target):
            remote.data['customer'] = 'new inquiry after activation'

        self.remote.after_rename = new_customer
        with self.assertRaisesRegex(RuntimeError, 'never restore customer data'):
            self.deploy()
        self.assertEqual(self.remote.files[A.AREA], self.original)
        self.assertEqual(self.remote.data['customer'], 'new inquiry after activation')
        self.assertEqual(len(self.renames()), 2)
        self.assert_clean_closed()

    def test_automatic_rollback_refuses_to_overwrite_later_editor(self):
        def other_editor(remote, target):
            remote.files[target] = b'newer third-party file'

        self.remote.after_rename = other_editor
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assertEqual(self.remote.files[A.AREA], b'newer third-party file')
        self.assertEqual(len(self.renames()), 1)
        self.assert_clean_closed()

    def install_backup(self, kind='area', target=None):
        target = target or (A.AREA if kind == 'area' else A.HTACCESS)
        original = self.original if kind == 'area' else self.htaccess
        released = b'released ' + kind.encode('ascii')
        backup = A.PREFIX + 'a' * 32
        self.remote.dirs.add(backup)
        self.remote.files[target], self.remote.attrs[target] = released, self.remote.metadata
        manifest = {'kind': kind, 'target': target, 'metadata': list(self.remote.metadata),
                    'original_sha256': A.D.sha(original), 'released_sha256': A.D.sha(released),
                    'before': {'data': {'customer': 'obsolete backup data'}}}
        self.remote.write_new(backup + '/manifest.json', json.dumps(manifest).encode('ascii'))
        self.remote.write_new(backup + ('/original.php' if kind == 'area' else '/original.htaccess'), original)
        return backup, target, original

    def test_explicit_rollback_restores_only_pinned_source_or_config_never_data(self):
        for kind in ('area', 'https'):
            with self.subTest(kind=kind):
                self.remote = FakeRemote()
                # The factory follows this test's in-memory replacement only.
                with patch.object(A.D.HELPER, 'Remote', return_value=self.remote):
                    backup, target, original = self.install_backup(kind)
                    self.remote.target = target
                    self.remote.data['customer'] = 'new inquiry must survive'
                    with patch.object(A, 'snapshot', side_effect=AssertionError('No database rollback')):
                        self.main('--rollback', backup, '--allow-live-write')
                    self.assertEqual(self.remote.files[target], original)
                    self.assertEqual(self.remote.data['customer'], 'new inquiry must survive')
                    self.assertEqual({event[2] for event in self.renames()}, {target})
                    self.assert_clean_closed()

    def test_explicit_rollback_rejects_stale_release_and_corrupt_original(self):
        for changed in ('release', 'original'):
            self.remote = FakeRemote()
            with self.subTest(changed=changed), patch.object(A.D.HELPER, 'Remote', return_value=self.remote):
                backup, target, _ = self.install_backup()
                if changed == 'release':
                    self.remote.files[target] = b'newer third-party file'
                else:
                    self.remote.files[backup + '/original.php'] += b'corrupt'
                before = self.remote.files[target]
                with self.assertRaisesRegex(RuntimeError, 'no longer owned|original hash changed'):
                    self.main('--rollback', backup, '--allow-live-write')
                self.assertEqual(self.remote.files[target], before)
                self.assertEqual(self.renames(), [])
                self.assertTrue(self.remote.closed)


if __name__ == '__main__':
    unittest.main(verbosity=2)
