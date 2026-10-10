# Construction Advertisement - Local Draft

Date: 2026-10-10. Website baseline: `ec3bd99`.

Status: LOCAL SAMPLES COMPLETE, USER REVIEW PENDING. Not uploaded, submitted for platform review, approved or serving. No live website, advertising settings, budget, bidding or conversion action is changed.

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

These are proposed destinations and copy, not a record of saved Ads assets. Existing ads and campaign budgets must be preserved until a separately approved publication step.

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

## Publication Gate

- [x] User approved local sample and the exact closing CTA.
- [x] Progress/capture dates and the clean future-rendering segment reviewed.
- [x] Both exports completed with exact 30-second duration.
- [x] Full decode, moving-frame, dimensions, metadata and font/layout checks passed.
- [x] Parent visual review and browser playback passed.
- [ ] User reviewed the samples and separately approved publication.

No Google approval, delivery, improved lead rate or sales effect is promised. Compare actual qualified inquiries after a controlled creative test; views or click volume alone are not the objective.
