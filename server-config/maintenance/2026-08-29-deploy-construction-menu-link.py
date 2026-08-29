"""Deploy the construction-log header-menu link with backup and rollback."""

import argparse
import hashlib
import json
import pathlib
import shlex
from datetime import datetime, timezone

import paramiko


ROOT = pathlib.Path(__file__).resolve().parents[2]
LIVE = "/home/harmath2/public_html"
MU = LIVE + "/wp-content/mu-plugins"
PLUGIN_NAME = "zz-harmat-construction-menu-link.php"
PLUGIN_SOURCE = ROOT / "wp-mu-plugins" / PLUGIN_NAME
q = shlex.quote


def sha256_bytes(data):
    return hashlib.sha256(data).hexdigest()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--key", required=True)
    parser.add_argument("--passphrase-file", required=True)
    parser.add_argument("--deploy", action="store_true")
    args = parser.parse_args()

    if not PLUGIN_SOURCE.is_file():
        raise RuntimeError("Reviewed menu-link plugin is missing.")

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
    backup = "/home/harmath2/codex-backups/construction-menu-link-" + stamp
    target = MU + "/" + PLUGIN_NAME
    staged = MU + "/." + PLUGIN_NAME + ".codex-tmp-" + stamp
    installed = False

    def run(command, timeout=120):
        _, stdout, stderr = client.exec_command(command, timeout=timeout)
        out = stdout.read().decode("utf-8", errors="replace")
        err = stderr.read().decode("utf-8", errors="replace")
        code = stdout.channel.recv_exit_status()
        if code:
            raise RuntimeError(f"Remote command failed ({code}): {out} {err}")
        return out.strip()

    def remote_bytes(path):
        try:
            with sftp.open(path, "rb") as source:
                return source.read()
        except FileNotFoundError:
            return None

    def remote_hash(path):
        data = remote_bytes(path)
        return None if data is None else sha256_bytes(data)

    def purge():
        return run(
            "cd "
            + q(LIVE)
            + " && wp eval "
            + q(
                'if (function_exists("wp_cache_clear_cache")) { wp_cache_clear_cache(); } '
                'wp_cache_flush(); delete_transient("harmat_public_page_scan"); '
                'echo "CACHE_CLEARED";'
            )
        )

    try:
        if remote_hash(target) is not None:
            raise RuntimeError("Live menu-link plugin already exists; refusing overwrite.")
        before = run(
            "cd "
            + q(LIVE)
            + " && wp eval "
            + q(
                '$p=get_page_by_path("epitesi-naplo"); '
                'echo "PAGE_STATUS=".($p ? $p->post_status : "missing")."\\n"; '
                'echo "OFFERS=".wp_count_posts("harmat_offer_lead")->private."\\n";'
            )
        )
        print("LIVE_PLUGIN=ABSENT")
        print(before)
        if not args.deploy:
            return

        run("mkdir -m 700 " + q(backup))
        run(
            "curl -L --compressed -sS "
            + q("https://harmat22.hu/")
            + " -o "
            + q(backup + "/homepage-before.html")
        )
        run(
            "curl -L --compressed -sS "
            + q("https://harmat22.hu/epitesi-naplo/")
            + " -o "
            + q(backup + "/epitesi-naplo-before.html")
        )
        print("BACKUP=" + backup)

        source_hash = sha256_bytes(PLUGIN_SOURCE.read_bytes())
        sftp.put(str(PLUGIN_SOURCE), staged)
        sftp.chmod(staged, 0o644)
        if remote_hash(staged) != source_hash:
            raise RuntimeError("Staged plugin hash verification failed.")
        print(run("php -l " + q(staged)))
        if remote_hash(target) is not None:
            raise RuntimeError("Live menu-link plugin appeared while staging.")

        sftp.posix_rename(staged, target)
        installed = True
        print(run("php -l " + q(target)))
        if remote_hash(target) != source_hash:
            raise RuntimeError("Final plugin hash verification failed.")

        print(purge())
        after = run(
            "cd "
            + q(LIVE)
            + " && wp eval "
            + q(
                'echo "WP_BOOT_OK\\n"; '
                '$p=get_page_by_path("epitesi-naplo"); '
                'echo "PAGE_STATUS=".($p ? $p->post_status : "missing")."\\n"; '
                'echo "OFFERS=".wp_count_posts("harmat_offer_lead")->private."\\n";'
            )
        )
        print(after)
        before_offers = before.split("OFFERS=")[-1].splitlines()[0]
        after_offers = after.split("OFFERS=")[-1].splitlines()[0]
        if before_offers != after_offers:
            raise RuntimeError("Offer lead count changed during deployment.")

        home_html = run("curl -L --compressed -sS " + q("https://harmat22.hu/"))
        required = (
            'id="harmat-construction-menu-link"',
            "link.href='/epitesi-naplo/'",
            "link.textContent='Építési napló'",
            'a[href="/elerhetosegeink/"]',
        )
        missing = [marker for marker in required if marker not in home_html]
        if missing:
            raise RuntimeError("Live homepage is missing menu runtime markers: " + ", ".join(missing))

        report = {
            "deployedAt": stamp,
            "backup": backup,
            "databaseChanged": False,
            "pluginSha256": source_hash,
        }
        output = ROOT / "outputs" / "construction-menu-link"
        output.mkdir(parents=True, exist_ok=True)
        (output / "deployment.json").write_text(json.dumps(report, indent=2) + "\n")
        print("DEPLOYED_AND_VERIFIED")
    except Exception:
        if installed and remote_hash(target) is not None:
            sftp.remove(target)
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
