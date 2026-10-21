"""Build an offline ad review, never an Ads API upload or website deployment."""
import hashlib
import json
from html import escape
from pathlib import Path
import shutil

from PIL import Image

ROOT = Path(__file__).resolve().parents[3]
SOURCE = Path(__file__).parent
OUT = ROOT / 'outputs/2026-10-21-chatgpt-ad-preview/v1'
BASE_URL = 'https://harmat22.hu/lakaskereso/'
COPY = {
    'brand': 'Harmat Lakópark',
    'headline': 'Új otthon Kőbányán',
    'description': 'Új építésű lakások és alaprajzok. Kérjen tájékoztatást! A kép látványterv.',
    'headline_zh': '在科巴尼奥，寻找新家',
    'description_zh': '新建住宅及户型图，欢迎咨询了解。配图为效果图。',
}
VARIANTS = [
    {'id': 'courtyard', 'label': 'A · 庭院版（推荐）',
     'source': 'wp-mu-plugins/harmat-about-family/harmat-courtyard.jpg',
     'file': 'courtyard.jpg', 'alt': 'A Harmat Lakópark tervezett belső udvarának látványterve.'},
    {'id': 'aerial', 'label': 'B · 项目全景版（备选）',
     'source': 'wp-mu-plugins/harmat-project-family/project-aerial.jpg',
     'file': 'aerial.jpg', 'alt': 'A tervezett Harmat Lakópark madártávlati látványterve.'},
]


def artboard(item):
    return f'''<main class="artboard" lang="hu"><header class="brandbar">
<img class="logo" src="assets/logo.png" alt=""><div><p class="brand">{COPY['brand']}</p>
<p class="location">Budapest X. kerület · Harmat utca 22.</p></div></header>
<img class="project" src="assets/{item['file']}" alt="{item['alt']}">
<footer class="art-footer"><h1>{COPY['headline']}</h1><p class="website">harmat22.hu</p>
<p class="disclosure">LÁTVÁNYTERV · NEM AZ ELKÉSZÜLT ÁLLAPOT FÉNYKÉPE</p></footer></main>'''


