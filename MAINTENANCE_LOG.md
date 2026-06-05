# Maintenance Log

## 2026-06-05

### Contact Page Scene Redesign

- Updated `wp-mu-plugins/harmat-performance-guard.php` to version `1.3.12`.
- Replaced the `/elerhetosegeink/` content output with a cleaner card-style sales-office scene using the existing live contact-showroom photos: project model, lounge area, office interior, and sales-point entrance.
- Kept the change scoped to the contact page; homepage, apartment search, property pages, and sales/agent/lawyer portals were not redesigned.
- Disabled the older contact-page showroom output hooks instead of deleting the old functions, so rollback remains simple.
- Added contact-page mobile safeguards for long Hungarian text, card widths, and the cookie banner box sizing so the 390px mobile viewport has no horizontal overflow.
- Cleared WordPress cache and transients after deployment.
- Verified `/elerhetosegeink/`, homepage, `/lakaskereso/`, and `/property/a1-f-l1/` return HTTP 200 with no fatal-error text.
- Verified the four used showroom images return HTTP 200 and a forced 390px mobile viewport reports `documentElement.scrollWidth === 390`.
- Live rollback backups: `/home/harmath2/codex-backups/contact-scene-redesign-20260605-162135`, `/home/harmath2/codex-backups/contact-scene-final-20260605-162304`, `/home/harmath2/codex-backups/contact-scene-spacing-20260605-162610`, `/home/harmath2/codex-backups/contact-scene-mobile-overflow-20260605-162918`.

### Emergency Homepage Rollback

- Rolled `wp-mu-plugins/harmat-performance-guard.php` back to the earlier stable backup `/home/harmath2/codex-backups/legal-cookie-privacy-20260605-20260605-103211/harmat-performance-guard.php` after the homepage became unusable on the user's device.
- Removed the temporary `harmat-home-gallery-lite.php` MU plugin that had been added during the performance attempt.
- Cleared WordPress cache and transients after rollback.
- Verified homepage, `/lakaskereso/`, `/property/a1-1-l2/`, and `/elerhetosegeink/` return HTTP 200 with no fatal-error text.
- Current live `harmat-performance-guard.php` version after rollback: `1.3.7`.
- Safety backup before rollback: `/home/harmath2/codex-backups/home-emergency-rollback-to-stable-20260605-155510`.

### Lightweight Room-Type Listing Pages

- Updated `wp-mu-plugins/harmat-performance-guard.php` to version `1.3.11`.
- Replaced the public `/studio-apartman/`, `/2-szobas/`, `/3-szobas/`, `/4-szobas/`, and `/5-szobas/` handling with a lightweight room-filter listing page using the same `hm-lakas-card` style as the apartment search cards.
- Kept `/lakaskereso/` as the full all-apartment search page, while the room-count entries now load only their matching room-count data instead of redirecting through the full search page.
- Updated the homepage room-type cards so Studio/2/3/4/5 link to these lightweight pages; the "all apartments" entry still links to `/lakaskereso/`.
- Verified all five room-type URLs return HTTP 200 directly, contain only matching `data-rooms` cards, include no old `harmat-room-entry-card` output, and do not include the heavy `window.harmatSalesFront` payload.
- Live rollback backup: `/home/harmath2/codex-backups/performance-guard-1.3.11-20260605-152257`.

### Public Site Performance Lightening

- Status: rolled back by `Emergency Homepage Rollback` above because the homepage became unusable on the user's device.
- Updated `wp-mu-plugins/harmat-performance-guard.php` to version `1.3.13` and `wp-plugins/harmat-sales-manager/harmat-sales-manager.php` to version `1.6.20`.
- Removed the sales-manager `window.harmatSalesFront` apartment data assignment from the homepage; it still loads on property detail pages where a single property needs form prefill behavior.
- Stopped homepage prefetching of the full `/lakaskereso/` and `/virtualis-lakasvalaszto/` pages, which could make weak browsers download heavy pages while the visitor was still on the homepage.
- Replaced homepage gallery preview image tags that referenced large `Harmat_22_*.jpg` originals with existing lighter 1024px visual assets, while keeping the gallery links intact.
- Dequeued homepage VR/map/noUiSlider-related assets that are not needed on the homepage; left Slider Revolution assets intact to avoid breaking the hero area.
- Limited public offer-form helper scripts to pages that actually need forms and debounced the form MutationObserver to reduce repeated DOM scans.
- Verified homepage no longer includes the sales-manager apartment assignment, no longer references the large `Harmat_22_*.jpg` files in image tags, no longer prefetches heavy listing/virtual pages, and has no fatal-error text. Remaining known weight: the homepage still includes the older unified offer apartment-picker data because the current homepage forms contain apartment selector fields.
- Live rollback backups: `/home/harmath2/codex-backups/global-performance-lighten-20260605-154356`, `/home/harmath2/codex-backups/performance-guard-1.3.13-20260605-154706`.

