# Adminka

A flat-file inline editor for static HTML websites. No database, no JSON, no build step — your HTML files **are** the content storage. Elements marked with a `data-editable` attribute become editable in place, and changes are written straight back into the HTML file.

**Requirements:** PHP 8.1+ (PHP 8.4+ recommended — uses the HTML5-accurate `Dom\HTMLDocument` parser; older versions fall back to legacy `DOMDocument`). Extensions: `dom` (always on), `openssl` + `curl` for Google/Apple sign-in and passkeys, `fileinfo` for uploads — all bundled by default on virtually every host.

---

## 1. Installation

1. Copy `admin.php`, `config.php`, `admin-lib/`, and (optionally) `tools/` into the root of your static site:

   ```
   /your-site
   ├── index.html
   ├── about.html
   ├── admin.php        ← the editor (entry point)
   ├── config.php       ← settings & credentials
   ├── admin-lib/       ← editor internals (PHP modules + ui.js / editor.js)
   ├── tools/           ← CLI helpers (assign-ids)
   ├── assets/content/  ← media library (image/ and video/, created on demand)
   ├── backups/         ← created automatically on first save
   └── data/            ← passkeys + sign-in state, created automatically
   ```

2. Make sure PHP can **write** to the HTML files and create `backups/`, `data/`, and `assets/content/`:

   ```bash
   chown -R www-data:www-data /path/to/your-site
   # or at minimum:
   chmod 664 *.html && chmod 775 .
   ```

3. That's it. No install step, no dependencies.

---

## 2. Set your password (required!)

The default login is `admin` / `changeme`. **Never deploy with the default.**

Generate a new hash:

```bash
php -r "echo password_hash('your-strong-password', PASSWORD_DEFAULT);"
```

Paste the output into `config.php`:

```php
'admin_user' => 'admin',
'admin_hash' => '$2y$10$...paste-hash-here...',
```

The plain password is never stored anywhere.

---

## 3. Sign-in options

Password sign-in always works and is the fallback that keeps you from being locked out. On top of it you can enable:

### Sign in with Google

1. Create an OAuth client at <https://console.cloud.google.com/apis/credentials> (type "Web application").
2. Add the redirect URI: `https://your-site.com/admin.php?action=oauth_cb&provider=google`
3. In `config.php` set:

   ```php
   'oauth' => [
       'google' => [
           'enabled'       => true,
           'client_id'     => '1234-abc.apps.googleusercontent.com',
           'client_secret' => 'GOCSPX-...',
       ],
       ...
   ],
   'oauth_allowed_emails' => ['you@gmail.com'],   // who may sign in
   ```

### Sign in with Apple

Needs a paid Apple Developer account. Create a **Services ID**, enable "Sign in with Apple" for it, register the same redirect URI (`...&provider=apple`), and create a **Sign in with Apple key** (.p8 file). Then:

```php
'apple' => [
    'enabled'   => true,
    'client_id' => 'com.your-site.adminka',   // the Services ID
    'team_id'   => 'ABCDE12345',
    'key_id'    => 'XYZ9876543',
    'key_file'  => __DIR__ . '/AuthKey.p8',   // keep it out of the web root if you can
],
```

Only emails on `oauth_allowed_emails` get in, no matter which provider vouched for them. Identity tokens are validated for issuer, audience, expiry, and a per-attempt nonce.

### Passkeys (Touch ID / Face ID / security keys)

No configuration needed. Sign in once (any method), then click **"Add a passkey for this device"** on the page list. From then on the login screen shows **"Sign in with a passkey"**. Passkeys require HTTPS (or localhost) and are stored in `data/passkeys.json` — delete a line there (or use the Remove button) to revoke one. ES256 and RS256 authenticators are supported, which covers Apple, Google, Windows Hello, and YubiKeys.

---

## 4. Mark up your HTML

Add `data-editable` with a **unique id** to any element the client should be able to change. Optionally add `data-editable-type` to control the editing behavior:

