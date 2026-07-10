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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $incoming = $_POST['content'] ?? [];

    if (is_array($incoming)) {
        $content = sanitize_content($incoming);
        $saved = save_content($contentFile, $content);

        if ($saved) {
            $message = 'Content saved.';
        } else {
            $error = 'Could not save content. Check file permissions for content/site.json.';
        }
    }
}

render_page('TorqueLink Admin', render_editor($content, $message, $error), true);

function read_content(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $json = file_get_contents($file);
    $content = json_decode((string) $json, true);

    return is_array($content) ? $content : [];
}

function save_content(string $file, array $content): bool
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

function render_editor(array $content, string $message, string $error): string
{
    return '<header class="admin-header">' .
        '<div><p>TorqueLink Media</p><h1>Content editor</h1></div>' .
        '<a href="?logout=1">Log out</a>' .
        '</header>' .
        ($message ? '<p class="notice notice--success">' . e($message) . '</p>' : '') .
        ($error ? '<p class="notice notice--error">' . e($error) . '</p>' : '') .
        '<form class="editor" method="post">' .
        '<input type="hidden" name="action" value="save">' .
        render_fields($content, 'content') .
        '<div class="save-bar"><button type="submit">Save changes</button><a href="../" target="_blank" rel="noopener">Open site</a></div>' .
        '</form>';
}

function render_fields(array $data, string $namePrefix, string $title = 'Site content'): string
{
    $html = '<section class="group"><h2>' . e(format_label($title)) . '</h2>';

    foreach ($data as $key => $value) {
        $fieldName = $namePrefix . '[' . e((string) $key) . ']';
        $label = format_label((string) $key);

        if (is_array($value)) {
            $html .= render_fields($value, $fieldName, $label);
            continue;
        }

        $rows = strlen((string) $value) > 90 ? 4 : 2;
        $html .= '<label class="field"><span>' . e($label) . '</span>' .
            '<textarea name="' . $fieldName . '" rows="' . $rows . '">' . e((string) $value) . '</textarea>' .
            '</label>';
    }

    return $html . '</section>';
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
        '</head><body><main class="' . $class . '">' . $body . '</main></body></html>';
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

body {
  margin: 0;
  background: var(--bg);
  color: var(--ink);
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  line-height: 1.5;
}

main {
  width: min(100% - 32px, 1120px);
  margin: 0 auto;
  padding: 40px 0;
}

main.narrow {
  min-height: 100vh;
  display: grid;
  place-items: center;
}

.panel,
.group,
.admin-header,
.notice,
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

h2 {
  font-size: 20px;
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

.login input,
textarea {
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

.editor {
  display: grid;
  gap: 18px;
}

.group {
  display: grid;
  gap: 16px;
  padding: 22px;
}

.group .group {
  border: 1px solid var(--line);
  box-shadow: none;
  padding: 18px;
}

.field span {
  font-size: 14px;
}

.notice {
  margin: 0 0 18px;
  padding: 14px 18px;
  font-weight: 750;
}

.notice--success {
  color: var(--good);
}

.notice--error {
  color: var(--bad);
}

.save-bar {
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

@media screen and (max-width: 768px) {
  main {
    width: min(100% - 20px, 1120px);
    padding: 20px 0;
  }

  .panel,
  .group,
  .admin-header {
    border-radius: 16px;
    padding: 18px;
  }

  .admin-header,
  .save-bar {
    align-items: stretch;
    flex-direction: column;
  }
}
CSS;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
