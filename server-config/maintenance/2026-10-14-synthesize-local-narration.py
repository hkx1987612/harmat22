"""Offline Hungarian narration preview; no account, cloud call, upload or spend."""
import hashlib
import json
import wave
from pathlib import Path

from piper import PiperVoice, SynthesisConfig

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "outputs/2026-10-14-site-videos/local-tts-v1"
MODEL = BASE / "model"
OUT = BASE / "audio"
COPY = ROOT / "server-config/maintenance/2026-10-14-compilation-copy.json"
PINS = {
    "hu_HU-imre-medium.onnx": "af7d98e2031b4f00cf3693cafc47b0b5347f23c28cd6a5957a693f76d7202c2d",
    "hu_HU-imre-medium.onnx.json": "bb9c31dd8429b1414d486e5d52d52f0790949c63bfaf1345075d42e23ad10c83",
    "MODEL_CARD": "7816348278ffe352e0b41183705b163810c5439f9d4e361cbc7ee87aa919d612",
}
STARTS = [0.5, 23.0, 47.1, 69.7]
ENDS = [22.4, 46.5, 69.1, 89.05]


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main():
    for name, expected in PINS.items():
        if digest(MODEL / name) != expected:
            raise RuntimeError(f"Changed voice asset: {name}")
    copy = json.loads(COPY.read_text(encoding="utf-8"))
    sections = copy["narration_sections"]
    if len(sections) != 4 or copy["capture_date"] != "2026-10-13":
        raise RuntimeError("Expected the approved four-section October 13 script")
    OUT.mkdir(exist_ok=False)
    voice = PiperVoice.load(str(MODEL / "hu_HU-imre-medium.onnx"), use_cuda=False)
    settings = SynthesisConfig(length_scale=1.0, normalize_audio=True, volume=0.7)
    records = []
    for index, (section, start, end) in enumerate(zip(sections, STARTS, ENDS), 1):
        path = OUT / f"section-{index:02d}.wav"
        with wave.open(str(path), "wb") as output:
            voice.synthesize_wav(section["text"], output, syn_config=settings)
        with wave.open(str(path), "rb") as audio:
            seconds = audio.getnframes() / audio.getframerate()
            if (audio.getframerate(), audio.getnchannels(), audio.getsampwidth()) != (22050, 1, 2):
                raise RuntimeError("Unexpected voice audio format")
        if seconds <= 1 or seconds > end - start:
            raise RuntimeError(f"Section {index} needs timing review: {seconds:.3f} seconds")
        records.append({"index": index, "path": str(path), "sha256": digest(path),
                        "seconds": seconds, "start_seconds": start, "max_seconds": end - start,
                        "text": section["text"]})
        print(f"Section {index}: {seconds:.3f}s; cue {start:.3f}s", flush=True)
    manifest = {"status": "LOCAL_SYNTHESIS_COMPLETE_LISTENING_REVIEW_REQUIRED",
                "engine": "piper-tts 1.8.0, CPU, offline", "voice": "hu_HU-imre-medium",
                "model_pins": PINS, "model_card_dataset_license": "CC0",
                "voice_repository_license": "MIT", "engine_license": "GPL-3.0-or-later",
                "model_revision": "e9ef9dd26adb6a40a9b47198817cd8e96d01e7cb",
                "model_card_url": "https://huggingface.co/rhasspy/piper-voices/blob/e9ef9dd26adb6a40a9b47198817cd8e96d01e7cb/hu/hu_HU/imre/medium/MODEL_CARD",
                "copy_sha256": digest(COPY), "sample_rate": 22050, "channels": 1,
                "original_audio_included": False, "background_music_included": False,
                "cloud_generation": False, "provider_spend": 0,
                "sections": records, "limits": ["No listening or native-speaker QA claimed", "Not uploaded"]}
    (OUT / "narration-manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    for name, expected in PINS.items():
        if digest(MODEL / name) != expected:
            raise RuntimeError(f"Voice asset changed during synthesis: {name}")


if __name__ == "__main__":
    main()
