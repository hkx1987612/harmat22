"""Bounded stdlib-only orchestration tests: python -B <this file>.

No real transport helper is imported, and no PHP, SSH, HTTP or process runs.
Only the three import-time helper assignments are replaced in the source AST;
all tested orchestrator functions execute unchanged. Remote semantics below are
an in-memory contract, not certification of SFTP, WordPress or native rendering.
Exact image identity and a single canonical are enforced by regression cases.
"""
import ast
import builtins
from contextlib import ExitStack, contextmanager, redirect_stdout
import hashlib
import io
import json
import os
from pathlib import Path
import socket
import subprocess
import sys
from types import ModuleType, SimpleNamespace
import unittest
from unittest.mock import Mock, patch
import urllib.error
import urllib.request


sys.dont_write_bytecode = True
SOURCE = Path(__file__).with_name('2026-10-17-deploy-project-family.py')


@contextmanager
def offline():
    real_import = builtins.__import__
    real_open, real_io_open = builtins.open, io.open

    def guarded_import(name, *args, **kwargs):
        if name.split('.')[0] in ('paramiko', 'fabric'):
            raise AssertionError('SSH imports forbidden')
        return real_import(name, *args, **kwargs)

    def read_only(opener):
        def guarded(file, mode='r', *args, **kwargs):
            if any(flag in mode for flag in 'wax+'):
                raise AssertionError('Disk writes forbidden')
            return opener(file, mode, *args, **kwargs)
        return guarded

    with ExitStack() as stack:
        stack.enter_context(patch.object(builtins, '__import__', guarded_import))
        stack.enter_context(patch.object(builtins, 'open', read_only(real_open)))
        stack.enter_context(patch.object(io, 'open', read_only(real_io_open)))
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


def load_orchestrator():
    helpers = {
        'H': ('project_transport', '2026-10-14-deploy-appointment-source.py'),
        'I': ('project_inventory', '2026-10-15-deploy-public-inventory.py'),
        'T': ('project_protected', '2026-10-16-deploy-public-trust.py'),
    }
    tree = ast.parse(SOURCE.read_text(encoding='utf-8'), filename=str(SOURCE))
    replaced = set()
    for node in tree.body:
        if not isinstance(node, ast.Assign) or len(node.targets) != 1:
            continue
        target = node.targets[0]
        if not isinstance(target, ast.Name) or target.id not in helpers:
            continue
        expected = ast.parse('module' + repr(helpers[target.id]), mode='eval').body
        if ast.dump(node.value) != ast.dump(expected) or target.id in replaced:
            raise AssertionError('Helper loading changed; review offline isolation')
        replaced.add(target.id)
        node.value = ast.copy_location(ast.Name(id='_stub_' + target.id, ctx=ast.Load()), node.value)
    if replaced != set(helpers):
        raise AssertionError('All transport dependencies must be isolated')
    result = ModuleType('offline_project_deploy')
    result.__file__ = str(SOURCE)
    result._stub_H = SimpleNamespace(
        LIVE='/offline/live', BACKUPS='/offline/backups', PHP='offline-php', WP='offline-wp',
        Remote=Mock(side_effect=AssertionError('Remote must be explicitly mocked')))
    result._stub_I = SimpleNamespace(CRITICAL=('/offline/live/protected.php',))
    result._stub_T = SimpleNamespace(OTHER_SOURCES={'source': '/offline/live/other.php'})
    exec(compile(ast.fix_missing_locations(tree), str(SOURCE), 'exec'), result.__dict__)
    return result


with offline():
    D = load_orchestrator()

NATIVE_IDS = ('h22-offer-modal', 'h22-offer-form', 'hm-cookie-settings-button', 'masthead', 'colophon')
IMAGE_BASE = 'https://harmat22.hu/wp-content/mu-plugins/harmat-project-family/'


def minimal_html():
    return ('<!doctype html><html><head><link rel="canonical" href="' + D.URL + '"></head><body>'
            '<main id="main"><div id="harmat-project-family"><h1 id="hpf-title">Harmat <br>Lak&#243;park</h1>'
            '<img src="' + IMAGE_BASE + 'project-aerial.jpg">'
            '<img src="' + IMAGE_BASE + 'project-courtyard.jpg">'
            '<img src="' + IMAGE_BASE + 'project-living-room.jpg">'
            '<img src="' + IMAGE_BASE + 'construction-site.webp">'
            '<a href="tel:+36300733375">Phone</a><a href="mailto:ertekesites@harmat22.hu">Mail</a>'
            '<a href="#opal-projectform-popup">Visit</a><p>Látványterv 2026-10-02 2026-06-12</p></div></main>'
            + ''.join('<div id="' + name + '"></div>' for name in NATIVE_IDS)
            + '</body></html>')


