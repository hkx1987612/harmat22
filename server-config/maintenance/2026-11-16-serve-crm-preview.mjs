import http from 'node:http';
import fs from 'node:fs';
import {fileURLToPath} from 'node:url';

// Serve only the generated, aggregate-only preview. No file paths or write routes.
const file = fileURLToPath(new URL('../../outputs/2026-11-16-conversion-name-preview/crm-preview/index.html', import.meta.url));
const html = fs.readFileSync(file);
const server = http.createServer((request, response) => {
  if (request.headers.host !== '127.0.0.1:8811' || !['GET', 'HEAD'].includes(request.method)
      || !['/', '/index.html'].includes(request.url)) {
    response.writeHead(404, {'Content-Type': 'text/plain'}); response.end('Not found'); return;
  }
  response.writeHead(200, {'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store',
    'X-Content-Type-Options': 'nosniff', 'Referrer-Policy': 'no-referrer'});
  response.end(request.method === 'HEAD' ? undefined : html);
});
server.listen(8811, '127.0.0.1', () => console.log('Aggregate-only CRM preview: http://127.0.0.1:8811/'));
process.on('SIGINT', () => server.close(() => process.exit(0)));
