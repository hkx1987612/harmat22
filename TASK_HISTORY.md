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
- Added public smart assistant widget direction and initial integration concept.
- Cleaned template leftovers such as fake contact names and wrong public branding in earlier passes.
- Reworked homepage sections repeatedly and reduced some heavy homepage resources.
- Added/optimized homepage gallery preview with a link to the full gallery.
- Worked on homepage virtual selector entry image linking to the first phase selector.
- Improved virtual selector mobile behavior in at least one stable pass.
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
- Contact page may be redesigned with project-site photos, but this was not completed in this context pass.
- Any future legal/privacy/cookie text should be reviewed carefully for Hungarian compliance.

## Do Not Assume

- Do not assume public prices are visible or final.
- Do not assume all old screenshots/assets are still approved.
- Do not assume agent/client/sales portals share login logic.
- Do not assume cached browser output reflects current live code.
