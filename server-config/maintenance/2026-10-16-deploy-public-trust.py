"""One NEW public-trust copy MU. Default/--check is strictly offline.

Parent review is mandatory. Live deployment requires the pinned checkout and
the SHA256 of the canonical combined source/test manifest printed by --check.
Recovery removes only original-absent, byte-identical owned files, never data.
HTTP privacy remains HTTP200: this copy-only release does not correct HTTPS.
"""
import argparse
from contextlib import contextmanager
import hashlib
from html import unescape
from html.parser import HTMLParser
import importlib.util
import json
from pathlib import Path
import re
import shlex
import urllib.error
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[2]
BASE = "cdaa3ec"
MAINTENANCE = "server-config/maintenance/"


def module(name, filename):
    spec = importlib.util.spec_from_file_location(name, ROOT / MAINTENANCE / filename)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result


HELPER = module("trust_transport", "2026-10-14-deploy-appointment-source.py")
INVENTORY = module("trust_inventory", "2026-10-15-deploy-public-inventory.py")
HEALTH = INVENTORY.HEALTH
DIRECTORY = HELPER.LIVE + "/wp-content/mu-plugins"
PREFIX = HELPER.BACKUPS + "/public-trust-"
LOCK = HELPER.BACKUPS + "/.public-trust.lock"
RELEASES = (
    ("wp-mu-plugins/zz-harmat-public-trust-copy.php", MAINTENANCE + "2026-10-16-test-public-trust-copy.php"),
)
TARGETS = tuple(DIRECTORY + "/" + Path(source).name for source, _ in RELEASES)
OTHER_SOURCES = {
    "galleryComfort": DIRECTORY + "/zz-harmat-gallery-comfort-layout.php",
    "migratedSnippets": DIRECTORY + "/harmat-migrated-snippets.php",
    "publicAuditPolish": DIRECTORY + "/harmat-public-audit-polish.php",
    "assistantLiveData": DIRECTORY + "/zz-harmat-assistant-live-data.php",
}
AGENT = "HarmatOfferSafety/1.0"
ORIGIN = "https://harmat22.hu"
PRIVACY = "/adatvedelmi-tajekoztato/"
LIMIT = 1024 * 1024
OLD_COPY = (
    "Csal\u00e1dbar\u00e1t otthon \u2013 CSOK ig\u00e9nyelhet\u0151",
    "CSOK - Okos beruh\u00e1z\u00e1s",
    "CSOK-bar\u00e1t lak\u00e1sokat k\u00edn\u00e1l",
)
NOTICE_MARKER = "harmat-gallery-render-notice"
NOTICE = ("A l\u00e1tv\u00e1nytervek t\u00e1j\u00e9koztat\u00f3 illusztr\u00e1ci\u00f3k; "
          "a megval\u00f3sult \u00e1llapot elt\u00e9rhet a bemutatott k\u00e9pekt\u0151l.")
NATIVE_PATH = ROOT / MAINTENANCE / "2026-10-16-native-public-trust-preflight.php"
NATIVE_SHA256 = "62c1753a9fda9ba263ce835b3c264593b13260468022b863c1030e412f5656f5"
PAGE_SNAPSHOT = r'''
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('CLI only'); }
global $wpdb;
$posts = $wpdb->get_results("SELECT ID,post_content,post_title,post_name,post_status,post_password FROM {$wpdb->posts} WHERE post_type='page' ORDER BY ID", ARRAY_A);
if ($wpdb->last_error !== '') { throw new RuntimeException('Page read failed'); }
$meta = $wpdb->get_results("SELECT m.meta_id,m.post_id,m.meta_key,m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type='page' AND m.meta_key IN ('_elementor_data','_elementor_page_settings') ORDER BY m.meta_id", ARRAY_A);
if ($wpdb->last_error !== '') { throw new RuntimeException('Page metadata read failed'); }
echo wp_json_encode(array('pages'=>hash('sha256',serialize($posts)), 'elementor'=>hash('sha256',serialize($meta))));
'''
PAGE_CACHE = r'''
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('CLI only'); }
foreach (array(164,439) as $id) {
    delete_post_meta($id, '_elementor_element_cache');
    if (metadata_exists('post', $id, '_elementor_element_cache')) { throw new RuntimeException('Element cache remains'); }
}
echo 'PUBLIC_TRUST_TWO_PAGE_CACHE_CLEARED';
'''


def require(value, message):
    if not value:
        raise RuntimeError(message)


def sha(data):
    return hashlib.sha256(data).hexdigest()


