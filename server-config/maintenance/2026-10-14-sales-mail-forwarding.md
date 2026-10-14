# Sales mailbox forwarding - read-only checkpoint

## Scope and Findings

The user reported a possible mail issue and requested forwarding from the official sales mailbox to a specific Gmail address, then clarified that this had already been configured. The private destination is deliberately omitted from shared Git records.

- Clean shared main `3b5ec5e` pulled up to date before investigation.
- Public MX records point to `mail.harmat22.hu` (priority 0) and `mx2.postmaster.hu` (priority 20). The primary mail host resolves to the existing hosting server.
- Known-host-verified SSH confirmed the hosting account. Its active `mail/harmat22.hu/ertekesites/.dovecot.sieve` links to `sieve/roundcube.sieve`.
- The active script contains one unconditional `redirect :copy` to the exact user-requested Gmail destination. The existing compiled Sieve binary also contains that destination and is newer than the script. These are configuration checks, not proof of successful external delivery.
- Mailbox cache reports quota 0 (unlimited), approximately 52.6 MB usage and login suspension 0. Incoming/outgoing suspension was not independently checked through the control panel.
- Read-only inspection matched the unique marker of the earlier, separately authorized October14 TEST appointment in one local sales email dated 11:49:08 UTC. It is currently in Trash with `X-Spam-Flag: NO`. No claim about who moved it, the customer confirmation, Gmail receipt or all real customer messages.
- cPanel's UAPI executable and system delivery logs are not available through the restricted SSH environment. Chrome hosting portal is at its login page, retained for user handoff. The connected Gmail profile is not the requested destination; its inbox was not searched.

## Resume Safely

Ask the user to log in to the retained Websupport/MHosting portal. Navigate through its existing hosting service to cPanel Track Delivery and inspect only the relevant sales mailbox/forwarding delivery records. Distinguish local acceptance, remote server acceptance and actual destination inbox receipt.

Do not duplicate the already active forwarding rule, disable it, change mailbox credentials, delete messages, loosen spam/authentication protection or alter DNS based on this checkpoint. If a concrete repair is needed, preserve a private rollback copy first and change only the confirmed fault. A new browser action that grants new sensitive-data access requires action-time consent.

No live change, new mail, new appointment, forwarding verification message, cache purge or website/ad/security setting change was made. Do not repeat the earlier successful appointment submission. Website stable tag remains `stable-2026-10-14-appointment-source-current`.

Private forwarding destination, mail content, account data, credentials and screenshots must stay out of Git. The ignored login screenshot is under `outputs/2026-10-14-mail-forwarding/`; public shared records contain only these sanitized facts. Diagnosis remains incomplete pending delivery trace, not a completed fix.