### Homepage Room Count Card Routing

- Updated `wp-mu-plugins/harmat-performance-guard.php` to version `1.3.10` and `wp-plugins/harmat-sales-manager/harmat-sales-manager.php` to version `1.6.19`.
- Replaced the old homepage room-type tile block at runtime with a unified `harmat-room-entry` card layout while keeping the same homepage position.
- Fixed the freeze after opening room-type cards by trimming `/lakaskereso/?rooms=N` output on the server: only matching room-count cards are sent to the browser instead of all 131 cards being loaded then hidden.
- Removed the full `window.harmatSalesFront` assignment from room-filtered search output and stopped loading the sales-manager front-card enhancement on `/lakaskereso/`, reducing the room-filter page payload.
- Verified `/lakaskereso/?rooms=2` returns HTTP 200 with no fatal error, only `data-rooms="2"` listing cards, and a smaller page payload than the full search page.
- Live rollback backups: `/home/harmath2/codex-backups/home-room-redesign-freeze-fix-20260605-145118`, `/home/harmath2/codex-backups/sales-manager-1.6.19-20260605-145259`, `/home/harmath2/codex-backups/performance-guard-1.3.10-20260605-145501`.

- Updated `wp-plugins/harmat-sales-manager/harmat-sales-manager.php` to version `1.6.17` and `wp-mu-plugins/harmat-performance-guard.php` to version `1.3.8`.
- Retargeted the homepage room image cards (`Stúdió Apartman`, `2 Szobás`, `3 Szobás`, `4 Szobás`, `5 Szobás`) to the unified `/lakaskereso/?rooms=N` search flow at runtime.
- Added a direct `/studio-apartman/` server redirect to `/lakaskereso/?rooms=1`, matching the existing room-count redirects.
- Kept the card layout unchanged and used redirects/link retargeting only, so the homepage design was not rebuilt.
- Deployed both files to the live server, cleared WordPress cache/transients, and verified homepage plus `/lakaskereso/?rooms=1` and `/lakaskereso/?rooms=2` return HTTP 200 with no fatal-error text.
- Live rollback backup: `/home/harmath2/codex-backups/home-room-cards-20260605-141426`.

- Updated `wp-plugins/harmat-sales-manager/harmat-sales-manager.php` to version `1.6.16`.
- Redirected legacy room-count result pages such as `/2-szobas/`, `/3-szobas/`, `/4-szobas/`, `/5-szobas/` and `?location=2-szobas` into the unified `/lakaskereso/?rooms=N` search flow.
- Added URL pre-filter support on the public search page so `rooms=2`, `rooms=3`, etc. apply the matching room-count filter automatically.
- Deployed the sales manager plugin to the live server, cleared WordPress cache/transients, and verified the legacy room-count URLs return 302 redirects to the unified search page.
- Live rollback backup: `/home/harmath2/codex-backups/sales-manager-1.6.16-20260605-134018`.

### Closed Customer Maintenance Fields

- Updated `wp-plugins/harmat-sales-manager/harmat-sales-manager.php` to version `1.6.13`.
- Expanded the closed-customer archive maintenance form so sales users can update customer name, phone, email, next follow-up date, next action, handover/aftercare notes, and internal aftercare notes from the customer profile.
- Kept CRM code, apartment, deal amount, payment plan, contract status, and commission fields outside this maintenance form.
- When a linked customer portal account exists, the customer display name and email are synchronized if the new email is valid and not used by another account.
- Deployed the sales manager plugin to the live server, cleared WordPress cache/transients, and verified `/sales/`, `/sales/?view=customers`, and the homepage return HTTP 200 with no fatal-error text.
- Live rollback backup: `/home/harmath2/codex-backups/sales-manager-1.6.13-20260605-124248`.

### Sales Commission Visibility Logic

- Updated `wp-plugins/harmat-sales-manager/harmat-sales-manager.php` to version `1.6.12`.
- Tightened commission visibility so commission fields, customer-profile commission blocks, and commission timeline cards only appear for broker-sourced deals.
- Website inquiries and walk-in customers now keep commission inputs hidden/disabled, and filtered tables omit the commission column when there are no broker-sourced deals in the current result set.
- Deployed the sales manager plugin to the live server, cleared WordPress cache/transients, and verified `/sales/`, `/sales/?view=customers`, and the homepage return HTTP 200 with no fatal-error text.
- Live rollback backup: `/home/harmath2/codex-backups/sales-manager-1.6.12-20260605-113433`.

