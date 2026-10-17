"""Read only a private synthetic fixture diagnostic; never print original page data."""
import argparse
import importlib.util
from pathlib import Path
import re
import shlex

spec = importlib.util.spec_from_file_location('project_release', Path(__file__).with_name('2026-10-17-deploy-project-family.py'))
D = importlib.util.module_from_spec(spec)
spec.loader.exec_module(D)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--backup', required=True)
    args = parser.parse_args()
    D.require(re.fullmatch(re.escape(D.PREFIX) + r'[a-f0-9]{32}', args.backup), 'Exact backup required')
    r = D.H.Remote()
    try:
        for name in (D.TEST, D.MAIN):
            expected = (D.MAINT / name if name == D.TEST else D.ROOT / 'wp-mu-plugins' / name).read_bytes().replace(b'\r\n', b'\n')
            D.require(r.read(args.backup + '/' + name, private=True) == expected, 'Diagnostic candidate differs')
        command = D.H.PHP + ' -d display_errors=1 -d log_errors=0 ' + shlex.quote(args.backup + '/' + D.TEST) + ' ' + shlex.quote(args.backup + '/' + D.MAIN)
        preview = args.backup + '/' + D.PREVIEW
        if not D.absent(r, preview):
            command += ' ' + shlex.quote(preview)
        stdin, stdout, stderr = r.client.exec_command(command, timeout=120)
        stdin.close()
        result = stdout.read(12000).decode('utf-8', 'replace')
        error = stderr.read(12000).decode('utf-8', 'replace')
        print('synthetic_fixture_exit=' + str(stdout.channel.recv_exit_status()))
        print(result)
        print(error)
    finally:
        r.close()


if __name__ == '__main__':
    main()
