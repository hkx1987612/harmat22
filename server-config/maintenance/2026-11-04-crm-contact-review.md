# CRM Contact Review And Reminder Classification

## Approved Scope

User approved the proposed contact-workflow refinements, desktop-first warm
presentation, filtering, quiet secondary actions, duplicate-contact hints and
reminder review. Back up first. Preserve customers, quote/PDF and appointments.
Concurrent-save handling is an isolated audit, not a storage migration.

## Candidate 1.6.125

- Inquiry progress is distinct from notification-mail state. A sent email never
  implies a recorded customer conversation. Existing linked deals open directly;
  no inquiry is automatically converted and no timestamps are rewritten.
- Inquiries filter by progress and owner in addition to existing source/search.
  Clients filter by name, email, formatted phone, status and owner. Filters operate
  after the existing permission checks. Existing inquiry retrieval caps remain.
- Long notes expand in place. Delete forms move inside More; original nonces,
  confirmation, permissions and POST payloads remain identical.
- Client editor displays matching phone/email from already-visible records.
  Editing excludes the same lead. Hints never merge, block, overwrite or submit.
- Default tasks include recorded follow-up, viewing and financial dates.
  Calculated sales suggestions remain in a separate view and no longer inflate
  scheduled overdue summaries or the existing daily reminder email. No reminder
  recipients, transport, cron schedules or stored records are changed.
- Matching Chinese/Hungarian labels; 15px search inputs and 42px controls.
  Existing desktop warm-gray/sage presentation remains scoped to private CRM.

## Backup And Verification

- Fresh full transactional backup: 79 tables and private CRM source/material
  archive, SHA256-verified outside the web root. No database restore drill.
- 148 existing CRM checks plus 121 new synthetic checks passed. Fixtures cover
  role isolation, filters, linked deal routing, escaping, duplicate-data scope,
  explicit follow-ups, real viewings/payment dates and retained suggestions.
- 481 other methods remain byte-identical to base 2ac5636. No save, assignment,
  conversion, delete, permissions, mail transport, quote or appointment handler
  modifications. CSS fixture exactly matches embedded private CSS.
- Desktop 1440/1920 and narrow390 checks: no page overflow; search, duplicate
  hints, note disclosure, More actions, language switch and task tabs exercised.
  All preview records are synthetic. Screenshot and detailed receipts stay in
  ignored outputs/2026-11-04-crm-review/.
- Guarded release: DEPLOYED_VERIFIED. Eight public form/SEO contracts and all
  protected data/unrelated sources unchanged. Zero live submissions.
- Native POST parity per language:195 client,320 inquiry and237 deal forms.
  The inquiry GET filter intentionally changed and is not part of POST parity.
 97 visible clients and four owner buckets independently reconciled.
 341 original reminders retained:146 scheduled and195 calculated suggestions.
- Appointment integrity26 cases, antispam727 checks and four offline
  deployment/rollback scenarios passed.
- Authenticated live GET checks:89 unrecorded inquiries;97 clients restored
  after filters; seven unassigned clients all match that state; both languages
  retain97 clients.146 scheduled and195 suggestion rows, with no overdue badges
  in the suggestion table. Client body15px, table fills wrapper, no page overflow.
- Native desktop screenshot capture timed out. Synthetic desktop screenshot was
  saved and inspected; do not describe it as a live-customer screenshot.
  Temporary viewport reset succeeded. Final tab-retention/cleanup command was
  rejected after the completed DOM audit; no retry or alternate UI bypass.
- Source SHA256: e40599dca32a4c15803a79c669abdf2d863480fdd8516c4d8a70748c3895749c.
  Private source rollback folder:
  /home/harmath2/codex-backups/crm-contact-review-b1c2a0b148a14a6fbdcc8e552f52af8f.

## Concurrent Save Finding

The isolated memory-store test invokes the actual existing save_leads/save_deals
methods. Interleaved stale snapshots reproduce lost updates to different records
and max-ID collisions when creating two records concurrently. No live writes.
Legal-documents also writes harmat_sales_deals_v1. A lock only in one CRM method
would not protect all writers or stale forms and could leave false success notices.

Next storage batch must cover all shared writers with per-record conflict/version
checks or an equivalent atomic store, unique ID allocation, explicit conflict
handling at callers, preservation of side-effect order, and migration/rollback
tests. Do not claim this risk fixed by the presentation release. Until then avoid
simultaneous edits/saves by multiple operators. The full backup is not a concurrency
solution and must not be restored over newer customer data.

## Recovery

Use the exact private source backup pinned by the new guarded deployment receipt.
Restore only the CRM PHP file after drift and syntax checks; flush cache and
recheck public form contracts. Never restore the database to undo this release.