### Sales Customer Archive Filtering

- Updated `wp-plugins/harmat-sales-manager/harmat-sales-manager.php` to version `1.6.11`.
- Added `/sales/?view=customers` filters for customer name, CRM code, phone, apartment number, payment method, responsible sales/broker, deal amount range, overdue status, and payment due window.
- Added filtered customer statistics and a payment due countdown column so closed customer archives can be managed after sales volume grows.
- Expanded the sales-staff permission notice so second-level sales users can maintain all closed customer archives while supervisor-only payment/contract/commission decisions remain protected.
- Deployed together with `1.6.12`; live rollback backup: `/home/harmath2/codex-backups/sales-manager-1.6.12-20260605-113433`.

### Sales Staff Permission Refinement

- Updated `wp-plugins/harmat-sales-manager/harmat-sales-manager.php` to version `1.6.9`.
- Added a clear sales-staff permission strip on `/sales/` so limited sales users can see what they may edit, what is read-only, and what requires supervisor confirmation.
- Adjusted the sales dashboard so non-manager sales users no longer see the global latest website-inquiry list; they see their own work scope instead.
- Hardened deal creation so direct `inquiry_id` URL parameters are ignored for non-manager sales users unless the existing deal was already website-sourced and assigned.

### Legal And Cookie Compliance Follow-up

- Updated and deployed `wp-mu-plugins/hm-legal-cookie-compliance.php` after reviewing lawyer privacy/cookie-policy comments.
- Changed the displayed legal/cookie version to `2026-06-05-v1.4` so returning visitors are asked for renewed cookie consent.
- Expanded the privacy notice with separate coverage for sales, agent, client, and lawyer portal data handling, stronger processor/recipient wording, EU/US transfer notes, legitimate-interest/security notes, and complaint/right details.
- Expanded the cookie policy with controller identification, legal bases by category, a concrete cookie table including `harmat_cookie_consent_v1` and `epl_wp_session`, consent withdrawal instructions, and CMP/consent-state notes.
- Updated `wp-mu-plugins/harmat-performance-guard.php` so public offer/contact form privacy wording uses the same newer legal basis wording and still keeps marketing consent optional.
- Verified the live cookie policy, privacy notice, and contact form source return HTTP 200 and include the expected new compliance text.
- Live rollback backup: `/home/harmath2/codex-backups/legal-cookie-privacy-20260605-20260605-103211`.

## 2026-06-01

### Privacy And Cookie Compliance

- Synced the live legal/cookie MU plugin into the repository as `wp-mu-plugins/hm-legal-cookie-compliance.php`.
- Updated the cookie policy table with clearer provider/category/purpose/retention details and moved the displayed last-updated date to 2026-06-01.
- Added a consent `policyVersion` value to the saved cookie consent state so old consent can be refreshed after policy changes.
- Removed the automatic front-end Google Site Kit gtag output and resource hint; the Google tag now loads only after the visitor accepts statistical cookies.
- Changed the consent scripts to `application/javascript` so Flying Scripts does not delay the banner itself.
- Verified live homepage first visit: cookie banner appears immediately, `Csak szükséges sütik` is visible, no Google gtag external script or Site Kit gtag handle is present before consent.
- Verified clicking only necessary cookies keeps Google gtag unloaded, and clicking accept all dynamically loads `https://www.googletagmanager.com/gtag/js?id=GT-K8DZ7HN6` with the consent-loaded marker.
- Live rollback backup: `/home/harmath2/codex-backups/privacy-cookie-compliance-20260601-231505`.

### Footer Company Name

- Corrected the live footer company display to `Cooperation Power Kft.` and marked the company name as non-translatable to avoid browser translation changing `Kft.` to `Ltd.`.
- Created a database rollback backup before the live edit: `/home/harmath2/codex-backups/footer-company-kft-20260601-205228/db-before-footer-company-kft.sql`.
- Verified the rendered footer DOM shows `© Cooperation Power Kft.` with `translate="no"` and no `Cooperation Power Ltd` remains in the live HTML/database search.

### Unified Offer Buttons

