# DRIFTER Static Site

Static, componentized homepage for DRIFTER, built from the supplied brand book and homepage SVG.

## Structure

- `src/pages/index.html` - source page template.
- `src/styles/main.pcss` - source PCSS for layout, interactions, and responsive behavior.
- `public/assets` - source image, SVG, and JavaScript assets.
- `scripts/build.mjs` - static build script.
- `../index.html`, `../styles`, `../assets` - generated site output in the git root.

## Commands

From `Drifter/`:

```bash
npm run build
cd ..
python3 -m http.server 4173
```

Open `http://localhost:4173`. The generated page lives at the git root so it works with GitHub Pages for `ykosinets.github.io`.
