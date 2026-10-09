"""Private, guarded one-time data repair; never installs a frontend plugin."""
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
OUT = ROOT / 'outputs/2026-10-09-sales-area-align'
LIVE = '/home/harmath2/public_html'
PREFIX = '/home/harmath2/codex-backups/sales-area-align-'
PHP = '/opt/alt/php83/usr/bin/php'
WP = PHP + ' /usr/local/bin/wp --path=' + LIVE
BASE = 'eb3fe3ac483bd597767cedf06cf87cb0127bd32a'
REPAIR = 'server-config/maintenance/2026-10-09-correct-sales-area.php'
TEST = 'server-config/maintenance/2026-10-09-test-sales-area.php'
REPAIR_SHA = '02a62843b93cb742a9a0073a145ffda32e1003f36f1b18b0dff7862ef7e6a5fc'
TEST_SHA = '1860343e2b8f2003400a5ceaaf2a5f4eda68d557b337973653b3cc87c9f24d27'
EXPECTED = {'4349': ('55.82', '55.81'), '4388': ('67.04', '67.05'), '4418': ('59.49', '59.48')}
# Full metadata hashes proved these two scan-time changes were expiry-only.
RUNTIME_CACHE_TIMEOUT_BASE = {'4343': 1791585764, '5144': 1791585723}
SOURCES = {
    'wp-mu-plugins/zz-harmat-search-ai-discovery.php': 'wp-content/mu-plugins/zz-harmat-search-ai-discovery.php',
    'wp-mu-plugins/harmat-migrated-snippets.php': 'wp-content/mu-plugins/harmat-migrated-snippets.php',
    'wp-mu-plugins/harmat-unified-offer-modal.php': 'wp-content/mu-plugins/harmat-unified-offer-modal.php',
    'wp-mu-plugins/zz-harmat-public-offer-integrity.php': 'wp-content/mu-plugins/zz-harmat-public-offer-integrity.php',
    'wp-mu-plugins/zz-harmat-assistant-live-data.php': 'wp-content/mu-plugins/zz-harmat-assistant-live-data.php',
    'wp-mu-plugins/zz-harmat-four-unit-area-correction.php': 'wp-content/mu-plugins/zz-harmat-four-unit-area-correction.php',
    'wp-mu-plugins/zz-harmat-automatic-reminder-email-exclusions.php': 'wp-content/mu-plugins/zz-harmat-automatic-reminder-email-exclusions.php',
    'wp-mu-plugins/zz-harmat-public-seo-metadata.php': 'wp-content/mu-plugins/zz-harmat-public-seo-metadata.php',
    'wp-plugins/harmat-sales-manager/harmat-sales-manager.php': 'wp-content/plugins/harmat-sales-manager/harmat-sales-manager.php',
    'wp-plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php': 'wp-content/plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php',
}
Q = shlex.quote
sha = lambda b: hashlib.sha256(b).hexdigest()
lf = lambda b: b.replace(b'\r\n', b'\n')

parser = argparse.ArgumentParser(description=__doc__)
modes = parser.add_mutually_exclusive_group(required=True)
modes.add_argument('--deploy', action='store_true')
modes.add_argument('--verify', metavar='PRIVATE_BACKUP')
modes.add_argument('--rollback', metavar='PRIVATE_BACKUP')
args = parser.parse_args()
OUT.mkdir(parents=True, exist_ok=True)
client = paramiko.SSHClient()
client.load_host_keys(str(Path.home() / '.ssh/known_hosts'))
client.set_missing_host_key_policy(paramiko.RejectPolicy())
client.connect('185.111.89.244', username='harmath2', key_filename=str(Path.home() / 'Downloads/harmat'),
               passphrase=(Path.home() / '.ssh/harmat_key_pass.txt').read_text(encoding='utf-8').strip(),
               look_for_keys=False, allow_agent=False, timeout=30)
sftp = client.open_sftp()
token = uuid.uuid4().hex
events = []
lock = '/home/harmath2/codex-backups/.sales-area-align.lock'
locked = False
applied = False
backup = args.verify or args.rollback


def note(event, value):
    events.append({'event': event, 'value': value})
    display = value
    if event in ['DRY_RUN', 'IDEMPOTENCE']:
        report = json.loads(value.splitlines()[0])
        display = {key:report[key] for key in ['mode','state','changes']}
        display['verifiedProperties'] = len(report['property_hashes'])
    print(event + '=' + json.dumps(display, ensure_ascii=True), flush=True)
    (OUT / ('operation-' + token + '.json')).write_text(json.dumps(events, indent=2), encoding='utf-8')


