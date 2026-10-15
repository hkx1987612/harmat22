import test from 'node:test';
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import { handleRequest, metadataEvents, verifySignature, MAX_BODY_BYTES } from './ingress.mjs';

const PAGE = '100000000000001';
const PEER = '200000000000001';
const env = Object.freeze({ RECEIVE_ENABLED: 'true', DATA_PROCESSING_APPROVED: 'true',
  VERIFY_ENABLED: 'true', META_PAGE_ID: PAGE, META_APP_SECRET: 'synthetic-app-secret-not-live-12345',
  IDENTITY_KEY: 'synthetic-identity-key-not-live-12345',
  META_VERIFY_TOKEN: 'synthetic-verification-token-not-live-12345' });
const event = (changes = {}) => ({ sender: { id: PEER }, recipient: { id: PAGE },
  timestamp: 1792065600000, message: { mid: 'synthetic-mid-1', text: 'Synthetic private question' }, ...changes });
const payload = events => ({ object: 'page', entry: [{ id: PAGE, messaging: events }] });
const sign = bytes => 'sha256=' + createHmac('sha256', env.META_APP_SECRET).update(bytes).digest('hex');
function request(body = JSON.stringify(payload([event()])), extra = {}) {
  return new Request('https://synthetic.invalid/webhook', { method: 'POST', body,
    headers: { 'content-type': 'application/json', 'x-hub-signature-256': sign(body), ...extra } });
}

test('default gates refuse body processing, including misleading sender/model flags', async () => {
  for (const vars of [{}, { ...env, RECEIVE_ENABLED: false }, { ...env, DATA_PROCESSING_APPROVED: 'false' },
    { ...env, META_PAGE_ID: '' }, { ...env, IDENTITY_KEY: 'short' }]) {
    assert.equal((await handleRequest(request(), { ...vars, SEND_ENABLED: 'true' },
      () => assert.fail('must not process'))).status, 503);
  }
});

test('health is static, nonproduction and contains no credentials or configuration', async () => {
  const response = await handleRequest(new Request('https://synthetic.invalid/healthz'), env);
  assert.equal(response.status, 200);
  assert.equal(response.headers.get('cache-control'), 'no-store');
  assert.equal(response.headers.get('access-control-allow-origin'), null);
  const text = await response.text();
  assert.match(text, /sending=false; model=false; production-ready=false/);
  assert.ok(!text.includes(env.META_APP_SECRET));
});

test('only exact webhook path/methods are supported; no public admin/reserve/send endpoint', async () => {
  for (const path of ['/admin', '/resume', '/reserve', '/send', '/webhook/']) {
    assert.equal((await handleRequest(new Request('https://synthetic.invalid' + path), env)).status, 404);
  }
  for (const method of ['PUT', 'DELETE', 'OPTIONS']) {
    assert.equal((await handleRequest(new Request('https://synthetic.invalid/webhook', { method }), env)).status, 405);
  }
});

test('verification needs exact token/mode and rejects duplicated or injectable parameters', async () => {
  const url = 'https://synthetic.invalid/webhook?hub.mode=subscribe&hub.verify_token='
    + env.META_VERIFY_TOKEN + '&hub.challenge=synthetic_challenge';
  const response = await handleRequest(new Request(url), env);
  assert.equal(response.status, 200);
  assert.equal(await response.text(), 'synthetic_challenge');
  for (const suffix of ['&hub.mode=subscribe', '&hub.verify_token=other', '&hub.challenge=again']) {
    assert.equal((await handleRequest(new Request(url + suffix), env)).status, 403);
  }
  assert.equal((await handleRequest(new Request(url.replace('subscribe', 'unsubscribe')), env)).status, 403);
  assert.equal((await handleRequest(new Request(url.replace('synthetic_challenge', '%3Cscript%3E')), env)).status, 403);
  assert.equal((await handleRequest(new Request(url.replace(env.META_VERIFY_TOKEN, 'wrong')), env)).status, 403);
  assert.equal((await handleRequest(new Request(url), {})).status, 503);
});

