// Receive-only boundary. No model client, message sender, logging or WordPress calls.
export const MAX_BODY_BYTES = 65536;
export const MAX_EVENTS = 50;
const encoder = new TextEncoder();
const id = value => typeof value === 'string' && /^[0-9]{5,32}$/.test(value);
const mid = value => typeof value === 'string' && /^[A-Za-z0-9_:.=\-]{1,256}$/.test(value);
const secret = (value, min) => typeof value === 'string' && value.length >= min && value.length <= 256;
const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const headers = { 'content-type': 'text/plain; charset=utf-8', 'cache-control': 'no-store',
  'x-content-type-options': 'nosniff' };
const reply = (status, text) => new Response(text, { status, headers });

async function hmacKey(value) {
  return crypto.subtle.importKey('raw', encoder.encode(value),
    { name: 'HMAC', hash: 'SHA-256' }, false, ['sign', 'verify']);
}

export async function verifySignature(appSecret, bytes, signature) {
  if (!secret(appSecret, 16) || !(bytes instanceof Uint8Array)
      || typeof signature !== 'string' || !/^sha256=[0-9a-f]{64}$/.test(signature)) return false;
  const supplied = Uint8Array.from(signature.slice(7).match(/../g), pair => parseInt(pair, 16));
  return crypto.subtle.verify('HMAC', await hmacKey(appSecret), supplied, bytes);
}

async function pseudonym(key, scope, parts) {
  const hash = await crypto.subtle.sign('HMAC', key, encoder.encode(JSON.stringify([scope, ...parts])));
  return [...new Uint8Array(hash)].map(n => n.toString(16).padStart(2, '0')).join('');
}

// Do not reserialize before signature validation: escaped Unicode is signed as wire bytes.
async function readBounded(request) {
  const length = request.headers.get('content-length');
  if (length !== null && (!/^\d+$/.test(length) || Number(length) > MAX_BODY_BYTES)) {
    throw new Error('body_limit');
  }
  const reader = request.body?.getReader();
  if (!reader) throw new Error('body_empty');
  const chunks = [];
  let size = 0;
  let timer;
  const deadline = new Promise((_, reject) => {
    timer = setTimeout(() => reject(new Error('body_timeout')), 2000);
  });
  try {
    while (true) {
      const { done, value } = await Promise.race([reader.read(), deadline]);
      if (done) break;
      size += value.byteLength;
      if (size > MAX_BODY_BYTES) throw new Error('body_limit');
      chunks.push(value);
    }
  } catch (error) {
    void reader.cancel().catch(() => {});
    throw error;
  } finally {
    clearTimeout(timer);
    reader.releaseLock();
  }
  const bytes = new Uint8Array(size);
  let offset = 0;
  for (const chunk of chunks) { bytes.set(chunk, offset); offset += chunk.byteLength; }
  return bytes;
}

export async function metadataEvents(body, pageId, identityKey) {
  if (!id(pageId) || !secret(identityKey, 32)) throw new Error('configuration');
  if (!object(body) || body.object !== 'page' || !Array.isArray(body.entry)
      || body.entry.length > 20) throw new Error('shape');
  let count = 0;
  const pending = [];
  for (const entry of body.entry) {
    if (!object(entry)) throw new Error('shape');
    // Other signed Page notifications (for example changes/standby) are not private messages.
    if (entry.messaging === undefined) continue;
    if (!Array.isArray(entry.messaging)) throw new Error('shape');
    count += entry.messaging.length;
    if (count > MAX_EVENTS) throw new Error('event_limit');
    if (entry.id !== pageId) continue;
    for (const event of entry.messaging) {
      if (!object(event) || !object(event.message) || !mid(event.message.mid)
          || !Number.isSafeInteger(event.timestamp) || event.timestamp < 0) continue;
      const echo = event.message.is_echo === true;
      if (event.message.is_echo !== undefined && typeof event.message.is_echo !== 'boolean') continue;
      const sender = event.sender?.id;
      const recipient = event.recipient?.id;
      if (!id(sender) || !id(recipient)) continue;
      if (echo ? sender !== pageId || recipient === pageId : recipient !== pageId || sender === pageId) continue;
      // Conservatively pause on EVERY outgoing echo until an audited sender can distinguish its own.
      pending.push({ type: echo ? 'staff' : 'message', timestamp: event.timestamp,
        messageId: event.message.mid, peer: echo ? recipient : sender });
    }
  }
  const key = await hmacKey(identityKey);
  const events = [];
  for (const event of pending) {
    events.push({ type: event.type, timestamp: event.timestamp,
      eventKey: await pseudonym(key, 'event-v1', [pageId, event.messageId]),
      threadKey: await pseudonym(key, 'thread-v1', [pageId, event.peer]) });
  }
  // A staff echo in the same batch wins even when later messages arrived first.
  return events.sort((a, b) => (a.type === 'staff' ? 0 : 1) - (b.type === 'staff' ? 0 : 1)
    || a.timestamp - b.timestamp);
}

