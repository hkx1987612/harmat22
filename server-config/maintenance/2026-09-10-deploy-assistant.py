"""Read-only preflight or guarded two-file assistant deployment; no content edits."""
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
BASE = 'd221189'
PLUGIN = 'wp-plugins/harmat-local-assistant/harmat-local-assistant.php'
MU = 'wp-mu-plugins/zz-harmat-assistant-live-data.php'
FILES = {MU: '/wp-content/mu-plugins/zz-harmat-assistant-live-data.php',
         PLUGIN: '/wp-content/plugins/harmat-local-assistant/harmat-local-assistant.php'}
OUT = ROOT / 'outputs/assistant-live-data'
q = shlex.quote


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--deploy', action='store_true')
    parser.add_argument('--verify', action='store_true')
    parser.add_argument('--rollback')
    args = parser.parse_args()
    OUT.mkdir(parents=True, exist_ok=True)
    c = paramiko.SSHClient()
    c.load_system_host_keys()
    c.load_host_keys(str(Path.home() / '.ssh/known_hosts'))
    c.connect('185.111.89.244', username='harmath2', key_filename=str(Path.home() / 'Downloads/harmat'),
              passphrase=(Path.home() / '.ssh/harmat_key_pass.txt').read_text().strip(),
              look_for_keys=False, allow_agent=False, timeout=20)
    s = c.open_sftp()
    stamp = datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')
    backup = '/home/harmath2/codex-backups/assistant-live-data-' + stamp
    staged, installed, originals = {}, [], {}

    def run(cmd):
        _, out, err = c.exec_command(cmd, timeout=150)
        a, b = out.read().decode(), err.read().decode()
        if out.channel.recv_exit_status():
            raise RuntimeError(cmd + ': ' + a + b)
        return a.strip()

    def read(path):
        try:
            with s.open(path, 'rb') as f:
                f.prefetch(max_concurrent_requests=8)
                return f.read()
        except FileNotFoundError:
            return None

    def wp(code):
        return run('wp --path=' + q(LIVE) + ' eval ' + q(code))

    def purge():
        print(wp('if(function_exists("wp_cache_clear_cache"))wp_cache_clear_cache();wp_cache_flush();echo "CACHE_CLEARED";'), flush=True)

    def state():
        return dict(offers=int(wp('echo (int)wp_count_posts("harmat_offer_lead")->private;')),
                    errorLog=run('stat -c "%s %Y" ' + q(LIVE + '/error_log')))

    def restore(source):
        target = LIVE + FILES[PLUGIN]
        temp = target.rsplit('/', 1)[0] + '/.assistant-restore-' + stamp
        run('cp -p ' + q(source + '/assistant.php') + ' ' + q(temp))
        print(run('php -l ' + q(temp)))
        s.posix_rename(temp, target)
        print(run('php -l ' + q(target)))
        if read(LIVE + FILES[MU]) is not None:
            s.remove(LIVE + FILES[MU])
        purge()

    try:
        if args.verify:
            for local, remote in FILES.items():
                if read(LIVE + remote) != (ROOT / local).read_bytes():
                    raise RuntimeError('Live file mismatch: ' + remote)
            check = json.loads(wp('$r=harmat_assistant_public_apartments(null);echo wp_json_encode(array("rows"=>count($r),"version"=>Harmat_Local_Assistant::VERSION));'))
            check.update(state())
            leftovers = []
            for remote in FILES.values():
                leftovers.extend(n for n in s.listdir((LIVE + remote).rsplit('/', 1)[0]) if n.startswith('.assistant-stage-'))
            if check['rows'] != 124 or check['version'] != '0.4.0' or leftovers:
                raise RuntimeError('Live verification failed')
            (OUT / 'final-verification.json').write_text(json.dumps(check, indent=2))
            print('VERIFIED=' + json.dumps(check))
            return
        if args.rollback:
            if not args.rollback.startswith('/home/harmath2/codex-backups/assistant-live-data-') or '/..' in args.rollback:
                raise RuntimeError('Invalid rollback path')
            for local, remote in FILES.items():
                if read(LIVE + remote) != (ROOT / local).read_bytes():
                    raise RuntimeError('Concurrent change: inspect before rollback')
            restore(args.rollback)
            print('ROLLED_BACK')
            return
        baseline = subprocess.check_output(['git', 'show', BASE + ':' + PLUGIN], cwd=ROOT)
        for local, remote in FILES.items():
            originals[local] = read(LIVE + remote)
        if originals[MU] is not None:
            raise RuntimeError('New MU target already exists')
        if originals[PLUGIN].replace(b'\r\n', b'\n') != baseline.replace(b'\r\n', b'\n'):
            raise RuntimeError('Live assistant differs from Git baseline')
        before = state()
        data = json.loads(wp('$rows=array();foreach(get_posts(array("post_type"=>"property","post_status"=>"publish","numberposts"=>-1)) as $p){'
                             '$d=harmat_sai_property_summary_data($p->ID);'
                             'if($d["hide_price"])$d["price"]=0;'
                             '$d["pdf"]=get_post_meta($p->ID,"property_floorplan",true);$rows[]=$d;}'
                             'echo wp_json_encode($rows);'))
        (OUT / 'public-source.json').write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding='utf-8')
        audit = dict(properties=len(data), hidden=sum(bool(d['hide_price']) for d in data),
                     missingPdf=sum(not d['pdf'] for d in data),
                     correctedAreas={d['title']: d['sales_area'] for d in data if d['title'].endswith('-4-L5')})
        if len(data) != 124 or any(abs(v - 47.83) > 0.001 for v in audit['correctedAreas'].values()):
            raise RuntimeError('Unexpected property count or area; inspect first')
        print('PREFLIGHT=' + json.dumps(dict(state=before, audit=audit)), flush=True)
        if not args.deploy:
            return
        run('mkdir -m 700 ' + q(backup))
        run('cp -p ' + q(LIVE + FILES[PLUGIN]) + ' ' + q(backup + '/assistant.php'))
        if read(backup + '/assistant.php') != originals[PLUGIN]:
            raise RuntimeError('Backup mismatch')
        print('BACKUP=' + backup, flush=True)
        for local, remote in FILES.items():
            target = LIVE + remote
            temp = target.rsplit('/', 1)[0] + '/.assistant-stage-' + stamp
            staged[local] = temp
            s.put(str(ROOT / local), temp)
            s.chmod(temp, 0o644)
            if read(temp) != (ROOT / local).read_bytes():
                raise RuntimeError('Staged upload mismatch')
            print(run('php -l ' + q(temp)), flush=True)
        for local, remote in FILES.items():
            if read(LIVE + remote) != originals[local]:
                raise RuntimeError('Concurrent live edit')
            s.posix_rename(staged[local], LIVE + remote)
            installed.append(local)
            print(run('php -l ' + q(LIVE + remote)), flush=True)
        purge()
        print(wp('$r=harmat_assistant_public_apartments(null);if(count($r)!==124)throw new Exception("Unexpected assistant data");echo "ASSISTANT_ROWS=".count($r);'), flush=True)
        for path in ('/', '/lakaskereso/', '/property/a3-4-l5/', '/galeria/', '/epitesi-naplo/',
                     '/virtualis-lakasvalaszto/', '/virtualis-lakasvalaszto-elso-utem/',
                     '/virtualis-lakasvalaszto-a1-epulet/', '/elerhetosegeink/'):
            html = run('curl --fail --compressed -sS --max-time 40 ' + q('https://harmat22.hu' + path))
            if any(x in html for x in ('Fatal error:', 'Parse error:', 'critical error on this website')):
                raise RuntimeError('Page failure: ' + path)
            if 'selection: selectionContext' not in html:
                raise RuntimeError('Old or missing assistant on ' + path)
            print('PAGE_OK=' + path, flush=True)
        after = state()
        if after['errorLog'] != before['errorLog'] or after['offers'] < before['offers']:
            raise RuntimeError('Server state changed; investigate')
        report = dict(backup=backup, before=before, after=after, audit=audit,
                      hashes={f: hashlib.sha256((ROOT / f).read_bytes()).hexdigest() for f in FILES})
        (OUT / 'deployment.json').write_text(json.dumps(report, indent=2))
        print('DEPLOYED=' + json.dumps(report), flush=True)
    except Exception:
        if PLUGIN in installed:
            restore(backup)
        elif MU in installed:
            s.remove(LIVE + FILES[MU])
            purge()
        raise
    finally:
        for path in staged.values():
            try:
                s.remove(path)
            except FileNotFoundError:
                pass
        s.close()
        c.close()


if __name__ == '__main__':
    main()
