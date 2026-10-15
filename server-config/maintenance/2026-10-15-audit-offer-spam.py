"""Read-only source parity and aggregate inquiry-spam audit; no customer export."""
import argparse
import hashlib
import json
import re
import shlex
from datetime import datetime, timezone
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[2]
LIVE = "/home/harmath2/public_html"
PHP = "/opt/alt/php83/usr/bin/php"
WP = PHP + " /usr/local/bin/wp --path=" + shlex.quote(LIVE)
DAY = "2026-10-15"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--inspect-integrations", action="store_true")
    parser.add_argument("--summary-only", action="store_true")
    parser.add_argument("--since-utc", help="Read-only post-release sample, YYYY-MM-DDTHH:MM:SS")
    args = parser.parse_args()
    if args.since_utc and not re.fullmatch(r"2026-10-15T\d{2}:\d{2}:\d{2}", args.since_utc):
        raise RuntimeError("Unexpected sample timestamp")
    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.load_host_keys(str(Path.home() / ".ssh/known_hosts"))
    client.set_missing_host_key_policy(paramiko.RejectPolicy())
    client.connect(
        "185.111.89.244", username="harmath2",
        key_filename=str(Path.home() / "Downloads/harmat"),
        passphrase=(Path.home() / ".ssh/harmat_key_pass.txt").read_text(encoding="utf-8").strip(),
        look_for_keys=False, allow_agent=False, timeout=20,
    )
    sftp = client.open_sftp()
    try:
        def run(command, allowed_statuses=(0,)):
            _, stdout, stderr = client.exec_command(command, timeout=60)
            result, error = stdout.read(), stderr.read()
            status = stdout.channel.recv_exit_status()
            if status not in allowed_statuses:
                raise RuntimeError("Read-only command failed; inspect locally without exporting secrets")
            return result.decode("utf-8").strip()

        prefix = run(WP + " config get table_prefix --type=variable")
        if not re.fullmatch(r"[A-Za-z0-9_]+", prefix):
            raise RuntimeError("Unexpected table prefix")
        posts, meta = prefix + "posts", prefix + "postmeta"

        def select(sql):
            if not sql.startswith("SELECT ") or ";" in sql:
                raise RuntimeError("Only one SELECT is permitted")
            return run(WP + " db query " + shlex.quote(sql) + " --skip-column-names")

        parity = {}
        for local, remote in (
            ("wp-plugins/harmat-sales-manager/harmat-sales-manager.php", "wp-content/plugins/harmat-sales-manager/harmat-sales-manager.php"),
            ("wp-mu-plugins/zz-harmat-public-offer-integrity.php", "wp-content/mu-plugins/zz-harmat-public-offer-integrity.php"),
        ):
            with sftp.open(LIVE + "/" + remote, "rb") as stream:
                data = stream.read()
            parity[local] = {
                "live_sha256": hashlib.sha256(data).hexdigest(),
                "normalized_parity": data.replace(b"\r\n", b"\n") == (ROOT / local).read_bytes().replace(b"\r\n", b"\n"),
                "lint": run(PHP + " -l " + shlex.quote(LIVE + "/" + remote)),
            }

        window = f"p.post_type='harmat_offer_lead' AND p.post_date>='{DAY} 00:00:00' AND p.post_date<'2026-10-16 00:00:00'"
        queries = {
            "daily_totals": f"SELECT DATE(post_date),COUNT(*) FROM {posts} WHERE post_type='harmat_offer_lead' AND post_date>='2026-10-12' GROUP BY DATE(post_date) ORDER BY DATE(post_date)",
            "today_by_hour": f"SELECT HOUR(p.post_date),COUNT(*) FROM {posts} p WHERE {window} GROUP BY HOUR(p.post_date)",
            "today_by_form": f"SELECT COALESCE(m.meta_value,'missing'),COUNT(*) FROM {posts} p LEFT JOIN {meta} m ON p.ID=m.post_id AND m.meta_key='_harmat_offer_cf7_form_id' WHERE {window} GROUP BY COALESCE(m.meta_value,'missing')",
            "today_security_metadata": f"SELECT CASE WHEN m.meta_id IS NULL THEN 'absent' ELSE 'present' END,COUNT(*) FROM {posts} p LEFT JOIN {meta} m ON p.ID=m.post_id AND m.meta_key='_harmat_offer_security' WHERE {window} GROUP BY CASE WHEN m.meta_id IS NULL THEN 'absent' ELSE 'present' END",
            "today_mail_status": f"SELECT COALESCE(m.meta_value,'missing'),COUNT(*) FROM {posts} p LEFT JOIN {meta} m ON p.ID=m.post_id AND m.meta_key='_harmat_offer_mail_status' WHERE {window} GROUP BY COALESCE(m.meta_value,'missing')",
            "today_link_flags": f"SELECT SUM(LOWER(p.post_title) REGEXP 'https?://|www[.]'),SUM(LOWER(p.post_content) REGEXP 'https?://|www[.]'),SUM(LOWER(p.post_title) LIKE '%google.com/share.google%'),COUNT(*) FROM {posts} p WHERE {window}",
            "today_contact_duplicate_groups": f"SELECT COUNT(*),COALESCE(SUM(n),0),COALESCE(MAX(n),0) FROM (SELECT COUNT(*) n FROM {posts} p JOIN {meta} e ON e.post_id=p.ID AND e.meta_key='_harmat_offer_email' JOIN {meta} t ON t.post_id=p.ID AND t.meta_key='_harmat_offer_phone' WHERE {window} GROUP BY e.meta_value,t.meta_value HAVING COUNT(*)>1) duplicates",
            "today_delivery_flags": f"SELECT SUM(EXISTS(SELECT 1 FROM {meta} m WHERE m.post_id=p.ID AND m.meta_key='_harmat_offer_sales_mail_sent_at' AND m.meta_value<>'')),SUM(EXISTS(SELECT 1 FROM {meta} m WHERE m.post_id=p.ID AND m.meta_key='_harmat_offer_customer_mail_sent_at' AND m.meta_value<>'')),COUNT(*) FROM {posts} p WHERE {window}",
            "today_rows_no_contacts": f"SELECT p.ID,p.post_date,CASE WHEN LOWER(p.post_title) REGEXP 'https?://|www[.]' THEN 1 ELSE 0 END,CASE WHEN LOWER(p.post_content) REGEXP 'https?://|www[.]' THEN 1 ELSE 0 END,CASE WHEN EXISTS(SELECT 1 FROM {meta} m WHERE m.post_id=p.ID AND m.meta_key='_harmat_offer_security') THEN 'REST-security' ELSE 'no-REST-security' END FROM {posts} p WHERE {window} ORDER BY p.ID LIMIT 100",
        }
        if args.summary_only:
            queries.pop("today_rows_no_contacts")
        if args.since_utc:
            since = args.since_utc.replace("T", " ")
            queries["since_utc_total_and_url_name_count"] = f"SELECT COUNT(*),COALESCE(SUM(LOWER(p.post_title) REGEXP 'https?://|www[.]'),0) FROM {posts} p WHERE p.post_type='harmat_offer_lead' AND p.post_date_gmt>'{since}'"
            queries["today_latest_record_utc"] = f"SELECT MAX(p.post_date_gmt) FROM {posts} p WHERE {window}"
        aggregates = {} if args.inspect_integrations else {name: select(sql) for name, sql in queries.items()}
        integrations = {}
        formal = LIVE + "/wp-content/mu-plugins/zz-harmat-public-offer-antispam.php"
        try:
            formal_mtime = datetime.fromtimestamp(sftp.stat(formal).st_mtime, timezone.utc).isoformat()
        except FileNotFoundError:
            formal_mtime = None
        if args.inspect_integrations:
            roots = [LIVE + "/wp-content/" + part for part in ("mu-plugins", "plugins", "themes")]
            integrations["provider_sync_source_paths"] = run(
                "grep -RIl --include='*.php' -- 'sent-to-provider-sync' "
                + " ".join(shlex.quote(path) for path in roots), allowed_statuses=(0, 1)
            ).splitlines()
            integrations["provider_sync_snippet_post_ids"] = select(
                f"SELECT ID,post_type FROM {posts} WHERE post_content LIKE '%sent-to-provider-sync%' ORDER BY ID LIMIT 10"
            )
            integrations["provider_sync_option_names"] = select(
                f"SELECT option_name,LENGTH(option_value) FROM {prefix}options WHERE option_value LIKE '%sent-to-provider-sync%' LIMIT 10"
            )
            mu_root = LIVE + "/wp-content/mu-plugins"
            names = sftp.listdir(mu_root)
            if len(names) > 250:
                raise RuntimeError("Unexpected MU source count")
            for name in names:
                if not name.endswith(".php"):
                    continue
                path = mu_root + "/" + name
                if sftp.stat(path).st_size > 2_000_000:
                    continue
                with sftp.open(path, "rb") as stream:
                    data = stream.read()
                source = data.decode("utf-8", errors="replace")
                if "sent-to-provider-sync" not in source:
                    continue
                integrations[name] = {
                    "sha256": hashlib.sha256(data).hexdigest(),
                    "function_names": re.findall(r"function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(", source),
                    "hook_names": re.findall(r"add_(?:action|filter)\(\s*['\"]([^'\"]+)['\"]", source),
                    "inserts_post": "wp_insert_post" in source,
                }
            path = LIVE + "/wp-content/plugins/contact-form-7/includes/submission.php"
            with sftp.open(path, "rb") as stream:
                source = stream.read().decode("utf-8")
            integrations["contact-form-7/includes/submission.php"] = {
                "get_contact_form_method": bool(re.search(r"function\s+get_contact_form\s*\(", source)),
                "get_posted_data_method": bool(re.search(r"function\s+get_posted_data\s*\(", source)),
                "spam_filter_calls": [source[max(0, match.start() - 40):match.end() + 100]
                                      for match in re.finditer(r"apply_filters\(\s*['\"]wpcf7_spam['\"]", source)],
                "sha256": hashlib.sha256(source.encode()).hexdigest(),
            }
        report = {
            "utc": datetime.now(timezone.utc).isoformat(),
            "scope": "READ_ONLY_NO_CUSTOMER_CONTENT_NO_FORM_OR_MAIL_SEND_NO_CHANGE",
            "site_local_day": DAY,
            "sample_since_utc": args.since_utc,
            "formal_antispam_file_mtime_utc": formal_mtime,
            "source_parity": parity,
            "aggregates": aggregates,
            "integration_metadata": integrations,
            "caution": "Mail flags mean recorded wp_mail acceptance, not recipient delivery. Link flags alone are not a spam verdict.",
        }
        output = ROOT / "outputs/2026-10-15-offer-spam"
        output.mkdir(parents=True, exist_ok=True)
        target = output / (datetime.now(timezone.utc).strftime("audit-%H%M%S-%f.json"))
        with target.open("x", encoding="utf-8") as handle:
            json.dump(report, handle, indent=2)
        print(json.dumps(report, indent=2))
    finally:
        sftp.close()
        client.close()


if __name__ == "__main__":
    main()
