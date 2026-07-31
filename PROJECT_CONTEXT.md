# PROJECT_CONTEXT.md - Harmat Lakopark 22

This file summarizes the long-term context for the Harmat Lakopark 22 website and related sales tools.

## Current Stable State

- Stable date: 2026-07-31
- Stable tag: `stable-2026-07-31-reserved-virtual-view-current`
- Reserved apartments remain visible and viewable in the A1-A4 virtual selectors. The selector lists now include 90 available and 32 reserved apartments, while the 2 sold apartments remain disabled and absent from the apartment cards.
- Reserved apartment cards show `Foglalva` and link to the correct public property page. Reserved SVG hotspots support keyboard navigation and direct mouse navigation to the matching property instead of selecting an unrelated available apartment.
- The offer-picker inventory remains available-only; the virtual-selector repair reads the complete 360-degree apartment dataset without modifying quotation or sales availability.
- MU implementation: `wp-mu-plugins/zz-harmat-reserved-virtual-view.php` version `1.3.0`.
- Full live rollback backup: `/home/harmath2/codex-backups/reserved-virtual-view-20260731-081249`
- Desktop and mobile regression passed for the main selector, first-phase selector, A1-A4 building selectors, room/floor filters, reserved cards, reserved SVG hotspots, sold hotspot blocking, and reserved property details.
- Post-deployment audit covered all 145 sitemap URLs, all 124 property pages, and 571 same-origin assets with zero sitemap, page, property, asset, language, mojibake, public-Chinese, or fatal-output findings.
- All 124 public property pages now prefer their verified high-resolution `-cn-floorplan-display.jpg` assets, so floor plans fill the presentation width without stretching or changing the original floor-plan PDFs.
- The final image set preserves 100 already-clean displays and removes screenshot-edge text/line fragments from 24 affected displays while retaining apartment details, gardens, balconies, and entrance arrows.
- The exact 124-image production set is tracked at `assets/property-floorplans/`; the image-selection change is in `wp-mu-plugins/harmat-migrated-snippets.php` version `2026.07.31.2`.
- Full live rollback backup: `/home/harmath2/codex-backups/all-floorplan-display-20260731-071439`
- Post-deployment audit covered all 145 sitemap URLs, all 124 property pages, and 569 same-origin assets with zero page, asset, language, mojibake, public-Chinese, fatal-output, broken-resource, or floor-plan-selection findings.
- Desktop and mobile checks passed for portrait, landscape, garden, and large-format floor plans without horizontal overflow. WordPress core checksums, all database tables, PHP syntax, cache purge, temporary-file checks, and post-deployment error logs also passed.
- The public experience optimization is live with conservative, rollback-friendly changes covering homepage video startup, apartment-search responsiveness, 360-degree loading, gallery image delivery, floor-plan PDF delivery, and icon-link accessibility.
- Full rollback backup: `/home/harmath2/codex-backups/experience-optimization-20260729-085538`
- The homepage now renders and preloads a real high-resolution poster before the YouTube player starts; the poster is hidden only after confirmed playback, so restricted YouTube regions retain a clean project image.
- Homepage document prefetches are now triggered by visitor intent instead of running unconditionally on initial load.
- The apartment search keeps all 124 properties, invalidates and warms its cached markup after property changes, removes unused carousel/range-library assets, and provides stable mobile statistics and 44-pixel range controls.
- The first-phase 360 selector provides direct A1-A4 building links, keyboard-operable SVG hotspots, stable JSON cache versions, a poster-first frame strategy, and connection-aware background frame loading.
- The public gallery serves verified 640-pixel and 1280-pixel WebP derivatives with responsive `srcset`; the original full-resolution JPEG files remain available as automatic fallbacks.
- All 124 public property pages now link to verified `-cn-floorplan-web.pdf` copies. The compact copies total approximately 136 MB instead of 398 MB, while the original PDFs remain untouched and accessible for rollback.
- The distinct `swsp_xmsp.mp4` project-introduction video remains an on-demand local asset with `preload="none"` and a delayed source; the separate `Látványvideó` remains the click-to-load YouTube presentation.
- Current versions: homepage YouTube guard `1.4.0`, apartment search `1.2.1`, 360 selector `1.9.0`, reserved virtual view `1.3.0`, gallery comfort layout `1.1.0`, accessibility polish `1.0.0`, and floor-plan PDF optimizer `1.0.0`.
- Final post-deployment audit covered all 145 sitemap URLs and 569 same-origin resources: every page and resource returned successfully, all 124 property pages used the compact floor-plan link, and there were zero SEO metadata, language, mojibake, placeholder, legacy-video, fatal-output, or broken-resource findings.
- Desktop and mobile browser regression passed for the homepage, 12 highlighted apartments, gallery, 124-property search, offer-source labels, main and first-phase virtual selectors, A3 building selector, A3-4-L5, and contact page without horizontal overflow.
- WordPress core checksums, every database table, related PHP syntax checks, cache purge, and post-regression error-log checks all passed.
- The public offer modal now has five lead-source choices: `Kültéri hirdetés`, `Google keresés`, `ingatlan.com`, `Közösségi média`, and `Egyéb`.
- `Közösségi média` combines Facebook, Instagram, and TikTok; separate Facebook/TikTok choices were removed without rewriting historical inquiry records.
- Unified public offer modal version: `1.0.6`
- Live rollback backup: `/home/harmath2/codex-backups/offer-source-social-media-20260729-081621`
- After a full WP Super Cache purge, all 145 sitemap URLs returned `200`, all 145 exposed the new social-media choice, and zero pages exposed the former Facebook/TikTok radio options or fatal output.
- Homepage room-type cards now link directly to `/lakaskereso/?rooms=1-5`, the former `/3d-viewer/` entry links directly to `/virtualis-lakasvalaszto/`, and both `/magunkrol/` offer links point directly to `/lakaskereso/`.
- Property room-list measurements now use Hungarian decimal commas with two decimal places; the underlying apartment and room-area values were not changed.
- The idempotent structured content migration is tracked at `server-config/maintenance/2026-07-29-direct-internal-links.php`.
- Post-deployment audit covered all 145 sitemap URLs, 432 internal links, and 212 same-origin media resources with zero HTTP, redirect, SEO metadata, language, mojibake, legacy-link, legacy-video, room-format, or duplicate-metadata findings.
- Server verification passed for 186 custom PHP files, WordPress core checksums, all database tables, and empty root, `wp-admin`, and debug error logs.
- Live rollback backup: `/home/harmath2/codex-backups/direct-links-room-format-20260729-075719`
- Homepage video SEO now exposes exactly one YouTube `VideoObject`; the video sitemap uses a stable publication/last-modified date instead of changing on every request.
- Retired origin videos `yulu-garden-source-compressed-60m.mp4`, `yulu-garden-mobile-720p.mp4`, and `spjs.mp4` return HTTP `410` with noindex headers and no longer appear in public page source.
- The dynamically generated `Latvanyvideo` card is replaced with the YouTube player on both the homepage and `/harmat-lakopark-kornyeke/`; the valid on-demand `swsp_xmsp.mp4` project-introduction video remains available.
- Duplicate public H1 output is normalized on `/harmat-lakopark-kornyeke/`, `/magunkrol/`, and `/szolgaltatasaink/`.
- Post-deployment sitemap audit covered 145 indexed URLs: all returned `200`, with zero redirect, title, description, canonical, noindex, language, H1, legacy-video, or duplicate-metadata findings.
- Search Console read `/sitemap_index.xml` successfully on 2026-07-29, reports the homepage indexed with one indexed video, accepted a fresh homepage indexing request, and started validation of the historical `/wp-json/` 5xx record after the endpoint was confirmed `200`.
- Current YouTube/retired-video guard version: `1.4.0`.
- CRM payment methods now include the internal key `loan`, displayed as `贷款` in Chinese sales mode and `Bankhitel` in Hungarian sales mode.
- The loan label is shared by deal editing, validation, filters, exports, confirmation sheets, and customer records; no loan percentage or bank condition is assumed automatically.
- Harmat Sales Manager version: `1.6.120`
- The approved sales area for `A1-4-L5`, `A2-4-L5`, `A3-4-L5`, and `A4-4-L5` is `47.83 m2` across apartment-search cards and property detail pages.
- The four matching per-square-meter prices are recalculated from each current apartment price and the corrected sales area.
- MU implementation: `wp-mu-plugins/zz-harmat-four-unit-area-correction.php`
- Homepage presentation video: YouTube video `kmAg_ki-yYY`, muted autoplay, loop, inline playback, adaptive quality with `hd1080` requested.
- The homepage uses the standard YouTube IFrame API with muted autoplay; the obsolete Slider Revolution preloader is suppressed and the project poster remains the default fallback.
- The homepage now reveals the standard YouTube embed only after the IFrame API confirms active playback. IPs that receive a YouTube sign-in or bot-verification interstitial keep the high-resolution project poster instead of exposing the error screen.
- The 3D experience `Latvanyvideo` card uses the same standard YouTube host, loads only after a visitor clicks play, and keeps its poster visible until playback is confirmed.
- MU implementation: `wp-mu-plugins/zz-harmat-home-youtube-guard.php`
- CRM bandwidth widget: `wp-mu-plugins/zz-harmat-crm-bandwidth-widget.php`
- Static origin-video deny template: `server-config/heavy-origin-video-deny.htaccess`
- The deny template is deployed as `.htaccess` in `wp-content/uploads/2026/05/` and `wp-content/plugins/harmat22-map-redesign/assets/harmat-3d/`.
- Legacy large files `yulu-garden-source-compressed-60m.mp4`, `yulu-garden-mobile-720p.mp4`, and `spjs.mp4` are blocked from direct web access.
- Bandwidth monitoring runs hourly, uses cPanel UAPI when available and archived Apache logs as a fallback, and sends one monthly email per 50/70/85/95 percent threshold.
- The current-month bandwidth total is displayed beside visitor metrics in the authenticated sales CRM, with separate Chinese and Hungarian labels.
- Bandwidth usage, threshold-notice state, and archived-log cache reset automatically on the first WordPress request of each new month.
- Live backup before the first deployment: `/home/harmath2/codex-backups/youtube-bandwidth-guard-20260729-044832`
- Live backup before the monitor fallback update: `/home/harmath2/codex-backups/bandwidth-monitor-fallback-20260729-045424`
- Live backup before the homepage spinner fix: `/home/harmath2/codex-backups/home-youtube-spinner-fix-20260729-051308`
- Live backup before the forced-autoplay iframe update: `/home/harmath2/codex-backups/home-youtube-autoplay-fix-20260729-051505`
- Live backup before the CRM bandwidth widget: `/home/harmath2/codex-backups/crm-bandwidth-widget-20260729-051736`
- Live backup before the YouTube regional fallback: `/home/harmath2/codex-backups/youtube-region-fallback-20260729-053146`
- Live backup before extending the regional fallback to the 3D player: `/home/harmath2/codex-backups/youtube-region-fallback-complete-20260729-053634`
- Live backup before the four-unit area correction: `/home/harmath2/codex-backups/four-unit-area-correction-20260729-054708`
- Live backup before adding the CRM loan payment method: `/home/harmath2/codex-backups/crm-loan-payment-20260729-064821`
- Full live backup before the video/SEO cleanup: `/home/harmath2/codex-backups/seo-video-index-cleanup-20260729-070118`
- Supplemental backup before the final neighborhood runtime correction: `/home/harmath2/codex-backups/neighborhood-video-runtime-fix-20260729-071357`
- Post-deployment checks passed for the homepage video, gallery, apartment search, four corrected apartment cards and detail pages, virtual selectors, contact page, sales/agent/client logins, the bilingual CRM loan option, CRM payment totals, monthly reset, language separation, PHP logs, and horizontal overflow.

