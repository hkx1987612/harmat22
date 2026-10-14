"""Build and audit one silent native-720p construction compilation.

Only writes to the ignored compilation-v1 directory. Existing accepted renders
are reused after hash verification; failed render attempts are kept separately.
"""

import argparse
import hashlib
import json
import struct
import subprocess
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont, ImageStat


ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "outputs/2026-10-14-site-videos"
OUT = BASE / "compilation-v1"
QA = OUT / "qa"
FFMPEG = Path("C:/Users/Administrator/AppData/Roaming/Python/Python312/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v7.1.exe")
PREVIEW = OUT / "harmat-2026-10-13-compilation-silent.mp4"
FPS = 30
TRANSITION = 0.4
TOTAL = 89.3
PINS = [
    "c7f264744c068aacda543599ca68ed409923e508fb5b67dc38ffafd308f5cbad",
    "223c0324054594f4afd6db70bad98188d8a4a39d201eb271471a23efb9edb95b",
    "427fdd8f0219bb20f3960851dae0fbcc2468d604bc7027e7578ba17231679f94",
    "d290afae1fcad76e4584698e7243bd34d9b0de13376845406d4feb8db4c5bab0",
]
TRIMS = [(2.0, 25.0), (4.0, 28.5), (2.0, 25.0), (4.0, 24.0)]
LABELS = ["A1 \u00e9s A3", "A2 - Pincef\u00f6d\u00e9m betonoz\u00e1sa", "A4 \u00e9s A2", "Helysz\u00edni k\u00f6rk\u00e9p"]
SAMPLES = [0, 1, 4.9, 6, 10, 18, 22.566667, 22.8, 23, 24, 30,
           40, 46.666667, 46.9, 47.1, 48, 54, 64, 69.266667, 69.5,
           69.7, 70.5, 76, 82, 84.3, 85.3, 87, 89.266667]


def sha(path):
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def save_json(path, value):
    path.write_text(json.dumps(value, indent=2, ensure_ascii=True) + "\n", encoding="utf-8")


def verify_sources():
    manifest = json.loads((BASE / "prepared-v1/qa/qa-manifest.json").read_text(encoding="utf-8"))
    verified = []
    for index, item in enumerate(manifest["videos"]):
        source = Path(item["source"])
        prepared = BASE / f"prepared-v1/facebook-silent/site-{index + 1:02}.mp4"
        assert sha(source) == item["source_sha256"], f"Changed original: {source}"
        assert sha(prepared) == PINS[index], f"Changed silent input: {prepared}"
        assert item["exports"]["facebook-silent"]["sha256"] == PINS[index]
        verified.append({"original": str(source), "original_sha256": sha(source),
                         "input": str(prepared), "input_sha256": PINS[index]})
    assert len(verified) == 4
    return verified


def plan():
    QA.mkdir(parents=True, exist_ok=True)
    sources = verify_sources()
    chapters = []
    starts = [0.0, 22.6, 46.7, 69.3]
    ends = [23.0, 47.1, 69.7, 89.3]
    clean = [0.0, 23.0, 47.1, 69.7]
    for i, ((start, end), label) in enumerate(zip(TRIMS, LABELS)):
        chapters.append({"index": i + 1, "label": label, **sources[i],
                         "input_interval_seconds_half_open": [start, end],
                         "input_duration_seconds": end - start,
                         "output_first_visible_seconds": starts[i],
                         "output_last_visible_exclusive_seconds": ends[i],
                         "output_clean_start_seconds": clean[i],
                         "narration_cue_seconds": clean[i],
                         "label_interval_seconds": [6.0, 10.5] if i == 0 else [clean[i] + 0.3, clean[i] + 5.3]})
    result = {"status": "PLANNED_SILENT_PREVIEW_NOT_PUBLISHED", "capture_date": "2026-10-13",
              "capture_date_authority": "User confirmation supersedes prepared-v1 UNCONFIRMED field",
              "preview_path": str(PREVIEW), "duration_seconds": TOTAL,
              "dimensions": [1280, 720], "fps": FPS, "expected_frames": 2679,
              "audio": "No audio streams; narration added later by main agent",
              "speed": "Real time; CFR resampling only, no time lapse or acceleration",
              "chapters": chapters,
              "transitions": [{"type": "cross_dissolve", "duration_seconds": TRANSITION,
                               "output_interval_seconds": [s, round(s + TRANSITION, 3)]}
                              for s in starts[1:]],
              "intro_interval_seconds": [0.0, 5.0],
              "intro_lines": ["Harmat Lak\u00f3park", "\u00c9p\u00edt\u00e9si napl\u00f3", "2026. okt\u00f3ber 13."],
              "closing_interval_seconds": [84.3, 89.3],
              "closing_lines": ["K\u00e9rjen szem\u00e9lyes aj\u00e1nlatot!", "harmat22.hu"],
              "logo": "Omitted; no new or redrawn brand asset", "crf": 18,
              "narration_alignment_note": "Use clean starts 0 / 23 / 47.1 / 69.7 seconds; CTA begins 84.3. Footage order is editorial, not a chronology claim."}
    save_json(OUT / "edit-plan.json", result)
    return result


