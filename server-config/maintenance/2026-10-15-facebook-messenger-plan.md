# Facebook Messenger knowledge-base assistant - proposal only

## Approved Scope and Current State

The user approved private Messenger and message-ad inquiries only, then explicitly requested reuse of the official website customer knowledge base. They selected a low-cost proposal first, with activation only after confirmation. No subscription, payment, external message-processing authorization or production launch is approved by that selection.

Shared main `fa7ad63` was clean and synchronized before work. No website, WordPress, DNS, CRM, mail, advertisement, budget or public-comment automation was changed. Existing website stable tag remains `stable-2026-10-14-appointment-source-current`.

Native Chrome inspection confirmed the Harmat Lakopark Page context, matching official contacts and a message-ad link to that Page. The automation list was empty. The visible template catalog offered Instant Reply, Away Message and Identify Unanswered Messages only. The separate Meta AI Business Assistant showed an administrator chat with prompts about audiences, automation and ad performance; no customer knowledge-base builder was found in these inspected surfaces. This is not a claim that Meta never offers customer AI elsewhere or in other regions.

An initial wrapper tab lost session ownership before input; recovery used a new tab in the same selected Chrome profile, not a different account. Meta's internal asset identifier differs from the public Page identifier; the visible selected Page, official greeting/contacts and Page-linked ad were checked before continuing. The Inbox initially auto-selected an existing conversation. Do not export its contents, profile details, names or identifiers. No message, label, assignment, follow-up or conversation status was deliberately edited. Native read/unread side effects of opening the Inbox were not independently audited. No real-customer test was sent.

Ignored proof: `outputs/2026-10-15-facebook-messenger/native-template-capabilities.png`, showing only the template catalog, not customer conversations. No native automation was saved or enabled. Do not mistake inspecting Create Automation or opening/closing the admin AI panel for a saved bot.

## Website Knowledge Reuse

- Baseline: `wp-plugins/harmat-local-assistant/harmat-local-assistant.php`; the current website assistant is deterministic, not an external LLM.
- Topic reference: `data/harmat_ai_faq_responses.md` under that plugin. This Markdown reference is not the active runtime engine and contains historical price examples/counts/milestones. It must not be imported wholesale as current truth.
- Volatile apartment source: `wp-mu-plugins/zz-harmat-assistant-live-data.php`, using `harmat_sai_property_summary_data`. The live adapter masks hidden prices and takes precedence over the older `data/harmat_apartments_kb.json` fallback. Never upload the older raw apartment snapshot as the Messenger price source or infer a hidden price from area/unit-price arithmetic.
- Public home, search, construction and contact pages were read for context. No current availability total, unit price, date, phase completion percentage, loan eligibility or booking promise is copied into the draft.
- Existing website ask requests have nonce and rate protections and perform analytics writes. Do not call them a side-effect-free feed, remove their checks or reuse the handoff endpoint to create unsolicited CRM leads.
- First stage can answer stable project FAQs and link to current public property/search pages. Direct unit recommendations/prices require an approved, freshness-checked public-only read adapter. Its design, test and any deployment remain future work; no live connection exists now.

Sol produced the explicitly disabled Hungarian copy seed and standard-library tests in this directory. Parent read both, simplified the greeting, independently reran the tests and checked the public contact link. Eight FAQ topics plus honest manual-contact, sensitive-data and recruitment branches are preparation, not a conversational runtime. All sending/data-collection/CRM/booking/external-AI flags remain false. Structural copy tests do not prove Hungarian native-speaker quality, AI accuracy, Meta approval, messaging-window compliance, delivery, staff takeover or sales conversions.

## Recommended Low-Cost Architecture

Proposal: Meta's approved Messenger integration -> isolated Cloudflare Worker -> selected public website knowledge / public-only property reader -> OpenAI API. Keep the operator Inbox for human support. This is a candidate architecture, not a connected or approved service.

Use a separate Worker hostname rather than putting the website behind a new proxy or moving DNS. Do not install a heavy bot/automation stack into the live WordPress process. A bot outage should leave the website and existing manual Inbox usable. Maintain configuration/code in shared Git; secrets, Page tokens and conversations stay outside it.

Minimum launch gates:

