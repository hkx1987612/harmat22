"""Offline source contracts only; not WordPress, browser, or mobile certification.

Run with Python's standard library. No PHP execution, networking, or file writes.
The controller's deployment pins and native integration are checked elsewhere.
"""

import hashlib
from html.parser import HTMLParser
from pathlib import Path
import re
import unittest
from urllib.parse import parse_qs, urlsplit


ROOT = Path(__file__).resolve().parents[2]
ASSETS = ROOT / "wp-mu-plugins" / "harmat-contact-family"
APPROVED = {
    "project-garden.jpg": (
        (1920, 1080),
        "96eaf92e4a8d32f6ec73ce0f7bf7b605394e893e30caa9b1a55cb38c081368b9",
    ),
    "showroom-model.jpg": (
        (1707, 1280),
        "e4ca1407126d7dcb9ea939fec5761d6d9982cf40c86d5a293673a628704bd457",
    ),
    "showroom-lounge.jpg": (
        (1707, 1280),
        "b3194b063d8d2bfdba7dadeffd981a79ba7560040d8d40b0939f008c154a9af3",
    ),
    "project-location.jpg": (
        (1707, 1280),
        "71ef699ca6bc75b6d911e1ef1cbe4ae74be3bfcba2f90f449d8349ed7b209b3e",
    ),
}
MAP = (
    "https://www.google.com/maps/search/?api=1&"
    "query=1105%20Budapest%2C%20Harmat%20utca%2022"
)
TRIGGER = "#opal-contactform-popup"
PHONE = "tel:+36300733375"
MAIL = "mailto:ertekesites@harmat22.hu"
VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input",
        "link", "meta", "param", "source", "track", "wbr"}


def extract_body(source):
    match = re.fullmatch(
        r"\s*<\?php\s+return\s+<<<'HTML'\r?\n(.*?)\r?\nHTML;\s*",
        source, re.S,
    )
    if not match:
        raise ValueError("Expected a data-only PHP HTML nowdoc")
    return match[1]


class Node:
    def __init__(self, tag, attrs=(), parent=None):
        self.tag, self.attrs, self.parent = tag, dict(attrs), parent
        self.children = []

    def text(self):
        return "".join(c.text() if isinstance(c, Node) else c for c in self.children)


class Document(HTMLParser):
    def __init__(self, source):
        super().__init__(convert_charrefs=True)
        self.root = Node("document")
        self.stack, self.nodes = [self.root], []
        self.feed(source)
        self.close()
        if len(self.stack) != 1:
            raise ValueError("Unclosed HTML element")

    def handle_starttag(self, tag, attrs):
        if len(dict(attrs)) != len(attrs):
            raise ValueError("Duplicate HTML attribute")
        node = Node(tag, attrs, self.stack[-1])
        self.stack[-1].children.append(node)
        self.nodes.append(node)
        if tag not in VOID:
            self.stack.append(node)

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in VOID:
            self.handle_endtag(tag)

    def handle_endtag(self, tag):
        if len(self.stack) == 1 or self.stack[-1].tag != tag:
            raise ValueError("Mismatched HTML end tag: " + tag)
        self.stack.pop()

    def handle_data(self, data):
        self.stack[-1].children.append(data)

    def handle_decl(self, decl):
        raise ValueError("Unexpected document declaration")


def jpeg_dimensions(data):
    """Read JPEG segment framing and SOF; does not claim image decoding."""
    # Approved source JPEGs can retain trailing metadata after EOI.
    if not data.startswith(b"\xff\xd8") or b"\xff\xd9" not in data[2:]:
        raise ValueError("Missing JPEG boundary markers")
    i = 2
    while i < len(data):
        if data[i] != 0xff:
            raise ValueError("Invalid JPEG marker boundary")
        while i < len(data) and data[i] == 0xff:
            i += 1
        if i >= len(data):
            break
        marker, i = data[i], i + 1
        if marker in {0xd9, 0xda}:
            break
        if marker in {0x01, *range(0xd0, 0xd8)}:
            continue
        size = int.from_bytes(data[i:i + 2], "big")
        if size < 2 or i + size > len(data):
            raise ValueError("Truncated JPEG segment")
        if marker in {0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7,
                      0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf}:
            if size < 8:
                raise ValueError("Short SOF")
            return (int.from_bytes(data[i + 5:i + 7], "big"),
                    int.from_bytes(data[i + 3:i + 5], "big"))
        i += size
    raise ValueError("Missing JPEG SOF")


