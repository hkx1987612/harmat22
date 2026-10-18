# Desktop Homepage Video Offset

## User Direction

- User reports continued video obstruction and confirms desktop. Parent measured
  native sticky header bottom75.996px and hero top39.746px at scroll36.25px.
  Prior work checked initial layout and retained native sticky behavior.
- Parent considered removing desktop sticky behavior, but user explicitly asks
  to move the video down instead. That proposed header/language rewrite was
  canceled before any file edit or deployment.
- Final scope: keep menu, sticky behavior, phone/tablet layout, video dimensions,
  media crop, typography, actions, native quote/appointment handlers and data.
  Append only a desktop(min1025px) owned hero margin-top76px and
  scroll-margin-top152px. No video height override or new JavaScript.
- This provides initial clearance below the menu. It does not claim a fixed
  header can never overlap content after arbitrary downward scrolling, nor
  that the existing object-fit:cover shows every edge of the video frame.

## Source And Safety

- Sol implementation; parent review and live acceptance.
- Base Git63361eb, previous v1.0.6 LF SHA
  `fec60e010844ed75673baf79d3f49f7c31eebe6edcd583c78d638712aaa020fd`.
- Current v1.0.7 LF SHA
  `c641cd493e4cc75f3596960c9096ff25c027f0ddfea6c22f80eff9166556c33d`.
- Narrow updater `2026-10-18-update-home-video-offset.py` pins exact old source,
  exact version/style patch sites and exact local new bytes. Reuses reviewed
  private backup, temp/formal PHP lint, ownership checks, atomic replacement,
  cache purge, protected state checks and source-only rollback transport.
- Historical header-fit test source is virtualized at its exact v1.0.6 fixture;
  production guard is not loosened. Prior clarity CSS assertions are bounded
  before the new desktop rule, which has its own selector/media/property checks.
- Parent independently ran9 new Python tests,3 new CSSOM tests,76 controller PHP
  assertions and lint PASS. CSSOM is not a substitute for actual browser QA.
- Sol completed99 Python tests,31 Node tests,147 PHP assertions, lint and diff
  checks. Parent also reran8 existing actions/clarity Node tests PASS.

## Live Acceptance

- Private backup:
  `/home/harmath2/codex-backups/home-video-offset-399023650e7e45c9a5a81d4d6b6f89d4`.
- Temporary and formalPHP8.3 lint, ownership-checked atomic replacement, cache
  purge,9 routes200/no fatal,124 native-public inventory parity, native form/SEO
  and protected source-page-property-CRM state,35 pure offer+17 anti-spam checks
  PASS. Separate independent `VERIFIED_HOME_HEADER_FLOW` PASS.
- Actual2400 desktop at scroll0: native menu bottom75.996px, video top151.992px,
  unchanged570px video height, no measured horizontal overflow. Clean cropped
  browser screenshot saved privately at
  `outputs/2026-10-18-home-family/video-offset-desktop.png`.
- Actual1280 desktop: identical76px margin/top151.992px/570px height,
  client/scroll1261px. Chinese mode keeps the new offset and the two original
  virtual-selector/search URLs; no measured horizontal overflow. Restored HU.
- Actual320 fresh load: margin0, menu bottom/video top75.996px,
  client/scroll301px; phone layout receives no new offset.
- Original desktop native quote opens one form; empty contacts and required
  privacy retained; closed without send. Final normal viewport and HU restored,
  modal closed. No all-device, full-frame video, or arbitrary-scroll no-overlap
  guarantee: native sticky behavior was intentionally retained per user request.
- No positive form submission, customer record, appointment, reservation or mail
  creation occurred. Private backups and browser screenshots remain out of Git.

## Current Verify And Source-Only Rollback

```powershell
python server-config/maintenance/2026-10-18-update-home-video-offset.py --verify /home/harmath2/codex-backups/home-video-offset-399023650e7e45c9a5a81d4d6b6f89d4 --expect c641cd493e4cc75f3596960c9096ff25c027f0ddfea6c22f80eff9166556c33d
python server-config/maintenance/2026-10-18-update-home-video-offset.py --rollback /home/harmath2/codex-backups/home-video-offset-399023650e7e45c9a5a81d4d6b6f89d4 --expect c641cd493e4cc75f3596960c9096ff25c027f0ddfea6c22f80eff9166556c33d
```

Rollback restores only the exact prior v1.0.6 controller, never business data.
Earlier updaters intentionally reject newer local controller bytes; do not bypass
their pins. Stable tag `stable-2026-10-18-home-video-offset-current` after sync.
