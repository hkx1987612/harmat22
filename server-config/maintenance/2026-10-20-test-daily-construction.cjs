'use strict';
const fs = require('node:fs');
const path = require('node:path');
const Module = require('node:module');
let source = fs.readFileSync(path.join(__dirname, '2026-10-18-test-construction-updates.cjs'), 'utf8');
source = source.replace('2026-10-18-test-construction-updates.php', '2026-10-20-test-daily-construction.php')
  .replace("[data-hcu-player]').length, 5", "[data-hcu-player]').length, 7")
  .replaceAll('2026-10-13', '2026-10-20')
  .replaceAll('2026年10月13日', '2026年10月20日')
  .replaceAll('2026. október 13.', '2026. október 20.')
  .replace('/október 13/', '/október 20/')
  .replace('five posters, dated descending', 'seven posters, dated descending');
const suite = new Module(__filename + '.adapted.cjs', module);
suite.filename = __filename;
suite.paths = module.paths;
suite._compile(source, __filename);
