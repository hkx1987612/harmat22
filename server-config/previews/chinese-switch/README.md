# Internal Chinese Switch - Local Prototype

Status: local prototype, NOT deployed, NOT a full-site Chinese release.

The user explicitly selects an internal reviewed dictionary, no external
translation provider. Existing approved Project preview and byte-identical
images are reused without modifying that original source. `switch.js` changes
only matched text nodes and three accessibility/image-description attributes.
No input values, option values, links, event handlers, inventory or money values
are translated. Unsupported text stays original. Switching back restores text.
There is no cookie, storage, model, API, tracking or paid service.

`form-fixture.html` is an isolated, non-submitting control fixture, NOT the live
offer form. Its example unit/area are not a live availability/price snapshot.
Submission is disabled, prevented in JS and blocked by CSP; no nonce or endpoint.
Never use this as evidence of a successful booking or native integration.
Known live modal roots are excluded by the prototype as another safety guard.

Build with:
`python -B server-config/maintenance/2026-10-17-build-chinese-switch.py --write`

The exclusive generated file is ignored under
`outputs/2026-10-17-chinese-switch/harmat-chinese-switch-v1.html`.
All five reviewed images, CSS, dictionary and script are embedded; the script is
CSP hash-allowlisted, all resource requests and form submissions are denied.
External site links intentionally still open the original Hungarian website.
No persistence/cross-page translation is claimed in this prototype.

DOM test prerequisite (local development only, never on the server): install
`jsdom@26.1.0` with scripts disabled under the ignored
`outputs/2026-10-17-chinese-switch/` folder, then run
`node --test server-config/maintenance/2026-10-17-test-chinese-switch.cjs`.
The 14 cases are DOM/behavior tests, not pixel, mobile, native WordPress or
successful end-to-end appointment evidence. No browser was used to bypass the
previously refused local-file navigation.

Before any live release: native full-render integration, public/private route
scope, HU-default/no-script fallback, controlled translation coverage, native
text-dependent offer triggers, dynamic selector labels/status/errors, modal
and source-value parity, mobile/keyboard/screen-reader checks, cache/cookie and
canonical review. Do not bypass source guards of the current family plugins.
Backup, temporary/formal PHP lint, atomic activation and native regressions
are mandatory. No positive test inquiry without fresh explicit approval.
