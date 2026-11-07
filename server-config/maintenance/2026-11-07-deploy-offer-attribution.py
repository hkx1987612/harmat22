"""Guarded metadata-only offer attribution release; offline unless a mode is given."""
import argparse
import json
from pathlib import Path
import re
import runpy
import shlex
import subprocess
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
B = runpy.run_path(str(HERE / '2026-10-29-deploy-search-budget.py'))
H, Q, N = B['H'], B['Q'], B['N']
require, sha = H.require, H.sha
BASE = '9608d9c5789e12ac20dfa6147d0a1c1c6e57b3b0'
SOURCE = 'wp-mu-plugins/harmat-unified-offer-modal.php'
TARGET = H.LIVE + '/wp-content/mu-plugins/harmat-unified-offer-modal.php'
PREFIX = H.BACKUPS + '/offer-attribution-'
LOCK = H.BACKUPS + '/.offer-attribution.lock'
OUT = ROOT / 'outputs/2026-11-07-offer-attribution'
ROUTES = B['ROUTES']
START = b'  function fillTracking() {'
END = b'\n  function open(trigger) {'


def encoded(value):
    return json.dumps(value, ensure_ascii=True, sort_keys=True, indent=2).encode()


def local():
    old = subprocess.check_output(['git', 'show', BASE + ':' + SOURCE], cwd=ROOT)
    new = B['norm']((ROOT / SOURCE).read_bytes())
    require(old.count(START) == new.count(START) == old.count(END) == new.count(END) == 1,
            'Unique attribution boundaries required')
    left = new[:new.index(START)].replace(b'Version: 1.0.9', b'Version: 1.0.8')
    require(left == old[:old.index(START)] and new[new.index(END):] == old[old.index(END):],
            'Only version and attribution helper may change')
    require(b'Version: 1.0.9' in new and b'harmatOfferAttributionV1' in new,
            'Reviewed attribution release missing')
    return old, new


def state(remote):
    # Only aggregate hashes leave the server. Exclude one derived render cache,
    # never apartment/customer/quote/appointment metadata or customer options.
    code = r'''global $wpdb; $result=array();
    if($wpdb->query('START TRANSACTION READ ONLY')===false)throw new RuntimeException('Read only required');
    try{
      foreach(array($wpdb->posts,$wpdb->postmeta) as $table){
        $h=hash_init('sha256');$count=0;$offset=0;$id=$table===$wpdb->posts?'ID':'meta_id';
        $where=$table===$wpdb->postmeta?" WHERE meta_key<>'_elementor_element_cache'":'';
        do{$rows=$wpdb->get_results("SELECT * FROM $table$where ORDER BY $id LIMIT $offset,1000",ARRAY_A);
          foreach($rows as $row){hash_update($h,wp_json_encode($row));$count++;}$offset+=1000;
        }while(count($rows)===1000);
        $result['tables'][$table]=array('count'=>$count,'sha256'=>hash_final($h));
      }
      $rows=$wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE 'harmat_%' ORDER BY option_name",ARRAY_A);
      foreach($rows as $row)$result['options'][$row['option_name']]=hash('sha256',$row['option_value']);
    }finally{$wpdb->query('ROLLBACK');}
    $folders=array(WP_CONTENT_DIR.'/mu-plugins');
    foreach(glob(WP_CONTENT_DIR.'/plugins/harmat*',GLOB_ONLYDIR) as $folder)$folders[]=$folder;
    $result['sources']=array();
    foreach($folders as $folder){
      foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder,FilesystemIterator::SKIP_DOTS)) as $f){
        if($f->isFile()&&!$f->isLink()&&in_array($f->getExtension(),array('php','js','css','json'),true)){
          $p=$f->getPathname();if($p!==OWNED)$result['sources'][$p]=hash_file('sha256',$p);
        }
      }
    }
    ksort($result['sources']);echo wp_json_encode($result);'''
    code = code.replace('OWNED', json.dumps(TARGET))
    return json.loads(remote.run(H.WP + ' --skip-plugins --skip-themes eval ' + shlex.quote(code)))


def pages():
    return B['pages']()


def proof(before, after, active):
    for route in ROUTES:
        a, b = N.D.Page(before[route].decode()), N.D.Page(after[route].decode())
        require(a.form == b.form and a.form and a.seo == b.seo and a.canonical == b.canonical,
                'Public form/SEO contract changed: ' + route)
        from lxml import html
        x, y = html.fromstring(before[route]), html.fromstring(after[route])
        for ident in ('masthead', 'colophon', 'h22-offer-modal', 'harmat-internal-language',
                      'hm-cookie-consent-script', 'harmat-confirmed-lead-tracking'):
            aa, bb = x.xpath('//*[@id=$id]', id=ident), y.xpath('//*[@id=$id]', id=ident)
            require(len(aa) == len(bb) and all(B['clean'](i) == B['clean'](j) for i, j in zip(aa, bb)),
                    'Unrelated shell/consent/tracking changed: ' + ident + ' ' + route)
        ident = 'content' if route.startswith('/property/') else 'main'
        aa, bb = x.xpath('//*[@id=$id]', id=ident), y.xpath('//*[@id=$id]', id=ident)
        require(len(aa) == len(bb) == 1 and B['clean'](aa[0]) == B['clean'](bb[0]),
                'Public content changed: ' + route)
        require((b'harmatOfferAttributionV1' in after[route]) == active, 'Stale attribution script: ' + route)
    return {'routes': len(ROUTES), 'form_seo_content_shell_unchanged': True,
            'submission': False, 'attribution_active': active}