def synthetic_blobs():
    return {name: (b'<?php\n// offline fixture\n' if name.endswith('.php') else
                   b'body { color: black; }\n' if name.endswith('.css') else
                   b'<!doctype html><html><body>Approved fixture</body></html>\n' if name.endswith('.html') else
                   b'\xff\xd8\r\n\x00' + name.encode('ascii') + b'\xff\xd9')
            for name in (*D.FILES, D.TEST, D.NATIVE, D.PREVIEW)}


class MemoryPath:
    def __init__(self, files, path=''):
        self.files, self.path = files, path

    def __truediv__(self, part):
        return MemoryPath(self.files, self.path + '/' + part if self.path else part)

    def read_bytes(self):
        return self.files[self.path]


class MemoryRemote:
    """Exclusive creates, nonempty-directory errors and exact-byte owned removal."""
    def __init__(self):
        self.files = {path: b'protected original' for path in (
            *D.I.CRITICAL, *D.T.OTHER_SOURCES.values(),
            *(D.DIR + '/' + name for name in D.EXTRA), D.H.LIVE + '/.htaccess')}
        self.dirs = {D.DIR, D.H.BACKUPS}
        self.events = []
        self.sftp = self
        self.closed = False
        self.native = json.dumps({'native': True})
        self.render = minimal_html().encode('utf-8')
        self.fixture = 'PASS synthetic fixture only'

    def directory(self, path):
        if path not in self.dirs:
            raise FileNotFoundError(path)

    def mkdir(self, path, mode=0o700):
        if path in self.dirs or path in self.files:
            raise FileExistsError(path)
        self.events.append(('mkdir', path, mode))
        self.dirs.add(path)

    def rmdir(self, path):
        if any(p.startswith(path + '/') for p in self.files.keys() | self.dirs):
            raise OSError('Directory not empty')
        self.events.append(('rmdir', path))
        self.dirs.remove(path)

    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        return SimpleNamespace()

    def read(self, path, private=False):
        self.events.append(('read', path, private))
        if path not in self.files:
            raise FileNotFoundError(path)
        return self.files[path]

    def write_new(self, path, data):
        if path in self.files or path in self.dirs:
            raise FileExistsError(path)
        self.directory(path.rsplit('/', 1)[0])
        self.events.append(('write', path))
        self.files[path] = data

    def lint(self, path):
        if path not in self.files:
            raise FileNotFoundError(path)
        self.events.append(('lint', path))

    def chmod(self, path, mode):
        self.info(path)
        self.events.append(('chmod', path, mode))

    def posix_rename(self, stage, target):
        if stage.rsplit('/', 1)[0] != target.rsplit('/', 1)[0]:
            raise AssertionError('Rename must stay in same directory')
        self.directory(target.rsplit('/', 1)[0])
        self.events.append(('rename', stage, target))
        self.files[target] = self.files.pop(stage)

    def remove_owned(self, path, expected):
        if self.read(path) != expected:
            raise RuntimeError('Owned bytes changed')
        self.events.append(('remove', path))
        del self.files[path]

    def run(self, command):
        self.events.append(('run', command))
        if command.startswith(D.H.WP + ' eval '):
            return '{"post":{"ID":1777},"meta":{}}'
        if D.TEST in command:
            return self.fixture
        if D.NATIVE in command:
            backup = next(p.rsplit('/', 1)[0] for p in self.files if p.endswith('/manifest.json'))
            self.write_new(backup + '/native-render.html', self.render)
            return self.native
        raise AssertionError('Unexpected simulated command')

    def purge(self):
        self.events.append(('purge',))

    def close(self):
        self.events.append(('close',))
        self.closed = True


class OfflineCase(unittest.TestCase):
    def setUp(self):
        self.stack = ExitStack()
        self.addCleanup(self.stack.close)
        self.stack.enter_context(offline())


