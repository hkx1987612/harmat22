import fs from 'node:fs';
import path from 'node:path';
import {createHash} from 'node:crypto';
import {fileURLToPath} from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const input = name => fs.readFileSync(path.join(here, 'previews', name), 'utf8').replace(/\r\n/g, '\n');
const model = input('crm-attribution-model.mjs').replace(/^export /gm, '');
const ui = input('crm-attribution-ui.mjs');
const bridge = /^import \{[^}]+\} from '\.\/crm-attribution-model\.mjs';\n/;
if (!bridge.test(ui)) throw new Error('UI imports changed');
const css = input('crm-attribution.css');
const js = model + '\n' + ui.replace(bridge, '');
const sha = raw => createHash('sha256').update(raw).digest('base64');
const output = path.join(here, '../../outputs/2026-11-23-crm-attribution-preview');
const html = `<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'sha256-${sha(js)}'; style-src 'sha256-${sha(css)}'; connect-src 'none'; form-action 'none'; base-uri 'none'">
  <title>询价与广告来源 | Harmat Lakópark</title>
  <style>${css}</style>
</head>
<body>
  <header class="masthead"><div class="masthead-inner">
    <div class="brand"><b>Harmat Lakópark</b><span data-text="brand">销售管理</span></div>
    <div class="tools"><span class="preview-label" data-text="local">离线预览 · 模拟数据</span><div class="languages" role="group" aria-label="Language"><button type="button" data-language="zh" aria-pressed="true">中文</button><button type="button" data-language="hu" aria-pressed="false" lang="hu">Magyar</button></div></div>
  </div></header>
  <main>
    <div class="page-title">
      <div><p class="eyebrow" data-text="nav"></p><h1 data-text="title"></h1></div>
      <div class="period-controls"><label><span data-text="from"></span><input id="start" type="date"></label><label><span data-text="to"></span><input id="end" type="date"></label><button type="button" id="reset" class="quiet-button" data-text="reset"></button></div>
    </div>
    <p id="date-error" class="date-error" role="status" data-text="invalidDate" hidden></p>
    <div id="report">
      <section class="metrics" aria-label="Overview">
        <div class="metric"><span class="metric-label" data-text="records"></span><strong id="count-records"></strong><p data-text="recordsNote"></p></div>
        <div class="metric focus"><span class="metric-label" data-text="qualified"></span><strong id="count-qualified"></strong><p data-text="qualifiedNote"></p></div>
        <div class="metric"><span class="metric-label" data-text="unknown"></span><strong id="count-unknown"></strong><p data-text="unknownNote"></p></div>
      </section>
      <section aria-labelledby="summary-title">
        <div class="section-name"><h2 id="summary-title" data-text="breakdown"></h2></div>
        <div class="table-wrap"><table aria-labelledby="summary-title">
          <thead><tr><th scope="col" data-text="channel"></th><th scope="col" class="number" data-text="records"></th><th scope="col" class="number" data-text="qualified"></th></tr></thead>
          <tbody id="channel-rows"></tbody>
          <tfoot><tr><th scope="row" data-text="total"></th><td class="number" id="total-records"></td><td class="number qualified-number" id="total-qualified"></td></tr></tfoot>
        </table></div>
      </section>
      <section class="detail-section">
        <div class="detail-toolbar">
          <div class="tabs" id="tabs" role="tablist" aria-label="Details"><button type="button" role="tab" id="tab-inquiries" data-view="inquiries" data-text="inquiries" aria-controls="detail-panel" aria-selected="true"></button><button type="button" role="tab" id="tab-clients" data-view="clients" data-text="clients" aria-controls="detail-panel" aria-selected="false" tabindex="-1"></button></div>
          <div class="detail-tools"><label><span data-text="detailsFilter"></span><select id="detail-source"></select></label><span class="count" id="details-count" role="status"></span></div>
        </div>
        <div class="table-wrap" id="detail-panel" role="tabpanel" aria-labelledby="tab-inquiries" tabindex="0"><table class="detail-table"><thead><tr id="detail-head"></tr></thead><tbody id="detail-rows"></tbody></table></div>
      </section>
      <details class="rules"><summary data-text="reporting"></summary><p data-text="sourceRule"></p><p data-text="qualityRule"></p></details>
      <footer data-text="scopeRule"></footer>
    </div>
  </main>
  <script>${js}</script>
</body></html>`;
fs.mkdirSync(output, {recursive: true});
fs.writeFileSync(path.join(output, 'index.html'), html);
console.log(`Offline synthetic-data preview: ${path.join(output, 'index.html')}`);
