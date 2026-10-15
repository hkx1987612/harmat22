// Synthetic offline checks only, not evidence of real Meta staff takeover/delivery.
// node --test server-config/messenger-prototype/safety-state.test.mjs
import test from 'node:test';
import assert from 'node:assert/strict';
import {
  createState, applyEvent, setPaused, setKillSwitch, reserveBudget,
} from './safety-state.mjs';

const HOUR = 3_600_000;
const DAY = 24 * HOUR;
const WINDOW = DAY - 300_000;
const NOW = Date.UTC(2026, 9, 15, 12, 30);
const id = number => number.toString(16).padStart(64, '0');
const THREAD = id(99_999);
const event = (number = 1, extra = {}) => ({
  type: 'message', eventKey: id(number), threadKey: THREAD, timestamp: NOW, ...extra,
});
const request = (number = 1, amountMicrousd = 1, month = '2026-10') => ({
  reservationKey: id(number), month, amountMicrousd,
});
const running = () => setKillSwitch(createState(), false);
const decide = (state, input, now = NOW, limits = {}) => applyEvent(state, input, now, limits);
const budget = (state, input, cap = 20_000_000) => reserveBudget(state, input, cap);
const reason = (output, action, expectedReason) => {
  assert.deepEqual(output.decision, { action, reason: expectedReason });
  assert.deepEqual(Object.keys(output).sort(), ['decision', 'state']);
  createState(output.state);
  return output.state;
};

function freeze(value) {
  if (value && typeof value === 'object') {
    Object.values(value).forEach(freeze);
    Object.freeze(value);
  }
  return value;
}

function filledEvents(count = 5000) {
  const state = running();
  state.clock = NOW;
  state.hour = Math.floor(NOW / HOUR);
  for (let number = 1; number <= count; number += 1) {
    state.events[id(number)] = {
      type: 'message', threadKey: THREAD, timestamp: NOW, seenAt: NOW,
    };
  }
  return createState(state);
}

function filledReservations() {
  const state = running();
  state.month = '2026-10';
  state.spentMicrousd = 5000;
  for (let number = 1; number <= 5000; number += 1) {
    state.reservations[id(number)] = { month: '2026-10', amountMicrousd: 1 };
  }
  return createState(state);
}

test('new state is stopped, detached, and cannot reserve or be eligible', () => {
  const state = createState();
  assert.equal(state.killSwitch, true);
  reason(decide(state, event()), 'blocked', 'kill_switch');
  reason(budget(state, request()), 'blocked', 'kill_switch');
  assert.deepEqual(state, createState());
  state.paused[THREAD] = true;
  assert.deepEqual(createState().paused, {});
});

test('intentional local enable permits eligibility only, without budget reservation', () => {
  const state = reason(decide(running(), event()), 'eligible', 'local_checks_passed');
  assert.equal(state.pageCount, 1);
  assert.equal(state.threads[THREAD], 1);
  assert.equal(state.spentMicrousd, 0);
  assert.deepEqual(Object.keys(state.events[id(1)]).sort(), ['seenAt', 'threadKey', 'timestamp', 'type']);
  assert.equal(state.events[id(1)].seenAt, NOW);
});

test('exact duplicate has no extra rate slot, receipt refresh, or cost', () => {
  const first = decide(running(), event()).state;
  const second = reason(decide(first, event(), NOW + 1000), 'ignore', 'duplicate');
  assert.equal(second.pageCount, 1);
  assert.equal(second.events[id(1)].seenAt, NOW);
  assert.equal(second.spentMicrousd, 0);
});

test('message event-key payload conflicts are rejected', () => {
  const state = decide(running(), event()).state;
  for (const extra of [{ threadKey: id(4) }, { timestamp: NOW - 1 }]) {
    reason(decide(state, event(1, extra)), 'blocked', 'event_conflict');
  }
});

test('thread limit is eight per fixed UTC hour and denied events are deduplicated', () => {
  let state = running();
  for (let number = 1; number <= 8; number += 1) {
    state = reason(decide(state, event(number)), 'eligible', 'local_checks_passed');
  }
  state = reason(decide(state, event(9)), 'blocked', 'thread_rate');
  assert.equal(state.pageCount, 8);
  reason(decide(state, event(9)), 'ignore', 'duplicate');
});

