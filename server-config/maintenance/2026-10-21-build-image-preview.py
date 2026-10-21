"""Build a local-only, self-contained image comparison. No live edits or forms."""
import hashlib
from html import escape
import json
from pathlib import Path
import shutil
import time
from urllib.parse import quote, urlsplit
from urllib.request import Request, urlopen
from PIL import Image

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'outputs/2026-10-21-image-preview/v1'
ASSETS=OUT/'assets'
SOURCE=Path(__file__).with_name('2026-10-21-image-preview-manifest.json')
SOURCE_FILES=Path(__file__).with_name('2026-10-21-image-preview')
data=json.loads(SOURCE.read_text(encoding='utf-8'))
if (OUT/'index.html').exists():raise RuntimeError('Preserve the existing reviewed preview')
ASSETS.mkdir(parents=True,exist_ok=True)
pins={}

def asset(item):
    name=item['file'];target=ASSETS/name
    if name in pins:return 'assets/'+name
    if Path(name).name!=name:raise RuntimeError('Unsafe asset name')
    if item.get('local'):
        local=ROOT/item['local']
        if not local.resolve().is_relative_to(ROOT.resolve()) or not local.is_file() or local.is_symlink():raise RuntimeError('Unsafe local asset')
        shutil.copyfile(local,target)
    else:
        url=item['url'];parts=urlsplit(url)
        if parts.scheme!='https' or parts.netloc!='harmat22.hu' or not parts.path.startswith('/wp-content/uploads/') or parts.query:raise RuntimeError('Unreviewed public media origin')
        time.sleep(2)
        req=Request(quote(url,safe='/:_%'),headers={'User-Agent':'HarmatLocalImagePreview/2026-10-21'})
        with urlopen(req,timeout=30) as response:
            if response.status!=200 or urlsplit(response.geturl()).netloc!='harmat22.hu' or not response.headers.get_content_type().startswith('image/'):raise RuntimeError('Wrong media response')
            content=response.read(5*1024*1024+1)
            if len(content)>5*1024*1024:raise RuntimeError('Oversized image')
        target.write_bytes(content)
    with Image.open(target) as im:
        if im.format not in ('JPEG','PNG','WEBP'):raise RuntimeError('Unexpected image format')
        dimensions=list(im.size)
        im.verify()
    pins[name]={'bytes':target.stat().st_size,'sha256':hashlib.sha256(target.read_bytes()).hexdigest(),
                'dimensions':dimensions,'source':item.get('url') or item.get('local')}
    print('PREVIEW_ASSET '+name+' '+str(dimensions),flush=True)
    return 'assets/'+name

def source_link(item):
    return '<a class="source" href="'+escape(item['url'],quote=True)+'" target="_blank" rel="noopener noreferrer">素材来源 <i data-lucide="arrow-up-right"></i></a>' if item.get('url') else ''

sections=[]
for index,case in enumerate(data['cases'],1):
    figures=[]
    for kind,label in (('before','当前使用'),('after','拟替换')):
        item=case[kind];src=asset(item)
        caption=item.get('zh','')
        hu=item.get('hu','')
        alt=item.get('alt_hu') or item['type']
        figures.append('<figure class="comparison '+kind+'"><div class="figure-head"><span>'+label+'</span><span>'+escape(item['type'])+'</span></div>'
            '<button class="image-button" type="button" data-image="'+src+'" data-caption="'+escape(caption or item['note'],quote=True)+'" aria-label="放大：'+escape(case['title']+' · '+label,quote=True)+'">'
            '<img src="'+src+'" alt="'+escape(alt,quote=True)+'" width="'+str(pins[item['file']]['dimensions'][0])+'" height="'+str(pins[item['file']]['dimensions'][1])+'"><span class="zoom"><i data-lucide="expand"></i></span></button>'
            '<figcaption><p>'+escape(item['note'])+'</p>'
            +('<div class="public-caption"><span>拟用图注</span><p lang="hu">'+escape(hu)+'</p><p>'+escape(caption)+'</p></div>' if caption else '')
            +source_link(item)+'</figcaption></figure>')
    sections.append('<section id="'+case['id']+'" class="case"><header class="case-head"><span class="case-number">'+str(index).zfill(2)+'</span><div><h2>'+escape(case['title'])+'</h2><a href="https://harmat22.hu'+case['page']+'" target="_blank" rel="noopener noreferrer">'+case['page']+' <i data-lucide="arrow-up-right"></i></a></div><span class="review-state"><i data-lucide="clock-3"></i> 待确认</span></header><div class="compare-grid">'+''.join(figures)+'</div></section>')

