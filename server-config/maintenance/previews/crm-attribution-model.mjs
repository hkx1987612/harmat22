export const channels = ['youtube', 'search', 'facebook', 'unknown'];
export const period = {start: '2026-11-09', end: '2026-11-22'};

const verifiedTags = {
  harmat_v4_leads_202611: {channel: 'youtube', source: 'youtube', medium: 'paid_video'},
  harmat_v4_fb_202611: {channel: 'facebook', source: 'facebook', medium: 'paid_social'},
};

// A click identifier or self-reported source is not campaign evidence.
export function attribute({tag, clickIdPresent, verifiedSearch, sourceConflict}) {
  if (sourceConflict) return {channel: 'unknown', reason: 'conflict'};
  const mapped = Object.hasOwn(verifiedTags, tag?.campaign) ? verifiedTags[tag.campaign] : null;
  if (mapped) {
    if (verifiedSearch || (tag.source && tag.source !== mapped.source) || (tag.medium && tag.medium !== mapped.medium)) {
      return {channel: 'unknown', reason: 'conflict'};
    }
    return {channel: mapped.channel, reason: 'tag'};
  }
  if (verifiedSearch) {
    return tag?.source && tag.source !== 'google'
      ? {channel: 'unknown', reason: 'conflict'} : {channel: 'search', reason: 'verifiedSearch'};
  }
  return {channel: 'unknown', reason: tag?.campaign ? 'unmapped' : clickIdPresent ? 'clickOnly' : 'missing'};
}

export function validDay(day) {
  return /^\d{4}-\d{2}-\d{2}$/.test(day ?? '') && Number.isFinite(Date.parse(day)) && new Date(day).toISOString().slice(0, 10) === day;
}

export function summarize(records, {start, end}) {
  if (!validDay(start) || !validDay(end) || start > end) throw new RangeError('Invalid date range');
  const hits = records.filter(r => r.date >= start && r.date <= end)
    .map(r => ({...r, ...attribute(r)})).sort((a, b) => b.date.localeCompare(a.date) || b.id.localeCompare(a.id));
  const breakdown = Object.fromEntries(channels.map(channel => [channel, {records: 0, qualified: 0}]));
  const people = new Map();
  for (const row of hits) {
    breakdown[row.channel].records++;
    if (!row.customerKey || !['confirmed', 'pending'].includes(row.status)) continue;
    if (!people.has(row.customerKey)) people.set(row.customerKey, []);
    people.get(row.customerKey).push(row);
  }
  const qualified = [];
  for (const [customerKey, group] of people) {
    if (!group.some(row => row.status === 'confirmed')) continue;
    const known = new Set(group.map(row => row.channel).filter(channel => channel !== 'unknown'));
    const conflict = known.size > 1 || group.some(row => row.reason === 'conflict');
    const channel = !conflict && known.size === 1 ? [...known][0] : 'unknown';
    qualified.push({customerKey, channel, date: group[0].date, records: group.map(row => row.id), conflict});
    breakdown[channel].qualified++;
  }
  return {hits, qualified, breakdown};
}

const youtube = {source: 'youtube', medium: 'paid_video', campaign: 'harmat_v4_leads_202611'};
const facebook = {source: 'facebook', medium: 'paid_social', campaign: 'harmat_v4_fb_202611'};
export const fixtures = [
  {id: 'I-01', date: '2026-11-09', customerKey: 'C-01', status: 'confirmed', tag: youtube},
  {id: 'I-02', date: '2026-11-10', customerKey: 'C-01', status: 'pending', tag: youtube},
  {id: 'I-03', date: '2026-11-11', customerKey: 'C-02', status: 'pending', tag: youtube},
  {id: 'I-04', date: '2026-11-12', customerKey: 'C-03', status: 'confirmed', verifiedSearch: true},
  {id: 'I-05', date: '2026-11-13', customerKey: 'C-03', status: 'confirmed', verifiedSearch: true},
  {id: 'I-06', date: '2026-11-14', customerKey: 'C-04', status: 'invalid', verifiedSearch: true},
  {id: 'I-07', date: '2026-11-16', customerKey: 'C-05', status: 'confirmed', tag: facebook},
  {id: 'I-08', date: '2026-11-17', customerKey: 'C-06', status: 'pending', tag: facebook},
  {id: 'I-09', date: '2026-11-18', customerKey: 'C-07', status: 'pending', clickIdPresent: true},
  {id: 'I-10', date: '2026-11-19', customerKey: 'C-08', status: 'confirmed'},
  {id: 'I-11', date: '2026-11-20', customerKey: 'C-09', status: 'confirmed', tag: youtube},
  {id: 'I-12', date: '2026-11-21', customerKey: 'C-09', status: 'pending', tag: facebook},
  {id: 'I-13', date: '2026-11-22', customerKey: null, status: 'confirmed', clickIdPresent: true},
];
