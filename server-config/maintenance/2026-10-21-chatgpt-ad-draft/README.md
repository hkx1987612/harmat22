# ChatGPT Ad Review V1 - Local Only

## Scope And State

- User explicitly asks to prepare ad copy and image previews while account access is unresolved. Publication still requires review of native platform preview and total budget. No live website image replacement, source/data/quote/booking changes, Ads upload/save/activate/publish, billing setup, customer audience, tracking code or new support email this turn.
- Earlier website-image replacement proposal was CANCELLED by the user. The separate image-comparison V1 is historical, not an approved or pending deployment. Do not later deploy its mappings based on this ad-only request.
- Previous account-creation attempt returned409 indicating creation succeeded but access not ready. Existing company account is listed without current-login access. User-authorized technical support email was sent and separately checked; no reply or access recovery in the preceding check. This local draft does not certify access recovery. Private email/account IDs and screenshots stay outside source control.

## Copy And Assets

- Public brand: Harmat Lakópark. Legal company remains separate; no private-person name in creative.
- Hungarian headline: **Új otthon Kőbányán** (18 characters).
- Hungarian description: **Új építésű lakások és alaprajzok. Kérjen tájékoztatást! A kép látványterv.** (74 characters).
- Chinese review gloss: 在科巴尼奥，寻找新家。新建住宅及户型图，欢迎咨询了解。配图为效果图。
- Candidate A/recommended: already adopted `wp-mu-plugins/harmat-about-family/harmat-courtyard.jpg`.
- Candidate B/alternative: already adopted `wp-mu-plugins/harmat-project-family/project-aerial.jpg`.
- Both source images1920x1080 copied byte-for-byte. No crop/redraw/new AI architecture/stock media. Existing logo copied byte-for-byte. Code-native square composition places full source inside1200x675 area with separate brand/location and rendering-disclosure bands; no photo retouching. Do not describe them as built-property photographs or exact apartment views.
- Final local JPEGs1200x1200 include `LÁTVÁNYTERV · NEM AZ ELKÉSZÜLT ÁLLAPOT FÉNYKÉPE`; ad copy independently discloses rendering. Square assets are candidate files, not uploaded or platform-approved.
- Landing: existing `/lakaskereso/`, optional static UTM parameters only in the future ad URL; no new pixels/scripts, no page changes. Clicks/website visits is the proposed objective, not configured/approved. No invented discounts, sales area, guaranteed delivery, investment yield or demographic/family targeting.

## Budget And Future Gate

- ONE proposed campaign:7 local calendar days, total media capHUF38,500, not two budgets for A/B. HUF5,500/day is an arithmetic average and official current minimum DAILY budget, NOT a guarantee of daily spend or a total-budget validation. Prefer the native campaign-total cap if supported; verify minimums in the actual account. Tax/card fees not yet verified; never add unapproved charges.
- Start/end dates deliberately unset. User confirmation, access, brand/account review and billing readiness precede launch. Select Hungary explicitly; Budapest/subnational availability remains unverified in account. Do not expand countries to overcome restrictions. Local calendar scheduling must account for Budapest DST change if the eventual interval crosses it.
- Native preview, total cap, precise start/end/time zone, invoice/taxes, matching creative/brand, destination, policy review and any creative-enhancement/translation/URL-optimization settings need explicit pre-publication review. No approval is inferred from this local handoff. Do not enable conversions without separately approved measurement work. No promised clicks/leads/ROI or placement in specific conversations.

## Outputs And QA

- Ignored local root: `outputs/2026-10-21-chatgpt-ad-preview/v1/`. Local review URL: `http://127.0.0.1:8791/`.
- `build.py` generates the review/2artboard HTML files, copies existing media and CSS, and writes ignored source hash manifest. It refuses overwriting an existing review. Regeneration also requires retained local logo from the October18 home preview. Screenshot export is a separate browser step; builder alone does NOT create final JPEGs.
- Final export uses documented browser `tab.screenshot({fullPage:true,clip:{x:0,y:0,width:1200,height:1200}})` on each inspected artboard. Native `getScreenshot()` initially produced1200x1165 captures; they were rejected, not final files. The final JPEG size/type/nonblank checks independently pass. A draft index from before the export correction is ignored outside the served root. Do not reuse rejectedPNG captures.
-12offline tests: review-only state, one budget/unset dates, character limits/no unconfirmed claims, source bytes/hashes, JPEG dimensions/nonblank pixels, no submission/tracking surfaces, local assets,2labelled mockups, exact landing URLs, noindex/CSP and full-frame/rendering labels. Actual browser1200desktop/390x844/320x720: both image sets load, no measured text or page overflow, mobile single column. Screenshots inspected; no physical-device or platform-delivery claim.
- Review page explicitly says it is a local design mockup, not the actual ChatGPT ad renderer. No fabricated ChatGPT response/endorsement. Source/private support UI and existing public site unchanged. No live quote/appointment regression is claimed for a local-only artifact with no integration.
- Dedicated loopback serverPID29192 in this session serves only this output root; no directory lists/HEAD/POST, allowlisted static extensions, noindex/no-store. PID may differ later. All assets/previews/screenshots/server logs ignored, no binaries/private data on GitHub.

## Official References Checked October21

- [Ad creation guidance](https://help.openai.com/en/articles/20001212-create-ads-for-chatgpt-ads).
- [Bulk schema checklist](https://help.openai.com/en/articles/20001218-bulk-upload-campaign-schema-checklist): maximum title50/copy100; squareJPG/PNG<=1200x1200.
- [Campaign budget/availability](https://help.openai.com/en/articles/20001210-create-campaigns-for-chatgpt-ads): country/account restrictions, budget types/minimumHUFdaily5500 and dates.
- [Creative enhancements](https://help.openai.com/en/articles/20001614-creative-enhancements-for-chatgpt-ads): verify the account's current controls before publication rather than promising immutable display.
- [Existing project page](https://harmat22.hu/harmat-lakopark/), already approved project image sources and source pins. This is not an independent licensing/title certification.
