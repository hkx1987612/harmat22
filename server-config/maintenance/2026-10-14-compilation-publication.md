# October 13 construction compilation - October 14 continuation

## Current Status

- LOCAL PREVIEW ONLY; no new YouTube/Facebook upload, draft, ID or public link.
- User superseded the initial four-individual-video plan: ONE compilation on both platforms, remove original audio and add Hungarian narration. Do not upload the individual exports.
- User confirmed all four sources were filmed on 2026-10-13 and their filename descriptions are accurate. No additional completion percentage, price or delivery date was supplied.
- Runway installed/enabled was not proof of a usable connection: three authentication checks returned UNAUTHORIZED and supplied no actionable authorization URL. No generation task, trial, purchase, credits or cloud narration was created.
- User clarified the account was unregistered, then reported registration required payment and expressly rejected paid Runway. Do not retry Runway, subscribe, enter a card, start a trial or uninstall it under this request.
- Free local Piper Hungarian Imre narration is generated; user was shown the first paragraph audio and asked to review its sound. No listening/native-speaker quality approval or platform copyright/playback result is claimed.
- Narrated MP4 assembly and local technical QA now pass; complete inline video preview shown. User sound/synchronization review and both platform publications are still pending. Silent MP4 below is NOT the publication target. No claim that the user approved the new voice or accepted this batch's actual upload terms.

## Source and Silent Preview

- Private archive: `Z:/10.13现场视频.rar`, SHA256 `bdb75fc73b543aa4de6a88f9ca839a96744622cfb4cf1ffdf788c90a2614130f`. Windows tar extraction retained originals under `outputs/2026-10-14-site-videos/source/`.
- Source order: A1-A3 / A2 basement-ceiling concrete pouring / A4-A2 / site panorama. Native source 1280x720; mixed approximately 30/60fps, never market as native1080p.
- Source SHA256s in order: `2957cf218f7a44c75b0f1db20c800c398aa2871708890d03b56fad3b63215ffa`, `acad0208431a41005dd07235ee028c4adc3a09cadf820b477a5f9cd58e302361`, `f86691280309d788b152c115a37428cfa7cd49eb480c92d048145c35cff7738e`, `97913ad9ec47023b6a1c78f0d6d6a32d2c88ea837b46886eab589afc686249e3`.
- `2026-10-14-prepare-site-videos.py` created lossless metadata-stripped silent and original-audio intermediates, full decode/stream parity and unchanged source pins passed. Its historical UNCONFIRMED date reflects preparation before the user's date confirmation, not current uncertainty. Local exact-hash duplicate check is not perceptual/platform-wide deduplication.
- Sol renderer `2026-10-14-build-site-compilation.py` makes only `outputs/2026-10-14-site-videos/compilation-v1/`. Silent preview `harmat-2026-10-13-compilation-silent.mp4`: SHA256 `12ad6ba546040acf692e1e1dcb9857d9b1f4e2add6a558ee83ded4fbebbeb8ed`, 110407496 bytes, 89.300 seconds, 1280x720/30fps/2679 frames, H264/yuv420p/faststart, zero audio streams.
- Source trims 2-25 / 4-28.5 / 2-25 / 4-24 seconds, three 0.4-second dissolves. Hungarian project/date/chapter plates and final website/CTA; real source glare/motion retained, no fabricated construction, speedup, crop or upscale.
- Full decode, video-only MP4 audit, black-interval check and source pins passed. Sol sampled visual/typography review and parent contact-sheet inspection plus independent `qa` run passed. This is not every-frame visual/OCR, mobile or platform playback QA.

## Free Offline Narration

