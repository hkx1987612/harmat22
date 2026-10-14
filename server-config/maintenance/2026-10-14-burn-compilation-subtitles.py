"""Bounded offline Hungarian subtitle burner: render|qa|all [--self-test].

Writes only inside subtitled-v1/render and subtitled-v1/qa. Exact parent SRT
and manifest are required. No narration generation, uploads or publication.
render registers a partial; qa promotes it only after technical checks.
"""

import argparse
import copy
import hashlib
import json
import math
import re
import struct
import subprocess
import sys
import tempfile
import unicodedata
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "outputs/2026-10-14-site-videos"
OUT = BASE / "subtitled-v1"
RENDER = OUT / "render"
QA = OUT / "qa"
SOURCE = BASE / "narrated-local-v1/harmat-2026-10-13-compilation-hu.mp4"
SOURCE_PIN = "f73755ed82ab62ae4adf87daba394c9736790e03cbe336893cd72c3bc9d72a84"
SRT = OUT / "harmat-2026-10-13.hu.srt"
MANIFEST = OUT / "subtitle-cues.json"
FINAL = RENDER / "harmat-2026-10-13-compilation-hu-subtitled.mp4"
STATE = RENDER / "render-state.json"
REPORT = QA / "qa-manifest.json"
FFMPEG = Path("C:/Users/Administrator/AppData/Roaming/Python/Python312/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v7.1.exe")
FONT = Path("C:/Windows/Fonts/arialbd.ttf")
FPS, FRAMES, TOTAL = 30, 2679, 89.3
WIDTH, HEIGHT = 1280, 720
FONT_SIZE = 30
BAND = (90, 446, 1190, 538)
MAX_TEXT_WIDTH = 1056
PASS = "TECHNICAL_QA_PASS_PARENT_VISUAL_REVIEW_REQUIRED_NOT_PUBLISHED"
STATUS_READY = {"READY", "APPROVED", "VALIDATED", "READY_FOR_BURN", "READY_FOR_RENDER",
                "READY_FOR_SUBTITLE_BURN", "VALIDATED_READY_FOR_BURN",
                "REFERENCE_CAPTIONS_ASR_TIMING_READY_VISUAL_QA_REQUIRED"}


def require(ok, message):
    if not ok:
        raise ValueError(message)


def sha(path):
    with Path(path).open("rb") as handle:
        return hashlib.file_digest(handle, "sha256").hexdigest()


def owned(path):
    path = Path(path).resolve()
    require(any(path.is_relative_to(p.resolve()) for p in (RENDER, QA)),
            f"Write outside owned render/qa directories: {path}")
    return path


def write(path, data):
    with owned(path).open("xb") as handle:
        handle.write(data)


def save(path, value):
    write(path, (json.dumps(value, indent=2, ensure_ascii=True, allow_nan=False) + "\n").encode())


def read(path):
    def no_duplicates(pairs):
        result = {}
        for key, value in pairs:
            require(key not in result, f"Duplicate JSON key: {key}")
            result[key] = value
        return result
    return json.loads(Path(path).read_text(encoding="utf-8-sig"),
                      object_pairs_hook=no_duplicates)


def seconds(value):
    require(isinstance(value, (int, float)) and not isinstance(value, bool)
            and math.isfinite(value), "Nonfinite/nonnumeric cue timing")
    return float(value)


def text_check(text):
    require(isinstance(text, str) and text.strip() == text and text,
            "Empty or whitespace-padded cue")
    require(unicodedata.normalize("NFC", text) == text, "Non-NFC subtitle text")
    require(len(text.splitlines()) <= 2, "More than two subtitle lines")
    require(not any(c in text for c in "<>{}\\"), "Subtitle markup/ASS injection")
    require(not any(unicodedata.category(c).startswith("C") for c in text if c != "\n"),
            "Control character in subtitle")
    require(not re.search(r"[\u3400-\u9fff\uf900-\ufaff\ufffd]", text),
            "CJK/replacement character in subtitle")
    require(not re.search(r"\b(?:placeholder|lorem|ipsum|todo|sample text)\b", text, re.I),
            "Placeholder subtitle")
    require(not re.search(r"[\u00c3\u00c2]|\u00c5[\u0090\u0091\u00b1]", text), "Mojibake subtitle")
    return text


