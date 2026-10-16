# SleepSync email submission: Hostinger deployment

## Your current and future URLs

- Current frontend: `https://shahid-bbp.github.io/sleepsync-landing-page/`
- PHP API configured in the form: `https://thesleepsync.in/api/submit-enquiry.php`
- Future frontend: `https://thesleepsync.in`
- SMTP: `smtp.hostinger.com`, port **465**, implicit SSL/TLS, username and recipient **web@thesleepsync.in**.

The API URL must already point to a PHP website on Hostinger before submissions can work from GitHub Pages. Moving the frontend later does not require changing this API URL. GitHub Pages is a static host and does not execute PHP. If you instead point `thesleepsync.in` at GitHub Pages, use a separate Hostinger hostname such as `api.thesleepsync.in` for the PHP endpoint and update both `action` and `data-endpoint` in `index.html`. DNS cannot route only `/api` to a different host. [GitHub Pages documentation](https://docs.github.com/en/pages/getting-started-with-github-pages/what-is-github-pages)

**Acceptance is still pending until a real enquiry appears in the recipient mailbox.** Local automated tests use a local SMTP server or mocked responses. An SMTP success response means the mail server accepted the message; it does not prove final inbox delivery.

## 1. Check PHP hosting and the mailbox

1. Open [hPanel](https://hpanel.hostinger.com/), select **Websites**, then the dashboard for the PHP website associated with `thesleepsync.in`.
2. Open **Advanced ? PHP Configuration** (or search the dashboard for ?PHP Configuration?). Select a supported PHP **8.2 or newer**; PHP 8.4 is used for this project's local tests. If this menu is absent, confirm with support that this website uses PHP web hosting, rather than a Website Builder-only site.
3. Under PHP extensions, confirm **openssl** is enabled. JSON, filter, hash, file access, and `flock` must be available. This implementation does not require curl, mbstring, a database, SSH or Composer on the server.
4. Keep `display_errors` off. Set `post_max_size` to at least `64K`, and `max_execution_time` to `60` seconds if your plan allows. The endpoint itself accepts at most 32 KiB. The bundled `.user.ini` supplies these defaults; hPanel settings and propagation may affect when they apply.
5. Under **Emails**, confirm `web@thesleepsync.in` exists and can sign into Hostinger Webmail. Find its outgoing server details under the mailbox's configuration settings. Verify SPF, DKIM and MX records using the values shown by Hostinger; retain mail DNS records when changing website DNS.

Hostinger documents [PHP extensions and options](https://www.hostinger.com/support/4667515-how-to-manage-php-extensions-and-options-in-hostinger/), [email server settings](https://support.hostinger.com/en/articles/1575756-how-to-get-email-account-configuration-details-for-hostinger-email), and that [Single Web plans lack SSH](https://www.hostinger.com/support/1583245-how-to-connect-to-a-hosting-plan-via-ssh-in-hostinger/). Account-specific features must be confirmed in your own dashboard; none have been inspected here.

## 2. Point the API domain to Hostinger and enable HTTPS

1. Associate `thesleepsync.in` with this hosting website in hPanel.
2. At the authoritative DNS provider, use the website IP/DNS instructions shown by Hostinger. Keep the current `github.io` frontend available while setting up the API domain.
3. Enable SSL for `thesleepsync.in` in **Security ? SSL**. Wait for DNS propagation and a valid certificate. Test the HTTPS hostname without a certificate warning. [Hostinger SSL instructions](https://support.hostinger.com/en/articles/1583258-how-to-install-lifetime-ssl-at-hostinger)
4. Use the exact HTTPS API URL with no redirect, including no redirect between apex and `www`. Fetch deliberately rejects redirects. Never disable certificate verification to work around TLS failures.

The endpoint refuses plain HTTP. It trusts the web server's HTTPS indicator, not arbitrary `X-Forwarded-Proto` headers. If your Hostinger proxy configuration does not set `HTTPS`/port 443 correctly, ask Hostinger to configure the trusted proxy at the server level. Do not weaken the PHP check.

## 3. Locate the correct directories

Open **Files ? File Manager**. Use **Access all files of your web hosting** if needed to reach the parent of the website's `public_html`. Actual account paths vary; a common layout is `/home/u123456/domains/thesleepsync.in/`. Do not copy that example username literally. [File Manager guide](https://www.hostinger.com/support/4548688-basic-actions-in-the-file-manager-in-hostinger/)

Use this structure relative to the **parent of the public web root**:

```text
<website-parent>/
  config/
    sleepsync-config.php           PRIVATE: create on Hostinger only
  data/                            PRIVATE: writable by PHP
  public_html/
    api/
      .htaccess
      .user.ini
      submit-enquiry.php
      test-smtp.php                 inert replacement for the old diagnostic
      lib/
        .htaccess
        security.php
        validation.php
        email-template.php
      PHPMailer/
        .htaccess
        Exception.php
        PHPMailer.php
        SMTP.php
        LICENSE
        SHA256SUMS
        VERSION.txt
      composer.json                optional local Composer alternative
```

The endpoint loads exactly `<website-parent>/config/sleepsync-config.php` by default. It rejects configuration and runtime storage inside the public document root. There is no public-root fallback.

If you later use a subdomain with a document root nested inside another public directory, secrets must be outside **every** public web root. Use an absolute private path via the server environment variable `SLEEPSYNC_CONFIG_PATH`, and set `storage_path` to a private directory. Confirm with support how to set environment variables for your PHP process; hPanel does not universally expose a general environment-variable editor.

## 4. Create private configuration

1. Create `config` beside `public_html`.
2. Copy `backend-php/config/sleepsync-config.example.php` into that directory and name the new server file **sleepsync-config.php**.
3. In Hostinger File Manager's editor, replace only `REPLACE_WITH_PRIVATE_MAILBOX_PASSWORD` with the mailbox password. This is the email password, not the hPanel password. Escape a literal single quote as `\'` and a literal backslash as `\\` inside the PHP single-quoted string.
4. Keep the default SMTP username, host, SSL/465, sender and recipient. This endpoint intentionally permits only the specified Hostinger mailbox and transport.
5. Create `data` beside `public_html`. The template's `storage_path` resolves to it. PHP must be able to write there.
6. Keep the two exact origins in the template for the current deployment and future move:

```php
'allowed_origins' => [
    'https://shahid-bbp.github.io',
    'https://thesleepsync.in',
],
```

A browser origin has no repository path or trailing slash. Add `https://www.thesleepsync.in` only if visitors actually use it. Remove the `github.io` entry after retiring that frontend. Do not add `*`, `null`, arbitrary origins, or localhost to the production list. CORS allows any page on the specified origin, and scripted clients can forge an Origin header; this is abuse reduction, not authentication.

Alternative: if Hostinger provides a supported server environment mechanism, set `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `MAIL_TO`, `MAIL_FROM`, `ALLOWED_ORIGINS` (comma-separated origins), and `STORAGE_PATH`. Environment values override the file. `SLEEPSYNC_CONFIG_PATH` selects an absolute private file. Prefer the private PHP file on Single Web hosting. Do not place `.env`, passwords, or configuration in `public_html`, GitHub settings intended for public frontend builds, HTML, JavaScript or this chat.

**Existing credential remediation:** a password-like value was found in the pre-existing example configuration and replaced with a placeholder. If it was real, rotate that mailbox password before deployment. The existing private local `sleepsync-config.php` is now ignored and was not included in packages. If an old public `test-smtp.php` was uploaded, replace it with the inert version included here immediately.

## 5. Install PHPMailer and upload the API

**Recommended, no Composer needed:** this checkout includes the three unmodified runtime classes from [PHPMailer 7.1.1](https://github.com/PHPMailer/PHPMailer/releases/tag/v7.1.1), its license and a SHA-256 manifest. Upload the complete `backend-php/public_html/api` folder into the server's `public_html`. Ensure hidden `.htaccess` and `.user.ini` files are included. The endpoint loads these classes directly.

Optional local Composer method: install PHP and Composer on your own computer, then run from `backend-php/public_html/api`:

```text
composer install --no-dev --prefer-dist --optimize-autoloader
composer audit --no-dev
```

Upload the resulting **entire `vendor` directory**, including `vendor/autoload.php`, with the endpoint. No Composer command runs on Hostinger. The endpoint prefers this autoloader when present. Avoid a stale `vendor` directory overriding the bundled classes; update the entire installation consistently. Retain `composer.lock` if you choose this route. Check the upstream security/release pages before future dependency upgrades.

To produce clean upload archives locally, run:

```text
python tools/package-deployment.py
```

`dist/sleepsync-hostinger-api.zip` includes only the API, placeholder config template and guides; it never includes your real local configuration. Extract the ZIP locally and upload its `public_html/api` folder as above. The separate `dist/sleepsync-frontend.zip` contains only the website. Do not upload the entire working directory.

## 6. Set permissions and verify private storage

In File Manager, use **Permissions** on each file/folder:

- Public folders: **755**. Public PHP files: **644**.
- Private `config` and `data` folders: **700**. Private config: **600**.
- If your PHP process uses a separate hosting group, use **750/640** only as directed by Hostinger. Never use 777.

The application creates state files with a restrictive umask and uses exclusive locks plus atomic replacement. A storage error stops sending; it never bypasses rate limiting. Do not reset the state file merely to retry a possibly delivered email. It contains keyed hashes, times, status and reference numbers, not form contents or raw IPs; expired entries are removed on later requests. Include it in private hosting maintenance and backups, not public files.

If permissions or `open_basedir` prevent access to sibling private folders, ask Hostinger to provide an allowed private directory or supported PHP environment configuration. If that cannot be provided on the account, use a PHP hosting setup that can provide private config and writable storage. Do not put secrets under the public root as a workaround. Multiple backend servers require a shared transactional store such as Redis or a database; the supplied file limiter is for one shared-hosting filesystem.

## 7. Publish the frontend on GitHub Pages

1. Commit the updated `index.html`, `styles.css`, `script.js`, `.gitignore` and `_config.yml` along with the safe implementation files. Review staged changes before pushing; do not force-add ignored config, `.tools`, `.test-output`, or `dist`.
2. Keep these attributes on the existing form:

```html
<form id="franchise-form" method="post"
      action="https://thesleepsync.in/api/submit-enquiry.php"
      data-endpoint="https://thesleepsync.in/api/submit-enquiry.php" novalidate>
```

3. Publish through your existing GitHub Pages setup and refresh `https://shahid-bbp.github.io/sleepsync-landing-page/` after deployment.
4. The supplied `_config.yml` excludes backend, docs and tests from the default Jekyll publication. If you use a custom Actions workflow or `.nojekyll`, publish **only** the frontend files from `dist/sleepsync-frontend.zip`. `.gitignore` prevents future additions; it does not remove already tracked secrets or stop arbitrary deployment tools from copying files.

No push or live deployment has been performed by this local implementation.

## 8. Verify the endpoint and perform the real acceptance test

A direct browser visit has no allowed Origin and should return **403**, not a diagnostic page. Use a preflight to verify connectivity without sending mail:

```powershell
curl.exe -i -X OPTIONS "https://thesleepsync.in/api/submit-enquiry.php" -H "Origin: https://shahid-bbp.github.io" -H "Access-Control-Request-Method: POST" -H "Access-Control-Request-Headers: content-type"
```

Expect **204**, `Access-Control-Allow-Origin: https://shahid-bbp.github.io`, and `Vary: Origin`. This checks routing/CORS, not SMTP configuration. The old `/api/test-smtp.php` and `/api/lib/security.php` URLs should return **403/404** and reveal no diagnostics.

1. Open the live GitHub Pages form. Complete all four steps with clearly labelled test details and an email address you control. Include `Deployment acceptance test ? please ignore` in comments.
2. Open developer tools ? **Network**, submit once, and inspect the API request. Expect HTTPS JSON POST; an OPTIONS request may appear first.
3. Only **200** with `ok: true`, `code: SMTP_ACCEPTED` and an `SS-?` reference should show success/reset the form. Record that reference privately.
4. Sign into [Hostinger Webmail](https://mail.hostinger.com/) as `web@thesleepsync.in`. Check Inbox and Spam. Confirm the message has the same reference, all selected fields, readable rupee symbols, comments and confirmed consent. Confirm Reply-To is the test applicant and From is the domain mailbox.
5. Record receipt date/time and reference in your private deployment notes. **Do not mark the integration complete until this receipt is verified.** A missing message requires mail-delivery investigation even if SMTP returned 200.

An optional Hostinger Mail connection can allow mailbox inspection from this conversation; it does not upload PHP files or configure hosting. It has not been confirmed connected here.

## 9. Move the frontend to Hostinger later

Upload only the frontend package contents into the same `public_html`, alongside the existing `api` folder. Keep `config` and `data` outside it. Ensure `https://thesleepsync.in` serves the frontend and HTTPS works, then repeat the real test from that domain. Keep the API URL unchanged. If you enable `www`, either redirect the frontend to the apex or explicitly add its origin. After retiring GitHub Pages, remove `https://shahid-bbp.github.io` from the server allowlist.

## 10. Keep secrets out of history and maintain the service

Before committing, run `git status --short` and `git diff --cached`. Verify `git check-ignore backend-php/config/sleepsync-config.php` reports the file. No real password belongs in an example, archive or fixture. The local tests use an explicitly fake password and an isolated private config.

If a real password was previously committed or published, rotate it first, remove the secret from all published copies and artifacts, then arrange repository-history cleanup. Adding `.gitignore` does not purge history; coordinate any history rewrite with collaborators.

Rate defaults: 5 POST attempts per IP per 15 minutes, 100 global attempts per 15 minutes and 100 mail attempts per rolling day. Invalid allowed-origin POSTs count. Adjust the private config to your traffic and mailbox quota; these values do not override Hostinger's own sending limits. Repeated identical validated enquiries reuse the existing success reference for 24 hours. In-progress or failed/uncertain sends block matching retries for 15 minutes. SMTP cannot guarantee exactly-once delivery after an ambiguous connection loss; contact the team before retrying. Distributed spam may need an additional server-verified CAPTCHA or upstream filtering.

See [TESTING.md](TESTING.md) for the complete checklist and troubleshooting.
