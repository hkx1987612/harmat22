# Apartment Search Total Budget And Journal-Link Removal

## Scope

- User approves a total apartment purchase-price filter and removal of the
  construction-journal link from the search-page hero only. Shared navigation,
  journal content, inventory/status defaults, images and existing sqm filter stay.
- Search plugin 1.2.5, markup cache v15: optional lower/upper numeric limits in
  millions of HUF; inclusive public total prices; unknown/hidden prices excluded
  only while a budget limit is active. Existing reset clears the new controls.
- New labels and reversed-bound warning use the native HU/ZH marker. Only the
  new protected fieldset is locally translated; shared dictionary/runtime stays.
- The existing smart-search matcher also applies the two limits; otherwise its
  delayed second pass would undo the new filter. All other guard functions stay
  byte-identical, including quote, appointment, assistant and security handlers.
- No quotation/PDF/appointment/CRM logic or business data edits, no lead sends,
  no payments, advertising, photo/video replacements or security changes.

## Verification

- `2026-10-29-test-search-budget.cjs`: 50 assertions, native PHP-rendered fixture
  plus unchanged smart-search and progressive-card runtimes. Covers inclusive
  boundaries, decimal/zero/one-sided limits, intersections, sorting, reset,
  hidden-price protection, legacy flexible text search and load-more behavior.
- `2026-10-29-test-budget-transport.py`: five offline staging/metadata/ownership,
  scope and mixed-line-ending preservation tests.
- `2026-10-30-test-budget-language.cjs`: retained real dictionary/pattern/runtime
  integration in a bounded worker. Eight language changes retain budgets and
  result counts; observer activity settles. Before the fix this test timed out.
- Native temporary and formal PHP lint uses hosting PHP 8.3. Read-only native
  offer/antispam fixtures run 35/17 checks without dispatch, submission or mail.
- Public contract compares 124 card facts, images, statuses, prices and action
  links; eight routes preserve native form/SEO/header/footer and unrelated main
  content. Raw render vs optimized HTML comparison explicitly normalizes only
  whitespace and lazy-image wrappers while retaining every semantic field.

## Initial Release And Recovery

- First private preflight rejected raw-vs-optimized card serialization; no
  production activation. Corrected to UTF-8 semantic card comparison.
- First activation attempt failed staging metadata equality because JSON turned
  a tuple into a list; stopped before rename. Caller now restores the tuple and
  an offline regression verifies atomic lint/metadata/ownership ordering.
- Initial release backup `search-budget-291fb201d6f04398b789b4f28f65c9e8`
  passed source/server/public checks and HU interactions, but actual Chinese
  switching exposed an observer feedback loop in the NEW label synchronizer.
  Both owned sources were restored to their exact originals and cache purged.
  Independent remote hash check confirmed both originals restored.
- Its old-baseline rollback page check crossed midnight: the only form-attribute
  difference was native date-input min 2026-10-29 -> 2026-10-30. All 127 form
  entries, SEO and canonical otherwise agree. No date logic was changed.
- Fix: new budget labels update only when language actually changes, avoiding
  an echo of the retained translator's mutation notifications. No shared
  translation code was edited. Actual desktop and 320px local tests with the
  real translator now stay responsive; test/browser screenshots retained in
  ignored outputs. This was not a physical-phone test.

## Current Status

Corrected release is live and separately VERIFIED on October30 Budapest time.
Backup: `search-budget-2f667cfb3fc04e8b8a0d200d7068d245`.
Raw search SHA256 `3851e81950e45d2b291989768bb2dd4b189200f085d0cac8c35285fa9c4a920b`;
raw performance guard SHA256 `fac8193d57daa110b089c531c50f851af99eed71b95582a5adf83a28279150f4`.
Local normalized source pins respectively
`fd956019c12c56afd22256a40aefdb3bee9f4add13462375291be0d33c558b0c` and
`43bdc89706d480aa00250aedb19b58da8318e80c460e8cd152e5a2b577231df1`.

- Fresh backup, staged PHP8.3/native124-card parity and 35/17 pure checks passed
  before activation. Atomic smart-matcher first, search controls second, cache
  purge; RELEASED and independent VERIFIED each pass eight route contracts and
  before/after protected source/business-state equality.
- Actual native Chrome CSS widths1280/320/390 checked, HU/ZH repeated switches
  responsive, no measured horizontal overflow. Site retains user's80% zoom;
  explicit viewport/capture dimensions were compensated and innerWidth checked,
  rather than incorrectly calling a400px CSS viewport320px. Zoom not changed.
- Actual budget50-80M:60 matches, exact public-data comparison, no wrong/missing
  matches. A1/two-room intersection13 sorted prices; budget60-70M20 matches;
  max50M2 matches; max0/no-results and reset124 verified. More24->48 retains
  filtered total. Native source remains full124/defaultALL; no status changes.
-320ZH native A1-1-L6 offer/appointment opens in277x722 dialog within320x740.
  All three contact fields blank, privacy required/unchecked, native date min
  October30, original three time slots/five HU lead-source values retained.
  Escape closes without submission. A resize/delayed-sort coordinate action
  initially followed neighboring A2 detail; returned to search, native keyboard
  activation of the verified focused A1 CTA opens exact A1-L6 without navigation.
  Tool focus-preparation errors were not bypassed with JS or forced clicks.
- Valid final full-page-derived desktop/mobile screenshots saved ignored with
  capture coordinates compensated for existing zoom. Earlier stale viewport or
  incorrectly scaled captures are not final visual proof. Existing extension
  overlays retained. Not a physical-phone/TV, mail-delivery or full CWV claim.
- HU/default viewport restored, filters reset124 and native modal closed before
  handoff. Opaque `Object` entries and extension connection/Lockdown errors were
  observed in the browser console; no clean-console certification is made.

## Recovery

Use the exact private backup directory recorded by the successful corrected
release with `2026-10-29-deploy-search-budget.py --rollback BACKUP`. It restores
only this release's two owned source files after hash/metadata checks and purges
presentation caches. Never restore a database snapshot or unrelated files.
Backups, HTML containing nonces, screenshots and local audit JSON are private,
gitignored outputs, not GitHub material.
