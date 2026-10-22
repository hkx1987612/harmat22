"""Build an isolated, public-content-only homepage copy review. No live writes."""
import argparse
from copy import deepcopy
from hashlib import sha256
from html import escape
import json
from pathlib import Path
import re
import shutil

from lxml import etree, html

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[2]
OUT = REPO / 'outputs/2026-10-22-home-practical/v1'
OLD = REPO / 'outputs/2026-10-18-home-family/assets'
IMAGE_REVIEW = REPO / 'outputs/2026-10-21-image-preview/v1/assets'
DOWNLOADS = Path.home() / 'Downloads'

# Only copy and information hierarchy are proposed. Facts, media and actions stay put.
COPY = {
    'apartmentsEyebrow': ('Lakáskínálat', '当前房源'),
    'apartmentsTitle': ('Elérhető lakások', '可售户型'),
    'everydayEyebrow': ('A tervekben és a helyszínen', '从项目规划到现场'),
    'everydayTitle': ('Otthon, belül és kívül', '家里家外的日常'),
    'everydayIntro': ('Ismerje meg a tervezett udvart, a lakások megoldásait és a környéket.', '看看规划中的庭院、公寓配套，以及周边环境。'),
    'courtyardTitle': ('Zöld belső udvar', '楼宇之间的绿色庭院'),
    'courtyardDescription': ('Az épületek közötti közös zöldterület a projekt terveiben. A képen a tervezett állapot látható.', '项目规划中的楼间共享绿地。图片展示规划效果，并非目前的建成状态。'),
    'livingRoomTitle': ('Kényelem a lakásban', '居家配套'),
    'livingRoomDescription': ('Hőszivattyús fűtés-hűtés, mélygarázs és tárolók a projekt terveiben. A részleteket az alaprajzok és a műszaki leírás tartalmazza.', '项目规划包含热泵供暖制冷、地下车库和储藏室。具体内容请查看对应户型图和技术说明。'),
    'neighborhoodEyebrow': ('A Harmat utca környéke', 'Harmat utca 周边'),
    'parkTitle': ('Park és mindennapi ügyek', '公园与日常配套'),
    'parkDescription': ('Közeli nyilvános park, üzletek, közlekedés és intézmények. A környékoldalon részletesebben is tájékozódhat.', '了解附近公共公园、商店、交通与公共服务设施，详细内容可在周边环境页查看。'),
    'showroomEyebrow': ('Személyes egyeztetés', '面对面交流'),
    'showroomTitle': ('Nézzük át együtt az alaprajzokat.', '一起看看户型图'),
    'showroomDescription': ('Bemutatótermünkben az értékesítési csapattal egyeztethet a lakásokról és a műszaki tartalomról.', '在展示中心，与销售团队交流户型选择和具体配置。'),
    'chooserEyebrow': ('Lakásválasztás épületenként', '按楼栋选房'),
    'chooserTitle': ('Válasszon épületet, majd lakást.', '先选楼栋，再看公寓'),
    'chooserDescription': ('Nézze meg az épületek elhelyezkedését, majd az egyes lakások alaprajzát és adatait.', '先了解楼栋位置，再查看各套公寓的户型图和详细资料。'),
    'teamLink': ('A projektről és a csapatról', '关于项目与团队'),
    'contactEyebrow': ('Harmat utca 22.', 'Harmat utca 22.'),
    'contactTitle': ('Értékesítés és kapcsolat', '销售咨询与联系'),
    'contactDescription': ('Kérjen aktuális lakásinformációt, vagy egyeztessen időpontot a bemutatóterembe.', '咨询当前房源信息，或联系销售团队预约参观展示中心。'),
    'panoramaProjectTitle': ('Harmat Lakópark · Budapest X.', 'Harmat Lakópark · 布达佩斯第十区'),
    'panoramaProjectCopy': ('A projekt címe: 1105 Budapest, Harmat utca 22. Az épületek és a tervezett környezet a panorámában is megtekinthetők.', '项目地址：1105 Budapest, Harmat utca 22。可在全景中查看楼栋与规划环境。'),
    'panoramaLocationTitle': ('A Harmat utca környéke', 'Harmat utca 周边'),
    'panoramaLocationCopy': ('A környék részletes bemutatója a Környékünk oldalon található.', '周边配套的详细介绍可在“周边环境”页查看。'),
}

