"""Deploy the three reviewed tracking files with hash guards, lint and rollback."""
import argparse
import hashlib
import json
import pathlib
import shlex
from datetime import datetime, timezone

import paramiko


ROOT = pathlib.Path(__file__).resolve().parents[2]
LIVE = '/home/harmath2/public_html'
MU = LIVE + '/wp-content/mu-plugins'
BASE = {
    'zz-harmat-confirmed-lead-tracking.php': None,
    'harmat-unified-offer-modal.php': '482aff733f17865e442c558ff71dfc16ba07be7e54a92503493d063290a2e658',
    'harmat-performance-guard.php': 'f04ed0cf11d169e7860331a9decd3d320988ceac416b742cf8429e28ec93550c',
}
q = shlex.quote


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--key', required=True)
    parser.add_argument('--passphrase-file', required=True)
    parser.add_argument('--deploy', action='store_true')
    args = parser.parse_args()
    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.load_host_keys(str(pathlib.Path.home() / '.ssh' / 'known_hosts'))
    client.connect('185.111.89.244', username='harmath2', key_filename=args.key,
                   passphrase=pathlib.Path(args.passphrase_file).read_text().strip(),
                   look_for_keys=False, allow_agent=False, timeout=20)
    sftp = client.open_sftp()
    stamp = datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')
    backup = '/home/harmath2/codex-backups/ads-confirmed-leads-' + stamp
    staged = []
    replaced = []

    def run(command):
        _, stdout, stderr = client.exec_command(command, timeout=90)
        out, err = stdout.read().decode(), stderr.read().decode()
        code = stdout.channel.recv_exit_status()
        if code:
            raise RuntimeError(f'Remote command failed ({code}): {out} {err}')
        return out.strip()

    def remote_hash(path):
        try:
            with sftp.open(path, 'rb') as source:
                return hashlib.sha256(source.read()).hexdigest()
        except FileNotFoundError:
            return None

    def purge():
        return run('cd ' + q(LIVE) + ' && wp eval ' + q(
            'if (function_exists("wp_cache_clear_cache")) { wp_cache_clear_cache(); } '
            'wp_cache_flush(); echo "CACHE_CLEARED";'))

    try:
        for name, expected in BASE.items():
            if remote_hash(MU + '/' + name) != expected:
                raise RuntimeError('Live file drift, refusing deployment: ' + name)
        print('LIVE_BASELINE_HASHES_MATCH')
        print(run('cd ' + q(LIVE) + ' && wp eval ' + q(
            '$s=get_option("googlesitekit_analytics-4_settings",array()); '
            'echo "GA4=".($s["measurementID"]??"")."\n"; '
            'echo "OFFERS=".wp_count_posts("harmat_offer_lead")->private."\n";')))
        if not args.deploy:
            return

        run('mkdir -m 700 ' + q(backup))
        for name, expected in BASE.items():
            if expected:
                run('cp -p ' + q(MU + '/' + name) + ' ' + q(backup + '/' + name))
                if remote_hash(backup + '/' + name) != expected:
                    raise RuntimeError('Backup verification failed: ' + name)
        print('BACKUP=' + backup)

        candidates = {}
        for name in BASE:
            path = MU + '/.' + name + '.codex-tmp-' + stamp
            staged.append(path)
            source = ROOT / 'wp-mu-plugins' / name
            candidates[name] = hashlib.sha256(source.read_bytes()).hexdigest()
            sftp.put(str(source), path)
            sftp.chmod(path, 0o644)
            if remote_hash(path) != candidates[name]:
                raise RuntimeError('Upload verification failed: ' + name)
            print(run('php -l ' + q(path)))

        for name, expected in BASE.items():
            if remote_hash(MU + '/' + name) != expected:
                raise RuntimeError('Live file changed while staging: ' + name)
        for name, staged_path in zip(BASE, staged):
            replaced.append(name)
            sftp.posix_rename(staged_path, MU + '/' + name)
            print(run('php -l ' + q(MU + '/' + name)))
        for name, expected in candidates.items():
            if remote_hash(MU + '/' + name) != expected:
                raise RuntimeError('Final verification failed: ' + name)
        print(purge())
        print(run('cd ' + q(LIVE) + ' && wp eval ' + q(
            'echo "WP_BOOT_OK\n"; '
            'echo "OFFERS=".wp_count_posts("harmat_offer_lead")->private."\n";')))
        report = {'deployedAt': stamp, 'backup': backup, 'files': candidates,
                  'baseline': BASE, 'databaseChanged': False}
        output = ROOT / 'outputs' / 'ads-confirmed-leads'
        output.mkdir(parents=True, exist_ok=True)
        (output / 'deployment.json').write_text(json.dumps(report, indent=2) + '\n')
        print('DEPLOYED_AND_VERIFIED')
    except Exception:
        if replaced:
            for name in reversed(replaced):
                target = MU + '/' + name
                if BASE[name]:
                    restore = target + '.rollback-tmp-' + stamp
                    run('cp -p ' + q(backup + '/' + name) + ' ' + q(restore))
                    run('php -l ' + q(restore))
                    sftp.posix_rename(restore, target)
                    run('php -l ' + q(target))
                elif remote_hash(target) is not None:
                    sftp.remove(target)
            print(purge())
            print('ROLLED_BACK')
        raise
    finally:
        for path in staged:
            try:
                sftp.remove(path)
            except FileNotFoundError:
                pass
        sftp.close()
        client.close()


if __name__ == '__main__':
    main()
