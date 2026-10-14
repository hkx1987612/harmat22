"""Offline narration sidecar; writes only to ignored narrated-local-v1.

Usage: python 2026-10-14-mux-local-narration.py render|qa|all
       python 2026-10-14-mux-local-narration.py qa --self-test
render registers a hash-pinned partial; qa promotes it only after all checks.
No listening, native-speaker review, publication or rights clearance is claimed.
"""

import argparse
import array
import copy
import hashlib
import json
import math
import re
import struct
import subprocess
import sys
import tempfile
import wave
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "outputs/2026-10-14-site-videos"
OUT = BASE / "narrated-local-v1"
AUDIO = BASE / "local-tts-v1/audio"
MANIFEST = AUDIO / "narration-manifest.json"
SOURCE = BASE / "compilation-v1/harmat-2026-10-13-compilation-silent.mp4"
SOURCE_PIN = "12ad6ba546040acf692e1e1dcb9857d9b1f4e2add6a558ee83ded4fbebbeb8ed"
FFMPEG = Path("C:/Users/Administrator/AppData/Roaming/Python/Python312/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v7.1.exe")
PREVIEW = OUT / "harmat-2026-10-13-compilation-hu.mp4"
STATE = OUT / "render-state.json"
REPORT = OUT / "qa-manifest.json"
TOTAL = 89.3
RATE = 48000
CUES = (0.5, 23.0, 47.1, 69.7)
LIMITS = (21.9, 23.5, 22.0, 19.35)
TARGET = "loudnorm=I=-18:TP=-2:LRA=7"
PASS = "TECHNICAL_QA_PASS_USER_AUDIO_VISUAL_APPROVAL_REQUIRED_NOT_PUBLISHED"


def require(condition, message):
    if not condition:
        raise ValueError(message)


def sha(path):
    with Path(path).open("rb") as handle:
        digest = hashlib.file_digest(handle, "sha256")
    return digest.hexdigest()


def owned(path):
    path = Path(path).resolve()
    require(path.is_relative_to(OUT.resolve()), f"Write outside owned directory: {path}")
    return path


def save(path, value):
    with owned(path).open("x", encoding="utf-8") as handle:
        json.dump(value, handle, indent=2, ensure_ascii=True, allow_nan=False)
        handle.write("\n")


def read(path):
    return json.loads(Path(path).read_text(encoding="utf-8"))


def pcm(path, rate=None):
    with wave.open(str(path), "rb") as handle:
        require(handle.getcomptype() == "NONE" and handle.getnchannels() == 1
                and handle.getsampwidth() == 2, f"Not mono 16-bit PCM: {path}")
        sample_rate, frames = handle.getframerate(), handle.getnframes()
        require(rate is None or sample_rate == rate, f"Wrong sample rate: {path}")
        raw = handle.readframes(frames)
    require(frames > 0 and len(raw) == frames * 2, f"Empty/truncated WAV: {path}")
    samples = array.array("h", raw)
    if sys.byteorder != "little":
        samples.byteswap()
    return sample_rate, raw, samples


def stats(samples):
    require(len(samples) > 0, "Empty sample interval")
    peak = max(abs(x) for x in samples) / 32768
    rms = math.sqrt(sum(x * x for x in samples) / len(samples)) / 32768
    db = lambda x: 20 * math.log10(x) if x > 0 else -200.0
    return {"frames": len(samples), "sample_peak_dbfs": db(peak), "rms_dbfs": db(rms)}


