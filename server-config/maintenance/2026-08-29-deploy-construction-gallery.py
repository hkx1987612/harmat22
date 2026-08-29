"""Deploy the construction photo timeline with guarded backup and rollback."""

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
GALLERY_DIR = UPLOADS + "/construction-progress"
PLUGIN_NAME = "zz-harmat-construction-progress-video.php"
PLUGIN_SOURCE = ROOT / "wp-mu-plugins" / PLUGIN_NAME
GALLERY_SOURCE = ROOT / "assets" / "construction" / "progress"
EXPECTED_LIVE_PLUGIN_SHA256 = "c83c94289658ab638c9fa967cdc44c2a70bfb8cb1c2dd02c286da17f015393a5"
q = shlex.quote


def sha256_bytes(data):
    return hashlib.sha256(data).hexdigest()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--key", required=True)
    parser.add_argument("--passphrase-file", required=True)
    parser.add_argument("--deploy", action="store_true")
    args = parser.parse_args()

    image_sources = sorted(GALLERY_SOURCE.glob("*.webp"))
    if not PLUGIN_SOURCE.is_file():
        raise RuntimeError("Reviewed construction plugin is missing.")
    if len(image_sources) != 32:
        raise RuntimeError(f"Expected 32 reviewed WebP files, found {len(image_sources)}.")

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
    backup = "/home/harmath2/codex-backups/construction-gallery-" + stamp
    plugin_target = MU + "/" + PLUGIN_NAME
    staged_plugin = MU + "/." + PLUGIN_NAME + ".codex-tmp-" + stamp
    plugin_replaced = False
    gallery_created = False
    installed_images = []
    staged_images = []

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
        live_plugin_hash = remote_hash(plugin_target)
        if live_plugin_hash != EXPECTED_LIVE_PLUGIN_SHA256:
            raise RuntimeError(
                "Live construction plugin changed unexpectedly: " + str(live_plugin_hash)
            )
        try:
            existing_gallery = sftp.listdir(GALLERY_DIR)
        except FileNotFoundError:
            existing_gallery = None
        if existing_gallery is not None:
            raise RuntimeError("Live construction gallery directory already exists; refusing overwrite.")

        before_state = run(
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
        print("LIVE_PLUGIN=" + live_plugin_hash)
        print("LIVE_GALLERY=ABSENT")
        print(before_state)
        if not args.deploy:
            return

        run("mkdir -m 700 " + q(backup))
        run("cp -p " + q(plugin_target) + " " + q(backup + "/" + PLUGIN_NAME))
        if remote_hash(backup + "/" + PLUGIN_NAME) != live_plugin_hash:
            raise RuntimeError("Plugin backup verification failed.")
        run(
            "curl -L --compressed -sS "
            + q("https://harmat22.hu/epitesi-naplo/")
            + " -o "
            + q(backup + "/epitesi-naplo-before.html")
        )
        print("BACKUP=" + backup)

        plugin_hash = sha256_bytes(PLUGIN_SOURCE.read_bytes())
        sftp.put(str(PLUGIN_SOURCE), staged_plugin)
        sftp.chmod(staged_plugin, 0o644)
        if remote_hash(staged_plugin) != plugin_hash:
            raise RuntimeError("Plugin staging hash verification failed.")
        print(run("php -l " + q(staged_plugin)))

        run("mkdir -m 755 " + q(GALLERY_DIR))
        gallery_created = True
        image_hashes = {}
        for source in image_sources:
            target = GALLERY_DIR + "/" + source.name
            staged = GALLERY_DIR + "/." + source.name + ".codex-tmp-" + stamp
            if remote_hash(target) is not None:
                raise RuntimeError("Unexpected live gallery file: " + target)
            local_hash = sha256_bytes(source.read_bytes())
            image_hashes[source.name] = local_hash
            sftp.put(str(source), staged)
            staged_images.append(staged)
            sftp.chmod(staged, 0o644)
            if remote_hash(staged) != local_hash:
                raise RuntimeError("Gallery staging hash verification failed: " + source.name)

        if remote_hash(plugin_target) != live_plugin_hash:
            raise RuntimeError("Live plugin changed while staging.")
        for source in image_sources:
            if remote_hash(GALLERY_DIR + "/" + source.name) is not None:
                raise RuntimeError("Gallery target appeared while staging: " + source.name)

        for source in image_sources:
            staged = GALLERY_DIR + "/." + source.name + ".codex-tmp-" + stamp
            target = GALLERY_DIR + "/" + source.name
            sftp.posix_rename(staged, target)
            staged_images.remove(staged)
            installed_images.append(target)
            if remote_hash(target) != image_hashes[source.name]:
                raise RuntimeError("Final gallery hash verification failed: " + source.name)

        sftp.posix_rename(staged_plugin, plugin_target)
        plugin_replaced = True
        print(run("php -l " + q(plugin_target)))
        if remote_hash(plugin_target) != plugin_hash:
            raise RuntimeError("Final plugin hash verification failed.")

        print(purge())
        after_state = run(
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
        print(after_state)
        if before_state.split("OFFERS=")[-1].splitlines()[0] != after_state.split("OFFERS=")[-1].splitlines()[0]:
            raise RuntimeError("Offer lead count changed during deployment.")

        live_html = run("curl -L --compressed -sS " + q("https://harmat22.hu/epitesi-naplo/"))
        required = (
            'data-harmat-construction-video="1"',
            'data-harmat-construction-gallery="1"',
            'data-harmat-construction-lightbox',
            '"@type":"ImageGallery"',
            "2026-08-26-tomoritett-agyazat-960.webp",
        )
        missing = [marker for marker in required if marker not in live_html]
        if missing:
            raise RuntimeError("Live page is missing markers: " + ", ".join(missing))
        if live_html.count('<button type="button" data-harmat-construction-photo ') != 16:
            raise RuntimeError("Live construction photo count is not 16.")
        if live_html.count("-1920.webp") < 16:
            raise RuntimeError("Live full-size gallery references are incomplete.")
        if "<iframe" in live_html.lower():
            raise RuntimeError("Construction page loaded an iframe before visitor interaction.")

        headers = run(
            "curl -L -sS -I "
            + q(
                "https://harmat22.hu/wp-content/uploads/2026/08/construction-progress/"
                "2026-08-26-tomoritett-agyazat-960.webp"
            )
        )
        if "200" not in headers or "content-type: image/webp" not in headers.lower():
            raise RuntimeError("Public gallery image response is invalid: " + headers)

        report = {
            "deployedAt": stamp,
            "backup": backup,
            "databaseChanged": False,
            "pluginSha256": plugin_hash,
            "imageCount": len(image_sources),
            "imageBytes": sum(source.stat().st_size for source in image_sources),
        }
        output = ROOT / "outputs" / "construction-gallery"
        output.mkdir(parents=True, exist_ok=True)
        (output / "deployment.json").write_text(json.dumps(report, indent=2) + "\n")
        print("DEPLOYED_AND_VERIFIED")
    except Exception:
        if plugin_replaced:
            restore = plugin_target + ".rollback-tmp-" + stamp
            run("cp -p " + q(backup + "/" + PLUGIN_NAME) + " " + q(restore))
            sftp.posix_rename(restore, plugin_target)
            print(run("php -l " + q(plugin_target)))
        for path in installed_images:
            try:
                sftp.remove(path)
            except FileNotFoundError:
                pass
        for path in staged_images:
            try:
                sftp.remove(path)
            except FileNotFoundError:
                pass
        if gallery_created:
            try:
                sftp.rmdir(GALLERY_DIR)
            except OSError:
                pass
        if plugin_replaced or gallery_created:
            print(purge())
            print("ROLLED_BACK")
        raise
    finally:
        try:
            sftp.remove(staged_plugin)
        except FileNotFoundError:
            pass
        for path in list(staged_images):
            try:
                sftp.remove(path)
            except FileNotFoundError:
                pass
        sftp.close()
        client.close()


if __name__ == "__main__":
    main()