def run(command):
    stdin, stdout, stderr = client.exec_command(command, timeout=120)
    stdin.close()
    body = stdout.read().decode('utf-8', 'replace')
    error = stderr.read().decode('utf-8', 'replace')
    if stdout.channel.recv_exit_status():
        raise RuntimeError('Remote command failed: ' + error + body)
    return body.strip()


def read(path):
    if not stat.S_ISREG(sftp.lstat(path).st_mode):
        raise RuntimeError('Not a regular file: ' + path)
    with sftp.open(path, 'rb') as handle:
        return handle.read()


def write_new(path, data):
    with sftp.open(path, 'wx') as handle:
        handle.write(data)
    sftp.chmod(path, 0o600)
    if read(path) != data or stat.S_IMODE(sftp.stat(path).st_mode) != 0o600:
        raise RuntimeError('Private file verification failed: ' + path)


def wp(code):
    return run(WP + ' eval ' + Q(code))


def state():
    code = '''global $wpdb; $options=array(); $facts=array();
foreach(array('harmat_legal_task_reminder_email_enabled','harmat_automatic_reminder_email_exclusions',
'harmat_sai_indexnow_queue','harmat_sai_indexnow_last_result','cron') as $key){
$r=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$key),ARRAY_A);
$options[$key]=$r?array('sha256'=>hash('sha256',$r['option_value']),'autoload'=>$r['autoload']):null;}
foreach(get_posts(array('post_type'=>'property','post_status'=>'publish','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC')) as $p){
$facts[$p->ID]=array('title'=>$p->post_title);foreach(array('property_price','property_price_display','property_status',
'property_under_offer','property_building_area','property_land_area','_harmat_sales_area','_harmat_hide_front_price',
'property_rooms','property_bedrooms','property_address_street','property_address_street_number','_harmat_sales_unit_price') as $k)
$facts[$p->ID][$k]=get_post_meta($p->ID,$k,true);}
$quotes=$wpdb->get_results($wpdb->prepare("SELECT meta_id,post_id,meta_value FROM {$wpdb->postmeta} WHERE meta_key=%s ORDER BY meta_id",'_harmat_offer_posted'),ARRAY_A);
$events=array();foreach(_get_cron_array() as $ts=>$hooks){foreach($hooks as $hook=>$entries){foreach($entries as $event){
$events[]=array('timestamp'=>$ts,'hook'=>$hook,'schedule'=>$event['schedule'],'interval'=>$event['interval']??null,
'argsSHA256'=>hash('sha256',wp_json_encode($event['args'])));}}}
echo wp_json_encode(array('facts'=>$facts,'propertyCount'=>count($facts),'options'=>$options,
'cronEvents'=>$events,
'offerLeads'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s",'harmat_offer_lead')),
'savedQuoteRows'=>count($quotes),'savedQuoteHash'=>hash('sha256',wp_json_encode($quotes))));'''
    value = json.loads(wp(code))
    value['sources'] = {source: sha(lf(read(LIVE + '/' + target))) for source, target in SOURCES.items()}
    try:
        info = sftp.stat(LIVE + '/error_log')
        value['errorLog'] = {'size': info.st_size, 'mtime': info.st_mtime, 'sha256': sha(read(LIVE + '/error_log'))}
    except FileNotFoundError:
        value['errorLog'] = None
    return value


