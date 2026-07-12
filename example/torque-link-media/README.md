# TorqueLink Media static site

Static one-page site for TorqueLink Media.

The main generated files live in the project root:

- `index.html`
- `index.php`
- `new.html`
- `style.css`
- `app.js`
- `content/site.json`

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
- `index.php`
- `new.html`
- `style.css`
- `app.js`
- `contact.php`
- `contact-config.sample.php`
- `content/`
  - `site.json`
  - `settings.json`
- `admin/`
- `assets/`

## Contact Form Integration

The contact form posts to `contact.php`, uses a honeypot field and minimum submit-time check for basic spam protection, then sends the enquiry by email. The destination email can also be changed in the custom admin under Form Settings.

Before uploading to production:

1. Copy `contact-config.sample.php` to `contact-config.php`.
2. Confirm the recipient/from email values.
3. Run `npm run build` or `npm run final:optimized`.
4. Upload `index.html`, `index.php`, `style.css`, `app.js`, `contact.php`, `contact-config.php`, `content/`, `admin/`, and `assets/`.

Keep `contact-config.php` out of public source control when it contains live email settings.

Note: PHP `mail()` depends on the hosting provider's mail configuration. If delivery is unreliable, switch the same handler to SMTP through the hosting provider's mailbox.

## Tiny PHP Admin

The site includes a small PHP content editor at `/admin/`. It edits `content/site.json`, and `index.php` renders that content into the HTML before the page is sent to the browser. `index.html` remains a static fallback.

Production setup:

1. Upload `admin/` and `content/` with the rest of the site.
2. On the hosting server, copy `admin/config.sample.php` to `admin/config.php`.
3. Generate a password hash:

```sh
php -r 'echo password_hash("your-password-here", PASSWORD_DEFAULT), PHP_EOL;'
```

4. Paste the generated hash into `admin/config.php` as `password_hash`.
5. Make sure PHP can write to `content/site.json`.

Keep `admin/config.php` out of public source control because it contains the admin password hash.

If saving fails, adjust permissions for `content/` or `content/site.json` through the hosting file manager or FTP client.

If your host serves `index.html` before `index.php`, set the directory index order to prefer `index.php`, or remove `index.html` after confirming `index.php` works.

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