test('page limit is 100, even with distinct pseudonymous threads', () => {
  let state = running();
  for (let number = 1; number <= 100; number += 1) {
    state = reason(decide(state, event(number, { threadKey: id(number) })), 'eligible', 'local_checks_passed');
  }
  state = reason(decide(state, event(101, { threadKey: id(101) })), 'blocked', 'page_rate');
  assert.equal(Object.keys(state.threads).length, 100);
  assert.equal(state.pageCount, 100);
});

test('fixed bucket resets at UTC-hour boundary, not at client event timestamp', () => {
  let state = running();
  const boundary = Math.floor(NOW / HOUR) * HOUR + HOUR;
  for (let number = 1; number <= 8; number += 1) {
    state = decide(state, event(number, { timestamp: boundary - 1 }), boundary - 1).state;
  }
  state = reason(decide(state, event(9, { timestamp: boundary - 1 }), boundary), 'eligible', 'local_checks_passed');
  assert.equal(state.pageCount, 1);
  reason(decide(state, event(1, { timestamp: boundary - 1 }), boundary), 'ignore', 'duplicate');
});

test('limits accept only bounded optional maxEvents/maxThreads/threadPerHour/pagePerHour', () => {
  const limits = { maxEvents: 5, maxThreads: 2, threadPerHour: 1, pagePerHour: 2 };
  const first = decide(running(), event(), NOW, limits).state;
  reason(decide(first, event(2), NOW, limits), 'blocked', 'thread_rate');
  for (const bad of [null, [], { maxEvents: 5001 }, { maxThreads: 1001 },
    { threadPerHour: 9 }, { pagePerHour: 101 }, { maxEvents: 0 },
    { maxThreads: 1.5 }, { pagePerHour: '100' }, { eventCapacity: 5 },
    { maxEvents: Infinity }, { toString: 1 }]) {
    reason(decide(running(), event(), NOW, bad), 'blocked', 'invalid_limits');
  }
});

test('message timestamps use exact age and future boundaries', () => {
  reason(decide(running(), event(1, { timestamp: NOW - WINDOW + 1 })), 'eligible', 'local_checks_passed');
  reason(decide(running(), event(1, { timestamp: NOW - WINDOW })), 'ignore', 'expired');
  reason(decide(running(), event(1, { timestamp: NOW - DAY })), 'ignore', 'expired');
  reason(decide(running(), event(1, { timestamp: NOW + 60_000 })), 'eligible', 'local_checks_passed');
  reason(decide(running(), event(1, { timestamp: NOW + 60_001 })), 'blocked', 'future_timestamp');
});

test('finite safe nonnegative integer timestamps and trusted now are mandatory', () => {
  for (const bad of [NaN, Infinity, -Infinity, -1, 0.1, '123', null, undefined,
    Number.MAX_SAFE_INTEGER + 1]) {
    reason(decide(running(), event(1, { timestamp: bad })), 'blocked', 'invalid_event');
    reason(applyEvent(running(), event(), bad), 'blocked', 'invalid_timestamp');
  }
  reason(decide(running(), event(1, { timestamp: 0 }), 0), 'eligible', 'local_checks_passed');
  const max = Number.MAX_SAFE_INTEGER;
  reason(decide(running(), event(1, { timestamp: max }), max), 'eligible', 'local_checks_passed');
});

test('clock rollback cannot reopen rate counters or prune event records', () => {
  const state = decide(running(), event()).state;
  const output = decide(state, event(2), NOW - 1);
  reason(output, 'blocked', 'clock_reversed');
  assert.deepEqual(output.state, state);
});

test('out-of-order message timestamps are allowed but charged to receipt hour', () => {
  const state = decide(running(), event()).state;
  const next = reason(decide(state, event(2, { timestamp: NOW - HOUR })), 'eligible', 'local_checks_passed');
  assert.equal(next.pageCount, 2);
  assert.equal(next.events[id(2)].seenAt, NOW);
});

