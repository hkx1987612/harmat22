# Messenger public inventory - approved release

## Current Status

User explicitly approved the separate public read-only interface and verification. It is now installed and publicly verified. Existing website frontend/assistant, offers, CRM, nogakft app.py/widget/proxy, Meta app/Page and advertising were not changed. This is NOT permission to process Facebook customer messages or publish the app.

Current shared source: `wp-mu-plugins/zz-harmat-messenger-public-inventory.php`. Formal production filename: `wp-content/mu-plugins/zz-harmat-messenger-public-inventory.php`. Earlier outside-MU candidate placement was only the approval-pending checkpoint and no longer exists.

Released LF SHA256: `0e4911367af710391070a675f24f00fd333a0a45fe5e460d4f5f462fef0137d8`.
Stable tag: `stable-2026-10-15-messenger-public-inventory-current`.
Public URL: `https://harmat22.hu/wp-json/harmat-public/v1/apartments`.

## Why a New Read Path

Existing nogakft backend is healthy, with current local provider, but its configured WordPress AI-unit and AI-lead routes were absent from the inspected registry. One public namespace-index GET returned404 `rest_no_route`. Inspection did not invoke an authenticated business handler or prove every authentication context; do not claim the server is stopped or route that has been configured is working. Do not revive the old credentialed lead-write path automatically.

Prefer a small independent read path grounded in the same existing `harmat_sai_property_summary_data` authority as the public website. No historical Markdown/JSON price snapshot or nogakft default-knowledge fallback is current inventory truth. Existing sales-area overrides, availability and hidden-price flags remain authoritative.

## Exact Contract

- GET `/wp-json/harmat-public/v1/apartments`; no POST/write route, authentication widening, customer data, CRM, booking, tracking, remote model or external request.
- Published, non-password `property` apartments A1-A4 only, canonical strict apartment codes. Full-post query primes ordinary caches; bound501 and fail503 above500, duplicate codes/invalid data/authority failure fail closed.
- Schema1: generation UTC timestamp, canonical search-page source, HUF currency and `items`. Each item contains only `code`, `url`, `building`, `floor`, `rooms`, `sales_area_m2`, `outdoor_area_m2`, `status`, `price_huf`.
- Same-origin HTTP(S) scheme/host/default-equivalent port, no URL credentials/query/fragment. No raw IDs, metadata, hidden amounts, per-area price, client or sales fields.
- Existing hidden/no-display/nonpositive price becomes JSON null, never a guessed amount or historical fallback. No automatic quote, loan or viewing/reservation promise.
- Responses cache for60seconds with revalidation; unavailable data gets503/no-store and Hungarian error text without source/exception leakage. Both carry noindex so the data feed is not a new SEO landing page. No custom transients or database-writing cache/analytics introduced.

## Verified Preparation

Sol implemented candidate and isolated PHP fixtures. Parent reviewed, tightened origin scheme/port checks, moved the candidate OUT of the MU source directory to avoid accidental installation, and independently passed61 checks plus lint. Fixtures load the actual repository authority functions and cover both hidden-price flags/hidden-value absence,47.83 override, reserved/sold, nonpublic exclusion, exact fields, cap500/501, dependency/error privacy and a token-level read-only function allowlist. No actual hidden price was inserted into production for testing.

Private CLI preflight then verified exact normalized source parity of the live authority, staged/linted the candidate and checker outside the webroot with0700/0600 permissions, and invoked only its read function. It returned124 current published apartments, exactly nine item fields, protected A3-4-L5 sales area47.83 and a matching source. At this snapshot all124 source prices were public, so live hidden-price cases were NOT exercised; synthetic tests supply that coverage. No full inventory/individual price/contact dataset exported to Git.

