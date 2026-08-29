# Construction-page main video - 2026-08-29

## Scope

- Existing public page: `https://harmat22.hu/epitesi-naplo/`
- YouTube video: `https://www.youtube.com/watch?v=HMgnTfeuQYM`
- Video duration: `PT1M31S`
- YouTube publication date: `2026-08-28`
- MU plugin: `wp-mu-plugins/zz-harmat-construction-progress-video.php`
- Local poster source: `assets/construction/harmat-epitesi-naplo-2026-08.jpg`
- Live poster: `/wp-content/uploads/2026/08/harmat-epitesi-naplo-2026-08.jpg`

The existing WordPress page `10732` was retained. No page, post, apartment, offer, CRM, menu, homepage-video or database value was changed.

## Behavior

- The local 1280x720 poster is the initial media and uses no origin-hosted video file.
- YouTube is not loaded during the initial page request.
- A visitor click creates a responsive `youtube-nocookie.com` iframe with Hungarian player language and inline playback.
- The page keeps its existing header, footer, June milestone and canonical URL.
- The current August update is presented before the older construction-log entry.
- The page description, social image and Yoast Schema graph describe the current construction video.

## Deployment

- Backup: `/home/harmath2/codex-backups/construction-video-20260829-054905`
- Supplemental Schema-date backup: `/home/harmath2/codex-backups/construction-video-date-fix-20260829-055812`
- The backup contains the pre-deployment public construction-page HTML and any pre-existing poster if one had existed.
- The target plugin and poster were confirmed absent before staging.
- The plugin was uploaded to a hidden temporary filename, hash-verified and linted before atomic installation.
- The poster was also staged and hash-verified before installation.
- The formal plugin passed `php -l`, WordPress booted, cache was cleared and no temporary staging files remained.
- The live plugin and poster SHA-256 values match the GitHub sources.
- A guarded `1.0.1` follow-up aligned `VideoObject.uploadDate` with the exact YouTube publication date `2026-08-28`; its temporary and final PHP files also passed lint.

Deployment helper:

```text
server-config/maintenance/2026-08-29-deploy-construction-video.py
```

Guarded Schema-date correction:

```text
server-config/maintenance/2026-08-29-deploy-construction-video-date-fix.py
```

Read-only post-deployment audit:

```text
server-config/maintenance/2026-08-29-audit-construction-video.py
```

## Verification

- Local PHP syntax: pass.
- JavaScript syntax: pass.
- Injection, idempotence, SEO description and `VideoObject`: pass.
- Live desktop and 390px mobile construction page: pass.
- Initial iframe count: zero.
- Clicked iframe: exact `HMgnTfeuQYM` privacy-enhanced embed.
- Poster: loaded at 1280x720.
- Page title, description, canonical, Hungarian language and one H1: pass.
- Horizontal overflow: zero on desktop and mobile.
- Key-page desktop/mobile regression: homepage, construction log, gallery, apartment search, A1-1-L2, main selector, first-phase selector, A1 building selector and contact page all passed.
- Public scan: 145 pages, 124 properties and 572 same-origin resources; zero sitemap, page, property or asset issues.
- Quote modal: A1-1-L2 data, privacy requirement and all five approved lead sources passed; no inquiry was submitted.
- Existing offer leads: 36 before and after.
- WordPress core checksums, every database table and WP-Cron: pass.
- Root error log last modified `2026-08-25 16:47:50Z`, before this deployment; admin and debug logs were absent.

## Rollback

1. Remove `/home/harmath2/public_html/wp-content/mu-plugins/zz-harmat-construction-progress-video.php`.
2. Remove `/home/harmath2/public_html/wp-content/uploads/2026/08/harmat-epitesi-naplo-2026-08.jpg`.
3. Clear the WordPress page/object cache.
4. Verify `/epitesi-naplo/` returns the pre-deployment June-only construction log saved in the backup.

The rollback removes only this new display layer and poster. It does not require a database restore.
