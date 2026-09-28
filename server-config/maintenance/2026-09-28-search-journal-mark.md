# Search construction-journal mark

Release date: 2026-09-28. Approved baseline: `5ae3bd4`. Release tag: `stable-2026-09-28-search-journal-mark-current`. Previous tag: `stable-2026-09-28-search-construction-park-current`.

## Scope

- `wp-plugins/harmat-lakaskereso-redesign/harmat-lakaskereso-redesign.php` version `1.2.4` advances the markup transient from `v13` to `v14` and clears `v13` during cache invalidation.
- On `/lakaskereso/`, the existing `Építési napló` entry remains one accessible link to `/epitesi-naplo/`. A small decorative, aria-hidden mini building and safety-helmet illustration sits immediately left of the bordered text label. It adds no external asset or network dependency and has responsive 52px/44px sizing.
- The prior icon-less search hero is otherwise preserved. Search data, filters, quote logic, construction content, media and financing content are unchanged.

## Backup And Rollback

Private backup: `/home/harmath2/codex-backups/search-journal-mark-20260928-193559`. Keep the backup and manifest outside the public site and Git. The guarded script `server-config/maintenance/2026-09-28-deploy-search-journal-mark.py` supports read-only verification and guarded rollback. For this exact release, after checking that no later changes supersede it:

```powershell
python server-config/maintenance/2026-09-28-deploy-search-journal-mark.py --verify
python server-config/maintenance/2026-09-28-deploy-search-journal-mark.py --rollback /home/harmath2/codex-backups/search-journal-mark-20260928-193559
```

Rollback is guarded against a concurrent file change and restores the backed-up plugin PHP; it does not require a database restore. Recheck `/lakaskereso/`, `/epitesi-naplo/`, the offer-modal opening and key public routes afterward. A Git tag checkout alone does not restore the live file.

## Verification

- Temporary and final PHP lint, exact deployed hash, cache purge and smoke checks passed.
- Desktop and mobile screenshots and layout metrics showed zero horizontal overflow. Focused search, construction video/gallery and financing QA passed.
- The nine-route desktop/mobile regression passed. The full scan covered 145 pages, 124 properties and 592 assets with zero issues.
- All 54 existing offer leads remained unchanged. Root `error_log` size and modification time remained `177642 1789998388`. No forms were submitted.
