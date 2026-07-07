# Reusable project starters

Copy a starter into `example/<project-name>` or `production/<project-name>` before changing it.

- `wp/`: WordPress core with the minimal `starter` block theme. Create `wp-config.php` and a database after copying.
- `shopify/`: Shopify's official Skeleton Theme. Connect the copied theme to a development store with Shopify CLI.
- `payload/`: Payload's official blank Next.js template, configured for local SQLite.
- `sanity/`: Minimal standalone Sanity Studio configured through environment variables.

These projects cannot run directly on GitHub Pages: WordPress requires PHP and MySQL/MariaDB, while Shopify Liquid is rendered by Shopify.
