# Practical Homepage Copy Review

October 22, 2026. LOCAL PREVIEW ONLY. User confirmation is required before any
production implementation. This is not a replacement image plan or an ad campaign.

## Ohegy Photo Follow-up

User subsequently explicitly requests one Ohegy park image sourced for this LOCAL
preview. This supersedes image preservation only for the park-row figure. All
other photographs, card data, main copy, videos, styles outside that figure and
quote/booking workflows remain unchanged. No production edit or deployment.

Current review rebuild uses `python server-config/previews/home-practical/build.py
--ohegy-park` (one command on one line). Without the flag, the builder retains the
historical copy-only original-park mode.14 invariant tests pass in Ohegy mode.

Current original (user found the previous2016 photo too old):
[Ohegy park with Kobanya landmark](https://commons.wikimedia.org/wiki/File:Budapest,_K%C5%91b%C3%A1nya,_%C3%93hegy_park,_6.jpg),
Random photos1989, November9,2024; CC0 1.0, linked author/source and dedication.
4096x3072 /8154459bytes, SHA256
`fc603a46552558b2acfdae68ab3bad57d569e7d19d11bb02b86afbc727c9c266`;
SHA1 `dae4ba94bd72bf418cc0f1252ded0fca0028f484` independently matches Commons
metadata. Three dated2024 open-license candidates visually inspected, daylight
landmark view selected over dark sunset and colored-light views.2025 visitor
photos found but no verified reuse permission; not copied into preview.

Downloads remain ignored at `outputs/2026-10-22-home-practical/source-images/`.
Original bytes retained, no crop/AI editing/resampling. Figure-only4:3/contain
matches this original and reserves space before lazy loading. HU/ZH alt/caption
explicitly mark2024-11-09 and public park, not the development's garden. This is
NOT an aerial whole-park/360 panorama or a claim of current2026 appearance. Old
2016 source file stays local as historical evidence, not selected. Original
comparison page still retains original site's pet-park picture.

Actual desktop/mobile picture, date, source and unchanged workflow checks are
repeated for this replacement:1280x800 image425.97x319.47,390x844 image335x251.25,
320x740 HU/ZH captions/nooverflow, original4096x3072/contain and browser console
clean.14 offline invariants pass. Screenshots `proof/ohegy-2024-desktop.jpg` and
`proof/ohegy-2024-mobile.jpg` remain ignored. Default viewport restored and review
tab retained. Local server restarted
PID25924 on127.0.0.1:8793. Original8.15MB is for review; any later authorized
production release needs its own responsive image/performance plan, not a silent
8MB homepage upload or unapproved compression.
Direct review anchor `/#hhf-ohegy-preview`. Source files/tests/notes only in Git;
photo and browser screenshot remain local. No back-end or customer submissions.

## Proposed Changes

- Replace 26 HU/ZH copy entries: descriptive section titles, shorter introductions,
  concrete room/grounds/neighborhood/showroom/contact descriptions.
- Consolidate the standalone text-only team band into the existing showroom row,
  retaining the original about-page link. All image-bearing sections stay.
- Retain warmth through existing imagery (except approved Ohegy follow-up above), typography and restrained palette;
  no generated photos, new palette, financial incentives or sales claims.
- Public facts (including area, price, dates, contacts), six floorplans, current
  October20 construction record, original media/captions and action destinations
  are unchanged. No area formula, quote, PDF, booking or CRM change.

## Isolated Preview

Artifact: `outputs/2026-10-22-home-practical/v1/index.html`, loopback URL
`http://127.0.0.1:8793/`. `before.html` is the same public snapshot before copy
changes; `changes.html` lists original HU, proposed HU and corresponding Chinese.
Images and videos use the original cached/downloaded files without compression,
cropping, AI editing or source substitution. Resource mapping retains original
public URLs and local SHA256 evidence. The server serves video ranges directly
from three exact local file aliases, not a directory of private media.

Public main content/styles were captured from the existing live browser after
one ordinary direct public GET returned403. No retry grid or security changes.
The snapshot contains no forms, authentication data, customer chats or analytics.
The six original featured units are a fixed public-data snapshot, not a second
real-time inventory/auto-rotation service. Offline controls preserve original
hero looping, six-card mobile expansion, Pannellum tabs and presentation. The
construction player URL guard is changed ONLY in the generated local runtime to
one exact local alias. No production PHP/JS guard is modified.

The top navigation is an explicitly labelled review shell, not a proposed
production menu/footer replacement. Customer service is deliberately disconnected
and disabled. Official contact, appointment, detail and selection links remain
outbound to the existing website; no production form submission is part of QA.
No POST, customer-service endpoint, backend login, cache purge or live deployment.

## Rebuild And Verify

Requires the ignored `public-snapshot.json`, local approved media caches, two
unmodified downloaded official images and Python with lxml. Missing resources
fail closed; the builder does not fetch new images or invent substitute content.

```powershell
python server-config/previews/home-practical/build.py
python server-config/previews/home-practical/test_preview.py
node --check outputs/2026-10-22-home-practical/v1/runtime-after.js
node --check outputs/2026-10-22-home-practical/v1/construction.js
node --check outputs/2026-10-22-home-practical/v1/preview.js
python server-config/previews/home-practical/serve.py
```

Server binds only127.0.0.1:8793, refuses directory listings/traversal/non-allowlisted
extensions, exposes no JSON snapshots/repository paths, and has no POST handler.
Media assets, screenshots and captured/generated artifacts remain ignored. Only
preview source, tests and maintenance notes belong in GitHub.

## QA

- 12 offline invariant tests: exact card facts/actions/journal/video/image order,
  unchanged image bytes, complete proposed HU/ZH entries, six cube resources,
  one text-only band consolidated, no forms/frames and local-only resources.
- Actual browser1280x720,390x844 and320x740: HU/ZH switching, no horizontal
  overflow, visible image load and unchanged contact destinations. Narrow-screen
  three-to-six-card expansion works. Original hero advances offscreen/loop=true.
- Local3D loads a nonblank canvas on320 and switches project/gallery tabs; new
  project text fits. Keyboard turn actions exercised, not a physical touch/TV or
  measured frame-rate certification. No weak-network/Core Web Vitals claim.
- Browser console had no error/warn entries at the checked state. No quote or
  appointment send, mail delivery or production regression-certification claim.

Any later deployment needs a separate narrow change list, fresh production
backup/rollback, pin updates if needed, and native desktop/mobile/form checks.