PARK = {
    'source': 'https://commons.wikimedia.org/wiki/File:Budapest,_K%C5%91b%C3%A1nya,_%C3%93hegy_park,_6.jpg',
    'image': 'https://upload.wikimedia.org/wikipedia/commons/1/18/Budapest%2C_K%C5%91b%C3%A1nya%2C_%C3%93hegy_park%2C_6.jpg',
    'author': 'Random photos 1989',
    'license': 'https://creativecommons.org/publicdomain/zero/1.0/',
    'license_name': 'CC0 1.0',
    'date': '2024-11-09',
    'width': 4096,
    'height': 3072,
    'sha256': 'fc603a46552558b2acfdae68ab3bad57d569e7d19d11bb02b86afbc727c9c266',
    'local': 'assets/ohegy-park-2024-11-09-original.jpg',
    'alt': ('Óhegy park a Kőbánya felirattal, 2024. november 9.', 'Óhegy 公园与 Kőbánya 地标，2024年11月9日实拍'),
    'caption': ('Óhegy park · fotó: 2024.11.09. · közpark, nem a lakópark belső kertje', 'Óhegy 公园 · 实拍于2024年11月9日 · 周边公共公园，非小区内部花园'),
}


def replace_park(candidate):
    source = OUT.parent / 'source-images' / Path(PARK['local']).name
    if not source.is_file() or digest(source) != PARK['sha256']:
        raise ValueError('Ohegy original image missing or changed')
    row = cls(candidate, 'hhf-park-row')[0]
    row.set('id', 'hhf-ohegy-preview')
    image = row.xpath('.//img')[0]
    image.set('src', PARK['image'])
    image.set('width', str(PARK['width']))
    image.set('height', str(PARK['height']))
    image.set('alt', PARK['alt'][0])
    image.set('data-preview-ohegy', '')
    caption = row.xpath('.//figcaption')[0]
    caption.text = PARK['caption'][0]
    credit = html.Element('span', {'class': 'preview-photo-credit'})
    author = etree.SubElement(credit, 'a', href=PARK['source'])
    author.text = PARK['author'] + ' / Wikimedia Commons'
    author.tail = ' · '
    license_link = etree.SubElement(credit, 'a', href=PARK['license'])
    license_link.text = PARK['license_name']
    credit.tail = None
    row.xpath('.//figure')[0].append(credit)
    shutil.copyfile(source, OUT / PARK['local'])


def digest(path):
    return sha256(path.read_bytes()).hexdigest()


def cls(root, name):
    return root.xpath('.//*[contains(concat(" ",normalize-space(@class)," ")," ' + name + ' ")]')


