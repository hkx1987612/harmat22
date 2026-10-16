"""Package the reviewed About draft into one offline HTML file; never deploy."""

import argparse
import base64
import hashlib
from html import escape
from html.parser import HTMLParser
from pathlib import Path, PurePosixPath
from urllib.parse import urlsplit


ROOT = Path(__file__).resolve().parents[2]
PREVIEW = ROOT / "server-config" / "previews" / "about-family"
OUTPUT = ROOT / "outputs" / "2026-10-16-about-family" / "harmat-about-family-preview-v2.html"
ASSETS = {
    "assets/harmat-courtyard.jpg": ("image/jpeg", "b9081b36c296f5c1ff519467eb8e1d3ce9379f03f78e4f0b49e4c7ce9405ee78"),
    "assets/harmat-living-room.jpg": ("image/jpeg", "3d68545fda27820a95c976074a89254d8ac442471119c2acfa8e385340e7d0c4"),
    "assets/harmat-logo.png": ("image/png", "83d853c5000b336d1be4169cef51ad3cc0096c187eb5fa0b68673b053ec5604c"),
}
CSP = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; script-src 'unsafe-inline'; base-uri 'none'; form-action 'none'"


def local_file(name):
    parsed = urlsplit(name)
    relative = PurePosixPath(name)
    if (not name or parsed.scheme or parsed.netloc or parsed.query or parsed.fragment
            or relative.is_absolute() or ".." in relative.parts or "\\" in name):
        raise ValueError("Only explicit local relative dependencies may be bundled")
    path = (PREVIEW / name).resolve(strict=True)
    path.relative_to(PREVIEW.resolve(strict=True))
    if not path.is_file():
        raise ValueError("Dependency is not a file")
    return path


class Bundle(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.parts = []
        self.images = 0
        self.styles = 0
        self.scripts = 0

    def handle_decl(self, decl):
        self.parts.append("<!" + decl + ">")

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == "link" and values.get("rel") == "stylesheet":
            if values.get("href") != "about-family.css":
                raise ValueError("Unexpected stylesheet")
            css = local_file(values["href"]).read_text(encoding="utf-8")
            if "</style" in css.lower():
                raise ValueError("Unsafe style closing marker")
            self.parts.append("<style>\n" + css + "\n</style>")
            self.styles += 1
        elif tag == "script":
            if values.get("src") != "about-family.js":
                raise ValueError("Unexpected script")
            script = local_file(values["src"]).read_text(encoding="utf-8")
            if "</script" in script.lower():
                raise ValueError("Unsafe script closing marker")
            self.parts.append("<script>\ndocument.addEventListener('DOMContentLoaded', () => {\n" + script + "\n}, {once: true});\n")
            self.scripts += 1
        elif tag == "img":
            name = values.get("src")
            if name not in ASSETS or values.get("srcset"):
                raise ValueError("Unreviewed image dependency")
            mime, expected = ASSETS[name]
            data = local_file(name).read_bytes()
            if hashlib.sha256(data).hexdigest() != expected:
                raise ValueError("Reviewed image hash changed")
            uri = "data:" + mime + ";base64," + base64.b64encode(data).decode("ascii")
            replaced = [(key, uri if key == "src" else value) for key, value in attrs]
            self.parts.append("<img" + "".join(" " + key if value is None else
                              " " + key + '="' + escape(value, quote=True) + '"'
                              for key, value in replaced) + ">")
            self.images += 1
        else:
            self.parts.append(self.get_starttag_text())
            if tag == "head":
                self.parts.append('\n<meta http-equiv="Content-Security-Policy" content="' + escape(CSP, quote=True) + '">')

    def handle_endtag(self, tag):
        self.parts.append("</" + tag + ">")

    def handle_startendtag(self, tag, attrs):
        raise ValueError("Unexpected self-closing markup; review before bundling")

    def handle_data(self, data):
        self.parts.append(data)

    def handle_entityref(self, name):
        self.parts.append("&" + name + ";")

    def handle_charref(self, name):
        self.parts.append("&#" + name + ";")

    def handle_comment(self, data):
        self.parts.append("<!--" + data + "-->")


def assemble():
    parser = Bundle()
    parser.feed(local_file("index.html").read_text(encoding="utf-8"))
    parser.close()
    if (parser.images, parser.styles, parser.scripts) != (4, 1, 1):
        raise ValueError("Unexpected reviewed resource counts")
    return "".join(parser.parts)


def main():
    args = argparse.ArgumentParser(description=__doc__)
    args.add_argument("--write", action="store_true", help="Create the exclusive new offline preview in ignored outputs")
    options = args.parse_args()
    content = assemble()
    if not options.write:
        print("Validated offline bundle; no files written.")
        return
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.parent.resolve(strict=True).relative_to((ROOT / "outputs").resolve(strict=True))
    with OUTPUT.open("x", encoding="utf-8", newline="\n") as output:
        output.write(content)
    print(str(OUTPUT))
    print("bytes=" + str(OUTPUT.stat().st_size))
    print("sha256=" + hashlib.sha256(OUTPUT.read_bytes()).hexdigest())


if __name__ == "__main__":
    main()