- Updated `Harmat Sales Manager` to version `1.5.0` so apartment offer CTAs share one frontend logic.
- Listing-page apartment offer buttons now open the local offer form instead of navigating to the property page, and the selected apartment fields are filled from the clicked apartment card.
- Loaded the same offer-prefill logic on virtual apartment selector paths for consistent future apartment CTAs.
- Verified live `/lakaskereso/` with an apartment card offer button: the page stayed in place, the form opened, and the selected apartment/building/floor were filled.
- Verified live `/property/a1-f-l3/` popup form contains the selected apartment data, and `/virtualis-lakasvalaszto-elso-utem/` loads the unified script with 124 cards and no browser errors.
- Live rollback backup: `/home/harmath2/codex-backups/lakaskereso-search-20260601-203616`.

### Lawyer Legal Documents Portal

- Added and deployed the standalone `Harmat Legal Documents` plugin under `wp-plugins/harmat-legal-documents/`.
- Created the protected `/lawyer/` portal and sales-side `/sales/?view=legal` view for lawyer file workflow.
- The legal homepage groups all 124 apartments, lets users choose one apartment first, and shows buyer, deal stage, sales status, amount, deposit, payment status, contract status, and file count when sales data exists.
- Legal uploads are saved outside public media URLs in `uploads/harmat-legal-private/`; downloads require login and legal-document permission.
- Added the `harmat_lawyer` role plus legal document view/upload/manage capabilities, and added a sales-side lawyer account creation panel.
- Polished the protected login form styling for the new lawyer/sales legal-document pages.
- Upgraded the legal portal to version `0.1.1` with per-apartment legal case status, a required-document checklist, missing-item reminders, deadline field, and internal legal notes.
- The apartment homepage now shows legal case status and missing-item count, and can filter apartments with missing legal items.
- Updated the legal portal to version `0.1.2`: visible portal text is Hungarian-only, the legal case number is shown as `CRM azonosító`, and sales deals without an explicit CRM field display a stable CRM-style code from deal date plus deal id.
- Verified live plugin activation, role creation, public login pages, blocked anonymous downloads, `/sales/` still returning 200, and backend legal-home rendering with 124 targets.
- Verified the workflow render path for 124 apartments, including the legal case panel, buyer identity checklist item, deposit proof checklist item, missing reminder, and save action.
- Verified the live `/lawyer/` and `/sales/?view=legal` login pages use Hungarian text, contain no old English legal-portal strings, and anonymous downloads still return 403.
- Live rollback backup: `/home/harmath2/codex-backups/legal-documents-20260601-191616`.

### Side Menu Responsive Fix

- Adjusted the Elementor popup side menu so the logo, link list, contact block, and opening hours compact by viewport height instead of overflowing on laptop screens.
- Hid the cookie settings and AI assistant floating buttons while the side menu popup is open, and constrained the popup to the viewport width to avoid horizontal overflow.
- Verified `/virtualis-lakasvalaszto/` with the side menu open: contact details and opening hours fit in the visible menu area, floating controls are hidden, and the popup has no horizontal overflow.
- Latest live rollback backup: `/home/harmath2/codex-backups/home-ai-mobile-cta-20260601-113901`.

### A1-F-L3 3D Model Demo

- Added and deployed the standalone A1-F-L3 3D apartment model page at `/3d-a1-f-l3/` through `wp-mu-plugins/harmat-vr-demo.php`.
- Added the `3D lakásmodell` entry beside the existing virtual demo entry on `/property/a1-f-l3/`.
- Kept the model clearly labelled as a sales visualization based on the public room list and floor plan, not a BIM or construction model.
- Verified the live 3D page returns 200, renders a nonblank WebGL canvas, has correct Hungarian labels, and links to the VR demo, PDF floor plan, apartment page, and sales email.
- Live rollback backup: `/home/harmath2/codex-backups/vr-demo-20260601-075316`.
- Upgraded the same 3D page to a warmer real-time interior visualization with procedural wood, tile, fabric, grass, glass, furniture staging, window frames, plants, and indoor lights.
- Verified the upgraded live model renders a nonblank WebGL canvas and preserved the A1-F-L3 property-page 3D and VR entries.
- Latest live rollback backup: `/home/harmath2/codex-backups/vr-demo-20260601-084550`.
- Added a `Belső nézet` low-camera interior view, URL-shareable view selection such as `/3d-a1-f-l3/?view=walk`, and extra interior detail including curtains, baseboards, wall art, backsplash, and bathroom mirror.
- Verified the low-camera view renders live without WebGL errors; latest rollback backup: `/home/harmath2/codex-backups/vr-demo-20260601-092950`.
- Added default first-person automatic apartment playback on `/3d-a1-f-l3/`, with a pause/start button, tour status overlay, and `autoplay=0` support for manual-only viewing.
- Verified default autoplay runs live, the WebGL canvas remains nonblank, and `/property/a1-f-l3/` keeps the 3D and VR entries; latest rollback backup: `/home/harmath2/codex-backups/vr-demo-20260601-100224`.
- Retired the experimental VR/3D demo after review: `/3d-a1-f-l3/` and `/vr-a1-f-l3/` now redirect to `/property/a1-f-l3/`, and the A1-F-L3 property page no longer injects 3D/VR demo buttons.
- Verified the retired demo URLs redirect and the property page no longer exposes the experimental entry; latest rollback backup: `/home/harmath2/codex-backups/vr-demo-20260601-111734`.

