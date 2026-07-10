# TorqueLink Media Decap Prototype

This folder is an isolated CMS prototype. It does not replace the current root presentation.

## Start Locally

From the project root:

```sh
nvm use
npm install
npm run cms:start
```

The terminal prints the actual BrowserSync URL. By default it is:

- Site preview: http://localhost:3000/decap/
- CMS admin: http://localhost:3000/decap/admin/

If port 3000 is already busy, BrowserSync will choose the next available port, for example `3001` or `3002`.

The local CMS edits `decap/content/site.json`.

## Files

- `index.html` - CMS prototype page
- `content/site.json` - editable page content
- `cms-content.js` - loads JSON content into the page
- `admin/config.yml` - Decap CMS field setup
- `admin/index.html` - Decap CMS app shell
- `uploads/` - CMS media uploads

## Deployment Notes

For Netlify:

1. Deploy the repo.
2. Change `admin/config.yml` repo from `owner-name/torquelink-media` to the real GitHub repo.
3. Enable Netlify Identity/Git Gateway or configure GitHub OAuth.

For GitHub Pages:

1. GitHub Pages can host `/decap/`.
2. Decap login still needs a GitHub OAuth proxy.
3. Update `backend.repo`, `backend.branch`, and OAuth settings before handing it to the client.
