"""Build isolated no-form filter preview from the tested synthetic fixture."""
import json
from pathlib import Path
import subprocess

ROOT=Path(__file__).resolve().parents[2]
fixture=Path(__file__).with_name('2026-10-29-search-budget-fixture.php')
data=json.loads(subprocess.check_output(['C:/tools/php-8.5.6/php.exe',str(fixture),
    str(ROOT/'wp-plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php'),
    str(ROOT/'wp-mu-plugins/harmat-performance-guard.php')]))
out=ROOT/'outputs/2026-10-29-search-budget/preview'
out.mkdir(parents=True,exist_ok=True)
page='''<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Harmat budget filter - isolated test</title><style>body{margin:0;background:#f7f8f6}header{padding:16px 24px;background:#fff;font:14px Arial;display:flex;justify-content:space-between}button{min-height:44px}</style>'''
page+='<style>'+data['css']+'</style></head><body class="harmat-lakas-redesign-page"><header><span>Harmat Lakópark · offline filter test</span><button type="button" id="language">Magyar / 中文</button></header>'
page+=data['html']+'<script>'+data['js']+'</script>'+data['patch']+data['comfort']
lang=ROOT/'wp-mu-plugins/harmat-internal-language'
page+='<style>#harmat-internal-language{display:none}</style><script>'+(lang/'runtime.js').read_text(encoding='utf-8')+'</script>'
page+='<script>'+(lang/'patterns.js').read_text(encoding='utf-8')+'</script><script>'
page+='const translator=HarmatInternalLanguage.create(document,'+json.dumps(json.loads((lang/'zh-Hans.json').read_text(encoding='utf-8')))+',{enabled:true,storage:false,translateDynamic:HarmatLanguagePatterns.translate});'
page+='''document.getElementById('language').addEventListener('click',()=>translator.setLanguage(translator.getLanguage()==='zh-Hans'?'hu':'zh-Hans'));</script></body></html>'''
(out/'index.html').write_text(page,encoding='utf-8')
print(out)
