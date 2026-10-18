# Homepage Construction Policy and Loading Audit

## Scope and State

- User confirms one latest construction compilation on the homepage, other clips in the construction journal; review mobile/loading without changing quotes or appointments.
- Production already meets that policy at main `10a86b2`, home controller v1.0.8 and construction module v1.0.0. No production source, content, media, configuration, cache, forms, customers, mail or advertising changed during this follow-up.
- Existing recovery remains the construction release's private backup and exact guarded recovery described in `2026-10-18-construction-updates.md`. No new deployment or stable production tag is claimed.

## Guest HTTP Evidence

Read-only audit: `2026-10-18-audit-home-loading.py`. Generated evidence remains ignored under `outputs/2026-10-18-home-load-audit/http-v1/`; no private page HTML or media committed. The fixed attempt directory intentionally refuses overwrite.

- Public homepage contains exactly one October 13 narrated/subtitled compilation. Journal contains that compilation plus four October 12 clips, retaining the older archive. The homepage link targets the same dated journal entry.
- No construction video/source/iframe is attached initially. Explicit playback creates the player; posters are lazy, asynchronously decoded and dimensioned 1280x720. Initial marketing/3D videos are separate and preserved.
- Homepage gzip HTML: 112,806 bytes; decoded HTML: 542,301 bytes. Three TTFB samples: 0.193779, 0.192011, 0.208187 seconds; median 0.193779. Median HTML transfer completion: 0.234331 seconds.
- Journal gzip HTML: 97,297 bytes; decoded HTML: 502,765 bytes. Three TTFB samples: 0.166388, 0.171794, 0.178571 seconds; median 0.171794. Median HTML transfer completion: 0.190883 seconds.
- Both routes advertise `public, max-age=300, stale-while-revalidate=86400`. These are this machine's guest HTTP timings, NOT complete mobile page load, Core Web Vitals, physical-phone, China or throttled-4G measurements.
- All 19 owned homepage image HEAD requests return 200/image MIME; five construction and two unique hero/showcase video HEAD requests return 200/video MP4, byte ranges and one-year caching. Many images are lazy or in inactive tabs; their sum is not the initial page payload.
- Nine bounded public health routes return 200 without fatal markers: home, apartment search, sample property, selector overview, first-phase selector, gallery, contact, journal and thank-you.

## Regression Evidence

- Parent independently ran 11 new Node policy tests. Sol's reported 22 counts repeated runs, not 22 distinct cases. Tests render the real PHP modules and verify one homepage/five journal entries, matching deeplink, deferred loading, HU/Chinese changes, error fallback, scoped responsive CSS and pinned assets.
- The policy helper also runs the existing 96-assertion PHP construction fixture. Native forms in this helper are synthetic sentinels, NOT complete live handlers; their bytes, node identity and edited field values remain unchanged.
- Existing presentation/construction Node suites: 31 pass. Existing homepage PHP suite: 76 pass.
- Server's existing pure validation: 35 offer checks and 17 anti-spam checks pass. No REST dispatch, positive submission, notification or mail delivery test was performed.
- jsdom/media/CSSOM stubs do not demonstrate actual layout, video decoding or physical touch performance.

## Mobile and Performance Limits

Chrome browser 3 is disconnected; browser inventory exposes only in-app and MCP-app surfaces. One reconnection request was sent. No alternate browser was silently selected, no fresh actual mobile/browser performance pass is claimed. Prior release's actual 320/390/1280/2400 checks remain historical evidence, not this follow-up's fresh measurements.

Current source/HEAD inspection identifies a possible later optimization: the same 39,504,516-byte 1920x1080 hero rendition serves mobile and desktop. It is range-streamed, not necessarily downloaded in full immediately. The hero already respects reduced motion, visibility and user pause, but has no Save-Data/effective-connection adaptation. Any mobile rendition must preserve the approved content/quality and pass actual playback/layout tests before release.

An obsolete performance-guard switch targets the former `yulu-garden-*` URLs rather than the active hero. Its old mobile URL returns 403; it was not substituted into the current page and is not evidence that the active hero is broken. Do not reuse it or remove legacy assets broadly: original fallback/header/forms must stay intact.

Next actual-browser gate, once Chrome reconnects: fresh 320/390 HU/Chinese menu and no-overflow checks, latest compilation playback/journal link, read-only navigation/resource timing, and native quote/appointment open/close without submission. Preserve existing quote/appointment handlers and media quality.
