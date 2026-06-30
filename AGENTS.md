# AGENTS.md - Harmat Lakopark 22 Codex Rules

This file is the first file Codex should read before working on the Harmat Lakopark 22 project.

Use ASCII in internal notes when possible. Public Hungarian website text must keep correct Hungarian accents.

## Project Identity

- Project/site: Harmat Lakopark 22
- Public brand: Harmat Lakopark
- Domain: https://harmat22.hu
- Address: 1105 Budapest, Harmat utca 22.
- Main sales email: ertekesites@harmat22.hu
- Main sales phone: +36300733375

## Required Working Rules

- Read `PROJECT_CONTEXT.md` and `TASK_HISTORY.md` before non-trivial work.
- Do not modify live website code, WordPress content, plugins, CSS, JS, or configuration unless the user explicitly asks for implementation.
- Before any live edit, create or confirm a rollback path.
- Never commit or upload passwords, SSH keys, cookies, raw backups, videos, temporary exports, or private customer data.
- Preserve unrelated user/live-site edits. Do not revert work unless explicitly requested.
- Keep frontend changes conservative. The current public site is considered stable unless the user asks for redesign.
- Apartment data should come from the unified sales/apartment source of truth.
- Do not invent public prices. If price is hidden, use the public-site equivalent of "Ar egyeztetes alapjan".
- Sales, agent, client, and WordPress backend login flows must remain separate.
- Public Hungarian text must not contain Chinese, English placeholders, mojibake, or template leftovers.

## Sensitive Areas

- Homepage
- Virtual apartment selector pages
- Apartment search `/lakaskereso/`
- Offer / appointment forms
- Sales CRM, agent portal, client portal
- Smart assistant widget

These areas must be tested carefully after changes.

## Verification Checklist

For public-facing changes, check:

- Homepage desktop and mobile
- `/lakaskereso/`
- One sample property page, for example `/property/a1-f-l1/`
- `/virtualis-lakasvalaszto/`
- `/virtualis-lakasvalaszto-elso-utem/`
- One building selector page on mobile
- Contact / offer form behavior
- Cookie banner and privacy links
- No horizontal scrolling or broken layout on mobile
- No obvious cache showing stale UI

## Deployment Notes

- Clear WordPress/cache/minification cache after live CSS/JS/plugin changes.
- Verify using a fresh browser/incognito view when cache may be involved.
- Keep GitHub snapshots for stable versions.
