# SleepSync Signature Store Franchise Landing Page

The existing HTML/CSS/JavaScript site now submits its four-step enquiry form to a PHP + PHPMailer backend on Hostinger. Design, branding and existing fields are retained.

- Current frontend: `https://shahid-bbp.github.io/sleepsync-landing-page/`
- Configured API: `https://thesleepsync.in/api/submit-enquiry.php`
- Later, upload the frontend to `https://thesleepsync.in` alongside the API.

## Setup

Follow [the Hostinger deployment guide](docs/HOSTINGER-PHP-DEPLOYMENT.md) to upload the bundled PHPMailer backend and create private SMTP configuration. Composer and SSH are not required on Hostinger. The template contains placeholders only; never commit the private config.

The frontend shows success only after a valid SMTP acceptance response, retains inputs on errors and prevents concurrent clicks. The API validates every field, escapes HTML email, restricts origins, enforces HTTPS, applies honeypot and rate limits, and suppresses duplicate messages. SMTP acceptance does not prove inbox receipt.

**Live deployment and receipt in `web@thesleepsync.in` remain required before production acceptance.** No live email delivery has been claimed. Review the [testing checklist and troubleshooting](docs/TESTING.md).

## Files

- `index.html`, `styles.css`, `script.js`, `assets/`: static frontend.
- `backend-php/public_html/api/`: PHP endpoint, helpers, bundled PHPMailer and server rules.
- `backend-php/config/sleepsync-config.example.php`: safe template for a private server file.
- `tests/`: isolated PHP/SMTP and browser regression checks; never deploy.
- `tools/package-deployment.py`: creates separate frontend and backend upload archives.

## Local checks and packaging

```text
node --check script.js
python tests/backend-integration.py path/to/php
npm install --prefix .tools/browser --no-save playwright
node tests/browser.cjs
python tools/package-deployment.py
```

The backend tests require PHP 8.2+ with OpenSSL and Python 3.9+. Browser tests use local Chrome by default (`TEST_BROWSER=msedge` selects Edge) and mock all API calls. Tests do not send live email. Preview the frontend with VS Code Live Server; production CORS deliberately excludes localhost. Use the browser harness for local submissions.

Keep backend files out of the static publication. `_config.yml` excludes them from GitHub Pages' default Jekyll build; custom workflows should publish only the generated frontend package contents.
