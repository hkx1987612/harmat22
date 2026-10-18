"""No-network parser tests; findings still require contextual/manual review."""
import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('audit', Path(__file__).with_name('2026-10-18-audit-payment-copy.py'))
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)


class AuditTests(unittest.TestCase):
    def test_private_and_query_urls_refused(self):
        for path in ('/sales/', '/wp-admin/', '/wp-json/x', '/wp-login.php', '/?token=x'):
            with self.subTest(path=path), self.assertRaises(ValueError):
                audit.public_url(audit.ORIGIN + path)

    def test_origin_and_scheme_refused(self):
        for url in ('http://harmat22.hu/', 'https://other.example/', 'https://harmat22.hu.evil.example/'):
            with self.subTest(url=url), self.assertRaises(ValueError):
                audit.public_url(url)

    def test_dates_not_automatically_classified_as_obsolete(self):
        row, _, _ = audit.inspect(b'<html><body>Payment 50% 2026-12-31, 50% 2027-06-30 for 4%</body></html>', audit.ORIGIN)
        self.assertTrue(row['dates'])
        self.assertNotIn('obsolete', row)

    def test_script_and_body_separate(self):
        row, _, _ = audit.inspect(b'<html><body>Deposit 10% + 15%<script>payment="2027-06-01"</script></body></html>', audit.ORIGIN)
        self.assertFalse(row['dates'])
        self.assertTrue(row['inline'][0]['dates'])

    def test_no_mathematical_conflict_inferred(self):
        row, _, _ = audit.inspect(b'<html><body>Payment: deposit 10%, advance 15%</body></html>', audit.ORIGIN)
        self.assertTrue(row['payment_percentages'])
        self.assertNotIn('conflict', row)

    def test_assets_documents_and_native_form(self):
        row, assets, docs = audit.inspect(b'<html><head><link rel="canonical" href="https://harmat22.hu/"></head><body><form id="h22-offer-form"></form><script src="/wp-content/mu-plugins/a.js?v=1"></script><a href="/plan.pdf">Plan</a></body></html>', audit.ORIGIN)
        self.assertEqual(row['native_form_count'], 1)
        self.assertEqual(assets, {audit.ORIGIN + '/wp-content/mu-plugins/a.js'})
        self.assertEqual(docs, {audit.ORIGIN + '/plan.pdf': 'Plan'})


if __name__ == '__main__':
    unittest.main()
