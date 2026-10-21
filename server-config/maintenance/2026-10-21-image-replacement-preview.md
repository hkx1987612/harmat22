# Image Replacement Comparison V1 - Local Review Only

## Superseding User Decision

The user subsequently cancelled all website image replacement and clarified that the task is paid ChatGPT advertising only. This preview is historical, NOT a deployment queue. No mapping here may be deployed based on later ad-creative approval. Production images, quote/appointment/PDF and apartment data remain unchanged. The separate local ad draft is documented in `2026-10-21-chatgpt-ad-draft/README.md`.

## Scope

- User requests a before/after image comparison using previously approved project material, clear rendering/site-photo labels, then backup/deploy only AFTER approval. No production implementation or authorization to change quote/appointment data is inferred.
- User clarifies that sales-area differences come from balcony inclusion. Record that explanation; do not infer a 100% or 50% coefficient, alter indoor/sales/outdoor values, recalculate prices, change quote PDFs or appointment handlers. Further area work remains separate.
- Public site, WordPress/media metadata, source PHP, native forms, apartment data, ads and cache remain unchanged. No live backup/deployment/cache purge, source deletion, private-data access, form submission, paid service or generated architecture/floorplan.

## Deliverable

- Local HTML: `outputs/2026-10-21-image-preview/v1/index.html`.
- Same-computer review URL: `http://127.0.0.1:8789/` (local-only server, not public/staging site). Preview contains no forms, iframe, tracking, video, backend or quote/appointment code. CSP blocks network connections and frames; all displayed assets/scripts/styles are local. Public source links open only on explicit user action.
- Six before/after cases: about-page OG/WebPage cover -> already adopted project courtyard; A1-F-L1/A4-F-L1/A3-F-L3 generic covers -> exact existing public floorplan images; five unverified legacy gallery interiors represented by one clearly labelled example -> retain approved project interior with explicit illustration/non-delivery-standard description and deduplicate; low-resolution gallery cover -> already approved project aerial rendering.
- The legacy group is explicitly a representative1/5 preview, listing all five filenames, NOT proof of every image's origin. Original files must not be deleted; proposed removal of their public references remains unapproved. Do not insert the same approved interior five times as replacement. Old upload paths alone do not prove unauthorized or unrelated content.
- Three additional existing-material caption examples, not new replacement proposals: actual construction poster2026-10-20, actual sales showroom/non-model-apartment, nearby public park/not private project garden. Unknown park date stays unverified; no claim it is the company's renovated park. Hungarian and Chinese labels/captions are explicit.
- Relevant source URLs, proposed captions, current/proposed category and chosen local assets are in `2026-10-21-image-preview-manifest.json`. This is an immutable PREVIEW_V1 mapping, not live WordPress configuration or approved publication queue.

## Media Handling

- Reused existing local courtyard/interior/aerial/showroom/park/construction originals byte-for-byte. Eight bounded sequential public same-origin image GETs acquire old comparison assets and the three existing public floorplans; max5MiB/asset, verified image response and image format/dimensions. No media downloaded to bypass a browser restriction; these are local assets for the requested standalone review artifact.
- Floorplans remain original649x933(A1),765x1009(A4),902x460(A3), no redraw/rotation/crop/label/data edits. CSS contain preserves the full image; native dialog exposes a larger original view. Portrait floorplans are NOT a production1200x630 social export; final platform crop/preview behavior needs validation after approval, without cutting the drawing or changing the original.
- Source/hash/size/dimension manifest is ignored `v1/asset-pins.json`. All media/screenshots/output generated HTML remain ignored, not committed to GitHub. New architecture/photos or housing features are not invented. Reuse of material previously approved on the website does not constitute independent title/licensing certification.

## Verification

-13 offline tests pass: preview status/manifest identity, six pairs/three references, no submission/tracking elements, local-only rendering, exact image bytes and retained local originals, exact room-code URL mapping, HU/ZH caption coverage, rendering/real-public-park qualifiers, noindex/CSP, accessible enlarge controls, area non-inference. JavaScript syntax passes.
- Actual browser1280 desktop:15 main images load/contain, no page overflow or form/frame.390/320 responsive: one-column comparisons, all images loaded, zero measured text-overflow nodes, no horizontal overflow.390 A4 dialog374.39px wide/592.86px high within844px viewport, original765x1009 intact; open/close and focus return. A3 original902x460 enlarged and exact filename/caption confirmed.
- The first screenshot immediately after resizing an open dialog had a scaled capture artifact; closed/reopened at stable390 and verified the actual image/viewport geometry. That first capture is not final proof. No physical-mobile certification.
- Ignored final screenshots: `outputs/2026-10-21-image-preview/proof/desktop.jpg`, `mobile-320.jpg`. Temporary browser viewport reset. Preview tab opened for user review; original website not navigated/edited. Native preview server bound127.0.0.1:8789, started hidden, PID22156 for this session; server script restricts serving to its preview directory, directory listings disabled. Do not expose this server publicly. Future processes may use different PID/port.

## Regeneration And Approval

```powershell
python server-config/maintenance/2026-10-21-build-image-preview.py
python server-config/maintenance/2026-10-21-test-image-preview.py
```

Builder refuses to overwrite an existing preview. A revision needs a new output version, preserving reviewedV1 assets/pins. Tracked CSS, JavaScript and loopback server sources are under `server-config/maintenance/2026-10-21-image-preview/`; the builder copies them into ignored output paths, including `outputs/2026-10-21-image-preview/serve.py`. These are not production website components. Regeneration requires the existing approved local media named in the manifest and the previously retained local logo/Lucide assets. Local static files also open independently of the server in a normal browser.

After the user confirms the selected replacements/captions, take a fresh private backup of the exact live owners/metadata and build a narrow candidate. First synchronize about/property/gallery head-image metadata and approved gallery references/labels with one authority; no blanket media-library deletion, no source-data/quote/appointment upgrade. Validate real desktop/mobile/language/native blank forms and nonowned HTML/protected state, then deploy/recheck and record an independent rollback. Any unknown image, change of room mapping, public park investment statement or area coefficient requires clarification before implementation.
