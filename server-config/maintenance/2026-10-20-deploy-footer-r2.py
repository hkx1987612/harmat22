"""Footer-only clearance for the existing fixed cookie settings control."""
import difflib
import importlib.util
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('footer_base',HERE/'2026-10-20-deploy-footer.py')
F = importlib.util.module_from_spec(spec)
spec.loader.exec_module(F)
F.PREFIX = F.H.BACKUPS+'/footer-family-r2-'
F.OLD_LF = '32868104d0da9d2a85ba0b779db408fe1688e8ec1ea3da4fda3615b3e8f92b19'
F.OLD_RAW = '6821501306eb456b3b9a7e1016ec40709aa82a7379fb47ee5fe269163bad6127'
F.NEW_LF = 'a74f6de8d93bc5f183cf7447be3100acbebacf19228cf2f5ea1de62d5d2b375f'
F.FIXTURE_CHECKS = 485


def release():
    new = (F.ROOT/'wp-mu-plugins'/F.NAME).read_bytes().replace(b'\r\n',b'\n')
    F.require(F.NEW_LF and F.sha(new) == F.NEW_LF,'Exact reviewed r2 footer required')
    old = new
    for after,before in (
        (b' * Version: 2026.10.20.2',b' * Version: 2026.10.20.1'),
        (b'padding:18px 24px 76px;text-align:left',b'padding:18px 24px 22px;text-align:left'),
        (b'padding:16px 16px 76px}',b'padding:16px 16px 20px}'),
    ):
        F.require(old.count(after) == 1,'Unique reviewed footer refinement required')
        old = old.replace(after,before,1)
    F.require(F.sha(old) == F.OLD_LF,'Only version and two footer bottom paddings allowed')
    original = b''.join(line if i in (4,175,176,177) else line.replace(b'\n',b'\r\n')
                        for i,line in enumerate(old.splitlines(keepends=True)))
    F.require(F.sha(original) == F.OLD_RAW,'Exact current r1 mixed-EOL source required')
    old_lines,new_lines = old.splitlines(keepends=True),new.splitlines(keepends=True)
    raw = original.splitlines(keepends=True)
    parts = []
    for tag,a,b,c,d in difflib.SequenceMatcher(None,old_lines,new_lines,autojunk=False).get_opcodes():
        if tag == 'equal':parts.extend(raw[a:b])
        else:
            for offset,line in enumerate(new_lines[c:d]):
                parts.append(line if b-a == d-c and a+offset in (4,175,176,177) else line.replace(b'\n',b'\r\n'))
    candidate = b''.join(parts)
    F.require(candidate.replace(b'\r\n',b'\n') == new,'Exact r2 EOL reconstruction failed')
    helpers = {n:(HERE/n).read_bytes().replace(b'\r\n',b'\n') for n in (F.TEST,F.D.CHECKER)}
    helpers['footer-dictionary.json'] = (F.ROOT/'wp-mu-plugins/harmat-internal-language/zh-Hans.json').read_bytes().replace(b'\r\n',b'\n')
    edits,_,protected,_,_ = F.M.release()
    protected = {**protected,**{p.removeprefix(F.D.DIR+'/'):b for p,(_,b) in edits.items()}}
    pins = {'old':F.sha(original),'new':F.sha(candidate),'normalized_new':F.sha(new),
            'protected':{p:F.sha(b) for p,b in protected.items()},'helpers':{p:F.sha(b) for p,b in helpers.items()}}
    return original,candidate,helpers,protected,pins,F.sha(F.Q.P.encoded(pins))


F.release = release
if __name__ == '__main__':F.main()
