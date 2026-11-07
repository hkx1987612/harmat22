"""Loopback-only synthetic browser fixture, using the exact production JS block.

No customer data, production endpoint, conversion tag or external request.
"""
from http.server import BaseHTTPRequestHandler, HTTPServer
import json
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
source = (ROOT / 'wp-mu-plugins/harmat-unified-offer-modal.php').read_text(encoding='utf-8')
block = source[source.index('  function fillTracking() {'):source.index('\n  function open(trigger) {')]
block, replacements = re.subn(r'<\?php echo wp_json_encode\(function_exists\(\'hm_legal_policy_version_20260601\'\)[\s\S]*?\?>',
                              json.dumps('2026-06-09-v1.6'), block)
assert replacements == 1 and '<?php' not in block
names = ('utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'source_url', 'landing_page', 'referrer')
inputs = ''.join('<input type="hidden" name="' + name + '">' for name in names)
body = '''<!doctype html><meta charset="utf-8"><title>Synthetic attribution QA</title>
<style>body{font:16px/1.6 Arial;margin:24px;max-width:800px;color:#203b32;background:#f2f4f2}
button,a{display:inline-block;margin:8px;padding:12px;color:#173c30}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style>
<h1>Synthetic attribution QA</h1><p>Isolated fixture. No live inquiry or advertising event.</p>
<button data-hm-cookie-accept>Grant test consent</button>
<a href="/property/a1-1-l6/">Open untagged detail</a>
<button id="check">Check metadata</button><button id="cleanup">Restore test settings</button>
<form id="fixture">INPUTS</form><pre id="result">Ready</pre>
<script>
var consentName='harmat_cookie_consent_v1';
var priorKey='harmatAttributionQaPriorConsent';
if(!sessionStorage.getItem(priorKey)){
  var original=document.cookie.split('; ').find(function(row){return row.indexOf(consentName+'=')===0;})||'';
  sessionStorage.setItem(priorKey,JSON.stringify({original:original}));
}
function setField(name,value){document.querySelector('#fixture [name="'+name+'"]').value=value;}
BLOCK
document.querySelector('[data-hm-cookie-accept]').addEventListener('click',function(){
  document.cookie=consentName+'='+encodeURIComponent(JSON.stringify({policyVersion:'2026-06-09-v1.6',marketing:true,analytics:false}))+'; Path=/; SameSite=Lax';
  document.querySelector('#result').textContent='Test consent granted';
});
document.querySelector('#check').addEventListener('click',function(){
  fillTracking();var value=function(n){return document.querySelector('#fixture [name="'+n+'"]').value;};
  var expected=location.origin+'/entry?utm_source=synthetic_qa&utm_medium=qa&utm_campaign=synthetic_20261107&utm_content=details';
  var checks=[['campaign retained',value('utm_campaign')==='synthetic_20261107'],
    ['source retained',value('utm_source')==='synthetic_qa'],['medium retained',value('utm_medium')==='qa'],
    ['content retained',value('utm_content')==='details'],['first entry retained',value('landing_page')===expected],
    ['current page separate',value('source_url')===location.href]];
  document.querySelector('#result').textContent=checks.map(function(c){return(c[1]?'PASS ':'FAIL ')+c[0];}).join('\\n');
});
document.querySelector('#cleanup').addEventListener('click',function(){
  var prior=JSON.parse(sessionStorage.getItem(priorKey)||'{}');
  document.cookie=consentName+'=; Max-Age=0; Path=/';
  if(prior.original)document.cookie=prior.original+'; Path=/; SameSite=Lax';
  sessionStorage.removeItem('harmatOfferAttributionV1');sessionStorage.removeItem(priorKey);
  document.querySelector('#result').textContent='Test settings restored';
});
</script>'''.replace('INPUTS', inputs).replace('BLOCK', block).encode()


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Cache-Control', 'no-store')
        self.send_header('Content-Security-Policy', "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; connect-src 'none'; form-action 'none'; base-uri 'none'")
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass


if __name__ == '__main__':
    server = HTTPServer(('127.0.0.1', 0), Handler)
    print('http://127.0.0.1:' + str(server.server_port) + '/entry?utm_source=synthetic_qa&utm_medium=qa&utm_campaign=synthetic_20261107&utm_content=details', flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
