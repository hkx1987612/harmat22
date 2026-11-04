# CRM Review And Desktop Simplification

## Scope And Status

User requests a data-first backup, desktop-first simplification, daily traffic
instead of stale overdue tasks, a direct visitor-overview link, and clearer
Chinese/Hungarian presentation. No public website, quote/PDF, booking, customer
record, ad, email, or automation changes are authorized by this release.

Released and server-verified on2026-11-04. CRM1.6.121 and bandwidth widget1.1.1.

## First Release

- Five primary entries: overview, inquiries, sales deals, client files, inventory.
- Keep tasks, payments, commission, legal, activity, trash and account tools in
  More. Do not delete records or disable scheduled reminders.
- Overview: four traffic metrics, latest-first seven-day visitor table, five
  recent permission-filtered inquiries with direct record anchors.
- Visitor overview now opens its own visible view in one click. Sales reports
  and manager-only server bandwidth remain optional details on that view.
- Client creation is collapsed by default; editing or a validation error opens
  the existing native form. All existing form fields and actions are retained.
- Compact shared heading/navigation, neutral background, consistent readable
  headings and spacing. The public site does not load this private portal CSS.
- Fixed More dropdown positioning at narrow widths; desktop remains priority.

## Evidence And Keep/Reduce Decisions

The live client-list heading was approximately 1296px down the old page; the
synthetic new desktop page places it around 401px including its preview banner.
These measurements use different viewport widths, so they are illustrative,
not a controlled performance comparison.

Read-only aggregate activity review: retained log is capped at 500 rows. Recent
30-day actions include 36 inquiry assignments, 22 client creations, two client
assignments and one client update, besides login/logout and two inquiry deletes.
This supports prioritizing inquiry assignment and customer lookup. The log
does not measure read-only views; lack of an action does not prove non-use.
No identities, contact details or raw log rows are copied into this document.

| Module | Decision | Reason |
| --- | --- | --- |
| Inquiries / clients / inventory / deals | Keep prominent | Core sales workflow and real activity |
| Quotes / appointments / permissions | Preserve exactly | Core business and access boundary |
| Overdue tasks on overview | Remove from overview | Stale synthesized reminders obscure current work |
| Duplicate funnel and repeated overview lists | Remove from overview | Repetition and unnecessary rendering |
| Server bandwidth | Move to manager-only optional detail | Technical operation, not sales performance |
| Payments / commissions / legal / audit / trash | Keep under More | Low frequency does not imply dispensable |

## Next Safe Iterations

1. Improve client search and quick filtering without changing IDs, ownership,
   customer records, quote generation or booking storage. Clarify the distinction
   between prospect files and post-sale customer accounts in labels.
2. Audit task rules separately. Distinguish genuinely dated tasks from automatically
   inferred missing follow-ups before disabling any reminders or deleting history.
3. Clarify analytics definitions. Traffic is consented-cookie coverage, while
   inquiry counts have different coverage; it is not a verified ad conversion rate.
4. Consolidate duplicate read-only displays only after field-by-field mapping.
   Do not merge prospect/deal/customer storage during a layout release.

## Engineering Risks For A Separate Batch

- `get_leads/save_leads` and `get_deals/save_deals` store whole option arrays;
  concurrent read-modify-write can overwrite another update. The legal plugin
  also writes shared deal state. Plan a shared write contract and concurrency
  tests before storage migration. No observed data-loss incident is claimed.
- Forced-password-change enforcement appears in portal rendering rather than
  every action entry point. Audit all POST/download handlers separately before
  tightening access; no unauthorized-access incident is established.
- Account provisioning currently carries initial credentials in mail/transient
  workflows. A one-use setup-link design deserves a separate tested migration.
- Duplicate matching and private-document download protections need dedicated
  acceptance tests. Do not delete or merge customers based on heuristic matches.

These are review findings, not changes included in this UI release.

## Backup And Verification