def compare(before, after, reverse=False, allow_housekeeping=False):
    expected = json.loads(json.dumps(before['facts']))
    for post_id, (old, new) in EXPECTED.items():
        start, end = (new, old) if reverse else (old, new)
        if expected[post_id]['_harmat_sales_area'] != start:
            raise RuntimeError('Unexpected before area: ' + post_id)
        expected[post_id]['_harmat_sales_area'] = end
    if after['facts'] != expected:
        raise RuntimeError('Unexpected published property data delta')
    for key in ['propertyCount', 'sources', 'errorLog', 'savedQuoteRows', 'savedQuoteHash']:
        if before[key] != after[key]:
            raise RuntimeError('Safety delta: ' + key)
    for key in before['options']:
        if key != 'cron' and before['options'][key] != after['options'][key]:
            raise RuntimeError('Protected option changed: ' + key)
    if before['options']['cron']['autoload'] != after['options']['cron']['autoload']:
        raise RuntimeError('Cron autoload policy changed')
    # Read-only WordPress boots also refresh Action Scheduler's minute runner.
    # Preserve every event identity/schedule/argument and all other timestamps.
    def canonical_events(events):
        return sorted(events, key=lambda e:(e['hook'], e['argsSHA256'], e['timestamp']))
    original_events = canonical_events(before['cronEvents'])
    current_events = canonical_events(after['cronEvents'])
    if len(original_events) != len(current_events):
        raise RuntimeError('Cron event count changed')
    runner_refresh = []
    for old_event, new_event in zip(original_events, current_events):
        expected_event = dict(old_event)
        if old_event['hook'] == 'action_scheduler_run_queue':
            delta = int(new_event['timestamp']) - int(old_event['timestamp'])
            if not 0 <= delta <= 7200:
                raise RuntimeError('Unexpected minute-runner timestamp movement')
            expected_event['timestamp'] = new_event['timestamp']
            if delta:
                runner_refresh.append({'before':old_event['timestamp'],'after':new_event['timestamp']})
        elif allow_housekeeping and old_event['hook'] == 'wp_cache_gc' and old_event['schedule'] is False and old_event['interval'] is None:
            delta = int(new_event['timestamp']) - int(old_event['timestamp'])
            if not 0 <= delta <= 7200:
                raise RuntimeError('Unexpected cache-GC timer movement')
            expected_event['timestamp'] = new_event['timestamp']
            if delta:
                note('NORMAL_CACHE_GC_TIMER', {'before':old_event['timestamp'],'after':new_event['timestamp']})
        elif (allow_housekeeping and not re.search(r'harmat|mail|reminder|indexnow', old_event['hook'], re.I)
              and old_event['schedule'] and isinstance(old_event['interval'], int) and old_event['interval'] > 0):
            delta = int(new_event['timestamp']) - int(old_event['timestamp'])
            if not 0 <= delta <= 7200 or delta % old_event['interval']:
                raise RuntimeError('Unexpected housekeeping cadence: ' + old_event['hook'])
            expected_event['timestamp'] = new_event['timestamp']
            if delta:
                note('NORMAL_HOUSEKEEPING_CADENCE', {'hook':old_event['hook'],'before':old_event['timestamp'],'after':new_event['timestamp']})
        if new_event != expected_event:
            raise RuntimeError('Cron event changed outside the minute-runner timestamp: ' + old_event['hook'])
    if runner_refresh:
        note('NORMAL_MINUTE_RUNNER_REFRESH', runner_refresh)
    if after['offerLeads'] < before['offerLeads']:
        raise RuntimeError('Offer-lead count decreased')
    for post_id in ['4419','5212','5332','5490']:
        if after['facts'][post_id]['_harmat_sales_area'] != '47.83':
            raise RuntimeError('Protected four-unit correction changed')
    note('EXACT_DATA_AND_SAFETY_PASSED', {'properties':124,'changedAreas':3,'beforeLeads':before['offerLeads'],'afterLeads':after['offerLeads']})


def functional(expected_new=True):
    value = json.loads(wp('''global $harmat_sales_manager; $rows=array();
$items=$harmat_sales_manager->frontend_sales_data(array(4349,4388,4418));
$assistant=harmat_assistant_public_apartments(array());
foreach(array(4349,4388,4418) as $id){$a=array_values(array_filter($assistant,static function($r)use($id){return $r['apartment']===get_the_title($id);}));
$rows[$id]=array('sales'=>$items[$id],'seo'=>harmat_sai_property_summary_data($id),'assistant'=>$a[0]);}echo wp_json_encode($rows);'''))
    for post_id, values in value.items():
        area = float(EXPECTED[post_id][1 if expected_new else 0])
        if any(float(current) != area for current in [values['sales']['salesArea'], values['seo']['sales_area'], values['assistant']['sales_area_m2']]):
            raise RuntimeError('Shared source mismatch: ' + post_id)
        if int(values['sales']['price']) != int(values['seo']['price']) or int(values['sales']['price']) != int(values['assistant']['price_huf']):
            raise RuntimeError('Shared total price mismatch: ' + post_id)
    note('SALES_SEO_ASSISTANT_PASSED', {key: row['sales']['salesArea'] for key, row in value.items()})
    paths = ['/', '/lakaskereso/', '/property/a1-1-l2/', '/virtualis-lakasvalaszto/',
             '/virtualis-lakasvalaszto-elso-utem/', '/virtualis-lakasvalaszto-a1-epulet/',
             '/epitesi-naplo/', '/elerhetosegeink/', '/finanszirozas/']
    paths += ['/property/' + value[key]['sales']['title'].lower() + '/' for key in EXPECTED]
    for path in paths:
        body = run('curl --fail --silent --show-error --compressed --max-time 45 -A Mozilla/5.0 ' + Q('https://harmat22.hu' + path))
        if any(error in body.lower() for error in ['fatal error:', 'parse error:', 'critical error on this website']):
            raise RuntimeError('PHP error: ' + path)
    note('HTTP_SMOKE_PASSED', len(paths))


