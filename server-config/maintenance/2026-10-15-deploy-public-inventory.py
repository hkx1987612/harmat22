"""Approved GET-only public feed: guarded new-file deploy and owned-file rollback.

No inquiry submission, mail, CRM/property write, Messenger grant or model call.
Only aggregate protected-record hashes leave the production database host.
"""
import argparse
import hashlib
import importlib.util
import json
import re
import shlex
import subprocess
import urllib.error
import urllib.request
import uuid
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SOURCE = "wp-mu-plugins/zz-harmat-messenger-public-inventory.php"
TEST = "server-config/maintenance/2026-10-15-test-messenger-public-inventory.php"
LIVE = "/home/harmath2/public_html"
TARGET = LIVE + "/wp-content/mu-plugins/zz-harmat-messenger-public-inventory.php"
PREFIX = "/home/harmath2/codex-backups/messenger-public-inventory-"
LOCK = "/home/harmath2/codex-backups/.messenger-public-inventory.lock"
URL = "https://harmat22.hu/wp-json/harmat-public/v1/apartments"
CRITICAL = {
    LIVE + "/wp-content/mu-plugins/zz-harmat-public-offer-integrity.php": "9c5864a6a38581d06f0f82bf64ec3142b014f0443cab16f7c9a01ff4ebc33ed3",
    LIVE + "/wp-content/plugins/harmat-sales-manager/harmat-sales-manager.php": "e2339891f8aaafe8eddf3315851479b65c866bb7c72cef6a79e1cfb59a2080cf",
    LIVE + "/wp-content/mu-plugins/zz-harmat-public-offer-antispam.php": "618fbe18387fa998b4b7bb2739bb58551d2b4c42fc96cc40478941a882682e2e",
    LIVE + "/wp-content/mu-plugins/zz-harmat-search-ai-discovery.php": "7a794651698b9af76c911e83700697753f8cc3263c6d307891db315d0026ebfa",
}
FIELDS = ["code", "url", "building", "floor", "rooms", "sales_area_m2", "outdoor_area_m2", "status", "price_huf"]
SNAPSHOT = r'''
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('CLI only'); }
global $wpdb;
$posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type IN ('property','harmat_offer_lead') ORDER BY ID", ARRAY_A);
if ($wpdb->last_error !== '') { throw new RuntimeException('Protected post read failed'); }
$meta = $wpdb->get_results("SELECT m.* FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type IN ('property','harmat_offer_lead') AND m.meta_key<>'_elementor_element_cache' ORDER BY m.meta_id", ARRAY_A);
if ($wpdb->last_error !== '') { throw new RuntimeException('Protected metadata read failed'); }
$options = $wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ('harmat_sales_deals_v1','harmat_broker_leads_v1','harmat_sales_activity_log_v1','harmat_sales_trash_v1') ORDER BY option_name", ARRAY_A);
if ($wpdb->last_error !== '') { throw new RuntimeException('Protected option read failed'); }
echo wp_json_encode(array('posts'=>hash('sha256',serialize($posts)), 'metadata'=>hash('sha256',serialize($meta)),
    'crm_options'=>hash('sha256',serialize($options)), 'post_count'=>count($posts), 'metadata_count'=>count($meta)));
'''
RUNTIME = r'''
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('CLI only'); }
$routes = rest_get_server()->get_routes();
$handlers = $routes['/harmat-public/v1/apartments'] ?? array();
$found = false;
foreach ($handlers as $handler) {
    if (!is_array($handler) || !isset($handler['callback'])) { continue; }
    if ($handler['callback'] !== 'harmat_mpi_apartments' || $handler['permission_callback'] !== '__return_true'
        || empty($handler['methods']['GET']) || array_diff(array_keys(array_filter($handler['methods'])), array('GET','HEAD'))) {
        throw new RuntimeException('Public route is not the exact read-only handler');
    }
    $found = true;
}
if (!$found) { throw new RuntimeException('Read-only handler missing'); }
$response = harmat_mpi_apartments();
if (!($response instanceof WP_REST_Response) || $response->get_status() !== 200) { throw new RuntimeException('Inventory unavailable'); }
$data = $response->get_data();
if (count($data['items']) !== 124) { throw new RuntimeException('Inventory count changed'); }
echo wp_json_encode(array('status'=>'PASS', 'registered_get_only'=>true, 'items'=>count($data['items']),
    'payload_sha256'=>hash('sha256',wp_json_encode($data['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))));
'''


def module(name, filename):
    spec = importlib.util.spec_from_file_location(name, ROOT / "server-config/maintenance" / filename)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result


HELPER = module("inventory_transport", "2026-10-14-deploy-appointment-source.py")
HEALTH = module("inventory_health", "2026-10-15-deploy-offer-antispam.py")


def require(value, message):
    if not value:
        raise RuntimeError(message)


def sha(data):
    return hashlib.sha256(data).hexdigest()


def absent(remote, path):
    try:
        remote.info(path)
    except FileNotFoundError:
        return True
    return False


