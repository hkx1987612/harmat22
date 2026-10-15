# Confirmed offer spam - recoverable cleanup

## Completed Scope

After the earlier narrow anti-spam release, the user explicitly authorized deleting the junk records and continuing the Facebook Messenger work. Chose recoverable trash, NOT permanent deletion.

Exactly120 private `harmat_offer_lead` records dated October15 with the previously confirmed Google-share promotional URL in the name/title were rechecked. All were unassigned, without an apartment or linked sales deal, comments/children or pending offer-mail job. Stored mail status had settled to `sent` / `sent-to-provider-sync`. Exact original post/metadata snapshot SHA256: `11cb5c132cb3a58522c159806f8f6ec57dbbdfabd300a099279d13701b77ceaa`. A changed count/snapshot or any protected association stops the operation; no generic delete-by-language/IP/email rule.

Successful private backup: `/home/harmath2/codex-backups/offer-spam-trash-05c42c7bd9384d9dbde15727a624656c`,0700 with0600 runner/originals/result. Original target posts and metadata remain there; no contact/content export to Git. Existing WordPress `EMPTY_TRASH_DAYS` is30, explicitly checked positive before calling native `wp_trash_post`; a0 setting would have stopped rather than permanently delete.

## Safety and Verification

- Private runner was uploaded to a non-executable stage, PHP8.3 linted, renamed and formally linted before WP-CLI execution. No public PHP/plugin/config was changed.
- Target records/metadata and four CRM option rows were locked in an InnoDB transaction. Private backup was verified before mutations. Native trash status was checked for all120; original contact metadata/title/content/dates/GUIDs and every non-target protected customer/property/CRM snapshot remained byte-identical.
- Two attempts rolled back because native WordPress trash normalized existing GUID HTML entities, tripping the non-status-data guard. Independent readback proved all120 still private with the same original snapshot before retry. The final runner does not exempt GUIDs: it accepts only entity-decoded-equivalent normalization, compare-and-swap restores its own generated GUID change while rows are locked, then checks original bytes again. Any different address still stops. Previews/failed backups are retained privately, not presented as successful cleanups.
- Commit confirmed `RECOVERABLY_TRASHED`, count120, no permanent deletion. Independent SQL/status readback after commit shows `trash120`, not private records in the active inquiry list. The private result file is present.
- Nine public routes returned200 afterward with no critical PHP marker: home, search, sample property, both selector levels, gallery, contact, construction and thank-you page. No new valid inquiry, booking lock, notification test, model call, ad/budget/network/security or nogakft-service change.
- Earlier anti-spam MU remains active at its verified hash. This operation removes known clutter from the active list, not a new all-spam guarantee or a diagnosis of the provider-sync duplicate writer.

## Recovery and Continuation

Restore through the normal CRM/WordPress trash during the configured30-day retention. The independent private backup preserves original fields beyond the normal UI mechanism; any manual backup restoration must be reviewed and restricted to these owned records, never overwrite later real customer activity. Do NOT permanently purge the trash under this authorization.

Cleanup is complete. Do not rerun `--prepare`/`--trash`: the exact120-private-record precondition should now fail. `2026-10-15-trash-confirmed-offer-spam.py --inspect BACKUP` with the exact successful path above is read-only and can verify status/result artifacts. No recurring cleanup or monitoring was created. Scripts, public maintenance notes and local-only Messenger preparation are shared; raw backups/private evidence are not.
