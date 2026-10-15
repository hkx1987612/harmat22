"""Guarded new-file MU deployment. No positive inquiry, deletion or IP blocking.

Explicit --deploy --allow-live-write --expected-sha256 is required. Rollback
removes only this new file at its verified released hash, never customer data.
"""
import argparse
import hashlib
import importlib.util
import json
import re
import shlex
import subprocess
import uuid
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SOURCE = "wp-mu-plugins/zz-harmat-public-offer-antispam.php"
TEST = "server-config/maintenance/2026-10-15-test-offer-antispam.php"
BASE = "5d3dcf1"
LIVE = "/home/harmath2/public_html"
TARGET = LIVE + "/wp-content/mu-plugins/zz-harmat-public-offer-antispam.php"
PREFIX = "/home/harmath2/codex-backups/offer-name-antispam-"
LOCK = "/home/harmath2/codex-backups/.offer-name-antispam.lock"
CRITICAL = {
    LIVE + "/wp-content/mu-plugins/zz-harmat-public-offer-integrity.php": "9c5864a6a38581d06f0f82bf64ec3142b014f0443cab16f7c9a01ff4ebc33ed3",
    LIVE + "/wp-content/plugins/harmat-sales-manager/harmat-sales-manager.php": "e2339891f8aaafe8eddf3315851479b65c866bb7c72cef6a79e1cfb59a2080cf",
}
RUNTIME_CHECK = r'''
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('CLI only'); }
if (has_filter('rest_request_before_callbacks', 'harmat_offer_antispam_filter') !== 20
    || has_filter('wpcf7_spam', 'harmat_offer_antispam_cf7_spam') !== 20) {
    throw new RuntimeException('Runtime hook registration mismatch');
}
$checks = 2;
foreach (array('/harmat-sales-manager/v1/offer', '/contact-form-7/v1/contact-forms/1002/feedback', '/contact-form-7/v1/contact-forms/8761/feedback') as $route) {
    $r = new WP_REST_Request('POST', $route);
    $r->set_param('your-name', 'https://example.invalid/runtime-only-no-request');
    $result = harmat_offer_antispam_filter(null, array(), $r);
    if (!is_wp_error($result) || $result->get_error_code() !== 'harmat_offer_antispam_invalid_name'
        || $result->get_error_data()['status'] !== 400) { throw new RuntimeException('Runtime rejection failed'); }
    $checks++;
    foreach (array('John Smith', json_decode('"Kov\u00e1cs \u00c1gnes"'), json_decode('"\u5f20\u4f1f"')) as $name) {
        $r->set_param('your-name', $name);
        $r->set_param('your-message', 'https://harmat22.hu/property/a1-1-l2/');
        if (harmat_offer_antispam_filter(null, array(), $r) !== null) { throw new RuntimeException('Runtime normal-name regression'); }
        $checks++;
    }
}
if (!class_exists('WPCF7_Submission') || !method_exists('WPCF7_Submission', 'get_contact_form')
    || !method_exists('WPCF7_Submission', 'get_posted_data')) { throw new RuntimeException('CF7 API changed'); }
$checks++;
$form = new class { public function id() { return 1002; } };
$submission = new class($form) {
    private $form;
    public $name = 'www.example.invalid';
    public function __construct($form) { $this->form = $form; }
    public function get_contact_form() { return $this->form; }
    public function get_posted_data() { return array('your-name' => $this->name); }
};
if (harmat_offer_antispam_cf7_spam(false, $submission) !== true) { throw new RuntimeException('CF7 runtime rejection failed'); }
$checks++;
$submission->name = 'John Smith';
if (harmat_offer_antispam_cf7_spam(false, $submission) !== false) { throw new RuntimeException('CF7 runtime normal-name regression'); }
$checks++;
echo wp_json_encode(array('checks' => $checks, 'status' => 'PASS', 'scope' => 'PURE_CALLBACKS_NO_REST_DISPATCH_NO_MAIL_NO_RECORD_WRITE'));
'''
SPEC = importlib.util.spec_from_file_location("harmat_prior_guarded_deploy", ROOT / "server-config/maintenance/2026-10-14-deploy-appointment-source.py")
HELPER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(HELPER)


def require(value, message):
    if not value:
        raise RuntimeError(message)


def sha(value):
    return hashlib.sha256(value).hexdigest()


def absent(remote, path):
    try:
        remote.info(path)
    except FileNotFoundError:
        return True
    return False


