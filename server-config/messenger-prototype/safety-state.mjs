// Offline reducer only. No I/O, sender, platform permissions or send authorization.
// Host MUST authenticate events, supply trusted UTC epoch milliseconds/month/cap,
// and read-reduce-write ALL operations within one durable transaction. Parallel
// reductions of the same snapshot are NOT atomic. Reserve before attempted cost;
// retain reservations on ambiguous failures: there are no refunds or resets here.
const HOUR = 3_600_000;
const DAY = 24 * HOUR;
const WINDOW = DAY - 5 * 60_000;
const CAPACITY = 5000;
const MAX_THREADS = 1000;
const DEFAULT_LIMITS = Object.freeze({
  threadPerHour: 8, pagePerHour: 100,
  maxEvents: CAPACITY, maxThreads: MAX_THREADS,
});
const integer = value => Number.isSafeInteger(value) && value >= 0;
const key = value => typeof value === 'string' && value.length === 64
  && /^[0-9a-f]{64}$/.test(value);
const month = value => typeof value === 'string' && value.length === 7
  && /^(?!0000)[0-9]{4}-(0[1-9]|1[0-2])$/.test(value);
const own = (object, name) => Object.hasOwn(object, name);

// Reject accessors, symbols, hidden fields and custom prototypes before reading.
function record(value) {
  if (value === null || typeof value !== 'object'
    || ![Object.prototype, null].includes(Object.getPrototypeOf(value))) return false;
  return Reflect.ownKeys(value).every(name => typeof name === 'string'
    && Object.getOwnPropertyDescriptor(value, name).enumerable
    && own(Object.getOwnPropertyDescriptor(value, name), 'value'));
}

function shape(value, names) {
  return record(value) && Reflect.ownKeys(value).length === names.length
    && names.every(name => own(value, name));
}

function map(value, capacity, valid) {
  return record(value) && Object.keys(value).length <= capacity
    && Object.entries(value).every(([name, entry]) => key(name) && valid(entry));
}

function validState(state) {
  if (!shape(state, ['version', 'killSwitch', 'clock', 'events', 'paused',
    'hour', 'pageCount', 'threads', 'month', 'spentMicrousd', 'reservations'])
    || state.version !== 1 || typeof state.killSwitch !== 'boolean'
    || !(state.clock === null || integer(state.clock))
    || !map(state.paused, MAX_THREADS, value => value === true)
    || !integer(state.pageCount) || state.pageCount > 100
    || !map(state.threads, 100, value => integer(value) && value >= 1 && value <= 8)
    || Object.values(state.threads).reduce((sum, value) => sum + value, 0) !== state.pageCount
    || !integer(state.spentMicrousd)) return false;
  if (state.clock === null ? state.hour !== null || state.pageCount !== 0
    : state.hour !== Math.floor(state.clock / HOUR)) return false;
  if (!map(state.events, CAPACITY, entry => shape(entry,
    ['type', 'threadKey', 'timestamp', 'seenAt'])
    && ['message', 'staff'].includes(entry.type) && key(entry.threadKey)
    && integer(entry.timestamp) && integer(entry.seenAt) && state.clock !== null
    && entry.seenAt <= state.clock && state.clock - entry.seenAt < DAY
    && entry.timestamp - entry.seenAt <= 60_000
    && entry.seenAt - entry.timestamp < WINDOW)) return false;
  if (!(state.month === null || month(state.month))) return false;
  const totals = new Map();
  if (!map(state.reservations, CAPACITY, entry => {
    if (!shape(entry, ['month', 'amountMicrousd']) || !month(entry.month)
      || !integer(entry.amountMicrousd) || entry.amountMicrousd < 1
      || state.month === null || entry.month > state.month) return false;
    const total = (totals.get(entry.month) ?? 0) + entry.amountMicrousd;
    totals.set(entry.month, total);
    return integer(total);
  })) return false;
  return state.month === null ? totals.size === 0 && state.spentMicrousd === 0
    : totals.has(state.month) && totals.get(state.month) === state.spentMicrousd;
}

