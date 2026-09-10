"""Restore only the homepage native video with staged lint and guarded rollback."""
import argparse
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import shlex
import subprocess
import paramiko

ROOT = Path(__file__).resolve().parents[2]
LIVE = '/home/harmath2/public_html'
BASE = 'd0d15799b817b8ffbee2a1b98bb99b5384a00ddb'
GUARD = 'wp-mu-plugins/zz-harmat-home-youtube-guard.php'
VIDEO = '/wp-content/uploads/harmat-video/harmat-home-1080p-v2.mp4'
FILES = {
    'outputs/home-native-video/harmat-home-1080p-v2.mp4': VIDEO,
    'server-config/home-native-video.htaccess': '/wp-content/uploads/harmat-video/.htaccess',
    'wp-mu-plugins/assets/harmat-home-native-video.js': '/wp-content/mu-plugins/assets/harmat-home-native-video.js',
    GUARD: '/wp-content/mu-plugins/zz-harmat-home-youtube-guard.php',
}
q = shlex.quote
sha = lambda data: hashlib.sha256(data).hexdigest()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--deploy', action='store_true')
    parser.add_argument('--rollback', help='Exact backup path from this deployment')
    args = parser.parse_args()
    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.load_host_keys(str(Path.home() / '.ssh/known_hosts'))
    client.connect('185.111.89.244', username='harmath2',
                   key_filename=str(Path.home() / 'Downloads/harmat'),
                   passphrase=(Path.home() / '.ssh/harmat_key_pass.txt').read_text().strip(),
                   look_for_keys=False, allow_agent=False, timeout=20)
    sftp = client.open_sftp()
    stamp = datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')
    backup = '/home/harmath2/codex-backups/home-native-video-' + stamp
    staged, installed, original = {}, [], {}

    def run(cmd):
        _, out, err = client.exec_command(cmd, timeout=150)
        stdout, stderr = out.read().decode(), err.read().decode()
        if out.channel.recv_exit_status():
            raise RuntimeError(cmd + ': ' + stdout + stderr)
        return stdout.strip()

    def read(path):
        try:
            with sftp.open(path, 'rb') as f:
                if f.stat().st_size > 1024 * 1024:
                    f.prefetch(max_concurrent_requests=16)
                return f.read()
        except FileNotFoundError:
            return None

    def wp(code):
        return run('wp --path=' + q(LIVE) + ' eval ' + q(code))

    def purge():
        print(wp('if(function_exists("wp_cache_clear_cache"))wp_cache_clear_cache();'
                 'wp_cache_flush();echo "CACHE_CLEARED";'))

    def state():
        return wp('echo wp_json_encode(array("offers"=>(int)wp_count_posts("harmat_offer_lead")->private,'
                  '"wp"=>get_bloginfo("version"),"bandwidth"=>get_option("harmat_bw_last_usage")));')

    def rollback_guard(source):
        target = LIVE + FILES[GUARD]
        restore = target + '.restore-' + stamp
        run('cp -p ' + q(source + '/guard.php') + ' ' + q(restore))
        print(run('php -l ' + q(restore)))
        sftp.posix_rename(restore, target)
        print(run('php -l ' + q(target)))
        purge()

    try:
        if args.rollback:
            if not args.rollback.startswith('/home/harmath2/codex-backups/home-native-video-') or '/..' in args.rollback:
                raise RuntimeError('Unexpected rollback path')
            current = read(LIVE + FILES[GUARD])
            if current != (ROOT / GUARD).read_bytes():
                raise RuntimeError('Concurrent guard change; review before rollback')
            rollback_guard(args.rollback)
            print('ROLLED_BACK_HOMEPAGE_TO_YOUTUBE; unused versioned media retained')
            return
        baseline = subprocess.check_output(['git', 'show', BASE + ':' + GUARD], cwd=ROOT)
        for local, remote in FILES.items():
            original[local] = read(LIVE + remote)
            if local == GUARD:
                if original[local].replace(b'\r\n', b'\n') != baseline.replace(b'\r\n', b'\n'):
                    raise RuntimeError('Live guard differs from Git baseline')
            elif original[local] is not None:
                raise RuntimeError('New target already exists: ' + remote)
        before = json.loads(state())
        log_before = run('stat -c "%s %Y" ' + q(LIVE + '/error_log'))
        print('BASELINE_OK; BEFORE=' + json.dumps(before), flush=True)
        if not args.deploy:
            return
        run('mkdir -m 700 ' + q(backup))
        run('cp -p ' + q(LIVE + FILES[GUARD]) + ' ' + q(backup + '/guard.php'))
        if read(backup + '/guard.php') != original[GUARD]:
            raise RuntimeError('Backup mismatch')
        print('BACKUP=' + backup, flush=True)
        hashes = {}
        for local, remote in FILES.items():
            target = LIVE + remote
            run('mkdir -p ' + q(target.rsplit('/', 1)[0]))
            staged[local] = target.rsplit('/', 1)[0] + '/.codex-native-' + stamp + '-' + str(len(staged))
            sftp.put(str(ROOT / local), staged[local])
            sftp.chmod(staged[local], 0o644)
            hashes[local] = sha((ROOT / local).read_bytes())
            if sha(read(staged[local])) != hashes[local]:
                raise RuntimeError('Staged hash mismatch')
            if local.endswith('.php'):
                print(run('php -l ' + q(staged[local])))
        for local, remote in FILES.items():
            if read(LIVE + remote) != original[local]:
                raise RuntimeError('Concurrent edit: ' + remote)
            sftp.posix_rename(staged[local], LIVE + remote)
            installed.append(local)
            if local.endswith('.php'):
                print(run('php -l ' + q(LIVE + remote)))
            if sha(read(LIVE + remote)) != hashes[local]:
                raise RuntimeError('Final hash mismatch')
        run('cp -p ' + q(LIVE + VIDEO) + ' ' + q(backup + '/harmat-home-1080p-v2.mp4'))
        purge()
        for path in ('/', '/lakaskereso/', '/property/a1-1-l2/', '/galeria/', '/epitesi-naplo/',
                     '/virtualis-lakasvalaszto/', '/virtualis-lakasvalaszto-elso-utem/',
                     '/virtualis-lakasvalaszto-a1-epulet/', '/harmat-lakopark-kornyeke/', '/elerhetosegeink/'):
            html = run('curl --fail --compressed -sS --max-time 40 ' + q('https://harmat22.hu' + path))
            if any(x in html for x in ('Fatal error:', 'Parse error:', 'critical error on this website')):
                raise RuntimeError('Error on ' + path)
            if path == '/':
                if html.count('id="harmat-native-home-video"') != 1 or 'id="harmat-youtube-hero-runtime"' in html:
                    raise RuntimeError('Homepage native guard failed')
            elif 'id="harmat-native-home-video"' in html:
                raise RuntimeError('Native hero outside homepage')
            print('PAGE_OK=' + path, flush=True)
        after = json.loads(state())
        if after['offers'] < before['offers']:
            raise RuntimeError('Lead count decreased')
        log_after = run('stat -c "%s %Y" ' + q(LIVE + '/error_log'))
        report = dict(backup=backup, before=before, after=after, hashes=hashes,
                      errorLogBefore=log_before, errorLogAfter=log_after)
        (ROOT / 'outputs/home-native-video/deployment.json').write_text(json.dumps(report, indent=2))
        print('DEPLOYED=' + json.dumps(report), flush=True)
    except Exception:
        if GUARD in installed:
            rollback_guard(backup)
        for local in reversed(installed):
            if local != GUARD and original[local] is None:
                sftp.remove(LIVE + FILES[local])
        if installed:
            print('ROLLED_BACK', flush=True)
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