test('dedup TTL is receipt time, retains just below 24h, and prunes exactly at 24h', () => {
  const input = event(1, { timestamp: NOW - WINDOW + 1 });
  const state = decide(running(), input).state;
  const near = decide(state, event(2, { timestamp: NOW + DAY - 1 }), NOW + DAY - 1).state;
  assert.ok(Object.hasOwn(near.events, id(1)));
  assert.equal(near.events[id(1)].seenAt, NOW);
  const end = decide(near, event(3, { timestamp: NOW + DAY }), NOW + DAY).state;
  assert.ok(!Object.hasOwn(end.events, id(1)));
  assert.equal(decide(end, input, NOW + DAY).decision.reason, 'expired');
});

test('5000 event hard bound fails closed without evicting fresh records', () => {
  const state = filledEvents();
  const next = reason(decide(state, event(5001)), 'blocked', 'event_capacity');
  assert.equal(next.killSwitch, true);
  assert.equal(Object.keys(next.events).length, 5000);
  reason(decide(state, event()), 'ignore', 'duplicate');
});

test('tightened event capacity is enforced and old records are pruned before capacity', () => {
  const first = decide(running(), event(), NOW, { maxEvents: 1 }).state;
  reason(decide(first, event(2), NOW, { maxEvents: 1 }), 'blocked', 'event_capacity');
  const later = reason(decide(first, event(2, { timestamp: NOW + DAY }), NOW + DAY,
    { maxEvents: 1 }), 'eligible', 'local_checks_passed');
  assert.equal(Object.keys(later.events).length, 1);
});

test('staff pauses even while killed and never consumes rate or budget', () => {
  const next = reason(decide(createState(), event(1, { type: 'staff' })), 'paused', 'staff_takeover');
  assert.equal(next.killSwitch, true);
  assert.equal(next.paused[THREAD], true);
  assert.equal(next.pageCount, 0);
  assert.equal(next.spentMicrousd, 0);
});

test('staff overrides ordinary-message dedup conflict and rate exhaustion', () => {
  let state = running();
  for (let number = 1; number <= 8; number += 1) state = decide(state, event(number)).state;
  state = reason(decide(state, event(1, { type: 'staff' })), 'paused', 'staff_takeover');
  assert.equal(state.paused[THREAD], true);
  assert.equal(state.pageCount, 8);
  state = reason(decide(state, event(9)), 'paused', 'thread_paused');
  assert.equal(state.pageCount, 8);
});

test('late and out-of-order staff set pause regardless of message-window expiry', () => {
  const base = decide(running(), event()).state;
  for (const timestamp of [NOW - 1, NOW - WINDOW, NOW - DAY]) {
    const next = reason(decide(base, event(2, { type: 'staff', timestamp })), 'paused', 'staff_takeover');
    assert.equal(next.paused[THREAD], true);
  }
  const next = reason(decide(base, event(2, { type: 'staff' }), NOW - 1), 'paused', 'clock_reversed');
  assert.equal(next.paused[THREAD], true);
});

test('future staff rejects without clearing an existing pause; malformed staff does not pause', () => {
  const base = setPaused(running(), THREAD, true);
  const next = reason(decide(base, event(2, { type: 'staff', timestamp: NOW + 60_001 })), 'blocked', 'future_timestamp');
  assert.equal(next.paused[THREAD], true);
  const malformed = decide(running(), event(2, { type: 'staff', timestamp: NaN }));
  reason(malformed, 'blocked', 'invalid_event');
  assert.deepEqual(malformed.state.paused, {});
});

test('duplicate staff reasserts a deliberately cleared pause with no extra costs', () => {
  const input = event(1, { type: 'staff' });
  let state = decide(running(), input).state;
  state = setPaused(state, THREAD, false);
  state = reason(decide(state, input), 'paused', 'staff_takeover');
  assert.equal(state.paused[THREAD], true);
  assert.equal(Object.keys(state.events).length, 1);
  assert.equal(state.pageCount, 0);
});

