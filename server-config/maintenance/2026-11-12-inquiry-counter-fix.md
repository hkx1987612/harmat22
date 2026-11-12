# 2026-11-12 - Actual Inquiry Counts And Walk-In Subtotal

User approved correcting the stuck160 summary and explicitly requested a walk-in
subtotal and actual total. Sales Manager version1.6.127 is released.

## Scope

- Full authorized rows drive website/walk-in/broker subtotals and the total;
  the160 rendered-row cap no longer becomes a false total. Added records update
  the total instead of merely displacing an older displayed row.
- Website posts are read in200-row batches with date/ID descending ordering,
  preserving private/publish/draft semantics. The shared offer-post helper,
  business handlers, permissions, row action renderer and table schema are intact.
- Source/search/owner/status filters apply before the rendered cap. Historical
  records beyond the old120 website limit can be searched. Full summary totals
  remain independent of current list filters, as before.
- HU/ZH summary order: website, walk-in, broker, loaded records, total. Loaded
  describes the server-rendered slice, not the client-side date filter result.
- Grain remains records: website posts plus normalized visible client files.
  A client file with website source contributes to the website subtotal. A form
  and a separate client file can represent the same person; these totals are NOT
  deduplicated effective customers, unique buyers, conversions or actual visits.
  No automated merging or destructive deduplication was requested/performed.

## Verification

- Fresh private transactional snapshot of79 tables and CRM/private materials;
  table/archive validation and checksums passed. No raw backups downloaded.
-495 existing parsed methods unchanged; only the inquiry renderer/row builder and
  one new batch iterator changed, plus plugin version metadata.
-148 existing CRM checks and317 targeted synthetic counter checks pass: empty,
 120/121/160/200/201/400/401 website boundaries, growth/removal, source sums,
  source/status/owner filters, historical search, staff isolation and bounded HTML.
- Native CLI audit uses read-only transactions; manager totals independently
  match source post counts plus normalized client files. Source tiles reconcile
  in both languages; two existing staff contexts retain the original visibility.
  Only aggregate counts leave server memory; no customer HTML is exported.
- Native original/new POST-form parity: clients, inquiries and deals in both
  languages. Existing inventory/reminder/daily walk-in checks also pass.
- Six synthetic Chromium layouts at1920/1440/390 widths in HU/ZH: source/total
  values, no card overlap/overflow, no page-level horizontal overflow and unchanged
 9-column/160-row contract. Screenshots visually inspected; synthetic data is not
  claimed as a signed-in production browser or physical-phone acceptance check.
-20 current-renderer Android fixtures and835 parser/role/total/session checks pass.
  No app source/APK update or new backend API needed.26 appointment integrity,
 727 antispam and4+7 release/rollback guard checks pass; no live form submitted.
- Atomic source-only deployment verified8 public offer-form/SEO contracts;
  protected posts, business metadata, CRM options and unrelated source hashes
  unchanged. Derived Elementor cache alone remains excluded from equality checks
  with its full hashes retained in the private audit.

## Artifacts And Recovery

Ignored local evidence: outputs/2026-11-12-inquiry-counter-fix/ including backup,
preflight/deployment receipts, live aggregate audit and synthetic preview images.
Exact business counts stay there, not Git. Source SHA256 after deployment:
86433965f9e5bc74173bbb0cac8b1431af611887f4dafd40b0db6149eab969e2.

The private preflight manifest pins original/candidate raw bytes and metadata.
Restore only the owned CRM PHP file if its live hash still matches this release;
stop on foreign drift. Never restore the database to undo a display fix. Follow
the existing guarded source replacement, lint/cache flush and parity checks.
No pricing/PDF, appointment, ads, role, customer-record or automation changes.