def critical(remote):
    result = {path: sha(remote.read(path)) for path in CRITICAL}
    require(result == CRITICAL, "Existing sales/integrity source changed; stop without overwriting")
    return result


def protected_records(remote):
    prefix = remote.run(HELPER.WP + " config get table_prefix --type=variable")
    require(re.fullmatch(r"[A-Za-z0-9_]+", prefix), "Unexpected database prefix")
    posts, meta = prefix + "posts", prefix + "postmeta"
    condition = "(p.post_type='property' OR (p.post_type='harmat_offer_lead' AND p.post_date<'2026-10-15 00:00:00'))"
    queries = {
        "posts": f"SELECT p.ID,SHA2(CONCAT_WS('|',HEX(p.post_type),HEX(p.post_status),HEX(p.post_title),HEX(p.post_content),HEX(p.post_modified)),256) FROM {posts} p WHERE {condition} ORDER BY p.ID",
        "metadata": f"SELECT m.meta_id,m.post_id,SHA2(CONCAT_WS('|',HEX(m.meta_key),HEX(m.meta_value)),256) FROM {meta} m JOIN {posts} p ON p.ID=m.post_id WHERE {condition} AND m.meta_key<>'_elementor_element_cache' ORDER BY m.meta_id",
    }
    return {label: sha(remote.run(HELPER.WP + " db query " + shlex.quote(sql) + " --skip-column-names").encode()) for label, sql in queries.items()}


def smoke():
    result = []
    for path in ("/", "/lakaskereso/", "/property/a1-1-l2/", "/virtualis-lakasvalaszto/", "/virtualis-lakasvalaszto-elso-utem/", "/galeria/", "/elerhetosegeink/", "/epitesi-naplo/", "/koszonjuk/"):
        request = urllib.request.Request("https://harmat22.hu" + path, headers={"User-Agent": "HarmatOfferSafety/1.0"})
        with urllib.request.urlopen(request, timeout=30) as response:
            body, status = response.read(), response.status
            html = "text/html" in response.headers.get("Content-Type", "")
        fatal = any(marker in body.lower() for marker in (b"<b>fatal error</b>", b"<b>parse error</b>", b"there has been a critical error on this website"))
        require(status == 200 and html and len(body) > 2048 and not fatal, "Public health failed: " + path)
        result.append({"path": path, "status": status, "bytes": len(body), "fatal": fatal})
    return result


