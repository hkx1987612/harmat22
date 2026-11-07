# Offer attribution across apartment details

Date: 2026-11-07, Europe/Budapest.

## Authorization and scope

The user approved backing up, testing and deploying a narrow fix for campaign
metadata lost after navigation into apartment details. Budgets, campaign
configuration, quote generation, appointment handling and customer records are
out of scope. The separate YouTube subscriber discussion has not authorized a
new spend or activation; no ad changes were made.

Only `wp-mu-plugins/harmat-unified-offer-modal.php` is deployed, version 1.0.9.
Apart from its version, only the attribution block between `fillTracking` and
`open` changes. Form markup, CSS, required fields, selected apartment, date/time
rules, manual source choices, validation, fetch/submission, success tracking,
thank-you navigation, REST/PDF/quote/mail/booking implementations remain exact.

## Behavior

- Capture entry metadata at public-page initialization, without waiting for the
  visitor to open the modal. Fill it again at the existing open/submit points.
- Preserve the first campaign landing/referrer through untagged internal pages;
  `source_url` remains the actual inquiry page.
- A new tagged campaign or Google click-marker entry replaces all prior campaign
  fields rather than mixing sources. No source or campaign is invented.
- Only the current policy's explicit marketing-cookie consent permits the new
  first-party session-storage record `harmatOfferAttributionV1`.
- Session storage is tab-scoped with 30-minute inactivity expiry, refreshed on
  active pages. Refusing/revoking consent clears only this owned record. Existing
  legacy storage behavior is not expanded or refactored by this patch.
- Stored entry URLs are same-origin, without credentials/fragments/unrelated
  query parameters; only the five UTM names and three Google click-marker names
  are retained, with bounded values/URL length. Referrer queries are not stored.
- Corrupt, expired, future-dated, cross-origin or unavailable storage fails back
  to the original current-page metadata without blocking the form.
- No additional external requests, conversion uploads, customer-data exports,
  automatic CRM status changes or historical attribution backfills.

## Backup and release

- Reviewed Git baseline: `9608d9c5789e12ac20dfa6147d0a1c1c6e57b3b0`.
- Live target: `/home/harmath2/public_html/wp-content/mu-plugins/harmat-unified-offer-modal.php`.
- Verified private backup, outside the public root:
  `/home/harmath2/codex-backups/offer-attribution-6a90b312ca6242c28494b0124a1b16cc`.
- Original raw SHA256:
  `295ac58b0058a99a10bbc9cbf536f966125c7a64d149c22a911b0d52135c2897`.
- Released raw SHA256:
  `32b7c2d51e37e3f9f173cc3425026c485ae5530ba7d07436045af9b69df45224`.
- Original metadata retained; private temporary PHP lint before same-directory
  atomic replacement; live lint and WordPress cache purge confirmed.
- Release checks passed all eight public routes, native form/SEO/content/shell
  parity, 35 native offer and 17 native antispam checks. Protected posts,
  metadata, Harmat options and unrelated custom source hashes did not change.
- The one derived `_elementor_element_cache` metadata key is excluded from the
  protected comparison, not any customer/apartment/quote/appointment data.
- A full database restore is neither needed nor allowed for this source repair.

Independent post-browser verification passed and is recorded locally in
`verified.json`, including all eight contracts and protected-state parity.

## Tests and actual browser checks

- 39 offline tests of the exact production block: V4/Search/Facebook/portal,
  multiple untagged hops, new campaigns and click-only entries, inactivity,
  consent grant/revocation, invalid data and denied storage; all pass.
- Full embedded-JS syntax and PHP lint pass. Exact source-boundary comparison
  proves every non-attribution UI and submission line is unchanged.
- 10 in-memory release/recovery tests pass: staging, cache and ownership/race
  failures stop or restore only owned source, never customer/business data.
- Existing 26 appointment-integrity and 727 antispam checks pass, isolated with
  no WordPress dispatch, network, database mutation or mail.
- Real Chrome production navigation and empty native modal open/close verified
  at 1280 CSS desktop and 390 CSS mobile, with Hungarian/Chinese switching.
  Date/contact fields remain empty, privacy unchecked and no horizontal overflow.
- Production hidden metadata is masked by the browser inspection tool; boolean
  comparisons against that masked DOM are not counted as attribution proof.
- A loopback-only synthetic fixture runs the exact production attribution block
  using real browser session storage and UI consent/navigation. All six visible
  cross-page assertions pass. The second intended narrow-fixture check actually
  retained a 1920 CSS desktop viewport, so it is not counted as mobile metadata
  proof; the separate native production modal checks did use 390 CSS. Its CSP blocks external
  requests and form submission; it contains no production data or endpoints.
- Synthetic test settings were restored and the loopback server stopped. Final
  viewport-reset/tab-cleanup/Ads-page-retention call was rejected; those cleanup
  outcomes cannot be certified. Production language was restored to Hungarian
  before the isolated fixture. The existing browser zoom was not changed.
- Desktop production screenshot saved locally. Mobile capture is partial and
  the isolated-proof capture timed out; no physical-device or complete mobile
  screenshot certification is claimed. No live inquiry/appointment was sent.

## Recovery and records

Source-only recovery, with hash/ownership checks and no database restore:

```powershell
python server-config/maintenance/2026-11-07-deploy-offer-attribution.py --rollback /home/harmath2/codex-backups/offer-attribution-6a90b312ca6242c28494b0124a1b16cc
```

Independent remote verification:

```powershell
python server-config/maintenance/2026-11-07-deploy-offer-attribution.py --verify /home/harmath2/codex-backups/offer-attribution-6a90b312ca6242c28494b0124a1b16cc
```

Keep backup manifests, public snapshots, synthetic receipts and screenshots only
under ignored `outputs/2026-11-07-offer-attribution/` or the private server backup.
No customer data, account identifiers, session cookies or private screenshots
are part of the maintenance commit.
