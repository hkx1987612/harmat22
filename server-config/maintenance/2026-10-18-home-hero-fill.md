# Homepage Hero Fill Release

## Approved Scope

User prefers the main homepage video to fill the hero and play automatically again. The existing single latest construction compilation remains click-to-play; other clips stay in the journal. User explicitly allows the in-app browser after Chrome remains unavailable.

- Home controller v1.0.8 -> v1.0.9. Only the final five hero layout overrides and version change: remove the 1280px cap, contain grid and separate mobile copy band; restore the existing pinned responsive cover layout, with bounded action wrapping.
- Existing native 76px header remains in flow, no extra gap. Original video quality/file and visibility-aware muted autoplay JS are unchanged. Reduced-motion preference, manual pause and background/offscreen pause remain respected. Cover fills the frame and intentionally can crop image edges; this is no longer full-frame contain.
- Quote/appointment/native header/language handlers, pinned home body/assets/manifest, 3D, construction module, old journal, property/CRM/customer data and advertising are not changed.

## Exact Release and Recovery

- Base commit: `d17dd30`.
- Previous LF SHA256: `ac813d2b6057fa23e4cf03ace499d7eb81c76b2faf372b950cf77b3faec6d2b3`.
- Released LF SHA256: `03e7fa36f1b3f23649f99240123b5a6eeefe6ee3af68edfec6f97a8f588b89c7`.
- Private backup: `/home/harmath2/codex-backups/home-hero-fill-f8938e7bf62e4feba91e8747247b6f5c`.
- Narrow adapter: `2026-10-18-update-home-hero-fill.py`. Default offline; requires exact source pins and allows only final hero CSS/version differences. Reuses existing controller-only locked, private-backup, PHP-linted, atomic replacement transport; adds unchanged construction-source fingerprints and public one-home/five-journal policy checks.
- Backup completed before activation; staged/formal PHP 8.3, atomic replacement, cache clear and separate independent verification succeeded. Shared transport retains its historical `RELEASED_HOME_HEADER_FLOW` / `VERIFIED_HOME_HEADER_FLOW` status labels; the backup prefix, exact manifest and adapter identify this hero-only release.

From repository root, verify or recover only this owned controller:

```powershell
python server-config/maintenance/2026-10-18-update-home-hero-fill.py --verify /home/harmath2/codex-backups/home-hero-fill-f8938e7bf62e4feba91e8747247b6f5c --expect 03e7fa36f1b3f23649f99240123b5a6eeefe6ee3af68edfec6f97a8f588b89c7
python server-config/maintenance/2026-10-18-update-home-hero-fill.py --rollback /home/harmath2/codex-backups/home-hero-fill-f8938e7bf62e4feba91e8747247b6f5c --expect 03e7fa36f1b3f23649f99240123b5a6eeefe6ee3af68edfec6f97a8f588b89c7
```

Never restore business data or redeploy an older whole-home installer. Foreign drift is refused. Production stable tag after synchronization: `stable-2026-10-18-home-hero-fill-current`.

## Verification

- Parent: 51 Node cases pass across presentation, construction runtime, construction policy and new hero suites. The old current-layout assertion was explicitly updated to the newly requested cover behavior, not skipped. Construction players still use contain. New fake transport: 8 pass; PHP controller: 76 pass; reused construction fixture: 96 pass; presentation fixture checks also run within Node tests. PHP lint and diff checks pass. Stubs/CSSOM tests are not actual rendering evidence.
- Production and independent verify: nine public routes 200/no fatal; 124-unit public/native authority match, 91 current/31 reserved/2 sold; native form structure and SEO unchanged. Protected source/page/property/CRM snapshots, unchanged assets/construction sources, 35 pure offer checks and 17 anti-spam checks pass. No positive submission, REST lead dispatch, notification or mail test.
- Actual in-app browser: 320/390 phone, 767 tablet, 1280/2400 desktop. No measured page or owned text overflow. At scroll zero, native header bottom and hero top both 76px. 1280 hero 1265x570, 2400 hero 2385x570, 390 hero 375x520; next section remains visible. Native scrollbar width explains client/viewport differences.
- Main video decodes 1920x1080, time advances, muted automatic play works; actual pause/resume works. Main video pauses after scrolling to the construction section. HU/Chinese labels and original official action destinations remain intact.
- Actual 320 Chinese native quote dialog opens and closes; one form, 228px form width, original date/time controls and five original Hungarian source values retained, contacts/date empty and required privacy unchecked. Mobile navigation opens/closes and HU is restored. No form submitted or customer/contact values entered.
- Actual 390 compilation decodes 1280x720 and advances with controls/muted; Chinese latest link resolves to `/epitesi-naplo/#construction-2026-10-13`. Journal has all five deferred players, no initial attached video, older September archive retained.
- Actual 767 3D Project/Panorama switching, visible 694x520 canvas, rotation pause and mouse drag change the visible panorama. No physical touch, Safari or fullscreen success claim.
- Temporary viewport reset; HU restored, forms closed, official homepage kept open. Ignored proof images: `outputs/2026-10-18-home-hero-fill/proof/desktop-1280.png`, `mobile-390-zh.png`, `panorama-767.png`. Desktop IAB screenshots show some raster softness; geometry, decoding and actual UI were separately observed.

## Loading Limits

No added media, external player or preload. The earlier bounded HTTP audit remains evidence for its own sample, not a fresh mobile/CWV measurement. Browser read-only evaluation did not expose the Performance API, so no invented LCP/CLS/full-load figures. The same 39.5MB range-streamed hero is retained for quality; a separate quality-reviewed adaptive rendition remains future work. No PageSpeed API calls or quota use.

This release supersedes the audit's pending actual viewport gate through explicit user-approved IAB checks, not through a silent Chrome fallback. No old social/AI/security/automation/shutdown work resumed.
