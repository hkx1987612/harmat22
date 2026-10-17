"""Native pure validators only: no REST dispatch, customer write or mail."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex

MAINT=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('language_check',MAINT/'2026-10-17-deploy-internal-language.py')
D=importlib.util.module_from_spec(spec);spec.loader.exec_module(D)

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--backup',required=True);args=p.parse_args()
    D.require(re.fullmatch(re.escape(D.PREFIX)+r'[a-f0-9]{32}',args.backup),'Exact preview backup')
    r=D.H.Remote()
    try:
        before=D.snapshot(r)
        body=(MAINT/'2026-10-16-native-offer-readonly.php').read_bytes().replace(b'\r\n',b'\n')
        target=args.backup+'/native-offer-readonly.php.txt'
        if D.D.absent(r,target):r.write_new(target,body)
        D.require(r.read(target,private=True)==body,'Pure checker ownership');r.lint(target)
        offer=json.loads(r.run(D.H.WP+' eval-file '+shlex.quote(target)))
        antispam=json.loads(r.run(D.H.WP+' eval '+shlex.quote(D.D.I.HEALTH.RUNTIME_CHECK)))
        D.require(offer['status']=='PASS' and offer['checks']==35 and antispam['status']=='PASS' and antispam['checks']==17,'Native validator regression')
        D.require(D.snapshot(r)==before,'Native checker altered protected state')
        proof={'offer':offer,'antispam':antispam,'protected_unchanged':True}
        if D.D.absent(r,args.backup+'/native-validation-proof.json'):
            D.D.write_json(r,args.backup+'/native-validation-proof.json',proof)
        print(json.dumps(proof))
    finally:r.close()
