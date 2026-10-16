"""Independent release readback plus pure-filter QA. No customer form is sent.

--run permits staging one private CLI-only fixture in the existing HTTPS backup.
All public requests are bounded and use the existing identifiable safety agent.
"""
import argparse
import copy
import importlib.util
import json
from pathlib import Path
import shlex

SPEC = importlib.util.spec_from_file_location(
    "release", Path(__file__).with_name("2026-10-16-deploy-area-https.py"))
R = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(R)
D = R.D
AREA_BACKUP = R.PREFIX + "0ead5ccff1d743f8862bb0e1c1402450"
HTTPS_BACKUP = R.PREFIX + "34b66ba97fff461b8e583e7c9e884d69"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--run", action="store_true")
    args = parser.parse_args()
    if not args.run:
        print("Offline only. Explicit --run required for release readback.")
        return
    remote = D.HELPER.Remote()
    stage = None
    try:
        area, area_original = R.read_manifest(remote, AREA_BACKUP)
        https, https_original = R.read_manifest(remote, HTTPS_BACKUP)
        released_area = remote.read(R.AREA)
        released_rules = remote.read(R.HTACCESS)
        D.require(D.sha(released_area) == area["released_sha256"]
                  and D.sha(released_rules) == https["released_sha256"], "Production release ownership changed")
        D.require(released_area.count(R.BLOCK.replace(b"\n", b"\r\n")) == 1
                  and released_area.replace(R.BLOCK.replace(b"\n", b"\r\n"), b"", 1) == area_original,
                  "Hero changed outside the single approved insertion")
        rules = (R.ROOT / R.RULE).read_bytes().replace(b"\r\n", b"\n")
        D.require(released_rules.count(rules) == 1 and released_rules.replace(rules + b"\n", b"", 1) == https_original,
                  "Existing root security/WordPress/cache rules changed")
        before = R.snapshot(remote)
        expected = copy.deepcopy(area["before"])
        expected["other_sources"]["migratedSnippets"] = area["released_sha256"]
        D.require(before == expected == https["before"], "Protected business/page/source readback drift")
        remote.lint(R.AREA)
        fixture = Path(__file__).with_name("2026-10-16-native-offer-readonly.php").read_bytes().replace(b"\r\n", b"\n")
        stage = HTTPS_BACKUP + "/offer-readonly.php"
        try:
            existing = remote.read(stage, private=True)
        except FileNotFoundError:
            remote.write_new(stage, fixture)
        else:
            D.require(existing == fixture, "Private fixture changed; do not overwrite")
        remote.lint(stage)
        native = json.loads(remote.run(D.HELPER.WP + " eval-file " + shlex.quote(stage)))
        D.require(native.get("status") == "PASS" and native.get("checks") == 35, "Native offer read-only validation failed")
        checks = R.https_public()
        feed_path = "/wp-json/harmat-public/v1/apartments"
        for scheme in ("http", "https"):
            feed = R.request(scheme + "://harmat22.hu" + feed_path)
            no_write = R.request(scheme + "://harmat22.hu" + feed_path, method="POST")
            D.require(feed["status"] == 200 and feed["location"] is None and len(json.loads(feed["body"])["items"]) == 124,
                      "Existing public read API was redirected/changed")
            D.require(no_write["status"] == 404 and no_write["location"] is None,
                      "Read-only API POST boundary was changed")
        D.require(R.snapshot(remote) == before, "QA unexpectedly changed protected state")
        result = {"status": "INDEPENDENT_PASS", "area": R.area_public(), "https": checks,
                  "pure_offer_validation": native, "existing_security_and_source_bytes_preserved": True,
                  "protected_business_pages_sources_unchanged": True,
                  "public_read_api_http_https": 200, "public_read_api_post_http_https": 404}
        path = HTTPS_BACKUP + "/independent-readback.json"
        try:
            existing = remote.read(path, private=True)
        except FileNotFoundError:
            remote.write_new(path, D.encoded(result))
        else:
            D.require(existing == D.encoded(result), "Earlier independent evidence differs; do not overwrite")
        print(json.dumps(result, sort_keys=True))
    finally:
        remote.close()


if __name__ == "__main__":
    main()