def critical(remote):
    actual = {path: sha(remote.read(path)) for path in CRITICAL}
    require(actual == CRITICAL, "Existing source drift; do not overwrite")
    return actual


def protected(remote):
    return json.loads(remote.run(HELPER.WP + " eval " + shlex.quote(SNAPSHOT)))


def public_check(remote):
    request = urllib.request.Request(URL, headers={"User-Agent": "HarmatPublicInventorySafety/1.0"})
    with urllib.request.urlopen(request, timeout=30) as response:
        require(response.status == 200 and response.geturl() == URL, "Public feed HTTP identity/status mismatch")
        require("application/json" in response.headers.get("Content-Type", ""), "Feed is not JSON")
        cache = response.headers.get("Cache-Control", "")
        require("max-age=60" in cache and "must-revalidate" in cache, "Feed cache policy changed")
        require("noindex" in response.headers.get("X-Robots-Tag", ""), "Feed must not be a search landing page")
        body = response.read(1024 * 1024 + 1)
    require(len(body) <= 1024 * 1024, "Feed size exceeds safety bound")
    data = json.loads(body)
    require(list(data) == ["schema_version", "generated_at", "source", "currency", "items"], "Top-level field leakage")
    require(data["schema_version"] == 1 and data["currency"] == "HUF" and data["source"] == "https://harmat22.hu/lakaskereso/", "Public feed identity mismatch")
    age = (datetime.now(timezone.utc) - datetime.fromisoformat(data["generated_at"].replace("Z", "+00:00"))).total_seconds()
    require(-60 < age < 180 and len(data["items"]) == 124, "Public inventory stale or incomplete")
    codes = set()
    statuses = {}
    hidden = 0
    for item in data["items"]:
        require(list(item) == FIELDS, "Item field leakage")
        code = item["code"]
        require(re.fullmatch(r"A[1-4]-(F|[1-9])-L[0-9]+", code) and code not in codes, "Invalid/duplicate unit")
        codes.add(code)
        require(item["url"] == "https://harmat22.hu/property/" + code.lower() + "/", "Noncanonical public unit URL")
        require(item["building"] == code.split("-")[0] and item["floor"] == code.split("-")[1], "Building/floor mismatch")
        require(item["status"] in ("current", "reserved", "sold"), "Private/unknown unit status")
        statuses[item["status"]] = statuses.get(item["status"], 0) + 1
        for field in ("rooms", "sales_area_m2", "outdoor_area_m2"):
            require(type(item[field]) in (int, float) and item[field] >= 0, "Invalid public numeric field")
        require(item["rooms"] > 0 and item["sales_area_m2"] > 0, "Empty public area/room value")
        require(item["price_huf"] is None or type(item["price_huf"]) is int and item["price_huf"] > 0, "Invalid public price")
        hidden += item["price_huf"] is None
    require(any(row["code"] == "A3-4-L5" and abs(row["sales_area_m2"] - 47.83) < 0.00001 for row in data["items"]), "Protected public area regression")
    native = json.loads(remote.run(HELPER.WP + " eval " + shlex.quote(RUNTIME)))
    payload = json.dumps(data["items"], ensure_ascii=False, separators=(",", ":")).encode()
    require(native["status"] == "PASS" and native["payload_sha256"] == sha(payload), "Anonymous feed differs from current public authority")
    request = urllib.request.Request(URL, data=b"", method="POST", headers={"User-Agent": "HarmatPublicInventorySafety/1.0"})
    try:
        with urllib.request.urlopen(request, timeout=30):
            raise RuntimeError("Read-only feed unexpectedly accepts POST")
    except urllib.error.HTTPError as error:
        post_status = error.code
        require(post_status in (403, 404, 405), "Unexpected POST response")
        post_boundary = "UPSTREAM_OR_PERMISSION_DENIAL_NOT_HANDLER_PROOF" if post_status == 403 else "NO_WRITE_ROUTE"
    return {"status": "PASS", "items": 124, "fields_per_item": len(FIELDS), "bytes": len(body),
            "status_counts": statuses, "hidden_price_rows": hidden, "freshness_seconds": round(age, 2),
            "native_public_authority_match": True, "get_only_registration": True,
            "post_status": post_status, "post_boundary": post_boundary, "noindex": True}


