"""Offline release-orchestration tests. No SSH, WordPress, network, or customer data."""
import contextlib
import importlib.util
import io
import json
from pathlib import Path
import tempfile

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('crm_release_test', HERE / '2026-11-04-deploy-crm-simplification.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


def case(failure=None):
    with tempfile.TemporaryDirectory(prefix='harmat-crm-test-') as temp:
        root = Path(temp); M.ROOT = root; M.OUT = root
        M.PATHS = {'crm': ('crm.php', '/live/crm.php'), 'bandwidth': ('bandwidth.php', '/live/bandwidth.php')}
        folder = M.PREFIX + 'offline-only'
        files = {}; pins = {}; meta = {}; old_data = {'options': 'unchanged', 'tables': 'unchanged'}
        for key, (relative, target) in M.PATHS.items():
            old, new = (key + '-old').encode(), (key + '-new').encode()
            (root / relative).write_bytes(new)
            files[target] = old
            files[folder + '/' + key + '-old.php'] = old
            files[folder + '/' + key + '-new.php'] = new
            pins[key] = {'old': M.sha(old), 'new': M.sha(new)}
            meta[key] = [0o664, 1608, 1611]
        saved = {'backup': folder, 'full_data_backup': '/private/backup', 'pins': pins, 'meta': meta,
                 'before': old_data, 'public': {'form': 'unchanged'}}
        files[folder + '/manifest.json'] = M.encoded(saved)
        (root / 'preflight.json').write_bytes(M.encoded(saved))
        calls = []

        class Remote:
            def __init__(self): self.sftp = self
            def mkdir(self, path): calls.append('lock')
            def rmdir(self, path): calls.append('unlock')
            def close(self): calls.append('close')
            def read(self, path, **kwargs): return files[path]
            def run(self, command): calls.append('cache-flush'); return ''

        def replace(remote, path, old, new, metadata):
            assert isinstance(metadata, tuple), 'JSON metadata must normalize before staging'
            assert files[path] == old
            files[path] = new; calls.append(path)
            if failure == 'after-rename' and len([c for c in calls if c.startswith('/live/')]) == 1:
                raise RuntimeError('Simulated post-rename validation failure')

        M.H.Remote = Remote
        M.Q.replace = replace
        M.state = lambda remote: {'changed': True} if failure == 'concurrent-data' else old_data
        M.public_contract = lambda pages: pages
        M.B['pages'] = lambda: {'form': 'changed' if failure == 'public-check' else 'unchanged'}
        error = None
        with contextlib.redirect_stdout(io.StringIO()):
            try: M.deploy()
            except Exception as exc: error = exc
        assert (error is not None) == (failure is not None), (failure, error)
        for key, (_, path) in M.PATHS.items():
            assert files[path] == (key + ('-old' if failure else '-new')).encode()
        assert calls[-2:] == ['unlock', 'close']
        if failure == 'concurrent-data': assert not any(c.startswith('/live/') for c in calls)
        assert old_data == {'options': 'unchanged', 'tables': 'unchanged'}


for scenario in (None, 'concurrent-data', 'public-check', 'after-rename'):
    case(scenario)
print('PASS 4 offline deployment/rollback scenarios; no external services used.')
