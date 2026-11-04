"""Guarded presentation-only CRM release. Private backup, native form parity, source-only recovery."""
import argparse
import json
from pathlib import Path
import runpy
import shlex
import subprocess
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
B = runpy.run_path(str(HERE / '2026-10-29-deploy-search-budget.py'))
H, Q = B['H'], B['Q']
require, sha = H.require, H.sha
BASE = '7877476'
OUT = ROOT / 'outputs/2026-11-04-crm-review'
PREFIX = H.BACKUPS + '/crm-ui-review-'
PATHS = {
    'crm': ('wp-plugins/harmat-sales-manager/harmat-sales-manager.php', H.LIVE + '/wp-content/plugins/harmat-sales-manager/harmat-sales-manager.php'),
    'bandwidth': ('wp-mu-plugins/zz-harmat-crm-bandwidth-widget.php', H.LIVE + '/wp-content/mu-plugins/zz-harmat-crm-bandwidth-widget.php'),
}
LOCK = H.BACKUPS + '/.crm-ui-review.lock'


def encoded(value):
    return json.dumps(value, ensure_ascii=True, sort_keys=True, indent=2).encode()


def state(r):
    code = '''global $wpdb; $r=array();
    foreach(array('harmat_broker_leads_v1','harmat_sales_deals_v1','harmat_sales_trash_v1') as $key){
      $value=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$key));
      $r['options'][$key]=hash('sha256',(string)$value);
    }
    foreach(array($wpdb->posts,$wpdb->postmeta) as $table){
      $h=hash_init('sha256');$count=0;$offset=0;$id=$table===$wpdb->posts?'ID':'meta_id';
      do{$rows=$wpdb->get_results("SELECT * FROM $table ORDER BY $id LIMIT $offset,1000",ARRAY_A);
        foreach($rows as $row){hash_update($h,wp_json_encode($row));$count++;}$offset+=1000;
      }while(count($rows)===1000);
      $r['tables'][$table]=array('count'=>$count,'sha256'=>hash_final($h));
    }
    $r['sources']=array();
    foreach(array(WP_CONTENT_DIR.'/mu-plugins',WP_CONTENT_DIR.'/plugins/harmat-sales-manager',WP_CONTENT_DIR.'/plugins/harmat-legal-documents') as $folder){
      foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder,FilesystemIterator::SKIP_DOTS)) as $f){
        if($f->isFile()&&!$f->isLink()&&in_array($f->getExtension(),array('php','js','css','json'),true)){
          $p=$f->getPathname();if(!in_array($p,OWNED,true))$r['sources'][$p]=hash_file('sha256',$p);
        }
      }
    }ksort($r['sources']);echo wp_json_encode($r);'''
    code = code.replace('OWNED', 'json_decode(' + json.dumps(json.dumps([v[1] for v in PATHS.values()])) + ',true)')
    return json.loads(r.run(H.WP + ' eval ' + shlex.quote(code)))


def public_contract(pages):
    result = {}
    for route, blob in pages.items():
        page = B['N'].D.Page(blob.decode('utf-8'))
        require(page.form, 'Missing public offer form: ' + route)
        result[route] = {'form': page.form, 'seo': page.seo, 'canonical': page.canonical}
    return json.loads(encoded(result))


def native(r, folder):
    helper = folder + '/native-check.php'
    r.write_new(helper, (HERE / '2026-11-04-native-crm-check.php').read_bytes())
    r.lint(helper)
    staged = r.read(folder + '/crm-new.php', private=True)
    require(staged.count(b'final class Harmat_Sales_Manager {') == 1, 'Class identity')
    staged = staged.replace(b'final class Harmat_Sales_Manager {', b'final class Harmat_Sales_Manager_Crm_Review {')
    tail = b"$GLOBALS['harmat_sales_manager'] = new Harmat_Sales_Manager();"
    require(staged.count(tail) == 1, 'Constructor boundary')
    staged = staged.replace(tail, b'// Native test class: no constructor, hooks, or live writes.')
    r.write_new(folder + '/candidate-class.php', staged)
    r.lint(folder + '/candidate-class.php')
    report = json.loads(r.run(H.WP + ' eval-file ' + shlex.quote(helper) + ' ' + shlex.quote(folder + '/candidate-class.php')))
    require(report['status'] == 'PASS', 'Native parity failed')
    return report


