// Actual local workerd/SQLite transactions. All identities/secrets/events are synthetic.
import test from 'node:test';
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { Miniflare, Log, LogLevel } from 'miniflare';
import { createState, setKillSwitch } from './safety-state.mjs';

const id = n => n.toString(16).padStart(64, '0');
const PAGE = '100000000000001';
const PEER = '200000000000001';
const bindings = { RECEIVE_ENABLED: 'true', DATA_PROCESSING_APPROVED: 'true',
  VERIFY_ENABLED: 'true', META_PAGE_ID: PAGE, META_APP_SECRET: 'synthetic-not-live-app-secret-123456',
  META_VERIFY_TOKEN: 'synthetic-not-live-verify-token-123456',
  IDENTITY_KEY: 'synthetic-not-live-identity-key-123456' };
const options = persist => ({ modules: true, scriptPath: fileURLToPath(new URL('./worker-fixture.mjs', import.meta.url)),
  compatibilityDate: '2026-07-30', cf: false, log: new Log(LogLevel.ERROR), bindings,
  durableObjects: { GUARD: { className: 'TestGuard', useSQLite: true } },
  durableObjectsPersist: persist,
  outboundService: () => { throw new Error('No external requests allowed during synthetic tests'); } });
const post = body => ({ method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify(body) });
const event = (n, extra = {}) => ({ type: 'message', eventKey: id(n), threadKey: id(999), timestamp: Date.now(), ...extra });
const syntheticWire = (messageId, echo = false) => JSON.stringify({ object: 'page', entry: [{ id: PAGE,
  messaging: [{ sender: { id: echo ? PAGE : PEER }, recipient: { id: echo ? PEER : PAGE },
    timestamp: Date.now(), message: { mid: messageId, ...(echo ? { is_echo: true } : {}),
      text: 'Synthetic private text synthetic@example.invalid' } }] }] });
const signed = body => ({ method: 'POST', body, headers: { 'content-type': 'application/json',
  'x-hub-signature-256': 'sha256=' + createHmac('sha256', bindings.META_APP_SECRET).update(body).digest('hex') } });

