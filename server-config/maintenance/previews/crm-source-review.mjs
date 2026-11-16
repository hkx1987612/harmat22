export const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g,
  c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

export function reviewModel(audit) {
  const c = audit?.verified?.crm;
  const keys = ['period_website_records', 'period_walkin_registration_records', 'period_broker_records'];
  if (!c || keys.some(k => !Number.isSafeInteger(c[k]) || c[k] < 0)) {
    throw new Error('A verified record-count audit is required. Missing values must not become zero.');
  }
  const range = audit.reporting_period;
  const validDate = value => /^\d{4}-\d{2}-\d{2}$/.test(value ?? '')
    && Number.isFinite(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
  if (!validDate(range?.start) || !validDate(range?.end) || range.start > range.end) {
    throw new Error('Invalid reporting period');
  }
  if (!Number.isSafeInteger(keys.reduce((sum, key) => sum + c[key], 0))) throw new Error('Unsafe record total');
  const optionalCount = (key, bound) => {
    if (c[key] === undefined || c[key] === null) return null;
    if (!Number.isSafeInteger(c[key]) || c[key] < 0 || c[key] > bound) throw new Error('Invalid supporting record count');
    return c[key];
  };
  return {
    start: range.start, end: range.end,
    counts: {website: c[keys[0]], walkin: c[keys[1]], broker: c[keys[2]]},
    total: keys.reduce((sum, key) => sum + c[key], 0),
    unrecorded: optionalCount('website_records_with_contact_progress_not_recorded', c[keys[0]]),
    possibleReturning: optionalCount('walkin_records_matching_earlier_website_email_in_loaded_records', c[keys[1]]),
    qualified: null, visits: null, attribution: 'unverified',
  };
}

// These are interface fixtures, never a substitute for saved customer attribution.
export const sourceExamples = Object.freeze([
  {id: 'S-01', channel: 'google', date: '2026-11-15 16:30', type: 'website',
    utm_source: 'google', utm_medium: 'cpc', utm_campaign: 'example_search', utm_content: '', utm_term: '',
    landing_page: 'https://harmat22.hu/uj-otthonok/', source_page: 'https://harmat22.hu/property/example/', referrer: 'https://www.google.com/'},
  {id: 'S-02', channel: 'youtube', date: '2026-11-14 10:15', type: 'website',
    utm_source: 'youtube', utm_medium: 'paid_video', utm_campaign: 'example_v4_leads', utm_content: 'example_60s', utm_term: '',
    landing_page: 'https://harmat22.hu/uj-otthonok/', source_page: 'https://harmat22.hu/property/example/', referrer: 'https://www.youtube.com/'},
  {id: 'S-03', channel: 'facebook', date: '2026-11-13 13:00', type: 'website',
    utm_source: 'facebook', utm_medium: 'paid_social', utm_campaign: 'example_v4_social', utm_content: 'example_60s', utm_term: '',
    landing_page: 'https://harmat22.hu/uj-otthonok/', source_page: 'https://harmat22.hu/elerhetosegeink/', referrer: ''},
  {id: 'S-04', channel: 'missing', date: '2026-11-12 09:45', type: 'website',
    utm_source: '', utm_medium: '', utm_campaign: '', utm_content: '', utm_term: '',
    landing_page: '', source_page: 'https://harmat22.hu/property/example/', referrer: ''},
]);

const sourceFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'landing_page', 'source_page', 'referrer'];

export function recordedSource(record) {
  if (record?.source_read !== true) return 'unread';
  return ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer']
    .some(k => typeof record[k] === 'string' && record[k].trim()) ? 'recorded' : 'missing';
}

export function filterExamples(rows, channel = 'all', order = 'desc') {
  return rows.filter(r => channel === 'all' || r.channel === channel)
    .sort((a, b) => (order === 'asc' ? 1 : -1) * a.date.localeCompare(b.date));
}

