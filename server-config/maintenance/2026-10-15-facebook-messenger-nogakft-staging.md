# Nogakft Messenger continuation - not launched

## Latest Instruction and State

User explicitly authorized continuing Facebook AI integration, testing before publication and preserving the website/quote/appointment functions. Shared main started clean at `3b7c680`, pulled up to date. Astra coordinated; three bounded Sol workers implemented only isolated Python components, parent reviewed/composed/tested them and closed all three. The pre-existing network-maintenance task was reused with the user's earlier explicit authorization, then returned to idle; never archive/close that existing task.

During the component/diagnostic checkpoint, NO ordinary-customer launch, app publication, Page grant/token, webhook subscription, model call, sender, valid inquiry, appointment or CRM change occurred. Subsequent separately approved Page association is now COMPLETE as recorded in `2026-10-15-facebook-messenger-page-binding.md`; no token/subscription/activation was added. No website PHP/config or existing nogakft app/widget/service/proxy was edited. The current production source tag remains `stable-2026-10-15-messenger-public-inventory-current`; this is not a chatbot production release.

## Components and Verification

All new runtime files remain under `server-config/messenger-prototype/nogakft/`:

- `inventory_reader.py`: one fixed anonymous HTTPS GET to the approved public feed; no proxy environment, redirects, credentials, cache or stale/default fallback. Strict duplicate-key/schema/public-field/canonical-URL/code/type/numeric validation, max128KiB/500 units, UTC age180seconds/future60seconds. Prices can be null, never inferred. Three-second socket-operation timeout is NOT a hard DNS/total deadline. Empty valid inventory is allowed; count124 is not hardcoded business logic.
- `ingress.py`: pure receive-only adapter, no listener. Exact bool gates default off; raw-byte SHA256 HMAC before strict UTF8/JSON;64KiB/20 entries/50 events; configured Page only, HMAC pseudonyms only in existing SQLite store. Staff echoes pause before incoming messages; ACK only after commit. Host must separately enforce bounded/timed raw reads. It does not retain/replay text, generate replies or spend money.
- `reply_preview.py`: deterministic NONSENDABLE Hungarian grounding harness, not a model or general classifier. Pins the reviewed public seed; protects human/recruitment/finance/sensitive-data/booking routes before hints. Only exact reviewed phrases or one exact user-provided unit in a newly validated fresh public snapshot can render. No nearest-unit guessing, hidden-price inference, reservation guarantee or actual handover. Coarse keyword rules are not a complete PII/language detector.
- `test_messenger_pipeline.py`: synthetic composition only, not a production queue or end-to-end Meta integration. Default-stop, signature rejection, duplicate/no-second-preview, persistent staff pause, source failure, metadata-only persistence and corrupt-store/no-ACK cases.

Parent independently ran148 local tests with ResourceWarnings treated as errors:30 metadata,42 ingress,31 inventory,37 reply preview and8 synthetic composition. All pass; no processes/listeners/test databases left running. Earlier89 Node/10-copy tests are separate historical checks, not rerun or included in148. Source changes do not claim a working live AI.

```text
cd server-config/messenger-prototype/nogakft
python -B -W error::ResourceWarning -m unittest discover -v
```

A separate explicit anonymous read on A computer succeeds with124 units/nine item fields. No inventory/customer dataset exported to Git.

## Private Host Preflight

Read-only fixed-known-host SSH reused the existing protected access location; no credentials printed/copied or new access created. Existing `harmat22-ai.service` active, local3022 health200/version1.3/providerlocal. Audited existing app/widget/unit/environment/drop-in/proxy hashes, available port3024 and resources. This is not permission to replace them or enable external model billing.

Private0700/0600 successful fixture backup:
`/root/harmat-messenger-backups/harmat-messenger-reader-970947b4bbaf4341abef7f33522a9cc9`.
Contains only the two reviewed reader/test files, check runner, manifest and aggregate result. Staged compile/formal private rename and31 tests pass on actual Python3.11.6. It is OUTSIDE all webroots and NOT an installed service. Existing source hashes and local health unchanged afterward. Initial existing shared-backup-parent mode refusal stopped before writes; a subsequent exclusive-file mode mismatch left an incomplete private checkpoint, not a partial service deployment. Neither changed existing permissions/services. The final successful fixture result is not a claim the actual remote GET succeeded.

