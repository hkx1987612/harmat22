"""Guarded September deployment. Default/--verify are read-only on the server.

Requires Paramiko, trusted known_hosts, Git and local PHP. Never submits forms.
Backups contain all media and both PHP versions; rollback needs no local assets/Git.
Do not commit backup/media files. Interrupted runs retain the private backup and
lock for operator review; never automatically break a stale lock.
"""

import argparse
import hashlib
import json
import re
import shlex
import stat
import subprocess
import uuid
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[2]
BASE = "ff06d6c"
LOCAL = "wp-mu-plugins/zz-harmat-construction-progress-video.php"
LIVE = "/home/harmath2/public_html"
TARGET = LIVE + "/wp-content/mu-plugins/" + Path(LOCAL).name
MEDIA = LIVE + "/wp-content/uploads/2026/09/construction-september"
PRIVATE = "/home/harmath2/codex-backups"
PREFIX = PRIVATE + "/september-construction-"
LOCK = PRIVATE + "/.september-construction.lock"
MARK = 'data-harmat-construction-september="1"'
SLUGS = ("2026-09-overview", "2026-09-21-a1-a2", "2026-09-22-a2",
         "2026-09-23-a1", "2026-09-24-a3", "2026-09-25-a1",
         "2026-09-25-a2", "2026-09-25-a4")
NAMES = tuple(s + ext for s in SLUGS for ext in (".mp4", ".jpg")) + tuple(
    f"harmat-2026-09-site-{i:02d}-{width}.webp"
    for i in range(1, 5) for width in (960, 1920))
Q = shlex.quote


def digest(data):
    return hashlib.sha256(data).hexdigest()


