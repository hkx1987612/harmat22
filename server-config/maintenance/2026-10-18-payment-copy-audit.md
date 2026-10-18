# Payment Copy Audit - 2026-10-18

## Scope And Authority

Read-only audit requested by the user. No production source/content/configuration,
cache, quote, contract, CRM, payment record or appointment edit. No form submission,
mail, live assistant message or customer-record query. Public HTML snapshots and
generated evidence remain ignored under `outputs/2026-10-18-payment-audit/`.

Approved policy, superseding the earlier May31 installment discussion:

1. Full apartment price by 2026-12-31: 8% apartment-price discount.
2. 50% by 2026-12-31 and remaining 50% by 2027-06-30: 4% apartment-price discount.
3. Construction milestones 25% deposit/downpayment, 25% topping-out, 25% interior
   fitout, 20% key-collection notice, 5% handover: no discount, no fixed dates.

December31 and June30 are valid deadlines for the first two choices. A 10% deposit
plus 15% advance is a valid split of the first 25%, not an arithmetic error. Tax
percentages and archive/legal/publication dates are not payment discounts/deadlines.

## Findings

### Public Property Copy Is Incomplete And Uses Older Milestone Wording

Actual IAB inspection of `https://harmat22.hu/property/a1-1-l2/` confirms the shared
Elementor4214 payment section is displayed after scrolling/entrance animation.
Initial accessibility omission was not proof of absence. HU and Chinese show:
10% deposit +15% advance, 25% structure completion, 25% interior work commencement,
20% apartment-completion notification, 5% key handover.

No fixed payment date occurs in this section, and the total is 100%. However:

- It does not present the full-payment 8% and 50/50 4% alternatives.
- It does not explicitly state that milestone installments have no discount.
- The 20% wording is apartment-completion notification rather than the newly
  confirmed key-collection notification. Chinese faithfully repeats the old HU
  meaning, so changing the Chinese dictionary alone would create a mismatch.

Browser language was restored to HU. No quote dialog was submitted or modified.
119 of124 property pages contain byte-equivalent normalized text in shared4214.
Five do not include this payment section: A1-4-L5, A2-4-L5, A3-4-L5, A4-2-L7 and
A4-4-L5. Their absence is a coverage difference, not evidence of a wrong date.

### Private Sales Templates Retain June1 Instead Of June30

Current live sales source matches the local source. The protected
`harmat-sales-manager.php` still contains:

- `auto_payment_plan_items`, lines15610-15612: project year2026 and next-year
  June1, used by the `half_half` branch around15661.
- `payment_plan_template_labels`, around15854-15868: HU/Chinese final50% label
  says 2027.06.01.
- Private `sales_portal_script`, around17484/17518/17575: same label/date in both
  languages, including `nextJuneDueDate = "2027-06-01"`.

This is a genuine template/generation default conflict with the approved June30
deadline, not a finding that all saved or sent quotes contain the error. Existing
quotes/contracts/customer records were not inspected. Per the user's explicit
boundary, this module and all records remain untouched. The 25%+25% upfront split
of the 50/50 option and 25%+75% full-payment split are not independently errors.
No automatic 8%/4% discount calculation is certified by this copy-only audit.

### Assistant Promotion Synonyms Omit Approved Alternatives

Isolated genuine assistant routing on the byte-matching current implementation:
18 HU/ZH/EN questions;12 payment/discount variants return all three plans, three
explicit tax variants correctly stay in tax guidance, and three promotion variants
return generic discount guidance without the approved plans:

- HU: `Milyen akciok vannak?` (test uses correct Hungarian accents).
- Chinese: question meaning "Are there special offers?".
- EN: `Are there any promotions?`.

None of the18 answers contains obsolete March31/May31 installment dates. The gap
is routing coverage, not an incorrect percentage promise. Relevant source is the
generic `discount` answer around1377-1382 and narrow payment guard around1687.
Reproduce with `2026-10-18-audit-payment-phrases.php`; synthetic in-memory fixture,
no network/WordPress database/customer events or chat request.

### Financing Page Has Only Generic Guidance

The freshly fetched `/finanszirozas/` page defers payment scheduling to individual
sales discussions. It has no obsolete fixed payment deadline or wrong discount,
but also does not provide the three newly approved alternatives. Potential public
copy improvement, not a mathematical/date conflict.

