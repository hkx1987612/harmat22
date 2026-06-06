# TASK_HISTORY.md - Harmat Lakopark 22

This file summarizes completed work, fixed issues, and known open items. It is not a full chat log.

## Stable Baseline

- Current repository stable snapshot around `v2.5` was pushed as a public-site stable version.
- Main public-site customization snapshot is stored in `wp-mu-plugins/harmat-performance-guard.php`.
- Sales manager plugin snapshot is stored in `wp-plugins/harmat-sales-manager/harmat-sales-manager.php`.
- Homepage has been treated as stable after multiple layout, speed, and visual revisions.

## Completed Work

- Corrected project domain context from older wrong assumptions to `harmat22.hu`.
- Restored/updated floor plan PDF/image links for apartment pages.
- Reworked apartment offer and appointment forms so selected apartment data can be included in inquiries.
- Added required validation logic for most form flows: name, email, phone/date/time where applicable, and privacy consent.
- Improved email notification and customer confirmation behavior for inquiries.
- Created a cleaner success/confirmation flow after form submission.
- Built and refined sales, agent, and client portal concepts and early functionality.
- Added CRM/customer management direction, deal tracking, commission logic, and client account generation concepts.
- Added client attachment upload/delete direction for sales-side document handling.
- Added protected lawyer document portal direction with apartment-first workflow linked to sales deal status, buyer data, and amounts.
- Added the first lawyer workflow layer: per-apartment legal case status, required-document checklist, missing-item reminders, deadline, and internal note.
- Updated the lawyer portal direction so the visible frontend is Hungarian only, the legal case number is the sales CRM identifier, and mojibake/Chinese/English placeholders are not acceptable.
- Added public smart assistant widget direction and initial integration concept.
- Cleaned template leftovers such as fake contact names and wrong public branding in earlier passes.
- Reworked homepage sections repeatedly and reduced some heavy homepage resources.
- Added/optimized homepage gallery preview with a link to the full gallery.
- Worked on homepage virtual selector entry image linking to the first phase selector.
- Improved virtual selector mobile behavior in at least one stable pass.
- Unified public offer button behavior so apartment-specific offer CTAs open the same form and pass the selected apartment code.
- Updated the live customer confirmation email used by the offer/appointment flow: the actual sender is Code Snippets snippet 76, and the customer email now uses a branded HTML layout instead of the old plain-text confirmation.
- Fixed the same customer email flow so apartment details are refreshed from the real property record before sending; this prevents stale hidden form values from causing garbled area text or hidden-price text after prices are public.
- Corrected the branded customer email logo URL from a broken hyphenated filename to the real public Harmat logo asset, so Gmail no longer shows a broken image in the email header.
- Polished the customer confirmation email for mobile Gmail: compact logo/header, one-column apartment detail rows, earlier apartment-detail CTA, one-business-day follow-up wording, and a short price/availability disclaimer.
- Cleaned backed-up website inquiry test records related to Codex test submissions; real recent inquiries were left intact.
- Redesigned the public contact page `/elerhetosegeink/` into a card-style sales-office scene using existing project/showroom photos, with address, phone, email, opening hours, map and apartment-search actions, plus a forced 390px mobile overflow check.
- Added automatic sales-deal payment calculation on `/sales/?view=deals`: full payment, 50/50 payment, and installment/deposit plans now fill payment-plan rows, paid amounts, statuses, and balance summary.
- Improved `/sales/?view=deals` deal editing: the editor now uses a wider single-column workspace, payment-plan rows include an explicit percentage field, and percentage/amount changes update live against the deal amount.
- Updated sales-deal payment templates: installment now defaults to 10/15/25/25/20/5, 50/50 payment defaults to 25/25/50 with 2026-12-31 and 2027-06-01 due dates, and full payment defaults to 25/75 with 2026-12-31 due date. Saved/manual payment percentages are no longer overwritten.
- Added a sales-deal payment summary strip on `/sales/?view=deals`: it shows deal amount, payment-plan total, percentage total, paid/unpaid amounts, and a clear warning when the payment plan does not match the deal amount.
- Improved the `/sales/?view=deals` daily list view with compact deal cards for customer, CRM, apartment, stage, payment, contract, next action, and edit actions; the old wide detailed table remains available as an expandable backup view.
- Improved `/sales/?view=deals` follow-up visibility: deal cards are now sorted by follow-up urgency, and the daily list shows overdue, today, next-7-days, and no-follow-up counts above the cards.
- Cleaned the Hungarian `/agent/` portal output so fixed broker-interface titles and labels are localized instead of showing Chinese UI text.
- Strengthened privacy/cookie compliance: refreshed the cookie policy table, versioned the saved consent state, and delayed Google tag loading until statistical cookie consent is accepted.
- Strengthened privacy/cookie compliance again after lawyer notes: added controller details, processing activity coverage for sales/client/agent/lawyer portals, processor and third-country-transfer wording, consent-withdrawal/CMP notes, an expanded cookie table including `epl_wp_session`, and updated public form privacy consent wording.
- Lightened the first-phase virtual selector overview page (`/virtualis-lakasvalaszto-elso-utem/`): it no longer loads the sales frontend apartment-card payload, and shortcode pages with `toggle="off"` no longer output the empty apartment list container. Building-level virtual selector pages keep their apartment lists.
- Upgraded the first low-risk plugin batch on the live site after creating a database/plugin-list backup: Classic Editor 1.7.0, Flamingo 2.6.2, Yoast SEO 27.7, Google Site Kit 1.180.0, and WP Super Cache 3.1.1. Public smoke checks for homepage, apartment search, one property page, contact page, and sales login returned HTTP 200 with no fatal-error text.
- Upgraded the second plugin batch on the live site after a focused backup: All-in-One WP Security 5.4.8, WebP Converter for Media 6.6.1, and Image Optimization 1.7.5. Public smoke checks still returned HTTP 200 with no fatal-error text.
- Upgraded the mail/form plugin batch on the live site after a focused backup: Contact Form 7 6.1.6 and SMTP2GO 1.17.0. Public smoke checks returned HTTP 200, Contact Form 7 assets were present on public form pages, `wp_mail` returned success, and the test email was found in the `ertekesites@harmat22.hu` mailbox.
- Corrected the homepage structured-data logo path in `harmat-performance-guard.php` from the missing hyphenated filename to the real `Harmat_Logo_250.png` asset.
- Replaced the Studio/2/3/4/5 room-type result entries with lightweight filtered listing pages that use the same `hm-lakas-card` style as `/lakaskereso/`, load only the matching room-count apartments, and avoid the full apartment-search payload that caused freezes on weaker browsers.
- Rolled back the attempted homepage performance-lightening changes after the homepage became unusable on the user's device; the live homepage performance guard is back to the earlier stable `1.3.7` file, and the temporary gallery-lite MU plugin was removed.
- Created internal project notes: `AGENTS.md` and `PROJECT_CONTEXT.md`.

