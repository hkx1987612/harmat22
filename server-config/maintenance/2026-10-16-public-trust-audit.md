# Public Trust And SEO Audit - October 16, 2026

## Priority And Scope

- User prioritizes verifying the supplied third-party website report. Messenger/network work is set aside, not completed or published.
- Reviewed shared-main baseline: `cdaa3ec6e637d0ab57de116f00f2f228c160e5b2`. Main/origin matched before the first deployment; no inherited dirty tracked files.
- This is a bounded evidence-based audit, not a new exhaustive crawl, Search Console inspection, PageSpeed score, legal eligibility decision or ranking guarantee.
- Preserve public prices, apartment records, existing inquiries, CRM, mail, appointments, video, ads, identity, security rules and the existing nogakft service. Never repeat the October 14 positive TEST.

## Report Reconciliation

| Supplied claim | Current evidence | Decision |
| --- | --- | --- |
| 1. Gallery dummy agent/name/email/telephone | Current Chrome gallery has official sales contacts. Seven retained public-page extracts contain none of the three alleged dummy strings; the contact extract was truncated and is not exhaustive raw-source proof. | Do not introduce a broad global replacement for a stale/unreproduced claim. |
| 2. Conflicting public sales telephone | Gallery/contact/footer inspected use `+36300733375`, with `ertekesites@harmat22.hu`. `+36306410358` is the separately approved recruitment number. | Preserve the current sales number and recruitment distinction. |
| 3. 398 versus 124 apartments | About page distinguishes 398 planned apartments from phase one's 124. Homepage explicitly labels phase one; 2028 Q2 is a planned handover, not a guarantee. | Do not change approved project totals or invent a new delivery promise. |
| 4. Definite CSOK promotional wording | Three exact old homepage component strings remain in generated source; the current modern layout hides these legacy components. This is crawler/source hygiene, not proof those statements are visibly prominent. | Scoped replacement in homepage Elementor document164, icon-box `df8c809` and RevSlider widget `ab01017` only. No eligibility claim or all-site policy rewrite. |
| 5. No public prices | Homepage and search show actual prices. Chrome `?rooms=3` selects three rooms and displays32 results, with HUF prices and unit prices. | Preserve unified public-price visibility rules; no invented starting price or gated price-list requirement. |
| 6. Missing breadcrumb/schema | Inspected routes already emit parseable BreadcrumbList and ApartmentComplex. A2 has Home plus current page, but current name contains literal `&#8211;` and lacks useful intermediate selector hierarchy. | A targeted Yoast breadcrumb/encoding improvement remains worthwhile; do not append duplicate schema or guess coordinates. |
| 7. Multilingual gap | No independently approved EN/ZH landing-page implementation was established in inspected routes. This is a market/content opportunity, not proof Hungarian pages are broken. | Plan separate reviewed EN/ZH URLs with accurate language links/hreflang. Keep Hungarian public pages free of mixed-language copy. |
| 8. Gallery render context | Existing footer notice/image alternatives provide context, but there was no concise notice immediately above the gallery. The `Kilatas` category remains. | Add one Hungarian notice without asserting every image is a rendering. Preserve all26 images, category filters, originals and lightbox. |
| 9. HTTP/privacy/mobile/forms | Both HTTP and HTTPS privacy URLs return200; canonical is HTTPS. Actual390px Chrome home/gallery/property/A2 have no horizontal overflow in inspected states. Source/date/time/privacy fields exist in the quote modal. | HTTP redirect still requires a separately validated hosting/ingress solution. No new form submission or inbox-delivery claim. |