def assets():
    prefix = 'https://harmat22.hu/'
    pairs = {
        'wp-content/mu-plugins/harmat-project-family/project-aerial.jpg': OLD / 'hero-aerial.jpg',
        'wp-content/mu-plugins/harmat-project-family/project-courtyard.jpg': OLD / 'project-courtyard.jpg',
        'wp-content/mu-plugins/harmat-project-family/project-living-room.jpg': OLD / 'project-living-room.jpg',
        'wp-content/mu-plugins/harmat-contact-family/showroom-lounge.jpg': OLD / 'showroom-lounge.jpg',
        'wp-content/uploads/2026/09/harmat-kornyek-kutyapark-2026-09.jpg': OLD / 'park.jpg',
        'wp-content/uploads/2026/02/Harmat22_latvany-8-harmat-1280.webp': DOWNLOADS / 'Harmat22_latvany-8-harmat-1280 (1).webp',
        'wp-content/uploads/2026/05/A1-1-L6-cn-floorplan-display.jpg': OLD / 'apartment-a1-1-l6.jpg',
        'wp-content/uploads/2026/05/A2-4-L5-cn-floorplan-display.jpg': OLD / 'apartment-a2-4-l5.jpg',
        'wp-content/uploads/2026/05/A1-1-L5-cn-floorplan-display.jpg': OLD / 'apartment-a1-1-l5.jpg',
        'wp-content/uploads/2026/05/A3-F-L3-cn-floorplan-display.jpg': IMAGE_REVIEW / 'a3-f-l3-floorplan.jpg',
        'wp-content/uploads/2026/05/A4-F-L1-cn-floorplan-display.jpg': IMAGE_REVIEW / 'a4-f-l1-floorplan.jpg',
        'wp-content/uploads/2026/05/A3-2-L2-cn-floorplan-display.jpg': DOWNLOADS / 'A3-2-L2-cn-floorplan-display.jpg',
        'wp-content/uploads/2026/10/construction-updates/2026-10-20-compilation-hu-subtitled.jpg': REPO / 'outputs/2026-10-20-site-media/final-v1/2026-10-20-compilation-hu-subtitled.jpg',
    }
    base = 'wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/'
    for face in 'frblud':
        pairs[base + 'pano_pano_' + face + '.jpg'] = OLD / ('panorama-' + face + '.jpg')
    for number in (0, 1, 4, 8, 10, 5):
        pairs[base + 'xgt_' + str(number) + '.jpg'] = OLD / ('panorama-gallery-' + str(number) + '.jpg')
    pairs[base + 'video_swsp_xmsp.jpg'] = OLD / 'panorama-overview.jpg'
    copied = {}
    proof = []
    for index, (url, source) in enumerate(pairs.items()):
        if not source.is_file():
            raise FileNotFoundError(source)
        name = f'assets/original-{index:02d}{source.suffix.lower()}'
        shutil.copyfile(source, OUT / name)
        copied[prefix + url] = name
        proof.append({'url': prefix + url, 'local': name, 'sha256': digest(source), 'bytes': source.stat().st_size})
    for name in ('pannellum.js', 'pannellum.css', 'pannellum.LICENSE', 'lucide.min.js', 'lucide.LICENSE', 'harmat-logo.png'):
        source = OLD / name
        if source.is_file():
            shutil.copyfile(source, OUT / 'assets' / name)
    return copied, proof


def clean(main):
    main.attrib.pop('data-home-family-ready', None)
    hero = cls(main, 'hhf-hero')[0]
    hero.attrib.pop('data-hhvf-hero', None)
    for el in main.xpath('.//*[@id="harmat-home-video-fullscreen-control"]') + cls(main, 'hhar-controls'):
        el.getparent().remove(el)
    # These public cards are an immutable review snapshot, not a second inventory service.
    for el in main.xpath('.//*[@class and (contains(@class,"hhar-") or contains(@class,"hhar "))]'):
        if el.getparent() is not None:
            el.getparent().remove(el)
    for button in cls(main, 'harmat-local-ai-launch'):
        button.set('disabled', '')
        button.set('title', 'Offline preview: customer service is not connected')
    for video in main.xpath('.//video'):
        video.attrib.pop('src', None)
        video.attrib.pop('data-revealed', None)
    for element in main.iter():
        for attribute in tuple(element.attrib):
            if attribute.startswith('on'):
                del element.attrib[attribute]
    if main.xpath('.//form|.//input|.//textarea|.//iframe|.//script'):
        raise ValueError('Non-public or executable content in snapshot')


