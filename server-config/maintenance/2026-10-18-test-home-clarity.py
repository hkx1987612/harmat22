"""Offline exact clarity update and inherited fake transport tests; no real SSH/HTTP."""
import importlib.util
from pathlib import Path
import socket
import re
import subprocess
import unittest
from unittest.mock import patch

HERE = Path(__file__).resolve().parent


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, HERE / filename)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


C = load('clarity_under_test', '2026-10-18-update-home-clarity.py')
T = load('clarity_fake_transport_tests', '2026-10-18-test-home-actions.py')
# Reuse only the offline fake transport harness with this release's exact pins.
T.A = C
T.U = U = C.configure()


class ClarityTests(T.ActionsTests):
    def get(self, url):
        self.assertEqual(url, U.D.URL)
        style = C.STYLE if self.r.files[U.TARGET] == self.candidate else C.A.STYLE
        return ('<html>' + style + '</html>').encode(), 'text/html'

    def test_exact_scoped_helper_invocation_style_and_version_only(self):
        self.assertEqual(U.sha(self.original), C.OLD_SHA)
        restored = self.candidate.replace(C.resource_block().encode(), C.A.resource_block().encode(), 1)
        restored = restored.replace(C.NEW_ACTION.encode(), C.OLD_ACTION.encode(), 1)
        self.assertEqual(restored.replace(b' * Version: 1.0.5', b' * Version: 1.0.4', 1), self.original)
        self.assertEqual(C.STYLE, C.A.STYLE[:-8] + ''.join(C.RULES) + '</style>')
        for bad in (self.original + b'drift', self.original.replace(b'1.0.4', b'1.0.3'),
                    self.original.replace(b'\n', b'\r\n'), self.candidate):
            with self.assertRaisesRegex(RuntimeError, 'Pinned v1.0.4'):
                C.patch(bad)

    def test_native_sticky_behavior_and_pinned_body_helper_guards_unchanged(self):
        additions = ''.join(C.RULES)
        self.assertIsNone(re.search(r'(?:\{|;)(?:position|transform|opacity|animation|pointer-events|visibility):', additions))
        for forbidden in ('elementor-sticky__spacer', 'display:none', 'is-idle-hidden'):
            self.assertNotIn(forbidden, additions)
        self.assertIn(C.A.HELPER.split('    $booking=')[0].encode(), self.candidate)
        for name in ('harmat-header-sales-contact.php', 'harmat-internal-language/bootstrap.js',
                     'harmat-internal-language/runtime.css', 'harmat-unified-offer-modal.php'):
            released = subprocess.check_output(['git', 'show', C.BASE + ':wp-mu-plugins/' + name], cwd=C.ROOT)
            self.assertEqual((C.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n'), released)


class HistoricalSourceTests(unittest.TestCase):
    def test_pinned_historical_controller_is_exact_patch_and_rejects_local_drift(self):
        with patch.object(socket.socket, 'connect', side_effect=AssertionError('NETWORK FORBIDDEN')):
            controller = (C.ROOT / 'wp-mu-plugins' / U.D.MAIN).resolve()
            real_read = Path.read_bytes
            base = subprocess.check_output(['git', 'show', C.BASE + ':wp-mu-plugins/' + U.D.MAIN], cwd=C.ROOT)
            historical = C.patch(base)
            # Virtualize only this historical controller, preserving production pins.
            with patch.object(Path, 'read_bytes', autospec=True,
                              side_effect=lambda p: historical if p.resolve() == controller else real_read(p)):
                original, candidate, _, _, _ = C.local_release()
                self.assertEqual(C.patch(original), candidate)
            with patch.object(Path, 'read_bytes', autospec=True,
                              side_effect=lambda p: b'drift' if p.resolve() == controller else real_read(p)):
                with self.assertRaisesRegex(RuntimeError, 'exact clarity patch'):
                    C.local_release()


if __name__ == '__main__':
    unittest.main(verbosity=2)
