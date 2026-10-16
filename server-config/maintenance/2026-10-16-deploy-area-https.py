"""Two separately backed-up changes; default is offline. No positive form sent.

Area release preserves the live file's mixed line endings and only inserts the
reviewed six-line block. HTTPS release preserves every existing .htaccess byte.
Rollback only replaces the exact owned source/configuration, never database data.
"""
import argparse
import copy
from datetime import datetime, timezone
from html.parser import HTMLParser
import importlib.util
import json
from pathlib import Path
import re
import shlex
import stat
import subprocess
import urllib.error
import urllib.request
import uuid

SPEC = importlib.util.spec_from_file_location(
    "trust", Path(__file__).with_name("2026-10-16-deploy-public-trust.py"))
D = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(D)
ROOT = D.ROOT
BASE = "4006df9819084369ca5ea7982ea09da1fe6baf8f"
SOURCE = "wp-mu-plugins/harmat-migrated-snippets.php"
AREA = D.DIRECTORY + "/harmat-migrated-snippets.php"
HTACCESS = D.HELPER.LIVE + "/.htaccess"
AREA_SHA = "508c7ec6d9f41559045de83d70070b145274cc27f20089aa81ad1483b756d70e"
HTACCESS_SHA = "527e57484be628c427f62b7872cb1529af359d219e4433001899385db4a24511"
PREFIX = D.HELPER.BACKUPS + "/area-https-"
LOCK = D.HELPER.BACKUPS + "/.area-https.lock"
BLOCK = b"""    if ($post_id === 3841 && $title === 'A1-F-L1') {
        $sales_area = get_post_meta($post_id, '_harmat_sales_area', true);
        if (is_scalar($sales_area) && !is_bool($sales_area) && is_numeric($sales_area) && is_finite((float) $sales_area) && (float) $sales_area > 0) {
            $sale_area = (float) $sales_area;
        }
    }
"""
ANCHOR = b"    $sale_area = $sale_area > 0 ? floor($sale_area * 100) / 100 : 0;"
TEST = "server-config/maintenance/2026-10-16-test-a1-f-l1-hero.php"
NATIVE = "server-config/maintenance/2026-10-16-native-a1-f-l1-hero.php"
RULE = "server-config/public-https.htaccess"


def bundle():
    paths = (SOURCE, TEST, NATIVE, RULE)
    blobs = {name: (ROOT / name).read_bytes().replace(b"\r\n", b"\n") for name in paths}
    baseline = subprocess.check_output(["git", "show", BASE + ":" + SOURCE], cwd=ROOT).replace(b"\r\n", b"\n")
    D.require(blobs[SOURCE].count(BLOCK) == 1 and blobs[SOURCE].replace(BLOCK, b"", 1) == baseline,
              "Only the reviewed six-line hero insertion is allowed")
    D.require(blobs[RULE].startswith(b"# BEGIN Harmat public HTTPS\n")
              and blobs[RULE].endswith(b"# END Harmat public HTTPS\n"), "Rule markers changed")
    rows = {name: D.sha(data) for name, data in blobs.items()}
    descriptor = {"baseline": BASE, "files": rows}
    return {**descriptor, "combined_sha256": D.sha(D.encoded(descriptor))}, blobs


