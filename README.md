# Kairos Covenant LLC Website

Static website for Kairos Covenant LLC, ready to deploy on Netlify.

## Netlify Deployment

- Build command: leave blank
- Publish directory: `.`
- Entry file: `index.html`
- Project visibility: Public

The site is a static HTML website with Netlify Forms, so it does not require Node, npm, or a build step.

## Forms

The public forms use Netlify Forms:

- `vendor-partnership`
- `submit-requirement`
- `talent-network`
- `contact`

Configure form notification emails in Netlify so submissions route to the right internal inboxes. Do not list the internal routing aliases publicly on the website:

- Partnerships: `partners@thekcsoft.com`
- Requirements form internal notification: `vendors@thekcsoft.com`
- Talent network form internal notification: `recruiting@thekcsoft.com`
- Public general contact: `info@thekcsoft.com`

If the deployed site says "This site is private", open the site in Netlify and change:

`Project configuration` -> `Access & security` -> `Project visibility` -> `Public`

Also check:

`Project configuration` -> `Access & security` -> `Visitor access` -> disable password or team login protection for production deploys

## Local Preview

Open `index.html` directly in a browser, or serve the folder with any static file server.
