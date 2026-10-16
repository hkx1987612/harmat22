# About Page - Family-Warmth Visual Preview

APPROVED AND RELEASED October16. This directory remains the standalone review reference; the production integration is a separate page-scoped MU, not this preview shell. The earlier preview-only checkpoints below are historical and superseded by the release note.

## Scope

User requests a redesign of `/magunkrol/`, specifically better color, type, layout and light, with a warm family feeling. Astra inspected existing repository contracts; Sol implements the bounded static preview and the parent reviews it. User subsequently approved replacement. Current production reference: `stable-2026-10-16-about-family-current`; source, exact private backups, tests, native-shell integration and recovery are in `server-config/maintenance/2026-10-16-about-family-release.md`.

Open `index.html` directly. Images, CSS and navigation JS are now relative local files; no network resources are required to display the preview. No build step, analytics, production scripts, API, form submission, storage or tracking. Links navigate to the existing public site only when deliberately followed. The preview header/footer are visual context, not changes to the production shell. Quote/contact navigation is not a tested or replaced production booking modal.

## Design and Data

- Bright white, soft sage and restrained forest/gold details; natural project imagery provides warmth, not a dark or beige wash.
- Readable normal-case headings, restrained typography, spacious but structured sections and responsive navigation.
- Gallery assets retain their original colors; courtyard and living-room visuals are project renderings, explicitly labelled illustrations. No fabricated finished-building photographs, fake residents or stock engineering images.
- Preserve approved398 planned total/124 first phase/expected2028Q2 distinction, existing professional background and official sales contacts. No price, eligibility, guaranteed handover, savings, availability or exclusive public-park claim.

Current public body identifies `page-id-2686`. This is DOM evidence only, not a protected WordPress record verification. Existing content is ordinary page `post_content` according to the prior direct-link migration. The production original contains mixed small-cap fonts, warm-yellow surfaces and repeated CTA sections; it also loads an unrelated stock engineering image. Original public HTML and reference images are saved only under ignored `outputs/2026-10-16-about-family/source/`.

## Safety and Future Deployment

The parent does not deploy this visual draft before the user reviews it. No existing source, database, WordPress content, property/quote/appointment data, cookie behavior, ads or social accounts is changed.

If later approved, use one independently removable, strictly page-specific MU with source/content identity guards and fallback to original content. Do not copy the standalone header/footer or preview contact behavior into production. Preserve existing menu, consent, SEO metadata/canonical, one visible H1 and native offer/appointment modal owners; guard against the existing first-H1 cleanup and offer-like text click interception. No global typography/CSS replacement. Back up first, stage/lint PHP, verify native render before atomic activation, purge relevant caches and regress key pages/forms without repeating the previously authorized positive inquiry test. Roll back only owned new files, not the database or unrelated changes.

Offline safety checks: `python -B server-config/maintenance/2026-10-16-test-about-family-preview.py` from the repository root. Browser screenshots and measured responsive checks, when available, are separate evidence; static checks alone do not certify appearance or production integration.

## Current Review Outcome

- Parent reviewed the three Sol files and corrected the living-room alt text to match the actual selected image. Hero copy is kept near the image's native left-side shadow across wide layouts; tablet/mobile use explicit smaller type and narrower copy, not a dark overlay.
- All12 offline safety checks pass; JavaScript syntax is checked separately. Both selected photographs were downloaded from actual gallery links and visually reviewed. Courtyard11 original SHA256 `b9081b36c296f5c1ff519467eb8e1d3ce9379f03f78e4f0b49e4c7ce9405ee78`; living-room13 `3d68545fda27820a95c976074a89254d8ac442471119c2acfa8e385340e7d0c4`.
- Browser capture of the existing production page times out; the viewport override did not change observed2400x1138 dimensions, so it is not responsive-device proof. Opening the new local file was explicitly blocked by the browser URL policy (http/https only). No alternative browser, local server, headless renderer or indirect workaround was used after the refusal. No screenshot, pixel-perfect or actual390px visual/interaction pass is claimed for this preview.
- The user can open `index.html` directly on their computer to review the direction. This is the visual-approval checkpoint, not approval or completion of production deployment. Existing website/quote/appointment functionality was not changed or retested by a new positive submission.

## Image Dependency Repair

- User reports missing preview images. The three original HTTPS image URLs independently return200 with correct JPEG/PNG MIME; a particular viewer policy, network error or hotlink rule was not established. The old preview's external-image dependency was unsuitable for a reliable offline handoff.
- Exact four image references now use three reviewed local assets: `assets/harmat-courtyard.jpg`, `assets/harmat-living-room.jpg`, `assets/harmat-logo.png` (logo repeated). Original source bytes retained, no cropping/recompression/image edit. SHA256 pins are enforced in the13 updated offline preview tests.
- Public provenance: courtyard https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-11.jpg ; living room https://harmat22.hu/wp-content/uploads/2026/02/Harmat22_latvany-13.jpg ; logo https://harmat22.hu/wp-content/uploads/2025/11/cropped-Harmat_Logo_250.png . Logo SHA256 `83d853c5000b336d1be4169cef51ad3cc0096c187eb5fa0b68673b053ec5604c`.
- For a completely portable file, run `python -B server-config/maintenance/2026-10-16-build-about-family-bundle.py --write` from the repository root. It creates an exclusive new ignored `outputs/2026-10-16-about-family/harmat-about-family-preview-v2.html` with four byte-identical base64 images, inline CSS and DOM-ready menu JS. It refuses unexpected/deviating resources and never overwrites an existing output. Default invocation only validates and writes nothing.
- The generated file is1,209,409bytes, SHA256 `71e17dc6327cfa22142694f0fcd0d140aeec4470be9bf575588c1ce99021c57b`. Its CSP permits only inline style/script and embedded images, forbids all default network resources and form submission. Deliberate public navigation links remain. Six additional package tests verify decoded image identity, no linked resources, DOM-ready timing, CSP, unchanged copy/contacts, unsafe-path rejection and exact generated-file readback.
- No attempt to bypass the earlier browser local-file refusal was made. Browser visual/mobile interaction verification remains unperformed, not retroactively certified by packaging tests. Only local preview and shared public assets/source changed; production site and quote/appointment functions untouched.