class BundleTests(OfflineCase):
    def bundle(self, blobs):
        files = {('wp-mu-plugins/' + name if name in D.FILES else 'server-config/previews/project-family/index.html' if name == D.PREVIEW else name): data
                 for name, data in blobs.items()}
        with patch.object(D, 'ROOT', MemoryPath(files)), patch.object(D, 'MAINT', MemoryPath(files)):
            return D.bundle()

    def test_crlf_normalizes_only_text_and_digest_is_order_independent(self):
        originals = synthetic_blobs()
        windows = {name: data.replace(b'\n', b'\r\n') if name.endswith(('.php', '.css', '.html')) else data
                   for name, data in originals.items()}
        blobs, pins, digest = self.bundle(windows)
        self.assertEqual(blobs, originals)
        expected = {name: hashlib.sha256(data).hexdigest() for name, data in originals.items()}
        self.assertEqual(pins, expected)
        self.assertEqual(digest, hashlib.sha256(json.dumps(
            expected, sort_keys=True, separators=(',', ':')).encode('ascii')).hexdigest())
        self.assertEqual(self.bundle(dict(reversed(list(originals.items())))), (blobs, pins, digest))

    def test_binary_and_lone_cr_preserved_and_every_input_is_pinned(self):
        originals = synthetic_blobs()
        originals[D.TEST] += b'// lone CR\r'
        baseline, _, digest = self.bundle(originals)
        self.assertEqual(baseline, originals)
        for name in originals:
            changed = dict(originals)
            changed[name] += b'x'
            with self.subTest(name=name):
                self.assertNotEqual(self.bundle(changed)[2], digest)


class PageTests(OfflineCase):
    def test_minimal_native_shell_accepts_entities_br_and_whitespace(self):
        self.assertEqual(D.assert_page(minimal_html()), {
            'root': 1, 'h1': ['Harmat Lak\u00f3park'], 'native_form': True, 'canonical': D.URL})

    def test_root_and_each_exact_native_id_must_occur_once(self):
        for name in ('harmat-project-family', 'main', 'hpf-title', *NATIVE_IDS):
            for variant in ('missing', 'duplicate', 'near-match'):
                text = minimal_html()
                if variant == 'duplicate':
                    text += '<div id="' + name + '"></div>'
                else:
                    text = text.replace('id="' + name + '"', 'id="' + name + '-other"'
                                        if variant == 'near-match' else '')
                with self.subTest(name=name, variant=variant), self.assertRaises(RuntimeError):
                    D.assert_page(text)

    def test_one_exact_h1_and_canonical_identity(self):
        text = minimal_html()
        variants = [text.replace('<h1 id="hpf-title">', '<h2>').replace('</h1>', '</h2>'),
                    text + '<h1>Harmat Lak&#243;park</h1>',
                    text.replace('Lak&#243;park', 'Wrong title')]
        for canonical in ('', 'http://harmat22.hu/elerhetosegeink/', 'https://example.invalid/elerhetosegeink/',
                          'https://harmat22.hu/elerhetosegeink', D.URL + '?preview=1'):
            variants.append(text.replace(D.URL, canonical))
        for number, variant in enumerate(variants):
            with self.subTest(number=number), self.assertRaises(RuntimeError):
                D.assert_page(variant)

    def test_owned_images_old_content_and_preview_paths(self):
        text = minimal_html()
        variants = [text + '<div class="site-main harmat-project-modern"></div>',
                    text + '<p>Your Name</p>',
                    text + '<img src="assets/unresolved.jpg">']
        for image in ('project-aerial.jpg', 'project-courtyard.jpg'):
            for wrong in ('assets/' + image, 'https://example.invalid/' + image, ''):
                variants.append(text.replace(IMAGE_BASE + image, wrong))
        for number, variant in enumerate(variants):
            with self.subTest(number=number), self.assertRaises(RuntimeError):
                D.assert_page(variant)

    def test_rejects_nonexact_owned_image_filename(self):
        text = minimal_html().replace('project-aerial.jpg', 'project-aerial.jpg.unrelated')
        with self.assertRaises(RuntimeError):
            D.assert_page(text)

    def test_rejects_conflicting_duplicate_canonical(self):
        text = minimal_html().replace('<head>', '<head><link rel="canonical" href="https://example.invalid/">')
        with self.assertRaises(RuntimeError):
            D.assert_page(text)


