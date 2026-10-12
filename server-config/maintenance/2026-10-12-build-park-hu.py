"""Bounded local Hungarian title replacement. No publication or rights approval."""

import argparse
import hashlib
import io
import json
import math
import shutil
import struct
import subprocess
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[2]
SOURCE = Path("Z:/mmexport1791791835803.mp4")
SOURCE_HASH = "f18521184f8d32f72c164f1c2880c421367847bc9a437fd10c35df66568135ec"
FFMPEG = Path("C:/Users/Administrator/AppData/Roaming/Python/Python312/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v7.1.exe")
COPY = ROOT / "server-config/maintenance/2026-10-12-park-hu-copy.json"
BASE = ROOT / "outputs/2026-10-12-park-hu"
LOGO = ROOT / "outputs/2026-10-10-construction-ad-fullscreen/source/harmat-logo.png"
FONTS = {"regular": Path("C:/Windows/Fonts/arial.ttf"), "bold": Path("C:/Windows/Fonts/arialbd.ttf")}
WIDTH, HEIGHT, FPS, FRAMES = 1920, 1080, 30, 1129
INTRO_END, FOOTER_START, CLOSING_START = 126, 120, 978
SOURCE_ENDING_FIRST_CN = 978
SOURCE_OPENING_LAST_CN = 124
# Parent visually selected approximate cues, not an automatic cut detector.
CUES = [126, 246, 366, 486, 624, 737, 850, CLOSING_START]
FOREST, GOLD, WHITE = "#123C31", "#CDB477", "#FFFFFF"
OWNERSHIP = "harmat-park-hu-local-v2"


def require(condition, message):
    if not condition:
        raise ValueError(message)


def sha256(path):
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def source_guard(path, expected=SOURCE_HASH):
    require(path.resolve() == SOURCE.resolve(), "Source path is not the pinned source")
    require(sha256(path) == expected, "Pinned source hash mismatch")


def strings(value):
    if isinstance(value, str):
        yield value
    elif isinstance(value, dict):
        for item in value.values():
            yield from strings(item)
    elif isinstance(value, list):
        for item in value:
            yield from strings(item)


def is_cjk(text):
    return any(0x3400 <= ord(c) <= 0x9FFF or 0xF900 <= ord(c) <= 0xFAFF or 0x20000 <= ord(c) <= 0x323AF for c in text)


def load_copy(path):
    value = json.loads(path.read_text(encoding="utf-8-sig"))
    require(value["status"] in ("DRAFT", "PREPARED_LOCAL"), "Copy is not a local draft")
    require(value["language"] == "hu-HU", "Unexpected language")
    require(not any(is_cjk(s) for s in strings(value)), "CJK text is forbidden")
    require(len(value["scenes"]) == 7, "Exactly seven scenes required")
    require([s["scene"] for s in value["scenes"]] == list(range(1, 8)), "Scene order mismatch")
    for scene in value["scenes"]:
        require(0 < len(scene["title"]) <= 32 and 0 < len(scene["subtitle"]) <= 62, "Scene copy length invalid")
    require(len(value["intro"]["sponsor_line"]) <= 100, "Sponsor line too long")
    require(len(value["youtube"]["title"]) < 100, "YouTube title too long")
    require(600 <= len(value["youtube"]["description"]) <= 1000, "Description length invalid")
    require(value["closing"]["website"] == "https://harmat22.hu/", "Unapproved closing URL")
    return value


def font_glyphs(path):
    """Read the actual TrueType cmap, without relying on fallback fonts."""
    data = path.read_bytes()
    count = struct.unpack_from(">H", data, 4)[0]
    cmap = None
    for index in range(count):
        tag, _, offset, _ = struct.unpack_from(">4sIII", data, 12 + 16 * index)
        if tag == b"cmap":
            cmap = offset
    require(cmap is not None, "Missing font cmap")
    supported = set()
    for index in range(struct.unpack_from(">H", data, cmap + 2)[0]):
        platform, encoding, offset = struct.unpack_from(">HHI", data, cmap + 4 + 8 * index)
        if platform not in (0, 3) or (platform == 3 and encoding not in (1, 10)):
            continue
        start = cmap + offset
        fmt = struct.unpack_from(">H", data, start)[0]
        if fmt == 4:
            segments = struct.unpack_from(">H", data, start + 6)[0] // 2
            ends = start + 14
            starts = ends + 2 * segments + 2
            deltas = starts + 2 * segments
            ranges = deltas + 2 * segments
            for segment in range(segments):
                end = struct.unpack_from(">H", data, ends + segment * 2)[0]
                begin = struct.unpack_from(">H", data, starts + segment * 2)[0]
                delta = struct.unpack_from(">h", data, deltas + segment * 2)[0]
                offset = struct.unpack_from(">H", data, ranges + segment * 2)[0]
                for code in range(begin, min(end, 0xFFFE) + 1):
                    glyph = (code + delta) & 0xFFFF
                    if offset:
                        glyph = struct.unpack_from(">H", data, ranges + segment * 2 + offset + 2 * (code - begin))[0]
                        if glyph:
                            glyph = (glyph + delta) & 0xFFFF
                    if glyph:
                        supported.add(code)
        elif fmt == 12:
            groups = struct.unpack_from(">I", data, start + 12)[0]
            for group in range(groups):
                begin, end, glyph = struct.unpack_from(">III", data, start + 16 + 12 * group)
                supported.update(range(begin + (glyph == 0), end + 1))
    return supported


