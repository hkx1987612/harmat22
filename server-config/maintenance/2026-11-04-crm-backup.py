"""Private backup only. Never restore or change live CRM records."""
import argparse
from datetime import datetime, timezone
import json
from pathlib import Path
import runpy
import shlex
import stat
import subprocess
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
H = runpy.run_path(str(HERE / '2026-10-14-deploy-appointment-source.py'))
Q = shlex.quote
SOURCES = (
    'wp-content/plugins/harmat-sales-manager/harmat-sales-manager.php',
    'wp-content/plugins/harmat-legal-documents/harmat-legal-documents.php',
    'wp-content/mu-plugins/zz-harmat-crm-bandwidth-widget.php',
)
MATERIALS = ('wp-content/uploads/harmat-customer-materials', 'wp-content/uploads/harmat-legal-private')


def backup():
    r = H['Remote']()
    try:
        parent, live = H['BACKUPS'], H['LIVE']
        r.directory(parent)
        H['require'](not parent.startswith(live + '/'), 'Backup must be outside public web root')
        wp = H['WP'] + ' --skip-plugins --skip-themes'
        tables = int(r.run(wp + ' db query ' + Q('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND TABLE_TYPE="BASE TABLE"') + ' --skip-column-names'))
        non_transactional = int(r.run(wp + ' db query ' + Q('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND TABLE_TYPE="BASE TABLE" AND ENGINE<>"InnoDB"') + ' --skip-column-names'))
        H['require'](tables > 0 and non_transactional == 0, 'Require a consistent InnoDB snapshot; no live locks applied')
        db_bytes = int(r.run(wp + ' db query ' + Q('SELECT COALESCE(SUM(DATA_LENGTH+INDEX_LENGTH),0) FROM information_schema.tables WHERE table_schema=DATABASE()') + ' --skip-column-names'))
        free_kib = int(r.run('df -Pk ' + Q(parent) + " | awk 'NR==2 {print $4}'"))
        paths = list(SOURCES)
        for relative in MATERIALS:
            try:
                info = r.info(live + '/' + relative)
            except FileNotFoundError:
                continue
            H['require'](stat.S_ISDIR(info.st_mode), 'Material path must be a directory')
            paths.append(relative)
        asset_bytes = int(r.run('du -sb ' + ' '.join(Q(live + '/' + p) for p in paths) + " | awk '{s+=$1} END{printf \"%.0f\",s}'"))
        H['require'](free_kib * 1024 > max(512 * 1024 * 1024, db_bytes * 5 + asset_bytes * 3), 'Insufficient backup headroom')
        folder = parent + '/crm-before-simplification-' + uuid.uuid4().hex
        r.mkdir(folder)
        sql, archive = folder + '/database.sql', folder + '/crm-files.tar.gz'
        r.run('umask 077; ' + wp + ' db export ' + Q(sql) + ' --single-transaction --quick --skip-lock-tables --add-drop-table')
        r.sftp.chmod(sql, 0o600)
        sql_info = r.info(sql)
        H['require'](stat.S_ISREG(sql_info.st_mode) and stat.S_IMODE(sql_info.st_mode) == 0o600 and sql_info.st_size > 1000, 'Database backup not complete')
        create_count = int(r.run("grep -c '^CREATE TABLE ' " + Q(sql)))
        H['require'](create_count == tables, 'Database schema count mismatch')
        r.run('umask 077; tar -czf ' + Q(archive) + ' -C ' + Q(live) + ' ' + ' '.join(Q(p) for p in paths))
        r.sftp.chmod(archive, 0o600)
        r.run('gzip -t ' + Q(archive))
        r.run('tar -tzf ' + Q(archive) + ' >/dev/null')
        hashes = {}
        for p in (sql, archive):
            info = r.info(p)
            H['require'](stat.S_IMODE(info.st_mode) == 0o600, 'Private backup mode required')
            digest = r.run('sha256sum ' + Q(p)).split()[0]
            H['require'](len(digest) == 64, 'Hash unavailable')
            hashes[p.rsplit('/', 1)[1]] = {'sha256': digest, 'bytes': info.st_size}
        source_hashes = {p: H['sha'](r.read(live + '/' + p)) for p in SOURCES}
        report = {'status': 'BACKUP_VERIFIED', 'utc': datetime.now(timezone.utc).isoformat(),
                  'backup': folder, 'tables': tables, 'transactional_snapshot': True,
                  'files': hashes, 'source_hashes': source_hashes,
                  'includes': paths, 'restore_drill_performed': False,
                  'policy': 'PRIVATE_REMOTE_ONLY_NO_DATABASE_RESTORE'}
        r.write_new(folder + '/manifest.json', json.dumps(report).encode())
        output = ROOT / 'outputs/2026-11-04-crm-review'
        output.mkdir(parents=True, exist_ok=True)
        (output / 'backup.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
        # Drift check reads code only, never exports customer data locally.
        matches = {}
        for relative, repository in (
            (SOURCES[0], 'wp-plugins/harmat-sales-manager/harmat-sales-manager.php'),
            (SOURCES[2], 'wp-mu-plugins/zz-harmat-crm-bandwidth-widget.php'),
        ):
            base = subprocess.check_output(['git', 'show', 'HEAD:' + repository], cwd=ROOT)
            matches[repository] = r.read(live + '/' + relative).replace(b'\r\n', b'\n') == base
        print(json.dumps({'status': report['status'], 'backup': folder, 'tables': tables,
                          'files': hashes, 'baseline_matches': matches}), flush=True)
    finally:
        r.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--backup', action='store_true')
    args = parser.parse_args()
    if args.backup:
        backup()
    else:
        print('No connection or change. Pass --backup for the user-authorized private backup.')
