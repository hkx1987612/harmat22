"""Guarded search/construction update. Default and --verify are read-only."""

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
BASE = "3610bd2"
BACKUP_ROOT = "/home/harmath2/codex-backups"
FILES = (
    (
        "wp-mu-plugins/harmat-public-audit-polish.php",
        "/wp-content/mu-plugins/harmat-public-audit-polish.php",
    ),
    (
        "wp-mu-plugins/zz-harmat-construction-progress-video.php",
        "/wp-content/mu-plugins/zz-harmat-construction-progress-video.php",
    ),
    (
        "wp-plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php",
        "/wp-content/plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php",
    ),
)
MEDIA = (
    (
        "assets/construction/harmat-kornyek-kutyapark-2026-09.mp4",
        "/wp-content/uploads/2026/09/harmat-kornyek-kutyapark-2026-09.mp4",
        5_441_367,
        "f88c316bcc2ede84dcd403ce339cafe059d2365f4925fc6d0f486302c5a1c45a",
    ),
    (
        "assets/construction/harmat-kornyek-kutyapark-2026-09.jpg",
        "/wp-content/uploads/2026/09/harmat-kornyek-kutyapark-2026-09.jpg",
        362_434,
        "6c62955ceed0afb094435e88e9eec729edbc92d37d7b3911cfe2f3127861e224",
    ),
)
Q = shlex.quote


def digest(data):
    return hashlib.sha256(data).hexdigest()


def file_digest(path):
    h = hashlib.sha256()
    with path.open("rb") as source:
        for block in iter(lambda: source.read(1024 * 1024), b""):
            h.update(block)
    return h.hexdigest()


