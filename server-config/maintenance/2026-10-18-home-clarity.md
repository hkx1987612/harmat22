# Homepage Clarity And Phone Header Fit

## Scope

- User requests right hero action to enter apartment search, video below the
  native menu, and clearer homepage typography. Keep virtual selector, native
  quotation/appointment logic, data, media and all other pages unchanged.
- Sol implements the bounded controller revisions; parent reviews, deploys and
  checks actual Chrome UI. No new form submission or customer notification.
- Right hero action uses `/lakaskereso/`, Hungarian `Lakaskereso` with correct
  accents in public PHP, and existing `navSearch` Chinese translation. Existing
  outlined class retained. First virtual-selector action is unchanged.
- Owned muted/body colors become #47574e/#35463c. Minimum contrast against the
  approved light bands is 6.61/8.66 respectively. This is not certification of
  all video frames or full-site accessibility. Headings use600 weight; hero
 38/32/30px and actions16px, without viewport-scaled text.

## Revisions

- v1.0.5 LF SHA
  `4ffbebca0b2d93a23b46368f9c0515f2e6b626ec758e91d9fd087dd4ad84d2d4`.
  Private backup:
  `/home/harmath2/codex-backups/home-clarity-a766f046721c4b59a70c1c1b8463a0e3`.
  Temp/formalPHP8.3, atomic replacement, cache purge, nine routes200,
  native form/SEO/protected state,124 native-public inventory and35 pure offer
  plus17 anti-spam checks PASS. No dispatch/submission/mail.
- Actual320px revealed inherited paddings not represented by CSSOM arithmetic:
  outer header row15px, icon widget20px and quote widget66px. These squeezed
  the native quote action to34px and caused menu/language overlap even though
  video correctly began at76px. This was not accepted as completed phone QA.
- Current v1.0.6 LF SHA
  `fec60e010844ed75673baf79d3f49f7c31eebe6edcd583c78d638712aaa020fd`.
  Only adds three homepage-phone padding resets and Hungarian language flex1.4
  to the prior candidate. Native sticky/hide behavior and language JS untouched.
  Header grid remains one row; native quote action is retained, not hidden.
  Private backup:
  `/home/harmath2/codex-backups/home-header-fit-2c8c0797baab43b3a10a2aba4598e7fd`.
- Current narrow adapter: `2026-10-18-update-home-header-fit.py`. It reconstructs
  the pinned v1.0.5 source through the unchanged clarity adapter and requires
  exact old SHA before backup/staging/replacement. Previous clarity updater
  intentionally refuses the newer local controller.

## Offline Evidence

- Initial revision:82 Python tests,27 Node tests,147 PHP assertions and lint.
- Fit refinement:8 Python tests,9 historical clarity,7 historical actions,
  8 Node tests,76 controller PHP assertions and lint PASS.
- Parent independently reran27 shared safe-update,24 original transport,
  20 presentation Node,9 clarity Python,4 clarity Node,8 fit Python,
  76 controller PHP and lint. Historical fixtures do not weaken production pins.
- No body.php/CSS/JS/vendor/manifest/native handler/backend edits. Source change
  is only the independently owned homepage controller; helpers/tests are local
  maintenance tooling. Original homepage164 database source is unchanged.

## Live Evidence

- v1.0.5 actual1280 desktop: hero document top75.996px, title38px/600,
  section headings36px/600, new body color active, no measured root overflow.
- New hero action really navigates to apartment search with124 results and
  normal filters. Chinese labels become virtual selection and apartment search
  with original URLs retained. Native desktop Chinese quote dialog opens and
  closes without entering contacts or sending.
- Final v1.0.6 temp/formalPHP8.3 lint, owned atomic replacement, cache purge,
  nine routes200,124 inventory parity, unchanged native form/SEO/protected state,
  35 pure offer+17 anti-spam checks PASS. Separate independent
  `VERIFIED_HOME_HEADER_FLOW` PASS. No business data restoration or submission.
- Actual320HU/Chinese: client/scroll301px, no measured owned text overflow;
  header bottom and hero top75.996px at scroll0. Menu x12..54, language60..144,
  logo149..185, native quote189..289px/100px wide, all within the76px row with
  no mutual rectangle overlap. Title30px/600, actions16px and46.48px high.
- Actual390 Chinese after fresh reload: client/scroll371px, no measured owned
  text overflow; hero top75.996px, title32px/600, actions320px wide. Native menu
  opens/closes with translated navigation. Logo really returns to home, not
  the neighboring quotation action. Language remains present beside menu.
- Actual320 Chinese quote: one220px native form; A1/1/A1-1-L2 selections,
  Google original Hungarian source value, Oct22/date and12-15 time verified.
  Contacts blank, required privacy unchecked. Closed without send. Native
  form fields and server-side snapshots remain unchanged.
- Actual767HU fresh reload: client/scroll748px, no measured owned text overflow,
  native controls remain in one76px row and hero begins below. Actual2400 final:
  client/scroll2381px, HU restored, native modal hidden, normal viewport reset.
  Resizing can leave native sticky inline width stale until a fresh page load;
  fresh-size checks are distinguished from resize-transition observations.
- Actual3D Project/Panorama tabs switch and one930x416 canvas initializes;
  original1920px hero video decodes. No new fullscreen, physical-touch, Safari
  or all-device certification. Assets/media/runtime remain byte-identical.
- Some viewport screenshot attempts timed out or produced repeated tiles;
  those are not counted as visual proof. Private screenshots stay in ignored
  outputs and are never committed. A calibrated browser clip produced a clean
  single-view final proof at `outputs/2026-10-18-home-family/clarity-final-desktop.png`.

## Current Verification And Source-Only Recovery

```powershell
python server-config/maintenance/2026-10-18-update-home-header-fit.py --verify /home/harmath2/codex-backups/home-header-fit-2c8c0797baab43b3a10a2aba4598e7fd --expect fec60e010844ed75673baf79d3f49f7c31eebe6edcd583c78d638712aaa020fd
python server-config/maintenance/2026-10-18-update-home-header-fit.py --rollback /home/harmath2/codex-backups/home-header-fit-2c8c0797baab43b3a10a2aba4598e7fd --expect fec60e010844ed75673baf79d3f49f7c31eebe6edcd583c78d638712aaa020fd
```

The rollback restores only the exact prior v1.0.5 controller, including its known
phone-padding issue. Full return to v1.0.4 requires the separate earlier clarity
backup and reviewed intermediate source; do not bypass an older updater's local
or remote ownership pin. Never restore the business database.
