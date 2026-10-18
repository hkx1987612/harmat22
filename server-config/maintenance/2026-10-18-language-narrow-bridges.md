# Narrow Chinese UI Bridges

Status: RELEASED v1.1.0 October18; independent server verification and actual
desktop/mobile/native controls pass. Stable stable-2026-10-18-chinese-ui-current.

## Approved Scope
- User explicitly requests Chinese property footer, assistant launch label and
  generated offer message, with backup/regression and no quote/appointment damage.
  Shared mainf89f78c/current stable1.0.4 clean and pulled current. Later asks if old
  program can be replaced; answered per-feature migration is possible, no whole
  legacy disable/replacement inferred from that question.
- Only language runtime, dictionary and controller version/two pins may deploy.
  Legacy migrated-snippets, native offer modal, backend, assistant plugin, CSS,
  bootstrap, patterns, inventory, prices/areas and stored HU content stay unchanged.
- Standing Astra-planning/Sol-implementation preference used for bounded runtime
  and isolated tests; parent owns independent real-native tests, package/recovery,
  review and live verification. No live writes by worker.

## Boundaries
- Footer exception only exact direct-HTML DIV.harmat-property-disclaimer under a
  single-property body and exact public apartment path; no DOM moving, broad HTML
  subtree permission, new text facts or native disclaimer-owner replacement.
  Existing in-body footer works as before. Keep excluded descendants excluded.
- Assistant changes only exact direct leaf label span in native launch button,
  original Kerdezzen mapping reused. Avatar/SVG, panel, customer conversations,
  assistant input/language controls and event ownership remain untouched.
- Default message bridge only exact original native form/textarea/selected-unit
  and exact existing generated marker/value/code. Owned automatic value/marker
  follow HU/Chinese and native apartment changes. Customer input/change marks
  field dirty; do not rewrite edited values, even when identical to either default.
  Clear replaceable-auto marker on dirty textarea so native equality comparison
  cannot mistake customer text for generated text. Dirty identity survives runtime
  destroy/recreate via weak node set; no customer strings stored outside field.
  No fabricated input/change/submit events, no other field-value writes or alternate
  submit route. Native owner may still refill an empty message under its original
  empty-value rule; translator never overwrites the user's empty draft.
- Seven reviewed static home title/aria variants add dictionary keys. Original1187
  mappings preserved; no conflict override. Original assistant mapping already
  exists as Chinese, so no duplicate or broad chat translation.

## Offline Test Evidence
-132 Node tests independently PASS:29 original native HTML/form tests,13 unchanged
  header geometry/visibility, four dictionary/attribute tests,74 narrow bridge
  fixtures and12 real-native-script integration cases. Full final source hashes
  reviewed; no runtime changes after the passing run.
-306 public PHP scope assertions/52 cases and nine offline transport/ownership
  tests PASS. Exact f89f78c six-file preimage, original1187 dictionary values,
  unchanged CSS/bootstrap/patterns, foreign drift rejection, temp PHP lint before
  rename/failure preservation and owned source-only reverse replacement covered.
- Independent real-native scripts use saved public HTML and captured public
  property records through native harmatOfferApartments slot. Saved Contact
  frontData.items is empty; fixture originally lacked selectable units, corrected
  from existing public records, not a production selector bug or invented data.
- Independent test catches exact-HU customer draft mistaken for auto text after
  marker restoration; fixed before deployment by dirty marker protection. No live
  activation during this failed offline test.
- All shadow request data intercepted locally; no real TEST/lead/appointment/mail.
-145 saved-page source audit PASS145/145 exact roundtrip/native identity, zero
  errors, two remaining source-review groups (phase identifier/inherited filename
  alt), not a rendered-coverage percentage. Final runtime/dictionary hashes match.
  Page-owned scripts not executed,
  so exact footer/native-form cases above and actual browser are separate gates.

## Reviewed Package
- runtime.js LF SHA256:
  6dcf4f0cd849c89123a0aae44bc2bfeb5a077ec89b70db4acaf26ae6c1c2fad1.
- zh-Hans.json SHA256:
  7651d634166af48b78084e6b5f6a0f7a0795d30ed2ada2c7d5bd0f24edac7316.
 1194 entries/116522bytes, seven additive keys, same six old merge warnings.
- Controller LF SHA256:
  a34c8461f8a65c5b8b9e9b45aa9e73ba92d2d76fd46e2362ae3ef294d8b29c45.
  Changes only1.0.4 to1.1.0 and runtime/dictionary pins; public scope unchanged.
- Bundle8bc5492e1119f422e63fad557cc82c2c74c3644eeee8ab56e8b84b50f147757e.
- Previous successful private backup:
  `/home/harmath2/codex-backups/internal-language-dictionary-57956dc7b29f4687b945b3648ea52312`.

## Production Verification
- Successful private0700/0600 backup:
  `/home/harmath2/codex-backups/internal-language-bridges-a101236270294d9aacaa89baa54a5ddb`.
