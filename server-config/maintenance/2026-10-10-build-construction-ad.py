"""Build the approved local, silent 30-second construction-ad drafts.

Requires FFmpeg with drawtext/libx264 and Pillow. No network or publishing.
Override a source only together with its independently verified SHA-256.
--fullscreen selects the separate native-photo/full-bleed v2 storyboard and output owner.
"""

import argparse
import hashlib
import json
import re
import shutil
import struct
import subprocess
import sys
from pathlib import Path

from PIL import Image, ImageChops, ImageFont, ImageStat


REPO = Path(__file__).resolve().parents[2]
OUTPUT = REPO / "outputs/2026-10-10-construction-ad"
OWNER = "harmat-construction-ad-2026-10-10-v1"
FULLSCREEN_OUTPUT = REPO / "outputs/2026-10-10-construction-ad-fullscreen"
FULLSCREEN_OWNER = "harmat-construction-ad-2026-10-10-fullscreen-v2"
FPS = 30
QA_TIMES = (2, 8, 14, 21, 25, 29)
CTA = "K\u00e9rjen szem\u00e9lyes aj\u00e1nlatot!"
BRAND = "Harmat Lak\u00f3park"
LATEST = "Helysz\u00edni felv\u00e9tel: 2026. okt\u00f3ber 2."
ARCHIVE = "Arch\u00edv felv\u00e9tel: 2026. szeptember 25."
RENDER = "L\u00e1tv\u00e1nyterv"
DOMAIN = "harmat22.hu"
BG = "0xf4f6f3"
INK = "0x23443b"
DEFAULTS = {
    "overview": ("outputs/2026-09-construction-ready/harmat-2026-september-overview.mp4",
                 "ae62c9dbc4ff0a3dceaa38b3018634302bb37ae3d746841440f6ac7c581a67b5"),
    "archive": ("outputs/2026-09-construction-ready/2026-09-25-a1.mp4",
                "1339877b4f7173883cc1d6db256842af722588344c270a571847d07678be75a1"),
    "render": ("outputs/home-native-video/original.mp4",
               "40e13d25fe9ac7198b5aae9126f1569d507e9932f9888ae8b6764ad79f21f7b7"),
    "logo": ("outputs/2026-10-10-construction-ad/source/harmat-logo.png",
             "99a024d825dac605fd3e09227bcaacb898b30f5d59ef21ea0eeef990fd638c5f"),
}
SCENES = (
    ("overview", 18.0, 5, "\u00c9p\u00fcl a Harmat Lak\u00f3park",
     "Budapest, Harmat utca 22. | 2026. okt\u00f3ber 2."),
    ("archive", 5.0, 6, "Pincei szerkezet\u00e9p\u00edt\u00e9s", ARCHIVE),
    ("overview", 24.0, 7, "K\u00f6vesse az \u00e9p\u00edtkez\u00e9st", LATEST),
    ("render", 72.0, 6, "Udvar \u00e9s teraszok", RENDER),
)
PHOTO_DATED = "Helysz\u00edni fot\u00f3: 2026. okt\u00f3ber 2."
PHOTO_UNDATED = "Helysz\u00edni fot\u00f3 (d\u00e1tuma nem ismert)"
OPENING = "\u00c9p\u00fcl a Harmat Lak\u00f3park"
FOUNDATION = "Alapoz\u00e1s \u00e9s vasal\u00e1s"
STRUCTURE = "Pincei szerkezet\u00e9p\u00edt\u00e9s"
FULLSCREEN_DEFAULTS = {
    "overview": ("outputs/2026-10-10-construction-ad-fullscreen/source/overview-raw.mp4",
                 "c9d7f0fb4a2b97e8168785c9b76f9b2e9174db3882abcee692db1b6472062166"),
    "render": DEFAULTS["render"],
    "logo": ("outputs/2026-10-10-construction-ad-fullscreen/source/harmat-logo.png", DEFAULTS["logo"][1]),
    "photo01": ("outputs/2026-10-10-construction-ad-fullscreen/source/photo01.jpg",
                "488d0045b2bff585366f48384bbb4f0aac4e4edafdf199d889a62384b85af410"),
    "photo04": ("outputs/2026-10-10-construction-ad-fullscreen/source/photo04.jpg",
                "42ea068630cab24b8e58850020bcbc588fbc2633210c43e2e5dcad3e71a47f04"),
    "photo02": ("outputs/2026-10-10-construction-ad-fullscreen/source/photo02.jpg",
                "c93c7239e224de90414c31b8561976aa2b7e662ae9a4e099d1870df4387f5aec"),
}
FULLSCREEN_SCENES = {
    "landscape": (("overview", 18.0, 6, OPENING, LATEST),
                  ("photo04", 0, 6, FOUNDATION, PHOTO_DATED),
                  ("overview", 24.0, 6, STRUCTURE, LATEST),
                  ("render", 72.0, 6, "Udvar \u00e9s teraszok", RENDER),
                  ("photo01", 0, 6, BRAND, PHOTO_DATED)),
    "portrait": (("photo01", 0, 6, OPENING, PHOTO_DATED),
                 ("photo04", 0, 6, FOUNDATION, PHOTO_DATED),
                 ("photo02", 0, 6, STRUCTURE, PHOTO_UNDATED),
                 ("render", 72.0, 6, "Udvar \u00e9s teraszok", RENDER),
                 ("photo01", 0, 6, BRAND, PHOTO_DATED)),
}
# Crop anchors within the available native-image margins, after visual review.
PHOTO_FOCUS = {
    "photo01": {"landscape": (0.50, 0.72), "portrait": (0.72, 0.50)},
    "photo04": {"landscape": (0.50, 0.90), "portrait": (0.22, 0.50)},
    "photo02": {"landscape": (0.50, 0.72), "portrait": (0.52, 0.50)},
}