def request(url, method="GET", headers=None):
    req = urllib.request.Request(url, method=method, headers={"User-Agent": D.AGENT, **(headers or {})})
    try:
        response = urllib.request.build_opener(D.NoRedirect()).open(req, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        D.require(response.code != 403, "HTTP403: stop without fallback or identity change")
        body = response.read(D.LIMIT + 1)
        D.require(len(body) <= D.LIMIT, "Public response size exceeded")
        return {"status": response.code, "location": response.headers.get("Location"), "body": body,
                "cache_control": response.headers.get("Cache-Control")}


class Hero(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.depth = 0
        self.active = False
        self.dd = False
        self.values = []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "section" and "harmat-property-hero" in attrs.get("class", "").split():
            self.active = True
            self.depth = 1
        elif self.active and tag == "section":
            self.depth += 1
        if self.active and tag == "dd":
            self.dd = True
            self.values.append("")

    def handle_endtag(self, tag):
        if tag == "dd":
            self.dd = False
        if self.active and tag == "section":
            self.depth -= 1
            if self.depth == 0:
                self.active = False

    def handle_data(self, data):
        if self.active and self.dd:
            self.values[-1] += data


def area_public():
    result = request(D.ORIGIN + "/property/a1-f-l1/")
    D.require(result["status"] == 200, "Target property must remain HTTP200 on HTTPS")
    parser = Hero()
    parser.feed(result["body"].decode("utf-8", "strict"))
    values = [value.replace("\xa0", " ").strip() for value in parser.values]
    D.require(len(values) == 9 and values[1] == "84 342 300 Ft" and values[2] == "60,10 m\u00b2"
              and values[3] == "1 403 366 Ft / m\u00b2" and values[7] == "57,05 m\u00b2"
              and values[8] == "64,05 m\u00b2", "Target public hero authority regression")
    return {"status": 200, "sales_area": values[2], "base_area": values[7], "price": values[1], "unit_price": values[3]}


def https_public():
    results = []
    for path in ("/", D.PRIVACY, "/lakaskereso/?rooms=3&utm_source=https-safety&utm_medium=check", "/property/a1-f-l1/", "/galeria/"):
        for method in ("GET", "HEAD"):
            old = request("http://harmat22.hu" + path, method)
            new = request(D.ORIGIN + path, method)
            D.require(old["status"] == 301 and old["location"] == D.ORIGIN + path,
                      "HTTP page must redirect once, retaining exact path/query: " + path)
            D.require(new["status"] == 200 and new["location"] is None, "HTTPS loop/status failure: " + path)
            results.append({"path": path, "method": method, "http": 301, "https": 200})
    return results


def snapshot(remote):
    value = D.snapshot(remote)
    value["trust_copy"] = D.sha(remote.read(D.DIRECTORY + "/zz-harmat-public-trust-copy.php"))
    D.require(value["trust_copy"] == "464d892b998fdbea469584cbcbb2e0d9defc380d3f343e394483f97383ccf8a3", "Prior public trust release drift")
    return value


def patch(kind, original, blobs):
    if kind == "area":
        D.require(D.sha(original) == AREA_SHA, "Raw live hero baseline changed")
        normalized = original.replace(b"\r\n", b"\n")
        D.require(blobs[SOURCE].replace(BLOCK, b"", 1) == normalized, "Live/Git source parity failed")
        needle = ANCHOR + b"\r\n"
        D.require(original.count(needle) == 2, "Expected two legacy area formulas")
        # The first formula belongs to sample detail; only hero (second) changes.
        index = original.rfind(needle) + len(needle)
        candidate = original[:index] + BLOCK.replace(b"\n", b"\r\n") + original[index:]
        D.require(candidate.replace(b"\r\n", b"\n") == blobs[SOURCE], "Exact raw insertion differs from reviewed Git candidate")
        return candidate
    D.require(D.sha(original) == HTACCESS_SHA and b"# BEGIN Harmat public HTTPS" not in original, "Root rule baseline changed")
    marker = b"# BEGIN WordPress\n"
    D.require(original.count(marker) == 1, "WordPress boundary not unique")
    return original.replace(marker, blobs[RULE] + b"\n" + marker, 1)


def target_info(remote, target):
    info = remote.info(target)
    D.require(stat.S_ISREG(info.st_mode), "Target must be a non-symlink regular file")
    return (stat.S_IMODE(info.st_mode), info.st_uid, info.st_gid)


def atomic(remote, target, expected, candidate, metadata, php):
    stage = target.rsplit("/", 1)[0] + "/.harmat-area-https-" + uuid.uuid4().hex + ".tmp"
    try:
        remote.write_new(stage, candidate)
        if php:
            remote.lint(stage)
        remote.stage_metadata(stage, metadata)
        D.require(D.sha(remote.read(target)) == expected and target_info(remote, target) == metadata, "Target drift before atomic activation")
        D.require(remote.read(stage) == candidate, "Staged bytes changed")
        remote.sftp.posix_rename(stage, target)
        D.require(remote.read(target) == candidate and target_info(remote, target) == metadata, "Formal bytes/metadata not confirmed")
        if php:
            remote.lint(target)
    finally:
        remote.remove_owned(stage, candidate)


def read_manifest(remote, backup):
    D.require(re.fullmatch(re.escape(PREFIX) + "[0-9a-f]{32}", backup), "Exact private backup required")
    remote.directory(backup, private=True)
    value = json.loads(remote.read(backup + "/manifest.json", private=True))
    D.require(value["kind"] in ("area", "https") and value["target"] == (AREA if value["kind"] == "area" else HTACCESS), "Backup identity mismatch")
    original = remote.read(backup + "/original.php" if value["kind"] == "area" else backup + "/original.htaccess", private=True)
    D.require(D.sha(original) == value["original_sha256"], "Private original hash changed")
    return value, original


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument("--deploy-area", action="store_true")
    group.add_argument("--deploy-https", action="store_true")
    group.add_argument("--verify", metavar="BACKUP")
    group.add_argument("--rollback", metavar="BACKUP")
    parser.add_argument("--allow-live-write", action="store_true")
    parser.add_argument("--expected-sha256")
    parser.add_argument("--https-preflight-backup")
    args = parser.parse_args()
    descriptor, blobs = bundle()
    if not any((args.deploy_area, args.deploy_https, args.verify, args.rollback)):
        print(json.dumps(descriptor, sort_keys=True))
        return
    if args.deploy_area or args.deploy_https or args.rollback:
        D.require(args.allow_live_write, "Explicit live-write switch required")
    if args.deploy_area or args.deploy_https:
        D.require(D.checkout_head(ROOT) == BASE and args.expected_sha256 == descriptor["combined_sha256"], "Reviewed checkout/bundle pin required")
    remote = D.HELPER.Remote()
    backup = None
    activated = False
    locked = False
    try:
        if args.verify or args.rollback:
            backup = args.verify or args.rollback
            manifest, original = read_manifest(remote, backup)
            target, metadata = manifest["target"], tuple(manifest["metadata"])
            D.require(D.sha(remote.read(target)) == manifest["released_sha256"], "Released file no longer owned; stop")
            if args.rollback:
                atomic(remote, target, manifest["released_sha256"], original, metadata, manifest["kind"] == "area")
                remote.purge()
                print(json.dumps({"status": "ROLLED_BACK_OWNED_FILE_ONLY", "backup": backup}))
            else:
                if manifest["kind"] == "area":
                    remote.lint(target)
                result = {"status": "VERIFIED", "backup": backup, "health": D.health(remote), "area": area_public()}
                if manifest["kind"] == "https":
                    result["https"] = https_public()
                print(json.dumps(result, sort_keys=True))
            return
        kind = "area" if args.deploy_area else "https"
        if kind == "https":
            probe = args.https_preflight_backup or ""
            D.require(re.fullmatch(re.escape(D.HELPER.BACKUPS + "/https-probe-") + "[0-9a-f]{32}", probe), "Exact successful isolated HTTPS preflight required")
            remote.directory(probe, private=True)
            D.require(remote.read(probe + "/tested-public-https.htaccess", private=True) == blobs[RULE], "Tested .htaccess rule differs from release")
            proof = json.loads(remote.read(probe + "/results.json", private=True))
            D.require(len(proof) == 20 and all(row["status"] in (200, 301, 302, 405) for row in proof), "Incomplete isolated protocol/method/header proof")
        target = AREA if kind == "area" else HTACCESS
        original = remote.read(target)
        candidate = patch(kind, original, blobs)
        metadata = target_info(remote, target)
        D.require(D.HELPER.safe_public_mode(*metadata, remote.private_group_verified), "Unsafe target permissions")
        before = snapshot(remote)
        gallery_before = D.gallery_signature(D.get(D.ORIGIN + "/galeria/"), released=True)
        remote.mkdir(LOCK)
        locked = True
        backup = PREFIX + uuid.uuid4().hex
        remote.mkdir(backup)
        remote.write_new(backup + ("/original.php" if kind == "area" else "/original.htaccess"), original)
        remote.write_new(backup + ("/candidate.php" if kind == "area" else "/candidate.htaccess"), candidate)
        manifest = {"schema": 1, "kind": kind, "target": target, "metadata": list(metadata),
                    "created_utc": datetime.now(timezone.utc).isoformat(), "bundle": descriptor,
                    "original_sha256": D.sha(original), "released_sha256": D.sha(candidate),
                    "before": before, "gallery_before": gallery_before}
        remote.write_new(backup + "/manifest.json", D.encoded(manifest))
        print(json.dumps({"event": "PRIVATE_BACKUP_VERIFIED", "kind": kind, "backup": backup,
                          "original_sha256": D.sha(original), "released_sha256": D.sha(candidate)}), flush=True)
        native = None
        if kind == "area":
            remote.lint(backup + "/candidate.php")
            for name, path in (("fixture.php", TEST), ("native.php", NATIVE)):
                remote.write_new(backup + "/" + name, blobs[path])
                remote.lint(backup + "/" + name)
            fixture = remote.run(D.HELPER.PHP + " " + shlex.quote(backup + "/fixture.php") + " " + shlex.quote(backup + "/candidate.php"))
            D.require("PASS" in fixture and "1402" in fixture, "Production isolated fixture failed")
            native = json.loads(remote.run(D.HELPER.PHP + " " + shlex.quote(backup + "/native.php") + " " + shlex.quote(backup)))
            D.require(native.get("status") == "PASS" and native.get("unchanged_heroes") == 123, "Native 124-property comparison failed")
            print(json.dumps({"event": "PRODUCTION_PREFLIGHT_PASS", "fixture": fixture, "native": native}), flush=True)
        D.require(snapshot(remote) == before, "Protected state drift before activation")
        atomic(remote, target, D.sha(original), candidate, metadata, kind == "area")
        activated = True
        remote.purge()
        expected = copy.deepcopy(before)
        if kind == "area":
            expected["other_sources"]["migratedSnippets"] = D.sha(candidate)
        result = {"status": "DEPLOYED_AND_VERIFIED", "kind": kind, "backup": backup,
                  "released_sha256": D.sha(candidate), "native": native,
                  "area": area_public(), "health": D.health(remote)}
        if kind == "https":
            result["https"] = https_public()
        D.require(D.gallery_signature(D.get(D.ORIGIN + "/galeria/"), released=True) == gallery_before, "Gallery assets changed")
        D.require(snapshot(remote) == expected, "Protected state drift; never restore customer data")
        D.require(remote.read(target) == candidate, "Released bytes changed during regression")
        remote.write_new(backup + "/result.json", D.encoded(result))
        print(json.dumps(result, sort_keys=True), flush=True)
    except BaseException:
        # A failure after rename but before atomic() returns still owns the new bytes.
        owns_candidate = (backup is not None and not (args.verify or args.rollback)
                          and D.sha(remote.read(target)) == D.sha(candidate))
        if activated or owns_candidate:
            atomic(remote, target, D.sha(candidate), original, metadata, kind == "area")
            remote.purge()
            print(json.dumps({"status": "AUTOMATICALLY_ROLLED_BACK_OWNED_FILE_ONLY", "backup": backup}), flush=True)
        raise
    finally:
        try:
            if locked:
                remote.sftp.rmdir(LOCK)
        finally:
            remote.close()


if __name__ == "__main__":
    main()
