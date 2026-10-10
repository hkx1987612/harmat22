# Construction Advertisement - Local Draft

Date: 2026-10-10. Website baseline: `ec3bd99`.

Status: V2 VISUAL QUALITY ACCEPTED AND PUBLICATION AUTHORIZED, NOT UPLOADED. After accepting the full-screen revision, the user explicitly approved uploading both v2 videos to YouTube and adding them as additional assets to existing Google Ads, preserving old assets, budgets and the website. The user subsequently requested separate public pages and detailed Hungarian introductions for earlier supplied videos on the dedicated company YouTube account, not the personal account. Neither version has been uploaded, submitted for platform review, approved by Google or put into delivery in this task. Company-account sign-in is still required; no live website, advertising settings, budget, bidding or conversion action is changed.

## Approved Creative

- A 30-second construction-proof video followed by a clearly identified future-project rendering and a stable six-second brand closing.
- The user explicitly selected the closing CTA: `Kérjen személyes ajánlatot!`, alongside the authentic project logo and `harmat22.hu`.
- Public on-screen text is Hungarian only. Chinese translations are discussion notes, not advertising assets.
- Landscape 1920x1080 and deliberately composed portrait 1080x1920 versions. These are export dimensions, not a claim that every input is native 1080p; the September archive source is 720p.
- The first samples are silent. No music of unknown licence, generated voice, invented site audio or claimed finished soundtrack is added.

## Timing And Provenance

| Output time | Source | Intended message |
| --- | --- | --- |
| 0-5 seconds | October 2 overview, source 18-23 seconds | Harmat Lakópark and the Budapest, Harmat utca 22. address |
| 5-11 seconds | September 25 A1 archive, source 5-11 seconds | `Pincei szerkezetépítés` and the archive date |
| 11-18 seconds | October 2 overview, source 24-31 seconds | `Kövesse az építkezést` and the capture date |
| 18-24 seconds | Original project presentation, source 72-78 seconds | Future courtyard/terraces, clearly labelled `Látványterv` |
| 24-30 seconds | Brand closing, authentic logo | `Kérjen személyes ajánlatot!` and `harmat22.hu` |

The construction report facts are as of September 30. The overview source metadata identifies October 2 capture. Do not label all footage September 30, present either date as the release date, or describe the September archive as October footage. The source 72/74/75.1/77/78-second rendering frames were reviewed; the 79-second lifestyle shot is excluded.

Authoritative public reference: https://harmat22.hu/epitesi-naplo/

No handover deadline, whole-project completion percentage, discount, investment return, price increase or scarcity claim is asserted. A percentage for one construction task is not a whole-building or whole-project completion percentage. Do not assign unlabeled footage to a particular building.

## Proposed Accompanying Copy

Headline:

> Épül a Harmat Lakópark

Short description:

> Valódi helyszíni felvételek, áttekinthető alaprajzok. Kérjen személyes ajánlatot!

Social post:

> Épül a Harmat Lakópark Kőbányán. Nézze meg a valódi helyszíni felvételeket, fedezze fel az alaprajzokat, és kérjen személyes ajánlatot! A szeptember 30-i építési beszámoló és az október 2-i helyszíni videó a harmat22.hu oldalon érhető el.

Suggested campaign landing page: https://harmat22.hu/lakaskereso/

Construction-proof secondary link: https://harmat22.hu/epitesi-naplo/

These are proposed destinations and copy, not a record of saved Ads assets. Publication is now authorized for the two full-screen v2 video additions only. Preserve all old assets, existing destinations, campaign budgets, bidding, targeting and conversion settings.

## Local Outputs And Reproduction