def parse_srt(raw):
    text = raw.decode("utf-8-sig").replace("\r\n", "\n")
    require("\r" not in text, "Malformed SRT line endings")
    blocks = re.split(r"\n\n+", text.strip())
    cues = []
    pattern = r"(\d{2}):(\d{2}):(\d{2}),(\d{3}) --> (\d{2}):(\d{2}):(\d{2}),(\d{3})"
    for block in blocks:
        lines = block.split("\n")
        require(3 <= len(lines) <= 4 and lines[0] == str(len(cues) + 1), "Invalid SRT index/line count")
        match = re.fullmatch(pattern, lines[1])
        require(match is not None, "Invalid SRT timestamp")
        parts = list(map(int, match.groups()))
        require(all(parts[i] < 60 for i in (1, 2, 5, 6)), "Invalid timestamp minute/second")
        start = parts[0] * 3600 + parts[1] * 60 + parts[2] + parts[3] / 1000
        end = parts[4] * 3600 + parts[5] * 60 + parts[6] + parts[7] / 1000
        cues.append({"index": len(cues) + 1, "start": start, "end": end,
                     "text": text_check("\n".join(lines[2:]))})
    return cues


def validate_manifest(manifest, srt_cues, srt_hash):
    require(manifest.get("status") in STATUS_READY, "Parent cue manifest is not explicitly ready/approved")
    require(manifest.get("source_sha256") == SOURCE_PIN, "Manifest source pin mismatch")
    require(manifest.get("srt_sha256") == srt_hash, "Manifest SRT pin mismatch")
    require(isinstance(manifest.get("limits"), (dict, list)), "Missing parent cue limits")
    cues = manifest.get("cues")
    require(isinstance(cues, list) and 1 <= len(cues) <= 100 and len(cues) == len(srt_cues),
            "Unexpected/missing cue count")
    previous_end = 0.0
    for i, (cue, parsed) in enumerate(zip(cues, srt_cues), 1):
        require(type(cue.get("index")) is int and cue["index"] == i, "Cue order/index mismatch")
        start, end = seconds(cue.get("start")), seconds(cue.get("end"))
        require(0 <= start < end <= TOTAL and start >= previous_end, "Cue overlap/order/duration")
        require(end - start >= 1 / FPS, "Cue too short to display a video frame")
        require(abs(start - parsed["start"]) <= 0.000501
                and abs(end - parsed["end"]) <= 0.000501, "SRT/manifest timing mismatch")
        require(text_check(cue.get("text")) == parsed["text"], "SRT/manifest exact text mismatch")
        previous_end = end
    # The rendered SRT millisecond intervals must also be nonoverlapping.
    require(all(a["end"] <= b["start"] for a, b in zip(srt_cues, srt_cues[1:])), "Rounded SRT overlap")
    return srt_cues


def layout(text):
    font = ImageFont.truetype(str(FONT), FONT_SIZE)
    clean = " ".join(text.split())
    words = clean.split(" ")
    require(all(font.getlength(w) <= MAX_TEXT_WIDTH for w in words), "Unbreakable word exceeds safe width")
    if font.getlength(clean) <= MAX_TEXT_WIDTH:
        lines = [clean]
    else:
        choices = []
        for i in range(1, len(words)):
            pair = [" ".join(words[:i]), " ".join(words[i:])]
            widths = [font.getlength(line) for line in pair]
            if max(widths) <= MAX_TEXT_WIDTH:
                choices.append((abs(widths[0] - widths[1]), i, pair))
        require(choices, "Subtitle does not fit two lines at 30px; parent must split cue")
        lines = min(choices)[2]
    require(" ".join(lines) == clean and len(lines) <= 2, "Wrapping changed subtitle text")
    return {"lines": lines, "font_size": FONT_SIZE,
            "pillow_glyph_bounds": [list(font.getbbox(line)) for line in lines],
            "advance_widths": [font.getlength(line) for line in lines]}


def inputs():
    require(SRT.is_file() and MANIFEST.is_file(), "Waiting for parent SRT and subtitle-cues.json")
    pins = {"source_sha256": sha(SOURCE), "srt_sha256": sha(SRT),
            "manifest_sha256": sha(MANIFEST), "font_sha256": sha(FONT),
            "ffmpeg_sha256": sha(FFMPEG), "script_sha256": sha(Path(__file__))}
    require(pins["source_sha256"] == SOURCE_PIN, "Narrated source changed")
    manifest = read(MANIFEST)
    cues = validate_manifest(manifest, parse_srt(SRT.read_bytes()), pins["srt_sha256"])
    layouts = [layout(c["text"]) for c in cues]
    require(sha(SRT) == pins["srt_sha256"] and sha(MANIFEST) == pins["manifest_sha256"],
            "Parent subtitles changed during validation")
    return {"pins": pins, "cues": cues, "layouts": layouts, "parent_limits": manifest["limits"]}


