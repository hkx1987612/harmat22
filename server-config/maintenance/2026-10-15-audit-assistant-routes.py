"""Read-only registered assistant-route metadata. Never invokes a business API."""
import importlib.util
import json
import shlex
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("harmat_route_transport", ROOT / "server-config/maintenance/2026-10-14-deploy-appointment-source.py")
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)
CODE = r'''
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('CLI only'); }
$routes = rest_get_server()->get_routes();
$output = array();
foreach (array('/harmat/v1/ai-units', '/harmat/v1/ai-lead', '/harmat-sales-manager/v1/offer') as $path) {
    $entry = array('registered' => isset($routes[$path]), 'handlers' => array());
    foreach ($routes[$path] ?? array() as $handler) {
        if (!is_array($handler) || !isset($handler['callback'])) { continue; }
        $item = array('methods' => array_keys(array_filter($handler['methods'] ?? array())));
        foreach (array('callback', 'permission_callback') as $kind) {
            $callback = $handler[$kind] ?? null;
            if (!$callback || !is_callable($callback)) { continue; }
            try {
                $reflection = is_array($callback)
                    ? new ReflectionMethod($callback[0], $callback[1])
                    : new ReflectionFunction($callback);
                $item[$kind] = array('name' => $reflection->getName(), 'source' => $reflection->getFileName(), 'start' => $reflection->getStartLine(), 'end' => $reflection->getEndLine());
            } catch (ReflectionException $error) {
                $item[$kind] = array('reflection' => 'unavailable');
            }
        }
        $entry['handlers'][] = $item;
    }
    $output[$path] = $entry;
}
echo wp_json_encode(array('scope' => 'REGISTRATION_ONLY_NO_HANDLER_CALL_NO_CHAT_LEAD_MODEL_OR_INVENTORY_REQUEST', 'routes' => $output));
'''


def main():
    remote = MODULE.Remote()
    try:
        result = json.loads(remote.run(MODULE.WP + " eval " + shlex.quote(CODE)))
        request = urllib.request.Request("https://harmat22.hu/wp-json/harmat/v1", headers={"User-Agent": "HarmatAssistantRouteAudit/1.0"})
        try:
            with urllib.request.urlopen(request, timeout=20) as response:
                status, body = response.status, response.read()
        except urllib.error.HTTPError as error:
            status, body = error.code, error.read()
        try:
            namespace = json.loads(body)
            result["public_namespace_index"] = {"status": status, "code": namespace.get("code"),
                                                "configured_routes_visible": [path for path in ("/harmat/v1/ai-units", "/harmat/v1/ai-lead") if path in namespace.get("routes", {})]}
        except json.JSONDecodeError:
            result["public_namespace_index"] = {"status": status, "json": False, "retry": False}
        result["limitation"] = "No authenticated business handler invoked; CLI registration and public namespace metadata only."
        directory = ROOT / "outputs/2026-10-15-facebook-messenger"
        directory.mkdir(parents=True, exist_ok=True)
        with (directory / datetime.now(timezone.utc).strftime("route-audit-%H%M%S-%f.json")).open("x", encoding="utf-8") as handle:
            json.dump(result, handle, indent=2)
        print(json.dumps(result, indent=2))
    finally:
        remote.close()


if __name__ == '__main__':
    main()