def semantic(data):
    return data.replace(b"\r\n", b"\n")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--deploy", action="store_true")
    mode.add_argument("--verify", action="store_true")
    mode.add_argument("--rollback", metavar="PRIVATE_BACKUP_DIR")
    parser.add_argument("--key", default=str(Path.home() / "Downloads" / "harmat"))
    parser.add_argument(
        "--passphrase-file", default=str(Path.home() / ".ssh" / "harmat_key_pass.txt")
    )
    args = parser.parse_args()
    if args.rollback and not re.fullmatch(
        r"/home/harmath2/codex-backups/search-construction-park-\d{8}-\d{6}",
        args.rollback,
    ):
        parser.error("--rollback must name an exact private backup directory")

    local_hashes = {}
    baselines = {}
    if not args.rollback:
        for local, _ in FILES:
            path = ROOT / local
            if not path.is_file():
                raise RuntimeError("Missing reviewed PHP: " + local)
            local_hashes[local] = file_digest(path)
            baselines[local] = subprocess.check_output(
                ["git", "show", f"{BASE}:{local}"], cwd=ROOT
            )
            if semantic(path.read_bytes()) == semantic(baselines[local]):
                raise RuntimeError("No reviewed change in " + local)
        for local, _, expected_size, expected_hash in MEDIA:
            path = ROOT / local
            if not path.is_file() or path.stat().st_size != expected_size:
                raise RuntimeError("Missing or changed reviewed media: " + local)
            if file_digest(path) != expected_hash:
                raise RuntimeError("Media hash mismatch: " + local)
            local_hashes[local] = expected_hash

    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.load_host_keys(str(Path.home() / ".ssh" / "known_hosts"))
    client.connect(
        "185.111.89.244",
        username="harmath2",
        key_filename=args.key,
        passphrase=Path(args.passphrase_file).read_text(encoding="utf-8").strip(),
        look_for_keys=False,
        allow_agent=False,
        timeout=20,
    )
    sftp = client.open_sftp()
    stamp = datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")
    backup = f"{BACKUP_ROOT}/search-construction-park-{stamp}"
    stages = []
    installed_php = []
    installed_media = []

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
            with sftp.open(path, "rb") as source:
                for block in iter(lambda: source.read(1024 * 1024), b""):
                    h.update(block)
        except FileNotFoundError:
            return None
        return h.hexdigest()

    def remote_bytes(path):
        with sftp.open(path, "rb") as source:
            return source.read()

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

    def purge():
        return wp(
            'delete_transient("harmat_lakas_redesign_markup_v12");'
            'delete_transient("harmat_lakas_redesign_markup_v13");'
            'if(function_exists("wp_cache_clear_cache"))wp_cache_clear_cache();'
            'wp_cache_flush();echo "CACHE_CLEARED";'
        )

    def smoke():
        urls = (
            ("/lakaskereso/", ("hm-lakas-construction-link", "/epitesi-naplo/")),
            ("/epitesi-naplo/", ("data-harmat-nearby-play", "data-harmat-construction-gallery=", "data-harmat-construction-video=")),
            ("/", ()),
            ("/property/a1-f-l1/", ()),
            ("/virtualis-lakasvalaszto/", ()),
            ("/virtualis-lakasvalaszto-elso-utem/", ()),
            ("/elerhetosegeink/", ()),
        )
        for path, markers in urls:
            html = run(
                "curl --fail --location --compressed --silent --show-error --max-time 45 "
                + "-H " + Q("Cache-Control: no-cache") + " "
                + Q("https://harmat22.hu" + path)
            )
            if not html or any(
                bad in html for bad in ("Fatal error:", "Parse error:", "critical error on this website")
            ):
                raise RuntimeError("Broken public page: " + path)
            if any(marker not in html for marker in markers):
                raise RuntimeError("Missing deployed page marker: " + path)
            if path == "/lakaskereso/" and "hm-lakas-stats" in html:
                raise RuntimeError("Old search badges still visible")
            if path == "/epitesi-naplo/":
                if '<section class="harmat-info-hero"' in html or html.count("data-harmat-construction-photo ") != 16:
                    raise RuntimeError("Construction intro or photo count is wrong")
            print("PAGE_OK=" + path, flush=True)
        for _, remote, _, _ in MEDIA:
            content_type = "video/mp4" if remote.endswith(".mp4") else "image/jpeg"
            headers = run(
                "curl --fail --location --silent --show-error --max-time 30 --head "
                + Q("https://harmat22.hu" + remote)
            ).lower()
            if "200" not in headers or content_type not in headers:
                raise RuntimeError("Public media response is invalid: " + remote)

    def restore_files(source, php_paths, media_paths, expected):
        # Check every target before changing any, so a concurrent edit blocks rollback.
        for target in php_paths + media_paths:
            if remote_hash(target) != expected[target]:
                raise RuntimeError("Concurrent change blocks safe rollback: " + target)
        for _, remote in reversed(FILES):
            target = LIVE + remote
            if target not in php_paths:
                continue
            saved = source + "/" + Path(remote).name
            original_hash = remote_hash(saved)
            if original_hash is None:
                raise RuntimeError("Missing private backup: " + saved)
            temp = target.rsplit("/", 1)[0] + "/." + Path(remote).name + ".restore-" + stamp + ".tmp"
            stages.append(temp)
            run("cp -p " + Q(saved) + " " + Q(temp))
            if remote_hash(temp) != original_hash:
                raise RuntimeError("Restore staging hash mismatch: " + target)
            run("php -l " + Q(temp))
            if remote_hash(target) != expected[target]:
                raise RuntimeError("Concurrent change blocks restore: " + target)
            sftp.posix_rename(temp, target)
            run("php -l " + Q(target))
            if remote_hash(target) != original_hash:
                raise RuntimeError("Restored PHP hash mismatch: " + target)
        for target in media_paths:
            if remote_hash(target) != expected[target]:
                raise RuntimeError("Concurrent media change blocks removal: " + target)
            sftp.remove(target)
        print(purge(), flush=True)

    try:
        if args.rollback:
            manifest_path = args.rollback + "/manifest.json"
            manifest = json.loads(remote_bytes(manifest_path).decode("utf-8"))
            if manifest.get("backup") != args.rollback or manifest.get("baseline") != BASE:
                raise RuntimeError("Backup manifest does not match requested deployment")
            expected = manifest.get("deployedHashes", {})
            all_targets = [LIVE + remote for _, remote in FILES] + [LIVE + remote for _, remote, _, _ in MEDIA]
            if set(expected) != set(all_targets):
                raise RuntimeError("Incomplete backup manifest")
            for _, remote in FILES:
                target = LIVE + remote
                if remote_hash(args.rollback + "/" + Path(remote).name) != manifest["originalHashes"][target]:
                    raise RuntimeError("Private backup hash mismatch: " + target)
            print("BACKUP=" + args.rollback, flush=True)
            before = state()
            restore_files(args.rollback, all_targets[:3], all_targets[3:], expected)
            after = state()
            if after["offerLeads"] != before["offerLeads"]:
                raise RuntimeError("Offer lead count changed during rollback")
            print("ROLLED_BACK=" + json.dumps({"before": before, "after": after}), flush=True)
            return

        if args.verify:
            for local, remote in FILES:
                if remote_hash(LIVE + remote) != local_hashes[local]:
                    raise RuntimeError("Live PHP does not match reviewed source: " + remote)
            for local, remote, _, _ in MEDIA:
                if remote_hash(LIVE + remote) != local_hashes[local]:
                    raise RuntimeError("Live media does not match reviewed source: " + remote)
            smoke()
            print("VERIFIED=" + json.dumps(state()), flush=True)
            return

        originals = {}
        for local, remote in FILES:
            target = LIVE + remote
            data = remote_bytes(target)
            if semantic(data) != semantic(baselines[local]):
                raise RuntimeError("Live PHP differs from Git baseline: " + remote)
            originals[target] = digest(data)
        for _, remote, _, _ in MEDIA:
            if remote_hash(LIVE + remote) is not None:
                raise RuntimeError("Media already exists: " + remote)
        before = state()
        print("PREFLIGHT=" + json.dumps(before), flush=True)

        if not args.deploy:
            print("READ_ONLY_PREFLIGHT_OK", flush=True)
            return

        run("mkdir -m 700 " + Q(backup))
        for _, remote in FILES:
            target = LIVE + remote
            saved = backup + "/" + Path(remote).name
            run("cp -p " + Q(target) + " " + Q(saved))
            if remote_hash(saved) != originals[target]:
                raise RuntimeError("Private backup hash mismatch: " + remote)
        expected = {LIVE + remote: local_hashes[local] for local, remote in FILES}
        expected.update({LIVE + remote: local_hashes[local] for local, remote, _, _ in MEDIA})
        manifest = {
            "backup": backup,
            "baseline": BASE,
            "originalHashes": originals,
            "deployedHashes": expected,
            "before": before,
        }
        with sftp.open(backup + "/manifest.json", "wb") as output:
            output.write(json.dumps(manifest, indent=2).encode("utf-8"))
        sftp.chmod(backup + "/manifest.json", 0o600)
        print("BACKUP=" + backup, flush=True)

        uploads = LIVE + "/wp-content/uploads/2026/09"
        run("mkdir -p " + Q(uploads))
        for local, remote, _, _ in MEDIA:
            target = LIVE + remote
            temp = uploads + "/." + Path(remote).name + ".stage-" + stamp + ".tmp"
            stages.append(temp)
            sftp.put(str(ROOT / local), temp)
            sftp.chmod(temp, 0o644)
            if remote_hash(temp) != local_hashes[local]:
                raise RuntimeError("Staged media hash mismatch: " + remote)
        for local, remote in FILES:
            target = LIVE + remote
            temp = target.rsplit("/", 1)[0] + "/." + Path(remote).name + ".stage-" + stamp + ".tmp"
            stages.append(temp)
            sftp.put(str(ROOT / local), temp)
            sftp.chmod(temp, 0o644)
            if remote_hash(temp) != local_hashes[local]:
                raise RuntimeError("Staged PHP hash mismatch: " + remote)
            print(run("php -l " + Q(temp)), flush=True)

        for _, remote in FILES:
            if remote_hash(LIVE + remote) != originals[LIVE + remote]:
                raise RuntimeError("Concurrent PHP edit before installation: " + remote)
        for _, remote, _, _ in MEDIA:
            if remote_hash(LIVE + remote) is not None:
                raise RuntimeError("Media appeared during staging: " + remote)

        for _, remote, _, _ in MEDIA:
            target = LIVE + remote
            temp = uploads + "/." + Path(remote).name + ".stage-" + stamp + ".tmp"
            sftp.posix_rename(temp, target)
            installed_media.append(target)
            if remote_hash(target) != expected[target]:
                raise RuntimeError("Installed media hash mismatch: " + remote)
        for _, remote in FILES:
            target = LIVE + remote
            temp = target.rsplit("/", 1)[0] + "/." + Path(remote).name + ".stage-" + stamp + ".tmp"
            if remote_hash(target) != originals[target]:
                raise RuntimeError("Concurrent PHP edit during installation: " + remote)
            sftp.posix_rename(temp, target)
            installed_php.append(target)
            print(run("php -l " + Q(target)), flush=True)
            if remote_hash(target) != expected[target]:
                raise RuntimeError("Installed PHP hash mismatch: " + remote)

        print(purge(), flush=True)
        smoke()
        after = state()
        if after["offerLeads"] != before["offerLeads"] or after["errorLog"] != before["errorLog"]:
            raise RuntimeError("Offer count or error log changed; automatic rollback required")
        print("DEPLOYED=" + json.dumps({"backup": backup, "before": before, "after": after,
                                         "deployedHashes": expected}), flush=True)
    except Exception:
        if installed_php or installed_media:
            try:
                restore_files(backup, installed_php, installed_media, expected)
                print("AUTO_ROLLBACK=" + backup, flush=True)
            except Exception as restore_error:
                print("ROLLBACK_NEEDS_REVIEW=" + str(restore_error) + " BACKUP=" + backup, flush=True)
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
