# Assistant live public data and selection context

Baseline: `d221189` / `stable-2026-09-10-home-native-video-current`.
Stable tag: `stable-2026-09-10-assistant-live-data-current`.

## Scope

- `wp-plugins/harmat-local-assistant/harmat-local-assistant.php`: version 0.4.0, live-data filter, structured page-local context, localized status/error labels, known-price budget/cheap matching, and unified quote-card actions.
- `wp-mu-plugins/zz-harmat-assistant-live-data.php`: version 1.0.0, read-only adapter using `harmat_sai_property_summary_data` and strict context allowlist. No new option, table, public data route, external AI service or database write.
- Queries read published A1-A4 apartment posts and preload their metadata once per ask request. There is no new persistent apartment cache to invalidate. Missing public summary helper returns no apartments rather than stale prices. Removing the adapter alone returns the original static JSON fallback, so use the complete rollback below.
- New selection state contains room count, price ceiling, area bounds, building, floor and outdoor preferences only. It is held in page JavaScript memory and sanitized by the server. Existing optional handoff transcript behavior is unchanged.
- Explicit new room/floor/area constraints replace prior values; balcony/garden/floor/budget restrictions can be cleared, and new search resets the context. Nearby alternatives retain the existing explicit non-exact-match wording.
- Quote cards call the existing modal's public opener, with a property-page hash fallback. The quote form itself remains the existing Hungarian form. Pricing, source choices, CRM submission, consent and Ads conversion rules were not edited.

## Backup And Deployment

Private backup: `/home/harmath2/codex-backups/assistant-live-data-20260910-183744/assistant.php`.
The MU adapter did not exist before deployment.

Use `2026-09-10-deploy-assistant.py` with no arguments for baseline preflight, `--deploy` for the guarded baseline deployment, or `--verify` to check this deployed version. The baseline deployment deliberately refuses to overwrite later work.

Deployment verified the live plugin against Git, copied the original outside public_html, uploaded both files with non-PHP temporary names, linted both before installation, installed the adapter first, atomically replaced the main plugin, linted final files, purged page/object cache, checked nine public routes and checked server state. Hashes and audit outputs are local in ignored `outputs/assistant-live-data/`.

Rollback from this exact deployed version:

```powershell
python server-config/maintenance/2026-09-10-deploy-assistant.py --rollback /home/harmath2/codex-backups/assistant-live-data-20260910-183744
```

Rollback verifies both live hashes, restores the old main plugin through a temporary linted file, removes only the added MU adapter, then clears cache. Repeat key-page and quote-opening regressions. No database restore is needed. Do not replace files if another computer has since modified them; reconcile against GitHub first.

## Verification

- `php server-config/maintenance/2026-09-10-test-assistant.php`: 39 assertions including three languages, continued criteria, FAQ, reset, cheaper ordering, hidden prices, reserved exact lookup, 47.83 m2, field allowlisting and quote URLs. Synthetic local fixtures never touch WordPress.
- `node server-config/maintenance/2026-09-10-test-assistant-live.mjs`: real public ask endpoint and quote opening on desktop 1440px and mobile 390px, all three languages, correct A3-4-L5 selection, five sources, context refinements/reset, no horizontal overflow, simulated 503 fallback, zero page errors and zero attempted inquiry requests. External analytics and assistant event POSTs are blocked in this harness; ordinary server question counters are exercised.
- `node server-config/maintenance/2026-08-29-test-construction-regression.mjs`: all nine routes pass on desktop/mobile, including public Hungarian text, one H1, construction assets, selectors and the A1-1-L2 quote summary.
- `node server-config/maintenance/2026-07-31-scan-public-site.mjs outputs/assistant-live-data/site-scan.json`: 145 pages, 124 properties, 588 assets, zero sitemap/page/property/asset issues.
- Preflight: 124 apartments, zero missing PDF fields, zero currently hidden prices, all four `A[1-4]-4-L5` sales areas exactly 47.83 m2.
- Final read-only verification: live version 0.4.0, exact two-file hashes, 124 apartment rows, no staging files, 44 private offer leads and unchanged root error-log size/time `134931 1788259806`.
- Mobile screenshot reviewed: existing layout retained, long answers scroll inside the widget, no overlap/overflow introduced.

## Limitations

This remains the local deterministic assistant, not free-form generative AI. It does not infer arbitrary conversational statements. Context is intentionally not retained after page reload/navigation. The quote form is the existing Hungarian form even when opened from an English or Chinese answer. Actual inquiry submission, native iOS/Safari and high-concurrency load were not tested; no fake customer record or conversion was created. Existing extended FAQ claims were not re-researched in this change. Future polish can shorten verbose replies and improve context visibility after measuring real visitor usage.
