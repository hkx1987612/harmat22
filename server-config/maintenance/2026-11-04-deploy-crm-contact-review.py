"""CRM contact presentation and reminder classification; no record writes."""
import argparse
import importlib.util
import subprocess
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('crm_contact_transport', HERE / '2026-11-04-deploy-crm-simplification.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
M.BASE = '2ac5636'
M.PATHS = {'crm': M.PATHS['crm']}
M.PREFIX = M.H.BACKUPS + '/crm-contact-review-'
M.LOCK = M.H.BACKUPS + '/.crm-contact-review.lock'

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--preflight', action='store_true')
    group.add_argument('--deploy', action='store_true')
    args = parser.parse_args()
    if args.preflight:
        subprocess.run(['php.cmd', str(HERE / '2026-11-04-test-crm-contact-review.php')], check=True, cwd=M.ROOT)
        M.preflight()
    elif args.deploy: M.deploy()
    else: print('Offline by default; --preflight before --deploy.')