test('signature verifies raw wire bytes, not decoded/reserialized escaped Unicode', async () => {
  const wire = new TextEncoder().encode('{"question":"\\u00e1r"}');
  assert.equal(await verifySignature(env.META_APP_SECRET, wire, sign(wire)), true);
  const decoded = new TextEncoder().encode(JSON.stringify(JSON.parse(new TextDecoder().decode(wire))));
  assert.equal(await verifySignature(env.META_APP_SECRET, decoded, sign(wire)), false);
  for (const header of [null, '', 'sha1=' + 'a'.repeat(64), 'sha256=' + 'A'.repeat(64), sign(wire) + ',extra']) {
    assert.equal(await verifySignature(env.META_APP_SECRET, wire, header), false);
  }
});

test('invalid signature is rejected before JSON parsing and storage', async () => {
  for (const body of ['not json', JSON.stringify(payload([event()]))]) {
    assert.equal((await handleRequest(request(body, { 'x-hub-signature-256': 'sha256=' + '0'.repeat(64) }),
      env, () => assert.fail('must not store'))).status, 403);
  }
});

test('oversized declared/chunked bodies and unsupported content types fail closed', async () => {
  for (const [body, extra, status] of [
    ['{}', { 'content-length': String(MAX_BODY_BYTES + 1) }, 413],
    ['{}', { 'content-length': 'not-a-number' }, 413],
    ['x'.repeat(MAX_BODY_BYTES + 1), {}, 413],
    ['{}', { 'content-type': 'text/plain' }, 415],
    ['{}', { 'content-encoding': 'gzip' }, 415],
  ]) assert.equal((await handleRequest(request(body, extra), env, () => assert.fail('must not store'))).status, status);
});

test('signed invalid JSON and invalid root/batch shapes never reach storage', async () => {
  for (const body of ['not json', 'null', '{}', '[]', JSON.stringify({ object: 'instagram', entry: [] }),
    JSON.stringify({ object: 'page', entry: Array(21).fill({ id: PAGE, messaging: [] }) }),
    JSON.stringify(payload(Array.from({ length: 51 }, () => event()))),
    JSON.stringify({ object: 'page', entry: [{ id: PAGE, messaging: {} }] })]) {
    assert.equal((await handleRequest(request(body), env, () => assert.fail('must not store'))).status, 400);
  }
});

test('only pseudonymous metadata reaches storage; no text/contact/ID/attachment/URL retained', async () => {
  const source = payload([event({ message: { mid: 'synthetic-mid-1',
    text: 'Synthetic Person synthetic@example.invalid secret-password',
    attachments: [{ payload: { url: 'https://private.invalid/cv.pdf' } }] } })]);
  let captured;
  const response = await handleRequest(request(JSON.stringify(source)), env, events => { captured = events; });
  assert.equal(response.status, 200);
  assert.equal(await response.text(), 'EVENT_RECEIVED');
  assert.equal(captured.length, 1);
  assert.deepEqual(Object.keys(captured[0]).sort(), ['eventKey', 'threadKey', 'timestamp', 'type'].sort());
  assert.match(captured[0].eventKey, /^[a-f0-9]{64}$/);
  assert.match(captured[0].threadKey, /^[a-f0-9]{64}$/);
  for (const privateValue of [PAGE, PEER, 'synthetic-mid-1', 'Synthetic Person', '@', 'secret-password', 'private.invalid']) {
    assert.ok(!JSON.stringify(captured).includes(privateValue));
  }
});

test('pseudonyms are stable per event/thread and domain-separated by page/key', async () => {
  const one = (await metadataEvents(payload([event()]), PAGE, env.IDENTITY_KEY))[0];
  const two = (await metadataEvents(payload([event({ message: { mid: 'synthetic-mid-2' } })]), PAGE, env.IDENTITY_KEY))[0];
  assert.notEqual(one.eventKey, two.eventKey);
  assert.equal(one.threadKey, two.threadKey);
  assert.notEqual(one.eventKey, one.threadKey);
  const changed = (await metadataEvents(payload([event()]), PAGE, env.IDENTITY_KEY + '2'))[0];
  assert.notEqual(one.threadKey, changed.threadKey);
});