| Type | Element | Client edits | Notes |
|---|---|---|---|
| `text` (default) | any | plain text, inline | HTML typed by the client is escaped; source indentation is shown collapsed and saves are trimmed |
| `html` | any container | rich content, inline | saved markup is sanitized against a tag/attribute whitelist |
| `image` | `<img>` | click → media picker | choose from `assets/content/image`, upload (with optional crop), or paste a URL |
| `video` | `<video>` | click → video dialog | file + poster from the library, background-autoplay toggle, controls toggle |
| `link` | `<a>` | click → text + URL dialog | `javascript:` / `data:` URLs rejected |
| `list` | any container | "+ Add item" / per-item "×" | items duplicated from the last; nested ids renamed; "×" hidden when only one item remains |
| `form` | `<form>` or wrapper | click any control | edit label, placeholder, required, select options + placeholder; names/types/values stay in markup |
| `attrs` | any | hover → ⚙ | attribute-only editing; see below |

Example:

```html
<h1 data-editable="hero-title">We build websites</h1>

<div data-editable="about-body" data-editable-type="html">
  <p>Rich <strong>formatted</strong> content here.</p>
</div>

<img src="team.jpg" data-editable="team-photo" data-editable-type="image">
<video src="reel.mp4" controls data-editable="showreel" data-editable-type="video"></video>

<a href="/contact.html" data-editable="cta" data-editable-type="link">Contact us</a>
```

Rules:
- Each `data-editable` id must be **unique per page** (ids can repeat across different pages).
- For `html` type, the allowed tags and attributes are configured in `config.php` (`allowed_tags`, `allowed_attrs`).

### Editable attributes

Any editable element can additionally expose chosen attributes with `data-editable-attrs`:

```html
<a href="/kit.zip" download data-editable="dl" data-editable-type="attrs"
   data-editable-attrs="href download title">Download the kit</a>
```

Hovering the element in edit mode shows a ⚙ button that opens an attribute
dialog — untick an attribute to remove it, tick with an empty value for boolean
attributes (`download`, `controls`, `loop`, …). Only the attributes listed in the
file can be edited (the server re-reads the list from disk, exactly like types),
event handlers / `style` / `data-editable*` are always refused, and URL-carrying
attributes (`href`, `src`, `poster`, …) go through the same scheme validation as
links. If you list `src` on an image/video element the media picker also
manages, the picker's value wins on save — simplest to not double-manage it.
Use `data-editable-type="attrs"` when attributes are the *only* thing to edit;
combine `data-editable-attrs` with any other type when you want both.

### Forms

Mark a `<form>` (or a wrapper around controls) with `data-editable-type="form"`.
In edit mode every `input`, `textarea`, `select`, and `button` inside becomes
click-to-edit: label text, placeholder (only for input types that support it),
and required; buttons get their text. Structural things — field names, input
types, default values — stay in the markup where the developer put them.

Select options are a simple label list: rename inline, drag ☰ to reorder,
× to remove, + to add. Each option's `value` / `selected` / `disabled`
attributes travel with it untouched — the editor changes only the words.
A select's **Placeholder** field manages a hidden first option
(`<option value="" disabled selected style="display:none;">…</option>`);
it never shows up among the option rows. Submitting is disabled while editing.

### Sliders, popups, and accordions

Edit mode freezes the page's own JavaScript (nothing scrolls, opens, or
navigates), so interactive widgets need two markers:

- `data-editable-scroll` on a slider/carousel track turns it into a horizontal
  scroll-snap strip in edit mode — every slide is reachable by scrolling, no
  arrows needed. Combine with `data-editable-type="list"` to add/remove slides.
- `data-editable-reveal` on popup or accordion content that the live site
  keeps hidden (`display:none`, collapsed height, off-screen modal) shows it
  inline while editing, flagged "Hidden on the live site". The trigger button
  or link is just a normal `text` / `link` editable.

Both are edit-mode-only overrides; the live page is untouched.

### Ids are optional — they assign themselves

You never have to invent ids. Write just the marker:

```html
<h1 data-editable>We build websites</h1>
<img src="team-photo.jpg" data-editable-type="image">
```