class HttpTests(OfflineCase):
    def test_http_errors_and_timeout_propagate_with_one_request_no_retry(self):
        for code in (403, 429, 500):
            with io.BytesIO(b'forbidden') as body:
                error = urllib.error.HTTPError(D.URL, code, 'synthetic', {}, body)
                with self.subTest(code=code), patch.object(urllib.request, 'urlopen', side_effect=error) as opening:
                    with self.assertRaises(urllib.error.HTTPError) as caught:
                        D.http(D.URL)
                    self.assertIs(caught.exception, error)
                    opening.assert_called_once()
                    self.assertEqual(body.tell(), 0)
                    request = opening.call_args.args[0]
                    self.assertEqual(request.full_url, D.URL)
                    self.assertEqual(request.get_method(), 'GET')
                    self.assertEqual(request.get_header('User-agent'), 'HarmatOfferSafety/1.0')
                    self.assertEqual(opening.call_args.kwargs, {'timeout': 30})
        with patch.object(urllib.request, 'urlopen', side_effect=TimeoutError('bounded timeout')) as opening:
            with self.assertRaises(TimeoutError):
                D.http(D.URL)
            opening.assert_called_once()

    def test_status_final_url_and_size_are_checked_with_bounded_read(self):
        limit = 2 * 1024 * 1024
        for status, url, data, accepted in (
            (200, D.URL, b'body', True), (200, D.URL, b'x' * limit, True),
            (200, D.URL, b'x' * (limit + 1), False),
            (201, D.URL, b'body', False), (200, D.URL + '?redirected', b'body', False),
        ):
            response = Mock(status=status, headers={'Content-Type': 'text/html; charset=utf-8'})
            response.geturl.return_value = url
            response.read.return_value = data
            context = Mock()
            context.__enter__ = Mock(return_value=response)
            context.__exit__ = Mock(return_value=False)
            with self.subTest(status=status, url=url, size=len(data)), patch.object(
                    urllib.request, 'urlopen', return_value=context) as opening:
                if accepted:
                    self.assertEqual(D.http(D.URL), (data, response.headers['Content-Type']))
                else:
                    with self.assertRaisesRegex(RuntimeError, 'HTTP identity/size/status'):
                        D.http(D.URL)
                response.read.assert_called_once_with(limit + 1)
                opening.assert_called_once()
                context.__exit__.assert_called_once()


class RollbackTests(OfflineCase):
    def setUp(self):
        super().setUp()
        self.remote = MemoryRemote()
        self.blobs = synthetic_blobs()
        self.manifest = {'pins': {name: D.sha(data) for name, data in self.blobs.items()},
                         'original_absent': list(D.FILES)}
        self.remote.dirs.add(D.DIR + '/' + D.ASSET)
        self.remote.files.update({D.DIR + '/' + name: self.blobs[name] for name in D.FILES})

    def test_removes_only_exact_owned_files_reverse_order_main_first(self):
        protected = {path: data for path, data in self.remote.files.items()
                     if path not in {D.DIR + '/' + name for name in D.FILES}}
        self.remote.files[D.DIR + '/' + D.MAIN + '.foreign.tmp'] = b'foreign stage'
        protected[D.DIR + '/' + D.MAIN + '.foreign.tmp'] = b'foreign stage'
        D.rollback(self.remote, '/offline/unused', self.manifest, self.blobs)
        self.assertEqual([e[1] for e in self.remote.events if e[0] == 'remove'],
                         [D.DIR + '/' + name for name in reversed(D.FILES)])
        self.assertEqual(self.remote.files, protected)
        self.assertEqual(self.remote.events[-2:], [('rmdir', D.DIR + '/' + D.ASSET), ('purge',)])

    def test_partial_release_absent_files_skipped_and_other_asset_blocks_rmdir(self):
        del self.remote.files[D.DIR + '/' + D.MAIN]
        del self.remote.files[D.DIR + '/' + D.FILES[1]]
        foreign = D.DIR + '/' + D.ASSET + '/foreign.jpg'
        self.remote.files[foreign] = b'unrelated'
        with self.assertRaisesRegex(OSError, 'not empty'):
            D.rollback(self.remote, '/offline/unused', self.manifest, self.blobs)
        self.assertEqual(self.remote.files[foreign], b'unrelated')
        self.assertFalse(any(D.DIR + '/' + name in self.remote.files for name in D.FILES))
        self.assertNotIn(('purge',), self.remote.events)

    def test_hash_drift_retains_changed_file_and_stops_removing_earlier_assets(self):
        for changed in (D.MAIN, D.FILES[2]):
            with self.subTest(changed=changed):
                r = MemoryRemote()
                r.dirs.add(D.DIR + '/' + D.ASSET)
                r.files.update({D.DIR + '/' + n: self.blobs[n] for n in D.FILES})
                r.files[D.DIR + '/' + changed] = b'newer editor bytes'
                with self.assertRaisesRegex(RuntimeError, 'Owned rollback refused'):
                    D.rollback(r, '/offline/unused', self.manifest, self.blobs)
                self.assertEqual(r.files[D.DIR + '/' + changed], b'newer editor bytes')
                self.assertEqual([e[1] for e in r.events if e[0] == 'remove'],
                                 [D.DIR + '/' + n for n in reversed(D.FILES[D.FILES.index(changed) + 1:])])
                self.assertNotIn(('purge',), r.events)


