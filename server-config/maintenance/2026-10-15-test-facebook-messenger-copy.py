"""Offline checks for a disabled copy draft, not a Messenger integration test."""

import json
from pathlib import Path
import re
import unittest
from urllib.parse import urlsplit


COPY_PATH = Path(__file__).with_name("2026-10-15-facebook-messenger-copy.json")
SALES_PHONE = "+36300733375"
RECRUITMENT_PHONE = "+36306410358"
EMAIL = "ertekesites@harmat22.hu"
ADDRESS = "1105 Budapest, Harmat utca 22."
LINKS = {
    "site": "https://harmat22.hu/",
    "apartment_search": "https://harmat22.hu/lakaskereso/",
    "construction": "https://harmat22.hu/epitesi-naplo/",
    "contact": "https://harmat22.hu/elerhetosegeink/",
}
TOPICS = {"apartment_search", "price", "viewing", "location", "construction", "manual_contact", "floorplans", "parking_storage"}
FLAGS = {
    "runtime_enabled", "automatic_sending_enabled", "public_comment_replies_enabled",
    "customer_reads_enabled", "crm_writes_enabled", "marketing_follow_up_enabled",
    "external_ai_processing_enabled", "free_form_generation_enabled",
    "human_transfer_enabled", "appointment_booking_enabled", "data_collection_enabled",
}


def leaves(value, path=""):
    if isinstance(value, dict):
        for key, item in value.items():
            yield from leaves(item, f"{path}.{key}".lstrip("."))
    elif isinstance(value, list):
        for index, item in enumerate(value):
            yield from leaves(item, f"{path}.{index}")
    else:
        yield path, value


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f"Duplicate JSON key: {key}")
        result[key] = value
    return result


class MessengerCopyTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.raw = COPY_PATH.read_text(encoding="utf-8")
        cls.data = json.loads(cls.raw, object_pairs_hook=unique_object)

    def test_disabled_draft_and_review_gates(self):
        d = self.data
        self.assertEqual(set(d), {
            "schema_version", "status", "enabled", "deployed", "language", "response_mode",
            "review_required", "provenance", "scope", "public", "routing", "copy", "recruitment",
        })
        self.assertEqual(d["schema_version"], 1)
        self.assertEqual(d["status"], "local_draft_not_deployed")
        self.assertIs(d["enabled"], False)
        self.assertIs(d["deployed"], False)
        self.assertEqual(d["language"], "hu")
        self.assertEqual(d["response_mode"], "prewritten_faq_draft")
        self.assertEqual(d["review_required"], [
            "parent_hungarian_copy_review", "meta_capability_review", "explicit_deployment_approval",
        ])

    def test_private_only_scope_no_side_effects(self):
        scope = self.data["scope"]
        self.assertEqual(set(scope), FLAGS | {"phase", "proposed_entry_points"})
        self.assertEqual(scope["phase"], "messenger_private_only")
        self.assertEqual(scope["proposed_entry_points"], ["messenger_private_inquiry", "messenger_ad_inquiry"])
        for flag in FLAGS:
            with self.subTest(flag=flag):
                self.assertIs(scope[flag], False)

    def test_website_provenance_without_live_connection_or_stale_import(self):
        self.assertEqual(self.data["provenance"], {
            "baseline_code": "wp-plugins/harmat-local-assistant/harmat-local-assistant.php",
            "baseline_public_faq": "wp-plugins/harmat-local-assistant/data/harmat_ai_faq_responses.md",
            "adaptation": "selected_public_topics_only_no_bulk_import",
            "volatile_property_adapter": "wp-mu-plugins/zz-harmat-assistant-live-data.php",
            "unified_public_property_helper": "harmat_sai_property_summary_data",
            "live_source_connected": False,
            "volatile_fact_policy": "public_search_link_now_read_only_published_unified_data_if_later_approved",
            "hidden_price_policy": "never_copy_expose_or_infer_hidden_prices",
            "historical_fact_policy": "do_not_copy_old_dates_payment_milestones_counts_or_price_snapshots",
        })

    def test_exact_public_brand_contacts_and_links(self):
        self.assertEqual(self.data["public"], {
            "brand": "Harmat Lakópark", "address": ADDRESS,
            "sales_phone": SALES_PHONE, "sales_email": EMAIL, "links": LINKS,
        })
        urls = []
        for _, value in leaves(self.data):
            if isinstance(value, str):
                urls.extend(re.findall(r"https?://[^\s]+", value))
        self.assertTrue(urls)
        for url in urls:
            with self.subTest(url=url):
                parsed = urlsplit(url)
                self.assertEqual(parsed.scheme, "https")
                self.assertEqual(parsed.netloc, "harmat22.hu")
                self.assertIn(url, LINKS.values())

    def test_manual_routing_targets_and_recruitment_separation(self):
        expected = {topic: f"copy.faq.{topic}" for topic in TOPICS}
        expected.update({"recruitment": "recruitment.reply", "sensitive_data": "copy.sensitive_data",
                         "finance_or_contract": "copy.finance_or_contract"})
        self.assertEqual(self.data["routing"], {
            "mode": "manual_topic_map_only_no_runtime", "ambiguous": "copy.clarification",
            "default": "copy.fallback", "topics": expected,
        })
        for target in [*expected.values(), "copy.clarification", "copy.fallback"]:
            node = self.data
            for part in target.split("."):
                node = node[part]
            self.assertTrue(node)
        for path, value in leaves(self.data):
            if isinstance(value, str):
                if RECRUITMENT_PHONE in value or "09:00" in value or "17:00" in value:
                    self.assertTrue(path.startswith("recruitment."), path)
                if path.startswith("recruitment."):
                    self.assertNotIn(SALES_PHONE, value)
        recruitment = self.data["recruitment"]
        self.assertEqual(set(recruitment), {"phone", "email", "workplace", "working_hours", "reply"})
        self.assertEqual(recruitment["phone"], RECRUITMENT_PHONE)
        self.assertEqual(recruitment["email"], EMAIL)
        self.assertEqual(recruitment["workplace"], ADDRESS)
        for text in (recruitment["working_hours"], recruitment["reply"]):
            self.assertIn("hétfőtől péntekig, 09:00-17:00", text)
            self.assertIn("nem", text)
            self.assertIn("értékesítési nyitvatartás", text)

    def test_hungarian_copy_is_nonempty_and_clean(self):
        copy = self.data["copy"]
        self.assertEqual(set(copy), {"greeting", "clarification", "fallback", "sensitive_data", "finance_or_contract", "faq"})
        self.assertEqual(set(copy["faq"]), TOPICS)
        for faq in copy["faq"].values():
            self.assertEqual(set(faq), {"question", "answer"})
        for path, text in [*leaves(copy), ("recruitment.reply", self.data["recruitment"]["reply"])]:
            with self.subTest(path=path):
                self.assertIsInstance(text, str)
                self.assertTrue(text.strip())
                self.assertRegex(text, r"[áéíóöőúüűÁÉÍÓÖŐÚÜŰ]")
                self.assertNotRegex(text, r"[\u3400-\u9fff\ufffd]|Ã|Å|â€|TODO|TBD|placeholder|lorem|[<>{}]|\{\{")
                self.assertNotRegex(text, r"[\x00-\x1f]")

    def test_disclosure_and_honest_human_fallback(self):
        copy = self.data["copy"]
        for phrase in ("Harmat Lakópark", "automatikus tájékoztató"):
            self.assertIn(phrase, copy["greeting"])
        self.assertLessEqual(len(copy["greeting"]), 220)
        self.assertNotRegex(copy["greeting"], r"AI-tanácsadó|emberi ügyintéző vagyok")
        for text in (copy["fallback"], copy["faq"]["manual_contact"]["answer"]):
            self.assertIn(SALES_PHONE, text)
            self.assertIn(EMAIL, text)
            self.assertRegex(text, r"nem (továbbította|kapcsolta)")
        self.assertIn("nem foglal és nem igazol vissza időpontot", copy["faq"]["viewing"]["answer"])
        for phrase in ("önéletrajzot", "személyazonosító okmányt", "banki vagy pénzügyi adatot"):
            self.assertIn(phrase, copy["sensitive_data"])
        self.assertIn("nem rögzít jelentkezést", self.data["recruitment"]["reply"])

    def test_authoritative_links_and_no_current_progress_claim(self):
        faq = self.data["copy"]["faq"]
        for topic in ("apartment_search", "price", "floorplans"):
            self.assertIn(LINKS["apartment_search"], faq[topic]["answer"])
        self.assertIn("Ár egyeztetés alapján", faq["price"]["answer"])
        self.assertIn(LINKS["construction"], faq["construction"]["answer"])
        self.assertIn("nem igazolja a pillanatnyi készültséget", faq["construction"]["answer"])
        self.assertIn(ADDRESS, faq["location"]["answer"])
        self.assertIn("hivatalos adatlapján", faq["floorplans"]["answer"])
        parking = faq["parking_storage"]["answer"]
        for phrase in ("aktuális elérhetőségéről", "áráról", "ajánlatot", SALES_PHONE, EMAIL, "nem foglal"):
            self.assertIn(phrase, parking)

    def test_no_fixed_figures_or_forbidden_commitments(self):
        # Known contact/address/hour digits are the only permitted numbers in copy.
        texts = [v for _, v in leaves(self.data["copy"])] + [self.data["recruitment"]["reply"]]
        forbidden = (
            r"garantál|garancia|biztosan|jóváhagytuk|hitelképes|kedvezményt adunk|ingyenes",
            r"átkapcsoltam|továbbítottam|továbbítottuk|rögzítettük|lefoglaltuk|visszahívjuk|hamarosan válaszol",
            r"időpontja visszaigazolva|beköltözhető|elkészült|kulcsrakész|átadásra kerül|\bkészültsége\b",
            r"\bszabad lakásunk van\b|\bparkolóhely\b|\bszobaméret\b|\b(?:Ft|HUF|EUR|m2)\b",
            r"küldje (?:el|be)|adja meg|töltse fel|iratkozzon|felvesszük önnel a kapcsolatot",
        )
        for text in texts:
            with self.subTest(text=text[:60]):
                for pattern in forbidden:
                    self.assertNotRegex(text.lower(), pattern.lower())
                for allowed in (SALES_PHONE, RECRUITMENT_PHONE, ADDRESS, "09:00-17:00", *LINKS.values(), EMAIL):
                    text = text.replace(allowed, "")
                self.assertNotRegex(text, r"\d")

    def test_bounded_pure_data(self):
        self.assertLessEqual(len(self.raw.encode("utf-8")), 16000)

        def check(value, depth=0):
            self.assertLessEqual(depth, 6)
            self.assertIn(type(value), (dict, list, str, bool, int))
            if isinstance(value, dict):
                self.assertLessEqual(len(value), 16)
                for key, item in value.items():
                    self.assertRegex(key, r"^[a-z][a-z0-9_]*$")
                    check(item, depth + 1)
            elif isinstance(value, list):
                self.assertLessEqual(len(value), 16)
                for item in value:
                    check(item, depth + 1)
            elif isinstance(value, str):
                self.assertTrue(value.strip())
                self.assertLessEqual(len(value), 600)

        check(self.data)
        self.assertLessEqual(len(list(leaves(self.data))), 120)


if __name__ == "__main__":
    unittest.main(verbosity=2)
