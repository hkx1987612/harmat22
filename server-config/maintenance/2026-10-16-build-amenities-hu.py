"""Offline, opt-in Hungarian amenities video renderer. No publication rights implied."""

import argparse
import hashlib
import importlib.util
import io
import json
import math
import subprocess
import sys
import unicodedata
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw, ImageFont, ImageStat

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[2]
HELPER = ROOT / "server-config/maintenance/2026-10-12-build-park-hu.py"
spec = importlib.util.spec_from_file_location("amenities_legacy_helpers", HELPER)
legacy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(legacy)
sys.modules["amenities_hu_renderer"] = sys.modules[__name__]
require, sha256 = legacy.require, legacy.sha256
FFMPEG, FONTS, LOGO = legacy.FFMPEG, legacy.FONTS, legacy.LOGO
FOREST, GOLD, WHITE = legacy.FOREST, legacy.GOLD, legacy.WHITE
text_block, bounds = legacy.text_block, legacy.bounds
SOURCE = Path("Z:/01e72510d2a38b9d07d4ce2490624afb_raw.mp4")
SOURCE_HASH = "a90c8c5b253127758bc541cc5acc4dcecb9d17171766fc580d939395f6905ea4"
SOURCE_BYTES = 71254636
COPY = ROOT / "server-config/maintenance/2026-10-16-amenities-hu-copy.json"
OUTPUT = ROOT / "outputs/2026-10-16-amenities-hu/render-v2"
TESTS = ROOT / "server-config/maintenance/2026-10-16-test-amenities-hu.py"
WIDTH, HEIGHT, FPS, FRAMES = 1920, 1080, 30, 1292
INTRO_END, CLOSING_START = 135, 1089
INTRO_BOX, FOOTER_BOX = (320, 430, 1280, 215), (0, 700, 1920, 380)
INTERVALS = tuple(zip(range(0, 1080, 120), [120, 240, 360, 480, 600, 720, 840, 960, 1089]))
OWNER = "harmat-amenities-hu-local-render-v2"


def is_cjk(text):
    ranges = ((0x2E80, 0x33FF), (0x3400, 0x9FFF), (0xF900, 0xFAFF),
              (0x20000, 0x323AF))
    return any(any(a <= ord(c) <= b for a, b in ranges) for c in text)


def validate_copy(copy):
    require(copy["status"] == "PREPARED_LOCAL" and copy["language"] == "hu-HU", "Not prepared Hungarian copy")
    for text in legacy.strings(copy):
        require(not is_cjk(text), "CJK text forbidden")
        require(unicodedata.normalize("NFC", text) == text, "Copy must be NFC")
        require(not any(c in text for c in ("\ufffd", "\u00c3", "\u00c5")), "Damaged accent encoding")
    require(len(copy["scenes"]) == 9, "Exactly nine scenes required")
    pairs = []
    for scene in copy["scenes"]:
        a, b = scene["start_frame"], scene["end_frame"]
        require(type(a) is int and type(b) is int, "Frame intervals must be integers, not booleans/floats")
        pairs.append((a, b))
        require(0 < len(scene["title"]) <= 64 and 0 < len(scene["subtitle"]) <= 120, "Invalid scene text")
    require(tuple(pairs) == INTERVALS, "Scene intervals differ from reviewed continuous zero-based partition")
    require(copy["closing"]["website"] == "https://harmat22.hu/", "Unexpected website")
    require(copy["closing"]["contact"] == "ertekesites@harmat22.hu | +36300733375", "Unexpected sales contacts")
    require(copy["brand"] == copy["intro"]["title"] == copy["closing"]["title"], "Brand mismatch")
    legacy.glyph_check(copy)
    return copy


def load_copy():
    return validate_copy(json.loads(COPY.read_text(encoding="utf-8-sig")))


def source_guard(path=SOURCE):
    require(path.resolve() == SOURCE.resolve(), "Not the pinned source path")
    require(path.stat().st_size == SOURCE_BYTES, "Pinned source byte count mismatch")
    require(sha256(path) == SOURCE_HASH, "Pinned source hash mismatch")


