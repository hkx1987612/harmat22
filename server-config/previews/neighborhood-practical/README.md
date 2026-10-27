# Neighborhood Practical Review

Local-only response to the October27 request to remove the page's AI/template
feel. No deployment or edits to the live site's source, quote/PDF, bookings,
business records, analytics, ads, security or cache settings.

## Design And Content

- Real approved2024 Ohegy park photograph, not generated media or a rendering.
- Compact navigation and practical searchable/filterable list instead of repeated
  promotional cards, invented-layout circle map and meta-marketing commentary.
- All17 existing public facility names and approximate straight-line distances
  retained verbatim. They are not newly surveyed/current route measurements.
  Old estimated walking/transport-time cards are not carried into this proposal.
- Local HU/ZH display copy, five categories, map-search links. Generic facilities
  are not represented as precise independently verified named locations.
- Optional3D uses the existing Pannellum library and original six cube faces,
  with rotation/zoom/reset/fullscreen, six-image gallery/lightbox and two original
  planning videos. Clearly labelled planned renders, not completed buildings.
- Review header/footer are isolated preview chrome, not replacements for native
  production navigation/forms. External contact/map links are normal public
  destinations; preview itself has no forms, backend, accounts or customer data.

## Build And Review

```powershell
python server-config/previews/neighborhood-practical/build.py --capture
python server-config/previews/neighborhood-practical/test_preview.py
node --check server-config/previews/neighborhood-practical/preview.js
python server-config/previews/neighborhood-practical/serve.py
```

`--capture` performs ONE bounded public GET and extracts structured rendered HTML;
reviewed source-name/order assertion prevents silent Chinese translation remaps.
Subsequent builds without that flag are offline. Generated HTML, public snapshot,
original media, pins, screenshots and logs stay ignored under
`outputs/2026-10-27-neighborhood-practical/`. Public-only `facilities.json` is a
source input. Original8MB park photo is reused byte-exact, no compression or image
generation; future production image delivery optimization requires separate work.

Review server: http://127.0.0.1:8795/ . It serves only the dedicated artifact root
and two exact original local video aliases, with path traversal checks, noindex,
no-store and no POST handler. It is not a public site or live workflow test.

Before a future release: user must approve the visual/content direction, then
fresh backup/source ownership gates, native form/booking compatibility, HU/ZH and
desktop/mobile tests are required. Do not deploy the preview shell or replace the
large shared performance plugin merely to change this page. Preserve all17POIs
and the October20 neighborhood preservation fix.

## October27 Verification

- Eleven offline invariants and JavaScript syntax checks pass. Production WP
  plugin/source paths have no diff. No form or customer submission was made.
- Actual IAB responsive checks at1280x720,390x844 and320x740: original image
  loaded, HU/ZH readable, no document horizontal overflow. Small Chinese title
  keeps the two-character place label together. This is not physical-phone QA.
- Category filter4 education entries, accent-insensitive `szent laszlo` match1,
  Chinese `小学` match1, missing term0 and reset17 verified. Mobile menu opens,
  closes with Escape, and keeps language controls visible.
- Original cubemap renders nonblank; actual desktop/mobile drag changes image,
  zoom/reset/rotation and fullscreen entry/exit work. Gallery next/close restores
  thumbnail focus; mobile dialog fits. Video tab retains native controls, no
  autoplay and preload-none; full original video playback is not certified.
- Actual screenshot pixel crops: desktop variance8591.38 / mean drag delta22.32;
  mobile variance4261.70 / mean drag delta47.30 (RGB0-255). These establish
  nonblank/changing canvas, not frame-rate or real-device performance.
- No browser warning/error entries at final checked state. Original park file
  remains about8MB; no cold-network or Core Web Vitals performance claim.
- Screenshots under ignored output/proof only. Review remains LOCAL, not live.
