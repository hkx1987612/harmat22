# Neighborhood Content Preservation

## Released And Independently Verified

- User reports missing facilities and a generic presentation shell on `/harmat-lakopark-kornyeke/`, then explicitly approves backup, restoration and keeping 3D below the details. No database, facility data, quote or appointment changes authorized/performed.
- Read-only evidence: server HTML still includes the detailed neighborhood section, six distance cards and original photographs. The executed DOM instead contained only `#harmat-3d-tour` plus a short SEO introduction. Existing `map-redesign.js` selected the entire `.harmat-neighborhood-page` and called `target.replaceWith(replacement)`. This is client-side removal, not deleted records or a translation failure. Its previous Git change predates the October homepage redesign (July7, `18db69f`); do not claim the latest homepage edits created this logic.
- Minimal source repair selects `main .harmat-neighborhood-page` explicitly and inserts the same original interactive module immediately after it. All other target-selection/legacy replacement, viewer, assets, media, controls and form handlers remain unchanged. Existing-module guard prevents duplicates. Plugin header/constant2.2 ->2.2.1 refreshes the cached JS/CSS URL; stylesheet bytes unchanged. Raw PHP mixed line endings retained during activation.

## Exact Release

- Aggregate: `d465da5076dc1e5e7f662bd1f047fb273525b3f93165d2e41df90d96a25222dc`.
- Private0700 backup: `/home/harmath2/codex-backups/neighborhood-preserve-ec4cbc01bbf745e89ed00da9b6bbd615`. Old/new sources, baseline pages, protected hashes and native preflight/receipt stay private0600. No raw backup or screenshots in Git.
- JS old SHA256 `ed225cd27cda3498b1329e84e788c53efe00435df019e4ccabf8c4a58eb81aa7`; current `a9d07a0f3c823d40fcd39b4ad0011ed74545f4c99ef28d3c8d534bcb039870f2`.
- PHP old raw `7ba09d026b7ab16c493c78302cbd3b8a373080efa5e7505ebe024f377a09f521`; current raw `fb3bdf09c766880a847c6bd55b150ff229c45374b16f9054aaac73bb18e61330`. Current repository canonical LF `034d27b59d7035e7d0c9e3e474474cd1675d70af82bc18ac7ac04143cf0458d2`. Do not normalize the deployed raw file.
- `2026-10-20-restore-neighborhood.py` defaults offline; exact aggregate required for writes. Exact-source/metadata/path/lock checks, private PHP lint, native pure filters and full protected snapshot precede same-directory atomic JS-then-version activation. Cache purge follows. Source-only ownership-checked recovery, never DB restore.
- First attempt encountered one503 before backup/activation; stopped without writes. Subsequent bounded homepage HEAD200. No security/rate-limit/hosting settings changed. A later attempt activated but cross-template verification incorrectly required the homepage `main` class on a native property page; owned sources were automatically restored. The next preflight caught the same checker assumption without activation. Corrected the CHECKER to exact `#content` only for the observed native property template, retaining exact `#main` elsewhere. Replayed all seven captured templates offline before the successful release. These were validator errors, not broken quote logic; no assertions relaxed to skip content comparison.
- Successful release and a separate fresh `--verify` both pass seven bounded routes, byte-exact server-rendered content/native form/canonical/SEO, exact served JS2.2.1, six facility cards, full protected business/source state,35 pure offer and17 anti-spam assertions. Requests are serial/throttled, not a full-site crawl. No submission, REST dispatch or mail.

## Actual Browser QA

- Independent IAB test tab:1280px desktop HU/ZH six cards, one viewer after details, no horizontal overflow. Original four neighborhood photographs loaded at desktop inspection.390x844 ZH single-column six cards, all card scroll widths within their boxes.320x568 HU and ZH likewise no overflow; all detailed facility translations remain present.
- Viewer location/panorama tabs switch while all six cards remain. Native fullscreen verified after resetting the temporary viewport override:1920x1080 console with nonblank canvas/no visible panorama error. Pause/keyboard ArrowRight changes a cropped canvas screenshot; Escape from the focused panorama exits fullscreen. Initial overridden-size fullscreen screenshot was an emulation artifact, not final evidence. Body-focused Escape was rejected while outside the fullscreen subtree; focused panorama Escape worked. No physical-touch/device/FPS certification.
-320px native neighborhood quote link is intentionally intercepted by the existing offer handler: blank native modal opens/closes in HU and ZH. Contact fields stay empty, privacy required/unchecked, date minimum2026-10-20, original three time bands and five HU source values retained. No selections, customer data or submissions sent. This is layout/filter regression evidence, not mail-delivery certification.
- User's original tab changed to the journal during testing; subsequent QA used a separate tab instead of navigating that user tab. Initial Chinese language restored, viewport override reset, empty modal closed. Repaired neighborhood test tab marked deliverable.
- Ignored proof: `outputs/2026-10-20-neighborhood-preserve/restored-desktop-facilities.png`, `restored-mobile-390-facilities.png`, `restored-panorama-fullscreen.png`. Screenshot embedded in user close-out.

## Local Verification And Recovery

- JS syntax and PHP lint pass.23 focused DOM assertions plus3 actual captured-page checks pass;7 offline atomic/source/native-wrapper tests pass. Foreign source bytes are never overwritten; failed stages removed only by owned-byte equality.
- Exact source-only rollback, only while these exact sources remain current:

```powershell
python server-config/maintenance/2026-10-20-restore-neighborhood.py --rollback /home/harmath2/codex-backups/neighborhood-preserve-ec4cbc01bbf745e89ed00da9b6bbd615 --expect d465da5076dc1e5e7f662bd1f047fb273525b3f93165d2e41df90d96a25222dc
```

- Rollback deliberately restores the old disappearing-content behavior; use only for a real regression and preserve any later independent edit. No DB rollback. Native data, previous distance claims, wording, translations and photography are restored as-is, NOT independently remeasured or refreshed. Existing generic viewer copy/contrast and a few untranslated viewer-only sentences remain separate improvement debt, outside this minimal repair.
- Earlier homepage stable code/media/rotation/assistant/footer and all ad budgets untouched by this release. New October19/20 media separately finished technical QA but remains unpublished at this checkpoint; see the updated preparation record.