const copy = {
  zh: {
    brand: '销售管理', local: '离线预览 · 未上线', nav: '询价与来源',
    title: '询价与广告来源', actual: '核查汇总', examples: '来源展示示例',
    period: '核查周期', records: '原始询价记录', qualified: '去重有效客户', visits: '已确认到访',
    pending: '待核实', verifiedSummary: '已核实记录汇总', sampleTitle: '来源明细示例',
    sampleNotice: '以下均为界面示例，不是真实客户、询价或广告效果，不计入核查汇总。',
    actualNotice: '记录数不等于新增客户数。有效客户与实际到访未核实，不按 0 计算。',
    rawNote: '网站询价＋自来登记＋经纪人询价', qualifiedNote: '按客户去重，并由销售确认购房需求',
    visitsNote: '须有到访确认与实际到访日期', type: '记录类型', count: '记录数', source: '广告来源',
    progress: '跟进结果', website: '网站询价', walkin: '自来询价', broker: '经纪人询价', total: '总计',
    unread: '来源待核实', missing: '来源未记录', recorded: '参数已记录', attribution: '广告归因未确认',
    registered: '按登记日期统计，不等于当日到访', noQualification: '尚无有效客户核实结果',
    noAutoMerge: '同邮箱仅提示可能回访，不自动合并、删除或标记有效。',
    outstanding: n => `${n} 条网站询价的联系进展未记录。`,
    returning: n => `${n} 条自来询价与较早的网站记录邮箱相同，可能是后续登记。`,
    emptySource: '未记录不代表自然流量，也不代表拒绝 Cookie。',
    sourceLimit: '本次汇总未逐条读取已保存的UTM，不能据此认定历史来源丢失。',
    footer: '报价、预约与原始客户记录保持不变', sampleOnly: '界面示例 · 非真实数据',
    all: '全部来源', channel: '来源筛选', order: '排序', desc: '最新在前', asc: '最旧在前',
    id: '示例记录', date: '登记时间', detail: '来源详情', stage: '客户结果',
    evidence: '记录与核实状态', definition: '统计口径',
    fields: {landing_page: '落地页', source_page: '询价页面', referrer: '来源网址'},
    fieldNote: '显示已保存字段，不将参数记录自动认定为广告归因。',
    testInput: '示例字段', noSourceRows: '当前筛选没有示例记录', detailsLabel: '查看来源详情',
  },
  hu: {
    brand: 'Értékesítés', local: 'Helyi előnézet · Nem éles', nav: 'Érdeklődések és források',
    title: 'Érdeklődések és hirdetési források', actual: 'Ellenőrzött összesítés', examples: 'Forrásmegjelenítési példa',
    period: 'Vizsgált időszak', records: 'Érdeklődési rekordok', qualified: 'Egyedi, minősített érdeklődők', visits: 'Igazolt látogatások',
    pending: 'Nem ellenőrzött', verifiedSummary: 'Ellenőrzött rekordszámok', sampleTitle: 'Forrásadatok mintanézete',
    sampleNotice: 'Kizárólag mintarekordok. Nem valós ügyfél-, érdeklődési vagy hirdetési eredmények; nem részei az összesítésnek.',
    actualNotice: 'A rekordszám nem azonos az új ügyfelek számával. A nem ellenőrzött eredmény nem nulla.',
    rawNote: 'Weboldali + saját rögzítés + közvetítői rekordok', qualifiedNote: 'Ügyfél szerinti deduplikálás és értékesítői megerősítés',
    visitsNote: 'Megerősített személyes látogatás és tényleges dátum szükséges', type: 'Rekord típusa', count: 'Rekordszám', source: 'Hirdetési forrás',
    progress: 'Követési eredmény', website: 'Weboldali érdeklődés', walkin: 'Saját érdeklődés', broker: 'Közvetítői érdeklődés', total: 'Összesen',
    unread: 'Forrás nem ellenőrzött', missing: 'Nincs rögzített forrás', recorded: 'Paraméter rögzítve', attribution: 'Hirdetési hozzárendelés nem igazolt',
    registered: 'Rögzítési dátum, nem feltétlenül látogatási dátum', noQualification: 'A minősítés még nem ellenőrzött',
    noAutoMerge: 'Azonos e-mail csak lehetséges visszatérést jelez. Nincs automatikus összevonás, törlés vagy minősítés.',
    outstanding: n => `${n} weboldali rekordnál nincs rögzített kapcsolatfelvételi előrehaladás.`,
    returning: n => `${n} saját rekord e-mailje korábbi weboldali rekorddal egyezik; lehetséges későbbi rögzítés.`,
    emptySource: 'A hiányzó mező nem bizonyít organikus forrást vagy elutasított sütiket.',
    sourceLimit: 'Az összesítés nem olvasta ki rekordonként a tárolt UTM-adatokat; a forrás elvesztése ebből nem állapítható meg.',
    footer: 'Árajánlatok, időpontfoglalás és eredeti ügyféladatok változatlanok', sampleOnly: 'Mintanézet · Nem valós adatok',
    all: 'Minden forrás', channel: 'Forrásszűrő', order: 'Rendezés', desc: 'Legújabb elöl', asc: 'Legrégebbi elöl',
    id: 'Mintarekord', date: 'Rögzítés ideje', detail: 'Forrás részletei', stage: 'Ügyféleredmény',
    evidence: 'Rekordok és ellenőrzési állapot', definition: 'Mutatók jelentése',
    fields: {landing_page: 'Belépési oldal', source_page: 'Érdeklődés oldala', referrer: 'Hivatkozó oldal'},
    fieldNote: 'A tárolt mezők megjelenítése nem igazolja önmagában a hirdetési hozzárendelést.',
    testInput: 'Mintamezők', noSourceRows: 'Nincs mintarekord a jelenlegi szűrésben', detailsLabel: 'Forrásadatok megtekintése',
  },
};

