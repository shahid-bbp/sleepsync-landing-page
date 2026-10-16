# SleepSync verification and troubleshooting

## Verification status

Implementation and local verification are complete. Hostinger upload, live endpoint routing, account-specific PHP permissions, mailbox authentication and **actual delivery to web@thesleepsync.in remain unverified**. A local TLS SMTP fixture is not Hostinger. Browser tests use mocked responses, not the live endpoint.

The test suite uses PHP 8.4.26 and PHPMailer 7.1.1. Chrome tests run at 1440?1000 and 390?844, including keyboard step navigation. Mobile viewport testing is not a substitute for an actual iOS/Android device check.

## Repeatable local tests

```text
node --check script.js
python tests/backend-integration.py path/to/php
npm install --prefix .tools/browser --no-save playwright
node tests/browser.cjs
```

For the workspace-local PHP runtime downloaded during implementation:

```powershell
python tests/backend-integration.py .tools/php/php.exe
```

PHP needs OpenSSL enabled; the harness enables it for the local Windows runtime. Python uses only standard-library modules. It starts a loopback PHP server and TLS SMTP fixture, creates an ephemeral test certificate and uses only a synthetic password. No real SMTP connection occurs. The loopback test router emulates web-server HTTPS and redirects PHP's SMTP connection inside the harness only. **Never deploy `tests/`, `.tools/` or `.test-output/`.** Production code has no test-mode switch.

API coverage includes exact allowed origins (current GitHub and future apex), missing/forged origins, preflight method/header rejection, HTTPS enforcement, GET rejection, all required fields, strict field types, invalid email and header injection, numeric bounds, checkbox arrays, consent, malformed JSON/UTF-8, content type, bounded chunked bodies, honeypot detection, HTML escaping, all fields in the plain-text email, real PHPMailer TLS submission, authentication/rejection/timeout failures, duplicate suppression, concurrent reservation, IP/global/daily limits and unavailable/corrupt storage. Error bodies reveal no credentials or SMTP transcript.

Browser coverage includes all four steps, forward-jump validation, the category requirement, consent, same-as-mobile helper, complete JSON serialization, loading/disabled state, duplicate clicks, non-success HTTP/JSON, invalid response bodies, network failure, rate-limit messages, server field focus, keyboard steps, success-only reset and responsive overflow checks. Reduced-motion mode is exercised. Screenshot artifacts in `.test-output/` are local only.

## Live acceptance checklist

Use clearly marked synthetic test details, with an applicant email you control. Avoid stressing the production rate limit; fault-injection tests belong on a separate staging copy with private configuration.

| Scenario | Expected result |
| --- | --- |
| Real enquiry from the GitHub Pages form | One HTTPS POST, 200 with `ok: true`, `code: SMTP_ACCEPTED`, and reference; form resets; matching message received at `web@thesleepsync.in` |
| All business/store/investment fields | All selected categories and every existing field appear in HTML and text alternatives; no unexpected header recipients |
| Reply-To | Visitor address only; fixed From/To `web@thesleepsync.in`, fixed subject |
| Missing required field | Client returns to its step; direct API gets 422; no email |
| Invalid email, array/object text, negative/fractional count, invalid enum | 422; no email; values retained on the page |
| Malformed JSON / oversized body / wrong content type | 400 / 413 / 415 respectively; server-level rejection may be HTML; frontend still reports failure |
| Filled or malformed honeypot | 422, no success claim and no email |
| Bad SMTP password on staging | 502 `EMAIL_SEND_FAILED`, no secrets in response, no reset; matching retries temporarily blocked |
| SMTP rejection or timeout on staging | 502 or upstream/network error; no success/reset; uncertain result explained |
| Rapid submit clicks | One fetch while pending; button and fields disabled until settled |
| Repeated identical successful payload | Prior reference returned for 24 hours without another send (attempt limits still apply) |
| Identical request while pending or after an uncertain failure | 409 for 15 minutes; no automatic resend |
| More than 5 POST attempts in 15 minutes from one IP | 429 and `Retry-After`; includes invalid allowed-origin attempts |
| Storage unavailable or corrupt on staging | 503, no send; limiter never fails open |
| Disallowed/missing Origin | 403 without `Access-Control-Allow-Origin`; no send |
| Browser offline, DNS failure, TLS failure or CORS blocked | Clear connection error, entries preserved; no automatic retry |
| Desktop/mobile/keyboard | Four steps usable; no horizontal overflow; submit/error status accessible; same-as-mobile editable after reset |
| Private resources | `/api/test-smtp.php`, `/api/lib/security.php`, `/api/PHPMailer/SMTP.php` do not expose code/diagnostics; config/data are outside every public root |
| Future Hostinger frontend | Repeat real test from `https://thesleepsync.in`; correct origin accepted |