def storyboard(mode, fullscreen):
    return FULLSCREEN_SCENES[mode] if fullscreen else SCENES + (("brand", 0, 6, BRAND, ""),)


def build_scope(fullscreen):
    return (FULLSCREEN_OUTPUT, FULLSCREEN_OWNER, FULLSCREEN_DEFAULTS) if fullscreen else (OUTPUT, OWNER, DEFAULTS)


def sha256(path):
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def run(command, timeout=600, binary_stdout=False):
    result = subprocess.run(command, shell=False, stdin=subprocess.DEVNULL,
                            capture_output=True, timeout=timeout)
    require(result.returncode == 0,
            result.stderr.decode("utf-8", "replace")[-6000:])
    return (result.stdout if binary_stdout else result.stdout.decode("utf-8", "replace")), result.stderr.decode("utf-8", "replace")


def probe(ffmpeg, source):
    # The bundled FFmpeg has no ffprobe. Retain only non-location media facts.
    _, log = run([str(ffmpeg), "-hide_banner", "-nostdin", "-i", str(source),
                  "-map", "0:v:0", "-an", "-frames:v", "0", "-f", "null", "-"])
    info = log.split("Stream mapping:")[0]
    duration = re.search(r"Duration: (\d+):(\d+):(\d+\.\d+)", info)
    video = re.search(r"Video: (.+)", info)
    require(duration and video, "Cannot inspect video duration/stream")
    line = video.group(1)
    size = re.search(r"\b(\d{3,5})x(\d{3,5})\b", line)
    fps = re.search(r"([\d.]+) fps", line)
    timebase = re.search(r"([\w.]+) tbn", line)
    sample_ratio = re.search(r"\bSAR (\d+):(\d+)", line)
    require(size and fps and timebase, "Cannot inspect video geometry/timebase")
    seconds = int(duration[1]) * 3600 + int(duration[2]) * 60 + float(duration[3])
    created = re.search(r"creation_time\s*:\s*(\S+)", info)
    return {"duration_seconds_reported": seconds, "width": int(size[1]),
            "height": int(size[2]), "fps_reported": float(fps[1]),
            "timebase_reported": timebase[1], "codec": line.split()[0],
            "pixel_format": "yuv420p" if "yuv420p" in line else "unknown",
            "creation_time": created[1] if created else None,
            "sample_aspect_ratio": [int(sample_ratio[1]), int(sample_ratio[2])] if sample_ratio else None}, info


def filter_path(path):
    value = path.resolve().as_posix()
    require(not any(char in value for char in "'\n\r"), "Unsupported font path characters")
    return "'" + value.replace(":", "\\:") + "'"


def text_style(text, size, y, font, color=INK, centered=True, x=96):
    metrics = ImageFont.truetype(str(font), size)
    box = metrics.getbbox(text)
    return {"text": text, "size": size, "y": y, "font": font,
            "color": color, "centered": centered, "x": x,
            "width": int(metrics.getlength(text) + 1), "height": box[3] - box[1]}


def layout(mode, end, title, caption, fonts):
    regular, bold, display = fonts
    if mode == "landscape":
        width, height = 1920, 1080
        if end:
            styles = [text_style(BRAND, 72, 456, display),
                      text_style(CTA, 52, 592, bold),
                      text_style(DOMAIN, 52, 724, regular)]
            logo = (835, 148, 250)
            extra = "drawbox=x=910:y=416:w=100:h=4:color=0x86aa93:t=fill"
        else:
            styles = [text_style(title, 60, 914, bold, "white", False),
                      text_style(caption, 32, 1008, regular, "white", False)]
            logo = None
            extra = "drawbox=x=0:y=902:w=iw:h=178:color=0x142d27@0.80:t=fill"
    else:
        width, height = 1080, 1920
        if end:
            styles = [text_style(BRAND, 64, 700, display),
                      text_style(CTA, 52, 890, bold),
                      text_style(DOMAIN, 58, 1040, regular)]
            logo = (415, 330, 250)
            extra = "drawbox=x=490:y=644:w=100:h=4:color=0x86aa93:t=fill"
        else:
            styles = [text_style(BRAND, 54, 420, display),
                      text_style(title, 54, 1260, bold),
                      text_style(caption, 36, 1360, regular, "0x496158"),
                      text_style(DOMAIN, 40, 1620, regular)]
            logo = (428, 144, 224)
            extra = "drawbox=x=490:y=498:w=100:h=4:color=0x86aa93:t=fill"
    for style in styles:
        style["left"] = (width - style["width"]) // 2 if style["centered"] else style["x"]
        require(style["left"] >= 80 and style["left"] + style["width"] <= width - 80,
                "Text exceeds horizontal safe zone: " + style["text"])
        require(80 <= style["y"] and style["y"] + style["height"] <= height - 40,
                "Text exceeds vertical safe zone")
    for previous, current in zip(styles, styles[1:]):
        require(previous["y"] + previous["height"] + 20 < current["y"],
                "Text lines overlap")
    return width, height, styles, logo, extra