def split_selectors(text):
    result, start, depth = [], 0, 0
    for i, char in enumerate(text):
        if char in "([":
            depth += 1
        elif char in ")]":
            depth -= 1
        elif char == "," and depth == 0:
            result.append(text[start:i].strip())
            start = i + 1
        if depth < 0:
            raise ValueError("Unbalanced selector")
    if depth:
        raise ValueError("Unbalanced selector")
    return result + [text[start:].strip()]


def css_rules(source):
    # Scan comments/strings structurally, retaining nested media-rule boundaries.
    source = re.sub(r"/\*.*?\*/", "", source, flags=re.S)
    if "/*" in source or "*/" in source:
        raise ValueError("Unclosed CSS comment")
    rules, i = [], 0
    while i < len(source):
        if not source[i:].strip():
            break
        opening = source.find("{", i)
        if opening < 0:
            raise ValueError("Trailing CSS outside a rule")
        selector = source[i:opening].strip()
        depth, quote, escaped, j = 1, None, False, opening + 1
        while j < len(source) and depth:
            char = source[j]
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif quote:
                if char == quote:
                    quote = None
            elif char in "\"'":
                quote = char
            elif char == "{":
                depth += 1
            elif char == "}":
                depth -= 1
            j += 1
        if depth or quote:
            raise ValueError("Unclosed CSS rule or string")
        content = source[opening + 1:j - 1]
        if selector.startswith("@"):
            if not re.fullmatch(r"@media\s+[^{};]+", selector):
                raise ValueError("Only media grouping is allowed")
            rules.extend(css_rules(content))
        else:
            if "{" in content or "}" in content:
                raise ValueError("Unexpected nested CSS")
            rules.append((split_selectors(selector), content))
        i = j
    return rules


def scoped(selector):
    if re.search(r":(?:root|global|host)\b|\\|/\*|[{};@]", selector):
        return False
    if re.search(r"\.page-id-(?!26\b)\d+", selector):
        return False
    root = re.match(r"#harmat-contact-family(?=[\s.#:\[>+~]|$)", selector)
    if root:
        remainder = selector[root.end():]
        # A sibling of the root is outside scope; siblings of descendants are not.
        boundary = re.search(r"[\s>+~]", remainder)
        escapes = boundary and remainder[boundary.start():].lstrip().startswith(("+", "~"))
        return not re.search(r"\b(?:body|html)\b", selector) and not escapes
    return bool(re.match(r"body\.page-id-26(?=[\s.#:\[>]|$)", selector)) and not re.search(
        r"[+~]|\bhtml\b|:has\(", selector
    )


