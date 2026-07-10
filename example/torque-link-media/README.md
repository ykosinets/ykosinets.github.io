# TorqueLink Media static site

Static one-page site for TorqueLink Media.

The main generated files live in the project root:

- `index.html`
- `new.html`
- `style.css`
- `app.js`

Source files live in `sources/`:

- `sources/components/` - section components with their own HTML, CSS, and JS
- `sources/styles/` - base/global styles
- `sources/scripts/` - base/global scripts
- `sources/index.html`, `sources/style.css`, and `sources/app.js` - entry files with includes

## Setup

Use the Node version from `.nvmrc`, then install dependencies.

```sh
nvm use
npm install
```

## Development

Start the local development server with file watching for HTML, CSS, and JS.

```sh
npm start
```

The dev script rebuilds source chunks and reloads the browser when files change.

## Build

Build the generated root files from `sources/`.

```sh
npm run build
```

## Final Package

Build the site and create the final zip in the project root.

```sh
npm run final
```

To optimize images before packaging, run:

```sh
npm run final:optimized
```

The archive includes:

- `index.html`
- `new.html`
- `style.css`
- `app.js`
- `contact.php`
- `contact-config.sample.php`
- `assets/`

## Contact Form Integration

The contact form posts to `contact.php`, verifies reCAPTCHA v3, then sends the enquiry by email to `info@torquelinkmedia.com`.

Before uploading to production:

1. Create a reCAPTCHA v3 key for the production domain.
2. Replace `REPLACE_WITH_RECAPTCHA_SITE_KEY` in `sources/index.html`.
3. Copy `contact-config.sample.php` to `contact-config.php`.
4. Fill in the reCAPTCHA secret key and confirm the recipient/from email values.
5. Run `npm run build` or `npm run final:optimized`.
6. Upload `index.html`, `style.css`, `app.js`, `contact.php`, `contact-config.php`, and `assets/`.

Keep `contact-config.php` private because it contains the reCAPTCHA secret key.

Note: PHP `mail()` depends on the hosting provider's mail configuration. If delivery is unreliable, switch the same handler to SMTP through the hosting provider's mailbox.

## Decap CMS Prototype

An isolated Decap CMS implementation lives in `decap/`. It does not replace the current root presentation.

```sh
npm run cms:start
```

See `decap/README.md` for local editing and deployment notes.

## Image Optimization

Create WebP versions in `assets/optimized/`. Smaller same-format JPG/PNG copies are kept only when they beat the original file size.

```sh
npm run optimize:assets
```

The script is non-destructive. It keeps the original files in `assets/` and writes optimized output separately.

## Quality Checks

Run CSS linting:

```sh
npm run lint:css
```

Format source and project files:

```sh
npm run format
```