def fullscreen_layout(mode, end, title, caption, fonts):
    regular, bold, display = fonts
    width, height = (1920, 1080) if mode == "landscape" else (1080, 1920)
    if end:
        if mode == "landscape":
            styles = [text_style(BRAND, 72, 456, display, "white"),
                      text_style(CTA, 52, 592, bold, "white"),
                      text_style(DOMAIN, 52, 724, regular, "white"),
                      text_style(caption, 26, 982, regular, "white", False)]
            logo = (835, 148, 250)
        else:
            styles = [text_style(BRAND, 64, 700, display, "white"),
                      text_style(CTA, 52, 890, bold, "white"),
                      text_style(DOMAIN, 58, 1040, regular, "white"),
                      text_style(caption, 28, 1470, regular, "white")]
            logo = (415, 330, 250)
        extra = "drawbox=x=0:y=0:w=iw:h=ih:color=black@0.48:t=fill"
    else:
        y, size, caption_y, caption_size = (900, 46, 976, 28) if mode == "landscape" else (1334, 56, 1440, 36)
        styles = [text_style(title, size, y, bold, "white", False),
                  text_style(caption, caption_size, caption_y, regular, "white", False)]
        box_width = max(style["width"] for style in styles) + 48
        box_y, box_height = (880, 152) if mode == "landscape" else (1310, 206)
        extra = f"drawbox=x=72:y={box_y}:w={box_width}:h={box_height}:color=0x142d27@0.68:t=fill"
        logo = None
    for style in styles:
        style["left"] = (width - style["width"]) // 2 if style["centered"] else style["x"]
        require(96 <= style["left"] and style["left"] + style["width"] <= width - 96,
                "Fullscreen text exceeds horizontal safe zone")
        bottom_limit = 1560 if mode == "portrait" else height - 40
        require(150 <= style["y"] and style["y"] + style["height"] <= bottom_limit,
                "Fullscreen text exceeds vertical safe zone")
    require(all(a["y"] + a["height"] + 20 < b["y"] for a, b in zip(styles, styles[1:])),
            "Fullscreen text overlaps")
    return width, height, styles, logo, extra


def fullscreen_source_guard(role, facts):
    if role.startswith("photo"):
        require((facts["width"], facts["height"], facts["orientation"]) == (4096, 3072, 1),
                "Fullscreen photographs must be the reviewed native, upright 4096x3072 files")
    elif role == "overview":
        require((facts["width"], facts["height"], facts["codec"]) == (1920, 1080, "hevc"),
                "Fullscreen overview must be the native 1080 HEVC source, not the 720 archive/derivative")
        require(facts["creation_time"] == "2026-10-02T08:19:32.000000Z", "Overview capture provenance changed")
    elif role == "render":
        require((facts["width"], facts["height"]) == (1920, 1080), "Render source dimensions changed")


def fullscreen_media(role, mode):
    width, height = (1920, 1080) if mode == "landscape" else (1080, 1920)
    if role.startswith("photo"):
        crop_w, crop_h = (4096, 2304) if mode == "landscape" else (1728, 3072)
        focus_x, focus_y = PHOTO_FOCUS[role][mode]
        require(0 <= focus_x <= 1 and 0 <= focus_y <= 1, "Photo focal range outside source")
        x, y = 2 * int((4096 - crop_w) * focus_x / 2), 2 * int((3072 - crop_h) * focus_y / 2)
        require(x + crop_w <= 4096 and y + crop_h <= 3072, "Photo crop exceeds native pixels")
        require(crop_w / 1.045 >= width and crop_h / 1.045 >= height, "Photo crop would upscale")
        filters = (f"crop={crop_w}:{crop_h}:{x}:{y},"
                   f"zoompan=z='1+0.045*on/179':x='(iw-iw/zoom)*(0.45+0.10*on/179)':"
                   f"y='(ih-ih/zoom)*0.55':d=180:s={width}x{height}:fps=30")
        framing = {"native_crop_xywh": [x, y, crop_w, crop_h], "zoom_range": [1, 1.045],
                   "pan_x_fraction_range": [0.45, 0.55], "pan_y_fraction": 0.55,
                   "motion": "Editorial pan/zoom of a real photograph, not recorded camera motion",
                   "photo_upscaling": False}
    elif mode == "portrait":
        require(role == "render", "Fullscreen portrait must not enlarge camera-strip footage")
        crop_w, low, high = 606, 0.46, 0.48
        filters = (f"crop={crop_w}:1080:x='2*floor((iw-ow)*({low}+{high-low:.2f}*n/179)/2)':y=0,"
                   "scale=1080:1920:flags=lanczos")
        framing = {"native_crop_width_height": [crop_w, 1080], "horizontal_margin_fraction_range": [low, high],
                   "native_x_range": [604, 630], "visualization_upscale_factor": 1920 / 1080}
    else:
        filters = "scale=1920:1080:flags=lanczos"
        framing = {"native_full_frame": True}
    require("pad=" not in filters and "color=" not in filters, "Fullscreen media cannot contain blank bands")
    return filters, framing


