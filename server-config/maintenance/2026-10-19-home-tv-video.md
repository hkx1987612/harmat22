# Full-Width Desktop Video And TV Fullscreen

## User Request And Scope

User supplies a photo of the television showing large side margins, and explicitly
wants full-width playback without cutting edges. Earlier viewport-height cap is
therefore superseded. Desktop natural16:9 section grows with actual page width;
ordinary browser chrome/header may require scrolling to see its bottom. A pure
video fullscreen control is added for16:9 television display without those bars
or webpage title/menu overlays. This geometric distinction was explained before
editing. No promise to fill a different-aspect screen without bars/cropping.

Mobile visual layout, original media, existing autoplay/manual-pause/reduced-motion
ownership, pinned body/CSS/JS/manifest, quotation/appointment/data/payment/ads stay
unchanged. New removable homepage-only fullscreen addon; no provider/storage,
network call, automatic fullscreen, purchase, upload or browser security change.
Native fullscreen requires a user click. Existing user tabs/drafts are untouched.

## Status

RELEASED and independently VERIFIED. Fresh private backup:
`/home/harmath2/codex-backups/home-tv-video-985c04daa74749979c8ba7b857e08476`.
Aggregate: `c23029ca4e01b71a30c6264cfd0e7f362b6e7fdfd9b4e6b83fb3631e14562a07`.

Home controller1.0.12 SHA256:
`b1aab40a51dab20d042a3a966e426fe6ff8cfe10bfe992e852acbd551da82d8b`.
Only version and desktop final max-height cap changed from1.0.11/a42ba47.
New removable fullscreen MU1.0.0 SHA256:
`38383bbaacb876e0b3de4b63d22fc23c6f29679784e3d4cf99e56d9ddd9d4580`.
It fullscreens the hero, hides owned title/actions/gradient/label, and retains
original pause ownership. Video attrs, time, mute and playback are not rewritten.
An initial literal-video-fullscreen candidate was local only, never deployed.

## Verification

- Local28 JavaScript DOM,42 PHP scope,76 home controller and6 strict transport/
  ownership/recovery cases pass. Production PHP8.3 staged42 scope checks pass.
  Temporary/formal PHP lint, atomic activation and cache purge completed.
- Release and separate verification both pass9 public routes200/no fatal,
  native form/SEO equivalence, protected source/page/business fingerprints,
 124 inventory records/native authority parity,35 pure offer and17 spam checks.
  No REST dispatch, real customer/form/mail submission or database recovery.
- Actual IAB390x844 retains375x520 cover/58%center mobile media, no horizontal
  overflow, hidden fullscreen control. Native298px quote opens/closes with empty
  contacts and required unchecked privacy; no send. Desktop1265x711.5625 video
  starts at header bottom76 and has exact16:9 ratio without intrinsic bars/crop.
- Actual IAB fullscreen enters1280x720, hides copy, plays muted; original pause
  works, exit preserves manual pause, resume works. After resetting the viewport
  override, actual native1920x1080 fullscreen again enters/exits successfully,
  video advances and fills its exact16:9 frame without bars/crop. Focus returns
  to the own fullscreen button. HU/ZH enter labels and roundtrip pass; HU restored.
- Clean native screenshot (ignored):
  `outputs/2026-10-19-home-tv/video-fullscreen-final.jpg`.
  Earlier `video-fullscreen.png` under forced viewport override has screenshot
  scaling artifacts; it is NOT final visual proof and must not be delivered.
- Chrome normal layout also confirms fullwidth exact16:9 below76px header.
  Chrome automated fullscreen clicks are rejected gracefully; this is NOT a
  Chrome/physical-TV fullscreen pass. User asked to refresh and click with a
  real mouse on the TV; hardware confirmation remains pending. No browser security
  workaround, physical-phone/Safari/CWV or inbox-delivery claim. Different-aspect
  screens cannot simultaneously fill all dimensions and preserve the whole frame.

## Recovery

Use the exact adapter with `--rollback` and this backup/aggregate. It removes only
the exact owned addon and restores the exact predecessor home source, retaining
foreign bytes; it never restores customer records. Independent read-only check:

```powershell
python server-config/maintenance/2026-10-19-update-home-tv-video.py --verify /home/harmath2/codex-backups/home-tv-video-985c04daa74749979c8ba7b857e08476 --expect c23029ca4e01b71a30c6264cfd0e7f362b6e7fdfd9b4e6b83fb3631e14562a07
```

Only sources/tests/maintenance records are shared. Private backups, screenshots,
HTML/nonces and media remain untracked. Stable tag after sync:
`stable-2026-10-19-home-tv-video-current`.
