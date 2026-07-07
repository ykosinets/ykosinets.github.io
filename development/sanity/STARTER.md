# Sanity Studio starter

This is a minimal standalone Sanity Studio with a basic Page schema.

Requires Node.js 22.12 or newer.

After copying this directory into a project:

1. Create a Sanity project and `production` dataset at `sanity.io/manage`, or run `npm create sanity@latest` and use the created project details.
2. Run `npm install`.
3. Copy `.env.example` to `.env` and set `SANITY_STUDIO_PROJECT_ID`.
4. Run `npm run dev`; Studio normally opens at `http://localhost:3333`.
5. Add the development and production frontend origins to the project's CORS settings when required.

Do not commit local environment files or authentication tokens. The project ID and dataset name are public identifiers, not secrets; write tokens are secrets.
