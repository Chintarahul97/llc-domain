# Kairos Covenant LLC Website

Static website for Kairos Covenant LLC, ready to deploy on Hostinger or Netlify.

## Hostinger Deployment

- Upload all tracked files, including `.htaccess`, `index.html`, `404.html` and the `api/` directory.
- The site uses client-side routes with an Apache `.htaccess` fallback so direct visits to routes such as `/submit-requirement`, `/careers` and `/contact` load `index.html`.
- API requests under `/api/` are excluded from the fallback and must reach the PHP files directly.
- HTTP is redirected to HTTPS and `www.thekcsoft.com` is redirected to `https://thekcsoft.com`.

## Production Forms

Forms submit with JavaScript to `/api/submit-form` and display inline success/error states without navigating visitors to `/404`.

Server-side routing:

- Vendor partnership form -> `partners@thekcsoft.com`
- Submit requirement form -> `vendors@thekcsoft.com`, with `partners@thekcsoft.com` copied
- Talent network form -> `recruiting@thekcsoft.com`
- Contact form -> `info@thekcsoft.com`

The PHP endpoint validates required fields, email addresses, honeypot submissions and upload types. Requirement attachments may be PDF, DOC, DOCX or TXT. Resume uploads may be PDF, DOC or DOCX. File uploads are limited to 8 MB.

Hostinger can use `api/submit-form.php`, which currently uses PHP `mail()`. For higher reliability on Hostinger, configure authenticated SMTP server-side and keep SMTP credentials out of frontend JavaScript and source control.

Current DNS/production checks show `thekcsoft.com` is served by Netlify. When Netlify serves the site, `/api/submit-form` is rewritten to the Netlify Function in `netlify/functions/submit-form.js`. Add these Netlify environment variables so the function can send email:

- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_USER`
- `SMTP_PASS`
- `SMTP_SECURE` (`true` for port 465, otherwise `false`)
- `MAIL_FROM` such as `Kairos Covenant Website <info@thekcsoft.com>`
- Optional `MAIL_BCC` to copy every form submission to the admin mailbox. If omitted, submissions are copied to `SMTP_USER`.

## Netlify Deployment

- Build command: leave blank
- Publish directory: `.`
- Entry file: `index.html`
- Project visibility: Public

If deploying to Netlify instead of Hostinger, use the included Netlify Function because Netlify does not execute PHP.

If the deployed site says "This site is private", open the site in Netlify and change:

`Project configuration` -> `Access & security` -> `Project visibility` -> `Public`

Also check:

`Project configuration` -> `Access & security` -> `Visitor access` -> disable password or team login protection for production deploys

## Local Preview

Open `index.html` directly in a browser, or serve the folder with any static file server.