def plate(name, size, lines):
    image = Image.new("RGBA", size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(image)
    draw.rectangle((0, 0, size[0] - 1, size[1] - 1), fill=(15, 25, 28, 190))
    draw.rectangle((0, 0, 4, size[1] - 1), fill=(211, 181, 94, 255))
    checks = []
    for text, font_size, bold, x, y in lines:
        font_path = Path("C:/Windows/Fonts/segoeuib.ttf" if bold else "C:/Windows/Fonts/segoeui.ttf")
        font = ImageFont.truetype(str(font_path), font_size)
        bounds = draw.textbbox((x, y), text, font=font)
        assert bounds[0] >= 0 and bounds[2] <= size[0] - 18
        assert bounds[1] >= 0 and bounds[3] <= size[1] - 8
        draw.text((x, y), text, font=font, fill="white")
        checks.append({"text": text, "font": str(font_path), "font_sha256": sha(font_path),
                       "font_size": font_size, "bounds": bounds})
    path = OUT / "overlays" / f"{name}.png"
    path.parent.mkdir(exist_ok=True)
    image.save(path)
    return path, checks


def make_overlays(p):
    intro, checks = plate("intro", (510, 158), [
        (p["intro_lines"][0], 38, True, 22, 12),
        (p["intro_lines"][1], 26, False, 24, 65),
        (p["intro_lines"][2], 24, False, 24, 106)])
    overlays = [(intro, 56, 44, 0, 5)]
    typography = [{"plate": str(intro), "checks": checks}]
    for chapter in p["chapters"]:
        path, checks = plate(f"chapter-{chapter['index']:02}", (620 if chapter["index"] == 2 else 370, 64),
                             [(chapter["label"], 28, True, 22, 12)])
        overlays.append((path, 56, 44, *chapter["label_interval_seconds"]))
        typography.append({"plate": str(path), "checks": checks})
    closing, checks = plate("closing", (650, 112), [
        (p["closing_lines"][0], 32, True, 22, 12),
        (p["closing_lines"][1], 28, False, 22, 62)])
    overlays.append((closing, 56, 552, 84.3, TOTAL))
    typography.append({"plate": str(closing), "checks": checks})
    save_json(QA / "typography-bounds.json", typography)
    return overlays


def run(command, log):
    with log.open("wb") as handle:
        proc = subprocess.run(command, stdout=handle, stderr=subprocess.STDOUT)
    if proc.returncode:
        raise RuntimeError(f"Command failed ({proc.returncode}); see {log}")


def render(p):
    if PREVIEW.exists():
        record = QA / "qa-manifest.json"
        assert record.exists(), "Existing unverified preview; inspect/preserve it before retrying"
        assert sha(PREVIEW) == json.loads(record.read_text())["preview"]["sha256"]
        print("Reusing hash-verified existing preview", flush=True)
        return
    overlays = make_overlays(p)
    cmd = [str(FFMPEG), "-hide_banner", "-nostdin", "-n", "-filter_complex_threads", "2"]
    for chapter in p["chapters"]:
        cmd.extend(["-i", chapter["input"]])
    for path, *_ in overlays:
        cmd.extend(["-loop", "1", "-framerate", "30", "-i", str(path)])
    filters = []
    for i, (start, end) in enumerate(TRIMS):
        filters.append(f"[{i}:v]trim=start={start}:end={end},setpts=PTS-STARTPTS,fps=30,settb=1/30,setsar=1,format=yuv420p[v{i}]")
    filters.extend(["[v0][v1]xfade=transition=fade:duration=0.4:offset=22.6[x1]",
                    "[x1][v2]xfade=transition=fade:duration=0.4:offset=46.7[x2]",
                    "[x2][v3]xfade=transition=fade:duration=0.4:offset=69.3[merged]"])
    previous = "merged"
    for i, (_, x, y, start, end) in enumerate(overlays):
        current = f"o{i}"
        filters.append(f"[{previous}][{i + 4}:v]overlay=x={x}:y={y}:enable='gte(t,{start})*lt(t,{end})':eof_action=repeat[{current}]")
        previous = current
    filters.append(f"[{previous}]format=yuv420p[final]")
    graph = ";\n".join(filters)
    (QA / "filtergraph.txt").write_text(graph + "\n", encoding="ascii")
    temporary = OUT / "render-in-progress.mp4"
    assert not temporary.exists(), "Previous unfinished attempt retained; inspect before retry"
    cmd.extend(["-filter_complex", graph, "-map", "[final]", "-an", "-sn", "-dn",
                "-map_metadata", "-1", "-map_chapters", "-1", "-t", str(TOTAL),
                "-c:v", "libx264", "-preset", "medium", "-crf", "18", "-threads", "4",
                "-pix_fmt", "yuv420p", "-r", "30", "-fps_mode", "cfr", "-movflags", "+faststart",
                "-color_primaries", "bt709", "-color_trc", "bt709", "-colorspace", "bt709", str(temporary)])
    save_json(QA / "render-command.json", cmd)
    try:
        run(cmd, QA / "render.log")
    except Exception:
        rejected = OUT / "rejected"
        rejected.mkdir(exist_ok=True)
        if temporary.exists():
            count = len(list(rejected.glob("failed-*.mp4"))) + 1
            temporary.rename(rejected / f"failed-{count:02}.mp4")
        raise
    temporary.rename(PREVIEW)


def boxes(data, start=0, end=None):
    end = len(data) if end is None else end
    while start + 8 <= end:
        size, kind = struct.unpack_from(">I4s", data, start)
        header = 8
        if size == 1:
            size = struct.unpack_from(">Q", data, start + 8)[0]
            header = 16
        if size == 0:
            size = end - start
        assert size >= header and start + size <= end
        yield kind.decode("ascii"), start, start + header, start + size
        start += size
    assert start == end


def container_audit(path):
    data = path.read_bytes()
    top = list(boxes(data))
    kinds = [item[0] for item in top]
    assert kinds.index("moov") < kinds.index("mdat"), "Missing faststart"
    streams = []
    for kind, _, a, b in top:
        if kind != "moov":
            continue
        for child, _, c, d in boxes(data, a, b):
            if child != "trak":
                continue
            stream = {}
            for name, _, e, f in boxes(data, c, d):
                if name == "tkhd":
                    stream["dimensions"] = [struct.unpack_from(">I", data, f - 8)[0] // 65536,
                                            struct.unpack_from(">I", data, f - 4)[0] // 65536]
                if name != "mdia":
                    continue
                for field, _, g, h in boxes(data, e, f):
                    if field == "hdlr":
                        stream["type"] = data[g + 8:g + 12].decode("ascii")
                    if field == "mdhd":
                        offset = 20 if data[g] == 1 else 12
                        timescale = struct.unpack_from(">I", data, g + offset)[0]
                        fmt = ">Q" if data[g] == 1 else ">I"
                        duration = struct.unpack_from(fmt, data, g + offset + 4)[0]
                        stream["duration_seconds"] = duration / timescale
                    if field == "minf":
                        for sub, _, j, k in boxes(data, g, h):
                            if sub != "stbl":
                                continue
                            for table, _, l, m in boxes(data, j, k):
                                if table == "stsz":
                                    stream["frames"] = struct.unpack_from(">I", data, l + 8)[0]
                                if table == "stsd":
                                    stream["codec"] = data[l + 12:l + 16].decode("ascii")
                                if table == "stts":
                                    count = struct.unpack_from(">I", data, l + 4)[0]
                                    stream["sample_timing_runs"] = [
                                        list(struct.unpack_from(">II", data, l + 8 + 8 * i))
                                        for i in range(count)]
            streams.append(stream)
    assert len(streams) == 1 and streams[0]["type"] == "vide", streams
    stream = streams[0]
    assert stream["dimensions"] == [1280, 720]
    assert stream["codec"] == "avc1"
    assert stream["frames"] == 2679
    assert abs(stream["duration_seconds"] - TOTAL) < 0.0001
    timing = stream["sample_timing_runs"]
    assert len(timing) == 1 and timing[0][0] == 2679, "Nonconstant frame cadence"
    stream["fps"] = stream["frames"] / stream["duration_seconds"]
    assert abs(stream["fps"] - FPS) < 0.0001
    return {"top_level_boxes": kinds, "faststart": True, "streams": streams, "audio_stream_count": 0}


def audit(p):
    assert PREVIEW.exists()
    metadata = container_audit(PREVIEW)
    run([str(FFMPEG), "-hide_banner", "-nostdin", "-v", "error", "-xerror", "-err_detect", "explode",
         "-i", str(PREVIEW), "-map", "0", "-f", "null", "-"], QA / "full-decode.log")
    assert (QA / "full-decode.log").stat().st_size == 0
    run([str(FFMPEG), "-hide_banner", "-nostdin", "-i", str(PREVIEW), "-an", "-vf",
         "blackdetect=d=0.1:pix_th=0.10:pic_th=0.98", "-f", "null", "-"], QA / "blackdetect.log")
    assert "black_start:" not in (QA / "blackdetect.log").read_text(encoding="utf-8")
    frames = QA / "frames"
    frames.mkdir(exist_ok=True)
    samples = []
    for time in SAMPLES:
        index = round(time * FPS)
        frame_path = frames / f"frame-{index:04}.png"
        if not frame_path.exists():
            run([str(FFMPEG), "-hide_banner", "-nostdin", "-v", "error", "-n", "-i", str(PREVIEW),
                 "-vf", f"select=eq(n\\,{index})", "-frames:v", "1", str(frame_path)], QA / "frame-extract.log")
        with Image.open(frame_path) as frame:
            assert frame.size == (1280, 720)
            stat = ImageStat.Stat(frame.convert("L"))
            assert stat.mean[0] > 15 and stat.stddev[0] > 15, f"Blank sample: {index}"
            samples.append({"frame": index, "seconds": index / FPS, "path": str(frame_path),
                            "luma_mean": stat.mean[0], "luma_stddev": stat.stddev[0]})
    sheet = Image.new("RGB", (1280, 7 * 204), "white")
    draw = ImageDraw.Draw(sheet)
    font = ImageFont.truetype("C:/Windows/Fonts/segoeui.ttf", 16)
    for i, sample in enumerate(samples):
        x, y = (i % 4) * 320, (i // 4) * 204
        with Image.open(sample["path"]) as frame:
            sheet.paste(frame.resize((320, 180)), (x, y))
        draw.text((x + 8, y + 181), f"{sample['seconds']:.3f} s / frame {sample['frame']}", fill="black", font=font)
    sheet.save(QA / "contact-sheet.jpg", quality=95)
    sources = verify_sources()
    visual_path = QA / "visual-review.json"
    visual = None
    if visual_path.exists():
        visual = json.loads(visual_path.read_text(encoding="utf-8"))
        assert visual["preview_sha256"] == sha(PREVIEW), "Stale visual review"
    report = {"status": "TECHNICAL_QA_PASS_VISUAL_REVIEW_REQUIRED", "preview": {
        "path": str(PREVIEW), "sha256": sha(PREVIEW), "bytes": PREVIEW.stat().st_size,
        "duration_seconds": TOTAL, "dimensions": [1280, 720], "fps": FPS, "pixel_format": "yuv420p",
        "audio_stream_count": 0, "container": metadata}, "full_decode": "PASS_NO_ERRORS",
        "blackdetect": "PASS_NO_98_PERCENT_BLACK_INTERVAL_AT_LEAST_0.1_SECONDS",
        "source_hash_pins_unchanged": sources, "sampled_frames": samples,
        "limits": ["Sampled visual review, not all-frame OCR or every-frame aesthetic review",
                   "No narration, music, platform upload, platform processing, or playback QA",
                   "Original sunlight glare and handheld motion preserved",
                   "No new legal, rights, factual-progress or finish-date audit"]}
    if visual:
        report["status"] = "LOCAL_SILENT_PREVIEW_QA_PASS_NOT_PUBLISHED"
        report["visual_review"] = {"path": str(visual_path), "sha256": sha(visual_path),
                                   "status": visual["status"]}
    report["renderer_sha256"] = sha(Path(__file__))
    report["edit_plan_sha256"] = sha(OUT / "edit-plan.json")
    save_json(QA / "qa-manifest.json", report)
    print(json.dumps({"preview": report["preview"], "plan": str(OUT / "edit-plan.json")}, indent=2), flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["plan", "render", "qa", "all"])
    args = parser.parse_args()
    p = plan()
    print(f"Narration plan ready: {OUT / 'edit-plan.json'}", flush=True)
    if args.action in ("render", "all"):
        render(p)
    if args.action in ("qa", "all"):
        audit(p)


if __name__ == "__main__":
    main()
