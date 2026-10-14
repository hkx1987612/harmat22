# Appointment source validation repair

## Verified result

On October 14, 2026, the user requested appointment testing, approved the exact source-encoding repair, and authorized ONE clearly marked TEST inquiry using the official sales contacts. The shared website offer/appointment endpoint now accepts the public Google-search value. Facebook's social-media source was already valid and remains valid. No frontend, ad, budget, security, reminder-policy or existing customer/property edit was made.

The approved TEST returned HTTP 200 with a real private inquiry and CRM reference. Read-only verification proved the test marker, official contact, A1-1-L2, October 20 / 09:00-12:00, decoded Google source and maintenance-only tracking were saved correctly. Both sales and customer mail success timestamps exist, status is `sent`, and neither mail error field is populated. This proves normal `wp_mail` acceptance, not receipt in the destination inbox. Both test messages use `ertekesites@harmat22.hu`; no manual resend or cron invocation was used.

The original 69 inquiry posts / 1,330 protected metadata rows and all 124 properties / 16,084 protected metadata rows retain their exact pre-test fingerprints. Total inquiries are 70 only because of the one authorized TEST. Ownership is proved before excluding that exact test from the original-inquiry comparison; no record was removed or rewritten. The only excluded derived metadata key is `_elementor_element_cache`. Root `error_log` remains absent and there is no old queued/sending/retrying/partial/failed mail backlog.

## Exact code change

- Shared endpoint: `/wp-json/harmat-sales-manager/v1/offer`.
- Only production file changed: `wp-mu-plugins/zz-harmat-public-offer-integrity.php` (existing version 1.0.0).
- Only edit: wrap the whitelist's literal `'Google keres&eacute;s'` in existing `harmat_offer_integrity_message(...)`, as already done for the other localized values.
- Browser sends the decoded accented value. Previously this one source returned `harmat_offer_integrity_bad_source`; the other four sources were unaffected.
- Origin, nonce, date, time, apartment, required-field, duplicate and rate guards are retained. Unified modal and sales-manager source hashes are unchanged.
- Reviewed baseline: `716167cc85d991ad75834235bc358d090f8643cb`.
- Original LF SHA256: `bd869d8fb175c4c25f7a42a6894e68fefb9389274eabdaef70d7f2dd0db7f565`.
- Released LF SHA256: `9c5864a6a38581d06f0f82bf64ec3142b014f0443cab16f7c9a01ff4ebc33ed3`.
- Stable release tag: `stable-2026-10-14-appointment-source-current`.

## Deployment and recovery

Private backup: `/home/harmath2/codex-backups/appointment-google-source-f9bf563b2bde48dd862af1d61d520a70` (directory 0700/files 0600). It holds the exact original, digest and guarded release manifest. Hidden temporary PHP passed production PHP 8.3 CLI lint before atomic replacement; formal file lint/hash, targeted cache clearing and owned staging/lock cleanup passed. Final read-only verification independently passed again.

The first attempt stopped before writes because existing backup-parent 0775 / target 0664 group permissions were stricter than the initial helper allowed. Read-only UID/GID/account inspection proved the account's primary group is private, with no other primary/supplementary members. The helper now permits that existing group-write mode only after proving exact ownership and private membership; world-write, symlinks, foreign groups and unproved membership remain rejected. No server permission or security setting was changed. Successful backup/activation is the later guarded attempt above.

Use configured SSH credentials locally, never in Git. The correct production CLI is `/opt/alt/php83/usr/bin/php`; default SSH `php` is CGI and failed two initial read-only audits. Those failed attempts are not successful QA.

Read-only release verification from the shared repository:

```powershell
python server-config/maintenance/2026-10-14-deploy-appointment-source.py --verify /home/harmath2/codex-backups/appointment-google-source-f9bf563b2bde48dd862af1d61d520a70
```

If an actual regression requires restoring this owned patch, the helper supports `--rollback` with the same exact backup and `--allow-live-write`. It first checks backup originals and that production still equals this release; it refuses to overwrite later/user edits. Rollback stages/lints before replacement, lints afterward and clears targeted caches. Repeat the focused form/key-page checks after any authorized restoration.

