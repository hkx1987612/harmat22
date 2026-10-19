"""Focused offline guards, historical fixture, and owned-source recovery tests."""
import contextlib
import importlib.util
import io
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('payment_terms_update', HERE / '2026-10-19-update-public-payment-terms.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class Remote:
    private_group_verified = True

    def __init__(self):
        self.files = {}
        self.sftp = self
        self.renames = []
        self.purges = 0

    def info(self, path):
        if path not in self.files: raise FileNotFoundError(path)
        return SimpleNamespace(st_mode=0o100644, st_uid=1608, st_gid=1611)

    def read(self, path, private=False): return self.files[path]
    def lint(self, path): pass
    def stage_metadata(self, path, meta): pass
    def purge(self): self.purges += 1
    def write_new(self, path, blob):
        if path in self.files: raise RuntimeError('collision')
        self.files[path] = blob
    def remove_owned(self, path, blob):
        if self.files[path] != blob: raise RuntimeError('foreign')
        del self.files[path]
    def posix_rename(self, source, target):
        self.files[target] = self.files.pop(source)
        self.renames.append(target)


class Tests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.edits, cls.helpers, cls.pins, cls.digest = M.release()

    def setUp(self):
        blocker = patch.object(socket.socket, 'connect', side_effect=AssertionError('NO NETWORK'))
        blocker.start(); self.addCleanup(blocker.stop)

    def test_exact_existing_two_sources(self):
        self.assertEqual(set(self.edits), {M.TARGET_N, M.TARGET_A})
        self.assertEqual(M.sha(self.edits[M.TARGET_N][0]), M.OLD_N)
        self.assertEqual(M.sha(self.edits[M.TARGET_A][0]), M.OLD_A)
        self.assertTrue(all(old is not None for old, new in self.edits.values()))

    def test_module_only_copy_version_and_assistant_only_answer_version(self):
        old, new = self.edits[M.TARGET_N]
        restored = new.replace(M.Q.block(new, 'harmat_ppc_html'), M.Q.block(old, 'harmat_ppc_html'), 1)
        self.assertEqual(restored.replace(b'Version: 1.0.1', b'Version: 1.0.0', 1), old)
        old, new = self.edits[M.TARGET_A]
        restored = new.replace(M.Q.block(new, 'payment_schedule_answer', True), M.Q.block(old, 'payment_schedule_answer', True), 1)
        self.assertEqual(restored.replace(b"const VERSION = '0.4.4'", b"const VERSION = '0.4.3'", 1), old)
        self.assertEqual(M.baseline_tax(old), M.baseline_tax(new))

    def test_mixed_eol_preserved_outside_bounded_answer(self):
        old, new = self.edits[M.TARGET_A]
        for data in (old, new):
            self.assertIn(b'\r\n', data)
            self.assertIn(b'\n', data.replace(b'\r\n', b''))
        self.assertEqual(M.Q.block(old, 'is_payment_schedule_question', True), M.Q.block(new, 'is_payment_schedule_question', True))

    def test_decoded_three_language_answers(self):
        values = M.answers(self.edits[M.TARGET_A][1])
        self.assertEqual(set(values), {'hu', 'zh', 'en'})
        for value in values.values():
            self.assertNotIn('8%', value); self.assertNotIn('4%', value)
        self.assertIn('优惠请与销售面议', values['zh'])
        self.assertIn("apartment's final signed offer", values['en'])

    def test_atomic_existing_replace(self):
        r = Remote(); r.files[M.TARGET_N] = b'old'
        M.Q.replace(r, M.TARGET_N, b'old', b'new', (0o644, 1608, 1611))
        self.assertEqual(r.files, {M.TARGET_N: b'new'})
        self.assertEqual(r.renames, [M.TARGET_N])

    def test_foreign_existing_source_rejected_and_stage_cleaned(self):
        r = Remote(); r.files[M.TARGET_N] = b'foreign'
        with self.assertRaisesRegex(RuntimeError, 'ownership'):
            M.Q.replace(r, M.TARGET_N, b'old', b'new', (0o644, 1608, 1611))
        self.assertEqual(r.files, {M.TARGET_N: b'foreign'})

    def test_stage_lint_failure_no_activation(self):
        r = Remote(); r.files[M.TARGET_N] = b'old'
        with patch.object(r, 'lint', side_effect=RuntimeError('lint failed')):
            with self.assertRaisesRegex(RuntimeError, 'lint failed'):
                M.Q.replace(r, M.TARGET_N, b'old', b'new', (0o644, 1608, 1611))
        self.assertEqual(r.files, {M.TARGET_N: b'old'})
        self.assertEqual(r.renames, [])

    def recovery(self, states):
        r = Remote()
        meta = {p: (0o644, 1608, 1611) for p in self.edits}
        for (path, pair), state in zip(self.edits.items(), states):
            r.files[path] = pair[state] if isinstance(state, int) else state
        r.files['/protected/home.php'] = b'current home 1.0.10'
        return r, meta

    def test_partial_release_restore_only_owned(self):
        r, meta = self.recovery((1, 0))
        M.restore(r, self.edits, meta)
        for path, (old, new) in self.edits.items(): self.assertEqual(r.files[path], old)
        self.assertEqual(r.files['/protected/home.php'], b'current home 1.0.10')
        self.assertEqual(r.renames, [M.TARGET_N])

    def test_foreign_recovery_retains_foreign_and_restores_other_owned_source(self):
        r, meta = self.recovery((b'foreign module', 1))
        with self.assertRaisesRegex(RuntimeError, 'Foreign source retained'):
            M.restore(r, self.edits, meta)
        self.assertEqual(r.files[M.TARGET_N], b'foreign module')
        self.assertEqual(r.files[M.TARGET_A], self.edits[M.TARGET_A][0])

    def test_recovery_idempotent(self):
        r, meta = self.recovery((0, 0))
        M.restore(r, self.edits, meta)
        self.assertEqual(r.renames, [])

    def test_default_offline_and_explicit_gate_before_remote(self):
        with patch.object(M.H, 'Remote', side_effect=AssertionError('NO REMOTE')):
            with patch.object(sys, 'argv', ['update']), contextlib.redirect_stdout(io.StringIO()) as output:
                M.main()
            self.assertIn('OFFLINE_NO_NETWORK', output.getvalue())
            for mode in (['--deploy'], ['--verify', 'bad'], ['--rollback', 'bad']):
                with patch.object(sys, 'argv', ['update'] + mode + ['--expect', 'wrong']):
                    with self.assertRaisesRegex(RuntimeError, 'aggregate'): M.main()

    def test_bad_backup_rejected_before_connect(self):
        with patch.object(M.H, 'Remote', side_effect=AssertionError('NO REMOTE')):
            with self.assertRaisesRegex(RuntimeError, 'private backup'):
                M.operate(self.edits, self.helpers, self.pins, self.digest, '/tmp/not-owned')

    def test_snapshot_accepts_current_home_excludes_owned_sources(self):
        value = {'sources': {M.TARGET_N: 'old', M.TARGET_A: 'old', 'home': '1.0.10'}, 'protected': {}}
        with patch.object(M.Q, 'snapshot', return_value=value):
            self.assertEqual(M.snapshot(None), {'sources': {'home': '1.0.10'}, 'protected': {}})

    def test_native_form_seo_tax_and_duplicate_guards(self):
        old_copy = '<section id="harmat-public-payment-copy">8% 4% 2026. december 31 2027. június 30 25% 20% 5%</section>'
        new_copy = old_copy.replace('8% 4%', '优惠请与销售面议 ' * 3 + 'egyedileg egyeztessen az értékesítéssel ' * 3)
        shell = '<html><head><link rel="canonical" href="https://harmat22.hu/"></head><body><form id="h22-offer-form"><input name="name" required></form><p>Tax 4%</p>{}</body></html>'
        old, new = shell.format(old_copy), shell.format(new_copy)
        self.assertTrue(M.assert_page(new, old, True)['native_form_unchanged'])
        for bad in (new.replace('name="name"', 'name="changed"'), new.replace('https://harmat22.hu/', 'https://other.example/'),
                    new.replace(new_copy, new_copy * 2), new.replace('优惠请与销售面议', '8% discount', 1)):
            with self.assertRaises(RuntimeError): M.assert_page(bad, old, True)

    def test_optional_layout_and_original_rollback(self):
        page = '<form id="h22-offer-form"></form>'
        self.assertEqual(M.assert_page(page, page, True, True)['payment_roots'], 0)
        with self.assertRaises(RuntimeError): M.assert_page(page + 'elementor-4214', page, True, True)
        with self.assertRaises(RuntimeError): M.assert_page(page, page, True)

    def test_historical_promotion_suite_remains_runnable_in_isolated_fixture(self):
        php = shutil.which('php')
        self.assertIsNotNone(php)
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            files = {
                'wp-plugins/harmat-local-assistant/harmat-local-assistant.php': self.edits[M.TARGET_A][0],
                'wp-mu-plugins/zz-harmat-assistant-live-data.php': M.Q.git('wp-mu-plugins/zz-harmat-assistant-live-data.php', M.BASE),
                'server-config/maintenance/2026-09-10-test-assistant.php': (HERE / '2026-09-10-test-assistant.php').read_bytes(),
                'server-config/maintenance/2026-10-18-test-assistant-promotion-payment.php': (HERE / '2026-10-18-test-assistant-promotion-payment.php').read_bytes(),
            }
            for path, blob in files.items():
                target = root / path; target.parent.mkdir(parents=True, exist_ok=True); target.write_bytes(blob)
            result = subprocess.run([php, str(root / 'server-config/maintenance/2026-10-18-test-assistant-promotion-payment.php')], capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            self.assertIn('"promotion_payment_checks":149', result.stdout)


if __name__ == '__main__': unittest.main()
