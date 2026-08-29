"""Deploy the construction-log video layer with backup, lint and rollback."""

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
UPLOADS = LIVE + "/wp-content/uploads/2026/08"
PLUGIN_NAME = "zz-harmat-construction-progress-video.php"
POSTER_NAME = "harmat-epitesi-naplo-2026-08.jpg"
PLUGIN_SOURCE = ROOT / "wp-mu-plugins" / PLUGIN_NAME
POSTER_SOURCE = ROOT / "assets" / "construction" / POSTER_NAME
q = shlex.quote


def sha256_bytes(data):
    return hashlib.sha256(data).hexdigest()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--key", required=True)
    parser.add_argument("--passphrase-file", required=True)
    parser.add_argument("--deploy", action="store_true")
    args = parser.parse_args()

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
    backup = "/home/harmath2/codex-backups/construction-video-" + stamp
    plugin_target = MU + "/" + PLUGIN_NAME
    poster_target = UPLOADS + "/" + POSTER_NAME
    staged_plugin = MU + "/." + PLUGIN_NAME + ".codex-tmp-" + stamp
    staged_poster = UPLOADS + "/." + POSTER_NAME + ".codex-tmp-" + stamp
    plugin_installed = False
    poster_installed = False
    original_poster_hash = None

    def run(command, timeout=90):
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
        if not PLUGIN_SOURCE.is_file() or not POSTER_SOURCE.is_file():
            raise RuntimeError("Reviewed plugin or poster source is missing.")

        if remote_hash(plugin_target) is not None:
            raise RuntimeError("Live construction video plugin already exists; refusing unguarded overwrite.")

        original_poster_hash = remote_hash(poster_target)
        print("LIVE_PLUGIN=ABSENT")
        print("LIVE_POSTER=" + (original_poster_hash or "ABSENT"))
        print(
            run(
                "cd "
                + q(LIVE)
                + " && wp eval "
                + q(
                    '$p=get_page_by_path("epitesi-naplo"); '
                    'echo "PAGE_ID=".($p ? $p->ID : 0)."\\n"; '
                    'echo "PAGE_STATUS=".($p ? $p->post_status : "missing")."\\n"; '
                    'echo "OFFERS=".wp_count_posts("harmat_offer_lead")->private."\\n";'
                )
            )
        )
        if not args.deploy:
            return

        run("mkdir -m 700 " + q(backup))
        run(
            "curl -L --compressed -sS "
            + q("https://harmat22.hu/epitesi-naplo/")
            + " -o "
            + q(backup + "/epitesi-naplo-before.html")
        )
        if original_poster_hash:
            run("cp -p " + q(poster_target) + " " + q(backup + "/" + POSTER_NAME))
            if remote_hash(backup + "/" + POSTER_NAME) != original_poster_hash:
                raise RuntimeError("Poster backup verification failed.")
        print("BACKUP=" + backup)

        plugin_hash = sha256_bytes(PLUGIN_SOURCE.read_bytes())
        poster_hash = sha256_bytes(POSTER_SOURCE.read_bytes())
        run("mkdir -p " + q(UPLOADS))

        sftp.put(str(POSTER_SOURCE), staged_poster)
        sftp.chmod(staged_poster, 0o644)
        if remote_hash(staged_poster) != poster_hash:
            raise RuntimeError("Poster upload verification failed.")

        sftp.put(str(PLUGIN_SOURCE), staged_plugin)
        sftp.chmod(staged_plugin, 0o644)
        if remote_hash(staged_plugin) != plugin_hash:
            raise RuntimeError("Plugin upload verification failed.")
        print(run("php -l " + q(staged_plugin)))

        if remote_hash(plugin_target) is not None:
            raise RuntimeError("Live plugin appeared while staging.")
        if remote_hash(poster_target) != original_poster_hash:
            raise RuntimeError("Live poster changed while staging.")

        sftp.posix_rename(staged_poster, poster_target)
        poster_installed = True
        if remote_hash(poster_target) != poster_hash:
            raise RuntimeError("Final poster verification failed.")

        sftp.posix_rename(staged_plugin, plugin_target)
        plugin_installed = True
        print(run("php -l " + q(plugin_target)))
        if remote_hash(plugin_target) != plugin_hash:
            raise RuntimeError("Final plugin verification failed.")

        print(purge())
        verification = run(
            "cd "
            + q(LIVE)
            + " && wp eval "
            + q(
                'echo "WP_BOOT_OK\\n"; '
                'echo "OFFERS=".wp_count_posts("harmat_offer_lead")->private."\\n"; '
                '$p=get_page_by_path("epitesi-naplo"); '
                'echo "PAGE_STATUS=".($p ? $p->post_status : "missing")."\\n";'
            )
        )
        print(verification)

        live_html = run(
            "curl -L --compressed -sS " + q("https://harmat22.hu/epitesi-naplo/")
        )
        required = (
            'data-harmat-construction-video="1"',
            HARMAT_VIDEO_ID,
            POSTER_NAME,
            'class="harmat-construction-trigger"',
        )
        missing = [marker for marker in required if marker not in live_html]
        if missing:
            raise RuntimeError("Live page is missing markers: " + ", ".join(missing))
        if '<iframe' in live_html.lower():
            raise RuntimeError("Construction page loaded an iframe before visitor interaction.")

        report = {
            "deployedAt": stamp,
            "backup": backup,
            "databaseChanged": False,
            "pluginSha256": plugin_hash,
            "posterSha256": poster_hash,
            "originalPosterSha256": original_poster_hash,
        }
        output = ROOT / "outputs" / "construction-video"
        output.mkdir(parents=True, exist_ok=True)
        (output / "deployment.json").write_text(json.dumps(report, indent=2) + "\n")
        print("DEPLOYED_AND_VERIFIED")
    except Exception:
        if plugin_installed and remote_hash(plugin_target) is not None:
            sftp.remove(plugin_target)
        if poster_installed:
            if original_poster_hash:
                restore = poster_target + ".rollback-tmp-" + stamp
                run("cp -p " + q(backup + "/" + POSTER_NAME) + " " + q(restore))
                sftp.posix_rename(restore, poster_target)
            elif remote_hash(poster_target) is not None:
                sftp.remove(poster_target)
        if plugin_installed or poster_installed:
            print(purge())
            print("ROLLED_BACK")
        raise
    finally:
        for path in (staged_plugin, staged_poster):
            try:
                sftp.remove(path)
            except FileNotFoundError:
                pass
        sftp.close()
        client.close()


HARMAT_VIDEO_ID = "HMgnTfeuQYM"


if __name__ == "__main__":
    main()
