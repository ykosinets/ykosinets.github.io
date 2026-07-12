<?php
/**
 * Adminka — Sign in with Google / Apple (OpenID Connect, authorization code flow).
 *
 * The id_token is obtained directly from the provider's token endpoint over
 * TLS, so we validate its claims (iss/aud/exp/nonce/email) rather than its
 * signature — the standard shortcut for confidential-client code flow.
 *
 * State + nonce live in server-side files (not the session cookie), because
 * Apple's form_post callback is a cross-site POST and browsers won't send a
 * SameSite=Lax session cookie with it.
 */

declare(strict_types=1);

function oauth_provider(string $name, array $config): array
{
    $known = [
        'google' => [
            'label'     => 'Google',
            'auth_url'  => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'iss'       => ['https://accounts.google.com', 'accounts.google.com'],
            'scope'     => 'openid email',
        ],
        'apple' => [
            'label'     => 'Apple',
            'auth_url'  => 'https://appleid.apple.com/auth/authorize',
            'token_url' => 'https://appleid.apple.com/auth/token',
            'iss'       => ['https://appleid.apple.com'],
            'scope'     => 'email',
        ],
    ];
    $prov = $config['oauth'][$name] ?? null;
    if (!isset($known[$name]) || !is_array($prov) || empty($prov['enabled'])) {
        fail(404, 'Sign-in provider not enabled.');
    }
    return $prov + $known[$name];   // config values win, e.g. test endpoint overrides
}

/** Providers to show on the login screen. */
function oauth_enabled_providers(array $config): array
{
    $out = [];
    foreach (['google', 'apple'] as $name) {
        if (!empty($config['oauth'][$name]['enabled'])) $out[$name] = ucfirst($name);
    }
    return $out;
}

function oauth_redirect_uri(string $provider): string
{
    return request_origin() . $_SERVER['SCRIPT_NAME'] . '?action=oauth_cb&provider=' . $provider;
}

/* ------------------------------------------------------- server-side state */

function oauth_state_dir(array $config): string
{
    $dir = data_dir($config) . '/oauth-state';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    return $dir;
}

function oauth_state_create(array $config, string $provider): array
{
    $dir = oauth_state_dir($config);
    foreach (glob($dir . '/*.json') ?: [] as $f) {          // GC stale states
        if (filemtime($f) < time() - 600) @unlink($f);
    }
    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));
    file_put_contents("$dir/$state.json", json_encode(['provider' => $provider, 'nonce' => $nonce, 'ts' => time()]));
    return [$state, $nonce];
}

function oauth_state_consume(array $config, string $state): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $state)) return null;
    $f = oauth_state_dir($config) . "/$state.json";
    if (!is_file($f)) return null;
    $d = json_decode((string)file_get_contents($f), true);
    @unlink($f);
    return (is_array($d) && time() - ($d['ts'] ?? 0) <= 600) ? $d : null;
}

/* ------------------------------------------------------------- Apple JWT */

/** ECDSA signature DER -> raw r||s (each $size bytes), as JWT ES256 requires. */
function ecdsa_der_to_raw(string $der, int $size = 32): string
{
    $off = 2;                                               // 0x30 len
    $read_int = function () use ($der, &$off, $size): string {
        $off++;                                             // 0x02
        $len = ord($der[$off++]);
        $int = substr($der, $off, $len);
        $off += $len;
        $int = ltrim($int, "\0");
        return str_pad($int, $size, "\0", STR_PAD_LEFT);
    };
    return $read_int() . $read_int();
}

/** Apple's "client secret" is a short-lived ES256 JWT signed with your .p8 key. */
function apple_client_secret(array $prov): string
{
    $pem = @file_get_contents($prov['key_file'] ?? '');
    $key = $pem ? openssl_pkey_get_private($pem) : false;
    if ($key === false) fail(500, 'Apple sign-in: cannot read key_file (.p8).');

    $header = b64url_encode(json_encode(['alg' => 'ES256', 'kid' => $prov['key_id']]));
    $claims = b64url_encode(json_encode([
        'iss' => $prov['team_id'],
        'iat' => time() - 60,
        'exp' => time() + 3000,
        'aud' => 'https://appleid.apple.com',
        'sub' => $prov['client_id'],
    ]));
    openssl_sign("$header.$claims", $der, $key, OPENSSL_ALGO_SHA256);
    return "$header.$claims." . b64url_encode(ecdsa_der_to_raw($der));
}

/* -------------------------------------------------------------- endpoints */

function oauth_start(string $name, array $config): never
{
    $prov = oauth_provider($name, $config);
    [$state, $nonce] = oauth_state_create($config, $name);

    $params = [
        'client_id'     => $prov['client_id'],
        'redirect_uri'  => oauth_redirect_uri($name),
        'response_type' => 'code',
        'scope'         => $prov['scope'],
        'state'         => $state,
        'nonce'         => $nonce,
    ];
    if ($name === 'google') $params['prompt'] = 'select_account';
    if ($name === 'apple')  $params['response_mode'] = 'form_post';

    header('Location: ' . $prov['auth_url'] . '?' . http_build_query($params));
    exit;
}

/** Human-facing error page for the OAuth callback (not a JSON API). */
function oauth_fail(string $msg): never
{
    http_response_code(403);
    $msg = htmlspecialchars($msg);
    echo "<!DOCTYPE html><meta charset=\"utf-8\"><title>Sign-in failed</title>
<body style=\"font:16px/1.5 system-ui,sans-serif;max-width:480px;margin:80px auto;padding:0 20px\">
<h1 style=\"font-size:1.2rem\">Sign-in failed</h1><p>$msg</p><p><a href=\"admin.php\">Back to sign-in</a></p>";
    exit;
}

function oauth_callback(string $name, array $config): never
{
    $prov  = oauth_provider($name, $config);
    $state = (string)($_REQUEST['state'] ?? '');
    $code  = (string)($_REQUEST['code'] ?? '');

    $st = oauth_state_consume($config, $state);
    if (!$st || $st['provider'] !== $name) oauth_fail('Sign-in session expired — please try again.');
    if ($code === '') oauth_fail('Sign-in was cancelled.');

    $secret = $name === 'apple' ? apple_client_secret($prov) : (string)$prov['client_secret'];
    [$status, $body] = http_post_form($prov['token_url'], [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => oauth_redirect_uri($name),
        'client_id'     => $prov['client_id'],
        'client_secret' => $secret,
    ]);
    $token = json_decode($body, true);
    if ($status !== 200 || !is_array($token) || empty($token['id_token'])) {
        oauth_fail('Could not verify the sign-in with ' . $prov['label'] . '.');
    }

    $parts  = explode('.', $token['id_token']);
    $claims = json_decode(b64url_decode($parts[1] ?? ''), true);
    if (!is_array($claims)) oauth_fail('Malformed identity token.');

    $email = strtolower((string)($claims['email'] ?? ''));
    if (!in_array($claims['iss'] ?? '', $prov['iss'], true)
        || ($claims['aud'] ?? '') !== $prov['client_id']
        || ($claims['exp'] ?? 0) < time()
        || !hash_equals($st['nonce'], (string)($claims['nonce'] ?? ''))
        || $email === ''
        || ($name === 'google' && empty($claims['email_verified']))) {
        oauth_fail('Identity token failed validation.');
    }

    $allowed = array_map('strtolower', $config['oauth_allowed_emails'] ?? []);
    if (!in_array($email, $allowed, true)) {
        oauth_fail("The account $email is not allowed to manage this site.");
    }

    session_regenerate_id(true);
    $_SESSION['auth'] = true;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    header('Location: admin.php');
    exit;
}
