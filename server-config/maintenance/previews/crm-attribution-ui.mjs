import {channels, fixtures, period, summarize, validDay} from './crm-attribution-model.mjs';

const words = {
  zh: {
    brand: '销售管理', local: '离线预览 · 模拟数据', nav: '询价归因', title: '询价与广告来源',
    from: '开始日期', to: '结束日期', reset: '重置日期', invalidDate: '请选择有效的起止日期。',
    records: '原始询价', qualified: '去重有效客户', unknown: '来源待核实', total: '总计', breakdown: '渠道汇总',
    recordsNote: '按询价登记时间计数', qualifiedNote: '销售确认购房意向，同一客户只计一次', unknownNote: '原始询价中无法确认广告来源的记录',
    channel: '广告来源', youtube: 'YouTube V4 询价', search: 'Google 搜索', facebook: 'Facebook', unknownChannel: '来源待核实',
    all: '全部来源', detailsFilter: '明细来源', inquiries: '询价明细', clients: '有效客户',
    id: '记录', customer: '客户', date: '登记日期', recent: '最近登记', status: '销售核实', evidence: '归因依据',
    confirmed: '购房意向已确认', pending: '待销售核实', invalid: '非有效询价', noIdentity: '身份待核实',
    tag: '活动标记匹配', verifiedSearch: '已核对到搜索广告系列', clickOnly: '有点击标识，系列不明',
    unmapped: '活动尚未核实', missing: '缺少可核实的广告标记', conflict: '来源冲突', linked: '关联询价',
    attribution: '查看依据', none: '当前筛选无记录', reporting: '统计口径',
    sourceRule: 'YouTube V4 和 Facebook 按已核对的活动标记区分。Google 搜索须有明确的搜索系列证据；仅有 Google 点击标识、客户自报来源或空白标记不直接归入任何广告。',
    qualityRule: '销售已确认购房意向且客户身份明确才计入有效客户。重复询价保留；同一客户只计一次。多个已核实渠道冲突时，客户计入「来源待核实」。',
    scopeRule: '仅用于预览；不写回 CRM，不改投放、报价或预约。',
  },
  hu: {
    brand: 'Értékesítés', local: 'Helyi előnézet · Mintaadatok', nav: 'Hirdetési források', title: 'Érdeklődések és hirdetési források',
    from: 'Kezdő dátum', to: 'Záró dátum', reset: 'Dátumok visszaállítása', invalidDate: 'Adjon meg érvényes dátumtartományt.',
    records: 'Érdeklődési rekordok', qualified: 'Egyedi minősített ügyfelek', unknown: 'Nem igazolt forrás', total: 'Összesen', breakdown: 'Csatornák összesítése',
    recordsNote: 'Az érdeklődés rögzítési dátuma szerint', qualifiedNote: 'Igazolt vételi szándék, ügyfelenként egyszer', unknownNote: 'Nem igazolható hirdetési forrású rekordok',
    channel: 'Hirdetési forrás', youtube: 'YouTube V4 érdeklődés', search: 'Google keresés', facebook: 'Facebook', unknownChannel: 'Nem igazolt forrás',
    all: 'Minden forrás', detailsFilter: 'Részletek szűrése', inquiries: 'Érdeklődések', clients: 'Minősített ügyfelek',
    id: 'Rekord', customer: 'Ügyfél', date: 'Rögzítés dátuma', recent: 'Utolsó rögzítés', status: 'Értékesítői visszajelzés', evidence: 'Forrás alapja',
    confirmed: 'Igazolt vételi szándék', pending: 'Ellenőrzésre vár', invalid: 'Nem érvényes érdeklődés', noIdentity: 'Azonosításra vár',
    tag: 'Egyező kampányjelölés', verifiedSearch: 'Igazolt keresési kampány', clickOnly: 'Kattintásazonosító, ismeretlen kampány',
    unmapped: 'Nem igazolt kampány', missing: 'Hiányzó hirdetési jelölés', conflict: 'Ellentmondó források', linked: 'Kapcsolódó rekordok',
    attribution: 'Forrásadatok', none: 'A szűrésre nincs találat', reporting: 'Számítási szabályok',
    sourceRule: 'A YouTube V4 és a Facebook ellenőrzött kampányjelölések alapján különül el. A Google kereséshez igazolt keresési kampány szükséges. A kattintásazonosító vagy az ügyfél közlése önmagában nem elég a besoroláshoz.',
    qualityRule: 'Csak az egyértelműen azonosítható és igazolt vételi szándékú ügyfelek számítanak. Az ismétlődő rekordok megmaradnak. Ügyfelenként egy számítás történik; ellentmondó csatornák esetén a forrás nem igazolt.',
    scopeRule: 'Helyi előnézet. A CRM, a hirdetések, az árajánlatok és az időpontfoglalás nem módosulnak.',
  },
};

