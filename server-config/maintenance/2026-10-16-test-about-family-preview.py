"""Offline safety checks for the non-deployable About-page visual preview."""

import re
import unittest
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit


ROOT = Path(__file__).resolve().parents[2]
PREVIEW = ROOT / "server-config" / "previews" / "about-family"


class Document(HTMLParser):
    def __init__(self, source):
        super().__init__(convert_charrefs=True)
        self.tags = []
        self.text = []
        self.headings = []
        self.heading = None
        self.hidden_depth = 0
        self.feed(source)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        self.tags.append((tag, attrs))
        if tag in {"script", "style"}:
            self.hidden_depth += 1
        if tag == "h1":
            self.heading = []
        if tag == "br" and self.heading is not None:
            self.heading.append(" ")

    def handle_endtag(self, tag):
        if tag in {"script", "style"}:
            self.hidden_depth -= 1
        if tag == "h1" and self.heading is not None:
            self.headings.append("".join(self.heading).strip())
            self.heading = None

    def handle_data(self, data):
        if not self.hidden_depth:
            self.text.append(data)
        if self.heading is not None:
            self.heading.append(data)


class AboutPreviewTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.html = (PREVIEW / "index.html").read_text(encoding="utf-8")
        cls.css = (PREVIEW / "about-family.css").read_text(encoding="utf-8")
        js = PREVIEW / "about-family.js"
        cls.js = js.read_text(encoding="utf-8") if js.exists() else ""
        cls.doc = Document(cls.html)
        cls.copy = " ".join(" ".join(cls.doc.text).split())

    def test_preview_is_outside_production_sources(self):
        self.assertNotIn("wp-mu-plugins", PREVIEW.parts)
        self.assertNotIn("wp-plugins", PREVIEW.parts)

    def test_hungarian_document_and_single_brand_h1(self):
        html = next(attrs for tag, attrs in self.doc.tags if tag == "html")
        self.assertEqual(html.get("lang"), "hu")
        self.assertEqual(self.doc.headings, ["Harmat Lakópark"])

    def test_noindex_nonproduction_preview(self):
        robots = [attrs.get("content", "") for tag, attrs in self.doc.tags
                  if tag == "meta" and attrs.get("name", "").lower() == "robots"]
        self.assertEqual(len(robots), 1)
        self.assertIn("noindex", robots[0])
        self.assertIn("nofollow", robots[0])

    def test_no_form_or_embedded_production_experience(self):
        self.assertFalse({tag for tag, _ in self.doc.tags} & {"form", "iframe", "object", "embed"})
        self.assertNotRegex(self.html, r"(?i)wp-json|admin-ajax|gtag\(|googletagmanager|fbq\(")

    def test_no_remote_scripts_or_active_link_payloads(self):
        for tag, attrs in self.doc.tags:
            if tag == "script" and attrs.get("src"):
                self.assertEqual(attrs["src"], "about-family.js")
            for name in ("href", "src"):
                value = attrs.get(name, "")
                self.assertNotRegex(value, r"(?i)^(javascript|data):")
            self.assertFalse(any(name.startswith("on") for name in attrs))

    def test_no_request_storage_or_tracking_code(self):
        self.assertNotRegex(self.js, r"(?i)\bfetch\s*\(|XMLHttpRequest|sendBeacon|localStorage|sessionStorage|document\.cookie|WebSocket|serviceWorker|eval\s*\(")

    def test_public_link_destination_allowlist(self):
        approved = {"harmat22.hu", "avant-garde.hu", "modum.hu", "value4real.com"}
        for tag, attrs in self.doc.tags:
            if tag != "a":
                continue
            href = attrs.get("href", "")
            if href.startswith("#"):
                continue
            parsed = urlsplit(href)
            if parsed.scheme in {"mailto", "tel"}:
                self.assertIn(href, {"mailto:ertekesites@harmat22.hu", "tel:+36300733375"})
            else:
                self.assertEqual(parsed.scheme, "https")
                self.assertIn(parsed.netloc.removeprefix("www."), approved)

    def test_official_contacts_and_project_facts(self):
        for text in ("398", "124", "2028", "Harmat utca 22", "ertekesites@harmat22.hu"):
            self.assertIn(text, self.copy)
        self.assertIn('href="tel:+36300733375"', self.html)
        self.assertRegex(self.copy.lower(), r"tervezett")
        self.assertRegex(self.copy.lower(), r"várható")
        self.assertRegex(self.copy.lower(), r"szerződés")

    def test_professional_background_preserved(self):
        for text in ("Cooperation Power Kft.", "Avant-Garde", "MODUM", "Value4Real", "Kertész", "Krizsán"):
            self.assertIn(text, self.copy)
        for domain in ("avant-garde.hu", "modum.hu", "value4real.com"):
            self.assertIn(domain, self.html)

    def test_image_provenance_dimensions_and_notice(self):
        images = [attrs for tag, attrs in self.doc.tags if tag == "img"]
        self.assertGreaterEqual(len(images), 3)
        for attrs in images:
            self.assertTrue(attrs.get("alt", "").strip())
            self.assertGreater(int(attrs.get("width", "0")), 0)
            self.assertGreater(int(attrs.get("height", "0")), 0)
            src = urlsplit(attrs["src"])
            self.assertEqual((src.scheme, src.netloc), ("https", "harmat22.hu"))
        self.assertIn("Harmat22_latvany-11", self.html)
        self.assertIn("Harmat22_latvany-13", self.html)
        self.assertRegex(self.copy.lower(), r"látványterv")
        self.assertRegex(self.copy.lower(), r"illusztráció")

    def test_no_placeholder_cjk_or_damaged_text(self):
        self.assertNotRegex(self.copy, r"[\u3400-\u9fff\uf900-\ufaff\ufffd]")
        self.assertNotRegex(self.copy.lower(), r"lorem ipsum|gipsz jakab|example\.com|coming soon")
        self.assertNotIn("Ã", self.copy)

    def test_no_gradient_negative_tracking_or_viewport_type(self):
        self.assertNotRegex(self.css, r"(?i)(linear|radial|conic)-gradient")
        self.assertNotRegex(self.css, r"(?i)letter-spacing\s*:\s*-")
        self.assertNotRegex(self.css, r"(?i)font-size\s*:[^;}]*\b(?:\d+(?:\.\d+)?)(?:vw|vi|vmin|vmax)")
        self.assertNotRegex(self.css, r"(?i)@import|fonts\.google|box-shadow\s*:[^;}]*[1-9]")


if __name__ == "__main__":
    unittest.main(verbosity=2)