def file_hash(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def semantic(data):
    # Only transport newline differences are ignored; no code/text replacement.
    return data.replace(b"\r\n", b"\n")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument("--deploy", action="store_true")
    modes.add_argument("--verify", action="store_true")
    modes.add_argument("--rollback", metavar="EXACT_PRIVATE_BACKUP_PATH")
    parser.add_argument("--key", default=str(Path.home() / "Downloads/harmat"))
    parser.add_argument("--passphrase-file", default=str(Path.home() / ".ssh/harmat_key_pass.txt"))
    parser.add_argument("--php", default="php", help="Local PHP executable path")
    args = parser.parse_args()
    if args.rollback and not re.fullmatch(re.escape(PREFIX) + r"[0-9a-f]{32}", args.rollback):
        parser.error("Rollback requires the exact backup path printed by this helper")

    sources = {}
    hashes = {}
    if not args.rollback:
        source = ROOT / LOCAL
        data = semantic(source.read_bytes())
        baseline = subprocess.check_output(["git", "show", BASE + ":" + LOCAL], cwd=ROOT)
        if not re.search(rb"Version:\s*1\.3\.0\b", data) or MARK.encode() not in data:
            raise RuntimeError("Local PHP must be reviewed v1.3.0 with September marker")
        if semantic(data) == semantic(baseline):
            raise RuntimeError("PHP is unchanged from baseline")
        subprocess.run([args.php, "-l", str(source)], check=True)
        for name in NAMES:
            directory = ("outputs/2026-09-construction-ready" if name.endswith(".mp4")
                         else "assets/construction")
            path = ROOT / directory / name
            if not path.is_file() or path.is_symlink() or not path.stat().st_size:
                raise RuntimeError("Missing/empty/nonregular media: " + str(path))
            sources[name] = path
            hashes[name] = file_hash(path)
        new_hash = digest(data)
        print("LOCAL_HASHES=" + json.dumps({"php": new_hash, "media": hashes}, sort_keys=True))

    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.load_host_keys(str(Path.home() / ".ssh/known_hosts"))
    client.set_missing_host_key_policy(paramiko.RejectPolicy())
    client.connect("185.111.89.244", username="harmath2", key_filename=args.key,
                   passphrase=Path(args.passphrase_file).read_text(encoding="utf-8").strip(),
                   look_for_keys=False, allow_agent=False, timeout=20)
    sftp = client.open_sftp()
    token = uuid.uuid4().hex
    backup = PREFIX + token
    php_stage = TARGET.rsplit("/", 1)[0] + "/.september-" + token + ".tmp"
    media_stage = MEDIA.rsplit("/", 1)[0] + "/.september-" + token + ".tmp"
    locked = False
    attempted = False
    manifest = None

    def run(command):
        _, out, err = client.exec_command(command, timeout=180)
        result = out.read().decode("utf-8", "replace")
        error = err.read().decode("utf-8", "replace")
        if out.channel.recv_exit_status():
            raise RuntimeError("Remote command failed: " + (error or result))
        return result.strip()

    def exists(path):
        try:
            return sftp.lstat(path)
        except FileNotFoundError:
            return None

    def rhash(path):
        info = exists(path)
        if info is None:
            return None
        if not stat.S_ISREG(info.st_mode):
            raise RuntimeError("Not a regular file: " + path)
        h = hashlib.sha256()
        with sftp.open(path, "rb") as stream:
            for block in iter(lambda: stream.read(1024 * 1024), b""):
                h.update(block)
        return h.hexdigest()

    def read(path):
        with sftp.open(path, "rb") as stream:
            return stream.read()

    def wp(code):
        return run("wp --path=" + Q(LIVE) + " eval " + Q(code))

    def state():
        count = wp('global $wpdb; echo (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s", "harmat_offer_lead"));')
        log = exists(LIVE + "/error_log")
        return {"offerLeads": int(count), "errorLog": None if log is None else
                [log.st_size, log.st_mtime, rhash(LIVE + "/error_log")]}

    def unchanged(before, label="POST_STATE"):
        after = state()
        print(label + "=" + json.dumps(after, sort_keys=True), flush=True)
        if after != before:
            raise RuntimeError("Offer-lead count or error_log state changed")

    def purge():
        print(wp('if(function_exists("wp_cache_clear_cache"))wp_cache_clear_cache();'
                 'wp_cache_flush();echo "CACHE_CLEARED";'))

    def smoke(september):
        for route in ("/epitesi-naplo/", "/", "/lakaskereso/", "/property/a1-f-l1/",
                      "/virtualis-lakasvalaszto/", "/virtualis-lakasvalaszto-elso-utem/",
                      "/elerhetosegeink/"):
            html = run("curl --fail --location --compressed --silent --show-error --max-time 45 "
                       "-H 'Cache-Control: no-cache' " + Q("https://harmat22.hu" + route + "?guard=" + token))
            if not html or any(s in html.lower() for s in
                               ("fatal error:", "parse error:", "critical error on this website")):
                raise RuntimeError("Smoke failed: " + route)
            if route == "/epitesi-naplo/":
                if (MARK in html) != september:
                    raise RuntimeError("Unexpected September page marker")
                if september and any(name not in html for name in NAMES):
                    raise RuntimeError("September page is missing media references")
            print("PAGE_OK=" + route)
        if september:
            for name in NAMES:
                run("curl --fail --silent --show-error --head --max-time 45 " + Q(
                    "https://harmat22.hu/wp-content/uploads/2026/09/construction-september/" + name))

    def check_media(directory, expected, allow_absent=False):
        info = exists(directory)
        if info is None and allow_absent:
            return False
        if info is None or not stat.S_ISDIR(info.st_mode):
            raise RuntimeError("Missing/non-directory media: " + directory)
        if set(sftp.listdir(directory)) != set(NAMES):
            raise RuntimeError("Unexpected files in media directory; refusing mutation")
        for name in NAMES:
            if rhash(directory + "/" + name) != expected[name]:
                raise RuntimeError("Media hash mismatch: " + name)
        return True

    def restore(m):
        old = m["oldHash"]
        new = m["newHash"]
        saved = m["backup"]
        if rhash(saved + "/before.php") != old or rhash(saved + "/after.php") != new:
            raise RuntimeError("Private PHP backup corrupted")
        check_media(saved + "/media", m["media"])
        current = rhash(TARGET)
        if current not in (old, new):
            raise RuntimeError("Concurrent PHP edit blocks rollback")
        has_media = check_media(MEDIA, m["media"], True)
        if current == new and not has_media:
            raise RuntimeError("Missing media with deployed PHP; manual review required")
        if current == new:
            run("cp -p " + Q(saved + "/before.php") + " " + Q(php_stage))
            run("php -l " + Q(php_stage))
            if rhash(php_stage) != old or rhash(TARGET) != new:
                raise RuntimeError("Concurrent PHP change during restore")
            sftp.posix_rename(php_stage, TARGET)
        run("php -l " + Q(TARGET))
        if rhash(TARGET) != old:
            raise RuntimeError("Restored PHP mismatch")
        if has_media:
            check_media(MEDIA, m["media"])
            # Rename the entire owned directory first; never delete a partial set.
            sftp.rename(MEDIA, media_stage)
            check_media(media_stage, m["media"])
            for name in NAMES:
                if rhash(media_stage + "/" + name) != m["media"][name]:
                    raise RuntimeError("Concurrent media edit blocks deletion")
                sftp.remove(media_stage + "/" + name)
            sftp.rmdir(media_stage)
        purge()
        smoke(False)
        print("ROLLED_BACK=" + saved)

    try:
        if args.deploy or args.rollback:
            sftp.mkdir(LOCK, mode=0o700)
            locked = True
        elif exists(LOCK):
            raise RuntimeError("Deployment lock exists; retry after operator review")

        if args.rollback:
            manifest = json.loads(read(args.rollback + "/manifest.json"))
            if (manifest.get("backup") != args.rollback or manifest.get("baseline") != BASE
                    or manifest.get("target") != TARGET or manifest.get("mediaTarget") != MEDIA
                    or set(manifest.get("media", {})) != set(NAMES)):
                raise RuntimeError("Backup manifest contract mismatch")
            for h in [manifest["oldHash"], manifest["newHash"], *manifest["media"].values()]:
                if not re.fullmatch(r"[0-9a-f]{64}", h):
                    raise RuntimeError("Invalid manifest hash")
            before = state()
            print("PRE_STATE=" + json.dumps(before, sort_keys=True), flush=True)
            restore(manifest)
            unchanged(before)
            return

        before = state()
        print("PRE_STATE=" + json.dumps(before, sort_keys=True), flush=True)
        if args.verify or (args.deploy and rhash(TARGET) == new_hash and exists(MEDIA)):
            if rhash(TARGET) != new_hash:
                raise RuntimeError("Live PHP hash mismatch")
            check_media(MEDIA, hashes)
            run("php -l " + Q(TARGET))
            smoke(True)
            unchanged(before, "VERIFIED_STATE")
            print("VERIFIED_EXACT_HASHES")
            return
        original = read(TARGET)
        if semantic(original) != semantic(baseline):
            raise RuntimeError("Live PHP differs from Git ff06d6c baseline")
        old_hash = digest(original)
        if exists(MEDIA):
            raise RuntimeError("Media destination must be entirely absent")
        run("php -l " + Q(TARGET))
        unchanged(before)
        if not args.deploy:
            print("READ_ONLY_PREFLIGHT_OK")
            return

        sftp.mkdir(backup, mode=0o700)
        print("BACKUP=" + backup, flush=True)
        run("cp -p " + Q(TARGET) + " " + Q(backup + "/before.php"))
        with sftp.open(backup + "/after.php", "wb") as output:
            output.write(data)
        sftp.chmod(backup + "/after.php", 0o600)
        sftp.mkdir(backup + "/media", mode=0o700)
        for name, path in sources.items():
            sftp.put(str(path), backup + "/media/" + name)
            sftp.chmod(backup + "/media/" + name, 0o600)
        check_media(backup + "/media", hashes)
        if rhash(backup + "/before.php") != old_hash or rhash(backup + "/after.php") != new_hash:
            raise RuntimeError("Private backup PHP mismatch")
        manifest = {"backup": backup, "baseline": BASE, "target": TARGET,
                    "mediaTarget": MEDIA, "oldHash": old_hash, "newHash": new_hash,
                    "media": hashes, "before": before}
        with sftp.open(backup + "/manifest.json", "wb") as output:
            output.write(json.dumps(manifest, indent=2).encode("ascii"))
        sftp.chmod(backup + "/manifest.json", 0o600)
        if json.loads(read(backup + "/manifest.json")) != manifest:
            raise RuntimeError("Backup manifest verification failed")

        run("cp " + Q(backup + "/after.php") + " " + Q(php_stage))
        sftp.chmod(php_stage, 0o644)
        run("php -l " + Q(php_stage))
        if rhash(php_stage) != new_hash:
            raise RuntimeError("Staged PHP hash mismatch")
        sftp.mkdir(media_stage, mode=0o755)
        for name in NAMES:
            run("cp " + Q(backup + "/media/" + name) + " " + Q(media_stage + "/" + name))
            sftp.chmod(media_stage + "/" + name, 0o644)
        check_media(media_stage, hashes)
        unchanged(before)
        if rhash(TARGET) != old_hash or exists(MEDIA):
            raise RuntimeError("Concurrent change blocks installation")
        attempted = True
        # Standard SFTP rename refuses an existing destination (no overwrite).
        sftp.rename(media_stage, MEDIA)
        check_media(MEDIA, hashes)
        if rhash(TARGET) != old_hash:
            raise RuntimeError("Concurrent PHP edit before activation")
        sftp.posix_rename(php_stage, TARGET)
        run("php -l " + Q(TARGET))
        if rhash(TARGET) != new_hash:
            raise RuntimeError("Final PHP hash mismatch")
        purge()
        smoke(True)
        unchanged(before)
        print("DEPLOYED_AND_VERIFIED=" + backup)
    except Exception:
        if attempted and manifest:
            try:
                restore(manifest)
                print("POST_STATE=" + json.dumps(state(), sort_keys=True), flush=True)
                print("AUTO_ROLLBACK=" + backup)
            except Exception as error:
                locked = False  # Retain the lock when recovery needs operator review.
                print("ROLLBACK_NEEDS_REVIEW=" + str(error) + " BACKUP=" + backup)
        raise
    finally:
        # Only remove this invocation's regular hidden PHP stage. Private media
        # backups and any uncertain media stages remain available for recovery.
        try:
            if exists(php_stage) and stat.S_ISREG(sftp.lstat(php_stage).st_mode):
                sftp.remove(php_stage)
            if locked:
                sftp.rmdir(LOCK)
        finally:
            sftp.close()
            client.close()


if __name__ == "__main__":
    main()
