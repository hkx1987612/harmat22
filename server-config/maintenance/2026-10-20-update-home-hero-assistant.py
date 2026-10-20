"""Move the existing homepage assistant below the two hero links; offline by default."""
import importlib.util
import json
from pathlib import Path
import re
import subprocess

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]


def load(name, filename):
    spec = importlib.util.spec_from_file_location(name, HERE/filename)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


U = load('hero_assistant_transport', '2026-10-19-update-home-hero-lower.py')
R = load('hero_assistant_protected', '2026-10-20-update-home-roof-garden.py')
D, H, Q = U.D, U.H, U.Q
require, sha = U.require, U.sha
OLD = '3bce85ddad7b4b59948293536b2fbd193bb21b820b6384c9bdbb937a529125bd'
NEW = '12f6ea88ed68b548aae279496b57402ed5d96c123788d9f1b176e346edd701ca'
RULE = b'#harmat-home-family #hhf-kezdolap #harmat-home-assistant-actions{'


def release():
    old = subprocess.check_output(['git','show','7f7dabd:wp-mu-plugins/'+U.V.MAIN], cwd=ROOT)
    new = R.S.read_local(U.V.MAIN)
    require(sha(old) == OLD and sha(new) == NEW, 'Exact reviewed source pair required')
    require(RULE not in old and new.count(RULE) == 1, 'Owned hero entry selector required')
    require(old.split(b"    echo <<<'HHAI_ASSETS'")[0].replace(b'1.0.1',b'1.0.2')
            == new.split(b"    echo <<<'HHAI_ASSETS'")[0], 'PHP guards must be retained')
    require(not re.search(rb'\b(?:wp_mail|update_option|update_post_meta|wp_insert_post|register_rest_route|wp_remote_\w+)\s*\(',new),
            'Presentation-only source required')
    _, _, protected, _, _ = R.bundle()
    protected.pop(U.V.MAIN)
    protected[D.MAIN] = R.S.read_local(D.MAIN)
    require(sha(protected[D.MAIN]) == R.NEW_SHA, 'Current homepage must be retained')
    for name in ('zz-harmat-assistant-presentation.php','harmat-assistant-presentation/assistant-presentation.css',
                 'harmat-assistant-presentation/assistant-presentation.js','harmat-assistant-presentation/LICENSE-lucide.txt'):
        protected[name] = R.S.read_local(name)
    helpers = {name:(HERE/name).read_bytes().replace(b'\r\n',b'\n') for name in (U.V.TEST,D.CHECKER)}
    descriptor = {'old':sha(old),'new':sha(new),'protected':{p:sha(b) for p,b in protected.items()},
                  'helpers':{p:sha(b) for p,b in helpers.items()},'snapshot_policy':R.M.A.CACHE_PREDICATE}
    return old,new,protected,helpers,descriptor,sha(Q.P.encoded(descriptor))


def state(remote, protected):
    value = R.M.A.state(remote, protected)
    value['sources'].pop(U.TARGET, None)
    return value


def asset_blocks(text):
    result = []
    for tag, name in (('style','style'),('script','script')):
        blocks = re.findall('<'+tag+' id="harmat-home-assistant-inline-'+name+'">([\\s\\S]*?)</'+tag+'>',text)
        require(len(blocks) == 1, 'Exactly one inline '+name+' block required')
        result.append(blocks[0].replace('\r\n','\n').strip())
    return result


def public(remote, baseline, protected, active):
    body, kind = Q.get(D.URL)
    text, previous = body.decode('utf-8'), baseline.decode('utf-8')
    require('text/html' in kind, 'Homepage MIME')
    proof = D.assert_home(text, previous)
    require(R.M.main_html(text) == R.M.main_html(previous), 'Homepage main must remain byte-exact')
    old,new,*_ = release()
    require(asset_blocks(text) == asset_blocks((new if active else old).decode('utf-8')),
            'Published placement assets differ')
    require('/home-family-six-r2.js?ver=514ac9461c375180' in text, 'Original homepage runtime changed')
    for route in ('/lakaskereso/','/elerhetosegeink/','/property/a1-f-l1/',
                  '/virtualis-lakasvalaszto/','/virtualis-lakasvalaszto-elso-utem/','/virtualis-lakasvalaszto-a1-epulet/'):
        data, mime = Q.get('https://harmat22.hu'+route)
        require('text/html' in mime and not any(marker.encode() in data for marker in U.V.MARKERS),
                'Unrelated route health or placement leakage')
    proof.update({'health_routes':7,'main_unchanged':True,'assistant_entry':'hero' if active else 'apartments',
                  'submission':False})
    return proof


def configure():
    U.release, U.public = release, public
    U.V.state = state
    U.PREFIX = H.BACKUPS+'/home-hero-assistant-'
    U.RULE = RULE
    U.__doc__ = __doc__
    return U


if __name__ == '__main__':
    configure().main()
