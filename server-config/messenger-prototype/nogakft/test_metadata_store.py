import multiprocessing
import json
from contextlib import closing
from pathlib import Path
import sqlite3
import tempfile
import time
import unittest

from metadata_store import MetadataStore, StoreError, DAY, HOUR, WINDOW

NOW = 100 * DAY + 1000


def k(n):
    return format(n, '064x')


def event(n=1, thread=1, timestamp=NOW, kind='message'):
    return dict(type=kind, eventKey=k(n), threadKey=k(thread), timestamp=timestamp)


def arrival(path, events, now, gate, out):
    gate.wait(30)
    try:
        out.put(MetadataStore(path).accept(events, now))
    except Exception:
        out.put('failed')


class StoreTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.path = Path(self.tmp.name) / 'fixture.sqlite'
        self.store = MetadataStore.create(self.path)

    def accept(self, e=None, now=NOW):
        return self.store.accept([event() if e is None else e], now)[0]

    def enabled(self):
        self.store.set_killed(False)

    def state(self):
        with self.store.transaction() as state:
            return state

    def test_default_killed(self):
        self.assertEqual(self.accept()['reason'], 'kill_switch')

    def test_killed_receipt_dedup_restart(self):
        self.accept()
        self.store = MetadataStore(self.path)
        self.enabled()
        self.assertEqual(self.accept()['reason'], 'duplicate')
        self.assertEqual(self.state()['page'], 0)

    def test_pause_restart_and_prune(self):
        self.enabled()
        self.store.set_paused(k(1), True)
        self.store = MetadataStore(self.path)
        self.assertEqual(self.accept(event(timestamp=NOW + DAY), NOW + DAY)['reason'], 'thread_paused')
        self.assertIn(k(1), self.state()['pauses'])

    def test_explicit_resume(self):
        self.enabled()
        self.store.set_paused(k(1), True)
        self.store.set_paused(k(1), False)
        self.assertEqual(self.accept()['action'], 'eligible')

    def test_stale_boundary(self):
        self.enabled()
        self.assertEqual(self.accept(event(timestamp=NOW-WINDOW))['reason'], 'expired')
        self.assertEqual(self.accept(event(2, timestamp=NOW-WINDOW+1))['action'], 'eligible')

    def test_future_boundary(self):
        self.enabled()
        self.assertEqual(self.accept(event(timestamp=NOW+60_001))['reason'], 'future_timestamp')
        self.assertEqual(self.accept(event(2, timestamp=NOW+60_000))['action'], 'eligible')

    def test_duplicate_and_conflict(self):
        self.enabled()
        self.accept()
        self.assertEqual(self.accept()['reason'], 'duplicate')
        self.assertEqual(self.accept(event(thread=2))['reason'], 'event_conflict')

    def test_receipt_ttl_from_observation(self):
        self.accept()
        self.accept(event(2, timestamp=NOW+DAY-1), NOW+DAY-1)
        self.assertIn(k(1), self.state()['events'])
        self.accept(event(3, timestamp=NOW+DAY), NOW+DAY)
        self.assertNotIn(k(1), self.state()['events'])

    def test_staff_reasserts_duplicate_under_kill(self):
        self.accept(event(kind='staff'))
        self.store.set_paused(k(1), False)
        self.accept(event(kind='staff'))
        self.assertIn(k(1), self.state()['pauses'])

    def test_staff_stale_future_reversed(self):
        self.accept()
        for i, stamp, now in [(2, NOW-WINDOW, NOW), (3, NOW+60_001, NOW),
                              (4, NOW-1, NOW-1)]:
            self.accept(event(i, i, stamp, 'staff'), now)
            self.assertIn(k(i), self.state()['pauses'])

    def test_staff_batch_precedes_message(self):
        self.enabled()
        result = self.store.accept([event(), event(2, kind='staff')], NOW)
        self.assertEqual([x['action'] for x in result], ['paused', 'paused'])
        self.assertEqual(self.state()['page'], 0)

    def test_staff_after_rate_limit(self):
        self.enabled()
        self.store.accept([event(i) for i in range(1, 10)], NOW)
        self.accept(event(10, kind='staff'))
        self.assertIn(k(1), self.state()['pauses'])

    def test_thread_rate_and_hour_boundary(self):
        self.enabled()
        results = self.store.accept([event(i) for i in range(1, 10)], NOW)
        self.assertEqual(sum(r['action'] == 'eligible' for r in results), 8)
        self.assertEqual(results[-1]['reason'], 'thread_rate')
        t = (NOW // HOUR + 1) * HOUR
        self.assertEqual(self.accept(event(10, timestamp=t), t)['action'], 'eligible')

    def test_page_rate(self):
        self.enabled()
        for start in (1, 51):
            self.store.accept([event(i, i) for i in range(start, start+50)], NOW)
        self.assertEqual(self.accept(event(101, 101))['reason'], 'page_rate')

    def test_clock_reversal(self):
        self.accept()
        self.assertEqual(self.accept(event(2), NOW-1)['reason'], 'clock_reversed')
        self.assertEqual(self.state()['clock'], NOW)

    def test_event_capacity_kills_and_staff_pauses(self):
        for start in range(1, 5001, 50):
            self.store.accept([event(i) for i in range(start, start+50)], NOW)
        self.enabled()
        self.assertEqual(self.accept(event(5001, kind='staff'))['reason'], 'event_capacity')
        self.assertTrue(self.state()['killed'])
        self.assertIn(k(1), self.state()['pauses'])
        self.assertEqual(len(self.state()['events']), 5000)

    def test_pause_capacity_kills_without_eviction(self):
        for start in range(1, 1001, 50):
            self.store.accept([event(i, i, kind='staff') for i in range(start, start+50)], NOW)
        self.enabled()
        self.assertEqual(self.accept(event(1001, 1001, kind='staff'))['reason'], 'pause_capacity')
        self.assertFalse(self.store.set_paused(k(1001), True))
        self.assertEqual(len(self.state()['pauses']), 1000)
        self.assertTrue(self.state()['killed'])

    def test_malformed_batch_is_atomic(self):
        bad = [dict(event(), text='fixture'), dict(event(), eventKey='A'*64),
               dict(event(), timestamp=True), dict(event(), timestamp=2**53),
               dict(event(), type='other'), dict(event(), threadKey='0'), None]
        for e in bad:
            with self.assertRaises(StoreError):
                self.store.accept([event(kind='staff'), e], NOW)
        self.assertEqual(self.state(), dict(version=1, killed=True, clock=None,
                         hour=None, page=0, rates={}, pauses={}, events={}))

    def test_invalid_controls_and_batch_bounds(self):
        for events, now in [([event()]*51, NOW), ((), NOW), ([], True), ([], -1)]:
            with self.assertRaises(StoreError):
                self.store.accept(events, now)
        with self.assertRaises(StoreError):
            self.store.set_killed(0)
        with self.assertRaises(StoreError):
            self.store.set_paused(k(1), 1)

    def test_missing_and_existing_refused(self):
        missing = Path(self.tmp.name) / 'missing.sqlite'
        with self.assertRaises(StoreError):
            MetadataStore(missing).accept([], NOW)
        self.assertFalse(missing.exists())
        for path in [self.path, missing]:
            if path == missing:
                path.touch()
            before = path.read_bytes()
            with self.assertRaises(StoreError):
                MetadataStore.create(path)
            self.assertEqual(path.read_bytes(), before)

    def test_corrupt_database_not_reset(self):
        self.path.write_bytes(b'not a sqlite database')
        before = self.path.read_bytes()
        with self.assertRaises(StoreError):
            self.accept()
        self.assertEqual(self.path.read_bytes(), before)

    def test_invalid_state_and_unknown_schema(self):
        with closing(sqlite3.connect(self.path)) as db, db:
            db.execute("UPDATE metadata SET state='{}'")
        before = self.path.read_bytes()
        with self.assertRaises(StoreError):
            self.accept()
        self.assertEqual(self.path.read_bytes(), before)
        other = Path(self.tmp.name) / 'unknown.sqlite'
        with closing(sqlite3.connect(other)) as db, db:
            db.execute('CREATE TABLE unknown (value TEXT)')
        with self.assertRaises(StoreError):
            MetadataStore(other).accept([], NOW)

    def test_schema_no_customer_fields(self):
        self.accept()
        with closing(sqlite3.connect(self.path)) as db, db:
            self.assertEqual([r[1] for r in db.execute('PRAGMA table_info(metadata)')], ['id', 'state'])
        self.assertEqual(set(self.state()['events'][k(1)]), {'type', 'threadKey', 'timestamp', 'seenAt'})

    def test_blob_state_is_corruption_not_accepted_json(self):
        with closing(sqlite3.connect(self.path)) as db, db:
            db.execute('UPDATE metadata SET state=?', (json.dumps(self.state()).encode(),))
        before = self.path.read_bytes()
        with self.assertRaises(StoreError):
            self.accept()
        self.assertEqual(self.path.read_bytes(), before)

    def test_busy_lock_wait_is_bounded(self):
        with closing(sqlite3.connect(self.path, isolation_level=None)) as db:
            db.execute('BEGIN IMMEDIATE')
            started = time.monotonic()
            with self.assertRaises(StoreError):
                self.accept()
            self.assertLess(time.monotonic() - started, 2.5)
            db.execute('ROLLBACK')
        self.assertEqual(self.state()['page'], 0)

    def test_transaction_failure_rolls_back(self):
        before = self.state()
        with self.assertRaises(StoreError):
            with self.store.transaction() as state:
                state['killed'] = False
                state['unapproved_field'] = 'synthetic'
        self.assertEqual(self.state(), before)

    def test_restart_retains_rate_and_clock(self):
        self.enabled()
        self.store.accept([event(i) for i in range(1, 9)], NOW)
        self.store = MetadataStore(self.path)
        self.assertEqual(self.accept(event(9))['reason'], 'thread_rate')
        self.assertEqual(self.accept(event(10), NOW-1)['reason'], 'clock_reversed')

    def test_persisted_extra_field_and_duplicate_json_rejected(self):
        state = self.state()
        state['extra'] = 'synthetic'
        for payload in [json.dumps(state), '{"version":1,"version":1}']:
            with closing(sqlite3.connect(self.path)) as db, db:
                db.execute('UPDATE metadata SET state=?', (payload,))
            before = self.path.read_bytes()
            with self.assertRaises(StoreError):
                self.accept()
            self.assertEqual(self.path.read_bytes(), before)

    def run_processes(self, batches):
        ctx = multiprocessing.get_context('spawn')
        gate, out = ctx.Event(), ctx.Queue()
        workers = [ctx.Process(target=arrival, args=(str(self.path), batch, NOW, gate, out))
                   for batch in batches]
        try:
            for worker in workers:
                worker.start()
            gate.set()
            results = [out.get(timeout=60) for _ in workers]
            for worker in workers:
                worker.join(60)
                self.assertEqual(worker.exitcode, 0)
            self.assertNotIn('failed', results)
            return results
        finally:
            for worker in workers:
                if worker.is_alive():
                    worker.terminate()
                    worker.join()
            out.close()
            out.join_thread()

    def test_twenty_process_duplicates(self):
        self.enabled()
        results = self.run_processes([[event()]] * 20)
        self.assertEqual(sum(r[0]['action'] == 'eligible' for r in results), 1)
        self.assertEqual(sum(r[0]['reason'] == 'duplicate' for r in results), 19)
        self.assertEqual(self.state()['page'], 1)

    def test_concurrent_batches_rate_atomic(self):
        self.enabled()
        results = self.run_processes([[event(i), event(i+100)] for i in range(1, 21)])
        self.assertEqual(sum(r['action'] == 'eligible' for batch in results for r in batch), 8)
        self.assertEqual(len(self.state()['events']), 40)
        self.assertEqual(self.state()['page'], 8)


if __name__ == '__main__':
    unittest.main()