def fullscreen_pixel_check(ffmpeg, movie, mode):
    small_w, small_h = (96, 54) if mode == "landscape" else (54, 96)
    data, _ = run([str(ffmpeg), "-hide_banner", "-nostdin", "-loglevel", "error", "-xerror",
                   "-err_detect", "explode", "-threads", "4", "-i", str(movie), "-map", "0:v:0", "-an",
                   "-vf", f"scale={small_w}:{small_h}", "-pix_fmt", "rgb24", "-f", "rawvideo", "pipe:1"],
                  binary_stdout=True)
    stride = small_w * small_h * 3
    require(len(data) == 900 * stride, "Fullscreen pixel decode count mismatch")
    edges = ((0, 0, small_w, 3), (0, small_h - 3, small_w, small_h),
             (0, 0, 3, small_h), (small_w - 3, 0, small_w, small_h))
    minimum, edge_minimum = 255, 255
    for index in range(900):
        frame = Image.frombytes("RGB", (small_w, small_h), data[index * stride:(index + 1) * stride])
        variation = max(ImageStat.Stat(frame).stddev)
        edge_variation = min(max(ImageStat.Stat(frame.crop(edge)).stddev) for edge in edges)
        require(variation > 8 and edge_variation > 0.25, f"Blank frame/constant edge band at frame {index}")
        minimum, edge_minimum = min(minimum, variation), min(edge_minimum, edge_variation)
    return {"frames_checked": 900, "minimum_frame_stddev": round(minimum, 3),
            "minimum_edge_stddev": round(edge_minimum, 3), "no_pad_filter": True,
            "media_fills_canvas_before_overlays": True}


def safe_hungarian_glyphs(fonts):
    for path in fonts:
        font = ImageFont.truetype(str(path), 36)
        missing = bytes(font.getmask("\u0378"))
        for character in "\u00c9\u00e1\u00e9\u00ed\u00f3\u00f6\u0151\u00fa\u00fc\u0171":
            mask = font.getmask(character)
            require(mask.getbbox() is not None and bytes(mask) != missing,
                    "Required Hungarian glyph absent in " + path.name)


def output_metadata_guard(info):
    require("Audio:" not in info and "Data:" not in info, "Unexpected export stream")
    require(not re.search(r"location|creation_time|GPS|openharmony", info, re.I), "Private metadata remains")


def faststart(path):
    boxes = []
    with path.open("rb") as stream:
        while stream.tell() < path.stat().st_size:
            offset = stream.tell()
            header = stream.read(8)
            require(len(header) == 8, "Truncated MP4 box")
            size, kind = struct.unpack(">I4s", header)
            if size == 1:
                size = struct.unpack(">Q", stream.read(8))[0]
            if size == 0:
                size = path.stat().st_size - offset
            require(size >= 8, "Invalid MP4 box size")
            boxes.append(kind.decode("ascii", "replace"))
            stream.seek(offset + size)
    require("moov" in boxes and "mdat" in boxes and boxes.index("moov") < boxes.index("mdat"),
            "MP4 is not faststart")
    return boxes