class LifecycleTests(OfflineCase):
    def setUp(self):
        super().setUp()
        self.r = MemoryRemote()
        self.originals = dict(self.r.files)
        self.blobs = synthetic_blobs()
        self.pins = {name: D.sha(data) for name, data in self.blobs.items()}
        self.remote = self.stack.enter_context(patch.object(D.H, 'Remote', return_value=self.r))
        self.snapshot = self.stack.enter_context(patch.object(D, 'snapshot', return_value={'protected': 'same'}))
        self.public = self.stack.enter_context(patch.object(D, 'public_proof', return_value={'offline': True}))
        self.stack.enter_context(patch.object(D.uuid, 'uuid4', return_value=SimpleNamespace(hex='a' * 32)))
        self.output = self.stack.enter_context(redirect_stdout(io.StringIO()))

    def deploy(self):
        D.deploy(self.blobs, self.pins, 'synthetic-reviewed-digest')

    def assert_closed_unchanged(self):
        self.assertTrue(self.r.closed)
        self.assertNotIn(D.LOCK, self.r.dirs)
        for path, data in self.originals.items():
            self.assertEqual(self.r.files[path], data)

    def assert_no_public_activation(self):
        self.assertFalse(any(e[0] in ('mkdir', 'write', 'rename', 'chmod') and
                             e[1].startswith(D.DIR + '/') for e in self.r.events))
        self.public.assert_not_called()
        self.assert_closed_unchanged()

    def test_native_preflight_failure_never_creates_public_paths(self):
        for native in ('{"native":false}', 'not JSON'):
            self.r = MemoryRemote()
            self.remote.return_value = self.r
            self.r.native = native
            with self.subTest(native=native), self.assertRaises((RuntimeError, ValueError)):
                self.deploy()
            self.assert_no_public_activation()
        self.assertTrue(any(e[0] == 'run' and D.NATIVE in e[1] for e in self.r.events))

    def test_bad_native_html_fixture_or_protected_drift_never_activates(self):
        for reason in ('native-html', 'fixture', 'snapshot'):
            with self.subTest(reason=reason):
                self.r = MemoryRemote()
                self.remote.return_value = self.r
                self.r.render = b'<html><h1>Wrong native page</h1></html>' if reason == 'native-html' else minimal_html().encode()
                self.r.fixture = 'FAIL synthetic' if reason == 'fixture' else 'PASS synthetic'
                self.snapshot.side_effect = [{'protected': 'same'}, {'protected': 'changed'}] if reason == 'snapshot' else None
                with self.assertRaises(RuntimeError):
                    self.deploy()
                self.assert_no_public_activation()

    def test_existing_main_or_asset_directory_stops_before_manifest_and_preserves_it(self):
        for name in (D.MAIN, D.ASSET):
            with self.subTest(name=name):
                self.r = MemoryRemote()
                self.remote.return_value = self.r
                path = D.DIR + '/' + name
                if name == D.MAIN:
                    self.r.files[path] = b'pre-existing must survive'
                else:
                    self.r.dirs.add(path)
                    self.r.files[path + '/pre-existing.jpg'] = b'pre-existing must survive'
                before = dict(self.r.files)
                with self.assertRaisesRegex(RuntimeError, 'paths already exist'):
                    self.deploy()
                self.assertEqual(self.r.files, before)
                self.assertFalse(any(e[0] in ('write', 'remove') for e in self.r.events))
                self.assert_no_public_activation()

    def test_success_private_checks_precede_activation_and_main_is_last(self):
        self.deploy()
        events = self.r.events
        renames = [e for e in events if e[0] == 'rename']
        self.assertEqual([e[2] for e in renames], [D.DIR + '/' + n for n in D.FILES])
        first = events.index(renames[0])
        native = next(i for i, e in enumerate(events) if e[0] == 'run' and D.NATIVE in e[1])
        self.assertLess(native, first)
        self.assertTrue(any(e[0] == 'read' and e[1].endswith('/native-render.html') and e[2]
                            for e in events[:first]))
        backup = D.PREFIX + 'a' * 32
        manifest = json.loads(self.r.files[backup + '/manifest.json'])
        self.assertEqual(manifest['original_absent'], list(D.FILES))
        self.assertEqual(manifest['pins'], self.pins)
        self.assertLess(events.index(('write', backup + '/original-page.json')), first)
        for name in D.FILES:
            self.assertEqual(self.r.files[D.DIR + '/' + name], self.blobs[name])
        self.public.assert_called_once_with(self.r, self.pins)
        self.assertIn(backup + '/release-proof.json', self.r.files)
        self.assertEqual(json.loads(self.output.getvalue())['status'], 'RELEASED')
        self.assert_closed_unchanged()

    def test_public_failure_rolls_back_only_new_files_and_preserves_protected_data(self):
        self.public.side_effect = RuntimeError('synthetic public failure')
        with self.assertRaisesRegex(RuntimeError, 'synthetic public failure'):
            self.deploy()
        self.assertEqual([e[1] for e in self.r.events if e[0] == 'remove'],
                         [D.DIR + '/' + n for n in reversed(D.FILES)])
        self.assertFalse(any(D.DIR + '/' + n in self.r.files for n in D.FILES))
        self.assertNotIn(D.DIR + '/' + D.ASSET, self.r.dirs)
        self.assertEqual(json.loads(self.output.getvalue())['was_activated'], True)
        self.assert_closed_unchanged()

    def test_partial_rename_failure_cleans_exact_stage_then_reverse_installed_files(self):
        for failure_index in (0, 2, len(D.FILES) - 1):
            with self.subTest(failure_index=failure_index):
                self.r = MemoryRemote()
                self.remote.return_value = self.r
                rename = self.r.posix_rename
                failed_target = D.DIR + '/' + D.FILES[failure_index]

                def fail_one_rename(stage, target):
                    if target == failed_target:
                        raise RuntimeError('synthetic rename failure')
                    rename(stage, target)

                with patch.object(self.r, 'posix_rename', side_effect=fail_one_rename):
                    with self.assertRaisesRegex(RuntimeError, 'synthetic rename failure'):
                        self.deploy()
                removed = [e[1] for e in self.r.events if e[0] == 'remove']
                stage = failed_target + '.' + 'a' * 32 + '.tmp'
                self.assertEqual(removed, [stage] + [D.DIR + '/' + n
                                                   for n in reversed(D.FILES[:failure_index])])
                self.assertFalse(any(p.startswith(D.DIR + '/' + D.ASSET + '/')
                                     or p.startswith(D.DIR + '/' + D.MAIN) for p in self.r.files))
                self.public.assert_not_called()
                self.assert_closed_unchanged()

    def test_failed_public_proof_refuses_changed_main_without_deleting_assets(self):
        def later_editor(*args):
            self.r.files[D.DIR + '/' + D.MAIN] = b'later unrelated edit'
            raise RuntimeError('synthetic public failure')
        self.public.side_effect = later_editor
        with self.assertRaisesRegex(RuntimeError, 'Owned rollback refused'):
            self.deploy()
        self.assertEqual(self.r.files[D.DIR + '/' + D.MAIN], b'later unrelated edit')
        self.assertFalse(any(e[0] == 'remove' for e in self.r.events))
        self.assertTrue(all(D.DIR + '/' + n in self.r.files for n in D.FILES))
        self.assert_closed_unchanged()

    def test_default_and_bad_pin_entrypoints_never_construct_remote(self):
        with patch.object(D, 'bundle', return_value=(self.blobs, self.pins, 'reviewed')):
            with patch.object(sys, 'argv', [str(SOURCE)]):
                D.main()
            for args in (['--deploy'], ['--deploy', '--expect', 'wrong'], ['--verify', '/not-a-backup']):
                with self.subTest(args=args), patch.object(sys, 'argv', [str(SOURCE), *args]):
                    with self.assertRaises(RuntimeError):
                        D.main()
        self.remote.assert_not_called()


if __name__ == '__main__':
    unittest.main(verbosity=2)
