# SEO Metadata And Selector Comfort

Release date: 2026-10-09. Reviewed Git baseline: `2b2a2c0`.
Status: stable release approved after deployment, independent HTTP verification, live browser QA and full-site scan passed. Release tag: `stable-2026-10-09-seo-selector-comfort-current`. All deployment and QA processes finished; no further live changes.

## Exact Scope

- New MU `wp-mu-plugins/zz-harmat-public-seo-metadata.php` version `1.0.0`: construction/page-index sitemap dates, current visible property floorplan sitemap images, empty share-image fallbacks only on `/harmat-lakopark/`, `/harmat-lakopark-kornyeke/`, `/elerhetosegeink/` and `/virtualis-lakasvalaszto/`. Curated images win; missing floorplan/helper leaves original sitemap entries intact.
- `wp-mu-plugins/zz-harmat-search-ai-discovery.php`: version `1.0.1` and Apartment `mainEntityOfPage` set to the exact permalink matching Yoast WebPage `@id`; no other change.
- `wp-plugins/360/viewer.js`: only `loading="lazy" decoding="async"` on the existing apartment-card image.
- `wp-plugins/360/lakaspark-360.php`: only plugin version `1.9.2` and script version `6.4`.
- No prices, areas, availability, public text, indexing rules, titles, attachment/post records, layout, forms, tracking or reminder behavior changed. No sender, cron task, form, inquiry, Google or IndexNow action was invoked by this procedure.

## Construction Date Source Of Truth

The fixed content-modification floor is `2026-10-03T08:30:47+00:00`: the deployed construction MU v1.3.0 source mtime, verified with release SHA-256 `a0a98a575697eb32862be18b366af5e023b000241e3fec158aa65f3a96b065d5`. October 3 publication is documented in `2026-10-03-september-construction.md` and commit `30d1354`. The precise mtime is source-release provenance, not an independently logged atomic activation instant.

September 30 is the report's progress cutoff, not its publication date. Never substitute the latest request time. Later WordPress publication/modification dates win, and a future reviewed construction-content release may update this fixed floor honestly using its verified source date. No blanket `post_modified` database writes are used. Yoast WebPage `dateModified` remains absent; the original June 8 `datePublished` is preserved.

Installed Yoast 27.7 source verified read-only: `wpseo_sitemap_entry` in `inc/sitemaps/class-post-type-sitemap-provider.php:224`; structured `wpseo_sitemap_index_links` in `inc/sitemaps/class-sitemaps.php:422`; `wpseo_frontend_presentation` in `src/integrations/front-end-integration.php:478`. Its per-image OG filter cannot create an image when the generated list is empty. The existing visible renderer and sitemap both use `hm_migrated_property_floorplan_image_from_uploads()`.

## Backup And Hashes

Private backup: `/home/harmath2/codex-backups/public-metadata-lazy-cards-278e9c42507e487a8aad83b59a0708ee`.
Directory 0700; all saved files and `manifest.json` 0600, with exact readback/hash verification. Manifest entries 0-2 contain both original bytes and deployed LF bytes (`N.before`/`N.after`); entry 3 contains `3.after` and `3.before.absent` with `ABSENT\n`, because the new MU did not previously exist. The manifest records exact targets, before/after SHA-256, baseline LF SHA-256 and original modes. Backup creation UTC: `2026-10-09T09:56:16.243210+00:00`.

Published LF SHA-256:

| File | SHA-256 |
| --- | --- |
| `wp-mu-plugins/zz-harmat-public-seo-metadata.php` | `8fc3144153d21fbc39449fe7ecea6feda71f72449ec6a8c8fc4d4ee6dd87eaad` |
| `wp-mu-plugins/zz-harmat-search-ai-discovery.php` | `35e39cb75888f657899939de72c5ba90b16c4eee5bf1d98bfcb68c3670765bb3` |
| `wp-plugins/360/viewer.js` | `ae9566aee3f979bc3cd83c441ea97bb7377d9e59490b2aa790563b03a754dce0` |
| `wp-plugins/360/lakaspark-360.php` | `b65b471c5ec8a8b6d002e5dff94320a840b97e3e7fbe3ef9d2e2e8df705a9205` |

