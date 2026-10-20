# Homepage Contact Image Replacement

User identifies the homepage contact-section fence photo and requests a more
attractive replacement. Scope is one existing image figure, alt and caption.
No layout/CSS/JS, contact details, quote/appointment, customer data or media
playback change. The contact page's own original photo remains untouched.

## Implementation

- Existing homepage controller 1.0.15 -> 1.0.16. Exact old SHA
  `7d823dc258bd7e64ede2586455ce222000632d2f47aa5523df779542d0bf3dab`;
  new SHA `d73fccd048b51e83f1d4b1ce301635751c25bc1ef8bbe7d521f14818b75e3f02`.
- Exact pinned body-six.php stays immutable; controller replaces one full owned
  figure only, requiring exactly one source match before continuing. All other
  bytes/logic retained except version. Original ten-pin manifest, scripts,
  fullscreen, inline assistant, six cards and footer remain exact protected bytes.
- Reuses existing project image
  `/wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/xgt_5.jpg`, inspected
  locally and exact public bytes checked before activation: 3840x2160 JPEG,
  1,041,262 bytes, SHA
  `1079bc7f5ab4995a2fc1e5307a84a4ca19d3fa82d75c0b20cf28d323ecf3cf99`.
  No image upload, generation, new provider or script. Existing lazy/async loading
  and responsive 4:3 desktop / 3:2 mobile frames retained.
- Existing HU/ZH keys panoramaGallery5/rendering give accurate garden/public-space
  alt and Látványterv / rendered-image caption, not a completed-site photo claim.

## Verification And Release

- Eight offline exact-delta/refusal/synthetic-render tests pass; the existing
  controller's 76 synthetic checks pass locally and in private server staging.
  Native 35 offer and 17 anti-spam pure checks pass without dispatch/mail.
- Initial preflight stopped before any live activation because the footer was
  compared as normalized LF rather than its retained production mixed EOL.
  Adapter now reconstructs and requires exact known raw footer SHA
  f28f8dfdf80ea2edea09a0f7d9ec4854f16305d9dd1033bc507a269882b51077,
  preserving its original four LF lines. No loosened footer comparison or edit.
- Fresh private backup
  `/home/harmath2/codex-backups/home-contact-image-5b0030278bde4a1bb87833a5a50b828f`.
  Aggregate `65ada2e95b760388362c73a4338d06b8d5bcf533413416201311d06a99abe300`.
  RELEASED and separately VERIFIED: atomic PHP-linted source replacement/cache,
  exact rendered main differs only by the figure, native form/SEO unchanged,
  homepage/search/contact healthy, protected source/page/business snapshots stable
  around each check. Only three scoped health routes, not another full-site scan.
- Actual IAB 1280px: image loaded 3840x2160, frame476.44x357.33, no page overflow.
  390px frame335x223.33, loaded, contacts/destination unchanged. HU->ZH caption
  and alt correct; 320px Chinese image/caption visible with no horizontal overflow.
  Native mobile Chinese quote/appointment modal opens/closes, contact fields stay
  empty, five HU source values unchanged, required privacy unchecked. No submit,
  no new lead, no mail/delivery or physical-device certification.
- HU and default viewport restored. Public homepage tab kept for user; desktop
  and mobile screenshots remain ignored under outputs/2026-10-20-contact-image/.

## Source-Only Recovery

```powershell
python server-config/maintenance/2026-10-20-update-home-contact-image.py --rollback /home/harmath2/codex-backups/home-contact-image-5b0030278bde4a1bb87833a5a50b828f --expect 65ada2e95b760388362c73a4338d06b8d5bcf533413416201311d06a99abe300
```

Restores only exact owned 1.0.16 controller to saved 1.0.15, never business data.
Roll this change back first before older footer/home transports whose frozen
baseline requires 1.0.15. Legitimate later CRM changes can make historical full
snapshot checks differ; never restore customer data just to match old hashes.
Stable tag after sync: stable-2026-10-20-home-contact-image-current.