export function sourceDetails(record, lang = 'zh') {
  const t = copy[lang] || copy.zh;
  const state = recordedSource(record);
  const labels = {unread: t.unread, missing: t.missing, recorded: t.recorded};
  const fields = sourceFields.map(key => `<div><dt>${escapeHtml(t.fields[key] || key)}</dt><dd>${escapeHtml(record.source_read === true ? (record[key] || '—') : t.pending)}</dd></div>`).join('');
  return `<details class="source-details"><summary>${escapeHtml(t.detailsLabel)}<span class="state ${state}">${escapeHtml(labels[state])}</span></summary><dl>${fields}</dl><p>${escapeHtml(state === 'missing' ? t.emptySource : t.fieldNote)}</p></details>`;
}

function boot() {
  const data = JSON.parse(document.getElementById('review-data').textContent);
  const model = reviewModel(data);
  let language = 'zh';
  let view = 'actual';
  let channel = 'all';
  let order = 'desc';
  const app = document.getElementById('review');

  function render() {
    const t = copy[language];
    document.documentElement.lang = language === 'hu' ? 'hu' : 'zh-CN';
    document.title = `${t.title} | Harmat Lakópark`;
    document.getElementById('mode').textContent = view === 'actual' ? t.local : t.sampleOnly;
    document.getElementById('brand-label').textContent = t.brand;
    document.getElementById('nav-label').textContent = t.nav;
    document.getElementById('page-title').textContent = t.title;
    document.getElementById('period-label').textContent = t.period;
    document.getElementById('period').textContent = `${model.start} – ${model.end}`;
    document.getElementById('footer-label').textContent = t.footer;
    document.querySelectorAll('[data-language]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.language === language)));
    document.querySelectorAll('[data-view]').forEach(b => {
      b.textContent = t[b.dataset.view === 'actual' ? 'actual' : 'examples'];
      b.setAttribute('aria-selected', String(b.dataset.view === view));
      b.tabIndex = b.dataset.view === view ? 0 : -1;
    });
    const metrics = view === 'actual' ? `<section class="metrics" aria-label="${t.evidence}">
      <article><span>${t.records}</span><strong data-metric="records">${model.total}</strong><p>${t.rawNote}</p></article>
      <article><span>${t.qualified}</span><strong class="unknown" data-metric="qualified">${t.pending}</strong><p>${t.qualifiedNote}</p></article>
      <article><span>${t.visits}</span><strong class="unknown" data-metric="visits">${t.pending}</strong><p>${t.visitsNote}</p></article>
    </section>` : '';
    const actualRows = Object.entries(model.counts).map(([key, count]) => `<tr><td><strong>${t[key]}</strong>${key === 'walkin' ? `<small>${t.registered}</small>` : ''}</td><td class="number">${count}</td><td><span class="status neutral">${t.unread}</span></td><td>${key === 'website' && model.unrecorded !== null ? t.outstanding(model.unrecorded) : t.noQualification}</td></tr>`).join('');
    const body = view === 'actual' ? `${metrics}<section class="section"><div class="section-head"><h2>${t.verifiedSummary}</h2><span>${model.start} – ${model.end}</span></div><p class="notice">${t.actualNotice}</p>
      <div class="table-scroll"><table class="aggregate-table"><thead><tr><th>${t.type}</th><th class="number">${t.count}</th><th>${t.source}</th><th>${t.progress}</th></tr></thead><tbody>${actualRows}</tbody><tfoot><tr><th>${t.total}</th><td class="number">${model.total}</td><td>${t.pending}</td><td>${t.noQualification}</td></tr></tfoot></table></div>
      <p class="source-limit">${t.sourceLimit}</p></section>
      <section class="section observations"><h2>${t.evidence}</h2><ul>${model.unrecorded !== null ? `<li>${t.outstanding(model.unrecorded)}</li>` : ''}${model.possibleReturning !== null ? `<li>${t.returning(model.possibleReturning)}</li>` : ''}<li>${t.noAutoMerge}</li></ul></section>` : `<section class="section"><div class="section-head"><h2>${t.sampleTitle}</h2><div class="filters"><label><span>${t.channel}</span><select id="source-filter" aria-label="${t.channel}">${[['all', t.all], ['google', 'Google'], ['youtube', 'YouTube'], ['facebook', 'Facebook'], ['missing', t.missing]].map(([key, label]) => `<option value="${key}"${channel === key ? ' selected' : ''}>${label}</option>`).join('')}</select></label><label><span>${t.order}</span><select id="source-order" aria-label="${t.order}"><option value="desc"${order === 'desc' ? ' selected' : ''}>${t.desc}</option><option value="asc"${order === 'asc' ? ' selected' : ''}>${t.asc}</option></select></label></div></div><p class="notice sample-notice">${t.sampleNotice}</p>
      <div class="table-scroll"><table class="example-table"><thead><tr><th>${t.id}</th><th>${t.date}</th><th>${t.source}</th><th>${t.stage}</th><th>${t.detail}</th></tr></thead><tbody>${filterExamples(sourceExamples, channel, order).map(row => `<tr><td><strong>${row.id}</strong><small>${t.website}</small></td><td>${row.date}</td><td><strong>${escapeHtml(row.utm_source || t.missing)}</strong><small>${t.attribution}</small></td><td><span class="status neutral">${t.pending}</span></td><td>${sourceDetails({...row, source_read: true}, language)}</td></tr>`).join('') || `<tr><td colspan="5">${t.noSourceRows}</td></tr>`}</tbody></table></div></section>`;
    app.innerHTML = body;
    document.getElementById('source-filter')?.addEventListener('change', e => {channel = e.target.value; render();});
    document.getElementById('source-order')?.addEventListener('change', e => {order = e.target.value; render();});
  }
  document.querySelectorAll('[data-language]').forEach(b => b.addEventListener('click', () => {language = b.dataset.language; render();}));
  document.querySelectorAll('[data-view]').forEach(b => b.addEventListener('click', () => {view = b.dataset.view; render();}));
  document.querySelector('[role="tablist"]').addEventListener('keydown', event => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    view = event.key === 'Home' ? 'actual' : event.key === 'End' ? 'examples' : view === 'actual' ? 'examples' : 'actual';
    render();
    document.querySelector(`[data-view="${view}"]`).focus();
  });
  render();
}

if (typeof document !== 'undefined') boot();
