# Local Receive-Only Messenger Components

Python standard-library isolated preparation. Not deployed, not integrated
with the existing host or app.py, not a full chatbot, and not a claim of complete
equivalence to the JavaScript transport/reducer. No dependencies, subscription,
HTTP listener, sender, model, private WordPress/CRM API or credentials.
The separate fixed-URL inventory reader now supports the approved anonymous
public GET only; it does not modify or expose the CRM or booking functions.
Budget reservations are deliberately omitted: no USD15 allowance enforcement or
provider invoice cap is supplied by this adapter. Eligibility is not sending or
spending authorization.

## Latest Continuation

Parent independently passed148 local tests:30 metadata,42 ingress,31 public
inventory,37 deterministic reply previews and8 synthetic composition checks.
The latter are NOT actual Meta receive/reply or a production queue/model.
All receive/verification gates remain off, previews always non-sendable.

Private host staging passed31 reader fixtures on Linux/Python3.11.6, but its
actual public GET failed closed. Bounded diagnostics and the authorized network
task confirm a TCP443 timeout, not a proven DNS or API-code issue. No network
settings, existing services or WordPress were modified. A computer can read124
units; that is not host-sync proof. Do not substitute stale/default facts.

Meta remains Unpublished/unconnected with blank callback fields, an outstanding
minimal Page-binding confirmation and missing publication privacy URL. Real
model/data-flow/secure-credential/transport/retention/sender/takeover/live test
and platform-review gates remain. See
`../../maintenance/2026-10-15-facebook-messenger-nogakft-staging.md` for proof,
private backup, limitations and continuation. Do not enable this by Git pull.

## Local API

```python
from metadata_store import MetadataStore

# Explicit operator-selected absolute local fixture path; parent must exist.
store = MetadataStore.create(fixture_path)  # refuses EVERY existing file
store.set_killed(False)  # explicit local control only; default is True
decisions = store.accept(trusted_events, trusted_now_ms)
store.set_paused(trusted_thread_key, True)
# Reopen existing state, never create/reset implicitly:
store = MetadataStore(fixture_path)
```

Paths and clocks must come from trusted host code, never customer input. One DB
represents one Page/safety namespace. Keys must already be scoped pseudonyms
produced by an authenticated ingress (64 lowercase hex, preferably keyed HMAC,
not unsalted raw-ID hashes). This adapter does not authenticate webhook bytes or
generate keys. Supply exactly `type`, `eventKey`, `threadKey` and `timestamp`,
with `type: 'message' | 'staff'`. Timestamp and
now are nonnegative safe-integer UTC epoch milliseconds. Extra fields, bool
timestamps, custom dict types and malformed batches are rejected in full before
any writes. Never supply raw IDs, customer text, URLs or secrets. Pseudonyms are
still sensitive metadata, not a guarantee of anonymization.

Each batch contains at most 50 events. Staff is processed first, then timestamp;
results use that processing order. Staff reasserts a persistent pause even on
duplicate/conflicting, expired, future or clock-reversed events, before kill and
rate checks. Only explicit set_paused(key, False) resumes a thread. Malformed
metadata or unavailable/corrupt storage cannot persist a pause and raises
StoreError; the host must fail closed and must not acknowledge failed writes.

Fresh valid receipts (including killed/rate-denied messages) deduplicate for
24 hours from first observation. Pruning is lazy at event processing, not a
background retention guarantee. Pauses never expire under pruning. Event age
must be strictly less than 23h55m; future skew may be at most 60s. Clock reversal
blocks messages. Only eligible messages use fixed UTC-hour slots: 100 per Page,
8 per thread; hour boundaries permit 100+100 / 8+8 bursts. At most 5000 receipts,
1000 persistent pauses and 100 hourly rate-counter threads. Receipt/pause
exhaustion turns the persistent kill switch on, without eviction; manual
unkilling cannot bypass a still-full bound. Unpaused threads are not separately
retained outside bounded receipts/rates.

All reads, validation, pruning, decisions and writes share one BEGIN IMMEDIATE
transaction per call, with FULL synchronization and a one-second lock timeout.
This bounds lock waiting, not a proved end-to-end Meta acknowledgement deadline.
Successful return follows commit. Concurrent processes serialize through SQLite;
all-or-nothing batch failure rolls back. A single bounded JSON state row stores
only version, kill/clock/hour/counters, pauses and receipt metadata. Every call
checks database integrity, exact schema, TEXT-only state and complete shape/invariants.
Unknown, damaged, empty or missing existing databases are never silently reset.
Creation uses exclusive filesystem creation; interrupted creation can leave an
unusable file requiring explicit operator investigation, never automatic repair.

Use a private local filesystem directory with appropriate ACLs; not a network
share, public web directory or cloud-sync folder. No encryption, backup policy,
multi-host coordination, rollback-resistant external clock, malicious local-file
writer defense, disk-quota guarantee or crash/power-loss certification is claimed.
Do not delete SQLite journal files while a process is running. Storage errors
raise fixed input-free errors; no customer payload logging exists here.

## Tests

From this directory:

```text
python -B -m unittest discover -v
```

Fixtures use local TemporaryDirectory databases only, including spawn-based
20-process duplicate and concurrent-batch checks. Tests do not contact any live
service. Host integration, authenticated ingress, Meta permissions, retention
operations and sending/budget controls remain separate unimplemented gates.

Parent-verified result:30 tests pass, with ResourceWarning treated as errors.
This includes bounded lock refusal and rejecting BLOB state in addition to the
original28 tests. No test database or local server remains running.

## Public Inventory Interface

The separately approved `wp-mu-plugins/zz-harmat-messenger-public-inventory.php`
is now live at `https://harmat22.hu/wp-json/harmat-public/v1/apartments`.
It is a bounded public-only reader of the existing website authority, not a
Messenger sender or CRM writer. Parent passed61 local and production-runtime
isolated checks, private preflight and actual GET-only124-apartment verification.
This Python store still has no HTTP reader or integration with that interface.
See `../../maintenance/2026-10-15-messenger-public-inventory-candidate.md` for
the released hash, backup, recovery and remaining production gates. Do not
reinstall on Git pull or confuse the public feed with enabled Facebook AI.
