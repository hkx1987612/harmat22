"""Read-only current verification when normal CRM activity follows a successful release."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import uuid

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('six_current_verify',HERE/'2026-10-20-deploy-home-six-r2.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--backup',required=True)
    p.add_argument('--expect',required=True)
    args = p.parse_args()
    bundle = M.release()
    edits,helpers,protected,pins,digest = bundle
    M.require(args.expect == digest,'Exact reviewed aggregate required')
    M.require(re.fullmatch(re.escape(M.H.BACKUPS+'/home-six-r2-')+r'[a-f0-9]{32}',args.backup),'Exact backup path required')
    r = M.H.Remote()
    try:
        saved = json.loads(r.read(args.backup+'/manifest.json',private=True))
        release = json.loads(r.read(args.backup+'/release.json',private=True))
        M.require(saved['pins'] == pins and saved['digest'] == digest,'Backup ownership changed')
        M.require(release['public']['cards'] == 6 and release['filters']['six_card_checks'] == 141,'Successful release evidence required')
        for path,(_,new) in edits.items():
            M.require(M.Q.metadata(r,path,new) == tuple(saved['metadata'][path]),'Released source drift')
        for name,data in helpers.items():
            M.require(r.read(args.backup+'/'+name,private=True) == data,'Saved checker changed')
        before = M.A.state(r,protected)
        old = json.loads(json.dumps(saved['before']))
        current = json.loads(json.dumps(before))
        old_crm = old['protected'].pop('crm_options')
        current_crm = current['protected'].pop('crm_options')
        M.require(old == current,'Non-CRM protected state changed since release')
        baseline = r.read(args.backup+'/baseline.html',private=True)
        M.require(M.sha(baseline) == saved['baseline'],'Saved public baseline changed')
        proof = M.public(r,baseline,protected,edits,True)
        filters = M.validate_native(r,args.backup)
        M.require(M.A.state(r,protected) == before,'Concurrent state changed during current verification')
        result = {'status':'VERIFIED_CURRENT_STATE','proof':proof,'filters':filters,
                  'crm_options_changed_after_successful_release':old_crm != current_crm,
                  'business_data_stable_during_this_check':True,'database_restored_or_written':False}
        M.D.write_json(r,args.backup+'/current-verification-'+uuid.uuid4().hex+'.json',result)
        print(json.dumps(result),flush=True)
    finally:
        r.close()


if __name__ == '__main__':
    main()
