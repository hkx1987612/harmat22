"""Local lossless copies: silent Facebook, original-audio YouTube; no upload."""
import hashlib
import json
import re
import subprocess
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "outputs/2026-10-14-site-videos"
SOURCE = BASE / "source"
OUT = BASE / "prepared-v1"
FF = Path("C:/Users/Administrator/AppData/Roaming/Python/Python312/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v7.1.exe")


def digest(path):
    h = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1048576), b""):
            h.update(block)
    return h.hexdigest()


def run(args):
    result = subprocess.run([str(FF), "-hide_banner", "-nostdin", *args], capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stderr.decode("utf-8", "replace"))
    return result


def streamhash(path, include_audio):
    maps = ["-map", "0:v:0"]
    if include_audio:
        maps += ["-map", "0:a?"]
    return run(["-v", "error", "-i", str(path), *maps, "-c", "copy", "-f", "streamhash", "-hash", "sha256", "-"]).stdout.decode().strip()


def probe(path):
    return run(["-i", str(path), "-t", "0", "-f", "null", "-"]).stderr.decode("utf-8", "replace")


def full_decode(path):
    result = run(["-v", "error", "-xerror", "-err_detect", "explode", "-i", str(path), "-map", "0:v:0", "-map", "0:a?", "-fps_mode", "passthrough", "-enc_time_base", "demux", "-f", "null", "-"])
    if result.stderr:
        raise RuntimeError(result.stderr.decode("utf-8", "replace"))


def main():
    sources = sorted(SOURCE.rglob("*.mp4"))
    if len(sources) != 4 or not FF.is_file():
        raise RuntimeError("Expected four source MP4s and the existing FFmpeg binary")
    pins = {str(path): digest(path) for path in sources}
    OUT.mkdir(exist_ok=False)
    for name in ("facebook-silent", "youtube-original", "qa"):
        (OUT / name).mkdir()

    prior = {}
    sizes = {path.stat().st_size for path in sources}
    for path in (ROOT / "outputs").rglob("*.mp4"):
        if BASE not in path.parents and path.stat().st_size in sizes:
            prior.setdefault(digest(path), []).append(str(path))

    font = ImageFont.truetype("C:/Windows/Fonts/arial.ttf", 20)
    report = {"status": "LOCAL_PREPARED_NOT_UPLOADED", "capture_date": "UNCONFIRMED",
              "facebook_audio": "NO_AUDIO_STREAM", "youtube_audio": "ORIGINAL_AUDIO_RETAINED",
              "duplicate_scope": "Exact SHA256 against prior local MP4s with matching size, not perceptual/platform deduplication",
              "videos": []}
    sheets = []
    for index, source in enumerate(sources, 1):
        name = f"site-{index:02d}"
        info = probe(source)
        (OUT / "qa" / f"{name}-source-info.txt").write_text(info, encoding="utf-8")
        match = re.search(r"Duration: (\d+):(\d+):(\d+\.\d+)", info)
        if not match:
            raise RuntimeError("Source duration missing")
        duration = int(match[1]) * 3600 + int(match[2]) * 60 + float(match[3])
        targets = {"youtube-original": OUT / "youtube-original" / f"{name}.mp4",
                   "facebook-silent": OUT / "facebook-silent" / f"{name}.mp4"}
        for kind, target in targets.items():
            maps = ["-map", "0:v:0"]
            maps += ["-map", "0:a?"] if kind == "youtube-original" else ["-an"]
            run(["-v", "error", "-n", "-i", str(source), *maps, "-c", "copy", "-map_metadata", "-1", "-map_metadata:s", "-1", "-map_chapters", "-1", "-movflags", "+faststart", str(target)])
            clean = probe(target)
            (OUT / "qa" / f"{name}-{kind}-info.txt").write_text(clean, encoding="utf-8")
            if re.search(r"location|creation_time|com\.apple|make\s*:|model\s*:", clean, re.I):
                raise RuntimeError("Source location/device/date metadata survived")
            if kind == "facebook-silent" and re.search(r"Stream #.*Audio:", clean):
                raise RuntimeError("Silent export unexpectedly has an audio stream")
            if streamhash(source, kind == "youtube-original") != streamhash(target, kind == "youtube-original"):
                raise RuntimeError("Compressed stream parity failed")
            full_decode(target)
        full_decode(source)

        sheet = Image.new("RGB", (1280, 650), "white")
        draw = ImageDraw.Draw(sheet)
        draw.text((12, 8), f"{name} | source unchanged | FB silent / YT original audio", fill="black", font=font)
        for frame_index, fraction in enumerate((.03, .16, .3, .43, .57, .7, .84, .97)):
            time = duration * fraction
            frame = OUT / "qa" / f"{name}-frame-{frame_index + 1:02d}.png"
            run(["-v", "error", "-n", "-ss", f"{time:.6f}", "-i", str(source), "-frames:v", "1", str(frame)])
            with Image.open(frame) as image:
                image.thumbnail((310, 245))
                x, y = (frame_index % 4) * 320, 45 + (frame_index // 4) * 300
                sheet.paste(image, (x + (320 - image.width) // 2, y))
                draw.text((x + 8, y + 250), f"{time:.2f} s", fill="black", font=font)
        sheetpath = OUT / "qa" / f"{name}-contact-sheet.jpg"
        sheet.save(sheetpath, quality=92)
        sheets.append(sheetpath)
        report["videos"].append({"name": name, "source": str(source), "source_sha256": pins[str(source)],
                                  "source_bytes": source.stat().st_size, "duration_seconds_rounded_container": duration,
                                  "source_stream_lines": [line.strip() for line in info.splitlines() if "Stream #" in line],
                                  "compressed_video_sha256": streamhash(source, False),
                                  "exact_prior_duplicates": prior.get(pins[str(source)], []),
                                  "exports": {kind: {"path": str(path), "sha256": digest(path), "full_decode": "PASS", "compressed_stream_parity": "PASS"} for kind, path in targets.items()},
                                  "source_full_decode": "PASS", "contact_sheet": str(sheetpath)})
        print(f"Prepared {name}: {duration:.2f}s", flush=True)

    if pins != {str(path): digest(path) for path in sources}:
        raise RuntimeError("Source files changed during preparation")
    report["sources_unchanged"] = True
    overview = Image.new("RGB", (1280, 2600), "white")
    for index, path in enumerate(sheets):
        with Image.open(path) as image:
            overview.paste(image, (0, index * 650))
    overview.save(OUT / "qa" / "all-four-contact-sheet.jpg", quality=92)
    (OUT / "qa" / "qa-manifest.json").write_text(json.dumps(report, ensure_ascii=True, indent=2), encoding="utf-8")
    print(json.dumps(report, ensure_ascii=True, indent=2))


if __name__ == "__main__":
    main()
