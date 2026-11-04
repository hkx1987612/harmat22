"""CSS-only desktop readability release through the guarded CRM transport."""
import argparse
import importlib.util
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('crm_readable_transport', HERE / '2026-11-04-deploy-crm-simplification.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
M.BASE = '1efa6ff'
M.PATHS = {'crm': M.PATHS['crm']}
M.PREFIX = M.H.BACKUPS + '/crm-desktop-readable-'
M.LOCK = M.H.BACKUPS + '/.crm-desktop-readable.lock'

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--preflight', action='store_true')
    group.add_argument('--deploy', action='store_true')
    args = parser.parse_args()
    if args.preflight: M.preflight()
    elif args.deploy: M.deploy()
    else: print('Offline by default; --preflight before --deploy.')