def glyph_check(copy):
    needed = {ord(c) for s in strings(copy) for c in s if not c.isspace()}
    needed.update(ord(c) for c in copy["closing"]["title"].upper())
    for path in FONTS.values():
        require(needed <= font_glyphs(path), "Missing glyphs in " + path.name)


def intervals():
    return list(zip([FOOTER_START] + CUES[1:-1], CUES[1:]))


def bounds(x, y, w, h):
    require(0 <= x < WIDTH and 0 <= y < HEIGHT and w > 0 and h > 0 and x + w <= WIDTH and y + h <= HEIGHT, "Mask outside canvas")


def wrapped(draw, text, font, width, max_lines):
    lines = []
    current = ""
    for word in text.split():
        require(draw.textlength(word, font=font) <= width, "Word exceeds width")
        candidate = (current + " " + word).strip()
        if draw.textlength(candidate, font=font) <= width:
            current = candidate
        else:
            lines.append(current)
            current = word
    if current:
        lines.append(current)
    require(0 < len(lines) <= max_lines, "Too many wrapped lines")
    return lines


def text_block(image, text, box, size, bold=False, max_lines=1, center=False, minimum=None, color=WHITE):
    x, y, w, h = box
    bounds(x, y, w, h)
    draw = ImageDraw.Draw(image)
    for chosen in range(size, (minimum or size) - 1, -1):
        font = ImageFont.truetype(str(FONTS["bold" if bold else "regular"]), chosen)
        try:
            lines = wrapped(draw, text, font, w, max_lines)
            line_height = math.ceil(chosen * 1.22)
            require(len(lines) * line_height <= h, "Text block too tall")
            positions = []
            for line_index, line in enumerate(lines):
                ink = draw.textbbox((0, 0), line, font=font, anchor="lt")
                tx = x - ink[0] + ((w - (ink[2] - ink[0])) / 2 if center else 0)
                ty = y + line_index * line_height - ink[1]
                bbox = draw.textbbox((tx, ty), line, font=font, anchor="lt")
                require(bbox[0] >= x and bbox[2] <= x + w and bbox[1] >= y and bbox[3] <= y + h, "Glyph exceeds text box")
                positions.append((tx, ty, line))
            for tx, ty, line in positions:
                draw.text((tx, ty), line, font=font, fill=color, anchor="lt")
            return {"text": text, "font_size": chosen, "lines": lines, "box": list(box)}
        except ValueError:
            if chosen == (minimum or size):
                raise