const $ = id => document.getElementById(id);
const escape = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const channelLabel = (t, channel) => channel === 'unknown' ? t.unknownChannel : t[channel];
let lang = 'zh';
let view = 'inquiries';
let channel = 'all';
$('start').value = period.start;
$('end').value = period.end;

function render() {
  const t = words[lang];
  document.documentElement.lang = lang === 'hu' ? 'hu' : 'zh-CN';
  document.title = `${t.title} | Harmat Lakópark`;
  document.querySelectorAll('[data-text]').forEach(el => {el.textContent = t[el.dataset.text];});
  document.querySelectorAll('[data-language]').forEach(el => el.setAttribute('aria-pressed', el.dataset.language === lang));
  document.querySelectorAll('[data-view]').forEach(el => {
    const active = el.dataset.view === view;
    el.setAttribute('aria-selected', active);
    el.tabIndex = active ? 0 : -1;
  });
  $('detail-panel').setAttribute('aria-labelledby', `tab-${view}`);
  const start = $('start').value, end = $('end').value;
  const valid = validDay(start) && validDay(end) && start <= end;
  $('date-error').hidden = valid;
  $('report').hidden = !valid;
  if (!valid) return;
  const model = summarize(fixtures, {start, end});
  $('count-records').textContent = model.hits.length;
  $('count-qualified').textContent = model.qualified.length;
  $('count-unknown').textContent = model.breakdown.unknown.records;
  $('channel-rows').innerHTML = channels.map(key => {
    const row = model.breakdown[key];
    return `<tr data-channel="${key}"><th scope="row"><span class="dot ${key}"></span>${channelLabel(t, key)}</th><td class="number">${row.records}</td><td class="number qualified-number">${row.qualified}</td></tr>`;
  }).join('');
  $('total-records').textContent = model.hits.length;
  $('total-qualified').textContent = model.qualified.length;
  $('detail-source').innerHTML = ['all', ...channels].map(key => `<option value="${key}"${channel === key ? ' selected' : ''}>${channelLabel(t, key)}</option>`).join('');
  const columns = view === 'clients' ? ['customer', 'recent', 'channel', 'linked'] : ['id', 'date', 'channel', 'status', 'evidence'];
  $('detail-head').innerHTML = columns.map(key => `<th scope="col">${t[key]}</th>`).join('');
  const rows = (view === 'clients' ? model.qualified : model.hits).filter(row => channel === 'all' || row.channel === channel);
  $('details-count').textContent = rows.length;
  $('detail-rows').innerHTML = rows.map(row => {
    const source = `<span class="dot ${row.channel}"></span>${channelLabel(t, row.channel)}`;
    if (view === 'clients') return `<tr><td>${escape(row.customerKey)}</td><td class="date">${escape(row.date)}</td><td>${source}${row.conflict ? `<small>${t.conflict}</small>` : ''}</td><td class="date">${row.records.map(escape).join(' · ')}</td></tr>`;
    const status = row.status === 'confirmed' && !row.customerKey ? 'noIdentity' : row.status;
    const reason = `<span>${t[row.reason]}</span>`;
    const evidence = row.tag?.campaign ? `<details><summary>${t[row.reason]}</summary><code>${escape(row.tag.campaign)}</code></details>` : reason;
    return `<tr><td>${escape(row.id)}${row.customerKey ? `<small>${escape(row.customerKey)}</small>` : ''}</td><td class="date">${escape(row.date)}</td><td>${source}</td><td><span class="review-state ${status}">${t[status]}</span></td><td>${evidence}</td></tr>`;
  }).join('') || `<tr><td colspan="${columns.length}" class="empty">${t.none}</td></tr>`;
}

document.querySelectorAll('[data-language]').forEach(el => el.addEventListener('click', () => {lang = el.dataset.language; render();}));
document.querySelectorAll('[data-view]').forEach(el => el.addEventListener('click', () => {view = el.dataset.view; render();}));
$('tabs').addEventListener('keydown', e => {
  if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(e.key)) return;
  e.preventDefault();
  view = e.key === 'Home' ? 'inquiries' : e.key === 'End' ? 'clients' : view === 'clients' ? 'inquiries' : 'clients';
  render();
  $(`tab-${view}`).focus();
});
['start', 'end'].forEach(id => $(id).addEventListener('change', render));
$('reset').addEventListener('click', () => {$('start').value = period.start; $('end').value = period.end; render();});
$('detail-source').addEventListener('change', e => {channel = e.target.value; render();});
render();
