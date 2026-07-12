<?php
/** Adminka — small shared helpers. */

declare(strict_types=1);

function fail(int $code, string $msg): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function json_out(array $data): never
{
    header('Content-Type: application/json');
    echo json_encode($data + ['ok' => true]);
    exit;
}

/** Read and decode the JSON request body, or fail. */
function json_body(): array
{
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) fail(400, 'Bad request.');
    return $body;
}

function require_csrf(array $body): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($body['csrf'] ?? ''))) {
        fail(403, 'Invalid CSRF token — reload the page.');
    }
}

function b64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64url_decode(string $str): string
{
    $bin = base64_decode(strtr($str, '-_', '+/'), true);
    return $bin === false ? '' : $bin;
}

/** Scheme + host (+ port) the admin is being served on. */
function request_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** Host without port — WebAuthn rpId. */
function request_host(): string
{
    return preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** URL path prefix admin.php lives under ('' when in web root). */
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($dir, '/');
}

/** Ensure data_dir exists and is shielded from direct web access (Apache). */
function data_dir(array $config): string
{
    $dir = $config['data_dir'];
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) file_put_contents($ht, "Require all denied\n");
    return $dir;
}

/** POST form-encoded fields, return [status, body]. */
function http_post_form(string $url, array $fields): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, is_string($body) ? $body : ''];
    }
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($fields),
        'timeout' => 15,
        'ignore_errors' => true,
    ]]);
    $body   = (string)@file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $status = (int)$m[1];
    }
    return [$status, $body];
}
