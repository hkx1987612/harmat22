"""User-approved three payment choices; FAQ only, no quotes or contract edits."""
import importlib.util
import json
import re
from pathlib import Path
import shlex
import subprocess

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('payment_plans_previous', HERE / '2026-10-18-update-assistant-payment.py')
A = importlib.util.module_from_spec(spec)
spec.loader.exec_module(A)
U = A.U
ROOT, SOURCE, TARGET = A.ROOT, A.SOURCE, A.TARGET
BASE = 'c25ba61'
OLD_LF_SHA = '6a244979f1aa73d1daea0e0852db18c4d7c44e56fdff3a1ecd688f8535dca851'
OLD_SHA = '5863b7a8ec69a4e148ab50e4567c3a118af2d797285650f0c1df72df302fe117'
NEW_LF_SHA = 'ff702f012ce13c7a7efdb0f25e7306cd5fbbb63cf29a14608645583451392b30'
NEW_SHA = 'e520fd3549820c6b77f798f6f3cb2ec2ae28c3bf206054866f9a48de7f4ff46d'
PREFIX = U.H.BACKUPS + '/assistant-payment-plans-'
payment_block = A.payment_block


def method_block(data, name):
    marker = ('    private function ' + name + '($' + ('lang' if name == 'payment_schedule_answer' else 'text') + ') {').encode()
    U.require(data.count(marker) == 1, 'Exact FAQ method required')
    start = data.index(marker)
    end = data.index(b'\n    }', start) + len(b'\n    }')
    return data[start:end]


def answers(data):
    values = re.findall(rb"\$this->u\('((?:[^'\\]|\\.)*)'\)", payment_block(data))
    U.require(len(values) == 3, 'Three static plans translations required')
    return dict(zip(('hu', 'zh', 'en'), (json.loads('"' + v.decode('ascii').replace("\\'", "'") + '"') for v in values)))


def validate(original, candidate):
    U.require(U.sha(original) == OLD_SHA and U.sha(candidate) == NEW_SHA, 'Pinned raw payment plans sources required')
    restored = candidate
    for name in ('payment_schedule_answer', 'is_payment_schedule_question'):
        restored = restored.replace(method_block(candidate, name), method_block(original, name), 1)
    restored = restored.replace(b"const VERSION = '0.4.2'", b"const VERSION = '0.4.1'", 1)
    U.require(restored == original, 'Changes outside payment answer/version refused')


def local_release():
    old_lf = subprocess.check_output(['git', 'show', BASE + ':' + SOURCE], cwd=ROOT)
    new_lf = (ROOT / SOURCE).read_bytes().replace(b'\r\n', b'\n')
    U.require(U.sha(old_lf) == OLD_LF_SHA and U.sha(new_lf) == NEW_LF_SHA, 'Reviewed payment plans LF sources required')
    original = b''.join(line if not line.endswith(b'\n') or any(a <= i <= b for a, b in A.LF_ONLY)
                        else line[:-1] + b'\r\n' for i, line in enumerate(old_lf.splitlines(keepends=True)))
    candidate = original
    for name in ('payment_schedule_answer', 'is_payment_schedule_question'):
        candidate = candidate.replace(method_block(original, name), method_block(new_lf, name).replace(b'\n', b'\r\n'), 1)
    candidate = candidate.replace(b"const VERSION = '0.4.1'", b"const VERSION = '0.4.2'", 1)
    U.require(candidate.replace(b'\r\n', b'\n') == new_lf, 'Raw plans differ from reviewed LF source')
    validate(original, candidate)
    files, blobs, hashes, _ = U.D.bundle()
    return original, candidate, files, hashes, blobs[U.D.CHECKER]


def public(remote, files, hashes, baseline, updated):
    proof = U.D.public_proof(remote, files, hashes, baseline)
    original, candidate, _, _, _ = local_release()
    value = json.loads(remote.run(U.H.WP + ' eval ' + shlex.quote(A.REFLECT)))
    U.require(value == {'version': '0.4.2' if updated else '0.4.1', 'answers': answers(candidate if updated else original)},
              'Actual pure payment plans mismatch')
    proof['payment_plans'] = {'languages': 3, 'plans': 3 if updated else 1, 'no_ask_dispatch_or_lead': True}
    return proof


def configure():
    A.local_release, A.public, A.validate = local_release, public, validate
    A.PREFIX = PREFIX
    A.configure()
    U.__doc__ = __doc__
    return U


if __name__ == '__main__':
    configure().main()
