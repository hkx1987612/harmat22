# Property Summary Pilot - 2026-10-09

## Scope

Discovery MU version `1.0.2` adds visible Hungarian summaries to four published
properties: `4349 / A1-2-L4`, `4388 / A1-4-L1`, `4418 / A1-4-L4`, and
`5146 / A2-1-L5`. The original twelve pilots retain their exact summary output.
The four additions use current property metadata for indoor/outdoor areas,
room count, floor, availability and visible price. They link to the corresponding
building selector, apartment search and construction journal using ordinary
HTML anchors. Existing apartment-search HTML already links to all four units.

The reviewed changes affect one live MU file. No property records, pricing,
area overrides, quote calculations, forms, CRM, home title/video, schema,
IndexNow behavior, reminder options or indexing rules were changed.
The selected pages receive no extra image, video or external script requests.
No ranking or indexing outcome is guaranteed by this content pilot.

## Existing Data Difference

Pre-deployment browser review found differing sales-area values between the
existing rendered hero and stored override/discovery helper:

| Unit | Existing Hero | Stored Override |
| --- | --- | --- |
| A1-2-L4 | 55.81 m2 | 55.82 m2 |
| A1-4-L1 | 67.05 m2 | 67.04 m2 |
| A1-4-L4 | 59.48 m2 | 59.49 m2 |

The first local candidate repeated sales area and was rejected before deployment.
The final new summaries explicitly label indoor area and leave sales-area
presentation to the original facts table. Existing hero values, metadata,
quotes and schema remain unchanged. This release does not fix that pre-existing
discrepancy; its source and intended commercial values need separate review.

## Release And Recovery

- Reviewed Git baseline: `b9fd4a2c0716e423f15e4510e4f569f456e2b7f8`.
- Live target: `/home/harmath2/public_html/wp-content/mu-plugins/zz-harmat-search-ai-discovery.php`.
- Baseline LF SHA-256: `35e39cb75888f657899939de72c5ba90b16c4eee5bf1d98bfcb68c3670765bb3`.
- Released LF SHA-256: `7a794651698b9af76c911e83700697753f8cc3263c6d307891db315d0026ebfa`.
- Test LF SHA-256: `13c914d003b08766c1afa15aab9807b53fc633eb8ee8a9affb147e573003e59e`.
- Private backup: `/home/harmath2/codex-backups/property-summary-pilot-e5cea437f47f466ca6880fb8be24dd73`.

The private directory is 0700 and its before/after source and manifest files are
0600. Exact source bytes and permissions were verified by readback before
activation. The hidden temporary file and final file passed production PHP 8.3
lint. The private isolated fixture passed 191 assertions before activation.
Atomic replacement, exact final hash, page/object-cache purge and 13-route HTTP
smoke passed. All 124 public property fact hashes, reminder/IndexNow option
hashes, home keyword source and reminder source remained unchanged. Offer-lead
counts were unchanged during deployment; root error_log remained absent.

The tracked deployment tool requires an explicit mode:

```powershell
python server-config/maintenance/2026-10-09-deploy-property-summary-pilot.py --verify
python server-config/maintenance/2026-10-09-deploy-property-summary-pilot.py --rollback /home/harmath2/codex-backups/property-summary-pilot-e5cea437f47f466ca6880fb8be24dd73
```

Rollback verifies the exact backup and current released source, stages the
original privately backed-up bytes, lints temporary/final PHP and clears cache.
It refuses a concurrent source change. It never restores database records.
The `--deploy` mode is pinned to the original reviewed pre-release Git baseline
and source/test hashes; it is not a general redeployment tool for later edits.
SSH credentials and known-host trust must already exist on the maintenance
computer. No credentials or raw backups are committed to GitHub.

## Validation

Local PHP lint and 191 focused assertions passed. Baseline parity covered twelve
old summary fixtures, 102 schema/default-description/head cases, unchanged
action/filter registration and unchanged IndexNow source. Existing metadata
regression tests passed 196 assertions. Four captured before/after HTML fixtures
preserved original markup outside the summary/CSS additions. Revised candidate
Chrome previews passed at 1440px and 390px, including original page geometry,
images, metadata, form behavior, summary layout, live numeric facts and links.

Final live Chrome checks passed 18 key-route cases and eight selected-property
cases at desktop/mobile viewports. Original content outside the additions,
metadata, schema, image sources, page geometry, search filter/reset behavior,
quote preselection, five source options and required unchecked privacy control
were preserved. New summary figures matched the existing indoor/outdoor/price
facts; each page had exactly one summary and three working public links.
All checked images decoded; summary layout and rotation pixel checks passed
with no errors, overflow, blank frames or recorded mutator requests.

The final scan exited 0 over 145 pages, 124 properties and 610 assets, with
all four issue counts zero. It retained the prior 145-page URL set. Two prior
Elementor animation stylesheet references were absent from this scan's HTML;
the resource total is a count of observed references, not a fixed site invariant.
Independent SSH/HTTP review verified backup hashes/modes, exact published source,
absent owned staging/lock and fresh-query summary content. One SSH connection
timed out; a subsequent attempt passed without another deployment.
The aggregate safety state was unchanged through the live browser checks.
All deployment, browser, audit and scan processes finished.

Release tag: `stable-2026-10-09-property-summary-pilot-current`.
Ignored evidence is under `outputs/2026-10-09-property-pilot/` and
`outputs/2026-10-09-property-indexing-pilot/`.

The browser harness blocks writes, mutator URLs and external tracking for the
whole session. It enters no contact details and submits no forms or test leads.
The final public scanner uses two concurrent requests with the existing checks.
Tests cover Chrome desktop/mobile viewports; real Safari/iOS, China-network
performance and production mail/inquiry submission are not claimed.
Private Search Console metrics and query data stay in ignored local evidence.
