# A1-F-L1 Hero and Public HTTPS - 2026-10-16

## Scope and Result

User explicitly approved both repairs, requiring backup, regression and no impact
on website/quote/appointment use. Shared main was clean at `4006df9` and pulled
up to date; there were no pre-existing TASK_HISTORY dirty edits to include.
User's Astra-planning/Sol-execution preference was retained for the bounded hero
source/fixture work. Parent reviewed the exact diff and handled production QA.

- A1-F-L1 / public post3841: `_harmat_sales_area=60.1` is the existing authority
  used by search, CRM, quotes and public schema. Old hero ignored that field and
  computed ground-floor sales area as the interior57.05. New six-line guard only
  affects exact integer ID3841 plus exact heading/fallback title `A1-F-L1`.
- Sales area now60.10m2; existing unit-price formula now1,403,366HUF/m2.
  Interior57.05m2, garden/terrace64.05m2, total84,342,300HUF, reserved status,
  actions, floorplan and all stored business data remain unchanged. A missing,
  non-positive, non-scalar, boolean or non-finite override retains old fallback.
- Public anonymous GET/HEAD pages now return a single301 to their same HTTPS
  path/query. No HSTS, authentication, DNS, proxy or firewall setting was changed.
  Existing .htaccess content is byte-identical outside one inserted block before
  the WordPress-generated section. Security rules remain in their original order.
- Redirect excludes PHP endpoints, REST, admin, agent/sales/client portals,
  action/nonce/token/preview queries and every non-GET/HEAD method. This is public
  page canonicalization, not a claim that every hosting-served static asset or
  excluded endpoint now redirects. Form submissions retain their original flow.

## Live Backups and Source Pins

Private directories0700/files0600, exact originals plus metadata and manifests:

| Change | Backup |
| --- | --- |
| Hero | `/home/harmath2/codex-backups/area-https-0ead5ccff1d743f8862bb0e1c1402450` |
| Public HTTPS | `/home/harmath2/codex-backups/area-https-34b66ba97fff461b8e583e7c9e884d69` |

- Hero original rawSHA256 `508c7ec6d9f41559045de83d70070b145274cc27f20089aa81ad1483b756d70e`.
- Original live file has2,246 CRLF among2,250 LF boundaries, not pureLF. Normalized
  Git/source parity independently provedSHA256 `582b4e10c882cc975f1d4f16cf17d688c021bb15c350d6693a4cc0619f1c2446`.
  Deployment inserted only the six CRLF lines and preserved every original byte.
- Hero released rawSHA256 `c63d48b79940f43c2311bb9c0fd85e280d35634bccd2caebcb4c583aacd65517`;
  Git LF SHA256 `cd01fb60107d7d6a13d5e4b83a93d6f22e02612ac779b3939fa9d211737cf5a8`.
- Root .htaccess originalSHA256 `527e57484be628c427f62b7872cb1529af359d219e4433001899385db4a24511`;
  releasedSHA256 `855e66419fc12c257e78d513e7f7e112a642a6653e0765b3809b82443ae94f3b`.
- Tracked `server-config/public-https.htaccess` LF SHA256
  `4fbc4708a6b274889f1a68bf0c73641d75f85e3b5687006910e0aa5216bdcf8d`.
- Reviewed source/test bundle SHA256 `88db00da1933f91849a5bcc95c0b5d85c9fb48d302e5e48ddeb4169115285d08`.
- Stable source tag: `stable-2026-10-16-area-https-current`.

## HTTPS Ingress Proof

The previous copy-only release's template_redirect experiment did not fix cached
HTTP responses and is still removed. Do not restore that rejected MU.

Three unique isolated child-directory probes left existing public files untouched;
all temporary files/directories were removed by exact ownership-byte checks.
The final full-rule20-result proof is privately retained at
`/home/harmath2/codex-backups/https-probe-6b3d719c951d45a6a4dd2d7f017c7a2f`.
Earlier diagnostic backups `https-probe-068487e8989c4148bb9ce91506383140` and
`https-probe-8340823e0eed484ca6c50de080256021` are historical observations only.

Actual nginx ingress sets X-Forwarded-Proto to http/https and overwrites contrary
client-supplied values. Native rewrite HTTPS !=on redirects BOTH schemes, despite
PHP seeing HTTPS=on for the HTTPS request; using that common rule would loop.
The final rule uses the observed proxy header, fixed allowed Host and destination.
Isolated real-server tests prove GET/HEAD301, HTTPS200, exact path/query retention,
contrary client-header overwrite, excluded action-query200 and a harmless probe
POST405 without redirect. The deployer verifies the privately tested rule bytes
before root activation. No hosting topology beyond those observations is assumed.

## Verification

Completed before/following the two independent atomic replacements:

- Local PHP8.5.6 and productionPHP8.3:1,402 isolated hero checks. Token parser
  extracts only the exact reviewed function, never evaluates the entire plugin.
- Private native WP query/render before hero activation: all124 published units;
  123 hero HTML strings byte-identical; target differs only sales-area/unit-price
  DD fields. Production candidate, same-directory temporary and formal PHP lint,
  raw hash, original file mode/owner preservation and cache purge all pass.
