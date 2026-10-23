"""Offline scope and source-only recovery checks; no network or form dispatch."""
import importlib.util
import json
import re
import unittest
from unittest.mock import patch
from pathlib import Path

spec = importlib.util.spec_from_file_location('internal_maintenance', Path(__file__).with_name('2026-10-23-internal-maintenance.py'))
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


class Scope(unittest.TestCase):
    def test_exact_bundle(self):
        pins, digest = M.bundle()
        self.assertEqual(set(pins), {M.LEGAL, M.LANG, M.DICT})
        self.assertRegex(digest, '^[a-f0-9]{64}$')

    def test_only_two_html_rows(self):
        old = M.original(M.LEGAL)
        new = M.local(M.LEGAL)
        self.assertEqual(M.rewrite(old), new)
        for a, b in reversed(M.PAIRS):
            new = new.replace(b.encode(), a.encode(), 1)
        self.assertEqual(new, old)

    def test_php_code_unchanged(self):
        pattern = rb'<\?php[\s\S]*?\?>'
        self.assertEqual(re.findall(pattern, M.original(M.LEGAL)), re.findall(pattern, M.local(M.LEGAL)))

    def test_host_email_cookie_settings_unchanged(self):
        old = M.original(M.LEGAL).decode()
        new = M.local(M.LEGAL).decode()
        for needle in ('Tárhely.Eu', 'SMTP2GO', 'function hm_legal_policy_version_', 'privacy_email', 'Hatályos:'):
            self.assertEqual([line for line in old.splitlines() if needle in line],
                             [line for line in new.splitlines() if needle in line])

    def test_only_reviewed_dictionary_delta(self):
        old = json.loads(M.original(M.OLD_DICT))
        new = json.loads(M.dictionary())
        self.assertEqual(set(old) - set(new), set(M.OLD_KEYS))
        self.assertEqual(set(new) - set(old), set(M.NEW_KEYS))
        self.assertTrue(all(old[k] == new[k] for k in old.keys() & new.keys()))
        self.assertNotIn('21stcenturywebsites.hu', M.dictionary().decode())
        self.assertNotIn('Szabados Attila', M.dictionary().decode())

    def test_fixed_bootstrap_contract(self):
        bootstrap = M.local('harmat-internal-language/bootstrap.js')
        self.assertIn(b"url.pathname.endsWith('/harmat-internal-language/zh-Hans.json')", bootstrap)
        self.assertEqual(bootstrap, M.original('harmat-internal-language/bootstrap.js'))
        self.assertIn(b'harmat-internal-language/zh-Hans.json', M.local(M.LANG))
        self.assertNotIn(b'zh-Hans-maintenance', M.local(M.LANG))

    def test_exact_language_selector_change(self):
        self.assertEqual(M.local(M.LANG), M.language(M.original(M.LANG)))
        for name in ('runtime.js', 'patterns.js', 'bootstrap.js', 'runtime.css'):
            path = 'harmat-internal-language/' + name
            self.assertEqual(M.local(path), M.original(path))

    def test_preserves_mixed_line_endings(self):
        raw = M.original(M.LEGAL).replace(b'\n', b'\r\n')
        self.assertEqual(raw.count(b'\r\n'), M.rewrite(raw).count(b'\r\n'))

    def test_rejects_non_owned_fragment(self):
        with self.assertRaises(Exception):
            M.rewrite(M.original(M.LEGAL).replace(M.PAIRS[0][0].encode(), b'foreign', 1))

    def test_recovery_keeps_foreign_source(self):
        class Remote:
            removed = False
            def read(self, path):
                return b'foreign'
            def remove_owned(self, path, expected):
                self.removed = True
        r = Remote()
        edits = {M.LEGAL: (b'old', b'new'), M.LANG: (b'old', b'new'), M.DICT: (None, M.dictionary())}
        with patch.object(M.Q.P, 'absent', return_value=False):
            with self.assertRaises(Exception):
                M.restore(r, edits, {})
        self.assertFalse(r.removed)


if __name__ == '__main__':
    unittest.main()
