# Harmat Messenger - receive-only staging prototype

## Latest Hosting Direction

The user confirms nogakft remains in use and asks us to choose the best path. Current recommendation/direction is to reuse that existing customer-service environment with an isolated Facebook entry and one authoritative public-only website data source. Do NOT create Cloudflare/Render services or insist on another model subscription before inspecting the existing setup. The scoped homepage loader observation below is not a claim that the nogakft server/service stopped; the user was explicitly given that correction. Existing backend/admin/project entry and access details are still missing; requested only the management/project URL or an authenticated browser page, not passwords/tokens/customer chats. No nogakft service change or deployment has occurred. Production message-processing grants/current terms and end-to-end testing remain required.

This directory is an isolated development prototype, not a deployed service or a functioning AI chatbot. It now includes a receive-only Worker handler, signed-webhook validation and a transactional SQLite-backed Durable Object adapter, tested in local Miniflare/workerd. It has no model client, message sender, production credentials, queue or live property reader. It does not call WordPress, create a CRM lead or modify an advertisement. No local test server is left running.

The user accepted the proposed first-month USD20 operating-budget direction, excluding applicable tax/FX, on October 15, 2026, then authorized continued work and publication contingent on successful testing. This does not accept new provider terms, recurring fees or specific customer-data permissions. No provider payment or subscription has been made. Cloudflare login is currently blocked by a security check/login403; the user also reports Incognito fails. Do not repeat login attempts or alter the website/DNS/security. Cloudflare is optional; Render/independent hosting was discussed, not selected or purchased. The Worker adapter is provider-specific and would need replacement/retesting for another host.

Latest user clarification: the existing website customer-service backend is hosted on the nogakft.hu server. Prioritize read-only inspection/reuse of that existing service before proposing another hosting/model subscription. Repository `harmat-performance-guard.php` contains an external widget reference to `https://jiankong.nogakft.hu/harmat22-ai/widget.js`, but actual live loader precedence/backend/data flow are not verified. Do not assume the local assistant plugin is the sole active service, or that this external record proves current real-time sync. The intended accuracy improvement is one authoritative public-only property source shared by website/Facebook, not merely co-hosting or copying an old snapshot. Do not reuse Harmat server credentials against another host without verified access details.

Later read-only check narrows this: the completed current homepage DOM has `harmat-local-assistant-style`/`harmat-local-assistant-script` and no jiankong external widget script. Repository local plugin explicitly removes `harmat_perf_ai_customer_assistant` at footer priority130. This proves this inspected page uses the local loader rather than the old external widget, not the existence/currentness of a nogakft backend or every page/client. Reconcile any still-running nogakft service before using its knowledge/data. No chat question was submitted.

## Run Offline Checks

With Node24 and pnpm11 available, install the pinned local test dependencies from this directory without package lifecycle scripts:

```powershell
pnpm install --frozen-lockfile --ignore-scripts
node --test *.test.mjs
```

From the shared repository root, also run the original copy checks:

```powershell
python -B server-config/maintenance/2026-10-15-test-facebook-messenger-copy.py
```

The policy preview uses the disabled Hungarian seed at `server-config/maintenance/2026-10-15-facebook-messenger-copy.json`. There is no model generation. Synthetic input is mapped to a small allowed topic set and static public-copy previews. Every result remains dry-run and not sendable, including when supplied with a misleading enable flag or untrusted input.

Current result:89 Node tests (20 policy,43 safety-state,17 ingress,9 runtime including its parent test) plus10 original Python copy checks pass. The runtime checks use the real local workerd/SQLite engine, including simultaneous duplicate requests,30 concurrent budget reservations, persistent pause across emulator restart, corruption fail-closed and a combined-capacity fixture below2MB. They do not prove live Meta delivery, model accuracy, native Hungarian quality, legal compliance, end-to-end takeover or production latency. Never connect this preview directly to an outgoing Messenger API.

Initial dependency audit found17 known issues in the local emulator's transitive dependencies, not the website. Pinned compatible patched `undici7.29.1` and `sharp0.35.5` through the local `pnpm-workspace.yaml`, regenerated the lockfile with scripts disabled, reran all89 Node tests and audit: no known vulnerabilities reported at this checkpoint. This is not a guarantee of future vulnerability absence. No global or website dependency changed. Disabled staging TOML was separately parsed/checked.

## Receive-Only Boundary

