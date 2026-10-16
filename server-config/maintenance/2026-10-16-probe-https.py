"""Explicit --run creates one temporary, non-sensitive child-directory probe.

No existing public file is edited. Always removes its own exact probe bytes.
Private backup pins the root configuration and protected business/source state.
"""
import argparse
import importlib.util
import json
from pathlib import Path
import shlex
import urllib.error
import urllib.request
import uuid

SPEC = importlib.util.spec_from_file_location(
    "trust", Path(__file__).with_name("2026-10-16-deploy-public-trust.py"))
D = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(D)
PHP = b'''<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    http_response_code(405); exit;
}
function scheme_flag($key) {
    $value = strtolower((string) ($_SERVER[$key] ?? ''));
    return in_array($value, array('on','off','http','https','80','443','1','0'), true) ? $value : ($value === '' ? 'absent' : 'other');
}
echo json_encode(array(
    'https' => scheme_flag('HTTPS'), 'port' => scheme_flag('SERVER_PORT'),
    'forwarded_proto' => scheme_flag('HTTP_X_FORWARDED_PROTO'),
    'forwarded_ssl' => scheme_flag('HTTP_X_FORWARDED_SSL'),
    'front_end_https' => scheme_flag('HTTP_FRONT_END_HTTPS')
));
'''
RULES = b'''<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^scheme.php$ https://harmat22.hu/adatvedelmi-tajekoztato/ [R=302,L]
RewriteCond %{HTTP:X-Forwarded-Proto} ^http$ [NC]
RewriteRule ^forwarded.php$ https://harmat22.hu/adatvedelmi-tajekoztato/ [R=302,L]
</IfModule>
'''


def request(url, extra_headers=None, method="GET"):
    req = urllib.request.Request(url, method=method, headers={"User-Agent": D.AGENT, **(extra_headers or {})})
    try:
        response = urllib.request.build_opener(D.NoRedirect()).open(req, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        D.require(response.code != 403, "HTTP403: stop without changing identity or retrying")
        D.require(response.code in (200, 301, 302, 405), "Unexpected isolated probe status: " + str(response.code))
        body = response.read(4097)
        D.require(len(body) <= 4096, "Unexpected probe body size")
        result = {"url": url, "method": method, "status": response.code,
                  "location": response.headers.get("Location"),
                  "server": response.headers.get("Server")}
        if response.code == 200 and method != "HEAD":
            result["flags"] = json.loads(body)
        return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--run", action="store_true")
    args = parser.parse_args()
    if not args.run:
        print("Offline only. Explicit --run is required.")
        return
    remote = D.HELPER.Remote()
    token = uuid.uuid4().hex
    directory = D.HELPER.LIVE + "/harmat-scheme-probe-" + token
    backup = D.HELPER.BACKUPS + "/https-probe-" + token
    final_rules = (D.ROOT / "server-config/public-https.htaccess").read_bytes().replace(b"\r\n", b"\n")
    files = {"flags.php": PHP, "scheme.php": PHP, "forwarded.php": PHP, "index.php": PHP,
             ".htaccess": RULES + final_rules}
    created = []
    made_directory = False
    try:
        remote.directory(D.HELPER.LIVE)
        before = D.snapshot(remote)
        root_rules = remote.read(D.HELPER.LIVE + "/.htaccess")
        D.require(D.sha(root_rules) == "527e57484be628c427f62b7872cb1529af359d219e4433001899385db4a24511", "Root configuration drift")
        remote.mkdir(backup)
        remote.write_new(backup + "/original.htaccess", root_rules)
        remote.write_new(backup + "/before.json", D.encoded(before))
        print(json.dumps({"event": "PRIVATE_PROBE_BACKUP", "backup": backup}), flush=True)
        remote.sftp.mkdir(directory, 0o755)
        made_directory = True
        for name, content in files.items():
            path = directory + "/" + name
            remote.write_new(path, content)
            created.append((path, content))
            if name.endswith(".php"):
                remote.lint(path)
            remote.sftp.chmod(path, 0o644)
        results = []
        for name in ("flags.php", "scheme.php", "forwarded.php"):
            for scheme in ("http", "https"):
                result = request(scheme + "://harmat22.hu/" + directory.rsplit("/", 1)[1] + "/" + name)
                results.append(result)
                print(json.dumps(result, sort_keys=True), flush=True)
        for scheme, supplied in (("http", "https"), ("https", "http")):
            result = request(scheme + "://harmat22.hu/" + directory.rsplit("/", 1)[1] + "/forwarded.php",
                             {"X-Forwarded-Proto": supplied})
            D.require(result["status"] == (302 if scheme == "http" else 200),
                      "Frontend does not overwrite client-supplied protocol header")
            result["supplied_protocol_header"] = supplied
            results.append(result)
            print(json.dumps(result, sort_keys=True), flush=True)
        public_path = "/" + directory.rsplit("/", 1)[1] + "/"
        for suffix in ("", "?rooms=3&utm_source=harmat-probe&utm_medium=check"):
            for method in ("GET", "HEAD"):
                for scheme in ("http", "https"):
                    result = request(scheme + "://harmat22.hu" + public_path + suffix, method=method)
                    D.require(result["status"] == (301 if scheme == "http" else 200), "Final isolated rule status failed")
                    D.require(result["location"] == ("https://harmat22.hu" + public_path + suffix if scheme == "http" else None),
                              "Final isolated rule path/query changed")
                    results.append(result)
                    print(json.dumps(result, sort_keys=True), flush=True)
        for scheme in ("http", "https"):
            for suffix, method, expected in (("?action=harmat_https_probe", "GET", 200), ("", "POST", 405)):
                result = request(scheme + "://harmat22.hu" + public_path + suffix, method=method)
                D.require(result["status"] == expected and result["location"] is None, "Excluded method/query was redirected")
                results.append(result)
                print(json.dumps(result, sort_keys=True), flush=True)
        D.unchanged(remote, before)
        D.require(remote.read(D.HELPER.LIVE + "/.htaccess") == root_rules, "Root rules changed during probe")
        remote.write_new(backup + "/results.json", D.encoded(results))
        remote.write_new(backup + "/tested-public-https.htaccess", final_rules)
    finally:
        try:
            for path, content in reversed(created):
                remote.remove_owned(path, content)
            if made_directory:
                remote.sftp.rmdir(directory)
        finally:
            remote.close()


if __name__ == "__main__":
    main()