def build(args):
    output, owner, defaults = build_scope(args.fullscreen)
    ffmpeg = Path(args.ffmpeg or shutil.which("ffmpeg") or "").resolve()
    require(ffmpeg.is_file(), "Supply --ffmpeg with the FFmpeg executable")
    fonts = tuple(Path(value).resolve() for value in (args.font, args.bold_font, args.display_font))
    require(all(font.is_file() for font in fonts), "A required font is missing")
    if args.fullscreen:
        safe_hungarian_glyphs(fonts)
    inputs, facts = {}, {}
    for role, (default_path, default_hash) in defaults.items():
        supplied = getattr(args, role + "_source")
        path = Path(supplied).resolve() if supplied else REPO / default_path
        expected = getattr(args, role + "_sha256")
        require(not supplied or expected, "Source override requires --" + role + "-sha256")
        expected = expected or default_hash
        require(re.fullmatch(r"[a-fA-F0-9]{64}", expected) is not None, "Invalid SHA-256 pin")
        if args.fullscreen:
            require(expected.lower() == default_hash, "Fullscreen inputs must match the approved native-source pins")
        require(path.is_file() and sha256(path) == expected.lower(), "Input hash mismatch: " + role)
        inputs[role] = path
        facts[role] = {"filename": path.name, "bytes": path.stat().st_size,
                       "sha256": expected.lower()}
        if role.startswith("photo"):
            with Image.open(path) as photo:
                require(photo.format in ("JPEG", "MPO"), "Native photograph must be JPEG or its MPO container")
                facts[role].update({"width": photo.width, "height": photo.height,
                                    "orientation": photo.getexif().get(274, 1), "image_format": photo.format,
                                    "primary_image": 0})
            if facts[role]["image_format"] == "MPO":
                loop_hashes, _ = run([str(ffmpeg), "-hide_banner", "-nostdin", "-loglevel", "error",
                                      "-loop", "1", "-framerate", "30", "-i", str(path), "-map", "0:v:0",
                                      "-frames:v", "3", "-f", "framemd5", "-"])
                primary_rows = [line for line in loop_hashes.splitlines() if line and not line.startswith("#")]
                require(len(primary_rows) == 3 and len({line.split(",")[-1].strip() for line in primary_rows}) == 1,
                        "MPO loop must repeat only the approved primary photograph")
                require("#dimensions 0: 4096x3072" in loop_hashes, "MPO decoder selected a non-native auxiliary image")
                facts[role]["primary_image_loop_verified"] = True
        elif role != "logo":
            media, _ = probe(ffmpeg, path)
            facts[role].update(media)
        if args.fullscreen:
            fullscreen_source_guard(role, facts[role])
    with Image.open(inputs["logo"]) as logo_image:
        require(logo_image.mode == "RGBA" and logo_image.width >= 250,
                "Expected a transparent logo of at least 250 pixels")
        require(logo_image.getextrema()[3][0] < 255, "Logo transparency is absent")
        facts["logo"]["dimensions"] = list(logo_image.size)
    for mode in ("landscape", "portrait"):
        scenes = storyboard(mode, args.fullscreen)
        for role, start, duration, _, _ in scenes:
            if role in facts and "duration_seconds_reported" in facts[role]:
                require(start + duration <= facts[role]["duration_seconds_reported"], "Cut exceeds source")
        require(sum(scene[2] for scene in scenes) == 30, "Timeline is not exactly 30 seconds")

    output.mkdir(parents=True, exist_ok=True)
    require(not output.is_symlink(), "Output root cannot be a symlink")
    registry = output / ".builder-owned.json"
    require(not registry.is_symlink(), "Output registry cannot be a symlink")
    if registry.exists():
        state = json.loads(registry.read_text(encoding="utf-8"))
        require(state.get("owner") == owner and args.overwrite_own,
                "Existing build: use --overwrite-own only to replace this builder's files")
    else:
        state = {"owner": owner, "files": {}}
        with registry.open("x", encoding="utf-8") as stream:
            json.dump(state, stream, indent=2)

    def save_registry():
        registry.write_text(json.dumps(state, indent=2) + "\n", encoding="utf-8")

    def destination(relative):
        path = output / relative
        require(relative.split("/", 1)[0] != "source", "Source files must never be builder-owned")
        require(path.resolve().is_relative_to(output.resolve()), "Output escapes approved scope")
        require(path.resolve() not in inputs.values(), "A source cannot be used as an output")
        require(not any(parent.is_symlink() for parent in (path, *path.parents)), "Output symlink refused")
        if path.exists():
            require(args.overwrite_own and relative in state["files"], "Unowned/existing output: " + relative)
            previous = state["files"][relative]
            require(previous is None or sha256(path) == previous, "Owned output changed externally: " + relative)
            path.unlink()
        path.parent.mkdir(parents=True, exist_ok=True)
        state["files"][relative] = None
        save_registry()
        return path

    def record(path):
        state["files"][path.relative_to(output).as_posix()] = sha256(path)
        save_registry()

    def write_text(relative, value):
        path = destination(relative)
        with path.open("x", encoding="utf-8", newline="\n") as stream:
            stream.write(value)
        record(path)
        return path

    common = [str(ffmpeg), "-hide_banner", "-nostdin", "-loglevel", "error", "-n"]
    encoding = ["-an", "-sn", "-dn", "-map_metadata", "-1", "-map_chapters", "-1",
                "-c:v", "libx264", "-preset", "medium", "-crf", "17" if args.fullscreen else "18", "-threads", "4",
                "-pix_fmt", "yuv420p", "-r", "30", "-fps_mode", "cfr",
                "-video_track_timescale", "30000", "-color_primaries", "bt709",
                "-color_trc", "bt709", "-colorspace", "bt709", "-color_range", "tv",
                "-metadata:s:v:0", "language=und", "-movflags", "+faststart"]
    geometry, deliverables, checks = {}, {}, {}
    stdout_version, _ = run([str(ffmpeg), "-version"])
    for mode in ("landscape", "portrait"):
        segments = []
        geometry[mode] = []
        scenes = storyboard(mode, args.fullscreen)
        for index in range(5):
            end = index == 4
            role, start, duration, title, caption = scenes[index]
            layout_function = fullscreen_layout if args.fullscreen else layout
            width, height, styles, logo, extra = layout_function(mode, end, title, caption, fonts)
            base = f"fps=30,trim=end_frame={duration * FPS},setpts=N/(30*TB),setsar=1"
            framing = None
            if args.fullscreen:
                media_filters, framing = fullscreen_media(role, mode)
                base += "," + media_filters + f",trim=end_frame={duration * FPS},setpts=N/(30*TB)"
                require("pad=" not in base, "Fullscreen cannot use padding")
            elif not end:
                if mode == "landscape":
                    base += ",scale=1920:1080:force_original_aspect_ratio=decrease:flags=lanczos,pad=1920:1080:(ow-iw)/2:(oh-ih)/2"
                else:
                    # Preserve the entire wide shot; no destructive portrait center crop.
                    base += f",scale=1080:608:force_original_aspect_ratio=decrease:flags=lanczos,pad=1080:608:(ow-iw)/2:(oh-ih)/2:color={BG},pad=1080:1920:0:560:color={BG}"
            base += ",setsar=1," + extra
            for line_index, style in enumerate(styles):
                text_file = write_text(f"text/{mode}-{index}-{line_index}.txt", style["text"])
                base += (f",drawtext=fontfile={filter_path(style['font'])}:textfile={filter_path(text_file)}"
                         f":expansion=none:fontsize={style['size']}:fontcolor={style['color']}"
                         f":x={( '(w-text_w)/2' if style['centered'] else style['x'])}:y={style['y']}")
            if logo:
                x, y, logo_width = logo
                graph = (f"[0:v]{base}[bg];[1:v]scale={logo_width}:-1:flags=lanczos,format=rgba[logo];"
                         f"[bg][logo]overlay={x}:{y}:shortest=1,format=yuv420p[out]")
            else:
                graph = f"[0:v]{base},format=yuv420p[out]"
            graph_file = write_text(f"filters/{mode}-{index}.ffgraph", graph)
            path = destination(f"work/{mode}-{index}.mp4")
            command = common + ["-threads", "4"]
            if args.fullscreen and role.startswith("photo"):
                command += ["-loop", "1", "-framerate", "30", "-i", str(inputs[role])]
            elif end and not args.fullscreen:
                command += ["-f", "lavfi", "-i", f"color=c={BG}:s={width}x{height}:r=30:d=6"]
            else:
                command += ["-ss", str(start), "-t", str(duration), "-i", str(inputs[role])]
            if logo:
                command += ["-loop", "1", "-i", str(inputs["logo"])]
            command += ["-filter_complex_threads", "2", "-filter_complex_script", str(graph_file),
                        "-map", "[out]", "-frames:v", str(duration * FPS)] + encoding + [str(path)]
            print(f"Rendering {mode} scene {index + 1}/5 ({duration}s)", flush=True)
            run(command)
            record(path)
            segments.append(path)
            geometry[mode].append({"scene": index, "logo": logo, "text": [
                {key: value for key, value in style.items() if key != "font"} for style in styles]})
            if args.fullscreen:
                geometry[mode][-1]["media_framing"] = framing
        concat = write_text(f"work/{mode}-concat.txt", "".join(
            f"file '{segment.name}'\n" for segment in segments))
        movie = destination(f"harmat-construction-ad-{mode}.mp4")
        run(common + ["-f", "concat", "-safe", "1", "-i", str(concat), "-map", "0:v:0",
                      "-an", "-sn", "-dn", "-c:v", "copy", "-map_metadata", "-1", "-map_chapters", "-1",
                      "-movflags", "+faststart", str(movie)])
        record(movie)
        media, info = probe(ffmpeg, movie)
        require(media["width"] == width and media["height"] == height and media["fps_reported"] == 30,
                "Export geometry/frame rate mismatch")
        require(media["sample_aspect_ratio"] == [1, 1], "Export pixels must be square")
        require(media["duration_seconds_reported"] == 30 and media["codec"] == "h264" and
                media["pixel_format"] == "yuv420p", "Export codec/duration mismatch")
        output_metadata_guard(info)
        boxes = faststart(movie)
        md5_file = destination(f"qa/{mode}-frames.framemd5")
        print(f"Decoding/checking all 900 {mode} frames", flush=True)
        run(common + ["-xerror", "-err_detect", "explode", "-threads", "4", "-i", str(movie),
                      "-map", "0:v:0", "-an", "-f", "framemd5", str(md5_file)])
        record(md5_file)
        md5_text = md5_file.read_text(encoding="utf-8")
        rows = [line.split(",") for line in md5_text.splitlines() if line and not line.startswith("#")]
        require(len(rows) == 900 and "#tb 0: 1/30" in md5_text, "Decoded frame count/timebase mismatch")
        require(all(int(row[2]) == index and int(row[3]) == 1 for index, row in enumerate(rows)),
                "Non-contiguous frame timestamps")
        motion, offset = [], 0
        for scene in (scenes if args.fullscreen else SCENES):
            count = scene[2] * FPS
            unique = len({row[-1].strip() for row in rows[offset:offset + count]})
            require(unique >= 20, "Moving scene unexpectedly frozen")
            motion.append({"frames": count, "unique_frame_hashes": unique})
            offset += count
        pixel_checks = []
        for second in QA_TIMES:
            frame = destination(f"qa/{mode}-{second:02d}s.jpg")
            run(common + ["-ss", str(second), "-i", str(movie), "-frames:v", "1", "-an",
                          "-map_metadata", "-1", "-q:v", "2", "-update", "1", str(frame)])
            record(frame)
            with Image.open(frame) as image:
                require(image.size == (width, height), "QA frame geometry mismatch")
                region = (0, 0, width, height) if args.fullscreen else (
                    (0, 0, width, 880) if mode == "landscape" else (0, 560, width, 1168))
                if second < 24:
                    variation = max(ImageStat.Stat(image.crop(region).resize((160, 90))).stddev)
                    require(variation > 12, "Blank/flat scene pixels")
                else:
                    variation = max(ImageStat.Stat(image.resize((160, 90))).stddev)
                    require(variation > 12, "Blank brand endscreen")
                boundaries = [sum(scene[2] for scene in scenes[:i + 1]) for i in range(5)]
                scene_index = next((i for i, boundary in enumerate(boundaries) if second < boundary), 4)
                for style in geometry[mode][scene_index]["text"]:
                    crop = image.crop((style["left"], style["y"], style["left"] + style["width"],
                                       style["y"] + style["height"] + 10)).convert("L")
                    require(ImageStat.Stat(crop).stddev[0] > 10, "Text region is blank")
                pixel_checks.append({"second": second, "pixel_stddev": round(variation, 3)})
        with Image.open(output / f"qa/{mode}-25s.jpg") as first, Image.open(output / f"qa/{mode}-29s.jpg") as last:
            end_delta = sum(ImageStat.Stat(ImageChops.difference(first, last)).mean) / 3
        if args.fullscreen:
            require(end_delta > 0.2, "Fullscreen ending backdrop must move")
        else:
            require(end_delta < 2, "Endscreen is not stable")
        cover = destination(f"harmat-construction-ad-{mode}-cover.jpg")
        run(common + ["-ss", "25", "-i", str(movie), "-frames:v", "1", "-an", "-map_metadata", "-1",
                      "-q:v", "2", "-update", "1", str(cover)])
        record(cover)
        deliverables[mode] = {"file": movie.name, "bytes": movie.stat().st_size, "sha256": sha256(movie),
                              "cover": cover.name, "cover_sha256": sha256(cover), **media,
                              "frames": 900, "decoded_timebase": "1/30", "silent": True}
        checks[mode] = {"full_decode_passed": True, "faststart_boxes": boxes,
                        "moving_scenes": motion, "pixel_checks": pixel_checks,
                        "endscreen_mean_pixel_delta": round(end_delta, 5), "safe_text_geometry": True,
                        "audio_data_gps_creation_metadata_absent": True}
        if args.fullscreen:
            checks[mode]["fullscreen_pixels"] = fullscreen_pixel_check(ffmpeg, movie, mode)
            checks[mode]["endscreen_text_geometry_identity"] = True

    require(all(sha256(inputs[role]) == facts[role]["sha256"] for role in inputs), "Input changed during build")
    manifest = {"owner": owner, "status": "local-preview-only", "script_sha256": sha256(Path(__file__)),
                "sources": facts, "source_provenance": {
                    "overview": "Filmed 2026-10-02; September 30 is only the progress-report cutoff",
                    "archive": "A1 archive, 2026-09-25", "render": "Architectural visualization, not live footage",
                    "logo": "Public Harmat_Logo_250.png; original untouched"},
                "timeline": [{"output_start": sum(item[2] for item in SCENES[:i]), "source": scene[0],
                              "source_start": scene[1], "seconds": scene[2], "caption": scene[4]}
                             for i, scene in enumerate(SCENES)] + [{"output_start": 24, "source": "brand", "seconds": 6}],
                "cta": CTA, "brand": BRAND, "domain": DOMAIN, "deliverables": deliverables,
                "qa": checks, "text_geometry": geometry, "ffmpeg_version": stdout_version.splitlines()[0],
                "font_hashes": {font.name: sha256(font) for font in fonts},
                "export": "1080 export with mixed 1080/720 sources; no overall 4K claim",
                "limits": ["Silent preview; no music or speech", "No platform upload/publishing validation",
                           "Existing source exposure/compression retained; no AI alteration",
                           "Typography pixel/geometry checks are not an OCR proof; visual review required"]}
    if args.fullscreen:
        manifest.update({"fullscreen": True, "source_provenance": {
            "overview": "Native HEVC, filmed 2026-10-02; September 30 is only the progress cutoff",
            "photo01": "Native site photograph, 2026-10-02; editorial pan/zoom only",
            "photo04": "Native foundation/rebar site photograph, 2026-10-02; editorial pan/zoom only",
            "photo02": "Native yellow-formwork site photograph; capture date unknown; editorial pan/zoom only",
            "render": "Architectural visualization, not live footage", "logo": "Native public logo, unchanged"},
            "timeline": {mode: [{"output_start": i * 6, "source": scene[0], "source_start": scene[1],
                                  "seconds": scene[2], "title": scene[3], "caption": scene[4], "brand_ending": i == 4}
                                 for i, scene in enumerate(FULLSCREEN_SCENES[mode])]
                         for mode in ("landscape", "portrait")},
            "export": "1080 fullscreen export; native 4096x3072 photographs and 1920x1080 videos; not 4K",
            "limits": ["Silent local preview; no publishing/platform/browser validation by this renderer",
                       "Portrait uses deliberately cropped native photos, not live video for its first 18 seconds",
                       "Portrait visualization is a 606x1080 focal crop enlarged to 1080x1920 for six seconds",
                       "Photo motion is editorial pan/zoom; no AI content alteration or invented capture dates",
                       "Text/pixel guards require final human playback review"]})
    manifest_file = write_text("manifest.json", json.dumps(manifest, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps({"manifest": str(manifest_file), "deliverables": deliverables, "qa_passed": True}, indent=2), flush=True)


def self_test():
    require(CTA == "K\u00e9rjen szem\u00e9lyes aj\u00e1nlatot!", "CTA changed")
    require(SCENES[3][1:3] == (72.0, 6), "Render cut changed")
    require(SCENES[3][4] == RENDER, "Render label absent")
    require(sum(scene[2] * FPS for scene in SCENES) + 180 == 900, "Frame total changed")
    require(QA_TIMES == (2, 8, 14, 21, 25, 29), "QA samples changed")
    require(all(len(item[1]) == 64 for item in DEFAULTS.values()), "Default pin length")
    require(filter_path(Path("C:/Windows/Fonts/segoeui.ttf")).startswith("'"), "Filter path quoting")
    require("Budapest, Harmat utca 22." in SCENES[0][4], "Opening address changed")
    require(SCENES[0][3] == "\u00c9p\u00fcl a Harmat Lak\u00f3park", "Opening construction message changed")
    print("9 storyboard/guard self-tests passed")


def expect_refusal(function, *arguments):
    try:
        function(*arguments)
    except RuntimeError:
        return
    raise RuntimeError("Expected guard refusal: " + function.__name__)


def fullscreen_self_test(fonts):
    require(build_scope(False) == (OUTPUT, OWNER, DEFAULTS), "Default v1 scope changed")
    require(FULLSCREEN_OUTPUT != OUTPUT and FULLSCREEN_OWNER != OWNER, "Fullscreen must have a new scope/owner")
    require("archive" not in FULLSCREEN_DEFAULTS, "Fullscreen cannot require the 720 archive")
    require(all(re.fullmatch(r"[a-f0-9]{64}", value[1]) for value in FULLSCREEN_DEFAULTS.values()), "Bad v2 pin")
    require(FULLSCREEN_DEFAULTS["overview"][1] != DEFAULTS["overview"][1], "Fullscreen must pin the native raw overview")
    require(FULLSCREEN_SCENES["landscape"][0][:3] == ("overview", 18.0, 6), "Landscape intro changed")
    require(FULLSCREEN_SCENES["landscape"][2][:3] == ("overview", 24.0, 6), "Landscape late cut changed")
    require([scene[0] for scene in FULLSCREEN_SCENES["portrait"][:3]] == ["photo01", "photo04", "photo02"],
            "Portrait must use native photo proof, not an enlarged camera strip")
    require(FULLSCREEN_SCENES["portrait"][2][4] == PHOTO_UNDATED and "2026" not in PHOTO_UNDATED,
            "Undated photograph cannot acquire a date")
    safe_hungarian_glyphs(fonts)
    count = 10
    for mode in ("landscape", "portrait"):
        scenes = storyboard(mode, True)
        require(len(scenes) == 5 and all(scene[2] == 6 for scene in scenes), "V2 must have five six-second scenes")
        require(sum(scene[2] * FPS for scene in scenes) == 900, "Fullscreen frame count changed")
        require(scenes[0][3] == OPENING and scenes[3][1:3] == (72.0, 6) and scenes[3][4] == RENDER,
                "Fullscreen opening/render provenance changed")
        require(scenes[-1][0] == "photo01", "Ending must use a real image backdrop")
        count += 4
        for index, (role, _, _, title, caption) in enumerate(scenes):
            _, _, styles, logo, _ = fullscreen_layout(mode, index == 4, title, caption, fonts)
            media_filters, _ = fullscreen_media(role, mode)
            require("pad=" not in media_filters and "color=" not in media_filters, "Blank fullscreen media")
            if index == 4:
                require(logo[2] == 250 and any(style["text"] == CTA for style in styles), "Native logo/exact CTA changed")
            count += 2
    good_photo = {"width": 4096, "height": 3072, "orientation": 1}
    fullscreen_source_guard("photo01", good_photo)
    expect_refusal(fullscreen_source_guard, "photo01", {**good_photo, "width": 1280})
    expect_refusal(fullscreen_source_guard, "photo01", {**good_photo, "orientation": 6})
    native = {"width": 1920, "height": 1080, "codec": "hevc", "creation_time": "2026-10-02T08:19:32.000000Z"}
    fullscreen_source_guard("overview", native)
    expect_refusal(fullscreen_source_guard, "overview", {**native, "codec": "h264"})
    expect_refusal(fullscreen_source_guard, "overview", {**native, "creation_time": None})
    expect_refusal(fullscreen_media, "overview", "portrait")
    for metadata in ("Audio: aac", "Data: timed_metadata", "location: coordinate", "creation_time: date", "GPS"):
        expect_refusal(output_metadata_guard, metadata)
    output_metadata_guard("Video: h264, yuv420p, SAR 1:1")
    require(CTA.encode("utf-8").decode("utf-8") == CTA, "UTF-8 CTA changed")
    print(f"{count + 14} fullscreen story/crop/source/metadata/glyph self-tests passed")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ffmpeg", help="FFmpeg executable (otherwise PATH)")
    for role in {**DEFAULTS, **FULLSCREEN_DEFAULTS}:
        parser.add_argument("--" + role + "-source", help="Optional source path; requires its hash pin")
        parser.add_argument("--" + role + "-sha256", help="Independently verified source SHA-256")
    parser.add_argument("--font", default="C:/Windows/Fonts/segoeui.ttf")
    parser.add_argument("--bold-font", default="C:/Windows/Fonts/segoeuib.ttf")
    parser.add_argument("--display-font", default="C:/Windows/Fonts/georgia.ttf")
    parser.add_argument("--overwrite-own", action="store_true", help="Replace only unchanged builder-owned outputs")
    parser.add_argument("--fullscreen", action="store_true", help="Build only full-bleed v2 in its separate output directory")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()
    if args.self_test:
        self_test()
        if args.fullscreen:
            fullscreen_self_test(tuple(Path(value).resolve() for value in (args.font, args.bold_font, args.display_font)))
    else:
        build(args)


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, OSError, subprocess.TimeoutExpired) as error:
        print("Build refused/failed: " + str(error), file=sys.stderr)
        sys.exit(1)
