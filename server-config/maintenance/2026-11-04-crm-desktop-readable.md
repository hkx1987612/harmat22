# CRM Desktop Readability Follow-up

## Scope

- User requested larger text, natural desktop spacing and use of available width.
- Private sales portal CSS only; no public-page, offer, PDF or appointment changes.
- The legacy inquiry table was fixed at1180px (1260px in Hungarian), leaving unused
  space inside a larger wrapper. Columns also had fixed minimum/maximum widths.
- CRM1.6.123 makes the inquiry table fluid, with a1320px minimum and proportional
  columns. Body15px, secondary13px, larger controls, softer row boundaries.
- Read views use a wider shell; inquiry search fields share one desktop row.
- CRM1.6.124 also makes the client table fluid and overrides its legacy14px rule;
  assignment controls stay horizontal. Deal metadata is14px. No new data logic.
- Below1181px the existing internal table scrolling remains. No page-wide overflow
  was observed in the tested narrow preview. This is desktop-first, not a mobile redesign.

## Read-only Data Audit

- Original inquiry rows, client registration dates and dashboard day totals were
  reconciled across seven dates. Website plus own/walk-in equals the displayed total.
- Default deal order was checked against actual update timestamps, then switched
  to legacy follow-up order and restored through GET filters. No records changed.
- Old-looking deals are also a workflow issue: recent inquiries have not yet been
  converted into deals. No automatic conversion or timestamp changes were made.
- Exact aggregate evidence and screenshots stay in ignored local outputs, not Git.

## Safety And Verification

- Full79-table transactional database backup and source/material archive verified
  outside the web root. Private snapshot:
  `/home/harmath2/codex-backups/crm-before-simplification-72dfd456803e4071a57389b0676f2c99`.
- Source scope allows only `sales_portal_warm_css`;488 other methods are identical.
- 148 isolated UI checks;26 appointment-integrity cases;727 antispam checks;
  four offline release/rollback scenarios. No live customer submissions.
- Native read-only comparison confirms all original client, inquiry and deal POST
  forms are byte-identical in Chinese and Hungarian.
- Desktop preview screenshots inspected at1440/1920; Hungarian and Chinese
  inquiry/client views have no clipped buttons at1440.1280 uses internal scrolling.
- 390px preview has no page-wide overflow. Temporary viewport override restored.
- Live inquiry table after1.6.123 uses the full wrapper, with15px text,13px metadata,
  all original rows and no clipped action controls.

## Release Status

- 1.6.123: `DEPLOYED_VERIFIED`, eight public form/SEO contracts and all protected
  data/unrelated sources unchanged. Source SHA256:
  `8a66ae40ebfc13cb19d89efb7caf968dd2d906b83de55a85d4638dd3f957fb30`.
- 1.6.124: `DEPLOYED_VERIFIED`, the same eight public form/SEO contracts and
  protected data/unrelated sources remain unchanged. A stale intermediate
  candidate was rejected before any live write, then fresh preflight passed.
  Private source backup:
  `/home/harmath2/codex-backups/crm-desktop-readable-66642a5adf294f3c8fba91972231b76e`.
  Source SHA256:
  `e92995ae62de300230564225b1f58e4b8eca8c67637a23f039bcd8f3d21d4caa`.
- Live authenticated follow-up confirms both tables fill their wrappers,15px
  body text and no clipped buttons/page overflow. Saved live inquiry/client
  screenshots were inspected. Daily totals and recent-update sorting remain
  correct. Original record counts retained; zero live submissions.
- Final duplicate screenshot/cleanup calls were rejected by the browser. No
  further UI attempts; the already saved live screenshots and final DOM audit
  remain valid. Viewport reset had succeeded before that interruption.
- Source-only rollback is available from each private preflight folder; never
  restore the database over subsequent customer activity.
