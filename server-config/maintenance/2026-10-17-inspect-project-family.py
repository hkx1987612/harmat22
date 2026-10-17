"""Read-only original Project identity. No form, content edit, or deployment."""
import importlib.util
import json
from pathlib import Path
import shlex
import hashlib
import re
import sys
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('project_transport', Path(__file__).with_name('2026-10-14-deploy-appointment-source.py'))
H = importlib.util.module_from_spec(spec)
spec.loader.exec_module(H)
CODE = r'''
$p=get_post(1777);
if (!$p || $p->post_name!=='harmat-lakopark' || $p->post_type!=='page') throw new RuntimeException('Wrong page');
echo wp_json_encode(array('id'=>$p->ID,'slug'=>$p->post_name,'status'=>$p->post_status,'password_empty'=>$p->post_password==='','raw_bytes'=>strlen($p->post_content),'raw_sha'=>hash('sha256',$p->post_content),'template'=>get_page_template_slug(1777),'raw'=>$p->post_content,'elementor'=>metadata_exists('post',1777,'_elementor_data')));
'''


def main():
    if sys.argv[1:] == ['--public']:
        request = urllib.request.Request('https://harmat22.hu/harmat-lakopark/', headers={'User-Agent': 'HarmatOfferSafety/1.0'})
        with urllib.request.urlopen(request, timeout=30) as response:
            data = response.read(2 * 1024 * 1024 + 1)
        if len(data) > 2 * 1024 * 1024:
            raise RuntimeError('Public response too large')
        html = data.decode('utf-8')
        mains = re.findall(r'<main\b[^>]*>[\s\S]*?</main>', html)
        if len(mains) != 1:
            raise RuntimeError('Expected single public main')
        print(json.dumps({'main_start': mains[0][:200], 'main_bytes': len(mains[0].encode()), 'main_sha': hashlib.sha256(mains[0].encode()).hexdigest(), 'h1': re.findall(r'<h1\b[^>]*>[\s\S]*?</h1>', html)}, ensure_ascii=True, indent=2))
        return
    r = H.Remote()
    try:
        result = json.loads(r.run(H.WP + ' eval ' + shlex.quote(CODE)))
        output = ROOT / 'outputs/2026-10-17-project-family/original'
        output.mkdir(parents=True, exist_ok=True)
        path = output / 'original-page-identity.json'
        with path.open('x', encoding='utf-8', newline='\n') as f:
            json.dump(result, f, ensure_ascii=False, indent=2)
        raw = result.pop('raw')
        result['raw_start'] = raw[:1500]
        result['raw_end'] = raw[-700:]
        print(json.dumps(result, ensure_ascii=True, indent=2))
    finally:
        r.close()


if __name__ == '__main__':
    main()
