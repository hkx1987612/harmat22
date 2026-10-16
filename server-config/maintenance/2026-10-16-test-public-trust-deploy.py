"""Offline, in-memory safety tests. No SSH, HTTP, Git, PHP or filesystem writes."""
import contextlib
import importlib.util
import io
import json
from pathlib import Path
import shlex
import stat
import unittest
from unittest.mock import patch
import urllib.error

SPEC = importlib.util.spec_from_file_location("public_trust_deploy", Path(__file__).with_name("2026-10-16-deploy-public-trust.py"))
D = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(D)
REAL_HEALTH = D.health


class MemoryPath:
    def __init__(self, files, path=""):
        self.files, self.path = files, path

    def __truediv__(self, part):
        return MemoryPath(self.files, str(Path(self.path) / part).replace("\\", "/"))

    def read_bytes(self):
        return self.files[self.path]


def local_files():
    result = {}
    for source, test in D.RELEASES:
        result[source] = b"<?php\n// isolated candidate " + source.encode() + b"\n"
        result[test] = b"<?php\nrequire $argv[1];\necho 'PASS 241 assertions';\n"
    return result


class FakeRemote:
    remove_owned = D.HELPER.Remote.remove_owned

    def __init__(self):
        self.files = {path: ("existing " + path).encode() for path in (*D.INVENTORY.CRITICAL, *D.OTHER_SOURCES.values())}
        self.modes = {path: 0o644 for path in self.files}
        self.dirs = {D.DIRECTORY: 0o755, D.HELPER.BACKUPS: 0o755}
        self.events = []
        self.sftp = self
        self.protected = {"posts": "a" * 64, "metadata": "b" * 64, "crm_options": "c" * 64}
        self.pages = {"pages": "d" * 64, "elementor": "e" * 64}
        self.renames = self.purges = 0
        self.fail_rename = None
        self.fail_lint = None
        self.after_rename = None
        self.before_activation = None
        self.closed = False

    def info(self, path):
        if path not in self.files and path not in self.dirs:
            raise FileNotFoundError(path)
        return type("Info", (), {"st_mode": stat.S_IFREG | self.modes.get(path, 0o700)})()

    def read(self, path, private=False):
        if path not in self.files:
            raise FileNotFoundError(path)
        if private:
            D.require(self.modes[path] == 0o600, "Private fake mode")
        return self.files[path]

    def directory(self, path, private=False):
        D.require(path in self.dirs, "Missing fake directory")
        if private:
            D.require(self.dirs[path] == 0o700, "Private fake directory mode")

    def mkdir(self, path):
        D.require(path not in self.dirs, "Exclusive fake mkdir")
        self.dirs[path] = 0o700

    def write_new(self, path, content):
        D.require(path not in self.files, "Exclusive fake file")
        self.files[path], self.modes[path] = content, 0o600
        self.events.append(("write", path))

    def lint(self, path):
        self.read(path)
        self.events.append(("lint", path))
        if self.fail_lint and self.fail_lint(path):
            raise RuntimeError("Injected lint failure")

    def stage_metadata(self, path, metadata):
        D.require(metadata == (0o644, 1608, 1611), "Unexpected new-file metadata")
        self.modes[path] = metadata[0]
        if self.before_activation:
            self.before_activation(self)

    def rename(self, stage, target):
        self.renames += 1
        self.events.append(("rename", stage, target))
        if self.renames == self.fail_rename:
            raise RuntimeError("Injected activation failure")
        D.require(target not in self.files, "Rename never overwrites")
        self.files[target] = self.files.pop(stage)
        self.modes[target] = self.modes.pop(stage)
        if self.after_rename:
            self.after_rename(self, target)

    def remove(self, path):
        self.events.append(("remove", path))
        del self.files[path]
        del self.modes[path]

    def rmdir(self, path):
        del self.dirs[path]

    def purge(self):
        self.purges += 1

    def run(self, command):
        self.events.append(("run", command))
        args = shlex.split(command)
        code = args[-1]
        if code == D.INVENTORY.SNAPSHOT:
            return json.dumps(self.protected)
        if code == D.PAGE_SNAPSHOT:
            return json.dumps(self.pages)
        if code == D.PAGE_CACHE:
            return "PUBLIC_TRUST_TWO_PAGE_CACHE_CLEARED"
        if code == D.HEALTH.RUNTIME_CHECK:
            return json.dumps({"status": "PASS", "checks": 17})
        if len(args) == 4 and args[1].endswith('/native-preflight.php.txt'):
            self.read(args[1], private=True)
            self.read(args[2], private=True)
            D.require(args[3] in ('164', '439'), 'Native page scope')
            if getattr(self, 'fail_native', False):
                raise RuntimeError('Injected native render failure')
            return json.dumps({'page':int(args[3]), 'bytes':100, 'old_copy_absent':True,
                               'notices':1, 'gallery_marker':1})
        D.require(len(args) == 3 and args[1].endswith("-test.php") and args[2].endswith("-source.php"),
                  "Tests must receive candidate in argv[1]")
        self.read(args[1], private=True)
        self.read(args[2], private=True)
        return getattr(self, "fixture_output", "PASS 241 assertions (including five isolated constant-gate processes)")

    def close(self):
        self.closed = True