- Owned script `2026-10-14-synthesize-local-narration.py`; exact public Hungarian copy in `2026-10-14-compilation-copy.json`. No person-specific voice cloning, music or original site sound.
- Local isolated venv `outputs/2026-10-14-site-videos/local-tts-v1/venv`, based on bundled Python3.12.14 with existing system-site packages. No global environment changed.
- Local packages: piper-tts1.8.0, onnxruntime1.31.0, pathvalidate3.3.1, flatbuffers25.12.19, protobuf7.36.2. Existing NumPy2.3.5 used. Downloads/install were free; no cloud generation or account signup by the agent.
- Engine [official repository](https://github.com/OHF-Voice/piper1-gpl) GPL-3.0-or-later; voice repository MIT; individual [Imre model card](https://huggingface.co/rhasspy/piper-voices/blob/e9ef9dd26adb6a40a9b47198817cd8e96d01e7cb/hu/hu_HU/imre/medium/MODEL_CARD) identifies CC0 training data. No claim of blanket legal/platform clearance. Local downloads/licenses/models remain ignored, not redistributed with this repository.
- Model revision `e9ef9dd26adb6a40a9b47198817cd8e96d01e7cb`; model SHA256 `af7d98e2031b4f00cf3693cafc47b0b5347f23c28cd6a5957a693f76d7202c2d`; config `bb9c31dd8429b1414d486e5d52d52f0790949c63bfaf1345075d42e23ad10c83`; model card `7816348278ffe352e0b41183705b163810c5439f9d4e361cbc7ee87aa919d612`. Download from the revision's `hu/hu_HU/imre/medium/` path in rhasspy/piper-voices; verify all pins before use.
- Audio in `outputs/2026-10-14-site-videos/local-tts-v1/audio/section-01.wav` through `section-04.wav`; manifest there records exact text/hashes/cues. 22050Hz/mono/16-bit; lengths 18.436644 / 14.280272 / 13.270204 / 15.360 seconds. Cues 0.5 / 23.0 / 47.1 / 69.7 seconds; all complete before their respective guarded ends.
- Parent independently rechecked WAV format, durations, pins, finite/nonempty samples and zero clipped samples; raw peak approximately -3.10dBFS. Initial inline QA command had a syntax error; corrected actual four-WAV check passed, initial attempt is not a pass. Audio must still be listened to before approval.

## Narrated Preview - Technical QA Pass

- Sol sidecar `2026-10-14-mux-local-narration.py` created `outputs/2026-10-14-site-videos/narrated-local-v1/harmat-2026-10-13-compilation-hu.mp4`, SHA256 `f73755ed82ab62ae4adf87daba394c9736790e03cbe336893cd72c3bc9d72a84`, 111924450 bytes. Its complete video stream is copied unchanged from the silent preview; no second video compression/upscale/crop.
- One native 1280x720/30fps/2679-frame H264 video and one Hungarian-tagged AAC/48kHz/mono audio stream, faststart. Requested AAC192k is not constant actual packet bitrate. Video length89.300s; decoded AAC89.301333s, codec padding within tolerance.
- Four complete paragraphs separately two-pass loudness-normalized, placed at the manifest's four cues, remaining PCM gaps exactly silent. AAC gap checks allow80ms boundary ringing. No original sound or music, no speech truncated or overlapping.
- Sol full decode, container/cadence/stream count, WAV/timeline/interval and true-peak checks passed; exact compressed-video and final decoded frame match. Final -18.32 LUFS/-1.85dBTP. Inputs remain unchanged. Local registered output reuse is hash-verified, not a new decode each time.
- Parent reviewed the whole sidecar and reran its 12 validation fixture cases. Independently full-decoded the actual narrated MP4 through2679 frames with zero duplicate/dropped frames/errors, compared both compressed-video SHA256s (`2d9b429ae0cda7a85d0ab78504592b2e05baff05860be79f34868df127de54a6`), pinned actual preview hash and independently remeasured -18.32 LUFS/-1.85dBTP. These completed checks are separate from reused-manifest status.
- Private `qa-manifest.json`, render state, normalized audio, fixture results and logs stay ignored. No direct listening/transcription/native-Hungarian pronunciation review or platform/mobile/copyright processing test is claimed. Both Sol agents are completed/closed; all owned processes finished.

## Safe Continuation

1. Read this current status, current thread replies and copy before continuing. Do not resurrect old separate-upload or original-audio YouTube targets.
2. Preserve all existing output/source files. Scripts reject unknown existing attempts rather than overwrite them. Rebuild on computer B only with privately transferred hash-verified source assets and appropriate local Python/FFmpeg/font paths; no raw media/model/private screenshots in GitHub.
3. Obtain the user's actual sound/synchronization review of the narrated preview. Revalidate its exact hash before publication; do not regenerate/replace approved media silently. Preserve failed/partial attempts separately.
4. Company YouTube Studio and the original authorized Facebook manager Chrome were verified this turn; the other company Chrome lacked the Facebook Page publishing actor. Use only the correct company channel/Page and read fresh native UI; do not use a personal channel or former company-name Page.
5. No uploader was opened/file sent. Accept any current batch upload agreement at action time with user confirmation; old batches' terms are not automatically proof. Verify fresh inventory and receipt to avoid duplicates, factual date/brand/copy/real contacts, processing/copyright state, saved visibility and actual playback before claiming publication. Stop at identity/permission/copyright/limit problems.

## Protected Scope

- Main featured showcase, old posts/links/audio/interactions and all Google/YouTube/Facebook paid ads/budgets/payer/billing untouched. This request authorizes natural publication, not a new boost.
- Latest compilation narration is an explicit exception to the prior future-non-main-Facebook-silent preference, only for this new combined video.
- Public brand Harmat Lakopark with correct Hungarian accents in public copy; real contacts ertekesites@harmat22.hu and +36300733375. No private owner names/emails, unsupported claims or public source-device/location metadata.
- Website/server/WordPress/property/quote/CRM/mail/security/tracking unchanged; no new website scan, test inquiry, cache purge, automation or production tag. Stable website tag stays `stable-2026-10-09-sales-area-alignment-current`.