## Business Goal

Harmat Lakopark 22 is a new-build residential project website for lead generation and apartment sales. The site should help visitors understand the project, find suitable apartments, request offers, book appointments, and contact the sales team.

The website should feel like a real premium residential project site, not a template site.

## Core Facts

- Public project name: Harmat Lakopark
- Domain: https://harmat22.hu
- Address: 1105 Budapest, Harmat utca 22.
- Area: Budapest X. kerulet
- Developer / investor shown on site: Cooperation Power Kft.
- Main contact: ertekesites@harmat22.hu
- Main phone: +36300733375
- Sales launch / opening date: 2026-06-12
- Expected first-phase handover: 2028 Q2
- Current sales note: gardens attached to ground-floor apartments are included as a gift; exact garden size, use details, and contractual wording must be confirmed by sales for the selected apartment.

## Audience

- Hungarian home buyers
- Families looking for new-build homes
- Investors
- Buyers interested in 1-5 room apartments
- Customers using desktop and mobile devices
- Agents / brokers working with the sales team

## Public Website Positioning

The site should emphasize:

- new-build apartments in Budapest X. kerulet
- Harmat utca 22 location
- family-friendly residential environment
- green surroundings
- underground parking and storage options
- apartment search and virtual apartment selection
- clear offer request and appointment flow