test('pause survives event expiry, rate reset, and kill toggles until explicit resume', () => {
  let state = decide(running(), event(1, { type: 'staff' })).state;
  state = setKillSwitch(setKillSwitch(state, true), false);
  state = reason(decide(state, event(2, { timestamp: NOW + 2 * DAY }), NOW + 2 * DAY), 'paused', 'thread_paused');
  assert.equal(Object.keys(state.events).length, 1);
  assert.equal(state.paused[THREAD], true);
  state = setPaused(state, THREAD, false);
  reason(decide(state, event(3, { timestamp: NOW + 2 * DAY }), NOW + 2 * DAY), 'eligible', 'local_checks_passed');
});

test('pause hard capacity 1000 stops globally and never automatically evicts', () => {
  const state = running();
  for (let number = 1; number <= 1000; number += 1) state.paused[id(number)] = true;
  const next = setPaused(state, THREAD, true);
  assert.equal(next.killSwitch, true);
  assert.deepEqual(next.paused, state.paused);
  const output = decide(state, event(1, { type: 'staff' }));
  reason(output, 'blocked', 'pause_capacity');
  assert.equal(output.state.killSwitch, true);
  assert.deepEqual(output.state.paused, state.paused);
});

test('tightened pause bound and full dedup storage still enforce staff stop', () => {
  const first = decide(running(), event(1, { type: 'staff' }), NOW, { maxThreads: 1 }).state;
  const full = decide(first, event(2, { type: 'staff', threadKey: id(8) }), NOW, { maxThreads: 1 });
  reason(full, 'blocked', 'pause_capacity');
  assert.equal(full.state.killSwitch, true);
  assert.deepEqual(full.state.paused, { [THREAD]: true });
  const eventFull = decide(filledEvents(), event(5001, { type: 'staff' }));
  reason(eventFull, 'paused', 'event_capacity');
  assert.equal(eventFull.state.killSwitch, true);
  assert.equal(eventFull.state.paused[THREAD], true);
});

test('paused and killed messages cannot consume rate slots; denied events stay deduplicated', () => {
  const paused = reason(decide(setPaused(running(), THREAD, true), event()), 'paused', 'thread_paused');
  assert.equal(paused.pageCount, 0);
  reason(decide(setPaused(paused, THREAD, false), event()), 'ignore', 'duplicate');
  const killed = decide(createState(), event()).state;
  reason(decide(setKillSwitch(killed, false), event()), 'ignore', 'duplicate');
});

test('explicit setters validate keys/booleans and are immutable', () => {
  const state = freeze(running());
  const paused = setPaused(state, THREAD, true);
  assert.deepEqual(state.paused, {});
  assert.equal(paused.paused[THREAD], true);
  for (const bad of [0, 1, null, 'false', undefined]) {
    assert.throws(() => setPaused(state, THREAD, bad), /^TypeError: invalid_pause$/);
    assert.throws(() => setKillSwitch(state, bad), /^TypeError: invalid_kill_switch$/);
  }
  assert.throws(() => setPaused(state, '__proto__', true), /^TypeError: invalid_pause$/);
});

test('all pseudonymous key fields reject raw IDs, uppercase, wrong length and newlines', () => {
  for (const bad of ['raw-customer-id', '__proto__', 'constructor', 'A'.repeat(64),
    'f'.repeat(63), 'f'.repeat(65), `${id(1)}\n`, null, 123]) {
    for (const name of ['threadKey', 'eventKey']) {
      reason(decide(running(), event(1, { [name]: bad })), 'blocked', 'invalid_event');
    }
    reason(budget(running(), { ...request(), reservationKey: bad }), 'blocked', 'invalid_reservation');
  }
});

test('wrong shapes and unrecognized fields never echo or retain arbitrary incoming information', () => {
  const raw = 'PRIVATE_SYNTHETIC_TEXT_OR_ID';
  for (const bad of [null, [], new Map(), {}, { ...event(), type: 'echo' },
    { ...event(), text: raw }, { ...event(), senderId: raw }]) {
    const output = decide(running(), bad);
    reason(output, 'blocked', 'invalid_event');
    assert.ok(!JSON.stringify(output).includes(raw));
    assert.deepEqual(output.state, running());
  }
  reason(budget(running(), { ...request(), raw }), 'blocked', 'invalid_reservation');
});

