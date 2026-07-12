<?php
/**
 * Adminka — passkey (WebAuthn) support, dependency-free.
 *
 * Registration uses attestation "none" (we trust the browser, like most
 * consumer sites). Credentials are stored in config['passkeys_file'].
 * Supported algorithms: ES256 (-7) and RS256 (-257).
 */

declare(strict_types=1);

/* ------------------------------------------------------------- credential store */

function passkeys_load(array $config): array
{
    $f = $config['passkeys_file'];
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    if (!is_array($d)) $d = [];
    return $d + ['user_handle' => null, 'creds' => []];
}

function passkeys_save(array $config, array $data): void
{
    data_dir($config);
    $f = $config['passkeys_file'];
    file_put_contents($f, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($f, 0600);
}

/* ------------------------------------------------------------------ CBOR / DER */

/** Decode one CBOR item (uint, negint, bytes, text, array, map — enough for WebAuthn). */
function cbor_decode(string $bin, int &$off = 0): mixed
{
    if ($off >= strlen($bin)) throw new RuntimeException('CBOR: truncated');
    $ib = ord($bin[$off++]);
    $mt = $ib >> 5;
    $ai = $ib & 31;

    if ($ai < 24)       { $n = $ai; }
    elseif ($ai === 24) { $n = ord($bin[$off]); $off += 1; }
    elseif ($ai === 25) { $n = unpack('n', substr($bin, $off, 2))[1]; $off += 2; }
    elseif ($ai === 26) { $n = unpack('N', substr($bin, $off, 4))[1]; $off += 4; }
    elseif ($ai === 27) { $n = unpack('J', substr($bin, $off, 8))[1]; $off += 8; }
    else throw new RuntimeException('CBOR: unsupported length encoding');

    switch ($mt) {
        case 0: return $n;
        case 1: return -1 - $n;
        case 2:
        case 3:
            $v = substr($bin, $off, $n); $off += $n;
            if (strlen($v) !== $n) throw new RuntimeException('CBOR: truncated string');
            return $v;
        case 4:
            $a = [];
            for ($i = 0; $i < $n; $i++) $a[] = cbor_decode($bin, $off);
            return $a;
        case 5:
            $map = [];
            for ($i = 0; $i < $n; $i++) {
                $k = cbor_decode($bin, $off);
                $map[$k] = cbor_decode($bin, $off);
            }
            return $map;
        case 6: return cbor_decode($bin, $off);   // tag — unwrap
        default: throw new RuntimeException('CBOR: unsupported major type ' . $mt);
    }
}

function der_len(int $len): string
{
    if ($len < 128) return chr($len);
    $b = ltrim(pack('N', $len), "\0");
    return chr(0x80 | strlen($b)) . $b;
}
function der_int(string $bytes): string
{
    $bytes = ltrim($bytes, "\0");
    if ($bytes === '' || ord($bytes[0]) > 127) $bytes = "\0" . $bytes;
    return "\x02" . der_len(strlen($bytes)) . $bytes;
}
function der_seq(string $inner): string  { return "\x30" . der_len(strlen($inner)) . $inner; }
function der_bits(string $inner): string { return "\x03" . der_len(strlen($inner) + 1) . "\0" . $inner; }

/** Convert a COSE public key (CBOR map) to PEM. Returns [pem, alg] or fails. */
function cose_to_pem(array $cose): array
{
    $kty = $cose[1] ?? null;
    $alg = $cose[3] ?? null;

    if ($kty === 2 && $alg === -7) {                     // EC2 / ES256, P-256 only
        if (($cose[-1] ?? null) !== 1) fail(400, 'Passkey: unsupported EC curve.');
        $x = $cose[-2] ?? ''; $y = $cose[-3] ?? '';
        if (strlen($x) !== 32 || strlen($y) !== 32) fail(400, 'Passkey: bad EC key.');
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004') . $x . $y;
    } elseif ($kty === 3 && $alg === -257) {             // RSA / RS256
        $n = $cose[-1] ?? ''; $e = $cose[-2] ?? '';
        if ($n === '' || $e === '') fail(400, 'Passkey: bad RSA key.');
        $rsaOid = hex2bin('300d06092a864886f70d0101010500');
        $der    = der_seq($rsaOid . der_bits(der_seq(der_int($n) . der_int($e))));
    } else {
        fail(400, 'Passkey: unsupported key algorithm.');
    }

    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    if (openssl_pkey_get_public($pem) === false) fail(400, 'Passkey: invalid public key.');
    return [$pem, $alg];
}

/* -------------------------------------------------------------------- authData */

/** Split WebAuthn authenticator data into its fields. */
function parse_auth_data(string $ad): array
{
    if (strlen($ad) < 37) fail(400, 'Passkey: malformed authenticator data.');
    $out = [
        'rpIdHash'  => substr($ad, 0, 32),
        'flags'     => ord($ad[32]),
        'signCount' => unpack('N', substr($ad, 33, 4))[1],
        'credId'    => null,
        'cose'      => null,
    ];
    if ($out['flags'] & 0x40) {                          // AT: attested credential data
        $len = unpack('n', substr($ad, 53, 2))[1];
        $out['credId'] = substr($ad, 55, $len);
        $off = 0;
        $out['cose'] = cbor_decode(substr($ad, 55 + $len), $off);
    }
    return $out;
}

/** Common clientDataJSON checks for create/get ceremonies. */
function check_client_data(string $raw, string $expectType): void
{
    $cd = json_decode($raw, true);
    if (!is_array($cd)
        || ($cd['type'] ?? '') !== $expectType
        || !hash_equals($_SESSION['webauthn_challenge'] ?? '', (string)($cd['challenge'] ?? ''))
        || ($cd['origin'] ?? '') !== request_origin()) {
        fail(400, 'Passkey: challenge or origin mismatch — reload and try again.');
    }
}

/* ------------------------------------------------------------------- endpoints */

function passkey_register_options(array $config): never
{
    $store = passkeys_load($config);
    if ($store['user_handle'] === null) {
        $store['user_handle'] = b64url_encode(random_bytes(16));
        passkeys_save($config, $store);
    }
    $_SESSION['webauthn_challenge'] = b64url_encode(random_bytes(32));

    json_out(['publicKey' => [
        'challenge' => $_SESSION['webauthn_challenge'],
        'rp'        => ['name' => 'Adminka', 'id' => request_host()],
        'user'      => [
            'id'          => $store['user_handle'],
            'name'        => $config['admin_user'],
            'displayName' => $config['admin_user'],
        ],
        'pubKeyCredParams' => [
            ['type' => 'public-key', 'alg' => -7],
            ['type' => 'public-key', 'alg' => -257],
        ],
        'authenticatorSelection' => ['residentKey' => 'preferred', 'userVerification' => 'preferred'],
        'excludeCredentials' => array_map(
            fn($c) => ['type' => 'public-key', 'id' => $c['id']],
            $store['creds']
        ),
        'attestation' => 'none',
        'timeout'     => 60000,
    ]]);
}

function passkey_register(array $config): never
{
    $body = json_body();
    require_csrf($body);
    if (empty($_SESSION['webauthn_challenge'])) fail(400, 'Passkey: no pending challenge.');

    $rawClient = b64url_decode((string)($body['response']['clientDataJSON'] ?? ''));
    check_client_data($rawClient, 'webauthn.create');

    $attObj = cbor_decode(b64url_decode((string)($body['response']['attestationObject'] ?? '')));
    if (!is_array($attObj) || !isset($attObj['authData'])) fail(400, 'Passkey: bad attestation object.');
    $ad = parse_auth_data($attObj['authData']);

    if (!hash_equals(hash('sha256', request_host(), true), $ad['rpIdHash'])) fail(400, 'Passkey: rpId mismatch.');
    if (!($ad['flags'] & 0x01)) fail(400, 'Passkey: user presence not confirmed.');
    if ($ad['credId'] === null || !is_array($ad['cose'])) fail(400, 'Passkey: no credential data.');

    [$pem, $alg] = cose_to_pem($ad['cose']);
    $credId = b64url_encode($ad['credId']);

    $store = passkeys_load($config);
    foreach ($store['creds'] as $c) {
        if (hash_equals($c['id'], $credId)) fail(400, 'This passkey is already registered.');
    }
    $label = trim((string)($body['label'] ?? '')) ?: 'Passkey';
    $store['creds'][] = [
        'id'        => $credId,
        'pem'       => $pem,
        'alg'       => $alg,
        'signCount' => $ad['signCount'],
        'label'     => mb_substr($label, 0, 60),
        'created'   => date('Y-m-d H:i'),
    ];
    passkeys_save($config, $store);
    unset($_SESSION['webauthn_challenge']);
    json_out(['registered' => true]);
}

function passkey_login_options(array $config): never
{
    $store = passkeys_load($config);
    if (!$store['creds']) fail(400, 'No passkeys registered.');
    $_SESSION['webauthn_challenge'] = b64url_encode(random_bytes(32));

    json_out(['publicKey' => [
        'challenge'        => $_SESSION['webauthn_challenge'],
        'rpId'             => request_host(),
        'allowCredentials' => array_map(
            fn($c) => ['type' => 'public-key', 'id' => $c['id']],
            $store['creds']
        ),
        'userVerification' => 'preferred',
        'timeout'          => 60000,
    ]]);
}

function passkey_login(array $config): never
{
    $body = json_body();
    if (empty($_SESSION['webauthn_challenge'])) fail(400, 'Passkey: no pending challenge.');

    $store = passkeys_load($config);
    $credId = (string)($body['id'] ?? '');
    $cred = null;
    foreach ($store['creds'] as $i => $c) {
        if (hash_equals($c['id'], $credId)) { $cred = $c; $credIdx = $i; break; }
    }
    if (!$cred) { usleep(500_000); fail(403, 'Unknown passkey.'); }

    $rawClient = b64url_decode((string)($body['response']['clientDataJSON'] ?? ''));
    check_client_data($rawClient, 'webauthn.get');

    $ad = b64url_decode((string)($body['response']['authenticatorData'] ?? ''));
    if (strlen($ad) < 37
        || !hash_equals(hash('sha256', request_host(), true), substr($ad, 0, 32))
        || !(ord($ad[32]) & 0x01)) {
        fail(400, 'Passkey: bad authenticator data.');
    }

    $sig    = b64url_decode((string)($body['response']['signature'] ?? ''));
    $signed = $ad . hash('sha256', $rawClient, true);
    if (openssl_verify($signed, $sig, $cred['pem'], OPENSSL_ALGO_SHA256) !== 1) {
        usleep(500_000);
        fail(403, 'Passkey signature check failed.');
    }

    // Clone detection: a counter that goes backwards means a copied key.
    $newCount = unpack('N', substr($ad, 33, 4))[1];
    if ($newCount !== 0 && $cred['signCount'] !== 0 && $newCount <= $cred['signCount']) {
        fail(403, 'Passkey counter mismatch — possible cloned credential.');
    }
    $store['creds'][$credIdx]['signCount'] = $newCount;
    passkeys_save($config, $store);

    unset($_SESSION['webauthn_challenge']);
    session_regenerate_id(true);
    $_SESSION['auth'] = true;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    json_out(['authenticated' => true]);
}

function passkey_delete(array $config): never
{
    $body = json_body();
    require_csrf($body);
    $store = passkeys_load($config);
    $before = count($store['creds']);
    $store['creds'] = array_values(array_filter(
        $store['creds'],
        fn($c) => !hash_equals($c['id'], (string)($body['id'] ?? ''))
    ));
    if (count($store['creds']) === $before) fail(404, 'Passkey not found.');
    passkeys_save($config, $store);
    json_out(['deleted' => true]);
}