test('outgoing echoes pause before incoming messages in the same batch; no echo reply loop', async () => {
  const echo = event({ sender: { id: PAGE }, recipient: { id: PEER }, timestamp: 1792065600001,
    message: { mid: 'echo-mid', is_echo: true, text: 'Staff or other app message' } });
  const events = await metadataEvents(payload([event(), echo]), PAGE, env.IDENTITY_KEY);
  assert.deepEqual(events.map(e => e.type), ['staff', 'message']);
  assert.equal(events[0].threadKey, events[1].threadKey);
});

test('foreign Page, unsupported notifications, reversed identities and malformed fields are ignored', async () => {
  const events = [
    { delivery: {}, sender: { id: PEER }, recipient: { id: PAGE } },
    event({ recipient: { id: PEER } }), event({ sender: { id: PAGE } }),
    event({ timestamp: '1792065600000' }), event({ timestamp: -1 }),
    event({ message: { mid: 'invalid\nmid' } }), event({ message: { mid: 'm', is_echo: 'true' } }),
    event({ sender: { id: {} } }),
  ];
  assert.deepEqual(await metadataEvents(payload(events), PAGE, env.IDENTITY_KEY), []);
  const foreign = payload([event()]); foreign.entry[0].id = PEER;
  assert.deepEqual(await metadataEvents(foreign, PAGE, env.IDENTITY_KEY), []);
});

test('ACK waits for durable commit and storage failure returns retryable failure without raw error', async () => {
  let release;
  const blocked = new Promise(resolve => { release = resolve; });
  let settled = false;
  const pending = handleRequest(request(), env, () => blocked).then(response => { settled = true; return response; });
  await new Promise(resolve => setTimeout(resolve, 10));
  assert.equal(settled, false);
  release();
  assert.equal((await pending).status, 200);
  const failed = await handleRequest(request(), env, () => { throw new Error('private account details'); });
  assert.equal(failed.status, 503);
  assert.equal(await failed.text(), 'Temporarily unavailable');
});

test('unsupported-only valid events ACK without creating any durable state', async () => {
  const response = await handleRequest(request(JSON.stringify(payload([{ read: {} }]))), env,
    () => assert.fail('no supported events'));
  assert.equal(response.status, 200);
});

test('signed public-change/standby notifications ACK without importing comments or conversations', async () => {
  const body = { object: 'page', entry: [{ id: PAGE,
    changes: [{ field: 'feed', value: { message: 'Do not import public comments' } }],
    standby: [{ message: { text: 'Do not import standby text' } }] }] };
  const response = await handleRequest(request(JSON.stringify(body)), env,
    () => assert.fail('outside private message scope'));
  assert.equal(response.status, 200);
});

test('signed malformed UTF-8 is rejected rather than repaired into different content', async () => {
  const bytes = new Uint8Array([123, 34, 97, 34, 58, 34, 0xc3, 0x28, 34, 125]);
  const response = await handleRequest(request(bytes), env, () => assert.fail('must not store'));
  assert.equal(response.status, 400);
});

test('stalled request body times out and cancels without storage or hanging the process', { timeout: 5000 }, async () => {
  let cancelled = false;
  const body = new ReadableStream({ start(controller) { controller.enqueue(new Uint8Array([123])); },
    cancel() { cancelled = true; } });
  const req = new Request('https://synthetic.invalid/webhook', { method: 'POST', body, duplex: 'half',
    headers: { 'content-type': 'application/json', 'x-hub-signature-256': 'sha256=' + '0'.repeat(64) } });
  const response = await handleRequest(req, env, () => assert.fail('must not store'));
  assert.equal(response.status, 408);
  assert.equal(cancelled, true);
});
