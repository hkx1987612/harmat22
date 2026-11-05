# Harmat CRM Android Preview

Independent, offline Android UX prototype for internal sales staff.
This is **not** a production CRM client and does not synchronize real records.

## Scope

- Daily traffic table with separate website/walk-in enquiries and accurate fixture totals.
- Clients: search, source filters, read-only detail view.
- Follow-ups: newest contact first and status filters, without legacy overdue-task clutter.
- Synthetic apartment-to-client navigation, no real inventory or prices.
- Chinese/Hungarian interface, saved language choice, system-font scaling.
- Native Android views; no embedded website, JavaScript bridge, API key, or customer export.

Every screen displays a demo-data badge. Fixture IDs use `DEMO-*` and `D-*`;
email addresses use the reserved `example.invalid` domain. Fixture dates are
explicitly labeled; they must not be presented as today's live numbers.

## Isolation

- Package `hu.harmat22.crm.preview` does not replace the older `hu.harmat22.admin` app.
- No INTERNET permission, backend credentials, login flow, exports, dial intents, or write API.
- Backup/device-transfer exclusions are configured. Only the UI language is persisted.
- No website, CRM PHP, quote, appointment, database, or advertising deployment.
- The app icon is copied unaltered from the existing Harmat Android project's official icon.

## Build

Prerequisites: JDK 17, Gradle 8.10.2, Android SDK/build tools for API 35.
Set `ANDROID_HOME`/`ANDROID_SDK_ROOT` or an untracked `local.properties`.

```powershell
gradle :app:assembleDebug :app:lintDebug --offline --console=plain
javac -encoding UTF-8 -d app/build/model-tests app/src/main/java/hu/harmat22/crm/preview/DemoData.java tests/DemoDataTest.java
java -cp app/build/model-tests hu.harmat22.crm.preview.DemoDataTest
adb -s emulator-5554 install -r app/build/outputs/apk/debug/app-debug.apk
./tests/Smoke.ps1 -OutputDirectory C:/path/to/ignored/qa
./tests/Smoke.ps1 -Compact -OutputDirectory C:/path/to/ignored/qa-compact
```

On this workstation, JDK 17.0.19 Windows AF_UNIX loopback initialization failed.
The successful build used a **process-local** `JAVA_TOOL_OPTIONS` setting of
`-Djdk.net.unixdomain.tmpdir=<absolute-project-path>/build/no-unix-sockets`, with
that directory intentionally absent. OpenJDK's pipe listener falls back to TCP
when the Unix socket bind fails. No firewall or system-wide Java settings were
changed. Do not create that directory. See the
[OpenJDK PipeImpl source](https://github.com/openjdk/jdk17u/blob/master/src/java.base/windows/classes/sun/nio/ch/PipeImpl.java).

Minimum declared Android version: 8.0/API 26. Actual emulator verification is on
API 35; older physical devices still need separate acceptance testing. Debug
APK is for local evaluation, not Play Store distribution or production signing.
The compact smoke run temporarily uses a 360dp-wide display. Both runs restore
emulator font and keyboard settings; the compact run also restores display
size/density. Taps and scrolls use bounds from UI Automator XML, not screenshots.

## Before real-data integration

1. Confirm the mobile screen/workflow scope with the user.
2. Back up the current backend and add a separately reviewed read-only API or
   equivalent authenticated adapter. Reuse sales-role/object-level access checks;
   do not reuse public tracking/offer endpoints or embed admin credentials.
3. Test login/logout/session expiry and account isolation with synthetic records.
   Never cache customer lists or tokens in plaintext or application backups.
4. Fix and test the known whole-option concurrent-save problem for
   `harmat_broker_leads_v1` and `harmat_sales_deals_v1` across **all** writers,
   including the legal plugin, before enabling any mobile writes.
5. Then stage narrowly scoped follow-up editing, conflict feedback, and retry
   handling. Quotes and appointments stay out of scope unless separately authorized.

Do not add INTERNET permission and load the live sales portal as a shortcut to
read-only mode: hiding buttons in a WebView is not an authorization boundary.
