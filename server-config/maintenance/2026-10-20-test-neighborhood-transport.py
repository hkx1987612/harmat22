"""Offline source bounds and atomic failure tests; no live connection."""
import importlib.util
from pathlib import Path
from types import SimpleNamespace
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('neighborhood_transport', Path(__file__).with_name('2026-10-20-restore-neighborhood.py'))
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class Remote:
    def __init__(self, name):
        self.files = {name: b'old'}
        self.lints = []
        self.sftp = SimpleNamespace(posix_rename=self.rename)
    def directory(self, path): pass
    def write_new(self, path, blob):
        assert path not in self.files
        self.files[path] = blob
    def read(self, path): return self.files[path]
    def stage_metadata(self, path, meta): pass
    def lint(self, path): self.lints.append(path)
    def rename(self, stage, path): self.files[path] = self.files.pop(stage)
    def remove_owned(self, path, blob):
        assert self.files[path] == blob
        del self.files[path]


class Tests(unittest.TestCase):
    def test_exact_source_bundle(self):
        old, new, protected, _, digest = M.bundle()
        self.assertNotEqual(old[M.JS], new[M.JS])
        self.assertNotIn(b'25%', new[M.JS])
        self.assertEqual(len(digest), 64)
        self.assertIn('zz-harmat-home-apartment-rotation.php', protected)
        self.assertIn('zz-harmat-construction-updates.php', protected)

    def activate(self, extension, foreign=False):
        path = '/owned/file.'+extension; r = Remote(path)
        def metadata(remote, name, expected):
            if foreign: remote.files[name] = b'foreign'
            M.require(remote.files[name] == expected, 'Source drift')
            return (0o644, 1, 1)
        with patch.object(M.Q, 'metadata', metadata), patch.object(M.D, 'absent', lambda remote, name: name not in remote.files):
            if foreign:
                with self.assertRaisesRegex(RuntimeError, 'Source drift'):
                    M.atomic(r, path, b'old', b'new', (0o644, 1, 1))
                self.assertEqual(r.files, {path: b'foreign'})
            else:
                M.atomic(r, path, b'old', b'new', (0o644, 1, 1))
                self.assertEqual(r.files, {path: b'new'})
        return r

    def test_js_atomic_without_fake_php_lint(self):
        self.assertEqual(self.activate('js').lints, [])

    def test_php_linted_before_and_after(self):
        self.assertEqual(len(self.activate('php').lints), 2)

    def test_foreign_source_never_overwritten(self):
        self.activate('js', foreign=True)

    def test_property_native_wrapper(self):
        body = '<div id="content"><article>Original native detail</article></div>'
        self.assertEqual(M.content_fragment(body, '/property/a1-f-l1/'), body)

    def test_missing_page_wrapper_rejected(self):
        with self.assertRaises(RuntimeError): M.content_fragment('<p>Missing</p>', '/')

    def test_same_bytes_with_changed_content_not_equal(self):
        a = '<main id="main">Facilities</main>'
        b = '<main id="main">Different facilities</main>'
        self.assertNotEqual(M.content_fragment(a, '/'), M.content_fragment(b, '/'))


if __name__ == '__main__': unittest.main()
