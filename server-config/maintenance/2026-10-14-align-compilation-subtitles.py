"""Local ASR-assisted subtitle timing for approved narration; no cloud/upload."""
import argparse
import difflib
import hashlib
import json
import re
import subprocess
from pathlib import Path

import numpy as np
from faster_whisper import WhisperModel
from huggingface_hub import snapshot_download

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "outputs/2026-10-14-site-videos"
OUT = BASE / "subtitled-v1"
ASR = OUT / "asr"
MODEL_REV = "536b0662742c02347bc0e980a01041f333bce120"
NARRATION = BASE / "local-tts-v1/audio/narration-manifest.json"
FF = "C:/Users/Administrator/AppData/Roaming/Python/Python312/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v7.1.exe"
SOURCE = BASE / "narrated-local-v1/harmat-2026-10-13-compilation-hu.mp4"
SOURCE_PIN = "f73755ed82ab62ae4adf87daba394c9736790e03cbe336893cd72c3bc9d72a84"


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def recognize():
    ASR.mkdir(parents=True, exist_ok=True)
    if (ASR / "recognized-words.json").exists():
        raise RuntimeError("Existing recognition evidence retained; do not overwrite")
    manifest = json.loads(NARRATION.read_text(encoding="utf-8"))
    model_dir = snapshot_download("Systran/faster-whisper-small", revision=MODEL_REV,
                                  token=False, local_dir=str(ASR / "model"),
                                  allow_patterns=["model.bin", "config.json", "tokenizer.json", "vocabulary.txt", "README.md"])
    model = WhisperModel(model_dir, device="cpu", compute_type="int8", cpu_threads=4,
                         local_files_only=True)
    results = []
    for section in manifest["sections"]:
        audio = Path(section["path"])
        if sha(audio) != section["sha256"]:
            raise RuntimeError("Narration source changed")
        # PyAV19 removed a keyword used by this recognizer; use the existing audited FFmpeg decoder.
        decoded = subprocess.run([FF, "-hide_banner", "-v", "error", "-nostdin", "-i", str(audio),
                                  "-ac", "1", "-ar", "16000", "-f", "f32le", "-"],
                                 capture_output=True, check=True)
        samples = np.frombuffer(decoded.stdout, dtype="<f4")
        if samples.size == 0 or not np.isfinite(samples).all():
            raise RuntimeError("Invalid decoded audio")
        segments, info = model.transcribe(samples, language="hu", beam_size=5,
                                         initial_prompt=section["text"], temperature=0,
                                         condition_on_previous_text=False,
                                         word_timestamps=True, vad_filter=False)
        records = [{"start": s.start, "end": s.end, "text": s.text,
                    "words": [{"start": w.start, "end": w.end, "word": w.word,
                               "probability": w.probability} for w in (s.words or [])]}
                   for s in segments]
        results.append({"index": section["index"], "audio_sha256": section["sha256"],
                        "offset": section["start_seconds"], "duration": section["seconds"],
                        "reference": section["text"], "language": info.language,
                        "segments": records})
        print(json.dumps({"section": section["index"], "segments": records}, ensure_ascii=True), flush=True)
    evidence = {"status": "ASR_TIMING_REVIEW_REQUIRED", "engine": "faster-whisper1.2.1 CPU int8",
                "model": "Systran/faster-whisper-small", "revision": MODEL_REV,
                "model_files": {p.name: sha(p) for p in Path(model_dir).iterdir() if p.is_file()},
                "narration_manifest_sha256": sha(NARRATION), "sections": results,
                "limits": ["Initial prompt uses approved reference text", "Not native-Hungarian listening QA", "No cloud audio transmission or fees"]}
    with (ASR / "recognized-words.json").open("x", encoding="utf-8") as out:
        json.dump(evidence, out, ensure_ascii=False, indent=2)


def normal(word):
    return re.sub(r"[^\w]", "", word.casefold())


def timestamp(seconds):
    value = round(seconds * 1000)
    return f"{value // 3600000:02d}:{value // 60000 % 60:02d}:{value // 1000 % 60:02d},{value % 1000:03d}"