def build(ohegy=False):
    (OUT / 'assets').mkdir(parents=True, exist_ok=True)
    snapshot = json.loads((OUT / 'public-snapshot.json').read_text(encoding='utf-8'))
    baseline = html.fromstring(snapshot['main'])
    candidate = deepcopy(baseline)
    changes = []
    for key, (hu, zh) in COPY.items():
        nodes = candidate.xpath('.//*[@data-i18n=$key]', key=key)
        if len(nodes) != 1:
            raise ValueError(f'Copy guard {key}: {len(nodes)}')
        node = nodes[0]
        if len(node):
            raise ValueError('Do not erase nested UI: ' + key)
        changes.append({'key': key, 'before': node.text_content(), 'after': hu, 'zh': zh})
        node.text = hu
    team = cls(candidate, 'hhf-team')[0]
    about_link = team.xpath('.//a')[0]
    cls(candidate, 'hhf-showroom-copy')[0].append(about_link)
    team.getparent().remove(team)
    mapping, proof = assets()
    copy_values = dict(COPY)
    if ohegy:
        replace_park(candidate)
        mapping[PARK['image']] = PARK['local']
        proof.append({'url': PARK['image'], 'local': PARK['local'], 'sha256': PARK['sha256'], 'bytes': (OUT / PARK['local']).stat().st_size})
        copy_values.update({'parkAlt': PARK['alt'], 'parkCaption': PARK['caption']})
    media = {
        'https://harmat22.hu/wp-content/uploads/harmat-video/harmat-home-1080p-v2.mp4': 'media/hero.mp4',
        'https://harmat22.hu/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/swsp_xmsp.mp4': 'media/panorama.mp4',
        'https://harmat22.hu/wp-content/uploads/2026/10/construction-updates/2026-10-20-compilation-hu-subtitled.mp4': 'media/construction.mp4',
    }
    mapping.update(media)
    for name, main in [('before', baseline), ('after', candidate)]:
        clean(main)
        for element in main.iter():
            for attribute, value in tuple(element.attrib.items()):
                if attribute in ('src', 'poster', 'data-src', 'data-hcu-video', 'data-hcu-poster') or attribute.startswith('data-cube-'):
                    if value in mapping:
                        element.set('data-original-' + attribute, value)
                        element.set(attribute, mapping[value])
                    elif value.startswith('https:'):
                        raise ValueError('Unmapped media: ' + value)
            if element.tag == 'a' and element.get('href', '').startswith(('https:', 'http:')):
                element.set('target', '_blank')
                element.set('rel', 'noopener noreferrer')
        runtime = (REPO / 'wp-mu-plugins/harmat-home-family/home-family-loop.js').read_text(encoding='utf-8')
        if name == 'after':
            for key, (_, zh) in copy_values.items():
                pattern = re.compile(r'^(\s*' + re.escape(key) + r': )[^\n]+$', re.M)
                runtime, count = pattern.subn(lambda match: match[1] + json.dumps(zh, ensure_ascii=False) + ',', runtime, count=1)
                if count != 1:
                    raise ValueError('ZH guard: ' + key)
        (OUT / f'runtime-{name}.js').write_text(runtime, encoding='utf-8')
        main_html = html.tostring(main, encoding='unicode')
        (OUT / f'{name}-main.html').write_text(main_html, encoding='utf-8')
        (OUT / ('index.html' if name == 'after' else 'before.html')).write_text(shell(main_html, name, ohegy and name == 'after'), encoding='utf-8')
    styles = '\n'.join(item['text'] for item in snapshot['styles'])
    (OUT / 'live-styles.css').write_text(styles, encoding='utf-8')
    shutil.copyfile(REPO / 'wp-mu-plugins/harmat-home-family/home-family.css', OUT / 'home-family.css')
    for name in ('preview.css', 'preview.js'):
        shutil.copyfile(HERE / name, OUT / name)
    for script in snapshot['scripts']:
        if script['id'] == 'harmat-home-video-fullscreen-js':
            (OUT / 'fullscreen.js').write_text(script['text'], encoding='utf-8')
        elif script['id'] == 'harmat-construction-updates-runtime':
            text = script['text']
            start = text.index('          if(!/^https:')
            end = text.index('\n', start)
            text = text[:start] + "          if(url !== 'media/construction.mp4')return;" + text[end:]
            (OUT / 'construction.js').write_text(text, encoding='utf-8')
    (OUT / 'proof.json').write_text(json.dumps({'captured': snapshot['captured'], 'images': proof, 'changes': changes, 'park_replacement': PARK if ohegy else None}, ensure_ascii=False, indent=2), encoding='utf-8')
    rows = ''.join('<tr><td>' + escape(c['key']) + '</td><td>' + escape(c['before']) + '</td><td>' + escape(c['after']) + '</td><td>' + escape(c['zh']) + '</td></tr>' for c in changes)
    (OUT / 'changes.html').write_text('<!doctype html><html lang="zh-Hans"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>文案对照</title><link rel="stylesheet" href="preview.css"><body class="review-document"><h1>首页文案对照</h1><p>只做本地预览，未上线。图片、视频、房源数据、报价及预约不改；独立团队文字区合并至展示中心。</p><p><a href="index.html">返回新版预览</a></p><div class="comparison-scroll"><table><thead><tr><th>位置</th><th>原匈牙利语</th><th>新版匈牙利语</th><th>对应中文</th></tr></thead><tbody>' + rows + '</tbody></table></div></body></html>', encoding='utf-8')
    if ohegy:
        review = OUT / 'changes.html'
        text = review.read_text(encoding='utf-8').replace('图片、视频、房源数据、报价及预约不改', '仅将公园区图片换成注明年份和来源的 Óhegy 实拍，其他图片、视频、房源数据、报价及预约不改')
        review.write_text(text, encoding='utf-8')
    print(f'Built {OUT}: {len(changes)} bilingual copy edits; Ohegy image={ohegy}; no production files edited.')


