"""Read-only server audit for the construction-video deployment."""

import argparse
import hashlib
import pathlib
import shlex

import paramiko


ROOT = pathlib.Path(__file__).resolve().parents[2]
LIVE = "/home/harmath2/public_html"
PLUGIN = "zz-harmat-construction-progress-video.php"
POSTER = "harmat-epitesi-naplo-2026-08.jpg"
q = shlex.quote


def sha256(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--key", required=True)
    parser.add_argument("--passphrase-file", required=True)
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

    def run(command, timeout=120):
        _, stdout, stderr = client.exec_command(command, timeout=timeout)
        out = stdout.read().decode("utf-8", errors="replace")
        err = stderr.read().decode("utf-8", errors="replace")
        code = stdout.channel.recv_exit_status()
        if code:
            raise RuntimeError(f"Remote command failed ({code}): {out} {err}")
        return (out + err).strip()

    def remote_hash(path):
        with sftp.open(path, "rb") as source:
            return hashlib.sha256(source.read()).hexdigest()

    try:
        plugin_source = ROOT / "wp-mu-plugins" / PLUGIN
        poster_source = ROOT / "assets" / "construction" / POSTER
        plugin_target = LIVE + "/wp-content/mu-plugins/" + PLUGIN
        poster_target = LIVE + "/wp-content/uploads/2026/08/" + POSTER

        if remote_hash(plugin_target) != sha256(plugin_source):
            raise RuntimeError("Live plugin hash does not match GitHub source.")
        if remote_hash(poster_target) != sha256(poster_source):
            raise RuntimeError("Live poster hash does not match GitHub source.")
        print("LIVE_HASHES_MATCH")
        print(run("php -l " + q(plugin_target)))
        print(run("cd " + q(LIVE) + " && wp core verify-checksums"))
        print(run("cd " + q(LIVE) + " && wp db check", timeout=180))
        print(run("cd " + q(LIVE) + " && wp cron test"))
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

        staged = run(
            "find "
            + q(LIVE + "/wp-content/mu-plugins")
            + " "
            + q(LIVE + "/wp-content/uploads/2026/08")
            + " -maxdepth 1 -type f -name "
            + q("*.codex-tmp-*")
            + " -print"
        )
        if staged:
            raise RuntimeError("Temporary deployment files remain: " + staged)
        print("NO_STAGED_FILES")

        log_state = run(
            "for f in "
            + q(LIVE + "/error_log")
            + " "
            + q(LIVE + "/wp-admin/error_log")
            + " "
            + q(LIVE + "/wp-content/debug.log")
            + '; do if [ -e "$f" ]; then stat -c "%n|%s|%Y" "$f"; fi; done'
        )
        print("ERROR_LOGS=" + (log_state or "ABSENT"))

        html = run("curl -L --compressed -sS " + q("https://harmat22.hu/epitesi-naplo/"))
        required = (
            'data-harmat-construction-video="1"',
            "HMgnTfeuQYM",
            POSTER,
            '"@type":"VideoObject"',
            'name="description" content="A Harmat Lakópark építési naplója:',
        )
        missing = [marker for marker in required if marker not in html]
        if missing:
            raise RuntimeError("Live HTML markers missing: " + ", ".join(missing))
        if "<iframe" in html.lower():
            raise RuntimeError("YouTube iframe loads before visitor interaction.")
        print("LIVE_HTML_PASS")
        print("CONSTRUCTION_VIDEO_SERVER_AUDIT_PASSED")
    finally:
        sftp.close()
        client.close()


if __name__ == "__main__":
    main()
