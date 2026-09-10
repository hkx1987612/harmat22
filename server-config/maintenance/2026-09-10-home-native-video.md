# Native Homepage Video

## Scope

The user explicitly requested restoring self-hosted homepage playback because the
YouTube hero looked poor. Only the homepage hero changes. Existing hero dimensions,
copy, buttons, apartment data, CRM, offer forms, construction video and on-demand
neighborhood YouTube player are preserved. No Ads settings were changed.

The existing MU guard is version 1.5.0. It retains the original Slider Revolution
background suppression, poster, layout and retired-file blocking, but selects an
HTML5 video when both the versioned MP4 and native runtime are readable. The old
YouTube path remains available for a controlled rollback. Native playback is muted,
inline, looping and revealed only after a decoded playing frame. Offscreen or
hidden pages pause; reduced-motion and Save-Data visitors retain the poster without
loading the MP4. Errors, blocked autoplay and a 20-second startup timeout retain the
poster. Pausing playback does not guarantee that every browser stops buffering.

## Media And Bandwidth

- Original: `/home/harmath2/codex-retired-media/retired-origin-video-20260731-102100/yulu-garden-source-compressed-60m.mp4`.
- Original SHA-256: `40e13d25fe9ac7198b5aae9126f1569d507e9932f9888ae8b6764ad79f21f7b7`.
- New public file: `/wp-content/uploads/harmat-video/harmat-home-1080p-v2.mp4`.
- New SHA-256: `f1a581fcbef2be81204845945a3e92a6eabb459e42234bc5cb76ac90f444dd16`.
- 1920x1080, 30 fps, H.264, yuv420p, full 90.03 seconds, no audio, faststart.
- Original 60,227,393 bytes; derivative 39,504,516 bytes, about 34.4% smaller.
- Encoding: `ffmpeg -i original.mp4 -map 0:v:0 -an -c:v libx264 -preset fast -crf 22 -maxrate 3500k -bufsize 7000k -pix_fmt yuv420p -movflags +faststart -threads 4 harmat-home-1080p-v2.mp4`.
- Video files remain outside Git. A private copy of the deployed derivative is in
  the deployment backup so another maintenance computer can recover identical bytes.
- A scoped `.htaccess` supplies video MIME type and one-year immutable caching;
  byte-range responses must remain supported. The three retired URLs remain 410.
- Existing monthly CRM totals and 50/70/85/95-percent alerts remain. Their email
  wording no longer incorrectly claims the homepage uses zero origin bandwidth.
- At inspection the archived September HTTP count was 18,888,120,187 bytes, 3.5% of
  the configured 512,000 MiB quota, with archive timestamp 2026-09-10 12:25 UTC.
  This is delayed HTTP accounting, not a real-time hosting-quota guarantee.
- 1,000 complete uncached downloads would add approximately 39.5 GB. Native video
  cannot provide the zero-origin-video-traffic benefit of YouTube. No automatic
  claim that caching/pausing prevents quota exhaustion is made.

## SEO

The existing homepage VideoObject keeps its identity, Hungarian metadata, publisher,
publication date and duration, but uses the actual MP4 `contentUrl` rather than a
YouTube `embedUrl`. The video sitemap similarly uses `video:content_loc`. No page
URL, canonical, robots rule, property Schema or construction VideoObject changes.

## Deployment And Rollback

- Baseline: `d0d15799b817b8ffbee2a1b98bb99b5384a00ddb`.
- Backup: `/home/harmath2/codex-backups/home-native-video-20260910-162013`.
- `2026-09-10-deploy-home-native.py` verifies the existing live guard against Git,
  backs it up, stages every file, validates SHA-256, lints staged PHP, installs
  media/runtime before the guard, lints final PHP, purges caches and checks pages.
- The deployment script restores the previous guard and removes its newly installed
  files if deployment verification fails. It never modifies a WordPress record.
- Reviewed rollback command: `python server-config/maintenance/2026-09-10-deploy-home-native.py --rollback /home/harmath2/codex-backups/home-native-video-20260910-162013`.
  This restores/lints the backed-up guard, clears caches and retains unused media.
  It refuses to overwrite a concurrently changed guard. Repeat public checks after
  rollback. A Git checkout alone does not replace live code or media.
- Emergency configuration flag: `HARMAT_HOME_NATIVE_VIDEO_DISABLED=true` selects
  the retained YouTube implementation after page-cache purge; normal configuration
  changes still require their own backup and lint.

## Local Verification

- PHP 8.5 syntax validation passed; live PHP lint is separately required.
- Two sets of 12 PHP assertions cover normal/disabled playback, idempotence,
  homepage scope, source removal, Schema and retained on-demand 3D runtime.
- Headless Chrome at 1440x900 and 390x844 verified actual 1920x1080 playback,
  nonblank decoded pixels, mute/inline playback, scroll pause/resume and looping.
- Reduced motion, Save-Data, rejected autoplay and failed-media tests passed.
- The first offscreen test found a zero-area intersection edge case; the runtime
  now requires positive intersection area and both device tests pass.
- Full derivative decode with FFmpeg `-xerror` passed; a representative 1080p frame
  was visually inspected. No claim of lossless recompression is made.

## Live Verification

- Staged and final PHP lint passed, exact deployed media/runtime/guard hashes match
  local sources, WordPress boots and page caches were purged. No staging files remain.
- Live desktop and 390px Chrome confirmed 1920x1080 moving decoded pixels, muted
  inline playback, exact hero/video bounds, zero horizontal overflow, both original
  CTAs, scroll pause/resume, no hero iframe and no initial YouTube API/embed request.
  Both screenshots were visually reviewed. JavaScript page-error lists were empty.
- HTTP range request returns 206 with the requested 1,024 bytes; video MIME type,
  one-year cache and video sitemap content location passed. Retired URLs remain 410.
- Nine key pages passed on desktop and mobile, including construction media, gallery,
  apartment search, property detail, both virtual selectors, A1 selector and contact.
  The mobile A1-1-L2 quote popup retained its apartment summary and five sources.
- Sitemap regression: 145 pages, 124 properties, 588 same-origin assets; zero sitemap,
  page, property or asset findings. No test inquiry or conversion was submitted.
- All 44 existing private offer leads remain. Root error-log size/time remained
  `134931 1788259806` through deployment and subsequent browser regression.
- Stable tag: `stable-2026-09-10-home-native-video-current`.
- Real Safari/iOS devices and China-specific networks were not available for this
  verification. Browser policies may still prevent autoplay; the poster fallback
  is intentional. There is no guarantee against future bandwidth exhaustion.
