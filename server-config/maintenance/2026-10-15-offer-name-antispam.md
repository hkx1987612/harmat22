# Public offer name-URL anti-spam repair

Later user-authorized cleanup supersedes the retained-record checkpoint below: all120 confirmed records are now in recoverable trash with private originals, not permanently purged. Exact completion/recovery: `2026-10-15-offer-spam-cleanup.md`. Anti-spam source/hash and website frontend are unchanged.

## Scope and Evidence

User reported promotional junk in the CRM and explicitly approved a narrow reversible repair: reject website URLs in the name field across the existing offer REST endpoint and legacy CF7 forms, with backup/lint/regression. No frontend, CAPTCHA, IP ban, existing-record deletion or additional positive TEST inquiry is authorized by this work.

Read-only audit at18:16:49UTC on October15 found113 new offer records that day, all with the same Google-share promotional URL pattern in their titles/name fields. There are56 duplicate email/phone groups,112 records in pairs. Form metadata:56 with CF7 ID1002 and57 missing; all113 lack the new offer-REST security metadata. Stored status:56 `sent`,56 `sent-to-provider-sync`, one `created`. These statuses are not independent inbox-delivery evidence. Neither source code in the inspected WordPress code directories nor the two inspected nogakft assistant files explains the provider-sync marker; do not invent its writer or claim a full duplicate-root-cause fix.

Existing sales and offer-integrity sources exactly match reviewed Git after LF normalization, and live PHP lint passes. Current sales REST has honeypot/contact-pair throttling, while legacy CF7 capture does not use that same path. Name URLs are the strong shared signal targeted here, not Chinese text, a Gmail address or a geography.

## Release

- New MU only: `wp-mu-plugins/zz-harmat-public-offer-antispam.php`.
- Stable release tag: `stable-2026-10-15-offer-name-antispam-current`.
- Released LF SHA256: `618fbe18387fa998b4b7bb2739bb58551d2b4c42fc96cc40478941a882682e2e`.
- Exact private backup: `/home/harmath2/codex-backups/offer-name-antispam-4d2e3852703245879109b2cdabcd1f97`, directory0700/files0600; records original target absence, protected source originals, candidate, tests and manifest/results. No customer export in Git.
- `POST /harmat-sales-manager/v1/offer` and CF7 `POST /contact-form-7/v1/contact-forms/{1002,8761}/feedback` are checked before callbacks. Case/trailing-slash variants share that scope. Existing responses/errors are preserved.
- Native verified `wpcf7_spam` hook additionally blocks the two forms before mail; preserves existing spam. CF7's current `get_contact_form` / `get_posted_data` methods and exact filter signature were inspected read-only before integration.
- Only scalar name values with entity-decoded explicit `http://`, `https://` or `www.` markers are rejected. Normal Hungarian/Chinese/English names and message-only URLs are not targeted. Existing malformed-field validation is not replaced.
- No persistent logging, database-writing rule state, model, remote CAPTCHA or external bot service was added. No edits to old sales/integrity sources, WordPress options/content, old leads, prices, availability, CRM workflows, nogakft service, ads, DNS or security configuration.

The first install attempt stopped before any writes because the existing backup parent was not0700. The existing parent was NOT chmodded: it uses the prior guarded directory checks, while this release's unique child remains0700 and files0600. Successful install then verified the backup, linted private candidate/test and same-directory non-PHP stage, ran isolated tests, activated the previously absent formal file, linted/hash-checked it and cleared existing page/object caches. Failures after activation would remove only the hash-owned new file; no customer-data restoration/overwrite.

## Verification

- Sol wrote the two scoped files locally; parent reviewed, added case/trailing-slash coverage and a CLI test-source parameter, independently reran727 isolated checks and local lint. The same727 checks passed on production PHP8.3 without WordPress/network/mail/database bootstrap. Sol closed.
- Formal file hash/lint verified independently after release. Seventeen additional pure-callback checks passed inside the actual WordPress runtime, confirming real hook registration, WP_Error output, legitimate multi-language names and CF7 API compatibility. No REST handler dispatch or mail/lead write in that check.
- Nine public routes returned200 before and after, no critical PHP markers: home, search, sample A1-1-L2, both selector levels, gallery, contact, construction journal and thank-you page. Not a full-site crawl or all-device functional guarantee.
- Protected pre-existing customer/offer records before October15 and all property records/metadata retain exact aggregate hashes, excluding only the established Elementor element-cache key. Rechecked after native callback verification. Today's active incoming records were deliberately not overwritten or deleted.
- Eight real HTTP incomplete-negative cases passed required-field validation: normal Hungarian/Chinese/English names, a legitimate message URL and all five source labels. No valid email/phone/privacy/date was supplied, so no valid inquiry or notification was created. Prior authorized October14 positive TEST was NOT repeated.
- URL-name HTTP probe returned upstream403 HTML before a WordPress JSON result. One diagnostic repeat identified that boundary; no further blocked-payload attempts or security bypass. That is NOT proof the new PHP callback caused the HTTP rejection. Local/native-callback tests provide application-rule proof; CF7 success/mail delivery was not retested.
- Chrome desktop and actual390x843 CSS-pixel mobile form render/fields/five sources/Google-source selection/close passed without horizontal overflow. Initial487px zoom-scaled mobile capture and a first hidden-dialog DOM query were excluded; corrected role-targeted390px evidence is used. No form submission/paid-ad click. Temporary viewport reset.
- Ignored evidence: `outputs/2026-10-15-offer-spam/`, private backup release result and probe/audit reports. Desktop/mobile screenshots are not committed. Existing browser extensions are not site changes.
- Final read-only audit at18:35:40UTC found120 total same-pattern records/60 duplicate pairs, all latest timestamps at or before18:18:32UTC, before the new file's18:24:22UTC staging mtime. Zero new records in the explicit18:27:28-18:35:40UTC sample. Earlier113 was the initial snapshot, not the final count. This quiet short sample is not proof of an ongoing attack being blocked, a promise of zero future spam or a scheduled monitor. Existing120 records remain intact.

## Continuation and Rollback

Do not promise all spam or duplicate records are solved: this rule covers the confirmed scalar-name URL pattern only. The separate existing AI `/lead` -> `/harmat/v1/ai-lead` route remains outside this approved patch, with no known source parity/masking/currentness verification. Any additional channel-wide or deletion/quarantine work needs its own reviewed scope. Do not change the nogakft service under this repair or repeat the earlier valid appointment TEST.

Run `python -B server-config/maintenance/2026-10-15-deploy-offer-antispam.py --verify BACKUP` with the exact private backup above to verify the owned release/native callbacks/protected-data hashes. It does not submit a form. Future legitimate changes to protected records may invalidate that historical comparison; inspect rather than overwrite.

If rollback is necessary and explicitly appropriate, `--rollback BACKUP --allow-live-write` removes only the exact released-hash new MU file, clears caches and checks key routes. A modified/new-owner file is never deleted. No old customer records are restored or purged. Do not rerun deployment over an existing target. Default `--check` is offline only; the installer retains a historical reviewed-HEAD guard and must be freshly reviewed before reuse on a later commit.