All live originals matched baseline LF hashes. The original 360 PHP was CRLF: raw SHA-256 `3b0d8451e169bea61f6821ea5f03bc4651556c61c28838b84a6b02cbbc62e7df`, normalized LF `e1bc518e6eac81a789ba28044dd6e656c393e88712099eeecbf55befc24f1dee`. Its exact original bytes are retained, not overwritten by newline normalization in the backup.

## Completed Verification

- Exact reviewed diffs and pinned candidate hashes checked before any writes; unexpected local/live edits abort. Hidden `.tmp` staging passed exact hashes and PHP 8.3 lint before atomic installs; all three final PHP files linted again. Local Node syntax passed; the served viewer JS matched its exact published LF SHA-256.
- Yoast page/property/index sitemap caches invalidated using verified `WPSEO_Sitemaps_Cache::clear(array('page','property'))` and `clear_queued()`, followed by the established WP Super Cache/object-cache purge. No content or indexable reindex operation was used. Owned staging files and deployment lock are absent; deployment process exited 0 and all its commands finished.
- Standalone `2026-10-09-test-public-seo-metadata.php`: 196 assertions each for helper-present and helper-absent modes, passed on local PHP 8.5 and production PHP 8.3. Installed Yoast presenter/renderer integration: 328 total isolated assertions, including 196 from the standalone fixture. This one-off integration used actual installed classes plus stubbed WordPress helpers/presentation/date formatting, not a live WordPress request. Parent also approved the reported 16 local before/after selector UI cases.
- Deployment cold/warm/fresh-query sitemap checks retained 21 pages and 124 properties, identical URL sets, exact October 3 journal/page-index dates and all 124 exact current-helper image references. Normal/fresh public checks passed unique four-route OG/Twitter fallbacks, unchanged journal/property sharing and Apartment reference resolution; private `/sales/` retained noindex. Nine fresh public routes returned 200 without PHP-error output.
- Parent independent HTTP verification passed the identical baseline 145-URL set (21 pages/124 properties), both October 3 sitemap dates, all 124 `/2026/05/UNIT-cn-floorplan-display.jpg` images, unique fallbacks, preserved custom OG, exact Apartment-to-Yoast link, public canonical/indexability/single H1, private `/sales/` noindex/nofollow and served viewer `6.4` hash. Parent verification session finished exit 0. No form/inquiry was posted.
- Parent-approved final live QA passed eight building/viewport card cases with exact layout/source, all rendered images decoded, zero blank frames and working filters; 18 public route/viewport cases had no failures. Full scan passed 145 pages, 124 properties and 612 assets, with all four issue counts zero. Evidence: `outputs/2026-10-09-site-review/card-lazy-live-summary.json`, `browser-live-results.json` and `postdeploy-public-scan.json`. Descartes confirmed all processes finished, no contact details entered/forms submitted, whole-session write/mutator blocking, no logged mutator attempts and no recorded successful mutators.
- Deployment pre/post safety snapshots are identical: 66 `harmat_offer_lead` posts across all statuses; root `error_log` absent; legal reminders paused; reminder MU hash `bdf5b36d8df9d1a35b5e4392c3caa63dcd2c81072680b2774eb5edbd1faef66d` unchanged. Private reminder-option and IndexNow queue/result value hashes and non-autoloaded states are unchanged; no recipient values are recorded here.
- Parent's later read-only audit during browser QA observed 67 offer-lead posts; the additional record's creation/modification timestamp was `2026-10-09T10:07:55Z`. Its actor/origin remains unattributed and the record was preserved. A repeated narrowed comparison confirmed the count was the only safety-snapshot delta; root error-log, legal pause/options/plugin and IndexNow queue/result fields remained identical. Parent also reverified all four exact live/local LF hashes and private backup hashes/modes. Live traffic can legitimately create records; timing alone does not establish attribution or a regression, and QA logs contain no mutator attempts or recorded successful mutators. No lead was deleted/modified and no rollback was performed.

