# Homepage Header and Readability Repair - Released

Historical v1.0.2 record. Later approved homepage balance and original hero
actions supersede its current-controller hashes and recovery entry point.
Use `2026-10-18-home-balance.md` for the latest v1.0.4 narrow recovery.

## Current v1.0.2

- User confirms the header is too tall, then reports tiny/unbalanced homepage text. Sol implements guarded homepage-only CSS in the existing controller; no native markup, JS, form, apartment, media, vendor or manifest edits. Parent reviews and deploys after a fresh backup. Header76px/logo60px, vertical container padding removed without shrinking menu hit area; inherited heading padding reset, scoped labels/meta enlarged, section spacing tightened. All overrides have homepage ownership selectors and fixed responsive font sizes, no global typography changes.
- Current controller LF SHA256 `b46c184678fe59eab2a01d1c6377abf1b76ceee7f32c299703148f4a62a3db03`.
- Current private backup `/home/harmath2/codex-backups/home-header-compact-63c26939981445cebe9c9d27a746cb40`. Intermediate v1.0.1 backup `/home/harmath2/codex-backups/home-header-flow-50a4ce996a7341f5ac1629585f4b7ba6`.
- Both releases pass private original/candidate backup, temporary/formal PHP8.3 lint, exact-ownership atomic controller replacement, cache purge, nine200 routes/no fatal,124 public-native inventory parity, unchanged native form/SEO/protected source-page-property-CRM snapshot and35 pure offer+17 anti-spam checks. Independent current `VERIFIED_HOME_HEADER_FLOW` PASS. No customer/lead/mail/CRM write or positive form submission.
- Offline51 controller assertions,7 compact composition/CSSOM tests,27 shared transport tests,20 presentation Node and71 presentation PHP pass. CSS media and vendor pins unchanged.
- Actual browser1280: native masthead75.996px, hero begins75.996px (no intersection), logo60px and menu53.75px/offer45.96px remain inside header; labels/card meta14px, h2 padding0, no root horizontal overflow. Native menu opens/closes and homepage offer opens exactly one native form with empty contacts/required unchecked privacy.
- Actual320 HU and Chinese: header75.996px/hero starts75.996px, root301px/scroll301px, menu and language/60px logo nonoverlapping. Native Chinese menu->contact appointment opens; selects A3/4/A3-4-L5, original accented Hungarian social-media source value retained, date2026-10-22/time09-12, form220px and requiredunchecked privacy, empty contacts. Closed without send. Actual390 root371px/no overflow, one263x376 Pannellum canvas initializes; real drag followed by rotation false->true and Project/Panorama tab switching work. Actual1023 root1005px/scroll1005px, same75.996px header/hero offset. No physical touch/Safari/fullscreen claim. Screenshot captures intermittently time out; those attempts are NOT visual proof. Desktop proof saved privately under `outputs/2026-10-18-home-family/live-compact-desktop.png`.

Current verification and narrow recovery (restore v1.0.1 flow, not old DB):

```powershell
python server-config/maintenance/2026-10-18-update-home-header-compact.py --verify /home/harmath2/codex-backups/home-header-compact-63c26939981445cebe9c9d27a746cb40 --expect b46c184678fe59eab2a01d1c6377abf1b76ceee7f32c299703148f4a62a3db03
python server-config/maintenance/2026-10-18-update-home-header-compact.py --rollback /home/harmath2/codex-backups/home-header-compact-63c26939981445cebe9c9d27a746cb40 --expect b46c184678fe59eab2a01d1c6377abf1b76ceee7f32c299703148f4a62a3db03
```

The original v1.0.1 updater below is historical. Its local exact-candidate guard
rejects current v1.0.2 sources; do not weaken it to force older recovery.

## Historical v1.0.1 Preparation

- Request: repair menu overlay of the new homepage video without changing native sales flows. Parent live diagnosis: native absolute masthead has no layout height at desktop and 1023px; mobile already reserves header height. Constant desktop padding is insufficient and would risk duplicated mobile spacing.
- Controller v1.0.1 adds exactly one inline style only after the existing guarded homepage transformation succeeds. Uses the established About/Contact/Project pattern: `body.home.page-id-164.opal-header-absolute #masthead` and its direct `div.site-header` use `position:relative!important;background:#fff`. No breakpoint, fixed padding, header markup, language, JS, form, media, vendor, database or asset manifest change.
- Original LF controller SHA256: `894a579f25882a94c5af3a49b47b4c416ee81461bc11dfbc58bda9709706c2b6` at Git baseline `283fd91`.
- Candidate LF controller SHA256: `6dd05836e76d6e0d666f272f55c6fd95df3af38090b30d207f502699e9e723f0`.
- Manifest remains `21e6ebec9bf0bfcbc273c42b5dc33f07ce96547fa04ccdd0b53faf780d3587b3`; owned asset bytes and pins remain unchanged.
- Offline checks: 45 PHP controller fixture assertions, 27 fake-transport update tests. No live browser/SSH/deployment/commit performed by this task. Actual desktop 1023/1280 and mobile390 checks remain the parent's responsibility, including native menu, language, video and unsent quote/appointment controls.

## Controller-Only Update

From the repository root, review default offline output first:

```powershell
python server-config/maintenance/2026-10-18-update-home-header-flow.py
python server-config/maintenance/2026-10-18-update-home-header-flow.py --deploy --expect 6dd05836e76d6e0d666f272f55c6fd95df3af38090b30d207f502699e9e723f0
```

The explicit deployment creates a fresh private `home-header-flow-<32hex>` backup, saves exact original/candidate bytes and public baseline, records protected snapshots and all unchanged homepage assets, and runs native35offer+17anti-spam pure checks before activation. Only one same-directory controller stage is written in production; staged PHP lint, metadata preservation and exact original ownership rechecks precede atomic POSIX replacement. Formal lint/cache purge/9 public routes/public124 inventory/native form and SEO/source checks/protected snapshots follow. No submission, mail or database restore. It shares the original homepage deployment lock. This lock does not coordinate unrelated external editors; stop other maintenance during activation.

Use the exact backup path printed by that deployment (not a historical whole-home backup):

```powershell
python server-config/maintenance/2026-10-18-update-home-header-flow.py --verify BACKUP --expect 6dd05836e76d6e0d666f272f55c6fd95df3af38090b30d207f502699e9e723f0
python server-config/maintenance/2026-10-18-update-home-header-flow.py --rollback BACKUP --expect 6dd05836e76d6e0d666f272f55c6fd95df3af38090b30d207f502699e9e723f0
```

Rollback validates private saved original/candidate/asset evidence and exact currently owned candidate, restores only the original controller, lints/purges/rechecks, and never rewinds customers or business data. Foreign edits are retained with an error. A subsequent real business-state change can make an old protected-state comparison fail; it is reported, never restored.

After deployment the older whole-home verifier/rollback expects a different bundle/controller and must not be used directly for this latest repair. This updater is the current narrow verification/recovery entry point; a full-home removal would require a separately reviewed original release recovery.
