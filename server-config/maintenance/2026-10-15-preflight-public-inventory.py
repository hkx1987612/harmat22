"""Private CLI-only inventory candidate check. Does not install/expose a route."""
import hashlib
import importlib.util
import json
import shlex
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("harmat_inventory_preflight_transport", ROOT / "server-config/maintenance/2026-10-14-deploy-appointment-source.py")
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)
CANDIDATE = ROOT / "wp-mu-plugins/zz-harmat-messenger-public-inventory.php"
CODE = r'''
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('CLI only'); }
require __DIR__ . '/candidate.php';
$response = harmat_mpi_apartments();
if (!($response instanceof WP_REST_Response) || $response->get_status() !== 200) { throw new RuntimeException('Candidate returned unavailable'); }
$data = $response->get_data();
$fields = array('code','url','building','floor','rooms','sales_area_m2','outdoor_area_m2','status','price_huf');
if (array_keys($data) !== array('schema_version','generated_at','source','currency','items') || count($data['items']) !== 124) { throw new RuntimeException('Public inventory shape/count changed'); }
$prices = array('public' => 0, 'hidden_or_unpublished' => 0);
$area = false;
foreach ($data['items'] as $row) {
    if (array_keys($row) !== $fields) { throw new RuntimeException('Unexpected fields'); }
    if ($row['price_huf'] === null) { $prices['hidden_or_unpublished']++; } else { $prices['public']++; }
    if ($row['code'] === 'A3-4-L5') { $area = abs($row['sales_area_m2'] - 47.83) < 0.00001; }
}
if (!$area) { throw new RuntimeException('Protected sales-area mismatch'); }
echo wp_json_encode(array('status'=>'PASS','scope'=>'PRIVATE_CLI_PUBLIC_FIELDS_ONLY_NO_PUBLIC_ROUTE_NO_CHAT_LEAD_MODEL_OR_MESSAGE',
    'items'=>count($data['items']),'allowed_fields'=>count($fields),'price_mask_counts'=>$prices,'protected_area_47_83'=>true,
    'source'=>$data['source'],'generated_at'=>$data['generated_at'],'full_inventory_exported'=>false));
'''


def main():
    remote = MODULE.Remote()
    try:
        try:
            remote.info(MODULE.LIVE + "/wp-content/mu-plugins/zz-harmat-messenger-public-inventory.php")
        except FileNotFoundError:
            pass
        else:
            raise RuntimeError("Public plugin already installed; use release --verify, not a duplicate private preflight")
        authority = "wp-mu-plugins/zz-harmat-search-ai-discovery.php"
        live = MODULE.LIVE + "/wp-content/mu-plugins/zz-harmat-search-ai-discovery.php"
        source = remote.read(live)
        if source.replace(b"\r\n", b"\n") != (ROOT / authority).read_bytes().replace(b"\r\n", b"\n"):
            raise RuntimeError("Live authority differs from reviewed/tested source")
        remote.directory(MODULE.BACKUPS)
        private = MODULE.BACKUPS + "/messenger-inventory-preflight-" + uuid.uuid4().hex
        remote.mkdir(private)
        candidate = CANDIDATE.read_bytes().replace(b"\r\n", b"\n")
        for name, content in (("candidate", candidate), ("check", ("<?php\n" + CODE).encode())):
            remote.write_new(private + "/" + name + ".stage", content)
            remote.lint(private + "/" + name + ".stage")
            remote.sftp.rename(private + "/" + name + ".stage", private + "/" + name + ".php")
            remote.lint(private + "/" + name + ".php")
        result = json.loads(remote.run(MODULE.WP + " eval-file " + shlex.quote(private + "/check.php")))
        result.update(private_preflight_path=private, candidate_sha256=hashlib.sha256(candidate).hexdigest(),
                      authority_sha256=hashlib.sha256(source).hexdigest(), public_installation=False)
        remote.write_new(private + "/result.json", json.dumps(result, indent=2).encode())
        output = ROOT / "outputs/2026-10-15-facebook-messenger"
        output.mkdir(parents=True, exist_ok=True)
        with (output / ("inventory-preflight-" + private.rsplit("-", 1)[1] + ".json")).open("x", encoding="utf-8") as handle:
            json.dump(result, handle, indent=2)
        print(json.dumps(result, indent=2))
    finally:
        remote.close()


if __name__ == '__main__':
    main()
