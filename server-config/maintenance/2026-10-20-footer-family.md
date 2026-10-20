# Footer Palette And Layout

## Scope

- User approved footer/color refinement, desktop/mobile preview, backup and release; quotes and appointments must remain untouched.
- The later screenshot identifies the pale-yellow footer navigation, not a request to replace a photograph. All photos/media remain unchanged.
- Sol owns the bounded renderer/style implementation; parent owns transport, preview, live verification and repository synchronization.
- Only `harmat-migrated-snippets.php` changes in production: version, `hm_migrated_clean_footer_html()`, its strongly scoped CSS, and removal of the old footer fragments from shared 900/560px media rules. Other original bytes/line endings are retained.
- Keep the exact `colophon` opening for property-disclaimer/payment guards. No new module, script, form, API, provider, CRM or database migration.

## Presentation

- Ink-green `#193e34`, soft-white links, muted gold headings/top rule. No cream/yellow background.
- Desktop project/apartments/contact columns; <=620px two navigation columns and full-width contact section.
- Duplicate apartment-search link replaced with construction journal. Four unchanged legal links move to a separate bottom row.
- Existing phone `+36300733375`, email `ertekesites@harmat22.hu`, address and copyright/disclaimer remain.
- Existing internal HU/ZH translator and dictionary unchanged; no external translation.

## Pins And Recovery

- Baseline Git `d608550`; production original raw SHA256 `76dcc12c8caf5aeae64f05ac54e0385f1ac1267470497c003e77ee45d48d287e`, LF `588e2740b09e02aff24f6d5ed3d89d34a76d3c142518c1a3c38e5e81646db2aa`.
- Candidate version `2026.10.20.1`, production raw SHA256 `6821501306eb456b3b9a7e1016ec40709aa82a7379fb47ee5fe269163bad6127`, LF `32868104d0da9d2a85ba0b779db408fe1688e8ec1ea3da4fda3615b3e8f92b19`.
- Production mixed EOL retained: original LF-only lines4/175/176/177; equal spans preserve raw bytes. Local checkout CRLF hash differs intentionally.
- Private backup `/home/harmath2/codex-backups/footer-family-1ff9b9a6441540abbf2abe8141051378`.
- Aggregate `6274a82a9df530f714d77c3e2957c5c04f58597e46cfc0ee0b7183406fc84633` binds original/candidate, helpers, unchanged private dictionary copy and17 protected homepage/UI assets.
- Exact adapter `2026-10-20-deploy-footer.py`: default offline; `--prepare`; reviewed preview cleanup; `--deploy BACKUP --preview-reviewed --expect AGGREGATE`; independent `--verify BACKUP --expect AGGREGATE`.
- These are historical R1 pins/commands and require the exact archived R1 source/helpers. The current checkout and current recovery command use the R2 adapter below; do not substitute today's485-assertion fixture into an archived477-assertion R1 aggregate.
- Recovery only while current source equals exact owned candidate: `--rollback BACKUP --expect AGGREGATE`. Atomic original-file restore/lint/cache purge and checks; never restore the database or customer records. Refuse foreign source changes.
- Raw backups/baseline HTML/nonces/screenshots remain private or ignored; never commit them.

## Preview And Tests

- User explicitly authorized a temporary public HTTPS static preview after the prior local-file browser refusal. No retry/bypass of the blocked file URL.
- Preview: noindex/nofollow/noarchive, no forms/inputs/nonces/native inline scripts; public styles plus unchanged internal translator. Actual visible IAB1280/1920/768 desktop/tablet and390/320 mobile measured; no horizontal or owned-element overflow. Mobile two columns/contact span, desktop three columns, HU/ZH round-trip/long email/legal wrapping pass.
- Preview removed before activation; browser reload visibly returned the site's404 page; preview tab closed. No preview URL remains linked from the site.
- Pure PHP477 assertions, Node16 tests, transport8 tests pass. CSSOM fixtures explicitly do not claim pixel/layout verification; actual browser checks are separate.
- First private staging attempt `footer-family-d2c699261e6a4838bdcd90ba9aca00e6` stopped before public preview/formal edits because fixture dictionary path assumed repo location. Fixture now accepts only readable absolute local dictionary override; exact dictionary staged privately.
- First activation attempt stopped during temporary-file metadata comparison before the atomic rename (JSON list versus helper tuple). Formal source remained original. Added typed tuple conversion and regression test; private activation snapshots are unique per attempt. No security/permission weakening.
- Public regression checks cover10 routes: home/search/A1-F-L1/selector/first-phase/A2/gallery/contact/journal/finance; native form/SEO/canonical/media unchanged, single footer/native offer modal, four legal links, unchanged sales contacts and124-unit public inventory.
- Native readonly validation35 offer plus17 spam cases; no positive form submission, notification or mail.
- Full current business/source/page snapshots surround release and verification, excluding only the existing three regenerable Elementor cache keys. No customer content exported/restored.