- Renderer: `server-config/maintenance/2026-10-10-build-construction-ad.py`.
- Generated samples, source logo, text files, manifest and QA evidence: ignored `outputs/2026-10-10-construction-ad/`.
- Large videos, original source metadata/GPS, temporary exports and fonts are not committed to GitHub. The renderer must strip source metadata, map video only and retain local source hashes/provenance in its ignored manifest.
- Inputs require pinned SHA-256 values. Source overrides require their independently verified hash; overwriting requires an explicit `--overwrite-own` and an unchanged builder-owned file. Both expected refusal checks passed without altering the final outputs. Unowned parent QA/source files are preserved.
- Script SHA-256 at final render: `393746397ca01239ad151f8674c87794cc3e6c3a8ef9709879cc3b60ce16ca03` (LF bytes; Git transport line-ending changes may differ).
- Landscape: `harmat-construction-ad-landscape.mp4`, 54,220,557 bytes, SHA-256 `fae56dfb844722e6f6cbd46d8df98d6f50a37850df7fc44e815ca3c9d936fa88`.
- Portrait: `harmat-construction-ad-portrait.mp4`, 23,006,849 bytes, SHA-256 `e774a3c37171af467ca2c9d820e935be100fbf0462afbb7a9de8cbd6cf958669`.
- Both: exactly 30 seconds/900 frames, 30 fps H.264/yuv420p, square pixels, silent, faststart; no audio/data/GPS/source-creation metadata streams. Every frame fully decoded; four moving scenes checked, text-safe geometry/nonblank regions checked, and the six-second closing is stable. Nine focused self-tests passed.
- Independent Chrome playback passed landscape 1440x810 and portrait 390x844 cases, correct natural video dimensions/duration, 14 decoded pixel samples, moving opening, advancing playback and no page errors. Final source/manifest/output hashes were independently compared.
- Parent reviewed opening/archive/site/render/closing frames at both formats and the mobile-sized closing. Initial local-file canvas security restrictions were resolved by using a temporary loopback-only QA server. Initial browser review caught a one-pixel natural-width discrepancy from post-scale sample aspect ratio; the renderer now sets square pixels after scaling/padding and verifies the exported ratio. Earlier font-spacing guard refusals are pre-render checks, not final passes. These issues were confined to local samples/testing; no website change or rollback occurred.

For the original-camera-quality build, provide the privately retained overview file and its known hash. The following is a reproduction template, not another executed build:

```powershell
python -X utf8 -B server-config/maintenance/2026-10-10-build-construction-ad.py `
  --ffmpeg '<FFmpeg executable>' `
  --overview-source '<verified original overview MP4>' `
  --overview-sha256 c9d7f0fb4a2b97e8168785c9b76f9b2e9174db3882abcee692db1b6472062166