Do not deploy this historical one-line patch again. Default `--check` and `--deploy` keep the original HEAD gate and intentionally refuse on later commits. Offline `--self-test` remains repeatable from the release checkout, using pinned original/candidate bytes without SSH. Restore/verify modes do not depend on the original HEAD.

## Test evidence

- 26/26 isolated PHP cases passed: all five sources, unknown/empty source, slots, valid/future/leap/past/malformed dates, nonce, property, cross-site origin and unrelated route/method. Before repair only the Google-source case failed.
- 39/39 in-memory deployment/failure-path fixtures passed, including rollback ownership, concurrent edits, failed lint/purge/rename, backup safety, symlinks and private-group modes.
- 13/13 live negative probes passed after repair. Name/email/phone/privacy are always empty, so these probes create no lead or mail. Includes every source, Facebook-style tracking, bad dates/slot and fresh public nonces from home/contact/property pages. HTTP 400 required-field refusals are expected, not successful appointments.
- Nine post-release HTTP routes returned 200 without PHP-error markers: home, search, sample property, both virtual selectors, gallery, contact, construction journal and thank-you. Thank-you was HTTP-only, not a browser conversion visit.
- Desktop UI: modal opening, property prefill, building/floor/apartment selection, required/privacy/email/past-date errors and unchanged five source controls passed. Actual Facebook Page-to-website organic link opened the same working social-source form; no paid-ad click or native Messenger test message.
- Actual 390x844 Chrome property modal has no horizontal overflow and reachable controls/validation. Additional verified 390px home, search, both selector levels and A1 building pages have scrollWidth 375, rendered assets and working navigation; search A2-4-L5 displays 47.83 m2 / HUF 62,236,650. Cookie-settings and privacy links open normally without saving consent changes. Escape does not close the existing cookie dialog; no cookie code was changed.
- Browser viewport override affected the property-test tab rather than other tabs. Two initial desktop captures named `mobile-*-final.png` are preserved but explicitly excluded from mobile proof. Final `mobile-home-390-verified.png`, `mobile-a1-390-verified.png` and measured route evidence use verified 390px dimensions. Temporary overrides were reset.
- Main site-test Chrome recorded no warning/error. Facebook-manager-profile console also had extension warnings and opaque objects; do not claim its console was completely clean or assign unproved causes. Its form path worked.

The ONE positive inquiry was submitted directly to the normal website REST handler, with public-page nonce and same-site origin, to avoid browser advertising pixels/fake conversions. Browser validation/navigation and positive backend persistence/normal mail execution were verified separately. The actual successful-browser redirect, native Facebook Messenger send/receive, recipient inbox delivery, real Safari/iOS, China networks and a new full 145-page crawl were not tested.

## Safe continuation on computer B

- Pull the released GitHub main and read the latest context/history before any live action. This task is complete; there is no pending submission or upload.
- Offline tests: `php server-config/maintenance/2026-10-14-test-appointment-integrity.php` and the deployment helper's `--self-test`.
- `2026-10-14-audit-appointment.py` performs only source reads/lint and aggregate SELECT queries. `--verify-approved-test` additionally requires this machine's acknowledged ignored one-test state and pre-test evidence; never reconstruct those files or broaden its ownership exclusion on B.
- `2026-10-14-probe-appointment.py` permits only incomplete negative requests. The separate `2026-10-14-test-appointment-once.py` has ALREADY been used under the user's one-test authorization. DO NOT run its send mode again, on either computer; local state is not copied by Git. A future positive test needs fresh explicit authorization and independent live deduplication.
- Private test ID/CRM/marker, nonces, fingerprints' raw rows, screenshots and raw state remain only in ignored `outputs/2026-10-14-appointment-qa/`. No original client content or contacts were exported. Only source, tests and accurate maintenance records are committed.
- Sol prepared the one-line fix/deployment helper; parent independently reviewed/refined its safety gates and executed/verified production. Sol is closed and all deployment/QA processes have finished. Attempted extra browser-tab closing was refused; no successful cleanup of those tabs is claimed.
