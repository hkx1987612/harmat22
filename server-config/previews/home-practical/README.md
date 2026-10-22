# Practical Homepage Copy Review

October 22, 2026. LOCAL PREVIEW ONLY. User confirmation is required before any
production implementation. This is not a replacement image plan or an ad campaign.

## Proposed Changes

- Replace 26 HU/ZH copy entries: descriptive section titles, shorter introductions,
  concrete room/grounds/neighborhood/showroom/contact descriptions.
- Consolidate the standalone text-only team band into the existing showroom row,
  retaining the original about-page link. All image-bearing sections stay.
- Retain warmth through the existing imagery, typography and restrained palette;
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