def preflight():
    for filename in ('2026-11-04-test-crm-source-scope.php', '2026-11-04-test-crm-simplification.php',
                     '2026-10-14-test-appointment-integrity.php', '2026-10-15-test-offer-antispam.php'):
        command = ['php.cmd', str(HERE / filename)]
        if filename == '2026-11-04-test-crm-source-scope.php':
            command.append(BASE)
        subprocess.run(command, check=True, cwd=ROOT)
    backup = json.loads((OUT / 'backup.json').read_text())
    require(backup['status'] == 'BACKUP_VERIFIED' and backup['transactional_snapshot'], 'Full data backup required')
    r = H.Remote(); locked = False
    try:
        r.mkdir(LOCK); locked = True
        r.directory(backup['backup'], private=True)
        for name, info in backup['files'].items():
            require(r.run('sha256sum ' + shlex.quote(backup['backup'] + '/' + name)).split()[0] == info['sha256'], 'Full backup checksum mismatch')
        folder = PREFIX + uuid.uuid4().hex; r.mkdir(folder)
        before = state(r); public_before = public_contract(B['pages']())
        pins, meta = {}, {}
        for key, (relative, target) in PATHS.items():
            old = subprocess.check_output(['git', 'show', BASE + ':' + relative], cwd=ROOT)
            new = (ROOT / relative).read_bytes().replace(b'\r\n', b'\n')
            raw = r.read(target); candidate = B['raw_patch'](raw, old, new)
            meta[key] = Q.metadata(r, target, raw)
            for label, blob in (('old', raw), ('new', candidate)):
                r.write_new(folder + '/' + key + '-' + label + '.php', blob)
                r.lint(folder + '/' + key + '-' + label + '.php')
            pins[key] = {'old': sha(raw), 'new': sha(candidate)}
        report = native(r, folder)
        require(state(r) == before, 'Concurrent protected-state change; preflight stopped, never restore data')
        saved = {'backup': folder, 'full_data_backup': backup['backup'], 'base': BASE,
                 'pins': pins, 'meta': meta, 'before': before, 'public': public_before, 'native': report}
        r.write_new(folder + '/manifest.json', encoded(saved))
        (OUT / 'preflight.json').write_bytes(encoded(saved))
        print(json.dumps({'status': 'PREFLIGHT_READY', 'backup': folder, 'native': report}), flush=True)
    finally:
        if locked: r.sftp.rmdir(LOCK)
        r.close()


def deploy():
    saved = json.loads((OUT / 'preflight.json').read_text())
    folder = saved['backup']; require(folder.startswith(PREFIX), 'Private preflight path required')
    r = H.Remote(); locked = False; changed = []
    try:
        r.mkdir(LOCK); locked = True
        require(json.loads(r.read(folder + '/manifest.json', private=True)) == saved, 'Manifest mismatch')
        blobs = {}
        for key, (relative, target) in PATHS.items():
            pair = [r.read(folder + '/' + key + '-' + tag + '.php', private=True) for tag in ('old', 'new')]
            require([sha(b) for b in pair] == [saved['pins'][key]['old'], saved['pins'][key]['new']], 'Pinned bytes changed')
            require(pair[1].replace(b'\r\n', b'\n') == (ROOT / relative).read_bytes().replace(b'\r\n', b'\n'), 'Local candidate changed')
            require(r.read(target) == pair[0], 'Live source drift')
            blobs[key] = pair
        require(state(r) == saved['before'], 'Business/source changed since preflight; do not deploy stale baseline')
        try:
            for key, (_, target) in PATHS.items():
                # Track before replace so an after-rename validation error is recoverable.
                changed.append(key)
                Q.replace(r, target, *blobs[key], tuple(saved['meta'][key]))
            r.run(H.WP + ' cache flush')
            require(public_contract(B['pages']()) == saved['public'], 'Public form/SEO contract changed')
            require(state(r) == saved['before'], 'Protected data/source changed during release')
            result = {'status': 'DEPLOYED_VERIFIED', 'backup': folder, 'full_data_backup': saved['full_data_backup'],
                      'public_routes': len(saved['public']), 'public_form_seo_unchanged': True,
                      'protected_data_source_unchanged': True, 'source_sha256': {k: sha(r.read(v[1])) for k, v in PATHS.items()},
                      'submissions': 0}
            (OUT / 'deployment.json').write_bytes(encoded(result))
            print(json.dumps(result), flush=True)
        except Exception:
            for key in reversed(changed):
                target = PATHS[key][1]; old, new = blobs[key]; current = r.read(target)
                if current == new: Q.replace(r, target, new, old, tuple(saved['meta'][key]))
                else: require(current == old, 'Foreign code drift: manual recovery required')
            r.run(H.WP + ' cache flush')
            print('SOURCE_ONLY_ROLLBACK; no database restore', flush=True)
            raise
    finally:
        if locked: r.sftp.rmdir(LOCK)
        r.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--preflight', action='store_true')
    group.add_argument('--deploy', action='store_true')
    args = parser.parse_args()
    if args.preflight: preflight()
    elif args.deploy: deploy()
    else: print('Offline by default. Use --preflight, review desktop QA, then --deploy.')
