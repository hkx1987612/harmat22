"""Native homepage presentation release invariants. No network or submissions."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import unittest
from unittest.mock import patch
from types import SimpleNamespace

from lxml import html

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('practical_release_tests', HERE / '2026-10-23-deploy-home-practical.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
B = M.B
PHP = 'C:/tools/php-8.5.6/php.exe' if sys.platform == 'win32' else 'php'


class PracticalReleaseTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.bundle = M.release()
        def render(which):
            return subprocess.check_output([PHP, str(HERE / M.D.TEST), str(B.OUT / 'fixture' / (which + '-home.php')), '--render']).decode()
        cls.before, cls.after = render('original'), render('candidate')

    def test_dependency_first_order(self):
        self.assertEqual(list(self.bundle[0]), [M.ASSET + '/' + n for n in (B.PHOTO, B.BODY, B.JS, B.MANIFEST)] + [M.M.HOME])

    def test_controller_exact(self):
        self.assertEqual(M.sha(self.bundle[0][M.M.HOME][0]), B.OLD_HOME_SHA)
        self.assertEqual(M.sha(self.bundle[0][M.M.HOME][1]), M.PINS[M.D.MAIN])

    def test_all_previous_assets_retained(self):
        old = json.loads(B.original(M.D.ASSET + '/manifest-loop.json'))
        new = json.loads((B.ASSET / B.MANIFEST).read_text())
        self.assertEqual({k: new[k] for k in old}, old)
        self.assertEqual(set(new) - set(old), {B.BODY, B.JS, B.PHOTO})

    def test_public_main_exact_approved_delta(self):
        self.assertEqual(M.main_html(self.after), B.rewrite(M.main_html(self.before), B.PUBLIC_PHOTO))

    def test_data_renderer_unchanged(self):
        old = B.original(M.D.ASSET + '/body-six.php').decode()
        new = (B.ASSET / B.BODY).read_text(encoding='utf-8')
        self.assertEqual(old.split('<main id=', 1)[0], new.split('<main id=', 1)[0])

    def test_playback_and_panorama_control_flow_exact(self):
        old = B.original(M.D.ASSET + '/home-family-loop.js').decode()
        new = (B.ASSET / B.JS).read_text(encoding='utf-8')
        self.assertEqual(old[old.index('  const huControls ='):], new[new.index('  const huControls ='):])

    def test_runtime_exact_reviewed_preview(self):
        self.assertEqual((B.ASSET / B.JS).read_bytes(), B.runtime())

    def test_original_photo_no_quality_change(self):
        proof = json.loads((B.OUT / 'image-proof.json').read_text())
        self.assertTrue(proof['pixel_exact'])
        self.assertFalse(proof['cropped'] or proof['resized'])
        self.assertEqual(proof['bytes'], 8154459)
        self.assertEqual(proof['sha256'], B.PARK['sha256'])

    def test_native_shell_and_fields_exact(self):
        before, after = html.fromstring(self.before), html.fromstring(self.after)
        for tag in ('header', 'footer', 'form'):
            self.assertEqual([html.tostring(n) for n in before.xpath('//' + tag)], [html.tostring(n) for n in after.xpath('//' + tag)])

    def test_dynamic_cards_and_journal_exact(self):
        before, after = html.fromstring(self.before), html.fromstring(self.after)
        for key in ('hhf-apartment-grid', 'hhf-epitesi-naplo'):
            self.assertEqual(html.tostring(before.get_element_by_id(key)), html.tostring(after.get_element_by_id(key)))

    def test_one_image_only_changes(self):
        before, after = html.fromstring(self.before).xpath('//main//img'), html.fromstring(self.after).xpath('//main//img')
        self.assertEqual(len(before), len(after))
        changed = [b for a, b in zip(before, after) if a.attrib != b.attrib]
        self.assertEqual(len(changed), 1)
        self.assertEqual(changed[0].get('src'), B.PUBLIC_PHOTO)
        self.assertEqual(changed[0].get('loading'), 'lazy')

    def test_no_review_shell_or_services(self):
        for marker in ('preview-header', 'review-note', 'noindex,nofollow', 'src="assets/', '127.0.0.1'):
            self.assertNotIn(marker, self.after)

    def test_existing_owner_sources_protected(self):
        for name in ('zz-harmat-home-apartment-rotation.php', 'zz-harmat-home-assistant-inline.php', 'zz-harmat-home-video-fullscreen.php', 'zz-harmat-construction-updates.php', 'harmat-migrated-snippets.php'):
            self.assertIn(name, self.bundle[2])

    def test_bad_aggregate_before_connection(self):
        result = subprocess.run([sys.executable, str(HERE / '2026-10-23-deploy-home-practical.py'), '--deploy', '--expect', 'bad'], capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Exact reviewed aggregate required before connecting', result.stderr)

    def test_binary_asset_never_php_linted(self):
        files = {}
        remote = SimpleNamespace()
        remote.write_new = lambda p, b: files.__setitem__(p, b)
        remote.read = lambda p: files[p]
        remote.stage_metadata = lambda p, m: None
        remote.sftp = SimpleNamespace(rename=lambda a, b: files.__setitem__(b, files.pop(a)))
        remote.remove_owned = lambda p, b: files.pop(p)
        remote.lint = lambda p: self.fail('A static asset must not be parsed as PHP')
        target = M.ASSET + '/' + B.PHOTO
        image = self.bundle[0][target][1]
        metadata = (420, 1608, 1611)
        with patch.object(M.Q.P, 'absent', side_effect=lambda r, p: p not in files), patch.object(M.Q, 'metadata', return_value=metadata):
            M.replace_asset_or_php(remote, target, None, image, metadata)
            self.assertEqual(files, {target: image})
            with self.assertRaises(Exception):
                M.replace_asset_or_php(remote, target, None, image, metadata)
            with self.assertRaises(Exception):
                M.replace_asset_or_php(remote, target + '.unexpected', None, image, metadata)

    def test_php_still_uses_original_linting_transport(self):
        with patch.object(M, 'PHP_REPLACE') as original:
            M.replace_asset_or_php('remote', M.HOME, b'old', b'new', (420, 1608, 1611))
            original.assert_called_once()


if __name__ == '__main__':
    unittest.main()
