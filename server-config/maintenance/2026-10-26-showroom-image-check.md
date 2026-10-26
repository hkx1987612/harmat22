# Homepage Showroom Image - Not Reproduced

User reports a broken showroom photograph, then confirms the HOMEPAGE (not the
contact page). Do not replace the photograph: user previously chose to wait for
a sharper original. This investigation is read-only; root cause remains unknown.

## Confirmed

- Direct image HEAD and full GET return200/image/jpeg/237920bytes. Server bytes
  and full public GET with homepage Referer match the approved original SHA256
  `ac4ad2578c1b44af479f5a0bcf9c07bb49e6f5a0245e24a65fc9a0b8e60eb1dc`.
- Homepage still emits the exact absolute image URL under
  `harmat-home-family/showroom-exterior-harmat.jpg`, width1706/height1280,
  native loading=lazy and decoding=async, no srcset/custom replacement.
- Fresh IAB1280x720 navigation: image initially unrequested below viewport,
  complete=false/natural0. Native focus/scroll into the section triggers loading;
  then complete=true/natural1706x1280 and an actual nonblank desktop screenshot.
  Initial offscreen lazy state is NOT evidence of a broken image.
- Fresh responsive390x844 HU/ZH: scroll-triggered original loads1706x1280,
  no document overflow, exact source unchanged in Chinese. Actual screenshot
  shows the full exterior/sign and caption with adjacent content. This is browser
  viewport testing, not physical-device or the user's failing session.
- Contact page model and lounge photos also load1707x1280; contact location
  photo remained an offscreen lazy placeholder at the sampled state, not a
  diagnosed failure. User subsequently confirms the homepage target.
- Saved ignored proof: `outputs/2026-10-26-showroom-check/homepage-showroom-desktop.jpg`
  and `homepage-showroom-mobile-zh.jpg`. No image editing/recompression/replacement.
- Default viewport and HU restored. No live source/data/cache/security/ad/form
  changes, no form fill/submission/mail. Repository baseline61c45a3; no deployment
  or whole-production-source verification was performed in this investigation.

## Log Limits

Read only the two existing October domain gzip archives via bounded local-memory
parsing. No raw logs, IPs, agents, queries, customer content or identifiers are
printed/saved. Helper emits only archive coverage and target-image status/date
counts, plus image integrity proof; aggregate output stays ignored.

- Available HTTP archive spansSep30 throughOct26 00:06:49+01:00.
- Available HTTPS archive spansSep30 throughOct5 14:04:40+02:00, before this
  photo was introduced. Neither archive contains requests for the target image.
- Static files are delivered by nginx; these archives do not prove access to
  current nginx image request/error logs. Absence of a record cannot rule out
  the reported intermittent error. Do not claim there were no404/403/5xx events.
- One standard Python-UA homepage GET returned403; the existing maintenance-UA
  GET and normal IAB navigation succeeded. Image HEAD/GET succeeded. This does
  not establish the cause of the user's image failure; no WAF weakening attempted.

## Next Evidence

Need the user's actual broken-image screenshot/session context (desktop/mobile,
browser and whether hard refresh changes it). Do not claim a root cause or fix,
blindly disable lazy loading, replace the photo, change delivery/security rules,
or add unbounded retries without evidence. A user hard refresh is a diagnostic,
not a proven fix. Quote/PDF/appointment code must stay untouched.