def output_guard(path=OUTPUT):
    require(path.absolute() == OUTPUT.absolute(), "Only the exclusive render-v2 child is writable")
    require(not path.exists(), "No-overwrite: render-v2 already exists")
    for parent in [path, *path.parents]:
        require(not parent.is_symlink() and not parent.is_junction(), "Output cannot traverse a link/junction")


def make_overlays(copy):
    overlays, layouts = [], []
    intro = Image.new("RGBA", (WIDTH, HEIGHT))
    ImageDraw.Draw(intro).rectangle((320, 430, 1599, 644), fill=FOREST)
    layouts.append(text_block(intro, copy["intro"]["title"], (365, 459, 1190, 73), 56, bold=True, center=True))
    layouts.append(text_block(intro, copy["intro"]["subtitle"], (365, 550, 1190, 70), 46, center=True))
    overlays.append(("intro.png", intro))
    for index, scene in enumerate(copy["scenes"], 1):
        image = Image.new("RGBA", (WIDTH, HEIGHT))
        draw = ImageDraw.Draw(image)
        draw.rectangle((0, 700, 1919, 1079), fill=FOREST)
        layouts.append(text_block(image, copy["brand"].upper(), (100, 962, 1720, 36), 24, bold=True, color=GOLD))
        draw.rectangle((100, 916, 1819, 918), fill=GOLD)
        # Keep essential text above Meta's profile/description overlay zone.
        layouts.append(text_block(image, scene["title"], (100, 718, 1720, 88), 60, bold=True, minimum=50))
        layouts.append(text_block(image, scene["subtitle"], (100, 809, 1720, 64), 42, max_lines=1))
        overlays.append((f"scene-{index}.png", image))
    closing = Image.new("RGBA", (WIDTH, HEIGHT), WHITE)
    with Image.open(LOGO) as logo:
        require(logo.mode == "RGBA" and logo.size == (250, 250), "Unexpected genuine logo")
        closing.alpha_composite(logo, (835, 110))
    for key, box, size, bold in [
        ("title", (180, 409, 1560, 114), 84, True),
        ("subtitle", (180, 548, 1560, 70), 42, False),
        ("cta", (180, 705, 1560, 80), 46, True),
        ("website", (180, 821, 1560, 65), 36, False),
        ("contact", (180, 935, 1560, 50), 30, False),
    ]:
        layouts.append(text_block(closing, copy["closing"][key], box, size, bold=bold, center=True, color=FOREST))
    ImageDraw.Draw(closing).rectangle((710, 653, 1209, 656), fill=GOLD)
    overlays.append(("closing.png", closing))
    validate_masks(overlays)
    return overlays, layouts


def validate_masks(overlays):
    require(len(overlays) == 11, "Unexpected overlay count")
    for index, (_, image) in enumerate(overlays):
        require(image.mode == "RGBA" and image.size == (WIDTH, HEIGHT), "Overlay canvas mismatch")
        alpha = image.getchannel("A")
        expected = Image.new("L", (WIDTH, HEIGHT), 0)
        rect = INTRO_BOX if index == 0 else FOOTER_BOX if index < 10 else (0, 0, WIDTH, HEIGHT)
        bounds(*rect)
        x, y, w, h = rect
        ImageDraw.Draw(expected).rectangle((x, y, x + w - 1, y + h - 1), fill=255)
        require(ImageChops.difference(alpha, expected).getbbox() is None, "Mask must be exactly opaque inside and transparent outside")


def enables():
    return ([f"gte(n,0)*lt(n,{INTRO_END})"]
            + [f"gte(n,{a})*lt(n,{b})" for a, b in INTERVALS]
            + [f"gte(n,{CLOSING_START})*lt(n,{FRAMES})"])


