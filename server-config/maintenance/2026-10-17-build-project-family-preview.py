"""Build a single offline Project preview; no website or server changes."""

import argparse
import base64
import hashlib
from html import escape
from html.parser import HTMLParser
from pathlib import Path, PurePosixPath
from urllib.parse import urlsplit


ROOT = Path(__file__).resolve().parents[2]
PREVIEW = ROOT / "server-config/previews/project-family"
OUTPUT = ROOT / "outputs/2026-10-17-project-family/harmat-project-family-preview-v1.html"
ASSETS = {
    "assets/project-aerial.jpg": ("image/jpeg", "efc5fc46c290fc4861ac37d309a4456d8b8866d37b5699ba742546e3ebb9c25d", (1920, 1080)),
    "assets/project-courtyard.jpg": ("image/jpeg", "b9081b36c296f5c1ff519467eb8e1d3ce9379f03f78e4f0b49e4c7ce9405ee78", (1920, 1080)),
    "assets/project-living-room.jpg": ("image/jpeg", "3d68545fda27820a95c976074a89254d8ac442471119c2acfa8e385340e7d0c4", (1920, 1080)),
    "assets/construction-site.webp": ("image/webp", "1855fec6962b3e546263a26ffc09b3523e3ce777bac409ed39ff9d1b67a29ee9", (1920, 1440)),
    "assets/harmat-logo.png": ("image/png", "83d853c5000b336d1be4169cef51ad3cc0096c187eb5fa0b68673b053ec5604c", (250, 250)),
}
CSP = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; script-src 'none'; base-uri 'none'; form-action 'none'"
PATHS = {
    "/", "/harmat-lakopark/", "/magunkrol/", "/lakaskereso/",
    "/virtualis-lakasvalaszto/", "/epitesi-naplo/", "/elerhetosegeink/",
    "/harmat-lakopark-kornyeke/", "/galeria/", "/adatvedelmi-tajekoztato/",
    "/cookie-tajekoztato/", "/felhasznalasi-feltetelek/", "/impresszum/",
}


def local_file(name):
    parsed = urlsplit(name)
    relative = PurePosixPath(name)
    if (not name or parsed.scheme or parsed.netloc or parsed.query or parsed.fragment
            or relative.is_absolute() or ".." in relative.parts or "\\" in name):
        raise ValueError("Explicit relative dependencies only")
    source = PREVIEW / name
    if any(path.is_symlink() for path in (source, *source.parents)):
        raise ValueError("Symlink dependency is not supported")
    path = source.resolve(strict=True)
    path.relative_to(PREVIEW.resolve(strict=True))
    if not path.is_file():
        raise ValueError("Dependency is not a file")
    return path


def public_link(value):
    if value.startswith("#") and len(value) > 1:
        return True
    if value in ("tel:+36300733375", "mailto:ertekesites@harmat22.hu"):
        return True
    parsed = urlsplit(value)
    return (parsed.scheme == "https" and parsed.netloc == "harmat22.hu"
            and parsed.path in PATHS and not parsed.query)


class Bundle(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.parts = []
        self.used = set()
        self.styles = 0
        self.heads = 0
        self.ids = []
        self.anchors = []

    def handle_decl(self, decl):
        if decl.lower() != "doctype html":
            raise ValueError("Unexpected document declaration")
        self.parts.append("<!" + decl + ">")

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if len(values) != len(attrs):
            raise ValueError("Duplicate HTML attributes")
        if tag in {"script", "form", "input", "textarea", "select", "iframe", "video", "audio", "object", "embed", "base", "svg", "source"}:
            raise ValueError("No active media, forms, scripts or external documents")
        if any(key.startswith("on") or key in {"srcset", "action", "formaction", "poster", "style", "ping"} for key in values):
            raise ValueError("Unexpected active or resource attribute")
        if "id" in values:
            self.ids.append(values["id"])
        if tag == "meta" and (values.get("http-equiv", "").lower() in {"refresh", "content-security-policy"}):
            raise ValueError("Unexpected source-level redirect or CSP")
        if tag == "a":
            href = values.get("href", "")
            if not public_link(href):
                raise ValueError("Unreviewed navigation link: " + href)
            if href.startswith("#"):
                self.anchors.append(href[1:])
        elif "href" in values and tag != "link":
            raise ValueError("Unexpected resource link")
        if tag == "link":
            if values.get("rel") != "stylesheet" or values.get("href") != "project-family.css":
                raise ValueError("Unexpected linked resource")
            css = local_file("project-family.css").read_text(encoding="utf-8")
            if any(marker in css.lower() for marker in ("</style", "url(", "@import", "@font-face")):
                raise ValueError("Unexpected stylesheet resource")
            self.parts.append("<style>\n" + css + "\n</style>")
            self.styles += 1
        elif tag == "img":
            name = values.get("src")
            if name not in ASSETS:
                raise ValueError("Unreviewed image")
            mime, expected, dimensions = ASSETS[name]
            data = local_file(name).read_bytes()
            if hashlib.sha256(data).hexdigest() != expected:
                raise ValueError("Reviewed original image changed")
            if (values.get("width"), values.get("height")) != tuple(map(str, dimensions)):
                raise ValueError("Image intrinsic dimensions differ")
            if not values.get("alt"):
                raise ValueError("Descriptive image alt required")
            uri = "data:" + mime + ";base64," + base64.b64encode(data).decode("ascii")
            self.parts.append("<img" + "".join(" " + key if value is None else
                              " " + key + '=\"' + escape(uri if key == "src" else value, quote=True) + '\"'
                              for key, value in attrs) + ">")
            self.used.add(name)
        else:
            if "src" in values:
                raise ValueError("Unexpected resource source")
            self.parts.append(self.get_starttag_text())
            if tag == "head":
                self.heads += 1
                self.parts.append('\n<meta http-equiv="Content-Security-Policy" content="' + escape(CSP, quote=True) + '">')

    def handle_endtag(self, tag):
        self.parts.append("</" + tag + ">")

    def handle_startendtag(self, tag, attrs):
        raise ValueError("Unexpected self-closing markup")

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
    if parser.used != set(ASSETS) or parser.styles != 1 or parser.heads != 1:
        raise ValueError("Unexpected reviewed resource coverage")
    if len(set(parser.ids)) != len(parser.ids) or not set(parser.anchors).issubset(parser.ids):
        raise ValueError("Duplicate IDs or missing local anchors")
    return "".join(parser.parts)


def main():
    args = argparse.ArgumentParser(description=__doc__)
    args.add_argument("--write", action="store_true", help="Create an exclusive new offline preview")
    options = args.parse_args()
    content = assemble()
    if not options.write:
        print("Validated offline Project preview; no files written.")
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
