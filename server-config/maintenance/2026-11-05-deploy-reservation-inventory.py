"""Inventory-backed reservation view; source-only deployment, no business writes."""
import argparse
import importlib.util
import json
import shlex
import subprocess
import sys
import uuid
from collections import Counter
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('crm_reservation_transport', HERE / '2026-11-04-deploy-crm-simplification.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
M.BASE = '403a03f'
M.PATHS = {'crm': M.PATHS['crm']}
M.OUT = M.ROOT / 'outputs/2026-11-05-inventory-deal-audit'
M.PREFIX = M.H.BACKUPS + '/crm-reservation-inventory-'
M.LOCK = M.H.BACKUPS + '/.crm-reservation-inventory.lock'

_read_state = M.state
_states = []
_audit_id = uuid.uuid4().hex
DERIVED_CACHE_KEY = '_elementor_element_cache'
PROTECTED_METADATA_SQL = '''global $wpdb; $h=hash_init('sha256');$count=0;$offset=0;
do{$rows=$wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE meta_key<>'_elementor_element_cache' ORDER BY meta_id LIMIT $offset,1000",ARRAY_A);
  foreach($rows as $row){hash_update($h,wp_json_encode($row));$count++;}$offset+=1000;
}while(count($rows)===1000);
echo wp_json_encode(array('table'=>$wpdb->postmeta,'count'=>$count,'sha256'=>hash_final($h)));'''


def audited_state(remote):
    snapshot = _read_state(remote)
    M.OUT.mkdir(parents=True, exist_ok=True)
    (M.OUT / ('state-full-' + _audit_id + '-' + str(len(_states)) + '.json')).write_bytes(M.encoded(snapshot))
    # Public render checks regenerate this derived cache. Keep its full hash in
    # the audit, but compare every other metadata row, post, CRM option and source.
    metadata = json.loads(remote.run(M.H.WP + ' eval ' + shlex.quote(PROTECTED_METADATA_SQL)))
    table = metadata.pop('table')
    M.require(table in snapshot['tables'], 'Metadata table identity mismatch')
    snapshot['tables'][table] = metadata
    snapshot['scope'] = {'excluded_derived_metadata_key': DERIVED_CACHE_KEY}
    (M.OUT / ('state-' + _audit_id + '-' + str(len(_states)) + '.json')).write_bytes(M.encoded(snapshot))
    if _states:
        previous = _states[-1]
        changed = {group: [key for key in sorted(set(previous.get(group, {})) | set(values))
                           if previous.get(group, {}).get(key) != values.get(key)]
                   for group, values in snapshot.items()}
        print(json.dumps({'protected_state_changes': {key: value for key, value in changed.items() if value}}), flush=True)
    _states.append(snapshot)
    return snapshot


M.state = audited_state


def diagnose_state():
    # Metadata values never leave the server; compare only stable row hashes.
    code = '''global $wpdb; $result=array();
    foreach($wpdb->get_results("SELECT meta_id,meta_key,SHA2(CONCAT(post_id,':',meta_key,':',meta_value),256) AS fingerprint FROM {$wpdb->postmeta} ORDER BY meta_id",ARRAY_A) as $row){
      $result[$row['meta_id']]=array('key'=>$row['meta_key'],'sha256'=>$row['fingerprint']);
    }echo wp_json_encode($result);'''
    remote = M.H.Remote()
    try:
        before = json.loads(remote.run(M.H.WP + ' eval ' + shlex.quote(code)))
        M.public_contract(M.B['pages']())
        after = json.loads(remote.run(M.H.WP + ' eval ' + shlex.quote(code)))
        changes = [{'meta_id': key, 'before': before.get(key), 'after': after.get(key)}
                   for key in set(before) | set(after) if before.get(key) != after.get(key)]
        M.OUT.mkdir(parents=True, exist_ok=True)
        (M.OUT / ('metadata-drift-' + _audit_id + '.json')).write_bytes(M.encoded(changes))
        print(json.dumps({'read_only_metadata_drift': dict(Counter((row['after'] or row['before'])['key'] for row in changes))}), flush=True)
    finally:
        remote.close()

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--preflight', action='store_true')
    group.add_argument('--deploy', action='store_true')
    group.add_argument('--diagnose-state', action='store_true')
    args = parser.parse_args()
    if args.preflight:
        subprocess.run(['php.cmd', str(HERE / '2026-11-05-test-reservation-inventory.php')], check=True, cwd=M.ROOT)
        subprocess.run([sys.executable, str(HERE / '2026-11-05-test-reservation-deploy.py')], check=True, cwd=M.ROOT)
        M.OUT.mkdir(parents=True, exist_ok=True)
        (M.OUT / 'backup.json').write_bytes((M.ROOT / 'outputs/2026-11-04-crm-review/backup.json').read_bytes())
        M.preflight()
    elif args.deploy:
        M.deploy()
    elif args.diagnose_state:
        diagnose_state()
    else:
        print('Offline by default; fresh full backup and --preflight required before --deploy.')
