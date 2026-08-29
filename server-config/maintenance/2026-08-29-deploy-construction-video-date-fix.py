"""Correct the construction VideoObject date with a guarded atomic update."""

import argparse
import hashlib
import pathlib
import shlex
from datetime import datetime, timezone

import paramiko


ROOT = pathlib.Path(__file__).resolve().parents[2]
LIVE = "/home/harmath2/public_html"
PLUGIN = "zz-harmat-construction-progress-video.php"
TARGET = LIVE + "/wp-content/mu-plugins/" + PLUGIN
BASE_SHA256 = "7ffe44cc6831b7748fdf561aff0b4aeb54124194fdd59c03f3e30d48a9aac1cd"
q = shlex.quote


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--key", required=True)
    parser.add_argument("--passphrase-file", required=True)
    parser.add_argument("--deploy", action="store_true")
    args = parser.parse_args()

    source = ROOT / "wp-mu-plugins" / PLUGIN
    candidate_hash = hashlib.sha256(source.read_bytes()).hexdigest()
    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.load_host_keys(str(pathlib.Path.home() / ".ssh" / "known_hosts"))
    client.connect(
        "185.111.89.244",
        username="harmath2",
        key_filename=args.key,
        passphrase=pathlib.Path(args.passphrase_file).read_text().strip(),
        look_for_keys=False,
        allow_agent=False,
        timeout=20,
    )
    sftp = client.open_sftp()
    stamp = datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")
    backup = "/home/harmath2/codex-backups/construction-video-date-fix-" + stamp
    staged = TARGET + ".codex-tmp-" + stamp
    replaced = False

    def run(command, timeout=90):
        _, stdout, stderr = client.exec_command(command, timeout=timeout)
        out = stdout.read().decode("utf-8", errors="replace")
        err = stderr.read().decode("utf-8", errors="replace")
        code = stdout.channel.recv_exit_status()
        if code:
            raise RuntimeError(f"Remote command failed ({code}): {out} {err}")
        return out.strip()

    def remote_hash(path):
        try:
            with sftp.open(path, "rb") as remote:
                return hashlib.sha256(remote.read()).hexdigest()
        except FileNotFoundError:
            return None

    def purge():
        return run(
            "cd "
            + q(LIVE)
            + " && wp eval "
            + q(
                'if (function_exists("wp_cache_clear_cache")) { wp_cache_clear_cache(); } '
                'wp_cache_flush(); echo "CACHE_CLEARED";'
            )
        )

    try:
        if remote_hash(TARGET) != BASE_SHA256:
            raise RuntimeError("Live plugin drift detected; refusing metadata correction.")
        print("LIVE_BASELINE_HASH_MATCHES")
        if not args.deploy:
            return

        run("mkdir -m 700 " + q(backup))
        run("cp -p " + q(TARGET) + " " + q(backup + "/" + PLUGIN))
        if remote_hash(backup + "/" + PLUGIN) != BASE_SHA256:
            raise RuntimeError("Backup verification failed.")
        print("BACKUP=" + backup)

        sftp.put(str(source), staged)
        sftp.chmod(staged, 0o644)
        if remote_hash(staged) != candidate_hash:
            raise RuntimeError("Candidate upload verification failed.")
        print(run("php -l " + q(staged)))
        if remote_hash(TARGET) != BASE_SHA256:
            raise RuntimeError("Live plugin changed while staging.")

        sftp.posix_rename(staged, TARGET)
        replaced = True
        print(run("php -l " + q(TARGET)))
        if remote_hash(TARGET) != candidate_hash:
            raise RuntimeError("Final plugin hash verification failed.")
        print(purge())
        print(
            run(
                "curl -L --compressed -sS "
                + q("https://harmat22.hu/epitesi-naplo/")
                + " | grep -F "
                + q('"uploadDate":"2026-08-28"')
                + " >/dev/null && echo SCHEMA_DATE_OK"
            )
        )
        print("DATE_FIX_DEPLOYED_AND_VERIFIED")
    except Exception:
        if replaced:
            restore = TARGET + ".rollback-tmp-" + stamp
            run("cp -p " + q(backup + "/" + PLUGIN) + " " + q(restore))
            print(run("php -l " + q(restore)))
            sftp.posix_rename(restore, TARGET)
            print(run("php -l " + q(TARGET)))
            print(purge())
            print("ROLLED_BACK")
        raise
    finally:
        try:
            sftp.remove(staged)
        except FileNotFoundError:
            pass
        sftp.close()
        client.close()


if __name__ == "__main__":
    main()
