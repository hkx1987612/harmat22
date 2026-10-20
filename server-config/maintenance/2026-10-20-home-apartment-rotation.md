# Six-Card Automatic Rotation

User explicitly chooses automatic rotation with manual arrows. New removable
`zz-harmat-home-apartment-rotation.php`1.0.0 SHA
`a505545d3c548206f04db2360847e56e48b44102e707c846a100795fd66bc957`.
Only homepage presentation. Native controller1.0.18, ten pinned assets, hero
assistant1.0.2, footer, source records, quotes and appointments remain unchanged.

## Behavior And Data

- Existing unified summary and floorplan helper, bounded501-post query with full
  post/meta priming. Only published/passwordless/current properties with validated
  code/numeric data/HTTPS same-origin URLs. No HTTP calls or new endpoints. JSON
  allowlist code/url/image/rooms/area/price only; hidden/nonpositive prices null.
  No CRM/client/private metadata serialized. Duplicate/invalid structure fails
  safely to original six-card presentation. No new persistent cache/database writes.
- Reuse six original DOM cards; first group retains existing featured selection,
  later groups cover all eligible units. Initial live pool91/16groups. Last group
  uses last six eligible homes (overlaps preceding group) rather than empty slots.
  Details navigate normally to original property pages, not to a booking action.
- Twelve-second groups; manual arrows, counter and play/pause with existing Lucide
  icons/titles/44px hit areas. No forced animation. Automatic timer runs only when
  section visible/foreground, stops on mouse hover, touch/card keyboard focus,
  and native quote/cookie/menu/chat blockers. Explicit play supported. Reduced
  motion starts paused; live IAB preference is reduced, so explicit play tested.
- Mobile retains original first3/expand6/collapse behavior. Same slots means native
  toggle handlers continue working. Existing home language bridge runs first;
  scoped root-lang observer reapplies current group's fields afterward so old
  captured translations cannot revert to the original featured units. All area/
  price values derive from source and use locale number formatting.

## Release And Verification

Backup:
`/home/harmath2/codex-backups/home-apartment-rotation-cfab18c28e2c4db4910c23ce016177f0`.
Aggregate `0e9ce13419fdeab5e06736af7f4dcb45f5db66e55933b7bd5590a20882b051c9`.
RELEASED and independent VERIFIED. Private staged PHP lint/107 pure data checks,
atomic addon activation/cache purge. Public main byte-exact, native form/SEO,
all public pool rows exact against server unified source, seven scoped routes,
35offer+17spam pure checks and protected source/page/business snapshots pass.
No full-site crawl, dispatch, form submission or mail.

Local107PHP/20Node/6transport tests cover public allowlist/hiddenprices/URLs/
bounded query/currentonly; six slots/wrap/timer/foreground/hover/focus/touch/modal/
reducedmotion/language/mobile toggle; exact pins/default offline/digest gate.
Offline namespace fixture Throwable alias and shared transport configuration
mutation fixed before any deployment; release base bundle now frozen before
configuring reusable absent-only transport. No lowered source/business guards.

Actual IAB1280: three columns/two rows, loaded six images, manual next changes
all code/title/image/facts/links consistently. Explicit autoplay advances2->3;
pause and last16->first1 verified. HU/ZH after later groups retains same units.
390mobile3->6->3,320controls273px/no horizontal overflow.320ZH original quote
opens/closes, contacts empty/privacy required unchecked/original five source
values; Oct23 and12-15 selectable then cleared. No submit. Actual newgroup
A1-4-L4 link navigates correct H1/59.48m2/73,810,800Ft. No physical phone/TV,
CWV/performance guarantee or email delivery certification. HU/default viewport
restored, homepage deliverable. Screenshots ignored under
`outputs/2026-10-20-home-rotation/desktop-final.jpg` and `mobile-final.jpg`.

## Recovery

Run `2026-10-20-deploy-home-apartment-rotation.py --rollback BACKUP --expect AGGREGATE`
with exact values above. This removes only the exact owned addon; original six
cards are the fallback. Default offline; exact digest required before connecting.
Foreign source/ownership/dependency/business drift is refused. Never restore
customer data. Remove this later addon before earlier hero/roofgarden/home-six
recovery so frozen release descriptors remain meaningful. Verify independently
after recovery. No old social/hosting/shutdown tasks resumed.
