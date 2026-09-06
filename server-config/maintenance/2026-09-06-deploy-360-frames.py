"""Guarded, two-file 360 frame-rendering deployment; no database writes."""
import argparse
import hashlib
import json
from pathlib import Path
import shlex
import subprocess
from datetime import datetime, timezone

import paramiko

ROOT = Path(__file__).resolve().parents[2]
LIVE = '/home/harmath2/public_html'
BASE = '85f6cda93c64d4bdf5969760709cf4f1efcfdeb4'
FILES = ('viewer.js', 'lakaspark-360.php')
q = shlex.quote
digest = lambda data: hashlib.sha256(data).hexdigest()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--key', required=True)
    parser.add_argument('--passphrase-file', required=True)
    parser.add_argument('--deploy', action='store_true')
    args = parser.parse_args()
    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.load_host_keys(str(Path.home() / '.ssh' / 'known_hosts'))
    client.connect('185.111.89.244', username='harmath2', key_filename=args.key,
                   passphrase=Path(args.passphrase_file).read_text().strip(),
                   look_for_keys=False, allow_agent=False, timeout=20)
    sftp = client.open_sftp()
    stamp = datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')
    backup = '/home/harmath2/codex-backups/360-no-flash-' + stamp
    installed, staged, original, target = [], {}, {}, {}

    def run(cmd, timeout=120):
        _, out, err = client.exec_command(cmd, timeout=timeout)
        stdout, stderr = out.read().decode(), err.read().decode()
        code = out.channel.recv_exit_status()
        if code:
            raise RuntimeError(f'Command failed ({code}): {stdout} {stderr}')
        return stdout.strip()

    def read(path):
        with sftp.open(path, 'rb') as stream:
            return stream.read()

    def wp(code):
        return run('cd ' + q(LIVE) + ' && wp eval ' + q(code))

    def purge():
        return wp('if(function_exists("wp_cache_clear_cache"))wp_cache_clear_cache();'
                  'wp_cache_flush();echo "CACHE_CLEARED";')

    def state():
        return json.loads(wp('echo wp_json_encode(array("offers"=>'
            '(int)wp_count_posts("harmat_offer_lead")->private,"wp"=>get_bloginfo("version")));'))

    try:
        plugin_dir = wp('echo WP_PLUGIN_DIR;') + '/360'
        if plugin_dir != LIVE + '/wp-content/plugins/360':
            raise RuntimeError('Unexpected 360 plugin directory: ' + plugin_dir)
        for name in FILES:
            target[name] = plugin_dir + '/' + name
            original[name] = read(target[name])
            baseline = subprocess.check_output(['git', 'show', BASE + ':wp-plugins/360/' + name], cwd=ROOT)
            if original[name].replace(b'\r\n', b'\n') != baseline.replace(b'\r\n', b'\n'):
                raise RuntimeError('Live file differs from reviewed Git baseline: ' + name)
        before = state()
        print('LIVE_BASELINE_MATCH=' + BASE)
        print('BEFORE=' + json.dumps(before))
        if not args.deploy:
            return

        run('mkdir -m 700 ' + q(backup))
        for name in FILES:
            run('cp -p ' + q(target[name]) + ' ' + q(backup + '/' + name))
            if digest(read(backup + '/' + name)) != digest(original[name]):
                raise RuntimeError('Backup checksum mismatch: ' + name)
        print('BACKUP=' + backup, flush=True)
        before_log = run('stat -c "%s %Y" ' + q(LIVE + '/error_log') + ' 2>/dev/null || true')
        source_hashes = {}
        for name in FILES:
            path = ROOT / 'wp-plugins' / '360' / name
            staged[name] = plugin_dir + '/.' + name + '.codex-tmp-' + stamp
            source_hashes[name] = digest(path.read_bytes())
            sftp.put(str(path), staged[name])
            sftp.chmod(staged[name], sftp.stat(target[name]).st_mode & 0o777)
            if digest(read(staged[name])) != source_hashes[name]:
                raise RuntimeError('Staged hash mismatch: ' + name)
            if name.endswith('.php'):
                print(run('php -l ' + q(staged[name])))

        for name in FILES:
            if read(target[name]) != original[name]:
                raise RuntimeError('Concurrent live edit: ' + name)
            sftp.posix_rename(staged[name], target[name])
            installed.append(name)
            if name.endswith('.php'):
                print(run('php -l ' + q(target[name])))
            if digest(read(target[name])) != source_hashes[name]:
                raise RuntimeError('Final hash mismatch: ' + name)
        print(purge())
        after = state()
        if after['offers'] < before['offers']:
            raise RuntimeError('Existing lead count decreased')
        for path in ('/', '/lakaskereso/', '/property/a1-1-l2/', '/virtualis-lakasvalaszto/',
                     '/virtualis-lakasvalaszto-elso-utem/', '/virtualis-lakasvalaszto-a2-epulet/',
                     '/elerhetosegeink/'):
            html = run('curl --fail --compressed -sS --max-time 40 ' + q('https://harmat22.hu' + path))
            if any(marker in html for marker in ('Fatal error:', 'Parse error:', 'critical error on this website')):
                raise RuntimeError('PHP error output on ' + path)
            if 'virtualis-lakasvalaszto' in path and 'viewer.js?ver=6.3' not in html:
                raise RuntimeError('Stale viewer version on ' + path)
            print('HTTP_OK=' + path)
        after_log = run('stat -c "%s %Y" ' + q(LIVE + '/error_log') + ' 2>/dev/null || true')
        report = {'backup': backup, 'before': before, 'after': after, 'hashes': source_hashes,
                  'errorLogBefore': before_log, 'errorLogAfter': after_log,
                  'databaseChanged': False, 'baseline': BASE}
        out = ROOT / 'outputs' / '360-rotation'
        out.mkdir(parents=True, exist_ok=True)
        (out / 'deployment.json').write_text(json.dumps(report, indent=2) + '\n')
        print('AFTER=' + json.dumps(after))
        print('ERROR_LOG_UNCHANGED=' + str(before_log == after_log))
        print('DEPLOYED_HTTP_VERIFIED')
    except Exception:
        for name in reversed(installed):
            rollback = staged[name] + '-rollback'
            run('cp -p ' + q(backup + '/' + name) + ' ' + q(rollback))
            if name.endswith('.php'):
                run('php -l ' + q(rollback))
            sftp.posix_rename(rollback, target[name])
            if name.endswith('.php'):
                run('php -l ' + q(target[name]))
            if read(target[name]) != original[name]:
                raise RuntimeError('Rollback verification failed: ' + name)
        if installed:
            print(purge())
            print('ROLLED_BACK')
        raise
    finally:
        for path in staged.values():
            try:
                sftp.remove(path)
            except FileNotFoundError:
                pass
        sftp.close()
        client.close()


if __name__ == '__main__':
    main()
