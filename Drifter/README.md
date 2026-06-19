# DRIFTER Static Site

Static, componentized homepage for DRIFTER, built from the supplied brand book and homepage SVG.

## Structure

- `src/components/atoms` - small primitives such as buttons, labels, and brand marks.
- `src/components/molecules` - grouped interface/content blocks.
- `src/components/organisms` - full page sections.
- `src/pages/index.html` - page template with component includes.
- `src/styles/main.pcss` - custom PCSS for brand effects, responsive details, and non-utility styling.
- `public/assets` - extracted and site-ready image/SVG assets.
- `dist` - generated static output.

## Commands

```bash
npm run build
python3 -m http.server 4173 --directory dist
```

The page also uses Tailwind's browser build for utility styling, while DRIFTER-specific layout, texture, and interaction details live in `src/styles/main.pcss`.