Historical private preflight: `/home/harmath2/codex-backups/messenger-inventory-preflight-0ee65c24aa12413fa4d8354966270a3b`,19:37:30UTC October15, older candidate LF hash `ae31221605ca533aea6f46abc33c4b7bb6ee7b8ce654d8df83be40c52949d5ba`. Authority raw SHA256 `7a794651698b9af76c911e83700697753f8cc3263c6d307891db315d0026ebfa`. The approved comment/noindex revision was preflighted again at19:43:00UTC in `/home/harmath2/codex-backups/messenger-inventory-preflight-148076d805ea42198f10c8fbe25640d4` at the final released hash. Neither preflight itself installed a public route.

Tests: `php server-config/maintenance/2026-10-15-test-messenger-public-inventory.php`. Private preflight orchestration: `2026-10-15-preflight-public-inventory.py`. No local server remains running.

## Production Verification and Recovery

Exact private0700/0600 release backup:
`/home/harmath2/codex-backups/messenger-public-inventory-f9ef365a50a24c2b89fc03e28962c077`.
Manifest records original target absence, released/test hashes, protected-source copies, aggregate protected-record hashes, pre-release health and61 production-PHP isolated fixtures. Staged non-PHP file and formal PHP8.3 lint/hash passed, new-file same-directory activation and cache purge succeeded. No existing plugin was replaced.

Anonymous GET returns200/JSON,24,228bytes,124 units, exact nine item fields,91 current/31 reserved/2 sold. Current124 prices all public; hidden-price flags and null/absence are fixture coverage, not claimed live hidden examples. A3-4-L5 remains47.83. Anonymous payload hash matches a fresh native read of the same public authority; timestamp/cache/noindex checked. Native registered handler is GET-only and actual empty POST returns404, without a valid inquiry or mail. Nine key public routes return200 and no fatal markers before/after. All property/offer rows (including the120 trashed records), protected metadata and four named CRM options retain exact hashes. Existing sales, integrity, anti-spam and authority sources remain unchanged. Separate new-process release verification and trash120 readback pass. These are HTTP/CLI checks, not a new visual/mobile browser pass, inbox receipt or complete booking/Messenger delivery test. Earlier positive appointment TEST was not repeated.

Guarded deployment/verification/recovery tool: `2026-10-15-deploy-public-inventory.py`.

```text
python -B server-config/maintenance/2026-10-15-deploy-public-inventory.py --verify /home/harmath2/codex-backups/messenger-public-inventory-f9ef365a50a24c2b89fc03e28962c077
python -B server-config/maintenance/2026-10-15-deploy-public-inventory.py --rollback /home/harmath2/codex-backups/messenger-public-inventory-f9ef365a50a24c2b89fc03e28962c077 --allow-live-write
```

Do not redeploy on Git pull. Deployment refuses an existing target; rollback removes ONLY this file while its exact released hash still owns it, then purges cache and checks pages. It does not restore customer/CRM records, replace old plugins, remove unknown files or alter nogakft. Future legitimate customer/data changes will make historical full-snapshot verification stop for investigation; never restore old records just to satisfy that guard.

## Next Gates

1. DONE: narrow approval, backup, staged/formal lint, independent public read-only/schema/data and protected-record verification. Keep existing anti-spam protection intact; never repeat the earlier positive appointment TEST.
2. Local fixed-URL bounded/schema/freshness reader is now implemented with31 local/Linux fixtures passing, but not integrated/deployed as a service. Actual nogakft GET fails closed at TCP443 timeout; A-computer GET124 succeeds. Resolve verified connectivity safely before claiming host sync; keep existing app.py/service unchanged. No arbitrary customer-provided URLs, stale/default price fallback or old shared CRM credentials. Latest proof/gates: `2026-10-15-facebook-messenger-nogakft-staging.md`.
3. Python metadata store is local-only/default-stopped. Authenticated raw-body transport, timed retention, actual model/cost controls, approved message-data flow, Page grant/current Meta review rules, outgoing delivery recovery, staff takeover and a specifically authorized real test conversation remain separate gates. Never enable ordinary-customer replies based on these61/30 tests alone.