```

The default overview is the separately hash-pinned local 1080p derivative; it produces different encoded output hashes. A repeat build must explicitly add `--overwrite-own`. All source inputs and fonts must be available locally on computer B through approved media storage; GitHub carries the renderer/copy/record, not the large source or output videos. Do not substitute unverified media, remove ownership guards or commit fonts/raw metadata/temporary video exports.

Browser evidence: ignored `outputs/2026-10-10-construction-ad/parent-browser-qa/results.json`. The local QA server and render/browser processes are stopped after their checks. Real iOS, social-platform safe-zone overlays, YouTube processing and Google ad review/delivery are not tested.

## Full-Screen Quality Revision

Revision baseline: `102ef26`. The user requested clearer, full-screen footage. The first version and its evidence remain intact in their original ignored output directory; its technical playback checks do not imply user acceptance of its visual quality.

- Completed new local output scope: ignored `outputs/2026-10-10-construction-ad-fullscreen/`. The renderer's explicit `--fullscreen` selects a separate v2 owner/output scope; without it the v1 storyboard/layout and default source pins remain unchanged.
- Both formats use full-bleed media throughout all 30 seconds, including the closing. No large plain background, boxed wide footage or baked-in letterbox is intended.
- The 720p September archive is omitted. Landscape retains native 1080p camera footage; portrait uses native 4096x3072 site photographs for its construction scenes instead of enlarging a low-resolution wide video crop. Photograph captions identify them as photographs. The architectural-render portion remains labelled `Látványterv`.
- Unchanged native photo copies are privately retained under the new `source/` directory and are not builder-owned output files. Sources 01/04 are October 2 supplements; source 02 is undated. September 30 remains the report cutoff. No source exposure, construction feature or capture date is invented or AI-altered.
- Photo pins: `photo01.jpg` / `488d0045b2bff585366f48384bbb4f0aac4e4edafdf199d889a62384b85af410`; `photo04.jpg` / `42ea068630cab24b8e58850020bcbc588fbc2633210c43e2e5dcad3e71a47f04`; `photo02.jpg` / `c93c7239e224de90414c31b8561976aa2b7e662ae9a4e099d1870df4387f5aec`. Each is 4096x3072, EXIF orientation 1. Original attachments and all private source copies are preserved.
- The exact six-second closing CTA/logo/website are retained over a real construction-photo background. Moving a camera view or photograph crop does not create additional construction progress. Full-screen framing cannot recover detail absent from a native 1080p video or turn it into genuine 4K footage.
- No Adobe/cloud media upload is made: this revision requires a new deterministic montage, crop and title composition, not a same-ratio resize. Generated video, private source/metadata and QA evidence remain outside GitHub.

| Output time | Landscape full-screen source | Portrait full-screen source |
| --- | --- | --- |
| 0-6 seconds | Native October 2 overview, 18-24 seconds | Native site photo 01, October 2 |
| 6-12 seconds | Native foundation/rebar photo 04, October 2 | Native foundation/rebar photo 04, October 2 |
| 12-18 seconds | Native October 2 overview, 24-30 seconds | Native yellow-formwork photo 02, explicitly undated |
| 18-24 seconds | Project rendering, 72-78 seconds, `Látványterv` | Focal crop of the same labelled rendering |
| 24-30 seconds | Photo 01 moving backdrop and unchanged logo/CTA/website | Photo 01 moving backdrop and unchanged logo/CTA/website |

- Landscape: `harmat-construction-ad-landscape.mp4`, 81,939,202 bytes, SHA-256 `6a69e6bc23d9f520cc0cdbc7e8871d8f57dd51366450ce6cf1ac610d9eac6135`.
- Portrait: `harmat-construction-ad-portrait.mp4`, 42,466,175 bytes, SHA-256 `3f4456197b4b4f66e1aaa7f7af5a6391fe86f95d834b4557cd3c6b6875ea6c95`.
- Renderer LF SHA-256 at v2 render: `0ed2126e25da0ab674a4963ba16fa7e9c6304c839ad1775dd5e1b1cc57d049ac`. Both exports are exactly 30 seconds/900 frames at 30 fps, square SAR 1:1, silent H.264/yuv420p, faststart, without source GPS/creation metadata or audio/data streams. All five scenes, including the editorial photo ending, have 180 different decoded frame hashes. The native-photo crops are downscaled, not enlarged. The six-second portrait visualization uses a 606x1080 crop enlarged to 1080x1920; it is not claimed to gain native detail or become 4K.
- Nine original plus 52 full-screen story/crop/source/metadata/Hungarian-glyph checks passed and were independently repeated. Every frame fully decoded; every frame also passed separate small-canvas/edge variation checks with no pad filter. Native camera source, three photographs, render source and logo remain hash-identical and unowned by the output registry. Parent independently compared both new outputs, all six source pins and the script/manifest pin, plus all 71 v1 builder-owned output hashes. Real no-overwrite and source-override-without-hash guard refusals were independently verified without changing exports.
- Independent final Chrome playback passed 1440x810 landscape, 432x768 portrait and 390x844 portrait cases: exact natural dimensions/duration, 21 nonblank frame/side-edge samples, moving opening and ending backdrop, actual advancing playback, and zero page errors. Parent reviewed construction, rendering and closing frames and mobile typography. First browser-run pixel/playback checks passed, but one screenshot caught Chrome's transient post-seek spinner; final screenshot capture waits for playable readiness and omits browser controls to inspect the encoded artwork. Initial evidence is preserved separately. Full-bleed describes the encoded canvas: a taller-than-9:16 player using `contain` may add external letterboxing, which is not baked into the video.
- Final evidence: `outputs/2026-10-10-construction-ad-fullscreen/parent-browser-qa-final/results.json`; renderer evidence and source/crop provenance: its `manifest.json`, `qa/`, `filters/` and `text/`. All render/browser/temporary QA-server processes finished; delegated agent closed. No platform upload, approval, delivery, real iOS/platform-overlay test or site regression claim is made.

Full-screen rebuild template (all hash-verified inputs and the same fonts/FFmpeg must be privately available on computer B):

```powershell
python -X utf8 -B server-config/maintenance/2026-10-10-build-construction-ad.py `
  --fullscreen --ffmpeg '<FFmpeg executable>'
```