def video_command():
    args = ["-n", "-v", "warning", "-threads", "2", "-i", SOURCE]
    names = ["intro.png"] + [f"scene-{i}.png" for i in range(1, 10)] + ["closing.png"]
    for name in names:
        args += ["-loop", "1", "-framerate", FPS, "-threads", "1", "-i", OUTPUT / name]
    filters = ["[0:v:0]setsar=1[v0]"]
    for index, enable in enumerate(enables(), 1):
        filters.append(f"[v{index - 1}][{index}:v]overlay=x=0:y=0:enable='{enable}':shortest=1:eof_action=pass:format=yuv420[v{index}]")
    return args + ["-filter_complex_threads", "1", "-filter_complex", ";".join(filters),
                   "-map", "[v11]", "-an", "-sn", "-dn", *clean_metadata(),
                   "-c:v", "libx264", "-preset", "medium", "-crf", "17", "-threads", "2",
                   "-pix_fmt", "yuv420p", "-fps_mode", "passthrough", "-frames:v", FRAMES,
                   "-fflags", "+bitexact", "-movflags", "+faststart", OUTPUT / "amenities-hu-silent.mp4"]


def clean_metadata():
    return ["-map_metadata", "-1", "-map_metadata:s", "-1", "-map_chapters", "-1",
            "-metadata", "creation_time=", "-metadata:s:v:0", "handler_name=VideoHandler",
            "-metadata:s:v:0", "language=und"]


def execute(args, log=None, commands=None):
    command = [str(FFMPEG), "-hide_banner", "-nostdin", "-filter_threads", "1", *map(str, args)]
    if commands is not None:
        commands.append(command)
    result = subprocess.run(command, capture_output=True, check=False)
    if log is not None:
        log.write_bytes(result.stderr)
    require(result.returncode == 0, result.stderr.decode("utf-8", errors="replace")[-6000:])
    return result.stdout


def check_video(info, audio=False, source=False):
    streams = info["streams"]
    video = [s for s in streams if s["type"] == "vide"]
    require(len(video) == 1, "Expected exactly one video stream")
    v = video[0]
    require((v["width"], v["height"], v["samples"], v["fps"], v["constant_rate"], v["sar"])
            == (WIDTH, HEIGHT, FRAMES, FPS, True, [1, 1]), "Native video contract mismatch")
    require(abs(v["duration"] - FRAMES / FPS) < 0.0001, "Duration mismatch")
    if not source:
        require(v["codec"] == "avc1" and len(streams) == (2 if audio else 1), "Export stream/codec mismatch")
        require(all(s["type"] in ("vide", "soun") for s in streams), "Data/subtitle stream forbidden")
    if audio:
        sounds = [s for s in streams if s["type"] == "soun"]
        require(len(sounds) == 1 and sounds[0]["codec"] == "mp4a", "Exactly one original AAC stream required")
    return v


