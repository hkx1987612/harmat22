# Messenger hosting diagnosis and account handoff

## Current State

User confirmed the original hosting portal is logged in and does not know whether
a company model API account exists. Clean shared main `decef74` pulled current.
No website/server/network/security change, Meta activation, model request,
purchase, new credential, positive booking TEST or shutdown occurred this turn.
The production source tag remains
`stable-2026-10-15-messenger-public-inventory-current`.

## Hosting Inspection

Original connected company Chrome: authenticated service details and existing
wePanel SSO succeeded. Account-level IP Blocker explicitly shows zero blocked IPs.
The end-user Imunify360 interface offers Malware Scanner and Proactive Defense,
not the required server/perimeter firewall logs. Its displayed malicious-file
view says no results; no scan was started and this is not a full security audit.
No CMS, TLS, ModSecurity, SSH, DNS, firewall or other setting was changed.

The user-authorized pre-existing network task performed a bounded read-only
follow-up under Sol. Actual HTTPS egress IPv4 was obtained once using strict TLS,
three-second timeout, max64bytes and no proxy/retry; it is retained only in private
task/browser context, not Git. Traffic uses eth0 and no Tailscale exit node.
That existing task returned idle and was not archived or closed.

Parent made exactly ONE fresh anonymous public-feed connection attempt from the
intended host, using the hash-pinned private reader and unchanged validation:
2026-10-16 05:01:01 UTC / 07:01:01 Europe/Budapest, TimeoutError after3.024seconds,
no HTTP response. No alternate address, proxy, UA, TLS or security bypass. This
revalidates the timeout after the overnight handoff; it does not establish the
blocking side or prove a WordPress/PHP error.

## Support Draft Not Submitted

Support listing explicitly warns that tickets written after April5,2023 are not
shown, so its empty table is NOT proof that no newer ticket exists. Prepared one
new, narrowly scoped Technical Support draft for the correct active hosting
service. It contains only the public endpoint, confirmed source/target network
metadata, exact latest timestamp and bounded diagnosis. No credentials, private
network addresses, customer data or attachments were added.

The draft requests investigation and the narrowest proposed remedy only. It
explicitly forbids global protection disablement, website/DNS edits, restart,
migration or paid work, and requests discussion before configuration changes.
One Send click led to knowledge-base suggestions and an explicit reCAPTCHA
connection failure, with NO success receipt or ticket number. No blind repeat,
CAPTCHA solving, hidden endpoint or alternative-channel bypass followed.
Do not report this ticket as submitted or repeat Send without reconciling state.

The filled form was retained in Chrome and the owner asked to complete the
verification/submission manually and provide the actual ticket number. Its
network identifiers, account identity and screenshot remain outside Git.

## Model Access Check

Network task independently rechecked the running service environment using
`bytes([0])` to split NUL-delimited entries, avoiding nested-escape ambiguity.
`HARMAT22_AI_API_KEY` and `OPENAI_API_KEY` are absent/empty in the running process;
neither is set in the corresponding environment/unit/drop-in files. Source
actually reads the former. Effective model name remains `gpt-5.4-mini`, provider
hostname api.openai.com. Only names/presence booleans/model/provider were returned;
no secret value, length or hash was exported and no model was called.

This proves no usable key in that deployment, NOT that the owner has no API
account elsewhere. Existing local website FAQ behavior does not prove a paid
model account. The proposed model/budget direction is unchanged; no new service
or billing commitment was accepted.

Opened the official API platform read-only. It requires login; no account was
selected, registration/terms accepted, key created or card/credit added. Owner
asked to log in with the intended company-project account only, then let us
inspect existing API access before any credential or payment step. Login tab and
Meta settings retained. Never request a password/API key in chat or put one in
Git. Current source references: [official quickstart](https://developers.openai.com/api/docs/quickstart)
and [unchanged proposed model](https://developers.openai.com/api/docs/models/gpt-5.4-mini).

## Verification and Continuation

Nine key public routes independently return200 with no fatal markers. This is a
bounded read-only HTTP check, not a visual/full-site scan, native form submission
or inbox-delivery proof. No177-test replay was used as a substitute for fixing
the actual transport/model gates.

Resume from the owner's ticket receipt and API login. Reconcile the existing
support draft before any repeat. If provider proposes an access/security change,
review the exact narrow scope and rollback before applying it; do not weaken
website/quote/booking protections. Secure full transport/queue/model/cost/privacy,
actual administrator/tester delivery and current Meta review remain launch gates.
No reminder/automation was created and all local command sessions ended.