def ass_time(seconds_value):
    # libass uses centiseconds. Raster samples below check the actual 30fps cue boundaries.
    centis = int(math.floor(seconds_value * 100 + 0.5))
    hours, rest = divmod(centis, 360000)
    minutes, rest = divmod(rest, 6000)
    seconds_part, rest = divmod(rest, 100)
    return f"{hours}:{minutes:02}:{seconds_part:02}.{rest:02}"


def ass_document(cues, layouts, isolated=False):
    header = """[Script Info]
ScriptType: v4.00+
PlayResX: 1280
PlayResY: 720
WrapStyle: 2
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Caption,Arial,30,&H00FFFFFF,&H00FFFFFF,&H401C1C1C,&H401C1C1C,-1,0,0,0,100,100,0,0,3,5,0,2,100,100,190,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
    events = []
    for i, (cue, item) in enumerate(zip(cues, layouts)):
        start, end = (i, i + 1) if isolated else (cue["start"], cue["end"])
        body = r"{\an2\pos(640,530)\q2}" + r"\N".join(item["lines"])
        events.append(f"Dialogue: 0,{ass_time(start)},{ass_time(end)},Caption,,0,0,0,,{body}")
    return header + "\n".join(events) + "\n"


def run(args, directory, name, binary=False):
    command = [str(FFMPEG), "-hide_banner", "-nostdin", *args]
    save(directory / f"{name}-command.json", command)
    proc = subprocess.run(command, cwd=directory, capture_output=True, timeout=900)
    write(directory / f"{name}.log", proc.stderr)
    if not binary:
        write(directory / f"{name}.stdout", proc.stdout)
    require(proc.returncode == 0, f"FFmpeg failed ({proc.returncode}); see {directory / (name + '.log')}")
    return proc.stdout if binary else proc.stdout.decode("utf-8", errors="replace"), proc.stderr.decode("utf-8", errors="replace")


def filters_check(directory):
    stdout, _ = run(["-filters"], directory, "available-filters")
    require(re.search(r"\bass\s+V->V", stdout), "Bundled libass filter unavailable; no render")


def check_bounds(bounds, message):
    require(bounds is not None and BAND[0] <= bounds[0] < bounds[2] <= BAND[2]
            and BAND[1] <= bounds[1] < bounds[3] <= BAND[3], message)


def typography(provenance, directory):
    owned(directory / "fonts").mkdir()
    write(directory / "fonts/font-arial-bold.ttf", FONT.read_bytes())
    write(directory / "typography.ass", ass_document(provenance["cues"], provenance["layouts"], True).encode("utf-8"))
    count = len(provenance["cues"])
    raw, log = run(["-f", "lavfi", "-i", f"color=c=black:s=1280x720:r=1:d={count}",
                    "-vf", "ass=typography.ass:fontsdir=fonts:shaping=complex", "-frames:v", str(count),
                    "-pix_fmt", "gray", "-f", "rawvideo", "-"], directory, "typography-raster", True)
    require("fontselect:" in log and not re.search(r"failed|not found|glyph.*missing", log, re.I),
            "Font selection/glyph rendering failed")
    require(len(raw) == count * WIDTH * HEIGHT, "Incomplete typography raster")
    checks = []
    for i, (cue, item) in enumerate(zip(provenance["cues"], provenance["layouts"])):
        frame = Image.frombytes("L", (WIDTH, HEIGHT), raw[i * WIDTH * HEIGHT:(i + 1) * WIDTH * HEIGHT])
        full_bounds = frame.point(lambda p: 255 if p > 2 else 0).getbbox()
        glyph_bounds = frame.point(lambda p: 255 if p > 210 else 0).getbbox()
        check_bounds(full_bounds, f"Actual libass text/backing escapes safe band: cue {i + 1} {full_bounds}")
        check_bounds(glyph_bounds, f"Empty/out-of-band actual glyphs: cue {i + 1}")
        png = directory / f"typography-{i + 1:03}.png"
        with owned(png).open("xb") as handle:
            frame.save(handle, format="PNG")
        checks.append({"index": cue["index"], "text": cue["text"], **item,
                       "actual_backing_bounds": full_bounds, "actual_white_glyph_bounds": glyph_bounds,
                       "raster": str(png), "raster_sha256": sha(png)})
    save(directory / "typography-bounds.json", {"font": str(FONT), "font_sha256": sha(FONT),
         "safe_band": BAND, "max_width": 1080, "font_size": FONT_SIZE, "cues": checks})
    return checks


def boxes(data, start=0, end=None):
    end = len(data) if end is None else end
    while start < end:
        require(start + 8 <= end, "Truncated MP4 box")
        size, kind = struct.unpack_from(">I4s", data, start)
        header = 8
        if size == 1:
            require(start + 16 <= end, "Truncated large box")
            size = struct.unpack_from(">Q", data, start + 8)[0]
            header = 16
        if size == 0:
            size = end - start
        require(size >= header and start + size <= end, "Invalid MP4 box")
        yield kind.decode("ascii"), start + header, start + size
        start += size


def container(path):
    data = Path(path).read_bytes()
    top = list(boxes(data))
    names = [b[0] for b in top]
    require(names.count("moov") == 1 and "mdat" in names
            and names.index("moov") < names.index("mdat"), "Missing faststart")
    moov = next(b for b in top if b[0] == "moov")
    streams = []
    for kind, a, b in boxes(data, moov[1], moov[2]):
        if kind != "trak":
            continue
        stream = {}
        for name, c, d in boxes(data, a, b):
            if name == "tkhd":
                stream["dimensions"] = [struct.unpack_from(">I", data, d - 8)[0] // 65536,
                                        struct.unpack_from(">I", data, d - 4)[0] // 65536]
            if name != "mdia":
                continue
            for field, e, f in boxes(data, c, d):
                if field == "hdlr":
                    stream["type"] = data[e + 8:e + 12].decode("ascii")
                if field == "mdhd":
                    require(data[e] in (0, 1), "Unknown mdhd")
                    offset = 20 if data[e] else 12
                    scale = struct.unpack_from(">I", data, e + offset)[0]
                    duration = struct.unpack_from(">Q" if data[e] else ">I", data, e + offset + 4)[0]
                    require(scale > 0, "Invalid timescale")
                    stream.update(timescale=scale, duration=duration / scale)
                if field != "minf":
                    continue
                for sub, g, h in boxes(data, e, f):
                    if sub != "stbl":
                        continue
                    for table, j, k in boxes(data, g, h):
                        if table == "stsz":
                            stream["samples"] = struct.unpack_from(">I", data, j + 8)[0]
                        if table == "stts":
                            count = struct.unpack_from(">I", data, j + 4)[0]
                            require(j + 8 + count * 8 <= k, "Truncated sample timing")
                            stream["timing"] = [list(struct.unpack_from(">II", data, j + 8 + n * 8))
                                                for n in range(count)]
                        if table == "stsd":
                            require(struct.unpack_from(">I", data, j + 4)[0] == 1, "Multiple sample descriptions")
                            entries = list(boxes(data, j + 8, k))
                            require(len(entries) == 1, "Invalid sample description")
                            codec, payload, end = entries[0]
                            stream["codec"] = codec
                            if codec == "avc1":
                                for tag, x, y in boxes(data, payload + 78, end):
                                    if tag == "pasp":
                                        stream["sar"] = list(struct.unpack_from(">II", data, x))
                            if codec == "mp4a":
                                stream["sample_rate"] = struct.unpack_from(">I", data, payload + 24)[0] / 65536
        streams.append(stream)
    videos = [s for s in streams if s.get("type") == "vide"]
    audios = [s for s in streams if s.get("type") == "soun"]
    require(len(streams) == 2 and len(videos) == len(audios) == 1, "Unexpected streams")
    video, audio = videos[0], audios[0]
    require(video["codec"] == "avc1" and video["dimensions"] == [WIDTH, HEIGHT]
            and video["samples"] == FRAMES and abs(video["duration"] - TOTAL) < 1e-6
            and video.get("sar", [1, 1]) == [1, 1], "Video dimensions/count/duration/SAR changed")
    require(len(video["timing"]) == 1 and video["timing"][0][0] == FRAMES
            and abs(video["timescale"] / video["timing"][0][1] - FPS) < 1e-6, "Video cadence changed")
    require(audio["codec"] == "mp4a" and audio["sample_rate"] == 48000
            and abs(audio["duration"] - TOTAL) < 0.05, "AAC format/duration changed")
    return {"faststart": True, "streams": streams}


def registered(provenance):
    if FINAL.exists():
        require(REPORT.is_file(), "Unknown existing final preserved; refusing reuse")
        report = read(REPORT)
        require(report.get("status") == PASS and report["inputs_before"] == provenance
                and report["inputs_after"] == provenance and report["output"]["sha256"] == sha(FINAL),
                "Existing final/report is stale or modified")
        return "verified"
    require(not REPORT.exists(), "Existing final QA report without final output")
    if STATE.exists():
        state = read(STATE)
        require(state["inputs"] == provenance, "Changed source/SRT/font/script since render")
        for item in state["artifacts"]:
            require(sha(owned(item["path"])) == item["sha256"], "Registered artifact changed")
        require(sha(owned(state["partial"])) == state["partial_sha256"], "Partial changed")
        return state
    return None


def render(provenance):
    existing = registered(provenance)
    if existing is not None:
        print("Existing hash-verified render registered; no overwrite", flush=True)
        return
    container(SOURCE)
    directory = Path(tempfile.mkdtemp(prefix="attempt-", dir=RENDER))
    filters_check(directory)
    checks = typography(provenance, directory)
    ass = directory / "captions.ass"
    write(ass, ass_document(provenance["cues"], provenance["layouts"]).encode("utf-8"))
    partial = directory / "subtitled.partial.mp4"
    run(["-n", "-i", str(SOURCE), "-vf", "ass=captions.ass:fontsdir=fonts:shaping=complex",
         "-map", "0:v:0", "-map", "0:a:0", "-sn", "-dn", "-map_chapters", "-1",
         "-map_metadata", "-1", "-metadata:s:a:0", "language=hun", "-c:v", "libx264",
         "-preset", "medium", "-crf", "18", "-pix_fmt", "yuv420p", "-threads", "4",
         "-fps_mode", "passthrough", "-c:a", "copy", "-movflags", "+faststart",
         "-color_primaries", "bt709", "-color_trc", "bt709", "-colorspace", "bt709",
         str(partial)], directory, "render")
    require(inputs() == provenance, "Inputs changed during rendering")
    artifacts = [ass, directory / "typography.ass", directory / "fonts/font-arial-bold.ttf",
                 directory / "typography-bounds.json"]
    save(STATE, {"inputs": provenance, "partial": str(partial), "partial_sha256": sha(partial),
                 "artifacts": [{"path": str(p), "sha256": sha(p)} for p in artifacts],
                 "typography": checks})
    print(json.dumps({"render": "REGISTERED_PARTIAL_NOT_PUBLISHED", "partial": str(partial)}), flush=True)


def hash_stream(path, directory, label):
    output, _ = run(["-v", "error", "-i", str(path), "-map", "0:a:0", "-c:a", "copy",
                     "-f", "streamhash", "-hash", "sha256", "-"], directory, label + "-aac-streamhash")
    match = re.fullmatch(r"0,a,SHA256=([a-f0-9]{64})\s*", output)
    require(match is not None, "Missing AAC streamhash")
    packets, _ = run(["-v", "error", "-i", str(path), "-map", "0:a:0", "-c:a", "copy",
                     "-f", "framehash", "-hash", "sha256", "-"], directory, label + "-aac-packets")
    lines = [line for line in packets.splitlines() if not line.startswith("#") and line.strip()]
    require(lines and all(re.search(r"[a-f0-9]{64}$", line) for line in lines), "Missing AAC packet hashes")
    timebase = [line for line in packets.splitlines() if line.startswith("#tb")]
    return {"stream_sha256": match[1], "packet_count": len(lines), "timebase": timebase,
            "packet_timing_size_hashes": lines}


def decode(path, directory):
    stdout, log = run(["-v", "info", "-xerror", "-err_detect", "explode", "-i", str(path),
                       "-map", "0:v:0", "-map", "0:a:0", "-vf", "showinfo",
                       "-progress", "pipe:1", "-nostats", "-f", "null", "-"], directory, "full-decode")
    frames = re.findall(r"\bn:\s*(\d+)\s+pts:\s*(-?\d+)\s+pts_time:([\d.e+-]+).*?fmt:(\S+).*?sar:(\S+).*?s:(\d+x\d+)", log)
    require(len(frames) == FRAMES, "Full decode did not produce 2679 frames")
    for i, (index, _, timestamp, fmt, sar, size) in enumerate(frames):
        require(int(index) == i and abs(float(timestamp) - i / FPS) < 0.00006
                and fmt == "yuv420p" and sar == "1/1" and size == "1280x720",
                f"Decoded cadence/format/geometry changed: frame {i}")
    progress = dict(line.split("=", 1) for line in stdout.splitlines() if "=" in line)
    require(progress.get("progress") == "end" and int(progress.get("frame", 0)) == FRAMES
            and int(progress.get("dup_frames", 0)) == int(progress.get("drop_frames", 0)) == 0,
            "Decode incomplete/dropped/duplicated frames")
    require(re.search(r"Audio: aac.*?48000 Hz, mono", log)
            and "(hun)" in log and not re.search(r"Error|Invalid|corrupt", log, re.I),
            "Decode errors or unexpected audio metadata")
    return {"frames": FRAMES, "first_pts": float(frames[0][2]), "last_pts": float(frames[-1][2]),
            "sar": "1:1", "fps": FPS, "dropped": 0, "duplicated": 0, "aac_hungarian_mono_48000": True}


def sample_frames(provenance):
    result = {0: ["first"], 2529: ["CTA_start"], 2678: ["final_CTA"]}
    for cue in provenance["cues"]:
        # ASS rounding is at most 5ms, less than a 30fps frame. Sample both sides too.
        start = int(math.floor(cue["start"] * 100 + 0.5)) / 100
        end = int(math.floor(cue["end"] * 100 + 0.5)) / 100
        first, last = math.ceil(start * FPS - 1e-8), math.ceil(end * FPS - 1e-8) - 1
        require(0 <= first <= last < FRAMES, "Cue has no rendered frame")
        indices = [(first, "start"), ((first + last) // 2, "mid"), (last, "end"),
                   (max(0, first - 1), "before"), (min(FRAMES - 1, last + 1), "after")]
        for index, label in indices:
            result.setdefault(index, []).append(f"cue-{cue['index']:03}-{label}")
    return result


def active_at(provenance, frame):
    time = frame / FPS
    return [c["index"] for c in provenance["cues"]
            if int(math.floor(c["start"] * 100 + 0.5)) / 100 <= time
            < int(math.floor(c["end"] * 100 + 0.5)) / 100]


def samples(provenance, partial, directory, ass_path):
    indices = sample_frames(provenance)
    selection = "+".join(f"eq(n\\,{n})" for n in sorted(indices))
    sample_records = []
    images = []
    # Decode identical frame indices from source, actual output, and expected libass overlay.
    expected_ass = directory / "captions.ass"
    write(expected_ass, ass_path.read_bytes())
    owned(directory / "fonts").mkdir()
    write(directory / "fonts/font-arial-bold.ttf", FONT.read_bytes())
    raw_sets = []
    for label, path, extra in (("source", SOURCE, ""), ("output", partial, ""),
                               ("expected", SOURCE, "ass=captions.ass:fontsdir=fonts:shaping=complex,")):
        raw, _ = run(["-v", "error", "-i", str(path), "-an", "-vf", extra + "select='" + selection + "'",
                      "-fps_mode", "passthrough", "-pix_fmt", "rgb24", "-f", "rawvideo", "-"],
                     directory, "sample-" + label, True)
        require(len(raw) == len(indices) * WIDTH * HEIGHT * 3, "Sample frame count mismatch")
        raw_sets.append(raw)
    stride = WIDTH * HEIGHT * 3
    for i, n in enumerate(sorted(indices)):
        source, actual, expected = [Image.frombytes("RGB", (WIDTH, HEIGHT), r[i * stride:(i + 1) * stride])
                                    for r in raw_sets]
        active = active_at(provenance, n)
        # Lossy re-encoding prevents exact pixel equality. Match expected glyphs instead.
        difference = ImageChops.difference(source, expected).convert("L")
        box = difference.point(lambda p: 255 if p > 4 else 0).getbbox()
        if active:
            check_bounds(box, f"Expected overlay outside safe band at frame {n}")
            white = expected.convert("L")
            glyph_pixels = []
            for y in range(BAND[1], BAND[3]):
                for x in range(BAND[0], BAND[2]):
                    if white.getpixel((x, y)) > 235 and difference.getpixel((x, y)) > 25:
                        glyph_pixels.append((x, y))
            require(len(glyph_pixels) >= 70, f"No detectable expected glyphs at frame {n}")
            good = sum(sum(abs(a - b) for a, b in zip(actual.getpixel(p), expected.getpixel(p))) / 3 < 45
                       for p in glyph_pixels)
            glyph_match = good / len(glyph_pixels)
            require(glyph_match >= 0.95, f"Burned glyph mismatch at frame {n}: {glyph_match}")
        else:
            require(box is None, f"Subtitle unexpectedly present at frame {n}")
            glyph_match = None
        png = directory / f"frame-{n:04}.png"
        with owned(png).open("xb") as handle:
            actual.save(handle, format="PNG")
        sample_records.append({"frame": n, "seconds": n / FPS, "labels": indices[n],
                               "active_cues": active, "expected_overlay_bounds": box,
                               "glyph_pixel_match": glyph_match, "path": str(png), "sha256": sha(png)})
        images.append((n, actual))
    for page in range(math.ceil(len(images) / 24)):
        batch = images[page * 24:(page + 1) * 24]
        sheet = Image.new("RGB", (1280, math.ceil(len(batch) / 4) * 202), "#eeeeee")
        draw = ImageDraw.Draw(sheet)
        label_font = ImageFont.truetype(str(FONT), 14)
        for i, (n, frame) in enumerate(batch):
            x, y = (i % 4) * 320, (i // 4) * 202
            sheet.paste(frame.resize((320, 180), Image.Resampling.LANCZOS), (x, y))
            draw.text((x + 5, y + 183), f"f{n} / {n / FPS:.3f}s", fill="black", font=label_font)
        with owned(directory / f"contact-sheet-{page + 1:02}.jpg").open("xb") as handle:
            sheet.save(handle, format="JPEG", quality=94)
    save(directory / "sample-frames.json", sample_records)
    return sample_records


def audit(provenance):
    state = registered(provenance)
    if state == "verified":
        print(json.dumps({"status": PASS, "reused": str(FINAL), "sha256": sha(FINAL)}))
        return
    require(isinstance(state, dict), "No registered render; run render or all first")
    partial = owned(state["partial"])
    directory = Path(tempfile.mkdtemp(prefix="audit-", dir=QA))
    source_info, final_info = container(SOURCE), container(partial)
    full_decode = decode(partial, directory)
    source_audio = hash_stream(SOURCE, directory, "source")
    final_audio = hash_stream(partial, directory, "output")
    require(source_audio == final_audio, "AAC bytes/packet timing/count changed")
    require(source_info["streams"][1] == final_info["streams"][1], "AAC container timing changed")
    frames = samples(provenance, partial, directory, Path(state["artifacts"][0]["path"]))
    after = inputs()
    require(after == provenance and registered(after) == state, "Inputs/partial changed during QA")
    report = {"status": PASS, "inputs_before": provenance, "inputs_after": after,
              "source_container": source_info, "output_container": final_info,
              "output": {"path": str(FINAL), "sha256": sha(partial), "bytes": partial.stat().st_size},
              "full_decode": full_decode, "audio": {"exact_copied_stream_and_packet_match": True,
              "sha256": source_audio["stream_sha256"], "packet_count": source_audio["packet_count"]},
              "typography": state["typography"], "sample_frames": frames, "qa_directory": str(directory),
              "passed_limits": {"dimensions": [WIDTH, HEIGHT], "duration": TOTAL, "frames": FRAMES,
              "fps": FPS, "sar": "1:1", "pixel_format": "yuv420p", "crf": 18, "faststart": True,
              "max_lines": 2, "font_size": FONT_SIZE, "band": BAND, "max_band_width": 1080,
              "min_margin": 90, "CTA_top": 552, "band_to_CTA_gap": 14},
              "limitations": ["No direct listening or native Hungarian pronunciation review",
              "Speech timing comes from parent SRT/manifest; no independent speech recognition",
              "ASS centisecond rounding differs from SRT by at most 5ms; actual frame boundaries sampled",
              "Visual samples are not human inspection of all 2679 frames or OCR",
              "Lossy video re-encode is necessary; AAC packet bytes and timing are exact",
              "Parent must independently review before publication; no publication performed",
              "No browser/cloud/paid/upload/website/ads/git/docs changes"]}
    save(directory / "qa-manifest.json", report)
    require(not FINAL.exists() and not REPORT.exists(), "Final/report appeared during audit; preserved")
    partial.rename(owned(FINAL))
    save(REPORT, report)
    print(json.dumps({"status": PASS, "output": str(FINAL), "sha256": sha(FINAL),
                      "report": str(REPORT), "samples": len(frames)}, indent=2), flush=True)


def self_test():
    directory = Path(tempfile.mkdtemp(prefix="validation-", dir=QA))
    raw = b"1\n00:00:00,500 --> 00:00:02,000\nHarmat Lakopark\n\n2\n00:00:03,000 --> 00:00:04,000\nBetonozas.\n"
    cues = parse_srt(raw)
    pin = hashlib.sha256(raw).hexdigest()
    fixture = {"status": "READY_FOR_BURN", "source_sha256": SOURCE_PIN,
               "srt_sha256": pin, "cues": cues, "limits": {"max_lines": 2}}
    validate_manifest(fixture, cues, pin)
    checks = ["valid_exact_srt_manifest"]
    cases = [("pending", lambda m: m.update(status="PENDING")),
             ("wrong_source", lambda m: m.update(source_sha256="0" * 64)),
             ("wrong_srt", lambda m: m.update(srt_sha256="0" * 64)),
             ("order", lambda m: m["cues"][0].update(index=2)),
             ("boolean_index", lambda m: m["cues"][0].update(index=True)),
             ("overlap", lambda m: m["cues"][1].update(start=1.0)),
             ("negative", lambda m: m["cues"][0].update(start=-1.0)),
             ("beyond_video", lambda m: m["cues"][1].update(end=90.0)),
             ("nan", lambda m: m["cues"][0].update(start=float("nan"))),
             ("infinite", lambda m: m["cues"][0].update(end=float("inf"))),
             ("text_changed", lambda m: m["cues"][0].update(text="Changed")),
             ("timing_changed", lambda m: m["cues"][0].update(start=0.8)),
             ("missing_limits", lambda m: m.pop("limits"))]
    def reject(name, action):
        try:
            action()
        except (ValueError, KeyError, UnicodeError):
            checks.append(name)
        else:
            raise ValueError(f"Validation accepted {name}")
    for name, mutate in cases:
        bad = copy.deepcopy(fixture)
        mutate(bad)
        reject(name, lambda b=bad: validate_manifest(b, cues, pin))
    for name, value in (("markup", "{\\pos(1,1)}"), ("CJK", "\u4e2d\u6587"),
                        ("placeholder", "Lorem ipsum"), ("mojibake", "\u00c3\u00a1"),
                        ("three_lines", "A\nB\nC"), ("empty", "")):
        reject(name, lambda v=value: text_check(v))
    reject("long_word", lambda: layout("W" * 200))
    reject("three_lines_needed", lambda: layout("Harmat " * 100))
    reject("malformed_timestamp", lambda: parse_srt(raw.replace(b"00:00:00,500", b"00:99:00,500")))
    reject("short_box", lambda: list(boxes(b"bad")))
    reject("oversized_box", lambda: list(boxes(struct.pack(">I4s", 99, b"moov"))))
    reject("write_source", lambda: owned(SOURCE))
    reject("write_parent_srt", lambda: owned(SRT))
    reject("unsafe_bounds", lambda: check_bounds((89, 450, 500, 530), "unsafe"))
    text_check("\u0150rizz\u00fck az \u00e9p\u00edtkez\u00e9s lend\u00fclet\u00e9t.")
    checks.append("proper_hungarian_accents")
    font = ImageFont.truetype(str(FONT), FONT_SIZE)
    require(font.getmask("\u0151").getbbox() and font.getmask("\u0171").getbbox()
            and bytes(font.getmask("\u0151")) != bytes(font.getmask("o"))
            and bytes(font.getmask("\u0171")) != bytes(font.getmask("u")), "Hungarian glyph support")
    checks.append("double_acute_glyphs")
    save(directory / "self-test-results.json", {"status": "PASS", "checks": checks, "count": len(checks)})
    print(json.dumps({"self_test": "PASS", "count": len(checks), "evidence": str(directory)}, indent=2))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=("render", "qa", "all"))
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()
    for path in (RENDER, QA):
        owned(path).mkdir(parents=True, exist_ok=True)
    if args.self_test:
        self_test()
        return
    provenance = inputs()
    if args.action in ("render", "all"):
        render(provenance)
    if args.action in ("qa", "all"):
        audit(provenance)


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(f"STOPPED: {exc}. Unknown files/partials preserved; no publication.", file=sys.stderr)
        sys.exit(1)