def valid_backup(path):
    require(re.fullmatch(re.escape(PREFIX) + r"[a-f0-9]{32}", path), "Unexpected private backup path")
    return path


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--check", action="store_true")
    mode.add_argument("--deploy", action="store_true")
    mode.add_argument("--verify", metavar="BACKUP")
    mode.add_argument("--rollback", metavar="BACKUP")
    parser.add_argument("--allow-live-write", action="store_true")
    parser.add_argument("--expected-sha256")
    args = parser.parse_args()
    candidate = (ROOT / SOURCE).read_bytes().replace(b"\r\n", b"\n")
    tests = (ROOT / TEST).read_bytes().replace(b"\r\n", b"\n")
    digest = sha(candidate)
    require(candidate.startswith(b"<?php\n") and b"\r" not in candidate, "Unexpected PHP encoding")
    if args.check:
        print(json.dumps({"mode": "OFFLINE_ONLY", "candidate_sha256": digest, "test_sha256": sha(tests)}))
        return
    if args.deploy or args.rollback:
        require(args.allow_live_write, "Explicit live-write switch required")
    if args.deploy:
        head = subprocess.check_output(["git", "rev-parse", "--short=7", "HEAD"], cwd=ROOT).decode().strip()
        require(head == BASE, "Reviewed Git baseline changed")
        require(args.expected_sha256 == digest and re.fullmatch(r"[a-f0-9]{64}", digest), "Reviewed candidate hash required")
    remote = HELPER.Remote()
    activated = False
    locked = False
    stage = None
    backup = None
    try:
        remote.directory(LIVE + "/wp-content/mu-plugins")
        remote.directory("/home/harmath2/codex-backups")
        if args.verify or args.rollback:
            backup = valid_backup(args.verify or args.rollback)
            manifest = json.loads(remote.read(backup + "/manifest.json", private=True))
            require(manifest["target"] == TARGET and manifest["original_absent"] is True, "Invalid rollback identity")
            require(sha(remote.read(TARGET)) == manifest["released_sha256"], "Formal file is not owned by this release")
            critical(remote)
            if args.rollback:
                remote.sftp.remove(TARGET)
                remote.purge()
                require(absent(remote, TARGET), "Rollback removal not confirmed")
                print(json.dumps({"status": "ROLLED_BACK_NEW_FILE_ONLY", "backup": backup, "smoke": smoke()}))
            else:
                remote.lint(TARGET)
                runtime = json.loads(remote.run(HELPER.WP + " eval " + shlex.quote(RUNTIME_CHECK)))
                require(runtime.get("status") == "PASS" and runtime.get("checks") == 17, "Runtime callback regression failed")
                require(protected_records(remote) == manifest["protected_before"], "Protected records changed since release")
                print(json.dumps({"status": "RELEASE_HASH_LINT_AND_NATIVE_CALLBACKS_VERIFIED", "backup": backup, "runtime": runtime, "protected_records_unchanged": True}))
            return

        require(absent(remote, TARGET), "New plugin already exists; do not overwrite or redeploy")
        critical(remote)
        before = protected_records(remote)
        baseline_smoke = smoke()
        remote.mkdir(LOCK)
        locked = True
        token = uuid.uuid4().hex
        backup = PREFIX + token
        remote.mkdir(backup)
        for index, path in enumerate(CRITICAL):
            remote.write_new(backup + f"/protected-source-{index}.php.txt", remote.read(path))
        remote.write_new(backup + "/release.php.txt", candidate)
        remote.write_new(backup + "/test.php.txt", tests)
        manifest = {"utc": datetime.now(timezone.utc).isoformat(), "target": TARGET,
                    "original_absent": True, "released_sha256": digest,
                    "test_sha256": sha(tests), "critical_sources": CRITICAL,
                    "protected_before": before, "baseline_smoke": baseline_smoke}
        remote.write_new(backup + "/manifest.json", json.dumps(manifest, indent=2).encode())
        print(json.dumps({"status": "BACKUP_VERIFIED_BEFORE_ACTIVATION", "backup": backup}), flush=True)
        remote.lint(backup + "/release.php.txt")
        remote.lint(backup + "/test.php.txt")
        test_result = remote.run(HELPER.PHP + " " + shlex.quote(backup + "/test.php.txt") + " " + shlex.quote(backup + "/release.php.txt"))
        require("PASS" in test_result, "Isolated PHP regression suite not confirmed")
        stage = TARGET.rsplit("/", 1)[0] + "/.offer-antispam-" + token + ".stage"
        remote.write_new(stage, candidate)
        remote.lint(stage)
        remote.stage_metadata(stage, (0o644, 1608, 1611))
        require(absent(remote, TARGET), "Formal path changed before activation")
        require(sha(remote.read(stage)) == digest, "Staged source changed")
        remote.sftp.rename(stage, TARGET)
        activated = True
        remote.lint(TARGET)
        require(sha(remote.read(TARGET)) == digest, "Formal source hash differs")
        remote.purge()
        after_smoke = smoke()
        critical(remote)
        after = protected_records(remote)
        require(before == after, "Protected pre-existing customer/property data changed; inspect without restoration")
        result = {"status": "DEPLOYED_AND_VERIFIED", "backup": backup,
                  "released_sha256": digest, "isolated_tests": test_result,
                  "smoke": after_smoke, "protected_records_unchanged": True,
                  "scope": "NEW_MU_ONLY_NO_INQUIRY_NO_EMAIL_NO_IP_BLOCK_NO_RECORD_DELETION"}
        remote.write_new(backup + "/result.json", json.dumps(result, indent=2).encode())
        output = ROOT / "outputs/2026-10-15-offer-spam"
        output.mkdir(parents=True, exist_ok=True)
        with (output / ("release-" + token + ".json")).open("x", encoding="utf-8") as handle:
            json.dump(result, handle, indent=2)
        print(json.dumps(result, indent=2))
    except BaseException:
        if activated:
            require(sha(remote.read(TARGET)) == digest, "Rollback stopped: released target was modified externally")
            remote.sftp.remove(TARGET)
            remote.purge()
            require(absent(remote, TARGET), "Automatic rollback could not be confirmed")
            print(json.dumps({"status": "AUTOMATICALLY_ROLLED_BACK_NEW_FILE_ONLY", "backup": backup}), flush=True)
        raise
    finally:
        if stage is not None and not absent(remote, stage):
            remote.remove_owned(stage, candidate)
        if locked:
            remote.rmdir(LOCK)
        remote.close()


if __name__ == "__main__":
    main()