## Important Public Pages

- Homepage: `/`
- Apartment search: `/lakaskereso/`
- Virtual selector: `/virtualis-lakasvalaszto/`
- First phase selector: `/virtualis-lakasvalaszto-elso-utem/`
- Building selectors such as `/virtualis-lakasvalaszto-a1-epulet/`
- Gallery: `/galeria/`
- Contact: `/elerhetosegeink/`
- About/project info: `/magunkrol/`
- Legal/privacy pages

## Operational Portals

- Sales portal: `/sales/`
- Agent portal: `/agent/`
- Client portal: `/client/`
- Lawyer legal-document portal: `/lawyer/`
- Sales-side legal-document view: `/sales/?view=legal`
- WordPress backend login must remain separate from all of these.

## Approved Project Numbers

Use these unless a newer approved sales table overrides them:

- Total planned apartments: 398
- First phase apartments: 124
- First phase underground parking spaces: 124
- First phase storage units: 92
- First phase area shown on homepage: 8388 m2
- Expected handover: 2028 Q2

## Apartment Data Rules

Apartment data should be unified across:

- public search
- property pages
- similar apartment cards
- offer forms
- sales CRM
- agent portal
- client portal
- smart assistant

Important fields:

- apartment number
- building
- floor
- room count
- base area
- sale area
- terrace / balcony / garden area
- status
- price visibility / price
- floor plan image/PDF

