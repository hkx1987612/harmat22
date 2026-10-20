"""Read-only current verification; retain CRM activity after a proven release."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import uuid

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('loop_current',HERE/'2026-10-20-deploy-home-loop.py')
M=importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)


def non_crm(value):
    copy=json.loads(json.dumps(value))
    copy['protected'].pop('crm_options')
    return copy


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--backup',required=True)
    parser.add_argument('--expect',required=True)
    parser.add_argument('--diagnose',action='store_true')
    args=parser.parse_args()
    edits,helpers,protected,pins,digest=M.release()
    M.require(args.expect==digest,'Exact reviewed aggregate required')
    M.require(re.fullmatch(re.escape(M.H.BACKUPS+'/home-loop-')+r'[a-f0-9]{32}',args.backup),'Exact backup path required')
    r=M.H.Remote()
    try:
        saved=json.loads(r.read(args.backup+'/manifest.json',private=True))
        released=json.loads(r.read(args.backup+'/release.json',private=True))
        M.require(saved['pins']==pins and saved['digest']==digest,'Backup ownership changed')
        M.require(released['public']['main_unchanged'] is True and released['public']['health_routes']==7
                  and released['public']['playback']=='automatic_loop_play_only_fallback'
                  and released['filters']['offer_checks']==35 and released['filters']['antispam_checks']==17,
                  'Successful release evidence required')
        for path,(old,new) in edits.items():
            M.require(M.Q.metadata(r,path,new)==tuple(saved['metadata'][path]),'Released source drift')
        for name,data in helpers.items():
            M.require(r.read(args.backup+'/'+name,private=True)==data,'Saved checker changed')
        before=M.P.state(r,protected)
        if args.diagnose:
            changed={key:[name for name in set(saved['before'][key])|set(before[key])
                           if saved['before'][key].get(name)!=before[key].get(name)]
                     for key in before if isinstance(before[key],dict) and saved['before'].get(key)!=before[key]}
            print(json.dumps({'status':'READ_ONLY_DIAGNOSIS','changed_sections':changed,'values_exported':False}));return
        M.require(non_crm(saved['before'])==non_crm(before),'Non-CRM protected state changed since release')
        baseline=r.read(args.backup+'/baseline.html',private=True)
        M.require(M.sha(baseline)==saved['baseline'],'Saved public baseline changed')
        proof=M.public(r,baseline,protected,edits,True)
        filters=M.D.validate_native(r,args.backup)
        M.require(M.P.state(r,protected)==before,'Concurrent state changed during current verification')
        result={'status':'VERIFIED_CURRENT_STATE','proof':proof,'filters':filters,
                'crm_options_changed_after_successful_release':saved['before']['protected']['crm_options']!=before['protected']['crm_options'],
                'business_data_stable_during_this_check':True,'database_restored_or_written':False}
        M.D.write_json(r,args.backup+'/current-verification-'+uuid.uuid4().hex+'.json',result)
        print(json.dumps(result),flush=True)
    finally:
        r.close()


if __name__=='__main__': main()
