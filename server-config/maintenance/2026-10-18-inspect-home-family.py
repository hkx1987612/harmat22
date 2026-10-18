"""Read-only homepage release identity; private output is never a Git artifact."""
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import shlex
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('home_transport', Path(__file__).with_name('2026-10-14-deploy-appointment-source.py'))
H = importlib.util.module_from_spec(spec)
spec.loader.exec_module(H)
CODE = r'''
$id=(int)get_option('page_on_front'); $p=get_post($id);
if (!$p || get_option('show_on_front')!=='page') throw new RuntimeException('No static homepage');
$rows=array();
foreach (array('a1-1-l6','a2-4-l5','a1-1-l5') as $slug) {
    $unit=get_page_by_path($slug,OBJECT,'property');
    if (!$unit || $unit->post_status!=='publish' || $unit->post_password!=='') throw new RuntimeException('Missing public unit');
    $d=harmat_sai_property_summary_data($unit->ID);
    $rows[]=array('id'=>$unit->ID,'code'=>$d['title'],'url'=>get_permalink($unit),'rooms'=>$d['rooms'],'sales_area'=>$d['sales_area'],'status'=>$d['status'],'price_huf'=>$d['hide_price']?null:$d['price']);
}
echo wp_json_encode(array('id'=>$id,'slug'=>$p->post_name,'status'=>$p->post_status,'raw_bytes'=>strlen($p->post_content),'raw_sha'=>hash('sha256',$p->post_content),'template'=>get_page_template_slug($id),'units'=>$rows));
'''

def main():
    r=H.Remote()
    try:
        identity=json.loads(r.run(H.WP+' eval '+shlex.quote(CODE)))
    finally:
        r.close()
    req=urllib.request.Request('https://harmat22.hu/',headers={'User-Agent':'HarmatOfferSafety/1.0'})
    with urllib.request.urlopen(req,timeout=30) as response:
        data=response.read(2*1024*1024+1)
        if response.status!=200 or len(data)>2*1024*1024: raise RuntimeError('Homepage response failed')
    html=data.decode('utf-8')
    mains=re.findall(r'<main\b[^>]*>[\s\S]*?</main>',html)
    if len(mains)!=1: raise RuntimeError('Expected single main')
    identity['main']={'opening':mains[0].split('>')[0]+'>','bytes':len(mains[0].encode()),'sha':hashlib.sha256(mains[0].encode()).hexdigest(),'h1':re.findall(r'<h1\b[^>]*>[\s\S]*?</h1>',html),'ids':re.findall(r'\bid="([^"]+)"',mains[0])}
    identity['main']['scripts']=len(re.findall(r'<script\b',mains[0]))
    identity['h1_inside_main']=bool(re.search(r'<h1\b',mains[0]))
    identity['media']=re.findall(r'<video\b[^>]*>|<source\b[^>]*>',html)
    identity['shell_positions']={key:html.find('id="'+key+'"') for key in ('masthead','colophon','h22-offer-modal','h22-offer-form','hm-cookie-settings-button')}
    output=ROOT/'outputs/2026-10-18-home-family/live-original'
    output.mkdir(parents=True,exist_ok=True)
    (output/'home.html').write_bytes(data)
    (output/'identity.json').write_text(json.dumps(identity,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps(identity,ensure_ascii=True,indent=2))

if __name__=='__main__': main()
