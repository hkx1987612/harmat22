"""Deploy the manager-only CRM attribution report, without writing CRM records."""
import argparse
import json
from pathlib import Path
import re
import runpy
import shlex
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
B = runpy.run_path(str(HERE / '2026-10-29-deploy-search-budget.py'))
H, Q, N = B['H'], B['Q'], B['N']
require, sha, norm = B['require'], B['sha'], B['norm']
CRM = 'wp-plugins/harmat-sales-manager/'
CONTROLLER = CRM + 'harmat-sales-manager.php'
REPORT = CRM + 'attribution-report.php'
LIVE = H.LIVE + '/wp-content/plugins/harmat-sales-manager/'
TARGET = LIVE + 'harmat-sales-manager.php'
HELPER = LIVE + 'attribution-report.php'
PREFIX = H.BACKUPS + '/crm-attribution-20261123-'
LOCK = H.BACKUPS + '/.crm-attribution-20261123.lock'
EXPECTED = {'youtube': {'records': 0, 'qualified': 0}, 'search': {'records': 3, 'qualified': 0},
            'facebook': {'records': 1, 'qualified': 0}, 'unknown': {'records': 12, 'qualified': 0}}


def candidates():
    old = subprocess.check_output(['git', 'show', 'a05042a:' + CONTROLLER], cwd=ROOT)
    new = norm((ROOT / CONTROLLER).read_bytes())
    code = norm((ROOT / REPORT).read_bytes())
    diff = subprocess.run(['git','diff','--numstat','a05042a','--',CONTROLLER], cwd=ROOT, check=True, capture_output=True, text=True).stdout.split()
    require(diff[:2] == ['10', '0'], 'Controller must only add the guarded route and link')
    require(b"$this->is_sales_manager_user() && is_readable($attribution_file)" in new and
            b"($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'" in new and new.count(b'Harmat_CRM_Attribution_Report::render(') == 1,
            'Manager-only GET contract missing')
    require(not re.search(rb'\b(?:update_option|wp_insert_post|wpdb->(?:insert|update|delete|query)|wp_remote_)\b', code), 'Unexpected mutation/network helper')
    return old, new, code


def real_preflight(remote, code):
    php = code.decode().removeprefix('<?php') + r'''
global $wpdb;
if ($wpdb->query('START TRANSACTION READ ONLY')===false) throw new RuntimeException('Read-only transaction required');
try {
    $c='Harmat_CRM_Attribution_Report';
    $path=dirname(rtrim(ABSPATH,'/')).'/logs';
    $report=$c::load($c::date('2026-11-09'),$c::date('2026-11-22'),$path);
    $html_counts=array();
    foreach(array('zh','hu') as $lang) {
        ob_start();
        $c::render($lang,array('attribution_from'=>'2026-11-09','attribution_to'=>'2026-11-22','attribution_view'=>'qualified'),home_url('/sales/'),$path);
        $html=ob_get_clean();
        foreach(array('raw','effective','unknown') as $key) {
            if(!preg_match('/id="ha-count-'.$key.'">(\d+)<\/strong>/',$html,$m)) throw new RuntimeException('Report did not render stats');
            $html_counts[$lang][$key]=(int)$m[1];
        }
    }
    echo json_encode(array('counts'=>$report['counts'],'types'=>array_count_values(array_column($report['rows'],'kind')),
        'reasons'=>array_count_values(array_column($report['rows'],'reason')),'row_states'=>array_count_values(array_column($report['rows'],'status')),
        'rendered'=>$html_counts,'read_only'=>true),JSON_THROW_ON_ERROR);
} finally {$wpdb->query('ROLLBACK');}
'''
    report = json.loads(remote.run(H.WP + ' eval ' + shlex.quote(php) + ' --skip-plugins --skip-themes'))
    require(report['counts'] == EXPECTED and report['types'] == {'website': 10, 'registered': 6}, 'Candidate and independent real CRM reconciliation differ')
    require(report['rendered'] == {lang: {'raw':16,'effective':0,'unknown':12} for lang in ('zh','hu')}, 'Rendered totals differ')
    return report


