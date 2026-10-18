# Construction Journal and Comfortable Video Viewing

## Scope

- User approves adding recent confirmed construction videos to the website journal, automatically featuring the latest update on the homepage, and prioritizing comfortable complete-frame video rather than forced cover cropping.
- Quote/appointment fields, handlers, notices, CRM, apartment data, ads, social publications and budgets are outside this change. No real lead or appointment submission is authorized by this task.
- Shared main starts at `6a492ed`; pulled before implementation. Parent plans/reviews; bounded Sol workers implement the presentation and guarded transport in disjoint files.

## Media Authority

- October 13: the approved 89.3-second Hungarian narrated/burned-subtitle compilation. Public social publication is already complete; see `2026-10-14-compilation-publication.md`. This task must not upload again to social platforms.
- Accepted source SHA256 `9d6c849e8f7d5dc6776921c991bf588e42172c4f3b93454e3a902a4e7333ab30`; original 107447273 bytes, native 1280x720/30fps. Confirmed chapter descriptions A1/A3, A2 basement-ceiling pouring, A4/A2, site panorama; no new completion percentages.
- October 12: four date-confirmed site clips. Specific building mapping is not confirmed; use generic numbered site records, not invented building labels or percentages.
- Sources and prepared media remain ignored. Do not commit video, original archives, raw/private backups or browser screenshots.

## Local Preparation

- `2026-10-18-prepare-construction-web.py` checks the five previously approved source hashes before and after preparation and refuses overwriting an output attempt.
- Four October 12 MP4s remain byte-identical lossless remuxes with their previously approved audio. October 13 web derivative uses native 720p H264 CRF22; existing Hungarian AAC copied exactly. No upscale, crop, speed change, additional voice, music or cloud service.
- October 13 web size 66207596 bytes, reduced from 107447273. Original approved source remains intact.
- Five JPEG posters are actual video frames at 8 seconds, not generated construction imagery. New players load MP4 only after explicit play, not when a page first opens.
- Full decode of all five web MP4s passes. Independent local verification checks all ten file hashes/sizes, MP4 faststart atom ordering and byte-identical approved compilation AAC; no browser playback claim from these checks alone.
- Local manifest: ignored `outputs/2026-10-18-construction-web/media-v1/manifest.json`. Public target subdirectory: `/wp-content/uploads/2026/10/construction-updates/`.

## Release Status

- Offline: 96 new PHP cases, 76 original homepage controller cases, 11 new Node cases, 20 unchanged homepage Node cases and 24 guarded transport cases pass. These are synthetic checks, not browser pixel evidence.
- Separate server-only preflight passed without activation; private backup `construction-updates-3de02dd6ed8b440e991e7ca22dfb31c8`.
- RELEASED and independently VERIFIED. Fresh release backup `/home/harmath2/codex-backups/construction-updates-a713a4d672ce4847903d0931b13f54ed` was completed before any live source activation.
- Reviewed aggregate `09473c80813bd5892d417bb45459f612bdcc2f4e2dacfd7ce1e3b5a237f7b7d6`.
- Homepage controller v1.0.8 LF SHA `ac813d2b6057fa23e4cf03ace499d7eb81c76b2faf372b950cf77b3faec6d2b3`; construction module LF SHA `a6d1388ba8e6750d452906f8b4aeb3f312538f62b05fe41e92e889d2cc2813b9`.
- Homepage pinned body/CSS/JS/Pannellum/manifest and old construction module are unchanged. A guarded presentation helper replaces only the pinned old homepage journal fragment. The new module is removable and retains legacy content on missing files or ownership drift.
- New entries use a single validated registry (approved/published, nonfuture dates, unique safe IDs/media names, paired local MP4/JPEG, both languages). Homepage always selects its newest valid record. This is page-content linkage, not browser notification permission or unsolicited push messaging.
- Construction MP4s are attached only on explicit play, muted initially, with native controls and permanent direct-link fallback. Website full-frame 16:9 players and homepage hero no longer use forced cover cropping. Mobile hero copy/actions occupy their own lower band; native header/sticky and native selection links remain unchanged.

## Live Verification

