"""Offline guard tests; no credentials, network or production writes."""
import copy
import importlib.util
import json
import tempfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('reservation_deploy', HERE / '2026-11-05-deploy-reservation-inventory.py')
D = importlib.util.module_from_spec(spec)
spec.loader.exec_module(D)

raw = {'options': {'deals': 'unchanged'}, 'sources': {'crm': 'unchanged'},
       'tables': {'posts': {'count': 124, 'sha256': 'posts'}, 'postmeta': {'count': 125, 'sha256': 'cache-a'}}}
D._read_state = lambda remote: copy.deepcopy(raw)


class Remote:
    metadata_hash = 'business-a'

    def run(self, command):
        assert "meta_key<>" in command and D.DERIVED_CACHE_KEY in command
        return json.dumps({'table': 'postmeta', 'count': 124, 'sha256': self.metadata_hash})


with tempfile.TemporaryDirectory() as temp:
    D.M.OUT = Path(temp)
    remote = Remote()
    before = D.audited_state(remote)
    raw['tables']['postmeta']['sha256'] = 'cache-b'
    after_cache = D.audited_state(remote)
    assert before == after_cache
    remote.metadata_hash = 'business-b'
    assert before != D.audited_state(remote)
    remote.metadata_hash = 'business-a'
    raw['options']['deals'] = 'new-deal'
    assert before != D.audited_state(remote)
    raw['options']['deals'] = 'unchanged'
    raw['sources']['crm'] = 'foreign-edit'
    assert before != D.audited_state(remote)
    full = sorted(Path(temp).glob('state-full-*.json'))
    assert len(full) == 5 and json.loads(full[0].read_text())['tables']['postmeta']['sha256'] == 'cache-a'
    assert D.PROTECTED_METADATA_SQL.count("meta_key<>'_elementor_element_cache'") == 1
    assert 'UPDATE ' not in D.PROTECTED_METADATA_SQL and 'DELETE ' not in D.PROTECTED_METADATA_SQL
print('PASS 7 reservation deployment guard checks; derived cache only, all business drift rejected.')