def backup_path(value):
    require(re.fullmatch(re.escape(PREFIX) + r"[a-f0-9]{32}", value), "Unexpected private backup path")
    return value


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
    authority = (ROOT / "wp-mu-plugins/zz-harmat-search-ai-discovery.php").read_bytes().replace(b"\r\n", b"\n")
    digest = sha(candidate)
    require(candidate.startswith(b"<?php\n") and b"\r" not in candidate, "Unexpected candidate encoding")
    if args.check:
        print(json.dumps({"mode": "OFFLINE_ONLY", "candidate_sha256": digest, "test_sha256": sha(tests)}))
        return
    if args.deploy or args.rollback:
        require(args.allow_live_write, "Explicit live-write switch required")
    if args.deploy:
        require(subprocess.check_output(["git", "rev-parse", "--short=7", "HEAD"], cwd=ROOT).decode().strip() == "c78e86d", "Reviewed Git baseline changed")
        require(args.expected_sha256 == digest, "Reviewed source hash required")
    remote = HELPER.Remote()
    stage = None
    activated = locked = False
    backup = None
    try:
        remote.directory(LIVE + "/wp-content/mu-plugins")
        remote.directory(HELPER.BACKUPS)
        critical(remote)
        if args.verify or args.rollback:
            backup = backup_path(args.verify or args.rollback)
            remote.directory(backup, private=True)
            manifest = json.loads(remote.read(backup + "/manifest.json", private=True))
            require(manifest["target"] == TARGET and manifest["original_absent"] is True, "Wrong backup target")
            require(sha(remote.read(TARGET)) == manifest["released_sha256"], "Release file changed; do not remove")
            if args.rollback:
                remote.sftp.remove(TARGET)
                remote.purge()
                require(absent(remote, TARGET), "Owned-file rollback not confirmed")
                print(json.dumps({"status": "ROLLED_BACK_NEW_FILE_ONLY", "backup": backup, "smoke": HEALTH.smoke()}))
            else:
                remote.lint(TARGET)
                checks = public_check(remote)
                require(protected(remote) == manifest["protected_before"], "Protected records changed; investigate, never restore automatically")
                print(json.dumps({"status": "RELEASE_VERIFIED", "public": checks, "protected_records_unchanged": True}))
            return
        require(absent(remote, TARGET), "New MU target already exists; never overwrite")
        baseline = HEALTH.smoke()
        before = protected(remote)
        remote.mkdir(LOCK)
        locked = True
        token = uuid.uuid4().hex
        backup = PREFIX + token
        remote.mkdir(backup)
        for index, path in enumerate(CRITICAL):
            remote.write_new(backup + f"/protected-source-{index}.php.txt", remote.read(path))
        for name, content in (("release.php.txt", candidate), ("test.php.txt", tests), ("authority.php.txt", authority)):
            remote.write_new(backup + "/" + name, content)
            remote.lint(backup + "/" + name)
        require(sha(authority) == CRITICAL[LIVE + "/wp-content/mu-plugins/zz-harmat-search-ai-discovery.php"], "Local public authority drift")
        isolated = remote.run(HELPER.PHP + " " + shlex.quote(backup + "/test.php.txt") + " " + shlex.quote(backup + "/release.php.txt") + " " + shlex.quote(backup + "/authority.php.txt"))
        require("PASS: 61 checks" in isolated, "Production-runtime isolated fixtures failed")
        manifest = {"utc": datetime.now(timezone.utc).isoformat(), "target": TARGET, "original_absent": True,
                    "released_sha256": digest, "test_sha256": sha(tests), "critical_sources": CRITICAL,
                    "protected_before": before, "baseline_smoke": baseline, "isolated": isolated}
        remote.write_new(backup + "/manifest.json", json.dumps(manifest, indent=2).encode())
        print(json.dumps({"status": "BACKUP_VERIFIED_BEFORE_ACTIVATION", "backup": backup}), flush=True)
        stage = TARGET.rsplit("/", 1)[0] + "/.messenger-inventory-" + token + ".stage"
        remote.write_new(stage, candidate)
        remote.lint(stage)
        remote.stage_metadata(stage, (0o644, 1608, 1611))
        critical(remote)
        require(protected(remote) == before, "Protected data changed before activation; stop")
        require(absent(remote, TARGET) and sha(remote.read(stage)) == digest, "New target/stage drift")
        remote.sftp.rename(stage, TARGET)
        activated = True
        remote.lint(TARGET)
        require(sha(remote.read(TARGET)) == digest, "Formal PHP source mismatch")
        remote.purge()
        checks = public_check(remote)
        health = HEALTH.smoke()
        critical(remote)
        require(protected(remote) == before, "Protected data changed; no automatic customer restoration")
        result = {"status": "DEPLOYED_AND_VERIFIED", "backup": backup, "released_sha256": digest,
                  "isolated": isolated, "public": checks, "smoke": health, "protected_records_unchanged": True,
                  "scope": "NEW_GET_ONLY_MU_NO_FRONTEND_CRM_MAIL_MESSENGER_OR_MODEL_CHANGE"}
        remote.write_new(backup + "/result.json", json.dumps(result, indent=2).encode())
        output = ROOT / "outputs/2026-10-15-facebook-messenger"
        output.mkdir(parents=True, exist_ok=True)
        with (output / ("inventory-release-" + token + ".json")).open("x", encoding="utf-8") as handle:
            json.dump(result, handle, indent=2)
        print(json.dumps(result, indent=2))
    except BaseException:
        if activated:
            require(sha(remote.read(TARGET)) == digest, "Automatic rollback stopped: released file changed externally")
            remote.sftp.remove(TARGET)
            remote.purge()
            require(absent(remote, TARGET), "Automatic owned-file rollback unconfirmed")
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