def shell(main, version, ohegy=False):
    page = '''<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; media-src 'self' blob:; connect-src 'self'; form-action 'none'; frame-src 'none'; object-src 'none'; base-uri 'none'">
<title>Harmat Lakópark · 首页文案离线预览</title><link rel="stylesheet" href="assets/pannellum.css"><link rel="stylesheet" href="home-family.css"><link rel="stylesheet" href="live-styles.css"><link rel="stylesheet" href="preview.css">
<script src="assets/pannellum.js" defer></script><script src="assets/lucide.min.js" defer></script><script src="runtime-''' + version + '''.js" defer></script><script src="fullscreen.js" defer></script><script src="construction.js" defer></script><script src="preview.js" defer></script></head>
<body class="home page-id-164"><aside class="review-strip" aria-label="离线预览说明"><span>离线预览 · ''' + ('精简版' if version == 'after' else '原版对照') + ''' · 未上线</span><a href="''' + ('before.html' if version == 'after' else 'index.html') + '''">''' + ('查看原版' if version == 'after' else '查看精简版') + '''</a><a href="changes.html">文案对照</a></aside>
<header class="preview-header"><div class="preview-header-inner"><details class="preview-menu"><summary title="菜单" aria-label="菜单"><i data-lucide="menu" aria-hidden="true"></i></summary><nav aria-label="预览导航"><a href="#hhf-lakasok" data-review-hu="Lakások" data-review-zh="房源">Lakások</a><a href="#hhf-epitesi-naplo" data-review-hu="Építési napló" data-review-zh="施工日志">Építési napló</a><a href="#hhf-mindennapok" data-review-hu="Otthon és környéke" data-review-zh="家与周边">Otthon és környéke</a><a href="#hhf-panorama" data-review-hu="360° panoráma" data-review-zh="360°全景">360° panoráma</a><a href="#hhf-kapcsolat" data-review-hu="Kapcsolat" data-review-zh="联系我们">Kapcsolat</a></nav></details>
<div class="preview-language" role="group" aria-label="语言"><button type="button" data-language="hu" aria-pressed="true">Magyar</button><button type="button" data-language="zh" aria-pressed="false">中文</button></div><a class="preview-brand" href="#hhf-kezdolap"><img src="assets/harmat-logo.png" alt="Harmat Lakópark" width="60" height="60"></a><a class="preview-contact" href="https://harmat22.hu/elerhetosegeink/" target="_blank" rel="noopener noreferrer" data-review-hu="Kapcsolat / időpont" data-review-zh="联系 / 预约">Kapcsolat / időpont</a></div></header>
<main>''' + main + '''</main><footer class="review-note"><p>本地文案预览：导航为预览用外壳，不替换正式菜单或页脚。六套房源为公开数据快照，预览不连接实时轮换、客服、报价或预约后台；联系与详情链接仍指向官网。所有图片和视频沿用原素材，未压缩或重绘。</p><a href="changes.html">查看全部文案对照</a></footer></body></html>'''
    if ohegy:
        page = page.replace('所有图片和视频沿用原素材，未压缩或重绘。', '仅公园区换成注明来源及日期的 Óhegy 实拍，原图未修改；其他图片和视频不变。')
    return page


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--ohegy-park', action='store_true')
    build(ohegy=parser.parse_args().ohegy_park)
