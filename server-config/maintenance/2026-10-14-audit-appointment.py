"""Read-only appointment audit: SFTP source parity and aggregate SELECT queries."""
import argparse
import hashlib
import json
import re
import shlex
import subprocess
from datetime import datetime, timezone
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[2]
LIVE = "/home/harmath2/public_html"
PHP = "/opt/alt/php83/usr/bin/php"
WP = PHP + " /usr/local/bin/wp --path=" + shlex.quote(LIVE)
FILES = {
    "modal": ("wp-mu-plugins/harmat-unified-offer-modal.php", "wp-content/mu-plugins/harmat-unified-offer-modal.php"),
    "sales": ("wp-plugins/harmat-sales-manager/harmat-sales-manager.php", "wp-content/plugins/harmat-sales-manager/harmat-sales-manager.php"),
    "integrity": ("wp-mu-plugins/zz-harmat-public-offer-integrity.php", "wp-content/mu-plugins/zz-harmat-public-offer-integrity.php"),
}


def digest(data):
    return hashlib.sha256(data).hexdigest()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", required=True)
    parser.add_argument("--source-revision", help="Optional reviewed Git SHA for the pre-deployment comparison")
    parser.add_argument("--verify-approved-test", action="store_true",
                        help="Read back the one guarded TEST result; exclude only that proved owned lead from fingerprints")
    args = parser.parse_args()
    if args.source_revision and not re.fullmatch(r"[a-f0-9]{7,40}", args.source_revision):
        raise RuntimeError("A reviewed hexadecimal Git SHA is required")
    output = Path(args.output).resolve()
    if not output.is_relative_to((ROOT / "outputs/2026-10-14-appointment-qa").resolve()):
        raise RuntimeError("Evidence must remain in the owned ignored output directory")
    output.mkdir(parents=True, exist_ok=False)
    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.load_host_keys(str(Path.home() / ".ssh/known_hosts"))
    client.connect("185.111.89.244", username="harmath2",
                   key_filename=str(Path.home() / "Downloads/harmat"),
                   passphrase=(Path.home() / ".ssh/harmat_key_pass.txt").read_text(encoding="utf-8").strip(),
                   look_for_keys=False, allow_agent=False, timeout=20)
    sftp = client.open_sftp()
    try:
        def run(command):
            _, stdout, stderr = client.exec_command(command, timeout=60)
            result, error = stdout.read(), stderr.read()
            status = stdout.channel.recv_exit_status()
            if status:
                raise RuntimeError(f"Read-only command failed ({status}): " + (error or result).decode(errors="replace"))
            return result.decode().strip()

        sources = {}
        for label, (local, remote) in FILES.items():
            with sftp.open(LIVE + "/" + remote, "rb") as stream:
                data = stream.read()
            local_data = (subprocess.check_output(['git', 'show', args.source_revision + ':' + local], cwd=ROOT)
                          if args.source_revision else (ROOT / local).read_bytes())
            parity = data.replace(b"\r\n", b"\n") == local_data.replace(b"\r\n", b"\n")
            sources[label] = {"live_sha256": digest(data), "normalized_local_parity": parity,
                              "lint": run(PHP + " -l " + shlex.quote(LIVE + "/" + remote))}
            if not parity:
                raise RuntimeError("Live source differs from reviewed repository: " + label)

        prefix = run(WP + " config get table_prefix --type=variable")
        if not re.fullmatch(r"[A-Za-z0-9_]+", prefix):
            raise RuntimeError("Unexpected database prefix")
        posts, meta = prefix + "posts", prefix + "postmeta"
        def select(sql):
            if not sql.startswith("SELECT ") or ";" in sql:
                raise RuntimeError("Only one SELECT is permitted")
            return run(WP + " db query " + shlex.quote(sql) + " --skip-column-names")

        owned_test, excluded_id = None, None
        if args.verify_approved_test:
            state = json.loads((ROOT / "outputs/2026-10-14-appointment-qa/approved-once/state.json").read_text())
            result = state.get('result', {})
            excluded_id = result.get('id')
            if (state.get('status') != 'HTTP_RESULT_RECEIVED_NO_RETRY'
                    or state.get('http_status') != 200 or result.get('success') is not True
                    or type(excluded_id) is not int or excluded_id <= 0
                    or not re.fullmatch(r'HARMAT-QA-20261014-[a-f0-9]{8}', state.get('marker', ''))
                    or state.get('scope') != 'ONE_APPROVED_TEST_NO_AD_PIXEL_NO_PROPERTY_LOCK'
                    or state.get('public_test_contact') != {
                        'name': 'TESZT - Harmat rendszerellen\u0151rz\u00e9s',
                        'email': 'ertekesites@harmat22.hu', 'phone': '+36300733375'}
                    or (state.get('date'), state.get('time'), state.get('apartment'), state.get('source')) !=
                       ('2026-10-20', '09:00-12:00', 'A1-1-L2', 'Google keres\u00e9s')
                    or not re.fullmatch(r'WEB-20261014-[0-9]+', result.get('crm', ''))):
                raise RuntimeError('Only the exact acknowledged one-test state can be verified')

            def hx(value):
                return '0x' + value.encode('utf-8').hex()

            identity = select(f"SELECT COUNT(*) FROM {posts} WHERE ID={excluded_id} "
                              "AND post_type='harmat_offer_lead' AND post_status='private' "
                              f"AND INSTR(CAST(post_title AS BINARY),{hx(state['public_test_contact']['name'])})>0 "
                              f"AND INSTR(CAST(post_content AS BINARY),{hx(state['marker'])})>0 "
                              f"AND INSTR(CAST(post_content AS BINARY),{hx('TESZT - ne h\u00edvj\u00e1k vissza!')})>0") == '1'
            expected_meta = {
                '_harmat_offer_email': state['public_test_contact']['email'],
                '_harmat_offer_phone': state['public_test_contact']['phone'],
                '_harmat_offer_apartment': state['apartment'],
                '_harmat_offer_date': state['date'], '_harmat_offer_time': state['time'],
                '_harmat_offer_crm_code': result['crm'],
            }
            saved_fields = {key: select(f"SELECT COUNT(*) FROM {meta} WHERE post_id={excluded_id} "
                            f"AND meta_key='{key}' AND CAST(meta_value AS BINARY)={hx(value)}") == '1'
                            for key, value in expected_meta.items()}
            saved_source = {}
            for key in ('_harmat_offer_posted', '_harmat_offer_tracking'):
                saved_source[key] = select(f"SELECT COUNT(*) FROM {meta} WHERE post_id={excluded_id} "
                    f"AND meta_key='{key}' AND INSTR(CAST(meta_value AS BINARY),{hx(state['source'])})>0 "
                    f"AND INSTR(CAST(meta_value AS BINARY),{hx('maintenance_test')})>0 "
                    f"AND INSTR(CAST(meta_value AS BINARY),{hx('qa_no_ads')})>0") == '1'
            if not identity or not all(saved_fields.values()) or not all(saved_source.values()):
                raise RuntimeError('TEST ownership/saved fields not proved; no fingerprint exclusion allowed')
            mail_status = select(f"SELECT meta_value FROM {meta} WHERE post_id={excluded_id} "
                                 "AND meta_key='_harmat_offer_mail_status'")
            flags = {}
            for key in ('_harmat_offer_sales_mail_sent_at', '_harmat_offer_customer_mail_sent_at'):
                flags[key] = select(f"SELECT COUNT(*) FROM {meta} WHERE post_id={excluded_id} "
                                    f"AND meta_key='{key}' AND meta_value<>''") == '1'
            errors = select(f"SELECT COUNT(*) FROM {meta} WHERE post_id={excluded_id} "
                            "AND meta_key IN ('_harmat_offer_sales_mail_error','_harmat_offer_customer_mail_error') "
                            "AND meta_value<>''")
            owned_test = {'identity_proved': identity, 'saved_field_checks': saved_fields,
                          'saved_source_tracking_checks': saved_source, 'mail_status': mail_status,
                          'mail_success_timestamp_present': flags, 'nonempty_mail_error_count': int(errors),
                          'wp_mail_accepted_both': mail_status == 'sent' and all(flags.values()) and errors == '0',
                          'inbox_delivery_verified': False, 'manual_resend_or_cron_triggered': False}
        queries = {
            "lead_totals": f"SELECT post_status, COUNT(*) FROM {posts} WHERE post_type='harmat_offer_lead' GROUP BY post_status",
            "lead_dates_last14": f"SELECT DATE(post_date),COUNT(*) FROM {posts} WHERE post_type='harmat_offer_lead' AND post_date>=DATE_SUB(NOW(),INTERVAL 14 DAY) GROUP BY DATE(post_date) ORDER BY DATE(post_date)",
            "mail_status_last14": f"SELECT COALESCE(m.meta_value,'missing'),COUNT(*) FROM {posts} p LEFT JOIN {meta} m ON m.post_id=p.ID AND m.meta_key='_harmat_offer_mail_status' WHERE p.post_type='harmat_offer_lead' AND p.post_date>=DATE_SUB(NOW(),INTERVAL 14 DAY) GROUP BY COALESCE(m.meta_value,'missing')",
            "old_pending_mail": f"SELECT COALESCE(m.meta_value,'missing'),COUNT(*) FROM {posts} p JOIN {meta} m ON m.post_id=p.ID AND m.meta_key='_harmat_offer_mail_status' WHERE p.post_type='harmat_offer_lead' AND m.meta_value IN ('queued','sending','retrying','partial','failed') AND p.post_date<DATE_SUB(NOW(),INTERVAL 10 MINUTE) GROUP BY m.meta_value",
            "date_time_quality": f"SELECT SUM(CASE WHEN d.meta_value='' OR d.meta_value IS NULL THEN 1 ELSE 0 END),SUM(CASE WHEN t.meta_value='' OR t.meta_value IS NULL THEN 1 ELSE 0 END),COUNT(*) FROM {posts} p LEFT JOIN {meta} d ON d.post_id=p.ID AND d.meta_key='_harmat_offer_date' LEFT JOIN {meta} t ON t.post_id=p.ID AND t.meta_key='_harmat_offer_time' WHERE p.post_type='harmat_offer_lead'",
        }
        stats = {}
        for label, sql in queries.items():
            stats[label] = select(sql)
        fingerprints = {}
        for label, kind in [('offer_records', 'harmat_offer_lead'), ('property_records', 'property')]:
            excluded = f" AND ID<>{excluded_id}" if excluded_id and kind == 'harmat_offer_lead' else ''
            sql = f"SELECT ID,SHA2(CONCAT_WS('|',HEX(post_type),HEX(post_status),HEX(post_title),HEX(post_content),HEX(post_modified)),256) FROM {posts} WHERE post_type='{kind}'{excluded} ORDER BY ID"
            rows = select(sql)
            fingerprints[label] = {'row_count': len(rows.splitlines()), 'sha256': digest(rows.encode())}
            excluded = f" AND p.ID<>{excluded_id}" if excluded_id and kind == 'harmat_offer_lead' else ''
            sql = f"SELECT m.meta_id,m.post_id,SHA2(CONCAT_WS('|',HEX(m.meta_key),HEX(m.meta_value)),256) FROM {meta} m JOIN {posts} p ON p.ID=m.post_id WHERE p.post_type='{kind}' AND m.meta_key<>'_elementor_element_cache'{excluded} ORDER BY m.meta_id"
            rows = select(sql)
            fingerprints[label + '_metadata'] = {'row_count': len(rows.splitlines()), 'sha256': digest(rows.encode())}
        try:
            info = sftp.stat(LIVE + "/error_log")
            error_log = {"present": True, "bytes": info.st_size, "mtime": info.st_mtime}
        except FileNotFoundError:
            error_log = {"present": False}
        report = {"utc": datetime.now(timezone.utc).isoformat(), "scope": "READ_ONLY_NO_INQUIRY_OR_EMAIL",
                  "sources": sources, "aggregate_stats": stats, "protected_record_fingerprints": fingerprints, "root_error_log": error_log,
                  "approved_test_readback": owned_test,
                  "limits": ["No customer content/contact export", "Mail status is wp_mail result, not inbox delivery",
                             "Normal visitors/cron can change aggregate counts between audits"]}
        if owned_test:
            before = json.loads((ROOT / 'outputs/2026-10-14-appointment-qa/protected-before/audit.json').read_text())
            report['original_records_unchanged_excluding_only_proved_test'] = (
                fingerprints == before['protected_record_fingerprints'])
        with (output / "audit.json").open("x", encoding="utf-8") as handle:
            json.dump(report, handle, indent=2)
        print(json.dumps(report, indent=2))
        if owned_test and not report['original_records_unchanged_excluding_only_proved_test']:
            raise RuntimeError('Original customer/property fingerprint changed; inspect without rewriting data')
    finally:
        sftp.close()
        client.close()


if __name__ == "__main__":
    main()