def encoded(value):
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=True).encode("ascii")


def checkout_head(root):
    """Read local Git metadata only; never invoke Git or refresh the checkout."""
    gitdir = root / ".git"
    if gitdir.is_file():
        text = gitdir.read_text(encoding="utf-8").strip()
        require(text.startswith("gitdir: "), "Invalid checkout metadata")
        gitdir = (root / text[8:]).resolve()
    head = (gitdir / "HEAD").read_text(encoding="ascii").strip()
    if head.startswith("ref: "):
        ref = head[5:]
        require(re.fullmatch(r"refs/[A-Za-z0-9_./-]+", ref) and ".." not in ref, "Invalid HEAD ref")
        common = gitdir
        if (gitdir / "commondir").is_file():
            common = (gitdir / (gitdir / "commondir").read_text().strip()).resolve()
        for directory in (gitdir, common):
            if (directory / ref).is_file():
                head = (directory / ref).read_text(encoding="ascii").strip()
                break
        else:
            packed = common / "packed-refs"
            head = next((line.split()[0] for line in packed.read_text().splitlines()
                         if not line.startswith(("#", "^")) and line.endswith(" " + ref)), "")
    require(re.fullmatch(r"[0-9a-f]{40}", head), "Invalid checkout HEAD")
    return head


def local_bundle(root=None):
    root = ROOT if root is None else root
    require(checkout_head(root).startswith(BASE), "Reviewed deployment baseline changed")
    rows, blobs = [], {}
    for (source, test), target in zip(RELEASES, TARGETS):
        for path in (source, test):
            content = (root / path).read_bytes().replace(b"\r\n", b"\n")
            require(content.startswith(b"<?php\n") and b"\r" not in content, "PHP must be UTF-8/LF: " + path)
            content.decode("utf-8", "strict")
            blobs[path] = content
        # Staging cannot use a fixture that silently requires the checkout.
        require(b"$argv[1]" in blobs[test]
                and not re.search(rb"require(?:_once)?\s+dirname\(__DIR__", blobs[test]),
                "Fixture must require candidate PHP from argv[1]: " + test)
        rows.append({"source": source, "test": test, "target": target,
                     "source_sha256": sha(blobs[source]), "test_sha256": sha(blobs[test])})
    descriptor = {"baseline": BASE, "releases": rows}
    return {**descriptor, "combined_sha256": sha(encoded(descriptor))}, blobs


def valid_backup(path):
    require(isinstance(path, str) and re.fullmatch(re.escape(PREFIX) + r"[0-9a-f]{32}", path),
            "Exact private public-trust backup required")
    return path


def pages(remote):
    result = json.loads(remote.run(HELPER.WP + " eval " + shlex.quote(PAGE_SNAPSHOT)))
    require(set(result) == {"pages", "elementor"}
            and all(re.fullmatch(r"[0-9a-f]{64}", value) for value in result.values()), "Invalid aggregate page hashes")
    return result


def snapshot(remote):
    return {"critical": INVENTORY.critical(remote),
            "other_sources": {name: sha(remote.read(path)) for name, path in OTHER_SOURCES.items()},
            "protected": INVENTORY.protected(remote), "public_pages": pages(remote)}


def unchanged(remote, before):
    require(snapshot(remote) == before, "Protected data/page/source drift; never rewind customer data or old code")


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class LimitedResponse:
    """Bound even legacy smoke().read() without changing shared helpers."""
    def __init__(self, response):
        self.response = response
        self.consumed = 0

    def __getattr__(self, name):
        return getattr(self.response, name)

    def read(self, size=-1):
        remaining = LIMIT - self.consumed
        data = self.response.read(min(size, remaining + 1) if size >= 0 else remaining + 1)
        self.consumed += len(data)
        require(self.consumed <= LIMIT, "HTTP body exceeds 1 MiB")
        return data

    def __enter__(self):
        return self

    def __exit__(self, *args):
        self.response.close()


@contextmanager
def bounded_shared_requests():
    # All inherited agents stay identifiable and unchanged. No retry on HTTP403.
    original = urllib.request.urlopen

    def bounded(request, *args, **kwargs):
        require(not args, "Unexpected HTTP positional options")
        kwargs["timeout"] = min(kwargs.get("timeout", 30), 30)
        try:
            return LimitedResponse(original(request, **kwargs))
        except urllib.error.HTTPError as error:
            error.close()
            raise

    urllib.request.urlopen = bounded
    try:
        yield
    finally:
        urllib.request.urlopen = original