- Exact previous six-file preimage, private candidate306 public-scope assertions,
  actual35 pure offer/17 anti-spam callbacks, temp/formal PHP8.3 lint, exact hashes,
  atomic runtime/dictionary then controller replacement/cache purge PASS.13 public
  routes/defaultHU/one switch, five public asset hashes/MIME,124-unit public/native
  authority and GET-only/noindex boundaries PASS. Fresh protected business/source/
  page/Elementor/HTTPS/selector snapshots unchanged. No business-data restore.
- Separate final process returns VERIFIED_HEADER_REFRESH for exact current backup;
  this inherited status label refers to three-file localization, not a header edit.

## Actual Browser
- Same authorized Chrome retained. Formal A3-4-L5 page uses final runtime6dcf4f0c
  and dictionary7651d634. Actual HTML-child footer now Chinese without moving it;
  HU switch restores original disclaimer, launcher and untouched automatic message.
  Chinese generated message/marker contain exact selected A3-4-L5 code.
- Native desktop property quote opens/closes. Typed exact-HU default is recognized
  as customer edit, remains unchanged after HU/Chinese switch and reopen, marker
  absent. No personal contact data, privacy unchecked, never clicked submit.
- Native assistant Chinese launch label displays existing reviewed `请提问`.
  Opens same original panel (expandedtrue/grid/nonzero height), native close returns
  expandedfalse, original input/three independent language controls retained. No
  question sent or conversation/history read; chat's own language remains separate.
- Actual320 Contact appointment selects A1/1/A1-1-L2 then A3/4/A3-4-L5; both
  generated defaults update in Chinese with matching markers. ALL five original
  source values/dateOct21/time09-12/required unchecked privacy pass;220px form fits.
  Customer-edited exact-HU A3 draft survives selecting A1, toggling and reopening.
  Contact reopening naturally clears selected apartment via original applyItem
  context; do not claim selected-unit persistence or silently change native owner.
  Native reload clears only own unsent local test draft; no backend record deletion.
- Actual390 checks17 routes: home/search/three properties/all six virtual selectors/
  Contact/gallery/journal/project/About/privacy. No root horizontal overflow/major
  nonempty text clipping/completed rendered broken image observed. Empty image-hover
  anchor false positive excluded. Actual390 property quote has Chinese A3 default.
  Actual320 outside footer fits269px/scroll266px, Chinese text and launch label,
  no root overflow. Footer remains outside body as existing legacy owner emits it;
  this task does not repair that original HTML structure.
- Actual320 A2 room2/floor1 returns exactlyL2/L3/L4, ready1920x1080 canvas.
  Search first sampled before debounce gave no rendered cards, NOT a passing check;
  later explicit visible-card wait at actual2400 gives one A3-4-L5 result, sales47.83/
  interior41.86/terrace11.95/63,214,200. No current320 search-result claim.
- Actual home title now Harmat Lakopark experience-center Chinese and launcher
  Chinese; final desktop2400 restored and live homepage marked deliverable. Earlier
  reset immediately measured1161 before settling; final measured2400 is authoritative.
- Private screenshot `native-chinese-default-screen.png` inspected: actual original
  quote form, correct Chinese default/code, empty contact fields, uncheckedprivacy,
  original submit. Normal desktop footer screenshot shows Chinese/footer+launcher,
  but existing fixed cookie control can cover the start at bottom. No CSS/spacing
  change made.320 capture and document-coordinate detail clip are blank and NOT
  mobile-pixel evidence. Do not certify every device/viewport/full pixels/Safari/
  China/FPS/inbox. Semantic/layout/native checks distinguished from screenshots.
- Raw saved-source audit under ignored
  `outputs/2026-10-17-chinese-audit/2026-10-18-narrow-bridges`; private browser proof
  and screenshots under `outputs/2026-10-18-language-bridges`. Never commit these.

## Recovery
- Reuse pinned safe transport with exact1.0.4 six-file preimage, private outside-
  webroot originals/all six candidate siblings, native/scope preflight, temporary
  PHP lint, atomic controller-last replacement, formal lint/cache/public checks.
- Final helper `2026-10-18-refresh-language-bridges.py` defaults OFFLINE_NO_WRITES.
  Fresh protected snapshots must agree during deployment/independent verification;
  concurrent business changes are investigated, never restored automatically.
- Read-only current verification:
  `python server-config/maintenance/2026-10-18-refresh-language-bridges.py --verify /home/harmath2/codex-backups/internal-language-bridges-a101236270294d9aacaa89baa54a5ddb`.
- Rollback only exact current owned runtime/dictionary/controller to1.0.4 using
  same helper `--rollback` with that exact backup; native business data never restored.
  Foreign file drift stops recovery. Superseded dictionary/header deployers must
  not be run against new public source/hash pins.
- Private raw HTML/nonces/screenshots/reports and original media stay out of Git.
  No ads/social/Messenger/security/automation/payment/identity/shutdown work.