Reference pages: [home](https://harmat22.hu/), [gallery](https://harmat22.hu/galeria/), [contact](https://harmat22.hu/elerhetosegeink/), [about](https://harmat22.hu/magunkrol/), [three-room search](https://harmat22.hu/lakaskereso/?rooms=3), [A2 selector](https://harmat22.hu/virtualis-lakasvalaszto-a2-epulet/), [privacy](https://harmat22.hu/adatvedelmi-tajekoztato/).

## Additional High-Priority Finding

Before the copy-only release, Chrome A1-F-L1 hero labels57.05m2 as `Eladasi terulet`, while the same page's detailed sold-area row, search card and quote modal use60.10m2. Both show the same total price84,342,300HUF, but the displayed unit prices consequently differ. Indoor/base area is57.05m2. No customer data or price was changed to reconcile this ambiguity.

Next work should audit the124 public units against the approved sales-area/base-area definitions, then fix presentation at its actual source. Do not choose a commercial area just because one particular UI is newer, edit saved quotes or alter apartment total prices. Source/raw-HTML startup states alone are not reliable evidence of the final browser state.

## Copy-Only Release

- Runtime: `wp-mu-plugins/zz-harmat-public-trust-copy.php`, LF SHA256 `464d892b998fdbea469584cbcbb2e0d9defc380d3f343e394483f97383ccf8a3`.
- Three exact homepage literals become family-friendly/non-subsidy-promising wording. Only the known document/widget/title field/render fragment is changed; no content/database write or whole-site output replacement.
- Gallery439 gets exactly one paragraph before its known Elementor gallery content: `A latvanytervek tajekoztato illusztraciok; a megvalosult allapot elterhet a bemutatott kepektol.` The runtime emits correct Hungarian accents, not this ASCII internal-note transliteration.
- Isolated fixture: `2026-10-16-test-public-trust-copy.php`,251 assertions including five fresh-process constant gates and actual one-argument gallery-filter dispatch. Fixture accepts staged candidate path in argv1.
- Guarded copy-only orchestrator: `2026-10-16-deploy-public-trust.py`;57 offline deployment/failure cases. Default/`--check` never connects. It accepts exactly one original-absent MU, pins source/test bytes and baseline, uses private0700/0600 backups, stages/lints before atomic new-file activation, lints/hashes formal source, checks protected data/page/source aggregates and removes only owned new bytes on failure.
- Combined approved source/test descriptor: `f84852f8bc3b480ef49f9cba2dd5c141ac3b266a9ae6ea722cff1bdcbbf383f9`.
- Additional native-render preflight: `2026-10-16-native-public-trust-preflight.php`, pinned LF SHA256 `62c1753a9fda9ba263ce835b3c264593b13260468022b863c1030e412f5656f5`. Private CLI bootstrap sets no-cache/disable-WP-cron, establishes public query context and renders the actual Elementor documents with the private candidate, checking all three old home literals and gallery notice/marker counts before activation. It is not a publicly accessible endpoint or a form submission.
- Only document164/439 Elementor element-cache metadata is removed before native candidate rendering and after activation/recovery; standard existing page/object cache purge follows. No global Elementor CSS regeneration or content rewrite.
- Successful private backup: `/home/harmath2/codex-backups/public-trust-623b8e8e1dbb480e8b45a5630439b942`,0700/0600. Production PHP8.3 isolated251 checks and native renders passed BEFORE formal activation: home87,928bytes/old three literals absent; gallery17,497bytes/exactly one notice and one target marker. Staged/formal lint/hash, atomic original-absent activation, cache purge and full postchecks passed. New-process independent `--verify` exited0.
- Public HTTPS privacy200/no intermediate redirect; HTTP privacy still200, explicitly NOT fixed. Nine existing key routes200/no fatal markers, public124-unit/nine-field/native-authority parity/GET-only harmless feed-POST404 and17 native anti-spam pure callbacks pass. No positive inquiry/mail invoked. All protected customer/property/CRM/page-content/Elementor-data and eight existing critical/adjacent source hashes remain unchanged. The complete public HTML image-source signature is unchanged:53 img tags including noscript/fallback/header, SHA256 `c5dd317f19224ff3a149a894dc73a0339a4d4fed1cd33c95bd04b2e978156dd7`; this is not53 gallery photographs.
- Independent final private readback confirms formal LF source464d892... and native preflight62c1753... exact hashes, no owned stage files, rejected HTTPS MU absent and temporary diagnostic MU absent.
- Chrome final gallery at actual390px has one370px image column, one readable notice and no horizontal overflow. Actual2381px desktop has two608.008px columns and no overflow. The26 ordered original JPG links exactly match the pre-release browser baseline. `Kilatas` selects six images and `Osszes` restores the gallery. Native Magnific preview opens the first1920px original and its close control works; the AX snapshot did not expose that overlay, so explicit DOM visibility/loaded-image/close checks were used rather than treating the first incomplete snapshot as a failure.
- Final actual390px property quote modal366.016px wide opens/prefills the same apartment and total price; all five source choices, date/time/privacy controls exist. Google and social radio selection works, modal closes without submission. Home modern title/project numbers/links/prices remain; before-release mobile A2 image loads1920px with no horizontal overflow. No real Safari/iOS, China-network, positive form/inbox delivery or exhaustive new145-page crawl claim.
- Gallery screenshot capture repeatedly timed out in the browser channel. No successful final gallery screenshot or pixel-perfect visual certification is claimed; DOM geometry, exact copy, image decode, interaction and source signatures were checked. The earlier successful mobile-home screenshot preceded this release. Temporary viewport reset completed successfully. Explicit test-tab close was refused by the browser, not retried or claimed successful; normal turn cleanup remains responsible for ephemeral tabs. The final live-gallery tab is retained as a deliverable and the three older pending workflow tabs are re-marked for handoff.

## Rejected HTTPS Batch And Safe Recovery

- Earlier two-new-MU attempt passed265/241 isolated production-PHP8.3 checks, private/staged/formal lint and activation, but failed a strict public response check. Its original generic error did not retain the actual HTTP status; it is NOT evidence of an HTTP403.
- Private failed-batch backup: `/home/harmath2/codex-backups/public-trust-4459eed749b94c0b889e583861b18f82`. Automatic owned-file removal completed. Independent readback proved both original targets absent and all protected source/page/property/offer/CRM aggregates unchanged. No customer/database rollback.
- One temporary staged/linted native diagnostic produced no public output and was removed in `finally`. Fixed identifiable-agent HTTPS and HTTP privacy GETs both returned200, but only one template-redirect diagnostic row ran. That row had SSLtrue, litespeed SAPI, no admin/Ajax/JSON/login/preview/REST/cron/CLI flag and no sent headers. This supports a pre-template cache/serving-path bypass; it does not identify every hosting cache layer or prove all requests share the same transport state.
- Do not claim the MU candidate provides reliable forced HTTPS. Its original source and265-check fixture remain in the private failed-batch backup only; the newly created unreleased local candidate/fixture were discarded, not committed as deployable source. It must NOT be copied into `wp-content/mu-plugins` as a released solution.
- No `.htaccess`, proxy, trusted-header, DNS, firewall, authentication, TLS validation or hosting security change. Validate redirect behavior at the actual HTTP/TLS ingress before proposing a hosting change.
- Archived access logs available during diagnosis were stale, not current request proof. Existing root error_log had only October15 Autoptimize missing-cache-directory warnings, not a fresh fatal from this release. Do not delete logs or fabricate causal conclusions.

### Corrected Native Hook And Preflight

- The first copy-only attempt at private backup `public-trust-3bdc2752b6cb4daaa8fc15880c9f5369` passed the home-copy check but failed gallery marker verification. Its new file was automatically removed; independent absence and protected-data/source equality passed.
- Actual installed Elementor `includes/frontend.php` line1213 applies `elementor/frontend/the_content` with ONE argument. It does NOT apply the initially selected `elementor/frontend/builder_content`. The final runtime uses the real one-argument hook, scoped by queried public gallery439 and exact gallery marker. Home builder-data and specific RevSlider hooks remain unchanged. Mock-only241 assertions did not establish that nonexistent hook's native effect; the final251 fixture contract and separate native renderer address that gap.
- A subsequent private native-only preflight at `public-trust-165be63448b3489cb1ad4f21d2c6f317` stopped before any formal activation. The first native home process exited255 with a WordPress error response; the initial safe wrapper retained no actual error text, so no precise exception attribution is claimed. After targeted cache purge, a repeated private home process exited0. Final orchestration therefore clears the two known Elementor caches before native rendering and on failed preflight; the formal target remained absent throughout this checkpoint. No public-page fatal or business-data change was established by that private-process result.

## Ordered Next Work

1. Resolve the A1-F-L1 area-label/source mismatch and measure the same condition across124 units, with no total-price/customer-record edits.
2. Prove and correct public HTTP-to-HTTPS behavior at the verified ingress/cache layer, including cached and uncached requests, without touching private portals or POST handlers.
3. Improve known selector breadcrumb hierarchy/entity decoding through existing Yoast contracts; preserve the current single ApartmentComplex graph and add coordinates only from a verified project source.
4. Review the financing page and other separately identified legacy CSOK pages against approved current wording. Do not infer loan/subsidy eligibility or mass-replace every occurrence.
5. Build a small reviewed EN/ZH visitor journey if required by the actual target market: project facts, available apartments, construction evidence and contact. Keep forms/shared data authoritative, separate language URLs and valid reciprocal language metadata.
6. Profile actual home/search/selector mobile load with bounded local measurements. Preserve the chosen local hero video and front-end layout; no repeated public PageSpeed quota calls or promises about untested China/Safari networks.

Google's official guidance supports consistent visible/structured content for AI search, canonical/redirect signals for duplicate URLs, and explicit language URLs/annotations. It does not guarantee ranking, AI citation or rich-result eligibility: [AI features](https://developers.google.com/search/docs/appearance/ai-features), [canonical URLs](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls), [multilingual sites](https://developers.google.com/search/docs/specialty/international/managing-multi-regional-sites).

## B-Computer Verification And Recovery

Pull the released shared main first. Do not redeploy this original-absent operation or repeat any positive TEST. Current stable tag: `stable-2026-10-16-public-trust-copy-current`.

```powershell
python -B server-config/maintenance/2026-10-16-deploy-public-trust.py --verify /home/harmath2/codex-backups/public-trust-623b8e8e1dbb480e8b45a5630439b942
```

Only for a confirmed regression from this exact new MU, after reviewing any newer legitimate site/customer changes:

```powershell
python -B server-config/maintenance/2026-10-16-deploy-public-trust.py --rollback /home/harmath2/codex-backups/public-trust-623b8e8e1dbb480e8b45a5630439b942 --allow-live-write
```

Rollback removes only byte-identical originally absent new copy MU and clears its page caches. It never restores old customer records or old existing files. Source drift is retained/reported. Verification deliberately compares protected snapshots, so legitimate new leads or later edits can stop it; investigate/rebaseline read-only instead of reverting business data. HTTP200 is the explicitly recorded remaining behavior, not a requirement to undo a future validated HTTPS fix.

## Explicit Limits

No new positive inquiry, customer mail, Messenger reply, model call, Meta publication, ad-budget change, new automation or shutdown. Preserve earlier pending hosting/API/Meta handoffs; this audit does not resolve Facebook AI launch gates. Private diagnostics, raw logs, account identifiers, media and credentials remain outside Git.