Public price policy: do not expose or generate prices unless the approved data source and display rule allow it.

## Forms And Leads

Forms should:

- validate required fields
- validate email format
- require privacy consent
- keep marketing consent optional unless the business changes this
- send sales notification to ertekesites@harmat22.hu
- send customer confirmation when email is provided
- show a clear success state after submission

## Sales / CRM Direction

The sales system should centralize:

- website inquiries
- walk-in customers
- agent-referred customers
- CRM codes
- follow-up status
- deals
- payment plans
- apartment status
- commissions
- client account generation
- client documents/attachments
- lawyer document workflow for reserved/contract apartments

CRM code rule: generated from date plus serial number, and stays with the customer.

## Lawyer Document Direction

The lawyer document portal should stay private and apartment-centered:

- lawyers first choose an apartment from the protected `/lawyer/` homepage
- reserved/contract/sold apartments should show sales status, buyer name, contact data, amount, deposit, payment status, and contract status when sales data exists
- sales managers can review the same legal file workspace from `/sales/?view=legal`
- each apartment can have a legal case status, required-document checklist, missing-item reminder, deadline, and internal legal note
- legal case number should be the CRM identifier from the sales system; if a sales deal has no explicit CRM field, display a stable CRM-style code from deal date plus deal id
- the lawyer portal frontend should be Hungarian only and must not show Chinese/English placeholders or mojibake
- uploaded lawyer files must not be public media URLs and must require logged-in legal-document permission to download
- do not expose buyer identity data, deal amounts, or legal files on public pages

## Agent Direction

Agents can manage their own customers and view relevant commissions. Agents should not mark deals as closed/sold; sales confirms sold status. Commission records should show amount, payment date, and settlement status.

## Client Portal Direction

Client accounts are created by sales. The client portal should show purchased apartment information, payment/contract status where available, project updates, progress photos, and uploaded attachments.

## Smart Assistant Direction

The assistant should be a controlled sales assistant:

- answer project FAQ
- recommend apartments from real data
- explain location/project advantages
- help with offer request and appointment flow
- never invent prices, legal advice, loan approval, or contractual promises
- hand off uncertain questions to sales

## Design Direction

- elegant, calm, real-estate oriented
- strong real/project images
- clear CTAs
- restrained colors
- mobile-friendly layout
- no placeholder names
- no mixed-language leftovers
- no unnecessary heavy resources on first load
