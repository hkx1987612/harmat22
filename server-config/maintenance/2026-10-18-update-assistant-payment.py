"""Three-language payment FAQ copy only; backup/atomic update, no saved offer edits."""
import importlib.util
import json
from pathlib import Path
import re
import shlex
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('payment_copy_transport', HERE / '2026-10-18-update-home-header-flow.py')
U = importlib.util.module_from_spec(spec)
spec.loader.exec_module(U)
BASE = '48fd938'
SOURCE = 'wp-plugins/harmat-local-assistant/harmat-local-assistant.php'
TARGET = U.H.LIVE + '/wp-content/plugins/harmat-local-assistant/harmat-local-assistant.php'
OLD_LF_SHA = 'c5257171792e9ed5c8c16b204e5d41b91bf6713ff23e8ad6ce9d340977decbb5'
NEW_LF_SHA = '6a244979f1aa73d1daea0e0852db18c4d7c44e56fdff3a1ecd688f8535dca851'
OLD_SHA = '930b853018dca8bc230430498bd8df99efac67e05a3e99bda422d17f78568302'
NEW_SHA = '5863b7a8ec69a4e148ab50e4567c3a118af2d797285650f0c1df72df302fe117'
# Observed legacy zero-based LF-only runs; all other terminated lines are CRLF.
# Reconstruct and hash the exact original, never normalize the production file.
LF_ONLY = ((5,5),(49,49),(54,56),(122,122),(646,648),(736,736),(854,862),
           (865,865),(874,876),(878,881),(883,883),(887,901),(970,970),
           (989,991),(1023,1025),(1033,1039),(1065,1065),(1174,1175),
           (1179,1188),(1897,1897),(1966,1969),(1998,2001),(2282,2284),
           (2407,2407),(2422,2422),(2437,2437),(2462,2462),(2562,2572))
PREFIX = U.H.BACKUPS + '/assistant-payment-copy-'
original_target, original_state = U.target, U.state
PROTECTED = ('zz-harmat-home-family.php', 'zz-harmat-construction-updates.php', 'zz-harmat-assistant-live-data.php')
REFLECT = """if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('CLI only'); }
$r = new ReflectionClass('Harmat_Local_Assistant');
$o = $r->newInstanceWithoutConstructor();
$m = $r->getMethod('payment_schedule_answer'); $v = array();
foreach (array('hu','zh','en') as $lang) { $v[$lang] = $m->invoke($o,$lang); }
echo wp_json_encode(array('version'=>Harmat_Local_Assistant::VERSION,'answers'=>$v));"""


def payment_block(data):
    marker = b'    private function payment_schedule_answer($lang) {'
    U.require(data.count(marker) == 1, 'Exact payment method required')
    start = data.index(marker)
    end = data.index(b'\n    }', start) + len(b'\n    }')
    return data[start:end]


def answers(data):
    values = re.findall(rb"\$this->u\('([^']*)'\)", payment_block(data))
    U.require(len(values) == 3, 'Three static payment translations required')
    return dict(zip(('hu', 'zh', 'en'), (json.loads('"' + v.decode('ascii') + '"') for v in values)))


def validate(original, candidate):
    U.require(U.sha(original) == OLD_SHA, 'Pinned raw original assistant required')
    U.require(U.sha(candidate) == NEW_SHA, 'Pinned raw payment copy required')
    restored = candidate.replace(payment_block(candidate), payment_block(original), 1)
    restored = restored.replace(b"const VERSION = '0.4.1'", b"const VERSION = '0.4.0'", 1)
    U.require(restored == original, 'Changes outside payment answer/version refused')


def local_release():
    old_lf = subprocess.check_output(['git', 'show', BASE + ':' + SOURCE], cwd=ROOT)
    new_lf = (ROOT / SOURCE).read_bytes().replace(b'\r\n', b'\n')
    U.require(U.sha(old_lf) == OLD_LF_SHA and U.sha(new_lf) == NEW_LF_SHA, 'Reviewed LF sources required')
    original = b''.join(line if not line.endswith(b'\n') or any(a <= i <= b for a,b in LF_ONLY)
                        else line[:-1] + b'\r\n' for i,line in enumerate(old_lf.splitlines(keepends=True)))
    replacement = payment_block(new_lf).replace(b'\n', b'\r\n')
    candidate = original.replace(payment_block(original), replacement, 1)
    candidate = candidate.replace(b"const VERSION = '0.4.0'", b"const VERSION = '0.4.1'", 1)
    U.require(candidate.replace(b'\r\n', b'\n') == new_lf, 'Raw candidate differs from reviewed LF source')
    validate(original, candidate)
    files, blobs, hashes, _ = U.D.bundle()
    return original, candidate, files, hashes, blobs[U.D.CHECKER]


def target(remote, expected, metadata=None):
    remote.directory(U.H.LIVE + '/wp-content/plugins')
    remote.directory(TARGET.rsplit('/', 1)[0])
    return original_target(remote, expected, metadata)


def state(remote, files, hashes):
    result = original_state(remote, files, hashes)
    result['current_modules'] = {name: U.sha(remote.read(U.D.DIR + '/' + name)) for name in PROTECTED}
    return result


def public(remote, files, hashes, baseline, updated):
    proof = U.D.public_proof(remote, files, hashes, baseline)
    original, candidate, _, _, _ = local_release()
    result = json.loads(remote.run(U.H.WP + ' eval ' + shlex.quote(REFLECT)))
    U.require(result == {'version': '0.4.1' if updated else '0.4.0',
                         'answers': answers(candidate if updated else original)}, 'Actual pure payment answer mismatch')
    proof['payment_copy'] = {'languages': 3, 'second_due': '2027-05-31' if updated else '2026-12-31',
                             'no_ask_dispatch_or_lead': True}
    return proof


def configure():
    U.TARGET, U.PREFIX = TARGET, PREFIX
    # This copy has no rendered CSS/HTML change; skip only the shared style assertion.
    U.STYLE = 'ASSISTANT_PAYMENT_COPY_NOT_RENDERED'
    U.target, U.state, U.public = target, state, public
    U.local_release, U.__doc__ = local_release, __doc__
    return U


if __name__ == '__main__':
    configure().main()
