"""Replace the homepage model figure with the official roof-garden rendering."""
import importlib.util
import json
from pathlib import Path
import subprocess

HERE=Path(__file__).resolve().parent
ROOT=HERE.parents[1]
spec=importlib.util.spec_from_file_location('roof_garden_transport',HERE/'2026-10-20-update-home-contact-image.py')
M=importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
D,S,H,Q=M.D,M.S,M.H,M.Q
require,sha=M.require,M.sha
OLD_SHA='65f29b83d86f96ac4a121049a90c7c67ffdeca07bb05d0dcac6542aebfc98651'
NEW_SHA='dafbf493b166909ded9f52dd01b7015f2edffaa6aa6aa6f9e8dd3fa3af21bb70'
OLD_FIGURE='<figure><img src="https://harmat22.hu/wp-content/mu-plugins/harmat-contact-family/showroom-model.jpg" width="1000" height="750" alt="A Harmat Lakópark épületmakettje a bemutatóteremben" data-i18n-alt="modelAlt" loading="lazy" decoding="async"><figcaption data-i18n="modelCaption">Helyszíni felvétel · a lakópark makettje a bemutatóteremben</figcaption></figure>'
NEW_FIGURE='<figure><img src="https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-8-harmat-1280.webp" width="1280" height="720" alt="A Harmat Lakópark tervezett tetőkertje" data-i18n-alt="rendering" loading="lazy" decoding="async"><figcaption data-i18n="rendering">Látványterv</figcaption></figure>'
BLOCK=("        $model_image='"+OLD_FIGURE+"';\n"
       '        if (substr_count($replacement,$model_image)!==1) return $html;\n'
       '        $replacement=str_replace($model_image,\n'
       "            '"+NEW_FIGURE+"',\n"
       '            $replacement);\n').encode()
original_public=M.public_proof


def validate_delta(old,new):
    require(sha(old)==OLD_SHA and sha(new)==NEW_SHA,'Exact reviewed source pair required')
    require(new.count(BLOCK)==1,'Exactly one model image block required')
    require(new.replace(BLOCK,b'',1).replace(b' * Version: 1.0.18',b' * Version: 1.0.17',1)==old,
            'Only image block and version may change')


def bundle():
    old=subprocess.check_output(['git','show','d5975d8:wp-mu-plugins/'+D.MAIN],cwd=ROOT)
    new=S.read_local(D.MAIN)
    validate_delta(old,new)
    manifest=S.read_local(D.ASSET+'/manifest-six-r2.json')
    require(sha(manifest)=='b7ad673660278889725b832cf748656c5b4e59fc92220519e75117eb99f25280','Retained manifest changed')
    pins=json.loads(manifest)
    protected={D.ASSET+'/'+n:S.read_local(D.ASSET+'/'+n) for n in pins}
    require(all(sha(protected[D.ASSET+'/'+n])==v for n,v in pins.items()),'Retained assets changed')
    for name in ('manifest.json','manifest-scroll-play.json','manifest-six.json','manifest-six-r2.json'):
        protected[D.ASSET+'/'+name]=S.read_local(D.ASSET+'/'+name)
    for name in ('zz-harmat-home-assistant-inline.php','zz-harmat-home-video-fullscreen.php'):
        protected[name]=S.read_local(name)
    footer=S.read_local('harmat-migrated-snippets.php')
    raw=b''.join(line if i in (4,175,176,177) else line.replace(b'\n',b'\r\n')
                 for i,line in enumerate(footer.splitlines(keepends=True)))
    require(sha(raw)=='f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077','Exact retained footer required')
    protected['harmat-migrated-snippets.php']=raw
    helpers={n:(HERE/n).read_bytes().replace(b'\r\n',b'\n') for n in (D.TEST,D.CHECKER)}
    digest=sha(Q.P.encoded({'old':sha(old),'new':sha(new),'protected':{p:sha(b) for p,b in protected.items()},
                           'helpers':{p:sha(b) for p,b in helpers.items()},'snapshot_policy':M.A.CACHE_PREDICATE}))
    return old,new,protected,helpers,digest


def public_proof(baseline,active):
    proof=original_public(baseline,active)
    for route in ('/property/a1-f-l1/','/virtualis-lakasvalaszto/',
                  '/virtualis-lakasvalaszto-elso-utem/','/virtualis-lakasvalaszto-a1-epulet/'):
        body,kind=Q.get('https://harmat22.hu'+route)
        require('text/html' in kind and b'id="harmat-home-family"' not in body,'Unrelated route health/ownership')
    proof.update({'health_routes':7,'replacement':'official_roof_garden_rendering'})
    return proof


def configure():
    M.bundle=bundle
    M.PREFIX=H.BACKUPS+'/home-roof-garden-'
    M.OLD_FIGURE,M.NEW_FIGURE=OLD_FIGURE,NEW_FIGURE
    M.IMAGE_URL='https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-8-harmat-1280.webp'
    M.IMAGE_MIME='image/webp'
    M.IMAGE_BYTES=271163
    M.IMAGE_SHA='5b6578a003743053822c80df755a074977425f7803b3f468e8fd9ab42819c516'
    M.public_proof=public_proof
    M.__doc__=__doc__
    return M


if __name__=='__main__': configure().main()