## Fixed / Previously Observed Issues

- Wrong site/domain confusion: `nagokft.hu` vs `harmat22.hu`.
- Broken links and 404s around apartment PDF/floor plan links.
- Form close button and submission feedback issues.
- Form emails initially not reliably received by sales/customer.
- Mixed old/new form logic causing inconsistent selected apartment information.
- Public pages showing placeholder names like `Gipsz Jakab`.
- Public pages showing inconsistent or zero project statistics in older versions.
- Footer/link clutter and default plugin page exposure in earlier versions.
- Gallery mobile layout problems in earlier versions.
- Virtual selector hover/click/list behavior changed several times; avoid broad edits without testing.
- Several homepage performance issues related to heavy video/map/old resources.
- Mojibake/encoding risk in internal files; current internal docs prefer ASCII.

## Current Known Risk Areas

- Homepage virtual selector preview image/overlay can easily become visually misaligned.
- Virtual selector pages can break mobile layout or create horizontal overflow.
- Apartment search/card styling may still need careful future refinement, but should not be rebuilt casually.
- Smart assistant needs stronger knowledge base and reliable apartment recommendation logic.
- Sales/agent/client portals are still evolving and should be changed with data safety in mind.
- Lawyer document portal contains buyer and deal data, so keep it private and test permissions before changing upload/download logic.
- Old temporary scan/cache/export files exist in the repository root; clean only with care and never delete source assets blindly.
- Public Hungarian text should be checked for accents, mixed Chinese/English, and mojibake after edits.

## Pending / Future Work

- Continue refining smart assistant knowledge base:
  - project overview
  - apartment search by area/room count/status
  - location advantages
  - nearby facilities
  - payment rules
  - FAQ
  - handoff to sales