## 2026-05-31

### Harmat Local Assistant

- Added and deployed the `Harmat Local Assistant` WordPress plugin under `wp-plugins/harmat-local-assistant/`.
- Loaded the approved local apartment knowledge base with 124 first-phase apartments, prices, areas, room counts, status, and property links.
- Activated the plugin on the live WordPress site and cleared WordPress/Super Cache/Autoptimize caches.
- The new assistant takes over the old footer AI widget to avoid duplicate floating customer-service buttons.
- Verified the live homepage outputs the new assistant and the REST endpoint answers apartment price and budget-recommendation questions.
- Updated the local assistant to version 0.1.5 with a clearer sales flow: generic recommendation questions now ask for budget/room/use first, while purchase process, project advantages, discount objections, and specific apartment checks have separate controlled answers.
- Updated the local assistant to version 0.1.6 with buyer-profile scoring, safe apartment tags, and a more human-looking avatar launch button.
- Updated the local assistant to version 0.1.7 with the 2026-06-12 sales launch date and the ground-floor gift-garden sales note.
- Added the first A1-F-L3 virtual apartment demo as `/vr-a1-f-l3/` through a standalone MU plugin, with a property-page entry button on `/property/a1-f-l3/`.

## 2026-05-17

### Version 2.4

- Synced the current Harmat Sales Manager plugin into the repository under `wp-plugins/harmat-sales-manager/`.
- Documented the separate private portals for sales, brokers, and customers.
- Added customer-center management improvements for account delivery, customer materials, payment tracking, broker commissions, and customer-visible document handling.
- Preserved public frontend behavior while keeping sales/customer operations inside private portal pages.

## 2026-05-13

### Version 2.2

- Added a hidden homepage SEO H1 without changing the visible frontend layout.
- Redirected `/kapcsolat/` to the real contact page `/elerhetosegeink/`.
- Repaired placeholder legal links in public footer output.
- Cleaned remaining public legacy text from `Harmatliget` to `Harmat 22`.
- Replaced obsolete public phone/email references with current sales contact details.
- Verified the checked public pages do not expose old test contact strings.

## 2026-05-12

### Version 2.1

- Synced the live Harmat22 interactive map module back to GitHub.
- Removed the floating text markers from the homepage location overview image so the aerial image displays cleanly.
- Kept the short project overview video in slow playback and restored the longer showcase video to normal playback speed.
- Confirmed the live homepage has no `.hi-map-pin` labels in the generated location image and that video rates resolve to `0.25x` for `swsp_xmsp.mp4` and `1x` for `spjs.mp4`.
- Preserved live rollback backups on the server before changing the production map script.

## 2026-05-06

- Created a local Git history for harmat22.hu maintenance work.
- Connected the local workspace to the GitHub repository `hkx1987612/harmat22`.
- Added repository safety rules to avoid committing credentials, cookies, large media, generated backups, and temporary website snapshots.
- Legal/compliance pages and form consent fields were prepared on the live WordPress site before GitHub binding.

### Version 1.0

- Stabilized the live Harmat22 frontend after the first full optimization pass.
- Confirmed the apartment picker works across the homepage, property pages, search page, and normal content pages.
- Moved the property disclaimer to the confirmed position under the final payment step on property detail pages.
- Polished the main menu contact typography, homepage video fullscreen button behavior, contact form layout, and cookie UI positioning.
- Fixed visible Hungarian text encoding issues in custom frontend snippets.
- Ran a visible-text audit across 153 public pages and property pages with no remaining visible mojibake findings.

## Rollback Notes

- Live WordPress backups are handled separately through the site's backup workflow.
- GitHub should track clean maintenance notes, reusable scripts, and intentional frontend changes only.
- Git tag `v1.0` marks the confirmed live-site state after the 2026-05-06 optimization pass.