def decode_report(report):
    rows = [line.split(",") for line in report.splitlines() if line and not line.startswith("#")]
    video = [r for r in rows if int(r[0]) == 0]
    require(len(video) == FRAMES, "Full decode frame count mismatch")
    require("#tb 0: 1/30" in report and "#dimensions 0: 1920x1080" in report, "Decode timebase/dimensions mismatch")
    require([int(r[2]) for r in video] == list(range(FRAMES)), "Decoded video PTS not continuous zero-based")
    require(all(int(r[3]) == 1 and int(r[4]) == WIDTH * HEIGHT * 3 // 2 for r in video), "Decode format mismatch")
    return {"decoded_video_frames": len(video), "decoded_audio_frames": len(rows) - len(video),
            "first_video_pts": 0, "last_video_pts": FRAMES - 1, "timebase": "1/30", "decode_errors": 0}


def full_decode(path, audio, commands):
    args = ["-v", "error", "-xerror", "-err_detect", "explode", "-threads", "2", "-i", path, "-map", "0:v:0"]
    if audio:
        args += ["-map", "0:a:0"]
    report = execute(args + ["-threads", "2", "-pix_fmt", "yuv420p", "-fps_mode", "passthrough",
                             "-f", "framehash", "-hash", "sha256", "-"],
                     OUTPUT / (path.stem + "-decode.log"), commands).decode("ascii")
    verified = decode_report(report)
    (OUTPUT / (path.stem + "-decode.framehash")).write_text(report, encoding="ascii")
    return verified


def stream_hash(path, kind, commands):
    return execute(["-v", "error", "-i", path, "-map", f"0:{kind}:0", "-c", "copy",
                    "-f", "hash", "-hash", "sha256", "-"], commands=commands).decode("ascii").strip()


def sample_frames(copy):
    centers = [(s["start_frame"] + s["end_frame"] - 1) // 2 for s in copy["scenes"]]
    labels = {"opening": 67, "closing": (CLOSING_START + FRAMES - 1) // 2,
              "intro-last": 134, "intro-first-clear": 135, "footage-last": 1088,
              "closing-first": 1089, "last": 1291}
    labels.update({f"scene-{i}": n for i, n in enumerate(centers, 1)})
    seconds = list(range(0, FRAMES, FPS))
    boundaries = {n for a, b in INTERVALS for n in (a, b - 1)}
    return labels, seconds, sorted(set(labels.values()) | set(seconds) | boundaries)


def extract_frames(path, frames, directory, commands):
    directory.mkdir(exist_ok=False)
    expression = "+".join(f"eq(n,{n})" for n in frames)
    execute(["-n", "-v", "error", "-threads", "2", "-i", path, "-map", "0:v:0", "-an", "-sn", "-dn",
             "-vf", f"select='{expression}'", "-fps_mode", "passthrough", "-enc_time_base", "1:30",
             "-threads", "1", "-frame_pts", "1", directory / "frame-%04d.png"],
            OUTPUT / (directory.name + "-extract.log"), commands)
    expected = [f"frame-{n:04d}.png" for n in frames]
    require(sorted(p.name for p in directory.iterdir()) == expected, "QA native frame PTS/names mismatch")
    for path in directory.iterdir():
        with Image.open(path) as image:
            require(image.size == (WIDTH, HEIGHT), "QA frame not native resolution")


def contact_sheet(directory, frames, target):
    columns, tw, th = 4, 480, 300
    sheet = Image.new("RGB", (columns * tw, math.ceil(len(frames) / columns) * th), "#eeeeee")
    font = ImageFont.truetype(str(FONTS["regular"]), 17)
    for i, n in enumerate(frames):
        x, y = (i % columns) * tw, (i // columns) * th
        with Image.open(directory / f"frame-{n:04d}.png") as image:
            sheet.paste(image.convert("RGB").resize((480, 270), Image.Resampling.LANCZOS), (x, y))
        ImageDraw.Draw(sheet).text((x + 8, y + 277), f"frame {n} | {n / FPS:.6f}s", font=font, fill="black")
    sheet.save(target, quality=94)


def pixel_checks(frames, overlays):
    checks = []
    reference = overlays[-1][1].convert("RGB")
    for n in frames:
        with Image.open(OUTPUT / "qa" / f"frame-{n:04d}.png") as image:
            rendered = image.convert("RGB")
        if n >= CLOSING_START:
            mae = sum(ImageStat.Stat(ImageChops.difference(rendered, reference)).mean) / 3
            require(mae < 2, "Closing must cover the complete source")
            checks.append({"frame": n, "closing_reference_rgb_mae": mae})
            continue
        with Image.open(OUTPUT / "qa-source" / f"frame-{n:04d}.png") as image:
            original = image.convert("RGB")
        diff = ImageChops.difference(original, rendered)
        mask = Image.new("L", (WIDTH, HEIGHT), 255)
        draw = ImageDraw.Draw(mask)
        draw.rectangle((0, 698, WIDTH - 1, HEIGHT - 1), fill=0)
        if n < INTRO_END:
            draw.rectangle((318, 428, 1601, 646), fill=0)
        mae = sum(ImageStat.Stat(diff, mask).mean) / 3
        logo_mae = sum(ImageStat.Stat(diff.crop((0, 0, 360, 220))).mean) / 3
        require(mae < 4 and logo_mae < 5, "Unexpected change outside masks/top-left logo")
        checks.append({"frame": n, "outside_masks_rgb_mae": mae, "top_left_rgb_mae": logo_mae})
    return checks


def self_test():
    test_spec = importlib.util.spec_from_file_location("amenities_hu_tests", TESTS)
    module = importlib.util.module_from_spec(test_spec)
    test_spec.loader.exec_module(module)
    return module.run_tests()


def write_json(path, value):
    path.write_text(json.dumps(value, ensure_ascii=True, indent=2) + "\n", encoding="utf-8")


def build(original_audio=False):
    output_guard()
    tests = self_test()
    copy_hash = sha256(COPY)
    copy = load_copy()
    source_guard()
    source_info = legacy.inspect_mp4(SOURCE)
    check_video(source_info, audio=original_audio, source=True)
    overlays, layouts = make_overlays(copy)
    output_guard()
    OUTPUT.mkdir(parents=True, exist_ok=False)
    manifest_path = OUTPUT / "ownership-manifest.json"
    manifest = {"owner": OWNER, "status": "IN_PROGRESS_LOCAL", "rights": "NOT_CONFIRMED", "upload_allowed": False,
                "audio_flags": {"primary_silent": True, "original_audio_requested": original_audio,
                                "voice_added": False, "music_rights": "NOT_CONFIRMED"},
                "source": {"path": str(SOURCE), "sha256": SOURCE_HASH, "bytes": SOURCE_BYTES, "container": source_info},
                "inputs_sha256": {"copy": copy_hash, "renderer": sha256(Path(__file__)), "tests": sha256(TESTS),
                                  "legacy_helpers": sha256(HELPER), "ffmpeg": sha256(FFMPEG), "logo": sha256(LOGO),
                                  "fonts": {k: sha256(v) for k, v in FONTS.items()}},
                "duration_seconds": FRAMES / FPS, "frames": FRAMES, "fps": FPS,
                "dimensions": [WIDTH, HEIGHT], "self_tests": tests, "commands": [], "outputs": {},
                "interval_convention": "zero-based inclusive start, exclusive end",
                "intro": {"box": INTRO_BOX, "frames": [0, INTRO_END]},
                "footer": {"box": FOOTER_BOX, "intervals": INTERVALS},
                "closing": {"opaque_full_frame": True, "frames": [CLOSING_START, FRAMES]},
                "visual_review": "Pending independent parent inspection; no OCR or publication clearance claimed.",
                "source_review_basis": "Parent-reviewed one-second source sheet and native opening/parking/closing boundary.",
                "overwrite_policy": "Exclusive new render-v2 only; never overwrite, resume or delete an existing run.", "files": {}}
    write_json(manifest_path, manifest)
    commands = manifest["commands"]
    try:
        for name, image in overlays:
            image.save(OUTPUT / name, compress_level=6)
        write_json(OUTPUT / "layout-report.json", layouts)
        silent = OUTPUT / "amenities-hu-silent.mp4"
        print("Self-tests passed; rendering native silent H.264 CRF17.", flush=True)
        execute(video_command(), OUTPUT / "render.log", commands)
        outputs = [(silent, False)]
        if original_audio:
            audio = OUTPUT / "amenities-hu-original-audio.mp4"
            execute(["-n", "-v", "warning", "-i", silent, "-i", SOURCE,
                     "-map", "0:v:0", "-map", "1:a:0", "-sn", "-dn", "-c:v", "copy", "-c:a", "copy",
                     *clean_metadata(), "-metadata:s:a:0", "handler_name=SoundHandler",
                     "-metadata:s:a:0", "language=und", "-fflags", "+bitexact", "-movflags", "+faststart", audio],
                    OUTPUT / "original-audio-remux.log", commands)
            outputs.append((audio, True))
        video_hash = stream_hash(silent, "v", commands)
        for path, audio in outputs:
            info = legacy.inspect_mp4(path, clean=True)
            check_video(info, audio)
            decoded = full_decode(path, audio, commands)
            require(stream_hash(path, "v", commands) == video_hash, "Remux changed the H.264 bitstream")
            item = {"sha256": sha256(path), "bytes": path.stat().st_size, "container": info,
                    "decode": decoded, "h264_packet_hash": video_hash, "audio_present": audio}
            if audio:
                original_hash = stream_hash(SOURCE, "a", commands)
                require(stream_hash(path, "a", commands) == original_hash, "Original AAC packet copy mismatch")
                item["original_aac_packet_hash"] = original_hash
                item["aac_packet_copy_verified"] = True
            manifest["outputs"][path.name] = item
        print("Exports fully decoded; extracting exact native QA frames from frame zero.", flush=True)
        labels, seconds, samples = sample_frames(copy)
        extract_frames(silent, samples, OUTPUT / "qa", commands)
        extract_frames(SOURCE, [n for n in samples if n < CLOSING_START], OUTPUT / "qa-source", commands)
        contact_sheet(OUTPUT / "qa", seconds, OUTPUT / "contact-sheet-1sec.jpg")
        contact_sheet(OUTPUT / "qa", sorted(set(labels.values())), OUTPUT / "contact-sheet-keyframes.jpg")
        manifest["qa"] = {"labels": labels, "native_frames": samples, "one_second_frames": seconds,
                          "samples": [{"frame": n, "seconds": n / FPS, "path": f"qa/frame-{n:04d}.png"} for n in samples],
                          "pixel_checks": pixel_checks(samples, overlays),
                          "indexing": "Full-from-zero select(n), passthrough timebase 1/30, frame_pts filenames verified."}
        source_guard()
        require(sha256(COPY) == copy_hash, "Parent copy changed during render; output not approved")
        manifest["source_unchanged"] = True
        manifest["status"] = "PREPARED_LOCAL_REVIEW_PENDING"
    except BaseException as error:
        manifest["status"] = "FAILED_LOCAL"
        manifest["failure"] = str(error)
        raise
    finally:
        manifest["files"] = {str(p.relative_to(OUTPUT)): {"sha256": sha256(p), "bytes": p.stat().st_size}
                             for p in sorted(OUTPUT.rglob("*")) if p.is_file() and p != manifest_path}
        write_json(manifest_path, manifest)
    print(json.dumps({"output": str(OUTPUT), "status": manifest["status"], "rights": manifest["rights"],
                      "outputs": manifest["outputs"], "tests": tests}, indent=2), flush=True)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--render", action="store_true", help="Run self-tests, then create the exclusive new render-v2")
    mode.add_argument("--self-test", action="store_true", help="Explicitly allow local FFmpeg synthetic counter test; no files")
    parser.add_argument("--original-audio", action="store_true", help="Optional packet-copy AAC edition; rights remain NOT_CONFIRMED")
    args = parser.parse_args(argv)
    require(not args.original_audio or args.render, "--original-audio requires explicit --render")
    if args.render:
        build(args.original_audio)
    elif args.self_test:
        print(json.dumps(self_test(), indent=2))
    else:
        copy = load_copy()
        print(json.dumps({"mode": "OFFLINE_SUMMARY_NO_WRITES", "render_opt_in": "--render",
                          "self_test_opt_in": "--self-test", "source": str(SOURCE), "source_sha256_expected": SOURCE_HASH,
                          "source_bytes_expected": SOURCE_BYTES, "output": str(OUTPUT), "fps": FPS,
                          "frames": FRAMES, "duration_seconds": FRAMES / FPS, "scenes": len(copy["scenes"]),
                          "intro_end_exclusive": INTRO_END, "closing_start": CLOSING_START,
                          "rights": "NOT_CONFIRMED", "upload_allowed": False}, indent=2))


if __name__ == "__main__":
    main()