- `ingress.mjs` verifies SHA256-HMAC over bounded raw wire bytes, before UTF-8/JSON decoding. Body reading is bounded at64KiB/two seconds; batches at50 events. Unsupported public changes/standby notifications are ignored, not imported.
- Only event/thread HMAC pseudonyms, trusted scalar metadata and safety counters reach the private durable store. No raw sender/recipient IDs, text, contacts, attachments or verification/app secrets are stored or logged by application code. Pseudonyms can still be personal data; this is not a legal anonymization claim.
- `safety-state.mjs` keeps receipt-based deduplication,8/thread and100/page per fixed UTC hour, persistent manual pauses and a default-stopped global switch. Fixed buckets allow boundary bursts. Every outgoing echo conservatively pauses; distinguishing an eventual bot's own echoes and actual Meta handover must be implemented/tested before sending.
- Dedup records are pruned lazily on a later event after24h; no timed deletion/alarm is implemented. Pauses never expire automatically. The bounded reservation ledger is retained and stops at5000 entries. Retention/secure operator controls and any cleanup require design/privacy review before production.
- `worker.mjs` acknowledges supported events only after the durable metadata transaction. Corrupt saved state returns failure without resetting counters. Private budget reservations are serialized, use server-derived UTC month, have no refunds and cap the future model allowance atUSD15/month. This is NOT a cap on hosting, tax, FX, other account usage or a provider invoice. No money was spent by these synthetic tests.
- `wrangler.toml` is a disabled STAGING template: no website route/account ID/secret, workers.dev/previews OFF, verification/receiving/data-processing OFF. Durable state starts stopped. There is no sending code even if an invented SEND flag is provided.
- `worker-fixture.mjs` exposes private test-only seed/snapshot helpers to the emulator. NEVER deploy it. The actual configured entry is `worker.mjs`, which does not import the fixture and exposes no public admin/resume/send/reservation endpoint.

No persistent raw-message queue exists. This receive-only stage cannot later replay saved customer questions or generate replies from them. Do not enable it as a substitute for a tested live messaging pipeline. Do not rotate the pseudonym key/change the fixed durable-object identity without a reviewed pause/budget/dedup migration. Official platform/transport logs and provider backups are separate from application logging and require account-specific review.

## Current Setup Handoff

The human completed Meta developer registration and reported completion. Independent navigation to My Apps initially showed the application-list interface and an empty list, confirming usable developer access.

The human then explicitly accepted the exact final Create App agreement/action: `Harmat Lakopark Asszisztens` (native display retains Hungarian accents), official sales email, Messenger customer-interaction use case only, and no business portfolio. No existing portfolio was offered. The agent freshly checked the overview and clicked Create App ONCE. Meta requested password reauthentication, which the human completed without agent credential entry. The original tab subsequently reached the exact project-named app dashboard, explicitly Unpublished. Creation succeeded; never create a second app to resume this workflow. Its private identifier and screenshots remain outside Git.

Read-only Messenger API settings show blank callback/verification fields and no Facebook Page connected. The permissions table includes use-case defaults marked ready for testing, not proof of a user/Page grant or live access. No optional permission was added, token generated, webhook saved, business bound, review submitted or app published. The current native settings say development messaging is limited to app roles and review is required for live data. This account-specific gate takes precedence over any inference from the earlier general overview; do not attempt ordinary-customer messaging before eligibility and review are verified. The exact API settings tab is held for continuation, not a password prompt.

Do not create duplicate developer accounts/apps, choose an unrelated business or switch to another user's account to bypass verification. Public replies must still use Harmat Lakopark branding; backend verification must use truthful information supplied by the account owner. Do not put private account identifiers, screenshots or contact/identity details in Git.

## Production Gates

- [x] User-confirmed private Messenger and message-ad scope, public comments excluded.
- [x] Selected website-KB topic seed and offline structural checks; historical price snapshots omitted.
- [x] User accepted the proposed USD20 first-month operating-budget direction for test preparation.
- [x] Human completed developer registration; agent verified My Apps and initially empty application list.
- [x] Human confirmed the exact Create App terms/action; agent clicked Create App once.
- [x] Human completed Meta password reauthentication; agent verified the created project-named app is Unpublished and Messenger remains unconnected.
- [x] Conditional instruction to continue testing and publish only after successful verification; this is not an access/terms grant.
- [x] Local receive-only signature/dedup/pause/rate/budget and actual workerd/SQLite transaction tests; all sending remains absent.
- [ ] Verify current Messenger permissions, app mode/review requirements, applicable message window and staff takeover events.
- [ ] Human confirms actual Cloudflare/OpenAI account terms, billing/tax/region and payment details; no unapproved recurring upgrade.
- [ ] Specific message-processing/data-flow approval and privacy notice review; no historical Inbox/CRM/CV export.
- [ ] Working hosting login, approved isolated deployment and secure secrets, timed retention, bounded durable message queue/retries and safe outgoing acknowledgement recovery.
- [ ] Real model client with atomic worst-case cost reservation, bounded/redacted prompts/output and independent billing controls; the local ledger alone is not an actual bill cap.
- [ ] Grounded Hungarian model tests with synthetic/public data; freshness checks before any direct price/availability reply.
- [ ] Verified staff takeover and intentional resume; do not claim an actual transfer based only on a routing decision.
- [ ] One specifically authorized test conversation and delivery/failure/duplicate/takeover checks before a separately approved limited launch.

No price/availability sync, autonomous quote/booking, CRM write, public comment reply, recruitment eligibility decision or marketing follow-up is enabled. The existing public website, its security checks and all advertising budgets remain unchanged. Store any future provider secrets only through the approved secure account workflow; never paste them into chat or commit them. Local environment/Worker secret files are ignored in this directory as a precaution, not created or populated.

See `server-config/maintenance/2026-10-15-facebook-messenger-plan.md` for the cost assumptions, opened primary documentation and earlier native-interface audit. Current website stable tag remains `stable-2026-10-14-appointment-source-current`.