Defaults expect `source/overview-raw.mp4`, `source/photo01.jpg`, `source/photo04.jpg`, `source/photo02.jpg` and `source/harmat-logo.png` in the new ignored output directory, plus the existing `outputs/home-native-video/original.mp4` render. The raw overview pin is `c9d7f0fb4a2b97e8168785c9b76f9b2e9174db3882abcee692db1b6472062166`. Optional private path overrides require the exact approved source hash. A repeat render needs `--overwrite-own`, which cannot replace changed/unowned outputs or sources. Keep the originals/private metadata and all large source/export files outside GitHub.

## Publication Gate

- [x] User approved local sample and the exact closing CTA.
- [x] Progress/capture dates and the clean future-rendering segment reviewed.
- [x] Both exports completed with exact 30-second duration.
- [x] Full decode, moving-frame, dimensions, metadata and font/layout checks passed.
- [x] Parent visual review and browser playback passed.
- [x] User accepted the revised visual quality and separately approved publication.
- [ ] Dedicated company YouTube account and existing Google Ads asset group verified in the authenticated backend.
- [ ] Approved v2 uploads completed and platform HD processing/playback checked.
- [ ] Both new video assets saved in the existing asset group, with old assets and campaign settings preserved.

## Company Channel Publication Preflight

- Chrome was connected after an initial unavailable-browser result. The signed-in Studio was a personal empty channel; no files were uploaded there. The user explicitly requires the dedicated company YouTube account. The normal Google add-account sign-in page is open as a handoff; the user must complete company sign-in before publication.
- Public channel observed in Chrome: `https://www.youtube.com/@Harmat-22` (display name `Harmat`). Its public video tab lists `HMgnTfeuQYM` / `2026.08` and `kmAg_ki-yYY` / `harmat22`. The August watch page currently has no description. This is a public listing, not an inventory of private/unlisted backend content. Reuse and preserve these existing URLs; do not create duplicate uploads or assume personal-channel ownership.
- Expanded channel-copy/inventory preparation remains pending. Earlier real site footage is intended for separate public video pages, with accurate Hungarian titles/descriptions and construction, project-visualization and nearby-environment playlists; the two approved v2 advertising exports remain separate additions to the existing advertising asset group. No complete inventory of all earlier archives, finished per-video metadata pack or channel branding change is claimed. Do not attach every archive clip to Ads or change website video sources automatically.
- Company sign-in has reached a user-controlled Google identity check requiring an already signed-in device. Authentication/verification must be completed by the user; no identity values, phone number, verification code or credential was submitted by the agent. Browser handoff tabs are retained.
- Privately prepared native-quality overview: ignored `outputs/2026-10-10-youtube-ready/harmat-helyszini-attekintes-2026-10-02.mp4`, 136,032,849 bytes, SHA-256 `96930de3cb5de59a7aa00903a4c7a28da495fd416d90ace47d5d97268b98d3fa`. Lossless video-only remux of the pinned October 2 original removes audio, timed data, location/device/creation metadata and chapters, retaining native 1920x1080 HEVC, SAR 1:1 and the approximately 31.81-second variable-timebase recording. It does not turn the native recording into the separate exact-32-second website derivative.
- Raw and sanitized compressed video stream SHA-256 agree: `16019628260e40e9a2f20d5a395a04f49a4d033fcc5c8a4cc5e0efed70090791`. Complete decode passed with input timebase retained (`-fps_mode passthrough -enc_time_base:v 1:90000`). Earlier default-null-mux checks reported a duplicate-DTS rounding warning; no source packet was changed to mask it. Sanitized inspection shows one video stream and no audio/data or source location/creation fields. Original sources and both v2 export hashes remain preserved.
- All preparation above is local/read-only platform inspection. No new YouTube ID, publication, review, delivery or performance result exists yet. Website stable tag remains unchanged.

No Google approval, delivery, improved lead rate or sales effect is promised. Compare actual qualified inquiries after a controlled creative test; views or click volume alone are not the objective.