def validate_backup(check_scope=True):
    if not backup or not re.fullmatch(re.escape(PREFIX) + '[0-9a-f]{32}', backup):
        raise RuntimeError('Exact private backup path required')
    if not stat.S_ISDIR(sftp.lstat(backup).st_mode) or stat.S_IMODE(sftp.stat(backup).st_mode) != 0o700:
        raise RuntimeError('Private backup directory invalid')
    manifest = json.loads(read(backup + '/release.json'))
    if (manifest['backup'] != backup or manifest['baseline'] != BASE
            or manifest['repairSHA256'] != REPAIR_SHA or manifest['testSHA256'] != TEST_SHA
            or sha(read(backup + '/repair.php')) != manifest['repairSHA256']):
        raise RuntimeError('Backup script hash mismatch')
    for filename, key in [('test.php','testSHA256'),('fixture.json','fixtureSHA256'),('2026-10-09-correct-sales-area.php','repairSHA256')]:
        if sha(read(backup + '/' + filename)) != manifest[key]:
            raise RuntimeError('Recovery material changed: ' + filename)
    for filename in ['repair.php','test.php','fixture.json','2026-10-09-correct-sales-area.php','release.json','before.json','before.sha256']:
        if stat.S_IMODE(sftp.lstat(backup + '/' + filename).st_mode) != 0o600:
            raise RuntimeError('Private file mode changed: ' + filename)
    if sha(read(backup + '/before.json')) != read(backup + '/before.sha256').decode().strip():
        raise RuntimeError('Data backup digest mismatch')
    code = 'define("HARMAT_SALES_AREA_TESTS_ONLY",true); require ' + json.dumps(backup + '/repair.php') + '; global $wpdb;'
    code += '$m=harmat_area_read_backup(' + json.dumps(backup) + ');'
    if not check_scope:
        note('DATA_BACKUP_AUDIT', wp(code + 'echo "DATA_BACKUP_VERIFIED";'))
        return manifest
    code += ' $c=harmat_area_snapshot($wpdb); $p=harmat_area_plan($m["before"]);'
    code += '$cache_timeouts=json_decode(' + json.dumps(json.dumps(RUNTIME_CACHE_TIMEOUT_BASE)) + ',true);'
    code += '''$hashes=harmat_area_hash_map($c);$refreshes=array();
foreach($cache_timeouts as $id=>$original_timeout){
if($hashes[$id]['meta']===$m['after_hashes'][$id]['meta'])continue;
$rows=$c['meta'][$id];$i=harmat_area_meta_index($rows,'_elementor_element_cache');$raw=$rows[$i]['meta_value'];
$cache=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
harmat_area_require(is_array($cache)&&array_keys($cache)===array('timeout','value')&&is_int($cache['timeout'])
&&$cache['timeout']>=$original_timeout,'Unexpected cache structure or backwards expiry.');
$token='"timeout":'.$cache['timeout'];harmat_area_require(substr_count($raw,$token)===1,'Cache expiry token is not unique.');
$rows[$i]['meta_value']=str_replace($token,'"timeout":'.$original_timeout,$raw);
$hashes[$id]['meta']=harmat_area_hash($rows);
harmat_area_require($hashes[$id]['meta']===$m['after_hashes'][$id]['meta'],'Metadata changed beyond proved cache expiry.');
$refreshes[]=array('id'=>$id,'originalTimeout'=>$original_timeout,'currentTimeout'=>$cache['timeout']);}
foreach(harmat_area_targets() as $id=>$target){
harmat_area_require($c['posts'][$id]===$p['after']['posts'][$id],'Target post scope changed after purge.');
$actual=array_values(array_filter($c['meta'][$id],static function($r){return $r['meta_key']!=='_elementor_element_cache';}));
$expected=array_values(array_filter($p['after']['meta'][$id],static function($r){return $r['meta_key']!=='_elementor_element_cache';}));
harmat_area_require($actual===$expected,'Target metadata scope changed after purge.');
unset($hashes[$id]);}
$expected=$m['after_hashes'];foreach(harmat_area_targets() as $id=>$target)unset($expected[$id]);
harmat_area_require($hashes===$expected,'Other 121 property rows/meta changed.');
echo wp_json_encode(array('result'=>'FULL_124_SCOPE_AND_BACKUP_VERIFIED','cacheExpiryOnly'=>$refreshes));'''
    note('INDEPENDENT_SCOPE_BACKUP_AUDIT', wp(code))
    return manifest