def saved(remote, folder):
    require(re.fullmatch(re.escape(PREFIX) + '[a-f0-9]{32}', folder), 'Exact private backup path required')
    remote.directory(folder, private=True)
    manifest = json.loads(remote.read(folder + '/manifest.json', private=True))
    require(manifest['base'] == BASE and manifest['target'] == TARGET and manifest['backup'] == folder,
            'Backup identity mismatch')
    old = remote.read(folder + '/original.php', private=True)
    require(sha(old) == manifest['original_sha256'], 'Backup bytes changed')
    baseline = {route: remote.read(folder + '/page-' + str(i) + '.html', private=True)
                for i, route in enumerate(ROUTES)}
    require({k: sha(v) for k, v in baseline.items()} == manifest['pages'], 'Baseline pages changed')
    return manifest, old, baseline


def backup():
    old = subprocess.check_output(['git', 'show', BASE + ':' + SOURCE], cwd=ROOT)
    remote = H.Remote()
    locked = False
    try:
        remote.directory(H.BACKUPS)
        remote.mkdir(LOCK)
        locked = True
        raw = remote.read(TARGET)
        require(B['norm'](raw) == old, 'Live original is not the reviewed source')
        metadata = Q.metadata(remote, TARGET, raw)
        baseline = pages()
        before = state(remote)
        folder = PREFIX + uuid.uuid4().hex
        remote.mkdir(folder)
        remote.write_new(folder + '/original.php', raw)
        remote.lint(folder + '/original.php')
        for i, route in enumerate(ROUTES):
            remote.write_new(folder + '/page-' + str(i) + '.html', baseline[route])
        remote.write_new(folder + '/' + N.D.CHECKER, (HERE / N.D.CHECKER).read_bytes())
        native = N.D.validate_native(remote, folder)
        require(state(remote) == before, 'Protected data changed during backup; no data restore')
        manifest = {'base': BASE, 'target': TARGET, 'backup': folder, 'original_sha256': sha(raw),
                    'metadata': metadata, 'before': before, 'native': native,
                    'pages': {k: sha(v) for k, v in baseline.items()}}
        remote.write_new(folder + '/manifest.json', encoded(manifest))
        saved(remote, folder)
        OUT.mkdir(parents=True, exist_ok=True)
        (OUT / 'backup.json').write_bytes(encoded(manifest))
        print(json.dumps({'status': 'PRIVATE_SOURCE_BACKUP_VERIFIED', 'backup': folder,
                          'original_sha256': sha(raw), 'native': native}), flush=True)
    finally:
        if locked:
            remote.sftp.rmdir(LOCK)
        remote.close()


def operate(folder, deploy=False, rollback=False):
    old, new = local()
    remote = H.Remote()
    locked = False
    owned = False
    try:
        if deploy or rollback:
            remote.mkdir(LOCK)
            locked = True
        manifest, raw, baseline = saved(remote, folder)
        candidate = B['raw_patch'](raw, old, new)
        before = state(remote)
        if deploy:
            require(before == manifest['before'], 'Protected state changed since backup; take a fresh backup')
            require(remote.read(TARGET) == raw, 'Concurrent live edit; no overwrite')
            remote.write_new(folder + '/candidate.php', candidate)
            remote.lint(folder + '/candidate.php')
            remote.write_new(folder + '/candidate.sha256', (sha(candidate) + '\n').encode())
            owned = True
            Q.replace(remote, TARGET, raw, candidate, tuple(manifest['metadata']))
            remote.purge()
        elif rollback:
            require(remote.read(folder + '/candidate.php', private=True) == candidate,
                    'Recovery candidate differs')
            Q.replace(remote, TARGET, candidate, raw, tuple(manifest['metadata']))
            remote.purge()
        expected = raw if rollback else candidate
        require(remote.read(TARGET) == expected, 'Live target hash mismatch')
        remote.lint(TARGET)
        public = proof(baseline, pages(), not rollback)
        native = N.D.validate_native(remote, folder)
        require(state(remote) == before, 'Protected data/unrelated source drift; never restore business data')
        result = {'status': 'ROLLED_BACK' if rollback else ('RELEASED' if deploy else 'VERIFIED'),
                  'backup': folder, 'sha256': sha(expected), 'public': public, 'native': native,
                  'protected_data_and_unrelated_sources_unchanged': True}
        remote.write_new(folder + '/' + result['status'].lower() + '-' + uuid.uuid4().hex + '.json', encoded(result))
        OUT.mkdir(parents=True, exist_ok=True)
        (OUT / (result['status'].lower() + '.json')).write_bytes(encoded(result))
        print(json.dumps(result), flush=True)
    except Exception:
        if owned:
            actual = remote.read(TARGET)
            require(actual in (raw, candidate), 'Foreign edit retained; automatic recovery refused')
            if actual == candidate:
                Q.replace(remote, TARGET, candidate, raw, tuple(manifest['metadata']))
                remote.purge()
            print(json.dumps({'status': 'OWN_SOURCE_ROLLBACK', 'backup': folder}), flush=True)
        raise
    finally:
        if locked:
            remote.sftp.rmdir(LOCK)
        remote.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument('--backup', action='store_true')
    modes.add_argument('--deploy')
    modes.add_argument('--verify')
    modes.add_argument('--rollback')
    args = parser.parse_args()
    if args.backup:
        backup()
    elif args.deploy or args.verify or args.rollback:
        operate(args.deploy or args.verify or args.rollback, bool(args.deploy), bool(args.rollback))
    else:
        old, new = local()
        print(json.dumps({'status': 'OFFLINE_SCOPE_PASS', 'before': sha(old), 'after': sha(new)}))