- .htaccess is not PHP; it was syntax/behavior-tested as an actual isolated
  child-directory .htaccess, not falsely described as php-linted. Exact tested
  rule bytes installed atomically only after a separate original-root backup.
- After EACH change: nine key HTTPS routes200/no fatal markers,124-unit public
  feed/nine-field/current-authority parity and GET-only boundary,17 pure native
  anti-spam checks. Existing 47.83m2 public unit protection remains intact.
- Public HTTPS independent repeated GET/HEAD: home, privacy, filtered search
  including UTM query, A1-F-L1 and gallery all HTTP301 to exact HTTPS URL, then
  HTTPS200/no intermediate redirect or loop. Anonymous/default cached paths are
  included, not only cache-busting queries.
- Independent second process confirms original .htaccess/security bytes and all
  original hero bytes outside the insertion, production lint/hashes, protected
  property/offer/CRM/page/Elementor aggregates and all adjacent critical sources.
  No stored area/price/customer/quote rewrite or business-data restoration.
- Native pure offer validation35 checks: five valid sources x three times x both
  same-host schemes (30 cases), plus registration and four bad-value boundaries.
  Calls only integrity/anti-spam filters; no REST dispatch, sales handler, email,
  record or booking creation. Public GET-only feed remains HTTP/HTTPS200 and its
  harmless no-write POST remains404 with no redirect on either scheme.
- Existing local26 appointment integrity cases also pass, including Google and
  social source, dates/times/nonce/property/origin boundaries.
- Independent Sol offline deployment fixture, reviewed and rerun by parent:
  32 tests pass with ResourceWarning as errors. Covers byte-preserving patch,
  stale/marker/rule guards, HTTP403 stop/no retry, staged/formal lint ordering,
  atomic failure cleanup, post-rename recovery and file-only rollback that does
  not rewind new customer data. These are simulations, not another live rollback.
- Chrome desktop actual2381px: target hero60.10, quote modal60.1 and equal total;
  source Google/social selection, valid future date, timeslot, open/close pass.
- Chrome actual390px: target hero,366px quote dialog within viewport, homepage,
  search, virtual selector, phase-one selector, A2 selector and contact route have
  scrollWidth=clientWidth390, no visible complete/broken images in inspected DOM.
  Cookie settings opens with privacy links; no preference save. Desktop home
  rechecked after viewport reset. Contact telephone/email retain official values.
  Browser navigation from HTTP target ends on exact HTTPS target.
- Initial zoom-scaled468px response is NOT the390px proof; adjusted temporary
  viewport produced independently measured390px. Viewport reset succeeded.
- Screenshot channel again timed out on capture; no pixel-perfect/screenshot
  certificate, real Safari/iOS/China-network/full-site crawl or recipient-inbox
  proof. No new positive test, appointment, email, ad click or conversion event
  intentionally submitted. The one authorized October14 TEST was not repeated.

Stable code/config is live; earlier historical copy-only HTTP200/current-A1-area
notes are superseded by this repair. Broader old-formula comparisons are only
audit signals, not proof of50 final-browser defects; do not mass-rewrite areas.
Facebook AI/network/API/support work remains paused in priority, not launched or
completed. No new model/API/ads/budget/security/identity change or shutdown.

## Verification and Recovery

Run from the shared repository with existing authorized local SSH configuration;
do not put keys, backup contents, private evidence or customer data in GitHub.

```powershell
python -B server-config/maintenance/2026-10-16-deploy-area-https.py
python -B -W error::ResourceWarning server-config/maintenance/2026-10-16-test-area-https-deploy.py
python -B server-config/maintenance/2026-10-16-deploy-area-https.py --verify /home/harmath2/codex-backups/area-https-34b66ba97fff461b8e583e7c9e884d69
python -B server-config/maintenance/2026-10-16-verify-area-https.py --run
```

The independent release-time verifier intentionally compares all protected
business/page aggregates to release snapshots. Later legitimate new leads or
owner edits may make it stop: investigate rather than restoring historical data.
Do not rerun the pre-release ingress probe against the changed root configuration.

HTTPS rollback only, preserving the area repair:

```powershell
python -B server-config/maintenance/2026-10-16-deploy-area-https.py --rollback /home/harmath2/codex-backups/area-https-34b66ba97fff461b8e583e7c9e884d69 --allow-live-write
```

Hero rollback only, preserving HTTPS:

```powershell
python -B server-config/maintenance/2026-10-16-deploy-area-https.py --rollback /home/harmath2/codex-backups/area-https-0ead5ccff1d743f8862bb0e1c1402450 --allow-live-write
```

These require exact current released ownership hashes, preserve original metadata,
lint PHP stages/formal targets, purge cache, and never restore database records.
If the target changed outside this release, stop and reconcile, never overwrite.
After rollback rerun key HTTPS routes and normal form controls, without another
positive TEST. GitHub is the handoff authority for the narrow source/config delta.
