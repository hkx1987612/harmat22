"""Stage/lint the private WP-CLI-only spam cleanup. Preview before explicit trash."""
import argparse
import importlib.util
import json
import re
import shlex
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("harmat_trash_transport", ROOT / "server-config/maintenance/2026-10-14-deploy-appointment-source.py")
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--prepare", action="store_true")
    parser.add_argument("--trash", metavar="BACKUP")
    parser.add_argument("--inspect", metavar="BACKUP")
    parser.add_argument("--snapshot-sha256")
    parser.add_argument("--allow-live-write", action="store_true")
    args = parser.parse_args()
    if sum(bool(value) for value in (args.prepare, args.trash, args.inspect)) != 1:
        raise RuntimeError("Choose one mode")
    if args.trash and (not args.allow_live_write or not re.fullmatch(r"[a-f0-9]{64}", args.snapshot_sha256 or "")):
        raise RuntimeError("Explicit write and reviewed snapshot hash required")
    source = (ROOT / "server-config/maintenance/2026-10-15-trash-confirmed-offer-spam.php").read_bytes().replace(b"\r\n", b"\n")
    remote = MODULE.Remote()
    try:
        def cleanup_command(command):
            stdin, stdout, stderr = remote.client.exec_command(command, timeout=120)
            stdin.close()
            body, error = stdout.read(), stderr.read()
            status = stdout.channel.recv_exit_status()
            if status or error:
                guards = []
                for line in error.decode("utf-8", errors="replace").splitlines():
                    if line.startswith("HARMAT_SPAM_TRASH_GUARD:"):
                        value = json.loads(line.split(":", 1)[1]).get("guard", "")
                        if re.fullmatch(r"[A-Za-z0-9_ :;/-]{1,180}", value):
                            guards.append(value)
                raise RuntimeError("Guarded cleanup stopped; safe diagnostics: " + json.dumps({"status": status, "guards": guards}))
            return body.decode("utf-8").strip()
        if args.inspect:
            backup = args.inspect
            if not re.fullmatch(r"/home/harmath2/codex-backups/offer-spam-trash-[a-f0-9]{32}", backup):
                raise RuntimeError("Unexpected backup path")
            prefix = remote.run(MODULE.WP + " config get table_prefix --type=variable")
            if not re.fullmatch(r"[A-Za-z0-9_]+", prefix):
                raise RuntimeError("Unexpected database prefix")
            sql = f"SELECT post_status,COUNT(*) FROM {prefix}posts WHERE post_type='harmat_offer_lead' AND post_date>='2026-10-15' AND LOWER(post_title) LIKE '%https://www.google.com/share.google%' GROUP BY post_status"
            with remote.sftp.open(MODULE.LIVE + "/wp-includes/post.php", "rb") as handle:
                core = handle.read().decode("utf-8")
            start = core.index("function wp_trash_post(")
            end = core.index("function wp_untrash_post(", start)
            section = core[start:end]
            guid_index = core.find("if ( '' === $guid )")
            guid_sql = f"SELECT SUM(guid=''),COUNT(*) FROM {prefix}posts WHERE post_type='harmat_offer_lead' AND post_status='private' AND post_date>='2026-10-15' AND LOWER(post_title) LIKE '%https://www.google.com/share.google%'"
            print(json.dumps({"scope": "READ_ONLY_RECOVERY_INSPECTION", "private_backup_files": remote.sftp.listdir(backup),
                              "matching_status_counts": remote.run(MODULE.WP + " db query " + shlex.quote(sql) + " --skip-column-names"),
                              "blank_guid_and_target_count": remote.run(MODULE.WP + " db query " + shlex.quote(guid_sql) + " --skip-column-names"),
                              "core_blank_guid_fill": core[guid_index:guid_index + 400] if guid_index >= 0 else "not found",
                              "core_trash_function_start": section[:700], "core_trash_return_lines": [line.strip() for line in section.splitlines() if "return " in line]}, indent=2))
            return
        if args.prepare:
            remote.directory(MODULE.BACKUPS)
            backup = MODULE.BACKUPS + "/offer-spam-trash-" + uuid.uuid4().hex
            remote.mkdir(backup)
            remote.write_new(backup + "/runner.stage", source)
            remote.lint(backup + "/runner.stage")
            remote.sftp.rename(backup + "/runner.stage", backup + "/runner.php")
            remote.lint(backup + "/runner.php")
            result = json.loads(remote.run(MODULE.WP + " eval-file " + shlex.quote(backup + "/runner.php") + " preview"))
        else:
            backup = args.trash
            if not re.fullmatch(r"/home/harmath2/codex-backups/offer-spam-trash-[a-f0-9]{32}", backup):
                raise RuntimeError("Unexpected backup path")
            remote.directory(backup, private=True)
            if remote.read(backup + "/runner.php", private=True) != source:
                raise RuntimeError("Prepared private runner changed")
            remote.lint(backup + "/runner.php")
            result = json.loads(cleanup_command(MODULE.WP + " eval-file " + shlex.quote(backup + "/runner.php") + " trash " + shlex.quote(backup) + " " + args.snapshot_sha256))
        print(json.dumps({"backup": backup, "result": result}, indent=2))
        output = ROOT / "outputs/2026-10-15-offer-spam"
        output.mkdir(parents=True, exist_ok=True)
        filename = "trash-" + ("preview-" if args.prepare else "result-") + backup.rsplit("-", 1)[1] + ".json"
        with (output / filename).open("x", encoding="utf-8") as handle:
            json.dump({"backup": backup, "result": result}, handle, indent=2)
    finally:
        remote.close()


if __name__ == "__main__":
    main()
