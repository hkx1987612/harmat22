"""Equal desktop lifestyle images; preserve the mobile stack and native sales shell."""
import importlib.util
import json
from pathlib import Path
import re
import subprocess

HERE=Path(__file__).resolve().parent
ROOT=HERE.parents[1]
spec=importlib.util.spec_from_file_location('everyday_image_transport',HERE/'2026-10-20-update-home-contact-image.py')
M=importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
D,S,H,Q=M.D,M.S,M.H,M.Q
require,sha=M.require,M.sha
OLD_SHA='d73fccd048b51e83f1d4b1ce301635751c25bc1ef8bbe7d521f14818b75e3f02'
NEW_SHA='65f29b83d86f96ac4a121049a90c7c67ffdeca07bb05d0dcac6542aebfc98651'
RULES=(
    '@media(min-width:621px){body.home.page-id-164 #harmat-home-family .hhf-everyday-grid{grid-template-columns:repeat(2,minmax(0,1fr))}body.home.page-id-164 #harmat-home-family .hhf-everyday-story:nth-child(2){padding-top:0}}',
    '@supports(grid-template-rows:subgrid){@media(min-width:621px){body.home.page-id-164 #harmat-home-family .hhf-everyday-grid{row-gap:0}body.home.page-id-164 #harmat-home-family .hhf-everyday-story{display:grid;grid-template-rows:subgrid;grid-row:span 3;align-self:stretch;min-width:0}}}',
)
BLOCK=''.join("            . '"+rule+"'\n" for rule in RULES).encode()
original_public_proof=M.public_proof


def validate_delta(old,new):
    require(sha(old)==OLD_SHA and sha(new)==NEW_SHA,'Exact reviewed source pair required')
    require(new.count(BLOCK)==1,'One two-rule desktop alignment block required')
    require(new.replace(BLOCK,b'',1).replace(b' * Version: 1.0.17',b' * Version: 1.0.16',1)==old,
            'Only scoped CSS/version edits allowed')


def bundle():
    old=subprocess.check_output(['git','show','f86dd79:wp-mu-plugins/'+D.MAIN],cwd=ROOT)
    new=S.read_local(D.MAIN)
    validate_delta(old,new)
    manifest=S.read_local(D.ASSET+'/manifest-six-r2.json')
    require(sha(manifest)=='b7ad673660278889725b832cf748656c5b4e59fc92220519e75117eb99f25280','Retained manifest changed')
    pins=json.loads(manifest)
    protected={D.ASSET+'/'+n:S.read_local(D.ASSET+'/'+n) for n in pins}
    require(all(sha(protected[D.ASSET+'/'+n])==v for n,v in pins.items()),'Retained asset changed')
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


def inline_style(text):
    matches=re.findall(r'<style id="harmat-home-family-header-flow">([\s\S]*?)</style>',text)
    require(len(matches)==1,'Exactly one owned style block required')
    return matches[0]


def validate_style(before,after,active):
    expected=before+''.join(RULES) if active else before
    require(after==expected,'Only the two reviewed CSS rules may change')


def public_proof(baseline,active):
    proof=original_public_proof(baseline,active)
    data,mime=Q.get(D.URL)
    require('text/html' in mime,'Homepage MIME failed')
    validate_style(inline_style(baseline.decode()),inline_style(data.decode()),active)
    for route in ('/property/a1-f-l1/','/virtualis-lakasvalaszto/',
                  '/virtualis-lakasvalaszto-elso-utem/','/virtualis-lakasvalaszto-a1-epulet/'):
        body,kind=Q.get('https://harmat22.hu'+route)
        require('text/html' in kind and not any(rule.encode() in body for rule in RULES),
                'Unrelated property/selector route health or scope changed')
    proof.pop('figure_only',None)
    proof['main_html_unchanged']=True
    proof['health_routes']=7
    proof['desktop_lifestyle_alignment']='equal_columns_and_shared_rows' if active else 'prior_offset'
    return proof


def configure():
    M.bundle=bundle
    M.PREFIX=H.BACKUPS+'/home-everyday-align-'
    # The existing contact figure is now protected, not replaced by this release.
    M.OLD_FIGURE=M.NEW_FIGURE
    M.public_proof=public_proof
    M.__doc__=__doc__
    return M


if __name__=='__main__': configure().main()
