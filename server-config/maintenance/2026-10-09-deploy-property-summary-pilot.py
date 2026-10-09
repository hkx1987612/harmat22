"""Guarded one-file release. Explicit --deploy, --verify or --rollback is required."""
import argparse
import hashlib
import json
import re
import shlex
import stat
import subprocess
import uuid
from datetime import datetime, timezone
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'outputs/2026-10-09-property-pilot'
LIVE = '/home/harmath2/public_html'
PRIVATE = '/home/harmath2/codex-backups'
PREFIX = PRIVATE + '/property-summary-pilot-'
LOCK = PRIVATE + '/.property-summary-pilot.lock'
PHP = '/opt/alt/php83/usr/bin/php'
SOURCE = 'wp-mu-plugins/zz-harmat-search-ai-discovery.php'
TARGET = LIVE + '/wp-content/mu-plugins/zz-harmat-search-ai-discovery.php'
BASE = 'b9fd4a2c0716e423f15e4510e4f569f456e2b7f8'
BEFORE = '35e39cb75888f657899939de72c5ba90b16c4eee5bf1d98bfcb68c3670765bb3'
AFTER = '7a794651698b9af76c911e83700697753f8cc3263c6d307891db315d0026ebfa'
TEST = 'server-config/maintenance/2026-10-09-test-property-summary-pilot.php'
TEST_SHA = '13c914d003b08766c1afa15aab9807b53fc633eb8ee8a9affb147e573003e59e'
ROUTES = ['/property/a1-2-l4/', '/property/a1-4-l1/', '/property/a1-4-l4/', '/property/a2-1-l5/']
Q = shlex.quote
sha = lambda data: hashlib.sha256(data).hexdigest()
lf = lambda data: data.replace(b'\r\n', b'\n')

parser = argparse.ArgumentParser(description=__doc__)
modes = parser.add_mutually_exclusive_group(required=True)
modes.add_argument('--deploy', action='store_true')
modes.add_argument('--verify', action='store_true')
modes.add_argument('--rollback')
args = parser.parse_args()
OUT.mkdir(parents=True, exist_ok=True)
client = paramiko.SSHClient()
client.load_host_keys(str(Path.home() / '.ssh/known_hosts'))
client.set_missing_host_key_policy(paramiko.RejectPolicy())
client.connect('185.111.89.244', username='harmath2', key_filename=str(Path.home() / 'Downloads/harmat'),
               passphrase=(Path.home() / '.ssh/harmat_key_pass.txt').read_text(encoding='utf-8').strip(),
               look_for_keys=False, allow_agent=False, timeout=20)
sftp = client.open_sftp()
token = uuid.uuid4().hex
events = []
stage = TARGET.rsplit('/', 1)[0] + '/.property-summary-' + token + '.tmp'
locked = False
activated = False
manifest = None

def note(event, value):
    events.append({'event': event, 'value': value})
    print(event + '=' + json.dumps(value, ensure_ascii=True), flush=True)
    (OUT / ('deployment-' + token + '.json')).write_text(json.dumps(events, indent=2), encoding='utf-8')

def read(path):
    info = sftp.lstat(path)
    if not stat.S_ISREG(info.st_mode):
        raise RuntimeError('Not a regular file: ' + path)
    with sftp.open(path, 'rb') as handle:
        return handle.read()

def write(path, data, mode):
    with sftp.open(path, 'wb') as handle:
        handle.write(data)
    sftp.chmod(path, mode)
    if read(path) != data or stat.S_IMODE(sftp.stat(path).st_mode) != mode:
        raise RuntimeError('Readback/mode failure: ' + path)

def run(command):
    stdin, stdout, stderr = client.exec_command(command, timeout=90)
    stdin.close()
    body = stdout.read().decode('utf-8', 'replace')
    error = stderr.read().decode('utf-8', 'replace')
    if stdout.channel.recv_exit_status():
        raise RuntimeError('Remote command failed: ' + error + body)
    return body.strip()

def wp(code):
    return run(PHP + ' /usr/local/bin/wp --path=' + Q(LIVE) + ' eval ' + Q(code))

