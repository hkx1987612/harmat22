# Internal Public Chinese Display

Independent public-only MU: `../zz-harmat-internal-language.php`.
No server-side content/CRM/property writes, REST route or external translation.

- Default original Hungarian. A small Magyar/Chinese control is non-submitting.
  Chinese loads the local116KB reviewed dictionary only on explicit selection or
  an earlier choice in this tab's sessionStorage. Storage failure keeps manual use.
- October17 menu placement: the same body-child control is positioned beside the
  real non-spacer navigation menu, without changing native header columns. It
  remains visible at the last good position when the native header hides or
  scrolls away. Narrow screens stack only if necessary to avoid the logo;
  unavailable/unsafe anchors use the original visible fallback. No cloned UI,
  permanent animation loop or translation/submission-owner change.
- Exact text/description translations, reversible node identities, incremental
  mutation handling. No broad string substitution or HTML reconstruction.
- Original field names, input values, option values, units, numbers, money, URLs,
  consent values, handlers and selected property data stay authoritative.
  v1.1.0 explicitly localizes ONLY the exact untouched native generated offer
  message plus its auto marker. A captured customer input/change makes that
  textarea permanently dirty for this node, even across runtime recreation;
  its value is never translated. Dirty auto markers are cleared so native
  equality checks cannot overwrite a customer draft that matches a default.
  Numeric auto-property prose and offer summaries use anchored parsers and token
  preservation, not stale translated price snapshots. Unknown prose stays original.
- Native text-dependent quote triggers have a narrow compatibility bridge, only
  for originally matching native triggers that cease matching after translation.
  Native anchor gates still handle popup URLs; no duplicate open or submit owner.
- Public passwordless published page/property GET/HEAD only. Private paths,
  decoded private paths, private queried slugs, admin/CLI/REST/ajax/cron/feeds/search
  and all submissions excluded. Admin preview uses `?h22_language_test=1` while
  the explicit public flag is false; no anonymous preview grant.
- Exact five sibling SHA256 pins/non-symlink checks fail closed to the original
  Hungarian website. Controller is activated last after private/temp/formal lint.
- Ordinary image/PDF/video content, original names/addresses, customer messages,
  private portals and independently multilingual AI chat are not auto-translated.
  Only the native launch button's exact leaf label has a narrow text exception;
  avatar/ARIA state/conversation/input/chat-language controls remain excluded.
  A second narrow exception covers the exact property disclaimer DIV emitted
  directly under HTML on exact public unit routes/single-property bodies, without
  moving it or expanding translation permission to other outside-body nodes.
  This does not create indexable `/zh/` URLs or change canonical/SEO metadata.
- New/changed Hungarian prose requires reviewed dictionary maintenance. There is
  no external provider to fill missing phrases automatically and no new API fee.

## Sources and Tests

Review `marketing.zh-Hans.json`, `legal.zh-Hans.json`, `property.zh-Hans.json`,
`dynamic.zh-Hans.json`, the prior Project-preview dictionary and last-merged
`coverage-fixes.zh-Hans.json`. The last fragment only adds missing exact keys;
existing compiled mappings are preserved, and conflicting proposals are reported.
Keep every source fragment in Git (JSON is ignored by default; stage exact owned
paths explicitly). Rebuilds never depend on ignored audit HTML or reports. Deterministic
merger: `server-config/maintenance/2026-10-17-build-internal-language.py`.
Update controller pins only after tests; never bypass a hash guard on the server.

DOM test tool `jsdom@26.1.0` is local-only under ignored outputs, not a production
dependency. `2026-10-17-test-internal-language.cjs` exercises native HTML/JS with
all network submissions intercepted. It does not paint pixels or send emails.
Current public PHP check explicitly requires the `public` context:
`php server-config/maintenance/2026-10-17-test-internal-language-scope.php wp-mu-plugins/zz-harmat-internal-language.php public`.
Historical full preview2167 assertions require the recorded preview controller;
do not run preview deployment against the now-public source.

Actual release/backup/gates/recovery live in
`server-config/maintenance/2026-10-17-internal-language-release.md`.
The header-only follow-up is in `2026-10-17-language-header.md`; dictionary-only
v1.0.4 is in `2026-10-17-chinese-coverage-release.md`. Current v1.1.0 narrow
bridges, backup/verification/rollback and exact test/browser outcomes are in
`2026-10-18-language-narrow-bridges.md`; use that release's verifier for current
hashes, not superseded dictionary/header verifiers. The older publisher pins the first release,
so it intentionally rejects later presentation hashes. The freshly approved ONE
positive TEST is NEVER replayed from B;
the one-shot intent/result is private and deliberately not in Git.