Actual host public read returns `UNAVAILABLE_FAIL_CLOSED`. One read-only diagnostic at3seconds and one explicitly longer8-second diagnostic both report TimeoutError before HTTP status. No alternate endpoint, proxy, UA/IP substitution, TLS bypass, repeated polling or protected old API call. Existing public health GET with bare urllib separately returns403; do not label this as service-down or use browser impersonation to bypass it. The local health remains200.

Authorized network-task diagnosis independently confirms the TCP443 connection timeout. The host has a harmat22.hu hosts entry matching the A-computer DNS answer, not proven obsolete; resolution itself took2ms. Traffic uses ordinary eth0, no Tailscale exit/related proxy, only IPv4 returned/no IPv6 default. Server conventional DNS timed out in its bounded check but is NOT the cause of the already-resolved TCP timeout. Evidence does not establish whether source egress, destination firewall or transit drops the connection. Do not edit DNS/proxies/firewalls or API validation based on a guess. Next inspect relevant connection/firewall logs with the proper host authorization; no network fix has been applied.

Tools: `2026-10-15-audit-messenger-host.py` and `2026-10-15-test-messenger-reader-host.py`. Operator must set `HARMAT_MESSENGER_SSH_HOST` to the already approved private host from the protected network-maintenance access record; the private address is deliberately not committed. Known-host rejection remains mandatory. Default fixture mode is offline; explicit private-stage switch creates a new private directory only. Future local health audits omit the separately observed public403 instead of repeatedly probing it. Do not call the fixture stage a production deploy or keep re-running the known failing GET. `--inspect` is a one-off read-only diagnostic; no automatic retry/remediation. Raw configuration/customer data remain outside Git.

## Meta and Launch Gates

The earlier read-only Chrome checkpoint found Development/Unpublished, blank callback/token fields and no Page; current UI explicitly limited development messaging to app roles and required review for live data, with a missing publication privacy URL/disabled Publish button. The subsequently approved binding did not publish the app or configure a callback/token.

User answered the specific binding question affirmatively. Exactly one Harmat Lakopark Page is now associated; seven native configuration checks and a148-test local rerun pass. No webhook fields, token or customer activation. Association approval is no longer pending; do not repeat the same request. The ordinary-login popup closed during continuation and the main table verified success; no exhaustive granted-scope screen was visible. Future scope/terms expansion must be assessed separately. Full current proof and remaining gates: `2026-10-15-facebook-messenger-page-binding.md`. Settings tab retained, private IDs/screenshots excluded.

Remaining gates, all must be met before launch:

1. Resolve actual source-to-website connectivity without weakening website quote/appointment/security checks; rerun one successful bounded public-only fetch, freshness/masking/grounding and failure tests from intended host.
2. DONE: user-confirmed single Page association. Future token/subscription/data-flow steps require their own secure implementation and review of any newly displayed scope/terms; no advertising, public comments or unrelated Pages.
3. Review truthful privacy/data flow, including intended host location, retention, message handling and eventual model provider. Fill an appropriate published notice only after review; do not assert an old website notice covers new processing automatically.
4. Implement/test the actual bounded HTTP transport/queue/retention, secure credential workflow, real model client and atomic worst-case cost controls within the approved first-month operating direction. There is NO production model/API-key connection, sender or complete invoice cap in this Python prototype. Do not silently reuse old WordPress shared CRM credentials or old nogakft defaults.
5. Specifically authorize one role-based real test conversation; verify receipt/reply/failure/duplicates/actual staff takeover/resume and quote/appointment non-interference, then complete current Meta review requirements. Never send to real prospects just to test. Only then consider limited publication under the user's conditional instruction.

Official background documentation opened during planning: [OpenAI structured outputs](https://developers.openai.com/api/docs/guides/structured-outputs?api-mode=responses), [data controls](https://developers.openai.com/api/docs/guides/your-data), [previously proposed model](https://developers.openai.com/api/docs/models/gpt-5.4-mini). No external model request/key access was made. Meta public web-tool documentation retrieval returned429; the current authenticated application UI is the observed launch-gate evidence, not third-party tutorials or a bypassed document.

## Final Website Guard

After component work, the independent public-feed release verification passes124 units/nine fields/native-authority equality/GET-only POST404 and ALL protected property/offer/CRM hashes. Existing anti-spam released hash/lint/17 native callback checks and historical protected-record hashes pass. Nine key public HTTP pages return200 with no fatal markers. No positive booking/inquiry/mail test, paid click or new browser/mobile visual pass was performed. These checks are not inbox delivery proof or a claim of complete live Messenger/appointment end-to-end testing.
