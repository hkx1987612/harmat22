"""In-memory release/recovery checks; never opens SSH or writes customer data."""
from contextlib import redirect_stdout
import copy
import io
import json
from pathlib import Path
import runpy
import tempfile
import unittest
from unittest.mock import patch

M = runpy.run_path(str(Path(__file__).with_name('2026-11-07-deploy-offer-attribution.py')))
G = M['operate'].__globals__
OLD, NEW = M['local']()
FOLDER = M['PREFIX'] + 'a' * 32
META = (0o644, 1608, 1611)


def fixture(active=False):
    return ('''<html><head><link rel="canonical" href="https://harmat22.hu/" /></head><body>
    <header id="masthead">Native menu</header><main id="main">Native content</main>
    <div id="content">Native apartment facts</div><footer id="colophon">Native footer</footer>
    <div id="h22-offer-modal"><form id="h22-offer-form"><input name="your-date" min="2026-11-07" />
    <input name="your-email" required /><input name="privacy-acceptance" type="checkbox" required /></form></div>
    <script id="harmat-internal-language">native</script><script id="hm-cookie-consent-script">native</script>
    <script id="harmat-confirmed-lead-tracking">native</script>''' +
    ('<script>harmatOfferAttributionV1</script>' if active else '') + '</body></html>').encode()


class MemoryRemote:
    def __init__(self):
        self.state = {'business': 'untouched'}
        self.files = {M['TARGET']: OLD}
        baseline = {route: fixture() for route in M['ROUTES']}
        manifest = {'base': M['BASE'], 'target': M['TARGET'], 'backup': FOLDER,
                    'original_sha256': M['sha'](OLD), 'metadata': META,
                    'before': self.state.copy(), 'pages': {k: M['sha'](v) for k, v in baseline.items()}}
        self.files[FOLDER + '/original.php'] = OLD
        self.files[FOLDER + '/manifest.json'] = M['encoded'](manifest)
        for i, route in enumerate(M['ROUTES']):
            self.files[FOLDER + '/page-' + str(i) + '.html'] = baseline[route]
        self.dirs = {FOLDER, M['H'].BACKUPS}
        self.fail_purge = False
        self.fail_stage = False
        self.race = False
        self.state_drift = False
        self.replacements = 0
        self.sftp = self

    def directory(self, folder, private=False):
        M['require'](folder in self.dirs, 'Missing fixture directory')

    def mkdir(self, folder):
        M['require'](folder not in self.dirs, 'Existing fixture lock')
        self.dirs.add(folder)

    def rmdir(self, folder):
        self.dirs.remove(folder)

    def read(self, name, private=False):
        return self.files[name]

    def write_new(self, name, blob):
        M['require'](name not in self.files, 'Exclusive fixture write refused')
        self.files[name] = blob

    def lint(self, name):
        if self.fail_stage and name.endswith('/candidate.php'):
            raise M['H'].GuardError('Injected staging failure')

    def purge(self):
        if self.fail_purge:
            self.fail_purge = False
            raise M['H'].GuardError('Injected purge failure')

    def close(self):
        pass

    def replace(self, remote, target, old, new, metadata):
        if self.race:
            self.race = False
            self.files[target] = b'foreign edit'
        M['require'](self.files[target] == old and tuple(metadata) == META, 'Ownership refusal')
        self.files[target] = new
        self.replacements += 1


class ReleaseTests(unittest.TestCase):
    def operation(self, remote, **mode):
        def state(_):
            if remote.state_drift and remote.replacements:
                remote.state['business'] = 'legitimate concurrent update'
            return copy.deepcopy(remote.state)
        with tempfile.TemporaryDirectory(prefix='harmat-attribution-test-') as temporary, \
             patch.object(M['H'], 'Remote', return_value=remote), \
             patch.object(M['Q'], 'replace', side_effect=remote.replace), \
             patch.object(M['N'].D, 'validate_native', return_value={'offer_checks': 35, 'antispam_checks': 17}), \
             patch.dict(G, {'OUT': Path(temporary), 'state': state,
                            'pages': lambda: {r: fixture(remote.files[M['TARGET']] == NEW) for r in M['ROUTES']}}), \
             redirect_stdout(io.StringIO()):
            M['operate'](FOLDER, **mode)
        self.assertNotIn(M['LOCK'], remote.dirs)

    def test_deploy_verify_and_exact_source_rollback(self):
        r = MemoryRemote()
        self.operation(r, deploy=True)
        self.assertEqual(r.files[M['TARGET']], NEW)
        self.operation(r)
        self.operation(r, rollback=True)
        self.assertEqual(r.files[M['TARGET']], OLD)
        self.assertEqual(r.state, {'business': 'untouched'})

    def test_stage_failure_never_activates(self):
        r = MemoryRemote(); r.fail_stage = True
        with self.assertRaises(M['H'].GuardError): self.operation(r, deploy=True)
        self.assertEqual(r.files[M['TARGET']], OLD)
        self.assertEqual(r.replacements, 0)

    def test_cache_failure_restores_only_owned_source(self):
        r = MemoryRemote(); r.fail_purge = True
        with self.assertRaises(M['H'].GuardError): self.operation(r, deploy=True)
        self.assertEqual(r.files[M['TARGET']], OLD)
        self.assertEqual(r.replacements, 2)
        self.assertEqual(r.state, {'business': 'untouched'})

    def test_live_drift_is_never_overwritten(self):
        r = MemoryRemote(); r.files[M['TARGET']] = b'foreign edit'
        with self.assertRaises(M['H'].GuardError): self.operation(r, deploy=True)
        self.assertEqual(r.files[M['TARGET']], b'foreign edit')
        self.assertEqual(r.replacements, 0)

    def test_race_during_swap_is_preserved(self):
        r = MemoryRemote(); r.race = True
        with self.assertRaises(M['H'].GuardError): self.operation(r, deploy=True)
        self.assertEqual(r.files[M['TARGET']], b'foreign edit')
        self.assertEqual(r.replacements, 0)

    def test_corrupt_backup_refused(self):
        r = MemoryRemote(); r.files[FOLDER + '/original.php'] += b'changed'
        with self.assertRaises(M['H'].GuardError): self.operation(r, deploy=True)
        self.assertEqual(r.files[M['TARGET']], OLD)

    def test_changed_business_data_not_restored(self):
        r = MemoryRemote(); r.state_drift = True
        with self.assertRaises(M['H'].GuardError): self.operation(r, deploy=True)
        self.assertEqual(r.files[M['TARGET']], OLD)
        self.assertEqual(r.state['business'], 'legitimate concurrent update')

    def test_expired_preflight_requires_fresh_backup(self):
        r = MemoryRemote(); r.state['business'] = 'new customer'
        with self.assertRaises(M['H'].GuardError): self.operation(r, deploy=True)
        self.assertEqual(r.replacements, 0)
        self.assertEqual(r.state['business'], 'new customer')

    def test_form_change_refused(self):
        before = {r: fixture() for r in M['ROUTES']}
        after = {r: fixture(True).replace(b'name="your-date"', b'name="changed-date"') for r in M['ROUTES']}
        with self.assertRaises(M['H'].GuardError): M['proof'](before, after, True)

    def test_stale_public_script_refused(self):
        pages = {r: fixture() for r in M['ROUTES']}
        with self.assertRaises(M['H'].GuardError): M['proof'](pages, pages, True)


if __name__ == '__main__':
    unittest.main()