Local evidence is ignored under `outputs/2026-10-09-public-seo-metadata/`: `deployment-events.json`, `release-manifest.json`, `current-helper-floorplans.json`, `installed-yoast-integration-evidence.md` and `construction-date-readonly.json`. The integration evidence is a saved transcript; its original stdin harness was not saved. The standalone PHP fixture is the persistent Git-tracked test. Ignored deployment tooling is not required for recovery.

## Guarded Recovery On Either Computer

These are recovery instructions, not a record of rollback execution or new deployment authorization. B-computer recovery needs only trusted SSH/SFTP access and the private backup manifest; no local ignored script or Git checkout is required. Use RejectPolicy with the trusted `known_hosts`; never write credentials into scripts or Git.

1. Read the exact backup's `manifest.json`; require baseline `2b2a2c0`, this exact backup path and only the four targets listed above under `/home/harmath2/public_html/wp-content/`. Verify directory/file modes, every saved `beforeSHA256`/`afterSHA256` and the exact absent marker. Never normalize saved bytes during rollback. Snapshot lead/error-log/reminder state before recovery.
2. Check all current target hashes before changing any file. Only exact recorded deployed hashes are eligible for replacement/removal. An already-original target (or already-absent new MU) is left untouched. Any other hash, symlink, unexpected target or existing deployment lock stops recovery for review. Do not force through a B-computer/concurrent edit.
3. For each eligible existing file, stage its verified `N.before` bytes beside its target as a new hidden `.tmp`, apply the manifest's original mode, verify its exact hash and lint staged PHP with `/opt/alt/php83/usr/bin/php -l`. Immediately recheck the target's deployed hash before atomic same-directory replacement. Then lint each final restored PHP again and verify its exact original-byte hash. Validate restored JS syntax with Node against those same bytes.
4. For the new metadata MU only, verify the exact deployed hash again and its `ABSENT\n` backup marker, then remove that one exact file. Do not delete attachments, content, backup directories or any unrelated file. Verify the target is absent.
5. With `/opt/alt/php83/usr/bin/php /usr/local/bin/wp --path=/home/harmath2/public_html eval`, run `WPSEO_Sitemaps_Cache::clear(array('page','property')); WPSEO_Sitemaps_Cache::clear_queued(); if(function_exists('wp_cache_clear_cache'))wp_cache_clear_cache(); wp_cache_flush();`. This is cache invalidation only; never save posts, run cron/senders, submit indexing or reindex Yoast content as part of recovery.
6. Recheck restored hashes, final PHP lint, nine-route HTTP health, restored metadata/viewer version and safety snapshots. Account for concurrent lead creation without deleting/modifying records to force count equality; an unattributed count increase alone is not an established regression or rollback trigger. Remove only hash-matching owned temporary files and the lock owned by that recovery; retain the complete private backup. If a conflicting edit or partial recovery blocks this, preserve evidence/lock and request review rather than overwrite it.

## Limits And Out Of Scope

The A1 mobile card test transferred 16,527,609 bytes before versus 12,218,722 after (4,308,887 saved), with requests falling from 31 to 24 in a four-second lab observation window. This is a bounded sample, not a universal bandwidth promise. A4's existing final card count is 29; do not claim 31 images/cards for every building. Some offscreen reserved images correctly remain deferred, so rendered-image decode checks are not a requirement that every offscreen image load.

The preexisting inline `harmat-virtual-unified-sales-20260513` runtime rebuilds available cards at 500/1200/2600 ms without the native attributes, limiting the gain. Preserving lazy/async attributes in that renderer is a future opportunity, not part of this batch. The initial strict all-attributes assertion was overbroad, not a product regression; its original `card-lazy-live-attribute-assertion.json` evidence remains preserved in ignored outputs. Existing homepage same-image duplicate OG and missing construction WebPage `dateModified` are also out of scope. No private Search Console metrics, search queries, email data or raw customer records belong in this release record.