/** New state starts stopped. Optional persisted snapshot must validate in full;
 * malformed state throws a fixed error, NEVER silently resets safety counters.
 * Results are detached deep clones, JSON-round-trippable and contain no raw IDs.
 */
export function createState(snapshot) {
  if (arguments.length === 0) return {
    version: 1, killSwitch: true, clock: null, events: {}, paused: {},
    hour: null, pageCount: 0, threads: {}, month: null, spentMicrousd: 0,
    reservations: {},
  };
  try {
    if (validState(snapshot)) return structuredClone(snapshot);
  } catch { /* Do not echo arbitrary input or validation errors. */ }
  throw new TypeError('invalid_state');
}

function limitsFor(limits) {
  if (!record(limits) || Object.keys(limits).some(name => !own(DEFAULT_LIMITS, name))) {
    return null;
  }
  const resolved = { ...DEFAULT_LIMITS, ...limits };
  return Object.entries(resolved).every(([name, value]) => integer(value)
    && value >= 1 && value <= DEFAULT_LIMITS[name]) ? resolved : null;
}

function pause(state, threadKey, capacity) {
  if (own(state.paused, threadKey)) return;
  if (Object.keys(state.paused).length >= capacity) state.killSwitch = true;
  else state.paused[threadKey] = true;
}

const result = (state, action, reason) => ({ state, decision: { action, reason } });

// Explicit private/local controls return the detached state directly.
export function setPaused(state, threadKey, paused) {
  const next = createState(state);
  if (!key(threadKey) || typeof paused !== 'boolean') throw new TypeError('invalid_pause');
  if (paused) pause(next, threadKey, MAX_THREADS);
  else delete next.paused[threadKey]; // Only this explicit local operation resumes.
  return next;
}

export function setKillSwitch(state, enabled) {
  const next = createState(state);
  if (typeof enabled !== 'boolean') throw new TypeError('invalid_kill_switch');
  next.killSwitch = enabled;
  return next;
}

/** Event: exactly {type: 'message'|'staff', eventKey, threadKey, timestamp}.
 * Keys (including reservationKey) are exactly 64 lowercase hex characters.
 * now/timestamp are nonnegative safe integer UTC epoch milliseconds. Retention
 * is <24h from first observation; event time must be <23h55m old, <=60s ahead.
 * limits may only tighten DEFAULT_LIMITS, not raise the fixed hard bounds.
 * Defaults: {maxEvents: 5000, maxThreads: 1000, threadPerHour: 8, pagePerHour: 100}.
 * maxThreads bounds persistent pauses; rate-counter threads are bounded at 100.
 * Fixed UTC-hour buckets allow a boundary burst (8+8/thread, 100+100/page).
 * Only eligible messages consume rate slots. All fresh events, including denied
 * messages, are deduplicated. Staff never consumes a rate slot; even stale,
 * duplicated or out-of-order staff events reassert pause before other gates.
 * Pauses never expire/evict; exhausted pause/event storage stops globally.
 * 'eligible' is NOT send authorization and does not reserve money.
 */
