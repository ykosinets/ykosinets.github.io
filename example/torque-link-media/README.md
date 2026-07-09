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
- `assets/`

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
