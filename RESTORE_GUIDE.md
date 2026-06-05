# Harmat Lakopark 22 Restore Guide

This repository is a source-code stable snapshot for the Harmat Lakopark 22 WordPress project.

It is intended to help restore the custom code layer quickly after a server issue, WordPress plugin issue, or accidental code change.

## What This Repository Contains

- Project working rules and context: `AGENTS.md`, `PROJECT_CONTEXT.md`, `TASK_HISTORY.md`
- Maintenance notes and rollback references: `MAINTENANCE_LOG.md`
- Custom must-use plugins under `wp-mu-plugins/`
- Custom WordPress plugins under `wp-plugins/`
- The map module source and already tracked media used by the custom map module

## What This Repository Does Not Contain

For safety and size reasons, GitHub must not be treated as a full WordPress backup. The following must be restored from hosting backups or a separate backup tool:

- WordPress database
- `/wp-content/uploads/`
- private customer, lawyer, or sales uploaded files
- server configuration, cPanel mailbox data, SSL, cron, and DNS settings
- passwords, SSH keys, cookies, and login sessions
- third-party commercial plugin packages

## Current Custom Code Areas

### Must-Use Plugins

- `harmat-performance-guard.php`: public frontend fixes, contact page scene, homepage guards, SEO/schema, assistant shell, redirects, and targeted page behavior.
- `hm-legal-cookie-compliance.php`: legal/privacy/cookie pages and consent banner behavior.
- `harmat-homepage-nocache.php`: temporary homepage cache guard used after the emergency rollback.
- `harmat-vr-demo.php`: retired VR/3D demo redirects and cleanup protection.
- `harmat-google-site-verification.php`: Google verification output.
- `harmat-seo-index-cleanup.php`: SEO indexing cleanup rules.
- `harmat-seo-redirects.php`: SEO redirect rules.
- `harmat-video-schema.php`: video schema output.

### Custom Plugins

- `harmat-sales-manager`: sales, customer, agent, payment, inventory, and public offer-form logic.
- `harmat-legal-documents`: protected lawyer document portal and sales-side legal view.
- `harmat-local-assistant`: local AI assistant knowledge base and controlled FAQ behavior.
- `harmat-lakaskereso-redesign`: apartment search redesign layer.
- `harmat22-map-redesign`: custom project/location map module.
- `wp-custom-map-layers`: custom map-layer support.
- `360`: current active 360 apartment selector plugin source.

## Basic Restore Steps

1. Restore WordPress core, theme, third-party plugins, database, and uploads from the hosting backup first.
2. Copy the files from `wp-mu-plugins/` into `/wp-content/mu-plugins/`.
3. Copy the folders from `wp-plugins/` into `/wp-content/plugins/`.
4. In WordPress admin or WP-CLI, activate the custom plugins that should be active.
5. Clear WordPress cache, page cache, and Autoptimize cache.
6. Verify the public smoke-test pages:
   - `/`
   - `/lakaskereso/`
   - `/elerhetosegeink/`
   - one sample property page, for example `/property/a1-f-l1/`
   - `/sales/`
   - `/agent/`
   - `/lawyer/`

## Important Notes

- Do not restore this repository over a live site without first creating a hosting-side backup.
- Do not commit database dumps, raw backups, private uploads, customer documents, lawyer documents, cookies, passwords, or SSH keys.
- If the homepage cache issue is fully resolved later, `harmat-homepage-nocache.php` can be reviewed and removed in a separate small change.