export function applyEvent(state, event, now, limits = {}) {
  const next = createState(state);
  if (!shape(event, ['type', 'eventKey', 'threadKey', 'timestamp'])
    || !['message', 'staff'].includes(event.type)
    || !key(event.eventKey) || !key(event.threadKey) || !integer(event.timestamp)) {
    return result(next, 'blocked', 'invalid_event');
  }
  const config = limitsFor(limits);
  const staff = event.type === 'staff';
  if (staff) pause(next, event.threadKey, config?.maxThreads ?? MAX_THREADS);
  if (!config) return result(next, 'blocked', 'invalid_limits');
  if (staff && !own(next.paused, event.threadKey)) return result(next, 'blocked', 'pause_capacity');
  if (!integer(now)) return result(next, 'blocked', 'invalid_timestamp');
  if (next.clock !== null && now < next.clock) {
    return result(next, staff ? 'paused' : 'blocked', 'clock_reversed');
  }
  next.clock = now;
  for (const [id, entry] of Object.entries(next.events)) {
    if (now - entry.seenAt >= DAY) delete next.events[id];
  }
  const hour = Math.floor(now / HOUR);
  if (hour !== next.hour) {
    next.hour = hour;
    next.pageCount = 0;
    next.threads = {};
  }
  if (event.timestamp - now > 60_000) return result(next, 'blocked', 'future_timestamp');
  if (now - event.timestamp >= WINDOW) {
    return result(next, staff ? 'paused' : 'ignore', staff ? 'staff_takeover' : 'expired');
  }
  if (own(next.events, event.eventKey)) {
    if (staff) return result(next, 'paused', 'staff_takeover');
    const previous = next.events[event.eventKey];
    const same = previous.type === event.type && previous.threadKey === event.threadKey
      && previous.timestamp === event.timestamp;
    return result(next, same ? 'ignore' : 'blocked', same ? 'duplicate' : 'event_conflict');
  }
  if (Object.keys(next.events).length >= config.maxEvents) {
    next.killSwitch = true;
    return result(next, staff ? 'paused' : 'blocked', 'event_capacity');
  }
  next.events[event.eventKey] = {
    type: event.type, threadKey: event.threadKey, timestamp: event.timestamp, seenAt: now,
  };
  if (staff) return result(next, 'paused', 'staff_takeover');
  if (next.killSwitch) return result(next, 'blocked', 'kill_switch');
  if (own(next.paused, event.threadKey)) return result(next, 'paused', 'thread_paused');
  const count = own(next.threads, event.threadKey) ? next.threads[event.threadKey] : 0;
  if (count >= config.threadPerHour) return result(next, 'blocked', 'thread_rate');
  if (next.pageCount >= config.pagePerHour) return result(next, 'blocked', 'page_rate');
  next.pageCount += 1;
  next.threads[event.threadKey] = count + 1;
  return result(next, 'eligible', 'local_checks_passed');
}

/** Exactly {reservationKey, month: 'YYYY-MM' (0001..9999), amountMicrousd}.
 * Amount is a positive safe integer; explicit cap is a nonnegative safe integer.
 * The month high-water mark advances only on a successful reservation. Old
 * months cannot reopen; exact old duplicates remain duplicates. All <=5000
 * reservations persist globally, with no automatic deletion or refund.
 */
export function reserveBudget(state, reservation, capMicrousd) {
  const next = createState(state);
  if (!shape(reservation, ['reservationKey', 'month', 'amountMicrousd'])
    || !key(reservation.reservationKey) || !month(reservation.month)
    || !integer(reservation.amountMicrousd) || reservation.amountMicrousd < 1
    || !integer(capMicrousd)) return result(next, 'blocked', 'invalid_reservation');
  const { reservationKey, month: requestedMonth, amountMicrousd } = reservation;
  if (own(next.reservations, reservationKey)) {
    const previous = next.reservations[reservationKey];
    const same = previous.month === requestedMonth && previous.amountMicrousd === amountMicrousd;
    return result(next, same ? 'duplicate' : 'blocked', same ? 'duplicate' : 'reservation_conflict');
  }
  if (next.killSwitch) return result(next, 'blocked', 'kill_switch');
  if (next.month !== null && requestedMonth < next.month) return result(next, 'blocked', 'month_reversed');
  if (Object.keys(next.reservations).length >= CAPACITY) return result(next, 'blocked', 'reservation_capacity');
  const spent = requestedMonth === next.month ? next.spentMicrousd : 0;
  // Subtraction avoids overflowing before comparing; no failed operation resets a month.
  if (spent > capMicrousd || amountMicrousd > capMicrousd - spent) {
    return result(next, 'blocked', 'monthly_cap');
  }
  next.month = requestedMonth;
  next.spentMicrousd = spent + amountMicrousd;
  next.reservations[reservationKey] = { month: requestedMonth, amountMicrousd };
  return result(next, 'reserved', 'budget_reserved');
}
