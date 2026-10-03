# September Construction Report - Release Record

Status: LIVE, RELEASE CHECKS VERIFIED. Live browser QA, nine-route regression, full public scan, exact PHP/media hashes, HTTP range delivery, staging/lock cleanup, WordPress boot and the final post-QA read-only audit passed.

Release date: 2026-10-03. Approved clean Git baseline: `ff06d6c`. Previous stable tag: `stable-2026-09-28-search-journal-mark-current`. Release tag: `stable-2026-10-03-september-construction-current`.

## Scope

- MU plugin: `wp-mu-plugins/zz-harmat-construction-progress-video.php`, version `1.3.0`.
- Public route: `/epitesi-naplo/` only. The new September report is prepended within the existing injection before the August video, nearby park video, June-August gallery and original construction-log list.
- Report cutoff: 2026-09-30. This is the date of the progress facts, not a blanket capture date for the media.
- New media: eight videos (one overview and seven archive clips), eight JPEG posters and four photos with two WebP sizes each: 24 files total.
- The original 16-photo historical gallery and its schema remain intact; the shared lightbox has 20 photo buttons. The August VideoObject is preserved.
- Poster clicks create native video controls and invoke playback with caught promise rejection. No September video element/source or MP4 preload is present before interaction. Each player also has a visible separate-tab MP4 fallback link, without duplicate noscript links.
- Photos use lazy 960-pixel thumbnails and request the 1920-pixel image through the existing lightbox. The photo grid is two columns on desktop and one on mobile; archive clips remain collapsed initially.
- August copy is historical and its poster is lazy. Page SEO descriptions retain the September 30 cutoff without dating all footage to September. OG/Twitter images use the new overview poster.
- No other site pages, apartment data, quote calculations, CRM logic, forms or tracking behavior are intentionally changed. No inquiry/form submission is required for verification.

## Confirmed Progress Facts

The following Hungarian copy describes the state as of September 30, not completion dates:

- A1: A pincefalak és pillérek betonozása elkészült. A pincefödém zsaluzása 30%-os készültségű.
- A2: A pincefalak és pillérek betonozása 80%-os, a pincefödém zsaluzása 20%-os készültségű.
- A3: Az alaplemez vasalása 40%-os készültségű.
- A4: Az alaplemez betonozása elkészült. A falak és pillérek vasalása folyamatban van.

## Media Dates And Provenance

- Overview raw metadata reports `creation_time=2026-10-02T08:19:32Z` and GPS. The visible label is `2026. október 2-i helyszíni felvétel`, explicitly supplemental to the September 30 report. It must not be described as captured in September.
- Overview VideoObject name and description identify October 2 footage supplementing the September 30 report; `dateCreated` is `2026-10-02`, `uploadDate` is `2026-10-03` and duration is `PT32S`. The upload date is separate from capture and report dates. Live SEO/schema checks passed.
- Photo 01: `Alaplemez és pinceszinti vasalás – 2026. október 2.`
- Photo 02: `Zsaluzási munkák a pince szintjén` (undated).
- Photo 03: `Toronydaru és pinceszinti szerkezet` (undated).
- Photo 04: `Alaplemez vasalása – 2026. október 2.`
- Photos 01/04 have October 2 EXIF provenance. Photos 02/03 have no asserted capture date. Each photo uses matching caption/alt text; no building attribution or invented completion claim is added.
- The first archive clip keeps filename `2026-09-21-a1-a2`, but its source creation metadata indicates September 17 rather than September 21. Its public label is only `2026. szeptember` with `datetime="2026-09"`; the filename is not evidence of an exact capture date.
- The remaining archive clip labels are September 22 (A2), September 23 (A1), September 24 (A3), and September 25 (A1, A2, A4). Do not generalize filename labels into independently verified metadata provenance.

## Assets And Privacy

Public destination: `/wp-content/uploads/2026/09/construction-september/`, built with `content_url()`.

Video/poster basenames, each with `.mp4` and `.jpg`:

```text
2026-09-overview
2026-09-21-a1-a2
2026-09-22-a2
2026-09-23-a1
2026-09-24-a3
2026-09-25-a1
2026-09-25-a2
2026-09-25-a4
```

