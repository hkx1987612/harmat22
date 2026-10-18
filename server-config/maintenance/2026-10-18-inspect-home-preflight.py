"""Private preflight diagnostic only; never writes production sources or activates."""
import importlib.util
import json
from pathlib import Path
import re
import shlex
import sys
spec=importlib.util.spec_from_file_location('home_deploy',Path(__file__).with_name('2026-10-18-deploy-home-family.py'))
D=importlib.util.module_from_spec(spec);spec.loader.exec_module(D)
r=D.H.Remote()
try:
    rows=[x for x in r.sftp.listdir_attr(D.H.BACKUPS) if re.fullmatch('home-family-[a-f0-9]{32}',x.filename)]
    row=max(rows,key=lambda x:x.st_mtime);backup=D.H.BACKUPS+'/'+row.filename
    if sys.argv[1:]==['--status']:
        print(json.dumps({'backup':backup,'private_files':len(r.sftp.listdir(backup)),
            'preflight_complete':not D.absent(r,backup+'/preflight-proof.json'),
            'release_complete':not D.absent(r,backup+'/release-proof.json'),
            'controller_present':not D.absent(r,D.DIR+'/'+D.MAIN)}))
        sys.exit(0)
    if sys.argv[1:]!=['--diagnose']:
        raise RuntimeError('Use --status for read-only metadata or --diagnose for private preflight')
    code=(Path(__file__).with_name(D.NATIVE)).read_bytes().replace(b'\r\n',b'\n')
    diagnostic=backup+'/native-diagnostic-'+D.sha(code)[:12]+'.php'
    if D.absent(r,diagnostic):r.write_new(diagnostic,code)
    D.require(r.read(diagnostic,private=True)==code,'Diagnostic already differs')
    r.lint(diagnostic)
    command=D.H.PHP+' '+shlex.quote(diagnostic)+' '+shlex.quote(backup+'/'+D.MAIN)
    stdin,stdout,stderr=r.client.exec_command(command,timeout=120);stdin.close()
    out=stdout.read().decode('utf-8');err=stderr.read().decode('utf-8');status=stdout.channel.recv_exit_status()
    error=json.loads(r.read(backup+'/native-error.json',private=True)) if not D.absent(r,backup+'/native-error.json') else None
    detail=json.loads(r.read(backup+'/home-failure.json',private=True)) if not D.absent(r,backup+'/home-failure.json') else None
    if not D.absent(r,backup+'/home-failed-baseline.html'):
        local=D.ROOT/'outputs/2026-10-18-home-family/live-original/home-failed-baseline.html'
        local.write_bytes(r.read(backup+'/home-failed-baseline.html',private=True))
    print(json.dumps({'backup':backup,'exit':status,'stdout_bytes':len(out),'stdout_end':out[-1000:],'native_render_exists':not D.absent(r,backup+'/native-render.html'),'detail':detail if status else None,'error':error if status else None,'stderr':err[-1000:]},ensure_ascii=True))
finally:r.close()
