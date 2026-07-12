<?php

declare(strict_types=1);

session_start();

$configPath = __DIR__ . '/config.php';
$samplePath = __DIR__ . '/config.sample.php';

if (!file_exists($configPath)) {
    render_page('Admin setup required', render_setup($samplePath), false);
    exit;
}

$config = require $configPath;
$contentFile = $config['content_file'] ?? (__DIR__ . '/../content/site.json');
$settingsFile = $config['settings_file'] ?? (__DIR__ . '/../content/settings.json');
$passwordHash = $config['password_hash'] ?? '';
$message = '';
$error = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['tlm_admin']);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $password = (string) ($_POST['password'] ?? '');

    if ($passwordHash && password_verify($password, $passwordHash)) {
        $_SESSION['tlm_admin'] = true;
        header('Location: index.php');
        exit;
    }

    $error = 'Invalid password.';
}

if (empty($_SESSION['tlm_admin'])) {
    render_page('TorqueLink Admin', render_login($error), false);
    exit;
}

$content = read_content($contentFile);
$settings = read_settings($settingsFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $incoming = $_POST['content'] ?? [];
    $incomingSettings = $_POST['settings'] ?? [];

    if (is_array($incoming) && is_array($incomingSettings)) {
        $content = sanitize_content($incoming);
        $settings = sanitize_settings($incomingSettings);
        $destinationEmail = (string) ($settings['contact']['recipient_email'] ?? '');

        if (!filter_var($destinationEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid destination email.';
        } else {
            $contentSaved = save_content($contentFile, $content);
            $settingsSaved = save_settings($settingsFile, $settings);

            if ($contentSaved && $settingsSaved) {
                $message = 'Changes saved.';
            } else {
                $error = 'Could not save changes. Check file permissions for content files.';
            }
        }
    }
}

render_page('TorqueLink Admin', render_editor($content, $settings, $message, $error), true);

function read_content(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $json = file_get_contents($file);
    $content = json_decode((string) $json, true);

    return is_array($content) ? $content : [];
}

function read_settings(string $file): array
{
    $defaults = [
        'contact' => [
            'recipient_email' => 'info@torquelinkmedia.com',
        ],
    ];

    if (!file_exists($file)) {
        return $defaults;
    }

    $json = file_get_contents($file);
    $settings = json_decode((string) $json, true);

    if (!is_array($settings)) {
        return $defaults;
    }

    return array_replace_recursive($defaults, $settings);
}

function save_settings(string $file, array $settings): bool
{
    return save_json($file, $settings);
}

function save_content(string $file, array $content): bool
{
    return save_json($file, $content);
}

function save_json(string $file, array $content): bool
{
    $directory = dirname($file);

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    return file_put_contents($file, $json . PHP_EOL, LOCK_EX) !== false;
}

function sanitize_settings(array $settings): array
{
    $email = clean_admin_email((string) ($settings['contact']['recipient_email'] ?? ''));

    return [
        'contact' => [
            'recipient_email' => $email,
        ],
    ];
}

function clean_admin_email(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function sanitize_content(mixed $value): mixed
{
    if (is_array($value)) {
        $clean = [];

        foreach ($value as $key => $item) {
            $clean[$key] = sanitize_content($item);
        }

        return $clean;
    }

    return trim((string) $value);
}

function render_setup(string $samplePath): string
{
    return '<div class="panel">' .
        '<h1>Admin setup required</h1>' .
        '<p>Copy <code>admin/config.sample.php</code> to <code>admin/config.php</code> on the server and replace the password hash.</p>' .
        '<pre>cp admin/config.sample.php admin/config.php' . PHP_EOL .
        'php -r \'echo password_hash("your-password-here", PASSWORD_DEFAULT), PHP_EOL;\'</pre>' .
        '<p>The sample file is expected at: <code>' . e($samplePath) . '</code></p>' .
        '</div>';
}

function render_login(string $error): string
{
    return '<form class="panel login" method="post">' .
        '<input type="hidden" name="action" value="login">' .
        '<h1>TorqueLink Admin</h1>' .
        '<p>Edit site copy without rebuilding the static files.</p>' .
        ($error ? '<p class="notice notice--error">' . e($error) . '</p>' : '') .
        '<label>Password<input type="password" name="password" autocomplete="current-password" required autofocus></label>' .
        '<button type="submit">Log in</button>' .
        '</form>';
}

function render_editor(array $content, array $settings, string $message, string $error): string
{
    $toast = '';

    if ($message || $error) {
        $toastType = $error ? 'error' : 'success';
        $toastText = $error ?: $message;
        $toast = '<div class="toast toast--' . $toastType . '" role="status" aria-live="polite">' . e($toastText) . '</div>';
    }

    return $toast .
        '<header class="admin-header">' .
        '<div><p>TorqueLink Media</p><h1>Content editor</h1></div>' .
        '<a href="?logout=1">Log out</a>' .
        '</header>' .
        '<form class="admin-layout" method="post">' .
        '<input type="hidden" name="action" value="save">' .
        render_side_nav($content, true) .
        '<div class="editor">' .
        render_settings($settings) .
        render_fields($content, 'content', 'Site content', 0) .
        '<div class="save-bar"><button type="submit">Save changes</button><a href="../" target="_blank" rel="noopener">Open site</a></div>' .
        '</div>' .
        '</form>';
}

function render_side_nav(array $content, bool $includeSettings = false): string
{
    $html = '<aside class="side-nav" aria-label="Content sections"><p>Sections</p><nav>';

    if ($includeSettings) {
        $html .= '<a href="#section-form-settings" data-admin-nav>Form Settings</a>';
    }

    foreach ($content as $key => $value) {
        if (is_array($value)) {
            $id = section_id((string) $key);
            $html .= '<a href="#' . e($id) . '" data-admin-nav>' . e(format_label((string) $key)) . '</a>';
        }
    }

    return $html . '</nav></aside>';
}

function render_settings(array $settings): string
{
    $email = (string) ($settings['contact']['recipient_email'] ?? 'info@torquelinkmedia.com');

    return '<details class="group group--depth-1" id="section-form-settings" open data-admin-section>' .
        '<summary><span>Form Settings</span><span class="group__chevron" aria-hidden="true"></span></summary>' .
        '<div class="group__body">' .
        '<label class="field"><span>Destination Email</span>' .
        '<input type="email" name="settings[contact][recipient_email]" value="' . e($email) . '" required>' .
        '<small>Contact form enquiries will be sent to this address.</small>' .
        '</label>' .
        '</div></details>';
}

function render_fields(array $data, string $namePrefix, string $title = 'Site content', int $depth = 0, int $index = 0): string
{
    if ($depth === 0) {
        $html = '';

        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $html .= render_fields($value, $namePrefix . '[' . e((string) $key) . ']', format_label((string) $key), 1, $index);
            $index++;
        }

        return $html;
    }

    $id = $depth === 1 ? ' id="' . e(section_id($title)) . '"' : '';
    $open = '';
    $html = '<details class="group group--depth-' . $depth . '"' . $id . $open . ' data-admin-section>' .
        '<summary><span>' . e(format_label($title)) . '</span><span class="group__chevron" aria-hidden="true"></span></summary>' .
        '<div class="group__body">';

    foreach ($data as $key => $value) {
        $fieldName = $namePrefix . '[' . e((string) $key) . ']';
        $label = format_label((string) $key);

        if (is_array($value)) {
            $html .= render_fields($value, $fieldName, $label, $depth + 1, 0);
            continue;
        }

        $rows = strlen((string) $value) > 90 ? 4 : 2;
        $html .= '<label class="field"><span>' . e($label) . '</span>' .
            '<textarea name="' . $fieldName . '" rows="' . $rows . '">' . e((string) $value) . '</textarea>' .
            '</label>';
    }

    return $html . '</div></details>';
}

function section_id(string $value): string
{
    $value = strtolower(trim(preg_replace('/(?<!^)[A-Z]/', '-$0', $value) ?? $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

    return 'section-' . trim($value, '-');
}

function format_label(string $value): string
{
    $value = preg_replace('/(?<!^)[A-Z]/', ' $0', $value) ?? $value;
    $value = str_replace(['_', '-'], ' ', $value);

    return ucwords($value);
}

function render_page(string $title, string $body, bool $wide): void
{
    $class = $wide ? 'wide' : 'narrow';

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">' .
        '<meta name="viewport" content="width=device-width, initial-scale=1">' .
        '<title>' . e($title) . '</title>' .
        '<style>' . admin_css() . '</style>' .
        '</head><body><main class="' . $class . '">' . $body . '</main><script>' . admin_js() . '</script></body></html>';
}

function admin_css(): string
{
    return <<<'CSS'
:root {
  color-scheme: light;
  --bg: #eef4fb;
  --panel: #ffffff;
  --ink: #071d36;
  --muted: #5e7288;
  --line: #d7e2ee;
  --blue: #2167ab;
  --blue-dark: #12385a;
  --good: #116d42;
  --bad: #b42318;
}

* {
  box-sizing: border-box;
}

html {
  scroll-behavior: smooth;
}

body {
  margin: 0;
  background: var(--bg);
  color: var(--ink);
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  line-height: 1.5;
}

main {
  width: min(100% - 32px, 1240px);
  margin: 0 auto;
  padding: 40px 0;
}

main.narrow {
  min-height: 100vh;
  display: grid;
  place-items: center;
}

.panel,
.admin-header,
.side-nav,
.group,
.save-bar {
  background: var(--panel);
  border-radius: 22px;
  box-shadow: 0 18px 50px rgba(7, 29, 54, 0.08);
}

.panel {
  width: min(100%, 460px);
  padding: 32px;
}

h1,
h2,
p {
  margin-top: 0;
}

h1 {
  font-size: clamp(30px, 4vw, 46px);
  line-height: 1;
}

p {
  color: var(--muted);
}

code,
pre {
  background: #f4f8fc;
  border-radius: 10px;
  color: var(--blue-dark);
}

code {
  padding: 2px 6px;
}

pre {
  overflow-x: auto;
  padding: 14px;
}

.login label,
.field {
  display: grid;
  gap: 8px;
  color: var(--blue-dark);
  font-weight: 750;
}

.panel.login label {
  margin-top: 20px;
}

.login input,
textarea,
.field input {
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #f8fbff;
  color: var(--ink);
  font: inherit;
}

.login input {
  min-height: 50px;
  padding: 0 14px;
}

.field input {
  min-height: 50px;
  padding: 0 14px;
}

textarea {
  min-height: 56px;
  resize: vertical;
  padding: 12px 14px;
}

button,
.save-bar a,
.admin-header a {
  border: 0;
  border-radius: 999px;
  background: var(--blue);
  color: #fff;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding: 0 22px;
  font: inherit;
  font-weight: 800;
  text-decoration: none;
}

.admin-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 18px;
  padding: 22px 24px;
}

.admin-header p {
  margin-bottom: 6px;
  color: var(--blue);
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.admin-header h1 {
  margin: 0;
}

.admin-layout {
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr);
  gap: 18px;
  align-items: start;
}

.side-nav {
  position: sticky;
  top: 18px;
  display: grid;
  gap: 14px;
  padding: 18px;
}

.side-nav p {
  margin: 0;
  color: var(--blue-dark);
  font-size: 13px;
  font-weight: 850;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.side-nav nav {
  display: grid;
  gap: 4px;
}

.side-nav a {
  border-radius: 12px;
  color: var(--muted);
  font-size: 14px;
  font-weight: 750;
  padding: 10px 12px;
  text-decoration: none;
}

.side-nav a:hover,
.side-nav a.is-active {
  background: #e7f0fa;
  color: var(--blue);
}

.editor {
  display: grid;
  gap: 14px;
  min-width: 0;
}

.group {
  scroll-margin-top: 24px;
  overflow: hidden;
}

.group[open] {
  box-shadow: 0 18px 50px rgba(7, 29, 54, 0.1);
}

.group summary {
  align-items: center;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  gap: 16px;
  list-style: none;
  padding: 20px 22px;
  color: var(--blue-dark);
  font-size: 19px;
  font-weight: 850;
}

.group summary::-webkit-details-marker {
  display: none;
}

.group__chevron {
  flex: 0 0 auto;
  width: 10px;
  height: 10px;
  border-right: 2px solid currentColor;
  border-bottom: 2px solid currentColor;
  rotate: 45deg;
  transition: rotate 180ms ease, translate 180ms ease;
}

.group[open] > summary .group__chevron {
  rotate: 225deg;
  translate: 0 4px;
}

.group__body {
  display: grid;
  gap: 16px;
  padding: 0 22px 22px;
}

.group .group {
  border: 1px solid var(--line);
  box-shadow: none;
}

.group .group summary {
  padding: 16px 18px;
  font-size: 16px;
}

.group .group .group__body {
  padding: 0 18px 18px;
}

.group--depth-3,
.group--depth-4 {
  background: #f8fbff;
}

.field span {
  font-size: 14px;
}

.field small {
  color: var(--muted);
  font-size: 13px;
  font-weight: 500;
}

.toast {
  position: fixed;
  z-index: 20;
  top: 18px;
  right: 18px;
  max-width: min(360px, calc(100vw - 36px));
  border-radius: 18px;
  background: var(--panel);
  box-shadow: 0 18px 50px rgba(7, 29, 54, 0.18);
  color: var(--blue-dark);
  font-weight: 800;
  padding: 14px 18px;
  animation: toast-in 220ms ease both;
  transition: opacity 180ms ease, transform 180ms ease;
}

.toast--success {
  border-left: 5px solid var(--good);
}

.toast--error {
  border-left: 5px solid var(--bad);
}

@keyframes toast-in {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.notice {
  margin: 0 0 18px;
  padding: 14px 18px;
  font-weight: 750;
}

.notice--error {
  color: var(--bad);
}

.save-bar {
  margin-left: auto;
  position: sticky;
  bottom: 18px;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 14px;
}

.save-bar a {
  background: var(--blue-dark);
}

@media screen and (max-width: 992px) {
  .admin-layout {
    grid-template-columns: 1fr;
  }

  .side-nav {
    position: static;
  }

  .side-nav nav {
    display: flex;
    overflow-x: auto;
    padding-bottom: 4px;
  }

  .side-nav a {
    white-space: nowrap;
  }
}

@media screen and (max-width: 768px) {
  main {
    width: min(100% - 20px, 1120px);
    padding: 20px 0;
  }

  .panel,
  .group,
  .admin-header,
  .side-nav {
    border-radius: 16px;
  }

  .panel,
  .admin-header,
  .side-nav,
  .group summary,
  .group__body {
    padding-left: 18px;
    padding-right: 18px;
  }

  .admin-header,
  .save-bar {
    align-items: stretch;
    flex-direction: column;
  }
}
CSS;
}

function admin_js(): string
{
    return <<<'JS'
const navLinks = [...document.querySelectorAll("[data-admin-nav]")];
const sections = [...document.querySelectorAll(".group--depth-1")];
const toast = document.querySelector(".toast");

navLinks.forEach((link) => {
  link.addEventListener("click", () => {
    const id = link.getAttribute("href");
    const section = id ? document.querySelector(id) : null;

    if (section && section.tagName === "DETAILS") {
      section.open = true;
    }
  });
});

if (sections.length && "IntersectionObserver" in window) {
  const observer = new IntersectionObserver(
    (entries) => {
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

      if (!visible) {
        return;
      }

      navLinks.forEach((link) => {
        link.classList.toggle("is-active", link.getAttribute("href") === "#" + visible.target.id);
      });
    },
    {
      rootMargin: "-15% 0px -70% 0px",
      threshold: [0.1, 0.4, 0.8],
    }
  );

  sections.forEach((section) => observer.observe(section));
}

if (toast) {
  window.setTimeout(() => {
    toast.style.opacity = "0";
    toast.style.transform = "translateY(-8px)";
  }, 2800);

  window.setTimeout(() => {
    toast.remove();
  }, 3100);
}
JS;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
