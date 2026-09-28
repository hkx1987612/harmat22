# Search and construction nearby video

Baseline: `3610bd2` / `stable-2026-09-10-assistant-live-data-current`. The repository was pulled clean before this change. Release tag: `stable-2026-09-28-search-construction-park-current`.

## Scope

- `wp-plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php` version `1.2.3`: replace the three hero statistics pills with an `Építési napló` link to `/epitesi-naplo/`, and advance the markup transient from `v12` to `v13`. Search data, filters, result count, prices and offers were not edited.
- `wp-mu-plugins/harmat-public-audit-polish.php`: remove the white introduction hero from the active construction-log HTML function only. Financing-page hero markup is not targeted.
- `wp-mu-plugins/zz-harmat-construction-progress-video.php` version `1.2.0`: place a nearby park section between the original 1:31 YouTube construction video and the 16-photo timeline. Its poster loads before interaction; clicking creates a native, controlled inline MP4 player. A direct video link remains available. Public copy is Hungarian.
- The supplied video is 17.033 seconds at 1280x720, 5,441,367 bytes, SHA-256 `f88c316bcc2ede84dcd403ce339cafe059d2365f4925fc6d0f486302c5a1c45a`. The 1280x720 JPEG poster is 362,434 bytes, SHA-256 `6c62955ceed0afb094435e88e9eec729edbc92d37d7b3911cfe2f3127861e224`. Chrome playback and MP4 faststart layout were checked. Exact copies are tracked in Git under `assets/construction/` for A/B computer handoff; the deployment script uses those paths. The original files in ignored `outputs/construction-nearby-park/` remain local.
- Live media: `/wp-content/uploads/2026/09/harmat-kornyek-kutyapark-2026-09.mp4` and `/wp-content/uploads/2026/09/harmat-kornyek-kutyapark-2026-09.jpg`.

## Backup And Rollback

Private backup: `/home/harmath2/codex-backups/search-construction-park-20260928-184136`. Keep its manifest and original PHP files outside `public_html`; neither backup nor SSH material belongs in GitHub. The two public media assets are version-controlled under `assets/construction/`.

Deployment used `server-config/maintenance/2026-09-28-deploy-search-construction-park.py`: verify the live baseline, back up originals with hashes, stage media and PHP under non-autoloaded temporary names, run `php -l` on temporary PHP, install atomically, lint final PHP, compare hashes, purge caches, check public pages and server state. The script includes automatic failure rollback and independent verification and rollback modes.

For this exact deployment, after confirming no later edits have replaced the installed files, use:

```powershell
python server-config/maintenance/2026-09-28-deploy-search-construction-park.py --rollback /home/harmath2/codex-backups/search-construction-park-20260928-184136
```

The rollback checks current hashes against the backup manifest, restores the three prior PHP files, removes only this deployment's two media files, purges caches, and checks the offer count. It refuses a concurrent change. Recheck the search page, construction page, quote opening and key public routes after rollback. No database restore is expected.

## Verification And Limits

- Temporary and final PHP lint, exact file hashes and cache clearing passed. Live MP4 served `video/mp4` with HTTP `206` for bytes `0-1023/5441367`.
- Live verification re-passed after the tracked media path change. The nine-route desktop/mobile regression passed. Full public scan covered 145 pages, 124 properties and 592 resources with zero issues.
- The 54 existing `harmat_offer_lead` records remained unchanged, and root `error_log` size and modification time remained `177642 1789998388`. No test inquiry or conversion was submitted.
- Focused live desktop/mobile QA passed: the search-page entry, status/building/query/reset filters and navigation; exactly one construction-page H1, the original video, all 16 images and lightbox; nearby poster with zero MP4 requests before click, followed by decoded native video on click; and the unchanged financing intro. No browser errors or horizontal overflow were observed. The corrected historic construction-page test also passed.
- Native iOS/Safari and geography-specific playback were not established by these browser checks.
