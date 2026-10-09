# Automatic Reminder Email Controls

## Scope
- MU version 1.0.0 uses only the legal and sales task-reminder recipient filters.
- Legal daily reminders are globally paused, including future lawyer recipients. The non-autoloaded `harmat_legal_task_reminder_email_enabled` option is false; only boolean true, integer 1 or string `1` explicitly enables sending.
- The non-autoloaded `harmat_automatic_reminder_email_exclusions` option contains two private exclusions. Sales applies these exclusions without being globally paused. Re-enabled legal reminders also apply them.
- Sales remains enabled because the user's conditional pause applied only to matching content: legal reminders use `legal_tasks()` and the lawyer route, while sales reminders use `sales_tasks()` with a separate message and sales-task route.
- No recipient addresses are stored in Git. Accounts, roles, historical records, reminder cron schedules, offer emails, credentials and security emails were not changed.

## Deployment Proof
- Verified private backup: `/home/harmath2/codex-backups/automatic-reminder-exclusions-20261009-1204b90654df4708a19a5f1960de2096`.
- Backup directory 0700; every backup file 0600. Readback hashes verified before changes. Backup records previous MU/option absence, reviewed MU bytes, state fingerprints and exact owned option rows.
- Backup manifest SHA-256: `521bee2df18cd4c8cce005f97aec7372d66d9a559224eff5d978943fe9d04ec7`.
- Hidden `.tmp` staging, production PHP 8.3 staged/final lint, atomic installation, exact hash and project cache purge passed. Staging file and deployment lock are absent.
- Published LF PHP SHA-256: `bdf5b36d8df9d1a35b5e4392c3caa63dcd2c81072680b2774eb5edbd1faef66d`.
- Fresh private-helper checks: legal 0 recipients, SHA-256 `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945`; sales 1 recipient, SHA-256 `d5beb15173490ca28930a64ce6a51f25d5ed1bf4d7b7da4a5db9cd7fc5e90479`.
- Both daily reminder hooks retain one event at timestamp `1791619200`. Account/email/role, legal/sales source and protected-state fingerprints matched; the successful run's full cron fingerprint also matched. The count of `harmat_offer_lead` posts, including all post statuses, remained 66; root error_log remained absent.
- The deployment count query was `SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='harmat_offer_lead'`. The parent's separate raw `harmat_broker_leads_v1` option count was 76: a different dataset, not evidence of a change. Deployment did not baseline that option's count or contents.
- Local isolated tests passed 136 assertions, independently repeated by the parent. Checks invoked recipient helpers/filters only, not senders, mail transport, cron execution or task/case mutators.

## Independent Verification
- Parent read-only live verification confirmed the exact published LF MU hash, both private option values and non-autoloaded status, the exact two exclusions, legal 0/sales 1 recipient hashes, and the future-lawyer pause.
- Account, the newly deployed MU, and the existing legal/sales plugin source hashes passed the parent's checks. Reminder cron entries were unchanged; root error_log was absent; staging was absent; private backup permissions were 0700/0600; final production PHP 8.3 lint passed.
- Nine fresh-query HTTP checks returned 200 without PHP-error output: homepage, apartment search, property A1-1-L2, both virtual selectors, construction, contact, lawyer and sales routes.
- This release did not perform browser layout/interaction QA, mail-delivery tests, reminder-sender/cron execution or form submissions. HTTP smoke and recipient dry runs do not establish actual email delivery or a full-site scan.

## Guarded Recovery
- Earlier guard failures rolled back only matching owned option rows and the matching new MU; a pre-write abort changed neither. Their private backups were retained.
- Successful-run originals were absent. Recovery must compare current full option rows against private `owned-options.json` and current MU against the published hash before removing either. Stop on concurrent changes; never restore account, cron, source, lead or historical data.
