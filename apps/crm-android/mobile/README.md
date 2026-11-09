# Harmat CRM Android - Connected Read Client

Native Android9/API28+ application, package `hu.harmat22.crm.mobile`.
Separate from the existing offline preview and older maintenance app.

## Connection

The application reads the existing authenticated `/sales/` HTML using jsoup,
not a new public/private API. No production PHP, database or permission edits.
It displays only what the signed-in account's existing server-side object/role
checks already return: overview/daily visits, enquiries, client files, sales
deals and apartment inventory. The app has no business POST, edit, delete,
quote/PDF, booking, export or customer-file download action. A phone-side view
does not create a new server read-only role; the account retains its normal
website permissions. Do not describe a hidden WebView button as authorization.

An isolated WebView is used only for the existing first-time account sign-in.
It does not expose the CRM as an alleged read-only website wrapper. Authenticated
cookies move to an in-memory native reader; the WebView is destroyed and its
cookies/cache/storage cleared. Only the exact HTTPS harmat22.hu origin is used;
no SSL-error override, arbitrary URLs, JS bridge, third-party auth or subresource
cookies, or business navigation in the login view. No password is captured by
native code. Initial password changes/security challenges unsupported by this
isolated login should be completed by the user in the official website first.

Native requests are fixed GETs for five allowlisted views, without automatic
redirects, disk response caching or arbitrary query parameters. Existing
`sales_lang` selection uses the portal's normal language-preference behavior.
An expired session or unauthorized role returns to sign-in. An unrecognized
schema fails visibly rather than silently showing stale/demo/zero customer data.
HTML changes can require an app update; this adapter is not a stable API contract.

## Fingerprint And Privacy

- Optional device session saving uses Android Keystore AES256/GCM with an
  auth-per-use Class3 biometric key; each save/decrypt operation is attached to
  the system BiometricPrompt CryptoObject. No custom fingerprint collection.
- App background/screen-off locks, clears decrypted native records/cookie and
  cancels pending reads; outstanding callbacks cannot reopen a locked page.
- New biometric enrollment, key invalidation, missing keys or decryption failure
  require account sign-in again. Biometrics never bypass server session expiry.
- Switching accounts discards the previous local biometric session first.
- No application-managed customer cache, plaintext saved native auth session,
  analytics SDK, cloud backup, device transfer or saved Activity/WebView state.
  Android's WebView can temporarily write login cookies in its private sandbox;
  they are cleared on login completion/cancel and app startup. Do not claim a
  forensic guarantee that the system WebView never writes plaintext internally.
- Production window/dialog screenshots and recent-task previews are protected
  using FLAG_SECURE. The debug synthetic-data mode alone permits QA screenshots.
- Local sign-out removes this device's stored session/key; it does not revoke
  other website sessions. Server role revocation/logout is honored by next read.
- No background polling/notifications, phone contacts, location, microphone,
  camera or external-storage permission. A network connection and an available
  CRM server are required; this does not promise24/7 server availability.

## Build And Test

PowerShell7+, JDK17, Gradle8.10.2, AGP8.7.3, SDK35. HTML parser pinned jsoup1.18.3 from
Maven Central for compatibility; it is not claimed to be the latest release.
Existing offline preview sources are unchanged. Native Android biometric APIs
avoid adding another authentication service or handling raw biometric templates.
The release includes the dependency's MIT notice in `assets/THIRD_PARTY_NOTICES.txt`.

```powershell
php tests/MobileFixtures.php
gradle :mobile:assembleDebug :mobile:assembleRelease :mobile:lintRelease --offline --console=plain
./tests/MobileSmoke.ps1 -OutputDirectory C:/path/to/ignored/qa
./tests/MobileSmoke.ps1 -Compact -OutputDirectory C:/path/to/ignored/qa-compact
./tests/BiometricSmoke.ps1 -OutputDirectory C:/path/to/ignored/qa-biometric
./BuildMobile.ps1
./tests/ReleaseLoginSmoke.ps1 -OutputDirectory C:/path/to/ignored/qa-login
./tests/ReleaseLoginSmoke.ps1 -Compact -OutputDirectory C:/path/to/ignored/qa-login-compact
```

The fixture generator uses the existing offline WP harness and CURRENT portal
renderer, producing20 role/language/view cases, never a production connection.
Debug fixture assets are generated/ignored; release excludes them. Debug package
`.qa` cannot replace the signed user package. Demo intent has no effect in release.
`MobileParserTest.java` tests schema, role output, totals and URL/cookie policy
against those fixtures. Native UI tests require an emulator, never a real phone.
BiometricSmoke assumes a disposable emulator with a synthetic fingerprint already
enrolled (ID1 by default). Restore its original PIN/enrollment after testing.
ReleaseLoginSmoke requires a fresh installed release profile without saved real
sessions; it checks the public login page/scroll/focus/cancel without entering
credentials or submitting a form. The WebView alone owns login-page scrolling.

BuildMobile creates/reuses a private RSA3072 signing identity under the current
Windows user's LOCALAPPDATA/HarmatCRM/Signing. Its password is Windows-DPAPI
protected; neither key nor password enters Git or the APK. Preserve that local
identity for future updates. No Play Store account, fee or publication is used.

Runtime validation on API35 does not certify every Android9+ physical device.
First actual company login and fingerprint enrollment on the user's phone remain
acceptance steps. Do not claim real client records were verified using fixtures.

Official references:
- https://developer.android.com/identity/sign-in/biometric-auth
- https://developer.android.com/privacy-and-security/keystore
- https://jsoup.org/
