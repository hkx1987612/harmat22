"""Private host fixtures + one anonymous public GET; no service/proxy changes."""
import argparse
import hashlib
import importlib.util
import json
import re
import shlex
import stat
import uuid
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('messenger_host_access', Path(__file__).with_name('2026-10-15-audit-messenger-host.py'))
ACCESS = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(ACCESS)
SOURCES = ('inventory_reader.py', 'test_inventory_reader.py')
PARENT = '/root/harmat-messenger-backups'
CHECK = r'''
import hashlib,json,sys,unittest
from datetime import datetime,timezone
from pathlib import Path
import inventory_reader
suite = unittest.defaultTestLoader.loadTestsFromName('test_inventory_reader')
result = unittest.TextTestRunner(stream=sys.stderr,verbosity=0).run(suite)
report={'isolated_tests':result.testsRun,'isolated_pass':result.wasSuccessful(),
 'scope':'PRIVATE_FIXTURES_ONE_ANONYMOUS_PUBLIC_GET_NO_CHAT_MODEL_CRM_OR_SERVICE_CHANGES'}
if not result.wasSuccessful(): raise RuntimeError('Isolated host tests failed')
try:
 data=inventory_reader.read_inventory()
 report['public_read']={'status':'PASS','items':len(data['items']),'generated_at':data['generated_at'],
  'fields_per_item':len(inventory_reader.ITEM_FIELDS),'hidden_rows':sum(x['price_huf'] is None for x in data['items']),
  'protected_area_47_83':any(x['code']=='A3-4-L5' and abs(x['sales_area_m2']-47.83)<0.00001 for x in data['items']),
  'full_inventory_exported':False}
except inventory_reader.InventoryUnavailable:
 report['public_read']={'status':'UNAVAILABLE_FAIL_CLOSED','full_inventory_exported':False}
print(json.dumps(report))
'''
DIAGNOSTIC = r'''
import json,sys,time
from datetime import datetime,timezone
sys.path.insert(0,sys.argv[1])
import inventory_reader as reader
result={'scope':'ONE_READ_ONLY_DIAGNOSTIC_NO_RETRY_OR_BYPASS'}
reader.TIMEOUT_SECONDS=int(sys.argv[2])
result['diagnostic_socket_timeout_seconds']=reader.TIMEOUT_SECONDS
started=time.monotonic()
try:
 with reader._public_transport() as response:
  result['http_status']=response.status
  result['content_type']=response.getheader('Content-Type','')[:100]
  result['content_encoding']=response.getheader('Content-Encoding','identity')[:30]
  payload=response.read(reader.MAX_RESPONSE_BYTES+1)
  result['bytes']=len(payload)
  if response.status == 200:
   try:
    data=reader.validate_inventory(payload,now=datetime.now(timezone.utc))
    result['validation']='PASS';result['items']=len(data['items'])
   except reader.InventoryUnavailable: result['validation']='FAIL_CLOSED'
except Exception as error:
 result['connection_error_type']=type(error).__name__
 if hasattr(error,'verify_code'):result['certificate_verify_code']=error.verify_code
result['elapsed_seconds']=round(time.monotonic()-started,3)
print(json.dumps(result))
'''


def sha(data):
    return hashlib.sha256(data).hexdigest()


def run(client, command, *, test_stderr=False):
    stdin, stdout, stderr = client.exec_command(command, timeout=60)
    stdin.close()
    body, errors = stdout.read(), stderr.read()
    if stdout.channel.recv_exit_status() != 0 or errors and not test_stderr:
        raise RuntimeError('Private host command failed; output not disclosed')
    return body.decode('utf-8').strip()


def audit(client):
    stdin, stdout, stderr = client.exec_command('python3 -', timeout=60)
    stdin.write(ACCESS.CODE)
    stdin.channel.shutdown_write()
    body, errors = stdout.read(), stderr.read()
    if stdout.channel.recv_exit_status() != 0 or errors:
        raise RuntimeError('Private source/health read failed')
    return json.loads(body)