test('local receive-only Worker, durable safety state and atomic reservations', { timeout: 120000 }, async t => {
  const persist = await mkdtemp(join(tmpdir(), 'harmat-messenger-test-'));
  let mf = new Miniflare(options(persist));
  let stub;
  const bind = async () => {
    const ns = await mf.getDurableObjectNamespace('GUARD');
    stub = ns.get(ns.idFromName('harmat-safety-v1'));
  };
  const seed = async state => {
    assert.equal((await stub.fetch('https://guard.invalid/__test/seed', post(state))).status, 200);
  };
  const state = async () => (await stub.fetch('https://guard.invalid/__test/state')).json();
  const events = async values => {
    const response = await stub.fetch('https://guard.invalid/events', post({ events: values }));
    assert.equal(response.status, 200);
    const result = await response.json();
    assert.equal(result.sendable, false);
    return result.decisions;
  };
  try {
    await bind();
    await t.test('public entry exposes neither test helpers nor private controls', async () => {
      for (const path of ['/__test/seed', '/__test/state', '/reserve', '/events', '/resume', '/send']) {
        assert.equal((await mf.dispatchFetch('https://synthetic.invalid' + path)).status, 404);
      }
      const health = await mf.dispatchFetch('https://synthetic.invalid/healthz');
      assert.match(await health.text(), /production-ready=false/);
    });

    await t.test('fresh persistent state starts stopped; signed ingress stores hashes only', async () => {
      const response = await mf.dispatchFetch('https://synthetic.invalid/webhook', signed(syntheticWire('fresh-mid')));
      assert.equal(response.status, 200);
      const saved = await state();
      assert.equal(saved.killSwitch, true);
      assert.equal(saved.pageCount, 0);
      assert.equal(Object.keys(saved.events).length, 1);
      for (const raw of ['fresh-mid', PEER, PAGE, 'synthetic@example.invalid', bindings.META_APP_SECRET]) {
        assert.ok(!JSON.stringify(saved).includes(raw));
      }
    });

    await t.test('concurrent duplicates consume exactly one eligibility slot', async () => {
      await seed(setKillSwitch(createState(), false));
      const same = event(1);
      const results = (await Promise.all(Array.from({ length: 20 }, () => events([same])))).flat();
      assert.equal(results.filter(r => r.action === 'eligible').length, 1);
      assert.equal(results.filter(r => r.reason === 'duplicate').length, 19);
      const saved = await state();
      assert.equal(saved.pageCount, 1);
      assert.equal(Object.keys(saved.events).length, 1);
      assert.equal(saved.spentMicrousd, 0);
    });

    await t.test('concurrent reservations cannot exceed the internal USD15 model allowance', async () => {
      await seed(setKillSwitch(createState(), false));
      const attempts = await Promise.all(Array.from({ length: 30 }, (_, index) =>
        stub.fetch('https://guard.invalid/reserve', post({ reservationKey: id(index + 1), amountMicrousd: 1_000_000 }))
          .then(async response => { assert.equal(response.status, 200); return response.json(); })));
      assert.equal(attempts.filter(r => r.decisions[0].action === 'reserved').length, 15);
      assert.equal(attempts.filter(r => r.decisions[0].reason === 'monthly_cap').length, 15);
      assert.equal((await state()).spentMicrousd, 15_000_000);
      const saved = await state();
      const reservedKey = Object.keys(saved.reservations)[0];
      const duplicate = await stub.fetch('https://guard.invalid/reserve', post({ reservationKey: reservedKey, amountMicrousd: 1_000_000 }));
      assert.equal((await duplicate.json()).decisions[0].action, 'duplicate');
      assert.equal((await state()).spentMicrousd, 15_000_000);
    });

    await t.test('outgoing echo persists a pause across a real local runtime restart', async () => {
      await seed(setKillSwitch(createState(), false));
      assert.equal((await mf.dispatchFetch('https://synthetic.invalid/webhook', signed(syntheticWire('staff-mid', true)))).status, 200);
      const paused = await state();
      assert.equal(Object.keys(paused.paused).length, 1);
      await mf.dispose();
      mf = new Miniflare(options(persist));
      await bind();
      assert.deepEqual((await state()).paused, paused.paused);
      assert.equal((await mf.dispatchFetch('https://synthetic.invalid/webhook', signed(syntheticWire('after-staff-mid')))).status, 200);
      assert.equal((await state()).pageCount, 0);
    });

    await t.test('malformed stored state fails closed without replacing or resetting it', async () => {
      const damaged = { version: 1, spentMicrousd: 999, corruption: true };
      await seed(damaged);
      const response = await mf.dispatchFetch('https://synthetic.invalid/webhook', signed(syntheticWire('corrupt-mid')));
      assert.equal(response.status, 503);
      assert.deepEqual(await state(), damaged);
    });

    await t.test('maximum combined metadata fixture fits actual SQLite storage; capacity stops globally', async () => {
      const full = setKillSwitch(createState(), false);
      const now = Date.now();
      full.clock = now;
      full.hour = Math.floor(now / 3_600_000);
      full.month = new Date(now).toISOString().slice(0, 7);
      full.spentMicrousd = 5000;
      for (let n = 1; n <= 5000; n++) {
        full.events[id(n)] = { type: 'message', threadKey: id(999), timestamp: now, seenAt: now };
        full.reservations[id(n)] = { month: full.month, amountMicrousd: 1 };
      }
      for (let n = 1; n <= 1000; n++) full.paused[id(n + 10000)] = true;
      createState(full);
      assert.ok(Buffer.byteLength(JSON.stringify(full)) < 2_000_000);
      await seed(full);
      assert.equal(Object.keys((await state()).reservations).length, 5000);
      const result = await events([event(9000)]);
      assert.equal(result[0].reason, 'event_capacity');
      assert.equal((await state()).killSwitch, true);
    });

    await t.test('private reservation input cannot supply its own cap or month', async () => {
      const response = await stub.fetch('https://guard.invalid/reserve', post({ reservationKey: id(7000),
        amountMicrousd: 1, month: '2099-12', capMicrousd: 1_000_000_000 }));
      assert.equal(response.status, 400);
    });
  } finally {
    await mf.dispose();
    const absolute = resolve(persist);
    assert.ok(absolute.startsWith(resolve(tmpdir()) + sep));
    assert.ok(absolute.split(sep).at(-1).startsWith('harmat-messenger-test-'));
    await rm(absolute, { recursive: true, force: true });
  }
});
