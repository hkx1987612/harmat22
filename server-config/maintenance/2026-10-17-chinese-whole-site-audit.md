# Chinese Whole-Site Read-Only Audit

Status: READ-ONLY AUDIT COMPLETE. Translation gaps confirmed; no live repair yet.
Current production remains bb8cd18 / stable-2026-10-17-language-header-current.
No live code/config/content/cache/business edits or new positive TEST/mail.

## Scope and Evidence
- User requests whole-site Chinese omissions/layout review, especially mobile,
  without impacting native quote/appointment. Required records read; clean shared
  main bb8cd18 pulled current. One bounded Sol writes local offline coverage tool,
  parent owns fresh snapshot collector and actual browser/function checks.
- Fresh sequential145 published public URLs return200 with original HU language,
  exact canonical, one switch and one native form. No page JS/submission/private
  route/CRM access in collector. Guard initially assumed hu-HU incorrectly; existing
  baseline proves hu, corrected only audit expectation before complete crawl.
  Original earlier release snapshots are not overwritten. Fresh nonce-bearing
  HTML/source manifest stay ignored under outputs/2026-10-17-chinese-audit/fresh.
- Actual authorized Chrome checks24 distinct URLs at390 AND320: ALL21 public
  nonproperty pages plus A1-F-L1, A1-1-L2 and A3-4-L5. Includes home, search, all
  six virtual selectors, About/project/neighborhood/gallery/journal/Contact,
  financing/services/privacy/cookies/terms/imprint/complaints. No whole-page
  horizontal overflow, major visible button/heading/label clipping or completed
  rendered broken image observed. Not124 property mobile screenshots or all lazy
  media/pixel/Safari/physical-device/China/performance certification.
- Inherited intentionally1px screen-reader H1 and empty image-link hover scroll
  width are false positives, not visible Chinese clipping. Browser extension
  Monica overlays are not website widgets. Emulated screenshots have partial
  framing limitations; do not certify fixed-control pixels from them alone.

## Confirmed Translation Gaps
- Homepage17 rendered text types: Virtuális választó; Tervezett átadás;
  Energetikai besorolás;24 órás zárt lakópark; Hőszivattyús fűtés-hűtés;
  Alapterület az első ütemben; Lakás az első ütemben; Mélygarázs parkoló;
  Interaktív bemutató; Panorámás látványtér;
  Húzza el a képet, és nézze körbe a projekt térbeli bemutatóját.;
  Panoráma; Videók; Tájékoztató; Szünet; Teljes képernyő; Teljes galéria.
  Confirmed in actual Chinese DOM/layout, not inferred from candidate counts.
- Property text: Műszaki PDF, Fontos tájékoztatás: and the full common disclaimer
  beginning Az árak kizárólag tájékoztató jellegűek... remain Hungarian. Late
  insertion means an early A3 snapshot need not show the note yet. Do not rewrite
  Hungarian legal facts/terms or translate embedded PDF content in this task.
-123 property floorplan alt descriptions still end in `alaprajz`. Actual visible
  A1-1-L2 image confirms `A1-1-L2 alaprajz`; this is an accessibility/description
  gap, not123 visually broken floorplans or changed apartment data.
- Empty active native appointment date input is still type=text with placeholder
  `Dátum` in Chinese mode. Actual opened desktop native form confirms it; native
  date selection/values still work. Source audit finds `Dátum` and `Email-cím`
 145 times each, but Email-cím is a deferred legacy-source candidate, NOT confirmed
  present in the actual active native form (current native email has no placeholder).
  Do not describe290 source occurrences as290 broken live booking fields.
- Native selected-apartment default textarea remains
  `A A1-1-L2 lakás iránt érdeklődöm.` Input protection deliberately preserves
  values. This is mixed-language UX, NOT payload corruption or a failed send.
  Any later language-aware default must distinguish generated untouched text from
  customer-entered content; never blindly translate textarea/input values.
- Public AI launcher Kérdezzen remains Hungarian under `.harmat-local-ai-launch`.
  Existing independent AI chat exclusion is deliberate. Optional future patch
  should localize ONLY the launch label, not conversations or customer inputs.