def public_snapshot():
    pages = {}
    from lxml import html
    for route in B['ROUTES']:
        blob, mime = Q.get('https://harmat22.hu' + route)
        require('text/html' in mime and not re.search(rb'Fatal error:|Parse error:', blob), 'Public page failed ' + route)
        raw = blob.decode()
        page = N.D.Page(raw)
        tree = html.fromstring(raw)
        forms = tree.xpath('//form')
        # The native offer is compared by existing verifier; booking ownership and controls are compared without volatile nonces.
        form_contract = [[(e.tag, e.get('name'), e.get('type'), e.get('required') is not None)
                          for e in f.xpath('.//input|.//select|.//textarea|.//button')] for f in forms]
        pages[route] = (page.form, page.seo, page.canonical, form_contract)
    return pages


def deploy(remote, backup, old, new, code, rollback=False):
    require(bool(re.fullmatch(re.escape(PREFIX) + '[a-f0-9]{32}', backup)), 'Invalid previous backup path')
    remote.directory(backup, private=True)
    original = remote.read(backup + '/controller-before.php', private=True)
    updated = B['raw_patch'](original, old, new)
    before, after = (updated, original) if rollback else (original, updated)
    remote.mkdir(LOCK)
    touched = False
    try:
        require(remote.read(TARGET) == before, 'CRM changed since the verified backup; stopped')
        metadata = Q.metadata(remote, TARGET, before)
        baseline = public_snapshot()
        if not rollback:
            result = real_preflight(remote, code)
            print(json.dumps({'status':'REAL_CRM_PREFLIGHT_PASS', **result}), flush=True)
            candidate = backup + '/controller-after.php'
            if N.absent(remote, candidate):
                remote.write_new(candidate, updated)
            require(remote.read(candidate, private=True) == updated, 'Unexpected release candidate')
            remote.lint(candidate)
            if N.absent(remote, HELPER):
                remote.write_new(HELPER, code)
                remote.sftp.chmod(HELPER, 0o644)
            require(remote.read(HELPER) == code, 'Helper staged content differs')
            remote.lint(HELPER)
        touched = True
        Q.replace(remote, TARGET, before, after, metadata)
        remote.purge()
        require(remote.read(TARGET) == after, 'Controller readback failed')
        require(public_snapshot() == baseline, 'Public form or SEO contract changed')
        body, _ = Q.get('https://harmat22.hu/sales/?view=inquiries&report=attribution')
        require(b'ha-count-raw' not in body and b'ha-detail-rows' not in body, 'Unauthenticated view exposes report')
        print(json.dumps({'status':'RESTORED' if rollback else 'RELEASED', 'backup':backup,
                          'public_routes_unchanged':len(baseline), 'anonymous_report_denied':True}), flush=True)
    except BaseException:
        if touched:
            current = remote.read(TARGET)
            if current == after:
                Q.replace(remote, TARGET, after, before, metadata)
                remote.purge()
                print(json.dumps({'status':'AUTOMATIC_SOURCE_RESTORE','backup':backup}), flush=True)
            else:
                require(current == before, 'Foreign controller change preserved')
        raise
    finally:
        remote.sftp.rmdir(LOCK)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    action = parser.add_mutually_exclusive_group(required=True)
    action.add_argument('--preflight', action='store_true')
    action.add_argument('--deploy', action='store_true')
    action.add_argument('--rollback', action='store_true')
    parser.add_argument('--backup')
    args = parser.parse_args()
    if not args.preflight and not args.backup:
        parser.error('--backup is required')
    bundle = candidates()
    r = H.Remote()
    try:
        if args.preflight:
            print(json.dumps(real_preflight(r, bundle[2])), flush=True)
        else:
            deploy(r, args.backup, *bundle, rollback=args.rollback)
    finally:
        r.close()