def build():
    if sha(SOURCE) != SOURCE_PIN:
        raise RuntimeError("Narrated preview changed")
    recognition = json.loads((ASR / "recognized-words.json").read_text(encoding="utf-8"))
    if sha(NARRATION) != recognition["narration_manifest_sha256"]:
        raise RuntimeError("Narration manifest changed")
    cues, corrections = [], []
    for section in recognition["sections"]:
        reference = section["reference"].split()
        recognized = [word for segment in section["segments"] for word in segment["words"]]
        times = [None] * len(reference)
        matcher = difflib.SequenceMatcher(None, [normal(w) for w in reference],
                                         [normal(w["word"]) for w in recognized], autojunk=False)
        for kind, a, b, c, d in matcher.get_opcodes():
            if kind == "equal":
                for ri, wi in zip(range(a, b), range(c, d)):
                    times[ri] = (recognized[wi]["start"], recognized[wi]["end"])
            elif kind == "replace":
                left = "".join(normal(w) for w in reference[a:b])
                right = "".join(normal(w["word"]) for w in recognized[c:d])
                ratio = difflib.SequenceMatcher(None, left, right).ratio()
                if ratio < 0.8 or not left or not right:
                    raise RuntimeError(f"Needs manual alignment review: {left} / {right}")
                start, end = recognized[c]["start"], recognized[d - 1]["end"]
                total = sum(len(normal(w)) for w in reference[a:b])
                cursor = start
                for ri in range(a, b):
                    finish = cursor + (end - start) * len(normal(reference[ri])) / total
                    times[ri] = (cursor, finish)
                    cursor = finish
                corrections.append({"section": section["index"], "reference": " ".join(reference[a:b]),
                                    "recognized": " ".join(w["word"].strip() for w in recognized[c:d]),
                                    "ratio": ratio, "start": start, "end": end})
            else:
                raise RuntimeError(f"Unmatched words require manual review: {kind} {reference[a:b]}")
        groups, current = [], []
        for i, word in enumerate(reference):
            candidate = " ".join(reference[j] for j in current + [i])
            if current and (len(candidate) > 90 or times[i][1] - times[current[0]][0] > 5.5):
                groups.append(current)
                current = []
            current.append(i)
            if word.endswith((".", "!", "?", ":")) or (word.endswith(",") and len(candidate) >= 44):
                groups.append(current)
                current = []
        if current:
            groups.append(current)
        # Keep a short trailing word with its phrase instead of flashing an orphan caption.
        merged = []
        for group in groups:
            if merged and len(" ".join(reference[i] for i in group)) < 16:
                candidate = merged[-1] + group
                if len(" ".join(reference[i] for i in candidate)) <= 110 and times[candidate[-1]][1] - times[candidate[0]][0] <= 6:
                    merged[-1] = candidate
                    continue
            merged.append(group)
        groups = merged
        section_cues = []
        for group in groups:
            text = " ".join(reference[i] for i in group)
            start = section["offset"] + max(0, times[group[0]][0] - 0.05)
            end = section["offset"] + min(section["duration"], times[group[-1]][1] + 0.15)
            section_cues.append({"index": 0, "section": section["index"],
                                 "start": round(start, 3), "end": round(end, 3), "text": text})
        for i, cue in enumerate(section_cues[:-1]):
            cue["end"] = min(cue["end"], round(section_cues[i + 1]["start"] - 0.02, 3))
        if " ".join(c["text"] for c in section_cues) != section["reference"]:
            raise RuntimeError("Caption text differs from approved reference")
        cues.extend(section_cues)
    prior = 0
    for i, cue in enumerate(cues, 1):
        cue["index"] = i
        if not (prior <= cue["start"] < cue["end"] <= 89.3) or cue["end"] - cue["start"] < 0.65:
            raise RuntimeError(f"Invalid caption interval: {cue}")
        prior = cue["end"]
    srt = OUT / "harmat-2026-10-13.hu.srt"
    with srt.open("x", encoding="utf-8", newline="\n") as handle:
        handle.write("\n\n".join(f"{c['index']}\n{timestamp(c['start'])} --> {timestamp(c['end'])}\n{c['text']}" for c in cues) + "\n")
    evidence = {"status": "REFERENCE_CAPTIONS_ASR_TIMING_READY_VISUAL_QA_REQUIRED",
                "source_sha256": SOURCE_PIN, "srt_sha256": sha(srt), "cues": cues,
                "recognition_sha256": sha(ASR / "recognized-words.json"), "reference_corrections": corrections,
                "limits": ["Approved reference wording, ASR-assisted word timing", "Small matched replacement spans interpolated inside recognized boundaries", "No native-Hungarian listening certification", "No cloud, spend or publication"]}
    with (OUT / "subtitle-cues.json").open("x", encoding="utf-8") as out:
        json.dump(evidence, out, ensure_ascii=False, indent=2)
    print(json.dumps({"srt": str(srt), "cue_count": len(cues), "reference_corrections": corrections}, ensure_ascii=True), flush=True)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["recognize", "build"])
    args = parser.parse_args()
    recognize() if args.action == "recognize" else build()