async function tokenMatches(expected, supplied) {
  if (!secret(expected, 32) || !secret(supplied, 32)) return false;
  const key = await hmacKey(expected);
  const signature = await crypto.subtle.sign('HMAC', key, encoder.encode(expected));
  return crypto.subtle.verify('HMAC', key, signature, encoder.encode(supplied));
}

export async function handleRequest(request, env = {}, acceptEvents) {
  const url = new URL(request.url);
  if (url.pathname === '/healthz' && request.method === 'GET') {
    return reply(200, 'receive-only staging; sending=false; model=false; production-ready=false');
  }
  if (url.pathname !== '/webhook') return reply(404, 'Not found');
  if (request.method === 'GET') {
    if (env.VERIFY_ENABLED !== 'true') return reply(503, 'Verification disabled');
    const names = ['hub.mode', 'hub.verify_token', 'hub.challenge'];
    if (names.some(name => url.searchParams.getAll(name).length !== 1)
        || url.searchParams.get('hub.mode') !== 'subscribe') return reply(403, 'Forbidden');
    const challenge = url.searchParams.get('hub.challenge');
    if (!/^[A-Za-z0-9_-]{1,128}$/.test(challenge ?? '')
        || !await tokenMatches(env.META_VERIFY_TOKEN, url.searchParams.get('hub.verify_token'))) {
      return reply(403, 'Forbidden');
    }
    return reply(200, challenge);
  }
  if (request.method !== 'POST') return reply(405, 'Method not allowed');
  if (env.RECEIVE_ENABLED !== 'true' || env.DATA_PROCESSING_APPROVED !== 'true'
      || !secret(env.META_APP_SECRET, 16) || !secret(env.IDENTITY_KEY, 32)
      || !id(env.META_PAGE_ID) || typeof acceptEvents !== 'function') {
    return reply(503, 'Receiver disabled');
  }
  if (!/^application\/json(?:\s*;\s*charset=utf-8)?$/i.test(request.headers.get('content-type') ?? '')
      || !['', 'identity'].includes(request.headers.get('content-encoding') ?? '')) {
    return reply(415, 'Unsupported content type');
  }
  let bytes;
  try { bytes = await readBounded(request); }
  catch (error) { return reply(error.message === 'body_timeout' ? 408 : 413, 'Body rejected'); }
  if (!await verifySignature(env.META_APP_SECRET, bytes, request.headers.get('x-hub-signature-256'))) {
    return reply(403, 'Forbidden');
  }
  let events;
  try {
    const body = JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(bytes));
    events = await metadataEvents(body, env.META_PAGE_ID, env.IDENTITY_KEY);
  } catch { return reply(400, 'Payload rejected'); }
  try {
    // ACK only after the durable metadata transaction; no waitUntil-only acknowledgement.
    if (events.length) await acceptEvents(events);
  } catch { return reply(503, 'Temporarily unavailable'); }
  return reply(200, 'EVENT_RECEIVED');
}
