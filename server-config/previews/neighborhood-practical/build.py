"""Build an isolated neighborhood review from public POIs and approved local media."""
import argparse
from hashlib import sha256
import json
from pathlib import Path
import shutil
from urllib.request import Request, urlopen

from lxml import html

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[2]
OUT = REPO / 'outputs/2026-10-27-neighborhood-practical/v1'
OLD = REPO / 'outputs/2026-10-18-home-family/assets'
PARK = REPO / 'outputs/2026-10-23-home-practical-release/ohegy-park-2024-original.jpg'
PARK_SHA = 'fc603a46552558b2acfdae68ab3bad57d569e7d19d11bb02b86afbc727c9c266'
SOURCE = 'https://harmat22.hu/harmat-lakopark-kornyeke/'

# Translations only; the public source remains the owner of names/distances.
CATEGORIES = {
    'Zöld környezet': ('parks', 'Park és szabadidő', '公园与休闲', 'trees'),
    'Oktatás': ('education', 'Oktatás', '教育', 'graduation-cap'),
    'Bevásárlás': ('shopping', 'Bevásárlás', '购物', 'shopping-bag'),
    'Közlekedés': ('transport', 'Közlekedés', '交通', 'tram-front'),
    'Egészség és ügyintézés': ('services', 'Egészség és ügyintézés', '医疗与日常服务', 'building-2'),
}
ZH_NAMES = [
    '附近遛狗区', 'Óhegy 公园', 'Csősztorony 与 Óhegy 周边', 'Újhegy 与 Sportliget 方向',
    'Kőbányai Szent László 中学', 'Kőbányai Harmat 小学',
    'Semmelweis 大学 Nagyvárad tér 校区', 'NKE Ludovika 校区',
    'ÁRKÁD Budapest', 'Sugár 购物中心', 'KÖKI Terminál',
    'Kőbánya alsó 火车站', 'Örs vezér tere', 'Kőbánya-Kispest',
    'Szent László tér／区中心', 'Bajcsy-Zsilinszky 医院', '周边药房、诊所与邮局',
]
SOURCE_NAMES = [
    'Közeli kutyafuttató', 'Óhegy park', 'Csősztorony és Óhegy környéke', 'Újhegy és Sportliget iránya',
    'Kőbányai Szent László Gimnázium', 'Kőbányai Harmat Általános Iskola',
    'Semmelweis Egyetem Nagyvárad tér', 'NKE Ludovika Campus', 'ÁRKÁD Budapest', 'Sugár Üzletközpont',
    'KÖKI Terminál', 'Kőbánya alsó vasútállomás', 'Örs vezér tere', 'Kőbánya-Kispest',
    'Szent László tér / kerületi központ', 'Bajcsy-Zsilinszky Kórház', 'gyógyszertárak, rendelők, posta',
]
DESCRIPTIONS = [
    ('Kutyafuttató', '遛狗活动场地'), ('Közpark', '公共公园'),
    ('Helyi látnivaló', '本地地标'), ('Sport és szabadidő', '运动与休闲'),
    ('Gimnázium', '中等教育'), ('Általános iskola', '基础教育'),
    ('Egyetemi helyszín', '大学校区'), ('Egyetemi campus', '大学校园'),
    ('Bevásárlóközpont', '购物中心'), ('Üzletek és szolgáltatások', '商店与服务'),
    ('Bevásárlóközpont', '购物中心'), ('Vasút és helyi kapcsolatok', '铁路与本地交通'),
    ('Metró, HÉV, villamos, busz', '地铁、HÉV、轻轨及公交'),
    ('M3 metró és vasút', 'M3地铁与铁路'), ('Kerületi ügyintézés', '区级公共服务'),
    ('Kórház és rendelőintézet', '医院与门诊'), ('Mindennapi szolgáltatások', '日常服务'),
]


def capture():
    request = Request(SOURCE, headers={'User-Agent': 'HarmatOfferSafety/1.0'})
    with urlopen(request, timeout=30) as response:
        data = response.read(2 * 1024 * 1024 + 1)
        assert response.status == 200 and response.geturl() == SOURCE and len(data) <= 2 * 1024 * 1024
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / 'public-before.html').write_bytes(data)
    tree = html.fromstring(data.decode('utf-8'))
    records, groups = [], []
    for article in tree.xpath('//*[contains(concat(" ",normalize-space(@class)," ")," harmat-neighborhood-distance-card ")]'):
        if not article.xpath('.//li'):
            continue
        title = ' '.join(article.xpath('.//h2')[0].text_content().split())
        key, hu, zh, icon = CATEGORIES[title]
        groups.append({'id': key, 'hu': hu, 'zh': zh, 'icon': icon})
        for li in article.xpath('.//li'):
            records.append({'id': str(len(records) + 1), 'category': key,
                            'distance': ' '.join(li.xpath('.//strong')[0].text_content().split()),
                            'name': ' '.join(li.xpath('.//span')[0].text_content().split()),
                            'sourceNote': ' '.join(li.xpath('.//small')[0].text_content().split())})
    assert len(records) == len(ZH_NAMES) == len(DESCRIPTIONS) == 17 and len(groups) == 5
    assert [item['name'] for item in records] == SOURCE_NAMES, 'Review changed names/order before translating'
    for item, name, description in zip(records, ZH_NAMES, DESCRIPTIONS):
        item.update({'zh': name, 'descriptionHu': description[0], 'descriptionZh': description[1]})
    result = {'source': SOURCE, 'captured': '2026-10-27', 'categories': groups, 'places': records}
    (HERE / 'facilities.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print('PUBLIC_POIS_CAPTURED:17; original names/distances retained')


def build():
    data = json.loads((HERE / 'facilities.json').read_text(encoding='utf-8'))
    assert len(data['places']) == 17
    assert sha256(PARK.read_bytes()).hexdigest() == PARK_SHA
    assets = OUT / 'assets'
    assets.mkdir(parents=True, exist_ok=True)
    names = ['harmat-logo.png', 'lucide.min.js', 'lucide.LICENSE', 'pannellum.js', 'pannellum.css',
             'pannellum.LICENSE', 'panorama-overview.jpg']
    names += ['panorama-' + face + '.jpg' for face in 'frblud']
    names += ['panorama-gallery-' + str(i) + '.jpg' for i in (0, 1, 4, 5, 8, 10)]
    pins = {}
    for name in names:
        source = OLD / name
        assert source.is_file()
        shutil.copyfile(source, assets / name)
        pins[name] = sha256(source.read_bytes()).hexdigest()
    shutil.copyfile(PARK, assets / 'ohegy-park.jpg')
    pins['ohegy-park.jpg'] = PARK_SHA
    template = (HERE / 'index.html').read_text(encoding='utf-8')
    payload = json.dumps(data, ensure_ascii=False).replace('<', '\\u003c')
    assert template.count('__FACILITIES__') == 1
    (OUT / 'index.html').write_text(template.replace('__FACILITIES__', payload), encoding='utf-8')
    for name in ('preview.css', 'preview.js'):
        shutil.copyfile(HERE / name, OUT / name)
    (OUT / 'asset-pins.json').write_text(json.dumps(pins, indent=2) + '\n', encoding='utf-8')
    print('LOCAL_PREVIEW_READY ' + str(OUT / 'index.html'))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--capture', action='store_true')
    args = parser.parse_args()
    if args.capture:
        capture()
    build()
