"""Guarded one-PHP journal-mark deployment. Default and --verify are read-only."""

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
LOCAL = "wp-plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php"
LIVE = "/home/harmath2/public_html"
TARGET = LIVE + "/wp-content/plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php"
BACKUP_ROOT = "/home/harmath2/codex-backups"
BASE = "5ae3bd4"
HOST = "185.111.89.244"
Q = shlex.quote


def digest(data):
    return hashlib.sha256(data).hexdigest()


def normalized(data):
    return data.replace(b"\r\n", b"\n")


def reviewed_source():
    path = ROOT / LOCAL
    source = path.read_bytes()
    baseline = subprocess.check_output(["git", "show", f"{BASE}:{LOCAL}"], cwd=ROOT)
    if normalized(source) == normalized(baseline):
        raise RuntimeError("No reviewed PHP change from the approved baseline")
    for marker in (
        b"Version: 1.2.4",
        b"harmat_lakas_redesign_markup_v14",
        b'hm-lakas-construction-link',
        b'hm-lakas-construction-mark',
        b'hm-lakas-construction-label',
        b"home_url('/epitesi-naplo/')",
    ):
        if marker not in source:
            raise RuntimeError("Reviewed PHP is missing expected journal-mark contract: " + marker.decode())
    if b"Version: 1.2.3" not in baseline or b"harmat_lakas_redesign_markup_v13" not in baseline:
        raise RuntimeError("Unexpected Git baseline")
    return path, source, baseline


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--deploy", action="store_true")
    mode.add_argument("--verify", action="store_true")
    mode.add_argument("--rollback", metavar="PRIVATE_BACKUP_DIR")
    parser.add_argument("--key", default=str(Path.home() / "Downloads" / "harmat"))
    parser.add_argument("--passphrase-file", default=str(Path.home() / ".ssh" / "harmat_key_pass.txt"))
    args = parser.parse_args()
    if args.rollback and not re.fullmatch(
        r"/home/harmath2/codex-backups/search-journal-mark-\d{8}-\d{6}", args.rollback
    ):
        parser.error("--rollback must name an exact private backup directory")

    if not args.rollback:
        local_path, source, baseline = reviewed_source()
        source_hash = digest(source)

    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.load_host_keys(str(Path.home() / ".ssh" / "known_hosts"))
    client.connect(
        HOST,
        username="harmath2",
        key_filename=args.key,
        passphrase=Path(args.passphrase_file).read_text(encoding="utf-8").strip(),
        look_for_keys=False,
        allow_agent=False,
        timeout=20,
    )
    sftp = client.open_sftp()
    stamp = datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")
    backup = f"{BACKUP_ROOT}/search-journal-mark-{stamp}"
    stages = []
    replacement_attempted = False

    def run(command, timeout=150):
        _, stdout, stderr = client.exec_command(command, timeout=timeout)
        output = stdout.read().decode("utf-8", errors="replace")
        error = stderr.read().decode("utf-8", errors="replace")
        status = stdout.channel.recv_exit_status()
        if status:
            raise RuntimeError(f"Remote command failed ({status}): {error or output}")
        return output.strip()

    def remote_hash(path):
        h = hashlib.sha256()
        try:
            with sftp.open(path, "rb") as stream:
                for block in iter(lambda: stream.read(1024 * 1024), b""):
                    h.update(block)
        except FileNotFoundError:
            return None
        return h.hexdigest()

    def remote_bytes(path):
        with sftp.open(path, "rb") as stream:
            return stream.read()

    def wp(code):
        return run("wp --path=" + Q(LIVE) + " eval " + Q(code))

    def state():
        try:
            log = sftp.stat(LIVE + "/error_log")
            log_state = {"bytes": log.st_size, "mtime": int(log.st_mtime)}
        except FileNotFoundError:
            log_state = None
        return {
            "offerLeads": int(wp('echo (int) wp_count_posts("harmat_offer_lead")->private;')),
            "errorLog": log_state,
        }

    def assert_state(before, after):
        if after != before:
            raise RuntimeError("Offer lead count or root error log changed")

    def purge():
        return wp(
            'delete_transient("harmat_lakas_redesign_markup_v13");'
            'delete_transient("harmat_lakas_redesign_markup_v14");'
            'if(function_exists("wp_cache_clear_cache"))wp_cache_clear_cache();'
            'wp_cache_flush();echo "CACHE_CLEARED";'
        )

    def smoke(expect_mark):
        paths = (
            "/lakaskereso/",
            "/",
            "/property/a1-f-l1/",
            "/virtualis-lakasvalaszto/",
            "/virtualis-lakasvalaszto-elso-utem/",
            "/elerhetosegeink/",
        )
        for path in paths:
            html = run(
                "curl --fail --location --compressed --silent --show-error --max-time 45 "
                + "-H " + Q("Cache-Control: no-cache") + " "
                + Q("https://harmat22.hu" + path)
            )
            if not html or any(bad in html for bad in (
                "Fatal error:", "Parse error:", "critical error on this website"
            )):
                raise RuntimeError("Broken public page: " + path)
            if path == "/lakaskereso/":
                link = re.search(
                    r'<a\b[^>]*class="[^"]*\bhm-lakas-construction-link\b[^"]*"[^>]*>.*?</a>',
                    html, re.DOTALL,
                )
                if not link or "/epitesi-naplo/" not in link.group():
                    raise RuntimeError("Journal link missing from search page")
                has_mark = bool(re.search(
                    r'<svg\b[^>]*class="[^"]*\bhm-lakas-construction-mark\b[^"]*"',
                    link.group(),
                ))
                if has_mark != expect_mark:
                    raise RuntimeError("Journal mark does not match expected deployment state")
            print("PAGE_OK=" + path, flush=True)

    def restore(directory, original_hash, deployed_hash):
        saved = directory + "/" + Path(TARGET).name
        if remote_hash(saved) != original_hash:
            raise RuntimeError("Private backup hash mismatch")
        if remote_hash(TARGET) != deployed_hash:
            raise RuntimeError("Concurrent change blocks safe rollback")
        temp = TARGET.rsplit("/", 1)[0] + "/." + Path(TARGET).name + ".restore-" + stamp + ".tmp"
        stages.append(temp)
        run("cp -p " + Q(saved) + " " + Q(temp))
        if remote_hash(temp) != original_hash:
            raise RuntimeError("Restore staging hash mismatch")
        print(run("php -l " + Q(temp)), flush=True)
        if remote_hash(TARGET) != deployed_hash:
            raise RuntimeError("Concurrent change blocks restore")
        sftp.posix_rename(temp, TARGET)
        print(run("php -l " + Q(TARGET)), flush=True)
        if remote_hash(TARGET) != original_hash:
            raise RuntimeError("Restored PHP hash mismatch")
        print(purge(), flush=True)

    try:
        if args.rollback:
            manifest = json.loads(remote_bytes(args.rollback + "/manifest.json").decode("utf-8"))
            if (manifest.get("backup") != args.rollback or manifest.get("baseline") != BASE
                    or manifest.get("target") != TARGET):
                raise RuntimeError("Backup manifest does not match this one-PHP deployment")
            original_hash = manifest["originalHash"]
            deployed_hash = manifest["deployedHash"]
            if not all(re.fullmatch(r"[0-9a-f]{64}", value) for value in (original_hash, deployed_hash)):
                raise RuntimeError("Invalid backup manifest hashes")
            before = state()
            restore(args.rollback, original_hash, deployed_hash)
            smoke(False)
            after = state()
            assert_state(before, after)
            print("ROLLED_BACK=" + json.dumps({"backup": args.rollback, "before": before, "after": after}), flush=True)
            return

        if args.verify:
            before = state()
            if remote_hash(TARGET) != source_hash:
                raise RuntimeError("Live PHP does not match reviewed source")
            print(run("php -l " + Q(TARGET)), flush=True)
            smoke(True)
            after = state()
            assert_state(before, after)
            print("VERIFIED=" + json.dumps(after), flush=True)
            return

        original = remote_bytes(TARGET)
        if normalized(original) != normalized(baseline):
            raise RuntimeError("Live PHP differs from Git baseline")
        original_hash = digest(original)
        before = state()
        print("PREFLIGHT=" + json.dumps({"before": before, "originalHash": original_hash,
                                         "deployedHash": source_hash}), flush=True)
        if not args.deploy:
            assert_state(before, state())
            print("READ_ONLY_PREFLIGHT_OK", flush=True)
            return

        run("mkdir -m 700 " + Q(backup))
        saved = backup + "/" + Path(TARGET).name
        run("cp -p " + Q(TARGET) + " " + Q(saved))
        if remote_hash(saved) != original_hash or remote_hash(TARGET) != original_hash:
            raise RuntimeError("Backup mismatch or concurrent edit before staging")
        manifest = {
            "backup": backup, "baseline": BASE, "target": TARGET,
            "originalHash": original_hash, "deployedHash": source_hash, "before": before,
        }
        with sftp.open(backup + "/manifest.json", "wb") as output:
            output.write(json.dumps(manifest, indent=2).encode("utf-8"))
        sftp.chmod(backup + "/manifest.json", 0o600)
        print("BACKUP=" + backup, flush=True)

        temp = TARGET.rsplit("/", 1)[0] + "/." + Path(TARGET).name + ".stage-" + stamp + ".tmp"
        stages.append(temp)
        sftp.put(str(local_path), temp)
        sftp.chmod(temp, 0o644)
        if remote_hash(temp) != source_hash:
            raise RuntimeError("Staged PHP hash mismatch")
        print(run("php -l " + Q(temp)), flush=True)
        if remote_hash(TARGET) != original_hash:
            raise RuntimeError("Concurrent edit before installation")
        replacement_attempted = True
        sftp.posix_rename(temp, TARGET)
        print(run("php -l " + Q(TARGET)), flush=True)
        if remote_hash(TARGET) != source_hash:
            raise RuntimeError("Installed PHP hash mismatch")
        print(purge(), flush=True)
        smoke(True)
        after = state()
        assert_state(before, after)
        print("DEPLOYED=" + json.dumps({"backup": backup, "before": before, "after": after,
                                         "deployedHash": source_hash}), flush=True)
    except Exception:
        if replacement_attempted:
            try:
                restore(backup, original_hash, source_hash)
                print("AUTO_ROLLBACK=" + backup, flush=True)
            except Exception as error:
                print("ROLLBACK_NEEDS_REVIEW=" + str(error) + " BACKUP=" + backup, flush=True)
        raise
    finally:
        for temp in stages:
            try:
                sftp.remove(temp)
            except FileNotFoundError:
                pass
        sftp.close()
        client.close()


if __name__ == "__main__":
    main()
