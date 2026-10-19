# Homepage Video Continues While Scrolling

## Scope

User clarifies the reported pause is caused by scrolling the webpage and explicitly
requests continued playback while scrolling. Actual pre-change IAB reproduction:
playing at69.318831s; Ctrl+End leaves hero bottom-5239.0625px and video paused
at73.452453s; Ctrl+Home resumes. This is the intentional old viewport pause rule,
not evidence of a corrupt video or insufficient bandwidth.

Only the homepage hero viewport gating changes. Manual pause, hidden-document
pause/resume, reduced-motion/manual-play, media failure/poster, source and time
ownership remain. No picture-in-picture, sticky overlay or new media download.
Panorama, layout, fullscreen, construction playback, languages, forms, quotations,
appointments, customer data and ads are outside the edit scope. Scrolling away
will now continue video decoding/buffering; hidden tabs still pause.

## Release Design

New versioned JavaScript and manifest retain all original files. Publish both
new files first, atomically switch the exact controller last, purge caches; stale
cached HTML still references its retained original assets. Recovery restores only
the exact controller first and removes owned new files, never customer records.
Default adapter mode is offline and live actions require the reviewed aggregate.

## Release And Verification

RELEASED and independently VERIFIED. Fresh private backup:
`/home/harmath2/codex-backups/home-scroll-play-55dd4d1391cd428ba71b9eccfade4683`.
Aggregate: `11bffac77540fa275fd68dcda49a1bd62108a2a2c03b00635fd18452044b65d7`.

- Controller1.0.13 SHA256
  `af6f0206b31a430d7acde7a3eac14e304d39220256a1a97083e858eaee59c787`.
  Only version, versioned manifest path/hash/required key, and selected JS change.
- New runtime SHA256
  `063358c160bcdbdf24c361f8d3350d890bb11b2fb8df0d129a4c30039e124e22`.
  Exact original-to-candidate source allowlist enforced. All panorama bytes remain
  identical; remove only hero viewport state/predicate/observer/fallback, initialize
  playback directly, and correct one comment about document visibility.
- New manifest SHA256
  `4916615afd43d01394b22b362e5e4878f9eca02ee086791cbb0081b52bbf7b81`.
  All six original pins retained, one new JS pin added. Original JS/manifest, CSS,
  body, vendor, fullscreen source, media, native forms and business sources intact.
-19 focused JS/API-stub cases,76 controller PHP cases,7 fake-transport cases,
  syntax/lint/whitespace pass. Fullscreen regression suite's old strict1.0.12
  source assertion and offscreen policy were superseded; updated exact-source
  allowlist and fixture now use current runtime, all28 cases pass. This is not
  evidence that all historical release verifiers accept newer authorized sources.
- Server PHP8.3 staged private controller/assets mirror76 cases, temporary/formal
  lint, assets-first/controller-last atomic activation, cache purge pass. Release
  and separate verify both pass9 routes200/no fatal, native form/SEO equivalence,
 124 inventory/native authority parity,35 pure offer+17 anti-spam checks and
  protected source/page/business fingerprints. New script absent on search,
  propertyA1-F-L1, contact and buildingA1 selector routes.
- Actual visible IAB post-release desktop: hero wholly above viewport at bottom
  -5239.0625px, paused=false, time3.534656->14.175268s. Manual pause at15.134833s
  survives Ctrl+End/Home unchanged; manual resume works. Mobile390x844 hero375px,
  bottom-8279px, paused=false/time23.720682->30.298856s, no horizontal overflow.
- Actual mobile HU/Chinese roundtrip and native298px quote open/close: contacts
  empty, required unchecked privacy, original source/date/time controls present.
  No send. Native desktop1920x1080 fullscreen enters/exits while playing at
 78.456539->88.104732s; original copy restored. HU/defaultviewport restored, formal
  homepage retained. Clean ignored screenshot:
  `outputs/2026-10-19-home-scroll-play/after-scroll-playback.jpg`.
- Hidden-document/reduced-motion/failure/panorama policies have focused stub tests;
  no new actual background-tab, physicalTV/phone, Safari, CWV or inbox-delivery
  certification. This removes intentional scroll pauses, not a guarantee against
  all network/device rendering stutter. No actual form/mail/customer write.

## Recovery And Shared Snapshot

Independent check, or replace `--verify` with `--rollback` for owned-source-only
recovery. Foreign controller bytes retain their dependencies and stop recovery.

```powershell
python server-config/maintenance/2026-10-19-update-home-scroll-play.py --verify /home/harmath2/codex-backups/home-scroll-play-55dd4d1391cd428ba71b9eccfade4683 --expect 11bffac77540fa275fd68dcda49a1bd62108a2a2c03b00635fd18452044b65d7
```

Stable tag after sync: `stable-2026-10-19-home-scroll-play-current`.
Only source/tests/records shared; no private backups, HTML/nonces, media or screen
captures in Git. Earlier shutdown, ads/social, Messenger and SEO tasks not resumed.
