"""No-browser, no-write checks for the self-contained preview package."""

import base64
import hashlib
import importlib.util
import unittest
from html.parser import HTMLParser
from pathlib import Path


SPEC = importlib.util.spec_from_file_location("about_bundle", Path(__file__).with_name("2026-10-16-build-about-family-bundle.py"))
bundle = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(bundle)


class Resources(HTMLParser):
    def __init__(self, text):
        super().__init__()
        self.tags = []
        self.feed(text)

    def handle_starttag(self, tag, attrs):
        self.tags.append((tag, dict(attrs)))


class BundleTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.text = bundle.assemble()
        cls.document = Resources(cls.text)

    def test_all_four_images_embedded_and_byte_identical(self):
        images = [attrs for tag, attrs in self.document.tags if tag == "img"]
        self.assertEqual(len(images), 4)
        expected = {item[1] for item in bundle.ASSETS.values()}
        seen = set()
        for attrs in images:
            prefix, encoded = attrs["src"].split(",", 1)
            self.assertIn(prefix, {"data:image/jpeg;base64", "data:image/png;base64"})
            data = base64.b64decode(encoded, validate=True)
            digest = hashlib.sha256(data).hexdigest()
            self.assertIn(digest, expected)
            self.assertTrue(data.startswith(b"\xff\xd8\xff") or data.startswith(b"\x89PNG\r\n\x1a\n"))
            seen.add(digest)
        self.assertEqual(seen, expected)

    def test_no_linked_styles_scripts_images_fonts(self):
        for tag, attrs in self.document.tags:
            self.assertFalse(tag == "link" and attrs.get("rel") == "stylesheet")
            self.assertFalse(tag == "script" and attrs.get("src"))
            self.assertFalse(tag in {"iframe", "form", "object", "embed"})
        self.assertIn(".harmat-about-preview", self.text)
        self.assertIn('const root = document.querySelector(".harmat-about-preview")', self.text)
        self.assertIn("document.addEventListener('DOMContentLoaded'", self.text)

    def test_csp_denies_network_resources_and_forms(self):
        csp = [attrs["content"] for tag, attrs in self.document.tags
               if tag == "meta" and attrs.get("http-equiv") == "Content-Security-Policy"]
        self.assertEqual(csp, [bundle.CSP])
        for policy in ("default-src 'none'", "img-src data:", "base-uri 'none'", "form-action 'none'"):
            self.assertIn(policy, csp[0])

    def test_document_copy_and_deliberate_contact_links_remain(self):
        for text in ("Harmat", "Lakópark", "398", "124", "2028", "noindex,nofollow",
                     'href="tel:+36300733375"', 'href="mailto:ertekesites@harmat22.hu"'):
            self.assertIn(text, self.text)

    def test_written_bundle_matches_reviewed_assembly_when_present(self):
        if not bundle.OUTPUT.exists():
            self.skipTest("Portable output has not been generated on this computer")
        self.assertEqual(bundle.OUTPUT.read_bytes(), self.text.encode("utf-8"))

    def test_unsafe_local_resource_paths_rejected(self):
        for path in ("../AGENTS.md", "/index.html", "https://harmat22.hu/image.jpg", "//example.com/image.jpg", "assets/../index.html", "assets\\harmat-logo.png", "index.html?x=1"):
            with self.assertRaises((ValueError, FileNotFoundError)):
                bundle.local_file(path)


if __name__ == "__main__":
    unittest.main(verbosity=2)
