# LOCAL PREVIEW / NOT DEPLOYED

Historical preview reference: the user subsequently approved this design and the
separate production MU is now released. Current tag
`stable-2026-10-17-project-family-current`; exact backups, native integration,
verification and recovery are in `server-config/maintenance/2026-10-17-project-family-release.md`.
This standalone preview shell itself was not deployed. The preview-only notes
below record the original review checkpoint.

Static visual proposal for `https://harmat22.hu/harmat-lakopark/`.
This folder is not a WordPress release, native theme replacement, booking
integration, or deployment approval. No production files or records are changed.

## Scope

- `index.html`: Hungarian project introduction with a contextual header/footer.
- `project-family.css`: local stylesheet; every selector is rooted under
  `.hpf-preview`, with `hpf-` classes and custom properties.
- `README.md`: scope and handoff notes only.
- The parent owns image assets, provenance, the self-contained HTML exporter,
  regression tests and final review. This implementation does not change them.

## Design

The page introduces the product, not the company story: aerial hero, four project
facts, courtyard and practical project rows, an archival construction panel,
interior illustration, then apartment-search and contact links. White surfaces,
sage, forest green and restrained gold match the recent About/Contact direction.
Content is constrained to 1240px; the hero is full bleed. A shallow light wash
behind the title leaves the buildings undimmed. Hero height reserves space for
the facts below on ordinary desktop/mobile viewport heights; actual viewport
framing still needs visual review.

All four `.hpf-fact-number` values use the same fixed 36px Arial, normal weight,
lining/tabular figures at every breakpoint. The square-metre unit and quarter
are separate subordinate spans. Section headings step through 34/28/24px;
Georgia is reserved for headings, with Arial/Helvetica for body and figures.
There are no feature cards, invented amenities, availability counts, prices,
gift-garden claims, or guaranteed dates.

## Assets

All image paths are relative to this directory and supplied by the parent:

| File | Width | Height | Treatment |
| --- | --- | --- | --- |
| `assets/project-aerial.jpg` | 1920 | 1080 | Hero, eager, high fetch priority |
| `assets/project-courtyard.jpg` | 1920 | 1080 | Lazy, rendering disclosure |
| `assets/project-living-room.jpg` | 1920 | 1080 | Lazy, rendering disclosure |
| `assets/construction-site.webp` | 1920 | 1440 | Lazy, dated real site photo |
| `assets/harmat-logo.png` | 250 | 250 | Existing brand, footer copy lazy |

The aerial is the approved official panorama. Its parent-verified SHA256 is
`efc5fc46c290fc4861ac37d309a4456d8b8866d37b5699ba742546e3ebb9c25d`.
Asset provenance/export certification remains with the parent.

All three rendering scenes are labelled `Látványterv / illusztráció`.
The real image is labelled `Helyszíni felvétel 2026. október 2.` with an explicit
archival disclosure. It is not a claim of current completion or a latest update.
The June 12, 2026 sales opening is expressed in the past tense. Expected first
phase handover is 2028 Q2, qualified by construction scheduling and contract.

## Behavior And Boundaries

- Exactly one linked stylesheet; no inline styles, scripts, forms, SVG, iframe,
  video, autoplay, external fonts, tracking, storage, API calls or dependencies.
- No `srcset`, CSS asset URLs, imports or font-face declarations.
- Source HTML has no CSP; the parent's self-contained bundle adds its policy.
- Navigation uses known official full URLs, real local anchors, and the official
  `tel:+36300733375` / `mailto:ertekesites@harmat22.hu` contacts.
- Mobile navigation is native `details`/`summary`, without JavaScript. It is a
  disclosure, not a custom modal or a replica of the production menu.
- The visual header/footer provide context only. Contact actions are ordinary
  navigation links, not tested native appointment or offer-modal triggers.

## Verification Status

Implementation is limited to static source checks. No browser, local server,
headless session, network request, live-site edit, Git staging or commit is part
of this work. A prior local-file browser refusal must not be worked around.
Actual 2400px/1440px/390px rendering, mobile menu interaction, contrast over
photos and absence of visual overflow remain pending real visual review.
Parent-owned export tests do not imply native booking integration is tested.

## Future Integration Requires Approval

Parent final checkpoint: all16 offline source/original-image-decode/portable-bundle
tests pass. Run `python -B server-config/maintenance/2026-10-17-test-project-family-preview.py`
from the repo root. Run `python -B server-config/maintenance/2026-10-17-build-project-family-preview.py --write`
once to create the exclusive portable HTML under ignored `outputs/2026-10-17-project-family/`.
No separate image/CSS files are needed to view that generated artifact. Its exact
source provenance/hash and limitations are in `2026-10-17-project-family-preview.md`
under maintenance. These offline checks do not certify actual browser rendering.

Do not deploy this standalone shell. A separately approved integration must
preserve the real native header, navigation, footer, cookie/legal controls,
SEO/canonical ownership and existing appointment/offer modal. Only the approved
project-page body should be adapted with page-scoped styles and a single H1.
Require an explicit implementation approval, rollback path, source/data guards,
native-theme preflight and desktop/mobile regression checks before any release.