test('accessors, hidden properties, symbols and prototype pollution are rejected without reading getters', () => {
  let reads = 0;
  const getterEvent = { ...event() };
  Object.defineProperty(getterEvent, 'timestamp', { enumerable: true, get() { reads += 1; return NOW; } });
  const symbolEvent = { ...event(), [Symbol('hidden')]: true };
  const hiddenEvent = { ...event() };
  Object.defineProperty(hiddenEvent, 'hidden', { value: 'raw' });
  for (const bad of [getterEvent, symbolEvent, hiddenEvent, Object.assign(Object.create({ polluted: true }), event())]) {
    reason(decide(running(), bad), 'blocked', 'invalid_event');
  }
  assert.equal(reads, 0);
  for (const badMap of [JSON.parse('{"__proto__":true}'), { constructor: true },
    Object.create({ [THREAD]: true }), new Map([[THREAD, true]])]) {
    const state = { ...running(), paused: badMap };
    assert.throws(() => createState(state), /^TypeError: invalid_state$/);
  }
  assert.equal({}.polluted, undefined);
});

test('malformed saved state is rejected, never silently reset by any exported operation', () => {
  const badStates = [undefined, null, [], {}, { ...running(), version: 2 },
    { ...running(), killSwitch: 0 }, { ...running(), raw: 'PRIVATE' },
    { ...running(), pageCount: 1 }, { ...running(), clock: NaN },
    { ...running(), hour: 1 }, { ...running(), paused: { [THREAD]: false } },
    { ...running(), reservations: { [id(1)]: { month: '2026-10', amountMicrousd: 1 } } }];
  for (const state of badStates) {
    for (const operation of [() => createState(state), () => decide(state, event()),
      () => setPaused(state, THREAD, true), () => setKillSwitch(state, false),
      () => budget(state, request())]) assert.throws(operation, /^TypeError: invalid_state$/);
  }
});

test('saved state validates record time, capacity, rate totals and budget ledger invariants', () => {
  const base = decide(running(), event()).state;
  const budgeted = budget(base, request(1, 5)).state;
  const changes = [
    state => { state.events[id(1)].seenAt = NOW + 1; },
    state => { state.events[id(1)].timestamp = NOW + 60_001; },
    state => { state.events[id(1)].timestamp = NOW - WINDOW; },
    state => { state.events[id(1)].raw = 'forbidden'; },
    state => { state.clock += DAY; state.hour = Math.floor(state.clock / HOUR); },
    state => { state.threads[THREAD] = 9; state.pageCount = 9; },
    state => { state.pageCount = 101; },
    state => { state.spentMicrousd = 4; },
    state => { state.month = '2026-09'; },
    state => { state.month = '2026-11'; },
    state => { state.reservations[id(1)].amountMicrousd = Number.MAX_SAFE_INTEGER + 1; },
    state => { for (let n = 1; n <= 1001; n += 1) state.paused[id(n)] = true; },
  ];
  for (const change of changes) {
    const state = structuredClone(budgeted);
    change(state);
    assert.throws(() => createState(state), /^TypeError: invalid_state$/);
  }
  const overflow = filledReservations();
  overflow.reservations[id(1)].amountMicrousd = Number.MAX_SAFE_INTEGER;
  overflow.spentMicrousd = Number.MAX_SAFE_INTEGER;
  assert.throws(() => createState(overflow), /^TypeError: invalid_state$/);
});

test('JSON persistence round-trip and null-prototype maps validate and clone deeply', () => {
  let state = budget(decide(running(), event()).state, request()).state;
  state.paused = Object.assign(Object.create(null), { [THREAD]: true });
  const clone = createState(state);
  clone.events[id(1)].timestamp -= 1;
  clone.reservations[id(1)].amountMicrousd += 1;
  clone.paused[THREAD] = false;
  assert.equal(state.events[id(1)].timestamp, NOW);
  assert.equal(state.reservations[id(1)].amountMicrousd, 1);
  assert.equal(state.paused[THREAD], true);
  assert.deepEqual(createState(JSON.parse(JSON.stringify(state))), createState(state));
});