1. Check the current Meta app/Page permissions, applicable messaging window and any review requirements. No access token or app permission has been created. Official developer-doc retrieval was rate-limited during this audit, so exact current permissions/approval steps must be verified before implementation/launch; do not bypass access limits.
2. Verify the proposed model can handle Hungarian from realistic synthetic tests before choosing it permanently. The cost illustration uses GPT-5.4 Mini, not a tested model choice.
3. Implement webhook signature verification, event deduplication, bounded queue/retries, per-user and global rate limits, bounded context/output and a durable kill switch. No replies to echoes, unsupported events or public comments.
4. Implement and verify staff takeover: a human request, sensitive/contract/finance question, recruitment topic or staff reply pauses automation for that thread. Only claim an actual transfer/assignment after the platform confirms it. Recruitment routes to its separately approved contact; never screen housing/employment eligibility using sensitive attributes.
5. Keep CRM/booking/property writes and bulk marketing disabled. Do not automatically promise availability, reserve a unit/parking space, issue a binding quote, approve financing or confirm a viewing slot. Use existing public form only when the customer voluntarily follows it.
6. Apply minimal message retention and processor/data-flow disclosure. Obtain specific approval for sending the necessary incoming question/short context to Cloudflare and OpenAI. Do not upload historical conversations, CVs, IDs, payment details or CRM records. Prefer contact-data redaction and bypass external AI for sensitive attachments; do not guarantee perfect free-text anonymization.
7. Check provider terms, actual billing/tax/region settings and approved spending limits at action time. Implement an application-level AI usage guard; a dashboard spending alert is not a demonstrated hard total-invoice cap. Infrastructure overages, taxes, FX, regional processing and separate existing account usage need their own checks.
8. Synthetic/local tests first, then a specifically authorized test conversation, persisted-config/delivery/takeover/duplicate/retry/cost-failure checks, then explicit limited rollout. Do not send tests to real customers or repeat the earlier website TEST inquiry.

## Operating-Cost Illustration

Research date: October 15, 2026. These are public list-price planning figures, not an invoice quote, observed traffic, subscription purchase or approved budget.

| Component | Public price / assumption |
| --- | --- |
| Cloudflare Workers Paid | Minimum USD5/account/month; metered overages are possible |
| GPT-5.4 Mini text | USD0.75 / million input tokens; USD4.50 / million output tokens |
| Per AI reply assumption | 3,000 input tokens including short context/retrieved knowledge, 300 output tokens; standard non-reasoning text request |
| 1,000 replies/month | Model USD3.60 + hosting base USD5 = USD8.60 |
| 4,000 replies/month | Model USD14.40 + hosting base USD5 = USD19.40 |

No paid web search/file-search/voice/image tool is included. No caching discount is assumed. Conversation length, actual tokenization, retries, infrastructure/database/queue use and optional regional processing can change the bill. Tax, currency conversion, initial credit requirements and the user's existing provider-account charges are excluded. These figures cover infrastructure and model only; verify any Meta/integration-specific fees before treating them as an all-in operating budget. One customer can generate many replies; reply counts are not lead/customer counts. A USD10-20/month illustration is plausible only under these assumptions, not guaranteed or approved. A first-month USD20 operating target (excluding applicable tax/FX) can be proposed, but do not activate paid products until the human accepts actual terms and charges.

The selected candidate avoids a separate chatbot SaaS subscription, but requires engineering and Meta access review. Manychat remains an alternative if a hosted workflow proves preferable; its current native AI Knowledge documentation explicitly limits that feature to Instagram, so do not buy it assuming direct Facebook knowledge-base AI. External HTTP integration is available only on certain paid plans and would need separate model/backend billing and a fresh region-specific quote.

Primary sources actually opened:
- [Workers pricing](https://developers.cloudflare.com/workers/platform/pricing/)
- [GPT-5.4 Mini model and pricing](https://developers.openai.com/api/docs/models/gpt-5.4-mini)
- [OpenAI API pricing](https://developers.openai.com/api/docs/pricing)
- [OpenAI data controls](https://developers.openai.com/api/docs/guides/your-data)
- [Manychat AI Knowledge limitations](https://help.manychat.com/hc/en-us/articles/25626595060124-Manychat-AI-Knowledge)
- [Manychat external requests](https://help.manychat.com/hc/en-us/articles/14281285374364-Dev-Tools-External-request)
- [Public project](https://harmat22.hu/), [search](https://harmat22.hu/lakaskereso/), [construction](https://harmat22.hu/epitesi-naplo/), [contact](https://harmat22.hu/elerhetosegeink/)

OpenAI documents no API-training use by default but standard abuse-monitoring retention can apply; `store=false` alone is not a promise of zero provider retention. Regional processing availability/eligibility and surcharge require account-specific review. No production model API call, key request, provider sign-up, payment, permission grant, message export or deployed AI runtime occurred in this phase. Sol is closed. Continue only after the user's response to the cost/architecture proposal.