def private_dir(sftp, path):
    attributes = sftp.lstat(path)
    if (not stat.S_ISDIR(attributes.st_mode) or stat.S_IMODE(attributes.st_mode) != 0o700
            or attributes.st_uid != 0 or sftp.normalize(path) != path):
        raise RuntimeError('Unexpected private backup directory identity')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--private-stage-and-test', action='store_true')
    parser.add_argument('--inspect', metavar='PRIVATE_BACKUP')
    parser.add_argument('--diagnostic-timeout-seconds', type=int, choices=(3,8), default=3)
    args = parser.parse_args()
    sources = {name: (ROOT / 'server-config/messenger-prototype/nogakft' / name).read_bytes().replace(b'\r\n', b'\n') for name in SOURCES}
    sources['check.py'] = CHECK.encode()
    for name, source in sources.items():
        compile(source, name, 'exec')
    if not args.private_stage_and_test and not args.inspect:
        print(json.dumps({'scope':'OFFLINE_ONLY','sources':{name:sha(data) for name,data in sources.items()}}))
        return
    client = ACCESS.connect()
    try:
        if args.inspect:
            if args.private_stage_and_test or not re.fullmatch(re.escape(PARENT) + '/harmat-messenger-reader-[a-f0-9]{32}', args.inspect):
                raise RuntimeError('Exact private read-only backup required')
            with client.open_sftp() as sftp:
                private_dir(sftp, args.inspect)
                with sftp.open(args.inspect + '/inventory_reader.py','rb') as handle:
                    if handle.read() != sources['inventory_reader.py']:
                        raise RuntimeError('Private reader source drift')
            print(run(client,'python3 -B -c ' + shlex.quote(DIAGNOSTIC) + ' ' + shlex.quote(args.inspect) + ' ' + str(args.diagnostic_timeout_seconds)))
            return
        before = audit(client)
        if before['service_active'] != 'active' or before['local_health'].get('status') != 200:
            raise RuntimeError('Existing service health not proved before private test')
        path = PARENT + '/harmat-messenger-reader-' + uuid.uuid4().hex
        with client.open_sftp() as sftp:
            try:
                private_dir(sftp, PARENT)
            except FileNotFoundError:
                sftp.mkdir(PARENT, 0o700)
                private_dir(sftp, PARENT)
            sftp.mkdir(path, 0o700)
            private_dir(sftp, path)
            print(json.dumps({'scope':'PRIVATE_DIRECTORY_CREATED_NO_SERVICE_CHANGE','path':path}), flush=True)
            manifest = {'utc':datetime.now(timezone.utc).isoformat(),'scope':'PRIVATE_STAGING_ONLY',
                'protected_before':before['protected'],'source_sha256':{name:sha(data) for name,data in sources.items()},
                'no_existing_file_replaced':True,'no_service_proxy_or_credential_change':True}
            sources['manifest.json'] = json.dumps(manifest, indent=2).encode()
            for name, data in sources.items():
                stage = path + '/' + name + '.stage'
                with sftp.open(stage, 'wx') as handle:
                    handle.write(data)
                sftp.chmod(stage, 0o600)
                with sftp.open(stage, 'rb') as handle:
                    if handle.read() != data:
                        raise RuntimeError('Private staged bytes mismatch')
                if name.endswith('.py'):
                    command = "import pathlib; p=pathlib.Path(__import__('sys').argv[1]); compile(p.read_bytes(),p.name,'exec')"
                    run(client, 'python3 -B -c ' + shlex.quote(command) + ' ' + shlex.quote(stage))
                sftp.rename(stage, path + '/' + name)
            result = json.loads(run(client, 'python3 -B ' + shlex.quote(path + '/check.py'), test_stderr=True))
            after = audit(client)
            if before['protected'] != after['protected'] or after['local_health'].get('status') != 200 or after['service_active'] != 'active':
                raise RuntimeError('Protected source/health changed; investigate without restoring unrelated data')
            result.update(private_backup=path,existing_sources_unchanged=True,existing_local_service_healthy=True,
                          public_existing_health=after['public_health'])
            with sftp.open(path + '/result.json', 'wx') as handle:
                handle.write(json.dumps(result, indent=2).encode())
            sftp.chmod(path + '/result.json', 0o600)
        print(json.dumps(result, indent=2))
    finally:
        client.close()


if __name__ == '__main__':
    main()