- Improve assistant response UX so answers start at the first line and do not jump to the bottom.
- Consider deeper integration between assistant and real apartment data for card-style recommendations.
- Continue cleaning unused large temporary files only after confirming they are not needed by the current live site.
- If homepage virtual selector is changed again, test desktop, mobile, speed, and layout stability before making it live.
- Contact page scene redesign is now live; future changes should only refine text/photo selection unless a broader redesign is requested.
- Any future legal/privacy/cookie text should be reviewed carefully for Hungarian compliance.
- Adjusted the mobile virtual selector hitbox scaling for `/virtualis-lakasvalaszto-elso-utem/` so the clickable SVG layer matches the cropped building image more closely on phones.
- Added a two-level internal sales permission model in `harmat-sales-manager`: supervisors keep full sales permissions, sales staff can access `/sales/`, maintain their own customers/deals/tasks, and view property inventory read-only while exports, deletes, payments, contracts, commissions, customer archives, and inventory edits remain supervisor-only.
- Added shared property-inventory filtering for the sales and agent portals: status tabs for all/available/reserved/sold plus apartment search, building, floor, room count, minimum area, minimum price, and maximum price filters.
- Added `/sales/` workspace bilingual display support: sales users can switch between Hungarian and Chinese, with the main dashboard, navigation, filters, deal/payment/customer/property/account labels, and common statuses localized in Hungarian mode.
- Adjusted the `/sales/` language switch labels from short abbreviations to clearer `Magyar` and `中文` buttons.
- Normalized portal language switch labels so login/logged-in screens use clear names instead of `HU`/`ZH` abbreviations.
- Tightened `/sales/` Hungarian localization by covering remaining deal editor placeholders, payment-plan validation messages, customer-account notices, material upload labels, property filter hints, and account-management confirmation dialogs.

