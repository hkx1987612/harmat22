"""Offline source-ownership, form-contract and atomic recovery tests."""
import importlib.util
from pathlib import Path
import socket
from types import SimpleNamespace
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('payment_deploy', Path(__file__).with_name('2026-10-18-deploy-public-payment-copy.py'))
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class Remote:
    private_group_verified = True

    def __init__(self):
        self.files = {'/owned/source.php': b'old'}
        self.sftp = self
        self.renames = []

    def info(self, path):
        if path not in self.files: raise FileNotFoundError(path)
        return SimpleNamespace(st_mode=0o100644, st_uid=1608, st_gid=1611)

    def read(self, path): return self.files[path]
    def lint(self, path): pass
    def write_new(self, path, blob):
        if path in self.files: raise RuntimeError('collision')
        self.files[path] = blob
    def stage_metadata(self, path, meta): pass
    def remove_owned(self, path, blob):
        if self.files[path] != blob: raise RuntimeError('foreign')
        del self.files[path]
    def rename(self, src, dst):
        if dst in self.files: raise RuntimeError('destination collision')
        self.posix_rename(src, dst)
    def posix_rename(self, src, dst):
        self.files[dst] = self.files.pop(src)
        self.renames.append(dst)


class Tests(unittest.TestCase):
    def setUp(self):
        self.blocker = patch.object(socket.socket, 'connect', side_effect=AssertionError('NO NETWORK'))
        self.blocker.start(); self.addCleanup(self.blocker.stop)

    def test_reviewed_two_file_boundary(self):
        edits, helpers, pins, digest = M.release()
        self.assertEqual(set(edits), {M.TARGET_A, M.TARGET_N})
        self.assertIsNone(edits[M.TARGET_N][0])
        self.assertEqual(M.sha(edits[M.TARGET_A][0]), 'e520fd3549820c6b77f798f6f3cb2ec2ae28c3bf206054866f9a48de7f4ff46d')
        self.assertEqual(M.A.payment_block(edits[M.TARGET_A][0]), M.A.payment_block(edits[M.TARGET_A][1]))
        self.assertEqual(len(digest), 64)

    def test_atomic_replacement_and_stage_cleanup(self):
        r = Remote()
        M.replace(r, '/owned/source.php', b'old', b'new', (0o644,1608,1611))
        self.assertEqual(r.files, {'/owned/source.php': b'new'})
        self.assertEqual(r.renames, ['/owned/source.php'])

    def test_foreign_source_not_overwritten(self):
        r = Remote(); r.files['/owned/source.php'] = b'foreign'
        with self.assertRaisesRegex(RuntimeError, 'ownership'):
            M.replace(r, '/owned/source.php', b'old', b'new', (0o644,1608,1611))
        self.assertEqual(r.files, {'/owned/source.php': b'foreign'})

    def test_absent_install_never_overwrites(self):
        r = Remote()
        with self.assertRaisesRegex(RuntimeError, 'collision'):
            M.replace(r, '/owned/source.php', None, b'new', (0o644,1608,1611))
        self.assertEqual(r.files, {'/owned/source.php': b'old'})

    def test_native_form_and_seo_unchanged(self):
        old = '<html><head><link rel="canonical" href="https://harmat22.hu/"></head><body><form id="h22-offer-form"><input name="name" required></form></body></html>'
        addition = '<section id="harmat-public-payment-copy">8% 4% 2026. december 31 2027. június 30 25% 20% 5%</section>'
        good = old.replace('</body>', addition + '</body>')
        self.assertTrue(M.assert_page(good, old, True)['native_form_unchanged'])
        for bad in (good.replace('name="name"', 'name="different"'), good.replace('href="https://harmat22.hu/"', 'href="https://other.example/"')):
            with self.assertRaisesRegex(RuntimeError, 'contract'): M.assert_page(bad, old, True)

    def test_duplicate_root_refused(self):
        old = '<form id="h22-offer-form"></form>'
        with self.assertRaisesRegex(RuntimeError, 'duplicate'):
            M.assert_page(old + '<div id="harmat-public-payment-copy"></div>' * 2, old, True)

    def test_explicit_custom_layout_can_remain_unchanged_but_never_keep_old_payment_block(self):
        old = '<form id="h22-offer-form"></form>'
        self.assertEqual(M.assert_page(old, old, True, True)['payment_roots'], 0)
        with self.assertRaisesRegex(RuntimeError, 'silently'):
            M.assert_page(old + '<div class="elementor elementor-4214"></div>', old, True, True)
        with self.assertRaisesRegex(RuntimeError, 'missing'):
            M.assert_page(old, old, True)


if __name__ == '__main__': unittest.main()
