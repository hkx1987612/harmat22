"""Replace one homepage contact figure; default offline, no business writes."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import shlex
import subprocess
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
spec = importlib.util.spec_from_file_location('contact_image_prior', HERE/'2026-10-20-deploy-home-six.py')
A = importlib.util.module_from_spec(spec)
spec.loader.exec_module(A)
S, D, H, Q = A.S, A.D, A.H, A.Q
require, sha = A.require, A.sha
TARGET = A.HOME
PREFIX = H.BACKUPS+'/home-contact-image-'
OLD_SHA = '7d823dc258bd7e64ede2586455ce222000632d2f47aa5523df779542d0bf3dab'
NEW_SHA = 'd73fccd048b51e83f1d4b1ce301635751c25bc1ef8bbe7d521f14818b75e3f02'
OLD_FIGURE = '<figure><img src="https://harmat22.hu/wp-content/mu-plugins/harmat-contact-family/project-location.jpg" width="1000" height="750" alt="A Harmat utca 22. környéke helyszíni felvételen" data-i18n-alt="locationAlt" loading="lazy" decoding="async"><figcaption data-i18n="locationCaption">Helyszíni felvétel · Harmat utca és környéke</figcaption></figure>'
NEW_FIGURE = '<figure><img src="https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_5.jpg" width="3840" height="2160" alt="Kert és közösségi tér" data-i18n-alt="panoramaGallery5" loading="lazy" decoding="async"><figcaption data-i18n="rendering">Látványterv</figcaption></figure>'
IMAGE_URL = 'https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_5.jpg'
BLOCK = ("        $contact_image='"+OLD_FIGURE+"';\n"
         '        if (!is_string($replacement) || substr_count($replacement,$contact_image)!==1) return $html;\n'
         '        $replacement=str_replace($contact_image,\n'
         "            '"+NEW_FIGURE+"',\n"
         '            $replacement);\n').encode()


def validate_delta(old, new):
    require(sha(old) == OLD_SHA and sha(new) == NEW_SHA, 'Exact reviewed source pair required')
    require(new.count(BLOCK) == 1, 'Exactly one contact image rewrite required')
    require(new.replace(BLOCK, b'', 1).replace(b' * Version: 1.0.16', b' * Version: 1.0.15', 1) == old,
            'Only one image block and version may change')


def bundle():
    old = subprocess.check_output(['git', 'show', '3796857:wp-mu-plugins/'+D.MAIN], cwd=ROOT)
    new = S.read_local(D.MAIN)
    validate_delta(old, new)
    manifest = S.read_local(D.ASSET+'/manifest-six-r2.json')
    require(sha(manifest) == 'b7ad673660278889725b832cf748656c5b4e59fc92220519e75117eb99f25280', 'Original pins required')
    pins = json.loads(manifest)
    protected = {D.ASSET+'/'+n:S.read_local(D.ASSET+'/'+n) for n in pins}
    require(all(sha(protected[D.ASSET+'/'+n]) == h for n,h in pins.items()), 'Retained asset drift')
    for name in ('manifest.json','manifest-scroll-play.json','manifest-six.json','manifest-six-r2.json'):
        protected[D.ASSET+'/'+name] = S.read_local(D.ASSET+'/'+name)
    for name in ('zz-harmat-home-assistant-inline.php','zz-harmat-home-video-fullscreen.php','harmat-migrated-snippets.php'):
        protected[name] = S.read_local(name)
    # The existing footer deliberately retains its original mixed line endings.
    footer = protected['harmat-migrated-snippets.php']
    raw_footer = b''.join(line if i in (4,175,176,177) else line.replace(b'\n',b'\r\n')
                          for i,line in enumerate(footer.splitlines(keepends=True)))
    require(sha(raw_footer) == 'f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077',
            'Exact retained production footer bytes required')
    protected['harmat-migrated-snippets.php'] = raw_footer
    helpers = {n:(HERE/n).read_bytes().replace(b'\r\n',b'\n') for n in (D.TEST,D.CHECKER)}
    digest = sha(Q.P.encoded({'old':sha(old),'new':sha(new),'protected':{p:sha(b) for p,b in protected.items()},
                             'helpers':{p:sha(b) for p,b in helpers.items()},'snapshot_policy':A.CACHE_PREDICATE}))
    return old,new,protected,helpers,digest


def main_html(text):
    found = re.findall(r'<main id="main" class="site-main">[\s\S]*?</main>', text)
    require(len(found) == 1, 'One owned homepage main required')
    return found[0]


def public_proof(baseline, active):
    data,mime = Q.get(D.URL)
    text = data.decode('utf-8')
    require('text/html' in mime, 'Homepage MIME')
    proof = D.assert_home(text, baseline.decode('utf-8'))
    previous = main_html(baseline.decode('utf-8'))
    require(previous.count(OLD_FIGURE) == 1, 'Baseline contact figure changed')
    expected = previous.replace(OLD_FIGURE,NEW_FIGURE,1) if active else previous
    require(main_html(text) == expected, 'Homepage change exceeds exact figure')
    for route in ('/lakaskereso/','/elerhetosegeink/'):
        body,kind = Q.get('https://harmat22.hu'+route)
        require('text/html' in kind and b'id="harmat-home-family"' not in body, 'Unrelated route health/ownership')
    proof.update({'figure_only':True,'health_routes':3,'submission':False})
    return proof


def operate(parts, backup=None, reverse=False):
    old,new,protected,helpers,digest = parts
    fresh = backup is None
    backup = backup or PREFIX+uuid.uuid4().hex
    require(re.fullmatch(re.escape(PREFIX)+r'[a-f0-9]{32}',backup), 'Exact private backup path required')
    r = H.Remote()
    locked = changed = False
    try:
        r.directory(H.BACKUPS); r.directory(D.DIR); r.mkdir(D.LOCK); locked = True
        if fresh:
            meta = Q.metadata(r,TARGET,old)
            before = A.state(r,protected)
            baseline,mime = Q.get(D.URL)
            require('text/html' in mime and main_html(baseline.decode()).count(OLD_FIGURE) == 1, 'Baseline unavailable/drift')
            image,kind = Q.get(IMAGE_URL)
            require(kind.startswith('image/jpeg') and len(image) == 1041262
                    and sha(image) == '1079bc7f5ab4995a2fc1e5307a84a4ca19d3fa82d75c0b20cf28d323ecf3cf99',
                    'Selected existing image differs from inspected image')
            r.mkdir(backup); r.mkdir(backup+'/'+D.ASSET)
            for name,blob in protected.items():
                if name.startswith(D.ASSET+'/'): r.write_new(backup+'/'+name,blob)
            for name,blob in helpers.items(): r.write_new(backup+'/'+name,blob); r.lint(backup+'/'+name)
            r.write_new(backup+'/original-home.php',old); r.lint(backup+'/original-home.php')
            r.write_new(backup+'/candidate-home.php',new); r.lint(backup+'/candidate-home.php')
            r.write_new(backup+'/baseline.html',baseline)
            saved={'digest':digest,'metadata':meta,'before':before,'baseline':sha(baseline)}
            D.write_json(r,backup+'/manifest.json',saved)
            fixture = json.loads(r.run(H.PHP+' '+shlex.quote(backup+'/'+D.TEST)+' '+shlex.quote(backup+'/candidate-home.php')))
            require(fixture.get('status') == 'PASS' and fixture.get('checks') == 76, 'Controller fixture failed')
            filters = D.validate_native(r,backup)
            require(A.state(r,protected) == before and Q.metadata(r,TARGET,old) == meta, 'Preflight protected drift')
            print(json.dumps({'status':'PRIVATE_BACKUP_READY','backup':backup,'aggregate':digest,'controller':fixture}),flush=True)
            changed = True
            Q.replace(r,TARGET,old,new,meta); r.purge()
        else:
            r.directory(backup,private=True)
            saved=json.loads(r.read(backup+'/manifest.json',private=True))
            require(saved['digest'] == digest, 'Saved bundle mismatch')
            meta,before = tuple(saved['metadata']),saved['before']
            baseline=r.read(backup+'/baseline.html',private=True)
            require(sha(baseline) == saved['baseline'], 'Saved baseline mismatch')
            require(r.read(backup+'/original-home.php',private=True) == old
                    and r.read(backup+'/candidate-home.php',private=True) == new, 'Saved source mismatch')
            for name,blob in helpers.items():
                require(r.read(backup+'/'+name,private=True) == blob, 'Saved checker mismatch')
            require(Q.metadata(r,TARGET,new) == meta, 'Released source metadata mismatch')
            if reverse: Q.replace(r,TARGET,new,old,meta); r.purge()
        expected = old if reverse else new
        require(Q.metadata(r,TARGET,expected) == meta, 'Final source differs')
        proof=public_proof(baseline,not reverse)
        filters=D.validate_native(r,backup)
        require(A.state(r,protected) == before, 'Protected state changed; never restore business data')
        if fresh: D.write_json(r,backup+'/release.json',{'proof':proof,'filters':filters})
        print(json.dumps({'status':'RELEASED' if fresh else 'ROLLED_BACK_SOURCE_ONLY' if reverse else 'VERIFIED',
                          'backup':backup,'aggregate':digest,'proof':proof,'filters':filters}),flush=True)
    except BaseException:
        if changed:
            current=r.read(TARGET)
            require(current in (old,new), 'Foreign controller retained')
            if current == new: Q.replace(r,TARGET,new,old,meta); r.purge()
            print(json.dumps({'status':'OWNED_SOURCE_ROLLED_BACK','backup':backup}),flush=True)
        raise
    finally:
        try:
            if locked: r.sftp.rmdir(D.LOCK)
        finally: r.close()


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    mode=parser.add_mutually_exclusive_group()
    mode.add_argument('--deploy',action='store_true'); mode.add_argument('--verify'); mode.add_argument('--rollback')
    parser.add_argument('--expect'); args=parser.parse_args(); parts=bundle()
    if not any((args.deploy,args.verify,args.rollback)):
        print(json.dumps({'mode':'OFFLINE_NO_NETWORK','aggregate':parts[-1],'old':sha(parts[0]),'new':sha(parts[1])})); return
    require(args.expect == parts[-1], 'Exact reviewed aggregate required before connecting')
    operate(parts,args.verify or args.rollback,bool(args.rollback))


if __name__ == '__main__': main()