- Hardened sales deal logic in `harmat-sales-manager` v1.6.6: customer material links now use protected authenticated download URLs, new customer material uploads go to a dedicated restricted folder, customer portal hides internal-only materials, deal deletion cleans deal-owned customer accounts/material attachments, synced property inventory state can roll back after deal stage/property changes or deletion, sales tasks now include per-payment-plan due reminders, stale manual payment statuses are corrected against real received amounts, and sales staff can no longer create new manual `website`-source deals without an inquiry record.
- Ran a live end-to-end sales workflow test with temporary manager/sales/customer accounts and a temporary draft property. Verified manager deal creation, sales-staff permission limits, payment-plan creation, reserved/sold/current property sync, customer material upload, customer-only material visibility, protected customer download, payment-reminder task generation, deal deletion cleanup, and final removal of all temporary accounts/posts/attachments/deals. During the test, fixed the protected customer download response status by upgrading `harmat-sales-manager` to v1.6.7 and explicitly returning HTTP 200 for successful file downloads.
- Scanned registered sales customer-material attachments before migration; no active customer materials were registered, so there were no old uploads to move into the protected customer-material folder. Also scanned live Hungarian sales views and the lawyer portal with temporary accounts; no visible Chinese/mojibake remained except the intentional `中文` language-switch label.
- Upgraded `harmat-sales-manager` to v1.6.8 and cleaned the sales payment-plan output: automatic payment statuses, generated payment plan labels, fallback deposit/balance rows, schedule text, and the live payment calculator summary now use Hungarian-only wording. Existing saved deal records were not rewritten.
- Updated `harmat-sales-manager` to v1.6.9 for the second-level sales model: sales-staff users now see a clear limited-permission banner, the dashboard no longer exposes the global latest website-inquiry list to sales staff, and direct `inquiry_id` URL parameters are ignored for non-manager users unless they are preserving an already assigned website-sourced deal.
- Deployed `harmat-sales-manager` v1.6.10-v1.6.12 for closed-customer archive maintenance and commission visibility: sales staff can access all closed customer profiles for aftercare/material upkeep, `/sales/?view=customers` supports customer/CRM/apartment/payment/responsible/amount/due-date filtering, filtered totals, and payment due countdowns, and commission UI only appears for broker-sourced deals. Website inquiries and walk-in customers hide/disable commission fields and omit customer-profile commission blocks; commission columns are hidden when the current filtered result has no broker-sourced deals. Live rollback backup: `/home/harmath2/codex-backups/sales-manager-1.6.12-20260605-113433`.
- Deployed `harmat-sales-manager` v1.6.13 so closed-customer profiles can maintain customer name, phone, email, next follow-up, next action, handover/aftercare notes, and internal aftercare notes without exposing CRM, apartment, price, payment, contract, or commission edits in that maintenance form. If a linked customer portal account exists, valid email/display-name changes are synchronized to the account unless the email belongs to another user. Live rollback backup: `/home/harmath2/codex-backups/sales-manager-1.6.13-20260605-124248`.
- Deployed `harmat-sales-manager` v1.6.16 for the homepage room-count entry issue: legacy room-count pages such as `/2-szobas/`, `/3-szobas/`, `/4-szobas/`, `/5-szobas/` and `?location=2-szobas` now redirect to the unified `/lakaskereso/?rooms=N` search flow, and the public search page reads the `rooms` parameter to pre-filter the new card list. Live rollback backup: `/home/harmath2/codex-backups/sales-manager-1.6.16-20260605-134018`.
- Deployed a follow-up for the homepage room image cards shown in the user screenshot: `harmat-sales-manager` v1.6.17 redirects `/studio-apartman/` to `/lakaskereso/?rooms=1`, and `harmat-performance-guard` v1.3.8 retargets the homepage `Stúdió Apartman`, `2 Szobás`, `3 Szobás`, `4 Szobás`, `5 Szobás`, and old `?page_id=4730` card links to the unified apartment search flow without rebuilding the visual card layout. Live rollback backup: `/home/harmath2/codex-backups/home-room-cards-20260605-141426`.
- Deployed the homepage room-card redesign and freeze fix: `harmat-performance-guard` v1.3.10 now replaces the old homepage room-type image tiles with unified `harmat-room-entry` cards, trims `/lakaskereso/?rooms=N` output server-side to only the requested room count, and removes the full unified sales-data assignment from room-filtered search output. `harmat-sales-manager` v1.6.19 no longer loads its front-card enhancement on `/lakaskereso/`. Verified `rooms=2` now serves only 2-room cards and a smaller payload than the full search page. Live rollback backups: `/home/harmath2/codex-backups/home-room-redesign-freeze-fix-20260605-145118`, `/home/harmath2/codex-backups/sales-manager-1.6.19-20260605-145259`, `/home/harmath2/codex-backups/performance-guard-1.3.10-20260605-145501`.
- Deployed `harmat-sales-manager` v1.6.20 for the `/sales/` dashboard traffic overview: public pages now record lightweight anonymous visit stats only after analytics-cookie consent, and the sales dashboard shows today visitors, today pageviews, 7-day pageviews, today inquiries, and today's top public pages. Private portal paths such as `/sales/` are excluded. Live rollback backup: `/home/harmath2/codex-backups/sales-manager-traffic-20260606-171521`.
- Deployed `harmat-sales-manager` v1.6.21 so sales traffic numbers are also visible as a compact strip at the top of non-dashboard sales pages such as deals, customers, payments, and properties. The full traffic overview remains on `/sales/` / dashboard. Live rollback backup: `/home/harmath2/codex-backups/sales-manager-traffic-strip-20260606-172351`.
- Deployed `harmat-sales-manager` v1.6.22 to add a 7-day traffic detail table to the `/sales/` dashboard, showing daily visitors, pageviews, and inquiry counts. The compact top strip on non-dashboard sales pages remains unchanged. Live rollback backup: `/home/harmath2/codex-backups/sales-manager-traffic-7day-20260606-175110`.
- Deployed `harmat-local-assistant` v0.2.1 to improve customer-question triage: the assistant now classifies intent before answering, handles discount/legal/loan/payment questions with safer boundaries, recognizes unknown apartment-code-like inputs, supports tighter area filters such as "80 m2 felett" / "80平以上", and returns near matches when exact apartment-search criteria are too narrow. Live rollback backup: `/home/harmath2/codex-backups/local-assistant-intent-20260606-180407`.

## Do Not Assume

- Do not assume public prices are visible or final.
- Do not assume all old screenshots/assets are still approved.
- Do not assume agent/client/sales portals share login logic.
- Do not expose lawyer documents, buyer identities, or deal amounts on public pages.
- Do not assume cached browser output reflects current live code.