def main():
    if (OUT / 'index.html').exists():
        raise RuntimeError('Preserve reviewed V1; build a new version for revisions')
    assert len(COPY['headline']) <= 50
    assert len(COPY['description']) <= 100
    (OUT / 'assets').mkdir(parents=True, exist_ok=True)
    pins = {}
    for item in VARIANTS:
        source = ROOT / item['source']
        shutil.copyfile(source, OUT / 'assets' / item['file'])
        with Image.open(source) as image:
            assert image.size == (1920, 1080)
        pins[item['file']] = {'source': item['source'], 'sha256': hashlib.sha256(source.read_bytes()).hexdigest()}
    shutil.copyfile(ROOT / 'outputs/2026-10-18-home-family/assets/harmat-logo.png', OUT / 'assets/logo.png')
    for name in ('preview.css', 'artboard.css'):
        shutil.copyfile(SOURCE / name, OUT / name)
    shutil.copyfile(SOURCE / 'serve.py', OUT.parent / 'serve.py')
    cards = []
    for item in VARIANTS:
        (OUT / (item['id'] + '-artboard.html')).write_text(
            '<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            '<meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="artboard.css"><title>'
            + COPY['brand'] + ' - ' + item['id'] + '</title></head><body>' + artboard(item) + '</body></html>', encoding='utf-8')
        url = BASE_URL + '?utm_source=chatgpt&utm_medium=paid&utm_campaign=harmat_7d_38500&utm_content=' + item['id'] + '_v1'
        cards.append(f'''<section class="variant"><h2>{item['label']}</h2><article class="ad" lang="hu">
<header class="ad-brand"><img src="assets/logo.png" alt="Harmat"><div><strong>{COPY['brand']}</strong><span>Hirdetés</span></div></header>
<a class="visual" href="{item['id']}-1200.jpg" target="_blank" rel="noopener" title="查看完整图片"><img src="{item['id']}-1200.jpg" width="1200" height="1200" alt="{item['alt']}"></a>
<div class="ad-copy"><h3>{COPY['headline']}</h3><p>{COPY['description']}</p><a class="destination" href="{escape(url, quote=True)}" target="_blank" rel="noopener noreferrer">harmat22.hu/lakaskereso/ <span aria-hidden="true">↗</span></a></div></article>
<div class="review-copy"><p><strong>中文释义</strong>　{COPY['headline_zh']}。{COPY['description_zh']}</p><p>现有项目效果图，非建成实景。原图完整保留，不新增建筑或配套。</p><a href="{item['id']}-1200.jpg" download>下载图片 · 1200 × 1200 JPG</a></div></section>''')
    html = '''<!doctype html><html lang="zh-Hans"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self'; style-src 'self'; connect-src 'none'; frame-src 'none'; form-action 'none'; base-uri 'none'"><title>Harmat Lakópark · 广告审阅 V1</title><link rel="stylesheet" href="preview.css"></head><body>
<header class="page-head"><div><p class="eyebrow">Harmat Lakópark</p><h1>广告文案与图片预览</h1></div><span class="state">V1 · 待确认 · 未发布</span></header>
<div class="budget"><div><span>投放周期</span><strong>7天</strong></div><div><span>总广告费上限</span><strong>38,500 <small>HUF</small></strong></div><div><span>拟用目标</span><strong>网站访问</strong></div><div><span>拟用市场／语言</span><strong>匈牙利／匈牙利语</strong></div></div>
<p class="review-note">以下是本地审阅示意，不是平台实际投放截图。A、B 为同一预算下的候选素材，不是两个独立广告预算。</p>
<main><div class="variants">''' + ''.join(cards) + '''</div>
<section class="details"><h2>发布前核对</h2><dl><div><dt>预算与期限</dt><dd>优先配置总预算上限38,500福林。5,500福林／天仅为7天平均值，不代表每日硬上限。开始日期待确认，按Europe/Budapest核对7个本地自然日及结束时刻，不自动续投。</dd></div><div><dt>账单</dt><dd>上述为广告媒体费；税费及付款手续费尚未核定。如需额外费用，先确认，不擅自增加。未添加付款。</dd></div><div><dt>投放范围</dt><dd>拟选匈牙利；布达佩斯等细分地区需权限恢复后核对后台可选项。没有保证特定对话一定展示，也不使用年龄、族群、家庭状况或客户名单定向。</dd></div><div><dt>资料准确性</dt><dd>不写折扣、起价、具体面积、交房承诺或收益保证。配图标清效果图；不替换官网图片、报价单、预约，不新增追踪代码。</dd></div><div><dt>账户与审批</dt><dd>账户权限待恢复，未保存平台广告草稿或预算；无上传、无投放。恢复后核对品牌名、素材、实际预览、地区、总预算、期限和审核要求，再请你确认发布。</dd></div><div><dt>平台自动调整</dt><dd>发布前核对创意自动优化、翻译及目的链接调整设置，避免未经复核的文案或图片变化。最终外观以平台当时的预览为准。</dd></div></dl></section>
</main><footer class="page-footer">仅用于本地审阅 · 2026年10月21日 · 官网及其他广告均未修改</footer></body></html>'''
    (OUT / 'index.html').write_text(html, encoding='utf-8')
    manifest = {'status': 'LOCAL_DRAFT_NOT_UPLOADED_NOT_APPROVED_FOR_PUBLICATION', 'days': 7,
                'media_budget_total_huf': 38500, 'start_date': None, 'end_date': None,
                'copy': COPY, 'variants': VARIANTS, 'source_pins': pins,
                'native_platform_preview_required': True, 'website_modified': False}
    (OUT / 'review-manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'output': str(OUT), 'headline_characters': len(COPY['headline']), 'description_characters': len(COPY['description']), 'status': manifest['status']}))


if __name__ == '__main__':
    main()
