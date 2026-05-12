# Maintenance Log

## 2026-05-12

### Version 2.1

- Synced the live Harmat22 interactive map module back to GitHub.
- Removed the floating text markers from the homepage location overview image so the aerial image displays cleanly.
- Kept the short project overview video in slow playback and restored the longer showcase video to normal playback speed.
- Confirmed the live homepage has no `.hi-map-pin` labels in the generated location image and that video rates resolve to `0.25x` for `swsp_xmsp.mp4` and `1x` for `spjs.mp4`.
- Preserved live rollback backups on the server before changing the production map script.

## 2026-05-06

- Created a local Git history for harmat22.hu maintenance work.
- Connected the local workspace to the GitHub repository `hkx1987612/harmat22`.
- Added repository safety rules to avoid committing credentials, cookies, large media, generated backups, and temporary website snapshots.
- Legal/compliance pages and form consent fields were prepared on the live WordPress site before GitHub binding.

### Version 1.0

- Stabilized the live Harmat22 frontend after the first full optimization pass.
- Confirmed the apartment picker works across the homepage, property pages, search page, and normal content pages.
- Moved the property disclaimer to the confirmed position under the final payment step on property detail pages.
- Polished the main menu contact typography, homepage video fullscreen button behavior, contact form layout, and cookie UI positioning.
- Fixed visible Hungarian text encoding issues in custom frontend snippets.
- Ran a visible-text audit across 153 public pages and property pages with no remaining visible mojibake findings.

## Rollback Notes

- Live WordPress backups are handled separately through the site's backup workflow.
- GitHub should track clean maintenance notes, reusable scripts, and intentional frontend changes only.
- Git tag `v1.0` marks the confirmed live-site state after the 2026-05-06 optimization pass.
