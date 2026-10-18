# Homepage Type, Spacing and Background Balance

## Scope

User requests coordinated desktop/mobile homepage typography and spacing, then
explicitly approves soft warm-gray/green-gray bands with white apartment cards.
Sol implements scoped presentation CSS; parent reviews, deploys and performs
real-browser regression. Quote and appointment must remain untouched.

Baseline is main `8a14c01`, controller v1.0.2 LF SHA256
`b46c184678fe59eab2a01d1c6377abf1b76ceee7f32c299703148f4a62a3db03`.
Native header76px/logo60px and all public copy, data, media, JS, asset manifest,
SEO, native menu/language/forms and backend owners are preserved.

## Findings and Presentation Rules

- Existing desktop section headings mix32/38px, tablet29/32px and mobile28/29/30px.
  Standard section titles become36/32/28px at fixed responsive breakpoints;
  prose16px with1.75 line height, small labels14px, tool/card titles retain their
  own compact hierarchy. Hero wording and type remain unchanged.
- Actual767px tablet has three223px apartment cards with small prices. Use two
  cards across621-1000px, three on desktop and the existing single column on
  phones. Prices24px, fact labels14px; hidden-price wrapping remains protected.
- Soft backgrounds: root/everyday `#f8f8f6`, highlights `#f6f5f2`, apartments
  `#f5f4f2`, journal `#edf2ef`, chooser `#e9f0eb`, contact `#f1f0ed`; panorama,
  team and individual apartment cards retain white. No gradient or extra media.
- Muted text `#58645d` has computed sRGB contrast at least5.34:1 against these
  light surfaces; body text `#4e5953` is at least6.30:1. This is a limited palette
  calculation, not a complete accessibility certification.
- Section rhythm56px desktop/40px mobile; consistent24px heading-to-content gap,
  grid gaps48/28px and value minimum heights46/60px (not fixed clipping heights).
  Native forms live outside the owned root and receive no styling changes.
- Baseline full-page screenshot leaves some offscreen media unpainted. After
  entering the lifestyle section, all four images report completed decode,
  natural widths1280-1920 and opacity1. Do not treat an unpainted full-page
  capture as proof of a broken image or silently change media loading.

## Intermediate v1.0.3 Release

First controller LF SHA256
`3b82f3e7fcd085b25ec4e4f9fba54af0ae63dd1e311145f421859a2a3d900337`,
private backup `/home/harmath2/codex-backups/home-balance-d62fa7fc1a0640a8968d3dd1de390182`.
Released and independently verified with staged/formal PHP8.3 lint, exact
controller-only atomic replacement/cache, nine200 routes,124 public-native
inventory parity, unchanged native form/SEO/protected source-page-property-CRM
snapshots and35 pure offer+17 anti-spam checks. No submission/mail write.

Actual320 HU and Chinese all section h2 now28px, root301px/scroll301px and no
measured text overflow. Actual767 all section h2 now32px, two336px cards instead
of three223px cards, no measured overflow. Palette rules are active. Chinese
long summary unit wraps taller than its neighbor; follow-up will place units
on a separate line with nonclipping64px minimum value height.

## Follow-Up Hero Actions

User asks for the two original buttons. Read-only DOM parsing of the saved
original public homepage identifies `Virtuális Lakásválasztó` linking to the
official virtual selector and `Időpont Kérés` linking to the native
`#opal-contactform-popup-56f08cc`. Restore these destinations using existing
owned translation keys `chooserLink` and `appointment`. No new form handler,
submission API, dictionary or media changes. Construction-log menu and section
remain. Follow-up requires a fresh backup and native popup/layout regression.

## Current v1.0.4 Release and Regression

- Controller LF SHA256 `680369cfa7dd3613a28d9b62d9ccb96e334cdb615a42d124cdc10c607e393bf1`.
  Fresh private backup `/home/harmath2/codex-backups/home-actions-f89880b13fc04920aa8b3734ca3c012c`.
- Only the controller changes: a guarded exact-fragment transformation of the
  two manifest-pinned owned hero anchors, same reviewed right-arrow SVG, existing
  chooser/appointment translations and scoped layout rules. Full output fails
  open if either original action is absent/duplicated/drifted. No native HTML
  serialization, new submit code, translated field values or form styling.
- Temporary/formalPHP8.3 lint, exact-owned atomic replacement/cache,9 routes200,
  public-native124 inventory parity, native form/SEO/protected state and35 pure
  offer+17 anti-spam checks PASS. Separate `VERIFIED_HOME_HEADER_FLOW` PASS using
  the current actions adapter. No positive submission/customer/lead/mail write.
- Offline73 controller assertions,7 actions transport tests,3 new rendered-node
  action/translation/CSS tests;8 balance/7 historical compact/27 shared safety/
  24 original transport/20 presentation Node/71 presentation PHP PASS. Historical
  version tests use virtual pinned source fixtures; production update pins were
  not weakened. Body, JS, media, vendor and asset manifest remain byte-identical.
- Actual2400 desktop: all section titles36px, three397px cards, two hero actions
  parallel at44px high and212.6/151.4px wide. Actual1280: all titles36px, three
  372px cards, root1261/scroll1261. Actual767: all titles32px, two336px cards,
  root749/scroll749. Actual320/390: all titles28px, single-column cards,
  root301/371 equals scroll width, no measured text overflow. Photos retain
  their genuine original assets; no new media or library requests.
- Actual320 Chinese summary value heights63.984px for all four items; caption
  tops align within each row. Existing native language changes both restored
  action labels and preserves hrefs. Header76px and hero behavior retained.
- Restored desktop and320HU appointment links open exactly one native form.
  Chinese320 appointment selects A1/1/A1-1-L2, original Hungarian Google source
  value, date2026-10-22/time12-15;220px form, empty contacts and requiredunchecked
  privacy. Close without send. Actual restored virtual-selector link navigates
  to its original route with A1-A4 destination controls. Construction-log menu
  and content section remain.390 inline3D initializes one canvas and Project/
  Panorama tabs switch; no fullscreen/physicaltouch/Safari guarantee.
- Screenshot evidence is private and ignored: `balance-final-desktop.png` and
  `balance-final-320-zh.png` under `outputs/2026-10-18-home-family/`. Document clips
  at a scrolled position may show an offscreen native spacer plus fixed header;
  DOM/viewport hit tests distinguish this from two on-screen sticky bars. No
  all-device or comprehensive accessibility certification claimed. Dedicated QA
  tab opened when the earlier user tab navigated elsewhere; unrelated pages not
  used as website evidence.

Current verification/recovery (restore only the prior v1.0.3 controller):

```powershell
python server-config/maintenance/2026-10-18-update-home-actions.py --verify /home/harmath2/codex-backups/home-actions-f89880b13fc04920aa8b3734ca3c012c --expect 680369cfa7dd3613a28d9b62d9ccb96e334cdb615a42d124cdc10c607e393bf1
python server-config/maintenance/2026-10-18-update-home-actions.py --rollback /home/harmath2/codex-backups/home-actions-f89880b13fc04920aa8b3734ca3c012c --expect 680369cfa7dd3613a28d9b62d9ccb96e334cdb615a42d124cdc10c607e393bf1
```

Older balance/compact/whole-home tools intentionally reject the current local
controller. Do not bypass their ownership pins; no business database is restored.
