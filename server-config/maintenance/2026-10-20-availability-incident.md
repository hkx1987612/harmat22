# October 20 Availability Incident - Read-Only Investigation

## Scope

User prioritizes determining whether the outage came from site changes or hosting.
Whole-site style audit paused immediately. This investigation made no production
code/configuration changes, rollback, service restart, security change, database
restore, or quote/appointment/customer submission. Its unfinished local-only
crawl draft was removed; no completed 145-page style report exists for this run.
Current stable production source remains footer 2026.10.20.2 and home 1.0.15.

## Observations

- User independently reported failure on mobile data as well as desktop.
- DNS resolved harmat22.hu to the hosting portal's server address. TCP connected,
  but TLS handshake and SSH banner each timed out after eight seconds. A later
  isolated HTTPS attempt was refused. No SSH server command ran in these attempts.
- Hosting subscription active; quota statistics in the portal were stale, not
  evidence of current resource health. Published provider status did not identify
  this incident, which does not exclude a server-specific fault.
- wePanel initially showed only its shell: charts/time selector kept loading and
  feature links did not navigate. After an ordinary reload, resource charts and
  error-log navigation became functional without a site change.
- The visible CPU chart remained below its 400 limit; visible memory/process
  charts did not show sustained saturation. These rendered account-level charts
  cannot establish host-wide health or exclude short/network faults. October 20
  overload time selector exposed no selectable events; no explicit all-clear.

## Error Logs Actually Read

Both logs were downloaded through wePanel's own visible download controls to
local Downloads only. Raw logs/screenshots are not committed or sent elsewhere.

- Public web-root error log: 18,344 bytes, 99 lines, all PHP warnings, zero PHP
  fatal errors. Last entry 2026-10-20 08:52:34 UTC (10:52:34 Budapest). Warnings
  include undefined `$text`/null array offsets in harmat-sales-manager and older
  cache-directory warnings. They are real code-quality issues, but do not prove
  the cause of a pre-HTTP/TLS and SSH outage. No plugin repair authorized/performed.
- Account-level log: 66,915 bytes, 446 lines, 21 historical fatal entries. Today's
  two fatal entries, 09:59:02 and 09:59:41 Europe/Budapest, are the private staging
  home-six R2 test refusing a frozen 1.0.14 fixture. This exact failure is already
  recorded in 2026-10-20-home-six.md, before live activation. It is not a public
  request crashing the deployed homepage. Other fatal entries are older CLI or
  private test failures. Do not present the word Fatal alone as site-crash proof.
- These are account/PHP logs, not the provider's nginx/TLS/firewall/kernel logs.
  No database outage or hosting root cause has been verified from them.

## Recovery And Conclusion

At 13:10 Europe/Budapest, an isolated homepage HEAD returned HTTP 200, TCP
0.056 s, TLS 0.109 s, total 1.096 s. Follow-up HEADs on apartment search and
contact also returned 200 (0.145 s / 1.193 s). No rollback or production edits
occurred between failure and recovery. This is availability evidence only,
not a new quote/appointment submission or complete functional certification.

Evidence favors a temporary hosting/service/network or protective filtering
event over a footer CSS/PHP deployment crash. Root cause remains unconfirmed.
The preceding serial read-only crawl could have contributed load or triggered
rate protection; do not exclude that possibility without provider logs.

## Provider Ticket And Next Step

One authorized urgent technical ticket was submitted successfully. The receipt
explicitly states "A bejelentes letrehozva" / "Levelet fogadtuk" (original UI
uses Hungarian accents). No ticket number displayed; portal explains tickets
after April 2023 are not shown in its old list. Do not resubmit because list is
empty. Receipt screenshot retained privately under ignored outputs.

Ask provider to correlate incident time with web/TLS/SSH service, host load,
firewall/rate-limit and account restriction logs. Do not assert who is at fault
or recommend a speculative database restore. Resume style work only separately.