The API exposes `Retry-After` through CORS. Statuses are JSON with `ok`, `code`, `message`, optional field `errors` or a success `reference`. OPTIONS uses 204 with no body. Web-server errors can be non-JSON and are intentionally treated as failures by the client.

## Troubleshooting

| Symptom | Action |
| --- | --- |
| GitHub Pages returns 404 for the API | PHP cannot execute there. Upload to Hostinger and check the configured absolute endpoint URL. |
| Hostinger domain is not ready | Check domain attachment, authoritative DNS, propagation and certificate installation. Frontend submission cannot work until this HTTPS hostname serves the PHP endpoint. |
| CORS error | Inspect OPTIONS and POST in Network. Match the browser's exact Origin; the GitHub repository path is not part of it. Check for apex/www or HTTP redirects and missing CORS on hosting-level errors. Do not use `mode: no-cors`. |
| 403 `HTTPS_REQUIRED` on an HTTPS URL | Confirm the web server exposes HTTPS/443 to PHP. Ask Hostinger about trusted proxy configuration; do not trust arbitrary forwarded headers in the script. |
| Direct browser visit returns 403 | Expected: a top-level navigation has no allowed Origin. Use the documented preflight or actual form. |
| 503 `SERVICE_UNAVAILABLE` | Verify PHP/OpenSSL, private config path and placeholder replacement, data permissions, and complete PHPMailer files. The privacy-safe application log records only a fixed event label and optional reference. |
| 503 `SERVICE_BUSY` | Another short storage update holds the lock. Wait briefly and retry. If persistent, ask hosting support about filesystem locking. |
| 502 `EMAIL_SEND_FAILED` | Verify mailbox login privately in Webmail and SMTP settings; ensure SMTP access is allowed, quota is available and outbound TCP 465 is reachable. Do not enable public SMTPDebug or dump exceptions. Confirm the mailbox before retrying because an acknowledgement can be lost after acceptance. |
| 200 but no inbox email | Check Spam, recipient spelling, mailbox quota, sender authentication and provider logs. Use the reference and timestamp with Hostinger support. SMTP acceptance alone is not final delivery. |
| 409 pending/uncertain | Check the mailbox first. The 15-minute hold prevents immediate duplicate delivery; do not delete state files to bypass it. |
| 429 | Follow `Retry-After`. Shared NAT/proxy users can share a limit. The server intentionally uses `REMOTE_ADDR`; configure a verified server-level real-IP integration if Hostinger requires one. Never accept a visitor's arbitrary forwarded IP. |
| Phone or Unicode text rejected | Phone needs 8+ digits within 18 characters. Text limits are UTF-8 bytes; non-ASCII characters use multiple bytes. Client and server both enforce bounded length. |
| 500/HTML instead of JSON | Check PHP version, `.htaccess` compatibility, `.user.ini` settings and hosting rules. PHP intentionally hides internal errors; inspect private hosting logs, never publish a diagnostic. |
| Private paths blocked by open_basedir | Use an allowed private directory configured by Hostinger, or a host supporting it. Do not relocate credentials into `public_html`. |
| Cannot find SSH or Composer in Single Web hosting | Use File Manager and the already bundled PHPMailer files. No server-side package installation is needed. |

To test incorrect passwords, limits, storage failure or SMTP timeout manually, use a separate staging deployment and its own private state directory. Preserve the production password and state. Retest normal delivery after restoring staging settings.

## Private acceptance record (fill only after receipt)

- Frontend URL:
- API URL:
- PHP version:
- Submission time and timezone:
- Returned reference:
- Receipt verified in Inbox/Spam at:
- All fields and Reply-To checked:
- Desktop and physical mobile devices checked:

Do not publish test applicants' personal details, email content, passwords, SMTP traces or private hosting paths in GitHub issues/screenshots.