Photo basenames: `harmat-2026-09-site-01` through `harmat-2026-09-site-04`, each with `-960.webp` and `-1920.webp`.

The overview is a re-encoded H.264 720p, 30 fps, silent derivative: 32 seconds and exactly 7,849,198 bytes. The seven archive clips retain their source-native 720p, 60 fps H.264 video streams by stream copy, without video re-encoding or video quality loss; durations are approximately 21-39 seconds. All eight public videos have audio removed, source metadata/GPS stripped and faststart placement. Metadata removal does not change the recorded provenance above.

All eight videos were fully decoded locally with FFmpeg while preserving the input timebase, with no errors (confirmed by the coordinating chat). Separate live browser tests decoded and played the overview, one archive clip and the existing park video at both viewports. Exact deployed hashes for all 24 media files were confirmed by `--verify`; individual hashes are retained in the hash-verified complete private backup manifest/media set.

- Eight public MP4s: ignored local directory `outputs/2026-09-construction-ready/`. The helper reads the exact eight filenames above, not alternate intermediate encodes.
- Eight JPEG posters and eight WebP derivatives: `assets/construction/`, the Git release media set.
- Raw archives, original videos/photos, intermediate encodes, private backups, credentials and QA outputs are not release Git assets. Do not commit them.
- Local preview screenshots/results: ignored `outputs/2026-09-construction-ready/qa/`. Live construction QA evidence: `outputs/2026-10-03-september-construction-live-qa/results.json`; the live report is distinct from the earlier stub harness.

## Deployment Helper

Existing helper: `server-config/maintenance/2026-10-03-deploy-september-construction.py`. Commands below are operator instructions, not a record of execution or deployment approval. Run from the repository root using the established Python environment with Paramiko, Git and local PHP available.

```powershell
# Server read-only baseline/media preflight (default).
python server-config/maintenance/2026-10-03-deploy-september-construction.py

# Mutates the server; run only after explicit deployment approval.
python server-config/maintenance/2026-10-03-deploy-september-construction.py --deploy

# Server read-only exact installed PHP/media verification.
python server-config/maintenance/2026-10-03-deploy-september-construction.py --verify

# Mutates the server; restores the previous public state from the confirmed backup.
python server-config/maintenance/2026-10-03-deploy-september-construction.py --rollback '/home/harmath2/codex-backups/september-construction-ab62c51178de4d85a9eef17cefe6a36e'
```

Authentication reuses the established SSH/Paramiko setup and trusted `~/.ssh/known_hosts`; unknown host keys are rejected. The helper accepts `--key`, `--passphrase-file` and `--php` for approved machine-specific paths. Do not place secrets in this document, command history or Git, and do not disable host-key verification.

Default mode requires the live PHP to match `ff06d6c` semantically (transport CRLF differences only) and the September media directory to be entirely absent. It hashes all 24 local media files, lints local/live PHP and reads offer-lead count/error-log state without deploying or clearing cache. `--verify` checks the deployed candidate instead and still requires local candidate PHP and media.

Deployment uses a private lock, a verified complete backup, hidden staging, temporary/final PHP lint, exact hashes, a final baseline/concurrency check, media-directory installation and atomic PHP replacement. It then clears cache and checks the construction page plus the helper's existing public smoke routes and media HEAD responses. These HTTP checks do not prove browser decoding or absence of layout regressions.

Backup structure under `/home/harmath2/codex-backups/september-construction-<32-hex-token>/`:

```text
before.php       Previous live PHP, preserving its exact bytes
after.php        Candidate PHP used by this deployment
manifest.json    Target paths, baseline, old/new hashes, all media hashes, pre-state
media/           Complete 24-file public-media set, including all eight MP4s
```

Exact backup path: `/home/harmath2/codex-backups/september-construction-ab62c51178de4d85a9eef17cefe6a36e` (deployment confirmed by the coordinating chat). The rollback and B-computer download instructions below use this same backup. An interrupted future run may leave an incomplete backup/stage or a lock requiring review. Never automatically break a stale lock.

## Confirmed Deployment Record

Facts supplied by the coordinating chat after live deployment:

