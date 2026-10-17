"""Offline Project preview contracts; not a browser or production-form test."""

import base64
import hashlib
from html.parser import HTMLParser
import importlib.util
import re
import unittest
from unittest.mock import patch

from PIL import Image


def load_builder():
    from pathlib import Path
    spec = importlib.util.spec_from_file_location(
        "project_builder", Path(__file__).with_name("2026-10-17-build-project-family-preview.py"))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


B = load_builder()
VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input",
        "link", "meta", "param", "source", "track", "wbr"}


class Document(HTMLParser):
    def __init__(self, source):
        super().__init__(convert_charrefs=True)
        self.nodes = []
        self.stack = []
        self.feed(source)
        self.close()
        if self.stack:
            raise ValueError("Unclosed HTML tags")

    def handle_starttag(self, tag, attrs):
        if len(dict(attrs)) != len(attrs):
            raise ValueError("Duplicate attributes")
        node = {"tag": tag, "attrs": dict(attrs), "text": ""}
        self.nodes.append(node)
        if tag not in VOID:
            self.stack.append(node)

    def handle_endtag(self, tag):
        if not self.stack or self.stack[-1]["tag"] != tag:
            raise ValueError("Mismatched HTML tag: " + tag)
        self.stack.pop()

    def handle_data(self, text):
        for node in self.stack:
            node["text"] += text

    def tags(self, name):
        return [node for node in self.nodes if node["tag"] == name]


class PreviewTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = B.local_file("index.html").read_text(encoding="utf-8")
        cls.css = B.local_file("project-family.css").read_text(encoding="utf-8")
        cls.document = Document(cls.source)
        cls.bundle = B.assemble()
        cls.packaged = Document(cls.bundle)

    def test_hungarian_document_and_no_indexing(self):
        self.assertEqual(self.document.tags("html")[0]["attrs"].get("lang"), "hu")
        meta = {n["attrs"].get("name"): n["attrs"].get("content") for n in self.document.tags("meta")}
        self.assertEqual(meta.get("robots"), "noindex,nofollow")
        self.assertEqual(meta.get("viewport"), "width=device-width, initial-scale=1")
        self.assertEqual(meta.get("referrer"), "no-referrer")

    def test_balanced_semantics_and_one_brand_heading(self):
        self.assertEqual(len(self.document.tags("main")), 1)
        h1 = self.document.tags("h1")
        self.assertEqual(len(h1), 1)
        self.assertEqual(" ".join(h1[0]["text"].split()), "Harmat Lakópark")
        ids = [n["attrs"]["id"] for n in self.document.nodes if "id" in n["attrs"]]
        self.assertEqual(len(ids), len(set(ids)))

    def test_copy_cleanliness(self):
        text = self.document.tags("body")[0]["text"]
        self.assertNotRegex(text, r"[\u3400-\u9fff\ufffd]")
        self.assertNotRegex(text.lower(), r"lorem|ipsum|placeholder|gipsz jakab|example\.com|click here")
        self.assertNotIn("június 12-én lesz", text)
        self.assertNotIn("012-888-2222", text)

    def test_four_consistent_fact_values(self):
        facts = [n["text"].strip() for n in self.document.nodes
                 if "hpf-fact-number" in n["attrs"].get("class", "").split()]
        self.assertEqual([re.sub(r"\s", "", v) for v in facts], ["398", "124", "8388", "2028"])
        self.assertIn("tabular-nums", self.css)
        self.assertIn("lining-nums", self.css)
        self.assertRegex(self.css, r"\.hpf-fact-number\s*\{[^}]*font-size:\s*(?:34|36)px")

    def test_qualified_dates_and_archival_photo(self):
        times = {n["attrs"].get("datetime"): n["text"] for n in self.document.tags("time")}
        self.assertIn("2026-10-02", times)
        self.assertIn("2026-06-12", times)
        self.assertNotIn("2026-10-17", times)
        text = self.document.tags("body")[0]["text"].lower()
        self.assertIn("várható", text)
        self.assertRegex(text, r"szerződés|ütemezés")
        self.assertIn("helyszíni", text)
        self.assertIn("látványterv", text)

    def test_official_contacts_and_known_destinations(self):
        links = [n["attrs"].get("href", "") for n in self.document.tags("a")]
        for href in links:
            self.assertTrue(B.public_link(href), href)
        for href in ("tel:+36300733375", "mailto:ertekesites@harmat22.hu",
                     "https://harmat22.hu/lakaskereso/", "https://harmat22.hu/epitesi-naplo/",
                     "https://harmat22.hu/elerhetosegeink/", "https://harmat22.hu/virtualis-lakasvalaszto/"):
            self.assertIn(href, links)
        self.assertNotIn("+36306410358", self.source)

    def test_no_fake_forms_or_tracking(self):
        for tag in ("form", "input", "button", "script", "iframe", "video", "audio", "object", "embed"):
            self.assertFalse(self.document.tags(tag), tag)
        self.assertNotRegex(self.css.lower(), r"@import|@font-face|url\(")
        self.assertEqual(len(self.document.tags("link")), 1)

    def test_reviewed_images_full_decode(self):
        for name, (mime, expected, dimensions) in B.ASSETS.items():
            path = B.local_file(name)
            self.assertEqual(hashlib.sha256(path.read_bytes()).hexdigest(), expected)
            with Image.open(path) as image:
                image.load()
                self.assertEqual(image.size, dimensions)
                self.assertEqual(Image.MIME[image.format], mime)

    def test_intrinsic_sizes_and_loading(self):
        for node in self.document.tags("img"):
            attrs = node["attrs"]
            dimensions = B.ASSETS[attrs["src"]][2]
            self.assertEqual((int(attrs["width"]), int(attrs["height"])), dimensions)
            self.assertTrue(attrs.get("alt"))
            if attrs["src"] == "assets/project-aerial.jpg":
                self.assertEqual(attrs.get("fetchpriority"), "high")
                self.assertNotEqual(attrs.get("loading"), "lazy")
            elif attrs["src"] != "assets/harmat-logo.png":
                self.assertEqual(attrs.get("loading"), "lazy")

    def test_responsive_source_constraints_not_browser_proof(self):
        self.assertIn("@media", self.css)
        self.assertIn("box-sizing: border-box", self.css)
        self.assertNotRegex(self.css, r"letter-spacing:\s*-|font-size:[^;{}]*\b(?:\d*\.)?\d+(?:vw|vmin)")
        self.assertNotRegex(self.css, r"(?:min-)?width:\s*(?:1[3-9]\d\d|[2-9]\d{3})px")

    def test_embedded_images_identical_and_no_resource_requests(self):
        originals = self.document.tags("img")
        embedded = self.packaged.tags("img")
        self.assertEqual(len(originals), len(embedded))
        for old, new in zip(originals, embedded):
            prefix, data = new["attrs"]["src"].split(",", 1)
            name = old["attrs"]["src"]
            self.assertEqual(prefix, "data:" + B.ASSETS[name][0] + ";base64")
            self.assertEqual(base64.b64decode(data, validate=True), B.local_file(name).read_bytes())
        self.assertFalse(self.packaged.tags("link"))
        self.assertFalse(self.packaged.tags("script"))
        self.assertEqual(self.packaged.tags("body")[0]["text"], self.document.tags("body")[0]["text"])

    def test_strict_bundle_policy(self):
        policies = [n["attrs"].get("content") for n in self.packaged.tags("meta")
                    if n["attrs"].get("http-equiv") == "Content-Security-Policy"]
        self.assertEqual(policies, [B.CSP])
        self.assertIn("script-src 'none'", B.CSP)
        self.assertIn("form-action 'none'", B.CSP)

    def test_unsafe_dependency_paths_rejected(self):
        for value in ("../AGENTS.md", "https://harmat22.hu/", "//example.com/x", "/x", "assets\\x", "index.html?x=1", "index.html#x"):
            with self.subTest(value=value), self.assertRaises(ValueError):
                B.local_file(value)

    def test_unsafe_navigation_rejected(self):
        for value in ("javascript:alert(1)", "https://harmat22.hu.evil.test/", "http://harmat22.hu/",
                      "https://harmat22.hu/wp-admin/", "https://harmat22.hu/?action=delete", "tel:+36306410358"):
            self.assertFalse(B.public_link(value))

    def test_bundle_refuses_modified_original(self):
        with patch.object(B.hashlib, "sha256") as digest:
            digest.return_value.hexdigest.return_value = "0" * 64
            with self.assertRaisesRegex(ValueError, "original image changed"):
                B.assemble()

    def test_existing_saved_bundle_matches_current_source(self):
        if B.OUTPUT.exists():
            self.assertEqual(B.OUTPUT.read_text(encoding="utf-8"), self.bundle)


if __name__ == "__main__":
    unittest.main(verbosity=2)