The first time a page is opened in the admin (or saved through it), every
editable without an id gets a readable generated one — slugs from the
element's content (`we-build-websites`), transliterated from non-Latin text,
filename-based for images/videos, class-based for lists and forms,
de-duplicated with numeric suffixes. The file is normalized once, with a
backup, and stays stable afterwards. Duplicate ids are healed the same way
(the first occurrence keeps the id). Hand-written ids are never touched, so
name the ones you care about and ignore the rest.

To normalize files before deploying (e.g. to avoid a first-open write on
production), the same logic is available as a CLI:

```bash
php tools/assign-ids.php index.html            # fills ids in, keeps index.html.bak
php tools/assign-ids.php index.html --dry-run  # preview only
```

---

## 5. Media library

Images live in `assets/content/image`, videos in `assets/content/video` (configurable). In edit mode, clicking an image opens a picker with everything in the matching folder, plus:

- **Upload…** — file lands in the folder and is selected immediately. For JPEG/PNG/WebP a **crop dialog** appears first: drag the corners to frame the shot, then *Crop & upload* (re-encoded at full resolution in the browser) or *Upload original*. GIF and AVIF skip cropping so animation/transparency survive. Uploads are checked three ways: extension whitelist, sniffed MIME type must match the folder kind, and a size cap (10 MB images / 200 MB videos by default).
- **Use URL…** — paste an external URL instead.
- **Alt text…** (images) — edit the `alt` attribute.

Clicking a **video** opens a dialog rather than the bare picker: pick the video file and a poster image (each via **Browse…** into the library), toggle **Autoplay silently in the background** (applies `muted autoplay loop playsinline webkit-playsinline disablepictureinpicture disableremoteplayback preload="metadata"` — the reliable recipe for a background hero video), and toggle **Show player controls**.

**Covered media:** background images and videos are often hidden behind overlay text and awkward to click. Every image/video editable also gets a small 🖼/🎬 badge pinned to its top-left corner in edit mode, so buried media stays reachable.

SVG uploads are deliberately rejected (SVG can carry scripts). Filenames are slugified on upload; collisions get `-2`, `-3`, … suffixes.

---

## 6. Usage (what your client does)

1. Open `https://your-site.com/admin.php`
2. Sign in — password, Google, Apple, or a passkey.
3. Pick a page from the list — it opens looking exactly like the live site, with editable areas outlined in blue.
4. Edit:
   - **Text / rich content** — click and type directly on the page.
   - **Images** — click (or use the 🖼 corner badge), then pick, upload + crop, or paste a URL.
   - **Videos** — click (or 🎬) for the video dialog: file, poster, background-autoplay, controls.
   - **Links** — click the link, change text and URL in the dialog.
   - **Form fields** — click any input/select/button to edit its label, placeholder, options.
   - **Lists** — "+ Add item" duplicates the last item; "×" removes one (saved immediately, with a backup).
   - **Attributes** — hover an element with `data-editable-attrs` and click ⚙.
5. Press **Save changes** in the bottom bar. Done — the live HTML file is updated instantly.

While editing, the page is a **canvas**: its own JavaScript and navigation are
frozen — links don't navigate, anchors don't scroll, forms don't submit, menus
and sliders don't react. Marked sliders become scrollable strips and marked
hidden content is shown inline (see "Sliders, popups, and accordions").
Leaving the page with unsaved changes triggers a browser warning.

---

## 7. Backups & recovery

Before every save, the current file is copied to `backups/` with a timestamp:

```
backups/index.html.2026-07-11_142530
```

The last 10 backups per file are kept — the oldest is pruned before each new one is written (change `backup_keep` in `config.php`).

**To restore:** copy a backup over the live file:

```bash
cp backups/index.html.2026-07-11_142530 index.html
```

Writes are atomic (temp file + rename), so a failed save can never leave a half-written page.

---

## 8. Configuration reference (`config.php`)

