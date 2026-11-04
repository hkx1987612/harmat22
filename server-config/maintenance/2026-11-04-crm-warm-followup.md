# CRM Warm Presentation And Deal Ordering

## Status And Authority

Released CRM1.6.122 on2026-11-04 after the user authorized direct replacement of
the first CRM iteration. User requested a warmer desktop-first presentation,
own/walk-in inquiry counts and totals, and a fix for old deals dominating the
display. Public website, quotes/PDF, appointments and business records are out
of scope. No customer record was submitted, deleted or rewritten.

## Changes

- Private sales-portal CSS only: warm gray and sage surfaces, restrained green
  actions, consistent system typography, focus outlines and readable desktop
  filter widths. Destructive actions retain their separate red treatment.
- Daily table: date, visitors, pageviews, website inquiries, own/walk-in
  inquiries, total. Total is website plus walk-in, not broker inquiries and not
  an advertising conversion rate. Website counts retain their existing source.
- Own/walk-in registrations use the existing normalized lead source and original
  creation date. Edits and generated deal copies do not count as new inquiries.
  Legacy source fallback behavior is retained. Manager-only totals remain
  restricted; non-manager placeholders do not reveal counts.
- Deal default is newest update first. Missing/invalid update dates use creation
  date, then descending ID for a stable tie. Existing oldest-overdue/follow-up
  priority remains available in the display-order filter. All stages, records,
  permissions and POST forms remain; exported record ordering is unchanged.
- Sorting does not create new customer activity or alter update timestamps.

## Backup And Release

Fresh full database/material backup outside the web root:

`/home/harmath2/codex-backups/crm-before-simplification-7d4f075b61714e2d8dc31f684943b0a6`

-79 InnoDB tables, consistent transaction; SQL26414185bytes.
- SQL SHA256 `1b5a3fb1a109b43b001df1802a17921729bd9a36975a8bf7e8fd4c89a8b27e87`.
- Private material archive301005bytes, SHA256
  `4d355e84d82d210c47908709e0be72cda6f77e93f19b58984d938154afb2cdd8`.
- Private directory0700/files0600, checksums and archive checked; no restore drill.

Final source preflight:

`/home/harmath2/codex-backups/crm-warm-theme-e10ec0ebf3dc42ca8de092ffc89bb5ff`

Only the CRM PHP entry file changed. Baseline repository commit `fec1e2a`.

- Old raw SHA256 `a450cdb7c0b5b1b0b71a7e4316094aa62f3bc9dc3d3950193ab4efe41e429d72`.
- New raw SHA256 `354dbfef7cb04db9c129b868a9791f8092816151c48935604cb4f7c0122c3c27`.
- PHP8.3 staging lint, pinned-byte/source-drift/protected-state gates passed.
- Atomic source replacement/cache flush returned `DEPLOYED_VERIFIED`.
- Eight public form/SEO contracts, protected CRM options, posts/postmeta and
  unrelated source hashes remained unchanged. Zero submissions.

## Verification

-142 isolated UI assertions: manager/staff, Chinese/Hungarian, six-column totals,
  creation-date counting, legacy source, missing dates, exclusions, sorting,
  stable ties, invalid filter input, record conservation, CSS fixture parity.
-478 existing methods byte-identical; eleven allowlisted presentation/read-only
  method changes. Write/auth/quote/booking methods are unchanged.
- Native read-only transaction:195 client forms,321 inquiry forms and237 deal
  POST forms unchanged in each language. GET sorting controls intentionally
  differ. Walk-in aggregates match an independent check for two dates.
-26 existing appointment-integrity cases,727 antispam checks and four mocked
  release/recovery cases pass.
- Actual local Chrome desktop previews at1440 and1280: dashboard, inquiries,
  clients, inventory and deals checked; warm surfaces, controls, language switch
  and recent-first sample order inspected. Dense tables scroll inside their
  own containers; page-level desktop overflow was not observed.
- The browser connection timed out during final narrow-screen work. Final
  mobile screenshot and post-release live visual checks were not completed.
  A temporary viewport override may remain; reset was not confirmed. Do not
  reuse first-release mobile checks as proof for this updated theme.
- Saved desktop screenshot contains synthetic data and predates the last small
  label/filter-width polish; it is not a live customer-data screenshot.
- No physical-device, complete sales-workflow, mail-delivery or restore test.

## Commands And Recovery

`2026-11-04-deploy-crm-warm.py` is offline by default. It reuses the guarded
transport with a one-file allowlist, independent lock and the fixed baseline.
`--preflight` must precede `--deploy`; deployment refuses changed reviewed bytes,
changed live source or changed protected records. Earlier preflights are stale.

For a UI regression, restore only `crm-old.php` with its recorded metadata from
the final private manifest, and only if live bytes equal the released new pin.
Never restore the database for this presentation update. New customer inquiries
may have arrived. Preserve third-party edits and diagnose before overwriting.

Raw reports, screenshots and private manifests stay in ignored
`outputs/2026-11-04-crm-review/`; raw database and assets stay on the private host.