GALLERY = '<div><img src="/one.jpg" srcset="/two.jpg 2x" data-src="/lazy.jpg"><img src="/three.jpg"></div>'


class DeployTests(unittest.TestCase):
    def setUp(self):
        self.stack = contextlib.ExitStack()
        self.addCleanup(self.stack.close)
        self.remote = FakeRemote()
        self.stack.enter_context(patch.object(D.INVENTORY, "CRITICAL", {p: D.sha(self.remote.files[p]) for p in D.INVENTORY.CRITICAL}))
        self.stack.enter_context(patch.object(D, "checkout_head", return_value=D.BASE + "0" * 33))
        self.bundle, self.blobs = D.local_bundle(MemoryPath(local_files()))
        self.stack.enter_context(patch.object(D, "health", return_value={"offline": True}))
        self.stack.enter_context(patch.object(D, "get", return_value=GALLERY))
        self.public = self.stack.enter_context(patch.object(D, "public_verify", return_value={"offline_public": True}))
        self.connection = self.stack.enter_context(patch.object(D.HELPER, "Remote", side_effect=AssertionError("No SSH allowed")))
        self.stack.enter_context(patch.object(D.urllib.request, "urlopen", side_effect=AssertionError("No network allowed")))
        self.stack.enter_context(contextlib.redirect_stdout(io.StringIO()))
        self.operation = D.Operation(self.remote)

    def deploy(self):
        return self.operation.deploy(self.bundle, self.blobs)

    def run_main(self, args):
        with patch.object(D, "local_bundle", return_value=(self.bundle, self.blobs)):
            return D.main(args)

    def test_default_and_explicit_check_never_connect(self):
        self.assertEqual(self.run_main([]), 0)
        self.assertEqual(self.run_main(["--check"]), 0)
        self.connection.assert_not_called()

    def test_actual_check_path_remains_offline(self):
        with patch.object(D, "ROOT", MemoryPath(local_files())):
            self.assertEqual(D.main([]), 0)
            self.assertEqual(D.main(["--check"]), 0)
        self.connection.assert_not_called()

    def test_bad_actual_check_path_remains_offline(self):
        with patch.object(D, "ROOT", MemoryPath(local_files())), patch.object(D, "checkout_head", return_value="0" * 40):
            self.assertEqual(D.main(["--check"]), 1)
        self.connection.assert_not_called()

    def test_wrong_baseline_refused(self):
        with patch.object(D, "checkout_head", return_value="0" * 40):
            with self.assertRaisesRegex(RuntimeError, "baseline"):
                D.local_bundle(MemoryPath(local_files()))

    def test_combined_digest_includes_copy_source_and_test(self):
        for path in self.blobs:
            files = local_files()
            files[path] += b"// changed\n"
            changed, _ = D.local_bundle(MemoryPath(files))
            self.assertNotEqual(changed["combined_sha256"], self.bundle["combined_sha256"])

    def test_exactly_one_copy_release_target_and_two_local_blobs(self):
        source = "wp-mu-plugins/zz-harmat-public-trust-copy.php"
        test = D.MAINTENANCE + "2026-10-16-test-public-trust-copy.php"
        self.assertEqual(D.RELEASES, ((source, test),))
        self.assertEqual(D.TARGETS, (D.DIRECTORY + "/zz-harmat-public-trust-copy.php",))
        self.assertEqual(set(self.blobs), {source, test})
        self.assertEqual(len(self.bundle["releases"]), 1)

    def test_one_target_deploy_and_rollback_leave_unrelated_source_untouched(self):
        unrelated = D.DIRECTORY + "/unrelated-existing.php"
        self.remote.files[unrelated] = b"unrelated original code"
        self.remote.modes[unrelated] = 0o644
        result = self.deploy()
        self.assertEqual([event[2] for event in self.remote.events if event[0] == "rename"], list(D.TARGETS))
        self.operation.rollback(result["backup"])
        self.assertEqual(self.remote.files[unrelated], b"unrelated original code")
        self.assertEqual(self.remote.modes[unrelated], 0o644)

    def test_hardcoded_test_path_refused(self):
        files = local_files()
        files[D.RELEASES[0][1]] = b"<?php\n// $argv[1]\nrequire dirname(__DIR__, 2) . '/source.php';\n"
        with self.assertRaisesRegex(RuntimeError, "argv"):
            D.local_bundle(MemoryPath(files))

    def test_native_isolated_fixture_stdout_accepted(self):
        self.remote.fixture_output = "PASS: 241 isolated checks"
        self.assertEqual(self.deploy()["status"], "DEPLOYED_AND_VERIFIED")

    def test_copy_assertion_fixture_stdout_accepted(self):
        self.remote.fixture_output = "PASS 241 assertions (including five isolated constant-gate processes)"
        self.assertEqual(self.deploy()["status"], "DEPLOYED_AND_VERIFIED")

    def test_fixture_whole_output_rejects_noise_and_false_passes(self):
        for output in ("noise\nPASS: 241 isolated checks", "PASS: 241 isolated checks\nFAIL", "PASS", "PASS: 0 checks"):
            with self.subTest(output=output):
                remote = FakeRemote()
                remote.fixture_output = output
                with self.assertRaisesRegex(RuntimeError, "Isolated candidate tests failed"):
                    D.Operation(remote).deploy(self.bundle, self.blobs)
                self.assertEqual(remote.renames, 0)

    def test_wrong_hash_no_connection(self):
        self.assertEqual(self.run_main(["--deploy", "--allow-live-write", "--expected-sha256", "0" * 64]), 1)
        self.connection.assert_not_called()

    def test_missing_write_gate_no_connection(self):
        self.assertEqual(self.run_main(["--deploy", "--expected-sha256", self.bundle["combined_sha256"]]), 1)
        self.assertEqual(self.run_main(["--rollback", D.PREFIX + "a" * 32]), 1)
        self.connection.assert_not_called()

    def test_check_rejects_live_gate(self):
        self.assertEqual(self.run_main(["--check", "--allow-live-write"]), 1)
        self.connection.assert_not_called()

    def test_invalid_backup_refused_before_connection(self):
        for path in (D.PREFIX + "../bad", D.PREFIX + "A" * 32, "/tmp/backup"):
            self.assertEqual(self.run_main(["--verify", path]), 1)
        self.connection.assert_not_called()

    def test_preexisting_copy_target_refuses_without_writes(self):
        for target in D.TARGETS:
            self.remote.files[target] = b"customer code"
            with self.assertRaisesRegex(RuntimeError, "already exists"):
                self.deploy()
            self.assertEqual(self.remote.files.pop(target), b"customer code")
            self.assertFalse(self.remote.events)

    def test_lint_before_each_activate_and_final_lint(self):
        result = self.deploy()
        self.assertEqual(result["status"], "DEPLOYED_AND_VERIFIED")
        for index, event in enumerate(self.remote.events):
            if event[0] == "rename":
                self.assertIn(("lint", event[1]), self.remote.events[:index])
                self.assertIn(("lint", event[2]), self.remote.events[index + 1:])
        self.assertFalse(any(path.endswith(".stage") for path in self.remote.files))

    def test_stage_lint_failure_never_activates(self):
        self.remote.fail_lint = lambda path: path.endswith(".stage")
        with self.assertRaisesRegex(RuntimeError, "lint failure"):
            self.deploy()
        self.assertEqual(self.remote.renames, 0)
        self.assertTrue(all(path not in self.remote.files for path in D.TARGETS))

    def test_final_lint_failure_removes_activated_file(self):
        self.remote.fail_lint = lambda path: path == D.TARGETS[0]
        with self.assertRaisesRegex(RuntimeError, "lint failure"):
            self.deploy()
        self.assertTrue(all(path not in self.remote.files for path in D.TARGETS))

    def test_no_critical_source_overwrite_on_drift(self):
        path = next(iter(D.INVENTORY.CRITICAL))
        self.remote.files[path] = b"external critical change"
        with self.assertRaisesRegex(RuntimeError, "source drift"):
            self.deploy()
        self.assertEqual(self.remote.files[path], b"external critical change")
        self.assertFalse(self.remote.events)

    def test_failed_rename_leaves_copy_absent(self):
        self.remote.fail_rename = 1
        with self.assertRaisesRegex(RuntimeError, "activation failure"):
            self.deploy()
        self.assertTrue(all(path not in self.remote.files for path in D.TARGETS))
        self.assertEqual(self.remote.purges, 2)

    def test_uncertain_rename_removes_own_bytes(self):
        def fail_after_rename(remote, target):
            raise RuntimeError("Lost rename acknowledgement")
        self.remote.after_rename = fail_after_rename
        with self.assertRaisesRegex(RuntimeError, "acknowledgement"):
            self.deploy()
        self.assertNotIn(D.TARGETS[0], self.remote.files)

    def test_external_copy_drift_not_deleted_after_activation_failure(self):
        def drift_then_fail(remote, target):
            remote.files[target] = b"external edit"
            raise RuntimeError("Post activation failure")
        self.remote.after_rename = drift_then_fail
        with self.assertRaises(RuntimeError):
            self.deploy()
        self.assertEqual(self.remote.files[D.TARGETS[0]], b"external edit")
        self.assertEqual(self.remote.purges, 2)

    def test_protected_drift_before_activation_never_rewinds(self):
        self.remote.before_activation = lambda remote: remote.protected.update(posts="f" * 64)
        with self.assertRaisesRegex(RuntimeError, "drift"):
            self.deploy()
        self.assertEqual(self.remote.renames, 0)
        self.assertEqual(self.remote.protected["posts"], "f" * 64)
        self.assertTrue(all(path not in self.remote.files for path in D.TARGETS))

    def test_public_page_drift_before_activation_preserved(self):
        self.remote.before_activation = lambda remote: remote.pages.update(elementor="f" * 64)
        with self.assertRaisesRegex(RuntimeError, "drift"):
            self.deploy()
        self.assertEqual(self.remote.pages["elementor"], "f" * 64)
        self.assertEqual(self.remote.renames, 0)
        self.assertNotIn(D.TARGETS[0], self.remote.files)

    def test_old_source_drift_never_restored(self):
        source = D.OTHER_SOURCES["migratedSnippets"]
        self.remote.after_rename = lambda remote, target: remote.files.update({source: b"unrelated new edit"})
        with self.assertRaisesRegex(RuntimeError, "drift"):
            self.deploy()
        self.assertEqual(self.remote.files[source], b"unrelated new edit")

    def test_public_403_removes_owned_copy_no_fallback(self):
        self.public.side_effect = urllib.error.HTTPError(D.ORIGIN, 403, "Forbidden", {}, None)
        with self.assertRaises(urllib.error.HTTPError):
            self.deploy()
        self.assertEqual(self.public.call_count, 1)
        self.assertTrue(all(path not in self.remote.files for path in D.TARGETS))

    def test_protected_drift_in_postchecks_removes_sources_not_data(self):
        def drift(*args):
            self.remote.protected["crm_options"] = "f" * 64
            return {"public": True}
        self.public.side_effect = drift
        with self.assertRaisesRegex(RuntimeError, "drift"):
            self.deploy()
        self.assertEqual(self.remote.protected["crm_options"], "f" * 64)
        self.assertTrue(all(path not in self.remote.files for path in D.TARGETS))

    def test_purge_failure_still_retains_private_failure_result(self):
        self.remote.fail_rename = 1
        with patch.object(self.remote, "purge", side_effect=[None, RuntimeError("Injected purge failure")]):
            with self.assertRaisesRegex(RuntimeError, "activation failure"):
                self.deploy()
        failures = [json.loads(value) for path, value in self.remote.files.items() if "/failure-" in path]
        self.assertEqual(len(failures), 1)
        self.assertEqual(failures[0]["recovery_error_type"], "RuntimeError")
        self.assertTrue(all(path not in self.remote.files for path in D.TARGETS))

    def test_private_manifest_captures_absence_hashes_tests(self):
        result = self.deploy()
        manifest, blobs = self.operation.load(result["backup"])
        self.assertEqual(blobs, self.blobs)
        self.assertEqual(manifest["original_absent"], dict.fromkeys(D.TARGETS, True))
        self.assertEqual(set(manifest["before"]["other_sources"]), set(D.OTHER_SOURCES))
        self.assertEqual(len(manifest["before"]["critical"]), 4)
        self.assertEqual(len(manifest["isolated"]), 1)
        self.assertEqual(manifest['native_preflight_sha256'], D.NATIVE_SHA256)
        self.assertEqual([r['page'] for r in manifest['native_render']], [164,439])
        self.assertEqual(self.remote.dirs[D.HELPER.BACKUPS], 0o755)
        for path in self.remote.files:
            if path.startswith(result["backup"] + "/"):
                self.assertEqual(self.remote.modes[path], 0o600)
        self.assertEqual(self.remote.dirs[result["backup"]], 0o700)

    def test_native_render_failure_never_activates(self):
        self.remote.fail_native = True
        with self.assertRaisesRegex(RuntimeError, 'native render failure'):
            self.deploy()
        self.assertEqual(self.remote.renames, 0)
        self.assertEqual(self.remote.purges, 2)
        self.assertTrue(all(path not in self.remote.files for path in D.TARGETS))

    def test_native_preflight_hash_failure_never_activates(self):
        with patch.object(D, 'NATIVE_SHA256', '0' * 64):
            with self.assertRaisesRegex(RuntimeError, 'preflight changed'):
                self.deploy()
        self.assertEqual(self.remote.renames, 0)

    def test_verify_no_file_or_data_writes(self):
        result = self.deploy()
        before = dict(self.remote.files)
        self.operation.verify(result["backup"])
        self.assertEqual(self.remote.files, before)

    def test_manifest_wrong_target_or_absence_refused(self):
        result = self.deploy()
        path = result["backup"] + "/manifest.json"
        original = self.remote.files[path]
        for mutate in (lambda value: value["original_absent"].update({D.TARGETS[0]: False}),
                       lambda value: value["bundle"]["releases"][0].update(target="/tmp/not-owned")):
            value = json.loads(original)
            mutate(value)
            self.remote.files[path] = D.encoded(value)
            with self.assertRaises(RuntimeError):
                self.operation.load(result["backup"])

    def test_original_absence_requires_exact_copy_target_and_boolean_true(self):
        result = self.deploy()
        path = result["backup"] + "/manifest.json"
        original = self.remote.files[path]
        cases = ({}, {D.TARGETS[0]: False}, {D.TARGETS[0]: 1},
                 {D.TARGETS[0]: True, "/tmp/not-owned.php": True})
        for absence in cases:
            with self.subTest(absence=absence):
                manifest = json.loads(original)
                manifest["original_absent"] = absence
                self.remote.files[path] = D.encoded(manifest)
                with self.assertRaisesRegex(RuntimeError, "exact target set absent"):
                    self.operation.load(result["backup"])
                self.assertEqual(self.remote.files[D.TARGETS[0]], self.blobs[D.RELEASES[0][0]])

    def test_historical_two_file_and_wrong_single_file_bundles_refused(self):
        result = self.deploy()
        path = result["backup"] + "/manifest.json"
        original = self.remote.files[path]
        rejected = {"source": "wp-mu-plugins/zz-harmat-public-https.php",
                    "test": D.MAINTENANCE + "2026-10-16-test-public-https.php",
                    "target": D.DIRECTORY + "/zz-harmat-public-https.php",
                    "source_sha256": "a" * 64, "test_sha256": "b" * 64}
        for rows in ([self.bundle["releases"][0], rejected], [rejected]):
            with self.subTest(rows=len(rows)):
                manifest = json.loads(original)
                manifest["bundle"]["releases"] = rows
                manifest["bundle"]["combined_sha256"] = D.sha(D.encoded({"baseline": D.BASE, "releases": rows}))
                self.remote.files[path] = D.encoded(manifest)
                with self.assertRaisesRegex(RuntimeError, "Wrong backup target set"):
                    self.operation.load(result["backup"])
                self.assertEqual(self.remote.files[D.TARGETS[0]], self.blobs[D.RELEASES[0][0]])

    def test_additional_bundle_row_refused_before_any_activation(self):
        bundle = json.loads(D.encoded(self.bundle))
        bundle["releases"].append(dict(bundle["releases"][0]))
        bundle["combined_sha256"] = D.sha(D.encoded({"baseline": D.BASE, "releases": bundle["releases"]}))
        with self.assertRaisesRegex(RuntimeError, "Wrong backup target set"):
            self.operation.deploy(bundle, self.blobs)
        self.assertEqual(self.remote.renames, 0)
        self.assertNotIn(D.TARGETS[0], self.remote.files)

    def test_private_combined_bundle_digest_mismatch_refused(self):
        result = self.deploy()
        path = result["backup"] + "/manifest.json"
        manifest = json.loads(self.remote.files[path])
        manifest["bundle"]["combined_sha256"] = "0" * 64
        self.remote.files[path] = D.encoded(manifest)
        with self.assertRaisesRegex(RuntimeError, "Combined manifest hash changed"):
            self.operation.load(result["backup"])
        self.assertEqual(self.remote.files[D.TARGETS[0]], self.blobs[D.RELEASES[0][0]])

    def test_rollback_ignores_database_drift_never_restores(self):
        result = self.deploy()
        self.remote.protected["posts"] = "f" * 64
        rollback = self.operation.rollback(result["backup"])
        self.assertFalse(rollback["unchanged"])
        self.assertEqual(self.remote.protected["posts"], "f" * 64)
        self.assertTrue(all(path not in self.remote.files for path in D.TARGETS))

    def test_rollback_retains_external_copy_file(self):
        result = self.deploy()
        self.remote.files[D.TARGETS[0]] = b"external edit"
        with self.assertRaisesRegex(RuntimeError, "externally changed"):
            self.operation.rollback(result["backup"])
        self.assertEqual(self.remote.files[D.TARGETS[0]], b"external edit")

    def test_rollback_health403_records_block_and_never_retries(self):
        result = self.deploy()
        error = urllib.error.HTTPError(D.ORIGIN, 403, "Forbidden", {}, None)
        with patch.object(D, "health", side_effect=error) as health:
            with self.assertRaises(urllib.error.HTTPError):
                self.operation.rollback(result["backup"])
            health.assert_called_once_with(self.remote)
        self.assertTrue(all(path not in self.remote.files for path in D.TARGETS))
        records = [json.loads(value) for path, value in self.remote.files.items() if "/rollback-" in path]
        self.assertEqual(len(records), 1)
        self.assertEqual(records[0]["status"], "ROLLBACK_REQUIRES_REVIEW")
        self.assertTrue(records[0]["removal_completed"])
        self.assertEqual(records[0]["retained_external_files"], [])

    def test_lock_preserves_parent_permissions(self):
        with D.lock(self.remote):
            self.assertEqual(self.remote.dirs[D.LOCK], 0o700)
        self.assertNotIn(D.LOCK, self.remote.dirs)
        self.assertEqual(self.remote.dirs[D.HELPER.BACKUPS], 0o755)

    def test_inventory_feed_post403_fails_without_fallback(self):
        with patch.object(D.HEALTH, "smoke", return_value=[{}] * 9) as smoke, \
                patch.object(D.INVENTORY, "public_check", return_value={"items": 124, "post_status": 403}) as feed:
            with self.assertRaisesRegex(RuntimeError, "blocked"):
                REAL_HEALTH(self.remote)
            smoke.assert_called_once()
            feed.assert_called_once()

    def test_native_17_pure_callback_check_reused(self):
        with patch.object(D.HEALTH, "smoke", return_value=[{}] * 9), \
                patch.object(D.INVENTORY, "public_check", return_value={"items": 124, "post_status": 404}):
            result = REAL_HEALTH(self.remote)
        self.assertEqual(result["antispam"]["checks"], 17)
        self.assertIn(("run", D.HELPER.WP + " eval " + shlex.quote(D.HEALTH.RUNTIME_CHECK)), self.remote.events)


