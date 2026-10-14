# SleepSync Signature Store Franchise Landing Page

Pure HTML, CSS and JavaScript. No framework, bundler or npm dependencies.

## Files
- `index.html` — page content, SEO metadata, navigation and enquiry form
- `styles.css` — responsive layout, visual styling, animation and mobile menu
- `script.js` — mobile navigation, scroll header, reveal effects and multi-step form validation
- `assets/` — official SleepSync brand logos, optimized WebP images (under 100 KB) and SVG assets

## Preview locally
Open `index.html` in a browser, or use the VS Code Live Server extension.

## Important before production
The enquiry form is intentionally in demo mode. It validates the fields, but does not send or save submissions until a secure backend/form endpoint is configured. Add the endpoint to the form as a `data-endpoint` attribute, for example:
`<form id="franchise-form" data-endpoint="https://your-domain.example/api/franchise-enquiries" novalidate>`
The endpoint must accept a JSON POST and should validate fields server-side, apply spam protection/rate limiting, securely store the lead, and notify the franchise team. Do not put private API keys in `script.js`.

Replace the illustrative SVG art with approved SleepSync product/showroom photographs before launch. Verify all commercial claims and financial figures against the current franchise offer document. Add the real privacy-policy URL and configure a canonical URL / social share image for the production domain.

## Deploy
Upload the folder to any static host (Netlify, Vercel, GitHub Pages or traditional hosting). No build command is needed; publish the folder containing `index.html`.