def get(url):
    request = urllib.request.Request(url, headers={"User-Agent": AGENT}, method="GET")
    try:
        response = urllib.request.build_opener(NoRedirect()).open(request, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    with LimitedResponse(response) as response:
        require(response.code == 200 and response.geturl() == url,
                "Public GET identity/status mismatch: " + url + " HTTP" + str(response.code)
                + "; same_url=" + str(response.geturl() == url) + "; no fallback/retry")
        require("text/html" in response.headers.get("Content-Type", ""), "Expected public HTML")
        body = response.read().decode("utf-8", "strict")
    return body


class Gallery(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.images = []
        self.notices = 0

    def handle_starttag(self, tag, attrs):
        if any(key == "class" and NOTICE_MARKER in (value or "").split() for key, value in attrs):
            self.notices += 1
        if tag == "img":
            self.images.append(sorted((key, value) for key, value in attrs
                                      if key in ("src", "srcset", "sizes") or key.startswith("data-")))

    handle_startendtag = handle_starttag


def gallery_signature(html, released=False):
    parser = Gallery()
    parser.feed(html)
    parser.close()
    require(parser.images, "Gallery has no image baseline")
    if released:
        require(parser.notices == 1 and html.count(NOTICE_MARKER) == 1, "Gallery note marker must occur exactly once")
        require(NOTICE in unescape(html), "Exact Hungarian gallery notice missing")
    return {"img_count": len(parser.images), "source_attributes_sha256": sha(encoded(parser.images))}


def health(remote):
    with bounded_shared_requests():
        smoke = HEALTH.smoke()
        public = INVENTORY.public_check(remote)
    require(len(smoke) == 9 and public["items"] == 124 and public["post_status"] != 403,
            "Health/inventory blocked or incomplete; no fallback agent")
    native = json.loads(remote.run(HELPER.WP + " eval " + shlex.quote(HEALTH.RUNTIME_CHECK)))
    require(native.get("checks") == 17 and native.get("status") == "PASS", "Native anti-spam pure callbacks failed")
    return {"smoke": smoke, "inventory": public, "antispam": native}


def public_verify(before_gallery):
    get(ORIGIN + PRIVACY)  # No redirect following, so a loop or intermediate redirect fails.
    get("http://harmat22.hu" + PRIVACY)  # Observe unchanged HTTP200, not a redirect fix.
    home = unescape(get(ORIGIN + "/"))
    require(not any(text in home for text in OLD_COPY), "Exact old promotional homepage copy still present")
    gallery = gallery_signature(get(ORIGIN + "/galeria/"), released=True)
    require(gallery == before_gallery, "Gallery image count/source attributes changed")
    return {"privacy_https": 200, "privacy_http": 200, "http_privacy_corrected": False,
            "scope": "COPY_ONLY_HTTP_PRIVACY_REMAINS_200_NOT_CORRECTED",
            "old_promotion_absent": True, "gallery": gallery}


def purge(remote):
    require(remote.run(HELPER.WP + " eval " + shlex.quote(PAGE_CACHE))
            == "PUBLIC_TRUST_TWO_PAGE_CACHE_CLEARED", "Scoped page cache clear failed")
    remote.purge()


class Operation:
    def __init__(self, remote):
        self.remote = remote

    def save(self, backup, name, value):
        self.remote.write_new(backup + "/" + name, encoded(value) + b"\n")

    def load(self, backup):
        valid_backup(backup)
        self.remote.directory(HELPER.BACKUPS)
        self.remote.directory(backup, private=True)
        manifest = json.loads(self.remote.read(backup + "/manifest.json", private=True))
        bundle = manifest["bundle"]
        require(manifest["schema"] == 1 and manifest["backup"] == backup and bundle["baseline"] == BASE,
                "Wrong backup identity/baseline")
        require(set(manifest["original_absent"]) == set(TARGETS)
                and all(manifest["original_absent"][target] is True for target in TARGETS),
                "Backup did not prove the exact target set absent")
        rows = bundle["releases"]
        require(len(rows) == len(RELEASES) and [(row["source"], row["test"], row["target"]) for row in rows]
                == [(source, test, target) for (source, test), target in zip(RELEASES, TARGETS)], "Wrong backup target set")
        require(bundle["combined_sha256"] == sha(encoded({"baseline": BASE, "releases": rows})), "Combined manifest hash changed")
        blobs = {}
        for index, row in enumerate(rows):
            for kind in ("source", "test"):
                data = self.remote.read(backup + f"/{index}-{kind}.php", private=True)
                require(sha(data) == row[kind + "_sha256"], "Private candidate/test hash changed")
                blobs[row[kind]] = data
        return manifest, blobs

    def owned_targets(self, bundle):
        for row in bundle["releases"]:
            require(sha(self.remote.read(row["target"])) == row["source_sha256"], "Formal source drift")
            self.remote.lint(row["target"])

    def remove(self, manifest, blobs):
        """Remove only exact owned copy bytes; retain external edits or uncertain ownership."""
        retained = []
        try:
            for row in manifest["bundle"]["releases"]:
                target = row["target"]
                try:
                    require(manifest["original_absent"].get(target) is True, "Not originally absent")
                    self.remote.remove_owned(target, blobs[row["source"]])
                    require(INVENTORY.absent(self.remote, target), "Owned removal unconfirmed")
                except Exception:
                    retained.append(target)
        finally:
            purge(self.remote)
        return retained

    def verify(self, backup):
        manifest, _ = self.load(backup)
        self.owned_targets(manifest["bundle"])
        unchanged(self.remote, manifest["before"])
        result = {"public": public_verify(manifest["gallery_before"]), "health": health(self.remote)}
        unchanged(self.remote, manifest["before"])
        self.owned_targets(manifest["bundle"])
        return result

    def rollback(self, backup):
        manifest, blobs = self.load(backup)
        result = {**manifest, "removal_completed": False, "retained_external_files": None}
        try:
            result["retained_external_files"] = self.remove(manifest, blobs)
            result["removal_completed"] = True
            # Drift is reported, never restored. Rollback does not demand old data.
            result["unchanged"] = snapshot(self.remote) == manifest["before"]
            result["health"] = health(self.remote)
            require(not result["retained_external_files"], "Rollback retained externally changed files; investigate")
            result["status"] = "ROLLBACK_OWNED_FILES_ONLY"
            return result
        except BaseException as error:
            result.update(status="ROLLBACK_REQUIRES_REVIEW", error_type=type(error).__name__)
            raise
        finally:
            self.save(backup, "rollback-" + uuid.uuid4().hex + ".json", result)

    def deploy(self, bundle, blobs):
        remote = self.remote
        remote.directory(DIRECTORY)
        remote.directory(HELPER.BACKUPS)
        require(all(INVENTORY.absent(remote, path) for path in TARGETS), "NEW target already exists; never overwrite")
        before = snapshot(remote)
        baseline_health = health(remote)
        gallery = gallery_signature(get(ORIGIN + "/galeria/"))
        backup = PREFIX + uuid.uuid4().hex
        remote.mkdir(backup)
        manifest = {"schema": 1, "backup": backup, "bundle": bundle,
                    "original_absent": {target: True for target in TARGETS}, "before": before,
                    "gallery_before": gallery, "baseline_health": baseline_health, "isolated": []}
        stages = []
        activation_attempted = False
        try:
            for index, path in enumerate(INVENTORY.CRITICAL):
                content = remote.read(path)
                require(sha(content) == before["critical"][path], "Critical source changed before private capture")
                remote.write_new(backup + f"/critical-{index}.php.txt", content)
            for index, row in enumerate(bundle["releases"]):
                for kind in ("source", "test"):
                    path = backup + f"/{index}-{kind}.php"
                    remote.write_new(path, blobs[row[kind]])
                    remote.lint(path)
                output = remote.run(shlex.join([HELPER.PHP, backup + f"/{index}-test.php", backup + f"/{index}-source.php"]))
                require(re.fullmatch(r"PASS:? [1-9][0-9]* (?:isolated )?(?:checks|assertions)(?: \([^\r\n]*\))?", output)
                        is not None, "Isolated candidate tests failed")
                manifest["isolated"].append(output)
            native = NATIVE_PATH.read_bytes().replace(b"\r\n", b"\n")
            require(sha(native) == NATIVE_SHA256, "Reviewed native preflight changed")
            native_path = backup + "/native-preflight.php.txt"
            remote.write_new(native_path, native)
            remote.lint(native_path)
            manifest["native_preflight_sha256"] = NATIVE_SHA256
            manifest["native_render"] = []
            # Existing widget HTML caches must not mask candidate render filters.
            purge(remote)
            for page in ("164", "439"):
                result = json.loads(remote.run(shlex.join([
                    HELPER.PHP, native_path, backup + "/0-source.php", page])))
                require(result.get("page") == int(page) and result.get("bytes", 0) > 0,
                        "Native Elementor rendering failed")
                require(result.get("old_copy_absent") is True if page == "164"
                        else result.get("notices") == 1 and result.get("gallery_marker") == 1,
                        "Native Elementor copy/notice failed")
                manifest["native_render"].append(result)
            self.save(backup, "manifest.json", manifest)
            self.load(backup)
            print(json.dumps({"status": "PRIVATE_PREFLIGHT_SAVED", "backup": backup}), flush=True)
            for index, row in enumerate(bundle["releases"]):
                stage = DIRECTORY + "/.public-trust-" + uuid.uuid4().hex + ".stage"
                content = blobs[row["source"]]
                stages.append((stage, content))
                remote.write_new(stage, content)
                remote.lint(stage)
                remote.stage_metadata(stage, (0o644, 1608, 1611))
                remote.directory(DIRECTORY)
                unchanged(remote, before)  # Immediately before each activation, not just preflight.
                require(INVENTORY.absent(remote, row["target"]) and remote.read(stage) == content,
                        "New target/stage changed before activation")
                activation_attempted = True
                # Standard SFTP rename refuses an existing destination; never posix_rename/replace.
                remote.sftp.rename(stage, row["target"])
                remote.lint(row["target"])
                require(sha(remote.read(row["target"])) == row["source_sha256"], "Activated source hash mismatch")
            purge(remote)
            checks = self.verify(backup)
            result = {**manifest, "status": "DEPLOYED_AND_VERIFIED", "checks": checks}
            self.save(backup, "result.json", result)
            return result
        except BaseException as error:
            retained, recovery_error = [], None
            try:
                if activation_attempted:
                    retained = self.remove(manifest, blobs)
                elif "native_preflight_sha256" in manifest:
                    purge(remote)
            except Exception as cleanup_error:
                recovery_error = type(cleanup_error).__name__
            self.save(backup, "failure-" + uuid.uuid4().hex + ".json",
                      {**manifest, "status": "FAILED_OWNED_REMOVAL_ONLY", "error_type": type(error).__name__,
                       "activation_attempted": activation_attempted, "retained_external_files": retained,
                       "recovery_error_type": recovery_error})
            raise
        finally:
            for stage, content in stages:
                remote.remove_owned(stage, content)


@contextmanager
def lock(remote):
    remote.directory(HELPER.BACKUPS)
    remote.mkdir(LOCK)
    owner = uuid.uuid4().hex.encode("ascii")
    try:
        remote.write_new(LOCK + "/owner", owner)
        yield
    finally:
        remote.directory(LOCK, private=True)
        require(remote.read(LOCK + "/owner", private=True) == owner, "Lock ownership drift; retaining lock")
        remote.remove_owned(LOCK + "/owner", owner)
        remote.rmdir(LOCK)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--check", action="store_true")
    mode.add_argument("--deploy", action="store_true")
    mode.add_argument("--verify", metavar="BACKUP")
    mode.add_argument("--rollback", metavar="BACKUP")
    parser.add_argument("--allow-live-write", action="store_true")
    parser.add_argument("--expected-sha256")
    args = parser.parse_args(argv)
    remote = None
    try:
        require(bool(args.deploy or args.rollback) == args.allow_live_write, "Live-write switch required only for deploy/rollback")
        require(args.expected_sha256 is None or args.deploy, "Expected digest is deploy-only")
        if args.verify or args.rollback:
            valid_backup(args.verify or args.rollback)
        else:
            bundle, blobs = local_bundle()
            if not args.deploy:
                print(json.dumps({"mode": "OFFLINE_ONLY", **bundle}, indent=2))
                return 0
            require(args.expected_sha256 == bundle["combined_sha256"], "Reviewed combined source/test SHA256 required")
        remote = HELPER.Remote()  # The only connection point, unreachable in default/--check.
        operation = Operation(remote)
        if args.verify:
            result = operation.verify(args.verify)
        else:
            with lock(remote):
                result = operation.rollback(args.rollback) if args.rollback else operation.deploy(bundle, blobs)
        print(json.dumps({"status": "VERIFIED" if args.verify else result["status"],
                          "backup": args.verify or args.rollback or result["backup"]}, sort_keys=True))
        return 0
    except Exception as error:
        # SSH exceptions may contain credentials/host details: do not export their messages.
        print(json.dumps({"status": "FAILED", "error_type": type(error).__name__,
                          "reason": str(error) if type(error) is RuntimeError else "See private preflight/results"}))
        return 1
    finally:
        if remote is not None:
            remote.close()


if __name__ == "__main__":
    raise SystemExit(main())
