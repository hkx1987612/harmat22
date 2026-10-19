# Inline Homepage Assistant Entry

## Follow-Up: Lower Hero Copy

User says the centered title/action group is still too high. Current1.0.1 adds
only desktop non-fullscreen `position:relative;top:64px` to the copy group, plus
the version bump. Left margins, JS, assistant placement, mobile/fullscreen, media,
loop/scroll playback, quotation and appointment remain unchanged. Exact predecessor
is c43260f/SHA449ffd8868549b5eba50a15e84efa9883517b828e678b96b987ca9ebcc1a9f71;
candidate SHA3bce85ddad7b4b59948293536b2fbd193bb21b820b6384c9bdbb937a529125bd.
55 DOM,79 PHP and5 existing-source transport/recovery cases pass. Fresh private
backup home-hero-lower-abbb92741ce241ed997b3f731993eda2, aggregate
a4131534ac5c20801756e1830e571af292fae0620749325d2291cabd3b4458bd.
RELEASED and independently VERIFIED. Both checks pass9routes,
124 inventory, nativeform/SEO/protectedstate,35 offer+17 spam checks, staged79PHP
and atomic/lint/cache flow. Actual visible IAB983px hero: copy center exactly64px
below video center, left24px unchanged; copybottom468.265625 remains above hero
bottom628.9375. Looptrue, assistantstatic. Actual390 mobile retainsstatic/topauto/
flex-end and nooverflow; defaultviewport restored. Clean ignored proof:
`outputs/2026-10-19-home-assistant-inline/hero-lowered.jpg`.
No form/chat/lead/mail submission or changes to playback sources/data.

Independent check (replace `--verify` with `--rollback` for exact source recovery):

```powershell
python server-config/maintenance/2026-10-19-update-home-hero-lower.py --verify /home/harmath2/codex-backups/home-hero-lower-abbb92741ce241ed997b3f731993eda2 --expect a4131534ac5c20801756e1830e571af292fae0620749325d2291cabd3b4458bd
```

Stable tag after sync: `stable-2026-10-19-home-hero-lower-current`.
The following sections describe the retained1.0.0 baseline behavior.

## Scope

User requests moving the intelligent-customer-service button off the screen
because it obstructs the homepage video. Move the original launch button into
the apartment-section action row below the video, next to the existing apartment
search link. No floating duplicate, new banner or auto-open. The existing panel
still opens only after an explicit click; original node, handlers, label/icon,
language bridge, chat draft/history, modal suspension and conversation service
remain owned by existing modules.

Homepage-only removable MU addon, no edits to original home/HAP/assistant sources,
media/playback/loop, quotation/appointment APIs, data, payment or
ads. Unknown/ambiguous DOM fails closed to the original entrance. Before-change
actual IAB launch is BODY child, fixed, data-hap-ready1, collapsed; it covers the
lower-right viewport (top814..862 in the observed desktop page).

User's additional screenshot request during implementation: move the hero brand
and both chooser links to the vertical center toward the left edge, with a safe
margin. Include separate desktop-only, non-fullscreen hero-inner alignment CSS
in the same unreleased homepage presentation addon. No video dimensions/cropping,
media, type sizes, links, mobile framing or fullscreen behavior changes.

## Release And Verification

RELEASED and independently VERIFIED. New removable addon1.0.0 SHA256:
`449ffd8868549b5eba50a15e84efa9883517b828e678b96b987ca9ebcc1a9f71`.
Fresh private backup:
`/home/harmath2/codex-backups/home-assistant-inline-be1f978acaac42eaa1ff2b581f6d752b`.
Aggregate: `bfbd2984914cbb9a83c0d9b63b7f12e529a3dadb69927f4eacf59cd8429fdbc9`.

-79 PHP scope checks,54 DOM/API-stub tests,7 fake-transport cases, PHP lints and
  diff checks pass. Existing scroll-play19 and fullscreen28 regression tests pass.
  Source reviewed independently. Sol source/tests worker completed and closed.
- Server PHP8.3 staged79 scope checks, temporary/formal lint, atomic absent-only
  installation and cache purge pass. Release and separate verification both pass
 9 routes200/no fatal,124 inventory/native authority parity,35 pure offer+17 spam
  checks, exact native form/SEO and protected source/page/business fingerprints.
  New resources absent on search, propertyA1-F-L1, contact and buildingA1 selector.
  All original home controller/manifest/runtime/assets/fullscreen, HAP assets and
  native assistant/quotation/appointment sources remain unchanged.
- Actual visible IAB: launcher is same single button, now position:static inside
  harmat-home-assistant-actions below video (top924.203125 before scrolling), no
  floating duplicate. Native panel opens and closes, collapsed/expanded state and
  focus return work without sending a question. Existing opening telemetry may run.
- Actual desktop983px hero: title group left24px, top300.671875/height103.59375,
  hero top76/height552.9375, exact vertical center.1280 viewport hero1265px/title
  left48px/center difference0, no overflow. Media1920x1080 and dimensions unchanged.
- Actual320 viewport: Chinese launcher label follows native page switch, static,
 91px wide; actions208px fit. HU actions273px wrap131.25px link and133.03px button
  on separate lines atx16, no horizontal overflow. Native chat panel285px opens
  with empty input and closes. Mobile hero remains flex-end, not newly centered.
- First click immediately after a viewport resize opened HU offer instead of the
  intended language control. Inspected actual state rather than repeating blindly:
  native228px offer had empty contacts/unchecked privacy, launcher correctly
  suspended/display:none. Closed offer, re-read current control bounds; separate
  HU/Chinese switch and chat open/close then passed. No application defect inferred
  from that automation/settling incident, no forced click or security workaround.
- Default viewport/HU restored. Actual fullscreen enters1920x1080 with copy hidden,
  loop true/pausedfalse, exits to983px/copyblock/enterlabel/playing. Formal homepage
  retained. No claim of new physicalTV/phone/Safari/CWV or message-delivery proof.
- Generated private proof (ignored):
  `outputs/2026-10-19-home-assistant-inline/hero-center-left.jpg` and
  `outputs/2026-10-19-home-assistant-inline/assistant-inline.jpg`.
  No chat question, lead, quotation/appointment send or customer-data write.

## Recovery And Shared Snapshot

The addon was originally absent. Replace `--verify` with `--rollback` below to
remove only exact owned bytes; foreign source is retained. No DB/customer rollback.

```powershell
python server-config/maintenance/2026-10-19-deploy-home-assistant-inline.py --verify /home/harmath2/codex-backups/home-assistant-inline-be1f978acaac42eaa1ff2b581f6d752b --expect bfbd2984914cbb9a83c0d9b63b7f12e529a3dadb69927f4eacf59cd8429fdbc9
```

Stable tag after sync: `stable-2026-10-19-home-ui-placement-current`.
Only source/tests/records shared, not private backups, HTML/nonces, screenshots or
media. Other pages retain their original launcher. Earlier pause-button hover-only
proposal was not implemented; native pause control remains. No old tasks/shutdown.