test('budget reservations reach but cannot exceed explicit monthly integer cap', () => {
  let state = reason(budget(running(), request(1, 6), 10), 'reserved', 'budget_reserved');
  state = reason(budget(state, request(2, 4), 10), 'reserved', 'budget_reserved');
  const failed = budget(state, request(3, 1), 10);
  reason(failed, 'blocked', 'monthly_cap');
  assert.deepEqual(failed.state, state);
  assert.equal(state.spentMicrousd, 10);
  assert.equal(Object.keys(state.reservations).length, 2);
});

test('budget duplicate does not double count; amount/month conflicts are blocked', () => {
  const state = budget(running(), request(1, 10), 10).state;
  assert.deepEqual(reason(budget(state, request(1, 10), 10), 'duplicate', 'duplicate'), state);
  reason(budget(state, request(1, 9), 10), 'blocked', 'reservation_conflict');
  reason(budget(state, request(1, 10, '2026-11'), 10), 'blocked', 'reservation_conflict');
  reason(budget(setKillSwitch(state, true), request(1, 10), 0), 'duplicate', 'duplicate');
});

test('month advances on success only and reversing cannot reopen spent counters', () => {
  let state = budget(running(), request(1, 10), 10).state;
  const failed = budget(state, request(2, 11, '2026-11'), 10);
  reason(failed, 'blocked', 'monthly_cap');
  assert.deepEqual(failed.state, state);
  state = reason(budget(state, request(2, 3, '2026-11'), 10), 'reserved', 'budget_reserved');
  assert.equal(state.spentMicrousd, 3);
  reason(budget(state, request(3, 1, '2026-10'), 10), 'blocked', 'month_reversed');
  assert.deepEqual(reason(budget(state, request(1, 10), 10), 'duplicate', 'duplicate'), state);
  assert.equal(Object.keys(state.reservations).length, 2);
});

test('month format is strict YYYY-MM with a real month and four-digit positive year', () => {
  for (const bad of ['2026-1', '2026-00', '2026-13', '0000-01', '026-01', '10000-01',
    '2026-10\n', ' 2026-10', '2026-10-01', 202610, null]) {
    reason(budget(running(), request(1, 1, bad)), 'blocked', 'invalid_reservation');
  }
  for (const good of ['0001-01', '2026-09', '9999-12']) {
    reason(budget(running(), request(1, 1, good)), 'reserved', 'budget_reserved');
  }
});

test('invalid amounts, absent/fractional/unsafe caps and zero-cap fail closed', () => {
  for (const bad of [0, -1, 0.5, NaN, Infinity, Number.MAX_SAFE_INTEGER + 1, '1', null]) {
    reason(budget(running(), request(1, bad)), 'blocked', 'invalid_reservation');
  }
  for (const bad of [-1, 0.5, NaN, Infinity, Number.MAX_SAFE_INTEGER + 1, '20', null]) {
    reason(budget(running(), request(), bad), 'blocked', 'invalid_reservation');
  }
  reason(reserveBudget(running(), request()), 'blocked', 'invalid_reservation');
  reason(budget(running(), request(), 0), 'blocked', 'monthly_cap');
});

test('safe integer maximum cannot overflow budget and lower cap does not refund', () => {
  const max = Number.MAX_SAFE_INTEGER;
  const state = reason(budget(running(), request(1, max), max), 'reserved', 'budget_reserved');
  assert.equal(state.spentMicrousd, max);
  assert.deepEqual(reason(budget(state, request(2), max), 'blocked', 'monthly_cap'), state);
  assert.deepEqual(reason(budget(state, request(2), 1), 'blocked', 'monthly_cap'), state);
});

test('5000 reservations persist globally across months; full ledger denies new but permits duplicate', () => {
  const state = filledReservations();
  for (const requestedMonth of ['2026-10', '2026-11']) {
    assert.deepEqual(reason(budget(state, request(5001, 1, requestedMonth)), 'blocked', 'reservation_capacity'), state);
  }
  reason(budget(state, request()), 'duplicate', 'duplicate');
  reason(budget(state, request(1, 2)), 'blocked', 'reservation_conflict');
  assert.equal(Object.keys(state.reservations).length, 5000);
});

