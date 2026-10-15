"""Local trusted-metadata store. Eligibility is never permission to send."""
import json
import os
from pathlib import Path
import re
import sqlite3
from contextlib import closing, contextmanager

HOUR = 3_600_000
DAY = 24 * HOUR
WINDOW = DAY - 300_000
MAX_INT = 2**53 - 1
KEY = re.compile(r"[0-9a-f]{64}", re.ASCII)
SCHEMA = "CREATE TABLE metadata (id INTEGER PRIMARY KEY CHECK(id=1), state TEXT NOT NULL)"


class StoreError(RuntimeError):
    """Fixed, input-free failure; caller must not acknowledge failed persistence."""


def integer(value):
    return type(value) is int and 0 <= value <= MAX_INT


def key(value):
    return type(value) is str and KEY.fullmatch(value) is not None


def shape(value, fields):
    return type(value) is dict and set(value) == set(fields)


def fresh():
    return dict(version=1, killed=True, clock=None, hour=None, page=0,
                rates={}, pauses={}, events={})


def valid(s):
    if not shape(s, fresh()) or type(s['version']) is not int or s['version'] != 1:
        return False
    if type(s['killed']) is not bool or not integer(s['page']) or s['page'] > 100:
        return False
    if s['clock'] is None:
        if s['hour'] is not None or s['page'] != 0:
            return False
    elif not integer(s['clock']) or type(s['hour']) is not int or s['hour'] != s['clock'] // HOUR:
        return False
    for field, bound in [('rates', 100), ('pauses', 1000), ('events', 5000)]:
        if type(s[field]) is not dict or len(s[field]) > bound or not all(key(k) for k in s[field]):
            return False
    if not all(integer(v) and 1 <= v <= 8 for v in s['rates'].values()):
        return False
    if sum(s['rates'].values()) != s['page'] or not all(v is True for v in s['pauses'].values()):
        return False
    for e in s['events'].values():
        if not shape(e, ['type', 'threadKey', 'timestamp', 'seenAt']):
            return False
        if e['type'] not in ('message', 'staff') or not key(e['threadKey']):
            return False
        if not integer(e['timestamp']) or not integer(e['seenAt']) or s['clock'] is None:
            return False
        if not 0 <= s['clock'] - e['seenAt'] < DAY:
            return False
        if e['timestamp'] - e['seenAt'] > 60_000 or e['seenAt'] - e['timestamp'] >= WINDOW:
            return False
    return True


def unique_object(pairs):
    result = {}
    for k, v in pairs:
        if k in result:
            raise ValueError()
        result[k] = v
    return result


def event_valid(e):
    return (shape(e, ['type', 'eventKey', 'threadKey', 'timestamp'])
            and type(e['type']) is str and e['type'] in ('message', 'staff')
            and key(e['eventKey']) and key(e['threadKey']) and integer(e['timestamp']))


def pause(s, thread):
    if thread not in s['pauses']:
        if len(s['pauses']) == 1000:
            s['killed'] = True
            return False
        s['pauses'][thread] = True
    return True


