import { DurableObject } from 'cloudflare:workers';
import { handleRequest, MAX_EVENTS } from './ingress.mjs';
import { createState, applyEvent, reserveBudget } from './safety-state.mjs';

const STATE_KEY = 'safety-v1';
// Future model allowance only, NOT a total provider invoice/hosting/tax cap.
const MODEL_ALLOWANCE_MICROUSD = 15_000_000;
const json = (status, body) => new Response(JSON.stringify(body), { status,
  headers: { 'content-type': 'application/json', 'cache-control': 'no-store' } });
const exactKeys = (body, names) => body !== null && typeof body === 'object' && !Array.isArray(body)
  && Object.keys(body).length === names.length && names.every(name => Object.hasOwn(body, name));
const hash = value => typeof value === 'string' && /^[a-f0-9]{64}$/.test(value);
const validEvent = value => exactKeys(value, ['type', 'timestamp', 'eventKey', 'threadKey'])
  && ['message', 'staff'].includes(value.type) && Number.isSafeInteger(value.timestamp)
  && value.timestamp >= 0 && hash(value.eventKey) && hash(value.threadKey);

export class HarmatGuard extends DurableObject {
  async fetch(request) {
    const path = new URL(request.url).pathname;
    if (request.method !== 'POST' || !['/events', '/reserve'].includes(path)) {
      return json(404, { error: 'not_found' });
    }
    let body;
    try {
      const text = await request.text();
      if (text.length > 16000) throw new Error('limit');
      body = JSON.parse(text);
    } catch { return json(400, { error: 'invalid_request' }); }
    if (path === '/events' && (!exactKeys(body, ['events']) || !Array.isArray(body.events)
        || body.events.length === 0 || body.events.length > MAX_EVENTS || !body.events.every(validEvent))) {
      return json(400, { error: 'invalid_events' });
    }
    if (path === '/reserve' && (!exactKeys(body, ['reservationKey', 'amountMicrousd'])
        || !hash(body.reservationKey) || !Number.isSafeInteger(body.amountMicrousd) || body.amountMicrousd < 1)) {
      return json(400, { error: 'invalid_reservation' });
    }
    try {
      const decisions = await this.ctx.storage.transaction(async tx => {
        const existing = await tx.get(STATE_KEY);
        let state = existing === undefined ? createState() : createState(existing);
        const now = Date.now();
        const decisions = [];
        if (path === '/events') {
          for (const event of body.events) {
            const reduced = applyEvent(state, event, now);
            state = reduced.state;
            decisions.push(reduced.decision);
          }
        } else {
          const reduced = reserveBudget(state, { ...body,
            month: new Date(now).toISOString().slice(0, 7) }, MODEL_ALLOWANCE_MICROUSD);
          state = reduced.state;
          decisions.push(reduced.decision);
        }
        await tx.put(STATE_KEY, state);
        return decisions;
      });
      return json(200, { decisions, sendable: false });
    } catch { return json(503, { error: 'storage_unavailable' }); }
  }
}

export default {
  async fetch(request, env) {
    return handleRequest(request, env, async events => {
      if (!env.GUARD) throw new Error('missing_binding');
      // One fixed private object serializes page-wide limits/budget across all requests.
      const stub = env.GUARD.get(env.GUARD.idFromName('harmat-safety-v1'));
      const response = await stub.fetch('https://guard.invalid/events', {
        method: 'POST', headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ events }),
      });
      if (!response.ok) throw new Error('storage_unavailable');
    });
  },
};