class ContactCopy(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.body = extract_body((ASSETS / "body.php").read_text(encoding="utf-8"))
        cls.doc = Document(cls.body)
        cls.css = (ASSETS / "contact-family.css").read_text(encoding="utf-8")
        cls.rules = css_rules(cls.css)

    def test_balanced_single_owned_root(self):
        roots = [n for n in self.doc.nodes if n.parent is self.doc.root]
        self.assertEqual(len(roots), 1)
        self.assertEqual(roots[0].attrs.get("id"), "harmat-contact-family")
        ids = [n.attrs["id"] for n in self.doc.nodes if "id" in n.attrs]
        self.assertEqual(len(ids), len(set(ids)))
        for n in self.doc.nodes:
            for ref in n.attrs.get("aria-labelledby", "").split():
                self.assertIn(ref, ids)

    def test_hero_has_exactly_one_h2_no_premature_h1(self):
        titles = [n for n in self.doc.nodes if n.attrs.get("id") == "hcf-title"]
        self.assertEqual(len(titles), 1)
        self.assertEqual(titles[0].tag, "h2")
        self.assertEqual(titles[0].text(), "Harmat Lak\u00f3park")
        self.assertFalse(any(n.tag == "h1" for n in self.doc.nodes))

    def test_four_exact_approved_images(self):
        images = [n for n in self.doc.nodes if n.tag == "img"]
        self.assertEqual(len(images), 4)
        self.assertCountEqual([n.attrs.get("src") for n in images],
                              ["assets/" + name for name in APPROVED])
        for n in images:
            name = n.attrs["src"].removeprefix("assets/")
            dims, digest = APPROVED[name]
            data = (ASSETS / name).read_bytes()
            with self.subTest(image=name):
                self.assertEqual(hashlib.sha256(data).hexdigest(), digest)
                self.assertEqual(jpeg_dimensions(data), dims)
                self.assertEqual((int(n.attrs["width"]), int(n.attrs["height"])), dims)
                self.assertTrue(n.attrs.get("alt", "").strip())
                self.assertEqual(n.attrs.get("decoding"), "async")
                self.assertEqual(n.attrs.get("loading"),
                                 "eager" if name == "project-garden.jpg" else "lazy")
                if name == "project-garden.jpg":
                    self.assertEqual(n.attrs.get("fetchpriority"), "high")
                else:
                    self.assertNotEqual(n.attrs.get("fetchpriority"), "high")

    def test_render_disclosure_and_real_photo_captions(self):
        self.assertIn("L\u00e1tv\u00e1nyterv", self.doc.root.text())
        self.assertIn("illusztr\u00e1ci\u00f3", self.doc.root.text())
        figures = [n for n in self.doc.nodes if n.tag == "figure"]
        self.assertEqual(len(figures), 3)
        for n in figures:
            self.assertEqual(sum(isinstance(c, Node) and c.tag == "figcaption"
                                 and bool(c.text().strip()) for c in n.children), 1)

    def test_only_intended_links_and_three_native_triggers(self):
        links = [n for n in self.doc.nodes if n.tag == "a"]
        allowed = {PHONE, MAIL, MAP, TRIGGER, "/lakaskereso/", "/epitesi-naplo/", "/galeria/"}
        self.assertEqual({n.attrs.get("href") for n in links}, allowed)
        self.assertEqual(sum(n.attrs.get("href") == TRIGGER for n in links), 3)
        for n in links:
            self.assertTrue(n.text().strip())
            if n.attrs["href"] == TRIGGER:
                self.assertEqual(n.attrs.get("role"), "button")
            if n.attrs.get("target") == "_blank":
                self.assertIn("noopener", n.attrs.get("rel", "").split())

    def test_map_query_preserved_by_html_parser(self):
        link = next(n for n in self.doc.nodes if n.attrs.get("href") == MAP)
        query = parse_qs(urlsplit(link.attrs["href"]).query, strict_parsing=True)
        self.assertEqual(query, {"api": ["1"], "query": ["1105 Budapest, Harmat utca 22"]})
        self.assertNotIn("amp;query", query)

    def test_actual_contacts_address_and_hours(self):
        text = " ".join(self.doc.root.text().split())
        self.assertIn("+36 30 073 3375", text)
        self.assertIn("ertekesites@harmat22.hu", text)
        self.assertIn("1105 Budapest, Harmat utca 22.", text)
        hours = next(n for n in self.doc.nodes if "hcf-hours" in n.attrs.get("class", "").split())
        rows = [n for n in hours.children if isinstance(n, Node)]
        self.assertEqual([" ".join(n.text().split()) for n in rows],
                         ["H\u00e9tf\u0151\u2013p\u00e9ntek09:00\u201317:00",
                          "Szombat\u2013vas\u00e1rnapZ\u00e1rva"])
        self.assertIn("el\u0151zetesen", text)

    def test_no_forms_scripts_embeds_or_additional_resource_attributes(self):
        forbidden = {"form", "input", "select", "textarea", "option", "script", "style",
                     "iframe", "video", "audio", "source", "object", "embed", "link", "base",
                     "meta", "svg", "image", "noscript", "template"}
        for n in self.doc.nodes:
            self.assertNotIn(n.tag, forbidden)
            for key, value in n.attrs.items():
                self.assertIn(key, {"class", "id", "aria-label", "aria-labelledby", "src",
                                    "width", "height", "alt", "loading", "fetchpriority",
                                    "decoding", "href", "role", "target", "rel"})
                self.assertFalse(key.startswith("on"), key)
                self.assertNotIn(key, {"style", "srcset", "poster", "srcdoc", "action",
                                       "formaction", "background", "data", "ping", "xlink:href"})
                if key == "src":
                    self.assertEqual(n.tag, "img")
                    self.assertIn(value, {"assets/" + name for name in APPROVED})

    def test_no_placeholder_mojibake_chinese_or_source_picker(self):
        self.assertNotRegex(self.body, r"[\u3400-\u9fff\uf900-\ufaff\U00020000-\U0002fa1f]")
        self.assertNotRegex(self.body, r"\ufffd|\u00c3[\u0080-\u00bf]|\u00c2[\u0080-\u00bf]|\u00e2\u20ac")
        self.assertNotRegex(self.body.lower(),
                            r"gipsz|jakab|example\.(?:com|hu)|lorem|ipsum|your name|your email|"
                            r"leave a message|placeholder|012.?888|641.?03.?58|noga|huang|"
                            r"offer-source|your-source|lead-source|nosource")

    def test_no_nested_card_or_decorative_orb_classes(self):
        for n in self.doc.nodes:
            classes = n.attrs.get("class", "").lower()
            self.assertNotRegex(classes, r"(?:^|[-_\s])(?:orb|orbs|bokeh)(?:$|[-_\s])")
            if re.search(r"(?:^|[-_\s])cards?(?:$|[-_\s])", classes):
                parent = n.parent
                while parent:
                    self.assertNotRegex(parent.attrs.get("class", "").lower(),
                                        r"(?:^|[-_\s])cards?(?:$|[-_\s])")
                    parent = parent.parent

    def test_all_css_rules_have_narrow_scope(self):
        self.assertGreater(len(self.rules), 20)
        for selectors, _ in self.rules:
            for selector in selectors:
                with self.subTest(selector=selector):
                    self.assertTrue(scoped(selector))

    def test_css_has_no_network_or_global_injection(self):
        self.assertNotRegex(self.css.lower(), r"url\s*\(|@import|@font-face|@keyframes|expression\s*\(")
        self.assertNotRegex(self.css.lower(), r"gradient\s*\(|(?:^|[-_\s])(?:orbs?|bokeh)(?:$|[-_\s])")

    def test_css_typography_and_motion_constraints(self):
        for _, declarations in self.rules:
            for declaration in declarations.split(";"):
                if ":" not in declaration:
                    continue
                prop, value = (x.strip().lower() for x in declaration.split(":", 1))
                if prop == "letter-spacing":
                    self.assertRegex(value, r"^0(?:px|em|rem)?(?:\s*!important)?$")
                if prop in {"font", "font-size"}:
                    self.assertNotRegex(value, r"[\d.](?:s|l|d)?v(?:w|h|i|b)\b")
                self.assertNotIn(prop, {"animation", "animation-name", "filter", "backdrop-filter"})

    def test_readable_numerals_and_responsive_intent_not_render_proof(self):
        self.assertRegex(self.css, r"font-variant-numeric\s*:\s*lining-nums\s+tabular-nums")
        self.assertRegex(self.css, r"@media\s*\(max-width:\s*700px\)")
        self.assertRegex(self.css, r"overflow-wrap\s*:\s*(?:anywhere|break-word)")
        self.assertIn(":focus-visible", self.css)

    def test_owned_headings_reset_inherited_theme_padding(self):
        self.assertRegex(self.css, r"#harmat-contact-family\s+:is\(h1,\s*h2,\s*h3\)\s*\{\s*padding:\s*0\s*!important;")


class CheckerMechanics(unittest.TestCase):
    def test_nowdoc_rejects_executable_suffix(self):
        with self.assertRaises(ValueError):
            extract_body("<?php\nreturn <<<'HTML'\n<div></div>\nHTML;\nmail('x','y','z');")

    def test_html_entities_preserve_query(self):
        doc = Document('<a href="/x?a=1&amp;b=2">X</a>')
        self.assertEqual(doc.nodes[0].attrs["href"], "/x?a=1&b=2")

    def test_malformed_html_fails(self):
        for html in ('<div><p></div>', '<div>', '<img src="a" src="b">'):
            with self.subTest(html=html), self.assertRaises(ValueError):
                Document(html)

    def test_media_and_function_commas_are_not_scope_bypasses(self):
        rules = css_rules('@media (max-width:700px) { #harmat-contact-family :is(h1,h2), body { color:red; } }')
        self.assertEqual(len(rules), 1)
        self.assertEqual(rules[0][0], ['#harmat-contact-family :is(h1,h2)', 'body'])
        self.assertTrue(scoped(rules[0][0][0]))
        self.assertFalse(scoped(rules[0][0][1]))

    def test_rejects_scope_near_misses(self):
        for selector in ('body', '#harmat-contact-family-other p', 'body.page-id-260 a',
                         'body.page-id-26 ~ body', '#harmat-contact-family body',
                         ':root', '#harmat-contact-family :global(body)',
                         '#harmat-contact-family + .outside',
                         '#harmat-contact-family.active ~ .outside',
                         'body.page-id-26:has(.page-id-99) p'):
            with self.subTest(selector=selector):
                self.assertFalse(scoped(selector))

    def test_rejects_bad_css_and_jpeg(self):
        for css in ('body { color:red;', '@import "x";', '@font-face {x:y}', '/* open'):
            with self.subTest(css=css), self.assertRaises(ValueError):
                css_rules(css)
        for data in (b'not jpeg', b'\xff\xd8\xff\xe0\x00\xff\xff\xd9'):
            with self.subTest(data=data), self.assertRaises(ValueError):
                jpeg_dimensions(data)


if __name__ == "__main__":
    unittest.main(verbosity=2)