- New-only media directory and module absence, exact original home-controller bytes, safe path components and ownership verified before writes. Private source/HTML/manifest backup and PHP8.3 preflight passed. Ten files uploaded with exclusive private stages, exact remote hashes/sizes, public0644 modes; module then homepage activated with staged/formal lint and atomic rename. Cache purge confirmed.
- Both release and independent verification passed: nine public routes HTTP200/no fatal; 124 native/public inventory records (91 current,31 reserved,2 sold), unchanged protected source/page/property/CRM snapshots, native homepage form/SEO equality, journal form/canonical/old ID counts and archive markers preserved.
- All ten public media HEAD checks and five MP4 bounded 1024-byte HTTP206 range checks passed. No full media download was used for HTTP verification. Actual35 pure offer and17 anti-spam checks passed before/after; no REST submission, mail or customer-data write.
- Actual Chrome2400/1280: hero top75.996px equals menu bottom; native16:9 media1280x720 at2400 and1261.25x709.45 at1280, `object-fit:contain`, moving original1920x1080 hero. Extra76px blank offset is removed. This does not promise sticky navigation never covers content after arbitrary scrolling.
- Homepage latest teaser is October13 and shares its poster, MP4 and journal deeplink with the journal. Initial construction video count zero. Real click creates muted native controls; full1280x720 compilation advances, no decode error; keyboard Space pauses. The hero pauses when scrolled out of view.
- Actual390 Chinese homepage: video331.25x186.33, copy in separate lower layout, localized latest date/title/summary/link/note and no measured text/document horizontal overflow. Actual320 reload:301px document width,169.45px hero frame with235.51px separate copy band; no measured overflow. These are browser viewport tests, not physical phone/Safari tests.
- Chinese journal deeplink opens the confirmed October13 entry; all five localized entries retain descending dates before legacy September. Four October12 clips each decode1280x720 and advance; selecting the next pauses the preceding own player. Latest compilation also played in the journal with burned subtitles visible. HU restored successfully.
- Native quote UI: actual390 Chinese buildingA1/floor1/A1-1-L2/date2026-10-22/time12:00-15:00/Google source retains original value `Google keresés`; contacts empty and required privacy unchecked. Actual320 popup one220.04px form, empty contacts and required privacy retained. Both closed without sending. No end-to-end submission or inbox-delivery claim.
- Existing homepage3D Project/Panorama tabs retain one930x416 canvas and no reported panorama error; A2 mobile route loads its1920px building image with no new construction module injected. Underlying vendor/selector files remain hash-protected and unchanged. No new full-screen/physical-touch/pixel-certification claim.
- Private browser proof under ignored `outputs/2026-10-18-construction-web/proof/`. `desktop-hero-1280.png` is a clean complete-frame hero view; `desktop-journal.png` shows real playback/captions and dated entries. Some other captures timed out or returned tiled browser images: do not interpret these as page duplication or use them as layout proof.
- Both Sol workers completed and closed; all deployment/verifier processes finished. No social uploads, ads, authorization, security changes, automation or shutdown performed.

## Recovery

```powershell
python server-config/maintenance/2026-10-18-deploy-construction-updates.py --verify /home/harmath2/codex-backups/construction-updates-a713a4d672ce4847903d0931b13f54ed --expect 09473c80813bd5892d417bb45459f612bdcc2f4e2dacfd7ce1e3b5a237f7b7d6
python server-config/maintenance/2026-10-18-deploy-construction-updates.py --rollback /home/harmath2/codex-backups/construction-updates-a713a4d672ce4847903d0931b13f54ed --expect 09473c80813bd5892d417bb45459f612bdcc2f4e2dacfd7ce1e3b5a237f7b7d6
```

- Default invocation is offline; online modes require the exact reviewed aggregate. Rollback restores the exact owned homepage first, removes only the new owned module/media, retains foreign/partial files and never restores a database. Later foreign source/data drift is refused, not overwritten.
- On another computer, first privately transfer the hash-verified ignored media bundle to the same outputs directory; the guarded tool validates local media even for verification/recovery. Do not add media or private backup contents to Git to bypass this requirement.
- Stable source/tag after repository synchronization: `stable-2026-10-18-construction-updates-current`.
