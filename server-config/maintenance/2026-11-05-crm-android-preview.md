# CRM Android Offline Preview - 2026-11-05

## Scope And Status

User clarified the proposed Android app is for internal CRM, not the public
apartment portal. Implemented an isolated UX test package, not a production
CRM connection or backend deployment. Existing ignored `android/harmat-app`
source and old packages are untouched.

Source: `apps/crm-android/`. Package: `hu.harmat22.crm.preview`, version 0.1.0.
Screens: overview/daily traffic, client search and source filters, chronological
follow-ups, synthetic apartment-to-client lookup, Chinese/Hungarian settings.
All records and inventory are explicitly marked as synthetic. No public prices.

## Safety Boundary

- No INTERNET permission, customer exports, session cookies, API keys or login.
- No customer writes, calls, offers, appointments, advertising or live requests.
- Shared preferences store only the UI language; backup/transfer disabled.
- Native views, no WebView or JavaScript bridge.
- Old production PHP unchanged; sales-manager SHA256 remains
  `e40599dca32a4c15803a79c669abdf2d863480fdd8516c4d8a70748c3895749c`.
- No live edit means no new production backup/restore was necessary. The last
  verified production backup remains intact; do not restore it for this preview.

## Verification

- Gradle 8.10.2 / AGP 8.7.3 / JDK17 / Android SDK35 offline build passed.
- Android lint: no issues found. Standalone fixture tests:32 passed.
- UI Automator smoke:15 assertions plus source-filter exclusions on Android35;
  Chinese/Hungarian, search/empty-state reset, source/status filters, detail
  dialogs, linked apartment navigation, language persistence and crash buffer.
- Normal display1080x2400/density420 and compact720x1280/density320; system
  font scales1.0 and1.3. Screenshots reviewed, including keyboard-visible search.
- Compact review prompted shorter Hungarian views caption, aligned metric
  labels and hiding native top/bottom chrome plus the client-page heading while
  the Android30+ IME is open, leaving space for search results.
- Emulator settings are restored by test `finally` blocks. No physical-phone
  installation or older-Android runtime acceptance is claimed.
- APK permissions inspected: no requested permissions. Debug signature valid.

The workstation's JDK Windows Unix-domain socket initialization failed. A
process-local temporary-socket-path override made OpenJDK fall back to TCP.
No firewall, OS security rule or global Java configuration was changed; see
the application README for the source reference and reproducible command.

## Local Artifacts

Ignored output: `outputs/2026-11-05-crm-android/`.
APK: `Harmat-CRM-preview-0.1.0.apk`.
SHA256: `033b7dcbffe73688ecd16fcdec792d81761a5417e703d94ff4fc75e084a9f884`.
QA: `qa-final/`, `qa-compact-final/`; no screenshots/APK committed to GitHub.

## Follow-up Gate

This APK is a preview only. A separate backed-up backend batch must establish
authenticated read-only CRM access with existing sales-role/object permissions.
Do not turn a WebView into an alleged read-only mode by hiding write buttons.
Before any mobile editing, fix and test the existing whole-option lost-update
and new-ID-collision issue across sales-manager and legal-documents writers.
Quotes and appointments remain outside the mobile editing scope.