def state():
    code = '''global $wpdb; $options=array();
foreach(array('harmat_legal_task_reminder_email_enabled','harmat_automatic_reminder_email_exclusions',
'harmat_sai_indexnow_queue','harmat_sai_indexnow_last_result') as $key) {
$row=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$key),ARRAY_A);
$options[$key]=$row?array('sha256'=>hash('sha256',$row['option_value']),'autoload'=>$row['autoload']):null;}
$facts=array();foreach(get_posts(array('post_type'=>'property','post_status'=>'publish','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC')) as $p) {
$facts[$p->ID]=array('title'=>$p->post_title);foreach(array('property_price','property_price_display','property_status',
'property_under_offer','property_building_area','property_land_area','_harmat_sales_area','_harmat_hide_front_price',
'property_rooms','property_bedrooms','property_address_street','property_address_street_number') as $key)
$facts[$p->ID][$key]=get_post_meta($p->ID,$key,true);}
echo wp_json_encode(array('propertyCount'=>count($facts),'publicFactsSHA256'=>hash('sha256',wp_json_encode($facts)),
'offerLeads'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s",'harmat_offer_lead')),
'options'=>$options));'''
    value = json.loads(wp(code))
    try:
        info = sftp.lstat(LIVE + '/error_log')
        value['errorLog'] = {'size': info.st_size, 'mtime': info.st_mtime, 'sha256': sha(read(LIVE + '/error_log'))}
    except FileNotFoundError:
        value['errorLog'] = None
    value['keywordSHA256'] = sha(lf(read(LIVE + '/wp-content/mu-plugins/zz-harmat-keyword-intent.php')))
    value['reminderSHA256'] = sha(lf(read(LIVE + '/wp-content/mu-plugins/zz-harmat-automatic-reminder-email-exclusions.php')))
    return value

def compare_state(before, after):
    for key in before:
        if key != 'offerLeads' and before[key] != after[key]:
            raise RuntimeError('Safety field changed: ' + key)
    if after['offerLeads'] < before['offerLeads']:
        raise RuntimeError('Offer lead count decreased')
    note('SAFETY_PRESERVED', {'beforeLeads': before['offerLeads'], 'afterLeads': after['offerLeads']})

def purge():
    note('CACHE', wp('if(function_exists("wp_cache_clear_cache"))wp_cache_clear_cache(); wp_cache_flush(); echo "PAGE_AND_OBJECT_CACHE_CLEARED";'))

def smoke(expected):
    routes = ['/', '/lakaskereso/', '/property/a1-1-l2/', '/virtualis-lakasvalaszto/',
              '/virtualis-lakasvalaszto-elso-utem/', '/virtualis-lakasvalaszto-a1-epulet/',
              '/epitesi-naplo/', '/elerhetosegeink/', '/finanszirozas/'] + ROUTES
    for path in routes:
        body = run("curl --fail --compressed --silent --show-error --max-time 45 -A Mozilla/5.0 " + Q('https://harmat22.hu' + path))
        if any(message in body.lower() for message in ('fatal error:', 'parse error:', 'critical error on this website')):
            raise RuntimeError('PHP error on ' + path)
        if expected == AFTER and path in ROUTES:
            match = re.search(r'<section class="harmat-search-summary"[^>]*>(.*?)</section>', body, re.S)
            if not match or 'A belső alapterület' not in match[1] or 'értékesítési terület' in match[1]:
                raise RuntimeError('Summary mismatch: ' + path)
            if match[1].count('<a href=') != 3:
                raise RuntimeError('Summary links mismatch: ' + path)
    if sha(lf(read(TARGET))) != expected:
        raise RuntimeError('Published source changed')
    note('HTTP_SMOKE_PASSED', len(routes))

def restore(value):
    backup = value['backup']
    if not re.fullmatch(re.escape(PREFIX) + r'[0-9a-f]{32}', backup):
        raise RuntimeError('Unexpected backup path')
    old = read(backup + '/before.php')
    if sha(old) != value['beforeSHA256'] or sha(lf(old)) != BEFORE:
        raise RuntimeError('Backup hash mismatch')
    if sha(lf(read(TARGET))) != AFTER:
        raise RuntimeError('Concurrent source edit blocks rollback')
    write(stage, old, value['mode'])
    run(PHP + ' -l ' + Q(stage))
    if sha(lf(read(TARGET))) != AFTER:
        raise RuntimeError('Concurrent source edit before restore')
    sftp.posix_rename(stage, TARGET)
    run(PHP + ' -l ' + Q(TARGET))
    if read(TARGET) != old:
        raise RuntimeError('Rollback bytes mismatch')
    purge()
    smoke(BEFORE)
    note('ROLLED_BACK', backup)

