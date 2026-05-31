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
- Expected first-phase handover: 2028 Q2

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

CRM code rule: generated from date plus serial number, and stays with the customer.

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