## Live Source Identity

Read-only PHP CLI file hashing, without WordPress boot or database access:

| Source | SHA256 (normalized LF) |
| --- | --- |
| Assistant | ff702f012ce13c7a7efdb0f25e7306cd5fbbb63cf29a14608645583451392b30 |
| Sales manager | e2339891f8aaafe8eddf3315851479b65c866bb7c72cef6a79e1cfb59a2080cf |
| Chinese dictionary | 7651d634166af48b78084e6b5f6a0f7a0795d30ed2ada2c7d5bd0f24edac7316 |

All three match local. Actual mixed-EOL assistant SHA is
`e520fd3549820c6b77f798f6f3cb2ec2ae28c3bf206054866f9a48de7f4ff46d`.
No file normalized or rewritten on the server.

## Public Attachment Sample And Limitations

Sequential GETs, three explicitly observed public URLs, no private documents:

- Technical description `/wp-content/uploads/2026/06/harmat-lakopark-muszaki-leiras.pdf`:
  nine pages,1,016,398bytes,SHA dbfdfe59a5d31cf77977c6111d82c5cf71601bc037e3b5d7398d691dcecba237.
  Extractable text has no payment deadline/discount; one "payment" match concerns
  technical-change fees, not apartment installments. Pages1/2/8 have no extractable
  text, so no OCR/image-only payment-content clearance is claimed.
- A1-1-L2 and A1-F-L1 public floorplan PDFs: one page each,1,008,553 and1,670,896bytes.
  Extractable text has no payment-date/promotion match. Only91/88 characters are
  extractable; image labels and all other floorplan PDFs are not certified.

`2026-10-18-audit-payment-pdfs.py` reproduces this bounded text sample. Document
link inventory is not equivalent to full attachment/OCR or historical-media audit.
No financial/legal/tax advice or legal policy verification is being provided.

## Coverage And Tests

Completed sitemap-based scan:145/145 public HTML pages (124property and21others),
seven first-party external scripts, zero fetch failures.119property pages contain
the same installment copy; no non-property body or inline script has a payment
percentage match. All returned body date snippets were classified as construction,
project-launch or legal dates, not installment deadlines. The external script date
match is archived construction copy. No old fixed payment deadline was detected
in these public HTML/inline/first-party-script surfaces. This does not certify all
unlinked historical URLs, dynamic states, image text or every attachment.

Inventoried124 public document links (floorplans); sampled two plus the technical
PDF observed in the actual property UI. The full floorplan archive was not fetched
or OCRed.18 isolated assistant questions and6/6 no-network parser tests complete.
Parser tests cover source/body separation, valid-date neutrality, split-payment
neutrality, same-origin/private-route refusal, resources/documents and form count.

Evidence: ignored `00bf396ee81a48878e5a38c1b0a1524a/audit.json`, per-page snapshots,
`assistant-phrases.json` and `pdf-sample.json` below the audit output directory.
Only helpers, this curated report and accurate maintenance notes are shared.
No new native form end-to-end/delivery test is claimed: sources/records unchanged,
and the user's no-quote/no-appointment boundary was maintained throughout.

## Safe Follow-Up (Not Implemented)

1. First approve a public-copy-only change: the shared property payment section,
   financing explanation, matching reviewed Chinese entries and assistant promotion
   synonyms. One authoritative three-plan wording, no invented milestone dates,
   no negotiated discount or tax-policy changes.
2. Fresh exact backup; patch only those owners; PHP/JS validation, HU/Chinese and
   responsive checks, native quote/appointment invariants and no-submit regression.
   Keep existing quote/appointment handlers, values and financial calculations.
3. Treat the private June1 template separately: no repair or record migration until
   the user explicitly includes it in scope. Never bulk rewrite signed contracts,
   historical quotes or customer payment schedules from this audit.

Production stable tag remains `stable-2026-10-18-assistant-ui-payment-current`.

## Subsequent Authorized Implementation Check

Actual IAB financing inspection on the approved follow-up also exposed the legacy
`harmat_audit_financing_html` month-based project milestones, unlike the generic
migrated article in the earlier static snapshot. This is additional runtime/cache
coverage, not evidence that the old private June1 default was changed. Exact live
implementation, preflight failures and final release status are tracked separately
in `2026-10-18-public-payment-copy.md`; the audit above remains historical evidence.