try:
    if args.verify:
        run(PHP + ' -l ' + Q(TARGET))
        smoke(AFTER)
        note('STATE', state())
    elif args.rollback:
        if not re.fullmatch(re.escape(PREFIX) + r'[0-9a-f]{32}', args.rollback):
            raise RuntimeError('Exact backup path required')
        manifest = json.loads(read(args.rollback + '/manifest.json'))
        if manifest['backup'] != args.rollback:
            raise RuntimeError('Manifest path mismatch')
        sftp.mkdir(LOCK, mode=0o700)
        locked = True
        restore(manifest)
    else:
        if subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT).decode().strip() != BASE:
            raise RuntimeError('Git baseline changed')
        candidate = lf((ROOT / SOURCE).read_bytes())
        test = lf((ROOT / TEST).read_bytes())
        if sha(candidate) != AFTER or sha(test) != TEST_SHA:
            raise RuntimeError('Reviewed candidate/test changed')
        old = read(TARGET)
        if sha(lf(old)) != BEFORE:
            raise RuntimeError('Live baseline changed')
        before = state()
        if before['propertyCount'] != 124:
            raise RuntimeError('Property baseline count changed')
        backup = PREFIX + token
        sftp.mkdir(LOCK, mode=0o700)
        locked = True
        sftp.mkdir(backup, mode=0o700)
        sftp.chmod(backup, 0o700)
        mode = stat.S_IMODE(sftp.stat(TARGET).st_mode)
        write(backup + '/before.php', old, 0o600)
        write(backup + '/after.php', candidate, 0o600)
        manifest = {'backup': backup, 'baseline': BASE, 'createdUTC': datetime.now(timezone.utc).isoformat(),
                    'target': TARGET, 'beforeSHA256': sha(old), 'afterSHA256': AFTER, 'mode': mode, 'before': before}
        write(backup + '/manifest.json', json.dumps(manifest, indent=2).encode(), 0o600)
        (OUT / 'release-manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
        note('HASH_VERIFIED_BACKUP', backup)
        for directory in ['/fixture', '/fixture/server-config', '/fixture/server-config/maintenance', '/fixture/wp-mu-plugins']:
            sftp.mkdir(backup + directory, mode=0o700)
        write(backup + '/fixture/' + SOURCE, candidate, 0o600)
        write(backup + '/fixture/' + TEST, test, 0o600)
        note('ISOLATED_PHP83_TEST', run(PHP + ' ' + Q(backup + '/fixture/' + TEST)))
        write(stage, candidate, mode)
        note('TEMPORARY_PHP83_LINT', run(PHP + ' -l ' + Q(stage)))
        if read(TARGET) != old or sha(lf((ROOT / SOURCE).read_bytes())) != AFTER:
            raise RuntimeError('Concurrent source edit before activation')
        compare_state(before, state())
        sftp.posix_rename(stage, TARGET)
        activated = True
        note('FINAL_PHP83_LINT', run(PHP + ' -l ' + Q(TARGET)))
        if read(TARGET) != candidate:
            raise RuntimeError('Installed bytes mismatch')
        purge()
        smoke(AFTER)
        after = state()
        compare_state(before, after)
        manifest['after'] = after
        write(backup + '/manifest.json', json.dumps(manifest, indent=2).encode(), 0o600)
        (OUT / 'release-manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
        note('RELEASE_ACTIVATED', AFTER)
except Exception:
    if activated and manifest:
        restore(manifest)
    raise
finally:
    try:
        sftp.lstat(stage)
        sftp.remove(stage)
    except FileNotFoundError:
        pass
    if locked:
        sftp.rmdir(LOCK)
    sftp.close()
    client.close()