def make_overlays(copy, logo):
    layouts = []
    overlays = []
    intro = Image.new("RGBA", (WIDTH, HEIGHT))
    draw = ImageDraw.Draw(intro)
    bounds(425, 345, 1165, 330)
    draw.rectangle((425, 345, 1589, 674), fill=FOREST)
    draw.rectangle((470, 364, 1544, 367), fill=GOLD)
    layouts.append(text_block(intro, copy["intro"]["subtitle"], (470, 390, 1075, 90), 70, bold=True, center=True))
    layouts.append(text_block(intro, copy["intro"]["title"], (470, 484, 1075, 62), 47, center=True))
    layouts.append(text_block(intro, copy["intro"]["sponsor_line"], (470, 559, 1075, 102), 38, max_lines=2, center=True))
    require(intro.getpixel((425, 345))[3] == 255 and intro.getpixel((0, 0))[3] == 0, "Intro opacity invalid")
    overlays.append(("intro.png", intro))
    for index, scene in enumerate(copy["scenes"], 1):
        image = Image.new("RGBA", (WIDTH, HEIGHT))
        draw = ImageDraw.Draw(image)
        draw.rectangle((0, 765, 1919, 1079), fill=FOREST)
        layouts.append(text_block(image, copy["closing"]["title"].upper(), (100, 793, 1720, 36), 24, bold=True, color=GOLD))
        draw.rectangle((100, 840, 1819, 842), fill=GOLD)
        layouts.append(text_block(image, scene["title"], (100, 866, 1720, 88), 60, bold=True, minimum=50))
        layouts.append(text_block(image, scene["subtitle"], (100, 970, 1720, 104), 42, max_lines=2))
        require(image.getpixel((0, 765))[3] == 255 and image.getpixel((0, 764))[3] == 0, "Footer opacity invalid")
        overlays.append((f"scene-{index}.png", image))
    closing = Image.new("RGBA", (WIDTH, HEIGHT), WHITE)
    with Image.open(logo) as original:
        require(original.size == (250, 250) and original.mode == "RGBA", "Unexpected genuine logo")
        closing.alpha_composite(original, (835, 110))
    layouts.append(text_block(closing, copy["closing"]["title"], (180, 409, 1560, 114), 84, bold=True, center=True, color=FOREST))
    layouts.append(text_block(closing, copy["closing"]["subtitle"], (180, 548, 1560, 70), 42, center=True, color=FOREST))
    ImageDraw.Draw(closing).rectangle((710, 653, 1209, 656), fill=GOLD)
    layouts.append(text_block(closing, copy["closing"]["cta"], (180, 705, 1560, 80), 46, bold=True, center=True, color=FOREST))
    layouts.append(text_block(closing, copy["closing"]["website"], (180, 821, 1560, 65), 36, center=True, color=FOREST))
    overlays.append(("closing.png", closing))
    require(closing.getchannel("A").getextrema() == (255, 255), "Closing must be fully opaque")
    return overlays, layouts


def execute(args, log=None):
    result = subprocess.run([str(FFMPEG), "-hide_banner", "-nostdin", "-filter_threads", "1", *map(str, args)], capture_output=True, check=False)
    if log:
        log.write_bytes(result.stderr)
    require(result.returncode == 0, result.stderr.decode("utf-8", errors="replace")[-4000:])
    return result.stdout


def atoms(data, start=0, end=None):
    end = len(data) if end is None else end
    while start < end:
        require(start + 8 <= end, "Truncated MP4 atom")
        size, kind = struct.unpack_from(">I4s", data, start)
        header = 8
        if size == 1:
            size = struct.unpack_from(">Q", data, start + 8)[0]
            header = 16
        elif size == 0:
            size = end - start
        require(size >= header and start + size <= end, "Invalid MP4 atom bounds")
        yield kind, start, start + header, start + size
        start += size


def find_atom(data, path, start=0, end=None):
    for kind, _, begin, finish in atoms(data, start, end):
        if kind == path[0]:
            return (begin, finish) if len(path) == 1 else find_atom(data, path[1:], begin, finish)
    raise ValueError("Missing MP4 atom: " + repr(path))


