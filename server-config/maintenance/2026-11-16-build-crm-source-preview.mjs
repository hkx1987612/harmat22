import fs from 'node:fs';
import path from 'node:path';
import {createHash} from 'node:crypto';
import {fileURLToPath} from 'node:url';
import {reviewModel, escapeHtml} from './previews/crm-source-review.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const auditPath = path.join(root, 'outputs/2026-11-16-conversion-audit/audit-summary.json');
const output = path.join(root, 'outputs/2026-11-16-conversion-name-preview/crm-preview');
const audit = JSON.parse(fs.readFileSync(auditPath, 'utf8'));
reviewModel(audit);
const css = fs.readFileSync(path.join(root, 'server-config/maintenance/previews/crm-source-review.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'server-config/maintenance/previews/crm-source-review.mjs'), 'utf8').replace(/^export /gm, '');
const hash = text => createHash('sha256').update(text).digest('base64');
const data = JSON.stringify({reporting_period: audit.reporting_period, verified: {crm: audit.verified.crm}}).replace(/</g, '\\u003c');
const html = `<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'sha256-${hash(js)}'; style-src 'sha256-${hash(css)}'; connect-src 'none'; form-action 'none'; base-uri 'none'"><title>询价与广告来源 | Harmat Lakópark</title><style>${css}</style></head><body>
<header><div class="brand"><b>Harmat Lakópark</b><span id="brand-label">销售管理</span></div><div class="tools"><span id="mode" class="mode">离线预览 · 未上线</span><div class="languages" role="group" aria-label="Language"><button type="button" data-language="zh" aria-pressed="true">中文</button><button type="button" data-language="hu" aria-pressed="false">Magyar</button></div></div></header>
<div class="shell"><aside><span class="nav-label" id="nav-label">询价与来源</span></aside><main><div class="heading"><h1 id="page-title">询价与广告来源</h1><div class="period"><span id="period-label">核查周期</span><strong id="period">${escapeHtml(audit.reporting_period.start)} – ${escapeHtml(audit.reporting_period.end)}</strong></div></div>
<div class="views" role="tablist" aria-label="Review views"><button type="button" role="tab" data-view="actual" aria-selected="true" aria-controls="review">核查汇总</button><button type="button" role="tab" data-view="examples" aria-selected="false" aria-controls="review">来源展示示例</button></div><div id="review" role="tabpanel"></div><footer id="footer-label">报价、预约与原始客户记录保持不变</footer></main></div>
<script id="review-data" type="application/json">${data}</script><script>${js}</script></body></html>`;
fs.mkdirSync(output, {recursive: true});
fs.writeFileSync(path.join(output, 'index.html'), html);
console.log(JSON.stringify({output: path.join(output, 'index.html'),network:'disabled_by_csp',data:'Verified aggregate counts only; source-detail fixtures are a separate labelled view',productionChanges:false}));