def validate_sections(manifest, directory=AUDIO):
    sections = manifest["sections"]
    require(len(sections) == 4, "Exactly four narration sections required")
    result = []
    previous_end = 0.0
    for i, section in enumerate(sections):
        require(section["index"] == i + 1, "Invalid section order/index")
        path = Path(section["path"])
        if not path.is_absolute():
            path = ROOT / path
        path = path.resolve()
        require(path == (directory / f"section-{i + 1:02}.wav").resolve(), "Unexpected WAV path")
        require(sha(path) == section["sha256"], f"WAV hash mismatch: {path}")
        rate, _, samples = pcm(path, 22050)
        seconds = len(samples) / rate
        start, maximum = float(section["start_seconds"]), float(section["max_seconds"])
        require(all(math.isfinite(x) for x in (start, maximum, float(section["seconds"]))),
                "Nonfinite manifest timing")
        require(abs(seconds - section["seconds"]) <= 1 / rate, "Wrong manifest WAV duration")
        require(abs(start - CUES[i]) < 1e-6 and abs(maximum - LIMITS[i]) < 1e-6,
                "Unexpected cue or duration limit")
        require(seconds <= maximum and start >= previous_end and start + seconds < 89.05,
                "Narration overlaps or needs truncation")
        measured = stats(samples)
        require(measured["rms_dbfs"] > -50 and measured["sample_peak_dbfs"] > -40,
                "Empty or effectively silent narration")
        result.append({"index": i + 1, "path": str(path), "sha256": section["sha256"],
                       "seconds": seconds, "source_frames": len(samples), "start_seconds": start,
                       "max_seconds": maximum, "input_level": measured})
        previous_end = start + seconds
    return result


def inputs():
    require(sha(SOURCE) == SOURCE_PIN, "Silent video pin mismatch")
    manifest_hash = sha(MANIFEST)
    manifest = read(MANIFEST)
    for key in ("original_audio_included", "background_music_included", "cloud_generation"):
        require(manifest.get(key) is False, f"Manifest must explicitly forbid {key}")
    require(manifest.get("provider_spend") == 0, "Only zero-spend inputs accepted")
    sections = validate_sections(manifest)
    require(sha(MANIFEST) == manifest_hash, "Narration manifest changed during validation")
    return {"video_path": str(SOURCE), "video_sha256": SOURCE_PIN,
            "narration_manifest_path": str(MANIFEST), "narration_manifest_sha256": manifest_hash,
            "sections": sections, "voice": manifest.get("voice"),
            "model_pins_from_parent_manifest": manifest.get("model_pins"),
            "script_sha256": sha(Path(__file__)), "ffmpeg_sha256": sha(FFMPEG)}


def boxes(data, start=0, end=None):
    end = len(data) if end is None else end
    while start < end:
        require(start + 8 <= end, "Truncated MP4 box")
        size, kind = struct.unpack_from(">I4s", data, start)
        header = 8
        if size == 1:
            require(start + 16 <= end, "Truncated large MP4 box")
            size = struct.unpack_from(">Q", data, start + 8)[0]
            header = 16
        if size == 0:
            size = end - start
        require(size >= header and start + size <= end, "Invalid MP4 box size")
        yield kind.decode("ascii"), start, start + header, start + size
        start += size