## R1 Release And Actual Follow-Up

- R1 RELEASED and independently VERIFIED:10 routes/native form-SEO/media identity/124 inventory,477 pure fixture assertions,35 offer+17 spam cases and stable protected state. Actual1280 three-column green footer below unchanged final photo;320 two columns/no page overflow.
- Actual scroll-to-bottom exposed a preexisting fixed Cookie settings button overlapping copyright (320: copy bottom719.73px, cookie top680px). Do not claim R1 had no visual overlap.

## R2 Cookie Clearance

- Only version `2026.10.20.2` and the two footer-bottom padding values change (desktop22px/mobile20px ->76px). No cookie/quote/appointment code changes. Reversing these three literals exactly reproduces R1.
- Current LF SHA256 `a74f6de8d93bc5f183cf7447be3100acbebacf19228cf2f5ea1de62d5d2b375f`; production mixed-EOL SHA256 `f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077`.
- Private R2 backup `/home/harmath2/codex-backups/footer-family-r2-a05724bae0dd4ff2aa3ecf869b4d6a3f`; aggregate `fe6c06c0a47483ac8a7dfaa23ca065c6e654724e6d676358bd87abf71a9f5587`.
- Use `2026-10-20-deploy-footer-r2.py` with the same prepare/cleanup/reviewed-deploy/verify flags and R2 backup/aggregate. Source-only rollback returns exactly R1, not the original yellow footer. Full original recovery is a separate guarded second restore from R1's private `original.php`, only if the target still equals R1; never restore customer data.
- Local485 PHP assertions,17 Node tests,8 transport tests pass. Actual public temporary R2 preview1280/320 HU/ZH/no overflow/computed76px bottom padding pass; reviewed static preview removed, browser404 confirmed and tab closed before activation.
- R2 RELEASED and independently VERIFIED:10 routes200/native form-SEO-canonical-media identical/124-unit inventory,485 pure PHP plus35 offer/17 spam checks, protected source/business/page snapshots stable. No customer data restore or positive submission.
- Actual official1280 desktop three columns;390/320 mobile two columns/contact span; HU/ZH no measured page overflow. Cookie-to-copyright vertical clearance:1280 HU16.16px,390 HU16.30px,320 HU16.27px/ZH15.53px. Existing contact-page assistant starts678px at320; copyright is above it. Cookie and assistant logic unmodified.
- Actual320 Chinese home quote opens/closes, contacts blank, required privacy unchecked, five original HU source values retained. Actual320 HU contact appointment dialog281px/no horizontal overflow; date2026-10-23 and time12:00-15:00 selectable/valid, contacts blank/privacy unchecked, closed without Send. Native offers/appointments not edited.
- Footer navigation to selector and A2 succeeds at320; overview poster complete1920px and loader hidden/no page overflow. First semantic A2 click did not match the CSS-uppercase accessible name; refreshed state and exact observed href succeeded, no forced action. This is not a full3D interaction recertification.
- Homepage/unchanged final photo restored, HU/default viewport restored, formal homepage retained as deliverable. Ignored screenshots `outputs/2026-10-20-footer/footer-desktop-final.jpg` and `footer-mobile-final.jpg`; both temporary preview pages removed/404-confirmed, tabs closed. Worker completed/closed after final source freeze.
- Stable tag `stable-2026-10-20-footer-current` after synchronization. No physical-phone/TV, Core Web Vitals or inbox-delivery certification claimed. No old ads/AI/video/shutdown task resumed.
