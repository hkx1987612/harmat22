# Sales Area Alignment - 2026-10-09

## Authority And Scope

The user explicitly chose the current public website display as authoritative
after review identified three differences between the property hero and quote
data. Fresh Chrome desktop/mobile checks confirmed these values before repair:

| Property ID | Apartment | Old Override | Approved Website Area |
| --- | --- | --- | --- |
| 4349 | A1-2-L4 | 55.82 | 55.81 m2 |
| 4388 | A1-4-L1 | 67.04 | 67.05 m2 |
| 4418 | A1-4-L4 | 59.49 | 59.48 m2 |

The same old values also appeared in the visible room-list total. The bounded
repair changes only each target's sales override, the exact sales-total line
in post_content, and the exact area-total-size span in Elementor HTML widget
4520a1d. Room dimensions, indoor/outdoor area, PDF/media, total apartment price,
availability, post dates and every other property field remain unchanged.
The existing imported _harmat_sales_unit_price field is preserved; current
display unit prices are derived from the unchanged price and corrected area.

New quote selection, CRM inventory, apartment search, app inventory, the
assistant and Apartment schema read the shared override. Existing saved inquiry
snapshots are not rewritten. This is not a legal survey or a new rounding rule;
the preserved hero still uses its existing calculation. No global source rewrite
or permanently loaded correction hook is introduced.

## Guarded Repair

- Reviewed Git baseline: eb3fe3ac483bd597767cedf06cf87cb0127bd32a.
- The tracked PHP is WP-CLI-only and defaults to a read-only dry run.
- Exact ID/title/slug/published-state, pinned original content/Elementor hashes,
  single-row/widget/span guards and uniform old/new state prevent stale writes.
- InnoDB transaction and compare-and-swap SQL update exactly nine field values.
  Full post rows and metadata for all 124 properties are checked before commit.
- Private 0700 backup directory contains hash-verified 0600 target snapshots,
  before/after property hash maps, the repair/test code and release manifest.
- Direct SQL deliberately avoids save/meta hooks, email, IndexNow queueing and
  cron dispatch. After verification and commit, only the three targets' derived
  Elementor element caches plus relevant post/search/page/object caches are
  invalidated. No global Elementor cache operation is used.
- PHP is staged as a hidden temporary file, linted, atomically renamed and
  linted again. No production PHP/plugin file is replaced.

## Recovery On Either Computer

Pull GitHub before starting. The private backup named in the completed release
record contains all recovery material. The maintained command is:

```powershell
python server-config/maintenance/2026-10-09-deploy-sales-area.py --verify EXACT_PRIVATE_BACKUP
python server-config/maintenance/2026-10-09-deploy-sales-area.py --rollback EXACT_PRIVATE_BACKUP
```

Rollback verifies the backup and current repaired values before restoring only
the nine owned fields. Later target edits block rollback; unrelated records are
never restored wholesale. Do not repeat --deploy after a later data/source edit.

## Verification

Successful private backup:
/home/harmath2/codex-backups/sales-area-align-97da12418afb4b2fadbf6aeeb13fbf41.
Repair LF SHA-256:
02a62843b93cb742a9a0073a145ffda32e1003f36f1b18b0dff7862ef7e6a5fc.
Test LF SHA-256:
1860343e2b8f2003400a5ceaaf2a5f4eda68d557b337973653b3cc87c9f24d27.

- Local and production PHP tests passed 245 assertions; local deploy-safety tests
  passed 34 isolated cases without network/database access.
- Staged/final PHP lint, source parity, private snapshot hashes/modes, exact
  nine-field transaction, full 124-property audit, scoped cache purge, shared
  sales/SEO/assistant values, 12-route HTTP smoke and zero-write repeat passed.
- Six targeted Chrome property cases plus desktop/mobile search passed. Hero
  values, total apartment price, room rows, images, metadata/quote controls and
  layout are retained; room total, quote area, card area and schema floorSize now
  agree. No contact details or test inquiry were submitted.
- The first attempt automatically restored its nine owned fields because an
  overly broad whole-cron-hash guard changed. Read-only diagnosis reproduced only
  Action Scheduler's normal minute-runner timestamp refresh; reminder/IndexNow
  options were unchanged. The successful guard preserves every cron event's
  identity, arguments, schedule and interval, allowing only this runner's forward
  timestamp movement (at most two hours). First-attempt evidence/backup is retained
  separately; it is not the successful release backup.
- Final read-only verification also accepts ordinary non-mail/non-Harmat
  recurring housekeeping on its exact cadence and the existing one-off cache-GC
  timer, with unchanged identity/arguments/schedule and bounded forward movement.
  Mail/reminder/IndexNow/custom Harmat events stay protected. It never dispatches
  a job. Initial read-only post-QA checks exposed these expected timer movements
  and are retained as failed overstrict checks, not claimed as passes.
- Browsing regenerated two unrelated Elementor expiry timestamps (4343, 5144).
  Local reconstruction matched each complete original metadata SHA-256 by
  replacing only its timeout number; all other bytes/rows were exact. Final
  auditing uses those two explicitly proved original timeout values, not a broad
  cache/metadata exemption. All 124 post rows and other metadata passed.
- First post-release QA started during recovery was stopped; its observed old
  area values are recovery-state evidence, not successful-release QA. The final
  eight-case report is after-complete.json. An initial baseline label assertion
  was also repaired before any mutation; original evidence remains preserved.

- Final key-route Chrome regression passed 18/18 cases. Geometry, forms, cookie
  controls, canonical/robots/H1 and images matched the prior stable release;
  only the approved area/derived-unit-price text and benign preload timing differ.
- Full low-concurrency scan passed 145 pages, 124 properties and 610 observed
  assets, all four issue counts zero, with the same page URL set. Final guarded
  verification exited 0; all 69 saved-quote snapshots/count hashes, ten critical
  source hashes and protected options remained unchanged; root error_log absent.
- All deployment/QA/scan/audit processes finished. Stable tag:
  stable-2026-10-09-sales-area-alignment-current. Real Safari/iOS, China-network
  performance and production inquiry/mail delivery were not tested.

Raw screenshots, public fixture exports and operation reports remain ignored
under outputs/2026-10-09-sales-area-align; private quote bodies are not exported.
