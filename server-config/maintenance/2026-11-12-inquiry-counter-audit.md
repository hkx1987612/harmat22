# 2026-11-12 - Inquiry Counter Stuck At160 (Read-Only Diagnosis)

User reported the top sales inquiry number never moves from160. No live repair
has been authorized or applied in this investigation. Existing website, CRM
records, role permissions, quote/PDF and appointment handlers are unchanged.

## Cause

`render_sales_portal_inquiries` obtains `$all_rows` from
`sales_inquiry_summary_rows('', '', $lang)` and uses `count($all_rows)` for the
summary-total tile and source subtotals. The row builder fetches only120 website
posts, combines visible client files and finally returns only160 newest rows.
Consequently a manager with more than160 combined records sees a160 "total";
new records can displace old ones while that number stays fixed. Source subtotals
also describe that truncated slice, not all accessible records.

Relevant current source: sales-manager PHP lines8721,8770,8855. The separate
`count_offer_inquiries()` is an uncapped website-post count, but is not used by
this combined summary. It cannot alone replace a mixed-source, role-scoped total.

## Evidence

- Read-only SFTP source comparison confirms production matches the repository
  and retains both caps. SSH reused the existing host-key-verified transport;
  no account impersonation or auth/security changes.
- Server-only aggregation read active website post counts/dates and normalized
  client-file counts/dates. Combined manager records exceed160; recently created
  records exist. Only aggregates leave the server, no names/phones/email/messages.
  Exact snapshot is private in outputs/2026-11-12-inquiry-counter-audit/aggregate.json.
- Offline synthetic reproduction using the existing WP stub harness:202 records
  show160; after adding another synthetic record,203 still show160. A separate
 180-website-record case loads only120. No database, mail or network in this test.
  This characterizes the defect, not acceptance criteria for a fixed version.

## Proposed Safe Scope (Pending User Approval)

Separate full authorized totals/source counts from bounded display rows. Preserve
manager/staff object visibility and archived/deleted-record semantics. Do not
report a record count as deduplicated effective buyers, conversions or visits.
Keep fetching/rendering bounded; do not merely raise160 to another arbitrary cap.

Removing the website cap needs a deliberately uncapped/batched read path:
`get_offer_inquiry_posts` currently applies absint to its limit, so passing-1
would request one row, not all rows. Review historical search/date behavior as
well as totals. New server pagination must not silently truncate the existing
Android client's fixed-view HTML reader; coordinate the contract before changing
the table/navigation structure. Do not touch business mutations to fix this.

Before any approved release: fresh private rollback backup, source drift check,
synthetic over-limit/filter/permission tests, HU/ZH desktop and Android contract
checks, quote/appointment/source-data parity and post-deploy aggregate verification.
No live appointment or enquiry should be submitted for a display-counter repair.
