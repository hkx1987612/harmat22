# Warm, Practical Homepage Preview

Status: OFFLINE DESIGN REVIEW ONLY, October 18, 2026. Not deployed.

The offline artifact itself remains local. The user later explicitly authorized
a separate native-shell production adaptation and live verification. That release
is documented in `server-config/maintenance/2026-10-18-home-family-release.md`;
its sources are `wp-mu-plugins/zz-harmat-home-family.php` and `harmat-home-family/`.
The offline browser restriction was not bypassed. Historical pending checks below
refer to the local artifact, not the separately verified production integration.

User approved Sol implementation of a local preview, with publication only after
review. Quote and appointment behavior must not change. Sol authored the three
presentation files here; parent review added packaging and no-network tests.

## Design

- Original project video and aerial poster, compact lower-left brand/two actions;
  long hero address and promotional copy removed after user screenshot feedback.
- White, restrained sage/forest and small gold accents; no invented family photos.
- Three genuine floorplans before the dated construction report and lifestyle images.
- Project courtyard/living-room renderings are labelled as renderings. The nearby
  public park is explicitly not the development's internal completed garden.
- Compact showroom, virtual-selector link, team and official sales contacts.
- Original interactive 3D/360 presentation is restored inline, separate from
  apartment selection: panorama, videos, project, gallery, location and notice.
  The user explicitly rejected a poster that navigates to another page. The
  existing Pannellum engine is reused, not replaced with hand-built 3D logic.
- All-width hamburger navigation and local HU/Chinese controls beside it; explicit
  official appointment destinations in the header and full menu.
- Public apartment values were checked against the official public inventory on
  October 18. They are a preview snapshot, not a live price feed.

## Boundaries

No WordPress/PHP, production CSS/JS, language plugin, apartment data, CRM, native
quote modal, form validation, appointment or security configuration was changed.
No forms, tracking, API calls, embedded selector engine or submissions exist here.
Quote-labelled local buttons lead to the preview's contact section, NOT a new
quote workflow. Explicit official-site links open new tabs. CSP blocks connection,
form submission and frames. Only the original local hero video can autoplay,
muted and disabled for reduced-motion preference, with poster fallback.

## Build

From repository root, using Python 3 and Node:

```powershell
python server-config/maintenance/2026-10-18-prepare-home-preview.py --assets
python server-config/maintenance/2026-10-18-prepare-home-preview.py --copy-source --bundle
node --check server-config/previews/home-family/home-family.js
node --test server-config/maintenance/2026-10-18-test-home-preview.cjs
python server-config/maintenance/2026-10-18-test-home-preview-package.py
```

`--assets` makes read-only official public inventory/property/image requests and
fetches integrity-verified `lucide@0.468.0` and `pannellum@2.5.6` from the official
npm registry, plus the original official cube/gallery/video assets. It also
requires the previously approved local native video, nearby-park image and prior
reviewed project preview identified in the helper. These ignored media inputs
are NOT included in Git. A second computer needs the approved media separately;
do not substitute guessed assets or commit raw video. Public facts/manifests and
the Lucide license are staged beneath the ignored output folder. DOM tests use
the existing `outputs/2026-10-17-chinese-switch/node_modules/jsdom` installation.

For an already staged preview, `--panorama` stages only the original official
`/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/pano_pano_f.jpg`
as `assets/project-panorama.jpg`. Original bytes are preserved (774,397 bytes,
SHA256 `d6571b9ceabe2db857b12278e63d19ffab1c550a6401003dd3b74261c2885b19`).
The image combines project visualization with its surrounding aerial context;
do not label it completed-development photography. The original official
`/harmat-lakopark-kornyeke/#harmat-3d-tour` destination returned 200 and loads the
existing panorama script; dragging/WebGL behavior was not newly browser-tested.
`--interactive` stages the full original six cubemap faces, six gallery images,
overview poster, 10,088,966-byte native project clip and pinned Pannellum library/
license. Names/provenance are recorded in the ignored interactive manifest.
API reference: [Pannellum documentation](https://pannellum.org/documentation/reference/).

Open the generated file directly, without a dev server:

`outputs/2026-10-18-home-family/harmat-home-family-preview-v3-r1.html`

Images/cube faces, stylesheets, libraries and behavior are embedded. Videos remain
at sibling `assets/hero-video.mp4` (39,504,516 bytes) and
`assets/panorama-project-video.mp4` (10,088,966 bytes); moving only the HTML loses
video playback, not the embedded images. No remote display dependency exists.
Generated exports and media remain ignored; Git shares only source/tools/records.
V3-R1 is a separate filename after the interaction audit. V1/V2/V3 are preserved;
use the new file rather than a previously loaded version.
The embedded offline package is intentionally larger than a production HTML page.
Do NOT deploy that base64 export: future integration must reuse original lazy
asset URLs, defer panorama GPU initialization and retain manual video playback.

## Verified / Pending

- 31 source/no-network DOM/API-stub/export tests and 15 synthetic packaging tests
  passed. API stubs are NOT real WebGL, native fullscreen or device evidence.
- JS syntax passed; production plugin sources equal stable checkout `2e3f738`.
- Ten official destination routes returned HTTP 200 by read-only GET. This is
  link availability evidence, not a functional quote/appointment submission test.
- Current compact-hero/inline-interactive V3-R1 bundle: 27,640,339 bytes; SHA256
  `1ece519906ba675df0b8c98c4f82af69b1b4657b2971024f1a55e59e3a25576b`.
- R1 fixes legacy touch-cancel cleanup, manual-drag rotation-button state, and
  offscreen hero playback, including background return and no-observer fallback.
  Vendor bytes, HTML and CSS remain unchanged; native production sources unchanged.
- All six cube-face bytes and embedded behavior in the actual export were checked;
  libraries retain their exact pinned package bytes and local license files.
- Original camera is pitch -27/yaw0/hfov92, faces front/right/back/left/up/down.
  Near-viewport initialization; visible-only rotation, reduced-motion preference,
  one manually played panel video at a time, panel/background pause, error poster
  and late-load guard. No-IntersectionObserver fallback also initializes visibly.
  Native dialog zoom, focus return and language changes retain viewer identity.
- Codex opened the local preview for user review. Browser automation explicitly
  disallowed reading file URLs; no alternate transport/browser workaround was
  attempted. Responsive CSS was reviewed, but NO real-browser mobile, pixel,
  screenshot, actual video decoding or device test is certified.

The original conditional local-preview release request was superseded by explicit
direct production-integration authorization. An opened local tab is not evidence.
Production integration used a separate live backup,
scoped integration preserving native quote/appointment owners and live HU/Chinese
controls, staged/formal PHP lint, cache handling, and real
desktop/narrow-viewport/core-flow regression after scoped publication. Stable live
release is recorded separately. No rollback is needed for this offline artifact.
Production integration must also namespace preview styles, retain the native
language/header/form owners, use live apartment data and never apply the offline
CSP to the production document. The released adapter implements these boundaries;
see the separate release record for actual evidence and fullscreen/device limits.
