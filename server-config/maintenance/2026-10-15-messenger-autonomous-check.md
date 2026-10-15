# Messenger autonomous debugging checkpoint

## Current Outcome

Facebook AI is NOT ready or published. The user requested autonomous debugging,
testing before launch and shutdown only after everything really works; subsequent
broad permission covers routine work, not missing credentials or required human
platform steps. No shutdown, future automation, token generation, subscription,
customer message or live service change was performed. Shared baseline was clean
main `b6b7e58`; only this checkpoint's owned files/history additions are included.

## New Tests

One bounded Sol worker created `asgi_app.py` and `test_asgi_app.py`; parent reviewed
them, independently passed177 offline tests in17.113seconds with ResourceWarnings
treated as errors and closed the worker. This includes29 new ASGI tests. The
adapter bounds raw body reads to two seconds total,64KiB and1,024 fragments,
preserves duplicate headers/query pairs and commits metadata before ACK. Gates
default OFF. POST additionally requires explicit metadata-only test mode; there
is no durable question queue, model or sender. `/readyz` ALWAYS returns503.
Do not subscribe real customer traffic to an ACK-and-discard test receiver.

Parent added `2026-10-15-test-messenger-http.py`:20 additional checks passed through
actual local TCP/HTTP using an isolated Uvicorn0.54.0 test environment, h110.16.0
and click8.5.0. Challenge, disabled defaults, bad/duplicate signatures, duplicate
receipts, staff pause persistence, oversized/partial bodies and actual two-second
socket timeout were checked with synthetic data only. All listeners stopped and
temporary databases were removed. This is NOT Meta delivery, remote HTTPS, an AI
reply or a real staff-takeover test. No model API request or paid service was used.

```text
cd server-config/messenger-prototype/nogakft
python -B -W error::ResourceWarning -m unittest discover -q
```

The optional HTTP check needs an operator-selected isolated virtual environment;
never install these dependencies into the old production service automatically.
Install from the official PyPI registry with the exact three versions above, then
run its Python from repository root:

```text
python -B -W error::ResourceWarning server-config/maintenance/2026-10-15-test-messenger-http.py --run-loopback
```

Without the explicit flag the script opens no listener. It binds127.0.0.1 with an
ephemeral port only; fixture keys/IDs are synthetic, never production credentials.
The existing nogakft Python3.11.6 environment has no Uvicorn/Gunicorn/Flask; none
was installed there. [Official Uvicorn package](https://pypi.org/project/uvicorn/)
documents the version/Python requirement; installation alone is not deployment.

## Actual External Blockers

- The already authorized existing network-maintenance task made ONE additional
  three-second TCP check with bounded packet observation. Three outbound SYNs
  including kernel retransmits reached eth0; no reply was captured. OUTPUT is
  ACCEPT; its sole DROP rule did not increment and the destination was not in its
  deny set. FORWARD's DROP policy is not on this host-originated Python path.
  This does not establish which cloud security group, transit or destination
  firewall drops the traffic. No firewall, hosts, DNS, proxy or route was changed;
  no UA/IP/alternate-host bypass. Packet observation ended, no capture file.
- Existing service model key variables were checked ONLY for nonempty presence:
  both configured model-key variables are absent/empty, external model inactive,
  effective provider host api.openai.com. No key value read back/copied and no
  model request. The configured model name is not usable API access.
- Original hosting client portal opens at its login form, not authenticated
  controls. No password reset, CAPTCHA, credential guessing or support submission.
  Its login tab and existing Meta settings were retained for user continuation.
- Current Meta settings still show the one intended Page, no subscribed fields,
  unused Generate-token button and blank callback/verify token. Existing app
  remains unpublished. Earlier missing privacy URL/review gates are unresolved;
  no new review or permission request was submitted.

The pre-existing network task returned idle and was not closed/archived. No
simulated check is counted as resolving these external gates. When the owner is
available, resume authenticated hosting diagnostics and provision a legitimate
company model API connection through a protected configuration path, never chat
or Git. Do not purchase another plan or use a personal/unofficial API proxy.
Then implement the queue/cost/retention/sender/privacy controls and perform the
already authorized administrator/tester conversation before considering launch.

## Website Non Interference

Independent public-inventory release verification passes124 units/nine fields,
fresh public-authority parity, GET-only registration/POST404 and all protected
property/offer/CRM hashes. Anti-spam release hash, PHP lint and17 native pure
callback cases pass with historical protected records unchanged. Nine public
routes return200 with no fatal markers. No positive quote/appointment/mail test
was repeated and no inbox-delivery/mobile-visual claim is made.

Final read-only nogakft audit confirms all six protected source/configuration
hashes and modes unchanged, old3022 service active/local health200/providerlocal,
no new service/directory/proxy. No credentials, private IP/account identifiers,
packet data or raw audit results belong in Git. Production tag remains
`stable-2026-10-15-messenger-public-inventory-current`; Git pull must not activate AI.
