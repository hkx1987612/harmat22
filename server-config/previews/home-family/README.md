# Warm, Practical Homepage Preview

Status: OFFLINE DESIGN REVIEW ONLY, October 18, 2026. Not deployed.

User approved Sol implementation of a local preview, with publication only after
review. Quote and appointment behavior must not change. Sol authored the three
presentation files here; parent review added packaging and no-network tests.

## Design

- Original project video and aerial poster; next section remains discoverable.
- White, restrained sage/forest and small gold accents; no invented family photos.
- Three genuine floorplans before the dated construction report and lifestyle images.
- Project courtyard/living-room renderings are labelled as renderings. The nearby
  public park is explicitly not the development's internal completed garden.
- Compact showroom, virtual-selector link, team and official sales contacts.
- Independent desktop navigation, mobile menu and local HU/Chinese controls.
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
fetches integrity-verified `lucide@0.468.0` from the official npm registry. It also
requires the previously approved local native video, nearby-park image and prior
reviewed project preview identified in the helper. These ignored media inputs
are NOT included in Git. A second computer needs the approved media separately;
do not substitute guessed assets or commit raw video. Public facts/manifests and
the Lucide license are staged beneath the ignored output folder. DOM tests use
the existing `outputs/2026-10-17-chinese-switch/node_modules/jsdom` installation.

Open the generated file directly, without a dev server:

`outputs/2026-10-18-home-family/harmat-home-family-preview-v1.html`

Images, stylesheet, Lucide and behavior are embedded. The original 39,504,516-byte
video remains at sibling `assets/hero-video.mp4`; moving only the HTML retains
the visual poster but loses video playback. No remote display dependency exists.
Generated exports and media remain ignored; Git shares only source/tools/records.

## Verified / Pending

- 12 source/no-network JSDOM tests and 10 synthetic packaging tests passed.
- JS syntax passed; production plugin sources equal stable checkout `2e3f738`.
- Ten official destination routes returned HTTP 200 by read-only GET. This is
  link availability evidence, not a functional quote/appointment submission test.
- Final bundle: 5,988,942 bytes; SHA256
  `95fbf160a1caa1dda4efc2a087b5c02f0a4fe366fdfec491428fd24d6c731086`.
- Codex opened the local preview for user review. Browser automation explicitly
  disallowed reading file URLs; no alternate transport/browser workaround was
  attempted. Responsive CSS was reviewed, but NO real-browser mobile, pixel,
  screenshot, actual video decoding or device test is certified.

User design review and real desktop/mobile visual checks remain pending. This
is NOT deployment approval. Future integration needs a separate live backup,
scoped integration preserving native quote/appointment owners and live HU/Chinese
controls, staged/formal PHP lint where applicable, cache handling, and real
desktop/mobile/core-flow regression before publication. Existing stable live
release/tag remains unchanged. No rollback is needed for this offline phase.
