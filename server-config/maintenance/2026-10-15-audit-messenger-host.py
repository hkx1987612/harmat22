"""Read-only existing-host audit; no secrets/customer content or service changes."""
import ipaddress
import json
import os
from pathlib import Path

import paramiko

CODE = r'''
import hashlib,json,socket,subprocess,urllib.request
from pathlib import Path
files = ['/opt/harmat22-ai/app.py', '/www/wwwroot/harmat22-ai/widget.js',
 '/etc/systemd/system/harmat22-ai.service', '/etc/harmat22-ai.env',
 '/etc/systemd/system/harmat22-ai.service.d/10-wp-lead.conf',
 '/www/server/panel/vhost/nginx/jiankong.nogakft.hu.conf']
result = {'scope':'READ_ONLY_NO_SECRET_OUTPUT_NO_CUSTOMER_DATA', 'protected':{}}
for name in files:
 p=Path(name); s=p.lstat()
 if not p.is_file() or p.is_symlink(): raise RuntimeError('Unexpected source identity')
 result['protected'][name]={'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),'mode':oct(s.st_mode & 0o777),'size':s.st_size}
result['service_active']=subprocess.check_output(['systemctl','is-active','harmat22-ai.service'],text=True).strip()
result['python']=subprocess.check_output(['python3','--version'],text=True).strip()
result['new_directory_absent']=not Path('/opt/harmat-messenger').exists()
result['new_unit_absent']=not Path('/etc/systemd/system/harmat-messenger.service').exists()
result['new_proxy_absent']='/harmat-messenger/' not in Path(files[-1]).read_text()
with socket.socket() as s:
 try: s.bind(('127.0.0.1',3024)); result['loopback_3024_available']=True
 except OSError: result['loopback_3024_available']=False
memory={}
for line in Path('/proc/meminfo').read_text().splitlines():
 k,v=line.split(':',1)
 if k in ('MemTotal','MemAvailable'): memory[k]=int(v.split()[0])
result['memory_kib']=memory
result['load_average']=Path('/proc/loadavg').read_text().split()[:3]
for label,url in [('local','http://127.0.0.1:3022/health'),('public','https://jiankong.nogakft.hu/harmat22-ai-api/health')]:
 if label == 'public':
  result['public_health']={'checked':False,'reason':'Do not repeat the separately observed public HTTP403 during local health audits'}
  continue
 try:
  with urllib.request.urlopen(url,timeout=10) as response:
   data=json.load(response)
   result[label+'_health']={'status':response.status,**{k:data[k] for k in ('ok','service','version','provider') if k in data}}
 except Exception as error:
  result[label+'_health']={'verified':False,'error_type':type(error).__name__,'http_status':getattr(error,'code',None)}
print(json.dumps(result))
'''


def connect():
    host = os.environ.get('HARMAT_MESSENGER_SSH_HOST', '')
    try:
        address = ipaddress.ip_address(host)
        if address not in ipaddress.ip_network('100.64.0.0/10'):
            raise ValueError()
    except ValueError:
        raise RuntimeError('Operator must configure the approved private SSH host; never store it in Git') from None
    vault = Path.home() / '.nogakft-vault/home-panel/ssh'
    client = paramiko.SSHClient()
    client.load_host_keys(str(vault / 'singapore_known_hosts'))
    client.set_missing_host_key_policy(paramiko.RejectPolicy())
    try:
        key = paramiko.RSAKey.from_private_key_file(str(vault / 'singapore_harmat_codex'),
            password=(vault / 'singapore_key_pass.txt').read_text().strip())
        client.connect(host, username='root', pkey=key, allow_agent=False,
            look_for_keys=False, timeout=12, auth_timeout=12, banner_timeout=12)
        return client
    except BaseException:
        client.close()
        raise


def main():
    client = connect()
    try:
        stdin, stdout, stderr = client.exec_command('python3 -', timeout=60)
        stdin.write(CODE)
        stdin.channel.shutdown_write()
        body, error = stdout.read(), stderr.read()
        if stdout.channel.recv_exit_status() != 0 or error:
            raise RuntimeError('Host read-only audit failed; private diagnostics not printed')
        data = json.loads(body)
        output = Path(__file__).resolve().parents[2] / 'outputs/2026-10-15-facebook-messenger'
        output.mkdir(parents=True, exist_ok=True)
        (output / 'host-audit-latest.json').write_text(json.dumps(data, indent=2), encoding='utf-8')
        print(json.dumps(data, indent=2))
    finally:
        client.close()


if __name__ == '__main__':
    main()
