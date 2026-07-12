<?php
/** Adminka — media library: list and upload files for the edit-mode pickers. */

declare(strict_types=1);

/** Validate a media kind ('image' | 'video') and return its config. */
function media_kind(string $kind, array $config): array
{
    $m = $config['media'][$kind] ?? null;
    if (!is_array($m)) fail(400, 'Unknown media kind.');
    return $m;
}

function media_dir(array $m, array $config): string
{
    $dir = $config['site_root'] . '/' . $m['dir'];
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

/** Public URL for a media file (root-relative, honors subdir installs). */
function media_url(array $m, string $name): string
{
    $segments = array_map('rawurlencode', explode('/', $m['dir']));
    return base_path() . '/' . implode('/', $segments) . '/' . rawurlencode($name);
}

function media_list(string $kind, array $config): never
{
    $m     = media_kind($kind, $config);
    $dir   = media_dir($m, $config);
    $items = [];
    foreach (scandir($dir) ?: [] as $f) {
        $path = $dir . '/' . $f;
        if ($f[0] === '.' || !is_file($path)) continue;
        if (!in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $m['ext'], true)) continue;
        $items[] = [
            'name'  => $f,
            'url'   => media_url($m, $f),
            'size'  => filesize($path),
            'mtime' => filemtime($path),
        ];
    }
    usort($items, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    json_out(['items' => $items]);
}

function media_upload(string $kind, array $config): never
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        fail(403, 'Invalid CSRF token — reload the page.');
    }
    $m    = media_kind($kind, $config);
    $file = $_FILES['file'] ?? null;
    if (!$file || !is_uploaded_file($file['tmp_name'] ?? '')) fail(400, 'No file uploaded.');
    if ($file['error'] !== UPLOAD_ERR_OK) fail(400, 'Upload failed (error ' . $file['error'] . ').');
    if ($file['size'] > $m['max_bytes']) {
        $limit = $m['max_bytes'] >= 1048576
            ? round($m['max_bytes'] / 1048576) . ' MB'
            : round($m['max_bytes'] / 1024) . ' KB';
        fail(400, "File too large — limit is $limit.");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $m['ext'], true)) {
        fail(400, 'File type not allowed. Allowed: ' . implode(', ', $m['ext']) . '.');
    }

    // Content check: sniffed MIME must match the folder kind (image/* or video/*).
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!str_starts_with($mime, $kind . '/')) {
        fail(400, 'File content does not look like a ' . $kind . ' (' . $mime . ').');
    }

    // Safe, readable filename; uniquify on collision.
    $base = strtolower(pathinfo($file['name'], PATHINFO_FILENAME));
    $base = trim(preg_replace('/[^a-z0-9_-]+/', '-', $base), '-') ?: $kind;
    $dir  = media_dir($m, $config);
    $name = "$base.$ext";
    for ($i = 2; file_exists("$dir/$name"); $i++) {
        $name = "$base-$i.$ext";
    }

    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        fail(500, 'Could not store the file. Check permissions.');
    }
    @chmod("$dir/$name", 0644);

    json_out(['item' => [
        'name'  => $name,
        'url'   => media_url($m, $name),
        'size'  => filesize("$dir/$name"),
        'mtime' => filemtime("$dir/$name"),
    ]]);
}