- Candidate/deployed LF PHP SHA-256: `a0a98a575697eb32862be18b366af5e023b000241e3fec158aa65f3a96b065d5`.
- Initial and final deployment offer-lead count: `54`, unchanged.
- Initial and final deployment root `error_log` state: `[191080, 1790981662, "5d6e21fc20176e08710f193d0d1d88e349d40ad65283dfca22fa6320501ecd1b"]`, unchanged. Values are bytes, modification-time Unix timestamp and SHA-256 respectively.
- Exact live helper `--verify` completed successfully with `VERIFIED_EXACT_HASHES`: deployed candidate PHP and all 24 media files match their expected hashes. Offer leads remained `54` and the complete error-log size/time/hash state above was unchanged at verification.
- Final deployment audit passed: all 24 public HTTP media hashes match exactly; all eight MP4s returned HTTP `206` with the correct `Content-Range` for bytes `0-1023`. Staged/final PHP lint, cache purge, WordPress boot and seven-route deployment smoke checks passed. Staging files and the deployment lock are absent.
- PRE/POST/VERIFIED state remained unchanged. The final post-browser-QA read-only audit reconfirmed WordPress boot, lead count `54`, the identical root error-log byte count/time/hash above, and the same published PHP SHA-256. All deployment/audit processes finished.
- Full public scan completed with exit code `0`: `145` pages, `124` properties and `610` assets; all four issue counts were `0`. Report: `outputs/2026-10-03-september-construction-site-scan.json` (local evidence, not a Git release asset).
- Nine-route live browser QA passed on desktop/mobile, with the focused results and limitations below.

Rollback checks the manifest, both PHP hashes, all private media hashes and concurrent live changes. It restores `before.php`, removes only the exact owned September media directory after validation, clears cache and performs old-state smoke checks. It does not need local videos, candidate PHP or Git. A conflicting PHP/media edit or uncertain recovery must be reviewed rather than overwritten. Preserve the complete private backup after rollback.

## B-Computer Recovery

On computer B, use the actual repository root (for this workspace, `C:\Users\Administrator\Documents\Codex\github-harmat22`) and provision the existing SSH key/passphrase file through the established private channel. Trusted host keys must already be present. Do not transfer secrets through Git.

For live rollback, run the `--rollback` command above with the exact verified backup path. This restores the previous public state directly from the server backup; local videos and a release checkout are not prerequisites. A Git checkout alone does not restore server PHP, uploaded media or cache state.

To reconstruct the local release media on B for preview or a separately approved redeployment, retrieve the manifest and hash-verified files from the complete private backup using the same Paramiko connection. Restore MP4s to ignored `outputs/2026-09-construction-ready/`; restore JPEG/WebP files to `assets/construction/`. The example below downloads to B only; it does not deploy or delete server files. Its backup path, baseline and candidate hash match the confirmed deployment record above; the manifest supplies the exact media hashes. It refuses to overwrite conflicting local files.

```powershell
$env:HARMAT_SEPTEMBER_BACKUP = '/home/harmath2/codex-backups/september-construction-ab62c51178de4d85a9eef17cefe6a36e'
@'
import hashlib
import json
import os
import re
from pathlib import Path
import paramiko

root = Path.cwd()
backup = os.environ['HARMAT_SEPTEMBER_BACKUP']
assert re.fullmatch(r'/home/harmath2/codex-backups/september-construction-[0-9a-f]{32}', backup)
client = paramiko.SSHClient()
client.load_system_host_keys()
client.load_host_keys(str(Path.home() / '.ssh/known_hosts'))
client.set_missing_host_key_policy(paramiko.RejectPolicy())
client.connect('185.111.89.244', username='harmath2',
               key_filename=str(Path.home() / 'Downloads/harmat'),
               passphrase=(Path.home() / '.ssh/harmat_key_pass.txt').read_text(encoding='utf-8').strip(),
               look_for_keys=False, allow_agent=False, timeout=20)
try:
    with client.open_sftp() as sftp:
        with sftp.open(backup + '/manifest.json', 'rb') as stream:
            manifest = json.loads(stream.read())
        assert manifest['backup'] == backup and manifest['baseline'] == 'ff06d6c'
        assert manifest['newHash'] == 'a0a98a575697eb32862be18b366af5e023b000241e3fec158aa65f3a96b065d5'
        slugs = ('2026-09-overview', '2026-09-21-a1-a2', '2026-09-22-a2',
                 '2026-09-23-a1', '2026-09-24-a3', '2026-09-25-a1',
                 '2026-09-25-a2', '2026-09-25-a4')
        names = {s + ext for s in slugs for ext in ('.mp4', '.jpg')}
        names.update(f'harmat-2026-09-site-{i:02d}-{w}.webp'
                     for i in range(1, 5) for w in (960, 1920))
        assert set(manifest['media']) == names
        for name in sorted(names):
            expected = manifest['media'][name]
            assert re.fullmatch(r'[0-9a-f]{64}', expected)
            folder = ('outputs/2026-09-construction-ready'
                      if name.endswith('.mp4') else 'assets/construction')
            target = root / folder / name
            if target.exists():
                assert hashlib.sha256(target.read_bytes()).hexdigest() == expected, str(target)
                continue
            with sftp.open(backup + '/media/' + name, 'rb') as stream:
                data = stream.read()
            assert hashlib.sha256(data).hexdigest() == expected, name
            target.parent.mkdir(parents=True, exist_ok=True)
            with target.open('xb') as stream:
                stream.write(data)
            print('RESTORED', target)
finally:
    client.close()
'@ | python -
Remove-Item Env:HARMAT_SEPTEMBER_BACKUP
```