try:
    if args.deploy:
        if subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT).decode().strip() != BASE:
            raise RuntimeError('Reviewed Git baseline changed')
        before = state()
        if before['propertyCount'] != 124:
            raise RuntimeError('Published inventory count changed')
        for source, expected_hash in before['sources'].items():
            if sha(lf((ROOT / source).read_bytes())) != expected_hash:
                raise RuntimeError('Live/local source drift: ' + source)
        for post_id, values in EXPECTED.items():
            if before['facts'][post_id]['_harmat_sales_area'] != values[0]:
                raise RuntimeError('Area baseline changed: ' + post_id)
        sftp.mkdir(lock, 0o700)
        locked = True
        backup = PREFIX + token
        sftp.mkdir(backup, 0o700)
        sftp.chmod(backup, 0o700)
        repair, test = lf((ROOT / REPAIR).read_bytes()), lf((ROOT / TEST).read_bytes())
        if sha(repair) != REPAIR_SHA or sha(test) != TEST_SHA:
            raise RuntimeError('Reviewed repair/test source changed')
        fixture = (OUT / 'source-details-before.json').read_bytes()
        manifest = {'backup':backup,'baseline':BASE,'createdUTC':datetime.now(timezone.utc).isoformat(),
                    'repairSHA256':sha(repair),'testSHA256':sha(test),'fixtureSHA256':sha(fixture),'before':before}
        write_new(backup + '/release.json', json.dumps(manifest, indent=2).encode())
        stage = backup + '/.repair.tmp'
        write_new(stage, repair)
        note('STAGED_PHP_LINT', run(PHP + ' -l ' + Q(stage)))
        sftp.posix_rename(stage, backup + '/repair.php')
        note('FINAL_PHP_LINT', run(PHP + ' -l ' + Q(backup + '/repair.php')))
        # The isolated tests resolve the repair source from their own directory.
        write_new(backup + '/2026-10-09-correct-sales-area.php', repair)
        write_new(backup + '/test.php', test)
        write_new(backup + '/fixture.json', fixture)
        note('SERVER_TESTS', run(PHP + ' ' + Q(backup + '/test.php') + ' ' + Q(backup + '/fixture.json')))
        note('DRY_RUN', run(WP + ' eval-file ' + Q(backup + '/repair.php')))
        note('PRIVATE_RELEASE_BACKUP', backup)
        # Also permits recovery if the repair commits but its cache purge fails.
        applied = True
        note('APPLY', run('HARMAT_APPLY=1 HARMAT_BACKUP_DIR=' + Q(backup) + ' ' + WP + ' eval-file ' + Q(backup + '/repair.php')))
        compare(before, state())
        validate_backup()
        functional()
        note('IDEMPOTENCE', run(WP + ' eval-file ' + Q(backup + '/repair.php')))
        (OUT / 'release-manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
    elif args.verify:
        manifest = validate_backup()
        note('FINAL_PHP_LINT', run(PHP + ' -l ' + Q(backup + '/repair.php')))
        note('IDEMPOTENCE', run(WP + ' eval-file ' + Q(backup + '/repair.php')))
        compare(manifest['before'], state(), allow_housekeeping=True)
        functional()
        note('VERIFIED', backup)
    else:
        manifest = validate_backup(False)
        before = state()
        sftp.mkdir(lock, 0o700)
        locked = True
        note('PHP_LINT', run(PHP + ' -l ' + Q(backup + '/repair.php')))
        note('ROLLBACK', run('HARMAT_ROLLBACK=1 HARMAT_BACKUP_DIR=' + Q(backup) + ' ' + WP + ' eval-file ' + Q(backup + '/repair.php')))
        compare(before, state(), reverse=True)
        functional(False)
except Exception:
    # Guarded recovery changes only our target fields; a later editor blocks it.
    if applied:
        try:
            note('GUARDED_RECOVERY', run('HARMAT_ROLLBACK=1 HARMAT_BACKUP_DIR=' + Q(backup) + ' ' + WP + ' eval-file ' + Q(backup + '/repair.php')))
            functional(False)
        except Exception as recovery_error:
            note('RECOVERY_REQUIRED', {'backup':backup,'error':str(recovery_error)})
    raise
finally:
    if locked:
        sftp.rmdir(lock)
        note('LOCK_REMOVED', True)
    sftp.close()
    client.close()