def inspect_mp4(path, clean=False):
    # Read only headers and moov; video payload is not loaded into memory.
    top = []
    moov = None
    with path.open("rb") as handle:
        end = path.stat().st_size
        while handle.tell() < end:
            position = handle.tell()
            header = handle.read(8)
            require(len(header) == 8, "Truncated top-level atom")
            size, kind = struct.unpack(">I4s", header)
            header_size = 8
            if size == 1:
                size = struct.unpack(">Q", handle.read(8))[0]
                header_size = 16
            if size == 0:
                size = end - position
            require(size >= header_size and position + size <= end, "Invalid container")
            top.append((kind.decode("ascii"), position))
            if kind == b"moov":
                moov = handle.read(size - header_size)
            handle.seek(position + size)
    require(moov is not None, "Missing moov")
    streams = []
    for kind, _, begin, finish in atoms(moov):
        if kind != b"trak":
            continue
        handler, _ = find_atom(moov, [b"mdia", b"hdlr"], begin, finish)
        stream_type = moov[handler + 8:handler + 12].decode("ascii")
        mdhd, _ = find_atom(moov, [b"mdia", b"mdhd"], begin, finish)
        version = moov[mdhd]
        timescale, duration = struct.unpack_from(">IQ" if version == 1 else ">II", moov, mdhd + (20 if version == 1 else 12))
        stsd, _ = find_atom(moov, [b"mdia", b"minf", b"stbl", b"stsd"], begin, finish)
        entry = stsd + 8
        codec = moov[entry + 4:entry + 8].decode("ascii")
        stsz, _ = find_atom(moov, [b"mdia", b"minf", b"stbl", b"stsz"], begin, finish)
        samples = struct.unpack_from(">I", moov, stsz + 8)[0]
        item = {"type": stream_type, "codec": codec, "samples": samples, "duration": duration / timescale}
        if stream_type == "vide":
            width, height = struct.unpack_from(">HH", moov, entry + 32)
            stts, _ = find_atom(moov, [b"mdia", b"minf", b"stbl", b"stts"], begin, finish)
            count = struct.unpack_from(">I", moov, stts + 4)[0]
            timings = [struct.unpack_from(">II", moov, stts + 8 + 8 * i) for i in range(count)]
            require(sum(n for n, _ in timings) == samples, "Sample timing/count mismatch")
            item.update(width=width, height=height, fps=timescale / timings[0][1], constant_rate=len({d for _, d in timings}) == 1)
            entry_end = entry + struct.unpack_from(">I", moov, entry)[0]
            item["sar"] = [1, 1]
            for child, _, payload, _ in atoms(moov, entry + 86, entry_end):
                if child == b"pasp":
                    item["sar"] = list(struct.unpack_from(">II", moov, payload))
        if stream_type == "soun":
            item["channels"] = struct.unpack_from(">H", moov, entry + 24)[0]
            item["sample_rate"] = struct.unpack_from(">I", moov, entry + 32)[0] >> 16
        streams.append(item)
    metadata = []

    def walk(start, end):
        containers = {b"trak", b"mdia", b"minf", b"stbl", b"udta", b"ilst", b"edts", b"dinf"}
        for kind, _, begin, finish in atoms(moov, start, end):
            if kind in (b"uuid", b"chpl", b"loci", b"gps "):
                metadata.append(kind.decode("latin1"))
            if kind == b"meta":
                walk(begin + 4, finish)
            elif kind in containers:
                walk(begin, finish)
            elif kind.startswith(b"\xa9") or kind == b"----":
                metadata.append(kind.decode("latin1"))
    walk(0, len(moov))
    if clean:
        require(all(name == "\xa9too" for name in metadata), "Unexpected/private metadata")
        require(not any(kind in ("uuid", "meta", "udta") for kind, _ in top), "Unexpected top-level metadata")
        require([k for k, _ in top].index("moov") < [k for k, _ in top].index("mdat"), "Not faststart")
    return {"streams": streams, "top_level": top, "metadata_atom_keys": metadata}


def check_video(info, audio):
    require(len(info["streams"]) == (2 if audio else 1), "Unexpected stream count")
    video = next(s for s in info["streams"] if s["type"] == "vide")
    require((video["codec"], video["width"], video["height"], video["samples"], video["fps"], video["constant_rate"], video["sar"]) == ("avc1", WIDTH, HEIGHT, FRAMES, FPS, True, [1, 1]), "Video contract mismatch")
    require(abs(video["duration"] - FRAMES / FPS) < 0.001, "Video duration mismatch")
    if audio:
        sound = next(s for s in info["streams"] if s["type"] == "soun")
        require((sound["codec"], sound["channels"], sound["sample_rate"]) == ("mp4a", 2, 44100), "Audio contract mismatch")
    return video


def audio_hash(path):
    return execute(["-v", "error", "-i", path, "-map", "0:a:0", "-c:a", "copy", "-f", "hash", "-hash", "sha256", "-"]).decode("ascii").strip()


