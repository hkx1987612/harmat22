# PROJECT_CONTEXT.md - Harmat Lakopark 22

This file summarizes the long-term context for the Harmat Lakopark 22 website and related sales tools.

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
- Main phone: +36-30-641-03-58
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
