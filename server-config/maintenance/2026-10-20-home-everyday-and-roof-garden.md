# Equal Lifestyle Images And Roof Garden

## Scope And Source Chain

User approves equal left/right lifestyle image dimensions and top alignment,
keeping courtyard/interior content. While that release was being checked, user
also requests replacing the homepage building-model photo with the sky/roof
garden. Both implemented separately with separate private backups. No quote,
appointment, customer, property, contact, payment, security or advertising change.

1. Home controller 1.0.16 -> 1.0.17, commit d5975d8. SHA
   `65f29b83d86f96ac4a121049a90c7c67ffdeca07bb05d0dcac6542aebfc98651`.
   Only version and two scoped CSS rules added. At >=621px equal columns and
   right padding offset removed; shared subgrid rows align captions/headings/body
   across wrapping titles. Unsupported subgrid retains simple equal columns;
   <=620px original one-column block layout remains unchanged. No image/source/
   script/text change in this step.
2. Current controller 1.0.18 SHA
   `dafbf493b166909ded9f52dd01b7015f2edffaa6aa6aa6f9e8dd3fa3af21bb70`.
   Only version and exact single model-figure substitution added. Existing
   gallery asset `/wp-content/uploads/2026/02/Harmat22_latvany-8-harmat-1280.webp`
   inspected directly: 1280x720 WebP, 271,163 bytes, SHA
   `5b6578a003743053822c80df755a074977425f7803b3f468e8fd9ab42819c516`.
   Shows the actual project's planned planted roof terrace; not a generated/new
   image or another development. HU alt identifies planned roof garden; existing
   rendering key gives Chinese rendered-image alt and HU/ZH rendering caption.
   Old real-site/model caption removed only here. Existing responsive framing,
   lazy/async loading and virtual-selector button destination retained.

All ten manifest-pinned original assets/body-six, runtime, fullscreen, assistant,
footer and native sales shell retained. No image upload or new frontend service.
The reusable contact-image transport now exposes the same formerly literal
MIME/size/hash pins as configuration; defaults unchanged. Older adapters recognize
only these exact later controller hashes to load their frozen prior Git candidate
for chained recovery. Unknown local/live sources remain refused.

## Backups And Server Verification

- Alignment backup:
  `/home/harmath2/codex-backups/home-everyday-align-c31c94cdb59541a9b8f8fea11453bec3`.
  Aggregate `dab9c05e2c4d85f539aefa104ae1123f1ae7a9a2758b61463e9106d90cb9f8ef`.
  RELEASED with three scoped health routes and separately VERIFIED with seven.
  Rendered main HTML unchanged; exact owned style differs only by two rules.
- Roof-garden backup:
  `/home/harmath2/codex-backups/home-roof-garden-b6eda971e30544bf8a67168c1bc860ac`.
  Aggregate `04b81d2c71085dbbdafb5b245e349dad7e2d54beafa94e9fb723685357e65e84`.
  RELEASED and independently VERIFIED with seven routes; exact rendered main
  differs only by the selected figure, native form/SEO unchanged. Staged PHP lint,
  76 synthetic controller checks, 35 offer and 17 anti-spam pure checks pass for
  each release. Protected source/page/business snapshots stable around each check.
- Seven routes: homepage, search, contact, sample property A1-F-L1, selector
  overview, first phase and A1 building. No full-site scan or form dispatch/mail.
- Local tests: alignment seven cases, roof six cases, retained contact eight
  cases and controller 76 checks. Exact source/version gates and original mixed
  footer EOL hash retained; no weakening of protection or data restoration.

## Actual Browser Checks

- 1280px HU lifestyle images both568.5x379, identical y coordinates; headings and
  descriptions also align. At768px both336.5x224.33 and two-line heading/shared
  paragraph starts align. Chinese1280 also identical image/title/body coordinates.
- 390px one335px column, stories block/zero top padding. At320px both lifestyle
  images273x182, stories block, loaded and no page overflow. HU/ZH roundtrip works.
  Temporary gallery/source tabs were closed before final mobile checks: the
  viewport override had initially affected that selected tab rather than the
  homepage; claims use actual measured homepage widths only.
- Roof desktop1280 image loaded at native1280x720; original virtual selector
  destination and rendering caption intact. Actual390 Chinese image335x223.33,
  correctly marked rendered image, no overflow;320 page also no overflow.
- 320 Chinese native quote/appointment modal opens/closes with empty contacts,
  original five HU source values, required privacy unchecked. Date2026-10-23
  selected (min2026-10-20), time12:00-15:00 selected. An attempted read-only
  checkValidity() call was unsupported by the browser DOM mirror; recorded actual
  values/min instead, not a native-validity or delivery assertion. Nothing sent.
- HU/default viewport restored. Private screenshots under ignored outputs:
  `2026-10-20-everyday-align/desktop-final.jpg`,
  `2026-10-20-roof-garden/desktop-final.jpg` and `mobile-zh-final.jpg`.
  Not physical-device/TV/Safari or mail-delivery certification.

## Source-Only Rollback Order

```powershell
python server-config/maintenance/2026-10-20-update-home-roof-garden.py --rollback /home/harmath2/codex-backups/home-roof-garden-b6eda971e30544bf8a67168c1bc860ac --expect 04b81d2c71085dbbdafb5b245e349dad7e2d54beafa94e9fb723685357e65e84
python server-config/maintenance/2026-10-20-align-home-everyday.py --rollback /home/harmath2/codex-backups/home-everyday-align-c31c94cdb59541a9b8f8fea11453bec3 --expect dab9c05e2c4d85f539aefa104ae1123f1ae7a9a2758b61463e9106d90cb9f8ef
```

These restore only exact owned controller1.0.18->1.0.17->1.0.16. Roll contact
image back next only if requested, using its own saved backup. Older historical
footer/six-card transports must run from matching frozen Git revision after the
required controller recovery. Never restore business data to match historic
snapshots; normal later CRM activity may require current-state read-only review.
Current stable tag after sync: stable-2026-10-20-home-roof-garden-current.