| Key | Default | Meaning |
|---|---|---|
| `admin_user` | `admin` | login name |
| `admin_hash` | — | `password_hash()` of the password |
| `site_root` | script directory | where editable HTML files live; nothing outside it can ever be read or written |
| `backup_dir` | `./backups` | backup location |
| `data_dir` | `./data` | passkeys + OAuth state (gets a deny-all `.htaccess` automatically) |
| `backup_keep` | `10` | backups kept per file (oldest pruned before each new save) |
| `extensions` | `html, htm` | editable file extensions |
| `allowed_tags` | p, br, b, strong, i, em, u, s, a, ul, ol, li, h1–h6, blockquote, span | tags permitted in `html`-type regions |
| `allowed_attrs` | `a[href,title,target]`, `span[class]` | attributes permitted per tag |
| `oauth` | disabled | Google / Apple provider credentials |
| `oauth_allowed_emails` | — | emails allowed through OAuth (case-insensitive) |
| `passkeys_file` | `./data/passkeys.json` | WebAuthn credential store |
| `media` | `assets/content/image`, `assets/content/video` | picker folders, allowed extensions, upload size caps |

---

## 9. Security notes

Built in: hashed password + PHP sessions, brute-force delay, CSRF token on every state-changing request (saves, uploads, passkey management), path confinement to `site_root` (no `../` traversal), server-side type enforcement (the editable type is read from the file, never trusted from the browser), HTML whitelist sanitizer (`script`/`style`/`iframe`/`object`/`form` are removed together with their content, event attributes stripped), URL scheme validation for images/videos/links, upload MIME sniffing, OAuth state/nonce validation with an email allowlist, and WebAuthn signature + counter verification (cloned-authenticator detection).

Your responsibilities:

- **Serve the site over HTTPS** — required for passkeys and for the login form to mean anything.
- **Change the default password** before going live.
- Optionally protect `admin.php` with an extra layer (HTTP Basic Auth or an IP allowlist in your nginx/Apache config).
- Deny web access to `backups/`, `data/`, `config.php`, and your Apple key, e.g. nginx:

  ```nginx
  location ~ ^/(backups/|data/|config\.php|AuthKey) { deny all; }
  ```

  On Apache, `data/` gets a deny-all `.htaccess` automatically; add the same file to `backups/`.

---

## 10. Known limitations

- **First save normalizes formatting.** The DOM round-trip may re-quote attributes and adjust whitespace. Content is untouched, but expect a one-time noisy git diff per file.
- **PHP 8.1–8.3 fallback** entity-encodes non-ASCII text (e.g. Cyrillic becomes `&#1053;...`). It renders correctly, but the source is ugly — use PHP 8.4+ for clean UTF-8 output.
- One admin identity. Everyone who signs in (password, OAuth, passkey) edits as the same user; there is no per-user audit trail.
- Editing is last-write-wins; two admins editing the same page simultaneously will overwrite each other.
- No image resizing/thumbnailing — cropping happens at upload, but what lands in the folder is what gets served, so compress large photos first.
- Edit mode disables the page's own JavaScript entirely. Content that only page JS can reveal needs the `data-editable-scroll` / `data-editable-reveal` markers to stay reachable.

---

## 11. Troubleshooting

| Symptom | Fix |
|---|---|
| "Could not write file. Check permissions." | Give the web server user write access to the HTML files and site directory |
| "Invalid CSRF token — reload the page." | Session expired; reload the edit page and save again |
| Page list is empty | Check `site_root` and `extensions` in `config.php` |
| Element not editable | Verify the `data-editable` id is present and unique on the page |
| "Sign-in session expired" during OAuth | The state file expired (10 min) or was reused — start the sign-in again |
| Passkey button missing on login screen | No passkeys registered yet, or `data/passkeys.json` unreadable |
| Passkey registration fails instantly | Page must be served over HTTPS (or localhost); check the browser console |
| Upload rejected as wrong type | The file content is sniffed — renaming `.mov` to `.mp4` etc. is fine, but a PHP script named `.png` is not |
| Slider/menu/popup doesn't react in edit mode | By design — page JS is frozen. Mark sliders with `data-editable-scroll` and hidden panels with `data-editable-reveal` |
| Styles look broken in edit mode for pages in subfolders | Use root-relative asset paths (`/css/style.css`) instead of relative ones |

---

## Roadmap

- SEO tooling: page `<title>` / meta description editing, image alt audit.
- Section library: blank boilerplate sections (hero, cards, form, …) and a picker to insert them into a page.
