# 2026-11-09 - Android1.0.1 Test Hardening

User approved fixing the three findings in the preceding independent audit.
Preserved original1.0.0-test signed APK/local/NAS copy and Git baseline3eb4165.
No website deployment, PHP/database edits, role changes, quote/PDF/appointment,
advertising, email or real account operations. App remains a native read client.

## Scoped Changes

- List parser validates exact semantic HU/ZH header order, including optional
  deal commission. Missing/renamed/reordered columns, unknown placeholders and
  unrelated empty widgets raise SchemaChanged instead of plausible wrong data.
  A valid empty result needs the requested view's direct panel heading/text.
  Existing server access/object filtering and field values remain authoritative.
  Dashboard parser is unchanged. HTML remains a coupled adapter, not a new API.
- All app AlertDialogs are tracked and dismissed on lock. Their actions carry
  the current screen generation; queued old actions cannot mutate a new screen.
  This includes settings, signout/account-switch confirmations, biometric offer,
  notices and private details. Native production dialogs keep FLAG_SECURE.
- Biometric and WebView callbacks carry owner identity/epoch. Old cancellation,
  errors, duplicated completions or reused owner objects cannot complete a new
  operation. Lock invalidates ownership before cancel/destroy side effects.
  Existing per-operation BiometricPrompt CryptoObject and AES/GCM vault unchanged.
- Bottom navigation uses content-driven height,66dp minimum and two-line labels,
  keeping text at the user's font scale. No viewport-based font shrinking.
- VersionCode2/versionName1.0.1-test, same application ID and RSA3072 signing
  identity. Filename derives from manifest metadata; output is a separate ignored
  delivery directory. No saved-session format or signing-key migration.

## Verification

- Parser835 checks pass on existing20 manager/staff HU/ZH renderer fixtures,
  including valid empty states, missing tables, unrelated empty panels, every
  renamed/adjacent-swapped header and optional commission. Original parser fails
  the expanded suite; no synthetic fixture is evidence of actual client records.
- CallbackOwner23 deterministic sequence checks pass for old-A/new-B, wrong
  owner, duplicate completion, owner-object reuse, lock invalidation and null.
  This is not exhaustive OEM callback scheduling certification.
- Astra/ultra implemented the disjoint parser/test patch, then independently
  reviewed the main-agent dialog/callback integration read-only. No actionable
  issue found within that bounded integration review; no claim of a full audit.
- API35 native normal1080x2400/420dpi and compact720x1280/320dpi each pass23 checks.
  EdgeSmoke now fails stale settings menus and signout confirmations, rather than
  just recording the defect. Private details, menus and confirmation cleanup and
  anonymous offline login pass. At320dp/font2.0 the reviewed screenshot shows all
  four navigation labels, wrapped instead of clipped. Source screenshots are fake
  data only and stay ignored, not on GitHub/NAS.
- Biometric24 checks pass, adding enrollment-offer background cleanup to actual
  CryptoObject encryption/unlock, wrong-finger rejection, cancellation, encrypted
  native storage, restart locking and local signout. No real fingerprint enrolled.
- Release build/lint reports no issues. Signed APK excludes fixtures/debuggable
  flag, includes dependency notice, and verifies with the original certificate.
  Emulator accepted an in-place release update without uninstall. No real saved
  company session was used to certify cross-version session continuity.
- Signed-release anonymous official-login scroll, password-focus and cancel
  checks passed at both normal and compact sizes, without credentials or sends.

## Delivery And Limits

Artifact `Harmat-CRM-Android-1.0.1.apk`, SHA256:
`a4542057600c76bec484c32dee7f19e110316a41a77e6568935edc978c0976ce`.
Private outputs: outputs/2026-11-09-crm-android-v1.0.1. Signing material remains
outside Git under LOCALAPPDATA and is never copied to the NAS.

Recommended NAS version folder: Harmat-CRM/2026-11-09/1.0.1-test. Keep the original
1.0.0 folder/hash unchanged. Only signed APK and installation notes may be copied.
Rollback of a deployed phone app can require uninstall/re-login because Android
normally rejects a lower version code. Browser CRM remains available; never roll
back the website database to recover a phone application.

Real company login/cookie handoff, server revocation, physical enrollment changes
and physical/API28-29 behavior remain user acceptance tasks. No live business form
or appointment was submitted. Network/display/security cleanup and final delivery
receipt are kept in ignored output, not public customer-data exports.

Final delivery:459651bytes, same SHA256 at local output, Downloads and NAS
Harmat-CRM/2026-11-09/1.0.1-test. Original1.0.0 NAS hash6a3f9491... preserved.
Temporary emulator PIN/fingerprint removed (zero enrollment/no lock credential),
default1080x2400/420dpi/font1.0 and network settings restored; emulator stopped.
Existing CRM PHP SHA207cb459... unchanged. No physical device was operated.