def container(path):
    data = Path(path).read_bytes()
    top = list(boxes(data))
    kinds = [item[0] for item in top]
    require(kinds.count("moov") == 1 and "mdat" in kinds
            and kinds.index("moov") < kinds.index("mdat"), "Missing faststart")
    streams = []
    for name, _, a, b in top:
        if name != "moov":
            continue
        for child, _, c, d in boxes(data, a, b):
            if child != "trak":
                continue
            stream = {}
            for field, _, e, f in boxes(data, c, d):
                if field == "tkhd":
                    stream["dimensions"] = [struct.unpack_from(">I", data, f - 8)[0] // 65536,
                                            struct.unpack_from(">I", data, f - 4)[0] // 65536]
                if field != "mdia":
                    continue
                for name, _, g, h in boxes(data, e, f):
                    if name == "hdlr":
                        stream["type"] = data[g + 8:g + 12].decode("ascii")
                    elif name == "mdhd":
                        require(data[g] in (0, 1), "Unknown mdhd version")
                        offset = 20 if data[g] == 1 else 12
                        scale = struct.unpack_from(">I", data, g + offset)[0]
                        require(scale > 0, "Invalid stream timescale")
                        duration = struct.unpack_from(">Q" if data[g] == 1 else ">I",
                                                      data, g + offset + 4)[0]
                        stream.update(timescale=scale, duration_seconds=duration / scale)
                    elif name == "minf":
                        for sub, _, j, k in boxes(data, g, h):
                            if sub != "stbl":
                                continue
                            for table, _, l, m in boxes(data, j, k):
                                if table == "stsz":
                                    stream["samples"] = struct.unpack_from(">I", data, l + 8)[0]
                                elif table == "stts":
                                    count = struct.unpack_from(">I", data, l + 4)[0]
                                    require(l + 8 + count * 8 <= m, "Invalid stts")
                                    stream["timing_runs"] = [list(struct.unpack_from(">II", data, l + 8 + n * 8))
                                                             for n in range(count)]
                                elif table == "stsd":
                                    require(struct.unpack_from(">I", data, l + 4)[0] == 1,
                                            "Multiple sample descriptions not supported")
                                    entry = list(boxes(data, l + 8, m))
                                    require(len(entry) == 1, "Unknown sample description")
                                    codec, _, payload, _ = entry[0]
                                    stream["codec"] = codec
                                    if codec == "mp4a":
                                        stream["sample_entry_channels"] = struct.unpack_from(">H", data, payload + 16)[0]
                                        stream["sample_rate"] = struct.unpack_from(">I", data, payload + 24)[0] / 65536
            streams.append(stream)
    return {"faststart": True, "top_level_boxes": kinds, "streams": streams}


def video_check(info, audio_count):
    streams = info["streams"]
    videos = [s for s in streams if s.get("type") == "vide"]
    audios = [s for s in streams if s.get("type") == "soun"]
    require(len(videos) == 1 and len(audios) == audio_count
            and len(streams) == 1 + audio_count, "Unexpected stream count/type")
    video = videos[0]
    require(video["codec"] == "avc1" and video["dimensions"] == [1280, 720]
            and video["samples"] == 2679 and abs(video["duration_seconds"] - TOTAL) < 1e-6,
            "Video geometry/frame count/duration changed")
    runs = video["timing_runs"]
    require(len(runs) == 1 and runs[0][0] == 2679
            and abs(video["timescale"] / runs[0][1] - 30) < 1e-6, "Not original 30fps cadence")
    if audios:
        audio = audios[0]
        require(audio["codec"] == "mp4a" and audio["sample_rate"] == RATE
                and abs(audio["duration_seconds"] - TOTAL) <= 0.05, "Unexpected AAC format/duration")


def run(args, directory, name):
    command = [str(FFMPEG), "-hide_banner", "-nostdin", *args]
    save(directory / f"{name}-command.json", command)
    proc = subprocess.run(command, capture_output=True, timeout=600)
    owned(directory / f"{name}.stdout").write_bytes(proc.stdout)
    owned(directory / f"{name}.log").write_bytes(proc.stderr)
    require(proc.returncode == 0, f"FFmpeg failed ({proc.returncode}); see {directory / (name + '.log')}")
    return proc.stdout.decode("utf-8", errors="replace"), proc.stderr.decode("utf-8", errors="replace")


def loudness(log):
    # FFmpeg loudnorm emits a single JSON object amidst its diagnostic lines.
    matches = re.findall(r'\{\s*"input_i".*?\}', log, flags=re.DOTALL)
    require(len(matches) == 1, "Missing/ambiguous loudnorm JSON")
    result = json.loads(matches[0])
    for key in ("input_i", "input_tp", "input_lra", "input_thresh", "target_offset"):
        require(math.isfinite(float(result[key])), f"Nonfinite loudness: {key}")
    return result


def registered(provenance):
    if PREVIEW.exists():
        require(REPORT.exists(), "Existing unverified preview retained; refusing reuse")
        report = read(REPORT)
        require(report["status"] == PASS and report["inputs_before"] == provenance
                and report["inputs_after"] == provenance
                and report["preview"]["sha256"] == sha(PREVIEW), "Stale/unknown existing preview")
        return "verified"
    require(not REPORT.exists(), "QA manifest exists without preview")
    if STATE.exists():
        state = read(STATE)
        require(state["inputs"] == provenance, "Stale render state")
        for artifact in state["artifacts"]:
            path = owned(artifact["path"])
            require(sha(path) == artifact["sha256"], f"Changed render artifact: {path}")
        return state
    return None


def render(provenance):
    existing = registered(provenance)
    if existing is not None:
        return existing
    video_check(container(SOURCE), 0)
    directory = Path(tempfile.mkdtemp(prefix="render-", dir=OUT))
    normalized = []
    for section in provenance["sections"]:
        i = section["index"]
        _, log = run(["-i", section["path"], "-map", "0:a:0", "-af",
                      f"aresample={RATE},{TARGET}:print_format=json", "-f", "null", "-"], directory, f"measure-{i:02}")
        measurement = loudness(log)
        graph = f"aresample={RATE},{TARGET}:measured_I={measurement['input_i']}"
        graph += f":measured_TP={measurement['input_tp']}:measured_LRA={measurement['input_lra']}"
        graph += f":measured_thresh={measurement['input_thresh']}:offset={measurement['target_offset']}"
        graph += f":linear=true:print_format=json,aresample={RATE}"
        path = directory / f"normalized-{i:02}.wav"
        _, log = run(["-n", "-i", section["path"], "-map", "0:a:0", "-af", graph,
                      "-ar", str(RATE), "-ac", "1", "-c:a", "pcm_s16le", str(path)], directory, f"normalize-{i:02}")
        _, raw, samples = pcm(path, RATE)
        require(abs(len(samples) - round(section["seconds"] * RATE)) <= 2,
                "Normalization changed paragraph duration")
        measured = stats(samples)
        require(measured["sample_peak_dbfs"] <= -1.0, "Normalized speech clips/exceeds ceiling")
        normalized.append({**section, "normalized_path": str(path), "normalized_frames": len(samples),
                           "normalized_seconds": len(samples) / RATE, "normalized_sha256": sha(path),
                           "normalization_measurement": measurement, "normalization_result": loudness(log),
                           "normalized_level": measured})
    timeline = bytearray(round(TOTAL * RATE) * 2)
    previous_end = 0
    for section in normalized:
        _, raw, _ = pcm(section["normalized_path"], RATE)
        start = round(section["start_seconds"] * RATE)
        end = start + section["normalized_frames"]
        require(start >= previous_end and end / RATE < 89.05
                and section["normalized_seconds"] <= section["max_seconds"], "Normalized paragraph overrun")
        timeline[start * 2:end * 2] = raw
        previous_end = end
    timeline_path = directory / "narration-timeline.wav"
    with wave.open(str(owned(timeline_path)), "wb") as handle:
        handle.setnchannels(1)
        handle.setsampwidth(2)
        handle.setframerate(RATE)
        handle.writeframes(timeline)
    partial = directory / "render-in-progress.mp4"
    run(["-n", "-i", str(SOURCE), "-i", str(timeline_path), "-map", "0:v:0", "-map", "1:a:0",
         "-map_metadata", "-1", "-map_chapters", "-1", "-c:v", "copy", "-c:a", "aac",
         "-b:a", "192k", "-ar", str(RATE), "-ac", "1", "-threads", "1",
         "-movflags", "+faststart", "-metadata:s:a:0", "language=hun", str(partial)], directory, "mux")
    require(inputs() == provenance, "Source pins changed while rendering")
    artifacts = [{"path": str(path), "sha256": sha(path)} for path in
                 [partial, timeline_path, *[Path(s["normalized_path"]) for s in normalized]]]
    state = {"status": "RENDERED_PARTIAL_QA_REQUIRED", "inputs": provenance,
             "directory": str(directory), "partial": str(partial), "timeline": str(timeline_path),
             "segments": normalized, "artifacts": artifacts,
             "aac_encoder": {"codec": "aac", "sample_rate": RATE, "channels": 1,
                             "requested_bitrate_bits_per_second": 192000}}
    save(STATE, state)
    return state


def hash_result(output, stream=False):
    pattern = r"^0,v,SHA256=([0-9a-f]{64})$" if stream else r"^SHA256=([0-9a-f]{64})$"
    match = re.fullmatch(pattern, output.strip())
    require(match is not None, "Unexpected FFmpeg hash output")
    require(match[1] != hashlib.sha256(b"").hexdigest(), "Empty hashed video/frame")
    return match[1]


def audio_intervals(samples, segments, encoded):
    result = {"speech": [], "gaps": []}
    previous_end = 0
    margin = round(0.08 * RATE) if encoded else 0
    for section in segments:
        start = round(section["start_seconds"] * RATE)
        end = start + section["normalized_frames"]
        require(end <= len(samples), "Decoded audio truncated a paragraph")
        speech = stats(samples[start:end])
        require(speech["rms_dbfs"] > -50 and speech["sample_peak_dbfs"] > -40,
                "Missing speech in expected cue")
        result["speech"].append({"index": section["index"], "start_seconds": start / RATE,
                                 "end_seconds": end / RATE, **speech})
        if start - margin > previous_end + margin:
            gap = stats(samples[previous_end + margin:start - margin])
            require(gap["sample_peak_dbfs"] <= (-55 if encoded else -200)
                    and gap["rms_dbfs"] <= (-70 if encoded else -200), "Unexpected audio in gap")
            result["gaps"].append({"start_seconds": (previous_end + margin) / RATE,
                                   "end_seconds": (start - margin) / RATE, **gap})
        previous_end = end
    if previous_end + margin < len(samples) - margin:
        tail = stats(samples[previous_end + margin:len(samples) - margin])
        require(tail["sample_peak_dbfs"] <= (-55 if encoded else -200)
                and tail["rms_dbfs"] <= (-70 if encoded else -200), "Unexpected tail audio")
        result["gaps"].append({"start_seconds": (previous_end + margin) / RATE,
                               "end_seconds": (len(samples) - margin) / RATE, **tail})
    return result


def audit(provenance):
    state = registered(provenance)
    if state == "verified":
        print(json.dumps({"status": PASS, "reused_hash_verified_preview": str(PREVIEW)}))
        return
    require(isinstance(state, dict), "No registered render; run render or all first")
    partial = owned(state["partial"])
    directory = Path(tempfile.mkdtemp(prefix="qa-", dir=OUT))
    source_info, final_info = container(SOURCE), container(partial)
    video_check(source_info, 0)
    video_check(final_info, 1)
    stdout, stderr = run(["-v", "error", "-xerror", "-err_detect", "explode", "-i", str(partial),
                          "-map", "0", "-progress", "pipe:1", "-nostats", "-f", "null", "-"],
                         directory, "full-decode")
    require(not stderr.strip(), "Full decode produced errors")
    progress = dict(line.split("=", 1) for line in stdout.splitlines() if "=" in line)
    require(progress.get("progress") == "end" and int(progress.get("frame", 0)) == 2679,
            "Full decode did not retain all 2679 frames")
    compressed, last = [], []
    for label, path in (("source", SOURCE), ("final", partial)):
        output, _ = run(["-v", "error", "-i", str(path), "-map", "0:v:0", "-c:v", "copy",
                         "-f", "streamhash", "-hash", "sha256", "-"], directory, f"{label}-streamhash")
        compressed.append(hash_result(output, True))
        output, _ = run(["-v", "error", "-i", str(path), "-map", "0:v:0", "-vf",
                         "select=eq(n\\,2678)", "-frames:v", "1", "-f", "hash", "-hash", "sha256", "-"],
                        directory, f"{label}-final-frame")
        last.append(hash_result(output))
    require(compressed[0] == compressed[1], "Compressed video bitstream changed")
    require(last[0] == last[1], "Final decoded frame changed/missing")
    decoded = directory / "decoded-aac.wav"
    run(["-n", "-v", "info", "-i", str(partial), "-map", "0:a:0", "-c:a", "pcm_s16le", str(decoded)],
        directory, "decode-aac")
    _, _, samples = pcm(decoded, RATE)
    require(abs(len(samples) / RATE - TOTAL) <= 0.05, "Decoded AAC duration out of tolerance")
    # MP4 sample entries can say stereo even for mono AAC. The decoder checks the actual AAC channel config.
    level = stats(samples)
    require(level["sample_peak_dbfs"] <= -1, "AAC sample peak exceeds -1 dBFS")
    decoded_intervals = audio_intervals(samples, state["segments"], True)
    _, _, timeline = pcm(state["timeline"], RATE)
    require(len(timeline) == round(TOTAL * RATE), "Timeline duration changed")
    timeline_intervals = audio_intervals(timeline, state["segments"], False)
    _, log = run(["-i", str(partial), "-map", "0:a:0", "-af", f"{TARGET}:print_format=json",
                  "-f", "null", "-"], directory, "aac-loudness")
    measured_loudness = loudness(log)
    require(float(measured_loudness["input_tp"]) <= -1, "AAC true peak exceeds -1 dBTP")
    after = inputs()
    require(after == provenance, "Source pins changed during QA")
    require(registered(after) == state, "Render artifacts changed during QA")
    report = {"status": PASS, "inputs_before": provenance, "inputs_after": after,
              "preview": {"path": str(PREVIEW), "sha256": sha(partial), "bytes": partial.stat().st_size,
                          "container": final_info}, "source_container": source_info,
              "full_decode": {"status": "PASS_NO_ERRORS", "frames": 2679},
              "compressed_video_sha256": {"source": compressed[0], "final": compressed[1], "exact_match": True},
              "final_decoded_frame_sha256": {"source": last[0], "final": last[1], "exact_match": True},
              "aac": {**state["aac_encoder"], "decoded_duration_seconds": len(samples) / RATE,
                      "decoded_channel_count": 1, "level": level, "loudnorm_measurement": measured_loudness,
                      "interval_checks": decoded_intervals, "decoded_wav_sha256": sha(decoded)},
              "timeline": {"sha256": sha(Path(state["timeline"])), "interval_checks": timeline_intervals},
              "segments": state["segments"], "qa_directory": str(directory),
              "limits": ["No listening, transcription or native Hungarian pronunciation/intelligibility review",
                         "User must preview and approve narration and synchronization before any publication",
                         "No browser, upload, publication, website, Ads, cloud or paid service action",
                         "Model/license facts are parent-manifest provenance, not independently audited here",
                         "AAC gap checks exclude 80ms at boundaries for codec ringing; timeline gaps are exact silence",
                         "192k is requested encoder bitrate, not a claim of constant measured AAC packet bitrate",
                         "Only video packet aggregate hash and final decoded frame checked; no new aesthetic/OCR review"]}
    save(directory / "qa-manifest.json", report)
    partial.rename(owned(PREVIEW))
    save(REPORT, report)
    print(json.dumps({"status": PASS, "preview": str(PREVIEW), "sha256": report["preview"]["sha256"],
                      "qa_manifest": str(REPORT)}, indent=2), flush=True)


def self_test():
    directory = Path(tempfile.mkdtemp(prefix="validation-fixtures-", dir=OUT))
    sections = []
    for i, cue in enumerate(CUES):
        path = directory / f"section-{i + 1:02}.wav"
        with wave.open(str(owned(path)), "wb") as handle:
            handle.setparams((1, 2, 22050, 0, "NONE", "not compressed"))
            handle.writeframes(struct.pack("<h", 4096) * 22050)
        sections.append({"index": i + 1, "path": str(path), "sha256": sha(path), "seconds": 1.0,
                         "start_seconds": cue, "max_seconds": LIMITS[i]})
    fixture = {"sections": sections}
    validate_sections(fixture, directory)
    passed = ["valid_mono_pcm_four_sections"]
    mutations = [("bad_hash", "sha256", "0" * 64), ("bad_index", "index", 2),
                 ("bad_cue", "start_seconds", 0), ("duration_mismatch", "seconds", 2),
                 ("nonfinite_time", "seconds", float("nan")), ("bad_max", "max_seconds", 0.5),
                 ("wrong_path", "path", str(SOURCE))]
    for name, key, value in mutations:
        invalid = copy.deepcopy(fixture)
        invalid["sections"][0][key] = value
        try:
            validate_sections(invalid, directory)
        except (ValueError, KeyError, wave.Error):
            passed.append(name)
        else:
            raise ValueError(f"Validation accepted {name}")
    for name, data in (("short_box", b"bad"), ("oversized_box", struct.pack(">I4s", 99, b"moov"))):
        try:
            list(boxes(data))
        except ValueError:
            passed.append(name)
        else:
            raise ValueError(f"Parser accepted {name}")
    try:
        owned(SOURCE)
    except ValueError:
        passed.append("write_scope_guard")
    else:
        raise ValueError("Write guard accepted source")
    require(hash_result("0,v,SHA256=" + "a" * 64, True) == "a" * 64, "Hash parser failed")
    passed.append("streamhash_parser")
    save(directory / "self-test-results.json", {"status": "PASS", "checks": passed})
    print(json.dumps({"self_test": "PASS", "count": len(passed), "evidence": str(directory)}, indent=2))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=("render", "qa", "all"))
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()
    OUT.mkdir(parents=True, exist_ok=True)
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
        print(f"STOPPED: {exc}. Partials/logs retained; no upload authorized.", file=sys.stderr)
        sys.exit(1)
