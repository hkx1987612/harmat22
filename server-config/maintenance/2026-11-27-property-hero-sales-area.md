# Two Property Hero Sales Areas

Released 2026-11-27 after the requested prelaunch regression. Only the property hero now reads the existing numeric sales-area authority for the exact ID/title pairs 5302 / A3-F-L3 and 5460 / A4-F-L1. The previous A1-F-L1 exception is preserved. No records or calculation methods were changed. Confirmed source: sales ledger, matching floor-plan sheets, and current public inventory agree that only half of the terrace, not the separate garden, is included for these two units.

| Unit | Indoor | Sales | Public total, HUF | Displayed HUF/m2 |
| --- | ---: | ---: | ---: | ---: |
| A3-F-L3 | 43.74 | 47.39 | 71037750 | 1499003 |
| A4-F-L1 | 57.05 | 60.10 | 89969250 | 1496993 |

Before release, checked 145 sitemap URLs (21 pages and 124 properties): all HTTP 200, canonical title and primary heading, language switch and native form present. The exact isolated renderer passed 1578 assertions. Chromium checked both candidate summaries at 1440, 390 and 320px; desktop and mobile also passed home playback, manual apartment rotation, total-budget filtering, selector navigation, neighborhood search, financing, and quote and appointment open/close. The postflight matched 13 live routes after excluding only the two owned fact cells per target. Repeated the six target Chromium checks against the actual live HTML. No forms submitted. Images, structured descriptions, payment conditions, quote/booking code, inventory, and the unrelated local presentation drafts were not deployed.

Private backup: `/home/harmath2/codex-backups/property-hero-area-20261127-3a249c665eaf4405b5ec21e437a70a9c`. `before.php` SHA-256 `f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077`; `after.php` and the live module SHA-256 `870f15f8d3967405607b5231e2cccd3f40531455ee2aebcf64c20ff0f8892f4a`. For rollback, verify the live module is still identical to the backed-up after file, then lint and atomically restore only the before file, preserving mode/owner, purge cache and recheck the two routes. Do not restore data, forms, or newer releases. Local run evidence remains ignored under `outputs/2026-11-27-prelaunch-sales-area/`.
