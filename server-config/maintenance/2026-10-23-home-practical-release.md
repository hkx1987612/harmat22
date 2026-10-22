# Homepage Practical Copy And Ohegy Photo Release

## Approval And Scope

User approved the reviewed local homepage, the selected 2024 park photo,
backup before publication, desktop/mobile review afterward, and shutdown
only after successful checks. Quote/PDF, appointment and business logic
must remain unchanged. No customer form submission is part of this release.

Controller 1.0.20 selects new immutable body/runtime/manifest files. The
26 HU/ZH copy changes and consolidation of the standalone team text into
the showroom match the approved preview. The original about link remains.
Only the park-row image changes. Native header/footer/forms/SEO, six-card
data and rotation, journal, hero playback, 3D and all other images remain.

## Photo

- Original photograph: 4096x3072, 8,154,459 bytes, no resize/crop/recompression.
- Taken 2024-11-09, Random photos 1989, Wikimedia Commons, CC0 1.0.
- Source: https://commons.wikimedia.org/wiki/File:Budapest,_K%C5%91b%C3%A1nya,_%C3%93hegy_park,_6.jpg
- License: https://creativecommons.org/publicdomain/zero/1.0/
- HU/ZH captions distinguish the dated public park from the development garden.
- Lazy/async loading and reserved native 4:3 aspect; full-frame contain.
- A local pixel-lossless WebP trial was larger and was rejected. The original
  8MB JPEG is retained for clarity, not represented as a bandwidth optimization.
  Cold-load cost on weak networks remains a known limitation.

## Exact Release

- Backup: `/home/harmath2/codex-backups/home-practical-49db8ca8b2ad41f69e92a2a5a1ac9fea`
- Aggregate: `609d64168975bbec403e0e08cba6cbf8b021ed1312d84b31dc8a1279208ee185`
- Controller SHA256: `b116dc413a0aea1c367c955b98669987368ee05b0c74b46cf2199bb013ba86df`
- Body: `868b2b5b750a2f05bc9be753510342df6421e291797d083e15f803640172b4d9`
- Runtime: `7c42df417edc93fbd5fcc390640fd71a2b5f97add611e067e79f01c1f945c77b`
- Manifest: `42a73d9100bb7422a1e85a94e079741a436466aa76ae14b6d935f10d962822b4`
- Original JPEG: `fc603a46552558b2acfdae68ab3bad57d569e7d19d11bb02b86afbc727c9c266`

Dependencies uploaded first; controller switched last. Old immutable assets
remain intact. PHP is staged/linted; exact new static assets are exclusively
staged, byte/hash/metadata checked and renamed without PHP parsing.

The first attempt safely rolled back owned files before controller activation:
the generic helper incorrectly attempted PHP lint on a JPEG. No new homepage
was activated. The static-file adapter was corrected and covered by a test
that forbids binary PHP lint and rejects collisions/unknown files. First backup
`home-practical-8c2411308aac42f88c0698c274c98ce8` is historical, not the live release.

## Verification

- 16 offline release/transport tests and 76 pure controller checks passed.
- Retained loop runtime: 13 existing DOM checks passed; new runtime syntax valid.
- Server preflight, RELEASED and separate VERIFIED receipts passed.
- Seven serialized public routes healthy; approved main delta exact.
- Native form/SEO/header/footer and dynamic cards/journal/media unchanged.
- 35 offer plus 17 anti-spam pure checks; no dispatch, submission or mail.
- Protected source and business-state snapshot unchanged across both checks.
- Actual IAB desktop 1280x800 and mobile 390x844/320x568 checks passed.
  HU/ZH updated headings, showroom links and dated public-park captions correct;
  no horizontal overflow. Park natural4096x3072 loaded, desktop425.97x319.47
  and narrow mobile273x204.75, contain/full4:3. Other lifestyle images retain
  their aligned desktop frame. Photo credit/license links retained.
- Native1920x1080 hero ready4/playing/loop true; observed84.24s ->6.66s
  without seek/reload, continues while scrolling/language changes. No pause UI.
- Mobile six-card expansion/manual next and automatic2->3->4 observed;
  390px cards593.83px high/grid3662.97px remained stable with images loaded.
  Pointer hover correctly suspends rotation; moving outside resumes it.
- 320px empty HU/ZH quote/appointment dialog opens/closes,281x550px;
  required empty contacts/date(min2026-10-23), original three time slots,
  five unchanged underlying source values, required unchecked privacy retained.
  Contact-page appointment entrance also opens/closes on390 without sending.
- 320px panorama canvas271x470 nonblank; project/gallery tabs work and
  rotation toggles false/true. 390px A1 selector image1920px loads and sample
  A1-F-L1 detail opens; no horizontal overflow. Server smoke also covers
  apartment search, overview and phase selector. No console warn/error in
  checked homepage states. Default viewport and HU restored; public tab kept.
- Proof screenshots/logs stay in ignored
  `outputs/2026-10-23-home-practical-release/`. These are emulated browser
  viewports, not physical-phone/TV, weak-network/FPS/CWV or inbox-delivery
  certification. No claim of exhaustive absence of every possible defect.

## Completion And Shutdown

User requests shutdown only after work and review pass. All scoped release
and browser checks passed; source/maintenance sync must complete first.
Then request Windows shutdown with a60-second delay and no forced app close.
Any OS refusal or unsaved-app blocker must not be described as confirmed poweroff.

## Recovery

Use the exact release tool and aggregate, never restore database/customer data:

```powershell
python server-config/maintenance/2026-10-23-deploy-home-practical.py --rollback /home/harmath2/codex-backups/home-practical-49db8ca8b2ad41f69e92a2a5a1ac9fea --expect 609d64168975bbec403e0e08cba6cbf8b021ed1312d84b31dc8a1279208ee185
```

This restores the original controller first, then removes only exact owned
new dependencies. Foreign/live edits cause refusal. Revert this newer release
before using historical loop/home recovery tools. Original media/outputs,
private backups, screenshots and credentials are excluded from Git.