def reduce_event(s, e, now):
    staff = e['type'] == 'staff'
    if staff and not pause(s, e['threadKey']):
        return ('blocked', 'pause_capacity')
    if s['clock'] is not None and now < s['clock']:
        return ('paused' if staff else 'blocked', 'clock_reversed')
    s['clock'] = now
    s['events'] = {k: v for k, v in s['events'].items() if now - v['seenAt'] < DAY}
    if s['hour'] != now // HOUR:
        s.update(hour=now // HOUR, page=0, rates={})
    if e['timestamp'] - now > 60_000:
        return ('blocked', 'future_timestamp')
    if now - e['timestamp'] >= WINDOW:
        return ('paused', 'staff_takeover') if staff else ('ignore', 'expired')
    previous = s['events'].get(e['eventKey'])
    if previous is not None:
        if staff:
            return ('paused', 'staff_takeover')
        same = all(previous[k] == e[k] for k in ('type', 'threadKey', 'timestamp'))
        return ('ignore', 'duplicate') if same else ('blocked', 'event_conflict')
    if len(s['events']) == 5000:
        s['killed'] = True
        return ('paused' if staff else 'blocked', 'event_capacity')
    s['events'][e['eventKey']] = {k: e[k] for k in ('type', 'threadKey', 'timestamp')}
    s['events'][e['eventKey']]['seenAt'] = now
    if staff:
        return ('paused', 'staff_takeover')
    if s['killed']:
        return ('blocked', 'kill_switch')
    if e['threadKey'] in s['pauses']:
        return ('paused', 'thread_paused')
    count = s['rates'].get(e['threadKey'], 0)
    if count >= 8:
        return ('blocked', 'thread_rate')
    if s['page'] >= 100:
        return ('blocked', 'page_rate')
    s['page'] += 1
    s['rates'][e['threadKey']] = count + 1
    return ('eligible', 'local_checks_passed')


class MetadataStore:
    def __init__(self, path):
        self.path = Path(path).resolve()

    @classmethod
    def create(cls, path):
        """Explicit creation only: even an existing zero-byte file is refused."""
        store = cls(path)
        try:
            fd = os.open(store.path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
            os.close(fd)
            with closing(sqlite3.connect(store.path)) as db, db:
                db.execute(SCHEMA)
                db.execute('INSERT INTO metadata VALUES (1, ?)', (json.dumps(fresh()),))
        except (OSError, sqlite3.Error):
            raise StoreError('creation_failed') from None
        return store

    @contextmanager
    def transaction(self):
        db = None
        try:
            db = sqlite3.connect(self.path.as_uri() + '?mode=rw', uri=True, timeout=1,
                                 isolation_level=None)
            db.execute('PRAGMA synchronous=FULL')
            db.execute('BEGIN IMMEDIATE')
            if db.execute('PRAGMA quick_check').fetchall() != [('ok',)]:
                raise ValueError()
            schema = db.execute("SELECT name, sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'").fetchall()
            if schema != [('metadata', SCHEMA)]:
                raise ValueError()
            rows = db.execute('SELECT id, state FROM metadata').fetchall()
            if (len(rows) != 1 or rows[0][0] != 1 or type(rows[0][1]) is not str
                    or len(rows[0][1]) > 3_000_000):
                raise ValueError()
            state = json.loads(rows[0][1], object_pairs_hook=unique_object)
            if not valid(state):
                raise ValueError()
            yield state
            if not valid(state):
                raise ValueError()
            db.execute('UPDATE metadata SET state=? WHERE id=1',
                       (json.dumps(state, separators=(',', ':')),))
            db.execute('COMMIT')
        except (sqlite3.Error, OSError, ValueError, TypeError, KeyError, OverflowError, RecursionError):
            raise StoreError('store_unavailable') from None
        finally:
            if db is not None:
                db.close()  # An uncommitted transaction rolls back.

    def accept(self, events, now):
        """Atomic <=50-event batch, staff first; results follow processing order."""
        if type(events) is not list or len(events) > 50 or not integer(now):
            raise StoreError('invalid_metadata')
        if not all(event_valid(e) for e in events):
            raise StoreError('invalid_metadata')
        events = [e.copy() for e in events]
        events.sort(key=lambda e: (e['type'] != 'staff', e['timestamp']))
        with self.transaction() as state:
            decisions = [reduce_event(state, e, now) for e in events]
        return [dict(action=a, reason=r) for a, r in decisions]

    def set_killed(self, enabled):
        if type(enabled) is not bool:
            raise StoreError('invalid_control')
        with self.transaction() as state:
            state['killed'] = enabled

    def set_paused(self, thread_key, enabled):
        if not key(thread_key) or type(enabled) is not bool:
            raise StoreError('invalid_control')
        with self.transaction() as state:
            if enabled:
                return pause(state, thread_key)
            state['pauses'].pop(thread_key, None)
        return True
