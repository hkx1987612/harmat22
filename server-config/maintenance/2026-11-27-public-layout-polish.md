# Public Layout Polish

Released three previously reviewed presentation changes: property milestone payments are shown in five aligned rows; neighborhood distances and map buttons align with the first line of each place name; legal contact tables wrap at mobile widths. Existing payment proportions, conditions, and the financing page layout are unchanged. Bilingual payment text matched the previous version after normalizing list separators. The five property templates with no prior payment block were preserved. Search descriptions, image metadata, images, prices, policy text, quote and appointment behavior were not changed.

Verified PHP lint, 282 payment assertions, five payment-runtime tests, and 346 neighborhood checks. Before/after HTML and protected form/SEO shells matched on 16 public routes after excluding the approved layout changes. Live Chromium verified HU at 1440 and 320px and ZH at 390px: five separate payment rows, 13 aligned distance entries, four legal pages with fitting contact tables, unchanged financing page, original bus timetable link, and quote open/close without submission. Evidence remains ignored in `outputs/public-layout-release/`.

Private verified backup: `/home/harmath2/codex-backups/public-layout-polish-f2f670d0f6bd4c06b48e3db5f60e6826`. It contains separate exact before/after copies of each named module. Rollback requires the current module to match its after copy, then linting and atomically restoring only its before copy with the same mode and owner, purging cache and rechecking involved public routes. Do not restore later changes or any database records.

| Module | Live SHA-256 |
| --- | --- |
| `hm-legal-cookie-compliance.php` | `b44749af1e774ef1c1e6bba173f8a855092d7be34cd5ab3c3cbc7fa763b1f4a2` |
| `zz-harmat-neighborhood-practical.php` | `47484ac1dc03fee047bcca72f7778c04c9d5a237e56dbe2ce59462f63acea531` |
| `zz-harmat-public-payment-copy.php` | `0751e697e7e616f56ccdd5eb109018ea46a98c22e624f9c5be4e34036ac26adc` |
