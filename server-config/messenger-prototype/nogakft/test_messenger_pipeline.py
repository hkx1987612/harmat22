"""Synthetic-only component composition, not a live messaging integration.

This test stitches receipt decisions to a preview. No production queue, sender,
model, Meta token, test conversation or listening HTTP server is provided.
"""
from datetime import datetime, timezone
import hashlib
import hmac
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

from ingress import Config, handle
from metadata_store import MetadataStore
from reply_preview import preview_reply

NOW = datetime(2026, 10, 15, 12, tzinfo=timezone.utc)
STAMP = int(NOW.timestamp() * 1000)
PAGE, PEER = '11111', '22222'
SECRET = 'synthetic-app-secret-not-a-credential'
CONFIG = Config(receive_enabled=True, data_processing_approved=True,
                page_id=PAGE, app_secret=SECRET,
                identity_key='synthetic-identity-key-not-a-credential')
SEED = (Path(__file__).resolve().parents[2] / 'maintenance' /
        '2026-10-15-facebook-messenger-copy.json').read_bytes()


def incoming(mid='fixture-1'):
    return dict(sender={'id':PEER},recipient={'id':PAGE},timestamp=STAMP,
                message={'mid':mid,'text':'A1-F-L1'})


def snapshot():
    return dict(schema_version=1,generated_at='2026-10-15T12:00:00Z',
        source='https://harmat22.hu/lakaskereso/',currency='HUF',items=[dict(
            code='A1-F-L1',url='https://harmat22.hu/property/a1-f-l1/',building='A1',
            floor='F',rooms=2,sales_area_m2=47.83,outdoor_area_m2=0,
            status='reserved',price_huf=None)])


def encode(value):
    return json.dumps(value,separators=(',',':')).encode()


class PipelineTests(unittest.TestCase):
    def setUp(self):
        directory = tempfile.TemporaryDirectory()
        self.addCleanup(directory.cleanup)
        self.store = MetadataStore.create(Path(directory.name) / 'metadata.sqlite')
        for target in ('socket.create_connection','socket.getaddrinfo','socket.socket.connect'):
            guard = patch(target,side_effect=AssertionError('network forbidden'))
            guard.start()
            self.addCleanup(guard.stop)

    def receive(self, events, *, tamper=False):
        body = encode({'object':'page','entry':[{'id':PAGE,'messaging':events}]})
        signature = 'sha256=' + hmac.new(SECRET.encode(),body,hashlib.sha256).hexdigest()
        if tamper:
            body += b' '
        original = MetadataStore.accept
        decisions = []

        def capture(store, batch, now):
            value = original(store,batch,now)
            decisions.extend(value)
            return value

        with patch.object(MetadataStore,'accept',new=capture):
            response = handle('POST','/webhook',[],[
                ('Content-Type','application/json'),('X-Hub-Signature-256',signature)],
                body,CONFIG,self.store,STAMP)
        return response,decisions

    def preview_if_eligible(self, decisions, *, inventory=None):
        if not any(value['action']=='eligible' for value in decisions):
            return None
        return preview_reply('A1-F-L1',seed_payload=SEED,
            inventory_payload=encode(snapshot() if inventory is None else inventory),now=NOW)

    def test_default_stop_prevents_even_preview(self):
        response,decisions = self.receive([incoming()])
        self.assertEqual(response.status,200)
        self.assertEqual(decisions,[{'action':'blocked','reason':'kill_switch'}])
        self.assertIsNone(self.preview_if_eligible(decisions))

    def test_receipt_to_grounded_non_sendable_preview(self):
        self.store.set_killed(False)
        response,decisions = self.receive([incoming()])
        result = self.preview_if_eligible(decisions)
        self.assertEqual(response.status,200)
        self.assertEqual(result['route'],'apartment')
        self.assertIs(result['dry_run'],True)
        self.assertIs(result['sendable'],False)
        self.assertIn('47,83',result['reply'])
        self.assertIn('Foglalt',result['reply'])
        self.assertIn('Ár egyeztetés alapján',result['reply'])

    def test_duplicate_does_not_generate_another_preview(self):
        self.store.set_killed(False)
        _,first = self.receive([incoming()])
        response,second = self.receive([incoming()])
        self.assertIsNotNone(self.preview_if_eligible(first))
        self.assertEqual(response.status,200)
        self.assertEqual(second,[{'action':'ignore','reason':'duplicate'}])
        self.assertIsNone(self.preview_if_eligible(second))

    def test_staff_pause_wins_same_batch_and_restart(self):
        self.store.set_killed(False)
        echo = dict(sender={'id':PAGE},recipient={'id':PEER},timestamp=STAMP,
                    message={'mid':'fixture-echo','is_echo':True})
        _,decisions = self.receive([incoming(),echo])
        self.assertIsNone(self.preview_if_eligible(decisions))
        self.store = MetadataStore(self.store.path)
        response,later = self.receive([incoming('fixture-2')])
        self.assertEqual(response.status,200)
        self.assertEqual(later,[{'action':'paused','reason':'thread_paused'}])

    def test_tampered_wire_never_commits_or_previews(self):
        before = self.store.path.read_bytes()
        response,decisions = self.receive([incoming()],tamper=True)
        self.assertEqual(response.status,403)
        self.assertEqual(decisions,[])
        self.assertEqual(before,self.store.path.read_bytes())

    def test_bad_source_never_yields_unit_details(self):
        self.store.set_killed(False)
        _,decisions = self.receive([incoming()])
        stale = snapshot()
        stale['generated_at']='2026-10-15T11:00:00Z'
        result = self.preview_if_eligible(decisions,inventory=stale)
        self.assertEqual(result['route'],'inventory_unavailable')
        self.assertNotIn('47,83',result['reply'])
        self.assertIs(result['sendable'],False)

    def test_no_raw_text_or_ids_persisted(self):
        self.receive([incoming()])
        with self.store.transaction() as state:
            value = json.dumps(state)
            for forbidden in ('A1-F-L1','fixture-1',PAGE,PEER):
                self.assertNotIn(forbidden,value)

    def test_corruption_is_not_acknowledged_or_repaired(self):
        self.store.path.write_bytes(b'synthetic corrupt fixture')
        response,decisions = self.receive([incoming()])
        self.assertEqual(response.status,503)
        self.assertEqual(decisions,[])
        self.assertEqual(self.store.path.read_bytes(),b'synthetic corrupt fixture')


if __name__=='__main__':
    unittest.main()