On B, use the approved PHP/helper/poster/image release revision identified by `stable-2026-10-03-september-construction-current`; `ff06d6c` is the previous baseline, not the v1.3.0 release. After media recovery, default helper mode is for an absent September installation; use `--verify` for the installed release. Never use `--deploy` merely to retrieve local assets.

## Verified QA

- Live Chrome desktop 1440px and mobile 390px construction QA passed; report: `outputs/2026-10-03-september-construction-live-qa/results.json`.
- All 20 lazy 960-pixel thumbnails loaded, including the 16 historical images; all seven collapsed archive clip posters decoded when opened. September and historical lightboxes passed open, next, keyboard previous, Escape and focus restoration checks.
- Before interaction: zero MP4 requests and zero 1920-pixel image requests, including after clip-list expansion. Large images loaded only through lightbox interaction.
- Overview, first archive clip and nearby park video each decoded at 1280x720 and advanced in time at both viewports; nonblank/moving pixel checks passed. Browser errors and horizontal overflow were zero across tested phases.
- Single H1, exact canonical, indexability, meta/OG/Twitter descriptions and overview poster, October 2 supplemental VideoObject and preserved August schema all passed.
- Original August poster click still creates the correct YouTube embed. External playback was deliberately blocked by the test, so actual YouTube decoding was not verified.
- Nine public routes passed at desktop/mobile, including the property quote modal's correct `57.36 m2` summary and five approved lead sources. Focused search filtering/reset/navigation and financing checks also passed. No test inquiry or form was submitted.
- Full public scan passed with exit `0`: 145 pages, 124 properties, 610 assets, all four issue counts `0`. Exact live `--verify` passed for PHP/all 24 media files. Lead count `54`, error-log size/time/hash and published PHP hash remained unchanged through the final post-QA read-only audit.
- Supplementary retained 2026-08-29 construction browser test passed with `DESKTOP_PASS`, `MOBILE_PASS` and `CONSTRUCTION_PAGE_BROWSER_TESTS_PASSED`. New QA rerun passed desktop/mobile. Top screenshots `desktop-initial.png` and `mobile-initial.png` in the live QA directory were confirmed at `scrollY=0`; September viewport screenshots `desktop-september-viewport.png` and `mobile-september-viewport.png` were reviewed by the coordinating chat.

## Limitations

Mobile QA uses a Chrome 390px viewport, not a real Safari/iOS device. Mainland-China network playback was not tested. The test blocked external services, including YouTube playback; its original embed creation was checked, not external video playback. Production inquiry submission was not exercised and no test inquiry was sent. These results cover the stated checks, not a claim that every possible defect is absent.

## Release Status

Operational release checks and the final post-QA read-only audit are complete. Release tag: `stable-2026-10-03-september-construction-current`. `PROJECT_CONTEXT.md` records the verified current state and `TASK_HISTORY.md` has an appended entry; earlier history is preserved.
