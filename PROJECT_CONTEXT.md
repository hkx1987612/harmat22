# PROJECT_CONTEXT.md - Harmat Lakopark 22

This file summarizes the long-term context for the Harmat Lakopark 22 website and related sales tools.

## Current Stable State

- Stable date: 2026-08-13
- Stable tag: `stable-2026-08-13-search-ai-discovery-current`
- Search/AI discovery layer `wp-mu-plugins/zz-harmat-search-ai-discovery.php` version `1.0.0` is live. It replaces two competing property Schema emitters with one data-backed `Apartment`/`Offer` graph linked to one `ApartmentComplex` and Yoast's `Organization` entity.
- Project entity data now includes the complete `1105 Budapest, Harmat utca 22.` address and sales contact. The homepage YouTube `VideoObject` references the same Organization instead of declaring a second one.
- Twelve currently available apartments have a conservative Hungarian factual-summary pilot sourced dynamically from their real WordPress fields. No apartment record, price, status, area, Elementor content, offer flow, or CRM logic was changed.
- Floor-plan PDFs remain downloadable but return `X-Robots-Tag: noindex, follow`, consolidating search indexing on the canonical property HTML pages. The IndexNow key is live at `/dd9b45afff1be991410cbeff16a313bf.txt`, and public post/property changes are queued for IndexNow submission.
- The former active Code Snippets entity emitter `#114` is disabled after its data was migrated into the tracked MU plugin, keeping GitHub as the source of truth.
- Full rollback backup: `/home/harmath2/codex-backups/search-ai-discovery-20260813-183649`
- Post-deployment validation passed all 4 sitemaps, 145 public pages, all 124 property pages and offer forms, 570 same-origin assets, the 12 content-pilot pages, crawler user agents, JSON-LD parsing, the A3-4-L5 `47.83 m2` correction, PDF headers, IndexNow key, retired-video guards, and sensitive-path guards. There were no PHP fatals or change-related warnings; one concurrent Action Scheduler rescheduling warning occurred during the first audit, after which WP-Cron tested healthy and the warning did not repeat.
- Previous stable tag: `stable-2026-08-13-china-cache-comfort-current`
- Anonymous public `GET` and `HEAD` requests no longer start the unnecessary Easy Property Listings session, allowing WP Super Cache to serve reusable public HTML. `POST`, WP-CLI, wp-cron, REST, logged-in, sales, agent, client, customer, lawyer, and other private requests retain their prior session behavior.
- Homepage and apartment-search HTML use a conservative five-minute public cache policy with a one-day stale-while-revalidate window. Property saves and relevant property-meta changes purge the page cache once at request shutdown so prices, areas, and statuses do not remain stale.
- Cache implementation: `wp-mu-plugins/008-harmat-public-cache-comfort.php` version `1.0.0`, `wp-mu-plugins/harmat-homepage-nocache.php` version `2.0.0`, and apartment search version `1.2.2`.
- No public content, apartment data, offer behavior, portal behavior, image, video, layout, or DNS/CDN setting changed in this optimization.
- Warm origin requests for the homepage, apartment search, property detail, virtual selector, gallery, and contact page now reach first byte in approximately `0.04 s`, compared with approximately `1.2 s` before public page caching.
- A 10-location mainland-China Globalping sample improved from an average `7.175 s` total and multi-second TTFB to an average `2.519 s` total and `0.660 s` TTFB. Cross-border DNS/TLS variance remains possible without mainland-China infrastructure and ICP filing.
- Full rollback backup: `/home/harmath2/codex-backups/china-cache-comfort-20260813-170953`
- Supplemental rollback backup for the final WP-CLI/wp-cron scope correction: `/home/harmath2/codex-backups/china-cache-comfort-cli-scope-20260813-171918`
- Post-deployment warm audit passed all 4 sitemap documents, 145 unique public URLs, all 124 property pages and offer forms, and 570 same-origin resources with zero HTTP, asset, language, placeholder, mojibake, retired-video-reference, SEO-structure, or fatal-output failures.
- SEO verification found zero duplicate titles/descriptions and zero failures for titles, descriptions, exact canonicals, Hungarian language declarations, single H1 output, indexability, sitemap dates, or JSON-LD parsing. Both declared sitemaps return `200`, and retired origin videos are absent. Two descriptions are only 1-2 characters over 160, and 15 ordinary content/legal pages lack a social-share image; these are non-blocking future polish items.
- Sales pages remain private and non-cacheable, all 26 real offer leads remained intact, no test offer was submitted, and the server error-log size and timestamp remained unchanged through deployment and regression.
- The public property offer flow now validates email format and prevents past appointment dates before submission. The date field uses the current Budapest date as its minimum.
- Independent MU protection `wp-mu-plugins/zz-harmat-public-offer-integrity.php` version `1.0.0` validates same-site request evidence, nonce values when present, the five approved lead sources, appointment time slots, appointment dates, and selected properties before the sales callback runs.
- Selected apartment data is canonicalized against the published WordPress property. Browser-supplied building, floor, area, room, price, and URL values cannot override the CRM record; the sales manager reloads those values from the matched property.
- Unified public offer modal version: `1.0.7`.
- Full rollback backup: `/home/harmath2/codex-backups/public-offer-integrity-20260731-110047`
- No-write REST tests passed for missing request context, invalid nonce, foreign origin, invalid source, past date, invalid property, and manipulated `1 Ft / 999 m2` property values. The private offer-lead count remained exactly 24.
- Desktop and 390-pixel mobile browser regression passed for `A3-4-L5`: the modal retained `47,83 m2`, `63 214 200 Ft`, all five Hungarian source choices, correct email/date messages, and no horizontal overflow.
- Post-deployment audits covered all 4 sitemaps, 145 public URLs, all 124 property offer forms, and 572 same-origin resources with zero page, form, resource, language, mojibake, placeholder, or fatal-output failures.
- WordPress core checksums, every database table, all 40 MU PHP files, the sales-manager PHP file, cache purge, temporary-file checks, key-page smoke tests, and post-regression error logs passed.
- Sensitive-path probes such as `.env`, `.git/config`, database exports, configuration backups, Composer manifests, and named site-backup archives now return `403` with a zero-byte body instead of rendering the approximately 407 KB WordPress 404 page.
- The protection uses a conservative root `.htaccess` layer tracked at `server-config/sensitive-probe-guard.htaccess` plus `wp-mu-plugins/zz-harmat-sensitive-probe-guard.php` version `1.0.0` as an early WordPress fallback for paths intercepted by the hosting security layer before root rewrite rules.
- Normal missing URLs retain the site's existing public 404 experience; valid pages, resources, `.well-known` paths, and ordinary archive/media names are not affected.
- The authenticated sales CRM bandwidth card now identifies hosting-archive data explicitly and shows its approximate age when the latest archived access log is at least six hours old. Chinese and Hungarian labels remain separate; public pages are unchanged.
- CRM bandwidth widget version: `1.1.0`.
- The three blocked legacy origin videos were moved intact out of `public_html` to `/home/harmath2/codex-retired-media/retired-origin-video-20260731-102100`; their SHA-256 checksums match the source files and their original public URLs continue to return `410` with zero bytes.
- The valid on-demand `swsp_xmsp.mp4` video remains in place and available.
- Full rollback backup: `/home/harmath2/codex-backups/traffic-hardening-20260731-101120`
- Supplemental rollback backups: `/home/harmath2/codex-backups/traffic-hardening-rule-revision-20260731-101807` and `/home/harmath2/codex-backups/traffic-hardening-mu-fallback-20260731-101956`
- Post-deployment audit covered all 4 sitemaps, 145 public URLs, all 124 property pages, and 572 same-origin resources with zero page, resource, language, mojibake, placeholder, retired-video-reference, SEO-structure, or fatal-output findings.
- Desktop and 390-pixel mobile regression passed for the homepage, gallery, apartment search, `A1-1-L2`, the main and first-phase virtual selectors, the A1 building selector, a reserved-apartment detail page, and the contact page without broken images or horizontal overflow.
- The public offer modal still exposes 90 available apartments and exactly five lead sources, and reserved A1 apartments remain viewable. No test quotation or business record was submitted.
- WordPress core checksums, every database table, all 39 MU PHP files, the sales-manager PHP file, cache purge, temporary-file checks, relocated-video checksums, and post-regression error logs passed.
- The homepage keeps the same visible layout, text, links, images, six featured-apartment cards, YouTube behavior, and unified offer modal while avoiding the sales manager's unused generic card/filter runtime.
- The complete apartment dataset remains available to the homepage offer picker. Only the unused 35 KB generic sales script and 15 KB generic sales stylesheet are replaced by a 521-character visibility shell and a 339-character cleanup script.
- Homepage source size fell from approximately 604 KB to 553 KB, and compressed HTML transfer fell from approximately 118 KB to 109 KB without changing the frontend.
- The featured-apartment and header offer flows were clicked after deployment. `A1-1-L6` still fills the exact apartment, building, floor, price, area, room and message fields, and the picker still exposes 90 available apartments.
- MU implementation: `wp-mu-plugins/007-harmat-home-data-comfort.php` version `1.1.0`.
- Full live rollback backup: `/home/harmath2/codex-backups/home-runtime-payload-20260731-093042`
- Desktop and mobile browser regression passed for the homepage, apartment search, one property, main and first-phase virtual selectors, A1 building selector, gallery, and contact page with no horizontal overflow or public-language errors.
- Post-deployment audit covered all 145 sitemap URLs, all 124 property pages, and 571 same-origin assets with zero sitemap, page, property, asset, language, mojibake, public-Chinese, or fatal-output findings.
- WordPress core checksums, every database table, all 38 MU PHP files, the sales-manager PHP file, cache purge, temporary-file checks, and post-deployment error logs passed.
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