- Full database: 79 InnoDB tables, consistent single-transaction export; no locks.
- Private remote backup outside public web root, directory0700/files0600. SQL
  CREATE TABLE count and SHA256 verified; asset archive gzip/list verified.
- CRM programs, legal module and existing private-material directories included.
- Raw backup/customer data never copied to Git. No restore drill was performed.
- Backup manifest and read-only aggregate/native proofs are in ignored local
  `outputs/2026-11-04-crm-review/`, not the public web root.
- 475 existing CRM methods remain byte-identical; changed methods are an explicit
  presentation-only allowlist. Existing data writes/auth/quote methods unchanged.
- Synthetic tests: 94 UI checks across manager/staff and Chinese/Hungarian;
  scope isolation, links, empty state, editor open/closed/error states.
- Native server preflight: each language compares 195 client forms and 321 inquiry
  forms against the original class, byte-identical in memory; no data export.
  Native candidate overview/traffic rendering passes in a read-only transaction.
- Existing isolated appointment-integrity 26 cases and antispam 727 checks pass.
- Preview browser checks: 1440px and1280px desktop;390px and320px narrow screen;
  one-click traffic, anchored inquiry, client form disclosure, language switching.
- Four offline deployment tests cover success, concurrent-data refusal, public
  verification failure and failure after an atomic rename; source-only recovery.

## Final Release Evidence

- Full-data backup: `crm-before-simplification-e91fa92f448d4d5b85ce676b4fc7e0b5`
  under `/home/harmath2/codex-backups/`.
- Final source preflight: `crm-ui-review-4f038d60834d4583abd1bf2b04f776b2`
  under the same private directory. Its manifest pins old/new files and metadata.
- First activation attempt stopped at staging because JSON metadata was a list
  while the existing transport validates tuples. No formal file was replaced.
  Normalized metadata type without weakening permissions; added offline tests.
- Final `DEPLOYED_VERIFIED`: eight public pages retain native form/SEO contracts;
  protected CRM options, all posts/postmeta and unrelated protected source hashes
  unchanged. Zero customer submissions, no database restore and no mail test.
- Raw live CRM SHA256:
  `a450cdb7c0b5b1b0b71a7e4316094aa62f3bc9dc3d3950193ab4efe41e429d72`.
- Raw live bandwidth-widget SHA256:
  `a7125f0c048f5bc92518d965fe09850f5f418682cb65863df0e3eaed6f06b3d7`.
- Actual authenticated browser: overview shows latest-first dates and no old
  overdue/bandwidth card; traffic link opens visible overview in one click;
  client list starts around373px at1800CSS width; editor opens with its original
  name/nonce fields; Hungarian and Chinese switching work. Restored Chinese and
  default viewport; original user client tab was not reloaded or edited.
- Live Chrome screenshot capture timed out, so final screenshot is explicitly a
  synthetic-data preview. Actual layout dimensions and interactions above came
  from live DOM checks. Local desktop and narrow-screen screenshots were viewed.
- During final cleanup the user navigated the new live tab to deals; preserved
  that state rather than navigating over the user's activity. Default viewport
  reset succeeded. Browser zoom was never changed.

## Release Commands And Recovery

`2026-11-04-deploy-crm-simplification.py` is offline by default. `--preflight`
requires the existing full-data backup, verifies its checksums, creates fresh
private old/new source copies, checks live-vs-Git drift, PHP8.3 syntax, native
forms and protected source/data hashes. `--deploy` requires unchanged reviewed
bytes and protected state; stages and atomically replaces only the two owned
PHP files. Automatic failure recovery restores only those source files.

Never restore the database for a UI regression: new inquiries may have arrived.
Use the exact old source files and metadata from the private preflight manifest;
refuse rollback over third-party changes. Keep the full database backup private.
Do not claim physical-device, complete business end-to-end or restore validation
from synthetic/read-only checks. No customer submission or email test is included.
