# Kairos Covenant LLC Website

Static website for Kairos Covenant LLC, ready to deploy on Netlify.

## Netlify Deployment

- Build command: leave blank
- Publish directory: `.`
- Entry file: `index.html`
- Project visibility: Public

The site is a single static HTML file with inline assets and a `mailto:` contact form, so it does not require Node, npm, or a build step.

If the deployed site says "This site is private", open the site in Netlify and change:

`Project configuration` -> `Access & security` -> `Project visibility` -> `Public`

Also check:

`Project configuration` -> `Access & security` -> `Visitor access` -> disable password or team login protection for production deploys

## Local Preview

Open `index.html` directly in a browser, or serve the folder with any static file server.
