"""Versioned homepage autoplay-loop runtime; default offline, source-only recovery."""
import importlib.util
import json
from pathlib import Path
import subprocess

HERE=Path(__file__).resolve().parent
ROOT=HERE.parents[1]
spec=importlib.util.spec_from_file_location('loop_prior',HERE/'2026-10-20-deploy-home-six.py')
P=importlib.util.module_from_spec(spec)
spec.loader.exec_module(P)
S,D,H,Q=P.S,P.D,P.H,P.Q
require,sha=P.require,P.sha
JS,MANIFEST='home-family-loop.js','manifest-loop.json'
HOME,ASSET=S.HOME,S.ASSET
PINS={JS:'fb9e31a153fde3e6264b236abf435ddb4de424ab08b85e05f372a24f230e0f88',
      MANIFEST:'0ea9f878ae7eacf6602af0ae0bda89e899ebea73ec6ca968c59d29fc07f5d998',
      D.MAIN:'36a2f2ff3cba033228dd5d12cd70833c6803ac9895d502501900347b3c34d29a'}
OLD='dafbf493b166909ded9f52dd01b7015f2edffaa6aa6aa6f9e8dd3fa3af21bb70'


def release():
    old=subprocess.check_output(['git','show','e683c90:wp-mu-plugins/'+D.MAIN],cwd=ROOT)
    new=S.read_local(D.MAIN)
    js,manifest=(S.read_local(D.ASSET+'/'+n) for n in (JS,MANIFEST))
    require(sha(old)==OLD and {D.MAIN:sha(new),JS:sha(js),MANIFEST:sha(manifest)}==PINS,
            'Exact reviewed loop bundle required')
    previous=S.read_local(D.ASSET+'/manifest-six-r2.json')
    require(sha(previous)=='b7ad673660278889725b832cf748656c5b4e59fc92220519e75117eb99f25280','Prior manifest changed')
    pins=json.loads(previous)
    require(json.loads(manifest)=={**pins,JS:sha(js)},'All original ten assets must remain')
    expected=old.replace(b' * Version: 1.0.18',b' * Version: 1.0.19',1)
    expected=expected.replace(b"'/manifest-six-r2.json'",b"'/manifest-loop.json'",1)
    expected=expected.replace(sha(previous).encode(),sha(manifest).encode(),1)
    expected=expected.replace(b"$pins['home-family-six-r2.js']))",b"$pins['home-family-six-r2.js'],$pins['home-family-loop.js']))",1)
    expected=expected.replace(b"array('pannellum.js','home-family-six-r2.js')",b"array('pannellum.js','home-family-loop.js')",1)
    require(new==expected,'Only version/manifest/runtime selection may change in controller')
    protected={D.ASSET+'/'+n:S.read_local(D.ASSET+'/'+n) for n in pins}
    require(all(sha(protected[D.ASSET+'/'+n])==value for n,value in pins.items()),'Prior pinned asset changed')
    for name in ('manifest.json','manifest-scroll-play.json','manifest-six.json','manifest-six-r2.json'):
        protected[D.ASSET+'/'+name]=S.read_local(D.ASSET+'/'+name)
    for name,pin in {
        'zz-harmat-home-assistant-inline.php':'12f6ea88ed68b548aae279496b57402ed5d96c123788d9f1b176e346edd701ca',
        'zz-harmat-home-apartment-rotation.php':'a505545d3c548206f04db2360847e56e48b44102e707c846a100795fd66bc957',
        'zz-harmat-home-video-fullscreen.php':'38383bbaacb876e0b3de4b63d22fc23c6f29679784e3d4cf99e56d9ddd9d4580',
        'zz-harmat-assistant-presentation.php':'b2d54a66500ad52211f23f5c23d20de5c3c7d37a94573f5bf3979ea3dbd99115',
        'harmat-assistant-presentation/assistant-presentation.js':'92a67eef8d00ef436270d791f03bfd8dbce06c57e319bde062094d49eca5b9b7',
        'harmat-assistant-presentation/assistant-presentation.css':'19f8692b310b83c1ecb0535d7e30846782f7736b5304065382f5f6bc21e4496c',
        'harmat-assistant-presentation/LICENSE-lucide.txt':'bde82befa48737eb050cbf8b6011cc803536c892af037e724f06dfb729b3ea3e',
    }.items():
        protected[name]=S.read_local(name)
        require(sha(protected[name])==pin,'Protected UI owner changed: '+name)
    footer=S.read_local('harmat-migrated-snippets.php')
    raw=b''.join(line if i in (4,175,176,177) else line.replace(b'\n',b'\r\n')
                 for i,line in enumerate(footer.splitlines(keepends=True)))
    require(sha(raw)=='f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077','Exact raw footer required')
    protected['harmat-migrated-snippets.php']=raw
    edits={ASSET+'/'+JS:(None,js),ASSET+'/'+MANIFEST:(None,manifest),HOME:(old,new)}
    helpers={name:(HERE/name).read_bytes().replace(b'\r\n',b'\n') for name in (D.TEST,D.CHECKER)}
    recorded={p:{'old':None if a is None else sha(a),'new':sha(b)} for p,(a,b) in edits.items()}
    digest=sha(Q.P.encoded({'sources':recorded,'protected':{p:sha(b) for p,b in protected.items()},
                            'helpers':{p:sha(b) for p,b in helpers.items()},'snapshot_policy':P.CACHE_PREDICATE}))
    return edits,helpers,protected,recorded,digest


def main_html(text):
    import re
    found=re.findall('<main id="main" class="site-main">[\\s\\S]*?</main>',text)
    require(len(found)==1,'Exactly one homepage main required')
    return found[0]


def public(remote,baseline,protected,edits,active):
    data,mime=Q.get(D.URL)
    text,previous=data.decode('utf-8'),baseline.decode('utf-8')
    require('text/html' in mime,'Homepage MIME')
    proof=D.assert_home(text,previous)
    require(main_html(text)==main_html(previous),'Homepage markup/layout must remain byte-exact')
    expected=JS if active else 'home-family-six-r2.js'
    other='home-family-six-r2.js' if active else JS
    require(text.count('/'+expected+'?ver=')==1 and '/'+other+'?ver=' not in text,'Wrong playback runtime')
    asset,kind=Q.get('https://harmat22.hu/wp-content/mu-plugins/'+D.ASSET+'/'+expected)
    wanted=edits[ASSET+'/'+JS][1] if active else protected[D.ASSET+'/'+expected]
    require(sha(asset)==sha(wanted),'Public playback runtime differs')
    for marker in ('harmat-home-video-fullscreen-js','harmat-home-assistant-inline-script','harmat-home-rotation-script'):
        require(text.count('id="'+marker+'"')==1,'Retained feature missing: '+marker)
    for route in ('/lakaskereso/','/elerhetosegeink/','/property/a1-f-l1/',
                  '/virtualis-lakasvalaszto/','/virtualis-lakasvalaszto-elso-utem/','/virtualis-lakasvalaszto-a1-epulet/'):
        body,kind=Q.get('https://harmat22.hu'+route)
        require('text/html' in kind and ('/'+JS+'?ver=').encode() not in body,'Runtime leaked outside homepage')
    proof.update({'health_routes':7,'main_unchanged':True,'playback':'automatic_loop_play_only_fallback' if active else 'manual_pause',
                  'submission':False})
    return proof


def configure():
    S.JS,S.MANIFEST=JS,MANIFEST
    S.PREFIX=H.BACKUPS+'/home-loop-'
    S.release,S.public,S.state=release,public,P.state
    S.__doc__=__doc__
    return S


if __name__=='__main__': configure().main()