test('serial transaction simulation prevents concurrent-looking reservations overspending', () => {
  let state = running();
  const queue = Array.from({ length: 40 }, (_, index) => request(index + 1, 3));
  const actions = [];
  for (const input of queue) {
    // Model the host transaction rereading the latest committed state each time.
    const output = budget(state, input, 20);
    state = output.state;
    actions.push(output.decision.action);
  }
  assert.equal(actions.filter(action => action === 'reserved').length, 6);
  assert.equal(actions.filter(action => action === 'blocked').length, 34);
  assert.equal(state.spentMicrousd, 18);
  reason(budget(state, queue[0], 20), 'duplicate', 'duplicate');
  assert.equal(Object.keys(state.reservations).length, 6);
});

test('same-snapshot reductions are NOT concurrency protection; host transaction is required', () => {
  const snapshot = running();
  const left = budget(snapshot, request(1, 7), 10);
  const right = budget(snapshot, request(2, 7), 10);
  assert.equal(left.decision.action, 'reserved');
  assert.equal(right.decision.action, 'reserved');
  reason(budget(left.state, request(2, 7), 10), 'blocked', 'monthly_cap');
  assert.deepEqual(snapshot, running());
});

test('all operations deep-clone inputs and outputs, including blocked/duplicate branches', () => {
  const state = freeze(budget(decide(running(), event()).state, request()).state);
  const before = JSON.stringify(state);
  const input = freeze(event(2));
  const limits = freeze({ maxEvents: 5000, maxThreads: 1000, threadPerHour: 8, pagePerHour: 100 });
  const outputs = [createState(state), setPaused(state, THREAD, true), setKillSwitch(state, true),
    decide(state, input, NOW, limits).state, decide(state, event()).state,
    decide(state, { ...event(), raw: 'reject' }).state,
    budget(state, freeze(request())).state, budget(state, request(2, 2)).state,
    budget(state, request(3, 2), 0).state];
  for (const output of outputs) {
    assert.notEqual(output, state);
    assert.notEqual(output.events, state.events);
    assert.notEqual(output.events[id(1)], state.events[id(1)]);
    assert.notEqual(output.reservations[id(1)], state.reservations[id(1)]);
    output.events[id(1)].timestamp -= 1;
    output.reservations[id(1)].amountMicrousd += 1;
  }
  assert.equal(JSON.stringify(state), before);
  assert.equal(input.timestamp, NOW);
});

test('bounded raw-free state contains only pseudonyms and fixed scalar metadata', () => {
  let state = running();
  for (let number = 1; number <= 150; number += 1) {
    state = decide(state, event(number, { threadKey: id(number) })).state;
    state = budget(state, request(number, 1), 1000).state;
  }
  assert.ok(Object.keys(state.events).length <= 5000);
  assert.ok(Object.keys(state.paused).length <= 1000);
  assert.ok(Object.keys(state.threads).length <= 100);
  assert.ok(Object.keys(state.reservations).length <= 5000);
  const encoded = JSON.stringify(state);
  assert.ok(encoded.length < 100_000);
  assert.ok(!/senderId|recipientId|text|messageBody|accessToken/.test(encoded));
  assert.deepEqual(createState(JSON.parse(encoded)), state);
});

test('combined full-capacity snapshot is bounded; adapter must handle its serialized size', context => {
  const state = filledEvents();
  state.month = '9999-12';
  state.spentMicrousd = 5_000_000_000_000_000;
  for (let number = 1; number <= 5000; number += 1) {
    state.reservations[id(number)] = { month: state.month, amountMicrousd: 1_000_000_000_000 };
    if (number <= 1000) state.paused[id(number)] = true;
    if (number <= 100) state.threads[id(number)] = 1;
  }
  state.pageCount = 100;
  const clone = createState(state);
  const bytes = Buffer.byteLength(JSON.stringify(clone));
  assert.ok(bytes < 2_000_000);
  context.diagnostic(`Combined-capacity synthetic snapshot: ${bytes} bytes (not an adapter storage test).`);
  for (const name of ['events', 'reservations']) {
    const overfull = structuredClone(clone);
    overfull[name][id(5001)] = structuredClone(overfull[name][id(1)]);
    assert.throws(() => createState(overfull), /^TypeError: invalid_state$/);
  }
});