class HttpTests(unittest.TestCase):
    def test_direct_get_uses_fixed_agent_timeout_and_exact_url(self):
        class Response(io.BytesIO):
            code = 200
            headers = {"Content-Type": "text/html; charset=utf-8"}

            def geturl(self):
                return D.ORIGIN + D.PRIVACY

        response = Response(b"")
        with patch.object(D.urllib.request, "build_opener") as build:
            build.return_value.open.return_value = response
            D.get(D.ORIGIN + D.PRIVACY)
            request = build.return_value.open.call_args.args[0]
            self.assertEqual(request.get_header("User-agent"), D.AGENT)
            self.assertEqual(request.get_method(), "GET")
            self.assertEqual(build.return_value.open.call_args.kwargs["timeout"], 30)
            self.assertIsInstance(build.call_args.args[0], D.NoRedirect)
        self.assertTrue(response.closed)

    def test_direct_403_fails_once_without_switching_agent(self):
        error = urllib.error.HTTPError(D.ORIGIN + D.PRIVACY, 403, "Forbidden", {}, io.BytesIO(b"denied"))
        with patch.object(D.urllib.request, "build_opener") as build:
            build.return_value.open.side_effect = error
            with self.assertRaisesRegex(RuntimeError, "HTTP403"):
                D.get(D.ORIGIN + D.PRIVACY)
            self.assertEqual(build.return_value.open.call_count, 1)
            self.assertEqual(build.return_value.open.call_args.args[0].get_header("User-agent"), D.AGENT)

    def test_https_privacy_redirect_is_not_accepted_as_200(self):
        error = urllib.error.HTTPError(D.ORIGIN + D.PRIVACY, 301, "Moved", {"Location": D.ORIGIN + D.PRIVACY}, io.BytesIO())
        with patch.object(D.urllib.request, "build_opener") as build:
            build.return_value.open.side_effect = error
            with self.assertRaisesRegex(RuntimeError, "identity/status"):
                D.get(D.ORIGIN + D.PRIVACY)
            self.assertEqual(build.return_value.open.call_count, 1)

    def test_gallery_signature_counts_and_source_attributes(self):
        baseline = D.gallery_signature(GALLERY)
        released = '<p class="' + D.NOTICE_MARKER + '">' + D.NOTICE + '</p>' + GALLERY
        self.assertEqual(D.gallery_signature(released, True), baseline)
        self.assertNotEqual(D.gallery_signature(GALLERY.replace("one.jpg", "changed.jpg")), baseline)
        with self.assertRaisesRegex(RuntimeError, "exactly once"):
            D.gallery_signature(released + '<p class="' + D.NOTICE_MARKER + '"></p>', True)

    def test_gallery_exact_hungarian_notice_is_required(self):
        wrong = '<p class="' + D.NOTICE_MARKER + '">Different notice</p>' + GALLERY
        with self.assertRaisesRegex(RuntimeError, "Exact Hungarian"):
            D.gallery_signature(wrong, True)

    def test_public_verify_rejects_changed_gallery_image_signature(self):
        notice = '<p class="' + D.NOTICE_MARKER + '">' + D.NOTICE + '</p>'
        def fake_get(url):
            return notice + GALLERY.replace("one.jpg", "changed.jpg") if url.endswith("/galeria/") else "<p>Public page</p>"
        with patch.object(D, "get", side_effect=fake_get):
            with self.assertRaisesRegex(RuntimeError, "Gallery image count/source attributes changed"):
                D.public_verify(D.gallery_signature(GALLERY))

    def test_unbounded_legacy_read_is_capped(self):
        response = D.LimitedResponse(io.BytesIO(b"x" * (D.LIMIT + 1)))
        with self.assertRaisesRegex(RuntimeError, "1 MiB"):
            response.read()

    def test_cumulative_reads_are_capped(self):
        response = D.LimitedResponse(io.BytesIO(b"x" * (D.LIMIT + 1)))
        self.assertEqual(len(response.read(D.LIMIT)), D.LIMIT)
        with self.assertRaises(RuntimeError):
            response.read(1)

    def test_no_redirect_handler(self):
        self.assertIsNone(D.NoRedirect().redirect_request(None, None, 301, "Moved", {}, D.ORIGIN))

    def test_exact_privacy_and_copy_only_contract(self):
        notice = '<p class="' + D.NOTICE_MARKER + '">' + D.NOTICE + '</p>'
        calls = []
        def fake_get(url):
            calls.append(url)
            return notice + GALLERY if url.endswith("/galeria/") else "<p>CSOK other cautious text</p>"
        with patch.object(D, "get", side_effect=fake_get):
            result = D.public_verify(D.gallery_signature(GALLERY))
        self.assertEqual(calls, [D.ORIGIN + D.PRIVACY, "http://harmat22.hu" + D.PRIVACY,
                                D.ORIGIN + "/", D.ORIGIN + "/galeria/"])
        self.assertEqual(result["privacy_https"], 200)
        self.assertEqual(result["privacy_http"], 200)
        self.assertIs(result["http_privacy_corrected"], False)
        self.assertNotIn("http_redirects", result)

    def test_all_three_exact_promotions_rejected(self):
        for old in D.OLD_COPY:
            with patch.object(D, "get", return_value=old):
                with self.assertRaisesRegex(RuntimeError, "promotional"):
                    D.public_verify({})

    def test_shared_request_timeout_body_bound_and_no_403_retry(self):
        error = urllib.error.HTTPError(D.ORIGIN, 403, "Forbidden", {}, io.BytesIO())
        with patch.object(D.urllib.request, "urlopen", side_effect=error) as opener:
            with D.bounded_shared_requests():
                with self.assertRaises(urllib.error.HTTPError):
                    D.urllib.request.urlopen(D.ORIGIN, timeout=120)
            self.assertEqual(opener.call_count, 1)
            self.assertEqual(opener.call_args.kwargs["timeout"], 30)
            self.assertIs(D.urllib.request.urlopen, opener)

    def test_cache_scope_has_no_global_elementor_css(self):
        self.assertIn("array(164,439)", D.PAGE_CACHE)
        self.assertIn("'_elementor_element_cache'", D.PAGE_CACHE)
        self.assertNotIn("files_manager", D.PAGE_CACHE)
        self.assertNotIn("_elementor_css", D.PAGE_CACHE)


if __name__ == "__main__":
    unittest.main(verbosity=2)