- Virtual overview badge UTEM-1 is an existing phase identifier, not confirmed
  broken translation; a separate Chinese phase caption is optional. Proper
  company/partner/place/legal-entity names, addresses, cookie identifiers, dates,
  prices/currency and apartment codes are not automatically defects.

## Critical Function Checks
-29 existing no-network real-HTML/native JS regressions PASS. Five source shadow
  payloads, HU exact roundtrip, input descriptions without value edits, native
  trigger gate, privacy/validation/close and lazy session/dictionary covered.
  Existing original snapshot inputs are labelled; fresh145 audit is separate.
- Actual320 Contact appointment opens; A1/floor1/A1-1-L2, ALL five original
  source VALUES individually selected, date2026-10-20/time09-12, required unchecked
  privacy and close pass. Form220px fits the document; one native submit button.
  No name/email/phone entered, no submit/consent accepted or notification.
- Actual320 searchA3-4-L5 gives one correct result, sales47.83/interior41.86/
  terrace11.95/current63,214,200 retained. Actual A2 room2/floor1 returns exactly
  A2-1-L2/L3/L4, loaded canvas1920x1080. No data correction or selector source edit.
- Actual2400 native header quote opens; A3/floor4/A3-4-L5, Google source/dateOct20/
  time12-15/required unchecked privacy and close pass. Current QA desktop resets
 2400 and Project deliverable retained. Earlier separate Contact QA tab's320
  override from previous task is not new actual-device evidence.
- No fresh complete submission/delivery test in this task. Earlier uniquely
  authorized TEST remains hard-disabled against replay; no inbox guarantee.

## Evidence Files (Private/Ignored)
- outputs/2026-10-17-chinese-audit/browser-audit.json:24 routes per mobile width,
  native source values/default message/search/virtual layout results, no nonce.
- desktop-quote-proof.json: current2400 native UI values, no personal input.
- appointment-320.png and home-untranslated-320.png: saved/inspected actual UI,
  partial screenshot framing is not complete pixel/device certification.
- Raw HTML/nonces/screenshots/generated reports remain outside Git. Only owned
  stable audit source and concise records may be shared; production stable tag
  stays unchanged. Dictionary-only fix confirmation requested separately; no
  permission inferred to deploy audit findings or touch form/AI-chat logic.

## Final Offline Results and Handoff
- Fresh145 saved pages with current owned translator:145/145 invariant PASS,
  zero errors. Exact HU->Chinese->HU DOM restoration, native field names/values,
  data/href attributes, node identity and selections preserved. Page-owned scripts
  never executed; all network disabled. Self-test independently rerun by parent.
-31994 eligible source text nodes/31926 changed is a mechanical lexical count,
  NOT a complete visible-Chinese coverage percentage.127 candidate groups after
  exclusions include123 floorplan alt strings, two placeholder strings and two
  review-only groups. Real dynamic misses above are separately attributed to
  Chrome, not falsely certified by jsdom. Proper names/units/addresses/identifiers
  exempted after review. Full raw/report remain ignored under final/.
- Common late disclaimer has124 exact literals across124 property source HTMLs;
  actual browser confirms late text in representative properties. Initial offline
  DOM excludes script bodies, so do not claim that executing every property JS
  or all124 physical mobile pages was tested.
- Production code/dictionaries/assets/SEO/server/cache untouched. Existing site
  stable tag remains unchanged. Share only these two audit helpers and concise
  current/history notes; no generated source/report/nonces/screenshots/customer
  data. Bounded Sol closed, all local audit/test processes complete.
- Recommended first repair, ONLY after separate user confirmation: reviewed
  dictionary additions for public dynamic labels/disclaimer/date placeholder and
  exact floorplan descriptions, preserving Hungarian originals and native values.
  Default textarea/AI-launch exceptions need separate narrow design, not blanket
  translation or broad replacement. No quote/appointment submit required to patch
  descriptions; backup/temp PHP lint/atomic/cache/native regression remain gates.
