# Approved Search Descriptions And Share Covers

Released the reviewed metadata for `/magunkrol/`, `/lakaskereso/`,
`/epitesi-naplo/`, and eight property pages: A1-2-L5, A3-F-L3, A4-F-L1,
A1-F-L1, A2-4-L5, A1-1-L6, A1-1-L1, and A3-F-L5. The new MU plugin
`zz-harmat-approved-share-metadata.php` only registers Yoast metadata hooks.
The eight property covers reuse their existing room plans; About reuses its
existing courtyard rendering, labelled as such. Search and Journal keep their
old cover files. Existing Apartment, Offer, VideoObject and organization data
are unchanged. The property descriptions use the existing public floor area,
sales area, availability and price-visibility data rather than storing offers.

Verified PHP lint, 33 new isolated assertions and 196 existing metadata tests.
Independent live HTML matched the approved fields for all 11 pages; 8 additional
routes were used as negative controls. Protected body, form, link and structured
data comparisons passed. Eight linked PDFs and all proposed image files matched
their original SHA-256 sums. Fresh Chromium contexts at 1440px and 390px passed
on all 11 pages; the quote modal was opened and closed, with submission blocked.
There were no body images, PDF files, price, quote, booking or database edits.
Search-engine snippets and third-party share caches were not reindexed or tested.

## Rollback

Verified private backup: `/home/harmath2/codex-backups/approved-share-metadata-799d4030a6bf4ad79232b2389d382e5c`.
It contains the prior metadata module, verified candidate, before-page archive
and hash manifest. The new module did not exist before release; do not restore
or overwrite the earlier metadata module. To roll back, verify the installed
new file still matches the candidate (SHA-256
`ab1d261e74d19d85df8a4d60d22defd37359a9d2f4183e5762313ae313422c78`),
remove only that new file, purge cache and confirm the old metadata returns.
If the file has since changed, preserve those changes and review before rollback.
Raw backup and QA evidence remain private, not in Git.