def full_decode(path, audio, log):
    args = ["-v", "error", "-xerror", "-err_detect", "explode", "-threads", "2", "-i", path, "-map", "0:v:0"]
    if audio:
        args += ["-map", "0:a:0"]
    report = execute(args + ["-threads", "2", "-fps_mode", "passthrough", "-f", "framehash", "-hash", "sha256", "-"], log).decode("ascii")
    rows = [line.split(",") for line in report.splitlines() if line and not line.startswith("#")]
    video = [row for row in rows if int(row[0]) == 0]
    require(len(video) == FRAMES, "Full decode frame count mismatch")
    require([int(row[2]) for row in video] == list(range(FRAMES)), "Decoded timestamps are not continuous")
    require(all(int(row[3]) == 1 and int(row[4]) == WIDTH * HEIGHT * 3 // 2 for row in video), "Unexpected decoded frame format")
    log.with_suffix(".framehash").write_text(report, encoding="ascii")
    return {"decoded_video_frames": len(video), "decoded_audio_frames": len(rows) - len(video), "continuous_pts": True, "decode_errors": 0}


def frame(path, n, seek_enabled=True):
    seek = n // FPS if seek_enabled else 0
    data = execute(["-v", "error", "-threads", "2", "-ss", str(seek), "-i", path, "-vf", f"select=eq(n\\,{n - seek * FPS})", "-frames:v", "1", "-threads", "1", "-f", "image2pipe", "-c:v", "png", "-"])
    image = Image.open(io.BytesIO(data)).convert("RGB")
    require(image.size == (WIDTH, HEIGHT), "QA frame dimensions changed")
    return image


def extract_range(path, start, end, output, prefix):
    require(0 <= start < end <= FRAMES, "Invalid native sample interval")
    seek = start // FPS
    local_start, local_end = start - seek * FPS, end - seek * FPS
    execute(["-n", "-v", "error", "-threads", "2", "-ss", str(seek), "-i", path, "-vf", f"select=gte(n\\,{local_start})*lt(n\\,{local_end})", "-fps_mode", "passthrough", "-frames:v", str(end - start), "-threads", "1", "-start_number", str(start), output / (prefix + "-%04d.png")])
    require(len(list(output.glob(prefix + "-*.png"))) == end - start, "Native sample count mismatch")


def chronological_sheet(paths, target, columns=6):
    sheet = Image.new("RGB", (columns * 400, math.ceil(len(paths) / columns) * 253), "#eeeeee")
    for index, path in enumerate(paths):
        with Image.open(path) as original:
            require(original.size == (WIDTH, HEIGHT), "Non-native sample")
            thumb = original.convert("RGB").resize((400, 225), Image.Resampling.LANCZOS)
        x, y = (index % columns) * 400, (index // columns) * 253
        sheet.paste(thumb, (x, y))
        n = int(path.stem.rsplit("-", 1)[1])
        ImageDraw.Draw(sheet).text((x + 8, y + 231), f"frame {n} | {n / FPS:.6f}s", fill="black", font=ImageFont.truetype(str(FONTS["regular"]), 16))
    sheet.save(target, quality=94)


def inspect_boundaries(output):
    require(output.resolve().is_relative_to(BASE.resolve()) and output.resolve() != BASE.resolve(), "Unbounded inspection output")
    require(not output.exists(), "No-overwrite: inspection directory exists")
    source_guard(SOURCE)
    output.mkdir(parents=True, exist_ok=False)
    for label, start, end in [("closing", 965, 1003), ("opening", 115, 135)]:
        extract_range(SOURCE, start, end, output, label)
        chronological_sheet(sorted(output.glob(label + "-*.png")), output / (label + "-chronological.jpg"))
    source_guard(SOURCE)
    print(json.dumps({"source_boundary_review": str(output), "source_hash_verified": True, "closing_frames": [965, 1002], "opening_frames": [115, 134]}))


def self_test(copy):
    import unittest
    from unittest.mock import patch

    class Tests(unittest.TestCase):
        def test_partition(self):
            ranges = intervals()
            self.assertEqual(len(ranges), 7)
            for n in range(FOOTER_START, CLOSING_START):
                self.assertEqual(sum(start <= n < end for start, end in ranges), 1)
            self.assertEqual(ranges[0][0], 120)
            self.assertEqual(ranges[-1][1], 978)

        def test_pinned_closing_and_opening_coverage(self):
            self.assertEqual(SOURCE_ENDING_FIRST_CN, 978)
            self.assertEqual(CLOSING_START, SOURCE_ENDING_FIRST_CN)
            self.assertEqual(CUES[-1], CLOSING_START)
            self.assertEqual(SOURCE_OPENING_LAST_CN, 124)
            self.assertGreater(INTRO_END, SOURCE_OPENING_LAST_CN)
            self.assertLess(CLOSING_START - 1, SOURCE_ENDING_FIRST_CN)
            for n in range(SOURCE_ENDING_FIRST_CN, FRAMES):
                self.assertTrue(n >= CLOSING_START)
                self.assertFalse(any(a <= n < b for a, b in intervals()))

        def test_ffmpeg_zero_based_enable_counter(self):
            raw = execute(["-v", "error", "-f", "lavfi", "-i", "color=black:s=8x8:r=30:d=0.1", "-f", "lavfi", "-i", "color=red:s=8x8:r=30:d=0.1", "-filter_complex_threads", "1", "-filter_complex", "[0:v][1:v]overlay=enable='gte(n,1)*lt(n,2)'", "-frames:v", "3", "-threads", "1", "-pix_fmt", "rgb24", "-f", "rawvideo", "-"])
            self.assertEqual(len(raw), 3 * 8 * 8 * 3)
            self.assertEqual(raw[:3], b"\x00\x00\x00")
            self.assertGreater(raw[192], 240)
            self.assertEqual(raw[384:387], b"\x00\x00\x00")

        def test_bounds(self):
            bounds(425, 345, 1165, 330)
            bounds(0, 765, 1920, 315)
            for rect in [(0, 765, 1920, 316), (-1, 0, 10, 10), (0, 0, 0, 10)]:
                with self.assertRaises(ValueError):
                    bounds(*rect)

        def test_cjk(self):
            self.assertTrue(is_cjk(chr(0x4E2D)))
            self.assertFalse(any(is_cjk(s) for s in strings(copy)))

        def test_source_change_guard(self):
            with patch(__name__ + ".sha256", return_value="0" * 64):
                with self.assertRaises(ValueError):
                    source_guard(SOURCE)
            with self.assertRaises(ValueError):
                source_guard(ROOT / "wrong.mp4")

        def test_font_layout_opacity(self):
            glyph_check(copy)
            images, layout = make_overlays(copy, LOGO)
            self.assertEqual(len(images), 9)
            self.assertEqual(len(layout), 28)
            self.assertTrue(all(image.size == (WIDTH, HEIGHT) for _, image in images))

    result = unittest.TextTestRunner(verbosity=2).run(unittest.defaultTestLoader.loadTestsFromTestCase(Tests))
    require(result.wasSuccessful(), "Self-tests failed")
    return {"tests": result.testsRun, "failures": len(result.failures), "errors": len(result.errors)}


def build(output, copy_path):
    copy = load_copy(copy_path)
    tests = self_test(copy)
    require(output.resolve().is_relative_to(BASE.resolve()) and output.resolve() != BASE.resolve(), "Output must be a new child directory of the bounded output root")
    require(not output.exists(), "No-overwrite: output directory already exists; inspect ownership manifest and select a new run directory")
    source_guard(SOURCE)
    source_info = inspect_mp4(SOURCE)
    check_video(source_info, True)
    output.mkdir(parents=True, exist_ok=False)
    manifest_path = output / "ownership-manifest.json"
    manifest = {"owner": OWNERSHIP, "status": "IN_PROGRESS_LOCAL", "rights": "NOT_CLEARED_FOR_YOUTUBE", "source_sha256": SOURCE_HASH, "copy_sha256": sha256(copy_path), "script_sha256": sha256(Path(__file__)), "ffmpeg_sha256": sha256(FFMPEG), "logo_sha256": sha256(LOGO), "font_sha256": {key: sha256(path) for key, path in FONTS.items()}, "files": {}, "overwrite_policy": "Never overwrite or delete existing files. No repeat-owned overwrite mode.", "rejected_prior_run": {"directory": "render-v1", "reason": "Central Chinese ending leaked above footer before old frame-996 closing. Confirmed at frame 995; v1 is not visually approved and must not be published."}}

    def save_manifest():
        manifest_path.write_text(json.dumps(manifest, ensure_ascii=True, indent=2) + "\n", encoding="utf-8")

    save_manifest()
    try:
        local_source = output / "source.mp4"
        shutil.copyfile(SOURCE, local_source)
        require(sha256(local_source) == SOURCE_HASH, "Local source copy hash mismatch")
        overlays, layouts = make_overlays(copy, LOGO)
        for name, image in overlays:
            image.save(output / name, optimize=False, compress_level=6)
        (output / "layout-report.json").write_text(json.dumps(layouts, ensure_ascii=True, indent=2), encoding="utf-8")
        command = ["-n", "-v", "warning", "-threads", "2", "-i", local_source]
        for name, _ in overlays:
            command += ["-loop", "1", "-framerate", str(FPS), "-threads", "1", "-i", output / name]
        filters = ["[0:v]setsar=1[v0]"]
        enables = [f"lt(n,{INTRO_END})"] + [f"gte(n,{start})*lt(n,{end})" for start, end in intervals()] + [f"gte(n,{CLOSING_START})"]
        for index, enable in enumerate(enables, 1):
            filters.append(f"[v{index - 1}][{index}:v]overlay=x=0:y=0:enable='{enable}':shortest=1:eof_action=pass:format=yuv420[v{index}]")
        audio_output = output / "park-hu-original-audio.mp4"
        command += ["-filter_complex_threads", "1", "-filter_complex", ";".join(filters), "-map", "[v9]", "-map", "0:a:0", "-map_metadata", "-1", "-map_metadata:s", "-1", "-map_chapters", "-1", "-c:v", "libx264", "-preset", "medium", "-crf", "17", "-threads", "2", "-pix_fmt", "yuv420p", "-fps_mode", "passthrough", "-c:a", "copy", "-metadata", "creation_time=", "-metadata:s:v:0", "handler_name=VideoHandler", "-metadata:s:a:0", "handler_name=SoundHandler", "-movflags", "+faststart", audio_output]
        execute(command, output / "render.log")
        silent_output = output / "park-hu-silent.mp4"
        execute(["-n", "-v", "warning", "-i", audio_output, "-map", "0:v:0", "-c:v", "copy", "-an", "-map_metadata", "-1", "-map_metadata:s", "-1", "-map_chapters", "-1", "-metadata", "creation_time=", "-metadata:s:v:0", "handler_name=VideoHandler", "-movflags", "+faststart", silent_output], output / "silent-remux.log")
        verification = {"tests": tests, "cue_method": "Parent visually selected approximate scene cuts; ending onset corrected by native per-frame review. No automatic cut claim.", "cue_frames": CUES, "footer_protection_starts_at_frame": FOOTER_START, "closing_coverage": {"source_first_ending_text_frame": SOURCE_ENDING_FIRST_CN, "first_covered_frame": CLOSING_START, "seconds": CLOSING_START / FPS, "last_preserved_upper_footage_frame": CLOSING_START - 1, "source_review_frames": [965, 1002]}, "opening_coverage": {"source_last_central_text_frame": SOURCE_OPENING_LAST_CN, "intro_end_exclusive": INTRO_END, "source_review_frames": [115, 134]}, "rejected_prior_run": manifest["rejected_prior_run"], "outputs": {}}
        original_audio_hash = audio_hash(local_source)
        for path, audio in [(audio_output, True), (silent_output, False)]:
            info = inspect_mp4(path, clean=True)
            check_video(info, audio)
            decoded = full_decode(path, audio, output / (path.stem + "-decode.log"))
            if audio:
                require(audio_hash(path) == original_audio_hash, "Copied AAC packet hash differs")
            verification["outputs"][path.name] = {"sha256": sha256(path), "container": info, "decode": decoded, "aac_packet_copy_verified": audio}
        video_hashes = [line for line in (output / "park-hu-original-audio-decode.framehash").read_text().splitlines() if line.startswith("0,")]
        silent_hashes = [line for line in (output / "park-hu-silent-decode.framehash").read_text().splitlines() if line.startswith("0,")]
        require(video_hashes == silent_hashes, "Silent derivative changed video frames")
        print("Render and full decode complete; producing native QA samples.", flush=True)
        qa = output / "qa"
        qa.mkdir()
        source_qa = output / "qa-source-boundaries"
        source_qa.mkdir()
        for label, start, end in [("closing", 965, 1003), ("opening", 115, 135)]:
            extract_range(local_source, start, end, source_qa, label)
            extract_range(audio_output, start, end, qa, label)
            chronological_sheet(sorted(source_qa.glob(label + "-*.png")), output / ("source-" + label + "-chronological.jpg"))
            chronological_sheet(sorted(qa.glob(label + "-*.png")), output / ("rendered-" + label + "-chronological.jpg"))
        seek_checks = []
        for n in [INTRO_END, SOURCE_ENDING_FIRST_CN]:
            direct = frame(local_source, n, seek_enabled=False)
            prefix = "opening" if n == INTRO_END else "closing"
            with Image.open(source_qa / f"{prefix}-{n:04d}.png") as sampled:
                require(sampled.convert("RGB").tobytes() == direct.tobytes(), "Seek-based frame indexing mismatch")
            seek_checks.append({"frame": n, "native_sample_matches_full_from_zero_decode": True})
        verification["native_index_checks"] = seek_checks
        centers = [63] + [(a + b) // 2 for a, b in intervals()] + [(CLOSING_START + FRAMES - 1) // 2]
        boundary_samples = sorted({n for boundary in [FOOTER_START, *CUES] for n in [boundary - 1, boundary, boundary + 1]})
        samples = sorted(set(centers + boundary_samples + [124, 125, 975, 977, 978, 979, 990, 995, 996, 1005, FRAMES - 1]))
        thumbs = []
        preservation = []
        import numpy as np
        closing_reference = np.asarray(overlays[-1][1].convert("RGB"), dtype=np.int16)
        closing_checks = []
        for n in samples:
            rendered = frame(audio_output, n)
            rendered.save(qa / f"frame-{n:04d}.png")
            thumb = rendered.resize((480, 270), Image.Resampling.LANCZOS)
            tile = Image.new("RGB", (480, 300), "white")
            tile.paste(thumb, (0, 0))
            ImageDraw.Draw(tile).text((8, 277), f"frame {n} | {n / FPS:.3f}s", fill="black", font=ImageFont.truetype(str(FONTS["regular"]), 17))
            thumbs.append(tile)
            if n >= CLOSING_START:
                closing_mae = float(np.abs(np.asarray(rendered, dtype=np.int16) - closing_reference).mean())
                require(closing_mae < 1.0, "Rendered closing does not match opaque branded graphic")
                closing_checks.append({"frame": n, "branded_closing_rgb_mae": round(closing_mae, 5)})
            if n < CLOSING_START:
                original = frame(local_source, n)
                a = np.asarray(original, dtype=np.int16)
                b = np.asarray(rendered, dtype=np.int16)
                mask = np.ones((HEIGHT, WIDTH), dtype=bool)
                if n < INTRO_END:
                    mask[343:677, 423:1592] = False
                if n >= FOOTER_START:
                    mask[763:, :] = False
                mae = float(np.abs(a[mask] - b[mask]).mean())
                require(mae < 4.0, "Unmasked frame differs unexpectedly")
                preservation.append({"frame": n, "outside_mask_rgb_mae": round(mae, 5)})
        columns = 4
        sheet = Image.new("RGB", (columns * 480, math.ceil(len(thumbs) / columns) * 300), "#eeeeee")
        for index, tile in enumerate(thumbs):
            sheet.paste(tile, ((index % columns) * 480, (index // columns) * 300))
        sheet.save(output / "contact-sheet.jpg", quality=94)
        # Center samples remain native 1920x1080 in a separate lossless sheet.
        native = Image.new("RGB", (WIDTH * 3, HEIGHT * 3), "white")
        for index, n in enumerate(centers):
            with Image.open(qa / f"frame-{n:04d}.png") as image:
                native.paste(image, ((index % 3) * WIDTH, (index // 3) * HEIGHT))
        native.save(output / "native-center-contact-sheet.png", compress_level=6)
        source_guard(SOURCE)
        verification.update(source_unchanged=True, original_aac_packet_sha256=original_audio_hash, silent_video_framehash_matches=True, qa_native_frame_samples=samples, qa_center_frames=centers, preservation_checks=preservation, closing_graphic_checks=closing_checks, chronological_native_boundary_samples={"opening": [115, 134], "closing": [965, 1002]}, visual_review="V1 rejected for visible Chinese ending. V2 covers the source ending from its first visible frame 978. Native chronological source/render samples retained for independent review; no automatic OCR or complete visual clearance claim.")
        (output / "verification.json").write_text(json.dumps(verification, ensure_ascii=True, indent=2) + "\n", encoding="utf-8")
        manifest["status"] = "PREPARED_LOCAL"
        manifest["files"] = {str(path.relative_to(output)): {"sha256": sha256(path), "bytes": path.stat().st_size} for path in sorted(output.rglob("*")) if path.is_file() and path != manifest_path}
        save_manifest()
        print(json.dumps({"output_directory": str(output), "status": manifest["status"], "outputs": {name: {"sha256": value["sha256"], "frames": value["decode"]["decoded_video_frames"]} for name, value in verification["outputs"].items()}, "tests": tests}, indent=2), flush=True)
    except BaseException as error:
        manifest["status"] = "FAILED_LOCAL"
        manifest["failure"] = str(error)
        manifest["files"] = {str(path.relative_to(output)): {"sha256": sha256(path), "bytes": path.stat().st_size} for path in sorted(output.rglob("*")) if path.is_file() and path != manifest_path}
        save_manifest()
        raise


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--copy", type=Path, default=COPY)
    parser.add_argument("--output-dir", type=Path, default=BASE / "render-v2")
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--inspect-boundaries", action="store_true")
    args = parser.parse_args()
    require(args.copy.resolve() == COPY.resolve(), "Copy input is limited to the owned JSON")
    if args.inspect_boundaries:
        inspect_boundaries(args.output_dir)
    elif args.self_test:
        print(json.dumps(self_test(load_copy(args.copy))))
    else:
        build(args.output_dir, args.copy)


if __name__ == "__main__":
    main()
