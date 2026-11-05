# Inventory-Backed Reservation Count

## Scope And Decision

User reported inventory38 versus reserved-deal32 and explicitly chose inventory
as the authority. CRM1.6.126 changes the sales-deal reservation badge to a
dynamic count of unique published property IDs with the existing `sales_status`
of `reserved`. It is not a literal38 and not a count of buyers/contracts.

The badge opens `sales/?view=deals&inventory_status=reserved`: one row per
reserved apartment, existing visible deal links, and missing/conflicting links.
All59 original deal records remain in the ordinary view. Other stage filters,
historical conversion metrics, writes, ownership and assignment remain unchanged.
Managers and staff share inventory totals, but customer/deal details still pass
through the existing `visible_deals` permission filter. Staff absence labels do
not imply that another salesperson has no associated deal.

No lead/deal was fabricated, merged, deleted or restaged. No inventory, prices,
contracts, payments, offers, appointments or public frontend were modified.

## Read-Only Reconciliation

Before deployment, authenticated manager UI showed124 apartments:38 reserved,
86 available and0 sold. There were32 reserved deal records,31 unique apartments.
One duplicate apartment and two available-inventory apartments explain why only
29 distinct reserved-inventory apartments matched those32 reserved records.

The inventory38 comprises29 matches,8 apartments without any linked deal and
one apartment with only a closed deal. Three reserved-inventory rows are flagged
for review because of duplicate or closed-stage links; two additional available
inventory rows have locking-stage deals. These are historical inconsistencies,
not automatically repaired customer data. Interest-only client records are not
evidence sufficient to assign a buyer or create a reservation.

Exact property/record reconciliation and screenshots stay under ignored
`outputs/2026-11-05-inventory-deal-audit/`; no customer identities in this note.

## Backup And Deployment

- Fresh79-table consistent InnoDB snapshot and CRM/material archive verified
  outside the web root. No restore drill and no database restore performed.
- Private source rollback bundle:
  `crm-reservation-inventory-b6153096fb154d3386d26085fa7955ff`.
- Full backup bundle:
  `crm-before-simplification-375e234e308a4cbe95af03df95bafeee`.
- Guarded atomic single-plugin release returned `DEPLOYED_VERIFIED`.
- Deployed CRM SHA256:
  `207cb4590043ce9d6869ffdd147c1b6d451763d8a762223d426aa788e6391a1b`.
- Eight public form/SEO contracts unchanged; all protected posts, CRM options,
  business metadata and unrelated plugin/MU-plugin sources unchanged.
- Zero customer submissions, quote generation or reservation tests on real data.

Three preflights stopped before live writes because the public-render checks
regenerated `_elementor_element_cache`. A bounded read-only fingerprint audit
identified that exact key as the only metadata drift. The release wrapper now
retains the full raw table hashes for audit, but excludes only that derived key
from equality checks, matching earlier project snapshot practice. All other
metadata remains strictly compared; no cache row was manually deleted. Seven
offline guard cases verify cache-only tolerance and rejection of business/source
changes. The final fresh preflight and release had zero protected-state changes.

## Verification

-148 existing synthetic CRM UI checks plus29 inventory-specific checks passed.
-494 existing methods unchanged; only the deal renderer and two new read-only
  inventory methods are allowed by source-scope checks.
-26 appointment-integrity and727 offer-antispam cases passed.
-Four existing offline deployment/rollback scenarios and seven new cache/guard
  cases passed.
-Native read-only WordPress comparison:197 client,320 inquiry and237 deal POST
  forms identical in each language. Exact38 property IDs and zero new POST forms.
-Native daily aggregation,98-client ownership filters and342 preserved reminders
  also passed. No native test writes or emails.
-Synthetic desktop1440x900 HU/ZH and390px narrow screenshots inspected. Full-width
  table,15px text; narrow table scrolls internally without page overflow.
-Live authenticated badge38, original59 deal cards; actual click opens38 unique
  inventory rows exactly matching the original inventory list. Missing8,
  reserved-review3, additional-conflict2. HU/ZH switching retains38 and no overflow.
-Live desktop DOM width2400,15px table text, table fills its existing content
  wrapper. A cropped live screenshot succeeded and is stored locally. A later
  duplicate capture timed out. Final viewport-reset/tab cleanup was rejected by
  the browser tool; do not claim that final reset succeeded. The earlier preview
  reset had succeeded. The isolated local preview server was stopped.

## Recovery And Limits

Use the ignored preflight manifest and private `crm-old.php` only after confirming
the live hash still matches this release. Restore source atomically using the
existing guarded transport and flush cache; never overwrite current database or
customer records from the full backup just to revert this UI change.

The8 missing links and historical status conflicts remain explicitly unresolved.
This release makes inventory-backed apartment totals consistent; it does not
certify contract/payment evidence or repair the previously documented concurrent
whole-option save risk. Quote and appointment paths remain outside this change.