refs=[]
for item in data['references']:
    src=asset(item)
    refs.append('<figure class="reference"><div class="figure-head"><span>'+escape(item['title'])+'</span></div><button class="image-button" type="button" data-image="'+src+'" data-caption="'+escape(item['zh'],quote=True)+'" aria-label="放大：'+escape(item['title'],quote=True)+'"><img src="'+src+'" alt="'+escape(item['zh'],quote=True)+'"><span class="zoom"><i data-lucide="expand"></i></span></button><figcaption><strong>'+escape(item['type'])+'</strong><p lang="hu">'+escape(item['hu'])+'</p><p>'+escape(item['zh'])+'</p></figcaption></figure>')

logo=ROOT/'outputs/2026-10-18-home-family/assets/harmat-logo.png'
shutil.copyfile(logo,ASSETS/'harmat-logo.png')
for name in ('lucide.min.js','lucide.LICENSE'):
    shutil.copyfile(ROOT/'outputs/2026-10-18-home-family/assets'/name,OUT/name)
for name in ('preview.css','preview.js'):
    shutil.copyfile(SOURCE_FILES/name,OUT/name)
shutil.copyfile(SOURCE_FILES/'serve.py',OUT.parent/'serve.py')
content='''<!doctype html><html lang="zh-Hans"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; connect-src 'none'; frame-src 'none'; form-action 'none'; base-uri 'none'"><title>Harmat Lakópark · 图片替换对照 V1</title><link rel="stylesheet" href="preview.css"><script src="lucide.min.js" defer></script><script src="preview.js" defer></script></head><body>
<header class="topbar"><div class="topbar-inner"><img class="brand" src="assets/harmat-logo.png" width="70" height="70" alt="Harmat Lakópark"><div><h1>图片替换对照 <span>V1</span></h1><p>Harmat Lakópark · 2026年10月21日</p></div><span class="local-status"><i data-lucide="shield-check"></i> 本地预览 · 未上线</span></div><nav aria-label="审阅分组"><a href="#about">关于页</a><a href="#a1">A1-F-L1</a><a href="#a4">A4-F-L1</a><a href="#a3">A3-F-L3</a><a href="#interior">室内图库</a><a href="#gallery">图库封面</a><a href="#real">实景标注</a></nav></header>
<main><div class="scope"><strong>只审阅图片及图注</strong><p>本轮不改官网，不删原素材，不修改面积、价格、报价单或预约。确认后，再备份并分批上线。</p></div>
'''+''.join(sections)+'''<section id="real" class="case"><header class="case-head"><span class="case-number">07</span><div><h2>实景图片 · 保留的标注方式</h2><p>现有项目素材。以下不作为新增替换项。</p></div></header><div class="reference-grid">'''+''.join(refs)+'''</div></section><section class="scope scope-bottom"><h2>销售面积说明 · 本轮不改</h2><p>按本次说明，销售面积与室内面积的差异涉及阳台计入。这里不默认阳台全额计入或按一半折算，也不重新计算任何房号的面积和单价。</p><p>另行核对时，区分室内面积、阳台／露台面积、销售面积，沿用现有核定销售数据。</p></section></main><footer>状态：待确认　·　没有发布、删除、付费或提交预约的操作</footer>
<dialog id="image-dialog" aria-label="图片原图预览"><div class="dialog-toolbar"><span>原图预览</span><button id="close-image" type="button" aria-label="关闭图片" title="关闭图片"><i data-lucide="x"></i></button></div><div class="dialog-media"><img id="dialog-image" alt=""></div><p id="dialog-caption"></p></dialog></body></html>'''
(OUT/'index.html').write_text(content,encoding='utf-8')
(OUT/'asset-pins.json').write_text(json.dumps({'status':data['status'],'manifest_sha256':hashlib.sha256(SOURCE.read_bytes()).hexdigest(),'images':pins},ensure_ascii=False,indent=2),encoding='utf-8')
print('LOCAL_PREVIEW_READY '+str(OUT/'index.html'),flush=True)
