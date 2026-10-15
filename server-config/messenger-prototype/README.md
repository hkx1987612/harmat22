# Harmat Messenger - offline policy prototype

This directory is an isolated development prototype, not a deployed service or a functioning AI chatbot. It has no HTTP listener, model client, message sender, production credentials, webhook verification or persistent conversation state. It does not call WordPress, create a CRM lead or modify an advertisement.

The user accepted proceeding with the proposed first-month USD20 operating budget, excluding applicable tax/FX, on October 15, 2026. Paid account terms, message-processing permissions and production activation still need their own confirmations. No provider payment or subscription has been made in this phase.

## Run Offline Checks

From the shared repository root, with Node 24 and Python available:

```powershell
node --test server-config/messenger-prototype/policy.test.mjs
python -B server-config/maintenance/2026-10-15-test-facebook-messenger-copy.py
```

The policy preview uses the disabled Hungarian seed at `server-config/maintenance/2026-10-15-facebook-messenger-copy.json`. There is no model generation. Synthetic input is mapped to a small allowed topic set and static public-copy previews. Every result remains dry-run and not sendable, including when supplied with a misleading enable flag or untrusted input.

The role of these tests is to check local routing and fail-safe behavior, not to prove Meta delivery, model accuracy, Hungarian native-speaker quality, legal compliance or correct platform takeover. Never connect this preview directly to an outgoing Messenger API.

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
- [ ] Verify current Messenger permissions, app mode/review requirements, applicable message window and staff takeover events.
- [ ] Human confirms actual Cloudflare/OpenAI account terms, billing/tax/region and payment details; no unapproved recurring upgrade.
- [ ] Specific message-processing/data-flow approval and privacy notice review; no historical Inbox/CRM/CV export.
- [ ] Separate HTTPS backend with secret storage, signature verification, durable event deduplication/queue and a kill switch.
- [ ] Atomic AI usage reservation, per-user/global throttles, finite retries and bounded prompts/output; distinguish application model limit from whole-provider invoice limits.
- [ ] Grounded Hungarian model tests with synthetic/public data; freshness checks before any direct price/availability reply.
- [ ] Verified staff takeover and intentional resume; do not claim an actual transfer based only on a routing decision.
- [ ] One specifically authorized test conversation and delivery/failure/duplicate/takeover checks before a separately approved limited launch.

No price/availability sync, autonomous quote/booking, CRM write, public comment reply, recruitment eligibility decision or marketing follow-up is enabled. The existing public website, its security checks and all advertising budgets remain unchanged. Store any future provider secrets only through the approved secure account workflow; never paste them into chat or commit them. Local environment/Worker secret files are ignored in this directory as a precaution, not created or populated.

See `server-config/maintenance/2026-10-15-facebook-messenger-plan.md` for the cost assumptions, opened primary documentation and earlier native-interface audit. Current website stable tag remains `stable-2026-10-14-appointment-source-current`.
