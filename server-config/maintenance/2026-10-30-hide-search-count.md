# Hide Search Result Count

User requests no prominent result-count reminder (the numeric badge and adjacent
result label). Hide only `.hm-lakas-resultbar` on the apartment-search page.
Keep its internal counter for existing filter scripts; no counter announcements
are added. Cards, empty state, budget/sqm/status/query filters, price/area/status
data, progressive load-more and all quotation/appointment logic remain intact.

## Change

- Search plugin1.2.6/cachev16, hidden/aria-hidden on the count row and one scoped
  display-none rule. Exact source-delta guard refuses other changes. Shared
  performance/translation code is not modified.
- Reuse existing bounded source-only transport; its activation/recovery loops
  now iterate the explicit target map, permitting this one-file release without
  rewriting the unchanged smart-search guard.
-53 DOM/visibility/filter/progression assertions, real translator eight-switch
  test and five transport tests pass. Native PHP8.3 lint/124-card parity and
 35offer17spam read-only checks pass before activation, no send/mail/REST dispatch.

## Release

Private backup `search-count-hidden-b07612d3f5e241c2afed770a22e18256`.
Original raw search SHA256
`3851e81950e45d2b291989768bb2dd4b189200f085d0cac8c35285fa9c4a920b`.
Candidate raw search SHA256
`d069717cbbcec7ba6020958d22ef63da2c3538f63f69f7e881f53c959a184c2c`;
normalized `9878528e4e16fe9a57bff025d8a3c5208aef233a01c341e47e62f655aaeab742`.
RELEASED and separately VERIFIED: eight public routes preserve native form/SEO/
shell and every unrelated main, all124 card facts, protected sources/business
state and35offer17spam pure checks. Only the result-row hidden attributes differ
in the search main; no database restore or customer submission.

Actual Chrome1280desktop/320CSSmobile checks: row computed display none/height0,
no horizontal overflow, HU/ZH switching retains hidden state. Budget50-80M with
current available status gives47 matches, exactly matching public card prices.
Native header quote/appointment opens/closes with all contacts blank, privacy
unchecked, native October30 date minimum and original time options; no send.
Original user selection (current91, blank budgets, Chinese) restored; internal
91 remains only for filter logic, not the hidden badge/label. Existing load-more
is retained. Browser viewport reset, existing80%zoom unchanged. This is responsive
browser testing, not a physical-phone or inbox-delivery certificate.

Final screenshot is ignored `outputs/2026-10-30-hide-search-count/mobile-count-hidden.jpg`.
Existing cookie/assistant/extension overlays remain; no clean-console or unrelated
redesign claim. Normal shutdown is requested only after tests and source sync;
the final handoff may schedule a non-forced shutdown. This one-off approval must
not be replayed by future turns or automations.

## Recovery And Privacy

Use `2026-10-30-hide-search-count.py --rollback EXACT_BACKUP` while its deployed
hash still owns the file. Restore only the backed-up source, not database data.
Private raw HTML, backups, snapshots and screenshots remain gitignored outputs.
User additionally requests normal shutdown only after successful regression;
never force-close unsaved applications or shut down with unresolved site issues.
