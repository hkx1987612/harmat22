# 2026-11-09 - Android NAS Delivery And Independent Audit

## Boundary

User asked to put the Android CRM APK on their NAS and use the highest available
model for regression review and improvement recommendations. Selected the tool's
frontier-review model `gpt-6-astra`, reasoning `ultra`, for an independent read-only
code review. Main agent performed separate emulator edge/negative-path checks.
Reviewer made no changes and has been closed. No new OpenAI API/project/service.

Reviewed application source is commit4c605ca, version1.0.0-test. This audit does
not replace the APK or claim that the findings below have been fixed. Only a new
test script and maintenance notes are added. No live website, CRM/PHP/data, ads,
quote, appointment, permissions or real account changes/submissions.

## NAS Delivery

Copied the existing signed APK to the user's mapped NAS, under
`Harmat-CRM/2026-11-09/1.0.0-test/Harmat-CRM-Android-1.0.0.apk`.
Existing differing files are never overwritten. Local and destination SHA256:
`6a3f9491d737db888406629939e35b6f7d77ee95d62a282b053004434bfcfe9b`.
Only APK and plain installation/audit notes are delivered; no signing identity,
password, auth cookie, customer data or screenshots are copied to the NAS.

## Verified Open Findings

1. P2, parser drift handling. `mobile/.../PortalPage.java:81` treats any
   `.harmat-sales-empty` as an empty requested view when its table is missing;
   line86 checks header count but not semantic order. The independent reviewer
   reproduced missing-table/unrelated-empty and same-count column-swap cases
   using synthetic in-memory probes. Existing169 parser/policy checks still pass.
   This does NOT show current live CRM records are wrong. Add view-specific empty/
   denied checks and semantic columns; mutate HU/ZH fixtures for regression tests.
2. P2, modal lifecycle. `mobile/.../MainActivity.java:361` creates untracked settings
   dialogs; line489 only dismisses the customer detail dialog. Main-agent runtime
   HOME/relaunch on API35 confirmed that the authenticated menu stays above the
   locked gate. Customer detail DID disappear, with no customer text after lock.
   Track/dismiss all app dialogs and invalidate their callbacks when locking.
   The current EdgeSmoke deliberately records this observation; after fixing,
   convert it into a failure assertion so it cannot silently regress.
3. P2, accessibility layout. `MainActivity.java:177` fixes each bottom tab to66dp
   while icon/padding/text grow. API35 at320dp/font2.0 shows clipped HU bottom-tab
   labels; normal/font1.3 previous checks do not cover it. Use content-driven tab
   height with a minimum, and retain320dp/200% as a visual regression case.

## Risks Requiring Targeted Tests

- Biometric callback ownership, `MainActivity.java:413`: a late cancellation/error
  callback can reset shared `biometricPending` without checking operation identity.
  Reviewer recommends operation tokens for all callback mutations and A-cancel /
  B-start / A-error tests. No authentication bypass was demonstrated.
- WebView callback ownership, `MainActivity.java:460`: old login errors can act
  on the current login without checking view identity/generation. Guard callbacks
  and test cancel/reopen/old-error order. Device occurrence was not demonstrated.

## Extra Runtime Evidence And Acceptance

- Added emulator-only EdgeSmoke.ps1:320dp/font2.0 visual capture, private detail
  removal, stale settings-menu observation and anonymous signed-release offline
  login. Offline login reaches its generic error instead of showing old records.
- Wi-Fi/mobile data and display/font changes were restored. The original release
  login scroll/password-focus/cancel script passed after network restoration.
  No credentials typed, real account authenticated or CRM form submitted.
- Synthetic screenshots/XML and private receipts stay ignored under
  outputs/2026-11-09-crm-android-audit. EdgeSmoke's PASS covers its named negative
  paths, not an assertion that the known menu/font defects have disappeared.
- Physical-phone first company login, actual cookie handoff, server session
  revocation, biometric enrollment invalidation and API28/29 physical behavior
  remain acceptance gaps. Do not call the app production-certified from fixtures.

Recommended order: parser drift guard and dialog ownership first; then callback
ownership tests and large-font layout; then actual company phone acceptance.
All are Android-side work except any separately approved future API contract.
No server migration is needed to address these app-level findings.
